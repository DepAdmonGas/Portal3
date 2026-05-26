<?php

namespace App\Services;

use App\Models\Operativo\TarjetasCB;
use App\Models\Operativo\ClientesControlgas;
use App\Models\Operativo\CorteDia;
use App\Core\Auth;
use App\Core\Session;

class MonederoService
{
public static function getEstado(int $idReporte): int
{
$corte = CorteDia::find($idReporte);
return $corte ? (int) $corte->monedero : 0;
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

private static function getBaucher(int $idReporte, string $concepto): float
{
$row = TarjetasCB::where('idreporte_dia', $idReporte)
->where('concepto', $concepto)
->first();
return $row ? (float) $row->baucher : 0;
}

private static function getControlgasPago(int $idReporte, string $concepto): float
{
$row = ClientesControlgas::where('idreporte_dia', $idReporte)
->where('concepto', $concepto)
->first();
return $row ? (float) $row->pago : 0;
}

private static function getControlgasConsumo(int $idReporte, string $concepto): float
{
$row = ClientesControlgas::where('idreporte_dia', $idReporte)
->where('concepto', $concepto)
->first();
return $row ? (float) $row->consumo : 0;
}

public static function getData(int $idReporte): array
{
$bancomer = self::getBaucher($idReporte, 'BBVA BANCOMER SA');
$amex = self::getBaucher($idReporte, 'AMERICAN EXPRESS');
$inburgas = self::getBaucher($idReporte, 'INBURGAS');
$inbursa = self::getBaucher($idReporte, 'INBURSA');
$totalTB = $bancomer + $amex + $inburgas + $inbursa;

$ticketcard = self::getBaucher($idReporte, 'TICKETCARD');
$g500fleet = self::getBaucher($idReporte, 'G500 FLETT');
$efecticard = self::getBaucher($idReporte, 'EFECTICARD');
$sodexo = self::getBaucher($idReporte, 'SODEXO');
$totalTarjetas = $ticketcard + $g500fleet + $efecticard + $sodexo;

$valeAccord = self::getBaucher($idReporte, 'VALE ACCORD');
$valeEfectivale = self::getBaucher($idReporte, 'VALE EFECTIVALE');
$valeSodexo = self::getBaucher($idReporte, 'VALE SODEXO');
$siVale = self::getBaucher($idReporte, 'SI VALE');
$totalVales = $valeAccord + $valeEfectivale + $valeSodexo + $siVale;

$pagoC = self::getControlgasPago($idReporte, 'CRÉDITO (ANEXO)');
$consumoC = self::getControlgasConsumo($idReporte, 'CRÉDITO (ANEXO)');
$pagoD = self::getControlgasPago($idReporte, 'DEBITO (ANEXO)');
$consumoD = self::getControlgasConsumo($idReporte, 'DEBITO (ANEXO)');
$totalPago = $pagoC + $pagoD;
$totalConsumo = $consumoC + $consumoD;

return [
'bancarias' => [
'bancomer' => $bancomer,
'amex' => $amex,
'inburgas' => $inburgas,
'inbursa' => $inbursa,
'total' => $totalTB,
],
'tarjetas_otros' => [
'ticketcard' => $ticketcard,
'g500fleet' => $g500fleet,
'efecticard' => $efecticard,
'sodexo' => $sodexo,
'total' => $totalTarjetas,
],
'vales' => [
'vale_accord' => $valeAccord,
'vale_efectivale' => $valeEfectivale,
'vale_sodexo' => $valeSodexo,
'si_vale' => $siVale,
'total' => $totalVales,
],
'credito' => [
'pago' => $pagoC,
'consumo' => $consumoC,
],
'debito' => [
'pago' => $pagoD,
'consumo' => $consumoD,
],
'total_pago' => $totalPago,
'total_consumo' => $totalConsumo,
];
}
}
