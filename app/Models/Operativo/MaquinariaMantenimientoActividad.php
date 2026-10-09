<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class MaquinariaMantenimientoActividad extends Model
{
    protected $table = 'op_maquinaria_mantenimiento_actividad';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_mantenimiento',
        'id_ocurrencia',
        'id_plantilla',
        'descripcion',
        'resultado',
        'observacion',
        'revision_tipo',
        'bloque_horas',
        'revisado_por',
        'cambiado_por_texto',
        'fecha',
    ];

    protected $casts = [
        'id'               => 'integer',
        'id_mantenimiento' => 'integer',
        'id_ocurrencia'    => 'integer',
        'id_plantilla'     => 'integer',
        'resultado'        => 'integer',
        'revisado_por'     => 'integer',
    ];
}