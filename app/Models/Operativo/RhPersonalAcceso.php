<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RhPersonalAcceso extends Model
{
    protected $table = 'op_rh_personal_acceso';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_personal',
        'huella',
        'pin'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_personal' => 'integer',
        'pin' => 'integer'
    ];
}

