<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/Database.php';

use App\Core\Database;
use App\Services\ChecadorShiftTemplateSqlWriteService;
use Illuminate\Database\Capsule\Manager as Capsule;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS {$message}\n";
};

foreach ([
    'APP_ENV', 'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD',
    'CHECADOR_M04_SQL_WRITER_ENABLED', 'CHECADOR_M04_API_WRITER_CLOSED',
    'CHECADOR_M04_SQL_GRANTS_VERIFIED', 'CHECADOR_M04_SQL_PREFLIGHT_VERIFIED',
] as $key) {
    unset($_ENV[$key]);
    putenv($key);
}

putenv('APP_ENV=production');
$_ENV['DB_CONNECTION'] = 'mysql';
$_ENV['DB_HOST'] = 'portal-global-db.invalid';
$_ENV['DB_PORT'] = '3310';
$_ENV['DB_DATABASE'] = 'portal_global';
$_ENV['DB_USERNAME'] = 'portal_global_user';
$_ENV['DB_PASSWORD'] = 'global-test-secret';

Database::initialize();
$default = Capsule::connection('default')->getConfig();
$missingDedicatedConnection = false;
try {
    Capsule::connection('checador_m04')->getConfig();
} catch (InvalidArgumentException) {
    $missingDedicatedConnection = true;
}

$assert(($default['host'] ?? null) === 'portal-global-db.invalid'
    && ($default['port'] ?? null) === '3310'
    && ($default['database'] ?? null) === 'portal_global'
    && ($default['username'] ?? null) === 'portal_global_user'
    && ($default['password'] ?? null) === 'global-test-secret',
    'M04 uses the existing default SQL connection without changing its configuration');
$assert($missingDedicatedConnection, 'no second M04 SQL connection is registered');
$assert(ChecadorShiftTemplateSqlWriteService::CONNECTION_NAME === 'default',
    'M04 writer selects the default Portal3 connection');
$assert(!ChecadorShiftTemplateSqlWriteService::writeGateOpen(),
    'reusing the default connection does not enable M04 writes');
