# IntegrationEngine Demo

A guided, executable tour through external API integrations using the [IntegrationEngine](https://github.com/carlosgude/integrationEngine) bundle. REST/TMDB, GraphQL/Countries, CSV/Supplier and Stripe all use the same Action → Mapper → Response architecture. Tour snippets are extracted live from source, and the **Run Step** action executes the corresponding integration or deterministic resilience scenario and shows its result and trace.

## Scope of this demo

This is a **tour of the engine**, not a real rental store. That shapes a few deliberate choices:

- **No persistence.** Nothing is saved to a database — no rentals, no payment history, no webhook log. `RentalPaymentGateway` creates a real Stripe PaymentIntent; the inbound webhook is verified by IntegrationEngine, mapped to `StripePaymentIntentEvent`, dispatched to Billing and published to the `admin/payments` Mercure topic for a live browser feed.
- **No admin panel, no payments/webhooks dashboard.** With no real traffic, a dashboard would just show one or two test rows — empty-looking statistics that undercut the point of the demo instead of supporting it. What matters here is the integration code itself, shown live in the tour, not a business back-office around it.
- **No background workers or message queue.** There's no state for a worker to reconcile, so there's nothing for Messenger/RabbitMQ to do here.

These aren't gaps to fill later — they're scope decisions. The original, broader roadmap (`docs/TAREAS.md`) explored a version of this demo *with* persistence, a payments panel, and a webhook inbox; that path was intentionally dropped in favor of staying a focused engine tour. See `docs/TAREAS.md`'s "Decisiones de alcance" section for the full reasoning.

### Runtime boundaries

- Live TMDB steps require a valid `TMDB_ACCESS_TOKEN`.
- Live Stripe PaymentIntent creation requires a valid test `STRIPE_SECRET_KEY`. The tour's payment-confirmation step deliberately starts at the typed-event boundary so it can demonstrate Billing → Mercure without forging a Stripe signature; the authentic inbound path remains `POST /webhook/stripe` and is covered separately.
- Supplier CSV is served by the local Compose `supplier` service. Countries/GraphQL calls the public Countries endpoint.
- Persistence, admin dashboards and background workers remain intentionally out of scope.

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

**Demo:** `main` is the development branch; the next release is the executable-tour update.
**IntegrationEngine:** `^8.0.4` (stable release, not `dev-main`).
**Symfony:** 7.4 LTS
**PHP:** 8.4 (current stable)

### Quality Gates

CI enforces the following gates:
- `docker compose config` validates the app, supplier and Mercure services
- ✅ `composer install --no-dev` → production boot
- ✅ Webhook endpoints unauthenticated
- PHPStan level `max` without a baseline, PHP CS Fixer and Deptrac
- Infection is blocking with MSI ≥ 58% and covered-code MSI ≥ 63%

Deptrac uses the maintained `deptrac/deptrac` package. Exact test/assertion totals are taken from the current CI run rather than frozen here.

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
| Tests | PHPUnit suite enforced by CI |
| Assertions / coverage | Reported by the current CI run; assertions are not presented as coverage |
| CI Gates | tests, style, static analysis, architecture, production install, Compose config, Docker build, mutation testing |
| Protocolos | 4 (REST, CSV, GraphQL, Stripe form-urlencoded) |
| Tour Steps | 7, bilingual EN/ES, live snippets and executable Run Step actions |
| Integrations | TMDB (REST), Supplier (CSV), Countries (GraphQL), Stripe (webhooks) |
| Code Quality | PHPStan max without baseline, PHP CS Fixer, Deptrac |
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

### ✅ Engine v8 integration

**Engine v8 Integration Complete:**
- [x] Remove StripeFormClientAdapter (129 lines → use engine's FormEncodedClientAdapter)
- [x] Simplify GetPricesMapper (44 lines removed → use engine's CsvParser utility)
- [x] Remove custom CSV parsing code
- [x] Update webhook and middleware contracts for v8 API compatibility

**Impact:** Custom boilerplate reduced from 410 → 100 lines (-76%)
- Lines removed: 170
- Lines added: 25
- Net reduction: -145 lines

See the architecture and upgrade documentation for implementation details.

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
