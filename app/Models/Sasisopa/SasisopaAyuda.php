<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SasisopaAyuda extends Model
{
    protected $table = 'pu_sasisopa_ayuda';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'detalle',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_usuario' => 'integer',
        'estado' => 'integer',
    ];

}
