<?php
namespace App\Controllers;
use App\Core\View;
use App\Models\SolicitudTarjetas;

class TarjetasController{

     public function index(){

        $data = [
            'title' => 'Solicitud de Tarjetas',
            'links' =>[
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/assets/js/vendor.min.js',
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/tarjetas/tarjetas.datatable.init.js',
            ]
        ];
        
        View::render('tarjetas/index', $data,'main');
    }

    public function datatableTarjetas(){
         $tarjetas = SolicitudTarjetas::groupBy('no_solicitud')->get();

         echo json_encode([
            "data" => $tarjetas
        ]);
        
        exit;
    }
}