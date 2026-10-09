<?php

declare(strict_types=1);

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Local civil-time segment; end_day_offset is explicit and not timezone-derived. */
class PersonalScheduleSegment extends Model
{
    protected $table = 'op_rh_personal_horario_version_segmento';
    public $timestamps = false;
    protected $fillable = [
        'day_definition_id',
        'sequence_number',
        'start_local_time',
        'end_local_time',
        'end_day_offset',
    ];
    protected $casts = [
        'id' => 'integer',
        'day_definition_id' => 'integer',
        'sequence_number' => 'integer',
        'end_day_offset' => 'integer',
    ];

    public function dayDefinition(): BelongsTo
    {
        return $this->belongsTo(PersonalScheduleDayDefinition::class, 'day_definition_id');
    }

    protected function performUpdate(Builder $query)
    {
        throw new LogicException('Personal schedule segments are append-only.');
    }

    protected function performDeleteOnModel()
    {
        throw new LogicException('Personal schedule segments are append-only and cannot be deleted.');
    }
}
