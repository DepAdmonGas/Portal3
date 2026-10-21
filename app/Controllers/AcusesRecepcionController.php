<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\AcusesRecepcionService;

class AcusesRecepcionController extends BaseController
{
    public function index()
    {
        $title = 'Acuses de Recepción';

        $permisos = AcusesRecepcionService::getPermisos();

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
            'empresas' => $permisos['es_admin'] ? AcusesRecepcionService::empresas() : [],
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
            'scripts' => [
                '/js/vendor.min.js?v=' . time(),
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/acuses-recepcion/acuses-recepcion.datatable.init.js?v=' . time(),
                '/js/acuses-recepcion/acuses-recepcion.actions.init.js?v=' . time(),
            ],
            'help' => false,
        ];

        View::render('acuses-recepcion/index', $data, 'main');
    }

    /** Listado de acuses según el filtro del buscador. */
    public function data()
    {
        if (!AcusesRecepcionService::getPermisos()['puede_consultar']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }

        $buscar = (string)Request::input('buscar', '');
        $datos = AcusesRecepcionService::getData($buscar);

        JsonResponse::custom([
            'success' => true,
            'data' => $datos['data'],
        ]);
    }

    /** Detalle de un acuse. */
    public function show()
    {
        if (!AcusesRecepcionService::getPermisos()['puede_consultar']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }

        $id = (int)Request::input('id');
        $resultado = AcusesRecepcionService::show($id);

        JsonResponse::custom($resultado, $resultado['success'] ? 200 : 404);
    }

    /** Documentos de un acuse (modal de gestión). */
    public function documentos()
    {
        if (!AcusesRecepcionService::getPermisos()['puede_consultar']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }

        $id = (int)Request::input('id');
        $resultado = AcusesRecepcionService::documentos($id);

        JsonResponse::custom($resultado, $resultado['success'] ? 200 : 404);
    }

    /** Empresas (razón social) para el formulario de finalización. */
    public function empresas()
    {
        if (!AcusesRecepcionService::getPermisos()['es_admin']) {
            JsonResponse::forbidden('No tienes permiso para consultar las empresas.');
        }

        JsonResponse::custom([
            'success' => true,
            'data' => AcusesRecepcionService::empresas(),
        ]);
    }

    /** Alta de un acuse vacío. */
    public function store()
    {
        $permisos = AcusesRecepcionService::getPermisos();

        if (!$permisos['puede_agregar']) {
            JsonResponse::forbidden('No tienes permiso para agregar acuses de recepción.');
        }

        $resultado = AcusesRecepcionService::store();

        JsonResponse::custom($resultado, $resultado['success'] ? 200 : 422);
    }

    /** Guarda la empresa del acuse. */
    public function guardarEmpresa()
    {
        $permisos = AcusesRecepcionService::getPermisos();

        if (!$permisos['puede_editar']) {
            JsonResponse::forbidden('No tienes permiso para editar acuses de recepción.');
        }

        $resultado = AcusesRecepcionService::guardarEmpresa(Request::all());

        JsonResponse::custom($resultado, $resultado['success'] ? 200 : 422);
    }

    /** Agrega un documento al acuse. */
    public function agregarDocumento()
    {
        $permisos = AcusesRecepcionService::getPermisos();

        if (!$permisos['puede_editar']) {
            JsonResponse::forbidden('No tienes permiso para agregar documentos.');
        }

        $resultado = AcusesRecepcionService::agregarDocumento(Request::all());

        JsonResponse::custom($resultado, $resultado['success'] ? 200 : 422);
    }

    /** Elimina un documento del acuse. */
    public function eliminarDocumento()
    {
        $permisos = AcusesRecepcionService::getPermisos();

        if (!$permisos['puede_editar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar documentos.');
        }

        $id = (int)Request::input('id');
        $resultado = AcusesRecepcionService::eliminarDocumento($id);

        JsonResponse::custom($resultado, $resultado['success'] ? 200 : 422);
    }

    /** Finaliza el acuse indicando quién recibe. */
    public function finalizar()
    {
        $permisos = AcusesRecepcionService::getPermisos();

        if (!$permisos['puede_editar']) {
            JsonResponse::forbidden('No tienes permiso para finalizar acuses.');
        }

        $resultado = AcusesRecepcionService::finalizar(Request::all());

        JsonResponse::custom($resultado, $resultado['success'] ? 200 : 422);
    }

    /** Baja del acuse y de sus documentos. */
    public function destroy()
    {
        $permisos = AcusesRecepcionService::getPermisos();

        if (!$permisos['puede_eliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar acuses de recepción.');
        }

        $id = (int)Request::input('id');
        $resultado = AcusesRecepcionService::destroy($id);

        JsonResponse::custom($resultado, $resultado['success'] ? 200 : 422);
    }
}
