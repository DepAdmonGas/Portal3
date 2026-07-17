<?php
namespace App\Services;

use App\Models\Operativo\SolicitudVale;
use App\Models\Operativo\SolicitudValeDocumento;
use App\Models\Operativo\SolicitudValeComentario;
use App\Models\Usuario;
use App\Models\Estacion;
use App\Core\Auth;
use App\Core\Session;
use App\Services\ModuloDptoOperativoService;
use App\Services\TelegramService;
use Carbon\Carbon;

class SolicitudValeService
{
public static function getPermisos(): array
{
$usuario = Auth::user();
$sessionUsuario = Session::get('usuario');

$idUsuario = (int)($sessionUsuario['id'] ?? 0);
$idPuesto = (int)($usuario->id_puesto ?? 0);
$idEstacion = (int)($sessionUsuario['id_estacion'] ?? 0);

if ($idUsuario === 292) {
$idEstacion = 8;
}

$esDireccion = $idPuesto === 13;
$esEncargado = $idPuesto === 6 || $idPuesto === 7;
$esGestoria = $idPuesto === 5;
$esAsistente = $idPuesto === 14;

$puedeCrear = $idPuesto !== 6 && $idPuesto !== 7;
$puedeEditar = true;
$puedeEliminar = ($esDireccion || $esEncargado || $esAsistente) && !$esGestoria;

$permisosDb = ModuloDptoOperativoService::permisosSesion('corporativo');
$dbTieneConfig = !empty($permisosDb);
if ($dbTieneConfig) {
$puedeEditar = !empty($permisosDb['editar']);
$puedeEliminar = !empty($permisosDb['eliminar']);
}

$puedeEditar = ($idUsuario === 627);
$puedeEliminar = ($idUsuario === 627);

return [
'id_usuario' => (int)($sessionUsuario['id'] ?? 0),
'id_estacion' => $idEstacion,
'id_puesto' => $idPuesto,
'esDireccion' => $esDireccion,
'esEncargado' => $esEncargado,
'esGestoria' => $esGestoria,
'esAsistente' => $esAsistente,
'puedeCrear' => $puedeCrear,
'puedeEditar' => $puedeEditar,
'puedeEliminar' => $puedeEliminar,
];
}

public static function getRecord(int $id): ?array
{
$r = SolicitudVale::find($id);
if (!$r) return null;
return [
'id_estacion' => $r->id_estacion,
'year' => $r->id_year,
'mes' => $r->id_mes,
'folio' => $r->folio,
'cuenta' => $r->cuenta,
];
}

public static function getDetalle(int $id): ?array
{
$r = SolicitudVale::find($id);
if (!$r) return null;

$estacionNombre = '';
if ($r->id_estacion && $r->id_estacion > 0) {
$est = Estacion::find($r->id_estacion);
$estacionNombre = $est ? $est->nombre : '';
}

return [
'id' => $r->id,
'folio' => $r->folio,
'fecha' => $r->fecha ? $r->fecha->format('Y-m-d') : '',
'hora' => $r->hora ? date('g:i a', strtotime($r->hora)) : '',
'monto' => (float)($r->monto ?? 0),
'moneda' => $r->moneda ?? 'MXN',
'concepto' => $r->concepto ?? '',
'solicitante' => $r->solicitante ?? '',
'autorizado_por' => $r->autorizado_por ?? '',
'metodo_autorizacion' => $r->metodo_autorizacion ?? '',
'observaciones' => $r->observaciones ?? '',
'id_estacion' => (int)($r->id_estacion ?? 0),
'estacion_nombre' => $estacionNombre,
'cuenta' => $r->cuenta ?? '',
'depto' => (int)($r->depto ?? 0),
'id_year' => $r->id_year,
'id_mes' => $r->id_mes,
'status' => (int)($r->status ?? 0),
];
}

public static function getData(int $idYear, int $idMes): array
{
$logFile = 'C:/xampp/htdocs/Portal3/storage/logs/debug_sv.log';

$sessionUsuario = Session::get('usuario');
$idUsuario = (int)($sessionUsuario['id'] ?? 0);
$idEstacionSession = (int)($sessionUsuario['id_estacion'] ?? 0);
if ($idEstacionSession === 0) {
$idEstacionSession = 8;
}
$idPuesto = (int)(Auth::user()->id_puesto ?? 0);

if ($idUsuario === 292) {
$idEstacionSession = 8;
$idPuesto = 3;
}

file_put_contents($logFile, date('Y-m-d H:i:s') . " === getData === idYear=$idYear idMes=$idMes idUsuario=$idUsuario idEstacionSession=$idEstacionSession idPuesto=$idPuesto\n", FILE_APPEND);

$query = SolicitudVale::where('id_year', $idYear)->where('id_mes', $idMes);

if ($idEstacionSession != 8) {
file_put_contents($logFile, "  FILTER: id_estacion = $idEstacionSession\n", FILE_APPEND);
$query->where('id_estacion', $idEstacionSession);
} else {
file_put_contents($logFile, "  NO FILTER (station 8)\n", FILE_APPEND);
}

$sql = $query->toSql();
$bindings = $query->getBindings();
file_put_contents($logFile, "  SQL: $sql\n", FILE_APPEND);
file_put_contents($logFile, "  Bindings: " . json_encode($bindings) . "\n", FILE_APPEND);

$records = $query->orderBy('fecha', 'desc')->orderBy('status', 'asc')->get();

file_put_contents($logFile, "  Records count: " . count($records) . "\n", FILE_APPEND);
$ids = [];
foreach ($records as $r) {
$ids[] = $r->id;
}
file_put_contents($logFile, "  Record IDs: " . implode(',', $ids) . "\n", FILE_APPEND);

$data = [];
foreach ($records as $r) {
$commentCount = SolicitudValeComentario::where('id_solicitud', $r->id)->count();
$estacionNombre = '';

if ($r->id_estacion && $r->id_estacion > 0) {
$est = Estacion::find($r->id_estacion);
$estacionNombre = $est ? $est->nombre : '';
}

$cargoCuentaDisplay = ($r->id_estacion && $r->id_estacion > 0) ? $estacionNombre : ($r->cuenta ?? '');

$data[] = [
'id' => $r->id,
'folio' => $r->folio,
'folio_display' => '00' . $r->folio,
'fecha_raw' => $r->fecha ? $r->fecha->format('Y-m-d') : '',
'hora_raw' => $r->hora ? $r->hora->format('H:i:s') : '',
'fecha' => $r->fecha ? formatearFecha($r->fecha->format('Y-m-d')) : '',
'hora' => $r->hora ? date('g:i a', strtotime($r->hora)) : '',
'monto' => (float)($r->monto ?? 0),
'moneda' => $r->moneda ?? 'MXN',
'concepto' => $r->concepto ?? '',
'solicitante' => $r->solicitante ?? '',
'autorizado_por' => $r->autorizado_por ?? '',
'metodo_autorizacion' => $r->metodo_autorizacion ?? '',
'observaciones' => $r->observaciones ?? '',
'id_estacion' => (int)($r->id_estacion ?? 0),
'estacion_nombre' => $estacionNombre,
'cuenta' => $r->cuenta ?? '',
'cargo_cuenta_display' => $cargoCuentaDisplay,
'status' => (int)($r->status ?? 0),
'total_comentarios' => $commentCount,
];
}
file_put_contents($logFile, "  Data count: " . count($data) . "\n", FILE_APPEND);
return $data;
}

public static function add(array $input): ?int
{
$logFile = 'C:/xampp/htdocs/Portal3/storage/logs/debug_sv.log';

$usuario = Auth::user();
$sessionUsuario = Session::get('usuario');
$idUsuario = (int)($sessionUsuario['id'] ?? 0);
$idEstacionSession = (int)($sessionUsuario['id_estacion'] ?? 0);
$idPuesto = (int)($usuario->id_puesto ?? 0);

file_put_contents($logFile, date('Y-m-d H:i:s') . " ADD: user=$idUsuario session_est=$idEstacionSession auth_puesto=$idPuesto\n", FILE_APPEND);

if ($idUsuario === 292) {
$idEstacionSession = 8;
$idPuesto = 3;
file_put_contents($logFile, "  FORCED: idEstacionSession=8 idPuesto=3 (user 292)\n", FILE_APPEND);
}

file_put_contents($logFile, "  POST: departamento='" . ($input['departamento'] ?? 'NULL') . "' estacion='" . ($input['estacion'] ?? 'NULL') . "' cuenta='" . ($input['cuenta'] ?? 'NULL') . "' fecha='" . ($input['fecha'] ?? 'NULL') . "'\n", FILE_APPEND);

$last = SolicitudVale::orderBy('id', 'desc')->first();
$newFolio = $last ? $last->folio + 1 : 1;

if ($idEstacionSession == 8) {
$estacionDestino = !empty($input['estacion']) ? (int)$input['estacion'] : 0;
$cuentaTexto = !empty($input['cuenta']) ? $input['cuenta'] : '';
} else {
$estacionDestino = $idEstacionSession;
$cuentaTexto = '';
}

$depto = !empty($input['departamento']) ? (int)$input['departamento'] : $idPuesto;
file_put_contents($logFile, "  RESULT: estacionDestino=$estacionDestino cuentaTexto='$cuentaTexto' depto=$depto folio=$newFolio\n", FILE_APPEND);

$record = SolicitudVale::create([
'id_year' => (int)$input['id_year'],
'id_mes' => (int)$input['id_mes'],
'id_estacion' => $estacionDestino ?: 0,
'cuenta' => $cuentaTexto,
'id_usuario' => $idUsuario,
'folio' => $newFolio,
'fecha' => $input['fecha'] ?? null,
'hora' => date('H:i:s'),
'monto' => (float)($input['monto'] ?? 0),
'moneda' => $input['moneda'] ?? 'MXN',
'concepto' => $input['concepto'] ?? '',
'solicitante' => $input['solicitante'] ?? '',
'autorizado_por' => $input['autorizado_por'] ?? '',
'metodo_autorizacion' => $input['metodo_autorizacion'] ?? '',
'observaciones' => $input['observaciones'] ?? '',
'depto' => $depto,
'status' => 1,
]);

if (!$record) {
file_put_contents($logFile, "  CREATE FAILED - returned null\n", FILE_APPEND);
return null;
}
file_put_contents($logFile, "  INSERTED id=" . $record->id . " folio=" . $record->folio . " depto=" . $record->depto . " status=" . $record->status . " id_estacion=" . $record->id_estacion . "\n", FILE_APPEND);
file_put_contents($logFile, " === ADD END ===\n\n", FILE_APPEND);

$recordId = $record->id;

$documentTypes = ['VALE', 'RECIBO', 'FACTURA', 'PDF', 'XML'];
for ($i = 0; $i < 5; $i++) {
$fileKey = 'doc_' . strtolower($documentTypes[$i]);
if (!empty($input[$fileKey]) && isset($input[$fileKey]['tmp_name']) && $input[$fileKey]['error'] === 0) {
self::uploadDocument($recordId, $documentTypes[$i], $input[$fileKey]);
}
}

return $recordId;
}

public static function update(int $id, array $input): bool
{
$record = SolicitudVale::find($id);
if (!$record) return false;

$idEstacionSession = (int)($input['_id_estacion_session'] ?? 0);
$idUsuario = (int)($input['_id_usuario'] ?? 0);

$estacionDestino = $record->id_estacion;
$cuentaTexto = $record->cuenta;

if ($idUsuario === 292 || $idEstacionSession === 8) {
$estacionDestino = !empty($input['estacion']) ? (int)$input['estacion'] : 0;
$cuentaTexto = !empty($input['cuenta']) ? $input['cuenta'] : '';
} elseif ($idEstacionSession > 0) {
$estacionDestino = $idEstacionSession;
$cuentaTexto = '';
}

$depto = !empty($input['departamento']) ? (int)$input['departamento'] : ($record->depto ?: 0);

$record->update([
'fecha' => $input['fecha'] ?? $record->fecha,
'hora' => $input['hora'] ?? $record->hora,
'monto' => (float)($input['monto'] ?? $record->monto),
'moneda' => $input['moneda'] ?? $record->moneda,
'concepto' => $input['concepto'] ?? $record->concepto,
'solicitante' => $input['solicitante'] ?? $record->solicitante,
'autorizado_por' => $input['autorizado_por'] ?? $record->autorizado_por,
'metodo_autorizacion' => $input['metodo_autorizacion'] ?? $record->metodo_autorizacion,
'observaciones' => $input['observaciones'] ?? $record->observaciones,
'id_estacion' => $estacionDestino,
'cuenta' => $cuentaTexto,
'depto' => $depto,
]);

return true;
}

public static function delete(int $id): ?array
{
$record = SolicitudVale::find($id);
if (!$record) return null;

$data = [
'id_estacion' => $record->id_estacion,
'folio' => $record->folio,
'cuenta' => $record->cuenta,
'id_year' => $record->id_year,
'id_mes' => $record->id_mes,
'fecha' => $record->fecha,
];

SolicitudValeDocumento::where('id_solicitud', $id)->delete();
SolicitudValeComentario::where('id_solicitud', $id)->delete();
$record->delete();

return $data;
}

public static function getComentarios(int $idSolicitud): array
{
$records = SolicitudValeComentario::where('id_solicitud', $idSolicitud)
->orderBy('id', 'desc')->get();

$data = [];
$usuario = Session::get('usuario');
$idUsuarioActual = (int)($usuario['id'] ?? 0);

foreach ($records as $r) {
$user = Usuario::find($r->id_usuario);
$data[] = [
'id' => $r->id,
'usuario_nombre' => $user ? $user->nombre : 'Desconocido',
'comentario' => $r->comentario,
'fecha_hora' => $r->fecha_creacion ? formatearFecha($r->fecha_creacion->format('Y-m-d H:i:s')) . ', ' . $r->fecha_creacion->format('g:i a') : '-',
'esPropio' => $r->id_usuario === $idUsuarioActual,
];
}
return $data;
}

public static function addComentario(int $idSolicitud, string $comentario, int $idUsuario): bool
{
SolicitudValeComentario::create([
'id_solicitud' => $idSolicitud,
'id_usuario' => $idUsuario,
'comentario' => $comentario,
]);
return true;
}

public static function getDocumentos(int $idSolicitud): array
{
$records = SolicitudValeDocumento::where('id_solicitud', $idSolicitud)
->orderBy('id', 'desc')->get();

$data = [];
foreach ($records as $r) {
$data[] = [
'id' => $r->id,
'nombre' => $r->nombre ?? '-',
'documento' => $r->documento,
];
}
return $data;
}

public static function addDocumento(int $idSolicitud, string $nombre, array $file): bool
{
if (empty($file['tmp_name']) || $file['error'] !== 0) return false;

$aleatorio = uniqid();
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$docName = $aleatorio . '-' . $nombre . '.' . $ext;
$uploadDir = __DIR__ . '/../../public/uploads/archivos/solicitud-vales/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

if (!move_uploaded_file($file['tmp_name'], $uploadDir . $docName)) return false;

SolicitudValeDocumento::create([
'id_solicitud' => $idSolicitud,
'nombre' => $nombre,
'documento' => $docName,
]);
return true;
}

public static function deleteDocumento(int $id): ?array
{
$record = SolicitudValeDocumento::find($id);
if (!$record) return null;
$data = ['documento' => $record->documento, 'id_solicitud' => $record->id_solicitud, 'nombre' => $record->nombre];
$record->delete();
return $data;
}

public static function notificarTelegram(string $accion, array $params): void
{
$idEstacion = $params['id_estacion'] ?? 0;
$idUsuario = $params['id_usuario'] ?? 0;
$user = Usuario::find($idUsuario);
$estacion = Estacion::find($idEstacion);
$nombreUsuario = $user ? $user->nombre : 'Desconocido';
$nombreES = $estacion ? $estacion->nombre : '';
$year = $params['year'] ?? '';
$mes = $params['mes'] ?? 0;
$folio = $params['folio'] ?? '';
$cuenta = $params['cuenta'] ?? '';

$periodo = self::monthName($mes) . ' ' . $year;

switch ($accion) {
case 'agregar':
$icono = '✅';
$accionTexto = 'creado un registro';
break;
case 'editar':
$icono = '🔄';
$accionTexto = 'editado un registro';
break;
case 'eliminar':
$icono = '🗑';
$accionTexto = 'eliminado un registro';
break;
case 'agregar_comentario':
$icono = '💭';
$accionTexto = 'agregado un comentario';
break;
default:
return;
}

$detalle = $icono . ' Se ha ' . $accionTexto
. ' en el apartado de <b>Solicitud de Vales</b>, correspondiente al módulo de <b>Corporativo</b> del periodo de <b>' . $periodo . '</b>:'
. PHP_EOL . PHP_EOL
. '#️⃣ Folio: 00' . $folio . PHP_EOL .
'👤 Responsable: ' . $nombreUsuario . PHP_EOL;

if ($idEstacion > 0) {
$detalle .= '⛽ Estación: ' . $nombreES;
} elseif ($cuenta) {
$detalle .= '🏢 Cargo a cuenta: ' . $cuenta;
}


$telegram = new TelegramService();
$mainToken = ($idEstacion > 0 && $idEstacion != 8) ? 292 : 2;
$telegram->sendTokenAsync($mainToken, $detalle);

TelegramService::notificar($idEstacion ?: 8, $idUsuario, $detalle);
}

public static function monthName(int $mes): string
{
$nombres = [
1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
];
return $nombres[$mes] ?? '';
}

private static function uploadDocument(int $idSolicitud, string $nombre, array $file): void
{
$aleatorio = uniqid();
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$docName = $aleatorio . '-' . strtolower($nombre) . '.' . $ext;
$uploadDir = __DIR__ . '/../../public/uploads/archivos/solicitud-vales/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
move_uploaded_file($file['tmp_name'], $uploadDir . $docName);

SolicitudValeDocumento::create([
'id_solicitud' => $idSolicitud,
'nombre' => $nombre,
'documento' => $docName,
]);
}
}
