<?php

declare(strict_types=1);

namespace App\Models { class Usuario { public int $id = 7; } }
namespace App\Core { class Auth { public static ?\App\Models\Usuario $current = null; public static function user(): ?\App\Models\Usuario { return self::$current; } } }
namespace App\Services {
    function random_int(int $min, int $max): int { return 42; }
    class ChecadorAccessService {
        public static bool $allowed = true;
        public static bool $mapped = true;
        public static function authorizeTemporaryAccess(?int $locality, string $action): array { return ['allowed'=>self::$allowed && $locality === 1 && $action === 'editar']; }
        public static function temporaryAccessRequestBelongsToLocality(?int $station, ?int $locality): bool { return self::$mapped && $station === 2 && $locality === 1; }
    }
}
namespace Illuminate\Database\Capsule { class Manager { public static $db; public static function connection($name = null) { if ($name !== 'default') throw new \RuntimeException('unexpected connection'); return self::$db; } } }
namespace {
    require __DIR__ . '/../app/Services/ChecadorTemporaryAccessSqlService.php';
    class FakeCollection extends \ArrayObject { }
    class FakeM08Query {
        private array $where = [];
        private bool $locked = false;
        public function __construct(private FakeM08Db $db, private string $table) {}
        public function where($key, $value): self { $this->where[$key] = $value; return $this; }
        public function whereRaw($sql): self { return $this; }
        public function lockForUpdate(): self { $this->locked = true; $this->db->locks[] = $this->table; return $this; }
        public function first($columns = ['*']): ?object {
            $row = $this->db->rows[$this->table] ?? null;
            if (!$row) return null;
            foreach ($this->where as $key => $value) if (($row[$key] ?? null) != $value) return null;
            if ($this->table === 'op_rh_v2_solicitudes_acceso_temporal' && in_array('pin_hash', $columns, true) && $this->db->badReadback) $row['status'] = 'BROKEN';
            $out = []; foreach ($columns as $column) $out[$column] = $row[$column] ?? null;
            return (object)$out;
        }
        public function get($columns = ['*']): FakeCollection {
            $row = $this->first($columns);
            return new FakeCollection($row ? [$row] : []);
        }
        public function update(array $values): int {
            $this->db->updates++;
            if ($this->db->conflict) return 0;
            $row = $this->db->rows[$this->table];
            foreach ($this->where as $key => $value) if (($row[$key] ?? null) != $value) return 0;
            $this->db->rows[$this->table] = array_replace($row, $values);
            return 1;
        }
    }
    class FakeM08Db {
        public array $rows; public array $locks = []; public int $updates = 0; public int $transactions = 0; public bool $conflict = false; public bool $badReadback = false; public bool $committed = false; public bool $rolledBack = false;
        public function __construct() {
            $this->rows = [
                'op_rh_v2_dispositivos'=>['id'=>2,'id_estacion'=>2,'estado'=>1],
                'op_rh_v2_solicitudes_acceso_temporal'=>['id'=>10,'request_uuid'=>'12345678-1234-4234-8234-123456789abc','id_dispositivo'=>2,'id_estacion'=>2,'id_personal_resuelto'=>3,'referencia_externa_personal'=>'REF','status'=>'PENDING','request_expires_at_utc'=>'2026-11-26 12:30:00','pin_expires_at_utc'=>null],
                'tb_estaciones'=>['id'=>2,'estatus'=>0], 'op_rh_personal'=>['id'=>3,'estado'=>1,'id_estacion'=>2],
                'op_rh_v2_dispositivo_identidad_personal'=>['id_dispositivo'=>2,'referencia_externa_personal'=>'REF','es_activo'=>1,'id_personal'=>3],
            ];
        }
        public function table(string $name): FakeM08Query { return new FakeM08Query($this,$name); }
        public function selectOne(string $sql): object { return (object)['now_utc'=>'2026-11-26 12:00:00','pin_expiry'=>'2026-11-26 12:15:00']; }
        public function transaction(callable $callback): array { $this->transactions++; $before=$this->rows; try { $result=$callback(); $this->committed=true; return $result; } catch (\Throwable $e) { $this->rows=$before; $this->rolledBack=true; throw $e; } }
    }
    $assert = static function(bool $ok,string $label): void { if (!$ok) throw new \RuntimeException($label); echo "PASS $label\n"; };
    $uuid = '12345678-1234-4234-8234-123456789abc'; $actor = new \App\Models\Usuario(); \App\Core\Auth::$current=$actor;
    $_ENV['CHECADOR_M08_HUMAN_WRITER']='PORTAL3'; $service = new \App\Services\ChecadorTemporaryAccessSqlService();
    $fresh = static function(): FakeM08Db { $db=new FakeM08Db(); \Illuminate\Database\Capsule\Manager::$db=$db; \App\Services\ChecadorAccessService::$allowed=true; \App\Services\ChecadorAccessService::$mapped=true; return $db; };
    $db=$fresh(); $out=$service->action($actor,1,$uuid,'authorize'); $row=$db->rows['op_rh_v2_solicitudes_acceso_temporal'];
    $assert(($out['ok'] ?? false) && $db->committed && ($out['data']['temporary_pin'] ?? '') === '000042' && $row['status'] === 'AUTHORIZED' && password_verify('000042',$row['pin_hash']) && $row['authorized_by_user_id']===7 && $row['pin_expires_at_utc']==='2026-11-26 12:15:00', 'authorize commits six-digit PIN, bcrypt, actor and 15-minute expiry');
    $assert(array_slice($db->locks,0,2) === ['op_rh_v2_dispositivos','op_rh_v2_solicitudes_acceso_temporal'], 'device lock precedes request lock');
    $assert(!str_contains(json_encode($out),$row['pin_hash']), 'authorization response excludes the stored hash');
    $again=$service->action($actor,1,$uuid,'authorize'); $assert(($again['error']['kind'] ?? '') === 'invalid_state' && $db->rows['op_rh_v2_solicitudes_acceso_temporal']['pin_hash']===$row['pin_hash'], 'double authorization does not issue another PIN');
    foreach (['reject'=>'REJECTED','cancel'=>'CANCELLED'] as $action=>$state) { $db=$fresh(); $out=$service->action($actor,1,$uuid,$action); $assert(($out['ok'] ?? false) && $db->rows['op_rh_v2_solicitudes_acceso_temporal']['status']===$state && !isset($out['data']['temporary_pin']), $action.' commits its transition without PIN'); }
    $db=$fresh(); $db->rows['op_rh_v2_solicitudes_acceso_temporal']['status']='AUTHORIZED'; $db->rows['op_rh_v2_solicitudes_acceso_temporal']['pin_expires_at_utc']='2026-11-26 12:15:00'; $out=$service->action($actor,1,$uuid,'cancel'); $assert(($out['ok'] ?? false) && $db->rows['op_rh_v2_solicitudes_acceso_temporal']['status']==='CANCELLED', 'unexpired authorized PIN can be cancelled');
    $db=$fresh(); $db->rows['op_rh_v2_solicitudes_acceso_temporal']['request_expires_at_utc']='2026-11-26 12:00:00'; $assert(($service->action($actor,1,$uuid,'authorize')['error']['kind'] ?? '')==='invalid_state' && $db->rolledBack, 'expired request rolls back');
    $db=$fresh(); $db->rows['op_rh_v2_solicitudes_acceso_temporal']['status']='USED'; $assert(($service->action($actor,1,$uuid,'cancel')['error']['kind'] ?? '')==='invalid_state' && $db->rows['op_rh_v2_solicitudes_acceso_temporal']['status']==='USED', 'consumed PIN cannot be cancelled');
    $db=$fresh(); $db->conflict=true; $assert(($service->action($actor,1,$uuid,'reject')['error']['kind'] ?? '')==='conflict' && $db->rolledBack && $db->rows['op_rh_v2_solicitudes_acceso_temporal']['status']==='PENDING', 'conditional update conflict rolls back');
    $db=$fresh(); $db->badReadback=true; $assert(($service->action($actor,1,$uuid,'authorize')['error']['kind'] ?? '')==='readback' && $db->rolledBack && !isset($db->rows['op_rh_v2_solicitudes_acceso_temporal']['pin_hash']), 'failed readback rolls back the hash and transition');
    $db=$fresh(); $db->rows['op_rh_personal']['id_estacion']=9; $assert(($service->action($actor,1,$uuid,'authorize')['error']['kind'] ?? '')==='scope' && $db->updates===0, 'foreign worker is denied');
    $db=$fresh(); $db->rows['op_rh_v2_dispositivos']['id_estacion']=9; $assert(($service->action($actor,1,$uuid,'reject')['error']['kind'] ?? '')==='scope' && $db->updates===0, 'foreign device is denied');
    $db=$fresh(); \App\Services\ChecadorAccessService::$mapped=false; $assert(($service->action($actor,1,$uuid,'cancel')['error']['kind'] ?? '')==='scope' && $db->updates===0, 'unmapped locality is denied');
    $db=$fresh(); \App\Services\ChecadorAccessService::$allowed=false; $assert(($service->action($actor,1,$uuid,'authorize')['error']['kind'] ?? '')==='permission_denied' && $db->transactions===0, 'missing permission is denied before transaction');
    $db=$fresh(); $_ENV['CHECADOR_M08_HUMAN_WRITER']='API'; $assert(($service->action($actor,1,$uuid,'authorize')['error']['kind'] ?? '')==='writer_disabled' && $db->transactions===0, 'API mode blocks SQL writer before transaction');
    unset($_ENV['CHECADOR_M08_HUMAN_WRITER']); echo "RESULT: 16 PASS / 0 FAIL\n";
}
