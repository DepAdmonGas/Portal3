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
$assert(!str_contains($writer, 'App\\Core\\Database')
    && !str_contains($writer, 'isLocalDevelopmentDatabaseConnection')
    && !str_contains($writer, 'assertLocalDevelopmentDatabase')
    && !str_contains($writer, 'APP_ENV')
    && !str_contains($writer, 'CHECADOR_COMODINES_PORTAL_WRITES')
    && !str_contains($writer, 'writer_database_not_local'),
    'canonical writer has no environment, flag, hostname, or local database identity gate');
$assert(str_contains($writer, 'Capsule::connection()->transaction(')
    && str_contains($writer, 'INSERT INTO op_rh_v2_worker_workplace_coverage_versions')
    && !str_contains($writer, "connection('comodines')")
    && !str_contains($writer, 'assertDedicatedWriterProfile'),
    'coverage mutations use Portal3 default connection transactions without adding a parallel connection');
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
$assert(str_contains($writer, 'puesto=? AND estado IN (?,?)')
    && str_contains($writer, 'ChecadorRolComodinesService::COVERAGE_WORKER_ALLOWED_STATES')
    && str_contains($writer, 'ChecadorRolComodinesService::COVERAGE_WORKER_POSITION_ID')
    && str_contains($writer, 'resolveCoverageCandidateSource($request[\'id_personal\'], true)')
    && str_contains($writer, '$action === \'AUTHORIZE\'')
    && str_contains($writer, 'resolveCoverageCandidateSource((int) $current[\'id_personal\'], true)'),
    'the transaction re-locks and validates the same Comodines-only role and status policy as candidate search');
$assert(str_contains($writer, 'self::assertActor($actorId, ChecadorV2PermissionService::COMODINES_REQUEST)')
    && str_contains($writer, 'self::assertActor($actorId, $permission)')
    && str_contains($writer, 'self::assertScope(')
    && str_contains($writer, 'assertHomeMembership')
    && str_contains($writer, 'lockWorkerAndWorkplaces')
    && str_contains($writer, 'writeCoverageAudit')
    && str_contains($writer, 'coverage_readback_failed'),
    'server-side actor capability, scope, membership, row locks, audit and readback remain enforced');

printf("CHECADOR_V2_COMODINES_DB_WRITER_SECURITY: %d PASS / %d FAIL\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);
