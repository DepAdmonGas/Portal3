<?php
namespace App\Controllers;
use App\Core\View;
use App\Services\BitacoraAditivoService;
use App\Services\BitacoraAditivoContext;
use App\Services\ModuleStationService;
use App\Core\Breadcrumb;
use Dompdf\Dompdf;
use Dompdf\Options;

class AditivoController extends BaseController{

protected string $modulo = 'bitacora-aditivo';

private function ctx(): BitacoraAditivoContext
{
return BitacoraAditivoContext::resolver();
}

private function assetsFor(string $layout, array $paths): array
{
if ($layout === 'main') {
return $paths;
}
return array_map(function (string $path) {
return '/assets' . $path;
}, $paths);
}

public function index(){

$ctx = $this->ctx();
$title = 'Bitácora de aditivo';

if ($ctx->getContexto() === BitacoraAditivoContext::CONTEXTO_IMPORTACION) {
if (!$ctx->getCapacidades()['puedeVer']) {
View::render('errors/403', [], $ctx->getLayout());
return;
}
}

$estacionId = $ctx->getEstacionId();

$inventario = ['gasolina' => 0, 'diesel' => 0];
if ($estacionId) {
$inventario = BitacoraAditivoService::getTotalInventario($estacionId);
}

foreach ($ctx->breadcrumbs($title) as [$label, $url]) {
Breadcrumb::add($label, $url);
}

if (!$this->guardModuleAccess('bitacora-aditivo', $title, $ctx->getLayout())) {
return;
}

$data = [
'title' => $title,
'capacidades' => $ctx->getCapacidades(),
'contexto' => $ctx->getContexto(),
'baseUrl' => $ctx->getBaseUrl(),
'modulo' => $this->modulo,
'moduleStationKey' => 'bitacora-aditivo',
'estacionId' => $estacionId,
'estacionProductos' => $ctx->getProductos(),
'filtro_usuario' => $this->filtro_usuario,
'inventario' => $inventario,
'links' =>$this->assetsFor($ctx->getLayout(), [
'/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
]),
'scripts' => $this->assetsFor($ctx->getLayout(), [
'/js/vendor.min.js',
'/libs/datatables.net/js/jquery.dataTables.min.js',
'/js/core/module-station-selector.js?v=' . time(),
'/js/bitacora/aditivo.datatable.init.js??v=' . time(),
'/js/bitacora/actions.init.js??v=' . time(),
]),
'help' => false
];

View::render('aditivo/index', $data, $ctx->getLayout());
}

public function resumen(){

$ctx = $this->ctx();
$title = 'Resumen aditivo';

if (!$ctx->getCapacidades()['puedeVerResumen']) {
View::render('errors/403', [], $ctx->getLayout());
return;
}

foreach ($ctx->breadcrumbs($title, true) as [$label, $url]) {
Breadcrumb::add($label, $url);
}

$data = [
'title' => $title,
'capacidades' => $ctx->getCapacidades(),
'contexto' => $ctx->getContexto(),
'baseUrl' => $ctx->getBaseUrl(),
'modulo' => $this->modulo,
'estacionId' => $ctx->getEstacionId(),
'links' =>$this->assetsFor($ctx->getLayout(), [
'/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
]),
'scripts' => $this->assetsFor($ctx->getLayout(), [
'/js/vendor.min.js',
'/libs/datatables.net/js/jquery.dataTables.min.js',
'/js/bitacora/resumen.datatable.init.js?v=' . time(),
]),
'help' => false
];

View::render('aditivo/resumen', $data, $ctx->getLayout());
}

private function filasResumen(): array
{
$ctx = $this->ctx();

$estaciones = $ctx->getEstaciones();
$ids = array_column($estaciones, 'id');

$resumen = BitacoraAditivoService::getResumen($ids);
$resumenPorEstacion = [];
foreach ($resumen as $fila) {
$resumenPorEstacion[(int) $fila['id_estacion']] = $fila;
}

$filas = [];
foreach ($estaciones as $estacion) {
$id = (int) $estacion['id'];
$datos = $resumenPorEstacion[$id] ?? ['gasolina' => 0, 'diesel' => 0];
$filas[] = [
'estacion' => $estacion['nombre'],
'gasolina' => (float) ($datos['gasolina'] ?? 0),
'diesel'   => (float) ($datos['diesel'] ?? 0),
];
}

return $filas;
}

public function datatableResumen(){

$ctx = $this->ctx();

if (!$ctx->getCapacidades()['puedeVerResumen']) {
echo json_encode(['data' => [], 'totales' => ['gasolina' => 0, 'diesel' => 0]]);
exit;
}

$filas = $this->filasResumen();

$totalGasolina = 0;
$totalDiesel = 0;
foreach ($filas as $fila) {
$totalGasolina += $fila['gasolina'];
$totalDiesel += $fila['diesel'];
}

echo json_encode([
'data' => $filas,
'totales' => [
'gasolina' => $totalGasolina,
'diesel'   => $totalDiesel,
]
]);
exit;
}

public function resumenPdf(){

$ctx = $this->ctx();

if (!$ctx->getCapacidades()['puedeVerResumen']) {
View::render('errors/403', [], $ctx->getLayout());
return;
}

$filas = $this->filasResumen();

$totalGasolina = 0;
$totalDiesel = 0;
foreach ($filas as $fila) {
$totalGasolina += $fila['gasolina'];
$totalDiesel += $fila['diesel'];
}

$logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

$rows = '';
foreach ($filas as $fila) {
$rows .= '<tr>
<td>' . htmlspecialchars((string) $fila['estacion']) . '</td>
<td class="text-center">' . $fila['gasolina'] . ' <small>(Galones)</small></td>
<td class="text-center">' . $fila['diesel'] . ' <small>(Galones)</small></td>
</tr>';
}

$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Resumen de inventario de aditivo</title>
<style>
@page{
    margin:0.4cm;
}
body{
    font-family:Arial, Helvetica, sans-serif;
    font-size:10px;
    color:#212529;
}
table{
    width:100%;
    border-collapse:collapse;
}
th,
td{
    border:1px solid #dee2e6;
    padding:3px;
    vertical-align:middle;
}
th{
    background:#F2F2F2;
    font-weight:bold;
}
.text-center {
    text-align:center !important;
}
.total{
    background:#e2e3e5;
    font-weight:bold;
}
</style>
</head>
<body>
<div class="text-start">
    <img src="' . $logo . '" width="180">
</div>
<h2 class="text-start">Resumen de inventario de aditivo</h2>
<table>
<thead>
<tr>
<th>Estación</th>
<th>Gasolina Hitec 6590C</th>
<th>Diesel Hitec 4133G</th>
</tr>
</thead>
<tbody>
' . $rows . '
<tr class="total">
<td>Total</td>
<td class="text-center">' . $totalGasolina . ' <small>(Galones)</small></td>
<td class="text-center">' . $totalDiesel . ' <small>(Galones)</small></td>
</tr>
</tbody>
</table>
</body>
</html>
';

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Arial');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

return $dompdf->stream(
'Resumen-inventario-aditivo-estaciones.pdf',
[
'Attachment' => true
]
);
}

public function datatableAditivo(){

$ctx = $this->ctx();
$idEstacion = $ctx->getEstacionId();

if (!$idEstacion) {
echo json_encode(['data' => [], 'permisos' => ['editar' => false, 'eliminar' => false]]);
exit;
}

$capacidades = $ctx->getCapacidades();

$permisos = [
'eliminar' => $capacidades['puedeEliminar'],
'editar' => $capacidades['puedeEditar']
];

$aditivo = BitacoraAditivoService::getBitacora($idEstacion);

echo json_encode([
"data" => $aditivo,
"permisos" => $permisos
]);

exit;
}

public function totalInventario()
{
$ctx = $this->ctx();
$idEstacion = $ctx->getEstacionId();

echo json_encode(BitacoraAditivoService::getTotalInventario((int) $idEstacion));
exit;
}


public function deleteAditivo(){

header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;

if (!$this->ctx()->getCapacidades()['puedeEliminar']) {
echo json_encode([
'success' => false,
'message' => 'No tienes permiso para eliminar'
]);
exit;
}

if (!$id) {
echo json_encode(['success' => false,'message' => 'ID requerido']);
exit;
}

echo json_encode(BitacoraAditivoService::eliminarBitacora($id));
exit;
}

public function createAditivo(){

header('Content-Type: application/json; charset=utf-8');
$data = json_decode(file_get_contents('php://input'), true);

if (!$this->ctx()->getCapacidades()['puedeCrear']) {
echo json_encode([
'success' => false,
'message' => 'No tienes permiso para crear'
]);
exit;
}


$litros     = sanitize_input($data['litros'] ?? null, 'float');
$producto   = sanitize_input($data['producto'] ?? null, 'string');
$fecha      = sanitize_input($data['fecha'] ?? null, 'string');
$factura    = sanitize_input($data['no_factura'] ?? null, 'string');

$ctx = $this->ctx();
$idEstacion = $ctx->getEstacionId();

if (!$idEstacion) {
echo json_encode(['success' => false, 'message' => 'Debes seleccionar una estación primero']);
exit;
}

// Validar campos obligatorios
$errors = validate_input($data, [
'litros' => 'required|numeric',
'producto' => 'required|max:100',
'fecha' => 'required|max:20'
]);

if (!empty($errors)) {
echo json_encode(['success' => false, 'errors' => $errors]);
exit;
}

echo json_encode(BitacoraAditivoService::crearBitacora(
(int) $idEstacion,
(float) ($litros ?? 0),
(string) ($producto ?? ''),
(string) ($fecha ?? ''),
$factura
));
exit;
}

public function updateAditivo()
{
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$id = sanitize_input($data['id'] ?? null, 'int');
$noFactura = sanitize_input($data['no_factura'] ?? null, 'string');

if (!$id || !$noFactura) {
echo json_encode([
'success' => false,
'message' => 'Datos incompletos'
]);
return;
}

if (!$this->ctx()->getCapacidades()['puedeEditar']) {
echo json_encode([
'success' => false,
'message' => 'Sin permisos'
]);
return;
}

echo json_encode(BitacoraAditivoService::editarFactura((int) $id, (string) ($noFactura ?? '')));
}

//--------- Reporte Bitacora Aditivo --------------
//-------------------------------------------------

public function reporte(){

$ctx = $this->ctx();
$title = 'Reporte aditivo';

if ($ctx->getContexto() === BitacoraAditivoContext::CONTEXTO_IMPORTACION) {
if (!$ctx->getCapacidades()['puedeVer']) {
View::render('errors/403', [], $ctx->getLayout());
return;
}
}

$estacionId = $ctx->getEstacionId();

foreach ($ctx->breadcrumbs($title, true) as [$label, $url]) {
Breadcrumb::add($label, $url);
}

$data = [
'title' => $title,
'capacidades' => $ctx->getCapacidades(),
'contexto' => $ctx->getContexto(),
'baseUrl' => $ctx->getBaseUrl(),
'modulo' => $this->modulo,
'moduleStationKey' => 'bitacora-aditivo',
'estacionId' => $estacionId,
'estacionProductos' => $ctx->getProductos(),
'filtro_usuario' => $this->filtro_usuario,
'links' =>$this->assetsFor($ctx->getLayout(), [
'/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
]),
'scripts' => $this->assetsFor($ctx->getLayout(), [
'/js/vendor.min.js',
'/libs/datatables.net/js/jquery.dataTables.min.js',
'/js/core/module-station-selector.js?v=' . time(),
'/js/bitacora/reporte.datatable.init.js?v=' . time(),
'/js/bitacora/reporte.actions.init.js?v=' . time(),
])
];

View::render('aditivo/reporte', $data, $ctx->getLayout());
}

public function datatableReporte(){

$ctx = $this->ctx();
$idEstacion = $ctx->getEstacionId();

if (!$idEstacion) {
echo json_encode(['data' => [], 'permisos' => ['eliminar' => false, 'descargar' => false, 'editar' => false]]);
exit;
}

$capacidades = $ctx->getCapacidades();

$reporte = BitacoraAditivoService::getReportes($idEstacion);

echo json_encode([
"data" => $reporte,
"permisos" => [
"eliminar" => $capacidades['puedeEliminar'],
"descargar" => $capacidades['puedeDescargar'],
"editar"   => $capacidades['puedeEditar']
]
]);

exit;
}

public function createReporte()
{
header('Content-Type: application/json; charset=utf-8');

// Permisos
if (!$this->ctx()->getCapacidades()['puedeCrear']) {
echo json_encode([
'success' => false,
'message' => 'No tienes permiso para crear'
]);
exit;
}


$fecha = sanitize_input($_POST['fecha'] ?? null, 'string');
$file  = $_FILES['documento'] ?? null;

// Validar campos obligatorios
$errors = validate_input($_POST, [
'fecha' => 'required|max:20'
]);

if (!empty($errors)) {
echo json_encode(['success' => false, 'errors' => $errors]);
exit;
}

echo json_encode(BitacoraAditivoService::guardarReporte(
(int) ($this->ctx()->getEstacionId() ?? 0),
(int) ($this->userId() ?? 0),
(string) ($fecha ?? ''),
$file
));
exit;
}

public function deleteReporte(){
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;

if (!$this->ctx()->getCapacidades()['puedeEliminar']) {
echo json_encode([
'success' => false,
'message' => 'No tienes permiso para eliminar'
]);
exit;
}

if (!$id) {
echo json_encode(['success' => false,'message' => 'ID requerido']);
exit;
}

echo json_encode(BitacoraAditivoService::eliminarReporte((int) $id));
exit;
}

//----------- Inventario -----------------
//----------------------------------------
public function inventario(){

$ctx = $this->ctx();
$title = 'Inventario aditivo';

if ($ctx->getContexto() === BitacoraAditivoContext::CONTEXTO_IMPORTACION) {
if (!$ctx->getCapacidades()['puedeVer']) {
View::render('errors/403', [], $ctx->getLayout());
return;
}
}

$estacionId = $ctx->getEstacionId();

$inventarioData = ['gasolina' => 0, 'diesel' => 0];
if ($estacionId) {
$inventarioData = BitacoraAditivoService::getTotalInventario($estacionId);
}

foreach ($ctx->breadcrumbs($title, true) as [$label, $url]) {
Breadcrumb::add($label, $url);
}

$data = [
'title' => $title,
'capacidades' => $ctx->getCapacidades(),
'contexto' => $ctx->getContexto(),
'baseUrl' => $ctx->getBaseUrl(),
'modulo' => $this->modulo,
'moduleStationKey' => 'bitacora-aditivo',
'estacionId' => $estacionId,
'estacionProductos' => $ctx->getProductos(),
'inventario' => $inventarioData,
'filtro_usuario' => $this->filtro_usuario,
'links' =>$this->assetsFor($ctx->getLayout(), [
'/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
]),
'scripts' => $this->assetsFor($ctx->getLayout(), [
'/js/vendor.min.js',
'/libs/datatables.net/js/jquery.dataTables.min.js',
'/js/core/module-station-selector.js?v=' . time(),
'/js/bitacora/inventario.datatable.init.js?v=' . time(),
'/js/bitacora/inventario.actions.init.js?v=' . time(),
])
];

View::render('aditivo/inventario', $data, $ctx->getLayout());
}

public function datatableInventario(){

$ctx = $this->ctx();
$idEstacion = $ctx->getEstacionId();

if (!$idEstacion) {
echo json_encode(['data' => [], 'permisos' => ['eliminar' => false, 'editar' => false]]);
exit;
}

$capacidades = $ctx->getCapacidades();

$inventario = BitacoraAditivoService::getInventarioHist($idEstacion);

echo json_encode([
"data" => $inventario,
"permisos" => [
"eliminar" => $capacidades['puedeEliminar'],
"editar"   => $capacidades['puedeEditar']
]
]);

exit;
}

public function createInventario(){

header('Content-Type: application/json; charset=utf-8');
$data = json_decode(file_get_contents('php://input'), true);

$ctx = $this->ctx();
$idEstacion = $ctx->getEstacionId();

if (!$idEstacion) {
echo json_encode(['success' => false, 'message' => 'Debes seleccionar una estación']);
exit;
}

if (!$ctx->getCapacidades()['puedeCrear']) {
echo json_encode([
'success' => false,
'message' => 'No tienes permiso para crear'
]);
exit;
}

$gasolina = sanitize_input($data['gasolina'] ?? 0, 'float');
$diesel   = sanitize_input($data['diesel'] ?? 0, 'float');

echo json_encode(BitacoraAditivoService::crearInventario(
(int) $idEstacion,
(float) ($gasolina ?? 0),
(float) ($diesel ?? 0)
));
exit;
}


}