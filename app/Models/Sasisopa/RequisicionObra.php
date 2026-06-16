<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;

class RequisicionObra extends Model
{
    protected $table = 'tb_requisicion_obra';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_usuario',
        'no_folio',
        'fecha',
        'descripcion',
        'justificacion',
        'proveedor',
        'estado'
    ];

    protected $casts = [
        'id' => 'int',
        'id_estacion' => 'int',
        'id_usuario' => 'int',
        'no_folio' => 'int',
        'fecha' => 'datetime',
        'estado' => 'int'
    ];

    public function usuario()
    {
        return $this->belongsTo(
            Usuario::class,
            'id_usuario'
        );
    }

   public function formato12()
{
    return $this->hasOne(
        RequisicionObraFormato12::class,
        'id_requisicion'
    );
}

public function formato14()
{
    return $this->hasOne(
        RequisicionObraFormato14::class,
        'id_requisicion'
    );
}

public function formato15()
{
    return $this->hasOne(
        RequisicionObraFormato15::class,
        'id_requisicion'
    );
}

public function cartaResponsiva()
{
    return $this->hasOne(
        RequisicionObraCartaResponsiva::class,
        'id_requisicion'
    );
}

}
