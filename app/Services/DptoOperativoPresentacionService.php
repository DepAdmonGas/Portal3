<?php

namespace App\Services;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Presentación de los accesos en /departamento-operativo.
 *
 * Decide, POR CADA MÓDULO y de forma independiente, si conviene mostrar la
 * tarjeta del módulo completo o los submódulos autorizados como accesos
 * directos. NO resuelve permisos: recibe los módulos ya resueltos por
 * ModuloDptoOperativoService::getPermisos().
 *
 * Regla (evaluada módulo por módulo):
 *   A = submódulos autorizados del usuario en el módulo
 *   T = submódulos habilitados (activo = 1) del módulo
 *
 *   A = 0              -> no se pinta nada de ese módulo
 *   A >= T             -> tarjeta del MÓDULO completo
 *   A <= MAX_DIRECTOS  -> los A submódulos como accesos directos
 *   en otro caso        -> tarjeta del MÓDULO
 */
class DptoOperativoPresentacionService
{
    /**
     * Máximo de submódulos que se muestran sueltos en la pantalla principal
     * de Departamento Operativo. A partir de esta cantidad conviene más
     * conservar la tarjeta del módulo y entrar a su menú.
     */
    public const MAX_DIRECTOS = 2;

    /**
     * Convierte los módulos permitidos en la lista plana de accesos a pintar.
     *
     * @param array $modulosPermitidos Salida de
     *        ModuloDptoOperativoService::getPermisos().
     *
     * @return array Lista de accesos con:
     *         tipo   => 'modulo' | 'submodulo'
     *         id     => int
     *         nombre => string
     *         ruta   => string (ya resuelta, con año/mes si aplica)
     *         icono  => string
     *         padre  => ?string (nombre del módulo, para el subtítulo)
     */
    public static function paraModulos(array $modulosPermitidos): array
    {
        if (empty($modulosPermitidos)) {
            return [];
        }

        $totales = self::totalesPorModulo();
        $accesos = [];

        foreach ($modulosPermitidos as $clave => $modulo) {

            $autorizados = $modulo['submenus'] ?? [];
            $cantidad    = count($autorizados);

            if ($cantidad === 0) {
                continue;
            }

            $idModulo = (int)($modulo['id_modulo'] ?? 0);
            $total    = (int)($totales[$idModulo] ?? 0);

            $accesoCompleto = ($total > 0 && $cantidad >= $total);

            if (!$accesoCompleto && $cantidad <= self::MAX_DIRECTOS) {

                foreach (self::sinDuplicados($autorizados) as $submodulo) {
                    $accesos[] = self::accesoSubmodulo($submodulo, $modulo);
                }

                continue;
            }

            $accesos[] = self::accesoModulo($modulo);
        }

        return $accesos;
    }

    /**
     * Submódulos habilitados (activo = 1) agrupados por id de módulo.
     *
     * Es metadata de catálogo, no autorización: solo se usa para saber si el
     * usuario ya tiene todos los submódulos del módulo.
     */
    private static function totalesPorModulo(): array
    {
        $filas = Capsule::table('modulos_sub_dpto_operativo')
            ->where('activo', 1)
            ->groupBy('id_modulo')
            ->get(['id_modulo', Capsule::raw('COUNT(*) AS total')]);

        $totales = [];

        foreach ($filas as $fila) {
            $totales[(int)$fila->id_modulo] = (int)$fila->total;
        }

        return $totales;
    }

    /**
     * Elimina submódulos repetidos por clave dentro del mismo módulo.
     *
     * Históricamente existieron dos filas con la misma clave y la misma ruta;
     * si vuelve a ocurrir, la pantalla las pintaría duplicadas. Se conserva
     * la primera aparición, que es la que respeta el orden del menú.
     */
    private static function sinDuplicados(array $submodulos): array
    {
        $vistos  = [];
        $limpios = [];

        foreach ($submodulos as $submodulo) {

            $clave = (string)($submodulo['clave'] ?? '');

            if (isset($vistos[$clave])) {
                continue;
            }

            $vistos[$clave] = true;
            $limpios[]      = $submodulo;
        }

        return $limpios;
    }

    /** Acceso a un módulo completo: igual al menú actual. */
    private static function accesoModulo(array $modulo): array
    {
        return [
            'tipo'   => 'modulo',
            'id'     => (int)($modulo['id_modulo'] ?? 0),
            'nombre' => (string)($modulo['nombre'] ?? ''),
            'ruta'   => (string)($modulo['ruta'] ?? '#'),
            'icono'  => (string)($modulo['icono'] ?: 'ti ti-layout-grid'),
            'padre'  => null,
        ];
    }

    /** Acceso directo a un submódulo, con la ruta ya resuelta. */
    private static function accesoSubmodulo(array $submodulo, array $modulo): array
    {
        return [
            'tipo'   => 'submodulo',
            'id'     => (int)($submodulo['id_sub_modulo'] ?? 0),
            'nombre' => (string)($submodulo['nombre'] ?? ''),
            'ruta'   => ModuloDptoOperativoService::resolverRutaSubmodulo($submodulo),
            'icono'  => (string)($submodulo['icono'] ?: 'ti ti-layout-grid'),
            'padre'  => (string)($modulo['nombre'] ?? ''),
        ];
    }
}
