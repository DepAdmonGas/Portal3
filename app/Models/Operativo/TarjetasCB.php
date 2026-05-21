<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class TarjetasCB extends Model
{
    protected $table = 'op_tarjetas_c_b';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'idreporte_dia',
        'num',
        'concepto',
        'baucher'
    ];

    protected $casts = [
        'id'             => 'integer',
        'idreporte_dia'  => 'integer',
        'baucher'        => 'double',
    ];

    public function corteDia()
    {
        return $this->belongsTo(CorteDia::class, 'idreporte_dia', 'id');
    }
}
