<?php
namespace App\Controllers;
use App\Core\Auth;
use App\Core\Session;
use App\Models\Estacion;
use App\Services\ModuleStationService;
use App\Services\MultiestacionService;

class SwitchEstacionController extends BaseController
{

public function switchSessionEstacion()
{
header('Content-Type: application/json');

try {

$input = json_decode(
file_get_contents('php://input'),
true
);

$idEstacion = $input['id_estacion'] ?? null;

if (!$idEstacion) {

echo json_encode([
'ok' => false,
'type' => 'error',
'message' => 'Estación inválida'
]);

return;
}

$user = Auth::user();

if (!$user) {

echo json_encode([
'ok' => false,
'type' => 'error',
'message' => 'Sesión no válida'
]);

return;
}

if (!MultiestacionService::isEnabled($user)) {

echo json_encode([
'ok' => false,
'type' => 'error',
'message' => 'No autorizado'
]);

return;
}

$estacion = Estacion::find($idEstacion);

if (!$estacion) {

echo json_encode([
'ok' => false,
'type' => 'error',
'message' => 'La estación no existe'
]);

return;
}

$usuario = Session::get('usuario');
$usuario['id_estacion'] = (int) $idEstacion;
$usuario['razonsocial'] = $estacion->razonsocial;
$usuario['nombre_estacion'] = $estacion->nombre;

Session::set(
'usuario',
$usuario
);

ModuleStationService::resetAllContexts();

echo json_encode([
'ok' => true,
'type' => 'success',
'message' => 'Estación cambiada'
]);

} catch (\Throwable $e) {

echo json_encode([
'ok' => false,
'type' => 'error',
'message' => $e->getMessage()
]);

}

}

}