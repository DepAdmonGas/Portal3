<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PolizaSeguro extends Model
{
protected $table = 'tb_poliza_seguro';
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
