<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class Pivoteo extends Model
{
    protected $table = 'op_pivoteo';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'nocontrol',
        'fecha',
        'sucursal',
        'causa',
        'estatus'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'nocontrol' => 'integer',
        'fecha' => 'date',
        'estatus' => 'integer'
    ];
}

