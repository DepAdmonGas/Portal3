<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class NivelExplosividad extends Model
{
    protected $table = 'op_nivel_explosividad';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'folio',
        'fecha',
        'estado',
    ];

    // 'fecha' va sin cast date: el legacy guarda los borradores en '0000-00-00' y
    // Carbon lanza excepción al leerlo. formatearFecha() ya normaliza ese centinela.
    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'folio' => 'integer',
        'estado' => 'integer',
    ];
}

