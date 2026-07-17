<?php

namespace App\Services;

use App\Models\Operativo\CorteMes;
use App\Models\Operativo\CorteDia;
use App\Models\Operativo\DespachoFactura;
use App\Models\Operativo\VentasDia;
use App\Models\Estacion;
use App\Core\Session;

class DespachoVentasService
{
public static function getData(int $idEstacion, int $idYear, int $idMes): array
{
$corteMes = CorteMes::whereHas('year', function ($q) use ($idEstacion, $idYear) {
$q->where('id_estacion', $idEstacion)->where('year', $idYear);
})->where('mes', $idMes)->first();

if (!$corteMes) {
return ['success' => false, 'message' => 'No hay datos para el periodo seleccionado'];
}

$estacion = Estacion::find($idEstacion);
$productos = self::getProductosEstacion($estacion);

$dias = CorteDia::where('id_mes', $corteMes->id)
->orderBy('fecha', 'asc')
->get();

$filas = [];
$totales = self::initTotales();

foreach ($dias as $dia) {
self::validaDia($dia->id);

$ventas = self::getVentasDelDia($dia->id, $productos);
$despacho = self::getDespachoDelDia($dia->id, $productos);
$diff = self::calcularDiff($ventas, $despacho);

$filas[] = [
'id_dia' => $dia->id,
'fecha' => $dia->fecha,
'fecha_formateada' => formatearFecha($dia->fecha),
'ventas' => $ventas,
'despacho' => $despacho,
'diff' => $diff,
];

self::acumularTotales($totales, $ventas, $despacho, $diff);
}

return [
'success' => true,
'id_mes' => $corteMes->id,
'productos' => $productos,
'num_productos' => count($productos),
'dias' => $filas,
'totales' => $totales,
];
}

public static function updateCell(int $idDia, float $valor, int $despacho): bool
{
self::validaDia($idDia);

$columna = self::despachoToColumna($despacho);
if (!$columna) return false;

return DespachoFactura::where('id_dia', $idDia)->update([$columna => $valor]);
}

private static function getVentasDelDia(int $idDia, array $productos): array
{
$result = [];
foreach ($productos as $idx => $prod) {
$v = self::getVentasProducto($idDia, $prod);
$result['l' . ($idx + 1)] = $v['TotalLitros'];
$result['p' . ($idx + 1)] = $v['TotalPrecio'];
}

$result['lt'] = array_sum(array_map(fn($i) => $result['l' . ($i + 1)] ?? 0, array_keys($productos)));
$result['pt'] = array_sum(array_map(fn($i) => $result['p' . ($i + 1)] ?? 0, array_keys($productos)));

return $result;
}

private static function getDespachoDelDia(int $idDia, array $productos): array
{
$despacho = DespachoFactura::where('id_dia', $idDia)->first();

$map = [
0 => ['l' => 'litros_producto_uno', 'p' => 'pesos_producto_uno'],
1 => ['l' => 'litros_producto_dos', 'p' => 'pesos_producto_dos'],
2 => ['l' => 'litros_producto_tres', 'p' => 'pesos_producto_tres'],
];

$result = [];
foreach ($productos as $idx => $prod) {
$result['l' . ($idx + 1)] = (float) ($despacho->{$map[$idx]['l']} ?? 0);
$result['p' . ($idx + 1)] = (float) ($despacho->{$map[$idx]['p']} ?? 0);
}

$result['lt'] = array_sum(array_map(fn($i) => $result['l' . ($i + 1)] ?? 0, array_keys($productos)));
$result['pt'] = array_sum(array_map(fn($i) => $result['p' . ($i + 1)] ?? 0, array_keys($productos)));

return $result;
}

private static function calcularDiff(array $ventas, array $despacho): array
{
$diff = [];
foreach (['l1', 'p1', 'l2', 'p2', 'l3', 'p3', 'lt', 'pt'] as $k) {
$diff[$k] = ($ventas[$k] ?? 0) - ($despacho[$k] ?? 0);
}
return $diff;
}

private static function getVentasProducto(int $idDia, string $producto): array
{
$ventas = VentasDia::where('idreporte_dia', $idDia)
->where('producto', $producto)
->get();

$totalLitros = 0;
$totalPrecio = 0;

foreach ($ventas as $v) {
$litros = (float) $v->litros;
$totalLitros += $litros;
$totalPrecio += $litros * (float) $v->precio_litro;
}

return [
'TotalLitros' => $totalLitros,
'TotalPrecio' => $totalPrecio,
];
}

private static function validaDia(int $idDia): void
{
$existe = DespachoFactura::where('id_dia', $idDia)->exists();
if (!$existe) {
DespachoFactura::create(['id_dia' => $idDia]);
}
}

private static function getProductosEstacion(?Estacion $estacion): array
{
$productos = [];
foreach (['producto_uno', 'producto_dos', 'producto_tres'] as $col) {
$nombre = $estacion ? trim($estacion->$col ?? '') : '';
if ($nombre !== '') {
$productos[] = $nombre;
}
}
return $productos;
}

private static function despachoToColumna(int $despacho): ?string
{
$map = [
1 => 'litros_producto_uno',
2 => 'litros_producto_dos',
3 => 'litros_producto_tres',
4 => 'pesos_producto_uno',
5 => 'pesos_producto_dos',
6 => 'pesos_producto_tres',
];
return $map[$despacho] ?? null;
}

private static function initTotales(): array
{
$t = [];
foreach (['ventas', 'despacho', 'diff'] as $group) {
$t[$group] = ['l1' => 0, 'p1' => 0, 'l2' => 0, 'p2' => 0, 'l3' => 0, 'p3' => 0, 'lt' => 0, 'pt' => 0];
}
return $t;
}

private static function acumularTotales(array &$totales, array $ventas, array $despacho, array $diff): void
{
foreach (['l1', 'p1', 'l2', 'p2', 'l3', 'p3', 'lt', 'pt'] as $k) {
$totales['ventas'][$k] += $ventas[$k] ?? 0;
$totales['despacho'][$k] += $despacho[$k] ?? 0;
$totales['diff'][$k] += $diff[$k] ?? 0;
}
}

public static function getPermisos(): array
{
$usuario = Session::get('usuario');
return [
'multiestacion' => !empty($usuario['multiestacion']),
'id_puesto' => $usuario['id_puesto'] ?? 0,
];
}

public static function esNegativo(float $valor): string
{
return $valor < 0 ? 'text-danger fw-bold' : '';
}
}
