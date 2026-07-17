<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RhLocalidad extends Model
{
protected $table = 'op_rh_localidades';
protected $primaryKey = 'id';
public $incrementing = true;
protected $keyType = 'int';
public $timestamps = false;

protected $fillable = [
'numlista',
'localidad',
'recuperacion_vapores',
];

protected $casts = [
'id' => 'integer',
'numlista' => 'integer',
];
}
