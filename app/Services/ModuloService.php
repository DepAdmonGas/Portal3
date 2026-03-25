<?php
namespace App\Services;

use App\Models\Usuario;
use App\Models\Modulo;

class ModuloService
{
    public static function getPermisos($usuario_id)
    {
        $usuario = Usuario::find($usuario_id);

        $modulos = Modulo::with([
            'roles' => function($q) use ($usuario) {
                $q->where('puesto_id', $usuario->id_puesto);
            },
            'usuarios' => function($q) use ($usuario_id) {
                $q->where('usuario_id', $usuario_id);
            }
        ])->get();

        $resultado = [];

        foreach ($modulos as $modulo) {

            $rol = $modulo->roles->first();
            $user = $modulo->usuarios->first();

            $resultado[$modulo->clave] = [
            'nombre' => $modulo->nombre ?? 'Modulo',
            'ruta'   => $modulo->ruta ?? '#',
            'icono'  => $modulo->icono ?: 'ti ti-box',

                'leer' => $user->pivot->leer ?? $rol->pivot->leer ?? 0,
                'crear' => $user->pivot->crear ?? $rol->pivot->crear ?? 0,
                'editar' => $user->pivot->editar ?? $rol->pivot->editar ?? 0,
                'eliminar' => $user->pivot->eliminar ?? $rol->pivot->eliminar ?? 0,
                'descargar' => $user->pivot->descargar ?? $rol->pivot->descargar ?? 0,
            ];
        }

        return $resultado;
    }
}