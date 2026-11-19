<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Request;
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

        $contexto = ModuleStationService::getContext(BitacoraRrhhService::MODULE_KEY);
        $contextoNombre = $contexto['nombre'] ?? '';

        View::render('departamento-operativo/2-recursos-humanos/bitacora-rrhh/index', [
            'title'            => $title,
            'idYear'           => $idYear,
            'idMes'            => $idMes,
            'idEstacion'       => (int)($contexto['id_estacion'] ?? $contexto['id_depto'] ?? 0),
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

        $ctx = ModuleStationService::getContext(BitacoraRrhhService::MODULE_KEY);
        $post = Request::all();
        // La sesión manda cuando hay un contexto seleccionado. Si no está
        // guardado, aceptar el ID canónico enviado por la vista y validarlo
        // contra el alcance permitido por BitacoraRrhhService::crear().
        $idEstacionContexto = (int)($ctx['id_estacion'] ?? 0);
        $idDeptoContexto = (int)($ctx['id_depto'] ?? 0);
        // ModuleStationService puede devolver la clave del contexto con valor 0.
        // En ese caso debe prevalecer la opción concreta enviada por el selector.
        $idEstacion = $idEstacionContexto > 0
            ? $idEstacionContexto
            : ($idDeptoContexto > 0
                ? $idDeptoContexto
                : (int)($post['id_estacion'] ?? $post['idEstacion'] ?? 0));
        $idYear = (int)($post['id_year'] ?? $post['idYear'] ?? date('Y'));
        $idMes = (int)($post['id_mes'] ?? $post['idMes'] ?? date('n'));
        $descripcion = trim((string)($post['descripcion'] ?? $post['DescripcionRRHH'] ?? ''));

        if ($idEstacion <= 0 || $descripcion === '') {
            JsonResponse::error('Completa los campos obligatorios.');
        }

        $id = BitacoraRrhhService::crear($idEstacion, $idYear, $idMes, $descripcion);
        if (!$id) {
            JsonResponse::forbidden('No tienes acceso al contexto seleccionado');
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

        if (!BitacoraRrhhService::eliminar($id)) {
            JsonResponse::error('No tienes acceso al registro o ya no existe');
        }

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
            'detalle' => BitacoraRrhhService::getDetalle($id),
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
        $detalle = BitacoraRrhhService::getDetalle($id);
        if (!$detalle || !BitacoraRrhhService::puedeVerLocalidad((int)$detalle['id_estacion'])) {
            JsonResponse::forbidden('No tienes acceso a este registro');
        }

        JsonResponse::custom([
            'success'    => true,
            'detalle'    => $detalle,
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
        $id = (int)($_GET['id'] ?? 0);
        $detalle = BitacoraRrhhService::getDetalle($id);
        if (!$detalle || !BitacoraRrhhService::puedeVerLocalidad((int)$detalle['id_estacion'])) {
            JsonResponse::forbidden('No tienes acceso a este registro');
        }

        JsonResponse::custom([
            'success'   => true,
            'registros' => BitacoraRrhhService::getRegistros($id),
        ]);
    }

    public function registrarVisualizacion()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? $_POST['id'] ?? 0);
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        if (!BitacoraRrhhService::registrarVisualizacion($id, $idUsuario)) {
            JsonResponse::forbidden('No tienes acceso a este registro');
        }

        JsonResponse::custom([
            'success' => true,
            'message' => 'Visualización registrada',
        ]);
    }
}
