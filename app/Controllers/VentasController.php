<?php
namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Session;
use App\Core\Auth;
use App\Services\VentasService;
use App\Services\DropdownYearMesService;
use App\Services\ModuloDptoOperativoService;
use App\Services\TelegramService;
use App\Services\EmailService;
use App\Models\Operativo\CorteDia;
use App\Models\Operativo\VentasDia;
use App\Models\Operativo\VentasDiaOtros;
use App\Models\Operativo\Prosegur;
use App\Models\Operativo\TarjetasCB;
use App\Models\Operativo\ClientesControlgas;
use App\Models\Operativo\PagoCliente;
use App\Models\Operativo\AceiteLubricante;
use App\Models\Operativo\CorteDiaArchivo;
use App\Models\Operativo\CorteDiaFirmas;
use App\Models\Operativo\CorteDiaToken;
use App\Models\Operativo\Observacione;
use App\Models\Estacion;
use Dompdf\Dompdf;
use Dompdf\Options;

class VentasController extends BaseController
{
protected string $modulo = 'corporativo';

public function index($idYear, $idMes, $idDia)
{
$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

$permisos = VentasService::getPermisos();
$estado = VentasService::getEstado((int) $idDia);
$fecha = VentasService::getFecha((int) $idDia);
$idEstacion = $this->estacionId();

$multiEstacion = $permisos['multiestacion'];

$permisoCrear = ModuloDptoOperativoService::validaPermiso('corporativo', 'crear');
$permisoEditar = ModuloDptoOperativoService::validaPermiso('corporativo', 'editar');
$permisoEliminar = ModuloDptoOperativoService::validaPermiso('corporativo', 'eliminar');
$permisoDescargar = ModuloDptoOperativoService::validaPermiso('corporativo', 'descargar');

if ($estado == 0) {
VentasService::asegurarRegistros((int) $idDia, $idEstacion, $idYear, $idMes);
}

$title = 'Ventas (' . formatearFecha($fecha) . ')';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
Breadcrumb::add('Corte Diario ' . nombreMes($idMes) . ' ' . $idYear . '', '/departamento-operativo/corporativo/corte-diario/' . $idYear . '/' . $idMes . '');
Breadcrumb::add('<span class="breadcrumb-item active">Ventas (' . formatearFecha($fecha) . ')</span>', '');

$data = [
'title' => $title,
'idYear' => $idYear,
'idMes' => $idMes,
'idDia' => (int) $idDia,
'estado' => $estado,
'fecha' => formatearFecha($fecha),
'multiestacion' => $multiEstacion,
'esDireccionOperaciones' => $permisos['es_direccion_operaciones'],
'idEstacion' => $idEstacion,
'observaciones' => VentasService::getObservaciones((int) $idDia),
'puedeCrear' => $permisoCrear,
'puedeEditar' => $permisoEditar,
'puedeEliminar' => $permisoEliminar,
'puedeDescargar' => $permisoDescargar,
'esSuperviso' => $permisos['es_superviso'],
'esVoBo' => $permisos['es_vobo'],
'ocultarSelectorEstacion' => true,
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/signature_pad/docs/js/signature_pad.umd.min.js?v=' . time(),
'/assets/js/departamento-operativo/1-corporativo/actions.ventas.init.js?v=' . time(),
],
'help' => false
];

View::render('departamento-operativo/1-corporativo/ventas/index', $data, 'departamento-operativo');
}

public function getData($idDia)
{
header('Content-Type: application/json; charset=utf-8');
$idReporte = (int) $idDia;

$estado = VentasService::getEstado($idReporte);
$permisos = VentasService::getPermisos();

$puedeEditar = ModuloDptoOperativoService::validaPermiso('corporativo', 'editar');
$puedeCrear = ModuloDptoOperativoService::validaPermiso('corporativo', 'crear');
$puedeEliminar = ModuloDptoOperativoService::validaPermiso('corporativo', 'eliminar');
$puedeDescargar = ModuloDptoOperativoService::validaPermiso('corporativo', 'descargar');

echo json_encode([
'success' => true,
'estado' => $estado,
'multiestacion' => $permisos['multiestacion'],
'puede_editar' => $puedeEditar,
'puede_crear' => $puedeCrear,
'puede_eliminar' => $puedeEliminar,
'puede_descargar' => $puedeDescargar,
'ventas_dia' => VentasService::getVentasDia($idReporte),
'ventas_dia_otros' => VentasService::getVentasDiaOtros($idReporte),
'prosegur' => VentasService::getProsegur($idReporte),
'tarjetas_cb' => VentasService::getTarjetasCB($idReporte),
'controlgas' => VentasService::getClientesControlgas($idReporte),
'pago_clientes' => VentasService::getPagoClientes($idReporte),
'aceites' => VentasService::getAceitesLubricantes($idReporte),
'documentos' => VentasService::getDocumentos($idReporte),
'observaciones' => VentasService::getObservaciones($idReporte),
'totales1234' => VentasService::getTotales1234($idReporte),
'totales_ventas' => VentasService::getTotalesVentas($idReporte),
'totales_aceites' => VentasService::getTotalesAceites($idReporte),
'total_pago_clientes' => VentasService::getTotalPagoClientes($idReporte),
'pago_total' => VentasService::getPagoTotal($idReporte),
'firmas' => VentasService::getFirmas($idReporte),
]);
exit;
}

public function newVenta($idDia)
{
header('Content-Type: application/json; charset=utf-8');
if (!ModuloDptoOperativoService::validaPermiso('corporativo', 'crear')) {
echo json_encode(['success' => false, 'message' => 'Permiso denegado']);
exit;
}
$idReporte = (int) $idDia;

$venta = VentasDia::create([
'idreporte_dia' => $idReporte,
'producto' => '',
'litros' => 0,
'jarras' => 0,
'precio_litro' => 0,
'ieps' => 0,
]);

$usuario = Session::get('usuario');
$idUsuario = $usuario['id'] ?? 0;
$nombreUsuario = $usuario['nombre'] ?? 'Desconocido';

register_shutdown_function(function () use ($idReporte, $idUsuario, $nombreUsuario) {
VentasService::notificarAgregarProducto($idReporte, $idUsuario, $nombreUsuario);
});

echo json_encode(['success' => true, 'data' => $venta]);
exit;
}

public function editVenta()
{
header('Content-Type: application/json; charset=utf-8');
if (!ModuloDptoOperativoService::validaPermiso('corporativo', 'editar')) {
echo json_encode(['success' => false, 'message' => 'Permiso denegado']);
exit;
}
$input = json_decode(file_get_contents('php://input'), true);

$id = (int) ($input['id'] ?? 0);
$field = $input['field'] ?? '';
$value = $input['value'] ?? '';

$allowed = ['producto', 'litros', 'jarras', 'precio_litro'];
if (!in_array($field, $allowed)) {
echo json_encode(['success' => false, 'message' => 'Campo no permitido']);
exit;
}

$venta = VentasDia::find($id);
if (!$venta) {
echo json_encode(['success' => false, 'message' => 'No encontrado']);
exit;
}

if ($field === 'producto') {
$ieps = 0;
switch (strtoupper($value)) {
case 'G SUPER':
$ieps = 0.4369;
break;
case 'G PREMIUM':
$ieps = 0.5331;
break;
case 'G DIESEL':
$ieps = 0.3626;
break;
}
$venta->update(['producto' => $value, 'ieps' => $ieps]);
} else {
$venta->update([$field => (float) $value]);
}

echo json_encode(['success' => true]);
exit;
}

public function editVentaOtros()
{
header('Content-Type: application/json; charset=utf-8');
if (!ModuloDptoOperativoService::validaPermiso('corporativo', 'editar')) {
echo json_encode(['success' => false, 'message' => 'Permiso denegado']);
exit;
}
$input = json_decode(file_get_contents('php://input'), true);

$id = (int) ($input['id'] ?? 0);
$value = (float) ($input['value'] ?? 0);

$otro = VentasDiaOtros::find($id);
if (!$otro) {
echo json_encode(['success' => false, 'message' => 'No encontrado']);
exit;
}

$otro->update(['importe' => $value]);
echo json_encode(['success' => true]);
exit;
}

public function editProsegur()
{
header('Content-Type: application/json; charset=utf-8');
if (!ModuloDptoOperativoService::validaPermiso('corporativo', 'editar')) {
echo json_encode(['success' => false, 'message' => 'Permiso denegado']);
exit;
}
$input = json_decode(file_get_contents('php://input'), true);

$id = (int) ($input['id'] ?? 0);
$field = $input['field'] ?? '';
$value = $input['value'] ?? '';

$prosegur = Prosegur::find($id);
if (!$prosegur) {
echo json_encode(['success' => false, 'message' => 'No encontrado']);
exit;
}

if ($field === 'recibo') {
$prosegur->update(['recibo' => strtoupper($value)]);
} else {
$prosegur->update(['importe' => (float) $value]);
}

echo json_encode(['success' => true]);
exit;
}

public function editTarjeta()
{
header('Content-Type: application/json; charset=utf-8');
if (!ModuloDptoOperativoService::validaPermiso('corporativo', 'editar')) {
echo json_encode(['success' => false, 'message' => 'Permiso denegado']);
exit;
}
$input = json_decode(file_get_contents('php://input'), true);

$id = (int) ($input['id'] ?? 0);
$value = (float) ($input['value'] ?? 0);

$tarjeta = TarjetasCB::find($id);
if (!$tarjeta) {
echo json_encode(['success' => false, 'message' => 'No encontrado']);
exit;
}

$tarjeta->update(['baucher' => $value]);
echo json_encode(['success' => true]);
exit;
}

public function editControlgas()
{
header('Content-Type: application/json; charset=utf-8');
if (!ModuloDptoOperativoService::validaPermiso('corporativo', 'editar')) {
echo json_encode(['success' => false, 'message' => 'Permiso denegado']);
exit;
}
$input = json_decode(file_get_contents('php://input'), true);

$id = (int) ($input['id'] ?? 0);
$field = $input['field'] ?? '';
$value = (float) ($input['value'] ?? 0);

$control = ClientesControlgas::find($id);
if (!$control) {
echo json_encode(['success' => false, 'message' => 'No encontrado']);
exit;
}

$control->update([$field => $value]);
echo json_encode(['success' => true]);
exit;
}

public function editPagoCliente()
{
header('Content-Type: application/json; charset=utf-8');
if (!ModuloDptoOperativoService::validaPermiso('corporativo', 'editar')) {
echo json_encode(['success' => false, 'message' => 'Permiso denegado']);
exit;
}
$input = json_decode(file_get_contents('php://input'), true);

$id = (int) ($input['id'] ?? 0);
$field = $input['field'] ?? '';
$value = $input['value'] ?? '';

$pago = PagoCliente::find($id);
if (!$pago) {
echo json_encode(['success' => false, 'message' => 'No encontrado']);
exit;
}

if ($field === 'nota') {
$pago->update(['nota' => strtoupper($value)]);
} else {
$pago->update(['importe' => (float) $value]);
}

echo json_encode(['success' => true]);
exit;
}

public function editAceite()
{
header('Content-Type: application/json; charset=utf-8');
if (!ModuloDptoOperativoService::validaPermiso('corporativo', 'editar')) {
echo json_encode(['success' => false, 'message' => 'Permiso denegado']);
exit;
}
$input = json_decode(file_get_contents('php://input'), true);

$id = (int) ($input['id'] ?? 0);
$field = $input['field'] ?? '';
$value = $input['value'] ?? '';

$aceite = AceiteLubricante::find($id);
if (!$aceite) {
echo json_encode(['success' => false, 'message' => 'No encontrado']);
exit;
}

$aceite->update([$field => (float) $value]);

$idReporte = $aceite->idreporte_dia;

$totales = VentasService::getTotalesAceites($idReporte);

VentasDiaOtros::where('idreporte_dia', $idReporte)
->where('concepto', '4 ACEITES Y LUBRICANTES')
->update(['piezas' => $totales['totalCantidad'], 'importe' => $totales['totalPrecio']]);

$updatedAceites = VentasService::getAceitesLubricantes($idReporte);
$updatedVDOtros = VentasDiaOtros::where('idreporte_dia', $idReporte)
->where('concepto', '4 ACEITES Y LUBRICANTES')->first();

echo json_encode(['success' => true, 'totales' => $totales, 'aceites' => $updatedAceites, 'ventas_dia_otros' => $updatedVDOtros]);
exit;
}

public function updatePiezasAceites($idDia)
{
header('Content-Type: application/json; charset=utf-8');
if (!ModuloDptoOperativoService::validaPermiso('corporativo', 'editar')) {
echo json_encode(['success' => false, 'message' => 'Permiso denegado']);
exit;
}
$idReporte = (int) $idDia;

$totales = VentasService::getTotalesAceites($idReporte);

VentasDiaOtros::where('idreporte_dia', $idReporte)
->where('concepto', '4 ACEITES Y LUBRICANTES')
->update(['piezas' => $totales['totalCantidad'], 'importe' => $totales['totalPrecio']]);

echo json_encode(['success' => true, 'totales' => $totales]);
exit;
}

public function editObservaciones()
{
header('Content-Type: application/json; charset=utf-8');
if (!ModuloDptoOperativoService::validaPermiso('corporativo', 'editar')) {
echo json_encode(['success' => false, 'message' => 'Permiso denegado']);
exit;
}
$input = json_decode(file_get_contents('php://input'), true);

$idReporte = (int) ($input['id'] ?? 0);
$observaciones = $input['observaciones'] ?? '';

Observacione::updateOrCreate(
['idreporte_dia' => $idReporte],
['observaciones' => $observaciones]
);

echo json_encode(['success' => true]);
exit;
}

public function uploadDocumento($idDia)
{
header('Content-Type: application/json; charset=utf-8');
if (!ModuloDptoOperativoService::validaPermiso('corporativo', 'crear')) {
echo json_encode(['success' => false, 'message' => 'Permiso denegado']);
exit;
}
$idReporte = (int) $idDia;
$nombreDocumento = $_POST['NombreDocumento'] ?? '';

if (empty($nombreDocumento)) {
echo json_encode(['success' => false, 'message' => 'Nombre de documento requerido']);
exit;
}

if (!isset($_FILES['Documento_file']) || $_FILES['Documento_file']['error'] !== UPLOAD_ERR_OK) {
echo json_encode(['success' => false, 'message' => 'Archivo requerido']);
exit;
}

$file = $_FILES['Documento_file'];
$aleatorio = uniqid();
$archivo = basename($file['name']);
$pdfNombre = $aleatorio . '-' . $archivo;
$uploadFolder = __DIR__ . '/../../public/uploads/archivos/' . $pdfNombre;

$dir = dirname($uploadFolder);
if (!is_dir($dir)) {
mkdir($dir, 0755, true);
}

move_uploaded_file($file['tmp_name'], $uploadFolder);

CorteDiaArchivo::create([
'id_reportedia' => $idReporte,
'detalle' => $nombreDocumento,
'documento' => $pdfNombre,
]);

$usuario = Session::get('usuario');
$idUsuario = $usuario['id'] ?? 0;
$nombreUsuario = $usuario['nombre'] ?? 'Desconocido';

register_shutdown_function(function () use ($idReporte, $idUsuario, $nombreUsuario, $nombreDocumento) {
VentasService::notificarSubirDocumento($idReporte, $idUsuario, $nombreUsuario, $nombreDocumento);
});

echo json_encode(['success' => true]);
exit;
}

public function deleteDocumento()
{
header('Content-Type: application/json; charset=utf-8');
if (!ModuloDptoOperativoService::validaPermiso('corporativo', 'eliminar')) {
echo json_encode(['success' => false, 'message' => 'Permiso denegado']);
exit;
}
$input = json_decode(file_get_contents('php://input'), true);
$id = (int) ($input['id'] ?? 0);

$doc = CorteDiaArchivo::find($id);
if ($doc) {
$idReporte = $doc->id_reportedia;
$nombreDoc = $doc->nombre_documento ?? '';
$ruta = realpath(__DIR__ . '/../../public/uploads/archivos/' . $doc->documento);
if ($ruta && file_exists($ruta)) unlink($ruta);
$doc->delete();

$usuario = Session::get('usuario');
$idUsuario = $usuario['id'] ?? 0;
$nombreUsuario = $usuario['nombre'] ?? 'Desconocido';

register_shutdown_function(function () use ($idReporte, $idUsuario, $nombreUsuario) {
VentasService::notificarEliminarDocumento($idReporte, $idUsuario, $nombreUsuario);
});
}

echo json_encode(['success' => true, 'message' => 'Documento eliminado exitosamente']);
exit;
}

public function firmar()
{
header('Content-Type: application/json; charset=utf-8');
$permisos = VentasService::getPermisos();
if ($permisos['multiestacion']) {
echo json_encode(['success' => false, 'message' => 'Permiso denegado']);
exit;
}
$input = json_decode(file_get_contents('php://input'), true);

$idReporte = (int) ($input['id'] ?? 0);
$base64 = $input['base64'] ?? '';
$usuario = Session::get('usuario');
$idUsuario = $usuario['id'] ?? 0;

if (!$idReporte || empty($base64)) {
echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
exit;
}

$fileName = VentasService::agregarFirma($idReporte, $idUsuario, $base64);
if ($fileName) {
$idEstacion = $this->estacionId();
VentasService::finalizarVentas($idReporte, $idUsuario, $idEstacion);

$nombreUsuario = $usuario['nombre'] ?? 'Desconocido';

register_shutdown_function(function () use ($idReporte, $idEstacion, $idUsuario, $nombreUsuario) {
VentasService::notificarFinalizacion($idReporte, $idEstacion, $idUsuario, $nombreUsuario);
});

echo json_encode(['success' => true]);
} else {
echo json_encode(['success' => false, 'message' => 'Error al guardar la firma']);
}
exit;
}

public function crearToken()
{
header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true);
$idReporte = (int) ($input['id'] ?? 0);
$method = $input['method'] ?? '';
$usuario = Session::get('usuario');
$idUsuario = $usuario['id'] ?? 0;

if (!in_array($idUsuario, [19, 2])) {
echo json_encode(['success' => false, 'message' => 'Permiso denegado']);
exit;
}

if (!$idReporte || !in_array($method, ['telegram', 'email'])) {
echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
exit;
}

$token = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

CorteDiaToken::where('id_reportedia', $idReporte)
->where('id_usuario', $idUsuario)
->delete();

CorteDiaToken::create([
'id_reportedia' => $idReporte,
'id_usuario' => $idUsuario,
'token' => $token,
]);

$tipo = $idUsuario == 19 ? 'Superviso' : 'VoBo';

if ($method === 'telegram') {
try {
$telegram = new TelegramService();
$idEstacion = $this->estacionId();
$estacion = Estacion::find($idEstacion);
$nombreES = $estacion ? $estacion->nombre : 'Desconocida';

$dateStr = VentasService::getFecha($idReporte);
$fechaFormat = $dateStr ? formatearFecha($dateStr) : '';

$detalle = $tipo === 'Superviso' ? 'la "Supervisión"' : 'el "VOBO"';
$mensaje = "📲 Usa el token <b>{$token}</b> para firmar {$detalle} en el Corte Diario del día: {$fechaFormat}." . PHP_EOL . PHP_EOL . "⛽ Estación: {$nombreES}.";

$sent = $telegram->sendToken($idUsuario, $mensaje);
if ($sent) {
echo json_encode(['success' => true, 'message' => 'Token enviado por Telegram']);
} else {
echo json_encode(['success' => false, 'message' => 'No se pudo enviar el token. Verifica que tu chat de Telegram esté vinculado.']);
}
exit;
} catch (\Throwable $e) {
echo json_encode(['success' => false, 'message' => 'Error al enviar por Telegram: ' . $e->getMessage()]);
exit;
}
} elseif ($method === 'email') {
try {
$user = Auth::user();
$email = $user ? $user->email : '';

if (empty($email)) {
echo json_encode(['success' => false, 'message' => 'El usuario no tiene correo electrónico registrado.']);
exit;
}

$emailService = new EmailService();
$sent = $emailService->sendToken($email, $token);
if ($sent) {
echo json_encode(['success' => true, 'message' => 'Token enviado por correo electrónico']);
} else {
$errorDetail = $emailService->getLastError();
echo json_encode(['success' => false, 'message' => 'Error al enviar el correo: ' . $errorDetail]);
}
exit;
} catch (\Throwable $e) {
echo json_encode(['success' => false, 'message' => 'Error al enviar por correo: ' . $e->getMessage()]);
exit;
}
}

echo json_encode(['success' => true]);
exit;
}

public function firmarConToken()
{
header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true);
$idReporte = (int) ($input['id'] ?? 0);
$tokenIngresado = $input['token'] ?? '';
$usuario = Session::get('usuario');
$idUsuario = $usuario['id'] ?? 0;

if (!in_array($idUsuario, [19, 2])) {
echo json_encode(['success' => false, 'message' => 'Permiso denegado']);
exit;
}

if (!$idReporte || empty($tokenIngresado)) {
echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
exit;
}

$detalle = '';
if ($idUsuario == 19) $detalle = 'Superviso';
elseif ($idUsuario == 2) $detalle = 'VoBo';

$tokenRecord = CorteDiaToken::where('id_reportedia', $idReporte)
->where('id_usuario', $idUsuario)
->where('token', $tokenIngresado)
->first();

if (!$tokenRecord) {
echo json_encode(['success' => false, 'message' => 'Token inválido']);
exit;
}

$tokenRecord->delete();

$firmaUniq = bin2hex(random_bytes(32)) . '.' . uniqid();

CorteDiaFirmas::create([
'id_reportedia' => $idReporte,
'id_usuario' => $idUsuario,
'firma' => $firmaUniq,
'detalle' => $detalle,
]);

echo json_encode(['success' => true]);
exit;
}

public function downloadPdf($idYear, $idMes, $idDia)
{
$idReporte = (int) $idDia;
$ventas = VentasService::getVentasDia($idReporte);
$aceites = VentasService::getAceitesLubricantes($idReporte);
$prosegur = VentasService::getProsegur($idReporte);
$tarjetas = VentasService::getTarjetasCB($idReporte);
$controlgas = VentasService::getClientesControlgas($idReporte);
$pagoClientes = VentasService::getPagoClientes($idReporte);
$ventasOtros = VentasService::getVentasDiaOtros($idReporte);
$observaciones = VentasService::getObservaciones($idReporte);
$firmas = VentasService::getFirmas($idReporte);
$totales1234 = VentasService::getTotales1234($idReporte);
$totalesVentas = VentasService::getTotalesVentas($idReporte);
$totalesAceites = VentasService::getTotalesAceites($idReporte);
$totalPagoClientes = VentasService::getTotalPagoClientes($idReporte);
$pagoTotal = VentasService::getPagoTotal($idReporte);
$fecha = VentasService::getFecha($idReporte);

$firmasElaboro = VentasService::getFirma($idReporte, 'Elaboró');
$firmasSuperviso = VentasService::getFirma($idReporte, 'Superviso');
$firmasVoBo = VentasService::getFirma($idReporte, 'VoBo');

$h = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$n = function ($v) { return number_format((float) $v, 2); };
$m = function ($v) { return '$ ' . number_format((float) $v, 2); };

$fechaFormat = formatearFecha($fecha);
$h3 = '<h3 style="text-align:center;margin:0 0 0.5rem 0;font-size:1rem;">' . $h($fechaFormat) . '</h3>';

$html = '<html><head><meta charset="UTF-8"><title>CORTE DE VENTAS</title>';
$html .= '<style>
@page {margin: 0.5cm 0.5cm;}
* { box-sizing:border-box; }
body { margin:0; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif; font-size:.52rem; font-weight:400; line-height:1; color:#212529; text-align:left; background-color:#fff; }
table { width:100%; border-collapse:collapse; }
.table { width:100%; max-width:100%; margin-bottom:1rem; background-color:transparent; }
.table th, .table td { padding:0.75rem; vertical-align:top; border-top:1px solid #dee2e6; }
.table thead th { vertical-align:bottom; border-bottom:2px solid #dee2e6; }
.table-sm th, .table-sm td { padding:0.3rem; }
.table-bordered { border:1px solid #dee2e6; }
.table-bordered th, .table-bordered td { border:1px solid #dee2e6; }
.table-bordered thead th { border-bottom-width:2px; }
.bg-light { background-color:#f8f9fa!important; }
.text-center { text-align:center!important; }
.text-right { text-align:right!important; }
.align-middle { vertical-align:middle!important; }
.p-1 { padding:0.25rem!important; }
.p-2 { padding:0.5rem!important; }
.pb-0 { padding-bottom:0!important; }
.mb-0 { margin-bottom:0!important; }
.mt-2 { margin-top:0.5rem!important; }
.border-0 { border:0!important; }
.border { border:1px solid #dee2e6!important; }
th { text-align:inherit; }
</style></head><body>';

$html .= $h3;

$html .= '<table class="table table-sm border-0"><tbody><tr>';

$html .= '<td>';

$html .= '<div>';
$html .= '<div class="bg-light p-1 text-center"><strong>CONCENTRADO DE VENTAS</strong></div>';
$html .= '<table class="table table-sm table-bordered pb-0 mb-0"><thead><tr>';
$html .= '<th class="text-center align-middle">PRODUCTO</th>';
$html .= '<th class="text-center align-middle">LITROS</th>';
$html .= '<th class="text-center align-middle">JARRAS</th>';
$html .= '<th class="text-center align-middle">TOTAL LITROS</th>';
$html .= '<th class="text-center align-middle">PRECIO POR LITRO</th>';
$html .= '<th class="text-center align-middle">IMPORTE TOTAL</th>';
$html .= '</tr></thead><tbody>';
$subTLitros = 0; $subJarras = 0; $subTotalLitros = 0; $subImporteTotal = 0;
foreach ($ventas as $v) {
$litros = (float) $v->litros;
$jarras = (float) $v->jarras;
$precio = (float) $v->precio_litro;
$totalLitros = $litros - $jarras;
$importe = $totalLitros * $precio;
$subTLitros += $litros; $subJarras += $jarras;
$subTotalLitros += $totalLitros; $subImporteTotal += $importe;
$html .= '<tr>';
$html .= '<td class="p-1 align-middle">' . $h($v->producto) . '</td>';
$html .= '<td class="p-1 align-middle text-right">' . $n($litros) . '</td>';
$html .= '<td class="p-1 align-middle text-right">' . $n($jarras) . '</td>';
$html .= '<td class="p-1 align-middle text-right"><strong>' . $n($totalLitros) . '</strong></td>';
$html .= '<td class="p-1 align-middle text-right">' . $m($precio) . '</td>';
$html .= '<td class="align-middle text-right"><strong>' . $m($importe) . '</strong></td>';
$html .= '</tr>';
}
$html .= '<tr class="bg-light">';
$html .= '<td>A SUB-TOTAL (1+2+3)</td>';
$html .= '<td class="align-middle text-right"><strong>' . $n($subTLitros) . '</strong></td>';
$html .= '<td class="align-middle text-right"><strong>' . $n($subJarras) . '</strong></td>';
$html .= '<td class="align-middle text-right"><strong>' . $n($subTotalLitros) . '</strong></td>';
$html .= '<td></td>';
$html .= '<td class="align-middle text-right"><strong>' . $m($subImporteTotal) . '</strong></td>';
$html .= '</tr>';

$sumImporte = 0;
foreach ($ventasOtros as $o) {
$importe = (float) $o->importe;
$sumImporte += $importe;
$html .= '<tr>';
$html .= '<td>' . $h($o->concepto) . '</td>';
$html .= '<td class="align-middle text-right">' . $h($o->piezas) . '</td>';
$html .= '<td class="align-middle text-right"></td>';
$html .= '<td class="align-middle text-right"></td>';
$html .= '<td class="align-middle text-right"></td>';
$html .= '<td class="align-middle text-right">' . $m($importe) . '</td>';
$html .= '</tr>';
}

$totalNeto = $subImporteTotal + $sumImporte;
$html .= '<tr class="bg-light">';
$html .= '<td>B TOTAL (A+4+5+6)</td>';
$html .= '<td class="align-middle text-right"></td>';
$html .= '<td class="align-middle text-right"></td>';
$html .= '<td class="align-middle text-right"></td>';
$html .= '<td class="bg-light"></td>';
$html .= '<td class="align-middle text-right"><strong>' . $m($totalNeto) . '</strong></td>';
$html .= '</tr>';
$html .= '</tbody></table>';
$html .= '</div>';

$html .= '<div class="mt-2">';
$html .= '<div class="bg-light p-1 text-center"><strong>RELACION DE VENTA DE ACEITES Y LUBRICANTES</strong></div>';
$html .= '<table class="table table-sm table-bordered pb-0 mb-0" style="font-size:.8em;"><thead><tr>';
$html .= '<th colspan="2" class="align-middle text-center">CONCEPTO</th>';
$html .= '<th class="align-middle text-center">CANTIDAD</th>';
$html .= '<th class="align-middle text-center">PRECIO UNITARIO</th>';
$html .= '<th class="align-middle text-center">IMPORTE</th>';
$html .= '</tr></thead><tbody>';
$totalCantidad = 0; $totalPrecio = 0;
foreach ($aceites as $a) {
$cantidad = (float) $a->cantidad;
$precioUnitario = (float) $a->precio_unitario;
$importe = $cantidad * $precioUnitario;
$totalCantidad += $cantidad;
$totalPrecio += $importe;
$html .= '<tr>';
$html .= '<td class="align-middle">' . $h($a->id_aceite) . '</td>';
$html .= '<td class="align-middle">' . $h($a->concepto) . '</td>';
$html .= '<td class="p-0 align-middle text-center">' . ($cantidad > 0 ? $cantidad : '') . '</td>';
$html .= '<td class="align-middle text-right">' . ($precioUnitario > 0 ? $m($precioUnitario) : '') . '</td>';
$html .= '<td class="align-middle text-right">' . $m($importe) . '</td>';
$html .= '</tr>';
}
$html .= '<tr>';
$html .= '<td class="bg-light text-center"></td>';
$html .= '<td class="bg-light text-center"></td>';
$html .= '<td class="bg-light align-middle text-center"><strong>' . $totalCantidad . '</strong></td>';
$html .= '<td class="bg-light align-middle text-right"></td>';
$html .= '<td class="bg-light align-middle text-right"><strong>' . $m($totalPrecio) . '</strong></td>';
$html .= '</tr>';
$html .= '</tbody></table>';
$html .= '</div>';

$html .= '</td>';

$html .= '<td>';

$html .= '<div>';
$html .= '<div class="bg-light p-1 text-center"><strong>PROSEGUR</strong></div>';
$html .= '<table class="table table-sm table-bordered pb-0 mb-0"><thead><tr>';
$html .= '<th class="text-center">DENOMINACION</th>';
$html .= '<th class="text-center">RECIBO</th>';
$html .= '<th class="text-center">IMPORTE</th>';
$html .= '</tr></thead><tbody>';
$total1 = 0;
foreach ($prosegur as $p) {
$imp = (float) $p->importe;
$total1 += $imp;
$html .= '<tr>';
$html .= '<td class="align-middle">' . $h($p->denominacion) . '</td>';
$html .= '<td class="p-0 align-middle">' . $h($p->recibo) . '</td>';
$html .= '<td class="p-0 align-middle text-right">' . $m($imp) . '</td>';
$html .= '</tr>';
}
$html .= '<tr>';
$html .= '<td class="bg-light text-center" colspan="2">TOTAL 1</td>';
$html .= '<td class="bg-light align-middle text-right"><strong>' . $m($total1) . '</strong></td>';
$html .= '</tr>';
$html .= '</tbody></table>';
$html .= '</div>';

$html .= '<div class="mt-2">';
$html .= '<div class="bg-light p-1 text-center"><strong>MONEDEROS Y BANCOS</strong></div>';
$html .= '<table class="table table-sm table-bordered pb-0 mb-0"><thead><tr>';
$html .= '<th class="text-center" colspan="2">CONCEPTO / BANCO</th>';
$html .= '<th class="text-center">IMPORTE</th>';
$html .= '</tr></thead><tbody>';
$total2 = 0;
foreach ($tarjetas as $t) {
$baucher = (float) $t->baucher;
$total2 += $baucher;
$html .= '<tr>';
$html .= '<td class="align-middle"><b>' . $h($t->num) . '</b></td>';
$html .= '<td class="align-middle">' . $h($t->concepto) . '</td>';
$html .= '<td class="p-1 align-middle text-right">' . $m($baucher) . '</td>';
$html .= '</tr>';
}
$html .= '<tr>';
$html .= '<td class="bg-light text-center" colspan="2">TOTAL 2</td>';
$html .= '<td class="bg-light align-middle text-right"><strong>' . $m($total2) . '</strong></td>';
$html .= '</tr>';
$html .= '</tbody></table>';
$html .= '</div>';

$html .= '<div class="mt-2">';
$html .= '<div class="bg-light p-1 text-center"><strong>CLIENTES (ATIO)</strong></div>';
$html .= '<table class="table table-sm table-bordered pb-0 mb-0"><thead><tr>';
$html .= '<th class="text-center">CONCEPTO</th>';
$html .= '<th class="text-center">PAGOS</th>';
$html .= '<th class="text-center">CONSUMOS</th>';
$html .= '</tr></thead><tbody>';
$totalPagos = 0; $total3 = 0;
foreach ($controlgas as $c) {
$pago = (float) $c->pago;
$consumo = (float) $c->consumo;
$totalPagos += $pago;
$total3 += $consumo;
$html .= '<tr>';
$html .= '<td class="align-middle">' . $h($c->concepto) . '</td>';
$html .= '<td class="p-1 align-middle text-right">' . $m($pago) . '</td>';
$html .= '<td class="p-1 align-middle text-right">' . $m($consumo) . '</td>';
$html .= '</tr>';
}
$html .= '<tr>';
$html .= '<td class="bg-light text-center">TOTAL 3</td>';
$html .= '<td class="bg-light align-middle text-right"><strong>' . $m($totalPagos) . '</strong></td>';
$html .= '<td class="bg-light align-middle text-right"><strong>' . $m($total3) . '</strong></td>';
$html .= '</tr>';
$html .= '</tbody></table>';
$html .= '</div>';

$cTotal = $total1 + $total2 + $total3;
$html .= '<table class="table table-sm table-bordered pb-0 mb-0 mt-2"><tr>';
$html .= '<td>C TOTAL (1+2+3)</td>';
$html .= '<td class="bg-light align-middle text-right"><strong>' . $m($cTotal) . '</strong></td>';
$html .= '</tr></table>';

$html .= '<table class="table table-sm table-bordered pb-0 mb-0 mt-2"><tr>';
$html .= '<td><strong>DIFERENCIA (B-C)</strong></td>';
$html .= '<td class="bg-light align-middle text-right"><strong>' . $m($cTotal - $totalNeto) . '</strong></td>';
$html .= '</tr></table>';

$html .= '<div class="mt-2">';
$html .= '<div class="bg-light p-1 text-center"><strong>PAGO DE CLIENTES</strong></div>';
$html .= '<table class="table table-sm table-bordered pb-0 mb-0"><thead><tr>';
$html .= '<th class="text-center">CONCEPTO</th>';
$html .= '<th class="text-center">IMPORTE</th>';
$html .= '<th class="text-center">NOTA</th>';
$html .= '</tr></thead><tbody>';
$total4 = 0;
foreach ($pagoClientes as $pc) {
$imp = (float) $pc->importe;
$total4 += $imp;
$html .= '<tr>';
$html .= '<td class="align-middle">' . $h($pc->concepto) . '</td>';
$html .= '<td class="p-1 align-middle text-right">' . $m($imp) . '</td>';
$html .= '<td class="p-1 align-middle">' . $h($pc->nota) . '</td>';
$html .= '</tr>';
}
$html .= '<tr>';
$html .= '<td class="bg-light text-center">TOTAL 4</td>';
$html .= '<td class="bg-light align-middle text-right"><strong>' . $m($total4) . '</strong></td>';
$html .= '<td class="bg-light align-middle text-right"></td>';
$html .= '</tr>';
$html .= '</tbody></table>';
$html .= '</div>';

$difPC = $pagoTotal - $total4;
$html .= '<table class="table table-sm table-bordered pb-0 mb-0 mt-2"><tr>';
$html .= '<td>DIF PAGO DE CLIENTES</td>';
$html .= '<td class="bg-light align-middle text-right"><strong>' . $m($difPC) . '</strong></td>';
$html .= '<td>(4-5)</td>';
$html .= '</tr></table>';

$html .= '<div class="border mt-2">';
$html .= '<div class="bg-light p-1"><strong>OBSERVACIONES:</strong></div>';
$html .= '<div class="p-2">' . nl2br($h($observaciones ?? '')) . '</div>';
$html .= '</div>';

$html .= '</td>';

$html .= '</tr></tbody></table>';

$html .= '<table class="table table-bordered table-sm pb-0 mb-0">';
$html .= '<tr>';
$html .= '<td class="text-center" width="200px">';
$html .= '<div class="text-center">ELABORÓ / SUPERVISO</div>';
$html .= $this->renderFirma($firmasElaboro, 'Elaboró');
$html .= '</td>';
$html .= '<td class="text-center" width="200px">';
$html .= '<div class="text-center">ELABORÓ / SUPERVISO</div>';
$html .= $this->renderFirma($firmasSuperviso, 'Superviso');
$html .= '</td>';
$html .= '<td class="text-center" width="200px">';
$html .= '<div class="text-center">Vo. Bo.</div>';
$html .= $this->renderFirma($firmasVoBo, 'VoBo');
$html .= '</td>';
$html .= '</tr>';
$html .= '</table>';

$html .= '</body></html>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("Corte " . formatearFecha($fecha) . ".pdf", ['Attachment' => true]);
exit;
}

private function renderFirma($firma, string $detalle): string
{
if (!$firma) return '<div class="text-muted small mt-1"><strong>¡Falta la Firma!</strong></div>';

$h = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$rutaBase = $_SERVER['DOCUMENT_ROOT'] . '/public/assets/img/firmas/';
$rutaArchivo = $rutaBase . $firma->firma;

if ($detalle === 'Elaboró') {
if (file_exists($rutaArchivo)) {
$imgData = file_get_contents($rutaArchivo);
$type = pathinfo($rutaArchivo, PATHINFO_EXTENSION);
$base64 = 'data:image/' . $type . ';base64,' . base64_encode($imgData);
$html = '<div class="text-center mt-1">';
$html .= '<img src="' . $base64 . '" class="firma-img" style="width:140px;height:60px;">';
$html .= '<div class="text-center mt-1 border-top pt-1"><strong>' . $h($firma->nombre_usuario) . '</strong></div>';
$html .= '</div>';
return $html;
}
}

if ($detalle === 'Superviso' || $detalle === 'VoBo') {
$fechaStr = $firma->fecha instanceof \DateTimeInterface
? $firma->fecha->format('Y-m-d H:i:s')
: (string) $firma->fecha;
$parts = explode(' ', $fechaStr);
$dateFormatted = formatearFecha($parts[0] ?? '');
$timeFormatted = date("g:i a", strtotime($parts[1] ?? ''));
$html = '<div class="text-center mt-1">';
$html .= '<div class="border-bottom text-center p-2 small" style="font-size:7pt;">';
$html .= 'El formato se firmó por un medio electrónico.<br>';
$html .= '<b>Fecha: ' . $dateFormatted . ', ' . $timeFormatted . '</b>';
$html .= '</div>';
$html .= '<div class="mt-1 text-center pt-2"><strong>' . $h($firma->nombre_usuario) . '</strong></div>';
$html .= '</div>';
return $html;
}

return '';
}
}
