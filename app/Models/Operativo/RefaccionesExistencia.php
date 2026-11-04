<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RefaccionesExistencia extends Model
{
    protected $table = 'op_refacciones_existencias';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_refaccion', 'id_estacion', 'existencia', 'costo_promedio', 'stock_minimo', 'actualizado_en',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_refaccion' => 'integer',
        'id_estacion' => 'integer',
        'existencia' => 'float',
        'costo_promedio' => 'float',
        'stock_minimo' => 'float',
    ];
}
