<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\Logger;
use App\Services\ControlDocumentosPersonalService;
use App\Services\PersonalExcelService;
use App\Services\ModuleStationService;
use App\Services\KpiPersonalService;
use App\Services\DropdownYearMesService;
use App\Models\Operativo\RhPuestos;

class ControlDocumentosPersonalController extends BaseController
{
    public function bajasPersonal()
    {
        $title = 'Bajas de Personal';
        if (!$this->guardModuleAccess('control-documentos-personal', $title, 'departamento-operativo')) return;
        $permisos = ControlDocumentosPersonalService::getPermisos();
        if (!$permisos['puedeLeer']) {
            http_response_code(403);
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add($title, '');

        View::render('departamento-operativo/2-recursos-humanos/control-documentos-personal/bajas-personal', [
            'title' => $title,
            'moduleStationKey' => 'bajas-personal',
            'pendientesData' => ControlDocumentosPersonalService::getBajasPendientes(),
            'pendientesActuales' => ControlDocumentosPersonalService::getBajaPendienteCountActual(),
            'puedeEditarBaja' => $permisos['puedeEditar'],
            'puedeEliminarBaja' => $permisos['puedeEliminarBaja'],
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/bajas-personal.datatable.init.js?v=' . time(),
            ],
            'links' => ['/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'],
            'help' => false,
        ], 'departamento-operativo');
    }

    public function getBajasData()
    {
        if (!ControlDocumentosPersonalService::getPermisos()['puedeLeer']) JsonResponse::forbidden('No tienes permiso de lectura para Bajas de Personal.');
        JsonResponse::custom(['success' => true, 'data' => ControlDocumentosPersonalService::getBajasList()]);
    }

    public function updateBaja()
    {
        $id = filter_var(Request::input('id_baja'), FILTER_VALIDATE_INT);
        $estado = filter_var(Request::input('estado_proceso'), FILTER_VALIDATE_INT);
        if (!$id || $estado === false) JsonResponse::validation('Completa los datos del proceso.');
        $proceso = trim((string)Request::input('proceso', ''));
        if (!ControlDocumentosPersonalService::updateBajaProceso((int)$id, $proceso, (int)$estado)) {
            JsonResponse::forbidden('No fue posible actualizar la baja. Verifica permisos, contexto y estatus.');
        }
        JsonResponse::success('Proceso de baja actualizado.');
    }

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
        $errors = ControlDocumentosPersonalService::validatePersonalInput($_POST);
        if ($errors) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Completa los campos obligatorios.',
                'errors' => $errors,
            ]);
            return;
        }
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

        $errors = ControlDocumentosPersonalService::validatePersonalInput($_POST);
        if ($errors) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Completa los campos obligatorios.',
                'errors' => $errors,
            ]);
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
        $idBaja = filter_var(Request::input('id_baja'), FILTER_VALIDATE_INT);
        if (!$idBaja) JsonResponse::validation('El identificador de la baja no es válido.');
        if (!ControlDocumentosPersonalService::getBajaDetalle((int)$idBaja)) JsonResponse::notFound('Baja no encontrada.');
        JsonResponse::custom(['success' => true, 'data' => ControlDocumentosPersonalService::getArchivosBaja((int)$idBaja)]);
    }

    public function uploadBajaArchivo()
    {
        $idBaja = filter_var(Request::input('id_baja'), FILTER_VALIDATE_INT);
        $descripcion = trim((string)Request::input('descripcion', ''));
        $archivo = $_FILES['archivo'] ?? [];
        if (!$idBaja || $descripcion === '' || (int)($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            JsonResponse::validation('Indica la descripción y selecciona un archivo.');
        }
        if (!ControlDocumentosPersonalService::uploadBajaArchivo((int)$idBaja, $descripcion, $archivo)) {
            JsonResponse::forbidden('No fue posible subir el archivo. Verifica permisos y contexto.');
        }
        JsonResponse::success('Archivo subido correctamente.');
    }

    public function deleteBajaArchivo()
    {
        $id = filter_var(Request::input('id'), FILTER_VALIDATE_INT);
        if (!$id) JsonResponse::validation('El identificador del archivo no es válido.');
        if (!ControlDocumentosPersonalService::deleteBajaArchivo((int)$id)) JsonResponse::forbidden('No fue posible eliminar el archivo.');
        JsonResponse::success('Archivo eliminado correctamente.');
    }

    public function getComentariosBaja()
    {
        $idBaja = filter_var(Request::input('id_baja'), FILTER_VALIDATE_INT);
        if (!$idBaja) JsonResponse::validation('El identificador de la baja no es válido.');
        if (!ControlDocumentosPersonalService::getBajaDetalle((int)$idBaja)) JsonResponse::notFound('Baja no encontrada.');
        JsonResponse::custom(['success' => true, 'data' => ControlDocumentosPersonalService::getComentariosBaja((int)$idBaja)]);
    }

    public function addComentarioBaja()
    {
        $idBaja = filter_var(Request::input('id_baja'), FILTER_VALIDATE_INT);
        $comentario = trim((string)Request::input('comentario', ''));
        if (!$idBaja || $comentario === '') JsonResponse::validation('Escribe el comentario antes de enviarlo.');
        if (!ControlDocumentosPersonalService::addComentarioBaja((int)$idBaja, $comentario)) JsonResponse::forbidden('No fue posible agregar el comentario.');
        JsonResponse::success('Comentario agregado.');
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
        $detalle = ControlDocumentosPersonalService::getComentarioResumen($idPersonal);
        echo json_encode(['success' => true, 'data' => $data, 'detalle' => $detalle]);
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
        $idPersonal = filter_var(Request::input('id_personal'), FILTER_VALIDATE_INT);
        if (!$idPersonal) JsonResponse::validation('El identificador del colaborador no es válido.');
        $errors = [];
        if (!normalizarFecha(Request::input('fecha_baja', ''))) $errors['fecha_baja'] = true;
        if (trim((string)Request::input('motivo', '')) === '') $errors['motivo'] = true;
        if ($errors) JsonResponse::validation('Completa los campos obligatorios.', $errors);
        try {
            $registrada = ControlDocumentosPersonalService::addBajaPersonal((int)$idPersonal, Request::all(), $_FILES);
        } catch (\Illuminate\Database\QueryException $exception) {
            $driverCode = (int)($exception->errorInfo[1] ?? 0);
            $driverMessage = (string)($exception->errorInfo[2] ?? '');
            $foreignConstraint = null;
            $childTable = null;
            if ($driverCode === 1452) {
                if (preg_match("/CONSTRAINT [`']([^`']+)[`']/i", $driverMessage, $matches)) $foreignConstraint = $matches[1];
                if (preg_match("/fails \(`[^`]+`\.`([^`]+)`/i", $driverMessage, $matches)) $childTable = $matches[1];
            }
            $detail = match ($driverCode) {
                1048, 1054, 1364 => preg_match("/(?:Unknown column|Field) '([^']+)'/i", $driverMessage, $matches) ? 'column:' . $matches[1] : 'column_schema_mismatch',
                1062 => preg_match("/for key '([^']+)'/i", $driverMessage, $matches) ? 'duplicate_key:' . $matches[1] : 'duplicate_key',
                1452 => 'foreign_key_constraint',
                default => 'database_write_failure',
            };
            Logger::error('No se pudo registrar la baja de personal por un error de base de datos.', [
                'sqlstate' => (string)$exception->getCode(),
                'driver_code' => $driverCode,
                'detail' => $detail,
                'constraint' => $foreignConstraint,
                'child_table' => $childTable,
            ]);
            JsonResponse::error('No fue posible registrar la baja por un error de base de datos (' . $driverCode . ').', 500);
        }
        if (!$registrada) {
            JsonResponse::forbidden('No fue posible registrar la baja. Verifica permisos, datos y contexto.');
        }
        JsonResponse::success('Baja registrada correctamente.');
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

        if (mb_strlen(trim((string)$pin), 'UTF-8') < 5) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'El PIN debe contener al menos 5 caracteres.']);
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

        $title = 'Asistencia ('. $data['nombre_completo'] . ')';

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
        $sueldoDiaRaw          = str_replace(',', '', trim((string)($_POST['sueldo_dia'] ?? '')));
        $sueldoDia            = $sueldoDiaRaw !== '' && is_numeric($sueldoDiaRaw) ? (float)$sueldoDiaRaw : null;

        if (!$idAsistencia || !$idIncidenciaCatalogo || trim((string)$comentario) === '') {
            echo json_encode(['success' => false, 'message' => 'Completa los campos obligatorios.']);
            return;
        }

        $documentoRuta = null;
        $archivoEnviado = isset($_FILES['documento'])
            && is_array($_FILES['documento'])
            && (int)($_FILES['documento']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($archivoEnviado) {
            if (!$this->isPdfUpload($_FILES['documento'])) {
                echo json_encode(['success' => false, 'message' => 'El archivo debe ser un PDF válido.']);
                return;
            }
            $carpeta = ControlDocumentosPersonalService::getIncidenciaUploadDir();
            $nombre = 'incidencia_' . $idAsistencia . '_' . bin2hex(random_bytes(6)) . '.pdf';

            if (move_uploaded_file($_FILES['documento']['tmp_name'], $carpeta . $nombre)) {
                $documentoRuta = $nombre;
            } else {
                echo json_encode(['success' => false, 'message' => 'No fue posible guardar el archivo PDF.']);
                return;
            }
        }

        $result = ControlDocumentosPersonalService::agregarIncidencia($idAsistencia, $idIncidenciaCatalogo, $comentario, $fechaInicio, $fechaFin, $sueldoDia, $documentoRuta);
        if (empty($result['success']) && $documentoRuta) {
            @unlink(ControlDocumentosPersonalService::getIncidenciaUploadDir() . $documentoRuta);
        }
        echo json_encode($result);
        return;
    }

    public function subirDocumentoIncidencia()
    {
        header('Content-Type: application/json');
        $idAsistencia = sanitize_input($_POST['id_asistencia'] ?? null, 'int');
        $fechaInicio  = !empty($_POST['fecha_inicio']) ? sanitize_input($_POST['fecha_inicio'], 'string') : null;
        $fechaFin     = !empty($_POST['fecha_fin']) ? sanitize_input($_POST['fecha_fin'], 'string') : null;
        $sueldoDiaRaw = str_replace(',', '', trim((string)($_POST['sueldo_dia'] ?? '')));
        $sueldoDia    = $sueldoDiaRaw !== '' && is_numeric($sueldoDiaRaw) ? (float)$sueldoDiaRaw : null;

        if (!$idAsistencia) {
            echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos.']);
            return;
        }

        if (empty($_FILES['documento']) || !is_array($_FILES['documento']) || empty($_FILES['documento']['tmp_name']) || $_FILES['documento']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'No se pudo subir el archivo. Asegúrese de que sea formato PDF.']);
            return;
        }

        if (!$this->isPdfUpload($_FILES['documento'])) {
            echo json_encode(['success' => false, 'message' => 'El archivo debe ser un PDF válido.']);
            return;
        }

        $metadatosParciales = $fechaInicio !== null || $fechaFin !== null || $sueldoDiaRaw !== '';
        if ($metadatosParciales && (!$fechaInicio || !$fechaFin || $sueldoDia === null || $sueldoDia < 0)) {
            echo json_encode(['success' => false, 'message' => 'Completa los campos obligatorios.']);
            return;
        }
        if ($fechaInicio && $fechaFin && $fechaFin < $fechaInicio) {
            echo json_encode(['success' => false, 'message' => 'La fecha fin debe ser mayor o igual a la fecha de inicio.']);
            return;
        }

        $carpeta = ControlDocumentosPersonalService::getIncidenciaUploadDir();
        $nombre = 'incidencia_' . $idAsistencia . '_' . bin2hex(random_bytes(6)) . '.pdf';
        $documentoRuta = null;

        if (move_uploaded_file($_FILES['documento']['tmp_name'], $carpeta . $nombre)) {
            $documentoRuta = $nombre;
        }

        if (!$documentoRuta) {
            echo json_encode(['success' => false, 'message' => 'No se pudo subir el archivo. Asegúrese de que sea formato PDF.']);
            return;
        }

        $result = ControlDocumentosPersonalService::subirDocumentoIncidencia($idAsistencia, $documentoRuta, $fechaInicio, $fechaFin, $sueldoDia);
        if (empty($result['success'])) {
            @unlink($carpeta . $documentoRuta);
        }
        echo json_encode($result);
        return;
    }

    private function isPdfUpload(array $file): bool
    {
        if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION)) !== 'pdf'
            || empty($file['tmp_name']) || !is_file($file['tmp_name'])) {
            return false;
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                return $mime === 'application/pdf';
            }
        }

        return true;
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
        $permisos = ControlDocumentosPersonalService::getPermisos();

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Bajas de Personal', '/departamento-operativo/recursos-humanos/bajas-personal');
        Breadcrumb::add($title);

        View::render('departamento-operativo/2-recursos-humanos/control-documentos-personal/detalle-baja', [
            'title'  => $title,
            'baja'   => $data,
            'moduleStationKey' => 'bajas-personal',
            'ocultarSelectorEstacion' => true,
            'nombreBadgeEstacion' => $data['nombre_estacion'],
            'puedeEditarBaja' => $permisos['puedeEditar'],
            'puedeEliminarBaja' => $permisos['puedeEliminarBaja'],
            'puedeDescargarBaja' => $permisos['puedeDescargar'],
            'help'   => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/control-documentos-personal.detalle-baja.js?v=' . time(),
            ],
        ], 'departamento-operativo');
    }
}
