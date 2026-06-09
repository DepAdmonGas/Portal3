<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class DispensarioApertura extends Model
{
    protected $table = 'tb_dispensarios_apertura';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'dispensario',
        'clave',
        'motivo',
        'producto',
        'lado',
        'fecha',
        'hora',
        'detalle',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'dispensario' => 'integer',
        'clave' => 'string',
        'motivo' => 'string',
        'producto' => 'string',
        'lado' => 'integer',
        'fecha' => 'date',
        'hora' => 'string',
        'detalle' => 'float',
    ];
}
