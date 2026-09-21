# 🔌 Building Custom Protocol Adapters

**IntegrationEngine** — How to integrate non-standard APIs and data formats

---

## Overview

IntegrationEngine provides built-in adapters for REST (JSON) and GraphQL. But what if your supplier sends CSV? Or you need to integrate with an XML-based API? Or a custom binary protocol?

This guide shows how to build custom adapters for your domain-specific needs.

---

## Adapter Pattern

Every adapter implements `ClientAdapterInterface`:

```php
interface ClientAdapterInterface {
    public function send(
        AbstractAction $action,
        ?ActionContextInterface $context = null,
        ?RequestHeadersInterface $headers = null,
    ): array;  // Returns ['body' => array, 'headers' => array]
    
    public static function getClientType(): string;
    public static function requiresPath(): bool;
    public static function requiresMethod(): bool;
    public function withBaseUrl(string $baseUrl): static;
}
```

### Step 1: Create the Adapter Class

```php
namespace App\YourIntegration\Client;

use IntegrationEngine\Core\Contract\Client\ClientAdapterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class YourCustomAdapter implements ClientAdapterInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
        private array $defaultHeaders = [],
    ) {}
    
    public static function getClientType(): string {
        return 'your_protocol';
    }
    
    public static function requiresPath(): bool { return true; }
    public static function requiresMethod(): bool { return true; }
    public function withBaseUrl(string $baseUrl): static {
        return new self($this->httpClient, $baseUrl, $this->defaultHeaders);
    }
    
    public function send(
        AbstractAction $action,
        ?ActionContextInterface $context = null,
        ?RequestHeadersInterface $headers = null,
    ): array {
        // Your protocol-specific logic here
        // Must return ['body' => array, 'headers' => array]
    }
}
```

### Step 2: Register in Services

```yaml
# config/services.yaml
services:
    app.client.your_protocol:
        class: App\YourIntegration\Client\YourCustomAdapter
        arguments:
            $httpClient: '@http_client'
            $baseUrl: 'https://api.yourservice.com'
            $defaultHeaders:
                Authorization: 'Bearer %env(YOUR_TOKEN)%'
```

### Step 3: Use in Integration Config

```yaml
# src/YourIntegration/YourIntegration.yaml
get_data:
    action: App\YourIntegration\Actions\GetDataAction
    method: GET
    path: /data/{id}
    mapper: App\YourIntegration\Mappers\GetDataMapper
```

```yaml
# config/packages/integration_engine.yaml
integration_engine:
    integrations:
        your_integration:
            client_service: app.client.your_protocol
            config_path: '%kernel.project_dir%/src/YourIntegration/YourIntegration.yaml'
```

---

## Real-World Examples

### Example 1: CSV Adapter (Supplier API)

**Problem:** Supplier API returns CSV data, not JSON.

**Solution:**

```php
namespace App\Integrations\Supplier\Client;

use IntegrationEngine\Core\Contract\Client\ClientAdapterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class CsvClientAdapter implements ClientAdapterInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
        private array $defaultHeaders = [],
    ) {}
    
    public static function getClientType(): string {
        return 'csv';
    }
    
    public static function requiresPath(): bool { return true; }
    public static function requiresMethod(): bool { return true; }
    public function withBaseUrl(string $baseUrl): static {
        return new self($this->httpClient, $baseUrl, $this->defaultHeaders);
    }
    
    public function send(
        AbstractAction $action,
        ?ActionContextInterface $context = null,
        ?RequestHeadersInterface $headers = null,
    ): array {
        // 1. Make HTTP request like normal
        $response = $this->httpClient->request(
            $action->getMethod(),
            $this->baseUrl . $action->getPath($context),
            ['headers' => $this->defaultHeaders],
        );
        
        // 2. Get raw CSV content
        $csvContent = $response->getContent();
        
        // 3. Parse CSV into array (using engine's utility in v7.0+)
        // For now: inline parsing or use external library
        $rows = $this->parseCSV($csvContent);
        
        // 4. Return in standard adapter format
        return [
            'body' => $rows,  // array of arrays
            'headers' => $response->getHeaders(),
        ];
    }
    
    private function parseCSV(string $content): array {
        // Parse CSV → array of associative arrays
        // See RESILIENCE-PATTERNS.md for implementation
        $lines = explode("\n", trim($content));
        $headers = str_getcsv(array_shift($lines));
        $rows = [];
        
        foreach ($lines as $line) {
            if (trim($line)) {
                $values = str_getcsv($line);
                $rows[] = array_combine($headers, $values);
            }
        }
        
        return $rows;
    }
}
```

**Usage:**
```php
class GetPricesMapper extends AbstractMapper {
    protected static function transform($action, $response, $headers) {
        $rows = $response['body'];  // Already parsed CSV
        return new GetPricesResponse($rows);
    }
}
```

### Example 2: XML Adapter (Legacy API)

**Problem:** Legacy backend returns XML, not JSON.

```php
final readonly class XmlClientAdapter implements ClientAdapterInterface
{
    public static function getClientType(): string { return 'xml'; }
    
    public function send(
        AbstractAction $action,
        ?ActionContextInterface $context = null,
        ?RequestHeadersInterface $headers = null,
    ): array {
        $response = $this->httpClient->request(...);
        $xml = new \SimpleXMLElement($response->getContent());
        
        // Convert XML to nested arrays
        $body = json_decode(json_encode($xml), true);
        
        return ['body' => $body, 'headers' => $response->getHeaders()];
    }
}
```

### Example 3: Form-Encoded Adapter (Stripe)

**Problem:** Stripe requires `application/x-www-form-urlencoded` bodies, not JSON.

```php
// This pattern is built-in to engine v7.0+
// Until then, here's how to build it:

final readonly class FormEncodedClientAdapter implements ClientAdapterInterface
{
    public static function getClientType(): string { return 'form_encoded'; }
    
    public function send(
        AbstractAction $action,
        ?ActionContextInterface $context = null,
        ?RequestHeadersInterface $headers = null,
    ): array {
        $path = $action->getPath($context);
        $method = $action->getMethod();
        $options = [
            'headers' => array_merge(
                $this->defaultHeaders,
                $headers?->toArray() ?? [],
            ),
        ];
        
        // Key difference: form-encode the body
        $body = $action->getBody();
        if ($body !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $options['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
            $options['body'] = http_build_query($body->toArray());
        }
        
        $response = $this->httpClient->request($method, $this->baseUrl.$path, $options);
        return [
            'body' => $response->toArray(),
            'headers' => $response->getHeaders(),
        ];
    }
}
```

### Example 4: Protocol Buffers (High-Performance Binary)

```php
final readonly class ProtobufClientAdapter implements ClientAdapterInterface
{
    public function send(...): array {
        // 1. Serialize action body to protobuf binary
        $protoMessage = $action->getBody()->toProtobuf();
        
        // 2. Send binary data
        $response = $this->httpClient->request(
            'POST',
            $this->baseUrl . $action->getPath($context),
            [
                'headers' => ['Content-Type' => 'application/protobuf'],
                'body' => $protoMessage,
            ],
        );
        
        // 3. Deserialize protobuf response
        $message = MyMessage::deserialize($response->getContent());
        
        return [
            'body' => (array) $message,
            'headers' => $response->getHeaders(),
        ];
    }
}
```

---

## Testing Custom Adapters

### Unit Test Template

```php
class YourCustomAdapterTest extends TestCase {
    private YourCustomAdapter $adapter;
    private MockHttpClient $httpClient;
    
    protected function setUp(): void {
        $this->httpClient = new MockHttpClient();
        $this->adapter = new YourCustomAdapter(
            $this->httpClient,
            'https://api.example.com',
        );
    }
    
    public function testSendParsesCustomFormat(): void {
        // Arrange
        $action = $this->createMock(AbstractAction::class);
        $action->method('getPath')->willReturn('/data/123');
        $action->method('getMethod')->willReturn('GET');
        
        $this->httpClient->mock([
            new Response(200, body: 'your,custom,format\nrow1,row2,row3'),
        ]);
        
        // Act
        $result = $this->adapter->send($action);
        
        // Assert
        $this->assertEquals(['your', 'custom', 'format'], array_keys($result['body'][0]));
        $this->assertEquals('row1', $result['body'][0]['your']);
    }
}
```

---

## Common Patterns

### Pattern 1: Normalization
Convert non-standard formats to standard internal format:

```php
public function send(...): array {
    $raw = $this->httpClient->request(...);
    $normalized = $this->normalize($raw->getContent());
    return ['body' => $normalized, 'headers' => $raw->getHeaders()];
}

private function normalize(string $content): array {
    // Protocol-specific parsing
    // Return as standard nested arrays
}
```

### Pattern 2: Authentication Override
Some protocols have non-standard auth (API keys in headers, custom JWT, etc.):

```php
private function buildHeaders(?RequestHeadersInterface $requestHeaders): array {
    $headers = $this->defaultHeaders;
    
    // Custom auth for this protocol
    if ($this->apiKey) {
        $headers['X-API-Key'] = $this->apiKey;
    }
    
    return array_merge($headers, $requestHeaders?->toArray() ?? []);
}
```

### Pattern 3: Retry & Timeout Override
Some protocols need protocol-specific retry logic:

```php
public function send(...): array {
    $maxAttempts = 3;
    $backoff = 100; // ms
    
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        try {
            return $this->doSend(...);
        } catch (TransportException $e) {
            if ($attempt < $maxAttempts && $this->isTransient($e)) {
                usleep($backoff * (2 ** ($attempt - 1)) * 1000);
                continue;
            }
            throw $e;
        }
    }
}
```

---

## IntegrationEngine v7.0: Built-in Adapters

In v7.0, these adapters will be built-in:

- ✅ `RestJsonClientAdapter` (exists)
- ✅ `GraphQLClientAdapter` (exists)
- ✨ `FormEncodedClientAdapter` (v7.0)
- ✨ `CsvClientAdapter` (v7.0, but see note below)
- 📋 `XmlClientAdapter` (roadmap)

### Note on CSV

CSV is **not a protocol** — it's a data format. So in v7.0:

- Engine provides `CsvParser` utility class in `IntegrationEngine\Utils`
- You build a `CsvClientAdapter` in your project using the utility
- This keeps the engine focused on protocols, not domain-specific formats

Example (v7.0+):
```php
use IntegrationEngine\Utils\CsvParser;

class SupplierCsvClientAdapter implements ClientAdapterInterface {
    public function send(...): array {
        $response = $this->httpClient->request(...);
        $rows = CsvParser::parse($response->getContent());
        return ['body' => $rows, 'headers' => $response->getHeaders()];
    }
}
```

---

## Migration Path

### Today (v6.0.0)
- Build custom adapters in your project
- See: `src/Billing/Infrastructure/Http/StripeFormClientAdapter.php`
- See: CSV parsing in `src/Integrations/Supplier/Mappers/GetPricesMapper.php`

### Tomorrow (v7.0.0)
- Use built-in `FormEncodedClientAdapter`
- Use `CsvParser` utility
- Delete custom implementations

### Migration Example

**Before (Today):**
```php
// 129 lines in StripeFormClientAdapter
// 35 lines of CSV parsing in mapper
// Total: ~164 lines of custom code
```

**After (v7.0):**
```yaml
# config/services.yaml
stripe:
    client_service: integration_engine.client.form_encoded

# src/Integrations/Supplier/Mappers/GetPricesMapper.php
use IntegrationEngine\Utils\CsvParser;

$rows = CsvParser::parse($response['body']);
// 1 line instead of 35
```

**Savings:** 163 lines removed, no functionality lost.

---

## Production Checklist

- [ ] Adapter implements `ClientAdapterInterface` correctly
- [ ] `send()` returns `['body' => array, 'headers' => array]`
- [ ] Error handling throws `RequestResponseException`
- [ ] Network errors caught and wrapped as `RequestResponseException`
- [ ] Default headers merged with request headers
- [ ] Unit tests cover happy path and error cases
- [ ] Integration tests verify end-to-end with real API (or mock)
- [ ] Adapter is registered in `services.yaml`
- [ ] Integration config references correct `client_service`
- [ ] Mapper correctly handles parsed body

---

## References

- **IntegrationEngine Docs:** https://github.com/carlosgude/integrationEngine
- **Action-Mapper-Response Pattern:** See ARCHITECTURE.md
- **Resilience Patterns:** See RESILIENCE-PATTERNS.md
- **Chaos Testing:** See CHAOS-TESTING.md

---

**Generated:** 2026-09-21 | IntegrationEngine Demo — Custom Adapter Patterns
