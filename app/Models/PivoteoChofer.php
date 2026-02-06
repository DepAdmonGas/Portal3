<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PivoteoChofer extends Model
{
    protected $table = 'tb_pivoteo_chofer';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'nombre_chofer',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'estado' => 'integer',
    ];
}
