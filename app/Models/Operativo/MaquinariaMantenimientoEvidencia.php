<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class MaquinariaMantenimientoEvidencia extends Model
{
    protected $table = 'op_maquinaria_mantenimiento_evidencia';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_mantenimiento',
        'id_ocurrencia',
        'archivo',
        'nombre_original',
        'extension',
        'tipo',
        'subido_por',
        'fecha_subida',
        'detalle',
    ];

    protected $casts = [
        'id'               => 'integer',
        'id_mantenimiento' => 'integer',
        'id_ocurrencia'    => 'integer',
        'tipo'             => 'integer',
        'subido_por'       => 'integer',
    ];
}