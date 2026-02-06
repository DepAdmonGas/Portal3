<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReciboNominaV2PrimaVacacional extends Model
{
    protected $table = 'op_recibo_nomina_v2_prima_vacacional';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'inicio_notificacion',
        'limite_notificacion',
        'id_usuario',
        'titulo_nomina',
        'status'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_usuario' => 'integer',
        'status' => 'integer',
        'inicio_notificacion' => 'date',
        'limite_notificacion' => 'date'
    ];
}
