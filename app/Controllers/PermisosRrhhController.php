<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Session;
use App\Core\View;
use App\Services\ModuleStationService;
use App\Services\PermisosRrhhService;

class PermisosRrhhController extends BaseController
{
    public function index()
    {
        $permisos = PermisosRrhhService::getPermisos();

        $title = 'Permisos';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add($title, '');

        if (!$this->guardModuleAccess(PermisosRrhhService::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        $contextoNombre = ModuleStationService::getContext(PermisosRrhhService::MODULE_KEY)['nombre'] ?? '';

        View::render('departamento-operativo/2-recursos-humanos/permisos/index', [
            'title'            => $title,
            'idEstacion'       => $permisos['id_estacion'],
            'idUsuario'        => $permisos['id_usuario'],
            'multiestacion'    => $permisos['multiestacion'],
            'moduleStationKey' => PermisosRrhhService::MODULE_KEY,
            'pendientesData'   => PermisosRrhhService::getPendingCountsFlat(),
            'contextoNombre'   => $contextoNombre,
            'totalPendientes'  => PermisosRrhhService::getPendingCountsContext(),
            'puedeCrear'       => $permisos['puedeCrear'],
            'puedeEditar'      => $permisos['puedeEditar'],
            'puedeEliminar'    => $permisos['puedeEliminar'],
            'puedeDescargar'   => $permisos['puedeDescargar'],
            'puedeVoBo'        => $permisos['puedeVoBo'],
            'help'             => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/permisos.datatable.init.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/permisos.actions.init.js?v=' . time(),
            ],
'links' => [
                '/assets/libs/select2/dist/css/select2.min.css',
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
        ], 'departamento-operativo');
    }

    public function nuevo()
    {
        $permisos = PermisosRrhhService::getPermisos();
        if (!$permisos['puedeCrear']) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        $ctx = ModuleStationService::getContext(PermisosRrhhService::MODULE_KEY);
        $idEstacion = (int)($ctx['id_estacion'] ?? $ctx['id_depto'] ?? 0);
        if ($idEstacion <= 0) {
            header('Location: /departamento-operativo/recursos-humanos/permisos');
            exit;
        }

        $title = 'Nuevo Permiso';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Permisos', '/departamento-operativo/recursos-humanos/permisos');
        Breadcrumb::add($title, '');

        View::render('departamento-operativo/2-recursos-humanos/permisos/formulario', [
            'title'                 => $title,
            'modo'                  => 'nuevo',
            'detalle'               => null,
            'idEstacion'            => $idEstacion,
            'idUsuario'             => $permisos['id_usuario'],
            'estaciones'            => PermisosRrhhService::getLocalidadesDisponibles(),
            'estacionesCubre'       => PermisosRrhhService::getEstacionesCubre(),
            'personal'              => PermisosRrhhService::getPersonal($idEstacion),
            'moduleStationKey'      => PermisosRrhhService::MODULE_KEY,
            'pendientesData'        => PermisosRrhhService::getPendingCountsFlat(),
                        'ocultarSelectorEstacion'=> true,

            'help'                  => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/select2/dist/js/select2.min.js',
                '/assets/libs/signature_pad/docs/js/signature_pad.umd.min.js',
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/permisos.actions.init.js?v=' . time(),
            ],
            'links' => [
                '/assets/libs/select2/dist/css/select2.min.css',
            ],
        ], 'departamento-operativo');
    }

    public function editar($id)
    {
        $permisos = PermisosRrhhService::getPermisos();
        
        if (!$permisos['puedeEditar']) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        $detalle = PermisosRrhhService::getDetalle((int)$id);
        if (!$detalle) {
            http_response_code(404);
            echo 'Permiso no encontrado';
            return;
        }

        if ((int)$detalle['estado'] === 2) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }
/*
        if (!PermisosRrhhService::puedeVerLocalidad((int)$detalle['id_estacion'])) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }
*/
        $ctxAnterior = ModuleStationService::getContext(PermisosRrhhService::MODULE_KEY);
        $idEstacionRecord = (int)$detalle['id_estacion'];
        if ($idEstacionRecord === 9) {
            ModuleStationService::setContext(PermisosRrhhService::MODULE_KEY, null, 9);
        } elseif ($idEstacionRecord > 0) {
            ModuleStationService::setContext(PermisosRrhhService::MODULE_KEY, $idEstacionRecord, null);
        }

        $title = 'Editar Permiso (#' . $id . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Permisos', '/departamento-operativo/recursos-humanos/permisos');
        Breadcrumb::add($title, '');

        View::render('departamento-operativo/2-recursos-humanos/permisos/formulario', [
            'title'                 => $title,
            'modo'                  => 'editar',
            'detalle'               => $detalle,
            'idEstacion'            => $idEstacionRecord,
            'idUsuario'             => $permisos['id_usuario'],
            'estaciones'            => PermisosRrhhService::getLocalidadesDisponibles(),
            'estacionesCubre'       => PermisosRrhhService::getEstacionesCubre(),
            'personal'              => PermisosRrhhService::getPersonal($idEstacionRecord),
            'moduleStationKey'      => PermisosRrhhService::MODULE_KEY,
            'pendientesData'        => PermisosRrhhService::getPendingCountsFlat(),
                        'ocultarSelectorEstacion'=> true,

            'help'                  => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/select2/dist/js/select2.min.js',
                '/assets/libs/signature_pad/docs/js/signature_pad.umd.min.js',
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/permisos.actions.init.js?v=' . time(),
            ],
            'links' => [
                '/assets/libs/select2/dist/css/select2.min.css',
            ],
        ], 'departamento-operativo');

        ModuleStationService::setContext(PermisosRrhhService::MODULE_KEY, $ctxAnterior['id_estacion'] ?? null, $ctxAnterior['id_depto'] ?? null);
    }

    public function firmarPage($id)
    {
        $detalle = PermisosRrhhService::getDetalle((int)$id);
        if (!$detalle) {
            http_response_code(404);
            echo 'Permiso no encontrado';
            return;
        }

        $permisos = PermisosRrhhService::getPermisos();

        if (!PermisosRrhhService::puedeVerLocalidad((int)$detalle['id_estacion'])) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        $title = 'Firmar Permiso (#' . $id . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Permisos', '/departamento-operativo/recursos-humanos/permisos');
        Breadcrumb::add($title, '');

        View::render('departamento-operativo/2-recursos-humanos/permisos/firmar', [
            'title'            => $title,
            'detalle'          => $detalle,
            'permisosFirma'    => $detalle['permisos_firma'],
            'idUsuario'        => $permisos['id_usuario'],
            'moduleStationKey' => PermisosRrhhService::MODULE_KEY . '-oculto',
            'ocultarSelectorEstacion' => true,
            'help'             => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/signature_pad/docs/js/signature_pad.umd.min.js',
                '/assets/js/departamento-operativo/2-recursos-humanos/permisos.actions.init.js?v=' . time(),
            ],
        ], 'departamento-operativo');
    }

    public function getData()
    {
        $permisos = PermisosRrhhService::getPermisos();

        JsonResponse::custom([
            'success' => true,
            'data'    => PermisosRrhhService::getLista(),
            'permisos'=> [
                'puedeEditar'   => $permisos['puedeEditar'],
                'puedeEliminar' => $permisos['puedeEliminar'],
                'puedeDescargar' => $permisos['puedeDescargar'],
                'puedeVoBo'     => $permisos['puedeVoBo'],
            ],
        ]);
    }

    public function getDetalle()
    {
        $id = (int)($_GET['id'] ?? 0);

        $detalle = PermisosRrhhService::getDetalle($id);
        if (!$detalle) {
            JsonResponse::error('Permiso no encontrado', 404);
        }

        JsonResponse::custom([
            'success' => true,
            'detalle' => $detalle,
        ]);
    }

    public function getFirmas()
    {
        $id = (int)($_GET['id'] ?? 0);
        JsonResponse::custom([
            'success' => true,
            'firmas'  => PermisosRrhhService::getFirmas($id),
        ]);
    }

    public function getPendingCounts()
    {
        $flat = PermisosRrhhService::getPendingCountsFlat();
        $flat['contexto'] = PermisosRrhhService::getPendingCountsContext();
        JsonResponse::custom(array_merge(['success' => true], $flat));
    }

    public function getPersonal()
    {
        $estacion = (int)($_GET['estacion'] ?? 0);
        if ($estacion <= 0) {
            JsonResponse::error('Estación no válida');
        }

        JsonResponse::custom([
            'success' => true,
            'personal' => PermisosRrhhService::getPersonal($estacion),
        ]);
    }

    public function guardar()
    {
        $permisos = PermisosRrhhService::getPermisos();
        if (!$permisos['puedeCrear']) {
            JsonResponse::forbidden('No tienes permiso para crear permisos');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $firma = trim((string)($input['firma'] ?? ''));
        $result = PermisosRrhhService::guardar($input, $firma);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function update()
    {
        $permisos = PermisosRrhhService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::forbidden('No tienes permiso para editar permisos');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            JsonResponse::error('ID no válido');
        }

        $firma = trim((string)($input['firma'] ?? ''));
        $result = PermisosRrhhService::actualizar($id, $input, $firma);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function delete()
    {
        $permisos = PermisosRrhhService::getPermisos();
        if (!$permisos['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar permisos');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? $_POST['id'] ?? 0);

        $result = PermisosRrhhService::eliminar($id);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function firmaQuienCubre()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $id = (int)($input['id'] ?? 0);
        $firma = trim((string)($input['firma'] ?? ''));

        if ($id <= 0) {
            JsonResponse::error('ID no válido');
        }

        $result = PermisosRrhhService::firmaQuienCubre($id, $firma);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function crearToken()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $id = (int)($input['id'] ?? 0);
        $via = (string)($input['via'] ?? 'telegram');
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        if ($id <= 0) {
            JsonResponse::error('ID no válido');
        }

        $result = PermisosRrhhService::crearToken($id, $idUsuario, $via);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }

    public function firmar()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $id = (int)($input['id'] ?? 0);
        $token = (int)($input['token'] ?? 0);
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        if ($id <= 0 || $token <= 0) {
            JsonResponse::error('Datos incompletos');
        }

        $result = PermisosRrhhService::firmarVoBo($id, $token, $idUsuario);
        JsonResponse::custom($result, $result['success'] ? 200 : 400);
    }
}