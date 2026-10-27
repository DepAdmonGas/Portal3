<?php

namespace App\Services;

use App\Models\Usuario;
use App\Models\ModuloDptoOperativo;
use App\Models\ModuloSubDptoOperativo;
use App\Models\ModuloSubUsuarioConfigDptoOperativo;

class ModuloDptoOperativoService
{

/*
|--------------------------------------------------------------------------
| RUTAS DE SUBMÓDULOS
|--------------------------------------------------------------------------
*/

/**
 * Ruta navegable de un submódulo.
 *
 * Varios submódulos necesitan el año (y el mes) al final de su ruta: la que
 * se guarda en modulos_sub_dpto_operativo no es navegable tal cual. Este
 * mapa vivía dentro de DptoOperativoController::renderModulo(); se movió
 * aquí para que el menú de cada módulo y los accesos directos de
 * /departamento-operativo generen exactamente la misma URL.
 *
 * @param array $submenu Submódulo con al menos 'clave' y 'ruta'.
 */
public static function resolverRutaSubmodulo(array $submenu)
{
$idYear = date('Y');
$idMes  = date('n');

$sufijos = [
'corte-diario'      => "/{$idYear}/{$idMes}",
'aceites-mes'        => "/{$idYear}/{$idMes}",
'resumen-monedero'   => "/{$idYear}/{$idMes}",
'embarques'         => "/{$idYear}/{$idMes}",
'solicitud-cheque'  => "/{$idYear}/{$idMes}",
'ingresos-facturacion' => "/{$idYear}",
'despacho-ventas'   => "/{$idYear}/{$idMes}",
'comparativo-xml'   => "/{$idYear}",
'aclaracion-voucher'   => "/{$idYear}/{$idMes}",
'solicitud-vales'   => "/{$idYear}/{$idMes}",
'factura-monedero'   => "/{$idYear}/{$idMes}",
'incidencias-nomina'   => "/{$idYear}",
'bitacora-rrhh'   => "/{$idYear}/{$idMes}",
'formato-descarga-merma'         => "/{$idYear}/{$idMes}",
'analisis-compra'         => "/{$idYear}/{$idMes}",
'inventarios-diarios'         => "/{$idYear}/{$idMes}",
'recibos-nomina'         => "/{$idYear}",
'cuenta-litros'          => "/{$idYear}/{$idMes}",
'precios-diarios-combustible'          => "/{$idYear}/{$idMes}",
'orden-compra'          => "/{$idYear}/{$idMes}",
];

$sufijo = $sufijos[$submenu['clave']] ?? '';

return (string)($submenu['ruta'] ?? '') . $sufijo;
}

/* ---------- VOBTENER TODOS LOS MODULOS O UNO EN ESPECIFICO ----------*/
public static function getPermisos($usuario_id, $clave = null)
{
$usuario = Usuario::find($usuario_id);

if (!$usuario) {
return [];
}

/* ---------- VERIFICAR PRIORIDAD: USUARIO > PUESTO ----------*/
$tienePermisosUsuario = ModuloDptoOperativo::whereHas(
'usuarios',
function ($q) use ($usuario_id) {
$q->where('id_usuario', $usuario_id);
}
)->exists();

// La configuración explícita resuelve submódulos por módulo. Acceso Temporal
// conserva su resolver independiente y el permiso principal de Recursos
// Humanos se mantiene en el esquema legado para no alterar Checador.
$modosPorModulo = [];
$modulosProtegidos = ModuloDptoOperativo::query()
    ->where('clave', 'acceso-temporal')
    ->pluck('id')
    ->map(static fn ($id) => (int) $id)
    ->all();
$configModel = new ModuloSubUsuarioConfigDptoOperativo();
if ($configModel->getConnection()->getSchemaBuilder()->hasTable($configModel->getTable())) {
    $configuraciones = ModuloSubUsuarioConfigDptoOperativo::query()
        ->where('id_usuario', $usuario_id)
        ->get(['id_modulo', 'modo']);

    if ($configuraciones->isNotEmpty()) {
        foreach ($configuraciones as $configuracion) {
            $idModuloConfigurado = (int) $configuracion->id_modulo;
            if (!in_array($idModuloConfigurado, $modulosProtegidos, true)) {
                $modosPorModulo[$idModuloConfigurado] = $configuracion->modo;
            }
        }
    }
}

// Una asignación individual de submódulo ya expresa una selección personal.
// Si no hay modo explícito, se toma como personalizada para ese módulo;
// el modo actual permite conservar de forma intencional la regla histórica.
$idsConSubmodulosUsuario = ModuloSubDptoOperativo::query()
    ->whereHas('usuarios', static function ($query) use ($usuario_id) {
        $query->where('id_usuario', $usuario_id);
    })
    ->pluck('id_modulo')
    ->map(static fn ($id) => (int) $id)
    ->unique()
    ->all();

foreach ($idsConSubmodulosUsuario as $idModuloConfigurado) {
    if (!in_array($idModuloConfigurado, $modulosProtegidos ?? [], true)
        && !array_key_exists($idModuloConfigurado, $modosPorModulo)) {
        $modosPorModulo[$idModuloConfigurado] = 'personalizar';
    }
}

$modulosConModoEspecifico = array_keys(array_filter(
    $modosPorModulo,
    static fn ($modo) => in_array($modo, ['heredar', 'personalizar'], true)
));

$query = ModuloDptoOperativo::with([

'roles' => function ($q) use ($usuario) {
$q->where('id_puesto', $usuario->id_puesto);
},

'usuarios' => function ($q) use ($usuario_id) {
$q->where('id_usuario', $usuario_id);
},

'submenus.roles' => function ($q) use ($usuario) {
$q->where('id_puesto', $usuario->id_puesto);
},

'submenus.usuarios' => function ($q) use ($usuario_id) {
$q->where('id_usuario', $usuario_id);
}

]);


/* ---------- FILTRAR POR MODULO SI VIENE LA "CLAVE" ----------*/
if ($clave) {
$query->where('clave', $clave);
}

/* ---------- APLICAR LA PRIORIDAD ----------*/
$query->where(function ($accessQuery) use ($tienePermisosUsuario, $usuario_id, $usuario, $modosPorModulo, $modulosConModoEspecifico) {
    if ($tienePermisosUsuario) {
        $accessQuery->whereHas('usuarios', function ($q) use ($usuario_id) {
            $q->where('id_usuario', $usuario_id);
        });
    } else {
        $accessQuery->whereHas('roles', function ($q) use ($usuario) {
            $q->where('id_puesto', $usuario->id_puesto);
        });
    }

    if ($modulosConModoEspecifico !== []) {
        $accessQuery->orWhere(function ($configuredQuery) use ($modulosConModoEspecifico, $usuario_id, $usuario) {
            $configuredQuery->whereIn('id', $modulosConModoEspecifico)
                ->where(function ($grantQuery) use ($usuario_id, $usuario) {
                    $grantQuery->whereHas('usuarios', function ($q) use ($usuario_id) {
                        $q->where('id_usuario', $usuario_id);
                    })->orWhereHas('roles', function ($q) use ($usuario) {
                        $q->where('id_puesto', $usuario->id_puesto);
                    });
                });
        });
    }
});

$modulos = $query->orderBy('id', 'asc')->get();
$resultado = [];

foreach ($modulos as $modulo) {

$rol  = $modulo->roles->first();
$user = $modulo->usuarios->first();
$modoConfigurado = $modulosProtegidos !== []
    && in_array((int) $modulo->id, $modulosProtegidos, true)
        ? null
        : ($modosPorModulo[$modulo->id] ?? null);
$modoSubmodulos = in_array($modoConfigurado, ['heredar', 'personalizar'], true)
    ? $modoConfigurado
    : null;

// Los módulos con modo explícito resuelven el permiso principal por módulo:
// usuario, si existe; de lo contrario, puesto. Sin modo se conserva la regla
// global histórica para no cambiar asignaciones existentes.
$permisoModulo = $modulo->clave !== 'recursos-humanos' && $modoSubmodulos !== null
    ? ($user ?: $rol)
    : ($tienePermisosUsuario ? $user : $rol);

if ($modoSubmodulos !== null && !$permisoModulo) {
    continue;
}

/* ---------- SUBMENUS ----------*/
$submenus = [];

foreach ($modulo->submenus as $submenu) {

$esSubmoduloProtegido = in_array($submenu->clave, ['checador', 'acceso-temporal'], true);
$permisoSub = $esSubmoduloProtegido
    ? ($tienePermisosUsuario ? $submenu->usuarios->isNotEmpty() : $submenu->roles->isNotEmpty())
    : match ($modoSubmodulos) {
        'personalizar' => $submenu->usuarios->isNotEmpty(),
        'heredar' => $submenu->roles->isNotEmpty(),
        default => $tienePermisosUsuario
            ? $submenu->usuarios->isNotEmpty()
            : $submenu->roles->isNotEmpty(),
    };

if (!$permisoSub) {
continue;
}

$submenus[] = [

'id_sub_modulo' => $submenu->id,
'nombre' => $submenu->nombre,
'clave' => $submenu->clave,
'ruta' => $submenu->ruta,
'icono' => $submenu->icono ?: 'ti ti-layout-grid'

];
}

/* ---------- RESULTADO DEL MENU ----------*/
$resultado[$modulo->clave] = [

'id_modulo' => $modulo->id,
'nombre' => $modulo->nombre ?? 'Modulo',
'ruta'   => $modulo->ruta ?? '#',
'icono'  => $modulo->icono ?: 'ti ti-layout-grid',


/* ---------- 🔒 Permisos (usuario > puesto > 0) ----------*/
'leer' => $permisoModulo && $permisoModulo->pivot ? $permisoModulo->pivot->leer : 0,

'crear' => $permisoModulo && $permisoModulo->pivot ? $permisoModulo->pivot->crear : 0,

'editar' => $permisoModulo && $permisoModulo->pivot ? $permisoModulo->pivot->editar : 0,

'eliminar' => $permisoModulo && $permisoModulo->pivot ? $permisoModulo->pivot->eliminar : 0,

'descargar' => $permisoModulo && $permisoModulo->pivot ? $permisoModulo->pivot->descargar : 0,

'submenus' => $submenus
];
}

return $resultado;
}

/* ---------- OBTENER SOLO UN MODULO ----------*/
public static function getPermiso($usuario_id, $clave)
{
$data = self::getPermisos($usuario_id, $clave);
return $data[$clave] ?? [];
}

/**
 * Resolve Temporary Access permissions from the database on every call.
 * This intentionally preserves the existing global user-over-role precedence
 * without relying on the session permission cache.
 */
public static function getTemporaryAccessPermissions(int $usuarioId, string $clave = 'acceso-temporal'): array
{
$denied = [
    'leer' => 0,
    'crear' => 0,
    'editar' => 0,
    'eliminar' => 0,
    'descargar' => 0,
];

try {
    $usuario = Usuario::query()
        ->where('id', $usuarioId)
        ->where('estatus', 0)
        ->first();

    if (!$usuario) {
        return $denied;
    }

    $modulo = ModuloDptoOperativo::query()
        ->where('clave', $clave)
        ->where('activo', 1)
        ->first();

    if (!$modulo) {
        return $denied;
    }

    $tienePermisosUsuario = ModuloDptoOperativo::whereHas(
        'usuarios',
        static function ($query) use ($usuarioId): void {
            $query->where('id_usuario', $usuarioId);
        }
    )->exists();

    $grant = $tienePermisosUsuario
        ? $modulo->usuarios()->where('id_usuario', $usuarioId)->first()
        : $modulo->roles()->where('id_puesto', $usuario->id_puesto)->first();

    if (!$grant || !$grant->pivot) {
        return $denied;
    }

    $flags = [];
    foreach (array_keys($denied) as $flag) {
        $flags[$flag] = (int) ($grant->pivot->{$flag} ?? 0) === 1 ? 1 : 0;
    }

    return $flags;
} catch (\Throwable $exception) {
    // Dependency failures must never turn into an allow decision.
    return $denied;
}
}


/* ---------- GUARDAR PERMISOS EN SESION ----------*/
public static function guardarEnSesion($usuario_id)
{
$_SESSION['permisos_do'] = self::getPermisos($usuario_id);
}

/* ---------- OBTENER PERMISOS DESDE SESION ----------*/
public static function permisosSesion($clave = null)
{
$permisos = $_SESSION['permisos_do'] ?? [];

if ($clave) {
return $permisos[$clave] ?? [];
}

return $permisos;
}

/* ---------- HELPERS ----------*/
public static function can($clave, $accion)
{
$permisos = $_SESSION['permisos_do'][$clave] ?? [];
return !empty($permisos[$accion]);
}


/* ---------- VALIDAR LOS PERMISOS ----------*/
public static function validaPermiso($modulo, $accion)
{
$permisos = self::permisosSesion($modulo);

if (empty($permisos)) {
$usuario_id = ($_SESSION['usuario']['id'] ?? 0);
if ($usuario_id) {
self::guardarEnSesion($usuario_id);
$permisos = self::permisosSesion($modulo);
}
}

return !empty($permisos[$accion]);
}

}
