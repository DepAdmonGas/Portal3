<?php
namespace App\Controllers;
use App\Core\View;
use App\Models\Sasisopa\Sasisopa;
use App\Core\Breadcrumb;
use App\Models\Estacion;
use App\Models\Sasisopa\PoliticaListaComprobacion;
use App\Models\Sasisopa\ListaAsistencia;
use App\Models\Sasisopa\AnalisisRiesgo;
use App\Models\Sasisopa\SeguimientoObjetivosMetas;
use App\Models\Sasisopa\SeguimientoReporteIndicador;
use App\Models\Sasisopa\RepresentanteTecnico;
use App\Models\Sasisopa\ComunicacionIE;
use App\Models\Sasisopa\QuejasSugerencia;
use App\Models\Sasisopa\EquipoCritico;
use App\Services\ModuloService;
use App\Core\Auth;
use Dompdf\Dompdf;
use Dompdf\Options;


class SasisopaController extends BaseController{

    protected string $modulo = 'sasisopa';

    public function index(){

        $title = 'SASISOPA';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add($title, '');

        $usuario = Auth::user();
        // Buscar permisos de los modulos
        $permisos = ModuloService::getPermisos($usuario->id);

        $sasisopa = Sasisopa::all();

         $data = [
            'title' => $title,
            'elementos' => $sasisopa,
            'permisos' => $permisos,
            'modulo' => 'sasisopa',
            'links' =>[],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ],
            'help' => false

        ];
        
        View::render('sasisopa/index', $data,'sasisopa');

    }
    //----------------------------------------------------------------
    //------------ 1 Politica ---------------------------------------
    public function politica(){

        $title = '1. POLÍTICA';
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
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/sasisopa/politica.datatable.init.js',
                '/js/sasisopa/listaasistencia.datatable.init.js',
                '/js/sasisopa/politica.actions.init.js?v=1.6'
            ],
            'help' => true
        ];
        
        View::render('sasisopa/politica', $data,'sasisopa');

    }

    public function updatePolitica(){

        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);
        $politica = $data['politica'] ?? null;
        $mision = $data['mision'] ?? null;
        $vision = $data['vision'] ?? null;


        if (!ModuloService::validaPermiso($this->modulo, 'editar')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para editar'
            ]);
            exit;
        }

        $registro = Estacion::find($this->estacionId());

        if (!$registro) {
            echo json_encode([
                'success' => false,
                'message' => 'Registro no encontrado'
            ]);
            return;
        }

        $registro->politica = $politica;
        $registro->mision = $mision;
        $registro->vision = $vision;
        $registro->save();

        echo json_encode([
            'success' => true,
            'message' => 'Politica actualizada correctamente'
        ]);


    }


    public function descargarPolitica()
    {
        $registro = Estacion::find($this->estacionId());

        if (!$registro) {
            echo "No se encontró la información";
            return;
        }

    
        $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

        $permisocre   = $registro->permisocre;
        $razonsocial   = $registro->razonsocial;
        $direccioncompleta   = $registro->direccioncompleta;
        
        $politica = $registro->politica;
        $mision   = $registro->mision;
        $vision   = $registro->vision;

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset="UTF-8">
        <title>POLÍTICA</title>
        <link rel="stylesheet" href="'.$_ENV['APP_URL'].'/assets/css/pdf.css">
        </head>

        <body>

        <div class="text-center">
            <img src="'.$logo.'" width="150">
        </div>

        <div class="text-center mt-4">'.$permisocre.'</div>
        <div class="text-center">'.$razonsocial.'</div>
        <div class="text-center">'.$direccioncompleta.'</div>

        <h2 class="mt-2 text-primary">Política</h2>
        <p>'.htmlspecialchars($politica).'</p>

        <h2 class="text-primary">Misión</h2>
        <p>'.htmlspecialchars($mision).'</p>

        <h2 class="text-primary">Visión</h2>
        <p>'.htmlspecialchars($vision).'</p>
        
        </body>
        </html>';

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $dompdf->stream("politica.pdf", ["Attachment" => true]);
    }

    //----------------------------------------------------------------------------------
    //----------------------------------------------------------------------------------


    public function datatableListaComprobacion(){
        $data = PoliticaListaComprobacion::where('id_estacion', 1)
        ->groupBy('fecha')
        ->get();

         echo json_encode([
            "data" => $data
        ]);
        
        exit;
    }

    public function datatableListaAsistencia($elemento){
        $data = ListaAsistencia::where('punto_sasisopa', $elemento)
        ->where('id_estacion', 1)
        ->groupBy('fecha')
        ->get();

         echo json_encode([
            "data" => $data
        ]);
        
        exit;
    }

    

    //------------------------------------------------------------------------------------
    //------------------------------------------------------------------------------------

    //------------------------------------------------------------------------------------
    //------------ 2 Identificacion de peligros y aspectos ambientales -------------------

    public function identificacionPeligrosAspectosAmbientalesAnalisisRiesgoEvaluacionImpactosAmbientales(){

        $title = '2. IDENTIFICACIÓN DE PELIGROS Y ASPECTOS AMBIENTALES, ANÁLISIS DE RIESGO Y EVALUACIÓN DE IMPACTOS AMBIENTALES';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/assets/js/vendor.min.js',
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/sasisopa/analisisriesgo.datatable.init.js',
                '/assets/js/sasisopa/listaasistencia.datatable.init.js'
            ],
            'help' => true
        ];
        
        View::render('sasisopa/identificacion-peligros-aspectos-ambientales-analisis-riesgo-evaluacion-impactos-ambientales', $data,'sasisopa');

    }

    public function datatableListaAnalisisRiesgo(){
        $data = AnalisisRiesgo::where('id_estacion',2)
        ->groupBy('fecha')
        ->get();

         echo json_encode([
            "data" => $data
        ]);
        
        exit;
    }

    //------------------------------------------------------------------------------------
    //------------------------------------------------------------------------------------

    //------------------------------------------------------------------------------------
    //------------ 3 Requisitos legales -------------------    

    public function requisitosLegales(){

        $title = '3. REQUISITOS LEGALES';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/assets/js/vendor.min.js',
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/sasisopa/listaasistencia.datatable.init.js'
            ],
            'help' => true
        ];
        
        View::render('sasisopa/requisitos-legales', $data,'sasisopa');

    }


    public function requisitosLegalesConfiguracion(){

        $title = 'REQUISITOS LEGALES CONFIGURACIÓN';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('3. REQUISITOS LEGALES', '/sasisopa/requisitos-legales');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/requisitos-legales-configuracion', $data,'sasisopa');

    }

     //------------------------------------------------------------------------------------
    //------------------------------------------------------------------------------------

    //------------------------------------------------------------------------------------
    //---------------- 4 Objetivos, metas e indicadores ----------------------------------

    public function objetivosMetasIndicadores(){

        $title = '4. OBJETIVOS, METAS E INDICADORES';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/assets/js/vendor.min.js',
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/sasisopa/listaseguimientoindicadores.datatable.init.js',
                '/assets/js/sasisopa/listaseguimientoobjetivosmetas.datatable.init.js'
            ],
            'help' => true
        ];
        
        View::render('sasisopa/objetivos-metas-indicadores', $data,'sasisopa');

    }

    public function datatableListaSeguimientoIndicadores(){
        $data = SeguimientoReporteIndicador::where('id_estacion',2)
        ->groupBy('fecha')
        ->get();

         echo json_encode([
            "data" => $data
        ]);
        
        exit;
    }

    public function datatableListaSeguimientoObjetivosMetas(){
        $data = SeguimientoObjetivosMetas::where('id_estacion',2)
        ->groupBy('fecha')
        ->get();

         echo json_encode([
            "data" => $data
        ]);
        
        exit;
    }

    public function objetivosMetasIndicadoresCapacitacionPersonal(){

        $title = 'Capacitación del personal';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('4. OBJETIVOS, METAS E INDICADORES', '/sasisopa/objetivos-metas-indicadores');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/capacitacion-personal', $data,'sasisopa');

    }

     public function objetivosMetasIndicadoresExperienciaCliente(){

        $title = 'Experiencia del cliente';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('4. OBJETIVOS, METAS E INDICADORES', '/sasisopa/objetivos-metas-indicadores');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/experiencia-cliente', $data,'sasisopa');

    }

    public function objetivosMetasIndicadoresIndicadorVentas(){

        $title = 'Indicadores de Ventas';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('4. OBJETIVOS, METAS E INDICADORES', '/sasisopa/objetivos-metas-indicadores');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/indicador-ventas', $data,'sasisopa');

    }

    //------------------------------------------------------------------------------------
    //------------------------------------------------------------------------------------

    //------------------------------------------------------------------------------------
    //------------- 5 Funciones, responsabilidades y auditoria ---------------------------
    

    public function funcionesResponsabilidadesAutoridad(){

        $title = '5. FUNCIONES, RESPONSABILIDADES Y AUTORIDAD';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/assets/js/vendor.min.js',
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/sasisopa/listaasistencia.datatable.init.js',
                '/assets/js/sasisopa/listarepresentantetecnico.datatable.init.js'
            ],
            'help' => true
        ];
        
        View::render('sasisopa/funciones-responsabilidades-autoridad', $data,'sasisopa');

    }

    public function datatableListaRepresentanteTecnico(){
        $data = RepresentanteTecnico::where('id_estacion',5)
        ->groupBy('fecha')
        ->get();

         echo json_encode([
            "data" => $data
        ]);
        
        exit;
    }

    //------------------------------------------------------------------------------------
    //------------------------------------------------------------------------------------

    //------------------------------------------------------------------------------------
    //------------- 6 ---------------------------

    public function competenciaPersonalCapacitacionEntrenamiento(){

        $title = '6. COMPETENCIA DEL PERSONAL, CAPACITACIÓN Y ENTRENAMIENTO';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ],
            'help' => true
        ];
        
        View::render('sasisopa/competencia-personal-capacitacion-entrenamiento', $data,'sasisopa');

    }

    //------------------------------------------------------------------------------------
    //------------------------------------------------------------------------------------

    //------------------------------------------------------------------------------------
    //------------- 7 Comunicacion, participacion y consulta ---------------------------

    public function comunicacionParticipacionConsulta(){

        $title = '7. COMUNICACIÓN, PARTICIPACIÓN Y CONSULTA';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/assets/js/vendor.min.js',
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/sasisopa/listaregistrocomunicacion.datatable.init.js',
                '/assets/js/sasisopa/listaquejassugerencias.datatable.init.js'
            ],
            'help' => true
        ];
        
        View::render('sasisopa/comunicacion-participacion-consulta', $data,'sasisopa');

    }

    public function datatableListaRegistroComunicacion(){
        $data = ComunicacionIE::where('id_estacion',5)
        ->groupBy('fecha')
        ->get();

         echo json_encode([
            "data" => $data
        ]);
        
        exit;
    }

    public function datatableListaQuejasSugerencias(){
        $data = QuejasSugerencia::where('id_estacion',5)
        ->groupBy('fecha')
        ->get();

         echo json_encode([
            "data" => $data
        ]);
        
        exit;
    }

    

    //------------------------------------------------------------------------------------
    //------------------------------------------------------------------------------------

    

    public function controlDocumentosRegistros(){

        $title = '8. CONTROL DE DOCUMENTOS Y REGISTROS';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ],
            'help' => true
        ];
        
        View::render('sasisopa/control-documentos-registros', $data,'sasisopa');

    }

    public function mejoresPracticasEstandares(){

        $title = '9. MEJORES PRÁCTICAS Y ESTÁNDARES';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ],
            'help' => true
        ];
        
        View::render('sasisopa/mejores-practicas-estandares', $data,'sasisopa');

    }

    public function controlActividadesProcesos(){

        $title = '10. CONTROL DE ACTIVIDADES Y PROCESOS';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ],
            'help' => true
        ];
        
        View::render('sasisopa/control-actividades-procesos', $data,'sasisopa');

    }

    //------------------------------------------------------------------------------------
    //------------- 11 integridad mecanica y aseguramiento de la calidad ---------------------------
    public function integridadMecanicaAseguramiento(){

        $title = '11. INTEGRIDAD MECÁNICA Y ASEGURAMIENTO DE LA CALIDAD';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/assets/js/vendor.min.js',
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/sasisopa/listaequipocritico.datatable.init.js'
            ],
            'help' => true
        ];
        
        View::render('sasisopa/integridad-mecanica-aseguramiento', $data,'sasisopa');

    }

    public function datatableListaEquipoCritico(){
        $data = EquipoCritico::where('id_estacion',1)
        ->groupBy('fecha_instalacion')
        ->get();

         echo json_encode([
            "data" => $data
        ]);
        
        exit;
    }

    //------------------------------------------------------------------------------------
    //------------------------------------------------------------------------------------

    public function seguridadContratistas(){

        $title = '12. SEGURIDAD DE CONTRATISTAS';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ],
            'help' => true
        ];
        
        View::render('sasisopa/seguridad-contratistas', $data,'sasisopa');

    }

    public function preparacionEmergencias(){

        $title = '13. PREPARACIÓN Y RESPUESTA A EMERGENCIAS';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

        $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ],
            'help' => true
        ];
        
        View::render('sasisopa/preparacion-emergencias', $data,'sasisopa');

    }

    public function monitoreoVerificacionEvaluacion(){

        $title = '14. MONITOREO, VERIFICACIÓN Y EVALUACIÓN';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

    $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ],
            'help' => true
        ];
        
        View::render('sasisopa/monitoreo-verificacion-evaluacion', $data,'sasisopa');

    }

    public function auditorias(){

        $title = '15. AUDITORÍAS';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ],
            'help' => true
        ];
        
        View::render('sasisopa/auditorias', $data,'sasisopa');

    }

    public function investigacionIncidentesAccidentes(){

        $title = '16. INVESTIGACIÓN DE INCIDENTES Y ACCIDENTES';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ],
            'help' => true
        ];
        
        View::render('sasisopa/investigacion-incidentes-accidentes', $data,'sasisopa');

    }

        public function revisionResultados(){

        $title = '17. REVISIÓN DE RESULTADOS';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ],
            'help' => true
        ];
        
        View::render('sasisopa/revision-resultados', $data,'sasisopa');

    }

    public function informesDesempeno(){

        $title = '18. INFORMES DE DESEMPEÑO';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ],
            'help' => true
        ];
        
        View::render('sasisopa/informes-desempeno', $data,'sasisopa');

    }

    //--------------------------------------------------------------


        public function calendario(){

        $title = 'Calendario';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/calendario', $data,'sasisopa');

    }

     public function cursos(){

        $title = 'Cursos';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
             'links' =>[
                
            ],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ]
        ];
        
        View::render('sasisopa/cursos', $data,'sasisopa');

    }

    

}
