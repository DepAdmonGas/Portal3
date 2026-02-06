<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuejasSugerencia extends Model
{
    protected $table = 'se_quejas_sugerencias';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'fecha',
        'nombre',
        'motivos_causas',
        'nombre_dirigido',
        'contacto',
        'nombre_puesto',
        'consecuencias',
        'solucion',
        'plazo',
        'confirmacion',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'fecha' => 'date',
        'nombre' => 'string',
        'motivos_causas' => 'string',
        'nombre_dirigido' => 'string',
        'contacto' => 'string',
        'nombre_puesto' => 'string',
        'consecuencias' => 'string',
        'solucion' => 'string',
        'plazo' => 'string',
        'confirmacion' => 'string',
    ];
}
