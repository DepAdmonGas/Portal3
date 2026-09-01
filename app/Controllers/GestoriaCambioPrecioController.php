<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Request;
use App\Core\JsonResponse;
use App\Services\ModuloService;

use App\Models\Estacion;
use App\Models\Sasisopa\CambioPrecio;

class GestoriaCambioPrecioController extends BaseController
{
    protected string $modulo = 'gestoria';

    public function index(int $idEstacion)
    {
        $estacion = Estacion::find($idEstacion);
        $title = 'Cambio de precio' . ' (' . $estacion->nombre . ')';

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

                '/js/gestoria/cambioprecio/index.actions.init.js?v=1.3.1',
                '/js/gestoria/cambioprecio/index.datatable.init.js?v=1.5.0',


            ],
            'help' => false
        ];

        View::render('gestoria/cambio-precio', $data, 'main');
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


            $cambios = CambioPrecio::query()
                ->where(
                    'id_estacion',
                    $idEstacion
                )
                ->orderByDesc('id')
                ->get();


            $data = $cambios
                ->map(
                    function (CambioPrecio $cambio) {

                        return [

                            'id' =>
                            (int) $cambio->id,

                            'id_estacion' =>
                            (int) $cambio->id_estacion,

                            'fechacreacion' =>
                            $cambio->fechacreacion
                                ?->format('Y-m-d H:i:s')
                                ?? '',

                            'fechacreacion_formateada' =>
                            $cambio->fechacreacion
                                ?->format('Y-m-d g:i a')
                                ?? '',

                            'fecha' =>
                            $cambio->fecha
                                ?->format('Y-m-d')
                                ?? '',

                            'fecha_formateada' =>
                            $cambio->fecha
                                ?->format('Y-m-d')
                                ?? '',

                            'hora' =>
                            $cambio->hora ?? '',

                            'gsuper' =>
                            $cambio->gsuper !== null
                                ? (float) $cambio->gsuper
                                : null,

                            'gpremium' =>
                            $cambio->gpremium !== null
                                ? (float) $cambio->gpremium
                                : null,

                            'gdiesel' =>
                            $cambio->gdiesel !== null
                                ? (float) $cambio->gdiesel
                                : null,

                            'estado' =>
                            (int) $cambio->estado,

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
                'No fue posible cargar los cambios de precio.'
            );
        }
    }

    public function actualizarEstado(): void
    {
        try {

            $idReporte =
                (int) Request::input(
                    'idReporte'
                );


            if ($idReporte <= 0) {

                JsonResponse::error(
                    'El reporte no es válido.'
                );

                return;
            }


            $cambioPrecio =
                CambioPrecio::find(
                    $idReporte
                );


            if (!$cambioPrecio) {

                JsonResponse::error(
                    'No se encontró el cambio de precio.'
                );

                return;
            }


            $cambioPrecio->estado = 1;

            $cambioPrecio->save();


            JsonResponse::custom([
                'success' => true,
                'data' => [
                    'id' =>
                    (int) $cambioPrecio->id,

                    'estado' =>
                    (int) $cambioPrecio->estado,
                ]
            ]);
        } catch (\Throwable $e) {

            JsonResponse::error(
                'No fue posible actualizar el estado.'
            );
        }
    }
}
