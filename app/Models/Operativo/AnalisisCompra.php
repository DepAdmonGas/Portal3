<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class AnalisisCompra extends Model
{
    protected $table = 'tb_analisis_compra';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'fecha',
        'factura',
        'notac',
        'status',
    ];

    protected $casts = [
        'id' => 'integer',
        'fecha' => 'date',
    ];
}
