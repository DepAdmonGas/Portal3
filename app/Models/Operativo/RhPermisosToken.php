<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RhPermisosToken extends Model
{
    protected $table = 'op_rh_permisos_token';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_permiso',
        'id_usuario',
        'token'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_permiso' => 'integer',
        'id_usuario' => 'integer',
        'token' => 'integer',
        'fecha_creacion' => 'datetime'
    ];
}
