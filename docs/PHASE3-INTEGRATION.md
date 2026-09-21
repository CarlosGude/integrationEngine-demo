# 🚀 Phase 3: Integration with IntegrationEngine v7.0

**Demo Project Upgrade Plan** — Remove custom code, use engine features

> ## ✅ Completed
>
> This plan has been carried out. The bundle is on v7.0.2, `app.client.stripe`
> uses the engine's `FormEncodedClientAdapter`, and `GetPricesMapper` uses the
> engine's `CsvParser`. The paths below under "Current State" describe the code
> **as it was before the migration** — they no longer exist.
>
> Kept as the record of what changed and why.

---

## Overview

IntegrationEngine v7.0 shipped the `FormEncodedClientAdapter` and the `CsvParser`
utility, which let this demo drop ~160 lines of custom boilerplate.

This document details exactly what changed.

---

## Task 3.1: Remove StripeFormClientAdapter

**Effort:** 30 minutes | **Lines Removed:** 129

### Current State
```
src/Billing/Infrastructure/Http/StripeFormClientAdapter.php (129 lines)
├─ Handles form-encoded request bodies
├─ Sets Content-Type: application/x-www-form-urlencoded
└─ Registered in config/services.yaml
```

### Changes After v7.0

**DELETE:**
- `src/Billing/Infrastructure/Http/StripeFormClientAdapter.php`

**UPDATE config/services.yaml:**
```yaml
# BEFORE:
app.client.stripe:
    class: App\Billing\Infrastructure\Http\StripeFormClientAdapter
    arguments:
        $httpClient: '@http_client'
        $baseUrl: 'https://api.stripe.com'
        $defaultHeaders:
            Authorization: 'Bearer %env(STRIPE_SECRET_KEY)%'

# AFTER:
# (Use built-in engine adapter)
```

**UPDATE config/packages/integration_engine.yaml:**
```yaml
# BEFORE:
stripe:
    client_service: app.client.stripe  # Custom adapter
    config_path: '%kernel.project_dir%/src/Integrations/Stripe/Stripe.yaml'

# AFTER:
stripe:
    client_service: integration_engine.client.form_encoded  # Built-in
    config_path: '%kernel.project_dir%/src/Integrations/Stripe/Stripe.yaml'
```

**UPDATE tests:**
- Delete `tests/Integrations/StripeFormClientAdapterTest.php` (if any)
- Stripe integration tests still pass (no logic changed, just using engine adapter)

### Verification
```bash
# After making changes, verify:
php bin/console billing:simulate-rental 550        # Should work
php bin/console billing:simulate-rental 550 --chaos # Should work
```

---

## Task 3.2: Simplify CSV Parser Usage

**Effort:** 45 minutes | **Lines Removed:** 35 (from mapper)

### Current State
```
src/Integrations/Supplier/Mappers/GetPricesMapper.php
├─ Contains parseCSV() method (35 lines)
├─ Handles column mapping
└─ Handles encoding/escaping
```

### Changes After v7.0

**UPDATE src/Integrations/Supplier/Mappers/GetPricesMapper.php:**

```php
// BEFORE (66 lines):
final class GetPricesMapper extends AbstractMapper {
    protected static function transform($action, $response, $headers) {
        $csvContent = $response['body'] ?? '';
        $rows = self::parseCSV($csvContent);
        return new GetPricesResponse($rows);
    }
    
    private static function parseCSV(string $csvContent): array {
        // 30+ lines of parsing logic
    }
}

// AFTER (25 lines):
use IntegrationEngine\Utils\CsvParser;

final class GetPricesMapper extends AbstractMapper {
    protected static function transform($action, $response, $headers) {
        $csvContent = $response['body'] ?? '';
        $rows = CsvParser::parse($csvContent);  // ← 1 line
        return new GetPricesResponse($rows);
    }
}
// Done!
```

**What Changed:**
- Removed: `parseCSV()` method (30 lines)
- Removed: `columns()` helper method (5 lines)
- Added: `use IntegrationEngine\Utils\CsvParser` (1 line)
- Changed: 1 line in transform method

**Net Result:**
- `GetPricesMapper.php`: 66 → 25 lines (-41 lines)
- `CsvParser` handles all edge cases in engine
- Mapper focuses on business logic

**UPDATE tests:**

```php
// BEFORE: Test parseCSV() method
public function testParsesCsvCorrectly(): void {
    $csv = "name,price\nWidget,9.99\n";
    $mapper = new GetPricesMapper();
    $result = $mapper->parseCSV($csv);
    // ...
}

// AFTER: No change needed
// Tests still verify GetPricesMapper's transform() works
// CsvParser tested in engine
```

### Verification
```bash
# Test CSV integration
php bin/console catalog:benchmark   # Uses supplier prices
# Should show same performance, cleaner code
```

---

## Task 3.3: Documentation & Custom Adapter Guide

**Effort:** 2-4 hours | **Output:** 1000+ lines

### Files to Create/Update

**CREATE docs/CUSTOM-ADAPTERS.md** ✅ (Done in this phase)
- Patterns for building custom adapters
- Real-world examples (CSV, XML, Protobuf, form-encoded)
- Migration path from v6 to v7
- Production checklist

**CREATE docs/PHASE3-INTEGRATION.md** ✅ (This document)
- Exact changes when v7.0 is released
- Step-by-step migration guide
- Before/after code comparisons

**UPDATE README.md:**
```markdown
## 📊 Code Quality After Phase 3

| Metric | Before | After | Change |
|--------|--------|-------|--------|
| Custom code lines | 410 | ~100 | -76% |
| Adapter code | 164 | 0 | Moved to engine |
| Mapper complexity | High | Low | Uses utilities |
| External dependencies | 4 adapters | 2 adapters | Simplified |
```

**UPDATE docs/ARCHITECTURE.md:**
- Add section: "Adapter Pattern & Custom Implementations"
- Update when v7.0 built-ins are available

---

## Summary of Changes

### Files to Delete
```
src/Billing/Infrastructure/Http/StripeFormClientAdapter.php (129 lines)
```

### Files to Modify
```
config/services.yaml
├─ Remove app.client.stripe definition

config/packages/integration_engine.yaml
├─ stripe.client_service: app.client.stripe → integration_engine.client.form_encoded

src/Integrations/Supplier/Mappers/GetPricesMapper.php
├─ Remove parseCSV() method (30 lines)
├─ Remove columns() helper (5 lines)
├─ Add CsvParser::parse() call (1 line)

docs/ARCHITECTURE.md
├─ Add adapter pattern section
├─ Link to CUSTOM-ADAPTERS.md
```

### Files to Create
```
docs/CUSTOM-ADAPTERS.md ✅ (Done)
docs/PHASE3-INTEGRATION.md ✅ (Done)
```

---

## Timeline

### When Engine v7.0 is Released

1. **Day 1 (30 min):** Delete StripeFormClientAdapter
2. **Day 2 (45 min):** Update config + mapper
3. **Day 3 (1 hour):** Run tests, verify everything works
4. **Day 4 (30 min):** Update README, commit
5. **Total:** ~3 hours of work

### Code Change Summary
```
Additions:
+ 1 line: use IntegrationEngine\Utils\CsvParser;
+ 1 line: CsvParser::parse($csvContent);

Deletions:
- 129 lines: StripeFormClientAdapter.php
- 30 lines: parseCSV() method
- 5 lines: columns() helper
- Total: -164 lines

Net Change: -162 lines of code
```

---

## Before & After

### Before (Today - v6.0.0)

```
Custom Code: 410 lines
├─ CSV Parser in mapper: 35 lines
├─ StripeFormClientAdapter: 129 lines
├─ Rate limiting middleware: 59 lines
├─ Circuit breaker pattern: ~187 lines (for education)

Adapters Used:
├─ REST (engine built-in)
├─ GraphQL (engine built-in)
├─ Form-encoded (custom)
└─ CSV (parsed in mapper)
```

### After (Phase 3 - v7.0+)

```
Custom Code: ~100 lines
├─ Rate limiting middleware: 59 lines
├─ Resilience patterns: ~41 lines (RetryMiddleware, CircuitBreaker, etc.)
├─ ChaosMonkey: ~100 lines (for testing)

Adapters Used:
├─ REST (engine built-in)
├─ GraphQL (engine built-in)
├─ Form-encoded (engine built-in) ← v7.0
└─ CSV (engine utility + mapper) ← v7.0

Code Reduction: -76% custom boilerplate
```

---

## Testing Strategy

### Pre-Migration Tests (Ensure v7.0 works)
```bash
# 1. Update composer.json to require engine v7.0
composer require carlosgude/integration-engine:^7.0

# 2. Run existing test suite
make test

# 3. If tests fail, debug incompatibilities
# (Should be minimal; only adapter implementations change)
```

### Post-Migration Tests (Verify no regressions)
```bash
# 1. Run test suite again
make test

# 2. Test manual workflows
php bin/console catalog:benchmark
php bin/console billing:simulate-rental 550
php bin/console billing:simulate-rental 550 --chaos

# 3. Verify all routes still work
open http://localhost:8080/en/store
open http://localhost:8080/es/store
```

---

## Rollback Plan

If engine v7.0 has breaking changes:

1. Revert composer changes: `composer require carlosgude/integration-engine:^6.0`
2. Restore deleted files from git: `git checkout HEAD~1 src/Billing/Infrastructure/Http/`
3. Undo config changes: `git diff` and manually revert

All changes are backwards compatible, so rollback is simple.

---

## Success Criteria

After Phase 3:

- ✅ All 55+ tests pass
- ✅ No custom adapter code (use engine v7.0)
- ✅ Mapper code simplified by 35+ lines
- ✅ Documentation updated
- ✅ Custom code reduced to <100 lines (from 410)
- ✅ No functionality lost
- ✅ Code review approved

---

## Related Documentation

- **Custom Adapters Guide:** [CUSTOM-ADAPTERS.md](CUSTOM-ADAPTERS.md)
- **Resilience Patterns:** [RESILIENCE-PATTERNS.md](RESILIENCE-PATTERNS.md)
- **Architecture:** [ARCHITECTURE.md](ARCHITECTURE.md)
- **Project Analysis:** [PROJECT-ANALYSIS.md](PROJECT-ANALYSIS.md)

---

## Questions?

- **"When will engine v7.0 be released?"** → Check https://github.com/carlosgude/integration-engine/releases
- **"Can I use this guide before v7.0?"** → Yes! It explains the pattern; you can build adapters today
- **"What if I need FormEncodedClientAdapter now?"** → It's in `src/Billing/Infrastructure/Http/StripeFormClientAdapter.php` — copy it to your project

---

**Generated:** 2026-09-21 | IntegrationEngine Demo — Phase 3 Integration Plan

---

*Status claims in this document last verified against the code on 2026-09-21, at `d67f899`.*
*Enforced for file paths by `tests/Documentation/DocumentedPathsExistTest.php`.*
