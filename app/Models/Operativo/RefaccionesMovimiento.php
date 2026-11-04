<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RefaccionesMovimiento extends Model
{
    protected $table = 'op_refacciones_movimientos';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'folio', 'tipo', 'fecha', 'id_estacion_origen', 'id_estacion_destino',
        'id_usuario', 'proveedor', 'referencia', 'motivo', 'notas', 'estado', 'creado_en',
    ];

    protected $casts = [
        'id' => 'integer',
        'fecha' => 'datetime',
        'id_estacion_origen' => 'integer',
        'id_estacion_destino' => 'integer',
        'id_usuario' => 'integer',
    ];
}
