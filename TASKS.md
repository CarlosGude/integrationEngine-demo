# Backlog Técnico — IntegrationEngine Demo

> **Generado:** 2026-09-21
> **Commit analizado:** `5fc7e94` (rama `main`, árbol limpio, sincronizada con `origin/main`)
> **Método:** ejecución real de la suite de QA (`phpunit`, `php-cs-fixer`, `phpstan`, `deptrac`, `lint:container`, `composer validate`, `docker compose config`) + inspección de configuración, Dockerfile y documentación.
>
> **Segunda pasada (revisión):** se re-verificaron las afirmaciones de la primera versión ejecutando `deptrac --report-uncovered`, `composer why doctrine/orm`, `debug:firewall main`, `debug:container integration_engine.config.countries` y una sonda funcional sobre `/admin`. Resultado: **T-09**, **T-10**, **T-11**, **T-18** y varios números de línea corregidos; **T-22** y **T-23** añadidas. **T-20** y **T-21** se verificaron y reproducen exactamente.
>
> **Addendum 2026-09-21 — T-20 y T-21:** añadidas tras revisar el estado del bundle (`carlosgude/integration-engine` v7.0.1). Su evidencia sale de `debug:container` sobre este repo y de la lectura del código de v7.0.1 en `vendor/`, no de la pasada de QA original.

---

## 1. Resumen ejecutivo

### 1.1 Estado de las puertas de calidad

| Puerta | Comando | Resultado |
|---|---|---|
| Tests | `make test` | ✅ 55 tests, 182 asserts — 2 skipped, 2 deprecations |
| Estilo | `make cs` | ✅ 0 de 82 ficheros a corregir |
| Análisis estático | `make stan` | ✅ 0 errores (PHPStan level `max`, **con baseline de 27 supresiones**) |
| Arquitectura | `make deptrac` | ✅ 0 violaciones — pero 4 namespaces propios sin capa asignada |
| Contenedor DI | `bin/console lint:container` | ✅ OK |
| Composer | `composer validate --strict` | ✅ válido |
| **Docker Compose** | `docker compose config` | ❌ **YAML inválido — no arranca** |

**Lectura:** el CI está verde, pero está verde en parte porque no mira donde están los problemas. Las puertas cubren `src/` y `tests/`; no cubren el arranque de `docker compose`, ni una instalación de producción real (`--no-dev`), ni la protección de rutas.

### 1.2 Inventario de tareas

| Prioridad | Nº | Descripción |
|---|---|---|
| **P0 — Bloqueante** | 3 | Rompen el arranque (local o producción) o dejan endpoints de escritura sin autenticación |
| **P1 — Alta** | 8 | Fragilidad de build, deprecations en código propio, huecos de test, deuda de migración a v7.0, `/admin` que no responde |
| **P2 — Media** | 6 | Deuda acumulada: baseline, capas, dependencias, CI |
| **P3 — Baja** | 6 | Documentación desincronizada, higiene de release y despliegue |
| **Total** | **23** | |

### 1.3 Convenciones de este documento

- **Severidad:** `P0` bloqueante · `P1` alta · `P2` media · `P3` baja.
- **Esfuerzo:** `S` ≤ 1 h · `M` 1–4 h · `L` > 4 h.
- **Evidencia:** salida real de comandos ejecutados sobre el commit `5fc7e94`.
- Cada tarea lleva **criterios de aceptación** verificables con un comando.

---

## 2. P0 — Bloqueantes

### T-01 · `compose.yaml` es YAML inválido: `make up` falla

| | |
|---|---|
| **Severidad** | P0 |
| **Esfuerzo** | S (15 min) |
| **Ficheros** | `compose.yaml:28`, `compose.yaml:31-52`, `compose.yaml:54-58` |

**Evidencia**

```console
$ docker compose config
yaml: while parsing a block mapping at <unknown position>:
line 30, column 3: did not find expected key

$ python3 -c "import yaml; yaml.safe_load(open('compose.yaml'))"
PARSE ERROR: expected <block end>, but found '<block mapping start>'
  in "compose.yaml", line 31, column 3
```

**Causa**

La receta de Flex de `symfony/mercure-bundle` insertó su bloque al final del fichero, pero para entonces ya se había cerrado el mapa `services:` con un `volumes: {}` de nivel superior en la línea 28. El resultado:

```yaml
28  volumes: {}          # <- cierra services, clave top-level
29
30  ###> symfony/mercure-bundle ###
31    mercure:           # <- indentado 2 espacios: no pertenece a nada
...
54  volumes:             # <- segunda clave "volumes" duplicada
```

Hay por tanto **dos errores combinados**: un servicio huérfano fuera de `services:` y una clave `volumes:` duplicada a nivel raíz.

**Impacto**

- `make up`, `docker compose up`, `docker compose config` y `docker compose exec` fallan todos de inmediato.
- El servicio `mercure` nunca se levanta, así que **toda la funcionalidad de tiempo real (v1.1.0) es inejecutable** por la vía documentada en el README.
- No lo detecta el CI: el job `build-image` usa `docker/build-push-action` contra el `Dockerfile`, no pasa por Compose.

**Solución propuesta**

Mover el servicio `mercure` dentro de `services:` y fusionar las dos claves `volumes:` en una sola al final:

```yaml
services:
  php:
    # ... sin cambios ...

###> symfony/mercure-bundle ###
  mercure:
    image: dunglas/mercure
    restart: unless-stopped
    environment:
      MERCURE_PUBLISHER_JWT_KEY: ${MERCURE_JWT_SECRET:-!ChangeThisMercureHubJWTSecretKey!}
      MERCURE_SUBSCRIBER_JWT_KEY: ${MERCURE_JWT_SECRET:-!ChangeThisMercureHubJWTSecretKey!}
      MERCURE_EXTRA_DIRECTIVES: |
        cors_origins http://127.0.0.1:8000 http://localhost:8080
    command: /usr/bin/caddy run --config /etc/caddy/dev.Caddyfile
    healthcheck:
      test: ["CMD", "curl", "-f", "https://localhost/healthz"]
      timeout: 5s
      retries: 5
      start_period: 60s
    volumes:
      - mercure_data:/data
      - mercure_config:/config
###< symfony/mercure-bundle ###

volumes:
###> symfony/mercure-bundle ###
  mercure_data:
  mercure_config:
###< symfony/mercure-bundle ###
```

Revisar también `compose.override.yaml`, que declara `mercure.ports: ["80"]` y hereda el mismo problema estructural (ver **T-18**).

**Criterios de aceptación**

- [ ] `docker compose config` imprime la configuración resuelta sin errores.
- [ ] `docker compose config --services` lista `php` y `mercure`.
- [ ] `make up` levanta ambos contenedores y el healthcheck pasa.
- [ ] Se añade al CI un paso `docker compose config -q` que falle si el YAML se rompe otra vez (ver **T-14**).

---

### T-02 · EasyAdmin está en `require-dev` pero el bundle se carga en `all`: producción no arranca

| | |
|---|---|
| **Severidad** | P0 |
| **Esfuerzo** | S (20 min) |
| **Ficheros** | `composer.json:36` (`require-dev`), `config/bundles.php:11`, `src/Controller/Admin/DashboardController.php` |

**Evidencia**

```console
$ grep -n "easyadmin" composer.json
38:        "easycorp/easyadmin-bundle": "^4.29",     # bloque require-dev

$ sed -n '11p' config/bundles.php
    EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle::class => ['all' => true],
```

`src/Controller/Admin/DashboardController.php` extiende `AbstractDashboardController` y usa los atributos `#[AdminDashboard]`, `Dashboard` y `MenuItem` del bundle — todas ellas clases que sólo existen si se instalan las dev-dependencies.

**Impacto**

Una instalación de producción estándar:

```console
$ composer install --no-dev --optimize-autoloader
```

deja el proyecto **sin arrancar**: `config/bundles.php` referencia `EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle::class`, que ya no existe → `Error: Class not found` en el boot del Kernel, antes de servir ninguna petición. Lo mismo ocurre con `src/Controller/Admin/DashboardController.php`, que se escanea al compilar el contenedor.

Por qué nadie lo ha visto todavía:

- El `Dockerfile:18` ejecuta `composer install` **con** dev-dependencies, así que la imagen funciona (a costa de meter PHPUnit, PHPStan e Infection en la imagen de producción — ver **T-04**).
- El CI tampoco lo ejecuta jamás con `--no-dev`.

> ⚠️ **Antes de elegir, leer T-11.** EasyAdmin arrastra `doctrine/orm` como dependencia propia (`composer why doctrine/orm`), así que conservar el panel implica conservar Doctrine. Las dos tareas son la misma decisión vista desde dos lados. Y el panel **hoy no responde** (**T-22**), lo que debilita el argumento de que sea "una función del producto".

**Solución propuesta** — elegir una de las dos:

**Opción A: EasyAdmin es una función del producto → moverlo a `require`.**

```console
composer remove --dev easycorp/easyadmin-bundle
composer require easycorp/easyadmin-bundle:^4.29
```

El panel `/admin` forma parte de lo que documenta v1.1.0, así que pertenece a las dependencias de runtime. Requiere resolver antes **T-03** (quedaría accesible en producción) y **T-22** (no responde).

**Opción B: el panel es sólo una demo local → restringirlo a dev/test.**

```php
// config/bundles.php
EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle::class => ['dev' => true, 'test' => true],
```

…y mover `src/Controller/Admin/` fuera del escaneo de producción, o protegerlo con `class_exists()`. Más frágil; sólo tiene sentido si el admin no se despliega.

**Criterios de aceptación**

- [ ] `composer install --no-dev` seguido de `APP_ENV=prod bin/console cache:warmup` termina sin errores.
- [ ] Se añade un job al CI que haga exactamente eso (ver **T-14**), para que la regresión no vuelva.
- [ ] `composer validate --strict` sigue pasando.

---

### T-03 · `/admin` está accesible sin autenticación ✅ ARREGLADO

| | |
|---|---|
| **Severidad** | P0 |
| **Estado** | ✅ Arreglado — `http_basic` + `access_control`, cubierto por `tests/Security/AdminAccessControlTest.php` |
| **Esfuerzo** | M (2–3 h) |
| **Ficheros** | `config/packages/security.yaml:15-28`, `src/Controller/Admin/DashboardController.php:12` |

> **Resuelto.** Medido antes y después sobre el kernel real, con caché limpia:
>
> | Ruta | Antes | Ahora |
> |---|---|---|
> | `GET /admin` anónimo | **200** | **401** |
> | `POST /api/mercure/publish` anónimo | **400** (procesaba la petición) | **401** |
> | `POST /api/mercure/transactions` anónimo | **400** | **401** |
> | `POST /api/mercure/webhook` anónimo | **400** | **401** |
> | `POST /webhook/stripe` anónimo | 406 | 406 (sin cambio, correcto) |
> | `GET /` y el tour | 302 / 200 | 302 / 200 (sin cambio) |
>
> El receptor de webhooks entrantes (`/webhook/{type}`, componente Webhook de
> Symfony) queda explícitamente en `PUBLIC_ACCESS`: se autentica por firma HMAC,
> no por credenciales, así que exigirle login lo rompería.
>
> El usuario es `admin`, fijo en el YAML — Symfony no resuelve placeholders de
> entorno en **claves** de configuración, así que la propuesta original de usar
> `'%env(ADMIN_USER)%'` como clave no habría funcionado. Solo el hash es
> configurable, vía `ADMIN_PASSWORD_HASH`. Si falta la variable la app no
> arranca, en vez de caer a un valor por defecto: una credencial debe fallar en
> cerrado.
>
> Pendiente aparte: `docs/ADMIN-FEATURES.md` sigue documentando un
> `src/Security/AdminVoter.php` y una jerarquía de roles que no existen — eso es
> **T-15**, no esto.

**Evidencia**

```console
$ bin/console debug:router | grep admin
  admin                       ANY      /admin
  app_admin_dashboard_index   ANY      /admin

$ bin/console debug:firewall main
  Entry Point            (vacío)
  Access Denied Handler  (vacío)

Authenticators for firewall "main"
==================================
 No authenticators have been registered for this firewall.
```

```yaml
# config/packages/security.yaml
15        main:
16            lazy: true
17            provider: users_in_memory       # <- proveedor vacío, sin autenticador
...
26     access_control:
27         # - { path: ^/admin, roles: ROLE_ADMIN }     # <- comentado
28         # - { path: ^/profile, roles: ROLE_USER }
```

El firewall `main` no declara ningún autenticador (`form_login`, `http_basic`, …), el proveedor `users_in_memory` no tiene usuarios (`memory: null`) y **todas** las reglas de `access_control` están comentadas. El resultado es un firewall que deja pasar todo de forma anónima.

**Impacto**

- **Ninguna de estas rutas exige credenciales.** Verificado a nivel de configuración: `debug:firewall main` confirma que no hay autenticadores registrados, con independencia de lo que cada ruta devuelva.
- Los tres endpoints `POST /api/mercure/*` son escribibles de forma anónima: cualquiera puede publicar en cualquier topic del hub.
- No es teórico en despliegue: el `Dockerfile:39-40` fija `APP_ENV=prod` / `APP_DEBUG=0`, y `docs/DEPLOYMENT.md` describe un despliegue en VPS. Hoy el panel es superficie pública.
- Agravante: `docs/ADMIN-FEATURES.md` afirma en su tabla de estado **"Permissions | ✅ Built-in | Role-based access"**, lo que da por resuelto algo que no está activado (ver **T-15**).
- Mitigantes actuales: el dashboard no expone todavía ningún CRUD ni entidad (ver **T-05**, **T-11**) y `/admin` ni siquiera completa una petición (**T-22**), así que por esa vía la fuga de datos hoy es nula. Ambos mitigantes desaparecen al arreglar el panel o al añadir el primer `CrudController`. **En los endpoints de Mercure no hay mitigante**: funcionan y están abiertos.

**Solución propuesta**

1. Activar la regla de acceso:

```yaml
    access_control:
        - { path: ^/admin, roles: ROLE_ADMIN }
```

2. Dar al firewall un autenticador real. Para una demo, `http_basic` con un usuario en memoria parametrizado por entorno es suficiente y no arrastra entidades ni formularios:

```yaml
    providers:
        users_in_memory:
            memory:
                users:
                    '%env(ADMIN_USER)%':
                        password: '%env(ADMIN_PASSWORD_HASH)%'
                        roles: ['ROLE_ADMIN']

    firewalls:
        main:
            lazy: true
            provider: users_in_memory
            http_basic: ~
```

3. Añadir `ADMIN_USER` y `ADMIN_PASSWORD_HASH` a `.env` con valores de desarrollo (hash generado con `bin/console security:hash-password`), y documentar en `docs/DEPLOYMENT.md` que deben sobreescribirse en producción.

4. Proteger también los tres endpoints de Mercure, que hoy son `POST` anónimos capaces de publicar en cualquier topic (`mercure_publish`, `mercure_transactions`, `mercure_webhook` — ver **T-07** para su cobertura de tests):

```yaml
        - { path: ^/api/mercure, roles: ROLE_ADMIN }
```

**Criterios de aceptación**

- [ ] `GET /admin` sin credenciales devuelve `401`.
- [ ] `GET /admin` con credenciales válidas no devuelve `401` (que además **renderice** depende de **T-22**).
- [ ] `POST /api/mercure/publish` sin credenciales devuelve `401`.
- [ ] Existe un test funcional que cubre los tres casos anteriores.
- [ ] `docs/DEPLOYMENT.md` documenta las variables de entorno nuevas.

---

## 3. P1 — Alta

### T-04 · El `Dockerfile` produce builds no reproducibles y mete dev-deps en la imagen de producción

| | |
|---|---|
| **Severidad** | P1 |
| **Esfuerzo** | M (2–3 h) |
| **Ficheros** | `Dockerfile:14-25`, `.dockerignore` |

**Evidencia**

```dockerfile
14  COPY composer.json ./          # <- sin composer.lock
18  RUN composer install --no-progress --no-interaction --no-scripts
20  COPY . .                       # <- vendor/ NO está en .dockerignore
21  RUN composer dump-autoload --optimize && \
22      cp vendor/symfony/runtime/Internal/autoload_runtime.template vendor/autoload_runtime.php && \
23      sed -i "s/%runtime_class%/'Symfony\\\\Component\\\\Runtime\\\\SymfonyRuntime'/g" vendor/autoload_runtime.php && \
24      sed -i 's|%runtime_options%|\[\]|g' vendor/autoload_runtime.php && \
```

```console
$ grep -c vendor .dockerignore
0
$ git check-ignore -v vendor/
.gitignore:33:/vendor/    vendor/
```

**Tres problemas encadenados**

1. **Build no reproducible.** La línea 14 copia `composer.json` pero no `composer.lock`. `composer install` sin lock resuelve las versiones más recientes que cumplan las restricciones, así que la imagen **no contiene las versiones fijadas en el repositorio**. Dos builds del mismo commit pueden dar dependencias distintas. Además, `carlosgude/integration-engine` está declarado como `dev-main`: la imagen se lleva el `main` del momento del build.
2. **El `COPY . .` pisa el vendor instalado.** Como `vendor/` no está en `.dockerignore`, la línea 20 sobreescribe el `vendor/` que Linux acaba de instalar con el `vendor/` del host (macOS). De ahí el parche manual de las líneas 22-24: reconstruir a mano `autoload_runtime.php` desde la plantilla porque el copiado deja el árbol inconsistente. **El hack es un síntoma, no la solución.**
3. **Dev-dependencies en la imagen de producción.** La línea 18 instala con dev, así que PHPUnit, PHPStan, Infection, PHP-CS-Fixer y deptrac viajan a una imagen que arranca con `APP_ENV=prod`. Esto además **enmascara T-02**: el build funciona sólo porque EasyAdmin entra por la puerta de las dev-deps.

**Solución propuesta**

Build multi-etapa, con lock, sin dev-deps y con el vendor excluido del contexto:

```dockerfile
FROM php:8.4-fpm AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-progress --no-interaction --optimize-autoloader

FROM php:8.4-fpm
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx curl \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY --from=vendor /app/vendor ./vendor
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative
```

Y en `.dockerignore`:

```
vendor/
tests/
docs/
.php-cs-fixer.dist.php
phpstan.neon
phpstan-baseline.neon
phpunit.xml.dist
infection.json5
deptrac.yaml
```

Nota de dependencia: este cambio **exige resolver T-02 primero**, porque con `--no-dev` el arranque falla mientras EasyAdmin siga en `require-dev`.

**Criterios de aceptación**

- [ ] `docker build .` termina sin los `sed` manuales sobre `autoload_runtime.php`.
- [ ] `docker run <img> composer show --installed | grep phpunit` no devuelve nada.
- [ ] Dos builds consecutivos del mismo commit instalan exactamente las versiones de `composer.lock`.
- [ ] La imagen arranca y responde `200` en `/`.

---

### T-05 · `config/packages/easy_admin.yaml` es configuración muerta que apunta a clases inexistentes

| | |
|---|---|
| **Severidad** | P1 |
| **Esfuerzo** | S (15 min) |
| **Ficheros** | `config/packages/easy_admin.yaml` (completo), referencias a `App\Entity\User` y `App\Entity\Transaction` |

**Evidencia**

```console
$ bin/console debug:config easy_admin
The extension with alias "easy_admin" does not have its getConfiguration() method setup.

$ ls src/Entity
ls: src/Entity: No such file or directory

$ grep -rn "App\\\\Entity" src config
config/packages/easy_admin.yaml:11:            class: App\Entity\User
config/packages/easy_admin.yaml:15:            class: App\Entity\Transaction
```

**Causa**

EasyAdmin 4 eliminó la configuración por YAML: el dashboard se define en PHP mediante `AbstractDashboardController` y atributos (que es justo lo que hace `src/Controller/Admin/DashboardController.php`). La extensión `easy_admin` existe como alias pero **no declara árbol de configuración**, así que Symfony **ignora silenciosamente** el contenido del fichero. Ni `site_name`, ni `dashboards`, ni `entities` tienen ningún efecto.

Encima, las dos entidades que declara (`App\Entity\User`, `App\Entity\Transaction`) no existen en el proyecto: no hay `src/Entity/`, y el mapeo de Doctrine apunta a otro sitio distinto y vacío (ver **T-11**).

**Impacto**

- Es documentación falsa dentro del código: cualquiera que lea el fichero creerá que el admin gestiona usuarios y transacciones.
- Alimenta las afirmaciones incorrectas de `docs/ADMIN-FEATURES.md` (ver **T-15**).
- Riesgo latente: si una versión futura de EasyAdmin introdujera un árbol de configuración con esas claves, el arranque pasaría de ignorar el fichero a fallar.

**Solución propuesta**

Borrar el fichero y llevar lo único aprovechable (`site_name`) al dashboard en PHP:

```console
rm config/packages/easy_admin.yaml
```

```php
// src/Controller/Admin/DashboardController.php
public function configureDashboard(): Dashboard
{
    return Dashboard::new()
        ->setTitle('Movie Rental Admin');   // antes en easy_admin.yaml:2
}
```

Si de verdad se quieren los CRUD de usuarios y transacciones, eso es **T-11** (crear las entidades) y va después.

**Criterios de aceptación**

- [ ] `config/packages/easy_admin.yaml` ya no existe.
- [ ] `grep -rn "App\\Entity" src config` no devuelve nada (salvo el comentario de `validator.yaml:6`).
- [ ] `/admin` sigue respondiendo y muestra el título correcto.
- [ ] `bin/console lint:container` sigue en verde.

---

### T-06 · `str_getcsv()` sin `$escape` en código propio: deprecation en PHP 8.4, ruptura en PHP 9

| | |
|---|---|
| **Severidad** | P1 |
| **Esfuerzo** | S (30 min) |
| **Ficheros** | `src/Pricing/Infrastructure/Http/CsvClientAdapter.php:40` |

**Evidencia**

```console
$ vendor/bin/phpunit --display-deprecations
4 tests triggered 2 PHP deprecations:

1) vendor/carlosgude/integration-engine/src/Utils/CsvParser.php:104
   str_getcsv(): the $escape parameter must be provided as its default value will change

2) src/Pricing/Infrastructure/Http/CsvClientAdapter.php:40
   str_getcsv(): the $escape parameter must be provided as its default value will change

Triggered by:
  * CsvClientAdapterTest::parsesValidCSV (4 times)
  * CsvClientAdapterTest::skipsEmptyLines (3 times)
  * CsvClientAdapterTest::throwsOnMisalignedColumns (2 times)
```

```php
// src/Pricing/Infrastructure/Http/CsvClientAdapter.php:38-41
private static function columns(string $line): array
{
    return \array_map(static fn (?string $value): string => (string) $value, \str_getcsv($line));
}
```

**Impacto**

- En PHP 8.4 es un `E_DEPRECATED` que ensucia la salida de tests y los logs.
- En PHP 9 **el valor por defecto de `$escape` cambia** de `"\\"` a `""`. El parseo de filas con barras invertidas cambiará de comportamiento **sin avisar**: hoy `\` escapa el carácter siguiente, mañana será un carácter literal. En un adaptador CSV de precios de proveedor eso es corrupción silenciosa de datos, no un warning cosmético.

**Solución propuesta**

Ser explícito y elegir el comportamiento futuro (sin escape, que es el estándar RFC 4180 y lo que esperan los CSV de proveedores):

```php
private static function columns(string $line): array
{
    return \array_map(
        static fn (?string $value): string => (string) $value,
        \str_getcsv($line, ',', '"', ''),
    );
}
```

Añadir además un caso de test que fije el comportamiento elegido:

```php
#[Test]
public function backslashIsTreatedAsLiteralCharacter(): void
{
    $rows = (new CsvClientAdapter())->parseCSV("sku,path\nA1,C:\\tmp\\file");
    self::assertSame('C:\\tmp\\file', $rows[0]['path']);
}
```

La segunda ocurrencia (`vendor/carlosgude/integration-engine/src/Utils/CsvParser.php:104`) está **en el bundle, no aquí**: abrir issue/PR en `CarlosGude/integrationEngine`. Dado que la demo consume `dev-main`, el arreglo llega solo al mergearse.

**Criterios de aceptación**

- [ ] `vendor/bin/phpunit --display-deprecations` ya no reporta la deprecation de `CsvClientAdapter.php`.
- [ ] Existe un test que fija el tratamiento de la barra invertida.
- [ ] Queda registrado el issue upstream para `CsvParser.php:104`.

---

### T-07 · Superficie sin tests: controlador Mercure y dos comandos

| | |
|---|---|
| **Severidad** | P1 |
| **Esfuerzo** | M (3–4 h) |
| **Ficheros** | `src/Controller/MercureUpdateController.php` (126 líneas), `src/Command/MercurePublishCommand.php` (82), `src/Console/SimulateRentalCommand.php` (235), `src/Controller/Admin/DashboardController.php` (26) |

**Evidencia**

```console
$ find tests -name "*Test.php" | wc -l
21
$ find tests -path "*Mercure*" -o -path "*Admin*"
(sin resultados)
```

Rutas expuestas y no cubiertas por ningún test:

| Ruta | Método | Controlador |
|---|---|---|
| `/api/mercure/publish` | POST | `MercureUpdateController::publish` |
| `/api/mercure/transactions` | POST | `MercureUpdateController::publishTransaction` |
| `/api/mercure/webhook` | POST | `MercureUpdateController::handleStripeWebhook` |
| `/admin` | ANY | `Admin\DashboardController` |

**Por qué importa aquí en concreto**

Es exactamente el código que tocaron los últimos cuatro commits (`9b17b36 security(controllers): add input validation to Mercure controllers and commands`, `1a2174d`, `f2cd6cf`, `c970d61 refactor: reduce cognitive complexity of SimulateRentalCommand`). Se endureció la validación de entrada y se refactorizó un comando de 235 líneas **sin una sola prueba que fije el comportamiento**. La validación añadida en `MercureUpdateController::publish` (líneas 19-26: JSON inválido → 400, `topic` ausente o no-string → 400) es precisamente lo que un test debería blindar contra regresiones.

Además, `publishTransaction` (línea 45) **no tiene la validación que sí tiene `publish`**: hace `json_decode(...) ?? []` y publica lo que sea, sin comprobar que el cuerpo sea un array. Asimetría que un test de contrato habría hecho evidente.

**Solución propuesta**

1. `tests/Shared/UI/MercureUpdateControllerTest.php` — test funcional con `MockHub` de `symfony/mercure`. **Usar `MockHub` no es opcional**: sin él, el test intenta publicar contra el hub real de `MERCURE_URL`, que en local no está levantado (ver **T-01**). Casos:
   - JSON malformado → `400`.
   - Falta `topic` → `400`.
   - `topic` no-string → `400`.
   - Petición válida → `200` + verificación de que el `Update` publicado lleva el topic y el payload esperados.
   - Los mismos casos para `/api/mercure/transactions`, lo que forzará a corregir la asimetría de validación.
2. `tests/Shared/UI/Console/MercurePublishCommandTest.php` con `CommandTester` + `MockHub`.
3. `tests/Billing/UI/Console/SimulateRentalCommandTest.php` con `CommandTester`, cubriendo el camino feliz y al menos un fallo de integración.
4. `tests/Controller/Admin/DashboardControllerTest.php`: smoke test de que `/admin` responde (y que exige autenticación, una vez cerrado **T-03**).

**Criterios de aceptación**

- [ ] Los cuatro ficheros de test existen y pasan.
- [ ] `publishTransaction` valida la entrada igual que `publish`.
- [ ] La suite total sube de 55 a ≥ 70 tests.

---

### T-08 · Dos tests `skipped` desde la migración a IntegrationEngine v7.0

| | |
|---|---|
| **Severidad** | P1 |
| **Esfuerzo** | M (2–3 h) |
| **Ficheros** | `tests/Catalog/Application/MovieCatalogGatewayTest.php:21,27` |

**Evidencia**

```console
$ vendor/bin/phpunit --display-skipped
There were 2 skipped tests:
1) MovieCatalogGatewayTest::getMovieByIdBuildsMovieDomainObject
   Requires integration engine mock setup
2) MovieCatalogGatewayTest::missingLanguagePlaceholderThrowsError
   Requires integration engine context validation
```

```php
// tests/Catalog/Application/MovieCatalogGatewayTest.php
16  final class MovieCatalogGatewayTest extends TestCase
17  {
18      #[Test]
19      public function getMovieByIdBuildsMovieDomainObject(): void
20      {
21          self::markTestSkipped('Requires integration engine mock setup');
22      }
23
24      #[Test]
25      public function missingLanguagePlaceholderThrowsError(): void
26      {
27          self::markTestSkipped('Requires integration engine context validation');
28      }
29  }
```

El fichero conserva imports sin usar (`MovieCatalogGateway`, `Movie`, `ContainerBuilder`, `MockHttpClient`, `MockResponse`, `HttpClientInterface`, líneas 7-14): son los restos del test real que se vació en `91d145e chore(phase3): integrate IntegrationEngine v7.0 - remove custom adapters`.

**Impacto**

`MovieCatalogGateway` es **la pieza central del proyecto** — el gateway que demuestra `send()` / `sendMany()`, el argumento entero de la demo — y hoy no tiene cobertura unitaria. Los dos casos anulados cubrían justo lo que más se puede romper: la construcción del agregado `Movie` a partir de la respuesta, y la validación del placeholder de idioma.

Además, un test que sólo llama a `markTestSkipped()` es peor que no tener test: aparece en verde en el contador y aporta una falsa sensación de cobertura. Y hay una entrada de baseline de PHPStan asociada a este gateway (`getMoviesByIdBatch()` should return `array<int, Movie|null>`, ver **T-09**), justamente el tipo de error que un test habría detectado.

**Solución propuesta**

Reimplementar ambos tests contra la API v7.0. El proyecto ya tiene un patrón válido que copiar: `tests/Billing/Infrastructure/Webhook/StripeWebhookFlowTest.php` monta un flujo completo, y `tests/Catalog/UI/StorefrontControllerTest.php` trabaja con el kernel de test. La vía más directa es un test de integración con `KernelTestCase` + `MockHttpClient` inyectado en el cliente HTTP del engine, en lugar de intentar mockear el motor pieza a pieza.

Si tras intentarlo resulta que la v7.0 no ofrece un punto de inyección razonable, **eso es un hallazgo sobre el bundle** y merece issue upstream — es exactamente el tipo de fricción que esta demo existe para detectar.

**Criterios de aceptación**

- [ ] Los dos tests ejecutan aserciones reales; `--display-skipped` no reporta ninguno.
- [ ] Se eliminan los imports huérfanos.
- [ ] Desaparece del baseline la entrada de `MovieCatalogGateway::getMoviesByIdBatch()`, o se corrige el tipo.

---

### T-20 · La demo sigue en el modelo de cliente de v6.0 y se auto-registra el servicio que v7.0 eliminó

| | |
|---|---|
| **Severidad** | P1 |
| **Esfuerzo** | S (30 min) |
| **Ficheros** | `config/packages/integration_engine.yaml:12-15`, `config/services.yaml:28-32` |

**Evidencia**

```yaml
# config/packages/integration_engine.yaml
12  countries:
13      base_url: 'https://countries.trevorblades.com'
14      client_service: integration_engine.client.graphql   # ← servicio que v7.0 ya no registra
15      config_path: '%kernel.project_dir%/src/Integrations/Countries/Pricing/Countries.yaml'
```

```yaml
# config/services.yaml
28  integration_engine.client.graphql:
29      class: IntegrationEngine\Infrastructure\Http\GraphQLClientAdapter
30      arguments:
31          $httpClient: '@http_client'
32          $endpointUrl: 'https://countries.trevorblades.com'
```

**Análisis**

En v6.0 el bundle registraba un cliente **global por tipo de adaptador** (`integration_engine.client.graphql`, `integration_engine.client.rest`). En v7.0 el registro pasó a ser **por integración**: `IntegrationCompilerPass` crea un `integration_engine.client.{nombre}` para cada entrada de `integrations:`, y el tipo de adaptador se elige con la clave `client:`. El servicio global desapareció.

La demo nunca llegó a migrar: conserva la referencia de v6.0 y **se define ella misma el servicio que falta** en `config/services.yaml`. Por eso el contenedor compila y el CI está verde — el workaround tapa el cambio de modelo en lugar de adoptarlo.

Consecuencias concretas:

1. **El `base_url` de la línea 13 es configuración muerta.** Cuando `client_service` está puesto, `resolveHttpClientRef()` devuelve esa referencia y sale antes de usar `base_url` y `headers`. La URL que realmente manda es el `$endpointUrl` de `services.yaml:32`. Están duplicadas, y la que *parece* autoritativa es justo la que se ignora.
2. **La demo enseña la configuración equivocada.** El bundle documenta en `MIGRATION-v7-client-registration.md` y `UPGRADE-7.0.md` que ese `client_service` global ya no existe. Esta demo es el ejemplo de referencia del bundle y muestra exactamente lo contrario.
3. **El contract test del bundle pierde su valor.** El workflow `contract.yml` del repo del bundle instala este repo contra el checkout real de v7 (confirmado en el run `35615204608`: hace mirroring de `../bundle` en el commit `7178f55`). Está verde — pero valida **el camino del workaround, no el modelo de cliente de v7.0**. La ruta que el bundle documenta como correcta no la ejercita nadie.
4. **`request_middlewares` quedaría sin efecto** si algún día se usa en esta integración: el compiler pass sólo los inyecta en los adaptadores integrados, no en un `client_service` propio.

**Solución propuesta**

Sustituir `client_service` por la clave `client:` y borrar el servicio a mano. El constructor de `GraphQLClientAdapter` es `(httpClient, endpointUrl, defaultHeaders, requestMiddlewares)` y el compiler pass le pasa `(http_client, base_url, headers)`, así que el cableado queda equivalente y de paso `base_url` pasa a ser real:

```yaml
# config/packages/integration_engine.yaml
countries:
    base_url: 'https://countries.trevorblades.com'
    client: graphql
    config_path: '%kernel.project_dir%/src/Integrations/Countries/Countries.yaml'   # ver T-21
```

Y en `config/services.yaml`, eliminar el bloque `integration_engine.client.graphql` entero.

`app.client.stripe` (línea 17 de `integration_engine.yaml`) **no se toca**: ése sí es un `client_service` legítimo — un adaptador propio (`FormEncodedClientAdapter`) con su propia configuración, que es justo para lo que `client_service` sigue existiendo en v7.0. Conviene dejar ambos casos en la demo, precisamente porque ilustran la diferencia.

**Criterios de aceptación**

- [ ] `grep -r "integration_engine.client.graphql" config/` no devuelve nada.
- [ ] `bin/console lint:container` sigue en OK.
- [ ] `bin/console debug:container integration_engine.client.countries` muestra un `MiddlewareClient` envolviendo a `GraphQLClientAdapter`.
- [ ] La integración `countries` responde igual que antes (ver **T-21**: hoy no responde en absoluto).

---

### T-21 · El `config_path` de `countries` apunta a un directorio que no existe

| | |
|---|---|
| **Severidad** | P1 |
| **Esfuerzo** | S (5 min el arreglo; ver criterios para el alcance) |
| **Ficheros** | `config/packages/integration_engine.yaml:15` |

**Evidencia**

```console
$ bin/console debug:container integration_engine.config.countries
  Class       IntegrationEngine\Infrastructure\Adapter\YamlConfigAdapter
  Arguments   /…/src/Integrations/Countries/Pricing/Countries.yaml

$ ls src/Integrations/Countries/Pricing/
ls: src/Integrations/Countries/Pricing/: No such file or directory

$ ls src/Integrations/Countries/
Countries.yaml   CountriesIntegration.php   GetCountries/   Mappers/
```

El fichero real está en `src/Integrations/Countries/Countries.yaml`. El segmento `Pricing/` de la ruta configurada no existe.

`YamlConfigAdapter` valida en el constructor, no de forma perezosa:

```php
// vendor/carlosgude/integration-engine/src/Infrastructure/Adapter/YamlConfigAdapter.php:25
public function __construct(string $configPath)
{
    if (!file_exists($configPath)) {
        throw new \InvalidArgumentException(sprintf('Integration config file not found: %s', $configPath));
    }
```

**Análisis**

La integración `countries` está rota: en cuanto se instancie `integration_engine.config.countries`, lanza. No ha saltado hasta ahora porque **`CountriesIntegration` no tiene ni un solo consumidor** — `grep -rn "CountriesIntegration" src/ templates/ config/` sólo encuentra su propia declaración. Es código muerto, y el código muerto no falla.

Queda una pregunta abierta que hay que responder antes de cerrar esta tarea: `IntegrationRegistry::register()` recibe instancias concretas (`register(string $name, IntegrationEngine $integration)`), no referencias perezosas, así que **instanciar el registry debería construir todas las integraciones de golpe**, incluida la rota. Y `MovieCatalogGateway` — la pieza central de la demo — inyecta ese registry. Si la cadena es la que parece, el storefront está caído ahora mismo y la suite no lo ve; si no lo está, es que hay un mecanismo de inlining perezoso de por medio que conviene entender antes de confiar en él.

No he confirmado ese extremo: los tests de este repo tardan demasiado en local para cerrarlo en esta pasada. Es lo primero que hay que verificar.

**Solución propuesta**

Corregir la ruta a `src/Integrations/Countries/Countries.yaml` (va junto con **T-20**, que toca el mismo bloque).

Después, decidir qué hacer con la integración: o se le da un consumidor real — encaja con el tour, que es donde `countries` tiene sentido como ejemplo de GraphQL — o se retira del repo. Lo que no aporta es dejarla configurada, rota y sin usar: es una integración que la demo anuncia y no ejecuta.

**Criterios de aceptación**

- [ ] **Primero:** determinar el alcance real con `bin/console debug:container --deprecations` y una petición a una ruta que use `MovieCatalogGateway`. Anotar aquí si el storefront estaba caído.
- [ ] El `config_path` apunta a un fichero existente.
- [ ] Un test cubre `CountriesIntegration::getCountries()` (aunque sea con `MockHttpClient`), de modo que la ruta no pueda volver a pudrirse en silencio.
- [ ] Se decide y se documenta si `countries` se usa o se retira.
- [ ] **T-14** incluye un job que valide que todo `config_path:` declarado existe — es un `grep` + `test -f`, y habría cazado esto el día que se introdujo.

---

---

### T-22 · `GET /admin` no completa ninguna petición

| | |
|---|---|
| **Severidad** | P1 |
| **Esfuerzo** | M (2–3 h, empieza por diagnosticar) |
| **Ficheros** | `src/Controller/Admin/DashboardController.php:12`, `config/packages/easy_admin.yaml` |

**Evidencia**

Sonda funcional con `WebTestCase` sobre el kernel de test:

```php
$client = static::createClient();
$client->request('GET', '/admin');   // nunca retorna
```

Tres ejecuciones, todas abortadas por timeout:

| Intento | Configuración | Resultado |
|---|---|---|
| 1 | por defecto (sigue redirecciones) | sin respuesta > 200 s |
| 2 | `followRedirects(false)` | sin respuesta > 200 s |
| 3 | sólo `/admin`, sin otras peticiones | sin respuesta > 200 s |

Como contraste, la suite completa (55 tests, incluidos los funcionales de `StorefrontController` y `TourController`) tarda **0,417 s**. El arranque del kernel no es el problema: es específico de `/admin`.

**Pista: dos rutas sobre el mismo path**

```console
$ bin/console debug:router | grep admin
  admin                       ANY      /admin
  app_admin_dashboard_index   ANY      /admin
```

El atributo `#[AdminDashboard(routePath: '/admin', routeName: 'admin')]` de `DashboardController.php:12` registra la ruta `admin`, y además se genera `app_admin_dashboard_index` para el mismo path. Un bucle de redirección entre ambas explicaría el intento 1 — **pero no el 2**, donde BrowserKit no sigue redirecciones. Así que la causa está sin confirmar.

> **Estado: diagnóstico pendiente.** Lo verificado es el síntoma (la petición no termina) y la duplicidad de rutas. La causa raíz **no** está determinada. Descartado que sea Xdebug bloqueando: está en `mode=develop`, sin depurador a la escucha.

**Impacto**

- El panel de administración, que es **la mitad de lo que aporta v1.1.0** (`6777d5c feat(v1.1.0): add EasyAdmin + Mercure real-time updates`), no funciona.
- Nadie lo había notado porque no existe ningún test que abra `/admin` (**T-07**).
- Condiciona tres decisiones: **T-02** (¿merece la pena mover a `require` un panel roto?), **T-11** (¿conservar Doctrine por un panel roto?) y **T-03** (la protección es urgente igualmente, pero el riesgo de fuga hoy es menor).

**Solución propuesta**

1. **Diagnosticar primero.** Reproducir con un límite de tiempo y una traza:
   ```console
   php -d max_execution_time=10 vendor/bin/phpunit --filter AdminProbe
   ```
   Si aborta por `max_execution_time`, la traza señalará el punto de bloqueo.
2. Resolver la duplicidad de rutas: fijar un único `routeName`/`routePath` y comprobar que `debug:router` sólo muestra una entrada para `/admin`.
3. Revisar si EasyAdmin intenta resolver assets en tiempo de render (AssetMapper/importmap no están instalados en este proyecto).
4. Añadir el smoke test de **T-07** para que la regresión no vuelva.

> Si se elige la **Opción A de T-11** (quitar Doctrine y EasyAdmin), esta tarea **desaparece**: no habría panel. Conviene decidir T-11 antes de invertir tiempo en diagnosticar.

**Criterios de aceptación**

- [ ] `GET /admin` devuelve una respuesta en menos de 1 s.
- [ ] `debug:router` muestra una sola ruta para `/admin`.
- [ ] Existe un test funcional que abre `/admin` y afirma el código de estado.
- [ ] …o bien se documenta la retirada del panel (Opción A de T-11).

## 4. P2 — Media

### T-09 · Baseline de PHPStan con 27 supresiones

| | |
|---|---|
| **Severidad** | P2 |
| **Esfuerzo** | L (6–8 h, fraccionable) |
| **Ficheros** | `phpstan-baseline.neon` (163 líneas, 27 entradas) |

**Evidencia**

```console
$ grep -c "message:" phpstan-baseline.neon
27
```

Distribución por fichero:

| Fichero | Entradas |
|---|---|
| `tests/Shared/.../RateLimitMiddlewareTest.php` | 4 |
| `src/Kernel.php` | 3 |
| `src/Command/MercurePublishCommand.php` | 2 |
| `src/Console/BenchmarkCommand.php` | 2 |
| Los 6 `*Action::mapper()` (Tmdb ×3, Supplier, Stripe, Countries) | 6 |
| Otros 10 ficheros | 1 cada uno |

**Clasificación de la deuda**

1. **Seis entradas idénticas y triviales** — `Method App\Integrations\*\*Action::mapper() never returns null so it can be removed from the return type`. Afecta a `GetMovieAction`, `GetTvSeasonAction`, `GetConfigurationAction`, `GetPricesAction`, `GetCountriesAction`, `CreatePaymentIntentAction`. Es el mismo error seis veces: la firma heredada del engine declara `?Mapper` y las implementaciones siempre devuelven algo. Se arregla quitando el `?` del tipo de retorno en las seis clases — **o**, si la firma de la clase base del engine lo impide, es feedback para el bundle.
2. **Siete entradas de tests que son ruido de PHPStan, no bugs** — `assertTrue() with true will always evaluate to true`, `method_exists() ... will always evaluate to true`, `is_subclass_of() ... will always evaluate to true`. Son tests defensivos que verifican contratos del engine. Se resuelven mejor con `@phpstan-ignore` local y un comentario que explique por qué, en vez de en el baseline global.
3. **La deuda real (~8 entradas)** — `Cannot cast mixed to int` (×2), `Cannot cast mixed to string`, `Cannot access offset string on mixed`, `Argument of an invalid type mixed supplied for foreach`, `Parameter $data of Update constructor expects string, string|false given`. Esto sí son agujeros de tipado. El de `Update` es particularmente feo: es un `json_encode()` cuyo `false` no se comprueba, en `MercureUpdateController` — el mismo controlador sin tests de **T-07**.
4. **Tres entradas de varianza de listas** — `list<float>` vs `non-empty-array` en `min` y en `max`, y `list<array<string,string>>` vs `array<array<string,string>>` en `GetPricesResponse`. Se arreglan con asserts de no-vacío o afinando los tipos.
5. **Tres entradas sueltas** — `Variable $lastException on left side of ?? always exists` (código muerto en `RetryMiddleware`), `TranslationParityTest::loadMessages` devolviendo `array<mixed>`, y `MovieCatalogGateway::getMoviesByIdBatch` con varianza de clave de lista. Esta última desaparece al cerrar **T-08**.

> Recuento: 6 + 7 + 8 + 3 + 3 = **27**, el total del baseline.

**Solución propuesta**

Atacarlo por lotes, un commit por categoría, regenerando el baseline al final de cada uno:

```console
vendor/bin/phpstan analyse --generate-baseline
```

Orden sugerido: categoría 1 (6 entradas de un golpe) → categoría 3 (la deuda real, empezando por `Update`/`json_encode`) → categoría 4 → categoría 2 (mover a ignores locales comentados).

**Criterios de aceptación**

- [ ] El baseline baja de 27 a ≤ 8 entradas.
- [ ] Cada entrada restante lleva un comentario justificando por qué se mantiene.
- [ ] `make stan` sigue en verde con `level: max`.

---

### T-10 · Deptrac no gobierna la capa de entrada (controladores, comandos, Kernel)

| | |
|---|---|
| **Severidad** | P2 |
| **Esfuerzo** | M (2–3 h) |
| **Ficheros** | `deptrac.yaml:7-63` |

**Evidencia**

```console
$ make deptrac
  Violations           0
  Skipped violations   0
  Uncovered            155
  Allowed              11
```

**Cómo leer ese 155 (y cómo NO leerlo)**

`Uncovered` **no cuenta clases propias sin capa**: cuenta *dependencias* cuyo destino no pertenece a ninguna capa. Desglosado por destino:

```console
$ vendor/bin/deptrac analyse --report-uncovered \
    | grep "has uncovered dependency on" \
    | sed 's/.*uncovered dependency on //' \
    | awk '{print ($1 ~ /^App\\/) ? "APP" : "VENDOR"}' | sort | uniq -c
 155 VENDOR
```

**Las 155 apuntan a código de terceros** (`IntegrationEngine\Core\*`, `Symfony\*`), no a clases del proyecto: cero destinos `App\`. Es el comportamiento normal de deptrac cuando no se declaran capas para el vendor. **No es deuda y no hay que reducirlo a cero.**

**El problema real (que ese número no refleja)**

Los colectores cubren siete namespaces (`App\Shared`, `App\Integrations`, `App\Catalog`, `App\Billing`, `App\Pricing`, `App\Legacy`, `App\Tour`), pero dejan fuera:

- `App\Controller\*` — 4 controladores, incluidos el de Mercure y el de admin.
- `App\Command\*` y `App\Console\*` — 3 comandos.
- `App\Kernel`.

Como esas clases no están en **ninguna** capa, deptrac no analiza sus dependencias en absoluto: ni las valida ni las reporta como `Uncovered`. **Son un punto ciego, no una métrica.** Un controlador puede hoy instanciar directamente un adaptador de infraestructura de otro contexto y `make deptrac` seguiría diciendo `0 violations`.

Segundo problema: el ruleset declara `Shared → Catalog, Billing, Legacy, Tour` (líneas 44-48). Eso es una **inversión de dependencias**: la capa compartida no debería conocer los contextos de dominio. Si hoy no produce violaciones es porque no existe tal dependencia — en cuyo caso la regla sobra y conviene quitarla antes de que alguien la lea como permiso.

**Solución propuesta**

1. Añadir una capa `UI` que recoja lo que falta:

```yaml
    - name: UI
      collectors:
          - type: className
            regex: ^App\\(Controller|Command|Console)\\.*

    - name: Kernel
      collectors:
          - type: className
            regex: ^App\\Kernel$
```

2. Definir su ruleset (la UI orquesta, no implementa):

```yaml
    UI:
      - Shared
      - Catalog
      - Billing
      - Pricing
      - Tour
```

3. Eliminar las dependencias invertidas de `Shared` (líneas 45-48) y verificar que sigue en verde.
4. **Esperar violaciones nuevas al añadir la capa `UI`.** Es el objetivo del cambio: hoy esas dependencias no se miran. Cada una se corrige o se documenta como excepción explícita.

> ⚠️ **No usar `--fail-on-uncovered`.** Con 155 dependencias legítimas hacia vendor, el build fallaría de inmediato. Si se quiere blindar la métrica, la vía correcta es declarar capas para el vendor (`IntegrationEngine`, `Symfony`) y sólo entonces plantearse el flag.

**Criterios de aceptación**

- [ ] `App\Controller`, `App\Command`, `App\Console` y `App\Kernel` pertenecen a una capa.
- [ ] `Violations` vuelve a 0 tras corregir o documentar lo que aflore.
- [ ] `Shared` ya no declara dependencias hacia contextos de dominio.
- [ ] El número de `Uncovered` **no** se usa como criterio (son dependencias a vendor).

---

### T-11 · Doctrine ORM y migraciones instalados y configurados, pero con cero entidades

| | |
|---|---|
| **Severidad** | P2 |
| **Esfuerzo** | M (decidir: 30 min · implementar: 4 h) |
| **Ficheros** | `config/packages/doctrine.yaml:14-20`, `src/Shared/Infrastructure/Persistence/Entity/` (vacío), `composer.json:13-14` |

**Evidencia**

```console
$ bin/console doctrine:mapping:info
 ! [CAUTION] You do not have any mapped Doctrine ORM entities according to the
 !           current configuration.

$ ls src/Shared/Infrastructure/Persistence/Entity/
(vacío)

$ ls migrations
ls: migrations: No such file or directory
```

`composer.json` requiere `doctrine/doctrine-bundle`, `doctrine/orm` y `doctrine/migrations`; `doctrine.yaml:18` apunta el mapeo a un directorio vacío; no hay carpeta `migrations/`; `compose.yaml:12` provisiona una base SQLite en `var/data.db` que nadie usa.

**Impacto**

- Tres dependencias pesadas (ORM, bundle, migraciones) en `require` de producción sin aportar nada: tiempo de boot, tamaño de imagen y superficie de mantenimiento a cambio de cero funcionalidad.
- Es la raíz de los problemas de **T-05** (YAML de EasyAdmin apuntando a entidades fantasma) y de las afirmaciones falsas de **T-15** (docs prometiendo gestión de usuarios y transacciones).
- La demo simula alquileres (`SimulateRentalCommand`, 235 líneas) y publica "transacciones" por Mercure sin persistir nada: el panel de admin no tiene datos que mostrar.

> ⚠️ **Restricción que condiciona esta decisión: EasyAdmin depende de Doctrine.**
>
> ```console
> $ composer why doctrine/orm
> carlosgude/integration-engine-demo dev-main requires  doctrine/orm (^3.0)
> easycorp/easyadmin-bundle          v4.29.16 requires  doctrine/orm (^2.12|^3.0)
> ```
>
> **No se puede quitar Doctrine y conservar el panel de admin.** Esta tarea y **T-02** son la misma decisión vista desde dos lados, y hay que resolverlas juntas. (El bundle `carlosgude/integration-engine` **no** depende de Doctrine: sólo requiere `psr/log` y cuatro componentes de Symfony, así que el engine no impone nada aquí.)

**Solución propuesta** — decisión de producto, dos caminos:

**Opción A: la demo no necesita ni persistencia ni panel → quitar Doctrine *y* EasyAdmin.**

```console
composer remove doctrine/doctrine-bundle doctrine/orm doctrine/migrations
composer remove --dev easycorp/easyadmin-bundle
rm config/packages/doctrine.yaml config/packages/easy_admin.yaml
rm -r src/Controller/Admin
rmdir src/Shared/Infrastructure/Persistence/Entity src/Shared/Infrastructure/Persistence
```

Quitar también `DATABASE_URL` de `.env` y `compose.yaml:12`, y `DoctrineBundle` + `EasyAdminBundle` de `config/bundles.php`. Cierra de golpe **T-02**, **T-05**, **T-22** y parte de **T-15**. Es la opción coherente con lo que la demo demuestra hoy: integraciones, no CRUD.

**Opción B: el panel de admin es una función real → conservar ambos y crear las entidades.**

Crear `Transaction` (y `User` si se quiere gestión de usuarios) en `src/Shared/Infrastructure/Persistence/Entity/`, generar la primera migración, persistir lo que genera `SimulateRentalCommand` y añadir los `CrudController` de EasyAdmin. Es trabajo de verdad (≈ 4 h) y obliga a cerrar antes **T-03**, porque el panel pasaría a mostrar datos reales.

**Recomendación:** Opción A. El valor de esta demo está en las integraciones; un CRUD de transacciones no demuestra nada sobre IntegrationEngine y añade una capa entera que mantener. Además el panel hoy **ni siquiera responde** (ver **T-22**), así que se estaría arreglando algo que nadie usa.

> Si se elige la Opción A, **T-02 queda resuelta por eliminación** (no hay bundle que mover a `require`) y **T-03** se reduce a proteger los endpoints de Mercure. Si se elige la B, hay que hacer T-02, T-03 y T-22 enteras.

**Criterios de aceptación**

- [ ] Se toma y se registra la decisión en el `README`.
- [ ] Si A: `composer show | grep -E "doctrine|easyadmin"` no devuelve nada y la app arranca.
- [ ] Si B: `doctrine:mapping:info` lista las entidades y existe al menos una migración.
- [ ] En ambos casos, `bin/console lint:container` sigue en verde.

---

### T-12 · `qossmic/deptrac-shim` está abandonado

| | |
|---|---|
| **Severidad** | P2 |
| **Esfuerzo** | S (30 min) |
| **Ficheros** | `composer.json:44`, `Makefile:35` |

**Evidencia**

```console
$ composer outdated --direct
qossmic/deptrac-shim  1.0.2  = 1.0.2  deptrac phar distribution
Package qossmic/deptrac-shim is abandoned, you should avoid using it. Use qossmic/deptrac instead.
```

El shim además arrastra una copia interna de `symfony/string` con deprecations de PHP 8.4, que ensucian toda la salida de `make deptrac`:

```
Deprecated: _HumbugBox...\AbstractString::slice(): Implicitly marking parameter
$length as nullable is deprecated ... in phar:///.../deptrac-shim/deptrac/vendor/symfony/string/AbstractString.php on line 340
```

**Solución propuesta**

```console
composer remove --dev qossmic/deptrac-shim
composer require --dev qossmic/deptrac
```

El binario sigue siendo `vendor/bin/deptrac`, así que el `Makefile` no cambia. Verificar que `deptrac.yaml` se lee igual (el formato es compatible) y aprovechar para aplicar **T-10** en el mismo PR.

**Criterios de aceptación**

- [ ] `composer outdated --direct` no reporta paquetes abandonados.
- [ ] `make deptrac` sale limpio, sin deprecations del phar.
- [ ] El CI (job `architecture`) sigue en verde.

---

### T-13 · Incoherencias en las versiones de dependencias

| | |
|---|---|
| **Severidad** | P2 |
| **Esfuerzo** | M (2–4 h) |
| **Ficheros** | `composer.json` |

**Evidencia**

```console
$ composer outdated --direct
doctrine/doctrine-bundle   2.19.1   ~ 3.3.2
easycorp/easyadmin-bundle  4.29.16  ~ 5.6.0
infection/infection        0.28.1   ~ 0.35.4
phpunit/phpunit            10.5.64  ~ 13.3.4
symfony/*                  7.4.x    ~ 8.1.x
```

**Análisis** — no todo hay que actualizarlo, pero sí hay una incoherencia concreta que arreglar:

1. **`symfony/phpunit-bridge: ^8.1` con el resto de Symfony en `^7.4`** (`composer.json:46`). El bridge va una major por delante del framework. Alinearlo a `^7.4` es el cambio correcto y es de bajo riesgo.
2. **Symfony 7.4 → 8.1**: 7.4 es LTS. Quedarse es una decisión defendible; conviene **dejarla escrita** en el README para que no parezca descuido. Si la demo quiere mostrar lo último, la migración es un PR aparte.
3. **PHPUnit 10.5 → 13.x**: tres majors de retraso. El código ya usa atributos (`#[Test]`), que es lo que más cuesta al migrar, así que el salto es más barato de lo que parece. Prioridad media.
4. **Infection 0.28 → 0.35**: irrelevante mientras el mutation testing no se ejecute (ver **T-14**). Actualizar junto con esa tarea.
5. **EasyAdmin 4 → 5**: sólo tiene sentido después de decidir **T-02** y **T-11**.
6. **`carlosgude/integration-engine: dev-main`**: la demo sigue el `main` del bundle sin restricción de versión. Para una demo que acompaña al bundle es intencionado, pero significa que **cualquier commit en el bundle puede romper el CI de este repo sin que nadie toque este repo**. Conviene al menos documentarlo, y considerar fijar un tag ahora que el bundle ha publicado v7.0.1.
   Efecto colateral ya comprobado: el `contract.yml` del bundle tiene que declarar su path repo con la versión exacta que pida este `composer.json`. El 2026-09-21 a las 14:55, el commit `2dadeae` cambió la constraint de `@dev` a `dev-main` y **rompió el contract test del bundle sin que nadie tocara el bundle** — `@dev` es un flag de estabilidad con constraint implícito `*`, que aceptaba el `6.0.99` que el workflow inyectaba; `dev-main` es una rama exacta, y un path repo es canónico, así que una versión que no encaja deja el paquete no instalable en vez de caer al repo VCS. Se arregló en el bundle (`700f2a5`) declarando el path repo como `dev-main`. Fijar `^7.0` aquí eliminaría este acoplamiento del todo. Ver también **T-20**.

**Solución propuesta**

Fase 1 (ahora, bajo riesgo): alinear `symfony/phpunit-bridge` a `^7.4`; documentar en el README la política de versiones (Symfony LTS + engine en `dev-main`).
Fase 2 (PR aparte): PHPUnit 13.
Fase 3 (tras T-02/T-11): EasyAdmin 5, Doctrine bundle 3 — si se mantienen.

**Criterios de aceptación**

- [ ] `symfony/phpunit-bridge` está en `^7.4`.
- [ ] El README documenta la política de versiones y el porqué de `dev-main`.
- [ ] La suite sigue en verde tras cada fase.

---

### T-14 · El CI no cubre lo que realmente se rompe

| | |
|---|---|
| **Severidad** | P2 |
| **Esfuerzo** | M (1–2 h) |
| **Ficheros** | `.github/workflows/ci.yml`, `Makefile:39-40`, `infection.json5` |

**Evidencia**

El workflow tiene cinco jobs: `tests`, `code-style`, `static-analysis`, `architecture`, `build-image`. Y sin embargo:

| Problema | Job que debería haberlo detectado | Por qué no lo detecta |
|---|---|---|
| **T-01** (compose roto) | ninguno | nadie ejecuta `docker compose config` |
| **T-02** (prod no arranca) | ninguno | nadie ejecuta `composer install --no-dev` |
| **T-03** (`/admin` abierto) | ninguno | no hay test funcional de autorización |
| Cobertura de código | `tests` | instala `pcov` pero **nunca genera informe** |
| Mutation testing | ninguno | `make mutation` sólo existe dentro de `make ci`, que el workflow no llama |

El `Makefile` define `ci: qa mutation`, pero el workflow invoca los targets sueltos, así que **`make ci` no se ejecuta jamás**. Infection está instalado y configurado (`infection.json5`) y nunca corre.

**Solución propuesta**

1. Añadir un job de smoke de producción — es el que habría cazado **T-02**:

```yaml
  production-install:
    runs-on: ubuntu-latest
    name: Production Install Smoke Test
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.4', extensions: pdo_sqlite,intl,zip }
      - run: composer install --no-dev --optimize-autoloader
      - run: APP_ENV=prod APP_DEBUG=0 bin/console cache:warmup
```

2. Añadir validación de Compose — habría cazado **T-01**:

```yaml
      - name: Validate compose files
        run: docker compose config -q
```

3. Generar cobertura de verdad (ya se instala `pcov`, sólo falta usarlo) y publicarla como artefacto.
4. Ejecutar Infection en un job `continue-on-error: true` al principio, para establecer una línea base de MSI antes de exigir umbral.
5. Actualizar las actions obsoletas: `actions/cache@v3` → `@v4`, `docker/setup-buildx-action@v2` → `@v3`.

**Criterios de aceptación**

- [ ] El CI falla si `composer install --no-dev` no arranca.
- [ ] El CI falla si `compose.yaml` es inválido.
- [ ] Se publica informe de cobertura como artefacto.
- [ ] Infection se ejecuta y reporta un MSI de referencia.

---

## 5. P3 — Baja (documentación y despliegue)

### T-15 · La documentación describe funcionalidad que no existe

| | |
|---|---|
| **Severidad** | P3 |
| **Esfuerzo** | M (2–3 h) |
| **Ficheros** | `README.md:8,31,119,341`, `docs/ADMIN-FEATURES.md`, `PLAN.md:3` |

**Evidencia — afirmaciones contra el estado real del código**

| Afirmación | Dónde | Realidad |
|---|---|---|
| `Status: v1.0.0 PRODUCTION READY ✅` | `README.md:31` | El HEAD incluye `feat(v1.1.0)`; sin tag v1.1.0 |
| `Ready for Days 27+ (Resilience, VPS, v1.0.0)` | `README.md:341` | Contradice la línea 31 del mismo fichero |
| `Project Status: Days 17-25 Complete` | `PLAN.md:3` | El propio PLAN documenta más abajo el Día 26 como hecho |
| `Permissions \| ✅ Built-in \| Role-based access` | `docs/ADMIN-FEATURES.md` | `access_control` comentado — ver **T-03** |
| `User Management → src/Controller/Admin/UserCrudController.php` | `docs/ADMIN-FEATURES.md` | El fichero no existe |
| `Transaction Management → src/Controller/Admin/TransactionCrudController.php` | `docs/ADMIN-FEATURES.md` | El fichero no existe |
| `Quick Stats: Users, Transactions, Payments, API Health` | `docs/ADMIN-FEATURES.md` | El dashboard tiene un único `MenuItem::linkToDashboard` |
| `Tests: 55+ ✅` | `README.md` tabla de métricas | Correcto (55), pero 2 son `markTestSkipped` — ver **T-08** |

**Impacto**

El caso grave es el de permisos: documentar como "✅ Built-in" un control de acceso que está comentado puede llevar a desplegar dando por hecha una protección inexistente. El resto es erosión de confianza: 14 documentos y 2000+ líneas de docs pierden valor si el lector descubre que las tablas de estado no son fiables.

**Solución propuesta**

1. En `docs/ADMIN-FEATURES.md`, separar sin ambigüedad **implementado** de **propuesto**: reescribir la tabla de estado usando sólo ✅ para lo verificable hoy y 📋 para lo planeado, y eliminar rutas de ficheros que no existen.
2. Unificar el estado de versión en `README.md` (líneas 31 y 341) y `PLAN.md:3`.
3. Añadir al final de cada doc de estado la fecha y el commit de última verificación.
4. Revisar los 14 ficheros de `docs/` con el mismo criterio (empezando por `DEPLOYMENT.md`, que es el que más daño hace si miente).

**Criterios de aceptación**

- [ ] Ninguna tabla de estado marca ✅ algo no implementado.
- [ ] Ninguna ruta de fichero en las docs apunta a un fichero inexistente (verificable con un script).
- [ ] `README.md` y `PLAN.md` coinciden en la versión y el estado.

---

### T-16 · Falta el tag `v1.1.0` y no hay `CHANGELOG.md`

| | |
|---|---|
| **Severidad** | P3 |
| **Esfuerzo** | S (30 min) |
| **Ficheros** | raíz del repo, `.dockerignore:19` |

**Evidencia**

```console
$ git tag -l
v1.0.0

$ git log --oneline -6
5fc7e94 fix: correct PHPDoc type hints formatting for PHP CS Fixer
c970d61 refactor(command): reduce cognitive complexity of SimulateRentalCommand
f2cd6cf fix(sonarqube): resolve remaining code quality issues
1a2174d fix(sonarqube): resolve security and code quality issues
9b17b36 security(controllers): add input validation to Mercure controllers
6777d5c feat(v1.1.0): add EasyAdmin + Mercure real-time updates + complete documentation

$ grep -n CHANGELOG .dockerignore
19:CHANGELOG.md          # <- se excluye un fichero que no existe
```

Hay seis commits desde `v1.0.0`, incluido un `feat(v1.1.0)` que nombra explícitamente una versión que nunca se etiquetó. El `.dockerignore` ya excluye un `CHANGELOG.md` inexistente, señal de que se dio por hecho que existiría.

**Solución propuesta**

1. Crear `CHANGELOG.md` siguiendo *Keep a Changelog*, con las entradas de v1.0.0 (recuperables de `docs/RELEASE-NOTES.md`) y v1.1.0.
2. Etiquetar: `git tag -a v1.1.0 -m "EasyAdmin + Mercure real-time updates"` — **después** de cerrar T-01/T-02/T-03, para no etiquetar una versión que no arranca en Compose ni en producción.
3. Decidir si `docs/RELEASE-NOTES.md` se mantiene o se sustituye por el CHANGELOG; tener ambos garantiza que se desincronicen.

**Criterios de aceptación**

- [ ] `CHANGELOG.md` existe y cubre v1.0.0 y v1.1.0.
- [ ] `git tag -l` incluye `v1.1.0`.
- [ ] No hay duplicidad de fuentes de verdad sobre el historial de versiones.

---

### T-17 · `entrypoint.sh`: PHP-FPM sin supervisión y código inalcanzable

| | |
|---|---|
| **Severidad** | P3 |
| **Esfuerzo** | S (30 min) |
| **Ficheros** | `docker/entrypoint.sh:4-11` |

**Evidencia**

```sh
 4  php-fpm &
 5  PHP_PID=$!
 7  exec nginx -g "daemon off;"
10  # If this script ends, kill PHP-FPM
11  kill $PHP_PID 2>/dev/null || true
```

`exec` en la línea 8 reemplaza el proceso del shell, así que **las líneas 10-11 nunca se ejecutan**. Y si `php-fpm` muere, nginx sigue vivo: el contenedor se queda "arriba" devolviendo `502` en todas las rutas PHP.

El `HEALTHCHECK` del `Dockerfile:33-34` (`curl -f http://localhost/`) sí detectaría el 502 y marcaría el contenedor como unhealthy — pero `compose.yaml` no declara `restart` condicionado a healthcheck, así que nadie lo reinicia.

**Solución propuesta**

Lo correcto en un contenedor es un proceso por contenedor, pero para una demo el camino más corto es un supervisor mínimo:

```sh
#!/bin/sh
set -e

php-fpm &
PHP_PID=$!

nginx -g "daemon off;" &
NGINX_PID=$!

# Si cualquiera de los dos cae, el contenedor cae con él
wait -n "$PHP_PID" "$NGINX_PID"
exit $?
```

Alternativa más limpia: separar `php` y `nginx` en dos servicios de Compose.

**Criterios de aceptación**

- [ ] Matar `php-fpm` dentro del contenedor termina el contenedor.
- [ ] No queda código inalcanzable en el script.
- [ ] El healthcheck sigue funcionando.

---

### T-18 · `compose.override.yaml` reclama los puertos 80 y 443 del host

| | |
|---|---|
| **Severidad** | P3 |
| **Esfuerzo** | S (15 min) |
| **Ficheros** | `compose.override.yaml:6-8,11-13` |

**Evidencia**

```yaml
# compose.override.yaml
 6    ports:
 7      - "80:80"
 8      - "443:443"
```

Frente a `compose.yaml:15`, que hace lo razonable:

```yaml
    ports:
      - "127.0.0.1:8080:80"
```

**Problemas**

1. El override sustituye el bind seguro a `127.0.0.1:8080` por un bind **a todas las interfaces** en los puertos privilegiados 80 y 443. En un portátil en una red compartida, eso publica la aplicación a la LAN.
2. El contenedor **no escucha en 443**: `docker/nginx.conf` sirve sólo por HTTP y el `Dockerfile:31` únicamente hace `EXPOSE 80`. El mapeo `443:443` no lleva a ninguna parte.
3. En macOS y Linux, enlazar al puerto 80 suele chocar con otros servicios o requerir privilegios.
4. El fichero **en sí mismo es YAML válido** (`services: [php, mercure]` parsea sin problema), a diferencia de `compose.yaml`. Pero como Compose fusiona ambos ficheros, hoy no llega a evaluarse nunca: el fallo de **T-01** aborta antes. Al arreglar T-01, estos puertos pasarán a aplicarse de golpe — conviene corregirlos en el mismo PR.

**Solución propuesta**

```yaml
services:
  php:
    environment:
      APP_ENV: dev
      APP_DEBUG: 1
    ports:
      - "127.0.0.1:8000:80"
```

Eliminar el mapeo de 443 mientras no haya TLS en el contenedor. Si se quiere HTTPS en local, la vía natural es `symfony server:start` o un proxy delante, no exponer un 443 que nadie atiende.

**Criterios de aceptación**

- [ ] `docker compose config` muestra los puertos enlazados sólo a `127.0.0.1`.
- [ ] No hay mapeos a puertos que el contenedor no sirve.
- [ ] `docs/QUICKSTART.md` documenta la URL correcta.

---

### T-19 · Secretos por defecto propagados por varios ficheros

| | |
|---|---|
| **Severidad** | P3 |
| **Esfuerzo** | S (1 h) |
| **Ficheros** | `.env`, `compose.yaml:11,37-38`, `docs/DEPLOYMENT.md` |

**Punto de partida positivo:** la higiene básica está bien. No hay credenciales reales commiteadas. `.env` contiene marcadores (`TMDB_ACCESS_TOKEN=` vacío, `sk_test_placeholder`, `whsec_test_placeholder`), `.env.local` está en `.gitignore` (líneas 1 y 27), y el histórico está limpio.

**Lo que sí conviene arreglar**

```console
$ git show HEAD:.env | grep -E "APP_SECRET|MERCURE_JWT"
APP_SECRET=dev-secret-change-in-production
MERCURE_JWT_SECRET="!ChangeThisMercureHubJWTSecretKey!"
```

```yaml
# compose.yaml
11      APP_SECRET: ${APP_SECRET:-dev-secret}                              # default distinto al de .env
37      MERCURE_PUBLISHER_JWT_KEY: '!ChangeThisMercureHubJWTSecretKey!'    # hardcodeado, sin var de entorno
38      MERCURE_SUBSCRIBER_JWT_KEY: '!ChangeThisMercureHubJWTSecretKey!'
```

Dos detalles concretos: la clave JWT de Mercure está **escrita a fuego** en `compose.yaml` en vez de leerse de `MERCURE_JWT_SECRET`, así que cambiarla en `.env.local` no surte efecto; y el valor por defecto de `APP_SECRET` difiere entre `.env` y `compose.yaml`, lo que hace que la sesión se invalide según cómo se arranque.

**Solución propuesta**

1. Parametrizar Mercure en `compose.yaml` (ya incluido en el fragmento de **T-01**):

```yaml
      MERCURE_PUBLISHER_JWT_KEY: ${MERCURE_JWT_SECRET:-!ChangeThisMercureHubJWTSecretKey!}
      MERCURE_SUBSCRIBER_JWT_KEY: ${MERCURE_JWT_SECRET:-!ChangeThisMercureHubJWTSecretKey!}
```

2. Unificar el valor por defecto de `APP_SECRET` entre `.env` y `compose.yaml`.
3. Añadir a `docs/DEPLOYMENT.md` una checklist explícita de secretos a rotar antes de desplegar: `APP_SECRET`, `MERCURE_JWT_SECRET`, `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`, `TMDB_ACCESS_TOKEN` y las credenciales de admin de **T-03**.
4. Opcional: `bin/console secrets:set` (Symfony Secrets) para producción.

**Criterios de aceptación**

- [ ] Ningún secreto queda hardcodeado en `compose.yaml`.
- [ ] `APP_SECRET` tiene el mismo valor por defecto en todas partes.
- [ ] `docs/DEPLOYMENT.md` incluye la checklist de rotación.

---

---

### T-23 · `config/reference.php` es un artefacto generado y está commiteado

| | |
|---|---|
| **Severidad** | P3 |
| **Esfuerzo** | S (15 min) |
| **Ficheros** | `config/reference.php` (98 KB), `.gitignore` |

**Evidencia**

```php
// config/reference.php:3
// This file is auto-generated and is for apps only. Bundles SHOULD NOT rely on its content.
```

Se detectó durante esta revisión: **basta con ejecutar un comando de consola para que el fichero cambie**. Tras lanzar una sonda de test, `git status` mostró:

```console
$ git diff --stat config/reference.php
 config/reference.php | 14 ++++++++++++++
```

Las 14 líneas añadidas eran bloques `"when@dev"?: array{...}` que Symfony regenera según el entorno con el que se arrancó por última vez.

**Impacto**

- Genera diffs espurios en cada PR según quién haya ejecutado qué y con qué `APP_ENV`.
- Ruido en las revisiones: 98 KB de anotaciones de tipos autogeneradas que nadie lee pero que aparecen como cambios reales.
- Riesgo de conflictos de merge en un fichero que ninguna persona edita a mano.

**Matiz a favor de conservarlo:** este fichero da array-shapes a PHPStan para la configuración de bundles, lo que ayuda con `level: max`. Si se comprueba que el baseline empeora al quitarlo, la alternativa es regenerarlo de forma determinista en CI en lugar de commitearlo.

**Solución propuesta**

1. Comprobar si PHPStan lo necesita: quitarlo del control de versiones y ejecutar `make stan`.
2. Si el baseline no empeora:
   ```console
   git rm --cached config/reference.php
   echo "/config/reference.php" >> .gitignore
   ```
3. Si sí lo necesita: dejarlo, pero documentar en `docs/CONTRIBUTING.md` que es generado y que sus diffs se descartan (`git checkout -- config/reference.php`).

**Criterios de aceptación**

- [ ] Ejecutar `bin/console` en cualquier entorno no ensucia `git status`.
- [ ] `make stan` sigue en verde.
- [ ] La decisión queda documentada en `docs/CONTRIBUTING.md`.

## 6. Plan de ejecución sugerido

### Sprint 0 — Una decisión que condiciona el resto (30 min)

**Decidir T-11: ¿el proyecto conserva el panel de admin?**

No es una tarea de código, es una bifurcación. `easycorp/easyadmin-bundle` arrastra `doctrine/orm`, y el panel además hoy no responde (**T-22**). De la respuesta depende si hay que hacer **T-02**, **T-05** y **T-22** o si desaparecen las tres.

| Decisión | Tareas que se activan | Tareas que desaparecen |
|---|---|---|
| **Quitar panel + Doctrine** (recomendado) | — | T-02, T-05, T-22 |
| **Conservar panel** | T-02, T-05, T-22 (diagnóstico) | — |

**Hacer esto antes de tocar nada más** evita invertir horas en diagnosticar un panel que quizá se retire.

### Sprint 1 — Desbloquear (½ día)

Sin esto, ni el entorno local ni un despliegue real funcionan.

1. **T-01** — arreglar `compose.yaml` *(15 min)*
2. **T-18** — arreglar los puertos del override *(15 min, va con T-01)*
3. **T-03** — proteger `/api/mercure` y, si sigue habiendo panel, `/admin` *(2-3 h)*
4. **T-02** — mover EasyAdmin a `require` *(20 min, sólo si se conserva el panel)*

**Resultado:** `make up` funciona, `composer install --no-dev` arranca, no quedan endpoints anónimos de escritura.

### Sprint 2 — Build y correcciones (1 día)

5. **T-04** — Dockerfile multi-etapa *(depende de T-02)*
6. **T-06** — `str_getcsv()` explícito
7. **T-05** — borrar el YAML muerto de EasyAdmin *(sólo si se conserva el panel)*
8. **T-21** — arreglar el `config_path` de `countries` *(5 min)*
9. **T-20** — migrar `countries` al modelo de cliente de v7.0 *(30 min, mismo bloque que T-21)*
10. **T-14** — jobs de CI que habrían cazado T-01, T-02 y T-21
11. **T-19** — parametrizar secretos

**Resultado:** el CI empieza a detectar esta clase de fallos por sí solo, y el contract test del bundle vuelve a validar el camino que el bundle documenta.

### Sprint 3 — Cobertura (1-2 días)

12. **T-07** — tests de Mercure y comandos *(con `MockHub`, no con el hub real)*
13. **T-22** — diagnosticar `/admin` *(sólo si se conserva el panel)*
14. **T-08** — recuperar los tests de `MovieCatalogGateway`
15. **T-10** — capa `UI` en deptrac
16. **T-12** — migrar a `qossmic/deptrac`

### Sprint 4 — Deuda y consistencia (1-2 días)

17. **T-09** — reducir el baseline de PHPStan
18. **T-13** — alinear versiones
19. **T-23** — decidir sobre `config/reference.php`
20. **T-15** — sincronizar documentación
21. **T-16** — CHANGELOG y tag v1.1.0
22. **T-17** — supervisión en el entrypoint

---

## 7. Apéndice — Reproducir este análisis

```console
# Puertas de calidad
make test
make cs
make stan
make deptrac

# Verificaciones que el CI no hace hoy
docker compose config -q                       # T-01
composer install --no-dev && bin/console cache:warmup   # T-02
bin/console debug:router | grep admin          # T-03
vendor/bin/phpunit --display-deprecations      # T-06
vendor/bin/phpunit --display-skipped           # T-08
grep -c "message:" phpstan-baseline.neon       # T-09
bin/console doctrine:mapping:info              # T-11
composer outdated --direct                     # T-12, T-13
git tag -l                                     # T-16

# Migración a IntegrationEngine v7.0
grep -rn "integration_engine.client.graphql" config/          # T-20
bin/console debug:container integration_engine.client.countries   # T-20
bin/console debug:container integration_engine.config.countries   # T-21
ls src/Integrations/Countries/Pricing/                        # T-21

# Verificaciones de la segunda pasada (revisión)
bin/console debug:firewall main                # T-03 (¿hay autenticadores?)
composer why doctrine/orm                      # T-11 (EasyAdmin arrastra Doctrine)
vendor/bin/deptrac analyse --report-uncovered \
  | grep "has uncovered dependency on" \
  | sed 's/.*uncovered dependency on //' \
  | awk '{print ($1 ~ /^App\\/) ? "APP" : "VENDOR"}' \
  | sort | uniq -c                             # T-10 (las 155 son a vendor)
python3 -c "import yaml; yaml.safe_load(open('compose.override.yaml'))"  # T-18
```

### Lo que está bien y conviene no romper

No todo es deuda. Merece la pena dejar constancia de lo que está sólido:

- **PHPStan en `level: max` sobre `src/` y `tests/`**, con `reportUnmatchedIgnoredErrors: true` — configuración exigente y bien planteada.
- **Deptrac con capas por contexto de dominio** — la idea es correcta; sólo hay que ampliar la cobertura (**T-10**).
- **`StripeWebhookFlowTest`** — cubre firma válida, firma inválida, firma caducada y filtrado de eventos. Es el mejor test del repositorio y el patrón a replicar en **T-07** y **T-08**.
- **`TranslationParityTest`** — verifica automáticamente la paridad EN/ES. Poca gente se molesta en hacer esto.
- **`LegacyEngineParityTest`** — valida que el refactor preserva el comportamiento del código legado. Es exactamente el argumento que la demo quiere demostrar.
- **Higiene de secretos** — sin credenciales en el histórico, `.env` con marcadores, `.env.local` ignorado.
- **La exclusión deliberada de `src/Legacy/` en PHPStan**, con un comentario que explica por qué (es la pieza "antes" del tour). Deuda documentada e intencionada, que es lo contrario de deuda oculta.
