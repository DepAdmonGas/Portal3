<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicitacionMunicipal extends Model
{
    protected $table = 'op_licitacion_municipal';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'year',
        'fecha',
        'nombre_formato',
        'archivo',
    ];

    protected $casts = [
        'id' => 'integer',
        'year' => 'integer',
        'fecha' => 'date',
        'nombre_formato' => 'string',
        'archivo' => 'string',
    ];
}
