<?php

namespace App\Services;

use App\Models\Operativo\CorteDia;
use App\Models\Operativo\CorteMes;
use App\Models\Operativo\CorteYear;
use App\Models\Operativo\TarjetasCB;
use App\Models\Operativo\CorteDiaHist;
use App\Models\Estacion;
use App\Services\TelegramService;
use App\Core\Session;
use App\Core\Auth;

class CorteDiarioService
{
public static function getResumenMensual($idMes)
{
$totalAccord = TarjetasCB::whereHas('corteDia', function ($q) use ($idMes) {
$q->where('id_mes', $idMes);
})->where('concepto', 'VALE ACCORD')->sum('baucher');

$totalEfectivale = TarjetasCB::whereHas('corteDia', function ($q) use ($idMes) {
$q->where('id_mes', $idMes);
})->where('concepto', 'VALE EFECTIVALE')->sum('baucher');

return [
'vale_accord'        => $totalAccord,
'vale_efectivale'    => $totalEfectivale,
'mostrar_tabla'      => ($totalAccord > 0 || $totalEfectivale > 0),
'mostrar_accord'     => ($totalAccord > 0),
'mostrar_efectivale' => ($totalEfectivale > 0),
];
}

public static function asegurarDiasDelMes($idYear, $idMes, $idEstacion)
{
$corteYear = CorteYear::firstOrCreate(
['id_estacion' => $idEstacion, 'year' => $idYear],
['id_estacion' => $idEstacion, 'year' => $idYear]
);

$corteMes = CorteMes::firstOrCreate(
['id_year' => $corteYear->id, 'mes' => $idMes],
['id_year' => $corteYear->id, 'mes' => $idMes]
);

$ultimoDia = (int) date('t', mktime(0, 0, 0, $idMes, 1, $idYear));
for ($dia = 1; $dia <= $ultimoDia; $dia++) {
$fecha = sprintf('%04d-%02d-%02d', $idYear, $idMes, $dia);
CorteDia::firstOrCreate(
['id_mes' => $corteMes->id, 'fecha' => $fecha],
['id_mes' => $corteMes->id, 'fecha' => $fecha, 'ventas' => 0, 'tpv' => 0, 'monedero' => 0]
);
}
}

public static function getDiasCorte($idYear, $idMes, $idEstacion)
{
return CorteDia::from('op_corte_dia as d')
->join('op_corte_mes as m', 'd.id_mes', '=', 'm.id')
->join('op_corte_year as y', 'm.id_year', '=', 'y.id')
->where('y.id_estacion', $idEstacion)
->where('y.year', $idYear)
->where('m.mes', $idMes)
->orderBy('d.fecha', 'asc')
->select(
'd.id as idDia',
'd.fecha',
'd.ventas',
'd.tpv',
'd.monedero'
)
->get();
}

public static function getPermisos()
{
$usuario = Auth::user();
$sessionUsuario = Session::get('usuario');
$multiEstacion = $sessionUsuario['multiestacion'] ?? false;

$esDireccionOperaciones = false;
if ($usuario && $usuario->puesto) {
$esDireccionOperaciones = ($usuario->puesto->tipo_puesto ?? '') === 'Dirección de operaciones';
}

return [
'multiestacion'         => $multiEstacion,
'es_direccion_operaciones' => $esDireccionOperaciones,
];
}

public static function getHistCount($idCorteDia)
{
return CorteDiaHist::where('id_corte', $idCorteDia)->count();
}

public static function getHistorial($idCorteDia)
{
$rows = CorteDiaHist::where('id_corte', $idCorteDia)
->orderBy('id', 'asc')
->get();

$historial = [];
foreach ($rows as $row) {
$usuario = \App\Models\Usuario::find($row->id_usuario);
$fechaExplode = explode(' ', $row->fecha);
$fecha = $fechaExplode[0] ?? '';
$hora = isset($fechaExplode[1]) ? date('g:i a', strtotime($fechaExplode[1])) : '';

$historial[] = [
'id'       => $row->id,
'fecha'    => formatearFecha($row->fecha),
'hora'     => $hora,
'usuario'  => $usuario ? $usuario->nombre : 'Desconocido',
'detalle'  => $row->detalle,
];
}

return $historial;
}

public static function activarCorte($idCorteDia, $idUsuario, $detalle)
{
$corte = CorteDia::find($idCorteDia);
if (!$corte) return false;

$corte->update([
'ventas'   => 0,
'tpv'      => 0,
'monedero' => 0,
]);

return CorteDiaHist::create([
'id_corte'   => $idCorteDia,
'id_usuario' => $idUsuario,
'fecha'      => date('Y-m-d H:i:s'),
'detalle'    => $detalle,
]);
}

private static function getEstacionIdFromCorte(int $idCorteDia): int
{
return (int) CorteDia::from('op_corte_dia as d')
->join('op_corte_mes as m', 'd.id_mes', '=', 'm.id')
->join('op_corte_year as y', 'm.id_year', '=', 'y.id')
->where('d.id', $idCorteDia)
->value('y.id_estacion');
}

public static function notificarEdicion(int $idCorteDia, int $idUsuario, string $nombreUsuario): void
{
try {
$idEstacion = self::getEstacionIdFromCorte($idCorteDia);
if (!$idEstacion) return;

$corte = CorteDia::find($idCorteDia);
$estacion = Estacion::find($idEstacion);
$fechaStr = $corte ? formatearFecha($corte->fecha->format('Y-m-d')) : '';
$nombreES = $estacion ? $estacion->nombre : 'Desconocida';

$mes = $corte ? nombremes((int)$corte->mes) : '';
$year = $corte ? $corte->year : '';

$mensaje = '✏️ Corte Diario editado' . PHP_EOL . PHP_EOL
. 'ID Corte: ' . $idCorteDia . PHP_EOL
. 'Fecha: ' . $fechaStr . PHP_EOL
. 'Periodo: ' . $mes . ' ' . $year . PHP_EOL
. 'Editó: ' . $nombreUsuario . PHP_EOL . PHP_EOL
. '⛽ Estación: ' . $nombreES;

$telegram = new TelegramService();
$userIds = $telegram->getUserIdsByStation($idEstacion, $idUsuario);

if (in_array($idEstacion, [6, 7])) {
$extraIds = $telegram->getUserIdsComercializadora($idUsuario);
$userIds = array_values(array_unique(array_merge($userIds, $extraIds)));
} elseif (in_array($idEstacion, [1, 2, 3, 4, 5, 14])) {
$extraIds = $telegram->getUserIdsContabilidad($idUsuario);
$userIds = array_values(array_unique(array_merge($userIds, $extraIds)));
}

$telegram->sendMessageToMultiple($userIds, $mensaje);
} catch (\Throwable $e) {
error_log('Error en notificarEdicion CorteDiario: ' . $e->getMessage());
}
}

public static function notificarActivacion(int $idCorteDia, int $idUsuario, string $nombreUsuario, string $detalle): void
{
try {
$idEstacion = self::getEstacionIdFromCorte($idCorteDia);
if (!$idEstacion) return;

$corte = CorteDia::find($idCorteDia);
$estacion = Estacion::find($idEstacion);
$fechaStr = $corte ? formatearFecha($corte->fecha->format('Y-m-d')) : '';
$nombreES = $estacion ? $estacion->nombre : 'Desconocida';

$mes = $corte ? nombremes((int)$corte->mes) : '';
$year = $corte ? $corte->year : '';

$mensaje = '🔓 Se ha reactivado un <b>Corte Diario</b> correspondiente al dia <b>'. $fechaStr . '</b>:' . PHP_EOL . PHP_EOL
. '📝 <b>Motivo de la reactivación:</b> ' . $detalle . PHP_EOL
. '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL
. '⛽ <b>Estación:</b> ' . $nombreES;

$telegram = new TelegramService();
$userIds = $telegram->getUserIdsByStation($idEstacion, $idUsuario);

if (in_array($idEstacion, [6, 7])) {
$extraIds = $telegram->getUserIdsComercializadora($idUsuario);
$userIds = array_values(array_unique(array_merge($userIds, $extraIds)));
} elseif (in_array($idEstacion, [1, 2, 3, 4, 5, 14])) {
$extraIds = $telegram->getUserIdsContabilidad($idUsuario);
$userIds = array_values(array_unique(array_merge($userIds, $extraIds)));
}

$telegram->sendMessageToMultiple($userIds, $mensaje);
} catch (\Throwable $e) {
error_log('Error en notificarActivacion CorteDiario: ' . $e->getMessage());
}
}
}

