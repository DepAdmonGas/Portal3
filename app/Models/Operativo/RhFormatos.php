<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RhFormatos extends Model
{
    protected $table = 'op_rh_formatos';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_localidad',
        'id_usuario',
        'formato',
        'fecha',
        'status'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_localidad' => 'integer',
        'id_usuario' => 'integer',
        'formato' => 'integer',
        'status' => 'integer',
        'fecha' => 'datetime'
    ];
}

