<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Estacion;
use App\Models\Usuario;
use Illuminate\Database\Capsule\Manager as Capsule;
use App\Models\Operativo\RhFormatos;
use App\Models\Operativo\RhFormatosAjusteSalarial;
use App\Models\Operativo\RhFormatosAlta;
use App\Models\Operativo\RhFormatosBaja;
use App\Models\Operativo\RhFormatosComentario;
use App\Models\Operativo\RhFormatosFalta;
use App\Models\Operativo\RhFormatosFirma;
use App\Models\Operativo\RhFormatosPrimaVacacional;
use App\Models\Operativo\RhFormatosRestructuracion;
use App\Models\Operativo\RhFormatosToken;
use App\Models\Operativo\RhFormatosVacaciones;
use App\Models\Operativo\RhLocalidad;
use App\Models\Operativo\RhPersonal;
use App\Models\Operativo\RhPersonalAcceso;
use App\Models\Operativo\RhPersonalBaja;
use App\Models\Operativo\RhPuestos;
use Carbon\Carbon;

class FormatosService
{
const MODULE_KEY = 'formatos';

const FORMATOS = [
1 => 'Alta de personal',
2 => 'Baja de Personal',
3 => 'Falta de Personal',
4 => 'Reestructuración de personal',
5 => 'Ajuste Salarial',
6 => 'Formato Vacaciones',
7 => 'Solicitud de Prima Vacacional',
];

const STATUS_LABELS = [
0 => 'Pendiente',
1 => 'En proceso',
2 => 'En proceso',
3 => 'Finalizado',
4 => 'Finalizado',
];

const CODIGOS_FORMATO = [
1 => 'RH-ALT-01',
2 => 'RH-BAJ-02',
3 => 'RH-FALT-03',
4 => 'RH-REEST-04',
5 => 'RH-ADJS-05',
6 => 'RH-FV-06',
7 => 'RH-SPV-07',
];

const INTRO_FORMATO = [
1 => 'Buenos días por medio del presente solicito de su amable apoyo para realizar la siguiente alta de personal.',
2 => 'Buenos días por medio del presente solicito de su amable apoyo para realizar las siguientes bajas de personal.',
3 => 'Por medio del presente se le notifica la siguiente incidencia que corresponde a faltas de personal.',
4 => 'Buenos días por medio del presente solicito de su amable apoyo para realizar el siguiente cambio de personal.',
5 => 'Buenos días por medio del presente solicito su apoyo para el ajuste salarial al siguiente colaborador.',
6 => 'Por medio de la presente, solicito su apoyo para llevar a cabo la autorización correspondiente en las vacaciones al siguiente colaborador.',
7 => 'Sirva la presente para enviarle un cordial saludo, al mismo tiempo, me permito solicitarle el pago de mi prima vacacional.',
];

const FIRMANTES = [
'B' => 19,
'C' => 2,
'D' => 354,
];

const MOTIVOS_BAJA = [
'Renuncia voluntaria',
'Mala practica',
'Abandono laboral',
];

private const DIRECTORIO_UPLOADS = '/uploads/archivos/formatos/';

private const CAMPOS_ARCHIVO_ALTA = [
'curriculum',
'ine',
'acta_nacimiento',
'nss',
'c_domicilio',
'c_estudios',
'c_recomendacion',
'curp',
'rfc',
'a_infonavit',
'c_antecedentes',
];

private const MAPEO_DOCUMENTOS_PERSONAL = [
'curriculum'      => ['folder' => 'curriculum',            'prefix' => 'Curriculum'],
'ine'              => ['folder' => 'ine',                    'prefix' => 'Identificacion'],
'acta_nacimiento'  => ['folder' => 'acta_nacimiento',        'prefix' => 'Acta de Nacimiento'],
'nss'              => ['folder' => 'nss',                    'prefix' => 'Comprobante IMSS'],
'c_domicilio'      => ['folder' => 'comprobante_domicilio',  'prefix' => 'Comprobante de Domicilio'],
'c_estudios'       => ['folder' => 'comprobante_estudios',   'prefix' => 'Comprobante de Estudios'],
'c_recomendacion'  => ['folder' => 'cartas_recomendacion',   'prefix' => 'Carta de Recomendacion'],
'curp'             => ['folder' => 'curp',                   'prefix' => 'CURP'],
'rfc'              => ['folder' => 'rfc',                    'prefix' => 'RFC'],
'a_infonavit'      => ['folder' => 'acta_infonavit',         'prefix' => 'Aviso Infonavit'],
'c_antecedentes'   => ['folder' => 'carta_antecedentes',     'prefix' => 'Antecedentes Penales'],
];

public static function getPermisos(): array
{
$usuario = Auth::user();
$sessionUsuario = Session::get('usuario');
$idUsuario = (int)($sessionUsuario['id'] ?? 0);
$idPuesto = (int)($usuario->id_puesto ?? 0);
$idEstacion = (int)($sessionUsuario['id_estacion'] ?? 0);
$multiestacion = !empty($sessionUsuario['multiestacion']);
$nombrePuesto = $usuario->puesto->tipo_puesto ?? '';

$permisosDb = ModuloDptoOperativoService::permisosSesion('recursos-humanos');
$tienePermisoLeer = !empty($permisosDb['leer']);
$tienePermisoCrear = !empty($permisosDb['crear']);
$tienePermisoEditar = !empty($permisosDb['editar']);
$tienePermisoEliminar = !empty($permisosDb['eliminar']);
$tienePermisoDescargar = !empty($permisosDb['descargar']);

return [
'id_usuario'     => $idUsuario,
'id_estacion'    => $idEstacion,
'id_puesto'      => $idPuesto,
'nombre_puesto'  => $nombrePuesto,
'multiestacion'  => $multiestacion,
'puedeLeer'      => $tienePermisoLeer,
'puedeAcceso'    => $tienePermisoLeer,
'puedeCrear'     => $tienePermisoCrear,
'puedeEditar'    => $tienePermisoEditar,
'puedeEliminar'  => $tienePermisoEliminar,
'puedeDescargar' => $tienePermisoDescargar,
'puedeFirmar'    => $tienePermisoLeer || $tienePermisoEditar,
'esFirmanteVOBO'       => $idUsuario === self::FIRMANTES['B'],
'esFirmanteAutorizacion' => $idUsuario === self::FIRMANTES['C'],
'esFirmanteVerificacion' => $idUsuario === self::FIRMANTES['D'],
'puedeFirmarVOBO'       => $idUsuario === self::FIRMANTES['B'],
'puedeFirmarAuth'       => $idUsuario === self::FIRMANTES['C'],
'puedeFirmarVerificacion' => $idUsuario === self::FIRMANTES['D'],
];
}

public static function getAllowedLocalidadIds(): array
{
$ids = [];
foreach (ModuleStationService::getAvailableStations(self::MODULE_KEY) as $s) {
$ids[] = (int)$s['id'];
}
foreach (ModuleStationService::getAvailableDepartments(self::MODULE_KEY) as $d) {
$ids[] = (int)$d['id'];
}
return array_values(array_unique($ids));
}

/**
* ¿El usuario actual puede gestionar (editar/eliminar) registros de la
* localidad indicada? Se respeta el conjunto de estaciones/departamentos
* que el puesto/usuario tiene autorizados.
*/
public static function puedeGestionarLocalidad(int $idLocalidad): bool
{
$allowed = self::getAllowedLocalidadIds();
return empty($allowed) || in_array($idLocalidad, $allowed, true);
}

public static function resolveNombreLocalidad(int $id): string
{
$loc = RhLocalidad::find($id);
if ($loc) {
return $loc->localidad;
}

$est = Estacion::find($id);
if ($est) {
return $est->nombre;
}

return '';
}

public static function getNombreContexto(int $idLocalidad): string
{
$nombre = self::resolveNombreLocalidad($idLocalidad);
return $nombre ?: 'Localidad #' . $idLocalidad;
}

/**
* Conteo de pendientes (status 0, 1 y 2) en el formato plano que usa
* ModuleStationService::render() para pintar los contadores del selector.
*/
public static function getPendingCountsFlat(): array
{
$estacionIds = [];
$pendientes = ['total' => 0];
foreach (ModuleStationService::getAvailableStations(self::MODULE_KEY) as $s) {
$id = (int)$s['id'];
$estacionIds[] = $id;
$pendientes['estacion_' . $id] = 0;
}
$deptoIds = [];
foreach (ModuleStationService::getAvailableDepartments(self::MODULE_KEY) as $d) {
$id = (int)$d['id'];
$deptoIds[] = $id;
$pendientes['depto_' . $id] = 0;
}

$allowed = array_values(array_unique(array_merge($estacionIds, $deptoIds)));

$counts = RhFormatos::query()
->whereIn('formato', array_keys(self::FORMATOS))
->where('status', '<', 3)
->whereYear('fecha', '>=', (int)date('Y'));

if (!empty($allowed)) {
$counts->whereIn('id_localidad', $allowed);
}

$counts->groupBy('id_localidad')->selectRaw('id_localidad, COUNT(*) as total');

foreach ($counts->get() as $row) {
$id = (int)$row->id_localidad;
$key = in_array($id, $deptoIds, true) ? 'depto_' . $id : 'estacion_' . $id;
$pendientes[$key] = (int)$row->total;
$pendientes['total'] += (int)$row->total;
}

return $pendientes;
}

/**
* Conteo de pendientes (status < 3) según la selección actual del contexto
* (estación específica, departamento específico o todas las permitidas).
*/
public static function getPendingCountsActual(): int
{
$ctx = ModuleStationService::getContext(self::MODULE_KEY);
$idEstacion = $ctx['id_estacion'];
$idDepto = $ctx['id_depto'];

$q = RhFormatos::query()
->whereIn('formato', array_keys(self::FORMATOS))
->where('status', '<', 3)
->whereYear('fecha', '>=', (int)date('Y'));

if ($idDepto && !$idEstacion) {
$q->where('id_localidad', $idDepto);
} elseif ($idEstacion) {
$q->where('id_localidad', $idEstacion);
} else {
$allowed = self::getAllowedLocalidadIds();
if (!empty($allowed)) {
$q->whereIn('id_localidad', $allowed);
}
}

return (int)$q->count();
}

public static function getListaFormatos(): array
{
$ctx = ModuleStationService::getContext(self::MODULE_KEY);
$idEstacion = $ctx['id_estacion'];
$idDepto = $ctx['id_depto'];
$permisos = self::getPermisos();

$q = RhFormatos::query();

if ($idDepto && !$idEstacion) {
$q->where('id_localidad', $idDepto);
} elseif ($idEstacion) {
$q->where('id_localidad', $idEstacion);
} else {
$allowed = self::getAllowedLocalidadIds();
if (!empty($allowed)) {
$q->whereIn('id_localidad', $allowed);
}
}

$q->whereIn('formato', array_keys(self::FORMATOS))
->whereYear('fecha', '>=', (int)date('Y'))
->orderBy('id', 'asc');

$data = [];

foreach ($q->get() as $f) {
$fecha = $f->fecha;
$fechaHora = '';
if ($fecha) {
$fechaHora = formatearFecha($fecha) . ', ' . $fecha->format('g:i a');
}

$status = (int)$f->status;
$numFirmas = RhFormatosFirma::where('id_formato', $f->id)->count();

$data[] = [
'id'               => $f->id,
'id_localidad'     => (int)$f->id_localidad,
'nombre_localidad' => self::resolveNombreLocalidad((int)$f->id_localidad),
'fecha_hora'       => $fechaHora,
'fecha'            => $fecha ? $fecha->format('Y-m-d H:i:s') : '',
'nombre_empleado'  => self::resolveNombreEmpleado((int)$f->formato, $f->id),
'formato'          => (int)$f->formato,
'formato_nombre'   => self::FORMATOS[(int)$f->formato] ?? '',
'status'           => $status,
'status_label'     => self::STATUS_LABELS[$status] ?? '',
'num_comentarios'  => RhFormatosComentario::where('id_formato', $f->id)->count(),
'num_firmas'       => $numFirmas,
'puede_detalle'    => RhFormatosFirma::where('id_formato', $f->id)->where('tipo_firma', 'A')->exists(),
'puede_firmar'     => $permisos['puedeFirmar'] && in_array($status, [0, 1, 2, 3], true),
'puede_editar'     => $permisos['puedeEditar'] && $status === 0,
'puede_eliminar'   => $permisos['puedeEliminar'] && $status < 3,
'puede_pdf'        => $status >= 3,
];
}

return $data;
}

public static function getDetalleFormato(int $formato, int $id): ?array
{
$formatoRow = RhFormatos::find($id);
if (!$formatoRow) {
return null;
}

if ($formato <= 0) {
$formato = (int)$formatoRow->formato;
}

$status = (int)$formatoRow->status;
$fecha = $formatoRow->fecha;

$detalle = [
'id'               => $id,
'id_localidad'     => (int)$formatoRow->id_localidad,
'nombre_localidad' => self::resolveNombreLocalidad((int)$formatoRow->id_localidad),
'fecha'            => $fecha ? $fecha->format('Y-m-d H:i:s') : '',
'fecha_hora'       => $fecha ? formatearFecha($fecha) . ', ' . $fecha->format('g:i a') : '',
'formato'          => $formato,
'formato_nombre'   => self::FORMATOS[$formato] ?? '',
'status'           => $status,
'status_label'     => self::STATUS_LABELS[$status] ?? '',
'nombre_empleado'  => self::resolveNombreEmpleado($formato, $id),
'campos'           => self::camposDetalle($formato, $id),
'archivos'         => self::archivosDetalle($formato, $id),
'codigo_formato'   => self::CODIGOS_FORMATO[$formato] ?? '',
'no_control'       => '00' . $id,
'encabezado_ciudad' => $fecha
? 'Huixquilucan, Edo. de México a ' . formatearFecha($fecha) . ', ' . $fecha->format('g:i a')
: '',
'dirigido_a'       => 'Lic. Alejandro Guzmán / Departamento de Recursos Humanos',
'intro'            => self::INTRO_FORMATO[$formato] ?? '',
'tabla'            => self::tablaDetalleFormato($formato, $id),
];

return $detalle;
}

public static function getFirmas(int $id): array
{
$firmas = RhFormatosFirma::where('id_formato', $id)->orderBy('id', 'asc')->get();
$data = [];

foreach ($firmas as $firma) {
$usuario = Usuario::find((int)$firma->id_usuario);
$tipo = $firma->tipo_firma;
$fechaStr = $firma->fecha ? $firma->fecha->format('Y-m-d H:i:s') : '';
$fechaF = substr($fechaStr, 0, 10);
$horaF = strlen($fechaStr) > 11 ? date('g:i a', strtotime(substr($fechaStr, 11))) : '';

$tipoLabel = match ($tipo) {
'A' => 'NOMBRE Y FIRMA DE QUIEN ELABORÓ',
'B' => 'NOMBRE Y FIRMA DEL VOBO',
'C' => 'NOMBRE Y FIRMA DE AUTORIZACIÓN',
'D' => 'NOMBRE Y FIRMA DE VERIFICACIÓN',
default => 'FIRMA ' . $tipo,
};

$data[] = [
'id'            => (int)$firma->id,
'id_usuario'    => (int)$firma->id_usuario,
'nombre'        => $usuario ? $usuario->nombre : 'Desconocido',
'usuario_nombre' => $usuario ? $usuario->nombre : 'Desconocido',
'tipo_firma'    => $tipo,
'tipo_label'    => $tipoLabel,
'firma'         => $firma->firma,
'firma_img_url' => in_array($tipo, ['A', 'D'], true)
? '/uploads/firmas/formatos/' . $firma->firma
: '',
'firma_texto'   => in_array($tipo, ['B', 'C'], true)
? '<b>Fecha: ' . formatearFecha($fechaF) . ', ' . $horaF . '</b> <br> El formato se firmó por un medio electrónico.'
: '',
'fecha'         => $fechaF ? formatearFecha($fechaF) . ', ' . $horaF : '',
];
}

return $data;
}

public static function getComentarios(int $id): array
{
$comentarios = RhFormatosComentario::where('id_formato', $id)->orderBy('id', 'asc')->get();
$data = [];

$sessionUsuario = Session::get('usuario');
$idActual = (int)($sessionUsuario['id'] ?? 0);

foreach ($comentarios as $comentario) {
$usuario = Usuario::find((int)$comentario->id_usuario);
$nombre = $usuario ? $usuario->nombre : 'Desconocido';
$fechaHora = $comentario->fecha_hora ? $comentario->fecha_hora->format('Y-m-d H:i:s') : '';
$fechaF = substr($fechaHora, 0, 10);
$horaF = strlen($fechaHora) > 11 ? date('g:i a', strtotime(substr($fechaHora, 11))) : '';

$data[] = [
'id'            => (int)$comentario->id,
'id_usuario'    => (int)$comentario->id_usuario,
'esPropio'      => (int)$comentario->id_usuario === $idActual,
'nombre'        => $nombre,
'usuario_nombre' => $nombre,
'comentario'    => $comentario->comentario,
'fecha'         => $fechaHora ? formatearFecha($fechaF) . ', ' . $horaF : '',
'fecha_hora'    => ($fechaF ? formatearFecha($fechaF) . ', ' . $horaF : ''),
];
}

return $data;
}

public static function storeComentario(int $id, int $idUsuario, string $comentario): array
{
$comentario = trim($comentario);
if ($id <= 0) {
return ['success' => false, 'message' => 'ID no válido'];
}
if ($comentario === '') {
return ['success' => false, 'message' => 'El comentario no puede estar vacío'];
}

$formato = RhFormatos::find($id);
if (!$formato) {
return ['success' => false, 'message' => 'Formato no encontrado'];
}

RhFormatosComentario::create([
'id_formato' => $id,
'id_usuario' => $idUsuario,
'fecha_hora' => Carbon::now(),
'comentario' => $comentario,
]);

$usuario = Usuario::find($idUsuario);
$nombreUsuario = $usuario ? $usuario->nombre : 'Desconocido';
$mensaje = '💬 Se ha agregado un comentario en el apartado de <b>Formatos</b>, correspondiente al formato denominado <b>' . self::FORMATOS[(int)$formato->formato] . '<b/>:' . PHP_EOL . PHP_EOL
. '📝 <b>Comentario:</b> ' . nl2br(htmlspecialchars($comentario, ENT_QUOTES, 'UTF-8')) . PHP_EOL
. '#️⃣ <b>No. de Control:</b> ' . $id . PHP_EOL . PHP_EOL
. '👤 <b>Responsable:</b> ' . htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8') . PHP_EOL
. '📍 <b>Estación/Departamento:</b> ' . self::getNombreContexto((int)$formato->id_localidad);

self::notificarUsuariosFormato($id, $idUsuario, $mensaje);

return ['success' => true, 'message' => 'Comentario agregado correctamente'];
}

public static function eliminarFormato(int $id, int $idUsuario): array
{
$formato = RhFormatos::find($id);
if (!$formato) {
return ['success' => false, 'message' => 'Formato no encontrado'];
}
if ((int)$formato->status >= 3) {
return ['success' => false, 'message' => 'No se puede eliminar un formato finalizado'];
}
if (!self::puedeGestionarLocalidad((int)$formato->id_localidad)) {
return ['success' => false, 'message' => 'No tienes permiso para eliminar este formato'];
}

// Se capturan los participantes y nombres ANTES de eliminar los registros de
// firmas/comentarios/tokens/detalle para poder notificar la eliminación.
$participantes = self::destinosFormato($id, $idUsuario);
$nombreEmpleados = self::resolveNombreEmpleado((int)$formato->formato, (int)$formato->id);

Capsule::transaction(function () use ($id, $formato) {
self::eliminarDetalleFormato((int)$formato->formato, $id);

RhFormatosFirma::where('id_formato', $id)->delete();
RhFormatosToken::where('id_formato', $id)->delete();
RhFormatosComentario::where('id_formato', $id)->delete();
$formato->delete();
});

$usuario = Usuario::find($idUsuario);
$nombreUsuario = $usuario ? $usuario->nombre : 'Desconocido';
$nombreFormato = self::FORMATOS[(int)$formato->formato] ?? 'Formato';
$folio = '00' . (int)$formato->id;
$contexto = self::getNombreContexto((int)$formato->id_localidad);

$mensaje = '🗑️ Se ha eliminado un formato del apartado de <b>Formatos</b>:' . PHP_EOL . PHP_EOL
. '📄 <b>Formato:</b> ' . $nombreFormato . PHP_EOL
. '#️⃣ <b>No. de Control:</b> ' . $folio . PHP_EOL . PHP_EOL
. '👤 <b>Responsable:</b> ' . htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8') . PHP_EOL
. '📍 <b>Estación/Departamento:</b> ' . $contexto ;

if (!empty($participantes)) {
$telegram = new TelegramService();
$telegram->sendMessageToMultiple($participantes, $mensaje);
}

return ['success' => true, 'message' => 'Formato eliminado correctamente'];
}

public static function crearToken(int $id, int $idUsuario, string $via = 'telegram'): array
{
try {
RhFormatosToken::where('id_formato', $id)->delete();

$token = rand(100000, 999999);

RhFormatosToken::create([
'id_formato'    => $id,
'id_usuario'    => $idUsuario,
'fecha_creacion' => Carbon::now(),
'token'         => $token,
]);
 
$formato = RhFormatos::find($id);
$nombreFormato = $formato ? self::FORMATOS[(int)$formato->formato] : 'Formato';
$contexto = $formato ? self::getNombreContexto((int)$formato->id_localidad) : '';

$mensaje = '📲 Usa el token <b>' . $token . '</b> para firma el siguiente formato en el apartado de <b>Formatos</b>:' . PHP_EOL . PHP_EOL
. '📄 <b>Formato:</b> ' . $nombreFormato . PHP_EOL
. '#️⃣ <b>No. de Control:</b> 00' . $id . PHP_EOL
. '📍 <b>Estación/Departamento:</b> ' . $contexto;

if ($via === 'email') {
$usuario = Usuario::find($idUsuario);
$email = $usuario?->email ?? '';
if (!$email) {
return ['success' => false, 'message' => 'El usuario no tiene correo electrónico registrado'];
}
$emailService = new EmailService();
$emailService->sendToken($email, $token);
return ['success' => true, 'message' => 'Token enviado por correo electrónico.'];
}

$telegram = new TelegramService();
$telegram->sendToken($idUsuario, $mensaje);
return ['success' => true, 'message' => 'Token enviado por Telegram.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al crear token: ' . $e->getMessage()];
}
}

public static function firmarFormato(int $id, string $tipoFirma, int $token, int $idUsuario): array
{
if (!in_array($tipoFirma, ['B', 'C'], true)) {
return ['success' => false, 'message' => 'Tipo de firma no válido'];
}

$firmanteId = $tipoFirma === 'B' ? (int)self::FIRMANTES['B'] : (int)self::FIRMANTES['C'];
if ((int)$idUsuario !== $firmanteId) {
$label = $tipoFirma === 'B' ? 'el Vo.Bo.' : 'la autorización';
return ['success' => false, 'message' => 'No tienes permisos para firmar ' . $label];
}

$tokenRecord = RhFormatosToken::where('id_formato', $id)
->where('id_usuario', $idUsuario)
->where('token', $token)
->orderBy('id', 'desc')
->first();

if (!$tokenRecord) {
return ['success' => false, 'message' => 'Token no válido'];
}

$expiry = Carbon::parse($tokenRecord->fecha_creacion)->addMinutes(2);
if (Carbon::now()->greaterThan($expiry)) {
return ['success' => false, 'message' => 'El token ha expirado'];
}

$estado = match ($tipoFirma) {
'B' => 2,
'C' => 3,
default => 0,
};

try {
$formato = RhFormatos::find($id);
if (!$formato) {
return ['success' => false, 'message' => 'Formato no encontrado'];
}

if ((int)$formato->status === 4) {
return ['success' => false, 'message' => 'El formato ya está finalizado'];
}

$statusActual = (int)$formato->status;
if ($tipoFirma === 'B' && $statusActual !== 1) {
return ['success' => false, 'message' => 'Aún no se ha firmado la elaboración del formato'];
}
if ($tipoFirma === 'C' && $statusActual !== 2) {
return ['success' => false, 'message' => 'Aún no se ha firmado el Vo.Bo.'];
}

if (in_array($tipoFirma, ['B', 'C'], true)
&& RhFormatosFirma::where('id_formato', $id)->where('tipo_firma', $tipoFirma)->exists()) {
$label = $tipoFirma === 'B' ? 'el VoBo' : 'la autorización';
return ['success' => false, 'message' => 'Ya fue firmado ' . $label];
}

$firmaHash = 'Firma:' . bin2hex(random_bytes(64)) . '.' . uniqid();

Capsule::transaction(function () use ($id, $idUsuario, $tipoFirma, $estado, $formato, $firmaHash) {
if ($tipoFirma === 'C') {
self::ejecutarOperacionAutorizacion($formato);
}

$formato->status = $estado;
$formato->save();

RhFormatosFirma::create([
'id_formato' => $id,
'id_usuario' => $idUsuario,
'tipo_firma' => $tipoFirma,
'firma'      => $firmaHash,
'fecha'      => Carbon::now(),
]);
});

$usuario = Usuario::find($idUsuario);
$nombre = $usuario ? $usuario->nombre : 'Desconocido';
$nombreFormato = self::FORMATOS[(int)$formato->formato] ?? 'Formato';
$folio = '00' . $id;
$nombreEmpleados = self::resolveNombreEmpleado((int)$formato->formato, $id);
$contexto = self::getNombreContexto((int)$formato->id_localidad);

if ($tipoFirma === 'B') {
$mensaje = '📝 Se ha firmado el Visto Bueno del formato en el apartado de <b>Formatos</b>:' . PHP_EOL . PHP_EOL
. '📄 <b>Formato:</b> ' . $nombreFormato . PHP_EOL
. '#️⃣ <b>No. de Control:</b> ' . $folio . PHP_EOL . PHP_EOL 
. '👤 <b>Responsable:</b> ' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8')  . PHP_EOL 
. '📍 <b>Estación/Departamento:</b> ' . $contexto;

} else {
$mensaje = '✅ Se ha firmado la Autorización del formato en el apartado de <b>Formatos</b>:' . PHP_EOL . PHP_EOL
. '📄 <b>Formato:</b> ' . $nombreFormato . PHP_EOL
. '#️⃣ <b>No. de Control:</b> ' . $folio . PHP_EOL . PHP_EOL 
. '👤 <b>Responsable:</b> ' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8')  . PHP_EOL 
. '📍 <b>Estación/Departamento:</b> ' . $contexto;
}
self::notificarUsuariosFormato($id, $idUsuario, $mensaje);

return ['success' => true, 'message' => 'Formato firmado correctamente', 'status' => $estado];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al firmar: ' . $e->getMessage()];
}
}

public static function firmarVerificacion(int $id, int $idUsuario, string $base64): array
{
if ($idUsuario !== self::FIRMANTES['D']) {
return ['success' => false, 'message' => 'No tienes permisos para firmar la verificación'];
}

$formato = RhFormatos::find($id);
if (!$formato) {
return ['success' => false, 'message' => 'Formato no encontrado'];
}
if ((int)$formato->status === 4) {
return ['success' => false, 'message' => 'El formato ya está finalizado'];
}
if ((int)$formato->status < 3) {
return ['success' => false, 'message' => 'Aún no se ha firmado la autorización'];
}
if (RhFormatosFirma::where('id_formato', $id)->where('tipo_firma', 'D')->exists()) {
return ['success' => false, 'message' => 'La verificación ya fue firmada'];
}

$res = self::guardarFirmaImagen($id, $idUsuario, $base64, 'D');
if (!$res['success']) {
return $res;
}

$formato->status = 4;
$formato->save();

$usuario = Usuario::find($idUsuario);
$nombre = $usuario ? $usuario->nombre : 'Desconocido';
$mensaje = '✅ Se ha firmado la Verificación del formato en el apartado de <b>Formatos</b>, completando todas las firmas requeridas:' . PHP_EOL . PHP_EOL
. '📄 <b>Formato:</b> ' . (self::FORMATOS[(int)$formato->formato] ?? 'Formato') .  PHP_EOL
. '#️⃣ <b>No. de Control:</b> ' . $id . PHP_EOL . PHP_EOL 
. '👤 <b>Responsable:</b> ' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8')  . PHP_EOL 
. '📍 <b>Estación/Departamento:</b> ' . self::getNombreContexto((int)$formato->id_localidad);
self::notificarUsuariosFormato($id, $idUsuario, $mensaje);

return ['success' => true, 'message' => 'Formato finalizado correctamente', 'status' => 4];
}

public static function getDatosFormulario(int $formato, int $idLocalidad): array
{
$data = [
'formato'        => $formato,
'formato_nombre' => self::FORMATOS[$formato] ?? '',
'id_localidad'   => $idLocalidad,
'nombre_localidad' => self::getNombreContexto($idLocalidad),
'puestos'        => RhPuestos::where('status', 1)->orderBy('puesto', 'asc')->get(['id', 'puesto']),
'personal'       => RhPersonal::where('id_estacion', $idLocalidad)
->where('estado', 1)
->orderBy('nombre_completo', 'asc')
->get(['id', 'nombre_completo', 'sd', 'puesto', 'fecha_ingreso']),
'estaciones'     => array_values(array_merge(
ModuleStationService::getAvailableStations(self::MODULE_KEY),
ModuleStationService::getAvailableDepartments(self::MODULE_KEY),
)),
'motivos'        => self::MOTIVOS_BAJA,
'detalle'        => null,
'valores'        => [],
'codigo_formato'  => self::CODIGOS_FORMATO[$formato] ?? '',
'encabezado_ciudad' => 'Huixquilucan, Edo. de México a ' . formatearFecha(date('Y-m-d')),
'dirigido_a'      => 'Lic. Alejandro Guzmán / Departamento de Recursos Humanos',
'intro'           => self::INTRO_FORMATO[$formato] ?? '',
];

return $data;
}

/**
* Prepara todos los datos que la vista necesita para renderizar el
* formulario sin lógica PHP (JSON listos, cabeceras, columnas, periodos,
* archivos del alta, etc.).
*/
public static function prepararDatosVista(array $datos): array
{
$formato = (int)($datos['formato'] ?? 0);
$personal = $datos['personal'] ?? [];
$puestos = $datos['puestos'] ?? [];
$estaciones = $datos['estaciones'] ?? [];
$idLocalidad = (int)($datos['id_localidad'] ?? 0);
$detalleFmt = $datos['detalle'] ?? null;

$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

$mapPersonal = collect($personal)->map(fn($p) => [
'id' => (int)$p->id,
'nombre_completo' => (string)$p->nombre_completo,
'sd' => (float)$p->sd,
'puesto' => (int)($p->puesto ?? 0),
'fecha_ingreso' => $p->fecha_ingreso ? formatearFecha($p->fecha_ingreso) : '',
])->values();

$mapPuestos = collect($puestos)->map(fn($p) => [
'id' => (int)$p->id,
'puesto' => (string)$p->puesto,
])->values();

$mapEstaciones = collect($estaciones)->map(fn($e) => [
'id' => (int)$e['id'],
'nombre' => (string)$e['nombre'],
])->values();

$datos['personal_json'] = json_encode($mapPersonal, $jsonFlags);
$datos['puestos_json'] = json_encode($mapPuestos, $jsonFlags);
$datos['estaciones_json'] = json_encode($mapEstaciones, $jsonFlags);

$datos['estaciones_cambio'] = array_values(array_filter($mapEstaciones->all(), fn($e) => (int)$e['id'] !== $idLocalidad));
$datos['estaciones_cambio_json'] = json_encode($datos['estaciones_cambio'], $jsonFlags);

$datos['motivos_json'] = json_encode(array_values($datos['motivos'] ?? []), $jsonFlags);

$archivosAlta = [];
foreach (self::getArchivosAlta() as $campo => $label) {
$archivosAlta[] = ['campo' => $campo, 'label' => $label];
}
$datos['archivos_alta'] = $archivosAlta;
$datos['archivos_alta_json'] = json_encode($archivosAlta, $jsonFlags);

$datos['tabla_columnas'] = self::getTablaColumnas($formato);
$datos['columnas_json'] = json_encode($datos['tabla_columnas'], $jsonFlags);
$datos['colspan_tabla'] = count($datos['tabla_columnas']);

$datos['periodos'] = self::getPeriodos();
$datos['periodos_json'] = json_encode($datos['periodos'], $jsonFlags);

$datos['es_multiempleado'] = $formato <= 5;
$datos['detalle_id'] = (int)($detalleFmt['id'] ?? 0);

$datos['valores_json'] = json_encode($datos['valores'] ?? [], $jsonFlags);

$datos['detalle_rows_json'] = json_encode($datos['detalle_rows'] ?? [], $jsonFlags);

$datos['cabecera'] = [
'codigo'     => $detalleFmt['codigo_formato'] ?? ($datos['codigo_formato'] ?? ''),
'no_control' => $detalleFmt['no_control'] ?? '',
'fecha'      => $detalleFmt['fecha_hora'] ?? ($datos['encabezado_ciudad'] ?? ''),
'dirigido_a' => $detalleFmt['dirigido_a'] ?? ($datos['dirigido_a'] ?? ''),
'intro'      => $detalleFmt['intro'] ?? ($datos['intro'] ?? ''),
];
$datos['cabecera_json'] = json_encode($datos['cabecera'], $jsonFlags);

$datos['formatos_data'] = json_encode([
'personal'        => $mapPersonal->all(),
'puestos'         => $mapPuestos->all(),
'estaciones'      => $mapEstaciones->all(),
'estacionesCambio' => $datos['estaciones_cambio'],
'motivos'         => $datos['motivos'] ?? [],
'archivosAlta'    => $archivosAlta,
'columnas'        => $datos['tabla_columnas'],
'periodos'        => $datos['periodos'],
'valores'         => $datos['valores'] ?? [],
'cabecera'        => $datos['cabecera'],
'detalleRows'     => $datos['detalle_rows'] ?? [],
], $jsonFlags);

return $datos;
}

private static function getArchivosAlta(): array
{
return [
'curriculum' => 'Solicitud de empleo',
'ine' => 'Identificación oficial vigente (INE o pasaporte)',
'acta_nacimiento' => 'Acta de nacimiento',
'nss' => 'Comprobante de afiliación IMSS',
'c_domicilio' => 'Comprobante de domicilio (recibo de teléfono, agua o predio con antigüedad máxima de tres meses)',
'c_estudios' => 'Último comprobante de estudios',
'c_recomendacion' => 'Cartas de recomendación',
'curp' => 'Clave única de registro de población (CURP)',
'rfc' => 'Constancia de situación fiscal (CSF) con homoclave',
'a_infonavit' => 'Aviso de retención de INFONAVIT',
'c_antecedentes' => 'Carta de antecedentes no penales',
];
}

private static function getTablaColumnas(int $formato): array
{ 
return match ($formato) {
1 => ['#', 'Nombre del empleado', 'Estación / Departamento', 'Puesto', 'Fecha de alta', 'Salario', '<i class="ti ti-file-text text-primary fs-5"></i>', '<i class="ti ti-trash text-danger fs-5"></i>'],
2 => ['#', 'Nombre del empleado', 'Estación / Departamento', 'Fecha de baja', 'Motivo', 'Detalle', '<i class="ti ti-trash text-danger fs-5"></i>'],
3 => ['#', 'Nombre del empleado', 'Día faltante', 'Estación / Departamento', '<i class="ti ti-trash text-danger fs-5"></i>'],
4 => ['#', 'Nombre del empleado', 'Estación / Departamento actual', 'Cambio a', 'Fecha de aplicación', '<i class="ti ti-trash text-danger fs-5"></i>'],
5 => ['#', 'Nombre del empleado', 'Puesto', 'Salario diario', 'Ajuste a', 'Aplicar a partir del', '<i class="ti ti-trash text-danger fs-5"></i>'],
default => ['#', ''],
};
}

private static function getPeriodos(): array
{
$periodos = [];
for ($i = (int)date('Y'); $i >= 2020; $i--) {
$periodos[] = $i;
}
return $periodos;
}

/**
* Valores actuales de un formato para precargar el formulario de edición,
* con las mismas claves que usa el formulario (name="...").
* Devuelve ['valores' => ...campos planos (formatos 6 y 7)...,
*            'detalle_rows' => ...filas previas (formatos 1 a 5)...].
*/
public static function getFormularioEdicion(int $formato, int $id): array
{
$valores = [];
$detalleRows = [];

switch ($formato) {
case 1:
case 2:
case 3:
case 4:
case 5:
$modelo = match ($formato) {
1 => RhFormatosAlta::class,
2 => RhFormatosBaja::class,
3 => RhFormatosFalta::class,
4 => RhFormatosRestructuracion::class,
5 => RhFormatosAjusteSalarial::class,
};
$rows = $modelo::where('id_formulario', $id)->orderBy('id')->get();
foreach ($rows as $row) {
$detalleRows[] = self::filaDesdeModelo($formato, $row);
}
break;

case 6:
$row = RhFormatosVacaciones::where('id_formulario', $id)->first();
if ($row) {
$valores = [
'id_personal' => (int)$row->id_usuario,
'num_dias'    => (int)$row->num_dias,
'fecha_inicio' => $row->fecha_inicio ? formatearFecha($row->fecha_inicio->format('Y-m-d')) : '',
'fecha_termino' => $row->fecha_termino ? formatearFecha($row->fecha_termino->format('Y-m-d')) : '',
'fecha_regreso' => $row->fecha_regreso ? formatearFecha($row->fecha_regreso->format('Y-m-d')) : '',
'observaciones' => $row->observaciones,
];
}
break;

case 7:
$row = RhFormatosPrimaVacacional::where('id_formulario', $id)->first();
if ($row) {
$valores = [
'id_personal' => (int)$row->id_personal,
'periodo'     => (int)$row->periodo,
];
}
break;
}

return ['valores' => $valores, 'detalle_rows' => $detalleRows];
}

/**
* Da el mismo formato de fila que devuelve getFormularioEdicion(), pero
* incluyendo el id persistido para poder quitar la fila desde la vista.
*/
private static function filaDesdeModelo(int $formato, $row): array
{
switch ($formato) {
case 1:
$existe = [];
foreach (self::CAMPOS_ARCHIVO_ALTA as $campo) {
if (!empty($row->{$campo})) {
$existe[$campo] = $row->{$campo};
}
}
return [
'id'            => (int)$row->id,
'id_estacion'   => (int)$row->id_estacion,
'nombre'        => $row->nombre,
'fecha_ingreso' => $row->fecha_ingreso ? formatearFecha($row->fecha_ingreso->format('Y-m-d')) : '',
'puesto'        => (int)$row->puesto,
'sd'            => (float)$row->sd,
'_existe'       => $existe,
];

case 2:
return [
'id'          => (int)$row->id,
'id_estacion' => (int)$row->id_estacion,
'id_personal' => (int)$row->id_personal,
'fecha_baja'  => $row->fecha_baja ? formatearFecha($row->fecha_baja->format('Y-m-d')) : '',
'motivo'      => $row->motivo,
'detalle'     => $row->detalle,
];

case 3:
return [
'id'          => (int)$row->id,
'id_estacion' => (int)$row->id_estacion,
'id_personal' => (int)$row->id_personal,
'dias_falta'  => $row->dias_falta ? formatearFecha($row->dias_falta->format('Y-m-d')) : '',
];

case 4:
return [
'id'                  => (int)$row->id,
'id_estacion'         => (int)$row->id_estacion,
'id_personal'         => (int)$row->id_personal,
'id_estacion_cambio'  => (int)$row->id_estacion_cambio,
'fecha'               => $row->fecha ? formatearFecha($row->fecha->format('Y-m-d')) : '',
];

case 5:
return [
'id'               => (int)$row->id,
'id_personal'      => (int)$row->id_personal,
'salario_actual'   => (float)$row->salario_actual,
'salario_ajustado' => (float)$row->salario_ajustado,
'fecha_aplicacion' => $row->fecha_aplicacion ? formatearFecha($row->fecha_aplicacion->format('Y-m-d')) : '',
];

default:
return ['id' => (int)$row->id];
}
}

/**
* Valida los campos obligatorios de una fila (empleado) para formatos 1 a 5,
* incluyendo la documentación obligatoria del alta de personal.
*/
private static function validarFila(int $formato, array $row, array $files = []): string
{
switch ($formato) {
case 1:
if (trim((string)($row['nombre'] ?? '')) === '') {
return 'El nombre completo es obligatorio';
}
if (empty($row['puesto'])) {
return 'El puesto es obligatorio';
}
if (empty($row['fecha_ingreso'])) {
return 'La fecha de ingreso es obligatoria';
}
if (($row['sd'] ?? '') === '' || $row['sd'] === null) {
return 'El salario diario es obligatorio';
}

$obligatorios = [
'curriculum', 'ine', 'acta_nacimiento', 'nss', 'c_domicilio',
'c_estudios', 'c_recomendacion', 'curp', 'rfc',
];
if ((int)($row['puesto'] ?? 0) === 4) {
$obligatorios[] = 'c_antecedentes';
}

$existe = is_array($row['_existe'] ?? null) ? $row['_existe'] : [];
foreach ($obligatorios as $campo) {
$tieneExiste = !empty($existe[$campo]);
$tieneArchivo = !empty($files['archivo_' . $campo]['name'])
&& ($files['archivo_' . $campo]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
if (!$tieneExiste && !$tieneArchivo) {
return 'Adjunta la documentación obligatoria del empleado';
}
}
break;

case 2:
if (empty($row['id_personal'])) {
return 'El empleado es obligatorio';
}
if (empty($row['fecha_baja'])) {
return 'La fecha de baja es obligatoria';
}
if (trim((string)($row['motivo'] ?? '')) === '') {
return 'La causa de la baja es obligatoria';
}
break;

case 3:
if (empty($row['id_personal'])) {
return 'El colaborador es obligatorio';
}
if (empty($row['dias_falta'])) {
return 'El día de falta es obligatorio';
}
break;

case 4:
if (empty($row['id_personal'])) {
return 'El empleado es obligatorio';
}
if (empty($row['id_estacion_cambio'])) {
return 'El cambio de ubicación es obligatorio';
}
if (empty($row['fecha'])) {
return 'La fecha de aplicación es obligatoria';
}
break;

case 5:
if (empty($row['id_personal'])) {
return 'El empleado es obligatorio';
}
if (($row['salario_actual'] ?? '') === '' || $row['salario_actual'] === null) {
return 'El salario actual es obligatorio';
}
if (($row['salario_ajustado'] ?? '') === '' || $row['salario_ajustado'] === null) {
return 'El salario ajustado es obligatorio';
}
if (empty($row['fecha_aplicacion'])) {
return 'La fecha de aplicación es obligatoria';
}
break;
}

return '';
}

/**
* Valida los campos obligatorios de los formatos de un solo empleado (6 y 7).
*/
private static function validarFormatoSimple(int $formato, array $data): string
{
if ($formato === 6) {
if (empty($data['id_personal'])) {
return 'Selecciona un empleado';
}
if ((int)($data['num_dias'] ?? 0) <= 0) {
return 'Indica el número de días a disfrutar';
}
if (empty($data['fecha_inicio'])) {
return 'Indica la fecha inicial';
}
if (empty($data['fecha_termino'])) {
return 'Indica la fecha final';
}
if (empty($data['fecha_regreso'])) {
return 'Indica la fecha de regreso';
}
}

if ($formato === 7) {
if (empty($data['id_personal'])) {
return 'Selecciona un empleado';
}
if ((int)($data['periodo'] ?? 0) <= 0) {
return 'Selecciona el periodo';
}
}

return '';
}

/**
* Pendiente que impide crear otro formato: mismo formato + mismo contexto
* (estación/departamento) + año actual o posterior + SIN firma de quien
* elabora. Los registros de años anteriores y los ya firmados por quien
* elaboró no bloquean la creación de uno nuevo.
*/
private static function pendienteReutilizable(int $formato, int $idLocalidad): ?RhFormatos
{
$conFirmaElaboro = RhFormatosFirma::where('tipo_firma', 'A')->pluck('id_formato');

return RhFormatos::where('id_localidad', $idLocalidad)
->where('formato', $formato)
->whereYear('fecha', '>=', (int)date('Y'))
->whereNotIn('id', $conFirmaElaboro)
->orderBy('id', 'asc')
->first();
}

/**
* Reutiliza el formato pendiente del mismo tipo, contexto y año que todavía
* no tiene la firma de quien elabora, o crea uno nuevo en status 0. Evita
* duplicados y permite que la información persista aunque el usuario no
* haya guardado todavía.
*/
public static function obtenerOCrearPendiente(int $formato, int $idLocalidad, ?int $idUsuario = null): int
{
$idUsuario = $idUsuario ?? (int)(Session::get('usuario')['id'] ?? 0);

$pendiente = self::pendienteReutilizable($formato, $idLocalidad);

if ($pendiente) {
return (int)$pendiente->id;
}

$row = RhFormatos::create([
'id_localidad' => $idLocalidad,
'id_usuario'   => $idUsuario,
'formato'      => $formato,
'fecha'        => Carbon::now(),
'status'       => 0,
]);

try {
$nombreUsuario = Session::get('usuario')['nombre'] ?? 'Desconocido';
$nombreFormato = self::FORMATOS[$formato] ?? 'Formato';
$folio = '00' . (int)$row->id;
$contexto = self::getNombreContexto($idLocalidad);

$mensaje = '📝 Se ha creado un nuevo formato en el apartado de <b>Formatos</b>:' . PHP_EOL . PHP_EOL
. '📄 <b>Formato:</b> ' . $nombreFormato . PHP_EOL
. '#️⃣ <b>No. de Control:</b> ' . $folio . PHP_EOL . PHP_EOL 
. '👤 <b>Responsable:</b> ' . htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8') . PHP_EOL 
. '📍 <b>Estación/Departamento:</b> ' . $contexto;
self::notificarUsuariosFormato((int)$row->id, $idUsuario, $mensaje);
} catch (\Throwable $e) {
error_log('Error notificando creación de formato: ' . $e->getMessage());
}

return (int)$row->id;
}

/**
* Guarda una fila de empleado de inmediato (formato 1 a 5) para que la
* información no dependa del estado del navegador.
*/
public static function agregarFila(int $formato, int $id, array $data, array $files = []): array
{
$parent = RhFormatos::find($id);
if (!$parent) {
return ['success' => false, 'message' => 'Formato no encontrado'];
}
if ((int)$parent->status >= 3) {
return ['success' => false, 'message' => 'No se puede agregar empleados a un formato finalizado'];
}
if (!self::puedeGestionarLocalidad((int)$parent->id_localidad)) {
return ['success' => false, 'message' => 'No tienes permiso para modificar este formato'];
}

$error = self::validarFila($formato, $data, $files);
if ($error !== '') {
return ['success' => false, 'message' => $error];
}

try {
// Los archivos del formato 1 llegan como "archivo_{campo}".
$filesRemap = [];
foreach ($files as $clave => $valor) {
if (str_starts_with((string)$clave, 'archivo_')) {
$filesRemap['detalle_archivo_0_' . substr($clave, 8)] = $valor;
} else {
$filesRemap[$clave] = $valor;
}
}

$saved = self::crearDetalleFormato($formato, $id, (int)$parent->id_localidad, $data, $filesRemap, 0);
$fila = self::filaDesdeModelo($formato, $saved);

try {
$sessionUsuario = Session::get('usuario');
$idUsuario = (int)($sessionUsuario['id'] ?? 0);
$nombreUsuario = $sessionUsuario['nombre'] ?? 'Desconocido';
$nombreFormato = self::FORMATOS[$formato] ?? 'Formato';
$folio = '00' . $id;
$nombreEmpleado = self::resolveNombreEmpleadoSimple($formato, $fila);
$contexto = self::getNombreContexto((int)$parent->id_localidad);

$mensaje = '➕ Se ha agregado un empleado al formato en el apartado de <b>Formatos</b>:' . PHP_EOL . PHP_EOL
. '📄 <b>Formato:</b> ' . $nombreFormato . PHP_EOL
. '#️⃣ <b>No. de Control:</b> ' . $folio . PHP_EOL . PHP_EOL
. '👤 <b>Responsable:</b> ' . htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8') . PHP_EOL
. '📍 <b>Estación/Departamento:</b> ' . $contexto . PHP_EOL;
self::notificarUsuariosFormato($id, $idUsuario, $mensaje);
} catch (\Throwable $e) {
error_log('Error notificando agregación de empleado: ' . $e->getMessage());
}

return [
'success' => true,
'message' => 'Empleado agregado correctamente',
'fila'    => $fila,
];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al agregar el empleado: ' . $e->getMessage()];
}
}

/**
* Elimina una fila de empleado específica de un formato 1 a 5.
*/
public static function eliminarFila(int $formato, int $id, int $filaId): array
{
$parent = RhFormatos::find($id);

if (!$parent) {
return [
'success' => false,
'message' => 'Formato no encontrado'
];
}

if ((int)$parent->status >= 3) {
return [
'success' => false,
'message' => 'No se puede quitar empleados de un formato finalizado'
];
}

if (!self::puedeGestionarLocalidad((int)$parent->id_localidad)) {
return [
'success' => false,
'message' => 'No tienes permiso para modificar este formato'
];
}

try {

$modelo = match ($formato) {
1 => RhFormatosAlta::class,
2 => RhFormatosBaja::class,
3 => RhFormatosFalta::class,
4 => RhFormatosRestructuracion::class,
5 => RhFormatosAjusteSalarial::class,
default => null,
};

if (!$modelo) {
return [
'success' => false,
'message' => 'Tipo de formato no válido'
];
}

$fila = $modelo::where('id', $filaId)
->where('id_formulario', $id)
->first();

if (!$fila) {
return [
'success' => false,
'message' => "No se encontró la fila. formato={$formato}, id_formulario={$id}, fila_id={$filaId}"
];
}

$nombreEmpleado = self::resolveNombreEmpleadoSimple($formato, $fila);
$fila->delete();

try {
$sessionUsuario = Session::get('usuario');
$idUsuario = (int)($sessionUsuario['id'] ?? 0);
$nombreUsuario = $sessionUsuario['nombre'] ?? 'Desconocido';
$nombreFormato = self::FORMATOS[$formato] ?? 'Formato';
$folio = '00' . $id;
$contexto = self::getNombreContexto((int)$parent->id_localidad);

$mensaje = '🗑️ Se ha eliminado un empleado del formato en el apartado de <b>Formatos</b>:' . PHP_EOL . PHP_EOL
. '📄 <b>Formato:</b> ' . $nombreFormato . PHP_EOL
. '#️⃣ <b>No. de Control:</b> ' . $folio . PHP_EOL . PHP_EOL
. '👤 <b>Responsable:</b> ' . htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8')  . PHP_EOL 
. '📍 <b>Estación/Departamento:</b> ' . $contexto ;
self::notificarUsuariosFormato($id, $idUsuario, $mensaje);
} catch (\Throwable $e) {
error_log('Error notificando eliminación de empleado: ' . $e->getMessage());
}

return [
'success' => true,
'message' => 'Empleado eliminado del formato'
];

} catch (\Exception $e) {

return [
'success' => false,
'message' => 'Error al eliminar el empleado: ' . $e->getMessage()
];
}
}

private static function contarFilasDetalle(int $formato, int $id): int
{
return match ($formato) {
1 => RhFormatosAlta::where('id_formulario', $id)->count(),
2 => RhFormatosBaja::where('id_formulario', $id)->count(),
3 => RhFormatosFalta::where('id_formulario', $id)->count(),
4 => RhFormatosRestructuracion::where('id_formulario', $id)->count(),
5 => RhFormatosAjusteSalarial::where('id_formulario', $id)->count(),
default => 0,
};
}

public static function editarFormato(int $formato, int $id, array $data, array $files = []): array
{
$formatoRow = RhFormatos::find($id);
if (!$formatoRow) {
return ['success' => false, 'message' => 'Formato no encontrado'];
}
if ((int)$formatoRow->status !== 0) {
return ['success' => false, 'message' => 'Solo se pueden editar formatos pendientes'];
}
if (!self::puedeGestionarLocalidad((int)$formatoRow->id_localidad)) {
return ['success' => false, 'message' => 'No tienes permiso para editar este formato'];
}

// La firma de quien elabora es obligatoria para guardar el formato.
if (trim((string)($data['firma_elaboro'] ?? '')) === '') {
return ['success' => false, 'message' => 'Es obligatorio capturar la firma de quien elabora'];
}

$formato = (int)$formatoRow->formato;
$idLocalidad = (int)$formatoRow->id_localidad;

if ($formato <= 5) {
if (self::contarFilasDetalle($formato, $id) <= 0) {
return ['success' => false, 'message' => 'Agrega al menos un empleado al formato'];
}
} else {
$error = self::validarFormatoSimple($formato, $data);
if ($error !== '') {
return ['success' => false, 'message' => $error];
}
}

try {
// Los formatos 1 a 5 persisten cada fila de empleado de inmediato
// (agregar-fila / eliminar-fila); aquí solo se guarda la firma.
if ($formato >= 6) {
self::guardarDetalleFormato($formato, $id, $idLocalidad, $data, $files);
}

$firmaOk = true;
$firmaElaboro = (string)($data['firma_elaboro'] ?? '');
if ($firmaElaboro !== '') {
$res = self::guardarFirmaImagen($id, (int)(Session::get('usuario')['id'] ?? 0), $firmaElaboro, 'A');
if ($res['success']) {
$formatoRow->status = 1;
$formatoRow->save();
} else {
$firmaOk = false;
}
}

$sessionUsuario = Session::get('usuario');
$nombreUsuario = $sessionUsuario['nombre'] ?? 'Desconocido';
$idUsuario = (int)($sessionUsuario['id'] ?? 0);
$nombreFormato = self::FORMATOS[$formato] ?? 'Formato';
$folio = '00' . $id;
$nombreEmpleados = self::resolveNombreEmpleado($formato, $id);
$contexto = self::getNombreContexto($idLocalidad);

$mensaje = '✏️ Se ha editado un formato en el apartado de <b>Formatos</b>:' . PHP_EOL . PHP_EOL
. '📄 <b>Formato:</b> ' . $nombreFormato . PHP_EOL
. '#️⃣ <b>No. de Control:</b> ' . $folio . PHP_EOL . PHP_EOL
. '👤 <b>Responsable:</b> ' . htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8') . PHP_EOL
. '📍 <b>Estación/Departamento:</b> ' . $contexto;

self::notificarUsuariosFormato($id, $idUsuario, $mensaje);

return [
'success' => true,
'message' => $firmaOk ? 'Formato actualizado correctamente' : 'Formato actualizado, pero no se pudo guardar la firma',
'id'      => $id,
];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al actualizar: ' . $e->getMessage()];
}
}

/* ============================================================
*  INTERNOS: encapsulan la lógica por formato.
* ============================================================ */

private static function resolveNombreEmpleado(int $formato, int $idFormato): string
{
$nombres = match ($formato) {
1 => RhFormatosAlta::where('id_formulario', $idFormato)->orderBy('id')->pluck('nombre')->toArray(),
2 => array_map(fn($x) => self::nombrePorId($x), RhFormatosBaja::where('id_formulario', $idFormato)->orderBy('id')->pluck('id_personal')->toArray()),
3 => array_map(fn($x) => self::nombrePorId($x), RhFormatosFalta::where('id_formulario', $idFormato)->orderBy('id')->pluck('id_personal')->toArray()),
4 => array_map(fn($x) => self::nombrePorId($x), RhFormatosRestructuracion::where('id_formulario', $idFormato)->orderBy('id')->pluck('id_personal')->toArray()),
5 => array_map(fn($x) => self::nombrePorId($x), RhFormatosAjusteSalarial::where('id_formulario', $idFormato)->orderBy('id')->pluck('id_personal')->toArray()),
6 => [self::nombrePorId(RhFormatosVacaciones::where('id_formulario', $idFormato)->value('id_usuario'))],
7 => array_map(fn($x) => self::nombrePorId($x), RhFormatosPrimaVacacional::where('id_formulario', $idFormato)->orderBy('id')->pluck('id_personal')->toArray()),
default => [],
};

return self::unirNombres($nombres);
}

private static function resolveNombreEmpleadoSimple(int $formato, $fila): string
{
$nombre = match ($formato) {
1 => trim((string)($fila->nombre ?? '')),
default => self::nombrePorId((int)($fila->id_personal ?? 0)),
};

return $nombre !== '' ? $nombre : 'Sin información';
}

private static function unirNombres(array $nombres): string
{
$nombres = array_values(array_filter(array_map('trim', $nombres)));

if (empty($nombres)) {
return 'Sin información';
}

return implode(', ', $nombres);
}

private static function ejecutarOperacionAutorizacion(RhFormatos $formato): void
{
switch ((int)$formato->formato) {
case 1:
self::aplicarAltaPersonal((int)$formato->id);
break;
case 2:
self::aplicarBajaPersonal((int)$formato->id);
break;
case 4:
self::aplicarReestructuracionPersonal((int)$formato->id);
break;
case 5:
self::aplicarAjusteSalarial((int)$formato->id);
break;
}
}

private static function aplicarAltaPersonal(int $idFormato): void
{
$filas = RhFormatosAlta::where('id_formulario', $idFormato)->orderBy('id')->get();

foreach ($filas as $fila) {
$nombre = trim((string)$fila->nombre);
$idEstacion = (int)$fila->id_estacion;

$duplicado = RhPersonal::where('nombre_completo', $nombre)
->where('id_estacion', $idEstacion)
->where('estado', 1)
->exists();

if ($duplicado) {
continue;
}

$datosPersonal = [
'id_estacion'     => $idEstacion,
'fecha_ingreso'   => $fila->fecha_ingreso ? $fila->fecha_ingreso->format('Y-m-d') : null,
'nombre_completo' => $nombre,
'puesto'          => (int)$fila->puesto,
'sd'              => (float)$fila->sd,
'estado'          => 1,
];

foreach (self::MAPEO_DOCUMENTOS_PERSONAL as $campo => $info) {
$valorGuardado = trim((string)($fila->{$campo} ?? ''));
if ($valorGuardado !== '') {
$nombreArchivo = self::copiarArchivoADocumentosPersonal($valorGuardado, $info, $nombre);
$datosPersonal[$campo] = $nombreArchivo !== '' ? $nombreArchivo : $valorGuardado;
} else {
$datosPersonal[$campo] = '';
}
}

$personal = RhPersonal::create($datosPersonal);

RhPersonalAcceso::create([
'id_personal' => (int)$personal->id,
'huella'      => '',
'pin'         => 0,
]);
}
}

private static function copiarArchivoADocumentosPersonal(string $rutaOrigen, array $infoDocumento, string $nombrePersonal): string
{
$nombreBase = basename($rutaOrigen);
$basePath = dirname(__DIR__, 2) . '/public';
$sourcePath = $basePath . '/uploads/archivos/formatos/alta/' . $nombreBase;

if (!file_exists($sourcePath)) {
return '';
}

$targetDir = $basePath . '/uploads/archivos/documentos-personal/' . $infoDocumento['folder'] . '/';
if (!is_dir($targetDir)) {
@mkdir($targetDir, 0775, true);
}

$aleatorio1 = rand(1, 1000000);
$aleatorio2 = rand(1000, 9999);
$ext = strtolower(pathinfo($nombreBase, PATHINFO_EXTENSION));
$nuevoNombre = $aleatorio1 . '-' . $infoDocumento['prefix'] . '-' . $nombrePersonal . '-' . $aleatorio2 . '.' . $ext;

if (@copy($sourcePath, $targetDir . $nuevoNombre)) {
return $nuevoNombre;
}

return '';
}

private static function aplicarBajaPersonal(int $idFormato): void
{
$filas = RhFormatosBaja::where('id_formulario', $idFormato)->orderBy('id')->get();

foreach ($filas as $fila) {
$idPersonal = (int)$fila->id_personal;
$personal = RhPersonal::find($idPersonal);
if (!$personal) {
continue;
}

if ((int)$personal->estado === 1) {
$personal->estado = 0;
$personal->save();
}

$yaRegistrada = RhPersonalBaja::where('id_personal', $idPersonal)
->where('estado_proceso', 0)
->exists();

if ($yaRegistrada) {
continue;
}

RhPersonalBaja::create([
'id_personal'    => $idPersonal,
'fecha_baja'     => $fila->fecha_baja ? $fila->fecha_baja->format('Y-m-d') : null,
'motivo'         => $fila->motivo ?? '',
'detalle'        => trim((string)($fila->detalle ?? '')) === '' ? 'Sin Información' : $fila->detalle,
'estado_proceso' => 0,
]);
}
}

private static function aplicarReestructuracionPersonal(int $idFormato): void
{
$filas = RhFormatosRestructuracion::where('id_formulario', $idFormato)->orderBy('id')->get();

foreach ($filas as $fila) {
$idPersonal = (int)$fila->id_personal;
$idEstacionCambio = (int)$fila->id_estacion_cambio;

$personal = RhPersonal::find($idPersonal);
if (!$personal) {
continue;
}

if ((int)$personal->id_estacion === $idEstacionCambio) {
continue;
}

$personal->id_estacion = $idEstacionCambio;
$personal->save();
}
}

private static function aplicarAjusteSalarial(int $idFormato): void
{
$filas = RhFormatosAjusteSalarial::where('id_formulario', $idFormato)->orderBy('id')->get();

foreach ($filas as $fila) {
$idPersonal = (int)$fila->id_personal;
$salarioAjustado = (float)$fila->salario_ajustado;

$personal = RhPersonal::find($idPersonal);
if (!$personal) {
continue;
}

if (abs((float)$personal->sd - $salarioAjustado) < 0.001) {
continue;
}

$personal->sd = $salarioAjustado;
$personal->save();
}
}

private static function nombrePorId($idPersonal): string
{
if (empty($idPersonal)) {
return '';
}

$personal = RhPersonal::find((int)$idPersonal);
if ($personal) {
return $personal->nombre_completo ?? '';
}

$usuario = Usuario::find((int)$idPersonal);
return $usuario ? $usuario->nombre : '';
}

private static function camposDetalle(int $formato, int $id): array
{
$campos = [];

switch ($formato) {
case 1:
$row = RhFormatosAlta::where('id_formulario', $id)->first();
if ($row) {
$puesto = RhPuestos::find((int)$row->puesto);
$campos[] = ['label' => 'Nombre', 'value' => $row->nombre, 'type' => 'text'];
$campos[] = ['label' => 'Fecha de ingreso', 'value' => $row->fecha_ingreso ? formatearFecha($row->fecha_ingreso) : '', 'type' => 'date'];
$campos[] = ['label' => 'Puesto', 'value' => $puesto ? $puesto->puesto : '', 'type' => 'text'];
$campos[] = ['label' => 'Salario diario', 'value' => number_format((float)$row->sd, 2), 'type' => 'money'];
}
break;

case 2:
$row = RhFormatosBaja::where('id_formulario', $id)->first();
if ($row) {
$campos[] = ['label' => 'Empleado', 'value' => self::nombrePorId($row->id_personal), 'type' => 'text'];
$campos[] = ['label' => 'Fecha de baja', 'value' => $row->fecha_baja ? formatearFecha($row->fecha_baja) : '', 'type' => 'date'];
$campos[] = ['label' => 'Motivo', 'value' => $row->motivo, 'type' => 'text'];
$campos[] = ['label' => 'Detalle', 'value' => $row->detalle, 'type' => 'text'];
}
break;

case 3:
$row = RhFormatosFalta::where('id_formulario', $id)->first();
if ($row) {
$campos[] = ['label' => 'Empleado', 'value' => self::nombrePorId($row->id_personal), 'type' => 'text'];
$campos[] = ['label' => 'Día de falta', 'value' => $row->dias_falta ? formatearFecha($row->dias_falta) : '', 'type' => 'date'];
}
break;

case 4:
$row = RhFormatosRestructuracion::where('id_formulario', $id)->first();
if ($row) {
$campos[] = ['label' => 'Empleado', 'value' => self::nombrePorId($row->id_personal), 'type' => 'text'];
$campos[] = ['label' => 'Nueva ubicación', 'value' => self::resolveNombreLocalidad((int)$row->id_estacion_cambio), 'type' => 'text'];
$campos[] = ['label' => 'Fecha', 'value' => $row->fecha ? formatearFecha($row->fecha) : '', 'type' => 'date'];
}
break;

case 5:
$row = RhFormatosAjusteSalarial::where('id_formulario', $id)->first();
if ($row) {
$campos[] = ['label' => 'Empleado', 'value' => self::nombrePorId($row->id_personal), 'type' => 'text'];
$campos[] = ['label' => 'Salario actual', 'value' => number_format((float)$row->salario_actual, 2), 'type' => 'money'];
$campos[] = ['label' => 'Salario ajustado', 'value' => number_format((float)$row->salario_ajustado, 2), 'type' => 'money'];
$campos[] = ['label' => 'Fecha de aplicación', 'value' => $row->fecha_aplicacion ? formatearFecha($row->fecha_aplicacion) : '', 'type' => 'date'];
}
break;

case 6:
$row = RhFormatosVacaciones::where('id_formulario', $id)->first();
if ($row) {
$campos[] = ['label' => 'Empleado', 'value' => self::nombrePorId($row->id_usuario), 'type' => 'text'];
$campos[] = ['label' => 'Días', 'value' => (int)$row->num_dias, 'type' => 'text'];
$campos[] = ['label' => 'Inicio', 'value' => $row->fecha_inicio ? formatearFecha($row->fecha_inicio) : '', 'type' => 'date'];
$campos[] = ['label' => 'Término', 'value' => $row->fecha_termino ? formatearFecha($row->fecha_termino) : '', 'type' => 'date'];
$campos[] = ['label' => 'Regreso', 'value' => $row->fecha_regreso ? formatearFecha($row->fecha_regreso) : '', 'type' => 'date'];
$campos[] = ['label' => 'Observaciones', 'value' => $row->observaciones, 'type' => 'text'];
}
break;

case 7:
$row = RhFormatosPrimaVacacional::where('id_formulario', $id)->first();
if ($row) {
$campos[] = ['label' => 'Empleado', 'value' => self::nombrePorId($row->id_personal), 'type' => 'text'];
$campos[] = ['label' => 'Periodo', 'value' => (int)$row->periodo, 'type' => 'text'];
}
break;
}

return $campos;
}

private static function archivosDetalle(int $formato, int $id): array
{
if ($formato !== 1) {
return [];
}

$rows = RhFormatosAlta::where('id_formulario', $id)->orderBy('id')->get();
if ($rows->isEmpty()) {
return [];
}

$etiquetas = [
'curriculum' => 'Currículum',
'ine' => 'INE',
'acta_nacimiento' => 'Acta de nacimiento',
'nss' => 'Comprobante de afiliación IMSS',
'c_domicilio' => 'Comprobante domicilio',
'c_estudios' => 'Comprobante estudios',
'c_recomendacion' => 'Carta recomendación',
'curp' => 'CURP',
'rfc' => 'Constancia de situación fiscal (RFC)',
'c_antecedentes' => 'Carta antecedentes',
'a_infonavit' => 'Aviso de retención INFONAVIT',
];

$archivos = [];
$multiple = $rows->count() > 1;
foreach ($rows as $row) {
$prefijo = $multiple ? '[' . trim((string)$row->nombre) . '] ' : '';
foreach (self::CAMPOS_ARCHIVO_ALTA as $campo) {
if (!empty($row->{$campo})) {
$archivos[] = ['label' => $prefijo . ($etiquetas[$campo] ?? $campo), 'ruta' => $row->{$campo}];
}
}
}

return $archivos;
}

/**
* Tabla en formato tipo carta para el detalle. Cada celda es
* ['value' => string, 'header' => bool, 'colspan' => int].
*/
private static function tablaDetalleFormato(int $formato, int $id): array
{
switch ($formato) {
case 1:
$rows = RhFormatosAlta::where('id_formulario', $id)->get();
$headers = ['#', 'Empleado', 'Estación', 'Puesto', 'Alta', 'Salario'];
$etiquetasArchivos = [
'curriculum' => 'Currículum',
'ine' => 'INE',
'acta_nacimiento' => 'Acta de nacimiento',
'nss' => 'Comprobante de afiliación IMSS',
'c_domicilio' => 'Comprobante domicilio',
'c_estudios' => 'Comprobante estudios',
'c_recomendacion' => 'Carta recomendación',
'curp' => 'CURP',
'rfc' => 'Constancia de situación fiscal (RFC)',
'c_antecedentes' => 'Carta antecedentes',
'a_infonavit' => 'Aviso de retención INFONAVIT',
];
$data = [];
$i = 1;
foreach ($rows as $r) {
$puesto = RhPuestos::find((int)$r->puesto);
$archivosEmpleado = [];
foreach (self::CAMPOS_ARCHIVO_ALTA as $campo) {
if (!empty($r->{$campo})) {
$archivosEmpleado[] = [
'campo' => $campo,
'label' => $etiquetasArchivos[$campo] ?? $campo,
'archivo' => basename($r->{$campo}),
'ruta' => $r->{$campo},
];
}
}
$data[] = [
['value' => (string)$i++],
['value' => $r->nombre],
['value' => self::resolveNombreLocalidad((int)$r->id_estacion)],
['value' => $puesto ? $puesto->puesto : ''],
['value' => $r->fecha_ingreso ? formatearFecha($r->fecha_ingreso) : ''],
['value' => number_format((float)$r->sd, 2)],
];
}
return ['headers' => $headers, 'rows' => $data];

case 2:
$rows = RhFormatosBaja::where('id_formulario', $id)->get();
$headers = ['#', 'Empleado', 'Estación', 'Fecha de baja', 'Motivo', 'Detalle'];
$data = [];
$i = 1;
foreach ($rows as $r) {
$data[] = [
['value' => (string)$i++],
['value' => self::nombrePorId($r->id_personal)],
['value' => self::resolveNombreLocalidad((int)$r->id_estacion)],
['value' => $r->fecha_baja ? formatearFecha($r->fecha_baja) : ''],
['value' => $r->motivo],
['value' => $r->detalle],
];
}
return ['headers' => $headers, 'rows' => $data];

case 3:
$rows = RhFormatosFalta::where('id_formulario', $id)->get();
$headers = ['#', 'Colaborador', 'Día faltante', 'Estación'];
$data = [];
$i = 1;
foreach ($rows as $r) {
$data[] = [
['value' => (string)$i++],
['value' => self::nombrePorId($r->id_personal)],
['value' => $r->dias_falta ? formatearFecha($r->dias_falta) : ''],
['value' => self::resolveNombreLocalidad((int)$r->id_estacion)],
];
}
return ['headers' => $headers, 'rows' => $data];

case 4:
$rows = RhFormatosRestructuracion::where('id_formulario', $id)->get();
$headers = ['#', 'Empleado', 'Estación actual', 'Cambio a', 'Fecha de aplicación'];
$data = [];
$i = 1;
foreach ($rows as $r) {
$data[] = [
['value' => (string)$i++],
['value' => self::nombrePorId($r->id_personal)],
['value' => self::resolveNombreLocalidad((int)$r->id_estacion)],
['value' => self::resolveNombreLocalidad((int)$r->id_estacion_cambio)],
['value' => $r->fecha ? formatearFecha($r->fecha) : ''],
];
}
return ['headers' => $headers, 'rows' => $data];

case 5:
$rows = RhFormatosAjusteSalarial::where('id_formulario', $id)->get();
$headers = ['#', 'Colaborador', 'Puesto', 'Salario Diario', 'Ajuste a', 'Aplicar a partir del'];
$data = [];
$i = 1;
foreach ($rows as $r) {
$personal = RhPersonal::find((int)$r->id_personal);
$data[] = [
['value' => (string)$i++],
['value' => self::nombrePorId($r->id_personal)],
['value' => $personal && $personal->puesto ? (RhPuestos::find((int)$personal->puesto)->puesto ?? '') : ''],
['value' => number_format((float)$r->salario_actual, 2)],
['value' => number_format((float)$r->salario_ajustado, 2)],
['value' => $r->fecha_aplicacion ? formatearFecha($r->fecha_aplicacion) : ''],
];
}
return ['headers' => $headers, 'rows' => $data];

case 6:
$r = RhFormatosVacaciones::where('id_formulario', $id)->first();
$parent = RhFormatos::find($id);
$area = $parent ? self::resolveNombreLocalidad((int)$parent->id_localidad) : '';
return [
'headers' => ['Área o Departamento', 'Nombre completo', 'Número de días a disfrutar'],
'rows'    => [
[
['value' => $area],
['value' => self::nombrePorId($r->id_usuario ?? 0)],
['value' => $r ? (string)(int)$r->num_dias : ''],
],
[
['value' => 'Del:', 'header' => true],
['value' => 'Al:', 'header' => true],
['value' => 'Regresando el:', 'header' => true],
],
[
['value' => $r && $r->fecha_inicio ? formatearFecha($r->fecha_inicio) : ''],
['value' => $r && $r->fecha_termino ? formatearFecha($r->fecha_termino) : ''],
['value' => $r && $r->fecha_regreso ? formatearFecha($r->fecha_regreso) : ''],
],
[
['value' => 'Observaciones:', 'header' => true, 'colspan' => 3],
],
[
['value' => $r ? $r->observaciones : '', 'colspan' => 3],
],
],
];

case 7:
$rows = RhFormatosPrimaVacacional::where('id_formulario', $id)->get();
$headers = ['#', 'Colaborador', 'Fecha de ingreso', 'Estación / Departamento', 'Periodo'];
$data = [];
$i = 1;
foreach ($rows as $r) {
$personal = RhPersonal::find((int)$r->id_personal);
$data[] = [
['value' => (string)$i++],
['value' => self::nombrePorId($r->id_personal)],
['value' => $personal && $personal->fecha_ingreso ? formatearFecha($personal->fecha_ingreso) : ''],
['value' => self::resolveNombreLocalidad((int)$r->id_estacion)],
['value' => (string)(int)$r->periodo],
];
}
return ['headers' => $headers, 'rows' => $data];

default:
return ['headers' => [], 'rows' => []];
}
}

private static function eliminarDetalleFormato(int $formato, int $id): void
{
switch ($formato) {
case 1:
RhFormatosAlta::where('id_formulario', $id)->delete();
break;
case 2:
RhFormatosBaja::where('id_formulario', $id)->delete();
break;
case 3:
RhFormatosFalta::where('id_formulario', $id)->delete();
break;
case 4:
RhFormatosRestructuracion::where('id_formulario', $id)->delete();
break;
case 5:
RhFormatosAjusteSalarial::where('id_formulario', $id)->delete();
break;
case 6:
RhFormatosVacaciones::where('id_formulario', $id)->delete();
break;
case 7:
RhFormatosPrimaVacacional::where('id_formulario', $id)->delete();
break;
}
}

private static function guardarDetalleFormato(int $formato, int $id, int $idLocalidad, array $data, array $files): void
{
switch ($formato) {
case 1:
case 2:
case 3:
case 4:
case 5:
$rows = isset($data['detalle_json']) ? (json_decode((string)$data['detalle_json'], true) ?: []) : [];

self::eliminarDetalleFormato($formato, $id);

foreach (array_values($rows) as $indice => $row) {
self::crearDetalleFormato($formato, $id, $idLocalidad, (array)$row, $files, $indice);
}
break;

case 6:
$valores = [
'id_formulario' => $id,
'id_usuario'    => (int)($data['id_personal'] ?? 0),
'num_dias'      => (int)($data['num_dias'] ?? 0),
'fecha_inicio'  => $data['fecha_inicio'] ?? null,
'fecha_termino' => $data['fecha_termino'] ?? null,
'fecha_regreso' => $data['fecha_regreso'] ?? null,
'observaciones' => $data['observaciones'] ?? '',
];
RhFormatosVacaciones::where('id_formulario', $id)->update($valores);
break;

case 7:
$valores = [
'id_formulario' => $id,
'id_personal'   => (int)($data['id_personal'] ?? 0),
'id_estacion'   => $idLocalidad,
'periodo'       => (int)($data['periodo'] ?? 0),
];
RhFormatosPrimaVacacional::where('id_formulario', $id)->update($valores);
break;
}
}

/**
* Crea una fila de detalle (empleado) para formatos multi-empleado (1 a 5).
* Los archivos del formato 1 llegan por $files["detalle_archivo_{indice}_{campo}"].
*/
private static function crearDetalleFormato(int $formato, int $id, int $idLocalidad, array $row, array $files, int $indice)
{
$created = null;
switch ($formato) {
case 1:
$valores = [
'id_formulario' => $id,
'id_estacion'   => $idLocalidad,
'fecha_ingreso' => $row['fecha_ingreso'] ?? null,
'nombre'        => $row['nombre'] ?? '',
'puesto'        => (int)($row['puesto'] ?? 0),
'sd'            => (float)($row['sd'] ?? 0),
];

$existe = is_array($row['_existe'] ?? null) ? $row['_existe'] : [];
foreach (self::CAMPOS_ARCHIVO_ALTA as $campo) {
$valores[$campo] = $existe[$campo] ?? '';
$fileKey = 'detalle_archivo_' . $indice . '_' . $campo;
if (!empty($files[$fileKey]['name'])) {
$valores[$campo] = self::guardarArchivo($files[$fileKey], 'alta');
}
}

$created = RhFormatosAlta::create($valores);
break;

case 2:
$created = RhFormatosBaja::create([
'id_formulario' => $id,
'id_personal'   => (int)($row['id_personal'] ?? 0),
'id_estacion'   => $idLocalidad,
'fecha_baja'    => $row['fecha_baja'] ?? null,
'motivo'        => $row['motivo'] ?? '',
'detalle'       => $row['detalle'] ?? '',
]);
break;

case 3:
$created = RhFormatosFalta::create([
'id_formulario' => $id,
'id_personal'   => (int)($row['id_personal'] ?? 0),
'id_estacion'   => $idLocalidad,
'dias_falta'    => $row['dias_falta'] ?? null,
]);
break;

case 4:
$created = RhFormatosRestructuracion::create([
'id_formulario'      => $id,
'id_personal'        => (int)($row['id_personal'] ?? 0),
'id_estacion'        => $idLocalidad,
'id_estacion_cambio' => (int)($row['id_estacion_cambio'] ?? 0),
'fecha'              => $row['fecha'] ?? null,
]);
break;

case 5:
$created = RhFormatosAjusteSalarial::create([
'id_formulario'    => $id,
'id_personal'      => (int)($row['id_personal'] ?? 0),
'id_estacion'      => $idLocalidad,
'salario_actual'   => (float)($row['salario_actual'] ?? 0),
'salario_ajustado' => (float)($row['salario_ajustado'] ?? 0),
'fecha_aplicacion' => $row['fecha_aplicacion'] ?? null,
]);
break;
}

return $created;
}

/**
* Guarda una firma dibujada (PNG base64) en el servidor y registra la
* fila correspondiente en op_rh_formatos_firma para el tipo indicado (A o D).
*/
private static function guardarFirmaImagen(int $id, int $idUsuario, string $base64, string $tipo): array
{
if (RhFormatosFirma::where('id_formato', $id)->where('tipo_firma', $tipo)->exists()) {
return ['success' => false, 'message' => 'La firma ya fue registrada'];
}

$img = str_replace('data:image/png;base64,', '', (string)$base64);
$img = str_replace('data:image/jpeg;base64,', '', $img);
if ($img === '') {
return ['success' => false, 'message' => 'Falta la firma'];
}

$fileData = base64_decode($img, true);
if ($fileData === false || $fileData === '') {
return ['success' => false, 'message' => 'Firma no válida'];
}

$directorio = dirname(__DIR__, 2) . '/public/uploads/firmas/formatos';
if (!is_dir($directorio)) {
@mkdir($directorio, 0775, true);
}

$fileName = uniqid() . '.png';
if (!@file_put_contents($directorio . '/' . $fileName, $fileData)) {
return ['success' => false, 'message' => 'Error al guardar la firma'];
}

try {
RhFormatosFirma::create([
'id_formato' => $id,
'id_usuario' => $idUsuario,
'tipo_firma' => $tipo,
'firma'      => $fileName,
'fecha'      => Carbon::now(),
]);
} catch (\Exception $e) {
@unlink($directorio . '/' . $fileName);
return ['success' => false, 'message' => 'Error al guardar la firma: ' . $e->getMessage()];
}

return ['success' => true];
}

private static function guardarArchivo(?array $file, string $subcarpeta): string
{
if (empty($file['name']) || empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
return '';
}

$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$permitidas = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
if (!in_array($extension, $permitidas, true)) {
return '';
}

$directorio = dirname(__DIR__, 2) . '/public/uploads/archivos/formatos/' . $subcarpeta;
if (!is_dir($directorio)) {
@mkdir($directorio, 0775, true);
}

$nombre = uniqid('fmt_' . $subcarpeta . '_', true) . '.' . $extension;
if (@move_uploaded_file($file['tmp_name'], $directorio . '/' . $nombre)) {
return self::DIRECTORIO_UPLOADS . $subcarpeta . '/' . $nombre;
}

return '';
}

private static function notificarUsuariosFormato(int $id, int $idAutor, string $mensaje): void
{
$destinos = self::destinosFormato($id, $idAutor);

if (!empty($destinos)) {
$telegram = new TelegramService();
$telegram->sendMessageToMultiple($destinos, $mensaje);
}
}

/**
* Usuarios participantes de un formato (comentarios, firmas o tokens)
* excluyendo al autor de la operación.
*/
private static function destinosFormato(int $id, int $idAutor): array
{
$ids = RhFormatosComentario::where('id_formato', $id)->pluck('id_usuario')->toArray();
$idsFirmas = RhFormatosFirma::where('id_formato', $id)->pluck('id_usuario')->toArray();
$idsTokens = RhFormatosToken::where('id_formato', $id)->pluck('id_usuario')->toArray();

return array_values(array_unique(array_filter(array_merge($ids, $idsFirmas, $idsTokens), function ($uid) use ($idAutor) {
return (int)$uid !== (int)$idAutor;
})));
}
}
