<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisitaEstacion extends Model
{
    protected $table = 'tb_visita_estacion';

    protected $primaryKey = 'id_visita_estacion';

    public $timestamps = false;

    protected $fillable = [
        'nombre_a',
        'archivo'
    ];

    protected $casts = [
        'id_visita_estacion' => 'int'
    ];
}
