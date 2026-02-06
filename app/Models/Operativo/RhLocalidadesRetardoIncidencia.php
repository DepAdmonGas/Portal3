<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RhLocalidadesRetardoIncidencia extends Model
{
    protected $table = 'op_rh_localidades_retardo_incidencia';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'retardo',
        'incidencia'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'retardo' => 'integer',
        'incidencia' => 'integer'
    ];
}
