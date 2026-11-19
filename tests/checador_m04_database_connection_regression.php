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
    'DB_M04_HOST', 'DB_M04_PORT', 'DB_M04_DATABASE', 'DB_M04_USERNAME', 'DB_M04_PASSWORD',
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
putenv('DB_M04_HOST=mariadb-m04.internal');
putenv('DB_M04_PORT=3307');
putenv('DB_M04_DATABASE=checador_v2');
putenv('DB_M04_USERNAME=portal3_m04_reader');
putenv('DB_M04_PASSWORD= m04-test-secret ');

Database::initialize();
$global = Capsule::connection('default')->getConfig();
$m04 = Capsule::connection('checador_m04')->getConfig();

$assert(($global['host'] ?? null) === 'portal-global-db.invalid'
    && ($global['database'] ?? null) === 'portal_global'
    && ($global['username'] ?? null) === 'portal_global_user'
    && ($global['password'] ?? null) === 'global-test-secret',
    'global Portal3 SQL connection remains unchanged');
$assert(($m04['driver'] ?? null) === 'mysql'
    && ($m04['host'] ?? null) === 'mariadb-m04.internal'
    && ($m04['port'] ?? null) === 3307
    && ($m04['database'] ?? null) === 'checador_v2'
    && ($m04['username'] ?? null) === 'portal3_m04_reader'
    && ($m04['password'] ?? null) === ' m04-test-secret ',
    'production APP_ENV registers the dedicated M04 connection from private DB_M04_* variables');
$assert(!ChecadorShiftTemplateSqlWriteService::writeGateOpen(),
    'registering the M04 read connection does not enable the M04 SQL writer');
