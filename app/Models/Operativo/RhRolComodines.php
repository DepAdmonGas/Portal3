<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RhRolComodines extends Model
{
    protected $table = 'op_rh_rol_comodines';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'status',
        'fecha_inicio',
        'fecha_fin',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'status' => 'integer',
    ];

    public function asignaciones()
    {
        return $this->hasMany(RhComodinesDia::class, 'id_reporte');
    }
}
