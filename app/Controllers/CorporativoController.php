<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\DropdownYearMesService;
use App\Services\CorteDiarioService;
use App\Models\Operativo\CorteYear;
use App\Models\Operativo\CorteMes;
use App\Models\Operativo\CorteDia;
use App\Models\Operativo\CorteDiaHist;
use App\Core\Session;
use App\Core\Auth;
use Illuminate\Support\Carbon;

class CorporativoController extends BaseController{
protected string $modulo = 'corporativo';

public function corteDiarioRedirect()
{
$year = date('Y');
$mes = date('n');
header('Location: /departamento-operativo/corporativo/corte-diario/' . $year . '/' . $mes);
exit;
}

public function corteDiarioIndex($idYear, $idMes)
{
$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

$permisos = CorteDiarioService::getPermisos();
$title = 'Corte Diario, ' . nombremes($idMes) . ' ' . $idYear;

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
Breadcrumb::add('<span class="breadcrumb-item active">Corte Diario</span>', '');
Breadcrumb::add(DropdownYearMesService::dropdownMes($idYear, $idMes), '');
Breadcrumb::add(DropdownYearMesService::dropdownYearManual($idYear, $idMes), '');

$data = [
'title'    => $title,
'idYear'   => $idYear,
'idMes'    => $idMes,
'yearMesTemplate' => '/departamento-operativo/corporativo/corte-diario/{year}/{mes}',
'multiestacion' => $permisos['multiestacion'],
'esDireccionOperaciones' => $permisos['es_direccion_operaciones'],
'estacionId' => $this->estacionId(),
'links' => [
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
],
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/departamento-operativo/1-corporativo/corte.diario.datatable.init.js?v=' . time(),
'/assets/js/departamento-operativo/1-corporativo/actions.corte.diario.init.js?v=' . time()
],
'help' => false
];

View::render('departamento-operativo/1-corporativo/corte-diario-index', $data, 'departamento-operativo');
}

public function corteDiarioDatatable($idYear, $idMes)
{
header('Content-Type: application/json; charset=utf-8');

$idEstacion = $this->estacionId();
$multiEstacion = $this->isMultiEs();

if (!$idEstacion) {
echo json_encode([
'data' => [],
'multiestacion' => $multiEstacion
]);
exit;
}

CorteDiarioService::asegurarDiasDelMes($idYear, $idMes, $idEstacion);

$rows = CorteDiarioService::getDiasCorte($idYear, $idMes, $idEstacion);
$permisos = CorteDiarioService::getPermisos();

$idMesDb = CorteMes::whereHas('year', function ($q) use ($idEstacion, $idYear) {
$q->where('id_estacion', $idEstacion)
->where('year', $idYear);
})->where('mes', $idMes)->value('id');

$resumen = $idMesDb
? CorteDiarioService::getResumenMensual($idMesDb)
: [];

$hoy = Carbon::today();

$data = [];

foreach ($rows as $row) {

$idDia = $row->idDia;
$fecha = $row->fecha;

$esPasado = $hoy->greaterThanOrEqualTo($fecha);
$textClass = $esPasado? '': 'opacity-25';
$fechaFormateada = formatearFecha($row->fecha->format('Y-m-d'));

$btnEditar = $this->renderBotonEditar($esPasado,$multiEstacion,$textClass,$idDia,$fecha);

$data[] = [

"fecha" =>
"<span class='{$textClass}'>{$fechaFormateada}</span>",

"ventas" =>
$this->renderIconoLink(
$esPasado,
$textClass,
"/departamento-operativo/ventas/{$idYear}/{$idMes}/{$idDia}",
"ti ti-currency-dollar"
),

"tpv" =>
$this->renderIconoLink(
$esPasado,
$textClass,
"/departamento-operativo/cierre-lote/{$idYear}/{$idMes}/{$idDia}",
"ti ti-receipt"
),

"impuestos" =>
$this->renderIconoLink(
$esPasado,
$textClass,
"/departamento-operativo/impuestos/{$idYear}/{$idMes}/{$idDia}",
"ti ti-receipt-tax"
),

"monedero" =>
$this->renderIconoLink(
$esPasado,
$textClass,
"/departamento-operativo/monedero/{$idYear}/{$idMes}/{$idDia}",
"ti ti-wallet"
),

"clientes" =>
$this->renderIconoLink(
$esPasado,
$textClass,
"/departamento-operativo/clientes/{$idYear}/{$idMes}/{$idDia}",
"ti ti-users"
),

"editar" => $btnEditar
];
}

echo json_encode([
"data" => $data,
"multiestacion" => $multiEstacion,
"resumen" => $resumen
]);

exit;
}

public function corteDiarioEditar()
{
header('Content-Type: application/json; charset=utf-8');

if (!$this->isMultiEs()) {
http_response_code(403);
echo json_encode(['success' => false, 'message' => 'No tienes permiso para editar']);
exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$idCorteDia = $input['id'] ?? null;
$ventas = $input['ventas'] ?? null;
$tpv = $input['tpv'] ?? null;
$monedero = $input['monedero'] ?? null;
$observaciones = $input['observaciones'] ?? null;

if (!$idCorteDia) {
echo json_encode(['success' => false, 'message' => 'ID requerido']);
exit;
}

$corte = CorteDia::find($idCorteDia);
if (!$corte) {
echo json_encode(['success' => false, 'message' => 'Registro no encontrado']);
exit;
}

$corte->ventas = $ventas !== null ? (int) $ventas : $corte->ventas;
$corte->tpv = $tpv !== null ? (int) $tpv : $corte->tpv;
$corte->monedero = $monedero !== null ? (int) $monedero : $corte->monedero;
$corte->save();

if ($observaciones !== null) {
$corte->observaciones()->updateOrCreate(
['idreporte_dia' => $idCorteDia],
['observaciones' => $observaciones]
);
}

$usuario = Session::get('usuario');
$idUsuario = $usuario['id'] ?? 0;
$nombreUsuario = $usuario['nombre'] ?? 'Desconocido';

CorteDiaHist::create([
'id_corte' => $idCorteDia,
'id_usuario' => $idUsuario,
'fecha' => date('Y-m-d H:i:s'),
'detalle' => 'Actualización por: ' . $nombreUsuario
]);

register_shutdown_function(function () use ($idCorteDia, $idUsuario, $nombreUsuario) {
CorteDiarioService::notificarEdicion($idCorteDia, $idUsuario, $nombreUsuario);
});

echo json_encode(['success' => true, 'message' => 'Corte actualizado correctamente']);
exit;
}

public function corteDiarioGetDetalle()
{
header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
echo json_encode(['success' => false, 'message' => 'ID requerido']);
exit;
}

$corte = CorteDia::with('observaciones')->find($id);
if (!$corte) {
echo json_encode(['success' => false, 'message' => 'No encontrado']);
exit;
}

echo json_encode([
'success' => true,
'data' => [
'id' => $corte->id,
'fecha' => $corte->fecha,
'ventas' => $corte->ventas,
'tpv' => $corte->tpv,
'monedero' => $corte->monedero,
'observaciones' => $corte->observaciones->observaciones ?? '',
]
]);
exit;
}

public function corteDiarioGetHistorial()
{
header('Content-Type: application/json; charset=utf-8');

if (!$this->isMultiEs()) {
http_response_code(403);
echo json_encode(['success' => false, 'message' => 'No tienes permisos']);
exit;
}

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
echo json_encode(['success' => false, 'data' => []]);
exit;
}

$historial = CorteDiarioService::getHistorial($id);

echo json_encode(['success' => true, 'data' => $historial]);
exit;
}

public function corteDiarioActivar()
{
header('Content-Type: application/json; charset=utf-8');

if (!$this->isMultiEs()) {
http_response_code(403);
echo json_encode(['success' => false, 'message' => 'No tienes permiso']);
exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$idCorteDia = (int) ($input['id'] ?? 0);
$detalle = trim($input['detalle'] ?? '');

if (!$idCorteDia) {
echo json_encode(['success' => false, 'message' => 'ID requerido']);
exit;
}

if ($detalle === '') {
echo json_encode(['success' => false, 'message' => 'El motivo es obligatorio']);
exit;
}

$usuario = Session::get('usuario');
$idUsuario = $usuario['id'] ?? null;

if (!$idUsuario) {
echo json_encode(['success' => false, 'message' => 'Sesión inválida']);
exit;
}

$result = CorteDiarioService::activarCorte($idCorteDia, $idUsuario, $detalle);

if ($result) {
$nombreUsuario = $usuario['nombre'] ?? 'Desconocido';

register_shutdown_function(function () use ($idCorteDia, $idUsuario, $nombreUsuario, $detalle) {
CorteDiarioService::notificarActivacion($idCorteDia, $idUsuario, $nombreUsuario, $detalle);
});

echo json_encode(['success' => true, 'message' => 'Corte activado exitosamente']);
} else {
echo json_encode(['success' => false, 'message' => 'No se encontró el registro de corte']);
}
exit;
}

private function renderIconoLink($esPasado, $textClass, $url, $icono)
{
if ($esPasado) {
return "<a href='{$url}' class='d-flex justify-content-center align-items-center {$textClass}'><i class='{$icono} fs-8'></i></a>";
}
return "<span class='d-flex justify-content-center align-items-center {$textClass}'><i class='{$icono} fs-8 text-muted'></i></span>";
}

private function renderBotonEditar($esPasado, $multiEstacion, $textClass, $idDia, $fecha)
{
if (!$multiEstacion) {
return '';
}

$histCount = CorteDiarioService::getHistCount($idDia);

$badgeHist = '';

if ($histCount > 0) {

$badgeHist = '
<span class="badge-historico">
' . $histCount . '
</span>';
}

if ($esPasado) {

return '
<a href="" class="btn-edit-corte btn-badge-historico d-flex justify-content-center align-items-center ' . $textClass . '" data-id="' . $idDia . '" data-fecha="' . $fecha . '">
<i class="ti ti-edit fs-8"></i> ' . $badgeHist . '
</a>';
}

return '
<span class="d-flex justify-content-center align-items-center position-relative ' . $textClass . '">
<i class="ti ti-edit fs-8 text-muted"></i> ' . $badgeHist . '
</span>';
}

}
