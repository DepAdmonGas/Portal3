<?php
namespace App\Controllers;

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use App\Services\ModuleStationService;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Session;
use App\Core\Request;
use App\Core\JsonResponse;
use App\Services\BiometricosService;
use App\Services\ControlDocumentosPersonalService;
use App\Services\ModuloDptoOperativoService;

class BiometricosController extends BaseController
{
public function index()
{
$permisos = BiometricosService::getPermisos();
$esMultiestacion = $permisos['multiestacion'];
$idEstacion = $esMultiestacion ? 0 : $permisos['id_estacion'];

$sessionUsuario = Session::get('usuario');
if (!$esMultiestacion && !empty($sessionUsuario['id_estacion']) && $sessionUsuario['id_estacion'] == 2) {
$idEstacion = 0;
}

$title = 'Biométricos';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
Breadcrumb::add($title, '');

if (!$this->guardModuleAccess(BiometricosService::MODULE_KEY, $title, 'departamento-operativo')) {
return;
}

View::render('departamento-operativo/2-recursos-humanos/biometricos/index', [
'title'            => $title,
'idEstacion'       => $idEstacion,
'multiestacion'    => $esMultiestacion,
'moduleStationKey' => BiometricosService::MODULE_KEY,
'puedeCrear'       => $permisos['puedeCrear'],
'puedeEditar'      => $permisos['puedeEditar'],
'puedeEliminar'    => $permisos['puedeEliminar'],
'puedeDescargar'   => $permisos['puedeDescargar'],
'nombrePuesto'     => $permisos['nombre_puesto'],
'help'             => false,
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/core/module-station-selector.js?v=' . time(),
'/assets/js/departamento-operativo/2-recursos-humanos/biometricos.datatable.init.js?v=' . time(),
'/assets/js/departamento-operativo/2-recursos-humanos/biometricos.actions.init.js?v=' . time(),
],
'links' => [
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
],
], 'departamento-operativo');
}

public function configuracion()
{
$permisos = BiometricosService::getPermisos();

if (!$permisos['multiestacion']) {
header('Location: /departamento-operativo/recursos-humanos/biometricos');
exit;
}

$title = 'Configuración Biométrico';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
Breadcrumb::add('Biométricos', '/departamento-operativo/recursos-humanos/biometricos');
Breadcrumb::add($title, '');

if (!$this->guardModuleAccess(BiometricosService::MODULE_KEY, $title, 'departamento-operativo')) {
return;
}

View::render('departamento-operativo/2-recursos-humanos/biometricos/configuracion', [
'title' => $title,
'help'  => false,
], 'departamento-operativo');
}

public function configuracionModulo(string $modulo)
{
$permisos = BiometricosService::getPermisos();

if (!$permisos['multiestacion']) {
header('Location: /departamento-operativo/recursos-humanos/biometricos');
exit;
}

$modulos = [
'perfil'                     => 'Perfil de aplicación',
'puestos'                    => 'Puestos',
'retardo-horarios-incidencias' => 'Retardos, Horarios e Incidencias',
];

if (!isset($modulos[$modulo])) {
header('Location: /departamento-operativo/recursos-humanos/biometricos/configuracion');
exit;
}

$title = 'Configuración Biométrico (' . $modulos[$modulo] . ')';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
Breadcrumb::add('Biométricos', '/departamento-operativo/recursos-humanos/biometricos');
Breadcrumb::add('Configuración', '/departamento-operativo/recursos-humanos/biometricos/configuracion');
Breadcrumb::add($modulos[$modulo], '');

if (!$this->guardModuleAccess(BiometricosService::MODULE_KEY, $title, 'departamento-operativo')) {
return;
}

if ($modulo === 'puestos') {
$permisosCrud = ModuloDptoOperativoService::permisosSesion('recursos-humanos');
View::render('departamento-operativo/2-recursos-humanos/biometricos/configuracion-puestos', [
'title'         => $title,
'help'          => false,
'permisos'      => $permisosCrud,
'links'         => [
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
],
'scripts'       => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/departamento-operativo/2-recursos-humanos/biometricos-config-puestos.datatable.init.js?v=' . time(),
'/assets/js/departamento-operativo/2-recursos-humanos/biometricos-config-puestos.actions.init.js?v=' . time(),
],
], 'departamento-operativo');
return;
}

if ($modulo === 'perfil') {

$ctx = ModuleStationService::getContext('biometricos');
$estacionId = $ctx['id_estacion'];

$permisosCrud = ModuloDptoOperativoService::permisosSesion('recursos-humanos');
View::render('departamento-operativo/2-recursos-humanos/biometricos/configuracion-perfil', [
'title'             => $title,
'help'              => false,
'estacionId' => $estacionId,
'permisos'          => $permisosCrud,
'moduleStationKey'  => BiometricosService::MODULE_KEY,
'links'             => [
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
],
'scripts'           => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/core/module-station-selector.js?v=' . time(),
'/assets/js/departamento-operativo/2-recursos-humanos/biometricos-config-perfil.datatable.init.js?v=' . time(),
'/assets/js/departamento-operativo/2-recursos-humanos/biometricos-config-perfil.actions.init.js?v=' . time(),
],
], 'departamento-operativo');
return;
}

if ($modulo === 'retardo-horarios-incidencias') {

$ctx = ModuleStationService::getContext('biometricos');
$estacionId = $ctx['id_estacion'];

$permisosCrud = ModuloDptoOperativoService::permisosSesion('recursos-humanos');
View::render('departamento-operativo/2-recursos-humanos/biometricos/configuracion-retardos', [
'title'             => $title,
'help'              => false,
'estacionId' => $estacionId,
'permisos'          => $permisosCrud,
'moduleStationKey'  => BiometricosService::MODULE_KEY,
'links'             => [
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
],
'scripts'           => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/core/module-station-selector.js?v=' . time(),
'/assets/js/departamento-operativo/2-recursos-humanos/biometricos-config-retardos.datatable.init.js?v=' . time(),
'/assets/js/departamento-operativo/2-recursos-humanos/biometricos-config-retardos.actions.init.js?v=' . time(),
],
], 'departamento-operativo');
return;
}

View::render('departamento-operativo/2-recursos-humanos/biometricos/configuracion-placeholder', [
'title' => $title,
'modulo' => $modulos[$modulo],
'help'  => false,
], 'departamento-operativo');
}

public function datatablePuestos()
{
$data = BiometricosService::datatablePuestos();
JsonResponse::custom(['success' => true, 'data' => $data]);
}

public function createPuesto()
{
$permisos = ModuloDptoOperativoService::permisosSesion('recursos-humanos');
if (empty($permisos['crear'])) {
JsonResponse::custom(['success' => false, 'message' => 'No tienes permisos para crear.']);
}

$nombre = trim((string) Request::input('nombre', ''));
if ($nombre === '') {
JsonResponse::custom(['success' => false, 'message' => 'El nombre del puesto es obligatorio.']);
}

$result = BiometricosService::createPuesto($nombre);
JsonResponse::custom($result);
}

public function updatePuesto()
{
$permisos = ModuloDptoOperativoService::permisosSesion('recursos-humanos');
if (empty($permisos['editar'])) {
JsonResponse::custom(['success' => false, 'message' => 'No tienes permisos para editar.']);
}

$id = (int) Request::input('id', 0);
$nombre = trim((string) Request::input('nombre', ''));
if ($id <= 0) {
JsonResponse::custom(['success' => false, 'message' => 'ID requerido.']);
}
if ($nombre === '') {
JsonResponse::custom(['success' => false, 'message' => 'El nombre del puesto es obligatorio.']);
}

$result = BiometricosService::updatePuesto($id, $nombre);
JsonResponse::custom($result);
}

public function deletePuesto()
{
$permisos = ModuloDptoOperativoService::permisosSesion('recursos-humanos');
if (empty($permisos['eliminar'])) {
JsonResponse::custom(['success' => false, 'message' => 'No tienes permisos para eliminar.']);
}

$id = (int) Request::input('id', 0);
if ($id <= 0) {
JsonResponse::custom(['success' => false, 'message' => 'ID requerido.']);
}

$result = BiometricosService::deletePuesto($id);
JsonResponse::custom($result);
}

public function datatablePerfil()
{
$idEstacion = Request::filled('id_estacion') ? (int) Request::input('id_estacion', 0) : null;
$data = BiometricosService::datatablePerfil($idEstacion);
JsonResponse::custom(['success' => true, 'data' => $data]);
}

public function createPerfil()
{
$permisos = ModuloDptoOperativoService::permisosSesion('recursos-humanos');
if (empty($permisos['crear'])) {
JsonResponse::custom(['success' => false, 'message' => 'No tienes permisos para crear.']);
}

$idEstacion = (int) Request::input('id_estacion', 0);
$usuario = trim((string) Request::input('usuario', ''));
$password = trim((string) Request::input('password', ''));

if ($idEstacion <= 0) {
JsonResponse::custom(['success' => false, 'message' => 'Selecciona una estación.']);
}
if ($usuario === '') {
JsonResponse::custom(['success' => false, 'message' => 'El usuario es obligatorio.']);
}
if ($password === '') {
JsonResponse::custom(['success' => false, 'message' => 'La contraseña es obligatoria.']);
}

$result = BiometricosService::createPerfil($idEstacion, $usuario, $password);
JsonResponse::custom($result);
}

public function updatePerfil()
{
$permisos = ModuloDptoOperativoService::permisosSesion('recursos-humanos');
if (empty($permisos['editar'])) {
JsonResponse::custom(['success' => false, 'message' => 'No tienes permisos para editar.']);
}

$id = (int) Request::input('id', 0);
$usuario = trim((string) Request::input('usuario', ''));
$password = trim((string) Request::input('password', ''));

if ($id <= 0) {
JsonResponse::custom(['success' => false, 'message' => 'ID requerido.']);
}
if ($usuario === '') {
JsonResponse::custom(['success' => false, 'message' => 'El usuario es obligatorio.']);
}

$result = BiometricosService::updatePerfil($id, $usuario, $password);
JsonResponse::custom($result);
}

public function deletePerfil()
{
$permisos = ModuloDptoOperativoService::permisosSesion('recursos-humanos');
if (empty($permisos['eliminar'])) {
JsonResponse::custom(['success' => false, 'message' => 'No tienes permisos para eliminar.']);
}

$id = (int) Request::input('id', 0);
if ($id <= 0) {
JsonResponse::custom(['success' => false, 'message' => 'ID requerido.']);
}

$result = BiometricosService::deletePerfil($id);
JsonResponse::custom($result);
}

// ─── Retardos, Horarios e Incidencias ──────────────────────────────────────

public function getRetardoIncidencia()
{
$idEstacion = (int) Request::input('id_estacion', 0);
if ($idEstacion <= 0) {
JsonResponse::custom(['success' => false, 'message' => 'Selecciona una estación.']);
}

$data = BiometricosService::getRetardoIncidencia($idEstacion);
JsonResponse::custom(['success' => true, 'data' => $data]);
}

public function updateRetardoIncidencia()
{
$permisos = ModuloDptoOperativoService::permisosSesion('recursos-humanos');
if (empty($permisos['editar'])) {
JsonResponse::custom(['success' => false, 'message' => 'No tienes permisos para editar.']);
}

$idEstacion = (int) Request::input('id_estacion', 0);
$retardo = (int) Request::input('retardo', 0);
$incidencia = (int) Request::input('incidencia', 0);

if ($idEstacion <= 0) {
JsonResponse::custom(['success' => false, 'message' => 'Selecciona una estación.']);
}

$result = BiometricosService::updateRetardoIncidencia($idEstacion, $retardo, $incidencia);
JsonResponse::custom($result);
}

public function datatableHorarios()
{
$idEstacion = (int) Request::input('id_estacion', 0);
if ($idEstacion <= 0) {
JsonResponse::custom(['success' => true, 'data' => []]);
return;
}

$data = BiometricosService::datatableHorarios($idEstacion);
JsonResponse::custom(['success' => true, 'data' => $data]);
}

public function createHorario()
{
$permisos = ModuloDptoOperativoService::permisosSesion('recursos-humanos');
if (empty($permisos['crear'])) {
JsonResponse::custom(['success' => false, 'message' => 'No tienes permisos para crear.']);
}

$idEstacion = (int) Request::input('id_estacion', 0);
$titulo = trim((string) Request::input('titulo', ''));
$horaEntrada = trim((string) Request::input('hora_entrada', ''));
$horaSalida = trim((string) Request::input('hora_salida', ''));

if ($idEstacion <= 0) {
JsonResponse::custom(['success' => false, 'message' => 'Selecciona una estación.']);
}

$result = BiometricosService::createHorario($idEstacion, $titulo, $horaEntrada, $horaSalida);
JsonResponse::custom($result);
}

public function updateHorario()
{
$permisos = ModuloDptoOperativoService::permisosSesion('recursos-humanos');
if (empty($permisos['editar'])) {
JsonResponse::custom(['success' => false, 'message' => 'No tienes permisos para editar.']);
}

$id = (int) Request::input('id', 0);
$titulo = trim((string) Request::input('titulo', ''));
$horaEntrada = trim((string) Request::input('hora_entrada', ''));
$horaSalida = trim((string) Request::input('hora_salida', ''));

if ($id <= 0) {
JsonResponse::custom(['success' => false, 'message' => 'ID requerido.']);
}

$result = BiometricosService::updateHorario($id, $titulo, $horaEntrada, $horaSalida);
JsonResponse::custom($result);
}

public function deleteHorario()
{
$permisos = ModuloDptoOperativoService::permisosSesion('recursos-humanos');
if (empty($permisos['eliminar'])) {
JsonResponse::custom(['success' => false, 'message' => 'No tienes permisos para eliminar.']);
}

$id = (int) Request::input('id', 0);
if ($id <= 0) {
JsonResponse::custom(['success' => false, 'message' => 'ID requerido.']);
}

$result = BiometricosService::deleteHorario($id);
JsonResponse::custom($result);
}

public function getData()
{
$datos = BiometricosService::getDatos();
JsonResponse::custom([
'success'  => true,
'data'     => $datos['rows'],
'contexto' => $datos['contexto'],
]);
}

public function getIncidenciasCatalogo()
{
$data = ControlDocumentosPersonalService::getIncidenciasCatalogo();
JsonResponse::success('OK', ['data' => $data]);
}

public function getIncidenciaPorAsistencia()
{
$idAsistencia = (int)Request::get('id_asistencia', 0);
if (!$idAsistencia) {
JsonResponse::error('Faltan datos requeridos.', 400, ['data' => null]);
}

$data = ControlDocumentosPersonalService::getIncidenciaPorAsistencia($idAsistencia);
JsonResponse::success('OK', ['data' => $data]);
}

public function agregarIncidencia()
{
$permisos = BiometricosService::getPermisos();

if (!$permisos['puedeCrear']) {
JsonResponse::custom(['success' => false, 'message' => 'No tienes permisos para crear incidencias.']);
}

$idAsistencia         = (int)Request::input('id_asistencia', 0);
$idIncidenciaCatalogo = (int)Request::input('id_incidencia', 0);
$comentario           = (string)Request::input('comentario', '');
$fechaInicio          = Request::filled('fecha_inicio') ? (string)Request::input('fecha_inicio') : null;
$fechaFin             = Request::filled('fecha_fin') ? (string)Request::input('fecha_fin') : null;
$sueldoDia            = Request::has('sueldo_dia') && Request::input('sueldo_dia') !== '' ? (float)Request::input('sueldo_dia') : null;

if (!$idAsistencia || !$idIncidenciaCatalogo) {
JsonResponse::custom(['success' => false, 'message' => 'Faltan datos requeridos.']);
}

$documentoRuta = self::subirDocumento('documento', $idAsistencia);

$result = ControlDocumentosPersonalService::agregarIncidencia(
$idAsistencia,
$idIncidenciaCatalogo,
$comentario,
$fechaInicio,
$fechaFin,
$sueldoDia,
$documentoRuta
);

JsonResponse::custom($result);
}

public function subirDocumentoIncidencia()
{
$permisos = BiometricosService::getPermisos();

if (!$permisos['puedeCrear']) {
JsonResponse::custom(['success' => false, 'message' => 'No tienes permisos para crear incidencias.']);
}

$idAsistencia = (int)Request::input('id_asistencia', 0);
$fechaInicio  = Request::filled('fecha_inicio') ? (string)Request::input('fecha_inicio') : null;
$fechaFin     = Request::filled('fecha_fin') ? (string)Request::input('fecha_fin') : null;
$sueldoDia    = Request::has('sueldo_dia') && Request::input('sueldo_dia') !== '' ? (float)Request::input('sueldo_dia') : null;

if (!$idAsistencia) {
JsonResponse::custom(['success' => false, 'message' => 'Faltan datos requeridos.']);
}

$documentoRuta = self::subirDocumento('documento', $idAsistencia, true);
if (!$documentoRuta) {
JsonResponse::custom(['success' => false, 'message' => 'No se pudo subir el archivo. Asegúrese de que sea formato PDF.']);
}

$result = ControlDocumentosPersonalService::subirDocumentoIncidencia($idAsistencia, $documentoRuta, $fechaInicio, $fechaFin, $sueldoDia);

JsonResponse::custom($result);
}

public function editarSueldoIncidencia()
{
$permisos = BiometricosService::getPermisos();

if (!$permisos['puedeEditar']) {
JsonResponse::custom(['success' => false, 'message' => 'No tienes permisos para editar.']);
}

$idAsistencia = (int)Request::input('id_asistencia', 0);
$sueldoDia    = (float)Request::input('sueldo_dia', 0);

if (!$idAsistencia) {
JsonResponse::custom(['success' => false, 'message' => 'Faltan datos requeridos.']);
}

$result = BiometricosService::editarSueldoIncidencia($idAsistencia, $sueldoDia);
JsonResponse::custom($result);
}

public function eliminarIncidencia()
{
$permisos = BiometricosService::getPermisos();

if (!$permisos['puedeEliminar']) {
JsonResponse::custom(['success' => false, 'message' => 'No tienes permisos para eliminar.']);
}

$idAsistencia = (int)Request::input('id_asistencia', 0);

if (!$idAsistencia) {
JsonResponse::custom(['success' => false, 'message' => 'Faltan datos requeridos.']);
}

$result = BiometricosService::eliminarIncidencia($idAsistencia);
JsonResponse::custom($result);
}

public function getReporte()
{
$permisos = BiometricosService::getPermisos();

$year = (int)Request::get('year', (int)date('Y'));
$mes  = (int)Request::get('mes', (int)date('m'));

if ($year < 2000 || $year > (int)date('Y') + 1 || $mes < 1 || $mes > 12) {
JsonResponse::custom(['success' => false, 'message' => 'Parámetros de reporte no válidos.']);
}

$reporte = BiometricosService::getReporteHtml($year, $mes, $permisos);
JsonResponse::custom(['success' => true, 'html' => $reporte['html']]);
}

public function reportePdf()
{
$permisos = BiometricosService::getPermisos();

if (!$permisos['puedeDescargar']) {
echo 'No tienes permisos para descargar este documento.';
exit;
}

$idEstacion = (int)Request::get('id_estacion', 0);
$year       = (int)Request::get('year', (int)date('Y'));
$mes        = (int)Request::get('mes', (int)date('m'));
$semana     = (int)Request::get('semana', 0);

if (!$idEstacion || !$semana || $year < 2000 || $mes < 1 || $mes > 12) {
echo 'Parámetros de reporte no válidos.';
exit;
}

$html = BiometricosService::getHtmlPdfReporte($idEstacion, $year, $mes, $semana);

$nombreEstacion = BiometricosService::resolveNombreEstacion($idEstacion) ?: ('Estacion_' . $idEstacion);
$archivo = 'Reporte incidencias de nomina - Semana ' . $semana . ' (' . $nombreEstacion . ').pdf';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Arial');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream($archivo, ['Attachment' => true]);
exit;
}

public function reportePdfEstaciones()
{
$permisos = BiometricosService::getPermisos();

if (!$permisos['puedeDescargar'] || BiometricosService::ocultaReporteEstaciones($permisos)) {
echo 'No tienes permisos para descargar este documento.';
exit;
}

$year   = (int)Request::get('year', (int)date('Y'));
$mes    = (int)Request::get('mes', (int)date('m'));
$semana = (int)Request::get('semana', 0);

if (!$semana || $year < 2000 || $mes < 1 || $mes > 12) {
echo 'Parámetros de reporte no válidos.';
exit;
}

$html = BiometricosService::getHtmlPdfReporteEstaciones($year, $mes, $semana);

$archivo = 'Reporte_Estaciones_Semana_' . $semana . '.pdf';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Arial');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream($archivo, ['Attachment' => true]);
exit;
}

public function reporteExcel()
{
$permisos = BiometricosService::getPermisos();

if (!$permisos['puedeDescargar']) {
echo 'No tienes permisos para descargar este documento.';
exit;
}

$idEstacion = (int)Request::get('id_estacion', 0);
$year       = (int)Request::get('year', (int)date('Y'));
$mes        = (int)Request::get('mes', (int)date('m'));

if (!$idEstacion || $year < 2000 || $mes < 1 || $mes > 12) {
echo 'Parámetros de reporte no válidos.';
exit;
}

$rows = BiometricosService::getReporteExcelData($idEstacion, $year, $mes);

$nombreEstacion = BiometricosService::resolveNombreEstacion($idEstacion) ?: ('Estación #' . $idEstacion);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle(substr($nombreEstacion, 0, 31));

$headers = [
'No.',
'Fecha',
'Nombre del personal',
'Puesto',
'Hora de Entrada (Sistema)',
'Hora de Salida (Sistema)',
'Hora de Entrada (Sensor)',
'Hora de Salida (Sensor)',
'Detalle',
];

$col = 1;
foreach ($headers as $header) {
$sheet->setCellValue(Coordinate::stringFromColumnIndex($col) . '1', $header);
$col++;
}

$sheet->getStyle('A1:I1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('749abf');
$sheet->getStyle('A1:I1')->getFont()->getColor()->setRGB('FFFFFF');
$sheet->getStyle('A1:I1')->getFont()->setBold(true);

$row = 2;
$num = 1;
foreach ($rows as $item) {
$horaEntrada      = $item['hora_entrada'] !== '00:00:00' ? date('g:i a', strtotime($item['hora_entrada'])) : 'S/I';
$horaSalida       = $item['hora_salida'] !== '00:00:00' ? date('g:i a', strtotime($item['hora_salida'])) : 'S/I';
$horaEntradaSensor = $item['hora_entrada_sensor'] !== '00:00:00' ? date('g:i a', strtotime($item['hora_entrada_sensor'])) : 'S/I';
$horaSalidaSensor  = $item['hora_salida_sensor'] !== '00:00:00' ? date('g:i a', strtotime($item['hora_salida_sensor'])) : 'S/I';

[$colorTable, $colorDetalle] = self::colorExcel(
$item['hora_entrada'],
$item['hora_salida'],
$item['hora_entrada_sensor'],
$item['hora_salida_sensor'],
$item['retardo_minutos']
);
$colorHex = self::obtenerColorHexExcel($colorDetalle);

$sheet->setCellValue('A' . $row, $num);
$sheet->getStyle("A$row")->getFont()->setBold(true);
$sheet->setCellValue('B' . $row, formatearFecha($item['fecha']));
$sheet->setCellValue('C' . $row, $item['nombre_completo']);
$sheet->setCellValue('D' . $row, $item['puesto']);
$sheet->setCellValue('E' . $row, $horaEntrada);
$sheet->setCellValue('F' . $row, $horaSalida);
$sheet->setCellValue('G' . $row, $horaEntradaSensor);
$sheet->setCellValue('H' . $row, $horaSalidaSensor);
$sheet->setCellValue('I' . $row, $item['detalle']);

$sheet->getStyle("A$row:I$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($colorTable);
$sheet->getStyle("I$row")->getFont()->getColor()->setRGB($colorHex);

$row++;
$num++;
}

$lastRow = $row - 1;
foreach (range('A', 'I') as $letter) {
$sheet->getColumnDimension($letter)->setAutoSize(true);
$sheet->getStyle($letter)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle($letter)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
}
$sheet->getStyle("A1:I$lastRow")->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

$nombreArchivo = 'Biometrico_' . nombremes(str_pad((string)$mes, 2, '0', STR_PAD_LEFT)) . '_' . $year . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
}

/**
* Colores de fila/detalle del Excel (misma lógica que la versión anterior).
*/
private static function colorExcel(string $horaEntrada, string $horaSalida, string $horaEntradaSensor, string $horaSalidaSensor, int $retardoMinutos): array
{
$colorTable = 'ffffff';
$colorDetalle = '';

if ($horaEntrada === '00:00:00' && $horaSalida === '00:00:00') {
if ($horaEntradaSensor !== '00:00:00') {
$colorTable = 'b0f2c2';
$colorDetalle = 'text-success';
} elseif ($horaEntradaSensor === '00:00:00' && $horaSalidaSensor === '00:00:00') {
$colorTable = 'cfe2ff';
$colorDetalle = 'text-secondary';
}
} else {
if ($horaEntradaSensor !== '00:00:00' || $horaSalidaSensor !== '00:00:00') {
$retardo = $retardoMinutos * 60;
$horainicio = strtotime($horaEntrada) + $retardo;
$sensorEntrada = strtotime($horaEntradaSensor);
if ($horainicio < $sensorEntrada) {
$colorTable = 'fcfcda';
$colorDetalle = 'text-warning';
}
} else {
$colorTable = 'ffb6af';
$colorDetalle = 'text-danger';
}
}

return [$colorTable, $colorDetalle];
}

/**
* Color hexadecimal del texto del detalle (mismo que la versión anterior).
*/
private static function obtenerColorHexExcel(string $colorDetalle): string
{
switch ($colorDetalle) {
case 'text-success':   return '198754';
case 'text-secondary': return '6c757d';
case 'text-warning':   return 'ffc107';
case 'text-danger':    return 'dc3545';
default:               return '000000';
}
}

/**
* Sube el documento de incidencia a la carpeta compartida del control de documentos.
*/
private static function subirDocumento(string $campo, int $idAsistencia, bool $obligatorio = false): ?string
{
if (empty($_FILES[$campo]) || !is_array($_FILES[$campo]) || empty($_FILES[$campo]['tmp_name']) || $_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
return null;
}

$carpeta = ControlDocumentosPersonalService::getUploadDir() . 'incidencias/';
if (!is_dir($carpeta)) {
mkdir($carpeta, 0777, true);
}

$ext = strtolower(pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['pdf'])) {
return null;
}

$nombre = 'incidencia_' . $idAsistencia . '_' . time() . '.' . $ext;

if (move_uploaded_file($_FILES[$campo]['tmp_name'], $carpeta . $nombre)) {
return $nombre;
}

return null;
}
}
