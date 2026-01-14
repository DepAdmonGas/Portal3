<?php
namespace App\Controllers;
use App\Core\View;

class UsuarioController extends BaseController{

    public function index(){
        $data = [
            'title' => 'Usuarios',
            'scripts' => []
        ];
        
        View::render('usuario/index', $data,'main');
    }





}