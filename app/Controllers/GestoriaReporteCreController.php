<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Request;
use App\Core\JsonResponse;
use App\Services\ModuloService;

use App\Models\Estacion;
use App\Models\Sasisopa\ReporteCreMes;
use App\Models\Sasisopa\ReporteCreMensaje;

class GestoriaReporteCreController extends BaseController
{
    protected string $modulo = 'gestoria';

    public function index(int $idEstacion)
    {
        $estacion = Estacion::find($idEstacion);
        $title = 'Reporte estadistico CRE' . ' (' . $estacion->nombre . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Gestoria', '/gestoria');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'idEstacion' => $idEstacion,
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',

                '/js/gestoria/reporte-cre/index.actions.init.js?v=1.6.0',
            ],
            'help' => false
        ];

        View::render('gestoria/reporte-cre', $data, 'main');
    }

    public function data(int $idEstacion): void
    {
        try {

            $year = (int) Request::input('year');
            $mes = (int) Request::input('mes');

            $estacion = Estacion::query()
                ->select([
                    'id',
                    'nombre',
                    'producto_uno',
                    'producto_dos',
                    'producto_tres',
                ])
                ->find($idEstacion);


            if (!$estacion) {

                JsonResponse::error(
                    'No se encontró la estación.'
                );

                return;
            }

            $yearActual = (int) date('Y');


            if (
                $year < 2019 ||
                $year > $yearActual
            ) {

                JsonResponse::error(
                    'El año seleccionado no es válido.'
                );

                return;
            }

            if (
                $mes < 0 ||
                $mes > 12
            ) {

                JsonResponse::error(
                    'El mes seleccionado no es válido.'
                );

                return;
            }

            if ($mes === 0) {

                $reportes = ReporteCreMes::query()
                    ->with([
                        'productos.pipas',
                        'mensajes',
                    ])
                    ->where(
                        'id_estacion',
                        $idEstacion
                    )
                    ->where(
                        'year',
                        $year
                    )
                    ->orderBy('mes')
                    ->get();


                if ($reportes->isEmpty()) {

                    JsonResponse::custom([
                        'data' => null
                    ]);

                    return;
                }


                $data =
                    $this->transformarReporteAnual(
                        $estacion,
                        $reportes,
                        $year
                    );


                JsonResponse::custom([
                    'data' => $data
                ]);

                return;
            }

            $reporte = ReporteCreMes::query()
                ->with([
                    'productos.pipas',
                    'mensajes',
                ])
                ->where(
                    'id_estacion',
                    $idEstacion
                )
                ->where(
                    'year',
                    $year
                )
                ->where(
                    'mes',
                    $mes
                )
                ->first();


            if (!$reporte) {

                JsonResponse::custom([
                    'data' => null
                ]);

                return;
            }


            $data =
                $this->transformarReporte(
                    $estacion,
                    $reporte
                );


            JsonResponse::custom([
                'data' => $data
            ]);
        } catch (\Throwable $e) {

            JsonResponse::error(
                'No fue posible cargar el reporte CRE.'
            );
        }
    }

    private function transformarReporteAnual(
        Estacion $estacion,
        $reportes,
        int $year
    ): array {

        $productosEstacion = [

            [
                'numero' => 1,
                'nombre' => $estacion->producto_uno,
                'color' => '#E21883',
            ],

            [
                'numero' => 2,
                'nombre' => $estacion->producto_dos,
                'color' => '#76C01E',
            ],

            [
                'numero' => 3,
                'nombre' => $estacion->producto_tres,
                'color' => '#5E1B8C',
            ],

        ];


        $productosEstacion = array_values(
            array_filter(
                $productosEstacion,
                fn($producto) =>
                !empty($producto['nombre'])
            )
        );

        $dias = [];
        $productosResumen = [];

        foreach (
            $productosEstacion
            as $productoEstacion
        ) {

            $nombreProducto =
                $productoEstacion['nombre'];


            $productosResumen[$nombreProducto] = [

                'numero' =>
                $productoEstacion['numero'],

                'nombre' =>
                $nombreProducto,

                'color' =>
                $productoEstacion['color'],

                'facturas' => [

                    'factura' =>
                    null,

                    'factura_inicial' =>
                    null,

                    'factura_final' =>
                    null,

                ],

                'documentos' =>
                [],

                'total_venta' =>
                0,

                'total_compra' =>
                0,

                'total_importe' =>
                0,

            ];
        }

        foreach (
            $reportes
            as $reporte
        ) {

            $mesTransformado =
                $this->transformarReporte(
                    $estacion,
                    $reporte
                );

            foreach (
                $mesTransformado['productos']
                as $producto
            ) {

                $nombre =
                    $producto['nombre'];


                if (
                    !isset(
                        $productosResumen[$nombre]
                    )
                ) {

                    continue;
                }

                $productosResumen[$nombre]['total_venta'] +=
                    (float)
                    $producto['total_venta'];


                $productosResumen[$nombre]['total_compra'] +=
                    (float)
                    $producto['total_compra'];


                $productosResumen[$nombre]['total_importe'] +=
                    (float)
                    $producto['total_importe'];

                foreach (
                    $producto['documentos'] ?? []
                    as $documento
                ) {

                    $productosResumen[$nombre]['documentos'][] =
                        $documento;
                }
            }


            foreach (
                $mesTransformado['dias']
                as $dia
            ) {

                $dia['mes'] =
                    (int)
                    $reporte->mes;


                $dia['year'] =
                    (int)
                    $reporte->year;

                $dia['id_reporte'] =
                    (int)
                    $reporte->id;


                $dias[] =
                    $dia;
            }
        }

        foreach (
            $productosResumen
            as &$productoResumen
        ) {

            usort(
                $productoResumen['documentos'],
                function ($a, $b) {

                    $comparacionMes =
                        ((int) $b['mes'])
                        <=>
                        ((int) $a['mes']);


                    if ($comparacionMes !== 0) {

                        return $comparacionMes;
                    }


                    return (
                        (int) $a['orden']
                        <=>
                        (int) $b['orden']
                    );
                }
            );
        }

        unset($productoResumen);

        usort(
            $dias,
            function ($a, $b) {

                return strcmp(
                    $b['fecha'],
                    $a['fecha']
                );
            }
        );

        return [

            'id' =>
            null,

            'id_estacion' =>
            (int)
            $estacion->id,

            'estacion' =>
            $estacion->nombre,

            'year' =>
            $year,

            'mes' =>
            0,

            'productos' =>
            array_values(
                $productosResumen
            ),

            'dias' =>
            $dias,

        ];
    }

    private function transformarReporte(
        Estacion $estacion,
        ReporteCreMes $reporte
    ): array {

        $productosEstacion = [

            [
                'numero' => 1,
                'nombre' => $estacion->producto_uno,
                'color' => '#E21883',
            ],

            [
                'numero' => 2,
                'nombre' => $estacion->producto_dos,
                'color' => '#76C01E',
            ],

            [
                'numero' => 3,
                'nombre' => $estacion->producto_tres,
                'color' => '#5E1B8C',
            ],

        ];


        $productosEstacion = array_values(
            array_filter(
                $productosEstacion,
                fn($producto) =>
                !empty($producto['nombre'])
            )
        );

        $facturas = [

            1 => [

                'factura' =>
                $reporte->f_producto_uno,

                'factura_inicial' =>
                $reporte->fi_producto_uno,

                'factura_final' =>
                $reporte->ff_producto_uno,

            ],


            2 => [

                'factura' =>
                $reporte->f_producto_dos,

                'factura_inicial' =>
                $reporte->fi_producto_dos,

                'factura_final' =>
                $reporte->ff_producto_dos,

            ],


            3 => [

                'factura' =>
                $reporte->f_producto_tres,

                'factura_inicial' =>
                $reporte->fi_producto_tres,

                'factura_final' =>
                $reporte->ff_producto_tres,

            ],

        ];

        $porFecha =
            $reporte
            ->productos
            ->groupBy(
                fn($producto) =>
                $producto
                    ->fecha
                    ?->format('Y-m-d')
            );

        $dias = [];


        foreach (
            $porFecha
            as $fecha => $productos
        ) {

            if (!$fecha) {

                continue;
            }

            $idFecha =
                strtotime(
                    $fecha
                );

            $mensajes =
                $reporte
                ->mensajes
                ->where(
                    'id_fecha',
                    $idFecha
                );

            $productosDia = [];


            foreach (
                $productosEstacion
                as $productoEstacion
            ) {

                $productoNombre =
                    $productoEstacion['nombre'];


                $producto =
                    $productos
                    ->firstWhere(
                        'producto',
                        $productoNombre
                    );


                if (!$producto) {

                    continue;
                }

                $totalCompra =
                    (float)
                    $producto
                        ->pipas
                        ->sum(
                            'volumen'
                        );

                $totalImporte =
                    (float)
                    $producto
                        ->pipas
                        ->sum(
                            'importe_total'
                        );


                $merma =
                    (
                        (float)
                        $producto->volumen_final

                        +

                        (float)
                        $producto->volumen_venta
                    )
                    -
                    (
                        (float)
                        $producto->volumen_inicial

                        +

                        $totalCompra
                    );

                $productosDia[] = [

                    'id' =>
                    (int)
                    $producto->id,

                    'numero' =>
                    $productoEstacion['numero'],

                    'producto' =>
                    $producto->producto,

                    'color' =>
                    $productoEstacion['color'],

                    'volumen_inicial' =>
                    (float)
                    $producto->volumen_inicial,

                    'volumen_venta' =>
                    (float)
                    $producto->volumen_venta,

                    'volumen_final' =>
                    (float)
                    $producto->volumen_final,

                    'merma' =>
                    round(
                        $merma
                    ),

                    'total_compra' =>
                    $totalCompra,

                    'total_importe' =>
                    $totalImporte,

                    'pipas' =>
                    $producto
                        ->pipas
                        ->map(
                            function ($pipa) {

                                return [

                                    'id' =>
                                    (int)
                                    $pipa->id,

                                    'pipa_numero' =>
                                    $pipa->pipa_numero,

                                    'volumen' =>
                                    (float)
                                    $pipa->volumen,

                                    'precio_litro' =>
                                    (float)
                                    $pipa->precio_litro,

                                    'costo_flete' =>
                                    (float)
                                    $pipa->costo_flete,

                                    'no_factura' =>
                                    $pipa->no_factura,

                                    'nombre_razonsocial' =>
                                    $pipa
                                        ->nombre_razonsocial,

                                    'importe_total' =>
                                    (float)
                                    $pipa->importe_total,

                                ];
                            }
                        )
                        ->values()
                        ->all(),

                ];
            }


            $dias[] = [

                'id_reporte' =>
                (int)
                $reporte->id,

                'fecha' =>
                $fecha,

                'dia' =>
                formatearFecha(
                    $fecha
                ),

                'id_fecha' =>
                $idFecha,

                'total_mensajes' =>
                $mensajes->count(),

                'productos' =>
                $productosDia,

            ];
        }

        usort(
            $dias,
            fn($a, $b) =>
            strcmp(
                $b['fecha'],
                $a['fecha']
            )
        );


        $productosResumen = [];


        foreach (
            $productosEstacion
            as $productoEstacion
        ) {

            $numeroProducto =
                $productoEstacion['numero'];


            $nombreProducto =
                $productoEstacion['nombre'];


            $productos =
                $reporte
                ->productos
                ->where(
                    'producto',
                    $nombreProducto
                );


            $totalVenta =
                (float)
                $productos
                    ->sum(
                        'volumen_venta'
                    );

            $totalCompra =
                (float)
                $productos
                    ->sum(
                        function ($producto) {

                            return $producto
                                ->pipas
                                ->sum(
                                    'volumen'
                                );
                        }
                    );

            $totalImporte =
                (float)
                $productos
                    ->sum(
                        function ($producto) {

                            return $producto
                                ->pipas
                                ->sum(
                                    'importe_total'
                                );
                        }
                    );


            $documentos =
                $this->crearDocumentosFactura(
                    $facturas[$numeroProducto],
                    (int)
                    $reporte->mes,
                    (int)
                    $reporte->year
                );


            $productosResumen[] = [

                'numero' =>
                $numeroProducto,

                'nombre' =>
                $nombreProducto,

                'color' =>
                $productoEstacion['color'],

                'facturas' =>
                $facturas[$numeroProducto],

                'documentos' =>
                $documentos,

                'total_venta' =>
                $totalVenta,

                'total_compra' =>
                $totalCompra,

                'total_importe' =>
                $totalImporte,

            ];
        }

        return [

            'id' =>
            (int)
            $reporte->id,

            'id_estacion' =>
            (int)
            $reporte->id_estacion,

            'estacion' =>
            $estacion->nombre,

            'year' =>
            (int)
            $reporte->year,

            'mes' =>
            (int)
            $reporte->mes,

            'productos' =>
            $productosResumen,

            'dias' =>
            $dias,

        ];
    }

    private function crearDocumentosFactura(
        array $facturas,
        int $mes,
        int $year
    ): array {

        $documentos = [];

        $ruta =
            '/uploads/archivos/cre/';

        if (
            !empty($facturas['factura'])
        ) {

            $archivo =
                basename(
                    $facturas['factura']
                );


            $documentos[] = [

                'tipo' =>
                'factura',

                'orden' =>
                1,

                'nombre' =>
                'Factura',

                'archivo' =>
                $archivo,

                'url' =>
                $ruta .
                    rawurlencode(
                        $archivo
                    ),

                'mes' =>
                $mes,

                'year' =>
                $year,

            ];
        }

        if (
            !empty($facturas['factura_inicial'])
        ) {

            $archivo =
                basename(
                    $facturas['factura_inicial']
                );


            $documentos[] = [

                'tipo' =>
                'factura_inicial',

                'orden' =>
                2,

                'nombre' =>
                'Factura inicial',

                'archivo' =>
                $archivo,

                'url' =>
                $ruta .
                    rawurlencode(
                        $archivo
                    ),

                'mes' =>
                $mes,

                'year' =>
                $year,

            ];
        }


        if (
            !empty($facturas['factura_final'])
        ) {

            $archivo =
                basename(
                    $facturas['factura_final']
                );


            $documentos[] = [

                'tipo' =>
                'factura_final',

                'orden' =>
                3,

                'nombre' =>
                'Factura final',

                'archivo' =>
                $archivo,

                'url' =>
                $ruta .
                    rawurlencode(
                        $archivo
                    ),

                'mes' =>
                $mes,

                'year' =>
                $year,

            ];
        }


        return $documentos;
    }

    //--------------------------------------------------------------------------
    public function mensajes(int $idEstacion): void
    {
        try {

            $idReporte = (int) Request::input(
                'id_reporte'
            );

            $idFecha = (int) Request::input(
                'id_fecha'
            );

            if (
                $idReporte <= 0 ||
                $idFecha <= 0
            ) {

                JsonResponse::error(
                    'Los datos del reporte no son válidos.'
                );

                return;
            }

            $reporte = ReporteCreMes::query()
                ->where(
                    'id',
                    $idReporte
                )
                ->where(
                    'id_estacion',
                    $idEstacion
                )
                ->first();


            if (!$reporte) {

                JsonResponse::error(
                    'No se encontró el reporte.'
                );

                return;
            }

            $idUsuario =
                $this->userId();

            $mensajes = ReporteCreMensaje::query()
                ->with([
                    'usuario'
                ])
                ->where(
                    'id_reporte',
                    $idReporte
                )
                ->where(
                    'id_fecha',
                    $idFecha
                )
                ->orderBy(
                    'fecha',
                    'asc'
                )
                ->orderBy(
                    'id',
                    'asc'
                )
                ->get();

            $data = $mensajes
                ->map(
                    function (
                        ReporteCreMensaje $mensaje
                    ) use ($idUsuario) {

                        return $this->transformarMensaje(
                            $mensaje,
                            $idUsuario
                        );
                    }
                )
                ->values()
                ->all();


            JsonResponse::custom([
                'data' => $data
            ]);
        } catch (\Throwable $e) {

            JsonResponse::error(
                'No fue posible cargar los mensajes.'
            );
        }
    }

    public function createMensaje(int $idEstacion): void
    {
        try {

            $idReporte = (int) Request::input(
                'id_reporte'
            );

            $idFecha = (int) Request::input(
                'id_fecha'
            );

            $texto = trim(
                (string) Request::input(
                    'mensaje'
                )
            );

            if (
                $idReporte <= 0 ||
                $idFecha <= 0
            ) {

                JsonResponse::error(
                    'Los datos del reporte no son válidos.'
                );

                return;
            }


            if ($texto === '') {

                JsonResponse::error(
                    'Escribe un mensaje.'
                );

                return;
            }

            if (
                mb_strlen($texto) > 2000
            ) {

                JsonResponse::error(
                    'El mensaje es demasiado largo.'
                );

                return;
            }

            $reporte = ReporteCreMes::query()
                ->where(
                    'id',
                    $idReporte
                )
                ->where(
                    'id_estacion',
                    $idEstacion
                )
                ->first();


            if (!$reporte) {

                JsonResponse::error(
                    'No se encontró el reporte.'
                );

                return;
            }

            $idUsuario =
                $this->userId();


            if ($idUsuario <= 0) {

                JsonResponse::error(
                    'No se pudo identificar al usuario.'
                );

                return;
            }

            $mensaje =
                ReporteCreMensaje::create([
                    'id_reporte' =>
                    $idReporte,

                    'id_fecha' =>
                    $idFecha,

                    'id_usuario' =>
                    $idUsuario,

                    'fecha' =>
                    date('Y-m-d H:i:s'),

                    'mensaje' =>
                    $texto,

                    'tipo' =>
                    0,
                ]);

            $mensaje->load(
                'usuario'
            );

            JsonResponse::custom([
                'data' =>
                $this->transformarMensaje(
                    $mensaje,
                    $idUsuario
                )
            ]);
        } catch (\Throwable $e) {

            JsonResponse::error(
                'No fue posible enviar el mensaje.'
            );
        }
    }

    private function transformarMensaje(
        ReporteCreMensaje $mensaje,
        int $idUsuario
    ): array {


        $nombreUsuario =
            $mensaje->usuario?->nombre
            ?? 'Usuario';

        $partesNombre =
            preg_split(
                '/\s+/',
                trim($nombreUsuario)
            );

        $nombreCorto =
            implode(
                ' ',
                array_slice(
                    $partesNombre,
                    0,
                    2
                )
            );

        $fecha =
            $mensaje->fecha;


        $fechaFormateada =
            $fecha
            ? formatearFecha($fecha->format(
                'Y-m-d,'
            )) . ', ' . $fecha->format(
                'g:i a'
            )
            : '';

        return [

            'id' =>
            (int) $mensaje->id,

            'id_reporte' =>
            (int) $mensaje->id_reporte,

            'id_fecha' =>
            (int) $mensaje->id_fecha,

            'id_usuario' =>
            (int) $mensaje->id_usuario,

            'usuario' =>
            $nombreCorto,

            'mensaje' =>
            $mensaje->mensaje,

            'tipo' =>
            (int) $mensaje->tipo,

            'fecha' =>
            $fecha
                ? $fecha->format(
                    'Y-m-d H:i:s'
                )
                : null,

            'fecha_formateada' =>
            $fechaFormateada,

            'es_mio' =>
            (int) $mensaje->id_usuario ===
                $idUsuario,

        ];
    }

    //------------------------------------------------------------

    public function descargarFacturasAnual(
        int $idEstacion
    ): void {

        $zipPath = null;

        try {

            /*
        |--------------------------------------------------------------------------
        | Año
        |--------------------------------------------------------------------------
        */

            $year =
                (int)
                Request::input('year');


            $yearActual =
                (int)
                date('Y');


            if (
                $year < 2019 ||
                $year > $yearActual
            ) {

                http_response_code(422);

                echo 'El año seleccionado no es válido.';

                return;
            }


            /*
        |--------------------------------------------------------------------------
        | Estación
        |--------------------------------------------------------------------------
        */

            $estacion =
                Estacion::query()
                ->select([
                    'id',
                    'nombre',
                    'razonsocial',
                ])
                ->find(
                    $idEstacion
                );


            if (!$estacion) {

                http_response_code(404);

                echo 'No se encontró la estación.';

                return;
            }


            /*
        |--------------------------------------------------------------------------
        | Reportes del año
        |--------------------------------------------------------------------------
        */

            $reportes =
                ReporteCreMes::query()
                ->select([

                    'id',
                    'mes',
                    'year',

                    /*
                     * Producto 1
                     */
                    'f_producto_uno',
                    'f_producto_dos',
                    'f_producto_tres',

                    /*
                     * Producto 2
                     */
                    'fi_producto_uno',
                    'fi_producto_dos',
                    'fi_producto_tres',

                    /*
                     * Producto 3
                     */
                    'ff_producto_uno',
                    'ff_producto_dos',
                    'ff_producto_tres',

                ])
                ->where(
                    'id_estacion',
                    $idEstacion
                )
                ->where(
                    'year',
                    $year
                )
                ->orderBy(
                    'mes'
                )
                ->get();


            if ($reportes->isEmpty()) {

                http_response_code(404);

                echo 'No existen reportes para el año seleccionado.';

                return;
            }


            /*
        |--------------------------------------------------------------------------
        | Nombre del ZIP
        |--------------------------------------------------------------------------
        */

            $razonSocial =
                trim(
                    (string)
                    (
                        $estacion->razonsocial
                        ?: $estacion->nombre
                    )
                );


            /*
         * Eliminar caracteres no válidos
         * para el nombre del archivo.
         */
            $razonSocial =
                preg_replace(
                    '/[^a-zA-Z0-9_-]+/',
                    '_',
                    $razonSocial
                );


            $razonSocial =
                trim(
                    $razonSocial,
                    '_'
                );


            if ($razonSocial === '') {

                $razonSocial =
                    'Estacion_' .
                    $idEstacion;
            }


            $nombreZip =
                'Facturas_CRE_' .
                $razonSocial .
                '_' .
                $year .
                '.zip';


            /*
        |--------------------------------------------------------------------------
        | Archivo temporal
        |--------------------------------------------------------------------------
        |
        | No generamos el ZIP dentro de public.
        | Esto evita:
        |
        | - colisiones entre usuarios
        | - archivos ZIP abandonados
        | - exposición pública temporal
        |
        */

            $archivoTemporal =
                tempnam(
                    sys_get_temp_dir(),
                    'cre_zip_'
                );


            if ($archivoTemporal === false) {

                throw new \RuntimeException(
                    'No fue posible crear el archivo temporal.'
                );
            }


            $zipPath =
                $archivoTemporal .
                '.zip';


            /*
         * tempnam crea un archivo vacío.
         * Como agregaremos .zip, eliminamos
         * el archivo inicial.
         */
            if (
                is_file(
                    $archivoTemporal
                )
            ) {

                unlink(
                    $archivoTemporal
                );
            }


            /*
        |--------------------------------------------------------------------------
        | Crear ZIP
        |--------------------------------------------------------------------------
        */

            $zip =
                new \ZipArchive();


            $resultado =
                $zip->open(
                    $zipPath,
                    \ZipArchive::CREATE |
                        \ZipArchive::OVERWRITE
                );


            if ($resultado !== true) {

                throw new \RuntimeException(
                    'No fue posible crear el archivo ZIP.'
                );
            }


            /*
        |--------------------------------------------------------------------------
        | Ruta física de las facturas
        |--------------------------------------------------------------------------
        |
        | Tus URLs públicas actuales son:
        |
        | /uploads/archivos/cre/archivo.pdf
        |
        */

            $directorioFacturas =
                rtrim(
                    (string)
                    $_SERVER['DOCUMENT_ROOT'],
                    DIRECTORY_SEPARATOR
                )
                .
                DIRECTORY_SEPARATOR
                .
                'uploads'
                .
                DIRECTORY_SEPARATOR
                .
                'archivos'
                .
                DIRECTORY_SEPARATOR
                .
                'cre'
                .
                DIRECTORY_SEPARATOR;


            /*
        |--------------------------------------------------------------------------
        | Meses
        |--------------------------------------------------------------------------
        */

            $meses = [

                1 => 'Enero',
                2 => 'Febrero',
                3 => 'Marzo',
                4 => 'Abril',
                5 => 'Mayo',
                6 => 'Junio',
                7 => 'Julio',
                8 => 'Agosto',
                9 => 'Septiembre',
                10 => 'Octubre',
                11 => 'Noviembre',
                12 => 'Diciembre',

            ];


            /*
        |--------------------------------------------------------------------------
        | Agregar archivos
        |--------------------------------------------------------------------------
        */

            $totalArchivos =
                0;


            foreach (
                $reportes
                as $reporte
            ) {

                $mes =
                    (int)
                    $reporte->mes;


                $nombreMes =
                    $meses[$mes]
                    ?? ('Mes_' . $mes);


                /*
             * Las nueve facturas del registro.
             */
                $archivos = [

                    /*
                 * Producto 1
                 */
                    $reporte->f_producto_uno,
                    $reporte->f_producto_dos,
                    $reporte->f_producto_tres,

                    /*
                 * Producto 2
                 */
                    $reporte->fi_producto_uno,
                    $reporte->fi_producto_dos,
                    $reporte->fi_producto_tres,

                    /*
                 * Producto 3
                 */
                    $reporte->ff_producto_uno,
                    $reporte->ff_producto_dos,
                    $reporte->ff_producto_tres,

                ];


                foreach (
                    $archivos
                    as $archivo
                ) {

                    if (
                        empty($archivo)
                    ) {

                        continue;
                    }


                    /*
                 * Solo utilizamos el nombre del archivo.
                 *
                 * Esto evita que una ruta almacenada en BD
                 * pueda salir del directorio permitido.
                 */
                    $nombreArchivo =
                        basename(
                            (string)
                            $archivo
                        );


                    if (
                        $nombreArchivo === ''
                    ) {

                        continue;
                    }


                    $rutaFisica =
                        $directorioFacturas .
                        $nombreArchivo;


                    if (
                        !is_file(
                            $rutaFisica
                        )
                    ) {

                        continue;
                    }


                    /*
                 * Estructura dentro del ZIP:
                 *
                 * Enero/factura.pdf
                 * Febrero/factura.pdf
                 * ...
                 */
                    $rutaZip =
                        $nombreMes .
                        '/' .
                        $nombreArchivo;


                    /*
                 * Evitar nombres duplicados
                 * dentro de una misma carpeta.
                 */
                    $rutaFinalZip =
                        $rutaZip;


                    $contador =
                        2;


                    while (
                        $zip->locateName(
                            $rutaFinalZip
                        ) !== false
                    ) {

                        $info =
                            pathinfo(
                                $nombreArchivo
                            );


                        $nombreBase =
                            $info['filename']
                            ?? 'archivo';


                        $extension =
                            isset(
                                $info['extension']
                            )
                            ? '.' .
                            $info['extension']
                            : '';


                        $rutaFinalZip =
                            $nombreMes .
                            '/' .
                            $nombreBase .
                            '_' .
                            $contador .
                            $extension;


                        $contador++;
                    }


                    if (
                        $zip->addFile(
                            $rutaFisica,
                            $rutaFinalZip
                        )
                    ) {

                        $totalArchivos++;
                    }
                }
            }


            /*
        |--------------------------------------------------------------------------
        | Cerrar ZIP
        |--------------------------------------------------------------------------
        */

            $zip->close();


            /*
        |--------------------------------------------------------------------------
        | Sin archivos
        |--------------------------------------------------------------------------
        */

            if (
                $totalArchivos === 0
            ) {

                if (
                    is_file(
                        $zipPath
                    )
                ) {

                    unlink(
                        $zipPath
                    );
                }


                http_response_code(404);

                echo 'No se encontraron facturas para descargar.';

                return;
            }


            /*
        |--------------------------------------------------------------------------
        | Validar ZIP
        |--------------------------------------------------------------------------
        */

            if (
                !is_file(
                    $zipPath
                )
            ) {

                throw new \RuntimeException(
                    'No fue posible generar el archivo ZIP.'
                );
            }


            /*
        |--------------------------------------------------------------------------
        | Limpiar cualquier salida previa
        |--------------------------------------------------------------------------
        */

            while (
                ob_get_level() > 0
            ) {

                ob_end_clean();
            }


            /*
        |--------------------------------------------------------------------------
        | Descargar ZIP
        |--------------------------------------------------------------------------
        */

            header(
                'Content-Type: application/zip'
            );

            header(
                'Content-Disposition: attachment; filename="' .
                    $nombreZip .
                    '"'
            );

            header(
                'Content-Length: ' .
                    filesize(
                        $zipPath
                    )
            );

            header(
                'Cache-Control: no-store, no-cache, must-revalidate'
            );

            header(
                'Pragma: no-cache'
            );

            header(
                'Expires: 0'
            );


            readfile(
                $zipPath
            );


            /*
        |--------------------------------------------------------------------------
        | Eliminar temporal
        |--------------------------------------------------------------------------
        */

            if (
                is_file(
                    $zipPath
                )
            ) {

                unlink(
                    $zipPath
                );
            }


            exit;
        } catch (\Throwable $e) {

            /*
         * Limpiar ZIP temporal si ocurrió un error.
         */
            if (
                $zipPath !== null &&
                is_file(
                    $zipPath
                )
            ) {

                unlink(
                    $zipPath
                );
            }


            http_response_code(500);

            echo 'No fue posible generar el archivo de facturas.';

            return;
        }
    }
}
