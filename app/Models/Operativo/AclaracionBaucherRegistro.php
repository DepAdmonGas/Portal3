<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AclaracionBaucherRegistro extends Model
{
    protected $table = 'op_aclaracion_bauchers_registro';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // La tabla no tiene created_at ni updated_at

    protected $fillable = [
        'year',
        'mes',
        'fecha',
        'descripcion',
        'id_estacion',
        'id_usuario',
        'estatus'
    ];

    protected $casts = [
        'year' => 'integer',
        'mes' => 'integer',
        'fecha' => 'datetime',
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
        'estatus' => 'integer'
    ];
}
