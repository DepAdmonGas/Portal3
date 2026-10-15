<?php

declare(strict_types=1);

use App\Core\Database;
use App\Services\EffectiveScheduleResolver;
use App\Services\PersonalScheduleVersionService;
use App\Services\WorkplaceTimezoneVersionService;
use App\Services\WorkerWorkplaceAssignmentVersionService;
use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../scripts/migrate_workplace_timezone_versions.php';
require_once __DIR__ . '/../scripts/migrate_worker_workplace_assignment_versions.php';
require_once __DIR__ . '/../scripts/migrate_personal_schedule_versions.php';

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$expectedDatabase = 'bd_portal3_p3_l3_4';
$configuredDatabase = (string)($_ENV['DB_DATABASE'] ?? '');
$host = strtolower(trim((string)($_ENV['DB_HOST'] ?? '')));
if ($configuredDatabase !== $expectedDatabase || !in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
    fwrite(STDERR, "REFUSED: set DB_DATABASE=bd_portal3_p3_l3_4 and use a loopback MariaDB host; no database changes made.\n");
    exit(2);
}

Database::initialize();
$connection = Capsule::connection();
$databaseName = (string)$connection->selectOne('SELECT DATABASE() AS db')->db;
$serverVersion = (string)$connection->selectOne('SELECT VERSION() AS version')->version;
if ($databaseName !== $expectedDatabase || !str_starts_with($serverVersion, '11.8.8-MariaDB')) {
    fwrite(STDERR, "REFUSED: expected disposable MariaDB 11.8.8 schema bd_portal3_p3_l3_4; no database changes made.\n");
    exit(2);
}
$requiredTables = [
    'op_rh_personal',
    'op_rh_localidades',
    'op_rh_localidad_timezone_version',
    'op_rh_personal_localidad_version',
    'op_rh_personal_horario_version',
    'op_rh_personal_horario_version_dia',
    'op_rh_personal_horario_version_segmento',
    'op_rh_personal_horario',
    'op_rh_personal_horario_hist',
    'op_rh_localidades_horario',
    'op_rh_personal_horario_programar',
    'op_rh_personal_horario_programar_detalle',
    'op_rh_personal_asistencia',
];
foreach ($requiredTables as $table) {
    if (!Capsule::schema()->hasTable($table)) {
        fwrite(STDERR, "REFUSED: required test schema table is missing: {$table}; no database changes made.\n");
        exit(2);
    }
}

$passed = 0;
$failed = 0;
$assert = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    if ($condition) {
        $passed++;
        echo "PASS: {$label}\n";
    } else {
        $failed++;
        echo "FAIL: {$label}\n";
    }
};

$clockValue = '2026-01-01T00:00:00Z';
$clock = static function () use (&$clockValue): DateTimeImmutable { return new DateTimeImmutable($clockValue); };
$timezoneWriter = new WorkplaceTimezoneVersionService($clock);
$assignmentWriter = new WorkerWorkplaceAssignmentVersionService($clock);
$scheduleWriter = new PersonalScheduleVersionService($clock);
$resolver = new EffectiveScheduleResolver(clock: $clock);
$sequence = 0;
$nextNumlista = static function () use (&$sequence): int {
    do {
        $candidate = random_int(1_800_000_000, 2_100_000_000) + $sequence++;
    } while (Capsule::table('op_rh_localidades')->where('numlista', $candidate)->exists());
    return $candidate;
};
$nextEmployeeNumber = static function () use (&$sequence): int {
    do {
        $candidate = random_int(1_800_000_000, 2_100_000_000) + $sequence++;
    } while (Capsule::table('op_rh_personal')->where('no_colaborador', $candidate)->exists());
    return $candidate;
};
$week = static function (string $date, string $state, array $segments): array {
    $weekdayTarget = (int)(new DateTimeImmutable($date, new DateTimeZone('UTC')))->format('N');
    $days = [];
    for ($weekday = 1; $weekday <= 7; $weekday++) {
        $days[] = [
            'weekday_iso' => $weekday,
            'day_state' => $weekday === $weekdayTarget ? $state : 'DAY_OFF',
            'segments' => $weekday === $weekdayTarget ? $segments : [],
        ];
    }
    return $days;
};
$makeFixture = static function (
    string $name,
    string $date,
    string $timezone,
    string $state = 'SCHEDULED',
    array $segments = [['sequence_number' => 1, 'start_local_time' => '08:00', 'end_local_time' => '16:00', 'end_day_offset' => 0]],
    array $assignments = [['valid_from_utc' => '2020-01-01T00:00:00Z', 'valid_to_utc' => null]],
    array $timezoneVersions = [],
    ?int $reuseWorker = null,
    bool $createSchedule = true,
) use (&$sequence, $nextNumlista, $nextEmployeeNumber, $timezoneWriter, $assignmentWriter, $scheduleWriter, $week): array {
    $workplaceId = (int)Capsule::table('op_rh_localidades')->insertGetId([
        'numlista' => $nextNumlista(),
        'localidad' => 'P3-L3-4 disposable ' . $name . '-' . $sequence,
        'recuperacion_vapores' => '',
    ]);
    $workerId = $reuseWorker ?? (int)Capsule::table('op_rh_personal')->insertGetId([
        'id_estacion' => $workplaceId + 1_000_000,
        'fecha_ingreso' => '2020-01-01',
        'no_colaborador' => $nextEmployeeNumber(),
        'nombre_completo' => 'P3-L3-4 disposable worker ' . $sequence,
        'puesto' => 0,
        'requisicion' => '',
        'curriculum' => '',
        'ine' => '',
        'acta_nacimiento' => '',
        'c_domicilio' => '',
        'curp' => '',
        'rfc' => '',
        'nss' => '',
        'c_estudios' => '',
        'c_recomendacion' => '',
        'a_infonavit' => '',
        'c_antecedentes' => '',
        'contrato' => '',
        'sd' => 0,
        'documentos' => '',
        'estado' => 1,
    ]);
    $timezoneVersions = $timezoneVersions !== [] ? $timezoneVersions : [[
        'timezone_iana' => $timezone,
        'valid_from_utc' => '2020-01-01T00:00:00Z',
    ]];
    $timezoneWriter->appendVersions($workplaceId, $timezoneVersions, null, 'P3-L3-4 disposable test');
    $assignmentIds = [];
    foreach ($assignments as $assignment) {
        $assignmentIds[] = $assignmentWriter->appendVersion(
            $workerId,
            $workplaceId,
            $assignment['valid_from_utc'],
            $assignment['valid_to_utc'] ?? null,
        );
    }
    $scheduleId = $createSchedule
        ? $scheduleWriter->appendVersion($workerId, $workplaceId, '2020-01-01', null, $week($date, $state, $segments))
        : null;
    return ['worker_id' => $workerId, 'workplace_id' => $workplaceId, 'schedule_id' => $scheduleId, 'assignment_ids' => $assignmentIds];
};
$domainTables = [
    'op_rh_personal',
    'op_rh_localidades',
    'op_rh_personal_horario',
    'op_rh_personal_horario_hist',
    'op_rh_localidades_horario',
    'op_rh_personal_horario_programar',
    'op_rh_personal_horario_programar_detalle',
    'op_rh_personal_asistencia',
    'op_rh_localidad_timezone_version',
    'op_rh_personal_localidad_version',
    'op_rh_personal_horario_version',
    'op_rh_personal_horario_version_dia',
    'op_rh_personal_horario_version_segmento',
];
$snapshot = static function () use ($domainTables): string {
    $rows = [];
    foreach ($domainTables as $table) {
        $rows[$table] = Capsule::table($table)->orderBy('id')->get()->toArray();
    }
    return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
};

$connection->beginTransaction();
try {
    $normal = $makeFixture('normal', '2026-10-05', 'America/Mexico_City');
    $normalResult = $resolver->resolve($normal['worker_id'], $normal['workplace_id'], '2026-10-05');
    $assert(
        $normalResult->status === 'SCHEDULED'
            && $normalResult->segments[0]['start_utc'] === '2026-10-05 14:00:00.000000'
            && $normalResult->timezone['version_id'] > 0
            && $normalResult->scheduleVersionId === $normal['schedule_id'],
        'MariaDB schedule history lookup and local-date materialization'
    );

    $adjacent = $makeFixture('adjacent-memberships', '2026-10-05', 'America/Mexico_City', 'SCHEDULED', [
        ['sequence_number' => 1, 'start_local_time' => '08:00', 'end_local_time' => '16:00', 'end_day_offset' => 0],
    ], [
        ['valid_from_utc' => '2026-10-05T14:00:00Z', 'valid_to_utc' => '2026-10-05T18:00:00Z'],
        ['valid_from_utc' => '2026-10-05T18:00:00Z', 'valid_to_utc' => '2026-10-05T22:00:00Z'],
    ]);
    $adjacentResult = $resolver->resolve($adjacent['worker_id'], $adjacent['workplace_id'], '2026-10-05');
    $assert($adjacentResult->status === 'SCHEDULED' && count($adjacentResult->assignmentVersionIds) === 2, 'MariaDB adjacent assignment versions fully cover a segment');

    $gap = $makeFixture('membership-gap', '2026-10-05', 'America/Mexico_City', 'SCHEDULED', [
        ['sequence_number' => 1, 'start_local_time' => '08:00', 'end_local_time' => '16:00', 'end_day_offset' => 0],
    ], [['valid_from_utc' => '2026-10-05T14:00:00Z', 'valid_to_utc' => '2026-10-05T18:00:00Z']]);
    $gapResult = $resolver->resolve($gap['worker_id'], $gap['workplace_id'], '2026-10-05');
    $assert($gapResult->status === 'UNKNOWN' && $gapResult->reasonCode === 'ASSIGNMENT_NOT_COVERING_SCHEDULE', 'MariaDB membership ending mid-segment is unresolved');

    $break = $makeFixture('break-gap', '2026-10-05', 'America/Mexico_City', 'SCHEDULED', [
        ['sequence_number' => 1, 'start_local_time' => '08:00', 'end_local_time' => '12:00', 'end_day_offset' => 0],
        ['sequence_number' => 2, 'start_local_time' => '14:00', 'end_local_time' => '18:00', 'end_day_offset' => 0],
    ], [
        ['valid_from_utc' => '2026-10-05T14:00:00Z', 'valid_to_utc' => '2026-10-05T18:00:00Z'],
        ['valid_from_utc' => '2026-10-05T20:00:00Z', 'valid_to_utc' => '2026-10-06T00:00:00Z'],
    ]);
    $breakResult = $resolver->resolve($break['worker_id'], $break['workplace_id'], '2026-10-05');
    $assert(
        $breakResult->status === 'SCHEDULED'
            && count($breakResult->segments) === 2
            && $breakResult->segments[0]['ordinal'] === 1
            && $breakResult->segments[1]['ordinal'] === 2
            && $breakResult->segments[0]['start_local_time'] === '08:00:00'
            && $breakResult->segments[1]['start_local_time'] === '14:00:00',
        'MariaDB preserves ordered schedule segments and ignores the unscheduled membership gap'
    );

    $off = $makeFixture('day-off', '2026-10-05', 'America/Mexico_City', 'DAY_OFF', []);
    $offResult = $resolver->resolve($off['worker_id'], $off['workplace_id'], '2026-10-05');
    $assert($offResult->status === 'DAY_OFF' && $offResult->segments === [], 'MariaDB explicit DAY_OFF uses local-midnight membership anchor');
    $offLateMember = $makeFixture('day-off-late-membership', '2026-10-05', 'America/Mexico_City', 'DAY_OFF', [], [['valid_from_utc' => '2026-10-05T07:00:00Z', 'valid_to_utc' => null]]);
    $offLateResult = $resolver->resolve($offLateMember['worker_id'], $offLateMember['workplace_id'], '2026-10-05');
    $assert($offLateResult->status === 'UNKNOWN' && $offLateResult->reasonCode === 'NOT_ASSIGNED', 'MariaDB DAY_OFF requires membership at local midnight');

    $unscheduled = $makeFixture('unscheduled-anchor', '2026-10-05', 'America/Mexico_City', 'UNSCHEDULED', []);
    $unscheduledResult = $resolver->resolve($unscheduled['worker_id'], $unscheduled['workplace_id'], '2026-10-05');
    $assert(
        $unscheduledResult->status === 'UNSCHEDULED'
            && $unscheduledResult->segments === []
            && $unscheduledResult->localDayStartUtc === '2026-10-05 06:00:00.000000',
        'MariaDB UNSCHEDULED authority is anchored at local-day-start UTC membership'
    );
    $unscheduledLateMember = $makeFixture('unscheduled-late-membership', '2026-10-05', 'America/Mexico_City', 'UNSCHEDULED', [], [['valid_from_utc' => '2026-10-05T07:00:00Z', 'valid_to_utc' => null]]);
    $unscheduledLateResult = $resolver->resolve($unscheduledLateMember['worker_id'], $unscheduledLateMember['workplace_id'], '2026-10-05');
    $assert($unscheduledLateResult->status === 'UNKNOWN' && $unscheduledLateResult->reasonCode === 'NOT_ASSIGNED', 'MariaDB UNSCHEDULED without local-midnight membership remains unresolved');

    $noSchedule = $makeFixture('no-schedule-fallback', '2026-10-05', 'America/Mexico_City', createSchedule: false);
    Capsule::table('op_rh_personal_horario')->insert([
        'id_estacion' => $noSchedule['workplace_id'],
        'id_personal' => $noSchedule['worker_id'],
        'horario' => 'Synthetic legacy personal schedule',
        'dia' => 'Lunes',
        'hora_entrada' => '07:00:00',
        'hora_salida' => '15:00:00',
    ]);
    Capsule::table('op_rh_localidades_horario')->insert([
        'id_estacion' => $noSchedule['workplace_id'],
        'titulo' => 'Synthetic station default',
        'hora_entrada' => '06:00:00',
        'hora_salida' => '14:00:00',
    ]);
    Capsule::table('op_rh_personal_horario_programar')->insert([
        'id_estacion' => $noSchedule['workplace_id'],
        'fecha' => '2026-10-05',
        'estado' => 1,
    ]);
    Capsule::table('op_rh_personal_horario_programar_detalle')->insert([
        'id_reporte' => 1,
        'id_estacion' => $noSchedule['workplace_id'],
        'id_personal' => $noSchedule['worker_id'],
        'horario' => 'Synthetic special programming',
        'dia' => 'Lunes',
        'hora_entrada' => '05:00:00',
        'hora_salida' => '13:00:00',
    ]);
    $noScheduleResult = $resolver->resolve($noSchedule['worker_id'], $noSchedule['workplace_id'], '2026-10-05');
    $assert(
        $noScheduleResult->status === 'UNKNOWN'
            && $noScheduleResult->reasonCode === 'SCHEDULE_UNRESOLVED'
            && $noScheduleResult->segments === [],
        'MariaDB does not fallback to id_estacion, legacy personal/station schedule, or special programming'
    );

    $multiWorkplaceA = $makeFixture('multi-workplace-a', '2026-10-05', 'America/Mexico_City');
    $multiWorkplaceB = $makeFixture(
        'multi-workplace-b',
        '2026-10-05',
        'UTC',
        'SCHEDULED',
        [['sequence_number' => 1, 'start_local_time' => '09:00', 'end_local_time' => '17:00', 'end_day_offset' => 0]],
        [['valid_from_utc' => '2020-01-01T00:00:00Z', 'valid_to_utc' => null]],
        [],
        $multiWorkplaceA['worker_id'],
    );
    $multiAResult = $resolver->resolve($multiWorkplaceA['worker_id'], $multiWorkplaceA['workplace_id'], '2026-10-05');
    $multiBResult = $resolver->resolve($multiWorkplaceB['worker_id'], $multiWorkplaceB['workplace_id'], '2026-10-05');
    $assert(
        $multiAResult->status === 'SCHEDULED'
            && $multiBResult->status === 'SCHEDULED'
            && $multiAResult->workplaceId !== $multiBResult->workplaceId
            && $multiAResult->timezone['timezone_iana'] === 'America/Mexico_City'
            && $multiBResult->timezone['timezone_iana'] === 'UTC'
            && $multiAResult->segments[0]['start_utc'] !== $multiBResult->segments[0]['start_utc'],
        'MariaDB resolves two Workplaces independently for one canonical worker'
    );

    $zoneHistory = $makeFixture('self-zone', '2026-10-05', 'UTC', 'SCHEDULED', [['sequence_number' => 1, 'start_local_time' => '08:00', 'end_local_time' => '16:00', 'end_day_offset' => 0]], [['valid_from_utc' => '2020-01-01T00:00:00Z', 'valid_to_utc' => null]], [
        ['timezone_iana' => 'America/Tijuana', 'valid_from_utc' => '2026-01-01T00:00:00Z', 'valid_to_utc' => '2026-10-05T06:00:00Z'],
        ['timezone_iana' => 'America/Mexico_City', 'valid_from_utc' => '2026-10-05T06:00:00Z'],
    ]);
    $zoneResult = $resolver->resolve($zoneHistory['worker_id'], $zoneHistory['workplace_id'], '2026-10-05');
    $assert($zoneResult->status === 'SCHEDULED' && $zoneResult->timezone['timezone_iana'] === 'America/Mexico_City', 'MariaDB timezone history selects the self-consistent local-midnight version');

    $recordedFixture = $makeFixture('as-recorded-consistency', '2026-10-05', 'America/Mexico_City');
    $recordedTimezoneId = (int)Capsule::table('op_rh_localidad_timezone_version')
        ->where('workplace_id', $recordedFixture['workplace_id'])
        ->value('id');
    $clockValue = '2026-01-02T00:00:00Z';
    $correctedTimezoneIds = $timezoneWriter->appendVersions($recordedFixture['workplace_id'], [[
        'timezone_iana' => 'America/Tijuana',
        'valid_from_utc' => '2020-01-01T00:00:00Z',
        'supersedes_id' => $recordedTimezoneId,
    ]], null, 'P3-L3-4 synthetic recorded-as-of correction');
    $correctedAssignmentId = $assignmentWriter->appendVersion(
        $recordedFixture['worker_id'],
        $recordedFixture['workplace_id'],
        '2020-01-01T00:00:00Z',
        null,
        null,
        'P3-L3-4 synthetic recorded-as-of correction',
        $recordedFixture['assignment_ids'][0],
    );
    $correctedScheduleId = $scheduleWriter->appendVersion(
        $recordedFixture['worker_id'],
        $recordedFixture['workplace_id'],
        '2020-01-01',
        null,
        $week('2026-10-05', 'SCHEDULED', [['sequence_number' => 1, 'start_local_time' => '09:00', 'end_local_time' => '17:00', 'end_day_offset' => 0]]),
        null,
        'P3-L3-4 synthetic recorded-as-of correction',
        $recordedFixture['schedule_id'],
    );
    $historicalResult = $resolver->resolve($recordedFixture['worker_id'], $recordedFixture['workplace_id'], '2026-10-05', '2026-01-01T12:00:00Z');
    $currentResult = $resolver->resolve($recordedFixture['worker_id'], $recordedFixture['workplace_id'], '2026-10-05', '2026-01-02T00:00:00Z');
    $assert(
        $historicalResult->status === 'SCHEDULED'
            && $historicalResult->timezone['timezone_iana'] === 'America/Mexico_City'
            && $historicalResult->scheduleVersionId === $recordedFixture['schedule_id']
            && $historicalResult->assignmentVersionIds === [$recordedFixture['assignment_ids'][0]]
            && $historicalResult->segments[0]['start_local_time'] === '08:00:00'
            && $historicalResult->segments[0]['start_utc'] === '2026-10-05 14:00:00.000000'
            && $currentResult->status === 'SCHEDULED'
            && $currentResult->timezone['timezone_iana'] === 'America/Tijuana'
            && $currentResult->timezone['version_id'] === $correctedTimezoneIds[0]
            && $currentResult->scheduleVersionId === $correctedScheduleId
            && $currentResult->assignmentVersionIds === [$correctedAssignmentId]
            && $currentResult->segments[0]['start_local_time'] === '09:00:00'
            && $currentResult->segments[0]['start_utc'] === '2026-10-05 16:00:00.000000',
        'MariaDB as-recorded and current views apply one consistent history perspective across all three domains'
    );

    $intraday = $makeFixture('zone-transition', '2026-10-05', 'UTC', 'SCHEDULED', [['sequence_number' => 1, 'start_local_time' => '08:00', 'end_local_time' => '16:00', 'end_day_offset' => 0]], [['valid_from_utc' => '2020-01-01T00:00:00Z', 'valid_to_utc' => null]], [
        ['timezone_iana' => 'America/Mexico_City', 'valid_from_utc' => '2020-01-01T00:00:00Z', 'valid_to_utc' => '2026-10-05T18:00:00Z'],
        ['timezone_iana' => 'America/Tijuana', 'valid_from_utc' => '2026-10-05T18:00:00Z'],
    ]);
    $intradayResult = $resolver->resolve($intraday['worker_id'], $intraday['workplace_id'], '2026-10-05');
    $assert($intradayResult->status === 'UNKNOWN' && $intradayResult->reasonCode === 'TIMEZONE_TRANSITION_WITHIN_WORKDAY', 'MariaDB timezone history change within work interval is conservative');

    $night = $makeFixture('night-shift', '2026-10-05', 'America/Mexico_City', 'SCHEDULED', [['sequence_number' => 1, 'start_local_time' => '22:00', 'end_local_time' => '06:00', 'end_day_offset' => 1]]);
    $nightResult = $resolver->resolve($night['worker_id'], $night['workplace_id'], '2026-10-05');
    $assert($nightResult->status === 'SCHEDULED' && $nightResult->segments[0]['end_local_datetime'] === '2026-10-06 06:00:00' && $nightResult->localWorkdayDate === '2026-10-05', 'MariaDB cross-midnight materialization retains the local workday');

    $dstNormal = $makeFixture('dst-normal', '2026-02-02', 'America/New_York');
    $dstGap = $makeFixture('dst-gap', '2026-03-08', 'America/New_York', 'SCHEDULED', [['sequence_number' => 1, 'start_local_time' => '02:30', 'end_local_time' => '04:00', 'end_day_offset' => 0]]);
    $dstFold = $makeFixture('dst-fold', '2026-11-01', 'America/New_York', 'SCHEDULED', [['sequence_number' => 1, 'start_local_time' => '01:30', 'end_local_time' => '02:30', 'end_day_offset' => 0]]);
    $normalDstResult = $resolver->resolve($dstNormal['worker_id'], $dstNormal['workplace_id'], '2026-02-02');
    $gapDstResult = $resolver->resolve($dstGap['worker_id'], $dstGap['workplace_id'], '2026-03-08');
    $foldDstResult = $resolver->resolve($dstFold['worker_id'], $dstFold['workplace_id'], '2026-11-01');
    $assert($normalDstResult->status === 'SCHEDULED' && $normalDstResult->segments[0]['start_utc'] === '2026-02-02 13:00:00.000000', 'MariaDB-backed resolver deterministically materializes a normal DST-observing date');
    $assert($gapDstResult->status === 'UNKNOWN' && $gapDstResult->reasonCode === 'TIME_NONEXISTENT', 'MariaDB-backed resolver rejects a nonexistent DST wall time');
    $assert($foldDstResult->status === 'UNKNOWN' && $foldDstResult->reasonCode === 'TIME_AMBIGUOUS', 'MariaDB-backed resolver rejects an ambiguous DST wall time');

    $corrupt = $makeFixture('invalid-assignment-overlap', '2026-10-05', 'America/Mexico_City');
    Capsule::table('op_rh_personal_localidad_version')->insert([
        'worker_id' => $corrupt['worker_id'],
        'workplace_id' => $corrupt['workplace_id'],
        'valid_from_utc' => '2020-01-01 00:00:00.000000',
        'valid_to_utc' => null,
        'recorded_at_utc' => '2026-01-01 00:00:00.000000',
        'recorded_by' => null,
        'reason' => 'deliberately corrupt transaction-local fixture',
        'supersedes_id' => null,
    ]);
    $corruptResult = $resolver->resolve($corrupt['worker_id'], $corrupt['workplace_id'], '2026-10-05');
    $assert($corruptResult->status === 'DATA_INVALID' && $corruptResult->reasonCode === 'INVALID_PERSISTED_STATE', 'MariaDB corrupt assignment history fails closed');

    $beforeRead = $snapshot();
    $resolver->resolve($normal['worker_id'], $normal['workplace_id'], '2026-10-05');
    $assert($snapshot() === $beforeRead, 'MariaDB resolution performs zero writes to timezone, assignment, or schedule history tables');
} catch (Throwable $exception) {
    fwrite(STDERR, 'P3_L3_4_MARIADB_ABORTED: ' . get_class($exception) . ': ' . $exception->getMessage() . "\n");
    $failed++;
} finally {
    if ($connection->transactionLevel() > 0) {
        $connection->rollBack();
    }
}

printf("P3_L3_4_MARIADB_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passed, $failed);
exit($failed === 0 ? 0 : 1);
