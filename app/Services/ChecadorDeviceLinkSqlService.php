<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Logger;
use App\Models\Usuario;
use Illuminate\Database\Capsule\Manager as Capsule;
use Throwable;

/** Portal-owned SQL lifecycle for one-time device-link tokens and biometric grants. */
final class ChecadorDeviceLinkSqlService
{
    private const TOKEN_PERMISSION = 'checador.dispositivos.vincular';
    private const GRANT_PERMISSION = 'checador.dispositivos.biometria.administrar';

    /** @var callable(Usuario,int,bool):bool */
    private $authorize;
    /** @var callable(int):?int */
    private $stationResolver;
    /** @var callable():string */
    private $utcClock;

    /** Optional seams are limited to isolated tests; production defaults use Portal3 ACL and `default`. */
    public function __construct(?callable $authorize = null, ?callable $stationResolver = null, ?callable $utcClock = null)
    {
        $this->authorize = $authorize ?? self::defaultAuthorization(...);
        $this->stationResolver = $stationResolver ?? static fn(int $workplaceId): ?int =>
            ChecadorAccessService::resolveActiveStationIdForLocality($workplaceId);
        $this->utcClock = $utcClock ?? static function (): string {
            $row = Capsule::connection('default')->selectOne('SELECT UTC_TIMESTAMP(6) AS now_utc');
            $value = is_object($row) ? (string) ($row->now_utc ?? '') : '';
            $date = self::parseUtc($value);
            if ($date === null) throw new \RuntimeException('database_utc_unavailable');
            return $date->format('Y-m-d H:i:s.u');
        };
    }

    /** @return array{ok:bool,status:string,items?:array<int,array<string,mixed>>,error?:string,can_manage?:bool} */
    public function readForWorkplace(Usuario $actor, int $workplaceId): array
    {
        if ($workplaceId < 1 || !(($this->authorize)($actor, $workplaceId, false))) {
            return ['ok' => false, 'status' => 'permission_denied', 'error' => 'permission_denied'];
        }
        $stationId = ($this->stationResolver)($workplaceId);
        if (!is_int($stationId) || $stationId < 1) {
            return ['ok' => false, 'status' => 'mapping_unavailable', 'error' => 'station_mapping_unresolved'];
        }

        try {
            $now = ($this->utcClock)();
            $station = Capsule::connection('default')->table('tb_estaciones')
                ->where('id', $stationId)->where('estatus', 0)->first(['id', 'nombre']);
            if ($station === null || trim((string) $station->nombre) === '') {
                return ['ok' => false, 'status' => 'mapping_unavailable', 'error' => 'station_not_active'];
            }

            $devices = Capsule::connection('default')->table('op_rh_v2_dispositivos')
                ->where('id_estacion', $stationId)->orderBy('nombre')->orderBy('id')
                ->limit(500)->get(['id', 'uuid_dispositivo', 'id_estacion', 'nombre', 'estado']);
            $items = [];
            foreach ($devices as $device) {
                $deviceId = (int) $device->id;
                $credentialQuery = Capsule::connection('default')->table('op_rh_v2_dispositivo_credenciales')
                    ->where('id_dispositivo', $deviceId);
                $activeCredentialCount = (int) (clone $credentialQuery)->where('estado', 'ACTIVE')->count();
                $latestLink = (clone $credentialQuery)->max('creado_at');
                $grantQuery = Capsule::connection('default')->table('op_rh_v2_device_biometric_grants')
                    ->where('id_dispositivo', $deviceId)->where('id_estacion', $stationId)
                    ->whereIn('id_credencial', (clone $credentialQuery)->where('estado', 'ACTIVE')->select('id'))
                    ->where('activo', 1)->whereNull('revocado_at_utc')->where('valido_desde_utc', '<=', $now)
                    ->where(static function ($query) use ($now): void {
                        $query->whereNull('valido_hasta_utc')->orWhere('valido_hasta_utc', '>', $now);
                    });
                $activeGrantCount = (int) (clone $grantQuery)->count();
                $pending = Capsule::connection('default')->table('op_rh_v2_device_link_tokens')
                    ->where('id_dispositivo', $deviceId)->where('id_estacion', $stationId)
                    ->whereNull('consumido_at_utc')->whereNull('revocado_at_utc')->where('expira_at_utc', '>', $now)
                    ->orderByDesc('id')->first(['expira_at_utc']);

                $items[] = [
                    'device' => [
                        'id' => $deviceId,
                        'uuid' => (string) $device->uuid_dispositivo,
                        'name' => (string) $device->nombre,
                        'persisted_status' => (int) $device->estado === 1 ? 'ACTIVE' : 'INACTIVE',
                    ],
                    'station' => ['id' => $stationId, 'name' => (string) $station->nombre],
                    'link_status' => $activeCredentialCount === 1 ? 'LINKED'
                        : ($activeCredentialCount > 1 ? 'CONFLICT' : ($pending !== null ? 'TOKEN_PENDING' : 'NOT_LINKED')),
                    'active_credential_count' => $activeCredentialCount,
                    'last_linked_at_utc' => $latestLink === null ? null : (string) $latestLink,
                    'biometric_grant_status' => $activeGrantCount === 1 ? 'AUTHORIZED'
                        : ($activeGrantCount > 1 ? 'CONFLICT' : 'REVOKED_OR_MISSING'),
                    'active_grant_count' => $activeGrantCount,
                    'pending_token_expires_at_utc' => $pending === null ? null : (string) $pending->expira_at_utc,
                    'can_manage' => (bool) (($this->authorize)($actor, $workplaceId, true)),
                ];
            }

            return ['ok' => true, 'status' => 'ready', 'items' => $items,
                'read_at_utc' => $now,
                'can_manage' => (bool) (($this->authorize)($actor, $workplaceId, true))];
        } catch (Throwable $exception) {
            self::logFailure($exception, 'read', $workplaceId, null);
            return ['ok' => false, 'status' => 'unavailable', 'error' => 'device_link_read_failed'];
        }
    }

    /** Issue or renew a one-time token; the plaintext is returned only after commit. */
    public function issueToken(Usuario $actor, int $workplaceId, int $deviceId): array
    {
        if ($workplaceId < 1 || $deviceId < 1) return ['ok' => false, 'error' => 'invalid_input'];
        if (!(($this->authorize)($actor, $workplaceId, true))) return ['ok' => false, 'error' => 'permission_denied'];
        $stationId = ($this->stationResolver)($workplaceId);
        if (!is_int($stationId) || $stationId < 1) return ['ok' => false, 'error' => 'station_mapping_unresolved'];

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $phase = 'transaction_begin';
        try {
            $result = Capsule::connection('default')->transaction(function () use (
                $actor, $workplaceId, $deviceId, $stationId, $token, $tokenHash, &$phase
            ): array {
                $phase = 'authorization_recheck';
                if (!(($this->authorize)($actor, $workplaceId, true))
                    || ($this->stationResolver)($workplaceId) !== $stationId) {
                    throw new \DomainException('permission_denied');
                }
                // API consumes a token before it locks the device; use the same token→device order.
                Capsule::connection('default')->table('op_rh_v2_device_link_tokens')
                    ->where('id_dispositivo', $deviceId)->where('id_estacion', $stationId)
                    ->whereNull('consumido_at_utc')->whereNull('revocado_at_utc')
                    ->lockForUpdate()->get(['id']);
                $phase = 'device_lock';
                $device = Capsule::connection('default')->table('op_rh_v2_dispositivos')
                    ->where('id', $deviceId)->where('id_estacion', $stationId)->lockForUpdate()->first();
                if ($device === null) throw new \DomainException('device_not_found');
                if ((int) $device->estado !== 1) throw new \DomainException('device_inactive');

                $phase = 'station_lock';
                $station = Capsule::connection('default')->table('tb_estaciones')
                    ->where('id', $stationId)->where('estatus', 0)->lockForUpdate()->first(['id', 'nombre']);
                if ($station === null || trim((string) $station->nombre) === '') throw new \DomainException('station_not_active');

                $now = ($this->utcClock)();
                $phase = 'credential_lock';
                $activeCredentials = Capsule::connection('default')->table('op_rh_v2_dispositivo_credenciales')
                    ->where('id_dispositivo', $deviceId)->where('estado', 'ACTIVE')->lockForUpdate()->get(['id']);
                if ($activeCredentials->count() > 1) throw new \DomainException('multiple_active_device_credentials');
                // Issuing a token never grants biometrics to an existing credential.

                $phase = 'pending_token_invalidation';
                Capsule::connection('default')->table('op_rh_v2_device_link_tokens')
                    ->where('id_dispositivo', $deviceId)->where('id_estacion', $stationId)
                    ->whereNull('consumido_at_utc')->whereNull('revocado_at_utc')->where('expira_at_utc', '>', $now)
                    ->update(['revocado_at_utc' => $now]);

                $phase = 'token_insert';
                $tokenId = Capsule::connection('default')->table('op_rh_v2_device_link_tokens')->insertGetId([
                    'token_sha256' => $tokenHash,
                    'id_dispositivo' => $deviceId,
                    'id_estacion' => $stationId,
                    'emitido_por_usuario_id' => (int) $actor->id,
                    'emitido_at_utc' => $now,
                    'expira_at_utc' => self::plusMinutes($now, 15),
                    'consumido_at_utc' => null,
                    'revocado_at_utc' => null,
                ]);
                $this->audit((int) $actor->id, self::TOKEN_PERMISSION, $workplaceId,
                    'DEVICE_LINK_TOKEN', (int) $tokenId, 'ISSUE_OR_RENEW');

                $phase = 'token_readback';
                $readback = Capsule::connection('default')->table('op_rh_v2_device_link_tokens')
                    ->where('id', $tokenId)->where('token_sha256', $tokenHash)
                    ->where('id_dispositivo', $deviceId)->where('id_estacion', $stationId)
                    ->where('emitido_por_usuario_id', (int) $actor->id)->first(['id', 'emitido_at_utc', 'expira_at_utc']);
                if ($readback === null || self::plusMinutes((string) $readback->emitido_at_utc, 15) !== (string) $readback->expira_at_utc) {
                    throw new \RuntimeException('token_readback_mismatch');
                }
                return [
                    'device_id' => $deviceId,
                    'device_name' => (string) $device->nombre,
                    'station_id' => $stationId,
                    'station_name' => (string) $station->nombre,
                    'expires_at_utc' => (string) $readback->expira_at_utc,
                ];
            });

            return ['ok' => true, 'item' => $result, 'token' => $token];
        } catch (\DomainException $exception) {
            return ['ok' => false, 'error' => $exception->getMessage()];
        } catch (Throwable $exception) {
            self::logFailure($exception, $phase, $workplaceId, $deviceId);
            return ['ok' => false, 'error' => 'device_link_write_failed'];
        }
    }

    public function revokePendingTokens(Usuario $actor, int $workplaceId, int $deviceId): array
    {
        return $this->revoke($actor, $workplaceId, $deviceId, 'token');
    }

    public function revokeBiometricAccess(Usuario $actor, int $workplaceId, int $deviceId): array
    {
        return $this->revoke($actor, $workplaceId, $deviceId, 'biometric');
    }

    public function revokeDevice(Usuario $actor, int $workplaceId, int $deviceId): array
    {
        return $this->revoke($actor, $workplaceId, $deviceId, 'device');
    }

    private function revoke(Usuario $actor, int $workplaceId, int $deviceId, string $mode): array
    {
        if ($workplaceId < 1 || $deviceId < 1) return ['ok' => false, 'error' => 'invalid_input'];
        if (!(($this->authorize)($actor, $workplaceId, true))) return ['ok' => false, 'error' => 'permission_denied'];
        $stationId = ($this->stationResolver)($workplaceId);
        if (!is_int($stationId) || $stationId < 1) return ['ok' => false, 'error' => 'station_mapping_unresolved'];
        try {
            $result = Capsule::connection('default')->transaction(function () use ($actor, $workplaceId, $deviceId, $stationId, $mode): array {
                if (!(($this->authorize)($actor, $workplaceId, true)) || ($this->stationResolver)($workplaceId) !== $stationId) throw new \DomainException('permission_denied');
                $tokens = Capsule::connection('default')->table('op_rh_v2_device_link_tokens')->where('id_dispositivo', $deviceId)
                    ->whereNull('consumido_at_utc')->whereNull('revocado_at_utc')->lockForUpdate()->get(['id']);
                $device = Capsule::connection('default')->table('op_rh_v2_dispositivos')->where('id', $deviceId)
                    ->where('id_estacion', $stationId)->lockForUpdate()->first(['id', 'nombre']);
                if ($device === null) throw new \DomainException('device_not_found');
                $now = ($this->utcClock)();
                if ($tokens->isNotEmpty()) Capsule::connection('default')->table('op_rh_v2_device_link_tokens')->whereIn('id', $tokens->pluck('id')->all())->update(['revocado_at_utc' => $now]);
                if ($mode !== 'token') {
                    Capsule::connection('default')->table('op_rh_v2_device_biometric_grants')->where('id_dispositivo', $deviceId)
                        ->where('activo', 1)->whereNull('revocado_at_utc')->update(['activo' => 0, 'revocado_at_utc' => $now, 'valido_hasta_utc' => $now]);
                    $this->invalidateAdministrativePinState($deviceId, $stationId, $now);
                }
                if ($mode === 'device') {
                    Capsule::connection('default')->table('op_rh_v2_dispositivo_credenciales')->where('id_dispositivo', $deviceId)
                        ->where('estado', 'ACTIVE')->update(['estado' => 'REVOKED', 'revocado_at' => $now]);
                }
                $this->audit((int)$actor->id, self::TOKEN_PERMISSION, $workplaceId, 'DEVICE_LINK', $deviceId, strtoupper('REVOKE_' . $mode));
                return ['device_id' => $deviceId, 'device_name' => (string)$device->nombre, 'revoked_at_utc' => $now];
            });
            return ['ok' => true, 'item' => $result];
        } catch (\DomainException $exception) {
            return ['ok' => false, 'error' => $exception->getMessage()];
        } catch (Throwable $exception) {
            self::logFailure($exception, 'revoke_' . $mode, $workplaceId, $deviceId);
            return ['ok' => false, 'error' => 'device_link_write_failed'];
        }
    }

    /** Revoking biometric access or unlinking a device must invalidate its admin PIN and sessions. */
    private function invalidateAdministrativePinState(int $deviceId, int $stationId, string $now): void
    {
        $db = Capsule::connection('default');
        $schema = $db->getSchemaBuilder();
        $hasPins = $schema->hasTable('op_rh_v2_admin_pin');
        $hasSessions = $schema->hasTable('op_rh_v2_admin_session');
        if (!$hasPins && !$hasSessions) return;
        if (!$hasPins || !$hasSessions) throw new \RuntimeException('administrative_pin_schema_incomplete');

        $pins = $db->table('op_rh_v2_admin_pin')->where('device_id', $deviceId)->where('station_id', $stationId)
            ->where('state', 'PENDING')->lockForUpdate()->get(['id', 'locality_id', 'actor_user_id']);
        foreach ($pins as $pin) {
            $db->table('op_rh_v2_admin_pin')->where('id', (int)$pin->id)->where('state', 'PENDING')
                ->update(['state'=>'REVOKED','revoked_at_utc'=>$now]);
            $this->auditAdminInvalidation((int)$pin->actor_user_id,(int)$pin->locality_id,'ADMIN_PIN',(int)$pin->id);
        }
        $sessions = $db->table('op_rh_v2_admin_session')->where('device_id', $deviceId)->where('station_id', $stationId)
            ->where('state', 'ACTIVE')->lockForUpdate()->get(['id', 'locality_id', 'actor_user_id']);
        foreach ($sessions as $session) {
            $db->table('op_rh_v2_admin_session')->where('id', (int)$session->id)->where('state', 'ACTIVE')
                ->update(['state'=>'REVOKED','ended_at_utc'=>$now]);
            $this->auditAdminInvalidation((int)$session->actor_user_id,(int)$session->locality_id,'ADMIN_SESSION',(int)$session->id);
        }
    }

    private function auditAdminInvalidation(int $actorId,int $workplaceId,string $entity,int $entityId):void
    {
        Capsule::connection('default')->table('op_rh_v2_m11_configuration_audit')->insert([
            'actor_portal_user_id'=>$actorId,
            'permission_key'=>ChecadorV2PermissionService::ADMIN_PIN_REVOKE,
            'workplace_id'=>$workplaceId,
            'entity_type'=>$entity,
            'entity_id'=>$entityId,
            'action'=>'CREDENTIAL_OR_GRANT_REVOKED',
            'created_at_utc'=>(string)Capsule::connection('default')->selectOne('SELECT UTC_TIMESTAMP(6) AS now_utc')->now_utc,
        ]);
    }

    private function audit(int $actorId, string $permission, int $workplaceId, string $entity, int $entityId, string $action): void
    {
        Capsule::connection('default')->table('op_rh_v2_m11_configuration_audit')->insert([
            'actor_portal_user_id' => $actorId,
            'permission_key' => $permission,
            'workplace_id' => $workplaceId,
            'entity_type' => $entity,
            'entity_id' => $entityId,
            'action' => $action,
            'created_at_utc' => ($this->utcClock)(),
        ]);
    }

    private static function defaultAuthorization(Usuario $actor, int $workplaceId, bool $manage): bool
    {
        $authenticated = Auth::user();
        if ($authenticated === null || !Auth::check() || (int) $authenticated->id !== (int) $actor->id) return false;
        $access = ChecadorAccessService::authorizeAdministrativeDevices($workplaceId);
        if (!($access['allowed'] ?? false)) return false;
        $position = (int) ($access['position_id'] ?? 0);
        if (!$manage) return in_array($position, [6, 13], true);
        return $position === 6
            && ChecadorV2PermissionService::allows((int)$actor->id, $position, self::TOKEN_PERMISSION)
            && ChecadorV2PermissionService::allows((int)$actor->id, $position, self::GRANT_PERMISSION);
    }

    private static function plusMinutes(string $utc, int $minutes): string
    {
        $date = self::parseUtc($utc);
        if ($date === null) throw new \InvalidArgumentException('invalid_utc_datetime');
        return $date->modify('+' . $minutes . ' minutes')->format('Y-m-d H:i:s.u');
    }

    private static function parseUtc(string $value): ?\DateTimeImmutable
    {
        try {
            $date = new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
            return $date->setTimezone(new \DateTimeZone('UTC'));
        } catch (Throwable) {
            return null;
        }
    }

    private static function logFailure(Throwable $exception, string $phase, int $workplaceId, ?int $deviceId): void
    {
        try {
            Logger::error('Checador M11 device-link SQL operation failed', [
                'phase' => $phase,
                'exception_class' => $exception::class,
                'sql_state' => $exception instanceof \PDOException && isset($exception->errorInfo[0])
                    ? (string) $exception->errorInfo[0] : null,
                'workplace_id' => $workplaceId,
                'device_id' => $deviceId,
            ]);
        } catch (Throwable) {
            error_log('Checador M11 device-link diagnostic could not be written');
        }
    }
}
