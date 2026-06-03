<?php

namespace App\Services;

use App\Models\Operativo\CorteDia;
use App\Models\Operativo\VentasDia;
use App\Models\Operativo\AceiteLubricante;
use App\Models\Operativo\TarjetasCB;
use App\Models\Operativo\ClientesControlgas;

class ResumenImpuestosService
{

public static function getData(int $idMes): array
{
$diaIds = CorteDia::where('id_mes', $idMes)->pluck('id')->toArray();

if (empty($diaIds)) {
return self::emptyData();
}

$productos = VentasDia::whereIn('idreporte_dia', $diaIds)
->select('producto', 'ieps')
->groupBy('producto', 'ieps')
->orderBy('id', 'asc')
->get();

// Obtener todos los registros de VentasDia para cálculos de litros/jarras/precio
$ventas = VentasDia::whereIn('idreporte_dia', $diaIds)
->select('producto', 'litros', 'jarras', 'precio_litro')
->get()
->groupBy('producto');

$items = [];
$totalVV = 0;
$totalISI = 0;
$totalIV2 = 0;
$totalIEPS2 = 0;
$totalNeto = 0;

foreach ($productos as $p) {
$producto = $p->producto;

$tolitros = 0;
$tojarras = 0;
$toprecio = 0;

$rows = $ventas->get($producto, collect());
foreach ($rows as $row) {
$tolitros += (float) $row->litros;
$tojarras += (float) $row->jarras;
$toprecio = ($toprecio + (float) $row->precio_litro) / 2;
}

$precioP = $tolitros > 0 ? $toprecio / 30.5 : 0;
$ieps = (float) $p->ieps;

$preciosiniva = ($precioP - $ieps) / 1.16;
$iva1 = $preciosiniva * 0.16;

$volumenv = $tolitros - $tojarras;
$importesiniva = $volumenv * $preciosiniva;
$iva2 = $importesiniva * 0.16;
$ieps2 = $volumenv * $ieps;
$total = $importesiniva + $iva2 + $ieps2;

$totalVV += $volumenv;
$totalISI += $importesiniva;
$totalIV2 += $iva2;
$totalIEPS2 += $ieps2;
$totalNeto += $total;

$items[] = [
'producto' => $producto,
'precio_litro' => $precioP,
'ieps' => $ieps,
'precio_sin_iva' => $preciosiniva,
'iva_unidad' => $iva1,
'volumen_vendido' => $volumenv,
'importe_sin_iva' => $importesiniva,
'iva_total' => $iva2,
'ieps_total' => $ieps2,
'total' => $total,
];
}

$aceitesTotal = (float) AceiteLubricante::whereIn('idreporte_dia', $diaIds)
->selectRaw('COALESCE(SUM(cantidad * precio_unitario), 0) as total')
->value('total');

$aceitesSinIva = $aceitesTotal / 1.16;
$aceitesIva = $aceitesSinIva * 0.16;

return [
'items' => $items,
'aceites_total' => $aceitesTotal,
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
'importe_sin_iva' => $totalISI + $aceitesSinIva,
'iva' => $totalIV2 + $aceitesIva,
'ieps' => $totalIEPS2,
'total' => $totalNeto + $aceitesTotal,
],
'monederos' => self::getMonederosData($diaIds),
];
}

private static function getMonederosData(array $diaIds): array
{
if (empty($diaIds)) {
return self::emptyMonederos();
}

// : suma TarjetasCB por concepto en todos los días
$bancomer = (float) TarjetasCB::whereIn('idreporte_dia', $diaIds)
->where('concepto', 'BBVA BANCOMER SA')->sum('baucher');
$amex = (float) TarjetasCB::whereIn('idreporte_dia', $diaIds)
->where('concepto', 'AMERICAN EXPRESS')->sum('baucher');
$inburgas = (float) TarjetasCB::whereIn('idreporte_dia', $diaIds)
->where('concepto', 'INBURSA')->sum('baucher');
$totalTB = $bancomer + $amex + $inburgas;

$ticketcard = (float) TarjetasCB::whereIn('idreporte_dia', $diaIds)
->where('concepto', 'TICKETCARD')->sum('baucher');
$g500fleet = (float) TarjetasCB::whereIn('idreporte_dia', $diaIds)
->where('concepto', 'G500 FLETT')->sum('baucher');
$efecticard = (float) TarjetasCB::whereIn('idreporte_dia', $diaIds)
->where('concepto', 'EFECTICARD')->sum('baucher');
$sodexo = (float) TarjetasCB::whereIn('idreporte_dia', $diaIds)
->where('concepto', 'SODEXO')->sum('baucher');
$totalTarjetas = $ticketcard + $g500fleet + $efecticard + $sodexo;

// Cartera de Clientes ATIO — 
// Crédito (Anexo)
$credito = ClientesControlgas::whereIn('idreporte_dia', $diaIds)
->where('concepto', 'CRÉDITO (ANEXO)')
->selectRaw('COALESCE(SUM(pago),0) as pago, COALESCE(SUM(consumo),0) as consumo')
->first();
$pagoC = (float) ($credito->pago ?? 0);
$consumoC = (float) ($credito->consumo ?? 0);

// Débito (Anexo)
$debito = ClientesControlgas::whereIn('idreporte_dia', $diaIds)
->where('concepto', 'DEBITO (ANEXO)')
->selectRaw('COALESCE(SUM(pago),0) as pago, COALESCE(SUM(consumo),0) as consumo')
->first();
$pagoD = (float) ($debito->pago ?? 0);
$consumoD = (float) ($debito->consumo ?? 0);

$totalPago = $pagoC + $pagoD;
$totalConsumo = $consumoC + $consumoD;

return [
'bancomer' => $bancomer,
'amex' => $amex,
'inburgas' => $inburgas,
'total_tb' => $totalTB,
'ticketcard' => $ticketcard,
'g500fleet' => $g500fleet,
'efecticard' => $efecticard,
'sodexo' => $sodexo,
'total_otros' => $totalTarjetas,
'pago_credito' => $pagoC,
'consumo_credito' => $consumoC,
'pago_debito' => $pagoD,
'consumo_debito' => $consumoD,
'total_pago' => $totalPago,
'total_consumo' => $totalConsumo,
];
}

private static function emptyData(): array
{
return [
'items' => [],
'aceites_total' => 0, 'aceites_sin_iva' => 0, 'aceites_iva' => 0,
'subtotal_combustibles' => [
'volumen' => 0, 'importe_sin_iva' => 0, 'iva' => 0, 'ieps' => 0, 'total' => 0,
],
'total_dia' => [
'importe_sin_iva' => 0, 'iva' => 0, 'ieps' => 0, 'total' => 0,
],
'monederos' => self::emptyMonederos(),
];
}

private static function emptyMonederos(): array
{
return [
'bancomer' => 0, 'amex' => 0, 'inburgas' => 0, 'total_tb' => 0,
'ticketcard' => 0, 'g500fleet' => 0, 'efecticard' => 0, 'sodexo' => 0, 'total_otros' => 0,
'pago_credito' => 0, 'consumo_credito' => 0,
'pago_debito' => 0, 'consumo_debito' => 0,
'total_pago' => 0, 'total_consumo' => 0,
];
}
}
