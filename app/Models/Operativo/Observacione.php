<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class Observacione extends Model
{
    protected $table = 'op_observaciones';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'idreporte_dia',
        'observaciones',
    ];

    protected $casts = [
        'id' => 'integer',
        'idreporte_dia' => 'integer',
        'observaciones' => 'string',
    ];
}
