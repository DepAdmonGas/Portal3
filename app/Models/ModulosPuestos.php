<?php
namespace App\Models;
use App\Models\Modulo;
use App\Models\Puestos;
use Illuminate\Database\Eloquent\Model;

class ModulosPuestos extends Model
{
protected $table = 'roles_modulos';

protected $primaryKey = 'id';

public $timestamps = false;

protected $fillable = [
'puesto_id',
'modulo_id',
'leer',
'crear',
'editar',
'eliminar',
'descargar'
];

public function puesto()
{
return $this->belongsTo(Puestos::class, 'puesto_id');
}

public function modulo()
{
return $this->belongsTo(Modulo::class, 'modulo_id');
}

}