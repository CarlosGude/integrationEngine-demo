<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__).'/vendor/autoload.php';

if (!isset($_SERVER['KERNEL_CLASS'])) {
    $_SERVER['KERNEL_CLASS'] = 'App\Kernel';
}

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
