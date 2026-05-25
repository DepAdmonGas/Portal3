<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class BitacoraRHRegistro extends Model
{
    protected $table = 'op_bitacora_rrhh_registro';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // No existen created_at ni updated_at

    protected $fillable = [
        'id_bitacora',
        'id_usuario',
        'fecha_hora'
    ];

    protected $casts = [
        'id_bitacora' => 'integer',
        'id_usuario' => 'integer',
        'fecha_hora' => 'datetime'
    ];

}

