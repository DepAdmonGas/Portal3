<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class TelefonosEmergencias extends Model
{
    protected $table = 'tb_telefonos_emergencias';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'titulo',
        'telefono',
        'prioridad'
    ];

    protected $casts = [
        'id' => 'int',
        'id_estacion' => 'int',
        'prioridad' => 'int'
    ];
}
