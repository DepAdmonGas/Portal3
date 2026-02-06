<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditoriaExterna extends Model
{
    protected $table = 'tb_auditoria_externa';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_usuario',
        'fechacreacion',
        'prestador_servicio',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
        'fechacreacion' => 'datetime',
        'prestador_servicio' => 'string',
    ];
}
