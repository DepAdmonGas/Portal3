<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipoCritico extends Model
{
    protected $table = 'tb_equipo_critico';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'fechacreacion',
        'id_equipo',
        'nombre_equipo',
        'marca_modelo',
        'funciones',
        'fecha_instalacion',
        'tiempo_vida',
        'manual',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_equipo' => 'integer',
        'tiempo_vida' => 'integer',
        'estado' => 'integer',
        'fechacreacion' => 'datetime',
        'fecha_instalacion' => 'date',
    ];
}
