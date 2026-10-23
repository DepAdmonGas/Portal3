<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Estacion;
use App\Models\Operativo\RhLocalidad;
use App\Models\Operativo\RhLocalidadesHorario;
use App\Models\Operativo\RhLocalidadesPerfil;
use App\Models\Operativo\RhPuestos;
use App\Models\Usuario;
use Throwable;

/** Read-only configuration projection for the Checador V2 shell. */
final class ChecadorConfigurationReadService
{
    /**
     * Load separately-labelled API V2 and legacy configuration sources.
     * Demo mode never reads legacy operational tables.
     *
     * @return array<string,mixed>
     */
    public function read(Usuario $user, int $workplaceId, bool $demoMode, ?int $workerId = null, string $date = ''): array
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
        $positionsRead = BiometricosService::hasActionPermission('recursos-humanos', 'leer');
        $positionsCreate = BiometricosService::hasActionPermission('recursos-humanos', 'crear');
        $positionsEdit = BiometricosService::hasActionPermission('recursos-humanos', 'editar');
        $positionsDelete = BiometricosService::hasActionPermission('recursos-humanos', 'eliminar');

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
                'positions_create' => $positionsRead && $positionsCreate,
                'positions_edit' => $positionsRead && $positionsEdit,
                'positions_delete' => $positionsRead && $positionsDelete,
            ],
            'mapping' => ['status' => 'not_checked', 'physical_station_id' => null, 'name' => null, 'legacy_scope_authorized' => false],
            'api_templates' => ['status' => 'not_requested', 'items' => [], 'message' => ''],
            'api_history' => ['status' => 'not_requested', 'effective_schedule' => null, 'assignments' => [], 'message' => ''],
            'legacy_schedules' => ['status' => 'not_read', 'items' => []],
            'legacy_rules' => ['status' => 'not_read', 'configured' => false, 'retardo' => null, 'incidencia' => null],
            'legacy_profiles' => ['status' => 'not_read', 'active_count' => null],
            'positions' => ['status' => 'not_read', 'items' => []],
            'device_auth' => ['status' => 'not_integrated', 'message' => 'La autenticación de dispositivos V2 no se consulta desde este catálogo legacy.'],
        ];

        if ($demoMode) {
            $result['status'] = 'demo_isolated';
            $result['api_templates'] = ['status' => $canRead ? 'pending' : 'permission_denied', 'items' => [],
                'message' => $canRead ? 'El catálogo se resolverá únicamente con la fuente local de demostración.' : 'Se requiere el permiso biometricos.leer.'];
            if ($canRead) {
                $reader = new ChecadorDailyReadService();
                $templates = $reader->getShiftTemplates($user, 1, 50);
                if (($templates['ok'] ?? false) === true) {
                    $items = $templates['data']['items'];
                    $detailsComplete = true;
                    foreach (array_slice($items, 0, 10) as $index => $template) {
                        $detail = $reader->getShiftTemplateVersion($user, (string) $template['clave'], (int) $template['version']);
                        if (($detail['ok'] ?? false) === true) {
                            $items[$index]['segments'] = $detail['data']['segmentos'];
                        } else {
                            $detailsComplete = false;
                            $items[$index]['segments'] = null;
                        }
                    }
                    $partial = count($items) > 10 || !$detailsComplete;
                    $result['api_templates'] = ['status' => $partial ? 'demo_fixture_partial' : 'demo_fixture', 'items' => $items,
                        'message' => $partial
                            ? 'Catálogo sintético local; algunos detalles no están disponibles o exceden el límite de vista. No representa plantillas ni asignaciones persistidas.'
                            : 'Catálogo sintético local; no representa plantillas ni asignaciones persistidas.'];
                } else {
                    $result['api_templates'] = ['status' => 'unavailable', 'items' => [], 'message' => 'El escenario demo no ofrece un catálogo de turnos para este contexto.'];
                }
                if ($workerId !== null && self::isCivilDate($date)) {
                    if (ChecadorAccessService::authorizeScheduling($workplaceId, 'leer')['allowed'] ?? false) {
                        $effective = $reader->getEffectiveSchedule($user, $workerId, $workplaceId, $date);
                        $history = $reader->getShiftAssignments($user, $workerId, $workplaceId, 1, 50);
                        if (($effective['ok'] ?? false) && ($history['ok'] ?? false)) {
                            $result['api_history'] = ['status' => 'demo_fixture', 'effective_schedule' => $effective['data'],
                                'assignments' => $history['data']['items'],
                                'message' => 'Respuesta sintética local de los lectores V2; no es historial persistido.'];
                        } else {
                            $result['api_history'] = ['status' => 'unavailable', 'effective_schedule' => null, 'assignments' => [],
                                'message' => 'El escenario demo no ofrece horario efectivo e historial para este contexto.'];
                        }
                    } else {
                        $result['api_history'] = ['status' => 'permission_denied', 'effective_schedule' => null, 'assignments' => [],
                            'message' => 'Se requiere autorización de lectura de programación para consultar este historial.'];
                    }
                }
            }
            $result['mapping'] = ['status' => 'not_read_in_demo', 'physical_station_id' => null, 'name' => null];
            $result['legacy_schedules'] = ['status' => 'not_read_in_demo', 'items' => []];
            $result['legacy_rules'] = ['status' => 'not_read_in_demo', 'configured' => false, 'retardo' => null, 'incidencia' => null];
            $result['legacy_profiles'] = ['status' => 'not_read_in_demo', 'active_count' => null];
            $result['positions'] = ['status' => $positionsRead ? 'not_read_in_demo' : 'permission_denied', 'items' => []];
            if ($workerId !== null && self::isCivilDate($date) && !$canRead) {
                $result['api_history'] = ['status' => 'permission_denied', 'effective_schedule' => null, 'assignments' => [],
                    'message' => 'Se requiere el permiso biometricos.leer para consultar el horario demo.'];
            }
            return $result;
        }

        if (!$canRead) {
            $result['api_templates'] = ['status' => 'permission_denied', 'items' => [], 'message' => 'Se requiere biometricos.leer para consultar turnos V2.'];
            $result['legacy_schedules'] = ['status' => 'permission_denied', 'items' => []];
            $result['legacy_rules'] = ['status' => 'permission_denied', 'configured' => false, 'retardo' => null, 'incidencia' => null];
            $result['legacy_profiles'] = ['status' => 'permission_denied', 'active_count' => null];
            $result['positions'] = self::readPositions($positionsRead);
            return $result;
        }

        $reader = new ChecadorDailyReadService();
        $templates = $reader->getShiftTemplates($user, 1, 50);
        if (($templates['ok'] ?? false) === true) {
            $items = $templates['data']['items'];
            // Keep this configuration page bounded; the full catalogue stays
            // available from the existing weekly scheduling screen.
            $detailLimit = 10;
            $detailsComplete = true;
            foreach (array_slice($items, 0, $detailLimit) as $index => $template) {
                $detail = $reader->getShiftTemplateVersion($user, (string) $template['clave'], (int) $template['version']);
                if (($detail['ok'] ?? false) === true) {
                    $items[$index]['segments'] = $detail['data']['segmentos'];
                } else {
                    $detailsComplete = false;
                    $items[$index]['segments'] = null;
                }
            }
            $partial = count($items) > $detailLimit || !$detailsComplete;
            $result['api_templates'] = ['status' => $partial ? 'partial' : 'ready',
                'items' => $items, 'message' => $partial
                    ? 'La vista limita los detalles a 10 y/o alguna respuesta de detalle no estuvo disponible; consulta la programación semanal para el catálogo completo y su contexto individual.'
                    : 'Catálogo contractual de API V2. El catálogo no asigna turnos por sí solo.'];
        } else {
            $result['api_templates'] = ['status' => 'unavailable', 'items' => [], 'message' => 'No se pudo consultar el catálogo contractual de turnos V2.'];
        }

        if ($workerId !== null && self::isCivilDate($date)
            && (ChecadorAccessService::authorizeScheduling($workplaceId, 'leer')['allowed'] ?? false)) {
            $effective = $reader->getEffectiveSchedule($user, $workerId, $workplaceId, $date);
            $history = $reader->getShiftAssignments($user, $workerId, $workplaceId, 1, 50);
            if (($effective['ok'] ?? false) === true && ($history['ok'] ?? false) === true) {
                $result['api_history'] = ['status' => count($history['data']['items']) === 50 ? 'possibly_partial' : 'ready',
                    'effective_schedule' => $effective['data'], 'assignments' => $history['data']['items'],
                    'message' => 'Lectura API V2 por trabajador, localidad y fecha. No se calcula ni modifica la asignación.'];
            } else {
                $result['api_history'] = ['status' => 'unavailable', 'effective_schedule' => null, 'assignments' => [],
                    'message' => 'No se pudo confirmar el horario efectivo y su historial en API V2.'];
            }
        } elseif ($workerId !== null && self::isCivilDate($date)) {
            $result['api_history'] = ['status' => 'permission_denied', 'effective_schedule' => null, 'assignments' => [],
                'message' => 'Se requiere autorización de lectura de programación para consultar este historial.'];
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
                    $schedules = RhLocalidadesHorario::query()->where('id_estacion', $physicalStationId)
                        ->orderBy('id')->get(['id', 'titulo', 'hora_entrada', 'hora_salida']);
                    $result['legacy_schedules'] = ['status' => 'legacy_catalog', 'items' => $schedules->map(static fn($row): array => [
                        'id' => (int) $row->id, 'title' => (string) $row->titulo,
                        'start' => (string) $row->hora_entrada, 'end' => (string) $row->hora_salida,
                    ])->all()];
                } catch (Throwable) {
                    $result['legacy_schedules'] = ['status' => 'unavailable', 'items' => []];
                }
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
                try {
                    $profileCount = RhLocalidadesPerfil::query()->where('id_estacion', $physicalStationId)->where('status', 1)->count();
                    $result['legacy_profiles'] = ['status' => 'legacy_metadata_only', 'active_count' => $profileCount];
                } catch (Throwable) {
                    $result['legacy_profiles'] = ['status' => 'unavailable', 'active_count' => null];
                }
            }
        }

        $result['positions'] = self::readPositions($positionsRead);

        return $result;
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

    /** @return array{status:string,physical_station_id:?int,name:?string} */
    private static function resolveUniquePhysicalStation(int $workplaceId): array
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

    private static function isCivilDate(string $date): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('UTC'));
        return $parsed !== false && $parsed->format('Y-m-d') === $date
            && !array_filter(\DateTimeImmutable::getLastErrors() ?: []);
    }

    private static function emptyResult(string $status): array
    {
        return ['status' => $status, 'demo_mode' => false, 'workplace_id' => null, 'workplace_kind' => 'unknown',
            'permissions' => ['read' => false, 'legacy_create' => false, 'legacy_edit' => false, 'legacy_delete' => false, 'v2_schedule_edit' => false,
                'positions_read' => false, 'positions_create' => false, 'positions_edit' => false, 'positions_delete' => false],
            'mapping' => ['status' => 'not_checked', 'physical_station_id' => null, 'name' => null],
            'api_templates' => ['status' => 'not_requested', 'items' => [], 'message' => ''],
            'api_history' => ['status' => 'not_requested', 'effective_schedule' => null, 'assignments' => [], 'message' => ''],
            'legacy_schedules' => ['status' => 'not_read', 'items' => []],
            'legacy_rules' => ['status' => 'not_read', 'configured' => false, 'retardo' => null, 'incidencia' => null],
            'legacy_profiles' => ['status' => 'not_read', 'active_count' => null], 'positions' => ['status' => 'not_read', 'items' => []],
            'device_auth' => ['status' => 'not_integrated', 'message' => 'La autenticación de dispositivos V2 no se consulta desde este catálogo legacy.']];
    }
}
