<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Capsule\Manager as Capsule;
use Throwable;

/** C01 read-only projection of persisted V2 journeys; never derives a result. */
final class ChecadorJourneySqlReadService
{
    public const MAX_RANGE_DAYS = 31;
    public const MAX_SCAN_ROWS = 5000;
    public const DEFAULT_PAGE_SIZE = 25;

    /** @var callable(int):bool */
    private $authorizeRead;
    /** @var callable(int,int,string,string):array */
    private $resolveMembership;
    /** @var callable():DateTimeImmutable */
    private $clock;

    /** @param callable(int):bool|null $authorizeRead @param callable(int,int,string,string):array|null $resolveMembership @param callable():DateTimeImmutable|null $clock */
    public function __construct(?callable $authorizeRead = null, ?callable $resolveMembership = null, ?callable $clock = null)
    {
        $this->authorizeRead = $authorizeRead ?? static function (int $workplaceId): bool {
            $scope = ChecadorAccessService::authorize($workplaceId);
            if (($scope['allowed'] ?? false) !== true) return false;
            foreach ($scope['workplaces'] ?? [] as $workplace) {
                if ((int) ($workplace['id'] ?? 0) === $workplaceId) return true;
            }
            return false;
        };
        $this->resolveMembership = $resolveMembership ?? static fn(int $workerId, int $workplaceId, string $effectiveAtUtc, string $recordedAsOfUtc): array =>
            (new WorkerWorkplaceAssignmentResolver())->resolveAssignments($workerId, $effectiveAtUtc, $recordedAsOfUtc);
        $this->clock = $clock ?? static fn(): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /** @return array{ok:bool,data?:array,error?:string} */
    public function read(int $workplaceId, string $startDate, string $endDate, ?int $workerId = null, int $page = 1, int $perPage = self::DEFAULT_PAGE_SIZE): array
    {
        if ($workplaceId < 1 || $page < 1 || $page > 100000 || $perPage < 1 || $perPage > 100
            || ($workerId !== null && $workerId < 1) || !self::isCivilDate($startDate) || !self::isCivilDate($endDate)) {
            return self::failure('invalid_request');
        }
        $start = self::date($startDate);
        $end = self::date($endDate);
        if ($start === null || $end === null || $end < $start
            || (int) $start->diff($end)->format('%a') + 1 > self::MAX_RANGE_DAYS) {
            return self::failure('invalid_date_range');
        }
        if (!(($this->authorizeRead)($workplaceId))) return self::failure('permission_denied');

        try {
            $connection = Capsule::connection('default');
            return $connection->transaction(function () use ($connection, $workplaceId, $startDate, $endDate, $workerId, $page, $perPage): array {
            $recordedAsOf = ($this->clock)()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
            $query = $connection->table('op_rh_v2_jornadas_programadas as j')
                ->leftJoin('op_rh_personal as p', 'p.id', '=', 'j.id_personal')
                ->leftJoin('op_rh_localidades as l', 'l.id', '=', 'j.id_estacion')
                ->where('j.id_estacion', $workplaceId)
                ->whereBetween('j.fecha_jornada_local', [$startDate, $endDate]);
            if ($workerId !== null) $query->where('j.id_personal', $workerId);
            $candidateRows = $query->orderBy('j.fecha_jornada_local')->orderBy('j.id_personal')->orderBy('j.id')
                ->limit(self::MAX_SCAN_ROWS + 1)
                ->get([
                    'j.id', 'j.id_personal', 'j.id_estacion', 'j.fecha_jornada_local',
                    'j.id_asignacion_origen', 'j.id_plantilla_origen', 'j.tipo_jornada',
                    'j.scheduled_start_utc', 'j.scheduled_end_utc', 'j.timezone_local',
                    'j.payload_segmentos_json', 'j.creado_at_utc', 'j.actualizado_at_utc',
                    'p.id as joined_worker_id', 'p.nombre_completo', 'p.no_colaborador', 'l.id as joined_workplace_id',
                ]);
            if ($candidateRows->count() > self::MAX_SCAN_ROWS) return self::failure('result_limit_exceeded');

            $verifiedRows = [];
            $excludedUnverified = 0;
            $assignmentCache = [];
            foreach ($candidateRows as $row) {
                $journeyId = (int) $row->id;
                $worker = (int) $row->id_personal;
                $workplace = (int) $row->id_estacion;
                $date = (string) $row->fecha_jornada_local;
                $timezone = trim((string) $row->timezone_local);
                if ($journeyId < 1 || $worker < 1 || $workplace !== $workplaceId
                    || (int) ($row->joined_worker_id ?? 0) !== $worker
                    || (int) ($row->joined_workplace_id ?? 0) !== $workplaceId) {
                    $excludedUnverified++;
                    continue;
                }
                $localDayStartUtc = self::localDayStartUtc($date, $timezone);
                if ($localDayStartUtc === null) {
                    $excludedUnverified++;
                    continue;
                }
                $membershipKey = $worker . '|' . $workplaceId . '|' . $localDayStartUtc;
                if (!array_key_exists($membershipKey, $assignmentCache)) {
                    $effectiveRfc3339 = str_replace(' ', 'T', $localDayStartUtc) . 'Z';
                    $recordedRfc3339 = str_replace(' ', 'T', $recordedAsOf) . 'Z';
                    $assignmentCache[$membershipKey] = ($this->resolveMembership)($worker, $workplaceId, $effectiveRfc3339, $recordedRfc3339);
                }
                $membership = $assignmentCache[$membershipKey];
                $matchesLocality = self::membershipConfirmsWorkplace($membership, $workplaceId);
                if (!$matchesLocality) {
                    $excludedUnverified++;
                    continue;
                }
                if ($row->id_asignacion_origen !== null && !$this->sourceAssignmentMatches(
                    (int) $row->id_asignacion_origen,
                    $worker,
                    $workplaceId,
                    $date
                )) {
                    $excludedUnverified++;
                    continue;
                }
                $verifiedRows[] = [
                    'journey_id' => $journeyId,
                    'worker_id' => $worker,
                    'worker_name' => trim((string) ($row->nombre_completo ?? '')) ?: 'Nombre no disponible',
                    'workplace_id' => $workplaceId,
                    'work_date' => $date,
                    'row' => $row,
                ];
            }

            $total = count($verifiedRows);
            $workerOptionsById = [];
            foreach ($verifiedRows as $item) {
                $workerOptionsById[$item['worker_id']] = [
                    'worker_id' => $item['worker_id'], 'full_name' => $item['worker_name'],
                    'employee_number' => $item['row']->no_colaborador === null ? null : trim((string) $item['row']->no_colaborador),
                ];
            }
            $workerOptions = array_values($workerOptionsById);
            usort($workerOptions, static fn(array $left, array $right): int =>
                strcasecmp($left['full_name'], $right['full_name']) ?: ($left['worker_id'] <=> $right['worker_id'])
            );

            $pages = max(1, (int) ceil($total / $perPage));
            if ($total > 0 && $page > $pages) return self::failure('page_out_of_range');
            $offset = ($page - 1) * $perPage;
            $pageRows = array_slice($verifiedRows, $offset, $perPage);
            $journeyIds = array_column($pageRows, 'journey_id');
            $details = $journeyIds === [] ? [] : $this->readDetails($journeyIds, $pageRows, $workplaceId);
            if ($details === null) return self::failure('persisted_data_inconsistent');

            $journeys = [];
            foreach ($pageRows as $item) {
                $journey = $this->projectJourney($item, $details[$item['journey_id']] ?? null);
                if ($journey === null) return self::failure('persisted_data_inconsistent');
                $journeys[$item['journey_id']] = $journey;
            }
            return ['ok' => true, 'data' => [
                'journeys' => $journeys,
                'worker_options' => $workerOptions,
                'pagination' => ['page' => $page, 'per_page' => $perPage, 'total_records' => $total, 'total_pages' => $pages],
                'excluded_unverified_count' => $excludedUnverified,
                'recorded_as_of_utc' => $recordedAsOf,
            ]];
            }, 1);
        } catch (Throwable $exception) {
            $sqlState = (string) $exception->getCode();
            if (in_array($sqlState, ['42S02', '42S22'], true)) return self::failure('schema_unavailable');
            return self::failure('sql_unavailable');
        }
    }

    /** @return array{status:string,revision:?array,reason:?string} */
    public static function selectCurrentPairing(array $revisions): array
    {
        $byId = [];
        $current = [];
        $maxRevision = 0;
        $numbers = [];
        foreach ($revisions as $revision) {
            if (!is_array($revision)) return ['status' => 'invalid', 'revision' => null, 'reason' => 'invalid_revision_row'];
            $id = (int) ($revision['id'] ?? 0);
            $number = (int) ($revision['revision_numero'] ?? 0);
            $journeyId = (int) ($revision['id_jornada_programada'] ?? 0);
            if ($id < 1 || $number < 1 || $journeyId < 1 || isset($byId[$id]) || isset($numbers[$number])) {
                return ['status' => 'invalid', 'revision' => null, 'reason' => 'invalid_revision_identity'];
            }
            $byId[$id] = $revision;
            $numbers[$number] = true;
            $maxRevision = max($maxRevision, $number);
            if (($revision['supercedido_por_id'] ?? null) === null) $current[] = $revision;
        }
        if ($revisions === []) return ['status' => 'missing', 'revision' => null, 'reason' => null];
        $successorTargets = [];
        foreach ($revisions as $revision) {
            $successorId = $revision['supercedido_por_id'] ?? null;
            if ($successorId === null) continue;
            if (isset($successorTargets[(int) $successorId])) {
                return ['status' => 'invalid', 'revision' => null, 'reason' => 'forked_supersession_chain'];
            }
            $successorTargets[(int) $successorId] = true;
            $successor = $byId[(int) $successorId] ?? null;
            if (!is_array($successor)
                || (int) ($successor['id_jornada_programada'] ?? 0) !== (int) $revision['id_jornada_programada']
                || (int) ($successor['revision_numero'] ?? 0) <= (int) $revision['revision_numero']) {
                return ['status' => 'invalid', 'revision' => null, 'reason' => 'broken_supersession_chain'];
            }
        }
        if (count($current) !== 1 || (int) $current[0]['revision_numero'] !== $maxRevision) {
            return ['status' => 'invalid', 'revision' => null, 'reason' => 'current_revision_ambiguous'];
        }
        return ['status' => 'ready', 'revision' => $current[0], 'reason' => null];
    }

    /** @return array{status:string,revision:?array,reason:?string} */
    public static function selectCurrentCalculation(array $calculations, int $currentPairingId, int $journeyId): array
    {
        $matching = [];
        $revisionNumbers = [];
        foreach ($calculations as $calculation) {
            if (!is_array($calculation)
                || (int) ($calculation['id_jornada_programada'] ?? 0) !== $journeyId
                || (int) ($calculation['id_pareo_jornada'] ?? 0) < 1
                || (int) ($calculation['revision_numero'] ?? 0) < 1) {
                return ['status' => 'invalid', 'revision' => null, 'reason' => 'invalid_calculation_identity'];
            }
            $revisionNumber = (int) $calculation['revision_numero'];
            if (isset($revisionNumbers[$revisionNumber])) {
                return ['status' => 'invalid', 'revision' => null, 'reason' => 'duplicate_calculation_revision'];
            }
            $revisionNumbers[$revisionNumber] = true;
            if ((int) $calculation['id_pareo_jornada'] === $currentPairingId) $matching[] = $calculation;
        }
        if ($currentPairingId < 1 || $matching === []) return ['status' => 'missing', 'revision' => null, 'reason' => null];
        usort($matching, static fn(array $left, array $right): int => (int) $left['revision_numero'] <=> (int) $right['revision_numero']);
        for ($index = 1; $index < count($matching); $index++) {
            if ((int) $matching[$index - 1]['revision_numero'] === (int) $matching[$index]['revision_numero']) {
                return ['status' => 'invalid', 'revision' => null, 'reason' => 'duplicate_calculation_revision'];
            }
        }
        return ['status' => 'ready', 'revision' => $matching[array_key_last($matching)], 'reason' => null];
    }

    /** A historical assignment is usable only when exactly one workplace is resolved. */
    public static function membershipConfirmsWorkplace(mixed $membership, int $workplaceId): bool
    {
        if (!is_array($membership) || $workplaceId < 1 || ($membership['status'] ?? '') !== 'RESOLVED_SINGLE') return false;
        $assignments = $membership['assignments'] ?? null;
        return is_array($assignments) && count($assignments) === 1
            && is_array($assignments[0] ?? null)
            && (int) ($assignments[0]['workplace_id'] ?? 0) === $workplaceId;
    }

    /** @return array<string,mixed>|null */
    private function readDetails(array $journeyIds, array $pageRows, int $workplaceId): ?array
    {
        $connection = Capsule::connection('default');
        $pairings = $connection->table('op_rh_v2_pareos_jornada')->whereIn('id_jornada_programada', $journeyIds)
            ->orderBy('id_jornada_programada')->orderBy('revision_numero')->get([
                'id', 'id_jornada_programada', 'id_personal', 'id_estacion', 'fecha_jornada_local',
                'revision_numero', 'version_algoritmo', 'estado_completitud_l2', 'estado_pareo',
                'fingerprint_ejecucion', 'supercedido_por_id', 'creado_at_utc',
            ]);
        $pairingsByJourney = [];
        $pairingIds = [];
        $journeyByPairingId = [];
        foreach ($pairings as $row) {
            $journeyId = (int) $row->id_jornada_programada;
            $expected = self::pageIdentity($pageRows, $journeyId);
            if ($expected === null || (int) $row->id_personal !== $expected['worker_id']
                || (int) $row->id_estacion !== $workplaceId || (string) $row->fecha_jornada_local !== $expected['work_date']) return null;
            $mapped = (array) $row;
            $pairingsByJourney[$journeyId][] = $mapped;
            $pairingIds[(int) $row->id] = true;
            $journeyByPairingId[(int) $row->id] = $journeyId;
        }

        $calculations = $connection->table('op_rh_v2_calculos_jornada')->whereIn('id_jornada_programada', $journeyIds)
            ->orderBy('id_jornada_programada')->orderBy('revision_numero')->get([
                'id', 'id_jornada_programada', 'id_pareo_jornada', 'id_personal', 'id_estacion',
                'fecha_jornada_local', 'revision_numero', 'version_algoritmo_calculo', 'estado_calculo',
                'segundos_programados_totales', 'segundos_trabajados_totales',
                'segundos_candidatos_fuera_horario', 'fingerprint_ejecucion', 'creado_at_utc',
            ]);
        $calculationsByJourney = [];
        $calculationIds = [];
        $calculationPairingIds = [];
        foreach ($calculations as $row) {
            $journeyId = (int) $row->id_jornada_programada;
            $expected = self::pageIdentity($pageRows, $journeyId);
            if ($expected === null || (int) $row->id_personal !== $expected['worker_id']
                || (int) $row->id_estacion !== $workplaceId || (string) $row->fecha_jornada_local !== $expected['work_date']
                || !isset($pairingIds[(int) $row->id_pareo_jornada])) return null;
            $mapped = self::normalizeObject($row);
            $calculationsByJourney[$journeyId][] = $mapped;
            $calculationIds[(int) $row->id] = true;
            $calculationPairingIds[(int) $row->id] = (int) $row->id_pareo_jornada;
        }

        $pairingSegmentRows = $pairingIds === [] ? collect() : $connection->table('op_rh_v2_pareos_segmentos')
            ->whereIn('id_pareo_jornada', array_keys($pairingIds))->orderBy('id_pareo_jornada')->orderBy('ordinal_segmento')
            ->get(['id', 'id_pareo_jornada', 'ordinal_segmento', 'id_marcacion_entrada', 'id_marcacion_salida', 'estado_segmento', 'creado_at_utc']);
        $pairingSegmentsById = [];
        foreach ($pairingSegmentRows as $row) $pairingSegmentsById[(int) $row->id] = self::normalizeObject($row);

        $associationRows = $pairingIds === [] ? collect() : $connection->table('op_rh_v2_pareos_marcaciones_asociadas')
            ->whereIn('id_pareo_jornada', array_keys($pairingIds))->orderBy('id_pareo_jornada')->orderBy('id')->get([
                'id', 'id_pareo_jornada', 'id_pareo_segmento', 'id_marcacion_cruda', 'rol_marcacion', 'creado_at_utc',
            ]);
        $rawIds = [];
        foreach ($associationRows as $row) $rawIds[(int) $row->id_marcacion_cruda] = true;
        foreach ($pairingSegmentRows as $row) {
            foreach (['id_marcacion_entrada', 'id_marcacion_salida'] as $column) {
                if ($row->{$column} !== null) $rawIds[(int) $row->{$column}] = true;
            }
        }
        $rawRows = $rawIds === [] ? collect() : $connection->table('op_rh_v2_marcaciones_crudas')->whereIn('id', array_keys($rawIds))
            ->get(['id', 'event_uuid', 'id_dispositivo', 'id_estacion_resuelta', 'id_personal_resuelto', 'ocurrido_at_utc', 'recibido_at_utc', 'metodo_captura', 'processing_status']);
        $rawById = [];
        foreach ($rawRows as $row) $rawById[(int) $row->id] = self::normalizeObject($row);

        $pairingSegments = [];
        foreach ($pairingSegmentRows as $row) {
            $segment = self::normalizeObject($row);
            $journeyId = $journeyByPairingId[(int) $row->id_pareo_jornada] ?? null;
            $expected = $journeyId === null ? null : self::pageIdentity($pageRows, $journeyId);
            if ($expected === null) return null;
            foreach (['id_marcacion_entrada' => 'entry_event_uuid', 'id_marcacion_salida' => 'exit_event_uuid'] as $column => $uuidColumn) {
                $eventId = $row->{$column} === null ? null : (int) $row->{$column};
                $raw = $eventId === null ? null : ($rawById[$eventId] ?? null);
                if ($eventId !== null && ($raw === null || (int) ($raw['id_personal_resuelto'] ?? 0) !== $expected['worker_id']
                    || (int) ($raw['id_estacion_resuelta'] ?? 0) !== $workplaceId)) return null;
                $segment[$uuidColumn] = $raw['event_uuid'] ?? null;
            }
            $pairingSegments[(int) $row->id_pareo_jornada][] = $segment;
        }
        $associationsByPairing = [];
        foreach ($associationRows as $row) {
            $segmentId = $row->id_pareo_segmento === null ? null : (int) $row->id_pareo_segmento;
            if ($segmentId !== null && (!isset($pairingSegmentsById[$segmentId])
                || (int) $pairingSegmentsById[$segmentId]['id_pareo_jornada'] !== (int) $row->id_pareo_jornada)) return null;
            if (!self::associationMatchesSegment((string) $row->rol_marcacion, (int) $row->id_marcacion_cruda,
                $segmentId === null ? null : $pairingSegmentsById[$segmentId])) return null;
            $raw = $rawById[(int) $row->id_marcacion_cruda] ?? null;
            $journeyId = $journeyByPairingId[(int) $row->id_pareo_jornada] ?? null;
            $expected = $journeyId === null ? null : self::pageIdentity($pageRows, $journeyId);
            if ($raw === null || $expected === null || (int) ($raw['id_personal_resuelto'] ?? 0) !== $expected['worker_id']
                || (int) ($raw['id_estacion_resuelta'] ?? 0) !== $workplaceId) return null;
            $associationsByPairing[(int) $row->id_pareo_jornada][] = [
                'id_pareo_segmento' => $segmentId,
                'rol_marcacion' => (string) $row->rol_marcacion,
                'event' => [
                    'event_uuid' => (string) ($raw['event_uuid'] ?? ''),
                    'ocurrido_at_utc' => $raw['ocurrido_at_utc'] ?? null,
                    'recibido_at_utc' => $raw['recibido_at_utc'] ?? null,
                    'id_dispositivo' => $raw['id_dispositivo'] === null ? null : (int) $raw['id_dispositivo'],
                    'metodo_captura' => $raw['metodo_captura'] ?? null,
                    'processing_status' => $raw['processing_status'] ?? null,
                ],
            ];
        }

        $calculationSegments = $calculationIds === [] ? collect() : $connection->table('op_rh_v2_calculos_segmentos')
            ->whereIn('id_calculo_jornada', array_keys($calculationIds))->orderBy('id_calculo_jornada')->orderBy('ordinal_segmento')
            ->get(['id', 'id_calculo_jornada', 'id_pareo_segmento', 'ordinal_segmento', 'tipo_segmento', 'estado_segmento',
                'segundos_programados', 'segundos_trabajados', 'delta_llegada_segundos', 'delta_salida_segundos', 'segundos_candidatos_fuera_horario', 'creado_at_utc']);
        $calculationSegmentsById = [];
        foreach ($calculationSegments as $row) {
            if ($row->id_pareo_segmento !== null
                && (!isset($pairingSegmentsById[(int) $row->id_pareo_segmento])
                    || (int) $pairingSegmentsById[(int) $row->id_pareo_segmento]['id_pareo_jornada'] !== ($calculationPairingIds[(int) $row->id_calculo_jornada] ?? 0))) return null;
            $calculationSegmentsById[(int) $row->id_calculo_jornada][] = self::normalizeObject($row);
        }

        $result = [];
        foreach ($journeyIds as $journeyId) {
            $p1 = $pairingsByJourney[$journeyId] ?? [];
            $currentPairing = self::selectCurrentPairing($p1);
            if ($currentPairing['status'] === 'invalid') return null;
            $selectedP1 = $currentPairing['revision'];
            $p2 = $calculationsByJourney[$journeyId] ?? [];
            $currentCalculation = self::selectCurrentCalculation($p2, (int) ($selectedP1['id'] ?? 0), (int) $journeyId);
            if ($currentCalculation['status'] === 'invalid') return null;
            $selectedP2 = $currentCalculation['revision'];
            if ($selectedP1 !== null) {
                $selectedP1['segments'] = [];
                foreach ($pairingSegments[$selectedP1['id']] ?? [] as $segment) {
                    $selectedP1['segments'][] = [
                        'id' => (int) $segment['id'],
                        'ordinal' => (int) $segment['ordinal_segmento'],
                        'status' => (string) $segment['estado_segmento'],
                        'created_at_utc' => (string) $segment['creado_at_utc'],
                    ];
                }
                $selectedP1['associated_events'] = $associationsByPairing[(int) $selectedP1['id']] ?? [];
            }
            if ($selectedP2 !== null) $selectedP2['segments'] = $calculationSegmentsById[(int) $selectedP2['id']] ?? [];
            foreach ($p1 as &$revision) {
                $revision['segments'] = $pairingSegments[(int) $revision['id']] ?? [];
            }
            unset($revision);
            foreach ($p2 as &$revision) {
                $revision['segments'] = $calculationSegmentsById[(int) $revision['id']] ?? [];
            }
            unset($revision);
            $result[$journeyId] = [
                'pairing_status' => $currentPairing['status'],
                'pairing' => $selectedP1,
                'pairing_history' => $p1,
                'calculation' => $selectedP2,
                'calculation_history' => $p2,
            ];
        }
        return $result;
    }

    private function projectJourney(array $item, ?array $detail): ?array
    {
        if ($detail === null) return null;
        $row = $item['row'];
        $scheduleType = strtoupper((string) $row->tipo_jornada);
        $segmentsJson = $row->payload_segmentos_json === null ? null : (string) $row->payload_segmentos_json;
        $decodedSegments = $segmentsJson === null ? null : json_decode($segmentsJson, true);
        if ($segmentsJson !== null && json_last_error() !== JSON_ERROR_NONE) return null;
        if ($segmentsJson !== null && $decodedSegments !== null && !is_array($decodedSegments)) return null;
        $pairing = $detail['pairing'];
        $calculation = $detail['calculation'];
        if (is_array($calculation)) {
            foreach (['segundos_programados_totales', 'segundos_trabajados_totales', 'segundos_candidatos_fuera_horario'] as $secondsColumn) {
                if (($calculation[$secondsColumn] ?? null) !== null && is_numeric($calculation[$secondsColumn])) {
                    $calculation[$secondsColumn] = (int) $calculation[$secondsColumn];
                }
            }
        }
        $segmentOrdinalById = [];
        foreach ($pairing['segments'] ?? [] as $segment) {
            if (!is_array($segment)) continue;
            $segmentId = (int) ($segment['id'] ?? 0);
            $ordinal = (int) ($segment['ordinal'] ?? 0);
            if ($segmentId > 0 && $ordinal > 0) $segmentOrdinalById[$segmentId] = $ordinal;
        }
        $associatedEvents = [];
        foreach ($pairing['associated_events'] ?? [] as $association) {
            if (!is_array($association) || !is_array($association['event'] ?? null)) continue;
            $segmentId = (int) ($association['id_pareo_segmento'] ?? 0);
            $associatedEvent = [
                'pairing_role' => (string) ($association['rol_marcacion'] ?? ''),
                ...$association['event'],
            ];
            if ($segmentId > 0 && isset($segmentOrdinalById[$segmentId])) {
                $associatedEvent['segment_ordinal'] = $segmentOrdinalById[$segmentId];
            }
            $associatedEvents[] = $associatedEvent;
        }
        $persistedStatus = $calculation['estado_calculo'] ?? $pairing['estado_pareo'] ?? null;
        return [
            'id' => $item['journey_id'],
            'worker_id' => $item['worker_id'],
            'worker_name' => $item['worker_name'],
            'journey_status' => is_string($persistedStatus) ? $persistedStatus : 'RESULT_NOT_AVAILABLE',
            'pairing_current_status' => (string) ($detail['pairing_status'] ?? 'missing'),
            'evaluated' => $calculation !== null,
            'evaluated_raw_event_count' => null,
            'context' => [
                'id_personal' => $item['worker_id'], 'id_estacion' => $item['workplace_id'],
                'fecha_local' => $item['work_date'], 'timezone' => (string) $row->timezone_local,
            ],
            'schedule' => [
                'estado_jornada' => $scheduleType,
                'scheduled_start_utc' => $row->scheduled_start_utc === null ? null : (string) $row->scheduled_start_utc,
                'scheduled_end_utc' => $row->scheduled_end_utc === null ? null : (string) $row->scheduled_end_utc,
                'payload_segmentos_json' => $segmentsJson,
            ],
            'pairing' => $pairing,
            'calculation' => $calculation,
            'pairing_history' => $detail['pairing_history'],
            'calculation_history' => $detail['calculation_history'],
            'provenance' => [
                'journey_created_at_utc' => (string) $row->creado_at_utc,
                'journey_updated_at_utc' => (string) $row->actualizado_at_utc,
                'p1_revision' => $pairing['revision_numero'] ?? null,
                'p1_algorithm_version' => $pairing['version_algoritmo'] ?? null,
                'p1_created_at_utc' => $pairing['creado_at_utc'] ?? null,
                'l2_status_persisted' => $pairing['estado_completitud_l2'] ?? null,
                'p1_fingerprint' => $pairing['fingerprint_ejecucion'] ?? null,
                'p2_revision' => $calculation['revision_numero'] ?? null,
                'p2_algorithm_version' => $calculation['version_algoritmo_calculo'] ?? null,
                'p2_created_at_utc' => $calculation['creado_at_utc'] ?? null,
                'p2_fingerprint' => $calculation['fingerprint_ejecucion'] ?? null,
            ],
            'source_evidence' => [
                'level2_status' => $pairing['estado_completitud_l2'] ?? null,
                'level2_verification' => 'not_independently_verifiable',
            ],
            // Associations are a persisted P1 subset, not a complete evaluated-event snapshot.
            'persisted_pairing_events' => $associatedEvents,
            'evaluated_raw_events' => $associatedEvents,
        ];
    }

    private function sourceAssignmentMatches(int $assignmentId, int $workerId, int $workplaceId, string $workDate): bool
    {
        $row = Capsule::connection('default')->table('op_rh_v2_turnos_asignaciones')
            ->where('id', $assignmentId)->first(['id_personal', 'id_estacion', 'valido_desde', 'valido_hasta']);
        return $row !== null && (int) $row->id_personal === $workerId && (int) $row->id_estacion === $workplaceId
            && (string) $row->valido_desde <= $workDate
            && ($row->valido_hasta === null || $workDate < (string) $row->valido_hasta);
    }

    /** ENTRY/EXIT may populate a slot only when its persisted segment references the same raw event. */
    public static function associationMatchesSegment(string $role, int $rawId, ?array $segment): bool
    {
        $role = strtoupper(trim($role));
        if (!in_array($role, ['ENTRY', 'EXIT'], true)) return true;
        $column = $role === 'ENTRY' ? 'id_marcacion_entrada' : 'id_marcacion_salida';
        return $rawId > 0 && $segment !== null && (int) ($segment[$column] ?? 0) === $rawId;
    }

    private static function localDayStartUtc(string $date, string $timezone): ?string
    {
        if (!ChecadorOperationalPeriod::isIanaTimezone($timezone) || !self::isCivilDate($date)) return null;
        try {
            $zone = new DateTimeZone($timezone);
            $wall = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $date . ' 00:00:00', new DateTimeZone('UTC'));
            if (!$wall) return null;
            $wallTimestamp = $wall->getTimestamp();
            $transitions = $zone->getTransitions($wallTimestamp - 172800, $wallTimestamp + 172800);
            $offsets = [];
            if (is_array($transitions)) foreach ($transitions as $transition) $offsets[(int) $transition['offset']] = true;
            else $offsets[$zone->getOffset($wall)] = true;
            $matches = [];
            foreach (array_keys($offsets) as $offset) {
                $candidate = (new DateTimeImmutable('@' . ($wallTimestamp - (int) $offset)))->setTimezone(new DateTimeZone('UTC'));
                if ($candidate->setTimezone($zone)->format('Y-m-d H:i:s') === $date . ' 00:00:00') $matches[$candidate->getTimestamp()] = true;
            }
            if (count($matches) !== 1) return null;
            $timestamp = (int) array_key_first($matches);
            return (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        } catch (Throwable) {
            return null;
        }
    }

    private static function pageIdentity(array $pageRows, int $journeyId): ?array
    {
        foreach ($pageRows as $item) if ((int) $item['journey_id'] === $journeyId) return $item;
        return null;
    }

    private static function normalizeObject(object $row): array
    {
        $result = [];
        foreach (get_object_vars($row) as $key => $value) $result[$key] = $value;
        return $result;
    }

    private static function isCivilDate(string $value): bool
    {
        return self::date($value) !== null;
    }

    private static function date(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        return $date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $date->format('Y-m-d') === $value ? $date : null;
    }

    private static function failure(string $error): array
    {
        return ['ok' => false, 'error' => $error];
    }
}
