<?php

declare(strict_types=1);

use App\Core\Database;
use App\Models\Operativo\PersonalScheduleVersion;
use App\Services\PersonalScheduleHistorySelector;
use App\Services\PersonalScheduleVersionService;
use App\Services\WorkplaceTimezoneResolver;
use App\Services\WorkplaceTimezoneVersionService;
use App\Services\WorkerWorkplaceAssignmentVersionService;
use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../scripts/migrate_workplace_timezone_versions.php';
require_once __DIR__ . '/../scripts/migrate_worker_workplace_assignment_versions.php';
require_once __DIR__ . '/../scripts/migrate_personal_schedule_versions.php';

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
Database::initialize();
$connection = Capsule::connection();
$databaseName = (string)$connection->selectOne('SELECT DATABASE() AS db')->db;
$serverVersion = (string)$connection->selectOne('SELECT VERSION() AS version')->version;
$host = strtolower(trim((string)$connection->getConfig('host')));
if ($databaseName !== 'bd_portal3_p3_l3_3'
    || !in_array($host, ['localhost', '127.0.0.1', '::1'], true)
    || !str_starts_with($serverVersion, '11.8.8-MariaDB')) {
    fwrite(STDERR, "REFUSED: expected local MariaDB 11.8.8 on bd_portal3_p3_l3_3; no database changes made.\n");
    exit(2);
}

/** Synthetic explicit ISO week used by the independent concurrency workers. */
function p3L33ConcurrentWeek(): array
{
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
}

if (($argv[1] ?? '') === '--worker') {
    $readyFile = (string)($argv[2] ?? '');
    $workerId = (int)($argv[3] ?? 0);
    $workplaceId = (int)($argv[4] ?? 0);
    $fromDate = (string)($argv[5] ?? '');
    $toDate = (string)($argv[6] ?? '');
    if ($readyFile === '' || $workerId <= 0 || $workplaceId <= 0) {
        fwrite(STDERR, "INVALID_WORKER_ARGS\n");
        exit(2);
    }
    file_put_contents($readyFile, (string)getmypid(), LOCK_EX);
    try {
        $id = (new PersonalScheduleVersionService())->appendVersion(
            $workerId,
            $workplaceId,
            $fromDate,
            $toDate,
            p3L33ConcurrentWeek(),
            null,
            'P3-L3-3 disposable concurrent writer'
        );
        echo json_encode(['outcome' => 'COMMITTED', 'id' => $id], JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(0);
    } catch (DomainException $exception) {
        echo json_encode(['outcome' => 'CONTROLLED_REJECTION', 'message' => $exception->getMessage()], JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(10);
    } catch (Throwable $exception) {
        fwrite(STDERR, get_class($exception) . ': ' . $exception->getMessage() . PHP_EOL);
        exit(1);
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
$throws = static function (callable $callback, ?string $messagePart = null): bool {
    try {
        $callback();
        return false;
    } catch (Throwable $exception) {
        return $messagePart === null || str_contains($exception->getMessage(), $messagePart);
    }
};
$tableExists = static fn(string $table): bool => Capsule::schema()->hasTable($table);
$requiredTables = [
    'op_rh_personal',
    'op_rh_localidades',
    'op_rh_personal_horario',
    'op_rh_personal_horario_hist',
    'op_rh_localidades_horario',
    'op_rh_personal_horario_programar',
    'op_rh_personal_horario_programar_detalle',
    'op_rh_personal_asistencia',
    WorkplaceTimezoneVersionsMigration::TABLE,
    WorkerWorkplaceAssignmentVersionsMigration::TABLE,
    PersonalScheduleVersionsMigration::HEADER_TABLE,
    PersonalScheduleVersionsMigration::DAY_TABLE,
    PersonalScheduleVersionsMigration::SEGMENT_TABLE,
];
foreach ($requiredTables as $table) {
    if (!$tableExists($table)) {
        fwrite(STDERR, "REFUSED: required schema-only or migrated table is missing: {$table}\n");
        exit(2);
    }
}
foreach ([
    'op_rh_personal',
    'op_rh_localidades',
    WorkplaceTimezoneVersionsMigration::TABLE,
    WorkerWorkplaceAssignmentVersionsMigration::TABLE,
    PersonalScheduleVersionsMigration::HEADER_TABLE,
    PersonalScheduleVersionsMigration::DAY_TABLE,
    PersonalScheduleVersionsMigration::SEGMENT_TABLE,
] as $table) {
    if ((int)Capsule::table($table)->count() !== 0) {
        fwrite(STDERR, "REFUSED: disposable target must have empty canonical parents and version tables: {$table}\n");
        exit(2);
    }
}

$domainTables = [
    PersonalScheduleVersionsMigration::HEADER_TABLE,
    PersonalScheduleVersionsMigration::DAY_TABLE,
    PersonalScheduleVersionsMigration::SEGMENT_TABLE,
];
$engineRows = $connection->select(
    'SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (?,?,?)',
    $domainTables
);
$innoDb = count($engineRows) === 3;
foreach ($engineRows as $row) {
    $innoDb = $innoDb && strtoupper((string)$row->ENGINE) === 'INNODB';
}
$assert($innoDb, 'all schedule header/day/segment tables use InnoDB');

$fkRows = $connection->select(
    'SELECT k.TABLE_NAME,k.REFERENCED_TABLE_NAME,r.DELETE_RULE '
    . 'FROM information_schema.KEY_COLUMN_USAGE k '
    . 'JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA '
    . 'AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME '
    . 'WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME IN (?,?,?) AND k.REFERENCED_TABLE_NAME IS NOT NULL',
    $domainTables
);
$fkPairs = [];
$allRestrict = count($fkRows) >= 6;
foreach ($fkRows as $fk) {
    $fkPairs[] = (string)$fk->TABLE_NAME . '>' . (string)$fk->REFERENCED_TABLE_NAME;
    $allRestrict = $allRestrict && strtoupper((string)$fk->DELETE_RULE) === 'RESTRICT';
}
$assert(
    in_array(PersonalScheduleVersionsMigration::HEADER_TABLE . '>op_rh_personal', $fkPairs, true)
        && in_array(PersonalScheduleVersionsMigration::HEADER_TABLE . '>op_rh_localidades', $fkPairs, true)
        && in_array(PersonalScheduleVersionsMigration::HEADER_TABLE . '>' . PersonalScheduleVersionsMigration::HEADER_TABLE, $fkPairs, true)
        && in_array(PersonalScheduleVersionsMigration::DAY_TABLE . '>' . PersonalScheduleVersionsMigration::HEADER_TABLE, $fkPairs, true)
        && in_array(PersonalScheduleVersionsMigration::SEGMENT_TABLE . '>' . PersonalScheduleVersionsMigration::DAY_TABLE, $fkPairs, true)
        && $allRestrict,
    'canonical and child/lineage foreign keys exist with RESTRICT delete semantics'
);
$indexRows = $connection->select(
    'SELECT TABLE_NAME,INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (?,?,?)',
    $domainTables
);
$indexNames = [];
foreach ($indexRows as $indexRow) {
    $indexNames[(string)$indexRow->TABLE_NAME][] = (string)$indexRow->INDEX_NAME;
}
$assert(
    in_array('op_rh_phv_worker_workplace_effective_idx', $indexNames[PersonalScheduleVersionsMigration::HEADER_TABLE] ?? [], true)
        && in_array('op_rh_phv_worker_supersedes_uq', $indexNames[PersonalScheduleVersionsMigration::HEADER_TABLE] ?? [], true)
        && in_array('op_rh_phv_day_version_weekday_uq', $indexNames[PersonalScheduleVersionsMigration::DAY_TABLE] ?? [], true)
        && in_array('op_rh_phv_segment_day_sequence_uq', $indexNames[PersonalScheduleVersionsMigration::SEGMENT_TABLE] ?? [], true),
    'effective overlap, lineage, weekday uniqueness, and segment ordering indexes exist'
);
$offsetType = (string)$connection->selectOne(
    'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',
    [PersonalScheduleVersionsMigration::SEGMENT_TABLE, 'end_day_offset']
)->COLUMN_TYPE;
$assert(str_contains(strtolower($offsetType), 'unsigned'), 'end_day_offset physical type is non-negative unsigned INT with no business cap');

$legacyTables = [
    'op_rh_personal_horario',
    'op_rh_personal_horario_hist',
    'op_rh_localidades_horario',
    'op_rh_personal_horario_programar',
    'op_rh_personal_horario_programar_detalle',
    'op_rh_personal_asistencia',
];
$baseNumlista = 990100 + random_int(0, 800);
$workplaceIds = [];
for ($i = 0; $i < 3; $i++) {
    $workplaceIds[] = (int)Capsule::table('op_rh_localidades')->insertGetId([
        'numlista' => $baseNumlista + $i,
        'localidad' => 'P3-L3-3 disposable Workplace ' . $i,
        'recuperacion_vapores' => '',
    ]);
}
$workerIds = [];
for ($i = 0; $i < 8; $i++) {
    $workerIds[] = (int)Capsule::table('op_rh_personal')->insertGetId([
        'id_estacion' => $workplaceIds[0],
        'fecha_ingreso' => '2020-01-01',
        'no_colaborador' => $baseNumlista + 100 + $i,
        'nombre_completo' => 'P3-L3-3 disposable worker ' . $i,
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
    'horario' => 'Legacy historical table row',
    'dia' => 'Lunes',
    'hora_entrada' => '09:00:00',
    'hora_salida' => '17:00:00',
]);
Capsule::table('op_rh_localidades_horario')->insert([
    'id_estacion' => $workplaceIds[0],
    'titulo' => 'Station catalog only',
    'hora_entrada' => '07:00:00',
    'hora_salida' => '15:00:00',
]);
$legacyBefore = [];
foreach ($legacyTables as $table) {
    $legacyBefore[$table] = (int)Capsule::table($table)->count();
}
$legacyPointerBefore = (int)Capsule::table('op_rh_personal')->where('id', $workerIds[2])->value('id_estacion');

$clockValue = '2025-12-01T00:00:00Z';
$clock = static function () use (&$clockValue): DateTimeImmutable { return new DateTimeImmutable($clockValue); };
$service = new PersonalScheduleVersionService($clock);
$selector = new PersonalScheduleHistorySelector($clock);
$week = static fn(): array => p3L33ConcurrentWeek();

$atomicWeek = $week();
$atomicWeek[0]['segments'] = [
    ['sequence_number' => 1, 'start_local_time' => '08:00', 'end_local_time' => '12:00', 'end_day_offset' => 0],
    ['sequence_number' => 2, 'start_local_time' => '13:00', 'end_local_time' => '16:00', 'end_day_offset' => 0],
];
$connection->unprepared(
    'CREATE TRIGGER p3_l3_3_atomic_fail BEFORE INSERT ON ' . PersonalScheduleVersionsMigration::SEGMENT_TABLE
    . " FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='disposable atomic schedule failure'"
);
$beforeAtomic = [
    Capsule::table(PersonalScheduleVersionsMigration::HEADER_TABLE)->count(),
    Capsule::table(PersonalScheduleVersionsMigration::DAY_TABLE)->count(),
    Capsule::table(PersonalScheduleVersionsMigration::SEGMENT_TABLE)->count(),
];
$atomicFailed = $throws(static fn() => $service->appendVersion(
    $workerIds[7], $workplaceIds[2], '2026-01-01', null, $atomicWeek
), 'disposable atomic schedule failure');
$connection->unprepared('DROP TRIGGER p3_l3_3_atomic_fail');
$afterAtomic = [
    Capsule::table(PersonalScheduleVersionsMigration::HEADER_TABLE)->count(),
    Capsule::table(PersonalScheduleVersionsMigration::DAY_TABLE)->count(),
    Capsule::table(PersonalScheduleVersionsMigration::SEGMENT_TABLE)->count(),
];
$assert($atomicFailed && $beforeAtomic === $afterAtomic, 'MariaDB trigger failure rolls back header, weekday, and prior segment inserts atomically');

$fullWeekId = $service->appendVersion($workerIds[2], $workplaceIds[0], '2026-01-01', '2027-01-01', $week(), 7, 'Single-segment workweek');
$daysCount = (int)Capsule::table(PersonalScheduleVersionsMigration::DAY_TABLE)->where('schedule_version_id', $fullWeekId)->count();
$segmentsCount = (int)Capsule::table(PersonalScheduleVersionsMigration::SEGMENT_TABLE)
    ->whereIn('day_definition_id', Capsule::table(PersonalScheduleVersionsMigration::DAY_TABLE)->select('id')->where('schedule_version_id', $fullWeekId))
    ->count();
$monday = $selector->selectVersion($workerIds[2], $workplaceIds[0], '2026-01-05');
$assert(
    $daysCount === 7 && $segmentsCount === 5
        && $monday['status'] === 'VERSION_FOUND'
        && $monday['selected_weekday_definition']['day_state'] === 'SCHEDULED'
        && $monday['selected_weekday_definition']['segments'][0]['start_local_time'] === '08:00:00',
    'MariaDB stores an explicit seven-day weekday definition and returns the effective local-date version'
);

$multiWeek = $week();
$multiWeek[0]['segments'] = [
    ['sequence_number' => 1, 'start_local_time' => '08:00', 'end_local_time' => '12:00', 'end_day_offset' => 0],
    ['sequence_number' => 2, 'start_local_time' => '14:00', 'end_local_time' => '18:00', 'end_day_offset' => 0],
    ['sequence_number' => 3, 'start_local_time' => '19:00', 'end_local_time' => '20:00', 'end_day_offset' => 0],
];
$multiId = $service->appendVersion($workerIds[2], $workplaceIds[1], '2026-01-01', '2027-01-01', $multiWeek);
$multiMonday = $selector->selectVersion($workerIds[2], $workplaceIds[1], '2026-01-05')['selected_weekday_definition'];
$assert(
    count($multiMonday['segments']) === 3
        && array_column($multiMonday['segments'], 'sequence_number') === [1, 2, 3],
    'multi-segment version at a different Workplace preserves ordered 1..N local segments'
);

$unscheduledWeek = $week();
$unscheduledWeek[6]['day_state'] = 'UNSCHEDULED';
$unscheduledId = $service->appendVersion($workerIds[6], $workplaceIds[2], '2026-01-01', null, $unscheduledWeek);
$assert(
    $selector->selectVersion($workerIds[6], $workplaceIds[2], '2026-01-04')['selected_weekday_definition']['day_state'] === 'UNSCHEDULED'
        && $selector->selectVersion($workerIds[6], $workplaceIds[2], '2026-01-04')['selected_weekday_definition']['segments'] === [],
    'UNSCHEDULED remains an explicit no-segment fact on MariaDB'
);

$badOff = $week();
$badOff[5]['segments'] = [['sequence_number' => 1, 'start_local_time' => '08:00', 'end_local_time' => '09:00', 'end_day_offset' => 0]];
$badUnscheduled = $week();
$badUnscheduled[6]['day_state'] = 'UNSCHEDULED';
$badUnscheduled[6]['segments'] = $badOff[5]['segments'];
$badScheduled = $week();
$badScheduled[0]['segments'] = [];
$badOverlap = $week();
$badOverlap[0]['segments'] = [
    ['sequence_number' => 1, 'start_local_time' => '08:00', 'end_local_time' => '13:00', 'end_day_offset' => 0],
    ['sequence_number' => 2, 'start_local_time' => '12:00', 'end_local_time' => '18:00', 'end_day_offset' => 0],
];
$assert(
    $throws(static fn() => $service->appendVersion($workerIds[7], $workplaceIds[2], '2026-01-01', null, $badOff), 'must not contain')
        && $throws(static fn() => $service->appendVersion($workerIds[7], $workplaceIds[2], '2026-01-01', null, $badUnscheduled), 'must not contain')
        && $throws(static fn() => $service->appendVersion($workerIds[7], $workplaceIds[2], '2026-01-01', null, $badScheduled), 'at least one')
        && $throws(static fn() => $service->appendVersion($workerIds[7], $workplaceIds[2], '2026-01-01', null, $badOverlap), 'may not overlap'),
    'MariaDB write boundary rejects illegal state/segment pairs and overlapping local segments'
);

$adjacentWeek = $week();
$adjacentWeek[0]['segments'] = [
    ['sequence_number' => 1, 'start_local_time' => '08:00', 'end_local_time' => '12:00', 'end_day_offset' => 0],
    ['sequence_number' => 2, 'start_local_time' => '12:00', 'end_local_time' => '16:00', 'end_day_offset' => 0],
];
$assert(
    $service->appendVersion($workerIds[7], $workplaceIds[1], '2026-01-01', null, $adjacentWeek) > 0,
    'adjacent local segments are accepted on MariaDB'
);
$nightWeek = $week();
$nightWeek[0]['segments'] = [['sequence_number' => 1, 'start_local_time' => '22:00', 'end_local_time' => '06:00', 'end_day_offset' => 1]];
$nightWeek[1]['segments'] = [['sequence_number' => 1, 'start_local_time' => '08:00', 'end_local_time' => '09:00', 'end_day_offset' => 2]];
$nightId = $service->appendVersion($workerIds[7], $workplaceIds[0], '2026-01-01', null, $nightWeek);
$noOffset = $nightWeek;
$noOffset[0]['segments'][0]['end_day_offset'] = 0;
$assert(
    $nightId > 0
        && $selector->selectVersion($workerIds[7], $workplaceIds[0], '2026-01-05')['selected_weekday_definition']['segments'][0]['end_day_offset'] === 1
        && $selector->selectVersion($workerIds[7], $workplaceIds[0], '2026-01-06')['selected_weekday_definition']['segments'][0]['end_day_offset'] === 2
        && $throws(static fn() => $service->appendVersion($workerIds[7], $workplaceIds[2], '2026-01-01', null, $noOffset), 'end must occur'),
    'overnight uses explicit offset 1; offset 2 is supported without a business max; offset 0 cannot imply overnight'
);

$scheduleA = $service->appendVersion($workerIds[3], $workplaceIds[0], '2026-01-01', '2026-06-01', $week());
$scheduleB = $service->appendVersion($workerIds[3], $workplaceIds[0], '2026-06-01', null, $week());
$assert(
    $selector->selectVersion($workerIds[3], $workplaceIds[0], '2026-05-31')['version']['id'] === $scheduleA
        && $selector->selectVersion($workerIds[3], $workplaceIds[0], '2026-06-01')['version']['id'] === $scheduleB
        && $throws(static fn() => $service->appendVersion($workerIds[3], $workplaceIds[0], '2026-05-01', '2026-07-01', $week()), 'may not overlap'),
    'adjacent local-date versions select on the boundary and same-key overlap is rejected'
);

$clockValue = '2025-12-15T00:00:00Z';
$correctionWriter = new PersonalScheduleVersionService(static function () use (&$clockValue): DateTimeImmutable { return new DateTimeImmutable($clockValue); });
$original = $correctionWriter->appendVersion($workerIds[4], $workplaceIds[0], '2026-01-01', '2027-01-01', $week(), 11, 'Original schedule');
$originalRecorded = (string)Capsule::table(PersonalScheduleVersionsMigration::HEADER_TABLE)->where('id', $original)->value('recorded_at_utc');
$clockValue = '2026-02-01T00:00:00Z';
$correction = $correctionWriter->appendVersion($workerIds[4], $workplaceIds[1], '2026-01-01', '2027-01-01', $week(), 12, 'Corrected Workplace', $original);
$beforeCorrection = $selector->selectVersionAsRecordedAt(
    $workerIds[4], $workplaceIds[0], '2026-03-01', str_replace(' ', 'T', $originalRecorded) . 'Z'
);
$afterCorrectionOld = $selector->selectVersionAsRecordedAt($workerIds[4], $workplaceIds[0], '2026-03-01', '2026-02-02T00:00:00Z');
$afterCorrectionNew = $selector->selectVersionAsRecordedAt($workerIds[4], $workplaceIds[1], '2026-03-01', '2026-02-02T00:00:00Z');
$assert(
    $beforeCorrection['version']['id'] === $original
        && $afterCorrectionOld['status'] === 'NO_VERSION'
        && $afterCorrectionNew['version']['id'] === $correction
        && Capsule::table(PersonalScheduleVersionsMigration::HEADER_TABLE)->where('id', $original)->exists(),
    'as-recorded correction remains reproducible and current selector follows corrected Workplace identity'
);

$assignmentWriter = new WorkerWorkplaceAssignmentVersionService();
$assignmentWriter->appendVersion($workerIds[2], $workplaceIds[0], '2026-01-01T00:00:00Z', null, null, 'Schedule composition A');
$assignmentWriter->appendVersion($workerIds[2], $workplaceIds[1], '2026-01-01T00:00:00Z', null, null, 'Schedule composition B');
$assert(
    $selector->selectVersion($workerIds[2], $workplaceIds[0], '2026-02-01')['version']['workplace_id'] === $workplaceIds[0]
        && $selector->selectVersion($workerIds[2], $workplaceIds[1], '2026-02-01')['version']['workplace_id'] === $workplaceIds[1],
    'P3-L3-2 worker with two effective Workplaces binds to independent schedule headers'
);
$noMembershipVersion = $service->appendVersion($workerIds[6], $workplaceIds[1], '2026-01-01', null, $week());
$assert($noMembershipVersion > 0, 'schedule write is not rejected merely because membership history has not been backfilled');

$timezoneWriter = new WorkplaceTimezoneVersionService();
$timezoneResolver = new WorkplaceTimezoneResolver();
foreach ([0 => 'America/Mexico_City', 1 => 'America/Monterrey', 2 => 'America/Tijuana'] as $index => $iana) {
    $timezoneWriter->appendVersions($workplaceIds[$index], [[
        'timezone_iana' => $iana,
        'valid_from_utc' => '2026-01-01T00:00:00Z',
    ]], null, 'P3-L3-3 disposable timezone');
}
$resolvedA = $selector->selectVersion($workerIds[2], $workplaceIds[0], '2026-02-01');
$resolvedB = $selector->selectVersion($workerIds[2], $workplaceIds[1], '2026-02-01');
$zoneA = $timezoneResolver->resolve((int)$resolvedA['version']['workplace_id'], '2026-02-01T00:00:00Z');
$zoneB = $timezoneResolver->resolve((int)$resolvedB['version']['workplace_id'], '2026-02-01T00:00:00Z');
$assert(
    $zoneA['status'] === 'RESOLVED' && $zoneA['timezone_iana'] === 'America/Mexico_City'
        && $zoneB['status'] === 'RESOLVED' && $zoneB['timezone_iana'] === 'America/Monterrey',
    'each schedule Workplace composes with its own IANA timezone and schedule rows store no timezone'
);

$sameWorker = $workerIds[0];
$connection->beginTransaction();
$connection->selectOne('SELECT id FROM op_rh_personal WHERE id=? FOR UPDATE', [$sameWorker]);
$runConcurrent = static function (int $workerId, array $workplaces, string $from, string $to, string $label) use ($connection, $assert): array {
    $dir = sys_get_temp_dir() . '/p3l33-' . bin2hex(random_bytes(8));
    if (!mkdir($dir, 0700)) {
        throw new RuntimeException('Could not create private MariaDB writer barrier directory.');
    }
    $environment = array_merge(getenv() ?: [], $_ENV, ['DB_DATABASE' => 'bd_portal3_p3_l3_3']);
    $processes = [];
    foreach ($workplaces as $index => $workplaceId) {
        $ready = $dir . '/ready-' . $index;
        $command = [PHP_BINARY, '-d', 'variables_order=EGPCS', __FILE__, '--worker', $ready, (string)$workerId, (string)$workplaceId, $from, $to];
        $pipes = [];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), $environment);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start independent MariaDB schedule writer.');
        }
        fclose($pipes[0]);
        $processes[] = ['process' => $process, 'pipes' => $pipes, 'ready' => $ready];
    }
    $readyDeadline = microtime(true) + 8.0;
    while (microtime(true) < $readyDeadline) {
        if (count(array_filter($processes, static fn(array $p): bool => is_file($p['ready']))) === count($processes)) {
            break;
        }
        usleep(20000);
    }
    $allReady = count(array_filter($processes, static fn(array $p): bool => is_file($p['ready']))) === count($processes);
    $assert($allReady, $label . ': independent writers reached the barrier');
    $waiting = 0;
    if ($allReady) {
        $waitDeadline = microtime(true) + 8.0;
        do {
            $waiting = count($connection->select(
                'SELECT ID FROM information_schema.PROCESSLIST WHERE DB=DATABASE() AND ID<>CONNECTION_ID() '
                . 'AND INFO LIKE ? AND LOWER(INFO) LIKE ?',
                ['%op_rh_personal%', '%for update%']
            ));
            if ($waiting >= count($processes)) {
                break;
            }
            usleep(30000);
        } while (microtime(true) < $waitDeadline);
    }
    $assert($waiting >= count($processes), $label . ': MariaDB observed all writers waiting on the canonical worker-row lock');
    if ($connection->transactionLevel() > 0) {
        $connection->commit();
    }
    $results = [];
    foreach ($processes as $entry) {
        $stdout = stream_get_contents($entry['pipes'][1]);
        $stderr = stream_get_contents($entry['pipes'][2]);
        fclose($entry['pipes'][1]);
        fclose($entry['pipes'][2]);
        $exit = proc_close($entry['process']);
        $results[] = ['exit' => $exit, 'result' => json_decode(trim((string)$stdout), true), 'stderr' => trim((string)$stderr)];
        @unlink($entry['ready']);
    }
    @rmdir($dir);
    return $results;
};
$sameResults = $runConcurrent($sameWorker, [$workplaceIds[0], $workplaceIds[0]], '2026-01-01', '2027-01-01', 'same-key concurrency');
$sameCommitted = count(array_filter($sameResults, static fn(array $r): bool => ($r['result']['outcome'] ?? null) === 'COMMITTED' && $r['exit'] === 0));
$sameRejected = count(array_filter($sameResults, static fn(array $r): bool => ($r['result']['outcome'] ?? null) === 'CONTROLLED_REJECTION' && $r['exit'] === 10));
$sameRows = Capsule::table(PersonalScheduleVersionsMigration::HEADER_TABLE)
    ->where('worker_id', $sameWorker)->where('workplace_id', $workplaceIds[0])->count();
$assert($sameCommitted === 1 && $sameRejected === 1, 'same worker/Workplace overlapping schedule writers produce one commit and one controlled rejection');
$assert($sameRows === 1, 'same-key MariaDB race persists exactly one schedule version and no overlapping pair');

$crossWorker = $workerIds[1];
$connection->beginTransaction();
$connection->selectOne('SELECT id FROM op_rh_personal WHERE id=? FOR UPDATE', [$crossWorker]);
$crossResults = $runConcurrent($crossWorker, [$workplaceIds[1], $workplaceIds[2]], '2026-02-01', '2026-10-01', 'cross-Workplace concurrency');
$crossCommitted = count(array_filter($crossResults, static fn(array $r): bool => ($r['result']['outcome'] ?? null) === 'COMMITTED' && $r['exit'] === 0));
$clockValue = '2027-01-01T00:00:00Z';
$crossA = $selector->selectVersion($crossWorker, $workplaceIds[1], '2026-04-01');
$crossB = $selector->selectVersion($crossWorker, $workplaceIds[2], '2026-04-01');
$assert($crossCommitted === 2, 'same worker/different Workplace concurrent schedule versions both commit');
$assert($crossA['status'] === 'VERSION_FOUND' && $crossB['status'] === 'VERSION_FOUND', 'cross-Workplace overlap remains two independently selectable valid versions');

$invalidWeek = $week();
$invalidWeek[6]['weekday_iso'] = 6;
$assert(
    $throws(static fn() => $service->appendVersion($workerIds[7], $workplaceIds[0], '2026-01-01', null, array_slice($week(), 0, 6)), 'exactly seven')
        && $throws(static fn() => $service->appendVersion($workerIds[7], $workplaceIds[0], '2026-01-01', null, $invalidWeek), 'unique ISO'),
    'MariaDB service rejects incomplete and duplicate-weekday definitions'
);

$clockValue = '2026-10-01T00:00:00Z';
$legacyNoFallback = $selector->selectVersion($workerIds[5], $workplaceIds[0], '2026-10-05');
$assert($legacyNoFallback['status'] === 'NO_VERSION', 'legacy personal/history schedule and station catalog do not fallback or imply DAY_OFF');

$assert(
    $selector->selectVersion(999999999, $workplaceIds[0], '2026-02-01')['status'] === 'DATA_INVALID'
        && $selector->selectVersion($workerIds[0], 999999999, '2026-02-01')['status'] === 'DATA_INVALID'
        && $throws(static fn() => $service->appendVersion(999999999, $workplaceIds[0], '2026-01-01', null, $week()), 'does not exist')
        && $throws(static fn() => $service->appendVersion($workerIds[0], 999999999, '2026-01-01', null, $week()), 'does not exist'),
    'invalid canonical worker and Workplace are rejected'
);
$assert(
    (int)Capsule::table('op_rh_personal')->where('id', $workerIds[2])->value('id_estacion') === $legacyPointerBefore,
    'legacy id_estacion remains unchanged'
);
$legacyStable = true;
foreach ($legacyBefore as $table => $count) {
    $legacyStable = $legacyStable && (int)Capsule::table($table)->count() === $count;
}
$assert($legacyStable, 'no feature writes to personal schedules/history, station schedules, special programming, or attendance');

$parentDeleteBlocked = $throws(static fn() => Capsule::table('op_rh_localidades')->where('id', $workplaceIds[0])->delete());
$assert($parentDeleteBlocked, 'canonical Workplace deletion is restricted while schedule history exists');

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
$populatedChecksum = $snapshot();
$assert(
    $throws(static fn() => PersonalScheduleVersionsMigration::down(), 'Refusing to drop populated'),
    'populated MariaDB DOWN refuses to drop schedule history'
);
$assert($snapshot() === $populatedChecksum, 'populated DOWN refusal preserves all header/day/segment rows');

Capsule::table(PersonalScheduleVersionsMigration::SEGMENT_TABLE)->delete();
Capsule::table(PersonalScheduleVersionsMigration::DAY_TABLE)->delete();
foreach (Capsule::table(PersonalScheduleVersionsMigration::HEADER_TABLE)->orderByDesc('id')->pluck('id') as $versionId) {
    Capsule::table(PersonalScheduleVersionsMigration::HEADER_TABLE)->where('id', $versionId)->delete();
}
PersonalScheduleVersionsMigration::down();
$emptyDown = !$tableExists(PersonalScheduleVersionsMigration::HEADER_TABLE)
    && !$tableExists(PersonalScheduleVersionsMigration::DAY_TABLE)
    && !$tableExists(PersonalScheduleVersionsMigration::SEGMENT_TABLE);
$assert($emptyDown, 'empty MariaDB DOWN removes the three schedule tables in dependency order');
PersonalScheduleVersionsMigration::up();
$assert($tableExists(PersonalScheduleVersionsMigration::HEADER_TABLE), 'schedule migration reapplies after empty MariaDB DOWN');

printf("P3_L3_3_MARIADB_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passed, $failed);
printf("MARIADB_SAME_KEY_COMMITTED: %d\n", $sameCommitted);
printf("MARIADB_SAME_KEY_REJECTED: %d\n", $sameRejected);
printf("MARIADB_CROSS_WORKPLACE_COMMITTED: %d\n", $crossCommitted);
printf("LEGACY_PERSONAL_SCHEDULE_WRITES: 0\n");
printf("LEGACY_SCHEDULE_HISTORY_WRITES: 0\n");
printf("LEGACY_STATION_SCHEDULE_WRITES: 0\n");
printf("SPECIAL_PROGRAMMING_WRITES: 0\n");
printf("LEGACY_ATTENDANCE_WRITES: 0\n");
printf("LEGACY_PERSONAL_LOCATION_WRITES: 0\n");
exit($failed === 0 ? 0 : 1);
