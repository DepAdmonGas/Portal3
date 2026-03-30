<?php
namespace App\Controllers;
use App\Core\View;
use App\Models\SolicitudGafetes;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Core\Session;
use App\Models\Estacion;

class GafetesController extends BaseController{

public function index(){

$title = 'Solicitud de Gafetes';
$modulo = 'solicitud-gafetes';

Breadcrumb::add('Home', '/home');
Breadcrumb::add($title, '');
// Buscar permisos de los modulos
$permisos = ModuloService::permisosSesion($modulo);

$data = [
'title' => $title,
'permisos' => $permisos,
'modulo' => $modulo,
'links' =>[
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
],
'scripts' => [
'/assets/js/vendor.min.js',
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/gafetes/gafetes.datatable.init.js',
],
'help' => false
];

View::render('gafetes/index', $data,'main');
}

public function datatableGafetes()
{

$filtro_usuario = Session::get('usuario');
$idEstacion = $filtro_usuario['id_estacion'] ?? null;
$query = SolicitudGafetes::query();

if ($idEstacion && $idEstacion != 8) {
$estacion = Estacion::find($idEstacion);

if ($estacion) {
$query->where('id_estacion', $estacion->nombre);
}
}

$gafetes = $query
->groupBy('no_reporte')
->get();

echo json_encode([
"data" => $gafetes
]);

exit;
}

}