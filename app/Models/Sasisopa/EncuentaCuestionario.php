<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class EncuentaCuestionario extends Model
{
    protected $table = 'tb_encuentas_cuestionario';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_encuentas',
        'num_pregunta',
        'pregunta',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_encuentas' => 'integer',
        'num_pregunta' => 'integer',
        'pregunta' => 'string',
    ];
}
