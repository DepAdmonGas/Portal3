<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RefaccionesReporte extends Model
{
    protected $table = 'op_refacciones_reporte';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_usuario',
        'fecha',
        'hora',
        'dispensario',
        'motivo',
        'archivo',
        'status'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
        'status' => 'integer',
        'fecha' => 'date',
        'hora' => 'time'
    ];


}

