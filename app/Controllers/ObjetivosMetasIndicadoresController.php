<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Estacion;
use App\Models\Sasisopa\SeguimientoReporteIndicador;
use App\Models\Sasisopa\SeguimientoObjetivosMetas;
use App\Models\Sasisopa\SeguimientoObjetivosMetasDetalle;
use App\Models\Sasisopa\ReporteCreMes;
use App\Models\Sasisopa\Encuestas;
use App\Models\Sasisopa\EncuentaCuestionario;
use App\Models\Sasisopa\EncuentaEstacion;
use App\Models\Sasisopa\EncuentasEstacionCliente;
use App\Models\Sasisopa\EncuentasEstacionClienteComentarios;
use App\Models\Sasisopa\EncuentasEstacionClientePreguntas;
use App\Services\CapacitacionService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Dompdf\Dompdf;
use Dompdf\Options;

class ObjetivosMetasIndicadoresController extends BaseController{
    protected string $modulo = 'sasisopa';

    public function index(){

        $title = '4. OBJETIVOS, METAS E INDICADORES';
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
                '/js/objetivosmetasindicadores/seguimientoindicadores.datatable.init.js?v=1.1',
                '/js/objetivosmetasindicadores/seguimientoobjetivosmetas.datatable.init.js?v=1.1',
                '/js/objetivosmetasindicadores/seguimientoobjetivosmetas.actions.init.js?v=1.1'
            ],
            'help' => true
        ];
        
        View::render('objetivosmetasindicadores/index', $data,'sasisopa');

    }

    public function datatableSeguimientoIndicadores(){

        $permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');
        $permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');
        $permisoDescargar = ModuloService::validaPermiso($this->modulo, 'descargar');

        $data = SeguimientoReporteIndicador::where('id_estacion', $this->estacionId())
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
    public function datatableSeguimientoObjetivosMetas(){
        $permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');
        $permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');
        $permisoDescargar = ModuloService::validaPermiso($this->modulo, 'descargar');

        $rows = SeguimientoObjetivosMetas::where('id_estacion',$this->estacionId())
        ->orderBy('fecha')
        ->get();

        $data = [];

        foreach ($rows as $row) {

            $detalles = SeguimientoObjetivosMetasDetalle::where('id_seguimiento', $row->id)->get();

            $completo = true;

            foreach ($detalles as $d) {

                if (
                    empty($d->fecha) ||
                    empty($d->nivel_cumplimiento) ||
                    empty($d->medidas) ||
                    empty($d->fecha_aplicacion)
                ) {
                    $completo = false;
                    break;
                }
            }

            $estatus = [
                "titulo" => '',
                "color_css" => '',
                "color_hexa" => ''
            ];

            if ($detalles->isEmpty()) {

                $estatus = [
                    "titulo" => 'Sin registros',
                    "color_css" => 'bg-secondary',
                    "color_hexa" => '#6c757d'
                ];

            } elseif ($completo) {

                $estatus = [
                    "titulo" => 'Finalizado',
                    "color_css" => 'bg-success',
                    "color_hexa" => '#198754'
                ];

            } else {

                $estatus = [
                    "titulo" => 'Incompleto',
                    "color_css" => 'badge bg-warning',
                    "color_hexa" => '#ffc107'
                ];
            }

            $data[] = [
                "id" => $row->id,
                "fecha" => $row->fecha,
                "estatus" => $estatus
            ];
        }

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
    public function createObjetivosMetas(){

        header('Content-Type: application/json; charset=utf-8');
        $input = json_decode(file_get_contents('php://input'), true);

        if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear'
            ]);
            return;
        }

        try {

        Capsule::beginTransaction();

        $json = $input['data'] ?? null;

        if (!$json) {
            echo json_encode([
                'success' => false,
                'message' => 'No llegaron datos'
            ]);
            exit;
        }

        $idEstacion = $this->estacionId();
        $idUsuario  = $this->userId();

        $seguimiento = SeguimientoObjetivosMetas::create([
            'id_estacion' => $idEstacion,
            'id_usuario'  => $idUsuario
        ]);

        $idSeguimiento = $seguimiento->id;

        $map = [
            'satisfaccion' => 'Satisfacción del cliente',
            'mantenimiento' => 'Mantenimiento',
            'capacitacion' => 'Capacitación',
            'quejas' => 'Quejas y sugerencias',
            'legislacion' => 'Cumplimiento de legislación'
        ];

        $insert = [];

        foreach ($map as $key => $nombre) {

            $item = $json[$key] ?? null;

            if (!$item) continue;

            $fecha = !empty($item['fecha']) ? $item['fecha'] : null;
            $fechaAplicacion = !empty($item['fecha_aplicacion']) ? $item['fecha_aplicacion'] : null;

            $insert[] = [
                'id_seguimiento'       => $idSeguimiento,
                'fecha'               => $fecha,
                'objetivo_meta'       => $nombre,
                'nivel_cumplimiento'  => $item['cumplimiento'] ?? '',
                'medidas'             => $item['accion'] ?? '',
                'fecha_aplicacion'    => $fechaAplicacion
            ];
        }

        // Insert masivo
        SeguimientoObjetivosMetasDetalle::insert($insert);

        Capsule::commit();

        echo json_encode([
            'success' => true,
            'message' => 'Registro guardado correctamente'
        ]);

    } catch (\Throwable $e) {

        Capsule::rollBack();

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }

    }

    public function getObjetivosMetas($id){

        header('Content-Type: application/json; charset=utf-8');

        try {

            // Obtener detalle
            $rows = SeguimientoObjetivosMetasDetalle::where('id_seguimiento', $id)->get();

            if ($rows->isEmpty()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No se encontró información'
                ]);
                return;
            }

            // Estructura base (igual a Alpine)
            $objetivos = [
                "satisfaccion" => [
                    "fecha" => "",
                    "cumplimiento" => "",
                    "accion" => "",
                    "fecha_aplicacion" => ""
                ],
                "mantenimiento" => [
                    "fecha" => "",
                    "cumplimiento" => "",
                    "accion" => "",
                    "fecha_aplicacion" => ""
                ],
                "capacitacion" => [
                    "fecha" => "",
                    "cumplimiento" => "",
                    "accion" => "",
                    "fecha_aplicacion" => ""
                ],
                "quejas" => [
                    "fecha" => "",
                    "cumplimiento" => "",
                    "accion" => "",
                    "fecha_aplicacion" => ""
                ],
                "legislacion" => [
                    "fecha" => "",
                    "cumplimiento" => "",
                    "accion" => "",
                    "fecha_aplicacion" => ""
                ]
            ];

            // Mapeo BD → Alpine
            $map = [
                'Satisfacción del cliente' => 'satisfaccion',
                'Mantenimiento' => 'mantenimiento',
                'Capacitación' => 'capacitacion',
                'Quejas y sugerencias' => 'quejas',
                'Cumplimiento de legislación' => 'legislacion'
            ];

            foreach ($rows as $row) {

                $key = $map[$row->objetivo_meta] ?? null;

                if (!$key) continue;

                $objetivos[$key] = [
                    "fecha" => formatDate($row->fecha),
                    "fecha_formateada" => formatearFecha($row->fecha),

                    "objetivo_meta" => $row->objetivo_meta,
                    "cumplimiento" => $row->nivel_cumplimiento,
                    "accion" => $row->medidas,

                    "fecha_aplicacion" => formatDate($row->fecha_aplicacion),
                    "fecha_aplicacion_formateada" => formatearFecha($row->fecha_aplicacion)
                ];
            }

            echo json_encode([
                'success' => true,
                'objetivos' => $objetivos
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function updateObjetivosMetas($id)
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!ModuloService::validaPermiso($this->modulo, 'editar')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para editar'
            ]);
            return;
        }

        try {

            Capsule::beginTransaction();
            $input = json_decode(file_get_contents("php://input"), true);

            if (!$input || !isset($input['data'])) {
                throw new \Exception('Datos inválidos');
            }

            $json = $input['data'];

            $seguimiento = SeguimientoObjetivosMetas::find($id);

            if (!$seguimiento) {
                throw new \Exception('Registro no encontrado');
            }

            SeguimientoObjetivosMetasDetalle::where('id_seguimiento', $id)->delete();

            $map = [
                'satisfaccion' => 'Satisfacción del cliente',
                'mantenimiento' => 'Mantenimiento',
                'capacitacion' => 'Capacitación',
                'quejas' => 'Quejas y sugerencias',
                'legislacion' => 'Cumplimiento de legislación'
            ];

            $insert = [];

            foreach ($map as $key => $nombre) {

                $item = $json[$key] ?? null;

                if (!$item) continue;

                $insert[] = [
                    'id_seguimiento'       => $id,
                    'fecha'               => !empty($item['fecha']) ? $item['fecha'] : null,
                    'objetivo_meta'       => $nombre,
                    'nivel_cumplimiento'  => $item['cumplimiento'] ?? '',
                    'medidas'             => $item['accion'] ?? '',
                    'fecha_aplicacion'    => !empty($item['fecha_aplicacion']) ? $item['fecha_aplicacion'] : null
                ];
            }

            if (!empty($insert)) {
                SeguimientoObjetivosMetasDetalle::insert($insert);
            }

            Capsule::commit();

            echo json_encode([
                'success' => true,
                'message' => 'Registro actualizado correctamente'
            ]);

        } catch (\Throwable $e) {

            Capsule::rollBack();

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function deleteObjetivosMetas(){

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

            try {

            Capsule::beginTransaction();
            SeguimientoObjetivosMetasDetalle::where('id_seguimiento', $id)->delete();

            $deleted = SeguimientoObjetivosMetas::where('id', $id)->delete();

            if (!$deleted) {
                throw new \Exception('No se encontró el registro');
            }

            Capsule::commit();

            echo json_encode([
                'success' => true,
                'message' => 'Registro eliminado correctamente'
            ]);

        } catch (\Throwable $e) {

            Capsule::rollBack();

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

    }

    public function pdfObjetivosMetas(){

        $estacion = Estacion::find($this->estacionId());
        $apoderado = htmlspecialchars($estacion->apoderado_legal ?? '');

        $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

        $detalles = SeguimientoObjetivosMetasDetalle::whereHas('seguimiento', function($q){
            $q->where('id_estacion', $this->estacionId());
        })
        ->orderBy('id_seguimiento', 'desc')
        ->get();

        $detallesAgrupados = $detalles->groupBy('id_seguimiento');
        $rows = '';

        foreach ($detallesAgrupados as $idSeguimiento => $items) {

        $rows .= '
        <tr>
            <td colspan="5" style="background:#d9d9d9; font-weight:bold;">
                Seguimiento de objetivos y metas No.'.$idSeguimiento.'
            </td>
        </tr>';

        foreach ($items as $row) {

            $rows .= '
            <tr>
                <td class="text-center">'.formatearFecha($row->fecha).'</td>
                <td class="text-center">'.$row->objetivo_meta.'</td>
                <td class="text-center">'.$row->nivel_cumplimiento.'</td>
                <td class="text-center">'.$row->medidas.'</td>
                <td class="text-center">'.formatearFecha($row->fecha_aplicacion).'</td>
            </tr>';
        }

    }

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Seguimiento de objetivos y metas</title>
            <link rel="stylesheet" href="'.$_ENV['APP_URL'].'/assets/css/pdf.css">
        </head>
        <body>

        <table class="table table-bordered table-sm fs-6">
            <tr>
                <td class="text-center align-middle">
                    <img src="'.$logo.'" style="width:120px;">
                </td>
                <td class="text-center align-middle" colspan="2">
                    <b>Seguimiento de objetivos y metas</b>
                </td>
                <td class="text-center align-middle">
                    <b>Fo.ADMONGAS.006</b>
                </td>
            </tr>
            <tr>
                <td class="text-center">Realizado por:<br> Nelly Estrada Garcia</td>
                <td class="text-center">Revisado por:<br> Eduardo Galicia Flores</td>
                <td class="text-center">Autorizado por:<br> '.$apoderado.'</td>
                <td class="text-center">Fecha de aprobación:<br> 01/10/2018</td>
            </tr>
        </table>

        <table class="table table-bordered table-sm fs-6">
            <thead>
                <tr>
                    <th class="text-center">Fecha</th>
                    <th class="text-center">Objetivo o meta</th>
                    <th class="text-center">Nivel de cumplimiento</th>
                    <th class="text-center">Medidas</th>
                    <th class="text-center">Fecha aplicación</th>
                </tr>
            </thead>
            <tbody>
                '.$rows.'
            </tbody>
        </table>

        </body>
        </html>
        ';

        $options = new Options();
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $dompdf->stream("Seguimiento-objetivos-metas.pdf", ["Attachment" => true]);
    }

    public function createReporteIndicadores(){

        header('Content-Type: application/json; charset=utf-8');
        $json = json_decode(file_get_contents('php://input'), true);
        $data = $json['data'] ?? null;

        if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear'
            ]);
            return;
        }

         if (!$data['fecha'] && !$data['capacitacion'] && !$data['experiencia']
                && !$data['ventas'] && !$data['medidas'] && !$data['fecha_aplicacion']) {
            echo json_encode([
                'success' => false,
                'message' => 'Faltan campos obligatorios'
            ]);
            exit;
        }

        try {

            Capsule::beginTransaction();

            SeguimientoReporteIndicador::create([
                'id_estacion' => $this->estacionId(),
                'id_usuario'  => $this->userId(),
                'fecha'       => $data['fecha'],
                'capacitacion'=> $data['capacitacion'],
                'exp_cliente' => $data['experiencia'],
                'ventas'      => $data['ventas'],
                'medidas_correctivas'     => $data['medidas'],
                'fecha_aplicacion' => $data['fecha_aplicacion']
            ]);

            Capsule::commit();

            echo json_encode([
                'success' => true,
                'message' => 'Registro guardado correctamente'
            ]);

        } catch (\Throwable $e) {

            Capsule::rollBack();

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function updateReporteIndicadores($id){

        header('Content-Type: application/json; charset=utf-8');

        if (!ModuloService::validaPermiso($this->modulo, 'editar')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para editar'
            ]);
            return;
        }

        try {

            $data = json_decode(file_get_contents('php://input'), true);
            $payload = $data['data'] ?? null;

            if (!$payload) {
                throw new \Exception('Payload vacío');
            }

            if (
                empty($payload['fecha']) ||
                empty($payload['capacitacion']) ||
                empty($payload['experiencia']) ||
                empty($payload['ventas']) ||
                empty($payload['medidas']) ||
                empty($payload['fecha_aplicacion'])
            ) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Faltan campos obligatorios'
                ]);
                return;
            }

            Capsule::beginTransaction();

            $registro = SeguimientoReporteIndicador::find($id);

            if (!$registro) {
                throw new \Exception('Registro no encontrado');
            }

            $registro->update([
                'fecha' => $payload['fecha'] ?: null,
                'capacitacion' => $payload['capacitacion'],
                'exp_cliente' => $payload['experiencia'],
                'ventas' => $payload['ventas'],
                'medidas_correctivas' => $payload['medidas'],
                'fecha_aplicacion' => $payload['fecha_aplicacion'] ?: null
            ]);

            Capsule::commit();

            echo json_encode([
                'success' => true,
                'message' => 'Registro actualizado correctamente'
            ]);

        } catch (\Throwable $e) {

            Capsule::rollBack();

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function getReporteIndicadores($id){

        header('Content-Type: application/json; charset=utf-8');

        try {

            $row = SeguimientoReporteIndicador::find($id);

            if (!$row) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No se encontró información'
                ]);
                return;
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'fecha' => formatDate($row->fecha),
                    'fecha_format' => formatearFecha($row->fecha),
                    'capacitacion' => $row->capacitacion ?? '',
                    'experiencia' => $row->exp_cliente ?? '',
                    'ventas' => $row->ventas ?? '',
                    'medidas' => $row->medidas_correctivas ?? '',
                    'fecha_aplicacion' => formatDate($row->fecha_aplicacion),
                    'fecha_aplicacion_format' => formatearFecha($row->fecha_aplicacion)
                ]
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function deleteReporteIndicadores(){

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

            try {

            Capsule::beginTransaction();
 
            $deleted = SeguimientoReporteIndicador::where('id', $id)->delete();

            if (!$deleted) {
                throw new \Exception('No se encontró el registro');
            }

            Capsule::commit();

            echo json_encode([
                'success' => true,
                'message' => 'Registro eliminado correctamente'
            ]);

        } catch (\Throwable $e) {

            Capsule::rollBack();

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

    }

    public function pdfReporteIndicadores(){

        $estacion = Estacion::find($this->estacionId());
        $apoderado = htmlspecialchars($estacion->apoderado_legal ?? '');

        $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

        // Obtener datos
        $data = SeguimientoReporteIndicador::where('id_estacion', $this->estacionId())
            ->orderBy('fecha', 'desc')
            ->get();

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Seguimiento y reporte de indicadores</title>
            <link rel="stylesheet" href="'.$_ENV['APP_URL'].'/assets/css/pdf.css">
        </head>
        <body>

        <table class="table table-bordered fs-6">
            <tbody>
                <tr>
                    <td class="align-middle text-center">
                        <img src="'.$logo.'" style="width:130px;">
                    </td>
                    <td colspan="2" class="align-middle text-center">
                        <b>Seguimiento y reporte de indicadores</b>
                    </td>
                    <td class="align-middle text-center">
                        <b>Fo.ADMONGAS.007</b>
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
                        Fecha de aprobación:<br> 01/10/2018
                    </td>
                </tr>
            </tbody>
        </table>

        <table class="table table-bordered table-sm fs-6">
            <thead>
                <tr>
                    <th class="align-middle">Fecha</th>
                    <th class="align-middle">Capacitación</th>
                    <th class="align-middle">Experiencia del cliente</th>
                    <th class="align-middle">Ventas</th>
                    <th class="align-middle">Medidas correctivas</th>
                    <th class="align-middle">Fecha de aplicación</th>
                </tr>
            </thead>
            <tbody>
        ';

        foreach ($data as $row) {

            $html .= '
            <tr>
                <td class="align-middle">'.formatearFecha($row->fecha).'</td>
                <td class="align-middle">'.htmlspecialchars($row->capacitacion).'</td>
                <td class="align-middle">'.htmlspecialchars($row->exp_cliente).'</td>
                <td class="align-middle">'.htmlspecialchars($row->ventas).'</td>
                <td class="align-middle">'.htmlspecialchars($row->medidas_correctivas).'</td>
                <td class="align-middle">'.formatearFecha($row->fecha_aplicacion).'</td>
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

        $dompdf->stream("Seguimiento-reporte-indicadores.pdf", ["Attachment" => true]);
    }

    //-----------------------------------------------------------------

    public function capacitacionPersonal(){

        $title = 'Capacitación del personal';
         // Buscar permisos de los modulos
        $permisos = ModuloService::permisosSesion($this->modulo);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('4. OBJETIVOS, METAS E INDICADORES', '/sasisopa/objetivos-metas-indicadores');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
             'links' =>[
                
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/objetivosmetasindicadores/capacitacionpersonal.actions.init.js?v=1.0'
            ],
            'help' => false
        ];
        
        View::render('objetivosmetasindicadores/capacitacion-personal', $data,'sasisopa');

    }

    public function resumenCapacitacionPermosal(){

          header('Content-Type: application/json');

            $year = $_GET['year'] ?? date('Y');

            $data = CapacitacionService::getResumen(
                $this->estacionId(),
                $year
            );

            echo json_encode($data);

    }

    //------------------------------------------------------------------

    public function indicadorVentas(){
        $title = 'Indicadores de Ventas';
         // Buscar permisos de los modulos
        $permisos = ModuloService::permisosSesion($this->modulo);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('4. OBJETIVOS, METAS E INDICADORES', '/sasisopa/objetivos-metas-indicadores');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
             'links' =>[
                
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/apexcharts/dist/apexcharts.min.js',
                '/js/objetivosmetasindicadores/indicadorventas.actions.init.js?v=1.0'
                
            ],
            'help' => true
        ];
        
        View::render('objetivosmetasindicadores/indicador-ventas', $data,'sasisopa');
    }

    public function getIndicadorVentas(){
        header('Content-Type: application/json');

        $year = $_GET['year'] ?? date('Y');

        $estacion = Estacion::find($this->estacionId());
        $estacionId = $this->estacionId();

        $producto1 = $estacion->producto_uno;
        $producto2 = $estacion->producto_dos;
        $producto3 = $estacion->producto_tres;

        // Traer todo en una sola consulta
        $meses = ReporteCreMes::with('productos')
            ->where('id_estacion', $estacionId)
            ->where('year', $year)
            ->get();

        // Inicializar meses
        $data = [];

        for ($i = 1; $i <= 12; $i++) {
            $data[$i] = [
                'mes' => $i,
                'nombre_mes' => nombremes($i),
                'producto1' => 0,
                'producto2' => 0,
                'producto3' => 0,
            ];
        }

        // Procesar
        foreach ($meses as $mes) {

            foreach ($mes->productos as $producto) {

                if ($producto->producto === $producto1) {
                    $data[$mes->mes]['producto1'] += $producto->volumen_venta;
                }

                if ($producto->producto === $producto2) {
                    $data[$mes->mes]['producto2'] += $producto->volumen_venta;
                }

                if ($producto3 && $producto->producto === $producto3) {
                    $data[$mes->mes]['producto3'] += $producto->volumen_venta;
                }
            }
        }

        $data = array_values($data);

        // Totales
        $totales = [
            'producto1' => array_sum(array_column($data, 'producto1')),
            'producto2' => array_sum(array_column($data, 'producto2')),
            'producto3' => array_sum(array_column($data, 'producto3')),
        ];

        // Construir gráfica
        $categories = array_column($data, 'nombre_mes');

        $series = [];

        if ($producto1) {
            $series[] = [
                'name' => $producto1,
                'data' => array_map(fn($d) => round($d['producto1'], 2), $data)
            ];
        }

        if ($producto2) {
            $series[] = [
                'name' => $producto2,
                'data' => array_map(fn($d) => round($d['producto2'], 2), $data)
            ];
        }

        if ($producto3) {
            $series[] = [
                'name' => $producto3,
                'data' => array_map(fn($d) => round($d['producto3'], 2), $data)
            ];
        }

        echo json_encode([
            'success' => true,
            'data' => $data,
            'totales' => $totales,
            'productos' => [
                'p1' => $producto1,
                'p2' => $producto2,
                'p3' => $producto3
            ],
            'chart' => [
                'categories' => $categories,
                'series' => $series
            ]
        ]);
    }

    public function ExperienciaCliente(){

        $title = 'Experiencia del cliente';
         // Buscar permisos de los modulos
        $permisos = ModuloService::permisosSesion($this->modulo);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('4. OBJETIVOS, METAS E INDICADORES', '/sasisopa/objetivos-metas-indicadores');
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
                 '/libs/apexcharts/dist/apexcharts.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/objetivosmetasindicadores/experienciacliente.datatable.init.js?v=1.3',
                '/js/objetivosmetasindicadores/experienciacliente.actions.init.js?v=1.3'
            ],
            'help' => true
        ];
        
        View::render('objetivosmetasindicadores/experiencia-cliente', $data,'sasisopa');

    }

    public function datatableExperienciaCliente(){

        header('Content-Type: application/json');

        $permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');
        $permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');
        $permisoDescargar = ModuloService::validaPermiso($this->modulo, 'descargar');

        $encuestas = EncuentaEstacion::withCount('clientes')
            ->where('id_estacion', $this->estacionId())
            ->where('estado', 1)
            ->orderBy('id')
            ->get();

        // Traer resultados agrupados en UNA SOLA QUERY
        $resultados = Capsule::table('tb_encuentas_estacion_cliente as c')
            ->join('tb_encuentas_estacion_cliente_preguntas as p', 'p.id_cliente', '=', 'c.id')
            ->selectRaw('
                c.id_cuentas_estacion as encuesta_id,
                SUM(CASE WHEN p.resultado = 4 THEN 1 ELSE 0 END) as r4,
                SUM(CASE WHEN p.resultado = 3 THEN 1 ELSE 0 END) as r3,
                SUM(CASE WHEN p.resultado = 2 THEN 1 ELSE 0 END) as r2,
                SUM(CASE WHEN p.resultado = 1 THEN 1 ELSE 0 END) as r1
            ')
            ->groupBy('c.id_cuentas_estacion')
            ->get()
            ->keyBy('encuesta_id');

        $data = [];

        foreach ($encuestas as $i => $encuesta) {
             $tot = $resultados[$encuesta->id] ?? null;

            $r4 = $tot->r4 ?? 0;
            $r3 = $tot->r3 ?? 0;
            $r2 = $tot->r2 ?? 0;
            $r1 = $tot->r1 ?? 0;

            $total = $r4 + $r3 + $r2 + $r1;

            $data[] = [
                'id' => $encuesta->id,
                'num' => $i + 1,
                'fecha' => $encuesta->fechacreacion,
                'encuestados' => $encuesta->clientes_count,

                'excelente_total' => $r4,
                'excelente_porcentaje' => $total ? round(($r4 / $total) * 100, 2) : 0,

                'bueno_total' => $r3,
                'bueno_porcentaje' => $total ? round(($r3 / $total) * 100, 2) : 0,

                'regular_total' => $r2,
                'regular_porcentaje' => $total ? round(($r2 / $total) * 100, 2) : 0,

                'malo_total' => $r1,
                'malo_porcentaje' => $total ? round(($r1 / $total) * 100, 2) : 0,
            ];
        }

        echo json_encode([
            'success' => true,
            'data' => $data,
            "permisos" => [
                "eliminar" => $permisoEliminar,
                "editar"   => $permisoEditar,
                "descargar" => $permisoDescargar
            ]
        ]);

    }

    public function chartExperienciaCliente()
    {
        header('Content-Type: application/json');

        $idEncuesta = $_GET['id'] ?? null;

        $query = Capsule::table('tb_encuentas_estacion_cliente as c')
            ->join('tb_encuentas_estacion_cliente_preguntas as p', 'p.id_cliente', '=', 'c.id');

        if ($idEncuesta) {
            // 👉 SOLO UNA encuesta
            $query->where('c.id_cuentas_estacion', $idEncuesta);
        } else {
    
            $encuestas = EncuentaEstacion::where('id_estacion', $this->estacionId())
                ->where('estado', 1)
                ->pluck('id');

            $query->whereIn('c.id_cuentas_estacion', $encuestas);
        }

        $totales = $query->selectRaw('
            SUM(CASE WHEN p.resultado = 4 THEN 1 ELSE 0 END) as excelente,
            SUM(CASE WHEN p.resultado = 3 THEN 1 ELSE 0 END) as bueno,
            SUM(CASE WHEN p.resultado = 2 THEN 1 ELSE 0 END) as regular,
            SUM(CASE WHEN p.resultado = 1 THEN 1 ELSE 0 END) as malo
        ')->first();

        $excelente = (int) ($totales->excelente ?? 0);
        $bueno     = (int) ($totales->bueno ?? 0);
        $regular   = (int) ($totales->regular ?? 0);
        $malo      = (int) ($totales->malo ?? 0);

        $total = $excelente + $bueno + $regular + $malo;

        echo json_encode([
            'success' => true,
            'data' => [
                'labels' => ['Excelente', 'Bueno', 'Regular', 'Malo'],
                'series' => $total > 0 ? [
                    round(($excelente / $total) * 100, 1),
                    round(($bueno / $total) * 100, 1),
                    round(($regular / $total) * 100, 1),
                    round(($malo / $total) * 100, 1)
                ] : [0, 0, 0, 0]
            ]
        ]);
    }

    public function createExperienciaCliente()
    {

     header('Content-Type: application/json; charset=utf-8');
        $input = json_decode(file_get_contents('php://input'), true);

        if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No tienes permiso para crear'
                ]);
                return;
            }

            $encuesta = EncuentaEstacion::create([
                'id_estacion'   => $this->estacionId(),
                'id_usuario'    => $this->userId(), // o auth()->id()
                'id_encuesta'   => 1,
                'estado'        => 1
            ]);

            echo json_encode([
                'success' => true,
                'id' => $encuesta->id
            ]);
    }

    public function editarExperienciaCliente($id){

         $title = 'Agregar Experiencia del cliente';
         // Buscar permisos de los modulos
        $permisos = ModuloService::permisosSesion($this->modulo);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('4. OBJETIVOS, METAS E INDICADORES', '/sasisopa/objetivos-metas-indicadores');
        Breadcrumb::add('Experiencia del cliente', '/sasisopa/objetivos-metas-indicadores/experiencia-cliente');
        Breadcrumb::add('Agregar', '');

        $encuestaEstacion = EncuentaEstacion::find($id);
        $cuestionario = EncuentaCuestionario::all();

         $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'id' => $id,
            'fecha' => formatDate($encuestaEstacion->fechacreacion),
            'cuestionario' => $cuestionario,
             'links' =>[
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
                
            ],
            'scripts' => [
                '/js/vendor.min.js',
                 '/libs/apexcharts/dist/apexcharts.min.js',
                 '/js/objetivosmetasindicadores/experienciaclienteeditar.actions.init.js?v=1.0'
            ],
            'help' => true
        ];
        
        View::render('objetivosmetasindicadores/experiencia-cliente-editar', $data,'sasisopa');
                

    }

    public function deleteExperienciaCliente(){

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

        try {

            Capsule::connection()->transaction(function () use ($id) {

                $encuesta = EncuentaEstacion::findOrFail($id);

                $clientesIds = EncuentasEstacionCliente::where('id_cuentas_estacion', $id)
                    ->pluck('id');

                if ($clientesIds->isNotEmpty()) {
                    EncuentasEstacionClienteComentarios::whereIn('id_cliente', $clientesIds)->delete();
                    EncuentasEstacionClientePreguntas::whereIn('id_cliente', $clientesIds)->delete();
                }

                EncuentasEstacionCliente::where('id_cuentas_estacion', $id)->delete();

                $encuesta->delete();
            });

            echo json_encode([
                'success' => true,
                'message' => 'Experiencia cliente eliminado correctamente'
            ]);

        } catch (\Throwable $e) {

            error_log($e->getMessage());

            echo json_encode([
                'success' => false,
                'message' => 'Error al eliminar registro'
            ]);
        }
    }

    public function detalleExperienciaCliente($id){

     $title = 'Detalle Experiencia del cliente';
         // Buscar permisos de los modulos
        $permisos = ModuloService::permisosSesion($this->modulo);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('4. OBJETIVOS, METAS E INDICADORES', '/sasisopa/objetivos-metas-indicadores');
        Breadcrumb::add('Experiencia del cliente', '/sasisopa/objetivos-metas-indicadores/experiencia-cliente');
        Breadcrumb::add('Detalle', '');

        $encuestaEstacion = EncuentaEstacion::find($id);
        $cuestionario = EncuentaCuestionario::all();

         $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'id' => $id,
            'fecha' => formatearFecha($encuestaEstacion->fechacreacion),
            'cuestionario' => $cuestionario,
             'links' =>[
                             
            ],
            'scripts' => [
                '/js/vendor.min.js',
                 '/libs/apexcharts/dist/apexcharts.min.js',
                 '/js/objetivosmetasindicadores/experienciaclientedetalle.actions.init.js?v=1.1'
            ],
            'help' => true
        ];
        
        View::render('objetivosmetasindicadores/experiencia-cliente-detalle', $data,'sasisopa');
    }

    public function agregarEncuestaCliente()
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);

        $idReporte = $data['id'];
        $nombre = $data['nombre'];
        $comentario = $data['comentario'];
        $preguntas = $data['preguntas'];

        $hoy = date('Y-m-d h:i:s');
        $idEncuestaCliente = strtotime($hoy);        
        

         if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No tienes permiso para crear'
                ]);
                return;
            }

        try {

            Capsule::connection()->transaction(function () use ($idReporte, $nombre, $comentario, $preguntas, $idEncuestaCliente) {

                               
                EncuentasEstacionCliente::create([
                    'id' => $idEncuestaCliente,
                    'id_cuentas_estacion' => $idReporte,
                    'nombre' => $nombre
                ]);

            $insertPreguntas = [];

                foreach ($preguntas as $p) {
                    if ($p['respuesta'] > 0) {
                        $insertPreguntas[] = [
                            'id_cliente' => $idEncuestaCliente,
                            'id_pregunta' => $p['id_pregunta'],
                            'resultado' => $p['respuesta']
                        ];
                    }
                }

                if (!empty($insertPreguntas)) {
                    EncuentasEstacionClientePreguntas::insert($insertPreguntas);
                }

                // Comentario
                if (!empty($comentario)) {
                    EncuentasEstacionClienteComentarios::create([
                        'id_cliente' => $idEncuestaCliente,
                        'comentario' => $comentario
                    ]);
                }
            });

            echo json_encode([
                'success' => true,
                'message' => 'Encuesta creada correctamente'
            ]);

        } catch (\Throwable $e) {


            echo json_encode([
                'success' => false,
                'message' => 'Error al guardar encuesta'
            ]);
        }
    }

    public function getListaClientes()
    {
        header('Content-Type: application/json');

        $id = $_GET['id'] ?? null;

        $clientes = EncuentasEstacionCliente::where('id_cuentas_estacion', $id)
            ->select('id', 'nombre')
            ->get();

        echo json_encode([
            'success' => true,
            'data' => $clientes
        ]);
    }

    public function detalleEncuestaCliente()
    {
        header('Content-Type: application/json');

        $id = $_GET['id'] ?? null;

        $cliente = EncuentasEstacionCliente::find($id);

        $comentario = EncuentasEstacionClienteComentarios::where('id_cliente', $id)
            ->value('comentario');

        $preguntas = Capsule::table('tb_encuentas_estacion_cliente_preguntas as p')
            ->join('tb_encuentas_cuestionario as c', 'c.id', '=', 'p.id_pregunta')
            ->where('p.id_cliente', $id)
            ->orderBy('c.num_pregunta')
            ->select(
                'c.num_pregunta',
                'c.pregunta',
                'p.resultado'
            )
            ->get();

        echo json_encode([
            'success' => true,
            'data' => [
                'nombre' => $cliente->nombre,
                'comentario' => $comentario,
                'preguntas' => $preguntas
            ]
        ]);
    }

    public function finalizarEncuesta()
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);

        $id = $data['id'] ?? null;
        $fecha = $data['fecha'] ?? null;

        if (!$id || !$fecha) {
            echo json_encode([
                'success' => false,
                'message' => 'Datos incompletos'
            ]);
            return;
        }

        if (!ModuloService::validaPermiso($this->modulo, 'editar')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso'
            ]);
            return;
        }

        try {

            $encuesta = EncuentaEstacion::findOrFail($id);

            $encuesta->fechacreacion = $fecha; // formato: Y-m-d H:i:s
            $encuesta->estado = 1;
            $encuesta->save();

            echo json_encode([
                'success' => true,
                'message' => 'Encuesta finalizada correctamente'
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function chartExperienciaClientePreguntas()
    {
        header('Content-Type: application/json');

        $idReporte = $_GET['id'] ?? null;

        if (!$idReporte) {
            echo json_encode(['success' => false]);
            return;
        }

                $data = Capsule::table('tb_encuentas_cuestionario as c')
            ->leftJoin('tb_encuentas_estacion_cliente_preguntas as p', function ($join) use ($idReporte) {
                $join->on('p.id_pregunta', '=', 'c.id')
                    ->whereIn('p.id_cliente', function ($q) use ($idReporte) {
                        $q->select('id')
                        ->from('tb_encuentas_estacion_cliente')
                        ->where('id_cuentas_estacion', $idReporte);
                    });
            })
            ->selectRaw('
                c.id,
                c.num_pregunta,
                c.pregunta,
                COALESCE(SUM(CASE WHEN p.resultado = 4 THEN 1 END),0) as excelente,
                COALESCE(SUM(CASE WHEN p.resultado = 3 THEN 1 END),0) as bueno,
                COALESCE(SUM(CASE WHEN p.resultado = 2 THEN 1 END),0) as regular,
                COALESCE(SUM(CASE WHEN p.resultado = 1 THEN 1 END),0) as malo
            ')
            ->groupBy('c.id', 'c.num_pregunta', 'c.pregunta')
            ->orderBy('c.num_pregunta')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => (int) $item->id,
                    'num_pregunta' => (int) $item->num_pregunta,
                    'pregunta' => $item->pregunta,
                    'excelente' => (int) $item->excelente,
                    'bueno' => (int) $item->bueno,
                    'regular' => (int) $item->regular,
                    'malo' => (int) $item->malo,
                ];
            });

        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
    }

}