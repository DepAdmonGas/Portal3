<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RhDiasDobles extends Model
{
    protected $table = 'op_rh_dias_dobles';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'dia',
        'mes',
        'descripcion'
    ];

    protected $casts = [
        'id' => 'integer',
        'dia' => 'integer',
        'mes' => 'integer'
    ];
}
