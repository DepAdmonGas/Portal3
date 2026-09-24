<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class ReportePinturas extends Model
{
    protected $table = 'op_pinturas_complementos_reporte';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'id_estacion',
        'id_usuario',
        'fecha',
        'hora',
        'detalle',
        'status'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
        'fecha' => 'date',
        'status' => 'integer'
    ];

    public function detalle()
    {
        return $this->hasMany(ReportePinturasDetalle::class, 'id_reporte', 'id');
    }

    public function estacion()
    {
        return $this->belongsTo(\App\Models\Estacion::class, 'id_estacion', 'id');
    }

    public function usuario()
    {
        return $this->belongsTo(\App\Models\Usuario::class, 'id_usuario', 'id');
    }
}