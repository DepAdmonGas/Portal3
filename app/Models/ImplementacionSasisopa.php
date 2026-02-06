<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImplementacionSasisopa extends Model
{
    protected $table = 'tb_implementacion_sasisopa';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_usuario',
        'fecha_hora',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
        'fecha_hora' => 'datetime',
    ];
}
