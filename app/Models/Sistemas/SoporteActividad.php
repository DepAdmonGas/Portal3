<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SoporteActividad extends Model
{
    protected $table = 'ds_soporte_actividades';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // No tiene created_at ni updated_at

    protected $fillable = [
        'id_ticket',
        'descripcion',
        'archivo',
        'fecha_inicio',
        'fecha_termino',
        'estado',
    ];

    protected $casts = [
        'id_ticket' => 'integer',
        'estado' => 'integer',
        'fecha_inicio' => 'date',
        'fecha_termino' => 'date',
    ];

}
