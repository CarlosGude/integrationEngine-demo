# 👨‍💼 Admin Dashboard Features

Comprehensive admin interface for managing IntegrationEngine Demo.

## Overview

| Feature | Status | Location |
|---------|--------|----------|
| **Dashboard** | ✅ Ready | `/admin` |
| **User Management** | 🔄 Ready to setup | `src/Controller/Admin/UserCrudController.php` |
| **Transaction Management** | 🔄 Ready to setup | `src/Controller/Admin/TransactionCrudController.php` |
| **Real-time Updates** | ✅ Installed | Mercure WebSockets |
| **Analytics** | 🔄 Optional | Charts & statistics |
| **Permissions** | ✅ Built-in | Role-based access |

## Dashboard

### Access

```
http://localhost:8000/admin
```

### Quick Stats

- **Users**: Total count, recent signups
- **Transactions**: Daily totals, success rate
- **Payments**: Stripe integration status
- **API Health**: External service status

## User Management

### Features

- ✅ Create/Edit/Delete users
- ✅ Assign roles (ADMIN, USER)
- ✅ Change passwords securely
- ✅ View login history
- ✅ Disable/enable accounts
- ✅ Bulk actions

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

## Transaction Management

### Features

- ✅ View all transactions
- ✅ Filter by status/date/amount
- ✅ Export to CSV
- ✅ Search by payment ID
- ✅ View transaction details
- ✅ Real-time updates (Mercure)

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

## Payment Analytics

### Dashboard Widgets

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

Export data:

```bash
# Export transactions to CSV
php bin/console export:transactions --format=csv

# Export users to CSV
php bin/console export:users --start-date=2024-01-01
```

## User Activity Log

### Audit Trail

- 👤 User login/logout
- ✏️ Data changes (who, what, when)
- 📄 File uploads
- 🔐 Permission changes
- ⚠️ Failed login attempts

### Viewing Logs

```php
// src/Command/ViewAuditLogCommand.php
php bin/console audit:log --user=john@example.com --limit=50
```

## Notifications

### In-Admin Notifications

- ✅ Payment received
- ⚠️ Transaction failed
- 📧 New user signup
- 🔔 System alerts

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

### Admin Settings

```
/admin/settings
```

Manage:
- 🌐 Site name & branding
- 🔐 Security policies
- 📧 Email settings
- 💾 Database backups
- 🔄 API integrations

### API Configuration

```yaml
# config/packages/integration_engine.yaml
integration_engine:
    tmdb:
        base_url: '%env(TMDB_BASE_URL)%'
        token: '%env(TMDB_ACCESS_TOKEN)%'
    stripe:
        secret_key: '%env(STRIPE_SECRET_KEY)%'
        webhook_secret: '%env(STRIPE_WEBHOOK_SECRET)%'
    mercure:
        url: '%env(MERCURE_PUBLIC_URL)%'
        jwt_secret: '%env(MERCURE_JWT_SECRET)%'
```

## Permissions

### Role Hierarchy

```
ROLE_ADMIN          (Full access)
├─ ROLE_MODERATOR   (View all, edit limited)
└─ ROLE_USER        (View own data only)
```

### Access Control

```php
// src/Security/AdminVoter.php
public function vote(TokenInterface $token, $subject, array $attributes): int
{
    if ('VIEW_TRANSACTIONS' === $attributes[0]) {
        return $this->isGranted('ROLE_ADMIN') ? self::ACCESS_GRANTED : self::ACCESS_DENIED;
    }
}
```

### Configuring Permissions

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/admin, roles: ROLE_ADMIN }
        - { path: ^/api/admin, roles: ROLE_ADMIN }
        - { path: ^/admin/reports, roles: ROLE_MODERATOR }
```

## Performance & Optimization

### Database Indexing

```php
// src/Entity/Transaction.php
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

### Automated Backups

```bash
# Daily backups
php bin/console backup:create --schedule=daily

# View backups
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

**Admin page shows 403 Forbidden**
- ✅ Check user has ROLE_ADMIN
- ✅ Verify security.yaml config
- ✅ Clear cache: `php bin/console cache:clear`

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
