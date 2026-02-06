<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NivelExplosividadDetalle extends Model
{
    protected $table = 'op_nivel_explosividad_detalle';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_reporte',
        'elemento1',
        'elemento2',
        'elemento3',
        'elemento4',
        'elemento5',
        'elemento6',
        'elemento7',
        'elemento8',
        'elemento9',
        'elemento10',
        'elemento11',
        'elemento12',
        'elemento13',
        'elemento14',
        'elemento15',
        'elemento16',
        'elemento17',
        'elemento18',
        'observaciones',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_reporte' => 'integer',
        'elemento1' => 'string',
        'elemento2' => 'string',
        'elemento3' => 'string',
        'elemento4' => 'integer',
        'elemento5' => 'integer',
        'elemento6' => 'integer',
        'elemento7' => 'integer',
        'elemento8' => 'integer',
        'elemento9' => 'integer',
        'elemento10' => 'integer',
        'elemento11' => 'integer',
        'elemento12' => 'integer',
        'elemento13' => 'integer',
        'elemento14' => 'integer',
        'elemento15' => 'integer',
        'elemento16' => 'integer',
        'elemento17' => 'integer',
        'elemento18' => 'integer',
        'observaciones' => 'string',
    ];
}
