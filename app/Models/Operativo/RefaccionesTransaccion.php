<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefaccionesTransaccion extends Model
{
    protected $table = 'op_refacciones_transaccion';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'fecha',
        'id_refaccion',
        'id_estacion_receptora',
        'id_refaccion_receptora',
        'piezas',
        'observaciones',
        'estado'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_refaccion' => 'integer',
        'id_estacion_receptora' => 'integer',
        'id_refaccion_receptora' => 'integer',
        'piezas' => 'integer',
        'estado' => 'integer',
        'fecha' => 'datetime'
    ];
}
