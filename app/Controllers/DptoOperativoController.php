<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;

use App\Services\ModuloService;
use App\Core\Auth;
 
class DptoOperativoController extends BaseController{
protected string $modulo = 'departamento-operativo';

public function index(){

$title = 'Dirección de Operaciones';

Breadcrumb::add('Home', '/home');
Breadcrumb::add($title, '');

$usuario = Auth::user();
// Buscar permisos de los modulos
$permisos = ModuloService::getPermisos($usuario->id);

$data = [
'title' => $title,
'permisos' => $permisos,
'links' =>[],
'scripts' => [
'/assets/js/vendor.min.js'
],
'help' => false

];

View::render('departamento-operativo/index', $data,'departamento-operativo');

}



}

