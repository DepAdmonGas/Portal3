<?php
namespace App\Controllers;

use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Models\SolicitudGafetes;
use App\Models\SolicitudGafetesSeguimiento;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Services\SolicitudGafetesService;
use App\Services\ModuleStationService;
use App\Models\Estacion;
use App\Core\Auth;
use Illuminate\Database\Capsule\Manager as Capsule;

class GafetesController extends BaseController{
protected string $modulo = 'solicitud-gafetes';

private function exigirCaptura(): void
{
if (!SolicitudGafetesService::puedeAgregar()) {
JsonResponse::forbidden('Solo los puestos 6 y 7 pueden agregar o editar registros de Solicitud de Gafetes.');
}
}

private function estatusSolicitud(Estacion $estacion, int $noReporte): int
{
    return SolicitudGafetesService::estatusActual((string) $estacion->nombre, $noReporte) ?? -1;
}

private function validarSolicitudAutorizada(int $idEstacion, int $noReporte): Estacion
{
$estacion = SolicitudGafetesService::estacionPermitida($idEstacion);
if (!$estacion || !SolicitudGafetes::where('id_estacion', $estacion->nombre)->where('no_reporte', $noReporte)->exists()) {
JsonResponse::notFound('La solicitud no existe o no tienes acceso a su estación.');
}
return $estacion;
}

private function archivoReferenciado(string $archivo): bool
{
    if ($archivo === '') return false;
    return SolicitudGafetes::where(function ($query) use ($archivo): void {
        $query->where('foto', $archivo)->orWhere('foto', 'like', '%/' . $archivo);
    })->exists();
}

// ============================================================
// SECURITY: Validación segura de uploads (Vulnerabilidad #11)
// ============================================================

/**
 * Valida uploads de archivos de forma segura
 * 
 * @param array $file Array $_FILES
 * @return array ['valid' => bool, 'error' => string|null, 'filename' => string|null]
 */
private function validateFileUpload(array $file): array
{
$response = ['valid' => false, 'error' => null, 'filename' => null];

// 1. Verificar que hay archivo
if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
$response['error'] = 'No se recibió archivo';
return $response;
}

// 2. Verificar error de upload
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
$response['error'] = 'Error al subir archivo: código ' . ($file['error'] ?? 'desconocido');
return $response;
}

// 3. Validar tamaño (ej: máximo 5MB)
$maxSize = 5 * 1024 * 1024;
if ($file['size'] > $maxSize) {
$response['error'] = 'El archivo excede el tamaño máximo de 5MB';
return $response;
}

// 4. Validar MIME type real (no solo extensión)
$finfo = new \finfo(FILEINFO_MIME_TYPE);
if (!$finfo) {
$response['error'] = 'Error al validar tipo de archivo';
return $response;
}
$mimeType = $finfo->file($file['tmp_name']);
if (!$mimeType) {
$response['error'] = 'No se pudo determinar el tipo de archivo';
return $response;
}

$allowedMimes = [
'image/jpeg',
'image/png', 
'image/gif',
'application/pdf',
];

if (!in_array($mimeType, $allowedMimes)) {
$response['error'] = 'Tipo de archivo no permitido: ' . $mimeType;
return $response;
}

// 5. Validar extensión coincide con MIME type
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$mimeToExt = [
'image/jpeg' => ['jpg', 'jpeg'],
'image/png' => ['png'],
'image/gif' => ['gif'],
'application/pdf' => ['pdf']
];

if (!isset($mimeToExt[$mimeType]) || !in_array($extension, $mimeToExt[$mimeType])) {
$response['error'] = 'La extensión no corresponde con el tipo de archivo';
return $response;
}

// 6. Generar nombre seguro
$safeFilename = preg_replace('/[^a-zA-Z0-9_-]/', '', $file['name']);
$filename = uniqid('gafete_') . '_' . substr($safeFilename, 0, 50) . '.' . $extension;

$response['valid'] = true;
$response['filename'] = $filename;
$response['mime'] = $mimeType;

return $response;
}

//---------------------------------------------------//
//----------------- PAGINA PRINCIPAL -----------------//
//---------------------------------------------------//
public function index(){

$title = 'Solicitud de Gafetes';

$datosUsuario = Auth::user();
$idPuesto = $datosUsuario->id_puesto;

Breadcrumb::add('Home', '/home');
Breadcrumb::add($title, '');

if (!$this->guardModuleAccess('solicitud-gafetes', $title, 'main')) {
return;
}

$permisos = ModuloService::permisosSesion($this->modulo);

// Compute pending counts per station for the selector
$pendientesData = SolicitudGafetesService::contarPendientesPorEstacion();

$data = [
'title' => $title,
'permisos' => $permisos,
'modulo' => $this->modulo,
'moduleStationKey' => 'solicitud-gafetes',
'pendientesData' => $pendientesData,
'puedeAdministrarGafetes' => SolicitudGafetesService::puedeAgregar() ? 'true' : 'false',
'filtro_usuario' => $this->filtro_usuario,
'utilitiesUser' =>[
'idPuestoUser' => $idPuesto
],
'links' =>[
'/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
],
'scripts' => [
'/js/vendor.min.js?v=' . time(),
'/libs/datatables.net/js/jquery.dataTables.min.js',
'/js/core/module-station-selector.js',
'/js/gafetes/gafetes.datatable.init.js?v=' . time(),
'/js/gafetes/actions.init.js?v=' . time()
],
'help' => false
];

View::render('gafetes/index', $data,'main');
}

public function datatableGafetes()
{
$alcance = SolicitudGafetesService::alcanceActual();
if (!$alcance['permitido']) {
JsonResponse::forbidden('No tienes estaciones autorizadas para consultar Solicitud de Gafetes.');
}

JsonResponse::custom([
'data' => SolicitudGafetesService::listarSolicitudes($alcance),
'filas_mostrar' => ['mostrar_fila_estacion' => $alcance['todas']],
'pendientes' => SolicitudGafetesService::contarPendientesPorEstacion(),
'permisos' => ['administrar' => SolicitudGafetesService::puedeAgregar()],
]);
}

public function createReporte()
{
    $this->exigirCaptura();
    $clave = trim((string) Request::input('clave', ''));
    $nombre = trim((string) Request::input('nombre_g', ''));
    $errores = [];
    if ($clave === '' || mb_strlen($clave) > 50) $errores['clave'] = 'La clave es obligatoria y no debe exceder 50 caracteres.';
    if ($nombre === '' || mb_strlen($nombre) > 255) $errores['nombre_g'] = 'El nombre es obligatorio y no debe exceder 255 caracteres.';
    if ($errores !== []) JsonResponse::validation('Verifique los campos obligatorios.', $errores);

    $alcance = SolicitudGafetesService::alcanceActual();
    if (!$alcance['permitido']) JsonResponse::forbidden('No tienes acceso a la estación seleccionada.');
    if ($alcance['seleccionada'] === null) {
        JsonResponse::validation('Selecciona una estación antes de agregar la solicitud.', ['estacion' => 'Selecciona una estación específica.']);
    }
    $estacion = SolicitudGafetesService::estacionPermitida($alcance['seleccionada']);
    if (!$estacion) JsonResponse::forbidden('No tienes acceso a la estación seleccionada.');

    $archivo = $_FILES['foto'] ?? null;
    if ($archivo === null || (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        JsonResponse::validation('Selecciona la foto del gafete.', ['foto' => 'La foto es obligatoria.']);
    }
    $validacion = $this->validateFileUpload($archivo);
    if (!$validacion['valid']) JsonResponse::validation($validacion['error'] ?? 'El archivo no es válido.', ['foto' => $validacion['error'] ?? 'Archivo inválido.']);

    $directorio = SolicitudGafetesService::directorioArchivos();
    if (!is_dir($directorio) && !mkdir($directorio, 0775, true) && !is_dir($directorio)) {
        JsonResponse::serverError('No fue posible preparar la carpeta de archivos de Portal3.');
    }
    $nombreArchivo = (string) $validacion['filename'];
    $rutaArchivo = $directorio . $nombreArchivo;
    if (!move_uploaded_file((string) $archivo['tmp_name'], $rutaArchivo)) {
        JsonResponse::serverError('No fue posible guardar la foto del gafete.');
    }

    $noReporte = 0;
    $idUsuario = (int) (Auth::id() ?? 0);
    try {
        Capsule::transaction(function () use ($estacion, $clave, $nombre, $nombreArchivo, &$noReporte): void {
            $ultimo = SolicitudGafetes::where('id_estacion', $estacion->nombre)->lockForUpdate()->max('no_reporte');
            $noReporte = ((int) $ultimo) + 1;
            SolicitudGafetes::create([
                'no_reporte' => $noReporte,
                'id_estacion' => $estacion->nombre,
                'usuario' => (string) (Auth::name() ?? ''),
                'fecha' => date('Y-m-d'),
                'clave' => $clave,
                'nombre' => $nombre,
                'foto' => $nombreArchivo,
                'comentarios' => '',
                'estatus' => 0,
            ]);
        });
    } catch (\Throwable $error) {
        if (is_file($rutaArchivo)) @unlink($rutaArchivo);
        error_log('GafetesController::createReporte - ' . $error->getMessage());
        JsonResponse::serverError('No fue posible registrar la solicitud de gafetes.');
    }

    SolicitudGafetesService::notificar('agregar', (int) $estacion->id, $noReporte, $idUsuario, 'Nueva solicitud');
    JsonResponse::success('Solicitud guardada correctamente.', [
        'idEstacion' => (int) $estacion->id,
        'no_reporte' => $noReporte,
    ]);
}

public function deleteReporte(){

$id = filter_var(Request::input('id'), FILTER_VALIDATE_INT);
if (!$id || $id < 1) JsonResponse::validation('Selecciona una solicitud válida.', ['id' => 'El identificador es obligatorio.']);

$principal = SolicitudGafetes::find($id);
if (!$principal) JsonResponse::notFound('La solicitud no existe o ya fue eliminada.');
$estacion = Estacion::where('nombre', $principal->id_estacion)->first();
if (!$estacion || !SolicitudGafetesService::estacionPermitida((int) $estacion->id)) {
JsonResponse::forbidden('No tienes acceso a la estación de esta solicitud.');
}

$solicitudes = SolicitudGafetes::where('id_estacion', $principal->id_estacion)->where('no_reporte', $principal->no_reporte)->get();
if ($solicitudes->isEmpty()) JsonResponse::notFound('La solicitud no existe o ya fue eliminada.');
$estatus = $this->estatusSolicitud($estacion, (int) $principal->no_reporte);
if (!SolicitudGafetesService::puedeEliminar($estatus)) {
    JsonResponse::forbidden('Solo los puestos 6 y 7 pueden eliminar solicitudes pendientes; los puestos 13 y 1 pueden eliminarlas cuando están en proceso.');
}
$archivos = $solicitudes->pluck('foto')->filter()->map(static fn($archivo): string => basename((string) $archivo))->unique()->all();

try {
Capsule::transaction(function () use ($principal, $estacion): void {
SolicitudGafetes::where('id_estacion', $principal->id_estacion)->where('no_reporte', $principal->no_reporte)->delete();
SolicitudGafetesSeguimiento::where('id_estacion', $estacion->id)->where('no_reporte', $principal->no_reporte)->delete();
});
} catch (\Throwable $error) {
error_log('GafetesController::deleteReporte - ' . $error->getMessage());
JsonResponse::serverError('No fue posible eliminar la solicitud.');
}

$directorio = SolicitudGafetesService::directorioArchivos();
foreach ($archivos as $archivo) {
if ($archivo !== '' && !$this->archivoReferenciado($archivo)) {
$ruta = realpath($directorio . $archivo);
$base = realpath($directorio);
if ($ruta && $base && dirname($ruta) === $base && is_file($ruta)) @unlink($ruta);
}
}

SolicitudGafetesService::notificar('eliminar', (int) $estacion->id, (int) $principal->no_reporte, (int) (Auth::id() ?? 0));
JsonResponse::success('Solicitud eliminada correctamente.');

}

//---------------------------------------------------//
//---------- FORMULARIO DEL NO. DE REPORTE ----------//
//---------------------------------------------------//

public function formularioReporte($idEstacion, $noReporte){

$datosEstacion = SolicitudGafetesService::estacionPermitida((int) $idEstacion);
if (!$datosEstacion || !SolicitudGafetes::where('id_estacion', $datosEstacion->nombre)
    ->where('no_reporte', (int) $noReporte)->exists()) {
    header('Location: /home');
    exit;
}
$estatusSolicitud = $this->estatusSolicitud($datosEstacion, (int) $noReporte);

$datosUsuario = Auth::user();
$idPuesto = $datosUsuario->id_puesto;

$title = 'Solicitud de Gafetes Formulario (#' . $noReporte. ')';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Solicitud de Gafetes', '/solicitud-gafetes');
Breadcrumb::add($title, '');

// Buscar permisos de los modulos
$permisos = ModuloService::permisosSesion($this->modulo);

$data = [
'title' => $title,
'permisos' => $permisos,
'modulo' => $this->modulo,
'idEstacion' => $idEstacion,
'noReporte' => $noReporte,
'puedeAdministrarGafetes' => (SolicitudGafetesService::puedeAgregar() && in_array($estatusSolicitud, [0, 1], true)) ? 'true' : 'false',
'utilitiesUser' =>[
'idPuestoUser' => $idPuesto
],
'links' =>[
'/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
],
'scripts' => [
'/js/vendor.min.js?v=' . time(),
'/libs/datatables.net/js/jquery.dataTables.min.js',
'/js/gafetes/gafetes.formulario.datatable.init.js?v=' . time(),
'/js/gafetes/actions.formulario.init.js?v=' . time()
],
'help' => false
];

View::render('gafetes/formulario-index', $data,'main');

}

public function datatableGafetesFormulario($idEstacion, $noReporte)
{
$estacion = $this->validarSolicitudAutorizada((int) $idEstacion, (int) $noReporte);
$estatus = $this->estatusSolicitud($estacion, (int) $noReporte);
$puedeAgregar = SolicitudGafetesService::puedeAgregarRegistro($estatus);
$puedeEditar = SolicitudGafetesService::puedeEditar($estatus);
$puedeEliminar = SolicitudGafetesService::puedeEliminar($estatus);
$directorio = SolicitudGafetesService::directorioArchivos();
$rows = SolicitudGafetes::where('id_estacion', $estacion->nombre)->where('no_reporte', $noReporte)->orderBy('id')->get();
$data = $rows->map(static function (SolicitudGafetes $row) use ($directorio): array {
    $archivo = basename((string) $row->foto);
    return [
        'idGafete' => (int) $row->id,
        'clave' => (string) $row->clave,
        'nombre_completo' => (string) $row->nombre,
        'foto_gafete' => $archivo,
        'archivo_disponible' => $archivo !== '' && is_file($directorio . $archivo),
        'fecha_formateada' => $row->fecha ? formatearFecha((string) $row->fecha) : 'S/I',
    ];
})->all();
JsonResponse::custom(['data' => $data, 'permisos' => ['administrar' => $puedeAgregar, 'eliminar' => $puedeEliminar, 'editar' => $puedeEditar, 'descargar' => true]]);
}

public function createReporteFormulario(){
$this->exigirCaptura();
$idEstacion = filter_var(Request::input('idEstacion'), FILTER_VALIDATE_INT);
$noReporte = filter_var(Request::input('no_reporte'), FILTER_VALIDATE_INT);
$clave = trim((string) Request::input('clave', ''));
$nombre = trim((string) Request::input('nombre_g', ''));
$errores = [];
if (!$idEstacion || !$noReporte) $errores['solicitud'] = 'La estación y solicitud son obligatorias.';
if ($clave === '' || mb_strlen($clave) > 50) $errores['clave'] = 'La clave es obligatoria y no debe exceder 50 caracteres.';
if ($nombre === '' || mb_strlen($nombre) > 255) $errores['nombre_g'] = 'El nombre es obligatorio y no debe exceder 255 caracteres.';
if ($errores !== []) JsonResponse::validation('Verifique los campos obligatorios.', $errores);

$estacion = $this->validarSolicitudAutorizada((int) $idEstacion, (int) $noReporte);
$estatus = $this->estatusSolicitud($estacion, (int) $noReporte);
if (!SolicitudGafetesService::puedeAgregarRegistro($estatus)) JsonResponse::forbidden('Solo los puestos 6 y 7 pueden agregar registros mientras la solicitud esté pendiente o en proceso.');
$file = $_FILES['foto'] ?? null;
if ($file === null || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    JsonResponse::validation('Selecciona la foto del gafete.', ['foto' => 'La foto es obligatoria.']);
}
$validacion = $this->validateFileUpload($file);
if (!$validacion['valid']) JsonResponse::validation((string) $validacion['error'], ['foto' => (string) $validacion['error']]);
$directorio = SolicitudGafetesService::directorioArchivos();
if (!is_dir($directorio) && !mkdir($directorio, 0775, true) && !is_dir($directorio)) JsonResponse::serverError('No fue posible preparar la carpeta de archivos.');
$archivo = (string) $validacion['filename'];
$ruta = $directorio . $archivo;
if (!move_uploaded_file((string) $file['tmp_name'], $ruta)) JsonResponse::serverError('No fue posible guardar la foto del gafete.');

try {
    $registroBase = SolicitudGafetes::where('id_estacion', $estacion->nombre)->where('no_reporte', $noReporte)->first();
    SolicitudGafetes::create([
        'no_reporte' => $noReporte, 'id_estacion' => $estacion->nombre,
        'usuario' => (string) (Auth::name() ?? ''), 'fecha' => date('Y-m-d'),
        'clave' => $clave, 'nombre' => $nombre, 'foto' => $archivo, 'comentarios' => '',
                'estatus' => $estatus,
    ]);
} catch (\Throwable $error) {
    if (is_file($ruta)) @unlink($ruta);
    error_log('GafetesController::createReporteFormulario - ' . $error->getMessage());
    JsonResponse::serverError('No fue posible guardar el registro del gafete.');
}

SolicitudGafetesService::notificar('agregar', (int) $estacion->id, (int) $noReporte, (int) (Auth::id() ?? 0), $nombre);
JsonResponse::success('Registro guardado correctamente.');
}

public function updateReporteFormulario(): void
{
    $this->exigirCaptura();
    $id = filter_var(Request::input('id'), FILTER_VALIDATE_INT);
    $clave = trim((string) Request::input('clave', ''));
    $nombre = trim((string) Request::input('nombre_g', ''));
    $errores = [];
    if (!$id || $id < 1) $errores['id'] = 'Selecciona un registro válido.';
    if ($clave === '' || mb_strlen($clave) > 50) $errores['clave'] = 'La clave es obligatoria y no debe exceder 50 caracteres.';
    if ($nombre === '' || mb_strlen($nombre) > 255) $errores['nombre_g'] = 'El nombre es obligatorio y no debe exceder 255 caracteres.';
    if ($errores !== []) JsonResponse::validation('Verifique los campos obligatorios.', $errores);

    $registro = SolicitudGafetes::find($id);
    if (!$registro) JsonResponse::notFound('El registro no existe o ya fue eliminado.');
    $estacion = Estacion::where('nombre', $registro->id_estacion)->first();
    if (!$estacion || !SolicitudGafetesService::estacionPermitida((int) $estacion->id)) JsonResponse::forbidden('No tienes acceso a la estación de este registro.');
    $this->validarSolicitudAutorizada((int) $estacion->id, (int) $registro->no_reporte);
    $estatus = $this->estatusSolicitud($estacion, (int) $registro->no_reporte);
    if (!SolicitudGafetesService::puedeEditar($estatus)) JsonResponse::forbidden('Solo los puestos 6 y 7 pueden editar registros mientras la solicitud esté pendiente.');

    $directorio = SolicitudGafetesService::directorioArchivos();
    $archivoAnterior = basename((string) $registro->foto);
    $archivoNuevo = null;
    $file = $_FILES['foto'] ?? null;
    if ($file !== null && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $validacion = $this->validateFileUpload($file);
        if (!$validacion['valid']) JsonResponse::validation((string) $validacion['error'], ['foto' => (string) $validacion['error']]);
        if (!is_dir($directorio) && !mkdir($directorio, 0775, true) && !is_dir($directorio)) JsonResponse::serverError('No fue posible preparar la carpeta de archivos.');
        $archivoNuevo = (string) $validacion['filename'];
        if (!move_uploaded_file((string) $file['tmp_name'], $directorio . $archivoNuevo)) JsonResponse::serverError('No fue posible guardar la nueva foto.');
    }

    try {
        $registro->clave = $clave;
        $registro->nombre = $nombre;
        if ($archivoNuevo !== null) $registro->foto = $archivoNuevo;
        $registro->save();
    } catch (\Throwable $error) {
        if ($archivoNuevo !== null && is_file($directorio . $archivoNuevo)) @unlink($directorio . $archivoNuevo);
        error_log('GafetesController::updateReporteFormulario - ' . $error->getMessage());
        JsonResponse::serverError('No fue posible actualizar el registro.');
    }

if ($archivoNuevo !== null && $archivoAnterior !== '' && !$this->archivoReferenciado($archivoAnterior)) {
        $oldPath = realpath($directorio . $archivoAnterior);
        $base = realpath($directorio);
        if ($oldPath && $base && dirname($oldPath) === $base && is_file($oldPath)) @unlink($oldPath);
    }
    SolicitudGafetesService::notificar('editar', (int) $estacion->id, (int) $registro->no_reporte, (int) (Auth::id() ?? 0), $nombre);
    JsonResponse::success('Registro actualizado correctamente.');
}

public function deleteReporteFormulario()
{
$id = filter_var(Request::input('id'), FILTER_VALIDATE_INT);
if (!$id || $id < 1) JsonResponse::validation('Selecciona un registro válido.', ['id' => 'El identificador es obligatorio.']);
$registro = SolicitudGafetes::find($id);
if (!$registro) JsonResponse::notFound('El registro no existe o ya fue eliminado.');
$estacion = Estacion::where('nombre', $registro->id_estacion)->first();
if (!$estacion || !SolicitudGafetesService::estacionPermitida((int) $estacion->id)) JsonResponse::forbidden('No tienes acceso a la estación de este registro.');
$this->validarSolicitudAutorizada((int) $estacion->id, (int) $registro->no_reporte);
$estatus = $this->estatusSolicitud($estacion, (int) $registro->no_reporte);
if (!SolicitudGafetesService::puedeEliminar($estatus)) JsonResponse::forbidden('No tienes permiso para eliminar un registro en el estado actual.');
$archivo = basename((string) $registro->foto);
try {
    $registro->delete();
} catch (\Throwable $error) {
    error_log('GafetesController::deleteReporteFormulario - ' . $error->getMessage());
    JsonResponse::serverError('No fue posible eliminar el registro.');
}
$directorio = SolicitudGafetesService::directorioArchivos();
if ($archivo !== '' && !$this->archivoReferenciado($archivo)) {
    $ruta = realpath($directorio . $archivo);
    $base = realpath($directorio);
    if ($ruta && $base && dirname($ruta) === $base && is_file($ruta)) @unlink($ruta);
}
 $sinRegistros = !SolicitudGafetes::where('id_estacion', $estacion->nombre)->where('no_reporte', $registro->no_reporte)->exists();
if ($sinRegistros) {
    SolicitudGafetesSeguimiento::where('id_estacion', $estacion->id)->where('no_reporte', $registro->no_reporte)->delete();
}
SolicitudGafetesService::notificar('eliminar', (int) $estacion->id, (int) $registro->no_reporte, (int) (Auth::id() ?? 0), (string) $registro->nombre);
JsonResponse::success(
    $sinRegistros ? 'Se eliminó el último registro. Volviendo a Solicitud de Gafetes.' : 'Registro eliminado correctamente.',
    ['sinRegistros' => $sinRegistros]
);
}

//---------------------------------------------------//
//---------------- PAGINA SEGUIMIENTO ---------------//
//---------------------------------------------------//
public function formularioSeguimiento($idEstacion, $noReporte){

$datosEstacion = $this->validarSolicitudAutorizada((int) $idEstacion, (int) $noReporte);
ModuleStationService::setContext($this->modulo, (int) $datosEstacion->id);

$datosUsuario = Auth::user();
$idPuesto = $datosUsuario->id_puesto;

$title = 'Detalle Solicitud de Gafetes (#' .  $noReporte  . ')';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Solicitud de Gafetes', '/solicitud-gafetes');
Breadcrumb::add($title, '');
// Buscar permisos de los modulos
$permisos = ModuloService::permisosSesion($this->modulo);

$data = [
'title' => $title,
'permisos' => $permisos,
'modulo' => $this->modulo,
'moduleStationKey' => $this->modulo,
'ocultarSelectorEstacion' => true,
'nombreBadgeEstacion' => (string) $datosEstacion->nombre,
'idEstacion' => $idEstacion,
'noReporte' => $noReporte,
'utilitiesUser' =>[
'idPuestoUser' => $idPuesto
],
'links' =>[
'/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
],
'scripts' => [
'/js/vendor.min.js',
'/libs/datatables.net/js/jquery.dataTables.min.js',
'/js/gafetes/gafetes.detalle.datatable.init.js?v=' . time(),
'/js/gafetes/gafetes.seguimiento.timeline.js?v=' . time(),
'/js/gafetes/actions.detalle.init.js?v=' . time()
],
'help' => false
];

View::render('gafetes/seguimiento-index', $data,'main');

}

public function timelineSeguimiento($idEstacion, $noReporte)
{

$datosEstacion = $this->validarSolicitudAutorizada((int) $idEstacion, (int) $noReporte);

$seguimientos = SolicitudGafetesSeguimiento::with('usuario')
->where('id_estacion', $idEstacion)
->where('no_reporte', $noReporte)
->orderBy('seguimiento')
->get();

if ($seguimientos->isEmpty()) {

$datosEstacion = Estacion::find($idEstacion);

$solicitud = SolicitudGafetes::where('id_estacion', $datosEstacion->nombre)
->where('no_reporte', $noReporte)
->first();

if ($solicitud) {

switch ($solicitud->estatus) {
case 0:
$pasos = [];
break;

case 1:
$pasos = [1];
break;

case 2:
case 3:
$pasos = [1, 2];
break;

case 4:
$pasos = [1, 2, 3];
break;

default:
$pasos = [];
break;
}

$seguimientos = collect($pasos)->map(function ($paso) use ($solicitud) {
return (object)[
'seguimiento' => $paso,
'fecha_hora' => $solicitud->fecha,
'usuario' => (object)[
'nombre' => 'Sin información'
]
];
});
}
}

JsonResponse::custom([
'data' => $seguimientos->map(function($data){
return [
'seguimiento' => $data->seguimiento,
'fecha_hora' => $data->fecha_hora,
'usuario' => $data->usuario->nombre ?? 'Sistema'
];
})
]);
}

public function updateSeguimientoGafetes()
{
$idEstacion = filter_var(Request::input('idEstacion'), FILTER_VALIDATE_INT);
$noReporte = filter_var(Request::input('no_reporte'), FILTER_VALIDATE_INT);
$idSeguimiento = filter_var(Request::input('idSeguimiento'), FILTER_VALIDATE_INT);
$comentario = trim((string) Request::input('comentario', ''));
if (!$idEstacion || !$noReporte || (!$idSeguimiento && $comentario === '')) {
    JsonResponse::validation('Indica el seguimiento o un comentario.', ['seguimiento' => 'La información es obligatoria.']);
}
$estacion = $this->validarSolicitudAutorizada((int) $idEstacion, (int) $noReporte);
if ($idSeguimiento && ($idSeguimiento < 1 || $idSeguimiento > 3)) JsonResponse::validation('El seguimiento no es válido.', ['seguimiento' => 'Selecciona un paso válido.']);
if (mb_strlen($comentario) > 2000) JsonResponse::validation('El comentario es demasiado largo.', ['comentario' => 'Máximo 2000 caracteres.']);
$estatusActual = $this->estatusSolicitud($estacion, (int) $noReporte);
if ($idSeguimiento && !SolicitudGafetesService::puedeRegistrarPaso($estatusActual, (int) $idSeguimiento)) {
    JsonResponse::forbidden('No tienes permiso para avanzar este paso desde el estado actual de la solicitud.');
}
if ($comentario !== '' && ($idSeguimiento !== 2 || !in_array(SolicitudGafetesService::puestoActual(), SolicitudGafetesService::PUESTOS_FINALIZACION, true))) {
    JsonResponse::forbidden('Solo los puestos 13 y 1 pueden agregar el comentario al finalizar una solicitud en proceso.');
}

try {
    Capsule::transaction(function () use ($idEstacion, $noReporte, $idSeguimiento, $comentario, $estacion): void {
        SolicitudGafetes::where('id_estacion', $estacion->nombre)->where('no_reporte', $noReporte)->lockForUpdate()->get();
        $estatusActual = (int) (SolicitudGafetesService::estatusActual((string) $estacion->nombre, (int) $noReporte) ?? -1);
        if ($idSeguimiento && !SolicitudGafetesService::puedeRegistrarPaso($estatusActual, (int) $idSeguimiento)) {
            JsonResponse::conflict('La solicitud cambió de estado. Actualiza la página antes de continuar.');
        }
        if ($comentario !== '' && ($idSeguimiento !== 2 || !in_array(SolicitudGafetesService::puestoActual(), SolicitudGafetesService::PUESTOS_FINALIZACION, true))) {
            JsonResponse::forbidden('Solo los puestos 13 y 1 pueden agregar el comentario al finalizar una solicitud en proceso.');
        }
        if ($idSeguimiento) {
            $query = SolicitudGafetesSeguimiento::where('id_estacion', $idEstacion)->where('no_reporte', $noReporte);
            if ($query->where('seguimiento', $idSeguimiento)->exists()) JsonResponse::conflict('Este seguimiento ya fue registrado.');
            $ultimoRegistrado = (int) (SolicitudGafetesSeguimiento::where('id_estacion', $idEstacion)->where('no_reporte', $noReporte)->max('seguimiento') ?? 0);
            $ultimoPorEstatus = match ($estatusActual) { 0 => 0, 1 => 1, 2, 3 => 2, 4 => 3, default => -1 };
            $ultimo = max($ultimoRegistrado, $ultimoPorEstatus);
            if ((int) $idSeguimiento !== $ultimo + 1) JsonResponse::conflict('El seguimiento no es válido en el orden.');
            if ((int) $idSeguimiento === 2) {
                SolicitudGafetes::where('id_estacion', $estacion->nombre)
                    ->where('no_reporte', $noReporte)->update(['comentarios' => $comentario]);
            }
            SolicitudGafetesSeguimiento::create([
                'id_estacion' => $idEstacion,
                'no_reporte' => $noReporte,
                'seguimiento' => $idSeguimiento,
                'id_usuario' => (int) (Auth::id() ?? 0),
                'fecha_hora' => date('Y-m-d H:i:s'),
            ]);
            $estatus = match ((int) $idSeguimiento) { 1 => 1, 2 => 2, 3 => 4 };
            SolicitudGafetes::where('id_estacion', $estacion->nombre)->where('no_reporte', $noReporte)->update(['estatus' => $estatus]);
        }
    });
} catch (\Throwable $error) {
    error_log('GafetesController::updateSeguimientoGafetes - ' . $error->getMessage());
    JsonResponse::serverError('No fue posible guardar el comentario o seguimiento.');
}

SolicitudGafetesService::notificar($idSeguimiento ? 'seguimiento' : 'comentario', (int) $estacion->id, (int) $noReporte, (int) (Auth::id() ?? 0), $comentario);
JsonResponse::success($idSeguimiento ? 'Seguimiento guardado correctamente.' : 'Comentario guardado correctamente.');
}

}
