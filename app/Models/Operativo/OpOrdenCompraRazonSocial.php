<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class OpOrdenCompraRazonSocial extends Model
{
    protected $table = 'op_orden_compra_razon_social';
    public $timestamps = false;

    protected $fillable = [
        'id_ordencompra',
        'id_estacion'
    ];

    public function localidad()
    {
        return $this->belongsTo(RhLocalidad::class, 'id_estacion', 'id');
    }
}