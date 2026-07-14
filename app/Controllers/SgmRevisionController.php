<?php

namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Sgm\RevisionProcedimientoRegistro;
use App\Models\Sgm\RevisionProcedimientoRegistroDetalle;
use App\Models\Sgm\Autorizado;
use Dompdf\Dompdf;
use Dompdf\Options;

use Illuminate\Database\Capsule\Manager as Capsule;
class SgmRevisionController extends BaseController{

    protected string $modulo = 'sgm';

        public function datatable(){

        $permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');
        $permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');
        $permisoDescargar   = ModuloService::validaPermiso($this->modulo, 'descargar');

        $data = RevisionProcedimientoRegistro::where('id_estacion', $this->estacionId())
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

    public function createRevision(){

    header('Content-Type: application/json; charset=utf-8');
    $data = json_decode(file_get_contents('php://input'), true);

     $realizadoPor = Autorizado::query()
        ->join('tb_usuarios', 'sgm_autorizado.id_usuario', '=', 'tb_usuarios.id')
        ->where('tb_usuarios.id_gas', $this->estacionId())
        ->where('sgm_autorizado.estado', 1)
        ->value('id_usuario') ?? 0;

    $revision = RevisionProcedimientoRegistro::create([
        'id_estacion'   => $this->estacionId(),
        'id_usuario'    => $this->userId(),
        'fecha'         => date('Y-m-d'),
        'hora'          => date('H:m:s'),
        'lugar'         => '',
        'elemento'      => $data['puntosgm'],
        'realizadopor'  => $realizadoPor,
        'estado'        => 0,
    ]);

    $this->crearDetalleRevision($revision->id);

    echo json_encode([
        'success' => true,
        'id'      => $revision->id,
    ]);

    }

    private function crearDetalleRevision(int $idRevision): void
    {
        $preguntas = [

            'SGM' => [
                'A la fecha del presente se han realizado cambios por nueva legislación:',
                'Cuales:',
                'Los cambios fueron registrados en el SGM en el apartado de control de revisiones:',
                'El cuerpo del SGM, mantienen su estructura de elaboración',
                'Se da a conocer a la alta dirección la revisión del SGM',
            ],

            'Procedimientos' => [
                'A la fecha del presente se han realizado cambios por nueva legislación:',
                'Cuales:',
                'Los cambios fueron registrados en el manual de procedimientos en el apartado de control de revisiones:',
                'El cuerpo del SGM, mantienen su estructura de elaboración',
                'Se da a conocer a la alta dirección la revisión de los procedimientos del SGM',
            ],

            'Registros' => [
                'A la fecha del presente se han realizado cambios por nueva legislación:',
                'Cuales:',
                'Los cambios fueron registrados en el manual de procedimientos en el apartado de control de revisiones y codificados por el responsable del SGM:',
                'El cuerpo del SGM, mantienen su estructura de elaboración',
                'Se da a conocer a la alta y a los involucrados los cambios en los formatos del SGM',
            ],

        ];

        $detalle = [];

        foreach ($preguntas as $categoria => $items) {

            foreach ($items as $pregunta) {

                $detalle[] = [
                    'id_revision' => $idRevision,
                    'categoria'   => $categoria,
                    'pregunta'    => $pregunta,
                    'respuesta'   => '',
                ];

            }
        }

        RevisionProcedimientoRegistroDetalle::insert($detalle);
    }

    public function deleteRevision(){

    header('Content-Type: application/json; charset=utf-8');
    $data = json_decode(file_get_contents('php://input'), true);

    $id = $data['id'];

   Capsule::transaction(function () use ($id) {

        $revision = RevisionProcedimientoRegistro::findOrFail($id);

        $revision->detalles()->delete();

        $revision->delete();

    });

    echo json_encode([
        'success' => true,
        'message' => 'Revisión eliminada correctamente'
    ]);

    }

    //-----------------------------------------------------------

    public function revisionIndex(int $id){

    $revision = RevisionProcedimientoRegistro::find($id);

    $subTitle = '';
    $subUrl = '';

    if($revision->elemento == 101){
        $subTitle = '1. Estructura del sistema de Medicion';
        $subUrl = '/sgm/estructura-sistema-medicion';
    }else if($revision->elemento == 102){
        $subTitle = '2. Control del documental del Sistema de Gestion de medición';
        $subUrl = '/sgm/control-documental-sistema-gestion-medicion';
    }

    $title = 'Revisión del SGM, procedimientos y registros';
    Breadcrumb::add('Home', '/home');
    Breadcrumb::add('SGM', '/sgm');
    Breadcrumb::add($subTitle, $subUrl);
    Breadcrumb::add($title, '');
    $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'id' => $id,
            'links' =>[
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/sgm/revision/edit.action.init.js?v=1.0.2',
            ],
            'help' => false
        ];
        
        View::render('sgm/revision/index', $data,'sgm');

    }

    public function detalleRevision(int $id)
    {
        $revision = RevisionProcedimientoRegistro::with('detalles')
            ->where('id', $id)
            ->where('id_estacion', $this->estacionId())
            ->firstOrFail();

        header('Content-Type: application/json');

        echo json_encode([
            'id' => $revision->id,
            'fecha' => $revision->fecha?->format('Y-m-d'),
            'hora' => $revision->hora,
            'lugar' => $revision->lugar,
            'estado' => $revision->estado,

            'categorias' => $revision->detalles
                ->groupBy('categoria')
                ->map(fn($items) => $items->values())
                ->toArray()
        ]);
    }

    public function updateRevision()
    {
        header('Content-Type: application/json; charset=utf-8');

        $data = json_decode(file_get_contents('php://input'), true);

        $revision = RevisionProcedimientoRegistro::findOrFail($data['id']);

        $revision->update([
            $data['campo'] => $data['valor']
        ]);

        echo json_encode([
            'success' => true
        ]);
    }

    public function updateRevisionDetalle()
    {
        header('Content-Type: application/json; charset=utf-8');

        $data = json_decode(file_get_contents('php://input'), true);

        $detalle = RevisionProcedimientoRegistroDetalle::findOrFail($data['id']);

        $detalle->update([
            'respuesta' => $data['respuesta']
        ]);

        echo json_encode([
            'success' => true
        ]);
    }

    public function finalizarRevision()
    {

    header('Content-Type: application/json; charset=utf-8');
    $data = json_decode(file_get_contents('php://input'), true);

        RevisionProcedimientoRegistro::findOrFail($data['id'])
            ->update([
                'estado'=>1
            ]);

        echo json_encode([
        'success' => true
    ]);
    }

    public function pdfRevision(int $id)
    {
        header('Content-Type: application/pdf');

        $estacion = Estacion::findOrFail($this->estacionId());

        $revision = RevisionProcedimientoRegistro::with('detalles')
            ->where('id', $id)
            ->where('id_estacion', $this->estacionId())
            ->firstOrFail();

        $realizadoPor = 'S/I';

        if ($revision->realizadopor) {
            $realizadoPor = Usuario::find($revision->realizadopor)?->nombre ?? 'S/I';
        }

        $categorias = $revision->detalles->groupBy('categoria');

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">

            <title>Revisión del SGM, procedimientos y registros</title>

            <link rel="stylesheet" href="' . $_ENV['APP_URL'] . '/assets/css/pdf.css">

            <style>

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
                    '.$estacion->razonsocial.'
                </td>

                <td rowspan="2" class="text-center align-middle">
                    <strong>Revisión del SGM, procedimientos y registros</strong>
                </td>

                <td class="text-center align-middle">
                    <strong>Fecha de autorización: 01-01-2024</strong>
                </td>
            </tr>

            <tr>
                <td class="text-center align-middle">
                    Fo.SGM.002
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

        </table>

        <table class="table table-bordered">
            <tr>

                <td>
                    <strong>Fecha:</strong><br>
                    '.formatearFecha($revision->fecha).'
                </td>

                <td>
                    <strong>Hora:</strong><br>
                    '.date('g:i a', strtotime($revision->hora)).'
                </td>

                <td>
                    <strong>Lugar:</strong><br>
                    '.htmlspecialchars($revision->lugar).'
                </td>

            </tr>

        </table>';
            foreach (['SGM', 'Procedimientos', 'Registros'] as $categoria) {

            $html .= "<h4>{$categoria}</h4>";

            foreach ($categorias->get($categoria, collect()) as $detalle) {

                $html .= '
                    <h5>'.htmlspecialchars($detalle->pregunta).'</h5>

                    <div class="respuesta">
                        '.nl2br(htmlspecialchars($detalle->respuesta)).'
                    </div>';
            }

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
            'Revision-del-SGM-procedimientos-y-registros.pdf',
            ['Attachment' => true]
        );

        exit;
    }

}