<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class PolizaEs extends Model
{
protected $table = 'op_poliza_es';
protected $primaryKey = 'id_poliza';
public $incrementing = true;
protected $keyType = 'int';
public $timestamps = false;

protected $fillable = [
'id_estacion',
'emision',
'vencimiento',
'archivo',
];

protected $casts = [
'id_poliza' => 'integer',
'id_estacion' => 'integer',
'emision' => 'date',
'vencimiento' => 'date',
];
}
