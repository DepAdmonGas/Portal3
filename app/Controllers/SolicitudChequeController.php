<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Session;
use App\Services\DropdownYearMesService;
use App\Services\SolicitudChequeService;
use App\Services\ModuloDptoOperativoService;
use App\Services\ModuleStationService;
use App\Services\MultiestacionService;

use App\Models\Operativo\SolicitudChequeDocumento;
use App\Models\Estacion;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class SolicitudChequeController extends BaseController
{
protected string $modulo = 'corporativo';

public function redirectToPeriod()
{
$year = date('Y');
$mes = date('n');
header('Location: /departamento-operativo/solicitud-cheque/' . $year . '/' . $mes);
exit;
}

public function index($idYear, $idMes)
{
$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

$puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer') || ModuloDptoOperativoService::validaPermiso('personal-general', 'leer');
if (!$puedeLeer) {
View::render('errors/403', [], 'departamento-operativo');
return;
}

$permisos = SolicitudChequeService::getPermisos();
$esMultiestacion = $permisos['multiestacion'];

$idEstacion = $esMultiestacion ? 0 : $permisos['id_estacion'];
$idPuesto = $permisos['id_puesto'];
$idDepto = 0;

$nombreFiltro = 'Todas las estaciones y departamentos';
if (!$esMultiestacion) {
$estacionModel = Estacion::find($idEstacion);
$nombreFiltro = $estacionModel ? $estacionModel->nombre : 'Estación #' . $idEstacion;
}

$esMesActual = (Carbon::now()->year == $idYear && Carbon::now()->month == $idMes);

$titleWithPeriod = 'Solicitud de Cheques (' . nombremes($idMes) . ' ' . $idYear . ')';
$titleH4 = $titleWithPeriod;

$facturaBadgeHtml = ' <span id="factura-status-breadcrumb-badge"></span>';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
Breadcrumb::add($titleWithPeriod, '');
Breadcrumb::add(DropdownYearMesService::dropdownMes($idYear, $idMes), '');
Breadcrumb::add(DropdownYearMesService::dropdownYearManual($idYear, $idMes) . $facturaBadgeHtml, '');

if (!$this->guardModuleAccess('solicitud-cheques', $titleWithPeriod, 'departamento-operativo')) {
return;
}

$yearMesTemplate = '/departamento-operativo/solicitud-cheque/{year}/{mes}';

$pendingCounts = SolicitudChequeService::getPendingCounts($idYear, $idMes);

$esEstacionNormal = $idEstacion <= 7 || $idEstacion == 14;

$ocultarTools = $esMultiestacion && $idEstacion === 0 && $idDepto === 0;
$estacionesFiltradas = [];
$departamentosFiltrados = [];
if ($esMultiestacion) {
$allowedStationIds = SolicitudChequeService::getAllowedStationIds();
foreach ($pendingCounts['stations'] as $s) {
if (in_array($s['id'], $allowedStationIds)) {
$estacionesFiltradas[] = $s;
}
}
$allowedDeptIds = SolicitudChequeService::getAllowedDeptIds();
foreach ($pendingCounts['departments'] as $d) {
if (in_array($d['id_puesto'], $allowedDeptIds)) {
$departamentosFiltrados[] = $d;
}
}
}

if ($permisos['es_gestoria'] && !MultiestacionService::isEnabled()) {
ModuleStationService::setContext('solicitud-cheques', 8, 5);
$idEstacion = 8;
$idDepto = 5;
$nombreFiltro = 'Gestoría';
$estacionesFiltradas = array_filter($estacionesFiltradas, fn($s) => $s['id'] == 8);
$departamentosFiltrados = array_filter($departamentosFiltrados, fn($d) => $d['id_puesto'] == 5);
}

$totalPendientes = array_sum(array_column($estacionesFiltradas, 'pendientes'))
+ array_sum(array_column($departamentosFiltrados, 'pendientes'));

$pendientesMap = ['total' => $totalPendientes];
foreach ($pendingCounts['stations'] as $s) {
$pendientesMap['estacion_' . $s['id']] = $s['pendientes'];
}
foreach ($pendingCounts['departments'] as $d) {
$pendientesMap['depto_' . $d['id_puesto']] = $d['pendientes'];
}

$data = [
'title' => $titleH4,
'idYear' => $idYear,
'idMes' => $idMes,
'idEstacion' => $idEstacion,
'idDepto' => $idDepto,
'esMesActual' => $esMesActual,
'esEstacionNormal' => $esEstacionNormal,
'multiestacion' => $esMultiestacion,
'yearMesTemplate' => $yearMesTemplate,
'ocultarTools' => $ocultarTools,
'nombreFiltro' => $nombreFiltro,
'estacionesFiltradas' => $estacionesFiltradas,
'departamentosFiltrados' => $departamentosFiltrados,
'totalPendientes' => $totalPendientes,
'moduleStationKey' => 'solicitud-cheques',
'pendientesData' => $pendientesMap,
'esGestoria' => $permisos['es_gestoria'],
'esDireccionOperaciones' => $permisos['es_direccion_operaciones'],
'esContabilidad' => $permisos['es_contabilidad'],
'esComercializadora' => $permisos['es_comercializadora'],
'esUser30' => $permisos['es_user_30'],
'puedeCrear' => $permisos['puede_crear'],
'puedeEditar' => $permisos['puede_editar'],
'puedeEliminar' => $permisos['puede_eliminar'],
'puedeFirmarVOBO' => $permisos['puede_firmar_vobo'],
'puedeVerComentarios' => $permisos['puede_ver_comentarios'],
'puedeAgregarComentarios' => $permisos['puede_agregar_comentarios'],
'puedeAgregarDocumentos' => $permisos['puede_agregar_documentos'],
'puedeGestionarPagos' => $permisos['puede_gestionar_pagos'],
'puedeGestionarTelcel' => $permisos['puede_gestionar_telcel'],
'puedeExportar' => $permisos['puede_exportar'],
'idUsuario' => $permisos['id_usuario'],
'idPuesto' => $permisos['id_puesto'],
'nombrePuesto' => $permisos['nombre_puesto'],
'pendingCounts' => $pendingCounts,
'pendientesJson' => json_encode($pendientesMap),
'help' => false,
'links' => [
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
'/assets/libs/select2/dist/css/select2.min.css',
],
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/libs/select2/dist/js/select2.full.min.js',
'/assets/js/core/module-station-selector.js?v=' . time(),
'/assets/js/departamento-operativo/1-corporativo/solicitud-cheque.datatable.init.js?v=' . time(),
'/assets/js/departamento-operativo/1-corporativo/actions.solicitud-cheque.init.js?v=' . time(),
],
];

View::render('departamento-operativo/1-corporativo/solicitud-cheque/index', $data, 'departamento-operativo');
}

public function crear($idYear, $idMes, $idEstacion = 0, $idDepto = 0)
{
$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

$puedeCrear = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer');
if (!$puedeCrear) {
View::render('errors/403', [], 'departamento-operativo');
return;
}

$title = 'Crear Solicitud de Cheque';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
Breadcrumb::add('Solicitud de Cheques (' . nombremes($idMes) . ' ' . $idYear . ')', '/departamento-operativo/solicitud-cheque/' . $idYear . '/' . $idMes);
Breadcrumb::add('<span class="breadcrumb-item active">' . $title . '</span>', '');

$sessionUsuario = Session::get('usuario');
if ($idEstacion === 0) {
$idEstacion = $sessionUsuario['id_estacion'] ?? 0;
}

$deptoNames = [5 => 'Gestoría', 4 => 'Comercializadora', 18 => 'Quitarga', 19 => 'Operación servicio y mantenimiento de personal', 23 => 'BANCAMIFEL, SOCIEDAD ANÓNIMA, FIDEICOMISO 2176/2016'];
$nombreContexto = $idDepto > 0 ? ($deptoNames[$idDepto] ?? '') : (Estacion::find($idEstacion)?->nombre ?? '');

$contextBadgeHtml = '<span id="contextBadge" class="mb-1 badge rounded-pill text-bg-info w-auto">' . htmlspecialchars($nombreContexto ?? '', ENT_QUOTES, 'UTF-8') . '</span>';

$data = [
'title' => $title,
'idYear' => $idYear,
'idMes' => $idMes,
'idEstacion' => $idEstacion,
'idDepto' => $idDepto,
'nombreContexto' => $nombreContexto,
'contextBadgeHtml' => $contextBadgeHtml,
'ocultarSelectorEstacion' => true,
'help' => false,
'scripts' => [
'/assets/libs/signature_pad/docs/js/signature_pad.umd.min.js?v=' . time(),
'/assets/js/departamento-operativo/1-corporativo/solicitud-cheque.crear.js?v=' . time(),
],
];

View::render('departamento-operativo/1-corporativo/solicitud-cheque/crear', $data, 'departamento-operativo');
}

public function editar($id)
{
$detalle = SolicitudChequeService::getDetalle((int)$id);
if (!$detalle) {
http_response_code(404);
echo 'Solicitud no encontrada';
exit;
}

$puedeEditar = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer');
if (!$puedeEditar) {
View::render('errors/403', [], 'departamento-operativo');
return;
}

$title = 'Editar Solicitud de Cheque (# ' . $id . ')';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
Breadcrumb::add('Solicitud de Cheques (' . nombremes($detalle['id_mes']) . ' ' . $detalle['id_year'] . ')', '/departamento-operativo/solicitud-cheque/' . $detalle['id_year'] . '/' . $detalle['id_mes']);
Breadcrumb::add('<span class="breadcrumb-item active">' . $title . '</span>', '');

$idEstacion = $detalle['id_estacion'] ?? 0;
$idDepto = $detalle['depto'] ?? 0;

$deptoNames = [5 => 'Gestoría', 4 => 'Comercializadora', 18 => 'Quitarga', 19 => 'Operación servicio y mantenimiento de personal', 23 => 'BANCAMIFEL, SOCIEDAD ANÓNIMA, FIDEICOMISO 2176/2016'];
$nombreContexto = $idDepto > 0 ? ($deptoNames[$idDepto] ?? '') : (Estacion::find($idEstacion)?->nombre ?? '');

$contextBadgeHtml = '<span id="contextBadge" class="mb-1 badge rounded-pill text-bg-info w-auto">' . htmlspecialchars($nombreContexto ?? '', ENT_QUOTES, 'UTF-8') . '</span>';

$data = [
'title' => $title,
'detalle' => $detalle,
'idEstacion' => $idEstacion,
'idDepto' => $idDepto,
'nombreContexto' => $nombreContexto,
'contextBadgeHtml' => $contextBadgeHtml,
'ocultarSelectorEstacion' => true,
'help' => false,
'scripts' => [
'/assets/libs/signature_pad/docs/js/signature_pad.umd.min.js?v=' . time(),
'/assets/js/departamento-operativo/1-corporativo/solicitud-cheque.editar.js?v=' . time(),
],
];

View::render('departamento-operativo/1-corporativo/solicitud-cheque/editar', $data, 'departamento-operativo');
}

public function getData($idYear, $idMes)
{
header('Content-Type: application/json; charset=utf-8');

try {
$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

$puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer') || ModuloDptoOperativoService::validaPermiso('personal-general', 'leer');
if (!$puedeLeer) {
echo json_encode(['success' => false, 'data' => []]);
exit;
}

$estacionFilter = isset($_REQUEST['estacion']) ? (int)$_REQUEST['estacion'] : null;
$deptoFilter = isset($_REQUEST['depto']) ? (int)$_REQUEST['depto'] : null;

$rows = SolicitudChequeService::getData($idYear, $idMes, $estacionFilter, $deptoFilter);
$permisos = SolicitudChequeService::getPermisos();

echo json_encode([
'success' => true,
'data' => $rows,
'permisos' => [
'puede_editar' => $permisos['puede_editar'],
'puede_eliminar' => $permisos['puede_eliminar'],
'puede_firmar_vobo' => $permisos['puede_firmar_vobo'],
'puede_ver_comentarios' => $permisos['puede_ver_comentarios'],
'puede_agregar_comentarios' => $permisos['puede_agregar_comentarios'],
'puede_agregar_documentos' => $permisos['puede_agregar_documentos'],
'puede_gestionar_pagos' => $permisos['puede_gestionar_pagos'],
'puede_gestionar_telcel' => $permisos['puede_gestionar_telcel'],
'id_usuario' => $permisos['id_usuario'],
'es_user_30' => $permisos['es_user_30'],
'es_gestoria' => $permisos['es_gestoria'],
],
]);
} catch (\Throwable $e) {
echo json_encode([
'success' => false,
'error' => $e->getMessage(),
'file' => $e->getFile(),
'line' => $e->getLine(),
]);
}
exit;
}

public function store()
{
header('Content-Type: application/json; charset=utf-8');

try {
$result = SolicitudChequeService::store($_POST, $_FILES);
} catch (\Throwable $e) {
$result = ['success' => false, 'message' => 'Error al procesar la solicitud: ' . $e->getMessage()];
}

echo json_encode($result);
exit;
}

public function update()
{
header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_POST['id'] ?? 0);
if (!$id) {
echo json_encode(['success' => false, 'message' => 'ID no válido']);
exit;
}

try {
$result = SolicitudChequeService::update($id, $_POST, $_FILES);
} catch (\Throwable $e) {
$result = ['success' => false, 'message' => 'Error al actualizar: ' . $e->getMessage()];
}
echo json_encode($result);
exit;
}

public function destroy()
{
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$id = (int) ($input['id'] ?? 0);
if (!$id) {
echo json_encode(['success' => false, 'message' => 'ID no válido']);
exit;
}

$result = SolicitudChequeService::destroy($id);
echo json_encode($result);
exit;
}

public function getDetalle()
{
header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
echo json_encode(['success' => false, 'message' => 'ID no válido']);
exit;
}

$detalle = SolicitudChequeService::getDetalle($id);
if (!$detalle) {
echo json_encode(['success' => false, 'message' => 'No encontrado']);
exit;
}

$permisos = SolicitudChequeService::getPermisos();
if ($permisos['es_gestoria'] && ((int)$detalle['id_estacion'] !== 8 || (int)$detalle['depto'] !== 5)) {
echo json_encode(['success' => false, 'message' => 'No tienes permiso para ver esta solicitud']);
exit;
}

echo json_encode(['success' => true, 'data' => $detalle]);
exit;
}

public function getDocumentos()
{
header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
echo json_encode(['success' => false, 'documentos' => []]);
exit;
}

$documentos = SolicitudChequeService::getDocumentos($id);
echo json_encode(['success' => true, 'documentos' => $documentos]);
exit;
}

public function storeDocumento()
{
header('Content-Type: application/json; charset=utf-8');

$idSolicitud = (int) ($_POST['id_solicitud'] ?? 0);
$descripcion = $_POST['descripcion'] ?? '';

if (!$idSolicitud || !$descripcion) {
echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
exit;
}

$result = SolicitudChequeService::storeDocumento($idSolicitud, $descripcion, $_FILES['archivo'] ?? []);
echo json_encode($result);
exit;
}

public function deleteDocumento()
{
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$id = (int) ($input['id'] ?? 0);

$result = SolicitudChequeService::deleteDocumento($id);
echo json_encode($result);
exit;
}

public function getComentarios()
{
header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
echo json_encode(['success' => false, 'comentarios' => []]);
exit;
}

$comentarios = SolicitudChequeService::getComentarios($id);
echo json_encode(['success' => true, 'comentarios' => $comentarios]);
exit;
}

public function storeComentario()
{
header('Content-Type: application/json; charset=utf-8');

$idSolicitud = (int) ($_POST['id_solicitud'] ?? 0);
$comentario = $_POST['comentario'] ?? '';

$result = SolicitudChequeService::storeComentario($idSolicitud, $comentario);
echo json_encode($result);
exit;
}

public function getFirmas()
{
header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
echo json_encode(['success' => false, 'firmas' => []]);
exit;
}

$firmas = SolicitudChequeService::getFirmas($id);
echo json_encode(['success' => true, 'firmas' => $firmas]);
exit;
}

public function crearToken()
{
header('Content-Type: application/json; charset=utf-8');

$idSolicitud = (int) ($_POST['id_solicitud'] ?? 0);
$sessionUsuario = Session::get('usuario');
$idUsuario = $sessionUsuario['id'] ?? 0;
$via = $_POST['via'] ?? 'telegram';

if (!$idSolicitud) {
echo json_encode(['success' => false, 'message' => 'ID no válido']);
exit;
}

$result = SolicitudChequeService::crearToken($idSolicitud, $idUsuario, $via);
echo json_encode($result);
exit;
}

public function firmar()
{
header('Content-Type: application/json; charset=utf-8');

$idSolicitud = (int) ($_POST['id_solicitud'] ?? 0);
$tipoFirma = $_POST['tipo_firma'] ?? '';
$token = (int) ($_POST['token'] ?? 0);
$sessionUsuario = Session::get('usuario');
$idUsuario = $sessionUsuario['id'] ?? 0;
$nameEstacion = $sessionUsuario['nomestacion'] ?? '';

if (!$idSolicitud || !$tipoFirma || !$token) {
echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
exit;
}

if (!SolicitudChequeService::puedeFirmarSolicitud($idSolicitud, $idUsuario)) {
echo json_encode(['success' => false, 'message' => 'No tienes permiso para firmar esta solicitud']);
exit;
}

try {
$result = SolicitudChequeService::firmar($idSolicitud, $tipoFirma, $token, $idUsuario, $nameEstacion);
} catch (\Throwable $e) {
$result = ['success' => false, 'message' => 'Error al firmar: ' . $e->getMessage()];
}
echo json_encode($result);
exit;
}

public function getTelcel()
{
header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
echo json_encode(['success' => false, 'telcel' => []]);
exit;
}

$telcel = SolicitudChequeService::getTelcel($id);
echo json_encode(['success' => true, 'telcel' => $telcel]);
exit;
}

public function storeTelcel()
{
header('Content-Type: application/json; charset=utf-8');

$idSolicitud = (int) ($_POST['id_solicitud'] ?? 0);
if (!$idSolicitud) {
echo json_encode(['success' => false, 'message' => 'ID no válido']);
exit;
}

$result = SolicitudChequeService::storeTelcel($idSolicitud, $_POST, $_FILES);
echo json_encode($result);
exit;
}

public function deleteTelcel()
{
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$id = (int) ($input['id'] ?? 0);

$result = SolicitudChequeService::deleteTelcel($id);
echo json_encode($result);
exit;
}

public function getTelcelGlobal()
{
header('Content-Type: application/json; charset=utf-8');
$idYear = (int) ($_GET['idYear'] ?? 0);
$idMes = (int) ($_GET['idMes'] ?? 0);
$idEstacion = (int) ($_GET['idEstacion'] ?? 0);
if (!$idYear || !$idMes || !$idEstacion) {
echo json_encode(['success' => false, 'telcel' => []]);
exit;
}
$telcel = SolicitudChequeService::getTelcelByFilter($idYear, $idMes, $idEstacion);
echo json_encode(['success' => true, 'telcel' => $telcel]);
exit;
}

public function storeTelcelGlobal()
{
header('Content-Type: application/json; charset=utf-8');
$idYear = (int) ($_POST['idYear'] ?? 0);
$idMes = (int) ($_POST['idMes'] ?? 0);
$idEstacion = (int) ($_POST['idEstacion'] ?? 0);
if (!$idYear || !$idMes || !$idEstacion) {
echo json_encode(['success' => false, 'message' => 'Filtros inválidos']);
exit;
}
$result = SolicitudChequeService::storeTelcelGlobal($idYear, $idMes, $idEstacion, $_FILES);
echo json_encode($result);
exit;
}

public function deleteComprobanteTelcel()
{
header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true);
$id = (int) ($input['id'] ?? 0);
$result = SolicitudChequeService::deleteComprobanteTelcel($id);
echo json_encode($result);
exit;
}

public function updatePagoTelcel()
{
header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_POST['id'] ?? 0);
if (!$id) {
echo json_encode(['success' => false, 'message' => 'ID no válido']);
exit;
}

$result = SolicitudChequeService::updatePagoTelcel($id, $_FILES);
echo json_encode($result);
exit;
}

public function getPagos()
{
header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
echo json_encode(['success' => false, 'pagos' => []]);
exit;
}

$pagos = SolicitudChequeService::getPagos($id);
echo json_encode(['success' => true, 'pagos' => $pagos]);
exit;
}

public function storePago()
{
header('Content-Type: application/json; charset=utf-8');

$idSolicitud = (int) ($_POST['id_solicitud'] ?? 0);
if (!$idSolicitud) {
echo json_encode(['success' => false, 'message' => 'ID no válido']);
exit;
}

$result = SolicitudChequeService::storePago($idSolicitud, $_FILES['archivo'] ?? []);
echo json_encode($result);
exit;
}

public function deletePago()
{
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$id = (int) ($input['id'] ?? 0);

$result = SolicitudChequeService::deletePago($id);
echo json_encode($result);
exit;
}

public function getSelectorOpciones()
{
header('Content-Type: application/json; charset=utf-8');

$opciones = SolicitudChequeService::getOpcionesSelector();
echo json_encode(['success' => true, 'opciones' => $opciones]);
exit;
}

public function getFacturaStatusEndpoint($idYear, $idMes, $idEstacion = 0, $idDepto = 0)
{
header('Content-Type: application/json; charset=utf-8');

try {
$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

$idEstacion = (int)$idEstacion;
$idDepto = (int)$idDepto;

if ($idEstacion === 0 && $idDepto === 0) {
$permisos = SolicitudChequeService::getPermisos();
$idEstacion = $permisos['id_estacion'];
$idDepto = $permisos['id_puesto'];
} elseif ($idEstacion === 0 && $idDepto > 0) {
$idEstacion = 8;
}

$status = SolicitudChequeService::getFacturaStatus($idYear, $idMes, $idEstacion, $idDepto);

echo json_encode(['success' => true, 'data' => $status]);
} catch (\Throwable $e) {
echo json_encode([
'success' => false,
'error' => $e->getMessage(),
'file' => $e->getFile(),
'line' => $e->getLine(),
]);
}
exit;
}

public function getPendingCountsEndpoint($idYear, $idMes)
{
header('Content-Type: application/json; charset=utf-8');

$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

$counts = SolicitudChequeService::getPendingCounts($idYear, $idMes);

echo json_encode(['success' => true, 'data' => $counts]);
exit;
}

public function firmarPage($idSolicitud)
{
$detalle = SolicitudChequeService::getDetalle((int)$idSolicitud);
if (!$detalle) {
http_response_code(404);
echo 'Solicitud no encontrada';
exit;
}

$puedeFirmar = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer') || ModuloDptoOperativoService::validaPermiso('personal-general', 'leer');
if (!$puedeFirmar) {
View::render('errors/403', [], 'departamento-operativo');
return;
}

$permisos = SolicitudChequeService::getPermisos();
if ($permisos['es_gestoria']) {
if ((int)$detalle['id_estacion'] !== 8 || (int)$detalle['depto'] !== 5) {
http_response_code(403);
echo 'No tienes permiso para firmar esta solicitud';
exit;
}
}
$sessionUsuario = Session::get('usuario');

$title = 'Firmar Solicitud de Cheque (# ' . $idSolicitud . ')';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
Breadcrumb::add('Solicitud de Cheques (' . nombremes($detalle['id_mes']) . ' ' . $detalle['id_year'] . ')', '/departamento-operativo/solicitud-cheque/' . $detalle['id_year'] . '/' . $detalle['id_mes']);
Breadcrumb::add('<span class="breadcrumb-item active">' . $title . '</span>', '');

$idEstacion = $detalle['id_estacion'] ?? 0;
$idDepto = $detalle['depto'] ?? 0;
$deptoNames = [5 => 'Gestoría', 4 => 'Comercializadora', 18 => 'Quitarga', 19 => 'Operación servicio y mantenimiento de personal', 23 => 'BANCAMIFEL, SOCIEDAD ANÓNIMA, FIDEICOMISO 2176/2016'];
$nombreContexto = $idDepto > 0 ? ($deptoNames[$idDepto] ?? '') : (Estacion::find($idEstacion)?->nombre ?? '');

$firmas = SolicitudChequeService::getFirmas((int)$idSolicitud);
$firmaB = 0;
$firmaC = 0;
foreach ($firmas as $f) {
if ($f['tipo_firma'] === 'B') $firmaB++;
if ($f['tipo_firma'] === 'C') $firmaC++;
}

$now = Carbon::now();
$periodoVencido = ($detalle['id_year'] < $now->year) || ($detalle['id_year'] == $now->year && $detalle['id_mes'] < $now->month);

$contextBadgeHtml = '<span id="contextBadge" class="mb-1 badge rounded-pill text-bg-info w-auto">' . htmlspecialchars($nombreContexto ?? '', ENT_QUOTES, 'UTF-8') . '</span>';

$data = [
'title' => $title,
'detalle' => $detalle,
'firmas' => $firmas,
'firmaB' => $firmaB,
'firmaC' => $firmaC,
'periodoVencido' => $periodoVencido,
'idUsuario' => $permisos['id_usuario'],
'idEstacion' => $permisos['id_estacion'],
'esUser30' => $permisos['es_user_30'],
'esUser19' => $permisos['id_usuario'] == 19,
'esUser2' => $permisos['id_usuario'] == 2,
'esUser22' => $permisos['id_usuario'] == 22,
'esGestoria' => $permisos['es_gestoria'],
'esUserGestoria' => $permisos['es_gestoria'],
'puedeFirmarVOBO' => ($permisos['id_usuario'] == 19 || $permisos['es_user_30'] || $permisos['es_gestoria']),
'puedeFirmarAuth' => ($permisos['id_usuario'] == 2 || $permisos['id_usuario'] == 22),
'multiestacion' => $permisos['multiestacion'],
'nombreContexto' => $nombreContexto,
'contextBadgeHtml' => $contextBadgeHtml,
'ocultarSelectorEstacion' => true,
'help' => false,
'links' => [],
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/js/departamento-operativo/1-corporativo/solicitud-cheque.firmar.js?v=' . time(),
],
];

View::render('departamento-operativo/1-corporativo/solicitud-cheque/firmar', $data, 'departamento-operativo');
}

public function downloadPdf($idSolicitud)
{
$detalle = SolicitudChequeService::getDetalle($idSolicitud);
if (!$detalle) {
http_response_code(404);
echo 'Solicitud no encontrada';
exit;
}

$h = function ($v) { return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8'); };

// Amount to words
$numeroLetras = self::numeroALetras($detalle['monto'], $detalle['moneda']);

$depto = (int)($detalle['depto'] ?? 0);
$idEstacion = (int)($detalle['id_estacion'] ?? 0);
$razonsocial = $detalle['razonsocial'] ?? '';
$estacionNombre = $detalle['estacion_nombre'] ?? '';

$moneda = $detalle['moneda'];

// Build HTML matching old version exactly
$html = '<html lang="es"><head><meta charset="UTF-8"><title>Solicitud de cheque</title>';
$html .= '<style>
@page { margin: 0.5cm 0.5cm; }
*,*::before,*::after { box-sizing: border-box; }
html { font-family: sans-serif; line-height: 1.15; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol"; font-size: .9rem; font-weight: 400; line-height: 1.15; color: #212529; text-align: left; background-color: #fff; }
table { border-collapse: collapse; width: 100%; }
th { text-align: inherit; }
.table { width: 100%; max-width: 100%; margin-bottom: 1rem; background-color: transparent; }
.table th, .table td { padding: 0.75rem; vertical-align: top; border-top: 1px solid #dee2e6; }
.table thead th { vertical-align: bottom; border-bottom: 2px solid #dee2e6; }
.table-sm th, .table-sm td { padding: 0.3rem; }
.table-bordered { border: 1px solid #dee2e6; }
.table-bordered th, .table-bordered td { border: 1px solid #dee2e6; }
.table-bordered thead th, .table-bordered thead td { border-bottom-width: 2px; }
.table-striped tbody tr:nth-of-type(odd) { background-color: rgba(0,0,0,0.05); }
.text-center { text-align: center !important; }
.text-right { text-align: right !important; }
.text-secondary { color: #6c757d !important; }
.text-muted { color: #6c757d !important; }
.border-bottom { border-bottom: 1px solid #dee2e6 !important; }
.mt-1 { margin-top: 0.25rem !important; }
.mt-2 { margin-top: 0.5rem !important; }
.mb-0 { margin-bottom: 0 !important; }
.mb-1 { margin-bottom: 0.25rem !important; }
.pb-0 { padding-bottom: 0 !important; }
.pb-1 { padding-bottom: 0.25rem !important; }
.py-0 { padding-bottom: 0 !important; }
.py-1 { padding-bottom: 0.25rem !important; }
.my-0 { margin-bottom: 0 !important; margin-top: 0 !important; }
.border-top { border-top: 1px solid #dee2e6 !important; }
.border-0 { border: 0 !important; }
.p-1 { padding: 0.25rem !important; }
.p-2 { padding: 0.5rem !important; }
.align-middle { vertical-align: middle !important; }
table td div.text-secondary { font-size: .85rem; }
</style></head><body>';

// Header: logo + company title
if ($depto != 4 && $depto != 18) {
$logoPath = __DIR__ . '/../../public/assets/images/logos/Logo.png';
if (file_exists($logoPath)) {
$logoData = file_get_contents($logoPath);
$logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
$html .= '<img src="' . $logoBase64 . '" style="width: 180px;">';
}
}

if ($depto == 4) {
$html .= '<div class="text-center mt-2" style="font-size: 1.8em;">Comercializadora de Artículos Gasolineros,S. A. de C. V.</div>';
$html .= '<div class="text-center mt-2" style="font-size: 1.2em;">Solicitud de cheque</div>';
} elseif ($depto == 18) {
$html .= '<div class="text-center mt-2" style="font-size: 1.8em;">Quitarga, S. A. de C. V.</div>';
$html .= '<div class="text-center mt-2" style="font-size: 1.2em;">Solicitud de cheque</div>';
} elseif ($depto == 23) {
$html .= '<div class="text-center mt-2" style="font-size: 1.5em;">BANCAMIFEL, SOCIEDAD ANÓNIMA, FIDEICOMISO 2176/2016</div>';
$html .= '<div class="text-center mt-2" style="font-size: 1.4em;">Solicitud de cheque</div>';
} else {
if ($razonsocial != '') {
$html .= '<div class="text-center" style="font-size: 1.8em;">Solicitud de cheque</div>';
$html .= '<div class="text-center mt-2" style="font-size: 1.2em;">' . $h($razonsocial) . '</div>';
} else {
$html .= '<div class="text-center" style="font-size: 1.8em;">Solicitud de cheque</div>';
$html .= '<div class="text-center mt-2" style="font-size: 1.2em;">' . $h($estacionNombre) . '</div>';
}
}

// Main info table (table-sm, borderless, border-bottom on inner divs)
$html .= '<table class="table-sm mb-0 pb-0 mt-2" style="width:100%;">';
$html .= '<tbody>';
$html .= '<tr>';
$html .= '<td colspan="2"><div class="text-secondary"><b>FECHA:</b></div><div class="mt-2 pb-1 border-bottom">' . $h(formatearFechaLarga($detalle['fecha'])) . '</div></td>';
$html .= '<td><div class="text-secondary"><b>NOMBRE DEL BENEFICIARIO:</b></div><div class="mt-2 pb-1 border-bottom">' . $h($detalle['beneficiario']) . '</div></td>';
$html .= '</tr>';

$monedaS = $moneda === 'DLLS' ? 'USD' : ($moneda === 'M.N.' ? 'MXN' : $h($moneda));
$html .= '<tr>';
$html .= '<td><div class="text-secondary"><b>MONTO:</b></div><div class="mt-2 pb-1 border-bottom">' . number_format((float)$detalle['monto'], 2) . '</div></td>';
$html .= '<td><div class="text-secondary"><b>MONEDA:</b></div><div class="mt-2 pb-1 border-bottom">' . $h($moneda) . '</div></td>';
$html .= '<td><div class="text-secondary"><b>IMPORTE CON LETRA:</b></div><div class="mt-2 pb-1 border-bottom">' . $h($numeroLetras) . '</div></td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td colspan="2"><div class="text-secondary"><b>FACTURA NO:</b></div><div class="mt-2 pb-1 border-bottom">' . $h($detalle['no_factura'] ?? '') . '</div></td>';
$html .= '<td><div class="text-secondary"><b>CORREO ELÉCTRONICO:</b></div><div class="mt-2 pb-1 border-bottom">' . $h($detalle['email'] ?? '') . '</div></td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td colspan="3"><div class="text-secondary"><b>CONCEPTO:</b></div><div class="mt-2 pb-1 border-bottom">' . nl2br($h($detalle['concepto'] ?? '')) . '</div></td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td><div class="text-secondary"><b>NOMBRE DEL SOLICITANTE:</b></div><div class="mt-2 pb-1 border-bottom">' . $h($detalle['solicitante'] ?? '') . '</div></td>';
$html .= '<td><div class="text-secondary"><b>TELÉFONO:</b></div><div class="mt-2 pb-1 border-bottom">' . $h($detalle['telefono'] ?? '') . '</div></td>';
$html .= '<td><div class="text-secondary"><b>USO DEL CFDI:</b></div><div class="mt-2 pb-1 border-bottom">' . $h($detalle['cfdi'] ?? '') . '</div></td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td><div class="text-secondary"><b>MÉTODO DE PAGO:</b></div><div class="mt-2 pb-1 border-bottom">' . $h($detalle['metodo_pago'] ?? '') . '</div></td>';
$html .= '<td><div class="text-secondary"><b>FORMA DE PAGO:</b></div><div class="mt-2 pb-1 border-bottom">' . $h($detalle['forma_pago'] ?? '') . '</div></td>';
$html .= '<td><div class="text-secondary"><b>BANCO:</b></div><div class="mt-2 pb-1 border-bottom">' . $h($detalle['banco'] ?? '') . '</div></td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td><div class="text-secondary"><b>NO. DE CUENTA:</b></div><div class="mt-2 pb-1 border-bottom">' . $h($detalle['no_cuenta'] ?? '') . '</div></td>';
$html .= '<td><div class="text-secondary"><b>NO. DE CUENTA CLABE:</b></div><div class="mt-2 pb-1 border-bottom">' . $h($detalle['cuenta_clabe'] ?? '') . '</div></td>';
$html .= '<td><div class="text-secondary"><b>REFERENCIA/CONVENIO:</b></div><div class="mt-2 pb-1 border-bottom">' . $h($detalle['referencia'] ?? '') . '</div></td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td colspan="3"><div class="text-secondary"><b>OBSERVACIONES:</b></div><div class="mt-2 pb-1 border-bottom">' . nl2br($h($detalle['observaciones'] ?? '')) . '</div></td>';
$html .= '</tr>';

$html .= '</tbody></table>';

// Signatures
$firmas = $detalle['firmas'] ?? [];
$html .= '<table class="table table-sm table-bordered" style="margin-top: 50px;">';
$html .= '<tbody><tr>';
if (count($firmas) > 0) {
foreach ($firmas as $f) {
$html .= '<td class="text-center align-middle" style="vertical-align:top;width:' . (int)(100 / count($firmas)) . '%">';
$html .= '<div class="text-secondary text-center">';
$html .= '<div>' . $h($f['usuario_nombre'] ?? '') . '</div>';

if ($f['tipo_firma'] === 'A' && !empty($f['firma'])) {
$rutaArchivo = SolicitudChequeService::getFirmaDir() . '/' . $f['firma'];
if (file_exists($rutaArchivo)) {
$imgData = file_get_contents($rutaArchivo);
$ext = pathinfo($rutaArchivo, PATHINFO_EXTENSION);
$base64 = 'data:image/' . $ext . ';base64,' . base64_encode($imgData);
$html .= '<div><img src="' . $base64 . '" style="width:200px;"></div>';
} else {
$html .= '<div style="padding:10px;"><small>¡Falta la Firma!</small></div>';
}
$html .= '<div style="margin-top:10px;"><b>NOMBRE Y FIRMA DEL ENCARGADO</b></div>';
} elseif ($f['tipo_firma'] === 'B') {
$html .= '<div class="border-bottom text-center" style="padding:10px;"><small>La solicitud de cheque se firmó por un medio electrónico.<br> <b>Fecha: ' . $h(formatearFechaLarga(substr($f['fecha'] ?? '', 0, 10))) . '</b></small></div>';
$html .= '<div style="margin-top:10px;"><b>NOMBRE Y FIRMA DE VOBO</b></div>';
} elseif ($f['tipo_firma'] === 'C') {
$html .= '<div class="border-bottom text-center" style="padding:10px;"><small>La solicitud de cheque se firmó por un medio electrónico.<br> <b>Fecha: ' . $h(formatearFechaLarga(substr($f['fecha'] ?? '', 0, 10))) . '</b></small></div>';
$html .= '<div style="margin-top:10px;"><b>NOMBRE Y FIRMA DE AUTORIZACIÓN</b></div>';
}

$html .= '</div></td>';
}
} else {
$html .= '<td class="text-center text-muted">Sin firmas registradas</td>';
}
$html .= '</tr></tbody></table>';

$html .= '</body></html>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("Solicitud de cheque.pdf", ["Attachment" => true]);
exit;
}

private static function numeroALetras($number, $moneda): string
{
$moneda = strtoupper(trim($moneda));
if ($moneda === 'DLLS' || $moneda === 'USD') {
$tipoMoneda = 'DOLARES';
$divisa = 'USD';
} else {
$tipoMoneda = 'PESOS';
$divisa = 'M.N';
}

if ($number < 0 || $number > 999999999) {
return 'No es posible convertir el numero a letras';
}

$numStr = number_format((float)$number, 2, '.', '');
$parts = explode('.', $numStr);
$entero = (int)$parts[0];
$decimal = $parts[1] ?? '00';

$letras = self::convertirGrupo($entero);
$resultado = $letras . ' ' . $tipoMoneda . ' ' . $decimal . '/100 ' . $divisa;
return trim($resultado);
}

private static function convertirGrupo($n): string
{
$unidades = ['', 'UN ', 'DOS ', 'TRES ', 'CUATRO ', 'CINCO ', 'SEIS ', 'SIETE ', 'OCHO ', 'NUEVE ', 'DIEZ ',
'ONCE ', 'DOCE ', 'TRECE ', 'CATORCE ', 'QUINCE ', 'DIECISEIS ', 'DIECISIETE ', 'DIECIOCHO ', 'DIECINUEVE ', 'VEINTE '];
$decenas = ['VENTI', 'TREINTA ', 'CUARENTA ', 'CINCUENTA ', 'SESENTA ', 'SETENTA ', 'OCHENTA ', 'NOVENTA '];
$centenas = ['CIENTO ', 'DOSCIENTOS ', 'TRESCIENTOS ', 'CUATROCIENTOS ', 'QUINIENTOS ', 'SEISCIENTOS ', 'SETECIENTOS ', 'OCHOCIENTOS ', 'NOVECIENTOS '];

if ($n == 0) return 'CERO ';
if ($n == 100) return 'CIEN ';

$str = (string)$n;
$len = strlen($str);

// Build number parts for groups of 3
$grupos = [];
$pos = $len;
while ($pos > 0) {
$start = max(0, $pos - 3);
$grupos[] = (int)substr($str, $start, $pos - $start);
$pos = $start;
}
// grupos[0] = unidades/centenas, grupos[1] = miles, grupos[2] = millones

$output = '';

// Millones
if (isset($grupos[2]) && $grupos[2] > 0) {
if ($grupos[2] == 1) {
$output .= 'UN MILLON ';
} else {
$output .= self::convertirCientos($grupos[2], $unidades, $decenas, $centenas) . 'MILLONES ';
}
}

// Miles
if (isset($grupos[1]) && $grupos[1] > 0) {
if ($grupos[1] == 1) {
$output .= 'MIL ';
} else {
$output .= self::convertirCientos($grupos[1], $unidades, $decenas, $centenas) . 'MIL ';
}
}

// Centenas/Decenas/Unidades
if (isset($grupos[0]) && $grupos[0] > 0) {
if ($grupos[0] == 1 && !isset($grupos[1]) && !isset($grupos[2])) {
$output .= 'UN ';
} else {
$output .= self::convertirCientos($grupos[0], $unidades, $decenas, $centenas);
}
}

return $output;
}

private static function convertirCientos($n, $unidades, $decenas, $centenas): string
{
$output = '';
if ($n >= 100) {
$c = (int)($n / 100);
if ($n == 100) return 'CIEN ';
$output .= $centenas[$c - 1];
$n %= 100;
}
if ($n > 0) {
if ($n <= 20) {
$output .= $unidades[$n];
} else {
$d = (int)($n / 10);
$u = $n % 10;
if ($d >= 2 && $d <= 9) {
if ($u > 0 && $n > 30) {
$output .= $decenas[$d - 2] . 'Y ' . $unidades[$u];
} else {
$output .= $decenas[$d - 2] . $unidades[$u];
}
}
}
}
return $output;
}

public function downloadExcel($idYear, $idMes)
{
$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

$estacion = (int)($_GET['estacion'] ?? 0);
$depto = (int)($_GET['depto'] ?? 0);
$tipo = $_GET['tipo'] ?? '';

$data = SolicitudChequeService::getData($idYear, $idMes, $estacion ?: null, $depto ?: null);

$nombremes = nombremes($idMes);

$spreadsheet = new Spreadsheet();

if ($tipo === 'comprobante') {
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Comprobantes de Pago");

$headers = ['Solicitud #', 'Beneficiario', 'Monto', 'Fecha Pago', 'Archivo'];
$col = 'A';
foreach ($headers as $h) {
$sheet->setCellValue($col . '1', $h);
$col++;
}

$styleHeader = [
'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '215d98']],
'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true],
'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
];
$sheet->getStyle("A1:E1")->applyFromArray($styleHeader);

$rowIndex = 2;
foreach ($data as $solicitud) {
$pagos = SolicitudChequeDocumento::where('id_solicitud', $solicitud['id'])
->where('nombre', 'PAGO')
->orderBy('id', 'asc')
->get();

foreach ($pagos as $pago) {
$sheet->setCellValue("A$rowIndex", $solicitud['id']);
$sheet->setCellValue("B$rowIndex", $solicitud['beneficiario']);
$sheet->setCellValue("C$rowIndex", '$ ' . number_format($solicitud['monto'], 2) . ' ' . $solicitud['moneda']);
$sheet->setCellValue("D$rowIndex", $pago->fecha ? formatearFecha($pago->fecha) : '');
$sheet->setCellValue("E$rowIndex", $pago->documento ?? '');

$sheet->getStyle("A$rowIndex:E$rowIndex")->getAlignment()
->setHorizontal(Alignment::HORIZONTAL_CENTER)
->setVertical(Alignment::VERTICAL_CENTER);
$rowIndex++;
}
}

if ($rowIndex === 2) {
$sheet->setCellValue("A$rowIndex", 'No se encontraron comprobantes de pago');
$sheet->mergeCells("A$rowIndex:E$rowIndex");
}

$lastRow = $rowIndex - 1;
$rangeCompleto = "A1:E$lastRow";
$styleBorders = [
'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
];
$sheet->getStyle($rangeCompleto)->applyFromArray($styleBorders);
foreach (range('A', 'E') as $c) {
$sheet->getColumnDimension($c)->setAutoSize(true);
}

$filename = 'Comprobantes_Pago_' . $nombremes . '_' . $idYear . '.xlsx';
} else {
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Solicitud de Cheque");

$headers = [
'A1' => 'No.', 'B1' => 'Fecha', 'C1' => 'Beneficiario', 'D1' => 'Monto',
'E1' => 'No. Factura', 'F1' => 'Concepto', 'G1' => 'Uso del CDFI',
'H1' => 'Metodo de Pago', 'I1' => 'Banco', 'J1' => 'No. de Cuenta',
'K1' => 'No. Cuenta CLABE', 'L1' => 'Referencia Convenio', 'M1' => 'Forma de Pago',
];

foreach ($headers as $cell => $text) {
$sheet->setCellValue($cell, $text);
}

$styleHeader = [
'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '215d98']],
'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true],
'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
];
$sheet->getStyle("A1:M1")->applyFromArray($styleHeader);

$rowIndex = 2;
$num = 1;

foreach ($data as $row) {
$fecha_hora = $row['fecha'] . ($row['hora'] ? ', ' . $row['hora'] : '');

$pagoDoc = SolicitudChequeDocumento::where('id_solicitud', $row['id'])
->where('nombre', 'PAGO')->count();
$st = $row['status'];
$trColor = 'FFFFFF';
if ($st == 2) {
$trColor = ($pagoDoc > 0) ? 'b0f2c2' : 'ffffff';
} elseif ($st == 0 || $st == 1) {
$trColor = 'fcfcda';
} else {
$trColor = 'ffb6af';
}

$sheet->setCellValue("A$rowIndex", $num);
$sheet->setCellValue("B$rowIndex", $fecha_hora);
$sheet->setCellValue("C$rowIndex", $row['beneficiario']);
$sheet->setCellValue("D$rowIndex", '$ ' . number_format($row['monto'], 2) . ' ' . $row['moneda']);
$sheet->setCellValue("E$rowIndex", $row['no_factura']);
$sheet->setCellValue("F$rowIndex", $row['concepto']);
$sheet->setCellValue("G$rowIndex", $row['cfdi']);
$sheet->setCellValue("H$rowIndex", $row['metodo_pago']);
$sheet->setCellValue("I$rowIndex", $row['banco']);
$sheet->setCellValue("J$rowIndex", $row['no_cuenta']);
$sheet->setCellValue("K$rowIndex", $row['cuenta_clabe']);
$sheet->setCellValue("L$rowIndex", $row['referencia']);
$sheet->setCellValue("M$rowIndex", $row['forma_pago']);

$sheet->getStyle("A$rowIndex:M$rowIndex")->getFill()
->setFillType(Fill::FILL_SOLID)
->getStartColor()->setRGB($trColor);

$rowIndex++;
$num++;
}

if ($num === 1) {
$sheet->setCellValue("A$rowIndex", 'No se encontró información');
$sheet->mergeCells("A$rowIndex:M$rowIndex");
$rowIndex++;
}

$lastRow = $rowIndex - 1;
$rangeCompleto = "A1:M$lastRow";

$styleBorders = [
'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
];
$sheet->getStyle($rangeCompleto)->applyFromArray($styleBorders);

$sheet->getColumnDimension('F')->setAutoSize(false);
$sheet->getColumnDimension('F')->setWidth(45);
$sheet->getStyle("F2:F$lastRow")->getAlignment()->setWrapText(true);

foreach (range('A', 'M') as $c) {
if ($c !== 'F') {
$sheet->getColumnDimension($c)->setAutoSize(true);
}
$sheet->getStyle($c . "1:" . $c . $lastRow)->getAlignment()
->setHorizontal(Alignment::HORIZONTAL_CENTER)
->setVertical(Alignment::VERTICAL_CENTER);
}

$estacionNombre = '';
if (!empty($data)) {
$estacionNombre = $data[0]['estacion_nombre'] ?? '';
}
$filename = 'Solicitud_Cheque_' . ($estacionNombre ? $estacionNombre . '_' : '') . $nombremes . '_' . $idYear . '.xlsx';
}

$writer = new Xlsx($spreadsheet);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer->save('php://output');
exit;
}

public function facturaTelcel($idYear, $idMes, $idEstacion, $idDepto = 0)
{
$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];
$idEstacion = (int)$idEstacion;
$idDepto = (int)$idDepto;

$nombreContexto = '';
if ($idDepto > 0 && $idEstacion == 8) {
$deptos = [5 => 'Gestoría', 7 => 'Comercializadora', 8 => 'Contabilidad'];
$nombreContexto = $deptos[$idDepto] ?? 'Depto #' . $idDepto;
} elseif ($idEstacion > 0) {
$estacionModel = Estacion::find($idEstacion);
$nombreContexto = $estacionModel ? $estacionModel->nombre : 'Estación #' . $idEstacion;
}

$title = 'Facturas Telcel (' . nombremes($idMes) . ' ' . $idYear . ')';

$status = SolicitudChequeService::getFacturaTelcelStatus($idEstacion, $idYear, $idMes);

$badgeHtml = '';
if ($status['total'] == 0) {
$badgeHtml = ' <span class="badge rounded-pill bg-danger">Sin factura</span>';
} elseif ($status['tiene_factura'] && $status['tiene_pago']) {
$badgeHtml = ' <span class="badge rounded-pill bg-success">Pagado</span>';
} elseif ($status['tiene_factura']) {
$badgeHtml = ' <span class="badge rounded-pill bg-warning text-white">Factura disponible</span>';
}

$yearMesTemplate = '/departamento-operativo/solicitud-cheque-telcel/{year}/{mes}/' . $idEstacion . ($idDepto > 0 ? '/' . $idDepto : '');

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
Breadcrumb::add('Solicitud de Cheques (' . nombremes($idMes) . ' ' . $idYear . ')', '/departamento-operativo/solicitud-cheque/' . $idYear . '/' . $idMes);
Breadcrumb::add('Facturas Telcel (' . nombremes($idMes) . ' ' . $idYear . ')', '');
Breadcrumb::add(DropdownYearMesService::dropdownMes($idYear, $idMes), '');
Breadcrumb::add(DropdownYearMesService::dropdownYearManual($idYear, $idMes), '');
Breadcrumb::add($badgeHtml, '');

$comentario = SolicitudChequeService::getFacturaTelcelComentario($idEstacion, $idYear, $idMes);

$data = [
'title' => $title,
'idYear' => $idYear,
'idMes' => $idMes,
'idEstacion' => $idEstacion,
'idDepto' => $idDepto,
'status' => $status,
'comentario' => $comentario,
'yearMesTemplate' => $yearMesTemplate,
//'multiestacion' => $esMultiestacion,
'ocultarSelectorEstacion' => true,
'help' => false,
'scripts' => [
'/assets/js/departamento-operativo/1-corporativo/solicitud-cheque.factura-telcel.js?v=' . time(),
],
];

View::render('departamento-operativo/1-corporativo/solicitud-cheque/factura-telcel', $data, 'departamento-operativo');
}

public function getDirectorioData($idYear, $idMes, $idEstacion)
{
header('Content-Type: application/json; charset=utf-8');
$directorio = SolicitudChequeService::getDirectorio($idEstacion, $idYear, $idMes);
echo json_encode(['success' => true, 'data' => $directorio]);
exit;
}

public function storeDirectorio()
{
header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true);
$idEstacion = (int)($input['idEstacion'] ?? 0);
$idYear = (int)($input['idYear'] ?? 0);
$idMes = (int)($input['idMes'] ?? 0);
if (!$idEstacion || !$idYear || !$idMes) {
echo json_encode(['success' => false, 'message' => 'Filtros inválidos']);
exit;
}
$result = SolicitudChequeService::storeDirectorio($idEstacion, $idYear, $idMes, $input);
echo json_encode($result);
exit;
}

public function updateDirectorio()
{
header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);
if (!$id) {
echo json_encode(['success' => false, 'message' => 'ID no válido']);
exit;
}
$result = SolicitudChequeService::updateDirectorio($id, $input);
echo json_encode($result);
exit;
}

public function deleteDirectorio()
{
header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);
$result = SolicitudChequeService::deleteDirectorio($id);
echo json_encode($result);
exit;
}

public function getFacturasTelcelList($idYear, $idMes, $idEstacion)
{
header('Content-Type: application/json; charset=utf-8');
$facturas = SolicitudChequeService::getFacturasTelcelList($idEstacion, $idYear, $idMes);
echo json_encode(['success' => true, 'data' => $facturas]);
exit;
}

public function storeFacturaTelcel()
{
header('Content-Type: application/json; charset=utf-8');
$idEstacion = (int)($_POST['idEstacion'] ?? 0);
$idYear = (int)($_POST['idYear'] ?? 0);
$idMes = (int)($_POST['idMes'] ?? 0);
if (!$idEstacion || !$idYear || !$idMes) {
echo json_encode(['success' => false, 'message' => 'Filtros inválidos']);
exit;
}
$result = SolicitudChequeService::storeFacturaTelcel($idEstacion, $idYear, $idMes, $_POST, $_FILES);
echo json_encode($result);
exit;
}

public function deleteFacturaTelcel()
{
header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);
$result = SolicitudChequeService::deleteFacturaTelcel($id);
echo json_encode($result);
exit;
}

public function storeFacturaTelcelComentario()
{
header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true);
$idEstacion = (int)($input['idEstacion'] ?? 0);
$idYear = (int)($input['idYear'] ?? 0);
$idMes = (int)($input['idMes'] ?? 0);
$comentario = $input['comentario'] ?? '';
if (!$idEstacion || !$idYear || !$idMes) {
echo json_encode(['success' => false, 'message' => 'Filtros inválidos']);
exit;
}
$result = SolicitudChequeService::storeFacturaTelcelComentario($idEstacion, $idYear, $idMes, $comentario);
echo json_encode($result);
exit;
}
}
