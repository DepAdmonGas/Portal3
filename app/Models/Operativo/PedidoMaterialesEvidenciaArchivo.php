<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidoMaterialesEvidenciaArchivo extends Model
{
    protected $table = 'op_pedido_materiales_evidencia_archivo';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_pedido',
        'area',
        'motivo',
        'archivo'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_pedido' => 'integer'
    ];

}
