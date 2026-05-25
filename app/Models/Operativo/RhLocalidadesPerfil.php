<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RhLocalidadesPerfil extends Model
{
    protected $table = 'op_rh_localidades_perfil';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'usuario',
        'password',
        'token',
        'status'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'status' => 'integer'
    ];
}

