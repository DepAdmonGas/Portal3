<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Logger;
use App\Models\Operativo\RhPersonal;
use App\Models\Usuario;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Capsule\Manager as Capsule;
use RuntimeException;
use Throwable;

/** Single Portal3 SQL writer for M03 assignment facts. */
final class ChecadorWeeklyScheduleSqlWriteService
{
    /** @var callable(Usuario,int):bool */
    private $authorizeWrite;
    /** @var callable(int,int,string,string):array */
    private $membership;
    /** @var callable(string):bool|null */
    private $testLock;
    /** @var callable():DateTimeImmutable */
    private $clock;

    /**
     * @param callable(Usuario,int):bool|null $authorizeWrite
     * @param callable(int,int,string,string):array|null $membership
     * @param callable(string):bool|null $testLock
     * @param callable():DateTimeImmutable|null $clock
     */
    public function __construct(?callable $authorizeWrite = null, ?callable $membership = null, ?callable $testLock = null, ?callable $clock = null)
    {
        $this->authorizeWrite = $authorizeWrite ?? static function (Usuario $user, int $workplaceId): bool {
            $authenticated = Auth::user();
            return $authenticated !== null
                && (int) ($authenticated->id ?? 0) === (int) ($user->id ?? 0)
                && (ChecadorAccessService::authorizeScheduling($workplaceId, 'editar')['allowed'] ?? false) === true;
        };
        $this->membership = $membership ?? [$this, 'resolveMembership'];
        $this->testLock = $testLock;
        $this->clock = $clock ?? static fn(): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public static function enabled(): bool
    {
        return self::operationalWritesAllowed();
    }

    /** @return array{ok:bool,assignment?:array,error?:string,status?:int,replayed?:bool} */
    public function create(Usuario $user, array $input): array
    {
        $workplaceId = (int) ($input['workplace_id'] ?? 0);
        $workerId = (int) ($input['worker_id'] ?? 0);
        $targetDate = trim((string) ($input['target_date'] ?? ''));
        $effectiveStart = trim((string) ($input['effective_start_date'] ?? ''));
        $weekStart = trim((string) ($input['week_start'] ?? ''));
        $type = strtoupper(trim((string) ($input['tipo_asignacion'] ?? '')));
        $templateValue = trim((string) ($input['template_id'] ?? ''));
        $reason = trim((string) ($input['motivo'] ?? ''));
        $key = trim((string) ($input['idempotency_key'] ?? ''));
        if ($workplaceId < 1 || $workerId < 1 || !self::isDate($targetDate)
            || !in_array($type, ['HABITUAL', 'TEMPORAL'], true)
            || ($type === 'HABITUAL' && !self::isDate($effectiveStart))
            || ($type === 'TEMPORAL' && $effectiveStart !== '')
            || ($templateValue !== 'REST' && filter_var($templateValue, FILTER_VALIDATE_INT) === false)
            || mb_strlen($reason) > 255 || !self::validKey($key)) {
            return self::failure('invalid_request', 422);
        }
        $templateId = $templateValue === 'REST' ? null : (int) $templateValue;
        if ($templateId !== null && $templateId < 1) return self::failure('invalid_template', 422);
        if (!($this->authorizeWrite)($user, $workplaceId)) return self::failure('permission_denied', 403);
        if (!self::operationalWritesAllowed()) return self::failure('m03_demo_mode_read_only', 403);

        $weekday = ChecadorWeeklyScheduleService::weekdayFor($targetDate);
        if ($weekday === null || ($type === 'HABITUAL'
            && ChecadorWeeklyScheduleService::firstWeekdayOnOrAfter($weekday, $effectiveStart) !== $targetDate)) {
            return self::failure('invalid_effective_date', 422);
        }
        $weekDates = ChecadorWeeklyScheduleService::dates($weekStart);
        if ($weekDates === [] || ($type === 'TEMPORAL' && !in_array($targetDate, array_column($weekDates, 'date'), true))
            || ($type === 'HABITUAL' && (!in_array($effectiveStart, array_column($weekDates, 'date'), true)
                || $targetDate < $weekStart || $targetDate > (new DateTimeImmutable($weekStart, new DateTimeZone('UTC')))->modify('+13 days')->format('Y-m-d')))) {
            return self::failure('invalid_effective_date', 422);
        }
        $effectiveDate = $type === 'TEMPORAL' ? $targetDate : $effectiveStart;
        $timezone = (new ChecadorWeeklyScheduleSqlReadService())->timezoneForDate($workplaceId, $effectiveDate);
        $today = $timezone === null ? null : ChecadorOperationalPeriod::currentLocalDate($timezone, ($this->clock)());
        if ($today === null || ($type === 'TEMPORAL' ? $targetDate < $today : $effectiveStart < $today)) {
            return self::failure('invalid_effective_date', 422);
        }
        $validFrom = $type === 'TEMPORAL' ? $targetDate : $effectiveStart;
        $validTo = $type === 'TEMPORAL'
            ? (new DateTimeImmutable($targetDate, new DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d')
            : null;

        return $this->mutate($user, $workplaceId, $workerId, $weekday, 'CREATE', $key, [
            'kind' => 'create', 'template_id' => $templateId, 'assignment_type' => $type,
            'weekday' => $weekday, 'target_date' => $targetDate, 'valid_from' => $validFrom, 'valid_to' => $validTo,
            'reason' => $reason,
        ]);
    }

    /** Create selected weekdays as one immutable habitual pattern in a single SQL transaction. */
    public function createHabitualPattern(Usuario $user, array $input): array
    {
        $workplaceId = (int) ($input['workplace_id'] ?? 0);
        $workerId = (int) ($input['worker_id'] ?? 0);
        $weekStart = trim((string) ($input['week_start'] ?? ''));
        $effectiveStart = trim((string) ($input['effective_start_date'] ?? ''));
        $key = trim((string) ($input['idempotency_key'] ?? ''));
        $scheduleInput = $input['schedule'] ?? null;
        if ($workplaceId < 1 || $workerId < 1 || !is_array($scheduleInput)
            || ChecadorWeeklyScheduleService::monday($weekStart) !== $weekStart
            || !self::isDate($effectiveStart) || !self::validKey($key)) {
            return self::failure('invalid_request', 422);
        }
        $windowEnd = (new DateTimeImmutable($weekStart, new DateTimeZone('UTC')))->modify('+13 days')->format('Y-m-d');
        if ($effectiveStart < $weekStart || $effectiveStart > $windowEnd) return self::failure('invalid_effective_date', 422);

        $schedule = [];
        foreach ($scheduleInput as $weekday => $templateValue) {
            $weekday = strtoupper(trim((string) $weekday));
            $templateValue = trim((string) $templateValue);
            if ($templateValue === '') continue;
            if (!in_array($weekday, ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO', 'DOMINGO'], true)
                || ($templateValue !== 'REST' && filter_var($templateValue, FILTER_VALIDATE_INT) === false)) {
                return self::failure('invalid_request', 422);
            }
            $templateId = $templateValue === 'REST' ? null : (int) $templateValue;
            if ($templateId !== null && $templateId < 1) return self::failure('invalid_template', 422);
            $targetDate = ChecadorWeeklyScheduleService::firstWeekdayOnOrAfter($weekday, $effectiveStart);
            if ($targetDate === null || $targetDate > $windowEnd || isset($schedule[$weekday])) {
                return self::failure('invalid_effective_date', 422);
            }
            $schedule[$weekday] = ['date' => $targetDate, 'template_id' => $templateId];
        }
        if ($schedule === []) return self::failure('invalid_request', 422);
        uasort($schedule, static fn(array $a, array $b): int => $a['date'] <=> $b['date']);
        if (!($this->authorizeWrite)($user, $workplaceId)) return self::failure('permission_denied', 403);
        if (!self::operationalWritesAllowed()) return self::failure('m03_demo_mode_read_only', 403);

        $readService = new ChecadorWeeklyScheduleSqlReadService();
        $timezone = $readService->timezoneForDate($workplaceId, $effectiveStart);
        $today = $timezone === null ? null : ChecadorOperationalPeriod::currentLocalDate(
            $timezone, ($this->clock)()
        );
        if ($today === null || $effectiveStart < $today) return self::failure('invalid_effective_date', 422);

        $actorId = (int) ($user->id ?? 0);
        if ($actorId < 1) return self::failure('unauthenticated', 401);
        $payload = [
            'kind' => 'create_habitual_pattern', 'workplace_id' => $workplaceId, 'worker_id' => $workerId,
            'effective_start_date' => $effectiveStart, 'assignments' => $schedule,
        ];
        $keyHash = hash('sha256', $key);
        $fingerprint = hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $connection = Capsule::connection();
        $lockName = 'checador_m03_assignment_writer';
        $lockAcquired = false;
        try {
            $lockAcquired = $this->acquireLock($connection, $lockName);
            if (!$lockAcquired) return self::failure('concurrent_write_busy', 409);
            $existing = $this->findIdempotentAudit($actorId, $keyHash);
            if ($existing !== null) {
                if (!hash_equals((string) ($existing['fingerprint'] ?? ''), $fingerprint)) {
                    return self::failure('idempotency_conflict', 409);
                }
                $snapshots = $existing['habitual_batch']['assignments'] ?? null;
                if (!is_array($snapshots) || count($snapshots) !== count($schedule)) {
                    return self::failure('idempotency_readback_failed', 502);
                }
                $rows = [];
                foreach ($snapshots as $snapshot) {
                    $row = is_array($snapshot) ? $this->readAssignment((int) ($snapshot['id'] ?? 0), $workplaceId, $workerId) : null;
                    if ($row === null || $row !== $snapshot) return self::failure('idempotency_readback_failed', 502);
                    $rows[] = $row;
                }
                return ['ok' => true, 'assignments' => $rows, 'assignment' => $rows[0] ?? null, 'replayed' => true];
            }

            $created = $connection->transaction(function () use (
                $connection, $user, $actorId, $workplaceId, $workerId, $effectiveStart,
                $schedule, $timezone, $keyHash, $fingerprint
            ): array {
                $worker = RhPersonal::query()->whereKey($workerId)->lockForUpdate()->first(['id', 'estado']);
                if ($worker === null || (int) $worker->estado !== 1) throw new RuntimeException('worker_unavailable');
                if (!($this->authorizeWrite)($user, $workplaceId)) throw new RuntimeException('permission_denied');
                $snapshots = [];
                foreach ($schedule as $weekday => $item) {
                    $targetDate = (string) $item['date'];
                    $targetTimezone = (new ChecadorWeeklyScheduleSqlReadService())->timezoneForDate($workplaceId, $targetDate);
                    if (!ChecadorOperationalPeriod::isIanaTimezone($targetTimezone)) {
                        throw new RuntimeException('timezone_unresolved');
                    }
                    $validity = ($this->membership)($workerId, $workplaceId, $targetDate, '');
                    if (($validity['ok'] ?? false) !== true) throw new RuntimeException((string) ($validity['error'] ?? 'membership_unverified'));

                    $history = $connection->table('op_rh_v2_turnos_asignaciones')->where('id_personal', $workerId)
                        ->where('id_estacion', $workplaceId)->where('dia_semana', $weekday)
                        ->where('tipo_asignacion', 'HABITUAL')->orderBy('valido_desde')->orderBy('id')
                        ->lockForUpdate()->get()->map(static fn($row): array => (array) $row)->all();
                    $interval = ChecadorWeeklyScheduleService::habitualAssignmentInterval(
                        $history, $workerId, $workplaceId, $weekday, $targetDate
                    );
                    if (!($interval['ok'] ?? false)) throw new RuntimeException('assignment_conflict');
                    $validTo = self::earliestDate($interval['valid_to'] ?? null, $validity['valid_to'] ?? null);
                    if ($validTo !== null && $validTo <= $targetDate) throw new RuntimeException('invalid_validity');

                    $template = $item['template_id'] === null
                        ? null
                        : $this->authorizedTemplate($connection, $workplaceId, (int) $item['template_id']);
                    if ($item['template_id'] !== null && $template === null) throw new RuntimeException('template_not_allowed');
                    $firstWorkStart = $this->assertSegments($connection, $item['template_id']);
                    $this->assertAssignmentDateIsSafe(
                        $connection, $workerId, $workplaceId, $targetDate, $targetTimezone, $firstWorkStart
                    );
                    $id = (int) $connection->table('op_rh_v2_turnos_asignaciones')->insertGetId([
                        'id_personal' => $workerId, 'id_estacion' => $workplaceId, 'id_plantilla' => $item['template_id'],
                        'tipo_asignacion' => 'HABITUAL', 'dia_semana' => $weekday,
                        'valido_desde' => $targetDate, 'valido_hasta' => $validTo, 'es_activo' => 1,
                        'creado_at' => self::utcExpression($connection), 'registrado_at_utc' => self::utcExpression($connection),
                        'creado_por_usuario_id' => $actorId, 'motivo' => null,
                    ]);
                    $snapshots[] = [
                        'id' => $id, 'id_personal' => $workerId, 'id_estacion' => $workplaceId,
                        'id_plantilla' => $item['template_id'], 'tipo_asignacion' => 'HABITUAL', 'dia_semana' => $weekday,
                        'valido_desde' => $targetDate, 'valido_hasta' => $validTo, 'es_activo' => 1,
                    ];
                }
                $batch = ['effective_start_date' => $effectiveStart, 'assignments' => $snapshots];
                foreach ($snapshots as $snapshot) {
                    $template = $snapshot['id_plantilla'] === null
                        ? null
                        : $this->templateVersion($connection, (int) $snapshot['id_plantilla']);
                    $this->audit($connection, $actorId, $workplaceId, $workerId, (int) $snapshot['id'], 'CREATE',
                        'HABITUAL', (string) $snapshot['valido_desde'], $snapshot['valido_hasta'],
                        $snapshot['id_plantilla'], $template['version'] ?? null, $keyHash, $fingerprint,
                        ['assignment_id' => (int) $snapshot['id'], 'snapshot' => $snapshot, 'habitual_batch' => $batch]);
                }

                // Read back every persisted row and audit event before COMMIT so a
                // mismatch rolls the whole seven-day pattern back atomically.
                $confirmed = [];
                foreach ($snapshots as $snapshot) {
                    $row = $this->readAssignment((int) $snapshot['id'], $workplaceId, $workerId);
                    if ($row === null || $row !== $snapshot) throw new RuntimeException('readback_mismatch');
                    $confirmed[] = $row;
                }
                if ($this->auditCount($actorId, $keyHash, $fingerprint) !== count($schedule)) {
                    throw new RuntimeException('audit_readback_failed');
                }
                return $confirmed;
            }, 1);

            $rows = [];
            foreach ($created as $confirmed) {
                $row = $this->readAssignment((int) $confirmed['id'], $workplaceId, $workerId);
                if ($row === null || $row !== $confirmed) return self::failure('readback_mismatch', 502);
                $rows[] = $row;
            }
            return ['ok' => true, 'assignments' => $rows, 'assignment' => $rows[0] ?? null, 'replayed' => false];
        } catch (Throwable $exception) {
            $kind = $exception instanceof RuntimeException ? $exception->getMessage() : 'database_write_failed';
            if (!in_array($kind, ['permission_denied', 'worker_unavailable', 'membership_unverified', 'membership_conflict',
                'assignment_conflict', 'template_not_allowed', 'invalid_template_segments', 'invalid_validity',
                'invalid_effective_date', 'shift_already_started', 'journey_already_materialized', 'timezone_unresolved',
                'readback_mismatch', 'audit_readback_failed'], true)) {
                $kind = 'database_write_failed';
            }
            Logger::warning('Checador M03 SQL habitual pattern write failed', [
                'operation' => 'm03_habitual_pattern_create', 'workplace_id' => $workplaceId,
                'worker_id' => $workerId, 'error_code' => $kind,
            ]);
            return self::failure($kind, in_array($kind, ['database_write_failed', 'readback_mismatch', 'audit_readback_failed'], true) ? 503 : 409);
        } finally {
            if ($lockAcquired) $this->releaseLock($connection, $lockName);
        }
    }

    /** @return array{ok:bool,assignment?:array,error?:string,status?:int,replayed?:bool} */
    public function close(Usuario $user, array $input): array
    {
        $workplaceId = (int) ($input['workplace_id'] ?? 0);
        $workerId = (int) ($input['worker_id'] ?? 0);
        $assignmentId = (int) ($input['assignment_id'] ?? 0);
        $closeDate = trim((string) ($input['effective_close_date'] ?? ''));
        $expectedFrom = trim((string) ($input['expected_valido_desde'] ?? ''));
        $expectedTo = trim((string) ($input['expected_valido_hasta'] ?? '')) ?: null;
        $key = trim((string) ($input['idempotency_key'] ?? ''));
        if ($workplaceId < 1 || $workerId < 1 || $assignmentId < 1 || !self::isDate($closeDate)
            || !self::isDate($expectedFrom) || ($expectedTo !== null && !self::isDate($expectedTo)) || !self::validKey($key)) {
            return self::failure('invalid_request', 422);
        }
        if (!($this->authorizeWrite)($user, $workplaceId)) return self::failure('permission_denied', 403);
        if (!self::operationalWritesAllowed()) return self::failure('m03_demo_mode_read_only', 403);
        return $this->mutate($user, $workplaceId, $workerId, '', 'CLOSE', $key, [
            'kind' => 'close', 'assignment_id' => $assignmentId, 'close_date' => $closeDate,
            'expected_from' => $expectedFrom, 'expected_to' => $expectedTo,
        ]);
    }

    /** @return array{ok:bool,assignment?:array,error?:string,status?:int,replayed?:bool} */
    public function replace(Usuario $user, array $input): array
    {
        $workplaceId = (int) ($input['workplace_id'] ?? 0);
        $workerId = (int) ($input['worker_id'] ?? 0);
        $assignmentId = (int) ($input['assignment_id'] ?? 0);
        $closeDate = trim((string) ($input['effective_close_date'] ?? ''));
        $expectedFrom = trim((string) ($input['expected_valido_desde'] ?? ''));
        $expectedTo = trim((string) ($input['expected_valido_hasta'] ?? '')) ?: null;
        $replacementTo = trim((string) ($input['replacement_valido_hasta'] ?? '')) ?: null;
        $templateValue = trim((string) ($input['replacement_template_id'] ?? ''));
        $key = trim((string) ($input['idempotency_key'] ?? ''));
        if ($workplaceId < 1 || $workerId < 1 || $assignmentId < 1 || !self::isDate($closeDate)
            || !self::isDate($expectedFrom) || ($expectedTo !== null && !self::isDate($expectedTo))
            || ($replacementTo !== null && !self::isDate($replacementTo))
            || ($templateValue !== 'REST' && filter_var($templateValue, FILTER_VALIDATE_INT) === false)
            || !self::validKey($key)) return self::failure('invalid_request', 422);
        if ($replacementTo !== null && $replacementTo <= $closeDate) return self::failure('invalid_validity', 422);
        $templateId = $templateValue === 'REST' ? null : (int) $templateValue;
        if ($templateId !== null && $templateId < 1) return self::failure('invalid_template', 422);
        if (!($this->authorizeWrite)($user, $workplaceId)) return self::failure('permission_denied', 403);
        if (!self::operationalWritesAllowed()) return self::failure('m03_demo_mode_read_only', 403);
        return $this->mutate($user, $workplaceId, $workerId, '', 'REPLACE', $key, [
            'kind' => 'replace', 'assignment_id' => $assignmentId, 'close_date' => $closeDate,
            'expected_from' => $expectedFrom, 'expected_to' => $expectedTo,
            'replacement_to' => $replacementTo, 'template_id' => $templateId,
        ]);
    }

    private function mutate(Usuario $user, int $workplaceId, int $workerId, string $weekday, string $operation, string $key, array $payload): array
    {
        $actorId = (int) ($user->id ?? 0);
        if ($actorId < 1) return self::failure('unauthenticated', 401);
        // The existing schema has no dedicated unique idempotency column. The
        // durable key hash/fingerprint lives in the assignment audit JSON written
        // in the same transaction; this DB-wide lock serializes lookup and writes
        // across Portal3 instances. It does not replace the durable audit record,
        // and the API writer must be cut off before this service is enabled.
        $lockName = 'checador_m03_assignment_writer';
        $connection = Capsule::connection();
        $lockAcquired = false;
        $keyHash = hash('sha256', $key);
        $fingerprint = hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        try {
            $lockAcquired = $this->acquireLock($connection, $lockName);
            if (!$lockAcquired) return self::failure('concurrent_write_busy', 409);
            $existing = $this->findIdempotentAudit($actorId, $keyHash);
            if ($existing !== null) {
                if (!hash_equals((string) ($existing['fingerprint'] ?? ''), $fingerprint)) {
                    return self::failure('idempotency_conflict', 409);
                }
                $row = is_array($existing['snapshot'] ?? null) ? $existing['snapshot'] : null;
                $id = (int) ($existing['assignment_id'] ?? 0);
                if ($row === null) $row = $id > 0 ? $this->readAssignment($id, $workplaceId, $workerId) : null;
                return $row === null ? self::failure('idempotency_readback_failed', 502)
                    : ['ok' => true, 'assignment' => $row, 'replayed' => true];
            }

            $timezone = null;
            if (($payload['kind'] ?? '') === 'create') {
                $timezone = (new ChecadorWeeklyScheduleSqlReadService())->timezoneForDate(
                    $workplaceId, (string) ($payload['target_date'] ?? $payload['valid_from'] ?? '')
                );
            }
            if (($payload['kind'] ?? '') === 'create' && !ChecadorOperationalPeriod::isIanaTimezone($timezone)) {
                return self::failure('timezone_unresolved', 422);
            }
            $result = $connection->transaction(function () use (
                $connection, $user, $actorId, $workplaceId, $workerId, $weekday, $operation, $payload, $timezone, $keyHash, $fingerprint
            ): array {
                $worker = RhPersonal::query()->whereKey($workerId)->lockForUpdate()->first(['id', 'estado']);
                if ($worker === null || (int) $worker->estado !== 1) throw new RuntimeException('worker_unavailable');
                if (!($this->authorizeWrite)($user, $workplaceId)) throw new RuntimeException('permission_denied');

                if ($payload['kind'] === 'create') {
                    $validity = ($this->membership)($workerId, $workplaceId, (string) $payload['valid_from'], (string) ($payload['valid_to'] ?? ''));
                    if (($validity['ok'] ?? false) !== true) throw new RuntimeException((string) ($validity['error'] ?? 'membership_unverified'));
                    $validTo = $payload['valid_to'] ?? $validity['valid_to'];
                    if ($payload['assignment_type'] === 'HABITUAL') {
                        $history = $connection->table('op_rh_v2_turnos_asignaciones')->where('id_personal', $workerId)
                            ->where('id_estacion', $workplaceId)->where('dia_semana', $weekday)
                            ->orderBy('valido_desde')->orderBy('id')->lockForUpdate()->get()->map(static fn($row): array => (array) $row)->all();
                        $interval = ChecadorWeeklyScheduleService::habitualAssignmentInterval(
                            $history, $workerId, $workplaceId, $weekday, (string) $payload['valid_from']
                        );
                        if (!($interval['ok'] ?? false)) throw new RuntimeException('assignment_conflict');
                        $validTo = self::earliestDate($interval['valid_to'] ?? null, $validTo);
                    } else {
                        $conflict = $connection->table('op_rh_v2_turnos_asignaciones')->where('id_personal', $workerId)
                            ->where('id_estacion', $workplaceId)->where('dia_semana', $weekday)->where('tipo_asignacion', 'TEMPORAL')
                            ->where('valido_desde', '<', (string) $payload['valid_to'])
                            ->where(static function ($query) use ($payload): void {
                                $query->whereNull('valido_hasta')->orWhere('valido_hasta', '>', (string) $payload['valid_from']);
                            })->lockForUpdate()->exists();
                        if ($conflict) throw new RuntimeException('assignment_conflict');
                    }
                    if ($validTo !== null && $validTo <= (string) $payload['valid_from']) {
                        throw new RuntimeException('invalid_validity');
                    }
                    $template = $payload['template_id'] === null ? null : $this->authorizedTemplate($connection, $workplaceId, (int) $payload['template_id']);
                    if ($payload['template_id'] !== null && $template === null) throw new RuntimeException('template_not_allowed');
                    $firstWorkStart = $this->assertSegments($connection, $payload['template_id']);
                    $this->assertAssignmentDateIsSafe(
                        $connection, $workerId, $workplaceId, (string) ($payload['target_date'] ?? $payload['valid_from']), $timezone, $firstWorkStart
                    );
                    $id = (int) $connection->table('op_rh_v2_turnos_asignaciones')->insertGetId([
                        'id_personal' => $workerId, 'id_estacion' => $workplaceId,
                        'id_plantilla' => $payload['template_id'], 'tipo_asignacion' => $payload['assignment_type'],
                        'dia_semana' => $weekday, 'valido_desde' => $payload['valid_from'], 'valido_hasta' => $validTo,
                        'es_activo' => 1, 'creado_at' => self::utcExpression($connection),
                        'registrado_at_utc' => self::utcExpression($connection), 'creado_por_usuario_id' => $actorId,
                        'motivo' => $payload['reason'] !== '' ? $payload['reason'] : null,
                    ]);
                    $this->audit($connection, $actorId, $workplaceId, $workerId, $id, $operation, $payload['assignment_type'],
                        (string) $payload['valid_from'], $validTo, $payload['template_id'], $template['version'] ?? null,
                        $keyHash, $fingerprint, ['assignment_id' => $id, 'snapshot' => [
                            'id' => $id, 'id_personal' => $workerId, 'id_estacion' => $workplaceId,
                            'id_plantilla' => $payload['template_id'], 'tipo_asignacion' => $payload['assignment_type'],
                            'dia_semana' => $weekday, 'valido_desde' => $payload['valid_from'],
                            'valido_hasta' => $validTo, 'es_activo' => 1,
                        ]]);
                    return ['assignment_id' => $id, 'valid_to' => $validTo];
                }

                $source = $connection->table('op_rh_v2_turnos_asignaciones')->where('id', (int) $payload['assignment_id'])
                    ->where('id_personal', $workerId)->where('id_estacion', $workplaceId)->lockForUpdate()->first();
                if ($source === null || (int) $source->es_activo !== 1
                    || (string) $source->valido_desde !== (string) $payload['expected_from']
                    || ($source->valido_hasta === null ? null : (string) $source->valido_hasta) !== ($payload['expected_to'] ?? null)) {
                    throw new RuntimeException('stale_assignment');
                }
                $closeDate = (string) $payload['close_date'];
                if ($closeDate <= (string) $source->valido_desde
                    || ($source->valido_hasta !== null && $closeDate > (string) $source->valido_hasta)
                    || ChecadorWeeklyScheduleService::weekdayFor($closeDate) !== (string) $source->dia_semana) {
                    throw new RuntimeException('invalid_close_date');
                }
                $updated = $connection->table('op_rh_v2_turnos_asignaciones')->where('id', (int) $source->id)
                    ->where('es_activo', 1)->update([
                        'es_activo' => 0, 'valido_hasta' => $closeDate, 'actualizado_por_usuario_id' => $actorId,
                    ]);
                if ($updated !== 1) throw new RuntimeException('stale_assignment');
                $oldTemplate = $source->id_plantilla === null ? null : $this->templateVersion($connection, (int) $source->id_plantilla);
                $this->audit($connection, $actorId, $workplaceId, $workerId, (int) $source->id, $operation,
                    (string) $source->tipo_asignacion, (string) $source->valido_desde, $closeDate,
                    $source->id_plantilla === null ? null : (int) $source->id_plantilla, $oldTemplate['version'] ?? null,
                    $keyHash, $fingerprint, ['assignment_id' => (int) $source->id, 'snapshot' => [
                        'id' => (int) $source->id, 'id_personal' => $workerId, 'id_estacion' => $workplaceId,
                        'id_plantilla' => $source->id_plantilla === null ? null : (int) $source->id_plantilla,
                        'tipo_asignacion' => (string) $source->tipo_asignacion, 'dia_semana' => (string) $source->dia_semana,
                        'valido_desde' => (string) $source->valido_desde, 'valido_hasta' => $closeDate, 'es_activo' => 0,
                    ]]);
                if ($payload['kind'] === 'close') return ['assignment_id' => (int) $source->id, 'closed_id' => (int) $source->id];

                $validity = ($this->membership)($workerId, $workplaceId, $closeDate, (string) ($payload['replacement_to'] ?? ''));
                if (($validity['ok'] ?? false) !== true) throw new RuntimeException((string) ($validity['error'] ?? 'membership_unverified'));
                $nextBoundary = null;
                if ((string) $source->tipo_asignacion === 'HABITUAL') {
                    $history = $connection->table('op_rh_v2_turnos_asignaciones')->where('id_personal', $workerId)
                        ->where('id_estacion', $workplaceId)->where('dia_semana', (string) $source->dia_semana)
                        ->where('tipo_asignacion', 'HABITUAL')->orderBy('valido_desde')->orderBy('id')
                        ->lockForUpdate()->get()->map(static fn($row): array => (array) $row)->all();
                    $interval = ChecadorWeeklyScheduleService::habitualAssignmentInterval(
                        $history, $workerId, $workplaceId, (string) $source->dia_semana, $closeDate
                    );
                    if (!($interval['ok'] ?? false)) throw new RuntimeException('assignment_conflict');
                    $nextBoundary = $interval['valid_to'] ?? null;
                } else {
                    $nextBoundary = (new DateTimeImmutable($closeDate, new DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d');
                }
                $replacementTo = self::earliestDate(
                    self::earliestDate($payload['replacement_to'] ?? null, $nextBoundary),
                    $validity['valid_to'] ?? null
                );
                if ($replacementTo !== null && $replacementTo <= $closeDate) throw new RuntimeException('invalid_validity');
                $template = $payload['template_id'] === null ? null : $this->authorizedTemplate($connection, $workplaceId, (int) $payload['template_id']);
                if ($payload['template_id'] !== null && $template === null) throw new RuntimeException('template_not_allowed');
                $this->assertSegments($connection, $payload['template_id']);
                $newId = (int) $connection->table('op_rh_v2_turnos_asignaciones')->insertGetId([
                    'id_personal' => $workerId, 'id_estacion' => $workplaceId,
                    'id_plantilla' => $payload['template_id'], 'tipo_asignacion' => (string) $source->tipo_asignacion,
                    'dia_semana' => (string) $source->dia_semana, 'valido_desde' => $closeDate, 'valido_hasta' => $replacementTo,
                    'es_activo' => 1, 'creado_at' => self::utcExpression($connection),
                    'registrado_at_utc' => self::utcExpression($connection), 'creado_por_usuario_id' => $actorId,
                    'motivo' => 'REEMPLAZO M03',
                ]);
                $this->audit($connection, $actorId, $workplaceId, $workerId, $newId, 'REPLACE',
                    (string) $source->tipo_asignacion, $closeDate, $replacementTo, $payload['template_id'], $template['version'] ?? null,
                    $keyHash, $fingerprint, ['assignment_id' => $newId, 'replaces_assignment_id' => (int) $source->id,
                        'snapshot' => [
                            'id' => $newId, 'id_personal' => $workerId, 'id_estacion' => $workplaceId,
                            'id_plantilla' => $payload['template_id'], 'tipo_asignacion' => (string) $source->tipo_asignacion,
                            'dia_semana' => (string) $source->dia_semana, 'valido_desde' => $closeDate,
                            'valido_hasta' => $replacementTo, 'es_activo' => 1,
                        ]]);
                return ['assignment_id' => $newId, 'closed_id' => (int) $source->id, 'close_date' => $closeDate,
                    'valid_to' => $replacementTo, 'template_id' => $payload['template_id']];
            }, 1);

            $row = $this->readAssignment((int) $result['assignment_id'], $workplaceId, $workerId);
            if ($row === null) return self::failure('readback_failed', 502);
            if ($payload['kind'] === 'create'
                && ((string) $row['valido_desde'] !== (string) $payload['valid_from']
                    || (($row['id_plantilla'] ?? null) !== ($payload['template_id'] ?? null))
                    || (string) $row['tipo_asignacion'] !== (string) $payload['assignment_type']
                    || (string) $row['dia_semana'] !== (string) $weekday
                    || (($row['valido_hasta'] ?? null) !== ($result['valid_to'] ?? null))
                    || (int) $row['es_activo'] !== 1)) return self::failure('readback_mismatch', 502);
            if ($payload['kind'] === 'close'
                && ((int) $row['es_activo'] !== 0 || (string) $row['valido_hasta'] !== (string) $payload['close_date'])) {
                return self::failure('readback_mismatch', 502);
            }
            if ($payload['kind'] === 'replace') {
                $closed = $this->readAssignment((int) $result['closed_id'], $workplaceId, $workerId);
                if ($closed === null || (int) $closed['es_activo'] !== 0
                    || (string) $closed['valido_hasta'] !== (string) $result['close_date']
                    || (int) $row['es_activo'] !== 1 || (string) $row['valido_desde'] !== (string) $result['close_date']
                    || (($row['id_plantilla'] ?? null) !== ($result['template_id'] ?? null))
                    || (($row['valido_hasta'] ?? null) !== ($result['valid_to'] ?? null))) {
                    return self::failure('readback_mismatch', 502);
                }
            }
            $auditCount = $this->auditCount($actorId, $keyHash, $fingerprint);
            if ($auditCount < 1) return self::failure('audit_readback_failed', 502);
            return ['ok' => true, 'assignment' => $row, 'replayed' => false];
        } catch (Throwable $exception) {
            $kind = $exception instanceof RuntimeException ? $exception->getMessage() : 'database_write_failed';
            if (!in_array($kind, ['permission_denied', 'worker_unavailable', 'membership_unverified', 'membership_conflict',
                'assignment_conflict', 'template_not_allowed', 'invalid_template_segments', 'stale_assignment',
                'invalid_close_date', 'invalid_validity', 'invalid_membership_date', 'invalid_effective_date',
                'shift_already_started', 'journey_already_materialized'], true)) $kind = 'database_write_failed';
            Logger::warning('Checador M03 SQL assignment write failed', [
                'operation' => 'm03_assignment_' . strtolower($operation), 'workplace_id' => $workplaceId,
                'worker_id' => $workerId, 'error_code' => $kind,
            ]);
            return self::failure($kind, $kind === 'database_write_failed' ? 503 : 409);
        } finally {
            if ($lockAcquired) $this->releaseLock($connection, $lockName);
        }
    }

    private function resolveMembership(int $workerId, int $workplaceId, string $from, string $to): array
    {
        $reader = new ChecadorWeeklyScheduleSqlReadService();
        $timezone = $reader->timezoneForDate($workplaceId, $from);
        if ($timezone === null) return ['ok' => false, 'error' => 'membership_unverified'];
        $start = (new DateTimeImmutable($from . ' 00:00:00', new DateTimeZone($timezone)))
            ->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
        $resolved = (new WorkerWorkplaceAssignmentResolver())->resolveAssignments($workerId, $start);
        if (!in_array($resolved['status'] ?? '', ['RESOLVED_SINGLE', 'RESOLVED_MULTIPLE'], true)) {
            return ['ok' => false, 'error' => 'membership_unverified'];
        }
        $matches = array_values(array_filter($resolved['assignments'] ?? [], static fn(array $row): bool =>
            (int) ($row['workplace_id'] ?? 0) === $workplaceId));
        if (count($matches) !== 1) return ['ok' => false, 'error' => count($matches) > 1 ? 'membership_conflict' : 'membership_unverified'];
        $validToUtc = $matches[0]['valid_to_utc'] ?? null;
        $membershipEndLocal = null;
        if (is_string($validToUtc) && $validToUtc !== '') {
            $membershipEndLocal = (new DateTimeImmutable($validToUtc))->setTimezone(new DateTimeZone($timezone))->format('Y-m-d');
            if ($membershipEndLocal <= $from) return ['ok' => false, 'error' => 'invalid_membership_date'];
            if ($to !== null && $to !== '' && $membershipEndLocal < $to) {
                return ['ok' => true, 'valid_to' => $membershipEndLocal];
            }
        }
        return ['ok' => true, 'valid_to' => $membershipEndLocal];
    }

    private function authorizedTemplate($connection, int $workplaceId, int $templateId): ?array
    {
        $query = $connection->table('op_rh_v2_turnos_plantillas')->where('id', $templateId)->where('es_activo', 1);
        if ($workplaceId === ChecadorAccessService::AUTOLAVADO_LOCALITY_ID) {
            $query->where('scope', 'STATION')->where('id_estacion', $workplaceId);
        } else {
            $query->where(static function ($scope) use ($workplaceId): void {
                $scope->where(static function ($global): void { $global->where('scope', 'GLOBAL')->whereNull('id_estacion'); })
                    ->orWhere(static function ($station) use ($workplaceId): void { $station->where('scope', 'STATION')->where('id_estacion', $workplaceId); });
            });
        }
        $row = $query->lockForUpdate()->first(['id', 'version', 'scope', 'id_estacion']);
        return $row === null ? null : ['id' => (int) $row->id, 'version' => (int) $row->version];
    }

    /** @return array{day_offset:int,time:string}|null earliest persisted WORK segment start */
    private function assertSegments($connection, ?int $templateId): ?array
    {
        if ($templateId === null) return null;
        $segments = $connection->table('op_rh_v2_turnos_segmentos')->where('id_plantilla', $templateId)
            ->orderBy('ordinal')->lockForUpdate()->get(['tipo_segmento', 'hora_inicio_local', 'hora_fin_local', 'dia_offset_inicio', 'dia_offset_fin']);
        $workCount = 0;
        $firstWorkStart = null;
        foreach ($segments as $segment) {
            if (strtoupper((string) $segment->tipo_segmento) === 'WORK') {
                $workCount++;
                $candidate = ['day_offset' => (int) $segment->dia_offset_inicio, 'time' => (string) $segment->hora_inicio_local];
                if ($firstWorkStart === null || [$candidate['day_offset'], $candidate['time']] < [$firstWorkStart['day_offset'], $firstWorkStart['time']]) {
                    $firstWorkStart = $candidate;
                }
            }
            if (!self::validTime((string) $segment->hora_inicio_local) || !self::validTime((string) $segment->hora_fin_local)
                || !in_array((int) $segment->dia_offset_inicio, [0, 1], true)
                || !in_array((int) $segment->dia_offset_fin, [0, 1], true)) throw new RuntimeException('invalid_template_segments');
        }
        if ($workCount < 1 || $firstWorkStart === null) throw new RuntimeException('invalid_template_segments');
        return $firstWorkStart;
    }

    /** A same-day assignment is allowed only before its persisted shift start and before Motor materialization. */
    private function assertAssignmentDateIsSafe(
        $connection,
        int $workerId,
        int $workplaceId,
        string $targetDate,
        string $timezone,
        ?array $firstWorkStart
    ): void {
        if ($connection->table('op_rh_v2_jornadas_programadas')
            ->where('id_personal', $workerId)->where('id_estacion', $workplaceId)
            ->where('fecha_jornada_local', $targetDate)->exists()) {
            throw new RuntimeException('journey_already_materialized');
        }

        $nowUtc = ($this->clock)()->setTimezone(new DateTimeZone('UTC'));
        $today = ChecadorOperationalPeriod::currentLocalDate($timezone, $nowUtc);
        if ($today === null || $targetDate < $today) throw new RuntimeException('invalid_effective_date');
        if ($targetDate > $today) return;
        if ($firstWorkStart === null) throw new RuntimeException('invalid_effective_date');

        try {
            $startDate = (new DateTimeImmutable($targetDate . ' 00:00:00', new DateTimeZone($timezone)))
                ->modify('+' . (int) $firstWorkStart['day_offset'] . ' days')->format('Y-m-d');
            $shiftStart = new DateTimeImmutable($startDate . ' ' . $firstWorkStart['time'], new DateTimeZone($timezone));
        } catch (Throwable) {
            throw new RuntimeException('invalid_template_segments');
        }
        if ($shiftStart <= $nowUtc->setTimezone(new DateTimeZone($timezone))) {
            throw new RuntimeException('shift_already_started');
        }
    }

    private function audit($connection, int $actorId, int $workplaceId, int $workerId, int $assignmentId, string $operation,
        string $type, string $from, ?string $to, ?int $templateId, ?int $templateVersion,
        string $keyHash, string $fingerprint, array $extra): void
    {
        $details = json_encode($extra + [
            'idempotency_key_hash' => $keyHash, 'payload_fingerprint' => $fingerprint,
            'writer' => 'portal3_m03_sql_v1',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($details)) throw new RuntimeException('database_write_failed');
        $connection->table('op_rh_v2_turnos_audit_log')->insert([
            'entity_type' => 'ASSIGNMENT', 'entity_id' => $assignmentId,
            'operacion' => $operation, 'id_usuario_actor' => $actorId, 'id_estacion' => $workplaceId,
            'id_personal' => $workerId, 'tipo_asignacion' => $type,
            'valido_desde' => $from, 'valido_hasta' => $to,
            'id_plantilla' => $templateId, 'version_plantilla' => $templateVersion,
            'detalle_json' => $details, 'recorded_at_utc' => self::utcExpression($connection),
        ]);
    }

    private function findIdempotentAudit(int $actorId, string $keyHash): ?array
    {
        $query = Capsule::table('op_rh_v2_turnos_audit_log')->where('entity_type', 'ASSIGNMENT')
            ->where('id_usuario_actor', $actorId);
        $jsonValue = Capsule::connection()->getDriverName() === 'mysql'
            ? "JSON_UNQUOTE(JSON_EXTRACT(detalle_json, '$.idempotency_key_hash'))"
            : "JSON_EXTRACT(detalle_json, '$.idempotency_key_hash')";
        $row = $query->whereRaw($jsonValue . ' = ?', [$keyHash])
            ->orderByDesc('id')->first(['entity_id', 'detalle_json']);
        if ($row === null) return null;
        $details = json_decode((string) $row->detalle_json, true);
        if (!is_array($details)) return null;
        return [
            'assignment_id' => (int) ($details['assignment_id'] ?? $row->entity_id),
            'fingerprint' => (string) ($details['payload_fingerprint'] ?? ''),
            'snapshot' => is_array($details['snapshot'] ?? null) ? $details['snapshot'] : null,
            'habitual_batch' => is_array($details['habitual_batch'] ?? null) ? $details['habitual_batch'] : null,
        ];
    }

    private function auditCount(int $actorId, string $keyHash, string $fingerprint): int
    {
        $query = Capsule::table('op_rh_v2_turnos_audit_log')->where('entity_type', 'ASSIGNMENT')
            ->where('id_usuario_actor', $actorId);
        $keyExpr = Capsule::connection()->getDriverName() === 'mysql'
            ? "JSON_UNQUOTE(JSON_EXTRACT(detalle_json, '$.idempotency_key_hash'))"
            : "JSON_EXTRACT(detalle_json, '$.idempotency_key_hash')";
        $fingerprintExpr = Capsule::connection()->getDriverName() === 'mysql'
            ? "JSON_UNQUOTE(JSON_EXTRACT(detalle_json, '$.payload_fingerprint'))"
            : "JSON_EXTRACT(detalle_json, '$.payload_fingerprint')";
        return (int) $query->whereRaw($keyExpr . ' = ?', [$keyHash])
            ->whereRaw($fingerprintExpr . ' = ?', [$fingerprint])
            ->count();
    }

    private function readAssignment(int $id, int $workplaceId, int $workerId): ?array
    {
        $row = Capsule::table('op_rh_v2_turnos_asignaciones')->where('id', $id)->where('id_estacion', $workplaceId)
            ->where('id_personal', $workerId)->first([
                'id', 'id_personal', 'id_estacion', 'id_plantilla', 'tipo_asignacion', 'dia_semana',
                'valido_desde', 'valido_hasta', 'es_activo', 'creado_por_usuario_id', 'actualizado_por_usuario_id', 'motivo',
            ]);
        return $row === null ? null : [
            'id' => (int) $row->id, 'id_personal' => (int) $row->id_personal, 'id_estacion' => (int) $row->id_estacion,
            'id_plantilla' => $row->id_plantilla === null ? null : (int) $row->id_plantilla,
            'tipo_asignacion' => (string) $row->tipo_asignacion, 'dia_semana' => (string) $row->dia_semana,
            'valido_desde' => (string) $row->valido_desde, 'valido_hasta' => $row->valido_hasta === null ? null : (string) $row->valido_hasta,
            'es_activo' => (int) $row->es_activo,
        ];
    }

    private function templateVersion($connection, int $templateId): ?array
    {
        $row = $connection->table('op_rh_v2_turnos_plantillas')->where('id', $templateId)->first(['version']);
        return $row === null ? null : ['version' => (int) $row->version];
    }

    private function acquireLock($connection, string $name): bool
    {
        if ($this->testLock !== null) return (bool) ($this->testLock)($name);
        if ($connection->getDriverName() !== 'mysql') return false;
        $row = $connection->selectOne('SELECT GET_LOCK(?, 8) AS acquired', [$name]);
        return (int) ($row->acquired ?? 0) === 1;
    }

    private function releaseLock($connection, string $name): void
    {
        if ($this->testLock !== null) return;
        if ($connection->getDriverName() === 'mysql') $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$name]);
    }

    private static function operationalWritesAllowed(): bool
    {
        return !ChecadorDailyReadService::demoModeEnabled();
    }

    private static function earliestDate(?string $a, ?string $b): ?string
    {
        if ($a === null || $a === '') return $b;
        if ($b === null || $b === '') return $a;
        return min($a, $b);
    }

    private static function isDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private static function validTime(string $value): bool
    {
        return preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?\z/', $value) === 1;
    }

    private static function validKey(string $key): bool
    {
        return strlen($key) >= 16 && strlen($key) <= 128 && preg_match('/\A[A-Za-z0-9._:-]+\z/', $key) === 1;
    }

    private static function utcExpression($connection): mixed
    {
        return $connection->getDriverName() === 'mysql'
            ? Capsule::raw('UTC_TIMESTAMP()')
            : gmdate('Y-m-d H:i:s');
    }

    private static function failure(string $kind, int $status): array
    {
        return ['ok' => false, 'error' => $kind, 'status' => $status];
    }
}
