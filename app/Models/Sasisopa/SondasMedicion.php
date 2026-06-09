<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class SondasMedicion extends Model
{
    protected $table = 'tb_sondas_medicion';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'no_sonda',
        'marca',
        'modelo',
        'ubicacion',
        'estado'
    ];

    protected $casts = [
        'id' => 'int',
        'id_estacion' => 'int',
        'no_sonda' => 'int'
    ];
}
