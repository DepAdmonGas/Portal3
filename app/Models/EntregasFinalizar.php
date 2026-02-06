<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntregasFinalizar extends Model
{
    protected $table = 'tb_entregas_finalizar';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_entrega',
        'fecha',
        'nombre',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_entrega' => 'integer',
        'fecha' => 'datetime',
    ];
}
