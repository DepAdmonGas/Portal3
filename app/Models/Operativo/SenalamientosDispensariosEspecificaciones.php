<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class SenalamientosDispensariosEspecificaciones extends Model
{
    protected $table = 'op_senalamientos_dispensarios_especificaciones';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_senalamiento',
        'dimension',
        'aprobacion',
        'modelo',
        'no_serie',
        'material'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_senalamiento' => 'integer'
    ];
}

