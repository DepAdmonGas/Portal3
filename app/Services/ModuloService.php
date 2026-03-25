<?php
namespace App\Services;

use App\Models\Usuario;
use App\Models\Modulo;

class ModuloService
{

    /*
    TODOS LOS MÓDULOS (MENÚ)
    $permisos = ModuloService::getPermisos($usuario->id);

    SOLO UN MÓDULO (CONTROLLER)
    $permisos = ModuloService::getPermiso($usuario->id, 'solicitud-gafetes');

    USAR DESDE SESIÓN
    $permisos = ModuloService::permisosSesion('solicitud-gafetes');
    
    */


    /**
     * Obtener TODOS los módulos o uno específico
     */
    public static function getPermisos($usuario_id, $clave = null)
    {
        $usuario = Usuario::find($usuario_id);

        if (!$usuario) {
            return [];
        }

        $query = Modulo::with([
            'roles' => function($q) use ($usuario) {
                $q->where('puesto_id', $usuario->id_puesto);
            },
            'usuarios' => function($q) use ($usuario_id) {
                $q->where('usuario_id', $usuario_id);
            }
        ]);

        // Filtrar por módulo si viene clave
        if ($clave) {
            $query->where('clave', $clave);
        }

        $modulos = $query->get();

        $resultado = [];

        foreach ($modulos as $modulo) {

            $rol  = $modulo->roles->first();
            $user = $modulo->usuarios->first();

            $resultado[$modulo->clave] = [
                'nombre' => $modulo->nombre ?? 'Modulo',
                'ruta'   => $modulo->ruta ?? '#',
                'icono'  => $modulo->icono ?: 'ti ti-box',

                // 🔒 Permisos (prioridad: usuario > rol > 0)
                'leer' => $user && $user->pivot ? $user->pivot->leer :
                          ($rol && $rol->pivot ? $rol->pivot->leer : 0),

                'crear' => $user && $user->pivot ? $user->pivot->crear :
                           ($rol && $rol->pivot ? $rol->pivot->crear : 0),

                'editar' => $user && $user->pivot ? $user->pivot->editar :
                            ($rol && $rol->pivot ? $rol->pivot->editar : 0),

                'eliminar' => $user && $user->pivot ? $user->pivot->eliminar :
                              ($rol && $rol->pivot ? $rol->pivot->eliminar : 0),

                'descargar' => $user && $user->pivot ? $user->pivot->descargar :
                               ($rol && $rol->pivot ? $rol->pivot->descargar : 0),
            ];
        }

        return $resultado;
    }

    /**
     *Obtener SOLO UN módulo (más limpio para controllers)
     */
    public static function getPermiso($usuario_id, $clave)
    {
        $data = self::getPermisos($usuario_id, $clave);
        return $data[$clave] ?? [];
    }

    /**
     * Guardar todos los permisos en sesión (opcional PRO)
     */
    public static function guardarEnSesion($usuario_id)
    {
        $_SESSION['permisos'] = self::getPermisos($usuario_id);
    }

    /**
     * Obtener permisos desde sesión (rápido)
     */
    public static function permisosSesion($clave = null)
    {
        $permisos = $_SESSION['permisos'] ?? [];

        if ($clave) {
            return $permisos[$clave] ?? [];
        }

        return $permisos;
    }

    /**
     * Helper tipo Laravel → can()
     */
    public static function can($clave, $accion)
    {
        $permisos = $_SESSION['permisos'][$clave] ?? [];

        return !empty($permisos[$accion]);
    }
}