<?php

declare(strict_types=1);

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/** Append-only effective and recorded-time version for a canonical Workplace. */
class RhLocalidadTimezoneVersion extends Model
{
    protected $table = 'op_rh_localidad_timezone_version';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'workplace_id',
        'timezone_iana',
        'valid_from_utc',
        'valid_to_utc',
        'recorded_at_utc',
        'recorded_by',
        'reason',
        'supersedes_id',
    ];

    protected $casts = [
        'id' => 'integer',
        'workplace_id' => 'integer',
        'recorded_by' => 'integer',
        'supersedes_id' => 'integer',
    ];

    protected function performUpdate(Builder $query)
    {
        throw new LogicException('Workplace timezone history is append-only; corrections require a superseding row.');
    }

    public function delete()
    {
        if ($this->exists) {
            throw new LogicException('Workplace timezone history is append-only and cannot be deleted.');
        }

        return null;
    }
}
