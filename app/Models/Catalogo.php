<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Catalogo extends Model
{
protected $table = 'tb_modulos';

protected $primaryKey = 'id';

public $incrementing = true;

protected $keyType = 'int';

public $timestamps = false;

protected $fillable = [
'nombre_modulo',
'url',
'status',
];

protected $casts = [
'id' => 'integer',
'nombre_modulo' => 'string',
'url' => 'string',
'status' => 'integer',
];
}