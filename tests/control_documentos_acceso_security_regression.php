<?php
declare(strict_types=1);
namespace App\Core {
    final class Auth { public static bool $active = true; public static int $position = 6; public static function check(): bool { return self::$active; } public static function user(): ?object { return self::$active ? (object)['id'=>7, 'id_puesto' => self::$position, 'id_gas' => 101, 'puesto' => (object)['tipo_puesto' => 'Encargado']] : null; } }
    final class Session { public static function get(string $key): mixed { return $key === 'usuario' ? ['id'=>7, 'id_estacion'=>101] : null; } }
}
namespace App\Services {
    final class ModuleStationService { public static array $stations = [['id'=>101]]; public static int $contextStation = 101; public static function getAvailableStations(string $key): array { return self::$stations; } public static function getAvailableDepartments(string $key): array { return []; } public static function getContext(string $key): array { return ['id_estacion'=>self::$contextStation]; } }
    final class ModuloDptoOperativoService { public static array $permissions = ['leer'=>1, 'editar'=>1]; public static function permisosSesion(?string $key = null): array { return self::$permissions; } }
    final class MultiestacionService {
        public const TABLA_ESTACIONES = 'tb_estaciones';
        public const TABLA_RH_LOCALIDADES = 'op_rh_localidades';
        public static function convertIds(array $ids, string $from, string $to): array {
            if ($from === self::TABLA_ESTACIONES && $to === self::TABLA_RH_LOCALIDADES) {
                $result = [];
                foreach ($ids as $id) {
                    $station = \Illuminate\Database\Capsule\Manager::connection('default')->table('tb_estaciones')->where('id', $id)->first();
                    if ($station) {
                        $locality = \Illuminate\Database\Capsule\Manager::connection('default')->table('op_rh_localidades')->where('numlista', $station->numlista)->first();
                        if ($locality) $result[] = (int) $locality->id;
                    }
                }
                return array_values(array_unique($result));
            }
            return $ids;
        }
    }
    final class ChecadorAccessService {
        public static function resolveActiveStationIdForLocality(int $localityId): ?int {
            $db = \Illuminate\Database\Capsule\Manager::connection('default');
            $locality = $db->table('op_rh_localidades')->where('id', $localityId)->first();
            if (!$locality || $db->table('op_rh_localidades')->where('numlista', $locality->numlista)->count() !== 1) return null;
            $stations = $db->table('tb_estaciones')->where('numlista', $locality->numlista)->where('estatus', 0)->get();
            return $stations->count() === 1 ? (int) $stations[0]->id : null;
        }
    }
    final class TelegramService { public static function notificar(int $stationId, int $userId, string $message): void {} }
}
namespace {
    require_once __DIR__ . '/../vendor/autoload.php';
    $capsule = new \Illuminate\Database\Capsule\Manager();
    $capsule->addConnection(['driver'=>'sqlite','database'=>':memory:','prefix'=>'']);
    $capsule->setAsGlobal(); $capsule->bootEloquent();
    $db = $capsule->getConnection(); $schema = $db->getSchemaBuilder();
    $schema->create('tb_estaciones', static function ($t): void { $t->integer('id')->primary(); $t->integer('estatus'); $t->integer('numlista'); });
    $db->table('tb_estaciones')->insert([['id'=>101,'estatus'=>0,'numlista'=>1],['id'=>202,'estatus'=>0,'numlista'=>2]]);
    $schema->create('op_rh_localidades', static function ($t): void { $t->increments('id'); $t->integer('numlista'); $t->string('localidad'); });
    $db->table('op_rh_localidades')->insert([['id'=>1001,'numlista'=>1,'localidad'=>'Estación autorizada'],['id'=>2002,'numlista'=>2,'localidad'=>'Otra estación']]);
    $schema->create('op_rh_personal', static function ($t): void {
        $t->increments('id'); $t->integer('id_estacion'); $t->integer('puesto');
        $t->string('fecha_ingreso')->nullable(); $t->integer('no_colaborador')->nullable();
        $t->string('nombre_completo')->nullable(); $t->float('sd')->nullable(); $t->integer('estado')->nullable();
        foreach (array_keys(\App\Services\ControlDocumentosPersonalService::DOCUMENT_TYPES) as $field) $t->text($field)->nullable();
    });
    $schema->create('op_rh_puestos', static function ($t): void { $t->increments('id'); $t->string('puesto'); });
    $schema->create('op_rh_personal_acceso', static function ($t): void { $t->increments('id'); $t->integer('id_personal'); $t->text('huella'); $t->integer('pin'); });
    $schema->create('op_rh_personal_localidad_version', static function ($t): void {
        $t->increments('id'); $t->integer('worker_id'); $t->integer('workplace_id');
        $t->dateTime('valid_from_utc'); $t->dateTime('valid_to_utc')->nullable(); $t->dateTime('recorded_at_utc');
        $t->integer('recorded_by')->nullable(); $t->string('reason')->nullable(); $t->integer('supersedes_id')->nullable();
    });
    $schema->create('tb_usuarios', static function ($t): void { $t->increments('id'); $t->string('nombre'); });
    $db->table('tb_usuarios')->insert(['id'=>7,'nombre'=>'Operador de prueba']);
    $db->table('op_rh_puestos')->insert(['id'=>6,'puesto'=>'Encargado']);
    $db->table('op_rh_personal')->insert([['id'=>1,'id_estacion'=>101,'puesto'=>6],['id'=>2,'id_estacion'=>202,'puesto'=>6],['id'=>3,'id_estacion'=>101,'puesto'=>6]]);
    $db->table('op_rh_personal_acceso')->insert(['id_personal'=>1,'huella'=>'sensitive-template','pin'=>12345]);
    $service = \App\Services\ControlDocumentosPersonalService::class;
    $assert = static function (bool $ok, string $message): void { if (!$ok) throw new \RuntimeException($message); echo "PASS {$message}\n"; };
    $result = $service::getAcceso(1);
    $assert($result['huella'] === true && !str_contains(json_encode($result), 'sensitive-template'), 'authorized access returns only biometric presence');
    $assert($service::getAcceso(2) === null && $service::getAcceso(3)['huella'] === false && $db->table('op_rh_personal_acceso')->count() === 1, 'cross-station denied and empty read does not create records');
    $denied = $service::editarPin(2, '98765');
    $assert(($denied['code'] ?? null) === 403 && $db->table('op_rh_personal_acceso')->count() === 1, 'cross-station PIN write denied before lookup or insert');
    \App\Services\ModuloDptoOperativoService::$permissions = ['leer'=>1, 'editar'=>1, 'crear'=>1];
    $assert($service::getPermisos()['puedeCrear'] === true, 'Encargado with create permission does not require multi-station');
    \App\Services\ModuleStationService::$stations = [['id'=>101], ['id'=>202]];
    $input = ['id_estacion'=>202, 'fecha_ingreso'=>'2026-11-25', 'no_colaborador'=>'19', 'nombre_completo'=>'Ejemplo', 'puesto'=>6];
    $assert($service::addPersonal($input, []) === null && $db->table('op_rh_personal')->count() === 3, 'Encargado cannot create for a foreign physical station even with a broad list');
    $input['id_estacion'] = 101;
    $createdId = $service::addPersonal($input, []);
    $created = $db->table('op_rh_personal')->where('id', $createdId)->first();
    $assert($created !== null && (int)$created->id_estacion === 101 && (int)$created->estado === 1, 'authorized Encargado creates an active worker at the assigned station');
    $membership = $db->table('op_rh_personal_localidad_version')->where('worker_id', $createdId)->first();
    $assert($membership !== null && (int)$membership->workplace_id === 1001, 'new worker receives canonical membership through append-only assignment service');
    $assignment = $membership ? (new \App\Services\WorkerWorkplaceAssignmentResolver())->resolveAssignmentsAsRecordedAt(
        (int) $createdId,
        (new \DateTimeImmutable((string)$membership->valid_from_utc, new \DateTimeZone('UTC')))->format('Y-m-d\\TH:i:s.u\\Z'),
        (new \DateTimeImmutable((string)$membership->recorded_at_utc, new \DateTimeZone('UTC')))->format('Y-m-d\\TH:i:s.u\\Z')
    ) : [];
    $assert(($assignment['status'] ?? '') === 'RESOLVED_SINGLE' && (int)($assignment['assignments'][0]['workplace_id'] ?? 0) === 1001, 'canonical membership readback resolves to the assigned locality');
    $accessRows = $db->table('op_rh_personal_acceso')->where('id_personal', $createdId)->get();
    $assert($accessRows->count() === 1 && (string)$accessRows[0]->huella === '' && (int)$accessRows[0]->pin === 0,
        'worker creation creates one access row using the existing no-template/no-PIN convention');
    $assert($service::addPersonal($input, []) === null && $db->table('op_rh_personal')->count() === 4, 'duplicate active employee number at the same station is rejected');
    $db->statement("CREATE TRIGGER fail_personal_access_insert BEFORE INSERT ON op_rh_personal_acceso BEGIN SELECT RAISE(ABORT, 'isolated access insert failure'); END");
    $rollbackObserved = false;
    $failedInput = $input;
    $failedInput['no_colaborador'] = '21';
    try { $service::addPersonal($failedInput, []); } catch (\Throwable) { $rollbackObserved = true; }
    $assert($rollbackObserved && $db->table('op_rh_personal')->count() === 4
        && $db->table('op_rh_personal_localidad_version')->count() === 1
        && $db->table('op_rh_personal_acceso')->count() === 2,
        'access-row failure rolls back worker and canonical membership in the same transaction');
    $db->statement('DROP TRIGGER fail_personal_access_insert');
    \App\Services\ModuloDptoOperativoService::$permissions = ['leer'=>0, 'editar'=>0];
    $assert($service::getAcceso(1) === null && ($service::editarPin(1, '98765')['code'] ?? null) === 403, 'missing permissions deny read and write');
    $assert($service::addPersonal([], []) === null && $db->table('op_rh_personal')->count() === 4, 'worker creation without create permission is denied before writes');
    \App\Core\Auth::$active = false;
    $assert($service::getAcceso(1) === null && ($service::editarPin(1, '98765')['code'] ?? null) === 403, 'anonymous access denied');
}
