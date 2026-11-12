<?php

namespace App\Services;

use App\Core\Auth;
use App\Models\Usuario;

/**
 * Menú lateral de Departamento Operativo.
 *
 * Adapta al sidebar los permisos efectivos que ya resuelve
 * ModuloDptoOperativoService (modulos_puestos_do / modulos_usuarios_do y sus
 * equivalentes de submódulos). No mantiene una segunda ACL ni depende de
 * tablas de configuración de presentación.
 */
class MenuDptoOperativoService
{
    /**
     * Grupos del sidebar listos para pintar.
     *
     * @return array [ ['nombre' => string, 'items' => [...]], ... ]
     *                Cada item: id, nombre, ruta, icono, tipo.
     */
    public static function paraUsuario(int $usuarioId): array
    {
        if ($usuarioId < 1 || !Usuario::query()
            ->where('id', $usuarioId)
            ->where('estatus', 0)
            ->exists()) {
            return [];
        }

        $modulosPermitidos = ModuloDptoOperativoService::getPermisos($usuarioId);

        if (empty($modulosPermitidos)) {
            return [];
        }

        $items = [];

        foreach ($modulosPermitidos as $modulo) {
            if ((int) ($modulo['leer'] ?? 0) !== 1) {
                continue;
            }

            $autorizados = $modulo['submenus'] ?? [];
            if (empty($autorizados)) {
                continue;
            }

            usort($autorizados, static fn(array $a, array $b): int =>
                (int) ($a['id_sub_modulo'] ?? 0) <=> (int) ($b['id_sub_modulo'] ?? 0)
            );

            foreach ($autorizados as $sub) {
                if (($sub['clave'] ?? '') === 'checador') {
                    $authenticated = Auth::user();
                    if (!$authenticated
                        || (int) ($authenticated->id ?? 0) !== $usuarioId
                        || !(ChecadorAccessService::authorize()['allowed'] ?? false)) {
                        continue;
                    }
                }

                $ruta = self::resolverRutaSidebar($sub);
                if ($ruta === '' || $ruta === '#') {
                    continue;
                }

                $items[] = [
                    'id' => 's' . (int) ($sub['id_sub_modulo'] ?? 0),
                    'nombre' => (string) ($sub['nombre'] ?? ''),
                    'ruta' => $ruta,
                    'icono' => (string) ($sub['icono'] ?? '') ?: 'ti ti-layout-grid',
                    'tipo' => 'submodulo',
                    'padre' => (string) ($modulo['nombre'] ?? ''),
                ];
            }
        }

        if ($items === []) {
            return [];
        }

        return [[
            'nombre' => self::GRUPO,
            'items' => $items,
        ]];
    }

    /**
     * Encabezado único del sidebar de Departamento Operativo.
     */
    public const GRUPO = 'DIRECCIÓN DE OPERACIONES';

    /**
     * Submódulos cuya ruta exige el periodo en la URL porque NO tienen una
     * ruta base sin periodo. Para el resto se usa la ruta tal cual está en
     * el catálogo: Portal3 ya redirecta o resuelve el periodo vigente.
     *
     * Verificado contra routes/web.php: solo estos cinco responden 404 si se
     * accede sin año (o año y mes, según el caso).
     */
    private const PERIODO_OBLIGATORIO = [
        'embarques'            => 'anio-mes',
        'orden-compra'         => 'anio-mes',
        'bitacora-rrhh'        => 'anio-mes',
        'recibos-nomina'       => 'anio',
        'incidencias-nomina'   => 'anio',
    ];

    /**
     * Ruta del sidebar para un submódulo.
     *
     * No usa ModuloDptoOperativoService::resolverRutaSubmodulo() porque ese
     * método agrega el periodo a 18 submódulos, y 13 de ellos ya tienen una
     * ruta propia sin periodo (algunos redirectan, otros no lo usan).
     * Aquí solo se agrega el periodo cuando la ruta lo exige.
     */
    private static function resolverRutaSidebar(array $sub): string
    {
        $ruta = (string)($sub['ruta'] ?? '');

        if ($ruta === '') {
            return '#';
        }

        $clave = (string)($sub['clave'] ?? '');
        $forma = self::PERIODO_OBLIGATORIO[$clave] ?? null;

        if ($forma === null) {
            return $ruta;
        }

        $anio = date('Y');
        $mes  = date('n');

        return $forma === 'anio'
            ? $ruta . '/' . $anio
            : $ruta . '/' . $anio . '/' . $mes;
    }

}
