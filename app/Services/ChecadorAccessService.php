<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Models\Estacion;
use App\Models\ModuloConfig;
use App\Models\ModuloDptoOperativo;
use App\Models\ModuloSubDptoOperativo;
use App\Models\MultiestacionUsuario;
use App\Models\Operativo\RhLocalidad;
use App\Models\Usuario;
use Throwable;

/** Authorization boundary for the Checador V2 shell only. */
final class ChecadorAccessService
{
    public const MODULE_KEY = 'checador';
    public const INCIDENCE_MODULE_KEY = 'checador-v2-incidencias';
    public const RESOLUTION_MODULE_KEY = 'checador-v2-incidencias-resolucion';
    public const AUTHORIZATION_MODULE_KEY = 'checador-v2-incidencias-autorizacion';
    public const TEMPORARY_ACCESS_MODULE_KEY = 'acceso-temporal';
    private const PARENT_KEY = 'recursos-humanos';
    private const ENCARGADO_PUESTO_ID = 6;
    private const DIRECCION_OPERACIONES_PUESTO_ID = 13;
    public const PALO_SOLO_LOCALITY_ID = 2;
    public const AUTOLAVADO_LOCALITY_ID = 9;

    public static function isDepartmentLocality(int $localityId): bool
    {
        return $localityId === self::AUTOLAVADO_LOCALITY_ID;
    }

    /**
     * Resolve menu grant and server-authorized workplace scope. No client
     * station value is used to derive or expand the scope.
     *
     * @return array{allowed: bool, reason: string, workplaces: array<int, array{id:int,nombre:string}>, position_id?:int, station_workplaces?:array<int,array{id:int,nombre:string}>, department_workplaces?:array<int,array{id:int,nombre:string}>}
     */
    public static function authorize(?int $requestedLocalityId = null): array
    {
        $deny = static fn(string $reason): array => [
            'allowed' => false,
            'reason' => $reason,
            'workplaces' => [],
            'station_workplaces' => [],
            'department_workplaces' => [],
            'descargar' => false,
            'position_id' => null,
        ];

        try {
            $authenticatedUser = Auth::user();
            if (!$authenticatedUser || !Auth::check()) {
                return $deny('unauthenticated');
            }
            // Reload the authenticated identity from the database on every
            // authorization check. In particular, Checador must not trust a
            // stale session station or a multi-station UI context.
            $user = Usuario::query()
                ->where('id', (int) $authenticatedUser->id)
                ->where('estatus', 0)
                ->first();
            if (!$user) {
                return $deny('inactive_user');
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

            // Station allowlist values are tb_estaciones IDs and are mapped to
            // canonical op_rh_localidades by a unique numlista. Departments are
            // a separate, explicit locality allowlist; they never derive from a
            // related gas station such as Palo Solo.
            $moduleConfig = ModuloConfig::query()
                ->where('modulo_key', self::MODULE_KEY)
                ->where('activo', 1)
                ->first();
            if (!$moduleConfig) {
                return $deny('station_configuration_missing');
            }
            $configType = (string) ($moduleConfig->tipo ?? '');
            $departmentType = (string) ($moduleConfig->tipo_departamento ?? '');
            $supportsLocalityDepartments = $configType === 'stations_and_departments'
                && $departmentType === 'localidades';
            $legacyStationOnlyConfig = $configType === 'stations_only'
                && $departmentType === 'puestos';
            if ((!$supportsLocalityDepartments && !$legacyStationOnlyConfig)
                || (bool) $moduleConfig->allow_all) {
                return $deny('station_configuration_invalid');
            }
            $supportedStationIds = self::positiveIdList($moduleConfig->estaciones_soportadas);
            if ($supportedStationIds === null && !$supportsLocalityDepartments) {
                return $deny('empty_station_scope');
            }
            $supportedStationIds ??= [];

            $stationIds = self::resolveStationIds($user, $supportedStationIds);
            $stationWorkplaces = $stationIds === [] ? [] : self::mapStationIdsToUniqueLocalities($stationIds);

            $departmentWorkplaces = $supportsLocalityDepartments
                ? self::resolveExplicitDepartmentLocalities($user, $moduleConfig, $stationWorkplaces)
                : [];
            if ($stationWorkplaces === [] && $departmentWorkplaces === []) {
                return $deny($stationIds !== [] ? 'station_mapping_unavailable' : 'empty_station_scope');
            }
            $workplaces = self::mergeWorkplaceGroups($stationWorkplaces, $departmentWorkplaces);

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
                'station_workplaces' => $stationWorkplaces,
                'department_workplaces' => $departmentWorkplaces,
                // The role is taken from the active database identity loaded
                // above, never from a request parameter or stale session.
                'position_id' => (int) ($user->id_puesto ?? 0),
                // Existing effective parent grant; submenu membership itself
                // has no capability columns in this ACL model.
                'descargar' => !empty($parent['descargar']),
            ];
        } catch (Throwable) {
            return $deny('authorization_lookup_failed');
        }
    }

    /** Schedule permission is separate from the broad Biométricos ACL. */
    public static function authorizeScheduling(?int $requestedLocalityId, string $action): array
    {
        if (!in_array($action, ['leer', 'editar'], true)) {
            return ['allowed' => false, 'reason' => 'unknown_scheduling_action', 'workplaces' => []];
        }
        $scope = self::authorize($requestedLocalityId);
        if (!($scope['allowed'] ?? false)) return $scope;
        try {
            $authenticated = Auth::user();
            if (!$authenticated) return ['allowed' => false, 'reason' => 'unauthenticated', 'workplaces' => []];
            $user = Usuario::query()->where('id', (int) $authenticated->id)->where('estatus', 0)->first();
            if (!$user) return ['allowed' => false, 'reason' => 'inactive_user', 'workplaces' => []];
            $positionId = (int) ($user->id_puesto ?? 0);
            if (!in_array($positionId, [self::ENCARGADO_PUESTO_ID, self::DIRECCION_OPERACIONES_PUESTO_ID], true)) {
                return ['allowed' => false, 'reason' => 'scheduling_permission_denied', 'workplaces' => []];
            }
            $permission = $action === 'editar'
                ? ChecadorV2PermissionService::SCHEDULE_EDIT
                : ChecadorV2PermissionService::SCHEDULE_READ;
            if (!ChecadorV2PermissionService::allows((int) $user->id, $positionId, $permission)) {
                return ['allowed' => false, 'reason' => 'scheduling_permission_denied', 'workplaces' => []];
            }

            // M03 scheduling remains station-scoped under the approved
            // scheduling authorization contract. A department locality granted
            // for the institutional selector or M04 does not extend this scope.
            $stationWorkplaces = is_array($scope['station_workplaces'] ?? null)
                ? $scope['station_workplaces']
                : [];
            $schedulableWorkplaces = $stationWorkplaces;
            $schedulableIds = array_values(array_map(
                static fn(array $workplace): int => (int) ($workplace['id'] ?? 0),
                $schedulableWorkplaces
            ));

            if ($requestedLocalityId !== null && !in_array($requestedLocalityId, $schedulableIds, true)) {
                return ['allowed' => false, 'reason' => 'requested_station_out_of_scope', 'workplaces' => []];
            }
            if ($requestedLocalityId !== null) {
                $schedulableWorkplaces = array_values(array_filter(
                    $schedulableWorkplaces,
                    static fn(array $workplace): bool => (int) ($workplace['id'] ?? 0) === $requestedLocalityId
                ));
            }
            if ($schedulableWorkplaces === []) {
                return ['allowed' => false, 'reason' => 'empty_station_scope', 'workplaces' => []];
            }

            return [
                'allowed' => true,
                'reason' => 'authorized',
                'position_id' => $positionId,
                'workplaces' => $schedulableWorkplaces,
                'station_workplaces' => $schedulableWorkplaces,
                'descargar' => false,
            ];
        } catch (Throwable) {
            return ['allowed' => false, 'reason' => 'authorization_lookup_failed', 'workplaces' => []];
        }
    }

    /** M05 read access is a separate capability and is limited to physical-station localities. */
    public static function authorizeJourneyRead(?int $requestedLocalityId = null): array
    {
        $deny = static fn(string $reason): array => [
            'allowed' => false,
            'reason' => $reason,
            'workplaces' => [],
            'station_workplaces' => [],
            'position_id' => null,
        ];

        $scope = self::authorize($requestedLocalityId);
        if (!($scope['allowed'] ?? false)) return $scope;
        try {
            $authenticated = Auth::user();
            if (!$authenticated || !Auth::check()) return $deny('unauthenticated');
            $user = Usuario::query()->where('id', (int) $authenticated->id)->where('estatus', 0)->first();
            if (!$user) return $deny('inactive_user');

            $positionId = (int) ($user->id_puesto ?? 0);
            $userId = (int) ($user->id ?? 0);
            $stationWorkplaces = is_array($scope['station_workplaces'] ?? null) ? $scope['station_workplaces'] : [];
            $stationIds = array_column($stationWorkplaces, 'id');
            $departmentWorkplaces = is_array($scope['department_workplaces'] ?? null) ? $scope['department_workplaces'] : [];
            $departmentIds = array_column($departmentWorkplaces, 'id');

            $canAccessAutolavado = in_array(self::AUTOLAVADO_LOCALITY_ID, $departmentIds, true)
                && (
                    $positionId === self::DIRECCION_OPERACIONES_PUESTO_ID
                    || in_array(self::PALO_SOLO_LOCALITY_ID, $stationIds, true)
                );

            if ($requestedLocalityId === self::AUTOLAVADO_LOCALITY_ID) {
                if (!$canAccessAutolavado) {
                    return $deny('requested_station_out_of_scope');
                }
                $workplaces = array_values(array_filter(
                    $departmentWorkplaces,
                    static fn(array $w): bool => (int) ($w['id'] ?? 0) === self::AUTOLAVADO_LOCALITY_ID
                ));
            } else {
                $workplaces = self::constrainJourneyStationWorkplaces(
                    $stationWorkplaces,
                    $requestedLocalityId
                );
                if ($workplaces === null) return $deny('requested_station_out_of_scope');
                if ($requestedLocalityId === null && $canAccessAutolavado) {
                    foreach ($departmentWorkplaces as $dw) {
                        if ((int) ($dw['id'] ?? 0) === self::AUTOLAVADO_LOCALITY_ID) {
                            $workplaces[] = $dw;
                            break;
                        }
                    }
                }
            }

            $specificGrant = ChecadorV2PermissionService::allows(
                $userId,
                $positionId,
                ChecadorV2PermissionService::JOURNEY_READ
            );
            $legacyReadGrant = false;
            if (!$specificGrant) {
                $biometricPermissions = ModuloDptoOperativoService::getPermiso($userId, 'biometricos');
                $legacyReadGrant = !empty($biometricPermissions['leer']);
            }
            if (!self::journeyReadCapabilityAllowed($positionId, $specificGrant, $legacyReadGrant)) {
                return $deny('journey_read_permission_denied');
            }

            return [
                'allowed' => true,
                'reason' => 'authorized',
                'position_id' => $positionId,
                'workplaces' => $workplaces,
                'station_workplaces' => $stationWorkplaces,
                'department_workplaces' => $departmentWorkplaces,
                'descargar' => false,
            ];
        } catch (Throwable) {
            return $deny('authorization_lookup_failed');
        }
    }

    /** M11 attendance policy: station-scoped editing for positions 6 and 13. */
    public static function authorizeAttendancePolicy(?int $requestedLocalityId, string $action = 'leer'): array
    {
        return self::authorizeM11StationCapability(
            $requestedLocalityId,
            $action,
            ChecadorV2PermissionService::ATTENDANCE_POLICY_READ,
            ChecadorV2PermissionService::ATTENDANCE_POLICY_EDIT,
            [self::ENCARGADO_PUESTO_ID, self::DIRECCION_OPERACIONES_PUESTO_ID],
            'attendance_policy'
        );
    }

    /** Reader accounts: station manager edits only its authorized station; Direction is read-only. */
    public static function authorizeReaderProfiles(?int $requestedLocalityId, string $action = 'leer'): array
    {
        return self::authorizeM11StationCapability(
            $requestedLocalityId,
            $action,
            ChecadorV2PermissionService::READER_PROFILE_READ,
            ChecadorV2PermissionService::READER_PROFILE_EDIT,
            [self::ENCARGADO_PUESTO_ID, self::DIRECCION_OPERACIONES_PUESTO_ID],
            'reader_profile'
        );
    }

    /** Position catalog is global and restricted to Dirección; it uses the shared Checador ACL boundary. */
    public static function authorizePositionCatalog(?int $requestedLocalityId, string $action = 'leer'): array
    {
        $deny = static fn(string $reason): array => [
            'allowed' => false, 'reason' => $reason, 'workplaces' => [], 'position_id' => null,
        ];
        if (!in_array($action, ['leer', 'editar'], true)) return $deny('position_catalog_action_invalid');
        $scope = self::authorize($requestedLocalityId);
        if (!($scope['allowed'] ?? false)) return $scope;
        try {
            $authenticated = Auth::user();
            if (!$authenticated || !Auth::check()) return $deny('unauthenticated');
            $user = Usuario::query()->where('id', (int) $authenticated->id)->where('estatus', 0)->first();
            if (!$user) return $deny('inactive_user');
            $positionId = (int) ($user->id_puesto ?? 0);
            $permission = $action === 'editar'
                ? ChecadorV2PermissionService::POSITION_CATALOG_EDIT
                : ChecadorV2PermissionService::POSITION_CATALOG_READ;
            if ($positionId !== self::DIRECCION_OPERACIONES_PUESTO_ID
                || !ChecadorV2PermissionService::allows((int) $user->id, $positionId, $permission)) {
                return $deny('position_catalog_permission_denied');
            }
            return [
                'allowed' => true, 'reason' => 'authorized', 'position_id' => $positionId,
                'workplaces' => $scope['workplaces'] ?? [], 'station_workplaces' => $scope['station_workplaces'] ?? [],
                'descargar' => false,
            ];
        } catch (Throwable) {
            return $deny('authorization_lookup_failed');
        }
    }

    /** Narrow M11 permission grant plus server-revalidated station locality scope. */
    private static function authorizeM11StationCapability(
        ?int $requestedLocalityId,
        string $action,
        string $readPermission,
        string $editPermission,
        array $allowedPositions,
        string $capability
    ): array {
        $deny = static fn(string $reason): array => [
            'allowed' => false, 'reason' => $reason, 'workplaces' => [], 'station_workplaces' => [], 'position_id' => null,
        ];
        if (!in_array($action, ['leer', 'editar'], true)) return $deny($capability . '_action_invalid');
        $scope = self::authorize($requestedLocalityId);
        if (!($scope['allowed'] ?? false)) return $scope;
        try {
            $authenticated = Auth::user();
            if (!$authenticated || !Auth::check()) return $deny('unauthenticated');
            $user = Usuario::query()->where('id', (int) $authenticated->id)->where('estatus', 0)->first();
            if (!$user) return $deny('inactive_user');
            $positionId = (int) ($user->id_puesto ?? 0);
            if (!in_array($positionId, $allowedPositions, true)) return $deny($capability . '_position_not_mapped');
            $permission = $action === 'editar' ? $editPermission : $readPermission;
            if (!ChecadorV2PermissionService::allows((int) $user->id, $positionId, $permission)) {
                return $deny($capability . '_permission_denied');
            }
            $stationWorkplaces = is_array($scope['station_workplaces'] ?? null) ? $scope['station_workplaces'] : [];
            $stationIds = array_column($stationWorkplaces, 'id');
            $departmentWorkplaces = is_array($scope['department_workplaces'] ?? null) ? $scope['department_workplaces'] : [];
            $departmentIds = array_column($departmentWorkplaces, 'id');

            $isAuthorizedAutolavado = $requestedLocalityId === self::AUTOLAVADO_LOCALITY_ID
                && in_array(self::PALO_SOLO_LOCALITY_ID, $stationIds, true)
                && in_array(self::AUTOLAVADO_LOCALITY_ID, $departmentIds, true);

            if ($capability === 'reader_profile' && $action === 'editar' && $isAuthorizedAutolavado) {
                return $deny('shared_reader_edit_not_authorized');
            }

            if ($isAuthorizedAutolavado) {
                $workplaces = array_values(array_filter($departmentWorkplaces, static fn(array $w): bool => (int) ($w['id'] ?? 0) === self::AUTOLAVADO_LOCALITY_ID));
            } else {
                $workplaces = self::constrainJourneyStationWorkplaces($stationWorkplaces, $requestedLocalityId);
            }

            if ($workplaces === null || $workplaces === []) return $deny('requested_station_out_of_scope');
            return [
                'allowed' => true, 'reason' => 'authorized', 'position_id' => $positionId,
                'workplaces' => $workplaces, 'station_workplaces' => $stationWorkplaces, 'descargar' => false,
            ];
        } catch (Throwable) {
            return $deny('authorization_lookup_failed');
        }
    }

    /** Only Encargado and Dirección can use M05; legacy biometricos.read remains compatible. */
    public static function journeyReadCapabilityAllowed(int $positionId, bool $specificGrant, bool $legacyReadGrant): bool
    {
        return in_array($positionId, [self::ENCARGADO_PUESTO_ID, self::DIRECCION_OPERACIONES_PUESTO_ID], true)
            && ($specificGrant || $legacyReadGrant);
    }

    /** Returns null for an empty/foreign scope; department-only localities are never included. */
    public static function constrainJourneyStationWorkplaces(array $stationWorkplaces, ?int $requestedLocalityId): ?array
    {
        $normalized = [];
        foreach ($stationWorkplaces as $workplace) {
            if (!is_array($workplace)) return null;
            $id = (int) ($workplace['id'] ?? 0);
            if ($id < 1) return null;
            $normalized[$id] = $workplace;
        }
        if ($normalized === []) return null;
        if ($requestedLocalityId === null) return array_values($normalized);
        if ($requestedLocalityId < 1 || !isset($normalized[$requestedLocalityId])) return null;
        return [$normalized[$requestedLocalityId]];
    }

    /** Preserve legacy Biométricos scheduling checks used by Comodines. */
    public static function authorizeBiometricosScheduling(?int $requestedLocalityId, string $action): array
    {
        if (!in_array($action, ['leer', 'editar'], true)) {
            return ['allowed' => false, 'reason' => 'unknown_biometricos_action', 'workplaces' => []];
        }
        $scope = self::authorize($requestedLocalityId);
        if (!($scope['allowed'] ?? false)) return $scope;
        try {
            $authenticated = Auth::user();
            if (!$authenticated) return ['allowed' => false, 'reason' => 'unauthenticated', 'workplaces' => []];
            $user = Usuario::query()->where('id', (int) $authenticated->id)->where('estatus', 0)->first();
            if (!$user) return ['allowed' => false, 'reason' => 'inactive_user', 'workplaces' => []];
            $permissions = ModuloDptoOperativoService::getPermiso((int) $user->id, 'biometricos');
            if (empty($permissions[$action])) {
                return ['allowed' => false, 'reason' => 'biometricos_permission_denied', 'workplaces' => []];
            }
            return [
                'allowed' => true,
                'reason' => 'authorized',
                'workplaces' => $scope['workplaces'],
                'station_workplaces' => $scope['station_workplaces'] ?? [],
            ];
        } catch (Throwable) {
            return ['allowed' => false, 'reason' => 'authorization_lookup_failed', 'workplaces' => []];
        }
    }

    /** Station-local template administration: Encargado is granted checador.turnos.estacion.administrar limited to authorized home station. */
    public static function authorizeStationTemplateAdministration(?int $requestedLocalityId = null): array
    {
        $scope = self::authorize($requestedLocalityId);
        if (!($scope['allowed'] ?? false)) return $scope;
        try {
            $authenticated = Auth::user();
            if (!$authenticated) return ['allowed' => false, 'reason' => 'unauthenticated', 'workplaces' => []];
            $user = Usuario::query()->where('id', (int) $authenticated->id)->where('estatus', 0)->first();
            if (!$user) return ['allowed' => false, 'reason' => 'inactive_user', 'workplaces' => []];
            $positionId = (int) ($user->id_puesto ?? 0);
            if ($positionId !== self::ENCARGADO_PUESTO_ID) {
                return ['allowed' => false, 'reason' => 'station_template_administration_denied_for_position', 'workplaces' => []];
            }
            if (!ChecadorV2PermissionService::allows(
                (int) $user->id,
                $positionId,
                ChecadorV2PermissionService::STATION_TEMPLATE_ADMIN
            )) {
                return ['allowed' => false, 'reason' => 'station_template_administration_permission_denied', 'workplaces' => []];
            }
            $stationWorkplaces = is_array($scope['station_workplaces'] ?? null)
                ? $scope['station_workplaces']
                : [];
            $stationIds = array_values(array_map(
                static fn(array $workplace): int => (int) ($workplace['id'] ?? 0),
                $stationWorkplaces
            ));
            $departmentIds = array_values(array_map(
                static fn(array $workplace): int => (int) ($workplace['id'] ?? 0),
                is_array($scope['department_workplaces'] ?? null) ? $scope['department_workplaces'] : []
            ));
            $isAuthorizedAutolavado = $requestedLocalityId === self::AUTOLAVADO_LOCALITY_ID
                && in_array(self::PALO_SOLO_LOCALITY_ID, $stationIds, true)
                && in_array(self::AUTOLAVADO_LOCALITY_ID, $departmentIds, true);
            if ($requestedLocalityId !== null
                && !in_array($requestedLocalityId, $stationIds, true)
                && !$isAuthorizedAutolavado) {
                return ['allowed' => false, 'reason' => 'requested_station_out_of_scope', 'workplaces' => []];
            }
            return [
                'allowed' => true,
                'reason' => 'authorized',
                'position_id' => $positionId,
                'workplaces' => $isAuthorizedAutolavado ? ($scope['department_workplaces'] ?? []) : $stationWorkplaces,
                'station_workplaces' => $stationWorkplaces,
                'department_workplaces' => $scope['department_workplaces'] ?? [],
                'descargar' => false,
            ];
        } catch (Throwable) {
            return ['allowed' => false, 'reason' => 'authorization_lookup_failed', 'workplaces' => []];
        }
    }

    /** M04 template reads have a narrow Autolavado department exception; this never grants M03 assignment access. */
    public static function authorizeM04TemplateRead(?int $requestedLocalityId = null): array
    {
        $scope = self::authorize($requestedLocalityId);
        if (!($scope['allowed'] ?? false)) return $scope;
        try {
            $authenticated = Auth::user();
            if (!$authenticated) return ['allowed' => false, 'reason' => 'unauthenticated', 'workplaces' => []];
            $user = Usuario::query()->where('id', (int) $authenticated->id)->where('estatus', 0)->first();
            if (!$user) return ['allowed' => false, 'reason' => 'inactive_user', 'workplaces' => []];
            $positionId = (int) ($user->id_puesto ?? 0);
            if (!in_array($positionId, [self::ENCARGADO_PUESTO_ID, self::DIRECCION_OPERACIONES_PUESTO_ID], true)
                || !ChecadorV2PermissionService::allows((int) $user->id, $positionId, ChecadorV2PermissionService::SCHEDULE_READ)) {
                return ['allowed' => false, 'reason' => 'scheduling_permission_denied', 'workplaces' => []];
            }

            $stationIds = array_map('intval', array_column($scope['station_workplaces'] ?? [], 'id'));
            $departmentIds = array_map('intval', array_column($scope['department_workplaces'] ?? [], 'id'));
            if ($requestedLocalityId !== null
                && !in_array($requestedLocalityId, $stationIds, true)
                && !($requestedLocalityId === self::AUTOLAVADO_LOCALITY_ID
                    && in_array(self::AUTOLAVADO_LOCALITY_ID, $departmentIds, true)
                    && ($positionId !== self::ENCARGADO_PUESTO_ID
                        || in_array(self::PALO_SOLO_LOCALITY_ID, $stationIds, true)))) {
                return ['allowed' => false, 'reason' => 'requested_station_out_of_scope', 'workplaces' => []];
            }

            $selected = $requestedLocalityId === self::AUTOLAVADO_LOCALITY_ID
                ? ($scope['department_workplaces'] ?? [])
                : ($scope['station_workplaces'] ?? []);
            if ($requestedLocalityId !== null) {
                $selected = array_values(array_filter($selected, static fn(array $item): bool => (int) ($item['id'] ?? 0) === $requestedLocalityId));
            }
            if ($selected === []) return ['allowed' => false, 'reason' => 'empty_template_scope', 'workplaces' => []];
            return ['allowed' => true, 'reason' => 'authorized', 'position_id' => $positionId,
                'workplaces' => $selected, 'station_workplaces' => $scope['station_workplaces'] ?? [],
                'department_workplaces' => $scope['department_workplaces'] ?? [], 'descargar' => false];
        } catch (Throwable) {
            return ['allowed' => false, 'reason' => 'authorization_lookup_failed', 'workplaces' => []];
        }
    }

    /** Global template catalog access is independently granted and never implied by schedule editing. */
    public static function authorizeTemplateAdministration(?int $requestedLocalityId = null): array
    {
        $scope = self::authorize($requestedLocalityId);
        if (!($scope['allowed'] ?? false)) return $scope;
        try {
            $authenticated = Auth::user();
            if (!$authenticated) return ['allowed' => false, 'reason' => 'unauthenticated', 'workplaces' => []];
            $user = Usuario::query()->where('id', (int) $authenticated->id)->where('estatus', 0)->first();
            if (!$user) return ['allowed' => false, 'reason' => 'inactive_user', 'workplaces' => []];
            $positionId = (int) ($user->id_puesto ?? 0);
            if (!ChecadorV2PermissionService::allows(
                (int) $user->id,
                $positionId,
                ChecadorV2PermissionService::TEMPLATE_ADMIN
            )) {
                return ['allowed' => false, 'reason' => 'template_administration_permission_denied', 'workplaces' => []];
            }
            return [
                'allowed' => true,
                'reason' => 'authorized',
                'position_id' => $positionId,
                'workplaces' => $scope['workplaces'],
                'station_workplaces' => $scope['station_workplaces'] ?? [],
                'descargar' => false,
            ];
        } catch (Throwable) {
            return ['allowed' => false, 'reason' => 'authorization_lookup_failed', 'workplaces' => []];
        }
    }

    /**
     * Re-resolve the independent Temporary Access ACL and physical-station
     * scope for each supervisor operation. Departments are never accepted as
     * PIN scope, and this ACL is never inferred from Checador permissions.
     *
     * @return array{allowed:bool,reason:string,workplace_id:?int,permissions:array{leer:int,crear:int,editar:int,eliminar:int,descargar:int},can_authorize:bool,can_cancel:bool,can_reject:bool}
     */
    public static function authorizeTemporaryAccess(?int $requestedLocalityId, string $action): array
    {
        $deny = static fn(string $reason): array => [
            'allowed' => false,
            'reason' => $reason,
            'workplace_id' => null,
            'permissions' => ['leer' => 0, 'crear' => 0, 'editar' => 0, 'eliminar' => 0, 'descargar' => 0],
            'can_authorize' => false,
            'can_cancel' => false,
            'can_reject' => false,
        ];

        if ($requestedLocalityId === null || $requestedLocalityId < 1
            || !in_array($action, ['leer', 'crear', 'editar', 'eliminar'], true)) {
            return $deny('invalid_temporary_access_scope');
        }

        try {
            $checadorAccess = self::authorize($requestedLocalityId);
            if (empty($checadorAccess['allowed'])
                || !in_array($requestedLocalityId, array_map('intval', array_column($checadorAccess['station_workplaces'] ?? [], 'id')), true)) {
                return $deny('temporary_access_station_out_of_checador_scope');
            }

            $authenticatedUser = Auth::user();
            if (!$authenticatedUser || !Auth::check()) {
                return $deny('unauthenticated');
            }
            $user = Usuario::query()
                ->where('id', (int) $authenticatedUser->id)
                ->where('estatus', 0)
                ->first();
            if (!$user) {
                return $deny('inactive_user');
            }

            $moduleConfig = ModuloConfig::query()
                ->where('modulo_key', self::TEMPORARY_ACCESS_MODULE_KEY)
                ->where('activo', 1)
                ->first();
            $supportedStations = $moduleConfig
                ? self::positiveIdList($moduleConfig->estaciones_soportadas)
                : null;
            if (!$moduleConfig
                || (string) ($moduleConfig->tipo_departamento ?? '') !== 'localidades'
                || (bool) ($moduleConfig->allow_all ?? true)
                || $supportedStations === null
                || $supportedStations === []) {
                return $deny('temporary_access_module_configuration_missing');
            }

            $permissions = ModuloDptoOperativoService::getTemporaryAccessPermissions(
                (int) $user->id,
                self::TEMPORARY_ACCESS_MODULE_KEY
            );
            $legacyEdit = (int) ($permissions['editar'] ?? 0) === 1;
            $required = match ($action) {
                'leer' => (int) ($permissions['leer'] ?? 0) === 1,
                'crear' => (int) ($permissions['crear'] ?? 0) === 1 || $legacyEdit,
                'eliminar' => (int) ($permissions['eliminar'] ?? 0) === 1 || $legacyEdit,
                'editar' => $legacyEdit,
                default => false,
            };
            if (!$required) {
                return $deny('temporary_access_permission_denied');
            }

            // ModuleStationService resolves this module's own effective
            // station allowlist in canonical locality space because its
            // configured tipo_departamento is `localidades`.
            $moduleWorkplaces = ModuleStationService::getAvailableStations(self::TEMPORARY_ACCESS_MODULE_KEY);
            $moduleWorkplaceIds = array_map('intval', array_column($moduleWorkplaces, 'id'));
            if (!in_array($requestedLocalityId, $moduleWorkplaceIds, true)) {
                return $deny('temporary_access_station_out_of_scope');
            }

            return [
                'allowed' => true,
                'reason' => 'authorized',
                'workplace_id' => $requestedLocalityId,
                'permissions' => [
                    'leer' => (int) ($permissions['leer'] ?? 0),
                    'crear' => (int) ($permissions['crear'] ?? 0),
                    'editar' => (int) ($permissions['editar'] ?? 0),
                    'eliminar' => (int) ($permissions['eliminar'] ?? 0),
                    'descargar' => (int) ($permissions['descargar'] ?? 0),
                ],
                'can_authorize' => (int) ($permissions['crear'] ?? 0) === 1 || $legacyEdit,
                'can_cancel' => (int) ($permissions['eliminar'] ?? 0) === 1 || $legacyEdit,
                'can_reject' => $legacyEdit,
            ];
        } catch (Throwable) {
            return $deny('authorization_lookup_failed');
        }
    }

    /**
     * Match the physical `id_estacion` returned by Temporary Access V2 to a
     * canonical Portal3 locality only through the validated one-to-one
     * `numlista` mapping. Numeric ID equality is never an authorization rule.
     */
    public static function temporaryAccessRequestBelongsToLocality(
        ?int $requestStationId,
        ?int $authorizedLocalityId
    ): bool {
        if ($requestStationId === null || $requestStationId < 1
            || $authorizedLocalityId === null || $authorizedLocalityId < 1) {
            return false;
        }

        try {
            $mapped = self::mapStationIdsToUniqueLocalities([$requestStationId]);
            return count($mapped) === 1
                && (int) ($mapped[0]['id'] ?? 0) === $authorizedLocalityId;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Departments are supported only by the explicit Checador module list
     * intersected with an explicit locality scope from the user's effective
     * station/department ACL. A null, wildcard, empty, or malformed ACL list
     * means no department access here (never "all").
     *
     * @return array<int,array{id:int,nombre:string}>
     */
    private static function resolveExplicitDepartmentLocalities(
        Usuario $user,
        ModuloConfig $moduleConfig,
        array $stationWorkplaces
    ): array {
        $supportedIds = self::positiveIdList($moduleConfig->departamentos_soportados);
        if ($supportedIds === null) {
            return [];
        }

        $explicitIds = self::departmentScopeForApprovedProfile($user, $stationWorkplaces);
        if ($explicitIds === null) {
            return [];
        }

        $requestedIds = array_values(array_intersect($supportedIds, $explicitIds));
        if ((int) $user->id_puesto === self::ENCARGADO_PUESTO_ID) {
            $requestedIds = array_values(array_intersect($requestedIds, [self::AUTOLAVADO_LOCALITY_ID]));
        } elseif ((int) $user->id_puesto !== self::DIRECCION_OPERACIONES_PUESTO_ID) {
            return [];
        }
        if ($requestedIds === []) {
            return [];
        }

        $stationLocalityIds = array_map('intval', array_column($stationWorkplaces, 'id'));
        $requestedIds = array_values(array_diff($requestedIds, $stationLocalityIds));
        if ($requestedIds === []) {
            return [];
        }

        return RhLocalidad::query()
            ->whereIn('id', $requestedIds)
            ->orderBy('numlista')
            ->get(['id', 'localidad as nombre'])
            ->map(static fn($row): array => ['id' => (int) $row->id, 'nombre' => (string) $row->nombre])
            ->values()
            ->all();
    }

    /**
     * Resolve department grants only for the two approved institutional
     * profiles. Dirección may use its explicit effective position/user scope.
     * The Palo Solo exception must be individual and cannot be inherited from
     * the Encargado position or from another module's station scope.
     *
     * @return array<int,int>|null
     */
    private static function departmentScopeForApprovedProfile(Usuario $user, array $stationWorkplaces): ?array
    {
        $positionId = (int) $user->id_puesto;
        if ($positionId === self::DIRECCION_OPERACIONES_PUESTO_ID) {
            $scope = MultiestacionService::getConfig($user);
            return $scope === null
                ? null
                : self::positiveIdList($scope['departamentos_localidades'] ?? null);
        }

        if ($positionId !== self::ENCARGADO_PUESTO_ID
            || count($stationWorkplaces) !== 1
            || (int) ($stationWorkplaces[0]['id'] ?? 0) !== self::PALO_SOLO_LOCALITY_ID) {
            return null;
        }

        // Do not use MultiestacionService::getConfig here: its normal
        // user-over-position merge would inherit a position-level exception.
        // Palo Solo → Autolavado is an individual authorization only.
        $userScopes = MultiestacionUsuario::query()
            ->where('id_usuario', (int) $user->id)
            ->where('activo', true)
            ->get(['id', 'departamentos_localidades']);
        if ($userScopes->count() !== 1) {
            return null;
        }

        return self::positiveIdList($userScopes->first()->departamentos_localidades);
    }

    /** @return array<int,array{id:int,nombre:string}> */
    private static function mergeWorkplaceGroups(array $stations, array $departments): array
    {
        $merged = [];
        foreach ([$stations, $departments] as $group) {
            foreach ($group as $workplace) {
                $id = (int) ($workplace['id'] ?? 0);
                if ($id > 0) {
                    $merged[$id] = ['id' => $id, 'nombre' => (string) ($workplace['nombre'] ?? '')];
                }
            }
        }
        return array_values($merged);
    }

    /**
     * Resolve the Checador-only source station IDs. Dirección receives the
     * active, validated Checador allowlist. Every other profile is bound to
     * its current id_gas; generic multi-station settings from other modules
     * never widen the Checador boundary.
     * Other roles retain the existing module-scoped station resolver.
     *
     * @param array<int,int> $supportedStationIds
     * @return array<int,int>
     */
    private static function resolveStationIds(Usuario $user, array $supportedStationIds): array
    {
        if ((int) $user->id_puesto === self::ENCARGADO_PUESTO_ID) {
            $homeStationId = (int) ($user->id_gas ?? 0);
            return $homeStationId > 0 && in_array($homeStationId, $supportedStationIds, true)
                ? [$homeStationId]
                : [];
        }

        if ((int) $user->id_puesto === self::DIRECCION_OPERACIONES_PUESTO_ID) {
            return $supportedStationIds;
        }

        $homeStationId = (int) ($user->id_gas ?? 0);
        return $homeStationId > 0 && in_array($homeStationId, $supportedStationIds, true)
            ? [$homeStationId]
            : [];
    }

    /**
     * Parse a non-empty explicit positive-ID list. Unrestricted sentinels and
     * malformed values are deliberately not accepted by this boundary.
     *
     * @return array<int,int>|null
     */
    private static function positiveIdList(mixed $value): ?array
    {
        if (!is_array($value) || $value === []) {
            return null;
        }

        $ids = [];
        foreach ($value as $candidate) {
            if (is_int($candidate)) {
                $id = $candidate;
            } elseif (is_string($candidate) && ctype_digit($candidate)) {
                $id = (int) $candidate;
            } else {
                return null;
            }
            if ($id < 1) {
                return null;
            }
            $ids[] = $id;
        }

        return array_values(array_unique($ids));
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
     * Incidence creation requires its dedicated `crear` grant and `leer` so
     * Portal3 can confirm the result through the canonical detail endpoint.
     * The API independently repeats both authorization and workplace checks.
     *
     * @return array{allowed:bool,reason:string,workplaces:array<int,array{id:int,nombre:string}>,namespace?:string}
     */
    public static function authorizeIncidenceCreate(?int $requestedLocalityId): array
    {
        $scope = self::authorizeIncidenceRead($requestedLocalityId);
        if (!($scope['allowed'] ?? false)) {
            return $scope;
        }

        try {
            $user = Auth::user();
            if (!$user) {
                return ['allowed' => false, 'reason' => 'unauthenticated', 'workplaces' => []];
            }
            $permissions = ModuloDptoOperativoService::getPermiso((int) $user->id, self::INCIDENCE_MODULE_KEY);
            if (empty($permissions['crear'])) {
                return ['allowed' => false, 'reason' => 'incidence_create_permission_denied', 'workplaces' => []];
            }

            return [
                'allowed' => true,
                'reason' => 'authorized',
                'workplaces' => $scope['workplaces'],
                'namespace' => self::INCIDENCE_MODULE_KEY,
            ];
        } catch (Throwable) {
            return ['allowed' => false, 'reason' => 'authorization_lookup_failed', 'workplaces' => []];
        }
    }

    /**
     * Incidence reads require the dedicated read capability and the same
     * authenticated workplace scope used by every Checador workspace.
     *
     * @return array{allowed:bool,reason:string,workplaces:array<int,array{id:int,nombre:string}>,namespace?:string}
     */
    public static function authorizeIncidenceRead(?int $requestedLocalityId): array
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
            $permissions = ModuloDptoOperativoService::getPermiso((int) $user->id, self::INCIDENCE_MODULE_KEY);
            if (empty($permissions['leer'])) {
                return ['allowed' => false, 'reason' => 'incidence_read_permission_denied', 'workplaces' => []];
            }
            return [
                'allowed' => true,
                'reason' => 'authorized',
                'workplaces' => $scope['workplaces'],
                'namespace' => self::INCIDENCE_MODULE_KEY,
            ];
        } catch (Throwable) {
            return ['allowed' => false, 'reason' => 'authorization_lookup_failed', 'workplaces' => []];
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

    /** Resolve a physical station to its unique active canonical locality. */
    public static function resolveCanonicalLocalityIdForStation(int $physicalStationId): ?int
    {
        if ($physicalStationId < 1) {
            return null;
        }

        try {
            $mapped = self::mapStationIdsToUniqueLocalities([$physicalStationId]);
            if (count($mapped) !== 1) {
                return null;
            }

            $localityId = (int) ($mapped[0]['id'] ?? 0);
            return $localityId > 0 ? $localityId : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** Resolve the unique active physical station backing a canonical locality. */
    public static function resolveActiveStationIdForLocality(int $localityId): ?int
    {
        if ($localityId < 1) {
            return null;
        }

        try {
            // Reuse the established station/locality converter, then validate
            // its result through this service's unique, active station bridge.
            $stationIds = MultiestacionService::convertIds(
                [$localityId],
                MultiestacionService::TABLA_RH_LOCALIDADES,
                MultiestacionService::TABLA_ESTACIONES
            );
            if (count($stationIds) !== 1) {
                return null;
            }

            $stationId = (int) $stationIds[0];
            $mappedLocalities = self::mapStationIdsToUniqueLocalities([$stationId]);
            if ($stationId < 1
                || count($mappedLocalities) !== 1
                || (int) ($mappedLocalities[0]['id'] ?? 0) !== $localityId) {
                return null;
            }

            return $stationId;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * ModuleStationService returns IDs in the module's configured station
     * space. Resolve the other catalog only through a one-to-one numlista.
     *
     * @param array<int,int> $stationIds in tb_estaciones ID space
     * @return array<int, array{id:int,nombre:string}>
     */
    private static function mapStationIdsToUniqueLocalities(array $stationIds): array
    {
        $mapped = [];

        foreach ($stationIds as $sourceId) {
            $sourceId = (int) $sourceId;
            if ($sourceId < 1) {
                return [];
            }

            // The approved Checador configuration uses tb_estaciones IDs.
            // estatus=0 is the active value established by the existing
            // Temporary Access security contract; inactive history is not
            // implicitly exposed through this report.
            $stationRow = Estacion::query()->where('id', $sourceId)->first();
            if (!$stationRow) {
                return [];
            }
            if ((int) $stationRow->estatus !== 0) {
                // Inactive catalog rows are not part of the current scope.
                continue;
            }

            $numlista = (int) ($stationRow->numlista ?? 0);
            if ($numlista < 1
                || Estacion::query()->where('numlista', $numlista)->count() !== 1
                || RhLocalidad::query()->where('numlista', $numlista)->count() !== 1) {
                return [];
            }
            $locality = RhLocalidad::query()->where('numlista', $numlista)->first();
            if (!$locality) {
                return [];
            }
            $mappedId = (int) $locality->id;
            $name = (string) $locality->localidad;

            $mapped[$mappedId] = ['id' => $mappedId, 'nombre' => $name];
        }

        return array_values($mapped);
    }
}
