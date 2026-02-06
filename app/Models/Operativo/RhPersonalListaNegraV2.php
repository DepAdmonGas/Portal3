<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RhPersonalListaNegraV2 extends Model
{
    protected $table = 'op_rh_personal_lista_negra_v2';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'fecha',
        'nombre',
        'puesto',
        'causa',
        'motivo',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'fecha' => 'datetime',
    ];

}
