# IntegrationEngine Demo: architecture and boundaries

This guide describes the current `main` branch. The application is an executable tour of the bundle, not a persistent rental system. See [Quick Start](QUICKSTART.md) for local setup and [the bundle architecture](https://github.com/CarlosGude/integrationEngine/blob/main/docs/ARCHITECTURE.md) for its internal layers.

## Application boundaries

| Area | Responsibility | Concrete example |
| --- | --- | --- |
| `src/Integrations` | Provider configuration, Actions, Mappers and typed Responses | `Tmdb`, `Countries`, `Supplier`, `Stripe` |
| `src/Catalog`, `src/Pricing`, `src/Billing` | Application gateways and small domain models | `MovieCatalogGateway` translates TMDB responses into `Movie` |
| `src/Tour` | Bilingual step registry, source snippets and executable examples | `TourStepRunner` |
| `src/Shared` | Demo middleware, observability and resilience examples | TMDB rate limiter; circuit breaker scenarios |
| `src/Controller`, `src/Console`, `src/Command` | HTTP and CLI entry points | Storefront, tour and `app:benchmark` |

The dependency rules are recorded in [`deptrac.yaml`](../deptrac.yaml). Provider DTOs are translated at the application gateway; the domain does not need to know the bundle's response types.

## Four integrations

| Integration | Wire format | Client configuration | Live caller |
| --- | --- | --- | --- |
| TMDB | REST/JSON | Built-in REST adapter and Bearer header | Storefront, catalog gateway and tour |
| Countries | GraphQL/JSON | Built-in GraphQL adapter | `PricingGateway::countCountries()` in the tour |
| Supplier | CSV | Application-owned `SupplierCsvClient` registered as `client_service` | `PricingGateway::countSupplierPrices()` in the tour; local Compose supplier |
| Stripe | Form-encoded outbound; signed JSON webhook inbound | Bundle's `FormEncodedClientAdapter` registered as `app.client.stripe`; engine webhook parser | Rental gateway, tour and `/webhook/stripe` |

The source of truth for wiring is [`config/packages/integration_engine.yaml`](../config/packages/integration_engine.yaml) and [`config/services.yaml`](../config/services.yaml). The application registers the bundle's built-in `FormEncodedClientAdapter` as `app.client.stripe` and selects it with `client_service`, rather than the `client: form_encoded` selector. The former demo-owned `StripeFormClientAdapter` was removed.

Each outbound operation follows Action → Mapper → Response. For example, `GetMovieAction` describes the operation, `GetMovieMapper` transforms the HTTP response, and `GetMovieResponse` is a provider DTO. `MovieCatalogGateway` then builds a `Movie` domain object. The integration YAML defines the action's method, path and optional body; bundle configuration chooses the client and transport wiring.

## Batch and failures

`MovieCatalogGateway::getMoviesByIdBatch()` builds one `EngineRequest` per movie, calls `sendMany()`, then fetches the shared TMDB image configuration with a separate `send()`. It maps successful responses and returns `null` for failed or missing movie IDs. The engine's built-in HTTP adapter dispatches batch requests concurrently under its documented conditions. The configuration request and domain mapping are outside that concurrent group.

`app:benchmark` records the median, minimum and maximum elapsed time for repeated **batch** calls. It does not execute a sequential comparison or calculate a speedup ratio. Network timing depends on TMDB, rate limiting, caching and the host; no fixed multiplier is claimed here.

## Webhook and simulation

Stripe POSTs to `/webhook/stripe` enter Symfony Webhook and the engine's `IntegrationWebhookRequestParser`. The integration YAML configures timestamped HMAC verification and event mappers. `StripePaymentIntentConsumer` maps a verified remote event to a typed `StripePaymentIntentEvent`. The Billing listener logs the event and attempts to publish it to the `admin/payments` Mercure topic. It does not update a rental: this project has no persistence. [`StripeWebhookFlowTest`](../tests/Billing/Infrastructure/Webhook/StripeWebhookFlowTest.php) covers signed acceptance and invalid/expired signatures.

The tour's **payment-confirmation** step starts later in the flow: `PaymentConfirmationSimulator` constructs a typed event and dispatches it to demonstrate Billing → Mercure. It neither forges a Stripe signature nor confirms the PaymentIntent created by the separate rental step.

## What a tour step proves

| Steps | Execution | Interpretation |
| --- | --- | --- |
| `the-problem`, `parallel-requests` | Live TMDB calls with a valid token | Real provider responses; availability and latency depend on TMDB |
| `behind-the-counter` | Live Countries GraphQL and local Supplier CSV calls | Two wire formats through application gateways |
| `when-suppliers-fail`, `graceful-degradation` | Deterministic in-process circuit breaker and fallback examples | Teaching scenarios, not a circuit breaker or retry wired into TMDB/Stripe traffic |
| `renting-a-movie` | Live Stripe test PaymentIntent with valid test key | Intent creation, not a completed charge or stored rental |
| `payment-confirmation` | Simulated typed downstream event | Billing listener and Mercure path, not an inbound signed webhook |

Only the rate limiter is configured as client middleware for TMDB. `RetryMiddleware`, `CircuitBreaker`, `FallbackStrategy` and `ChaosMonkey` have focused tests and demonstrations, but retry/circuit breaker are not in the live integration configuration. The demo deliberately has no database, admin dashboard or asynchronous worker.

## Verification

The current [CI workflow](../.github/workflows/ci.yml) runs PHPUnit, PHPStan, style and Deptrac checks, mutation testing with the documented exclusions, a production dependency installation, Compose validation and Docker build. Tests use mocks and local fixtures rather than real TMDB or Stripe credentials. CI passing does not establish a hosted deployment; [`DEPLOYMENT.md`](DEPLOYMENT.md) is a reference exercise.
