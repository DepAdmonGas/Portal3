<?php
namespace App\Controllers;
use App\Core\View;
use App\Services\ModuloService;
use App\Core\Breadcrumb;
use App\Models\Estacion;
use App\Models\Sasisopa\RequisitosLegalesCalendario;
use App\Models\Sasisopa\RequisitosLegalesMatriz;
use App\Models\Sasisopa\InformeRevisionResultado;

use Dompdf\Dompdf;
use Dompdf\Options;

class EvaluacionRequisitosLegalesController extends BaseController{

protected string $modulo = 'sasisopa';

    public function index(){

        $title = 'EVALUACIÓN Y CUMPLIMIENTO DE REQUISITOS LEGALES';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('14. MONITOREO, VERIFICACIÓN Y EVALUACIÓN', '/sasisopa/monitoreo-verificacion-evaluacion');
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
                '/js/monitoreoverificacionevaluacion/evaluacionrequisitoslegales.actions.init.js?v=1.0'
            ],
            'help' => false
        ];
        
        View::render('monitoreoverificacionevaluacion/evaluacion-requisitos-legales', $data,'sasisopa');
    }

    public function pdf(){

     $matriz = self::getMatrizCumplimiento(
        $this->estacionId()
    );

    $grupos = collect($matriz)
        ->groupBy('nivel_gobierno');

    $porcentajeGeneral = (int) round(
        $grupos
            ->map(
                fn ($items) => self::porcentajeGrupo(
                    $items->toArray()
                )
            )
            ->avg()
    );
     

    $estacion = Estacion::find(
            $this->estacionId()
        );
    $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

    $baseCheck = $_ENV['APP_URL'] . '/assets/images/svgs/success.svg';

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <title>Calendario de calibraciones</title>

        <style>

            @page {
                margin: 0.5cm;
                font-family: Arial, Helvetica, sans-serif;
            }

            body{
                font-family: Arial, Helvetica, sans-serif;
                font-size:16px;
            }

            table{
                width:100%;
                border-collapse:collapse;
            }

            .table th,
            .table td{
                border:1px solid #dee2e6;
                padding:4px;
            }

            .text-center{
                text-align:center;
            }

            .align-middle{
                vertical-align:middle;
            }

            .table-success{
                background:#c3e6cb;
            }

            .table-warning{
                background:#ffeeba;
            }

            .mb-2,
            .my-2 {
            margin-bottom: 0.5rem !important;
            }

            .bg-secondary {
            background-color: #6c757d !important;
            }

            .bg-primary {
                background-color: #007bff !important;
                }

                .text-right {
                text-align: right !important;
                }

                .text-white {
                color: #fff !important;
                }

        </style>
    </head>

    <body>

        <table class="table mb-2">

            <tr>

                <td class="text-center align-middle">
                    <img src="' . $logo . '" width="150">
                </td>

                <td colspan="2" class="text-center align-middle">
                    <strong>Matriz de evaluación del cumplimiento legal</strong>
                </td>

                <td class="text-center align-middle">
                    Fo.ADMONGAS.021
                </td>

            </tr>

            <tr>

                <td class="text-center align-middle">
                    Realizado por: Nelly Estrada Garcia
                </td>

                <td class="text-center align-middle">
                    Revisado por: Eduardo Galicia Flores
                </td>

                <td class="text-center align-middle">
                    Autorizado por: ' . $estacion->apoderado_legal . '
                </td>

                <td class="text-center align-middle">
                    Fecha de autorización 01/10/2018
                </td>

            </tr>

        </table>
         <table class="table">

            <thead>

                <tr>

                    <th class="text-center">
                        Nivel de gobierno
                    </th>

                    <th class="text-center">
                        Nombre del requisito legal
                    </th>

                    <th class="text-center">
                        Vigencia
                    </th>

                    <th class="text-center">
                        Acuse
                    </th>

                    <th class="text-center">
                        Requisito legal
                    </th>

                    <th class="text-center">
                        % cumplimiento
                    </th>

                </tr>

            </thead>

            <tbody>
       ';

       foreach ($grupos as $nivel => $items) {

            $html .= self::renderFilasNivel(
                $nivel,
                $items->toArray(),
                '<img src="'.$baseCheck.'" width="18">'
            );
        }


    $html .= ' <tr>

                <td colspan="6"
                    class="bg-primary text-white text-center">

                    <div style="font-size:18px;">

                        <b>
                            Porcentaje de cumplimiento general
                            '.$porcentajeGeneral.' %
                        </b>

                    </div>

                </td>

            </tr>

        </tbody>

    </table>

    </body>
    </html>';

    $options = new Options();
    $options->set('isRemoteEnabled',true);
    $options->set('defaultFont','Arial');
    $dompdf = new Dompdf($options);

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4','landscape');
    $dompdf->render();

    $dompdf->stream(
        'Matriz de evaluación del cumplimiento legal.pdf',
        [
            'Attachment' => true
        ]
    );

    exit;
    }

    private static function porcentajeGrupo(
    array $items
    ): int {

        if (empty($items)) {
            return 0;
        }

        return (int) round(collect($items)->avg('cumplimiento'));
    }

    public static function getMatrizCumplimiento(
    int $estacionId
    ): array {
        
        $requisitos = RequisitosLegalesCalendario::query()
            ->where('id_estacion', $estacionId)
            ->where('estado', 1)
            ->orderBy('nivel_gobierno')
            ->get();

        $data = [];

        foreach ($requisitos as $requisito) {

            $ultima = RequisitosLegalesMatriz::query()
                ->where('idcalendario', $requisito->id)
                ->latest('id')
                ->first();

            $acuse = !empty($ultima?->acusepdf);
            $legal = !empty($ultima?->requisitolegalpdf);

            $cumplimiento = match (true) {
                !$acuse && !$legal => 0,
                $acuse && !$legal => 50,
                default => 100
            };

            $data[] = [
                'nivel_gobierno' => $requisito->nivel_gobierno,
                'requisito_legal' => $requisito->requisito_legal,
                'vigencia' => $requisito->vigencia,
                'acuse' => $acuse,
                'requisito' => $legal,
                'cumplimiento' => $cumplimiento
            ];
        }

        return $data;
    }

    private static function renderFilasNivel(
    string $nivel,
    array $items,
    string $check
    ): string {

        $html = '';

        foreach ($items as $item) {

            $html .= '
            <tr>
                <td class="text-center align-middle">
                    '.$nivel.'
                </td>
                <td class="align-middle">
                    '.$item['requisito_legal'].'
                </td>
                <td class="text-center align-middle">
                    '.$item['vigencia'].'
                </td>
                <td class="text-center align-middle">
                    '.($item['acuse'] ? $check : '').'
                </td>
                <td class="text-center align-middle">
                    '.($item['requisito'] ? $check : '').'
                </td>
                <td class="text-center align-middle">
                    '.$item['cumplimiento'].' %
                </td>
            </tr>';
        }

        $html .= '
        <tr>
            <td colspan="6"
                class="bg-secondary text-white text-right">
                <b>
                    % de cumplimiento por nivel de gobierno
                    '.$nivel.'
                    '.self::porcentajeGrupo($items).' %
                </b>
            </td>
        </tr>';

        return $html;
    }

    public function datatable()
    {

            $informes = InformeRevisionResultado::query()
                ->where(
                    'id_estacion',
                    $this->estacionId()
                )
                ->orderByDesc('fecha')
                ->get()
                ->values()
                ->map(function ($item, $index) {

                    return [

                        'id' => $item->id,
                        'numero' => $index + 1,
                        'fecha' => $item->fecha?->format('Y-m-d'),
                        'fecha_larga' => formatearFecha($item->fecha?->format('Y-m-d')),
                        'archivo' => $item->archivo,
                        'url_pdf' =>
                            '/uploads/archivos/informe-revision-resultados/' .
                            $item->archivo
                        
                    ];

                })
                ->toArray();

            echo json_encode([
                'data' => $informes,
                'permisos' => [
                'eliminar' => ModuloService::validaPermiso(
                    $this->modulo,
                    'eliminar'
                ),

                'editar' => ModuloService::validaPermiso(
                    $this->modulo,
                    'editar'
                )
            ]
            ]);

    }

    public function create(){

    header('Content-Type: application/json');

    try {

        $fecha = sanitize_input($_POST['fecha'] ?? '','string');

        if(empty($fecha)){

            echo json_encode([
                'success' => false,
                'message' => 'La fecha es obligatoria'
            ]);

            return;
        }

        $rutaBd = '';

        if(!empty($_FILES['documento'])
            &&
            $_FILES['documento']['error']
                === UPLOAD_ERR_OK
        ){

            $carpeta =
                __DIR__
                . '../../../public/uploads/archivos/informe-revision-resultados/';

            if(!file_exists($carpeta)){

                mkdir_safe(
                    $carpeta,
                    true
                );
            }

            $nombre =
                'Informe-revision-resultados-'
                .$this->estacionId()
                .'-'
                .time()
                .'.pdf';

            move_uploaded_file(
                $_FILES['documento']['tmp_name'],
                $carpeta.$nombre
            );

            $rutaBd = $nombre;
        }

        InformeRevisionResultado::create([

            'id_estacion' =>
                $this->estacionId(),

            'fecha' =>
                $fecha,

            'archivo' =>
                $rutaBd
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Informe agregado'
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }

    exit;

    }

    public function delete()
    {
        header('Content-Type: application/json');

        try {

            $data = json_decode(file_get_contents('php://input'),true);
            $id = (int)($data['id'] ?? 0);
            $informe = InformeRevisionResultado::find($id);

            if(!$informe){

                echo json_encode([
                    'success' => false,
                    'message' => 'Registro no encontrado'
                ]);

                return;
            }

            $informe->delete();

            echo json_encode([
                'success' => true,
                'message' => 'Protocolo eliminado'
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

}