<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditoriaInterna extends Model
{
    protected $table = 'tb_auditoria_interna';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_usuario',
        'fechacreacion',
        'auditor',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
        'fechacreacion' => 'datetime',
        'auditor' => 'string',
    ];
}
