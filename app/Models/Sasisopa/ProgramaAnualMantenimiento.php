<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramaAnualMantenimiento extends Model
{
    protected $table = 'po_programa_anual_mantenimiento';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'year',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'year' => 'string',
        'estado' => 'integer',
    ];

}
