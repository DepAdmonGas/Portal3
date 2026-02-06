<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequisicionObraCartaResponsiva extends Model
{
    protected $table = 'tb_requisicion_obra_carta_responsiva';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_requisicion',
        'fecha',
        'archivo',
        'dia',
        'mes',
        'year',
        'municipio',
        'estado',
        'representante_legal',
        'razon_social',
        'domicilio',
        'apoderado_legal',
        'firma'
    ];

    protected $casts = [
        'id' => 'int',
        'id_requisicion' => 'int',
        'fecha' => 'datetime',
        'dia' => 'int',
        'year' => 'int'
    ];
}
