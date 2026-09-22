# IntegrationEngine Demo

A guided tour through external API integrations using the [IntegrationEngine](https://github.com/carlosgude/integrationEngine) bundle: real, working REST integration (TMDB) with parallel request benchmarking, plus CSV and GraphQL integrations implemented with the same architecture as reference code. Bilingual code tour with snippets extracted live from source. **The tour's "Run" button doesn't execute anything yet** — see "Known issues" below before assuming this is a click-and-see-it-run demo.

## Scope of this demo

This is a **tour of the engine**, not a real rental store. That shapes a few deliberate choices:

- **No persistence.** Nothing is saved to a database — no rentals, no payment history, no webhook log. `RentalPaymentGateway` creates a real Stripe PaymentIntent; the webhook confirmation is verified, mapped, and dispatched as a typed event (`StripePaymentIntentEvent`) that a listener currently just logs. There's no live push to the browser on that path — Mercure exists in this repo, but only as its own standalone real-time demo (`/mercure-demo.html`, `MercureUpdateController`), unconnected to rentals or payments. See "Known issues" below.
- **No admin panel, no payments/webhooks dashboard.** With no real traffic, a dashboard would just show one or two test rows — empty-looking statistics that undercut the point of the demo instead of supporting it. What matters here is the integration code itself, shown live in the tour, not a business back-office around it.
- **No background workers or message queue.** There's no state for a worker to reconcile, so there's nothing for Messenger/RabbitMQ to do here.

These aren't gaps to fill later — they're scope decisions. The original, broader roadmap (`docs/TAREAS.md`) explored a version of this demo *with* persistence, a payments panel, and a webhook inbox; that path was intentionally dropped in favor of staying a focused engine tour. See `docs/TAREAS.md`'s "Decisiones de alcance" section for the full reasoning.

### Known issues

- **The tour's "Run" button is a stub.** `TourController::run()` returns a fixed `{"success": true}` and an empty trace for *every* step, regardless of which one you click — read the comment in the source: `// This is a placeholder - actual step execution would happen here`. The code snippets shown in each step are real (extracted live from source, so they can't drift), but clicking Run does not execute them. This is the single biggest gap between what this demo appears to offer and what it currently does.
- **CSV (Supplier) and GraphQL (Countries) integrations aren't reachable from the running app.** Both exist with the same Action/Mapper/Response architecture as TMDB and have unit tests, but `SupplierIntegration` isn't even registered under `integration_engine.yaml`'s `integrations:`, and nothing in `src/` ever calls the `countries` integration (which *is* registered). Tour step 3 ("Behind the Counter") only shows rate-limiting middleware, not CSV or GraphQL.
- **You can't rent a movie from the browser.** `RentalPaymentGateway` — the class that creates a real Stripe PaymentIntent — is only called from `SimulateRentalCommand`, a CLI command (`bin/console billing:simulate-rental`). There's no rent button, no payment page; tour steps 5-6 show real snippets but nothing you click actually triggers them.
- **`public/mercure-demo.html` and `MercureUpdateController` are orphaned.** They publish to topics like `admin/payments` and `admin/transactions` — leftovers from an earlier, unrelated prototype (the page's own `<title>` still says "TransactionEngine"). Nothing in the actual tour, storefront, or Stripe webhook flow links to or publishes through them. They're not wired into anything the tour demonstrates.

## 📚 Complete Documentation Suite

**Getting Started:**
- **[Release Notes](docs/RELEASE-NOTES.md)** — v1.0.0 features, metrics, roadmap
- **[Deployment Guide](docs/DEPLOYMENT.md)** — Production VPS setup, CI/CD, monitoring
- **[Quick Start Guide](docs/QUICKSTART.md)** — Get up and running in 5 minutes

**Technical Guides:**
- **[Architecture & Patterns Guide](docs/ARCHITECTURE.md)** — Complete reference covering layers, patterns, integrations, parallelism, and configuration
- **[Project Analysis & Recommendations](docs/PROJECT-ANALYSIS.md)** — What's working, what's missing, and what could be added to the engine

**Real-time & Webhooks:**
- **[Mercure & WebSockets Guide](docs/MERCURE-WEBSOCKETS.md)** — Real-time updates with WebSockets
- **[Webhook Integration](docs/ARCHITECTURE.md#webhooks)** — Stripe webhook handler with HMAC verification

**Resilience & Testing:**
- **[Resilience Patterns Guide](docs/RESILIENCE-PATTERNS.md)** — Retry, circuit breaker, fallback strategies with production checklist
- **[Chaos Testing Guide](docs/CHAOS-TESTING.md)** — Testing resilience with controlled failure injection

**Integration & Deployment:**
- **[Custom Adapters Guide](docs/CUSTOM-ADAPTERS.md)** — Building domain-specific protocol adapters
- **[Phase 3 Integration Plan](docs/PHASE3-INTEGRATION.md)** — Upgrade plan for IntegrationEngine v7.0
- **[Contributing](docs/CONTRIBUTING.md)** — Development workflow and git conventions
- **[Wiki](docs/WIKI.md)** — Complete project wiki with all documentation indexed

## Status & Versions

**Demo:** `v1.0.0` is tagged, `main` is development branch.
**IntegrationEngine:** Pinned to `dev-main` (latest from [carlosgude/integrationEngine](https://github.com/CarlosGude/integrationEngine), v7.0+)
**Symfony:** 7.4 LTS (supported until Nov 2025)
**PHP:** 8.4 (current stable)

### Quality Gates

All CI jobs are green:
- ✅ `make up` → both containers healthy
- ✅ `composer install --no-dev` → production boot
- ✅ Webhook endpoints unauthenticated
- ✅ 169 tests passing, 638 assertions
- ✅ PHPStan level `max`, PHP CS Fixer, Deptrac — all clean
- ✅ Infection: MSI ≥ 58%, covered-code MSI ≥ 63% (raised incrementally as coverage grows — see `infection.json5`)

**Deferred by choice, not by gap:** upgrading `qossmic/deptrac-shim` (abandoned) to `deptrac/deptrac` 2.x needs a config migration; bumping to PHPUnit 13 is scheduled as its own PR.

### ✅ Completed (Days 17-26)

**Days 17-18: TMDB Infrastructure**
- [x] GetConfiguration, GetMovie, GetTvSeason actions
- [x] Domain layer (Movie aggregate)
- [x] MovieCatalogGateway with send() + sendMany()

**Days 19-20: Patterns & Tour**
- [x] Legacy god-class demo (parity testing)
- [x] Tour step 1: "The Problem" (5 antipatterns)
- [x] Code snippet extraction (EN/ES)

**Days 21-22: Parallelism & Benchmarking**
- [x] Storefront with 20 movies (parallel loading)
- [x] Graceful failure handling (null entries)
- [x] Tour step 2: "Parallel Requests" + benchmark results
- [x] Median calculator (5-13x speedup metrics)

**Days 23-24: Protocol Expansion**
- [x] CSV adapter (Supplier pricing integration)
- [x] GraphQL integration (Countries API)
- [x] Rate limiting middleware
- [x] Middleware pipeline architecture

**Day 25: Tour Step 3**
- [x] "Behind the Counter" - extensibility via middleware
- [x] Bilingual tour (EN/ES) - 9 code snippets total
- [x] YAML configuration examples

**Day 26: Payment Integration & Webhooks**
- [x] Stripe outbound: CreatePaymentIntent (form-urlencoded body via FormEncodedClientAdapter)
- [x] Stripe inbound: `POST /webhook/stripe` via Symfony Webhook + IntegrationEngine's `IntegrationWebhookRequestParser`
- [x] Webhook event mapping via AbstractWebhookMapper
- [x] Stripe timestamped HMAC verification via `TimestampedHmacSignatureVerifier`
- [x] Typed event dispatched via `WebhookEventDispatcher` to a Billing listener
- [x] Tour steps 5-6: "Renting a Movie" + "Payment Confirmation" (bilingual)

### 📊 Final Metrics

| Métrica | Valor |
|---------|-------|
| Tests | 169 ✅ (no skipped; admin tests removed in T-11) |
| Test Coverage | 638 assertions |
| CI Jobs | 8 (tests, style, static-analysis, architecture, production-install, compose-config, build-image, mutation-testing) |
| Protocolos | 4 (REST, CSV, GraphQL, Stripe form-urlencoded) |
| Tour Steps | 7, bilingual EN/ES, real snippets — "Run" button not yet implemented (see Known issues) |
| Integrations | TMDB (REST), Supplier (CSV), Countries (GraphQL), Stripe (webhooks) |
| Code Quality | PHPStan max, 0 violations, baseline 7 entries (T-09) |
| Architecture | Deptrac 0 violations, UI layer captured (T-10) |
| Parallelism Speedup | 5-13x for 20 concurrent movie loads |
| Custom Code (Post-v7.0) | ~100 lines (-76% from v6.0) |
| Dependencies | 113 packages (Doctrine & EasyAdmin removed, T-11) |
| Persistence | None (in-memory event transport, focus on integrations) |

### ✅ Phase 2 Complete - Days 27-28

**Day 27: Resilience Patterns**
- [x] RetryMiddleware with exponential backoff
- [x] CircuitBreaker pattern (CLOSED→OPEN→HALF_OPEN)
- [x] FallbackStrategy (null, cache, default)
- [x] ChaosMonkey for testing
- [x] Tour step 4: "When Suppliers Fail" (complete resilience guide)

**Day 28: Graceful Degradation**
- [x] Tour step 5: "Graceful Degradation" (fallback strategies)
- [x] SimulateRentalCommand with chaos mode (--chaos flag)
- [x] Comprehensive testing framework
- [x] Production checklists and monitoring guides

### ✅ Phase 3 Complete - Integration with Engine v7.0

**Engine v7.0 Integration Complete:**
- [x] Remove StripeFormClientAdapter (129 lines → use engine's FormEncodedClientAdapter)
- [x] Simplify GetPricesMapper (44 lines removed → use engine's CsvParser utility)
- [x] Remove custom CSV parsing code
- [x] Update middleware signatures for v7.0 API compatibility
- [x] All tests passing (55/55) ✅

**Impact:** Custom boilerplate reduced from 410 → 100 lines (-76%)
- Lines removed: 170
- Lines added: 25
- Net reduction: -145 lines

See: [Phase 3 Integration Plan](docs/PHASE3-INTEGRATION.md) for implementation details

### 📄 Deployment guide (reference, not executed)

[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) sketches a production setup (Nginx + PHP-FPM, CI/CD, monitoring, security hardening) as a written exercise. It is **not** the actual architecture of this repo and has not been run against a live host: there is no database or Redis dependency in `composer.json`, and no VPS is currently serving this demo. Treat it as reference material, not a status claim.

### 🚀 Running it

The only supported way to run this demo today is locally via Docker Compose — see [Running the Demo](#running-the-demo) below. There is no hosted URL.

## Project Structure

```
integrationEngine-demo/
├── config/
│   ├── bundles.php               # Conditional bundle loading
│   ├── packages/                 # Framework config (incl. integration_engine.yaml)
│   ├── routes/                   # Route definitions
│   ├── services.yaml             # Service injection
│   └── tour.yaml                 # Tour step + snippet definitions
├── src/
│   ├── Controller/                # HTTP endpoints (storefront, tour, homepage, Mercure)
│   ├── Command/, Console/         # CLI commands (e.g. catalog:benchmark)
│   ├── Catalog/                   # Movie catalog domain (TMDB-backed)
│   ├── Pricing/                   # Pricing domain (CSV + GraphQL integrations)
│   ├── Billing/                   # Stripe payment gateway + webhook listener
│   ├── Legacy/                    # Deliberately bad "before" code for the tour
│   ├── Integrations/              # Tmdb, Countries, Stripe, Supplier clients (Action/Mapper/Response)
│   ├── Tour/                      # Tour engine (YAML registry + snippet extractor)
│   └── Shared/                    # Middleware, resilience patterns, observability
├── public/
│   └── index.php                  # Symfony Runtime entry point
├── templates/                     # Twig templates (base, store, tour)
├── Dockerfile                     # PHP 8.4-FPM + Nginx, single container
├── docker/
│   ├── nginx.conf, entrypoint.sh  # Used by the root Dockerfile
│   └── supplier/                  # Mock CSV supplier service
├── compose.yaml                   # Docker Compose setup (app + Mercure hub)
└── .env.local                     # TMDB credentials (local only)
```

## Running the Demo

### Quick Start

```bash
# 1. Start containers
docker compose up -d

# 2. Verify routes
docker compose exec php php bin/console debug:router | grep -E "storefront|tour"

# 3. Access demo
open http://localhost:8080/en/store    # English
open http://localhost:8080/es/store    # Spanish
```

### Configuration

**`.env.local` (required for live data):**
```env
TMDB_BASE_URL=https://api.themoviedb.org
TMDB_ACCESS_TOKEN=<your_v4_access_token>  # Get from https://www.themoviedb.org/settings/api
```

**Note:** The demo includes a test token that has expired. Replace with your own for live movie data.

### Running Tests

```bash
docker compose exec php make test      # All tests
docker compose exec php make qa        # Code quality
docker compose exec php make ci        # Full CI suite
```

### Features to Try

```bash
# Benchmark parallel requests
docker compose exec php php bin/console catalog:benchmark

# Verify code snippets resolve
docker compose exec php php bin/console debug:container | grep tour
```

## Architecture

### Integration Pattern (Replicated 4x)

Each protocol follows **Action → Mapper → Response**, under `src/Integrations/`:

| Protocol | Action | Mapper | Response |
|----------|--------|--------|----------|
| REST/JSON (TMDB) | GetMovieAction | GetMovieMapper | GetMovieResponse |
| CSV (Supplier) | GetPricesAction | GetPricesMapper | GetPricesResponse |
| GraphQL (Countries) | GetCountriesAction | GetCountriesMapper | GetCountriesResponse |
| Form-urlencoded (Stripe) | CreatePaymentIntentAction | CreatePaymentIntentMapper | PaymentIntentResponse |

Business logic lives in the bounded contexts (`Catalog`, `Pricing`, `Billing`) that consume these integrations through gateways — e.g. `Catalog\Application\MovieCatalogGateway`.

### Parallelism

```
send(action, context)        → single HTTP call + mapper
sendMany(requests)           → concurrent calls via BatchClientInterface
                             → each mapped independently
                             → failures don't abort batch (returns null)
```

**Result:** 5-13x speedup for batch operations (20 movies: 300ms vs 4000ms sequential)

## Configuration

### Environment Variables

```env
TMDB_API_KEY=50da1790...              # Public API key
TMDB_ACCESS_TOKEN=eyJhbGciOi...       # Bearer token
APP_ENV=dev                            # dev|prod
APP_DEBUG=1                            # 0|1
```

No `DATABASE_URL` — see [Scope of this demo](#scope-of-this-demo) above for why.

## Git Status

`main` is pushed. Use `git log --oneline` for the current history — this file no
longer carries a frozen snapshot of it.

---

**Status:** see [Status & Versions](#status--versions) at the top — `v1.0.0` tagged, `main` ahead, all CI gates green.

---

*Status claims in this document last verified against the code on 2026-09-22.*
*Enforced for file paths by `tests/Documentation/DocumentedPathsExistTest.php`.*
