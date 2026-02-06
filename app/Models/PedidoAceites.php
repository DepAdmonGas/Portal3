<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidoAceites extends Model
{
    protected $table = 'tb_pedido_aceites';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'no_reporte',
        'id_estacion',
        'id_aceite',
        'caja_pedido',
        'caja_encargado',
        'estatus',
    ];

    protected $casts = [
        'id' => 'integer',
        'no_reporte' => 'integer',
        'id_estacion' => 'integer',
        'id_aceite' => 'integer',
        'caja_pedido' => 'integer',
        'caja_encargado' => 'integer',
        'estatus' => 'integer',
    ];
}
