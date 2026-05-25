<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class ProcedimientosModulos extends Model
{
    protected $table = 'op_procedimientos_modulos';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'fecha',
        'modulo',
        'titulo',
        'archivo'
    ];

    protected $casts = [
        'id' => 'integer',
        'fecha' => 'date'
    ];
}

