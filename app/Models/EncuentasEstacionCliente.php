<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EncuentasEstacionCliente extends Model
{
    protected $table = 'tb_encuentas_estacion_cliente';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_cuentas_estacion',
        'nombre',
        'fecha',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_cuentas_estacion' => 'integer',
        'fecha' => 'datetime',
    ];
}
