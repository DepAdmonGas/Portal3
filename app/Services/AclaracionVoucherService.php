<?php
namespace App\Services;

use App\Models\Operativo\AclaracionVoucher;
use App\Models\Operativo\AclaracionBaucherComentario;
use App\Models\Operativo\VoucherDocumentos;
use App\Models\Usuario;
use App\Models\Estacion;
use App\Core\Auth;
use App\Core\Session;
use App\Services\ModuloDptoOperativoService;
use Carbon\Carbon;

class AclaracionVoucherService
{
public static function getPermisos(): array
{
$usuario = Auth::user();
$sessionUsuario = Session::get('usuario');

$idPuesto = (int)($usuario->id_puesto ?? 0);
$idEstacion = (int)($sessionUsuario['id_estacion'] ?? 0);
$multiestacion = !empty($sessionUsuario['multiestacion']);

$esDireccion = $idPuesto === 13;
$esComercializadora = $idPuesto === 4;
$esContaduria = $idPuesto === 12;
$esEncargado = $idPuesto === 6 || $idPuesto === 7;
$esGestoria = $idPuesto === 5;
$esAsistente = $idPuesto === 14;

// Only puestos other than 6 and 7 can create
$puedeCrear = $idPuesto !== 6 && $idPuesto !== 7;
$puedeEditar = true;
$puedeEliminar = ($esDireccion || $esEncargado || $esAsistente) && !$esGestoria;

$permisosDb = ModuloDptoOperativoService::permisosSesion('corporativo');
$dbTieneConfig = !empty($permisosDb);
if ($dbTieneConfig) {
$puedeEditar = !empty($permisosDb['editar']);
$puedeEliminar = !empty($permisosDb['eliminar']);
}

return [
'id_usuario' => (int)($sessionUsuario['id'] ?? 0),
'id_estacion' => $idEstacion,
'id_puesto' => $idPuesto,
'esDireccion' => $esDireccion,
'esComercializadora' => $esComercializadora,
'esContaduria' => $esContaduria,
'esEncargado' => $esEncargado,
'esGestoria' => $esGestoria,
'esAsistente' => $esAsistente,
'puedeCrear' => $puedeCrear,
'puedeEditar' => $puedeEditar,
'puedeEliminar' => $puedeEliminar,
'multiestacion' => $multiestacion,
];
}

public static function getAllowedStationIds(): array
{
$permisos = self::getPermisos();
if ($permisos['esDireccion']) {
return [1, 2, 3, 4, 5, 6, 7, 14];
}
if ($permisos['esComercializadora']) {
return [6, 7];
}
if ($permisos['esContaduria']) {
return [1, 2, 3, 4, 5, 14];
}
return [1, 2, 3, 4, 5, 6, 7, 14];
}

public static function getPendientes(int $idYear, int $idMes): array
{
$stationIds = self::getAllowedStationIds();
$pendientes = ['total' => 0];

foreach ($stationIds as $id) {
$count = AclaracionVoucher::where('id_estacion', $id)
->where('year', $idYear)
->where('mes', $idMes)
->where('estado', 0)
->whereRaw('DATEDIFF(NOW(), fecha_creacion) < 3')
->count();
$pendientes['estacion_' . $id] = $count;
$pendientes['total'] += $count;
}
return $pendientes;
}

public static function getRecord(int $id): ?array
{
$r = AclaracionVoucher::find($id);
if (!$r) return null;
return [
'id_estacion' => $r->id_estacion,
'year' => $r->year,
'mes' => $r->mes,
'nombre_ticket' => $r->nombre_ticket,
'numero_aclaracion' => $r->numero_aclaracion,
];
}

public static function getData(int $idYear, int $idMes, ?int $estacionFilter = null): array
{
$query = AclaracionVoucher::where('year', $idYear)
->where('mes', $idMes);

if ($estacionFilter !== null && $estacionFilter > 0) {
$query->where('id_estacion', $estacionFilter);
} else {
$allowedIds = self::getAllowedStationIds();
if (!empty($allowedIds)) {
$query->whereIn('id_estacion', $allowedIds);
}
}

$records = $query->orderBy('id', 'desc')->get();

$data = [];
$idx = count($records);
foreach ($records as $r) {
$diffDays = $r->fecha_creacion ? $r->fecha_creacion->diffInDays(Carbon::now()) : 99;
$commentCount = AclaracionBaucherComentario::where('id_aclaracion', $r->id)->count();

$user = Usuario::find($r->id_solicitante);
$estacion = Estacion::find($r->id_estacion);
$nombreSolicitante = $user ? $user->nombre : 'Desconocido';
$nombreEstacion = $estacion ? $estacion->nombre : '';

$data[] = [
'id' => $r->id,
'num' => $idx--,
'fecha_raw' => $r->fecha ? $r->fecha->format('Y-m-d') : '',
'hora_raw' => $r->hora ? $r->hora->format('H:i:s') : '',
'fecha_creacion' => $r->fecha_creacion ? formatearFecha($r->fecha_creacion->format('Y-m-d H:i:s')) . ', ' . $r->fecha_creacion->format('g:i a') : '',
'solicitante_nombre' => $nombreSolicitante,
'estacion_nombre' => $nombreEstacion,
'nombre_ticket' => $r->nombre_ticket ?? '',
'fecha' => $r->fecha ? formatearFecha($r->fecha->format('Y-m-d')) : '',
'hora' => $r->hora ? date('g:i a', strtotime($r->hora)) : '',
'valera' => $r->valera ?? '',
'importe' => (float)($r->importe ?? 0),
'numero_aclaracion' => $r->numero_aclaracion ?? '',
'doc_ticket' => $r->doc_ticket,
'doc_voucher' => $r->doc_voucher,
'pagado' => (int)($r->pagado ?? 0),
'estado' => (int)($r->estado ?? 0),
'total_comentarios' => $commentCount,
];
}
return $data;
}

public static function add(array $input): int
{
$aleatorio = uniqid();
$docTicket = '';
$docVoucher = '';

if (!empty($input['ticket_file']) && isset($input['ticket_file']['tmp_name']) && $input['ticket_file']['error'] === 0) {
$ext = pathinfo($input['ticket_file']['name'], PATHINFO_EXTENSION);
$docTicket = $aleatorio . '-ticket.' . $ext;
$uploadDir = __DIR__ . '/../../public/uploads/archivos/aclaracion-voucher/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
move_uploaded_file($input['ticket_file']['tmp_name'], $uploadDir . $docTicket);
}

if (!empty($input['voucher_file']) && isset($input['voucher_file']['tmp_name']) && $input['voucher_file']['error'] === 0) {
$ext = pathinfo($input['voucher_file']['name'], PATHINFO_EXTENSION);
$docVoucher = $aleatorio . '-voucher.' . $ext;
$uploadDir = __DIR__ . '/../../public/uploads/archivos/aclaracion-voucher/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
move_uploaded_file($input['voucher_file']['tmp_name'], $uploadDir . $docVoucher);
}

$record = AclaracionVoucher::create([
'id_estacion' => $input['id_estacion'],
'year' => $input['year'],
'mes' => $input['mes'],
'id_solicitante' => $input['id_usuario'],
'nombre_ticket' => $input['nombre_ticket'],
'fecha' => $input['fecha'],
'hora' => $input['hora'],
'valera' => $input['valera'],
'importe' => $input['importe'],
'numero_aclaracion' => $input['numero_aclaracion'],
'doc_ticket' => $docTicket,
'doc_voucher' => $docVoucher,
'pagado' => 0,
'estado' => 0,
]);

return $record->id;
}

public static function update(int $id, array $input): bool
{
$record = AclaracionVoucher::find($id);
if (!$record) return false;

$aleatorio = uniqid();
$docTicket = $record->doc_ticket;
$docVoucher = $record->doc_voucher;

if (!empty($input['ticket_file']) && isset($input['ticket_file']['tmp_name']) && $input['ticket_file']['error'] === 0) {
$ext = pathinfo($input['ticket_file']['name'], PATHINFO_EXTENSION);
$docTicket = $aleatorio . '-ticket.' . $ext;
$uploadDir = __DIR__ . '/../../public/uploads/archivos/aclaracion-voucher/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
move_uploaded_file($input['ticket_file']['tmp_name'], $uploadDir . $docTicket);
}

if (!empty($input['voucher_file']) && isset($input['voucher_file']['tmp_name']) && $input['voucher_file']['error'] === 0) {
$ext = pathinfo($input['voucher_file']['name'], PATHINFO_EXTENSION);
$docVoucher = $aleatorio . '-voucher.' . $ext;
$uploadDir = __DIR__ . '/../../public/uploads/archivos/aclaracion-voucher/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
move_uploaded_file($input['voucher_file']['tmp_name'], $uploadDir . $docVoucher);
}

$record->update([
'nombre_ticket' => $input['nombre_ticket'],
'fecha' => $input['fecha'],
'hora' => $input['hora'],
'valera' => $input['valera'],
'importe' => $input['importe'],
'numero_aclaracion' => $input['numero_aclaracion'],
'pagado' => $input['pagado'] ?? 0,
'doc_ticket' => $docTicket,
'doc_voucher' => $docVoucher,
]);

return true;
}

public static function delete(int $id): ?array
{
$record = AclaracionVoucher::find($id);
if (!$record) return null;

$data = [
'id_estacion' => $record->id_estacion,
'nombre_ticket' => $record->nombre_ticket,
'numero_aclaracion' => $record->numero_aclaracion,
'doc_ticket' => $record->doc_ticket,
'doc_voucher' => $record->doc_voucher,
'mes' => $record->mes,
'year' => $record->year,
];

$record->delete();
return $data;
}

public static function finalizar(int $id): bool
{
$record = AclaracionVoucher::find($id);
if (!$record) return false;
$record->update(['estado' => 1]);
return true;
}

public static function getComentarios(int $idAclaracion): array
{
$records = AclaracionBaucherComentario::where('id_aclaracion', $idAclaracion)
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
'fecha_hora' => $r->fecha_hora ? formatearFecha($r->fecha_hora->format('Y-m-d H:i:s')) . ', ' . $r->fecha_hora->format('g:i a') : '-',
'esPropio' => $r->id_usuario === $idUsuarioActual,
];
}
return $data;
}

public static function addComentario(int $idAclaracion, string $comentario, int $idUsuario): bool
{
AclaracionBaucherComentario::create([
'id_aclaracion' => $idAclaracion,
'id_usuario' => $idUsuario,
'comentario' => $comentario,
]);
return true;
}

public static function getAnexos(int $idSolicitud): array
{
$records = VoucherDocumentos::where('id_reporte', $idSolicitud)->orderBy('id', 'desc')->get();
$data = [];
foreach ($records as $r) {
$user = Usuario::find($r->id_usuario);
$data[] = [
'id' => $r->id,
'descripcion' => $r->descripcion ?? '-',
'documento' => $r->documento,
'fecha_creacion' => $r->fecha_creacion ? formatearFecha($r->fecha_creacion->format('Y-m-d H:i:s')) . ', ' . $r->fecha_creacion->format('g:i a') : '-',
'usuario_nombre' => $user ? $user->nombre : 'Desconocido',
];
}
return $data;
}

public static function addAnexo(int $idSolicitud, string $descripcion, array $file, int $idUsuario): bool
{
$aleatorio = uniqid();
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$docName = $aleatorio . '-anexo.' . $ext;
$uploadDir = __DIR__ . '/../../public/uploads/archivos/aclaracion-voucher/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

if (!move_uploaded_file($file['tmp_name'], $uploadDir . $docName)) return false;

VoucherDocumentos::create([
'id_reporte' => $idSolicitud,
'id_usuario' => $idUsuario,
'descripcion' => $descripcion,
'documento' => $docName,
]);
return true;
}

public static function deleteAnexo(int $id): ?array
{
$record = VoucherDocumentos::find($id);
if (!$record) return null;
$data = ['documento' => $record->documento, 'id_reporte' => $record->id_reporte, 'descripcion' => $record->descripcion];
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
$nombreES = $estacion ? $estacion->nombre : "ES{$idEstacion}";
$year = $params['year'] ?? '';
$mes = $params['mes'] ?? 0;
$ticket = $params['nombre_ticket'] ?? '';
$numAclaracion = $params['numero_aclaracion'] ?? '';

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

case 'agregar_anexo':
$icono = '📎';
$accionTexto = 'agregado un anexo';
break;

case 'eliminar_anexo':
$icono = '🗑';
$accionTexto = 'eliminado un anexo';
break;

case 'finalizar':
$icono = '✅🔚';
$accionTexto = 'finalizado un registro';
break;

default:
return;
}

$detalle = $icono . ' Se ha ' . $accionTexto
. ' en el apartado de <b>Aclaración Voucher</b>, correspondiente al módulo de <b>Corporativo</b> del periodo de <b>' . $periodo . '</b>:'
. PHP_EOL . PHP_EOL;

if (in_array($accion, ['agregar_anexo', 'eliminar_anexo'])) {
$detalle .= '📄 <b>Anexo:</b> ' . ($params['descripcion'] ?? '') . PHP_EOL;
}

$detalle .= '🎟 <b>Ticket:</b> ' . $ticket . PHP_EOL
. ($numAclaracion ? '🔢 <b>No. Aclaración:</b> ' . $numAclaracion . PHP_EOL : '')
. PHP_EOL
. '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL
. '⛽ <b>Estación:</b> ' . $nombreES;

if (!empty($detalle)) {
TelegramService::notificar($idEstacion, $idUsuario, $detalle);
}
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
}
