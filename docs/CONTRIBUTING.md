# 🤝 Contributing Guide

Guidelines for contributing to IntegrationEngine Demo project.

## Code of Conduct

Be respectful, inclusive, and professional. We welcome contributions from everyone.

## Getting Started

### 1. Fork & Clone

```bash
git clone https://github.com/YOUR-USERNAME/integrationEngine-demo.git
cd integrationEngine-demo
git remote add upstream https://github.com/carlosgude/integrationEngine-demo.git
```

### 2. Create Branch

```bash
git checkout -b feature/your-feature-name
# or
git checkout -b fix/bug-description
```

Branch naming:
- `feature/description` — New features
- `fix/bug-name` — Bug fixes
- `docs/update` — Documentation
- `refactor/area` — Code refactoring
- `test/coverage` — Tests

### 3. Install Dependencies

```bash
composer install
```

### 4. Make Changes

Follow the [coding standards](#coding-standards).

#### Generated files you should not commit

`config/reference.php` is written by Symfony's ConfigBuilder during cache
warmup, and it comes out with a different shape depending on the `APP_ENV` of
the last command you ran. It used to be in version control, so merely running
`bin/console` produced a diff of a 98 KB file nobody edits.

It is now git-ignored. If you see it in `git status`, your checkout predates
that change — run:

```bash
git rm --cached config/reference.php
```

Nothing in this project consumes it: every file in `config/packages/` is YAML,
so there is no PHP configuration for it to type, and PHPStan only analyses
`src` and `tests`. `make stan` gives the identical result with and without it,
which is what settled the question.

### 5. Test Your Changes

```bash
# Run all tests
make test

# Run specific test
make test TEST=tests/Security/AdminAccessControlTest.php

# Code quality
make cs
make stan
make deptrac
```

### 6. Commit Changes

```bash
git add .
git commit -m "feat: add new feature" # or fix:, docs:, etc.
```

**Commit message format:**
```
<type>(<scope>): <subject>

<body>

Fixes #issue-number (if applicable)
```

**Types:**
- `feat` — New feature
- `fix` — Bug fix
- `docs` — Documentation
- `style` — Code style (not CSS)
- `refactor` — Code restructure (no behavior change)
- `test` — Test additions/updates
- `chore` — Dependency updates, CI config
- `perf` — Performance improvement

**Example:**
```bash
git commit -m "feat(mercure): add real-time transaction updates

- Implement HubInterface publisher
- Add MercureUpdateController with 3 endpoints
- Create mercure-demo.html for testing
- Update configuration in .env

Fixes #42"
```

### 7. Push & Create PR

```bash
git push origin feature/your-feature-name
```

Then open a Pull Request on GitHub.

## Pull Request Guidelines

### Before Submitting

- [ ] Tests pass: `make ci`
- [ ] Code follows standards: `make cs`
- [ ] No type errors: `make stan`
- [ ] Architecture validated: `make deptrac`
- [ ] Documentation updated
- [ ] Commit messages are clear

### PR Description Template

```markdown
## Description
Brief description of changes.

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Related Issues
Fixes #123

## Testing
How to test this change:

## Checklist
- [ ] Tests added/updated
- [ ] Documentation updated
- [ ] No breaking changes
- [ ] Code style followed
```

## Coding Standards

### PHP Style

We use PSR-12 with these additions:

```php
// 1. Type hints required
public function processTransaction(Transaction $tx): Response
{
    // ...
}

// 2. Declare strict types
declare(strict_types=1);

// 3. Use readonly where possible
private readonly HubInterface $hub;

// 4. Single responsibility principle
class UserCrudController extends AbstractCrudController
{
    // One entity per controller
}

// 5. Dependency injection via constructor
public function __construct(
    private TransactionService $service,
    private HubInterface $hub,
) {}
```

### Naming Conventions

| Type | Convention | Example |
|------|-----------|---------|
| Classes | PascalCase | `UserController`, `TransactionService` |
| Methods | camelCase | `getTransactions()`, `processPayment()` |
| Constants | UPPER_SNAKE_CASE | `MAX_RETRIES`, `DEFAULT_TIMEOUT` |
| Variables | camelCase | `$transactionId`, `$userData` |
| Properties | camelCase | `private $repository` |

### Documentation

Every public method needs a docblock:

```php
/**
 * Process a payment transaction.
 *
 * @param Transaction $transaction The transaction to process
 * @param PaymentGateway $gateway The payment processor
 *
 * @return PaymentResult The processing result
 *
 * @throws PaymentException If processing fails
 */
public function processPayment(
    Transaction $transaction,
    PaymentGateway $gateway,
): PaymentResult {
    // ...
}
```

### Testing

- ✅ Unit tests for services
- ✅ Integration tests for controllers
- ✅ Feature tests for workflows
- ✅ Minimum 80% coverage

Illustrative example of the shape we expect — this file is not in the repo:

```php
class BenchmarkTest extends TestCase
{
    public function testParallelLoadingIsFaster(): void
    {
        // Arrange
        $benchmark = new CatalogBenchmark();
        
        // Act
        $sequential = $benchmark->sequential(5);
        $parallel = $benchmark->parallel(5);
        
        // Assert
        $this->assertLessThan($sequential, $parallel * 2);
    }
}
```

## Project Structure

```
src/
├── Catalog/          # Movie catalog (TMDB)
├── Pricing/          # Pricing service (CSV + GraphQL)
├── Payment/          # Payment processing (Stripe)
├── Shared/           # Cross-cutting concerns
├── Controller/
│   ├── Admin/        # Admin CRUD controllers
│   └── ...
└── Tour/             # Interactive tour system

tests/
├── Catalog/
├── Pricing/
├── Payment/
└── Tour/

config/
├── packages/         # Framework config
├── routes/
└── services.yaml
```

## Running Tests Locally

```bash
# All tests
make test

# Watch mode (requires watchman)
make test-watch

# With coverage
make test-coverage

# Specific test
make test TEST=tests/Security/AdminAccessControlTest.php::testInboundWebhookStaysAnonymous
```

## Continuous Integration

GitHub Actions runs on every push and PR:

- ✅ PHPUnit tests
- ✅ PHP CS Fixer
- ✅ PHPStan (level max)
- ✅ Deptrac (architecture)
- ✅ Docker image build

All must pass before merge.

## Documentation

Update docs when:
- Adding features
- Changing API
- Fixing bugs (if documented)
- Improving examples

### File Structure

```
docs/
├── QUICKSTART.md          # Get started in 5 min
├── ARCHITECTURE.md        # System design
├── EASYADMIN.md           # Admin setup
├── MERCURE-WEBSOCKETS.md  # Real-time updates
├── DEPLOYMENT.md          # Production
└── WIKI.md                # Master index
```

### Markdown Style

```markdown
# Main Heading

## Subheading

### Sub-subheading

**Bold for emphasis**

- Bullet list
- Another item

1. Numbered list
2. Another item

```bash
# Code blocks
php bin/console debug:router
```

[Link text](url)
```

## Git Workflow

### 1. Keep Branch Updated

```bash
git fetch upstream
git rebase upstream/main
```

### 2. Squash Commits (if needed)

```bash
git rebase -i HEAD~3  # Rebase last 3 commits
# Mark commits as 'squash' except first
```

### 3. Force Push to Feature Branch (only!)

```bash
git push origin feature/name --force-with-lease
```

**⚠️ Never force push to main!**

## Performance Guidelines

When adding features:

- ⚡ Parallel requests where possible
- 🔄 Reuse existing services
- 📊 Benchmark impacts (see `catalog:benchmark`)
- 🗄️ Use database indexes
- ⏱️ Cache expensive operations

## Security Guidelines

- 🔐 No credentials in code
- ✅ Use environment variables
- ✅ Validate all input
- ✅ Use parameterized queries
- ✅ Follow OWASP top 10

## Questions?

- **Issues**: https://github.com/carlosgude/integrationEngine-demo/issues
- **Discussions**: https://github.com/carlosgude/integrationEngine-demo/discussions
- **Email**: carlos.sgude@gmail.com

## License

By contributing, you agree your work is licensed under the MIT License.

---

**Thank you for contributing!** 🎉
