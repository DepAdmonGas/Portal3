<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Soporte extends Model
{
    protected $table = 'ds_soporte';

    // Llave primaria personalizada
    protected $primaryKey = 'id_ticket';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // La tabla no incluye created_at ni updated_at

    protected $fillable = [
        'id_personal',
        'descripcion',
        'prioridad',
        'fecha_creacion',
        'fecha_inicio',
        'fecha_termino',
        'tiempo_solucion',
        'fecha_termino_real',
        'porcentaje',
        'categoria',
        'id_personal_soporte',
        'estado',
    ];

    protected $casts = [
        'id_personal' => 'integer',
        'id_personal_soporte' => 'integer',
        'tiempo_solucion' => 'integer',
        'porcentaje' => 'integer',
        'estado' => 'integer',

        'fecha_creacion' => 'datetime',
        'fecha_inicio' => 'datetime',
        'fecha_termino' => 'datetime',
        'fecha_termino_real' => 'datetime',
    ];
}
