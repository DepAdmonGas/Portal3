<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class OrdenCompraRazonSocial extends Model
{
    protected $table = 'op_orden_compra_razon_social';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_ordencompra',
        'id_estacion'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_ordencompra' => 'integer',
        'id_estacion' => 'integer'
    ];

}

