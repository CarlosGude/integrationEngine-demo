# Quick Start Guide

Run the demo locally with Docker Compose. It starts the Symfony application,
the CSV supplier and the Mercure hub. No database or JavaScript build is needed.

## 1. Configure credentials

Clone the repository, then create or edit `.env.local` in its root. Preserve any
existing settings; this file is ignored by Git.

```dotenv
TMDB_ACCESS_TOKEN=your_tmdb_api_read_access_token
STRIPE_SECRET_KEY=sk_test_your_stripe_test_secret_key
```

Replace both placeholders with your own credentials:

- `TMDB_ACCESS_TOKEN` is the API Read Access Token used as a Bearer token. The
  application does not use `TMDB_API_KEY` for authentication.
- `STRIPE_SECRET_KEY` must be a valid Stripe test secret key. The example in
  `.env` cannot create payments. A publishable key (`pk_test_…`) cannot replace it.
- `STRIPE_WEBHOOK_SECRET` is needed separately if you forward signed Stripe
  events to `POST /webhook/stripe`. It is not required to create a PaymentIntent.

Saving `.env.local` is enough for subsequent requests in this development setup.
Do not commit credentials.

## 2. Start the application

```bash
docker compose up -d --build --wait
```

`compose.override.yaml` selects the Dockerfile's `development` target, which
includes development dependencies and enables `APP_ENV=dev` and `APP_DEBUG=1`.
The default Dockerfile target remains `production`, without dev dependencies.

If upgrading a container that already has an anonymous `/app/vendor` volume
from the production image, replace that volume after rebuilding:

```bash
docker compose up -d --build --force-recreate --renew-anon-volumes php
```

This replaces the PHP service's anonymous vendor and var volumes, including local
cache, profiler history and sessions. Source files and `.env.local` remain on the
host; Mercure's named volumes are not affected.

## 3. Open the demo

- Spanish store: http://localhost:8080/es/store
- English store: http://localhost:8080/en/store
- Spanish tour: http://localhost:8080/es/tour/the-problem
- English tour: http://localhost:8080/en/tour/the-problem
- Standalone Mercure demo: http://localhost:8080/mercure-demo.html

The app listens on loopback port `8080`; Mercure uses loopback port `3000`.

## Try renting a movie

Click **Alquilar por 3,99 US$** / **Rent for $3.99** on a movie. The form submits
its movie ID and a CSRF token to `POST /{_locale}/store/{movieId}/rent`. The server
sets the price to 399 cents in USD and calls `RentalPaymentGateway`.

With a valid Stripe test key, the result page shows the PaymentIntent reference,
amount and status. This is a payment request, not a completed rental: there is no
card-entry checkout, payment confirmation or saved rental history in this flow.
The separate payment-confirmation tour step demonstrates a simulated typed event;
it does not confirm this PaymentIntent.

If Stripe rejects the request, the page displays an unavailable message with HTTP
503. Check the server logs and the test secret key in `.env.local`.

## Symfony debug toolbar

Development HTML pages include the toolbar. Open a panel to inspect its request
in `/_profiler`; toolbar resources are served under `/_wdt`.

If it is missing:

1. Rebuild the development image and renew old anonymous volumes as described above.
2. Confirm `APP_ENV=dev` and `APP_DEBUG=1` in the PHP service.
3. Reload the page. Profiler links must retain `http://localhost:8080`; the Nginx
   configuration passes this listener's port to Symfony.

The profiler is registered only for dev/test; collection is disabled in tests.

## Commands and tests

```bash
# Container logs and routes
docker compose logs --tail=100 php
docker compose exec php php bin/console debug:router

# Catalog benchmark (requires a valid TMDB token)
docker compose exec php php bin/console app:benchmark

# Tests inside the development container
docker compose exec php vendor/bin/phpunit
docker compose exec php vendor/bin/phpunit tests/Catalog tests/Billing

# Configuration checks
docker compose config --quiet
docker compose exec php php bin/console lint:yaml config/
docker compose exec php php bin/console lint:twig templates/
```

For local quality tools, install PHP 8.4+, Composer and Make, then run:

```bash
composer install
make qa
# With a coverage driver available:
XDEBUG_MODE=coverage make ci
```

The runtime container does not include Make or a coverage driver. Use the PHPUnit
command directly there; run the full quality workflow in an appropriately equipped
local or CI environment. Keep `composer.lock` when installing dependencies.

## Troubleshooting

| Symptom | What to check |
| --- | --- |
| Catalog unavailable; logs show TMDB HTTP 401 | Replace `TMDB_ACCESS_TOKEN` with a valid API Read Access Token. |
| Rental unavailable; logs show Stripe HTTP 401 | Replace the example `STRIPE_SECRET_KEY` with a valid test secret key. |
| Rental form rejected after restarting containers | Reload the store to obtain a fresh session and CSRF token. |
| Old Rent button does nothing | Reload the updated store; the current button submits a form. |
| Port 8080 already in use | Stop the conflicting local service before starting Compose. |
| Mercure connection fails | Check `docker compose ps mercure` and hub logs. |
| PHPUnit or WebProfilerBundle missing | Rebuild the development target and renew the old vendor volume. |

There is no admin dashboard or database. See [scope](../README.md#scope-of-this-demo),
[architecture](ARCHITECTURE.md) and [contributing](CONTRIBUTING.md) for more detail.
The [deployment guide](DEPLOYMENT.md) is a reference exercise, not a live deployment.
