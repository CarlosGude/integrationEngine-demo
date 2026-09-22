# 🛠️ EasyAdmin Setup & Guide

EasyAdmin Bundle v4 provides auto-generated CRUD admin interface for managing data.

## Installation Status

✅ **Installed:** `easycorp/easyadmin-bundle` v4.29.16

## What It Does

- 🎯 Auto-generates admin pages from Doctrine entities
- 🎨 Professional Bootstrap 5 UI out of the box
- 📝 CRUD operations (Create, Read, Update, Delete)
- 🔍 Search, filtering, sorting, pagination
- 👤 User authentication & role-based access
- 📊 Dashboard with charts & analytics
- 📁 File uploads support
- 🔐 CSRF protection built-in

## Quick Setup

### 1. Create a Dashboard Controller

Already created at `src/Controller/Admin/DashboardController.php`:

```php
<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class DashboardController extends AbstractDashboardController
{
    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);
        return $this->redirect($adminUrlGenerator->setController(UserCrudController::class)->generateUrl());
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('IntegrationEngine Admin')
            ->setLocales(['en', 'es']);
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        return UserMenu::new()
            ->setName($user->getUserIdentifier())
            ->setGravatarEmail($user->getEmail() ?? '')
            ->linkToLogoutRoute();
    }
}
```

### 2. Create Entities

```bash
# Create User entity
php bin/console make:entity User

# Create other entities as needed
php bin/console make:entity Transaction
php bin/console make:entity Product
```

### 3. Generate CRUD Controllers

```bash
# For each entity
php bin/console make:crud --entity=App\\Entity\\User UserCrudController
php bin/console make:crud --entity=App\\Entity\\Transaction TransactionCrudController
```

### 4. Configure EasyAdmin (PHP)

EasyAdmin v4 uses PHP-based configuration. Configure your dashboard in `src/Controller/Admin/DashboardController.php`:

```php
public function configureMenuItems(): iterable
{
    yield MenuItem::linkToCrud('Users', 'fas fa-users', User::class);
    yield MenuItem::linkToCrud('Transactions', 'fas fa-exchange', Transaction::class);
}
```

### 5. Access Admin

```
http://localhost:8000/admin
```

## Customization

### Display Fields

```php
public function configureFields(string $pageName): iterable
{
    return [
        IdField::new('id')->hideOnForm(),
        EmailField::new('email'),
        TextField::new('username'),
        DateTimeField::new('createdAt'),
        MoneyField::new('amount')->setNumDecimals(2),
        ChoiceField::new('status')->setChoices([
            'Active' => 'active',
            'Inactive' => 'inactive',
        ]),
        AssociationField::new('user'),
        ImageField::new('avatar')->setBasePath('uploads/avatars'),
    ];
}
```

### Actions & Permissions

```php
public function configureActions(Actions $actions): Actions
{
    return $actions
        ->add(Crud::PAGE_INDEX, Action::DETAIL)
        ->remove(Crud::PAGE_EDIT, Action::DELETE)
        ->disable(Action::NEW, Action::EDIT);
}
```

### Filters

```php
public function configureFilters(Filters $filters): Filters
{
    return $filters
        ->add(EntityFilter::new('user'))
        ->add(DateFilter::new('createdAt'))
        ->add(NumericFilter::new('amount'));
}
```

### Search

```php
public function configureSearchFields(SearchDto $searchDto): array
{
    return ['id', 'email', 'username', 'createdAt'];
}
```

## Security Setup

### Authentication

```bash
# Create User entity with password field
php bin/console make:user

# Create login form
php bin/console make:auth
```

### Role-based Access

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/admin, roles: ROLE_ADMIN }
```

In CRUD controller:

```php
public function configureCrud(Crud $crud): Crud
{
    return $crud
        ->setPageTitle('index', 'User Management')
        ->setDefaultSort(['createdAt' => 'DESC']);
}
```

## Real-world Examples

### Transaction CRUD

```php
class TransactionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Transaction::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('paymentIntentId'),
            MoneyField::new('amount')->setNumDecimals(2)->setStoredAsCents(),
            ChoiceField::new('status')->setChoices([
                'Succeeded' => 'succeeded',
                'Pending' => 'pending',
                'Failed' => 'failed',
            ]),
            DateTimeField::new('createdAt')->hideOnForm(),
            AssociationField::new('user'),
        ];
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'Payment Transactions')
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->remove(Crud::PAGE_INDEX, Action::DELETE);
    }
}
```

### Movie Analytics

```php
class MovieAnalyticsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Movie::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('title'),
            ImageField::new('posterPath')->setBasePath('https://image.tmdb.org/t/p/w200'),
            NumberField::new('voteAverage'),
            TextEditorField::new('overview'),
            DateField::new('releaseDate'),
        ];
    }
}
```

## Themes & Customization

### Custom CSS

```bash
mkdir templates/bundles/EasyAdminBundle/css
```

Create `templates/bundles/EasyAdminBundle/css/custom.css` and reference in config.

### Custom Sidebar

```yaml
easy_admin:
    dashboards:
        admin:
            menu:
                - { label: 'Dashboard', url: '/admin' }
                - { label: 'Users', entity: User }
                - { label: 'Transactions', entity: Transaction }
```

## Dashboard Widgets

Add to DashboardController:

```php
public function configureMenuItems(): iterable
{
    yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
    yield MenuItem::linkToCrud('Users', 'fa fa-user', User::class);
    yield MenuItem::linkToCrud('Transactions', 'fa fa-money', Transaction::class);
    yield MenuItem::section('Reports');
    yield MenuItem::linkToRoute('Analytics', 'fa fa-chart', 'admin_analytics');
}
```

## Performance Tips

1. **Lazy Load Associations**: Use `AssociationField::new()->hideOnIndex()`
2. **Pagination**: Set reasonable page size
3. **Search**: Only index essential fields
4. **Filters**: Avoid expensive queries
5. **Caching**: Cache CRUD metadata

## Troubleshooting

### "Service not found" errors
→ Verify all dependencies are properly injected into your dashboard controller

### Dashboard not loading
→ Verify `DashboardController` extends `AbstractDashboardController` and methods are public

### Authentication required
→ Setup `make:auth` and configure security.yaml

### Missing CRUD actions
→ Run `make:crud` for each entity you want to manage

## Next Steps

1. **Create Your Entities**: Add User, Transaction, Product entities
2. **Setup Authentication**: Use `make:auth` for login
3. **Generate CRUD Pages**: One command per entity
4. **Customize Fields**: Show/hide, validate, format fields
5. **Add Permissions**: Role-based access control

## Resources

- **Docs**: https://symfony.com/bundles/EasyAdminBundle/current/
- **Recipes**: https://github.com/symfony/recipes
- **Tutorial**: https://github.com/EasyCorp/EasyAdminBundle

## Integration with Mercure

Real-time updates when records change:

```php
// In CRUD controller
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

public function persistEntity(EntityManagerInterface $em, $entity): void
{
    parent::persistEntity($em, $entity);
    
    $this->hub->publish(new Update(
        topic: 'admin/updates',
        data: json_encode(['action' => 'entity_updated', 'entity' => class($entity)])
    ));
}
```

Then update admin page in real-time with WebSocket listener.
