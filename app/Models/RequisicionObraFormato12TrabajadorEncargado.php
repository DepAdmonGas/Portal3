<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequisicionObraFormato12TrabajadorEncargado extends Model
{
    protected $table = 'tb_requisicion_obra_formato_12_trabajador_encargado';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_requisicion',
        'id_personal',
        'nombre',
        'puesto',
        'no_seguro',
        'categoria'
    ];

    protected $casts = [
        'id' => 'int',
        'id_requisicion' => 'int',
        'id_personal' => 'int',
        'categoria' => 'int'
    ];
}
