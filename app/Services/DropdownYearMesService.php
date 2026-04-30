<?php

namespace App\Services;

use App\Models\Operativo\CorteYear;
use App\Core\Auth;

class DropdownYearMesService
{

/* ============================================
VALIDAR YEAR Y MES
============================================ */
public static function validarYearMes($idYear, $idMes)
{
$yearActual = date('Y');
$mesActual  = date('n');

$year = $idYear ?: $yearActual;
$mes  = $idMes  ?: $mesActual;

if ($year > $yearActual) {
$year = $yearActual;
}

if ($year == $yearActual && $mes > $mesActual) {
$mes = $mesActual;
}

return [
'idYear' => (int) $year,
'idMes'  => (int) $mes
];
}

/* ============================================
OBTENER YEARS POR ESTACION
============================================ */
public static function getYears()
{
$usuario = Auth::user();

if (!$usuario || empty($usuario->id_gas)) {
return [];
}

return CorteYear::yearsByEstacion($usuario->id_gas)->toArray();
}

/* ============================================
DROPDOWN YEAR (CONSULTA)
============================================ */
public static function dropdownYear($idYear, $idMes)
{
$years = self::getYears();

if (empty($years)) {
return '<span class="text-muted">Sin años</span>';
}

$html = '
<a class="dropdown-toggle breadcrumb-item active" role="button" data-bs-toggle="dropdown" aria-expanded="false"> 
<i class="ti ti-calendar"></i> <span class="ms-1">' . $idYear . '</span> 
</a>

<ul class="dropdown-menu animated rubberBand">';

foreach ($years as $year) {

$html .= '
<li class="pointer">
<a class="dropdown-item" href="#" x-on:click.prevent="cambiarYearMes('. $year . ',' . $idMes . ')">
<i class="ti ti-calendar"></i> <span class="ms-1">'. $year .'</span>
</a>
</li>';
}

$html .= '</ul>';

return $html;
}

/* ============================================
DROPDOWN YEAR MANUAL (SIN CONSULTA)
Inicia desde el 2020 hasta el año actual
============================================ */
public static function dropdownYearManual($idYear, $idMes)
{

$yearActual = date("Y");
$yearInicio = 2020;

$html = '
<a class="dropdown-toggle breadcrumb-item active" role="button" data-bs-toggle="dropdown" aria-expanded="false">
<i class="ti ti-calendar"></i> <span class="ms-1">' . $idYear . '</span> 
</a>

<ul class="dropdown-menu animated rubberBand">';

for ($year = $yearActual; $year >= $yearInicio; $year--) {

$html .= '
<li class="pointer">

<a class="dropdown-item" href="#" x-on:click.prevent="cambiarYearMes('. $year . ',' . $idMes . ')">
<i class="ti ti-calendar"></i> <span class="ms-1">'. $year .'</span>
</a>

</li>';
}

$html .= '</ul>';

return $html;
}

/* ============================================
DROPDOWN MES
============================================ */
public static function dropdownMes($idYear, $idMes)
{

$yearActual = date("Y");
$mesActual  = date("n");

$html = '
<a class="dropdown-toggle breadcrumb-item active" role="button" data-bs-toggle="dropdown" aria-expanded="false">
<i class="ti ti-calendar-stats"></i> <span class="ms-1">' . nombremes($idMes) . '</span> 
</a>

<ul class="dropdown-menu">';

for ($i = 12; $i >= 1; $i--) {
$clase = "";

if (
$idYear >= $yearActual
&& $i > $mesActual
) {
$clase = "d-none";
}

$html .= '
<li class="' . $clase . '">
<a class="dropdown-item" href="#" x-on:click.prevent="cambiarYearMes(' . $idYear . ',' . $i . ')">
<i class="fa-solid fa-calendar-days"></i>
<i class="ti ti-calendar-stats"></i></i> <span class="ms-1">'. nombremes($i) .'</span>
</a>
</li>';
}

$html .= '</ul>';

return $html;
}

}