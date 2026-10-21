<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\IncidenciasService;
use App\Services\ModuleStationService;

class IncidenciasController extends BaseController
{
    /** Clave del módulo para el selector de estación compartido. */
    protected string $modulo = IncidenciasService::CLAVE;

    public function index()
    {
        $title = 'Incidencias';

        $permisos = IncidenciasService::getPermisos();

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
            'modulo' => $this->modulo,
            'tipoDescarga' => IncidenciasService::TIPO_DESCARGA,
            // Estación fija cuando el selector no se imprime (usuario de una
            // sola estación); en "todas" el alta la pide el formulario.
            'estacionContexto' => IncidenciasService::getContexto(),
            'estaciones' => IncidenciasService::estacionesPermitidasConNombre(),
            'moduleStationKey' => $this->modulo,
            // El selector lo imprime el layout a partir de moduleStationKey.
            'pendientesData' => [],
            // La columna Estación sólo existe con "todas".
            'mostrarFilaEstacion' => IncidenciasService::mostrarColumnaEstacion(),
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
            'scripts' => [
                '/js/vendor.min.js?v=' . time(),
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/core/module-station-selector.js',
                '/js/incidencias/incidencias.datatable.init.js?v=' . time(),
                '/js/incidencias/incidencias.actions.init.js?v=' . time(),
            ],
            'help' => false,
        ];

        View::render('incidencias/index', $data, 'main');
    }

    /** Listado dentro del alcance del usuario. */
    public function data()
    {
        if (!IncidenciasService::getPermisos()['puede_consultar']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }

        $datos = IncidenciasService::getData();

        JsonResponse::custom([
            'success' => true,
            'data' => $datos['data'],
            // Con una estación seleccionada no se muestra la columna Estación.
            'mostrar_fila_estacion' => $datos['mostrar_fila_estacion'],
        ]);
    }

    /** Detalle de una incidencia. */
    public function show()
    {
        if (!IncidenciasService::getPermisos()['puede_consultar']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }

        $id = (int)Request::input('id');
        $resultado = IncidenciasService::show($id);

        JsonResponse::custom($resultado, $resultado['success'] ? 200 : 404);
    }

    public function store()
    {
        $permisos = IncidenciasService::getPermisos();

        if (!$permisos['puede_agregar']) {
            JsonResponse::forbidden('No tienes permiso para agregar incidencias.');
        }

        $resultado = IncidenciasService::store(
            Request::post(),
            $_FILES
        );

        JsonResponse::custom($resultado, $resultado['success'] ? 200 : 422);
    }

    public function update()
    {
        $permisos = IncidenciasService::getPermisos();

        if (!$permisos['puede_editar']) {
            JsonResponse::forbidden('No tienes permiso para editar incidencias.');
        }

        $resultado = IncidenciasService::update(
            Request::post(),
            $_FILES
        );

        JsonResponse::custom($resultado, $resultado['success'] ? 200 : 422);
    }

    public function destroy()
    {
        $permisos = IncidenciasService::getPermisos();

        if (!$permisos['puede_eliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar incidencias.');
        }

        $id = (int)Request::input('id');
        $resultado = IncidenciasService::destroy($id);

        JsonResponse::custom($resultado, $resultado['success'] ? 200 : 422);
    }
}
