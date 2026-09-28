<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class SolicitudAditivoDocumento extends Model
{
    protected $table = 'op_solicitud_aditivo_documento';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_reporte',
        'fecha',
        'nombre',
        'documento'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_reporte' => 'integer',
        'fecha' => 'datetime'
    ];
}