<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorteYear extends Model
{
    protected $table = 'op_corte_year';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'year',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'year' => 'integer',
    ];
}
