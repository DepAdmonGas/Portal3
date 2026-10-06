<?php

declare(strict_types=1);

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/** Immutable recorded version of a worker's weekly schedule at one Workplace. */
class PersonalScheduleVersion extends Model
{
    protected $table = 'op_rh_personal_horario_version';
    public $timestamps = false;
    protected $fillable = [
        'worker_id',
        'workplace_id',
        'valid_from_local_date',
        'valid_to_local_date',
        'recorded_at_utc',
        'recorded_by',
        'record_source',
        'definition_version',
        'reason',
        'supersedes_id',
    ];
    protected $casts = [
        'id' => 'integer',
        'worker_id' => 'integer',
        'workplace_id' => 'integer',
        'recorded_by' => 'integer',
        'definition_version' => 'integer',
        'supersedes_id' => 'integer',
    ];

    public function days(): HasMany
    {
        return $this->hasMany(PersonalScheduleDayDefinition::class, 'schedule_version_id')->orderBy('weekday_iso');
    }

    protected function performUpdate(Builder $query)
    {
        throw new LogicException('Personal schedule history is append-only; corrections require a superseding version.');
    }

    protected function performDeleteOnModel()
    {
        throw new LogicException('Personal schedule history is append-only and cannot be deleted.');
    }
}
