<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RhFormatosToken extends Model
{
    protected $table = 'op_rh_formatos_token';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_formato',
        'id_usuario',
        'fecha_creacion',
        'token'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_formato' => 'integer',
        'id_usuario' => 'integer',
        'token' => 'integer',
        'fecha_creacion' => 'datetime'
    ];

}

