<?php

declare(strict_types=1);

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Append-only, bitemporal worker-to-canonical-Workplace membership version. */
class RhPersonalLocalidadVersion extends Model
{
    protected $table = 'op_rh_personal_localidad_version';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'worker_id',
        'workplace_id',
        'valid_from_utc',
        'valid_to_utc',
        'recorded_at_utc',
        'recorded_by',
        'reason',
        'supersedes_id',
    ];

    protected $casts = [
        'id' => 'integer',
        'worker_id' => 'integer',
        'workplace_id' => 'integer',
        'recorded_by' => 'integer',
        'supersedes_id' => 'integer',
    ];

    protected function performUpdate(Builder $query)
    {
        throw new LogicException('Worker-Workplace assignment history is append-only; corrections require a superseding row.');
    }

    public function delete()
    {
        if ($this->exists) {
            throw new LogicException('Worker-Workplace assignment history is append-only and cannot be deleted.');
        }

        return null;
    }
}
