<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudTarjetasImagen extends Model
{
protected $table = 'tb_imagen_tarjeta';

protected $primaryKey = 'id';

public $timestamps = false;

protected $fillable = [
'no_solicitud',
'estacion',
'ruta'
];

//---------- Relación con Solicitud de Tarjetas ----------//
public function solicitud()
{
return $this->belongsTo(SolicitudTarjetas::class, 'no_solicitud', 'no_solicitud');
}
}