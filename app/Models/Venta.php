<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    protected $table = 'venta';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'folio',
        'total',
        'fecha',
        'hora',
        'clave_desp',
        'eco'
    ];

    protected $casts = [
        'id' => 'int',
        'folio' => 'int',
        'total' => 'int',
        'fecha' => 'date',
        'hora' => 'datetime:H:i:s',
        'clave_desp' => 'int'
    ];
}
