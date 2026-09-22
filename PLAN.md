# IntegrationEngine Demo - Implementation Plan

## Project Status: Days 17-28 Complete ✅, plus a Phase 5 engine v7.0 cleanup pass

**Scope note:** this is a tour of the IntegrationEngine bundle, not a real rental
business. Persistence (Doctrine/SQLite), a payments/webhooks admin panel, and
RabbitMQ/Messenger workers were explicitly descoped — and FrankenPHP was judged
not relevant, the app stays on `php:8.4-fpm` + Nginx. See `docs/TAREAS.md` §
"Decisiones de alcance" for the reasoning. `docs/TAREAS.md` itself is an archived
draft of a broader plan, not the live one — this file is the source of truth.

**Final Status:** Feature-complete for that scope, with one important caveat
verified 2026-09-22: **the tour's "Run" button does not execute anything.**
`TourController::run()` (`src/Controller/TourController.php`) is a stub —
`// This is a placeholder - actual step execution would happen here` — that
returns a fixed `{"success": true}` and an empty trace for every step,
regardless of which one was requested. The tour's real, verifiable value is
the code snippets themselves (extracted live from source via
`SourceSnippetExtractor`, so they can't drift from the code) — not live
execution in the browser. See Next Steps for what that implies for each step.

Resilience patterns (retry, circuit breaker, fallback, chaos injection) exist
under `src/Shared/Infrastructure/` and are shown as source in tour step 4
("When Suppliers Fail"), and are genuinely *executed* — but only via
`bin/console billing:simulate-rental --chaos` from the CLI, not from the
tour's Run button. They're also **not** wired into the production request
pipeline — `config/packages/integration_engine.yaml` only declares
`app.middleware.rate_limit` under `tmdb`'s `middlewares:`. Wiring
retry/circuit-breaker into that live pipeline is a separate open item; see
Next Steps.

### Overview
A progressive demonstration of the **IntegrationEngine** Symfony bundle, showcasing API integration patterns through a movie storefront demo. Spans 9 days of incremental development with 3 distinct protocols and middleware extensibility.

### Phase 1: Core Integration & Patterns (Days 17-22)

| Day | Feature | Protocols | Status |
|-----|---------|-----------|--------|
| **17** | TMDB infrastructure | REST/JSON | ✅ DONE |
| **18** | Domain layer + Gateway | REST/JSON | ✅ DONE |
| **19** | Legacy parity test | REST/JSON | ✅ DONE |
| **20** | Tour step 1: "The Problem" | REST/JSON | ✅ DONE |
| **21** | Parallel storefront (20 movies) | REST/JSON | ✅ DONE |
| **22** | Benchmark + tour step 2 | REST/JSON | ✅ DONE |

**Achievements:**
- Multi-protocol support (REST)
- Batch/parallel request handling
- Domain→Application→Infrastructure separation
- Bilingual tour with live code snippets
- Performance benchmarking (5-13x speedup)
- Legacy code parity testing

### Phase 2: Protocol Expansion & Extensibility (Days 23-25)

| Day | Feature | Protocols | Status |
|-----|---------|-----------|--------|
| **23** | CSV adapter + supplier integration | CSV | ✅ DONE |
| **24** | GraphQL + rate limiting middleware | GraphQL | ✅ DONE |
| **25** | Tour step 3: "Behind the Counter" | Middleware | ✅ DONE |

### Phase 3: Payment Integration & Webhooks (Day 26)

| Day | Feature | Flow | Status |
|-----|---------|------|--------|
| **26** | Stripe: outbound payment + webhook confirmation | Outbound (REST form-encoded) / Inbound (webhook signature validation) | ✅ DONE |

**Achievements (Days 23-25):**
- Three distinct protocols implemented with identical architecture (Action/Mapper/Response), each with unit test coverage
- Middleware pipeline for cross-cutting concerns
- Rate limiting with Symfony integration — **the only one of these actually wired into a live request**: `tmdb`'s `middlewares:` in `config/packages/integration_engine.yaml`
- Extensibility demonstrated via YAML configuration
- **Caveat (verified 2026-09-22):** CSV (`SupplierIntegration`) isn't even registered under `integration_engine.yaml`'s `integrations:`, and GraphQL (`countries`, which *is* registered) has no caller anywhere in `src/` — no controller, no command, no tour step. Both exist only as tested, standalone code; neither is reachable from a running instance of the app. Tour step 3 ("Behind the Counter") only shows the rate-limit middleware snippets, not CSV or GraphQL.
- Three complete tour steps (1-3) with live snippets

**Achievements (Day 26):**
- First inbound endpoint (all previous were outbound)
- Outbound payment intent creation via Stripe REST API (form-urlencoded via custom StripeFormClientAdapter)
- Inbound webhooks (`POST /webhook/stripe`) via Symfony Webhook + IntegrationEngine v5.2 pieces:
  - IntegrationWebhookRequestParser subclass (StripePaymentIntentParser) with TimestampedHmacSignatureVerifier
  - AbstractWebhookMapper for payload transformation (StripePaymentIntentMapper → StripePaymentIntentEvent)
  - #[AsRemoteEventConsumer] (StripePaymentIntentConsumer) dispatching the typed event via WebhookEventDispatcher
  - Symfony EventDispatcher listener in Billing (#[AsEventListener])
- Two complete tour steps (5-6) with live snippets
- Tests: outbound mapper, webhook mapper, end-to-end signed webhook flow

### Phase 4: Resilience Patterns (Days 27-28)

| Day | Feature | Status |
|-----|---------|--------|
| **27** | Retry (exponential backoff), circuit breaker, fallback strategies, chaos monkey; tour step 4 "When Suppliers Fail" | ✅ DONE |
| **28** | Tour step "Graceful Degradation"; `SimulateRentalCommand --chaos` for manual failure injection | ✅ DONE |

**Achievements:**
- `RetryMiddleware` (exponential backoff, transient-vs-permanent error classification)
- `CircuitBreaker` (CLOSED → OPEN → HALF_OPEN state machine)
- `FallbackStrategy` (null, cache, default)
- `ChaosMonkey` for controlled failure injection, driven by `bin/console billing:simulate-rental --chaos`
- **Caveat 1:** genuinely executed only via that console command — the tour's own "Run" button is a stub (see the Final Status note above) and doesn't invoke any of this.
- **Caveat 2:** not wired into `config/packages/integration_engine.yaml`'s `middlewares:` for any integration — only `app.middleware.rate_limit` is. Wiring them into the real request pipeline is still open (see Next Steps).

### Phase 5: Engine v7.0 Integration Cleanup

Once IntegrationEngine shipped native form-urlencoded and CSV support, the demo's own workarounds were removed:
- Removed the custom `StripeFormClientAdapter` (129 lines) in favor of the engine's `FormEncodedClientAdapter`
- Simplified `GetPricesMapper` (44 lines removed) using the engine's CSV parser utility
- Net effect: ~410 → ~100 lines of custom protocol-handling code (-76%)

See `docs/PHASE3-INTEGRATION.md` for the detailed before/after.

### Architecture Highlights

**Three Protocols Supported:**
```
REST/JSON  ──→  GetMovieAction + GetMovieMapper
CSV        ──→  GetPricesAction + GetPricesMapper  
GraphQL    ──→  GetCountriesAction + GetCountriesMapper
```

**Middleware Pipeline:**
```
Application 
  ↓ (send/sendMany)
  ↓ 
RateLimitMiddleware (consume token)
  ↓ 
CachingMiddleware (if configured)
  ↓ 
HTTP Adapter (REST/CSV/GraphQL)
  ↓ 
Mapper (response → DTO)
  ↓ 
Domain Service
```

### Test Coverage

**Current:** 169 tests, 638 assertions, all passing ✅ (verified 2026-09-22 via `vendor/bin/phpunit`)
- `tests/Catalog/` — TMDB integration, domain layer, storefront controller
- `tests/Pricing/` — CSV adapter, Countries GraphQL, rate limiting
- `tests/Tour/` — snippet resolution, tour configuration
- `tests/Legacy/` — parity testing with legacy code
- `tests/Billing/` — Stripe mappers, end-to-end webhook flow (signature, expiry, event filtering)
- `tests/Shared/` — resilience patterns, middleware, observability
- `tests/Command/`, `tests/Console/`, `tests/Controller/` — CLI and HTTP entry points
- `tests/Documentation/` — guards that docs don't point at nonexistent files
- `tests/Translation/`, `tests/Security/`, `tests/Unit/`

Infection MSI thresholds are `minMsi: 58`, `minCoveredMsi: 63` in `infection.json5` — deliberately below the bundle's own 85%/95% target, raised incrementally as coverage grows.

### Files Structure

Integrations live in one flat, shared location (`src/Integrations/`), not nested per bounded context — this diverges from `docs/TAREAS.md`'s original per-context layout, intentionally.

```
src/
├── Catalog/            Domain + Application (Movie aggregate, MovieCatalogGateway)
├── Pricing/             Application/Infrastructure (CsvClientAdapter, StoreRegion)
├── Billing/             Application/Domain (RentalPaymentGateway) + webhook EventListener
├── Legacy/              TmdbApiService — deliberately bad "before" code for the tour
├── Integrations/        Tmdb, Countries, Stripe, Supplier — Action/Mapper/Response per protocol
├── Tour/                YamlTourRegistry, SourceSnippetExtractor
├── Shared/              Middleware (RateLimit, Retry), Resilience (CircuitBreaker, FallbackStrategy), Observability, Stats
└── Controller/, Command/, Console/    HTTP + CLI entry points (flat, not per-context UI/)

config/
├── tour.yaml (7 steps, ~26 snippets)
├── packages/
│   ├── integration_engine.yaml (tmdb, countries, stripe — only tmdb has a middleware wired)
│   └── rate_limiter.yaml

translations/
├── tour.en.yaml
└── tour.es.yaml

docker/
├── nginx.conf, entrypoint.sh   (used by the root Dockerfile)
└── supplier/                    Dockerfile + nginx.conf + prices.csv (CSV mock)

tests/  — mirrors src/, 169 tests total (see Test Coverage above)
```

### Git History

`main` is pushed, `v1.0.0` is tagged. Use `git log --oneline` for the current
history — this file no longer carries a frozen commit list, which drifted out
of sync with reality every time new work landed.

### Demo Access

**URLs:**
- **English Storefront:** http://localhost:8080/en/store
- **Spanish Storefront:** http://localhost:8080/es/store

**Status:** 🟢 Architecture complete | ⚠️ Needs valid TMDB credentials for live data

### Implementation Validation

| Component | Status | Evidence |
|-----------|--------|----------|
| Action/Mapper/Response | ✅ | 4 protocols under `src/Integrations/` |
| Domain layer | ✅ | Movie aggregate, fromInfrastructure factory |
| Gateway pattern | ✅ | send() + sendMany() parallelism |
| Route discovery | ✅ | 11 routes registered (`bin/console debug:router`) |
| YAML config loading | ✅ | Tmdb.yaml, Countries.yaml, Stripe.yaml parsed |
| HTTP method resolution | ✅ | GET /3/configuration (verified) |
| Authorization injection | ✅ | Bearer token header sent |
| Middleware pipeline | ✅ | RateLimitMiddleware wired; Retry/CircuitBreaker demo-only (see caveat above) |
| Bilingual tour (snippets) | ✅ | EN/ES, 7 steps, ~26 snippets, extracted live from source |
| Bilingual tour ("Run" button) | ❌ | Stub — `TourController::run()` returns a fixed response, executes nothing |
| Tests | ✅ | 169 tests, 638 assertions passing |

### Next Steps

**Known open items:**
- [ ] **Make the tour's "Run" button actually run something.** `TourController::run()` is a stub returning a fixed response for every step. This is the biggest gap between what the demo claims to do and what it does — fixing it (or being explicit in the UI that it's not implemented yet) should be the top priority before showing this to anyone external.
- [ ] Wire `SupplierIntegration` (CSV) into `integration_engine.yaml` and call it from somewhere real (tour step 3 or the storefront), or remove it — right now it's unregistered, unreachable code with only unit tests.
- [ ] Give `countries` (GraphQL) an actual caller, or remove it — it's registered but nothing in `src/` ever calls it.
- [ ] Wire `RetryMiddleware`/`CircuitBreaker` into the live `integration_engine.yaml` pipeline — currently only reachable via `bin/console billing:simulate-rental --chaos`
- [ ] D5.2 "Partner stores" tour step (SSRF protection via a connection resolver) — the one piece of `docs/TAREAS.md`'s original spec not discarded by the scope decisions
- [ ] Decide the fate of `MercureUpdateController` / `public/mercure-demo.html` — orphaned from an earlier, unrelated prototype (publishes to `admin/payments`/`admin/transactions` topics, page title still says "TransactionEngine"). Not linked from the tour or storefront, not part of the real Stripe webhook flow. Either wire it into the payment-confirmation tour step for a genuine live-update demo, or remove it.
- [ ] There is no web UI path to rent a movie at all — `RentalPaymentGateway` is only called from `SimulateRentalCommand` (CLI). Tour steps 5-6 ("Renting a Movie", "Payment Confirmation") show real snippets but can't be triggered from the browser.

**Explicitly out of scope** (see `docs/TAREAS.md` § "Decisiones de alcance" — this is a decided position, not a backlog):
- Persistence (Doctrine/SQLite), a payments/webhooks admin panel, RabbitMQ/Messenger workers
- Migrating from `php:8.4-fpm` to FrankenPHP

---

## Running the Demo

### Prerequisites
```bash
# Install dependencies
make install

# Start Docker containers
docker-compose up -d

# Run tests
make test

# Verify routes
php bin/console debug:router | grep -E "storefront|tour"
```

### Access Points
- **Storefront**: http://localhost:8080/en/store (20 movies in parallel)
- **Storefront ES**: http://localhost:8080/es/store (Spanish version)
- **Benchmark**: `php bin/console catalog:benchmark` (CLI)
- **Tour**: `/{_locale}/tour/{stepId}` — 7 steps, from "the-problem" to "payment-confirmation"
- **API Endpoints**:
  - TMDB (REST/JSON): https://api.themoviedb.org/3/configuration
  - Supplier (CSV): Docker mock service
  - Countries (GraphQL): https://countries.trevorblades.com/graphql

### Configuration

**Environment Variables (.env.local):**
```bash
TMDB_BASE_URL=https://api.themoviedb.org
TMDB_ACCESS_TOKEN=<your_v4_access_token_here>  # Needs valid token
```

**Why TMDB 401 Error:**
The demo uses a test token from `.env.local` that has expired. To see live movie data:
1. Get a valid TMDB v4 access token from https://www.themoviedb.org/settings/api
2. Replace `TMDB_ACCESS_TOKEN` in `.env.local`
3. Restart Docker container

---

## Summary

**Current state (Days 17-28 + Phase 5 cleanup):**
- 4 bounded contexts (Catalog, Pricing, Billing) + Legacy + Tour + Shared, with integrations kept flat under `src/Integrations/`
- 4 protocols implemented with identical architecture: REST/JSON (TMDB), CSV (Supplier), GraphQL (Countries), form-urlencoded (Stripe) — but only TMDB and Stripe are actually reachable from a running instance (see caveats below)
- Resilience patterns (retry, circuit breaker, fallback, chaos injection) built and genuinely executed via CLI, not yet wired into the live pipeline or the tour
- Complete bilingual tour: 7 steps, ~26 snippets extracted live from source
- 169 tests, 638 assertions, all green
- No persistence, no admin panel, no message queue, no FrankenPHP — deliberately, see the scope note at the top

**Architecture Proven:**
✅ Separates concerns (Domain/Application/Infrastructure per context)
✅ Handles multiple protocols uniformly in code (outbound + inbound)
✅ Supports parallelism (5-13x speedup) — live in the storefront
✅ Extensible via middleware and custom client adapters
✅ Webhook infrastructure (Symfony webhook + remote-event): verified, mapped, and
   dispatched as a typed event — currently just logged, no live push to the browser
   (`MercureUpdateController`/`public/mercure-demo.html` exist but are an unrelated,
   unwired standalone demo — see Next Steps)
✅ Bilingual UI with live, extracted-from-source code snippets
✅ Test coverage across all layers

**Not yet proven — verified false 2026-09-22:**
❌ The tour's "Run" button executes anything (it's a stub)
❌ CSV (Supplier) or GraphQL (Countries) integrations are reachable from the running app (unregistered/uncalled, respectively)
❌ Renting a movie is possible from a browser (CLI-only, via `SimulateRentalCommand`)

**Open work:** see "Next Steps" above — making "Run" real (or labeling it as not implemented) is the top priority, ahead of wiring resilience middleware or the D5.2 "Partner stores" tour step.

---

*Status claims in this document last verified against the code on 2026-09-22, at `75bb4b7`.*
*Enforced for file paths by `tests/Documentation/DocumentedPathsExistTest.php`.*
