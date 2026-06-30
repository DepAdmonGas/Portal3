<?php

namespace App\Models\Sasisopa;
use Illuminate\Database\Eloquent\Model;


class Sasisopa extends Model
{
    protected $table = 'sa_sasisopa';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'numero_sasisopa',
        'nombre',
    ];

    protected $casts = [
        'id' => 'integer',
        'numero_sasisopa' => 'integer',
    ];

}