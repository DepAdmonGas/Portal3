<?php
namespace App\Controllers;
use App\Services\MenuService;
use App\Core\Auth;

class MenuController
{
    public function index()
    {
        $user = Auth::user();

        $menus = MenuService::getMenuByUsuario($user->id);

        echo json_encode($menus);
    }
}