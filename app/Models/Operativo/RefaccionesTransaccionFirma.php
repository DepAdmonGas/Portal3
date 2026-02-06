<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefaccionesTransaccionFirma extends Model
{
    protected $table = 'op_refacciones_transaccion_firma';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_reporte',
        'id_usuario',
        'tipo_firma',
        'fecha',
        'firma'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_reporte' => 'integer',
        'id_usuario' => 'integer',
        'fecha' => 'datetime'
    ];
}
