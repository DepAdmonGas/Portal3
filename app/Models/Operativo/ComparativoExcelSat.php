<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComparativoExcelSat extends Model
{
    protected $table = 'op_comparativo_excel_sat';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // La tabla no tiene created_at / updated_at

    protected $fillable = [
        'id_estacion',
        'year',
        'mes',
        'categoria',
        'sat_monto',
        'despacho_monto'
    ];

    protected $casts = [
        'id_estacion'     => 'integer',
        'year'            => 'integer',
        'mes'             => 'integer',
        'sat_monto'       => 'double',
        'despacho_monto'  => 'double'
    ];
}
