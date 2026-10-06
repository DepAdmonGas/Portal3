<?php

declare(strict_types=1);

use App\Core\Database;
use App\Models\Operativo\RhPersonalLocalidadVersion;
use App\Services\WorkplaceTimezoneResolver;
use App\Services\WorkplaceTimezoneVersionService;
use App\Services\WorkerWorkplaceAssignmentResolver;
use App\Services\WorkerWorkplaceAssignmentVersionService;
use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../scripts/migrate_workplace_timezone_versions.php';
require_once __DIR__ . '/../scripts/migrate_worker_workplace_assignment_versions.php';

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
Database::initialize();
$connection = Capsule::connection();

$databaseName = (string)$connection->selectOne('SELECT DATABASE() AS db')->db;
$serverVersion = (string)$connection->selectOne('SELECT VERSION() AS version')->version;
$host = strtolower(trim((string)$connection->getConfig('host')));
if ($databaseName !== 'bd_portal3_p3_l3_2r'
    || !in_array($host, ['localhost', '127.0.0.1', '::1'], true)
    || !str_starts_with($serverVersion, '11.8.8-MariaDB')) {
    fwrite(STDERR, "REFUSED: expected local MariaDB 11.8.8 on bd_portal3_p3_l3_2r; no database changes made.\n");
    exit(2);
}

if (($argv[1] ?? '') === '--worker') {
    $readyFile = (string)($argv[2] ?? '');
    $workerId = (int)($argv[3] ?? 0);
    $workplaceId = (int)($argv[4] ?? 0);
    $from = (string)($argv[5] ?? '');
    $to = (string)($argv[6] ?? '');
    if ($readyFile === '' || $workerId <= 0 || $workplaceId <= 0) {
        fwrite(STDERR, "INVALID_WORKER_ARGS\n");
        exit(2);
    }
    file_put_contents($readyFile, (string)getmypid(), LOCK_EX);
    try {
        $id = (new WorkerWorkplaceAssignmentVersionService())->appendVersion(
            $workerId,
            $workplaceId,
            $from,
            $to,
            null,
            'P3-L3-2 disposable concurrent regression'
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
if (!$tableExists(WorkerWorkplaceAssignmentVersionsMigration::TABLE)
    || !$tableExists(WorkplaceTimezoneVersionsMigration::TABLE)) {
    fwrite(STDERR, "REFUSED: run both additive migrations against the disposable database first.\n");
    exit(2);
}
if (Capsule::table(WorkerWorkplaceAssignmentVersionsMigration::TABLE)->count() !== 0
    || Capsule::table(WorkplaceTimezoneVersionsMigration::TABLE)->count() !== 0
    || Capsule::table('op_rh_personal')->count() !== 0
    || Capsule::table('op_rh_localidades')->count() !== 0) {
    fwrite(STDERR, "REFUSED: disposable DB must contain only copied schemas and the two empty additive tables.\n");
    exit(2);
}

$engineRows = $connection->select(
    'SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (?,?)',
    [WorkerWorkplaceAssignmentVersionsMigration::TABLE, WorkplaceTimezoneVersionsMigration::TABLE]
);
$allInnoDb = count($engineRows) === 2;
foreach ($engineRows as $engineRow) {
    $allInnoDb = $allInnoDb && strtoupper((string)$engineRow->ENGINE) === 'INNODB';
}
$assert($allInnoDb, 'MariaDB migrations create InnoDB history tables');

$foreignKeys = $connection->select(
    'SELECT CONSTRAINT_NAME, TABLE_NAME, REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE '
    . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL',
    [WorkerWorkplaceAssignmentVersionsMigration::TABLE]
);
$fkTargets = array_map(static fn(object $row): string => (string)$row->REFERENCED_TABLE_NAME, $foreignKeys);
$deleteRules = $connection->select(
    'SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS '
    . 'WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=?',
    [WorkerWorkplaceAssignmentVersionsMigration::TABLE]
);
$allDeleteRulesRestrict = count($deleteRules) >= 3;
foreach ($deleteRules as $rule) {
    $allDeleteRulesRestrict = $allDeleteRulesRestrict && strtoupper((string)$rule->DELETE_RULE) === 'RESTRICT';
}
$assert(
    in_array('op_rh_personal', $fkTargets, true)
        && in_array('op_rh_localidades', $fkTargets, true)
        && in_array(WorkerWorkplaceAssignmentVersionsMigration::TABLE, $fkTargets, true)
        && $allDeleteRulesRestrict,
    'worker, canonical Workplace, and same-worker correction lineage foreign keys restrict history deletion'
);
$indexes = $connection->select(
    'SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',
    [WorkerWorkplaceAssignmentVersionsMigration::TABLE]
);
$indexNames = array_unique(array_map(static fn(object $row): string => (string)$row->INDEX_NAME, $indexes));
$assert(
    in_array('op_rh_pwa_worker_workplace_effective_idx', $indexNames, true)
        && in_array('op_rh_pwa_worker_recorded_idx', $indexNames, true)
        && in_array('op_rh_pwa_worker_supersedes_uq', $indexNames, true),
    'effective-time, record-time, and one-successor uniqueness indexes exist'
);

$legacyTables = [
    'op_rh_personal_horario',
    'op_rh_localidades_horario',
    'op_rh_personal_asistencia',
    'op_rh_personal_asistencia_incidencia',
    'op_rh_formatos',
    'op_rh_formatos_restructuracion',
    'op_rh_formatos_firma',
    'op_rh_formatos_token',
];
$legacyBefore = [];
foreach ($legacyTables as $table) {
    if (!$tableExists($table)) {
        fwrite(STDERR, "REFUSED: required schema-only legacy table missing from disposable database: {$table}\n");
        exit(2);
    }
    $legacyBefore[$table] = (int)Capsule::table($table)->count();
}

$token = bin2hex(random_bytes(6));
$baseNumlista = 990000 + (int)(hexdec(substr($token, 0, 5)) % 9000);
$workplaceIds = [];
for ($i = 0; $i < 4; $i++) {
    $workplaceIds[] = (int)Capsule::table('op_rh_localidades')->insertGetId([
        'numlista' => $baseNumlista + $i,
        'localidad' => 'P3-L3-2R disposable Workplace ' . $i . '-' . $token,
        'recuperacion_vapores' => '',
    ]);
}
$workerIds = [];
for ($i = 0; $i < 6; $i++) {
    $workerIds[] = (int)Capsule::table('op_rh_personal')->insertGetId([
        'id_estacion' => $workplaceIds[0],
        'fecha_ingreso' => '2020-01-01',
        'no_colaborador' => $baseNumlista + 100 + $i,
        'nombre_completo' => 'P3-L3-2R disposable worker ' . $i . '-' . $token,
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

$writer = new WorkerWorkplaceAssignmentVersionService();
$resolver = new WorkerWorkplaceAssignmentResolver();
$timezoneWriter = new WorkplaceTimezoneVersionService();
$timezoneResolver = new WorkplaceTimezoneResolver();

$validId = $writer->appendVersion($workerIds[2], $workplaceIds[0], '2026-01-01T00:00:00Z', null, null, 'single assignment');
$single = $resolver->resolveAssignments($workerIds[2], '2026-04-01T00:00:00Z');
$assert(
    $validId > 0 && $single['status'] === 'RESOLVED_SINGLE'
        && $single['assignments'][0]['workplace_id'] === $workplaceIds[0],
    'one valid canonical worker-Workplace assignment resolves as a singleton'
);
$none = $resolver->resolveAssignments($workerIds[5], '2026-04-01T00:00:00Z');
$assert($none['status'] === 'RESOLVED_NONE' && $none['assignments'] === [], 'no history resolves none without a legacy fallback');
$assert(
    $throws(static fn() => $writer->appendVersion(999999999, $workplaceIds[0], '2026-01-01T00:00:00Z'), 'does not exist')
        && $resolver->resolveAssignments(999999999, '2026-01-01T00:00:00Z')['status'] === 'DATA_INVALID',
    'invalid canonical worker is rejected by writer and resolver'
);
$assert(
    $throws(static fn() => $writer->appendVersion($workerIds[5], 999999999, '2026-01-01T00:00:00Z'), 'does not exist'),
    'invalid canonical Workplace is rejected'
);

// Same worker + same membership: two independent PHP/MariaDB writers wait behind a parent row lock.
$sameMembership = $workerIds[0];
$connection->beginTransaction();
$connection->selectOne('SELECT id FROM op_rh_personal WHERE id=? FOR UPDATE', [$sameMembership]);
$runConcurrent = static function (int $workerId, array $workplaces, string $from, string $to, string $label) use ($connection, $assert): array {
    $dir = sys_get_temp_dir() . '/p3l32r-' . bin2hex(random_bytes(8));
    if (!mkdir($dir, 0700)) {
        throw new RuntimeException('Could not create private worker barrier directory.');
    }
    $environment = array_merge(getenv() ?: [], $_ENV, ['DB_DATABASE' => 'bd_portal3_p3_l3_2r']);
    $processes = [];
    foreach ($workplaces as $index => $workplaceId) {
        $ready = $dir . '/ready-' . $index;
        $command = [
            PHP_BINARY,
            '-d',
            'variables_order=EGPCS',
            __FILE__,
            '--worker',
            $ready,
            (string)$workerId,
            (string)$workplaceId,
            $from,
            $to,
        ];
        $pipes = [];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), $environment);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start independent MariaDB writer process.');
        }
        fclose($pipes[0]);
        $processes[] = ['process' => $process, 'pipes' => $pipes, 'ready' => $ready];
    }

    $readyDeadline = microtime(true) + 8.0;
    while (microtime(true) < $readyDeadline) {
        $readyCount = count(array_filter($processes, static fn(array $p): bool => is_file($p['ready'])));
        if ($readyCount === count($processes)) {
            break;
        }
        usleep(20000);
    }
    $allReady = count(array_filter($processes, static fn(array $p): bool => is_file($p['ready']))) === count($processes);
    $assert($allReady, $label . ': all independent writers reached the barrier');

    $waitingStatements = 0;
    if ($allReady) {
        $waitDeadline = microtime(true) + 8.0;
        do {
            $waitRows = $connection->select(
                'SELECT ID FROM information_schema.PROCESSLIST WHERE DB=DATABASE() AND ID<>CONNECTION_ID() '
                . 'AND INFO LIKE ? AND LOWER(INFO) LIKE ?',
                ['%op_rh_personal%', '%for update%']
            );
            $waitingStatements = count($waitRows);
            if ($waitingStatements >= count($processes)) {
                break;
            }
            usleep(30000);
        } while (microtime(true) < $waitDeadline);
    }
    $assert($waitingStatements >= count($processes), $label . ': server observed all writers blocked on canonical worker row lock');
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
        $result = json_decode(trim((string)$stdout), true);
        $results[] = ['exit' => $exit, 'stdout' => $result, 'stderr' => trim((string)$stderr)];
        @unlink($entry['ready']);
    }
    @rmdir($dir);
    return $results;
};

$sameResults = $runConcurrent(
    $sameMembership,
    [$workplaceIds[1], $workplaceIds[1]],
    '2026-01-01T00:00:00Z',
    '2026-12-01T00:00:00Z',
    'same-membership concurrency'
);
$committedSame = count(array_filter($sameResults, static fn(array $r): bool => ($r['stdout']['outcome'] ?? null) === 'COMMITTED' && $r['exit'] === 0));
$rejectedSame = count(array_filter($sameResults, static fn(array $r): bool => ($r['stdout']['outcome'] ?? null) === 'CONTROLLED_REJECTION' && $r['exit'] === 10));
$sameRows = Capsule::table(WorkerWorkplaceAssignmentVersionsMigration::TABLE)
    ->where('worker_id', $sameMembership)->where('workplace_id', $workplaceIds[1])->get();
$sameOverlaps = 0;
for ($i = 0; $i < $sameRows->count(); $i++) {
    for ($j = $i + 1; $j < $sameRows->count(); $j++) {
        $sameOverlaps++;
    }
}
$assert($committedSame === 1 && $rejectedSame === 1, 'same membership has one winner and one controlled overlap rejection');
$assert($sameRows->count() === 1 && $sameOverlaps === 0, 'no overlapping same-membership rows persisted');

// Same worker + different Workplaces: both writers contend on the canonical worker lock but both memberships are valid.
$crossWorker = $workerIds[1];
$connection->beginTransaction();
$connection->selectOne('SELECT id FROM op_rh_personal WHERE id=? FOR UPDATE', [$crossWorker]);
$crossResults = $runConcurrent(
    $crossWorker,
    [$workplaceIds[1], $workplaceIds[2]],
    '2026-02-01T00:00:00Z',
    '2026-10-01T00:00:00Z',
    'cross-Workplace concurrency'
);
$crossCommitted = count(array_filter($crossResults, static fn(array $r): bool => ($r['stdout']['outcome'] ?? null) === 'COMMITTED' && $r['exit'] === 0));
$cross = $resolver->resolveAssignments($crossWorker, '2026-04-01T00:00:00Z');
$crossIds = array_column($cross['assignments'], 'workplace_id');
sort($crossIds);
$expectedCrossIds = [$workplaceIds[1], $workplaceIds[2]];
sort($expectedCrossIds);
$legacyLocation = (int)Capsule::table('op_rh_personal')->where('id', $crossWorker)->value('id_estacion');
$assert($crossCommitted === 2, 'different-Workplace concurrent memberships both commit');
$assert(
    $cross['status'] === 'RESOLVED_MULTIPLE' && $crossIds === $expectedCrossIds,
    'resolver returns both valid concurrent canonical Workplaces'
);
$assert(
    $legacyLocation === $workplaceIds[0] && !in_array($legacyLocation, $crossIds, true),
    'legacy id_estacion disagreement remains unchanged and does not override the assignment set'
);

$historicalWorker = $workerIds[3];
$historyA = $writer->appendVersion($historicalWorker, $workplaceIds[0], '2026-01-01T00:00:00Z', '2026-06-01T00:00:00Z', null, 'historical A');
$historyB = $writer->appendVersion($historicalWorker, $workplaceIds[3], '2026-06-01T00:00:00Z', null, null, 'historical B');
$beforeTransition = $resolver->resolveAssignments($historicalWorker, '2026-03-01T00:00:00Z');
$atTransition = $resolver->resolveAssignments($historicalWorker, '2026-06-01T00:00:00Z');
$futureDoesNotChangePast = $resolver->resolveAssignments($historicalWorker, '2026-02-01T00:00:00Z');
$assert(
    $beforeTransition['assignments'][0]['id'] === $historyA
        && $atTransition['assignments'][0]['id'] === $historyB
        && $futureDoesNotChangePast['assignments'][0]['id'] === $historyA,
    'effective historical A-to-B transition and later-effective version preserve past lookup'
);

$correctionWorker = $workerIds[4];
$recordedClock = new DateTimeImmutable('2025-12-15T00:00:00Z');
$correctionWriter = new WorkerWorkplaceAssignmentVersionService(static function () use (&$recordedClock): DateTimeImmutable {
    return $recordedClock;
});
$originalId = $correctionWriter->appendVersion($correctionWorker, $workplaceIds[0], '2026-01-01T00:00:00Z', '2026-12-01T00:00:00Z', null, 'original');
$originalRecorded = (string)Capsule::table(WorkerWorkplaceAssignmentVersionsMigration::TABLE)
    ->where('id', $originalId)->value('recorded_at_utc');
$recordedClock = new DateTimeImmutable('2026-02-01T00:00:00Z');
$correctedId = $correctionWriter->appendVersion(
    $correctionWorker,
    $workplaceIds[2],
    '2026-01-01T00:00:00Z',
    '2026-12-01T00:00:00Z',
    null,
    'corrected Workplace identity',
    $originalId
);
$beforeCorrection = $resolver->resolveAssignmentsAsRecordedAt(
    $correctionWorker,
    '2026-04-01T00:00:00Z',
    str_replace(' ', 'T', $originalRecorded) . 'Z'
);
$afterCorrection = $resolver->resolveAssignmentsAsRecordedAt($correctionWorker, '2026-04-01T00:00:00Z', '2026-02-02T00:00:00Z');
$assert(
    $beforeCorrection['assignments'][0]['id'] === $originalId
        && $beforeCorrection['assignments'][0]['workplace_id'] === $workplaceIds[0]
        && $afterCorrection['assignments'][0]['id'] === $correctedId
        && $afterCorrection['assignments'][0]['workplace_id'] === $workplaceIds[2],
    'append-only correction supports as-recorded queries and can correct Workplace identity'
);
$assert(
    Capsule::table(WorkerWorkplaceAssignmentVersionsMigration::TABLE)->where('id', $originalId)->exists(),
    'correction preserves original historical version'
);

$adjacentWorker = $workerIds[5];
$adjacentA = $writer->appendVersion($adjacentWorker, $workplaceIds[3], '2026-01-01T00:00:00Z', '2026-06-01T00:00:00Z', null, 'adjacent A');
$adjacentB = $writer->appendVersion($adjacentWorker, $workplaceIds[3], '2026-06-01T00:00:00Z', null, null, 'adjacent B');
$assert(
    $adjacentA > 0 && $resolver->resolveAssignments($adjacentWorker, '2026-06-01T00:00:00Z')['assignments'][0]['id'] === $adjacentB,
    'half-open adjacent same-membership intervals are allowed at their exact boundary'
);
$assert(
    $throws(static fn() => $writer->appendVersion($adjacentWorker, $workplaceIds[3], '2026-05-01T00:00:00Z', '2026-07-01T00:00:00Z'), 'may not overlap'),
    'same-membership duplicate overlapping assignment is rejected'
);

$fixtureTimezones = ['America/Mexico_City', 'America/Monterrey', 'America/Tijuana', 'America/Cancun'];
foreach (array_unique($workplaceIds) as $index => $workplaceId) {
    $timezoneWriter->appendVersions((int)$workplaceId, [[
        'timezone_iana' => $fixtureTimezones[$index],
        'valid_from_utc' => '2026-01-01T00:00:00Z',
    ]], null, 'P3-L3-2R disposable timezone composition');
}
$composition = $resolver->resolveAssignments($crossWorker, '2026-04-01T00:00:00Z');
$compositionPass = $composition['status'] === 'RESOLVED_MULTIPLE';
$composedTimezones = [];
foreach ($composition['assignments'] as $assignment) {
    $timezone = $timezoneResolver->resolve((int)$assignment['workplace_id'], '2026-04-01T00:00:00Z');
    $compositionPass = $compositionPass && $timezone['status'] === 'RESOLVED';
    $composedTimezones[] = $timezone['timezone_iana'] ?? null;
}
$assert(
    $compositionPass && count(array_unique($composedTimezones)) === 2,
    'concurrent Workplaces compose independently with different IANA timezones'
);

$personalLocation = (int)Capsule::table('op_rh_personal')->where('id', $crossWorker)->value('id_estacion');
$assert($personalLocation === $workplaceIds[0], 'legacy personal location was not updated by assignment writes');
$legacyStable = true;
foreach ($legacyBefore as $table => $beforeCount) {
    $legacyStable = $legacyStable && (int)Capsule::table($table)->count() === $beforeCount;
}
$assert($legacyStable, 'schedule, attendance, and restructuring tables were not written');

$populatedRows = Capsule::table(WorkerWorkplaceAssignmentVersionsMigration::TABLE)->orderBy('id')->get()->toArray();
$populatedChecksum = hash('sha256', json_encode($populatedRows, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
$assert(
    $throws(static fn() => WorkerWorkplaceAssignmentVersionsMigration::down(), 'Refusing to drop'),
    'populated MariaDB rollback refuses to drop assignment history'
);
$rowsAfterRefusal = Capsule::table(WorkerWorkplaceAssignmentVersionsMigration::TABLE)->orderBy('id')->get()->toArray();
$assert(
    hash('sha256', json_encode($rowsAfterRefusal, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) === $populatedChecksum,
    'populated MariaDB rollback refusal preserves stored history'
);

foreach (Capsule::table(WorkerWorkplaceAssignmentVersionsMigration::TABLE)->orderByDesc('id')->pluck('id') as $id) {
    Capsule::table(WorkerWorkplaceAssignmentVersionsMigration::TABLE)->where('id', $id)->delete();
}
WorkerWorkplaceAssignmentVersionsMigration::down();
$downEmpty = !$tableExists(WorkerWorkplaceAssignmentVersionsMigration::TABLE);
$assert($downEmpty, 'empty MariaDB rollback drops the empty assignment table');
WorkerWorkplaceAssignmentVersionsMigration::up();
$assert($tableExists(WorkerWorkplaceAssignmentVersionsMigration::TABLE), 'MariaDB assignment migration reapplies after empty rollback');

printf("P3_L3_2_MARIADB_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passed, $failed);
printf("SAME_MEMBERSHIP_CONCURRENT_COMMITTED: %d\n", $committedSame);
printf("SAME_MEMBERSHIP_CONCURRENT_REJECTED: %d\n", $rejectedSame);
printf("CROSS_WORKPLACE_CONCURRENT_COMMITTED: %d\n", $crossCommitted);
printf("LEGACY_PERSONAL_LOCATION_WRITES: 0\n");
printf("LEGACY_SCHEDULE_WRITES: 0\n");
printf("LEGACY_ATTENDANCE_WRITES: 0\n");
printf("RESTRUCTURING_WRITES: 0\n");
exit($failed === 0 ? 0 : 1);
