<?php
namespace App\Controllers;
use App\Core\View;
use App\Models\SolicitudTarjetas;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Core\Session;
use App\Models\Estacion;

class TarjetasController{
 
public function index(){

$title = 'Solicitud de Tarjetas';
$modulo = 'solicitud-tarjetas';

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
'/assets/js/tarjetas/tarjetas.datatable.init.js',
]
];

View::render('tarjetas/index', $data, 'main');
}

public function datatableTarjetas(){
$filtro_usuario = Session::get('usuario');
$idEstacion = $filtro_usuario['id_estacion'] ?? null;
$query = SolicitudTarjetas::query();

if ($idEstacion && $idEstacion != 8) {
$estacion = Estacion::find($idEstacion);

if ($estacion) {
$query->where('id_estacion', $estacion->nombre);
}
}

$tarjetas = $query
->groupBy('no_solicitud')
->get();

echo json_encode([
"data" => $tarjetas
]);

exit;
}


}