<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class AceiteLubricanteReporteFinalizar extends Model
{
    protected $table = 'op_aceites_lubricantes_reporte_finalizar';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // No existen created_at ni updated_at

    protected $fillable = [
        'id_mes',
        'fecha'
    ];

    protected $casts = [
        'id_mes' => 'integer',
        'fecha' => 'datetime',
    ];


}

