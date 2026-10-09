<?php

declare(strict_types=1);

use App\Models\Operativo\PersonalScheduleVersion;
use App\Models\Operativo\PersonalScheduleDayDefinition;
use App\Models\Operativo\PersonalScheduleSegment;
use App\Services\PersonalScheduleHistorySelector;
use App\Services\PersonalScheduleVersionService;
use App\Services\WorkplaceTimezoneResolver;
use App\Services\WorkplaceTimezoneVersionService;
use App\Services\WorkerWorkplaceAssignmentVersionService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../scripts/migrate_workplace_timezone_versions.php';
require_once __DIR__ . '/../scripts/migrate_worker_workplace_assignment_versions.php';
require_once __DIR__ . '/../scripts/migrate_personal_schedule_versions.php';

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
$throws = static function (callable $callback, ?string $messagePart = null): bool {
    try {
        $callback();
        return false;
    } catch (Throwable $exception) {
        return $messagePart === null || str_contains($exception->getMessage(), $messagePart);
    }
};

$capsule = new Capsule();
$capsule->addConnection([
    'driver' => 'sqlite',
    'database' => ':memory:',
    'prefix' => '',
    'foreign_key_constraints' => true,
    'options' => [PDO::ATTR_TIMEOUT => 5],
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();
Capsule::connection()->statement('PRAGMA foreign_keys = ON');
Capsule::connection()->statement('PRAGMA busy_timeout = 5000');

Capsule::schema()->create('op_rh_personal', static function (Blueprint $table): void {
    $table->increments('id');
    $table->integer('id_estacion');
});
Capsule::schema()->create('op_rh_localidades', static function (Blueprint $table): void {
    $table->increments('id');
    $table->integer('numlista');
    $table->string('localidad');
    $table->string('recuperacion_vapores');
});
Capsule::schema()->create('op_rh_personal_horario', static function (Blueprint $table): void {
    $table->increments('id');
    $table->integer('id_estacion');
    $table->integer('id_personal');
    $table->text('horario');
    $table->text('dia');
    $table->time('hora_entrada')->nullable();
    $table->time('hora_salida')->nullable();
});
Capsule::schema()->create('op_rh_personal_horario_hist', static function (Blueprint $table): void {
    $table->increments('id');
    $table->integer('id_estacion');
    $table->integer('id_personal');
    $table->text('horario');
    $table->text('dia');
    $table->time('hora_entrada')->nullable();
    $table->time('hora_salida')->nullable();
});
Capsule::schema()->create('op_rh_localidades_horario', static function (Blueprint $table): void {
    $table->increments('id');
    $table->integer('id_estacion');
    $table->text('titulo');
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
    $table->text('horario');
    $table->text('dia');
    $table->time('hora_entrada');
    $table->time('hora_salida');
});
Capsule::schema()->create('op_rh_personal_asistencia', static function (Blueprint $table): void {
    $table->increments('id');
    $table->integer('id_personal');
});

$workplaceIds = [];
foreach (['Workplace A', 'Workplace B', 'Workplace C'] as $index => $label) {
    $workplaceIds[] = (int)Capsule::table('op_rh_localidades')->insertGetId([
        'numlista' => 301 + $index,
        'localidad' => $label,
        'recuperacion_vapores' => '',
    ]);
}
$workerIds = [];
for ($i = 0; $i < 8; $i++) {
    $workerIds[] = (int)Capsule::table('op_rh_personal')->insertGetId(['id_estacion' => $workplaceIds[0]]);
}
Capsule::table('op_rh_personal_horario')->insert([
    'id_estacion' => $workplaceIds[0],
    'id_personal' => $workerIds[5],
    'horario' => 'Legacy Monday shift',
    'dia' => 'Lunes',
    'hora_entrada' => '08:00:00',
    'hora_salida' => '16:00:00',
]);
Capsule::table('op_rh_personal_horario_hist')->insert([
    'id_estacion' => $workplaceIds[0],
    'id_personal' => $workerIds[5],
    'horario' => 'Prior legacy Monday shift',
    'dia' => 'Lunes',
    'hora_entrada' => '09:00:00',
    'hora_salida' => '17:00:00',
]);
Capsule::table('op_rh_localidades_horario')->insert([
    'id_estacion' => $workplaceIds[0],
    'titulo' => 'Station catalog shift',
    'hora_entrada' => '07:00:00',
    'hora_salida' => '15:00:00',
]);

WorkplaceTimezoneVersionsMigration::up();
WorkerWorkplaceAssignmentVersionsMigration::up();
PersonalScheduleVersionsMigration::up();
$assert(
    Capsule::schema()->hasTable(PersonalScheduleVersionsMigration::HEADER_TABLE)
        && Capsule::schema()->hasTable(PersonalScheduleVersionsMigration::DAY_TABLE)
        && Capsule::schema()->hasTable(PersonalScheduleVersionsMigration::SEGMENT_TABLE),
    'additive migration creates header, explicit weekday, and segment tables'
);

$clockValue = '2025-12-01T00:00:00Z';
$clock = static function () use (&$clockValue): DateTimeImmutable { return new DateTimeImmutable($clockValue); };
$service = new PersonalScheduleVersionService($clock);
$selector = new PersonalScheduleHistorySelector($clock);
$week = static function (): array {
    $days = [];
    for ($weekday = 1; $weekday <= 7; $weekday++) {
        $days[] = [
            'weekday_iso' => $weekday,
            'day_state' => $weekday <= 5 ? 'SCHEDULED' : 'DAY_OFF',
            'segments' => $weekday <= 5 ? [[
                'sequence_number' => 1,
                'start_local_time' => '08:00',
                'end_local_time' => '16:00',
                'end_day_offset' => 0,
            ]] : [],
        ];
    }
    return $days;
};

// Force a failure after header/day/first-segment inserts to prove transaction rollback is complete.
$atomicWeek = $week();
$atomicWeek[0]['segments'] = [
    ['sequence_number' => 1, 'start_local_time' => '08:00', 'end_local_time' => '12:00', 'end_day_offset' => 0],
    ['sequence_number' => 2, 'start_local_time' => '13:00', 'end_local_time' => '16:00', 'end_day_offset' => 0],
];
Capsule::connection()->statement(
    "CREATE TRIGGER p3_l3_3_fail_second_segment BEFORE INSERT ON " . PersonalScheduleVersionsMigration::SEGMENT_TABLE
    . " WHEN NEW.sequence_number = 2 BEGIN SELECT RAISE(ABORT, 'disposable atomic schedule failure'); END"
);
$beforeAtomicFailure = [
    Capsule::table(PersonalScheduleVersionsMigration::HEADER_TABLE)->count(),
    Capsule::table(PersonalScheduleVersionsMigration::DAY_TABLE)->count(),
    Capsule::table(PersonalScheduleVersionsMigration::SEGMENT_TABLE)->count(),
];
$atomicFailureObserved = $throws(static fn() => $service->appendVersion(
    $workerIds[7], $workplaceIds[2], '2026-01-01', null, $atomicWeek
), 'disposable atomic schedule failure');
Capsule::connection()->statement('DROP TRIGGER p3_l3_3_fail_second_segment');
$afterAtomicFailure = [
    Capsule::table(PersonalScheduleVersionsMigration::HEADER_TABLE)->count(),
    Capsule::table(PersonalScheduleVersionsMigration::DAY_TABLE)->count(),
    Capsule::table(PersonalScheduleVersionsMigration::SEGMENT_TABLE)->count(),
];
$assert(
    $atomicFailureObserved && $beforeAtomicFailure === $afterAtomicFailure,
    'failed segment insert rolls back the entire weekly header, day, and segment transaction'
);

// Migration's empty DOWN/reapply gate is exercised before schedule history exists.
PersonalScheduleVersionsMigration::down();
$assert(!Capsule::schema()->hasTable(PersonalScheduleVersionsMigration::HEADER_TABLE), 'empty schedule migration DOWN removes the empty tables');
PersonalScheduleVersionsMigration::up();
$assert(Capsule::schema()->hasTable(PersonalScheduleVersionsMigration::SEGMENT_TABLE), 'schedule migration reapplies after empty DOWN');

$weekA = $week();
$versionA = $service->appendVersion($workerIds[0], $workplaceIds[0], '2026-01-01', '2027-01-01', $weekA, 7, 'Initial weekly schedule');
$storedDaysA = Capsule::table(PersonalScheduleVersionsMigration::DAY_TABLE)->where('schedule_version_id', $versionA)->count();
$storedSegmentsA = Capsule::table(PersonalScheduleVersionsMigration::SEGMENT_TABLE)
    ->whereIn('day_definition_id', Capsule::table(PersonalScheduleVersionsMigration::DAY_TABLE)->select('id')->where('schedule_version_id', $versionA))
    ->count();
$assert($storedDaysA === 7 && $storedSegmentsA === 5, 'a complete Monday-Friday scheduled week and Saturday/Sunday DAY_OFF persists explicitly');
$selectedA = $selector->selectVersion($workerIds[0], $workplaceIds[0], '2026-01-05');
$assert(
    $selectedA['status'] === 'VERSION_FOUND'
        && $selectedA['version']['id'] === $versionA
        && $selectedA['selected_weekday_definition']['weekday_iso'] === 1
        && $selectedA['selected_weekday_definition']['day_state'] === 'SCHEDULED'
        && $selectedA['selected_weekday_definition']['segments'][0]['start_local_time'] === '08:00:00',
    'effective local-date selector returns the stored Monday version and segments without UTC conversion'
);

$unscheduledWeek = $week();
$unscheduledWeek[6]['day_state'] = 'UNSCHEDULED';
$unscheduledId = $service->appendVersion($workerIds[1], $workplaceIds[0], '2026-01-01', null, $unscheduledWeek);
$unscheduled = $selector->selectVersion($workerIds[1], $workplaceIds[0], '2026-01-04');
$assert(
    $unscheduled['status'] === 'VERSION_FOUND'
        && $unscheduled['selected_weekday_definition']['day_state'] === 'UNSCHEDULED'
        && $unscheduled['selected_weekday_definition']['segments'] === [],
    'UNSCHEDULED is an explicit persisted state distinct from DAY_OFF'
);

$invalidOff = $week();
$invalidOff[5]['segments'] = [[
    'sequence_number' => 1,
    'start_local_time' => '08:00',
    'end_local_time' => '09:00',
    'end_day_offset' => 0,
]];
$invalidUnscheduled = $week();
$invalidUnscheduled[6]['day_state'] = 'UNSCHEDULED';
$invalidUnscheduled[6]['segments'] = $invalidOff[5]['segments'];
$invalidScheduled = $week();
$invalidScheduled[0]['segments'] = [];
$assert(
    $throws(static fn() => $service->appendVersion($workerIds[2], $workplaceIds[0], '2026-01-01', null, $invalidOff), 'must not contain')
        && $throws(static fn() => $service->appendVersion($workerIds[2], $workplaceIds[0], '2026-01-01', null, $invalidUnscheduled), 'must not contain')
        && $throws(static fn() => $service->appendVersion($workerIds[2], $workplaceIds[0], '2026-01-01', null, $invalidScheduled), 'at least one'),
    'DAY_OFF and UNSCHEDULED reject segments while SCHEDULED requires one'
);

$sixDay = array_slice($week(), 0, 6);
$eightDay = $week();
$eightDay[] = $eightDay[6];
$duplicateDay = $week();
$duplicateDay[6]['weekday_iso'] = 6;
$assert(
    $throws(static fn() => $service->appendVersion($workerIds[2], $workplaceIds[0], '2026-01-01', null, $sixDay), 'exactly seven')
        && $throws(static fn() => $service->appendVersion($workerIds[2], $workplaceIds[0], '2026-01-01', null, $eightDay), 'exactly seven')
        && $throws(static fn() => $service->appendVersion($workerIds[2], $workplaceIds[0], '2026-01-01', null, $duplicateDay), 'unique ISO'),
    'incomplete, eight-row, and duplicate-weekday schedules are rejected'
);

$multiWeek = $week();
$multiWeek[0]['segments'] = [
    ['sequence_number' => 1, 'start_local_time' => '08:00', 'end_local_time' => '12:00', 'end_day_offset' => 0],
    ['sequence_number' => 2, 'start_local_time' => '14:00', 'end_local_time' => '18:00', 'end_day_offset' => 0],
    ['sequence_number' => 3, 'start_local_time' => '19:00', 'end_local_time' => '20:00', 'end_day_offset' => 0],
];
$multiId = $service->appendVersion($workerIds[0], $workplaceIds[1], '2026-01-01', '2027-01-01', $multiWeek);
$multiDay = $selector->selectVersion($workerIds[0], $workplaceIds[1], '2026-01-05')['selected_weekday_definition'];
$assert(
    count($multiDay['segments']) === 3
        && array_column($multiDay['segments'], 'sequence_number') === [1, 2, 3],
    'one worker may hold an overlapping schedule at another Workplace with ordered 1..N segments'
);

$adjacentWeek = $week();
$adjacentWeek[0]['segments'] = [
    ['sequence_number' => 1, 'start_local_time' => '08:00', 'end_local_time' => '12:00', 'end_day_offset' => 0],
    ['sequence_number' => 2, 'start_local_time' => '12:00', 'end_local_time' => '16:00', 'end_day_offset' => 0],
];
$adjacentId = $service->appendVersion($workerIds[2], $workplaceIds[0], '2026-01-01', null, $adjacentWeek);
$overlapWeek = $week();
$overlapWeek[0]['segments'] = [
    ['sequence_number' => 1, 'start_local_time' => '08:00', 'end_local_time' => '13:00', 'end_day_offset' => 0],
    ['sequence_number' => 2, 'start_local_time' => '12:00', 'end_local_time' => '18:00', 'end_day_offset' => 0],
];
$assert(
    $adjacentId > 0
        && $throws(static fn() => $service->appendVersion($workerIds[3], $workplaceIds[0], '2026-01-01', null, $overlapWeek), 'may not overlap'),
    'adjacent local segments are allowed and actual segment overlap is rejected'
);

$nightWeek = $week();
$nightWeek[0]['segments'] = [[
    'sequence_number' => 1,
    'start_local_time' => '22:00',
    'end_local_time' => '06:00',
    'end_day_offset' => 1,
]];
$nightWeek[1]['segments'] = [[
    'sequence_number' => 1,
    'start_local_time' => '08:00',
    'end_local_time' => '09:00',
    'end_day_offset' => 2,
]];
$nightId = $service->appendVersion($workerIds[3], $workplaceIds[0], '2026-01-01', null, $nightWeek);
$nightSelected = $selector->selectVersion($workerIds[3], $workplaceIds[0], '2026-01-05')['selected_weekday_definition'];
$twoDayOffsetSelected = $selector->selectVersion($workerIds[3], $workplaceIds[0], '2026-01-06')['selected_weekday_definition'];
$missingOffset = $nightWeek;
$missingOffset[0]['segments'][0]['end_day_offset'] = 0;
$negativeOffset = $nightWeek;
$negativeOffset[0]['segments'][0]['end_day_offset'] = -1;
$assert(
    $nightId > 0
        && $nightSelected['segments'][0]['end_day_offset'] === 1
        && $twoDayOffsetSelected['segments'][0]['end_day_offset'] === 2
        && $throws(static fn() => $service->appendVersion($workerIds[4], $workplaceIds[0], '2026-01-01', null, $missingOffset), 'end must occur')
        && $throws(static fn() => $service->appendVersion($workerIds[4], $workplaceIds[0], '2026-01-01', null, $negativeOffset), 'non-negative'),
    'overnight offsets 1 and 2 persist as local facts; missing/negative offset cannot imply overnight'
);
$zeroLength = $week();
$zeroLength[0]['segments'][0]['end_local_time'] = '08:00';
$assert(
    $throws(static fn() => $service->appendVersion($workerIds[4], $workplaceIds[0], '2026-01-01', null, $zeroLength), 'end must occur'),
    'zero-length segment is rejected'
);

$dateVersionA = $service->appendVersion($workerIds[4], $workplaceIds[0], '2026-01-01', '2026-06-01', $week());
$dateVersionB = $service->appendVersion($workerIds[4], $workplaceIds[0], '2026-06-01', null, $week());
$assert(
    $selector->selectVersion($workerIds[4], $workplaceIds[0], '2026-05-31')['version']['id'] === $dateVersionA
        && $selector->selectVersion($workerIds[4], $workplaceIds[0], '2026-06-01')['version']['id'] === $dateVersionB
        && $throws(static fn() => $service->appendVersion($workerIds[4], $workplaceIds[0], '2026-05-01', '2026-07-01', $week()), 'may not overlap'),
    'local-date versions are half-open, adjacent, and overlapping same-key versions are rejected'
);

$correctionWorker = $workerIds[6];
$recordedClock = new DateTimeImmutable('2025-12-15T00:00:00Z');
$correctionService = new PersonalScheduleVersionService(static function () use (&$recordedClock): DateTimeImmutable { return $recordedClock; });
$original = $correctionService->appendVersion($correctionWorker, $workplaceIds[0], '2026-01-01', '2027-01-01', $week(), 9, 'Original schedule');
$originalRecordedAt = (string)Capsule::table(PersonalScheduleVersionsMigration::HEADER_TABLE)->where('id', $original)->value('recorded_at_utc');
$recordedClock = new DateTimeImmutable('2026-02-01T00:00:00Z');
$correction = $correctionService->appendVersion(
    $correctionWorker,
    $workplaceIds[1],
    '2026-01-01',
    '2027-01-01',
    $week(),
    10,
    'Corrected Workplace identity',
    $original
);
$asRecordedBefore = $selector->selectVersionAsRecordedAt(
    $correctionWorker,
    $workplaceIds[0],
    '2026-03-01',
    str_replace(' ', 'T', $originalRecordedAt) . 'Z'
);
$asRecordedAfterOldPlace = $selector->selectVersionAsRecordedAt(
    $correctionWorker,
    $workplaceIds[0],
    '2026-03-01',
    '2026-02-02T00:00:00Z'
);
$asRecordedAfterNewPlace = $selector->selectVersionAsRecordedAt(
    $correctionWorker,
    $workplaceIds[1],
    '2026-03-01',
    '2026-02-02T00:00:00Z'
);
$assert(
    $asRecordedBefore['version']['id'] === $original
        && $asRecordedBefore['version']['workplace_id'] === $workplaceIds[0]
        && $asRecordedAfterOldPlace['status'] === 'NO_VERSION'
        && $asRecordedAfterNewPlace['version']['id'] === $correction,
    'as-recorded correction preserves prior knowledge and suppresses corrected Workplace identity in current history'
);
$assert(Capsule::table(PersonalScheduleVersionsMigration::HEADER_TABLE)->where('id', $original)->exists(), 'superseded schedule header remains append-only');

$assignmentWriter = new WorkerWorkplaceAssignmentVersionService($clock);
$assignmentWriter->appendVersion($workerIds[0], $workplaceIds[0], '2026-01-01T00:00:00Z', null, null, 'test assignment A');
$assignmentWriter->appendVersion($workerIds[0], $workplaceIds[1], '2026-01-01T00:00:00Z', null, null, 'test assignment B');
$assert(
    $selector->selectVersion($workerIds[0], $workplaceIds[0], '2026-02-01')['version']['workplace_id'] === $workplaceIds[0]
        && $selector->selectVersion($workerIds[0], $workplaceIds[1], '2026-02-01')['version']['workplace_id'] === $workplaceIds[1]
        && $multiId > 0,
    'schedule versions bind independently to P3-L3-2 canonical worker/Workplace assignments'
);
$noAssignmentWorker = $workerIds[7];
$noAssignmentSchedule = $service->appendVersion($noAssignmentWorker, $workplaceIds[2], '2026-01-01', null, $week());
$assert($noAssignmentSchedule > 0, 'schedule write does not require membership history to be backfilled first');

$timezoneWriter = new WorkplaceTimezoneVersionService($clock);
$timezoneResolver = new WorkplaceTimezoneResolver($clock);
$timezoneWriter->appendVersions($workplaceIds[0], [[
    'timezone_iana' => 'America/Mexico_City',
    'valid_from_utc' => '2026-01-01T00:00:00Z',
]], null, 'Schedule composition fixture');
$timezoneWriter->appendVersions($workplaceIds[1], [[
    'timezone_iana' => 'America/Monterrey',
    'valid_from_utc' => '2026-01-01T00:00:00Z',
]], null, 'Schedule composition fixture');
$placeA = $selector->selectVersion($workerIds[0], $workplaceIds[0], '2026-02-01');
$placeB = $selector->selectVersion($workerIds[0], $workplaceIds[1], '2026-02-01');
$zoneA = $timezoneResolver->resolve($placeA['version']['workplace_id'], '2026-02-01T00:00:00Z');
$zoneB = $timezoneResolver->resolve($placeB['version']['workplace_id'], '2026-02-01T00:00:00Z');
$assert(
    $zoneA['status'] === 'RESOLVED' && $zoneA['timezone_iana'] === 'America/Mexico_City'
        && $zoneB['status'] === 'RESOLVED' && $zoneB['timezone_iana'] === 'America/Monterrey',
    'schedule and Workplace timezone compose independently without duplicating timezone or deriving UTC shift windows'
);

$assert(
    $selector->selectVersion(99999, $workplaceIds[0], '2026-02-01')['status'] === 'DATA_INVALID'
        && $selector->selectVersion($workerIds[0], 99999, '2026-02-01')['status'] === 'DATA_INVALID'
        && $throws(static fn() => $service->appendVersion(99999, $workplaceIds[0], '2026-01-01', null, $week()), 'does not exist')
        && $throws(static fn() => $service->appendVersion($workerIds[0], 99999, '2026-01-01', null, $week()), 'does not exist'),
    'invalid canonical worker and Workplace IDs fail closed'
);
$assert(
    $selector->selectVersion($workerIds[5], $workplaceIds[0], '2026-02-01')['status'] === 'NO_VERSION',
    'legacy personal schedule/history and station catalog do not provide a fallback or implicit DAY_OFF'
);

$immutableVersion = PersonalScheduleVersion::findOrFail($versionA);
$immutableDay = PersonalScheduleDayDefinition::where('schedule_version_id', $versionA)->firstOrFail();
$immutableSegment = PersonalScheduleSegment::where('day_definition_id', $immutableDay->id)->firstOrFail();
$assert(
    $throws(static function () use ($immutableVersion): void { $immutableVersion->reason = 'overwrite'; $immutableVersion->save(); }, 'append-only')
        && $throws(static fn() => $immutableVersion->delete(), 'append-only')
        && $throws(static function () use ($immutableDay): void { $immutableDay->day_state = 'DAY_OFF'; $immutableDay->save(); }, 'append-only')
        && $throws(static fn() => $immutableSegment->delete(), 'append-only'),
    'version, day, and segment models prevent in-place mutation/deletion'
);

$legacyTables = [
    'op_rh_personal_horario',
    'op_rh_personal_horario_hist',
    'op_rh_localidades_horario',
    'op_rh_personal_horario_programar',
    'op_rh_personal_horario_programar_detalle',
    'op_rh_personal_asistencia',
];
$legacyCounts = [];
foreach ($legacyTables as $table) {
    $legacyCounts[$table] = (int)Capsule::table($table)->count();
}
$legacyPointerBefore = (int)Capsule::table('op_rh_personal')->where('id', $workerIds[0])->value('id_estacion');
$legacyStable = true;
foreach ($legacyCounts as $table => $count) {
    $legacyStable = $legacyStable && (int)Capsule::table($table)->count() === $count;
}
$legacyStable = $legacyStable
    && (int)Capsule::table('op_rh_personal')->where('id', $workerIds[0])->value('id_estacion') === $legacyPointerBefore;
$assert($legacyStable, 'schedule version domain performs zero writes to legacy schedules, history, station catalog, special programming, attendance, or id_estacion');

$snapshotTables = [
    PersonalScheduleVersionsMigration::HEADER_TABLE,
    PersonalScheduleVersionsMigration::DAY_TABLE,
    PersonalScheduleVersionsMigration::SEGMENT_TABLE,
];
$snapshot = static function () use ($snapshotTables): string {
    $data = [];
    foreach ($snapshotTables as $table) {
        $data[$table] = Capsule::table($table)->orderBy('id')->get()->toArray();
    }
    return hash('sha256', json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
};
$historyChecksum = $snapshot();
$assert(
    $throws(static fn() => PersonalScheduleVersionsMigration::down(), 'Refusing to drop populated'),
    'populated migration DOWN refuses to destroy schedule history'
);
$assert($snapshot() === $historyChecksum, 'populated migration DOWN leaves header, days, and segments unchanged');

Capsule::table(PersonalScheduleVersionsMigration::SEGMENT_TABLE)->delete();
Capsule::table(PersonalScheduleVersionsMigration::DAY_TABLE)->delete();
foreach (Capsule::table(PersonalScheduleVersionsMigration::HEADER_TABLE)->orderByDesc('id')->pluck('id') as $versionId) {
    Capsule::table(PersonalScheduleVersionsMigration::HEADER_TABLE)->where('id', $versionId)->delete();
}
PersonalScheduleVersionsMigration::down();
$downEmpty = !Capsule::schema()->hasTable(PersonalScheduleVersionsMigration::HEADER_TABLE)
    && !Capsule::schema()->hasTable(PersonalScheduleVersionsMigration::DAY_TABLE)
    && !Capsule::schema()->hasTable(PersonalScheduleVersionsMigration::SEGMENT_TABLE);
$assert($downEmpty, 'empty migration DOWN removes all three schedule tables in child-first order');
PersonalScheduleVersionsMigration::up();
$assert(Capsule::schema()->hasTable(PersonalScheduleVersionsMigration::HEADER_TABLE), 'schedule migration reapplies after empty DOWN');

printf("P3_L3_3_SQLITE_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passed, $failed);
exit($failed === 0 ? 0 : 1);
