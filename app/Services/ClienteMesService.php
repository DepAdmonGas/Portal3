<?php
namespace App\Services;
use App\Models\Operativo\Cliente;
use App\Models\Operativo\ConsumosPago;
use App\Models\Operativo\ConsumosPagosResumen;
use App\Models\Operativo\ConsumosPagosResumenFinalizar;
use App\Models\Operativo\CorteDia;
use App\Models\Operativo\CorteMes;
use App\Models\Operativo\CorteYear;
use App\Models\Estacion;
use App\Services\TelegramService;
use App\Core\Session;
use App\Core\Auth;

class ClienteMesService
{
public static function getIdReporte(int $idEstacion, int $idYear, int $idMes): int
{
$corteMes = CorteMes::join('op_corte_year', 'op_corte_mes.id_year', '=', 'op_corte_year.id')
->where('op_corte_year.id_estacion', $idEstacion)
->where('op_corte_year.year', $idYear)
->where('op_corte_mes.mes', $idMes)
->select('op_corte_mes.id')
->first();

return $corteMes ? (int) $corteMes->id : 0;
}

public static function getIdReporteAnterior(int $idEstacion, int $idYear, int $idMes): int
{
if ($idMes == 1) {
$yearA = $idYear - 1;
$mesA = 12;
} else {
$yearA = $idYear;
$mesA = $idMes - 1;
}

return self::getIdReporte($idEstacion, $yearA, $mesA);
}

public static function estaFinalizado(int $idReporte): bool
{
return ConsumosPagosResumenFinalizar::where('id_mes', $idReporte)->exists();
}

public static function calcularResumen(int $idReporte, int $idReporteA, int $idEstacion): void
{
$clientes = Cliente::where('id_estacion', $idEstacion)
->where('estado', 1)
->get();

foreach ($clientes as $cliente) {
$existe = ConsumosPagosResumen::where('id_mes', $idReporte)
->where('id_cliente', $cliente->id)
->exists();

if (!$existe) {
ConsumosPagosResumen::create([
'id_mes' => $idReporte,
'id_cliente' => $cliente->id,
'saldo_inicial' => 0,
'consumos' => 0,
'pagos' => 0,
'saldo_final' => 0,
]);
}
}

$rows = ConsumosPagosResumen::where('id_mes', $idReporte)->get();

foreach ($rows as $row) {
$dias = CorteDia::where('id_mes', $idReporte)->pluck('id');

$totalConsumos = ConsumosPago::whereIn('id_reportedia', $dias)
->where('id_cliente', $row->id_cliente)
->where('tipo', 'Consumo')
->sum('total');

$totalPagos = ConsumosPago::whereIn('id_reportedia', $dias)
->where('id_cliente', $row->id_cliente)
->where('tipo', 'Pago')
->sum('total');

$row->consumos = $totalConsumos;
$row->pagos = $totalPagos;

if ($idReporteA != 0) {
$anterior = ConsumosPagosResumen::where('id_mes', $idReporteA)
->where('id_cliente', $row->id_cliente)
->first();

if ($anterior && (float) $anterior->saldo_final != 0) {
$row->saldo_inicial = (float) $anterior->saldo_final;
}
}

$row->saldo_final = (float) $row->saldo_inicial + (float) $row->consumos - (float) $row->pagos;
$row->save();
}
}

public static function getDatos(int $idReporte): array
{
$credito = ConsumosPagosResumen::join('op_cliente', 'op_consumos_pagos_resumen.id_cliente', '=', 'op_cliente.id')
->where('op_consumos_pagos_resumen.id_mes', $idReporte)
->where('op_cliente.tipo', 'Crédito')
->where('op_cliente.estado', 1)
->orderBy('op_cliente.cliente', 'asc')
->select(
'op_consumos_pagos_resumen.id',
'op_consumos_pagos_resumen.id_cliente',
'op_consumos_pagos_resumen.saldo_inicial',
'op_consumos_pagos_resumen.consumos',
'op_consumos_pagos_resumen.pagos',
'op_consumos_pagos_resumen.saldo_final',
'op_cliente.cuenta',
'op_cliente.cliente as nombre'
)
->get()
->toArray();

$debito = ConsumosPagosResumen::join('op_cliente', 'op_consumos_pagos_resumen.id_cliente', '=', 'op_cliente.id')
->where('op_consumos_pagos_resumen.id_mes', $idReporte)
->where('op_cliente.tipo', 'Débito')
->where('op_cliente.estado', 1)
->orderBy('op_cliente.cliente', 'asc')
->select(
'op_consumos_pagos_resumen.id',
'op_consumos_pagos_resumen.id_cliente',
'op_consumos_pagos_resumen.saldo_inicial',
'op_consumos_pagos_resumen.consumos',
'op_consumos_pagos_resumen.pagos',
'op_consumos_pagos_resumen.saldo_final',
'op_cliente.cuenta',
'op_cliente.cliente as nombre'
)
->get()
->toArray();

$totals = self::calcularTotales($credito, $debito);

return [
'credito' => $credito,
'debito' => $debito,
'totals' => $totals,
];
}

private static function calcularTotales(array $credito, array $debito): array
{
$cSi = $cCo = $cPa = $cSf = 0;
foreach ($credito as $r) {
$cSi += (float) $r['saldo_inicial'];
$cCo += (float) $r['consumos'];
$cPa += (float) $r['pagos'];
$cSf += (float) $r['saldo_final'];
}

$dSi = $dCo = $dPa = $dSf = 0;
foreach ($debito as $r) {
$dSi += (float) $r['saldo_inicial'];
$dCo += (float) $r['consumos'];
$dPa += (float) $r['pagos'];
$dSf += (float) $r['saldo_final'];
}

return [
'credito' => ['saldo_inicial' => $cSi, 'consumos' => $cCo, 'pagos' => $cPa, 'saldo_final' => $cSf],
'debito' => ['saldo_inicial' => $dSi, 'consumos' => $dCo, 'pagos' => $dPa, 'saldo_final' => $dSf],
'gran_total' => [
'saldo_inicial' => $cSi + $dSi,
'consumos' => $cCo + $dCo,
'pagos' => $cPa + $dPa,
'saldo_final' => $cSf + $dSf,
],
];
}

public static function getNombreEstacion(int $idEstacion): string
{
$estaciones = [
1 => 'Interlomas',
2 => 'Palo Solo',
3 => 'San Agustin',
4 => 'Gasomira',
5 => 'Valle de Guadalupe',
6 => 'Esmegas',
7 => 'Xochimilco',
14 => 'Bosque Real',
];

return $estaciones[$idEstacion] ?? 'Estación';
}

public static function getPermisos(): array
{
$sessionUsuario = Session::get('usuario');
$multiEstacion = $sessionUsuario['multiestacion'] ?? false;

$usuario = Auth::user();
$puestoExcluido = false;
$esDireccionOperaciones = false;

if ($usuario && $usuario->puesto) {
$nombrePuesto = $usuario->puesto->tipo_puesto ?? '';
$puestoExcluido = in_array($nombrePuesto, [
'Contabilidad',
'Comercializadora',
'Dirección de operaciones servicio social',
]);
$esDireccionOperaciones = $nombrePuesto === 'Dirección de operaciones';
}

$permisos = ModuloDptoOperativoService::permisosSesion('corporativo');

return [
'multiestacion' => $multiEstacion,
'es_direccion_operaciones' => $esDireccionOperaciones,
'puesto_excluido' => $puestoExcluido,
'id_usuario' => $sessionUsuario['id'] ?? 0,
'puedeLeer' => !empty($permisos['leer']),
'puedeCrear' => !empty($permisos['crear']),
'puedeEditar' => !empty($permisos['editar']),
'puedeEliminar' => !empty($permisos['eliminar']),
'puedeDescargar' => !empty($permisos['descargar']),
];
}

public static function finalizar(int $idReporte, int $idUsuario): bool
{
if (self::estaFinalizado($idReporte)) {
return false;
}

try {
ConsumosPagosResumenFinalizar::create([
'id_mes' => $idReporte,
]);

return true;
} catch (\Throwable $e) {
error_log('Error al finalizar resumen clientes: ' . $e->getMessage());
return false;
}
}

public static function editarSaldoInicial(int $idResumen, float $saldo): array
{
$resumen = ConsumosPagosResumen::find($idResumen);
if (!$resumen) {
return ['success' => false, 'saldo_final' => 0];
}

$resumen->saldo_inicial = $saldo;
$resumen->saldo_final = $saldo + (float) $resumen->consumos - (float) $resumen->pagos;
$saved = $resumen->save();

return [
'success' => $saved,
'saldo_final' => $saved ? (float) $resumen->saldo_final : 0,
];
}

private static function getEstacionIdFromIdMes(int $idMes): int
{
$corteMes = CorteMes::with('year')->find($idMes);
return $corteMes && $corteMes->year ? (int) $corteMes->year->id_estacion : 0;
}

private static function enviarTelegramClienteMes(int $idEstacion, int $excludeUserId, string $mensaje): void
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

public static function notificarFinalizar(int $idReporte, int $idUsuario, string $nombreUsuario): void
{
try {
$idEstacion = self::getEstacionIdFromIdMes($idReporte);
if (!$idEstacion) return;

$corteMes = CorteMes::with('year')->find($idReporte);
$periodo = $corteMes && $corteMes->year ? nombremes((int) $corteMes->mes) . ' ' . $corteMes->year->year : '';

$estacion = Estacion::find($idEstacion);
$nombreES = $estacion ? $estacion->nombre : 'Desconocida';

$mensaje = '✅ Se ha finalizado el apartado de <b>Resumen de Clientes</b>, correspondiente al <b>Corte Diario</b> del periodo de <b>' . $periodo . '</b>:' . PHP_EOL . PHP_EOL
. '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL
. '⛽ <b>Estación:</b> ' . $nombreES;

self::enviarTelegramClienteMes($idEstacion, $idUsuario, $mensaje);
} catch (\Throwable $e) {
error_log('Error en notificarFinalizar ClienteMes: ' . $e->getMessage());
}
}
}
