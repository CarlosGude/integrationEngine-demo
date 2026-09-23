<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__).'/vendor/autoload.php';

// Test credentials are generated per PHPUnit process instead of being committed.
// They only need to be syntactically valid: all external HTTP calls are mocked.
foreach (['MERCURE_JWT_SECRET', 'TMDB_ACCESS_TOKEN', 'STRIPE_SECRET_KEY', 'STRIPE_WEBHOOK_SECRET'] as $name) {
    if (!isset($_SERVER[$name]) && !isset($_ENV[$name])) {
        $value = bin2hex(random_bytes(32));
        $_SERVER[$name] = $value;
        $_ENV[$name] = $value;
        putenv($name.'='.$value);
    }
}

if (!isset($_SERVER['KERNEL_CLASS'])) {
    $_SERVER['KERNEL_CLASS'] = 'App\Kernel';
}

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
