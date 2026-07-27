<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;

class CumplimientoObjetivosRevision extends Model
{
    protected $table = 'sgm_cumplimiento_objetivos_revision';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'year',
        'fecha',
        'hora',
        'lugar',
        'responsable',
        'realizadopor',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'year' => 'integer',
        'fecha' => 'date',
        'hora' => 'string',
        'lugar' => 'string',
        'responsable' => 'string',
        'realizadopor' => 'integer',
        'estado' => 'integer',
    ];

    public function detalles()
    {
        return $this->hasMany(
            CumplimientoObjetivosRevisionDetalle::class,
            'id_cumplimiento'
        );
    }

    public function asistentes()
    {
        return $this->hasMany(
            CumplimientoObjetivosRevisionAsistente::class,
            'id_cumplimiento'
        );
    }
    
    public function responsable()
    {
        return $this->belongsTo(
            Usuario::class,
            'realizadopor'
        );
    }

}
