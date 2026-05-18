<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class MantenimientoLista extends Model
{
    protected $table = 'po_mantenimiento_lista';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'num_lista',
        'detalle',
        'periodicidad',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'num_lista' => 'integer',
        'estado' => 'integer',
    ];

}
