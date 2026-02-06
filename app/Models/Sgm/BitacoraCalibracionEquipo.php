<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BitacoraCalibracionEquipo extends Model
{
    protected $table = 'sgm_bitacora_calibracion_equipo';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_programa',
        'fecha',
        'hora',
        'nombre_equipo',
        'marca',
        'capacidad',
        'almacena',
        'nombre_laboratorio',
        'no_acreditacion',
        'metodo_calibracion',
        'nombre_patron',
        'marca_modelo_serie',
        'resolucion',
        'incertidumbre',
        'vigencia_certificado',
        'realizadopor',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_programa' => 'integer',
        'fecha' => 'date',
        'hora' => 'string',
        'nombre_equipo' => 'string',
        'marca' => 'string',
        'capacidad' => 'string',
        'almacena' => 'string',
        'nombre_laboratorio' => 'string',
        'no_acreditacion' => 'string',
        'metodo_calibracion' => 'string',
        'nombre_patron' => 'string',
        'marca_modelo_serie' => 'string',
        'resolucion' => 'string',
        'incertidumbre' => 'string',
        'vigencia_certificado' => 'string',
        'realizadopor' => 'integer',
    ];
}
