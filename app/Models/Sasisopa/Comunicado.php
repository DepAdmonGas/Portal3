<?php

namespace App\Models\Sasisopa;
use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;
use App\Models\Puestos;

class Comunicado extends Model
{
    protected $table = 'co_comunicados';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_comunicado',
        'id_usuario',
        'fecha',
        'tema',
        'detalle',
        'dirigidoa',
        'archivo',
    ];

    protected $casts = [
        'id'             => 'integer',
        'id_estacion'    => 'integer',
        'id_comunicado'  => 'integer',
        'id_usuario'     => 'integer',
        'fecha'          => 'date:Y-m-d',
    ];

    public function usuario()
    {
        return $this->belongsTo(
            Usuario::class,
            'id_usuario'
        );
    }


}