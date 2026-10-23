<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class MantenimientoPreventivoListado extends Model
{
    protected $table = 'op_mantenimiento_preventivo_listado';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'descripcion',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'descripcion' => 'string',
        'estado' => 'integer',
    ];
}