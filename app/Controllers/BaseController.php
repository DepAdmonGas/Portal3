<?php
namespace App\Controllers;
use App\Core\Auth;

class BaseController {
    protected function view($viewName, $data = ['admin']) {
        // Extraer los datos para que estén disponibles en la vista

        $data['user'] = Auth::user();

        extract($data);
        // Cargar la vista
        require __DIR__ . '/../Views/' . $viewName;
    }

    protected function json($data, $status = 200)
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }

}



