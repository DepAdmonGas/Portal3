<?php
namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Session;
use App\Services\SolicitudValeService;
use App\Services\DropdownYearMesService;
use App\Models\Estacion;
use App\Models\Usuario;
use App\Core\Auth;
use Dompdf\Dompdf;
use Dompdf\Options;

class SolicitudValeController extends BaseController
{
protected string $modulo = 'corporativo';

public function redirect()
{
$validados = DropdownYearMesService::validarYearMes(0, 0);
header('Location: /departamento-operativo/corporativo/solicitud-vales/' . $validados['idYear'] . '/' . $validados['idMes']);
exit;
}

public function index(int $idYear = 0, int $idMes = 0)
{
$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

$permisos = SolicitudValeService::getPermisos();
$idEstacion = $permisos['id_estacion'];
$idUsuario = $permisos['id_usuario'];
$idPuesto = $permisos['id_puesto'];

$title = 'Solicitud de Vales (' . nombremes($idMes) . ' ' . $idYear . ')';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
Breadcrumb::add($title, '');
Breadcrumb::add(DropdownYearMesService::dropdownMes($idYear, $idMes), '');
Breadcrumb::add(DropdownYearMesService::dropdownYearManual($idYear, $idMes), '');

$yearMesTemplate = '/departamento-operativo/corporativo/solicitud-vales/{year}/{mes}';

$estacionNombre = '';
$mostrarCuenta = false;

if ($idEstacion == 8 || $idPuesto == 5) {
$usuario = Usuario::find($idUsuario);
$nombrePuesto = $usuario && $usuario->puesto ? $usuario->puesto->nombre : 'Desconocido';
$estacionNombre = 'Puesto: ' . $nombrePuesto;
$mostrarCuenta = ($idEstacion == 8);
} else {
$estacion = Estacion::find($idEstacion);
$estacionNombre = $estacion ? $estacion->nombre : '';
}

View::render('departamento-operativo/1-corporativo/solicitud-vales/index', [
'title' => $title,
'idYear' => $idYear,
'idMes' => $idMes,
'idEstacion' => $idEstacion,
'idUsuario' => $idUsuario,
'idPuesto' => $idPuesto,
'yearMesTemplate' => $yearMesTemplate,
'estacionNombre' => $estacionNombre,
'mostrarCuenta' => $mostrarCuenta,
'puedeCrear' => $permisos['puedeCrear'],
'puedeEditar' => $permisos['puedeEditar'],
'puedeEliminar' => $permisos['puedeEliminar'],
'help' => false,
'scripts' => [
' /assets/js/vendor.min.js?v=' . time(),
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/departamento-operativo/1-corporativo/solicitud-vales.datatable.init.js?v=' . time(),
'/assets/js/departamento-operativo/1-corporativo/solicitud-vales.actions.init.js?v=' . time(),
],
'links' => [
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
],
], 'departamento-operativo');
}

public function crear(int $idYear, int $idMes, int $idEstacion = 0, int $idDepto = 0)
{
$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

$permisos = SolicitudValeService::getPermisos();

$title = 'Crear Solicitud de Vale';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
Breadcrumb::add('Solicitud de Vales (' . nombremes($idMes) . ' ' . $idYear . ')', '/departamento-operativo/corporativo/solicitud-vales/' . $idYear . '/' . $idMes);
Breadcrumb::add('<span class="breadcrumb-item active">' . $title . '</span>', '');

if ($idEstacion === 0) {
$sessionUsuario = Session::get('usuario');
$idEstacion = (int)($sessionUsuario['id_estacion'] ?? 0);
}

$estaciones = Estacion::where('numlista', '<=', 8)->orderBy('numlista')->get()
->map(fn($e) => ['id' => $e->id, 'nombre' => $e->nombre])
->values();

$data = [
'title' => $title,
'idYear' => $idYear,
'idMes' => $idMes,
'idEstacion' => $idEstacion,
'idDepto' => $idDepto,
'estacionesJson' => json_encode($estaciones, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP),
'mostrarCuenta' => ($permisos['id_estacion'] == 8),
'permisos' => $permisos,
'help' => false,
'scripts' => [
'/assets/js/departamento-operativo/1-corporativo/solicitud-vales.crear.js?v=' . time(),
],
];

View::render('departamento-operativo/1-corporativo/solicitud-vales/crear', $data, 'departamento-operativo');
}

public function editar(int $idYear, int $idMes, int $idEstacion, int $id)
{
$permisos = SolicitudValeService::getPermisos();
if (!$permisos['puedeEditar']) {
header('Location: /departamento-operativo/corporativo/solicitud-vales/' . $idYear . '/' . $idMes);
exit;
}

$detalle = SolicitudValeService::getDetalle($id);
if (!$detalle) {
http_response_code(404);
echo 'Solicitud no encontrada';
exit;
}

$title = 'Editar Solicitud de Vale';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
Breadcrumb::add('Solicitud de Vales (' . nombremes($detalle['id_mes']) . ' ' . $detalle['id_year'] . ')', '/departamento-operativo/corporativo/solicitud-vales/' . $detalle['id_year'] . '/' . $detalle['id_mes']);
Breadcrumb::add('<span class="breadcrumb-item active">' . $title . '</span>', '');

$estaciones = Estacion::where('numlista', '<=', 8)->orderBy('numlista')->get()
->map(fn($e) => ['id' => $e->id, 'nombre' => $e->nombre])
->values();

$data = [
'title' => $title,
'idYear' => $detalle['id_year'],
'idMes' => $detalle['id_mes'],
'idEstacion' => $detalle['id_estacion'] ?? 0,
'estacionesJson' => json_encode($estaciones, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP),
'detalleJson' => json_encode($detalle, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP),
'mostrarCuenta' => ($permisos['id_estacion'] == 8),
'permisos' => $permisos,
'help' => false,
'scripts' => [
'/assets/js/departamento-operativo/1-corporativo/solicitud-vales.editar.js?v=' . time(),
],
];

View::render('departamento-operativo/1-corporativo/solicitud-vales/editar', $data, 'departamento-operativo');
}

public function pdf(int $id)
{
$detalle = SolicitudValeService::getDetalle($id);
if (!$detalle) {
http_response_code(404);
echo 'Solicitud no encontrada';
exit;
}

$h = function ($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); };

$depto = (int)($detalle['depto'] ?? 0);
$deptoNames = [2 => 'Sistemas', 4 => 'Comercializadora', 5 => 'Gestoria', 8 => 'Mantenimiento', 13 => 'Dirección de operaciones', 15 => 'Departamento Jurídico'];
$deptoNombre = $deptoNames[$depto] ?? '';

$html = '<html lang="es"><head><meta charset="UTF-8"><title>Solicitud de Vale</title>';
$html .= '<style>
@page { margin: 0.5cm 0.5cm; }
*,*::before,*::after { box-sizing: border-box; }
html { font-family: sans-serif; line-height: 1.15; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol"; font-size: .9rem; font-weight: 400; line-height: 1.15; color: #212529; text-align: left; background-color: #fff; }
table { border-collapse: collapse; width: 100%; }
.table { width: 100%; max-width: 100%; margin-bottom: 1rem; background-color: transparent; }
.table th, .table td { padding: 0.75rem; vertical-align: top; border-top: 1px solid #dee2e6; }
.table thead th { vertical-align: bottom; border-bottom: 2px solid #dee2e6; }
.table-sm th, .table-sm td { padding: 0.3rem; }
.table-bordered { border: 1px solid #dee2e6; }
.table-bordered th, .table-bordered td { border: 1px solid #dee2e6; }
.table-bordered thead th, .table-bordered thead td { border-bottom-width: 2px; }
.text-center { text-align: center !important; }
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
.border-0 { border: 0 !important; }
.p-2 { padding: 0.5rem !important; }
.align-middle { vertical-align: middle !important; }
table td div.text-secondary { font-size: .85rem; }
</style></head><body>';

$logoPath = __DIR__ . '/../../public/assets/images/logos/Logo.png';
if (file_exists($logoPath)) {
$logoData = file_get_contents($logoPath);
$logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
$html .= '<div class="text-center"><img src="' . $logoBase64 . '" style="width: 180px;"></div>';
}

$html .= '<div class="text-center mt-2" style="font-size: 1.6em; font-weight: bold;">SOLICITUD DE VALE</div>';

$html .= '<table class="table-sm mb-0 pb-0 mt-2" style="width:100%;">';
$html .= '<tbody>';

$html .= '<tr>';
$html .= '<td><div class="text-secondary"><b>FOLIO:</b></div><div class="mt-2 pb-1 border-bottom">00' . $h($detalle['folio']) . '</div></td>';
$html .= '<td><div class="text-secondary"><b>DEPARTAMENTO:</b></div><div class="mt-2 pb-1 border-bottom">' . $h($deptoNombre) . '</div></td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td><div class="text-secondary"><b>FECHA:</b></div><div class="mt-2 pb-1 border-bottom">' . $h($detalle['fecha']) . ' ' . $h($detalle['hora']) . '</div></td>';
$html .= '<td><div class="text-secondary"><b>SOLICITANTE:</b></div><div class="mt-2 pb-1 border-bottom">' . $h($detalle['solicitante']) . '</div></td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td colspan="2"><div class="text-secondary"><b>MONTO:</b></div><div class="mt-2 pb-1 border-bottom">$' . number_format((float)$detalle['monto'], 2) . ' ' . $h($detalle['moneda']) . '</div></td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td colspan="2"><div class="text-secondary"><b>CONCEPTO:</b></div><div class="mt-2 pb-1 border-bottom">' . nl2br($h($detalle['concepto'])) . '</div></td>';
$html .= '</tr>';

if ($detalle['id_estacion'] > 0 || $detalle['cuenta']) {
$cargoTexto = $detalle['id_estacion'] > 0 ? $h($detalle['estacion_nombre']) : $h($detalle['cuenta']);
$html .= '<tr>';
$html .= '<td colspan="2"><div class="text-secondary"><b>CARGO A CUENTA:</b></div><div class="mt-2 pb-1 border-bottom">' . $cargoTexto . '</div></td>';
$html .= '</tr>';
}

$html .= '<tr>';
$html .= '<td><div class="text-secondary"><b>AUTORIZADO POR:</b></div><div class="mt-2 pb-1 border-bottom">' . $h($detalle['autorizado_por']) . '</div></td>';
$html .= '<td><div class="text-secondary"><b>MÉTODO DE AUTORIZACIÓN:</b></div><div class="mt-2 pb-1 border-bottom">' . $h($detalle['metodo_autorizacion']) . '</div></td>';
$html .= '</tr>';

if ($detalle['observaciones']) {
$html .= '<tr>';
$html .= '<td colspan="2"><div class="text-secondary"><b>OBSERVACIONES:</b></div><div class="mt-2 pb-1 border-bottom">' . nl2br($h($detalle['observaciones'])) . '</div></td>';
$html .= '</tr>';
}

$html .= '</tbody></table>';

$html .= '</body></html>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("Solicitud de Vale.pdf", ["Attachment" => true]);
exit;
}

public function getData()
{
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
try {
$idYear = (int)($_REQUEST['year'] ?? 0);
$idMes = (int)($_REQUEST['mes'] ?? 0);

if (!$idYear || !$idMes) {
echo json_encode(['success' => false, 'data' => []]);
exit;
}

$data = SolicitudValeService::getData($idYear, $idMes);
$permisos = SolicitudValeService::getPermisos();

echo json_encode([
'success' => true,
'data' => $data,
'permisos' => [
'id_usuario' => $permisos['id_usuario'],
'puede_editar' => $permisos['puedeEditar'],
'puede_eliminar' => $permisos['puedeEliminar'],
],
]);
} catch (\Throwable $e) {
echo json_encode(['success' => false, 'error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
}
exit;
}

public function add()
{
header('Content-Type: application/json; charset=utf-8');
$permisos = SolicitudValeService::getPermisos();
/*
if (!$permisos['puedeCrear']) {
echo json_encode(['success' => false, 'message' => 'No tienes permisos para agregar.']);
exit;
}
*/

$id = SolicitudValeService::add($_POST + $_FILES);
if ($id) {
$record = SolicitudValeService::getRecord($id);
SolicitudValeService::notificarTelegram('agregar', [
'id_estacion' => $record['id_estacion'] ?? $permisos['id_estacion'],
'id_usuario' => $permisos['id_usuario'],
'year' => $record['year'] ?? $_POST['id_year'],
'mes' => $record['mes'] ?? $_POST['id_mes'],
'folio' => $record['folio'] ?? '',
'cuenta' => $record['cuenta'] ?? '',
]);
echo json_encode(['success' => true, 'message' => 'Solicitud de vale creada exitosamente.', 'id' => $id]);
} else {
echo json_encode(['success' => false, 'message' => 'Error al crear la solicitud de vale.']);
}
exit;
}

public function edit()
{
header('Content-Type: application/json; charset=utf-8');

$permisos = SolicitudValeService::getPermisos();
if (!$permisos['puedeEditar']) {
echo json_encode(['success' => false, 'message' => 'No tienes permisos para editar.']);
exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);

if (!$id) {
echo json_encode(['success' => false, 'message' => 'ID no proporcionado.']);
exit;
}
$input['_id_estacion_session'] = $permisos['id_estacion'];
$input['_id_usuario'] = $permisos['id_usuario'];

$updated = SolicitudValeService::update($id, $input);
if ($updated) {
$record = SolicitudValeService::getRecord($id);
$permisos = SolicitudValeService::getPermisos();
SolicitudValeService::notificarTelegram('editar', [
'id_estacion' => $record['id_estacion'] ?? $permisos['id_estacion'],
'id_usuario' => $permisos['id_usuario'],
'year' => $record['year'] ?? '',
'mes' => $record['mes'] ?? 0,
'folio' => $record['folio'] ?? '',
'cuenta' => $record['cuenta'] ?? '',
]);
echo json_encode(['success' => true, 'message' => 'Solicitud de vale actualizada exitosamente.']);
} else {
echo json_encode(['success' => false, 'message' => 'Error al actualizar la solicitud de vale.']);
}
exit;
}

public function delete()
{
header('Content-Type: application/json; charset=utf-8');

$permisos = SolicitudValeService::getPermisos();
if (!$permisos['puedeEliminar']) {
echo json_encode(['success' => false, 'message' => 'No tienes permisos para eliminar.']);
exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);

if (!$id) {
echo json_encode(['success' => false, 'message' => 'ID no proporcionado.']);
exit;
}

$deleted = SolicitudValeService::delete($id);
if ($deleted) {
$permisos = SolicitudValeService::getPermisos();
SolicitudValeService::notificarTelegram('eliminar', [
'id_estacion' => $deleted['id_estacion'] ?? $permisos['id_estacion'],
'id_usuario' => $permisos['id_usuario'],
'year' => $deleted['id_year'] ?? '',
'mes' => $deleted['id_mes'] ?? 0,
'folio' => $deleted['folio'] ?? '',
'cuenta' => $deleted['cuenta'] ?? '',
]);
echo json_encode(['success' => true, 'message' => 'Solicitud de vale eliminada exitosamente.']);
} else {
echo json_encode(['success' => false, 'message' => 'Error al eliminar la solicitud de vale.']);
}
exit;
}

public function getComentarios()
{
header('Content-Type: application/json; charset=utf-8');
try {
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
echo json_encode(['success' => false, 'data' => []]);
exit;
}
$comentarios = SolicitudValeService::getComentarios($id);
$detalle = SolicitudValeService::getDetalle($id);
echo json_encode(['success' => true, 'data' => $comentarios, 'detalle' => $detalle]);
} catch (\Throwable $e) {
echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;
}

public function addComentario()
{
header('Content-Type: application/json; charset=utf-8');
$permisos = SolicitudValeService::getPermisos();

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);
$comentario = trim($input['comentario'] ?? '');

if (!$id || !$comentario) {
echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
exit;
}

$added = SolicitudValeService::addComentario($id, $comentario, $permisos['id_usuario']);
if ($added) {
$record = SolicitudValeService::getRecord($id);
SolicitudValeService::notificarTelegram('agregar_comentario', [
'id_estacion' => $record['id_estacion'] ?? $permisos['id_estacion'],
'id_usuario' => $permisos['id_usuario'],
'year' => $record['year'] ?? '',
'mes' => $record['mes'] ?? 0,
'folio' => $record['folio'] ?? '',
'cuenta' => $record['cuenta'] ?? '',
]);
echo json_encode(['success' => true, 'message' => 'Comentario agregado exitosamente.']);
} else {
echo json_encode(['success' => false, 'message' => 'Error al agregar comentario.']);
}
exit;
}

public function getDocumentos()
{
header('Content-Type: application/json; charset=utf-8');
try {
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
echo json_encode(['success' => false, 'data' => []]);
exit;
}
$data = SolicitudValeService::getDocumentos($id);
echo json_encode(['success' => true, 'data' => $data]);
} catch (\Throwable $e) {
echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;
}

public function addDocumento()
{
header('Content-Type: application/json; charset=utf-8');

$id = (int)($_POST['id'] ?? 0);
$nombre = $_POST['nombre'] ?? '';

if (!$id || !$nombre) {
echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
exit;
}

if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== 0) {
echo json_encode(['success' => false, 'message' => 'Debe seleccionar un archivo.']);
exit;
}

$added = SolicitudValeService::addDocumento($id, $nombre, $_FILES['archivo']);
echo json_encode([
'success' => $added,
'message' => $added ? 'Documento agregado exitosamente.' : 'Error al agregar documento.',
]);
exit;
}

public function deleteDocumento()
{
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);

if (!$id) {
echo json_encode(['success' => false, 'message' => 'ID no proporcionado.']);
exit;
}

$deleted = SolicitudValeService::deleteDocumento($id);
if ($deleted) {
$uploadDir = __DIR__ . '/../../public/uploads/archivos/solicitud-vales/';
if ($deleted['documento'] && file_exists($uploadDir . $deleted['documento'])) unlink($uploadDir . $deleted['documento']);
echo json_encode(['success' => true, 'message' => 'Documento eliminado exitosamente.']);
} else {
echo json_encode(['success' => false, 'message' => 'Error al eliminar el documento.']);
}
exit;
}
}
