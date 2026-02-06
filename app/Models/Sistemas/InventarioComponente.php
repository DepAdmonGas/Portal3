<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarioComponente extends Model
{
    protected $table = 'ds_inventario_componentes';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // No usa created_at / updated_at

    protected $fillable = [
        'nombre_componente',
        'estado',
    ];

    protected $casts = [
        'estado' => 'integer',
    ];
}
