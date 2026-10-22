# Observability: metrics from lifecycle events

IntegrationEngine's integrations already emit lifecycle events (see
`vendor/carlosgude/integration-engine/src/Core/Event/`): `RequestSent` when a call
starts, `ResponseReceived` once the API has answered and before mapping (engine
v9.4+), and `ResponseMapped` or `RequestFailed` to close it. This demo turns those
into metrics and into the tour's execution trace entirely from the application
side: the bundle stays agnostic, and the application decides. Each destination —
Prometheus, InfluxDB, the tour trace — is just another Symfony listener over the
same events, with no change to the bundle or to any integration under
`src/Integrations/`.

## 1. Flow

```
IntegrationEngine::send()
    → dispatches ResponseReceived, then ResponseMapped / RequestFailed
      (Symfony's event_dispatcher)
    → App\Shared\Observability\Lifecycle\PrometheusLifecycleListener
        (#[AsEventListener] on all three)
    → Prometheus\CollectorRegistry
        (backed by shared storage — see §4)
    → GET /metrics (App\Controller\MetricsController, text/plain 0.0.4)
    → Prometheus scrapes it (pull, see docker/prometheus/prometheus.yml)
    → Grafana (dashboards) / Alertmanager (docker/prometheus/alerts.yml)
```

`App\Shared\Observability\Lifecycle\CollectorRegistryFactory` builds the registry from
`METRICS_STORAGE`. Nothing here reads or depends on anything under
`src/Integrations/`.

## 2. Why labels are bounded

The listener attaches exactly three labels: `integration`, `action`, `status_class`
(see `App\Shared\Observability\StatusClass::fromCode()`). It never attaches
`requestKey`, `message`, `exceptionClass` or `responseClass` — those are per-request
or effectively unbounded values, and Prometheus allocates one time series per unique
label combination. A per-request label would mean a new time series on every single
request, which never gets cleaned up and eventually exhausts the scraper's memory
(cardinality explosion). Bucketing the status code into `2xx/3xx/4xx/5xx/network`
keeps the label space fixed regardless of how long the demo runs.

## 3. Upstream time vs. mapping time

`ResponseMapped::durationMs` covers the whole call: connection resolution, token
fetching, middleware, transport and mapping. `ResponseReceived` (engine v9.4) splits
it: mapping time is `ResponseMapped::timestamp − ResponseReceived::timestamp`, and a
`ResponseReceived` followed by `RequestFailed` means the API answered and the mapper
threw — usually a change in the upstream response shape rather than an outage.

Getting either needs the two events paired. Within `send()` they're back to back;
within `sendMany()` each item's pair carries its batch `requestKey`.
`App\Shared\Observability\Lifecycle\ReceivedResponses` pairs them by
(integration, action, requestKey). Each listener keeps its own instance, since
pairing removes the entry.

From that pairing:

- **Prometheus:** `integration_mapping_duration_ms` (histogram, sub-millisecond to
  50ms buckets) and `integration_mapping_failures_total` (counter, labelled with the
  status the API answered with, since the failure itself reports status 0). Same
  three labels as the other metrics. `docker/prometheus/alerts.yml` raises
  `IntegrationMappingFailures` on any increase.
- **InfluxDB:** a `mapping_ms` field on mapped points and a `mapping_failed` boolean
  field on failed points.
- **Tour trace:** `TraceLifecycleListener` records one entry per call — one per item
  in a `sendMany()` batch — with the real status code, upstream and mapping time, and
  whether it ended `mapped`, `mapping_failed` or `failed`. The trace's total is
  wall-clock time for the step, not the sum of the calls, because batch items overlap.

In a batch the engine gets every item's result at once, so an item's upstream time
runs until the whole batch returned; its mapping time is still exact.

## 4. Why APCu or Redis with PHP-FPM

PHP-FPM workers are separate processes that don't share memory between requests, so
a `CollectorRegistry` built fresh on every request needs storage that outlives the
request to actually accumulate counts. `METRICS_STORAGE` picks the backend:

- `apcu` (default): shared memory local to the host, no extra infrastructure. Used
  by `Prometheus\Storage\APCng`. Requires the `apcu` PHP extension, which is why it's
  installed in the Dockerfile's `development` target (the only target this demo
  actually runs via Compose) and nowhere else.
- `redis`: shares metrics across multiple app containers/hosts. Needs the `redis` PHP
  extension (not installed by default) and a Redis server, provided by the optional
  `compose.metrics.yaml` overlay — never required to start the demo.
- `in_memory`: resets every request; only useful for tests.

If the requested backend isn't actually available (extension missing), the factory
logs a warning and falls back to `in_memory` instead of breaking the request.

## 5. Prometheus (pull) vs. InfluxDB (push)

Prometheus scrapes `/metrics` on an interval (`scrape_interval: 15s` in
`docker/prometheus/prometheus.yml`) and expects the current state of each counter
and histogram, aggregated server-side. It fits this listener well: counters and
histograms are exactly what the lifecycle events already carry, and PHP-FPM doesn't
need to hold a connection open to anywhere.

InfluxDB is a push model, implemented here by
`App\Shared\Observability\Lifecycle\InfluxDbLifecycleListener` (disabled by default —
`INFLUXDB_ENABLED=0`, every method a no-op). Each lifecycle event becomes a `Point`
with **tags** `integration`/`action`/`status_class` (indexed, same bounded set as
Prometheus) and **fields** `duration_ms`/`status_code`/`request_key`, plus
`mapping_ms` or `mapping_failed` (see §3). `requestKey`
is a field, not a tag, specifically because InfluxDB only indexes tags — a field
doesn't create a new series per value, which is the opposite of a Prometheus label.
The demo has no workers, so points are accumulated in memory during the request and
written in one batch on `kernel.terminate`/`console.terminate`, with millisecond
precision from the event's own `timestamp`, rather than written inline (which would
hold up the response on a slow InfluxDB server) or queued through Messenger.

Use Prometheus for alerting on aggregate rates/latencies; reach for a push-based
store like InfluxDB when you need to inspect individual requests after the fact.

## 6. Running it locally

```bash
# .env.local
METRICS_ENABLED=1
METRICS_STORAGE=apcu   # default; no extra service needed

docker compose up -d --build --wait
curl http://localhost:8080/metrics
```

To also run Prometheus against it:

```bash
docker compose -f compose.yaml -f compose.metrics.yaml up -d --build --wait
# Prometheus UI: http://localhost:9090
```

`METRICS_ENABLED` defaults to `0`: `/metrics` is a 404 until explicitly turned on,
and it's never linked from the tour or the storefront navigation.

To also push points to an InfluxDB instance you already have running:

```bash
# .env.local
INFLUXDB_ENABLED=1
INFLUXDB_URL=http://localhost:8086
INFLUXDB_TOKEN=your-token
INFLUXDB_ORG=your-org
INFLUXDB_BUCKET=your-bucket
```

No InfluxDB server is part of this demo or its Compose files — `INFLUXDB_ENABLED`
stays `0` unless you point it at one yourself.
