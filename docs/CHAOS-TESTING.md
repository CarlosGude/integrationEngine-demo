# 🔴 Chaos Testing Guide

**IntegrationEngine Demo** — Testing resilience patterns with controlled failure injection

---

## Overview

Chaos testing deliberately injects failures to verify your system recovers gracefully. This guide shows how to use the demo's built-in chaos mode to test resilience.

---

## Quick Start

### Normal Mode (Happy Path)
```bash
php bin/console billing:simulate-rental 550
```

Output:
```
ℹ Simulating rental for movie #550...
✓ Payment intent created!
Field                     Value
─────────────────────────────────────
Payment Intent ID         pi_xxxxx
Movie ID                  550
Amount                    500 cents (USD)
Status                    requires_payment_method
Client Secret             ***xxxxxxxxxxx
```

### Chaos Mode (Inject Failures)
```bash
php bin/console billing:simulate-rental 550 --chaos
```

**Example Output:**
```
ℹ Simulating rental for movie #550...
⚠ 🔴 CHAOS MODE ENABLED
  Timeout failures: 30%
  Rate limit failures: 20%

Attempt 1/5...
  ⟳ Transient: Request timeout (simulated)
Attempt 2/5...
  ⟳ Transient: Too Many Requests (simulated)
Attempt 3/5...
  ⟳ Transient: Request timeout (simulated)
Attempt 4/5...
  ✓ Success

✓ Eventually succeeded! This demonstrates retry resilience.

RESILIENCE SUMMARY
  ✓ Survived 1 out of 4 attempts
  ⟳ Retried on transient errors (timeouts, rate limits)
  ✓ Recovered after failures via exponential backoff

FAILURE LOG
Attempt  Error                            Retryable?
────────────────────────────────────────────────────
1        Request timeout (simulated)      Yes (⟳)
2        Too Many Requests (simulated)    Yes (⟳)
3        Request timeout (simulated)      Yes (⟳)

ℹ In production, RetryMiddleware + CircuitBreaker would handle this automatically.
```

---

## Configuration

### Timeout Injection
```bash
php bin/console billing:simulate-rental 550 --chaos --timeout-rate=50
```

- `--timeout-rate=30` (default): 30% of requests timeout
- `--timeout-rate=0`: No timeouts
- `--timeout-rate=100`: All requests timeout (circuit breaker will trip)

### Rate Limiting Injection
```bash
php bin/console billing:simulate-rental 550 --chaos --ratelimit-rate=40
```

- `--ratelimit-rate=20` (default): 20% get 429 rate limit responses
- `--ratelimit-rate=0`: No rate limits
- `--ratelimit-rate=100`: All requests rate-limited

### Combine Both
```bash
php bin/console billing:simulate-rental 550 --chaos --timeout-rate=40 --ratelimit-rate=30
```

Now 70% of requests fail (either timeout or rate limit).

---

## What the Command Demonstrates

### 1. Transient vs Permanent Errors

**Transient** (retry-able):
- Timeouts: Network latency, temporary slowness
- 429 Rate Limit: Too many requests, backoff and retry
- 503 Service Unavailable: Server restarting, temporary

Status: **⟳ Transient**

**Permanent** (fail fast):
- 400 Bad Request: Your data is wrong
- 401 Unauthorized: Invalid token
- 404 Not Found: Endpoint doesn't exist

Status: **✗ Permanent**

### 2. Exponential Backoff

Each retry waits longer:
```
Attempt 1 → Fail
Attempt 2 → Wait 100ms → Retry
Attempt 3 → Wait 200ms → Retry
Attempt 4 → Wait 400ms → Retry → Success ✓
```

Delays: 100ms, 200ms, 400ms, 800ms...

### 3. Recovery

With intelligent retries and backoff:
- Transient failures don't crash your app
- The system recovers automatically
- No need to manually retry or refresh

---

## Test Scenarios

### Scenario 1: Occasional Timeouts (Prod Reality)
```bash
php bin/console billing:simulate-rental 550 --chaos --timeout-rate=15 --ratelimit-rate=5
```

**Expected:** Mostly succeeds; occasional retries on timeout
**Learns:** Backoff works; app stays responsive

### Scenario 2: Heavy Load (Rate Limiting)
```bash
php bin/console billing:simulate-rental 550 --chaos --timeout-rate=5 --ratelimit-rate=50
```

**Expected:** Many 429s; retries with backoff
**Learns:** Rate limiting handled gracefully

### Scenario 3: Cascade Failure (Circuit Breaker)
```bash
php bin/console billing:simulate-rental 550 --chaos --timeout-rate=100 --ratelimit-rate=100
```

**Expected:** All fail; after ~5 attempts, circuit breaker opens
**Learns:** System doesn't hammer failing API forever

### Scenario 4: Flaky Service (Mixed)
```bash
php bin/console billing:simulate-rental 550 --chaos --timeout-rate=30 --ratelimit-rate=30
```

**Expected:** Alternating failures and successes; eventual recovery
**Learns:** Backoff handles intermittent issues

---

## How to Extend

### Add New Failure Types

In `ChaosMonkey`:
```php
public function shouldDatabaseFail(): bool {
    return $this->shouldFail($this->databaseFailureRate);
}

public function injectDatabaseError(): never {
    throw new \Exception('Database connection lost');
}

public function withDatabaseFailureRate(int $rate): self {
    $this->databaseFailureRate = $rate;
    return $this;
}
```

In `SimulateRentalCommand`:
```php
if ($chaos->shouldDatabaseFail()) {
    $failures[] = ['attempt' => $i, 'error' => 'Database error', 'retryable' => false];
    $chaos->injectDatabaseError();
}
```

In the command options:
```php
$this->addOption('database-rate', null, InputOption::VALUE_OPTIONAL, 'Database failure rate', '0');
```

---

## Production Insights

### What Chaos Testing Reveals

✓ **Good:**
- App recovers from transient failures
- Retries don't create request storms
- Circuit breaker prevents cascading
- Fallbacks work as designed

✗ **Problems to Watch:**
- Exponential backoff too aggressive (long customer wait)
- Max retries too high (exhausts resources)
- Fallback data too stale (incorrect information)
- No monitoring of failure rates (silent cascade)

### Metrics to Track

In production, monitor:

```
- retry_attempts_total (counter)
- retry_success_rate (gauge)
- circuit_breaker_state (enum: CLOSED, OPEN, HALF_OPEN)
- fallback_usage_rate (% of requests using fallback)
- mean_retry_duration_ms (how long retries take)
```

Example Prometheus/Grafana:
```promql
# Alert if circuit breaker is OPEN for > 5 minutes
rate(circuit_breaker_state{state="open"}[5m]) > 0
```

---

## Related Documentation

- [Resilience Patterns Guide](RESILIENCE-PATTERNS.md) — Deep dive into patterns
- [Tour: Day 27 - When Suppliers Fail](../config/tour.yaml) — Educational walkthrough
- [IntegrationEngine v7.0 Roadmap](../docs/PROJECT-ANALYSIS.md#engine-roadmap) — Future built-in support

---

## References

- **Chaos Engineering:** https://en.wikipedia.org/wiki/Chaos_engineering
- **Exponential Backoff:** https://aws.amazon.com/blogs/architecture/exponential-backoff-and-jitter/
- **Circuit Breaker:** https://martinfowler.com/bliki/CircuitBreaker.html
- **Netflix Chaos Monkey:** https://netflix.github.io/chaosmonkey/

---

**Generated:** 2026-09-21 | IntegrationEngine Demo — Phase 2.2: Chaos Testing
