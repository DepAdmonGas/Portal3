<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequisitosLegalesLista extends Model
{
    protected $table = 'rl_requisitos_legales_lista';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'nivel_gobierno',
        'mun_alc_est',
        'dependencia',
        'permiso',
        'fundamento',
        'id_estacion',
        'id_usuario',
        'sgm',
        'disabled',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
        'sgm' => 'integer',
        'disabled' => 'integer',
        'estado' => 'integer',
    ];

}
