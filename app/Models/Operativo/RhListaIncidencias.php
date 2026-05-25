<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RhListaIncidencias extends Model
{
    protected $table = 'op_rh_lista_incidencias';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'detalle',
        'documento',
        'puntos'
    ];

    protected $casts = [
        'id' => 'integer',
        'documento' => 'integer',
        'puntos' => 'float'
    ];
}

