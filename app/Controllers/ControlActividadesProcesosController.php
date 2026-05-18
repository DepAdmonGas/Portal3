<?php

namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Sasisopa\ProgramaAnualMantenimiento;
use App\Models\Sasisopa\ProgramaAnualMantenimientoDetalle;
use App\Services\ProgramaMantenimientoService;

class ControlActividadesProcesosController extends BaseController{

    protected string $modulo = 'sasisopa';

    public function index(){

     $title = '10. CONTROL DE ACTIVIDADES Y PROCESOS';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $permisos = ModuloService::permisosSesion($this->modulo);

         $data = [
           'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
             'links' =>[
                
            ],
            'scripts' => [
                '/js/vendor.min.js'
            ],
            'help' => true
        ];
        
        View::render('controlactividadproceso/index', $data,'sasisopa');
        
    }

    public function programaAnual(){

     $title = 'Programa Anual de Mantenimiento';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('10. CONTROL DE ACTIVIDADES Y PROCESOS', '/sasisopa/control-actividades-procesos');
        Breadcrumb::add($title, '');

         $permisos = ModuloService::permisosSesion($this->modulo);

         $programas = ProgramaAnualMantenimiento::where(
            'id_estacion',
            $this->estacionId()
        )
        ->select([
            'id',
            'year',
            'estado'
        ])
        ->orderByDesc('year')
        ->get();

         $data = [
           'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'programas'=> $programas,
             'links' =>[
              
            ],
            'scripts' => [
                '/js/vendor.min.js'
            ],
            'help' => true
        ];
        
        View::render('controlactividadproceso/programa-anual-mantenimiento', $data,'sasisopa');

    }

    public function detalleProgramaAnual(int $id){

    $title = 'Detalle Programa Anual de Mantenimiento';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('10. CONTROL DE ACTIVIDADES Y PROCESOS', '/sasisopa/control-actividades-procesos');
        Breadcrumb::add('Programa Anual de Mantenimiento', '/sasisopa/control-actividades-procesos/programa-anual-mantenimiento');
        Breadcrumb::add($title, '');

         $permisos = ModuloService::permisosSesion($this->modulo);

         $data = [
           'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'idPrograma' => (int)$id,
             'links' =>[
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/controlactividadproceso/programaanualmantenimiento.datatable.init.js?v=1.0',
            ],
            'help' => false
        ];
        
        View::render('controlactividadproceso/detalle-programa-anual-mantenimiento', $data,'sasisopa');

    }

    


    public function datatableProgramaMantenimiento(int $id)
    {

          $programa = ProgramaAnualMantenimiento::find(
            $id
        );

        if (!$programa) {

            echo json_encode([
                'data' => []
            ]);

            return;
        }

        $year = $programa->year;

        $detalles = ProgramaAnualMantenimientoDetalle::with(
            'mantenimiento'
        )
        ->where(
            'id_programa_fecha',
            $id
        )
        ->get();

        $data = [];

        foreach ($detalles as $item) {

            $row = [

                'id' => $item->id,
                'id_mantenimiento' => $item->id_mantenimiento,
                'equipo' => $item->mantenimiento?->detalle,
                'periodicidad' => $item->mantenimiento?->periodicidad,
            ];

            $meses = [

                'enero',
                'febrero',
                'marzo',
                'abril',
                'mayo',
                'junio',
                'julio',
                'agosto',
                'septiembre',
                'octubre',
                'noviembre',
                'diciembre'
            ];

            foreach ($meses as $index => $mes) {

                $fecha = $item->$mes;

                if (
                    $item->mantenimiento?->periodicidad
                    == 'Semanal'
                ) {

                    $fecha =
                        ProgramaMantenimientoService::buscaFechaSemanal(
                            $this->estacionId(),
                            $item->id_mantenimiento,
                            $year,
                            $index + 1
                        );
                }

                $row[$mes] = [

                    'fecha' => $fecha,

                    'texto' =>
                        ProgramaMantenimientoService::txtFecha($fecha),

                    'background' =>
                        ProgramaMantenimientoService::colorTD($fecha),

                    'textColor' =>
                        ProgramaMantenimientoService::txtColor($fecha)
                ];
            }

            $data[] = $row;
        }

        echo json_encode([
            'data' => $data,
            'permisos' => [
                'editar' =>  ModuloService::validaPermiso($this->modulo, 'editar'),
                'eliminar' =>  ModuloService::validaPermiso($this->modulo, 'eliminar')
            ]
        ]);
    }

}