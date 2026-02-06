<?php
namespace App\Controllers;
use App\Core\View;
use App\Models\Operativo\BitacoraAditivo;
class AditivoController extends BaseController{

    public function index(){

        $data = [
            'title' => 'Bitácora de aditivo',
            'links' =>[
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/assets/js/vendor.min.js',
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/bitacora/aditivo.datatable.init.js',
                '/assets/js/bitacora/aditivo.actions.init.js'
            ]
        ];
        
        View::render('aditivo/index', $data,'main');
    }

    public function datatableAditivo(){
         $aditivo = BitacoraAditivo::all();

         echo json_encode([
            "data" => $aditivo
        ]);
        
        exit;
    }

    public function reporte(){
        $data = [
            'title' => 'Reporte aditivo',
            'links' =>[
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/assets/js/vendor.min.js',
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js'
            ]
        ];
        
        View::render('aditivo/reporte', $data,'main');
    }

    public function inventario(){
        $data = [
            'title' => 'Inventario aditivo',
            'links' =>[
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/assets/js/vendor.min.js',
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js'
            ]
        ];
        
        View::render('aditivo/inventario', $data,'main');
    }

}