<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class ReportePinturasDetalle extends Model
{
    protected $table = 'op_pinturas_complementos_reporte_detalle';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_reporte',
        'id_producto',
        'unidad',
        'observaciones'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_reporte' => 'integer',
        'id_producto' => 'integer',
        'unidad' => 'integer'
    ];

    public function reporte()
    {
        return $this->belongsTo(ReportePinturas::class, 'id_reporte', 'id');
    }

    public function producto()
    {
        return $this->belongsTo(PinturasLista::class, 'id_producto', 'id');
    }
}