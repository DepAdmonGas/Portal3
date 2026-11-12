<?php

namespace App\Services;

use App\Models\Operativo\EstimuloFiscalPago;
use App\Models\Sasisopa\ReporteCreMes;
use App\Models\Estacion;
use App\Core\Session;

class EstimuloFiscalService
{
public static function getProductoTotal(int $idEstacion, string $fechaInicio, string $fechaTermino, string $producto): float
{
return (float) ReporteCreMes::where('re_reporte_cre_mes.id_estacion', $idEstacion)
->join('re_reporte_cre_producto', 're_reporte_cre_mes.id', '=', 're_reporte_cre_producto.id_re_mes')
->join('re_reporte_cre_pipas', 're_reporte_cre_producto.id', '=', 're_reporte_cre_pipas.id_re_producto')
->where('re_reporte_cre_producto.producto', $producto)
->whereBetween('re_reporte_cre_producto.fecha', [$fechaInicio, $fechaTermino])
->sum('re_reporte_cre_pipas.volumen');
}

public static function getResumen(int $idEstacion, string $fechaInicio, string $fechaTermino): array
{
$gSuper = self::getProductoTotal($idEstacion, $fechaInicio, $fechaTermino, 'G SUPER');
$gPremium = self::getProductoTotal($idEstacion, $fechaInicio, $fechaTermino, 'G PREMIUM');
$gDiesel = self::getProductoTotal($idEstacion, $fechaInicio, $fechaTermino, 'G DIESEL');

$totalLitros = $gSuper + $gPremium + $gDiesel;
$totalPagar = $totalLitros * 0.02;

return [
'fecha_inicio' => $fechaInicio,
'fecha_termino' => $fechaTermino,
'g_super' => $gSuper,
'g_premium' => $gPremium,
'g_diesel' => $gDiesel,
'total_litros' => $totalLitros,
'total_pagar' => $totalPagar,
];
}

public static function getListaPagos(int $idEstacion): array
{
$pagos = EstimuloFiscalPago::where('id_estacion', $idEstacion)
->orderBy('id', 'desc')
->get()
->toArray();

$data = [];
foreach ($pagos as $p) {
$data[] = [
'id' => $p['id'],
'fecha_inicio' => $p['fecha_inicio'],
'fecha_inicio_formateada' => formatearFecha($p['fecha_inicio']),
'fecha_termino' => $p['fecha_termino'],
'fecha_termino_formateada' => formatearFecha($p['fecha_termino']),
'pdf' => $p['pdf'] ?? '',
'pdf_disponible' => self::getExistingArchivoPath((string) ($p['pdf'] ?? '')) !== null,
'xml' => $p['xml'] ?? '',
'xml_disponible' => self::getExistingArchivoPath((string) ($p['xml'] ?? '')) !== null,
'co_pdf' => $p['co_pdf'] ?? '',
'co_pdf_disponible' => self::getExistingArchivoPath((string) ($p['co_pdf'] ?? '')) !== null,
'co_xml' => $p['co_xml'] ?? '',
'co_xml_disponible' => self::getExistingArchivoPath((string) ($p['co_xml'] ?? '')) !== null,
];
}

return $data;
}

public static function getDetalle(int $id): ?array
{
$p = EstimuloFiscalPago::find($id);
if (!$p) return null;

return [
'id' => $p->id,
'id_estacion' => $p->id_estacion,
'fecha_inicio' => $p->fecha_inicio instanceof \Carbon\Carbon ? $p->fecha_inicio->format('Y-m-d') : (string) $p->fecha_inicio,
'fecha_termino' => $p->fecha_termino instanceof \Carbon\Carbon ? $p->fecha_termino->format('Y-m-d') : (string) $p->fecha_termino,
'pdf' => $p->pdf ?? '',
'pdf_disponible' => self::getExistingArchivoPath((string) ($p->pdf ?? '')) !== null,
'xml' => $p->xml ?? '',
'xml_disponible' => self::getExistingArchivoPath((string) ($p->xml ?? '')) !== null,
'co_pdf' => $p->co_pdf ?? '',
'co_pdf_disponible' => self::getExistingArchivoPath((string) ($p->co_pdf ?? '')) !== null,
'co_xml' => $p->co_xml ?? '',
'co_xml_disponible' => self::getExistingArchivoPath((string) ($p->co_xml ?? '')) !== null,
];
}

public static function guardar(int $idEstacion, array $data, ?array $filePDF, ?array $fileXML): int
{
$archivosGuardados = [];
$pdf = self::storeUploadedFile($filePDF);
if (!$pdf) return 0;
$archivosGuardados[] = $pdf;

$xml = self::storeUploadedFile($fileXML);
if (!$xml) {
self::removeStoredFiles($archivosGuardados);
return 0;
}
$archivosGuardados[] = $xml;

try {
$pago = EstimuloFiscalPago::create([
'id_estacion' => $idEstacion,
'fecha_inicio' => $data['fecha_inicio'],
'fecha_termino' => $data['fecha_termino'],
'pdf' => $pdf,
'xml' => $xml,
]);
return $pago->id;
} catch (\Throwable $e) {
self::removeStoredFiles($archivosGuardados);
return 0;
}
}

public static function editar(int $id, array $data, ?array $files): bool
{
$pago = EstimuloFiscalPago::find($id);
if (!$pago) return false;

$update = [
'fecha_inicio' => $data['fecha_inicio'],
'fecha_termino' => $data['fecha_termino'],
];

$fileFields = ['EPDF_file' => 'pdf', 'EXML_file' => 'xml', 'CPDF_file' => 'co_pdf', 'CXML_file' => 'co_xml'];
$archivosGuardados = [];
$archivosAnteriores = [];

foreach ($fileFields as $field => $column) {
$file = $files[$field] ?? null;
if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
self::removeStoredFiles($archivosGuardados);
return false;
}

$nombre = self::storeUploadedFile($file);
if (!$nombre) {
self::removeStoredFiles($archivosGuardados);
return false;
}
$archivosGuardados[] = $nombre;
if (!empty($pago->$column)) $archivosAnteriores[] = (string) $pago->$column;
$update[$column] = $nombre;
}

$actualizado = $pago->update($update);
if (!$actualizado) {
self::removeStoredFiles($archivosGuardados);
return false;
}
self::removeStoredFiles($archivosAnteriores);
return true;
}

public static function eliminar(int $id): bool
{
$pago = EstimuloFiscalPago::find($id);
if (!$pago) return false;

$fields = ['pdf', 'xml', 'co_pdf', 'co_xml'];
foreach ($fields as $field) {
if ($pago->$field) {
self::removeStoredFiles([(string) $pago->$field]);
}
}

return $pago->delete();
}

private static function storeUploadedFile(?array $file): ?string
{
if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) return null;

$originalName = basename(str_replace('\\', '/', (string) ($file['name'] ?? '')));
if ($originalName === '') return null;

$directory = self::getUploadDir();
if (!$directory) return null;
$filename = uniqid('', true) . '-' . $originalName;
return move_uploaded_file($file['tmp_name'], $directory . $filename) ? $filename : null;
}

private static function getUploadDir(): ?string
{
$directory = dirname(__DIR__, 2) . '/public/uploads/archivos/estimulo-fiscal';
if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) return null;
$realDirectory = realpath($directory);
return $realDirectory !== false ? $realDirectory . DIRECTORY_SEPARATOR : null;
}

private static function getExistingArchivoPath(string $filename): ?string
{
$filename = basename(str_replace('\\', '/', $filename));
if ($filename === '' || $filename === '.' || $filename === '..') return null;

$root = dirname(__DIR__, 2) . '/public/uploads/archivos';
$candidates = [
$root . '/estimulo-fiscal/' . $filename,
$root . '/' . $filename,
];
foreach ($candidates as $candidate) {
$realPath = realpath($candidate);
$realRoot = realpath($root);
if ($realPath && $realRoot && is_file($realPath) && str_starts_with($realPath, $realRoot . DIRECTORY_SEPARATOR)) return $realPath;
}
return null;
}

private static function removeStoredFiles(array $filenames): void
{
foreach ($filenames as $filename) {
$path = self::getExistingArchivoPath((string) $filename);
if ($path && is_file($path)) @unlink($path);
}
}

public static function getPermisos(): array
{
$usuario = Session::get('usuario');
return [
'multiestacion' => !empty($usuario['multiestacion']),
'id_puesto' => $usuario['id_puesto'] ?? 0,
'nombre_puesto' => $usuario['nompuesto'] ?? '',
];
}
 
public static function notificarTelegram(string $accion, array $data): void
{
$usuario = Session::get('usuario');
$nombreUsuario = $usuario['nombre'] ?? 'Usuario';
$idEstacion = $data['id_estacion'];
$fechaInicio = formatearFecha($data['fecha_inicio']);
$fechaTermino = formatearFecha($data['fecha_termino']);

$estacionNombre = '';
$estacion = Estacion::find($idEstacion);
if ($estacion) {
$estacionNombre = $estacion->nombre;
}

$iconos = [
'agregar' => "📄",
'editar' => "✏️",
'eliminar' => "🗑️",
];
$icono = $iconos[$accion] ?? '';

$accionTexto = [
'agregar' => 'agregado',
'editar' => 'editado',
'eliminar' => 'eliminado',
];
$texto = $accionTexto[$accion] ?? 'modificado';

$mensaje = $icono . ' Se ha <b>' . $texto . '</b> un comprobante de pago en el apartado de <b>Estímulo Fiscal</b>, correspondiente al módulo de <b>Corporativo</b> periodo de '. $fechaInicio . ' al ' . $fechaTermino . PHP_EOL . PHP_EOL
. "👤 <b>Responsable:</b> " . $nombreUsuario . PHP_EOL
. "⛽ <b>Estación:</b> " . $estacionNombre . '.';

$multiestacion = !empty($usuario['multiestacion']);
if (!$multiestacion) {
TelegramService::notificar($idEstacion, $usuario['id'] ?? 0, $mensaje);
}
}
}
