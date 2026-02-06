<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MantenimientoVerificarTirillaInventario extends Model
{
    protected $table = 'po_mantenimiento_verificar_tirilla_inventario';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_verificar',
        'imagen_tirilla',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_verificar' => 'integer',
        'imagen_tirilla' => 'string',
    ];

}
