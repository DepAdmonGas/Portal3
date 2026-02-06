<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConstanciaFiscalEs extends Model
{
    protected $table = 'tb_constancia_fiscal_es';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'fecha',
        'archivo',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'fecha' => 'date',
        'archivo' => 'string',
    ];
}
