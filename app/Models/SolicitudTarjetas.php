<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudTarjetas extends Model
{
protected $table = 'tb_solicitud_tarjetas';

protected $primaryKey = 'id';

public $timestamps = false;

protected $fillable = [
'no_solicitud',
'id_estacion',
'id_usuario',
'fecha',
'razon_social',
'no_flotilla',
'vehiculo',
'placas',
'no_unidad',
'tarjeta',
'tipo_tarjeta',
'comentarios',
'estatus'
];

protected $casts = [
'id' => 'int',
'no_solicitud' => 'int',
'fecha' => 'date'
];

//---------- Relación con Solicitud Tarjetas (Imagenes) ----------//
public function imagenes()
{
return $this->hasMany(SolicitudTarjetasImagen::class, 'no_solicitud', 'no_solicitud');
}


}
