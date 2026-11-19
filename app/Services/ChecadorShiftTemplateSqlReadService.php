<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Logger;
use App\Models\Usuario;
use Illuminate\Database\Capsule\Manager as Capsule;
use Throwable;

/** Read-only M04 catalog boundary over Portal3's existing MariaDB connection. */
final class ChecadorShiftTemplateSqlReadService
{
    /** @var callable(Usuario,int):bool */
    private $authorizeRead;
    private string $connectionName;

    /** @param callable(Usuario,int):array|bool|null $authorizeRead Test seam; production always uses the active Checador ACL. */
    public function __construct(?callable $authorizeRead = null, string $connectionName = 'default')
    {
        $this->connectionName = in_array($connectionName, ['default', 'checador_m04'], true)
            ? $connectionName
            : 'default';
        $this->authorizeRead = $authorizeRead ?? static function (Usuario $user, int $workplaceId): array {
            $authenticated = Auth::user();
            if ($authenticated === null || (int) ($authenticated->id ?? 0) !== (int) ($user->id ?? 0)) {
                return ['allowed' => false, 'reason' => 'unauthenticated'];
            }
            return ChecadorAccessService::authorizeShiftTemplateCatalogRead($workplaceId);
        };
    }

    /** @return array{ok:bool,data?:array{page:int,per_page:int,items:list<array<string,mixed>>},error?:array{kind:string,status:int}} */
    public function getShiftTemplates(
        Usuario $user,
        int $workplaceId,
        int $page = 1,
        int $perPage = 200,
        string $catalogScope = 'STATION'
    ): array
    {
        $catalogScope = strtoupper(trim($catalogScope));
        if ($workplaceId < 1 || $page < 1 || $page > 10000 || $perPage < 1 || $perPage > 200
            || !in_array($catalogScope, ['STATION', 'GLOBAL'], true)) {
            return self::failure('bad_request', 400);
        }
        $catalogAccess = $this->authorizedCatalogAccess($user, $workplaceId);
        if ($catalogAccess === null) {
            return self::failure('permission_denied', 403);
        }
        $catalogWorkplaceId = $catalogAccess['workplace_id'];
        if ($catalogScope === 'GLOBAL' && !$catalogAccess['include_global_templates']) {
            return self::failure('permission_denied', 403);
        }

        try {
            $connection = Capsule::connection($this->connectionName);
            $scopeFilter = static function ($query) use ($catalogWorkplaceId, $catalogScope): void {
                if ($catalogScope === 'STATION') {
                    $query->where('scope', 'STATION')->where('id_estacion', $catalogWorkplaceId);
                } else {
                    $query->where('scope', 'GLOBAL')->whereNull('id_estacion');
                }
            };
            $latest = $connection->table('op_rh_v2_turnos_plantillas')
                ->select('clave', 'scope', 'id_estacion')
                ->selectRaw('MAX(version) AS latest_version')
                ->where($scopeFilter)
                ->groupBy('clave', 'scope', 'id_estacion');

            $rows = $connection->table('op_rh_v2_turnos_plantillas AS templates')
                ->joinSub($latest, 'latest', static function ($join): void {
                    $join->on('templates.clave', '=', 'latest.clave')
                        ->on('templates.scope', '=', 'latest.scope')
                        ->on('templates.version', '=', 'latest.latest_version')
                        ->whereRaw('COALESCE(templates.id_estacion, 0) = COALESCE(latest.id_estacion, 0)');
                })
                ->orderBy('templates.clave')
                ->orderBy('templates.scope')
                ->orderBy('templates.id_estacion')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get([
                    'templates.id', 'templates.clave', 'templates.version', 'templates.nombre',
                    'templates.tipo_turno', 'templates.scope', 'templates.id_estacion', 'templates.es_activo',
                ]);

            $items = [];
            $keys = [];
            foreach ($rows as $row) {
                $scope = strtoupper((string) $row->scope);
                $stationId = $row->id_estacion === null ? null : (int) $row->id_estacion;
                if (($scope !== $catalogScope)
                    || ($scope === 'GLOBAL' && $stationId !== null)
                    || ($scope === 'STATION' && $stationId !== $catalogWorkplaceId)
                    || !in_array($scope, ['GLOBAL', 'STATION'], true)) {
                    return self::failure('schedule_templates_unavailable', 502);
                }
                $key = (string) $row->clave;
                $keys[] = $key;
                $items[] = [
                    'id' => (int) $row->id,
                    'clave' => $key,
                    'version' => (int) $row->version,
                    'nombre' => (string) $row->nombre,
                    'tipo_turno' => (string) $row->tipo_turno,
                    'scope' => $scope,
                    'id_estacion' => $stationId,
                    'es_activo' => (int) $row->es_activo,
                    'derived_from_global' => null,
                    'station_customization' => null,
                ];
            }

            if ($items !== []) {
                $lineageRows = $connection->table('op_rh_v2_shift_template_station_lineage')
                    ->where('id_estacion', $catalogWorkplaceId)
                    ->where(static function ($query) use ($keys): void {
                        $query->whereIn('source_global_clave', $keys)->orWhereIn('local_clave', $keys);
                    })
                    ->get([
                        'source_global_clave', 'source_global_version', 'source_global_template_id',
                        'local_clave', 'local_template_id',
                    ]);
                $lineageBySource = [];
                $lineageByLocal = [];
                foreach ($lineageRows as $lineage) {
                    $lineageBySource[(string) $lineage->source_global_clave] = $lineage;
                    $lineageByLocal[(string) $lineage->local_clave] = $lineage;
                }
                foreach ($items as &$item) {
                    if ($item['scope'] === 'GLOBAL' && isset($lineageBySource[$item['clave']])) {
                        $lineage = $lineageBySource[$item['clave']];
                        $item['station_customization'] = [
                            'clave' => (string) $lineage->local_clave,
                            'source_version' => (int) $lineage->source_global_version,
                        ];
                    } elseif ($item['scope'] === 'STATION' && isset($lineageByLocal[$item['clave']])) {
                        $lineage = $lineageByLocal[$item['clave']];
                        $item['derived_from_global'] = [
                            'clave' => (string) $lineage->source_global_clave,
                            'version' => (int) $lineage->source_global_version,
                        ];
                    }
                }
                unset($item);
            }

            return ['ok' => true, 'data' => ['page' => $page, 'per_page' => $perPage, 'items' => $items]];
        } catch (Throwable $exception) {
            self::logReadFailure('m04_template_catalog_sql_read', $exception);
            return self::failure('database_unavailable', 503);
        }
    }

    /** @return array{ok:bool,data?:array<string,mixed>,error?:array{kind:string,status:int}} */
    public function getShiftTemplateVersion(
        Usuario $user,
        string $templateKey,
        int $version,
        int $workplaceId,
        int $templateId,
        string $scope,
        ?int $stationId
    ): array {
        $scope = strtoupper(trim($scope));
        if ($templateKey === '' || strlen($templateKey) > 100 || str_contains($templateKey, '/')
            || $version < 1 || $workplaceId < 1 || $templateId < 1
            || !in_array($scope, ['GLOBAL', 'STATION'], true)
            || ($scope === 'GLOBAL' && $stationId !== null)
            || ($scope === 'STATION' && $stationId === null)) {
            return self::failure('bad_request', 400);
        }
        $catalogAccess = $this->authorizedCatalogAccess($user, $workplaceId);
        if ($catalogAccess === null) {
            return self::failure('permission_denied', 403);
        }
        $catalogWorkplaceId = $catalogAccess['workplace_id'];
        if ($scope === 'GLOBAL') {
            if (!$catalogAccess['include_global_templates']) return self::failure('schedule_template_not_found', 404);
        }
        if ($scope === 'STATION' && $stationId !== $catalogWorkplaceId) {
            return self::failure('bad_request', 400);
        }

        try {
            $query = Capsule::connection($this->connectionName)->table('op_rh_v2_turnos_plantillas')
                ->where('id', $templateId)
                ->where('clave', $templateKey)
                ->where('version', $version)
                ->where('scope', $scope);
            $scope === 'GLOBAL' ? $query->whereNull('id_estacion') : $query->where('id_estacion', $catalogWorkplaceId);
            $template = $query->first([
                'id', 'clave', 'version', 'nombre', 'tipo_turno', 'scope', 'id_estacion', 'es_activo',
            ]);
            if ($template === null) return self::failure('schedule_template_not_found', 404);
            if ((int) $template->id !== $templateId
                || (int) $template->version !== $version
                || (string) $template->clave !== $templateKey
                || strtoupper((string) $template->scope) !== $scope
                || ($scope === 'STATION' && (int) ($template->id_estacion ?? 0) !== $catalogWorkplaceId)
                || ($scope === 'GLOBAL' && $template->id_estacion !== null)) {
                return self::failure('schedule_templates_unavailable', 502);
            }

            $segments = Capsule::connection($this->connectionName)->table('op_rh_v2_turnos_segmentos')
                ->where('id_plantilla', $templateId)
                ->orderBy('ordinal')
                ->get([
                    'ordinal', 'tipo_segmento', 'hora_inicio_local', 'hora_fin_local',
                    'dia_offset_inicio', 'dia_offset_fin',
                ])
                ->map(static fn($segment): array => [
                    'ordinal' => (int) $segment->ordinal,
                    'tipo_segmento' => (string) $segment->tipo_segmento,
                    'hora_inicio_local' => (string) $segment->hora_inicio_local,
                    'hora_fin_local' => (string) $segment->hora_fin_local,
                    'dia_offset_inicio' => (int) $segment->dia_offset_inicio,
                    'dia_offset_fin' => (int) $segment->dia_offset_fin,
                ])
                ->all();

            foreach ($segments as $segment) {
                if ($segment['ordinal'] < 1
                    || !in_array($segment['dia_offset_inicio'], [0, 1], true)
                    || !in_array($segment['dia_offset_fin'], [0, 1], true)
                    || preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?\z/', $segment['hora_inicio_local']) !== 1
                    || preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?\z/', $segment['hora_fin_local']) !== 1) {
                    return self::failure('schedule_templates_unavailable', 502);
                }
            }

            $lineage = null;
            if ($scope === 'STATION') {
                $lineage = Capsule::connection($this->connectionName)->table('op_rh_v2_shift_template_station_lineage')
                    ->where('id_estacion', $catalogWorkplaceId)
                    ->where('local_template_id', $templateId)
                    ->first(['source_global_clave', 'source_global_version']);
            }

            return ['ok' => true, 'data' => [
                'id' => (int) $template->id,
                'clave' => (string) $template->clave,
                'version' => (int) $template->version,
                'nombre' => (string) $template->nombre,
                'tipo_turno' => (string) $template->tipo_turno,
                'scope' => (string) $template->scope,
                'id_estacion' => $template->id_estacion === null ? null : (int) $template->id_estacion,
                'es_activo' => (int) $template->es_activo,
                'derived_from_global' => $lineage === null ? null : [
                    'clave' => (string) $lineage->source_global_clave,
                    'version' => (int) $lineage->source_global_version,
                ],
                'segmentos' => $segments,
            ]];
        } catch (Throwable $exception) {
            self::logReadFailure('m04_template_version_sql_read', $exception);
            return self::failure('database_unavailable', 503);
        }
    }

    /** @return array{workplace_id:int,include_global_templates:bool}|null */
    private function authorizedCatalogAccess(Usuario $user, int $requestedWorkplaceId): ?array
    {
        $authorization = ($this->authorizeRead)($user, $requestedWorkplaceId);
        if ($authorization === true) {
            return ['workplace_id' => $requestedWorkplaceId, 'include_global_templates' => false];
        }
        if (!is_array($authorization) || !($authorization['allowed'] ?? false)) {
            return null;
        }
        $catalogWorkplaceId = filter_var($authorization['catalog_workplace_id'] ?? $requestedWorkplaceId, FILTER_VALIDATE_INT);
        // A permission decision may not silently substitute another locality's
        // catalog (notably Autolavado 9 -> Palo Solo 2).
        return $catalogWorkplaceId !== false && $catalogWorkplaceId === $requestedWorkplaceId
            ? [
                'workplace_id' => $requestedWorkplaceId,
                'include_global_templates' => (bool) ($authorization['include_global_templates'] ?? false),
            ]
            : null;
    }

    /** @return array{ok:false,error:array{kind:string,status:int}} */
    private static function failure(string $kind, int $status): array
    {
        return ['ok' => false, 'error' => ['kind' => $kind, 'status' => $status]];
    }

    private static function logReadFailure(string $operation, Throwable $exception): void
    {
        try {
            Logger::warning('Checador M04 SQL read failed', [
                'operation' => $operation,
                'error_kind' => 'database_unavailable',
                'exception_class' => $exception::class,
            ]);
        } catch (Throwable) {
            // Diagnostics must not change a read-only response.
        }
    }
}
