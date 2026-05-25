<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class SenalamientosDispensarios extends Model
{
    protected $table = 'op_senalamientos_dispensarios';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'dispensario',
        'imagen'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer'
    ];
}

