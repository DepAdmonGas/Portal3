<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class ReportePapeleriaDetalle extends Model
{
    protected $table = 'op_papeleria_reporte_detalle';
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
        return $this->belongsTo(ReportePapeleria::class, 'id_reporte', 'id');
    }

    public function producto()
    {
        return $this->belongsTo(PapeleriaLista::class, 'id_producto', 'id');
    }
}