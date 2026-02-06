<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Refacciones extends Model
{
    protected $table = 'op_refacciones';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'descripcion_f',
        'nombre',
        'imagen',
        'unidad',
        'estado_r',
        'costo',
        'modelo',
        'marca',
        'proveedor',
        'contacto',
        'area',
        'archivo',
        'status'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'unidad' => 'integer',
        'costo' => 'double',
        'area' => 'integer',
        'status' => 'integer'
    ];

}
