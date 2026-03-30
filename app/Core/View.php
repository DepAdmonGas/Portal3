<?php
namespace App\Core;

use App\Core\Auth;
use App\Controllers\BaseController;
use App\Models\Estacion;

class View
{
protected static function globals(): array
{

// Crear instancia para reutilizar lógica existente
$baseController = new class extends BaseController {

public function getFiltroUsuario() {
return $this->filtro_usuario;
}

public function getEstacionId() {
return $this->estacionId();
}

public function getIsMultiEs() {
return $this->isMultiEs();
}

};

// Filtro de usuarios
$filtro_usuario = $baseController->getFiltroUsuario();

// Obtener estacion de la session
$filtro_estacion = null;
if ($baseController->getEstacionId()) {
$filtro_estacion = Estacion::find(
$baseController->getEstacionId()
);
}

// Obtener listado de estaciones
$estaciones = [];
if ($baseController->getIsMultiEs()) {
$estaciones = Estacion::where('numlista', '<=', 8)
->orderBy('numlista', 'ASC')
->get();
}

// Obtener estación
$filtro_estacion = null;
if ($baseController->getEstacionId()) {
$filtro_estacion = Estacion::find(
$baseController->getEstacionId()
);
}

return [
'user' => Auth::user(),
'filtro_usuario'  => $filtro_usuario,
'filtro_estacion' => $filtro_estacion,
'estaciones'      => $estaciones
];

}

public static function render(string $view,array $data = [],string $layout = 'main') {

// Variables globales + datos de la vista
extract(array_merge(self::globals(), $data), EXTR_SKIP);

$viewPath   = __DIR__ . "/../Views/{$view}.php";
$layoutPath = __DIR__ . "/../Views/layouts/{$layout}.php";

ob_start();
require $viewPath;
$content = ob_get_clean();

require $layoutPath;
}
}
