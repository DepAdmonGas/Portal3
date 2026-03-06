<?php
namespace App\Controllers;

use App\Core\View;
use App\Core\Auth;
use App\Models\Modulo;

class HomeController extends BaseController
{
public function index()
{
$user = Auth::user();

if (!$user) {
header('Location: /login');
exit;
}

$idUsuario = $user->id;
$idPuesto  = $user->puesto->id;

// ✅ MÉTODO CORRECTO
$modulos = Modulo::modulosHomeUsuario($idUsuario, $idPuesto);

$data = [
'title'   => 'Portal3',
'scripts'=> [],
'modulos'=> $modulos
];

View::render('home/index', $data, 'main');
}
}
