<?php 

namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Models\Estacion;
use App\Services\ModuloService;
use App\Models\Sasisopa\EquipoCritico;
use Dompdf\Dompdf;
use Dompdf\Options;

class IntegridadMecanicaController extends BaseController{

protected string $modulo = 'sasisopa';

    public function index(){
        $title = '11. INTEGRIDAD MECÁNICA Y ASEGURAMIENTO DE LA CALIDAD';

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
                    '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
                ],
                'scripts' => [
                    '/js/vendor.min.js',
                    '/libs/datatables.net/js/jquery.dataTables.min.js',
                    '/js/integridadmecanica/equipocritico.datatable.init.js?v=' . time(),
                    '/js/integridadmecanica/equipocritico.action.init.js?v=' . time(),
                ],
                'help' => true
            ];
            
            View::render('integridadmecanica/index', $data,'sasisopa');
    }

    public function datatableEquipoCritico(){
        $data = EquipoCritico::where('id_estacion',$this->estacionId())
        ->where('estado',1)
        ->get();

         echo json_encode([
            "data" => $data,
            'permisos' => [
                'eliminar' =>ModuloService::validaPermiso($this->modulo,'eliminar')
            ]
        ]);
        
        exit;
    }

    public function createEquipoCritico(){

       header('Content-Type: application/json');

        if (!ModuloService::validaPermiso($this->modulo, 'crear')) {

            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear'
            ]);

            return;
        }

try {
            
        
            $nombre = sanitize_input($_POST['nombre_equipo'] ?? null, 'string');
            $marca = sanitize_input($_POST['marca_modelo'] ?? null, 'string');
            $funciones = sanitize_input($_POST['funciones'] ?? null, 'string');
            $fecha = sanitize_input($_POST['fecha_instalacion'] ?? null, 'string');
            $vida = sanitize_input($_POST['tiempo_vida'] ?? null, 'string');

            if (
                empty($nombre) ||
                empty($marca) ||
                empty($funciones) ||
                empty($fecha) ||
                empty($vida)
            ) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Completa todos los campos'
                ]);

                return;
            }

            if (
                !isset($_FILES['manual']) ||
                $_FILES['manual']['error'] !== UPLOAD_ERR_OK
            ) {

                echo json_encode([
                    'success' => false,
                    'message' => 'El manual PDF es obligatorio'
                ]);

                return;
            }

            $archivo = $_FILES['manual'];

            $extension = strtolower(
                pathinfo($archivo['name'], PATHINFO_EXTENSION)
            );

            if ($extension !== 'pdf') {

                echo json_encode([
                    'success' => false,
                    'message' => 'Solo se permiten archivos PDF'
                ]);

                return;
            }

      
            $ruta = __DIR__ . '../../../public/uploads/archivos/manuales/';
            $nombreArchivo = 'MANUAL-EQUIPO-' .uniqid() . '.pdf';

            // SECURITY: BAJO #35 - Usar mkdir_safe con permisos 0755
            if (!is_dir($ruta)) {
                mkdir_safe($ruta, true);
            }

            move_uploaded_file(
                $archivo['tmp_name'],
                $ruta . $nombreArchivo
            );

            $ultimo = EquipoCritico::where(
                'id_estacion',
                $this->estacionId()
            )->max('id_equipo');

            EquipoCritico::create([
                'id_estacion' => $this->estacionId(),
                'id_equipo' => ($ultimo ?? 0) + 1,
                'nombre_equipo' => $nombre,
                'marca_modelo' => $marca,
                'funciones' => $funciones,
                'fecha_instalacion' => $fecha,
                'tiempo_vida' => $vida,
                'manual' => 'archivos/manuales/' . $nombreArchivo,
                'estado' => 1
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Equipo crítico agregado correctamente'
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => 'Error al guardar'
            ]);
        }

    }

    public function deleteEquipoCritico()
    {
        header('Content-Type: application/json');

        try {

            $data = json_decode(
                file_get_contents('php://input'),
                true
            );

            $registro = EquipoCritico::where(
                'id_estacion',
                $this->estacionId()
            )->find($data['id']);

            if (!$registro) {

                echo json_encode([
                    'success' => false,
                    'message' => 'No se encontró el registro'
                ]);

                return;
            }

            $registro->estado = 3;
            $registro->save();

            echo json_encode([
                'success' => true,
                'message' => 'Equipo crítico eliminado correctamente'
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => 'Error al eliminar'
            ]);
        }
    }

    public function bajaEquipoCritico()
    {
        header('Content-Type: application/json');

        try {

            $data = json_decode(
                file_get_contents('php://input'),
                true
            );

            $registro = EquipoCritico::where(
                'id_estacion',
                $this->estacionId()
            )->find($data['id']);

            if (!$registro) {

                echo json_encode([
                    'success' => false,
                    'message' => 'No se encontró el registro'
                ]);

                return;
            }

            $registro->estado = 2;
            $registro->save();

            echo json_encode([
                'success' => true,
                'message' => 'Equipo crítico fue dado de baja correctamente'
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => 'Error al dar de baja'
            ]);
        }
    }

    public function pdfEquipoCritico(){

    $estacion = Estacion::find(
            $this->estacionId()
        );

        if (!$estacion) {
            return 'No se encontró información';
        }

        $equipos = EquipoCritico::where(
            'id_estacion',
            $this->estacionId()
        )
        ->where('estado',1)
        ->orderByDesc('id_equipo')
        ->get();

        $logo = $_ENV['APP_URL'] .
            '/assets/images/logos/Logo.png';

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset="UTF-8">
        <title>Equipos críticos</title>

        <link rel="stylesheet"
        href="'.$_ENV['APP_URL'].'/assets/css/pdf.css">

        </head>
        <body class="fs-6">

        <div class="text-center">
            <img src="'.$logo.'" style="width:250px;">
        </div>

        <div class="text-center mt-3">
            '.$estacion->permisocre.'
        </div>

        <div class="text-center mt-1">
            '.$estacion->razonsocial.'
        </div>

        <div class="text-center mt-1">
            '.$estacion->direccioncompleta.'
        </div>

         <div class="text-center mt-1">
            '.$estacion->apoderado_legal.'
        </div>

        <h1 class="text-center mt-4">
            Lista de equipos críticos
        </h1>

        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nombre equipo</th>
                    <th>Marca y modelo</th>
                    <th>Función</th>
                    <th>Fecha instalación</th>
                    <th>Tiempo de vida</th>
                </tr>
            </thead>
            <tbody>
        ';

        if ($equipos->count() > 0) {

            foreach ($equipos as $item) {

                $html .= '
                <tr>
                    <td>'.$item->id_equipo.'</td>
                    <td>'.$item->nombre_equipo.'</td>
                    <td>'.$item->marca_modelo.'</td>
                    <td>'.$item->funciones.'</td>
                    <td>'.formatearFecha($item->fecha_instalacion).'</td>
                    <td>'.$item->tiempo_vida.' años</td>
                </tr>
                ';
            }

        } else {

            $html .= '
            <tr>
                <td colspan="6" class="text-center">
                    No hay registros disponibles
                </td>
            </tr>
            ';
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
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        return $dompdf->stream(
            'Lista-equipos-criticos.pdf',
            ['Attachment' => true]
        );
    }

    public function bitacoras(){

    $title = 'Bitacoras';

            Breadcrumb::add('Home', '/home');
            Breadcrumb::add('SASISOPA', '/sasisopa');
            Breadcrumb::add('11. INTEGRIDAD MECÁNICA Y ASEGURAMIENTO DE LA CALIDAD', '/sasisopa/integridad-mecanica-aseguramiento');
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
                    '/js/vendor.min.js',
                ],
                'help' => false
            ];
            
            View::render('integridadmecanica/bitacoras', $data,'sasisopa');

    }
    

}