<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class PivoteoToken extends Model
{
    protected $table = 'op_pivoteo_token';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_pivoteo',
        'id_usuario',
        'fecha_creacion',
        'token'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_pivoteo' => 'integer',
        'id_usuario' => 'integer',
        'fecha_creacion' => 'datetime',
        'token' => 'integer'
    ];
}

