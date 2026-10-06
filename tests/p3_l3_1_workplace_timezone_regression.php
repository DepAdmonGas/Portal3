<?php

declare(strict_types=1);

use App\Models\Operativo\RhLocalidadTimezoneVersion;
use App\Services\WorkplaceTimezoneResolver;
use App\Services\WorkplaceTimezoneVersionService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../scripts/migrate_workplace_timezone_versions.php';

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
$throws = static function (callable $callback): bool {
    try {
        $callback();
        return false;
    } catch (Throwable) {
        return true;
    }
};
$configure = static function (string $database): void {
    $capsule = new Capsule();
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => $database,
        'prefix' => '',
        'foreign_key_constraints' => true,
        'options' => [PDO::ATTR_TIMEOUT => 5],
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    Capsule::connection()->statement('PRAGMA foreign_keys = ON');
    Capsule::connection()->statement('PRAGMA busy_timeout = 5000');
};
$createFixtureSchema = static function (): void {
    Capsule::schema()->create('op_rh_localidades', static function (Blueprint $table): void {
        $table->increments('id');
        $table->string('localidad')->nullable();
    });

    foreach ([
        'op_rh_personal_horario',
        'op_rh_localidades_horario',
        'op_rh_personal_asistencia',
        'op_rh_personal_asistencia_incidencia',
        'op_rh_localidades_programacion_especial',
    ] as $legacyTable) {
        Capsule::schema()->create($legacyTable, static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('fixture_value');
        });
        Capsule::table($legacyTable)->insert(['fixture_value' => 'untouched']);
    }

    Capsule::table('op_rh_localidades')->insert([
        ['localidad' => 'Workplace A'],
        ['localidad' => 'Workplace B'],
        ['localidad' => 'Workplace C'],
    ]);
};
$legacySnapshot = static function (): string {
    $snapshot = [];
    foreach ([
        'op_rh_personal_horario',
        'op_rh_localidades_horario',
        'op_rh_personal_asistencia',
        'op_rh_personal_asistencia_incidencia',
        'op_rh_localidades_programacion_especial',
    ] as $legacyTable) {
        $snapshot[$legacyTable] = Capsule::table($legacyTable)->orderBy('id')->get()->toArray();
    }
    return json_encode($snapshot, JSON_THROW_ON_ERROR);
};

$configure(':memory:');
$createFixtureSchema();
$legacyBefore = $legacySnapshot();

// Empty rollback and re-apply are exercised before adding any history.
WorkplaceTimezoneVersionsMigration::up();
$assert(Capsule::schema()->hasTable(WorkplaceTimezoneVersionsMigration::TABLE), 'additive migration creates the timezone history table');
WorkplaceTimezoneVersionsMigration::down();
$assert(!Capsule::schema()->hasTable(WorkplaceTimezoneVersionsMigration::TABLE), 'empty migration rollback drops only the empty timezone table');
WorkplaceTimezoneVersionsMigration::up();
$assert(Capsule::schema()->hasTable(WorkplaceTimezoneVersionsMigration::TABLE), 'migration can be reapplied after empty rollback');
$foreignKeys = Capsule::connection()->select("PRAGMA foreign_key_list('" . WorkplaceTimezoneVersionsMigration::TABLE . "')");
$hasCanonicalWorkplaceFk = false;
$hasCompositeLineageFk = false;
foreach ($foreignKeys as $foreignKey) {
    if ($foreignKey->table === 'op_rh_localidades'
        && $foreignKey->from === 'workplace_id'
        && $foreignKey->to === 'id'
        && strtoupper($foreignKey->on_delete) === 'RESTRICT') {
        $hasCanonicalWorkplaceFk = true;
    }
}
foreach ($foreignKeys as $firstKey) {
    foreach ($foreignKeys as $secondKey) {
        if ($firstKey->id === $secondKey->id
            && $firstKey->table === WorkplaceTimezoneVersionsMigration::TABLE
            && $secondKey->table === WorkplaceTimezoneVersionsMigration::TABLE
            && $firstKey->from === 'workplace_id'
            && $firstKey->to === 'workplace_id'
            && $secondKey->from === 'supersedes_id'
            && $secondKey->to === 'id') {
            $hasCompositeLineageFk = true;
        }
    }
}
$assert(
    $hasCanonicalWorkplaceFk && $hasCompositeLineageFk,
    'migration restricts Workplace deletion and enforces same-Workplace correction lineage'
);

$clockValue = '2026-01-01T00:00:00Z';
$clock = static function () use (&$clockValue): DateTimeImmutable {
    return new DateTimeImmutable($clockValue);
};
$writer = new WorkplaceTimezoneVersionService($clock);
$resolver = new WorkplaceTimezoneResolver($clock);

$validIdentifiers = ['America/Mexico_City', 'Europe/Madrid'];
$assert(
    WorkplaceTimezoneVersionService::isValidIanaTimezone($validIdentifiers[0])
        && WorkplaceTimezoneVersionService::isValidIanaTimezone($validIdentifiers[1]),
    'runtime IANA validation accepts Mexico City and a second IANA identifier'
);
$invalidIdentifiers = ['CST', 'UTC-6', 'GMT-0600', 'Mexico City', 'Definitely/Not_A_Zone'];
$invalidRejected = true;
foreach ($invalidIdentifiers as $invalidIdentifier) {
    $invalidRejected = $invalidRejected && !WorkplaceTimezoneVersionService::isValidIanaTimezone($invalidIdentifier);
}
$assert($invalidRejected, 'runtime IANA validation rejects abbreviations, offsets, labels, and invalid names');

$defaultTimezoneBefore = date_default_timezone_get();
$appTimezoneBefore = $_ENV['APP_TIMEZONE'] ?? null;
date_default_timezone_set('Pacific/Honolulu');
$_ENV['APP_TIMEZONE'] = 'America/Los_Angeles';
$assert(
    $resolver->resolve(1, '2026-02-01T12:00:00-08:00')['status'] === 'TIMEZONE_UNRESOLVED',
    'existing Workplace without a version resolves explicitly unresolved with no ambient fallback'
);
date_default_timezone_set($defaultTimezoneBefore);
if ($appTimezoneBefore === null) {
    unset($_ENV['APP_TIMEZONE']);
} else {
    $_ENV['APP_TIMEZONE'] = $appTimezoneBefore;
}

$firstId = $writer->appendVersions(1, [[
    'timezone_iana' => 'America/Mexico_City',
    'valid_from_utc' => '2026-01-01T00:00:00Z',
    'valid_to_utc' => '2026-06-01T00:00:00Z',
]], 11, 'Initial effective period')[0];
$clockValue = '2026-01-02T00:00:00Z';
$secondId = $writer->appendVersions(1, [[
    'timezone_iana' => 'Europe/Madrid',
    'valid_from_utc' => '2026-06-01T00:00:00Z',
    'valid_to_utc' => null,
]], 11, 'Adjacent future period')[0];
$janResolution = $resolver->resolve(1, '2026-02-01T12:00:00Z');
$juneBoundary = $resolver->resolve(1, '2026-06-01T00:00:00Z');
$assert(
    $janResolution['status'] === 'RESOLVED'
        && $janResolution['version_id'] === $firstId
        && $janResolution['timezone_iana'] === 'America/Mexico_City',
    'historical effective lookup resolves the earlier exact version'
);
$assert(
    $juneBoundary['status'] === 'RESOLVED'
        && $juneBoundary['version_id'] === $secondId
        && $juneBoundary['timezone_iana'] === 'Europe/Madrid',
    'adjacent half-open boundary resolves to the later version deterministically'
);
$assert(
    $resolver->resolve(1, '2026-04-01T00:00:00Z')['version_id'] === $firstId,
    'later version does not affect lookup within the earlier effective interval'
);
$assert(
    $throws(static fn() => $writer->appendVersions(1, [[
        'timezone_iana' => 'America/New_York',
        'valid_from_utc' => '2026-05-01T00:00:00Z',
        'valid_to_utc' => '2026-07-01T00:00:00Z',
    ]])),
    'overlapping intervals for the same Workplace are rejected'
);
$otherWorkplaceId = $writer->appendVersions(2, [[
    'timezone_iana' => 'America/New_York',
    'valid_from_utc' => '2026-01-01T00:00:00Z',
    'valid_to_utc' => null,
]])[0];
$assert(
    $resolver->resolve(2, '2026-04-01T00:00:00Z')['version_id'] === $otherWorkplaceId,
    'other Workplace may independently use an overlapping calendar interval'
);
$ambiguousRaw = [
    'workplace_id' => 2,
    'timezone_iana' => 'Europe/Madrid',
    'valid_from_utc' => '2026-03-01 00:00:00.000000',
    'valid_to_utc' => '2026-05-01 00:00:00.000000',
    'recorded_at_utc' => '2026-01-02 00:00:00.000000',
];
Capsule::table(WorkplaceTimezoneVersionsMigration::TABLE)->insert($ambiguousRaw);
$assert(
    $resolver->resolve(2, '2026-04-01T00:00:00Z')['status'] === 'AMBIGUOUS',
    'resolver reports an explicit ambiguous status for overlapping persisted facts'
);

$invalidWritesRejected = true;
foreach ($invalidIdentifiers as $invalidIdentifier) {
    $invalidWritesRejected = $invalidWritesRejected && $throws(static fn() => $writer->appendVersions(3, [[
        'timezone_iana' => $invalidIdentifier,
        'valid_from_utc' => '2026-01-01T00:00:00Z',
    ]]));
}
$assert($invalidWritesRejected, 'write service rejects each required invalid timezone form');
$assert(
    $throws(static fn() => $writer->appendVersions(99999, [[
        'timezone_iana' => 'America/Mexico_City',
        'valid_from_utc' => '2026-01-01T00:00:00Z',
    ]])) && $resolver->resolve(99999, '2026-01-01T00:00:00Z')['status'] === 'DATA_INVALID',
    'nonexistent canonical Workplace is rejected by writes and invalid in resolution'
);
$assert(
    $throws(static fn() => $writer->appendVersions(3, [[
        'timezone_iana' => 'America/Mexico_City',
        'valid_from_utc' => '2026-05-01T00:00:00Z',
        'valid_to_utc' => '2026-05-01T00:00:00Z',
    ]])),
    'zero-length effective intervals are rejected'
);
$assert(
    $throws(static fn() => $writer->appendVersions(3, [[
        'timezone_iana' => 'America/Mexico_City',
        'valid_from_utc' => '2026-02-30T00:00:00Z',
    ]])),
    'invalid calendar timestamps are rejected'
);

$originalId = $writer->appendVersions(3, [[
    'timezone_iana' => 'America/Mexico_City',
    'valid_from_utc' => '2025-12-31T18:00:00-06:00',
    'valid_to_utc' => null,
]])[0];
$clockValue = '2026-04-01T00:00:00Z';
$correctedId = $writer->appendVersions(3, [[
    'timezone_iana' => 'Europe/Madrid',
    'valid_from_utc' => '2026-01-01T00:00:00Z',
    'valid_to_utc' => null,
    'supersedes_id' => $originalId,
]], 12, 'Corrected recorded timezone')[0];
$beforeCorrection = $resolver->resolve(3, '2026-05-01T00:00:00Z', '2026-03-31T23:59:59.999999Z');
$afterCorrection = $resolver->resolve(3, '2026-05-01T00:00:00Z', '2026-04-01T00:00:00Z');
$assert(
    $beforeCorrection['status'] === 'RESOLVED'
        && $beforeCorrection['version_id'] === $originalId
        && $beforeCorrection['timezone_iana'] === 'America/Mexico_City'
        && $afterCorrection['version_id'] === $correctedId
        && $afterCorrection['timezone_iana'] === 'Europe/Madrid',
    'record-time query reproduces pre-correction knowledge and current correction'
);
$assert(
    RhLocalidadTimezoneVersion::query()->whereKey($originalId)->exists(),
    'correction preserves the original append-only version row'
);
$immutableRow = RhLocalidadTimezoneVersion::query()->findOrFail($originalId);
$immutableRow->timezone_iana = 'Europe/Madrid';
$assert(
    $throws(static fn() => $immutableRow->save())
        && $throws(static fn() => RhLocalidadTimezoneVersion::query()->findOrFail($originalId)->delete()),
    'Eloquent model refuses in-place update and deletion of recorded history'
);
$assert(
    $throws(static fn() => $writer->appendVersions(2, [[
        'timezone_iana' => 'America/New_York',
        'valid_from_utc' => '2026-01-01T00:00:00Z',
        'supersedes_id' => $correctedId,
    ]])),
    'correction cannot supersede another Workplace history row'
);

$dstTimezone = new DateTimeZone('America/New_York');
$dstTransitions = $dstTimezone->getTransitions(
    (new DateTimeImmutable('2026-01-01T00:00:00Z'))->getTimestamp(),
    (new DateTimeImmutable('2026-12-31T23:59:59Z'))->getTimestamp()
);
$assert(
    $dstTimezone->getName() === 'America/New_York' && is_array($dstTransitions) && count($dstTransitions) >= 2,
    'DST IANA identity resolves to runtime timezone rules rather than a fixed offset'
);

$assert(
    $throws(static fn() => WorkplaceTimezoneVersionsMigration::down()),
    'populated migration rollback safely refuses to erase version history'
);
$assert(
    Capsule::schema()->hasTable(WorkplaceTimezoneVersionsMigration::TABLE),
    'populated rollback refusal leaves the timezone history table intact'
);
$assert(
    $legacyBefore === $legacySnapshot(),
    'migration and timezone domain leave legacy schedule and attendance fixtures unchanged'
);

// Concurrent overlap attempt against a disposable file-backed SQLite database.
$concurrencyDb = tempnam(sys_get_temp_dir(), 'p3-l3-1-tz-');
if ($concurrencyDb === false) {
    $assert(false, 'concurrent overlap test allocates disposable database');
} elseif (!function_exists('pcntl_fork')) {
    $assert(false, 'concurrent overlap test requires pcntl support');
    @unlink($concurrencyDb);
} else {
    $configure($concurrencyDb);
    $createFixtureSchema();
    WorkplaceTimezoneVersionsMigration::up();
    Capsule::connection()->disconnect();

    $children = [];
    for ($i = 0; $i < 2; $i++) {
        $pid = pcntl_fork();
        if ($pid === -1) {
            break;
        }
        if ($pid === 0) {
            $configure($concurrencyDb);
            // Let both child processes establish their independent SQLite connections first.
            usleep(150000);
            try {
                $childWriter = new WorkplaceTimezoneVersionService();
                $childWriter->appendVersions(1, [[
                    'timezone_iana' => 'America/Mexico_City',
                    'valid_from_utc' => '2026-01-01T00:00:00Z',
                    'valid_to_utc' => null,
                ]]);
                exit(0);
            } catch (Throwable) {
                exit(10);
            }
        }
        $children[] = $pid;
    }

    $outcomes = [];
    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
        $outcomes[] = pcntl_wifexited($status) ? pcntl_wexitstatus($status) : 255;
    }

    $configure($concurrencyDb);
    $committed = (int)Capsule::table(WorkplaceTimezoneVersionsMigration::TABLE)->count();
    $concurrentResolver = new WorkplaceTimezoneResolver();
    $concurrentResult = $concurrentResolver->resolve(1, '2026-02-01T00:00:00Z');
    $assert(
        count($children) === 2
            && count($outcomes) === 2
            && count(array_filter($outcomes, static fn(int $outcome): bool => $outcome === 0)) === 1
            && count(array_filter($outcomes, static fn(int $outcome): bool => $outcome === 10)) === 1
            && $committed === 1
            && $concurrentResult['status'] === 'RESOLVED',
        'two concurrent overlapping writes produce exactly one committed version'
    );
    Capsule::connection()->disconnect();
    foreach ([$concurrencyDb, $concurrencyDb . '-journal', $concurrencyDb . '-wal', $concurrencyDb . '-shm'] as $temporaryFile) {
        if (is_file($temporaryFile)) {
            unlink($temporaryFile);
        }
    }
}

printf("P3_L3_1_TIMEZONE_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passed, $failed);
exit($failed === 0 ? 0 : 1);
