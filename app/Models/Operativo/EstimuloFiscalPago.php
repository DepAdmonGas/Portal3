<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class EstimuloFiscalPago extends Model
{
protected $table = 'op_estimulo_fiscal_pago';
protected $primaryKey = 'id';
public $incrementing = true;
protected $keyType = 'int';
public $timestamps = false;

protected $fillable = [
'id_estacion',
'fecha_inicio',
'fecha_termino',
'pdf',
'xml',
'co_pdf',
'co_xml',
];

protected $casts = [
'id' => 'integer',
'id_estacion' => 'integer',
'fecha_inicio' => 'date',
'fecha_termino' => 'date',
];
}
