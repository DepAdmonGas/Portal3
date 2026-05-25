<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class PedidoMaterialesAreaOtros extends Model
{
    protected $table = 'op_pedido_materiales_area_otros';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_area',
        'categoria',
        'sub_area',
        'estatus'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_area' => 'integer',
        'categoria' => 'integer',
        'estatus' => 'integer'
    ];

}

