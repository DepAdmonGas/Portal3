<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RhPermisosFirma extends Model
{
    protected $table = 'op_rh_permisos_firma';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_permiso',
        'id_usuario',
        'tipo_firma',
        'firma'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_permiso' => 'integer',
        'id_usuario' => 'integer',
        'fecha' => 'datetime'
    ];
}
