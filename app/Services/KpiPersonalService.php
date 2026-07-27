<?php

namespace App\Services;

use App\Models\Operativo\RhPersonal;
use App\Models\Operativo\RhPersonalBaja;

class KpiPersonalService
{
private static array $colores_mensual = [
'#ca6ed0', '#dc3912', '#ff9900', '#109618',
'#990099', '#0099c6', '#dd4477', '#66aa00',
'#b82e2e', '#316395', '#994499', '#22aa99', '#FFD300',
];

private static array $colores_estaciones = [
'#3366cc', '#dc3912', '#ff9900', '#109618',
'#990099', '#0099c6', '#dd4477', '#66aa00',
'#0080aa', '#6533cc', '#009990', '#f87750',
'#967777', '#00e4ff',
];

public static function getOpciones(): array
{
return [
['id' => 1, 'titulo' => 'Altas del Personal', 'icono' => 'ti ti-user-plus'],
['id' => 2, 'titulo' => 'Bajas del Personal', 'icono' => 'ti ti-user-minus'],
];
}

public static function getData(?int $idEstacion, int $idYear, int $tipo): array
{
$estacionNombre = $idEstacion ? ControlDocumentosPersonalService::resolveNombreEstacion($idEstacion) : 'Todas las estaciones';
$nombreTipo = $tipo === 1 ? 'Altas del Personal' : 'Bajas del Personal';

$mensual = $tipo === 1
? self::contarAltasMensual($idEstacion, $idYear)
: self::contarBajasMensual($idEstacion, $idYear);

$anual = $tipo === 1
? self::contarAltasAnual($idYear)
: self::contarBajasAnual($idYear);

$mejorEstacion = self::calcularMejorEstacion($anual['obtenido'], $anual['categorias']);

return [
'estacion_nombre'  => $estacionNombre,
'estacion_id'      => $idEstacion,
'year'             => $idYear,
'tipo'             => $tipo,
'nombre_tipo'      => $nombreTipo,
'colores_mensual'  => self::$colores_mensual,
'colores_estaciones' => self::$colores_estaciones,
'mensual'          => $mensual,
'anual'            => $anual,
'mejor_estacion'   => $mejorEstacion,
];
}

private static function contarAltasMensual(?int $idEstacion, int $idYear): array
{
$categorias = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$obtenido = array_fill(0, 12, 0);

$query = RhPersonal::whereRaw('YEAR(fecha_ingreso) = ?', [$idYear])
->whereRaw('MONTH(fecha_ingreso) BETWEEN 1 AND 12');

if ($idEstacion !== null) {
$query->where('id_estacion', $idEstacion);
}

$rows = $query->selectRaw('MONTH(fecha_ingreso) as mes, COUNT(*) as total')
->groupBy('mes')
->get();

foreach ($rows as $r) {
$obtenido[$r->mes - 1] = (int) $r->total;
}

$totalAnual = array_sum($obtenido);
$categorias[] = 'Anual';
$obtenido[] = $totalAnual;

return ['categorias' => $categorias, 'obtenido' => $obtenido];
}

private static function contarBajasMensual(?int $idEstacion, int $idYear): array
{
$categorias = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$obtenido = array_fill(0, 12, 0);

$query = RhPersonalBaja::join('op_rh_personal', 'op_rh_personal.id', '=', 'op_rh_personal_baja.id_personal')
->whereRaw('YEAR(op_rh_personal_baja.fecha_baja) = ?', [$idYear])
->whereRaw('MONTH(op_rh_personal_baja.fecha_baja) BETWEEN 1 AND 12');

if ($idEstacion !== null) {
$query->where('op_rh_personal.id_estacion', $idEstacion);
}

$rows = $query->selectRaw('MONTH(op_rh_personal_baja.fecha_baja) as mes, COUNT(*) as total')
->groupBy('mes')
->get();

foreach ($rows as $r) {
$obtenido[$r->mes - 1] = (int) $r->total;
}

$totalAnual = array_sum($obtenido);
$categorias[] = 'Anual';
$obtenido[] = $totalAnual;

return ['categorias' => $categorias, 'obtenido' => $obtenido];
}

private static function contarAltasAnual(int $idYear): array
{
$estaciones = self::getEstacionesDisponibles();
$categorias = [];
$obtenido = [];

foreach ($estaciones as $id => $nombre) {
$categorias[] = $nombre;
$count = RhPersonal::where('id_estacion', $id)
->whereRaw('YEAR(fecha_ingreso) = ?', [$idYear])
->count();
$obtenido[] = $count;
}

return ['categorias' => $categorias, 'obtenido' => $obtenido];
}

private static function contarBajasAnual(int $idYear): array
{
$estaciones = self::getEstacionesDisponibles();
$categorias = [];
$obtenido = [];

foreach ($estaciones as $id => $nombre) {
$categorias[] = $nombre;
$count = RhPersonalBaja::join('op_rh_personal', 'op_rh_personal.id', '=', 'op_rh_personal_baja.id_personal')
->where('op_rh_personal.id_estacion', $id)
->whereRaw('YEAR(op_rh_personal_baja.fecha_baja) = ?', [$idYear])
->count();
$obtenido[] = $count;
}

return ['categorias' => $categorias, 'obtenido' => $obtenido];
}

public static function getEstacionesDisponibles(): array
{
$allowedIds = ControlDocumentosPersonalService::getAllowedEstacionIds();
$estaciones = [];

foreach ($allowedIds as $id) {
$nombre = ControlDocumentosPersonalService::resolveNombreEstacion($id);
if ($nombre) {
$estaciones[$id] = $nombre;
}
}

return $estaciones;
}

private static function calcularMejorEstacion(array $obtenido, array $categorias): string
{
if (empty($obtenido)) return '';
$maxVal = max($obtenido);
if ($maxVal === 0) return 'Sin registros';
$mejores = [];
foreach ($obtenido as $i => $val) {
if ($val === $maxVal && isset($categorias[$i])) {
$mejores[] = $categorias[$i];
}
}
return implode(', ', $mejores) . ' — ' . $maxVal . ' registros';
}
}
