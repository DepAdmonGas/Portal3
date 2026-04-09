<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Models\Estacion;
use App\Models\Sasisopa\RequisitosLegalesCalendario;
use App\Models\Sasisopa\RequisitosLegalesLista;
use App\Models\Sasisopa\RequisitosLegalesDependencia;
use App\Services\ModuloService;

use Dompdf\Dompdf;
use Dompdf\Options;

class RequisitosLegalesController extends BaseController{

    protected string $modulo = 'sasisopa';

    public function requisitosLegales(){

        $title = '3. REQUISITOS LEGALES';
        // Buscar permisos de los modulos
        $permisos = ModuloService::permisosSesion($this->modulo);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

        $requisitos = RequisitosLegalesCalendario::ToRequisitosTodos($this->estacionId());

         $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'requisitos' => $requisitos,
             'links' =>[
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/libs/select2/dist/css/select2.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/libs/select2/dist/js/select2.full.min.js',
                '/libs/select2/dist/js/select2.min.js',
                '/js/asistencia/listaasistencia.datatable.init.js',
                '/js/asistencia/listaasistencia.actions.init.js?v=1.0'
            ],
            'help' => true
        ];
        
        View::render('requisitoslegales/index', $data,'sasisopa');

    }

    public function calendarioRequisitosLegales(){

        $estacion = Estacion::find($this->estacionId());
        $apoderado = htmlspecialchars($estacion->apoderado_legal ?? '');

        if (!ModuloService::validaPermiso($this->modulo, 'descargar')) {
            header("Location: /404");
            exit;
        }

        $municipal = RequisitosLegalesCalendario::NivelGobierno('Municipal', $this->estacionId());
        $estatal   = RequisitosLegalesCalendario::NivelGobierno('Estatal', $this->estacionId());
        $federal   = RequisitosLegalesCalendario::NivelGobierno('Federal', $this->estacionId());
        $varios    = RequisitosLegalesCalendario::NivelGobierno('Varios', $this->estacionId());

        $html = '';
        $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

        $html .= '
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset="UTF-8">
        <title>Calendario anual de renovacion de Requisitos Legales</title>
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
        <b>Calendario anual de renovacion de Requisitos Legales</b>
        </td>
        <td class="align-middle text-center">
        <b>Fo.ADMONGAS.004</b>
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

        <table class="table table-bordered table-sm mt-4" style="font-size: .75em;" width="100%">

        <tr class="table-active">
        <td class="text-center align-middle"><b>Dependencia</b></td>
        <td class="text-center align-middle"><b>Permiso</b></td>
        <td class="text-center align-middle"><b>Vigencia</b></td>
        <td class="text-center align-middle"><b>Fecha emisión</b></td>
        <td class="text-center align-middle"><b>Fecha vencimiento</b></td>
        <td class="text-center align-middle"><b>Renovación</b></td>
        </tr>';

        $html .= $this->renderNivel('Municipal', $municipal);
        $html .= $this->renderNivel('Estatal', $estatal);
        $html .= $this->renderNivel('Federal', $federal);
        $html .= $this->renderNivel('Varios', $varios);

        $html .= '</table>

        </body>
        </html>';

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $dompdf->stream("Calendario-anual-renovacion-Requisitos-Legales.pdf", ["Attachment" => true]);
        
    }

    private function renderNivel($titulo, $data)
    {
        $html = '';

        $html .= '
        <tr>
            <td class="text-center table-info" colspan="6">
                <b>Nivel de Gobierno '.$titulo.'</b>
            </td>
        </tr>';

        foreach ($data as $row) {

            $html .= '
            <tr>
                <td>'.$row['dependencia'].'</td>
                <td><b>'.$row['requisito'].'</b></td>
                <td>'.$row['vigencia'].'</td>
                <td>'.$row['fecha_emision'].'</td>
                <td>'.$row['fecha_vencimiento'].'</td>
                <td>'.$row['renovacion'].'</td>
            </tr>';
        }

        return $html;
    }

    public function requisitosLegalesConfiguracion(){

        $title = 'REQUISITOS LEGALES CONFIGURACIÓN';
         // Buscar permisos de los modulos
        $permisos = ModuloService::permisosSesion($this->modulo);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('3. REQUISITOS LEGALES', '/sasisopa/requisitos-legales');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
             'links' =>[
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/libs/select2/dist/css/select2.min.css',
                '/css/select2-modal.css?v=1.0.1'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/libs/select2/dist/js/select2.full.min.js',
                '/libs/select2/dist/js/select2.min.js',
                '/js/requisitoslegales/configuracion.datatable.init.js?v=1.1',
                '/js/requisitoslegales/configuracion.actions.init.js?v=1.1'
            ]
        ];
        
        View::render('requisitoslegales/configuracion', $data,'sasisopa');

    }

    public function datatableConfiguracion(){

        $idEstacion = $this->estacionId();
        $estacion = Estacion::find($idEstacion);

        $estado = $estacion->di_estado;
        $municipio = $estacion->di_municipio;
        // permisos
        $permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');

        $data = RequisitosLegalesLista::select('id', 'nivel_gobierno', 'dependencia', 'permiso', 'fundamento')
        ->whereIn('id_estacion', [$idEstacion, 0])
        ->where('estado', 1)
        ->where(function ($query) use ($municipio, $estado) {

            // Municipal
            $query->where(function ($q) use ($municipio) {
                $q->where('nivel_gobierno', 'Municipal')
                ->where('mun_alc_est', $municipio);
            });

            // Estatal
            $query->orWhere(function ($q) use ($estado) {
                $q->where('nivel_gobierno', 'Estatal')
                ->where('mun_alc_est', $estado);
            });

            // Federal y Varios (sin filtro extra)
            $query->orWhereIn('nivel_gobierno', ['Federal', 'Varios']);
        })
        ->orderBy('dependencia', 'asc')
        ->get();

         echo json_encode([
            "data" => $data,
            "permisos" => [
                "eliminar" => $permisoEliminar
            ]
        ]);
        
        exit;

    }

    public function deleteConfiguracion()
    {
        header('Content-Type: application/json; charset=utf-8');

        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;

        if (!ModuloService::validaPermiso($this->modulo, 'eliminar')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para eliminar'
            ]);
            return;
        }

        if (!$id) {
            echo json_encode([
                'success' => false,
                'message' => 'ID requerido'
            ]);
            return;
        }

        $requisito = RequisitosLegalesLista::find($id);

        if (!$requisito) {
            echo json_encode([
                'success' => false,
                'message' => 'No existe el registro'
            ]);
            return;
        }

        if ($requisito->disabled != 0) {
            echo json_encode([
                'success' => false,
                'message' => 'No se puede eliminar'
            ]);
            return;
        }

        $requisito->estado = 0;
        $requisito->save();

        echo json_encode([
            'success' => true,
            'message' => 'Requisito Legal eliminado correctamente'
        ]);
    }

    public function getDependencias()
    {
        header('Content-Type: application/json');

        $idEstacion = $this->estacionId();

        $data = RequisitosLegalesDependencia::whereIn('id_estacion', [$idEstacion, 0])
            ->where('estado', 1)
            ->orderBy('dependencia', 'asc')
            ->get(['id', 'dependencia']);

        echo json_encode($data);

        exit;
    }

    public function createConfiguracion(){

        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);

        $gobierno = $data['gobierno'] ?? null;
        $dependencia = $data['dependencia'] ?? null;
        $permiso = $data['permiso'] ?? null;
        $fundamento = $data['fundamento'] ?? null;


        if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear'
            ]);
            return;
        }

        if (!$gobierno || !$dependencia || !$permiso || !$fundamento) {
            echo json_encode([
                'success' => false,
                'message' => 'Completa todos los campos obligatorios'
            ]);
            return;
        }

        $estacion = Estacion::find($this->estacionId());

        
        if($gobierno == "Municipal"){
        $MA = $estacion->di_municipio;
        }else if($gobierno == "Estatal"){
        $MA = $estacion->di_estado;
        }else if($gobierno == "Federal"){
        $MA = "";
        }else if($gobierno == "Varios"){
        $MA = "";
        }

         $asistencia = RequisitosLegalesLista::create([
                'nivel_gobierno'  => $gobierno,
                'mun_alc_est'     => $MA,
                'dependencia'     => $dependencia,
                'permiso'         => $permiso,
                'fundamento'      => $fundamento,
                'id_estacion'     => $this->estacionId(),
                'disabled'        => 0,
                'estado'          => 1
            ]);

         echo json_encode([
                'success' => true,
                'id' => $asistencia->id,
                'message' => 'Lista de asistencia guardada correctamente'
            ]);
    }

    

}
