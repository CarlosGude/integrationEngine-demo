# 🚀 Quick Start Guide

Get IntegrationEngine Demo up and running in 5 minutes.

## Prerequisites

- PHP 8.4+
- Composer
- Docker (optional)
- Node.js 18+ (for frontend dev)

## Installation

### 1. Clone & Install

```bash
git clone https://github.com/carlosgude/integrationEngine-demo.git
cd integrationEngine-demo

composer install
```

### 2. Configure Environment

```bash
cp .env.local.example .env.local
# Edit .env.local with your TMDB token
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

# Option B: Docker Compose
docker compose up -d

# Option C: PHP built-in
php -S localhost:8000 -t public
```

### 4. Access the Demo

Open your browser:
- **Storefront**: http://localhost:8000/en/store
- **Tour**: http://localhost:8000/en/tour
- **Admin Dashboard**: http://localhost:8000/admin (after setup)
- **Real-time Demo**: http://localhost:8000/mercure-demo.html

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

### 4. Admin Dashboard
```bash
# After database setup
php bin/console make:entity User
php bin/console make:admin:dashboard
# Visit http://localhost:8000/admin
```

### 5. Real-time Updates
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
│   ├── Payment/          # Payment processing (Stripe)
│   ├── Shared/           # Shared infrastructure
│   └── Tour/             # Interactive tour system
├── config/
│   ├── packages/
│   │   ├── integration_engine.yaml    # API configs
│   │   ├── mercure.yaml              # WebSocket setup
│   │   └── rate_limiter.yaml
│   └── routes/
├── tests/
├── docs/                 # Full documentation
├── docker/               # Docker setup
└── public/
    ├── mercure-demo.html # Real-time demo
    └── index.php         # Entry point
```

## Configuration

### API Keys

Create `.env.local`:
```env
# TMDB (Required for live data)
TMDB_ACCESS_TOKEN=your_token_here

# Stripe (Optional - for payments)
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Mercure (Optional - for real-time)
MERCURE_URL=http://localhost:3000/.well-known/mercure
MERCURE_PUBLIC_URL=http://localhost:3000/.well-known/mercure
MERCURE_JWT_SECRET=dev-secret
```

### Database

```bash
# SQLite (default - no setup needed)
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"

# PostgreSQL (production)
DATABASE_URL="postgresql://user:pass@localhost/demo"

# MySQL
DATABASE_URL="mysql://user:pass@localhost/demo"
```

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

# Database
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# Cache
php bin/console cache:clear

# Test
php bin/console test

# Lint
php bin/console lint:yaml config/
```

## Troubleshooting

### "Token not found" Error
→ Set `TMDB_ACCESS_TOKEN` in `.env.local`

### "Port 8000 already in use"
→ Use different port: `symfony server:start --port=8001`

### Database connection error
→ Check `DATABASE_URL` in `.env.local`

### Mercure connection fails
→ Ensure Mercure server running: `docker run -p 3000:80 dunglas/mercure`

### Composer dependency conflicts
→ Clear cache: `rm -rf vendor composer.lock && composer install`

## Next Steps

1. **Read the [Architecture Guide](ARCHITECTURE.md)** — Understand the design patterns
2. **Explore [Resilience Patterns](RESILIENCE-PATTERNS.md)** — Retry, fallback, circuit breaker
3. **Setup [Admin Dashboard](EASYADMIN.md)** — Create management interface
4. **Configure [Real-time Updates](MERCURE-WEBSOCKETS.md)** — WebSocket communication
5. **Deploy to [Production](DEPLOYMENT.md)** — VPS setup guide

## Support

- **Issues**: https://github.com/carlosgude/integrationEngine-demo/issues
- **Docs**: See `docs/` directory
- **Contributing**: See [Contributing Guide](CONTRIBUTING.md)

## License

MIT — See LICENSE file
