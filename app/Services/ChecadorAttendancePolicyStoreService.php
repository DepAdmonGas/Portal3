<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Operativo\AttendancePolicyVersion;
use App\Models\Operativo\RhLocalidad;
use App\Models\Usuario;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Illuminate\Database\Capsule\Manager as Capsule;
use Throwable;

/** SQL read/write boundary for the existing, append-only M11 policy store. */
final class ChecadorAttendancePolicyStoreService
{
    private const SOURCE = 'PORTAL3_ADMIN';

    /** @var callable():DateTimeImmutable */
    private $clock;
    /** @var callable(Usuario,int):bool */
    private $authorizeWrite;

    public function __construct(?callable $clock = null, ?callable $authorizeWrite = null)
    {
        $this->clock = $clock ?? static fn(): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->authorizeWrite = $authorizeWrite ?? static function (Usuario $actor, int $workplaceId): bool {
            $authenticated = \App\Core\Auth::user();
            return $authenticated !== null && (int) $authenticated->id === (int) $actor->id
                && (ChecadorAccessService::authorizeAttendancePolicy($workplaceId, 'leer')['allowed'] ?? false)
                && (ChecadorAccessService::authorizeAttendancePolicy($workplaceId, 'editar')['allowed'] ?? false);
        };
    }

    /** @return array{ok:bool,data?:array<string,mixed>,error?:string} */
    public function readCurrent(int $workplaceId): array
    {
        if ($workplaceId < 1) return ['ok' => false, 'error' => 'invalid_workplace'];
        try {
            $now = ($this->clock)()->setTimezone(new DateTimeZone('UTC'));
            $candidateDate = $now->format('Y-m-d');
            $timezone = '';
            for ($attempt = 0; $attempt < 4; $attempt++) {
                $timezone = $this->timezoneForDate($workplaceId, $candidateDate);
                $localDate = $now->setTimezone(new DateTimeZone($timezone))->format('Y-m-d');
                if ($localDate === $candidateDate) break;
                $candidateDate = $localDate;
            }
            $timezone = $this->timezoneForDate($workplaceId, $candidateDate);
            $verifiedLocalDate = $now->setTimezone(new DateTimeZone($timezone))->format('Y-m-d');
            if ($verifiedLocalDate !== $candidateDate) return ['ok' => false, 'error' => 'workplace_timezone_unresolved'];
            return $this->readForEffectiveDate($workplaceId, $candidateDate, $timezone, $candidateDate);
        } catch (Throwable) {
            return ['ok' => false, 'error' => 'attendance_policy_read_failed'];
        }
    }

    /** @return array{ok:bool,data?:array<string,mixed>,error?:string} */
    public function readForEffectiveDate(int $workplaceId, string $effectiveDate, ?string $timezone = null, ?string $currentLocalDate = null): array
    {
        if ($workplaceId < 1 || !self::isCivilDate($effectiveDate)) return ['ok' => false, 'error' => 'invalid_request'];
        try {
            $timezone ??= $this->timezoneForDate($workplaceId, $effectiveDate);
            if (!self::isIanaTimezone($timezone)) return ['ok' => false, 'error' => 'workplace_timezone_unresolved'];
            $now = ($this->clock)()->setTimezone(new DateTimeZone('UTC'));
            $currentLocalDate ??= $now->setTimezone(new DateTimeZone($timezone))->format('Y-m-d');
            $effective = AttendancePolicyVersion::query()
                ->where('workplace_id', $workplaceId)
                ->where('effective_from_local_date', '<=', $effectiveDate)
                ->orderByDesc('effective_from_local_date')->orderByDesc('policy_version')->first();
            $next = AttendancePolicyVersion::query()
                ->where('workplace_id', $workplaceId)
                ->where('effective_from_local_date', '>', $currentLocalDate)
                ->orderBy('effective_from_local_date')->orderBy('policy_version')->first();
            $latest = AttendancePolicyVersion::query()->where('workplace_id', $workplaceId)
                ->orderByDesc('policy_version')->first();

            return ['ok' => true, 'data' => [
                'workplace_id' => $workplaceId,
                'effective_date' => $effectiveDate,
                'timezone_id' => $timezone,
                'current_local_date' => $currentLocalDate,
                'status' => $effective === null ? 'LEGACY_UNVERSIONED' : 'VERSIONED',
                'policy' => $effective?->toPolicyArray(),
                'next_scheduled_policy' => $next?->toPolicyArray(),
                'latest_scheduled_policy' => $latest?->toPolicyArray(),
            ]];
        } catch (Throwable) {
            return ['ok' => false, 'error' => 'attendance_policy_read_failed'];
        }
    }

    /** @return array{ok:bool,policy?:array<string,mixed>,next_scheduled_policy?:?array<string,mixed>,error?:string} */
    public function create(Usuario $actor, int $workplaceId, string $effectiveDate, mixed $tolerance, mixed $deadlineDays, string $idempotencyKey): array
    {
        $toleranceValue = self::strictInteger($tolerance);
        $deadlineValue = self::strictInteger($deadlineDays);
        if ($workplaceId < 1 || (int) ($actor->id ?? 0) < 1 || !self::isCivilDate($effectiveDate)
            || $toleranceValue === null || $toleranceValue > 120
            || $deadlineValue === null || $deadlineValue > 30
            || preg_match('/\A[A-Za-z0-9._:-]{1,64}\z/D', $idempotencyKey) !== 1) {
            return ['ok' => false, 'error' => 'invalid_policy_input'];
        }
        if (ChecadorDailyReadService::demoModeEnabled()) return ['ok' => false, 'error' => 'demo_mode'];
        if (!(($this->authorizeWrite)($actor, $workplaceId))) {
            return ['ok' => false, 'error' => 'permission_denied'];
        }

        try {
            $createdOrExisting = Capsule::connection()->transaction(function () use (
                $actor, $workplaceId, $effectiveDate, $toleranceValue, $deadlineValue, $idempotencyKey
            ): array {
                $workplace = RhLocalidad::query()->whereKey($workplaceId)->lockForUpdate()->first();
                if ($workplace === null) throw new DomainException('workplace_not_found');
                $timezone = $this->timezoneForDate($workplaceId, $effectiveDate);
                $todayLocal = ($this->clock)()->setTimezone(new DateTimeZone($timezone))->format('Y-m-d');

                $byKey = AttendancePolicyVersion::query()->where('workplace_id', $workplaceId)
                    ->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($byKey !== null) {
                    if ((int) $byKey->actor_portal_user_id !== (int) $actor->id
                        || (string) $byKey->effective_from_local_date !== $effectiveDate
                        || (int) $byKey->retardo_tolerance_minutes !== $toleranceValue
                        || (int) $byKey->incidence_deadline_days !== $deadlineValue) {
                        throw new DomainException('idempotency_conflict');
                    }
                    return ['policy' => $byKey->toPolicyArray(), 'created' => false];
                }

                $sameDate = AttendancePolicyVersion::query()->where('workplace_id', $workplaceId)
                    ->where('effective_from_local_date', $effectiveDate)->lockForUpdate()->first();
                if ($sameDate !== null) {
                    if ((int) $sameDate->retardo_tolerance_minutes !== $toleranceValue
                        || (int) $sameDate->incidence_deadline_days !== $deadlineValue) {
                        throw new DomainException('policy_date_conflict');
                    }
                    return ['policy' => $sameDate->toPolicyArray(), 'created' => false];
                }

                if ($effectiveDate <= $todayLocal) throw new DomainException('policy_must_be_prospective');
                $latest = AttendancePolicyVersion::query()->where('workplace_id', $workplaceId)
                    ->orderByDesc('policy_version')->lockForUpdate()->first();
                if ($latest !== null && $effectiveDate <= (string) $latest->effective_from_local_date) {
                    throw new DomainException('policy_version_date_conflict');
                }
                $version = $latest === null ? 1 : (int) $latest->policy_version + 1;
                if ($version < 1) throw new DomainException('policy_version_overflow');

                $policy = AttendancePolicyVersion::query()->create([
                    'workplace_id' => $workplaceId,
                    'policy_version' => $version,
                    'effective_from_local_date' => $effectiveDate,
                    'timezone_id' => $timezone,
                    'retardo_tolerance_minutes' => $toleranceValue,
                    'incidence_deadline_days' => $deadlineValue,
                    'actor_portal_user_id' => (int) $actor->id,
                    'source' => self::SOURCE,
                    'idempotency_key' => $idempotencyKey,
                ]);
                $this->audit((int) $actor->id, $workplaceId, (int) $policy->id);
                $readback = AttendancePolicyVersion::query()->whereKey((int) $policy->id)
                    ->where('workplace_id', $workplaceId)->lockForUpdate()->first();
                if ($readback === null || (int) $readback->policy_version !== $version
                    || (string) $readback->effective_from_local_date !== $effectiveDate
                    || (string) $readback->timezone_id !== $timezone
                    || (int) $readback->retardo_tolerance_minutes !== $toleranceValue
                    || (int) $readback->incidence_deadline_days !== $deadlineValue
                    || (int) $readback->actor_portal_user_id !== (int) $actor->id
                    || (string) $readback->idempotency_key !== $idempotencyKey) {
                    throw new DomainException('policy_readback_mismatch');
                }
                return ['policy' => $readback->toPolicyArray(), 'created' => true];
            });

            $readback = $this->readForEffectiveDate($workplaceId, $effectiveDate);
            if (!($readback['ok'] ?? false)) return ['ok' => false, 'error' => 'policy_readback_unavailable'];
            $persisted = $readback['data']['policy'] ?? null;
            if (!is_array($persisted)
                || (int) ($persisted['id'] ?? 0) !== (int) ($createdOrExisting['policy']['id'] ?? 0)
                || (int) ($persisted['workplace_id'] ?? 0) !== $workplaceId
                || (int) ($persisted['policy_version'] ?? 0) !== (int) ($createdOrExisting['policy']['policy_version'] ?? -1)
                || (string) ($persisted['effective_from_local_date'] ?? '') !== $effectiveDate
                || (int) ($persisted['retardo_tolerance_minutes'] ?? -1) !== $toleranceValue
                || (int) ($persisted['incidence_deadline_days'] ?? -1) !== $deadlineValue) {
                return ['ok' => false, 'error' => 'policy_readback_mismatch'];
            }
            $current = $this->readCurrent($workplaceId);
            if (!($current['ok'] ?? false)) return ['ok' => false, 'error' => 'policy_readback_unavailable'];
            return ['ok' => true, 'policy' => $createdOrExisting['policy'],
                'next_scheduled_policy' => $current['data']['next_scheduled_policy'] ?? null,
                'current_local_date' => $current['data']['current_local_date'] ?? null,
                'created' => $createdOrExisting['created']];
        } catch (DomainException $exception) {
            return ['ok' => false, 'error' => $exception->getMessage()];
        } catch (Throwable) {
            return ['ok' => false, 'error' => 'attendance_policy_write_failed'];
        }
    }

    private function timezoneForDate(int $workplaceId, string $date): string
    {
        $rows = Capsule::table('op_rh_v2_localidades_zonas_horarias')
            ->where('id_localidad', $workplaceId)
            ->where('valido_desde', '<=', $date)
            ->where(static function ($query) use ($date): void {
                $query->whereNull('valido_hasta')->orWhere('valido_hasta', '>', $date);
            })->orderBy('valido_desde')->get(['timezone_iana']);
        if ($rows->count() !== 1) throw new DomainException('workplace_timezone_unresolved');
        $timezone = (string) $rows[0]->timezone_iana;
        if (!self::isIanaTimezone($timezone)) throw new DomainException('workplace_timezone_unresolved');
        return $timezone;
    }

    private function audit(int $actorId, int $workplaceId, int $policyId): void
    {
        Capsule::table('op_rh_v2_m11_configuration_audit')->insert([
            'actor_portal_user_id' => $actorId,
            'permission_key' => ChecadorV2PermissionService::ATTENDANCE_POLICY_EDIT,
            'workplace_id' => $workplaceId,
            'entity_type' => 'attendance_policy',
            'entity_id' => $policyId,
            'action' => 'CREATE_VERSION',
            'created_at_utc' => ($this->clock)()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ]);
    }

    private static function strictInteger(mixed $value): ?int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) return null;
        if ((string) $value !== (string) (int) $value || (int) $value < 0) return null;
        return (int) $value;
    }

    private static function isCivilDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private static function isIanaTimezone(string $value): bool
    {
        return $value !== '' && $value === trim($value) && in_array($value, DateTimeZone::listIdentifiers(), true);
    }
}
