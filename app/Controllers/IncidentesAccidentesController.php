<?php 
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Estacion;
use App\Models\Sasisopa\InvestigacionIncidenteAccidenteNo;
use App\Models\Sasisopa\InvestigacionIncidenteAccidente;
use App\Models\Sasisopa\InvestigacionIncidenteAccidenteFormato;
use App\Models\Sasisopa\InvestigacionIncidenteAccidenteTercerautorizado;
use App\Models\Sasisopa\InvestigacionIncidenteAccidenteGrupo;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Capsule\Manager as Capsule;
class IncidentesAccidentesController extends BaseController 
{
    protected string $modulo = 'sasisopa';

    public function index(){

        $title = '16. INVESTIGACIÓN DE INCIDENTES Y ACCIDENTES';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);
        $estacion = Estacion::find($this->estacionId()); 

         $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'estacion' => $estacion,
             'links' =>[
                
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/incidentesaccidentes/index.actions.init.js?v=1.3'
            ],
            'help' => true
        ];
        
        View::render('incidentesaccidentes/index', $data,'sasisopa');

    }

    //-------- investigación de incidentes y accidentes ----------
    //------------------------------------------------------------

    public function datatableInvestigaciones()
{
    header('Content-Type: application/json');

    try {

        $registros =
            InvestigacionIncidenteAccidente::with([
                'usuario.puesto',
                'grupos',
                'formatos',
                'terceroAutorizado'
            ])
            ->where(
                'id_estacion',
                $this->estacionId()
            )
            ->orderByDesc('id')
            ->get();

        $data = [];

        foreach ($registros as $item) {

            $formato026 =
                $item->formatos
                    ->first();

            $data[] = [

                'id' => $item->id,

                'fecha' =>
                    $item->fechacreacion
                        ->format('Y-m-d'),

                'fecha_larga' =>
                    formatearFecha(
                        $item->fechacreacion
                            ->format('Y-m-d')
                    ),

                'usuario' =>

                    $item->usuario?->nombre,

                'puesto' =>

                    $item->usuario?->puesto?->tipo_puesto,

                'descripcion' =>
                    $item->descripcion,

                'tipo_evento' =>
                    $item->tipo_evento,

                'muertes' =>
                    $item->muertes > 0,

                'grupo' =>

                    $item->grupos
                        ->count(),

                'tercer_autorizado' =>

                    (bool)
                    $item->tercer_autorizado,

                 'tercer_autorizado_detalle' => [

                 'id' =>
                        $item->terceroAutorizado?->id,

                    'nombre' =>
                        $item->terceroAutorizado?->nombre,

                    'numero' =>
                        $item->terceroAutorizado?->numero,

                    'lider' =>
                        $item->terceroAutorizado?->lider,

                    'fecha' =>
                        $item->terceroAutorizado?->fecha->format('Y-m-d'),

                    'fecha_larga' =>
                    formatearFecha(
                        $item->terceroAutorizado?->fecha->format('Y-m-d')
                    ),

                    'archivo' =>
                        $item->terceroAutorizado?->archivo
                ],

                'formato026' => [

                    'archivo' =>
                        $formato026?->archivo,

                    'existe' =>
                        !empty(
                            $formato026?->archivo
                        )
                ]
            ];
        }

        echo json_encode([
            'success' => true,
            'data' => $data
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' =>
                $e->getMessage()
        ]);
    }

    exit;
}
    public function createInvestigacion()
    {
        header('Content-Type: application/json');

        try {

            $data = json_decode(
                file_get_contents('php://input'),
                true
            );

            Capsule::transaction(
                function () use ($data) {

                    $investigacion =
                        InvestigacionIncidenteAccidente::create([

                            'id_estacion' =>
                                $this->estacionId(),

                            'id_usuario' =>
                                $this->userId(),

                            'fechacreacion' =>
                                $data['fecha'],

                            'descripcion' =>
                                trim(
                                    $data['descripcion']
                                ),

                            'tipo_evento' =>
                                (int) $data['tipo_evento'],

                            'muertes' => (int) (
                                    $data['muertes'] ?? 0
                                ),

                            'tercer_autorizado' =>

                                !empty(
                                    $data['tercer_autorizado']
                                ) ? 1 : 0
                        ]);

                    if (
                        in_array(
                            $data['tipo_evento'],
                            [1,2,3]
                        ) && $data['nombre_ta']
                    ) {

                        InvestigacionIncidenteAccidenteTercerautorizado::create([

                            'id_investigacion' =>
                                $investigacion->id,

                            'nombre' =>
                                trim(
                                    $data['nombre_ta']
                                ),

                            'numero' =>
                                trim(
                                    $data['numero_autorizacion']
                                ),

                            'lider' =>
                                trim(
                                    $data['lider']
                                ),

                            'fecha' => '',

                            'archivo' => ''
                        ]);
                    }
                }
            );

            echo json_encode([
                'success' => true,
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

    public function deleteInvestigacion(){

    header('Content-Type: application/json');

    try {

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        $id = (int) ($data['id'] ?? 0);

        $registro = InvestigacionIncidenteAccidente::with([
            'formatos',
            'grupos',
            'terceroAutorizado'
        ])->find($id);

        if (!$registro) {

            echo json_encode([
                'success' => false,
                'message' => 'Registro no encontrado'
            ]);

            exit;
        }

        Capsule::transaction(function () use ($registro) {

            // eliminar relaciones primero

            $registro->formatos()->delete();
            $registro->grupos()->delete();
            $registro->terceroAutorizado()?->delete();

            // eliminar principal
            $registro->delete();
        });

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

    public function grupoInterdiciplinario(){
    
        header('Content-Type: application/json');

        try {

            $idAuditoria = (int) ($_GET['id'] ?? 0);

            $asea = InvestigacionIncidenteAccidenteGrupo::query()
                ->where('id_investigacion', $idAuditoria)
                ->orderBy('id', 'desc')
                ->get()
                 ->values()
        ->map(function ($item, $index) {
            return [
            'id' => $item->id,
            'fecha' =>  formatearFecha($item->fechacreacion?->format('Y-m-d')),
            'nombre' => $item->nombre, 
            'puesto' => $item->puesto ,
            'especialidad' => $item->especialidad                  
        ];
        })
        ->toArray();

            echo json_encode([
                'success' => true,
                'data' => $asea
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

    public function createGrupoInterdiciplinario(){
    header('Content-Type: application/json');

    try {

    $data = json_decode(
                file_get_contents('php://input'),
                true
            );

            $registro =
                InvestigacionIncidenteAccidenteGrupo::create([

                    'id_investigacion' =>
                        $data['id'],
                    'nombre' =>
                        $data['nombre'],
                    'puesto' =>
                        $data['puesto'],

                    'especialidad' =>
                        $data['especialidad']
                ]);

            echo json_encode([
                'success' => true,
                'message' => 'Registro creado correctamente',
                'id' => $registro->id
            ]);

    

     } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
    }

    exit;
    }

    public function uploadFormato026(){
    header('Content-Type: application/json');

        try {

            $idInvestigacion = (int) ($_POST['id_investigacion'] ?? 0);

            if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No tienes permiso para crear'
                ]);
                exit;
            }

            if (
                !$idInvestigacion ||
                !isset($_FILES['archivo'])
            ) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Archivo requerido'.$idInvestigacion
                ]);
                exit;
            }

            $archivo = $_FILES['archivo'];

            $nombre = sprintf(
                'F-I-D-%s-%s.pdf',
                $idInvestigacion,
                time()
            );

            $rutaFisica = __DIR__ . '../../../public/uploads/archivos/incidentes-accidentes/' . $nombre;
        
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

            $rutaBD =
                'archivos/incidentes-accidentes/' . $nombre;

            InvestigacionIncidenteAccidenteFormato::where('id_investigacion', $idInvestigacion)->delete();

            InvestigacionIncidenteAccidenteFormato::create([
                'id_investigacion' => $idInvestigacion,
                'archivo'      => $rutaBD
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Archivo cargado correctamente',
                'archivo' => $rutaBD
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;    
    }

    public function uploadFormatoTercer(){
    header('Content-Type: application/json');

        try {

            $id = (int) ($_POST['id'] ?? 0);
            $registro = InvestigacionIncidenteAccidenteTercerautorizado::findOrFail($id);

            if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No tienes permiso para crear'
                ]);
                exit;
            }

            if (
                !$id ||
                !isset($_FILES['archivo'])
            ) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Archivo requerido'.$id
                ]);
                exit;
            }

            $archivo = $_FILES['archivo'];

            $nombre = sprintf(
                'I-T-A-%s-%s.pdf',
                $id,
                time()
            );

            $rutaFisica = __DIR__ . '../../../public/uploads/archivos/incidentes-accidentes/' . $nombre;
        
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

            $rutaBD =
                'archivos/incidentes-accidentes/' . $nombre;

          $registro->archivo = $rutaBD;
          $registro->fecha = date('Y-m-d');
          $registro->save();

            echo json_encode([
                'success' => true,
                'message' => 'Archivo cargado correctamente',
                'archivo' => $rutaBD,
                'fecha_larga' => formatearFecha(date('Y-m-d'))
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;    
    }

    public function pdfInvestigacion(){

        $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

        $inicio = $_GET['inicio'] ?? null;
        $fin    = $_GET['fin'] ?? null;

        $registros = InvestigacionIncidenteAccidente::with([
                'usuario.puesto'
            ])
            ->where(
                'id_estacion',
                $this->estacionId()
            )
            ->when(
                !empty($inicio) && !empty($fin),
                fn ($q) => $q->whereBetween(
                    'fechacreacion',
                    [
                        $inicio . ' 00:00:00',
                        $fin . ' 23:59:59'
                    ]
                )
            )
            ->orderByDesc('id')
            ->get();
       

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
        <title>INVESTIGACIÓN DE INCIDENTES Y ACCIDENTES</title>
        <style>
        @page {margin: 0.5cm 1cm; font-family: Arial, Helvetica, sans-serif;}
*,
*::before,
*::after {
  box-sizing: border-box;
}

@-ms-viewport {
  width: device-width;
}


body {
 margin: 0;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
  font-size: 1rem;
  font-weight: 400;
  line-height: 1.5;
  color: #212529;
  background-color: #fff;
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

.mb-2,
.my-2 {
  margin-bottom: 0.5rem !important;
}

table {
  border-collapse: collapse;
}
.table {
  width: 100%;
  max-width: 100%;
  margin-bottom: 10px;
  background-color: transparent;
}

.table th,
.table td {
  padding: 0.30rem;
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
  padding: 0.2rem;
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
  padding: 0.2rem;
}
.align-middle {
  vertical-align: middle !important;
}

.border {
  border: 1px solid #dee2e6 !important;
}

.mt-3,
.my-3 {
  margin-top: 1rem !important;
}

.p-3 {
  padding: 1rem !important;
}

.mb-3,
.my-3 {
  margin-bottom: 1rem !important;
}
h5, .h5 {
  font-size: 1.25rem;
}
.text-center {
  text-align: center !important;
}
.bg-light {
  background-color: #f8f9fa !important;
}
        </style>
    </head>
    <body>';
    
    $html .= '

    <h4 style="text-align:center;">
        INVESTIGACIÓN DE INCIDENTES Y ACCIDENTES
    </h4>

    <table class="table table-sm table-bordered">

        <thead>

            <tr>

                <th class="align-middle bg-light text-center">
                    #
                </th>

                <th class="align-middle bg-light text-center">
                    Fecha
                </th>

                <th class="align-middle bg-light text-center">
                    Nombre
                </th>

                <th class="align-middle bg-light text-center">
                    Puesto
                </th>

                <th class="align-middle bg-light text-center">
                    Descripción evento
                </th>

                <th class="align-middle bg-light text-center">
                    Tipo evento
                </th>

                <th class="align-middle bg-light text-center">
                    Muertes
                </th>

            </tr>

        </thead>

        <tbody>';

foreach ($registros as $index => $item) {

    $html .= '

        <tr>

            <td class="text-center align-middle">
                '.($index + 1).'
            </td>

            <td class="text-center align-middle">
                '.formatearFecha(
                    $item->fechacreacion->format('Y-m-d')
                ).'
            </td>

            <td class="text-center align-middle">
                '.htmlspecialchars(
                    $item->usuario?->nombre ?? ''
                ).'
            </td>

            <td class="text-center align-middle">
                '.htmlspecialchars(
                    $item->usuario?->puesto?->tipo_puesto ?? ''
                ).'
            </td>

            <td class="text-center align-middle">
                '.htmlspecialchars(
                    $item->descripcion
                ).'
            </td>

            <td class="text-center align-middle">
                Tipo '.$item->tipo_evento.'
            </td>

            <td class="text-center align-middle">
                '.(
                    $item->muertes > 0
                        ? 'SI'
                        : 'NO'
                ).'
            </td>

        </tr>';
}

$html .= '
        </tbody>
    </table>';

    $html .= '</body>
    </html>';

    $options = new Options();
    $options->set('isRemoteEnabled',true);
    $options->set('defaultFont','Arial');
    $dompdf = new Dompdf($options);

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4','portrait');
    $dompdf->render();

    $dompdf->stream(
        'INVESTIGACIÓN DE INCIDENTES Y ACCIDENTES.pdf',
        [
            'Attachment' => true
        ]
    );

    exit;

    }

    //-------- investigación de incidentes y accidentes ----------
    //------------------------------------------------------------


    //----------------------------------------------
    //--------- Sin accidentes a la fecha ----------

    public function datatableNoAccidentes()
    {
        header('Content-Type: application/json');

        try {

            $registros = InvestigacionIncidenteAccidenteNo::with([
                'usuario:id,nombre'
            ])
            ->where('id_estacion', $this->estacionId())
            ->orderByDesc('id')
            ->get();

            $data = [];

            foreach ($registros as $registro) {

                $data[] = [

                    'id' => $registro->id,

                    'fecha' => $registro->fecha->format('Y-m-d'),

                    'fecha_larga' => formatearFecha(
                        $registro->fecha
                    ),

                    'usuario' => $registro->usuario?->nombre,

                    'estatus' => (int) $registro->estatus,

                    'row_class' => $registro->estatus == 0
                        ? 'table-warning'
                        : ''
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

    public function createNoAccidente()
    {
        header('Content-Type: application/json');

        try {

            $data = json_decode(
                file_get_contents('php://input'),
                true
            );

            $fecha = trim(
                $data['fecha'] ?? ''
            );

            if ($fecha === '') {

                echo json_encode([
                    'success' => false,
                    'message' => 'La fecha es obligatoria'
                ]);

                exit;
            }

            $registro =
                InvestigacionIncidenteAccidenteNo::create([

                    'id_estacion' =>
                        $this->estacionId(),

                    'id_usuario' =>
                        $this->userId(),

                    'fecha' =>
                        $fecha,

                    'estatus' => 1
                ]);

            echo json_encode([
                'success' => true,
                'message' => 'Registro creado correctamente',
                'id' => $registro->id
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

    public function updateNoAccidente()
    {
        header('Content-Type: application/json');

        try {

            $data = json_decode(
                file_get_contents('php://input'),
                true
            );

            $id = (int) ($data['id'] ?? 0);

            $fecha = trim(
                $data['fecha'] ?? ''
            );

            if (!$id) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Registro no encontrado'
                ]);

                exit;
            }

            if ($fecha === '') {

                echo json_encode([
                    'success' => false,
                    'message' => 'La fecha es obligatoria'
                ]);

                exit;
            }

            $registro =
                InvestigacionIncidenteAccidenteNo::find($id);

            if (!$registro) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Registro no encontrado'
                ]);

                exit;
            }

            $registro->update([
                'fecha' => $fecha
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Registro actualizado correctamente'
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

    public function pdfNoAccidentes()
    {
        $id = (int) ($_GET['id'] ?? 0);

        $registro = InvestigacionIncidenteAccidenteNo::with([
        'usuario.puesto',
        'estacion'
        ])->findOrFail($id);

        $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';
        $usuario = $registro->usuario;
        $estacion = $registro->estacion;

        $firma = $_ENV['APP_URL'] . '/uploads/firma-personal/' . $usuario->firma;

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
        <title>Sin accidentes a la fecha</title>
        <style>
        @page {margin: 0.5cm 1cm; font-family: Arial, Helvetica, sans-serif;}
        *,
        *::before,
        *::after {
        box-sizing: border-box;
        }

        @-ms-viewport {
        width: device-width;
        }

        body {
        margin: 0;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
        font-size: 1rem;
        font-weight: 400;
        line-height: 1.5;
        color: #212529;
        background-color: #fff;
        }

        .text-right {
        text-align: right !important;
        }
        .justify{
         text-align: justify;
        }
        </style>
    </head>
    <body>
    
    <div><img src="'.$logo.'" style="width: 150px;"></div>

    <div class="text-right" style="margin-top:40px;">
    '.$estacion->di_municipio.'
    '.$estacion->di_estado.',
    a
    '.formatearFecha($registro->fecha->format('Y-m-d')).'

    </div>

    <div style="margin-top:40px;">
        <b>A quien corresponda</b>
    </div>

    <p class="justify" style="margin-top:40px;">

    <b>'.$usuario->nombre.'</b>,

    en carácter de Representante técnico
    del regulado

    <b>'.$estacion->razonsocial.'</b>,

    con ubicación en

    <b>'.$estacion->direccioncompleta.'</b>

    manifiesto bajo protesta de decir verdad y
    sabedor de la pena que conlleva a quienes
    actúan de mala fe o declaran con falsedad,
    manifiesto que en las instalaciones antes
    mencionadas a la fecha del presente no han
    ocurrido ningún tipo de incidentes o accidentes.

    </p>

    <p class="justify">

    Lo anterior en cumplimiento a las
    DISPOSICIONES administrativas de carácter
    general que establecen los Lineamientos
    para Informar la ocurrencia de incidentes
    y accidentes a la Agencia Nacional de
    Seguridad Industrial y de Protección al
    Medio Ambiente del Sector Hidrocarburos.

    </p>

    <p style="margin-top:60px;">
        <b>Atentamente</b>
    </p>

    <div>

        <img
            src="'.$firma.'"
            width="100"
        >

    </div>

    <div>
        '.$usuario->nombre.'
    </div>

    <div>
        '.$usuario->puesto?->tipo_puesto.'
    </div>

    </body>
    </html>';

    $options = new Options();
    $options->set('isRemoteEnabled',true);
    $options->set('defaultFont','Arial');
    $dompdf = new Dompdf($options);

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4','portrait');
    $dompdf->render();

    $dompdf->stream(
        'Sin accidentes a la fecha.pdf',
        [
            'Attachment' => true
        ]
    );

    exit;
    }

    public function deleteNoAccidentes(){

        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'),true);
        $accidentes = InvestigacionIncidenteAccidenteNo::find($data['id']);

        if (!$accidentes) {

            echo json_encode([
                'success' => false,
                'message' => 'Error al eliminar'
            ]);

            return;
        }

        $accidentes->delete();

        echo json_encode([
            'success' => true,
            'message' => 'Registro eliminado'
        ]);

        exit;

    }

    //----------------------------------------------
    //--------- Sin accidentes a la fecha ----------

}