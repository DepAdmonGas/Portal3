<?php

namespace App\Services;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Menú lateral de Departamento Operativo.
 *
 * Decide qué entra al sidebar de /departamento-operativo combinando:
 *
 *   1. Los permisos efectivos que ya resuelve ModuloDptoOperativoService
 *      (modulos_puestos_do / modulos_usuarios_do y sus equivalentes de
 *      submódulos). Este servicio NO reimplementa permisos.
 *   2. La configuración de presentación de modulos_sidebar_do
 *      (qué submódulos se listan, su orden y cuáles son obligatorios).
 *   3. Las excepciones por usuario de modulos_sidebar_do_usuarios.
 *
 * Regla de visualización, evaluada módulo por módulo:
 *
 *   A = submódulos autorizados y configurados como visibles
 *   T = submódulos habilitados del módulo
 *
 *   - Si hay submódulos obligatorios concedidos, se listan esos (y no la
 *     tarjeta del módulo, para no duplicar).
 *   - Si A = 0            -> no se pinta nada de ese módulo.
 *   - Si A <= MAX_LATERAL -> se listan los A submódulos.
 *   - Si A >  MAX_LATERAL -> se pinta solo el módulo.
 */
class MenuDptoOperativoService
{
    /**
     * Máximo de submódulos que se listan sueltos en el sidebar de un mismo
     * módulo. Por encima de este número conviene mostrar el módulo y entrar
     * a su menú. Es una constante centralizada: cambiarla aquí basta.
     */
    public const MAX_LATERAL = 6;

    /**
     * Grupos del sidebar listos para pintar.
     *
     * @return array [ ['nombre' => string, 'items' => [...]], ... ]
     *                Cada item: id, nombre, ruta, icono, tipo.
     */
    public static function paraUsuario(int $usuarioId): array
    {
        $modulosPermitidos = ModuloDptoOperativoService::getPermisos($usuarioId);

        if (empty($modulosPermitidos)) {
            return [];
        }

        $config    = self::configuracion();
        $excepcion = self::excepcionesUsuario($usuarioId);

        $grupos = [];

        foreach ($modulosPermitidos as $clave => $modulo) {

            $autorizados = $modulo['submenus'] ?? [];

            if (empty($autorizados)) {
                continue;
            }

            $idModulo = (int)($modulo['id_modulo'] ?? 0);

            $visibles = self::visibles($autorizados, $config, $excepcion);

            if (empty($visibles)) {
                continue;
            }

            $obligatorios = array_values(array_filter(
                $visibles,
                fn ($s) => !empty($config[$s['id_sub_modulo']]['obligatorio'])
            ));

            if (!empty($obligatorios)) {

                foreach ($obligatorios as $sub) {
                    self::agregar($grupos, $config, $sub, $modulo, 'submodulo');
                }

                continue;
            }

            if (count($visibles) <= self::MAX_LATERAL) {

                foreach ($visibles as $sub) {
                    self::agregar($grupos, $config, $sub, $modulo, 'submodulo');
                }

                continue;
            }

            self::agregar($grupos, $config, null, $modulo, 'modulo');
        }

        return array_values($grupos);
    }

    /**
     * Submódulos autorizados que además están configurados como visibles.
     */
    private static function visibles(
        array $autorizados,
        array $config,
        array $excepcion
    ): array {

        $salida = [];

        foreach ($autorizados as $sub) {

            $idSub = (int)($sub['id_sub_modulo'] ?? 0);

            // Excepción individual: manda sobre todo lo demás.
            if (array_key_exists($idSub, $excepcion)) {
                if (empty($excepcion[$idSub]['visible'])) {
                    continue;
                }
            } elseif (empty($config[$idSub]['activo'])) {
                // Sin excepción, debe estar activo en la configuración.
                continue;
            }

            $salida[] = $sub;
        }

        // Orden: excepción individual > orden de configuración > id.
        usort($salida, function ($a, $b) use ($config, $excepcion) {

            $ia = (int)($a['id_sub_modulo'] ?? 0);
            $ib = (int)($b['id_sub_modulo'] ?? 0);

            $oa = $excepcion[$ia]['orden']
                ?? ($config[$ia]['orden'] ?? $ia);
            $ob = $excepcion[$ib]['orden']
                ?? ($config[$ib]['orden'] ?? $ib);

            return [$oa, $ia] <=> [$ob, $ib];
        });

        return $salida;
    }

    /** Añade un elemento al sidebar. */
    private static function agregar(
        array &$grupos,
        array $config,
        ?array $sub,
        array $modulo,
        string $tipo
    ): void {

        if ($tipo === 'modulo') {

            $idModulo = (int)($modulo['id_modulo'] ?? 0);

            $item = [
                'id'     => 'm' . $idModulo,
                'nombre' => (string)($modulo['nombre'] ?? ''),
                'ruta'   => (string)($modulo['ruta'] ?? '#'),
                'icono'  => (string)($modulo['icono'] ?: 'ti ti-layout-grid'),
                'tipo'   => 'modulo',
            ];

        } else {

            $idSub = (int)($sub['id_sub_modulo'] ?? 0);
            $cfg   = $config[$idSub] ?? [];

            $item = [
                'id'     => 's' . $idSub,
                'nombre' => (string)($cfg['etiqueta'] ?? '')
                    ?: (string)($sub['nombre'] ?? ''),
                'ruta'   => self::resolverRutaSidebar($sub),
                'icono'  => (string)($cfg['icono'] ?? '')
                    ?: (string)($sub['icono'] ?: 'ti ti-layout-grid'),
                'tipo'   => 'submodulo',
                'padre'  => (string)($modulo['nombre'] ?? ''),
            ];
        }

        // El sidebar de Departamento Operativo tiene un único encabezado.
        if (!isset($grupos[self::GRUPO])) {
            $grupos[self::GRUPO] = [
                'nombre' => self::GRUPO,
                'items' => [],
            ];
        }

        $grupos[self::GRUPO]['items'][] = $item;
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

    /**
     * Configuración del sidebar indexada por id de submódulo.
     */
    private static function configuracion(): array
    {
        $filas = Capsule::table('modulos_sidebar_do')->get();

        $mapa = [];

        foreach ($filas as $f) {
            $mapa[(int)$f->id_sub_modulo] = [
                'id_sub_modulo' => (int)$f->id_sub_modulo,
                'id_modulo_do'  => (int)$f->id_modulo_do,
                'etiqueta'      => $f->etiqueta,
                'icono'         => $f->icono,
                'orden'         => (int)$f->orden,
                'activo'        => (int)$f->activo === 1,
                'obligatorio'   => (int)$f->obligatorio === 1,
            ];
        }

        return $mapa;
    }

    /**
     * Excepciones individuales indexadas por id de submódulo.
     */
    private static function excepcionesUsuario(int $usuarioId): array
    {
        $filas = Capsule::table('modulos_sidebar_do_usuarios')
            ->where('id_usuario', $usuarioId)
            ->get();

        $mapa = [];

        foreach ($filas as $f) {
            $mapa[(int)$f->id_sub_modulo] = [
                'visible' => (int)$f->visible === 1,
                'orden'   => (int)$f->orden,
            ];
        }

        return $mapa;
    }
}
