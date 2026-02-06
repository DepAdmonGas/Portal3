<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispensarioAperturaBitacora extends Model
{
    protected $table = 'tb_dispensarios_apertura_bitacora';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_dispensario',
        'fecha',
        'hora_inicio',
        'hora_termino',
        'lado',
        'producto',
        'clave',
        'motivo',
        'responsable',
        'detalle',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_dispensario' => 'integer',
        'fecha' => 'date',
        'hora_inicio' => 'string',
        'hora_termino' => 'string',
        'lado' => 'string',
        'producto' => 'string',
        'clave' => 'string',
        'motivo' => 'string',
        'responsable' => 'integer',
        'detalle' => 'string',
    ];
}
