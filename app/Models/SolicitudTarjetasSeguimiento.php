<?php

namespace App\Models;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;

class SolicitudTarjetasSeguimiento extends Model
{
protected $table = 'tb_solicitud_tarjetas_seguimiento';
public $timestamps = false;

protected $fillable = [
'id_estacion',
'no_reporte',
'id_usuario',
'fecha_hora',
'seguimiento'
];

public function usuario()
{
return $this->belongsTo(Usuario::class, 'id_usuario');
}

}