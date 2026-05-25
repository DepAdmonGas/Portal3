<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class PivoteoCorreo extends Model
{
    protected $table = 'op_pivoteo_correo';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_pivoteo',
        'correo',
        'fecha_creacion'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_pivoteo' => 'integer',
        'fecha_creacion' => 'datetime'
    ];

 
}

