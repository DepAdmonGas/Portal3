<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class InventariosDiario extends Model
{
    protected $table = 'op_inventarios_diarios';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'fecha',
        'estatus',
    ];

    protected $casts = [
        'id' => 'integer',
        'fecha' => 'date',
        'estatus' => 'integer',
    ];
}

