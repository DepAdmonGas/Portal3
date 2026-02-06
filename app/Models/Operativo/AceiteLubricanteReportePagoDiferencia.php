<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AceiteLubricanteReportePagoDiferencia extends Model
{
    protected $table = 'op_aceites_lubricantes_reporte_pagodiferencia';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // No tiene created_at ni updated_at

    protected $fillable = [
        'id_aceite',
        'id_reporte',
        'nomaceite',
        'diferencia',
        'fecha',
        'documento',
        'comentario',
        'estado'
    ];

    protected $casts = [
        'id_aceite' => 'integer',
        'id_reporte' => 'integer',
        'nomaceite' => 'integer',
        'diferencia' => 'integer',
        'fecha' => 'datetime',
        'estado' => 'integer',
    ];
}
