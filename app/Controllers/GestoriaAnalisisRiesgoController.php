<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Request;
use App\Core\JsonResponse;
use App\Services\ModuloService;

use App\Models\Estacion;
use App\Models\Sasisopa\AnalisisRiesgo;
use App\Models\Sasisopa\AnalisisRiesgoAnexo;

class GestoriaAnalisisRiesgoController extends BaseController
{
    protected string $modulo = 'gestoria';

    public function index(int $idEstacion)
    {
        $estacion = Estacion::find($idEstacion);
        $title = 'Análisis de riesgo' . ' (' . $estacion->nombre . ')';

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

                '/js/gestoria/analisis-riesgo/index.actions.init.js?v=1.6.0',
                '/js/gestoria/analisis-riesgo/index.datatable.init.js?v=1.9.0',
            ],
            'help' => false
        ];

        View::render('gestoria/analisis-riesgo', $data, 'main');
    }


    public function data(int $idEstacion)
    {
        $analisis = AnalisisRiesgo::query()
            ->where('id_estacion', $idEstacion)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get([
                'id',
                'id_estacion',
                'fecha',
                'descripcion',
                'documento',
            ]);

        $data = $analisis->map(function ($item, $index) {

            return [
                'numero' => $index + 1,

                'id' => $item->id,

                'id_estacion' => $item->id_estacion,

                'fecha' => $item->fecha?->format('Y-m-d'),

                'fecha_formateada' => $item->fecha
                    ? formatearFecha($item->fecha->format('Y-m-d'))
                    : 'S/I',

                'descripcion' => $item->descripcion ?? 'S/I',

                'documento' => $item->documento,

                'tiene_documento' => !empty($item->documento),
            ];
        })->values();

        JsonResponse::custom([
            'data' => $data
        ]);
    }

    public function create(int $idEstacion): void
    {
        try {

            $fecha = trim(
                (string) Request::input('fecha')
            );

            $descripcion = trim(
                (string) Request::input('descripcion')
            );


            if (
                !$idEstacion ||
                !$fecha ||
                !$descripcion ||
                !isset($_FILES['documento'])
            ) {

                JsonResponse::error(
                    'Complete todos los campos obligatorios.'
                );

                return;
            }


            $file = $_FILES['documento'];
            $maxSize = 10 * 1024 * 1024;


            if (
                $file['size'] > $maxSize
            ) {

                JsonResponse::error(
                    'El documento no puede superar los 10 MB.'
                );

                return;
            }


            $extension = strtolower(
                pathinfo(
                    $file['name'],
                    PATHINFO_EXTENSION
                )
            );


            $nombreArchivo = sprintf(
                'ANALISIS-RIESGO-%d-%d-%s.%s',
                $idEstacion,
                time(),
                bin2hex(random_bytes(4)),
                $extension
            );


            $carpeta =
                dirname(__DIR__, 2) .
                '/public/uploads/archivos/analisis-riesgo/';


            if (!is_dir($carpeta)) {

                mkdir(
                    $carpeta,
                    0777,
                    true
                );
            }


            $destino =
                $carpeta .
                $nombreArchivo;


            if (
                !move_uploaded_file(
                    $file['tmp_name'],
                    $destino
                )
            ) {

                JsonResponse::error(
                    'No fue posible guardar el documento.'
                );

                return;
            }


            try {

                $analisis = AnalisisRiesgo::create([
                    'id_estacion' => $idEstacion,
                    'fecha' => $fecha,
                    'descripcion' => $descripcion,
                    'documento' => $nombreArchivo,
                ]);
            } catch (\Throwable $e) {

                if (is_file($destino)) {

                    unlink($destino);
                }

                throw $e;
            }


            JsonResponse::success(
                'Análisis de riesgo guardado correctamente.',
                [
                    'id' => $analisis->id,

                    'documento' =>
                    $nombreArchivo,

                    'url' =>
                    '/uploads/archivos/analisis-riesgo/' .
                        $nombreArchivo
                ]
            );
        } catch (\Throwable $e) {

            JsonResponse::error(
                'No fue posible guardar el análisis de riesgo.'
            );
        }
    }

    public function delete(int $idEstacion): void
    {
        try {
            $id = (int) Request::jsonInput('id');

            if (!$id) {
                JsonResponse::error(
                    'No se especificó el análisis de riesgo.'
                );
                return;
            }

            $analisis = AnalisisRiesgo::query()
                ->where('id', $id)
                ->where('id_estacion', $idEstacion)
                ->first();

            if (!$analisis) {
                JsonResponse::error(
                    'No se encontró el análisis de riesgo.'
                );
                return;
            }

            $nombreDocumento = $analisis->documento;

            AnalisisRiesgoAnexo::query()
                ->where(
                    'id_analisis',
                    $analisis->id
                )
                ->delete();

            $analisis->delete();

            if ($nombreDocumento) {
                $archivo =
                    dirname(__DIR__, 2) .
                    '/public/uploads/archivos/analisis-riesgo/' .
                    basename($nombreDocumento);

                if (is_file($archivo)) {
                    unlink($archivo);
                }
            }

            JsonResponse::success(
                'Análisis de riesgo eliminado correctamente.'
            );
        } catch (\Throwable $e) {

            JsonResponse::error(
                'No fue posible eliminar el análisis de riesgo.'
            );
        }
    }

    public function editData(
        int $idEstacion,
        int $id
    ): void {
        try {

            $analisis = AnalisisRiesgo::query()
                ->where('id', $id)
                ->where(
                    'id_estacion',
                    $idEstacion
                )
                ->first();


            if (!$analisis) {

                JsonResponse::error(
                    'No se encontró el análisis de riesgo.'
                );

                return;
            }


            JsonResponse::custom([

                'data' => [

                    'id' =>
                    $analisis->id,

                    'id_estacion' =>
                    $analisis->id_estacion,

                    'fecha' =>
                    $analisis->fecha
                        ?->format('Y-m-d'),

                    'descripcion' =>
                    $analisis->descripcion
                        ?? '',

                    'documento' =>
                    $analisis->documento,

                    'url_documento' =>
                    $analisis->documento
                        ? '/uploads/archivos/analisis-riesgo/' .
                        $analisis->documento
                        : null,

                ]

            ]);
        } catch (\Throwable $e) {

            JsonResponse::error(
                'No fue posible cargar el análisis de riesgo.'
            );
        }
    }

    public function update(
        int $idEstacion,
        int $id
    ): void {
        try {

            $analisis = AnalisisRiesgo::query()
                ->where('id', $id)
                ->where(
                    'id_estacion',
                    $idEstacion
                )
                ->first();


            if (!$analisis) {

                JsonResponse::error(
                    'No se encontró el análisis de riesgo.'
                );

                return;
            }


            $fecha = trim(
                (string) Request::input(
                    'fecha'
                )
            );


            $descripcion = trim(
                (string) Request::input(
                    'descripcion'
                )
            );


            if (
                !$fecha ||
                !$descripcion
            ) {

                JsonResponse::error(
                    'Complete todos los campos obligatorios.'
                );

                return;
            }

            $datos = [

                'fecha' =>
                $fecha,

                'descripcion' =>
                $descripcion,

            ];

            $nuevoDocumento = null;
            $destinoNuevo = null;
            $documentoAnterior =
                $analisis->documento;


            if (
                isset($_FILES['documento']) &&
                $_FILES['documento']['error']
                !== UPLOAD_ERR_NO_FILE
            ) {

                $file =
                    $_FILES['documento'];


                if (
                    $file['error']
                    !== UPLOAD_ERR_OK
                ) {

                    JsonResponse::error(
                        'No fue posible recibir el documento.'
                    );

                    return;
                }


                $maxSize =
                    10 * 1024 * 1024;


                if (
                    $file['size']
                    > $maxSize
                ) {

                    JsonResponse::error(
                        'El documento no puede superar los 10 MB.'
                    );

                    return;
                }

                $extension = strtolower(
                    pathinfo(
                        $file['name'],
                        PATHINFO_EXTENSION
                    )
                );


                $nuevoDocumento = sprintf(
                    'ANALISIS-RIESGO-%d-%d-%s.%s',
                    $idEstacion,
                    time(),
                    bin2hex(
                        random_bytes(4)
                    ),
                    $extension
                );


                $carpeta =
                    dirname(__DIR__, 2) .
                    '/public/uploads/archivos/analisis-riesgo/';


                if (!is_dir($carpeta)) {

                    mkdir(
                        $carpeta,
                        0777,
                        true
                    );
                }


                $destinoNuevo =
                    $carpeta .
                    $nuevoDocumento;


                if (
                    !move_uploaded_file(
                        $file['tmp_name'],
                        $destinoNuevo
                    )
                ) {

                    JsonResponse::error(
                        'No fue posible guardar el nuevo documento.'
                    );

                    return;
                }


                $datos['documento'] =
                    $nuevoDocumento;
            }

            try {

                $analisis->update(
                    $datos
                );
            } catch (\Throwable $e) {


                if (
                    $destinoNuevo &&
                    is_file($destinoNuevo)
                ) {

                    unlink(
                        $destinoNuevo
                    );
                }

                throw $e;
            }

            if (
                $nuevoDocumento &&
                $documentoAnterior
            ) {

                $archivoAnterior =
                    dirname(__DIR__, 2) .
                    '/public/uploads/archivos/analisis-riesgo/' .
                    basename(
                        $documentoAnterior
                    );


                if (
                    is_file(
                        $archivoAnterior
                    )
                ) {

                    unlink(
                        $archivoAnterior
                    );
                }
            }


            JsonResponse::success(
                'Análisis de riesgo actualizado correctamente.',
                [

                    'id' =>
                    $analisis->id,

                    'documento' =>
                    $analisis->documento,

                    'url' =>
                    $analisis->documento
                        ? '/uploads/archivos/analisis-riesgo/' .
                        $analisis->documento
                        : null,

                ]
            );
        } catch (\Throwable $e) {

            JsonResponse::error(
                'No fue posible actualizar el análisis de riesgo.'
            );
        }
    }

    //----------------------------------------------------

    public function anexosData(
        int $idEstacion,
        int $idAnalisis
    ): void {
        try {

            $analisis = AnalisisRiesgo::query()
                ->where('id', $idAnalisis)
                ->where('id_estacion', $idEstacion)
                ->first();


            if (!$analisis) {

                JsonResponse::error(
                    'No se encontró el análisis de riesgo.'
                );

                return;
            }


            $anexos = AnalisisRiesgoAnexo::query()
                ->where(
                    'id_analisis',
                    $analisis->id
                )
                ->orderByDesc('id')
                ->get([
                    'id',
                    'id_analisis',
                    'descripcion',
                    'documento',
                ]);


            $dataAnexos =
                $anexos->map(function ($anexo) {

                    return [

                        'id' =>
                        $anexo->id,

                        'id_analisis' =>
                        $anexo->id_analisis,

                        'descripcion' =>
                        $anexo->descripcion ?? 'S/I',

                        'documento' =>
                        $anexo->documento,

                        'url' =>
                        '/uploads/archivos/analisis-riesgo/' .
                            $anexo->documento,

                    ];
                })->values();


            JsonResponse::custom([

                'data' => [

                    'analisis' => [

                        'id' =>
                        $analisis->id,

                        'fecha' =>
                        $analisis->fecha
                            ?->format('Y-m-d'),

                        'fecha_formateada' =>
                        $analisis->fecha
                            ? formatearFecha(
                                $analisis
                                    ->fecha
                                    ->format('Y-m-d')
                            )
                            : 'S/I',

                        'descripcion' =>
                        $analisis->descripcion
                            ?? 'S/I',

                    ],

                    'anexos' =>
                    $dataAnexos,

                ]

            ]);
        } catch (\Throwable $e) {

            JsonResponse::error(
                'No fue posible cargar los anexos.'
            );
        }
    }

    public function createAnexo(
        int $idEstacion,
        int $idAnalisis
    ): void {
        try {

            $analisis = AnalisisRiesgo::query()
                ->where('id', $idAnalisis)
                ->where('id_estacion', $idEstacion)
                ->first();


            if (!$analisis) {

                JsonResponse::error(
                    'No se encontró el análisis de riesgo.'
                );

                return;
            }


            $descripcion = trim(
                (string) Request::input(
                    'descripcion'
                )
            );


            if (
                !$descripcion ||
                !isset($_FILES['documento'])
            ) {

                JsonResponse::error(
                    'Complete todos los campos obligatorios.'
                );

                return;
            }


            $file =
                $_FILES['documento'];

            $maxSize =
                10 * 1024 * 1024;


            if (
                $file['size']
                > $maxSize
            ) {

                JsonResponse::error(
                    'El documento no puede superar los 10 MB.'
                );

                return;
            }


            $extension = strtolower(
                pathinfo(
                    $file['name'],
                    PATHINFO_EXTENSION
                )
            );


            $nombreArchivo = sprintf(
                'ANEXO-ANALISIS-RIESGO-%d-%d-%s.%s',
                $analisis->id,
                time(),
                bin2hex(
                    random_bytes(4)
                ),
                $extension
            );


            $carpeta =
                dirname(__DIR__, 2) .
                '/public/uploads/archivos/analisis-riesgo/';


            if (!is_dir($carpeta)) {

                mkdir(
                    $carpeta,
                    0777,
                    true
                );
            }


            $destino =
                $carpeta .
                $nombreArchivo;


            if (
                !move_uploaded_file(
                    $file['tmp_name'],
                    $destino
                )
            ) {

                JsonResponse::error(
                    'No fue posible guardar el documento.'
                );

                return;
            }


            try {

                $anexo =
                    AnalisisRiesgoAnexo::create([

                        'id_analisis' =>
                        $analisis->id,

                        'descripcion' =>
                        $descripcion,

                        'documento' =>
                        $nombreArchivo,

                    ]);
            } catch (\Throwable $e) {

                if (is_file($destino)) {

                    unlink($destino);
                }

                throw $e;
            }


            JsonResponse::success(
                'Anexo guardado correctamente.',
                [

                    'id' =>
                    $anexo->id,

                    'documento' =>
                    $nombreArchivo,

                    'url' =>
                    '/uploads/archivos/analisis-riesgo/' .
                        $nombreArchivo,

                ]
            );
        } catch (\Throwable $e) {

            JsonResponse::error(
                'No fue posible guardar el anexo.'
            );
        }
    }

    public function deleteAnexo(
        int $idEstacion,
        int $idAnalisis
    ): void {
        try {

            $idAnexo =
                (int) Request::jsonInput('id');


            if (!$idAnexo) {

                JsonResponse::error(
                    'No se especificó el anexo.'
                );

                return;
            }


            $analisis = AnalisisRiesgo::query()
                ->where('id', $idAnalisis)
                ->where('id_estacion', $idEstacion)
                ->first();


            if (!$analisis) {

                JsonResponse::error(
                    'No se encontró el análisis de riesgo.'
                );

                return;
            }


            $anexo = AnalisisRiesgoAnexo::query()
                ->where('id', $idAnexo)
                ->where(
                    'id_analisis',
                    $analisis->id
                )
                ->first();


            if (!$anexo) {

                JsonResponse::error(
                    'No se encontró el anexo.'
                );

                return;
            }


            $nombreDocumento =
                $anexo->documento;


            $anexo->delete();


            if ($nombreDocumento) {

                $archivo =
                    dirname(__DIR__, 2) .
                    '/public/uploads/archivos/analisis-riesgo/' .
                    basename(
                        $nombreDocumento
                    );


                if (is_file($archivo)) {

                    unlink($archivo);
                }
            }


            JsonResponse::success(
                'Anexo eliminado correctamente.'
            );
        } catch (\Throwable $e) {

            JsonResponse::error(
                'No fue posible eliminar el anexo.'
            );
        }
    }
}
