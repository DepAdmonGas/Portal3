<?php
namespace App\Controllers;
use App\Core\Session;

class BaseController {

    public function __construct()
    {
        //  Validar sesión
        if (!Session::isLogged()) {
            header('Location: /');
            exit;
        }
    }

}



