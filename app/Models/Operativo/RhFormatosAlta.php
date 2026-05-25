<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RhFormatosAlta extends Model
{
    protected $table = 'op_rh_formatos_alta';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_formulario',
        'id_estacion',
        'fecha_ingreso',
        'nombre',
        'puesto',
        'curriculum',
        'ine',
        'acta_nacimiento',
        'nss',
        'c_domicilio',
        'c_estudios',
        'c_recomendacion',
        'curp',
        'rfc',
        'c_antecedentes',
        'a_infonavit',
        'sd'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_formulario' => 'integer',
        'id_estacion' => 'integer',
        'puesto' => 'integer',
        'sd' => 'double',
        'fecha_ingreso' => 'date'
    ];
}

