# 🏗️ IntegrationEngine Demo - Architecture & Patterns

Guía de patrones, flujos y componentes del proyecto

---

## 📋 Resumen Ejecutivo

Este proyecto es una demostración de **IntegrationEngine**, un bundle de Symfony que estandariza la integración con APIs externas. Utiliza patrones de arquitectura clean con separación clara entre capas de dominio, aplicación e infraestructura.

**Métricas:** 55 tests ✅ | 4 integraciones | 3 protocolos (REST, CSV, GraphQL) | Webhooks | Parallelismo

---

## 🎯 Las 3 Capas de Arquitectura

```
🟢 Domain
  Objetos de negocio (Movie, RentalPayment)
  Movie::fromInfrastructure()

🔵 Application
  Lógica de coordinación (MovieCatalogGateway)
  getMovieById() + getMoviesByIdBatch()

🟡 Infrastructure
  API clients, mappers, adapters
  TMDB, Stripe, Supplier, Countries
```

---

## 🔌 Las 4 Integraciones

| Integración | Protocolo | Cliente | Características |
|---|---|---|---|
| **TMDB** | REST (JSON) | HttpClient (default) | Bearer auth, parallelismo, configuration |
| **Supplier** | CSV | HttpClient + parseador | Mapeo CSV → array[][] |
| **Countries** | GraphQL | GraphQLClient | Queries tipadas, rate limiting |
| **Stripe** | Form-encoded | StripeFormClientAdapter (custom) | Outbound + webhooks, timestamped HMAC |

---

## 📐 Pattern: Action → Mapper → Response

Toda integración sigue el mismo patrón de 3 responsabilidades:

### 1️⃣ Action: Define qué enviar
```php
GetMovieAction extends AbstractAction
```
→ Declara nombre, path, método, mapper

### 2️⃣ Mapper: Transforma la respuesta
```php
GetMovieMapper extends AbstractMapper
```
→ Convierte array[] crudo a DTO tipado (GetMovieResponse)

### 3️⃣ Response: DTO tipado (final readonly)
```php
GetMovieResponse implements ResponseInterface
```
→ Datos estructurados, accesibles via métodos o toArray()

### Flujo de transformación:
```
HTTP Response (JSON)
    ↓
AbstractMapper::transform(array, headers)
    ↓
DTO (GetMovieResponse)
    ↓
Domain Object (Movie::fromInfrastructure())
```

---

## 🚀 Pattern: Gateway (Application Layer)

Coordina múltiples API calls y traduce DTOs a objetos de negocio:

### MovieCatalogGateway

- `getMovieById(int)` → secuencial (getMovie + getConfiguration)
- `getMoviesByIdBatch(int[])` → paralelo con `sendMany()`
- Manejo de fallos graceful: null para películas que fallan
- Traducción: DTO → Movie (dominio)

### Flujo de batch:
```
MovieCatalogGateway::getMoviesByIdBatch([1,2,3])
    ├─→ sendMany(['get_movie' × 3])  (paralelo)
    ├─→ send('get_configuration')     (serial)
    ├─→ response.responses() × 3      (mapeos)
    └─→ Movie[] (dominio)
```

---

## 🎣 Webhooks (Stripe)

**POST /webhook/stripe** inbound → eventos de pago confirmados

### Flujo de webhook:

1. `StripePaymentIntentParser` → verifica firma HMAC (timestamped)
2. `RemoteEvent` → payload verificado
3. `StripePaymentIntentConsumer` (trait: ConsumesWebhookEvents)
4. `WebhookEventDispatcher` → StripePaymentIntentEvent (tipado)
5. `StripePaymentIntentEventListener` → updateRental()

### Diagrama de flujo:
```
Stripe POST /webhook/stripe (HMAC-SHA256)
    ↓
StripePaymentIntentParser::parse()
    ├─ verify(signature, secret)
    └─ RemoteEvent (payload verificado)
    ↓
StripePaymentIntentConsumer (trait ConsumesWebhookEvents)
    ├─ handles() → comprueba tipo de evento
    └─ dispatch(event, mapper, [])
    ↓
WebhookEventDispatcher
    └─ emit(StripePaymentIntentEvent)
    ↓
#[AsEventListener] StripePaymentIntentEventListener
    └─ onPaymentConfirmed()
```

---

## 🔧 Custom Adapter: Stripe Form-Encoded

Stripe requiere `application/x-www-form-urlencoded`, no JSON. Solución: cliente custom.

### StripeFormClientAdapter implements ClientAdapterInterface

- Recibe `ActionBodyInterface`
- Convierte: `body→toArray() | http_build_query()`
- Header: `Content-Type: application/x-www-form-urlencoded`
- Registrado en services.yaml como `app.client.stripe`

---

## 📊 Parallelismo con sendMany()

IntegrationEngine orquesta requests paralelos automáticamente:

```
MovieCatalogGateway::getMoviesByIdBatch([1,2,3,4,5])
    │
    ├─→ engine->sendMany([...])
    │       │
    │       ├─ EngineRequest('get_movie', {id:1})
    │       ├─ EngineRequest('get_movie', {id:2})
    │       ├─ EngineRequest('get_movie', {id:3})
    │       ├─ EngineRequest('get_movie', {id:4})
    │       └─ EngineRequest('get_movie', {id:5})
    │
    ├─→ BatchClientInterface (paralelo)
    │       │
    │       ├─ [HTTP GET /3/movie/1] ──┐
    │       ├─ [HTTP GET /3/movie/2] ──┼─ Ejecutan en paralelo
    │       ├─ [HTTP GET /3/movie/3] ──┤
    │       ├─ [HTTP GET /3/movie/4] ──┤
    │       └─ [HTTP GET /3/movie/5] ──┘
    │
    ├─→ BatchResultCollection
    │       │
    │       ├─ responses() [Movie, Movie, Movie, Movie, null]
    │       ├─ errors() [Error si hubo]
    │       └─ hasFailures() false
    │
    └─→ Retorna: array<int, Movie|null>

⚡ Ganancia: 5-13x speedup vs secuencial (20 películas)
```

---

## 🧪 Testing: Patrones

| Tipo de Test | Ubicación | Ejemplo |
|---|---|---|
| Parity (Legacy vs Engine) | `tests/Legacy/LegacyEngineParityTest.php` | TmdbApiService (viejo) vs TmdbIntegration (nuevo) |
| Integration (API real) | `tests/Integration/` | Llamadas reales a TMDB, Stripe, etc. |
| Unit (Mappers, Adapters) | `tests/Unit/` | CSV parsing, Movie::fromInfrastructure() |
| Quality (PHPStan, Deptrac) | CI | Type safety, architecture compliance |

---

## 📝 Configuración YAML

Cada integración tiene su YAML en `src/Integrations/[Name]/[Name].yaml`:

### Estructura del YAML:
```yaml
create_payment_intent:
    │
    ├─ action: App\Integrations\Stripe\CreatePaymentIntent\CreatePaymentIntentAction
    │
    ├─ method: POST
    │
    ├─ path: /v1/payment_intents
    │
    ├─ body: App\Integrations\Stripe\CreatePaymentIntent\CreatePaymentIntentRequest
    │   └─ [DTO tipado que serializa a x-www-form-urlencoded]
    │
    └─ mapper: App\Integrations\Stripe\Mappers\CreatePaymentIntentMapper
        └─ [transform(array) → CreatePaymentIntentResponse]
```

### Cómo se ensambla en Symfony:
```yaml
integration_engine:
    integrations:
        stripe:
            │
            ├─ client_service: app.client.stripe
            │   └─ StripeFormClientAdapter (custom)
            │       ├─ Base URL: https://api.stripe.com
            │       ├─ Authorization: Bearer [STRIPE_SECRET_KEY]
            │       └─ Content-Type: application/x-www-form-urlencoded
            │
            └─ config_path: src/Integrations/Stripe/Stripe.yaml
                └─ [Carga todas las acciones disponibles]
```

### Flujo de una acción:
```
StripeIntegration::createPaymentIntent($request)
    │
    ├─→ registry->get('stripe')
    │   └─ IntegrationEngine (contiene todas las acciones)
    │
    ├─→ engine->send('create_payment_intent', $context)
    │   │
    │   ├─ Carga acción del YAML
    │   ├─ Resuelve placeholders en path
    │   ├─ Inyecta body (DTO tipado)
    │   └─ Aplica auth (Bearer token)
    │
    ├─→ StripeFormClientAdapter (cliente custom)
    │   │
    │   ├─ Convierte body→http_build_query()
    │   ├─ Envía: POST /v1/payment_intents (form-encoded)
    │   └─ Retorna: {body: array, headers: array}
    │
    ├─→ CreatePaymentIntentMapper
    │   │
    │   └─ transform(array, headers) → CreatePaymentIntentResponse (DTO)
    │
    └─→ Retorna: CreatePaymentIntentResponse tipado
```

---

## 🔐 Seguridad & Auth

- **TMDB:** Bearer token (header Authorization)
- **Stripe (outbound):** Bearer token
- **Stripe (webhooks):** Timestamped HMAC-SHA256 verification
- **Tokens cacheados:** Cache pool per-integration (xxh128 hash)
- **401 Retry:** Si un token cached expira, refetch automático

---

## 🛠️ Tech Stack

- **Symfony:** 7.4 (Framework, Webhook, Messenger)
- **IntegrationEngine:** 6.0 (Core, lifecycle events, webhooks)
- **PHPUnit:** 10.5 (Tests)
- **PHPStan:** 2.2 (Type analysis level max)
- **PHP:** 8.4+ (readonly, named args, typed properties)

---

## 🎓 Key Takeaways

### ✅ Patrones aprendidos:

1. **Clean Architecture** → Domain/App/Infra separation
2. **Gateway Pattern** → Coordina múltiples APIs
3. **DTO Pattern** → Infrastructure → Application → Domain
4. **Adapter Pattern** → Custom clients para protocolos raros
5. **Webhook Consumer Pattern** → Eventos tipados, async
6. **Parallelism** → sendMany() para speedup 5-13x

---

**Última actualización:** Septiembre 2026 | IntegrationEngine v6.0.0
