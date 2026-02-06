<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Localidades extends Model
{
    protected $table = 'tb_localidades';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
    ];

    protected $casts = [
        'id' => 'integer',
    ];
}
