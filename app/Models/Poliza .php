<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Poliza extends Model
{
    protected $table = 'polizas';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'archivo',
        'fecha_subida',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'fecha_subida' => 'datetime',
    ];
}
