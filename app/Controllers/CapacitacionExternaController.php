<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Sasisopa\CapacitacionExterna;
use App\Models\Sasisopa\CapacitacionExternaPersonal;
use Illuminate\Database\Capsule\Manager as Capsule;
use Dompdf\Dompdf;
use Dompdf\Options;

class CapacitacionExternaController extends BaseController{

 protected string $modulo = 'sasisopa';

    public function index(){

        $title = 'Programa de capacitación externa';
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
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/capacitacionexterna/index.datatable.init.js?v=1.3',
                '/js/capacitacionexterna/index.init.js?v=1.1'
            ],
            'help' => false
        ];
        
        View::render('capacitacionexterna/index', $data,'sasisopa');

    }

    public function datatableCapacitacionExterna(){

        // permisos
        $permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');
        $permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');
        $permisoDescargar = ModuloService::validaPermiso($this->modulo, 'descargar');

         $capacitacion = CapacitacionExterna::
         where('id_estacion', $this->estacionId())
         ->get();

         echo json_encode([
            "data" => $capacitacion,
            "permisos" => [
                "eliminar" => $permisoEliminar,
                "editar"   => $permisoEditar,
                "descargar" => $permisoDescargar
            ]
        ]);
        
        exit;
    }

    public function createCapacitacionExterna()
{
    header('Content-Type: application/json; charset=utf-8');

    try {

$data = json_decode(file_get_contents('php://input'), true);

        
        $curso = sanitize_input($data['curso'] ?? null, 'string');
        $fecha_programada = sanitize_input($data['fecha_programada'] ?? null, 'string');
        $duracion = sanitize_input($data['duracion'] ?? null, 'string');
        $duraciondetalle = sanitize_input($data['duraciondetalle'] ?? null, 'string');
        $instructor = sanitize_input($data['instructor'] ?? null, 'string');

        if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear'
            ]);
            return;
        }

        if (empty($curso) || empty($fecha_programada)) {
            echo json_encode([
                'success' => false,
                'message' => 'Completa todos los campos obligatorios'
            ]);
            return;
        }

        $id_estacion = $this->estacionId();
        $id_usuario = $this->userId();


        Capsule::beginTransaction();

        $capacitacion = CapacitacionExterna::create([
            'id_estacion'       => $id_estacion,
            'id_usuario'        => $id_usuario,
            'curso'             => $curso,
            'fecha_programada'  => $fecha_programada,
            'duracion'          => $duracion,
            'duraciondetalle'   => $duraciondetalle,
            'instructor'        => $instructor,
            'fecha_real'        => ''
        ]);
      
        
        if (empty($curso) || empty($fecha_programada)) {
            echo json_encode([
                'success' => false,
                'message' => 'Completa todos los campos obligatorios'
            ]);
            return;
        }

        $id_estacion = $this->estacionId();
        $id_usuario = $this->userId();


        Capsule::beginTransaction();

        $capacitacion = CapacitacionExterna::create([
            'id_estacion'       => $id_estacion,
            'id_usuario'        => $id_usuario,
            'curso'             => $curso,
            'fecha_programada'  => $fecha_programada,
            'duracion'          => $duracion,
            'duraciondetalle'   => $duraciondetalle,
            'instructor'        => $instructor,
            'fecha_real'        => ''
        ]);

  
        $usuarios = Usuario::activo()
            ->where('id_gas', $id_estacion)
            ->pluck('id');


        $insert = [];
        foreach ($usuarios as $id_empleado) {
            $insert[] = [
                'id_capacitacion' => $capacitacion->id,
                'id_empleado'     => $id_empleado
            ];
        }

        if (!empty($insert)) {
            CapacitacionExternaPersonal::insert($insert);
        }

        Capsule::commit();

        echo json_encode([
            'success' => true,
            'message' => 'Capacitación externa creada correctamente'
        ]);


    } catch (\Throwable $e) {

        Capsule::rollBack();

        echo json_encode([
            'success' => false,
            'message' => 'Error al guardar la capacitación'
        ]);
    }
    }


    public function deleteCapacitacionExterna(){

       header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;

        try {

            if (!$id) {
                echo json_encode([
                    'success' => false,
                    'message' => 'ID requerido'
                ]);
                return;
            }


            if (!ModuloService::validaPermiso($this->modulo, 'eliminar')) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No tienes permiso para eliminar'
                ]);
                return;
            }


             Capsule::beginTransaction();

            $cap = CapacitacionExterna::find($id);
            $capPersonal = CapacitacionExternaPersonal::where('id_capacitacion', $id);


            if (!$cap) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Registro no encontrado'
                ]);
                return;
            }

            $cap->delete();
            $capPersonal->delete();

            Capsule::commit();

            echo json_encode([
                'success' => true,
                'message' => 'Registro eliminado correctamente'
            ]);

        } catch (\Throwable $e) {

            Capsule::rollBack();

            echo json_encode([
                'success' => false,
                'message' => 'Error al eliminar'
            ]);
        }

    }

    public function updateCapacitacionExterna(int $id)
    {
        header('Content-Type: application/json; charset=utf-8');

        try {

            $data = json_decode(file_get_contents('php://input'), true);

            $curso = $data['curso'] ?? null;
            $fecha_programada = $data['fecha_programada'] ?? null;
            $duracion = $data['duracion'] ?? null;
            $duraciondetalle = $data['duraciondetalle'] ?? null;
            $instructor = $data['instructor'] ?? null;
            $fecha_real = $data['fecha_real'] ?? null;

            if (!ModuloService::validaPermiso($this->modulo, 'editar')) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No tienes permiso para editar'
                ]);
                return;
            }

            if (empty($curso) || empty($fecha_programada)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Completa los campos obligatorios'
                ]);
                return;
            }

            $cap = CapacitacionExterna::find($id);

            if (!$cap) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Registro no encontrado'
                ]);
                return;
            }

            $cap->update([
                'curso' => $curso,
                'fecha_programada' => $fecha_programada,
                'duracion' => $duracion,
                'duraciondetalle' => $duraciondetalle,
                'instructor' => $instructor,
                'fecha_real' => $fecha_real ?: null 
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Registro actualizado correctamente'
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => 'Error al actualizar'
            ]);
        }
    }

    public function getPersonal(int $id)
    {
        header('Content-Type: application/json');

        $id_estacion = $this->estacionId();

        $personal = CapacitacionExternaPersonal::with('usuario')
            ->where('id_capacitacion', $id)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'nombre' => $p->usuario->nombre ?? ''
            ]);

        $usuarios = Usuario::activo()
            ->where('id_gas', $id_estacion)
            ->whereNotIn('id', function($q) use ($id) {
                $q->select('id_empleado')
                ->from('tb_capacitacion_externa_personal')
                ->where('id_capacitacion', $id);
            })
            ->get(['id','nombre']);

        echo json_encode([
            'personal' => $personal,
            'usuarios' => $usuarios
        ]);
    }

    public function createPersonal()
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);

        $id_capacitacion = $data['id_capacitacion'] ?? null;
        $id_empleado = $data['id_empleado'] ?? null;

          if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear'
            ]);
            return;
        }

        if (!$id_capacitacion || !$id_empleado) {
            echo json_encode([
                'success' => false,
                'message'=> 'Completa todos los campos obligatorios'
                ]);
            return;
        }

        CapacitacionExternaPersonal::create([
            'id_capacitacion' => $id_capacitacion,
            'id_empleado' => $id_empleado
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Trabajador agregado correctamente'
            ]);
    }

    public function deletePersonal()
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);

        $id = $data['id'] ?? null;

        if (!$id) {
            echo json_encode(['success' => false]);
            return;
        }

        CapacitacionExternaPersonal::where('id', $id)->delete();

        echo json_encode([
            'success' => true,
            'message' => 'Trabajador eliminado correctamente']);
    }

    public function pdfCapacitacionExterna(int $id)
    {
        $idEstacion = $this->estacionId();

        $registro = Estacion::find($idEstacion);

        if (!$registro) {
            return "No se encontró la información";
        }

        $capacitacion = CapacitacionExterna::with(['personal.usuario'])
            ->where('id_estacion', $idEstacion)
            ->find($id);

        if (!$capacitacion) {
            return "Capacitación no encontrada";
        }

        $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';
        $apoderadolegal = $registro->apoderado_legal;

        $rows = '';
        $i = 1;

        foreach ($capacitacion->personal as $p) {

            $nombre = $p->usuario->nombre ?? 'S/I';

            $rows .= '
            <tr>
                <td class="text-center">'.$i.'</td>
                <td>'.$nombre.'</td>
                <td>'.$capacitacion->curso.'</td>
                <td>'.formatearFecha($capacitacion->fecha_programada).'</td>
                <td>'.$capacitacion->duracion.' '.$capacitacion->duraciondetalle.'</td>
                <td>'.$capacitacion->instructor.'</td>
                <td></td>
                <td class="text-center">X</td>
            </tr>
            ';

            $i++;
        }

        if ($rows === '') {
            $rows = '
            <tr>
                <td colspan="8" class="text-center">No hay trabajadores asignados</td>
            </tr>';
        }

        // ======================
        // HTML
        // ======================
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset="UTF-8">
        <title>Capacitación externa</title>
        <link rel="stylesheet" href="'.$_ENV['APP_URL'].'/assets/css/pdf.css">
        </head>
        <body>

        <table class="table">
            <tr>
                <td class="text-center">
                    <img src="'.$logo.'" style="width:130px">
                </td>
                <td colspan="2" class="text-center">
                    <b>Programa de Capacitación y adiestramiento</b>
                </td>
                <td class="text-center"><b>Fo.ADMONGAS.009</b></td>
            </tr>
            <tr>
                <td class="text-center">Realizado por:<br>Nelly Estrada Garcia</td>
                <td class="text-center">Revisado por:<br>Eduardo Galicia Flores</td>
                <td class="text-center">Autorizado por:<br>'.$apoderadolegal.'</td>
                <td class="text-center">Fecha de aprobación:<br>01/10/2018</td>
            </tr>
        </table>

        <table class="table table-bordered" style="font-size:12px;">
            <thead>
                <tr>
                    <th rowspan="2">No.</th>
                    <th rowspan="2">Nombre del trabajador</th>
                    <th rowspan="2">Nombre del Curso</th>
                    <th rowspan="2">Fecha Programada</th>
                    <th rowspan="2">Duración</th>
                    <th rowspan="2">Nombre del instructor</th>
                    <th colspan="2">Tipo de instructor</th>
                </tr>
                <tr>
                    <th>Interno</th>
                    <th>Externo</th>
                </tr>
            </thead>
            <tbody>
                '.$rows.'
            </tbody>
        </table>

        </body>
        </html>
        ';

        // ======================
        // PDF
        // ======================
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape'); 
        $dompdf->render();

        return $dompdf->stream(
            "Capacitacion-externa.pdf",
            ["Attachment" => true]
        );
    }

        public function pdfCapacitacionExternaCompleto()
    {
        $inicio = $_GET['inicio'] ?? null;
        $fin    = $_GET['fin'] ?? null;

        $idEstacion = $this->estacionId();

        $estacion = Estacion::find($idEstacion);

        if (!$estacion) {
            exit('No se encontró la estación.');
        }

        $query = CapacitacionExterna::with(['personal.usuario'])
            ->where('id_estacion', $idEstacion);

        if (!empty($inicio) && !empty($fin)) {
            $query->whereBetween('fecha_programada', [
                $inicio,
                $fin
            ]);
        }

        $capacitaciones = $query
            ->orderBy('fecha_programada')
            ->get();

        $logo = $_ENV['APP_URL'].'/assets/images/logos/Logo.png';

        $rows = '';

        foreach ($capacitaciones as $capacitacion) {

            $rows .= '
            <tr>
                <td colspan="8"
                    style="background:#e9ecef;font-weight:bold;">
                    '.$capacitacion->curso.'
                </td>
            </tr>';

            $i = 1;

            foreach ($capacitacion->personal as $p) {

                $rows .= '
                <tr>
                    <td class="text-center">'.$i.'</td>
                    <td>'.($p->usuario->nombre ?? 'S/I').'</td>
                    <td>'.$capacitacion->curso.'</td>
                    <td>'.formatearFecha($capacitacion->fecha_programada).'</td>
                    <td>'.$capacitacion->duracion.' '.$capacitacion->duraciondetalle.'</td>
                    <td>'.$capacitacion->instructor.'</td>
                    <td></td>
                    <td class="text-center">X</td>
                </tr>';

                $i++;
            }

            if ($capacitacion->personal->isEmpty()) {

                $rows .= '
                <tr>
                    <td colspan="8" class="text-center">
                        No hay trabajadores asignados
                    </td>
                </tr>';

            }

        }

        if ($rows === '') {

            $rows = '
            <tr>
                <td colspan="8" class="text-center">
                    No se encontró información para mostrar
                </td>
            </tr>';

        }

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset="UTF-8">
        <title>Programa de Capacitación y adiestramiento</title>
        <link rel="stylesheet" href="'.$_ENV['APP_URL'].'/assets/css/pdf.css">
        </head>
        <body>

        <table class="table">
            <tr>
                <td class="text-center">
                    <img src="'.$logo.'" style="width:130px">
                </td>
                <td colspan="2" class="text-center">
                    <b>Programa de Capacitación y adiestramiento</b>
                </td>
                <td class="text-center">
                    <b>Fo.ADMONGAS.009</b>
                </td>
            </tr>

            <tr>
                <td class="text-center">
                    Realizado por:<br>Nelly Estrada Garcia
                </td>

                <td class="text-center">
                    Revisado por:<br>Eduardo Galicia Flores
                </td>

                <td class="text-center">
                    Autorizado por:<br>'.$estacion->apoderado_legal.'
                </td>

                <td class="text-center">
                    Fecha de aprobación:<br>01/10/2018
                </td>
            </tr>

        </table>

        <table class="table table-bordered" style="font-size:12px;">

            <thead>

                <tr>

                    <th rowspan="2">No.</th>

                    <th rowspan="2">Nombre del trabajador</th>

                    <th rowspan="2">Nombre del Curso</th>

                    <th rowspan="2">Fecha Programada</th>

                    <th rowspan="2">Duración</th>

                    <th rowspan="2">Nombre del instructor</th>

                    <th colspan="2">Tipo de instructor</th>

                </tr>

                <tr>

                    <th>Interno</th>

                    <th>Externo</th>

                </tr>

            </thead>

            <tbody>

                '.$rows.'

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

        $dompdf->stream(
            'Programa-Capacitacion-Adiestramiento.pdf',
            ['Attachment' => true]
        );
    }


}