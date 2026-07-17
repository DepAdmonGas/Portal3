<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Estacion;
use App\Models\Sgm\Autorizado;
use App\Models\Sasisopa\RequisitosLegalesMatriz;
use App\Models\Sasisopa\RequisitosLegalesCalendario;
use App\Models\Sgm\InventarioNormatividadAplicable;

use Dompdf\Dompdf;
use Dompdf\Options;

class SgmNormatividadController extends BaseController{

    protected string $modulo = 'sgm';

    public function index(){

        $title = '5. Normatividad aplicable a mediciones';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add($title, '');
        $permisos = ModuloService::permisosSesion($this->modulo);

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

                '/js/sgm/normatividad/inventario.actions.init.js?v=1.0.1',
                '/js/asistencia/listaasistencia.actions.init.js?v=1.0.1',

                '/js/asistencia/listaasistencia.datatable.init.js?v=1.0.1', 
                '/js/sgm/normatividad/inventario.datatable.init.js?v=1.0.1', 
            ],
            'help' => true
        ];
        
        View::render('sgm/normatividad/index', $data,'sgm');

    }

    //---Fo.SGM.005 Inventario de Normatividad Aplicable ----

    public function datatableInventario(){

    header('Content-Type: application/json');

    $permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');
        $permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');
        $permisoDescargar   = ModuloService::validaPermiso($this->modulo, 'descargar');

    $inventario = InventarioNormatividadAplicable::query()
        ->whereIn('estado', [
            $this->estacionId(),
            0
        ])
        ->orderBy('id')
        ->get();

    $data = $inventario->values()->map(function ($item, $index) {

        return [

            'id' => $item->id,

            'numero' => $index + 1,

            'norma' => $item->norma,

            'fecha_publicacion' => $item->fecha_publicacion
                ? $item->fecha_publicacion->format('Y-m-d')
                : 'S/I',

            'fecha_aplicacion' => (
                !$item->fecha_aplicacion ||
                $item->fecha_aplicacion->format('Y-m-d') === '0000-00-00'
            )
                ? 'S/I'
                : $item->fecha_aplicacion->format('d-m-Y'),

            'equipo' => $item->equipo,

            'link' => $item->link,

        ];

    });

    echo json_encode([
    "data" => $data,
    "permisos" => [
        "eliminar" => $permisoEliminar,
        "editar"   => $permisoEditar,
        "descargar" => $permisoDescargar
    ]
    ]);

    }

    public function createInventario()
    {
        header('Content-Type: application/json');

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        $inventario = InventarioNormatividadAplicable::create([
            'norma'              => $data['norma'],
            'fecha_publicacion'  => $data['fecha_publicacion'] ?: '',
            'fecha_aplicacion'   => $data['fecha_aplicacion'] ?: '',
            'equipo'             => $data['equipo'],
            'link'               => $data['link'],
            'estado'             => $this->estacionId(),
        ]);

        echo json_encode([
            'success' => true,
            'id' => $inventario->id,
            'message' => 'Registro guardado correctamente.'

        ]);
    }

    public function deleteInventario(){

    header('Content-Type: application/json');

    $data = json_decode(
        file_get_contents('php://input'),
        true
    );

    try {

        $registro = InventarioNormatividadAplicable::query()
            ->where('id', $data['id'])
            ->where('estado', $this->estacionId())
            ->first();

        if (!$registro) {

            echo json_encode([
                'success' => false,
                'message' => 'No se encontró el registro.'
            ]);

            return;
        }

        $registro->delete();

        echo json_encode([
            'success' => true,
            'message' => 'Registro eliminado correctamente.'
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);

    }

    }

    public function pdfInventario(){
     header('Content-Type: application/pdf');

    $estacion = Estacion::findOrFail($this->estacionId());

    $realizadoPor = Autorizado::query()
        ->join(
            'tb_usuarios',
            'tb_usuarios.id',
            '=',
            'sgm_autorizado.id_usuario'
        )
        ->where('tb_usuarios.id_gas', $this->estacionId())
        ->where('sgm_autorizado.estado', 1)
        ->value('tb_usuarios.nombre') ?? 'S/I';

    $inventario = InventarioNormatividadAplicable::query()
        ->whereIn('estado', [
            $this->estacionId(),
            0
        ])
        ->orderBy('norma')
        ->get();

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">

        <title>Inventario de Normatividad Aplicable</title>

        <link rel="stylesheet" href="' . $_ENV['APP_URL'] . '/assets/css/pdf.css">

        <style>

            body{
                font-size:12px;
            }

            table{
                width:100%;
                border-collapse:collapse;
            }

            th,
            td{
                border:1px solid #dee2e6;
                padding:5px;
                vertical-align:middle;
            }

            th{
                background:#f5f5f5;
            }

        </style>

    </head>

    <body>

    <table class="table table-bordered">

        <tr>

            <td rowspan="2" class="text-center align-middle">

                '.$estacion->razonsocial.'

            </td>

            <td rowspan="2" class="text-center align-middle">

                <strong>Inventario de Normatividad Aplicable</strong>

            </td>

            <td class="text-center align-middle">

                <strong>Fecha de autorización: 01-01-2024</strong>

            </td>

        </tr>

        <tr>

            <td class="text-center align-middle">

                Fo.SGM.005

            </td>

        </tr>

        <tr>

            <td class="text-center align-middle">

                Realizado por:<br>
                '.$realizadoPor.'

            </td>

            <td class="text-center align-middle">

                Revisado por:<br>
                Eduardo Galicia Flores

            </td>

            <td class="text-center align-middle">

                Autorizado por:<br>
                '.$estacion->apoderado_legal.'

            </td>

        </tr>

    </table>

    <br>

    <table>

        <thead>

            <tr>

                <th width="40">#</th>
                <th>Norma, acuerdo o disposición</th>
                <th width="50">Fecha de publicación</th>
                <th width="50">Fecha de aplicación</th>
                <th>Equipo o procedimiento de medición al que aplica</th>
                <th width="110">Link</th>
            </tr>

        </thead>

        <tbody>';

    if ($inventario->isEmpty()) {

        $html .= '
        <tr>
            <td colspan="6" class="text-center">
                No se encontró información.

            </td>

        </tr>';

    } else {

        foreach ($inventario as $index => $item) {

           $fechaPublicacion = $item->fecha_publicacion
            ? formatearFecha($item->fecha_publicacion->format('Y-m-d'))
            : 'S/I';

$fechaOriginal = $item->getRawOriginal('fecha_aplicacion');

$fechaAplicacion = (
    empty($fechaOriginal) ||
    $fechaOriginal === '0000-00-00'
)
    ? 'S/I'
    : formatearFecha($fechaOriginal);

            $html .= '

            <tr>
                <td class="text-center">
                    '.($index + 1).'
                </td>
                <td>
                    '.$item->norma.'
                </td>
                <td class="text-center">
                    '.$fechaPublicacion.'
                </td>
                <td class="text-center">
                    '.$fechaAplicacion.'
                </td>
                <td class="text-center">
                    '.$item->equipo.'
                </td>
                <td class="text-center">
                    '.$item->link.'
                </td>
            </tr>';

        }

    }

    $html .= '

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
        'Inventario de Normatividad Aplicable.pdf',
        ['Attachment' => true]
    );

    exit;   
    }


    //----- Fo.SGM.006 Requisitos legales del SGM -----
    public function pdfRequisitoLegal()
    {
        header('Content-Type: application/pdf');

            $estacion = Estacion::findOrFail($this->estacionId());
            $realizadoPor = $this->realizadoPor();


            $html = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Requisitos legales del SGM</title>
                <link rel="stylesheet" href="' . $_ENV['APP_URL'] . '/assets/css/pdf.css">
                <style>
                @page {margin: 0.5cm 1cm; font-family: Arial, Helvetica, sans-serif;}
                *,
                *::before,
                *::after {
                box-sizing: border-box;
                }
                html {
                font-family: sans-serif;
                line-height: 1.15;
                -webkit-text-size-adjust: 100%;
                -ms-text-size-adjust: 100%;
                -ms-overflow-style: scrollbar;
                -webkit-tap-highlight-color: transparent;
                }

                @-ms-viewport {
                width: device-width;
                }

                body {
                margin: 0;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
                font-size: .7rem;
                font-weight: 400;
                line-height: 1.5;
                color: #212529;
                background-color: #fff;
                }
                </style>
            </head>

            <body >

            <table class="table table-bordered">
                <tr>
                    <td rowspan="2" class="text-center align-middle">
                        '.$estacion->razonsocial.'
                    </td>

                    <td rowspan="2" class="text-center align-middle">
                        <strong>Requisitos legales del SGM</strong>
                    </td>

                    <td class="text-center align-middle">
                        <strong>Fecha de autorización: 01-01-2024</strong>
                    </td>
                </tr>

                <tr>
                    <td class="text-center align-middle">
                        Fo.SGM.006
                    </td>
                </tr>

                <tr>
                    <td class="text-center align-middle">
                        Realizado por:<br>'.$realizadoPor.'
                    </td>

                    <td class="text-center align-middle">
                        Revisado por:<br>Eduardo Galicia Flores
                    </td>

                    <td class="text-center align-middle">
                        Autorizado por:<br>'.$estacion->apoderado_legal.'
                    </td>
                </tr>

            </table>';

            $html .= '

            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nombre del permiso</th>
                        <th>Periodicidad</th>
                        <th>Fecha emisión</th>
                        <th>Fecha vencimiento</th>
                        <th>Fundamento legal</th>
                    </tr>
                </thead>
                <tbody>
                    '.$this->tablaRequisitosLegales().'
                </tbody>
            </table>';

            $html .= '
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
                'Requisitos legales del SGM.pdf',
                ['Attachment' => true]
            );

            exit;
    }

    private function realizadoPor(): string
    {
        return Autorizado::query()
            ->join(
                'tb_usuarios',
                'tb_usuarios.id',
                '=',
                'sgm_autorizado.id_usuario'
            )
            ->where('tb_usuarios.id_gas', $this->estacionId())
            ->where('sgm_autorizado.estado', 1)
            ->value('tb_usuarios.nombre') ?? 'S/I';
    }
    private function ultimaActualizacion(int $idCalendario): array
    {
        $matriz = RequisitosLegalesMatriz::query()
            ->where('idcalendario', $idCalendario)
            ->latest('id')
            ->first();

        if (!$matriz) {

            return [
                'fecha_emision'      => 'S/I',
                'fecha_vencimiento'  => 'S/I'
            ];

        }

        return [

            'fecha_emision' =>

                $matriz->fecha_emision &&
                $matriz->fecha_emision != '0000-00-00'

                    ? formatearFecha($matriz->fecha_emision->format('Y-m-d'))

                    : 'S/I',

            'fecha_vencimiento' =>

                $matriz->fecha_vencimiento &&
                $matriz->fecha_vencimiento != '0000-00-00'

                    ? formatearFecha($matriz->fecha_vencimiento->format('Y-m-d'))

                    : 'S/I'

        ];
    }
    private function tablaRequisitosLegales(): string
    {
        $html = '';

        $requisitos = RequisitosLegalesCalendario::query()
            ->join(
                'rl_requisitos_legales_lista',
                'rl_requisitos_legales_lista.id',
                '=',
                'rl_requisitos_legales_calendario.id_requisito_legal'
            )
            ->where(
                'rl_requisitos_legales_calendario.id_estacion',
                $this->estacionId()
            )
            ->where(
                'rl_requisitos_legales_calendario.estado',
                1
            )
            ->orderBy('rl_requisitos_legales_lista.id')
            ->get([
                'rl_requisitos_legales_calendario.id',
                'rl_requisitos_legales_calendario.vigencia',
                'rl_requisitos_legales_lista.id as numero',
                'rl_requisitos_legales_lista.permiso',
                'rl_requisitos_legales_lista.fundamento'
            ]);

        foreach ($requisitos as $item) {

            $ultima = $this->ultimaActualizacion($item->id);
            $html .= '
            <tr>
                <td>'.$item->numero.'</td>
                <td>'.$item->permiso.'</td>
                <td>'.$item->vigencia.'</td>
                <td>'.$ultima['fecha_emision'].'</td>
                <td>'.$ultima['fecha_vencimiento'].'</td>
                <td>'.$item->fundamento.'</td>
            </tr>';

        }

        return $html;
    }

    public function requisitoLegal(){

        $title = 'Federal';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add('5. Normatividad aplicable a mediciones', '/sgm/normatividad-aplicable-mediciones');
        Breadcrumb::add($title, '');
        $permisos = ModuloService::permisosSesion($this->modulo);

        $requisitos = RequisitosLegalesCalendario::ToRequisitosTodos($this->estacionId(),1);

         $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'requisitos' => $requisitos,
             'links' =>[
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/libs/select2/dist/css/select2.min.css',
                '/css/select2-modal.css?v=1.0'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/libs/select2/dist/js/select2.full.min.js',
                '/libs/select2/dist/js/select2.min.js',
                '/js/requisitoslegales/detalle.datatable.init.js?v=1.1',
                '/js/requisitoslegales/detalle.actions.init.js?v=1.1'

            ],
            'help' => true
        ];
        
        View::render('sgm/normatividad/requisito-legal', $data,'sgm');

    }

}