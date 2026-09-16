# IntegrationEngine Demo

A guided tour through external API integrations using the [IntegrationEngine](https://github.com/carlosgude/integrationEngine) bundle. Demonstrates best practices for TMDB, Stripe, and custom integrations with live code snippets and parallel request benchmarking.

## Status: Phase 2 - Days 11-17

### ✅ Completed (Days 11-16)

- [x] **Day 11**: Bootstrap (Symfony 7.4 + Docker setup)
- [x] **Day 12**: Docker & CI (multi-stage build, Nginx, volume management)
- [x] **Day 13**: i18n & layout (EN/ES routes, translations, base template)
- [x] **Day 14**: Tour motor (TourRegistry, YAML config, step navigation)
- [x] **Day 15**: Snippet extractor (syntax highlighting, security validation)
- [x] **Day 16**: Tour UI + endpoints (TourController, RunController structure)

### 🔄 In Progress (Day 17)

- [ ] **BLOCKER - HTTP Response Transmission**: PHP generates 1571 bytes but HTTP returns 0-5 bytes
  - Root cause narrowed to PHP-FPM ↔ Nginx socket communication
  - [Debugging checklist saved in memory](../.claude/projects/-Users-cgude-PhpstormProjects-integrationEngine/memory/day-17-http-transmission-debug.md)
  
- [ ] **TMDB Integration**: GetConfiguration + GetMovie actions
  - API key & token stored in `.env.local`
  - DTOs and mappers ready to implement once HTTP blocker resolved
  
### 📋 Remaining (Days 18-32)

- **Days 18-20**: TMDB seasons, MovieCatalogGateway, legacy god-class demo, tour step 1
- **Days 21-22**: Storefront with 20-30 movies, tour step 2 + benchmark
- **Days 23-25**: CSV provider, GraphQL countries, RateLimitMiddleware, tour step 3
- **Days 26-32**: Security hardening, VPS provisioning, CD pipeline, v1.0.0 release

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

## Local Development

### Prerequisites
- Docker & Docker Compose
- TMDB API key (included in `.env.local`)

### Running the Demo

```bash
docker compose up --build -d
```

Access at: `http://localhost:8080/en/`

### Current Issue (Day 17 Blocker)

HTTP response transmission fails despite PHP generating content:

```
✅ Direct PHP execution: 1571 bytes
❌ Via HTTP (curl):     0-5 bytes
```

**Debugging next session:**
1. Check PHP-FPM timeout settings
2. Test with PHP built-in server (bypass Nginx)
3. Inspect FastCGI packets with tcpdump
4. Adjust Nginx chunked encoding

## Architecture

### Core Layers

- **UI**: TourController, RunController (HTTP endpoints)
- **Application**: Tour motor, snippet extraction
- **Infrastructure**: TMDB, Stripe, CSV integrations
- **Domain**: Movie, Snippet, Tour entities

### Integration Engine Pattern

```
Action (HTTP method, path, auth)
  ↓
Mapper (raw API response → typed DTO)
  ↓
Response (immutable DTO, toArray() contract)
```

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

## Commits

- `d2bd230`: Day 17 - Symfony config fixes & HTTP transmission investigation
- `779d388`: Day 16 - Docker build & vendor volume mounting (earlier session)

---

**Next session**: Start with [HTTP transmission debugging checklist](../.claude/projects/-Users-cgude-PhpstormProjects-integrationEngine/memory/day-17-http-transmission-debug.md), then implement TMDB integration.
