# IntegrationEngine Demo - Implementation Plan

## Project Status: Days 17-26 Complete ✅

**Final Status:** Feature-complete, architecture validated, ready for Days 27+
(Hardening/Resilience — the retry, circuit-breaker and fallback classes exist
under `src/Shared/Infrastructure/`, but nothing wires them yet; only
`app.middleware.rate_limit` is declared under `middlewares:`).

Known issues are tracked in [`TASKS.md`](TASKS.md).

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
- Three distinct protocols working with identical architecture
- Middleware pipeline for cross-cutting concerns
- Rate limiting with Symfony integration
- Extensibility demonstrated via YAML configuration
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

**Current:** 48+ tests passing ✅
- `tests/Catalog/` — TMDB integration, domain layer, storefront controller
- `tests/Pricing/` — CSV adapter, Countries GraphQL, rate limiting
- `tests/Tour/` — snippet resolution, tour configuration
- `tests/Legacy/` — parity testing with legacy code
- `tests/Billing/` — Stripe mappers, end-to-end webhook flow (signature, expiry, event filtering)

### Files Structure

```
src/
├── Catalog/
│   ├── Domain/Movie.php
│   ├── Application/MovieCatalogGateway.php
│   ├── Infrastructure/Integrations/Tmdb/
│   │   ├── {Get*Action,*Mapper,*Response}.php (3 actions)
│   └── UI/{StorefrontController,Console/BenchmarkCommand}
├── Pricing/
│   ├── Infrastructure/
│   │   ├── Http/CsvClientAdapter.php
│   │   └── Integrations/{Supplier,Countries}/
│   │       ├── {Get*Action,*Mapper,*Response}.php
├── Tour/Infrastructure/{YamlTourRegistry,SourceSnippetExtractor}.php
├── Legacy/TmdbApiService.php (for parity testing)
└── Shared/Infrastructure/Middleware/RateLimitMiddleware.php

config/
├── tour.yaml (3 steps with 9 snippets)
├── packages/
│   ├── integration_engine.yaml (2 integrations + middleware wiring)
│   └── rate_limiter.yaml (fixed window, 1000/hour)

translations/
├── tour.en.yaml (English step descriptions)
└── tour.es.yaml (Spanish step descriptions)

docker/
├── supplier/Dockerfile + nginx.conf + prices.csv (CSV mock)
└── (TMDB uses real API via environment variables)

tests/
├── Catalog/... (6 test files)
├── Pricing/... (6 test files)
├── Tour/Infrastructure/TourSnippetsResolveTest.php
└── Legacy/LegacyEngineParityTest.php
```

### Git History

**9 commits in this session:**
```
077426e feat(day25): tour step 3 - behind the counter (middleware extensibility)
e7da039 feat(day24): GraphQL integration + rate limiting middleware
[6 more commits for days 17-23]
```

### Demo Access

**URLs:**
- **English Storefront:** http://localhost:8080/en/store
- **Spanish Storefront:** http://localhost:8080/es/store

**Status:** 🟢 Architecture complete | ⚠️ Needs valid TMDB credentials for live data

### Implementation Validation

| Component | Status | Evidence |
|-----------|--------|----------|
| Action/Mapper/Response | ✅ | 15 classes across 3 protocols |
| Domain layer | ✅ | Movie aggregate, fromInfrastructure factory |
| Gateway pattern | ✅ | send() + sendMany() parallelism |
| Route discovery | ✅ | 8 routes registered |
| YAML config loading | ✅ | Tmdb.yaml, Countries.yaml parsed |
| HTTP method resolution | ✅ | GET /3/configuration (verified) |
| Authorization injection | ✅ | Bearer token header sent |
| Middleware pipeline | ✅ | RateLimitMiddleware wired |
| Bilingual tour | ✅ | EN/ES with 9 code snippets |
| Tests | ✅ | 40+ tests passing |

### Next Steps (Days 27+)

**Day 27: Hardening (Resilience)**
- [ ] Retry logic for failed requests
- [ ] Circuit breaker pattern
- [ ] Exponential backoff middleware
- [ ] Tour step 4: "When Suppliers Fail"

**Day 28+: Deployment**
- [ ] Deploy to VPS
- [ ] Set up CI/CD pipeline (GitHub Actions)
- [ ] Release v1.0.0

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
- **Tour**: Integrated in storefront (step 1-3)
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

**Total Work (Days 17-26):**
- 13 commits
- 48+ tests passing (8 new for Billing)
- 4 bounded contexts (Catalog, Pricing, Tour, Billing)
- 3 protocols for outbound + webhook inbound (REST, CSV, GraphQL + Stripe)
- 20+ integration classes
- 13 tour snippets (bilingual, steps 1-3 complete, 5-6 complete)
- Complete middleware pipeline
- Form-encoded request adapter (custom client service)
- Timestamped HMAC-SHA256 webhook validation (Stripe-Signature)
- 10 days of development (Days 17-26)

**Architecture Proven:**
✅ Separates concerns (Domain/Application/Infrastructure)
✅ Handles multiple protocols uniformly (outbound + inbound)
✅ Supports parallelism (5-13x speedup)
✅ Extensible via middleware
✅ Extensible via custom adapters (form encoding vs JSON)
✅ Webhook infrastructure (Symfony webhook + remote-event)
✅ Bilingual UI with live code
✅ Test coverage for all layers

**Ready for:** Resilience patterns (retry/circuit-breaker), VPS deployment, v1.0.0 release

---

*Status claims in this document last verified against the code on 2026-09-21, at `d67f899`.*
*Enforced for file paths by `tests/Documentation/DocumentedPathsExistTest.php`.*
