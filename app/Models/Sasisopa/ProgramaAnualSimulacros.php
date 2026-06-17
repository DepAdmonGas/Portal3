<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class ProgramaAnualSimulacros extends Model
{
    protected $table = 'tb_programa_anual_simulacros';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'nombre_simulacro',
        'periodicidad',
        'fecha',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'fecha' => 'date',
    ];

    // ProgramaAnualSimulacros.php

    public function personal()
    {
        return $this->hasMany(
            ProgramaAnualSimulacrosPersonal::class,
            'id_programa'
        );
    }

    public function resumen()
    {
        return $this->hasOne(
            ProgramaAnualSimulacrosResumen::class,
            'id_programa'
        );
    }

}
