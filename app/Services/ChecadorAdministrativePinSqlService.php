<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Models\Usuario;
use Illuminate\Database\Capsule\Manager as Capsule;
use Throwable;

/** Portal-owned SQL lifecycle for configuration PINs. This is separate from attendance PINs. */
final class ChecadorAdministrativePinSqlService
{
    private const PIN_TABLE = 'op_rh_v2_admin_pin';
    private const SESSION_TABLE = 'op_rh_v2_admin_session';
    private const LIMIT_TABLE = 'op_rh_v2_admin_pin_limit';
    private const AUDIT_TABLE = 'op_rh_v2_m11_configuration_audit';

    /** @return array{ok:bool,state:string,devices?:array,history?:array,can_issue?:bool,can_revoke?:bool,error?:string} */
    public function readForWorkplace(Usuario $actor, int $workplaceId): array
    {
        if ($workplaceId < 1 || !$this->authorized($actor, $workplaceId, 'leer')) {
            return ['ok' => false, 'state' => 'denied', 'error' => 'permission_denied'];
        }
        $stationId = ChecadorAccessService::resolveActiveStationIdForLocality($workplaceId);
        if (!is_int($stationId) || $stationId < 1) return ['ok'=>false,'state'=>'unavailable','error'=>'station_mapping_unresolved'];
        try {
            $db = Capsule::connection('default');
            $this->assertSchemaReadable();
            $station = $db->table('tb_estaciones')->where('id', $stationId)->where('estatus', 0)->first(['id','nombre']);
            if ($station === null) return ['ok'=>false,'state'=>'unavailable','error'=>'station_not_active'];
            $deviceRows = $db->table('op_rh_v2_dispositivos')->where('id_estacion',$stationId)
                ->orderBy('nombre')->orderBy('id')->limit(200)->get(['id','nombre','estado']);
            $devices = [];
            foreach ($deviceRows as $device) {
                $deviceId=(int)$device->id;
                $credentialIds=$db->table('op_rh_v2_dispositivo_credenciales')->where('id_dispositivo',$deviceId)
                    ->where('estado','ACTIVE')->whereNull('revocado_at')->whereRaw('valido_desde <= UTC_TIMESTAMP(6)')
                    ->where(static function($q):void {$q->whereNull('valido_hasta')->orWhereRaw('valido_hasta > UTC_TIMESTAMP(6)');})
                    ->pluck('id')->map(static fn($id):int=>(int)$id)->all();
                $grantCount = count($credentialIds) === 1
                    ? (int)$db->table('op_rh_v2_device_biometric_grants')->where('id_dispositivo',$deviceId)
                        ->where('id_estacion',$stationId)->where('id_credencial',$credentialIds[0])->where('activo',1)
                        ->whereNull('revocado_at_utc')->whereRaw('valido_desde_utc <= UTC_TIMESTAMP(6)')
                        ->where(static function($q):void {$q->whereNull('valido_hasta_utc')->orWhereRaw('valido_hasta_utc > UTC_TIMESTAMP(6)');})->count()
                    : 0;
                $pending=$db->table(self::PIN_TABLE)->where('device_id',$deviceId)->where('station_id',$stationId)
                    ->where('state','PENDING')->where('expires_at_utc','>',Capsule::raw('UTC_TIMESTAMP(6)'))
                    ->orderByDesc('id')->first(['authorization_uuid','issued_at_utc','expires_at_utc']);
                $session=$db->table(self::SESSION_TABLE)->where('device_id',$deviceId)->where('state','ACTIVE')
                    ->whereRaw('expires_at_utc > UTC_TIMESTAMP(6)')
                    ->whereRaw('last_activity_at_utc > DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 120 SECOND)')
                    ->orderByDesc('id')->first(['session_uuid','created_at_utc','expires_at_utc','last_activity_at_utc']);
                $devices[]=[
                    'device_id'=>$deviceId,'device_name'=>(string)$device->nombre,
                    'device_active'=>(int)$device->estado===1,'credential_active'=>count($credentialIds)===1,
                    'biometric_grant_active'=>$grantCount===1,
                    'pending_pin'=>$pending===null?null:[
                        'authorization_id'=>(string)$pending->authorization_uuid,
                        'issued_at_utc'=>(string)$pending->issued_at_utc,'expires_at_utc'=>(string)$pending->expires_at_utc,
                    ],
                    'session'=>$session===null?null:[
                        'session_id'=>(string)$session->session_uuid,'state'=>'ACTIVE',
                        'created_at_utc'=>(string)$session->created_at_utc,'expires_at_utc'=>(string)$session->expires_at_utc,
                        'last_activity_at_utc'=>(string)$session->last_activity_at_utc,
                    ],
                ];
            }
            $nowUtc=(string)$db->selectOne('SELECT UTC_TIMESTAMP(6) AS now_utc')->now_utc;
            $historyRows=$db->table(self::PIN_TABLE.' as p')->join('op_rh_v2_dispositivos as d','d.id','=','p.device_id')
                ->leftJoin('tb_usuarios as u','u.id','=','p.actor_user_id')
                ->leftJoin(self::SESSION_TABLE.' as s','s.pin_id','=','p.id')->where('p.station_id',$stationId)
                ->orderByDesc('p.id')->limit(100)->get([
                    'p.authorization_uuid','p.device_id','d.nombre as device_name','p.actor_user_id','u.usuario as actor_name',
                    'p.state','p.issued_at_utc','p.expires_at_utc','p.consumed_at_utc','p.revoked_at_utc','s.session_uuid','s.state as session_state','s.created_at_utc as session_created_at_utc','s.expires_at_utc as session_expires_at_utc','s.last_activity_at_utc as session_last_activity_at_utc','s.ended_at_utc as session_ended_at_utc',
                ]);
            $history=[];
            foreach($historyRows as $row){
                $state=(string)$row->state;
                if($state==='PENDING' && (string)$row->expires_at_utc <= $nowUtc) $state='EXPIRED';
                $sessionState=$row->session_state===null?null:(string)$row->session_state;
                $idleBoundary=(new \DateTimeImmutable($nowUtc,new \DateTimeZone('UTC')))->modify('-120 seconds')->format('Y-m-d H:i:s.u');
                if($sessionState==='ACTIVE' && ((string)$row->session_expires_at_utc <= $nowUtc || (string)$row->session_last_activity_at_utc <= $idleBoundary)) $sessionState='EXPIRED';
                $history[]=[
                    'authorization_id'=>(string)$row->authorization_uuid,'device_id'=>(int)$row->device_id,
                    'device_name'=>(string)$row->device_name,'actor_user_id'=>(int)$row->actor_user_id,
                    'actor_name'=>trim((string)($row->actor_name??'')) ?: 'No disponible','state'=>$state,
                    'issued_at_utc'=>(string)$row->issued_at_utc,'expires_at_utc'=>(string)$row->expires_at_utc,
                    'consumed_at_utc'=>$row->consumed_at_utc===null?null:(string)$row->consumed_at_utc,
                    'revoked_at_utc'=>$row->revoked_at_utc===null?null:(string)$row->revoked_at_utc,
                    'session_id'=>$row->session_uuid===null?null:(string)$row->session_uuid,
                    'session_state'=>$sessionState,
                    'session_created_at_utc'=>$row->session_created_at_utc===null?null:(string)$row->session_created_at_utc,
                    'session_expires_at_utc'=>$row->session_expires_at_utc===null?null:(string)$row->session_expires_at_utc,
                    'session_ended_at_utc'=>$row->session_ended_at_utc===null?null:(string)$row->session_ended_at_utc,
                ];
            }
            return ['ok'=>true,'state'=>'ready','station_name'=>(string)$station->nombre,'devices'=>$devices,'history'=>$history,
                'can_issue'=>$this->featureEnabled() && $this->authorized($actor,$workplaceId,'emitir'),
                'can_revoke'=>$this->featureEnabled() && $this->authorized($actor,$workplaceId,'revocar'),
                'feature_enabled'=>$this->featureEnabled(),
                'secret_configured'=>$this->secret()!==null];
        } catch(Throwable $exception) {
            self::logFailure('read', $exception);
            return ['ok'=>false,'state'=>'unavailable','error'=>'admin_pin_read_unavailable'];
        }
    }

    /** PIN plaintext is returned only after the SQL transaction commits. */
    public function issue(Usuario $actor,int $workplaceId,int $deviceId):array
    {
        if($workplaceId<1||$deviceId<1)return ['ok'=>false,'error'=>'invalid_input'];
        if(!$this->featureEnabled())return ['ok'=>false,'error'=>'feature_disabled'];
        $secret=$this->secret();
        if($secret===null)return ['ok'=>false,'error'=>'secret_unavailable'];
        if(!$this->authorized($actor,$workplaceId,'emitir'))return ['ok'=>false,'error'=>'permission_denied'];
        $stationId=ChecadorAccessService::resolveActiveStationIdForLocality($workplaceId);
        if(!is_int($stationId)||$stationId<1)return ['ok'=>false,'error'=>'station_mapping_unresolved'];
        $pin=self::generatePin(); $pinVerifier=hash_hmac('sha256',$pin,$secret);
        $pinHash=password_hash($pinVerifier,PASSWORD_DEFAULT);
        if(!is_string($pinHash))return ['ok'=>false,'error'=>'hash_unavailable'];
        $uuid=self::uuidV4(); $phase='begin';
        try{
            $item=Capsule::connection('default')->transaction(function()use($actor,$workplaceId,$deviceId,$stationId,$pinHash,$uuid,&$phase):array{
                $phase='authorization_recheck';
                if(!$this->authorized($actor,$workplaceId,'emitir')||ChecadorAccessService::resolveActiveStationIdForLocality($workplaceId)!==$stationId)throw new \DomainException('permission_denied');
                $db=Capsule::connection('default');
                $phase='device_lock';
                $device=$db->table('op_rh_v2_dispositivos')->where('id',$deviceId)->where('id_estacion',$stationId)->lockForUpdate()->first(['id','nombre','estado']);
                if($device===null)throw new \DomainException('device_not_found');
                if((int)$device->estado!==1)throw new \DomainException('device_inactive');
                $phase='credential_lock';
                $credentials=$db->table('op_rh_v2_dispositivo_credenciales')->where('id_dispositivo',$deviceId)->where('estado','ACTIVE')->whereNull('revocado_at')
                    ->whereRaw('valido_desde <= UTC_TIMESTAMP(6)')
                    ->where(static function($q):void{$q->whereNull('valido_hasta')->orWhereRaw('valido_hasta > UTC_TIMESTAMP(6)');})
                    ->lockForUpdate()->get(['id']);
                if($credentials->count()!==1)throw new \DomainException('credential_unavailable');
                $credentialId=(int)$credentials->first()->id;
                $phase='biometric_grant_lock';
                $grants=$db->table('op_rh_v2_device_biometric_grants')->where('id_dispositivo',$deviceId)->where('id_estacion',$stationId)->where('id_credencial',$credentialId)
                    ->where('activo',1)->whereNull('revocado_at_utc')->whereRaw('valido_desde_utc <= UTC_TIMESTAMP(6)')
                    ->where(static function($q):void{$q->whereNull('valido_hasta_utc')->orWhereRaw('valido_hasta_utc > UTC_TIMESTAMP(6)');})
                    ->lockForUpdate()->get(['id']);
                if($grants->count()!==1)throw new \DomainException('biometric_grant_unavailable');
                // Keep the cross-project lock order aligned with API redemption:
                // device -> limiter -> pending PIN -> session. Re-issuance only
                // creates the limiter row when absent; it never resets counters.
                $phase='limiter_lock';
                $db->table(self::LIMIT_TABLE)->insertOrIgnore([
                    'device_id'=>$deviceId,
                    'window_started_at_utc'=>$db->raw('UTC_TIMESTAMP(6)'),
                    'failed_attempts'=>0,
                    'blocked_until_utc'=>null,
                ]);
                $limit=$db->table(self::LIMIT_TABLE)->where('device_id',$deviceId)->lockForUpdate()->first(['device_id']);
                if($limit===null)throw new \RuntimeException('limiter_lock_unavailable');
                $phase='pending_pin_lock';
                $db->table(self::PIN_TABLE)->where('device_id',$deviceId)->where('state','PENDING')->lockForUpdate()->get(['id']);
                $phase='session_lock';
                $sessions=$db->table(self::SESSION_TABLE)->where('device_id',$deviceId)->where('state','ACTIVE')->lockForUpdate()->get(['id','created_at_utc','expires_at_utc','last_activity_at_utc']);
                $now=(string)$db->selectOne('SELECT UTC_TIMESTAMP(6) AS now_utc')->now_utc;
                $idleBoundary=(new \DateTimeImmutable($now,new \DateTimeZone('UTC')))->modify('-120 seconds')->format('Y-m-d H:i:s.u');
                foreach($sessions as $session){
                    if((string)$session->expires_at_utc<=$now||(string)$session->last_activity_at_utc<=$idleBoundary){
                        $db->table(self::SESSION_TABLE)->where('id',$session->id)->where('state','ACTIVE')->update(['state'=>'EXPIRED','ended_at_utc'=>$now]);
                    }else throw new \DomainException('active_session_exists');
                }
                $db->table(self::PIN_TABLE)->where('device_id',$deviceId)->where('state','PENDING')->update(['state'=>'REVOKED','revoked_at_utc'=>$now]);
                $expires=self::plusSeconds($now,300);
                $phase='pin_insert';
                $id=$db->table(self::PIN_TABLE)->insertGetId([
                    'authorization_uuid'=>$uuid,'device_id'=>$deviceId,'credential_id'=>$credentialId,'station_id'=>$stationId,
                    'locality_id'=>$workplaceId,'actor_user_id'=>(int)$actor->id,'pin_hash'=>$pinHash,'state'=>'PENDING','failed_attempts'=>0,
                    'issued_at_utc'=>$now,'expires_at_utc'=>$expires,'consumed_at_utc'=>null,'revoked_at_utc'=>null,
                ]);
                $this->audit((int)$actor->id,$workplaceId,'ADMIN_PIN',(int)$id,'ISSUE');
                $phase='readback';
                $readback=$db->table(self::PIN_TABLE)->where('id',$id)->where('authorization_uuid',$uuid)
                    ->where('device_id',$deviceId)->where('credential_id',$credentialId)->where('station_id',$stationId)
                    ->where('locality_id',$workplaceId)->where('actor_user_id',(int)$actor->id)->where('state','PENDING')
                    ->first(['id','issued_at_utc','expires_at_utc']);
                if($readback===null||self::plusSeconds((string)$readback->issued_at_utc,300)!==(string)$readback->expires_at_utc)throw new \RuntimeException('readback_mismatch');
                return ['authorization_id'=>$uuid,'device_id'=>$deviceId,'device_name'=>(string)$device->nombre,
                    'expires_at_utc'=>(string)$readback->expires_at_utc];
            });
            return ['ok'=>true,'item'=>$item,'pin'=>$pin];
        }catch(\DomainException $exception){return ['ok'=>false,'error'=>$exception->getMessage()];}
        catch(Throwable $exception){self::logFailure($phase,$exception);return ['ok'=>false,'error'=>'admin_pin_write_unavailable'];}
    }

    public function revokePin(Usuario $actor,int $workplaceId,string $authorizationId):array
    {
        return $this->revoke($actor,$workplaceId,$authorizationId,'pin');
    }
    public function revokeSession(Usuario $actor,int $workplaceId,string $sessionId):array
    {
        return $this->revoke($actor,$workplaceId,$sessionId,'session');
    }

    private function revoke(Usuario $actor,int $workplaceId,string $uuid,string $kind):array
    {
        if(!preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i',$uuid))return ['ok'=>false,'error'=>'invalid_input'];
        $action=$kind==='pin'?'revocar':'revocar';
        if(!$this->featureEnabled())return ['ok'=>false,'error'=>'feature_disabled'];
        if(!$this->authorized($actor,$workplaceId,$action))return ['ok'=>false,'error'=>'permission_denied'];
        $stationId=ChecadorAccessService::resolveActiveStationIdForLocality($workplaceId);
        if(!is_int($stationId)||$stationId<1)return ['ok'=>false,'error'=>'station_mapping_unresolved'];
        try{
            $result=Capsule::connection('default')->transaction(function()use($actor,$workplaceId,$stationId,$uuid,$kind):array{
                if(!$this->authorized($actor,$workplaceId,'revocar')||ChecadorAccessService::resolveActiveStationIdForLocality($workplaceId)!==$stationId)throw new \DomainException('permission_denied');
                $db=Capsule::connection('default'); $now=(string)$db->selectOne('SELECT UTC_TIMESTAMP(6) AS now_utc')->now_utc;
                if($kind==='pin'){
                    $row=$db->table(self::PIN_TABLE)->where('authorization_uuid',$uuid)->where('station_id',$stationId)->where('locality_id',$workplaceId)->lockForUpdate()->first(['id','state']);
                    if($row===null)throw new \DomainException('not_found');
                    if((string)$row->state==='PENDING')$db->table(self::PIN_TABLE)->where('id',$row->id)->where('state','PENDING')->update(['state'=>'REVOKED','revoked_at_utc'=>$now]);
                    elseif((string)$row->state!=='REVOKED')throw new \DomainException('not_revocable');
                    $entity='ADMIN_PIN';$entityId=(int)$row->id;
                }else{
                    $row=$db->table(self::SESSION_TABLE)->where('session_uuid',$uuid)->where('station_id',$stationId)->where('locality_id',$workplaceId)->lockForUpdate()->first(['id','state']);
                    if($row===null)throw new \DomainException('not_found');
                    if((string)$row->state==='ACTIVE')$db->table(self::SESSION_TABLE)->where('id',$row->id)->where('state','ACTIVE')->update(['state'=>'REVOKED','ended_at_utc'=>$now]);
                    elseif((string)$row->state!=='REVOKED')throw new \DomainException('not_revocable');
                    $entity='ADMIN_SESSION';$entityId=(int)$row->id;
                }
                $this->audit((int)$actor->id,$workplaceId,$entity,$entityId,'REVOKE');
                return ['state'=>'REVOKED'];
            });
            return ['ok'=>true,'item'=>$result];
        }catch(\DomainException $exception){return ['ok'=>false,'error'=>$exception->getMessage()];}
        catch(Throwable $exception){self::logFailure('revoke_'.$kind,$exception);return ['ok'=>false,'error'=>'admin_pin_write_unavailable'];}
    }

    private function authorized(Usuario $actor,int $workplaceId,string $action):bool
    {
        $authenticated=Auth::user();
        if(!$authenticated||!Auth::check()||(int)$authenticated->id!==(int)$actor->id)return false;
        $fresh=Usuario::query()->where('id',(int)$actor->id)->where('estatus',0)->first();
        if(!$fresh)return false;
        $position=(int)$fresh->id_puesto;
        // Administrative configuration PINs belong to Checador and use its
        // canonical locality ACL. They must not inherit the separate
        // attendance Temporary Access module permission.
        $scope=ChecadorAccessService::authorize($workplaceId);
        $stationWorkplaceIds=array_map(static fn(array $item):int=>(int)($item['id']??0),$scope['station_workplaces']??[]);
        if(empty($scope['allowed'])
            || !in_array($workplaceId,$stationWorkplaceIds,true))return false;
        if($action==='leer')return in_array($position,[6,13],true);
        if($position!==6)return false;
        $specific=$action==='emitir'?ChecadorV2PermissionService::ADMIN_PIN_ISSUE:ChecadorV2PermissionService::ADMIN_PIN_REVOKE;
        // The administrative-PIN capability authorizes only this session
        // lifecycle. Biometric enrollment has separate capabilities and is
        // not implemented or granted by this service.
        return ChecadorV2PermissionService::allows((int)$fresh->id,$position,$specific);
    }

    private function assertSchemaReadable():void
    {
        $db=Capsule::connection('default');
        $db->select('SELECT id, authorization_uuid, device_id, credential_id, station_id, locality_id, actor_user_id, pin_hash, state, failed_attempts, issued_at_utc, expires_at_utc, consumed_at_utc, revoked_at_utc FROM '.self::PIN_TABLE.' WHERE 1=0');
        $db->select('SELECT id, session_uuid, pin_id, device_id, credential_id, station_id, locality_id, actor_user_id, session_hash, state, created_at_utc, expires_at_utc, last_activity_at_utc, ended_at_utc FROM '.self::SESSION_TABLE.' WHERE 1=0');
        $db->select('SELECT device_id, window_started_at_utc, failed_attempts, blocked_until_utc FROM '.self::LIMIT_TABLE.' WHERE 1=0');
        $db->select('SELECT actor_portal_user_id, permission_key, workplace_id, entity_type, entity_id, action, created_at_utc FROM '.self::AUDIT_TABLE.' WHERE 1=0');
    }

    private function audit(int $actorId,int $workplaceId,string $entity,int $entityId,string $action):void
    {
        Capsule::connection('default')->table(self::AUDIT_TABLE)->insert([
            'actor_portal_user_id'=>$actorId,'permission_key'=>$action==='ISSUE'?ChecadorV2PermissionService::ADMIN_PIN_ISSUE:ChecadorV2PermissionService::ADMIN_PIN_REVOKE,
            'workplace_id'=>$workplaceId,'entity_type'=>$entity,'entity_id'=>$entityId,'action'=>$action,'created_at_utc'=>(string)Capsule::connection('default')->selectOne('SELECT UTC_TIMESTAMP(6) AS now_utc')->now_utc,
        ]);
    }

    private function secret():?string
    {
        $value=(string)($_ENV['CHECADOR_ADMIN_PIN_HMAC_SECRET']??getenv('CHECADOR_ADMIN_PIN_HMAC_SECRET')?:'');
        return strlen($value)>=32?$value:null;
    }
    private function featureEnabled():bool{return (string)($_ENV['CHECADOR_ADMIN_PIN_ENABLED']??getenv('CHECADOR_ADMIN_PIN_ENABLED')?:'')==='1';}
    private static function generatePin():string{return str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);}
    private static function plusSeconds(string $utc,int $seconds):string{$date=new \DateTimeImmutable($utc,new \DateTimeZone('UTC'));return $date->modify('+'.$seconds.' seconds')->format('Y-m-d H:i:s.u');}
    private static function uuidV4():string{$b=random_bytes(16);$b[6]=chr((ord($b[6])&0x0f)|0x40);$b[8]=chr((ord($b[8])&0x3f)|0x80);$h=bin2hex($b);return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20);}
    private static function logFailure(string $stage,Throwable $e):void{$root=$e;while($root->getPrevious() instanceof Throwable)$root=$root->getPrevious();$row=['event'=>'CHECADOR_ADMIN_PIN_SQL_FAILURE','stage'=>$stage,'exception_class'=>get_class($root)];if($root instanceof \PDOException&&is_array($root->errorInfo??null)&&preg_match('/\A[A-Z0-9]{5}\z/',(string)($root->errorInfo[0]??'')))$row['sqlstate']=$root->errorInfo[0];error_log((string)json_encode($row,JSON_UNESCAPED_SLASHES));}
}
