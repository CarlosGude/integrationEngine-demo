# IntegrationEngine Demo

A guided tour through external API integrations using the [IntegrationEngine](https://github.com/carlosgude/integrationEngine) bundle. Demonstrates best practices for multi-protocol integration (REST, CSV, GraphQL) with parallel request benchmarking, middleware extensibility, and bilingual code tour.

## Status: Phase 3 Complete - Days 17-26 ✅

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
- [x] Stripe outbound: CreatePaymentIntent (form-urlencoded body via StripeFormClientAdapter)
- [x] Stripe inbound: webhook signature validation (HMAC-SHA256 via IntegrationEngine v5.2)
- [x] Custom adapter for non-JSON request bodies
- [x] Webhook event mapping via AbstractWebhookMapper
- [x] HMAC signature verification via SignatureVerifierInterface
- [x] Event listener via Symfony EventDispatcher
- [x] Tour steps 5-6: "Renting a Movie" + "Payment Confirmation" (bilingual)

### 📊 Final Metrics (Days 17-26)

| Métrica | Valor |
|---------|-------|
| Commits | 13 nuevos |
| Tests | 48+ ✅ |
| Protocolos | 4 (REST, CSV, GraphQL, Stripe + webhook) |
| Clases integración | 20+ |
| Bounded contexts | 4 (Catalog, Pricing, Tour, Billing) |
| Tour steps | 5 of 6 completos |
| Snippets | 13 (bilingual) |
| Speedup paralelo | 5-13x |
| Webhook validation | HMAC-SHA256 (Stripe-Signature) |

### 📋 Pending (Days 27+)

- **Day 27**: Resilience patterns (retry, circuit-breaker, exponential backoff)
- **Day 28**: Tour step 4: "When Suppliers Fail"
- **Day 29+**: VPS deployment + CD pipeline
- **Day 30+**: v1.0.0 release

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

**Local commits (ready to push):** 12
```
1163f6e docs: update PLAN - days 17-25 complete
34c9e40 fix: TMDB YAML config - proper HTTP methods and paths
b9ade08 fix: namespace updates and demo preparation
077426e feat(day25): tour step 3 - behind the counter
e7da039 feat(day24): GraphQL integration + rate limiting
45a1b55 feat(day23): CSV adapter + supplier service
9ae132d feat(day22): Benchmark command + tour step 2
cf11217 feat(day21): Parallel storefront
828c046 feat(day20): Tour step 1 code snippets
2effea4 feat(day19): Legacy parity test
cfffe80 feat(day18): TMDB + domain layer
30e0aa6 fix: routing & config setup
```

**To push:**
```bash
gh auth login
git push origin main
```

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

**Status:** Days 17-26 ✅ | Ready for Days 27+ (Resilience, VPS, v1.0.0)
