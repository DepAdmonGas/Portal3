<?php

declare(strict_types=1);

use App\Services\WorkerWorkplaceAssignmentBaselineImportService;
use App\Services\WorkerWorkplaceAssignmentBaselinePreviewService;
use App\Services\WorkerWorkplaceAssignmentResolver;
use App\Services\WorkerWorkplaceAssignmentVersionService;
use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../scripts/migrate_worker_workplace_assignment_versions.php';

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$env = static function (string $key, string $default = ''): string {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return is_string($value) ? $value : $default;
};
$capsule = new Capsule();
$capsule->addConnection([
    'driver' => $env('DB_CONNECTION'),
    'host' => $env('DB_HOST'),
    'port' => (int) $env('DB_PORT', '0'),
    'database' => $env('DB_DATABASE'),
    'username' => $env('DB_USERNAME'),
    'password' => $env('DB_PASSWORD'),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$connection = Capsule::connection();
$identity = $connection->selectOne('SELECT DATABASE() AS db, VERSION() AS version, @@port AS port');
$host = strtolower(trim((string) $connection->getConfig('host')));
if ((string) $identity->db !== 'bd_checador_v2_baseline_test'
    || !in_array($host, ['127.0.0.1', 'localhost'], true)
    || (int) $identity->port !== 3306
    || !str_starts_with((string) $identity->version, '11.8.8-MariaDB')) {
    fwrite(STDERR, "REFUSED: expected disposable local MariaDB 11.8.8 baseline test database.\n");
    exit(2);
}

$fixtureTables = [
    'op_rh_personal', 'op_rh_localidades', 'op_rh_personal_localidad_version',
    'tb_modulos_config', 'tb_usuarios', 'tb_estaciones',
];
foreach ($fixtureTables as $table) {
    if (Capsule::schema()->hasTable($table)) {
        fwrite(STDERR, "REFUSED: disposable fixture database is not empty.\n");
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
$throws = static function (callable $callback, string $messagePart = ''): bool {
    try {
        $callback();
        return false;
    } catch (Throwable $exception) {
        return $messagePart === '' || str_contains($exception->getMessage(), $messagePart);
    }
};

try {
    Capsule::statement('CREATE TABLE op_rh_personal (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, id_estacion INT NULL, estado INT NOT NULL) ENGINE=InnoDB');
    Capsule::statement('CREATE TABLE op_rh_localidades (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, numlista INT NULL, localidad VARCHAR(100) NULL, recuperacion_vapores VARCHAR(100) NULL) ENGINE=InnoDB');
    Capsule::statement('CREATE TABLE tb_modulos_config (modulo_key VARCHAR(100) NOT NULL PRIMARY KEY, tipo_departamento VARCHAR(50) NOT NULL, activo TINYINT NOT NULL) ENGINE=InnoDB');
    Capsule::statement('CREATE TABLE tb_usuarios (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB');
    Capsule::statement('CREATE TABLE tb_estaciones (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, numlista INT NULL, estatus TINYINT NOT NULL DEFAULT 1) ENGINE=InnoDB');
    Capsule::table('tb_modulos_config')->insert(['modulo_key' => 'control-documentos-personal', 'tipo_departamento' => 'localidades', 'activo' => 1]);
    $interlomas = 9;
    $otherLocality = 2;
    Capsule::table('op_rh_localidades')->insert([
        ['id' => $interlomas, 'numlista' => 10, 'localidad' => 'Synthetic Autolavado', 'recuperacion_vapores' => ''],
        ['id' => $otherLocality, 'numlista' => 2, 'localidad' => 'Synthetic other', 'recuperacion_vapores' => ''],
    ]);
    Capsule::table('op_rh_localidades')->insert(['id' => 999, 'numlista' => 9, 'localidad' => 'Synthetic excluded', 'recuperacion_vapores' => '']);
    Capsule::table('tb_estaciones')->insert([
        ['id' => 11, 'numlista' => 10, 'estatus' => 1],
        ['id' => 2, 'numlista' => 2, 'estatus' => 1],
        ['id' => 19, 'numlista' => 9, 'estatus' => 1],
    ]);
    Capsule::table('tb_usuarios')->insert([['id' => 901], ['id' => 902]]);

    $workers = [];
    foreach ([[11, 1], [11, 1], [11, 1], [11, 0], [2, 1], [19, 1]] as [$physicalStationId, $status]) {
        $workers[] = (int) Capsule::table('op_rh_personal')->insertGetId(['id_estacion' => $physicalStationId, 'estado' => $status]);
    }
    WorkerWorkplaceAssignmentVersionsMigration::up();

    $allowedIds = [$interlomas];
    $scopeResolver = static fn(): array => [
        'actor_id' => 901,
        'workplace_ids' => $allowedIds,
        'can_edit' => true,
    ];
    $clock = static fn(): DateTimeImmutable => new DateTimeImmutable('2026-11-04T12:00:00Z');
    $preview = new WorkerWorkplaceAssignmentBaselinePreviewService($scopeResolver, $clock);
    $writer = new WorkerWorkplaceAssignmentVersionService($clock);
    $resolver = new WorkerWorkplaceAssignmentResolver($clock);
    $importer = new WorkerWorkplaceAssignmentBaselineImportService($preview, $writer, $resolver);

    $writer->appendVersion($workers[2], $interlomas, '2026-10-28T00:00:00Z', null, 901, 'existing version fixture');
    $snapshot = $preview->preview($interlomas);
    $expectedIds = [$workers[0], $workers[1]];
    sort($expectedIds);
    $assert(
        $snapshot['workplace_id'] === $interlomas
            && $snapshot['counts']['active_workers'] === 3
            && $snapshot['counts']['baseline_candidates'] === 2
            && $snapshot['candidate_worker_ids'] === $expectedIds,
        'scoped preview returns exactly the two active unversioned workers in the authorized locality'
    );
    $assert(
        $snapshot['locality_reference']['physical_station_id'] === 11
            && $snapshot['locality_reference']['numlista'] === 10
            && $snapshot['candidate_worker_ids'] === $expectedIds
            && array_reduce($snapshot['candidate_worker_ids'], static fn(bool $ok, int $id): bool => $ok
                && (int) Capsule::table('op_rh_personal')->where('id', $id)->value('id_estacion') === 11, true),
        'physical station 11 maps through numlista 10 to canonical Autolavado locality 9 without numeric-ID assumptions'
    );
    $assert(
        count($snapshot['locality_distribution']) === 1
            && $snapshot['locality_distribution'][0]['workplace_id'] === $interlomas
            && $snapshot['locality_distribution'][0]['baseline_candidate_count'] === 2,
        'scoped distribution excludes other localities and the excluded locality 999'
    );
    $assert(
        $snapshot['database_writes'] === false
            && preg_match('/\A[a-f0-9]{64}\z/D', $snapshot['candidate_snapshot_sha256']) === 1
            && preg_match('/\A[a-f0-9]{64}\z/D', $snapshot['source_snapshot_sha256']) === 1
            && preg_match('/\A[a-f0-9]{64}\z/D', $snapshot['locality_reference_sha256']) === 1,
        'preview is read-only and fingerprints both exact candidates and the locality/source reference'
    );
    $assert(
        $throws(static fn() => $preview->preview($otherLocality), 'not authorized')
            && $throws(static fn() => $preview->preview(999), 'not authorized')
            && $throws(static fn() => $preview->preview(0), 'explicit canonical locality'),
        'unauthorized, excluded, and omitted/global locality scopes fail closed'
    );

    $noEditResolver = static fn(): array => ['actor_id' => 901, 'workplace_ids' => [$interlomas], 'can_edit' => false];
    $readOnlyPreview = new WorkerWorkplaceAssignmentBaselinePreviewService($noEditResolver, $clock);
    $assert(
        $throws(static fn() => (new WorkerWorkplaceAssignmentBaselineImportService($readOnlyPreview, $writer, $resolver))->importFromPreview(
            $interlomas, 2, $snapshot['candidate_snapshot_sha256'], $snapshot['source_snapshot_sha256'],
            '2026-11-05T00:00:00Z', 901, 'QA reason', static function (): void {}
        ), 'RH editor'),
        'import requires edit permission even when locality read access is present'
    );
    $assert(
        $throws(static fn() => $preview->assertAuthorizedWorkplace($interlomas, 902, requireWrite: true), 'match the authenticated'),
        'recorded actor cannot be substituted for the authenticated user'
    );

    $validMetadata = $preview->prepareVersionMetadata('2026-11-05T00:00:00-06:00', 901, '  Prospective QA confirmation  ');
    $assert(
        $validMetadata['valid_from_utc'] === '2026-11-05 06:00:00.000000'
            && $validMetadata['reason'] === 'Prospective QA confirmation'
            && $validMetadata['valid_to_utc'] === null,
        'explicit timezone cutoff is normalized to UTC with mandatory actor and reason metadata'
    );
    $assert(
        $throws(static fn() => $preview->prepareVersionMetadata('2026-11-05', 901, 'reason'))
            && $throws(static fn() => $preview->prepareVersionMetadata('2026-11-03T00:00:00Z', 901, 'reason'), 'prospective')
            && $throws(static fn() => $preview->prepareVersionMetadata('2026-11-05T00:00:00Z', 901, '  '), 'non-empty'),
        'date-only, retrospective, and empty-reason metadata are rejected'
    );

    $staleLocality = $preview->preview($interlomas);
    Capsule::table('op_rh_localidades')->where('id', $interlomas)->update(['numlista' => 22]);
    $assert(
        $throws(static fn() => $importer->importFromPreview(
            $interlomas, $staleLocality['counts']['baseline_candidates'], $staleLocality['candidate_snapshot_sha256'], $staleLocality['source_snapshot_sha256'],
            '2026-11-06T00:00:00Z', 901, 'Prospective QA confirmation', static function (): void {}
        ), 'physical station mapping')
            && (int) Capsule::table('op_rh_personal_localidad_version')->count() === 1,
        'a locality-to-station mapping change is rejected before any version append'
    );
    Capsule::table('op_rh_localidades')->where('id', $interlomas)->update(['numlista' => 2]);

    $stale = $preview->preview($interlomas);
    Capsule::table('op_rh_personal')->where('id', $workers[1])->update(['estado' => 0]);
    $beforeStaleImport = (int) Capsule::table('op_rh_personal_localidad_version')->count();
    $assert(
        $throws(static fn() => $importer->importFromPreview(
            $interlomas, $stale['counts']['baseline_candidates'], $stale['candidate_snapshot_sha256'], $stale['source_snapshot_sha256'],
            '2026-11-06T00:00:00Z', 901, 'Prospective QA confirmation', static function (): void {}
        ), 'changed after preview')
            && (int) Capsule::table('op_rh_personal_localidad_version')->count() === $beforeStaleImport,
        'source fingerprint change is detected under transaction before any membership append'
    );
    Capsule::table('op_rh_personal')->where('id', $workers[1])->update(['estado' => 1]);

    $fresh = $preview->preview($interlomas);
    $wrongCandidateFingerprint = str_repeat('a', 64);
    $beforeBadFingerprint = (int) Capsule::table('op_rh_personal_localidad_version')->count();
    $assert(
        $throws(static fn() => $importer->importFromPreview(
            $interlomas, $fresh['counts']['baseline_candidates'], $wrongCandidateFingerprint, $fresh['source_snapshot_sha256'],
            '2026-11-07T00:00:00Z', 901, 'Prospective QA confirmation', static function (): void {}
        ), 'candidate set changed')
            && (int) Capsule::table('op_rh_personal_localidad_version')->count() === $beforeBadFingerprint,
        'candidate fingerprint mismatch prevents writes'
    );

    $cutoff = '2026-11-08T06:00:00Z';
    $reason = 'Prospective QA confirmation';
    $insertResult = $importer->importFromPreview(
        $interlomas,
        $fresh['counts']['baseline_candidates'],
        $fresh['candidate_snapshot_sha256'],
        $fresh['source_snapshot_sha256'],
        $cutoff,
        901,
        $reason,
        static function (array $result) use ($assert, $env): void {
            $lockName = 'p3:cv2:assignment:' . substr(hash('sha256', 'bd_checador_v2_baseline_test'), 0, 24);
            $probe = new mysqli($env('DB_HOST'), $env('DB_USERNAME'), $env('DB_PASSWORD'), $env('DB_DATABASE'), (int) $env('DB_PORT', '3306'));
            $probeResult = $probe->query("SELECT GET_LOCK('" . $probe->real_escape_string($lockName) . "', 0) AS acquired");
            $acquiredByConcurrentConnection = $probeResult ? (int) $probeResult->fetch_assoc()['acquired'] : -1;
            $probe->close();
            $assert($result['inserted'] === 2, 'commit callback receives the scoped result while the assignment lock is held');
            $assert($acquiredByConcurrentConnection === 0, 'a concurrent connection cannot acquire the baseline coordination lock before callback completion');
        }
    );
    $stored = Capsule::table('op_rh_personal_localidad_version')->where('workplace_id', $interlomas)->orderBy('worker_id')->get();
    $resolved = array_map(fn(object $row): array => $resolver->resolveAssignments((int) $row->worker_id, $cutoff), $stored->all());
    $newCandidateIds = $fresh['candidate_worker_ids'];
    $resolvedNewCandidates = array_values(array_filter($resolved, static fn(array $row): bool => in_array($row['worker_id'], $newCandidateIds, true)));
    $assert(
        $insertResult['inserted'] === 2
            && $insertResult['already_applied'] === false
            && $stored->count() === 3
            && $stored->where('valid_from_utc', '2026-11-08 06:00:00.000000')->count() === 2
            && $stored->where('recorded_by', 901)->where('reason', $reason)->count() === 2
            && count(array_filter($resolvedNewCandidates, static fn(array $row): bool => $row['status'] === 'RESOLVED_SINGLE'
                && (int) $row['assignments'][0]['workplace_id'] === $interlomas)) === 2,
        'import writes only scoped fixtures, preserves actor/cutoff/reason, and passes canonical resolver readback'
    );
    $afterImport = $preview->preview($interlomas);
    $versionCount = (int) Capsule::table('op_rh_personal_localidad_version')->count();
    $retry = $importer->importFromPreview(
        $interlomas,
        $fresh['counts']['baseline_candidates'],
        $fresh['candidate_snapshot_sha256'],
        $fresh['source_snapshot_sha256'],
        $cutoff,
        901,
        $reason,
        static function (): void {}
    );
    $assert(
        $afterImport['counts']['baseline_candidates'] === 0
            && $retry['inserted'] === 0
            && $retry['already_applied'] === true
            && (int) Capsule::table('op_rh_personal_localidad_version')->count() === $versionCount,
        'same scoped request is idempotent and creates no duplicate memberships'
    );
    $overlapRejected = false;
    try {
        $writer->appendVersion($workers[0], $interlomas, '2026-11-08T12:00:00Z', null, 901, 'overlap fixture');
    } catch (Throwable) {
        $overlapRejected = true;
    }
    $assert(
        $overlapRejected && (int) Capsule::table('op_rh_personal_localidad_version')->where('worker_id', $workers[0])->count() === 1,
        'existing canonical version writer rejects overlapping membership instead of duplicating it'
    );

    $noGlobalScope = $throws(static fn() => $importer->importFromPreview(
        0, 0, str_repeat('0', 64), str_repeat('0', 64), '2026-11-09T00:00:00Z', 901, $reason, static function (): void {}
    ), 'explicit canonical locality');
    $assert($noGlobalScope, 'import requires an explicit authorized locality and cannot silently become global');
} finally {
    if (Capsule::schema()->hasTable('op_rh_personal_localidad_version')) {
        Capsule::table('op_rh_personal_localidad_version')->delete();
        WorkerWorkplaceAssignmentVersionsMigration::down();
    }
    foreach (['tb_estaciones', 'tb_usuarios', 'tb_modulos_config', 'op_rh_localidades', 'op_rh_personal'] as $table) {
        if (Capsule::schema()->hasTable($table)) {
            Capsule::schema()->drop($table);
        }
    }
}

printf("CHECADOR_V2_SCOPED_BASELINE_MARIADB_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passed, $failed);
exit($failed === 0 ? 0 : 1);
