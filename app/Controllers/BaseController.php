<?php
namespace App\Controllers;
use App\Core\Session;

class BaseController {
    protected $filtro_usuario;

    public function __construct()
    {
        // filtro de usuarios
        $this->filtro_usuario = Session::get('usuario');
    }

    protected function userId()
    {
        return $this->filtro_usuario['id'] ?? null;
    }

    protected function estacionId()
    {
        return $this->filtro_usuario['id_estacion'] ?? null;
    }

    protected function isMultiEs()
    {
        return $this->filtro_usuario['multiestacion'] ?? false;
    }

}



