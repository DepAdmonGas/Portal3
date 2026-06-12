<?php

namespace App\Services;

use App\Models\Operativo\CorteDiaHist;

class KpiCorteDiarioService
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

private static string $info_evaluacion = '<p>La evaluación de la Apertura de Cortes Diarios se lleva a cabo mediante el registro de aperturas realizadas en cada estación. Cada apertura de corte diario tendrá un valor de <b>1 punto</b>, acumulándose conforme se realicen nuevos registros.</p><p>Este proceso implica otorgar una puntuación acumulativa, donde cada apertura de corte diario contribuye a incrementar el puntaje total. Es importante llevar un registro preciso de cada apertura realizada en la estación, por lo que se deberá evitar realizar aperturas constantes o innecesarias para mantener un buen historial de cumplimiento.</p><h6><strong>No. de aperturas por estación:</strong></h6><p>La evaluación de las aperturas de cortes diarios se visualizará de acuerdo a los 12 meses del año (<b>Mensual</b>), y se presentará el resumen general de la evaluación obtenida durante todo el año (<b>Anual</b>).</p><h6><strong>No. de aperturas de todas las estaciones:</strong></h6><p>La evaluación de las aperturas de cortes diarios mostrará un resumen general de los resultados obtenidos a lo largo de todo el año (Anual). Esto permitirá identificar las estaciones que están realizando correctamente el proceso.<br><br><b>Nota: Se considerará una mejor evaluación a aquellas estaciones que tengan un menor número de aperturas, ya que refleja un mejor control y cumplimiento del proceso.</b></p>';
private static array $coloresMensual = [
'#3366cc', '#dc3912', '#ff9900', '#109618',
'#990099', '#0099c6', '#dd4477', '#66aa00',
'#b82e2e', '#316395', '#994499', '#22aa99',
'#FFD300',
];

private static array $coloresAnual = [
'#3366cc', '#dc3912', '#ff9900', '#109618',
'#990099', '#0099c6', '#dd4477', '#66aa00',
];

public static function getData(int $idEstacion, int $idYear): array
{
$mensual = self::calcularMensual($idEstacion, $idYear);
$anual = self::calcularAnual($idYear);

$peorEstacion = self::calcularPeorEstacion($anual['obtenido']);

return [
'estacion_nombre' => self::$estaciones[$idEstacion] ?? 'Estación',
'estacion_id' => $idEstacion,
'year' => $idYear,
'estaciones' => self::$estaciones,
'colores_mensual' => self::$coloresMensual,
'colores_anual' => self::$coloresAnual,
'info' => self::$info_evaluacion,
'mensual' => $mensual,
'anual' => $anual,
'peor_estacion' => $peorEstacion,
];
}

public static function consultaAperturaMensual(int $idEstacion, int $idYear, int $mes): int
{
return CorteDiaHist::join('op_corte_dia', 'op_corte_dia_hist.id_corte', '=', 'op_corte_dia.id')
->join('op_corte_mes', 'op_corte_dia.id_mes', '=', 'op_corte_mes.id')
->join('op_corte_year', 'op_corte_mes.id_year', '=', 'op_corte_year.id')
->where('op_corte_year.id_estacion', $idEstacion)
->where('op_corte_year.year', $idYear)
->where('op_corte_mes.mes', $mes)
->count();
}

public static function consultaAperturaES(int $idEstacion, int $idYear): int
{
return CorteDiaHist::join('op_corte_dia', 'op_corte_dia_hist.id_corte', '=', 'op_corte_dia.id')
->join('op_corte_mes', 'op_corte_dia.id_mes', '=', 'op_corte_mes.id')
->join('op_corte_year', 'op_corte_mes.id_year', '=', 'op_corte_year.id')
->where('op_corte_year.id_estacion', $idEstacion)
->where('op_corte_year.year', $idYear)
->count();
}

private static function calcularMensual(int $idEstacion, int $idYear): array
{
$categorias = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$obtenido = [];

for ($mes = 1; $mes <= 12; $mes++) {
$obtenido[] = self::consultaAperturaMensual($idEstacion, $idYear, $mes);
}

$categorias[] = 'Anual';
$obtenido[] = array_sum($obtenido);

return [
'categorias' => $categorias,
'obtenido' => $obtenido,
'colores' => self::$coloresMensual,
];
}

private static function calcularAnual(int $idYear): array
{
$ids = array_keys(self::$estaciones);
$nombres = array_values(self::$estaciones);
$obtenido = [];

foreach ($ids as $idEstacion) {
$obtenido[] = self::consultaAperturaES($idEstacion, $idYear);
}

return [
'categorias' => $nombres,
'obtenido' => $obtenido,
'colores' => self::$coloresAnual,
];
}

private static function calcularPeorEstacion(array $obtenido): string
{
if (empty($obtenido)) return '';
$nombres = array_values(self::$estaciones);
$minVal = min($obtenido);
$peores = [];
foreach ($obtenido as $i => $val) {
if ($val === $minVal && isset($nombres[$i])) {
$peores[] = $nombres[$i];
}
}
return implode(', ', $peores) . ' - Aperturas: ' . $minVal;
}
}
