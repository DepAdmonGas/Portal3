<?php

namespace App\Controllers;

use App\Core\Session;
use App\Core\View;
use App\Services\ModuleStationService;

class BaseController
{
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

    // Verifica el acceso al módulo y muestra un bloqueo si no está disponible.
    protected function guardModuleAccess(string $moduleKey, string $title = '', string $layout = 'main'): bool
    {
        if (ModuleStationService::isAvailable($moduleKey)) {
            return true;
        }

        View::render('partials/_module-blocked', [
            'title' => $title ?: ucfirst(str_replace('-', ' ', $moduleKey)),
            'moduleStationKey' => $moduleKey,
        ], $layout);

        return false;
    }
}
