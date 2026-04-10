<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PolizaSeguroCobertura extends Model
{
protected $table = 'tb_cobertura_poliza';
protected $primaryKey = 'id';
public $timestamps = false;

protected $fillable = [
'fecha_hora',
'archivo',
'estatus',
];

protected $casts = [
'id' => 'integer',
'fecha_hora' => 'datetime',
];
}
