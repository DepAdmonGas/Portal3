<?php

namespace App\Services;

use App\Models\Operativo\VentasDia;
use App\Models\Operativo\VentasDiaOtros;
use App\Models\Operativo\Prosegur;
use App\Models\Operativo\TarjetasCB;
use App\Models\Operativo\ClientesControlgas;
use App\Models\Operativo\PagoCliente;
use App\Models\Operativo\AceiteLubricante;
use App\Models\Operativo\CorteDiaArchivo;
use App\Models\Operativo\CorteDiaFirmas;
use App\Models\Operativo\Observacione;
use App\Models\Operativo\CorteDia;
use App\Models\Operativo\CorteMes;
use App\Models\Operativo\CorteYear;
use App\Models\Operativo\Aceite;
use App\Models\Operativo\InventarioAceite;
use App\Models\Estacion;
use App\Models\Usuario;
use App\Services\TelegramService;
use App\Core\Auth;
use App\Core\Session;

class VentasService
{
public static function asegurarRegistros(int $idReporte, int $idEstacion, int $idYear, int $idMes): void
{
self::asegurarVentasDia($idReporte);
self::asegurarVentasDiaOtros($idReporte, $idEstacion);
self::asegurarProsegur($idReporte);
self::asegurarTarjetasCB($idReporte, $idEstacion);
self::asegurarClientesControlgas($idReporte);
self::asegurarPagoClientes($idReporte);
self::asegurarAceitesLubricantes($idReporte, $idEstacion, $idYear, $idMes);
}

public static function getEstado(int $idReporte): int
{
$corte = CorteDia::find($idReporte);
return $corte ? (int) $corte->ventas : 0;
}

public static function getFecha(int $idReporte): string
{
$corte = CorteDia::find($idReporte);
return $corte ? $corte->fecha->format('Y-m-d') : '';
}

private static function asegurarVentasDia(int $idReporte): void
{
$existe = VentasDia::where('idreporte_dia', $idReporte)->count();
if ($existe == 0) {
VentasDia::create([
'idreporte_dia' => $idReporte,
'producto' => '',
'litros' => 0,
'jarras' => 0,
'precio_litro' => 0,
'ieps' => 0,
]);
}
}

private static function asegurarVentasDiaOtros(int $idReporte, int $idEstacion): void
{
$conceptos = ['OTROS', '4 ACEITES Y LUBRICANTES', '5 AUTOLAVADO', '6 ADITIVO PARA DIESEL'];
if ($idEstacion == 2) {
$conceptos[] = '7 G BENEFICIOS';
}
foreach ($conceptos as $concepto) {
VentasDiaOtros::firstOrCreate(
['idreporte_dia' => $idReporte, 'concepto' => $concepto],
['idreporte_dia' => $idReporte, 'concepto' => $concepto, 'piezas' => 0, 'importe' => 0]
);
}
}

private static function asegurarProsegur(int $idReporte): void
{
$denominaciones = [
'BILLETE MATUTINO', 'BILLETE VESPERTINO', 'BILLETE NOCTURNO',
'MORRALLA', 'DEPOSITO BANCARIO', 'CHEQUE 1',
'TRANSFERENCIA 1', 'CHEQUE 2', 'TRANSFERENCIA 2'
];
foreach ($denominaciones as $denominacion) {
Prosegur::firstOrCreate(
['idreporte_dia' => $idReporte, 'denominacion' => $denominacion],
['idreporte_dia' => $idReporte, 'denominacion' => $denominacion, 'recibo' => '', 'importe' => 0]
);
}
}

private static function asegurarTarjetasCB(int $idReporte, int $idEstacion): void
{
$tarjetasPorEstacion = [
1 => ['TICKETCARD', 'G500 FLETT', 'VALE ACCORD', 'EFECTICARD', 'VALE EFECTIVALE', 'SODEXO', 'VALE SODEXO', 'INBURGAS', 'AMERICAN EXPRESS', 'BBVA BANCOMER SA', 'INBURSA', 'SI VALE', 'OTROS'],
2 => ['TICKETCARD', 'G500 FLETT', 'EFECTICARD', 'SODEXO', 'INBURGAS', 'AMERICAN EXPRESS', 'BBVA BANCOMER SA', 'SHELL FLEET NAVIGATOR', 'INBURSA', 'OTROS'],
3 => ['TICKETCARD', 'G500 FLETT', 'EFECTICARD', 'SODEXO', 'INBURGAS', 'AMERICAN EXPRESS', 'BBVA BANCOMER SA', 'INBURSA', 'SI VALE', 'ULTRAGAS', 'ENERGEX', 'OTROS'],
4 => ['TICKETCARD', 'G500 FLETT', 'EFECTICARD', 'SODEXO', 'INBURGAS', 'AMERICAN EXPRESS', 'BBVA BANCOMER SA', 'INBURSA', 'OTROS'],
5 => ['TICKETCARD', 'G500 FLETT', 'EFECTICARD', 'SODEXO', 'INBURGAS', 'AMERICAN EXPRESS', 'BBVA BANCOMER SA', 'INBURSA', 'OTROS'],
6 => ['TICKETCARD', 'G500 FLETT', 'EFECTICARD', 'VALE EFECTIVALE', 'SODEXO', 'VALE SODEXO', 'INBURGAS', 'AMERICAN EXPRESS', 'BBVA BANCOMER SA', 'INBURSA', 'OTROS'],
7 => ['TICKETCARD', 'G500 FLETT', 'VALE ACCORD', 'EFECTICARD', 'VALE EFECTIVALE', 'SODEXO', 'INBURGAS', 'AMERICAN EXPRESS', 'BBVA BANCOMER SA', 'INBURSA', 'OTROS'],
14 => ['TICKETCARD', 'G500 FLETT', 'EFECTICARD', 'SODEXO', 'INBURGAS', 'AMERICAN EXPRESS', 'SHELL FLEET NAVIGATOR', 'BBVA BANCOMER SA', 'OTROS', 'SANTANDER'],
];

$numerosPorEstacion = [
1 => ['1', '1.1', 'A', '2', 'B', '3', 'C', '4', '5', '6', 'E', '7', '10'],
2 => ['1', '1.1', '2', '3', '4', '5', '6', '7', 'E', '10'],
3 => ['1', '1.1', '2', '3', '4', '5', '6', 'E', '7', '8', '9', '10'],
4 => ['1', '1.1', '2', '3', '4', '5', '6', 'E', '10'],
5 => ['1', '1.1', '2', '3', '4', '5', '6', 'E', '10'],
6 => ['1', '1.1', '2', 'B', '3', 'C', '4', '5', '6', 'E', '10'],
7 => ['1', '1.1', 'A', '2', 'B', '3', '4', '5', '6', 'E', '10'],
14 => ['1', '1.1', '2', '3', '4', '5', '7', '6', '10', '8'],
];

$tarjetas = $tarjetasPorEstacion[$idEstacion] ?? $tarjetasPorEstacion[1];
$numeros = $numerosPorEstacion[$idEstacion] ?? $numerosPorEstacion[1];

foreach (array_combine($numeros, $tarjetas) as $num => $concepto) {
TarjetasCB::firstOrCreate(
['idreporte_dia' => $idReporte, 'concepto' => $concepto],
['idreporte_dia' => $idReporte, 'num' => $num, 'concepto' => $concepto, 'baucher' => 0]
);
}

$monederosGlobales = ['TICKETCARD', 'G500 FLETT', 'EFECTICARD', 'SODEXO', 'INBURGAS', 'AMERICAN EXPRESS', 'BBVA BANCOMER SA', 'INBURSA', 'ULTRAGAS', 'ENERGEX'];
foreach ($monederosGlobales as $monedero) {
TarjetasCB::firstOrCreate(
['idreporte_dia' => $idReporte, 'concepto' => $monedero],
['idreporte_dia' => $idReporte, 'num' => '', 'concepto' => $monedero, 'baucher' => 0]
);
}
}

private static function asegurarClientesControlgas(int $idReporte): void
{
$conceptos = ['CRÉDITO (ANEXO)', 'DEBITO (ANEXO)'];
foreach ($conceptos as $concepto) {
ClientesControlgas::firstOrCreate(
['idreporte_dia' => $idReporte, 'concepto' => $concepto],
['idreporte_dia' => $idReporte, 'concepto' => $concepto, 'pago' => 0, 'consumo' => 0]
);
}
}

private static function asegurarPagoClientes(int $idReporte): void
{
$conceptos = ['EFECTIVO', 'CHEQUE', 'TRANSFERENCIA (SPEI)', 'TARJETA DE CREDITO', 'DEPOSITO BANCARIO'];
foreach ($conceptos as $concepto) {
PagoCliente::firstOrCreate(
['idreporte_dia' => $idReporte, 'concepto' => $concepto],
['idreporte_dia' => $idReporte, 'concepto' => $concepto, 'importe' => 0, 'nota' => '']
);
}
}

private static function asegurarAceitesLubricantes(int $idReporte, int $idEstacion, int $idYear, int $idMes): void
{
$idMesDb = CorteMes::whereHas('year', function ($q) use ($idEstacion, $idYear) {
$q->where('id_estacion', $idEstacion)->where('year', $idYear);
})->where('mes', $idMes)->value('id');

if (!$idMesDb) return;

$inventario = InventarioAceite::where('id_estacion', $idEstacion)
->where('id_mes', $idMesDb)
->get();

foreach ($inventario as $inv) {
$aceite = Aceite::find($inv->id_aceite);
if (!$aceite) continue;

AceiteLubricante::firstOrCreate(
['idreporte_dia' => $idReporte, 'id_aceite' => $aceite->id_aceite],
[
'idreporte_dia' => $idReporte,
'id_aceite' => $aceite->id_aceite,
'concepto' => $aceite->concepto,
'cantidad' => 0,
'precio_unitario' => $aceite->precio
]
);
}
}

public static function getVentasDia(int $idReporte)
{
return VentasDia::where('idreporte_dia', $idReporte)->get();
}

public static function getVentasDiaOtros(int $idReporte)
{
return VentasDiaOtros::where('idreporte_dia', $idReporte)->get();
}

public static function getProsegur(int $idReporte)
{
return Prosegur::where('idreporte_dia', $idReporte)->get();
}

public static function getTarjetasCB(int $idReporte)
{
return TarjetasCB::where('idreporte_dia', $idReporte)->get();
}

public static function getClientesControlgas(int $idReporte)
{
return ClientesControlgas::where('idreporte_dia', $idReporte)->get();
}

public static function getPagoClientes(int $idReporte)
{
return PagoCliente::where('idreporte_dia', $idReporte)->get();
}

public static function getAceitesLubricantes(int $idReporte)
{
return AceiteLubricante::where('idreporte_dia', $idReporte)
->orderBy('id_aceite', 'asc')
->get();
}

public static function getDocumentos(int $idReporte)
{
return CorteDiaArchivo::where('id_reportedia', $idReporte)->get();
}

public static function getObservaciones(int $idReporte): string
{
$obs = Observacione::where('idreporte_dia', $idReporte)->first();
return $obs ? $obs->observaciones : '';
}

public static function getFirmas(int $idReporte)
{
$firmas = CorteDiaFirmas::where('id_reportedia', $idReporte)
->orderBy('id', 'desc')
->get();

return $firmas->map(function ($f) {
$usuario = Usuario::find($f->id_usuario);
$f->nombre_usuario = $usuario ? $usuario->nombre : 'Desconocido';
$f->fecha_formateada = $f->fecha ? formatearFecha($f->fecha->format('Y-m-d')) : '';
return $f;
});
}

public static function getFirma(int $idReporte, string $detalle)
{
$firma = CorteDiaFirmas::where('id_reportedia', $idReporte)
->where('detalle', $detalle)
->orderBy('id', 'desc')
->first();

if ($firma) {
$usuario = Usuario::find($firma->id_usuario);
$firma->nombre_usuario = $usuario ? $usuario->nombre : 'Desconocido';
}

return $firma;
}

public static function validaFirma(int $idReporte, string $detalle): bool
{
return CorteDiaFirmas::where('id_reportedia', $idReporte)
->where('detalle', $detalle)
->exists();
}

public static function getPermisos(): array
{
$usuario = Auth::user();
$sessionUsuario = Session::get('usuario');
$multiEstacion = $sessionUsuario['multiestacion'] ?? false;

$esDireccionOperaciones = false;
if ($usuario && $usuario->puesto) {
$esDireccionOperaciones = ($usuario->puesto->tipo_puesto ?? '') === 'Dirección de operaciones';
}

$idUsuario = $sessionUsuario['id'] ?? 0;

return [
'multiestacion' => $multiEstacion,
'es_direccion_operaciones' => $esDireccionOperaciones,
'id_usuario' => $idUsuario,
'es_superviso' => ($idUsuario == 19),
'es_vobo' => ($idUsuario == 2),
];
}

public static function finalizarVentas(int $idReporte, int $idUsuario, int $idEstacion): bool
{
$corte = CorteDia::find($idReporte);
if (!$corte) return false;

$corte->update(['ventas' => 1, 'tpv' => 1, 'monedero' => 1]);
return true;
}

public static function agregarFirma(int $idReporte, int $idUsuario, string $base64): string
{
$img = str_replace('data:image/png;base64,', '', $base64);
$fileData = base64_decode($img);
$fileName = uniqid() . '.png';
$ruta = $_SERVER['DOCUMENT_ROOT'] . '/public/assets/img/firmas/';

if (!is_dir($ruta)) {
mkdir($ruta, 0755, true);
}

if (file_put_contents($ruta . $fileName, $fileData)) {
CorteDiaFirmas::create([
'id_reportedia' => $idReporte,
'id_usuario' => $idUsuario,
'firma' => $fileName,
'detalle' => 'Elaboró',
]);
return $fileName;
}
return '';
}

public static function getTotales1234(int $idReporte): array
{
$total1 = Prosegur::where('idreporte_dia', $idReporte)->sum('importe');
$total2 = TarjetasCB::where('idreporte_dia', $idReporte)->sum('baucher');
$total3 = ClientesControlgas::where('idreporte_dia', $idReporte)->sum('consumo');

return [
'total1' => $total1,
'total2' => $total2,
'total3' => $total3,
'cTotal' => $total1 + $total2 + $total3,
];
}

public static function getTotalesVentas(int $idReporte): array
{
$ventas = VentasDia::where('idreporte_dia', $idReporte)->get();
$subTLitros = 0;
$subJarras = 0;
$subTotalLitros = 0;
$subImporteTotal = 0;

foreach ($ventas as $v) {
$totalLitros = $v->litros - $v->jarras;
$importeTotal = $totalLitros * $v->precio_litro;
$subTLitros += $v->litros;
$subJarras += $v->jarras;
$subTotalLitros += $totalLitros;
$subImporteTotal += $importeTotal;
}

$sumOtros = VentasDiaOtros::where('idreporte_dia', $idReporte)->sum('importe');

return [
'subTLitros' => $subTLitros,
'subJarras' => $subJarras,
'subTotalLitros' => $subTotalLitros,
'subImporteTotal' => $subImporteTotal,
'sumOtros' => $sumOtros,
'totalNeto' => $subImporteTotal + $sumOtros,
];
}

public static function getTotalesAceites(int $idReporte): array
{
$aceites = AceiteLubricante::where('idreporte_dia', $idReporte)->get();
$totalCantidad = 0;
$totalPrecio = 0;

foreach ($aceites as $a) {
$importe = $a->cantidad * $a->precio_unitario;
$totalCantidad += $a->cantidad;
$totalPrecio += $importe;
}

return [
'totalCantidad' => $totalCantidad,
'totalPrecio' => $totalPrecio,
];
}

public static function getTotalPagoClientes(int $idReporte): float
{
return PagoCliente::where('idreporte_dia', $idReporte)->sum('importe');
}

public static function getPagoTotal(int $idReporte): float
{
return ClientesControlgas::where('idreporte_dia', $idReporte)->sum('pago');
}

public static function notificarFinalizacion(int $idReporte, int $idEstacion, int $idUsuario, string $nombreUsuario): void
{
try {
$estacion = Estacion::find($idEstacion);
$nombreES = $estacion ? $estacion->nombre : 'Desconocida';

$dateStr = self::getFecha($idReporte);
$fechaFormat = $dateStr ? formatearFecha($dateStr) : '';

$corte = CorteDia::find($idReporte);
$mes = $corte ? nombremes((int)$corte->mes) : '';
$year = $corte ? $corte->year : '';

$mensaje = '✅ Se ha finalizado el apartado de <b>Ventas</b> correspondiente al Corte Diario con fecha del día <b>' . $fechaFormat . '</b>:' . PHP_EOL . PHP_EOL . 
'👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL . 
'⛽ <b>Estación:</b> ' . $nombreES;

self::enviarTelegram($idEstacion, $idUsuario, $mensaje);
} catch (\Throwable $e) {
error_log('Error en notificarFinalizacion: ' . $e->getMessage());
}
}

private static function getEstacionIdFromReporte(int $idReporte): int
{
return (int) CorteDia::from('op_corte_dia as d')
->join('op_corte_mes as m', 'd.id_mes', '=', 'm.id')
->join('op_corte_year as y', 'm.id_year', '=', 'y.id')
->where('d.id', $idReporte)
->value('y.id_estacion');
}

private static function enviarTelegram(int $idEstacion, int $excludeUserId, string $mensaje): void
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

public static function notificarAgregarProducto(int $idReporte, int $idUsuario, string $nombreUsuario): void
{
try {
$idEstacion = self::getEstacionIdFromReporte($idReporte);
if (!$idEstacion) return;

$estacion = Estacion::find($idEstacion);
$nombreES = $estacion ? $estacion->nombre : 'Desconocida';
$fechaStr = self::getFecha($idReporte);
$fechaFormat = $fechaStr ? formatearFecha($fechaStr) : '';

$corte = CorteDia::find($idReporte);
$mes = $corte ? nombremes((int)$corte->mes) : '';
$year = $corte ? $corte->year : '';

$mensaje = '📄 Se ha registrado un nuevo producto en el <b>Concentrado de Ventas</b> correspondiente al Corte Diario con fecha del día <b>' . $fechaFormat . '</b>:' . PHP_EOL . PHP_EOL
. '👤 <b>Responsable</b>: ' . $nombreUsuario . PHP_EOL
. '⛽ <b>Estación</b>: ' . $nombreES;

self::enviarTelegram($idEstacion, $idUsuario, $mensaje);
} catch (\Throwable $e) {
error_log('Error en notificarAgregarProducto: ' . $e->getMessage());
}
}

public static function notificarSubirDocumento(int $idReporte, int $idUsuario, string $nombreUsuario, string $detalle): void
{
try {
$idEstacion = self::getEstacionIdFromReporte($idReporte);
if (!$idEstacion) return;

$estacion = Estacion::find($idEstacion);
$nombreES = $estacion ? $estacion->nombre : 'Desconocida';
$fechaStr = self::getFecha($idReporte);
$fechaFormat = $fechaStr ? formatearFecha($fechaStr) : '';

$corte = CorteDia::find($idReporte);
$mes = $corte ? nombremes((int)$corte->mes) : '';
$year = $corte ? $corte->year : '';

$mensaje = '📎 Se ha agregado un nuevo documento en el apartado de <b>Ventas</b> correspondiente al Corte Diario con fecha del día <b>' . $fechaFormat . '</b>:' . PHP_EOL . PHP_EOL
. '📄 <b>Descripción del documento:</b> ' . $detalle . PHP_EOL
. '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL
. '⛽ <b>Estación:</b> ' . $nombreES;

self::enviarTelegram($idEstacion, $idUsuario, $mensaje);
} catch (\Throwable $e) {
error_log('Error en notificarSubirDocumento: ' . $e->getMessage());
}
}

public static function notificarEliminarDocumento(int $idReporte, int $idUsuario, string $nombreUsuario): void
{
try {
$idEstacion = self::getEstacionIdFromReporte($idReporte);
if (!$idEstacion) return;

$estacion = Estacion::find($idEstacion);
$nombreES = $estacion ? $estacion->nombre : 'Desconocida';
$fechaStr = self::getFecha($idReporte);
$fechaFormat = $fechaStr ? formatearFecha($fechaStr) : '';

$corte = CorteDia::find($idReporte);
$mes = $corte ? nombremes((int)$corte->mes) : '';
$year = $corte ? $corte->year : '';

$mensaje = '🗑️ Se ha eliminado un documento del apartado de <b>Ventas</b> correspondiente al Corte Diario con fecha del día <b>' . $fechaFormat . '</b>:' . PHP_EOL . PHP_EOL
. '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL 
. '⛽ <b>Estación:</b> ' . $nombreES;

self::enviarTelegram($idEstacion, $idUsuario, $mensaje);
} catch (\Throwable $e) {
error_log('Error en notificarEliminarDocumento: ' . $e->getMessage());
}
}
}
