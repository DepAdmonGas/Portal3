<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class MaquinariaPlantillaActividad extends Model
{
    protected $table = 'op_maquinaria_plantilla_actividad';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'frecuencia',
        'orden',
        'descripcion',
        'horas_umbral',
        'activa',
        'fecha_creacion',
    ];

    protected $casts = [
        'id'          => 'integer',
        'frecuencia'  => 'integer',
        'orden'       => 'integer',
        'horas_umbral'=> 'integer',
        'activa'      => 'integer',
    ];
}