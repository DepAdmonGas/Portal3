<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Models\Estacion;
use App\Models\ModuloDptoOperativo;
use App\Models\ModuloSubDptoOperativo;
use App\Models\Operativo\RhLocalidad;
use Throwable;

/** Authorization boundary for the Checador V2 shell only. */
final class ChecadorAccessService
{
    public const MODULE_KEY = 'checador';
    public const RESOLUTION_MODULE_KEY = 'checador-v2-incidencias-resolucion';
    public const AUTHORIZATION_MODULE_KEY = 'checador-v2-incidencias-autorizacion';
    private const PARENT_KEY = 'recursos-humanos';

    /**
     * Resolve menu grant and server-authorized workplace scope. No client
     * station value is used to derive or expand the scope.
     *
     * @return array{allowed: bool, reason: string, workplaces: array<int, array{id:int,nombre:string}>}
     */
    public static function authorize(?int $requestedLocalityId = null): array
    {
        $deny = static fn(string $reason): array => [
            'allowed' => false,
            'reason' => $reason,
            'workplaces' => [],
        ];

        try {
            $user = Auth::user();
            if (!$user || !Auth::check()) {
                return $deny('unauthenticated');
            }

            $parent = ModuloDptoOperativoService::getPermiso((int) $user->id, self::PARENT_KEY);
            if (empty($parent)
                || empty($parent['leer'])
                || !ModuloDptoOperativo::query()
                    ->where('id', (int) ($parent['id_modulo'] ?? 0))
                    ->where('clave', self::PARENT_KEY)
                    ->where('activo', 1)
                    ->exists()) {
                return $deny('parent_module_denied');
            }

            $activeSubmodule = ModuloSubDptoOperativo::query()
                ->where('id_modulo', (int) $parent['id_modulo'])
                ->where('clave', self::MODULE_KEY)
                ->where('activo', 1)
                ->exists();
            $hasSubmodule = false;
            foreach ($parent['submenus'] ?? [] as $submenu) {
                if (($submenu['clave'] ?? null) === self::MODULE_KEY) {
                    $hasSubmodule = true;
                    break;
                }
            }
            if (!$activeSubmodule || !$hasSubmodule) {
                return $deny('submodule_denied');
            }

            // An absent configuration, empty scope, malformed mapping, or
            // lookup error must never be interpreted as unrestricted access.
            if (ModuleStationService::getConfig(self::MODULE_KEY) === null) {
                return $deny('station_configuration_missing');
            }
            $configuredStations = ModuleStationService::getAvailableStations(self::MODULE_KEY);
            if ($configuredStations === []) {
                return $deny('empty_station_scope');
            }

            $workplaces = self::mapToUniqueLocalities($configuredStations);
            if ($workplaces === []) {
                return $deny('station_mapping_unavailable');
            }

            if ($requestedLocalityId !== null) {
                $ids = array_column($workplaces, 'id');
                if (!in_array($requestedLocalityId, $ids, true)) {
                    return $deny('requested_station_out_of_scope');
                }
                $workplaces = array_values(array_filter(
                    $workplaces,
                    static fn(array $workplace): bool => $workplace['id'] === $requestedLocalityId
                ));
            }

            return [
                'allowed' => true,
                'reason' => 'authorized',
                'workplaces' => $workplaces,
            ];
        } catch (Throwable) {
            return $deny('authorization_lookup_failed');
        }
    }

    /**
     * Resolve a lifecycle capability and the same canonical workplace scope
     * used by the Checador shell. Missing or malformed dedicated ACLs deny.
     *
     * @return array{allowed:bool,reason:string,workplaces:array<int,array{id:int,nombre:string}>,namespace?:string}
     */
    public static function authorizeLifecycle(?int $requestedLocalityId, string $action): array
    {
        $deny = static fn(string $reason): array => [
            'allowed' => false,
            'reason' => $reason,
            'workplaces' => [],
        ];
        if (!in_array($action, ['resolve', 'authorize', 'reject', 'revoke'], true)) {
            return $deny('unknown_lifecycle_action');
        }

        $scope = self::authorize($requestedLocalityId);
        if (!$scope['allowed']) {
            return $scope;
        }

        try {
            $user = Auth::user();
            if (!$user) return $deny('unauthenticated');
            $namespace = $action === 'resolve'
                ? self::RESOLUTION_MODULE_KEY
                : self::AUTHORIZATION_MODULE_KEY;
            $permissions = ModuloDptoOperativoService::getPermiso((int) $user->id, $namespace);
            $allowed = $action === 'revoke'
                ? !empty($permissions['eliminar']) || !empty($permissions['editar'])
                : !empty($permissions['editar']);
            if (!$allowed) {
                return $deny('lifecycle_permission_denied');
            }
            return [
                'allowed' => true,
                'reason' => 'authorized',
                'workplaces' => $scope['workplaces'],
                'namespace' => $namespace,
            ];
        } catch (Throwable) {
            return $deny('authorization_lookup_failed');
        }
    }

    /**
     * Administrative evaluation is executable with either dedicated
     * resolution or authorization edit permission. The API repeats this
     * check; Portal3 mirrors it before sending the command.
     *
     * @return array{allowed:bool,reason:string,workplaces:array<int,array{id:int,nombre:string}>}
     */
    public static function authorizeEvaluation(?int $requestedLocalityId = null): array
    {
        $scope = self::authorize($requestedLocalityId);
        if (!($scope['allowed'] ?? false)) {
            return $scope;
        }
        try {
            $user = Auth::user();
            if (!$user) {
                return ['allowed' => false, 'reason' => 'unauthenticated', 'workplaces' => []];
            }
            $resolution = ModuloDptoOperativoService::getPermiso((int) $user->id, self::RESOLUTION_MODULE_KEY);
            $authorization = ModuloDptoOperativoService::getPermiso((int) $user->id, self::AUTHORIZATION_MODULE_KEY);
            if (empty($resolution['editar']) && empty($authorization['editar'])) {
                return ['allowed' => false, 'reason' => 'evaluation_permission_denied', 'workplaces' => []];
            }
            return [
                'allowed' => true,
                'reason' => 'authorized',
                'workplaces' => $scope['workplaces'],
            ];
        } catch (Throwable) {
            return ['allowed' => false, 'reason' => 'authorization_lookup_failed', 'workplaces' => []];
        }
    }

    /**
     * Explicit final FALTA authorization requires the dedicated
     * authorization namespace; evaluation permission alone is insufficient.
     */
    public static function authorizeFalta(?int $requestedLocalityId = null): array
    {
        $scope = self::authorize($requestedLocalityId);
        if (!($scope['allowed'] ?? false)) {
            return $scope;
        }
        try {
            $user = Auth::user();
            if (!$user) {
                return ['allowed' => false, 'reason' => 'unauthenticated', 'workplaces' => []];
            }
            $authorization = ModuloDptoOperativoService::getPermiso((int) $user->id, self::AUTHORIZATION_MODULE_KEY);
            if (empty($authorization['editar'])) {
                return ['allowed' => false, 'reason' => 'falta_permission_denied', 'workplaces' => []];
            }
            return [
                'allowed' => true,
                'reason' => 'authorized',
                'workplaces' => $scope['workplaces'],
            ];
        } catch (Throwable) {
            return ['allowed' => false, 'reason' => 'authorization_lookup_failed', 'workplaces' => []];
        }
    }

    /**
     * ModuleStationService returns IDs in the module's configured station
     * space. Resolve the other catalog only through a one-to-one numlista.
     *
     * @param array<int, array{id:int}> $stations
     * @return array<int, array{id:int,nombre:string}>
     */
    private static function mapToUniqueLocalities(array $stations): array
    {
        $sourceIsLocality = MultiestacionService::getIdSpaceForModule(self::MODULE_KEY)
            === MultiestacionService::TABLA_RH_LOCALIDADES;
        $mapped = [];

        foreach ($stations as $station) {
            $sourceId = (int) ($station['id'] ?? 0);
            if ($sourceId < 1) {
                return [];
            }

            if ($sourceIsLocality) {
                $locality = RhLocalidad::query()->where('id', $sourceId)->first();
                if (!$locality) {
                    continue;
                }
                $numlista = (int) ($locality->numlista ?? 0);
                if ($numlista < 1
                    || RhLocalidad::query()->where('numlista', $numlista)->count() !== 1
                    || Estacion::query()->where('numlista', $numlista)->count() !== 1) {
                    continue;
                }
                $mappedId = (int) $locality->id;
                $name = (string) $locality->localidad;
            } else {
                $stationRow = Estacion::query()->where('id', $sourceId)->first();
                if (!$stationRow) {
                    continue;
                }
                $numlista = (int) ($stationRow->numlista ?? 0);
                if ($numlista < 1
                    || Estacion::query()->where('numlista', $numlista)->count() !== 1
                    || RhLocalidad::query()->where('numlista', $numlista)->count() !== 1) {
                    continue;
                }
                $locality = RhLocalidad::query()->where('numlista', $numlista)->first();
                if (!$locality) {
                    continue;
                }
                $mappedId = (int) $locality->id;
                $name = (string) $locality->localidad;
            }

            $mapped[$mappedId] = ['id' => $mappedId, 'nombre' => $name];
        }

        return array_values($mapped);
    }
}
