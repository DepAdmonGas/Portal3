<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CapacitacionInterna extends Model
{
    protected $table = 'tb_capacitacion_interna';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'id_tema',
        'id_modulo',
        'id_submodulo',
        'fechaprogramada',
        'fechareal',
        'id_detalle',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_usuario' => 'integer',
        'id_tema' => 'integer',
        'id_modulo' => 'integer',
        'id_submodulo' => 'integer',
        'fechaprogramada' => 'date',
        'fechareal' => 'date',
        'id_detalle' => 'integer',
    ];
}
