# 👨‍💼 Admin Dashboard

> **What exists today, and what this document is.**
>
> The admin panel is an EasyAdmin dashboard with a single menu entry and no CRUD
> controllers, because this project has **no Doctrine entities** (see `TASKS.md`,
> T-11). Everything below the Overview table is a **recipe** — how to build these
> features once there are entities to manage — not a description of shipped
> functionality. Code blocks name files you would create; ✅ rows are the only
> claims about the current state.

## Overview

| Feature | Status | Where |
|---------|--------|-------|
| **Dashboard page** | ✅ Built | `src/Controller/Admin/DashboardController.php` — title and one `linkToDashboard` menu item |
| **Authentication** | ✅ Built | `http_basic` + `ROLE_ADMIN` over `^/admin`, `config/packages/security.yaml` |
| **Real-time transport** | ✅ Installed | Mercure hub wired; `src/Controller/MercureUpdateController.php` publishes updates |
| **User management** | 📋 Planned | Needs a `User` entity first |
| **Transaction management** | 📋 Planned | Needs a `Transaction` entity first |
| **Quick stats / analytics** | 📋 Planned | Nothing is rendered on the dashboard today |
| **Audit log** | 📋 Planned | No audit storage or command exists |
| **Role hierarchy / voters** | 📋 Planned | Only the single `ROLE_ADMIN` above is wired |

## Dashboard

### Access

```
http://localhost:8000/admin
```

Credentials come from `ADMIN_PASSWORD_HASH`; the username is `admin`. See
[DEPLOYMENT.md](DEPLOYMENT.md) for generating a hash and overriding it in
production.

### What it renders today

The dashboard sets a title and one menu item. There are no widgets, counters or
CRUD sections — adding them is what the rest of this document describes.

## 📋 User Management

Not built. There is no `User` entity and no CRUD controller. What follows is the
recipe for adding one.

### Features it would provide

- Create/Edit/Delete users
- Assign roles (ADMIN, USER)
- Change passwords securely
- View login history
- Disable/enable accounts
- Bulk actions

### Setup

```bash
# Create User entity
php bin/console make:entity User

# Add password field
php bin/console make:user

# Generate CRUD
php bin/console make:crud --entity=App\\Entity\\User

# Migrate
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

### Fields

| Field | Type | Purpose |
|-------|------|---------|
| `id` | UUID | Unique identifier |
| `email` | Email | Login username |
| `username` | String | Display name |
| `password` | Password | Hashed password |
| `roles` | Array | ROLE_ADMIN, ROLE_USER |
| `createdAt` | DateTime | Account creation |
| `lastLogin` | DateTime | Last login time |
| `isActive` | Boolean | Account status |

## 📋 Transaction Management

Not built. There is no `Transaction` entity. The Mercure transport below is real
and already publishes; what is missing is anything to persist or render.

### Features it would provide

- View all transactions
- Filter by status/date/amount
- Export to CSV
- Search by payment ID
- View transaction details
- Real-time updates (Mercure)

### Setup

```bash
php bin/console make:entity Transaction
php bin/console make:crud --entity=App\\Entity\\Transaction
```

### Fields

| Field | Type | Purpose |
|-------|------|---------|
| `id` | UUID | Transaction ID |
| `paymentIntentId` | String | Stripe ID |
| `amount` | Money | Amount in cents |
| `currency` | String | USD, EUR, etc. |
| `status` | Enum | succeeded, pending, failed |
| `user` | User | Who made transaction |
| `createdAt` | DateTime | Transaction time |
| `metadata` | JSON | Custom data |

### Real-time Updates

New transactions appear instantly via Mercure WebSockets:

```php
// Listen for updates on admin page
const es = new EventSource('/.well-known/mercure?topic=admin/transactions');
es.onmessage = (event) => {
    const tx = JSON.parse(event.data);
    // Refresh table row, play notification sound, etc.
};
```

## 📋 Payment Analytics

Not built — the dashboard renders no widgets today.

### Widgets it would provide

- 💰 Total revenue (today, this month)
- 📊 Transaction count (success rate)
- 🌍 Revenue by country
- 📈 Trending products
- 🔴 Failed payments

### Stripe Integration

Monitor payments in real-time:

```php
// Webhook receives Stripe events
// Published to admin via Mercure
// Dashboard updates in real-time
```

### Reports

Neither command exists yet. They would be invoked like this:

```bash
php bin/console export:transactions --format=csv
php bin/console export:users --start-date=2024-01-01
```

## 📋 User Activity Log

Not built — nothing records an audit trail.

### Events it would record

- 👤 User login/logout
- ✏️ Data changes (who, what, when)
- 📄 File uploads
- 🔐 Permission changes
- ⚠️ Failed login attempts

### Viewing Logs

Nothing records an audit trail today. A console command to read one would live
at `src/Command/ViewAuditLogCommand.php` and be invoked like this — **the file
does not exist yet**:

```bash
php bin/console audit:log --user=john@example.com --limit=50
```

## 📋 Notifications

Not built. The Mercure transport exists, but nothing publishes these topics.

### Notifications it would deliver

- Payment received
- Transaction failed
- New user signup
- System alerts

Delivered via:
- **Real-time**: Mercure WebSockets
- **Email**: Background jobs
- **Database**: Audit log

### Subscribing to Notifications

```html
<script>
// Real-time notifications
const es = new EventSource('/.well-known/mercure?topic=admin/notifications');
es.onmessage = (event) => {
    const notification = JSON.parse(event.data);
    showNotificationToast(notification);
};
</script>
```

## Settings & Configuration

### 📋 Admin Settings

There is no `/admin/settings` route. A settings screen would manage:

- Site name & branding
- Security policies
- Email settings
- Database backups
- API integrations

### ✅ API Configuration

Integrations are declared under `integrations:`, each with its own YAML action
file. Credentials travel as headers or through a custom client service, not as
top-level `token:` keys:

```yaml
# config/packages/integration_engine.yaml
integration_engine:
    integrations:
        tmdb:
            base_url: 'https://api.themoviedb.org'
            headers:
                Authorization: 'Bearer %env(TMDB_ACCESS_TOKEN)%'
            config_path: '%kernel.project_dir%/src/Integrations/Tmdb/Tmdb.yaml'
            middlewares:
                - app.middleware.rate_limit
        stripe:
            client_service: app.client.stripe
            config_path: '%kernel.project_dir%/src/Integrations/Stripe/Stripe.yaml'
```

Mercure is configured separately, by `symfony/mercure-bundle`.

## Permissions

### ✅ What is wired today

One in-memory user with `ROLE_ADMIN`, authenticated over HTTP Basic, guarding
`/admin` and the Mercure publish endpoints. This is the whole of it:

```yaml
# config/packages/security.yaml
security:
    providers:
        users_in_memory:
            memory:
                users:
                    admin:
                        password: '%env(ADMIN_PASSWORD_HASH)%'
                        roles: ['ROLE_ADMIN']

    firewalls:
        main:
            lazy: true
            provider: users_in_memory
            http_basic: ~

    access_control:
        - { path: ^/webhook, roles: PUBLIC_ACCESS }
        - { path: ^/admin, roles: ROLE_ADMIN }
        - { path: ^/api/mercure, roles: ROLE_ADMIN }
```

`^/webhook` is pinned to `PUBLIC_ACCESS` on purpose: inbound webhooks
authenticate by HMAC signature, so demanding credentials would break them.

Covered by `tests/Security/AdminAccessControlTest.php`.

### 📋 Planned: role hierarchy and voters

Neither exists yet. There is no `ROLE_MODERATOR`, no `ROLE_USER` and no voter
class. A richer setup would look like this:

```
ROLE_ADMIN          (Full access)
├─ ROLE_MODERATOR   (View all, edit limited)
└─ ROLE_USER        (View own data only)
```

```php
// src/Security/AdminVoter.php — does not exist yet
public function vote(TokenInterface $token, $subject, array $attributes): int
{
    if ('VIEW_TRANSACTIONS' === $attributes[0]) {
        return $this->isGranted('ROLE_ADMIN') ? self::ACCESS_GRANTED : self::ACCESS_DENIED;
    }
}
```

## Performance & Optimization

### Database Indexing

There are no entities to index yet. Once a `Transaction` entity exists at
`src/Entity/Transaction.php`, it would carry its indexes like this:

```php
#[ORM\Index(columns: ['status', 'createdAt'])]
class Transaction
{
    // ...
}
```

### Caching

```php
// Cache transaction statistics
$cache->remember('transaction_stats', 3600, function () {
    return $this->getTransactionStatistics();
});
```

### Pagination

EasyAdmin defaults to 15 items per page. Adjust:

```php
public function configureCrud(Crud $crud): Crud
{
    return $crud->setPaginatorPageSize(50);
}
```

## Customization Examples

### Add Custom Action

```php
class TransactionCrudController extends AbstractCrudController
{
    public function configureActions(Actions $actions): Actions
    {
        $refundAction = Action::new('refund')
            ->linkToCrudAction('refund')
            ->setLabel('Refund');
            
        return $actions->add(Crud::PAGE_DETAIL, $refundAction);
    }
    
    public function refund(AdminContext $context)
    {
        $transaction = $context->getEntity();
        $this->stripeService->refund($transaction);
        
        return $this->redirect($context->getReferrer());
    }
}
```

### Add Custom Dashboard Widget

```php
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        $stats = [
            'total_revenue' => $this->getTotalRevenue(),
            'transaction_count' => $this->getTransactionCount(),
            'success_rate' => $this->getSuccessRate(),
        ];
        
        return $this->render('admin/dashboard.html.twig', ['stats' => $stats]);
    }
}
```

### Add Custom Filter

```php
public function configureFilters(Filters $filters): Filters
{
    return $filters
        ->add('createdAt', EntityFilter::new())
        ->add('status', ChoiceFilter::new()
            ->setChoices([
                'Succeeded' => 'succeeded',
                'Failed' => 'failed',
            ]))
        ->add('user');
}
```

## Mobile Responsive

Admin dashboard is fully responsive:
- ✅ Desktop (1920px+)
- ✅ Tablet (768px - 1920px)
- ✅ Mobile (< 768px)

Try on mobile:
```
http://localhost:8000/admin
```

## Backup & Recovery

### 📋 Automated Backups

Neither command exists. A scheduled-backup feature would expose them like this:

```bash
php bin/console backup:create --schedule=daily
php bin/console backup:list
```

### Manual Backup

```bash
# Export database
php bin/console doctrine:migrations:dump-schema

# Export configuration
tar czf backup.tar.gz config/ templates/
```

## Support & Troubleshooting

### Common Issues

**Admin page shows 401 Unauthorized**

The firewall uses HTTP Basic, so an unauthenticated request gets `401`, not
`403`. The browser should prompt for credentials.

- Username is `admin`; the password must match `ADMIN_PASSWORD_HASH`
- Regenerate the hash: `php bin/console security:hash-password 'your-password'`
- If the app refuses to boot, `ADMIN_PASSWORD_HASH` is missing from the
  environment — it has no default on purpose
- Clear cache: `php bin/console cache:clear`

**Real-time updates not working**
- ✅ Check Mercure server running on port 3000
- ✅ Verify MERCURE_PUBLIC_URL correct
- ✅ Check browser console for errors

**Slow dashboard**
- ✅ Enable query logging: `QUERY_DEBUG=true`
- ✅ Add database indexes
- ✅ Reduce page size: `setPaginatorPageSize(15)`

## Next Steps

1. **Create User Entity**: `make:entity User`
2. **Setup Authentication**: `make:auth`
3. **Generate CRUD**: `make:crud User`
4. **Configure Roles**: Security.yaml
5. **Test Real-time**: Open Mercure demo
6. **Customize Dashboard**: Add widgets & charts

## Resources

- **EasyAdmin Docs**: https://symfony.com/bundles/EasyAdminBundle/current/
- **Symfony Security**: https://symfony.com/doc/current/security.html
- **Mercure Real-time**: https://symfony.com/doc/current/mercure.html

---

*Status claims in this document last verified against the code on 2026-09-21, at `d67f899`.*
*Enforced for file paths by `tests/Documentation/DocumentedPathsExistTest.php`.*
