# 📖 Complete Project Wiki

Master index of all IntegrationEngine Demo documentation.

> **Note (2026-09-22):** this file previously described features that don't exist in
> this project — an EasyAdmin admin dashboard, PostgreSQL/Redis, a multi-tenant
> roadmap. That content has been removed. See `README.md` § "Scope of this demo" for
> why: this is a tour of the IntegrationEngine bundle, not a real rental business, and
> it deliberately has no persistence layer, no admin panel, and no database.

## 🚀 Getting Started

### For New Developers

1. **[Quick Start Guide](QUICKSTART.md)** (5 minutes)
   - Installation steps
   - Running the demo
   - Common commands
   - Troubleshooting

2. **[Architecture Guide](ARCHITECTURE.md)** (15 minutes)
   - Project structure
   - Design patterns
   - Layer separation
   - Integration patterns

### For DevOps / Deployment

1. **[Deployment Guide](DEPLOYMENT.md)** — a written reference exercise, **not executed**
   against a live host. There is no hosted instance of this demo.

2. **[Release Notes](RELEASE-NOTES.md)**
   - v1.0.0 features
   - Changelog

---

## 🎯 Feature Documentation

### Real-time Updates

- **[Mercure & WebSockets](MERCURE-WEBSOCKETS.md)** — WebSocket communication protocol
  guide. In this repo it powers a standalone demo page (`/mercure-demo.html`), not the
  rental/payment flow — see the note at the top of that file.

### API Integrations

- **[TMDB Integration](ARCHITECTURE.md#tmdb-integration)** — Movie data
  - Action classes
  - Mappers & responses
  - Configuration
  - Rate limiting

- **[Stripe Webhooks](ARCHITECTURE.md#stripe-integration)** — Payment processing
  - Webhook setup
  - Event mapping
  - Signature verification

- **[CSV & GraphQL](ARCHITECTURE.md#csv--graphql-integrations)** — Alternative protocols
  - CSV parsing
  - GraphQL queries
  - Protocol adapters

---

## 🛠️ Technical Deep Dives

### Core Patterns

- **[Resilience Patterns](RESILIENCE-PATTERNS.md)** — Fault tolerance
  - Retry middleware (exponential backoff)
  - Circuit breaker pattern
  - Fallback strategies
  - Built, demonstrated in tour step 4 — **not yet wired** into the live request
    pipeline (see `PLAN.md` § Next Steps)

- **[Chaos Testing](CHAOS-TESTING.md)** — Testing resilience
  - Controlled failure injection
  - ChaosMonkey utility
  - `bin/console app:simulate-rental --chaos`

### Parallelism & Performance

- **[Benchmark Guide](ARCHITECTURE.md#parallelism)** — Parallel requests
  - Request batching via `sendMany()`
  - Performance metrics (5-13x speedup)

### Custom Development

- **[Custom Adapters Guide](CUSTOM-ADAPTERS.md)** — Protocol extensions
  - Building adapters
  - Middleware integration
  - Configuration
  - Testing

- **[Phase 3 Integration](PHASE3-INTEGRATION.md)** — Engine v7.0 upgrade
  - Already carried out — this is the historical record of what changed

---

## 📊 Analysis & Planning

- **[Project Analysis & Recommendations](PROJECT-ANALYSIS.md)** — a point-in-time
  analysis (engine v6.0.0 era). Read its 2026-09-22 update banner first: several
  "missing" items it lists have since shipped.
- **[TAREAS.md](TAREAS.md)** — an archived, broader-scope draft plan (persistence,
  a payments panel, RabbitMQ). Explicitly **not** the live plan; `PLAN.md` is.

---

## 👨‍💻 Development

### Contributing

- **[Contributing Guide](CONTRIBUTING.md)** — How to contribute
  - Fork & branch
  - Commit conventions
  - Pull request process
  - Code standards
  - Testing requirements

### Running Locally

```bash
# Clone & setup
git clone https://github.com/carlosgude/integrationEngine-demo.git
cd integrationEngine-demo
composer install

# Start (Docker, recommended — also runs the Mercure hub)
docker compose up -d --build --wait

# Credentials and existing vendor-volume upgrades: see docs/QUICKSTART.md

# Run tests
make test

# Code quality
make ci
```

### Commands Reference

| Command | Purpose |
|---------|---------|
| `make test` | Run PHPUnit tests |
| `make cs` | Check code style |
| `make stan` | Static analysis (PHPStan) |
| `make deptrac` | Architecture validation |
| `make ci` | Full CI suite |
| `php bin/console app:benchmark` | Parallel performance test |
| `php bin/console mercure:publish` | Publish a real-time update (standalone demo) |
| `php bin/console debug:router` | List routes |

---

## 🗂️ File Structure Reference

```
integrationEngine-demo/
│
├── docs/                           # 📖 All documentation
│   ├── QUICKSTART.md
│   ├── ARCHITECTURE.md
│   ├── DEPLOYMENT.md              # reference only, not executed
│   ├── MERCURE-WEBSOCKETS.md
│   ├── RESILIENCE-PATTERNS.md
│   ├── CHAOS-TESTING.md
│   ├── CUSTOM-ADAPTERS.md
│   ├── PROJECT-ANALYSIS.md        # point-in-time snapshot
│   ├── PHASE3-INTEGRATION.md      # historical record, already done
│   ├── CONTRIBUTING.md
│   ├── RELEASE-NOTES.md
│   ├── TAREAS.md                  # archived draft plan
│   └── WIKI.md                    # this file
│
├── src/                             # 🔧 Application code
│   ├── Catalog/                    # Movie catalog domain (TMDB-backed)
│   ├── Pricing/                    # Pricing domain (CSV + GraphQL)
│   ├── Billing/                    # Stripe payment gateway + webhook listener
│   ├── Legacy/                     # Deliberately bad "before" code for the tour
│   ├── Integrations/                # Tmdb, Countries, Stripe, Supplier (Action/Mapper/Response)
│   ├── Shared/                      # Middleware, resilience, observability
│   ├── Controller/, Command/, Console/   # HTTP + CLI entry points
│   └── Tour/                        # Tour engine (YAML registry + snippet extractor)
│
├── config/                          # ⚙️ Configuration
│   ├── packages/
│   │   ├── integration_engine.yaml  # API configs
│   │   ├── mercure.yaml
│   │   └── rate_limiter.yaml
│   ├── routes/
│   ├── services.yaml
│   └── tour.yaml
│
├── templates/                       # 🎨 Views (base, store, tour)
│
├── public/
│   ├── index.php                    # Entry point
│   └── mercure-demo.html            # Standalone real-time demo (see notes above)
│
├── tests/                            # ✅ mirrors src/
│
├── translations/                     # 🌍 tour.en.yaml, tour.es.yaml
│
├── docker/                            # nginx.conf, entrypoint.sh (used by root Dockerfile), supplier/
│
├── Makefile
├── compose.yaml                       # app + Mercure hub
├── composer.json
├── README.md
└── .env.local                         # Local secrets (git-ignored)
```

---

## 🔗 External Resources

### Official Documentation

- **Symfony**: https://symfony.com/doc/
- **Mercure Protocol**: https://mercure.rocks

### Tutorials & Examples

- **TMDB API**: https://www.themoviedb.org/settings/api
- **Stripe Documentation**: https://stripe.com/docs
- **Symfony Best Practices**: https://symfony.com/doc/current/best_practices.html

### Tools & Services

- **GitHub Actions**: https://github.com/features/actions
- **Docker Hub**: https://hub.docker.com/
- **Packagist**: https://packagist.org/

---

## 📈 Project Metrics

Quality targets and verification commands (see the latest CI run for results):

| Metric | Status |
|--------|--------|
| PHPUnit Tests | Run `vendor/bin/phpunit`; totals change with the suite |
| PHPStan Level | Max (`make stan`) |
| Architecture | Deptrac (`make deptrac`) |
| Mutation Score | MSI ≥ 58%, covered-code MSI ≥ 63% (raised incrementally) |

| Metric | Value |
|--------|-------|
| Parallel Speedup | 5-13x |
| Sequential Response (20 items) | ~4000ms |
| Parallel Response (20 items) | ~300-600ms |
| Persistence | None — see `README.md` § "Scope of this demo" |
| Integrations | 4 (TMDB/REST, Supplier/CSV, Countries/GraphQL, Stripe/form-urlencoded) |

---

## 🗣️ Communication

### Getting Help

- **GitHub Issues**: https://github.com/carlosgude/integrationEngine-demo/issues
- **GitHub Discussions**: https://github.com/carlosgude/integrationEngine-demo/discussions
- **Email**: carlos.sgude@gmail.com

### Reporting Bugs

1. Check if bug already reported
2. Create issue with:
   - Clear title
   - Steps to reproduce
   - Expected behavior
   - Actual behavior
   - Environment (OS, PHP version, etc.)

---

## 📝 Status

**Current:** feature-complete for this demo's intentionally limited scope (a tour of
the engine, not a rental business) — see `PLAN.md` for the authoritative status and
open items, and `README.md` § "Scope of this demo" / "Known issues" for what's
deliberately not here and what's still a loose end (the orphaned Mercure demo
controller, resilience middleware not yet wired into the live pipeline, and the
`docs/TAREAS.md` "Partner stores" SSRF tour step that's still a legitimate gap).

**Not planned:** persistence, an admin/payments dashboard, multi-tenant support,
RabbitMQ/Messenger workers, or a FrankenPHP migration — all explicitly descoped, see
`docs/TAREAS.md` § "Decisiones de alcance".

---

## 🎓 Learning Path

### Beginner (0-2 hours)

1. [Quick Start](QUICKSTART.md)
2. [Architecture Guide](ARCHITECTURE.md) - Overview section
3. Try the storefront & the tour

### Intermediate (2-8 hours)

1. [Architecture Guide](ARCHITECTURE.md) - Full
2. [Resilience Patterns](RESILIENCE-PATTERNS.md)
3. [Mercure Integration](MERCURE-WEBSOCKETS.md) (standalone demo — read its note first)

### Advanced (8+ hours)

1. [Custom Adapters](CUSTOM-ADAPTERS.md)
2. [Phase 3 Integration](PHASE3-INTEGRATION.md) (historical record)
3. [Project Analysis](PROJECT-ANALYSIS.md) (point-in-time snapshot)
4. [Deployment reference](DEPLOYMENT.md) (not executed)

---

## ✅ Checklist for New Contributors

- [ ] Read [Quick Start](QUICKSTART.md)
- [ ] Read [Contributing Guide](CONTRIBUTING.md)
- [ ] Read [Architecture Guide](ARCHITECTURE.md)
- [ ] Run `make test` - all tests pass
- [ ] Run `make ci` - full CI suite passes
- [ ] Pick an issue or feature to work on
- [ ] Create feature branch
- [ ] Submit pull request
- [ ] Address review feedback

---

## 🏆 What This Project Actually Demonstrates

✨ **Multi-protocol integration** — 4 protocols (REST, CSV, GraphQL, Stripe form-urlencoded) through one uniform Action → Mapper → Response pattern

⚡ **Parallel performance** — 5-13x speedup demonstrated and measured (`app:benchmark`)

🔐 **Resilience patterns** — Retry, circuit breaker, fallback, chaos injection — built and demonstrated in the tour, not yet wired into the live pipeline

🌍 **Bilingual** — Interactive 7-step tour in English & Spanish, with code snippets extracted live from source

🧪 **Well-tested** — 169 tests, PHPStan level max, Deptrac architecture checks

---

## 📞 Support Channels

| Channel | Best For |
|---------|----------|
| GitHub Issues | Bugs, concrete problems |
| GitHub Discussions | Questions, ideas |
| Email | Security issues |
| Documentation | Learning & reference |

---

**Last Updated:** 2026-09-22
**Version:** 1.0.0 tag (see `PLAN.md` for current `main` status)

[↑ Back to Top](#-complete-project-wiki)
