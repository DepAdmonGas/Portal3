<?php

namespace App\Services;

use App\Models\Estacion;
use App\Models\Sasisopa\ReporteCreMes;
use App\Models\Sasisopa\ReporteCreMensaje;

/**
 * Reporte Estadístico de la CRE (dpto. operativo).
 *
 * Comportamiento tomado del módulo anterior del proyecto portal-sasisopa:
 *   - public/administrador/reporte-estadistico-index.php
 *   - public/administrador/vistas/lista-reporte-cre-year.php
 *   - public/administrador/vistas/lista-reporte-cre.php
 *   - public/administrador/vistas/descargar-facturas.php
 *
 * El módulo es de SOLO LECTURA más comentarios y descarga anual de facturas.
 * El alta/edición de reportes vive en otro módulo y no se replica aquí.
 *
 * Este Service es propio de la ruta /departamento-operativo/reporte-estadistico-cre
 * y NO modifica ni delega en ningún otro módulo existente.
 */
class ReporteEstadisticoCreService
{
    /** Clave en tb_modulos_config (selector de estación). */
    public const MODULE_KEY = 'reporte-estadistico-cre';

    /** Clave del módulo padre en modulos_dpto_operativo (permisos). */
    public const MODULO_PADRE = 'corporativo';

    public const BASE_URL = '/departamento-operativo/reporte-estadistico-cre';

    /** La BD arranca en 2019 (año más antiguo con datos CRE). */
    public const ANIO_INICIO = 2019;

    /** Periodos de factura: 1 = inicio de mes, 2 = mediados, 3 = final. */
    public const PERIODOS_FACTURA = [
        1 => ['clave' => 'f',  'titulo' => 'Factura'],
        2 => ['clave' => 'fi', 'titulo' => 'Factura inicial'],
        3 => ['clave' => 'ff', 'titulo' => 'Factura final'],
    ];

    /**
     * Resuelve la estación del contexto global y verifica que el usuario
     * esté autorizado. Devuelve null si no hay estación o no tiene acceso.
     */
    public static function estacionContextual(): ?int
    {
        $contexto = ModuleStationService::getContext(self::MODULE_KEY);
        $idEstacion = (int)($contexto['id_estacion'] ?? 0);

        if ($idEstacion <= 0) {
            return null;
        }

        // Backend real: la estación solicitada debe estar en la lista que el
        // sistema calcula para este usuario. Evita consultar estaciones ajenas.
        $autorizadas = ModuleStationService::getAvailableStations(self::MODULE_KEY);

        if (empty($autorizadas)) {
            return null;
        }

        $permitidas = array_map(static fn (array $e) => (int)$e['id'], $autorizadas);

        return in_array($idEstacion, $permitidas, true) ? $idEstacion : null;
    }

    /** Permisos efectivos del módulo para el usuario en sesión. */
    public static function getPermisos(): array
    {
        $db = ModuloDptoOperativoService::permisosSesion(self::MODULO_PADRE) ?: [];
        $idEstacion = self::estacionContextual();
        $contexto = ModuleStationService::getContext(self::MODULE_KEY);

        return [
            'id_estacion'    => $idEstacion,
            'estacion'       => (string)($contexto['nombre'] ?? ''),
            'puedeLeer'      => !empty($db['leer']) || !empty($db['crear']) || !empty($db['editar']),
            'puedeDescargar' => !empty($db['descargar']) || !empty($db['editar']),
            'puedeComentar'  => !empty($db['editar']) || !empty($db['crear']),
        ];
    }

    //======================================================================
    // CATÁLOGOS
    //======================================================================

    /**
     * Productos que maneja la estación, en el orden 1..3.
     * El módulo anterior armaba las columnas según cuantos productos tuviera.
     */
    public static function productosEstacion(int $idEstacion): array
    {
        $estacion = self::estacion($idEstacion);

        if (!$estacion) {
            return [];
        }

        $catalogo = [
            ['numero' => 1, 'nombre' => $estacion->producto_uno, 'color' => 'success'],
            ['numero' => 2, 'nombre' => $estacion->producto_dos, 'color' => 'danger'],
            ['numero' => 3, 'nombre' => $estacion->producto_tres, 'color' => 'dark'],
        ];

        return array_values(array_filter(
            $catalogo,
            static fn (array $p) => trim((string)$p['nombre']) !== ''
        ));
    }

    public static function estacion(int $idEstacion): ?Estacion
    {
        return Estacion::query()
            ->select(['id', 'nombre', 'razonsocial', 'producto_uno', 'producto_dos', 'producto_tres'])
            ->find($idEstacion);
    }

    /** Años que realmente tienen reportes CRE para la estación. */
    public static function aniosConDatos(int $idEstacion): array
    {
        return ReporteCreMes::query()
            ->where('id_estacion', $idEstacion)
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(static fn ($y) => (int)$y)
            ->all();
    }

    /**
     * Meses disponibles del año.
     *
     * El módulo anterior solo habilitaba los meses ya transcurridos: los meses
     * futuros del año en curso salían apagados y sin clic.
     */
    public static function mesesDisponibles(int $idEstacion, int $year): array
    {
        $meses = ReporteCreMes::query()
            ->where('id_estacion', $idEstacion)
            ->where('year', $year)
            ->distinct()
            ->orderBy('mes')
            ->pluck('mes')
            ->map(static fn ($m) => (int)$m)
            ->all();

        $anioActual = (int)date('Y');
        $mesActual  = (int)date('n');

        return array_map(static fn (int $mes) => [
            'mes'       => $mes,
            'nombre'    => nombremes($mes),
            'habilitado' => $year < $anioActual || $mes <= $mesActual,
        ], $meses);
    }

    //======================================================================
    // REPORTE MENSUAL
    //======================================================================

    /**
     * Estructura del reporte de un mes: encabezado por producto, días con
     * sus volúmenes, merma, pipas y conteo de comentarios.
     */
    public static function reporteMensual(int $idEstacion, int $year, int $mes): ?array
    {
        $estacion = self::estacion($idEstacion);

        if (!$estacion) {
            return null;
        }

        $reporte = ReporteCreMes::query()
            ->select([
                'id', 'id_estacion', 'year', 'mes',
                'f_producto_uno', 'f_producto_dos', 'f_producto_tres',
                'fi_producto_uno', 'fi_producto_dos', 'fi_producto_tres',
                'ff_producto_uno', 'ff_producto_dos', 'ff_producto_tres',
            ])
            ->with(['productos' => static function ($query) {
                $query->select([
                    'id', 'id_re_mes', 'fecha', 'producto',
                    'volumen_inicial', 'volumen_venta', 'volumen_final',
                ])->with(['pipas' => static function ($query) {
                    $query->select([
                        'id', 'id_re_producto', 'pipa_numero', 'volumen',
                        'precio_litro', 'costo_flete',
                        'nombre_razonsocial', 'importe_total',
                    ]);
                }]);
            }])
            ->where('id_estacion', $idEstacion)
            ->where('year', $year)
            ->where('mes', $mes)
            ->first();

        if (!$reporte) {
            return null;
        }

        $catalogo = self::productosEstacion($idEstacion);
        $conteosMensajes = self::conteoMensajesPorFecha([(int)$reporte->id]);

        $datos = $reporte->productos->groupBy(
            static fn ($producto) => $producto->fecha?->format('Y-m-d')
        );

        $dias = [];

        foreach ($datos as $fecha => $productos) {

            if (!$fecha) {
                continue;
            }

            $idFecha = strtotime($fecha);

            $celdas = [];

            // Se recorre el CATALOGO de la estación (no los renglones del día)
            // para conservar el orden y mostrar una celda por producto aunque
            // ese día no tenga captura para alguno de ellos.
            foreach ($catalogo as $productoCatalogo) {

                $registro = self::productoDelDia($productos, $productoCatalogo['nombre']);

                if (!$registro) {
                    continue;
                }

                $celdas[] = self::celdaProducto($registro);
            }

            $dias[] = [
                'id_reporte'    => (int)$reporte->id,
                'fecha'         => $fecha,
                'id_fecha'      => $idFecha,
                'dia'           => 'Día ' . (int)date('j', strtotime($fecha)),
                'fecha_label'   => formatearFecha($fecha),
                'total_mensajes' => $conteosMensajes[(int)$reporte->id][$idFecha] ?? 0,
                'productos'     => $celdas,
            ];
        }

        usort($dias, static fn ($a, $b) => strcmp($b['fecha'], $a['fecha']));

        return [
            'id'         => (int)$reporte->id,
            'estacion'   => $estacion->nombre,
            'year'       => $year,
            'mes'        => $mes,
            'mes_nombre' => nombremes($mes),
            'productos'  => self::productosConTotales($reporte, $catalogo),
            'dias'       => $dias,
        ];
    }

    /**
     * El módulo anterior consultaba el producto por nombre exacto dentro de
     * la fecha; si el nombre en BD difiere del catálogo de la estación la
     * celda desaparecía. Aquí se acepta la coincidencia sin distinguir
     * mayúsculas, espacios sobrantes o acentos.
     */
    private static function productoDelDia($productos, string $nombre): ?object
    {
        return $productos->first(
            static fn ($p) => self::normalizar((string)$p->producto) === self::normalizar($nombre)
        ) ?? null;
    }

    private static function normalizar(string $texto): string
    {
        $texto = trim($texto);
        $texto = (string)preg_replace('/\s+/u', ' ', $texto);

        return function_exists('mb_strtolower')
            ? mb_strtolower($texto, 'UTF-8')
            : strtolower($texto);
    }

    /** Conteo liviano de comentarios por reporte y día, sin leer sus textos. */
    private static function conteoMensajesPorFecha(array $idsReporte): array
    {
        if ($idsReporte === []) {
            return [];
        }

        $salida = [];
        $conteos = ReporteCreMensaje::query()
            ->select(['id_reporte', 'id_fecha'])
            ->selectRaw('COUNT(*) AS total')
            ->whereIn('id_reporte', $idsReporte)
            ->groupBy('id_reporte', 'id_fecha')
            ->get();

        foreach ($conteos as $conteo) {
            $salida[(int)$conteo->id_reporte][(int)$conteo->id_fecha] = (int)$conteo->total;
        }

        return $salida;
    }

    /** Fila de volúmenes + tabla de pipas de un producto en un día. */
    private static function celdaProducto(object $registro): array
    {
        $pipas = $registro->pipas;

        $totalCompra   = (float)$pipas->sum('volumen');
        $totalImporte  = (float)$pipas->sum('importe_total');

        // Fórmula del módulo anterior, sin modificar:
        // merma = (volumen_final + volumen_venta) - (volumen_inicial + compras)
        $merma = ((float)$registro->volumen_final + (float)$registro->volumen_venta)
            - ((float)$registro->volumen_inicial + $totalCompra);

        return [
            'id'             => (int)$registro->id,
            'producto'       => $registro->producto,
            'volumen_inicial' => (float)$registro->volumen_inicial,
            'volumen_venta'   => (float)$registro->volumen_venta,
            'volumen_final'  => (float)$registro->volumen_final,
            'total_compra'   => $totalCompra,
            'total_importe'  => $totalImporte,
            'merma'          => round($merma),
            'pipas'          => $pipas->map(static fn ($pipa) => [
                'id'                 => (int)$pipa->id,
                'pipa_numero'        => $pipa->pipa_numero,
                'volumen'            => (float)$pipa->volumen,
                'precio_litro'       => (float)$pipa->precio_litro,
                'costo_flete'        => (float)$pipa->costo_flete,
                'nombre_razonsocial' => $pipa->nombre_razonsocial,
                'importe_total'      => (float)$pipa->importe_total,
            ])->values()->all(),
        ];
    }

    /** Totales del mes por producto + facturas registradas. */
    private static function productosConTotales(ReporteCreMes $reporte, array $productos): array
    {
        $facturas = self::facturasDelMes($reporte);
        $salida = [];

        foreach ($productos as $producto) {

            $registros = $reporte->productos->filter(
                static fn ($p) => self::normalizar((string)$p->producto) === self::normalizar($producto['nombre'])
            );

            $totalVenta   = (float)$registros->sum('volumen_venta');
            $totalCompra  = (float)$registros->sum(fn ($p) => (float)$p->pipas->sum('volumen'));
            $totalImporte = (float)$registros->sum(fn ($p) => (float)$p->pipas->sum('importe_total'));

            $salida[] = [
                'numero'        => $producto['numero'],
                'nombre'        => $producto['nombre'],
                'color'         => $producto['color'],
                'total_venta'   => $totalVenta,
                'total_compra'  => $totalCompra,
                'total_importe' => $totalImporte,
                'documentos'    => self::documentosFactura($facturas[$producto['numero']] ?? [], $reporte),
            ];
        }

        return $salida;
    }

    /**
     * Las nueve columnas de factura del mes, agrupadas por producto.
     * f_ = factura, fi_ = factura inicial, ff_ = factura final.
     */
    private static function facturasDelMes(ReporteCreMes $reporte): array
    {
        $mapa = [
            1 => ['f' => 'f_producto_uno',  'fi' => 'fi_producto_uno',  'ff' => 'ff_producto_uno'],
            2 => ['f' => 'f_producto_dos',  'fi' => 'fi_producto_dos',  'ff' => 'ff_producto_dos'],
            3 => ['f' => 'f_producto_tres', 'fi' => 'fi_producto_tres', 'ff' => 'ff_producto_tres'],
        ];

        $salida = [];

        foreach ($mapa as $numero => $campos) {

            $periodos = [];

            foreach (self::PERIODOS_FACTURA as $periodo => $meta) {
                $periodos[$periodo] = [
                    'titulo' => $meta['titulo'],
                    'ruta'   => (string)($reporte->{$campos[$meta['clave']]} ?? ''),
                ];
            }

            $salida[$numero] = $periodos;
        }

        return $salida;
    }

    /**
     * Construye los enlaces de las facturas.
     *
     * El valor guardado en BD es una ruta relativa legacy (archivos/cre/x.pdf),
     * por eso solo se toma el nombre y se resuelve contra el directorio real.
     */
    private static function documentosFactura(array $periodos, ReporteCreMes $reporte): array
    {
        $documentos = [];

        foreach ($periodos as $periodo => $info) {

            if (trim($info['ruta']) === '') {
                continue;
            }

            $archivo = basename($info['ruta']);

            $documentos[] = [
                'periodo'  => $periodo,
                'titulo'   => $info['titulo'],
                'archivo'  => $archivo,
                'mes'      => (int)$reporte->mes,
                'year'     => (int)$reporte->year,
                'existe'   => self::resolverFactura($info['ruta']) !== null,
            ];
        }

        return $documentos;
    }

    //======================================================================
    // RESUMEN ANUAL
    //======================================================================

    /**
     * Reporte anual con el mismo detalle por día que la vista mensual.
     * Cada día conserva el id del reporte mensual para que los comentarios
     * sigan apuntando al registro correcto.
     */
    public static function reporteAnualDetallado(int $idEstacion, int $year): ?array
    {
        $estacion = self::estacion($idEstacion);

        if (!$estacion) {
            return null;
        }

        $reportes = ReporteCreMes::query()
            ->select([
                'id', 'id_estacion', 'year', 'mes',
                'f_producto_uno', 'f_producto_dos', 'f_producto_tres',
                'fi_producto_uno', 'fi_producto_dos', 'fi_producto_tres',
                'ff_producto_uno', 'ff_producto_dos', 'ff_producto_tres',
            ])
            ->with(['productos' => static function ($query) {
                $query->select([
                    'id', 'id_re_mes', 'fecha', 'producto',
                    'volumen_inicial', 'volumen_venta', 'volumen_final',
                ])->with(['pipas' => static function ($query) {
                    $query->select([
                        'id', 'id_re_producto', 'pipa_numero', 'volumen',
                        'precio_litro', 'costo_flete',
                        'nombre_razonsocial', 'importe_total',
                    ]);
                }]);
            }])
            ->where('id_estacion', $idEstacion)
            ->where('year', $year)
            ->orderBy('mes')
            ->get();

        if ($reportes->isEmpty()) {
            return null;
        }

        $catalogo = self::productosEstacion($idEstacion);
        $conteosMensajes = self::conteoMensajesPorFecha($reportes->modelKeys());
        $dias = [];
        $totales = [];

        foreach ($catalogo as $producto) {
            $totales[$producto['numero']] = [
                'total_venta' => 0.0,
                'total_compra' => 0.0,
                'total_importe' => 0.0,
            ];
        }

        foreach ($reportes as $reporte) {
            foreach ($catalogo as $producto) {
                $registros = $reporte->productos->filter(
                    static fn ($p) => self::normalizar((string)$p->producto) === self::normalizar($producto['nombre'])
                );

                $totales[$producto['numero']]['total_venta'] += (float)$registros->sum('volumen_venta');
                $totales[$producto['numero']]['total_compra'] += (float)$registros->sum(
                    static fn ($p) => (float)$p->pipas->sum('volumen')
                );
                $totales[$producto['numero']]['total_importe'] += (float)$registros->sum(
                    static fn ($p) => (float)$p->pipas->sum('importe_total')
                );
            }

            $porFecha = $reporte->productos->groupBy(
                static fn ($producto) => $producto->fecha?->format('Y-m-d')
            );

            foreach ($porFecha as $fecha => $productosDia) {
                if (!$fecha) {
                    continue;
                }

                $idFecha = strtotime($fecha);
                $celdas = [];

                foreach ($catalogo as $productoCatalogo) {
                    $registro = self::productoDelDia($productosDia, $productoCatalogo['nombre']);

                    if ($registro) {
                        $celdas[] = self::celdaProducto($registro);
                    }
                }

                $dias[] = [
                    'id_reporte' => (int)$reporte->id,
                    'fecha' => $fecha,
                    'id_fecha' => $idFecha,
                    'dia' => 'Día ' . (int)date('j', strtotime($fecha)),
                    'fecha_label' => formatearFecha($fecha),
                    'total_mensajes' => $conteosMensajes[(int)$reporte->id][$idFecha] ?? 0,
                    'productos' => $celdas,
                ];
            }
        }

        usort($dias, static fn ($a, $b) => strcmp($b['fecha'], $a['fecha']));

        $productos = [];
        foreach ($catalogo as $producto) {
            $facturas = self::facturasDelAnio($reportes, $producto['numero'], $year);
            $productos[] = array_merge($producto, $totales[$producto['numero']], [
                'documentos' => array_map(static fn (array $factura) => [
                    'periodo' => $factura['periodo'],
                    'titulo' => $factura['titulo'],
                    'archivo' => $factura['archivo'],
                    'mes' => $factura['mes'],
                    'mes_nombre' => $factura['mes_nombre'],
                    'year' => $factura['year'],
                    'existe' => $factura['existe'],
                ], $facturas),
            ]);
        }

        return [
            'id' => null,
            'estacion' => $estacion->nombre,
            'year' => $year,
            'mes' => null,
            'mes_nombre' => 'Todo el año',
            'productos' => $productos,
            'dias' => $dias,
        ];
    }

    /**
     * Totales del año por producto. Es la vista que el módulo anterior
     * mostraba al elegir un año (tarjetas "Total venta anual en litros").
     */
    public static function resumenAnual(int $idEstacion, int $year): array
    {
        $estacion = self::estacion($idEstacion);

        if (!$estacion) {
            return [];
        }

        $reportes = ReporteCreMes::query()
            ->with('productos.pipas')
            ->where('id_estacion', $idEstacion)
            ->where('year', $year)
            ->orderBy('mes')
            ->get();

        $salida = [];

        foreach (self::productosEstacion($idEstacion) as $producto) {

            $totalVenta = 0.0;
            $totalCompra = 0.0;
            $totalImporte = 0.0;

            foreach ($reportes as $reporte) {

                $registros = $reporte->productos->filter(
                    static fn ($p) => self::normalizar((string)$p->producto) === self::normalizar($producto['nombre'])
                );

                $totalVenta   += (float)$registros->sum('volumen_venta');
                $totalCompra  += (float)$registros->sum(fn ($p) => (float)$p->pipas->sum('volumen'));
                $totalImporte += (float)$registros->sum(fn ($p) => (float)$p->pipas->sum('importe_total'));
            }

            $salida[] = [
                'numero'        => $producto['numero'],
                'nombre'        => $producto['nombre'],
                'color'         => $producto['color'],
                'total_venta'   => $totalVenta,
                'total_compra'  => $totalCompra,
                'total_importe' => $totalImporte,
                'facturas'      => self::facturasDelAnio($reportes, $producto['numero'], $year),
            ];
        }

        return $salida;
    }

    private static function facturasDelAnio($reportes, int $numeroProducto, int $year): array
    {
        $mapa = [
            1 => ['f' => 'f_producto_uno',  'fi' => 'fi_producto_uno',  'ff' => 'ff_producto_uno'],
            2 => ['f' => 'f_producto_dos',  'fi' => 'fi_producto_dos',  'ff' => 'ff_producto_dos'],
            3 => ['f' => 'f_producto_tres', 'fi' => 'fi_producto_tres', 'ff' => 'ff_producto_tres'],
        ];

        $campos = $mapa[$numeroProducto] ?? [];
        $facturas = [];

        foreach ($reportes as $reporte) {

            foreach (self::PERIODOS_FACTURA as $periodo => $meta) {

                $ruta = (string)($reporte->{$campos[$meta['clave']]} ?? '');

                if (trim($ruta) === '') {
                    continue;
                }

                $archivo = basename($ruta);

                $facturas[] = [
                    'periodo' => $periodo,
                    'titulo'  => $meta['titulo'],
                    'mes'     => (int)$reporte->mes,
                    'mes_nombre' => nombremes((int)$reporte->mes),
                    'year'    => $year,
                    'archivo' => $archivo,
                    'existe'  => self::resolverFactura($ruta) !== null,
                ];
            }
        }

        usort($facturas, static function (array $a, array $b) {
            return [$b['year'], $b['mes'], $a['periodo']]
               <=> [$a['year'], $a['mes'], $b['periodo']];
        });

        return $facturas;
    }

    //======================================================================
    // COMENTARIOS
    //======================================================================

    public static function mensajes(int $idReporte, int $idFecha, int $idUsuario): array
    {
        return ReporteCreMensaje::query()
            ->with('usuario')
            ->where('id_reporte', $idReporte)
            ->where('id_fecha', $idFecha)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get()
            ->map(static fn ($mensaje) => self::transformarMensaje($mensaje, $idUsuario))
            ->values()
            ->all();
    }

    /** El reporte debe pertenecer a la estación del contexto ya validada. */
    public static function reportePerteneceAEstacion(int $idReporte, int $idEstacion): ?ReporteCreMes
    {
        return ReporteCreMes::query()
            ->where('id', $idReporte)
            ->where('id_estacion', $idEstacion)
            ->first();
    }

    public static function crearMensaje(int $idReporte, int $idFecha, int $idUsuario, string $texto): ?ReporteCreMensaje
    {
        $mensaje = ReporteCreMensaje::create([
            'id_reporte' => $idReporte,
            'id_fecha'  => $idFecha,
            'id_usuario' => $idUsuario,
            'fecha'     => date('Y-m-d H:i:s'),
            'mensaje'   => $texto,
            'tipo'      => 0,
        ]);

        return $mensaje->load('usuario');
    }

    private static function transformarMensaje(ReporteCreMensaje $mensaje, int $idUsuario): array
    {
        $nombre = trim((string)($mensaje->usuario?->nombre ?? 'Usuario'));
        $partes = preg_split('/\s+/', $nombre) ?: [];
        $corto  = implode(' ', array_slice($partes, 0, 2));

        $fecha = $mensaje->fecha;

        return [
            'id'           => (int)$mensaje->id,
            'nombre'       => $corto,
            'comentario'   => $mensaje->mensaje,
            'tipo'         => (int)$mensaje->tipo,
            'fecha_label'  => $fecha ? formatearFecha($fecha->format('Y-m-d')) : '',
            'hora_label'   => $fecha ? $fecha->format('h:i A') : '',
            'es_propio'    => (int)$mensaje->id_usuario === $idUsuario,
        ];
    }

    //======================================================================
    // ARCHIVOS
    //======================================================================

    /**
     * Directorio físico de las facturas CRE.
     *
     * La BD guarda rutas relativas al proyecto anterior (archivos/cre/x.pdf).
     * Se busca primero dentro de Portal3 y, si el archivo aún vive en el
     * proyecto anterior, se usa ese como respaldo para no romper la descarga
     * de documentos ya existentes sin necesidad de moverlos.
     */
    public static function directorioFacturas(): ?string
    {
        static $directorios = null;

        if ($directorios !== null) {
            return $directorios[0] ?? null;
        }

        $raizPortal = dirname(__DIR__, 2);
        $candidatos = [
            $raizPortal . '/public/uploads/archivos/cre/',
            dirname($raizPortal, 1) . '/portal-sasisopa/archivos/cre/',
        ];

        foreach ($candidatos as $candidato) {
            if (is_dir($candidato)) {
                $directorios = [$candidato];
                return $candidato;
            }
        }

        $directorios = [null];

        return null;
    }

    /**
     * Ubica un archivo de factura. Devuelve null si no existe en ninguno de
     * los directorios soportados, para no exponer enlaces rotos.
     */
    public static function resolverFactura(string $ruta): ?string
    {
        $archivo = basename(trim($ruta));

        if ($archivo === '') {
            return null;
        }

        $raizPortal = dirname(__DIR__, 2);
        $candidatos = [
            $raizPortal . '/public/uploads/archivos/cre/' . $archivo,
            dirname($raizPortal, 1) . '/portal-sasisopa/archivos/cre/' . $archivo,
        ];

        foreach ($candidatos as $candidato) {
            if (is_file($candidato)) {
                return $candidato;
            }
        }

        return null;
    }

    /** Ruta relativa que espera DownloadController para el tipo 'cre'. */
    public static function rutaLogicaFactura(string $ruta): string
    {
        return basename(trim($ruta));
    }

    //======================================================================
    // EXPORTACIÓN
    //======================================================================

    /**
     * Nombres de archivo de las facturas del año, agrupados por mes, para el
     * ZIP. Se replicate la estructura del módulo anterior: Mes/archivo.pdf.
     */
    public static function facturasParaZip(int $idEstacion, int $year): array
    {
        $reportes = ReporteCreMes::query()
            ->where('id_estacion', $idEstacion)
            ->where('year', $year)
            ->orderBy('mes')
            ->get();

        $porMes = [];

        foreach ($reportes as $reporte) {

            $mes = (int)$reporte->mes;

            $facturas = array_merge(
                [$reporte->f_producto_uno, $reporte->f_producto_dos, $reporte->f_producto_tres],
                [$reporte->fi_producto_uno, $reporte->fi_producto_dos, $reporte->fi_producto_tres],
                [$reporte->ff_producto_uno, $reporte->ff_producto_dos, $reporte->ff_producto_tres]
            );

            foreach ($facturas as $factura) {

                $ruta = trim((string)$factura);

                if ($ruta === '') {
                    continue;
                }

                $fisica = self::resolverFactura($ruta);

                if ($fisica === null) {
                    continue;
                }

                $porMes[$mes][] = [
                    'ruta'    => $fisica,
                    'archivo' => basename($ruta),
                ];
            }
        }

        return $porMes;
    }

    /** Nombre del ZIP: replicaba Facturas_CRE_{razonsocial}.zip */
    public static function nombreZip(Estacion $estacion, int $year): string
    {
        $razonSocial = trim((string)($estacion->razonsocial ?: $estacion->nombre));
        $razonSocial = trim((string)preg_replace('/[^a-zA-Z0-9_-]+/', '_', $razonSocial), '_');

        if ($razonSocial === '') {
            $razonSocial = 'Estacion_' . $estacion->id;
        }

        return 'Facturas_CRE_' . $razonSocial . '_' . $year . '.zip';
    }
}
