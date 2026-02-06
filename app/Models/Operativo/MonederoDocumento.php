<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonederoDocumento extends Model
{
    protected $table = 'op_monedero_documento';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_mes',
        'fecha',
        'monedero',
        'diferencia',
        'pdf',
        'xml',
        'excel',
        'sodi',
        'fecha_evaluacion',
        'puntaje',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_mes' => 'integer',
        'fecha' => 'date',
        'monedero' => 'string',
        'diferencia' => 'double',
        'pdf' => 'string',
        'xml' => 'string',
        'excel' => 'string',
        'sodi' => 'string',
        'fecha_evaluacion' => 'date',
        'puntaje' => 'integer',
    ];
}
