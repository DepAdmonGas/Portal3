<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RhListaNegraComentario extends Model
{
    protected $table = 'op_rh_lista_negra_comentarios';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_lista_negra',
        'id_usuario',
        'comentario',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_lista_negra' => 'integer',
        'id_usuario' => 'integer',
    ];
}