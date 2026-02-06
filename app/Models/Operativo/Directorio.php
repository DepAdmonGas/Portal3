<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Directorio extends Model
{
    protected $table = 'op_directorio';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_mes',
        'cuenta',
        'puesto',
        'clave',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_mes' => 'integer',
        'cuenta' => 'string',
        'puesto' => 'string',
        'clave' => 'string',
    ];
}
