<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class BitacoraAditivo extends Model
{
    protected $table = 'op_bitacora_aditivo';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'folio',
        'litros',
        'fecha',
        'no_factura',
        'producto',
        'galones',
        'inventario_fisico',
        'estado'
    ];

    protected $casts = [
        'id_estacion' => 'integer',
        'folio' => 'integer',
        'litros' => 'double',
        'fecha' => 'date',
        'galones' => 'double',
        'inventario_fisico' => 'double',
        'estado' => 'integer'
    ];


}
