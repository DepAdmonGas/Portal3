<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class Contrato extends Model
{
   
    protected $table = 'op_contratos';
    protected $primaryKey = 'id_contratos';
    public $incrementing = true; 
    protected $keyType = 'int';

    public $timestamps = false; // Cambiar a true si tienes timestamps

    protected $fillable = [
        'fecha',
        'id_estacion',
        'descripcion',
        'archivo',
        'objeto',
        'proveedor',
        'vencimiento',
        'firmas',
        'comentario',
        'categoria',
    ];

    // Casts para convertir automÃ¡ticamente los tipos de datos
    protected $casts = [
        'id_contratos' => 'integer',
        'id_estacion' => 'integer',
        'fecha' => 'date',
        'vencimiento' => 'date',
        'descripcion' => 'string',
        'archivo' => 'string',
        'objeto' => 'string',
        'proveedor' => 'string',
        'firmas' => 'string',
        'comentario' => 'string',
        'categoria' => 'string',
    ];
}

