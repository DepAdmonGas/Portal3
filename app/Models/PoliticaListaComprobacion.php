<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PoliticaListaComprobacion extends Model
{
    protected $table = 'tb_politica_lista_comprobacion';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_usuario',
        'fecha',
        'asistentes',
        'comentarios',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
        'fecha' => 'date',
    ];
}
