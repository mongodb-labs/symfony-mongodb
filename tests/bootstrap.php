<?php

declare(strict_types=1);

use MongoDB\Bundle\Tests\TestApplication\TestKernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;

require dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists(dirname(__DIR__) . '/config/bootstrap.php')) {
    require dirname(__DIR__) . '/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
}

// Default connection string for local test runs, the CI provides its own value
$_SERVER['MONGODB_PRIMARY_URL'] ??= 'mongodb://localhost:27017';

$kernel = new TestKernel();

// delete the existing cache directory to avoid issues
(new Filesystem())->remove($kernel->getCacheDir());
