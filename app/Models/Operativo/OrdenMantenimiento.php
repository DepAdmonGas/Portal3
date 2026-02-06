<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdenMantenimiento extends Model
{
    protected $table = 'op_orden_mantenimiento';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_usuario',
        'fecha',
        'folio',
        'codigo',
        'no_control',
        'tipo_mantenimiento',
        'tipo_trabajo',
        'marco_normativo',
        'entrada_vigor',
        'estatus_tramite',
        'descripcion',
        'seguimiento',
        'trabajo_terminado',
        'contrato_vigente',
        'garantia_trabajo',
        'obervaciones',
        'estatus'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
        'fecha' => 'datetime',
        'folio' => 'integer',
        'codigo' => 'string',
        'no_control' => 'string',
        'tipo_mantenimiento' => 'integer',
        'tipo_trabajo' => 'integer',
        'marco_normativo' => 'string',
        'entrada_vigor' => 'string',
        'estatus_tramite' => 'string',
        'descripcion' => 'string',
        'seguimiento' => 'integer',
        'trabajo_terminado' => 'integer',
        'contrato_vigente' => 'integer',
        'garantia_trabajo' => 'integer',
        'obervaciones' => 'string',
        'estatus' => 'integer'
    ];
}
