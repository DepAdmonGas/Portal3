<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Estacion;
use App\Models\Sasisopa\EvaluacionDesempeno;
use App\Models\Sasisopa\ImplementacionSasisopa;
use App\Models\Sasisopa\ImplementacionSasisopaProcedimientos;
use App\Models\Sasisopa\ImplementacionSasisopaProcedimientosPuesto;

use Illuminate\Database\Capsule\Manager as Capsule;

use Dompdf\Dompdf;
use Dompdf\Options;

class InformeDesempenoController extends BaseController{
    protected string $modulo = 'sasisopa';
    public function index(){

        $title = '18. INFORMES DE DESEMPEÑO';

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
                
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/informedesempeno/index.actions.init.js?v=1.1'
            ],
            'help' => true
        ];
        
        View::render('informedesempeno/index', $data,'sasisopa');

    }

    public function datatableEvaluacion(){

        header('Content-Type: application/json');

        try {

            $registros = EvaluacionDesempeno::with([
                'usuario'
            ])
            ->where(
                'id_estacion',
                $this->estacionId()
            )
            ->orderByDesc('fecha_hora')
            ->get();

            $data = [];

            foreach ($registros as $item) {

                $data[] = [

                    'id' => $item->id,

                    'fecha' => $item->fecha_hora
                        ? $item->fecha_hora->format('Y-m-d')
                        : '',

                    'fecha_larga' => $item->fecha_hora
                        ? formatearFecha(
                            $item->fecha_hora->format('Y-m-d')
                        )
                        : '',

                    'usuario' =>
                        $item->usuario?->nombre,

                    'archivo' =>
                        '/uploads/'.$item->archivo,

                    'tiene_archivo' =>
                        !empty($item->archivo),
                ];
            }

            echo json_encode([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    
    }

    public function createEvaluacion()
    {
        header('Content-Type: application/json');

        try {

            if (!isset($_FILES['archivo'])) {

                throw new \Exception(
                    'Debe seleccionar un PDF'
                );
            }

            $fecha = $_POST['fecha'];
            $archivo = $_FILES['archivo'];

            $nombreArchivo =
                $this->estacionId()
                . '-EVALUACION-'
                . time()
                . '.pdf';

            $rutaFisica = __DIR__ . '../../../public/uploads/archivos/evaluacion-desempeño/' . $nombreArchivo;

        if (!move_uploaded_file(
                    $archivo['tmp_name'],
                    $rutaFisica
                )) {

                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al subir archivo'
                    ]);
                    exit;
                }

            $revision =
                EvaluacionDesempeno::create([

                    'id_estacion' =>
                        $this->estacionId(),

                    'id_usuario' =>
                        $this->userId(),

                    'fecha_hora' =>
                        $fecha . ' ' . date('H:i:s'),

                    'archivo' =>
                        'archivos/evaluacion-desempeño/'
                        . $nombreArchivo
                ]);

            echo json_encode([
                'success' => true,
                'id' => $revision->id
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

    public function updateEvaluacion()
    {
        header('Content-Type: application/json');

        try {

            $revision =
                EvaluacionDesempeno::find(
                    $_POST['id']
                );

            if (!$revision) {

                throw new \Exception(
                    'Registro no encontrado'
                );
            }

            $revision->fecha_hora =
                $_POST['fecha']
                . ' '
                . date('H:i:s');

            if (isset($_FILES['archivo'])) {

                if (
                    !empty($revision->archivo)
                    &&
                    file_exists(
                        __DIR__ . '../../../public/uploads/'
                        . $revision->archivo
                    )
                ) {

                    unlink(
                        __DIR__ . '../../../public/uploads/'
                        . $revision->archivo
                    );
                }

                $nombreArchivo =
                    $revision->id
                    . '-EVALUACION-'
                    . time()
                    . '.pdf';

                $rutaFisica =
                    __DIR__ . '../../../public/uploads/archivos/evaluacion-desempeño/'
                    . $nombreArchivo;

                move_uploaded_file(
                    $_FILES['archivo']['tmp_name'],
                    $rutaFisica
                );

                $revision->archivo =
                    'archivos/evaluacion-desempeño/'
                    . $nombreArchivo;
            }

            $revision->save();

            echo json_encode([
                'success' => true,
                'message' => 'Registro actualizado'
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

    public function deleteEvaluacion()
    {
        header('Content-Type: application/json');

        try {

            $data = json_decode(
                file_get_contents('php://input'),
                true
            );

            $revision = EvaluacionDesempeno::find(
                $data['id'] ?? 0
            );

            if (!$revision) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Registro no encontrado'
                ]);

                exit;
            }

            // Eliminar archivo físico si existe
            if (
                !empty($revision->archivo) &&
                file_exists(
                    __DIR__ . '../../../public/uploads/' . $revision->archivo
                )
            ) {

                unlink(
                    __DIR__ . '../../../public/uploads/' . $revision->archivo
                );
            }

            $revision->delete();

            echo json_encode([
                'success' => true,
                'message' => 'Registro eliminado correctamente'
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

    //---------------------------------------------------------------------
    //---------------------------------------------------------------------

    public function datatableImplementacion(){

    header('Content-Type: application/json');

        try {

            $registros = ImplementacionSasisopa::with([
                'usuario',
            ])
            ->where(
                'id_estacion',
                $this->estacionId()
            )
            ->orderByDesc('fecha_hora')
            ->get();

            $data = [];

            foreach ($registros as $item) {

                $data[] = [

                    'id' => $item->id,

                    'fecha' => $item->fecha_hora
                        ? $item->fecha_hora->format('Y-m-d')
                        : '',

                    'fecha_larga' => $item->fecha_hora
                        ? formatearFecha(
                            $item->fecha_hora->format('Y-m-d')
                        )
                        : '',

                    'usuario' =>
                        $item->usuario?->nombre,

                    'archivo' =>
                        '/uploads/'.$item->archivo,

                    'tiene_archivo' =>
                        !empty($item->archivo),
                ];
            }

            echo json_encode([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;

    }


    public function createImplementacion()
    {
        header('Content-Type: application/json');

        try {

            $implementacion = Capsule::transaction(function () {

                $reporte = ImplementacionSasisopa::create([
                    'id_estacion' => $this->estacionId(),
                    'id_usuario' => $this->userId(),
                    'fecha_hora' => date('Y-m-d H:i:s')
                ]);

                $procedimientos = [
                    'I. Política.',
                    'II. Identificación de peligros y aspectos ambientales, análisis de riesgo y evaluación de impactos ambientales.',
                    'III. Requisitos legales.',
                    'IV. Objetivos, metas, indicadores.',
                    'V. Funciones, responsabilidades y autoridad.',
                    'VI. Competencia del personal, capacitación y entrenamiento',
                    'VII. Comunicación, participación y consulta.',
                    'VIII. Control de documentos y registros.',
                    'IX. Mejores prácticas y estándares.',
                    'X. Control de actividades y procesos.',
                    'XI. Integridad mecánica y aseguramiento de la calidad.',
                    'XII. Seguridad de contratistas.',
                    'XIII. Preparación y respuesta a emergencias.',
                    'XIV. Monitoreo, verificación y evaluación.',
                    'XV. Auditorías.',
                    'XVI. Investigación de incidentes y accidentes.',
                    'XVII. Revisión de resultados.',
                    'XVIII. Informes de desempeño.'
                ];

                foreach ($procedimientos as $procedimiento) {

                    ImplementacionSasisopaProcedimientos::create([

                        'id_reporte' => $reporte->id,
                        'fecha_implementacion' => '',
                        'procedimiento' => $procedimiento,
                        'descripcion' => '',
                        'informacion' => '',
                        'observaciones' => ''
                    ]);
                }

                return $reporte;
            });

            echo json_encode([
                'success' => true,
                'id' => $implementacion->id,
                'message' => 'Registro creado correctamente'
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

    public function deleteImplementacion()
    {
        header('Content-Type: application/json');

        try {

            $data = json_decode(
                file_get_contents('php://input'),
                true
            );

            $implementacion =
                ImplementacionSasisopa::with('procedimientos')
                    ->find($data['id']);

            if (!$implementacion) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Registro no encontrado'
                ]);

                exit;
            }

            Capsule::transaction(function () use ($implementacion) {

                $idsProcedimientos =
                    $implementacion
                        ->procedimientos
                        ->pluck('id');

                ImplementacionSasisopaProcedimientosPuesto
                    ::whereIn(
                        'id_reporte',
                        $idsProcedimientos
                    )
                    ->delete();

                $implementacion
                    ->procedimientos()
                    ->delete();

                $implementacion
                    ->delete();

            });

            echo json_encode([
                'success' => true
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

    public function pdfImplementacion($id){

    $estacion = Estacion::find(
            $this->estacionId()
        );
     $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

      $reporte = ImplementacionSasisopa::with([
        'procedimientos.puestos'
    ])->findOrFail($id);


     $html = '
        <!DOCTYPE html>
        <html>
        <head>

            <meta charset="UTF-8">
            <title>Registro de la atención y el seguimiento a la comunicación interna y externa</title>

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

        article, aside, dialog, figcaption, figure, footer, header, hgroup, main, nav, section {
        display: block;
        }
        body {
        margin: 0;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
        font-size: .85rem;
        font-weight: 400;
        line-height: 1.5;
        color: #212529;
        text-align: left;
        background-color: #fff;
        }

        .text-center {
        text-align: center !important;
        }
        .p-1 {
        padding: 0.25rem !important;
        }
        .mt-1 {
        margin-top: 0.25rem !important;
        }
        .mt-3 {
        margin-top: 1rem !important;
        }
        .mt-4 {
        margin-top: 1.5rem !important;
        }
        table {
        border-collapse: collapse;
        }
        .table {
        width: 100%;
        max-width: 100%;
        margin-bottom: 1rem;
        background-color: transparent;
        }

        .table th,
        .table td {
        padding: 0.75rem;
        vertical-align: top;
        border-top: 1px solid #dee2e6;
        }

        .table thead th {
        vertical-align: bottom;
        border-bottom: 2px solid #dee2e6;
        }

        .table tbody + tbody {
        border-top: 2px solid #dee2e6;
        }

        .table .table {
        background-color: #fff;
        }

        .table-sm th,
        .table-sm td {
        padding: 0.3rem;
        }

        .table-bordered {
        border: 1px solid #dee2e6;
        }

        .table-bordered th,
        .table-bordered td {
        border: 1px solid #dee2e6;
        }

        .table-bordered thead th,
        .table-bordered thead td {
        border-bottom-width: 2px;
        }
        .table-bordered {
        border: 1px solid #dee2e6;
        }

        .table-bordered th,
        .table-bordered td {
        border: 1px solid #dee2e6;
        }

        .table-bordered thead th,
        .table-bordered thead td {
        border-bottom-width: 2px;
        }
        .table-sm th,
        .table-sm td {
        padding: 0.3rem;
        }
        .align-middle {
        vertical-align: middle !important;
        }
        small {
        font-size: 80%;
        }
        .table-active,
        .table-active > th,
        .table-active > td {
        background-color: rgba(0, 0, 0, 0.075);
        }

        .table thead th {
        vertical-align: bottom;
        border-bottom: 2px solid #dee2e6;
        }

        .table tbody + tbody {
        border-top: 2px solid #dee2e6;
        }

        img {
        vertical-align: middle;
        border-style: none;
        }

        .table-success,
        .table-success > th,
        .table-success > td {
        background-color: #c3e6cb;
        }

        .table-info,
        .table-info > th,
        .table-info > td {
        background-color: #bee5eb;
        }
        
            </style>

        </head>

        <body>

            <table class="table table-bordered">

                <tr>

                    <td class="text-center align-middle">
                        <img src="'.$logo.'" width="150">
                    </td>

                    <td colspan="2" class="text-center align-middle">
                        <strong>
                            Control de la implementación de los procedimientos del SASISOPA.
                        </strong>
                    </td>

                    <td class="text-center align-middle">
                        Fo.ADMONGAS.029
                    </td>

                </tr>

                <tr>

                    <td class="text-center align-middle">
                        Realizado por:
                        Nelly Estrada Garcia
                    </td>

                    <td class="text-center align-middle">
                        Revisado por:
                        Eduardo Galicia Flores
                    </td>

                    <td class="text-center align-middle">
                        Autorizado por:
                        '.$estacion->apoderado_legal.'
                    </td>

                    <td class="text-center align-middle">
                        Fecha autorización
                        01-Oct-2018
                    </td>

                </tr>

            </table>
            <br>
            
            <table class="table table-bordered table-sm">
            <thead>
            <tr>
            <th class="text-center align-middle">Fecha implementación</th>
            <th class="text-center align-middle">Nombre del procedimiento</th>
            <th class="text-center align-middle">Breve descripción de la implementación</th>
            <th class="text-center align-middle">Se dio a conocer la implementación
            <div><label class="border-right pr-3 pl-2">Si</label> <label class="pl-2 pr-2">No</label>
            </th>
            <th class="text-center align-middle">Puestos de personal enterados de la implementación</th>
            <th class="text-center align-middle">Observaciones</th>
            </tr>
            </thead>
            <tbody>';

            foreach($reporte->procedimientos as $item):

            $html .= '<tr>
            <td class="text-center align-middle">';

            $html .= $item->fecha_implementacion &&
            $item->fecha_implementacion->format('Y-m-d') != '-0001-11-30'
            ? formatearFecha(
            $item->fecha_implementacion->format('Y-m-d')
            )
            : '';

      

            $html .= '</td>
            <td class="align-middle">
            <strong>'.htmlspecialchars($item->procedimiento).'</strong>
            </td>
            <td class="align-middle">
            '.nl2br(htmlspecialchars($item->descripcion)).'
            </td>
            <td class="text-center align-middle">
            '.htmlspecialchars($item->informacion).'
            </td>
            <td class="align-middle"><small>';
         
            $html .= $item->puestos
                ->pluck('puesto')
                ->implode(', ');


            $html .= '</small></td>
            <td class="align-middle">
            '.nl2br(htmlspecialchars($item->observaciones)).'
            </td>
            </tr>';

            endforeach;


        $html .= '
        </tbody>
        </table>

        </body>
        </html>';

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper(
            'A4',
            'landscape'
        );

        $dompdf->render();

        $dompdf->stream(
            'Control de la implementación de los procedimientos del SASISOPA.pdf',
            [
                'Attachment' => true
            ]
        );

        exit;
    
    }

    public function indexEditar(int $id){

     $title = 'CONTROL DE LA IMPLEMENTACIÓN DE LOS PROCEDIMIENTOS DEL SASISOPA';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('18. INFORMES DE DESEMPEÑO', '/sasisopa/informes-desempeno');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

               $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'idReporte' => $id,
             'links' =>[
                
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/informedesempeno/editar.actions.init.js?v=1.1'
            ],
            'help' => false
        ];
        
        View::render('informedesempeno/editar', $data,'sasisopa');

    }

    public function editarImplementacionDatatable($idReporte)
    {
        header('Content-Type: application/json');

        try {

            $reporte = ImplementacionSasisopa::with([
                'procedimientos.puestos'
            ])->findOrFail($idReporte);

            $data = $reporte->procedimientos->map(function ($item) {

                return [

                    'id' => $item->id,

                    'fecha_implementacion' =>
                    $item->fecha_implementacion &&
                    $item->fecha_implementacion->format('Y-m-d') !== '-0001-11-30'
                        ? $item->fecha_implementacion->format('Y-m-d')
                        : '',

                'fecha_implementacion_larga' =>
                    $item->fecha_implementacion &&
                    $item->fecha_implementacion->format('Y-m-d') !== '-0001-11-30'
                        ? formatearFecha($item->fecha_implementacion->format('Y-m-d'))
                        : '',

                    'procedimiento' =>
                        $item->procedimiento,

                    'descripcion' =>
                        $item->descripcion,

                    'informacion' =>
                        $item->informacion,

                    'observaciones' =>
                        $item->observaciones,

                    'puestos' =>
                        $item->puestos->map(function ($puesto) {

                            return [

                                'id' => $puesto->id,
                                'id_lista' => $puesto->id_lista,
                                'puesto' => $puesto->puesto

                            ];

                        })

                ];

            });

            echo json_encode([
                'success' => true,
                'fecha' => $reporte->fecha_hora->format('Y-m-d'),
                'procedimientos' => $data
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);

        }

        exit;
    }

    public function updateFechaReporte()
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);

        $reporte = ImplementacionSasisopa::find($data['id']);

        if (!$reporte) {

            echo json_encode([
                'success'=>false
            ]);

            exit;

        }

        $hora = $reporte->fecha_hora->format('H:i:s');

        $reporte->fecha_hora =
            $data['fecha'].' '.$hora;

        $reporte->save();

        echo json_encode([
            'success'=>true
        ]);

        exit;
    }

    public function updateFechaImplementacion()
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);

        $registro =
            ImplementacionSasisopaProcedimientos::find(
                $data['id']
            );

        if(!$registro){

            echo json_encode([
                'success'=>false
            ]);

            exit;
        }

        $registro->fecha_implementacion =
            $data['fecha'];

        $registro->save();

        echo json_encode([
            'success'=>true
        ]);

        exit;
    }

    public function updateDescripcionImplementacion()
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);

        $registro =
            ImplementacionSasisopaProcedimientos::find(
                $data['id']
            );

        if(!$registro){

            echo json_encode([
                'success'=>false
            ]);

            exit;
        }

        $registro->descripcion =
            $data['descripcion'];

        $registro->save();

        echo json_encode([
            'success'=>true
        ]);

        exit;
    }

    public function updateObservacionImplementacion()
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);

        $registro =
            ImplementacionSasisopaProcedimientos::find(
                $data['id']
            );

        if(!$registro){

            echo json_encode([
                'success'=>false
            ]);

            exit;
        }

        $registro->observaciones =
            $data['observaciones'];

        $registro->save();

        echo json_encode([
            'success'=>true
        ]);

        exit;
    }

    public function updateInformacionImplementacion()
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);

        $registro =
            ImplementacionSasisopaProcedimientos::find(
                $data['id']
            );

        if(!$registro){

            echo json_encode([
                'success'=>false
            ]);

            exit;
        }

        $registro->informacion =
            $data['informacion'];

        $registro->save();

        echo json_encode([
            'success'=>true
        ]);

        exit;
    }

    public function createPuestoImplementacion()
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);

        $existe =
            ImplementacionSasisopaProcedimientosPuesto
                ::where('id_reporte',$data['procedimiento'])
                ->where('id_lista',$data['id_lista'])
                ->exists();

        if(!$existe){

            ImplementacionSasisopaProcedimientosPuesto::create([

                'id_reporte' =>
                    $data['procedimiento'],

                'id_lista' =>
                    $data['id_lista'],

                'puesto' =>
                    $data['puesto']

            ]);

        }

        echo json_encode([
            'success'=>true
        ]);

        exit;
    }

    public function deletePuestoImplementacion()
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);

        ImplementacionSasisopaProcedimientosPuesto
            ::where(
                'id_reporte',
                $data['procedimiento']
            )
            ->where(
                'id_lista',
                $data['puesto']
            )
            ->delete();

        echo json_encode([
            'success'=>true
        ]);

        exit;
    }
}