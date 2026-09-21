# 🛡️ Resilience Patterns Guide

**IntegrationEngine Demo** — Building fault-tolerant integrations

---

## Overview

When integrating external APIs, failures are inevitable. Network timeouts, rate limits, server errors — they happen. This guide shows three battle-tested patterns for handling them gracefully.

---

## 1. Exponential Backoff Retries

### Problem
A transient error (503 service unavailable, timeout) shouldn't immediately fail your request. Some errors are temporary.

### Solution
Automatically retry with increasing delays between attempts.

### Implementation
See: `src/Shared/Infrastructure/Middleware/RetryMiddleware.php`

```php
// Retry with exponential backoff
for ($attempt = 1; $attempt <= 3; $attempt++) {
    try {
        return $next($action, $context);  // Try the request
    } catch (Throwable $e) {
        if (!$this->isTransient($e)) {
            throw $e;  // Don't retry auth/validation errors
        }
        
        // Wait: 100ms, 200ms, 400ms
        $backoff = 100 * (2 ** ($attempt - 1));
        usleep($backoff * 1000);
    }
}
```

### Error Classification

**Retryable (Transient):**
- 429 Too Many Requests (rate limit)
- 503 Service Unavailable
- 504 Gateway Timeout
- Network timeouts
- Connection refused

**Non-Retryable (Permanent):**
- 400 Bad Request (your data is wrong)
- 401 Unauthorized (invalid token)
- 404 Not Found (endpoint doesn't exist)

### When to Use
- **Always** for external APIs
- Especially for read-only operations (safe to retry)
- Use a short max_attempts (3-5) to avoid cascading delays

### Configuration
```yaml
integration_engine:
    integrations:
        supplier:
            # In v7.0, this will be built-in:
            retry_policy: exponential_backoff
            max_attempts: 3
            initial_backoff_ms: 100
```

---

## 2. Circuit Breaker Pattern

### Problem
If an API is down, don't keep hammering it. Each request wastes time waiting for a timeout. You'll exhaust your own resources.

### Solution
When failures pile up, stop calling the API temporarily. Give it time to recover.

### States

```
CLOSED (normal)
  ↓ (5 failures in 10s)
OPEN (stop calling)
  ↓ (wait 30 seconds)
HALF_OPEN (test recovery)
  ↓ (1 success)
CLOSED (back to normal)
  ↓ (1 failure)
OPEN (back to failing)
```

### Implementation
See: `src/Shared/Infrastructure/Resilience/CircuitBreaker.php`

```php
$breaker = new CircuitBreaker();

// Check before each request
if (!$breaker->allow()) {
    throw new \Exception('Circuit breaker OPEN — supplier API is down');
}

try {
    $result = $api->call(...);
    $breaker->recordSuccess();  // HALF_OPEN → CLOSED
    return $result;
} catch (Throwable $e) {
    $breaker->recordFailure();  // → OPEN if threshold exceeded
    throw $e;
}
```

### When to Use
- External APIs that are slow to start
- APIs behind load balancers that might need restart time
- When you want to fail fast instead of waiting for timeout

### Configuration (Future)
```yaml
integration_engine:
    integrations:
        supplier:
            circuit_breaker:
                failure_threshold: 5          # Failures to trip
                failure_window_sec: 10        # Time window
                timeout_sec: 30               # Time in OPEN before HALF_OPEN
```

---

## 3. Fallback Strategies

### Problem
After retries are exhausted and circuit breaker is open, what do you do? Throw an error to the user?

### Solution
Have a fallback plan: return null, use cached data, or provide a safe default.

### Types

#### A. Null Fallback (Graceful Degradation)
```php
try {
    $prices = $supplier->getPrices();
} catch (Throwable $e) {
    return null;  // User sees "prices unavailable"
}
```

**Use when:** Data is nice-to-have but not critical
**User experience:** "Prices not available right now"

#### B. Cache Fallback (Stale Data)
```php
$cachedPrices = $cache->get('supplier_prices');
$cachedAge = time() - $cachedPrices['cached_at'];

try {
    return $supplier->getPrices();
} catch (Throwable $e) {
    if ($cachedAge < 3600) {  // Less than 1 hour old
        return $cachedPrices;   // Use it
    }
    throw $e;  // Too old, give up
}
```

**Use when:** Read operations with acceptable staleness
**User experience:** "Showing prices from 30 minutes ago"

#### C. Default Fallback (Safe Value)
```php
try {
    $inventory = $supplier->getInventory('SKU-123');
} catch (Throwable $e) {
    return 0;  // Conservative default: no inventory
}
```

**Use when:** Wrong data is dangerous
**User experience:** "Out of stock" (better than overselling)

### Implementation
See: `src/Shared/Infrastructure/Resilience/FallbackStrategy.php`

---

## Combining Patterns

### Recommended Chain
```
1. Try with circuit breaker check
   ↓
2. Retry on transient errors (exponential backoff)
   ↓
3. If all retries fail, use fallback
```

### Example: Resilient Supplier Integration
```php
final class ResilientSupplierGateway {
    public function getPrices(string $sku): array {
        if (!$this->breaker->allow()) {
            return $this->fallback->useCached();
        }
        
        try {
            return $this->retry->execute(function () use ($sku) {
                return $this->supplier->getPrices($sku);
            });
        } catch (Throwable $e) {
            return $this->fallback->useDefault([]);
        }
    }
}
```

---

## Production Checklist

- [ ] **Retries configured** for all external API calls
- [ ] **Error classification** distinguishes transient vs permanent
- [ ] **Circuit breaker active** for APIs that need protection
- [ ] **Max retry attempts** set (3-5, not infinite)
- [ ] **Backoff strategy** prevents thundering herd
- [ ] **Fallback strategy** chosen (null, cache, or default)
- [ ] **Metrics tracked** (retry count, circuit breaker state, fallback usage)
- [ ] **Tests cover** failure scenarios (timeout, 429, 503, etc.)
- [ ] **Observability** includes resilience metrics in logs/dashboards
- [ ] **Timeout configured** per integration (don't wait forever)

---

## Testing Resilience

### Inject Failures
```php
class SupplierApiStub implements SupplierApiInterface {
    public function __construct(private int $failureRate = 50) {}
    
    public function getPrices(string $sku): array {
        if (random_int(1, 100) <= $this->failureRate) {
            throw new \Exception('Service unavailable');
        }
        return ['SKU-123' => 9.99];
    }
}
```

### Test Each Pattern
```php
public function testRetrySucceedsOnThirdAttempt(): void {
    $api = new SupplierApiStub(failureRate: 66);  // 2/3 fail
    $retried = new RetryMiddleware($api);
    $result = $retried->getPrices('SKU-123');  // Succeeds on attempt 3
    $this->assertEquals(['SKU-123' => 9.99], $result);
}

public function testCircuitBreakerTripsAfterThreshold(): void {
    $breaker = new CircuitBreaker();
    for ($i = 0; $i < 5; $i++) {
        $breaker->recordFailure();
    }
    $this->assertFalse($breaker->allow());  // Circuit is OPEN
}

public function testFallbackReturnsStaleData(): void {
    $cache = ['prices' => [...], 'cached_at' => time() - 600];
    $result = FallbackStrategy::cacheFallback($cache);
    $this->assertEquals([...], $result);
}
```

---

## IntegrationEngine v7.0 Roadmap

In v7.0, resilience patterns will be built-in via `ResiliencePolicyInterface`:

```php
interface ResiliencePolicyInterface {
    public function shouldRetry(Throwable $e, int $attempt): bool;
    public function getBackoffMs(int $attempt): int;
    public function getFallback(AbstractAction $action, Throwable $e): mixed;
}
```

You'll configure in YAML:
```yaml
integration_engine:
    integrations:
        supplier:
            retry_policy: exponential_backoff
            circuit_breaker: true
            fallback: last_cached_value
```

Until then, implement these patterns as shown in this demo.

---

## References

- **Circuit Breaker:** https://martinfowler.com/bliki/CircuitBreaker.html
- **Exponential Backoff:** https://aws.amazon.com/blogs/architecture/exponential-backoff-and-jitter/
- **Retry Logic:** https://en.wikipedia.org/wiki/Exponential_backoff
- **Bulkhead Pattern:** Related pattern for resource isolation

---

**Generated:** 2026-09-21 | IntegrationEngine Demo — Phase 2: Resilience Patterns
