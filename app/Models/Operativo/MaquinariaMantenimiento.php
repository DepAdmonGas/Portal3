<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class MaquinariaMantenimiento extends Model
{
    protected $table = 'op_maquinaria_mantenimiento';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_equipo',
        'tipo_mantenimiento',
        'frecuencia',
        'fecha',
        'fecha_fin',
        'orden',
        'estatus',
        'falla_descripcion',
        'observaciones',
        'costo_mantenimiento',
        'creado_por',
        'fecha_creacion',
        'fecha_finalizacion',
    ];

    protected $casts = [
        'id'                 => 'integer',
        'id_equipo'          => 'integer',
        'tipo_mantenimiento' => 'integer',
        'frecuencia'         => 'integer',
        'orden'              => 'integer',
        'estatus'            => 'integer',
        'costo_mantenimiento'=> 'float',
        'creado_por'         => 'integer',
    ];

    public function ocurrencias()
    {
        return $this->hasMany(MaquinariaMantenimientoOcurrencia::class, 'id_mantenimiento', 'id');
    }
}