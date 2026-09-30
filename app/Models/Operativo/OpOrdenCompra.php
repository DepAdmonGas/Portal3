<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;

class OpOrdenCompra extends Model
{
    protected $table = 'op_orden_compra';
    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'fecha',
        'year',
        'mes',
        'porcentaje_total',
        'cargo',
        'no_control',
        'iva',
        'estatus'
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id');
    }

    public function estacionRelacion()
    {
        return $this->hasOne(OpOrdenCompraRazonSocial::class, 'id_ordencompra', 'id');
    }

    public function proveedores()
    {
        return $this->hasMany(OpOrdenCompraProveedor::class, 'id_ordencompra', 'id');
    }

    public function articulos()
    {
        return $this->hasMany(OpOrdenCompraArticulo::class, 'id_ordencompra', 'id');
    }

    public function refacturaciones()
    {
        return $this->hasMany(OpOrdenCompraRefacturacion::class, 'id_ordencompra', 'id');
    }

    public function firmas()
    {
        return $this->hasMany(OpOrdenCompraFirma::class, 'id_ordencompra', 'id')->orderBy('id', 'asc');
    }
}