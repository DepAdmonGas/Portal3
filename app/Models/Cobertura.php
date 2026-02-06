<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cobertura extends Model
{
    protected $table = 'coberturas';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // La tabla ya tiene su timestamp personalizado

    protected $fillable = [
        'nombre',
        'archivo',
        'fecha_subida',
        'estado',
    ];

    protected $casts = [
        'fecha_subida' => 'datetime',
        'estado' => 'string', // enum tratado como string
    ];
}
