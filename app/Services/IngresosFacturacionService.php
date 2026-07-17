<?php

namespace App\Services;

use App\Models\Operativo\IngresosFacturacionContabilidad;
use App\Models\Operativo\IngresosFacturacionArchivo;
use App\Models\Operativo\CorteYear;
use App\Models\Estacion;
use App\Core\Session;
use Illuminate\Database\Capsule\Manager as Capsule;

class IngresosFacturacionService
{
public static function getOrCreateYearReporte(int $idEstacion, int $year): int
{
$corteYear = CorteYear::firstOrCreate(
['id_estacion' => $idEstacion, 'year' => $year],
['id_estacion' => $idEstacion, 'year' => $year]
);

self::validaConceptos($corteYear->id);

return $corteYear->id;
}

public static function getData(int $idReporte): array
{
$posicion1 = IngresosFacturacionContabilidad::where('id_year', $idReporte)
->where('posicion', 1)
->orderBy('id')
->get()
->toArray();

$posicion2 = IngresosFacturacionContabilidad::where('id_year', $idReporte)
->where('posicion', 2)
->orderBy('id')
->get()
->toArray();

$totales = self::calcularTotales($idReporte);

return [
'posicion1' => $posicion1,
'posicion2' => $posicion2,
'totales' => $totales,
];
}

public static function calcularTotales(int $idReporte): array
{
$meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

$t1 = array_fill_keys($meses, 0);
$t2 = array_fill_keys($meses, 0);
$tej1 = 0;
$tej2 = 0;

$rows1 = IngresosFacturacionContabilidad::where('id_year', $idReporte)->where('posicion', 1)->get();
foreach ($rows1 as $r) {
foreach ($meses as $m) {
$t1[$m] += (float) ($r->$m ?? 0);
}
$tej1 += $r->enero + $r->febrero + $r->marzo + $r->abril + $r->mayo + $r->junio + $r->julio + $r->agosto + $r->septiembre + $r->octubre + $r->noviembre + $r->diciembre;
}

$rows2 = IngresosFacturacionContabilidad::where('id_year', $idReporte)->where('posicion', 2)->get();
foreach ($rows2 as $r) {
foreach ($meses as $m) {
$t2[$m] += (float) ($r->$m ?? 0);
}
$tej2 += $r->enero + $r->febrero + $r->marzo + $r->abril + $r->mayo + $r->junio + $r->julio + $r->agosto + $r->septiembre + $r->octubre + $r->noviembre + $r->diciembre;
}

$diferencias = [];
foreach ($meses as $m) {
$diferencias[$m] = $t2[$m] - $t1[$m];
}
$difTE = $tej2 - $tej1;

return [
'TC1' => $t1,
'TEJ1' => $tej1,
'TC2' => $t2,
'TEJ2' => $tej2,
'TD' => $diferencias,
'TDTE' => $difTE,
];
}

public static function getTotalesJson(int $idReporte): array
{
$t = self::calcularTotales($idReporte);
$mesAbr = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];

$data = [];
foreach ($mesAbr as $i => $m) {
$idx = $i + 1;
$data["T1$idx"] = self::fmt($t['TC1'][$m]);
$data["T2$idx"] = self::fmt($t['TC2'][$m]);
$data["TD$idx"] = self::fmt($t['TD'][$m]);
}
$data['TF1'] = self::fmt($t['TEJ1']);
$data['TF2'] = self::fmt($t['TEJ2']);
$data['TDTE'] = self::fmt($t['TDTE']);

return $data;
}

public static function getDataForApi(int $idEstacion, int $year): array
{
$idReporte = self::getOrCreateYearReporte($idEstacion, $year);
$data = self::getData($idReporte);
$permisos = self::getPermisos();
$yearsDisponibles = self::getYearsPorEstacion($idEstacion);

return [
'success' => true,
'id_reporte' => $idReporte,
'id_estacion' => $idEstacion,
'posicion1' => $data['posicion1'],
'posicion2' => $data['posicion2'],
'totales' => $data['totales'],
'permisos' => $permisos,
'years_disponibles' => $yearsDisponibles,
];
}

private static function fmt($v): string
{
return '$ ' . number_format((float) $v, 2);
}

public static function updateCell(int $id, float $valor, int $mes): bool
{
$columna = self::mesToColumna($mes);
if (!$columna) return false;

return IngresosFacturacionContabilidad::where('id', $id)->update([$columna => $valor]);
}

private static function mesToColumna(int $mes): ?string
{
$map = [
1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
];
return $map[$mes] ?? null;
}

public static function getArchivos(int $idReporte): array
{
$archivos = IngresosFacturacionArchivo::where('id_year', $idReporte)
->orderBy('fecha', 'desc')
->get()
->toArray();

foreach ($archivos as &$a) {
$a['fecha_formateada'] = !empty($a['fecha']) ? formatearFecha($a['fecha']) : '';
}

return $archivos;
}

public static function saveArchivo(int $idReporte, array $file): bool
{
$aleatorio = uniqid();
$filePdf = $file['name'];
$uploadDir = self::getUploadDir();

if ($file['error'] !== UPLOAD_ERR_OK) return false;

$folderPDF = $uploadDir . $aleatorio . '-' . $filePdf;

$pdf = '';
if (move_uploaded_file($file['tmp_name'], $folderPDF)) {
$pdf = $aleatorio . '-' . $filePdf;
}

if (!$pdf) return false;

IngresosFacturacionArchivo::create([
'id_year' => $idReporte,
'archivo' => $pdf,
'fecha' => date('Y-m-d H:i:s'),
]);

return true;
}

public static function deleteArchivo(int $id): bool
{
$archivo = IngresosFacturacionArchivo::find($id);
if (!$archivo) return false;

$uploadDir = self::getUploadDir();
$ruta = $uploadDir . $archivo->archivo;
if (file_exists($ruta)) {
unlink($ruta);
}

return $archivo->delete();
}

private static function getUploadDir(): string
{
$dir = realpath(__DIR__ . '/../../public/uploads/archivos/ingresos-facturacion');
if (!$dir) {
$dir = __DIR__ . '/../../public/uploads/archivos/ingresos-facturacion';
if (!is_dir($dir)) {
mkdir($dir, 0755, true);
}
$dir = realpath($dir);
}
return $dir . '/';
}

public static function getPermisos(): array
{
$usuario = Session::get('usuario');
$idPuesto = $usuario['id_puesto'] ?? 0;
$multiestacion = $usuario['multiestacion'] ?? false;
$esDireccionOperaciones = ($idPuesto == 1);
$esCorporativo  = ($idPuesto == 2);
$tipoPuesto = $usuario['tipo_puesto'] ?? '';

return [
'multiestacion' => $multiestacion,
'es_direccion_operaciones' => $esDireccionOperaciones,
'es_corporativo' => $esCorporativo,
'tipo_puesto' => $tipoPuesto,
'id_puesto' => $idPuesto,
];
}

private static function validaConceptos(int $idReporte): void
{
$conceptos1 = ['G SUPER', 'G PREMIUM', 'Aceites y Lubricantes', 'Rentas', 'IEPS'];
$conceptos2 = [
'Público en General', 'Clientes crédito', 'Monederos electronicos',
'Facturas aceites y lubricantes', 'Clientes débito', 'Ventas mostrador',
'TPV', 'Página WEB', 'ERP',
];

foreach ($conceptos1 as $c) {
self::validaConcepto($idReporte, $c, 1);
}
foreach ($conceptos2 as $c) {
self::validaConcepto($idReporte, $c, 2);
}
}

private static function validaConcepto(int $idReporte, string $detalle, int $posicion): void
{
$existe = IngresosFacturacionContabilidad::where('id_year', $idReporte)
->where('detalle', $detalle)
->where('posicion', $posicion)
->exists();

if (!$existe) {
IngresosFacturacionContabilidad::create([
'id_year' => $idReporte,
'detalle' => $detalle,
'posicion' => $posicion,
]);
}
}

public static function getYearsPorEstacion(int $idEstacion): array
{
return CorteYear::where('id_estacion', $idEstacion)
->select('year')
->groupBy('year')
->orderBy('year', 'desc')
->pluck('year')
->toArray();
}

public static function getAllYears(): array
{
return CorteYear::select('year')
->groupBy('year')
->orderBy('year', 'desc')
->pluck('year')
->toArray();
}

public static function notificarAgregarArchivo(int $idReporte, int $idUsuario, string $nombreUsuario, string $archivoNombre): void
{
$corteYear = CorteYear::find($idReporte);
if (!$corteYear) return;

$nombreES = Estacion::find($corteYear->id_estacion)->nombre ?? 'Desconocida';

$mensaje = '📎 Se ha agregado un nuevo documento en la sección de <b>Entregables</b> del apartado de <b>Ingresos vs Facturación</b>, correspondiente al año <b>' . $corteYear->year . '</b>:' . PHP_EOL . PHP_EOL
. '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL
. '⛽ <b>Estación:</b> ' . $nombreES;

self::enviarTelegramIF($corteYear->id_estacion, $idUsuario, $mensaje);
}

public static function notificarEliminarArchivo(int $idReporte, int $idUsuario, string $nombreUsuario, string $archivoNombre): void
{
$corteYear = CorteYear::find($idReporte);
if (!$corteYear) return;

$nombreES = Estacion::find($corteYear->id_estacion)->nombre ?? 'Desconocida';

$mensaje = '🗑️ Se ha eliminado un documento en la sección de <b>Entregables</b> del apartado de <b>Ingresos vs Facturación</b>, correspondiente al año <b>' . $corteYear->year . '</b>:' . PHP_EOL . PHP_EOL
. '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL
. '⛽ <b>Estación:</b> ' . $nombreES;

self::enviarTelegramIF($corteYear->id_estacion, $idUsuario, $mensaje);
}

private static function enviarTelegramIF(int $idEstacion, int $excludeUserId, string $mensaje): void
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
}
