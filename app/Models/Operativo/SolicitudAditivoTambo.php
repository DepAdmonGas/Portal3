<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudAditivoTambo extends Model
{
    protected $table = 'op_solicitud_aditivo_tambo';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_reporte',
        'cantidad',
        'producto',
        'aditivo',
        'kilogramo'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_reporte' => 'integer',
        'cantidad' => 'integer',
        'kilogramo' => 'integer'
    ];
}
