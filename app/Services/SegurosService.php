<?php

namespace App\Services;

use App\Models\Operativo\PolizaIncidencia;
use App\Models\Operativo\PolizaEs;
use App\Models\Operativo\RhLocalidad;
use App\Models\Estacion;
use App\Core\Session;
use App\Core\Auth;

class SegurosService
{
public static function getIncidencias(int $idEstacion): array
{
$rows = PolizaIncidencia::where('id_estacion', $idEstacion)
->orderBy('id_poliza_incidencia', 'desc')
->get();

$data = [];
$i = 1;
foreach ($rows as $r) {
$data[] = [
'num' => $i++,
'id' => $r->id_poliza_incidencia,
'fecha_raw' => $r->fecha instanceof \Carbon\Carbon ? $r->fecha->format('Y-m-d') : (string) $r->fecha,
'fecha' => formatearFecha($r->fecha),
'hora' => date('g:i a', strtotime($r->hora)),
'asunto' => $r->asunto,
'observaciones' => $r->observaciones,
'solucion' => $r->solucion,
'archivo' => $r->archivo ?? '',
];
}

return $data;
}

public static function getDetalleIncidencia(int $id): ?array
{
$r = PolizaIncidencia::find($id);
if (!$r) return null;

return [
'id' => $r->id_poliza_incidencia,
'id_estacion' => $r->id_estacion,
'fecha' => formatearFecha($r->fecha),
'fecha_raw' => $r->fecha instanceof \Carbon\Carbon ? $r->fecha->format('Y-m-d') : (string) $r->fecha,
'hora' => date('g:i a', strtotime($r->hora)),
'hora_raw' => $r->hora instanceof \Carbon\Carbon ? $r->hora->format('H:i') : (is_string($r->hora) ? substr($r->hora, 0, 5) : ''),
'asunto' => $r->asunto,
'observaciones' => $r->observaciones,
'solucion' => $r->solucion,
'archivo' => $r->archivo ?? '',
];
}

public static function guardarIncidencia(int $idEstacion, array $data, ?array $file): int
{
$archivo = self::uploadFile($file, 'incidencias-poliza-es');

$inc = PolizaIncidencia::create([
'id_estacion' => $idEstacion,
'fecha' => $data['fecha'],
'hora' => $data['hora'],
'asunto' => $data['asunto'],
'observaciones' => $data['observaciones'],
'solucion' => $data['solucion'],
'archivo' => $archivo,
]);

return $inc->id_poliza_incidencia;
}

public static function editarIncidencia(int $id, array $data, ?array $file): bool
{
$inc = PolizaIncidencia::find($id);
if (!$inc) return false;

$update = [
'fecha' => $data['fecha'],
'hora' => $data['hora'],
'asunto' => $data['asunto'],
'observaciones' => $data['observaciones'],
'solucion' => $data['solucion'],
];

$archivo = self::uploadFile($file, 'incidencias-poliza-es');
if ($archivo) {
$update['archivo'] = $archivo;
}

return $inc->update($update);
}

public static function eliminarIncidencia(int $id): bool
{
$inc = PolizaIncidencia::find($id);
if (!$inc) return false;

if ($inc->archivo) {
$ruta = __DIR__ . '/../../public/uploads/archivos/incidencias-poliza-es/' . $inc->archivo;
if (file_exists($ruta)) unlink($ruta);
}

return $inc->delete();
}

public static function getPolizas(int $idEstacion): array
{
$rows = PolizaEs::where('id_estacion', $idEstacion)
->orderBy('emision', 'desc')
->get();

$data = [];
foreach ($rows as $r) {
$data[] = [
'id' => $r->id_poliza,
'archivo' => $r->archivo ?? '',
'emision_raw' => $r->emision instanceof \Carbon\Carbon ? $r->emision->format('Y-m-d') : (string) $r->emision,
'emision' => formatearFecha($r->emision),
'vencimiento_raw' => $r->vencimiento instanceof \Carbon\Carbon ? $r->vencimiento->format('Y-m-d') : (string) $r->vencimiento,
'vencimiento' => formatearFecha($r->vencimiento),
];
}

return $data;
}

public static function getDetallePoliza(int $id): ?array
{
$r = PolizaEs::find($id);
if (!$r) return null;

return [
'id' => $r->id_poliza,
'id_estacion' => $r->id_estacion,
'emision' => $r->emision instanceof \Carbon\Carbon ? $r->emision->format('Y-m-d') : (string) $r->emision,
'vencimiento' => $r->vencimiento instanceof \Carbon\Carbon ? $r->vencimiento->format('Y-m-d') : (string) $r->vencimiento,
'archivo' => $r->archivo ?? '',
];
}

public static function guardarPoliza(int $idEstacion, array $data, ?array $file): int
{
$archivo = self::uploadFile($file, 'poliza-estacion');

$p = PolizaEs::create([
'id_estacion' => $idEstacion,
'emision' => $data['emision'],
'vencimiento' => $data['vencimiento'],
'archivo' => $archivo,
]);

return $p->id_poliza;
}

public static function editarPoliza(int $id, array $data, ?array $file): bool
{
$p = PolizaEs::find($id);
if (!$p) return false;

$update = [
'emision' => $data['emision'],
'vencimiento' => $data['vencimiento'],
];

$archivo = self::uploadFile($file, 'poliza-estacion');
if ($archivo) {
$update['archivo'] = $archivo;
}

return $p->update($update);
}

public static function eliminarPoliza(int $id): bool
{
$p = PolizaEs::find($id);
if (!$p) return false;

if ($p->archivo) {
$ruta = __DIR__ . '/../../public/uploads/archivos/poliza-estacion/' . $p->archivo;
if (file_exists($ruta)) unlink($ruta);
}

return $p->delete();
}

public static function getVencimiento(string $emision): string
{
return date('Y-m-d', strtotime($emision . '+ 1 year'));
}

private static function uploadFile(?array $file, string $subdir): string
{
if (!$file || empty($file['tmp_name'])) return '';

$aleatorio = uniqid();
$nombre = $aleatorio . '-' . $file['name'];
$ruta = __DIR__ . '/../../public/uploads/archivos/' . $subdir . '/' . $nombre;
$dir = dirname($ruta);

if (!is_dir($dir)) mkdir($dir, 0755, true);

if (move_uploaded_file($file['tmp_name'], $ruta)) {
return $nombre;
}

return '';
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

private static function rhIdToEstacionId(int $rhId): int
{
$loc = RhLocalidad::find($rhId);
if (!$loc) return 0;
$est = Estacion::where('numlista', $loc->numlista)->first();
return $est ? (int) $est->id : 0;
}

public static function notificarTelegram(string $accion, array $data): void
{
$usuario = Session::get('usuario');
$nombreUsuario = $usuario['nombre'] ?? 'Usuario';
$rhId = (int) ($data['id_estacion'] ?? 0);

$localidad = RhLocalidad::find($rhId);
$localidadNombre = $localidad ? $localidad->localidad : '';

$idEstacionNotif = self::rhIdToEstacionId($rhId);

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

$tipo = $data['tipo'] ?? 'incidencia';
$tipoLabel = $tipo === 'poliza' ? 'póliza de seguro' : 'registro de incidencia';

$extra = '';
if ($tipo === 'incidencia') {
$fecha = formatearFecha($data['fecha']);
$extra = PHP_EOL . "📅 <b>Fecha:</b> " . $fecha . PHP_EOL;
} elseif ($tipo === 'poliza') {
$emision = formatearFecha($data['emision']);
$vencimiento = formatearFecha($data['vencimiento']);
$extra = PHP_EOL . "📅 <b>Emisión:</b> " . $emision . PHP_EOL
. "📅 <b>Vencimiento:</b> " . $vencimiento . PHP_EOL;
}

$mensaje = $icono . ' Se ha <b>' . $texto . '</b> un ' . $tipoLabel . ' en el apartado de <b>Incidentes y Accidentes (Seguros)</b> correspondiente al modulo de <b>Corporativo</b>:' . PHP_EOL . PHP_EOL
. $extra
. "👤 <b>Responsable:</b> " . $nombreUsuario . PHP_EOL
. "⛽ <b>Estación:</b> " . $localidadNombre . '.';

$multiestacion = !empty($usuario['multiestacion']);
if ($multiestacion && $idEstacionNotif) {
TelegramService::notificar($idEstacionNotif, $usuario['id'] ?? 0, $mensaje);
}
}
}
