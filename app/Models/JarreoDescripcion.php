<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JarreoDescripcion extends Model
{
    protected $table = 'tb_jarreo_descripcion';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_reporte',
        'posicion',
        'producto',
        'jarra1',
        'velocidad1',
        'jarra2',
        'velocidad2',
        'jarra3',
        'velocidad3',
        'resultado',
        'corte_pistola',
        'calcomania',
        'conta_meca',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_reporte' => 'integer',
        'posicion' => 'integer',
        'jarra1' => 'integer',
        'jarra2' => 'integer',
        'jarra3' => 'integer',
        'resultado' => 'integer',
    ];
}
