<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RhAreas extends Model
{
    protected $table = 'op_rh_areas';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'nombre_area',
        'abreviatura'
    ];

    protected $casts = [
        'id' => 'integer'
    ];
}
