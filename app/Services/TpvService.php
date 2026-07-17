<?php

namespace App\Services;
use App\Models\Operativo\TarjetasCB;
use App\Models\Operativo\CierreLote;
use App\Models\Operativo\CorteDia;
use App\Models\Estacion;
use App\Services\TelegramService;
use App\Core\Auth;
use App\Core\Session;

class TpvService
{
public static function getEmpresasPorEstacion(int $idEstacion): array
{
$base = ['TICKETCARD', 'AMERICAN EXPRESS', 'G500 FLETT', 'BBVA BANCOMER SA', 'EFECTICARD', 'SODEXO', 'INBURGAS', 'INBURSA'];
$extra = [];
if ($idEstacion === 3) {
$extra = ['ULTRAGAS', 'ENERGEX'];
}
if ($idEstacion === 2 || $idEstacion === 14) {
$extra[] = 'SHELL FLEET NAVIGATOR';
}
if ($idEstacion === 14) {
$extra[] = 'SANTANDER';
}
return array_merge($base, $extra);
}

public static function getEstado(int $idReporte): int
{
$corte = CorteDia::find($idReporte);
return $corte ? (int) $corte->tpv : 0;
}

public static function getFecha(int $idReporte): string
{
$corte = CorteDia::find($idReporte);
return $corte ? $corte->fecha->format('Y-m-d') : '';
}

public static function getPermisos(): array
{
$usuario = Auth::user();
$sessionUsuario = Session::get('usuario');
	$multiEstacion = $sessionUsuario['multiestacion'] ?? false;
	if (ModuleStationService::isPuesto6Estacion8()) {
		$multiEstacion = false;
	}

	$esDireccionOperaciones = false;
if ($usuario && $usuario->puesto) {
$esDireccionOperaciones = ($usuario->puesto->tipo_puesto ?? '') === 'Dirección de operaciones';
}

$idUsuario = $sessionUsuario['id'] ?? 0;

return [
'multiestacion' => $multiEstacion,
'es_direccion_operaciones' => $esDireccionOperaciones,
'id_usuario' => $idUsuario,
];
}

public static function getCierres(int $idReporte, ?string $empresa = null)
{
$query = CierreLote::where('idreporte_dia', $idReporte);
if ($empresa) {
$query->where('empresa', $empresa);
}
return $query->orderBy('empresa', 'asc')
->orderBy('id', 'asc')
->get();
}

public static function getCierresPorEmpresa(int $idReporte): array
{
$cierres = self::getCierres($idReporte);
$agrupados = [];
foreach ($cierres as $c) {
$agrupados[$c->empresa][] = $c;
}
return $agrupados;
}

public static function crearCierre(int $idReporte, string $empresa): CierreLote
{
return CierreLote::create([
'idreporte_dia' => $idReporte,
'empresa' => $empresa,
'no_cierre_lote' => '',
'importe' => 0,
'ticktes' => 0,
'estado' => 0,
]);
}

public static function editarCierre(int $idCierre, string $campo, string $valor): bool
{
$allowed = ['no_cierre_lote', 'importe', 'ticktes'];
if (!in_array($campo, $allowed)) return false;

$cierre = CierreLote::find($idCierre);
if (!$cierre) return false;

$val = ($campo === 'no_cierre_lote') ? $valor : (float) $valor;
$cierre->update([$campo => $val]);

if ($campo === 'importe') {
$idReporte = $cierre->idreporte_dia;
self::sincronizarMonederos($idReporte, $cierre->empresa);
}

return true;
}

public static function togglePendiente(int $idCierre, int $estado): bool
{
$cierre = CierreLote::find($idCierre);
if (!$cierre) return false;
return $cierre->update(['estado' => $estado]);
}

public static function getTotalesPorEmpresa(int $idReporte, string $empresa): array
{
$cierres = CierreLote::where('idreporte_dia', $idReporte)
->where('empresa', $empresa)
->get();

$totalImporte = 0;
$totalTicket = 0;
foreach ($cierres as $c) {
$totalImporte += (float) $c->importe;
$totalTicket += (int) $c->ticktes;
}

return [
'total_importe' => $totalImporte,
'total_ticket' => $totalTicket,
];
}

public static function sincronizarMonederos(int $idReporte, string $empresa): void
{
$importeTotal = CierreLote::where('idreporte_dia', $idReporte)
->where('empresa', $empresa)
->sum('importe');

$tarjeta = TarjetasCB::where('idreporte_dia', $idReporte)
->where('concepto', $empresa)
->first();

if ($tarjeta) {
$tarjeta->update(['baucher' => $importeTotal]);
}
}

public static function isFinalizado(int $idReporte): bool
{
return self::getEstado($idReporte) === 1;
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

public static function notificarAgregarTpv(int $idReporte, int $idUsuario, string $nombreUsuario, string $empresa): void
{
try {
$idEstacion = self::getEstacionIdFromReporte($idReporte);
if (!$idEstacion) return;

if ($empresa == "G500 FLETT") {
$empresa = "TICKETCARD+";
}

$estacion = Estacion::find($idEstacion);
$nombreES = $estacion ? $estacion->nombre : 'Desconocida';
$fecha = self::getFecha($idReporte);
$fechaFormat = $fecha ? formatearFecha($fecha) : '';

$mensaje = '💳 Se ha agregado una nueva <b>TPV</b> correspondiente al Corte Diario con fecha del día <b>' . $fechaFormat . '</b>:' . PHP_EOL . PHP_EOL
. '🏢 <b>Empresa:</b> ' . $empresa . PHP_EOL
. '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL 
. '⛽ <b>Estación:</b> ' . $nombreES;

self::enviarTelegram($idEstacion, $idUsuario, $mensaje);
} catch (\Throwable $e) {
error_log('Error en notificarAgregarTpv: ' . $e->getMessage());
}
}

public static function notificarCambiarEstatusTpv(int $idCierre, int $idUsuario, string $nombreUsuario, int $estado): void
{
try {
$cierre = CierreLote::find($idCierre);
if (!$cierre) return;

$idReporte = $cierre->idreporte_dia;
$idEstacion = self::getEstacionIdFromReporte($idReporte);
if (!$idEstacion) return;

$estacion = Estacion::find($idEstacion);
$nombreES = $estacion ? $estacion->nombre : 'Desconocida';
$fecha = self::getFecha($idReporte);
$fechaFormat = $fecha ? formatearFecha($fecha) : '';
$estadoTexto = $estado ? 'Pendiente' : 'Activo';

if ($cierre->empresa == "G500 FLETT") {
$cierre->empresa = "TICKETCARD+";
}

$mensaje = '🔄 Se ha actualizado el estatus de una <b>TPV</b> correspondiente al Corte Diario con fecha del día <b>' . $fechaFormat . '</b>:' . PHP_EOL . PHP_EOL
. '🏢 <b>Empresa:</b> ' . $cierre->empresa . PHP_EOL
. '📊 <b>Nuevo estatus:</b> ' . $estadoTexto . PHP_EOL
. '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL . PHP_EOL
. '⛽ <b>Estación:</b> ' . $nombreES;

self::enviarTelegram($idEstacion, $idUsuario, $mensaje);
} catch (\Throwable $e) {
error_log('Error en notificarCambiarEstatusTpv: ' . $e->getMessage());
}
}
}
