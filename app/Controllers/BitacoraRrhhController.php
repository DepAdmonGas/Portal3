<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Session;
use App\Core\View;
use App\Services\BitacoraRrhhService;
use App\Services\DropdownYearMesService;
use App\Services\ModuleStationService;

class BitacoraRrhhController extends BaseController
{
    public function index($idYear, $idMes)
    {
        $validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
        $idYear = (int)$validados['idYear'];
        $idMes = (int)$validados['idMes'];

        $permisos = BitacoraRrhhService::getPermisos();
        $esMultiestacion = $permisos['multiestacion'];

        $title = 'Bitácora RRHH (' . BitacoraRrhhService::getNombreMes($idMes) . ' ' . $idYear . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add($title, '');
        Breadcrumb::add(DropdownYearMesService::dropdownMes($idYear, $idMes), '');
        Breadcrumb::add(DropdownYearMesService::dropdownYearManual($idYear, $idMes), '');

        if (!$this->guardModuleAccess(BitacoraRrhhService::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        $contextoNombre = ModuleStationService::getContext(BitacoraRrhhService::MODULE_KEY)['nombre'] ?? '';

        View::render('departamento-operativo/2-recursos-humanos/bitacora-rrhh/index', [
            'title'            => $title,
            'idYear'           => $idYear,
            'idMes'            => $idMes,
            'idEstacion'       => $permisos['id_estacion'],
            'moduleStationKey' => BitacoraRrhhService::MODULE_KEY,
            'pendientesData'   => BitacoraRrhhService::getPendingCountsFlat($idYear, $idMes),
            'yearMesTemplate'  => '/departamento-operativo/recursos-humanos/bitacora-rrhh/{year}/{mes}',
            'multiestacion'    => $esMultiestacion,
            'contextoNombre'   => $contextoNombre,
            'puedeCrear'       => $permisos['puedeCrear'],
            'puedeEditar'      => $permisos['puedeEditar'],
            'puedeEliminar'    => $permisos['puedeEliminar'],
            'puedeDescargar'   => $permisos['puedeDescargar'],
            'puedeFinalizar'   => $permisos['puedeFinalizar'],
            'puedeEliminarDoc' => $permisos['puedeEliminarDoc'],
            'puedeVerVisualizaciones' => $permisos['puedeVerVisualizaciones'],
            'idUsuario'        => $permisos['id_usuario'],
            'totalPendientes'  => BitacoraRrhhService::getPendingCountsContext($idYear, $idMes),
            'help'             => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/bitacora-rrhh.datatable.init.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/bitacora-rrhh.actions.init.js?v=' . time(),
            ],
            'links' => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
        ], 'departamento-operativo');
    }

    public function editar($id)
    {
        $detalle = BitacoraRrhhService::getDetalle((int)$id);
        if (!$detalle) {
            http_response_code(404);
            echo 'Bitácora no encontrada';
            exit;
        }

        if (!BitacoraRrhhService::puedeVerLocalidad((int)$detalle['id_estacion'])) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        $permisos = BitacoraRrhhService::getPermisos();

        $title = 'Formulario Bitácora RRHH (# ' . $id . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add(
            'Bitácora RRHH (' . BitacoraRrhhService::getNombreMes($detalle['mes']) . ' ' . $detalle['year'] . ')',
            '/departamento-operativo/recursos-humanos/bitacora-rrhh/' . $detalle['year'] . '/' . $detalle['mes']
        );
        Breadcrumb::add($title, '');

        View::render('departamento-operativo/2-recursos-humanos/bitacora-rrhh/formulario', [
            'title'                 => $title,
            'detalle'               => $detalle,
            'idUsuario'             => $permisos['id_usuario'],
            'moduleStationKey'      => BitacoraRrhhService::MODULE_KEY . '-oculto',
            'ocultarSelectorEstacion' => true,
            'puedeDescargar'   => $permisos['puedeDescargar'],
            'puedeFinalizar'   => $permisos['puedeFinalizar'],
            'puedeEliminarDoc' => $permisos['puedeEliminarDoc'],
            'puedeVerVisualizaciones' => $permisos['puedeVerVisualizaciones'],

            'help'             => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/bitacora-rrhh.actions.init.js?v=' . time(),
            ],
        ], 'departamento-operativo');
    }

    public function getData()
    {
        $idYear = (int)($_GET['idYear'] ?? 0);
        $idMes = (int)($_GET['idMes'] ?? 0);

        $permisos = BitacoraRrhhService::getPermisos();

        JsonResponse::custom([
            'success' => true,
            'data'    => BitacoraRrhhService::getLista($idYear, $idMes),
            'permisos'=> [
                'puedeEditar' => $permisos['puedeEditar'],
                'puedeEliminar'   => $permisos['puedeEliminar'],
                'puedeFinalizar'  => $permisos['puedeFinalizar'],
                'puedeVerVisualizaciones' => $permisos['puedeVerVisualizaciones'],
            ],
        ]);
    }

    public function getPendingCounts()
    {
        $idYear = (int)($_GET['idYear'] ?? 0);
        $idMes = (int)($_GET['idMes'] ?? 0);

        $flat = BitacoraRrhhService::getPendingCountsFlat($idYear, $idMes);
        $flat['contexto'] = BitacoraRrhhService::getPendingCountsContext($idYear, $idMes);
        JsonResponse::custom(array_merge(['success' => true], $flat));
    }

    public function crear()
    {
        $permisos = BitacoraRrhhService::getPermisos();
        if (!$permisos['puedeCrear']) {
            JsonResponse::forbidden('No tienes permiso para crear registros');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $post = is_array($input) ? $input : $_POST;

        $ctx = ModuleStationService::getContext(BitacoraRrhhService::MODULE_KEY);
        $idEstacion = (int)($post['id_estacion'] ?? ($ctx['id_estacion'] ?? $ctx['id_depto'] ?? 0));

        if ($idEstacion <= 0) {
            $idEstacion = (int)($ctx['id_estacion'] ?? $ctx['id_depto'] ?? 0);
        }

        $idYear = (int)($post['id_year'] ?? date('Y'));
        $idMes = (int)($post['id_mes'] ?? date('n'));
        $descripcion = trim((string)($post['descripcion'] ?? ''));

        if ($idEstacion <= 0) {
            JsonResponse::error('Selecciona una estación o departamento');
        }
        if ($descripcion === '') {
            JsonResponse::error('La descripción es obligatoria');
        }

        $id = BitacoraRrhhService::crear($idEstacion, $idYear, $idMes, $descripcion);
        if (!$id) {
            JsonResponse::error('No tienes acceso a la estación seleccionada');
        }

        JsonResponse::custom([
            'success' => true,
            'message' => 'Registro creado correctamente',
            'id'      => $id,
        ]);
    }

    public function finalizar()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? $_POST['id'] ?? 0);

        if (!BitacoraRrhhService::finalizar($id)) {
            JsonResponse::error('No tienes permiso para finalizar este registro');
        }

        JsonResponse::custom([
            'success' => true,
            'message' => 'Registro finalizado correctamente',
        ]);
    }

    public function delete()
    {
        $permisos = BitacoraRrhhService::getPermisos();
        if (!$permisos['puedeEliminar']) {
            JsonResponse::forbidden('No tienes permiso para eliminar registros');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? $_POST['id'] ?? 0);

        BitacoraRrhhService::eliminar($id);

        JsonResponse::custom([
            'success' => true,
            'message' => 'Registro eliminado correctamente',
        ]);
    }

    public function getComentarios()
    {
        $id = (int)($_GET['id'] ?? 0);
        JsonResponse::custom([
            'success'    => true,
            'comentarios' => BitacoraRrhhService::getComentarios($id),
        ]);
    }

    public function addComentario()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $post = is_array($input) ? $input : $_POST;
        $id = (int)($post['id'] ?? 0);
        $comentario = trim((string)($post['comentario'] ?? ''));
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        if ($comentario === '') {
            JsonResponse::error('El comentario es obligatorio');
        }

        BitacoraRrhhService::addComentario($id, $comentario, $idUsuario);

        JsonResponse::custom([
            'success' => true,
            'message' => 'Comentario agregado',
        ]);
    }

    public function getDocumentos()
    {
        $id = (int)($_GET['id'] ?? 0);
        JsonResponse::custom([
            'success'    => true,
            'detalle'    => BitacoraRrhhService::getDetalle($id),
            'documentos' => BitacoraRrhhService::getDocumentos($id),
        ]);
    }

    public function addDocumento()
    {
        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim((string)($_POST['nombre'] ?? ''));

        $result = BitacoraRrhhService::addDocumento($id, $nombre, $_FILES['archivo'] ?? []);
        if (!$result) {
            JsonResponse::error('No se pudo subir el documento');
        }

        JsonResponse::custom([
            'success' => true,
            'message' => 'Documento subido correctamente',
        ]);
    }

    public function deleteDocumento()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? $_POST['id'] ?? 0);

        if (!BitacoraRrhhService::eliminarDocumento($id)) {
            JsonResponse::error('No tienes permiso para eliminar documentos');
        }

        JsonResponse::custom([
            'success' => true,
            'message' => 'Documento eliminado',
        ]);
    }

    public function getRegistros()
    {
        $permisos = BitacoraRrhhService::getPermisos();
        if (!$permisos['puedeVerVisualizaciones']) {
            JsonResponse::forbidden('No tienes permiso para ver visualizaciones');
        }

        $id = (int)($_GET['id'] ?? 0);
        JsonResponse::custom([
            'success'   => true,
            'registros' => BitacoraRrhhService::getRegistros($id),
        ]);
    }

    public function registrarVisualizacion()
    {
        $permisos = BitacoraRrhhService::getPermisos();
        if (!$permisos['puedeVerVisualizaciones']) {
            JsonResponse::forbidden('No tienes permiso para registrar visualizaciones');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? $_POST['id'] ?? 0);
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        BitacoraRrhhService::registrarVisualizacion($id, $idUsuario);

        JsonResponse::custom([
            'success' => true,
            'message' => 'Visualización registrada',
        ]);
    }
}
