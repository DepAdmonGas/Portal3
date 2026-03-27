<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Session;
use App\Models\Estacion;

class HomeController extends BaseController
{
public function index()
{   

// filtro de usuarios
$filtro_usuario = Session::get('usuario');

// Obtener estacion de la session
$filtro_estacion = null;
if ($filtro_usuario && isset($filtro_usuario['id_estacion'])) {
$filtro_estacion = Estacion::find($filtro_usuario['id_estacion']);
}

// Obtener listado de estaciones
$estaciones = [];
if (!empty($filtro_usuario['multiestacion'])) {
$estaciones = Estacion::where('numlista', '<=', 8)
->orderBy('numlista', 'ASC')
->get();
}

// Obtener módulos con permisos
$modulos = Session::get('permisos');

$data = [
'title'   => 'Home',
'modulos' => $modulos,
'filtro_usuario' => $filtro_usuario,
'filtro_estacion' => $filtro_estacion,
'estaciones'       => $estaciones,
'scripts' => []
];

View::render('home/index', $data, 'main');
}
}