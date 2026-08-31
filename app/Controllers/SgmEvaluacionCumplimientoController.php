<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Services\ModuleStationService;
use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Sgm\Autorizado;
use App\Models\Sgm\CumplimientoObjetivosRevision;
use App\Models\Sgm\CumplimientoObjetivosRevisionDetalle;
use App\Models\Sgm\CumplimientoObjetivosRevisionAsistente;
use Dompdf\Dompdf;
use Dompdf\Options;

use Illuminate\Database\Capsule\Manager as Capsule;

class SgmEvaluacionCumplimientoController extends BaseController
{

    protected string $modulo = 'sgm';

    private function estacionModulo(): ?int
    {
        return ModuleStationService::getContext('sgm')['id_estacion'] ?? null;
    }

    public function index()
    {

        $title = '11. Evaluación del cumplimiento de Objetivos y revisión por la Dirección';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);
        $this->validaRegistro(date('Y'));

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'estacionId' => $this->estacionModulo(),
            'moduleStationKey' => 'sgm',
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/core/module-station-selector.js?v=' . time(),
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/sgm/evaluacion-cumplimiento/index.actions.init.js?v=' . time(),
                '/js/sgm/evaluacion-cumplimiento/index.datatable.init.js?v=' . time(),
            ],
            'help' => true
        ];

        View::render('sgm/evaluacion-cumplimiento/index', $data, 'sgm');
    }

    public function validaRegistro(int $year): void
    {
        $idEstacion = $this->estacionModulo();

        if (!$idEstacion) {
            return;
        }

        $existe = CumplimientoObjetivosRevision::query()
            ->where('id_estacion', $idEstacion)
            ->where('year', $year)
            ->exists();

        if ($existe) {
            return;
        }

        $realizadoPor = Autorizado::query()
            ->join('tb_usuarios', 'tb_usuarios.id', '=', 'sgm_autorizado.id_usuario')
            ->where('tb_usuarios.id_gas', $idEstacion)
            ->where('sgm_autorizado.estado', 1)
            ->value('sgm_autorizado.id_usuario') ?? 0;

        CumplimientoObjetivosRevision::create([
            'id_estacion'  => $idEstacion,
            'year'         => $year,
            'fecha'        => '0000-00-00',
            'hora'         => '00:00:00',
            'lugar'        => '',
            'responsable'  => '',
            'realizadopor' => $realizadoPor,
            'estado'       => 0,
        ]);
    }

    public function datatable()
    {

        header('Content-Type: application/json');
        $permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');
        $permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');
        $permisoDescargar   = ModuloService::validaPermiso($this->modulo, 'descargar');

        $registros = CumplimientoObjetivosRevision::query()
            ->where('id_estacion', $this->estacionModulo())
            ->orderByDesc('id')
            ->get();

        $data = [];

        foreach ($registros as $i => $registro) {

            $data[] = [
                'id'      => $registro->id,
                'numero'  => $i + 1,
                'fecha'   => $registro->fecha
                    ? $registro->fecha->format('Y-m-d')
                    : '',
                'hora'    => $registro->hora,
                'estado'  => (int) $registro->estado
            ];
        }

        echo json_encode([
            'data' => $data,
            "permisos" => [
                "eliminar" => $permisoEliminar,
                "editar"   => $permisoEditar,
                "descargar" => $permisoDescargar
            ]
        ]);
    }

    //------------------------------------------------------------------------

    public function editarIndex(int $id)
    {

        $title = 'Editar Cumplimiento de objetivos y revisión por la dirección';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add('11. Evaluación del cumplimiento de Objetivos y revisión por la Dirección', '/sgm/evaluacion-cumplimiento-objetivos-revision-direccion');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);
        $this->crearDetalleSiNoExiste($id);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'estacionId' => $this->estacionModulo(),
            'moduleStationKey' => 'sgm',
            'id' => $id,
            'ocultarSelectorEstacion'=> true,
            'links' => [
                '/libs/select2/dist/css/select2.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/core/module-station-selector.js?v=' . time(),
                '/libs/select2/dist/js/select2.full.min.js',
                '/libs/select2/dist/js/select2.min.js',

                '/js/sgm/evaluacion-cumplimiento/editar.actions.init.js?v=' . time(),
            ],
            'help' => true
        ];

        View::render('sgm/evaluacion-cumplimiento/editar', $data, 'sgm');
    }

    private function crearDetalleSiNoExiste(int $id): void
    {
        if (
            CumplimientoObjetivosRevisionDetalle::where(
                'id_cumplimiento',
                $id
            )->exists()
        ) {
            return;
        }

        foreach (
            [
                'Indicador: Implementación del SGM',
                'Indicador: Calibración de equipos',
                'Indicador: Satisfacción del cliente'
            ] as $categoria
        ) {

            CumplimientoObjetivosRevisionDetalle::create([
                'id_cumplimiento' => $id,
                'categoria' => $categoria,
                'resultado1' => '',
                'resultado2' => '',
                'resultado3' => '',
                'resultado4' => '',
                'resultado5' => ''
            ]);
        }
    }

    public function detalle($id)
    {
        header('Content-Type: application/json');

        $cumplimiento = CumplimientoObjetivosRevision::with([
            'detalles',
            'asistentes.usuario'
        ])->findOrFail($id);

        $idsAsistentes = $cumplimiento->asistentes
            ->pluck('id_usuario')
            ->toArray();

        $cumplimiento = $cumplimiento->toArray();

        $cumplimiento['fecha'] = !empty($cumplimiento['fecha'])
            ? date('Y-m-d', strtotime($cumplimiento['fecha']))
            : '';

        // Responsable
        $usuarios = Usuario::query()
            ->where('id_gas', $this->estacionModulo())
            ->where('estatus', 0)
            ->get([
                'nombre'
            ]);

        // Disponibles para agregar como asistentes
        $usuariosDisponibles = Usuario::query()
            ->where('id_gas', $this->estacionModulo())
            ->where('estatus', 0)
            ->whereNotIn('id', $idsAsistentes)
            ->get([
                'id',
                'nombre'
            ]);

        echo json_encode([
            'cumplimiento' => $cumplimiento,
            'usuarios' => $usuarios,
            'usuariosDisponibles' => $usuariosDisponibles,
        ]);
    }

    public function update()
    {
        header('Content-Type: application/json');

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        Capsule::transaction(function () use ($data) {

            $revision = CumplimientoObjetivosRevision::findOrFail(
                $data['id']
            );

            $revision->update([
                'fecha' => $data['fecha'],
                'hora' => $data['hora'],
                'lugar' => $data['lugar'],
                'responsable' => $data['responsable']
            ]);

            foreach ($data['detalles'] as $detalle) {

                CumplimientoObjetivosRevisionDetalle::whereKey($detalle['id'])
                    ->update([
                        'resultado1' => $detalle['resultado1'],
                        'resultado2' => $detalle['resultado2'],
                        'resultado3' => $detalle['resultado3'],
                        'resultado4' => $detalle['resultado4'],
                        'resultado5' => $detalle['resultado5'],
                    ]);
            }
        });

        echo json_encode([
            'success' => true
        ]);
    }

    public function agregarAsistentes()
    {
        header('Content-Type: application/json');

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        foreach ($data['usuarios'] as $usuario) {

            CumplimientoObjetivosRevisionAsistente::firstOrCreate([
                'id_cumplimiento' => $data['id'],
                'id_usuario' => $usuario
            ]);
        }

        echo json_encode([
            'success' => true
        ]);
    }

    public function eliminarAsistente()
    {

        header('Content-Type: application/json');

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        CumplimientoObjetivosRevisionAsistente::destroy(
            $data['id']
        );

        echo json_encode([
            'success' => true,
            'message' => 'Asistente eliminado'
        ]);
    }

    public function finalizar()
    {

        header('Content-Type: application/json');

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        CumplimientoObjetivosRevision::findOrFail($data['id'])
            ->update([
                'estado' => 1
            ]);

        echo json_encode([
            'success' => true
        ]);
    }

    public function pdf($id)
    {

        header('Content-Type: application/pdf');

        $estacion = Estacion::findOrFail($this->estacionModulo());

        $revision = CumplimientoObjetivosRevision::with([
            'detalles',
            'asistentes.usuario'
        ])->findOrFail($id);

        $realizadoPor = 'S/I';

        if ($revision->realizadopor) {
            $realizadoPor = Usuario::find($revision->realizadopor)?->nombre ?? 'S/I';
        }

        $css = file_get_contents(
            'assets/css/pdf.css'
        );

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Cumplimiento de objetivos y revisión por la dirección</title>
            <link rel="stylesheet" href="' . $_ENV['APP_URL'] . '/assets/css/pdf.css">
            <style>
            ' . $css . '
                h4{
                    font-size: 20px;
                    margin:12px 0 8px;
                    color:#17a2b8;
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
                    <strong>Cumplimiento de objetivos y revisión por la dirección</strong>
                </td>

                <td class="text-center align-middle">
                    <strong>Fecha de autorización: 01-01-2024</strong>
                </td>
            </tr>

            <tr>
                <td class="text-center align-middle">
                    Fo.SGM.021
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

        $html .= $this->contenidoDatos($revision);
        $html .= $this->contenidoIndicadores($revision);
        $html .= $this->contenidoAsistentes($revision);

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
            'Cumplimiento de objetivos y revisión por la dirección.pdf',
            ['Attachment' => true]
        );

        exit;
    }

    private function contenidoDatos(CumplimientoObjetivosRevision $revision): string
    {
        return '
        <table class="table table-sm table-bordered">
            <tr>
                <td><b>Fecha:</b></td>
                <td>' . formatearFecha($revision->fecha) . '</td>
            </tr>
            <tr>
                <td><b>Hora:</b></td>
                <td>' . $revision->hora . '</td>
            </tr>
            <tr>
                <td><b>Lugar:</b></td>
                <td>' . $revision->lugar . '</td>
            </tr>
            <tr>
                <td><b>Responsable de la medición:</b></td>
                <td>' . $revision->responsable . '</td>
            </tr>
        </table>';
    }

    private function contenidoIndicadores(
        CumplimientoObjetivosRevision $revision
    ): string {
        $html = '';

        foreach ($revision->detalles as $detalle) {

            $meta = $detalle->categoria === 'Indicador: Satisfacción del cliente'
                ? 'Meta: disminuir 30% de reclamaciones contra el año inmediato anterior'
                : 'Meta: 100%';

            $html .= '
        <table class="table table-sm table-bordered">

            <tr class="bg-secondary text-white">
                <td colspan="3">
                    <b>' . $detalle->categoria . '</b>
                </td>
            </tr>

            <tr>
                <td><b>' . $meta . '</b></td>
                <td><b>Resultado</b></td>
                <td>' . $detalle->resultado1 . '</td>
            </tr>

            <tr>
                <td><b>Comentarios y observaciones:</b></td>
                <td colspan="2">' . $detalle->resultado2 . '</td>
            </tr>

            <tr>
                <td colspan="3">
                    <b>Acciones a tomar para mejorar o mantener el resultado:</b>
                </td>
            </tr>

            <tr>
                <td colspan="3">' . $detalle->resultado3 . '</td>
            </tr>

            <tr>
                <td colspan="3">
                    <b>Responsable de realizar las acciones:</b>
                </td>
            </tr>

            <tr>
                <td colspan="3">' . $detalle->resultado4 . '</td>
            </tr>

            <tr>
                <td colspan="3">
                    <b>Recursos necesarios:</b>
                </td>
            </tr>

            <tr>
                <td colspan="3">' . $detalle->resultado5 . '</td>
            </tr>

        </table>';
        }

        return $html;
    }

    private function contenidoAsistentes(
        CumplimientoObjetivosRevision $revision
    ): string {
        if ($revision->asistentes->isEmpty()) {
            return '';
        }

        $html = '
    <table class="table table-sm table-bordered mt-2">

        <tr class="bg-secondary text-white">
            <td><b>Nombre</b></td>
            <td width="180" class="text-center">
                <b>Firma</b>
            </td>
        </tr>';

        foreach ($revision->asistentes as $asistente) {

            $firma = '';

            if ($asistente->usuario?->firma) {

                $ruta = $_ENV['APP_URL'] . '/uploads/firma-personal/' . $asistente->usuario->firma;
                $firma = '<img width="100" src="' . $ruta . '">';
            }

            $html .= '
        <tr>
            <td>' . $asistente->usuario?->nombre . '</td>
            <td class="text-center">' . $firma . '</td>
        </tr>';
        }

        $html .= '</table>';

        return $html;
    }
}
