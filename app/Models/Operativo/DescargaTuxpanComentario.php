<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class DescargaTuxpanComentario extends Model
{
    protected $table = 'op_descarga_tuxpan_comentario';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_descarga',
        'id_usuario',
        'comentario',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_descarga' => 'integer',
        'id_usuario' => 'integer',
        'comentario' => 'string',
    ];
}
