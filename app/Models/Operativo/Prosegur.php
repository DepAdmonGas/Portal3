<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prosegur extends Model
{
    protected $table = 'op_prosegur';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'idreporte_dia',
        'denominacion',
        'recibo',
        'importe'
    ];

    protected $casts = [
        'id' => 'integer',
        'idreporte_dia' => 'integer',
        'importe' => 'double'
    ];
}
