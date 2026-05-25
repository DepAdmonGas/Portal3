<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class Tabulador extends Model
{
    protected $table = 'op_tabulador';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'year',
        'dias'
    ];

    protected $casts = [
        'id'   => 'integer',
        'year' => 'integer',
        'dias' => 'integer',
    ];
}

