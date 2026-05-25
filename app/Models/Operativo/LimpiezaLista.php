<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class LimpiezaLista extends Model
{
    protected $table = 'op_limpieza_lista';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'unidad',
        'producto',
        'estatus',
    ];

    protected $casts = [
        'id' => 'integer',
        'unidad' => 'string',
        'producto' => 'string',
        'estatus' => 'integer',
    ];
}

