<?php
namespace App\Controllers;  
use App\Core\View;
use App\Models\SolicitudTarjetas;
use App\Models\SolicitudTarjetasImagen;
use App\Models\SolicitudTarjetasSeguimiento;
use App\Core\Breadcrumb;
use App\Models\Usuario;
use App\Services\ModuloService;
use App\Services\ModuleStationService;
use App\Core\Session;
use App\Models\Estacion;
use App\Core\Auth;
use App\Core\Request;
use App\Core\JsonResponse;
use App\Services\SolicitudTarjetasService;
use Illuminate\Database\Capsule\Manager as Capsule;

class TarjetasController extends BaseController{
protected string $modulo = 'solicitud-tarjetas';

private const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;

private function validateTarjetaUpload(?array $file): array
{
    if ($file === null || ($file['error'] ?? null) === UPLOAD_ERR_NO_FILE) {
        return ['valid' => true, 'extension' => null];
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'message' => 'El archivo no se pudo subir correctamente.'];
    }

    if (!isset($file['tmp_name'], $file['size'], $file['name'])
        || !is_string($file['tmp_name'])
        || !is_int($file['size'])
        || $file['size'] < 1
        || $file['size'] > self::MAX_UPLOAD_BYTES
        || !is_file($file['tmp_name'])) {
        return ['valid' => false, 'message' => 'El archivo excede el tamaño permitido o es inválido.'];
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'pdf' => ['application/pdf'],
    ];

    if (!isset($allowed[$extension])) {
        return ['valid' => false, 'message' => 'Tipo de archivo no permitido.'];
    }

    $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!is_string($mime) || !in_array($mime, $allowed[$extension], true)) {
        return ['valid' => false, 'message' => 'El contenido del archivo no coincide con su extensión.'];
    }

    return ['valid' => true, 'extension' => $extension];
}

private function tarjetaJsonPayload(): array
{
    $payload = Request::json();
    if ($payload === []) JsonResponse::validation('El cuerpo JSON no es válido.', ['json' => 'Envía un objeto JSON válido.']);

    $allowed = ['id', 'razon_social', 'nombre_usuario', 'vehiculo', 'placas', 'no_unidad', 'tarjeta', 'tipo_tarjeta'];
    if (!is_array($payload) || array_diff(array_keys($payload), $allowed)) {
        JsonResponse::validation('La estructura JSON no es válida.', ['json' => 'La estructura contiene campos no permitidos.']);
    }

    $rules = [
        'id' => ['required' => true, 'integer' => true, 'min' => 1],
        'razon_social' => ['max' => 255],
        'nombre_usuario' => ['max' => 255],
        'vehiculo' => ['max' => 255],
        'placas' => ['max' => 20],
        'no_unidad' => ['max' => 20],
        'tarjeta' => ['max' => 50],
        'tipo_tarjeta' => ['enum' => ['Cliente Nuevo', 'Tarjeta Adicional', 'Desgaste', 'Reposición por extravio $50.00']],
    ];
    $errors = [];
    foreach ($rules as $field => $rule) {
        $value = $payload[$field] ?? null;
        if (($rule['required'] ?? false) && $value === null) {
            $errors[$field] = 'Campo requerido.';
            continue;
        }
        if ($field === 'id' && (!is_int($value) || $value < 1)) {
            $errors[$field] = 'Debe ser un entero positivo.';
            continue;
        }
        if ($field !== 'id' && (!is_string($value) || trim($value) === '')) {
            $errors[$field] = 'Debe ser texto no vacío.';
            continue;
        }
        if (isset($rule['max']) && is_string($value) && mb_strlen($value) > $rule['max']) {
            $errors[$field] = 'Excede la longitud permitida.';
        }
        if (isset($rule['enum']) && !in_array($value, $rule['enum'], true)) {
            $errors[$field] = 'Valor no permitido.';
        }
    }
    if ($errors) {
        JsonResponse::validation('Revisa los campos enviados.', $errors);
    }

    foreach (array_keys($rules) as $field) {
        if (is_string($payload[$field] ?? null)) {
            $payload[$field] = trim($payload[$field]);
        }
    }
    return $payload;
}

public function index(){

$title = 'Solicitud de Tarjetas';

$datosUsuario = Auth::user();
$idPuesto = $datosUsuario->id_puesto;

Breadcrumb::add('Home', '/home');
Breadcrumb::add($title, '');

if (!$this->guardModuleAccess('solicitud-tarjetas', $title, 'main')) {
return;
}
if (!SolicitudTarjetasService::puedeLeer()) { http_response_code(403); View::render('errors/404', [], 'main'); return; }

$permisos = ModuloService::permisosSesion($this->modulo);

// Compute pending counts per station for the selector
$pendientesData = SolicitudTarjetasService::contarPendientesPorEstacion();

$data = [
'title' => $title,
'permisos' => $permisos,
'modulo' => $this->modulo,
'moduleStationKey' => 'solicitud-tarjetas',
'pendientesData' => $pendientesData,
'puedeCrear' => SolicitudTarjetasService::puedeAgregar(),
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
'/js/tarjetas/tarjetas.datatable.init.js?v=' . time(),
'/js/tarjetas/actions.init.js?v=' . time()
]
];

View::render('tarjetas/index', $data, 'main');
}

public function datatableTarjetas(){
    if (!SolicitudTarjetasService::puedeLeer()) JsonResponse::forbidden('No tienes permiso para consultar Solicitud de Tarjetas.');
    $ctx = SolicitudTarjetasService::contexto();
    if (!$ctx['allowed']) JsonResponse::forbidden('No tienes estaciones autorizadas para este módulo.');
    $stations = ModuleStationService::getAvailableStations($this->modulo);
    $names = array_column($stations, 'nombre');
    $query = SolicitudTarjetas::query()->whereIn('id_estacion', $names)
        ->whereNotIn('id_estacion', ['', 'Administrador', 'Todas']);
    if (!$ctx['all']) $query->where('id_estacion', $ctx['station']->nombre);
    $rows = $query->orderByDesc('id')->get()->groupBy(fn($r) => $r->id_estacion . '|' . $r->no_solicitud)
        ->map(fn($group) => $group->sortByDesc('estatus')->first());
    $data = [];
    foreach ($rows as $row) {
        $status = (int)$row->estatus;
        $statusInfo = match (true) {
            $status === 0 => ['Sin atender', 'bg-danger'],
            $status === 1 => ['En proceso', 'bg-warning'],
            in_array($status, [2, 3], true) => ['Finalizada', 'bg-success'],
            $status === 4 => ['Entregada', 'bg-info'],
            default => ['Sin estatus', 'bg-secondary'],
        };
        $station = Estacion::where('nombre', $row->id_estacion)->first();
        $fechaRaw = $row->fecha ? date('Y-m-d', strtotime((string)$row->fecha)) : 'S/I';
        $data[] = [
            'idSolicitud' => (int)$row->id,
            'idEstacionReal' => (int)($station->id ?? 0),
            'no_solicitud' => (int)$row->no_solicitud,
            'fecha_solicitud' => $fechaRaw,
            'fecha_solicitud_formateada' => $fechaRaw === 'S/I' ? 'S/I' : formatearFecha($fechaRaw),
            'nombre_usuario' => $row->id_usuario ?: 'S/I',
            'nombre_estacion' => $row->id_estacion,
            'comentarios' => trim((string)$row->comentarios) ?: 'Sin comentarios',
            'estatus_solicitud' => $status,
            'estatus' => ['titulo' => $statusInfo[0], 'color_badge' => $statusInfo[1]],
'siguientePaso' => SolicitudTarjetasService::siguientePaso($status),
            'permisos' => [
                'disableDetail' => false,
                'disabledEdit' => !SolicitudTarjetasService::puedeEditar($status),
                'disabledDelete' => !SolicitudTarjetasService::puedeEliminar($status),
                'puedeActualizar' => SolicitudTarjetasService::siguientePaso($status) !== null,
            ],
        ];
    }
    $pendingCounts = SolicitudTarjetasService::contarPendientesPorEstacion();
    $pendingContext = $ctx['all'] ? $pendingCounts['total'] : ($pendingCounts['estacion_' . (int)$ctx['station']->id] ?? 0);
    JsonResponse::custom(['data' => $data, 'filas_mostrar' => ['mostrar_fila_estacion' => $ctx['all']], 'pendientesData' => $pendingCounts, 'pendientesContexto' => $pendingContext]);
}

public function createReporte()
{

if (!SolicitudTarjetasService::puedeAgregar()) {
JsonResponse::forbidden('No tienes permiso para crear solicitudes.');
}

$id = sanitize_input(Request::input('id'), 'int');
$file  = $_FILES['archivo'] ?? null;
$razon_social = sanitize_input(Request::input('razon_social'), 'string');
$nombre_usuario = sanitize_input(Request::input('nombre_usuario'), 'string');
$vehiculo = sanitize_input(Request::input('vehiculo'), 'string');
$placas = sanitize_input(Request::input('placas'), 'string');
$no_unidad = sanitize_input(Request::input('no_unidad'), 'string');
$tarjeta = sanitize_input(Request::input('tarjeta'), 'string');
$tipo_tarjeta = sanitize_input(Request::input('tipo_tarjeta'), 'string');
$comentarios = '';
$status = 0;

// Validar campos obligatorios
$errors = validate_input($_POST, [
'razon_social' => 'required|max:255',
'nombre_usuario' => 'required|max:255',
'vehiculo' => 'required|max:255',
'placas' => 'required|max:20',
'no_unidad' => 'required|max:20',
'tarjeta' => 'required|max:50',
'tipo_tarjeta' => 'required|max:50'
]);

if (!empty($errors)) {
JsonResponse::validation('Completa los campos obligatorios.', $errors);
}

if (!$razon_social || !$nombre_usuario || !$vehiculo || !$placas || !$no_unidad || !$tarjeta || !$tipo_tarjeta) {
JsonResponse::validation('Completa los campos obligatorios.', ['solicitud' => 'Faltan datos requeridos.']);
}

// CONFIG RUTA
$carpeta = dirname(__DIR__, 2) . '/public/uploads/archivos/solicitud-tarjetas/';
// SECURITY: BAJO #35 - Usar mkdir_safe con permisos 0755
if (!file_exists($carpeta)) {
mkdir_safe($carpeta, true);
}

$nombreArchivo = null;
try {

$upload = $this->validateTarjetaUpload($file);
if (!$upload['valid']) {
JsonResponse::validation('El archivo no es válido.', ['archivo' => $upload['message']]);
}

// SUBIR ARCHIVO (opcional)
if ($file && $file['error'] === UPLOAD_ERR_OK) {
// nombre único
$nombreArchivo = bin2hex(random_bytes(16)) . '.' . $upload['extension'];
$rutaDestino = $carpeta . $nombreArchivo;

if (!move_uploaded_file($file['tmp_name'], $rutaDestino)) {
throw new \Exception('No se pudo guardar el archivo');
}
}

// Obtener el usuario
$idUsuario = $this->userId();
$datosUsuario = Usuario::find($idUsuario);

// Obtener la estacion desde el contexto del módulo
$ctx = SolicitudTarjetasService::contexto();
$datosEstacion = $ctx['all'] ? null : ($ctx['station'] ?? null);
if (!$ctx['allowed'] || !$datosEstacion) {
    if ($nombreArchivo && is_file($carpeta . $nombreArchivo)) unlink($carpeta . $nombreArchivo);
    JsonResponse::forbidden('Selecciona una estación autorizada para crear la solicitud.');
}
$idEstacion = (int)$datosEstacion->id;

// Obtener el max(no_solicitud) por estación
$maxSolicitud = SolicitudTarjetas::where('id_estacion', $datosEstacion->nombre)
->max('no_solicitud');

$id_reporte = $maxSolicitud ? $maxSolicitud + 1 : 1;
$no_solicitud = ($id == 0) ? $id_reporte : $id;

Capsule::beginTransaction();

try {

// GUARDAR EN BD
SolicitudTarjetas::create([
'no_solicitud' => $no_solicitud,
'id_estacion' => $datosEstacion->nombre,
'id_usuario' => $datosUsuario->nombre,
'fecha' => date('Y-m-d'),
'razon_social' => $razon_social,  
'no_flotilla' => $nombre_usuario,
'vehiculo' => $vehiculo,  
'placas' => $placas,
'no_unidad' => $no_unidad,  
'tarjeta' => $tarjeta,
'tipo_tarjeta' => $tipo_tarjeta,  
'comentarios' => $comentarios,
'estatus' => $status,
]);

// 🔥 GUARDAR IMAGEN SOLO SI EXISTE
if ($nombreArchivo) {
SolicitudTarjetasImagen::create([
'ruta'         => $nombreArchivo,
'no_solicitud' => $no_solicitud,
'estacion'     => $datosEstacion->nombre,
]);
}

Capsule::commit();

SolicitudTarjetasService::notificar('agregar', $idEstacion, (int)$no_solicitud);

JsonResponse::success('Solicitud guardada correctamente', ['idEstacion' => $idEstacion, 'no_solicitud' => $no_solicitud]);

} catch (\Throwable $e) {
// Si falla BD, borrar archivo
if ($nombreArchivo && file_exists($carpeta . $nombreArchivo)) {
unlink($carpeta . $nombreArchivo);
}

Capsule::rollBack();
JsonResponse::error('No se pudo guardar la solicitud.', 500);
}

} catch (\Exception $e) {
Capsule::rollBack();

JsonResponse::error('No se pudo procesar la solicitud.', 500);

}
}

public function deleteReporte(){
$data = Request::json();
$id = $data['id'] ?? null;

if (!$id) JsonResponse::validation('Falta el identificador de la solicitud.', ['id' => 'Campo requerido.']);

$solicitud_principal = SolicitudTarjetas::find($id);
if (!$solicitud_principal) JsonResponse::notFound('La solicitud no existe o ya fue eliminada.');
$stationId = (int)(Estacion::where('nombre', $solicitud_principal->id_estacion)->value('id') ?? 0);
$ctx = SolicitudTarjetasService::contexto();
if (!$ctx['allowed'] || (!$ctx['all'] && (int)$ctx['station']->id !== $stationId)
    || !SolicitudTarjetasService::puedeEliminar((int)$solicitud_principal->estatus)) {
JsonResponse::forbidden('No tienes permiso para eliminar esta solicitud en su estatus actual.');
}

try {

$solicitudes = SolicitudTarjetas::where('id_estacion', $solicitud_principal->id_estacion)
->where('no_solicitud', $solicitud_principal->no_solicitud)
->get();

if ($solicitudes->isEmpty()) {
JsonResponse::notFound('La solicitud no existe.');
}

$rutaBase = dirname(__DIR__, 2) . '/public/uploads/archivos/solicitud-tarjetas/';
$archivosEliminar = [];

Capsule::beginTransaction();

$imagenes = SolicitudTarjetasImagen::where('no_solicitud', $solicitud_principal->no_solicitud)
->where('estacion', $solicitud_principal->id_estacion)
->get();

foreach ($imagenes as $img) {

$rutaArchivo = $rutaBase . basename((string)$img->ruta);
$archivosEliminar[] = $rutaArchivo;

SolicitudTarjetasImagen::where('id', $img->id)->delete();
}

foreach ($solicitudes as $solicitud) {
SolicitudTarjetas::where('id', $solicitud->id)->delete();
}

Capsule::commit();

foreach ($archivosEliminar as $rutaArchivo) {
if (is_file($rutaArchivo)) @unlink($rutaArchivo);
}

SolicitudTarjetasService::notificar('eliminar', $stationId, (int)$solicitud_principal->no_solicitud);

JsonResponse::success('Solicitud e imágenes eliminadas correctamente.');

} catch (\Throwable $e) {

Capsule::rollBack();

JsonResponse::error('No se pudo eliminar la solicitud.', 500);
}

}

//---------------------------------------------------//
//---------- FORMULARIO DEL NO. DE REPORTE ----------//
//---------------------------------------------------//
public function formularioReporte($idEstacion, $noSolicitud){

if (!SolicitudTarjetasService::puedeLeer()) { http_response_code(403); View::render('errors/404', [], 'main'); return; }

$solicitud = SolicitudTarjetasService::solicitud((int)$idEstacion, (int)$noSolicitud);
if (!$solicitud) { header('Location: /home'); exit; }

$datosUsuario = Auth::user();
$idPuesto = $datosUsuario->id_puesto;

$datosEstacion = Estacion::find($idEstacion);
$title = 'Solicitud de Tarjetas Formulario (#' . $noSolicitud. ')';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Solicitud de Tarjetas', '/solicitud-tarjetas');
Breadcrumb::add($title, '');

// Buscar permisos de los modulos
$permisos = ModuloService::permisosSesion($this->modulo);

$data = [
'title' => $title,
'permisos' => $permisos,
'modulo' => $this->modulo,
'idEstacion' => $idEstacion,
'noSolicitud' => $noSolicitud,
'puedeAgregarDetalle' => SolicitudTarjetasService::puedeAgregar() && in_array((int)$solicitud->estatus, [0, 1], true),
'utilitiesUser' =>[
'idPuestoUser' => $idPuesto
],
'links' =>[
'/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
],
'scripts' => [
'/js/vendor.min.js?v=' . time(),
'/libs/datatables.net/js/jquery.dataTables.min.js',
'/js/tarjetas/tarjetas.formulario.datatable.init.js?v=' . time(),
'/js/tarjetas/actions.formulario.init.js?v=' . time()
],
'help' => false
];

View::render('tarjetas/formulario-index', $data,'main');

}

public function datatableTarjetasFormulario($idEstacion, $noSolicitud)
{
    if (!SolicitudTarjetasService::puedeLeer()) JsonResponse::forbidden('No tienes permiso para consultar este detalle.');
    $solicitud = SolicitudTarjetasService::solicitud((int)$idEstacion, (int)$noSolicitud);
    if (!$solicitud) JsonResponse::notFound('La solicitud no existe o no está autorizada.');
    $status = (int)$solicitud->estatus;
    $tarjetas = SolicitudTarjetas::where('id_estacion', $solicitud->id_estacion)
        ->where('no_solicitud', $noSolicitud)->get()->map(function ($row) use ($status) {
            $row->acciones = [
                'editar' => SolicitudTarjetasService::puedeEditar($status),
                'eliminar' => SolicitudTarjetasService::puedeEliminar($status),
            ];
            return $row;
        });
    JsonResponse::custom(['data' => $tarjetas, 'permisos' => ['editar' => SolicitudTarjetasService::puedeEditar($status), 'eliminar' => SolicitudTarjetasService::puedeEliminar($status), 'crear' => SolicitudTarjetasService::puedeAgregar() && in_array($status, [0,1], true)]]);
}

public function createReporteFormulario(){
$data = Request::json();

if (!SolicitudTarjetasService::puedeAgregar()) {
JsonResponse::forbidden('No tienes permiso para agregar tarjetas a la solicitud.');
}

$no_solicitud = sanitize_input($data['no_solicitud'] ?? null, 'int');
$idEstacion = sanitize_input($data['idEstacion'] ?? null, 'int');
$razon_social = sanitize_input($data['razon_social'] ?? null, 'string');
$nombre_usuario = sanitize_input($data['nombre_usuario'] ?? null, 'string');
$vehiculo = sanitize_input($data['vehiculo'] ?? null, 'string');
$placas = sanitize_input($data['placas'] ?? null, 'string');
$no_unidad = sanitize_input($data['no_unidad'] ?? null, 'string');
$tarjeta = sanitize_input($data['tarjeta'] ?? null, 'string');
$tipo_tarjeta = sanitize_input($data['tipo_tarjeta'] ?? null, 'string');
$status = 0;

// Validar campos obligatorios
$errors = validate_input($data, [
'razon_social' => 'required|max:255',
'nombre_usuario' => 'required|max:255',
'vehiculo' => 'required|max:255',
'placas' => 'required|max:20',
'no_unidad' => 'required|max:20',
'tarjeta' => 'required|max:50',
'tipo_tarjeta' => 'required|max:50'
]);

if (!empty($errors)) JsonResponse::validation('Completa los campos obligatorios.', $errors);

if (!$razon_social || !$nombre_usuario || !$vehiculo || !$placas || !$no_unidad || !$tarjeta || !$tipo_tarjeta) {
JsonResponse::validation('Completa los campos obligatorios.', ['solicitud' => 'Faltan datos requeridos.']);
}

try {

// Obtener el usuario
$idUsuario = $this->userId();
$datosUsuario = Usuario::find($idUsuario);

// Obtener la estacion 
$solicitud = SolicitudTarjetasService::solicitud((int)$idEstacion, (int)$no_solicitud);
if (!$solicitud || !in_array((int)$solicitud->estatus, [0,1], true)) JsonResponse::forbidden('No se pueden agregar tarjetas a esta solicitud.');
$datosEstacion = Estacion::where('nombre', $solicitud->id_estacion)->first();

Capsule::beginTransaction();

try {

// GUARDAR EN BD
SolicitudTarjetas::create([
'no_solicitud' => $no_solicitud,
'id_estacion' => $datosEstacion->nombre,
'id_usuario' => $datosUsuario->nombre,
'fecha' => date('Y-m-d'),
'razon_social' => $razon_social,  
'no_flotilla' => $nombre_usuario,
'vehiculo' => $vehiculo,  
'placas' => $placas,
'no_unidad' => $no_unidad,  
'tarjeta' => $tarjeta,
'tipo_tarjeta' => $tipo_tarjeta,  
//'comentarios' => $comentarios,
'estatus' => (int)$solicitud->estatus,
]);

Capsule::commit();

SolicitudTarjetasService::notificar('agregar', (int)$idEstacion, (int)$no_solicitud, 'Se agregó una tarjeta solicitada');

JsonResponse::success('Tarjeta agregada correctamente.');



} catch (\Throwable $e) {

Capsule::rollBack();
JsonResponse::error('No se pudo agregar la tarjeta.', 500);
}

} catch (\Exception $e) {

Capsule::rollBack();

JsonResponse::error('No se pudo procesar la solicitud.', 500);

}

}


public function updateReporteFormulario(){
$data = $this->tarjetaJsonPayload();

$id = $data['id'];
$razon_social = $data['razon_social'] ?? null;
$nombre_usuario = $data['nombre_usuario'] ?? null;
$vehiculo = $data['vehiculo'] ?? null;
$placas = $data['placas'] ?? null;
$no_unidad = $data['no_unidad'] ?? null;
$tarjeta = $data['tarjeta'] ?? null;
$tipo_tarjeta = $data['tipo_tarjeta'] ?? null;

if (!$id || !$razon_social || !$nombre_usuario || !$vehiculo || !$placas || !$no_unidad || !$tarjeta || !$tipo_tarjeta) JsonResponse::validation('Completa los campos obligatorios.', ['registro' => 'Faltan datos requeridos.']);

$registro = SolicitudTarjetas::find($id);

if (!$registro) {
JsonResponse::notFound('El registro no existe.');
}

$stationId = (int)(Estacion::where('nombre', $registro->id_estacion)->value('id') ?? 0);
$ctx = SolicitudTarjetasService::contexto();
if (!$ctx['allowed'] || (!$ctx['all'] && (int)$ctx['station']->id !== $stationId)
    || !SolicitudTarjetasService::puedeEditar((int)$registro->estatus)) {
    JsonResponse::forbidden('Solo se pueden editar registros pendientes desde una estación autorizada.');
}

$registro->razon_social = $razon_social;
$registro->no_flotilla = $nombre_usuario;
$registro->vehiculo = $vehiculo;
$registro->placas = $placas;
$registro->no_unidad = $no_unidad;
$registro->tarjeta = $tarjeta;
$registro->tipo_tarjeta = $tipo_tarjeta;
$registro->save();

SolicitudTarjetasService::notificar('editar', $stationId, (int)$registro->no_solicitud, 'Se editaron los datos de una tarjeta solicitada');

JsonResponse::success('Registro actualizado correctamente.');

}

public function deleteReporteFormulario(){
$data = Request::json();
$id = $data['id'] ?? null;

if (!$id) JsonResponse::validation('Falta el identificador del registro.', ['id' => 'Campo requerido.']);

try {
// Buscar registro
$registro_reporte = SolicitudTarjetas::find($id);

if (!$registro_reporte) {
JsonResponse::notFound('El registro no existe.');
}

$stationId = (int)(Estacion::where('nombre', $registro_reporte->id_estacion)->value('id') ?? 0);
$ctx = SolicitudTarjetasService::contexto();
if (!$ctx['allowed'] || (!$ctx['all'] && (int)$ctx['station']->id !== $stationId)
    || !SolicitudTarjetasService::puedeEliminar((int)$registro_reporte->estatus)) {
    JsonResponse::forbidden('No tienes permiso para eliminar este registro en su estatus actual.');
}

// TRANSACCIÓN
Capsule::beginTransaction();

$noSolicitud = (int)$registro_reporte->no_solicitud;
$nombreEstacion = (string)$registro_reporte->id_estacion;
$registro_reporte->delete();
$remaining = SolicitudTarjetas::where('id_estacion', $nombreEstacion)
    ->where('no_solicitud', $noSolicitud)->count();
$archivosEliminar = [];
if ($remaining === 0) {
    SolicitudTarjetasSeguimiento::where('id_estacion', $stationId)
        ->where('no_reporte', $noSolicitud)->delete();
    $imagenes = SolicitudTarjetasImagen::where('estacion', $nombreEstacion)
        ->where('no_solicitud', $noSolicitud)->get();
    foreach ($imagenes as $imagen) {
        $archivosEliminar[] = dirname(__DIR__, 2) . '/public/uploads/archivos/solicitud-tarjetas/' . basename((string)$imagen->ruta);
        $imagen->delete();
    }
}
Capsule::commit();

foreach ($archivosEliminar as $archivoEliminar) {
    if (is_file($archivoEliminar)) @unlink($archivoEliminar);
}

SolicitudTarjetasService::notificar('eliminar', $stationId, $noSolicitud, 'Se eliminó un registro de tarjeta');

JsonResponse::success('Registro eliminado correctamente.', ['remaining' => $remaining]);

} catch (\Throwable $e) {
Capsule::rollBack();

JsonResponse::error('No se pudo eliminar el registro.', 500);
}

exit;
}


//---------- SEGUIMIENTO DE LA SOLICITUD ----------//
public function timelineSeguimiento($idEstacion, $noReporte)
{
$solicitudAutorizada = SolicitudTarjetasService::solicitud((int)$idEstacion, (int)$noReporte);
if (!$solicitudAutorizada) JsonResponse::notFound('La solicitud no existe o no está autorizada.');

$seguimientos = SolicitudTarjetasSeguimiento::with('usuario')
->where('id_estacion', $idEstacion)
->where('no_reporte', $noReporte)
->orderBy('seguimiento')
->get();

if ($seguimientos->isEmpty()) {

$datosEstacion = Estacion::find($idEstacion);

$solicitud = SolicitudTarjetas::where('id_estacion', $datosEstacion->nombre)
->where('no_solicitud', $noReporte)
->first();

if ($solicitud) {

switch ($solicitud->estatus) {
case 0:
$pasos = [0];
break;

case 1:
$pasos = [0, 1];
break;

case 2:
case 3:
$pasos = [0, 1, 2];
break;

case 4:
$pasos = [0, 1, 2, 3];
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
}),
'comentarios' => trim((string)$solicitudAutorizada->comentarios) ?: 'Sin comentarios',
'estatus' => (int)$solicitudAutorizada->estatus,
'siguientePaso' => SolicitudTarjetasService::siguientePaso((int)$solicitudAutorizada->estatus),
'puedeActualizar' => SolicitudTarjetasService::siguientePaso((int)$solicitudAutorizada->estatus) !== null
]);
}

//---------- ACTUALIZAR SEGUIMIENTO DE LA SOLICITUD ----------//
public function updateSeguimientoTarjetas()
{
$data = Request::json();

$idEstacion = $data['idEstacion'] ?? null;
$no_reporte = $data['no_reporte'] ?? null;
$idSeguimiento = (int)($data['idSeguimiento'] ?? 0);
$comentario = trim((string)($data['comentarios'] ?? ''));

$solicitud = SolicitudTarjetasService::solicitud((int)$idEstacion, (int)$no_reporte);
if (!$solicitud || !$idSeguimiento) JsonResponse::notFound('La solicitud no existe o los datos no son válidos.');
$status = (int)$solicitud->estatus;
if (SolicitudTarjetasService::siguientePaso($status) !== $idSeguimiento) JsonResponse::forbidden('El estatus actual no permite este cambio.');
$datosEstacion = Estacion::where('nombre', $solicitud->id_estacion)->first();

try {

Capsule::beginTransaction();
$solicitudBloqueada = SolicitudTarjetas::where('id_estacion', $solicitud->id_estacion)
    ->where('no_solicitud', $no_reporte)->lockForUpdate()->orderByDesc('estatus')->first();
if (!$solicitudBloqueada || SolicitudTarjetasService::siguientePaso((int)$solicitudBloqueada->estatus) !== $idSeguimiento) {
    Capsule::rollBack();
    JsonResponse::conflict('El estatus cambió; actualiza la pantalla e inténtalo de nuevo.');
}
$status = (int)$solicitudBloqueada->estatus;

$registro_existente = SolicitudTarjetasSeguimiento::where('id_estacion', $idEstacion)
->where('no_reporte', $no_reporte)
->where('seguimiento', $idSeguimiento)
->exists();

if ($registro_existente) { Capsule::rollBack(); JsonResponse::conflict('Este cambio de estatus ya fue registrado.'); }

$ultimo_seguimiento = SolicitudTarjetasSeguimiento::where('id_estacion', $idEstacion)
->where('no_reporte', $no_reporte)
->max('seguimiento');

$ultimo_seguimiento = (int)($ultimo_seguimiento ?? 0);

$pasoEsperado = $status === 0 ? 1 : ($status === 1 ? 2 : 3);
if ($idSeguimiento !== $pasoEsperado || ($ultimo_seguimiento > 0 && $idSeguimiento !== $ultimo_seguimiento + 1)) { Capsule::rollBack(); JsonResponse::conflict('El proceso debe continuar en orden.'); }

try {

SolicitudTarjetasSeguimiento::create([
'id_estacion' => $idEstacion,
'no_reporte'  => $no_reporte,
'seguimiento' => $idSeguimiento,
'id_usuario'     => $this->userId()
]);

if ($idSeguimiento == 1) {
SolicitudTarjetas::where('id_estacion', $datosEstacion->nombre)
->where('no_solicitud', $no_reporte)
->update(['estatus' => 1]);
}else if($idSeguimiento == 2){
SolicitudTarjetas::where('id_estacion', $datosEstacion->nombre)
->where('no_solicitud', $no_reporte)
->update(['estatus' => 2, 'comentarios' => $comentario]);
}else if($idSeguimiento == 3){
SolicitudTarjetas::where('id_estacion', $datosEstacion->nombre)
->where('no_solicitud', $no_reporte)
->update(['estatus' => 4]);
}

Capsule::commit();

SolicitudTarjetasService::notificar($idSeguimiento === 2 ? 'finalizar' : 'estatus', (int)$idEstacion, (int)$no_reporte, $idSeguimiento === 2 ? $comentario : 'Nuevo estatus: ' . ($idSeguimiento === 1 ? 'En proceso' : 'Entrega finalizada'));

JsonResponse::success('El estatus de la solicitud se actualizó correctamente.');

} catch (\Throwable $e) {

Capsule::rollBack();

JsonResponse::error('No se pudo actualizar el estatus.', 500);
}

} catch (\Throwable $e) {

Capsule::rollBack();

JsonResponse::error('No se pudo actualizar el estatus.', 500);
}

}

//---------------------------------------------------//
//----------- FORMULARIO DEL SEGUIMIENTO -----------//
//-------------------------------------------------//
public function formularioSeguimiento($idEstacion, $noSolicitud){

if (!SolicitudTarjetasService::puedeLeer()) { http_response_code(403); View::render('errors/404', [], 'main'); return; }

$solicitud = SolicitudTarjetasService::solicitud((int)$idEstacion, (int)$noSolicitud);
if (!$solicitud) { http_response_code(404); View::render('errors/404', [], 'main'); return; }

$datosUsuario = Auth::user();
$idPuesto = $datosUsuario->id_puesto;

$datosEstacion = Estacion::find($idEstacion);
ModuleStationService::setContext($this->modulo, (int) $datosEstacion->id);
$title = 'Detalle Solicitud de Tarjetas (#' . $noSolicitud . ')';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Solicitud de Tarjetas', '/solicitud-tarjetas');
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
'noSolicitud' => $noSolicitud,
'comentarios' => trim((string)$solicitud->comentarios) ?: 'Sin comentarios',
'utilitiesUser' =>[
'idPuestoUser' => $idPuesto
],
'links' =>[
'/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
],
'scripts' => [
'/js/vendor.min.js?v=' . time(),
'/libs/datatables.net/js/jquery.dataTables.min.js',
'/js/tarjetas/tarjetas.detalle.datatable.init.js',
'/js/tarjetas/tarjetas.seguimiento.timeline.js?v=' . time(),
'/js/tarjetas/actions.detalle.init.js?v=' . time()
],
'help' => false
];

View::render('tarjetas/seguimiento-index', $data,'main');
}

public function updateComentarioTarjetas(){
    JsonResponse::forbidden('Los comentarios solo se agregan al cambiar de En proceso a Finalizada.');
}

public function obtenerArchivoTarjeta($idEstacion, $noSolicitud)
{
$solicitud = SolicitudTarjetasService::solicitud((int)$idEstacion, (int)$noSolicitud);
if (!$solicitud || !SolicitudTarjetasService::puedeDescargar()) JsonResponse::forbidden('No tienes permiso para consultar este archivo.');
$datosEstacion = Estacion::where('nombre', $solicitud->id_estacion)->first();
$archivo = SolicitudTarjetasImagen::where('estacion', $datosEstacion->nombre)
->where('no_solicitud', $noSolicitud)
->orderBy('id', 'desc')
->first();
$archivoDisponible = $archivo && is_file(dirname(__DIR__, 2) . '/public/uploads/archivos/solicitud-tarjetas/' . basename((string)$archivo->ruta));

JsonResponse::custom([
'success' => true,
'archivo' => $archivoDisponible ? basename((string)$archivo->ruta) : null
]);
}


}
