<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class ReciboNominaAcuseV2 extends Model
{
    protected $table = 'op_recibo_nomina_acuse_v2';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false; // La tabla no usa created_at / updated_at estÃ¡ndar

    protected $fillable = [
        'id_estacion',
        'fecha',
        'archivo',
        'descripcion',
        'semana_quincena'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'semana_quincena' => 'integer',
        'fecha' => 'datetime',
    ];
}

