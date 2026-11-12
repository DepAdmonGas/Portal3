<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\ComunicadosDoService;

class ComunicadosDoController extends BaseController
{
    public function index()
    {
        $title = 'Comunicados';

        $permisos = ComunicadosDoService::getPermisos();

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add($title, '');

        // El acceso se resuelve con la entrada del menú del Home.
        if (!$permisos['puede_consultar']) {
            View::render('errors/404', [], 'main');
            return;
        }

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'tipoDescarga' => ComunicadosDoService::TIPO_DESCARGA,
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
            'scripts' => [
                '/js/vendor.min.js?v=' . time(),
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/comunicados/comunicados.datatable.init.js?v=' . time(),
                '/js/comunicados/comunicados.actions.init.js?v=' . time(),
            ],
            'help' => false,
        ];

        View::render('comunicados/index', $data, 'main');
    }

    /** Listado de comunicados (global) y acuses del usuario. */
    public function data()
    {
        if (!ComunicadosDoService::getPermisos()['puede_consultar']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }

        $datos = ComunicadosDoService::getData();

        JsonResponse::custom([
            'success' => true,
            'data' => $datos['data'],
        ]);
    }

    /** Detalle de un comunicado con la lista de acuses. */
    public function show()
    {
        if (!ComunicadosDoService::getPermisos()['puede_consultar']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }

        $id = (int)Request::input('id');
        $resultado = ComunicadosDoService::show($id);

        JsonResponse::custom($resultado, $resultado['success'] ? 200 : 404);
    }

    public function store()
    {
        $permisos = ComunicadosDoService::getPermisos();

        if (!$permisos['puede_agregar']) {
            JsonResponse::forbidden('No tienes permiso para agregar comunicados.');
        }

        $resultado = ComunicadosDoService::store(
            Request::post(),
            $_FILES
        );

        JsonResponse::custom($resultado, $resultado['success'] ? 200 : 422);
    }

    public function update()
    {
        $permisos = ComunicadosDoService::getPermisos();

        if (!$permisos['puede_editar']) {
            JsonResponse::forbidden('No tienes permiso para editar comunicados.');
        }

        $resultado = ComunicadosDoService::update(
            Request::post(),
            $_FILES
        );

        JsonResponse::custom($resultado, $resultado['success'] ? 200 : 422);
    }

    public function destroy()
    {
        $permisos = ComunicadosDoService::getPermisos();

        if (!$permisos['puede_eliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar comunicados.');
        }

        $id = (int)Request::input('id');
        $resultado = ComunicadosDoService::destroy($id);

        JsonResponse::custom($resultado, $resultado['success'] ? 200 : 422);
    }

    /** Acuse "De Acuerdo" del usuario actual. */
    public function acuse()
    {
        $permisos = ComunicadosDoService::getPermisos();

        if (!$permisos['puede_acusar']) {
            JsonResponse::forbidden('No tienes permiso para confirmar este comunicado.');
        }

        $id = (int)Request::input('id');
        $resultado = ComunicadosDoService::acuse($id);

        JsonResponse::custom($resultado, $resultado['success'] ? 200 : 422);
    }
}
