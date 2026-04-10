<?php
namespace App\Controllers;
use App\Core\View;
use App\Models\ActividadesTecnica;
use App\Models\VisitaEstacion;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Core\Auth;

class ProcedimientosController extends BaseController{

protected string $modulo = 'procedimientos';

//---------------------------------------------------//
//----------------- PAGINA PRINCIPAL -----------------//
//---------------------------------------------------//
public function index(){

$title = 'Procedimientos';
Breadcrumb::add('Home', '/home');
Breadcrumb::add($title, '');

// Buscar permisos de los modulos
$permisos = ModuloService::permisosSesion($this->modulo);

$data = [
'title' => $title,
'permisos' => $permisos,
'modulo' => $this->modulo,
'filtro_usuario' => $this->filtro_usuario,
'links' =>[
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
], 
'scripts' => [
'/assets/js/vendor.min.js',
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/procedimientos/procedimientos.datatable.init.js'
],
'help' => false
];

View::render('procedimientos/index', $data,'main');
}

public function datatableActividadesTec(){

$permisoDescargar = ModuloService::validaPermiso($this->modulo, 'descargar');
$permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');
$actividades = ActividadesTecnica::orderBy('id_actividades_tecnicas', 'asc')->get();

echo json_encode([
"data" => $actividades,
"permisos" => [
"descargar" => $permisoDescargar,
"editar"   => $permisoEditar
]
]);

exit;
}

public function datatableVisitaES(){

$permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');
$permisoDescargar = ModuloService::validaPermiso($this->modulo, 'descargar');

$visita = VisitaEstacion::orderBy('id_visita_estacion', 'asc')->get();

echo json_encode([
"data" => $visita,
"permisos" => [
"descargar" => $permisoDescargar,
"editar"   => $permisoEditar
]
]);

exit;
}

}