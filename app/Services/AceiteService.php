<?php

namespace App\Services;

use App\Models\Operativo\Aceite;
use App\Models\Operativo\AceiteLubricante;
use App\Models\Operativo\AceiteLubricanteReporte;
use App\Models\Operativo\AceiteLubricanteReporteFinalizar;
use App\Models\Operativo\AceiteLubricanteReportePagoDiferencia;
use App\Models\Operativo\AceiteDocumento;
use App\Models\Operativo\AceiteFactura;
use App\Models\Operativo\CorteYear;
use App\Models\Operativo\CorteMes;
use App\Models\Operativo\CorteDia;
use App\Models\Operativo\InventarioAceite;
use App\Models\EventoPortal2025;
use App\Models\Estacion;
use App\Core\Auth;
use App\Core\Session;

class AceiteService
{
public static function getNombreEstacion(int $idEstacion): string
{
$estacion = \App\Models\Estacion::find($idEstacion);
return $estacion ? $estacion->nombre : '';
}
public static function getPermisos(): array
{
$sessionUsuario = Session::get('usuario');
$multiEstacion = $sessionUsuario['multiestacion'] ?? false;

$usuario = Auth::user();
$esDireccionOperaciones = false;
if ($usuario && $usuario->puesto) {
$esDireccionOperaciones = ($usuario->puesto->tipo_puesto ?? '') === 'Dirección de operaciones';
}

$permisos = ModuloDptoOperativoService::permisosSesion('corporativo');

return [
'multiestacion' => $multiEstacion,
'es_direccion_operaciones' => $esDireccionOperaciones,
'id_usuario' => $sessionUsuario['id'] ?? 0,
'puedeLeer' => !empty($permisos['leer']),
'puedeCrear' => !empty($permisos['crear']),
'puedeEditar' => !empty($permisos['editar']),
'puedeEliminar' => !empty($permisos['eliminar']),
];
}

public static function getMesId(int $idEstacion, int $idYear, int $idMes): ?int
{
$corteYear = CorteYear::where('id_estacion', $idEstacion)
->where('year', $idYear)
->first();
if (!$corteYear) return null;

$corteMes = CorteMes::where('id_year', $corteYear->id)
->where('mes', $idMes)
->first();
return $corteMes ? $corteMes->id : null;
}

public static function asegurarRegistros(int $idMes, int $idEstacion, int $idYear, int $idMesNum): void
{
$aceites = InventarioAceite::where('id_estacion', $idEstacion)
->where('id_mes', $idMes)
->join('op_aceites', 'op_inventario_aceites.id_aceite', '=', 'op_aceites.id')
->select('op_aceites.id', 'op_aceites.id_aceite', 'op_aceites.concepto', 'op_aceites.precio',
'op_inventario_aceites.exhibidores', 'op_inventario_aceites.bodega')
->get();

foreach ($aceites as $a) {
$existe = AceiteLubricanteReporte::where('id_mes', $idMes)
->where('concepto', $a->concepto)
->exists();

if (!$existe) {
AceiteLubricanteReporte::create([
'id_mes' => $idMes,
'id_aceite' => $a->id_aceite,
'concepto' => $a->concepto,
'precio' => $a->precio,
'bodega' => $a->bodega,
'exibidores' => $a->exhibidores,
'pedido' => 0,
'inventario_bodega' => 0,
'inventario_exibidores' => 0,
'producto_facturado' => 0,
'factura_venta_mostrador' => 0,
]);
}
}
}

public static function getReporte(int $idMes): array
{
$rows = AceiteLubricanteReporte::where('id_mes', $idMes)
->where('id_aceite', '!=', 0)
->orderBy('id_aceite', 'ASC')
->get()
->toArray();

// Pre-cargar todas las ventas diarias para todos los aceites usando SUM(cantidad) por día/aceite
$dias = CorteDia::where('id_mes', $idMes)->orderBy('fecha', 'ASC')->get();
$idAceites = array_column($rows, 'id_aceite');
$ventasPorDia = [];

if (!empty($idAceites) && $dias->isNotEmpty()) {
$diaIds = $dias->pluck('id')->toArray();
$diaNumByDiaId = [];
foreach ($dias as $dia) {
$diaNumByDiaId[$dia->id] = (int) $dia->fecha->format('d');
}

// Aggregate SUM(cantidad) and SUM(cantidad*precio_unitario) per aceite per day
$agregado = AceiteLubricante::whereIn('id_aceite', $idAceites)
->whereIn('idreporte_dia', $diaIds)
->select('id_aceite', 'idreporte_dia')
->selectRaw('SUM(cantidad) as total_cantidad')
->selectRaw('SUM(cantidad * precio_unitario) as total_importe')
->groupBy('id_aceite', 'idreporte_dia')
->get();

foreach ($agregado as $row) {
$idAceite = (int) $row->id_aceite;
$diaId = (int) $row->idreporte_dia;
$diaNum = $diaNumByDiaId[$diaId] ?? 0;
$cantidad = (int) $row->total_cantidad;

if (!isset($ventasPorDia[$idAceite])) {
$ventasPorDia[$idAceite] = [];
}
$ventasPorDia[$idAceite][$diaNum] = [
'cantidad' => $cantidad,
'importe' => (float) $row->total_importe,
];
}

// Fill in zeros for days with no sales, ensuring all days are represented
foreach ($idAceites as $idAceite) {
if (!isset($ventasPorDia[$idAceite])) {
$ventasPorDia[$idAceite] = [];
}
foreach ($dias as $dia) {
$diaNum = (int) $dia->fecha->format('d');
if (!isset($ventasPorDia[$idAceite][$diaNum])) {
$ventasPorDia[$idAceite][$diaNum] = [
'cantidad' => 0,
'importe' => 0,
];
}
}
}
}

$data = [];
$totals = [
'bodega' => 0, 'exibidores' => 0, 'inventarioI' => 0,
'pedido' => 0, 'ventasM' => 0, 'inventarioF' => 0,
'inventario_bodega' => 0, 'inventario_exibidores' => 0,
'inventario_final' => 0, 'diferencia' => 0, 'difPrecio' => 0,
'factotal' => 0, 'diffactura' => 0,
];

foreach ($rows as &$r) {
$bodega = (int)$r['bodega'];
$exibidores = (int)$r['exibidores'];
$pedido = (int)$r['pedido'];
$invBodega = (int)$r['inventario_bodega'];
$invExibidores = (int)$r['inventario_exibidores'];
$prodFacturado = (float)$r['producto_facturado'];
$factVtaMostrador = (float)$r['factura_venta_mostrador'];

$ventasMes = 0;
if (isset($ventasPorDia[$r['id_aceite']])) {
foreach ($ventasPorDia[$r['id_aceite']] as $diaData) {
$ventasMes += (int)$diaData['cantidad'];
}
}

$inventarioI = $bodega + $exibidores;
$inventarioF = $inventarioI + $pedido - $ventasMes;
$inventarioFinal = $invBodega + $invExibidores;
$diferencia = $inventarioFinal - $inventarioF;
$difPrecio = (float)$r['precio'] * $diferencia;
$factotal = $factVtaMostrador + $prodFacturado;

$r['piezas'] = self::getPiezas($r['id_aceite']);
$r['ventas_mes'] = $ventasMes;
$r['inventario_inicial'] = $inventarioI;
$r['inventario_final_calc'] = $inventarioF;
$r['inventario_final_fisico'] = $inventarioFinal;
$r['diferencia'] = $diferencia;
$r['diferencia_precio'] = $difPrecio;
$r['factotal'] = $factotal;
$r['diffactura'] = $factotal;
$r['diarias'] = $ventasPorDia[$r['id_aceite']] ?? [];

$totals['bodega'] += $bodega;
$totals['exibidores'] += $exibidores;
$totals['inventarioI'] += $inventarioI;
$totals['pedido'] += $pedido;
$totals['ventasM'] += $ventasMes;
$totals['inventarioF'] += $inventarioF;
$totals['inventario_bodega'] += $invBodega;
$totals['inventario_exibidores'] += $invExibidores;
$totals['inventario_final'] += $inventarioFinal;
$totals['diferencia'] += $diferencia;
$totals['difPrecio'] += $difPrecio;
$totals['factotal'] += $factotal;
}
unset($r);

return [
'rows' => $rows,
'totals' => $totals,
];
}

public static function getVentasDiarias(int $idMes, int $idAceite): array
{
$dias = CorteDia::where('id_mes', $idMes)
->orderBy('fecha', 'ASC')
->get();

$agregado = AceiteLubricante::whereIn('idreporte_dia', $dias->pluck('id')->toArray())
->where('id_aceite', $idAceite)
->select('idreporte_dia')
->selectRaw('SUM(cantidad) as total_cantidad')
->selectRaw('SUM(cantidad * precio_unitario) as total_importe')
->groupBy('idreporte_dia')
->get()
->keyBy('idreporte_dia');

$result = [];
foreach ($dias as $dia) {
$row = $agregado->get($dia->id);
$cantidad = $row ? (int) $row->total_cantidad : 0;

$result[] = [
'fecha' => $dia->fecha->format('Y-m-d'),
'cantidad' => $cantidad,
'importe' => $row ? (float) $row->total_importe : 0,
];
}

return $result;
}

private static function getPiezas(int $idAceite): int
{
$aceite = Aceite::where('id_aceite', $idAceite)->first();
return $aceite ? (int)$aceite->piezas : 0;
}

public static function guardarCampo(int $id, string $campo, $valor, bool $log = false): mixed
{
$permitidos = ['pedido', 'inventario_bodega', 'inventario_exibidores',
'producto_facturado', 'factura_venta_mostrador',
'bodega', 'exibidores'];
if (!in_array($campo, $permitidos)) return false;

$reporte = AceiteLubricanteReporte::find($id);
if (!$reporte) return false;

$reporte->$campo = $valor;
$saved = $reporte->save();

if ($saved && $log) {
self::guardarLog($id, $campo, $valor);
}

return $saved;
}

private static function guardarLog(int $idReporte, string $campo, $valor): void
{
$usuario = Auth::user();
$nombre = $usuario ? ($usuario->nombre . ' ' . $usuario->apellidos) : 'Sistema';
$logFile = __DIR__ . '/../../storage/logs/aceites.log';
$line = date('Y-m-d H:i:s') . " | $nombre | $campo=$valor | reporte_id=$idReporte\n";
@file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

public static function getDocumentos(int $idMes): array
{
$documentos = AceiteDocumento::where('id_mes', $idMes)
->orderBy('fecha', 'DESC')
->get()
->toArray();

return array_map(function ($f) {
$f['fecha_formateada'] = !empty($f['fecha']) ? formatearFecha($f['fecha']) : '';
return $f;
}, $documentos);

}

public static function subirDocumento(int $idMes, array $files): array
{
$uploadDir = __DIR__ . '/../../public/uploads/archivos/aceites-documentos/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$data = ['id_mes' => $idMes, 'fecha' => date('Y-m-d')];
$campos = ['ficha_deposito', 'imagen_bodega', 'factura_venta'];
$saved = [];

foreach ($campos as $campo) {
if (!empty($files[$campo]) && $files[$campo]['error'] === UPLOAD_ERR_OK) {
$aleatorio = uniqid();
$nombre = $aleatorio . '-' . basename($files[$campo]['name']);
if (move_uploaded_file($files[$campo]['tmp_name'], $uploadDir . $nombre)) {
$data[$campo] = $nombre;
$saved[] = $campo;
}
}
}

if (empty($saved)) {
return ['success' => false, 'message' => 'No se subió ningún archivo'];
}

AceiteDocumento::create($data);
return ['success' => true, 'message' => 'Documento(s) guardado(s) correctamente'];
}

public static function actualizarDocumento(int $id, int $idMes, array $files): array
{
$doc = AceiteDocumento::find($id);
if (!$doc) return ['success' => false, 'message' => 'Documento no encontrado'];

$uploadDir = __DIR__ . '/../../public/uploads/archivos/aceites-documentos/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$campos = ['ficha_deposito', 'imagen_bodega', 'factura_venta'];
foreach ($campos as $campo) {
if (!empty($files[$campo]) && $files[$campo]['error'] === UPLOAD_ERR_OK) {
if ($doc->$campo && file_exists($uploadDir . $doc->$campo)) {
unlink($uploadDir . $doc->$campo);
}
$aleatorio = uniqid();
$nombre = $aleatorio . '-' . basename($files[$campo]['name']);
if (move_uploaded_file($files[$campo]['tmp_name'], $uploadDir . $nombre)) {
$doc->$campo = $nombre;
}
}
}

$doc->save();
return ['success' => true, 'message' => 'Documento actualizado correctamente'];
}

public static function eliminarDocumento(int $id): array
{
$doc = AceiteDocumento::find($id);
if (!$doc) return ['success' => false, 'message' => 'Documento no encontrado'];

$uploadDir = __DIR__ . '/../../public/uploads/archivos/aceites-documentos/';
foreach (['ficha_deposito', 'imagen_bodega', 'factura_venta'] as $campo) {
if ($doc->$campo && file_exists($uploadDir . $doc->$campo)) {
unlink($uploadDir . $doc->$campo);
}
}

$doc->delete();
return ['success' => true, 'message' => 'Documento eliminado correctamente'];
}

public static function evaluarDocumento(int $id, string $campo, string $fecha, int $puntaje): array
{
$doc = AceiteDocumento::find($id);
if (!$doc) return ['success' => false, 'message' => 'Documento no encontrado'];

$doc->$campo = $fecha;
$campoPuntaje = $campo === 'fecha_evaluacion_ficha' ? 'puntaje_ficha' : 'puntaje_factura';
$doc->$campoPuntaje = $puntaje;
$doc->save();

return ['success' => true, 'message' => 'Evaluación guardada'];
}

public static function getFacturas(int $idMes): array
{
$facturas = AceiteFactura::where('id_mes', $idMes)
->orderBy('fecha', 'DESC')
->get()
->toArray();

return array_map(function ($f) {
$f['fecha_formateada'] = !empty($f['fecha']) ? formatearFecha($f['fecha']) : '';
return $f;
}, $facturas);
}

public static function subirFactura(int $idMes, string $fecha, string $concepto, array $file): array
{
$uploadDir = __DIR__ . '/../../public/uploads/archivos/aceites-facturas/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) {
return ['success' => false, 'message' => 'Archivo no válido'];
}

$aleatorio = uniqid();
$nombre = $aleatorio . '-' . basename($file['name']);

if (!move_uploaded_file($file['tmp_name'], $uploadDir . $nombre)) {
return ['success' => false, 'message' => 'Error al guardar archivo'];
}

AceiteFactura::create([
'id_mes' => $idMes,
'fecha' => $fecha,
'nombre_anexo' => $concepto,
'archivo' => $nombre,
]);

return ['success' => true, 'message' => 'Factura subida correctamente'];
}

public static function eliminarFactura(int $id): array
{
$factura = AceiteFactura::find($id);
if (!$factura) return ['success' => false, 'message' => 'Factura no encontrada'];

$uploadDir = __DIR__ . '/../../public/uploads/archivos/aceites-facturas/';
if ($factura->archivo && file_exists($uploadDir . $factura->archivo)) {
unlink($uploadDir . $factura->archivo);
}

$factura->delete();
return ['success' => true, 'message' => 'Factura eliminada correctamente'];
}

public static function evaluarFactura(int $id, string $fecha, int $puntaje): array
{
$factura = AceiteFactura::find($id);
if (!$factura) return ['success' => false, 'message' => 'Factura no encontrada'];

$factura->fecha_evaluacion = $fecha;
$factura->puntaje = $puntaje;
$factura->save();

return ['success' => true, 'message' => 'Evaluación guardada'];
}

public static function getDiferenciasPago(int $idMes): array
{
$diferencias = AceiteLubricanteReportePagoDiferencia::where('id_reporte', $idMes)
->orderBy('fecha', 'DESC')
->get()
->toArray();

return array_map(function ($d) {
$d['fecha_formateada'] = !empty($d['fecha']) ? formatearFecha($d['fecha']) : '';
return $d;
}, $diferencias);
}

public static function agregarDiferenciaPago(int $idAceite, int $idMes, string $nombreAceite, int $diferencia, string $comentario, array $file): array
{
$uploadDir = __DIR__ . '/../../public/uploads/archivos/aceites-diferencias/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$documento = '';
if (!empty($file) && $file['error'] === UPLOAD_ERR_OK) {
$aleatorio = uniqid();
$documento = $aleatorio . '-' . basename($file['name']);
move_uploaded_file($file['tmp_name'], $uploadDir . $documento);
}

AceiteLubricanteReportePagoDiferencia::create([
'id_aceite' => $idAceite,
'id_reporte' => $idMes,
'nomaceite' => $nombreAceite,
'diferencia' => $diferencia,
'fecha' => date('Y-m-d H:i:s'),
'documento' => $documento,
'comentario' => $comentario,
'estado' => 0,
]);

return ['success' => true, 'message' => 'Diferencia de pago registrada'];
}

public static function actualizarDocumentoDiferenciaPago(int $id, array $file): array
{
$pago = AceiteLubricanteReportePagoDiferencia::find($id);
if (!$pago) {
return ['success' => false, 'message' => 'Registro no encontrado'];
}

if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) {
return ['success' => false, 'message' => 'Seleccione un archivo PDF'];
}

$uploadDir = __DIR__ . '/../../public/uploads/archivos/aceites-diferencias/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$aleatorio = uniqid();
$documento = $aleatorio . '-' . basename($file['name']);
move_uploaded_file($file['tmp_name'], $uploadDir . $documento);

$pago->documento = $documento;
$pago->save();

return ['success' => true, 'message' => 'Documento actualizado'];
}

public static function finalizarInventario(int $idMes): array
{
$yaFinalizado = AceiteLubricanteReporteFinalizar::where('id_mes', $idMes)->exists();
if ($yaFinalizado) {
return ['success' => false, 'message' => 'El inventario ya ha sido finalizado'];
}

$corteMes = CorteMes::with('year')->find($idMes);
if (!$corteMes || !$corteMes->year) {
return ['success' => false, 'message' => 'Registro de mes no encontrado'];
}

$idEstacion = $corteMes->year->id_estacion;
$yearNum = (int) $corteMes->year->year;
$mesNum = (int) $corteMes->mes;

if ($mesNum == 12) {
$nextMes = 1;
$nextYear = $yearNum + 1;
} else {
$nextMes = $mesNum + 1;
$nextYear = $yearNum;
}

$nextCorteYear = CorteYear::firstOrCreate(
['id_estacion' => $idEstacion, 'year' => $nextYear],
['id_estacion' => $idEstacion, 'year' => $nextYear]
);

$nextCorteMes = CorteMes::firstOrCreate(
['id_year' => $nextCorteYear->id, 'mes' => $nextMes],
['id_year' => $nextCorteYear->id, 'mes' => $nextMes]
);

InventarioAceite::where('id_mes', $nextCorteMes->id)
->where('id_estacion', $idEstacion)
->delete();

$reportes = AceiteLubricanteReporte::where('id_mes', $idMes)->get();
foreach ($reportes as $r) {
$aceite = Aceite::where('id_aceite', $r->id_aceite)->first();
if ($aceite) {
InventarioAceite::create([
'id_mes' => $nextCorteMes->id,
'id_estacion' => $idEstacion,
'id_aceite' => $aceite->id,
'exhibidores' => (int) $r->inventario_exibidores,
'bodega' => (int) $r->inventario_bodega,
]);
}
}

self::aplicarDiferenciasMesSiguiente($idMes, $nextCorteMes->id);

AceiteLubricanteReporteFinalizar::create([
'id_mes' => $idMes,
'fecha' => date('Y-m-d H:i:s'),
]);

self::enviarNotificacionesFinalizacion($corteMes, $idEstacion, $yearNum, $mesNum);

return ['success' => true, 'message' => 'Inventario finalizado correctamente'];
}

public static function estaFinalizado(int $idMes): bool
{
return AceiteLubricanteReporteFinalizar::where('id_mes', $idMes)->exists();
}

private static function aplicarDiferenciasMesSiguiente(int $idMesActual, int $idMesSiguiente): void
{
$diferencias = AceiteLubricanteReportePagoDiferencia::where('id_reporte', $idMesActual)
->where('estado', 0)
->get();

foreach ($diferencias as $diff) {
$nextReporte = AceiteLubricanteReporte::where('id_mes', $idMesSiguiente)
->where('id_aceite', $diff->nomaceite)
->first();

if ($nextReporte) {
$nextReporte->inventario_bodega = (int)$nextReporte->inventario_bodega + (int)$diff->diferencia;
$nextReporte->save();
}

$diff->estado = 1;
$diff->save();
}
}

private static function enviarNotificacionesFinalizacion($corteMes, int $idEstacion, int $yearNum, int $mesNum): void
{
try {
$usuario = Auth::user();
$nombreUsuario = $usuario ? ($usuario->nombre . ' ' . $usuario->apellidos) : 'Sistema';
$sessionUsuario = Session::get('usuario');
$idUsuario = $sessionUsuario['id'] ?? 0;

$estacion = Estacion::find($idEstacion);
$nombreES = $estacion ? $estacion->nombre : '';

$mesNombre = nombremes($mesNum);
$eventoAccion = "Finalizó el Resumen de Aceites correspondiente al mes de {$mesNombre} del {$yearNum} de la estación {$nombreES}";
$detalleTelegram = "✅ {$nombreUsuario} finalizó el Resumen de Aceites correspondiente al mes de {$mesNombre} del {$yearNum}.\n\n⛽ Estación: {$nombreES}.";

if ($idUsuario) {
EventoPortal2025::create([
'id_usuario' => $idUsuario,
'fecha_creacion' => date('Y-m-d H:i:s'),
'accion' => $eventoAccion,
]);
}

$telegram = new TelegramService();
$userIds = $telegram->getUserIdsByStation($idEstacion, $idUsuario);

if (in_array($idEstacion, [6, 7])) {
$userIds = array_merge($userIds, $telegram->getUserIdsComercializadora($idUsuario));
}
if (in_array($idEstacion, [1, 2, 3, 4, 5, 14])) {
$userIds = array_merge($userIds, $telegram->getUserIdsContabilidad($idUsuario));
}

$telegram->sendMessageToMultiple($userIds, $detalleTelegram);

} catch (\Throwable $e) {
error_log('Error en notificaciones finalización aceites: ' . $e->getMessage());
}
}

public static function getResumenPuntajes(int $idMes): array
{
$documentos = AceiteDocumento::where('id_mes', $idMes)->get();
$totalFicha = 0;
$totalFactura = 0;
$countFicha = 0;
$countFactura = 0;

foreach ($documentos as $doc) {
if ($doc->puntaje_ficha !== null) {
$totalFicha += $doc->puntaje_ficha;
$countFicha++;
}
if ($doc->puntaje_factura !== null) {
$totalFactura += $doc->puntaje_factura;
$countFactura++;
}
}

$facturas = AceiteFactura::where('id_mes', $idMes)->get();
$totalFacturaAnexo = 0;
$countFacturaAnexo = 0;
foreach ($facturas as $f) {
if ($f->puntaje !== null) {
$totalFacturaAnexo += $f->puntaje;
$countFacturaAnexo++;
}
}

return [
'promedio_ficha' => $countFicha > 0 ? round($totalFicha / $countFicha, 2) : 0,
'promedio_factura_doc' => $countFactura > 0 ? round($totalFactura / $countFactura, 2) : 0,
'promedio_factura_anexo' => $countFacturaAnexo > 0 ? round($totalFacturaAnexo / $countFacturaAnexo, 2) : 0,
];
}

public static function procesarImportacionFacturas(int $idMes, array $data): array
{
$count = 0;
foreach ($data as $row) {
if (empty($row['fecha']) || empty($row['concepto']) || empty($row['archivo'])) continue;

$nombreArchivo = uniqid() . '-' . basename($row['archivo']);
AceiteFactura::create([
'id_mes' => $idMes,
'fecha' => $row['fecha'],
'nombre_anexo' => $row['concepto'],
'archivo' => $nombreArchivo,
]);
$count++;
}

return ['success' => true, 'message' => "$count factura(s) importada(s)"];
}

public static function getListaAceites(): array
{
return Aceite::orderBy('id_aceite', 'asc')->get()->toArray();
}

public static function actualizarAceite(int $id, string $campo, $valor): bool
{
$actualizado = Aceite::where('id', $id)->update([$campo => $valor]);
return $actualizado > 0;
}

public static function crearAceite(): array
{
$ultimo = Aceite::orderBy('id_aceite', 'desc')->first();
$nuevoIdAceite = $ultimo ? $ultimo->id_aceite + 1 : 1;

$aceite = Aceite::create([
'id_aceite' => $nuevoIdAceite,
'concepto' => '',
'piezas' => 0,
'precio' => 0,
]);

return $aceite->toArray();
}

public static function eliminarAceite(int $id): array
{
$aceite = Aceite::find($id);
if (!$aceite) {
return ['success' => false, 'message' => 'Aceite no encontrado'];
}
$aceite->delete();
return ['success' => true, 'message' => 'Aceite eliminado correctamente'];
}
}
