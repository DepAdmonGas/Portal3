<?php
namespace App\Controllers;
use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Sasisopa\ListaAsistencia;
use App\Models\Sasisopa\ListaAsistenciaDetalle;
use App\Models\Sasisopa\ListaAsistenciaEvidencia;
use App\Models\Sasisopa\ComunicacionIE;
use App\Services\ModuloService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Capsule\Manager as Capsule;

class ListaAsistenciaController extends BaseController{
        protected string $modulo = 'sasisopa';
        public function datatableListaAsistencia($elemento){
         // permisos
        $permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');
        $permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');
        $permisoDescargar   = ModuloService::validaPermiso($this->modulo, 'descargar');

        $data = ListaAsistencia::where('punto_sasisopa', $elemento)
        ->where('id_estacion', $this->estacionId())
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

    public function deleteListaAsistencia(){
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

         Capsule::beginTransaction();
         try {

            // Buscar registro
            $evidencia = ListaAsistenciaEvidencia::where('id_lista_asistencia', $id);

            $reporte = ListaAsistencia::find($id);

            if (!$reporte) {
                throw new \Exception('Registro no encontrado');
            }

             $detalle = ListaAsistenciaDetalle::where('id_lista_asistencia',$id);

            if (!$detalle) {
                throw new \Exception('Registro no encontrado');
            }

            // Eliminar registro
            $evidencia->delete();
            $detalle->delete();
            $reporte->delete();
           

            Capsule::commit();

            echo json_encode([
                'success' => true,
                'message' => 'Reporte eliminado correctamente'
            ]);

        } catch (\Throwable $e) {

            Capsule::rollBack();

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function pdfListaAsistencia($id){
        header('Content-Type: application/pdf');

        $estacion = Estacion::find($this->estacionId());
        $apoderado = htmlspecialchars($estacion->apoderado_legal ?? '');

        $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

        $asistencia = ListaAsistencia::find($id);
        $detalle = ListaAsistenciaDetalle::where('id_lista_asistencia',$id)->get();
        $comunicacion = ComunicacionIE::where('asistencia', $id)->first();
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Registro de la atención y el seguimiento a la comunicación interna y externa.</title>
            <link rel="stylesheet" href="'.$_ENV['APP_URL'].'/assets/css/pdf.css">
        </head>
        <body>
        <table class="table table-bordered">
            <tbody>
            <tr>

            <td class="align-middle text-center">
            <img src="'.$logo.'" style="width: 150px;">
            </td>
            <td colspan="2" class="align-middle text-center">
            <b>Registro de la atención y el seguimiento a la comunicación interna y externa.</b>
            </td>
            <td class="align-middle text-center">
            <b>Fo.ADMONGAS.010</b>
            </td>

            </tr>
            
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
            Fecha de aprobacion:<br>  28-09-2020
            </td>
            </tr>  
            </tbody>
        </table>

        <table class="table table-bordered">
        <tbody>

        <tr>
        <td class="align-middle text-center">
        Fecha: '.formatearFecha($asistencia->fecha).'
        </td>
        <td class="align-middle text-center">
        Hora: '.date('g:i a', strtotime($asistencia->hora)).'
        </td>
        <td class="align-middle text-center">
        Lugar: '.$asistencia->lugar.'
        </td>
        </tr>

        <tr>
        <td class="align-middle" colspan="3">
        <b>Tema a cominicar:</b> '.$asistencia->tema.'
        </td>
        </tr>

        <tr>
        <td class="align-middle" colspan="3">
        <b>Finalidad de la comunicación:</b> '.$asistencia->finalidad.'
        </td>
        </tr>

        <tr>
        <td class="align-middle" colspan="3">
        <b>Nombre del encargado de la comunicación:</b> '.$asistencia->encargado.'
        </td>
        </tr>';

        if($comunicacion){
        $html .= '<tr>
        <td class="align-middle" colspan="3">
        <b>Tipo de comunicación:</b> '.$comunicacion->tipo_comunicacion.'
        </td>
        </tr>

        <tr>
        <td class="align-middle" colspan="3">
        <b>Material utilizado para la comunicación:</b> '.$comunicacion->material.'
        </td>
        </tr>';
        }

        $html .= '</tbody>
        </table>';

        $html .=  '<table class="table table-bordered">
        <tbody>
        <tr>
        <td class="align-middle text-center"><b>Nombre</b></td> 
        <td class="align-middle text-center"><b>Puesto</b></td> 
        <td class="align-middle text-center"><b>Firma</b></td>
        </tr>';

        foreach ($detalle as $row) {

        $firma = Usuario::buscarFirma($row->usuario);

            $rutaFirma = realpath(__DIR__ . '/../../public/uploads/firma-personal/' . $firma);

            if (empty($firma)) {
            $firma_usuario = '';
            } else {

                if (file_exists($rutaFirma)) {
                    $rutaPublica = $_ENV['APP_URL'] . '/uploads/firma-personal/' . $firma;
                    $firma_usuario = '<img src="'.$rutaPublica.'" style="width: 70px;">';
                } else {
                    $firma_usuario = '';
                }
            }

        $html .=  '<tr>
                <td class="align-middle">'.$row->usuario.'</td>
                <td class="align-middle">'.$row->puesto.'</td>
                <td class="align-middle text-center">'.$firma_usuario.'</td>
            </tr>';
        }

        $html .= '
        </tbody>
        </table>        
        </body>
        </html>
        ';


        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $dompdf->stream("Registro-atención-seguimiento-comunicación-interna-externa.pdf", ["Attachment" => true]);
    }

}