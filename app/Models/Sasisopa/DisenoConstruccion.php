<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class DisenoConstruccion extends Model
{
    protected $table = 'tb_diseno_construccion';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'valor1',
        'valor2',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'valor1' => 'string',
        'valor2' => 'string',
        'estado' => 'integer',
    ];
}
