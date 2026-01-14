<?php
namespace App\Controllers;
use App\Core\Auth;

class BaseController {

    protected function json($data, $status = 200)
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }

}



