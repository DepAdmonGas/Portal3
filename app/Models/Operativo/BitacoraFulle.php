<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BitacoraFulle extends Model
{
    protected $table = 'tb_bitacora_fulles';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_usuario',
        'fechafactura',
        'fechaarribo',
        'nopedido',
        'nofull',
        'operador',
        'transporte',
        'unidad',
        'capacidadautotanque',
        'noautotanque',
        'medidahumeda',
        'nice',
        'medidasat',
        'niceseca',
        'producto',
        'hora',
        'temperaturainicial',
        'temperaturafinal',
        'nofactura',
        'preciolitro',
        'preciotransporte',
        'litrosfacturados',
        'tirilladescarga',
        'medidacuentalitros',
        'tiempodescarga',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
        'fechafactura' => 'date',
        'fechaarribo' => 'string',
        'nopedido' => 'string',
        'nofull' => 'string',
        'operador' => 'string',
        'transporte' => 'string',
        'unidad' => 'string',
        'capacidadautotanque' => 'string',
        'noautotanque' => 'string',
        'medidahumeda' => 'string',
        'nice' => 'string',
        'medidasat' => 'string',
        'niceseca' => 'string',
        'producto' => 'string',
        'hora' => 'string',
        'temperaturainicial' => 'string',
        'temperaturafinal' => 'string',
        'nofactura' => 'string',
        'preciolitro' => 'float',
        'preciotransporte' => 'float',
        'litrosfacturados' => 'float',
        'tirilladescarga' => 'string',
        'medidacuentalitros' => 'string',
        'tiempodescarga' => 'string',
    ];
}
