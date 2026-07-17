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
'xml' => $p['xml'] ?? '',
'co_pdf' => $p['co_pdf'] ?? '',
'co_xml' => $p['co_xml'] ?? '',
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
'xml' => $p->xml ?? '',
'co_pdf' => $p->co_pdf ?? '',
'co_xml' => $p->co_xml ?? '',
];
}

public static function guardar(int $idEstacion, array $data, ?array $filePDF, ?array $fileXML): int
{
$aleatorio = uniqid();
$pdf = '';
$xml = '';

if ($filePDF && !empty($filePDF['tmp_name'])) {
$nombre = $aleatorio . '-' . $filePDF['name'];
$ruta = __DIR__ . '/../../public/uploads/archivos/estimulo-fiscal/' . $nombre;
$dir = dirname($ruta);
if (!is_dir($dir)) mkdir($dir, 0755, true);
if (move_uploaded_file($filePDF['tmp_name'], $ruta)) {
$pdf = $nombre;
}
}

if ($fileXML && !empty($fileXML['tmp_name'])) {
$nombre = $aleatorio . '-' . $fileXML['name'];
$ruta = __DIR__ . '/../../public/uploads/archivos/estimulo-fiscal/' . $nombre;
$dir = dirname($ruta);
if (!is_dir($dir)) mkdir($dir, 0755, true);
if (move_uploaded_file($fileXML['tmp_name'], $ruta)) {
$xml = $nombre;
}
}

$pago = EstimuloFiscalPago::create([
'id_estacion' => $idEstacion,
'fecha_inicio' => $data['fecha_inicio'],
'fecha_termino' => $data['fecha_termino'],
'pdf' => $pdf,
'xml' => $xml,
]);

return $pago->id;
}

public static function editar(int $id, array $data, ?array $files): bool
{
$pago = EstimuloFiscalPago::find($id);
if (!$pago) return false;

$update = [
'fecha_inicio' => $data['fecha_inicio'],
'fecha_termino' => $data['fecha_termino'],
];

$aleatorio = uniqid();
$fileFields = ['EPDF_file' => 'pdf', 'EXML_file' => 'xml', 'CPDF_file' => 'co_pdf', 'CXML_file' => 'co_xml'];

foreach ($fileFields as $field => $column) {
$file = $files[$field] ?? null;
if ($file && !empty($file['tmp_name'])) {
$nombre = $aleatorio . '-' . $file['name'];
$ruta = __DIR__ . '/../../public/uploads/archivos/estimulo-fiscal/' . $nombre;
$dir = dirname($ruta);
if (!is_dir($dir)) mkdir($dir, 0755, true);
if (move_uploaded_file($file['tmp_name'], $ruta)) {
$update[$column] = $nombre;
}
}
}

return $pago->update($update);
}

public static function eliminar(int $id): bool
{
$pago = EstimuloFiscalPago::find($id);
if (!$pago) return false;

$fields = ['pdf', 'xml', 'co_pdf', 'co_xml'];
foreach ($fields as $field) {
if ($pago->$field) {
$ruta = __DIR__ . '/../../public/uploads/archivos/estimulo-fiscal/' . $pago->$field;
if (file_exists($ruta)) {
unlink($ruta);
}
}
}

return $pago->delete();
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
