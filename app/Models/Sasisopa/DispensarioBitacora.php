<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class DispensarioBitacora extends Model
{
    protected $table = 'tb_dispensarios_bitacora';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'folio',
        'id_dispensario',
        'fecha',
        'hora_inicio',
        'hora_termino',
        'lado',
        'producto',
        'motivo',
        'responsable',
        'observaciones',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'folio' => 'integer',
        'id_dispensario' => 'integer',
        'fecha' => 'date',
        'hora_inicio' => 'string',
        'hora_termino' => 'string',
        'lado' => 'string',
        'producto' => 'string',
        'motivo' => 'string',
        'responsable' => 'integer',
        'observaciones' => 'string',
        'estado' => 'integer',
    ];
}
