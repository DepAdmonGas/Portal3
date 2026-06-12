<?php

namespace App\Services;

use App\Models\Operativo\VentasDia;
use App\Models\Operativo\CorteYear;
use App\Models\Estacion;
use App\Core\Auth;
use App\Core\Session;

class ConcentradoVentasService
{
public static function getPermisos(): array
{
$sessionUsuario = Session::get('usuario');

return [
'multiestacion'         => !empty($sessionUsuario['multiestacion']),
'es_direccion_operaciones' => ($sessionUsuario['id_puesto'] ?? 0) == 1,
'puedeLeer'             => ModuloDptoOperativoService::validaPermiso('corporativo', 'leer'),
'puedeDescargar'        => ModuloDptoOperativoService::validaPermiso('corporativo', 'descargar'),
];
}

public static function getProductosEstacion(int $idEstacion): array
{
$estacion = Estacion::find($idEstacion);
$productos = [];
foreach (['producto_uno', 'producto_dos', 'producto_tres'] as $col) {
$nombre = $estacion ? trim($estacion->$col ?? '') : '';
if ($nombre !== '') {
$productos[] = $nombre;
}
}
return $productos;
}

public static function getDiasDelMes(int $idEstacion, int $idYear, int $idMes): array
{
return CorteYear::where('op_corte_year.id_estacion', $idEstacion)
->where('op_corte_year.year', $idYear)
->join('op_corte_mes', 'op_corte_year.id', '=', 'op_corte_mes.id_year')
->join('op_corte_dia', 'op_corte_mes.id', '=', 'op_corte_dia.id_mes')
->where('op_corte_mes.mes', $idMes)
->select('op_corte_dia.id AS idDia', 'op_corte_dia.fecha')
->orderBy('op_corte_dia.fecha')
->get()
->toArray();
}

public static function getVentasProducto(int $idDia, string $producto): array
{
$ventas = VentasDia::where('idreporte_dia', $idDia)
->where('producto', $producto)
->get();

$TotalLitros = 0;
$TotalPrecio = 0;

foreach ($ventas as $v) {
$litros = (float) $v->litros;
$precio = (float) $v->precio_litro;
$TotalLitros += $litros;
$TotalPrecio += $litros * $precio;
}

return [
'TotalLitros' => $TotalLitros,
'TotalPrecio' => $TotalPrecio,
];
}

public static function getDataMensual(int $idEstacion, int $idYear, int $idMes): array
{
$productos = self::getProductosEstacion($idEstacion);
$dias = self::getDiasDelMes($idEstacion, $idYear, $idMes);

$totalesPorProducto = [];
foreach ($productos as $p) {
$totalesPorProducto[$p] = ['TotalLitros' => 0, 'TotalPrecio' => 0];
}

$dailyRows = [];
foreach ($dias as $dia) {
$idDia = $dia['idDia'];
$row = [
'fecha'     => formatearFecha($dia['fecha'] instanceof \Carbon\Carbon ? $dia['fecha']->format('Y-m-d') : $dia['fecha']),
'idDia'     => $idDia,
'productos' => [],
];

foreach ($productos as $p) {
$venta = self::getVentasProducto($idDia, $p);
$row['productos'][$p] = $venta;
$totalesPorProducto[$p]['TotalLitros'] += $venta['TotalLitros'];
$totalesPorProducto[$p]['TotalPrecio'] += $venta['TotalPrecio'];
}

$dailyRows[] = $row;
}

return [
'productos' => $productos,
'daily'     => $dailyRows,
'totales'   => $totalesPorProducto,
];
}
}
