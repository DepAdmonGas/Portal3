<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequisitosLegalesCalendario extends Model
{
    protected $table = 'rl_requisitos_legales_calendario';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_requisito_legal',
        'nivel_gobierno',
        'requisito_legal',
        'vigencia',
        'enero',
        'febrero',
        'marzo',
        'abril',
        'mayo',
        'junio',
        'julio',
        'agosto',
        'septiembre',
        'octubre',
        'noviembre',
        'diciembre',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_requisito_legal' => 'integer',
        'enero' => 'integer',
        'febrero' => 'integer',
        'marzo' => 'integer',
        'abril' => 'integer',
        'mayo' => 'integer',
        'junio' => 'integer',
        'julio' => 'integer',
        'agosto' => 'integer',
        'septiembre' => 'integer',
        'octubre' => 'integer',
        'noviembre' => 'integer',
        'diciembre' => 'integer',
        'estado' => 'integer',
    ];

}
