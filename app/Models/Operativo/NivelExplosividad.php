<?php

namespace App\Models;

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

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'folio' => 'integer',
        'fecha' => 'date',
        'estado' => 'integer',
    ];
}
