<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Logger;
use App\Models\Usuario;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Capsule\Manager as Capsule;
use Throwable;

/** M03 read boundary over persisted V2 assignments and templates. */
final class ChecadorWeeklyScheduleSqlReadService
{
    /** @var callable(Usuario,int):bool */
    private $authorizeRead;

    /** @param callable(Usuario,int):bool|null $authorizeRead Test seam; production binds the active session and M03 ACL. */
    public function __construct(?callable $authorizeRead = null)
    {
        $this->authorizeRead = $authorizeRead ?? static function (Usuario $user, int $workplaceId): bool {
            $authenticated = Auth::user();
            return $authenticated !== null
                && (int) ($authenticated->id ?? 0) === (int) ($user->id ?? 0)
                && (ChecadorAccessService::authorizeScheduling($workplaceId, 'leer')['allowed'] ?? false) === true;
        };
    }

    /** @return array{ok:bool,data?:array,error?:array{kind:string,status:int}} */
    public function readWeek(Usuario $user, int $workplaceId, string $monday, array $workers): array
    {
        if ($workplaceId < 1 || ChecadorWeeklyScheduleService::monday($monday) !== $monday) {
            return self::failure('bad_request', 400);
        }
        if (!(($this->authorizeRead)($user, $workplaceId))) {
            return self::failure('permission_denied', 403);
        }

        $workerIds = array_values(array_unique(array_filter(array_map(
            static fn(array $worker): int => (int) ($worker['worker_id'] ?? 0),
            array_filter($workers, 'is_array')
        ), static fn(int $id): bool => $id > 0)));
        if (count($workerIds) > ChecadorAttendanceRosterService::MAX_ROSTER_ROWS) {
            return self::failure('roster_limit_exceeded', 422);
        }
        try {
            $connection = Capsule::connection();
            $weekEnd = (new DateTimeImmutable($monday, new DateTimeZone('UTC')))->modify('+7 days')->format('Y-m-d');
            $assignments = $connection->table('op_rh_v2_turnos_asignaciones')
                ->where('id_estacion', $workplaceId)
                ->whereIn('id_personal', $workerIds)
                ->where('valido_desde', '<', $weekEnd)
                ->where(static function ($query) use ($monday): void {
                    $query->whereNull('valido_hasta')->orWhere('valido_hasta', '>', $monday);
                })
                ->orderBy('id_personal')->orderBy('valido_desde')->orderBy('id')
                ->get([
                    'id', 'id_personal', 'id_estacion', 'id_plantilla', 'tipo_asignacion',
                    'dia_semana', 'valido_desde', 'valido_hasta', 'es_activo', 'motivo',
                ]);
            $assignmentsByWorker = array_fill_keys($workerIds, []);
            $templateIds = [];
            foreach ($assignments as $row) {
                $assignment = [
                    'id' => (int) $row->id,
                    'id_personal' => (int) $row->id_personal,
                    'id_estacion' => (int) $row->id_estacion,
                    'id_plantilla' => $row->id_plantilla === null ? null : (int) $row->id_plantilla,
                    'tipo_asignacion' => strtoupper((string) $row->tipo_asignacion),
                    'dia_semana' => strtoupper((string) $row->dia_semana),
                    'valido_desde' => (string) $row->valido_desde,
                    'valido_hasta' => $row->valido_hasta === null ? null : (string) $row->valido_hasta,
                    'es_activo' => (int) $row->es_activo,
                    'motivo' => $row->motivo === null ? null : (string) $row->motivo,
                ];
                $assignmentsByWorker[$assignment['id_personal']][] = $assignment;
                if ($assignment['id_plantilla'] !== null) $templateIds[$assignment['id_plantilla']] = true;
            }

            $catalogQuery = $connection->table('op_rh_v2_turnos_plantillas AS p');
            $catalogQuery->whereRaw('p.version = (SELECT MAX(p2.version) FROM op_rh_v2_turnos_plantillas p2 WHERE p2.clave=p.clave AND p2.scope=p.scope AND COALESCE(p2.id_estacion,0)=COALESCE(p.id_estacion,0))');
            if ($workplaceId === ChecadorAccessService::AUTOLAVADO_LOCALITY_ID) {
                $catalogQuery->where('p.scope', 'STATION')->where('p.id_estacion', $workplaceId);
            } else {
                $catalogQuery->where(static function ($scope) use ($workplaceId): void {
                    $scope->where(static function ($global): void { $global->where('p.scope', 'GLOBAL')->whereNull('p.id_estacion'); })
                        ->orWhere(static function ($station) use ($workplaceId): void { $station->where('p.scope', 'STATION')->where('p.id_estacion', $workplaceId); });
                });
            }
            $catalogRows = $catalogQuery->orderBy('p.nombre')->get([
                'p.id', 'p.clave', 'p.version', 'p.nombre', 'p.tipo_turno', 'p.scope', 'p.id_estacion', 'p.es_activo',
            ]);
            $templatesById = [];
            foreach ($catalogRows as $row) {
                if ((int) $row->es_activo === 1) $templateIds[(int) $row->id] = true;
            }
            $allIds = array_keys($templateIds);
            if ($allIds !== []) {
                $templateRows = $connection->table('op_rh_v2_turnos_plantillas')
                    ->whereIn('id', $allIds)->get([
                        'id', 'clave', 'version', 'nombre', 'tipo_turno', 'scope', 'id_estacion', 'es_activo',
                    ]);
                foreach ($templateRows as $row) {
                    $id = (int) $row->id;
                    if (($row->scope === 'STATION' && (int) $row->id_estacion !== $workplaceId)
                        || ($row->scope === 'GLOBAL' && $row->id_estacion !== null)
                        || !in_array((string) $row->scope, ['GLOBAL', 'STATION'], true)) {
                        return self::failure('template_scope_conflict', 502);
                    }
                    $templatesById[$id] = [
                        'id' => $id, 'clave' => (string) $row->clave, 'version' => (int) $row->version,
                        'nombre' => (string) $row->nombre, 'tipo_turno' => (string) $row->tipo_turno,
                        'scope' => (string) $row->scope, 'id_estacion' => $row->id_estacion === null ? null : (int) $row->id_estacion,
                        'es_activo' => (int) $row->es_activo, 'segmentos' => [],
                    ];
                }
                $segments = $connection->table('op_rh_v2_turnos_segmentos')->whereIn('id_plantilla', $allIds)
                    ->orderBy('id_plantilla')->orderBy('ordinal')->get([
                        'id_plantilla', 'ordinal', 'tipo_segmento', 'hora_inicio_local', 'hora_fin_local',
                        'dia_offset_inicio', 'dia_offset_fin',
                    ]);
                foreach ($segments as $segment) {
                    $id = (int) $segment->id_plantilla;
                    if (!isset($templatesById[$id])) continue;
                    $templatesById[$id]['segmentos'][] = [
                        'ordinal' => (int) $segment->ordinal, 'tipo_segmento' => (string) $segment->tipo_segmento,
                        'hora_inicio_local' => (string) $segment->hora_inicio_local,
                        'hora_fin_local' => (string) $segment->hora_fin_local,
                        'dia_offset_inicio' => (int) $segment->dia_offset_inicio,
                        'dia_offset_fin' => (int) $segment->dia_offset_fin,
                    ];
                }
                $lineageRows = $connection->table('op_rh_v2_shift_template_station_lineage')
                    ->where('id_estacion', $workplaceId)
                    ->where(static function ($query) use ($allIds): void {
                        $query->whereIn('source_global_template_id', $allIds)
                            ->orWhereIn('local_template_id', $allIds);
                    })
                    ->get([
                        'source_global_template_id', 'source_global_clave', 'source_global_version',
                        'local_template_id', 'local_clave',
                    ]);
                foreach ($lineageRows as $lineage) {
                    $globalId = (int) $lineage->source_global_template_id;
                    $localId = (int) $lineage->local_template_id;
                    if (isset($templatesById[$globalId])) {
                        $templatesById[$globalId]['station_customization'] = [
                            'clave' => (string) $lineage->local_clave,
                            'source_version' => (int) $lineage->source_global_version,
                        ];
                    }
                    if (isset($templatesById[$localId])) {
                        $templatesById[$localId]['derived_from_global'] = [
                            'clave' => (string) $lineage->source_global_clave,
                            'version' => (int) $lineage->source_global_version,
                        ];
                    }
                }
            }
            foreach ($assignmentsByWorker as $workerAssignments) foreach ($workerAssignments as $assignment) {
                $templateId = $assignment['id_plantilla'];
                if ($templateId !== null && (!isset($templatesById[$templateId]) || $templatesById[$templateId]['segmentos'] === [])) {
                    return self::failure('assignment_template_incomplete', 502);
                }
            }
            $selectable = [];
            foreach ($catalogRows as $row) {
                $id = (int) $row->id;
                if ((int) $row->es_activo === 1) {
                    if (!isset($templatesById[$id]) || $templatesById[$id]['segmentos'] === []) {
                        return self::failure('catalog_template_incomplete', 502);
                    }
                    $selectable[] = $templatesById[$id];
                }
            }
            return ['ok' => true, 'data' => [
                'assignments_by_worker' => $assignmentsByWorker,
                'templates' => $selectable,
                'templates_by_id' => $templatesById,
                'status' => 'ready',
            ]];
        } catch (Throwable $exception) {
            Logger::warning('Checador M03 SQL weekly read failed', [
                'operation' => 'm03_week_read', 'workplace_id' => $workplaceId,
                'exception' => get_class($exception),
            ]);
            return self::failure('database_unavailable', 503);
        }
    }

    /**
     * Read persisted assignment history for one already scope-verified worker.
     * This returns assignment facts only; Portal3 does not resolve an effective
     * schedule or infer attendance outcomes.
     *
     * @return array{ok:bool,data?:array,error?:array{kind:string,status:int}}
     */
    public function readAssignmentHistory(
        Usuario $user,
        int $workplaceId,
        int $workerId,
        int $page = 1,
        int $perPage = 50,
        ?string $localDate = null
    ): array {
        if ($workplaceId < 1 || $workerId < 1 || $page < 1 || $page > 10000
            || $perPage < 1 || $perPage > 200
            || ($localDate !== null && !self::isCivilDate($localDate))) {
            return self::failure('bad_request', 400);
        }
        if (!(($this->authorizeRead)($user, $workplaceId))) {
            return self::failure('permission_denied', 403);
        }

        try {
            $connection = Capsule::connection();
            $historyQuery = $connection->table('op_rh_v2_turnos_asignaciones')
                ->where('id_personal', $workerId)->where('id_estacion', $workplaceId);
            $historyRows = (clone $historyQuery)->orderByDesc('valido_desde')->orderByDesc('id')
                ->forPage($page, $perPage)->get([
                    'id', 'id_personal', 'id_estacion', 'id_plantilla', 'tipo_asignacion',
                    'dia_semana', 'valido_desde', 'valido_hasta', 'es_activo', 'registrado_at_utc', 'motivo',
                ]);
            $historyItems = $historyRows->map(static fn(object $row): array => self::normalizeAssignmentRow($row))->all();

            $dateAssignments = [];
            if ($localDate !== null) {
                $dateRows = $connection->table('op_rh_v2_turnos_asignaciones')
                    ->where('id_personal', $workerId)->where('id_estacion', $workplaceId)
                    ->where('valido_desde', '<=', $localDate)
                    ->where(static function ($query) use ($localDate): void {
                        $query->where(static function ($active) use ($localDate): void {
                            $active->where('es_activo', 1)->where(static function ($valid) use ($localDate): void {
                                $valid->whereNull('valido_hasta')->orWhere('valido_hasta', '>', $localDate);
                            });
                        })->orWhere(static function ($closed) use ($localDate): void {
                            $closed->where('es_activo', 0)->whereNotNull('valido_hasta')
                                ->whereColumn('valido_hasta', '>', 'valido_desde')
                                ->where('valido_hasta', '>', $localDate);
                        });
                    })
                    ->orderByDesc('valido_desde')->orderByDesc('id')->limit(200)
                    ->get([
                        'id', 'id_personal', 'id_estacion', 'id_plantilla', 'tipo_asignacion',
                        'dia_semana', 'valido_desde', 'valido_hasta', 'es_activo', 'registrado_at_utc', 'motivo',
                    ]);
                $dateAssignments = $dateRows->map(static fn(object $row): array => self::normalizeAssignmentRow($row))->all();
            }

            return ['ok' => true, 'data' => [
                'page' => $page,
                'per_page' => $perPage,
                'items' => $historyItems,
                'has_next' => count($historyItems) === $perPage,
                'date_assignment_facts' => $dateAssignments,
                'date_facts_truncated' => count($dateAssignments) === 200,
                'source' => 'PORTAL3_SQL_M03_ASSIGNMENT_FACTS',
            ]];
        } catch (Throwable $exception) {
            Logger::warning('Checador M03 SQL assignment history read failed', [
                'operation' => 'm03_assignment_history_read', 'workplace_id' => $workplaceId,
                'worker_id' => $workerId, 'exception' => get_class($exception),
            ]);
            return self::failure('database_unavailable', 503);
        }
    }

    /** Resolve the versioned workplace timezone using only SQL-backed resolver data. */
    public function timezoneForDate(int $workplaceId, string $date): ?string
    {
        if ($workplaceId < 1 || !preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date)) return null;
        $probe = new DateTimeImmutable($date . 'T12:00:00Z');
        $previous = null;
        for ($i = 0; $i < 4; $i++) {
            $localDate = $probe->format('Y-m-d');
            try {
                $rows = Capsule::table('op_rh_v2_localidades_zonas_horarias')
                    ->where('id_localidad', $workplaceId)
                    ->where('valido_desde', '<=', $localDate)
                    ->where(static function ($query) use ($localDate): void {
                        $query->whereNull('valido_hasta')->orWhere('valido_hasta', '>', $localDate);
                    })
                    ->orderBy('valido_desde')->get(['timezone_iana']);
            } catch (Throwable $exception) {
                Logger::warning('Checador M03 SQL timezone read failed', [
                    'operation' => 'm03_timezone_read', 'workplace_id' => $workplaceId,
                    'exception' => get_class($exception),
                ]);
                return null;
            }
            if ($rows->count() !== 1) return null;
            $timezone = (string) $rows[0]->timezone_iana;
            if (!WorkplaceTimezoneVersionService::isValidIanaTimezone($timezone)) return null;
            if ($timezone === $previous) return $timezone;
            $previous = $timezone;
            $probe = new DateTimeImmutable($date . ' 12:00:00', new DateTimeZone($timezone));
            $probe = $probe->setTimezone(new DateTimeZone('UTC'));
        }
        return null;
    }

    private static function failure(string $kind, int $status): array
    {
        return ['ok' => false, 'error' => ['kind' => $kind, 'status' => $status]];
    }

    private static function normalizeAssignmentRow(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'id_personal' => (int) $row->id_personal,
            'id_estacion' => (int) $row->id_estacion,
            'id_plantilla' => $row->id_plantilla === null ? null : (int) $row->id_plantilla,
            'tipo_asignacion' => strtoupper((string) $row->tipo_asignacion),
            'dia_semana' => strtoupper((string) $row->dia_semana),
            'valido_desde' => (string) $row->valido_desde,
            'valido_hasta' => $row->valido_hasta === null ? null : (string) $row->valido_hasta,
            'es_activo' => (int) $row->es_activo,
            'registrado_at_utc' => isset($row->registrado_at_utc) ? (string) $row->registrado_at_utc : null,
            'motivo' => $row->motivo === null ? null : (string) $row->motivo,
        ];
    }

    private static function isCivilDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
