<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RhDiaDoblePersonal extends Model
{
    protected $table = 'op_rh_dia_doble_personal';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_registro',
        'id_usuario',
        'fecha_doble',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_registro' => 'integer',
        'id_usuario' => 'integer',
    ];

    public function registro()
    {
        return $this->belongsTo(RhDiaDobleRegistro::class, 'id_registro');
    }
}
