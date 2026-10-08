<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;
use App\Models\Operativo\RhPersonal;

class OpReciboNominaV2 extends Model
{
    protected $table = 'op_recibo_nomina_v2';
    public $timestamps = false;

    protected $fillable = [
        'year',
        'mes',
        'no_semana_quincena',
        'descripcion',
        'id_estacion',
        'id_usuario',
        'id_puesto',
        'importe_total',
        'doc_nomina',
        'doc_nomina_firma',
        'doc_nomina_aguinaldo',
        'nomina_original',
        'prima_vacacional'
    ];

    public function personal()
    {
        return $this->belongsTo(RhPersonal::class, 'id_usuario', 'id');
    }

    public function comentarios()
    {
        return $this->hasMany(OpReciboNominaV2Comentario::class, 'id_nomina', 'id');
    }
}