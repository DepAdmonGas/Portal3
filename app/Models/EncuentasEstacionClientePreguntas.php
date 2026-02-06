<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EncuentasEstacionClientePreguntas extends Model
{
    protected $table = 'tb_encuentas_estacion_cliente_preguntas';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_cliente',
        'id_pregunta',
        'resultado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_cliente' => 'integer',
        'id_pregunta' => 'integer',
        'resultado' => 'integer',
    ];
}
