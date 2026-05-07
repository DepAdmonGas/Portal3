<?php
namespace App\Core;

use App\Core\Auth;
use App\Controllers\BaseController;
use App\Models\Estacion;
use App\Core\Session;

class View
{
protected static function globals(): array
{

    $filtro_usuario = Session::get('usuario') ?? null;

    // Obtener listado de estaciones
    $estaciones = [];

    if (($filtro_usuario['multiestacion'] ?? false)) {
    $estaciones = Estacion::where('numlista', '<=', 8)
        ->orderBy('numlista', 'ASC')
        ->get();
}

        return [
        'user' => Auth::user(),
        'filtro_usuario'  => $filtro_usuario,
        'estaciones'      => $estaciones
        ];

    }

    public static function render(string $view,array $data = [],string $layout = 'main'): void {

        // Variables globales + datos de la vista

        $viewData = array_merge(self::globals(), $data);
        extract($viewData, EXTR_SKIP);

        $viewPath   = __DIR__ . "/../Views/{$view}.php";
        $layoutPath = __DIR__ . "/../Views/layouts/{$layout}.php";

        ob_start();

        require $viewPath;

        $content = ob_get_clean();

        require $layoutPath;

    }
}
