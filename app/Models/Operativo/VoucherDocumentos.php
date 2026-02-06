<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherDocumentos extends Model
{
    protected $table = 'op_voucher_documentos';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_reporte',
        'id_usuario',
        'fecha_creacion',
        'descripcion',
        'documento',
    ];

    protected $casts = [
        'id'           => 'integer',
        'id_reporte'   => 'integer',
        'id_usuario'   => 'integer',
        'fecha_creacion' => 'datetime',
    ];
}
