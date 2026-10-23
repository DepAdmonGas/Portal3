<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class MaquinariaEquipo extends Model
{
    protected $table = 'op_maquinaria_equipos';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'maquinaria',
        'descripcion',
        'marca',
        'modelo',
        'no_serie',
        'fecha_compra',
        'fecha_instalacion',
        'factura',
        'manual',
        'proveedor',
        'costo_compra',
        'garantia',
        'estatus',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'costo_compra' => 'float',
        'estatus' => 'integer',
    ];

    public function comentarios()
    {
        return $this->hasMany(MaquinariaEquiposComentario::class, 'id_maquinaria_equipo', 'id');
    }
}