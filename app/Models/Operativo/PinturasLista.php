<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class PinturasLista extends Model
{
    protected $table = 'op_pinturas_lista';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'unidad',
        'producto',
        'estatus'
    ];

    protected $casts = [
        'id' => 'integer',
        'estatus' => 'integer'
    ];
}

