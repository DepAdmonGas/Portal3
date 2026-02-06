<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CumplimientoObjetivosRevisionDetalle extends Model
{
    protected $table = 'sgm_cumplimiento_objetivos_revision_detalle';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_cumplimiento',
        'categoria',
        'resultado1',
        'resultado2',
        'resultado3',
        'resultado4',
        'resultado5',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_cumplimiento' => 'integer',
        'categoria' => 'string',
        'resultado1' => 'string',
        'resultado2' => 'string',
        'resultado3' => 'string',
        'resultado4' => 'string',
        'resultado5' => 'string',
    ];
}
