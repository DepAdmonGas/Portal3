<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Usuario;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Capsule\Manager as Capsule;
use Throwable;

/** Read-only M07 projection over persisted administrative adjustments. */
final class ChecadorAdjustmentSqlReadService
{
    public const MAX_RANGE_DAYS = 31;
    public const DEFAULT_PAGE_SIZE = 25;
    private const MAX_SCAN_ROWS = 5000;

    /** @var callable(Usuario,int):bool */
    private $authorizeRead;
    /** @var callable(int,string,string):array */
    private $resolveMembership;
    /** @var callable():DateTimeImmutable */
    private $clock;

    /** @param callable(Usuario,int):bool|null $authorizeRead @param callable(int,string,string):array|null $resolveMembership */
    public function __construct(?callable $authorizeRead = null, ?callable $resolveMembership = null, ?callable $clock = null)
    {
        $this->authorizeRead = $authorizeRead ?? static function (Usuario $actor, int $workplaceId): bool {
            $sessionUser = \App\Core\Auth::user();
            if (!$sessionUser || (int) ($sessionUser->id ?? 0) !== (int) ($actor->id ?? 0)) return false;
            $scope = ChecadorAccessService::authorize($workplaceId);
            if (($scope['allowed'] ?? false) !== true) return false;
            foreach ($scope['workplaces'] ?? [] as $workplace) {
                if ((int) ($workplace['id'] ?? 0) === $workplaceId) return true;
            }
            return false;
        };
        $this->resolveMembership = $resolveMembership ?? static fn(int $workerId, string $effectiveAtUtc, string $recordedAsOfUtc): array =>
            (new WorkerWorkplaceAssignmentResolver())->resolveAssignments($workerId, $effectiveAtUtc, $recordedAsOfUtc);
        $this->clock = $clock ?? static fn(): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /** @return array{ok:bool,data?:array,error?:string} */
    public function listForWorkplace(Usuario $actor, int $workplaceId, array $filters, int $page = 1, int $perPage = self::DEFAULT_PAGE_SIZE): array
    {
        if ($workplaceId < 1 || $page < 1 || $perPage < 1 || $perPage > 100) return self::failure('invalid_request');
        if (!(($this->authorizeRead)($actor, $workplaceId))) return self::failure('permission_denied');
        $normalized = self::normalizeFilters($filters);
        if ($normalized === null) return self::failure('invalid_filter');

        try {
            $connection = Capsule::connection('default');
            return $connection->transaction(function () use ($connection, $actor, $workplaceId, $normalized, $page, $perPage): array {
                $recordedAsOf = ($this->clock)()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
                $query = $this->baseQuery($connection, $workplaceId, $normalized['start_date'], $normalized['end_date']);
                if ($normalized['worker_id'] !== null) $query->where('j.id_personal', $normalized['worker_id']);
                if ($normalized['type'] !== null) $query->where('a.tipo_ajuste', $normalized['type']);
                if ($normalized['status'] !== null) $query->where('a.estado_ajuste', $normalized['status']);
                $candidateRows = $query->orderBy('a.uuid_ajuste_logico')->orderBy('a.revision_numero')
                    ->limit(self::MAX_SCAN_ROWS + 1)->get(self::projectionColumns());
                if ($candidateRows->count() > self::MAX_SCAN_ROWS) return self::failure('result_limit_exceeded');

                $logicalIds = $candidateRows->pluck('uuid_ajuste_logico')->map(static fn($id): string => (string) $id)->unique()->values()->all();
                if ($logicalIds === []) {
                    return ['ok' => true, 'data' => [
                        'items' => [], 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total_records' => 0, 'total_pages' => 0],
                        'recorded_as_of_utc' => $recordedAsOf, 'excluded_unverified_count' => 0,
                    ]];
                }

                // Reload each selected logical adjustment without date predicates so its full persisted history is available.
                $historyRows = $this->baseQuery($connection, $workplaceId, null, null)
                    ->whereIn('a.uuid_ajuste_logico', $logicalIds)
                    ->orderBy('a.uuid_ajuste_logico')->orderBy('a.revision_numero')
                    ->limit(self::MAX_SCAN_ROWS + 1)->get(self::projectionColumns());
                if ($historyRows->count() > self::MAX_SCAN_ROWS) return self::failure('result_limit_exceeded');

                $candidateGroups = [];
                foreach ($candidateRows as $row) $candidateGroups[(string) $row->uuid_ajuste_logico][] = $row;
                $historyGroups = [];
                foreach ($historyRows as $row) $historyGroups[(string) $row->uuid_ajuste_logico][] = $row;
                $excluded = 0;
                $items = [];
                $evaluationLinks = $this->readEvaluationLinks($connection, $historyRows->pluck('adjustment_id')->map(static fn($id): int => (int) $id)->all());
                foreach ($candidateGroups as $logicalId => $candidateGroup) {
                    $fullGroup = $historyGroups[$logicalId] ?? [];
                    $projected = $this->projectLogicalGroup($fullGroup, $workplaceId, $recordedAsOf, $evaluationLinks);
                    if ($projected === null) {
                        $excluded++;
                        continue;
                    }
                    $current = $projected['current'];
                    if ($current['work_date'] < $normalized['start_date'] || $current['work_date'] > $normalized['end_date']) continue;
                    if ($normalized['worker_id'] !== null && $current['worker_id'] !== $normalized['worker_id']) continue;
                    if ($normalized['type'] !== null && $current['type'] !== $normalized['type']) continue;
                    if ($normalized['status'] !== null && $current['status'] !== $normalized['status']) continue;
                    $items[] = $projected;
                }
                usort($items, static fn(array $left, array $right): int =>
                    [$right['current']['work_date'], $right['current']['uuid_ajuste_logico']] <=> [$left['current']['work_date'], $left['current']['uuid_ajuste_logico']]);
                $total = count($items);
                $pages = $total === 0 ? 0 : (int) ceil($total / $perPage);
                if ($pages > 0 && $page > $pages) return self::failure('page_out_of_range');
                return ['ok' => true, 'data' => [
                    'items' => array_slice($items, ($page - 1) * $perPage, $perPage),
                    'pagination' => ['page' => $page, 'per_page' => $perPage, 'total_records' => $total, 'total_pages' => $pages],
                    'recorded_as_of_utc' => $recordedAsOf, 'excluded_unverified_count' => $excluded,
                ]];
            }, 1);
        } catch (Throwable $exception) {
            $sqlState = (string) $exception->getCode();
            return self::failure(in_array($sqlState, ['42S02', '42S22'], true) ? 'schema_unavailable' : 'sql_read_failed');
        }
    }

    /** Read already-persisted administrative evaluations for one authorized worker and bounded period. */
    public function evaluationsForWorkerPeriod(Usuario $actor, int $workplaceId, int $workerId, string $startDate, string $endDate): array
    {
        if ($workplaceId < 1 || $workerId < 1 || !self::isCivilDate($startDate) || !self::isCivilDate($endDate)
            || $endDate < $startDate
            || (int) (new DateTimeImmutable($startDate, new DateTimeZone('UTC'))
                ->diff(new DateTimeImmutable($endDate, new DateTimeZone('UTC')))->format('%a')) + 1 > self::MAX_RANGE_DAYS) {
            return self::failure('invalid_filter');
        }
        if (!(($this->authorizeRead)($actor, $workplaceId))) return self::failure('permission_denied');

        try {
            $connection = Capsule::connection('default');
            return $connection->transaction(function () use ($connection, $actor, $workplaceId, $workerId, $startDate, $endDate): array {
                $recordedAsOf = ($this->clock)()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
                $rows = $connection->table('op_rh_v2_evaluaciones_administrativas as e')
                    ->join('op_rh_v2_jornadas_programadas as j', 'j.id', '=', 'e.id_jornada_programada')
                    ->join('op_rh_v2_calculos_jornada as c', 'c.id', '=', 'e.id_calculo_jornada')
                    ->join('op_rh_personal as p', 'p.id', '=', 'e.id_personal')
                    ->join('op_rh_localidades as l', 'l.id', '=', 'e.id_estacion')
                    ->where('e.id_estacion', $workplaceId)
                    ->where('e.id_personal', $workerId)
                    ->whereBetween('e.fecha_jornada_local', [$startDate, $endDate])
                    ->orderBy('e.fecha_jornada_local')->orderBy('e.revision_numero')->orderBy('e.id')
                    ->limit(self::MAX_SCAN_ROWS + 1)
                    ->get([
                        'e.id', 'e.id_jornada_programada', 'e.id_calculo_jornada', 'e.id_personal', 'e.id_estacion',
                        'e.fecha_jornada_local', 'e.revision_numero', 'e.version_algoritmo_admin', 'e.version_politica',
                        'e.estado_evaluacion', 'e.estado_revision_humana', 'e.creado_at_utc',
                        'j.id as joined_journey_id', 'j.id_personal as journey_worker_id', 'j.id_estacion as journey_workplace_id',
                        'j.fecha_jornada_local as journey_work_date', 'j.timezone_local',
                        'c.id as joined_calculation_id', 'c.id_jornada_programada as calculation_journey_id',
                        'c.id_personal as calculation_worker_id', 'c.id_estacion as calculation_workplace_id',
                        'c.fecha_jornada_local as calculation_work_date', 'p.id as joined_worker_id', 'l.id as joined_workplace_id',
                    ]);
                if ($rows->count() > self::MAX_SCAN_ROWS) return self::failure('result_limit_exceeded');

                $items = [];
                $excluded = 0;
                foreach ($rows as $row) {
                    if ((int) $row->id_personal !== $workerId || (int) $row->id_estacion !== $workplaceId
                        || (int) $row->joined_worker_id !== $workerId || (int) $row->joined_workplace_id !== $workplaceId
                        || (int) $row->joined_journey_id !== (int) $row->id_jornada_programada
                        || (int) $row->journey_worker_id !== $workerId || (int) $row->journey_workplace_id !== $workplaceId
                        || (string) $row->journey_work_date !== (string) $row->fecha_jornada_local
                        || (int) $row->joined_calculation_id !== (int) $row->id_calculo_jornada
                        || (int) $row->calculation_journey_id !== (int) $row->id_jornada_programada
                        || (int) $row->calculation_worker_id !== $workerId
                        || (int) $row->calculation_workplace_id !== $workplaceId
                        || (string) $row->calculation_work_date !== (string) $row->fecha_jornada_local) {
                        $excluded++;
                        continue;
                    }
                    $effectiveAt = self::localDayStartUtc((string) $row->fecha_jornada_local, (string) $row->timezone_local);
                    if ($effectiveAt === null) {
                        $excluded++;
                        continue;
                    }
                    $membership = ($this->resolveMembership)($workerId, $effectiveAt, $recordedAsOf);
                    if (!self::membershipMatches($membership, $workplaceId)) {
                        $excluded++;
                        continue;
                    }
                    $items[] = [
                        'evaluation_id' => (int) $row->id,
                        'journey_id' => (int) $row->id_jornada_programada,
                        'calculation_id' => (int) $row->id_calculo_jornada,
                        'worker_id' => $workerId,
                        'workplace_id' => $workplaceId,
                        'work_date' => (string) $row->fecha_jornada_local,
                        'revision' => (int) $row->revision_numero,
                        'algorithm_version' => (string) $row->version_algoritmo_admin,
                        'policy_version' => (string) $row->version_politica,
                        'status' => (string) $row->estado_evaluacion,
                        'human_review_status' => (string) $row->estado_revision_humana,
                        'created_at_utc' => (string) $row->creado_at_utc,
                    ];
                }
                return ['ok' => true, 'data' => [
                    'items' => $items,
                    'recorded_as_of_utc' => $recordedAsOf,
                    'excluded_unverified_count' => $excluded,
                ]];
            }, 1);
        } catch (Throwable $exception) {
            $sqlState = (string) $exception->getCode();
            return self::failure(in_array($sqlState, ['42S02', '42S22'], true) ? 'schema_unavailable' : 'sql_read_failed');
        }
    }

    /** Validate an aggregate's immutable predecessor chain and all canonical scope links. */
    public function projectLogicalGroup(array $rows, int $workplaceId, string $recordedAsOfUtc, array $evaluationLinks = []): ?array
    {
        if ($rows === []) return null;
        $revisions = [];
        $workerId = null;
        $journeyId = null;
        $workDate = null;
        $timezone = null;
        foreach ($rows as $row) {
            if ((int) $row->journey_workplace_id !== $workplaceId
                || (int) $row->calculation_workplace_id !== $workplaceId
                || (int) $row->calculation_journey_id !== (int) $row->journey_id
                || (int) $row->calculation_worker_id !== (int) $row->worker_id
                || (string) $row->calculation_work_date !== (string) $row->work_date
                || trim((string) $row->timezone_local) === '') return null;
            if ($workerId !== null && ($workerId !== (int) $row->worker_id
                || $journeyId !== (int) $row->journey_id || $workDate !== (string) $row->work_date
                || $timezone !== (string) $row->timezone_local)) return null;
            $workerId = (int) $row->worker_id;
            $journeyId = (int) $row->journey_id;
            $workDate = (string) $row->work_date;
            $timezone = (string) $row->timezone_local;
            $effective = self::localDayStartUtc($workDate, $timezone);
            if ($effective === null) return null;
            $membership = ($this->resolveMembership)($workerId, $effective, $recordedAsOfUtc);
            if (!self::membershipMatches($membership, $workplaceId)) return null;
            $id = (int) $row->adjustment_id;
            $verifiedEvaluations = [];
            $invalidEvaluationLink = false;
            foreach ($evaluationLinks[$id] ?? [] as $evaluation) {
                if ((int) ($evaluation['journey_id'] ?? 0) !== (int) $row->journey_id
                    || (int) ($evaluation['calculation_id'] ?? 0) !== (int) $row->calculation_id
                    || (int) ($evaluation['worker_id'] ?? 0) !== $workerId
                    || (int) ($evaluation['workplace_id'] ?? 0) !== $workplaceId
                    || (string) ($evaluation['work_date'] ?? '') !== (string) $row->work_date) {
                    $invalidEvaluationLink = true;
                    continue;
                }
                $verifiedEvaluations[] = $evaluation;
            }
            $revisions[] = [
                'id' => $id, 'uuid' => (string) $row->uuid_ajuste,
                'logical_uuid' => (string) $row->uuid_ajuste_logico,
                'revision' => (int) $row->revision_numero,
                'status' => strtoupper((string) $row->adjustment_status),
                'previous_id' => $row->previous_adjustment_id === null ? null : (int) $row->previous_adjustment_id,
                'type' => (string) $row->type, 'entry_utc' => $row->entry_utc === null ? null : (string) $row->entry_utc,
                'exit_utc' => $row->exit_utc === null ? null : (string) $row->exit_utc,
                'reason' => (string) $row->reason, 'created_by_user_id' => (int) $row->created_by_user_id,
                'approved_by_user_id' => (int) $row->approved_by_user_id, 'created_at_utc' => (string) $row->created_at_utc,
                'journey_id' => (int) $row->journey_id, 'calculation_id' => (int) $row->calculation_id,
                'evaluation_links' => $verifiedEvaluations,
                'evaluation_link_integrity' => $invalidEvaluationLink ? 'unverified' : 'verified',
            ];
        }
        $selected = self::selectCurrentRevision($revisions);
        if (($selected['status'] ?? '') !== 'ready') return null;
        $current = $selected['revision'];
        $currentRow = null;
        foreach ($rows as $row) if ((int) $row->adjustment_id === (int) $current['id']) { $currentRow = $row; break; }
        if (!$currentRow || trim((string) ($currentRow->worker_name ?? '')) === '') return null;
        $current['worker_id'] = (int) $currentRow->worker_id;
        $current['worker_name'] = trim((string) $currentRow->worker_name);
        $current['employee_number'] = $currentRow->employee_number === null ? null : (string) $currentRow->employee_number;
        $current['workplace_id'] = $workplaceId;
        $current['workplace_name'] = (string) ($currentRow->workplace_name ?? '');
        $current['work_date'] = (string) $currentRow->work_date;
        return ['current' => $current, 'revisions' => $revisions];
    }

    /** @return array{status:string,revision:?array,reason:?string} */
    public static function selectCurrentRevision(array $revisions): array
    {
        if ($revisions === []) return ['status' => 'invalid', 'revision' => null, 'reason' => 'empty_history'];
        usort($revisions, static fn(array $a, array $b): int => (int) ($a['revision'] ?? 0) <=> (int) ($b['revision'] ?? 0));
        $byId = [];
        $seenUuids = [];
        $logicalUuid = trim((string) ($revisions[0]['logical_uuid'] ?? ''));
        foreach ($revisions as $index => $revision) {
            $id = (int) ($revision['id'] ?? 0);
            $number = (int) ($revision['revision'] ?? 0);
            $status = strtoupper((string) ($revision['status'] ?? ''));
            $uuid = trim((string) ($revision['uuid'] ?? ''));
            if ($id < 1 || $uuid === '' || trim((string) ($revision['logical_uuid'] ?? '')) !== $logicalUuid
                || isset($seenUuids[$uuid]) || $number !== $index + 1 || isset($byId[$id])
                || !in_array($status, ['ACTIVE', 'SUPERSEDED', 'CANCELLED'], true)) {
                return ['status' => 'invalid', 'revision' => null, 'reason' => 'invalid_revision_sequence'];
            }
            $byId[$id] = $revision;
            $seenUuids[$uuid] = true;
            if ($index === 0 && ($revision['previous_id'] ?? null) !== null) return ['status' => 'invalid', 'revision' => null, 'reason' => 'invalid_root_revision'];
            if ($index > 0 && (int) ($revision['previous_id'] ?? 0) !== (int) $revisions[$index - 1]['id']) {
                return ['status' => 'invalid', 'revision' => null, 'reason' => 'broken_predecessor_chain'];
            }
            if ($index < count($revisions) - 1 && !in_array($status, ['SUPERSEDED', 'CANCELLED'], true)) {
                return ['status' => 'invalid', 'revision' => null, 'reason' => 'non_current_revision_state'];
            }
        }
        $current = $revisions[array_key_last($revisions)];
        if (!in_array(strtoupper((string) $current['status']), ['ACTIVE', 'CANCELLED'], true)) {
            return ['status' => 'invalid', 'revision' => null, 'reason' => 'current_revision_state_invalid'];
        }
        return ['status' => 'ready', 'revision' => $current, 'reason' => null];
    }

    private function baseQuery(mixed $connection, int $workplaceId, ?string $startDate, ?string $endDate): mixed
    {
        $query = $connection->table('op_rh_v2_ajustes_administrativos as a')
            ->join('op_rh_v2_jornadas_programadas as j', 'j.id', '=', 'a.id_jornada_programada')
            ->join('op_rh_v2_calculos_jornada as c', 'c.id', '=', 'a.id_calculo_jornada')
            ->leftJoin('op_rh_personal as p', 'p.id', '=', 'j.id_personal')
            ->leftJoin('op_rh_localidades as l', 'l.id', '=', 'j.id_estacion')
            ->where('j.id_estacion', $workplaceId)
            ->whereColumn('c.id_jornada_programada', 'j.id')
            ->whereColumn('c.id_personal', 'j.id_personal')
            ->whereColumn('c.id_estacion', 'j.id_estacion')
            ->whereColumn('c.fecha_jornada_local', 'j.fecha_jornada_local')
            ->select(self::projectionColumns());
        if ($startDate !== null) $query->where('j.fecha_jornada_local', '>=', $startDate);
        if ($endDate !== null) $query->where('j.fecha_jornada_local', '<=', $endDate);
        return $query;
    }

    private static function projectionColumns(): array
    {
        return [
            'a.id as adjustment_id', 'a.uuid_ajuste', 'a.uuid_ajuste_logico', 'a.revision_numero',
            'a.estado_ajuste as adjustment_status', 'a.id_ajuste_anterior as previous_adjustment_id',
            'a.id_jornada_programada as journey_id', 'a.id_calculo_jornada as calculation_id',
            'a.tipo_ajuste as type', 'a.hora_interpretada_entrada_utc as entry_utc',
            'a.hora_interpretada_salida_utc as exit_utc', 'a.motivo_ajuste as reason',
            'a.creado_por_usuario_id as created_by_user_id', 'a.aprobado_por_usuario_id as approved_by_user_id',
            'a.creado_at_utc as created_at_utc', 'j.id_personal as worker_id', 'j.id_estacion as journey_workplace_id',
            'j.fecha_jornada_local as work_date', 'j.timezone_local', 'c.id_jornada_programada as calculation_journey_id',
            'c.id_personal as calculation_worker_id', 'c.id_estacion as calculation_workplace_id',
            'c.fecha_jornada_local as calculation_work_date', 'p.nombre_completo as worker_name',
            'p.no_colaborador as employee_number', 'l.localidad as workplace_name',
        ];
    }

    private function readEvaluationLinks(mixed $connection, array $adjustmentIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $adjustmentIds), static fn(int $id): bool => $id > 0)));
        if ($ids === []) return [];
        $rows = $connection->table('op_rh_v2_evaluaciones_ajustes as ea')
            ->join('op_rh_v2_evaluaciones_administrativas as e', 'e.id', '=', 'ea.id_evaluacion_administrativa')
            ->whereIn('ea.id_ajuste_administrativo', $ids)
            ->orderBy('ea.id_ajuste_administrativo')->orderBy('e.revision_numero')
            ->get(['ea.id_ajuste_administrativo', 'e.id as evaluation_id', 'e.id_jornada_programada',
                'e.id_calculo_jornada', 'e.id_personal', 'e.id_estacion', 'e.fecha_jornada_local',
                'e.revision_numero', 'e.version_algoritmo_admin', 'e.version_politica',
                'e.estado_evaluacion', 'e.estado_revision_humana', 'e.creado_at_utc']);
        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->id_ajuste_administrativo][] = [
                'evaluation_id' => (int) $row->evaluation_id,
                'journey_id' => (int) $row->id_jornada_programada,
                'calculation_id' => (int) $row->id_calculo_jornada,
                'worker_id' => (int) $row->id_personal,
                'workplace_id' => (int) $row->id_estacion,
                'work_date' => (string) $row->fecha_jornada_local,
                'revision' => (int) $row->revision_numero,
                'algorithm_version' => (string) $row->version_algoritmo_admin,
                'policy_version' => (string) $row->version_politica,
                'status' => (string) $row->estado_evaluacion,
                'human_review_status' => (string) $row->estado_revision_humana,
                'created_at_utc' => (string) $row->creado_at_utc,
            ];
        }
        return $result;
    }

    private static function membershipMatches(array $membership, int $workplaceId): bool
    {
        $assignments = $membership['assignments'] ?? [];
        return ($membership['status'] ?? '') === 'RESOLVED_SINGLE' && count($assignments) === 1
            && (int) ($assignments[0]['workplace_id'] ?? 0) === $workplaceId;
    }

    private static function localDayStartUtc(string $date, string $timezone): ?string
    {
        if (!self::isCivilDate($date) || !in_array($timezone, DateTimeZone::listIdentifiers(), true)) return null;
        try {
            return (new DateTimeImmutable($date . ' 00:00:00', new DateTimeZone($timezone)))
                ->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
        } catch (Throwable) { return null; }
    }

    private static function normalizeFilters(array $filters): ?array
    {
        $worker = $filters['worker_id'] ?? null;
        if ($worker !== null && (!is_int($worker) || $worker < 1)) return null;
        $type = $filters['type'] ?? null;
        if ($type !== null && !in_array($type, ['OVERRIDE_STATUS', 'INTERPRETED_ENTRY_TIME', 'INTERPRETED_EXIT_TIME', 'JUSTIFY_LATENESS'], true)) return null;
        $status = $filters['status'] ?? null;
        if ($status !== null && !in_array($status, ['ACTIVE', 'SUPERSEDED', 'CANCELLED'], true)) return null;
        $start = $filters['start_date'] ?? null;
        $end = $filters['end_date'] ?? null;
        if (!is_string($start) || !is_string($end) || !self::isCivilDate($start) || !self::isCivilDate($end) || $end < $start) return null;
        $startInstant = new DateTimeImmutable($start, new DateTimeZone('UTC'));
        $endInstant = new DateTimeImmutable($end, new DateTimeZone('UTC'));
        if ((int) $startInstant->diff($endInstant)->format('%a') + 1 > self::MAX_RANGE_DAYS) return null;
        return ['worker_id' => $worker, 'type' => $type, 'status' => $status, 'start_date' => $start, 'end_date' => $end];
    }

    private static function isCivilDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private static function failure(string $error): array { return ['ok' => false, 'error' => $error]; }
}
