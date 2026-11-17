<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Operativo\RhLocalidadesPerfil;
use App\Models\Operativo\RhPuestos;
use App\Models\Usuario;
use Illuminate\Database\Capsule\Manager as Capsule;
use Throwable;

/** Narrow M11 writers for the existing, approved Checador configuration stores. */
final class ChecadorConfigurationWriteService
{
    /** @return array{ok:bool,item?:array{id:int,name:string},error?:string} */
    public function savePosition(Usuario $actor, ?int $workplaceId, ?int $positionId, string $name): array
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100 || preg_match('/[\x00-\x1F\x7F]/u', $name)) {
            return ['ok' => false, 'error' => 'invalid_position_name'];
        }
        if (!ChecadorDailyReadService::m11ConfigurationPostOperationsEnabled()) {
            return ['ok' => false, 'error' => 'm11_write_disabled'];
        }
        if (!(ChecadorAccessService::authorizePositionCatalog($workplaceId, 'editar')['allowed'] ?? false)) {
            return ['ok' => false, 'error' => 'permission_denied'];
        }

        try {
            $item = Capsule::connection()->transaction(function () use ($actor, $workplaceId, $positionId, $name): array {
                if (RhPuestos::query()->where('status', 1)->whereRaw('LOWER(puesto) = LOWER(?)', [$name])
                    ->when($positionId !== null, static fn($query) => $query->where('id', '<>', $positionId))->exists()) {
                    throw new \DomainException('duplicate_position');
                }

                if ($positionId === null) {
                    $position = RhPuestos::query()->create(['puesto' => $name, 'status' => 1]);
                    $action = 'CREATE';
                } else {
                    $position = RhPuestos::query()->where('id', $positionId)->where('status', 1)->lockForUpdate()->first();
                    if ($position === null) throw new \DomainException('position_not_found');
                    $position->puesto = $name;
                    $position->save();
                    $action = 'UPDATE';
                }

                $this->audit((int) $actor->id, ChecadorV2PermissionService::POSITION_CATALOG_EDIT,
                    null, 'position', (int) $position->id, $action);
                $readback = RhPuestos::query()->where('id', (int) $position->id)->where('status', 1)->first(['id', 'puesto']);
                if ($readback === null || (string) $readback->puesto !== $name) {
                    throw new \RuntimeException('position_readback_mismatch');
                }
                return ['id' => (int) $readback->id, 'name' => (string) $readback->puesto];
            });
            return ['ok' => true, 'item' => $item];
        } catch (\DomainException $exception) {
            return ['ok' => false, 'error' => $exception->getMessage()];
        } catch (Throwable) {
            return ['ok' => false, 'error' => 'position_write_failed'];
        }
    }

    /** @return array{ok:bool,profile?:array{id:int,username:string},error?:string} */
    public function updateReaderProfile(Usuario $actor, int $workplaceId, int $profileId, string $username, string $newPassword): array
    {
        $username = trim($username);
        if ($workplaceId < 1 || $profileId < 1 || $username === '' || mb_strlen($username) > 120
            || preg_match('/[\x00-\x1F\x7F]/u', $username) || strlen($newPassword) > 512) {
            return ['ok' => false, 'error' => 'invalid_profile_input'];
        }
        if (!ChecadorDailyReadService::m11ConfigurationPostOperationsEnabled()) {
            return ['ok' => false, 'error' => 'm11_write_disabled'];
        }
        if (!(ChecadorAccessService::authorizeReaderProfiles($workplaceId, 'editar')['allowed'] ?? false)) {
            return ['ok' => false, 'error' => 'permission_denied'];
        }
        $physicalStationId = ChecadorAccessService::resolveActiveStationIdForLocality($workplaceId);
        if ($physicalStationId === null) return ['ok' => false, 'error' => 'station_mapping_unresolved'];

        try {
            $profile = Capsule::connection()->transaction(function () use ($actor, $workplaceId, $physicalStationId, $profileId, $username, $newPassword): array {
                $profile = RhLocalidadesPerfil::query()->where('id', $profileId)
                    ->where('id_estacion', $physicalStationId)->where('status', 1)->lockForUpdate()->first();
                if ($profile === null) throw new \DomainException('profile_not_found');

                // The old store uses a reversible compatibility encoding. Only its existing codec is reused;
                // credentials remain write-only and never enter projections, audit, feedback, or logs.
                $encodedUsername = \App\Services\BiometricosService::encrypt($username);
                $duplicate = RhLocalidadesPerfil::query()->where('id_estacion', $physicalStationId)
                    ->where('status', 1)->where('id', '<>', $profileId)->where('usuario', $encodedUsername)->exists();
                if ($duplicate) throw new \DomainException('duplicate_profile_username');

                $profile->usuario = $encodedUsername;
                if ($newPassword !== '') $profile->password = \App\Services\BiometricosService::encrypt($newPassword);
                $profile->save();

                $this->audit((int) $actor->id, ChecadorV2PermissionService::READER_PROFILE_EDIT,
                    $workplaceId, 'reader_profile', $profileId, 'ROTATE_OR_UPDATE');
                $readback = RhLocalidadesPerfil::query()->where('id', $profileId)->where('id_estacion', $physicalStationId)
                    ->where('status', 1)->first(['id', 'usuario']);
                if ($readback === null || \App\Services\BiometricosService::decrypt((string) $readback->usuario) !== $username) {
                    throw new \RuntimeException('profile_readback_mismatch');
                }
                return ['id' => (int) $readback->id, 'username' => $username];
            });
            return ['ok' => true, 'profile' => $profile];
        } catch (\DomainException $exception) {
            return ['ok' => false, 'error' => $exception->getMessage()];
        } catch (Throwable) {
            return ['ok' => false, 'error' => 'profile_write_failed'];
        }
    }

    private function audit(int $actorId, string $permission, ?int $workplaceId, string $entityType, int $entityId, string $action): void
    {
        Capsule::table('op_rh_v2_m11_configuration_audit')->insert([
            'actor_portal_user_id' => $actorId,
            'permission_key' => $permission,
            'workplace_id' => $workplaceId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'created_at_utc' => gmdate('Y-m-d H:i:s'),
        ]);
    }
}
