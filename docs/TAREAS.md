# IntegrationEngine · Tareas de la demo

> **Pestaña: demo.** Repo nuevo `github.com/CarlosGude/integrationEngine-demo`, desplegado en `https://demo.integrationengine.dev`.
> El orden de ejecución lo marca **[PLAN.md](PLAN.md)**. Aquí está el *cómo* de cada tarea.
> Estándares de calidad, TDD y DoD: **PLAN.md § 1** (mismos umbrales que el bundle: PHPStan max, CS Fixer, MSI ≥ 85 %, MSI cubierto ≥ 95 %).
>
> **Este documento es un borrador de planificación original, no el plan vigente.** El estado real del proyecto vive en `PLAN.md` y diverge de aquí en arquitectura (contextos, capas Deptrac) y en las decisiones de alcance de abajo. Se conserva como referencia histórica.

---

## Decisiones de alcance (2026-09-22 — decisión de Carlos)

Estas decisiones descartan partes del plan original tras revisar la brecha entre este documento y el repo real. Se anotan aquí en vez de reescribir cada tarea, para dejar constancia de que la desviación es intencionada.

**1. Sin persistencia (Doctrine/SQLite, colas, workers, paneles).** Esto es un *tour* del bundle, no una aplicación de negocio real. No tiene sentido persistir alquileres o pagos ficticios, ni mantener infraestructura (Doctrine, RabbitMQ, un worker de Messenger, un scheduler de reinicio nocturno) para sostener datos que no son reales — y un panel de pagos o de webhooks con dos o tres filas de prueba solo mostraría estadísticas vacías, justo lo contrario de lo que se quiere transmitir. El flujo de confirmación de pago ya demuestra lo que hace falta sin nada de esto: `RentalPaymentGateway` crea el PaymentIntent contra Stripe, y el webhook se verifica, mapea y despacha como evento tipado que hoy solo se registra en el log (`StripePaymentIntentEventListener`). No hay envío en vivo al navegador en ese flujo — `MercureUpdateController`/`public/mercure-demo.html` existen pero son una demo aislada de un prototipo anterior, sin relación con alquileres ni pagos (ver PLAN.md § Next Steps).

Descartado por completo: **D4.1** (persistencia SQLite), **D4.6** (Messenger + RabbitMQ), **D4.7** (consumer idempotente sobre entidades persistidas), **D4.8** (endpoint de estado por sondeo — no hay `Rental` persistido que consultar), **D4.9** (panel de pagos), **D4.10** (bandeja de webhooks), **D4.11** (herramientas de reenvío/simulación), **D4.12** (reinicio nocturno), la parte de **D4.15** relativa a desplegar RabbitMQ/worker/scheduler, y **D5.3** (panel "Engine events").

Cubierto de otra forma: **D4.4** — el caso de uso "alquilar" existe (`RentalPaymentGateway::rentMovie()`), pero sin un agregado `Rental` persistido ni estados `pending/rented/failed` en base de datos.

**2. FrankenPHP no es relevante (D2.2).** El requisito de usar FrankenPHP en un único contenedor no aplica: el proyecto ya funciona con `php:8.4-fpm` + Nginx en un único contenedor Docker (`Dockerfile` + `docker/nginx.conf` + `docker/entrypoint.sh`) y no hay ninguna razón de negocio para migrar solo por seguir el plan original.

---

## 0 · Visión de la demo

**Qué es:** un tour guiado (EN/ES) por una **tienda ficticia de alquiler de películas** que usa el bundle para integrar proveedores reales. Cada paso: explicación, código real extraído del repo y botón **Run** que ejecuta la llamada y muestra la respuesta y la traza del engine.

**Pasos del tour (orden de lectura):**

| # | Paso | Proveedor | Demuestra | Fase |
|---|---|---|---|---|
| 1 | The problem | TMDB | Antes/después: god class frente al engine | 2 |
| 2 | Parallel requests | TMDB | Concurrencia con `sendMany()`, fallos parciales | 2 |
| 3 | Behind the counter | Proveedor propio (CSV), GraphQL público | Puntos de extensión: adaptador, GraphQL, middleware | 2 |
| 4 | When suppliers fail | Proveedor propio | Timeouts, reintentos, `Retry-After`, POST no reintentado, form-encoded, `Idempotency-Key` | 3 |
| 5 | Renting a movie | Stripe (test) | Salida form-encoded con Bearer estático | 4 |
| 6 | Payment confirmation | Stripe (test) | Webhooks firmados, RabbitMQ, idempotencia, panel | 4 |
| 7 | Partner stores | Proveedor propio | Base URL por tenant y protección SSRF | 5 |

**Etiquetado honesto:** todo lo simulado se marca en pantalla como *Simulated*. Stripe se marca *Stripe test mode — no real money moves*. TMDB lleva su atribución obligatoria.

**Arquitectura de carpetas (contextos):**
```
src/
├─ Catalog/      Domain · Application · Infrastructure/Integrations/Tmdb · UI
├─ Pricing/      Domain · Application · Infrastructure/{Http,Integrations} · UI
├─ Rental/       Domain · Application · Infrastructure/{Integrations/Supplier,Persistence} · UI
├─ Payments/     Domain · Application · Infrastructure/{Integrations/Stripe,Webhook,Persistence} · UI
├─ Partners/     (fase 5)
├─ Legacy/       god class para el antes/después (aislado)
├─ Tour/         Domain · Infrastructure · UI
└─ Shared/       Observability · Infrastructure/Middleware · Stats · Scheduler · UI
```

**Reglas de arquitectura (Deptrac, desde D2.1):**
- `*\Domain` no depende de nada salvo `Shared\Domain` (si existe) y PHP.
- `*\Application` depende de su `Domain` y de interfaces propias.
- `*\Infrastructure` depende de `Domain`, `Application`, `IntegrationEngine\*`, `Symfony\*`, `Doctrine\*`.
- `*\UI` depende de `Application`, `Domain`, `Symfony\*`, `Twig\*`.
- `Legacy` solo depende de `Catalog\Domain` y `Symfony\Contracts\HttpClient`. Nadie depende de `Legacy` salvo su controlador.
- `Tour` no depende de ningún contexto de negocio (lee código como texto).

**Convenciones:**
- PHP 8.4, Symfony 7.4 LTS, `declare(strict_types=1)` en todos los archivos.
- Clases `final` por defecto; DTOs y eventos `final readonly`.
- Tests en `tests/` reflejando `src/`. Grupo `#[Group('integration')]` para tests que necesitan servicios de Docker; excluidos del job de contrato del bundle.
- Fixtures de APIs reales en `tests/Fixtures/<proveedor>/`, grabados una vez y revisados para no contener datos personales ni claves.
- Textos del tour en `translations/tour.{en,es}.yaml`; resto de la UI en `messages.{en,es}.yaml`.

---

## FASE 2 · Demo online

### D2.1 · Bootstrap del repo y puertas de calidad

**Necesidad:** que la demo nazca con las mismas puertas que el bundle y sea instalable desde un clon limpio (el repo antiguo no lo era).

**Pasos:**
1. `composer create-project symfony/skeleton:"7.4.*" integrationEngine-demo` (PHP 8.4). Verificar que 7.4 es LTS y la versión vigente; si no, usar la LTS actual y anotarlo.
2. Dependencias:
   ```bash
   composer require carlosgude/integration-engine:^4.1.1 symfony/twig-bundle symfony/translation \
     symfony/http-client symfony/rate-limiter symfony/asset-mapper symfony/yaml symfony/runtime
   composer require --dev phpunit/phpunit symfony/browser-kit symfony/css-selector \
     phpstan/phpstan phpstan/phpstan-symfony phpstan/phpstan-phpunit phpstan/phpstan-strict-rules \
     friendsofphp/php-cs-fixer infection/infection deptrac/deptrac
   ```
   **Si Infection entra en conflicto con Symfony 7.4:** aislarlo en `tools/infection/` (proyecto Composer propio) o usar el PHAR oficial verificado, y apuntar el `Makefile` a ese binario. **Nunca** eliminarlo del proyecto; que no esté en la imagen `prod` es lo esperado (`--no-dev`), no un motivo para quitarlo.
   (Verificar nombres y versiones compatibles en Packagist; ajustar `phpunit` a la versión soportada por Symfony 7.4.)
3. `phpstan.neon`:
   ```neon
   includes:
     - vendor/phpstan/phpstan-symfony/extension.neon
     - vendor/phpstan/phpstan-symfony/rules.neon
     - vendor/phpstan/phpstan-phpunit/extension.neon
     - vendor/phpstan/phpstan-strict-rules/rules.neon
   parameters:
     level: max
     paths: [src, tests]
     symfony:
       containerXmlPath: var/cache/dev/App_KernelDevDebugContainer.xml
   ```
4. `.php-cs-fixer.dist.php`: copiar reglas del bundle (`@PhpCsFixer`, `@PhpCsFixer:risky`, `php_unit_attributes`).
5. `infection.json5`: `minMsi: 85`, `minCoveredMsi: 95`, `source.directories: ["src"]`, `excludes: ["Kernel.php"]`.
6. `deptrac.yaml` con las reglas de § 0 (capas por regex de namespace: `#^App\\[^\\]+\\Domain\\#`, etc.).
7. `Makefile` con los targets de PLAN.md § 1.1 y además `up`, `down`, `sh`, `logs`.
8. `composer.local.json.dist`:
   ```json
   {
     "repositories": [{ "type": "path", "url": "../integrationEngine", "options": { "symlink": true } }],
     "require": { "carlosgude/integration-engine": "@dev" }
   }
   ```
   `.gitignore`: `composer.local.json`, `composer.local.lock`, `.env.local`, `.env.*.local`, `var/`, `.phpunit.cache`.
   `README` (sección *Developing against a local bundle*): `cp composer.local.json.dist composer.local.json && COMPOSER=composer.local.json composer update carlosgude/integration-engine`.
9. **Test de humo (rojo primero):** `tests/Smoke/KernelBootTest.php` → arranca el kernel y comprueba que existe el servicio `IntegrationEngine\Core\Registry\IntegrationRegistry`.

**Criterios de aceptación:**
- [ ] `composer show carlosgude/integration-engine` muestra versión de Packagist.
- [ ] `grep -n '"type": "path"' composer.json composer.lock` sin resultados.
- [ ] `make ci` verde.

---

### D2.2 · Docker y CI

> **FrankenPHP no es relevante** (decisión de Carlos, 2026-09-22) — ver "Decisiones de alcance". El proyecto usa `php:8.4-fpm` + Nginx en un único contenedor.

**Archivos:** `Dockerfile`, `compose.yaml`, `compose.override.yaml`, `.dockerignore`, `Caddyfile` (si FrankenPHP lo requiere), `.github/workflows/ci.yml`, `Makefile`.

**Diseño:**
- **Servidor: FrankenPHP en un único servicio. No usar Nginx + PHP-FPM.** Motivos: un solo contenedor para la app (menos piezas en un VPS pequeño), HTTPS automático en producción sin proxy adicional y la misma imagen en local y en prod. Cambiarlo es una desviación que requiere aprobación (PLAN.md § 0.2, regla 8).
- **Imagen:** `dunglas/frankenphp:1-php8.4` (verificar tag vigente). Etapas `base` (extensiones: `intl`, `opcache`, `pdo_sqlite`, `amqp`, `zip`, `apcu`), `dev` (xdebug o pcov), `prod` (`composer install --no-dev --classmap-authoritative`, `APP_ENV=prod`, assets compilados).
- **`compose.yaml`** (base, común a todos los entornos):
  ```yaml
  services:
    php:
      build: { context: ., target: prod }
      restart: unless-stopped
      environment:
        SERVER_NAME: ${SERVER_NAME:-:80}
      healthcheck:
        test: ["CMD", "curl", "-fsS", "http://localhost/healthz"]   # endpoint en D2.19; hasta entonces, "/"
        interval: 10s
        timeout: 3s
        retries: 5
      volumes:
        - app_var:/app/var
  volumes:
    app_var:
  ```
- **`compose.override.yaml`** (dev): target `dev`, bind mount del código, puerto `80:80`, `APP_ENV=dev`.
- **CI (`ci.yml`):** jobs `cs`, `stan`, `test`, `mutation`, `deptrac` en PHP 8.4 (pcov para mutación) y `docker-build` (`docker build --target prod .`). En `test`, `vendor/bin/phpunit --exclude-group integration`; job adicional `integration` que levanta `docker compose up -d --wait` y ejecuta `--group integration`.

**Verificación roja:** antes de crear `compose.yaml`, `docker compose up -d --wait` falla. Después: `curl -fsS http://localhost/` → 200.

**Criterios de aceptación:**
- [ ] `docker compose up -d --wait` sano en local.
- [ ] Todos los jobs de CI verdes.
- [ ] Imagen `prod` no contiene `vendor/phpunit` (`docker run --rm <img> test ! -d vendor/phpunit`).

---

### D2.3 · i18n y layout base

**Archivos:** `config/packages/translation.yaml`, `config/routes.yaml`, `src/Shared/UI/LocaleRedirectController.php`, `translations/messages.en.yaml`, `translations/messages.es.yaml`, `templates/base.html.twig`, `templates/_partials/{header,footer,locale_switcher,simulated_badge}.html.twig`, `tests/Shared/UI/LocaleRedirectControllerTest.php`, `tests/Translation/TranslationParityTest.php`, `assets/styles/app.css`.

**TDD:**
1. `LocaleRedirectControllerTest::testRootRedirectsToEnglish()` — `GET /` → 302 a `/en/`.
2. `testRootRedirectsToSpanishWhenPreferred()` — `Accept-Language: es-ES` → `/es/`.
3. `testUnsupportedLocaleReturns404()` — `GET /fr/` → 404.
4. `TranslationParityTest`: carga todos los `translations/*.en.yaml` y su par `.es.yaml`; compara conjuntos de claves aplanadas; falla con la lista de diferencias.
5. `FooterTest` (WebTestCase): la portada contiene el aviso de atribución de TMDB en ambos idiomas y un enlace a `https://www.themoviedb.org`.

**Diseño:**
- Rutas de la app con prefijo: `prefix: '/{_locale}'`, `requirements: { _locale: 'en|es' }`, `defaults: { _locale: 'en' }`.
- Aviso de TMDB (texto exigido por sus condiciones; verificar redacción vigente): *"This product uses the TMDB API but is not endorsed or certified by TMDB."* + logo según sus guías de marca.
- Parcial `simulated_badge.html.twig` reutilizable con variantes `simulated` y `stripe_test`.
- Estilo sobrio y accesible (contraste AA, foco visible). Sin frameworks CSS pesados.

---

### D2.4 · Motor del tour: registro de pasos

**Archivos:** `src/Tour/Domain/TourStep.php`, `src/Tour/Domain/SnippetReference.php`, `src/Tour/Domain/TourRegistry.php` (interfaz), `src/Tour/Domain/Exception/UnknownTourStep.php`, `src/Tour/Infrastructure/YamlTourRegistry.php`, `config/tour.yaml`, `config/services.yaml`, `tests/Tour/Domain/*`, `tests/Tour/Infrastructure/YamlTourRegistryTest.php`, `tests/Fixtures/tour/*.yaml`.

**Formato de `config/tour.yaml`:**
```yaml
steps:
  - id: the-problem
    title: tour.the_problem.title          # clave de traducción
    body: tour.the_problem.body
    snippets:
      - { id: legacy.base-url, file: src/Legacy/TmdbApiService.php, label: tour.the_problem.snippet.legacy_config, lang: php }
      - { id: engine.config,  file: config/integrations/Tmdb.yaml,  label: tour.the_problem.snippet.engine_config, lang: yaml }
    runs:
      - { id: legacy, route: tour_run_the_problem_legacy, label: tour.the_problem.run.legacy }
      - { id: engine, route: tour_run_the_problem_engine, label: tour.the_problem.run.engine }
```

**TDD:**
1. `TourStepTest`: id no vacío y en kebab-case; al menos un snippet o un run.
2. `YamlTourRegistryTest`:
   - `testReturnsStepsInDeclaredOrder()`
   - `testFindsStepById()` / `testThrowsUnknownTourStep()`
   - `testPreviousOfFirstIsNull()` / `testNextOfLastIsNull()`
   - `testRejectsDuplicateIds()`
   - `testRejectsInvalidYaml()` (fixture roto)

---

### D2.5 · Extractor de snippets desde el código real

**Necesidad:** es la garantía de credibilidad del tour. Lo que se ve en pantalla es literalmente el código ejecutado; no puede desincronizarse como pasó con la landing.

**Archivos:** `src/Tour/Domain/SnippetExtractor.php` (interfaz), `src/Tour/Infrastructure/SourceSnippetExtractor.php`, `src/Tour/Infrastructure/SyntaxHighlighter.php`, `src/Tour/Domain/Exception/{SnippetNotFound,SnippetOutsideAllowedPaths,UnclosedSnippet}.php`, `tests/Tour/Infrastructure/SourceSnippetExtractorTest.php`, `tests/Fixtures/snippets/*`.

**Formato de marcadores:**
```php
// tour:start legacy.base-url
private const BASE = 'https://api.themoviedb.org/3';
// tour:end
```
```yaml
# tour:start engine.config
GetMovie:
  action: App\Catalog\Infrastructure\Integrations\Tmdb\GetMovie\Request\GetMovieAction
  method: GET
  path: /movie/{movieId}?language={language}
# tour:end
```

**Reglas:**
- Rutas relativas al `kernel.project_dir`; lista blanca: `src/`, `config/`. `realpath()` debe empezar por una de las rutas permitidas.
- Se devuelven las líneas entre marcadores, sin las líneas de marcador, con la indentación común eliminada.
- Un id aparece una sola vez por archivo.
- Resaltado en servidor con `tempest/highlight` (verificar paquete y compatibilidad; alternativa: `scrivo/highlight.php`). La salida HTML se considera segura solo tras pasar por el highlighter; nunca `|raw` sobre contenido no procesado.

**TDD:**
1. `testExtractsLinesBetweenMarkers()`
2. `testStripsCommonIndentation()`
3. `testThrowsWhenMarkerNotFound()`
4. `testThrowsWhenMarkerNotClosed()`
5. `testThrowsOnDuplicateMarkerId()`
6. `testRejectsPathTraversal()` — `../../.env`, `src/../.env.local`
7. `testRejectsPathsOutsideWhitelist()` — `vendor/…`, `var/…`, `.env`
8. `testRejectsSymlinkEscapingWhitelist()` — fixture con symlink temporal hacia fuera
9. `SyntaxHighlighterTest::testEscapesHtmlInSource()` — `'<script>'` en el código → escapado en la salida.
10. `TourSnippetsResolveTest` (se crea aquí, vacío de pasos): recorre `config/tour.yaml` y resuelve todos los snippets. Se ejecuta en CI desde el primer paso real.

---

### D2.6 · UI del tour, endpoint Run y registro de llamadas

**Archivos:** `src/Tour/UI/TourController.php`, `src/Tour/UI/RunResponse.php`, `templates/tour/index.html.twig`, `templates/tour/step.html.twig`, `templates/tour/_run_result.html.twig`, `assets/tour.js`, `src/Shared/Observability/CallTrace.php`, `src/Shared/Observability/TracedCall.php`, `src/Shared/Observability/TraceRecorderMiddleware.php`, `config/packages/integration_engine.yaml`, `tests/Tour/UI/TourControllerTest.php`, `tests/Shared/Observability/TraceRecorderMiddlewareTest.php`.

**Contrato del endpoint Run (JSON):**
```json
{
  "ok": true,
  "result": { "…": "respuesta de dominio serializada" },
  "trace": [
    { "integration": "tmdb", "action": "GetMovie", "method": "GET", "path": "/movie/{movieId}?language={language}", "durationMs": 182.4, "status": "ok" }
  ],
  "totalMs": 190.1,
  "labels": ["simulated"]
}
```
En error controlado: `ok: false`, `error: { code, message }` con mensaje traducible; nunca trazas de pila.

**`TraceRecorderMiddleware extends AbstractClientMiddleware`:**
- `process()`: mide con `hrtime(true)`, llama a `$next`, registra `TracedCall` en `CallTrace` (servicio con `ResetInterface`, vaciado por petición).
- `processMany()`: registra una entrada por clave y la duración total del batch.
- Registra también excepciones (`status: error`, código HTTP si es `RequestResponseException`) y las relanza.
- Registrar en `integration_engine.yaml` → `middlewares:` de cada integración.
- Limitación a documentar en el paso: las respuestas servidas por `CachingMiddleware` (capa exterior) no llegan a este middleware.

**TDD:**
1. `TraceRecorderMiddlewareTest`: registra éxito; registra error y relanza; `processMany` registra N entradas; path es la plantilla (`getRawPath()`), no la URL resuelta.
2. `CallTraceTest`: `reset()` vacía.
3. `TourControllerTest`:
   - `GET /en/tour` lista pasos; `GET /en/tour/the-problem` 200; `GET /en/tour/nope` 404.
   - Paso de prueba (fixture `config/tour_test.yaml` cargado en entorno `test`) muestra el snippet real y botones Run.
   - `POST /en/tour/<step>/run/<run>` → JSON con el contrato (validar claves y tipos).
   - Navegación anterior/siguiente presente.
4. `assets/tour.js` (vanilla, AssetMapper): `fetch` POST con token CSRF, estado de carga, render de `result` y `trace`, errores legibles. Sin test JS en esta fase; cubierto por `WebTestCase` del JSON.

---

### D2.7 · TMDB: configuración y películas

**Archivos:**
```
config/packages/integration_engine.yaml
config/integrations/Tmdb.yaml
src/Catalog/Infrastructure/Integrations/Tmdb/
├─ TmdbIntegration.php                       ← facade, implements IntegrationName
├─ GetConfiguration/Request/GetConfigurationAction.php
├─ GetConfiguration/Response/{GetConfigurationMapper,GetConfigurationResponse}.php
├─ GetMovie/Request/{GetMovieAction,TmdbLanguageContext}.php
├─ GetMovie/Response/{GetMovieMapper,GetMovieResponse}.php
└─ Dto/{ImagesConfigurationDto,MovieDto}.php
tests/Catalog/Infrastructure/Integrations/Tmdb/
tests/Fixtures/tmdb/{configuration.json,movie-550-en-US.json,movie-550-es-ES.json}
.env (placeholders) · .env.test (valores falsos)
```

**Configuración:**
```yaml
# config/packages/integration_engine.yaml
integration_engine:
  integrations:
    tmdb:
      base_url: 'https://api.themoviedb.org/3'
      config_path: '%kernel.project_dir%/config/integrations/Tmdb.yaml'
      middlewares:
        - App\Shared\Observability\TraceRecorderMiddleware
```
```yaml
# config/integrations/Tmdb.yaml
GetConfiguration:
  action: App\Catalog\Infrastructure\Integrations\Tmdb\GetConfiguration\Request\GetConfigurationAction
  method: GET
  path: /configuration
  cache_ttl: 86400
  authorization: { type: bearer, token: '%env(TMDB_READ_ACCESS_TOKEN)%' }
GetMovie:
  action: App\Catalog\Infrastructure\Integrations\Tmdb\GetMovie\Request\GetMovieAction
  method: GET
  path: /movie/{movieId}?language={language}
  authorization: { type: bearer, token: '%env(TMDB_READ_ACCESS_TOKEN)%' }
```
(Verificar la sintaxis exacta de `authorization` y `%env()%` en la documentación del bundle v4.1.1.)

**Grabación de fixtures (una vez, fuera de CI):**
```bash
curl -sS -H "Authorization: Bearer $TMDB_READ_ACCESS_TOKEN" "https://api.themoviedb.org/3/configuration" | jq . > tests/Fixtures/tmdb/configuration.json
curl -sS -H "Authorization: Bearer $TMDB_READ_ACCESS_TOKEN" "https://api.themoviedb.org/3/movie/550?language=en-US" | jq . > tests/Fixtures/tmdb/movie-550-en-US.json
```

**Diseño de mapeo:**
- `ImagesConfigurationDto`: `secureBaseUrl`, `posterSizes` (`list<string>`).
- `MovieDto`: `id`, `title`, `overview`, `releaseDate` (`?DateTimeImmutable`), `runtimeMinutes` (`?int`), `genres` (`list<string>`), `posterPath` (`?string`).
- **URL del póster:** se construye en `MovieCatalogGateway` (D2.8) combinando `ImagesConfigurationDto` + `posterPath` + tamaño `w342` (constante con fallback al tamaño más cercano disponible). El DTO nunca expone `secure_base_url` crudo fuera de Infrastructure.

**TDD:**
1. `GetConfigurationMapperTest`: fixture → DTO; `poster_sizes` vacío → excepción de mapeo.
2. `GetMovieMapperTest`: fixture EN y ES; `release_date` vacío → `null`; `poster_path` `null` → `null`; `genres` → lista de nombres.
3. `TmdbIntegrationTest` (con `MockHttpClient` inyectado vía `http_client` de test): cabecera `Authorization: Bearer <token de test>`; URL `/movie/550?language=es-ES`.
4. `TmdbLanguageContextTest`: `en` → `en-US`, `es` → `es-ES`, otro → excepción.

---

### D2.8 · TMDB: temporadas, Gateway y dominio

**Archivos:** `src/Catalog/Domain/{Movie,Season,Episode,PosterUrl,MovieId}.php`, `src/Catalog/Application/MovieCatalogGateway.php`, `src/Catalog/Application/Exception/MovieNotFound.php`, `src/Catalog/Infrastructure/Integrations/Tmdb/GetTvSeason/*`, `config/integrations/Tmdb.yaml`, `tests/Catalog/Application/MovieCatalogGatewayTest.php`, `tests/Catalog/Domain/*`, `tests/Fixtures/tmdb/tv-1399-season-1-en-US.json`.

**Diseño:**
- `GetTvSeason`: `path: /tv/{seriesId}/season/{seasonNumber}?language={language}` (dos placeholders de path + uno de query, espejo del antipatrón 2 de la landing).
- `MovieCatalogGateway` (Anti-Corruption Layer):
  - `find(MovieId $id, string $locale): Movie`
  - `findMany(list<MovieId> $ids, string $locale): array<int, Movie|MovieUnavailable>` (usa `sendMany()`; ver D2.11)
  - `season(int $seriesId, int $season, string $locale): Season`
  - Traduce 404 de TMDB (`RequestResponseException` con `statusCode 404`) a `MovieNotFound`.
- `Movie` (dominio): `id`, `title`, `synopsis`, `year` (`?int`), `runtime` (`?int`), `genres`, `poster` (`?PosterUrl`). Sin nombres de campos de TMDB.

**TDD:**
1. `MovieCatalogGatewayTest` (con la integración y `MockHttpClient`): mapeo a dominio; póster con URL completa `https://image.tmdb.org/t/p/w342/<path>` a partir del fixture; 404 → `MovieNotFound`; `GetConfiguration` se pide una sola vez en dos `find()` seguidos (caché).
2. `GetTvSeasonMapperTest` y test de que falta `seasonNumber` → excepción del engine **sin** petición HTTP (`getRequestsCount() === 0`).
3. `PosterUrlTest`: solo `https`, no vacía.
4. `deptrac analyse` verde: `Catalog\Domain` sin dependencias de `Infrastructure` ni `IntegrationEngine`.

---

### D2.9 · Legacy y tests de caracterización

**Necesidad:** el antes/después debe comparar dos implementaciones que hacen **exactamente** lo mismo; si no, la comparación es trampa.

**Archivos:** `src/Legacy/TmdbApiService.php`, `src/Legacy/UI/LegacyCatalogController.php`, `tests/Legacy/LegacyEngineParityTest.php`, `tests/Legacy/TmdbApiServiceTest.php`.

**`TmdbApiService` debe contener deliberadamente los 5 antipatrones de la landing:**
1. `private const BASE = 'https://api.themoviedb.org/3';` y rutas en cada método.
2. Concatenación de strings para `/tv/' . $id . '/season/' . $season` sin validar nulos.
3. Arrays crudos con claves de TMDB (`poster_path`, `secure_base_url`) devueltos al controlador.
4. El controlador importa el servicio directamente y transforma arrays.
5. `fetchMany()` con `foreach` secuencial.

Cada antipatrón rodeado de marcadores `tour:start legacy.<n>` / `tour:end`. Cabecera de clase con comentario: *"Intentionally written the wrong way for the 'before' side of the tour. Do not copy."*

**TDD:**
1. `TmdbApiServiceTest`: comportamiento caracterizado con `MockHttpClient` y los mismos fixtures.
2. `LegacyEngineParityTest`:
   - `testSingleMovieProducesSameDomainOutput()` — `LegacyCatalogController` y el controlador del engine devuelven el mismo JSON para `550` en `en` y `es`.
   - `testSeasonProducesSameOutput()`.
   - `testManyMoviesProduceSameOutputRegardlessOfOrder()`.
3. `deptrac`: capa `Legacy` aislada (regla de § 0).

---

### D2.10 · Paso 1 del tour: "The problem"

**Archivos:** `config/tour.yaml`, `translations/tour.en.yaml`, `translations/tour.es.yaml`, `src/Catalog/UI/TourRunController.php` (runs del paso 1), marcadores en `src/Legacy/` y `src/Catalog/`, `config/integrations/Tmdb.yaml`, `tests/Tour/TourSnippetsResolveTest.php`, `tests/Catalog/UI/TourRunControllerTest.php`.

**Contenido (redactar en inglés y traducir):**
- Intro de 3 frases: "Every external API ends up shaped differently…".
- Cinco pestañas, una por antipatrón, cada una con *Before* (snippet legacy) y *After* (snippet engine) y una frase de "why it matters" (reutilizar el razonamiento de la landing).
- Runs: *Run legacy* y *Run engine* sobre la película 550, mostrando que el resultado es idéntico y que la traza del engine enumera las llamadas.

**TDD:**
1. `TourSnippetsResolveTest` verde con los snippets del paso 1.
2. `TourRunControllerTest`: ambos runs devuelven JSON con el contrato y el mismo `result`.
3. `TranslationParityTest` verde.

**Revisión manual:** leer el paso en EN y ES en móvil y escritorio; captura en el PR.

---

### D2.11 · Catálogo en paralelo

**Archivos:** `config/packages/catalog.yaml`, `src/Catalog/Application/Storefront.php`, `src/Catalog/Domain/MovieUnavailable.php`, `src/Catalog/UI/StorefrontController.php`, `src/Catalog/UI/MovieController.php`, `templates/store/{index,movie,_card,_card_unavailable}.html.twig`, `tests/Catalog/Application/StorefrontTest.php`, `tests/Catalog/UI/*`.

**Diseño:**
- `app.catalog.movie_ids`: 20-30 ids de TMDB curados (películas conocidas, clasificación apta para todos los públicos). Parámetro en `config/packages/catalog.yaml`.
- `MovieCatalogGateway::findMany()` usa `sendMany()` y convierte cada `Throwable` del `BatchResultCollection` en `MovieUnavailable($id, $reason)`.
- Portada `/{_locale}/` con tarjetas (póster, título, año). Ficha `/{_locale}/movies/{id}`.

**TDD:**
1. `StorefrontTest`: todos OK → N `Movie`; una respuesta 500 → N-1 `Movie` + 1 `MovieUnavailable`; todas fallan → lista de `MovieUnavailable` sin excepción.
2. `StorefrontControllerTest`: renderiza tarjetas y tarjeta de no disponible; `lazy loading` en imágenes (`loading="lazy"`), `alt` con el título.
3. `MovieControllerTest`: 200 con datos; id inexistente → 404 con plantilla propia.

---

### D2.12 · Paso 2 del tour: "Parallel requests" y benchmark

**Archivos:** `src/Shared/Stats/Median.php`, `src/Catalog/Application/LoadStrategyTimer.php`, `src/Catalog/UI/Console/BenchmarkCommand.php`, `src/Catalog/UI/TourRunController.php`, `config/tour.yaml`, `translations/tour.{en,es}.yaml`, `tests/Shared/Stats/MedianTest.php`, `tests/Catalog/Application/LoadStrategyTimerTest.php`, `tests/Catalog/UI/Console/BenchmarkCommandTest.php`.

**Diseño:**
- `LoadStrategyTimer::sequential(list<MovieId>)` (llamadas `send()` en bucle) y `parallel(list<MovieId>)` (`sendMany()`); devuelven resultados + ms.
- Run del paso: 10 películas; muestra ambas duraciones, las tarjetas cargadas y la traza.
- **Caché:** el run del tour debe desactivar la caché de respuestas de TMDB para ser honesto, o mostrar explícitamente "served from cache". Decisión en el PR; por defecto, desactivar para estas dos llamadas mediante cabecera/contexto o integración separada `tmdb_uncached` sin `cache_ttl`.
- `app:benchmark --count=10 --repeat=20`: tabla con mediana, p90 y mínimo por estrategia. Salida también en `--format=json` para publicar en la landing.

**TDD:**
1. `MedianTest`: impar, par, un elemento, vacío → excepción; `p90`.
2. `LoadStrategyTimerTest` con `MockHttpClient` y respuestas con retardo simulado (`MockResponse` con `info: ['total_time' => …]` o callback); comprobar que `parallel` inicia todas las peticiones antes de consumirlas (orden registrado en el callback), no por tiempos reales.
3. `BenchmarkCommandTest` con `CommandTester`: salida contiene ambas estrategias; `--format=json` es JSON válido con claves esperadas; `--repeat=0` → error.

---

### D2.13 · Proveedor propio y adaptador CSV

**Necesidad:** demostrar que un protocolo no JSON se integra con la misma forma, y tener un proveedor controlado para los fallos de la fase 3.

**Archivos:**
```
docker/supplier/
├─ Dockerfile              ← php:8.4-cli-alpine, `php -S 0.0.0.0:80 -t public public/index.php`
├─ public/index.php        ← router mínimo
├─ public/prices.csv
├─ src/{Router,PricesEndpoint}.php
├─ tests/PricesEndpointTest.php
└─ phpunit.xml.dist
compose.yaml               ← servicio `supplier` (sin puertos publicados)
src/Pricing/Infrastructure/Http/CsvClientAdapter.php
src/Pricing/Infrastructure/Integrations/Supplier/{SupplierPricesIntegration,GetPriceList/*}.php
src/Pricing/Domain/{Price,PriceList}.php
src/Pricing/Application/PriceCatalog.php
config/integrations/SupplierPrices.yaml
tests/Pricing/*
```

**CSV:** `movie_id,price_cents,currency` con precios para todos los ids de `app.catalog.movie_ids`.

**`CsvClientAdapter implements ClientAdapterInterface`:**
- `getClientType(): 'csv'`, `requiresPath(): true`, `requiresMethod(): false`.
- Descarga con `HttpClientInterface`, parsea con `str_getcsv` / `SplFileObject`, devuelve `['body' => list<array<string,string>>, 'headers' => …]` según el contrato del bundle.
- Registrado con el tag `integration_engine.client_adapter` y usado con `client: csv`.

**TDD:**
1. `CsvClientAdapterTest`: CSV válido; cabecera ausente → `RequestResponseException`; fila con columnas de menos → excepción con número de fila; CSV vacío → lista vacía; respuesta 500 → `RequestResponseException`.
2. `PriceCatalogTest`: precio por película; película sin precio → `null`.
3. `PricesEndpointTest` (en `docker/supplier/`, ejecutado en CI con `php docker/supplier/vendor/bin/phpunit` o con PHPUnit del proyecto apuntando a ese directorio): `GET /prices.csv` → 200 `text/csv`.
4. Test `integration`: desde el contenedor `php`, la integración obtiene la lista real del servicio.

---

### D2.14 · GraphQL y middleware propio

**Archivos:** `src/Pricing/Infrastructure/Integrations/Countries/*`, `src/Pricing/Application/StoreRegion.php`, `config/integrations/Countries.yaml`, `src/Shared/Infrastructure/Middleware/RateLimitMiddleware.php`, `config/packages/rate_limiter.yaml`, `config/packages/integration_engine.yaml`, `tests/Pricing/*`, `tests/Shared/Infrastructure/Middleware/RateLimitMiddlewareTest.php`, `tests/Fixtures/countries/*.json`.

**Pasos:**
1. **Verificación previa (obligatoria):** comprobar que una API GraphQL pública de países responde sin autenticación (candidata: `https://countries.trevorblades.com/`). Anotar en el PR la consulta probada y la respuesta. Si no responde o exige clave, **parar** y proponer otra API GraphQL pública estable.
2. Integración `countries` con `client: graphql`; acción `GetCountry` con `GraphQLBodyInterface` (query `country(code: $code) { name emoji currency }`).
3. `StoreRegion`: locale `en` → `US`, `es` → `ES`; muestra "Prices shown for 🇪🇸 Spain (EUR)" en la tienda.
4. `RateLimitMiddleware extends AbstractClientMiddleware`: `RateLimiterFactory` inyectada (`token_bucket`); `process()` consume 1 token (espera con `reserve()->wait()` o lanza excepción según configuración: elegir **lanzar** `RateLimitExceeded` para no bloquear workers web y documentarlo); `processMany()` consume N tokens.
5. Aplicar el middleware a `tmdb` en `integration_engine.yaml` (orden: `RateLimitMiddleware` antes de `TraceRecorderMiddleware`).

**TDD:**
1. `GetCountryBodyTest`: query y variables.
2. `GetCountryMapperTest` con fixture.
3. `RateLimitMiddlewareTest` con `InMemoryStorage`: consume; al agotar → excepción; `processMany` de 5 con 3 tokens → excepción sin llamar a `$next`.

---

### D2.15 · Paso 3 del tour: "Behind the counter"

**Archivos:** `config/tour.yaml`, `translations/tour.{en,es}.yaml`, `src/Pricing/UI/TourRunController.php`, marcadores en `src/Pricing/`, `src/Shared/Infrastructure/Middleware/`, `config/packages/integration_engine.yaml`, `tests/Pricing/UI/*`.

**Contenido:** tres bloques — *A different protocol* (CSV + adaptador), *GraphQL* (misma forma, otro cliente), *Your own middleware* (rate limiting). Cada bloque con snippet de configuración + clase, y Run. Mensaje clave: "the bundle was extended without touching its code".

**TDD:** `TourSnippetsResolveTest`, runs con JSON válido, paridad de traducciones.

---

### D2.16 · Endurecimiento para uso público

**Archivos:** `config/packages/rate_limiter.yaml`, `src/Tour/UI/RunRateLimitSubscriber.php`, `config/packages/prod/framework.yaml`, `config/packages/prod/monolog.yaml`, `templates/bundles/TwigBundle/Exception/{error,error404,error429}.html.twig`, `Caddyfile` (o configuración de FrankenPHP), `tests/Tour/UI/RunRateLimitSubscriberTest.php`, `tests/Shared/UI/SecurityHeadersTest.php`.

**Diseño:**
- Limitador `tour_run`: `sliding_window`, 30 peticiones / 5 min por IP (ajustable por env). Respuesta 429 JSON con `Retry-After`.
- Todos los endpoints `POST` con CSRF (`csrf_token('tour_run')`).
- `trusted_proxies` configurado solo si hay proxy delante (Caddy/FrankenPHP directo → no).
- Cabeceras: `Content-Security-Policy` (sin `unsafe-inline` en scripts; imágenes `self` + `image.tmdb.org`), `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` restrictiva, `Strict-Transport-Security` en prod.
- Logs en prod: nivel `warning`, sin cuerpos de peticiones ni cabeceras de autorización.

**TDD:**
1. `RunRateLimitSubscriberTest`: N permitidas, N+1 → 429 con `Retry-After`; otra IP no afectada.
2. `SecurityHeadersTest` (entorno `test` con la misma configuración de cabeceras que `prod` vía subscriber de Symfony, no solo Caddy): presencia y valores.
3. `CsrfTest`: run sin token → 403.

---

### D2.17 · VPS

**Archivo en repo:** `docs/ops/VPS.md` (checklist reproducible, sin IPs ni secretos).

**Pasos (manuales, cada uno con evidencia en el PR):**
1. **Hetzner Cloud:** servidor de vCPU compartida de la gama más barata que cumpla **≥ 4 GB RAM** (RabbitMQ en la fase 4), Ubuntu 24.04 LTS, ubicación en la UE, IPv4 incluida. Comprobar precio final con IVA ≤ 10 €/mes. Añadir tu clave SSH al crear el servidor.
2. **Hetzner Cloud Firewall:** entrada `22/tcp` solo desde tu IP; `80/tcp` y `443/tcp` desde cualquier origen; salida abierta.
3. **Sistema:**
   ```bash
   adduser --disabled-password deploy && usermod -aG sudo deploy
   # copiar ~/.ssh/authorized_keys a deploy
   sed -i 's/^#\?PasswordAuthentication .*/PasswordAuthentication no/; s/^#\?PermitRootLogin .*/PermitRootLogin no/' /etc/ssh/sshd_config
   systemctl restart ssh
   apt-get update && apt-get -y upgrade && apt-get -y install unattended-upgrades fail2ban
   dpkg-reconfigure -plow unattended-upgrades
   ```
4. **Docker Engine** desde el repositorio oficial de Docker (no el paquete de Ubuntu); `usermod -aG docker deploy`.
5. **Directorio:** `/srv/integrationengine-demo/` propiedad de `deploy`.
6. **DNS en Cloudflare:** registro `A demo → <IPv4>` en modo **DNS only** (nube gris) para que FrankenPHP/Caddy obtenga el certificado de Let's Encrypt.
7. **Swap** de 2 GB (seguro ante picos de Composer/RabbitMQ).

**Verificación:**
- [ ] `ssh root@<ip>` → rechazado; `ssh -o PreferredAuthentications=password deploy@<ip>` → rechazado.
- [ ] `nmap -Pn <ip>` desde fuera de tu IP: solo 80 y 443 abiertos.
- [ ] `dig +short demo.integrationengine.dev` → IPv4 del VPS.
- [ ] `unattended-upgrades --dry-run` sin errores.

---

### D2.18 · Despliegue continuo

**Archivos:** `compose.prod.yaml`, `.github/workflows/deploy.yml`, `docs/ops/DEPLOY.md`.

**`compose.prod.yaml` (se aplica sobre `compose.yaml`):**
```yaml
services:
  php:
    image: ghcr.io/carlosgude/integrationengine-demo:${IMAGE_TAG:-latest}
    build: !reset null
    env_file: [.env.prod.local]
    environment:
      SERVER_NAME: demo.integrationengine.dev
      APP_ENV: prod
    ports: ["80:80", "443:443", "443:443/udp"]
    volumes:
      - caddy_data:/data
      - caddy_config:/config
volumes:
  caddy_data:
  caddy_config:
```

**Workflow `deploy.yml` (en `push` a `main` tras CI verde):**
1. Build `--target prod` y push a GHCR con tags `sha-<short>` y `latest`.
2. SSH al VPS (secretos `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS`):
   ```bash
   cd /srv/integrationengine-demo
   git fetch --depth=1 origin main && git reset --hard origin/main   # solo archivos compose y docs
   IMAGE_TAG=sha-XXXX docker compose -f compose.yaml -f compose.prod.yaml pull
   IMAGE_TAG=sha-XXXX docker compose -f compose.yaml -f compose.prod.yaml up -d --wait
   docker image prune -f
   ```
3. Prueba de humo: `curl -fsS https://demo.integrationengine.dev/en/` y `…/en/tour/the-problem`.
4. Si falla la prueba de humo: `up -d` con el tag anterior (guardado en `/srv/integrationengine-demo/.last_good_tag`) y workflow en rojo.

**Secretos en el VPS:** `/srv/integrationengine-demo/.env.prod.local` (permisos 600, propietario `deploy`) con `APP_SECRET`, `TMDB_READ_ACCESS_TOKEN`. **Nunca** en GitHub Actions salvo los de despliegue.

**Verificación:**
- [ ] PR fusionado → despliegue automático verde.
- [ ] HTTPS válido (`curl -vI https://demo.integrationengine.dev 2>&1 | grep "SSL certificate verify ok"`).
- [ ] Rollback probado una vez forzando una prueba de humo fallida en una rama de prueba.

---

### D2.19 · Healthcheck y operación mínima

**Archivos:** `src/Shared/UI/HealthController.php`, `compose.yaml` (healthcheck a `/healthz`), `compose.prod.yaml` (logging), `docs/ops/RUNBOOK.md`, `tests/Shared/UI/HealthControllerTest.php`.

**Diseño:**
- `GET /healthz` → `200 {"status":"ok"}`; no consulta APIs externas (evita consumir cuota con cada chequeo); comprueba que el kernel arranca y que `var/` es escribible. Sin versión, sin hostname.
- Logging Docker: `json-file`, `max-size: 10m`, `max-file: 3`.
- Monitor externo gratuito (p. ej., UptimeRobot o similar, verificar condiciones) cada 5 min sobre `/healthz` y sobre `/en/`, alerta por email.
- `RUNBOOK.md`: cómo ver logs, reiniciar, desplegar un tag concreto, rotar `TMDB_READ_ACCESS_TOKEN`, qué hacer si TMDB limita.

**TDD:** `HealthControllerTest`: 200, JSON exacto, no expone cabeceras ni datos del entorno.

---

### D2.20 · README de la demo y archivo del repo antiguo

**Archivos:** `README.md`, `CHANGELOG.md`, `docs/ARCHITECTURE.md`; en `integrationEngine-use-example`: `README.md`.

**README de la demo (inglés):**
1. Una frase + enlace a `https://demo.integrationengine.dev` + captura del paso 1.
2. *What this demo shows* (tabla de pasos de § 0).
3. *Reviewing in 10 minutes* (3-4 enlaces directos al código clave).
4. *Run it locally* (`make up`, variables necesarias, cómo obtener un token de TMDB).
5. *Architecture* (contextos, reglas Deptrac, enlace a `docs/ARCHITECTURE.md`).
6. *Quality gates* (tabla y badges de CI).
7. *Attribution* (TMDB; más adelante Stripe test mode).
8. *Developing against a local bundle*.

**Repo antiguo:**
1. Banner al inicio: `> **Archived.** This reference has been replaced by [integrationEngine-demo](https://github.com/CarlosGude/integrationEngine-demo) — live at https://demo.integrationengine.dev`.
2. Commit, push y **Settings → Archive this repository**.

(Los enlaces del bundle y la landing se actualizan en B2.1, mismo día.)

---

### D2.21 · Revisión en frío y demo v1.0

**Checklist (evidencia en el PR):**
- [ ] **Recruiter (30 s):** desde la landing, "Live demo" → portada → paso 1. ¿Se entiende qué hace el proyecto sin leer código?
- [ ] **Tech lead (10 min):** README de la demo → paso 1 (snippets y runs) → paso 2 (tiempos) → paso 3 → código de `MovieCatalogGateway` → CI verde → `deptrac.yaml`.
- [ ] EN y ES completos, sin claves de traducción visibles.
- [ ] Móvil (375 px) y escritorio sin desbordes.
- [ ] Lighthouse (portada y paso 1): Accessibility ≥ 90, Best Practices ≥ 90.
- [ ] Atribución de TMDB visible en todas las páginas con datos.
- [ ] Ninguna página muestra trazas de error ni datos de entorno (forzar 404, 429 y 500).
- [ ] `make ci` verde; despliegue verde.
- [ ] Tag `v1.0.0` y `CHANGELOG.md`.

---

## FASE 3 · Integraciones robustas

### D3.1 · Proveedor: escenarios y reservas

**Archivos:** `docker/supplier/src/{StockEndpoint,ReservationsEndpoint,ScenarioStore}.php`, `docker/supplier/public/index.php`, `docker/supplier/tests/*`, `compose.yaml`.

**Contrato del proveedor:**

| Endpoint | Comportamiento |
|---|---|
| `GET /stock/{movieId}` | Escenario por cabecera `X-Demo-Scenario` y clave de ejecución `X-Demo-Run-Id` |
| · `ok` | 200 `{"movieId":550,"copies":3}` |
| · `fail-twice` | 503, 503, 200 para el mismo `X-Demo-Run-Id` |
| · `rate-limited` | 429 con `Retry-After: 1`, luego 200 |
| · `slow` | espera 3 s y responde 200 |
| · `always-fail` | 503 siempre |
| `POST /reservations` | Exige `Content-Type: application/x-www-form-urlencoded` (si no → 415) e `Idempotency-Key` (si no → 400). Misma clave → misma reserva (201 la primera, 200 las siguientes con el mismo cuerpo). Escenario `fail-twice` también aplicable. |

Todas las respuestas incluyen `X-Attempt: <n>` (número de intento para ese `X-Demo-Run-Id`).

`ScenarioStore`: contadores y reservas en APCu o archivo en `/tmp` (el servicio no tiene estado persistente; se reinicia limpio).

**TDD (`docker/supplier/tests`):**
1. `StockEndpointTest` por escenario, incluido `X-Attempt` y aislamiento entre `X-Demo-Run-Id` distintos.
2. `ReservationsEndpointTest`: 415 sin form; 400 sin `Idempotency-Key`; 201 y luego 200 idéntico con la misma clave; claves distintas → reservas distintas.
3. El CI de la demo ejecuta estos tests (job `supplier`).

---

### D3.3 · Reservas form-encoded contra el commit del bundle

> Se ejecuta **antes** de publicar v4.2.0 (día 36), usando `composer.local.json`.

**Archivos:** `config/integrations/Supplier.yaml`, `config/packages/integration_engine.yaml`, `src/Rental/Infrastructure/Integrations/Supplier/{SupplierIntegration,CreateReservation/*}.php`, `src/Rental/Application/ReserveCopy.php`, `src/Rental/Domain/{Reservation,ReservationId}.php`, `tests/Rental/*`.

**Diseño:**
- `CreateReservationBody implements FormEncodedBodyInterface` (`movie_id`, `copies`, `store` como campos).
- `Idempotency-Key` enviada con `RequestHeadersInterface` desde el facade (valor generado por `ReserveCopy`, estable por intento de usuario).
- Integración `supplier` con `base_url: 'http://supplier'` (env `SUPPLIER_URL`).

**TDD:**
1. `CreateReservationBodyTest`: `toArray()`; implementa `FormEncodedBodyInterface`.
2. `SupplierIntegrationTest` con `MockHttpClient`: `Content-Type` form, cuerpo exacto, cabecera `Idempotency-Key`.
3. `ReserveCopyTest`: misma clave en reintento de usuario → misma `ReservationId`.
4. `#[Group('integration')] SupplierReservationsIntegrationTest`: contra el servicio real en Docker, 201 y luego 200 con la misma clave.

**Criterio adicional:** anotar en el PR del bundle (B3.3) el enlace a este PR como evidencia de consumidor real.

---

### D3.2 · Stock con resiliencia contra el commit del bundle

> Se ejecuta **antes** de publicar v4.3.0 (día 41).

**Archivos:** `config/packages/integration_engine.yaml`, `config/integrations/Supplier.yaml`, `src/Rental/Infrastructure/Integrations/Supplier/GetStock/*`, `src/Rental/Application/StockGateway.php`, `src/Rental/Domain/{Stock,StockUnavailable}.php`, `tests/Rental/*`.

**Configuración:**
```yaml
integration_engine:
  integrations:
    supplier:
      base_url: '%env(SUPPLIER_URL)%'
      config_path: '%kernel.project_dir%/config/integrations/Supplier.yaml'
      timeout: 1.5
      max_duration: 6.0
      retry:
        max_retries: 3
        delay_ms: 100
        multiplier: 2.0
        max_delay_ms: 1000
        jitter: 0.1
      middlewares:
        - App\Shared\Observability\TraceRecorderMiddleware
```

**Diseño:**
- `StockGateway::check(MovieId, Scenario, RunId): Stock|StockUnavailable` — pasa `X-Demo-Scenario` y `X-Demo-Run-Id` como cabeceras; lee `X-Attempt` de las cabeceras de respuesta (el mapper recibe cabeceras, ver `MapperReceivesHeadersTest` del bundle) para mostrar cuántos intentos hubo.

**TDD (unitarios con `MockHttpClient` + `RetryableHttpClient` configurado como en producción):**
1. `fail-twice` → `Stock` con `attempts: 3`.
2. `always-fail` → `StockUnavailable` tras 4 intentos.
3. `rate-limited` → éxito tras respetar `Retry-After` (sin `sleep` real: estrategia/reloj inyectado según la solución de B3.6).
4. `slow` con `timeout: 1.5` → `StockUnavailable(reason: timeout)`.
5. `POST /reservations` con `fail-twice` y sin opt-in → falla al primer intento (no se reintenta POST).
6. `#[Group('integration')]` contra el servicio real: `fail-twice` y `slow`.

---

### D3.4 · Paso 4 del tour: "When suppliers fail"

**Archivos:** `config/tour.yaml`, `translations/tour.{en,es}.yaml`, `src/Rental/UI/TourRunController.php`, `templates/tour/_scenario_buttons.html.twig`, marcadores en `config/packages/integration_engine.yaml` y `src/Rental/`, `tests/Rental/UI/*`.

**Contenido:**
- Intro: "External systems fail. The question is what your integration does about it."
- Snippet de configuración `timeout` + `retry` (declarativo).
- Botones: *Healthy*, *Fails twice*, *Rate limited*, *Too slow*, *Always down*, *Reserve twice (same key)*, *Reserve with a failing supplier (POST)*.
- Para cada run: resultado, intentos (`X-Attempt`), duración total y explicación de una frase ("GET is idempotent, so it was retried" / "POST is not retried by default: retrying could create two reservations").
- Enlace al ADR 0010 del bundle.

**TDD:** `WebTestCase` de cada botón con el proveedor simulado por `MockHttpClient` (sin Docker); `TourSnippetsResolveTest`; paridad de traducciones.

---

### D3.5 · Despliegue y demo v1.1

**Archivos:** `compose.prod.yaml`, `CHANGELOG.md`, `docs/ops/RUNBOOK.md`.

**Pasos:**
1. `supplier` en producción sin `ports:` publicados; imagen construida en CI y publicada en GHCR (`integrationengine-demo-supplier`).
2. `SUPPLIER_URL=http://supplier` en `.env.prod.local`.
3. Despliegue, prueba de humo de los 7 botones del paso 4.
4. Tag `v1.1.0`.

**Verificación:**
- [ ] `curl -m 3 http://<ip-vps>:8080` y variantes → sin respuesta; `docker compose exec php curl -fsS http://supplier/stock/550 -H 'X-Demo-Scenario: ok' -H 'X-Demo-Run-Id: t'` → 200.

---

## FASE 4 · Bidireccional con Stripe

### D4.1 · Persistencia SQLite

> **Descartado** (decisión de Carlos, 2026-09-22) — ver "Decisiones de alcance". Sin Doctrine, sin persistencia: es un tour, no una app real.

**Archivos:** `config/packages/doctrine.yaml`, `config/packages/doctrine_migrations.yaml`, `migrations/Version*.php`, `src/Rental/Domain/{Rental,RentalId,RentalStatus}.php`, `src/Rental/Domain/RentalRepository.php`, `src/Rental/Infrastructure/Persistence/DoctrineRentalRepository.php`, `src/Payments/Domain/{WebhookDelivery,WebhookDeliveryId,DeliveryStatus,SignatureStatus}.php`, `src/Payments/Domain/WebhookDeliveryRepository.php`, `src/Payments/Infrastructure/Persistence/DoctrineWebhookDeliveryRepository.php`, `src/Payments/Domain/ProcessedEventRepository.php` (+ Doctrine), `tests/Rental/Infrastructure/Persistence/*`, `tests/Payments/Infrastructure/Persistence/*`, `compose.yaml` (volumen `app_data`).

**Dependencias:** `symfony/orm-pack` (o `doctrine/doctrine-bundle` + `doctrine/orm` + `doctrine/doctrine-migrations-bundle`), `symfony/uid`.

**Modelo:**
- `Rental`: `id` (UUID v7), `movieId` (int), `amountCents` (int), `currency` (string 3), `status` (`pending|rented|failed`), `paymentIntentId` (`?string`), `createdAt`, `updatedAt`. Transiciones válidas: `pending → rented`, `pending → failed`; `rented`/`failed` son finales (métodos de dominio `markRented()`, `markFailed()` que lanzan en transición inválida).
- `WebhookDelivery`: `id` (UUID v7), `receivedAt`, `integration`, `eventId` (`?string`), `eventType` (`?string`), `signatureStatus` (`valid|invalid|missing|malformed|expired`), `rejectionReason` (`?string`), `signatureHeader` (`?string`, truncado a 512), `rawPayload` (text, máx. 64 KB, truncado con marca), `mappedEvent` (`?json`), `status` (`received|queued|processed|duplicate|rejected|ignored`), `processedAt` (`?`), `source` (`stripe|replay|simulated`).
- `ProcessedEvent`: `eventId` (PK), `processedAt` — base de la deduplicación.

**Base de datos:** `DATABASE_URL="sqlite:///%kernel.project_dir%/var/data/demo.sqlite"`; en test `sqlite:///:memory:`.

**TDD:**
1. `RentalTest`: transiciones válidas e inválidas.
2. `WebhookDeliveryTest`: truncado de payload y cabecera; transición `queued → processed|duplicate`.
3. Repositorios (`KernelTestCase` + esquema creado en memoria): guardar/recuperar; `ProcessedEventRepository::markIfNew(string $eventId): bool` devuelve `true` la primera vez y `false` después (restricción de clave primaria, no `SELECT` previo).
4. `bin/console doctrine:schema:validate` en CI.

---

### D4.2 · Stripe: crear PaymentIntent

**Archivos:**
```
config/integrations/Stripe.yaml
config/packages/integration_engine.yaml
src/Payments/Infrastructure/Integrations/Stripe/
├─ StripeIntegration.php
├─ CreatePaymentIntent/Request/{CreatePaymentIntentAction,CreatePaymentIntentBody}.php
├─ CreatePaymentIntent/Response/{CreatePaymentIntentMapper,CreatePaymentIntentResponse}.php
└─ Dto/PaymentIntentDto.php
src/Payments/Application/{PaymentsGateway,Exception/PaymentDeclined}.php
src/Payments/Domain/{TestCard,Money}.php
tests/Payments/*
tests/Fixtures/stripe/{payment_intent.succeeded.json,card_declined.json}
```

**Antes de escribir código (verificación en la documentación oficial de Stripe, anotada en el PR):**
- Parámetros para confirmar al crear sin redirecciones: `confirm=true` con `automatic_payment_methods[enabled]=true` y `automatic_payment_methods[allow_redirects]=never` (o `payment_method_types[]=card`).
- IDs de PaymentMethod de prueba vigentes para éxito (`pm_card_visa`) y rechazo genérico (candidato: `pm_card_visa_chargeDeclined`).
- Formato del error 402 (`error.type`, `error.code`, `error.decline_code`) y si el PaymentIntent queda creado con `requires_payment_method` y emite `payment_intent.payment_failed`.
- Versión de API vigente para fijar en `Stripe-Version`.

**Configuración:**
```yaml
integration_engine:
  integrations:
    stripe:
      base_url: 'https://api.stripe.com'
      config_path: '%kernel.project_dir%/config/integrations/Stripe.yaml'
      headers:
        Stripe-Version: '%env(STRIPE_API_VERSION)%'
      middlewares:
        - App\Shared\Observability\TraceRecorderMiddleware
```
```yaml
CreatePaymentIntent:
  action: App\Payments\Infrastructure\Integrations\Stripe\CreatePaymentIntent\Request\CreatePaymentIntentAction
  method: POST
  path: /v1/payment_intents
  body: App\Payments\Infrastructure\Integrations\Stripe\CreatePaymentIntent\Request\CreatePaymentIntentBody
  authorization: { type: bearer, token: '%env(STRIPE_SECRET_KEY)%' }
```

**Body (`FormEncodedBodyInterface`):**
```php
[
  'amount' => 399, 'currency' => 'eur',
  'payment_method' => 'pm_card_visa',
  'confirm' => 'true',
  'automatic_payment_methods' => ['enabled' => 'true', 'allow_redirects' => 'never'],
  'metadata' => ['rental_id' => '<uuid>', 'movie_id' => '550'],
  'description' => 'IntegrationEngine demo rental',
]
```

**Diseño:**
- `TestCard` enum: `Success`, `Declined` → id de PaymentMethod. **Nunca** se aceptan ids arbitrarios del usuario.
- `Idempotency-Key: rental-<rentalId>` vía `RequestHeadersInterface`.
- `PaymentsGateway::createPayment(Rental, TestCard): PaymentIntentDto`; traduce `RequestResponseException` con `statusCode 402` a `PaymentDeclined` (conservando el id del PaymentIntent si viene en el error).
- Guardarraíl: si `STRIPE_SECRET_KEY` no empieza por `sk_test_` → excepción al arrancar el servicio (test).

**TDD:**
1. `CreatePaymentIntentBodyTest`: estructura exacta; implementa `FormEncodedBodyInterface`.
2. `StripeIntegrationTest` (`MockHttpClient`): método, URL, `Authorization: Bearer sk_test_…`, `Stripe-Version`, `Idempotency-Key`, `Content-Type` form, cuerpo codificado con corchetes.
3. `CreatePaymentIntentMapperTest` con fixture.
4. `PaymentsGatewayTest`: 200 → DTO; 402 → `PaymentDeclined`; 500 → excepción genérica de pagos.
5. `StripeLiveKeyGuardTest`: `sk_live_…` → excepción.
6. **Prueba manual** con clave de test: PaymentIntent `succeeded` visible en el Dashboard (captura sin datos sensibles).

---

### D4.3 · Stripe: listado de pagos

**Archivos:** `config/integrations/Stripe.yaml`, `src/Payments/Infrastructure/Integrations/Stripe/ListPaymentIntents/*`, `src/Payments/Application/PaymentsGateway.php`, `src/Payments/Application/PaymentList.php`, `tests/Payments/*`, `tests/Fixtures/stripe/payment_intents.list*.json`.

**Diseño:**
- `path: /v1/payment_intents?limit={limit}` + `PathResolvableContextInterface` para añadir `starting_after` solo si existe.
- `PaymentList`: `list<PaymentIntentDto>`, `hasMore`, `lastId`.
- Solo pagos creados por la demo: filtrar por `metadata.rental_id` presente en el mapper/gateway (la cuenta de test es exclusiva de la demo, pero el filtro evita mostrar pruebas manuales ajenas).

**TDD:** sin cursor; con cursor (URL exacta); `has_more`; filtrado de pagos sin `rental_id`.

---

### D4.4 · Caso de uso "alquilar"

> **Cubierto de otra forma** (decisión de Carlos, 2026-09-22) — ver "Decisiones de alcance". Existe `RentalPaymentGateway::rentMovie()`, pero sin `Rental` persistido ni estados `pending/rented/failed` en base de datos: sin persistencia, no hay nada que transicionar.

**Archivos:** `src/Rental/Application/RentMovie.php`, `src/Rental/UI/RentController.php`, `templates/store/movie.html.twig`, `templates/store/_rent_buttons.html.twig`, `config/packages/rate_limiter.yaml`, `tests/Rental/Application/RentMovieTest.php`, `tests/Rental/UI/RentControllerTest.php`.

**Flujo:**
1. `POST /{_locale}/movies/{id}/rent` con `card=success|declined` y token CSRF `rent_<id>`.
2. `RentMovie`: comprueba que la película existe en el catálogo curado y tiene precio (`PriceCatalog`); crea `Rental(pending)`; llama a `PaymentsGateway::createPayment()`; guarda `paymentIntentId`.
3. `PaymentDeclined` **no** marca el alquiler como fallido: el estado final lo decide el webhook (D4.7). Se responde igualmente 202.
4. Respuesta `202 {"rentalId":"…","statusUrl":"/en/rentals/…/status"}`.
5. Limitador `rent`: 5 alquileres / 10 min por IP.

**TDD:**
1. `RentMovieTest` (con dobles de repositorio y gateway): feliz; película fuera del catálogo → excepción; sin precio → excepción; `PaymentDeclined` → rental sigue `pending` con `paymentIntentId` si existe.
2. `RentControllerTest`: sin CSRF → 403; `card=foo` → 400; feliz → 202 con JSON; 6.º intento → 429.

---

### D4.5 · Recepción de webhooks y registro de entregas

> Se ejecuta **antes** de publicar v4.4.0 (día 58), con `composer.local.json`.

**Archivos:**
```
config/packages/webhook.yaml
config/routes/webhook.yaml
config/integrations/Stripe.yaml                      ← sección webhooks
src/Payments/Infrastructure/Integrations/Stripe/Webhook/
├─ PaymentIntentSucceeded/{PaymentIntentSucceededEvent,PaymentIntentSucceededMapper}.php
└─ PaymentIntentFailed/{PaymentIntentFailedEvent,PaymentIntentFailedMapper}.php
src/Payments/Infrastructure/Webhook/RecordingRequestParser.php
tests/Payments/Infrastructure/Webhook/*
tests/Support/StripeSignature.php                     ← firma payloads en tests
tests/Fixtures/stripe/events/{payment_intent.succeeded,payment_intent.payment_failed}.json
```

**Configuración (ajustar a lo que determine B4.1):**
```yaml
# config/integrations/Stripe.yaml
webhooks:
  type_field: type
  id_field: id
  signature:
    type: timestamped_hmac
    header: Stripe-Signature
    secret: '%env(STRIPE_WEBHOOK_SECRET)%'
    tolerance: 300
  unknown_events: ignore
  events:
    payment_intent.succeeded:
      mapper: App\Payments\Infrastructure\Integrations\Stripe\Webhook\PaymentIntentSucceeded\PaymentIntentSucceededMapper
    payment_intent.payment_failed:
      mapper: App\Payments\Infrastructure\Integrations\Stripe\Webhook\PaymentIntentFailed\PaymentIntentFailedMapper
```
```yaml
# config/packages/webhook.yaml
framework:
  webhook:
    routing:
      stripe:
        service: App\Payments\Infrastructure\Webhook\RecordingRequestParser
        secret: '%env(STRIPE_WEBHOOK_SECRET)%'
```

**`RecordingRequestParser implements RequestParserInterface`** (decorador del parser del bundle `integration_engine.webhook_parser.stripe`):
1. Guarda `WebhookDelivery(received)` con cabecera de firma y payload crudo **antes** de delegar.
2. Delegación exitosa → actualiza `eventId`, `eventType`, `mappedEvent` (serializado a JSON legible), `signatureStatus: valid`, `status: queued` (o `ignored` si el bundle devuelve evento ignorado) y devuelve el `RemoteEvent`.
3. `RejectWebhookException` → lee el motivo del `previous` (B4.8), guarda `status: rejected`, `rejectionReason`, `signatureStatus` derivado, y **relanza**.
4. Añade al `RemoteEvent` el id de la entrega para que el consumidor la actualice (vía un `DeliveryContext` en `RequestStack` o metadato del evento, según permita el diseño de B4.6; decisión documentada en el PR).

**Eventos tipados (`final readonly`):**
- `PaymentIntentSucceededEvent`: `eventId`, `paymentIntentId`, `rentalId` (de `data.object.metadata.rental_id`), `amountCents`, `currency`, `occurredAt`.
- `PaymentIntentFailedEvent`: igual + `failureCode` (`?string`), `failureMessage` (`?string`, truncado).

**TDD:**
1. `StripeSignature::sign(string $payload, string $secret, int $timestamp, array $extraV1 = []): string` con test propio contra un vector conocido.
2. Mappers con fixtures.
3. `RecordingRequestParserTest` (`WebTestCase` sobre `POST /webhook/stripe`):
   - firma válida → 2xx según B4.1, entrega `queued` con evento mapeado;
   - firma inválida → rechazo, entrega `rejected` / `signature_invalid`;
   - sin cabecera → `rejected` / `header_missing`;
   - timestamp caducado (reloj fijado) → `rejected` / `timestamp_out_of_tolerance`;
   - tipo desconocido → entrega `ignored`;
   - el payload guardado es byte a byte el recibido.

---

### D4.6 · Messenger y RabbitMQ

> **Descartado** (decisión de Carlos, 2026-09-22) — ver "Decisiones de alcance". El consumer actual solo registra el evento en el log; no hay `Rental` que reconciliar, así que no hace falta cola ni worker.

**Archivos:** `config/packages/messenger.yaml`, `config/packages/test/messenger.yaml`, `compose.yaml`, `compose.prod.yaml`, `Dockerfile` (extensión `amqp`), `.env` (`MESSENGER_TRANSPORT_DSN`), `tests/Payments/Infrastructure/Messaging/WebhookRoutingTest.php`.

**Dependencias:** `symfony/messenger`, `symfony/amqp-messenger`.

**Configuración:**
```yaml
framework:
  messenger:
    failure_transport: failed
    transports:
      async:
        dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
        retry_strategy: { max_retries: 3, delay: 1000, multiplier: 2 }
      failed: 'doctrine://default?queue_name=failed'
    routing:
      Symfony\Component\RemoteEvent\Messenger\ConsumeRemoteEventMessage: async
```
`test/messenger.yaml`: `async: 'in-memory://'`.

**Compose:**
```yaml
rabbitmq:
  image: rabbitmq:4-management-alpine          # verificar tag vigente
  restart: unless-stopped
  environment:
    RABBITMQ_DEFAULT_USER: ${RABBITMQ_USER:-demo}
    RABBITMQ_DEFAULT_PASS: ${RABBITMQ_PASS:-demo}
  healthcheck:
    test: ["CMD", "rabbitmq-diagnostics", "-q", "check_running"]
    interval: 10s
    retries: 10
  volumes: [rabbitmq_data:/var/lib/rabbitmq]
  # sin ports publicados; en dev, override con 15672 solo en 127.0.0.1
worker:
  image: (misma que php)
  command: ["php", "bin/console", "messenger:consume", "async", "--time-limit=3600", "--memory-limit=128M", "-vv"]
  restart: unless-stopped
  depends_on: { rabbitmq: { condition: service_healthy } }
```

**TDD:**
1. `WebhookRoutingTest`: tras un `POST /webhook/stripe` válido, `in-memory` `async` contiene exactamente un `ConsumeRemoteEventMessage` con el `MappedRemoteEvent` (id y nombre correctos).
2. Firma inválida → 0 mensajes.
3. `#[Group('integration')]`: con RabbitMQ real en Docker, el mensaje llega a la cola (`messenger:stats` o consumo con `--limit=1`).

---

### D4.7 · Consumer idempotente

> **Descartado** (decisión de Carlos, 2026-09-22) — ver "Decisiones de alcance". Depende de entidades persistidas que no existen.

**Archivos:** `src/Payments/Application/StripeRemoteEventConsumer.php`, `src/Payments/Application/HandlePaymentSucceeded.php`, `src/Payments/Application/HandlePaymentFailed.php`, `tests/Payments/Application/*`.

**Diseño:**
```php
#[AsRemoteEventConsumer('stripe')]
final readonly class StripeRemoteEventConsumer implements ConsumerInterface
{
    public function consume(RemoteEvent $event): void
    {
        // 1. $event instanceof MappedRemoteEvent, si no → log y return
        // 2. ProcessedEventRepository::markIfNew($event->getId()) === false → delivery "duplicate", return
        // 3. match ($event->event()::class) → HandlePaymentSucceeded | HandlePaymentFailed
        // 4. delivery "processed", processedAt
    }
}
```
- Todo dentro de una transacción (Doctrine `wrapInTransaction`): marcar evento procesado + transición del alquiler + actualización de la entrega.
- `HandlePaymentSucceeded`: `Rental::markRented()`; si ya está `failed` → no cambia y registra aviso (evento tardío).
- `HandlePaymentFailed`: `Rental::markFailed()` solo desde `pending`; si ya está `rented` → ignora (orden desordenado).
- Alquiler no encontrado (`rental_id` ausente o desconocido) → entrega `processed` con nota `unknown_rental`, sin excepción (no reintentar para siempre).

**TDD:**
1. `succeeded` → `rented`, entrega `processed`.
2. `payment_failed` → `failed`.
3. Mismo `eventId` dos veces → una transición; segunda entrega `duplicate`.
4. `payment_failed` después de `rented` → sigue `rented`.
5. `succeeded` después de `failed` → sigue `failed` + aviso en log.
6. `rental_id` desconocido → sin excepción.
7. Excepción en mitad de la transacción → nada persistido (`markIfNew` también revertido).

---

### D4.8 · Estado del alquiler en vivo

> **Descartado** (decisión de Carlos, 2026-09-22) — ver "Decisiones de alcance". No hay `Rental` persistido cuyo estado consultar.

**Archivos:** `src/Rental/UI/RentalStatusController.php`, `assets/rental-status.js`, `templates/store/movie.html.twig`, `tests/Rental/UI/RentalStatusControllerTest.php`.

**Diseño:**
- `GET /{_locale}/rentals/{id}/status` → `{"status":"pending|rented|failed","updatedAt":"…"}`; `Cache-Control: no-store`; id inválido o inexistente → 404.
- `rental-status.js`: tras el 202 de alquilar, sondea cada 1 s hasta estado final o 30 s; muestra *Processing payment…* → *Rented — enjoy!* / *Payment declined*; tras 30 s, *Still waiting for Stripe's confirmation — check the webhook inbox* con enlace al panel.

**TDD:** tres estados; UUID mal formado → 404; id inexistente → 404; cabecera `no-store`.

---

### D4.9 · Panel: pagos

> **Descartado** (decisión de Carlos, 2026-09-22) — ver "Decisiones de alcance". Un panel con dos o tres pagos de prueba solo mostraría estadísticas vacías.

**Archivos:** `src/Payments/UI/PanelController.php`, `templates/panel/_layout.html.twig`, `templates/panel/payments.html.twig`, `translations/messages.{en,es}.yaml`, `config/packages/cache.yaml`, `tests/Payments/UI/PanelControllerTest.php`.

**Diseño:**
- Ruta `/{_locale}/panel/payments`, pestañas del panel: *Payments*, *Webhooks*, (fase 5) *Engine events*.
- Tabla: película (título desde catálogo), importe, estado de Stripe, estado del alquiler local, fecha, enlace a la entrega de webhook relacionada.
- Caché de la lista de Stripe 10 s (`cache.app` con clave por página).
- Badge *Stripe test mode — no real money moves* siempre visible.
- Error de Stripe → mensaje amable y enlace a reintentar; nunca detalle técnico.

**TDD:** lista con datos; vacío; error; badge presente; paginación con `starting_after`.

---

### D4.10 · Panel: bandeja de webhooks

> **Descartado** (decisión de Carlos, 2026-09-22) — ver "Decisiones de alcance". Depende de persistencia de entregas, descartada.

**Archivos:** `src/Payments/UI/WebhookInboxController.php`, `templates/panel/webhooks.html.twig`, `templates/panel/webhook_detail.html.twig`, `tests/Payments/UI/WebhookInboxControllerTest.php`.

**Diseño:**
- Lista (últimas 50): hora, origen (`stripe`/`replay`/`simulated`), tipo, id de evento, firma, estado, motivo.
- Detalle:
  1. Cabecera `Stripe-Signature` recibida (texto plano, escapado).
  2. Payload crudo (JSON formateado **para mostrar**, con nota "stored byte-for-byte; formatted here for readability").
  3. Evento tipado producido por el mapper (JSON) con título *"What your application sees"*.
  4. Línea de tiempo: recibido → verificado/rechazado → encolado → procesado/duplicado.
  5. Botones de D4.11.
- Autoescape de Twig en todo; ningún `|raw` sobre datos de la entrega.

**TDD:**
1. Lista ordenada por fecha descendente y limitada a 50.
2. Detalle de entrega aceptada y rechazada.
3. **XSS:** entrega con `metadata.movie_title = "<script>alert(1)</script>"` → la respuesta contiene `&lt;script&gt;` y no `<script>alert(1)`.
4. Id inexistente → 404.

---

### D4.11 · Herramientas de reenvío y simulación

> **Descartado** (decisión de Carlos, 2026-09-22) — ver "Decisiones de alcance". Depende de la bandeja de webhooks (D4.10), descartada.

**Archivos:** `src/Payments/Application/WebhookReplayer.php`, `src/Payments/Application/WebhookSimulator.php`, `src/Payments/UI/WebhookToolsController.php`, `templates/panel/_webhook_tools.html.twig`, `config/packages/rate_limiter.yaml`, `tests/Payments/Application/*`, `tests/Payments/UI/WebhookToolsControllerTest.php`.

**Herramientas (todas `POST`, CSRF, limitador `webhook_tools`: 10 / 10 min por IP):**

| Herramienta | Qué hace | Resultado esperado | Etiqueta |
|---|---|---|---|
| **Replay original** | Reenvía a `http://php/webhook/stripe` (URL interna, env `INTERNAL_BASE_URL`) el payload y la cabecera `Stripe-Signature` **exactos** guardados | < 300 s desde el `t` original → aceptado y `duplicate`; > 300 s → `rejected: timestamp_out_of_tolerance` | *Real Stripe signature* |
| **Simulate expired timestamp** | Firma el payload guardado con `t = now - 600` y el secreto real | `rejected: timestamp_out_of_tolerance` | *Simulated* |
| **Simulate invalid signature** | Cabecera con `t` actual y `v1` aleatorio | `rejected: signature_invalid` | *Simulated* |
| **Simulate missing header** | Sin `Stripe-Signature` | `rejected: header_missing` | *Simulated* |
| **Simulate secret rotation** | Cabecera con dos `v1`: una aleatoria y una válida | aceptado (y `duplicate` si el evento ya se procesó) | *Simulated* |

- Solo sobre entregas existentes con `source: stripe`.
- Las entregas generadas quedan con `source: replay` o `simulated`.
- La UI muestra un texto de una frase explicando **qué protección** se está viendo.

**TDD (reloj inyectable en el replayer y en el verificador del bundle en entorno test):**
1. Replay < 300 s → nueva entrega `duplicate`.
2. Replay > 300 s → `rejected` con motivo.
3. Cada simulación → motivo esperado.
4. Rotación → aceptada.
5. Entrega `source: simulated` no puede usarse como base → 400.
6. 11.ª petición → 429.
7. El replayer nunca envía a una URL distinta de `INTERNAL_BASE_URL` (test que falla si se intenta cambiar por parámetro).

---

### D4.12 · Reinicio nocturno

> **Descartado** (decisión de Carlos, 2026-09-22) — ver "Decisiones de alcance". No hay base de datos que reiniciar.

**Archivos:** `src/Shared/Application/ResetDemo.php`, `src/Shared/UI/Console/ResetDemoCommand.php`, `src/Shared/Scheduler/DemoSchedule.php`, `src/Shared/Scheduler/ResetDemoMessage.php` (+ handler), `compose.yaml` (servicio `scheduler`), `tests/Shared/*`.

**Dependencias:** `symfony/scheduler`.

**Diseño:**
- `app:demo:reset`: vacía `rental`, `webhook_delivery`, `processed_event`, cola `failed`; limpia la caché de listas de Stripe; **no** toca Stripe (los PaymentIntents de test quedan en la cuenta).
- `#[AsSchedule('default')] DemoSchedule`: `RecurringMessage::cron('0 4 * * *', new ResetDemoMessage())` (hora UTC).
- Servicio `scheduler` en Compose: `messenger:consume scheduler_default --time-limit=3600`.

**TDD:**
1. `ResetDemoTest`: con datos → tablas vacías; esquema intacto.
2. `ResetDemoCommandTest`: salida y código 0; `--dry-run` muestra recuentos sin borrar.
3. `DemoScheduleTest`: el schedule contiene un mensaje recurrente con la expresión esperada.

---

### D4.13 · Pasos 5 y 6 del tour

**Archivos:** `config/tour.yaml`, `translations/tour.{en,es}.yaml`, `src/Payments/UI/TourRunController.php`, marcadores en `config/integrations/Stripe.yaml`, `config/packages/{webhook,messenger}.yaml`, `src/Payments/`, `tests/Payments/UI/*`.

**Paso 5 · Renting a movie**
- Snippets: `CreatePaymentIntent` en YAML, `CreatePaymentIntentBody` (form-encoded), `PaymentsGateway::createPayment()` (con `Idempotency-Key`).
- Run: *Rent with a card that works* / *Rent with a declined card* → resultado, traza (una llamada a Stripe) y enlace a la ficha para ver el estado en vivo.
- Nota: "Stripe's API only accepts form-encoded bodies — that's why the bundle supports them natively (v4.2)".

**Paso 6 · Payment confirmation**
- Diagrama (HTML/SVG): *Stripe → bundle (verify · map · RemoteEvent) │ app (Messenger → RabbitMQ → consumer → rental status)* con la frontera marcada.
- Snippets: sección `webhooks` del YAML, un mapper, el consumer (deduplicación).
- Enlaces: bandeja de webhooks, detalle de la última entrega, herramientas de D4.11.
- Enlaces a ADR 0011 y 0012 del bundle.

**TDD:** `TourSnippetsResolveTest`; runs del paso 5 con `MockHttpClient`; paridad de traducciones; el paso 6 renderiza enlaces a entregas existentes o un estado vacío con instrucción ("rent a movie first").

---

### D4.14 · Stripe real en local

**Archivos:** `compose.yaml` (perfil `stripe-local`), `docs/STRIPE-LOCAL.md`, `Makefile`.

```yaml
stripe-cli:
  profiles: [stripe-local]
  image: stripe/stripe-cli
  command: ["listen", "--forward-to", "http://php/webhook/stripe", "--events", "payment_intent.succeeded,payment_intent.payment_failed"]
  environment:
    STRIPE_API_KEY: ${STRIPE_SECRET_KEY}
```

**`docs/STRIPE-LOCAL.md`:**
1. Crear cuenta de Stripe y activar modo test.
2. Copiar `sk_test_…` a `.env.local`.
3. `make stripe-local` (= `docker compose --profile stripe-local up -d`), copiar el `whsec_…` que imprime `stripe listen` (`docker compose logs stripe-cli`) a `STRIPE_WEBHOOK_SECRET` y reiniciar `php` y `worker`.
4. Alquilar desde la tienda o `docker compose run --rm stripe-cli trigger payment_intent.succeeded`.
5. Ver la entrega en el panel.

**Verificación:** ejecución completa documentada con capturas; el signing secret de `stripe listen` es estable entre reinicios (verificarlo y anotarlo).

---

### D4.15 · Despliegue de la fase 4

> **Parcialmente descartado** (decisión de Carlos, 2026-09-22) — ver "Decisiones de alcance". Nada de RabbitMQ/worker/scheduler que desplegar; solo aplica lo relativo a Stripe.

**Pasos:**
1. `compose.prod.yaml`: servicios `rabbitmq`, `worker`, `scheduler` con la imagen de producción; volúmenes `app_data` (SQLite) y `rabbitmq_data`; límites de memoria (`mem_limit`) orientativos: `rabbitmq` 512 MB, `worker` 256 MB, `scheduler` 128 MB.
2. `deploy.yml`: tras `up -d --wait`, `docker compose exec -T php bin/console doctrine:migrations:migrate --no-interaction` y reinicio de `worker` (`messenger:stop-workers`).
3. `.env.prod.local`: `STRIPE_SECRET_KEY` (test), `STRIPE_API_VERSION`, `STRIPE_WEBHOOK_SECRET`, `MESSENGER_TRANSPORT_DSN=amqp://…@rabbitmq:5672/%2f/messages`, `RABBITMQ_USER`, `RABBITMQ_PASS` (aleatorios), `INTERNAL_BASE_URL=http://php`.
4. **Dashboard de Stripe (modo test):** endpoint `https://demo.integrationengine.dev/webhook/stripe`, eventos `payment_intent.succeeded` y `payment_intent.payment_failed`, **versión de API igual a `STRIPE_API_VERSION`**. Copiar el signing secret.
5. Prueba de humo real: alquilar con tarjeta que funciona y con tarjeta rechazada.

**Verificación:**
- [ ] Alquiler con éxito → `rented` en ≤ 10 s; entrega `processed` en la bandeja.
- [ ] Alquiler rechazado → `failed`; entrega `processed` de `payment_intent.payment_failed`.
- [ ] Reenvío desde el Dashboard de Stripe del mismo evento → entrega `duplicate`.
- [ ] `docker stats --no-stream` tras 24 h anotado en `docs/ops/RUNBOOK.md`; memoria total usada ≤ 70 % de la RAM.
- [ ] Tras reiniciar el VPS, todo vuelve a `healthy` sin intervención.

---

### D4.16 · Revisión de seguridad de la demo pública

**Checklist (cada punto con evidencia):**
- [ ] `STRIPE_SECRET_KEY` es de test (guardarraíl D4.2 activo en prod).
- [ ] Ningún secreto en el repo: `git log -p | grep -E "sk_(test|live)_|whsec_|eyJhbGciOi"` sin resultados (el token de TMDB es un JWT).
- [ ] Logs de prod sin cabeceras `Authorization`, `Stripe-Signature` completas ni payloads de webhooks.
- [ ] RabbitMQ sin puertos publicados; credenciales no por defecto.
- [ ] `supplier` sin puertos publicados.
- [ ] Todos los `POST` con CSRF y limitador (tour, alquilar, herramientas de webhooks).
- [ ] El endpoint de webhooks no tiene CSRF (correcto) y solo acepta `POST` JSON.
- [ ] Cabeceras de seguridad presentes en prod (`curl -sI`).
- [ ] Páginas de error sin información técnica (forzar 404, 405, 429, 500).
- [ ] Payloads de webhooks mostrados escapados (test XSS verde).
- [ ] El replayer solo apunta a `INTERNAL_BASE_URL`.
- [ ] SSH solo con clave; firewall correcto; actualizaciones automáticas activas.
- [ ] Copia de `.env.prod.local` guardada fuera del servidor en un gestor de contraseñas.
- [ ] Dependencias sin vulnerabilidades conocidas: `composer audit` en CI (añadir si no está).

---

### D4.17 · Revisión en frío y demo v2.0

Repetir la checklist de D2.21 añadiendo:
- [ ] Recruiter: desde la ficha de una película, alquilar y ver *Rented* sin explicación previa.
- [ ] Tech lead: paso 6 → bandeja → detalle de entrega → herramientas de D4.11 → ADR 0012 → consumer.
- [ ] Badge de modo test visible en ficha, panel y pasos 5-6.
- [ ] Tag `v2.0.0`, `CHANGELOG.md`, `ROADMAP.md` del bundle actualizado.

---

## FASE 5 · Calidad de diseño visible

### D5.1 · Extensión PHPStan aplicada a la demo

**Pasos:**
1. `composer.local.json` con el commit del bundle que contiene B5.2-B5.5; `composer require --dev phpstan/extension-installer` (la extensión se carga sola).
2. `vendor/bin/phpstan analyse` → corregir todas las violaciones de las reglas nuevas (sin `ignoreErrors`).
3. Si la inferencia salió adelante: eliminar los `\assert($response instanceof …)` de todos los facades y comprobar que PHPStan max sigue verde.
4. Anotar en el PR del bundle (release v4.5.0) el enlace a este PR como evidencia.

**Verificación:** `make ci` verde; `grep -rn 'assert($response instanceof' src/` → 0 (si "go").

---

### D5.2 · Paso 7 del tour: "Partner stores"

**Archivos:** `config/packages/partners.yaml`, `config/packages/integration_engine.yaml`, `src/Partners/Domain/{PartnerStore,PartnerStoreId}.php`, `src/Partners/Application/PartnerCatalog.php`, `src/Partners/Infrastructure/PartnerConnectionResolver.php`, `src/Partners/UI/TourRunController.php`, `config/tour.yaml`, `translations/tour.{en,es}.yaml`, `tests/Partners/*`.

**Diseño:**
- Tres tiendas asociadas en configuración:
  1. `downtown` → `http://supplier` (permitida).
  2. `rogue` → `https://evil.example` (host no permitido).
  3. `metadata` → `http://169.254.169.254` (IP de metadatos de nube).
- Integración `partners` con `connection_resolver: App\Partners\Infrastructure\PartnerConnectionResolver`, `allowed_hosts: ['supplier']`, `block_private_networks: true`.
  - **Atención:** `supplier` resuelve a una IP privada de la red de Docker. Verificar cómo interactúan `allowed_hosts` y `block_private_networks` con un host interno legítimo; si el bloqueo impide la tienda válida, usar solo `allowed_hosts` en esta integración y demostrar `block_private_networks` con la integración `tmdb` (host público), documentando la decisión en el paso.
- Run por tienda: la válida devuelve stock; las otras dos muestran `DisallowedHostException` (o bloqueo de red privada) con explicación de una frase.

**TDD:**
1. `PartnerConnectionResolverTest`: credenciales por tienda.
2. `PartnerCatalogTest` con `MockHttpClient`: `rogue` y `metadata` → excepción **y** `getRequestsCount() === 0`; `downtown` → petición enviada.
3. `TourRunControllerTest` de los tres runs.
4. **Verificación en producción:** los logs del VPS no muestran conexiones salientes a `169.254.169.254` (`docker compose logs php | grep 169.254` solo muestra el rechazo registrado por la app).

---

### D5.3 · Panel "Engine events"

> **Descartado** (decisión de Carlos, 2026-09-22) — ver "Decisiones de alcance". Mismo motivo que D4.9/D4.10: sin datos reales, un panel así solo mostraría estadísticas vacías.

**Archivos:** `src/Shared/Observability/EngineEventListener.php`, `src/Shared/Observability/CallTrace.php`, `src/Shared/Observability/RecentEngineEvents.php`, `src/Payments/UI/PanelController.php`, `templates/panel/events.html.twig`, `config/packages/integration_engine.yaml`, `tests/Shared/Observability/*`.

**Pasos:**
1. `EngineEventListener` escucha `RequestSent`, `ResponseMapped`, `RequestFailed`, `TokenRefreshed`, `WebhookReceived`, `WebhookRejected` y alimenta `CallTrace` (traza del tour) y `RecentEngineEvents` (últimos 100 en caché compartida con TTL 1 h).
2. **Decisión:** mantener `TraceRecorderMiddleware` como ejemplo de middleware en el paso 3 **pero** dejar de usarlo como fuente de la traza del tour (la traza pasa a eventos). Documentar en el paso 3 y en un comentario de la clase.
3. Pestaña *Engine events* del panel con tabla (hora, evento, integración, acción/tipo, duración, resultado).
4. En la traza del paso 4, los reintentos ya no dependen de `X-Attempt` si los eventos del bundle los exponen; si no los exponen (los reintentos ocurren dentro de `RetryableHttpClient`), mantener `X-Attempt` y anotarlo.

**TDD:**
1. `EngineEventListenerTest`: cada evento → entrada correcta en `CallTrace` y `RecentEngineEvents`.
2. `RecentEngineEventsTest`: límite de 100 y orden.
3. `PanelControllerTest`: pestaña renderiza eventos y estado vacío.
4. Test de que ninguna entrada visible contiene el token de TMDB, la clave de Stripe ni el secreto de webhooks (reutilizar la idea de B5.9).

---

### D5.4 · Revisión final y demo v3.0

**Checklist final:**
- [ ] Los 7 pasos del tour funcionan en producción en EN y ES.
- [ ] Recruiter (30 s): landing → Live demo → paso 1 → tienda → alquiler con éxito.
- [ ] Tech lead (10 min): README del bundle → "Reviewing this project? Start here" → paso 1 → paso 4 → paso 6 → bandeja de webhooks → ADRs 0011/0012 → Deptrac y extensión de PHPStan → CI de ambos repos.
- [ ] Una persona ajena al proyecto hace el recorrido sin ayuda; anotar dónde se atasca y corregir.
- [ ] Lighthouse: Accessibility ≥ 90 en portada, paso 1, paso 6 y panel.
- [ ] `docs/ops/RUNBOOK.md` al día (memoria, costes reales del VPS, rotación de secretos).
- [ ] `make ci` verde, despliegue verde, monitor externo verde 7 días seguidos.
- [ ] Tag `v3.0.0` y `CHANGELOG.md`.
