<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class MantenimientoPreventivoDocumento extends Model
{
    protected $table = 'op_mantenimiento_preventivo_documentos';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'fecha',
        'descripcion',
        'archivo',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'fecha' => 'string',
        'descripcion' => 'string',
        'archivo' => 'string',
    ];
}