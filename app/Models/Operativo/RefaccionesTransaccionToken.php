<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefaccionesTransaccionToken extends Model
{
    protected $table = 'op_refacciones_transaccion_token';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_reporte',
        'id_usuario',
        'fecha_creacion',
        'token'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_reporte' => 'integer',
        'id_usuario' => 'integer',
        'token' => 'integer',
        'fecha_creacion' => 'datetime'
    ];

}
