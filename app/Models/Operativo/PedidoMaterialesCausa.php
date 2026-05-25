<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class PedidoMaterialesCausa extends Model
{
    protected $table = 'op_pedido_materiales_causa';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_reporte',
        'fecha',
        'hora',
        'descripcion',
        'localidad_refaccion',
        'factura_pdf',
        'factura_xml',
        'precio'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_reporte' => 'integer',
        'fecha' => 'date',
        'hora' => 'datetime:H:i:s',
        'precio' => 'double'
    ];

}

