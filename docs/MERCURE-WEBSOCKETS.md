# 📡 Mercure & WebSockets Guide

Real-time bidirectional communication using Mercure protocol and WebSockets.

## Installation Status

✅ **Installed:** 
- `symfony/mercure` (dev-main)
- `symfony/mercure-bundle` (dev-main)
- `lcobucci/jwt` v5.6.0 (JWT signing)

## What Is Mercure?

**Mercure** is a protocol for publishing real-time updates:
- 📡 Server-Sent Events (SSE) based
- 🔐 JWT-authenticated subscriptions
- 🌐 Topic-based pub/sub
- ⚡ Efficient & scalable
- 🔄 Automatic reconnection

## Architecture

```
┌─────────────────────────────────────────────┐
│  Browser (Multiple Clients)                 │
├─────────────────────────────────────────────┤
│  EventSource (Subscribe to topics)          │
│  ↓                                          │
│  /.well-known/mercure?topic=admin/updates   │
└────────────┬────────────────────────────────┘
             │
       HTTP/SSE Connection
             │
┌────────────▼────────────────────────────────┐
│  Mercure Hub Server (Docker)                │
│  ├─ Topic routing                           │
│  ├─ JWT validation                          │
│  └─ Message broadcasting                    │
└────────────┬────────────────────────────────┘
             │
       HTTP/JSON Updates
             │
┌────────────▼────────────────────────────────┐
│  Symfony Backend                            │
├─────────────────────────────────────────────┤
│  HubInterface::publish(Update)              │
│  ├─ Transaction updates                     │
│  ├─ Payment notifications                   │
│  └─ System alerts                           │
└─────────────────────────────────────────────┘
```

## Installation & Setup

### 1. Environment Variables

Already configured in `.env`:

```env
# Backend Mercure URL
MERCURE_URL=http://localhost:3000/.well-known/mercure

# Frontend Mercure URL
MERCURE_PUBLIC_URL=http://localhost:3000/.well-known/mercure

# JWT Secret (change in production!)
MERCURE_JWT_SECRET=dev-secret-key
```

### 2. Start Mercure Server

**Option A: Docker (Recommended)**

```bash
docker run -d \
  --name mercure \
  -p 3000:80 \
  -e MERCURE_PUBLISHER_JWT_SECRET=dev-secret-key \
  -e MERCURE_SUBSCRIBER_JWT_SECRET=dev-secret-key \
  dunglas/mercure:latest
```

**Option B: Docker Compose**

Add to `docker-compose.yml`:

```yaml
services:
  mercure:
    image: dunglas/mercure:latest
    ports:
      - "3000:80"
    environment:
      MERCURE_PUBLISHER_JWT_SECRET: dev-secret-key
      MERCURE_SUBSCRIBER_JWT_SECRET: dev-secret-key
      MERCURE_EXTRA_DIRECTIVES: |
        cors_origins *
    volumes:
      - /tmp/mercure:/data
```

**Option C: Binary**

```bash
# Download from https://github.com/dunglas/mercure/releases
wget https://github.com/dunglas/mercure/releases/download/v0.5.3/mercure-linux-amd64
chmod +x mercure-linux-amd64
./mercure-linux-amd64
```

### 3. Verify Connection

```bash
# Check if Mercure is running
curl http://localhost:3000/.well-known/mercure
```

## Publishing Updates

### Method 1: CLI Command

```bash
# Publish a simple message
php bin/console mercure:publish "admin/updates" '{"type":"info","message":"Hello!"}'

# Publish with repeat and delay
php bin/console mercure:publish "admin/payments" \
  '{"status":"succeeded","amount":99.99}' \
  --repeat=5 --delay=2

# Real transaction update
php bin/console mercure:publish "admin/transactions" '{
  "id": "txn_123",
  "amount": 99.99,
  "currency": "USD",
  "status": "succeeded"
}'
```

### Method 2: HTTP API

```bash
curl -X POST http://localhost:8000/api/mercure/publish \
  -H "Content-Type: application/json" \
  -d '{
    "topic": "admin/updates",
    "message": {
      "type": "notification",
      "text": "Payment processed"
    }
  }'
```

### Method 3: In PHP Code

```php
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class PaymentService {
    public function __construct(private HubInterface $hub) {}
    
    public function processPayment($data) {
        // ... payment logic ...
        
        // Publish real-time update
        $this->hub->publish(new Update(
            topic: 'admin/payments',
            data: json_encode([
                'action' => 'payment_processed',
                'amount' => $data['amount'],
                'status' => 'succeeded',
                'timestamp' => date('c'),
            ])
        ));
    }
}
```

## Subscribing to Updates

### Basic Subscription (JavaScript)

```javascript
// Connect to Mercure hub
const eventSource = new EventSource(
    'http://localhost:3000/.well-known/mercure?topic=admin/updates'
);

// Listen for messages
eventSource.onmessage = (event) => {
    const data = JSON.parse(event.data);
    console.log('Received:', data);
    updateUI(data);
};

// Handle errors
eventSource.onerror = (error) => {
    console.error('Connection error:', error);
};
```

### Multiple Topics

```javascript
const url = new URL('http://localhost:3000/.well-known/mercure');
url.searchParams.append('topic', 'admin/transactions');
url.searchParams.append('topic', 'admin/payments');
url.searchParams.append('topic', 'admin/updates');

const eventSource = new EventSource(url);
eventSource.onmessage = (event) => {
    const update = JSON.parse(event.data);
    handleUpdate(update);
};
```

### Wildcard Topics

```javascript
// Subscribe to all admin topics
const url = new URL('http://localhost:3000/.well-known/mercure');
url.searchParams.append('topic', 'admin/{id}');

const eventSource = new EventSource(url);
```

## Real-world Examples

### 1. Transaction Dashboard

**Backend (Publish):**

No such listener exists yet — updates are published directly from
`src/Controller/MercureUpdateController.php`. A listener would live at
`src/EventListener/TransactionListener.php` and look like this:

```php
class TransactionListener {
    public function __construct(private HubInterface $hub) {}
    
    public function onTransactionCreated(TransactionCreatedEvent $event) {
        $transaction = $event->getTransaction();
        
        $this->hub->publish(new Update(
            topic: 'admin/transactions',
            data: json_encode([
                'action' => 'new_transaction',
                'id' => $transaction->getId(),
                'amount' => $transaction->getAmount(),
                'status' => $transaction->getStatus(),
                'timestamp' => date('c'),
            ])
        ));
    }
}
```

**Frontend (Subscribe):**

```html
<div id="transactions">
    <!-- Updates appear here -->
</div>

<script>
const eventSource = new EventSource(
    '/.well-known/mercure?topic=admin/transactions'
);

eventSource.onmessage = (event) => {
    const tx = JSON.parse(event.data);
    addTransactionRow(tx);
};
</script>
```

### 2. Stripe Webhook Updates

```php
// Receive Stripe webhook
// Publish to admin dashboard in real-time

class StripeWebhookListener {
    public function __construct(private HubInterface $hub) {}
    
    public function onPaymentSucceeded(PaymentIntentEvent $event) {
        $this->hub->publish(new Update(
            topic: 'admin/payments',
            data: json_encode([
                'action' => 'payment_succeeded',
                'paymentId' => $event->paymentIntentId,
                'amount' => $event->amount,
                'status' => 'success',
                'timestamp' => date('c'),
            ])
        ));
    }
}
```

### 3. User Activity Feed

```javascript
// Real-time notification of user actions

const eventSource = new EventSource(
    '/.well-known/mercure?topic=activity/user/{userId}'
);

eventSource.onmessage = (event) => {
    const activity = JSON.parse(event.data);
    
    // Show notification
    showNotification({
        title: activity.action,
        message: activity.message,
        type: activity.type, // 'success', 'error', 'info'
    });
};
```

## Interactive Demo

Visit `http://localhost:8000/mercure-demo.html` for:
- ✅ Real-time event listener
- 📊 Statistics dashboard
- 🎯 One-click publishers
- 🧪 Test different topics

## Topics Reference

| Topic | Purpose | Example Payload |
|-------|---------|-----------------|
| `admin/updates` | General notifications | `{type:"info", message:"..."}` |
| `admin/transactions` | Transaction events | `{action:"new", amount:99.99}` |
| `admin/payments` | Payment updates | `{status:"succeeded", id:"..."}` |
| `admin/users` | User activity | `{action:"login", userId:"..."}` |
| `admin/{id}` | Specific resource | `{resource:"tx_123", data:{...}}` |
| `broadcast/{channel}` | Chat/messaging | `{from:"user", text:"..."}` |

## Security

### JWT Secret

The `MERCURE_JWT_SECRET` must be:
- ✅ Strong (random 32+ characters)
- ✅ Same for publisher & subscriber
- ✅ Kept secret in production

Generate:
```bash
openssl rand -base64 32
```

### Topic Authorization

Restrict access to sensitive topics:

```php
// Only authenticated users can publish
if (!$this->isGranted('ROLE_ADMIN')) {
    throw new AccessDeniedException('Admin required');
}
```

### CORS Setup

For cross-origin requests:

```yaml
# config/packages/mercure.yaml
mercure:
    hubs:
        default:
            url: '%env(MERCURE_URL)%'
            public_url: '%env(MERCURE_PUBLIC_URL)%'
            jwt:
                secret: '%env(MERCURE_JWT_SECRET)%'
            # Add CORS headers
            extra_directives: |
                cors_origins *
```

## Performance Tips

### 1. Efficient Message Size

```php
// ✅ Good: Minimal payload
$hub->publish(new Update(
    topic: 'admin/transactions',
    data: json_encode(['id' => $tx->getId(), 'status' => 'done'])
));

// ❌ Avoid: Large payload
$hub->publish(new Update(
    topic: 'admin/transactions',
    data: json_encode($tx->toArray()) // Full entity data
));
```

### 2. Topic Strategies

```php
// ✅ Specific topics
$hub->publish(new Update(topic: 'user/123/messages', ...));

// ❌ Broad topics (slower)
$hub->publish(new Update(topic: 'broadcast/*', ...));
```

### 3. Batching

```php
// Publish multiple updates efficiently
$updates = [];
foreach ($transactions as $tx) {
    $updates[] = new Update(
        topic: 'admin/transactions',
        data: json_encode(['id' => $tx->getId()])
    );
}

foreach ($updates as $update) {
    $hub->publish($update);
}
```

## Troubleshooting

### "Connection refused" Error
```
Error: Failed to fetch from Mercure
```
✅ **Solution:** Make sure Mercure server is running on port 3000

### "No updates received"
```
EventSource shows no messages
```
✅ **Solutions:**
- Check browser console for errors
- Verify Mercure server logs: `docker logs mercure`
- Ensure topic matches subscription

### "403 Forbidden"
```
JWT validation failed
```
✅ **Solution:** Verify `MERCURE_JWT_SECRET` matches between publisher & Mercure

### CORS Issues
```
No 'Access-Control-Allow-Origin' header
```
✅ **Solution:** Add `cors_origins *` to Mercure config (or restrict to your domain)

## Production Deployment

### 1. Environment Variables

```env
MERCURE_URL=https://your-domain.com/.well-known/mercure
MERCURE_PUBLIC_URL=https://your-domain.com/.well-known/mercure
MERCURE_JWT_SECRET=<strong-random-secret>
```

### 2. HTTPS Required

Use HTTPS in production. Mercure requires secure connections.

### 3. Load Balancing

For high traffic, use Mercure load balancer or managed service.

### 4. Monitoring

```bash
# Watch Mercure logs
docker logs -f mercure

# Check connections
curl http://localhost:3000/.well-known/mercure

# Verify JWT
php bin/console mercure:debug
```

## Resources

- **Mercure Docs**: https://mercure.rocks
- **Symfony Integration**: https://symfony.com/doc/current/mercure.html
- **GitHub**: https://github.com/dunglas/mercure
- **Demo**: http://localhost:8000/mercure-demo.html

## Next Steps

1. ✅ Start Mercure server (Docker)
2. ✅ Verify connection
3. ✅ Test CLI publish command
4. ✅ Open demo page
5. ✅ Integrate with your entities
6. ✅ Add real-time UI updates

---

*Status claims in this document last verified against the code on 2026-09-21, at `d67f899`.*
*Enforced for file paths by `tests/Documentation/DocumentedPathsExistTest.php`.*
