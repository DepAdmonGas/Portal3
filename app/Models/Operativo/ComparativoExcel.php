<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComparativoExcel extends Model
{
    protected $table = 'op_comparativo_excel';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // La tabla no tiene created_at / updated_at

    protected $fillable = [
        'id_estacion',
        'year',
        'mes',
        'mensual',
        'monederos_1',
        'monederos_con_iva',
        'monederos_sin_iva',
        'clientes_1',
        'octanos_87',
        'octanos_91',
        'diesel',
        'aceites_lubricantes',
        'ieps',
        'autolavado',
        'aceites',
        'renta_espacios',
        'renta',
        'ingresos',
        'total_global',
        'iva',
        'total',
        'monederos_2',
        'clientes_2',
        'iva_cv',
        'total_cv',
        'monederos_3',
        'ingresos_2',
        'clientes_3',
        'iva_sat',
        'total_sat',
        'monederos_4',
        'clientes_4',
        'diferencia',
        'total_2',
        'diferencia_total_monederos',
        'iva_2',
        'sin_iva'
    ];

    protected $casts = [
        'id_estacion' => 'integer',
        'year'        => 'integer',
        'mes'         => 'integer',
        'mensual'     => 'double',
        'monederos_1' => 'double',
        'monederos_con_iva' => 'double',
        'monederos_sin_iva' => 'double',
        'clientes_1'  => 'double',
        'octanos_87'  => 'double',
        'octanos_91'  => 'double',
        'diesel'      => 'double',
        'aceites_lubricantes' => 'double',
        'ieps'        => 'double',
        'autolavado'  => 'double',
        'aceites'     => 'double',
        'renta_espacios' => 'double',
        'renta'       => 'double',
        'ingresos'    => 'double',
        'total_global'=> 'double',
        'iva'         => 'double',
        'total'       => 'double',
        'monederos_2' => 'double',
        'clientes_2'  => 'double',
        'iva_cv'      => 'double',
        'total_cv'    => 'double',
        'monederos_3' => 'double',
        'ingresos_2'  => 'double',
        'clientes_3'  => 'double',
        'iva_sat'     => 'double',
        'total_sat'   => 'double',
        'monederos_4' => 'double',
        'clientes_4'  => 'double',
        'diferencia'  => 'double',
        'total_2'     => 'double',
        'diferencia_total_monederos' => 'double',
        'iva_2'       => 'double',
        'sin_iva'     => 'double'
    ];

}
