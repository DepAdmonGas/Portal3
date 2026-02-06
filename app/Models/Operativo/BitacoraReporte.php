<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BitacoraReporte extends Model
{
    protected $table = 'op_bitacora_reporte';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_usuario',
        'fecha',
        'hora',
        'documento'
    ];

    protected $casts = [
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
        'fecha' => 'date',
        'hora' => 'string',
    ];

}
