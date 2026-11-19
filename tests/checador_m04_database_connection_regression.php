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
$assert(ChecadorShiftTemplateSqlWriteService::writeGateOpen(),
    'M04 does not require environment flags to pass its non-demo operation gate');

foreach ([
    'CHECADOR_M04_SQL_WRITER_ENABLED', 'CHECADOR_M04_API_WRITER_CLOSED',
    'CHECADOR_M04_SQL_GRANTS_VERIFIED', 'CHECADOR_M04_SQL_PREFLIGHT_VERIFIED',
] as $flag) {
    $_ENV[$flag] = '0';
    putenv($flag . '=0');
}
$assert(ChecadorShiftTemplateSqlWriteService::writeGateOpen(),
    'M04 operation gate ignores all four legacy deployment flags');
foreach ([
    'CHECADOR_M04_SQL_WRITER_ENABLED', 'CHECADOR_M04_API_WRITER_CLOSED',
    'CHECADOR_M04_SQL_GRANTS_VERIFIED', 'CHECADOR_M04_SQL_PREFLIGHT_VERIFIED',
] as $flag) {
    unset($_ENV[$flag]);
    putenv($flag);
}

$grantCheck = new ReflectionMethod(ChecadorShiftTemplateSqlWriteService::class, 'hasRequiredM04Grants');
$grantCheck->setAccessible(true);
$leastPrivilegeRows = [
    (object) ['grant_scope' => 'SCHEMA', 'table_schema' => 'checador', 'table_name' => null, 'privilege_type' => 'SELECT'],
    ...array_map(static fn(string $table): object => (object) [
        'grant_scope' => 'TABLE', 'table_schema' => 'checador', 'table_name' => $table, 'privilege_type' => 'INSERT',
    ], [
        'op_rh_v2_turnos_plantillas', 'op_rh_v2_turnos_segmentos',
        'op_rh_v2_shift_template_station_lineage', 'op_rh_v2_turnos_audit_log',
    ]),
];
$assert($grantCheck->invoke(null, $leastPrivilegeRows, 'checador'),
    'local grant preflight accepts the required SELECT and INSERT privileges');
$broadGrants = [...$leastPrivilegeRows,
    (object) ['grant_scope' => 'SCHEMA', 'table_schema' => 'checador', 'table_name' => null, 'privilege_type' => 'ALL PRIVILEGES'],
];
$assert($grantCheck->invoke(null, $broadGrants, 'checador'),
    'local grant preflight does not reject sufficient privileges solely because additional grants exist');
$missingInsert = array_values(array_filter($leastPrivilegeRows,
    static fn(object $row): bool => !($row->grant_scope === 'TABLE' && $row->privilege_type === 'INSERT')));
$assert(!$grantCheck->invoke(null, $missingInsert, 'checador'),
    'local grant preflight still rejects a principal missing required INSERT privileges');
