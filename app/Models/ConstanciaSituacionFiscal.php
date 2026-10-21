<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Constancia de Situación Fiscal.
 *
 * Tabla heredada del proyecto anterior (departamento-operativo).
 * id_estacion está en el espacio de ids de op_rh_localidades y no en el de
 * tb_estaciones: lo cubren las gasolineras 1-7 y 14, más Dirección de
 * Operaciones (11), Comercializadora (22) y Quitarga (23).
 */
class ConstanciaSituacionFiscal extends Model
{
    protected $table = 'tb_constancia_fiscal_es';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'fecha',
        'archivo',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'fecha' => 'date',
    ];
}