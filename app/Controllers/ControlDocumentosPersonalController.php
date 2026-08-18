<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ControlDocumentosPersonalService;
use App\Services\PersonalExcelService;
use App\Services\ModuleStationService;
use App\Services\KpiPersonalService;
use App\Services\DropdownYearMesService;
use App\Models\Operativo\RhPuestos;

class ControlDocumentosPersonalController extends BaseController
{
    public function index()
    {
        $permisos = ControlDocumentosPersonalService::getPermisos();
        $esMultiestacion = $permisos['multiestacion'];
        $idEstacion = $esMultiestacion ? 0 : $permisos['id_estacion'];

        $sessionUsuario = \App\Core\Session::get('usuario');
        if (!$esMultiestacion && !empty($sessionUsuario['id_estacion']) && $sessionUsuario['id_estacion'] == 2) {
            $idEstacion = 0;
        }

        $title = 'Control de Documentos del Personal';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add($title, '');

        if (!$this->guardModuleAccess('control-documentos-personal', $title, 'departamento-operativo')) {
            return;
        }

        View::render('departamento-operativo/2-recursos-humanos/control-documentos-personal/index', [
            'title'            => $title,
            'idEstacion'       => $idEstacion,
            'multiestacion'    => $esMultiestacion,
            'moduleStationKey' => 'control-documentos-personal',
            'puedeCrear'       => $permisos['puedeCrear'],
            'puedeEditar'      => $permisos['puedeEditar'],
            'puedeEliminar'    => $permisos['puedeEliminar'],
            'puedeDescargar'   => $permisos['puedeDescargar'],
            'nombrePuesto'     => $permisos['nombre_puesto'],
            'help'             => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/libs/select2/dist/js/select2.full.min.js?v=' . time(),
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/control-documentos-personal.datatable.init.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/control-documentos-personal.actions.init.js?v=' . time(),
            ],
            'links' => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/assets/libs/select2/dist/css/select2.min.css?v=' . time(),
                '/assets/css/select2-modal.css?v=' . time(),
            ],
        ], 'departamento-operativo');
    }

    public function getData()
    {
        header('Content-Type: application/json');
        $data = ControlDocumentosPersonalService::getPersonalList();
        echo json_encode(['success' => true, 'data' => $data]);
        return;
    }

    public function getDataInactivos()
    {
        header('Content-Type: application/json');
        $data = ControlDocumentosPersonalService::getPersonalInactivosList();
        echo json_encode(['success' => true, 'data' => $data]);
        return;
    }

    public function add()
    {
        header('Content-Type: application/json');
        $id = ControlDocumentosPersonalService::addPersonal($_POST, $_FILES);
        echo json_encode([
            'success' => (bool)$id,
            'message' => $id ? 'Personal agregado correctamente.' : 'Error al agregar personal.',
        ]);
        return;
    }

    public function edit()
    {
        header('Content-Type: application/json');
        $id = sanitize_input($_POST['id'] ?? null, 'int');

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID no proporcionado.']);
            return;
        }

        $updated = ControlDocumentosPersonalService::editPersonal($id, $_POST, $_FILES);
        echo json_encode([
            'success' => $updated,
            'message' => $updated ? 'Información actualizada correctamente.' : 'Error al actualizar.',
        ]);
        return;
    }

    public function delete()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $id = sanitize_input($data['id'] ?? null, 'int');

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID no proporcionado.']);
            return;
        }

        $deleted = ControlDocumentosPersonalService::deletePersonal($id);
        echo json_encode([
            'success' => $deleted,
            'message' => $deleted ? 'Personal eliminado correctamente.' : 'Error al eliminar.',
        ]);
        return;
    }

    public function getArchivosBaja()
    {
        header('Content-Type: application/json');
        $idBaja = sanitize_input($_GET['id_baja'] ?? null, 'int');
        if (!$idBaja) {
            echo json_encode(['success' => false, 'data' => []]);
            return;
        }
        $data = ControlDocumentosPersonalService::getArchivosBaja($idBaja);
        echo json_encode(['success' => true, 'data' => $data]);
        return;
    }

    public function uploadBajaArchivo()
    {
        header('Content-Type: application/json');
        $idBaja = sanitize_input($_POST['id_baja'] ?? null, 'int');
        $descripcion = sanitize_input($_POST['descripcion'] ?? null, 'string');

        if (!$idBaja || empty($descripcion)) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
            return;
        }

        if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== 0) {
            echo json_encode(['success' => false, 'message' => 'Archivo no proporcionado.']);
            return;
        }

        $result = ControlDocumentosPersonalService::uploadBajaArchivo($idBaja, $descripcion, $_FILES['archivo']);
        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Archivo subido correctamente.' : 'Error al subir archivo.',
        ]);
        return;
    }

    public function deleteBajaArchivo()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $id = sanitize_input($data['id'] ?? null, 'int');

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID no proporcionado.']);
            return;
        }

        $deleted = ControlDocumentosPersonalService::deleteBajaArchivo($id);
        echo json_encode([
            'success' => $deleted,
            'message' => $deleted ? 'Archivo eliminado correctamente.' : 'Error al eliminar archivo.',
        ]);
        return;
    }

    public function getComentariosBaja()
    {
        header('Content-Type: application/json');
        $idBaja = sanitize_input($_GET['id_baja'] ?? null, 'int');
        if (!$idBaja) {
            echo json_encode(['success' => false, 'data' => []]);
            return;
        }
        $data = ControlDocumentosPersonalService::getComentariosBaja($idBaja);
        echo json_encode(['success' => true, 'data' => $data]);
        return;
    }

    public function addComentarioBaja()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $idBaja = sanitize_input($data['id_baja'] ?? null, 'int');
        $comentario = sanitize_input($data['comentario'] ?? null, 'string');

        if (!$idBaja || empty($comentario)) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
            return;
        }

        $result = ControlDocumentosPersonalService::addComentarioBaja($idBaja, $comentario);
        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Comentario agregado.' : 'Error al agregar comentario.',
        ]);
        return;
    }

    public function getComentarios()
    {
        header('Content-Type: application/json');
        $idPersonal = sanitize_input($_GET['id_personal'] ?? null, 'int');
        if (!$idPersonal) {
            echo json_encode(['success' => false, 'data' => []]);
            return;
        }
        $data = ControlDocumentosPersonalService::getComentarios($idPersonal);
        echo json_encode(['success' => true, 'data' => $data]);
        return;
    }

    public function addComentario()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $idPersonal = sanitize_input($data['id_personal'] ?? null, 'int');
        $comentario = sanitize_input($data['comentario'] ?? null, 'string');

        if (!$idPersonal || empty($comentario)) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
            return;
        }

        $result = ControlDocumentosPersonalService::addComentario($idPersonal, $comentario);
        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Comentario agregado.' : 'Error al agregar comentario.',
        ]);
        return;
    }

    public function uploadDocumento()
    {
        header('Content-Type: application/json');
        $idPersonal = sanitize_input($_POST['id_personal'] ?? null, 'int');
        $tipo = sanitize_input($_POST['tipo'] ?? null, 'string');

        if (!$idPersonal || empty($tipo)) {
            echo json_encode(['success' => false, 'message' => 'Datos inválidos.']);
            return;
        }

        if (empty($_FILES['archivo'])) {
            echo json_encode(['success' => false, 'message' => 'Archivo no proporcionado.']);
            return;
        }

        $uploaded = ControlDocumentosPersonalService::uploadDocumento($idPersonal, $tipo, $_FILES['archivo']);
        echo json_encode([
            'success' => $uploaded,
            'message' => $uploaded ? 'Documento subido correctamente.' : 'Error al subir documento.',
        ]);
        return;
    }

    public function deleteDocumento()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $idPersonal = sanitize_input($data['id'] ?? null, 'int');
        $tipo = sanitize_input($data['tipo'] ?? ($_GET['tipo'] ?? ''), 'string');

        if (!$idPersonal || empty($tipo)) {
            echo json_encode(['success' => false, 'message' => 'Datos inválidos.']);
            return;
        }

        $deleted = ControlDocumentosPersonalService::deleteDocumento($idPersonal, $tipo);
        echo json_encode([
            'success' => $deleted,
            'message' => $deleted ? 'Documento eliminado.' : 'Error al eliminar.',
        ]);
        return;
    }

    public function addBaja()
    {
        header('Content-Type: application/json');
        $idPersonal = sanitize_input($_POST['id_personal'] ?? null, 'int');

        if (!$idPersonal) {
            echo json_encode(['success' => false, 'message' => 'ID no proporcionado.']);
            return;
        }

        $result = ControlDocumentosPersonalService::addBajaPersonal($idPersonal, $_POST, $_FILES);
        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Baja registrada correctamente.' : 'Error al registrar baja.',
        ]);
        return;
    }

    public function getAcceso()
    {
        header('Content-Type: application/json');
        $idPersonal = sanitize_input($_GET['id_personal'] ?? null, 'int');

        if (!$idPersonal) {
            echo json_encode(['success' => false, 'data' => null, 'message' => 'ID no proporcionado.']);
            return;
        }

        $data = ControlDocumentosPersonalService::getAcceso($idPersonal);
        echo json_encode(['success' => true, 'data' => $data]);
        return;
    }

    public function editarPin()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $idPersonal = sanitize_input($data['id_personal'] ?? null, 'int');
        $pin = sanitize_input($data['pin'] ?? null, 'string');

        if (!$idPersonal) {
            echo json_encode(['success' => false, 'message' => 'ID no proporcionado.']);
            return;
        }

        $result = ControlDocumentosPersonalService::editarPin($idPersonal, $pin);
        echo json_encode([
            'success' => $result['success'],
            'message' => $result['message'],
        ]);
        return;
    }

    public function getPuestos()
    {
        header('Content-Type: application/json');
        $puestos = RhPuestos::where('status', 1)
            ->orderBy('puesto')
            ->get(['id', 'puesto']);
        echo json_encode(['success' => true, 'data' => $puestos->toArray()]);
        return;
    }

    public function getEstaciones()
    {
        header('Content-Type: application/json');
        $data = ControlDocumentosPersonalService::getEstaciones();
        echo json_encode(['success' => true, 'data' => $data]);
        return;
    }

    public function getPersonalById()
    {
        header('Content-Type: application/json');
        $id = sanitize_input($_GET['id'] ?? null, 'int');
        if (!$id) {
            echo json_encode(['success' => false, 'data' => null]);
            return;
        }
        $data = ControlDocumentosPersonalService::getPersonalById($id);
        echo json_encode(['success' => (bool)$data, 'data' => $data]);
        return;
    }

    public function excel()
    {
        PersonalExcelService::generarYDescargar();
    }

    public function kpi($idYear)
    {

        /*
$permisos = ControlDocumentosPersonalService::getPermisos();
if (!$permisos['puedeLeer']) {
View::render('errors/403', [], 'departamento-operativo');
return;
}
*/

        $validados = DropdownYearMesService::validarYearMes($idYear, 1);
        $idYear = $validados['idYear'];

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Control de Documentos del Personal', '/departamento-operativo/recursos-humanos/control-documentos-personal');
        Breadcrumb::add(DropdownYearMesService::dropdownYearManual($idYear, 1), '');

        View::render('departamento-operativo/2-recursos-humanos/control-documentos-personal/kpi-personal', [
            'title'    => 'Evaluación Personal (KPI\'s), ' . $idYear,
            'idYear'   => $idYear,
            'opciones' => KpiPersonalService::getOpciones(),
            'help'     => false,
            'moduleStationKey' => 'control-documentos-personal',
            'yearMesTemplate' => '/departamento-operativo/recursos-humanos/control-documentos-personal-kpi/{year}',
            'scripts'  => [
                '/assets/libs/apexcharts/dist/apexcharts.min.js',
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/kpi-personal.actions.init.js?v=' . time(),
            ],
        ], 'departamento-operativo');
    }

    public function kpiData($idYear, $tipo)
    {
        try {
            header('Content-Type: application/json');

            $validados = DropdownYearMesService::validarYearMes($idYear, 1);
            $idYear = $validados['idYear'];

            $tipo = (int) $tipo;
            if ($tipo < 1 || $tipo > 2) {
                echo json_encode(['success' => false, 'message' => 'Tipo inválido']);
                return;
            }

            $ctx = ModuleStationService::getContext('control-documentos-personal');
            $idEstacion = $ctx['id_estacion'] !== null ? (int) $ctx['id_estacion'] : null;
            $idDepto = $ctx['id_depto'] !== null ? (int) $ctx['id_depto'] : null;

            if ($idEstacion === null && $idDepto !== null) {
                $idEstacion = $idDepto;
            }

            $data = KpiPersonalService::getData($idEstacion, $idYear, $tipo);
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            error_log('Error KPI personal data: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error al cargar datos: ' . $e->getMessage()]);
        }
    }

    public function asistencia($id)
    {
        $idPersonal = (int)$id;
        if (!$idPersonal) {
            header('Location: /departamento-operativo/recursos-humanos/control-documentos-personal');
            exit;
        }

        $data = ControlDocumentosPersonalService::getAsistenciaPersonal($idPersonal);
        if (!$data) {
            header('Location: /departamento-operativo/recursos-humanos/control-documentos-personal');
            exit;
        }

        $title = 'Asistencia (' . $data['nombre_completo'] . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Control de Documentos del Personal', '/departamento-operativo/recursos-humanos/control-documentos-personal');
        Breadcrumb::add($title);

        View::render('departamento-operativo/2-recursos-humanos/control-documentos-personal/asistencia', [
            'title'    => $title,
            'personal' => $data,
            'help'     => false,
            'scripts'  => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/departamento-operativo/2-recursos-humanos/control-documentos-personal.asistencia.js?v=' . time(),
            ],
            'links' => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
        ], 'departamento-operativo');
    }

    public function getAsistenciaData()
    {
        header('Content-Type: application/json');
        $idPersonal = sanitize_input($_GET['id_personal'] ?? null, 'int');
        if (!$idPersonal) {
            echo json_encode(['success' => false, 'data' => []]);
            return;
        }
        $data = ControlDocumentosPersonalService::getAsistenciaData($idPersonal);
        echo json_encode(['success' => true, 'data' => $data]);
        return;
    }

    public function crearIncidencia()
    {
        header('Content-Type: application/json');
        $idAsistencia = sanitize_input($_POST['id_asistencia'] ?? null, 'int');
        $incidencia   = sanitize_input($_POST['incidencia'] ?? null, 'string');
        $comentario   = sanitize_input($_POST['comentario'] ?? null, 'string');
        $documento    = !empty($_POST['documento']) ? sanitize_input($_POST['documento'], 'string') : null;

        if (!$idAsistencia || !$incidencia) {
            echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos.']);
            return;
        }

        $result = ControlDocumentosPersonalService::crearIncidencia($idAsistencia, $incidencia, $comentario, $documento);
        echo json_encode($result);
        return;
    }

    public function agregarIncidencia()
    {
        header('Content-Type: application/json');
        $idAsistencia         = sanitize_input($_POST['id_asistencia'] ?? null, 'int');
        $idIncidenciaCatalogo = sanitize_input($_POST['id_incidencia'] ?? null, 'int');
        $comentario           = sanitize_input($_POST['comentario'] ?? null, 'string');
        $fechaInicio          = !empty($_POST['fecha_inicio']) ? sanitize_input($_POST['fecha_inicio'], 'string') : null;
        $fechaFin             = !empty($_POST['fecha_fin']) ? sanitize_input($_POST['fecha_fin'], 'string') : null;
        $sueldoDia            = isset($_POST['sueldo_dia']) && $_POST['sueldo_dia'] !== '' ? sanitize_input($_POST['sueldo_dia'], 'float') : null;

        if (!$idAsistencia || !$idIncidenciaCatalogo) {
            echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos.']);
            return;
        }

        $documentoRuta = null;
        if (!empty($_FILES['documento']) && is_array($_FILES['documento']) && !empty($_FILES['documento']['tmp_name']) && $_FILES['documento']['error'] === UPLOAD_ERR_OK) {
            $carpeta = ControlDocumentosPersonalService::getUploadDir() . 'incidencias/';
            if (!is_dir($carpeta)) mkdir($carpeta, 0777, true);
            $ext = strtolower(pathinfo($_FILES['documento']['name'], PATHINFO_EXTENSION));
            $nombre = 'incidencia_' . $idAsistencia . '_' . time() . '.' . $ext;

            if (move_uploaded_file($_FILES['documento']['tmp_name'], $carpeta . $nombre)) {
                $documentoRuta = $nombre;
            }
        }

        $result = ControlDocumentosPersonalService::agregarIncidencia($idAsistencia, $idIncidenciaCatalogo, $comentario, $fechaInicio, $fechaFin, $sueldoDia, $documentoRuta);
        echo json_encode($result);
        return;
    }

    public function subirDocumentoIncidencia()
    {
        header('Content-Type: application/json');
        $idAsistencia = sanitize_input($_POST['id_asistencia'] ?? null, 'int');
        $fechaInicio  = !empty($_POST['fecha_inicio']) ? sanitize_input($_POST['fecha_inicio'], 'string') : null;
        $fechaFin     = !empty($_POST['fecha_fin']) ? sanitize_input($_POST['fecha_fin'], 'string') : null;
        $sueldoDia    = isset($_POST['sueldo_dia']) && $_POST['sueldo_dia'] !== '' ? sanitize_input($_POST['sueldo_dia'], 'float') : null;

        if (!$idAsistencia) {
            echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos.']);
            return;
        }

        if (empty($_FILES['documento']) || !is_array($_FILES['documento']) || empty($_FILES['documento']['tmp_name']) || $_FILES['documento']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'No se pudo subir el archivo. Asegúrese de que sea formato PDF.']);
            return;
        }

        $carpeta = ControlDocumentosPersonalService::getUploadDir() . 'incidencias/';
        if (!is_dir($carpeta)) mkdir($carpeta, 0777, true);
        $ext = strtolower(pathinfo($_FILES['documento']['name'], PATHINFO_EXTENSION));
        $nombre = 'incidencia_' . $idAsistencia . '_' . time() . '.' . $ext;
        $documentoRuta = null;

        if (move_uploaded_file($_FILES['documento']['tmp_name'], $carpeta . $nombre)) {
            $documentoRuta = $nombre;
        }

        if (!$documentoRuta) {
            echo json_encode(['success' => false, 'message' => 'No se pudo subir el archivo. Asegúrese de que sea formato PDF.']);
            return;
        }

        $result = ControlDocumentosPersonalService::subirDocumentoIncidencia($idAsistencia, $documentoRuta, $fechaInicio, $fechaFin, $sueldoDia);
        echo json_encode($result);
        return;
    }

    public function getIncidenciasCatalogo()
    {
        header('Content-Type: application/json');
        $data = ControlDocumentosPersonalService::getIncidenciasCatalogo();
        echo json_encode(['success' => true, 'data' => $data]);
        return;
    }

    public function getIncidenciaPorAsistencia()
    {
        header('Content-Type: application/json');
        $idAsistencia = sanitize_input($_GET['id_asistencia'] ?? null, 'int');
        if (!$idAsistencia) {
            echo json_encode(['success' => false, 'data' => null]);
            return;
        }
        $data = ControlDocumentosPersonalService::getIncidenciaPorAsistencia($idAsistencia);
        echo json_encode(['success' => true, 'data' => $data]);
        return;
    }

    public function acceso($id)
    {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Funcionalidad en desarrollo.']);
        return;
    }

    public function bajaDetalle($id)
    {
        $idBaja = (int)$id;
        if (!$idBaja) {
            header('Location: /departamento-operativo/recursos-humanos/control-documentos-personal');
            exit;
        }

        $data = ControlDocumentosPersonalService::getBajaDetalle($idBaja);
        if (!$data) {
            header('Location: /departamento-operativo/recursos-humanos/control-documentos-personal');
            exit;
        }

        $title = 'Detalle Baja de Personal (' . $data['nombre_completo'] . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Control de Documentos del Personal', '/departamento-operativo/recursos-humanos/control-documentos-personal');
        Breadcrumb::add($title);

        View::render('departamento-operativo/2-recursos-humanos/control-documentos-personal/detalle-baja', [
            'title'  => $title,
            'baja'   => $data,
            'help'   => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/control-documentos-personal.detalle-baja.js?v=' . time(),
            ],
        ], 'departamento-operativo');
    }
}
