<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Session;
use App\Controllers\ControlDocumentosPersonalController;
use App\Models\Operativo\RhPersonal;
use App\Models\Operativo\RhPersonalLocalidadVersion;
use App\Services\ControlDocumentosPersonalService;
use App\Services\ChecadorWeeklyScheduleService;
use App\Services\FormatosService;
use App\Services\RhDirectCreateAuthorizationException;
use App\Services\RhDirectCreateIdempotencyConflict;
use App\Services\WorkerWorkplaceAssignmentHistoryWriteGate;
use App\Services\WorkerWorkplaceDirectCreateMembershipWriteGate;
use App\Services\WorkerWorkplaceAssignmentResolver;
use App\Services\WorkerWorkplaceAssignmentVersionService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../scripts/migrate_worker_workplace_assignment_versions.php';
require_once __DIR__ . '/../scripts/migrate_rh_direct_create_idempotency.php';

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$env = static function (string $key, string $default = ''): string {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return is_string($value) ? $value : $default;
};
$dbConfig = [
    'driver' => $env('DB_CONNECTION'),
    'host' => $env('DB_HOST'),
    'port' => (int) $env('DB_PORT', '0'),
    'database' => $env('DB_DATABASE'),
    'username' => $env('DB_USERNAME'),
    'password' => $env('DB_PASSWORD'),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
];
$capsule = new Capsule();
$capsule->addConnection($dbConfig);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$connection = Capsule::connection();
$identity = $connection->selectOne('SELECT DATABASE() AS db, VERSION() AS version, @@port AS port');
$host = strtolower(trim((string) $connection->getConfig('host')));
if ((string) $identity->db !== 'bd_checador_v2_hr_assignment_test'
    || !in_array($host, ['127.0.0.1', 'localhost'], true)
    || (int) $dbConfig['port'] !== 33308
    || (int) $identity->port !== 3306
    || !str_starts_with((string) $identity->version, '11.8.8-MariaDB')) {
    fwrite(STDERR, "REFUSED: expected the disposable local HR assignment MariaDB 11.8.8 fixture.\n");
    exit(2);
}

$raceWorker = static function (int $workerId, int $workplaceId, string $readyPath, string $startPath) use ($env): never {
    $_ENV[WorkerWorkplaceAssignmentHistoryWriteGate::ENVIRONMENT_KEY] = '1';
    $_SESSION = [];
    Session::set('usuario', ['id' => 901, 'id_estacion' => 42, 'multiestacion' => true, 'nombre' => 'Synthetic HR actor']);
    Session::set('permisos_do', ['recursos-humanos' => ['crear' => 1, 'editar' => 1]]);
    Session::set('module_context', []);
    Auth::forget();
    file_put_contents($readyPath, 'ready', LOCK_EX);
    $deadline = microtime(true) + 15;
    while (!file_exists($startPath) && microtime(true) < $deadline) {
        usleep(10000);
    }
    if (!file_exists($startPath)) {
        fwrite(STDERR, "RACE_TIMEOUT\n");
        exit(3);
    }

    $ok = ControlDocumentosPersonalService::editPersonal($workerId, [
        'id_estacion' => $workplaceId,
        'fecha_ingreso' => '2001-01-01',
        'no_colaborador' => 70001,
        'nombre_completo' => 'Synthetic concurrent worker',
        'puesto' => 13,
        'sd' => 1,
    ], []);
    echo json_encode(['updated' => $ok, 'worker_id' => $workerId], JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit($ok ? 0 : 4);
};

$createRaceWorker = static function (int $workplaceId, string $key, string $readyPath, string $startPath): never {
    $_ENV[WorkerWorkplaceDirectCreateMembershipWriteGate::ENVIRONMENT_KEY] = '1';
    unset($_ENV[WorkerWorkplaceAssignmentHistoryWriteGate::ENVIRONMENT_KEY], $_SERVER[WorkerWorkplaceAssignmentHistoryWriteGate::ENVIRONMENT_KEY]);
    $_SESSION = [];
    Session::set('usuario', ['id' => 901, 'id_estacion' => 42, 'multiestacion' => true, 'nombre' => 'Synthetic HR actor']);
    Session::set('permisos_do', ['recursos-humanos' => ['crear' => 1, 'editar' => 1]]);
    Session::set('module_context', []);
    Auth::forget();
    file_put_contents($readyPath, 'ready', LOCK_EX);
    $deadline = microtime(true) + 15;
    while (!file_exists($startPath) && microtime(true) < $deadline) usleep(10000);
    if (!file_exists($startPath)) {
        fwrite(STDERR, "CREATE_RACE_TIMEOUT\n");
        exit(3);
    }
    $result = ControlDocumentosPersonalService::addPersonal([
        'id_estacion' => $workplaceId,
        'fecha_ingreso' => '2026-11-04',
        'no_colaborador' => 70006,
        'nombre_completo' => 'Synthetic idempotent concurrent create',
        'puesto' => 13,
        'sd' => 1,
    ], [], $key);
    echo json_encode($result, JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit($result !== null ? 0 : 4);
};

if (($argv[1] ?? '') === '--race-worker') {
    $raceWorker((int) ($argv[2] ?? 0), (int) ($argv[3] ?? 0), (string) ($argv[4] ?? ''), (string) ($argv[5] ?? ''));
}
if (($argv[1] ?? '') === '--create-race-worker') {
    $createRaceWorker((int) ($argv[2] ?? 0), (string) ($argv[3] ?? ''), (string) ($argv[4] ?? ''), (string) ($argv[5] ?? ''));
}

$fixtureTables = [
    'op_rh_personal',
    'op_rh_localidades',
    'tb_usuarios',
    'tb_puestos',
    'tb_estaciones',
    'tb_modulos_config',
    'tb_multiestacion_usuario',
    'tb_multiestacion_puesto',
    'op_rh_puestos',
    'op_rh_personal_acceso',
    'op_rh_formatos',
    'op_rh_formatos_token',
    'op_rh_formatos_firma',
    'op_recibo_formatos_comentarios',
    'op_rh_formatos_alta',
    'op_rh_formatos_restructuracion',
    'op_rh_personal_localidad_version',
];
foreach ($fixtureTables as $table) {
    if (Capsule::schema()->hasTable($table)) {
        fwrite(STDERR, "REFUSED: disposable database is not empty (fixture table {$table} exists).\n");
        exit(2);
    }
}

$passed = 0;
$failed = 0;
$blocked = 0;
// Keep CLI regression output buffered so controller-level HTTP assertions can
// exercise header/status behavior after earlier assertions have printed.
ob_start();
$assert = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    if ($condition) {
        $passed++;
        echo "PASS: {$label}\n";
    } else {
        $failed++;
        echo "FAIL: {$label}\n";
    }
};
$reportBlocked = static function (string $label) use (&$blocked): void {
    $blocked++;
    echo "BLOCKED: {$label}\n";
};
$throws = static function (callable $callback, string $messagePart = ''): bool {
    try {
        $callback();
        return false;
    } catch (Throwable $exception) {
        return $messagePart === '' || str_contains($exception->getMessage(), $messagePart);
    }
};
$setGate = static function (bool $enabled): void {
    $key = WorkerWorkplaceAssignmentHistoryWriteGate::ENVIRONMENT_KEY;
    if ($enabled) {
        $_ENV[$key] = '1';
        return;
    }
    unset($_ENV[$key], $_SERVER[$key]);
    putenv($key);
};
$setDirectCreateGate = static function (bool $enabled): void {
    $key = WorkerWorkplaceDirectCreateMembershipWriteGate::ENVIRONMENT_KEY;
    if ($enabled) {
        $_ENV[$key] = '1';
        return;
    }
    unset($_ENV[$key], $_SERVER[$key]);
    putenv($key);
};
$setActor = static function (int $actorId = 901, bool $canCreate = true, bool $canEdit = true): void {
    $_SESSION = [];
    Session::set('usuario', [
        'id' => $actorId,
        'id_estacion' => 42,
        'multiestacion' => true,
        'nombre' => 'Synthetic HR actor',
    ]);
    Session::set('permisos_do', ['recursos-humanos' => [
        'leer' => 1,
        'crear' => $canCreate ? 1 : 0,
        'editar' => $canEdit ? 1 : 0,
        'eliminar' => 0,
        'descargar' => 0,
    ]]);
    Session::set('module_context', []);
    Auth::forget();
};
$insertPersonnel = static function (int $workplaceId, int $workerNo, string $name): int {
    return (int) Capsule::table('op_rh_personal')->insertGetId([
        'id_estacion' => $workplaceId,
        'fecha_ingreso' => '2001-01-01',
        'no_colaborador' => $workerNo,
        'nombre_completo' => $name,
        'puesto' => 13,
        'sd' => 1,
        'estado' => 1,
    ]);
};
$addPersonal = static fn(array $input, string $key): ?array => ControlDocumentosPersonalService::addPersonal($input, [], $key);
$invokeDirectCreateController = static function (array $input, string $key): array {
    $_POST = $input;
    $_FILES = [];
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = $key;
    http_response_code(200);
    ob_start();
    (new ControlDocumentosPersonalController())->add();
    $body = (string)ob_get_clean();
    return ['status' => http_response_code(), 'body' => json_decode($body, true)];
};
$toRfc3339 = static function (string $databaseTimestamp): string {
    return str_replace(' ', 'T', $databaseTimestamp) . 'Z';
};

try {
    Capsule::schema()->create('tb_puestos', static function (Blueprint $table): void {
        $table->integer('id')->primary();
        $table->string('tipo_puesto')->nullable();
        $table->integer('estatus')->default(0);
    });
    Capsule::schema()->create('tb_usuarios', static function (Blueprint $table): void {
        $table->integer('id')->primary();
        $table->integer('id_puesto')->nullable();
        $table->integer('id_gas')->default(900);
        $table->integer('estatus')->default(0);
        $table->string('nombre')->nullable();
        $table->string('email')->nullable();
    });
    Capsule::schema()->create('tb_estaciones', static function (Blueprint $table): void {
        $table->integer('id')->primary();
        $table->integer('numlista');
        $table->text('nombre');
        $table->integer('estatus')->default(1);
    });
    Capsule::schema()->create('op_rh_localidades', static function (Blueprint $table): void {
        $table->integer('id')->autoIncrement()->primary();
        $table->integer('numlista');
        $table->string('localidad');
        $table->string('recuperacion_vapores')->default('NO');
    });
    Capsule::schema()->create('op_rh_personal', static function (Blueprint $table): void {
        $table->integer('id')->autoIncrement()->primary();
        $table->integer('id_estacion')->nullable();
        $table->date('fecha_ingreso')->nullable();
        $table->integer('no_colaborador')->default(0);
        $table->string('nombre_completo')->default('');
        $table->integer('puesto')->default(0);
        $table->double('sd')->default(0);
        $table->integer('estado')->default(1);
        foreach ([
            'requisicion', 'curriculum', 'ine', 'acta_nacimiento', 'c_domicilio', 'curp', 'rfc',
            'nss', 'c_estudios', 'c_recomendacion', 'a_infonavit', 'c_antecedentes', 'contrato', 'documentos',
        ] as $column) {
            $table->text($column)->nullable();
        }
    });
    Capsule::schema()->create('tb_modulos_config', static function (Blueprint $table): void {
        $table->increments('id');
        $table->string('modulo_key')->unique();
        $table->string('tipo');
        $table->json('estaciones_soportadas')->nullable();
        $table->json('departamentos_soportados')->nullable();
        $table->string('tipo_departamento')->nullable();
        $table->boolean('allow_all')->default(false);
        $table->string('placeholder')->nullable();
        $table->boolean('activo')->default(true);
    });
    foreach (['tb_multiestacion_usuario', 'tb_multiestacion_puesto'] as $tableName) {
        Capsule::schema()->create($tableName, static function (Blueprint $table) use ($tableName): void {
            $table->increments('id');
            $table->integer($tableName === 'tb_multiestacion_usuario' ? 'id_usuario' : 'id_puesto');
            $table->json('estaciones')->nullable();
            $table->json('departamentos_puestos')->nullable();
            $table->json('departamentos_localidades')->nullable();
            $table->boolean('activo')->default(true);
        });
    }
    Capsule::schema()->create('op_rh_personal_acceso', static function (Blueprint $table): void {
        $table->increments('id');
        $table->integer('id_personal');
        $table->text('huella')->nullable();
        $table->integer('pin')->default(0);
    });
    Capsule::schema()->create('op_rh_puestos', static function (Blueprint $table): void {
        $table->integer('id')->primary();
        $table->string('puesto')->nullable();
        $table->integer('status')->default(1);
    });
    Capsule::schema()->create('op_rh_formatos', static function (Blueprint $table): void {
        $table->increments('id');
        $table->integer('id_localidad');
        $table->integer('id_usuario');
        $table->integer('formato');
        $table->dateTime('fecha')->nullable();
        $table->integer('status')->default(0);
    });
    Capsule::schema()->create('op_rh_formatos_token', static function (Blueprint $table): void {
        $table->increments('id');
        $table->integer('id_formato');
        $table->integer('id_usuario');
        $table->dateTime('fecha_creacion');
        $table->integer('token');
    });
    Capsule::schema()->create('op_rh_formatos_firma', static function (Blueprint $table): void {
        $table->increments('id');
        $table->integer('id_formato');
        $table->integer('id_usuario');
        $table->string('tipo_firma');
        $table->text('firma');
        $table->dateTime('fecha');
    });
    Capsule::schema()->create('op_recibo_formatos_comentarios', static function (Blueprint $table): void {
        $table->increments('id');
        $table->integer('id_formato');
        $table->integer('id_usuario');
        $table->text('comentario')->nullable();
        $table->dateTime('fecha_hora')->nullable();
    });
    Capsule::schema()->create('op_rh_formatos_alta', static function (Blueprint $table): void {
        $table->increments('id');
        $table->integer('id_formulario');
        $table->integer('id_estacion');
        $table->date('fecha_ingreso')->nullable();
        $table->string('nombre');
        $table->integer('puesto');
        $table->double('sd')->default(0);
        foreach ([
            'curriculum', 'ine', 'acta_nacimiento', 'nss', 'c_domicilio', 'c_estudios',
            'c_recomendacion', 'curp', 'rfc', 'a_infonavit', 'c_antecedentes',
        ] as $column) {
            $table->text($column)->nullable();
        }
    });
    Capsule::schema()->create('op_rh_formatos_restructuracion', static function (Blueprint $table): void {
        $table->increments('id');
        $table->integer('id_formulario');
        $table->integer('id_personal');
        $table->integer('id_estacion');
        $table->integer('id_estacion_cambio');
        $table->date('fecha')->nullable();
    });

    Capsule::table('tb_puestos')->insert(['id' => 13, 'tipo_puesto' => 'Synthetic HR', 'estatus' => 1]);
    Capsule::table('op_rh_puestos')->insert(['id' => 13, 'puesto' => 'Synthetic HR worker', 'status' => 1]);
    Capsule::table('tb_usuarios')->insert([
        ['id' => 2, 'id_puesto' => 13, 'id_gas' => 900, 'estatus' => 0, 'nombre' => 'Synthetic approver'],
        ['id' => 901, 'id_puesto' => 13, 'id_gas' => 900, 'estatus' => 0, 'nombre' => 'Synthetic HR actor'],
    ]);
    Capsule::table('tb_estaciones')->insert([
        ['id' => 42, 'numlista' => 42, 'nombre' => 'Synthetic station A'],
        ['id' => 43, 'numlista' => 43, 'nombre' => 'Synthetic station B'],
        ['id' => 44, 'numlista' => 44, 'nombre' => 'Synthetic station C'],
    ]);
    $workplaces = [];
    foreach ([42, 43, 44] as $index => $numlista) {
        $workplaces[] = (int) Capsule::table('op_rh_localidades')->insertGetId([
            'numlista' => $numlista,
            'localidad' => 'Synthetic locality ' . ($index + 1),
        ]);
    }
    Capsule::table('op_rh_localidades')->insert([
        'id' => 999,
        'numlista' => 999,
        'localidad' => 'Synthetic locality 999 without a station row',
        'recuperacion_vapores' => 'NO',
    ]);
    Capsule::table('tb_modulos_config')->insert([
        'modulo_key' => 'control-documentos-personal',
        'tipo' => 'stations_and_departments',
        'estaciones_soportadas' => json_encode([42, 43, 44], JSON_THROW_ON_ERROR),
        'departamentos_soportados' => json_encode([999], JSON_THROW_ON_ERROR),
        'tipo_departamento' => 'localidades',
        'allow_all' => 0,
        'placeholder' => '',
        'activo' => 1,
    ]);
    Capsule::table('tb_multiestacion_usuario')->insert([
        'id_usuario' => 901,
        'estaciones' => json_encode([42, 43, 44], JSON_THROW_ON_ERROR),
        'departamentos_puestos' => json_encode([], JSON_THROW_ON_ERROR),
        'departamentos_localidades' => json_encode([999], JSON_THROW_ON_ERROR),
        'activo' => 1,
    ]);

    WorkerWorkplaceAssignmentVersionsMigration::up();
    RhDirectCreateIdempotencyMigration::up();
    $setActor();
    $gateKey = WorkerWorkplaceAssignmentHistoryWriteGate::ENVIRONMENT_KEY;
    $savedGateValue = $_ENV[$gateKey] ?? null;
    $savedServerGateValue = $_SERVER[$gateKey] ?? null;
    $directGateKey = WorkerWorkplaceDirectCreateMembershipWriteGate::ENVIRONMENT_KEY;
    $savedDirectGateValue = $_ENV[$directGateKey] ?? null;
    $savedServerDirectGateValue = $_SERVER[$directGateKey] ?? null;
    $setGate(false);
    $setDirectCreateGate(false);

    $assert(
        !WorkerWorkplaceAssignmentHistoryWriteGate::enabled()
            && !WorkerWorkplaceDirectCreateMembershipWriteGate::enabled(),
        'both history-write gates are disabled by default'
    );
    $disabledBefore = (int) Capsule::table('op_rh_personal')->count();
    $legacyAddedResult = $addPersonal([
        'id_estacion' => $workplaces[0],
        'fecha_ingreso' => '1998-02-03',
        'no_colaborador' => 70000,
        'nombre_completo' => 'Synthetic legacy add',
        'puesto' => 13,
        'sd' => 1,
    ], 'rh-create-test-key-gate-off-0001');
    $legacyAddedWorkerId = $legacyAddedResult['worker_id'] ?? null;
    $assert(
        $legacyAddedWorkerId !== null
            && (int) Capsule::table('op_rh_personal')->count() === $disabledBefore + 1
            && Capsule::table('op_rh_personal_localidad_version')->where('worker_id', $legacyAddedWorkerId)->count() === 0,
        'activation gate off preserves the HR add and does not create a history row'
    );

    $setActor(901, false, false);
    $unauthorizedBefore = (int) Capsule::table('op_rh_personal')->count();
    $unauthorizedAdd = $throws(static fn() => $addPersonal([
        'id_estacion' => $workplaces[0],
        'fecha_ingreso' => '2001-01-01',
        'no_colaborador' => 70002,
        'nombre_completo' => 'Synthetic denied add',
        'puesto' => 13,
        'sd' => 1,
    ], 'rh-create-test-key-denied-0001'), 'cannot create RH personnel');
    $assert(
        $unauthorizedAdd
            && (int) Capsule::table('op_rh_personal')->count() === $unauthorizedBefore,
        'unauthorized HR create is denied by the server-side permission check'
    );
    $setActor();

    $setGate(true);
    $sharedOnlyResult = $addPersonal([
        'id_estacion' => $workplaces[0],
        'fecha_ingreso' => '1998-02-03',
        'no_colaborador' => 70005,
        'nombre_completo' => 'Synthetic shared-gate isolation',
        'puesto' => 13,
        'sd' => 1,
    ], 'rh-create-test-key-shared-only-0001');
    $sharedOnlyWorker = $sharedOnlyResult['worker_id'] ?? null;
    $assert(
        WorkerWorkplaceAssignmentHistoryWriteGate::enabled()
            && !WorkerWorkplaceDirectCreateMembershipWriteGate::enabled()
            && $sharedOnlyWorker !== null
            && Capsule::table('op_rh_personal_localidad_version')->where('worker_id', $sharedOnlyWorker)->count() === 0,
        'shared gate does not enable direct-create membership writes'
    );

    $setGate(false);
    $setDirectCreateGate(true);
    $assert(
        WorkerWorkplaceDirectCreateMembershipWriteGate::enabled()
            && !WorkerWorkplaceAssignmentHistoryWriteGate::enabled(),
        'direct-create gate is independent from the shared history and Formatos gate'
    );
    $beforeAlta = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $primaryCreateInput = [
        'id_estacion' => $workplaces[0],
        'fecha_ingreso' => '1998-02-03',
        'no_colaborador' => 70003,
        'nombre_completo' => 'Synthetic versioned add',
        'puesto' => 13,
        'sd' => 1,
    ];
    $newWorkerResponse = $invokeDirectCreateController($primaryCreateInput, 'rh-create-test-key-direct-0001');
    $newWorkerResult = $newWorkerResponse['body'];
    $newWorkerId = $newWorkerResult['worker_id'] ?? null;
    $afterAlta = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $initialVersion = Capsule::table('op_rh_personal_localidad_version')
        ->where('worker_id', $newWorkerId)->first();
    $initialValidFrom = $initialVersion
        ? new DateTimeImmutable(str_replace(' ', 'T', (string) $initialVersion->valid_from_utc) . 'Z')
        : null;
    $assert(
        $newWorkerId !== null
            && $newWorkerResponse['status'] === 201
            && ($newWorkerResult['idempotent_replay'] ?? true) === false
            && $initialVersion !== null
            && (int) $initialVersion->workplace_id === $workplaces[0]
            && $initialValidFrom !== null
            && $initialValidFrom >= $beforeAlta
            && $initialValidFrom <= $afterAlta
            && (int) $initialVersion->recorded_by === 901
            && (string) $initialVersion->reason === 'Alta directa en Control de Documentos del Personal'
            && (string) $initialVersion->valid_from_utc !== '1998-02-03 00:00:00.000000',
        'direct HR add atomically creates a prospective UTC version with actor/reason, not fecha_ingreso'
    );
    $assert(
        (int) Capsule::table('op_rh_personal')->where('id', $newWorkerId)->value('id_estacion') === $workplaces[0],
        'new history uses the canonical locality ID directly as op_rh_personal.id_estacion'
    );

    $nextMonday = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('next monday')->format('Y-m-d');
    $nextWeekDates = ChecadorWeeklyScheduleService::dates($nextMonday);
    $verifiedFutureDates = [];
    $membershipResolver = new WorkerWorkplaceAssignmentResolver();
    foreach ($nextWeekDates as $day) {
        $resolved = $membershipResolver->resolveAssignments($newWorkerId, $day['date'] . 'T12:00:00.000000Z');
        if ($resolved['status'] === 'RESOLVED_SINGLE'
            && (int)$resolved['assignments'][0]['workplace_id'] === $workplaces[0]) {
            $verifiedFutureDates[] = $day['date'];
        }
    }
    $m03Eligible = ChecadorWeeklyScheduleService::workersWithVerifiedMembership([[
        'worker_id' => $newWorkerId,
        'verified_dates' => $verifiedFutureDates,
        'versioned_verified_dates' => $verifiedFutureDates,
    ]], array_column($nextWeekDates, 'date'));
    $assert(
        count($verifiedFutureDates) === 7
            && count($m03Eligible) === 1
            && (int)$m03Eligible[0]['worker_id'] === $newWorkerId,
        'persisted prospective membership passes the M03 weekly eligibility projection for the following week'
    );

    $beforeExactRetryWorkers = (int)Capsule::table('op_rh_personal')->count();
    $beforeExactRetryVersions = (int)Capsule::table('op_rh_personal_localidad_version')->where('worker_id', $newWorkerId)->count();
    $exactRetryResponse = $invokeDirectCreateController($primaryCreateInput, 'rh-create-test-key-direct-0001');
    $exactRetry = $exactRetryResponse['body'];
    $assert(
        $exactRetryResponse['status'] === 200
            && $exactRetry !== null
            && (int)$exactRetry['worker_id'] === $newWorkerId
            && $exactRetry['idempotent_replay'] === true
            && (int)Capsule::table('op_rh_personal')->count() === $beforeExactRetryWorkers
            && (int)Capsule::table('op_rh_personal_localidad_version')->where('worker_id', $newWorkerId)->count() === $beforeExactRetryVersions,
        'identical HTTP-operation retry returns the original worker without another worker or membership write'
    );
    $conflictingRetry = $invokeDirectCreateController([
        ...$primaryCreateInput, 'nombre_completo' => 'Synthetic changed payload',
    ], 'rh-create-test-key-direct-0001');
    $assert(
        $conflictingRetry['status'] === 409
            && ($conflictingRetry['body']['code'] ?? '') === 'idempotency_conflict'
            && (int)Capsule::table('op_rh_personal')->count() === $beforeExactRetryWorkers
            && (int)Capsule::table('op_rh_personal_localidad_version')->where('worker_id', $newWorkerId)->count() === $beforeExactRetryVersions,
        'reusing an Idempotency-Key with a different payload is rejected without writes'
    );
    $keyMetadata = Capsule::table('op_rh_personal')->where('id', $newWorkerId)->first([
        RhDirectCreateIdempotencyMigration::KEY_HASH,
        RhDirectCreateIdempotencyMigration::REQUEST_FINGERPRINT,
    ]);
    $assert(
        is_string($keyMetadata->{RhDirectCreateIdempotencyMigration::KEY_HASH})
            && strlen($keyMetadata->{RhDirectCreateIdempotencyMigration::KEY_HASH}) === 32
            && is_string($keyMetadata->{RhDirectCreateIdempotencyMigration::REQUEST_FINGERPRINT})
            && strlen($keyMetadata->{RhDirectCreateIdempotencyMigration::REQUEST_FINGERPRINT}) === 32,
        'only fixed-length key and normalized request fingerprints are persisted, not the original key'
    );

    $triggerDirectHistory = 'trg_hr_assignment_test_fail_direct_history_insert';
    Capsule::statement("CREATE TRIGGER {$triggerDirectHistory} BEFORE INSERT ON op_rh_personal_localidad_version FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic direct history write failure'");
    $beforeDirectRollbackWorkers = (int)Capsule::table('op_rh_personal')->count();
    $directRollback = $throws(static fn() => $addPersonal([
        'id_estacion' => $workplaces[0], 'fecha_ingreso' => '2026-11-04', 'no_colaborador' => 70007,
        'nombre_completo' => 'Synthetic direct rollback', 'puesto' => 13, 'sd' => 1,
    ], 'rh-create-test-key-rollback-0001'), 'synthetic direct history write failure');
    Capsule::statement("DROP TRIGGER IF EXISTS {$triggerDirectHistory}");
    $assert(
        $directRollback
            && (int)Capsule::table('op_rh_personal')->count() === $beforeDirectRollbackWorkers
            && !Capsule::table('op_rh_personal')->where('no_colaborador', 70007)->exists()
            && !Capsule::table('op_rh_personal')->where(RhDirectCreateIdempotencyMigration::KEY_HASH, hash('sha256', 'rh-create-test-key-rollback-0001', true))->exists(),
        'membership failure rolls back both direct worker and idempotency result'
    );

    if (function_exists('proc_open')) {
        $createRaceBase = sys_get_temp_dir() . '/checador_rh_direct_create_race_' . bin2hex(random_bytes(6));
        mkdir($createRaceBase, 0700, true);
        $createStartPath = $createRaceBase . '/start';
        $createReadyPaths = [$createRaceBase . '/ready-a', $createRaceBase . '/ready-b'];
        $createRaceKey = 'rh-create-test-key-concurrent-0001';
        $createRaceEnv = [
            'DB_CONNECTION' => $dbConfig['driver'], 'DB_HOST' => $dbConfig['host'],
            'DB_PORT' => (string)$dbConfig['port'], 'DB_DATABASE' => $dbConfig['database'],
            'DB_USERNAME' => $dbConfig['username'], 'DB_PASSWORD' => $dbConfig['password'],
            WorkerWorkplaceDirectCreateMembershipWriteGate::ENVIRONMENT_KEY => '1',
        ];
        $createProcesses = [];
        foreach ($createReadyPaths as $readyPath) {
            $pipes = [];
            $process = proc_open([
                PHP_BINARY, __FILE__, '--create-race-worker', (string)$workplaces[0],
                $createRaceKey, $readyPath, $createStartPath,
            ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), $createRaceEnv);
            $createProcesses[] = ['process' => $process, 'pipes' => $pipes];
        }
        $createDeadline = microtime(true) + 15;
        while (microtime(true) < $createDeadline && (!file_exists($createReadyPaths[0]) || !file_exists($createReadyPaths[1]))) usleep(10000);
        $createBothReady = file_exists($createReadyPaths[0]) && file_exists($createReadyPaths[1]);
        file_put_contents($createStartPath, 'go', LOCK_EX);
        $createResults = [];
        foreach ($createProcesses as $entry) {
            fclose($entry['pipes'][0]);
            $stdout = stream_get_contents($entry['pipes'][1]);
            $stderr = stream_get_contents($entry['pipes'][2]);
            fclose($entry['pipes'][1]);
            fclose($entry['pipes'][2]);
            $createResults[] = ['exit' => proc_close($entry['process']), 'stdout' => trim($stdout), 'stderr' => $stderr];
        }
        foreach (glob($createRaceBase . '/*') ?: [] as $path) unlink($path);
        rmdir($createRaceBase);
        $decodedCreateResults = array_map(static fn(array $result): ?array => json_decode($result['stdout'], true), $createResults);
        $raceKeyHash = hash('sha256', $createRaceKey, true);
        $raceWorkerRow = Capsule::table('op_rh_personal')->where(RhDirectCreateIdempotencyMigration::KEY_HASH, $raceKeyHash)->first();
        $assert(
            $createBothReady
                && count($createResults) === 2
                && $createResults[0]['exit'] === 0 && $createResults[1]['exit'] === 0
                && $decodedCreateResults[0] !== null && $decodedCreateResults[1] !== null
                && (int)$decodedCreateResults[0]['worker_id'] === (int)$decodedCreateResults[1]['worker_id']
                && (int)$raceWorkerRow?->id > 0
                && (int)Capsule::table('op_rh_personal')->where('nombre_completo', 'Synthetic idempotent concurrent create')->count() === 1
                && (int)Capsule::table('op_rh_personal_localidad_version')->where('worker_id', $raceWorkerRow->id)->count() === 1,
            'two concurrent identical creates yield one worker and one membership'
        );
    } else {
        $reportBlocked('proc_open unavailable; concurrent direct-create idempotency was not run.');
    }

    $setDirectCreateGate(false);
    $setGate(true);

    $historyCount = static fn(int $workerId): int => (int) Capsule::table('op_rh_personal_localidad_version')->where('worker_id', $workerId)->count();
    $beforeNoChange = $historyCount($newWorkerId);
    $noChange = ControlDocumentosPersonalService::editPersonal($newWorkerId, [
        'id_estacion' => $workplaces[0],
        'fecha_ingreso' => '1998-02-03',
        'no_colaborador' => 70003,
        'nombre_completo' => 'Synthetic edited without move',
        'puesto' => 13,
        'sd' => 2,
    ], []);
    $assert($noChange && $historyCount($newWorkerId) === $beforeNoChange, 'editing non-locality fields does not append a locality version');

    $beforeMove = $historyCount($newWorkerId);
    $moved = ControlDocumentosPersonalService::editPersonal($newWorkerId, [
        'id_estacion' => $workplaces[1],
        'fecha_ingreso' => '1998-02-03',
        'no_colaborador' => 70003,
        'nombre_completo' => 'Synthetic moved worker',
        'puesto' => 13,
        'sd' => 2,
    ], []);
    $transitionRows = Capsule::table('op_rh_personal_localidad_version')
        ->where('worker_id', $newWorkerId)->orderBy('id')->get();
    $originalVersion = $transitionRows->firstWhere('supersedes_id', null);
    $correctedOldVersion = $transitionRows->firstWhere('supersedes_id', (int) ($originalVersion->id ?? 0));
    $newWorkplaceVersion = $transitionRows->firstWhere('workplace_id', $workplaces[1]);
    $transitionInstant = $newWorkplaceVersion
        ? (string) $newWorkplaceVersion->valid_from_utc
        : '';
    $asOfTransition = $transitionInstant !== ''
        ? (new WorkerWorkplaceAssignmentResolver())->resolveAssignments(
            $newWorkerId,
            $toRfc3339($transitionInstant)
        )
        : ['status' => 'DATA_INVALID', 'assignments' => []];
    $beforeTransitionInstant = $transitionInstant !== ''
        ? (new DateTimeImmutable($toRfc3339($transitionInstant)))->modify('-1 microsecond')->format('Y-m-d\TH:i:s.u\Z')
        : '';
    $beforeTransition = $beforeTransitionInstant !== ''
        ? (new WorkerWorkplaceAssignmentResolver())->resolveAssignments($newWorkerId, $beforeTransitionInstant)
        : ['status' => 'DATA_INVALID', 'assignments' => []];
    $assert(
        $moved
            && $historyCount($newWorkerId) === $beforeMove + 2
            && $originalVersion !== null
            && $correctedOldVersion !== null
            && $correctedOldVersion->valid_to_utc !== null
            && $newWorkplaceVersion !== null
            && $beforeTransition['status'] === 'RESOLVED_SINGLE'
            && $beforeTransition['assignments'][0]['workplace_id'] === $workplaces[0]
            && $asOfTransition['status'] === 'RESOLVED_SINGLE'
            && $asOfTransition['assignments'][0]['workplace_id'] === $workplaces[1],
        'locality edit appends a correction and new version with a half-open A-to-B effective boundary'
    );
    $originalStillExists = Capsule::table('op_rh_personal_localidad_version')
        ->where('id', (int) ($originalVersion->id ?? 0))->exists();
    $assert(
        $originalStillExists
            && $originalVersion->valid_to_utc === null
            && (int) $correctedOldVersion->recorded_by === 901
            && str_contains((string) $correctedOldVersion->reason, 'Control de Documentos'),
        'transition preserves the original append-only fact and records correction provenance'
    );

    $sameLocalityCount = $historyCount($newWorkerId);
    $sameLocality = ControlDocumentosPersonalService::editPersonal($newWorkerId, [
        'id_estacion' => $workplaces[1],
        'fecha_ingreso' => '1998-02-03',
        'no_colaborador' => 70003,
        'nombre_completo' => 'Synthetic repeated locality',
        'puesto' => 13,
        'sd' => 2,
    ], []);
    $assert($sameLocality && $historyCount($newWorkerId) === $sameLocalityCount, 'repeating the same locality edit creates no duplicate version');

    $invalidMove = ControlDocumentosPersonalService::editPersonal($newWorkerId, [
        'id_estacion' => 99999,
        'fecha_ingreso' => '1998-02-03',
        'no_colaborador' => 70003,
        'nombre_completo' => 'Synthetic invalid locality',
        'puesto' => 13,
        'sd' => 2,
    ], []);
    $assert(
        !$invalidMove
            && (int) Capsule::table('op_rh_personal')->where('id', $newWorkerId)->value('id_estacion') === $workplaces[1]
            && $historyCount($newWorkerId) === $sameLocalityCount,
        'invalid locality reference is rejected without changing the worker or history'
    );

    $outOfScopeWorkerId = $insertPersonnel(777, 70004, 'Synthetic out-of-scope worker');
    $outOfScopeEdit = ControlDocumentosPersonalService::editPersonal($outOfScopeWorkerId, [
        'id_estacion' => $workplaces[1],
        'fecha_ingreso' => '2001-01-01',
        'no_colaborador' => 70004,
        'nombre_completo' => 'Synthetic out-of-scope worker',
        'puesto' => 13,
        'sd' => 1,
    ], []);
    $assert(
        !$outOfScopeEdit
            && (int) Capsule::table('op_rh_personal')->where('id', $outOfScopeWorkerId)->value('id_estacion') === 777,
        'an authenticated editor cannot move a worker outside their authorized locality scope'
    );

    $untrackedWorkerCount = $historyCount((int) $legacyAddedWorkerId);
    $untrackedMove = ControlDocumentosPersonalService::editPersonal((int) $legacyAddedWorkerId, [
        'id_estacion' => $workplaces[1],
        'fecha_ingreso' => '1998-02-03',
        'no_colaborador' => 70000,
        'nombre_completo' => 'Synthetic legacy add',
        'puesto' => 13,
        'sd' => 1,
    ], []);
    $assert(
        !$untrackedMove
            && (int) Capsule::table('op_rh_personal')->where('id', $legacyAddedWorkerId)->value('id_estacion') === $workplaces[0]
            && $historyCount((int) $legacyAddedWorkerId) === $untrackedWorkerCount,
        'post-activation locality edit fails closed when the worker has no baseline history'
    );

    $conflictHistoryId = (int) Capsule::table('op_rh_personal_localidad_version')->insertGetId([
        'worker_id' => $newWorkerId,
        'workplace_id' => $workplaces[2],
        'valid_from_utc' => '2002-01-01 00:00:00.000000',
        'valid_to_utc' => null,
        'recorded_at_utc' => '2002-01-01 00:00:00.000000',
        'recorded_by' => 901,
        'reason' => 'Synthetic conflict fixture',
        'supersedes_id' => null,
    ]);
    $beforeConflictAttempt = $historyCount($newWorkerId);
    $conflictMove = ControlDocumentosPersonalService::editPersonal($newWorkerId, [
        'id_estacion' => $workplaces[0],
        'fecha_ingreso' => '1998-02-03',
        'no_colaborador' => 70003,
        'nombre_completo' => 'Synthetic moved worker',
        'puesto' => 13,
        'sd' => 2,
    ], []);
    $assert(
        !$conflictMove
            && (int) Capsule::table('op_rh_personal')->where('id', $newWorkerId)->value('id_estacion') === $workplaces[1]
            && $historyCount($newWorkerId) === $beforeConflictAttempt,
        'history ambiguity blocks the owning HR locality update without partial writes'
    );
    Capsule::table('op_rh_personal_localidad_version')->where('id', $conflictHistoryId)->delete();

    $triggerUpdate = 'trg_hr_assignment_test_fail_personal_update';
    Capsule::statement("CREATE TRIGGER {$triggerUpdate} BEFORE UPDATE ON op_rh_personal FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic owner update failure'");
    $beforeOwnerFailure = $historyCount($newWorkerId);
    $ownerFailure = $throws(static fn() => ControlDocumentosPersonalService::editPersonal($newWorkerId, [
        'id_estacion' => $workplaces[2],
        'fecha_ingreso' => '1998-02-03',
        'no_colaborador' => 70003,
        'nombre_completo' => 'Synthetic moved worker',
        'puesto' => 13,
        'sd' => 2,
    ], []), 'synthetic owner update failure');
    Capsule::statement("DROP TRIGGER IF EXISTS {$triggerUpdate}");
    $assert(
        $ownerFailure
            && (int) Capsule::table('op_rh_personal')->where('id', $newWorkerId)->value('id_estacion') === $workplaces[1]
            && $historyCount($newWorkerId) === $beforeOwnerFailure,
        'owner update failure rolls back the already-prepared history transition atomically'
    );

    if (function_exists('proc_open')) {
        $tempBase = sys_get_temp_dir() . '/checador_hr_assignment_race_' . bin2hex(random_bytes(6));
        mkdir($tempBase, 0700, true);
        $startPath = $tempBase . '/start';
        $readyPaths = [$tempBase . '/ready-a', $tempBase . '/ready-b'];
        $raceTargets = [$workplaces[0], $workplaces[2]];
        $childEnvironment = [
            'DB_CONNECTION' => $dbConfig['driver'],
            'DB_HOST' => $dbConfig['host'],
            'DB_PORT' => (string) $dbConfig['port'],
            'DB_DATABASE' => $dbConfig['database'],
            'DB_USERNAME' => $dbConfig['username'],
            'DB_PASSWORD' => $dbConfig['password'],
            WorkerWorkplaceAssignmentHistoryWriteGate::ENVIRONMENT_KEY => '1',
        ];
        $processes = [];
        foreach ($raceTargets as $index => $targetId) {
            $pipes = [];
            $process = proc_open([
                PHP_BINARY,
                __FILE__,
                '--race-worker',
                (string) $newWorkerId,
                (string) $targetId,
                $readyPaths[$index],
                $startPath,
            ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), $childEnvironment);
            $processes[] = ['process' => $process, 'pipes' => $pipes];
        }
        $readyDeadline = microtime(true) + 15;
        while (microtime(true) < $readyDeadline
            && (!file_exists($readyPaths[0]) || !file_exists($readyPaths[1]))) {
            usleep(10000);
        }
        $bothReady = file_exists($readyPaths[0]) && file_exists($readyPaths[1]);
        file_put_contents($startPath, 'go', LOCK_EX);
        $childResults = [];
        foreach ($processes as $entry) {
            fclose($entry['pipes'][0]);
            $stdout = stream_get_contents($entry['pipes'][1]);
            $stderr = stream_get_contents($entry['pipes'][2]);
            fclose($entry['pipes'][1]);
            fclose($entry['pipes'][2]);
            $exitCode = proc_close($entry['process']);
            $childResults[] = ['exit' => $exitCode, 'stdout' => $stdout, 'stderr' => $stderr];
        }
        foreach (glob($tempBase . '/*') ?: [] as $path) {
            unlink($path);
        }
        rmdir($tempBase);
        $finalLocality = (int) Capsule::table('op_rh_personal')->where('id', $newWorkerId)->value('id_estacion');
        $raceHistory = (new WorkerWorkplaceAssignmentResolver())->resolveAssignments(
            $newWorkerId,
            (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z')
        );
        $assert(
            $bothReady
                && count($childResults) === 2
                && $childResults[0]['exit'] === 0
                && $childResults[1]['exit'] === 0
                && in_array($finalLocality, $raceTargets, true)
                && $raceHistory['status'] === 'RESOLVED_SINGLE'
                && $raceHistory['assignments'][0]['workplace_id'] === $finalLocality,
            'two concurrent HR locality changes serialize and leave one effective canonical assignment'
        );
    } else {
        $reportBlocked('proc_open is unavailable; two-process HR concurrency regression was not run.');
    }

    $setActor(2, false, false);
    $setGate(true);
    $setDirectCreateGate(false);
    $formatId = (int) Capsule::table('op_rh_formatos')->insertGetId([
        'id_localidad' => 999,
        'id_usuario' => 2,
        'formato' => 1,
        'fecha' => date('Y-m-d H:i:s'),
        'status' => 2,
    ]);
    Capsule::table('op_rh_formatos_token')->insert([
        'id_formato' => $formatId,
        'id_usuario' => 2,
        'fecha_creacion' => date('Y-m-d H:i:s'),
        'token' => 240001,
    ]);
    Capsule::table('op_rh_formatos_alta')->insert([
        'id_formulario' => $formatId,
        'id_estacion' => 999,
        'fecha_ingreso' => '1990-01-01',
        'nombre' => 'Synthetic authorized alta locality 999',
        'puesto' => 13,
        'sd' => 1,
    ]);
    $beforeFormatAlta = (int) Capsule::table('op_rh_personal')->count();
    $formatAltaBefore = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $altaResult = FormatosService::firmarFormato($formatId, 'C', 240001, 2);
    $formatAltaAfter = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $altaWorker = Capsule::table('op_rh_personal')->where('nombre_completo', 'Synthetic authorized alta locality 999')->first();
    $altaVersion = $altaWorker
        ? Capsule::table('op_rh_personal_localidad_version')->where('worker_id', $altaWorker->id)->first()
        : null;
    $altaValidFrom = $altaVersion
        ? new DateTimeImmutable(str_replace(' ', 'T', (string) $altaVersion->valid_from_utc) . 'Z')
        : null;
    $assert(
        ($altaResult['success'] ?? false)
            && (int) Capsule::table('op_rh_personal')->count() === $beforeFormatAlta + 1
            && $altaWorker !== null
            && (int) $altaWorker->id_estacion === 999
            && $altaVersion !== null
            && (int) $altaVersion->workplace_id === 999
            && $altaValidFrom !== null
            && $altaValidFrom >= $formatAltaBefore
            && $altaValidFrom <= $formatAltaAfter
            && (int) $altaVersion->recorded_by === 2
            && str_contains((string) $altaVersion->reason, 'formato RH #' . $formatId)
            && (string) $altaVersion->valid_from_utc !== '1990-01-01 00:00:00.000000',
        'authorized alta creates a current-time version with signer provenance and preserves direct locality ID 999'
    );
    $assert(
        Capsule::table('tb_estaciones')->where('numlista', 999)->doesntExist()
            && (int) $altaVersion->workplace_id === 999,
        'locality 999 is stored in its canonical locality ID space without station/numlista conversion'
    );

    $duplicateAltaId = (int) Capsule::table('op_rh_formatos')->insertGetId([
        'id_localidad' => 999, 'id_usuario' => 2, 'formato' => 1,
        'fecha' => date('Y-m-d H:i:s'), 'status' => 2,
    ]);
    Capsule::table('op_rh_formatos_token')->insert([
        'id_formato' => $duplicateAltaId, 'id_usuario' => 2,
        'fecha_creacion' => date('Y-m-d H:i:s'), 'token' => 240007,
    ]);
    Capsule::table('op_rh_formatos_alta')->insert([
        'id_formulario' => $duplicateAltaId, 'id_estacion' => 999,
        'fecha_ingreso' => '1990-01-01', 'nombre' => 'Synthetic authorized alta locality 999',
        'puesto' => 13, 'sd' => 1,
    ]);
    $duplicateAltaWorkersBefore = (int) Capsule::table('op_rh_personal')->count();
    $duplicateAltaHistoryBefore = $historyCount((int) $altaWorker->id);
    $duplicateAlta = FormatosService::firmarFormato($duplicateAltaId, 'C', 240007, 2);
    $assert(
        ($duplicateAlta['success'] ?? false)
            && (int) Capsule::table('op_rh_personal')->count() === $duplicateAltaWorkersBefore
            && $historyCount((int) $altaWorker->id) === $duplicateAltaHistoryBefore,
        'repeated authorized alta recognizes the existing matching history and does not duplicate worker or version'
    );

    $pendingAltaId = (int) Capsule::table('op_rh_formatos')->insertGetId([
        'id_localidad' => $workplaces[0], 'id_usuario' => 901, 'formato' => 1,
        'fecha' => date('Y-m-d H:i:s'), 'status' => 1,
    ]);
    Capsule::table('op_rh_formatos_token')->insert([
        'id_formato' => $pendingAltaId, 'id_usuario' => 2,
        'fecha_creacion' => date('Y-m-d H:i:s'), 'token' => 240002,
    ]);
    Capsule::table('op_rh_formatos_alta')->insert([
        'id_formulario' => $pendingAltaId, 'id_estacion' => $workplaces[0],
        'fecha_ingreso' => '2001-01-01', 'nombre' => 'Synthetic pending alta', 'puesto' => 13, 'sd' => 1,
    ]);
    $pendingAltaCount = (int) Capsule::table('op_rh_personal')->count();
    $pendingAltaResult = FormatosService::firmarFormato($pendingAltaId, 'C', 240002, 2);
    $assert(
        !($pendingAltaResult['success'] ?? false)
            && (int) Capsule::table('op_rh_personal')->count() === $pendingAltaCount
            && (int) Capsule::table('op_rh_formatos')->where('id', $pendingAltaId)->value('status') === 1
            && !Capsule::table('op_rh_formatos_firma')->where('id_formato', $pendingAltaId)->exists(),
        'pending alta is not applied before the authorization stage'
    );

    $triggerHistory = 'trg_hr_assignment_test_fail_history_insert';
    Capsule::statement("CREATE TRIGGER {$triggerHistory} BEFORE INSERT ON op_rh_personal_localidad_version FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic history write failure'");
    $atomicAltaId = (int) Capsule::table('op_rh_formatos')->insertGetId([
        'id_localidad' => $workplaces[0], 'id_usuario' => 901, 'formato' => 1,
        'fecha' => date('Y-m-d H:i:s'), 'status' => 2,
    ]);
    Capsule::table('op_rh_formatos_token')->insert([
        'id_formato' => $atomicAltaId, 'id_usuario' => 2,
        'fecha_creacion' => date('Y-m-d H:i:s'), 'token' => 240003,
    ]);
    Capsule::table('op_rh_formatos_alta')->insert([
        'id_formulario' => $atomicAltaId, 'id_estacion' => $workplaces[0],
        'fecha_ingreso' => '2001-01-01', 'nombre' => 'Synthetic failed atomic alta', 'puesto' => 13, 'sd' => 1,
    ]);
    $beforeAtomicAlta = (int) Capsule::table('op_rh_personal')->count();
    $atomicAltaResult = FormatosService::firmarFormato($atomicAltaId, 'C', 240003, 2);
    Capsule::statement("DROP TRIGGER IF EXISTS {$triggerHistory}");
    $assert(
        !($atomicAltaResult['success'] ?? false)
            && (int) Capsule::table('op_rh_personal')->count() === $beforeAtomicAlta
            && (int) Capsule::table('op_rh_formatos')->where('id', $atomicAltaId)->value('status') === 2
            && !Capsule::table('op_rh_formatos_firma')->where('id_formato', $atomicAltaId)->exists()
            && !Capsule::table('op_rh_personal')->where('nombre_completo', 'Synthetic failed atomic alta')->exists(),
        'history insert failure rolls back authorized worker creation, format status, and signature atomically'
    );

    $restructWorker = $insertPersonnel($workplaces[0], 70005, 'Synthetic restructured worker');
    $restructWriter = new WorkerWorkplaceAssignmentVersionService();
    $restructWriter->appendVersion($restructWorker, $workplaces[0], $restructWriter->nowUtc(), null, 901, 'Synthetic baseline fixture');
    $pendingReorgId = (int) Capsule::table('op_rh_formatos')->insertGetId([
        'id_localidad' => $workplaces[0], 'id_usuario' => 901, 'formato' => 4,
        'fecha' => date('Y-m-d H:i:s'), 'status' => 1,
    ]);
    Capsule::table('op_rh_formatos_token')->insert([
        'id_formato' => $pendingReorgId, 'id_usuario' => 2,
        'fecha_creacion' => date('Y-m-d H:i:s'), 'token' => 240004,
    ]);
    Capsule::table('op_rh_formatos_restructuracion')->insert([
        'id_formulario' => $pendingReorgId, 'id_personal' => $restructWorker,
        'id_estacion' => $workplaces[0], 'id_estacion_cambio' => $workplaces[1], 'fecha' => '2027-02-03',
    ]);
    $restructHistoryBefore = $historyCount($restructWorker);
    $pendingReorg = FormatosService::firmarFormato($pendingReorgId, 'C', 240004, 2);
    $assert(
        !($pendingReorg['success'] ?? false)
            && (int) Capsule::table('op_rh_personal')->where('id', $restructWorker)->value('id_estacion') === $workplaces[0]
            && $historyCount($restructWorker) === $restructHistoryBefore
            && (int) Capsule::table('op_rh_formatos')->where('id', $pendingReorgId)->value('status') === 1,
        'pending restructuring does not change the worker or create history'
    );

    $invalidReorgId = (int) Capsule::table('op_rh_formatos')->insertGetId([
        'id_localidad' => $workplaces[0], 'id_usuario' => 901, 'formato' => 4,
        'fecha' => date('Y-m-d H:i:s'), 'status' => 2,
    ]);
    Capsule::table('op_rh_formatos_token')->insert([
        'id_formato' => $invalidReorgId, 'id_usuario' => 2,
        'fecha_creacion' => date('Y-m-d H:i:s'), 'token' => 240005,
    ]);
    Capsule::table('op_rh_formatos_restructuracion')->insert([
        'id_formulario' => $invalidReorgId, 'id_personal' => $restructWorker,
        'id_estacion' => $workplaces[0], 'id_estacion_cambio' => 99999, 'fecha' => '2027-02-03',
    ]);
    $invalidReorg = FormatosService::firmarFormato($invalidReorgId, 'C', 240005, 2);
    $assert(
        !($invalidReorg['success'] ?? false)
            && (int) Capsule::table('op_rh_personal')->where('id', $restructWorker)->value('id_estacion') === $workplaces[0]
            && $historyCount($restructWorker) === $restructHistoryBefore,
        'restructuring with a nonexistent or ambiguous canonical destination is rejected'
    );

    $reorgId = (int) Capsule::table('op_rh_formatos')->insertGetId([
        'id_localidad' => $workplaces[0], 'id_usuario' => 901, 'formato' => 4,
        'fecha' => date('Y-m-d H:i:s'), 'status' => 2,
    ]);
    Capsule::table('op_rh_formatos_token')->insert([
        'id_formato' => $reorgId, 'id_usuario' => 2,
        'fecha_creacion' => date('Y-m-d H:i:s'), 'token' => 240006,
    ]);
    Capsule::table('op_rh_formatos_restructuracion')->insert([
        'id_formulario' => $reorgId, 'id_personal' => $restructWorker,
        'id_estacion' => $workplaces[0], 'id_estacion_cambio' => $workplaces[1], 'fecha' => '2027-02-03',
    ]);
    $dateOnlyReorg = FormatosService::firmarFormato($reorgId, 'C', 240006, 2);
    $assert(
        !($dateOnlyReorg['success'] ?? false)
            && (int) Capsule::table('op_rh_personal')->where('id', $restructWorker)->value('id_estacion') === $workplaces[0]
            && $historyCount($restructWorker) === $restructHistoryBefore
            && (int) Capsule::table('op_rh_formatos')->where('id', $reorgId)->value('status') === 2
            && !Capsule::table('op_rh_formatos_firma')->where('id_formato', $reorgId)->exists(),
        'date-only restructuring fails closed and rolls back until an effective-time timezone policy exists'
    );
    $reportBlocked('authorized restructuring cannot version DATE-only effective dates without an institutional time and timezone rule.');

    $correctionTarget = Capsule::table('op_rh_personal_localidad_version')
        ->where('worker_id', $restructWorker)->orderBy('id')->first();
    $correctionWriter = new WorkerWorkplaceAssignmentVersionService();
    $correctedId = $correctionWriter->appendVersion(
        $restructWorker,
        $workplaces[1],
        $toRfc3339((string) $correctionTarget->valid_from_utc),
        null,
        901,
        'Synthetic administrative correction',
        (int) $correctionTarget->id
    );
    $assert(
        $correctedId > 0
            && Capsule::table('op_rh_personal_localidad_version')->where('id', $correctionTarget->id)->exists()
            && (int) Capsule::table('op_rh_personal_localidad_version')->where('id', $correctedId)->value('supersedes_id') === (int) $correctionTarget->id,
        'historical correction uses supersedes lineage and never updates or deletes the original row'
    );
    $immutable = RhPersonalLocalidadVersion::query()->whereKey($correctedId)->firstOrFail();
    $assert(
        $throws(static function () use ($immutable): void { $immutable->reason = 'overwrite'; $immutable->save(); }, 'append-only')
            && $throws(static fn() => $immutable->delete(), 'append-only'),
        'existing append-only model still rejects history update and delete'
    );

    $deniedEditBefore = (int) Capsule::table('op_rh_personal')->where('id', $newWorkerId)->value('id_estacion');
    $setActor(901, true, false);
    $deniedEdit = ControlDocumentosPersonalService::editPersonal($newWorkerId, [
        'id_estacion' => $workplaces[0], 'fecha_ingreso' => '1998-02-03',
        'no_colaborador' => 70003, 'nombre_completo' => 'Synthetic moved worker', 'puesto' => 13, 'sd' => 1,
    ], []);
    $assert(
        !$deniedEdit
            && (int) Capsule::table('op_rh_personal')->where('id', $newWorkerId)->value('id_estacion') === $deniedEditBefore,
        'user without HR edit permission cannot change another worker locality'
    );

    if ($savedGateValue === null) unset($_ENV[$gateKey]); else $_ENV[$gateKey] = $savedGateValue;
    if ($savedServerGateValue === null) unset($_SERVER[$gateKey]); else $_SERVER[$gateKey] = $savedServerGateValue;
    if ($savedDirectGateValue === null) unset($_ENV[$directGateKey]); else $_ENV[$directGateKey] = $savedDirectGateValue;
    if ($savedServerDirectGateValue === null) unset($_SERVER[$directGateKey]); else $_SERVER[$directGateKey] = $savedServerDirectGateValue;
} finally {
    foreach (['trg_hr_assignment_test_fail_personal_update', 'trg_hr_assignment_test_fail_history_insert', 'trg_hr_assignment_test_fail_direct_history_insert'] as $trigger) {
        try { Capsule::statement("DROP TRIGGER IF EXISTS {$trigger}"); } catch (Throwable) {}
    }
    if (Capsule::schema()->hasTable('op_rh_personal_localidad_version')) {
        $connection->statement('SET FOREIGN_KEY_CHECKS=0');
        Capsule::schema()->drop('op_rh_personal_localidad_version');
        $connection->statement('SET FOREIGN_KEY_CHECKS=1');
    }
    foreach (array_reverse($fixtureTables) as $table) {
        if ($table !== 'op_rh_personal_localidad_version' && Capsule::schema()->hasTable($table)) {
            Capsule::schema()->drop($table);
        }
    }
}

printf("CHECADOR_V2_HR_ASSIGNMENT_MARIADB_RESULT: %d PASS / %d FAIL / %d BLOCKED\n", $passed, $failed, $blocked);
$suiteOutput = ob_get_clean();
echo $suiteOutput;
exit($failed === 0 ? 0 : 1);
