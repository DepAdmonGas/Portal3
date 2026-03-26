<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class InventarioAditivo extends Model
{
    protected $table = 'op_inventario_aditivo';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'gasolina',
        'diesel',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'gasolina' => 'double',
        'diesel' => 'double',
    ];
}
