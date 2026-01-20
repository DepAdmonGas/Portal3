<?php
namespace App\Controllers;

class BaseController {

    protected function json($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

}



