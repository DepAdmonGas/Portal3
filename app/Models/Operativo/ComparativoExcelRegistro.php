<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class ComparativoExcelRegistro extends Model
{
    protected $table = 'op_comparativo_excel_registros';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // La tabla no tiene created_at / updated_at

    protected $fillable = [
        'id_responsable',
        'id_estacion',
        'fecha_hora',
        'year',
        'mes',
        'seccion',
        'descripcion',
        'monto'
    ];

    protected $casts = [
        'id_responsable' => 'integer',
        'id_estacion'    => 'integer',
        'fecha_hora'     => 'datetime',
        'year'           => 'integer',
        'mes'            => 'integer',
        'monto'          => 'double'
    ];

}

