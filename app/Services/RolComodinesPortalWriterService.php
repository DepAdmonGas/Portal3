<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use App\Core\Database;
use Illuminate\Database\Capsule\Manager as Capsule;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * The sole Portal3 administrative writer for canonical Workplace coverage.
 * Reads and mutations use Portal3's institutional PDO connection. Mutations
 * fail closed unless the process is development-only and attached to local
 * bd_admongas, and CHECADOR_COMODINES_PORTAL_WRITES is enabled.
 */
final class RolComodinesPortalWriterService
{
    private const FLAG = 'CHECADOR_COMODINES_PORTAL_WRITES';
    private const TZ = 'America/Mexico_City';
    private const DAYS = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO', 'DOMINGO'];

    public static function writesEnabled(?string $environment = null, ?string $flag = null): bool
    {
        $environment ??= strtolower(trim((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '')));
        $flag ??= (string) ($_ENV[self::FLAG] ?? getenv(self::FLAG) ?: '');
        return in_array($environment, ['dev', 'development', 'local'], true) && trim($flag) === '1';
    }

    /** @return array{ok:bool,http_status:int,code?:string,data?:array<string,mixed>} */
    public function listVisible(array $authorizedWorkplaceIds): array
    {
        try {
            $scope = self::positiveIds($authorizedWorkplaceIds);
            if ($scope === []) return self::failure('coverage_scope_denied', 403);
            $marks = implode(',', array_fill(0, count($scope), '?'));
            $sql = "SELECT c.* FROM op_rh_v2_worker_workplace_coverage_versions c
                    JOIN (SELECT coverage_uuid, MAX(id) AS id FROM op_rh_v2_worker_workplace_coverage_versions GROUP BY coverage_uuid) latest ON latest.id=c.id
                    WHERE c.id_home_workplace IN ($marks) AND c.id_coverage_workplace IN ($marks)
                    ORDER BY c.id DESC LIMIT 1001";
            $items = $this->all($sql, [...$scope, ...$scope]);
            if (count($items) > 1000) return self::failure('coverage_result_limit', 502);
            foreach ($items as &$item) $item = self::normalizeRow($item);
            unset($item);
            return ['ok' => true, 'http_status' => 200, 'data' => [
                'items' => $items,
                'snapshot_id' => (int) ($items[0]['id'] ?? 0),
                'complete' => true,
            ]];
        } catch (Throwable) {
            return self::failure('coverage_read_failed', 503);
        }
    }

    /** Read the allowed active global template catalogue from the shared canonical database. */
    public function templateCatalog(): array
    {
        try {
            $items = $this->all("SELECT id,clave,version,nombre,tipo_turno,scope,id_estacion,es_activo
                FROM op_rh_v2_turnos_plantillas
                WHERE scope='GLOBAL' AND es_activo=1
                ORDER BY nombre,id,version LIMIT 501");
            if (count($items) > 500) return self::failure('coverage_template_limit', 502);
            foreach ($items as &$item) {
                foreach (['id','version','es_activo'] as $key) $item[$key] = (int) $item[$key];
                $item['id_estacion'] = $item['id_estacion'] === null ? null : (int) $item['id_estacion'];
            }
            unset($item);
            return ['ok' => true, 'http_status' => 200, 'data' => ['items' => $items, 'complete' => true]];
        } catch (Throwable) {
            return self::failure('coverage_template_unavailable', 503);
        }
    }

    /** @return array{ok:bool,http_status:int,code?:string,data?:array<string,mixed>} */
    public function detail(string $coverageUuid, array $authorizedWorkplaceIds): array
    {
        try {
            if (!self::isUuid($coverageUuid)) return self::failure('invalid_coverage_uuid', 400);
            $row = $this->one(
                'SELECT c.* FROM op_rh_v2_worker_workplace_coverage_versions c JOIN
                 (SELECT MAX(id) id FROM op_rh_v2_worker_workplace_coverage_versions WHERE coverage_uuid=?) latest ON latest.id=c.id',
                [$coverageUuid]
            );
            if ($row === null) return self::failure('not_found', 404);
            $scope = self::positiveIds($authorizedWorkplaceIds);
            if (!in_array((int) $row['id_home_workplace'], $scope, true)
                || !in_array((int) $row['id_coverage_workplace'], $scope, true)) {
                return self::failure('coverage_scope_denied', 403);
            }
            $history = $this->all(
                'SELECT * FROM op_rh_v2_worker_workplace_coverage_versions WHERE coverage_uuid=? ORDER BY revision_no',
                [$coverageUuid]
            );
            foreach ($history as &$revision) {
                $revision = self::normalizeRow($revision);
                $revision['audit_events'] = $this->all(
                    "SELECT id,operacion,id_usuario_actor,id_estacion,id_personal,valido_desde,valido_hasta,
                            id_plantilla,version_plantilla,detalle_json,recorded_at_utc
                     FROM op_rh_v2_turnos_audit_log WHERE entity_type='WORKPLACE_COVERAGE' AND entity_id=? ORDER BY id",
                    [(int) $revision['id']]
                );
                foreach ($revision['audit_events'] as &$audit) {
                    $audit['detalle_json'] = self::decodeJson($audit['detalle_json'] ?? null);
                }
                unset($audit);
            }
            unset($revision);
            $latest = self::normalizeRow($row);
            $latest['revision_history'] = $history;
            return ['ok' => true, 'http_status' => 200, 'data' => $latest];
        } catch (Throwable) {
            return self::failure('coverage_read_failed', 503);
        }
    }

    /** New request is always REQUESTED; no active shift assignment is created here. */
    public function createRequest(int $actorId, array $authorizedWorkplaceIds, array $payload, string $idempotencyKey): array
    {
        if (!self::writesEnabled()) return self::failure('feature_disabled', 503);
        if (!Database::isLocalDevelopmentDatabaseConnection()) return self::failure('writer_database_not_local', 503);
        if (!self::validIdempotencyKey($idempotencyKey)) return self::failure('idempotency_key_required', 422);
        try {
            $request = self::normalizeRequest($payload);
            $scope = self::positiveIds($authorizedWorkplaceIds);
            self::assertScope($scope, $request['id_home_workplace'], $request['id_coverage_workplace']);
            $keyHash = hash('sha256', $idempotencyKey);
            $fingerprint = self::fingerprint('REQUEST', ['actor_user_id' => $actorId, 'request' => $request]);
            $result = Capsule::connection()->transaction(function () use ($request, $actorId, $keyHash, $fingerprint): array {
                $this->assertLocalDevelopmentDatabase();
                self::assertActor($actorId, ChecadorV2PermissionService::COMODINES_REQUEST);
                $this->lockWorkerAndWorkplaces($request['id_personal'], [$request['id_home_workplace'], $request['id_coverage_workplace']]);
                $this->assertCoverageDestination($request['id_coverage_workplace']);
                $candidateSource = ChecadorRolComodinesService::resolveCoverageCandidateSource($request['id_personal'], true);
                if (empty($candidateSource['ok']) || empty($candidateSource['eligible'])) {
                    throw new RuntimeException('worker_candidate_not_eligible');
                }
                $prior = $this->one('SELECT * FROM op_rh_v2_worker_workplace_coverage_versions WHERE idempotency_key_hash=? FOR UPDATE', [$keyHash]);
                if ($prior !== null) return self::replay($prior, $fingerprint, $actorId);
                $membershipId = $this->assertHomeMembership($request);
                $request['id_home_membership_version'] = $membershipId;
                $this->assertTimezoneRange($request['id_coverage_workplace'], $request['fecha_desde_local'], $request['fecha_hasta_local_exclusiva']);
                $this->assertTemplates($request['id_coverage_workplace'], $request['proposed_weekly_schedule']);
                $this->assertNoCoverageOverlap($request['id_personal'], $request['fecha_desde_local'], $request['fecha_hasta_local_exclusiva']);
                $uuid = self::uuidV4();
                $json = json_encode($request['proposed_weekly_schedule'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                $this->execute(
                    "INSERT INTO op_rh_v2_worker_workplace_coverage_versions
                     (coverage_uuid,revision_no,supersedes_id,id_personal,id_home_workplace,id_home_membership_version,id_coverage_workplace,
                      id_home_physical_station,id_coverage_physical_station,fecha_desde_local,fecha_hasta_local_exclusiva,timezone_iana,
                      proposed_schedule_json,status,requested_by_usuario_id,requested_at_utc,approved_by_usuario_id,approved_at_utc,
                      transition_actor_usuario_id,transition_action,motivo_solicitud,motivo_transicion,idempotency_key_hash,payload_fingerprint,recorded_at_utc)
                     VALUES (?,1,NULL,?,?,?,?,NULL,NULL,?,?,?,?,'REQUESTED',?,UTC_TIMESTAMP(6),NULL,NULL,?,'REQUEST',?,NULL,?,?,UTC_TIMESTAMP(6))",
                    [$uuid,$request['id_personal'],$request['id_home_workplace'],$membershipId,$request['id_coverage_workplace'],
                     $request['fecha_desde_local'],$request['fecha_hasta_local_exclusiva'],self::TZ,$json,$actorId,$actorId,
                     $request['motivo_solicitud'],$keyHash,$fingerprint]
                );
                $id = (int) Capsule::connection()->getPdo()->lastInsertId();
                $coverage = $this->one('SELECT * FROM op_rh_v2_worker_workplace_coverage_versions WHERE id=?', [$id]);
                if ($coverage === null) throw new RuntimeException('coverage_readback_failed');
                $this->writeCoverageAudit($id, $actorId, 'REQUEST', $coverage, ['coverage_uuid'=>$uuid,'revision_no'=>1]);
                return ['created'=>true,'coverage'=>self::normalizeRow($coverage)];
            }, 1);
            return ['ok'=>true,'http_status'=>empty($result['created'])?200:201,'data'=>$result['coverage']];
        } catch (Throwable $e) {
            if (isset($keyHash, $fingerprint) && self::isUniqueViolation($e)) {
                try {
                    $prior = $this->one('SELECT * FROM op_rh_v2_worker_workplace_coverage_versions WHERE idempotency_key_hash=?', [$keyHash]);
                    if ($prior !== null) {
                        $replayed = self::replay($prior, $fingerprint, $actorId);
                        return ['ok' => true, 'http_status' => 200, 'data' => $replayed['coverage']];
                    }
                } catch (Throwable $replayError) {
                    return self::failure(self::publicCode($replayError), self::httpFor($replayError));
                }
            }
            return self::failure(self::publicCode($e), self::httpFor($e));
        }
    }

    /** Explicit AUTHORIZE or CANCEL transition. REJECT is intentionally not exposed by this slice. */
    public function transition(string $coverageUuid, string $action, int $actorId, array $authorizedWorkplaceIds, string $reason, string $idempotencyKey): array
    {
        if (!self::writesEnabled()) return self::failure('feature_disabled', 503);
        if (!Database::isLocalDevelopmentDatabaseConnection()) return self::failure('writer_database_not_local', 503);
        $action = strtoupper(trim($action));
        $permission = $action === 'AUTHORIZE' ? ChecadorV2PermissionService::COMODINES_APPROVE
            : ($action === 'CANCEL' ? ChecadorV2PermissionService::COMODINES_CANCEL : '');
        if (!self::isUuid($coverageUuid) || $permission === '') return self::failure('invalid_coverage_transition', 400);
        if (trim($reason) === '' || mb_strlen(trim($reason)) > 500) return self::failure('coverage_reason_required', 422);
        if (!self::validIdempotencyKey($idempotencyKey)) return self::failure('idempotency_key_required', 422);
        try {
            $scope = self::positiveIds($authorizedWorkplaceIds);
            $keyHash = hash('sha256', $idempotencyKey);
            $reason = trim($reason);
            $result = Capsule::connection()->transaction(function () use ($coverageUuid,$action,$actorId,$scope,$keyHash,$reason,$permission): array {
                $this->assertLocalDevelopmentDatabase();
                self::assertActor($actorId, $permission);
                $current = $this->one('SELECT * FROM op_rh_v2_worker_workplace_coverage_versions WHERE coverage_uuid=? ORDER BY revision_no DESC LIMIT 1 FOR UPDATE', [$coverageUuid]);
                if ($current === null) throw new RuntimeException('coverage_not_found');
                self::assertScope($scope,(int)$current['id_home_workplace'],(int)$current['id_coverage_workplace']);
                $this->lockWorkerAndWorkplaces((int)$current['id_personal'],[(int)$current['id_home_workplace'],(int)$current['id_coverage_workplace']]);
                $prior = $this->one('SELECT * FROM op_rh_v2_worker_workplace_coverage_versions WHERE idempotency_key_hash=? FOR UPDATE', [$keyHash]);
                if ($prior !== null) {
                    $fp = self::fingerprint($action,['actor_user_id'=>$actorId,'coverage_uuid'=>$coverageUuid,'source_revision_id'=>(int)($prior['supersedes_id']??0),'reason'=>$reason]);
                    return self::replay($prior,$fp,$actorId);
                }
                $sourceStatus = (string)$current['status'];
                if (($action==='AUTHORIZE' && $sourceStatus!=='REQUESTED') || ($action==='CANCEL' && $sourceStatus!=='AUTHORIZED')) throw new RuntimeException('coverage_state_conflict');
                if ($action === 'AUTHORIZE') {
                    $this->assertCoverageDestination((int) $current['id_coverage_workplace']);
                    $candidateSource = ChecadorRolComodinesService::resolveCoverageCandidateSource((int) $current['id_personal'], true);
                    if (empty($candidateSource['ok']) || empty($candidateSource['eligible'])) {
                        throw new RuntimeException('worker_candidate_not_eligible');
                    }
                }
                $this->assertHomeMembership([
                    'id_personal'=>(int)$current['id_personal'],'id_home_workplace'=>(int)$current['id_home_workplace'],
                    'id_home_membership_version'=>(int)$current['id_home_membership_version'],'fecha_desde_local'=>(string)$current['fecha_desde_local'],
                    'fecha_hasta_local_exclusiva'=>(string)$current['fecha_hasta_local_exclusiva']
                ]);
                $to = (string)$current['fecha_hasta_local_exclusiva'];
                $closeAt = null;
                if ($action==='AUTHORIZE') {
                    $this->assertTimezoneRange((int)$current['id_coverage_workplace'],(string)$current['fecha_desde_local'],$to);
                    if ((string)$current['fecha_desde_local'] <= $this->workplaceToday()) throw new RuntimeException('coverage_not_future');
                    $this->assertTemplates((int)$current['id_coverage_workplace'],self::decodeJson($current['proposed_schedule_json']));
                    $this->assertNoCoverageOverlap((int)$current['id_personal'],(string)$current['fecha_desde_local'],$to,(string)$current['coverage_uuid']);
                    $this->assertNoTemporalConflict((int)$current['id_personal'],(int)$current['id_coverage_workplace'],(string)$current['fecha_desde_local'],$to);
                    $this->assertNoMaterializedSchedules((int)$current['id_personal'],(string)$current['fecha_desde_local'],$to);
                } else {
                    $today = $this->workplaceToday();
                    if ((string)$current['fecha_desde_local'] <= $today) {
                        $closeAt = (new DateTimeImmutable($today,new DateTimeZone(self::TZ)))->modify('+1 day')->format('Y-m-d');
                        if ($closeAt >= $to) throw new RuntimeException('coverage_no_future_segment');
                        $this->assertNoMaterializedSchedules((int)$current['id_personal'],$closeAt,$to);
                    } else $closeAt = (string)$current['fecha_desde_local'];
                }
                $fp = self::fingerprint($action,['actor_user_id'=>$actorId,'coverage_uuid'=>$coverageUuid,'source_revision_id'=>(int)$current['id'],'reason'=>$reason]);
                $newStatus = $action==='AUTHORIZE'?'AUTHORIZED':'CANCELLED';
                $effectiveTo = $action==='CANCEL' && $closeAt !== (string)$current['fecha_desde_local'] ? $closeAt : $to;
                $this->insertRevision($current,$newStatus,$action,$actorId,$reason,$keyHash,$fp,$effectiveTo);
                $newId=(int)Capsule::connection()->getPdo()->lastInsertId();
                if ($action==='AUTHORIZE') $this->createLinkedAssignments($newId,$current,$actorId);
                else $this->closeLinkedAssignments((int)$current['id'],$newId,$current,(string)$closeAt,$actorId);
                $revision=$this->one('SELECT * FROM op_rh_v2_worker_workplace_coverage_versions WHERE id=?',[$newId]);
                if ($revision===null) throw new RuntimeException('coverage_readback_failed');
                $this->writeCoverageAudit($newId,$actorId,$action,$current,[
                    'coverage_uuid'=>$coverageUuid,'source_revision_id'=>(int)$current['id'],'revision_no'=>(int)$current['revision_no']+1,
                    'motivo'=>$reason,'effective_to_local_exclusive'=>$effectiveTo,'cancel_effective_from_local'=>$action==='CANCEL'?$closeAt:null
                ]);
                return ['created'=>true,'coverage'=>self::normalizeRow($revision)];
            },1);
            return ['ok'=>true,'http_status'=>empty($result['created'])?200:201,'data'=>$result['coverage']];
        } catch (Throwable $e) {
            if (isset($keyHash) && self::isUniqueViolation($e)) {
                try {
                    $prior = $this->one('SELECT * FROM op_rh_v2_worker_workplace_coverage_versions WHERE idempotency_key_hash=?', [$keyHash]);
                    if ($prior !== null) {
                        $fingerprint = self::fingerprint($action, [
                            'actor_user_id' => $actorId,
                            'coverage_uuid' => $coverageUuid,
                            'source_revision_id' => (int) ($prior['supersedes_id'] ?? 0),
                            'reason' => $reason,
                        ]);
                        $replayed = self::replay($prior, $fingerprint, $actorId);
                        return ['ok' => true, 'http_status' => 200, 'data' => $replayed['coverage']];
                    }
                } catch (Throwable $replayError) {
                    return self::failure(self::publicCode($replayError), self::httpFor($replayError));
                }
            }
            return self::failure(self::publicCode($e),self::httpFor($e));
        }
    }

    private function lockWorkerAndWorkplaces(int $workerId, array $workplaceIds): void
    {
        if ($this->one('SELECT id FROM op_rh_personal WHERE id=? AND estado=1 FOR UPDATE',[$workerId])===null) throw new RuntimeException('worker_not_active');
        $ids=self::positiveIds($workplaceIds); sort($ids,SORT_NUMERIC);
        foreach($ids as $id) if($this->one('SELECT id FROM op_rh_localidades WHERE id=? FOR UPDATE',[$id])===null) throw new RuntimeException('workplace_not_found');
    }

    private function assertCoverageDestination(int $workplaceId): void
    {
        if (!ChecadorRolComodinesService::isAllowedCoverageDestination($workplaceId)) {
            throw new RuntimeException('coverage_destination_not_authorized');
        }
        if (ChecadorRolComodinesService::resolveCoverageDestination($workplaceId, true) === null) {
            throw new RuntimeException('coverage_destination_mapping_unavailable');
        }
    }

    private function assertHomeMembership(array $request): int
    {
        $from=self::localBoundaryUtc((string)$request['fecha_desde_local']);
        $to=self::localBoundaryUtc((string)$request['fecha_hasta_local_exclusiva']);
        $atEnd=(new DateTimeImmutable($to,new DateTimeZone('UTC')))->modify('-1 microsecond')->format('Y-m-d\TH:i:s.u\Z');
        $resolver=new WorkerWorkplaceAssignmentResolver();
        $first=$resolver->resolveAssignments((int)$request['id_personal'],$from);
        $last=$resolver->resolveAssignments((int)$request['id_personal'],$atEnd);
        if (($first['status']??'')!=='RESOLVED_SINGLE'||($last['status']??'')!=='RESOLVED_SINGLE') throw new RuntimeException('worker_membership_unverified');
        $firstRow=$first['assignments'][0]??[]; $lastRow=$last['assignments'][0]??[];
        if ((int)($firstRow['workplace_id']??0)!==(int)$request['id_home_workplace']
            || (int)($lastRow['workplace_id']??0)!==(int)$request['id_home_workplace']
            || (int)($firstRow['id']??0)!==(int)($lastRow['id']??0)
            || (int)($firstRow['id']??0)!==(int)$request['id_home_membership_version']) throw new RuntimeException('worker_membership_unverified');
        $row=$this->one('SELECT id FROM op_rh_personal_localidad_version WHERE id=? AND worker_id=? AND workplace_id=? AND valid_from_utc<=? AND (valid_to_utc IS NULL OR valid_to_utc>=?) AND recorded_at_utc<=UTC_TIMESTAMP(6) FOR UPDATE',[
            (int)$firstRow['id'],(int)$request['id_personal'],(int)$request['id_home_workplace'],self::utcDatabaseTimestamp($from),self::utcDatabaseTimestamp($to)
        ]);
        if($row===null) throw new RuntimeException('worker_membership_unverified');
        return (int)$row['id'];
    }

    private function assertTimezoneRange(int $workplaceId,string $from,string $to): void
    {
        $rows=$this->all('SELECT timezone_iana,valido_desde,valido_hasta FROM op_rh_v2_localidades_zonas_horarias WHERE id_localidad=? AND valido_desde<? AND (valido_hasta IS NULL OR valido_hasta>?) ORDER BY valido_desde',[$workplaceId,$to,$from]);
        $cursor=$from; $seen=false;
        foreach($rows as $row){$vf=(string)$row['valido_desde'];$vt=$row['valido_hasta']===null?null:(string)$row['valido_hasta'];
            if($vf>$cursor||($seen&&$vf<$cursor)||(string)$row['timezone_iana']!==self::TZ) throw new RuntimeException('workplace_timezone_unresolved');
            $seen=true;if($vt===null||$vt>=$to){$cursor=$to;break;}if($vt<=$cursor)throw new RuntimeException('workplace_timezone_unresolved');$cursor=$vt;}
        if(!$seen||$cursor<$to) throw new RuntimeException('workplace_timezone_unresolved');
    }

    private function assertTemplates(int $workplaceId,array $schedule): void
    {
        foreach($schedule as $entry){if(($entry['tipo']??'')!=='WORK')continue;
            $id=(int)$entry['id_plantilla'];$version=(int)$entry['version_plantilla'];
            // The form exposes only global templates; do not compare a physical
            // station identifier to a canonical Workplace identifier here.
            $template=$this->one("SELECT id,version,scope,id_estacion,es_activo FROM op_rh_v2_turnos_plantillas WHERE id=? AND version=? AND es_activo=1 AND scope='GLOBAL' FOR UPDATE",[$id,$version]);
            if($template===null)throw new RuntimeException('coverage_template_unavailable');
            $segments=$this->all('SELECT ordinal,tipo_segmento,hora_inicio_local,hora_fin_local,dia_offset_inicio,dia_offset_fin FROM op_rh_v2_turnos_segmentos WHERE id_plantilla=? ORDER BY ordinal',[$id]);
            if(!self::validSegments($segments))throw new RuntimeException('coverage_template_unavailable');
        }
    }

    private function assertNoCoverageOverlap(int $workerId,string $from,string $to,?string $exceptUuid=null): void
    {
        $sql="SELECT c.id FROM op_rh_v2_worker_workplace_coverage_versions c JOIN (SELECT coverage_uuid,MAX(id) id FROM op_rh_v2_worker_workplace_coverage_versions GROUP BY coverage_uuid) l ON l.id=c.id WHERE c.id_personal=? AND c.status IN ('REQUESTED','AUTHORIZED') AND c.fecha_desde_local<? AND c.fecha_hasta_local_exclusiva>?";
        $params=[$workerId,$to,$from];if($exceptUuid!==null){$sql.=' AND c.coverage_uuid<>?';$params[]=$exceptUuid;}$sql.=' FOR UPDATE';
        if($this->one($sql,$params)!==null)throw new RuntimeException('coverage_overlap_conflict');
    }

    private function assertNoTemporalConflict(int $workerId,int $workplaceId,string $from,string $to): void
    {
        if($this->one("SELECT id FROM op_rh_v2_turnos_asignaciones WHERE id_personal=? AND id_estacion=? AND tipo_asignacion='TEMPORAL' AND es_activo=1 AND valido_desde<? AND (valido_hasta IS NULL OR valido_hasta>?) FOR UPDATE",[$workerId,$workplaceId,$to,$from])!==null)throw new RuntimeException('coverage_assignment_conflict');
    }

    private function assertNoMaterializedSchedules(int $workerId,string $from,string $to): void
    {
        if($this->one('SELECT id FROM op_rh_v2_jornadas_programadas WHERE id_personal=? AND fecha_jornada_local>=? AND fecha_jornada_local<? LIMIT 1 FOR UPDATE',[$workerId,$from,$to])!==null)throw new RuntimeException('coverage_consolidated_date_conflict');
    }

    private function createLinkedAssignments(int $coverageRevisionId,array $coverage,int $actorId): void
    {
        $schedule=self::decodeJson($coverage['proposed_schedule_json']??null);$from=(string)$coverage['fecha_desde_local'];$to=(string)$coverage['fecha_hasta_local_exclusiva'];$worker=(int)$coverage['id_personal'];$workplace=(int)$coverage['id_coverage_workplace'];
        $expectedByDay=[];
        foreach($schedule as $entry){$day=(string)$entry['dia_semana'];
            $expectedByDay[$day]=$entry['tipo']==='WORK'?(int)$entry['id_plantilla']:null;
            if($this->one("SELECT id FROM op_rh_v2_turnos_asignaciones WHERE id_personal=? AND id_estacion=? AND dia_semana=? AND tipo_asignacion='TEMPORAL' AND es_activo=1 AND valido_desde<? AND (valido_hasta IS NULL OR valido_hasta>?) FOR UPDATE",[$worker,$workplace,$day,$to,$from])!==null)throw new RuntimeException('coverage_assignment_conflict');
            $template=$entry['tipo']==='WORK'?(int)$entry['id_plantilla']:null;
            $reason='WORKPLACE_COVERAGE:'.(string)$coverage['coverage_uuid'].':'.((int)$coverage['revision_no']+1);
            $this->execute("INSERT INTO op_rh_v2_turnos_asignaciones (id_personal,id_estacion,id_plantilla,tipo_asignacion,dia_semana,valido_desde,valido_hasta,es_activo,creado_at,registrado_at_utc,creado_por_usuario_id,motivo,id_cobertura_version) VALUES (?,?,?,'TEMPORAL',?,?,?,1,UTC_TIMESTAMP(),UTC_TIMESTAMP(),?,?,?)",[$worker,$workplace,$template,$day,$from,$to,$actorId,$reason,$coverageRevisionId]);
            $assignmentId=(int)Capsule::connection()->getPdo()->lastInsertId();
            $version=$entry['tipo']==='WORK'?(int)$entry['version_plantilla']:null;
            $details=json_encode(['coverage_revision_id'=>$coverageRevisionId,'coverage_uuid'=>$coverage['coverage_uuid'],'assignment_id'=>$assignmentId,'source'=>'WORKPLACE_COVERAGE'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
            $this->execute("INSERT INTO op_rh_v2_turnos_audit_log (entity_type,entity_id,operacion,id_usuario_actor,id_estacion,id_personal,tipo_asignacion,valido_desde,valido_hasta,id_plantilla,version_plantilla,detalle_json,recorded_at_utc) VALUES ('WORKPLACE_COVERAGE',?,'COVERAGE_ASSIGN',?,?,?,'TEMPORAL',?,?,?,?,?,UTC_TIMESTAMP())",[$coverageRevisionId,$actorId,$workplace,$worker,$from,$to,$template,$version,$details]);
            if($template!==null&&$this->one('SELECT a.id FROM op_rh_v2_turnos_asignaciones a JOIN op_rh_v2_turnos_plantillas p ON p.id=a.id_plantilla WHERE a.id=? AND p.id=? AND p.version=?',[$assignmentId,$template,$version])===null)throw new RuntimeException('coverage_assignment_readback_failed');
        }
        $rows=$this->all("SELECT id_personal,id_estacion,id_plantilla,tipo_asignacion,dia_semana,valido_desde,valido_hasta,es_activo,creado_por_usuario_id,id_cobertura_version
            FROM op_rh_v2_turnos_asignaciones WHERE id_cobertura_version=? ORDER BY dia_semana FOR UPDATE",[$coverageRevisionId]);
        if(count($rows)!==7||count($expectedByDay)!==7)throw new RuntimeException('coverage_assignment_readback_failed');
        foreach($rows as $row){$day=(string)$row['dia_semana'];
            if(!array_key_exists($day,$expectedByDay)||(int)$row['id_personal']!==$worker||(int)$row['id_estacion']!==$workplace
                ||(string)$row['tipo_asignacion']!=='TEMPORAL'||(string)$row['valido_desde']!==$from
                ||(string)$row['valido_hasta']!==$to||(int)$row['es_activo']!==1
                ||(int)($row['creado_por_usuario_id']??0)!==$actorId||(int)$row['id_cobertura_version']!==$coverageRevisionId
                ||($expectedByDay[$day]===null?$row['id_plantilla']!==null:(int)$row['id_plantilla']!==$expectedByDay[$day]))
                throw new RuntimeException('coverage_assignment_readback_failed');
            unset($expectedByDay[$day]);
        }
        if($expectedByDay!==[])throw new RuntimeException('coverage_assignment_readback_failed');
        $audits=(int)($this->one("SELECT COUNT(*) n FROM op_rh_v2_turnos_audit_log WHERE entity_type='WORKPLACE_COVERAGE' AND entity_id=? AND operacion='COVERAGE_ASSIGN'",[$coverageRevisionId])['n']??0);
        if($audits!==7)throw new RuntimeException('coverage_assignment_readback_failed');
    }

    private function closeLinkedAssignments(int $authorizedRevisionId,int $cancellationRevisionId,array $coverage,string $closeAt,int $actorId): void
    {
        $ids=array_map('intval',array_column($this->all('SELECT id FROM op_rh_v2_turnos_asignaciones WHERE id_cobertura_version=? AND es_activo=1 FOR UPDATE',[$authorizedRevisionId]),'id'));
        if(count($ids)!==7)throw new RuntimeException('coverage_assignment_readback_failed');
        $updated=$this->execute("UPDATE op_rh_v2_turnos_asignaciones SET es_activo=0,valido_hasta=?,actualizado_por_usuario_id=?,motivo=CONCAT(COALESCE(motivo,''),' | COVERAGE_CANCELLED') WHERE id_cobertura_version=? AND es_activo=1 AND tipo_asignacion='TEMPORAL'",[$closeAt,$actorId,$authorizedRevisionId]);
        if($updated!==7)throw new RuntimeException('coverage_assignment_close_failed');
        foreach($ids as $assignmentId){$row=$this->one('SELECT a.id_plantilla,a.valido_desde,p.version FROM op_rh_v2_turnos_asignaciones a LEFT JOIN op_rh_v2_turnos_plantillas p ON p.id=a.id_plantilla WHERE a.id=?',[$assignmentId]);
            $details=json_encode(['coverage_revision_id'=>$cancellationRevisionId,'coverage_uuid'=>$coverage['coverage_uuid'],'assignment_id'=>$assignmentId,'source'=>'WORKPLACE_COVERAGE'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
            $this->execute("INSERT INTO op_rh_v2_turnos_audit_log (entity_type,entity_id,operacion,id_usuario_actor,id_estacion,id_personal,tipo_asignacion,valido_desde,valido_hasta,id_plantilla,version_plantilla,detalle_json,recorded_at_utc) VALUES ('WORKPLACE_COVERAGE',?,'COVERAGE_CANCEL',?,?,?,'TEMPORAL',?,?,?,?,?,UTC_TIMESTAMP())",[$cancellationRevisionId,$actorId,(int)$coverage['id_coverage_workplace'],(int)$coverage['id_personal'],$row['valido_desde'],$closeAt,$row['id_plantilla'],$row['version'],$details]);}
        $readback=(int)($this->one('SELECT COUNT(*) n FROM op_rh_v2_turnos_asignaciones WHERE id_cobertura_version=? AND es_activo=0 AND valido_hasta=? AND actualizado_por_usuario_id=?',[$authorizedRevisionId,$closeAt,$actorId])['n']??0);
        $audits=(int)($this->one("SELECT COUNT(*) n FROM op_rh_v2_turnos_audit_log WHERE entity_type='WORKPLACE_COVERAGE' AND entity_id=? AND operacion='COVERAGE_CANCEL'",[$cancellationRevisionId])['n']??0);
        if($readback!==7||$audits!==7)throw new RuntimeException('coverage_assignment_close_failed');
    }

    private function insertRevision(array $current,string $status,string $action,int $actorId,string $reason,string $keyHash,string $fingerprint,string $effectiveTo): void
    {
        $approved=$status==='AUTHORIZED'?$actorId:($current['approved_by_usuario_id']===null?null:(int)$current['approved_by_usuario_id']);
        $approvedAt=$status==='AUTHORIZED'?(new DateTimeImmutable('now',new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u'):($current['approved_at_utc']??null);
        $this->execute('INSERT INTO op_rh_v2_worker_workplace_coverage_versions (coverage_uuid,revision_no,supersedes_id,id_personal,id_home_workplace,id_home_membership_version,id_coverage_workplace,id_home_physical_station,id_coverage_physical_station,fecha_desde_local,fecha_hasta_local_exclusiva,timezone_iana,proposed_schedule_json,status,requested_by_usuario_id,requested_at_utc,approved_by_usuario_id,approved_at_utc,transition_actor_usuario_id,transition_action,motivo_solicitud,motivo_transicion,idempotency_key_hash,payload_fingerprint,recorded_at_utc) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, ?,UTC_TIMESTAMP(6))',[
            $current['coverage_uuid'],(int)$current['revision_no']+1,(int)$current['id'],(int)$current['id_personal'],(int)$current['id_home_workplace'],(int)$current['id_home_membership_version'],(int)$current['id_coverage_workplace'],$current['id_home_physical_station'],$current['id_coverage_physical_station'],$current['fecha_desde_local'],$effectiveTo,$current['timezone_iana'],$current['proposed_schedule_json'],$status,(int)$current['requested_by_usuario_id'],$current['requested_at_utc'],$approved,$approvedAt,$actorId,$action,$current['motivo_solicitud'],$reason,$keyHash,$fingerprint
        ]);
    }

    private function writeCoverageAudit(int $coverageId,int $actorId,string $operation,array $coverage,array $details): void
    {
        $json=json_encode($details,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $this->execute("INSERT INTO op_rh_v2_turnos_audit_log (entity_type,entity_id,operacion,id_usuario_actor,id_estacion,id_personal,valido_desde,valido_hasta,detalle_json,recorded_at_utc) VALUES ('WORKPLACE_COVERAGE',?,?,?,?,?,?,?,?,UTC_TIMESTAMP())",[$coverageId,$operation,$actorId,(int)$coverage['id_coverage_workplace'],(int)$coverage['id_personal'],$coverage['fecha_desde_local'],$coverage['fecha_hasta_local_exclusiva'],$json]);
    }

    /** Fail closed if the default PDO is not the verified local development database. */
    private function assertLocalDevelopmentDatabase(): void
    {
        if (!Database::isLocalDevelopmentDatabaseConnection()) {
            throw new RuntimeException('writer_database_not_local');
        }
    }

    private static function assertActor(int $actorId,string $permission): void
    {
        $actor=Capsule::table('tb_usuarios')->where('id',$actorId)->first(['id','id_puesto']);
        if($actor===null||(int)$actor->id_puesto!==13||!ChecadorV2PermissionService::allowsComodines($actorId,13,$permission))throw new RuntimeException('function_permission_denied');
    }

    private static function assertScope(array $scope,int $home,int $target): void
    { if($scope===[]||!in_array($home,$scope,true)||!in_array($target,$scope,true))throw new RuntimeException('coverage_scope_denied'); }

    private static function normalizeRequest(array $payload): array
    {
        $worker=filter_var($payload['id_personal']??null,FILTER_VALIDATE_INT);$home=filter_var($payload['id_home_workplace']??null,FILTER_VALIDATE_INT);$membership=filter_var($payload['id_home_membership_version']??null,FILTER_VALIDATE_INT);$target=filter_var($payload['id_coverage_workplace']??null,FILTER_VALIDATE_INT);
        $from=trim((string)($payload['fecha_desde_local']??''));$to=trim((string)($payload['fecha_hasta_local_exclusiva']??''));$tz=trim((string)($payload['timezone_iana']??''));$reason=trim((string)($payload['motivo_solicitud']??''));
        if($worker===false||$worker<1||$home===false||$home<1||$membership===false||$membership<1||$target===false||$target<1||$home===$target||!self::isDate($from)||!self::isDate($to)||$to<=$from||$tz!==self::TZ||$reason===''||mb_strlen($reason)>500)throw new InvalidArgumentException('invalid_coverage_request');
        return ['id_personal'=>(int)$worker,'id_home_workplace'=>(int)$home,'id_home_membership_version'=>(int)$membership,'id_coverage_workplace'=>(int)$target,'fecha_desde_local'=>$from,'fecha_hasta_local_exclusiva'=>$to,'timezone_iana'=>$tz,'proposed_weekly_schedule'=>self::normalizeSchedule($payload['proposed_weekly_schedule']??null),'motivo_solicitud'=>$reason];
    }

    private static function normalizeSchedule(mixed $schedule): array
    {
        if(!is_array($schedule)||!array_is_list($schedule)||count($schedule)!==7)throw new InvalidArgumentException('invalid_coverage_schedule');$byDay=[];
        foreach($schedule as $item){if(!is_array($item))throw new InvalidArgumentException('invalid_coverage_schedule');$day=strtoupper(trim((string)($item['dia_semana']??'')));$type=strtoupper(trim((string)($item['tipo']??'')));if(!in_array($day,self::DAYS,true)||isset($byDay[$day])||!in_array($type,['WORK','DAY_OFF'],true))throw new InvalidArgumentException('invalid_coverage_schedule');
            if($type==='WORK'){$id=filter_var($item['id_plantilla']??null,FILTER_VALIDATE_INT);$version=filter_var($item['version_plantilla']??null,FILTER_VALIDATE_INT);if($id===false||$id<1||$version===false||$version<1)throw new InvalidArgumentException('invalid_coverage_schedule');$byDay[$day]=['dia_semana'=>$day,'tipo'=>'WORK','id_plantilla'=>$id,'version_plantilla'=>$version];}
            else{if(($item['id_plantilla']??null)!==null||($item['version_plantilla']??null)!==null)throw new InvalidArgumentException('invalid_coverage_schedule');$byDay[$day]=['dia_semana'=>$day,'tipo'=>'DAY_OFF','id_plantilla'=>null,'version_plantilla'=>null];}}
        $out=[];foreach(self::DAYS as $day){if(!isset($byDay[$day]))throw new InvalidArgumentException('invalid_coverage_schedule');$out[]=$byDay[$day];}return $out;
    }

    private static function validSegments(array $segments): bool
    {
        if($segments===[])return false;$last=null;$work=false;foreach($segments as $i=>$s){$type=strtoupper((string)($s['tipo_segmento']??''));$start=(string)($s['hora_inicio_local']??'');$end=(string)($s['hora_fin_local']??'');$os=filter_var($s['dia_offset_inicio']??null,FILTER_VALIDATE_INT);$oe=filter_var($s['dia_offset_fin']??null,FILTER_VALIDATE_INT);$ord=filter_var($s['ordinal']??null,FILTER_VALIDATE_INT);
            if(!in_array($type,['WORK','BREAK'],true)||!preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?\z/',$start)||!preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?\z/',$end)||!in_array($os,[0,1],true)||!in_array($oe,[0,1],true)||$ord!==$i+1)return false;
            $a=$os*1440+self::minute($start);$b=$oe*1440+self::minute($end);if($b<=$a||($last!==null&&$a<$last))return false;$last=$b;$work=$work||$type==='WORK';}return $work;
    }

    private static function minute(string $time): int { $p=array_map('intval',explode(':',$time));return $p[0]*60+$p[1]; }
    private function workplaceToday(): string { return (new DateTimeImmutable('now',new DateTimeZone('UTC')))->setTimezone(new DateTimeZone(self::TZ))->format('Y-m-d'); }
    private static function localBoundaryUtc(string $date): string { $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date,new DateTimeZone(self::TZ));if(!$d||$d->format('Y-m-d')!==$date)throw new InvalidArgumentException('invalid_local_date');return $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z'); }
    private static function utcDatabaseTimestamp(string $value): string
    {
        $instant = new DateTimeImmutable($value, new DateTimeZone('UTC'));
        return $instant->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
    private static function isDate(string $date): bool { if(!preg_match('/\A\d{4}-\d{2}-\d{2}\z/',$date))return false;$d=DateTimeImmutable::createFromFormat('!Y-m-d',$date,new DateTimeZone(self::TZ));return $d&&$d->format('Y-m-d')===$date; }
    private static function validIdempotencyKey(string $key): bool { return strlen($key)>=16&&strlen($key)<=200&&preg_match('/\A[A-Za-z0-9._:-]+\z/',$key)===1; }
    private static function isUuid(string $v): bool { return preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/iD',$v)===1; }
    private static function isUniqueViolation(Throwable $e): bool
    {
        do {
            if ($e instanceof \PDOException
                && ((string) $e->getCode() === '23000' || (int) ($e->errorInfo[1] ?? 0) === 1062)) return true;
            $e = $e->getPrevious();
        } while ($e !== null);
        return false;
    }
    private static function uuidV4(): string { $b=random_bytes(16);$b[6]=chr((ord($b[6])&0x0f)|0x40);$b[8]=chr((ord($b[8])&0x3f)|0x80);$h=bin2hex($b);return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20); }
    private static function fingerprint(string $op,array $payload): string { $sort=static function(mixed $v)use(&$sort):mixed{if(!is_array($v))return $v;if(array_is_list($v))return array_map($sort,$v);ksort($v,SORT_STRING);foreach($v as $k=>$x)$v[$k]=$sort($x);return $v;};return hash('sha256',json_encode(['operation'=>$op,'payload'=>$sort($payload)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)); }
    private static function positiveIds(array $ids): array { $out=[];foreach($ids as $id){$v=filter_var($id,FILTER_VALIDATE_INT);if($v!==false&&$v>0)$out[(int)$v]=(int)$v;}return array_values($out); }
    private static function decodeJson(mixed $v): array { if(is_array($v))return $v;$out=json_decode((string)$v,true);return is_array($out)?$out:[]; }
    private static function normalizeRow(array $row): array { $row['proposed_weekly_schedule']=self::decodeJson($row['proposed_schedule_json']??null);foreach(['id','revision_no','supersedes_id','id_personal','id_home_workplace','id_home_membership_version','id_coverage_workplace','requested_by_usuario_id','approved_by_usuario_id','transition_actor_usuario_id'] as $key)if(array_key_exists($key,$row)&&$row[$key]!==null)$row[$key]=(int)$row[$key];return $row; }
    private static function replay(array $row,string $fingerprint,int $actorId): array { if(!hash_equals((string)$row['payload_fingerprint'],$fingerprint)||(int)$row['transition_actor_usuario_id']!==$actorId)throw new RuntimeException('idempotency_key_conflict');return ['created'=>false,'coverage'=>self::normalizeRow($row)]; }
    private static function publicCode(Throwable $e): string { $allowed=['invalid_coverage_request','invalid_coverage_schedule','worker_not_active','workplace_not_found','worker_candidate_not_eligible','worker_membership_unverified','coverage_destination_not_authorized','coverage_destination_mapping_unavailable','coverage_template_unavailable','coverage_overlap_conflict','coverage_assignment_conflict','coverage_consolidated_date_conflict','coverage_not_future','coverage_no_future_segment','coverage_state_conflict','coverage_assignment_readback_failed','coverage_assignment_close_failed','coverage_readback_failed','coverage_scope_denied','function_permission_denied','idempotency_key_conflict','idempotency_key_required','coverage_not_found','workplace_timezone_unresolved','writer_database_not_local'];$msg=strtolower($e->getMessage());foreach($allowed as $code)if(str_contains($msg,$code))return $code;return 'coverage_operation_failed'; }
    private static function httpFor(Throwable $e): int { return match(self::publicCode($e)){ 'function_permission_denied','coverage_scope_denied'=>403,'coverage_not_found'=>404,'coverage_state_conflict','coverage_overlap_conflict','coverage_assignment_conflict','coverage_consolidated_date_conflict','idempotency_key_conflict'=>409,'invalid_coverage_request','invalid_coverage_schedule','worker_candidate_not_eligible','worker_membership_unverified','coverage_destination_not_authorized','coverage_template_unavailable','coverage_not_future','coverage_no_future_segment','workplace_timezone_unresolved'=>422,default=>503}; }
    private static function failure(string $code,int $status): array { return ['ok'=>false,'http_status'=>$status,'code'=>$code]; }
    private function dataConnection(): \Illuminate\Database\Connection
    {
        return Capsule::connection();
    }

    private function one(string $sql,array $params=[]): ?array { $stmt=$this->dataConnection()->getPdo()->prepare($sql);$stmt->execute($params);$row=$stmt->fetch(PDO::FETCH_ASSOC);return $row===false?null:$row; }
    private function all(string $sql,array $params=[]): array { $stmt=$this->dataConnection()->getPdo()->prepare($sql);$stmt->execute($params);return $stmt->fetchAll(PDO::FETCH_ASSOC); }
    private function execute(string $sql,array $params=[]): int { $stmt=$this->dataConnection()->getPdo()->prepare($sql);$stmt->execute($params);return $stmt->rowCount(); }
}
