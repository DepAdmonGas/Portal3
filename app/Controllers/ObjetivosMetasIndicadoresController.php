<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Estacion;
use App\Models\Sasisopa\SeguimientoReporteIndicador;
use App\Models\Sasisopa\SeguimientoObjetivosMetas;
use App\Models\Sasisopa\SeguimientoObjetivosMetasDetalle;
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
                '/js/objetivosmetasindicadores/seguimientoindicadores.datatable.init.js?v=1.0',
                '/js/objetivosmetasindicadores/seguimientoobjetivosmetas.datatable.init.js?v=1.4',
                '/js/objetivosmetasindicadores/seguimientoobjetivosmetas.actions.init.js?v=1.4'
            ],
            'help' => true
        ];
        
        View::render('sasisopa/objetivos-metas-indicadores', $data,'sasisopa');

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

    <table class="table table-bordered table-sm">
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

    <br>

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
}