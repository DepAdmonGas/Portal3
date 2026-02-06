<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdenMantenimientoTrabajo extends Model
{
    protected $table = 'op_orden_mantenimiento_trabajo';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_mantenimiento',
        'trabajo',
        'estatus',
        'detalle'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_mantenimiento' => 'integer',
        'trabajo' => 'string',
        'estatus' => 'integer',
        'detalle' => 'string'
    ];
}
