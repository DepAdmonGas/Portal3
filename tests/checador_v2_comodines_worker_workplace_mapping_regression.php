<?php

declare(strict_types=1);

use App\Services\RolComodinesService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

require_once __DIR__ . '/../vendor/autoload.php';

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . PHP_EOL;
    $condition ? $passes++ : $fails++;
};

// All data below lives in an in-memory SQLite connection. This test never
// reaches bd_admongas and never invokes createCanonicalProgramming().
$capsule = new Capsule();
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$schema = Capsule::schema();
$schema->create('tb_estaciones', static function (Blueprint $table): void {
    $table->integer('id')->primary();
    $table->integer('numlista')->nullable();
    $table->string('nombre');
    $table->integer('estatus')->default(0);
});
$schema->create('op_rh_localidades', static function (Blueprint $table): void {
    $table->integer('id')->primary();
    $table->integer('numlista')->nullable();
    $table->string('localidad');
});
$schema->create('op_rh_personal', static function (Blueprint $table): void {
    $table->increments('id');
    $table->integer('id_estacion');
    $table->string('nombre_completo');
    $table->integer('no_colaborador')->nullable();
    $table->integer('estado')->default(1);
});
$schema->create('op_rh_comodines_dia', static function (Blueprint $table): void {
    $table->increments('id');
    $table->integer('id_personal')->nullable();
    $table->integer('id_usuario')->nullable();
});
$schema->create('op_rh_rol_comodines', static function (Blueprint $table): void {
    $table->increments('id');
});

$db = Capsule::connection();
$db->table('op_rh_localidades')->insert([
    ['id' => 901, 'numlista' => 11, 'localidad' => 'Base distinta'],
    ['id' => 302, 'numlista' => 22, 'localidad' => 'Localidad coincidente con otra estación'],
    ['id' => 904, 'numlista' => 33, 'localidad' => 'Localidad de colisión numérica'],
    ['id' => 50, 'numlista' => 50, 'localidad' => 'Base coincidente'],
    ['id' => 905, 'numlista' => 55, 'localidad' => 'Cobertura autorizada'],
    ['id' => 906, 'numlista' => 66, 'localidad' => 'Estación inactiva'],
    ['id' => 907, 'numlista' => 77, 'localidad' => 'Sin estación'],
    ['id' => 908, 'numlista' => 88, 'localidad' => 'Mapeo ambiguo'],
]);
$db->table('tb_estaciones')->insert([
    ['id' => 501, 'numlista' => 11, 'nombre' => 'Física 501', 'estatus' => 0],
    ['id' => 700, 'numlista' => 22, 'nombre' => 'Física 700', 'estatus' => 0],
    // Physical ID 302 intentionally equals an unrelated canonical locality ID.
    ['id' => 302, 'numlista' => 33, 'nombre' => 'Física 302', 'estatus' => 0],
    ['id' => 50, 'numlista' => 50, 'nombre' => 'Física 50', 'estatus' => 0],
    ['id' => 800, 'numlista' => 55, 'nombre' => 'Cobertura física 800', 'estatus' => 0],
    ['id' => 600, 'numlista' => 66, 'nombre' => 'Física inactiva', 'estatus' => 1],
    ['id' => 801, 'numlista' => 88, 'nombre' => 'Mapeo ambiguo A', 'estatus' => 0],
    ['id' => 802, 'numlista' => 88, 'nombre' => 'Mapeo ambiguo B', 'estatus' => 0],
]);
$db->table('op_rh_personal')->insert([
    ['id' => 1, 'id_estacion' => 901, 'nombre_completo' => 'Bruno Base Válida', 'no_colaborador' => 1001, 'estado' => 1],
    ['id' => 2, 'id_estacion' => 904, 'nombre_completo' => 'Bruno Colisión Numérica', 'no_colaborador' => 1002, 'estado' => 1],
    ['id' => 3, 'id_estacion' => 302, 'nombre_completo' => 'Beto Cobertura Cruzada', 'no_colaborador' => 1003, 'estado' => 1],
    ['id' => 4, 'id_estacion' => 50, 'nombre_completo' => 'Sofía IDs Coincidentes', 'no_colaborador' => 1004, 'estado' => 1],
    ['id' => 5, 'id_estacion' => 907, 'nombre_completo' => 'Eva Estación Ausente', 'no_colaborador' => 1005, 'estado' => 1],
    ['id' => 6, 'id_estacion' => 906, 'nombre_completo' => 'Inés Estación Inactiva', 'no_colaborador' => 1006, 'estado' => 1],
    ['id' => 7, 'id_estacion' => 908, 'nombre_completo' => 'Óscar Mapeo Ambiguo', 'no_colaborador' => 1007, 'estado' => 1],
]);

$_ENV['APP_ENV'] = 'dev';
$_ENV['CHECADOR_COMODINES_CANONICAL_WRITES'] = '1';
putenv('APP_ENV=dev');
putenv('CHECADOR_COMODINES_CANONICAL_WRITES=1');

$differentIds = RolComodinesService::searchCanonicalWorkers('Bruno', [901]);
$assert(($differentIds['ok'] ?? false) === true
    && array_column($differentIds['workers'] ?? [], 'worker_id') === [1],
    'worker search filters canonical locality 901 even though physical station ID is 501');

$numericCollision = RolComodinesService::searchCanonicalWorkers('Bruno', [302]);
$assert(($numericCollision['ok'] ?? false) === true && ($numericCollision['workers'] ?? []) === [],
    'a physical ID collision does not change worker canonical locality 904');

$crossBaseSearch = RolComodinesService::searchCanonicalWorkers('Beto', [302]);
$assert(($crossBaseSearch['ok'] ?? false) === true
    && array_column($crossBaseSearch['workers'] ?? [], 'worker_id') === [3],
    'search includes the worker whose canonical base locality is authorized');

$coincidentIds = RolComodinesService::searchCanonicalWorkers('Sofía', [50]);
$assert(($coincidentIds['ok'] ?? false) === true
    && array_column($coincidentIds['workers'] ?? [], 'worker_id') === [4],
    'worker search uses a canonical ID directly when physical and canonical IDs happen to match');

$missingMapping = RolComodinesService::searchCanonicalWorkers('Eva', [907]);
$inactiveMapping = RolComodinesService::searchCanonicalWorkers('Inés', [906]);
$ambiguousMapping = RolComodinesService::searchCanonicalWorkers('Óscar', [908]);
$assert(($missingMapping['ok'] ?? false) === true && array_column($missingMapping['workers'] ?? [], 'worker_id') === [5]
    && ($inactiveMapping['ok'] ?? false) === true && array_column($inactiveMapping['workers'] ?? [], 'worker_id') === [6]
    && ($ambiguousMapping['ok'] ?? false) === true && array_column($ambiguousMapping['workers'] ?? [], 'worker_id') === [7],
    'worker search uses canonical locality identity independently of physical-station mapping state');

$baseCheck = new ReflectionMethod(RolComodinesService::class, 'workerBaseWorkplaceIsAuthorized');
$baseCheck->setAccessible(true);
$baseIsAuthorized = static fn(int $workerLocalityId, array $localities): bool =>
    (bool) $baseCheck->invoke(null, $workerLocalityId, $localities);

$assert($baseIsAuthorized(901, [901])
    && !$baseIsAuthorized(501, [302])
    && !$baseIsAuthorized(904, [302]),
    'server-side worker base validation compares canonical locality IDs and rejects physical-ID collisions');
$assert($baseIsAuthorized(907, [907]) && !$baseIsAuthorized(907, [901]),
    'department locality affiliation remains in its own explicit canonical scope');

$coverage = new ReflectionMethod(RolComodinesService::class, 'resolvePhysicalStationForWorkplace');
$coverage->setAccessible(true);
$authorizedCoverage = $coverage->invoke(null, 905, [905]);
$deniedCoverage = $coverage->invoke(null, 905, [901]);
$missingCoverage = $coverage->invoke(null, 907, [907]);
$inactiveCoverage = $coverage->invoke(null, 906, [906]);
$ambiguousCoverage = $coverage->invoke(null, 908, [908]);
$assert($baseIsAuthorized(901, [901])
    && ($authorizedCoverage['ok'] ?? false) === true
    && (int) $authorizedCoverage['station_id'] === 800
    && ($deniedCoverage['ok'] ?? true) === false
    && ($missingCoverage['ok'] ?? true) === false
    && ($inactiveCoverage['ok'] ?? true) === false
    && ($ambiguousCoverage['ok'] ?? true) === false,
    'authorized base locality and separately authorized cross-station coverage remain independent');

$permanentStation = (int) $db->table('op_rh_personal')->where('id', 1)->value('id_estacion');
$assert($permanentStation === 901 && $db->table('op_rh_rol_comodines')->count() === 0,
    'eligibility checks preserve permanent worker affiliation and perform no comodines persistence');

printf("CHECADOR_V2_COMODINES_WORKER_WORKPLACE_MAPPING: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);
