<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class Modulo extends Model
{
    protected $table = 'modulos';

    public function roles()
    {
        return $this->belongsToMany(Puestos::class, 'roles_modulos', 'modulo_id', 'puesto_id')
                    ->withPivot(['leer','crear','editar','eliminar','descargar']);
    }

    public function usuarios()
    {
        return $this->belongsToMany(Usuario::class, 'usuarios_modulos', 'modulo_id', 'usuario_id')
                    ->withPivot(['leer','crear','editar','eliminar','descargar']);
    }
}