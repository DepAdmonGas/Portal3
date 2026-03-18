<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class RepresentanteTecnico extends Model
{
    protected $table = 'tb_representante_tecnico';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'nom_representante',
        'fecha',
        'archivo'
    ];

    protected $casts = [
        'id' => 'int',
        'id_estacion' => 'int',
        'fecha' => 'date'
    ];
}
