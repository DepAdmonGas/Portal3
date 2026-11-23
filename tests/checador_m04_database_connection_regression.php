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
    'M04 production operation gate remains closed until target cutover is approved');

$_ENV['APP_ENV'] = 'dev';
$_ENV['CHECADOR_ADVANCED_DEMO_ENABLED'] = 'false';
$assert(!ChecadorShiftTemplateSqlWriteService::writeGateOpen(),
    'APP_ENV alone cannot open the local exception when the configured default connection is not the local bd_admongas database');

$localTargetCheck = new ReflectionMethod(ChecadorShiftTemplateSqlWriteService::class, 'isLocalDevelopmentTarget');
$localTargetCheck->setAccessible(true);
$assert($localTargetCheck->invoke(null, 'dev', 'mysql', '127.0.0.1', 'bd_admongas', 'bd_admongas'),
    'the Fedora exception requires dev mode and matching local MySQL configuration and runtime database');
$assert($localTargetCheck->invoke(null, 'development', 'mysql', 'localhost', 'bd_admongas', 'bd_admongas'),
    'the Fedora exception accepts the explicitly authorized development environment aliases on loopback');
$assert(!$localTargetCheck->invoke(null, 'production', 'mysql', '127.0.0.1', 'bd_admongas', 'bd_admongas')
    && !$localTargetCheck->invoke(null, 'dev', 'mysql', 'db.example.test', 'bd_admongas', 'bd_admongas')
    && !$localTargetCheck->invoke(null, 'dev', 'mysql', '127.0.0.1', 'bd_admongas_v2_preprod', 'bd_admongas_v2_preprod')
    && !$localTargetCheck->invoke(null, 'dev', 'mysql', '127.0.0.1', 'bd_admongas', 'other_database'),
    'production, remote hosts, and mismatched or preproduction databases cannot use the Fedora exception');

$gateNames = [
    'CHECADOR_M04_SQL_WRITER_ENABLED', 'CHECADOR_M04_API_WRITER_CLOSED',
    'CHECADOR_M04_SQL_GRANTS_VERIFIED', 'CHECADOR_M04_SQL_PREFLIGHT_VERIFIED',
];
foreach ($gateNames as $flag) {
    $_ENV[$flag] = '1';
    putenv($flag . '=1');
}
$assert(ChecadorShiftTemplateSqlWriteService::writeGateOpen(),
    'M04 gate opens only when all four approved environment attestations are explicitly true');
foreach ($gateNames as $flag) {
    $_ENV[$flag] = '0';
    putenv($flag . '=0');
}
$assert(!ChecadorShiftTemplateSqlWriteService::writeGateOpen(),
    'any disabled M04 attestation keeps the write gate closed');
foreach ($gateNames as $flag) {
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
$assert(!$grantCheck->invoke(null, $broadGrants, 'checador'),
    'grant preflight rejects broad privileges that permit mutation of immutable M04 history');
$assert($grantCheck->invoke(null, $broadGrants, 'checador', true),
    'Fedora exception accepts broad grants while still requiring the necessary SELECT and INSERT privileges');
$missingInsert = array_values(array_filter($leastPrivilegeRows,
    static fn(object $row): bool => !($row->grant_scope === 'TABLE' && $row->privilege_type === 'INSERT')));
$assert(!$grantCheck->invoke(null, $missingInsert, 'checador'),
    'local grant preflight still rejects a principal missing required INSERT privileges');
$pleskDatabase = 'bd_admongas_v2_preprod';
$escapedSchema = 'bd\\_admongas\\_v2\\_preprod';
$escapedSchemaGrants = array_map(static function (object $row) use ($escapedSchema): object {
    $copy = clone $row;
    if ($copy->grant_scope !== 'GLOBAL') $copy->table_schema = $escapedSchema;
    return $copy;
}, $leastPrivilegeRows);
$assert($grantCheck->invoke(null, $escapedSchemaGrants, $pleskDatabase),
    'grant preflight recognizes MariaDB escaped underscores using exact schema-name normalization');
$wrongSchemaGrants = array_map(static function (object $row): object {
    $copy = clone $row;
    if ($copy->grant_scope !== 'GLOBAL') $copy->table_schema = 'bdXadmongasXv2Xpreprod';
    return $copy;
}, $leastPrivilegeRows);
$assert(!$grantCheck->invoke(null, $wrongSchemaGrants, $pleskDatabase),
    'grant preflight rejects a different schema instead of treating underscores as wildcards');
$forbiddenUpdate = [...$leastPrivilegeRows,
    (object) ['grant_scope' => 'TABLE', 'table_schema' => 'checador', 'table_name' => 'op_rh_v2_turnos_audit_log', 'privilege_type' => 'UPDATE'],
];
$assert(!$grantCheck->invoke(null, $forbiddenUpdate, 'checador'),
    'grant preflight rejects UPDATE on immutable M04 audit history');
$forbiddenDelete = [...$leastPrivilegeRows,
    (object) ['grant_scope' => 'TABLE', 'table_schema' => 'checador', 'table_name' => 'op_rh_v2_turnos_plantillas', 'privilege_type' => 'DELETE'],
];
$assert(!$grantCheck->invoke(null, $forbiddenDelete, 'checador'),
    'grant preflight rejects DELETE on immutable M04 templates');
$writerSource = file_get_contents(__DIR__ . '/../app/Services/ChecadorShiftTemplateSqlWriteService.php') ?: '';
$assert(!str_contains($writerSource, 'TABLE_SCHEMA = ?')
    && str_contains($writerSource, 'SCHEMA_PRIVILEGES WHERE GRANTEE = ?')
    && str_contains($writerSource, 'TABLE_PRIVILEGES WHERE GRANTEE = ? AND TABLE_NAME IN'),
    'grant preflight fetches schema rows before exact PHP normalization, avoiding escaped-name SQL false rejection');
