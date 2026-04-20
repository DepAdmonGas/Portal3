<?php
namespace App\Models;
use App\Models\Modulo;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;

class ModulosUsuarios extends Model
{
protected $table = 'usuarios_modulos';

protected $primaryKey = 'id';

public $timestamps = false;

protected $fillable = [
'usuario_id',
'modulo_id',
'leer',
'crear',
'editar',
'eliminar',
'descargar'
];

public function usuario()
{
return $this->belongsTo(Usuario::class, 'usuario_id');
}

public function modulo()
{
return $this->belongsTo(Modulo::class, 'modulo_id');
}

}