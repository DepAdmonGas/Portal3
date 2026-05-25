<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class BitacoraRH extends Model
{
    protected $table = 'op_bitacora_rrhh';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'id_estacion',
        'fecha_hora',
        'descripcion',
        'year',
        'mes',
        'estatus'
    ];

    protected $casts = [
        'id_usuario' => 'integer',
        'id_estacion' => 'integer',
        'fecha_hora' => 'datetime',
        'year' => 'integer',
        'mes' => 'integer',
        'estatus' => 'integer'
    ];
}

