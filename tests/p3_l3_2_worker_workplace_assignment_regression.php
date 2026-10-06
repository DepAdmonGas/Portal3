<?php

declare(strict_types=1);

use App\Models\Operativo\RhPersonalLocalidadVersion;
use App\Services\WorkplaceTimezoneResolver;
use App\Services\WorkplaceTimezoneVersionService;
use App\Services\WorkerWorkplaceAssignmentResolver;
use App\Services\WorkerWorkplaceAssignmentVersionService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../scripts/migrate_workplace_timezone_versions.php';
require_once __DIR__ . '/../scripts/migrate_worker_workplace_assignment_versions.php';

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
    $table->integer('id_estacion')->nullable();
});
Capsule::schema()->create('op_rh_localidades', static function (Blueprint $table): void {
    $table->increments('id');
    $table->integer('numlista')->nullable();
    $table->string('localidad')->nullable();
    $table->string('recuperacion_vapores')->nullable();
});
Capsule::table('op_rh_localidades')->insert([
    ['numlista' => 101, 'localidad' => 'Workplace A', 'recuperacion_vapores' => ''],
    ['numlista' => 102, 'localidad' => 'Workplace B', 'recuperacion_vapores' => ''],
    ['numlista' => 103, 'localidad' => 'Workplace C', 'recuperacion_vapores' => ''],
    ['numlista' => 104, 'localidad' => 'Workplace D', 'recuperacion_vapores' => ''],
]);
Capsule::table('op_rh_personal')->insert([
    ['id_estacion' => 1],
    ['id_estacion' => 1],
    ['id_estacion' => 1],
    ['id_estacion' => 1],
]);

WorkerWorkplaceAssignmentVersionsMigration::up();
WorkplaceTimezoneVersionsMigration::up();

$clockValue = '2025-12-01T00:00:00Z';
$clock = static function () use (&$clockValue): DateTimeImmutable {
    return new DateTimeImmutable($clockValue);
};
$writer = new WorkerWorkplaceAssignmentVersionService($clock);
$resolver = new WorkerWorkplaceAssignmentResolver($clock);

$firstId = $writer->appendVersion(1, 1, '2026-01-01T00:00:00Z', '2026-06-01T00:00:00Z', 7, 'Initial A');
$laterId = $writer->appendVersion(1, 2, '2026-06-01T00:00:00Z', null, 7, 'Later B');
$historicalA = $resolver->resolveAssignments(1, '2026-03-01T00:00:00Z');
$historicalB = $resolver->resolveAssignments(1, '2026-06-01T00:00:00Z');
$assert(
    $historicalA['status'] === 'RESOLVED_SINGLE'
        && $historicalA['assignments'][0]['workplace_id'] === 1
        && $historicalA['assignments'][0]['id'] === $firstId
        && $historicalB['assignments'][0]['workplace_id'] === 2
        && $historicalB['assignments'][0]['id'] === $laterId,
    'valid effective assignments resolve canonical Workplaces across historical periods'
);
$futureDoesNotChangePast = $resolver->resolveAssignments(1, '2026-02-01T00:00:00Z');
$assert(
    $futureDoesNotChangePast['status'] === 'RESOLVED_SINGLE'
        && $futureDoesNotChangePast['assignments'][0]['workplace_id'] === 1,
    'a later effective version does not alter an earlier lookup'
);

$adjacentFirst = $writer->appendVersion(1, 3, '2026-01-01T00:00:00Z', '2026-06-01T00:00:00Z', null, 'Adjacent first');
$adjacentSecond = $writer->appendVersion(1, 3, '2026-06-01T00:00:00Z', null, null, 'Adjacent second');
$adjacentBoundary = $resolver->resolveAssignments(1, '2026-06-01T00:00:00Z');
$assert(
    $adjacentBoundary['status'] === 'RESOLVED_MULTIPLE'
        && array_filter($adjacentBoundary['assignments'], static fn(array $a): bool => $a['id'] === $adjacentSecond) !== [],
    'same-membership half-open adjacent intervals are allowed and boundary selects the later version'
);
$assert(
    $throws(
        static fn() => $writer->appendVersion(1, 3, '2026-05-01T00:00:00Z', '2026-07-01T00:00:00Z'),
        'may not overlap'
    ),
    'overlapping effective intervals for one worker/Workplace membership are rejected'
);

$assert(
    $writer->appendVersion(1, 4, '2026-02-01T00:00:00Z', '2026-03-01T00:00:00Z') > 0,
    'one worker may have overlapping effective assignments at different Workplaces'
);

$none = $resolver->resolveAssignments(2, '2026-04-01T00:00:00Z');
$assert($none['status'] === 'RESOLVED_NONE' && $none['assignments'] === [], 'worker with no history resolves to an explicit empty set');
$multiA = $writer->appendVersion(2, 2, '2026-01-01T00:00:00Z', '2026-12-01T00:00:00Z');
$multiB = $writer->appendVersion(2, 3, '2026-03-01T00:00:00Z', '2026-08-01T00:00:00Z');
$multiple = $resolver->resolveAssignments(2, '2026-04-01T00:00:00Z');
$multipleWorkplaces = array_column($multiple['assignments'], 'workplace_id');
sort($multipleWorkplaces);
$assert(
    $multiple['status'] === 'RESOLVED_MULTIPLE'
        && $multipleWorkplaces === [2, 3]
        && !in_array(1, $multipleWorkplaces, true),
    'concurrent cross-Workplace memberships return both and ignore legacy id_estacion'
);
$assert($multiA > 0 && $multiB > 0, 'both cross-Workplace membership versions were appended');

$assert(
    $throws(static fn() => $writer->appendVersion(999, 1, '2026-01-01T00:00:00Z'), 'does not exist'),
    'nonexistent canonical worker is rejected'
);
$assert(
    $throws(static fn() => $writer->appendVersion(1, 999, '2026-01-01T00:00:00Z'), 'does not exist'),
    'nonexistent canonical Workplace is rejected'
);
$assert(
    $resolver->resolveAssignments(999, '2026-01-01T00:00:00Z')['status'] === 'DATA_INVALID',
    'resolver rejects an unknown worker instead of consulting a legacy alias'
);

$clockValue = '2025-12-15T00:00:00Z';
$originalId = $writer->appendVersion(4, 1, '2026-01-01T00:00:00Z', '2026-12-01T00:00:00Z', 11, 'Recorded original');
$original = Capsule::table('op_rh_personal_localidad_version')->where('id', $originalId)->first();
$clockValue = '2026-02-01T00:00:00Z';
$correctionId = $writer->appendVersion(4, 2, '2026-01-01T00:00:00Z', '2026-12-01T00:00:00Z', 12, 'Corrected Workplace', $originalId);
$currentCorrection = $resolver->resolveAssignments(4, '2026-04-01T00:00:00Z');
$asRecordedBefore = $resolver->resolveAssignmentsAsRecordedAt(
    4,
    '2026-04-01T00:00:00Z',
    str_replace(' ', 'T', (string)$original->recorded_at_utc) . 'Z'
);
$asRecordedAfter = $resolver->resolveAssignmentsAsRecordedAt(4, '2026-04-01T00:00:00Z', '2026-02-02T00:00:00Z');
$assert(
    $currentCorrection['status'] === 'RESOLVED_SINGLE'
        && $currentCorrection['assignments'][0]['id'] === $correctionId
        && $currentCorrection['assignments'][0]['workplace_id'] === 2
        && $asRecordedBefore['assignments'][0]['id'] === $originalId
        && $asRecordedBefore['assignments'][0]['workplace_id'] === 1
        && $asRecordedAfter['assignments'][0]['id'] === $correctionId,
    'append-only correction can change Workplace and reproduces prior/current record-time views'
);
$assert(
    Capsule::table('op_rh_personal_localidad_version')->where('id', $originalId)->exists(),
    'superseded assignment fact remains stored'
);

$immutable = RhPersonalLocalidadVersion::findOrFail($correctionId);
$assert(
    $throws(static function () use ($immutable): void { $immutable->reason = 'overwrite'; $immutable->save(); }, 'append-only')
        && $throws(static fn() => $immutable->delete(), 'append-only'),
    'assignment version model refuses in-place update and deletion'
);

$timezoneWriter = new WorkplaceTimezoneVersionService($clock);
$timezoneResolver = new WorkplaceTimezoneResolver($clock);
foreach ([2 => 'America/Mexico_City', 3 => 'America/Monterrey'] as $workplaceId => $timezoneIana) {
    $timezoneWriter->appendVersions($workplaceId, [[
        'timezone_iana' => $timezoneIana,
        'valid_from_utc' => '2026-01-01T00:00:00Z',
    ]], null, 'Disposable composition fixture');
}
$compositionPass = $multiple['status'] === 'RESOLVED_MULTIPLE';
$composedTimezones = [];
foreach ($multiple['assignments'] as $assignment) {
    $timezone = $timezoneResolver->resolve((int)$assignment['workplace_id'], '2026-04-01T00:00:00Z');
    $compositionPass = $compositionPass && $timezone['status'] === 'RESOLVED';
    $composedTimezones[] = $timezone['timezone_iana'] ?? null;
}
$assert(
    $compositionPass && count(array_unique($composedTimezones)) === 2,
    'concurrent Workplaces compose independently with different IANA timezones'
);

$historyBeforeDown = Capsule::table('op_rh_personal_localidad_version')->orderBy('id')->get()->toArray();
$historyChecksum = hash('sha256', json_encode($historyBeforeDown, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
$assert(
    $throws(static fn() => WorkerWorkplaceAssignmentVersionsMigration::down(), 'Refusing to drop'),
    'populated assignment migration rollback refuses to drop history'
);
$historyAfterDown = Capsule::table('op_rh_personal_localidad_version')->orderBy('id')->get()->toArray();
$assert(
    hash('sha256', json_encode($historyAfterDown, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) === $historyChecksum,
    'populated rollback refusal preserves assignment rows byte-for-byte at query serialization level'
);

foreach (Capsule::table('op_rh_personal_localidad_version')->orderByDesc('id')->pluck('id') as $id) {
    Capsule::table('op_rh_personal_localidad_version')->where('id', $id)->delete();
}
WorkerWorkplaceAssignmentVersionsMigration::down();
$assert(!Capsule::schema()->hasTable('op_rh_personal_localidad_version'), 'empty assignment migration rollback removes the table');
WorkerWorkplaceAssignmentVersionsMigration::up();
$assert(Capsule::schema()->hasTable('op_rh_personal_localidad_version'), 'assignment migration reapplies after an empty rollback');

printf("P3_L3_2_ASSIGNMENT_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passed, $failed);
exit($failed === 0 ? 0 : 1);
