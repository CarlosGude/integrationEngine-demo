# IntegrationEngine Demo

A guided tour through external API integrations using the [IntegrationEngine](https://github.com/carlosgude/integrationEngine) bundle. Demonstrates best practices for multi-protocol integration (REST, CSV, GraphQL) with parallel request benchmarking, middleware extensibility, and bilingual code tour.

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

[`TASKS.md`](TASKS.md) tracks 23 known issues. **P1 blockers are closed:**
- ✅ `make up` → both containers healthy
- ✅ `composer install --no-dev` → production boot
- ✅ Webhook endpoints unauthenticated
- ✅ 65 tests passing (78 before Doctrine removal), 224 assertions

**Remaining:** 10 tasks (mostly P3 documentation, T-12 deptrac upgrade deferred).

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
| Total Commits | 20+ |
| Tests | 65 ✅ (no skipped; admin tests removed in T-11) |
| Test Coverage | 224 assertions |
| CI Jobs | 8 (tests, style, static-analysis, architecture, production-install, compose-config, build-image, mutation-testing) |
| Protocolos | 4 (REST, CSV, GraphQL, Stripe form-urlencoded) |
| Tour Steps | 7 (complete, bilingual EN/ES) |
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

### ✅ Phase 4 Complete - Deployment Ready

**v1.0.0 Deployment Documentation:**
- [x] Production VPS setup guide (AWS EC2 / DigitalOcean)
- [x] Nginx + PHP-FPM configuration
- [x] Database & Redis setup
- [x] CI/CD pipeline (GitHub Actions)
- [x] Monitoring & backup strategies
- [x] Security hardening checklist
- [x] Performance optimization guide
- [x] Release notes & roadmap

### 🚀 Deployment

The deployment guide is written and complete. Two blockers stand between it and
an actual deploy — see the Status section above and [`TASKS.md`](TASKS.md).

## Project Structure

```
integrationEngine-demo/
├── config/
│   ├── bundles.php              # Conditional bundle loading
│   ├── packages/                 # Framework config
│   ├── routes/                   # Route definitions
│   ├── services.yaml             # Service injection
│   └── tour.yaml                 # Tour step definitions
├── src/
│   ├── Controller/               # HTTP endpoints
│   ├── Catalog/                  # Movie catalog domain
│   ├── Tour/                     # Tour motor infrastructure
│   └── Shared/                   # Cross-cutting concerns
├── public/
│   └── index.php                 # Symfony Runtime entry point
├── templates/
│   └── base.html.twig            # Main layout
├── docker/
│   ├── Dockerfile                # PHP 8.4-FPM + Nginx
│   ├── nginx.conf                # Web server config
│   └── entrypoint.sh             # Container startup
├── compose.yaml                  # Docker Compose setup
└── .env.local                    # TMDB credentials (local only)
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

### Layer Separation

```
Domain Layer
  ├── Movie (readonly aggregate)
  ├── Snippet, Tour entities
  └── Value objects

Application Layer
  ├── MovieCatalogGateway (orchestration)
  └── Use case services

Infrastructure Layer
  ├── Integrations (TMDB, Supplier, Countries)
  ├── Middleware (rate limiting, caching)
  └── Adapters (HTTP, CSV, GraphQL clients)
```

### Integration Pattern (Replicated 3x)

Each protocol follows **Action → Mapper → Response**:

| Protocol | Action | Mapper | Response |
|----------|--------|--------|----------|
| REST/JSON | GetMovieAction | GetMovieMapper | GetMovieResponse |
| CSV | GetPricesAction | GetPricesMapper | GetPricesResponse |
| GraphQL | GetCountriesAction | GetCountriesMapper | GetCountriesResponse |

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
DATABASE_URL=sqlite:///var/data.db    # SQLite
```

## Quality Gates

- **PHPUnit**: All tests green
- **PHPStan**: Level max analysis
- **Deptrac**: Layer isolation verified
- **Infection**: 85%+ MSI mutation score

Run all:
```bash
docker compose exec php make ci
```

## Git Status

`main` is pushed. Use `git log --oneline` for the current history — this file no
longer carries a frozen snapshot of it.

## File Structure

```
src/
├── Catalog/
│   ├── Domain/Movie.php
│   ├── Application/MovieCatalogGateway.php
│   ├── Infrastructure/Integrations/Tmdb/
│   │   ├── GetConfigurationAction.php
│   │   ├── GetMovieAction.php
│   │   └── *Mapper.php + *Response.php
│   └── UI/
│       ├── StorefrontController.php
│       ├── Console/BenchmarkCommand.php
│       └── ...
├── Pricing/
│   └── Infrastructure/
│       ├── Http/CsvClientAdapter.php
│       └── Integrations/
│           ├── Supplier/ (CSV)
│           └── Countries/ (GraphQL)
├── Shared/
│   └── Infrastructure/Middleware/RateLimitMiddleware.php
└── Tour/
    └── Infrastructure/
        ├── YamlTourRegistry.php
        └── SourceSnippetExtractor.php

config/
├── tour.yaml (3 steps, 9 snippets)
├── packages/
│   ├── integration_engine.yaml (TMDB, Countries)
│   └── rate_limiter.yaml
└── routes.yaml

translations/
├── tour.en.yaml (English)
└── tour.es.yaml (Spanish)

tests/
├── Catalog/ (TMDB, storefront, benchmark)
├── Pricing/ (CSV, GraphQL)
├── Tour/ (snippet resolution)
└── Legacy/ (parity testing)
```

---

**Status:** see the [Status](#status) section at the top — `v1.0.0` tagged, `main` ahead, two blockers open in [`TASKS.md`](TASKS.md).

---

*Status claims in this document last verified against the code on 2026-09-21, at `d67f899`.*
*Enforced for file paths by `tests/Documentation/DocumentedPathsExistTest.php`.*
