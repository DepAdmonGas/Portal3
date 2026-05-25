<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RefaccionesReporteDetalle extends Model
{
    protected $table = 'op_refacciones_reporte_detalle';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_reporte',
        'id_refaccion',
        'unidad'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_reporte' => 'integer',
        'id_refaccion' => 'integer',
        'unidad' => 'integer'
    ];

}

