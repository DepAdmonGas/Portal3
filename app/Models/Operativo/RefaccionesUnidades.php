<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RefaccionesUnidades extends Model
{
    protected $table = 'op_refacciones_unidades';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'fechacreacion',
        'id_refaccion',
        'unidad'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_refaccion' => 'integer',
        'unidad' => 'integer',
        'fechacreacion' => 'datetime'
    ];

}

