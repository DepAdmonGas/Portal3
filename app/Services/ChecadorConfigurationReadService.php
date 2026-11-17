<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Operativo\RhPuestos;
use App\Models\Operativo\RhLocalidadesPerfil;
use App\Models\Usuario;
use Throwable;

/** Read-only projection of the approved Checador V2 configuration sources. */
final class ChecadorConfigurationReadService
{
    /** @return array<string,mixed> */
    public function read(Usuario $user, int $workplaceId, bool $demoMode, ?int $workerId = null, string $date = ''): array
    {
        $scope = ChecadorAccessService::authorize($workplaceId);
        if (!($scope['allowed'] ?? false)) return self::emptyResult('workplace_out_of_scope');

        $policyRead = ChecadorAccessService::authorizeAttendancePolicy($workplaceId, 'leer');
        $policyEdit = ChecadorAccessService::authorizeAttendancePolicy($workplaceId, 'editar');
        $scheduleRead = ChecadorAccessService::authorizeScheduling($workplaceId, 'leer');
        $scheduleEdit = ChecadorAccessService::authorizeScheduling($workplaceId, 'editar');
        $templateEdit = ChecadorAccessService::authorizeStationTemplateAdministration($workplaceId);
        $readerRead = ChecadorAccessService::authorizeReaderProfiles($workplaceId, 'leer');
        $readerEdit = ChecadorAccessService::authorizeReaderProfiles($workplaceId, 'editar');
        $positionRead = ChecadorAccessService::authorizePositionCatalog($workplaceId, 'leer');
        $positionEdit = ChecadorAccessService::authorizePositionCatalog($workplaceId, 'editar');
        $deviceRead = ChecadorAccessService::authorizeAdministrativeDevices($workplaceId);

        $result = self::emptyResult('ready');
        $result['demo_mode'] = $demoMode;
        $result['workplace_id'] = $workplaceId;
        $departmentIds = array_map('intval', array_column($scope['department_workplaces'] ?? [], 'id'));
        $result['workplace_kind'] = in_array($workplaceId, $departmentIds, true) ? 'department' : 'station';
        $result['permissions'] = [
            'read' => (bool) ($policyRead['allowed'] ?? false),
            'policy_read' => (bool) ($policyRead['allowed'] ?? false),
            'policy_edit' => (bool) ($policyEdit['allowed'] ?? false),
            'legacy_create' => false,
            'legacy_edit' => false,
            'legacy_delete' => false,
            'v2_schedule_edit' => (bool) ($scheduleEdit['allowed'] ?? false),
            'station_template_edit' => (bool) ($templateEdit['allowed'] ?? false),
            'schedule_read' => (bool) ($scheduleRead['allowed'] ?? false),
            'reader_profile_read' => (bool) ($readerRead['allowed'] ?? false),
            'reader_profile_edit' => (bool) ($readerEdit['allowed'] ?? false),
            'positions_read' => (bool) ($positionRead['allowed'] ?? false),
            'positions_create' => (bool) ($positionEdit['allowed'] ?? false),
            'positions_edit' => (bool) ($positionEdit['allowed'] ?? false),
            'positions_delete' => false,
            'device_read' => (bool) ($deviceRead['allowed'] ?? false),
        ];
        $result['api_templates'] = ['status' => 'permission_denied', 'items' => [], 'message' => ''];
        $result['api_history'] = ['status' => 'not_requested', 'effective_schedule' => null, 'assignments' => [], 'message' => ''];
        $result['legacy_schedules'] = ['status' => 'not_used_as_v2_source', 'items' => []];
        $result['legacy_rules'] = ['status' => 'not_used_as_v2_source', 'configured' => false, 'retardo' => null, 'incidencia' => null];
        $result['legacy_profiles'] = ['status' => 'not_used_as_v2_source', 'active_count' => null];
        $isStation = $result['workplace_kind'] === 'station';
        $result['reader_profiles'] = $demoMode
            ? ['status' => 'not_read_in_demo', 'items' => []]
            : (!$isStation ? ['status' => 'not_applicable', 'items' => []]
                : self::readReaderProfiles($workplaceId, (bool) ($readerRead['allowed'] ?? false)));
        $result['positions'] = $demoMode
            ? ['status' => 'not_read_in_demo', 'items' => []]
            : self::readPositions((bool) ($positionRead['allowed'] ?? false));
        $result['device_auth'] = ['status' => $demoMode ? 'not_read_in_demo' : 'permission_denied', 'completeness' => 'UNKNOWN', 'items' => [], 'partial_reasons' => []];

        if ($demoMode) {
            $result['status'] = 'demo_isolated';
            $result['api_templates'] = self::readTemplates($user, $workplaceId, true, (bool) ($scheduleRead['allowed'] ?? false));
            $result['policy'] = ['status' => 'not_read_in_demo', 'data' => null];
            if ($workerId !== null && self::isCivilDate($date) && ($scheduleRead['allowed'] ?? false)) {
                $result['api_history'] = self::readHistory($user, $workerId, $workplaceId, $date, true);
            } elseif ($workerId !== null && self::isCivilDate($date)) {
                $result['api_history'] = ['status' => 'permission_denied', 'effective_schedule' => null, 'assignments' => [], 'message' => 'Se requiere autorización de lectura de programación.'];
            }
            $result['positions'] = ['status' => 'not_read_in_demo', 'items' => []];
            return $result;
        }

        $result['api_templates'] = self::readTemplates($user, $workplaceId, false, (bool) ($scheduleRead['allowed'] ?? false));
        if (!$isStation) {
            $result['policy'] = ['status' => 'not_applicable', 'data' => null];
        } elseif ($policyRead['allowed'] ?? false) {
            try {
                $policy = (new ChecadorDailyReadService())->readCurrentAttendancePolicy($user, $workplaceId);
                $result['policy'] = ($policy['ok'] ?? false)
                    ? ['status' => 'ready', 'data' => $policy['data']]
                    : ['status' => (string) ($policy['error']['kind'] ?? 'unavailable'), 'data' => null];
            } catch (Throwable) {
                $result['policy'] = ['status' => 'unavailable', 'data' => null];
            }
        } else {
            $result['policy'] = ['status' => 'permission_denied', 'data' => null];
        }

        if ($workerId !== null && self::isCivilDate($date)) {
            $result['api_history'] = ($scheduleRead['allowed'] ?? false)
                ? self::readHistory($user, $workerId, $workplaceId, $date, false)
                : ['status' => 'permission_denied', 'effective_schedule' => null, 'assignments' => [], 'message' => 'Se requiere autorización de lectura de programación.'];
        }

        if ($deviceRead['allowed'] ?? false) {
            try {
                $deviceResult = (new ChecadorDailyReadService())->readAdministrativeDevices($user, $workplaceId);
                $result['device_auth'] = ($deviceResult['ok'] ?? false)
                    ? array_merge(['status' => 'ready'], $deviceResult['data'])
                    : ['status' => (string) ($deviceResult['error']['kind'] ?? 'unavailable'), 'completeness' => 'UNKNOWN', 'items' => [], 'partial_reasons' => []];
            } catch (Throwable) {
                $result['device_auth'] = ['status' => 'unavailable', 'completeness' => 'UNKNOWN', 'items' => [], 'partial_reasons' => []];
            }
        }

        return $result;
    }

    /** @return array{status:string,items:list<array{id:int,usuario:string}>} */
    private static function readReaderProfiles(int $workplaceId, bool $allowed): array
    {
        if (!$allowed) return ['status' => 'permission_denied', 'items' => []];
        $physicalStationId = ChecadorAccessService::resolveActiveStationIdForLocality($workplaceId);
        if ($physicalStationId === null) return ['status' => 'mapping_unavailable', 'items' => []];
        try {
            $profiles = RhLocalidadesPerfil::query()->where('id_estacion', $physicalStationId)
                ->where('status', 1)->orderBy('id')->get(['id', 'usuario']);
            $items = [];
            foreach ($profiles as $profile) {
                $username = \App\Services\BiometricosService::decrypt((string) $profile->usuario);
                if ($username === '' || mb_strlen($username) > 120) return ['status' => 'unavailable', 'items' => []];
                $items[] = ['id' => (int) $profile->id, 'usuario' => $username];
            }
            return ['status' => 'station_scoped', 'items' => $items];
        } catch (Throwable) {
            return ['status' => 'unavailable', 'items' => []];
        }
    }

    /** @return array<string,mixed> */
    private static function readTemplates(Usuario $user, int $workplaceId, bool $demoMode, bool $allowed): array
    {
        if (!$allowed) return ['status' => 'permission_denied', 'items' => [], 'message' => 'Se requiere el permiso de lectura de programación de Checador.'];
        try {
            $reader = new ChecadorDailyReadService();
            // The caller supplies only the selected, server-authorized canonical
            // locality. API V2 validates it again and scopes both list and detail reads.
            $pageSize = 100;
            $maximumPages = 10;
            $items = [];
            $allPagesRead = false;
            for ($page = 1; $page <= $maximumPages; $page++) {
                $templates = $reader->getShiftTemplates($user, $page, $pageSize, $workplaceId);
                if (!($templates['ok'] ?? false)) {
                    return ['status' => 'unavailable', 'items' => [], 'message' => 'El catálogo contractual de plantillas no está disponible.'];
                }
                $pageItems = $templates['data']['items'] ?? [];
                foreach ($pageItems as $template) {
                    $scope = strtoupper((string) ($template['scope'] ?? ''));
                    $stationId = $template['id_estacion'] ?? null;
                    if (!in_array($scope, ['GLOBAL', 'STATION'], true)
                        || ($scope === 'GLOBAL' && $stationId !== null)
                        || ($scope === 'STATION' && (int) $stationId !== $workplaceId)) {
                        return ['status' => 'unavailable', 'items' => [], 'message' => 'La API devolvió una plantilla fuera del alcance canónico seleccionado.'];
                    }
                }
                array_push($items, ...$pageItems);
                if (count($pageItems) < $pageSize) {
                    $allPagesRead = true;
                    break;
                }
            }

            $detailsComplete = true;
            foreach ($items as $index => $template) {
                $detail = $reader->getShiftTemplateVersion($user, (string) $template['clave'], (int) $template['version'], $workplaceId);
                if (!($detail['ok'] ?? false)) {
                    $items[$index]['segments'] = null;
                    $detailsComplete = false;
                    continue;
                }
                $detailData = $detail['data'];
                if ((int) ($detailData['id'] ?? 0) !== (int) ($template['id'] ?? 0)
                    || (string) ($detailData['scope'] ?? '') !== (string) ($template['scope'] ?? '')
                    || ($detailData['scope'] === 'STATION' && (int) ($detailData['id_estacion'] ?? 0) !== $workplaceId)
                    || ($detailData['scope'] === 'GLOBAL' && ($detailData['id_estacion'] ?? null) !== null)) {
                    return ['status' => 'unavailable', 'items' => [], 'message' => 'El detalle de una plantilla no coincide con el catálogo del centro seleccionado.'];
                }
                $items[$index]['segments'] = $detailData['segmentos'];
            }
            $partial = !$allPagesRead || !$detailsComplete;
            return ['status' => $demoMode ? ($partial ? 'demo_fixture_partial' : 'demo_fixture') : ($partial ? 'partial' : 'ready'),
                'items' => $items, 'message' => $demoMode
                    ? 'Catálogo sintético local; no representa plantillas ni asignaciones persistidas.'
                    : 'Catálogo leído con el contexto canónico autorizado del centro seleccionado. La API mantiene la distinción entre plantillas globales y personalizadas por estación.'];
        } catch (Throwable) {
            return ['status' => 'unavailable', 'items' => [], 'message' => 'No fue posible consultar el catálogo de plantillas.'];
        }
    }

    /** @return array<string,mixed> */
    private static function readHistory(Usuario $user, int $workerId, int $workplaceId, string $date, bool $demoMode): array
    {
        try {
            $reader = new ChecadorDailyReadService();
            $effective = $reader->getEffectiveSchedule($user, $workerId, $workplaceId, $date);
            $history = $reader->getShiftAssignments($user, $workerId, $workplaceId, 1, 50);
            if (($effective['ok'] ?? false) && ($history['ok'] ?? false)) {
                return ['status' => $demoMode ? 'demo_fixture' : (count($history['data']['items'] ?? []) === 50 ? 'possibly_partial' : 'ready'),
                    'effective_schedule' => $effective['data'], 'assignments' => $history['data']['items'] ?? [],
                    'message' => $demoMode ? 'Respuesta sintética local; no es historial persistido.' : 'Lectura API V2 por trabajador, localidad y fecha.'];
            }
            return ['status' => 'unavailable', 'effective_schedule' => null, 'assignments' => [], 'message' => 'No fue posible confirmar el horario efectivo y su historial.'];
        } catch (Throwable) {
            return ['status' => 'unavailable', 'effective_schedule' => null, 'assignments' => [], 'message' => 'No fue posible consultar el historial autorizado.'];
        }
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

    private static function isCivilDate(string $date): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('UTC'));
        return $parsed !== false && $parsed->format('Y-m-d') === $date && !array_filter(\DateTimeImmutable::getLastErrors() ?: []);
    }

    /** @return array<string,mixed> */
    private static function emptyResult(string $status): array
    {
        return ['status' => $status, 'demo_mode' => false, 'workplace_id' => null, 'workplace_kind' => 'unknown',
            'permissions' => [], 'mapping' => ['status' => 'not_used', 'physical_station_id' => null, 'name' => null],
            'policy' => ['status' => 'not_requested', 'data' => null],
            'api_templates' => ['status' => 'not_requested', 'items' => [], 'message' => ''],
            'api_history' => ['status' => 'not_requested', 'effective_schedule' => null, 'assignments' => [], 'message' => ''],
            'legacy_schedules' => ['status' => 'not_used_as_v2_source', 'items' => []],
            'legacy_rules' => ['status' => 'not_used_as_v2_source', 'configured' => false, 'retardo' => null, 'incidencia' => null],
            'legacy_profiles' => ['status' => 'not_used_as_v2_source', 'active_count' => null],
            'reader_profiles' => ['status' => 'not_integrated', 'items' => []],
            'positions' => ['status' => 'not_requested', 'items' => []],
            'device_auth' => ['status' => 'not_integrated', 'message' => 'La lectura de autenticación de dispositivos no está integrada en esta vista.']];
    }
}
