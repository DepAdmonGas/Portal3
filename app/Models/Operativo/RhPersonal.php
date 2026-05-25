<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RhPersonal extends Model
{
    protected $table = 'op_rh_personal';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'fecha_ingreso',
        'no_colaborador',
        'nombre_completo',
        'puesto',
        'requisicion',
        'curriculum',
        'ine',
        'acta_nacimiento',
        'c_domicilio',
        'curp',
        'rfc',
        'nss',
        'c_estudios',
        'c_recomendacion',
        'a_infonavit',
        'c_antecedentes',
        'contrato',
        'sd',
        'documentos',
        'estado'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'fecha_ingreso' => 'date',
        'no_colaborador' => 'integer',
        'puesto' => 'integer',
        'sd' => 'float',
        'estado' => 'integer'
    ];
}

