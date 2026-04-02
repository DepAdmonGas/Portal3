<?php

namespace App\Models;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;

class SolicitudGafetesSeguimiento extends Model
{
protected $table = 'tb_solicitud_gafetes_seguimiento';
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