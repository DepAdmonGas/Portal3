<?php

namespace App\Services;

use App\Models\ListaTransportes;
use App\Models\Operativo\CorteMes;
use App\Models\Operativo\Embarque;
use App\Models\Operativo\EmbarquesComentario;
use App\Models\PivoteoChofer;
use App\Models\UnidadesTransporte;
use App\Models\Estacion;
use App\Services\TelegramService;
use App\Core\Auth;
use App\Core\Session;
use Illuminate\Support\Carbon;

class EmbarquesService
{
public static function getMesId(int $idEstacion, int $idYear, int $idMes): ?int
{
$corteMes = CorteMes::whereHas('year', function ($q) use ($idEstacion, $idYear) {
$q->where('id_estacion', $idEstacion)->where('year', $idYear);
})->where('mes', $idMes)->first();

return $corteMes?->id;
}

public static function getPermisos(): array
{
$usuario = Auth::user();
$sessionUsuario = Session::get('usuario');

$multiEstacion = $sessionUsuario['multiestacion'] ?? false;
$esDireccionOperaciones = false;
$esContabilidad = false;
$esServicioSocial = false;
$esComercializadora = false;
$esEncargado = false;
$esAsistente = false;

if ($usuario && $usuario->puesto) {
$tipo = $usuario->puesto->tipo_puesto ?? '';
$esDireccionOperaciones = $tipo === 'Dirección de operaciones';
$esContabilidad = $tipo === 'Contabilidad';
$esServicioSocial = $tipo === 'Dirección de operaciones servicio social';
$esComercializadora = $tipo === 'Comercializadora';
$esEncargado = $tipo === 'Encargado';
$esAsistente = $tipo === 'Asistente Administrativo';
}

return [
'multiestacion' => $multiEstacion,
'es_direccion_operaciones' => $esDireccionOperaciones,
'es_contabilidad' => $esContabilidad,
'es_servicio_social' => $esServicioSocial,
'es_comercializadora' => $esComercializadora,
'es_encargado' => $esEncargado,
'es_asistente' => $esAsistente,
'id_usuario' => $sessionUsuario['id'] ?? 0,
'id_puesto' => $usuario->id_puesto ?? 0,
'tipo_puesto' => $usuario->puesto->tipo_puesto ?? '',
'puede_leer' => true,
'puede_agregar' => $esEncargado || $esAsistente || $esDireccionOperaciones,
'puede_editar' => $esEncargado || $esAsistente || $esDireccionOperaciones,
'puede_eliminar' => ($esEncargado || $esAsistente || $esDireccionOperaciones) && !$esServicioSocial,
'puede_upload' => $esEncargado || $esAsistente || $esDireccionOperaciones,
'puede_ver_comentarios' => true,
'puede_agregar_comentarios' => $esEncargado || $esAsistente || $esDireccionOperaciones,
'puede_analisis_compras' => $esDireccionOperaciones || $esContabilidad || $esComercializadora || $esServicioSocial,
];
}

public static function getDatos(?int $idMesDb): array
{
if (!$idMesDb) {
return [];
}

$rows = Embarque::where('id_mes', $idMesDb)
->orderBy('fecha', 'desc')
->orderBy('id', 'desc')
->get();

$result = [];

foreach ($rows as $row) {
$numCom = EmbarquesComentario::where('id_embarques', $row->id)->count();

$result[] = [
'id' => $row->id,
'id_mes' => $row->id_mes,
'fecha' => formatearFecha($row->fecha ?? ''),
'fecha_raw' => !empty($row->fecha) ? $row->fecha->format('Y-m-d') : '',
'embarque' => $row->embarque ?? '',
'producto' => $row->producto ?? '',
'documento' => $row->documento ?? '',
'documentocv' => $row->documentocv ?? '',
'importef' => (float)($row->importef ?? 0),
'precio_litro' => (float)($row->precio_litro ?? 0),
'merma' => (float)($row->merma ?? 0),
'tad' => $row->tad ?? '',
'nom_transporte' => $row->nom_transporte ?? '',
'chofer' => $row->chofer ?? '',
'unidad' => $row->unidad ?? '',
'pdf' => $row->pdf ?? '',
'xml' => $row->xml ?? '',
'comprobante_p' => $row->comprobante_p ?? '',
'nc_pdf' => $row->nc_pdf ?? '',
'nc_xml' => $row->nc_xml ?? '',
'comPDF' => $row->comPDF ?? '',
'comXML' => $row->comXML ?? '',
'semaforo' => self::getSemaforoDocs($row),
'num_comentarios' => $numCom,
];
}

return $result;
}

public static function getSemaforoDocs(Embarque $row): int
{
$transportista = strtoupper(trim($row->nom_transporte ?? ''));
$tipoEmbarque = strtoupper(trim($row->embarque ?? ''));

if ($tipoEmbarque === 'PEMEX' || $tipoEmbarque === 'DELIVERY') {
return 2;
}

if (strpos($transportista, 'PETRO ASFALTOS') !== false || strpos($transportista, 'SANTA FE') !== false) {
$docs = ['pdf', 'xml', 'comprobante_p', 'nc_pdf', 'comPDF', 'comXML'];
} elseif ($transportista === 'SIPCI') {
$docs = ['pdf', 'xml', 'comprobante_p'];
} else {
$docs = ['pdf', 'xml', 'comprobante_p', 'nc_pdf', 'nc_xml', 'comPDF', 'comXML'];
}

$tiene = 0;
$total = count($docs);
foreach ($docs as $d) {
if (!empty($row->$d)) $tiene++;
}

if ($tiene === 0) return 0;
if ($tiene >= $total) return 2;
return 1;
}

public static function store(array $data, array $files): array
{
$idMesDb = (int) ($data['id_mes'] ?? 0);
if (!$idMesDb) {
return ['success' => false, 'message' => 'ID de mes no válido'];
}

$usuario = Auth::user();
if (!$usuario) {
return ['success' => false, 'message' => 'Sesión no válida'];
}

$uploadDir = self::getUploadDir();
if (!is_dir($uploadDir)) {
mkdir($uploadDir, 0755, true);
}

$record = [
'id_mes' => $idMesDb,
'fecha' => $data['fecha'] ?? null,
'embarque' => $data['embarque'] ?? '',
'producto' => $data['producto'] ?? '',
'documentocv' => $data['documentocv'] ?? '',
'importef' => (float) ($data['importef'] ?? 0),
'precio_litro' => (float) ($data['precio_litro'] ?? 0),
'merma' => (float) ($data['merma'] ?? 0),
'tad' => $data['tad'] ?? '',
'nom_transporte' => $data['nom_transporte'] ?? '',
'chofer' => $data['chofer'] ?? '',
'unidad' => $data['unidad'] ?? '',
];

$fileFields = ['documento', 'pdf', 'xml', 'comprobante_p', 'nc_pdf', 'nc_xml', 'comPDF', 'comXML'];
foreach ($fileFields as $field) {
if (!empty($files[$field]) && $files[$field]['error'] === UPLOAD_ERR_OK) {
$ext = pathinfo($files[$field]['name'], PATHINFO_EXTENSION);
$filename = $field . '_' . time() . '_' . uniqid() . '.' . $ext;
if (move_uploaded_file($files[$field]['tmp_name'], $uploadDir . '/' . $filename)) {
$record[$field] = $filename;
}
}
}

self::persistCatalogos($data);

try {
$embarque = Embarque::create($record);
return ['success' => true, 'message' => 'Embarque agregado exitosamente.', 'id' => $embarque->id];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()];
}
}

public static function update(int $id, array $data, array $files): array
{
$embarque = Embarque::find($id);
if (!$embarque) {
return ['success' => false, 'message' => 'Embarque no encontrado'];
}

$embarque->fecha = $data['fecha'] ?? $embarque->fecha;
$embarque->embarque = $data['embarque'] ?? $embarque->embarque;
$embarque->producto = $data['producto'] ?? $embarque->producto;
$embarque->documentocv = $data['documentocv'] ?? $embarque->documentocv;
$embarque->importef = (float) ($data['importef'] ?? $embarque->importef);
$embarque->precio_litro = (float) ($data['precio_litro'] ?? $embarque->precio_litro);
$embarque->merma = (float) ($data['merma'] ?? $embarque->merma);
$embarque->tad = $data['tad'] ?? $embarque->tad;
$embarque->nom_transporte = $data['nom_transporte'] ?? $embarque->nom_transporte;
$embarque->chofer = $data['chofer'] ?? $embarque->chofer;
$embarque->unidad = $data['unidad'] ?? $embarque->unidad;

$uploadDir = self::getUploadDir();
if (!is_dir($uploadDir)) {
mkdir($uploadDir, 0755, true);
}

$fileFields = ['documento', 'pdf', 'xml', 'comprobante_p', 'nc_pdf', 'nc_xml', 'comPDF', 'comXML'];
foreach ($fileFields as $field) {
if (!empty($files[$field]) && $files[$field]['error'] === UPLOAD_ERR_OK) {
if ($embarque->$field) {
$oldFile = $uploadDir . '/' . $embarque->$field;
if (file_exists($oldFile)) {
unlink($oldFile);
}
}
$ext = pathinfo($files[$field]['name'], PATHINFO_EXTENSION);
$filename = $field . '_' . time() . '_' . uniqid() . '.' . $ext;
if (move_uploaded_file($files[$field]['tmp_name'], $uploadDir . '/' . $filename)) {
$embarque->$field = $filename;
}
}
}

self::persistCatalogos($data);

try {
$embarque->save();
return ['success' => true, 'message' => 'Embarque actualizado exitosamente.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al actualizar: ' . $e->getMessage()];
}
}

public static function destroy(int $id): array
{
$embarque = Embarque::find($id);
if (!$embarque) {
return ['success' => false, 'message' => 'Embarque no encontrado'];
}

$uploadDir = self::getUploadDir();
$fileFields = ['documento', 'pdf', 'xml', 'comprobante_p', 'nc_pdf', 'nc_xml', 'comPDF', 'comXML'];
foreach ($fileFields as $field) {
if ($embarque->$field) {
$filePath = $uploadDir . '/' . $embarque->$field;
if (file_exists($filePath)) {
unlink($filePath);
}
}
}

try {
$embarque->delete();
return ['success' => true, 'message' => 'Embarque eliminado exitosamente.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()];
}
}

public static function getComentarios(int $idEmbarque): array
{
return EmbarquesComentario::where('id_embarques', $idEmbarque)
->orderBy('fecha_hora', 'asc')
->with('usuario:id,nombre')
->get()
->toArray();
}

public static function storeComentario(int $idEmbarque, string $comentario): array
{
$usuario = Auth::user();
if (!$usuario) {
return ['success' => false, 'message' => 'Sesión no válida'];
}

if (!trim($comentario)) {
return ['success' => false, 'message' => 'El comentario no puede estar vacío'];
}

try {
EmbarquesComentario::create([
'id_embarques' => $idEmbarque,
'fecha_hora' => Carbon::now(),
'id_usuario' => $usuario->id,
'comentario' => trim($comentario),
]);
return ['success' => true, 'message' => 'Comentario agregado.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al guardar comentario: ' . $e->getMessage()];
}
}

public static function notificarComentarioEmb(int $idEmbarque, int $idUsuario, string $nombreUsuario, string $comentario = ''): void
{
try {
$embarque = Embarque::with('mes.year')->find($idEmbarque);
if (!$embarque || !$embarque->mes || !$embarque->mes->year) return;

$idEstacion = (int) $embarque->mes->year->id_estacion;
$corteMes = CorteMes::with('year')->find($embarque->id_mes);
$mes = $corteMes ? (int) $corteMes->mes : 0;
$year = $corteMes && $corteMes->year ? (int) $corteMes->year->year : 0;
$estacion = Estacion::find($idEstacion);
$nombreES = $estacion ? $estacion->nombre : 'Desconocida';
$fechaEmb = $embarque->fecha ? $embarque->fecha->format('Y-m-d') : '';
$fechaHora = date('Y-m-d H:i:s');
$importef = (float)($embarque->importef ?? 0);
$precioLitro = (float)($embarque->precio_litro ?? 0);
$merma = (float)($embarque->merma ?? 0);

$mensaje = '💬 Se ha agregado un comentario en el apartado de <b>Resumen de Embarques</b>, correspondiente al periodo <b>'
. nombremes($mes) . ' ' . $year . '</b>:' . PHP_EOL

. PHP_EOL . '📝 <b>Comentario:</b> ' . $comentario . PHP_EOL 
. PHP_EOL . '🚛 <b>Tipo embarque:</b> ' . ($embarque->embarque ?? 'N/A')
. PHP_EOL . '🛢️ <b>Producto:</b> ' . ($embarque->producto ?? 'N/A')
. PHP_EOL . '📅 <b>Fecha embarque:</b> ' . ($fechaEmb ?: 'N/A')
. PHP_EOL . '📏 <b>Litros factura:</b> ' . ($importef ?: '0')
. PHP_EOL . '💲 <b>Precio por litro:</b> $' . number_format($precioLitro, 2);

if (!empty($merma)) {
$mensaje .= PHP_EOL . '📉 <b>Merma:</b> ' . $merma;
}
$mensaje .=
PHP_EOL . '🚚 <b>Transportista:</b> ' . ($embarque->nom_transporte ?? 'N/A')
. PHP_EOL . '👨‍✈️ <b>Chofer:</b> ' . ($embarque->chofer ?? 'N/A')
. PHP_EOL . '🚛 <b>Unidad:</b> ' . ($embarque->unidad ?? 'N/A')

. PHP_EOL . PHP_EOL . '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL
. '⛽ <b>Estación:</b> ' . $nombreES . PHP_EOL;


self::enviarTelegramEmb($idEstacion, $idUsuario, $mensaje);
} catch (\Throwable $e) {
error_log('Error en notificarComentarioEmb: ' . $e->getMessage());
}
}

public static function getCatalogos(): array
{
try {
return [
'choferes' => PivoteoChofer::where('estado', 0)->orderBy('id')->pluck('nombre_chofer')->toArray(),
'unidades' => UnidadesTransporte::where('estado', 0)->orderBy('id')->pluck('no_unidad')->toArray(),
'transportes' => ListaTransportes::where('estado', 0)->orderBy('nombre_transporte')->pluck('nombre_transporte')->toArray(),
];
} catch (\Exception $e) {
return ['choferes' => [], 'unidades' => [], 'transportes' => []];
}
}

public static function persistCatalogos(array $data): void
{
if (!empty($data['chofer'])) {
PivoteoChofer::firstOrCreate(
['nombre_chofer' => trim($data['chofer'])],
['estado' => 0]
);
}

if (!empty($data['unidad'])) {
UnidadesTransporte::firstOrCreate(
['no_unidad' => trim($data['unidad'])],
['estado' => 0]
);
}

if (!empty($data['nom_transporte'])) {
ListaTransportes::firstOrCreate(
['nombre_transporte' => trim($data['nom_transporte'])],
['estado' => 0]
);
}
}

public static function getUploadDir(): string
{
$dir = __DIR__ . '/../../public/uploads/archivos/embarques';
if (!is_dir($dir)) {
mkdir($dir, 0755, true);
}
return realpath($dir);
}

private static function getEstacionIdFromMesIdEmb(int $idMes): int
{
$corteMes = CorteMes::with('year')->find($idMes);
return $corteMes && $corteMes->year ? (int) $corteMes->year->id_estacion : 0;
}

private static function enviarTelegramEmb(int $idEstacion, int $excludeUserId, string $mensaje): void
{
$telegram = new TelegramService();
$userIds = $telegram->getUserIdsByStation($idEstacion, $excludeUserId);

if (in_array($idEstacion, [6, 7])) {
$extraIds = $telegram->getUserIdsComercializadora($excludeUserId);
$userIds = array_values(array_unique(array_merge($userIds, $extraIds)));
} elseif (in_array($idEstacion, [1, 2, 3, 4, 5, 14])) {
$extraIds = $telegram->getUserIdsContabilidad($excludeUserId);
$userIds = array_values(array_unique(array_merge($userIds, $extraIds)));
}

$telegram->sendMessageToMultiple($userIds, $mensaje);
}

public static function getMesYearEmb(int $idMes): string
{
$corteMes = CorteMes::with('year')->find($idMes);
return $corteMes && $corteMes->year ? nombremes((int) $corteMes->mes) . ' ' . $corteMes->year->year : '';
}

public static function notificarStoreEmb(int $idMes, int $idUsuario, string $nombreUsuario, string $embarque = '', string $documento = '', array $extra = []): void
{
try {
$idEstacion = self::getEstacionIdFromMesIdEmb($idMes);
if (!$idEstacion) return;

$periodo = self::getMesYearEmb($idMes);
$estacion = Estacion::find($idEstacion);
$nombreES = $estacion ? $estacion->nombre : 'Desconocida';
$corteMes = CorteMes::with('year')->find($idMes);
$mes = $corteMes ? (int) $corteMes->mes : 0;
$year = $corteMes && $corteMes->year ? (int) $corteMes->year->year : 0;

$detalle = '';

if (!empty($extra['fecha'])) {
$detalle .= PHP_EOL . '📅 <b>Fecha:</b> ' . $extra['fecha'];
}
if (!empty($extra['producto'])) {
$detalle .= PHP_EOL . '🛢️ <b>Producto:</b> ' . $extra['producto'];
}
if (!empty($extra['importef'])) {
$detalle .= PHP_EOL . '📏 <b>Litros factura:</b> ' . (float) $extra['importef'];
}
if (!empty($extra['precio_litro'])) {
$detalle .= PHP_EOL . '💲 <b>Precio por litro:</b> $' . number_format((float) $extra['precio_litro'], 2);
}
if (!empty($extra['merma'])) {
$detalle .= PHP_EOL . '📉 <b>Merma:</b> ' . (float) $extra['merma'];
}
if (!empty($extra['tad'])) {
$detalle .= PHP_EOL . '📍 <b>TAD:</b> ' . $extra['tad'];
}

$transporte = '';

if (!empty($extra['nom_transporte'])) {
$transporte .= PHP_EOL . '🚚 <b>Transportista:</b> ' . $extra['nom_transporte'];
}
if (!empty($extra['chofer'])) {
$transporte .= PHP_EOL . '👨‍✈️ <b>Chofer:</b> ' . $extra['chofer'];
}
if (!empty($extra['unidad'])) {
$transporte .= PHP_EOL . '🚛 <b>Unidad:</b> ' . $extra['unidad'];
}

$mensaje = '📦 Se ha agregado un nuevo embarque en el apartado de <b>Resumen de Embarques</b>, correspondiente al periodo de <b>'
. nombremes($mes) . ' ' . $year . '</b>:' . PHP_EOL . PHP_EOL
. '🚛 <b>Embarque:</b> ' . ($embarque ?: 'N/A')
. $detalle
. $transporte . PHP_EOL . PHP_EOL
. '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL
. '⛽ <b>Estación:</b> ' . $nombreES;

self::enviarTelegramEmb($idEstacion, $idUsuario, $mensaje);
} catch (\Throwable $e) {
error_log('Error en notificarStoreEmb: ' . $e->getMessage());
}
}

public static function notificarUpdateEmb(int $idMes, int $idUsuario, string $nombreUsuario, string $embarque = '', string $documento = '', array $extra = []): void
{
try {
$idEstacion = self::getEstacionIdFromMesIdEmb($idMes);
if (!$idEstacion) return;

$periodo = self::getMesYearEmb($idMes);
$estacion = Estacion::find($idEstacion);
$nombreES = $estacion ? $estacion->nombre : 'Desconocida';
$corteMes = CorteMes::with('year')->find($idMes);
$mes = $corteMes ? (int) $corteMes->mes : 0;
$year = $corteMes && $corteMes->year ? (int) $corteMes->year->year : 0;
$fechaHora = date('Y-m-d H:i:s');

$detalle = '';

if (!empty($extra['fecha'])) {
$detalle .= PHP_EOL . '📅 <b>Fecha:</b> ' . $extra['fecha'];
}
if (!empty($extra['producto'])) {
$detalle .= PHP_EOL . '🛢️ <b>Producto:</b> ' . $extra['producto'];
}
if (!empty($extra['importef'])) {
$detalle .= PHP_EOL . '📏 <b>Litros factura:</b> ' . (float) $extra['importef'];
}
if (!empty($extra['precio_litro'])) {
$detalle .= PHP_EOL . '💲 <b>Precio por litro:</b> $' . number_format((float) $extra['precio_litro'], 2);
}
if (!empty($extra['merma'])) {
$detalle .= PHP_EOL . '📉 <b>Merma:</b> ' . (float) $extra['merma'];
}
if (!empty($extra['tad'])) {
$detalle .= PHP_EOL . '📍 <b>TAD:</b> ' . $extra['tad'];
}

$transporte = '';

if (!empty($extra['nom_transporte'])) {
$transporte .= PHP_EOL . '🚚 <b>Transportista:</b> ' . $extra['nom_transporte'];
}
if (!empty($extra['chofer'])) {
$transporte .= PHP_EOL . '👨‍✈️ <b>Chofer:</b> ' . $extra['chofer'];
}
if (!empty($extra['unidad'])) {
$transporte .= PHP_EOL . '🚛 <b>Unidad:</b> ' . $extra['unidad'];
}

$mensaje = '✏️ Se ha actualizado un embarque en el apartado de <b>Resumen de Embarques</b>, correspondiente al periodo de <b>'
. nombremes($mes) . ' ' . $year . '</b>:' . PHP_EOL . PHP_EOL
. '🚛 <b>Embarque:</b> ' . ($embarque ?: 'N/A') . PHP_EOL
. '📄 <b>Documento:</b> ' . ($documento ?: 'N/A')
. $detalle
. $transporte . PHP_EOL . PHP_EOL
. '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL
. '⛽ <b>Estación:</b> ' . $nombreES;

self::enviarTelegramEmb($idEstacion, $idUsuario, $mensaje);
} catch (\Throwable $e) {
error_log('Error en notificarUpdateEmb: ' . $e->getMessage());
}
}

public static function notificarDestroyEmb(int $idMes, int $idUsuario, string $nombreUsuario, string $embarque = '', array $extra = []): void
{
try {
$idEstacion = self::getEstacionIdFromMesIdEmb($idMes);
if (!$idEstacion) return;

$periodo = self::getMesYearEmb($idMes);
$estacion = Estacion::find($idEstacion);
$nombreES = $estacion ? $estacion->nombre : 'Desconocida';
$corteMes = CorteMes::with('year')->find($idMes);
$mes = $corteMes ? (int) $corteMes->mes : 0;
$year = $corteMes && $corteMes->year ? (int) $corteMes->year->year : 0;
$fechaHora = date('Y-m-d H:i:s');

$detalle = '';

if (!empty($extra['fecha'])) {
$detalle .= PHP_EOL . '📅 <b>Fecha:</b> ' . $extra['fecha'];
}
if (!empty($extra['producto'])) {
$detalle .= PHP_EOL . '🛢️ <b>Producto:</b> ' . $extra['producto'];
}
if (!empty($extra['importef'])) {
$detalle .= PHP_EOL . '📏 <b>Litros factura:</b> ' . (float) $extra['importef'];
}
if (!empty($extra['precio_litro'])) {
$detalle .= PHP_EOL . '💲 <b>Precio por litro:</b> $' . number_format((float) $extra['precio_litro'], 2);
}
if (!empty($extra['nom_transporte'])) {
$detalle .= PHP_EOL . '🚚 <b>Transportista:</b> ' . $extra['nom_transporte'];
}
if (!empty($extra['chofer'])) {
$detalle .= PHP_EOL . '👨‍✈️ <b>Chofer:</b> ' . $extra['chofer'];
}

$mensaje = '🗑️ Se ha eliminado un embarque en el apartado de <b>Resumen de Embarques</b>, correspondiente al periodo de <b>'
. nombremes($mes) . ' ' . $year . '</b>:' . PHP_EOL . PHP_EOL
. '🚛 <b>Embarque:</b> ' . ($embarque ?: 'N/A')
. $detalle . PHP_EOL . PHP_EOL
. '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL
. '⛽ <b>Estación:</b> ' . $nombreES;


self::enviarTelegramEmb($idEstacion, $idUsuario, $mensaje);
} catch (\Throwable $e) {
error_log('Error en notificarDestroyEmb: ' . $e->getMessage());
}
}
}
