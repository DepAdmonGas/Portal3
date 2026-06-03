<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ControlVolumetricoService;
use App\Services\DropdownYearMesService;
use App\Services\ModuloDptoOperativoService;

class ControlVolumetricoController extends BaseController
{
protected string $modulo = 'corporativo';

public function index($idYear, $idMes)
{
$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

$idEstacion = $this->estacionId();
$multiEstacion = $this->isMultiEs();

if (!$idEstacion || ($multiEstacion && $idEstacion === 8)) {
$data = [
'title' => 'Control Volumétrico (' . nombremes($idMes) . ' ' . $idYear . ')',
'idYear' => $idYear,
'idMes' => $idMes,
'idEstacion' => 0,
'multiestacion' => $multiEstacion,
'esDireccionOperaciones' => false,
'help' => false,
];
View::render('departamento-operativo/1-corporativo/control-volumetrico/index', $data, 'departamento-operativo');
return;
}

$idMesDb = ControlVolumetricoService::getMesId($idEstacion, $idYear, $idMes);
if (!$idMesDb) {
View::render('errors/404', [], 'departamento-operativo');
return;
}

$puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer');
if (!$puedeLeer) {
View::render('errors/403', [], 'departamento-operativo');
return;
}

ControlVolumetricoService::asegurarRegistros($idMesDb, $idEstacion, $idYear, $idMes);

$usuario = \App\Core\Auth::user();
$tipoPuesto = $usuario && $usuario->puesto ? $usuario->puesto->tipo_puesto : '';

$permisos = ControlVolumetricoService::getPermisos();
$estado = ControlVolumetricoService::getEstado($idMesDb);

$title = 'Control Volumétrico (' . nombremes($idMes) . ' ' . $idYear . ')';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
Breadcrumb::add('Corte Diario ' . nombremes($idMes) . ' ' . $idYear . '', '/departamento-operativo/corporativo/corte-diario/' . $idYear . '/' . $idMes . '');
Breadcrumb::add('<span class="breadcrumb-item active">Control Volumétrico (' . nombremes($idMes) . ' ' . $idYear . ')</span>', '');

$data = [
'title' => $title,
'idYear' => $idYear,
'idMes' => $idMes,
'idMesDb' => $idMesDb,
'idEstacion' => $idEstacion,
'estado' => $estado,
'multiestacion' => $permisos['multiestacion'],
'esDireccionOperaciones' => $permisos['es_direccion_operaciones'],
'tipoPuesto' => $tipoPuesto,
'links' => [
'/assets/libs/select2/dist/css/select2.min.css',
'/assets/css/select2-modal.css',
],
'scripts' => [
'/assets/js/vendor.min.js',
'/assets/libs/select2/dist/js/select2.full.min.js',
'/assets/js/departamento-operativo/1-corporativo/actions.control.volumetrico.init.js',
],
'help' => false,
];

View::render('departamento-operativo/1-corporativo/control-volumetrico/index', $data, 'departamento-operativo');
}

public function getData()
{
header('Content-Type: application/json; charset=utf-8');
$idMes = (int) ($_GET['id_mes'] ?? 0);
if (!$idMes) {
echo json_encode(['success' => false, 'message' => 'ID de mes requerido']);
exit;
}

$data = ControlVolumetricoService::getData($idMes);
$documentos = ControlVolumetricoService::getDocumentos($idMes);
$prefijos = ControlVolumetricoService::getPrefijos($idMes);
$comentarios = ControlVolumetricoService::getComentarios($idMes);
$estado = ControlVolumetricoService::getEstado($idMes);

echo json_encode([
'success' => true,
'estado' => $estado,
'data' => $data,
'documentos' => $documentos,
'prefijos' => $prefijos,
'comentarios' => $comentarios,
]);
exit;
}

public function editarResumen()
{
header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true);
$id = (int) ($input['id'] ?? 0);
$campo = $input['campo'] ?? '';
$valor = $input['valor'] ?? '';

$result = ControlVolumetricoService::editarResumenDato($id, $campo, $valor);
echo json_encode(['success' => $result]);
exit;
}

public function editarComentarioResumen()
{
header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true);
$id = (int) ($input['id'] ?? 0);
$comentario = $input['comentario'] ?? '';

$result = ControlVolumetricoService::editarComentarioResumen($id, $comentario);
echo json_encode(['success' => $result]);
exit;
}

public function editarAceite()
{
header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true);
$idMes = (int) ($input['id_mes'] ?? 0);
$valor = $input['valor'] ?? '0';

$result = ControlVolumetricoService::editarAceiteVolumetrico($idMes, $valor);
echo json_encode(['success' => $result]);
exit;
}

public function editarPrefijo()
{
header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true);
$id = (int) ($input['id'] ?? 0);
$total = $input['total'] ?? '0';

$result = ControlVolumetricoService::editarPrefijoTotal($id, $total);
echo json_encode(['success' => $result]);
exit;
}

public function agregarComentario()
{
header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true);
$idMes = (int) ($input['id_mes'] ?? 0);
$comentario = $input['comentario'] ?? '';

if (empty(trim($comentario))) {
echo json_encode(['success' => false, 'message' => 'El comentario no puede estar vacío']);
exit;
}

$result = ControlVolumetricoService::agregarComentario($idMes, $this->userId(), $comentario);
echo json_encode(['success' => $result]);
exit;
}

public function uploadDocumento()
{
header('Content-Type: application/json; charset=utf-8');
try {
$idMes = (int) ($_POST['id_mes'] ?? 0);
$fecha = $_POST['fecha'] ?? '';
$anexos = $_POST['anexos'] ?? '';

if (!$idMes) {
echo json_encode(['success' => false, 'message' => 'ID de mes requerido']);
exit;
}
if (empty($fecha)) {
echo json_encode(['success' => false, 'message' => 'La fecha es requerida']);
exit;
}
if (empty($anexos)) {
echo json_encode(['success' => false, 'message' => 'El anexo es requerido']);
exit;
}
if (!isset($_FILES['documento']) || $_FILES['documento']['error'] !== UPLOAD_ERR_OK) {
$errorMsg = 'Archivo requerido';
if (isset($_FILES['documento'])) {
$phpErrors = [
UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por PHP',
UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo del formulario',
UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
UPLOAD_ERR_NO_FILE => 'No se seleccionó ningún archivo',
UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal de PHP',
UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en el disco',
];
$errorMsg = $phpErrors[$_FILES['documento']['error']] ?? 'Error al subir el archivo (código: ' . $_FILES['documento']['error'] . ')';
}
echo json_encode(['success' => false, 'message' => $errorMsg]);
exit;
}

$result = ControlVolumetricoService::subirDocumento($idMes, $_FILES['documento'], $fecha, $anexos);
if ($result) {
echo json_encode(['success' => true, 'message' => 'Registro agregado exitosamente.']);
} else {
echo json_encode(['success' => false, 'message' => 'Error al guardar el archivo en el servidor. Verifique los permisos de la carpeta uploads/archivos.']);
}
exit;
} catch (\Exception $e) {
echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
exit;
}
}

public function eliminarDocumento()
{
header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true);
$id = (int) ($input['id'] ?? 0);

$result = ControlVolumetricoService::eliminarDocumento($id);
echo json_encode(['success' => $result, 'message' => 'Documento eliminado exitosamente']);
exit;
}

public function getDocumentosList()
{
header('Content-Type: application/json; charset=utf-8');
$idMes = (int) ($_GET['id_mes'] ?? 0);
if (!$idMes) {
echo json_encode(['success' => false, 'message' => 'ID de mes requerido']);
exit;
}
echo json_encode([
'success' => true,
'documentos' => ControlVolumetricoService::getDocumentos($idMes),
]);
exit;
}

public function getComentariosList()
{
header('Content-Type: application/json; charset=utf-8');
$idMes = (int) ($_GET['id_mes'] ?? 0);
if (!$idMes) {
echo json_encode(['success' => false, 'message' => 'ID de mes requerido']);
exit;
}
echo json_encode([
'success' => true,
'comentarios' => ControlVolumetricoService::getComentarios($idMes),
]);
exit;
}
}
