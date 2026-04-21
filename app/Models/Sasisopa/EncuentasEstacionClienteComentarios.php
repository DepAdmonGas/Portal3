<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class EncuentasEstacionClienteComentarios extends Model
{
    protected $table = 'tb_encuentas_estacion_cliente_comentarios';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_cliente',
        'comentario',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_cliente' => 'integer',
    ];
}
