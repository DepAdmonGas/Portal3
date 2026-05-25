<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class SolicitudAditivo extends Model
{
    protected $table = 'op_solicitud_aditivo';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'orden_compra',
        'fecha',
        'id_personal',
        'para',
        'fecha_entrega',
        'comentarios',
        'status'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'orden_compra' => 'integer',
        'id_personal' => 'integer',
        'status' => 'integer',
        'fecha' => 'date',
        'fecha_entrega' => 'date'
    ];
}

