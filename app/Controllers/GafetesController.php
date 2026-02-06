<?php
namespace App\Controllers;
use App\Core\View;
use App\Models\SolicitudGafetes;

class GafetesController{

     public function index(){

        $data = [
            'title' => 'Solicitud de Gafetes',
            'links' =>[
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/assets/js/vendor.min.js',
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/gafetes/gafetes.datatable.init.js',
            ]
        ];
        
        View::render('gafetes/index', $data,'main');
    }

    public function datatableGafetes(){
         $gafetes = SolicitudGafetes::groupBy('no_reporte')->get();

         echo json_encode([
            "data" => $gafetes
        ]);
        
        exit;
    }
}