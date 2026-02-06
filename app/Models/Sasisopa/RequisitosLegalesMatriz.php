<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequisitosLegalesMatriz extends Model
{
    protected $table = 'rl_requisitos_legales_matriz';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'idcalendario',
        'fecha_emision',
        'fecha_vencimiento',
        'acusepdf',
        'requisitolegalpdf',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'idcalendario' => 'integer',
        'estado' => 'integer',
        'fecha_emision' => 'date',
        'fecha_vencimiento' => 'date',
    ];

}
