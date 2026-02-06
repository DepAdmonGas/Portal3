<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RhPuestos extends Model
{
    protected $table = 'op_rh_puestos';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'puesto',
        'status'
    ];

    protected $casts = [
        'id' => 'integer',
        'status' => 'integer'
    ];
}
