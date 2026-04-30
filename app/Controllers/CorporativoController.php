<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\DropdownYearMesService;
use App\Models\Operativo\CorteYear;
use App\Models\Operativo\CorteMes;
use App\Models\Operativo\CorteDia;
use App\Services\ModuloDptoOperativoService;
use App\Core\Session;
use App\Core\Auth;

class CorporativoController extends BaseController{
protected string $modulo = 'corporativo';

public function corteDiarioIndex($idYear, $idMes)
{

$datosUsuario = Auth::user();

/* ========== VALIDAR EL AÑO Y MES ========== */
['idYear' => $idYear, 'idMes' => $idMes] = DropdownYearMesService::validarYearMes($idYear,$idMes);

$title = 'Corte Diario, '. nombremes($idMes) . ' ' . $idYear;
Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones','/departamento-operativo');
Breadcrumb::add('Corporativo','/departamento-operativo/corporativo');
Breadcrumb::add('<span class="breadcrumb-item active">Corte Diario</span>','');
/* ========== DROPDOWNS DEL MES Y DE LA FECHA ========== */
Breadcrumb::add(DropdownYearMesService::dropdownmES($idYear,$idMes),'');
Breadcrumb::add(DropdownYearMesService::dropdownYear($idYear,$idMes),'');

$data = [
'title'  => $title,
'idYear' => $idYear,
'idMes'  => $idMes,
'links' =>[
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
],
'scripts' => [
'/assets/js/vendor.min.js',
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/departamento-operativo/1-corporativo/corte.diario.datatable.init.js',
'/assets/js/departamento-operativo/1-corporativo/actions.corte.diario.init.js?v=1.0'
],
'help' => false
];

View::render('departamento-operativo/1-corporativo/corte-diario-index',$data,'departamento-operativo');
}

public function corteDiarioDatatable($idYear, $idMes)
{

$datosUsuario = Session::get('usuario');
$idEstacion = $datosUsuario['id_estacion'] ?? null;

// Obtener días con joins (igual que tu SQL)
$rows = CorteDia::from('op_corte_dia as d')
->join('op_corte_mes as m', 'd.id_mes', '=', 'm.id')
->join('op_corte_year as y', 'm.id_year', '=', 'y.id')
->where('y.id_estacion', $idEstacion)
->where('y.year', $idYear)
->where('m.mes', $idMes)
->orderBy('d.fecha', 'asc')
->select(
'd.id as idDia',
'd.fecha',
'd.ventas',
'd.tpv',
'd.monedero'
)
->get();

$data = [];
$hoy = date('Y-m-d');

foreach ($rows as $row) {
$idDia = $row->idDia;
$fecha = $row->fecha;

// lógica de habilitado/deshabilitado
$esPasado = $hoy >= formatDate($fecha);
$textClass = $esPasado ? '' : 'opacity-25';


if (empty(formatDate($row->fecha)) ||formatDate($row->fecha) === '0000-00-00' ||formatDate($row->fecha) === '-0001-11-30') {
$fechaFormateada = 'S/I';
} else {
$fechaFormateada = formatearFecha($row->fecha);
}

$data[] = [
"fecha" => "<span class='{$textClass}'>{$fechaFormateada}</span>",

"ventas" => "
<a href='" . ($esPasado ? "/departamento-operativo/ventas/{$idYear}/{$idMes}/{$idDia}" : "javascript:void(0)") . "'
class='d-flex justify-content-center align-items-center {$textClass} " . (!$esPasado ? "disabled opacity-25" : "") . "'>
<i class='ti ti-currency-dollar fs-8'></i>
</a>
",

"tpv" => "
<a href='" . ($esPasado ? "/departamento-operativo/cierrelote/{$idYear}/{$idMes}/{$idDia}" : "javascript:void(0)") . "'
class='d-flex justify-content-center align-items-center {$textClass} " . (!$esPasado ? "disabled opacity-25" : "") . "'>
<i class='ti ti-receipt fs-8'></i>
</a>
",

"impuestos" => "
<a href='" . ($esPasado ? "/departamento-operativo/impuestos/{$idYear}/{$idMes}/{$idDia}" : "javascript:void(0)") . "'
class='d-flex justify-content-center align-items-center {$textClass} " . (!$esPasado ? "disabled opacity-25" : "") . "'>
<i class='ti ti-receipt-tax fs-8'></i>
</a>
",

"monedero" => "
<a href='" . ($esPasado ? "/departamento-operativo/monedero/{$idYear}/{$idMes}/{$idDia}" : "javascript:void(0)") . "'
class='d-flex justify-content-center align-items-center {$textClass} " . (!$esPasado ? "disabled opacity-25" : "") . "'>
<i class='ti ti-wallet fs-8'></i>
</a>
",

"clientes" => "
<a href='" . ($esPasado ? "/departamento-operativo/clientes/{$idYear}/{$idMes}/{$idDia}" : "javascript:void(0)") . "'
class='d-flex justify-content-center align-items-center {$textClass} " . (!$esPasado ? "disabled opacity-25" : "") . "'>
<i class='ti ti-users fs-8'></i>
</a>
",

"editar" => "<i class='ti ti-edit fs-8 {$textClass} " . (!$esPasado ? "disabled opacity-25" : "") . "'></i>"
];
}

echo json_encode([
"data" => $data,

]);

}









}