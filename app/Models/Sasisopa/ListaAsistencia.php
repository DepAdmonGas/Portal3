<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class ListaAsistencia extends Model
{
    protected $table = 'tb_lista_asistencia';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_usuario',
        'punto_sasisopa',
        'fecha',
        'hora',
        'lugar',
        'tema',
        'finalidad',
        'encargado',
        'realizadopor',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
        'punto_sasisopa' => 'integer',
        'realizadopor' => 'integer',
        'estado' => 'integer',
        'fecha' => 'date',
        'hora' => 'datetime:H:i:s',
    ];
}
