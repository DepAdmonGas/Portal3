<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorteMes extends Model
{
    protected $table = 'op_corte_mes';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_year',
        'mes',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_year' => 'integer',
        'mes' => 'integer',
    ];
}
