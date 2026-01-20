<?php

namespace App\Core;

use App\Core\Auth;

class View
{
    protected static function globals(): array
    {
        return [
            'user' => Auth::user()
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
