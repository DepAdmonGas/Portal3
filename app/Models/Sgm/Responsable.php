<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;
use App\Models\Estacion;

class Responsable extends Model
{
    protected $table = 'sgm_responsable';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'fecha',
        'responsable',
        'auxiliar',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'fecha' => 'date',
        'responsable' => 'integer',
        'auxiliar' => 'integer',
    ];

    public function usuarioResponsable()
    {
        return $this->belongsTo(
            Usuario::class,
            'responsable',
            'id'
        );
    }

    public function usuarioAuxiliar()
    {
        return $this->belongsTo(
            Usuario::class,
            'auxiliar',
            'id'
        );
    }

    public function estacion()
    {
        return $this->belongsTo(
            Estacion::class,
            'id_estacion',
            'id'
        );
    }
}
