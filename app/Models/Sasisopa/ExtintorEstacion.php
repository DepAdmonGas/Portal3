<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtintorEstacion extends Model
{
    protected $table = 'po_extintores_estacion';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'no_extintor',
        'ubicacion',
        'ultima_recarga',
        'tipo_extintor',
        'peso_kg',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'no_extintor' => 'integer',
        'ultima_recarga' => 'date',
        'estado' => 'integer',
    ];
}
