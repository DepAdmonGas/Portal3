<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipoComputoMtto extends Model
{
    protected $table = 'ds_equipo_computo_mtto';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // La tabla no maneja created_at / updated_at

    protected $fillable = [
        'id_refaccion',
        'codigo',
        'nombre',
        'modelo',
        'fecha_compra',
        'fecha_baja',
        'notas',
        'calificacion',
        'estado',
    ];

    protected $casts = [
        'id_refaccion' => 'integer',
        'nombre' => 'integer',      // En la tabla está declarado como INT
        'calificacion' => 'integer',
        'estado' => 'integer',
        'fecha_compra' => 'date',
        'fecha_baja' => 'date',
    ];

}
