<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;

class Elemento extends Model
{
    protected $table = 'sgm_elementos';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'no',
        'criterio',
        'url',
    ];

    protected $casts = [
        'id' => 'integer',
        'no' => 'string',
        'numero_sgm' => 'integer',
        'criterio' => 'string',
    ];
}
