<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class PolizaIncidencia extends Model
{
protected $table = 'op_poliza_incidencia';
protected $primaryKey = 'id_poliza_incidencia';
public $incrementing = true;
protected $keyType = 'int';
public $timestamps = false;

protected $fillable = [
'id_estacion',
'fecha',
'hora',
'asunto',
'observaciones',
'solucion',
'archivo',
];

protected $casts = [
'id_poliza_incidencia' => 'integer',
'id_estacion' => 'integer',
'fecha' => 'date',
'hora' => 'string',
];
}
