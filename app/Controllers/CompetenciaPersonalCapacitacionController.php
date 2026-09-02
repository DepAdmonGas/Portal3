<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Services\ModuleStationService;


class CompetenciaPersonalCapacitacionController extends BaseController
{
    protected string $modulo = 'sasisopa';

    private function estacionModulo(): ?int
    {
        return ModuleStationService::getContext('sasisopa')['id_estacion'] ?? null;
    }

    public function index(){

        $title = '6. COMPETENCIA DEL PERSONAL, CAPACITACIÓN Y ENTRENAMIENTO';
         // Buscar permisos de los modulos
        $permisos = ModuloService::permisosSesion($this->modulo);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

        $idEstacion = $this->estacionModulo();

         $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'estacionId' => $idEstacion,
            'moduleStationKey' => 'sasisopa',
            'filtro_usuario' => $this->filtro_usuario,
             'links' =>[
                
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/core/module-station-selector.js?v=' . time(),
            ],
            'help' => true
        ];
        
        View::render('competenciapersonalcapacitacionentrenamiento/index', $data,'sasisopa');

    }

    public function perfilesPuestoTrabajo(){
        $title = 'Perfiles de puesto de trabajo';
        // Buscar permisos de los modulos
        $permisos = ModuloService::permisosSesion($this->modulo);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('6. COMPETENCIA DEL PERSONAL, CAPACITACIÓN Y ENTRENAMIENTO', '/sasisopa/competencia-personal-capacitacion-entrenamiento');
        Breadcrumb::add($title, '');

        $idEstacion = $this->estacionModulo();

         $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'estacionId' => $idEstacion,
            'moduleStationKey' => 'sasisopa', 
            'ocultarSelectorEstacion'=> true,
            'filtro_usuario' => $this->filtro_usuario,
             'links' =>[
                
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/core/module-station-selector.js?v=' . time(),
            ],
            'help' => false
        ];
        
        View::render('competenciapersonalcapacitacionentrenamiento/perfiles-puesto-trabajo', $data,'sasisopa');

    }

    

}