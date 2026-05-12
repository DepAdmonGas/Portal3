<?php
namespace App\Controllers;
use App\Core\View;
use App\Models\Puestos;
use App\Core\Breadcrumb;

class PuestoController extends BaseController{

    public function index(){

        $title = 'Puestos';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add($title, '');

     $data = [
            'title' => $title,
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

    public function getPuestos()
    {
        echo json_encode(

            Puestos::where('estatus', 0)
                ->select('id', 'tipo_puesto')
                ->orderBy('tipo_puesto')
                ->get()

        );
    }


}