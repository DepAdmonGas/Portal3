<?php

declare(strict_types=1);

use App\Models\Operativo\RhPersonal;
use App\Services\ChecadorAttendanceRosterService;
use App\Services\RolComodinesService;
use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../scripts/migrate_checador_comodines_canonical_worker.php';

$getenvValue = static fn(string $key): string => (string) (getenv($key) ?: '');
$host = $getenvValue('CHECADOR_COMODINES_TEST_DB_HOST');
$port = (int) $getenvValue('CHECADOR_COMODINES_TEST_DB_PORT');
$database = $getenvValue('CHECADOR_COMODINES_TEST_DB_DATABASE');
$username = $getenvValue('CHECADOR_COMODINES_TEST_DB_USERNAME');
$password = $getenvValue('CHECADOR_COMODINES_TEST_DB_PASSWORD');
if ($host !== '127.0.0.1' || $port !== 33308 || $database !== 'bd_checador_v2_comodines_test'
    || $username === '' || $password === '') {
    fwrite(STDERR, "REFUSED: this regression connects only to the disposable local Comodines MariaDB database.\n");
    exit(2);
}

$capsule = new Capsule();
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => $host,
    'port' => $port,
    'database' => $database,
    'username' => $username,
    'password' => $password,
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$connection = Capsule::connection();
$identity = $connection->selectOne('SELECT DATABASE() AS database_name, VERSION() AS server_version, @@port AS server_port');
if ((string) $identity->database_name !== $database
    || (int) $identity->server_port !== 3306
    || !str_starts_with((string) $identity->server_version, '11.8.8-MariaDB')) {
    fwrite(STDERR, "REFUSED: server identity does not match disposable MariaDB 11.8.8.\n");
    exit(2);
}

foreach (['op_rh_comodines_dia', 'op_rh_rol_comodines', 'op_rh_personal', 'op_rh_localidades', 'tb_estaciones', 'tb_usuarios'] as $table) {
    if (Capsule::schema()->hasTable($table)) {
        fwrite(STDERR, "REFUSED: isolated database is not empty (table {$table} exists).\n");
        exit(2);
    }
}

$_ENV['APP_ENV'] = 'dev';
$_ENV['CHECADOR_COMODINES_CANONICAL_WRITES'] = '1';

$passed = 0;
$failed = 0;
$assert = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    $condition ? $passed++ : $failed++;
};
$fixture = static function (array $input, array $stationScope = [101, 202], array $workerScope = [101, 202]): array {
    return RolComodinesService::createCanonicalProgramming($input, $stationScope, $workerScope);
};
$baseInput = static fn(int $workerId = 1001, int $workplaceId = 101, string $start = '2027-03-01', string $end = '2027-03-31', array $days = ['Lunes']): array => [
    'worker_id' => $workerId,
    'workplace_id' => $workplaceId,
    'fecha_inicio' => $start,
    'fecha_fin' => $end,
    'days' => $days,
];

try {
    $connection->statement(<<<'SQL'
        CREATE TABLE op_rh_personal (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            id_estacion INT(11) NULL,
            nombre_completo VARCHAR(160) NOT NULL,
            no_colaborador INT(11) NULL,
            estado INT(11) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    $connection->statement(<<<'SQL'
        CREATE TABLE op_rh_localidades (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            numlista INT(11) NULL,
            localidad VARCHAR(100) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    $connection->statement(<<<'SQL'
        CREATE TABLE tb_estaciones (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            numlista INT(11) NULL,
            nombre VARCHAR(100) NOT NULL,
            estatus INT(11) NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    $connection->statement(<<<'SQL'
        CREATE TABLE tb_usuarios (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    $connection->statement(<<<'SQL'
        CREATE TABLE op_rh_rol_comodines (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            id_estacion INT(11) NOT NULL,
            status INT(11) NOT NULL DEFAULT 0,
            fecha_inicio DATE NULL,
            fecha_fin DATE NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    $connection->statement(<<<'SQL'
        CREATE TABLE op_rh_comodines_dia (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            id_reporte INT(11) NOT NULL,
            id_usuario INT(11) NOT NULL,
            id_estacion INT(11) NOT NULL,
            dia TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

    // Physical station IDs intentionally differ from canonical locality IDs.
    $connection->table('op_rh_localidades')->insert([
        ['id' => 101, 'numlista' => 71, 'localidad' => 'Synthetic locality one'],
        ['id' => 202, 'numlista' => 72, 'localidad' => 'Synthetic locality two'],
        ['id' => 303, 'numlista' => 73, 'localidad' => 'Synthetic locality without station'],
    ]);
    $connection->table('tb_estaciones')->insert([
        ['id' => 501, 'numlista' => 71, 'nombre' => 'Synthetic station one', 'estatus' => 0],
        ['id' => 502, 'numlista' => 72, 'nombre' => 'Synthetic station two', 'estatus' => 0],
        ['id' => 503, 'numlista' => 74, 'nombre' => 'Synthetic station without locality', 'estatus' => 0],
    ]);
    $connection->table('op_rh_rol_comodines')->insert([
        'id' => 1,
        'id_estacion' => 501,
        'status' => 0,
        'fecha_inicio' => '2027-03-01',
        'fecha_fin' => '2027-03-31',
    ]);
    // Numeric collision with the future canonical worker is deliberate. It must remain legacy only.
    $connection->table('op_rh_comodines_dia')->insert([
        'id' => 1,
        'id_reporte' => 1,
        'id_usuario' => 1001,
        'id_estacion' => 501,
        'dia' => 'Lunes',
    ]);

    $before = ChecadorComodinesCanonicalWorkerMigration::preflight();
    $assert(!empty($before['ready']) && !empty($before['applicable']), 'candidate migration preflight accepts the exact synthetic legacy schema');
    ChecadorComodinesCanonicalWorkerMigration::up();
    $after = ChecadorComodinesCanonicalWorkerMigration::preflight();
    $legacy = $connection->table('op_rh_comodines_dia')->where('id', 1)->first();
    $assert(!empty($after['ready']) && !empty($after['already_applied']), 'candidate migration passes post-apply schema verification');
    $assert((int) $legacy->id_usuario === 1001 && $legacy->id_personal === null, 'migration preserves legacy id_usuario and does not infer a canonical worker');

    $connection->table('op_rh_personal')->insert([
        ['id' => 1001, 'id_estacion' => 101, 'nombre_completo' => 'Canonical worker without account', 'no_colaborador' => 7001, 'estado' => 1],
        ['id' => 1002, 'id_estacion' => 101, 'nombre_completo' => 'Inactive canonical worker', 'no_colaborador' => 7002, 'estado' => 0],
        ['id' => 1003, 'id_estacion' => 202, 'nombre_completo' => 'Out of worker scope', 'no_colaborador' => 7003, 'estado' => 1],
    ]);

    $search = RolComodinesService::searchCanonicalWorkers('Canonical worker', [101]);
    $inactiveSearch = RolComodinesService::searchCanonicalWorkers('Inactive canonical', [101]);
    $assert(!empty($search['ok']) && array_column($search['workers'] ?? [], 'worker_id') === [1001],
        'worker selection searches the active canonical HR roster within the authorized locality');
    $assert(!empty($inactiveSearch['ok']) && ($inactiveSearch['workers'] ?? []) === [],
        'worker selector does not return inactive HR records');

    $created = $fixture($baseInput());
    $child = $created['ok'] ? $connection->table('op_rh_comodines_dia')->where('id_reporte', $created['header_id'])->first() : null;
    $header = $created['ok'] ? $connection->table('op_rh_rol_comodines')->where('id', $created['header_id'])->first() : null;
    $assert(!empty($created['ok']) && ($created['coverage_authorized'] ?? true) === false, 'creates a canonical-worker programming record without asserting coverage authorization');
    $assert($child !== null && (int) $child->id_personal === 1001 && $child->id_usuario === null && (int) $header->id_estacion === 501,
        'new assignment stores op_rh_personal.id, leaves legacy id_usuario NULL, and maps locality through numlista');
    $assert(!$connection->table('tb_usuarios')->where('id', 1001)->exists(), 'canonical worker programming does not require a Portal3 account');
    $assert($connection->table('op_rh_comodines_dia')->where('id', 1)->value('id_personal') === null,
        'legacy numeric collision remains unlinked after a same-numbered canonical worker exists');
    $readModel = RolComodinesService::getChecadorReadModel(101, [101, 202]);
    $canonicalReadRows = array_values(array_filter($readModel['roles'] ?? [], static fn(array $row): bool => ($row['identity_label'] ?? '') === 'Canonical worker without account'));
    $legacyReadRows = array_values(array_filter($readModel['roles'] ?? [], static fn(array $row): bool => str_contains((string) ($row['identity_label'] ?? ''), 'sin vínculo canónico')));
    $assert(count($canonicalReadRows) === 1 && empty($canonicalReadRows[0]['incomplete'])
        && ($canonicalReadRows[0]['coverage_authorized'] ?? true) === false,
        'administrative programming read model resolves the canonical worker without claiming formal coverage authorization');
    $assert(count($legacyReadRows) === 1 && str_contains($legacyReadRows[0]['identity_label'], 'sin vínculo canónico'),
        'station read model keeps the same-numbered legacy account record separate from the canonical worker');

    $assert(($fixture($baseInput(9999))['reason'] ?? '') === 'worker_not_active_or_missing', 'rejects a nonexistent canonical worker');
    $assert(($fixture($baseInput(1002))['reason'] ?? '') === 'worker_not_active_or_missing', 'rejects an inactive canonical worker');
    $assert(($fixture($baseInput(1003), [101, 202], [101])['reason'] ?? '') === 'worker_out_of_scope', 'rejects a canonical worker outside the server-derived locality scope');
    $assert(($fixture($baseInput(1001, 202), [101], [101, 202])['reason'] ?? '') === 'requested_station_out_of_scope', 'rejects a station outside the allowed station list');
    $assert(($fixture($baseInput(1001, 303), [303], [101, 202])['reason'] ?? '') === 'station_mapping_unavailable', 'rejects a locality without a physical station');
    $assert(($fixture($baseInput(1001, 404), [404], [101, 202])['reason'] ?? '') === 'station_mapping_unavailable', 'rejects a nonexistent locality even if a caller supplies its ID');
    $assert(($fixture($baseInput(1001, 101, '2027-02-30', '2027-03-31'))['reason'] ?? '') === 'invalid_period', 'rejects invalid civil dates');
    $assert(($fixture($baseInput(1001, 101, '2027-03-31', '2027-03-01'))['reason'] ?? '') === 'invalid_period', 'rejects reversed date periods');
    $assert(($fixture($baseInput(1001, 101, '2027-03-01', '2027-03-31', ['Not a day']))['reason'] ?? '') === 'invalid_days', 'rejects an unsupported weekday value');
    $assert(($fixture($baseInput())['reason'] ?? '') === 'duplicate_assignment', 'detects an overlapping same-worker, same-station, same-day duplicate');
    $assert(($fixture($baseInput(1001, 202), [101, 202], [101, 202])['reason'] ?? '') === 'cross_station_conflict', 'detects overlapping cross-station assignments for the same worker and weekday');

    $nonOverlapping = $fixture($baseInput(1001, 101, '2027-04-01', '2027-04-30', ['Lunes']));
    $assert(!empty($nonOverlapping['ok']), 'permits a same-day programming period that does not overlap an earlier period');
    $scheduled = RolComodinesService::getCanonicalScheduledWorkersForDate(101, '2027-03-01', [101, 202]);
    $assert(($scheduled['status'] ?? '') === 'ready' && array_column($scheduled['workers'] ?? [], 'worker_id') === [1001],
        'queries a structurally valid canonical program by date and authorized station');
    $assert(($scheduled['workers'][0]['coverage_authorized'] ?? true) === false
        && ($scheduled['workers'][0]['programming_state'] ?? '') === 'validated_structurally',
        'date query labels the schedule structurally validated without treating it as approved coverage');
    $otherStation = RolComodinesService::getCanonicalScheduledWorkersForDate(202, '2027-03-01', [202]);
    $assert(($otherStation['status'] ?? '') === 'ready' && ($otherStation['workers'] ?? []) === [], 'station-scoped query does not return a worker scheduled at another station');
    $deniedStation = RolComodinesService::getCanonicalScheduledWorkersForDate(202, '2027-03-01', [101]);
    $assert(($deniedStation['status'] ?? '') === 'unavailable' && ($deniedStation['workers'] ?? []) === [], 'station query fails closed outside the supplied authorized scope');

    $mixedHeader = $fixture($baseInput(1001, 101, '2027-04-01', '2027-04-30', ['Miércoles']));
    $connection->table('op_rh_comodines_dia')->insert([
        'id_reporte' => $mixedHeader['header_id'],
        'id_usuario' => null,
        'id_personal' => 1001,
        'id_estacion' => 502,
        'dia' => 'Miércoles',
    ]);
    $mixed = RolComodinesService::getCanonicalScheduledWorkersForDate(101, '2027-04-07', [101]);
    $assert(($mixed['status'] ?? '') === 'partial' && ($mixed['workers'] ?? []) === [], 'omits a mixed-station header when any child row falls outside the authorized station scope');

    $worker = RhPersonal::query()->find(1001);
    $roster = ['status' => 'ready', 'coverage' => 'current_active_roster', 'workers' => [['worker_id' => 1001]]];
    $scheduledRelation = [['worker_id' => 1001]];
    $assert($worker !== null
        && ChecadorAttendanceRosterService::observedWorkerRelationship(1001, ['status' => 'ready', 'coverage' => 'current_active_roster', 'workers' => []], $scheduledRelation) === 'scheduled_comodine',
        'daily attendance can distinguish a scheduled canonical worker outside the habitual roster');
    $assert(ChecadorAttendanceRosterService::observedWorkerRelationship(1001, $roster, $scheduledRelation) === 'habitual_and_scheduled',
        'daily attendance deduplicates one canonical worker who is both habitual and scheduled');
    $assert(ChecadorAttendanceRosterService::observedWorkerRelationship(1002, ['status' => 'historical_roster_unavailable', 'coverage' => 'observed_workers_only', 'workers' => []]) === 'observed',
        'a resolved observed worker without a schedule is not mislabeled as a comodín');

    $connection->table('op_rh_localidades')->insert([
        'id' => 404,
        'numlista' => 71,
        'localidad' => 'Synthetic duplicate numlista locality',
    ]);
    $ambiguousRead = RolComodinesService::getCanonicalScheduledWorkersForDate(101, '2027-03-01', [101, 202]);
    $ambiguousWrite = $fixture($baseInput(1001, 101, '2027-05-01', '2027-05-30', ['Lunes']));
    $assert(($ambiguousRead['status'] ?? '') === 'unavailable' && ($ambiguousRead['workers'] ?? []) === [], 'date query refuses an ambiguous locality-to-station numlista mapping');
    $assert(($ambiguousWrite['reason'] ?? '') === 'station_mapping_unavailable', 'creation refuses a non-unique numlista mapping');

    $_ENV['CHECADOR_COMODINES_CANONICAL_WRITES'] = '0';
    $disabledCreate = $fixture($baseInput(1001, 101, '2027-06-01', '2027-06-30', ['Lunes']));
    $assert(($disabledCreate['reason'] ?? '') === 'programming_not_enabled', 'create service fails closed when the development write flag is disabled');
} catch (Throwable $exception) {
    $failed++;
    fwrite(STDERR, 'FAIL: unexpected integration error: ' . $exception->getMessage() . PHP_EOL);
}

printf("CHECADOR_V2_COMODINES_CANONICAL_WORKER_MARIADB: %d PASS / %d FAIL\n", $passed, $failed);
exit($failed === 0 ? 0 : 1);
