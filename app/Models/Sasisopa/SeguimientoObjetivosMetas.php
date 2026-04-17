<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class SeguimientoObjetivosMetas extends Model
{
    protected $table = 'tb_seguimiento_objetivos_metas';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_usuario',
        'fecha'
    ];

    protected $casts = [
        'id' => 'int',
        'id_estacion' => 'int',
        'id_usuario' => 'int',
        'fecha' => 'datetime'
    ];

    
}
