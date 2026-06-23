<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Estacion;
use App\Models\Sasisopa\ProgramaAuditorias;
use App\Models\Sasisopa\AuditoriaInterna;
use App\Models\Sasisopa\AuditoriaInternaFormato;
use App\Models\Sasisopa\AuditoriaInternaAnexo;

use App\Models\Sasisopa\AuditoriaExterna;
use App\Models\Sasisopa\AuditoriaExternaFormato;
use App\Models\Sasisopa\AuditoriaExternaAsea;

use Illuminate\Database\Capsule\Manager as Capsule;

use Dompdf\Dompdf;
use Dompdf\Options;

class AuditoriasController extends BaseController{
    protected string $modulo = 'sasisopa';
    public function index(){

    $title = '15. AUDITORÍAS';

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
                
            ],
            'help' => true
        ];
        
        View::render('auditorias/index', $data,'sasisopa');

    }

    //------------ FORMATO PROGRAMA DE AUDITORIAS (INTERNAS Y EXTERNAS)

    public function programa(){

    $title = 'FORMATO PROGRAMA DE AUDITORIAS (INTERNAS Y EXTERNAS)';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('15. AUDITORÍAS', '/sasisopa/auditorias');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

        $estacion = Estacion::findOrFail($this->estacionId());
        $this->generarProgramaAuditorias($estacion->fecha_autorizacion,$estacion->id,(int) date('Y'));

         $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'apoderado_legal' => $estacion->apoderado_legal,
            'links' =>[
                
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/auditorias/programa.actions.init.js?v=1.1'
            ],
            'help' => true
        ];
        
        View::render('auditorias/programa', $data,'sasisopa');

    }

    public function generarProgramaAuditorias(
    string $fechaAutorizacion,
    int $idEstacion,
    int $fechaYear
    ): void {

        if (empty($fechaAutorizacion) || $fechaAutorizacion === '0000-00-00') {
            return;
        }

        $yearAutorizacion = (int) date('Y',strtotime($fechaAutorizacion));
        $years = ($fechaYear + 1) - $yearAutorizacion;

        $this->generarAuditoriasInternas(
            $fechaAutorizacion,
            $idEstacion,
            $years
        );

        $bianual = $years / 2;

        if (((int) $bianual % 2) === 0) {

            $this->generarAuditoriasExternas(
                $fechaAutorizacion,
                $idEstacion,
                (int) $bianual
            );

        } else {

            $this->generarAuditoriasExternas(
                $fechaAutorizacion,
                $idEstacion,
                (int) ($bianual - 1)
            );
        }
    }

    private function generarAuditoriasInternas(
    string $fechaAutorizacion,
    int $idEstacion,
    int $years
    ): void {

        $iteraciones = $years * 2;

        for ($i = 1; $i <= $iteraciones; $i++) {

            $fecha = date('Y-m-d',strtotime($fechaAutorizacion . " +".($i * 6)." months"));
            ProgramaAuditorias::firstOrCreate(
                [
                    'id_estacion' => $idEstacion,
                    'tipo_auditoria' => 'Interna',
                    'fecha' => $fecha
                ],
                [
                    'responsable' => 'Gerente/Depto. gestión',
                    'periodicidad' => 'Semestral'
                ]
            );
        }
    }

    private function generarAuditoriasExternas(
    string $fechaAutorizacion,
    int $idEstacion,
    int $bianual
    ): void {

        for ($i = 1; $i <= $bianual; $i++){

            $fecha = date('Y-m-d',strtotime($fechaAutorizacion . " +".($i * 2)." years"));
            ProgramaAuditorias::firstOrCreate(
                [
                    'id_estacion' => $idEstacion,
                    'tipo_auditoria' => 'Externa',
                    'fecha' => $fecha
                ],
                [
                    'responsable' => 'Tercer acreditado',
                    'periodicidad' => 'Bianual'
                ]
            );
        }
    }


    public function formatoAuditorias()
    {
        header('Content-Type: application/json');

        $inicio = $_GET['inicio'] ?? date('Y-01-01');
        $fin    = $_GET['fin'] ?? date('Y-12-31');

        $yearInicio = date('Y', strtotime($inicio));
        $yearFin    = date('Y', strtotime($fin));

        $registros = ProgramaAuditorias::query()
            ->where('id_estacion', $this->estacionId())
            ->whereYear('fecha', '>=', $yearInicio)
            ->whereYear('fecha', '<=', $yearFin)
            ->orderBy('fecha')
            ->get();

        echo json_encode([
            'success' => true,
            'inicio' => $yearInicio,
            'fin' => $yearFin,
            'data' => $registros
        ]);

        exit;
    }

    public function formatpPdfAuditorias(int $yearInicio, int $yearFin)
    {
        $estacion = Estacion::find(
            $this->estacionId()
        );

        $auditorias = ProgramaAuditorias::query()
            ->where('id_estacion', $this->estacionId())
            ->whereYear('fecha', '>=', $yearInicio)
            ->whereYear('fecha', '<=', $yearFin)
            ->orderBy('fecha')
            ->get();

        $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

        $html = '
        <!DOCTYPE html>
        <html>
        <head>

            <meta charset="UTF-8">
            <title>Formato Programa de auditorías (Internas y externas)</title>

            <style>

                @page{
                    margin:0.5cm;
                }

                body{
                    font-family:Arial, Helvetica, sans-serif;
                    font-size:14px;
                }

                table{
                    width:100%;
                    border-collapse:collapse;
                }

                th,
                td{
                    border:1px solid #dee2e6;
                    padding:4px;
                }

                .text-center{
                    text-align:center;
                }

                .align-middle{
                    vertical-align:middle;
                }

                .table-primary{
                    background:#b8daff;
                }

                .table-success{
                    background:#c3e6cb;
                }

            </style>

        </head>

        <body>

            <table>

                <tr>

                    <td class="text-center align-middle">
                        <img src="'.$logo.'" width="100">
                    </td>

                    <td colspan="2" class="text-center align-middle">
                        <strong>
                            Formato Programa de auditorías
                            (Internas y externas)
                        </strong>
                    </td>

                    <td class="text-center align-middle">
                        Fo.ADMONGAS.023
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

            <table>

                <thead>

                    <tr>

                        <th>Tipo auditoría</th>
                        <th>Responsable</th>
                        <th>Periodicidad</th>';

        for ($year = $yearInicio; $year <= $yearFin; $year++) {

            $html .= '
                <th class="text-center">
                    '.$year.'
                </th>';
        }

        $html .= '
                    </tr>

                </thead>

                <tbody>';

        foreach ($auditorias as $auditoria) {

            $html .= '<tr>';

            $html .= '
                <td>'.$auditoria->tipo_auditoria.'</td>
                <td>'.$auditoria->responsable.'</td>
                <td>'.$auditoria->periodicidad.'</td>';

            $yearAuditoria = $auditoria->fecha->format('Y');
            $mesAuditoria  = $auditoria->fecha->format('m');

            for ($year = $yearInicio; $year <= $yearFin; $year++) {

                $titulo = '';
                $clase = '';

                if ($yearAuditoria == $year) {

                    $titulo = nombremes($mesAuditoria);

                    $clase =
                        $auditoria->tipo_auditoria === 'Interna'
                            ? 'table-primary'
                            : 'table-success';
                }

                $html .= '
                    <td class="text-center '.$clase.'">
                        '.$titulo.'
                    </td>';
            }

            $html .= '</tr>';
        }

        $html .= '

                </tbody>

            </table>

            <br>

            <div style="font-size:10px; text-align:center;">

                *Las auditorias al SA se realizaran por personal interno de la empresa, que puede ser el gerente de la estación de servicio, el Representante legal, el departamento de gestión, entre otras y las auditorias
    externas se realizaran por un tercer acreditado (cada dos años de acuerdo a las DACG expendio de petrolíferos) ante la Agencia de Seguridad Energía y Ambiente, tercer acreditado que tendrá que tener
    vigente su autorización ante la Agencia y el personal podrá elegir.

            </div>

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
            'Formato Programa de auditorías (Internas y externas).pdf',
            [
                'Attachment' => true
            ]
        );

        exit;
    }

    //------------ FORMATO PROGRAMA DE AUDITORIAS (INTERNAS Y EXTERNAS)

    //------------ AUDITORIA INTERNA 

    public function interna(){

    $title = 'AUDITORIA INTERNA';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('15. AUDITORÍAS', '/sasisopa/auditorias');
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
                '/js/auditorias/interna.actions.init.js?v=1.2'
            ],
            'help' => true
        ];
        
        View::render('auditorias/interna', $data,'sasisopa');

    }

    public function datatable()
    {
        header('Content-Type: application/json');

        $auditorias = AuditoriaInterna::with([
            'formatos',
            'anexos'
        ])
        ->where(
            'id_estacion',
            $this->estacionId()
        )
        ->orderByDesc('id')
        ->get();

        $data = [];

        foreach ($auditorias as $auditoria) {

            $formato024 = $auditoria
                ->formatos
                ->where('formato', 'formato024')
                ->last();

            $formato025 = $auditoria
                ->formatos
                ->where('formato', 'formato025')
                ->last();

            $data[] = [

                'id' => $auditoria->id,

                'fecha' => $auditoria
                    ->fechacreacion
                    ->format('Y-m-d'),

                'fecha_larga' => formatearFecha(
                    $auditoria->fechacreacion
                        ->format('Y-m-d')
                ),

                'auditor' => $auditoria->auditor,

                'formato024' => [
                    'archivo' => $formato024?->archivo,
                    'existe'  => !empty($formato024?->archivo)
                ],

                'formato025' => [
                    'archivo' => $formato025?->archivo,
                    'existe'  => !empty($formato025?->archivo)
                ],

                'anexos_024' => $auditoria
                    ->anexos
                    ->where('formato', 24)
                    ->count(),

                'anexos_025' => $auditoria
                    ->anexos
                    ->where('formato', 25)
                    ->count(),
            ];
        }

        echo json_encode([
            'success' => true,
            'data' => $data
        ]);

        exit;
    }

    public function createInterna()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'),true);

        try {

            $auditor = trim($data['auditor'] ?? '');

            if ($auditor === '') {

                echo json_encode([
                'success' => false,
                'message' => 'Ingrese el nombre del auditor'
                ]);

            }

            $registro = AuditoriaInterna::create([
                'id_estacion' => $this->estacionId(),
                'id_usuario'  => $this->userId(),
                'auditor'     => $auditor
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Auditoría creada correctamente',
                'id'      => $registro->id
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

    public function deleteInterna()
    {
        header('Content-Type: application/json');

        try {

            $data = json_decode(
                file_get_contents('php://input'),
                true
            );

            $id = (int) ($data['id'] ?? 0);

            $auditoria = AuditoriaInterna::with([
                'formatos',
                'anexos'
            ])->find($id);

            if (!$auditoria) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Registro no encontrado'
                ]);

                exit;
            }

            Capsule::transaction(function () use ($auditoria) {

                $auditoria->formatos()->delete();
                $auditoria->anexos()->delete();
                $auditoria->delete();

            });

            echo json_encode([
                'success' => true,
                'message' => 'Auditoría eliminada correctamente'
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }
    public function uploadFormato024()
    {
        header('Content-Type: application/json');

        try {

            $idAuditoria = (int) ($_POST['id_auditoria'] ?? 0);

            if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No tienes permiso para crear'
                ]);
                exit;
            }

            if (
                !$idAuditoria ||
                !isset($_FILES['archivo'])
            ) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Archivo requerido'.$idAuditoria
                ]);
                exit;
            }

            $archivo = $_FILES['archivo'];

            $nombre = sprintf(
                'I-A-%s-%s.pdf',
                $idAuditoria,
                time()
            );

            $rutaFisica = __DIR__ . '../../../public/uploads/archivos/auditorias/' . $nombre;
        
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
                'archivos/auditorias/' . $nombre;

            AuditoriaInternaFormato::create([
                'id_auditoria' => $idAuditoria,
                'formato'      => 'formato024',
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

    public function uploadFormato025()
    {
        header('Content-Type: application/json');

        try {

            $idAuditoria = (int) ($_POST['id_auditoria'] ?? 0);

            if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No tienes permiso para crear'
                ]);
                exit;
            }

            if (
                !$idAuditoria ||
                !isset($_FILES['archivo'])
            ) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Archivo requerido'.$idAuditoria
                ]);
                exit;
            }

            $archivo = $_FILES['archivo'];

            $nombre = sprintf(
                'P-D-H-%s-%s.pdf',
                $idAuditoria,
                time()
            );

            $rutaFisica = __DIR__ . '../../../public/uploads/archivos/auditorias/' . $nombre;
        
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
                'archivos/auditorias/' . $nombre;

            AuditoriaInternaFormato::create([
                'id_auditoria' => $idAuditoria,
                'formato'      => 'formato025',
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

    public function anexos()
    {
        header('Content-Type: application/json');

        try {

            $idAuditoria = (int) ($_GET['id'] ?? 0);
            $formato = (int) ($_GET['formato'] ?? 0);

            $anexos = AuditoriaInternaAnexo::query()
                ->where('id_auditoria', $idAuditoria)
                ->where('formato', $formato)
                ->orderBy('id', 'desc')
                ->get([
                    'id',
                    'documento',
                    'archivo'
                ]);

            echo json_encode([
                'success' => true,
                'data' => $anexos
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

    public function createAnexo()
    {
        header('Content-Type: application/json');

        try {

            if (!ModuloService::validaPermiso($this->modulo, 'crear')) {

                echo json_encode([
                    'success' => false,
                    'message' => 'No tienes permiso para crear'
                ]);

                exit;
            }

            $idAuditoria = (int) ($_POST['id'] ?? 0);
            $formato     = (int) ($_POST['formato'] ?? 0);
            $documento   = trim($_POST['documento'] ?? '');

            if (
                !$idAuditoria ||
                !$formato ||
                $documento === '' ||
                !isset($_FILES['archivo'])
            ) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Información incompleta'
                ]);

                exit;
            }

            $archivo = $_FILES['archivo'];

            $extension = strtolower(
                pathinfo(
                    $archivo['name'],
                    PATHINFO_EXTENSION
                )
            );

            if ($extension !== 'pdf') {

                echo json_encode([
                    'success' => false,
                    'message' => 'Solo se permiten archivos PDF'
                ]);

                exit;
            }

            $nombre = sprintf(
                'A-I-ANEXO-%s-%s.%s',
                $idAuditoria,
                time(),
                $extension
            );

            $rutaFisica =
                __DIR__ .
                '../../../public/uploads/archivos/auditorias/' .
                $nombre;

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
                'archivos/auditorias/' .
                $nombre;

            $anexo = AuditoriaInternaAnexo::create([
                'id_auditoria' => $idAuditoria,
                'formato'      => $formato,
                'documento'    => $documento,
                'archivo'      => $rutaBD
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Anexo agregado correctamente',
                'data' => [
                    'id' => $anexo->id,
                    'documento' => $anexo->documento,
                    'archivo' => $anexo->archivo
                ]
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

    //------------ AUDITORIA INTERNA

    public function externa(){

    $title = 'AUDITORIA EXTERNA';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('15. AUDITORÍAS', '/sasisopa/auditorias');
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
                '/js/auditorias/externa.actions.init.js?v=1.0'
            ],
            'help' => true
        ];
        
        View::render('auditorias/externa', $data,'sasisopa');

    }

    public function datatableExterna()
    {
        header('Content-Type: application/json');

        $auditorias = AuditoriaExterna::with([
            'formatos',
            'asea'
        ])
        ->where(
            'id_estacion',
            $this->estacionId()
        )
        ->orderByDesc('id')
        ->get();

        $data = [];

        foreach ($auditorias as $auditoria) {

            $formato024 = $auditoria
                ->formatos
                ->where('formato', 'formato024')
                ->last();

            $formato025 = $auditoria
                ->formatos
                ->where('formato', 'formato025')
                ->last();

            $data[] = [

                'id' => $auditoria->id,

                'fecha' => $auditoria
                    ->fechacreacion
                    ->format('Y-m-d'),

                'fecha_larga' => formatearFecha(
                    $auditoria->fechacreacion
                        ->format('Y-m-d')
                ),

                'prestador_servicio' => $auditoria->prestador_servicio,

                'formato024' => [
                    'archivo' => $formato024?->archivo,
                    'existe'  => !empty($formato024?->archivo)
                ],

                'formato025' => [
                    'archivo' => $formato025?->archivo,
                    'existe'  => !empty($formato025?->archivo)
                ],

            ];
        }

        echo json_encode([
            'success' => true,
            'data' => $data
        ]);

        exit;
    }

    public function createExterna()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'),true);

        try {

            $auditor = trim($data['auditor'] ?? '');

            if ($auditor === '') {

                echo json_encode([
                'success' => false,
                'message' => 'Ingrese el nombre del auditor'
                ]);

            }

            $registro = AuditoriaExterna::create([
                'id_estacion' => $this->estacionId(),
                'id_usuario'  => $this->userId(),
                'prestador_servicio'     => $auditor
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Auditoría creada correctamente',
                'id'      => $registro->id
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

    public function deleteExterna()
    {
        header('Content-Type: application/json');

        try {

            $data = json_decode(
                file_get_contents('php://input'),
                true
            );

            $id = (int) ($data['id'] ?? 0);

            $auditoria = AuditoriaExterna::with([
                'formatos',
                'asea'
            ])->find($id);

            if (!$auditoria) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Registro no encontrado'
                ]);

                exit;
            }

            Capsule::transaction(function () use ($auditoria) {

                $auditoria->formatos()->delete();
                $auditoria->asea()->delete();
                $auditoria->delete();

            });

            echo json_encode([
                'success' => true,
                'message' => 'Auditoría eliminada correctamente'
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

     public function uploadExternaFormato024()
    {
        header('Content-Type: application/json');

        try {

            $idAuditoria = (int) ($_POST['id_auditoria'] ?? 0);

            if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No tienes permiso para crear'
                ]);
                exit;
            }

            if (
                !$idAuditoria ||
                !isset($_FILES['archivo'])
            ) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Archivo requerido'.$idAuditoria
                ]);
                exit;
            }

            $archivo = $_FILES['archivo'];

            $nombre = sprintf(
                'I-A-%s-%s.pdf',
                $idAuditoria,
                time()
            );

            $rutaFisica = __DIR__ . '../../../public/uploads/archivos/auditorias/' . $nombre;
        
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
                'archivos/auditorias/' . $nombre;

            AuditoriaExternaFormato::create([
                'id_auditoria' => $idAuditoria,
                'formato'      => 'formato024',
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

    public function uploadExternaFormato025()
    {
        header('Content-Type: application/json');

        try {

            $idAuditoria = (int) ($_POST['id_auditoria'] ?? 0);

            if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No tienes permiso para crear'
                ]);
                exit;
            }

            if (
                !$idAuditoria ||
                !isset($_FILES['archivo'])
            ) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Archivo requerido'.$idAuditoria
                ]);
                exit;
            }

            $archivo = $_FILES['archivo'];

            $nombre = sprintf(
                'P-D-H-%s-%s.pdf',
                $idAuditoria,
                time()
            );

            $rutaFisica = __DIR__ . '../../../public/uploads/archivos/auditorias/' . $nombre;
        
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
                'archivos/auditorias/' . $nombre;

            AuditoriaExternaFormato::create([
                'id_auditoria' => $idAuditoria,
                'formato'      => 'formato025',
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

    public function asea()
    {
        header('Content-Type: application/json');

        try {

            $idAuditoria = (int) ($_GET['id'] ?? 0);

            $asea = AuditoriaExternaAsea::query()
                ->where('id_auditoria', $idAuditoria)
                ->orderBy('id', 'desc')
                ->get()
                 ->values()
        ->map(function ($item, $index) {
            return [
            'id' => $item->id,
            'fecha' =>  formatearFecha($item->fechacreacion?->format('Y-m-d')),
            'archivo' => $item->archivo, 
            'comentario' => $item->comentario                  
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

    public function createAsea()
    {
        header('Content-Type: application/json');

        try {

            if (!ModuloService::validaPermiso($this->modulo, 'crear')) {

                echo json_encode([
                    'success' => false,
                    'message' => 'No tienes permiso para crear'
                ]);

                exit;
            }

            $idAuditoria = (int) ($_POST['id'] ?? 0);
            $comentario   = trim($_POST['comentario'] ?? '');

            if (
                !$idAuditoria ||
                $comentario === '' ||
                !isset($_FILES['archivo'])
            ) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Información incompleta'
                ]);

                exit;
            }

            $archivo = $_FILES['archivo'];

            $extension = strtolower(
                pathinfo(
                    $archivo['name'],
                    PATHINFO_EXTENSION
                )
            );

            if ($extension !== 'pdf') {

                echo json_encode([
                    'success' => false,
                    'message' => 'Solo se permiten archivos PDF'
                ]);

                exit;
            }

            $nombre = sprintf(
                'ASEA-%s-%s.%s',
                $idAuditoria,
                time(),
                $extension
            );

            $rutaFisica =
                __DIR__ .
                '../../../public/uploads/archivos/auditorias/' .
                $nombre;

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
                'archivos/auditorias/' .
                $nombre;

            $anexo = AuditoriaExternaAsea::create([
                'id_auditoria' => $idAuditoria,
                'archivo'      => $rutaBD,
                'comentario'    => $comentario,
                
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Asea agregado correctamente',
                'data' => [
                    'id' => $anexo->id,                    
                    'archivo' => $anexo->archivo,
                    'comentario' => $anexo->comentario,
                ]
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