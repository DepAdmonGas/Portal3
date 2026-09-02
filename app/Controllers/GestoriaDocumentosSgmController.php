<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Request;
use App\Core\JsonResponse;
use App\Services\ModuloService;

use App\Models\Estacion;
use App\Models\Sgm\Documento;
use App\Models\Sgm\ControlDocumental;

class GestoriaDocumentosSgmController extends BaseController
{
    protected string $modulo = 'gestoria';

    public function index(int $idEstacion)
    {
        $estacion = Estacion::find($idEstacion);
        $title = 'Control documental del SGM' . ' (' . $estacion->nombre . ')';

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
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js',

                '/js/gestoria/controldocumentalsgm/index.actions.init.js?v=1.1.0',


            ],
            'help' => false
        ];

        View::render('gestoria/control-documental-sgm', $data, 'main');
    }

    public function data(int $idEstacion): void
    {
        try {

            $estacion =
                Estacion::query()
                ->select([
                    'id',
                    'nombre',
                ])
                ->find(
                    $idEstacion
                );


            if (!$estacion) {

                JsonResponse::error(
                    'No se encontró la estación.'
                );

                return;
            }

            $documentos =
                Documento::query()
                ->whereIn(
                    'seccion',
                    [
                        1,
                        2,
                        3,
                    ]
                )
                ->orderBy('seccion')
                ->orderBy('id')
                ->get();


            $archivos =
                ControlDocumental::query()
                ->where(
                    'id_estacion',
                    $idEstacion
                )
                ->orderByDesc('fecha')
                ->orderByDesc('id')
                ->get()
                ->groupBy(
                    'id_documento'
                );

            $data =
                $documentos
                ->map(
                    function (
                        Documento $documento
                    ) use (
                        $archivos
                    ) {

                        $historial =
                            $archivos
                            ->get(
                                $documento->id,
                                collect()
                            )
                            ->map(
                                function (
                                    ControlDocumental $archivo
                                ) {

                                    $nombreArchivo =
                                        basename(
                                            (string) $archivo->archivo
                                        );


                                    return [

                                        'id' =>
                                        (int) $archivo->id,

                                        'id_documento' =>
                                        (int) $archivo->id_documento,

                                        'id_estacion' =>
                                        (int) $archivo->id_estacion,

                                        'fecha' =>
                                        formatearFecha($archivo->fecha
                                            ?->format('Y-m-d'))
                                            ?? '',

                                        'fecha_formateada' =>
                                        formatearFecha($archivo->fecha
                                            ?->format('Y-m-d'))
                                            ?? '',

                                        'archivo' =>
                                        $nombreArchivo,

                                        'url' =>
                                        '/uploads/archivos/FormatosSGM/'
                                            . rawurlencode(
                                                $nombreArchivo
                                            ),

                                    ];
                                }
                            )
                            ->values();

                        $ultimoArchivo =
                            $historial->first();


                        return [

                            'id' =>
                            (int) $documento->id,

                            'codificacion' =>
                            $documento->codificacion
                                ?? '',

                            'nombre' =>
                            $documento->nombre
                                ?? '',

                            'fecha_aprobacion' =>
                            $documento->fecha_aprobacion
                                ?->format('Y-m-d')
                                ?? '',

                            'fecha_aprobacion_formateada' =>
                            formatearFecha($documento->fecha_aprobacion
                                ?->format('Y-m-d'))
                                ?? '',

                            'seccion' =>
                            (int) $documento->seccion,

                            'total' =>
                            $historial->count(),

                            'archivo_actual' =>
                            $ultimoArchivo
                                ?? null,

                            'archivos' =>
                            $historial->all(),

                        ];
                    }
                )
                ->values();

            $seccion3 =
                $data
                ->where(
                    'seccion',
                    3
                )
                ->values()
                ->all();


            $seccion1 =
                $data
                ->where(
                    'seccion',
                    1
                )
                ->values()
                ->all();


            $seccion2 =
                $data
                ->where(
                    'seccion',
                    2
                )
                ->values()
                ->all();

            JsonResponse::custom([

                'success' =>
                true,

                'data' => [

                    'estacion' => [

                        'id' =>
                        (int) $estacion->id,

                        'nombre' =>
                        $estacion->nombre
                            ?? '',

                    ],

                    'seccion3' =>
                    $seccion3,

                    'seccion1' =>
                    $seccion1,

                    'seccion2' =>
                    $seccion2,

                ]

            ]);
        } catch (\Throwable $e) {

            JsonResponse::error(
                'No fue posible cargar el control documental.'
            );
        }
    }

    public function guardarDocumento(
        int $idEstacion,
        int $idDocumento
    ): void {

        $rutaGuardada = null;
        try {


            $estacion =
                Estacion::query()
                ->select([
                    'id',
                ])
                ->find(
                    $idEstacion
                );


            if (!$estacion) {

                JsonResponse::error(
                    'No se encontró la estación.'
                );

                return;
            }

            $documento =
                Documento::query()
                ->find(
                    $idDocumento
                );


            if (!$documento) {

                JsonResponse::error(
                    'No se encontró el documento.'
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


            $archivo = $_FILES['documento'];


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

            $extension =
                strtolower(
                    pathinfo(
                        $archivo['name'],
                        PATHINFO_EXTENSION
                    )
                );


            if (
                $extension === ''
            ) {

                JsonResponse::error(
                    'El archivo no tiene una extensión válida.'
                );

                return;
            }

            $nombreArchivo =
                uniqid(
                    'sgm_',
                    true
                )
                . '-'
                . time()
                . '.'
                . $extension;

            $directorio = dirname(__DIR__, 2) .
                '/public/uploads/archivos/FormatosSGM/';


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


            $rutaGuardada =
                $directorio
                . $nombreArchivo;

            if (
                !move_uploaded_file(
                    $archivo['tmp_name'],
                    $rutaGuardada
                )
            ) {

                throw new \RuntimeException(
                    'No fue posible guardar el archivo.'
                );
            }

            $control =
                ControlDocumental::create([

                    'id_documento' =>
                    $idDocumento,

                    'id_estacion' =>
                    $idEstacion,

                    'fecha' =>
                    date('Y-m-d'),

                    'archivo' =>
                    $nombreArchivo,

                ]);

            JsonResponse::custom([

                'success' =>
                true,

                'id' =>
                (int) $control->id,

                'message' =>
                'Documento guardado correctamente.'

            ]);
        } catch (\Throwable $e) {

            if (
                $rutaGuardada &&
                is_file(
                    $rutaGuardada
                )
            ) {

                @unlink(
                    $rutaGuardada
                );
            }


            JsonResponse::error(
                'No fue posible guardar el documento.'
            );
        }
    }

    public function eliminarDocumento(
        int $idEstacion,
        int $idArchivo
    ): void {

        try {

            $archivo =
                ControlDocumental::query()
                ->where(
                    'id',
                    $idArchivo
                )
                ->where(
                    'id_estacion',
                    $idEstacion
                )
                ->first();


            if (!$archivo) {

                JsonResponse::error(
                    'No se encontró el documento.'
                );

                return;
            }

            $nombreArchivo =
                basename(
                    (string) $archivo->archivo
                );

            $archivo->delete();

            $rutaArchivo = dirname(__DIR__, 2) .
                '/public/uploads/archivos/FormatosSGM/' . $nombreArchivo;


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
}
