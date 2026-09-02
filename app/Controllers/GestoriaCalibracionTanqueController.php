<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Request;
use App\Core\JsonResponse;
use App\Services\ModuloService;

use App\Models\Estacion;
use App\Models\Sgm\CalibracionTanque;
use App\Models\Sgm\CalibracionTanqueDocumento;
use App\Models\Sgm\CalibracionTanqueDetalle;

class GestoriaCalibracionTanqueController extends BaseController
{

    protected string $modulo = 'gestoria';

    public function index(int $idEstacion)
    {
        $estacion = Estacion::find($idEstacion);
        $title = 'Calibración de Tanques' . ' (' . $estacion->nombre . ')';

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

                '/js/gestoria/calibraciontanque/index.actions.init.js?v=1.2.0',
                '/js/gestoria/calibraciontanque/index.datatable.init.js?v=1.1.0',


            ],
            'help' => false
        ];

        View::render('gestoria/calibracion-tanque', $data, 'main');
    }

    public function data(int $idEstacion): void
    {
        try {

            $estacion = Estacion::query()
                ->select([
                    'id',
                    'nombre',
                ])
                ->find($idEstacion);


            if (!$estacion) {

                JsonResponse::error(
                    'No se encontró la estación.'
                );

                return;
            }

            $calibraciones =
                CalibracionTanque::query()
                ->where(
                    'id_estacion',
                    $idEstacion
                )
                ->orderByDesc('id')
                ->get();

            $data =
                $calibraciones
                ->map(
                    function (CalibracionTanque $calibracion) {

                        return [
                            'id' =>
                            (int) $calibracion->id,

                            'id_estacion' =>
                            (int) $calibracion->id_estacion,
                            'fecha' =>
                            $calibracion->fecha
                                ?->format('Y-m-d')
                                ?? '',

                            'fecha_formateada' =>
                            $calibracion->fecha
                                ?->format('d/m/Y')
                                ?? '',
                        ];
                    }
                )
                ->values()
                ->all();

            JsonResponse::custom([
                'data' => $data
            ]);
        } catch (\Throwable $e) {

            JsonResponse::error(
                'No fue posible cargar las calibraciones de tanques.'
            );
        }
    }

    public function create(int $idEstacion): void
    {
        try {

            $estacion = Estacion::query()
                ->select([
                    'id',
                    'nombre',
                ])
                ->find($idEstacion);


            if (!$estacion) {

                JsonResponse::error(
                    'No se encontró la estación.'
                );

                return;
            }

            $calibracion =
                CalibracionTanque::create([

                    'id_estacion' =>
                    $idEstacion,

                    'fecha' =>
                    date('Y-m-d'),

                ]);

            JsonResponse::custom([

                'success' =>
                true,

                'id' =>
                (int) $calibracion->id,

                'message' =>
                'La calibración fue creada correctamente.'

            ]);
        } catch (\Throwable $e) {

            JsonResponse::error(
                'No fue posible crear la calibración de tanques.'
            );
        }
    }

    public function delete(
        int $idEstacion,
        int $id
    ): void {

        try {

            $calibracion =
                CalibracionTanque::query()
                ->where(
                    'id',
                    $id
                )
                ->where(
                    'id_estacion',
                    $idEstacion
                )
                ->first();


            if (!$calibracion) {

                JsonResponse::error(
                    'No se encontró la calibración.'
                );

                return;
            }

            $calibracion->delete();


            JsonResponse::custom([

                'success' =>
                true,

                'message' =>
                'La calibración fue eliminada correctamente.'

            ]);
        } catch (\Throwable $e) {

            JsonResponse::error(
                'No fue posible eliminar la calibración.'
            );
        }
    }

    //-----------------------------------------------------------------

    public function editarIndex(
        int $idEstacion,
        int $idCalibracion
    ) {
        $estacion = Estacion::find($idEstacion);
        $title = 'Editar Calibración de Tanques' . ' (' . $estacion->nombre . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Gestoria', '/gestoria');
        Breadcrumb::add('Calibración de Tanques', '/gestoria/calibracion-tanques/' . $idEstacion);
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'idEstacion' => $idEstacion,
            'idCalibracion' => $idCalibracion,
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js',

                '/js/gestoria/calibraciontanque/editar.actions.init.js?v=1.4.0'


            ],
            'help' => false
        ];

        View::render('gestoria/calibracion-tanque-editar', $data, 'main');
    }

    public function dataCalibracion(
        int $idEstacion,
        int $idReporte
    ): void {

        try {

            $reporte =
                CalibracionTanque::query()
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
                    'No se encontró la calibración.'
                );

                return;
            }

            $documentos =
                CalibracionTanqueDocumento::query()
                ->orderBy('id')
                ->get();

            $detalles =
                CalibracionTanqueDetalle::query()
                ->where(
                    'id_calibracion',
                    $idReporte
                )
                ->orderByDesc('id')
                ->get()
                ->groupBy('id_documento');

            $documentosData =
                $documentos
                ->map(
                    function (
                        CalibracionTanqueDocumento $documento
                    ) use (
                        $detalles
                    ) {

                        $archivos =
                            $detalles
                            ->get(
                                $documento->id,
                                collect()
                            )
                            ->map(
                                function (
                                    CalibracionTanqueDetalle $detalle
                                ) {

                                    $archivo =
                                        basename(
                                            $detalle->archivo
                                        );


                                    return [

                                        'id' =>
                                        (int) $detalle->id,

                                        'id_calibracion' =>
                                        (int) $detalle->id_calibracion,

                                        'id_documento' =>
                                        (int) $detalle->id_documento,

                                        'archivo' =>
                                        $archivo,

                                        'url' =>
                                        '/uploads/archivos/calibracion/'
                                            . rawurlencode(
                                                $archivo
                                            ),

                                    ];
                                }
                            )
                            ->values()
                            ->all();


                        return [

                            'id' =>
                            (int) $documento->id,

                            'nombre' =>
                            $documento->nombre,

                            'total' =>
                            count(
                                $archivos
                            ),

                            'archivos' =>
                            $archivos,

                        ];
                    }
                )
                ->values()
                ->all();

            JsonResponse::custom([

                'success' =>
                true,

                'data' => [

                    'reporte' => [

                        'id' =>
                        (int) $reporte->id,

                        'id_estacion' =>
                        (int) $reporte->id_estacion,

                        'fecha' =>
                        $reporte->fecha
                            ?->format('Y-m-d')
                            ?? '',

                    ],

                    'documentos' =>
                    $documentosData,

                ]

            ]);
        } catch (\Throwable $e) {

            JsonResponse::error(
                'No fue posible cargar la información de calibración.'
            );
        }
    }

    public function guardarDocumento(
        int $idEstacion,
        int $idCalibracion
    ): void {

        $archivoGuardado = null;

        try {

            $idDocumento =
                (int) Request::input(
                    'idDocumento'
                );


            if ($idDocumento <= 0) {

                JsonResponse::error(
                    'El documento no es válido.'
                );

                return;
            }

            $reporte =
                CalibracionTanque::query()
                ->where(
                    'id',
                    $idCalibracion
                )
                ->where(
                    'id_estacion',
                    $idEstacion
                )
                ->first();


            if (!$reporte) {

                JsonResponse::error(
                    'No se encontró la calibración.'
                );

                return;
            }

            $documento =
                CalibracionTanqueDocumento::find(
                    $idDocumento
                );


            if (!$documento) {

                JsonResponse::error(
                    'No se encontró el tipo de documento.'
                );

                return;
            }

            if (
                !isset(
                    $_FILES['documento']
                )
            ) {

                JsonResponse::error(
                    'Selecciona un archivo.'
                );

                return;
            }


            $archivo =
                $_FILES['documento'];


            if (
                $archivo['error']
                !== UPLOAD_ERR_OK
            ) {

                JsonResponse::error(
                    'No fue posible cargar el archivo.'
                );

                return;
            }

            $maxSize =
                10 * 1024 * 1024;


            if (
                (int) $archivo['size']
                > $maxSize
            ) {

                JsonResponse::error(
                    'El archivo no puede superar los 10 MB.'
                );

                return;
            }

            $finfo =
                new \finfo(
                    FILEINFO_MIME_TYPE
                );


            $mime =
                $finfo->file(
                    $archivo['tmp_name']
                );


            if (
                $mime
                !== 'application/pdf'
            ) {

                JsonResponse::error(
                    'El archivo debe ser un PDF.'
                );

                return;
            }


            $nombreOriginal =
                pathinfo(
                    $archivo['name'],
                    PATHINFO_FILENAME
                );


            $nombreOriginal =
                preg_replace(
                    '/[^A-Za-z0-9_-]/',
                    '_',
                    $nombreOriginal
                );


            $nombreArchivo =
                uniqid(
                    'cal_',
                    true
                )
                . '-'
                . $nombreOriginal
                . '.pdf';

            $directorio = dirname(__DIR__, 2) .
                '/public/uploads/archivos/calibracion/';

            if (
                !is_dir(
                    $directorio
                )
            ) {

                if (
                    !mkdir(
                        $directorio,
                        0775,
                        true
                    )
                    &&
                    !is_dir(
                        $directorio
                    )
                ) {

                    throw new \RuntimeException(
                        'No fue posible crear el directorio.'
                    );
                }
            }


            $archivoGuardado =
                $directorio
                . $nombreArchivo;


            if (
                !move_uploaded_file(
                    $archivo['tmp_name'],
                    $archivoGuardado
                )
            ) {

                throw new \RuntimeException(
                    'No fue posible guardar el archivo.'
                );
            }

            $detalle =
                CalibracionTanqueDetalle::create([

                    'id_calibracion' =>
                    $idCalibracion,

                    'id_documento' =>
                    $idDocumento,

                    'archivo' =>
                    $nombreArchivo,

                ]);


            JsonResponse::custom([

                'success' =>
                true,

                'id' =>
                (int) $detalle->id,

                'message' =>
                'Documento guardado correctamente.'

            ]);
        } catch (\Throwable $e) {

            if (
                $archivoGuardado &&
                is_file(
                    $archivoGuardado
                )
            ) {

                @unlink(
                    $archivoGuardado
                );
            }


            JsonResponse::error(
                'No fue posible guardar el documento.'
            );
        }
    }

    public function eliminarDocumento(
        int $idEstacion,
        int $idCalibracion,
        int $idDetalle
    ): void {

        try {

            $reporte =
                CalibracionTanque::query()
                ->where(
                    'id',
                    $idCalibracion
                )
                ->where(
                    'id_estacion',
                    $idEstacion
                )
                ->first();


            if (!$reporte) {

                JsonResponse::error(
                    'No se encontró la calibración.'
                );

                return;
            }

            $detalle =
                CalibracionTanqueDetalle::query()
                ->where(
                    'id',
                    $idDetalle
                )
                ->where(
                    'id_calibracion',
                    $idCalibracion
                )
                ->first();


            if (!$detalle) {

                JsonResponse::error(
                    'No se encontró el documento.'
                );

                return;
            }


            $nombreArchivo =
                basename(
                    $detalle->archivo
                );

            $detalle->delete();


            $rutaArchivo = dirname(__DIR__, 2) .
                '/public/uploads/archivos/calibracion/' . $nombreArchivo;


            if (
                is_file(
                    $rutaArchivo
                )
            ) {

                @unlink(
                    $rutaArchivo
                );
            }


            JsonResponse::custom([

                'success' =>
                true,

                'message' =>
                'Documento eliminado correctamente.'

            ]);
        } catch (\Throwable $e) {

            JsonResponse::error(
                'No fue posible eliminar el documento.'
            );
        }
    }

    public function finalizar(
        int $idEstacion,
        int $idCalibracion
    ): void {

        try {

            $fecha =
                trim(
                    (string) Request::input(
                        'fecha'
                    )
                );


            if (
                $fecha === ''
            ) {

                JsonResponse::error(
                    'La fecha es obligatoria.'
                );

                return;
            }

            $fechaValida =
                \DateTime::createFromFormat(
                    'Y-m-d',
                    $fecha
                );


            if (
                !$fechaValida ||
                $fechaValida->format(
                    'Y-m-d'
                ) !== $fecha
            ) {

                JsonResponse::error(
                    'La fecha no es válida.'
                );

                return;
            }

            $reporte =
                CalibracionTanque::query()
                ->where(
                    'id',
                    $idCalibracion
                )
                ->where(
                    'id_estacion',
                    $idEstacion
                )
                ->first();


            if (!$reporte) {

                JsonResponse::error(
                    'No se encontró la calibración.'
                );

                return;
            }

            $reporte->fecha =
                $fecha;

            $reporte->save();


            JsonResponse::custom([

                'success' =>
                true,

                'id' =>
                (int) $reporte->id,

                'fecha' =>
                $reporte->fecha,

                'message' =>
                'La calibración fue finalizada correctamente.'

            ]);
        } catch (\Throwable $e) {

            JsonResponse::error(
                'No fue posible finalizar la calibración.'
            );
        }
    }
}
