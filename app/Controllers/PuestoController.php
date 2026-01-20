<?php
namespace App\Controllers;
use App\Core\View;
use App\Models\Puestos;
class PuestoController extends BaseController{

    public function index(){

     $data = [
            'title' => 'Puestos',
             'links' =>[
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/assets/js/vendor.min.js',
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/puestos/datatable.init.js'
            ]
        ];
        
        View::render('puestos/index', $data,'main');

    }

    public function datatablePuestos(){
         $puesto = Puestos::all();
         echo json_encode([
            "data" => $puesto
        ]);
    }


}