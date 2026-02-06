<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AclaracionVoucher extends Model
{
    protected $table = 'op_aclaracion_voucher';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // La tabla usa timestamp manual, no created_at/updated_at

    protected $fillable = [
        'fecha_creacion',
        'id_estacion',
        'year',
        'mes',
        'id_solicitante',
        'nombre_ticket',
        'fecha',
        'hora',
        'nombre_banco',
        'valera',
        'importe',
        'numero_aclaracion',
        'doc_ticket',
        'doc_voucher',
        'pagado',
        'estado'
    ];

    protected $casts = [
        'fecha_creacion' => 'datetime',
        'fecha' => 'date',
        'hora' => 'datetime:H:i:s',
        'importe' => 'double',
        'id_estacion' => 'integer',
        'id_solicitante' => 'integer',
        'pagado' => 'integer',
        'estado' => 'integer',
        'year' => 'integer',
        'mes' => 'integer'
    ];

}
