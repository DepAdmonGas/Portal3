<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Session;
use App\Core\View;
use App\Services\FormatosService;
use App\Services\ModuleStationService;
use Dompdf\Dompdf;
use Dompdf\Options;

class FormatosController extends BaseController
{
public function index()
{
$permisos = FormatosService::getPermisos();
$esMultiestacion = $permisos['multiestacion'];
$idEstacion = $esMultiestacion ? 0 : $permisos['id_estacion'];

$sessionUsuario = Session::get('usuario');
if (!$esMultiestacion && !empty($sessionUsuario['id_estacion']) && $sessionUsuario['id_estacion'] == 2) {
$idEstacion = 0;
}

$title = 'Formatos';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
Breadcrumb::add($title, '');

if (!$this->guardModuleAccess(FormatosService::MODULE_KEY, $title, 'departamento-operativo')) {
return;
}

View::render('departamento-operativo/2-recursos-humanos/formatos/index', [
'title'            => $title,
'idEstacion'       => $idEstacion,
'multiestacion'    => $esMultiestacion,
'moduleStationKey' => FormatosService::MODULE_KEY,
'pendientesData'   => FormatosService::getPendingCountsFlat(),
'puedeCrear'       => $permisos['puedeCrear'],
'puedeAcceso'      => $permisos['puedeAcceso'],
'puedeEditar'      => $permisos['puedeEditar'],
'puedeEliminar'    => $permisos['puedeEliminar'],
'puedeDescargar'   => $permisos['puedeDescargar'],
'puedeFirmar'      => $permisos['puedeFirmar'],
'idUsuario'        => $permisos['id_usuario'],
'nombrePuesto'     => $permisos['nombre_puesto'],
'help'             => false,
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/core/module-station-selector.js?v=' . time(),
'/assets/js/departamento-operativo/2-recursos-humanos/formatos.datatable.init.js?v=' . time(),
'/assets/js/departamento-operativo/2-recursos-humanos/formatos.actions.init.js?v=' . time(),
],
'links' => [
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
],
], 'departamento-operativo');
}

public function getData()
{
JsonResponse::custom(['success' => true, 'data' => FormatosService::getListaFormatos()]);
}

public function getPendingCountsEndpoint()
{
$flat = FormatosService::getPendingCountsFlat();
$flat['contexto'] = FormatosService::getPendingCountsActual();
JsonResponse::custom(array_merge(
['success' => true],
$flat
));
}

public function getDetalle()
{
$id = (int)($_GET['id'] ?? 0);
$formato = (int)($_GET['formato'] ?? 0);

$detalle = FormatosService::getDetalleFormato($formato, $id);
if (!$detalle) {
JsonResponse::error('Formato no encontrado', 404);
}

JsonResponse::custom([
'success' => true,
'detalle' => $detalle,
'firmas'  => FormatosService::getFirmas($id),
]);
}

public function getComentarios()
{
$id = (int)($_GET['id'] ?? 0);
JsonResponse::custom([
'success'    => true,
'comentarios' => FormatosService::getComentarios($id),
]);
}

public function storeComentario()
{
$id = (int)($_POST['id'] ?? 0);
$comentario = (string)($_POST['comentario'] ?? '');
$idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

$result = FormatosService::storeComentario($id, $idUsuario, $comentario);
JsonResponse::custom($result, $result['success'] ? 200 : 400);
}

public function destroy()
{
$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? ($_POST['id'] ?? 0));
$idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

$permisos = FormatosService::getPermisos();
if (!$permisos['puedeEliminar']) {
JsonResponse::forbidden('No tienes permiso para eliminar formatos');
}

$result = FormatosService::eliminarFormato($id, $idUsuario);
JsonResponse::custom($result, $result['success'] ? 200 : 400);
}

public function crear($formato, $idLocalidad = 0)
{
$permisos = FormatosService::getPermisos();
if (!$permisos['puedeAcceso']) {
View::render('errors/403', [], 'departamento-operativo');
return;
}

$ctx = ModuleStationService::getContext(FormatosService::MODULE_KEY);
$idLocalidad = (int)$idLocalidad ?: ($ctx['id_estacion'] ?? $ctx['id_depto'] ?? 0);
if ($idLocalidad <= 0) {
header('Location: /departamento-operativo/recursos-humanos/formatos');
exit;
}

// Reutiliza el formato pendiente del mismo tipo y localidad, o crea uno
// nuevo (status 0). Así no se duplican registros en proceso y la
// información no depende del estado del navegador.
$id = FormatosService::obtenerOCrearPendiente((int)$formato, $idLocalidad);

$detalleFormato = FormatosService::getDetalleFormato(0, $id);
if (!$detalleFormato) {
http_response_code(404);
echo 'Formato no encontrado';
return;
}

$datos = FormatosService::getDatosFormulario((int)$formato, $idLocalidad);
$datos['detalle'] = $detalleFormato;
$formularioEdicion = FormatosService::getFormularioEdicion((int)$formato, $id);
$datos['valores'] = $formularioEdicion['valores'];
$datos['detalle_rows'] = $formularioEdicion['detalle_rows'];
$datos = FormatosService::prepararDatosVista($datos);

$title = 'Formato (' . $datos['formato_nombre'] . ')';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
Breadcrumb::add('Formatos', '/departamento-operativo/recursos-humanos/formatos');
Breadcrumb::add($title, '');

View::render('departamento-operativo/2-recursos-humanos/formatos/formato', [
'title'            => $title,
'datos'            => $datos,
'esEdicion'        => true,
'permisos'         => $permisos,
'moduleStationKey' => FormatosService::MODULE_KEY,
'ocultarSelectorEstacion' => true,
'help'             => false,
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/select2/dist/js/select2.full.min.js?v=' . time(),
'/assets/libs/signature_pad/docs/js/signature_pad.umd.min.js',
'/assets/js/departamento-operativo/2-recursos-humanos/formatos.form.init.js?v=' . time(),
],
'links' => [
'/assets/libs/select2/dist/css/select2.min.css?v=' . time(),
'/assets/css/select2-modal.css?v=' . time(),
'/assets/css/formatos-formato.css?v=' . time(),
],
], 'departamento-operativo');
}

public function agregarFila()
{
$permisos = FormatosService::getPermisos();
if (!$permisos['puedeAcceso']) {
JsonResponse::forbidden('No tienes permiso para editar formatos');
}

$formato = (int)($_POST['formato'] ?? 0);
$id = (int)($_POST['id'] ?? 0);

if ($formato < 1 || $formato > 5) {
JsonResponse::error('Formato no válido');
}

$result = FormatosService::agregarFila($formato, $id, $_POST, $_FILES);
JsonResponse::custom($result, $result['success'] ? 200 : 400);
}

public function eliminarFila()
{
$permisos = FormatosService::getPermisos();

if (!$permisos['puedeAcceso']) {
JsonResponse::forbidden('No tienes permiso para editar formatos');
}

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
$data = $_POST;
}

$formato = (int)($data['formato'] ?? 0);
$id = (int)($data['id'] ?? 0);
$filaId = (int)($data['fila_id'] ?? 0);

if ($formato < 1 || $formato > 5 || $filaId <= 0) {
JsonResponse::error('Datos no válidos');
}

$result = FormatosService::eliminarFila($formato, $id, $filaId);

JsonResponse::custom(
$result,
$result['success'] ? 200 : 400
);
}

public function editar($id)
{
$permisos = FormatosService::getPermisos();
if (!$permisos['puedeAcceso']) {
View::render('errors/403', [], 'departamento-operativo');
return;
}

$id = (int)$id;

$formato = FormatosService::getDetalleFormato(0, $id);
if (!$formato) {
http_response_code(404);
echo 'Formato no encontrado';
return;
}

if ((int)$formato['status'] !== 0) {
View::render('errors/403', [], 'departamento-operativo');
return;
}

if (!FormatosService::puedeGestionarLocalidad((int)$formato['id_localidad'])) {
View::render('errors/403', [], 'departamento-operativo');
return;
}

$datos = FormatosService::getDatosFormulario($formato['formato'], $formato['id_localidad']);
$datos['detalle'] = $formato;
$formularioEdicion = FormatosService::getFormularioEdicion($formato['formato'], $id);
$datos['valores'] = $formularioEdicion['valores'];
$datos['detalle_rows'] = $formularioEdicion['detalle_rows'];
$datos = FormatosService::prepararDatosVista($datos);

$title = $formato['formato_nombre'] . ' (Folio ' . $id . ')';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
Breadcrumb::add('Formatos', '/departamento-operativo/recursos-humanos/formatos');
Breadcrumb::add($title, '');

View::render('departamento-operativo/2-recursos-humanos/formatos/formato', [
'title'            => $title,
'datos'            => $datos,
'esEdicion'        => true,
'permisos'         => $permisos,
'moduleStationKey' => FormatosService::MODULE_KEY,
'ocultarSelectorEstacion' => true,
'help'             => false,
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/select2/dist/js/select2.full.min.js?v=' . time(),
'/assets/libs/signature_pad/docs/js/signature_pad.umd.min.js',
'/assets/js/departamento-operativo/2-recursos-humanos/formatos.form.init.js?v=' . time(),
],
'links' => [
'/assets/libs/select2/dist/css/select2.min.css?v=' . time(),
'/assets/css/select2-modal.css?v=' . time(),
'/assets/css/formatos-formato.css?v=' . time(),
],
], 'departamento-operativo');
}

public function update()
{
$formato = (int)($_POST['formato'] ?? 0);
$id = (int)($_POST['id'] ?? 0);

$permisos = FormatosService::getPermisos();
if (!$permisos['puedeAcceso']) {
JsonResponse::forbidden('No tienes permiso para editar formatos');
}

$result = FormatosService::editarFormato($formato, $id, $_POST, $_FILES);
JsonResponse::custom($result, $result['success'] ? 200 : 400);
}

public function firmarPage($id)
{
$id = (int)$id;
$detalle = FormatosService::getDetalleFormato(0, $id);
if (!$detalle) {
http_response_code(404);
echo 'Formato no encontrado';
return;
}

$permisos = FormatosService::getPermisos();
if (!$permisos['puedeFirmar']) {
View::render('errors/403', [], 'departamento-operativo');
return;
}

$firmas = FormatosService::getFirmas($id);
$firmaB = 0;
$firmaC = 0;
$firmaD = 0;
foreach ($firmas as $f) {
if ($f['tipo_firma'] === 'B') {
$firmaB++;
}
if ($f['tipo_firma'] === 'C') {
$firmaC++;
}
if ($f['tipo_firma'] === 'D') {
$firmaD++;
}
}

$title = 'Firmar ' . $detalle['formato_nombre'] . ' (#00' . $id . ')';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
Breadcrumb::add('Formatos', '/departamento-operativo/recursos-humanos/formatos');
Breadcrumb::add($title, '');

View::render('departamento-operativo/2-recursos-humanos/formatos/firmar', [
'title'            => $title,
'detalle'          => $detalle,
'firmas'           => $firmas,
'firmaB'           => $firmaB,
'firmaC'           => $firmaC,
'firmaD'           => $firmaD,
'permisos'         => $permisos,
'idUsuario'        => $permisos['id_usuario'],
'moduleStationKey' => FormatosService::MODULE_KEY,
'ocultarSelectorEstacion' => true,
'help'             => false,
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/signature_pad/docs/js/signature_pad.umd.min.js',
'/assets/js/departamento-operativo/2-recursos-humanos/formatos.firma.init.js?v=' . time(),
'/assets/js/departamento-operativo/2-recursos-humanos/formatos.archivos.init.js?v=' . time(),
],
], 'departamento-operativo');
}

public function crearToken()
{
$id = (int)($_POST['id'] ?? 0);
$idUsuario = (int)(Session::get('usuario')['id'] ?? 0);
$via = (string)($_POST['via'] ?? 'telegram');

if (!$id) {
JsonResponse::error('ID no válido');
}

$result = FormatosService::crearToken($id, $idUsuario, $via);
JsonResponse::custom($result, $result['success'] ? 200 : 400);
}

public function firmar()
{
$id = (int)($_POST['id'] ?? 0);
$tipoFirma = (string)($_POST['tipo_firma'] ?? '');
$token = (int)($_POST['token'] ?? 0);
$idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

if (!$id || !$tipoFirma || !$token) {
JsonResponse::error('Datos incompletos');
}

$result = FormatosService::firmarFormato($id, $tipoFirma, $token, $idUsuario);
JsonResponse::custom($result, $result['success'] ? 200 : 400);
}

public function firmarImagen()
{
$id = (int)($_POST['id'] ?? 0);
$firmaBase64 = (string)($_POST['firma'] ?? '');
$idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

if (!$id) {
JsonResponse::error('ID no válido');
}

$result = FormatosService::firmarVerificacion($id, $idUsuario, $firmaBase64);
JsonResponse::custom($result, $result['success'] ? 200 : 400);
}

public function getFirmas()
{
$id = (int)($_GET['id'] ?? 0);
JsonResponse::custom([
'success' => true,
'firmas'  => FormatosService::getFirmas($id),
]);
}

public function downloadPdf($id)
{
$id = (int)$id;
$detalle = FormatosService::getDetalleFormato(0, $id);
if (!$detalle) {
http_response_code(404);
echo 'Formato no encontrado';
return;
}

$firmas = FormatosService::getFirmas($id);

if (!empty($detalle['tabla']['headers'])) {
$detalle['tabla']['headers'] = array_values(array_filter(
$detalle['tabla']['headers'],
fn($h) => trim((string)$h) !== 'Archivo'
));
$detalle['tabla']['rows'] = array_map(function ($row) {
return array_values(array_filter($row, function ($cell) {
$v = is_array($cell) ? ($cell['value'] ?? '') : $cell;
return trim((string)$v) !== 'Sí';
}));
}, $detalle['tabla']['rows']);
}

$titulos = [
1 => 'Alta de Personal',
2 => 'Baja de Personal',
3 => 'Falta de Personal',
4 => 'Reestructuración de Personal',
5 => 'Ajuste Salarial',
6 => 'Vacaciones de Personal',
7 => 'Solicitud Prima Vacacional',
];
$titulo = ($titulos[$detalle['formato']] ?? $detalle['formato_nombre']) . ' ' . $detalle['nombre_localidad'];

ob_start();
extract([
'detalle' => $detalle,
'firmas'  => $firmas,
'titulo'  => $titulo,
], EXTR_SKIP);
require __DIR__ . '/../Views/departamento-operativo/2-recursos-humanos/formatos/pdf.php';
$html = ob_get_clean();

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Arial');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$nombreArchivo = $titulo . '.pdf';
$dompdf->stream($nombreArchivo, ['Attachment' => true]);
}
}
