<?php

namespace App\Services;

use App\Models\Operativo\CorteDia;
use App\Models\Operativo\CorteMes;
use App\Models\Operativo\CorteYear;
use App\Models\Operativo\TarjetasCB;
use App\Models\Operativo\CorteDiaHist;
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
return CorteDiaHist::create([
'id_corte'   => $idCorteDia,
'id_usuario' => $idUsuario,
'fecha'      => date('Y-m-d H:i:s'),
'detalle'    => $detalle,
]);
}
}

