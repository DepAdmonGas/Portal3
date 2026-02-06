<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequisicionObra extends Model
{
    protected $table = 'tb_requisicion_obra';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_usuario',
        'no_folio',
        'fecha',
        'descripcion',
        'justificacion',
        'estado'
    ];

    protected $casts = [
        'id' => 'int',
        'id_estacion' => 'int',
        'id_usuario' => 'int',
        'no_folio' => 'int',
        'fecha' => 'datetime',
        'estado' => 'int'
    ];
}
