<?php

namespace App\Services;

use App\Models\Operativo\Contrato;
use App\Models\Estacion;
use App\Core\Session;

class ContratosService
{
public static function getData(int $idEstacion, string $categoria): array
{
$contratos = Contrato::where('id_estacion', $idEstacion)
->where('categoria', $categoria)
->orderBy('fecha', 'desc')
->get()
->toArray();

$data = [];
$idx = 1;
foreach ($contratos as $c) {
$data[] = [
'id' => $c['id_contratos'],
'num' => $idx++,
'fecha' => $c['fecha'],
'fecha_formateada' => formatearFecha($c['fecha']),
'descripcion' => $c['descripcion'],
'archivo' => $c['archivo'] ?? '',
];
}

return $data;
}

public static function getDetalle(int $id): ?array
{
$c = Contrato::find($id);
if (!$c) return null;

$fecha = $c->fecha instanceof \Carbon\Carbon ? $c->fecha->format('Y-m-d') : (string) $c->fecha;
$vencimientoRaw = null;
if ($c->vencimiento && $c->vencimiento !== '0000-00-00') {
$vencimientoRaw = $c->vencimiento instanceof \Carbon\Carbon ? $c->vencimiento->format('Y-m-d') : (string) $c->vencimiento;
}

return [
'id' => $c->id_contratos,
'id_estacion' => $c->id_estacion,
'fecha' => $fecha,
'fecha_formateada' => formatearFecha($c->fecha),
'descripcion' => $c->descripcion ?: 'S/I',
'archivo' => $c->archivo,
'objeto' => $c->objeto ?: 'S/I',
'proveedor' => $c->proveedor ?: 'S/I',
'vencimiento' => $c->vencimiento && $c->vencimiento !== '0000-00-00'
? formatearFecha($c->vencimiento) : 'S/I',
'vencimiento_raw' => $vencimientoRaw,
'firmas' => $c->firmas ?: 'S/I',
'comentario' => $c->comentario ?: 'Sin comentarios.',
'categoria' => $c->categoria,
];
}

public static function guardar(array $data, ?array $file): int
{
$archivo = '';
if ($file && $file['tmp_name']) {
$aleatorio = uniqid();
$nombre = $aleatorio . '-' . $file['name'];
$ruta = __DIR__ . '/../../public/uploads/archivos/contratos/' . $nombre;
$dir = dirname($ruta);
if (!is_dir($dir)) {
mkdir($dir, 0755, true);
}
if (move_uploaded_file($file['tmp_name'], $ruta)) {
$archivo = $nombre;
}
}

$contrato = Contrato::create([
'id_estacion' => $data['id_estacion'],
'fecha' => $data['fecha'],
'descripcion' => $data['descripcion'],
'archivo' => $archivo,
'objeto' => $data['objeto'] ?? '',
'proveedor' => $data['proveedor'] ?? '',
'vencimiento' => $data['vencimiento'] ?? null,
'firmas' => $data['firmas'] ?? '',
'comentario' => $data['comentario'] ?? '',
'categoria' => $data['categoria'],
]);

return $contrato->id_contratos;
}

public static function editar(int $id, array $data, ?array $file): bool
{
$contrato = Contrato::find($id);
if (!$contrato) return false;

$update = [
'fecha' => $data['fecha'],
'descripcion' => $data['descripcion'],
'objeto' => $data['objeto'] ?? '',
'proveedor' => $data['proveedor'] ?? '',
'vencimiento' => $data['vencimiento'] ?? null,
'firmas' => $data['firmas'] ?? '',
'comentario' => $data['comentario'] ?? '',
];

if ($file && $file['tmp_name']) {
$aleatorio = uniqid();
$nombre = $aleatorio . '-' . $file['name'];
$ruta = __DIR__ . '/../../public/uploads/archivos/contratos/' . $nombre;
$dir = dirname($ruta);
if (!is_dir($dir)) {
mkdir($dir, 0755, true);
}
if (move_uploaded_file($file['tmp_name'], $ruta)) {
$update['archivo'] = $nombre;
}
}

return $contrato->update($update);
}

public static function eliminar(int $id): bool
{
$contrato = Contrato::find($id);
if (!$contrato) return false;

if ($contrato->archivo) {
$ruta = __DIR__ . '/../../public/uploads/archivos/contratos/' . $contrato->archivo;
if (file_exists($ruta)) {
unlink($ruta);
}
}

return $contrato->delete();
}

public static function getPermisos(): array
{
$usuario = Session::get('usuario');
return [
'multiestacion' => !empty($usuario['multiestacion']),
'id_puesto' => $usuario['id_puesto'] ?? 0,
];
}

public static function notificarTelegram(string $accion, array $data): void
{
$usuario = Session::get('usuario');
$nombreUsuario = $usuario['nombre'] ?? 'Usuario';
$idEstacion = $data['id_estacion'];
$descripcion = $data['descripcion'];
$categoria = $data['categoria'];
$tipoContrato = $categoria === 'almacen' ? 'Importación' : $categoria;
$fecha = formatearFecha($data['fecha']);

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

$mensaje = $icono . ' Se ha <b>' . $texto . '</b> un contrato en el apartado de <b>Contratos</b> correspondiente al modulo de <b>' . $tipoContrato . '</b>:' . PHP_EOL . PHP_EOL
. "📄 <b>Descripción del contrato:</b> " . $descripcion . PHP_EOL
. "📅 <b>Fecha:</b> " . $fecha . PHP_EOL . PHP_EOL
. "👤 <b>Responsable:</b> " . $nombreUsuario . PHP_EOL
. "⛽ <b>Estación:</b> " . $estacionNombre . '.';

TelegramService::notificar($idEstacion, $usuario['id'] ?? 0, $mensaje);
}
}
