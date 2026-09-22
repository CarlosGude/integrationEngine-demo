# 🚀 Quick Start Guide

Get IntegrationEngine Demo up and running in 5 minutes.

## Prerequisites

- PHP 8.4+
- Composer
- Docker (recommended — `compose.yaml` also runs the Mercure hub)

No Node.js needed — assets are served via Symfony AssetMapper, no JS build step.

## Installation

### 1. Clone & Install

```bash
git clone https://github.com/carlosgude/integrationEngine-demo.git
cd integrationEngine-demo

composer install
```

### 2. Configure Environment

There's no `.env.local.example` to copy — create `.env.local` directly (it's
git-ignored) with at least your TMDB token; everything else already has a
working default in `.env`:

```bash
echo 'TMDB_ACCESS_TOKEN=your_token_here' > .env.local
```

Get a TMDB token:
1. Visit https://www.themoviedb.org/settings/api
2. Create an API key (v4 Bearer token)
3. Add to `.env.local`:
   ```env
   TMDB_ACCESS_TOKEN=eyJhbGci...
   ```

### 3. Start the Server

```bash
# Option A: Symfony CLI (simplest)
symfony server:start

# Option B: Docker Compose  (serves on 8080, not 8000)
docker compose up -d

# Option C: PHP built-in
php -S localhost:8000 -t public
```

### 4. Access the Demo

**The port depends on how you started it:** `8000` for the Symfony CLI and the
built-in server (options A and C), `8080` for Docker Compose (option B, bound to
`127.0.0.1` only). The Mercure hub is on `127.0.0.1:3000` under Docker.

Open your browser — substituting the port for your option:
- **Storefront**: http://localhost:8000/en/store
- **Tour**: http://localhost:8000/en/tour
- **Real-time Demo**: http://localhost:8000/mercure-demo.html

There's no admin dashboard — this demo has no persistence layer at all, see
[README.md](../README.md) § "Scope of this demo".

## Features to Try

### 1. Storefront
```bash
# Browse movies with parallel loading
curl http://localhost:8000/en/store
```

### 2. Tour
```bash
# Interactive guided tour (EN/ES)
curl http://localhost:8000/en/tour
curl http://localhost:8000/es/tour
```

### 3. Benchmark
```bash
# See parallel request speedup
php bin/console catalog:benchmark
```

### 4. Real-time Updates
```bash
# Start Mercure (in another terminal)
docker run -p 3000:80 -e MERCURE_PUBLISHER_JWT_SECRET=dev dunglas/mercure

# Open demo page
http://localhost:8000/mercure-demo.html

# Publish test event
php bin/console mercure:publish "admin/updates" '{"test":"hello"}'
```

## Running Tests

```bash
# All tests
make test

# Specific suite
make test TEST=tests/Catalog

# With coverage
php bin/console --group=coverage
```

## Code Quality

```bash
# Check code style
make cs

# Static analysis
make stan

# Architecture validation
make deptrac

# Everything
make ci
```

## Project Structure

```
integrationEngine-demo/
├── src/
│   ├── Catalog/          # Movie catalog (TMDB integration)
│   ├── Pricing/          # Pricing service (CSV + GraphQL)
│   ├── Billing/          # Stripe payment gateway + webhook listener
│   ├── Integrations/     # Tmdb, Countries, Stripe, Supplier clients
│   ├── Shared/           # Middleware, resilience patterns, observability
│   └── Tour/             # Interactive tour system
├── config/
│   ├── packages/
│   │   ├── integration_engine.yaml    # API configs
│   │   ├── mercure.yaml              # WebSocket setup
│   │   └── rate_limiter.yaml
│   └── routes/
├── tests/
├── docs/                 # Full documentation
├── docker/               # nginx.conf + entrypoint.sh (used by root Dockerfile)
└── public/
    ├── mercure-demo.html # Real-time demo
    └── index.php         # Entry point
```

## Configuration

### API Keys

Create or edit `.env.local`:
```env
# TMDB (Required for live data)
TMDB_ACCESS_TOKEN=your_token_here

# Stripe (already has a working test placeholder in .env)
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Mercure (already has a working default in .env)
MERCURE_JWT_SECRET=dev-secret
```

No database, no `DATABASE_URL` — this demo has no persistence layer, see
[README.md](../README.md) § "Scope of this demo".

## Docker Compose

```bash
# Start all services
docker compose up -d

# View logs
docker compose logs -f php

# Run commands
docker compose exec php php bin/console debug:router

# Stop services
docker compose down
```

## Common Commands

```bash
# Routes
php bin/console debug:router

# Services
php bin/console debug:container

# Cache
php bin/console cache:clear

# Tests
vendor/bin/phpunit

# Lint
php bin/console lint:yaml config/
```

## Troubleshooting

### "Token not found" Error
→ Set `TMDB_ACCESS_TOKEN` in `.env.local`

### "Port 8000 already in use"
→ Use different port: `symfony server:start --port=8001`

### Mercure connection fails
→ Ensure the `mercure` container is up: `docker compose up -d mercure`

### Composer dependency conflicts
→ Clear cache: `rm -rf vendor composer.lock && composer install`

## Next Steps

1. **Read the [Architecture Guide](ARCHITECTURE.md)** — Understand the design patterns
2. **Explore [Resilience Patterns](RESILIENCE-PATTERNS.md)** — Retry, fallback, circuit breaker
3. **Configure [Real-time Updates](MERCURE-WEBSOCKETS.md)** — WebSocket communication
4. **Read [Deployment reference](DEPLOYMENT.md)** — a written exercise, not an executed live setup

## Support

- **Issues**: https://github.com/carlosgude/integrationEngine-demo/issues
- **Docs**: See `docs/` directory
- **Contributing**: See [Contributing Guide](CONTRIBUTING.md)

## License

MIT — See LICENSE file
