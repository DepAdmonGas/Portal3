<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MantenimientoDetalle extends Model
{
    protected $table = 'po_mantenimiento_detalle';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_lista',
        'id_sublista',
        'detalle',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_lista' => 'integer',
        'id_sublista' => 'integer',
    ];

}
