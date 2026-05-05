<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;


class CompetenciaPersonalCapacitacionController extends BaseController
{
    protected string $modulo = 'sasisopa';
    public function index(){

        $title = '6. COMPETENCIA DEL PERSONAL, CAPACITACIÓN Y ENTRENAMIENTO';
         // Buscar permisos de los modulos
        $permisos = ModuloService::permisosSesion($this->modulo);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

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
            'help' => false
        ];
        
        View::render('competenciapersonalcapacitacionentrenamiento/perfiles-puesto-trabajo', $data,'sasisopa');

    }

    

}