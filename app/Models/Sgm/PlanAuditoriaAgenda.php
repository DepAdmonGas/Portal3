<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanAuditoriaAgenda extends Model
{
    protected $table = 'sgm_plan_auditoria_agenda';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_plan',
        'hora_inicio',
        'hora_termino',
        'proceso',
        'elemento_sistema',
        'nombre_rol',
        'guia',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_plan' => 'integer',
        'hora_inicio' => 'string',
        'hora_termino' => 'string',
        'proceso' => 'string',
        'elemento_sistema' => 'string',
        'nombre_rol' => 'string',
        'guia' => 'string',
    ];
}
