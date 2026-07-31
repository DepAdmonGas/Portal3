<?php
namespace App\Core;

use App\Core\Auth;
use App\Models\Estacion;
use App\Core\Session;
use App\Services\CalendarioService;
use App\Services\MultiestacionService;
use App\Services\ModuleStationService;

class View
{
protected static function globals(): array
{

$filtro_usuario = Session::get('usuario') ?? null;

// Obtener listado de estaciones
$estaciones = [];

$allowedStations = MultiestacionService::getAllowedStations(Auth::user());
if ($allowedStations !== null) {
$estaciones = Estacion::whereIn('id', $allowedStations)
->orderBy('numlista', 'ASC')
->get();
} elseif (($filtro_usuario['multiestacion'] ?? false)) {
$estaciones = Estacion::where('numlista', '<=', 8)
->orderBy('numlista', 'ASC')
->get();
}

return [
'title'           => 'Portal3',
'user'            => Auth::user(),
'filtro_usuario'  => $filtro_usuario,
'estaciones'      => $estaciones,
'pendientes'     => CalendarioService::pendientes()
];

}

public static function render(string $view,array $data = [],string $layout = 'main'): void {

// Variables globales + datos de la vista

$viewData = array_merge(self::globals(), $data);
extract($viewData, EXTR_SKIP);

$viewPath   = __DIR__ . "/../Views/{$view}.php";
$layoutPath = __DIR__ . "/../Views/layouts/{$layout}.php";

$moduleStationSelector = '';

ob_start();

// Obtiene la clave del módulo enviada por el controlador (si existe).
$moduleKey = $viewData['moduleStationKey'] ?? null;

// Si la vista pertenece a un módulo con selector de estación/departamento.
if ($moduleKey) {

// Obtiene la información de pendientes del módulo.
$pendientesData = $viewData['pendientesData'] ?? [];

// Indica si el selector de estación debe ocultarse.
$ocultarSelector = !empty($viewData['ocultarSelectorEstacion']);

// Genera el HTML del selector y valida si el módulo está disponible.
$html = ModuleStationService::render($moduleKey, $pendientesData, !$ocultarSelector);

// Si el módulo está bloqueado (Para id_gas = 8), solo muestra el mensaje de bloqueo.
if (ModuleStationService::$isBlocked) {
echo $html;
} else {

// Guarda el selector para mostrarlo en el layout y carga la vista.
$moduleStationSelector = $html;
require $viewPath;
}
} else {

// Si la vista no utiliza el sistema de multiestacion, se carga normalmente.
require $viewPath;
}

$content = ob_get_clean();

require $layoutPath;

}
}
