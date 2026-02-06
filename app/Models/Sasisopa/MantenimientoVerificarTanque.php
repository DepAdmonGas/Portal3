<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MantenimientoVerificarTanque extends Model
{
    protected $table = 'po_mantenimiento_verificar_tanque';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_verificar',
        'detalle',
        'resultado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_verificar' => 'integer',
        'detalle' => 'string',
        'resultado' => 'string',
    ];

}
