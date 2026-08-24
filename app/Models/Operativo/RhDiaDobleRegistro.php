<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RhDiaDobleRegistro extends Model
{
    protected $table = 'op_rh_dia_doble_registro';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'year',
        'quincena',
        'status',
    ];

    protected $casts = [
        'id' => 'integer',
        'year' => 'integer',
        'quincena' => 'integer',
        'status' => 'integer',
    ];

    public function personal()
    {
        return $this->hasMany(RhDiaDoblePersonal::class, 'id_registro');
    }

    public function comentarios()
    {
        return $this->hasMany(RhDiaDobleComentarios::class, 'id_reporte');
    }
}
