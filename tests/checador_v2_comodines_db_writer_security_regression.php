<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$database = file_get_contents($root . '/app/Core/Database.php') ?: '';
$writer = file_get_contents($root . '/app/Services/RolComodinesPortalWriterService.php') ?: '';
$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $label) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    $condition ? $passes++ : $fails++;
};
$assert(str_contains($database, 'public static function isLocalDevelopmentDatabaseConnection()')
    && str_contains($database, "'bd_admongas'")
    && str_contains($database, "['localhost', '127.0.0.1', '::1']")
    && str_contains($database, 'DATABASE() AS database_name')
    && !str_contains($database, 'COMODINES_DB_')
    && !str_contains($database, 'comodinesConnection'),
    'the writer reuses the institutional default connection after checking local environment and bd_admongas identity');
$assert(str_contains($writer, 'Capsule::connection()->transaction(')
    && str_contains($writer, 'assertLocalDevelopmentDatabase()')
    && str_contains($writer, 'INSERT INTO op_rh_v2_worker_workplace_coverage_versions')
    && !str_contains($writer, "connection('comodines')")
    && !str_contains($writer, 'assertDedicatedWriterProfile'),
    'coverage mutations run in one default connection transaction and revalidate development guard');
$assert(str_contains($writer, 'INSERT INTO op_rh_v2_turnos_asignaciones')
    && str_contains($writer, 'UPDATE op_rh_v2_turnos_asignaciones')
    && str_contains($writer, "tipo_asignacion='TEMPORAL'")
    && str_contains($writer, 'id_cobertura_version')
    && !str_contains($writer, 'op_rh_v2_comodines_assignments_v1'),
    'linked assignments use canonical versioned rows and cancellation only touches linked TEMPORAL assignments');
$assert(str_contains($writer, 'INSERT INTO op_rh_v2_turnos_audit_log')
    && str_contains($writer, "FROM op_rh_v2_turnos_audit_log WHERE entity_type='WORKPLACE_COVERAGE'")
    && !str_contains($writer, 'op_rh_v2_comodines_audit_v1'),
    'audit events use the canonical audit table');
$assert(str_contains($writer, 'idempotency_key_hash')
    && str_contains($writer, 'payload_fingerprint')
    && str_contains($writer, 'lockWorkerAndWorkplaces')
    && str_contains($writer, 'assertHomeMembership')
    && str_contains($writer, 'assertNoCoverageOverlap')
    && str_contains($writer, 'writeCoverageAudit'),
    'idempotency, row locks, canonical membership, overlap checks and audit remain mandatory');
$assert(str_contains($writer, "in_array(\$environment, ['dev', 'development', 'local'], true)")
    && str_contains($writer, "trim(\$flag) === '1'"),
    'Portal3 writes require an explicit local development environment and enabled writer flag');

printf("CHECADOR_V2_COMODINES_DB_WRITER_SECURITY: %d PASS / %d FAIL\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);
