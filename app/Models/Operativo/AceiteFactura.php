<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class AceiteFactura extends Model
{
    protected $table = 'op_aceites_factura';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // No existen created_at ni updated_at

    protected $fillable = [
        'id_mes',
        'fecha',
        'nombre_anexo',
        'archivo',
        'fecha_evaluacion',
        'puntaje'
    ];

    protected $casts = [
        'id_mes' => 'integer',
        'fecha' => 'date',
        'fecha_evaluacion' => 'date',
        'puntaje' => 'integer'
    ];

}

