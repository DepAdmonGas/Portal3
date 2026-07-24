<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;

class PatronInstrumento extends Model
{
    protected $table = 'sgm_patrones_instrumentos';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'periodicidad',
        'categoria',
    ];

    protected $casts = [
        'id' => 'integer',
        'nombre' => 'string',
        'periodicidad' => 'string',
        'categoria' => 'string',
    ];
}
