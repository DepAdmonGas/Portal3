<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuariosDocumentos extends Model
{
    protected $table = 'tb_usuarios_documentos';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'nombre',
        'archivo'
    ];

    protected $casts = [
        'id' => 'int',
        'id_usuario' => 'int'
    ];
}
