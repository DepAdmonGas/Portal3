<?php
namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\View;

use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Sasisopa\ListaAsistencia;
use App\Models\Sasisopa\ListaAsistenciaDetalle;
use App\Models\Sasisopa\ListaAsistenciaEvidencia;
use App\Models\Sasisopa\ComunicacionIE;
use App\Models\Sgm\Autorizado;
use App\Services\ModuloService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Capsule\Manager as Capsule;

class ListaAsistenciaController extends BaseController{
        protected string $modulo = 'sasisopa';

        public function indexListaAsistencia($id){
        
        $asistencia = ListaAsistencia::where('id', $id)
        ->where('id_estacion', $this->estacionId())
        ->first();

        if (!$asistencia) {
            header("Location: /404");
            exit;
        }

        if ($asistencia->realizadopor == 0) {
        $title = "Fo.ADMONGAS.010 (Registro de la atención y el seguimiento a la comunicación interna y externa.)";
            $bcModulo = 'SASISOPA';
            $bcUrl = '/sasisopa';

        if($asistencia->punto_sasisopa == 1 ){
            $bcSubModulo = '1. POLÍTICA';
            $bcSubUrl = '/sasisopa/politica';
        }else if($asistencia->punto_sasisopa == 2 ){
            $bcSubModulo = '2. IDENTIFICACIÓN DE PELIGROS Y ASPECTOS AMBIENTALES, ANÁLISIS DE RIESGO Y EVALUACIÓN DE IMPACTOS AMBIENTALES';
            $bcSubUrl = '/sasisopa/identificacion-peligros-aspectos-ambientales-analisis-riesgo-evaluacion-impactos-ambientales';
        }else if($asistencia->punto_sasisopa == 3 ){
            $bcSubModulo = '3. REQUISITOS LEGALES';
            $bcSubUrl = '/sasisopa/requisitos-legales';
        }else if($asistencia->punto_sasisopa == 5 ){
            $bcSubModulo = '5. FUNCIONES, RESPONSABILIDADES Y AUTORIDAD';
            $bcSubUrl = '/sasisopa/funciones-responsabilidades-autoridad';
        }

        } else {
        $title = "Fo.SGM.001 Lista de asistencia";

            $bcModulo = 'SGM';
            $bcUrl = '/sgm';

            if($asistencia->punto_sasisopa == 104 ){
            $bcSubModulo = '';
            $bcSubUrl = '';
            }
        }

        // Buscar permisos de los modulos
        $permisos = ModuloService::permisosSesion($this->modulo);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add($bcModulo, $bcUrl);
        Breadcrumb::add($bcSubModulo, $bcSubUrl);        
        Breadcrumb::add($title, '');

        $encargados = Usuario::where('id_gas', $this->estacionId())
        ->where('id_puesto', 6)
        ->activo()
        ->orderBy('nombre')
        ->get(['nombre']);

        $usuariosAsignados = ListaAsistenciaDetalle::where('id_lista_asistencia', $id)
        ->pluck('usuario');

        $personal = Usuario::activo()
        ->where('id_gas', $this->estacionId())
        ->when(!empty($usuariosAsignados), function ($query) use ($usuariosAsignados) {
            $query->whereNotIn('nombre', $usuariosAsignados);
        })->get(['id', 'nombre']);

         $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'idListaAsistencia' => $id,
            'asistencia' => $asistencia,
            'encargados' => $encargados,
            'personal' => $personal,
             'links' =>[
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/libs/select2/dist/css/select2.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/libs/select2/dist/js/select2.full.min.js',
                '/libs/select2/dist/js/select2.min.js',
                '/js/asistencia/listaasistenciafirma.datatable.init.js?v=1.3',
                '/js/asistencia/listaasistencia.actions.init.js?v=1.7'
            ],
            'help' => true
        ];
        
        View::render('asistencia/asistencia', $data,'sasisopa');

        }
    
    public function datatableFirmaListaAsistencia($id){

            // permisos
        $permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');

        $data = ListaAsistenciaDetalle::where('id_lista_asistencia', $id)->get();
        $rutaPublica = $_ENV['APP_URL'] . '/uploads/firma-personal/';

         echo json_encode([
            "data" => $data,
            "permisos" => [
                "eliminar" => $permisoEliminar
            ],
            "urlFirma" => $rutaPublica

        ]);
        
        exit;

    }
    public function datatableListaAsistencia($elemento){
         // permisos
        $permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');
        $permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');
        $permisoDescargar   = ModuloService::validaPermiso($this->modulo, 'descargar');

        $data = ListaAsistencia::where('punto_sasisopa', $elemento)
        ->where('id_estacion', $this->estacionId())
        ->orderBy('fecha', 'desc')
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

    public function createListaAsistencia(){
        header('Content-Type: application/json; charset=utf-8');

        $data = json_decode(file_get_contents('php://input'), true);

        // SECURITY: Sanitización de inputs (Vulnerabilidad #5)
        $punto = sanitize_input($data['punto_sasisopa'] ?? null, 'string');
        $herramienta = sanitize_input($data['herramienta'] ?? null, 'int');

         if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear'
            ]);
            exit;
        }

        if (!$punto) {
            echo json_encode([
                'success' => false,
                'message' => 'Dato requerido'
            ]);
            return;
        }

        Capsule::beginTransaction();

        try {

            $estacion = $this->estacionId();
            $usuario = $this->userId();

            // buscar autorizado
            if($herramienta == 2){
                $realizadopor = Autorizado::join('tb_usuarios', 'sgm_autorizado.id_usuario', '=', 'tb_usuarios.id')
                ->where('tb_usuarios.id_gas', $estacion)
                ->where('sgm_autorizado.estado', 1)
                ->value('sgm_autorizado.id_usuario') ?? 0;
            }else{
                $realizadopor = 0; 
            }

            // crear registro (AUTO INCREMENT, ya no necesitas id manual)
            $asistencia = ListaAsistencia::create([
                'id_estacion'     => $estacion,
                'id_usuario'      => $usuario,
                'punto_sasisopa'  => $punto,
                'fecha'           => date('Y-m-d'),
                'hora'            => date('H:i:s'),
                'lugar'           => '',
                'tema'            => '',
                'finalidad'       => '',
                'encargado'       => '',
                'realizadopor'    => $realizadopor,
                'estado'          => 0
            ]);

            Capsule::commit();

            echo json_encode([
                'success' => true,
                'id' => $asistencia->id,
                'message' => 'Lista de asistencia guardada correctamente'
            ]);

        } catch (\Throwable $e) {

            Capsule::rollBack();

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
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
            return;
        }

         if (!$id) {
            echo json_encode(['success' => false,'message' => 'ID requerido']);
            return;
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

    public function updateListaAsistencia(){

    header('Content-Type: application/json; charset=utf-8');
    $data = json_decode(file_get_contents('php://input'), true);

     if (!ModuloService::validaPermiso($this->modulo, 'editar')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para editar'
            ]);
            return;
        }

        try {

        $asistencia = ListaAsistencia::find($data['id']);

        if (!$asistencia) {
            echo json_encode([
                'success' => false,
                'message' => 'Registro no encontrado'
            ]);
            return;
        }

        $asistencia->update([
            'fecha' => $data['fecha'],
            'hora' => $data['hora'],
            'lugar' => $data['lugar'],
            'encargado' => $data['encargado'],
            'tema' => $data['tema'],
            'finalidad' => $data['finalidad'],
            'estado' => 1
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Actualizado correctamente'
        ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

    }

    public function createFirmaListaAsistencia()
    {
        header('Content-Type: application/json; charset=utf-8');

        $data = json_decode(file_get_contents('php://input'), true);

        $id = $data['id_lista_asistencia'] ?? null;
        $personal = $data['personal'] ?? [];

        if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear'
            ]);
            return;
        }

        if (!$id || empty($personal)) {
            echo json_encode([
                'success' => false,
                'message' => 'Datos incompletos'
            ]);
            return;
        }

        try {

            // TRAES TODOS LOS USUARIOS EN UNA SOLA CONSULTA
            $usuarios = Usuario::with('puesto')
                ->whereIn('nombre', $personal)
                ->where('estatus', 0)
                ->get()
                ->keyBy('nombre');

            foreach ($personal as $nombre) {

                $usuario = $usuarios[$nombre] ?? null;

                if (!$usuario) continue;

                ListaAsistenciaDetalle::create([
                    'id_lista_asistencia' => $id,
                    'usuario' => $usuario->nombre,
                    'puesto' => $usuario->puesto->tipo_puesto ?? '',
                    'firma' => $usuario->firma ?? ''
                ]);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Personal guardado correctamente'
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function deleteFirmaListaAsistencia(){

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
            echo json_encode(['success' => false,'message' => 'ID requerido']);
            return;
        }

         Capsule::beginTransaction();
         try {

            // Buscar registro
            $detalle = ListaAsistenciaDetalle::find($id);

            if (!$detalle) {
                echo json_encode([
                'success' => false,
                'message' => 'Registro no encontrado'
            ]);
            return;
            }

            $nombre = $detalle->usuario;

            // Eliminar registro
            $detalle->delete();           

            Capsule::commit();

            echo json_encode([
                'success' => true,
                'message' => 'Firma eliminada correctamente',
                'nombre' => $nombre
            ]);

        } catch (\Throwable $e) {

            Capsule::rollBack();

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

    }

}