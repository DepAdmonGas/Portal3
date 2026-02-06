<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComparativoExcelSatRegistro extends Model
{
    protected $table = 'op_comparativo_excel_sat_registros';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // La tabla no maneja created_at / updated_at

    protected $fillable = [
        'id_responsable',
        'id_estacion',
        'fecha_hora',
        'year',
        'mes',
        'categoria',
        'descripcion',
        'monto'
    ];

    protected $casts = [
        'id_responsable' => 'integer',
        'id_estacion'    => 'integer',
        'fecha_hora'     => 'datetime',
        'year'           => 'integer',
        'mes'            => 'integer',
        'monto'          => 'double'
    ];

}
