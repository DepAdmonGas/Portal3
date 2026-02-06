<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NivelExplosividadPozoMotobomba extends Model
{
    protected $table = 'op_nivel_explosividad_pozo_motobomba';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_reporte',
        'pozo_motobomba',
        'ppm',
        'ubicacion',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_reporte' => 'integer',
        'pozo_motobomba' => 'string',
        'ppm' => 'integer',
        'ubicacion' => 'string',
    ];
}
