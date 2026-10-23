<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class MaquinariaMantenimientoToken extends Model
{
    protected $table = 'op_maquinaria_mantenimiento_token';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_mantenimiento',
        'id_ocurrencia',
        'id_usuario',
        'token',
        'fecha_creacion',
        'expirado',
        'canal',
    ];

    protected $casts = [
        'id'               => 'integer',
        'id_mantenimiento' => 'integer',
        'id_ocurrencia'    => 'integer',
        'id_usuario'       => 'integer',
        'token'            => 'integer',
        'expirado'         => 'integer',
        'canal'            => 'integer',
    ];
}