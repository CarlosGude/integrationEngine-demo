# 🚀 Deployment Guide

**IntegrationEngine Demo v1.0.0** — Production deployment instructions

---

## Prerequisites

- PHP 8.4+ with FPM
- PostgreSQL 15+ or MySQL 8.0+
- Redis 7.0+ (for caching)
- Nginx 1.25+
- Symfony 7.4+
- Git (for CI/CD)

---

## Architecture Overview

```
┌─────────────────┐
│   Nginx         │  (reverse proxy, TLS termination)
└────────┬────────┘
         │
┌────────▼────────┐
│  PHP 8.4 FPM    │  (Symfony 7.4 app)
└────────┬────────┘
         │
    ┌────┴────┐
    │          │
┌───▼──┐  ┌──▼────┐
│ DB   │  │ Redis │  (PostgreSQL + cache)
└──────┘  └───────┘
```

---

## VPS Deployment (AWS EC2 / DigitalOcean)

### Step 1: Provision Server

**Specs for demo:**
- Ubuntu 24.04 LTS
- t3.medium (2 vCPU, 4GB RAM)
- 20GB SSD
- Public IP with elastic IP

**Security Groups:**
```
Inbound:
  - 80/tcp (HTTP) from 0.0.0.0/0
  - 443/tcp (HTTPS) from 0.0.0.0/0
  - 22/tcp (SSH) from your IP only
Outbound:
  - All (for API calls)
```

### Step 2: System Setup

**SSH into server:**
```bash
ssh -i your-key.pem ubuntu@your-ip
```

**Install dependencies:**
```bash
sudo apt update && sudo apt upgrade -y

# PHP 8.4
sudo add-apt-repository ppa:ondrej/php
sudo apt install -y \
  php8.4-fpm \
  php8.4-cli \
  php8.4-pdo \
  php8.4-mbstring \
  php8.4-xml \
  php8.4-curl \
  php8.4-gd \
  php8.4-redis \
  composer

# Database
sudo apt install -y postgresql postgresql-contrib
sudo systemctl enable postgresql

# Cache
sudo apt install -y redis-server
sudo systemctl enable redis-server

# Web server
sudo apt install -y nginx
sudo systemctl enable nginx

# Certbot (HTTPS)
sudo apt install -y certbot python3-certbot-nginx
```

### Step 3: Database Setup

```bash
# Connect to PostgreSQL
sudo -u postgres psql

# Create database and user
CREATE DATABASE integration_engine;
CREATE USER app_user WITH PASSWORD 'strong_password_here';
ALTER ROLE app_user SET client_encoding TO 'utf8';
ALTER ROLE app_user SET default_transaction_isolation TO 'read committed';
ALTER ROLE app_user SET default_transaction_deferrable TO on;
ALTER ROLE app_user SET default_timezone TO 'UTC';
GRANT ALL PRIVILEGES ON DATABASE integration_engine TO app_user;
\q
```

### Step 4: Deploy Application

```bash
# As ubuntu user
cd /var/www
sudo git clone https://github.com/CarlosGude/integrationEngine-demo.git
cd integrationEngine-demo
sudo chown -R www-data:www-data .

# Install dependencies (as www-data)
sudo -u www-data composer install --no-dev --optimize-autoloader

# Set permissions
sudo chmod -R 755 var/
sudo chmod -R 755 public/
```

### Step 5: Environment Configuration

**Create `.env.local`:**
```bash
sudo -u www-data nano .env.local
```

**Contents (required):**
```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=your-generated-secret-key

# Mercure real-time hub — required for /en/store live updates
MERCURE_JWT_SECRET=your-long-random-string

# The Movie Database API — required for /en/store to display movies
TMDB_ACCESS_TOKEN=your_read_token_here

# Stripe webhook signing — required for /webhook/stripe
STRIPE_SECRET_KEY=sk_live_your_key_here
STRIPE_WEBHOOK_SECRET=whsec_your_secret_here

# Public Mercure URL (browser connects here for real-time updates)
MERCURE_PUBLIC_URL=https://your-domain.com/.well-known/mercure
```

**Generate APP_SECRET:**
```bash
php -r 'echo bin2hex(random_bytes(16)), PHP_EOL;'
```

### Secret rotation checklist

Every value below ships with a placeholder that is public in this repository.
None of them is a secret until you replace it. Work through the list before the
first deploy, and again whenever someone with access leaves.

| Variable | Ships as | Required? | Replace with |
|---|---|---|---|
| `APP_SECRET` | `dev-secret-change-in-production` in `.env` | ✅ Yes | `php -r 'echo bin2hex(random_bytes(16)), PHP_EOL;'` |
| `MERCURE_JWT_SECRET` | `!ChangeThisMercureHubJWTSecretKey!` in `.env` | ✅ Yes | any long random string; hub refuses to start without it |
| `TMDB_ACCESS_TOKEN` | empty in `.env` | ✅ Yes (for /store) | a read access token from [themoviedb.org](https://www.themoviedb.org/settings/api) |
| `STRIPE_SECRET_KEY` | `sk_test_placeholder` | ✅ Yes (for webhooks) | your live key from the [Stripe dashboard](https://dashboard.stripe.com/apikeys) |
| `STRIPE_WEBHOOK_SECRET` | `whsec_test_placeholder` | ✅ Yes | the signing secret from your [Stripe webhook endpoint](https://dashboard.stripe.com/webhooks) |
| `MERCURE_PUBLIC_URL` | `https://example.com/.well-known/mercure` in `.env` | ✅ Yes | your actual domain + `/mercure` path; browsers use this |

Put the replacements in `.env.local` (git-ignored) or, better, in the real
environment of the host. Do not edit `.env` itself — it is committed, and the
next `git pull` will fight you for it.

**Why Doctrine was removed:** This demo focuses on IntegrationEngine integrations, not
data persistence. No database or `DATABASE_URL` needed. The app uses in-memory
messenger transport for demo events. See [Architecture](./README.md#architecture) for details.

**Where they are read from:**

- `APP_SECRET` comes from the `.env` chain only. It is deliberately *not* set in
  `compose.yaml`: a real environment variable overrides every `.env` file in
  Symfony, so declaring it there gave Docker a different secret from
  `symfony server:start`, and sessions broke depending on how the app was
  started.
- `MERCURE_JWT_SECRET` is read twice — by Symfony for signing, and by Docker
  Compose to configure the hub. One value in `.env.local` covers both.

### Step 6: Nginx Configuration

**Create `/etc/nginx/sites-available/integration-engine`:**
```nginx
upstream php_backend {
    server unix:/run/php/php8.4-fpm.sock;
}

server {
    listen 80;
    server_name your-domain.com www.your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com www.your-domain.com;

    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    root /var/www/integrationEngine-demo/public;
    index index.php;

    # Security headers
    add_header Strict-Transport-Security "max-age=31536000" always;
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {
        # No `$uri/`: it matches the document root itself for `GET /`, so nginx
        # serves a directory index and answers 403 instead of reaching the
        # front controller. This is the shape Symfony documents.
        try_files $uri /index.php$is_args$args;
    }

    location ~ \.php$ {
        fastcgi_pass php_backend;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Cache static assets
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 365d;
        add_header Cache-Control "public, immutable";
    }
}
```

**Enable site:**
```bash
sudo ln -s /etc/nginx/sites-available/integration-engine /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

**Setup HTTPS:**
```bash
sudo certbot certonly --nginx -d your-domain.com -d www.your-domain.com
```

### Step 7: PHP-FPM Configuration

**Edit `/etc/php/8.4/fpm/pool.d/www.conf`:**
```ini
; Maximum number of children
pm.max_children = 20
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 15
pm.process_idle_timeout = 10s

; Security
php_admin_value[disable_functions] = exec,passthru,shell_exec,system,proc_open,popen,curl_exec
```

**Restart:**
```bash
sudo systemctl restart php8.4-fpm
```

### Step 8: Cache & Background Jobs

**Warm up cache (production):**
```bash
cd /var/www/integrationEngine-demo
sudo -u www-data php bin/console cache:warmup --env=prod
```

**Messenger worker (optional, for async jobs):**
```bash
# Create systemd service at /etc/systemd/system/integration-engine-worker.service
[Unit]
Description=IntegrationEngine Messenger Worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/integrationEngine-demo
ExecStart=/usr/bin/php bin/console messenger:consume async -vv
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

**Enable:**
```bash
sudo systemctl enable integration-engine-worker
sudo systemctl start integration-engine-worker
```

---

## CI/CD Pipeline (GitHub Actions)

**Create `.github/workflows/deploy.yml`:**

```yaml
name: Deploy to Production

on:
  push:
    branches: [main]
    tags: ['v*']

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3

      - name: Test
        run: |
          composer install
          php bin/console cache:clear --env=test
          php bin/console doctrine:schema:create --env=test
          vendor/bin/phpunit

      - name: Code Quality
        run: |
          vendor/bin/phpstan analyse --level=max
          vendor/bin/deptrac analyse

      - name: Deploy via SSH
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.DEPLOY_HOST }}
          username: ${{ secrets.DEPLOY_USER }}
          key: ${{ secrets.DEPLOY_KEY }}
          script: |
            cd /var/www/integrationEngine-demo
            git fetch origin
            git checkout ${{ github.ref }}
            composer install --no-dev --optimize-autoloader
            php bin/console cache:clear --env=prod
            php bin/console cache:warmup --env=prod
            php bin/console doctrine:migrations:migrate --no-interaction
            sudo systemctl reload php8.4-fpm
            sudo systemctl reload nginx
```

**Add GitHub secrets:**
- `DEPLOY_HOST` — VPS IP
- `DEPLOY_USER` — SSH user (ubuntu)
- `DEPLOY_KEY` — SSH private key

---

## Monitoring & Logs

### Application Logs
```bash
# Real-time logs
sudo tail -f /var/www/integrationEngine-demo/var/log/prod.log

# Nginx logs
sudo tail -f /var/log/nginx/access.log
sudo tail -f /var/log/nginx/error.log

# PHP-FPM logs
sudo tail -f /var/log/php8.4-fpm.log
```

### Health Checks
```bash
# Check app status
curl -s https://your-domain.com/en/store | grep -q "IntegrationEngine" && echo "OK" || echo "FAIL"

# Check database
sudo -u www-data php bin/console doctrine:query:sql "SELECT 1"

# Check cache
sudo -u www-data php bin/console cache:pool:clear cache.app
```

### Monitoring Stack (Optional)
```bash
# Install monitoring
sudo apt install -y prometheus-node-exporter
sudo systemctl enable prometheus-node-exporter

# Optional: Add to Datadog, New Relic, or Sentry
```

---

## Backup Strategy

### Database Backups
```bash
# Create backup script at /usr/local/bin/backup-db.sh
#!/bin/bash
BACKUP_DIR="/backups/database"
mkdir -p $BACKUP_DIR
DATE=$(date +%Y%m%d_%H%M%S)
sudo -u postgres pg_dump integration_engine | gzip > $BACKUP_DIR/backup_$DATE.sql.gz
find $BACKUP_DIR -mtime +30 -delete  # Keep 30 days
```

**Schedule daily:**
```bash
sudo crontab -e
# Add: 0 2 * * * /usr/local/bin/backup-db.sh
```

### Code Backups
```bash
# Automated via git push to GitHub (already done)
# Backup .env.local separately
sudo cp /var/www/integrationEngine-demo/.env.local /backups/.env.local.backup
```

---

## Security Checklist

- [ ] HTTPS enabled (Let's Encrypt certificate)
- [ ] APP_DEBUG=0 in production
- [ ] Strong database password
- [ ] Redis requires authentication
- [ ] SSH key-only access (no passwords)
- [ ] Firewall configured (only ports 80, 443, 22)
- [ ] Regular backups scheduled
- [ ] Log rotation configured
- [ ] Secrets stored in `.env.local` (not committed)
- [ ] STRIPE_WEBHOOK_SECRET verified
- [ ] Rate limiting enabled
- [ ] CORS configured if needed
- [ ] Security headers added (Nginx config above)
- [ ] Regular security updates scheduled

---

## Performance Optimization

### PHP Configuration
```ini
; /etc/php/8.4/fpm/php.ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
opcache.revalidate_freq=0

realpath_cache_size=4096K
realpath_cache_ttl=3600
```

### Nginx Caching
```nginx
# Already in config above:
# - Gzip compression
# - Static asset caching (365 days)
# - HTTP/2 support
```

### Database
```sql
-- Create indexes for frequently queried columns
CREATE INDEX idx_rental_user ON rentals(user_id);
CREATE INDEX idx_rental_status ON rentals(status);
```

---

## Rollback Procedure

If deployment fails:

```bash
cd /var/www/integrationEngine-demo

# See recent commits
git log --oneline -10

# Rollback to previous working version
git checkout previous-commit-hash

# Recompile cache
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

# Restart services
sudo systemctl reload php8.4-fpm
sudo systemctl reload nginx
```

---

## Testing After Deployment

```bash
# 1. HTTP test
curl -I https://your-domain.com/en/store
# Should return 200

# 2. Database connectivity
curl https://your-domain.com/en/store | grep -q "movies"

# 3. API integration (TMDB)
curl https://your-domain.com/en/store | grep -q "Fight Club"

# 4. Run health checks
php bin/console debug:router | head -20
```

---

## Support & Monitoring

### On-Call Alerts
- [ ] Setup uptime monitoring (UptimeRobot, Statuspage)
- [ ] Setup error tracking (Sentry)
- [ ] Setup performance monitoring (New Relic, Datadog)
- [ ] Setup log aggregation (ELK Stack, Splunk)

### Documentation
- [ ] Team runbook for common issues
- [ ] Incident response plan
- [ ] Escalation contacts

---

**Generated:** 2026-09-21 | Ready for production deployment
