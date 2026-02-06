<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Entregas extends Model
{
    protected $table = 'tb_entregas';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'estacion',
        'fecha',
        'destinatario',
        'estatus',
    ];

    protected $casts = [
        'id' => 'integer',
        'fecha' => 'date',
        'estatus' => 'integer',
    ];
}
