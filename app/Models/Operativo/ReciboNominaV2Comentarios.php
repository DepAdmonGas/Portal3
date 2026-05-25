<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class ReciboNominaV2Comentarios extends Model
{
    protected $table = 'op_recibo_nomina_v2_comentarios';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_nomina',
        'fecha_hora',
        'id_usuario',
        'comentario'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_nomina' => 'integer',
        'id_usuario' => 'integer'
    ];

}

