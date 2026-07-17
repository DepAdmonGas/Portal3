<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class IngresosFacturacionArchivo extends Model
{
protected $table = 'op_ingresos_facturacion_archivo';
protected $primaryKey = 'id';
public $incrementing = true;
protected $keyType = 'int';
public $timestamps = false;

protected $fillable = [
'id_year',
'archivo',
'fecha',
];

protected $casts = [
'id' => 'integer',
'id_year' => 'integer',
'archivo' => 'string',
'fecha' => 'string',
];
}
