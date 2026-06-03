<?php

namespace App\Services;

use App\Models\Operativo\AceiteFactura;
use App\Models\Operativo\AceiteDocumento;

class KpiAceitesService
{
private static array $estaciones = [
1 => 'Interlomas',
2 => 'Palo Solo',
3 => 'San Agustin',
4 => 'Gasomira',
5 => 'Valle de Guadalupe',
6 => 'Esmegas',
7 => 'Xochimilco',
14 => 'Bosque Real',
];

private static array $colores_mensual = [
'#ca6ed0', '#dc3912', '#ff9900', '#109618',
'#990099', '#0099c6', '#dd4477', '#66aa00',
'#b82e2e', '#316395', '#994499', '#22aa99', '#FFD300',
];

private static array $colores_estaciones = [
'#3366cc', '#dc3912', '#ff9900', '#109618',
'#990099', '#0099c6', '#dd4477', '#66aa00',
];

private static array $info_evaluacion = [
1 => '<p>La evaluación de las Notas de Remisión se lleva a cabo según las 3 notas base: QUAKER STATE, G5 y BARDAHL. Cada uno de ellos tiene un valor de <b>3 puntos</b>, y la puntuación total se obtiene mediante la suma, alcanzando un máximo de <b>9 puntos</b>.<br><br>1. Se otorgarán <b>3 puntos</b> si la Nota de Remisión se carga antes del día 20 o en días anteriores del mes.<br>2. Se otorgarán <b>2 puntos</b> si la Nota de Remisión se cargue entre el día 21 y el día 25 del mes.<br>3. Se otorgará <b>1 punto</b> si la Nota de Remisión se carga entre el día 26 y el día 28 del mes.<br>4. No se asignarán puntos si la Nota de Remisión se carga a partir del día 29 del mes en adelante.</p><p>Este proceso implica otorgar una puntuación acumulativa, donde cada Nota de Remisión contribuye a incrementar el puntaje total. Es importante llevar un registro preciso de cada registro de las Notas de Remisión efectuado en la estación, por lo que se debera de evitar el no subir la documentacion para tener un buen historial.</p><h6>No. de aperturas por estación:</h6><p>La evaluación de las Notas de Remisión se visualizara de acuerdo a los 12 meses del año (<b>Mensual</b>), y se presentará el resumen general de la evaluación obtenida durante todo el año (<b>Anual</b>).</p><h6>No. de aperturas de todas las estaciones:</h6><p>La evaluación de la Notas de Remisión se mostrará un resumen general de la evaluación obtenida a lo largo de todo el año (Anual). Esto permitirá destacar las estaciones que están llevando a cabo el proceso de manera efectiva.<br><br><b>Nota: Se considerará una mejor evaluación a aquellas estaciones con un mayor número de cumplimiento (9 puntos al mes y 108 puntos de manera anual).</b></p>',
2 => '<p>La evaluación de las facturas se lleva a cabo según las 3 facturas base: QUAKER STATE, G5 y BARDAHL. Cada uno de ellos tiene un valor de <b>3 puntos</b>, y la puntuación total se obtiene mediante la suma, alcanzando un máximo de <b>9 puntos</b>.<br><br>1. Se otorgarán <b>3 puntos</b> si la factura se carga antes del día 20 o en días anteriores del mes.<br>2. Se otorgarán <b>2 puntos</b> si la factura se cargue entre el día 21 y el día 25 del mes.<br>3. Se otorgará <b>1 punto</b> si la factura se carga entre el día 26 y el día 28 del mes.<br>4. No se asignarán puntos si la factura se carga a partir del día 29 del mes en adelante.</p><p>Este proceso implica otorgar una puntuación acumulativa, donde cada factura contribuye a incrementar el puntaje total. Es importante llevar un registro preciso de cada registro de las facturas efectuado en la estación, por lo que se debera de evitar el no subir la documentacion para tener un buen historial.</p><h6>No. de aperturas por estación:</h6><p>La evaluación de las facturas se visualizara de acuerdo a los 12 meses del año (<b>Mensual</b>), y se presentará el resumen general de la evaluación obtenida durante todo el año (<b>Anual</b>).</p><h6>No. de aperturas de todas las estaciones:</h6><p>La evaluación de las facturas se mostrará un resumen general de la evaluación obtenida a lo largo de todo el año (Anual). Esto permitirá destacar las estaciones que están llevando a cabo el proceso de manera efectiva.<br><br><b>Nota: Se considerará una mejor evaluación a aquellas estaciones con un mayor número de cumplimiento (9 puntos al mes y 108 puntos de manera anual).</b></p>',
3 => '<p>La evaluación de las Facturas Venta Mostrador se lleva a cabo de acuerdo a lo siguiente:<br><br>1. Se otorgarán <b>3 puntos</b> si la factura se carga antes del día 2 o en días anteriores del mes.<br>2. Se otorgarán <b>2 puntos</b> si la factura se cargue el dia 3 del mes.<br>3. Se otorgará <b>1 punto</b> si la factura se carga entre el dia 4 del mes.<br>4. No se asignarán puntos si la factura se carga a partir del día 5 del mes en adelante.</p><p>Este proceso implica otorgar una puntuación acumulativa, donde cada factura contribuye a incrementar el puntaje total. Es importante llevar un registro preciso de cada registro de las facturas efectuado en la estación, por lo que se debera de evitar el no subir la documentacion para tener un buen historial.</p><h6>No. de aperturas por estación:</h6><p>La evaluación de las facturas se visualizara de acuerdo a los 12 meses del año (<b>Mensual</b>), y se presentará el resumen general de la evaluación obtenida durante todo el año (<b>Anual</b>).</p><h6>No. de aperturas de todas las estaciones:</h6><p>La evaluación de las facturas se mostrará un resumen general de la evaluación obtenida a lo largo de todo el año (Anual). Esto permitirá destacar las estaciones que están llevando a cabo el proceso de manera efectiva.<br><br><b>Nota: Se considerará una mejor evaluación a aquellas estaciones con un mayor número de cumplimiento (3 puntos al mes y 36 puntos de manera anual).</b></p>',
4 => '<p>La evaluación de las Ficha de Deposito Faltante se lleva a cabo de acuerdo a lo siguiente:<br><br>1. Se otorgarán <b>3 puntos</b> si la ficha se carga antes del día 2 o en días anteriores del mes.<br>2. Se otorgarán <b>2 puntos</b> si la ficha se cargue entre el día 3 y el día 10 del mes.<br>3. Se otorgará <b>1 punto</b> si la ficha se cargue entre el día 11 y el día 20 del mes.<br>4. No se asignarán puntos si la ficha se carga a partir del día 21 del mes en adelante.</p><p>Este proceso implica otorgar una puntuación acumulativa, donde cada ficha contribuye a incrementar el puntaje total. Es importante llevar un registro preciso de cada registro de las fichas efectuado en la estación, por lo que se debera de evitar el no subir la documentacion para tener un buen historial.</p><h6>No. de aperturas por estación:</h6><p>La evaluación de las ficha se visualizara de acuerdo a los 12 meses del año (<b>Mensual</b>), y se presentará el resumen general de la evaluación obtenida durante todo el año (<b>Anual</b>).</p><h6>No. de aperturas de todas las estaciones:</h6><p>La evaluación de las ficha se mostrará un resumen general de la evaluación obtenida a lo largo de todo el año (Anual). Esto permitirá destacar las estaciones que están llevando a cabo el proceso de manera efectiva.<br><br><b>Nota: Se considerará una mejor evaluación a aquellas estaciones con un mayor número de cumplimiento (3 puntos al mes y 36 puntos de manera anual).</b></p>',
];

private static array $iconos = [
1 => 'ti ti-file-description',
2 => 'ti ti-file-invoice',
3 => 'ti ti-shopping-cart',
4 => 'ti ti-alert-triangle',
];

public static function getOpciones(): array
{
return [
1 => ['id' => 1, 'titulo' => 'Notas de Remisión', 'icono' => self::$iconos[1]],
2 => ['id' => 2, 'titulo' => 'Facturas', 'icono' => self::$iconos[2]],
3 => ['id' => 3, 'titulo' => 'Facturas Venta Mostrador', 'icono' => self::$iconos[3]],
4 => ['id' => 4, 'titulo' => 'Fichas de Deposito Faltante', 'icono' => self::$iconos[4]],
];
}

public static function getTipoData(int $idEstacion, int $idYear, int $tipo): array
{
$info = self::$info_evaluacion[$tipo] ?? '';

$tipoData = match ($tipo) {
1 => self::getTipoNotasRemision($idEstacion, $idYear),
2 => self::getTipoFacturas($idEstacion, $idYear),
3 => self::getTipoVentaMostrador($idEstacion, $idYear),
4 => self::getTipoFaltantes($idEstacion, $idYear),
default => null,
};

if (!$tipoData) {
return [];
}

$mejorEstacion = self::calcularMejorEstacion($tipoData['anual']['obtenido'], $tipoData['puntaje_anual']);

return [
'estacion_nombre' => self::$estaciones[$idEstacion] ?? 'Estación',
'estacion_id' => $idEstacion,
'year' => $idYear,
'estaciones' => self::$estaciones,
'colores_mensual' => self::$colores_mensual,
'colores_estaciones' => self::$colores_estaciones,
'info' => $info,
'titulo' => $tipoData['titulo'],
'puntaje_mensual' => $tipoData['puntaje_mensual'],
'puntaje_anual' => $tipoData['puntaje_anual'],
'mensual' => $tipoData['mensual'],
'anual' => $tipoData['anual'],
'mejor_estacion' => $mejorEstacion,
];
}

public static function getData(int $idEstacion, int $idYear): array
{
return [
'estacion_nombre' => self::$estaciones[$idEstacion] ?? 'Estación',
'estacion_id' => $idEstacion,
'year' => $idYear,
'estaciones' => self::$estaciones,
'colores_mensual' => self::$colores_mensual,
'colores_estaciones' => self::$colores_estaciones,
'info_evaluacion' => self::$info_evaluacion,
'tipos' => [
1 => self::getTipoNotasRemision($idEstacion, $idYear),
2 => self::getTipoFacturas($idEstacion, $idYear),
3 => self::getTipoVentaMostrador($idEstacion, $idYear),
4 => self::getTipoFaltantes($idEstacion, $idYear),
],
];
}

private static function getTipoNotasRemision(int $idEstacion, int $idYear): array
{
return [
'titulo' => 'Notas de Remisión',
'puntaje_mensual' => 9,
'puntaje_anual' => 108,
'mensual' => self::calcularMensualFactura($idEstacion, $idYear, '%Nota%', 9),
'anual' => self::calcularAnualFactura($idYear, '%Nota%', 9),
'mejor_estacion' => null,
];
}

private static function getTipoFacturas(int $idEstacion, int $idYear): array
{
return [
'titulo' => 'Facturas',
'puntaje_mensual' => 9,
'puntaje_anual' => 108,
'mensual' => self::calcularMensualFactura($idEstacion, $idYear, '%Factura%', 9),
'anual' => self::calcularAnualFactura($idYear, '%Factura%', 9),
'mejor_estacion' => null,
];
}

private static function getTipoVentaMostrador(int $idEstacion, int $idYear): array
{
return [
'titulo' => 'Facturas Venta Mostrador',
'puntaje_mensual' => 3,
'puntaje_anual' => 36,
'mensual' => self::calcularMensualDocumento($idEstacion, $idYear, 'puntaje_ficha', 'ficha_deposito', 3),
'anual' => self::calcularAnualDocumento($idYear, 'puntaje_ficha', 'ficha_deposito', 3),
'mejor_estacion' => null,
];
}

private static function getTipoFaltantes(int $idEstacion, int $idYear): array
{
return [
'titulo' => 'Fichas de Deposito Faltante',
'puntaje_mensual' => 3,
'puntaje_anual' => 36,
'mensual' => self::calcularMensualDocumento($idEstacion, $idYear, 'puntaje_factura', 'factura_venta', 3),
'anual' => self::calcularAnualDocumento($idYear, 'puntaje_factura', 'factura_venta', 3),
'mejor_estacion' => null,
];
}

private static function calcularMensualFactura(int $idEstacion, int $idYear, string $likeFilter, int $maxMensual): array
{
$categorias = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$obtenido = array_fill(0, 12, 0);
$maximo = array_fill(0, 12, $maxMensual);

$rows = AceiteFactura::join('op_corte_mes', 'op_aceites_factura.id_mes', '=', 'op_corte_mes.id')
->join('op_corte_year', 'op_corte_mes.id_year', '=', 'op_corte_year.id')
->where('op_corte_year.id_estacion', $idEstacion)
->where('op_corte_year.year', $idYear)
->where('op_aceites_factura.nombre_anexo', 'like', $likeFilter)
->groupBy('op_corte_mes.mes', 'op_aceites_factura.nombre_anexo')
->selectRaw('op_corte_mes.mes, op_aceites_factura.nombre_anexo, SUM(op_aceites_factura.puntaje) as suma_puntajes, COUNT(op_aceites_factura.id) as cantidad_registros')
->get();

$monthData = [];
foreach ($rows as $r) {
$monthData[$r->mes][] = $r;
}

foreach ($monthData as $mesNum => $grupos) {
$puntajeCalculo = 0;
foreach ($grupos as $g) {
$puntajeCalculo += ($g->cantidad_registros > 0) ? $g->suma_puntajes / $g->cantidad_registros : 0;
}
$obtenido[$mesNum - 1] = round($puntajeCalculo, 2);
}

$categorias[] = 'Anual';
$totalAnual = array_sum($obtenido);
$obtenido[] = round($totalAnual, 2);
$maximo[] = $maxMensual * 12;

return ['categorias' => $categorias, 'obtenido' => $obtenido, 'maximo' => $maximo];
}

private static function calcularAnualFactura(int $idYear, string $likeFilter, int $maxMensual): array
{
$ids = array_keys(self::$estaciones);
$nombres = array_values(self::$estaciones);
$obtenido = array_fill(0, count($ids), 0);
$maximo = array_fill(0, count($ids), $maxMensual * 12);
$anualMax = $maxMensual * 12;

foreach ($ids as $idx => $idEstacion) {
$rows = AceiteFactura::join('op_corte_mes', 'op_aceites_factura.id_mes', '=', 'op_corte_mes.id')
->join('op_corte_year', 'op_corte_mes.id_year', '=', 'op_corte_year.id')
->where('op_corte_year.id_estacion', $idEstacion)
->where('op_corte_year.year', $idYear)
->where('op_aceites_factura.nombre_anexo', 'like', $likeFilter)
->groupBy('op_corte_mes.mes', 'op_aceites_factura.nombre_anexo')
->selectRaw('op_corte_mes.mes, op_aceites_factura.nombre_anexo, SUM(op_aceites_factura.puntaje) as suma_puntajes, COUNT(op_aceites_factura.id) as cantidad_registros')
->get();

$puntajeCalculo = 0;
foreach ($rows as $r) {
$puntajeCalculo += ($r->cantidad_registros > 0) ? $r->suma_puntajes / $r->cantidad_registros : 0;
}
$obtenido[$idx] = round($puntajeCalculo, 2);
}

return ['categorias' => $nombres, 'obtenido' => $obtenido, 'maximo' => $maximo];
}

private static function calcularMensualDocumento(int $idEstacion, int $idYear, string $puntajeField, string $groupByField, int $maxMensual): array
{
$categorias = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$obtenido = array_fill(0, 12, 0);
$maximo = array_fill(0, 12, $maxMensual);

$rows = AceiteDocumento::join('op_corte_mes', 'op_aceites_documento.id_mes', '=', 'op_corte_mes.id')
->join('op_corte_year', 'op_corte_mes.id_year', '=', 'op_corte_year.id')
->where('op_corte_year.id_estacion', $idEstacion)
->where('op_corte_year.year', $idYear)
->groupBy('op_corte_mes.mes', 'op_aceites_documento.' . $groupByField)
->selectRaw("op_corte_mes.mes, SUM(op_aceites_documento.{$puntajeField}) as suma_puntajes, COUNT(op_aceites_documento.id) as cantidad_registros")
->get();

$monthData = [];
foreach ($rows as $r) {
$monthData[$r->mes][] = $r;
}

foreach ($monthData as $mesNum => $grupos) {
$puntajeCalculo = 0;
foreach ($grupos as $g) {
$puntajeCalculo += ($g->cantidad_registros > 0) ? $g->suma_puntajes / $g->cantidad_registros : 0;
}
$obtenido[$mesNum - 1] = round($puntajeCalculo, 2);
}

$categorias[] = 'Anual';
$totalAnual = array_sum($obtenido);
$obtenido[] = round($totalAnual, 2);
$maximo[] = $maxMensual * 12;

return ['categorias' => $categorias, 'obtenido' => $obtenido, 'maximo' => $maximo];
}

private static function calcularAnualDocumento(int $idYear, string $puntajeField, string $groupByField, int $maxMensual): array
{
$ids = array_keys(self::$estaciones);
$nombres = array_values(self::$estaciones);
$obtenido = array_fill(0, count($ids), 0);
$maximo = array_fill(0, count($ids), $maxMensual * 12);

foreach ($ids as $idx => $idEstacion) {
$rows = AceiteDocumento::join('op_corte_mes', 'op_aceites_documento.id_mes', '=', 'op_corte_mes.id')
->join('op_corte_year', 'op_corte_mes.id_year', '=', 'op_corte_year.id')
->where('op_corte_year.id_estacion', $idEstacion)
->where('op_corte_year.year', $idYear)
->groupBy('op_corte_mes.mes', 'op_aceites_documento.' . $groupByField)
->selectRaw("op_corte_mes.mes, SUM(op_aceites_documento.{$puntajeField}) as suma_puntajes, COUNT(op_aceites_documento.id) as cantidad_registros")
->get();

$puntajeCalculo = 0;
foreach ($rows as $r) {
$puntajeCalculo += ($r->cantidad_registros > 0) ? $r->suma_puntajes / $r->cantidad_registros : 0;
}
$obtenido[$idx] = round($puntajeCalculo, 2);
}

return ['categorias' => $nombres, 'obtenido' => $obtenido, 'maximo' => $maximo];
}

private static function calcularMejorEstacion(array $obtenido, int $anualMax = 108): string
{
if (empty($obtenido)) return '';
$nombres = array_values(self::$estaciones);
$maxVal = max($obtenido);
$mejores = [];
foreach ($obtenido as $i => $val) {
if ($val === $maxVal && isset($nombres[$i])) {
$mejores[] = $nombres[$i];
}
}
return implode(', ', $mejores) . ' - Puntaje: ' . $maxVal . ' de ' . $anualMax;
}
}
