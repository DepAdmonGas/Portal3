<?php

namespace App\Services;

use App\Models\Operativo\VentasDia;
use App\Models\Operativo\AceiteLubricante;
use App\Models\Operativo\CorteDia;
use App\Core\Auth;
use App\Core\Session;

class ImpuestoService
{
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

public static function getPermisos(): array
{
$sessionUsuario = Session::get('usuario');
$multiEstacion = $sessionUsuario['multiestacion'] ?? false;
if (ModuleStationService::isPuesto6Estacion8()) {
$multiEstacion = false;
}

$usuario = Auth::user();
$esDireccionOperaciones = false;
if ($usuario && $usuario->puesto) {
$esDireccionOperaciones = ($usuario->puesto->tipo_puesto ?? '') === 'Dirección de operaciones';
}

return [
'multiestacion' => $multiEstacion,
'es_direccion_operaciones' => $esDireccionOperaciones,
'id_usuario' => $sessionUsuario['id'] ?? 0,
];
}

public static function getData(int $idReporte): array
{
$ventas = VentasDia::where('idreporte_dia', $idReporte)->orderBy('id', 'asc')->get();
$aceites = AceiteLubricante::where('idreporte_dia', $idReporte)->orderBy('id', 'asc')->get();

$items = [];
$totalVV = 0;
$totalISI = 0;
$totalIV2 = 0;
$totalIEPS2 = 0;
$totalNeto = 0;

foreach ($ventas as $v) {
$precioLitro = (float) $v->precio_litro;
$ieps = (float) $v->ieps;
$litros = (float) $v->litros;
$jarras = (float) $v->jarras;

$precioSinIva = ($precioLitro - $ieps) / 1.16;
$ivaUnidad = $precioSinIva * 0.16;
$volumenVendido = $litros - $jarras;
$importeSinIva = $volumenVendido * $precioSinIva;
$ivaTotal = $importeSinIva * 0.16;
$iepsTotal = $volumenVendido * $ieps;
$total = $importeSinIva + $ivaTotal + $iepsTotal;

$totalVV += $volumenVendido;
$totalISI += $importeSinIva;
$totalIV2 += $ivaTotal;
$totalIEPS2 += $iepsTotal;
$totalNeto += $total;

$items[] = [
'producto' => $v->producto,
'precio_litro' => $precioLitro,
'ieps' => $ieps,
'precio_sin_iva' => $precioSinIva,
'iva_unidad' => $ivaUnidad,
'volumen_vendido' => $volumenVendido,
'importe_sin_iva' => $importeSinIva,
'iva_total' => $ivaTotal,
'ieps_total' => $iepsTotal,
'total' => $total,
];
}

$totalAceites = 0;
foreach ($aceites as $a) {
$totalAceites += (float) $a->cantidad * (float) $a->precio_unitario;
}

$aceitesSinIva = $totalAceites / 1.16;
$aceitesIva = $aceitesSinIva * 0.16;

$totalDiaISI = $totalISI + $aceitesSinIva;
$totalDiaIVA = $totalIV2 + $aceitesIva;
$totalDia = $totalNeto + $totalAceites;

return [
'items' => $items,
'aceites_total' => $totalAceites,
'aceites_sin_iva' => $aceitesSinIva,
'aceites_iva' => $aceitesIva,
'subtotal_combustibles' => [
'volumen' => $totalVV,
'importe_sin_iva' => $totalISI,
'iva' => $totalIV2,
'ieps' => $totalIEPS2,
'total' => $totalNeto,
],
'total_dia' => [
'importe_sin_iva' => $totalDiaISI,
'iva' => $totalDiaIVA,
'ieps' => $totalIEPS2,
'total' => $totalDia,
],
];
}
}
