<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarioRefaccionMtto extends Model
{
    protected $table = 'ds_inventario_refacciones_mtto';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // No tiene created_at ni updated_at

    protected $fillable = [
        'id_estacion',
        'id_categoria',
        'codigo',
        'nombre',
        'modelo',
        'fecha_compra',
        'fecha_baja',
        'ubicacion',
        'notas',
        'calificacion',
        'estado',
        'estado_equipo',
        'componentes',
    ];

    protected $casts = [
        'id_estacion' => 'integer',
        'id_categoria' => 'integer',
        'calificacion' => 'integer',
        'estado' => 'integer',
        'estado_equipo' => 'integer',
        'componentes' => 'integer',
        'fecha_compra' => 'date',
        'fecha_baja' => 'date',
    ];

}
