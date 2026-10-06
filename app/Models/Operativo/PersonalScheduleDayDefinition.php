<?php

declare(strict_types=1);

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/** One explicit ISO weekday fact in a complete schedule version. */
class PersonalScheduleDayDefinition extends Model
{
    protected $table = 'op_rh_personal_horario_version_dia';
    public $timestamps = false;
    protected $fillable = ['schedule_version_id', 'weekday_iso', 'day_state'];
    protected $casts = [
        'id' => 'integer',
        'schedule_version_id' => 'integer',
        'weekday_iso' => 'integer',
    ];

    public function scheduleVersion(): BelongsTo
    {
        return $this->belongsTo(PersonalScheduleVersion::class, 'schedule_version_id');
    }

    public function segments(): HasMany
    {
        return $this->hasMany(PersonalScheduleSegment::class, 'day_definition_id')->orderBy('sequence_number');
    }

    protected function performUpdate(Builder $query)
    {
        throw new LogicException('Personal schedule day facts are append-only.');
    }

    protected function performDeleteOnModel()
    {
        throw new LogicException('Personal schedule day facts are append-only and cannot be deleted.');
    }
}
