<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RhComodinesDia extends Model
{
    protected $table = 'op_rh_comodines_dia';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_reporte',
        'id_usuario',
        'id_estacion',
        'dia',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_reporte' => 'integer',
        'id_usuario' => 'integer',
        'id_estacion' => 'integer',
    ];
}
