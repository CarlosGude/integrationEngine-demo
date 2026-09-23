# IntegrationEngine Demo - Implementation Plan

## Project Status: Executable demo on IntegrationEngine v8

**Scope note:** this is a tour of the IntegrationEngine bundle, not a real rental
business. Persistence (Doctrine/SQLite), a payments/webhooks admin panel, and
RabbitMQ/Messenger workers were explicitly descoped — and FrankenPHP was judged
not relevant, the app stays on `php:8.4-fpm` + Nginx. See `docs/TAREAS.md` §
"Decisiones de alcance" for the reasoning. `docs/TAREAS.md` itself is an archived
draft of a broader plan, not the live one — this file is the source of truth.

**Final Status:** the tour is executable. `TourController::run()` delegates to
`TourStepRunner`, which executes live TMDB, Countries/GraphQL, Supplier/CSV and
Stripe outbound calls where appropriate, plus deterministic resilience scenarios.
The payment-confirmation step exercises the typed-event downstream path and
publishes to Mercure; authentic inbound Stripe requests still enter through
`POST /webhook/stripe` with IntegrationEngine signature verification.

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
- The tour now executes deterministic circuit-breaker and fallback scenarios directly; `SimulateRentalCommand --chaos` remains the manual randomized failure-injection path.
- **Caveat 2:** not wired into `config/packages/integration_engine.yaml`'s `middlewares:` for any integration — only `app.middleware.rate_limit` is. Wiring them into the real request pipeline is still open (see Next Steps).

### Phase 5: Engine v8 Integration Cleanup

IntegrationEngine v8 is pinned as a stable dependency and the demo uses its current contracts:
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

**Current:** enforced by CI. Exact test/assertion totals are read from the current run rather than frozen in documentation.
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

tests/  — mirrors src/ and is enforced by CI
```

### Git History

`main` is pushed, `v1.0.0` is tagged. Use `git log --oneline` for the current
history — this file no longer carries a frozen commit list, which drifted out
of sync with reality every time new work landed.

### Demo Access

**URLs:**
- **English Storefront:** http://localhost:8080/en/store
- **Spanish Storefront:** http://localhost:8080/es/store

**Status:** Architecture and executable tour complete; live external steps need the corresponding test credentials.

### Implementation Validation

| Component | Status | Evidence |
|-----------|--------|----------|
| Action/Mapper/Response | ✅ | 4 protocols under `src/Integrations/` |
| Domain layer | ✅ | Movie aggregate, fromInfrastructure factory |
| Gateway pattern | ✅ | send() + sendMany() parallelism |
| Route discovery | ✅ | 11 routes registered (`bin/console debug:router`) |
| YAML config loading | ✅ | Tmdb.yaml, Countries.yaml, Supplier.yaml, Stripe.yaml parsed |
| HTTP method resolution | ✅ | GET /3/configuration (verified) |
| Authorization injection | ✅ | Bearer token header sent |
| Middleware pipeline | ✅ | RateLimitMiddleware wired; Retry/CircuitBreaker demo-only (see caveat above) |
| Bilingual tour (snippets) | ✅ | EN/ES, 7 steps, ~26 snippets, extracted live from source |
| Bilingual tour ("Run" button) | ✅ | `TourStepRunner` executes integrations/scenarios and returns result + trace |
| Tests | ✅ | PHPUnit enforced by CI |

### Next Steps

The original execution gaps are closed:

- [x] Run Step executes real integrations or deterministic resilience scenarios.
- [x] Supplier/CSV is registered and served by the local Compose supplier service.
- [x] Countries/GraphQL has a real caller through the executable tour.
- [x] Stripe's verified typed event reaches Billing and Mercure.
- [x] The duplicate unverified Stripe/Mercure webhook endpoint was removed.
- [x] IntegrationEngine is pinned to stable v8 rather than `dev-main`.
- [x] PHPStan max runs without a baseline; Deptrac uses the maintained package; Infection is blocking.

Remaining items are optional extensions, not required to make the demo truthful:
- wiring retry/circuit-breaker into a production request pipeline instead of keeping them as explicit teaching scenarios;
- adding a browser-side Stripe confirmation flow if the demo ever grows beyond an engine tour.

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
- 4 protocols implemented with the same integration architecture: REST/JSON (TMDB), CSV (Supplier), GraphQL (Countries), form-urlencoded (Stripe), all reachable through executable demo flows
- Resilience patterns are executable in the tour; randomized chaos injection remains available from the CLI
- Complete bilingual tour: 7 steps, ~26 snippets extracted live from source
- PHPUnit, PHPStan, Deptrac, CS Fixer, production boot, Docker build and Infection are enforced by CI
- No persistence, no admin panel, no message queue, no FrankenPHP — deliberately, see the scope note at the top

**Architecture Proven:**
✅ Separates concerns (Domain/Application/Infrastructure per context)
✅ Handles multiple protocols uniformly in code (outbound + inbound)
✅ Supports parallelism (5-13x speedup) — live in the storefront
✅ Extensible via middleware and custom client adapters
✅ Webhook infrastructure (Symfony webhook + remote-event): verified, mapped and dispatched as a typed event; Billing publishes the resulting payment event to Mercure for live browser updates
✅ Bilingual UI with live, extracted-from-source code snippets
✅ Test coverage across all layers

**Deliberate boundary:** the project remains an engine tour, not a rental product. The executable "Renting a Movie" step creates the PaymentIntent from the tour; there is no full card-entry/Stripe.js checkout UI or persistence layer.

**Optional future work:** wire retry/circuit-breaker into a normal production request pipeline or add a complete browser checkout if the scope changes.

---

*Status claims updated for the IntegrationEngine v8 executable-tour branch on 2026-09-23.*
*Enforced for file paths by `tests/Documentation/DocumentedPathsExistTest.php`.*
