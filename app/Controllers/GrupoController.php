<?php
namespace App\Controllers;
use App\Core\View;
use App\Models\Grupo;
class GrupoController extends BaseController{
    
    public function index(){
       
        $data = [
            'title' => 'Grupos',
            'links' =>[
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/assets/js/vendor.min.js',
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/datatable/datatable.init.js'
            ]
        ];
        
        View::render('grupos/index', $data,'main');
       
    }

    public function datatableGrupos(){

        $grupo = Grupo::all();
         echo json_encode([
            "data" => $grupo
        ]);
        
    }

}