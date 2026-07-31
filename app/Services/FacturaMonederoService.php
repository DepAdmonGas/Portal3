<?php
namespace App\Services;

use App\Models\Operativo\FacturaMonederosPago;
use App\Models\Operativo\FacturaMonederoComentario;
use App\Models\Usuario;
use App\Models\Estacion;
use App\Core\Auth;
use App\Core\Session;
use App\Services\ModuloDptoOperativoService;

class FacturaMonederoService
{
public static function getPermisos(): array
{
$usuario = Auth::user();
$sessionUsuario = Session::get('usuario');

$idUsuario = (int)($sessionUsuario['id'] ?? 0);
$idPuesto = (int)($usuario->id_puesto ?? 0);
$idEstacion = (int)($sessionUsuario['id_estacion'] ?? 0);
$multiestacion = !empty($sessionUsuario['multiestacion']);

if ($idUsuario === 292) {
$idEstacion = 8;
}

$puedeCrear = $idPuesto !== 6 && $idPuesto !== 7;
$puedeEditar = true;
$puedeEliminar = true;

$permisosDb = ModuloDptoOperativoService::permisosSesion('corporativo');
$dbTieneConfig = !empty($permisosDb);
if ($dbTieneConfig) {
if (isset($permisosDb['crear'])) $puedeCrear = !empty($permisosDb['crear']);
if (isset($permisosDb['editar'])) $puedeEditar = !empty($permisosDb['editar']);
if (isset($permisosDb['eliminar'])) $puedeEliminar = !empty($permisosDb['eliminar']);
}

return [
'id_usuario' => $idUsuario,
'id_estacion' => $idEstacion,
'id_puesto' => $idPuesto,
'multiestacion' => $multiestacion,
'puedeCrear' => $puedeCrear,
'puedeEditar' => $puedeEditar,
'puedeEliminar' => $puedeEliminar,
];
}

public static function getRecord(int $id): ?array
{
$r = FacturaMonederosPago::find($id);
if (!$r) return null;
return [
'id_estacion' => $r->id_estacion,
'year' => $r->year,
'mes' => $r->mes,
'folio' => $r->folio,
'no_factura' => $r->no_factura,
];
}

public static function getData(int $idYear, int $idMes, ?int $estacionFilter = null): array
{
$query = FacturaMonederosPago::where('year', $idYear)->where('mes', $idMes);

if ($estacionFilter !== null && $estacionFilter > 0) {
$query->where('id_estacion', $estacionFilter);
} else {
$sessionUsuario = Session::get('usuario');
$multiestacion = !empty($sessionUsuario['multiestacion']);
if (!$multiestacion) {
$idEstacionSession = (int)($sessionUsuario['id_estacion'] ?? 0);
$idUsuario = (int)($sessionUsuario['id'] ?? 0);
if ($idUsuario === 292) $idEstacionSession = 8;
if ($idEstacionSession > 0) {
$query->where('id_estacion', $idEstacionSession);
}
} else {
$stations = ModuleStationService::getAvailableStations('factura-monedero');
$stationIds = array_column($stations, 'id');
if (!empty($stationIds)) {
$query->whereIn('id_estacion', $stationIds);
}
}
}

$records = $query->orderBy('folio', 'desc')->get();
$data = [];

foreach ($records as $r) {
$commentCount = FacturaMonederoComentario::where('id_factura', $r->id)->count();
$estacionNombre = '';
if ($r->id_estacion && $r->id_estacion > 0) {
$est = Estacion::find($r->id_estacion);
$estacionNombre = $est ? $est->nombre : '';
}

$data[] = [
'id' => $r->id,
'folio' => $r->folio,
'folio_display' => str_pad((string)$r->folio, 3, '0', STR_PAD_LEFT),
'fecha_creacion' => $r->fecha_creacion ? $r->fecha_creacion->format('Y-m-d H:i:s') : '',
'fecha_creacion_format' => $r->fecha_creacion ? formatearFecha($r->fecha_creacion->format('Y-m-d')) . ', ' . $r->fecha_creacion->format('g:i a') : '-',
'no_factura' => $r->no_factura ?? '',
'monto' => (float)($r->monto ?? 0),
'id_estacion' => (int)($r->id_estacion ?? 0),
'estacion_nombre' => $estacionNombre,
'archivo_factura' => $r->archivo_factura ?? '',
'archivo_comprobante_pago' => $r->archivo_comprobante_pago ?? '',
'archivo_factura_xml' => $r->archivo_factura_xml ?? '',
'estado' => (int)($r->estado ?? 0),
'total_comentarios' => $commentCount,
];
}

return $data;
}

public static function getPendientes(int $idYear, int $idMes): array
{
$stations = ModuleStationService::getAvailableStations('factura-monedero');
$stationIds = array_column($stations, 'id');

$pendientes = ['total' => 0];
foreach ($stationIds as $id) {
$count = FacturaMonederosPago::where('id_estacion', $id)
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

public static function getDetalle(int $id): ?array
{
$r = FacturaMonederosPago::find($id);
if (!$r) return null;

$estacionNombre = '';
if ($r->id_estacion && $r->id_estacion > 0) {
$est = Estacion::find($r->id_estacion);
$estacionNombre = $est ? $est->nombre : '';
}

return [
'id' => $r->id,
'id_estacion' => (int)($r->id_estacion ?? 0),
'estacion_nombre' => $estacionNombre,
'year' => $r->year,
'mes' => $r->mes,
'folio' => $r->folio,
'folio_display' => str_pad((string)$r->folio, 3, '0', STR_PAD_LEFT),
'fecha_creacion' => $r->fecha_creacion ? formatearFecha($r->fecha_creacion->format('Y-m-d H:i:s')) . ', ' . $r->fecha_creacion->format('g:i a') : '-',
'no_factura' => $r->no_factura ?? '',
'monto' => (float)($r->monto ?? 0),
'archivo_factura' => $r->archivo_factura ?? '',
'archivo_comprobante_pago' => $r->archivo_comprobante_pago ?? '',
'archivo_factura_xml' => $r->archivo_factura_xml ?? '',
'estado' => (int)($r->estado ?? 0),
];
}

public static function generarFolio(int $idEstacion, int $year, int $mes): int
{
$last = FacturaMonederosPago::where('id_estacion', $idEstacion)
->where('year', $year)
->where('mes', $mes)
->orderBy('folio', 'desc')
->first();
return $last ? $last->folio + 1 : 1;
}

public static function getUploadDir(): string
{
$dir = __DIR__ . '/../../public/uploads/archivos/factura-monedero/';
if (!is_dir($dir)) mkdir($dir, 0775, true);
return $dir;
}

public static function add(array $input): ?int
{
$idEstacion = (int)($input['id_estacion'] ?? 0);
if (!$idEstacion) {
$ctx = ModuleStationService::getContext('factura-monedero');
$idEstacion = (int)($ctx['id_estacion'] ?? 0);
}
if (!$idEstacion) {
$sessionUsuario = Session::get('usuario');
$idEstacion = (int)($sessionUsuario['id_estacion'] ?? 0);
$idUsuario = (int)($sessionUsuario['id'] ?? 0);
if ($idUsuario === 292) $idEstacion = 8;
}

$year = (int)($input['year'] ?? 0);
$mes = (int)($input['mes'] ?? 0);

if (!$idEstacion || !$year || !$mes) return null;

$folio = self::generarFolio($idEstacion, $year, $mes);
$uploadDir = self::getUploadDir();
$aleatorio = uniqid();

$archivoFactura = '';
$archivoXml = '';

if (!empty($input['archivo_factura']) && isset($input['archivo_factura']['tmp_name']) && $input['archivo_factura']['error'] === 0) {
$ext = pathinfo($input['archivo_factura']['name'], PATHINFO_EXTENSION);
$archivoFactura = $aleatorio . '-factura.' . $ext;
move_uploaded_file($input['archivo_factura']['tmp_name'], $uploadDir . $archivoFactura);
}

if (!empty($input['archivo_factura_xml']) && isset($input['archivo_factura_xml']['tmp_name']) && $input['archivo_factura_xml']['error'] === 0) {
$ext = pathinfo($input['archivo_factura_xml']['name'], PATHINFO_EXTENSION);
$archivoXml = $aleatorio . '-xml.' . $ext;
move_uploaded_file($input['archivo_factura_xml']['tmp_name'], $uploadDir . $archivoXml);
}

$record = FacturaMonederosPago::create([
'id_estacion' => $idEstacion,
'year' => $year,
'mes' => $mes,
'folio' => $folio,
'fecha_creacion' => date('Y-m-d H:i:s'),
'no_factura' => $input['no_factura'] ?? '',
'monto' => (float)($input['monto'] ?? 0),
'archivo_factura' => $archivoFactura,
'archivo_factura_xml' => $archivoXml,
'estado' => 0,
]);

return $record ? $record->id : null;
}

public static function update(int $id, array $input): bool
{
$record = FacturaMonederosPago::find($id);
if (!$record) return false;

$aleatorio = uniqid();
$uploadDir = self::getUploadDir();
$archivoFactura = $record->archivo_factura;
$archivoComprobante = $record->archivo_comprobante_pago;
$archivoXml = $record->archivo_factura_xml;
$comprobanteNuevo = false;

if (!empty($input['archivo_factura']) && isset($input['archivo_factura']['tmp_name']) && $input['archivo_factura']['error'] === 0) {
$ext = pathinfo($input['archivo_factura']['name'], PATHINFO_EXTENSION);
$archivoFactura = $aleatorio . '-factura.' . $ext;
move_uploaded_file($input['archivo_factura']['tmp_name'], $uploadDir . $archivoFactura);
if ($record->archivo_factura && file_exists($uploadDir . $record->archivo_factura)) {
unlink($uploadDir . $record->archivo_factura);
}
}

if (!empty($input['archivo_comprobante_pago']) && isset($input['archivo_comprobante_pago']['tmp_name']) && $input['archivo_comprobante_pago']['error'] === 0) {
$ext = pathinfo($input['archivo_comprobante_pago']['name'], PATHINFO_EXTENSION);
$archivoComprobante = $aleatorio . '-comprobante.' . $ext;
move_uploaded_file($input['archivo_comprobante_pago']['tmp_name'], $uploadDir . $archivoComprobante);
$comprobanteNuevo = true;
if ($record->archivo_comprobante_pago && file_exists($uploadDir . $record->archivo_comprobante_pago)) {
unlink($uploadDir . $record->archivo_comprobante_pago);
}
}

if (!empty($input['archivo_factura_xml']) && isset($input['archivo_factura_xml']['tmp_name']) && $input['archivo_factura_xml']['error'] === 0) {
$ext = pathinfo($input['archivo_factura_xml']['name'], PATHINFO_EXTENSION);
$archivoXml = $aleatorio . '-xml.' . $ext;
move_uploaded_file($input['archivo_factura_xml']['tmp_name'], $uploadDir . $archivoXml);
if ($record->archivo_factura_xml && file_exists($uploadDir . $record->archivo_factura_xml)) {
unlink($uploadDir . $record->archivo_factura_xml);
}
}

$record->update([
'no_factura' => $input['no_factura'] ?? $record->no_factura,
'monto' => (float)($input['monto'] ?? $record->monto),
'archivo_factura' => $archivoFactura,
'archivo_comprobante_pago' => $archivoComprobante,
'archivo_factura_xml' => $archivoXml,
'estado' => $comprobanteNuevo ? 1 : $record->estado,
]);

return true;
}

public static function delete(int $id): ?array
{
$record = FacturaMonederosPago::find($id);
if (!$record) return null;

$uploadDir = self::getUploadDir();
if ($record->archivo_factura && file_exists($uploadDir . $record->archivo_factura)) unlink($uploadDir . $record->archivo_factura);
if ($record->archivo_comprobante_pago && file_exists($uploadDir . $record->archivo_comprobante_pago)) unlink($uploadDir . $record->archivo_comprobante_pago);
if ($record->archivo_factura_xml && file_exists($uploadDir . $record->archivo_factura_xml)) unlink($uploadDir . $record->archivo_factura_xml);

$data = [
'id_estacion' => $record->id_estacion,
'folio' => $record->folio,
'year' => $record->year,
'mes' => $record->mes,
'no_factura' => $record->no_factura,
];

FacturaMonederoComentario::where('id_factura', $id)->delete();
$record->delete();

return $data;
}

public static function getComentarios(int $idFactura): array
{
$records = FacturaMonederoComentario::where('id_factura', $idFactura)
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

public static function addComentario(int $idFactura, string $comentario, int $idUsuario): bool
{
FacturaMonederoComentario::create([
'id_factura' => $idFactura,
'id_usuario' => $idUsuario,
'comentario' => $comentario,
]);
return true;
}
}
