<?php
namespace App\Controllers;
use App\Core\View;
Use App\Models\Usuario;

class UsuarioController extends BaseController{

    public function index(){
        $data = [
            'title' => 'Usuarios',
             'links' =>[
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/assets/js/vendor.min.js',
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/usuarios/datatable.init.js'
            ]
        ];
        
        View::render('usuario/index', $data,'main');
    }

    public function datatableUsuarios(){
        
         $usuarios = Usuario::select('id','nombre','email','telefono','id_puesto','estatus')
         ->with('puesto:id,tipo_puesto')
         ->get()
         ->map(fn($u) => [
            'id' => $u->id,
            'nombre' => $u->nombre,
            'email'   => $u->email,
            'telefono'   => $u->telefono,
            'puesto'  => $u->puesto->tipo_puesto ?? 'Sin puesto',
            'estatus' => $u->estatus
         ]);

         echo json_encode([
            "data" => $usuarios
        ]);

    }



}