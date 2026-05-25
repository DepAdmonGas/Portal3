<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class CierreLote extends Model
{
    protected $table = 'op_cierre_lote';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // no tiene created_at / updated_at

    protected $fillable = [
        'idreporte_dia',
        'empresa',
        'no_cierre_lote',
        'importe',
        'ticktes',
        'estado'
    ];

    protected $casts = [
        'idreporte_dia' => 'integer',
        'importe'       => 'double',
        'ticktes'       => 'integer',
        'estado'        => 'integer',
    ];

}

