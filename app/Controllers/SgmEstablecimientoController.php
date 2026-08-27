<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Sgm\ObjetivoCliente;
use App\Models\Sgm\SeguimientoObjetivoIndicador;
use App\Models\Usuario;
use App\Models\Estacion;

use App\Models\Sgm\Autorizado;
use App\Models\Sgm\SeguimientoImplementacionSgm;
use App\Models\Sgm\SeguimientoCalibracionEquipo;
use App\Models\Sgm\SeguimientoSatisfaccionCliente;
use App\Models\Sgm\SeguimientoAsistente;

use Dompdf\Dompdf;
use Dompdf\Options;

use Carbon\Carbon;
use Illuminate\Database\Capsule\Manager as Capsule;

class SgmEstablecimientoController extends BaseController
{
    protected string $modulo = 'sgm';

    public function index()
    {

        $title = '4. Establecimiento de objetivos enfocados al cliente';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/libs/select2/dist/css/select2.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/libs/select2/dist/js/select2.full.min.js',
                '/libs/select2/dist/js/select2.min.js',

                '/js/asistencia/listaasistencia.crear.init.js?v=' . time(),

                '/js/sgm/establecimiento-objetivos/objetivos.actions.init.js?v=' . time(),
                '/js/sgm/establecimiento-objetivos/seguimientoobjetivos.action.init.js?v=' . time(),

                '/js/asistencia/listaasistencia.datatable.init.js?v=1.0.2',
                '/js/sgm/establecimiento-objetivos/seguimientoobjetivos.datatable.init.js?v=' . time(),
            ],
            'help' => true
        ];

        View::render('sgm/establecimiento-objetivos/index', $data, 'sgm');
    }

    public function tableObjetivos()
    {
        header('Content-Type: application/json');

        $objetivos = ObjetivoCliente::where(
            'id_estacion',
            $this->estacionId()
        )
            ->orderByDesc('id')
            ->get();

        $data = $objetivos->map(fn($objetivo) => [
            'id' => $objetivo->id,
            'fecha' => formatearFecha($objetivo->fecha?->format('Y-m-d')),
            'detalle' => $objetivo->detalle,
        ]);

        echo json_encode($data);
    }

    public function objetivoIndex()
    {

        $title = 'Editar objetivos enfocados al cliente';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add('4. Establecimiento de objetivos enfocados al cliente', '/sgm/establecimiento-objetivos-enfocados-cliente');
        Breadcrumb::add($title, '');
        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'links' => [
                'libs/quill/dist/quill.snow.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/quill/dist/quill.js',
                '/js/sgm/establecimiento-objetivos/objetivoseditar.actions.init.js?v=' . time(),
            ],
            'help' => false
        ];

        View::render('sgm/establecimiento-objetivos/objetivos', $data, 'sgm');
    }

    public function detalleObjetivo()
    {
        header('Content-Type: application/json');

        $objetivo = ObjetivoCliente::where(
            'id_estacion',
            $this->estacionId()
        )
            ->latest('id')
            ->first();

        if (!$objetivo) {

            echo json_encode([
                'fecha' => date('Y-m-d'),
                'detalle' => ''
            ]);

            return;
        }

        echo json_encode([
            'fecha' => $objetivo->fecha?->format('Y-m-d'),
            'detalle' => $objetivo->detalle
        ]);
    }

    public function createObjetivo()
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);

        ObjetivoCliente::create([
            'id_estacion' => $this->estacionId(),
            'detalle'   => $data['detalle']
        ]);

        echo json_encode([
            'success' => true

        ]);
    }

    public function deleteObjetivo()
    {
        header('Content-Type: application/json');

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        ObjetivoCliente::where(
            'id_estacion',
            $this->estacionId()
        )
            ->findOrFail($data['id'])
            ->delete();

        echo json_encode([
            'success' => true,
            'message' => 'Objetivo eliminado'
        ]);
    }

    //----------------------------------------------------------------------------
    //-- Fo.SGM.004 Seguimiento de objetivos e indicadores 

    public function SeguimientoObjetivoIndex(int $id)
    {

        $title = 'Fo.SGM.004 Seguimiento de objetivos e indicadores';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add('4. Establecimiento de objetivos enfocados al cliente', '/sgm/establecimiento-objetivos-enfocados-cliente');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'id' => $id,
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/libs/select2/dist/css/select2.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/select2/dist/js/select2.full.min.js',
                '/libs/select2/dist/js/select2.min.js',

                '/js/sgm/establecimiento-objetivos/seguimientoobjetivos.action.init.js?v=' . time(),
            ],
            'help' => true
        ];

        View::render('sgm/establecimiento-objetivos/seguimiento-objetivos', $data, 'sgm');
    }

    public function datatableSeguimientoObjetivo()
    {
        header('Content-Type: application/json');

        $permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');
        $permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');
        $permisoDescargar   = ModuloService::validaPermiso($this->modulo, 'descargar');

        $registros = SeguimientoObjetivoIndicador::query()
            ->where('id_estacion', $this->estacionId())
            ->orderBy('fecha')
            ->get();

        $data = $registros->values()->map(function ($item, $index) {

            return [
                'id'     => $item->id,
                'numero' => $index + 1,
                'fecha'  => $item->fecha->format('Y-m-d'),
                'hora'   => $item->hora,
                'estado' => (int) $item->estado,
            ];
        });

        echo json_encode([
            'data' => $data,
            "permisos" => [
                "eliminar" => $permisoEliminar,
                "editar"   => $permisoEditar,
                "descargar" => $permisoDescargar
            ]
        ]);
    }

    public function createSeguimientoObjetivo()
    {

        header('Content-Type: application/json');


        $seguimiento = Capsule::transaction(function () {

            $realizadoPor = Autorizado::query()
                ->join(
                    'tb_usuarios',
                    'tb_usuarios.id',
                    '=',
                    'sgm_autorizado.id_usuario'
                )
                ->where('tb_usuarios.id_gas', $this->estacionId())
                ->where('sgm_autorizado.estado', 1)
                ->value('sgm_autorizado.id_usuario') ?? 0;

            $seguimiento = SeguimientoObjetivoIndicador::create([

                'id_estacion'  => $this->estacionId(),
                'id_usuario'   => $this->userId(),
                'fecha'        => date('Y-m-d'),
                'hora'         => date('H:i:s'),
                'lugar'        => '',
                'realizadopor' => $realizadoPor,
                'estado'       => 0,

            ]);

            SeguimientoImplementacionSgm::create([
                'id_seguimiento' => $seguimiento->id,
                'respuesta_uno' => 0,
                'respuesta_dos' => 0,
                'respuesta_tres' => '',
                'respuesta_cuatro' => '',
            ]);

            SeguimientoCalibracionEquipo::create([
                'id_seguimiento' => $seguimiento->id,
                'respuesta_uno' => 0,
                'respuesta_dos' => '',
                'respuesta_tres' => '',
            ]);

            SeguimientoSatisfaccionCliente::create([
                'id_seguimiento' => $seguimiento->id,
                'respuesta_uno' => 0,
                'respuesta_dos' => 0,
                'respuesta_tres' => 0,
                'respuesta_cuatro' => '',
                'respuesta_cinco' => '',
            ]);

            return $seguimiento;
        });

        echo json_encode([
            'success' => true,
            'id' => $seguimiento->id
        ]);
    }

    public function deleteSeguimientoObjetivo()
    {

        header('Content-Type: application/json');
        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        $id = $data['id'];

        try {


            Capsule::transaction(function () use ($id) {


                SeguimientoAsistente::where(
                    'id_seguimiento',
                    $id
                )->delete();

                SeguimientoSatisfaccionCliente::where(
                    'id_seguimiento',
                    $id
                )->delete();

                SeguimientoCalibracionEquipo::where(
                    'id_seguimiento',
                    $id
                )->delete();

                SeguimientoImplementacionSgm::where(
                    'id_seguimiento',
                    $id
                )->delete();

                SeguimientoObjetivoIndicador::where(
                    'id',
                    $id
                )->delete();
            });

            echo json_encode([
                'success' => true,
                'message' => 'Seguimiento Objetivos eliminados'
            ]);
        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    //-----------------------------------------------------------------------------
    //-----------------------------------------------------------------------------

    public function detalle(int $id)
    {
        header('Content-Type: application/json');

        $seguimiento = SeguimientoObjetivoIndicador::with([
            'implementacion',
            'calibracion',
            'satisfaccion',
            'asistentes.usuario'
        ])->findOrFail($id);

        $usuarios = Usuario::query()
            ->where('id_gas', $this->estacionId())
            ->where('estatus', 0)
            ->whereNotIn(
                'id',
                $seguimiento->asistentes
                    ->pluck('id_usuario')
            )
            ->orderBy('nombre')
            ->get([
                'id',
                'nombre'
            ]);

        $seguimiento = $seguimiento->toArray();

        $seguimiento['fecha'] = Carbon::parse(
            $seguimiento['fecha']
        )->format('Y-m-d');

        echo json_encode([
            'seguimiento' => $seguimiento,
            'usuarios'    => $usuarios
        ]);
    }

    public function agregarAsistentes()
    {
        header('Content-Type: application/json');

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        Capsule::transaction(function () use ($data) {

            foreach ($data['usuarios'] as $usuario) {

                SeguimientoAsistente::firstOrCreate([
                    'id_seguimiento' => $data['id'],
                    'id_usuario'     => $usuario
                ]);
            }
        });

        echo json_encode([
            'success' => true,
            'message' => 'Asistente agregado'
        ]);
    }

    public function eliminarAsistente()
    {
        header('Content-Type: application/json');

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        SeguimientoAsistente::destroy(
            $data['id']
        );

        echo json_encode([
            'success' => true,
            'message' => 'Asistente eliminado'
        ]);
    }

    public function actualizarCampo()
    {
        header('Content-Type: application/json');

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        $modelo = match ($data['seccion']) {

            1 => SeguimientoImplementacionSgm::class,
            2 => SeguimientoCalibracionEquipo::class,
            3 => SeguimientoSatisfaccionCliente::class,

            default => null
        };

        if (!$modelo) {

            http_response_code(400);

            echo json_encode([
                'success' => false
            ]);

            return;
        }

        $registro = $modelo::where(
            'id_seguimiento',
            $data['id']
        )->firstOrFail();

        $campo = match ($data['campo']) {

            1 => 'respuesta_uno',
            2 => 'respuesta_dos',
            3 => 'respuesta_tres',
            4 => 'respuesta_cuatro',
            5 => 'respuesta_cinco',

            default => null
        };

        if (!$campo) {

            http_response_code(400);

            return;
        }

        $registro->$campo = $data['valor'];

        $registro->save();

        if (
            $data['seccion'] == 3 &&
            $data['campo'] == 6
        ) {

            ObjetivoCliente::create([

                'id_estacion' => $this->estacionId(),
                'detalle'     => $data['contenido']

            ]);
        }

        echo json_encode([
            'success' => true
        ]);
    }

    public function finalizar()
    {
        header('Content-Type: application/json');

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        $seguimiento = SeguimientoObjetivoIndicador::findOrFail(
            $data['id']
        );

        $seguimiento->update([

            'fecha'  => $data['fecha'],
            'hora'   => $data['hora'],
            'lugar'  => $data['lugar'],
            'estado' => 1

        ]);

        echo json_encode([
            'success' => true
        ]);
    }

    public function pdf(int $id)
    {

        header('Content-Type: application/pdf');

        $estacion = Estacion::findOrFail($this->estacionId());

        $seguimiento = SeguimientoObjetivoIndicador::with([
            'implementacion',
            'calibracion',
            'satisfaccion',
            'asistentes.usuario',
        ])->findOrFail($id);

        $realizadoPor = 'S/I';

        if ($seguimiento->realizadopor) {
            $realizadoPor = Usuario::find($seguimiento->realizadopor)?->nombre ?? 'S/I';
        }

        $anio = $seguimiento->fecha?->format('Y') ?? date('Y');

        $css = file_get_contents(
            'assets/css/pdf.css'
        );

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">

            <title>Seguimiento de objetivos e indicadores</title>

            <link rel="stylesheet" href="' . $_ENV['APP_URL'] . '/assets/css/pdf.css">

            <style>
                ' . $css . '
                h4{
                    font-size: 18px;
                    margin:12px 0 8px;
                }

                h5{
                    margin:8px 0 4px;
                    font-size:14px;
                }

                hr{
                    margin:12px 0;
                }

                .respuesta{
                    border:1px solid #dee2e6;
                    padding:6px;
                    min-height:20px;
                    margin-bottom:10px;
                }

            </style>

        </head>

        <body>

        <table class="table table-bordered">
            <tr>
                <td rowspan="2" class="text-center align-middle">
                    ' . $estacion->razonsocial . '
                </td>

                <td rowspan="2" class="text-center align-middle">
                    <strong>Seguimiento de objetivos e indicadores</strong>
                </td>

                <td class="text-center align-middle">
                    <strong>Fecha de autorización: 01-01-2024</strong>
                </td>
            </tr>

            <tr>
                <td class="text-center align-middle">
                    Fo.SGM.004
                </td>
            </tr>

            <tr>
                <td class="text-center align-middle">
                    Realizado por:<br>' . $realizadoPor . '
                </td>

                <td class="text-center align-middle">
                    Revisado por:<br>Eduardo Galicia Flores
                </td>

                <td class="text-center align-middle">
                    Autorizado por:<br>' . $estacion->apoderado_legal . '
                </td>
            </tr>

        </table>';

        $html .= '
        <table class="table table-bordered table-sm">
            <tr>
                <th>Fecha</th>
                <th>Hora</th>
                <th>Lugar</th>
            </tr>

            <tr>
                <td class="text-center">' . formatearFecha($seguimiento->fecha?->format('Y-m-d')) . '</td>
                <td class="text-center">' . date('g:i A', strtotime($seguimiento->hora)) . '</td>
                <td class="text-center">' . $seguimiento->lugar . '</td>
            </tr>
        </table>';

        $html .= '
            <h4>Indicador: Implementación del SGM</h4>

            <table class="table table-bordered table-sm">

            <tr>
            <td>Porcentaje de procedimientos implementados durante el año inmediato anterior</td>
            <td>' . $seguimiento->implementacion->respuesta_uno . '</td>
            </tr>

            <tr>
            <td>Porcentaje de procedimientos documentados durante el año inmediato anterior</td>
            <td>' . $seguimiento->implementacion->respuesta_dos . '</td>
            </tr>

            <tr>
            <td colspan="2"><b>Comentarios y observaciones</b></td>
            </tr>

            <tr>
            <td colspan="2">' . $seguimiento->implementacion->respuesta_tres . '</td>
            </tr>

            <tr>
            <td colspan="2"><b>Acciones de mejora</b></td>
            </tr>

            <tr>
            <td colspan="2">' . $seguimiento->implementacion->respuesta_cuatro . '</td>
            </tr>

            </table>';

        $html .= '
        <h4>Indicador: Calibración de equipos</h4>

        <table class="table table-bordered table-sm">

        <tr>
        <td>Porcentaje de equipos calibrados durante el año ' . $anio . '</td>
        <td>' . $seguimiento->calibracion->respuesta_uno . '</td>
        </tr>

        <tr>
        <td colspan="2"><b>Comentarios y observaciones</b></td>
        </tr>

        <tr>
        <td colspan="2">' . $seguimiento->calibracion->respuesta_dos . '</td>
        </tr>

        <tr>
        <td colspan="2"><b>Acciones de mejora</b></td>
        </tr>

        <tr>
        <td colspan="2">' . $seguimiento->calibracion->respuesta_tres . '</td>
        </tr>

        </table>';

        $html .= '
        <h4>Indicador: Satisfacción del cliente</h4>

        <table class="table table-bordered table-sm">

        <tr>
        <td>Número de quejas por parte de los clientes</td>
        <td>' . $seguimiento->satisfaccion->respuesta_uno . '</td>
        </tr>

        <tr>
        <td>Número de quejas atendidas de manera satisfactoria</td>
        <td>' . $seguimiento->satisfaccion->respuesta_dos . '</td>
        </tr>

        <tr>
        <td>Porcentaje respecto al año anterior</td>
        <td>' . $seguimiento->satisfaccion->respuesta_tres . '</td>
        </tr>

        <tr>
        <td colspan="2"><b>Comentarios y observaciones</b></td>
        </tr>

        <tr>
        <td colspan="2">' . $seguimiento->satisfaccion->respuesta_cuatro . '</td>
        </tr>

        <tr>
        <td colspan="2"><b>Acciones de mejora</b></td>
        </tr>

        <tr>
        <td colspan="2">' . $seguimiento->satisfaccion->respuesta_cinco . '</td>
        </tr>

        </table>';

        $html .= '
        <h4>Asistentes</h4>

        <table class="table table-bordered table-sm">

        <thead>

        <tr>

        <th width="40">#</th>
        <th>Nombre</th>
        <th width="120">Firma</th>

        </tr>

        </thead>

        <tbody>';

        foreach ($seguimiento->asistentes as $i => $asistente) {

            $firma = '';

            if ($asistente->usuario?->firma) {

                $archivo = $_ENV['APP_URL'] . '/uploads/firma-personal/' . $asistente->usuario->firma;

                if ($archivo) {
                    $firma = '<img src="' . $archivo . '" width="70">';
                }
            }

            $html .= '
    <tr>

        <td class="text-center">' . ($i + 1) . '</td>

        <td>' . $asistente->usuario->nombre . '</td>

        <td align="center">';

            if ($firma) {

                $html .= $firma;
            }

            $html .= '
        </td>

    </tr>';
        }


        $html .= '
        </body>
        </html>';

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $dompdf->stream(
            'Seguimiento de objetivos e indicadores.pdf',
            ['Attachment' => true]
        );

        exit;
    }
}
