<?php

declare(strict_types=1);

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Append-only M11 attendance-policy version stored in the authorized V2 table. */
final class AttendancePolicyVersion extends Model
{
    protected $table = 'op_rh_v2_attendance_policy_versions';
    public $timestamps = false;

    protected $fillable = [
        'workplace_id', 'policy_version', 'effective_from_local_date', 'timezone_id',
        'retardo_tolerance_minutes', 'incidence_deadline_days', 'early_entry_allowed_minutes',
        'duplicate_punch_window_minutes', 'exit_priority_window_minutes', 'actor_portal_user_id',
        'source', 'idempotency_key',
    ];

    protected $casts = [
        'id' => 'integer', 'workplace_id' => 'integer', 'policy_version' => 'integer',
        'retardo_tolerance_minutes' => 'integer', 'incidence_deadline_days' => 'integer',
        'early_entry_allowed_minutes' => 'integer', 'duplicate_punch_window_minutes' => 'integer',
        'exit_priority_window_minutes' => 'integer',
        'actor_portal_user_id' => 'integer',
    ];

    protected function performUpdate(Builder $query)
    {
        throw new LogicException('Attendance policy history is append-only; corrections require a new prospective version.');
    }

    protected function performDeleteOnModel()
    {
        throw new LogicException('Attendance policy history is append-only and cannot be deleted.');
    }

    /** @return array<string,mixed> */
    public function toPolicyArray(): array
    {
        return [
            'id' => (int) $this->id,
            'workplace_id' => (int) $this->workplace_id,
            'policy_version' => (int) $this->policy_version,
            'effective_from_local_date' => (string) $this->effective_from_local_date,
            'timezone_id' => (string) $this->timezone_id,
            'retardo_tolerance_minutes' => (int) $this->retardo_tolerance_minutes,
            'incidence_deadline_days' => (int) $this->incidence_deadline_days,
            'early_entry_allowed_minutes' => $this->early_entry_allowed_minutes,
            'duplicate_punch_window_minutes' => $this->duplicate_punch_window_minutes,
            'exit_priority_window_minutes' => $this->exit_priority_window_minutes,
            'actor_portal_user_id' => (int) $this->actor_portal_user_id,
            'source' => (string) $this->source,
            'created_at_utc' => (string) $this->created_at_utc,
        ];
    }
}
