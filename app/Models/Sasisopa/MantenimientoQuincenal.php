<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;
use App\Models\Estacion;

class MantenimientoQuincenal extends Model
{
    protected $table = 'bi_mantenimiento_quincenal';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_empleado',
        'fechacreacion',
        'folio',
        'formato1',
        'formato2',
        'formato3',
        'formato4',
        'formato5',
        'formato6',
        'formato7',
    ];

    protected $casts = [
        'id_estacion' => 'integer',
        'id_empleado' => 'integer',
        'folio' => 'integer',
        'fechacreacion' => 'date',
    ];

    // RELACIÓN CON ESTACION
    public function estacion()
    {
        return $this->belongsTo(Estacion::class, 'id_estacion', 'id');
    }
}
