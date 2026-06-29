<?php
namespace App\Controllers;
use App\Core\View;
use App\Models\Sasisopa\Sasisopa;
use App\Core\Breadcrumb;
use App\Models\Estacion;
use App\Models\Sasisopa\AnalisisRiesgo;
use App\Models\Sasisopa\AnalisisRiesgoAnexo;
use App\Models\Sasisopa\RepresentanteTecnico;
use App\Services\ModuloService;
use Dompdf\Dompdf;
use Dompdf\Options;

use Illuminate\Database\Capsule\Manager as Capsule;

class SasisopaController extends BaseController{

    protected string $modulo = 'sasisopa';

    public function index(){

        $title = 'SASISOPA';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add($title, '');

        // Buscar permisos de los modulos
        $permisos = ModuloService::getPermisos($this->userId());

        $sasisopa = Sasisopa::all();

         $data = [
            'title' => $title,
            'elementos' => $sasisopa,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'links' =>[],
            'scripts' => [
                '/assets/js/vendor.min.js'
            ],
            'help' => false

        ];
        
        View::render('sasisopa/index', $data,'sasisopa');

    }
  
    //------------------------------------------------------------------------------------
    //------------------------------------------------------------------------------------

    //------------------------------------------------------------------------------------
    //------------ 2 Identificacion de peligros y aspectos ambientales -------------------

    public function identificacionPeligrosAspectosAmbientalesAnalisisRiesgoEvaluacionImpactosAmbientales(){

        $title = '2. IDENTIFICACIÓN DE PELIGROS Y ASPECTOS AMBIENTALES, ANÁLISIS DE RIESGO Y EVALUACIÓN DE IMPACTOS AMBIENTALES';
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
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/libs/select2/dist/css/select2.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/libs/select2/dist/js/select2.full.min.js',
                '/libs/select2/dist/js/select2.min.js',
                '/js/sasisopa/analisisriesgo.datatable.init.js?v=1.0',
                '/js/asistencia/listaasistencia.datatable.init.js?v=1.0',
                '/js/asistencia/listaasistencia.actions.init.js?v=1.0',
                '/js/sasisopa/analisisriesgo.actions.init.js?v=1.0'
            ],
            'help' => true
        ];
        
        View::render('sasisopa/identificacion-peligros-aspectos-ambientales-analisis-riesgo-evaluacion-impactos-ambientales', $data,'sasisopa');

    }

    public function datatableListaAnalisisRiesgo(){

        $permisoDescargar   = ModuloService::validaPermiso($this->modulo, 'descargar');

        $data = AnalisisRiesgo::where('id_estacion',$this->estacionId())
        ->orderBy('fecha','desc')
        ->get();

         echo json_encode([
            "data" => $data,
            "permisos" => [
                "descargar" => $permisoDescargar
            ]
        ]);
        
        exit;
    }

    public function pdfAspectosAmbientales(){

        $estacion = Estacion::find($this->estacionId());
        $apoderado = htmlspecialchars($estacion->apoderado_legal ?? '');

        if (!ModuloService::validaPermiso($this->modulo, 'descargar')) {
            header("Location: /404");
            exit;
        }

        $html = '';

        $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

        $html .= '
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset="UTF-8">
        <title>Identificación y evaluación de Aspectos e Impactos Ambientales.</title>
        <link rel="stylesheet" href="'.$_ENV['APP_URL'].'/assets/css/pdf.css">
        </head>
        <body>

        <table class="table table-bordered" style="font-size: .9em;">
        <tbody>
        <tr>

        <td class="align-middle text-center">
        <img src="'.$logo.'" style="width: 150px;">
        </td>
        <td colspan="2" class="align-middle text-center">
        <b>Identificación y evaluación de Aspectos e Impactos Ambientales.</b>
        </td>
        <td class="align-middle text-center">
        <b>Fo.ADMONGAS.002</b>
        </td>

        </tr>
        //------------------------------------------------------------------
        <tr>
        <td class="align-middle text-center">
        Realizado por:<br> Nelly Estrada Garcia
        </td>
        <td class="align-middle text-center">
        Revisado por:<br> Eduardo Galicia Flores
        </td>
        <td class="align-middle text-center">
        Autorizado por:<br> '.$apoderado.'
        </td>
        <td class="align-middle text-center">
        Fecha de aprobacion:<br>  01-oct-18
        </td>
        </tr>
                    

        </tbody>
        </table>

    <table class="table table-sm table-bordered text-center" style="font-size: .7em;">
    <tbody>
      <thead>
        <tr>
            <th colspan="10" class="align-middle text-center table-success">ETAPA: OPERACIÓN Y MANTENIMIENTO</th>
        </tr>
        <tr>
            <th class="align-middle">Id</th>
            <th class="align-middle">Proceso o Actividad</th>
            <th class="align-middle">Tipo</th>
            <th class="align-middle">Entradas</th>
            <th class="align-middle">Salidas</th>
            <th class="align-middle">Impacto ambiental</th>
            <th class="align-middle">Naturaleza</th>
            <th class="align-middle">Importancia</th>
            <th class="align-middle">Magnitud</th>
            <th class="align-middle">Resultado</th>
        </tr>
        </thead>
  
        <tr>
            <td class="align-middle">1</td>
            <td class="align-middle">Despacho de combustible al público</td>
            <td class="align-middle">Rutinaria</td>
            <td class="align-middle">Combustible</td>
            <td class="align-middle">Emisiones fugitivas de VOC´s</td>
            <td class="align-middle">Aire</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">2</td>
            <td class="align-middle">9</td>
            <td class="align-middle">-18</td>
        </tr>
        <tr>
            <td class="align-middle">2</td>
            <td class="align-middle">Despacho de combustible al público</td>
            <td class="align-middle">Rutinaria</td>
            <td class="align-middle">Energía</td>
            <td class="align-middle">Ruido</td>
            <td class="align-middle">Aire</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">1</td>
            <td class="align-middle">2</td>
            <td class="align-middle">-2</td>
        </tr>
        <tr>
            <td class="align-middle">3</td>
            <td class="align-middle">Despacho de combustible al público</td>
            <td class="align-middle">Rutinaria</td>
            <td class="align-middle">Energía</td>
            <td class="align-middle">Vibraciones</td>
            <td class="align-middle">Salud</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">2</td>
            <td class="align-middle">1</td>
            <td class="align-middle">-2</td>
        </tr>
        <tr>
            <td class="align-middle">4</td>
            <td class="align-middle">Despacho de combustible al público</td>
            <td class="align-middle">Rutinaria</td>
            <td class="align-middle">Sustancias químicas (anticongelante, lubricantes)</td>
            <td class="align-middle">Derrames por gote </td>
            <td class="align-middle">suelo</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">3</td>
            <td class="align-middle">2</td>
            <td class="align-middle">-6</td>
        </tr>
        <tr>
            <td class="align-middle">5</td>
            <td class="align-middle">Despacho de combustible al público</td>
            <td class="align-middle">Rutinaria</td>
            <td class="align-middle">Combustibl </td>
            <td class="align-middle">Derrames por goteo</td>
            <td class="align-middle">Suelo y Aire</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">4</td>
            <td class="align-middle">3</td>
            <td class="align-middle">-12</td>
        </tr>
        <tr>
            <td class="align-middle">6</td>
            <td class="align-middle">Despacho de combustible al público</td>
            <td class="align-middle">Rutinaria</td>
            <td class="align-middle">Papel y otros</td>
            <td class="align-middle">Residuos no peligrosos</td>
            <td class="align-middle">Suelo</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">2</td>
            <td class="align-middle">2</td>
            <td class="align-middle">-4</td>
        </tr>
        <tr>
            <td class="align-middle">7</td>
            <td class="align-middle">Despacho de combustible al público</td>
            <td class="align-middle">Rutinaria</td>
            <td class="align-middle">Trapo</td>
            <td class="align-middle">Residuos peligrosos</td>
            <td class="align-middle">Suelo</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">4</td>
            <td class="align-middle">4</td>
            <td class="align-middle">-16</td>
        </tr>
        <tr>
            <td class="align-middle">8</td>
            <td class="align-middle">Despacho de combustible al público</td>
            <td class="align-middle">Rutinaria</td>
            <td class="align-middle">Agua</td>
            <td class="align-middle">Agua residual (Lavado parabrisas-servicio)</td>
            <td class="align-middle">Agua</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">4</td>
            <td class="align-middle">2</td>
            <td class="align-middle">-8</td>
        </tr>
        <tr>
            <td class="align-middle">9</td>
            <td class="align-middle">Despacho de combustible al público</td>
            <td class="align-middle">Emergencia</td>
            <td class="align-middle">Combustible</td>
            <td class="align-middle">Derrames al suelo - fuga</td>
            <td class="align-middle">Suelo y Aire</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">6</td>
            <td class="align-middle">6</td>
            <td class="align-middle">-36</td>
        </tr>
        <tr>
            <td class="align-middle">10</td>
            <td class="align-middle">Descarga de combustible a tanques de almacenamiento</td>
            <td class="align-middle">No rutinaria</td>
            <td class="align-middle">Combustible</td>
            <td class="align-middle">Emisiones fugitivas de VOC´s</td>
            <td class="align-middle">Aire</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">5</td>
            <td class="align-middle">5</td>
            <td class="align-middle">-25</td>
        </tr>
        <tr>
            <td class="align-middle">11</td>
            <td class="align-middle">Descarga de combustible a tanques de almacenamiento</td>
            <td class="align-middle">No rutinaria</td>
            <td class="align-middle">Trapos</td>
            <td class="align-middle">Residuos peligrosos</td>
            <td class="align-middle">Suelo</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">4</td>
            <td class="align-middle">4</td>
            <td class="align-middle">-16</td>
        </tr>
        <tr>
            <td class="align-middle">12</td>
            <td class="align-middle">Descarga de combustible a tanques de almacenamiento</td>
            <td class="align-middle">No rutinaria</td>
            <td class="align-middle">Papel</td>
            <td class="align-middle">Residuos no peligrosos</td>
            <td class="align-middle">Suelo</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">2</td>
            <td class="align-middle">2</td>
            <td class="align-middle">-4</td>
        </tr>
        <tr>
            <td class="align-middle">13</td>
            <td class="align-middle">Descarga de combustible a tanques de almacenamiento</td>
            <td class="align-middle">Emergencia</td>
            <td class="align-middle">Combustible</td>
            <td class="align-middle">Derrames al suelo - fuga</td>
            <td class="align-middle">Suelo y Aire</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">6</td>
            <td class="align-middle">7</td>
            <td class="align-middle">-42</td>
        </tr>
        <tr>
            <td class="align-middle">14</td>
            <td class="align-middle">Almacenamiento de combustibles</td>
            <td class="align-middle">Rutinaria</td>
            <td class="align-middle">Combustible</td>
            <td class="align-middle">Emisiones fugitivas de VOC´s</td>
            <td class="align-middle">Aire</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">4</td>
            <td class="align-middle">5</td>
            <td class="align-middle">-20</td>
        </tr>
        <tr>
            <td class="align-middle">15</td>
            <td class="align-middle">Almacenamiento de combustibles</td>
            <td class="align-middle">Emergencia</td>
            <td class="align-middle">Combustible</td>
            <td class="align-middle">Derrames al suelo - fuga</td>
            <td class="align-middle">Suelo</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">5</td>
            <td class="align-middle">7</td>
            <td class="align-middle">-35</td>
        </tr>
        <tr>
            <td class="align-middle">16</td>
            <td class="align-middle">Limpieza en área de descarga</td>
            <td class="align-middle">No rutinaria</td>
            <td class="align-middle">Agua</td>
            <td class="align-middle">Agua residual (mezclada con combustibles)</td>
            <td class="align-middle">Agua</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">4</td>
            <td class="align-middle">5</td>
            <td class="align-middle">-20</td>
        </tr>
        <tr>
            <td class="align-middle">17</td>
            <td class="align-middle">Limpieza en el área de despacho de combustible al publico</td>
            <td class="align-middle">Rutinaria</td>
            <td class="align-middle">Agua</td>
            <td class="align-middle">Agua residual (mezclada con combustibles)</td>
            <td class="align-middle">Agua</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">4</td>
            <td class="align-middle">5</td>
            <td class="align-middle">-20</td>
        </tr>
        <tr>
            <td class="align-middle">18</td>
            <td class="align-middle">Limpieza en el área de despacho de combustible al publico</td>
            <td class="align-middle">Rutinaria</td>
            <td class="align-middle">Trapos</td>
            <td class="align-middle">Residuos peligrosos</td>
            <td class="align-middle">Suelo</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">4</td>
            <td class="align-middle">4</td>
            <td class="align-middle">-16</td>
        </tr>
        <tr>
            <td class="align-middle">19</td>
            <td class="align-middle">Limpieza de trampas de combustible</td>
            <td class="align-middle">No rutinaria</td>
            <td class="align-middle">Agua</td>
            <td class="align-middle">Agua residual (mezclada con combustibles)</td>
            <td class="align-middle">Agua</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">4</td>
            <td class="align-middle">6</td>
            <td class="align-middle">-24</td>
        </tr>
        <tr>
            <td class="align-middle">20</td>
            <td class="align-middle">Limpieza en tanques de almacenamiento</td>
            <td class="align-middle">No rutinaria</td>
            <td class="align-middle">Agua</td>
            <td class="align-middle">Agua residual (mezclada con combustibles)</td>
            <td class="align-middle">Agua</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">4</td>
            <td class="align-middle">4</td>
            <td class="align-middle">-16</td>
        </tr>
        <tr>
            <td class="align-middle">21</td>
            <td class="align-middle">Limpieza en oficinas y tienda</td>
            <td class="align-middle">Rutinaria</td>
            <td class="align-middle">Agua</td>
            <td class="align-middle">Agua residual (mezclada con jabón)</td>
            <td class="align-middle">Agua</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">2</td>
            <td class="align-middle">2</td>
            <td class="align-middle">-4</td>
        </tr>
        <tr>
            <td class="align-middle">22</td>
            <td class="align-middle">Limpieza en oficinas y tienda</td>
            <td class="align-middle">Rutinaria</td>
            <td class="align-middle">Trapos</td>
            <td class="align-middle">Residuos no peligrosos</td>
            <td class="align-middle">Suelo</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">3</td>
            <td class="align-middle">3</td>
            <td class="align-middle">-9</td>
        </tr>
        <tr>
            <td class="align-middle">23</td>
            <td class="align-middle">Limpieza en oficinas y tienda</td>
            <td class="align-middle">Rutinaria</td>
            <td class="align-middle">Papel</td>
            <td class="align-middle">Residuos no peligrosos</td>
            <td class="align-middle">Suelo</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">2</td>
            <td class="align-middle">2</td>
            <td class="align-middle">-4</td>
        </tr>
        <tr>
            <td class="align-middle">24</td>
            <td class="align-middle">Mantenimiento de dispensarios</td>
            <td class="align-middle">No rutinaria</td>
            <td class="align-middle">Trapos</td>
            <td class="align-middle">Residuos peligrosos</td>
            <td class="align-middle">Suelo</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">3</td>
            <td class="align-middle">3</td>
            <td class="align-middle">-9</td>
        </tr>
        <tr>
            <td class="align-middle">25</td>
            <td class="align-middle">Mantenimiento de tanques de almacenamiento</td>
            <td class="align-middle">No rutinaria</td>
            <td class="align-middle">Trapos</td>
            <td class="align-middle">Residuos peligrosos</td>
            <td class="align-middle">Suelo</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">3</td>
            <td class="align-middle">3</td>
            <td class="align-middle">-9</td>
        </tr>
        <tr>
            <td class="align-middle">26</td>
            <td class="align-middle">Venta de productos</td>
            <td class="align-middle">Rutinaria</td>
            <td class="align-middle">Envases de producto (aceites, anticongelantes, aditivos)</td>
            <td class="align-middle">Residuos peligroso </td>
            <td class="align-middle">Suelo</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">4</td>
            <td class="align-middle">4</td>
            <td class="align-middle">-16</td>
        </tr>
        <tr>
            <td class="align-middle">27</td>
            <td class="align-middle">Operación de la tienda</td>
            <td class="align-middle">Rutinaria</td>
            <td class="align-middle">Papel, plástico, cartón</td>
            <td class="align-middle">Residuos no peligrosos</td>
            <td class="align-middle">Suelo</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">2</td>
            <td class="align-middle">2</td>
            <td class="align-middle">-4</td>
        </tr>
        <tr>
            <td class="align-middle">28</td>
            <td class="align-middle">Pintado de instalaciones</td>
            <td class="align-middle">No rutinaria</td>
            <td class="align-middle">Trapos</td>
            <td class="align-middle">Residuos peligrosos</td>
            <td class="align-middle">Suelo</td>
            <td class="align-middle">-1</td>
            <td class="align-middle">3</td>
            <td class="align-middle">3</td>
            <td class="align-middle">-9</td>
        </tr>
        </tbody>
    </table>
        
        </body>
        </html>';

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $dompdf->stream("Identificación-evaluación-Aspectos-Impactos-Ambientales.pdf", ["Attachment" => true]);
    }

    public function pdfRiesgosPeligros(){

    $estacion = Estacion::find($this->estacionId());
        $apoderado = htmlspecialchars($estacion->apoderado_legal ?? '');

        if (!ModuloService::validaPermiso($this->modulo, 'descargar')) {
            header("Location: /404");
            exit;
        }

        $html = '';

        $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

        $html .= '
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset="UTF-8">
        <title>Identificación y evaluación de Riesgos y Peligros para registrar el análisis.</title>
        <link rel="stylesheet" href="'.$_ENV['APP_URL'].'/assets/css/pdf.css">
        </head>
        <body>

        <table class="table table-bordered" style="font-size: .9em;">
        <tbody>
        <tr>

        <td class="align-middle text-center">
        <img src="'.$logo.'" style="width: 150px;">
        </td>
        <td colspan="2" class="align-middle text-center">
        <b>Identificación y evaluación de Riesgos y Peligros para registrar el análisis.</b>
        </td>
        <td class="align-middle text-center">
        <b>Fo.ADMONGAS.003</b>
        </td>

        </tr>
        //------------------------------------------------------------------
        <tr>
        <td class="align-middle text-center">
        Realizado por:<br> Nelly Estrada Garcia
        </td>
        <td class="align-middle text-center">
        Revisado por:<br> Eduardo Galicia Flores
        </td>
        <td class="align-middle text-center">
        Autorizado por:<br> '.$apoderado.'
        </td>
        <td class="align-middle text-center">
        Fecha de aprobacion:<br>  01-oct-18
        </td>
        </tr>
                
        </tbody>
        </table>

            <table class="table table-sm table-bordered text-center" style="font-size: .6em;">
            <tbody>
                <tr>
                    <td class="align-middle text-center p-0" rowspan="2"><b>Id</b></td>
                    <td class="align-middle text-center" rowspan="2"><b>PROCESO</b></td>
                    <td class="align-middle text-center" rowspan="2"><b>TAREA</b></td>
                    <td class="align-middle text-center" rowspan="2"><b>PELIGRO</b></td>
                    <td class="align-middle text-center" rowspan="2"><b>RIESGO</b></td>
                    <td class="align-middle text-center" rowspan="2"><b>CONSECUENCIAS</b></td>
                    <td class="align-middle text-center" colspan="5"><b>PROBABILIDAD</b></td>
                    <td class="align-middle text-center" rowspan="2"><b>ÍNDICE<br>DE<br>SEVERIDAD</b></td>
                    <td class="align-middle text-center" rowspan="2"><b>RIE<br>SGO</b></td>
                    <td class="align-middle text-center" rowspan="2"><b>T<br>I<br>P<br>O</b></td>
                    <td class="align-middle text-center" rowspan="2"><b>¿Riesgo<br>Signif<br>icativo?</b></td>
                </tr>
                <tr>
                    <td class="align-middle text-center"><b>Índice de personas expuestas (A)</b></td>
                    <td class="align-middle text-center"><b>Índice de procedimientos existentes (B)</b></td>
                    <td class="align-middle text-center"><b>Índice de capacitación (C)</b></td>
                    <td class="align-middle text-center"><b>Índice de exposición al riesgo (D)</b></td>
                    <td class="align-middle text-center"><b>PROBAB<br>ILIDAD</b></td></td>
                </tr>
                <tr>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">Despacho de combustible</td>
                    <td class="align-middle text-center">Colocación de la manguera en el vehÍculo</td>
                    <td class="align-middle text-center">Mala colocación de la manguera</td>
                    <td class="align-middle text-center">Desprendimiento de la manguera</td>
                    <td class="align-middle text-center">Derrame de combustible y posible incendio en caso de una fuente de ignición</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">3</td>
                    <td class="align-middle text-center table-warning">6</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning font-weight-bold">6</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center">Despacho de combustible</td>
                    <td class="align-middle text-center">Colocación de la manguera en el vehÍculo</td>
                    <td class="align-middle text-center">Movimiento del vehÍculo</td>
                    <td class="align-middle text-center">Desprendimiento de la manguera</td>
                    <td class="align-middle text-center">Derrame de combustible y posible incendio en caso de una fuente de ignición</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">3</td>
                    <td class="align-middle text-center table-warning">6</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning font-weight-bold">12</td>
                    <td class="align-middle text-center table-secondary">Moderado</td>
                    <td class="align-middle text-center table-danger">SI</td>
                </tr>
                <tr>
                    <td class="align-middle text-center">3</td>
                    <td class="align-middle text-center">Despacho de combustible</td>
                    <td class="align-middle text-center">Despacho de combustible</td>
                    <td class="align-middle text-center">Condiciones climáticas</td>
                    <td class="align-middle text-center">Exposición prolongada</td>
                    <td class="align-middle text-center">Daños a la salud</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center">3</td>
                    <td class="align-middle text-center table-warning">8</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning font-weight-bold">8</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center" >4</td>
                    <td class="align-middle text-center">Despacho de combustible</td>
                    <td class="align-middle text-center">Colocación de la manguera en el vehÍculo</td>
                    <td class="align-middle text-center">Movimiento del vehÍculo</td>
                    <td class="align-middle text-center">Colisión con otro vehÍculo</td>
                    <td class="align-middle text-center">Lesiones a personas</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning">5</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning font-weight-bold">10</td>
                    <td class="align-middle text-center table-secondary">Moderado</td>
                    <td class="align-middle text-center table-danger">SI</td>
                </tr>
                <tr>
                    <td class="align-middle text-center">5</td>
                    <td class="align-middle text-center">Despacho de combustible</td>
                    <td class="align-middle text-center">Despacho a bidones</td>
                    <td class="align-middle text-center">Sobrellenado del bidón</td>
                    <td class="align-middle text-center">Derrame de combustible</td>
                    <td class="align-middle text-center">Incendio en el área</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">3</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning">8</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning font-weight-bold">16</td>
                    <td class="align-middle text-center table-secondary">Moderado</td>
                    <td class="align-middle text-center table-danger">SI</td>
                </tr>
                <tr>
                    <td class="align-middle text-center" >6</td>
                    <td class="align-middle text-center">Despacho de combustible</td>
                    <td class="align-middle text-center">Colocación de la manguera en el vehÍculo</td>
                    <td class="align-middle text-center">Movimiento del vehÍculo</td>
                    <td class="align-middle text-center">Atropellamiento de una persona</td>
                    <td class="align-middle text-center">Lesiones a personas</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">3</td>
                    <td class="align-middle text-center table-warning">7</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning font-weight-bold">14</td>
                    <td class="align-middle text-center table-secondary">Moderado</td>
                    <td class="align-middle text-center table-danger">SI</td>
                </tr>
                <tr>
                    <td class="align-middle text-center" >7</td>
                    <td class="align-middle text-center">Despacho de combustible</td>
                    <td class="align-middle text-center">Colocación de la manguera en el vehÍculo</td>
                    <td class="align-middle text-center">Movimiento del vehÍculo</td>
                    <td class="align-middle text-center">Colisión con las instalaciones</td>
                    <td class="align-middle text-center">Incidentes materiales</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning">5</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning font-weight-bold">5</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center">8</td>
                    <td class="align-middle text-center">Despacho de combustible</td>
                    <td class="align-middle text-center">Colocación de la manguera en el vehÍculo</td>
                    <td class="align-middle text-center">Equipos en mal estado (mangueras, pistola de suministro, conexiones, sistema de recuperación de gases etc.)</td>
                    <td class="align-middle text-center">Fuga de combustible</td>
                    <td class="align-middle text-center">Derrame de combustible y posible incendio en caso de una fuente de ignición</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning">4</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning font-weight-bold">8</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center" >9</td>
                    <td class="align-middle text-center">Despacho de combustible</td>
                    <td class="align-middle text-center">Colocación de la manguera en el vehÍculo</td>
                    <td class="align-middle text-center">Equipos de suministro en mal estado</td>
                    <td class="align-middle text-center">Contacto con combustibles o sustancias quÍmicas</td>
                    <td class="align-middle text-center">Daños a la salud</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning">4</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning font-weight-bold">4</td>
                    <td class="align-middle text-center table-secondary">Trivial</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center" >10</td>
                    <td class="align-middle text-center">Despacho de combustible</td>
                    <td class="align-middle text-center">Colocación de la manguera en el vehÍculo</td>
                    <td class="align-middle text-center">vehÍculos de gran tonelaje</td>
                    <td class="align-middle text-center">Exposición al ruido</td>
                    <td class="align-middle text-center">Daños a la salud</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">3</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning">8</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning font-weight-bold">8</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center" >11</td>
                    <td class="align-middle text-center">Descarga del auto tanque</td>
                    <td class="align-middle text-center">Conexión de manguera al tanque fijo</td>
                    <td class="align-middle text-center">Mala colocación de la manguera</td>
                    <td class="align-middle text-center">Desprendimiento de la manguera</td>
                    <td class="align-middle text-center">Derrame mayor de combustible</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning">5</td>
                    <td class="align-middle text-center">3</td>
                    <td class="align-middle text-center table-warning font-weight-bold">15</td>
                    <td class="align-middle text-center table-secondary">Moderado</td>
                    <td class="align-middle text-center table-danger">SI</td>
                </tr>
                <tr>
                    <td class="align-middle text-center">12</td>
                    <td class="align-middle text-center">Mantenimiento de instalaciones</td>
                    <td class="align-middle text-center">Limpieza en drenajes, registros y trampa de combustibles</td>
                    <td class="align-middle text-center">Acumulación de combustibles</td>
                    <td class="align-middle text-center">Contacto con combustibles o sustancias quÍmicas</td>
                    <td class="align-middle text-center">Daños a la salud</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning">5</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning font-weight-bold">5</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center" >13</td>
                    <td class="align-middle text-center">Mantenimiento de instalaciones</td>
                    <td class="align-middle text-center">Prueba de hermeticidad a tanques y tuberÍas</td>
                    <td class="align-middle text-center">Falta de purgado de tanques</td>
                    <td class="align-middle text-center">Acumulación de vapores</td>
                    <td class="align-middle text-center">posible incendio en caso de una fuente de ignición</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning">4</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning font-weight-bold">8</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center">14</td>
                    <td class="align-middle text-center">Mantenimiento de instalaciones</td>
                    <td class="align-middle text-center">Revisar la calibración de medidores mediante la jarra patrón</td>
                    <td class="align-middle text-center">Derrame de combustible</td>
                    <td class="align-middle text-center">Contacto con combustibles o sustancias quÍmicas</td>
                    <td class="align-middle text-center">Daños a la salud</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning">5</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning font-weight-bold">5</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center">15</td>
                    <td class="align-middle text-center">Mantenimiento de instalaciones</td>
                    <td class="align-middle text-center">Pintar la fachada</td>
                    <td class="align-middle text-center">Mala colocación de barandillas, barras intermedias y plintos</td>
                    <td class="align-middle text-center">CaÍda</td>
                    <td class="align-middle text-center">Lesiones a personas</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning">4</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning font-weight-bold">8</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center">16</td>
                    <td class="align-middle text-center">Mantenimiento de instalaciones</td>
                    <td class="align-middle text-center">Cambio de lámparas o focos</td>
                    <td class="align-middle text-center">Mala colocación de la escalera</td>
                    <td class="align-middle text-center">CaÍda</td>
                    <td class="align-middle text-center">Lesiones a personas</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning">4</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning font-weight-bold">8</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center" >17</td>
                    <td class="align-middle text-center">Mantenimiento de instalaciones</td>
                    <td class="align-middle text-center">Revisión de instalaciones eléctricas</td>
                    <td class="align-middle text-center">Falta de des energización de equipos</td>
                    <td class="align-middle text-center">Generación de carga electricidad estática</td>
                    <td class="align-middle text-center">Lesiones a personas</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning">5</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning font-weight-bold">5</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center">18</td>
                    <td class="align-middle text-center">Mantenimiento de instalaciones</td>
                    <td class="align-middle text-center">Pintado delimitación de áreas de despacho, patios, oficinas</td>
                    <td class="align-middle text-center">Falta de acordonamiento del área</td>
                    <td class="align-middle text-center">Atropellamiento de una persona</td>
                    <td class="align-middle text-center">Lesiones a personas</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning">5</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning font-weight-bold">10</td>
                    <td class="align-middle text-center table-secondary">Moderado</td>
                    <td class="align-middle text-center table-danger">SI</td>
                </tr>
                <tr>
                    <td class="align-middle text-center">19</td>
                    <td class="align-middle text-center">Mantenimiento de instalaciones</td>
                    <td class="align-middle text-center">Colocación de publicidad</td>
                    <td class="align-middle text-center">Escalera mal colocada</td>
                    <td class="align-middle text-center">CaÍda</td>
                    <td class="align-middle text-center">Lesiones a personas</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning">6</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning font-weight-bold">6</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center">20</td>
                    <td class="align-middle text-center">Limpieza</td>
                    <td class="align-middle text-center">Limpieza y orden en el lugar de trabajo</td>
                    <td class="align-middle text-center">Suelo mojado</td>
                    <td class="align-middle text-center">CaÍdas, resbalones</td>
                    <td class="align-middle text-center">Lesiones a personas</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">3</td>
                    <td class="align-middle text-center table-warning">6</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning font-weight-bold">6</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center">21</td>
                    <td class="align-middle text-center">Limpieza</td>
                    <td class="align-middle text-center">Limpieza y orden en el lugar de trabajo</td>
                    <td class="align-middle text-center">Acumulación en suelo de vertidos de aceites, carburantes, lÍquidos de frenos y similares</td>
                    <td class="align-middle text-center">CaÍdas, tropiezos</td>
                    <td class="align-middle text-center">Lesiones a personas</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">3</td>
                    <td class="align-middle text-center table-warning">6</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning font-weight-bold">6</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center" >22</td>
                    <td class="align-middle text-center">Limpieza</td>
                    <td class="align-middle text-center">Limpieza y orden en el lugar de trabajo</td>
                    <td class="align-middle text-center">Obstáculos, objetos abandonados o mal situados</td>
                    <td class="align-middle text-center">CaÍdas, tropiezos</td>
                    <td class="align-middle text-center">Lesiones a personas</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">3</td>
                    <td class="align-middle text-center table-warning">6</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning font-weight-bold">6</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center">23</td>
                    <td class="align-middle text-center">Limpieza</td>
                    <td class="align-middle text-center">Medición del volumen de los tanques de almacenamiento</td>
                    <td class="align-middle text-center">Presencia de vapores de combustibles</td>
                    <td class="align-middle text-center">Contacto con vapores de combustibles</td>
                    <td class="align-middle text-center">Intoxicación</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning">5</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning font-weight-bold">5</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
                <tr>
                    <td class="align-middle text-center" >24</td>
                    <td class="align-middle text-center">Limpieza</td>
                    <td class="align-middle text-center">Limpieza de tanques de almacenamiento</td>
                    <td class="align-middle text-center">Presencia de combustibles en el tanque</td>
                    <td class="align-middle text-center">Contacto con vapores de combustibles</td>
                    <td class="align-middle text-center">Intoxicación</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center">2</td>
                    <td class="align-middle text-center table-warning">5</td>
                    <td class="align-middle text-center">1</td>
                    <td class="align-middle text-center table-warning font-weight-bold">5</td>
                    <td class="align-middle text-center table-secondary">Tolerable</td>
                    <td class="align-middle text-center table-success">NO</td>
                </tr>
            </tbody>
        </table>
        
        </body>
        </html>';

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $dompdf->stream("Identificación-evaluación-Riesgos-Peligros-registrar-análisis.pdf", ["Attachment" => true]);

    }

    public function anexosAnalisisRiesgo($id)
    {
            header('Content-Type: application/json; charset=utf-8');

            try {

                $analisis = AnalisisRiesgo::find($id);

                if (!$analisis) {
                    throw new \Exception('No encontrado');
                }

                $anexos = AnalisisRiesgoAnexo::where('id_analisis', $id)->get();

                echo json_encode([
                    'success' => true,
                    'data' => [
                        'fecha' => formatearFecha($analisis->fecha),
                        'descripcion' => $analisis->descripcion,
                        'anexos' => $anexos
                    ]
                ]);

            } catch (\Throwable $e) {

                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
    }

    //------------------------------------------------------------------------------------
    //------------------------------------------------------------------------------------

    //------------------------------------------------------------------------------------
    //------------- 5 Funciones, responsabilidades y auditoria ---------------------------
    

    public function funcionesResponsabilidadesAutoridad(){

        $title = '5. FUNCIONES, RESPONSABILIDADES Y AUTORIDAD';
        // Buscar permisos de los modulos
        $permisos = ModuloService::permisosSesion($this->modulo);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

        $estacion = Estacion::find($this->estacionId());
        
         $data = [
            'title' => $title,
             'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'organigrama' => asset('/images/organigramas/' . $estacion->organigrama),
             'links' =>[
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/libs/select2/dist/css/select2.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/libs/select2/dist/js/select2.full.min.js',
                '/libs/select2/dist/js/select2.min.js',
                '/js/asistencia/listaasistencia.datatable.init.js?v=1.0',
                '/js/asistencia/listaasistencia.actions.init.js?v=1.0',
                '/js/sasisopa/listarepresentantetecnico.datatable.init.js?v=1.5',
                '/js/sasisopa/representantetecnico.action.init.js?v=1.1'
            ],
            'help' => true
        ];
        
        View::render('sasisopa/funciones-responsabilidades-autoridad', $data,'sasisopa');

    }

    public function datatableListaRepresentanteTecnico(){

        $permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');
        $permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');
        $permisoDescargar   = ModuloService::validaPermiso($this->modulo, 'descargar');

        $data = RepresentanteTecnico::where('id_estacion',$this->estacionId())
        ->orderBy('fecha')
        ->get();

         echo json_encode([
            "data" => $data,
             "permisos" => [
                "eliminar" => $permisoEliminar,
                "editar"   => $permisoEditar,
                "descargar" => $permisoDescargar
            ]
        ]);
        
        exit;
    }

    public function createRepresentanteTecnico(){

        header('Content-Type: application/json; charset=utf-8');

         if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear'
            ]);
            exit;
        }

        
        $nombre = sanitize_input($_POST['nombre'] ?? null, 'string');
        $fecha = sanitize_input($_POST['fecha'] ?? null, 'string');
        $file  = $_FILES['pdf'] ?? null;

        if (!$nombre || !$fecha) {
        echo json_encode([
            'success' => false,
            'message' => 'Campos obligatorios'
        ]);
        exit;
        }

        // CONFIG RUTA
        $carpeta = __DIR__ . '../../../public/uploads/archivos/representante-tecnico/';

        // SECURITY: BAJO #35 - Usar mkdir_safe con permisos 0755
        if (!file_exists($carpeta)) {
            mkdir_safe($carpeta, true);
        }

        $nombreArchivo = null;

        try {

            // SUBIR ARCHIVO (opcional)
            if ($file && $file['error'] === UPLOAD_ERR_OK) {

                // Validar extensión
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                // nombre único
                $nombreArchivo = uniqid('Formato_') . '.' . $extension;

                $rutaDestino = $carpeta . $nombreArchivo;

                if (!move_uploaded_file($file['tmp_name'], $rutaDestino)) {
                    throw new \Exception('No se pudo guardar el archivo');
                }
            }

            // GUARDAR EN BD
            RepresentanteTecnico::create([
                'id_estacion' => $this->estacionId(),
                'nom_representante'  => $nombre,
                'fecha'       => $fecha,
                'archivo'   => 'archivos/representante-tecnico/' . $nombreArchivo
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Representante técnico almacenado correctamente'
            ]);

        } catch (\Throwable $e) {

            // Si falla BD, borrar archivo
            if ($nombreArchivo && file_exists($carpeta . $nombreArchivo)) {
                unlink($carpeta . $nombreArchivo);
            }

            echo json_encode([
                'success' => false,
                'message' => 'Error al guardar representante técnico'
            ]);
        }

        exit;   

    }

    public function deleteRepresentanteTecnico(){
        header('Content-Type: application/json; charset=utf-8');

        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;

        if (!ModuloService::validaPermiso($this->modulo, 'eliminar')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para eliminar'
            ]);
            exit;
        }

        if (!$id) {
            echo json_encode(['success' => false,'message' => 'ID requerido']);
            exit;
        }

            try {

            // Buscar registro
            $reporte = RepresentanteTecnico::find($id);

            if (!$reporte) {
                throw new \Exception('Registro no encontrado');
            }

            // Ruta archivo
            $rutaBase = __DIR__ . '../../../public/uploads/';
            $rutaArchivo = $rutaBase . $reporte->archivo;

            // TRANSACCIÓN
            Capsule::beginTransaction();

            // Eliminar archivo si existe
            if ($reporte->archivo && file_exists($rutaArchivo)) {
                unlink($rutaArchivo);
            }

            // Eliminar registro (puedes usar delete o estado = 0)
            $reporte->delete();

            Capsule::commit();

            echo json_encode([
                'success' => true,
                'message' => 'Representante técnico eliminado correctamente'
            ]);

        } catch (\Throwable $e) {

            Capsule::rollBack();

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

    //------------------------------------------------------------------------------------
    //------------------------------------------------------------------------------------
    //--------------------------------------------------------------

    public function calendario(){

        $title = 'Calendario';

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
