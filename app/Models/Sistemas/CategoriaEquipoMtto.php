<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoriaEquipoMtto extends Model
{
    protected $table = 'ds_categoria_equipo_mtto';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // La tabla no usa created_at / updated_at

    protected $fillable = [
        'categoria',
        'estado',
    ];

    protected $casts = [
        'estado' => 'integer',
    ];
}
