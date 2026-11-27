<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Models\Usuario;
use Illuminate\Database\Capsule\Manager as Capsule;
use Throwable;

/** M08: SQL read and prepared administrative writer. Device operations remain with API. */
final class ChecadorTemporaryAccessSqlService
{
    public static function writerMode(): string
    {
        $value = $_ENV['CHECADOR_M08_HUMAN_WRITER'] ?? getenv('CHECADOR_M08_HUMAN_WRITER');
        return $value === false || $value === null ? 'API' : (in_array($value, ['API', 'PORTAL3'], true) ? $value : 'INVALID');
    }

    private static function allowed(Usuario $user, int $locality, string $action): bool
    {
        $current = Auth::user();
        return $current && (int) $current->id === (int) $user->id && $locality > 0
            && (ChecadorAccessService::authorizeTemporaryAccess($locality, $action)['allowed'] ?? false) === true;
    }

    public function listForLocality(Usuario $user, int $locality): array
    {
        if (!self::allowed($user, $locality, 'leer')) return ['ok'=>false, 'error'=>['kind'=>'permission_denied']];
        try {
            $db = Capsule::connection('default');
            $local = $db->table('op_rh_localidades')->where('id', $locality)->first(['numlista']);
            if (!$local || (int)$local->numlista < 1 || $db->table('op_rh_localidades')->where('numlista', $local->numlista)->count() !== 1) throw new \RuntimeException('mapping');
            $stations = $db->table('tb_estaciones')->where('numlista', $local->numlista)->get(['id']);
            if ($stations->count() !== 1) throw new \RuntimeException('mapping');
            $station = (int)$stations[0]->id;
            if (!ChecadorAccessService::temporaryAccessRequestBelongsToLocality($station, $locality)) throw new \RuntimeException('mapping');
            $rows = $db->table('op_rh_v2_solicitudes_acceso_temporal as r')
                ->join('op_rh_v2_dispositivos as d', 'd.id', '=', 'r.id_dispositivo')
                ->leftJoin('op_rh_personal as p', 'p.id', '=', 'r.id_personal_resuelto')
                ->where('r.id_estacion', $station)->where('r.status', 'PENDING')
                ->whereRaw('r.request_expires_at_utc > UTC_TIMESTAMP(6)')
                ->orderBy('r.requested_at_utc')->orderBy('r.id')
                ->get(['r.request_uuid','r.id_personal_resuelto','p.nombre_completo as nombre_trabajador','r.id_estacion','d.nombre as dispositivo_nombre','r.reason','r.requested_at_utc','r.request_expires_at_utc']);
            return ['ok'=>true, 'requests'=>array_map(static fn($row)=>(array)$row, $rows->all())];
        } catch (Throwable $e) {
            return ['ok'=>false, 'error'=>['kind'=>'sql_unavailable']];
        }
    }

    /** Current persisted state, not an audit trail of transitions. */
    public function historyForLocality(Usuario $user, int $locality, array $filters = [], int $page = 1, int $perPage = 25): array
    {
        if (!self::allowed($user, $locality, 'leer')) return ['ok'=>false,'error'=>['kind'=>'permission_denied']];
        $states = ['PENDING','AUTHORIZED','REJECTED','CANCELLED','EXPIRED','USED','LOCKED'];
        $status = trim((string)($filters['status'] ?? ''));
        $search = trim((string)($filters['search'] ?? ''));
        $worker = $filters['worker_id'] ?? null;
        $from = trim((string)($filters['from'] ?? ''));
        $to = trim((string)($filters['to'] ?? ''));
        $sort = (string)($filters['sort'] ?? 'date');
        $direction = strtolower((string)($filters['direction'] ?? 'desc'));
        $columns = ['date'=>'r.requested_at_utc','worker'=>'p.nombre_completo','status'=>'r.status'];
        $dateValid = static function (string $date): bool {
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('UTC'));
            return $parsed !== false && $parsed->format('Y-m-d') === $date;
        };
        if ($page < 1 || $perPage < 1 || $perPage > 100 || strlen($search) > 100
            || ($status !== '' && !in_array($status, $states, true))
            || ($worker !== null && $worker !== '' && (!ctype_digit((string)$worker) || (int)$worker < 1))
            || ($from !== '' && !$dateValid($from)) || ($to !== '' && !$dateValid($to))
            || ($from !== '' && $to !== '' && $from > $to) || !isset($columns[$sort]) || !in_array($direction, ['asc','desc'], true)) {
            return ['ok'=>false,'error'=>['kind'=>'invalid_filter']];
        }
        try {
            $db = Capsule::connection('default');
            $local = $db->table('op_rh_localidades')->where('id',$locality)->first(['numlista']);
            if (!$local || (int)$local->numlista < 1 || $db->table('op_rh_localidades')->where('numlista',$local->numlista)->count() !== 1) throw new \RuntimeException('mapping');
            $stations = $db->table('tb_estaciones')->where('numlista',$local->numlista)->get(['id']);
            if ($stations->count() !== 1) throw new \RuntimeException('mapping');
            $station = (int)$stations[0]->id;
            if (!ChecadorAccessService::temporaryAccessRequestBelongsToLocality($station,$locality)) throw new \RuntimeException('mapping');
            $query = $db->table('op_rh_v2_solicitudes_acceso_temporal as r')
                ->join('op_rh_v2_dispositivos as d','d.id','=','r.id_dispositivo')
                ->leftJoin('op_rh_personal as p','p.id','=','r.id_personal_resuelto')
                ->where('r.id_estacion',$station);
            if ($status !== '') $query->where('r.status',$status);
            if ($worker !== null && $worker !== '') $query->where('r.id_personal_resuelto',(int)$worker);
            if ($from !== '') $query->where('r.requested_at_utc','>=',$from.' 00:00:00');
            if ($to !== '') $query->where('r.requested_at_utc','<',(new \DateTimeImmutable($to,new \DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d').' 00:00:00');
            if ($search !== '') $query->where(function ($q) use ($search) { $q->where('p.nombre_completo','like','%'.$search.'%')->orWhere('d.nombre','like','%'.$search.'%'); });
            $total = (clone $query)->count();
            $rows = $query->orderBy($columns[$sort],$direction)->orderBy('r.id',$direction)
                ->offset(($page-1)*$perPage)->limit($perPage)->get([
                    'r.request_uuid','r.id_personal_resuelto','p.nombre_completo as nombre_trabajador','r.id_estacion',
                    'd.nombre as dispositivo_nombre','r.reason','r.status','r.requested_at_utc','r.request_expires_at_utc',
                    'r.authorized_by_user_id','r.authorized_at_utc','r.rejected_by_user_id','r.rejected_at_utc',
                    'r.cancelled_by_user_id','r.cancelled_at_utc','r.pin_expires_at_utc','r.used_at_utc'
                ]);
            return ['ok'=>true,'items'=>array_map(static fn($row)=>(array)$row,$rows->all()),'total'=>$total,'page'=>$page,'per_page'=>$perPage];
        } catch (Throwable $e) {
            return ['ok'=>false,'error'=>['kind'=>'sql_unavailable']];
        }
    }

    public function action(Usuario $user, int $locality, string $uuid, string $action): array
    {
        if (self::writerMode() !== 'PORTAL3') return ['ok'=>false,'error'=>['kind'=>'writer_disabled']];
        if (!self::allowed($user, $locality, 'editar') || !in_array($action, ['authorize','reject','cancel'], true)
            || !preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $uuid)) return ['ok'=>false,'error'=>['kind'=>'permission_denied']];
        try {
            $db = Capsule::connection('default');
            return $db->transaction(function () use ($db, $user, $locality, $uuid, $action): array {
                $hint = $db->table('op_rh_v2_solicitudes_acceso_temporal')->where('request_uuid',$uuid)->first(['id_dispositivo']);
                if (!$hint) throw new \RuntimeException('not_found');
                $device = $db->table('op_rh_v2_dispositivos')->where('id', $hint->id_dispositivo)->lockForUpdate()->first(['id','id_estacion','estado']);
                $request = $db->table('op_rh_v2_solicitudes_acceso_temporal')->where('request_uuid',$uuid)->lockForUpdate()->first(['id','request_uuid','id_dispositivo','id_estacion','id_personal_resuelto','referencia_externa_personal','status','request_expires_at_utc','pin_expires_at_utc']);
                if (!$device || !$request || (int)$device->id !== (int)$request->id_dispositivo || (int)$device->estado !== 1 || (int)$device->id_estacion !== (int)$request->id_estacion || !self::allowed($user,$locality,'editar') || !ChecadorAccessService::temporaryAccessRequestBelongsToLocality((int)$request->id_estacion,$locality)) throw new \RuntimeException('scope');
                $station = $db->table('tb_estaciones')->where('id',$request->id_estacion)->lockForUpdate()->first(['estatus']);
                $worker = $db->table('op_rh_personal')->where('id',$request->id_personal_resuelto)->lockForUpdate()->first(['id','estado','id_estacion']);
                $identity = $db->table('op_rh_v2_dispositivo_identidad_personal')->where('id_dispositivo',$device->id)->where('referencia_externa_personal',$request->referencia_externa_personal)->where('es_activo',1)->whereRaw('valido_desde <= UTC_TIMESTAMP(6)')->whereRaw('(valido_hasta IS NULL OR valido_hasta > UTC_TIMESTAMP(6))')->lockForUpdate()->get(['id_personal']);
                if (!$station || (int)$station->estatus !== 0 || !$worker || (int)$worker->estado !== 1 || (int)$worker->id_estacion !== (int)$request->id_estacion || $identity->count() !== 1 || (int)$identity[0]->id_personal !== (int)$worker->id) throw new \RuntimeException('scope');
                $clock = $db->selectOne('SELECT UTC_TIMESTAMP() AS now_utc, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 15 MINUTE) AS pin_expiry');
                $now = (string)$clock->now_utc;
                $valid = $request->status === 'PENDING' && (string)$request->request_expires_at_utc > $now;
                if ($action === 'cancel' && $request->status === 'AUTHORIZED') $valid = $request->pin_expires_at_utc !== null && (string)$request->pin_expires_at_utc > $now;
                if (!$valid) throw new \RuntimeException('invalid_state');
                $values = [];
                $pin = null;
                if ($action === 'authorize') {
                    $pin = str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
                    $hash = password_hash($pin, PASSWORD_BCRYPT);
                    if (!is_string($hash)) throw new \RuntimeException('hash');
                    $values = ['status'=>'AUTHORIZED','pin_hash'=>$hash,'pin_failed_attempts'=>0,'authorized_by_user_id'=>(int)$user->id,'authorized_at_utc'=>$now,'pin_expires_at_utc'=>$clock->pin_expiry];
                } elseif ($action === 'reject') $values = ['status'=>'REJECTED','rejected_by_user_id'=>(int)$user->id,'rejected_at_utc'=>$now];
                else $values = ['status'=>'CANCELLED','cancelled_by_user_id'=>(int)$user->id,'cancelled_at_utc'=>$now];
                if ($db->table('op_rh_v2_solicitudes_acceso_temporal')->where('id',$request->id)->where('status',$request->status)->update($values) !== 1) throw new \RuntimeException('conflict');
                $saved = $db->table('op_rh_v2_solicitudes_acceso_temporal')->where('id',$request->id)->first(['status','pin_expires_at_utc','pin_hash','authorized_by_user_id','rejected_by_user_id','cancelled_by_user_id']);
                $actorColumn = ['authorize'=>'authorized_by_user_id','reject'=>'rejected_by_user_id','cancel'=>'cancelled_by_user_id'][$action];
                if (!$saved || $saved->status !== $values['status'] || (int)$saved->$actorColumn !== (int)$user->id || ($pin !== null && ((string)$saved->pin_expires_at_utc !== (string)$clock->pin_expiry || !password_verify($pin, (string)$saved->pin_hash)))) throw new \RuntimeException('readback');
                $data = ['status'=>$saved->status];
                if ($pin !== null) { $data['temporary_pin']=$pin; $data['pin_expires_at_utc']=(string)$saved->pin_expires_at_utc; }
                return ['ok'=>true,'data'=>$data];
            });
        } catch (Throwable $e) {
            $kind = $e instanceof \RuntimeException && in_array($e->getMessage(), ['not_found','scope','invalid_state','conflict','readback'], true)
                ? $e->getMessage() : 'sql_unavailable';
            return ['ok'=>false,'error'=>['kind'=>$kind]];
        }
    }
}
