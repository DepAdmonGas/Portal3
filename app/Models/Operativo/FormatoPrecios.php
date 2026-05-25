<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class FormatoPrecios extends Model
{
    protected $table = 'op_formato_precios';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'fecha',
        'year',
        'mes',
        'estatus',
    ];

    protected $casts = [
        'id' => 'integer',
        'fecha' => 'date',
        'year' => 'integer',
        'mes' => 'integer',
        'estatus' => 'integer',
    ];
}

