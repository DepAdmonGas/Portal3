<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Estacion;
use App\Models\Operativo\RhLocalidad;
use App\Models\Operativo\RhPuestos;
use App\Models\Usuario;
use Throwable;

/** Read-only configuration projection for the Checador V2 shell. */
final class ChecadorConfigurationReadService
{
    /**
     * Load M11 configuration sources without duplicating M03/M04 schedule reads.
     * Demo mode never reads legacy operational tables.
     *
     * @return array<string,mixed>
     */
    public function read(Usuario $user, int $workplaceId, bool $demoMode): array
    {
        $denied = ChecadorAccessService::authorize($workplaceId);
        if (empty($denied['allowed'])) {
            return self::emptyResult('workplace_out_of_scope');
        }

        $permission = ModuloDptoOperativoService::getPermiso((int) $user->id, 'biometricos');
        $canRead = (int) ($permission['leer'] ?? 0) === 1;
        $canCreate = (int) ($permission['crear'] ?? 0) === 1;
        $canEdit = (int) ($permission['editar'] ?? 0) === 1;
        $canDelete = (int) ($permission['eliminar'] ?? 0) === 1;
        $positionReadAccess = ChecadorAccessService::authorizePositionCatalog($workplaceId, 'leer');
        $positionEditAccess = ChecadorAccessService::authorizePositionCatalog($workplaceId, 'editar');
        $readerReadAccess = ChecadorAccessService::authorizeReaderProfiles($workplaceId, 'leer');
        $readerEditAccess = ChecadorAccessService::authorizeReaderProfiles($workplaceId, 'editar');
        $scheduleReadAccess = ChecadorAccessService::authorizeScheduling($workplaceId, 'leer');
        $positionsRead = ($positionReadAccess['allowed'] ?? false) === true;
        $positionsEdit = ($positionEditAccess['allowed'] ?? false) === true;
        $readerRead = ($readerReadAccess['allowed'] ?? false) === true;
        $readerEdit = ($readerEditAccess['allowed'] ?? false) === true;
        $scheduleRead = ($scheduleReadAccess['allowed'] ?? false) === true;
        $templateAdmin = $this->templateAdministrationAllowed($workplaceId);

        $result = [
            'status' => 'ready',
            'demo_mode' => $demoMode,
            'workplace_id' => $workplaceId,
            'workplace_kind' => in_array($workplaceId, array_map('intval', array_column($denied['department_workplaces'] ?? [], 'id')), true)
                ? 'department' : 'station',
            'permissions' => [
                'read' => $canRead,
                'legacy_create' => $canRead && $canCreate,
                'legacy_edit' => $canRead && $canEdit,
                'legacy_delete' => $canRead && $canDelete,
                // Legacy biometricos flags do not authorize V2 schedule writes.
                'v2_schedule_edit' => $canRead && $canEdit && (ChecadorAccessService::authorizeScheduling($workplaceId, 'editar')['allowed'] ?? false),
                'positions_read' => $positionsRead,
                'positions_create' => $positionsEdit,
                'positions_edit' => $positionsRead && $positionsEdit,
                'positions_delete' => false,
                'reader_profiles_read' => $readerRead,
                'reader_profiles_edit' => $readerRead && $readerEdit,
                'template_admin' => $templateAdmin,
            ],
            'mapping' => ['status' => 'not_checked', 'physical_station_id' => null, 'name' => null, 'legacy_scope_authorized' => false],
            'api_templates' => ['status' => 'not_requested', 'items' => [], 'message' => ''],
            'api_history' => ['status' => 'not_requested', 'effective_schedule' => null, 'assignments' => [], 'message' => ''],
            'legacy_schedules' => ['status' => 'not_read', 'items' => []],
            'legacy_rules' => ['status' => 'not_read', 'configured' => false, 'retardo' => null, 'incidencia' => null],
            'attendance_policy' => ['status' => 'not_requested', 'data' => null, 'message' => ''],
            'legacy_profiles' => ['status' => 'not_read', 'active_count' => null],
            'positions' => ['status' => 'not_read', 'items' => []],
            'reader_profiles' => ['status' => 'not_read', 'items' => [], 'partial' => false],
            'device_auth' => ['status' => 'not_integrated', 'message' => 'La autenticación de dispositivos V2 no se consulta desde este catálogo legacy.'],
        ];

        if (!$demoMode && ($policyReadAccess['allowed'] ?? false) === true) {
            $result['legacy_rules'] = self::readM11LegacyPolicyReference($workplaceId);
        }

        if ($demoMode) {
            $result['status'] = 'demo_isolated';
            $result['mapping'] = ['status' => 'not_read_in_demo', 'physical_station_id' => null, 'name' => null];
            $result['legacy_schedules'] = ['status' => 'not_read_in_demo', 'items' => []];
            $result['legacy_rules'] = ['status' => 'not_read_in_demo', 'configured' => false, 'retardo' => null, 'incidencia' => null];
            $result['attendance_policy'] = ['status' => 'demo_blocked', 'data' => null, 'message' => 'La configuración versionada no se consulta en modo demo.'];
            $result['legacy_profiles'] = ['status' => 'not_read_in_demo', 'active_count' => null];
            $result['positions'] = ['status' => $positionsRead ? 'not_read_in_demo' : 'permission_denied', 'items' => []];
            $result['reader_profiles'] = ['status' => $readerRead ? 'not_read_in_demo' : 'permission_denied', 'items' => [], 'partial' => false];
            return $result;
        }

        if (!$canRead) {
            if (($result['legacy_rules']['status'] ?? '') === 'not_read') {
                $result['legacy_rules'] = ['status' => 'permission_denied', 'configured' => false, 'retardo' => null, 'incidencia' => null];
            }
            $result['attendance_policy'] = self::readAttendancePolicyVersioned($user, $workplaceId);
            $result['legacy_profiles'] = ['status' => 'not_applicable', 'active_count' => null];
            $result['reader_profiles'] = self::readReaderProfiles($workplaceId, $readerRead);
            $result['positions'] = self::readPositions($positionsRead);
            return $result;
        }

        if ($result['workplace_kind'] === 'department') {
            $result['mapping'] = ['status' => 'department_has_no_legacy_station_mapping', 'physical_station_id' => null, 'name' => null];
            $result['legacy_schedules'] = ['status' => 'not_applicable', 'items' => []];
            $result['legacy_rules'] = ['status' => 'not_applicable', 'configured' => false, 'retardo' => null, 'incidencia' => null];
            $result['legacy_profiles'] = ['status' => 'not_applicable', 'active_count' => null];
        } else {
            $mapping = self::resolveUniquePhysicalStation($workplaceId);
            $physicalStationId = $mapping['physical_station_id'];
            $legacyScopeAuthorized = $physicalStationId !== null
                && in_array($physicalStationId, BiometricosService::authorizedLegacyStationIds(), true);
            $mapping['legacy_scope_authorized'] = $legacyScopeAuthorized;
            $result['mapping'] = $mapping;
            if (!$legacyScopeAuthorized) {
                $legacyStatus = $physicalStationId === null ? 'mapping_or_module_scope_unavailable' : 'legacy_station_not_authorized';
                $result['legacy_schedules'] = ['status' => $legacyStatus, 'items' => []];
                $result['legacy_rules'] = ['status' => $legacyStatus, 'configured' => false, 'retardo' => null, 'incidencia' => null];
                $result['legacy_profiles'] = ['status' => $legacyStatus, 'active_count' => null];
            } else {
                try {
                    $rules = BiometricosService::getRetardoIncidencia($physicalStationId);
                    $ruleStatus = empty($rules['available']) ? 'permission_denied'
                        : (!empty($rules['configured']) ? 'legacy_configured' : 'legacy_unconfigured');
                    $result['legacy_rules'] = ['status' => $ruleStatus,
                        'configured' => (bool) ($rules['configured'] ?? false), 'retardo' => $rules['retardo'] ?? null,
                        'incidencia' => $rules['incidencia'] ?? null];
                } catch (Throwable) {
                    $result['legacy_rules'] = ['status' => 'unavailable', 'configured' => false, 'retardo' => null, 'incidencia' => null];
                }
            }
        }

        $result['positions'] = self::readPositions($positionsRead);
        $result['reader_profiles'] = self::readReaderProfiles($workplaceId, $readerRead);

        $result['attendance_policy'] = self::readAttendancePolicyVersioned($user, $workplaceId);

        return $result;
    }

    /** M11 has its own narrow ACL; it is not inherited from biometricos.leer. */
    private static function readAttendancePolicyVersioned(Usuario $user, int $workplaceId): array
    {
        $policyAccess = ChecadorAccessService::authorizeAttendancePolicy($workplaceId, 'leer');
        if (!($policyAccess['allowed'] ?? false)) {
            return [
                'status' => ($policyAccess['reason'] ?? '') === 'attendance_policy_position_not_mapped'
                    ? 'role_not_mapped' : 'permission_denied',
                'data' => null,
                'message' => 'La consulta de política V2 requiere identidad y permiso específico de configuración.',
            ];
        }

        try {
            $dailyReader = new ChecadorDailyReadService();
            $policyResult = $dailyReader->readCurrentAttendancePolicy($user, $workplaceId);
        } catch (Throwable) {
            $policyResult = ['ok' => false];
        }
        if (($policyResult['ok'] ?? false) !== true) {
            return ['status' => 'unavailable', 'data' => null,
                'message' => 'La política versionada no pudo confirmarse desde API V2.'];
        }

        $policyData = $policyResult['data'];
        $latestPolicy = is_array($policyData['latest_scheduled_policy'] ?? null)
            ? $policyData['latest_scheduled_policy'] : null;
        $effectivePolicy = is_array($policyData['policy'] ?? null) ? $policyData['policy'] : null;
        $historyById = [];
        foreach ([$effectivePolicy, $latestPolicy] as $policy) {
            if (is_array($policy) && (int) ($policy['id'] ?? 0) > 0) {
                $historyById[(int) $policy['id']] = $policy;
            }
        }

        // The existing API read accepts an effective date, not a history-list endpoint.
        // Walk prior effective dates using that read contract, bounded to 50 versions.
        $historyComplete = true;
        $cursor = $latestPolicy ?? $effectivePolicy;
        $expectedVersion = (int) ($cursor['policy_version'] ?? 0) - 1;
        $historyReads = 0;
        while ($expectedVersion > 0 && $historyReads < 50 && is_array($cursor)) {
            $cursorDate = (string) ($cursor['effective_from_local_date'] ?? '');
            $parsedCursorDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $cursorDate, new \DateTimeZone('UTC'));
            if (!$parsedCursorDate || $parsedCursorDate->format('Y-m-d') !== $cursorDate
                || array_filter(\DateTimeImmutable::getLastErrors() ?: [])) {
                $historyComplete = false;
                break;
            }
            $beforeDate = (new \DateTimeImmutable($cursorDate, new \DateTimeZone('UTC')))->modify('-1 day')->format('Y-m-d');
            try {
                $priorResult = $dailyReader->readAttendancePolicy($user, $workplaceId, $beforeDate);
            } catch (Throwable) {
                $priorResult = ['ok' => false];
            }
            $historyReads++;
            $prior = $priorResult['data']['policy'] ?? null;
            if (($priorResult['ok'] ?? false) !== true || !is_array($prior)
                || (int) ($prior['policy_version'] ?? 0) !== $expectedVersion
                || (int) ($prior['workplace_id'] ?? 0) !== $workplaceId) {
                $historyComplete = false;
                break;
            }
            $historyById[(int) $prior['id']] = $prior;
            $cursor = $prior;
            $expectedVersion--;
        }
        if ($expectedVersion > 0) $historyComplete = false;
        $policyData['policy_history'] = array_values($historyById);
        usort($policyData['policy_history'], static fn(array $left, array $right): int =>
            strcmp((string) ($right['effective_from_local_date'] ?? ''), (string) ($left['effective_from_local_date'] ?? '')));
        $policyData['policy_history_complete'] = $historyComplete;
        return [
            'status' => (string) ($policyData['status'] ?? 'unavailable'),
            'data' => $policyData,
            'message' => (string) ($policyData['status'] ?? '') === 'VERSIONED'
                ? 'Política V2 versionada consultada por localidad y fecha local.'
                : 'No existe una versión V2 para la fecha; se conservan por separado los valores legacy de referencia.',
        ];
    }

    /** @return array{status:string,items:list<array{id:int,name:string}>} */
    private static function readPositions(bool $allowed): array
    {
        if (!$allowed) return ['status' => 'permission_denied', 'items' => []];
        try {
            $positions = RhPuestos::query()->where('status', 1)->orderBy('puesto')->get(['id', 'puesto']);
            return ['status' => 'current_catalog', 'items' => $positions->map(static fn($row): array => [
                'id' => (int) $row->id, 'name' => (string) $row->puesto,
            ])->all()];
        } catch (Throwable) {
            return ['status' => 'unavailable', 'items' => []];
        }
    }

    /** Direction may read the shared institutional catalog even before a station is selected. */
    public static function readM11PositionCatalog(?int $requestedLocalityId = null): array
    {
        $allowed = ChecadorAccessService::authorizePositionCatalog($requestedLocalityId, 'leer');
        return self::readPositions(($allowed['allowed'] ?? false) === true);
    }

    private static function readReaderProfiles(int $workplaceId, bool $allowed): array
    {
        if (!$allowed) return ['status' => 'permission_denied', 'items' => [], 'partial' => false];
        $result = BiometricosService::readM11ReaderProfiles($workplaceId);
        return is_array($result) ? $result : ['status' => 'unavailable', 'items' => [], 'partial' => false];
    }

    /** M11 may read only the selected station's existing legacy values as labelled, unversioned references. */
    private static function readM11LegacyPolicyReference(int $workplaceId): array
    {
        $mapping = self::resolveUniquePhysicalStation($workplaceId);
        $physicalId = (int) ($mapping['physical_station_id'] ?? 0);
        if (($mapping['status'] ?? '') !== 'unique_numlista_mapping' || $physicalId < 1) {
            return ['status' => 'mapping_unavailable', 'configured' => false, 'retardo' => null, 'incidencia' => null];
        }
        try {
            $row = RhLocalidadesRetardoIncidencia::query()->where('id_estacion', $physicalId)->orderByDesc('id')->first(['retardo', 'incidencia']);
            if (!$row) return ['status' => 'legacy_unconfigured', 'configured' => false, 'retardo' => null, 'incidencia' => null];
            return ['status' => 'legacy_configured', 'configured' => true, 'retardo' => (int) $row->retardo, 'incidencia' => (int) $row->incidencia];
        } catch (Throwable) {
            return ['status' => 'unavailable', 'configured' => false, 'retardo' => null, 'incidencia' => null];
        }
    }

    private function templateAdministrationAllowed(int $workplaceId): bool
    {
        return (ChecadorAccessService::authorizeTemplateAdministration($workplaceId)['allowed'] ?? false) === true;
    }

    /** @return array{status:string,physical_station_id:?int,name:?string} */
    public static function resolveUniquePhysicalStation(int $workplaceId): array
    {
        try {
            $locality = RhLocalidad::query()->where('id', $workplaceId)->first(['id', 'numlista', 'localidad']);
            if (!$locality || (int) $locality->numlista < 1) {
                return ['status' => 'mapping_unavailable', 'physical_station_id' => null, 'name' => null];
            }
            $rows = Estacion::query()->where('numlista', (int) $locality->numlista)->get(['id', 'numlista', 'nombre', 'estatus']);
            if ($rows->count() !== 1 || (int) $rows->first()->estatus !== 0) {
                return ['status' => 'mapping_not_unique_or_inactive', 'physical_station_id' => null, 'name' => null];
            }
            return ['status' => 'unique_numlista_mapping', 'physical_station_id' => (int) $rows->first()->id,
                'name' => (string) $locality->localidad];
        } catch (Throwable) {
            return ['status' => 'mapping_unavailable', 'physical_station_id' => null, 'name' => null];
        }
    }

    private static function emptyResult(string $status): array
    {
        return ['status' => $status, 'demo_mode' => false, 'workplace_id' => null, 'workplace_kind' => 'unknown',
            'permissions' => ['read' => false, 'legacy_create' => false, 'legacy_edit' => false, 'legacy_delete' => false, 'v2_schedule_edit' => false,
                'positions_read' => false, 'positions_create' => false, 'positions_edit' => false, 'positions_delete' => false,
                'reader_profiles_read' => false, 'reader_profiles_edit' => false, 'template_admin' => false],
            'mapping' => ['status' => 'not_checked', 'physical_station_id' => null, 'name' => null],
            'api_templates' => ['status' => 'not_requested', 'items' => [], 'message' => ''],
            'api_history' => ['status' => 'not_requested', 'effective_schedule' => null, 'assignments' => [], 'message' => ''],
            'legacy_schedules' => ['status' => 'not_read', 'items' => []],
            'legacy_rules' => ['status' => 'not_read', 'configured' => false, 'retardo' => null, 'incidencia' => null],
            'legacy_profiles' => ['status' => 'not_read', 'active_count' => null], 'positions' => ['status' => 'not_read', 'items' => []],
            'reader_profiles' => ['status' => 'not_read', 'items' => [], 'partial' => false],
            'device_auth' => ['status' => 'not_integrated', 'message' => 'La autenticación de dispositivos V2 no se consulta desde este catálogo legacy.']];
    }
}
