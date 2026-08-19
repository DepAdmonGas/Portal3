<?php
namespace App\Controllers;

use Dompdf\Dompdf;
use Dompdf\Options;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Session;
use App\Core\JsonResponse;
use App\Services\HorarioPersonalService;

class HorarioPersonalController extends BaseController
{
public function index()
{
$permisos = HorarioPersonalService::getPermisos();
$esMultiestacion = $permisos['multiestacion'];
$idEstacion = $esMultiestacion ? 0 : $permisos['id_estacion'];

$sessionUsuario = Session::get('usuario');
if (!$esMultiestacion && !empty($sessionUsuario['id_estacion']) && $sessionUsuario['id_estacion'] == 2) {
$idEstacion = 0;
}

$title = 'Horario Personal';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
Breadcrumb::add($title, '');

if (!$this->guardModuleAccess(HorarioPersonalService::MODULE_KEY, $title, 'departamento-operativo')) {
return;
}

View::render('departamento-operativo/2-recursos-humanos/horario-personal/index', [
'title'            => $title,
'idEstacion'       => $idEstacion,
'multiestacion'    => $esMultiestacion,
'moduleStationKey' => HorarioPersonalService::MODULE_KEY,
'puedeEditar'      => $permisos['puedeEditar'],
'puedeEliminar'    => $permisos['puedeEliminar'],
'puedeDescargar'   => $permisos['puedeDescargar'],
'nombrePuesto'     => $permisos['nombre_puesto'],
'help'             => false,
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/libs/select2/dist/js/select2.full.min.js',
'/assets/js/core/module-station-selector.js?v=' . time(),
'/assets/js/departamento-operativo/2-recursos-humanos/horario-personal.datatable.init.js?v=' . time(),
'/assets/js/departamento-operativo/2-recursos-humanos/horario-personal.actions.init.js?v=' . time(),
],
'links' => [
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
'/assets/libs/select2/dist/css/select2.min.css',
],
], 'departamento-operativo');
}

public function getData()
{
$datos = HorarioPersonalService::getDatos();
JsonResponse::custom([
'success'  => true,
'data'     => $datos['rows'],
'contexto' => $datos['contexto'],
]);
}

public function editar()
{
$permisos = HorarioPersonalService::getPermisos();

if (!$permisos['puedeEditar']) {
JsonResponse::custom(['success' => false, 'message' => 'No tienes permisos para editar.']);
}

$data = json_decode(file_get_contents('php://input'), true);
$idPersonal = (int)($data['id_personal'] ?? 0);
$dia = (int)($data['dia'] ?? 0);
$horario = trim((string)($data['horario'] ?? ''));

if (!$idPersonal || !$dia) {
JsonResponse::custom(['success' => false, 'message' => 'Datos incompletos.']);
}

try {
if ($horario === '') {
HorarioPersonalService::eliminarHorarioDia($idPersonal, $dia);
JsonResponse::custom(['success' => true, 'message' => 'Horario del día eliminado correctamente.', 'celda' => null]);
}

$celda = HorarioPersonalService::editarHorario($idPersonal, $dia, $horario);
JsonResponse::custom(['success' => true, 'message' => 'Horario actualizado correctamente.', 'celda' => $celda]);
} catch (\Throwable $e) {
JsonResponse::custom(['success' => false, 'message' => $e->getMessage()]);
}
}

public function eliminar()
{
$permisos = HorarioPersonalService::getPermisos();

if (!$permisos['puedeEliminar']) {
JsonResponse::custom(['success' => false, 'message' => 'No tienes permisos para eliminar.']);
}

$data = json_decode(file_get_contents('php://input'), true);
$idPersonal = (int)($data['id'] ?? 0);

if (!$idPersonal) {
JsonResponse::custom(['success' => false, 'message' => 'ID no proporcionado.']);
}

try {
HorarioPersonalService::eliminarHorarioPersonal($idPersonal);
JsonResponse::custom(['success' => true, 'message' => 'Horario del personal eliminado correctamente.']);
} catch (\Throwable $e) {
JsonResponse::custom(['success' => false, 'message' => $e->getMessage()]);
}
}

public function pdf()
{
$permisos = HorarioPersonalService::getPermisos();

if (!$permisos['puedeDescargar']) {
echo 'No tienes permisos para descargar este documento.';
exit;
}

$fecha = (string)($_GET['fecha'] ?? '');
$html = HorarioPersonalService::getHtmlPdf($fecha);

$nombreContexto = HorarioPersonalService::getContexto()['nombre'];
$archivo = 'Horario_Personal' . ($nombreContexto ? '_' . str_replace(' ', '_', $nombreContexto) : '') . '.pdf';

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
}
