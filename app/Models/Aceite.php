<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Aceite extends Model
{
    protected $table = 'tb_aceites';

    protected $primaryKey = 'id_aceite';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'unidad',
        'piezas',
        'precio',
        'id_categoria',
        'estatus',
    ];

    protected $casts = [
        'id_aceite' => 'integer',
        'nombre' => 'string',
        'unidad' => 'string',
        'piezas' => 'integer',
        'precio' => 'string',
        'id_categoria' => 'integer',
        'estatus' => 'integer',
    ];
}
