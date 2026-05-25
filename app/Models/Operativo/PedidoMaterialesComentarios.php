<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class PedidoMaterialesComentarios extends Model
{
    protected $table = 'op_pedido_materiales_comentarios';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false; // La tabla no usa created_at / updated_at

    protected $fillable = [
        'id_pedido',
        'fecha_hora',
        'id_usuario',
        'comentario'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_pedido' => 'integer',
        'id_usuario' => 'integer',
        'fecha_hora' => 'datetime',
    ];

}

