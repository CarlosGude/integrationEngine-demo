<?php

declare(strict_types=1);

if (!isset($_SERVER['APP_ENV'])) {
    $_SERVER['APP_ENV'] = 'test';
}

if (!isset($_SERVER['KERNEL_CLASS'])) {
    $_SERVER['KERNEL_CLASS'] = 'App\Kernel';
}

require_once dirname(__DIR__).'/vendor/autoload.php';
