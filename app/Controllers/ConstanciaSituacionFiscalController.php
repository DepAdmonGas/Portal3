<?php

namespace App\Controllers;

use App\Core\JsonResponse;
use App\Core\Breadcrumb;
use App\Core\Request;
use App\Core\View;
use App\Services\ConstanciaSituacionFiscalService;
use App\Services\ModuleStationService;

class ConstanciaSituacionFiscalController extends BaseController
{
    /** Clave del módulo para el selector de estación compartido. */
    protected string $modulo = ConstanciaSituacionFiscalService::CLAVE;

    public function index()
    {
        $title = 'Constancia de Situación Fiscal';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add($title, '');

        /*
         * El acceso se resuelve con la entrada del menú del Home (puesto o
         * usuario). No se usa guardModuleAccess() porque CSF guarda
         * id_estacion en el espacio de op_rh_localidades, donde el id 11 es
         * Dirección de Operaciones y no la gasolinera "Sabino Aguirre".
         */
        if (!ConstanciaSituacionFiscalService::getPermisos()['puede_consultar']) {
            View::render('errors/404', [], 'main');
            return;
        }

        /*
         * Este módulo no maneja Autolavado (op_rh_localidades.id = 9): el
         * bloque legacy del selector lo ofrece a los usuarios con id_gas = 2,
         * y aquí no corresponde. Se excluye sólo para este módulo.
         */
        ModuleStationService::excluirAutoLavado($this->modulo);

        // El selector compartido imprime su propio HTML en el layout.
        $data = [
            'title' => $title,
            'permisos' => ConstanciaSituacionFiscalService::getPermisos(),
            'modulo' => $this->modulo,
            'tipoDescarga' => ConstanciaSituacionFiscalService::TIPO_DESCARGA,
            'moduleStationKey' => $this->modulo,
            // Sin contadores entre paréntesis en las opciones del selector.
            'pendientesData' => [],
            // La columna Estacion/Departamento sólo existe con "todas".
            'mostrarFilaEstacion' => ConstanciaSituacionFiscalService::mostrarColumnaEstacion(),
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
            'scripts' => [
                '/js/vendor.min.js?v=' . time(),
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/core/module-station-selector.js',
                '/js/constancia-situacion-fiscal/constancia-situacion-fiscal.datatable.init.js?v=' . time(),
                '/js/constancia-situacion-fiscal/constancia-situacion-fiscal.actions.init.js?v=' . time(),
            ],
            'help' => false,
        ];

        View::render('constancia-situacion-fiscal/index', $data, 'main');
    }

    /** Listado dentro del alcance del usuario. */
    public function data()
    {
        if (!ConstanciaSituacionFiscalService::getPermisos()['puede_consultar']) {
            JsonResponse::forbidden('No tienes permiso de lectura para este módulo.');
        }

        $datos = ConstanciaSituacionFiscalService::getData();

        JsonResponse::custom([
            'success' => true,
            'data' => $datos['data'],
            // Con una entidad seleccionada no se muestra la columna Estación.
            'mostrar_fila_estacion' => $datos['mostrar_fila_estacion'],
        ]);
    }

    public function store()
    {
        $permisos = ConstanciaSituacionFiscalService::getPermisos();

        if (!$permisos['puede_agregar']) {
            JsonResponse::forbidden('No tienes permiso para agregar constancias.');
        }

        $resultado = ConstanciaSituacionFiscalService::store(
            Request::post(),
            $_FILES
        );

        JsonResponse::custom($resultado, $resultado['success'] ? 200 : 422);
    }

    public function destroy()
    {
        $permisos = ConstanciaSituacionFiscalService::getPermisos();

        if (!$permisos['puede_eliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar constancias.');
        }

        $id = (int)Request::input('id');
        $resultado = ConstanciaSituacionFiscalService::destroy($id);

        JsonResponse::custom($resultado, $resultado['success'] ? 200 : 422);
    }
}