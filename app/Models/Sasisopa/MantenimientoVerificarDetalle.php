<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MantenimientoVerificarDetalle extends Model
{
    protected $table = 'po_mantenimiento_verificar_detalle';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_verificar',
        'id_detalle',
        'resultado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_verificar' => 'integer',
        'id_detalle' => 'integer',
        'resultado' => 'string',
    ];


}
