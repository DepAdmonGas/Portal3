<?php

declare(strict_types=1);

use App\Services\EffectiveScheduleResolver;
use App\Services\PersonalScheduleVersionService;
use App\Services\WorkplaceTimezoneVersionService;
use App\Services\WorkerWorkplaceAssignmentVersionService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../scripts/migrate_workplace_timezone_versions.php';
require_once __DIR__ . '/../scripts/migrate_worker_workplace_assignment_versions.php';
require_once __DIR__ . '/../scripts/migrate_personal_schedule_versions.php';

$capsule = new Capsule();
$capsule->addConnection([
    'driver' => 'sqlite',
    'database' => ':memory:',
    'prefix' => '',
    'foreign_key_constraints' => true,
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();
Capsule::connection()->statement('PRAGMA foreign_keys = ON');

Capsule::schema()->create('op_rh_personal', static function (Blueprint $table): void {
    $table->increments('id');
    $table->integer('id_estacion')->default(0);
});
Capsule::schema()->create('op_rh_localidades', static function (Blueprint $table): void {
    $table->increments('id');
    $table->integer('numlista')->unique();
    $table->string('localidad');
    $table->string('recuperacion_vapores')->default('');
});
Capsule::schema()->create('op_rh_personal_horario', static function (Blueprint $table): void {
    $table->increments('id');
    $table->integer('id_estacion');
    $table->integer('id_personal');
    $table->string('dia');
    $table->time('hora_entrada')->nullable();
    $table->time('hora_salida')->nullable();
});
Capsule::schema()->create('op_rh_personal_horario_hist', static function (Blueprint $table): void {
    $table->increments('id');
    $table->integer('id_estacion');
    $table->integer('id_personal');
    $table->string('dia');
    $table->time('hora_entrada')->nullable();
    $table->time('hora_salida')->nullable();
});
Capsule::schema()->create('op_rh_localidades_horario', static function (Blueprint $table): void {
    $table->increments('id');
    $table->integer('id_estacion');
    $table->string('titulo');
    $table->time('hora_entrada');
    $table->time('hora_salida');
});
Capsule::schema()->create('op_rh_personal_horario_programar', static function (Blueprint $table): void {
    $table->increments('id');
    $table->integer('id_estacion');
    $table->date('fecha');
    $table->integer('estado');
});
Capsule::schema()->create('op_rh_personal_horario_programar_detalle', static function (Blueprint $table): void {
    $table->increments('id');
    $table->integer('id_reporte');
    $table->integer('id_estacion');
    $table->integer('id_personal');
    $table->string('horario');
    $table->string('dia');
    $table->time('hora_entrada');
    $table->time('hora_salida');
});
Capsule::schema()->create('op_rh_personal_asistencia', static function (Blueprint $table): void {
    $table->increments('id');
    $table->integer('id_personal');
});
WorkplaceTimezoneVersionsMigration::up();
WorkerWorkplaceAssignmentVersionsMigration::up();
PersonalScheduleVersionsMigration::up();

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
$week = static function (string $date, string $state, array $segments): array {
    $targetWeekday = (int)(new DateTimeImmutable($date, new DateTimeZone('UTC')))->format('N');
    $days = [];
    for ($weekday = 1; $weekday <= 7; $weekday++) {
        $days[] = [
            'weekday_iso' => $weekday,
            'day_state' => $weekday === $targetWeekday ? $state : 'DAY_OFF',
            'segments' => $weekday === $targetWeekday ? $segments : [],
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
    bool $createSchedule = true,
    ?int $reuseWorker = null,
) use (&$sequence, $clock, $timezoneWriter, $assignmentWriter, $scheduleWriter, $week): array {
    $workplaceId = (int)Capsule::table('op_rh_localidades')->insertGetId([
        'numlista' => 980000 + ++$sequence,
        'localidad' => 'P3-L3-4 ' . $name,
        'recuperacion_vapores' => '',
    ]);
    $workerId = $reuseWorker ?? (int)Capsule::table('op_rh_personal')->insertGetId(['id_estacion' => $workplaceId + 10000]);
    $timezoneWriter->appendVersions($workplaceId, [[
        'timezone_iana' => $timezone,
        'valid_from_utc' => '2020-01-01T00:00:00Z',
    ]], null, 'P3-L3-4 disposable test');
    $assignmentIds = [];
    foreach ($assignments as $assignment) {
        $assignmentIds[] = $assignmentWriter->appendVersion(
            $workerId,
            $workplaceId,
            $assignment['valid_from_utc'],
            $assignment['valid_to_utc'] ?? null,
        );
    }
    $scheduleId = null;
    if ($createSchedule) {
        $scheduleId = $scheduleWriter->appendVersion($workerId, $workplaceId, '2020-01-01', null, $week($date, $state, $segments));
    }
    return ['worker_id' => $workerId, 'workplace_id' => $workplaceId, 'schedule_id' => $scheduleId, 'assignment_ids' => $assignmentIds];
};

$regular = $makeFixture('regular', '2026-10-05', 'America/Mexico_City');
$result = $resolver->resolve($regular['worker_id'], $regular['workplace_id'], '2026-10-05');
$assert(
    $result->status === 'SCHEDULED' && $result->reasonCode === null
        && $result->segments[0]['start_utc'] === '2026-10-05 14:00:00.000000'
        && $result->segments[0]['end_utc'] === '2026-10-05 22:00:00.000000'
        && $result->timezone['version_id'] > 0 && $result->scheduleVersionId === $regular['schedule_id'],
    'normal scheduled resolution materializes boundaries and provenance'
);
$assert(
    $result->segments[0]['id'] > 0 && $result->segments[0]['ordinal'] === 1
        && $result->assignmentVersionIds === $regular['assignment_ids']
        && $result->assignmentPrimaryAnchorUtc === $result->segments[0]['start_utc'],
    'scheduled result preserves segment identity, assignment provenance, and primary anchor'
);
$assert(
    $result->workplaceId === $regular['workplace_id']
        && $result->workerId === $regular['worker_id']
        && $result->status === 'SCHEDULED',
    'legacy id_estacion disagreement does not override canonical worker-Workplace membership'
);

$dayOff = $makeFixture('day-off', '2026-10-05', 'America/Mexico_City', 'DAY_OFF', []);
$dayOffResult = $resolver->resolve($dayOff['worker_id'], $dayOff['workplace_id'], '2026-10-05');
$assert($dayOffResult->status === 'DAY_OFF' && $dayOffResult->segments === [], 'explicit DAY_OFF is distinct and has zero segments');
$dayOffLateMember = $makeFixture('day-off-assigned-after-anchor', '2026-10-05', 'America/Mexico_City', 'DAY_OFF', [], [['valid_from_utc' => '2026-10-05T07:00:00Z', 'valid_to_utc' => null]]);
$dayOffLateResult = $resolver->resolve($dayOffLateMember['worker_id'], $dayOffLateMember['workplace_id'], '2026-10-05');
$assert($dayOffLateResult->status === 'UNKNOWN' && $dayOffLateResult->reasonCode === 'NOT_ASSIGNED', 'DAY_OFF membership beginning after local midnight does not qualify');
$dayOffNoMember = $makeFixture('day-off-unassigned', '2026-10-05', 'America/Mexico_City', 'DAY_OFF', [], [], true);
$dayOffNoMemberResult = $resolver->resolve($dayOffNoMember['worker_id'], $dayOffNoMember['workplace_id'], '2026-10-05');
$assert($dayOffNoMemberResult->status === 'UNKNOWN' && $dayOffNoMemberResult->reasonCode === 'NOT_ASSIGNED', 'DAY_OFF without membership at local midnight is not authoritative');
$unscheduled = $makeFixture('unscheduled', '2026-10-05', 'America/Mexico_City', 'UNSCHEDULED', []);
$unscheduledResult = $resolver->resolve($unscheduled['worker_id'], $unscheduled['workplace_id'], '2026-10-05');
$assert($unscheduledResult->status === 'UNSCHEDULED' && $unscheduledResult->segments === [], 'explicit UNSCHEDULED remains distinct from DAY_OFF');
$unscheduledLateMember = $makeFixture('unscheduled-assigned-after-anchor', '2026-10-05', 'America/Mexico_City', 'UNSCHEDULED', [], [['valid_from_utc' => '2026-10-05T07:00:00Z', 'valid_to_utc' => null]]);
$unscheduledLateResult = $resolver->resolve($unscheduledLateMember['worker_id'], $unscheduledLateMember['workplace_id'], '2026-10-05');
$assert($unscheduledLateResult->status === 'UNKNOWN' && $unscheduledLateResult->reasonCode === 'NOT_ASSIGNED', 'UNSCHEDULED uses local-midnight membership rather than a later point');
$unscheduledNoMember = $makeFixture('unscheduled-unassigned', '2026-10-05', 'America/Mexico_City', 'UNSCHEDULED', [], []);
$unscheduledNoMemberResult = $resolver->resolve($unscheduledNoMember['worker_id'], $unscheduledNoMember['workplace_id'], '2026-10-05');
$assert($unscheduledNoMemberResult->status === 'UNKNOWN' && $unscheduledNoMemberResult->reasonCode === 'NOT_ASSIGNED', 'UNSCHEDULED without membership remains unresolved');

$noSchedule = $makeFixture('no-schedule', '2026-10-05', 'America/Mexico_City', createSchedule: false);
Capsule::table('op_rh_personal_horario')->insert(['id_estacion' => $noSchedule['workplace_id'], 'id_personal' => $noSchedule['worker_id'], 'dia' => 'Lunes', 'hora_entrada' => '07:00', 'hora_salida' => '15:00']);
Capsule::table('op_rh_personal_horario_hist')->insert(['id_estacion' => $noSchedule['workplace_id'], 'id_personal' => $noSchedule['worker_id'], 'dia' => 'Lunes', 'hora_entrada' => '07:00', 'hora_salida' => '15:00']);
Capsule::table('op_rh_localidades_horario')->insert(['id_estacion' => $noSchedule['workplace_id'], 'titulo' => 'legacy', 'hora_entrada' => '07:00', 'hora_salida' => '15:00']);
$noScheduleResult = $resolver->resolve($noSchedule['worker_id'], $noSchedule['workplace_id'], '2026-10-05');
$assert($noScheduleResult->status === 'UNKNOWN' && $noScheduleResult->reasonCode === 'SCHEDULE_UNRESOLVED', 'no personal schedule does not fallback to legacy or station defaults');

$multi = $makeFixture('multi-segment', '2026-10-05', 'America/Mexico_City', 'SCHEDULED', [
    ['sequence_number' => 1, 'start_local_time' => '08:00', 'end_local_time' => '12:00', 'end_day_offset' => 0],
    ['sequence_number' => 2, 'start_local_time' => '14:00', 'end_local_time' => '18:00', 'end_day_offset' => 0],
], [
    ['valid_from_utc' => '2026-10-05T14:00:00Z', 'valid_to_utc' => '2026-10-05T18:00:00Z'],
    ['valid_from_utc' => '2026-10-05T20:00:00Z', 'valid_to_utc' => '2026-10-06T00:00:00Z'],
]);
$multiResult = $resolver->resolve($multi['worker_id'], $multi['workplace_id'], '2026-10-05');
$assert(
    $multiResult->status === 'SCHEDULED' && count($multiResult->segments) === 2
        && $multiResult->segments[0]['ordinal'] === 1 && $multiResult->segments[1]['ordinal'] === 2
        && $multiResult->segments[0]['assignment_version_ids'] !== $multiResult->segments[1]['assignment_version_ids'],
    'multiple schedule segments remain ordered and membership gap between them is permitted'
);
$gap = $makeFixture('assignment-gap-inside', '2026-10-05', 'America/Mexico_City', 'SCHEDULED', [
    ['sequence_number' => 1, 'start_local_time' => '08:00', 'end_local_time' => '16:00', 'end_day_offset' => 0],
], [
    ['valid_from_utc' => '2026-10-05T14:00:00Z', 'valid_to_utc' => '2026-10-05T18:00:00Z'],
    ['valid_from_utc' => '2026-10-05T20:00:00Z', 'valid_to_utc' => '2026-10-05T22:00:00Z'],
]);
$gapResult = $resolver->resolve($gap['worker_id'], $gap['workplace_id'], '2026-10-05');
$assert($gapResult->status === 'UNKNOWN' && $gapResult->reasonCode === 'ASSIGNMENT_NOT_COVERING_SCHEDULE', 'assignment ending mid-segment fails full interval coverage');
$adjacent = $makeFixture('adjacent-assignment', '2026-10-05', 'America/Mexico_City', 'SCHEDULED', [
    ['sequence_number' => 1, 'start_local_time' => '08:00', 'end_local_time' => '16:00', 'end_day_offset' => 0],
], [
    ['valid_from_utc' => '2026-10-05T14:00:00Z', 'valid_to_utc' => '2026-10-05T18:00:00Z'],
    ['valid_from_utc' => '2026-10-05T18:00:00Z', 'valid_to_utc' => '2026-10-05T22:00:00Z'],
]);
$adjacentResult = $resolver->resolve($adjacent['worker_id'], $adjacent['workplace_id'], '2026-10-05');
$assert($adjacentResult->status === 'SCHEDULED' && count($adjacentResult->assignmentVersionIds) === 2, 'adjacent assignment versions jointly cover one scheduled interval');

$tzAbsent = $makeFixture('no-timezone', '2026-10-05', 'America/Mexico_City');
Capsule::table('op_rh_localidad_timezone_version')->where('workplace_id', $tzAbsent['workplace_id'])->delete();
$tzAbsentResult = $resolver->resolve($tzAbsent['worker_id'], $tzAbsent['workplace_id'], '2026-10-05');
$assert($tzAbsentResult->status === 'UNKNOWN' && $tzAbsentResult->reasonCode === 'TIMEZONE_UNRESOLVED', 'timezone absence is unresolved without fallback');

$selfConsistent = $makeFixture('self-consistent-zone', '2026-10-05', 'UTC', 'SCHEDULED', [['sequence_number' => 1, 'start_local_time' => '08:00', 'end_local_time' => '16:00', 'end_day_offset' => 0]]);
Capsule::table('op_rh_localidad_timezone_version')->where('workplace_id', $selfConsistent['workplace_id'])->delete();
$timezoneWriter->appendVersions($selfConsistent['workplace_id'], [
    ['timezone_iana' => 'America/Tijuana', 'valid_from_utc' => '2026-01-01T00:00:00Z', 'valid_to_utc' => '2026-10-05T06:00:00Z'],
    ['timezone_iana' => 'America/Mexico_City', 'valid_from_utc' => '2026-10-05T06:00:00Z'],
], null, 'self-consistent selection fixture');
$selfResult = $resolver->resolve($selfConsistent['worker_id'], $selfConsistent['workplace_id'], '2026-10-05');
$assert($selfResult->status === 'SCHEDULED' && $selfResult->timezone['timezone_iana'] === 'America/Mexico_City', 'timezone is selected by its own local-midnight UTC instant');

$transition = $makeFixture('timezone-transition', '2026-10-05', 'UTC');
Capsule::table('op_rh_localidad_timezone_version')->where('workplace_id', $transition['workplace_id'])->delete();
$timezoneWriter->appendVersions($transition['workplace_id'], [
    ['timezone_iana' => 'America/Mexico_City', 'valid_from_utc' => '2020-01-01T00:00:00Z', 'valid_to_utc' => '2026-10-05T18:00:00Z'],
    ['timezone_iana' => 'America/Tijuana', 'valid_from_utc' => '2026-10-05T18:00:00Z'],
], null, 'intraday transition fixture');
$transitionResult = $resolver->resolve($transition['worker_id'], $transition['workplace_id'], '2026-10-05');
$assert($transitionResult->status === 'UNKNOWN' && $transitionResult->reasonCode === 'TIMEZONE_TRANSITION_WITHIN_WORKDAY', 'resolver does not switch timezone inside a scheduled segment');

$night = $makeFixture('night-shift', '2026-10-05', 'America/Mexico_City', 'SCHEDULED', [
    ['sequence_number' => 1, 'start_local_time' => '22:00', 'end_local_time' => '06:00', 'end_day_offset' => 1],
]);
$nightResult = $resolver->resolve($night['worker_id'], $night['workplace_id'], '2026-10-05');
$assert(
    $nightResult->status === 'SCHEDULED'
        && $nightResult->segments[0]['start_local_datetime'] === '2026-10-05 22:00:00'
        && $nightResult->segments[0]['end_local_datetime'] === '2026-10-06 06:00:00'
        && $nightResult->segments[0]['start_utc'] === '2026-10-06 04:00:00.000000'
        && $nightResult->localWorkdayDate === '2026-10-05',
    'night shift preserves local workday and explicit next-day segment boundary'
);

$dstNormal = $makeFixture('dst-normal', '2026-02-02', 'America/New_York');
$dstNormalResult = $resolver->resolve($dstNormal['worker_id'], $dstNormal['workplace_id'], '2026-02-02');
$assert($dstNormalResult->status === 'SCHEDULED' && $dstNormalResult->segments[0]['start_utc'] === '2026-02-02 13:00:00.000000', 'DST-observing timezone resolves a normal date deterministically');
$dstGap = $makeFixture('dst-gap', '2026-03-08', 'America/New_York', 'SCHEDULED', [['sequence_number' => 1, 'start_local_time' => '02:30', 'end_local_time' => '04:00', 'end_day_offset' => 0]]);
$dstGapResult = $resolver->resolve($dstGap['worker_id'], $dstGap['workplace_id'], '2026-03-08');
$assert($dstGapResult->status === 'UNKNOWN' && $dstGapResult->reasonCode === 'TIME_NONEXISTENT', 'nonexistent DST boundary is rejected without normalization');
$dstFold = $makeFixture('dst-fold', '2026-11-01', 'America/New_York', 'SCHEDULED', [['sequence_number' => 1, 'start_local_time' => '01:30', 'end_local_time' => '02:30', 'end_day_offset' => 0]]);
$dstFoldResult = $resolver->resolve($dstFold['worker_id'], $dstFold['workplace_id'], '2026-11-01');
$assert($dstFoldResult->status === 'UNKNOWN' && $dstFoldResult->reasonCode === 'TIME_AMBIGUOUS', 'repeated DST boundary is rejected without choosing an offset');

$large = $makeFixture('large-offset', '2026-10-05', 'UTC', 'SCHEDULED', [['sequence_number' => 1, 'start_local_time' => '08:00', 'end_local_time' => '09:00', 'end_day_offset' => 100000]]);
$largeResult = $resolver->resolve($large['worker_id'], $large['workplace_id'], '2026-10-05');
$assert($largeResult->status === 'SCHEDULED' && substr($largeResult->segments[0]['end_local_datetime'], 0, 4) > '2200', 'large valid end_day_offset is materialized without a business cap');

$twoPlacesWorker = (int)Capsule::table('op_rh_personal')->insertGetId(['id_estacion' => 0]);
$placeA = $makeFixture('multi-place-scheduled', '2026-10-05', 'America/Mexico_City', 'SCHEDULED', [['sequence_number' => 1, 'start_local_time' => '08:00', 'end_local_time' => '16:00', 'end_day_offset' => 0]], [['valid_from_utc' => '2020-01-01T00:00:00Z', 'valid_to_utc' => null]], true, $twoPlacesWorker);
$placeB = $makeFixture('multi-place-off', '2026-10-05', 'America/Mexico_City', 'DAY_OFF', [], [['valid_from_utc' => '2020-01-01T00:00:00Z', 'valid_to_utc' => null]], true, $twoPlacesWorker);
$placeAResult = $resolver->resolve($twoPlacesWorker, $placeA['workplace_id'], '2026-10-05');
$placeBResult = $resolver->resolve($twoPlacesWorker, $placeB['workplace_id'], '2026-10-05');
$assert($placeAResult->status === 'SCHEDULED' && $placeBResult->status === 'DAY_OFF', 'same worker resolves two Workplaces independently');

$recorded = $makeFixture('as-recorded', '2026-10-05', 'America/Mexico_City');
$originalTimezoneId = (int)Capsule::table('op_rh_localidad_timezone_version')->where('workplace_id', $recorded['workplace_id'])->value('id');
$originalAssignmentId = $recorded['assignment_ids'][0];
$originalScheduleId = $recorded['schedule_id'];
$clockValue = '2026-04-01T00:00:00Z';
$correctedTimezoneId = $timezoneWriter->appendVersions($recorded['workplace_id'], [[
    'timezone_iana' => 'UTC',
    'valid_from_utc' => '2020-01-01T00:00:00Z',
    'supersedes_id' => $originalTimezoneId,
]], null, 'as-recorded timezone correction')[0];
$correctedAssignmentId = $assignmentWriter->appendVersion(
    $recorded['worker_id'],
    $recorded['workplace_id'],
    '2020-01-01T00:00:00Z',
    '2026-10-04T23:00:00Z',
    null,
    'as-recorded membership correction',
    $originalAssignmentId,
);
$correctedScheduleId = $scheduleWriter->appendVersion(
    $recorded['worker_id'],
    $recorded['workplace_id'],
    '2020-01-01',
    null,
    $week('2026-10-05', 'DAY_OFF', []),
    null,
    'as-recorded schedule correction',
    $originalScheduleId,
);
$pastResult = $resolver->resolve($recorded['worker_id'], $recorded['workplace_id'], '2026-10-05', '2026-02-01T00:00:00Z');
$currentResult = $resolver->resolve($recorded['worker_id'], $recorded['workplace_id'], '2026-10-05', '2026-05-01T00:00:00Z');
$assert(
    $pastResult->status === 'SCHEDULED' && $pastResult->scheduleVersionId === $originalScheduleId
        && $pastResult->timezone['version_id'] === $originalTimezoneId
        && $pastResult->assignmentVersionIds === [$originalAssignmentId],
    'earlier as-recorded view uses the historical schedule, timezone, and assignment together'
);
$assert(
    $currentResult->status === 'UNKNOWN' && $currentResult->reasonCode === 'NOT_ASSIGNED'
        && $currentResult->scheduleVersionId === $correctedScheduleId
        && $currentResult->timezone['version_id'] === $correctedTimezoneId
        && $currentResult->assignmentVersionIds === [],
    'current as-recorded view uses all superseding history without mixing perspectives'
);

$corrupt = $makeFixture('corrupt-timezone', '2026-10-05', 'UTC');
Capsule::table('op_rh_localidad_timezone_version')->insert([
    'workplace_id' => $corrupt['workplace_id'],
    'timezone_iana' => 'UTC',
    'valid_from_utc' => '2020-01-01 00:00:00.000000',
    'valid_to_utc' => null,
    'recorded_at_utc' => '2026-01-01 00:00:00.000000',
    'recorded_by' => null,
    'reason' => 'deliberately corrupt disposable state',
    'supersedes_id' => null,
]);
$corruptResult = $resolver->resolve($corrupt['worker_id'], $corrupt['workplace_id'], '2026-10-05');
$assert($corruptResult->status === 'DATA_INVALID' && $corruptResult->reasonCode === 'TIMEZONE_AMBIGUOUS', 'overlapping timezone candidates are rejected as persisted ambiguity');

$corruptAssignment = $makeFixture('corrupt-assignment', '2026-10-05', 'America/Mexico_City');
Capsule::table('op_rh_personal_localidad_version')->insert([
    'worker_id' => $corruptAssignment['worker_id'],
    'workplace_id' => $corruptAssignment['workplace_id'],
    'valid_from_utc' => '2020-01-01 00:00:00.000000',
    'valid_to_utc' => null,
    'recorded_at_utc' => '2026-01-01 00:00:00.000000',
    'recorded_by' => null,
    'reason' => 'deliberately corrupt disposable state',
    'supersedes_id' => null,
]);
$corruptAssignmentResult = $resolver->resolve($corruptAssignment['worker_id'], $corruptAssignment['workplace_id'], '2026-10-05');
$assert($corruptAssignmentResult->status === 'DATA_INVALID' && $corruptAssignmentResult->reasonCode === 'INVALID_PERSISTED_STATE', 'overlapping assignment facts are rejected conservatively');

foreach ([
    ['op_rh_personal_horario_programar', ['id_estacion' => $regular['workplace_id'], 'fecha' => '2026-10-05', 'estado' => 1]],
    ['op_rh_personal_horario_programar_detalle', ['id_reporte' => 1, 'id_estacion' => $regular['workplace_id'], 'id_personal' => $regular['worker_id'], 'horario' => 'legacy', 'dia' => 'Lunes', 'hora_entrada' => '07:00', 'hora_salida' => '15:00']],
    ['op_rh_personal_asistencia', ['id_personal' => $regular['worker_id']]],
] as [$table, $row]) {
    Capsule::table($table)->insert($row);
}

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
$beforeRead = $snapshot();
$resolver->resolve($regular['worker_id'], $regular['workplace_id'], '2026-10-05');
$assert($snapshot() === $beforeRead, 'resolver performs zero inserts, updates, or deletes across versioned domains');

printf("P3_L3_4_SQLITE_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passed, $failed);
exit($failed === 0 ? 0 : 1);
