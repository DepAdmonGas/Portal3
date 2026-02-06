<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdenMantenimientoArea extends Model
{
    protected $table = 'op_orden_mantenimiento_area';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_mantenimiento',
        'area',
        'estatus'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_mantenimiento' => 'integer',
        'area' => 'string',
        'estatus' => 'integer'
    ];

}
