<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;
use App\Models\Estacion;

class OpOrdenCompraRefacturacion extends Model
{
    protected $table = 'op_orden_compra_refacturacion';
    public $timestamps = false;

    protected $fillable = [
        'id_ordencompra',
        'id_estacion',
        'descripcion',
        'cantidad',
        'importe',
        'porcentaje',
        'cantidadES',
        'cantidadAl'
    ];

    public function estacion()
    {
        return $this->belongsTo(Estacion::class, 'id_estacion', 'id');
    }
}