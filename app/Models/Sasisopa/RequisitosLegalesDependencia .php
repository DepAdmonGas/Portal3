<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequisitosLegalesDependencia extends Model
{
    protected $table = 'rl_requisitos_legales_dependencias';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'dependencia',
        'id_estacion',
        'disabled',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'disabled' => 'integer',
        'estado' => 'integer',
    ];

}
