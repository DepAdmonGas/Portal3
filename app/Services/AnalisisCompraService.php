<?php

namespace App\Services;

use App\Models\Operativo\AnalisisCompra;
use App\Core\Auth;
use App\Core\Session;
use Illuminate\Database\Capsule\Manager as DB;

class AnalisisCompraService
{
public static function getPermisos(): array
{
$usuario = Auth::user();
$sessionUsuario = Session::get('usuario');
$multiEstacion = $sessionUsuario['multiestacion'] ?? false;

$esDireccionOperaciones = false;
$esContabilidad = false;
$esComercializadora = false;

if ($usuario && $usuario->puesto) {
$tipo = $usuario->puesto->tipo_puesto ?? '';
$esDireccionOperaciones = $tipo === 'Dirección de operaciones';
$esContabilidad = $tipo === 'Contabilidad';
$esComercializadora = $tipo === 'Comercializadora';
}

return [
'multiestacion' => $multiEstacion,
'es_direccion_operaciones' => $esDireccionOperaciones,
'es_contabilidad' => $esContabilidad,
'es_comercializadora' => $esComercializadora,
'id_usuario' => $sessionUsuario['id'] ?? 0,
'puede_editar' => $esDireccionOperaciones || $esContabilidad || $esComercializadora,
];
}

public static function getDatos(int $idEstacion, int $idYear, int $idMes): array
{
$rows = DB::connection()->select("
SELECT
pipas.id,
pipas.id_re_producto,
pipas.volumen,
pipas.precio_litro,
pipas.no_factura,
pipas.nombre_razonsocial,
pipas.importe_total,
producto.id_re_mes,
producto.fecha,
producto.producto,
mes.id_estacion
FROM re_reporte_cre_pipas pipas
INNER JOIN re_reporte_cre_producto producto ON pipas.id_re_producto = producto.id
INNER JOIN re_reporte_cre_mes mes ON producto.id_re_mes = mes.id
WHERE mes.id_estacion = ?
AND YEAR(producto.fecha) = ?
AND MONTH(producto.fecha) = ?
ORDER BY producto.fecha ASC
", [$idEstacion, $idYear, $idMes]);

$result = [];
foreach ($rows as $row) {
$r = self::calcularRow($row);
if ($r) {
$result[] = $r;
}
}

return $result;
}

private static function calcularRow($row): ?array
{
$fecha = $row->fecha ?? '';
$volumen = (float)($row->volumen ?? 0);
$precioLitro = (float)($row->precio_litro ?? 0);
$importeTotal = (float)($row->importe_total ?? 0);
$noFactura = $row->no_factura ?? '';
$productoStr = $row->producto ?? '';
$idEstacion = (int)($row->id_estacion ?? 0);

$productoLabel = match ($productoStr) {
'G SUPER' => '87 Oct',
'G PREMIUM' => '91 Oct',
'G DIESEL' => 'DIESEL',
default => $productoStr,
};

$embarqueData = self::getEmbarque($fecha, $productoStr, $volumen, $idEstacion);
$tuxpaData = self::getTuxpa($fecha, $productoStr, $idEstacion, $noFactura);
$precioPemex = self::getPrecioPemex($fecha, $productoStr);
$analisisCompra = self::getAnalisisCompra($fecha, $noFactura);

$litrosFacturados = $volumen;
$cuentaLitros = $tuxpaData['cuenta_litros'];
$mermaCuentaLitros = $litrosFacturados - $cuentaLitros;
$tolerancia = (int)(($volumen * 0.55) / 100);

$importeFacturado = $precioLitro;
$importeTransporte = $litrosFacturados > 0 ? $importeTotal / $litrosFacturados : 0;
$precioPickup = $importeFacturado + $importeTransporte;

$precioPemexVal = (float)$precioPemex;
$diferencia = $precioPickup - $precioPemexVal;
$importeMermaTotal = $mermaCuentaLitros * $precioPickup;
$merma = $litrosFacturados - $cuentaLitros - $tolerancia;
$importeMerma = $merma * $importeFacturado;
$importeNota = ($mermaCuentaLitros - $tolerancia) * $importeFacturado;
$montoFactura = $importeTotal;
$totalPagarTransporte = $montoFactura - $importeNota;
$pickup = $litrosFacturados - $precioPickup;
$pemex = $litrosFacturados - $precioPemexVal;
$diferenciaPemex = $pickup - $pemex;

return [
'tad' => $embarqueData['tad'],
'fecha' => $fecha,
'no_factura' => $noFactura,
'nombre_razonsocial' => $row->nombre_razonsocial ?? '',
'litros_facturados' => $litrosFacturados,
'cuenta_litros' => $cuentaLitros,
'merma_cuenta_litros' => $mermaCuentaLitros,
'tolerancia' => $tolerancia,
'producto' => $productoLabel,
'transporte' => $embarqueData['transporte'],
'unidad' => $embarqueData['unidad'],
'chofer' => $embarqueData['chofer'],
'importe_facturado' => $importeFacturado,
'importe_transporte' => $importeTransporte,
'precio_pickup' => $precioPickup,
'precio_pemex' => $precioPemexVal,
'diferencia' => $diferencia,
'diferencia_pemex' => $diferenciaPemex,
'importe_merma_total' => $importeMermaTotal,
'merma' => $merma,
'importe_merma' => $importeMerma,
'notac' => $analisisCompra['notac'],
'importe_nota' => $importeNota,
'factura_transporte' => $tuxpaData['factura_transporte'],
'monto_factura' => $montoFactura,
'total_pagar_transporte' => $totalPagarTransporte,
'status' => $analisisCompra['status'],
'pickup' => $pickup,
'pemex' => $pemex,
];
}

private static function getEmbarque(string $fecha, string $producto, float $volumen, int $idEstacion): array
{
$default = ['tad' => '', 'transporte' => '', 'unidad' => '', 'chofer' => ''];

$rows = DB::connection()->select("
SELECT e.tad, e.nom_transporte, e.unidad, e.chofer
FROM op_embarques e
INNER JOIN op_corte_mes cm ON e.id_mes = cm.id
INNER JOIN op_corte_year cy ON cm.id_year = cy.id
WHERE e.fecha = ?
AND e.producto = ?
AND e.importef = ?
AND cy.id_estacion = ?
", [$fecha, $producto, $volumen, $idEstacion]);

if (empty($rows)) return $default;

$last = end($rows);
return [
'tad' => $last->tad ?? '',
'transporte' => $last->nom_transporte ?? '',
'unidad' => $last->unidad ?? '',
'chofer' => $last->chofer ?? '',
];
}

private static function getTuxpa(string $fecha, string $producto, int $idEstacion, string $factura): array
{
$default = ['cuenta_litros' => 0, 'factura_transporte' => ''];

$productoMap = match ($producto) {
'G SUPER' => '87 oct',
'G PREMIUM' => '91 oct',
'G DIESEL' => 'Diesel',
default => $producto,
};

$facturaLimpia = explode('-', $factura)[0];

$rows = DB::connection()->select("
SELECT cuenta_litros, no_factura_remision
FROM op_descarga_tuxpa
WHERE id_estacion = ?
AND fecha_llegada = ?
AND producto = ?
AND SUBSTRING_INDEX(no_factura_remision, '-', 1) = ?
", [$idEstacion, $fecha, $productoMap, $facturaLimpia]);

if (empty($rows)) return $default;

$last = end($rows);
return [
'cuenta_litros' => (float)($last->cuenta_litros ?? 0),
'factura_transporte' => $last->no_factura_remision ?? '',
];
}

private static function getPrecioPemex(string $fecha, string $producto): float
{
$productoMap = match ($producto) {
'G SUPER' => 'Super',
'G PREMIUM' => 'Premium',
'G DIESEL' => 'Diesel',
default => $producto,
};

$rows = DB::connection()->select("
SELECT d.pemex
FROM op_formato_precios_detalle_c d
INNER JOIN op_formato_precios p ON d.id_precio = p.id
WHERE p.fecha = ?
AND d.producto = ?
", [$fecha, $productoMap]);

if (empty($rows)) return 0;

$last = end($rows);
return (float)($last->pemex ?? 0);
}

private static function getAnalisisCompra(string $fecha, string $factura): array
{
$default = ['notac' => '', 'status' => ''];

$record = AnalisisCompra::where('fecha', $fecha)
->where('factura', $factura)
->first();

if (!$record) return $default;

return [
'notac' => $record->notac ?? '',
'status' => $record->status ?? '',
];
}

public static function getTotals(array $rows): array
{
$totalDiferenciaPemex = 0;
$totalImporteMerma = 0;
$totalImporteNota = 0;
$totalPickup = 0;
$totalPemex = 0;

foreach ($rows as $row) {
$totalDiferenciaPemex += $row['diferencia_pemex'];
$totalImporteMerma += $row['importe_merma'];
$totalImporteNota += $row['importe_nota'];
$totalPickup += $row['pickup'];
$totalPemex += $row['pemex'];
}

return [
'diferencia_pemex' => $totalDiferenciaPemex,
'importe_merma' => $totalImporteMerma,
'importe_nota' => $totalImporteNota,
'pickup' => $totalPickup,
'pemex' => $totalPemex,
];
}

public static function updateNotac(string $fecha, string $factura, string $valor): array
{
$record = AnalisisCompra::where('fecha', $fecha)
->where('factura', $factura)
->first();

if ($record) {
$record->notac = $valor;
$record->save();
} else {
AnalisisCompra::create([
'fecha' => $fecha,
'factura' => $factura,
'notac' => $valor,
'status' => '',
]);
}

return ['success' => true];
}

public static function updateStatus(string $fecha, string $factura, string $valor): array
{
$record = AnalisisCompra::where('fecha', $fecha)
->where('factura', $factura)
->first();

if ($record) {
$record->status = $valor;
$record->save();
} else {
AnalisisCompra::create([
'fecha' => $fecha,
'factura' => $factura,
'notac' => '',
'status' => $valor,
]);
}

return ['success' => true];
}
}
