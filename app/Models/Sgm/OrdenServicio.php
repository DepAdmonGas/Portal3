<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;

class OrdenServicio extends Model
{
    protected $table = 'sgm_orden_servicio';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'fecha',
        'hora',
        'id_solicitante',
        'descripcion',
        'justificacion',
        'realizadopor',
        'folio',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'fecha' => 'date',
        'hora' => 'string',
        'id_solicitante' => 'integer',
        'descripcion' => 'string',
        'justificacion' => 'string',
        'realizadopor' => 'integer',
        'folio' => 'integer',
    ];

    public function evaluacion()
    {
        return $this->hasOne(
            EvaluacionProveedor::class,
            'id_orden_servicio'
        );
    }

    public function solicitante()
    {
        return $this->belongsTo(
            Usuario::class,
            'id_solicitante'
        );
    }

    public function realizadoPor()
    {
        return $this->belongsTo(
            Usuario::class,
            'realizadopor'
        );
    }
}
