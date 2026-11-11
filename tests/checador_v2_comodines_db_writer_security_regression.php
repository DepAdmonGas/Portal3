<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Services\RolComodinesPortalWriterService;

$root = dirname(__DIR__);
$database = file_get_contents($root . '/app/Core/Database.php');
$writer = file_get_contents($root . '/app/Services/RolComodinesPortalWriterService.php');
$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $label) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    $condition ? $passes++ : $fails++;
};

$assert(is_string($database)
    && str_contains($database, 'public static function isLocalDevelopmentDatabaseConnection()')
    && str_contains($database, "'bd_admongas'")
    && str_contains($database, "['localhost', '127.0.0.1', '::1']")
    && str_contains($database, 'DATABASE() AS database_name')
    && !str_contains($database, 'COMODINES_DB_')
    && !str_contains($database, 'comodinesConnection'),
    'the writer reuses only the institutional default PDO after checking local development and effective bd_admongas identity');
$assert(is_string($writer)
    && str_contains($writer, "Capsule::connection()->transaction(")
    && str_contains($writer, 'assertLocalDevelopmentDatabase()')
    && str_contains($writer, "INSERT INTO op_rh_v2_worker_workplace_coverage_versions")
    && !str_contains($writer, "connection('comodines')")
    && !str_contains($writer, 'assertDedicatedWriterProfile'),
    'coverage mutations use one default PDO transaction and revalidate its environment inside the transaction');
$assert(is_string($writer)
    && str_contains($writer, 'INSERT INTO op_rh_v2_turnos_asignaciones')
    && str_contains($writer, 'UPDATE op_rh_v2_turnos_asignaciones')
    && str_contains($writer, "tipo_asignacion='TEMPORAL'")
    && str_contains($writer, 'id_cobertura_version')
    && !str_contains($writer, 'op_rh_v2_comodines_assignments_v1'),
    'linked assignments are persisted directly to canonical table rows and cancellation is restricted to TEMPORAL coverage links');
$assert(is_string($writer)
    && str_contains($writer, 'INSERT INTO op_rh_v2_turnos_audit_log')
    && str_contains($writer, "FROM op_rh_v2_turnos_audit_log WHERE entity_type='WORKPLACE_COVERAGE'")
    && !str_contains($writer, 'op_rh_v2_comodines_audit_v1'),
    'audit is written and read from the canonical audit table in the same Portal3 transaction');
$assert(is_string($writer)
    && str_contains($writer, 'idempotency_key_hash')
    && str_contains($writer, 'payload_fingerprint')
    && str_contains($writer, 'lockWorkerAndWorkplaces')
    && str_contains($writer, 'assertHomeMembership')
    && str_contains($writer, 'assertNoCoverageOverlap')
    && str_contains($writer, 'writeCoverageAudit'),
    'idempotency, row locks, canonical membership, overlap checks, and audit remain mandatory');
$assert(RolComodinesPortalWriterService::writesEnabled('dev', '1') === true
    && RolComodinesPortalWriterService::writesEnabled('development', '1') === true
    && RolComodinesPortalWriterService::writesEnabled('testing', '1') === false
    && RolComodinesPortalWriterService::writesEnabled('production', '1') === false,
    'the Portal3 writer gate accepts only local development environment names');
$oldAppEnv = $_ENV['APP_ENV'] ?? null;
$oldGate = $_ENV['CHECADOR_COMODINES_PORTAL_WRITES'] ?? null;
$oldProcessGate = getenv('CHECADOR_COMODINES_PORTAL_WRITES');
$_ENV['APP_ENV'] = 'dev';
$_ENV['CHECADOR_COMODINES_PORTAL_WRITES'] = '1';
putenv('CHECADOR_COMODINES_PORTAL_WRITES');
$blockedCreate = (new RolComodinesPortalWriterService())->createRequest(13, [], [], 'idempotency-key-not-used');
$blockedTransition = (new RolComodinesPortalWriterService())->transition(
    'daaa2c2b-f936-4b0e-bf7e-20376cb90739', 'AUTHORIZE', 13, [], 'No operation', 'idempotency-key-not-used'
);
if ($oldAppEnv === null) unset($_ENV['APP_ENV']); else $_ENV['APP_ENV'] = $oldAppEnv;
if ($oldGate === null) unset($_ENV['CHECADOR_COMODINES_PORTAL_WRITES']); else $_ENV['CHECADOR_COMODINES_PORTAL_WRITES'] = $oldGate;
if ($oldProcessGate === false) putenv('CHECADOR_COMODINES_PORTAL_WRITES'); else putenv('CHECADOR_COMODINES_PORTAL_WRITES=' . $oldProcessGate);
$assert(($blockedCreate['code'] ?? '') === 'writer_database_not_local'
    && ($blockedTransition['code'] ?? '') === 'writer_database_not_local',
    'an enabled development gate still fails closed when the effective default connection is not verified as local bd_admongas');
$oldEnv = $_ENV['APP_ENV'] ?? null;
$_ENV['APP_ENV'] = 'production';
$productionConnectionDenied = Database::isLocalDevelopmentDatabaseConnection() === false;
if ($oldEnv === null) unset($_ENV['APP_ENV']); else $_ENV['APP_ENV'] = $oldEnv;
$assert($productionConnectionDenied, 'the local database guard denies production before opening or mutating a connection');

printf("CHECADOR_V2_COMODINES_DB_WRITER_SECURITY: %d PASS / %d FAIL\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);
