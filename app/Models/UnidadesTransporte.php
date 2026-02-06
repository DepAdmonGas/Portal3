<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnidadesTransporte extends Model
{
    protected $table = 'tb_unidades_transporte';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'no_unidad',
        'estado'
    ];

    protected $casts = [
        'id' => 'int',
        'estado' => 'int'
    ];
}
