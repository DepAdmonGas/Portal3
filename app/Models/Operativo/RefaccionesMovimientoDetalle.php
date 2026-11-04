<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RefaccionesMovimientoDetalle extends Model
{
    protected $table = 'op_refacciones_movimientos_detalle';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_movimiento', 'id_refaccion', 'cantidad', 'costo_unitario',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_movimiento' => 'integer',
        'id_refaccion' => 'integer',
        'cantidad' => 'float',
        'costo_unitario' => 'float',
    ];
}
