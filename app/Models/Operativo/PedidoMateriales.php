<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class PedidoMateriales extends Model
{
    protected $table = 'op_pedido_materiales';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'folio',
        'id_estacion',
        'fecha',
        'afectacion',
        'tipo_servicio',
        'orden_trabajo',
        'orden_riesgo',
        'comentarios',
        'estatus'
    ];

    protected $casts = [
        'id' => 'integer',
        'folio' => 'integer',
        'id_estacion' => 'integer',
        'fecha' => 'date',
        'tipo_servicio' => 'integer',
        'orden_trabajo' => 'integer',
        'orden_riesgo' => 'integer',
        'estatus' => 'integer'
    ];
}

