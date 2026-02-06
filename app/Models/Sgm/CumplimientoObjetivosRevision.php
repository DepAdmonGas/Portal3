<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CumplimientoObjetivosRevision extends Model
{
    protected $table = 'sgm_cumplimiento_objetivos_revision';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'year',
        'fecha',
        'hora',
        'lugar',
        'responsable',
        'realizadopor',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'year' => 'integer',
        'fecha' => 'date',
        'hora' => 'string',
        'lugar' => 'string',
        'responsable' => 'string',
        'realizadopor' => 'integer',
        'estado' => 'integer',
    ];
}
