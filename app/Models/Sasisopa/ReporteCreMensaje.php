<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;

class ReporteCreMensaje extends Model
{
    protected $table = 're_reporte_cre_mensajes';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_reporte',
        'id_fecha',
        'id_usuario',
        'fecha',
        'mensaje',
        'tipo',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_reporte' => 'integer',
        'id_fecha' => 'integer',
        'id_usuario' => 'integer',
        'fecha' => 'datetime',
        'tipo' => 'integer',
    ];

public function usuario()
{
    return $this->belongsTo(Usuario::class, 'id_usuario');
}

}
