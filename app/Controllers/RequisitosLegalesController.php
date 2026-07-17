<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Models\Estacion;
use App\Models\Sasisopa\RequisitosLegalesCalendario;
use App\Models\Sasisopa\RequisitosLegalesLista;
use App\Models\Sasisopa\RequisitosLegalesDependencia;
use App\Models\Sasisopa\RequisitosLegalesMatriz;
use App\Services\ModuloService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Capsule\Manager as Capsule;

class RequisitosLegalesController extends BaseController{

    protected string $modulo = 'sasisopa';

    public function requisitosLegales(){

        $title = '3. REQUISITOS LEGALES';
        // Buscar permisos de los modulos
        $permisos = ModuloService::permisosSesion($this->modulo);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

        $requisitos = RequisitosLegalesCalendario::ToRequisitosTodos($this->estacionId(),0);

         $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'requisitos' => $requisitos,
             'links' =>[
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/libs/select2/dist/css/select2.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/libs/select2/dist/js/select2.full.min.js',
                '/libs/select2/dist/js/select2.min.js',
                '/js/asistencia/listaasistencia.datatable.init.js',
                '/js/asistencia/listaasistencia.actions.init.js?v=1.0'
            ],
            'help' => true
        ];
        
        View::render('requisitoslegales/index', $data,'sasisopa');

    }

    public function calendarioRequisitosLegales(){

        $estacion = Estacion::find($this->estacionId());
        $apoderado = htmlspecialchars($estacion->apoderado_legal ?? '');

        if (!ModuloService::validaPermiso($this->modulo, 'descargar')) {
            header("Location: /404");
            exit;
        }

        $municipal = RequisitosLegalesCalendario::NivelGobierno('Municipal', $this->estacionId(),0);
        $estatal   = RequisitosLegalesCalendario::NivelGobierno('Estatal', $this->estacionId(),0);
        $federal   = RequisitosLegalesCalendario::NivelGobierno('Federal', $this->estacionId(),0);
        $varios    = RequisitosLegalesCalendario::NivelGobierno('Varios', $this->estacionId(),0);

        $html = '';
        $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

        $html .= '
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset="UTF-8">
        <title>Calendario anual de renovacion de Requisitos Legales</title>
        <link rel="stylesheet" href="'.$_ENV['APP_URL'].'/assets/css/pdf.css">
        </head>
        <body>

        <table class="table table-bordered" style="font-size: .9em;">
        <tbody>
        <tr>

        <td class="align-middle text-center">
        <img src="'.$logo.'" style="width: 150px;">
        </td>
        <td colspan="2" class="align-middle text-center">
        <b>Calendario anual de renovacion de Requisitos Legales</b>
        </td>
        <td class="align-middle text-center">
        <b>Fo.ADMONGAS.004</b>
        </td>

        </tr>
        //------------------------------------------------------------------
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
        Fecha de aprobacion:<br>  01-oct-18
        </td>
        </tr>
        </tbody>
        </table>

        <table class="table table-bordered table-sm mt-4" style="font-size: .75em;" width="100%">

        <tr class="table-active">
        <td class="text-center align-middle"><b>Dependencia</b></td>
        <td class="text-center align-middle"><b>Permiso</b></td>
        <td class="text-center align-middle"><b>Vigencia</b></td>
        <td class="text-center align-middle"><b>Fecha emisión</b></td>
        <td class="text-center align-middle"><b>Fecha vencimiento</b></td>
        <td class="text-center align-middle"><b>Renovación</b></td>
        </tr>';

        $html .= $this->renderNivel('Municipal', $municipal);
        $html .= $this->renderNivel('Estatal', $estatal);
        $html .= $this->renderNivel('Federal', $federal);
        $html .= $this->renderNivel('Varios', $varios);

        $html .= '</table>

        </body>
        </html>';

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $dompdf->stream("Calendario-anual-renovacion-Requisitos-Legales.pdf", ["Attachment" => true]);
        
    }

    private function renderNivel($titulo, $data)
    {
        $html = '';

        $html .= '
        <tr>
            <td class="text-center table-info" colspan="6">
                <b>Nivel de Gobierno '.$titulo.'</b>
            </td>
        </tr>';

        foreach ($data as $row) {

            $html .= '
            <tr>
                <td>'.$row['dependencia'].'</td>
                <td><b>'.$row['permiso'].'</b></td>
                <td>'.$row['vigencia'].'</td>
                <td>'.formatearFechaCorta($row['fecha_emision']).'</td>
                <td>'.formatearFechaCorta($row['fecha_vencimiento']).'</td>
                <td>'.$row['renovacion'].'</td>
            </tr>';
        }

        return $html;
    }

    public function requisitosLegalesConfiguracion(){

        $title = 'REQUISITOS LEGALES CONFIGURACIÓN';
         // Buscar permisos de los modulos
        $permisos = ModuloService::permisosSesion($this->modulo);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('3. REQUISITOS LEGALES', '/sasisopa/requisitos-legales');
        Breadcrumb::add($title, '');

         $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
             'links' =>[
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/libs/select2/dist/css/select2.min.css',
                '/css/select2-modal.css?v=1.0.1'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/libs/select2/dist/js/select2.full.min.js',
                '/libs/select2/dist/js/select2.min.js',
                '/js/requisitoslegales/configuracion.datatable.init.js?v=1.1',
                '/js/requisitoslegales/configuracion.actions.init.js?v=1.1'
            ]
        ];
        
        View::render('requisitoslegales/configuracion', $data,'sasisopa');

    }

    public function datatableConfiguracion(){

        $idEstacion = $this->estacionId();
        $estacion = Estacion::find($idEstacion);

        $estado = $estacion->di_estado;
        $municipio = $estacion->di_municipio;
        // permisos
        $permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');

        $data = RequisitosLegalesLista::select('id', 'nivel_gobierno', 'dependencia', 'permiso', 'fundamento')
        ->whereIn('id_estacion', [$idEstacion, 0])
        ->where('estado', 1)
        ->where(function ($query) use ($municipio, $estado) {

            // Municipal
            $query->where(function ($q) use ($municipio) {
                $q->where('nivel_gobierno', 'Municipal')
                ->where('mun_alc_est', $municipio);
            });

            // Estatal
            $query->orWhere(function ($q) use ($estado) {
                $q->where('nivel_gobierno', 'Estatal')
                ->where('mun_alc_est', $estado);
            });

            // Federal y Varios (sin filtro extra)
            $query->orWhereIn('nivel_gobierno', ['Federal', 'Varios']);
        })
        ->orderBy('dependencia', 'asc')
        ->get();

         echo json_encode([
            "data" => $data,
            "permisos" => [
                "eliminar" => $permisoEliminar
            ]
        ]);
        
        exit;

    }

    public function deleteConfiguracion()
    {
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

        $requisito = RequisitosLegalesLista::find($id);

        if (!$requisito) {
            echo json_encode([
                'success' => false,
                'message' => 'No existe el registro'
            ]);
            return;
        }

        if ($requisito->disabled != 0) {
            echo json_encode([
                'success' => false,
                'message' => 'No se puede eliminar'
            ]);
            return;
        }

        $requisito->estado = 0;
        $requisito->save();

        echo json_encode([
            'success' => true,
            'message' => 'Requisito Legal eliminado correctamente'
        ]);
    }

    public function getDependencias()
    {
        header('Content-Type: application/json');

        $idEstacion = $this->estacionId();

        $data = RequisitosLegalesDependencia::whereIn('id_estacion', [$idEstacion, 0])
            ->where('estado', 1)
            ->orderBy('dependencia', 'asc')
            ->get(['id', 'dependencia']);

        echo json_encode($data);

        exit;
    }

    public function createConfiguracion(){

        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);

        
        $gobierno = sanitize_input($data['gobierno'] ?? null, 'string');
        $dependencia = sanitize_input($data['dependencia'] ?? null, 'string');
        $permiso = sanitize_input($data['permiso'] ?? null, 'string');
        $fundamento = sanitize_input($data['fundamento'] ?? null, 'string');


        if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear'
            ]);
            return;
        }

        if (!$gobierno || !$dependencia || !$permiso || !$fundamento) {
            echo json_encode([
                'success' => false,
                'message' => 'Completa todos los campos obligatorios'
            ]);
            return;
        }

        $estacion = Estacion::find($this->estacionId());

        
        if($gobierno == "Municipal"){
        $MA = $estacion->di_municipio;
        }else if($gobierno == "Estatal"){
        $MA = $estacion->di_estado;
        }else if($gobierno == "Federal"){
        $MA = "";
        }else if($gobierno == "Varios"){
        $MA = "";
        }

         $asistencia = RequisitosLegalesLista::create([
                'nivel_gobierno'  => $gobierno,
                'mun_alc_est'     => $MA,
                'dependencia'     => $dependencia,
                'permiso'         => $permiso,
                'fundamento'      => $fundamento,
                'id_estacion'     => $this->estacionId(),
                'disabled'        => 0,
                'estado'          => 1
            ]);

         echo json_encode([
                'success' => true,
                'id' => $asistencia->id,
                'message' => 'Lista de asistencia guardada correctamente'
            ]);
    }

    public function requisitosLegalesDetalle($nGobierno){

        $title = $nGobierno;
         // Buscar permisos de los modulos
        $permisos = ModuloService::permisosSesion($this->modulo);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('3. REQUISITOS LEGALES', '/sasisopa/requisitos-legales');
        Breadcrumb::add($title, '');

        $requisitos = RequisitosLegalesCalendario::ToRequisitosTodos($this->estacionId(),0);

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
                '/js/requisitoslegales/detalle.datatable.init.js?v=1.0',
                '/js/requisitoslegales/detalle.actions.init.js?v=1.0'
            ]
        ];
        
        View::render('requisitoslegales/detalle', $data,'sasisopa');

    }

    public function datatableDetalle($nGobierno, $modulo){

    $permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');
    $permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');
    $permisoDescargar = ModuloService::validaPermiso($this->modulo, 'descargar');

    $rows = RequisitosLegalesCalendario::NivelGobierno(
        $nGobierno,
        $this->estacionId(),
        $modulo
    );

    $data = [];

        $estatus = [
        "titulo" => '',
        "color_css" => '',
        "color_hexa" => ''
    ];

    foreach ($rows as $row) {
    
    $fechaEmision = $row['fecha_emision'];
    $fechaVencimiento = $row['fecha_vencimiento'];

    if (empty($row['fecha_emision']) ||
    $fechaEmision === '0000-00-00' ||
    $fechaEmision === '-0001-11-30'
    ) {
        $fechaEmision = 'S/I';
    }

    if (empty($row['fecha_vencimiento']) ||
    $fechaVencimiento === '0000-00-00' ||
    $fechaVencimiento === '-0001-11-30'
    ) {
        $fechaVencimiento = 'S/I';
    }

    $hoy = date('Y-m-d');
    $vigencia = $row['vigencia'];
    $cumplimiento = (int)$row['cumplimiento'];
    $fechaVenc = $row['fecha_vencimiento'];

        //CASOS ESPECIALES (sin fechas)
    if ($vigencia === 'Cuando se realice cambio' || $vigencia === 'Permanente') {

        if ($cumplimiento === 100) {
            $estatus = [
                "titulo" => 'Finalizado',
                "color_css" => 'text-bg-success',
                "color_hexa" => '#198754'
            ];
        } else {
            $estatus = [
                "titulo" => 'Pendiente',
                "color_css" => 'text-bg-warning',
                "color_hexa" => '#ffc107'
            ];
        }

    } else {

        //VALIDAR FECHA PRIMERO
        if (
            !empty($fechaVenc) &&
            $fechaVenc !== '0000-00-00' &&
            $fechaVenc !== '-0001-11-30'
        ) {

            $fechaNotificacion = date('Y-m-d', strtotime($fechaVenc . ' -30 days'));

            if ($fechaVenc < $hoy) {

                //VENCIDO (PRIORIDAD MÁXIMA)
                $estatus = [
                    "titulo" => 'Vencido',
                    "color_css" => 'text-bg-danger',
                    "color_hexa" => '#dc3545'
                ];

            } elseif ($fechaNotificacion <= $hoy) {

                //PRÓXIMO A VENCER
                $estatus = [
                    "titulo" => 'Próximo a vencer',
                    "color_css" => 'text-bg-warning',
                    "color_hexa" => '#fd7e14'
                ];

            } else {

                // SOLO SI NO ESTÁ EN RIESGO
                if ($cumplimiento === 100) {
                    $estatus = [
                        "titulo" => 'Finalizado',
                        "color_css" => 'text-bg-success',
                        "color_hexa" => '#198754'
                    ];
                } else {
                    $estatus = [
                        "titulo" => 'Pendiente',
                        "color_css" => 'text-bg-warning',
                        "color_hexa" => '#ffc107'
                    ];
                }
            }

        } else {

            // SIN FECHA → solo cumplimiento
            if ($cumplimiento === 100) {
                $estatus = [
                    "titulo" => 'Finalizado',
                    "color_css" => 'text-bg-success',
                    "color_hexa" => '#198754'
                ];
            } else {
                $estatus = [
                    "titulo" => 'Pendiente',
                    "color_css" => 'text-bg-warning',
                    "color_hexa" => '#ffc107'
                ];
            }
        }
    }

        $data[] = [
            "id" => $row['id'],
            "dependencia" => $row['dependencia'],
            "permiso" => $row['permiso'],
            "vigencia" => $row['vigencia'],
            "fecha_emision" => $fechaEmision,
            "fecha_vencimiento" => $fechaVencimiento,
            "acuse_file" => $row['acuse_file'],
            "requisito_file" => $row['requisito_file'],
            "renovacion" => $row['renovacion'],
            "cumplimiento" => $row['cumplimiento'],
            "estatus" =>$estatus
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

    public function getPermisos($nGobierno, $sgm, $idActual = null)
    {
        header('Content-Type: application/json');

        $idEstacion = $this->estacionId();
        $estacion = Estacion::find($idEstacion);

        $estado = $estacion->di_estado;
        $municipio = $estacion->di_municipio;

        $query = RequisitosLegalesLista::whereIn('id_estacion', [$idEstacion, 0])
            ->where('nivel_gobierno', $nGobierno)
            ->where(function ($query) use ($municipio, $estado) {

                $query->where(function ($q) use ($municipio) {
                    $q->where('nivel_gobierno', 'Municipal')
                    ->where('mun_alc_est', $municipio);
                });

                $query->orWhere(function ($q) use ($estado) {
                    $q->where('nivel_gobierno', 'Estatal')
                    ->where('mun_alc_est', $estado);
                });

                $query->orWhereIn('nivel_gobierno', ['Federal', 'Varios']);
            })
            ->where('sgm', $sgm)
            ->where('estado', 1);

    
        if (!$idActual) {
            $query->whereDoesntHave('calendario', function ($query) use ($idEstacion) {
                $query->where('id_estacion', $idEstacion);
            });
        }

        $data = $query->selectRaw("
    id,
    CONCAT_WS(', ',
        NULLIF(nivel_gobierno,''),
        NULLIF(mun_alc_est,''),
        NULLIF(dependencia,''),
        NULLIF(permiso,'')
    ) AS permiso
")
->orderBy('permiso')
->get();

        echo json_encode($data);
        exit;
    }

    public function createPermisoDetalle()
        {
        header('Content-Type: application/json; charset=utf-8');

        if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear'
            ]);
            return;
        }

        $nivelGobierno = sanitize_input($_POST['nivel_gobierno'] ?? null, 'string');
        $permisoId = sanitize_input($_POST['permiso'] ?? null, 'int');
        $vigencia = sanitize_input($_POST['vigencia'] ?? null, 'string');
        $fechaEmision = sanitize_input($_POST['fecha_emision'] ?? null, 'string');
        $fechaVencimiento = sanitize_input($_POST['fecha_vencimiento'] ?? null, 'string');

        if (!$nivelGobierno || !$permisoId || !$vigencia || !$fechaEmision) {
            echo json_encode([
                'success' => false,
                'message' => 'Completa todos los campos obligatorios'
            ]);
            return;
        }

        $permiso = RequisitosLegalesLista::find($permisoId);

        if (!$permiso) {
            echo json_encode([
                'success' => false,
                'message' => 'El permiso seleccionado no existe'
            ]);
            return;
        }

        $meses = [
            'enero' => (int) ($_POST['enero'] ?? 0),
            'febrero' => (int) ($_POST['febrero'] ?? 0),
            'marzo' => (int) ($_POST['marzo'] ?? 0),
            'abril' => (int) ($_POST['abril'] ?? 0),
            'mayo' => (int) ($_POST['mayo'] ?? 0),
            'junio' => (int) ($_POST['junio'] ?? 0),
            'julio' => (int) ($_POST['julio'] ?? 0),
            'agosto' => (int) ($_POST['agosto'] ?? 0),
            'septiembre' => (int) ($_POST['septiembre'] ?? 0),
            'octubre' => (int) ($_POST['octubre'] ?? 0),
            'noviembre' => (int) ($_POST['noviembre'] ?? 0),
            'diciembre' => (int) ($_POST['diciembre'] ?? 0),
        ];

        $carpeta = __DIR__ . '../../../public/uploads/archivos/reuisitos-legales/';

        if (!file_exists($carpeta)) {
             mkdir_safe($carpeta, true);
        }

        $acusePath = null;
        $requisitoPath = null;
        $transactionStarted = false;

        try {
            if (!empty($_FILES['acuse_pdf']) && $_FILES['acuse_pdf']['error'] === UPLOAD_ERR_OK) {
                $acusePath = $this->guardarArchivoRequisitoLegal($_FILES['acuse_pdf'], $carpeta, 'acuse_');
            }

            if (!empty($_FILES['requisito_pdf']) && $_FILES['requisito_pdf']['error'] === UPLOAD_ERR_OK) {
                $requisitoPath = $this->guardarArchivoRequisitoLegal($_FILES['requisito_pdf'], $carpeta, 'requisito_');
            }

            Capsule::beginTransaction();
            $transactionStarted = true;

            $calendario = RequisitosLegalesCalendario::create([
                'id_estacion' => $this->estacionId(),
                'id_requisito_legal' => $permiso->id,
                'nivel_gobierno' => $nivelGobierno,
                'requisito_legal' => $permiso->permiso,
                'vigencia' => $vigencia,
                'enero' => $meses['enero'],
                'febrero' => $meses['febrero'],
                'marzo' => $meses['marzo'],
                'abril' => $meses['abril'],
                'mayo' => $meses['mayo'],
                'junio' => $meses['junio'],
                'julio' => $meses['julio'],
                'agosto' => $meses['agosto'],
                'septiembre' => $meses['septiembre'],
                'octubre' => $meses['octubre'],
                'noviembre' => $meses['noviembre'],
                'diciembre' => $meses['diciembre'],
                'estado' => 1
            ]);

            RequisitosLegalesMatriz::create([
                'idcalendario' => $calendario->id,
                'fecha_emision' => $fechaEmision,
                'fecha_vencimiento' => $fechaVencimiento ?: '',
                'acusepdf' => $acusePath ?: '',
                'requisitolegalpdf' => $requisitoPath ?: '',
                'estado' => 1
            ]);

            Capsule::commit();

            $cumplimiento = round(
                RequisitosLegalesCalendario::ToRequisitos($this->estacionId(), $nivelGobierno,0)['Cumplimiento'] ?? 0
            );

            echo json_encode([
                'success' => true,
                'id' => $calendario->id,
                'cumplimiento' => $cumplimiento,
                'message' => 'Requisito legal guardado correctamente'
            ]);
        } catch (\Throwable $e) {
            if ($transactionStarted) {
                Capsule::rollBack();
            }

            if ($acusePath && file_exists(__DIR__ . '../../../public/uploads/' . ltrim($acusePath, '/'))) {
                unlink(__DIR__ . '../../../public/uploads/' . ltrim($acusePath, '/'));
            }

            if ($requisitoPath && file_exists(__DIR__ . '../../../public/uploads/' . ltrim($requisitoPath, '/'))) {
                unlink(__DIR__ . '../../../public/uploads/' . ltrim($requisitoPath, '/'));
            }

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    private function guardarArchivoRequisitoLegal(array $file, string $carpeta, string $prefijo): string
    {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        $nombreArchivo = uniqid($prefijo, true) . '.' . $extension;
        $rutaDestino = $carpeta . $nombreArchivo;

        if (!move_uploaded_file($file['tmp_name'], $rutaDestino)) {
            throw new \Exception('No se pudo guardar el archivo');
        }

        return 'archivos/reuisitos-legales/' . $nombreArchivo;
    }

    public function deleteDetalle(){

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

        Capsule::beginTransaction();

        try {

            $calendario = RequisitosLegalesCalendario::find($id);

            if (!$calendario) {
                throw new \Exception('Registro no encontrado');
            }

            $nivelGobierno = $calendario->nivel_gobierno;
            $matrices = RequisitosLegalesMatriz::where('idcalendario', $id)->get();
            

            foreach ($matrices as $m) {

                if (!empty($m->acusepdf)) {
                    $file = __DIR__ . '../../../public/uploads/' . ltrim($m->acusepdf, '/');
                    if (file_exists($file)) {
                        unlink($file);
                    }
                }

                if (!empty($m->requisitolegalpdf)) {
                    $file = __DIR__ . '../../../public/uploads/' . ltrim($m->requisitolegalpdf, '/');
                    if (file_exists($file)) {
                        unlink($file);
                    }
                }

                 if ($m instanceof RequisitosLegalesMatriz) {
                    $m->delete();
                }
            }

            // eliminar calendario
            $calendario->delete();

            Capsule::commit();

            $cumplimiento = round(
                RequisitosLegalesCalendario::ToRequisitos($this->estacionId(), $nivelGobierno,0)['Cumplimiento'] ?? 0
            );

            echo json_encode([
                'success' => true,
                'cumplimiento' => $cumplimiento,
                'message' => 'Requisito legal eliminado correctamente'
            ]);
            exit;

        } catch (\Throwable $e) {

            Capsule::rollBack();

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
            exit;
        }
    }

    public function getDetalle($id)
        {
            header('Content-Type: application/json');

            $calendario = RequisitosLegalesCalendario::with([
                'requisito',
                'matriz'
            ])->find($id);

            if (!$calendario) {
                echo json_encode(['success' => false]);
                exit;
            }

            if ($calendario->id_requisito_legal == 0) {

                $detalle = [
                    'nivel_gobierno' => $calendario->nivel_gobierno,
                    'permiso'        => $calendario->requisito_legal,
                    'vigencia'       => $calendario->vigencia,
                    'dependencia'    => null,
                    'fundamento'     => null,
                    'mun_alc_est'    => null
                ];

            } else {

                $detalleRL = $calendario->requisito;

                $detalle = [
                    'nivel_gobierno' => optional($detalleRL)->nivel_gobierno,
                    'permiso'        => optional($detalleRL)->permiso,
                    'vigencia'       => $calendario->vigencia,
                    'dependencia'    => optional($detalleRL)->dependencia,
                    'fundamento'     => optional($detalleRL)->fundamento,
                    'mun_alc_est'    => optional($detalleRL)->mun_alc_est
                ];
            }

            $matriz = $calendario->matriz->map(function ($m) {

                return [
                    'fecha_emision' => ($m->fecha_emision && $m->fecha_emision != '0000-00-00')
                        ? formatearFecha($m->fecha_emision)
                        : 'S/I',

                    'fecha_vencimiento' => ($m->fecha_vencimiento && $m->fecha_vencimiento != '0000-00-00')
                        ? formatearFecha($m->fecha_vencimiento)
                        : 'S/I',

                    'acuse' => $m->acusepdf ? basename($m->acusepdf) : '',
                    'requisito' => $m->requisitolegalpdf ? basename($m->requisitolegalpdf) : ''
                ];
            });

    
            $meses = [
                'enero'      => $calendario->enero,
                'febrero'    => $calendario->febrero,
                'marzo'      => $calendario->marzo,
                'abril'      => $calendario->abril,
                'mayo'       => $calendario->mayo,
                'junio'      => $calendario->junio,
                'julio'      => $calendario->julio,
                'agosto'     => $calendario->agosto,
                'septiembre' => $calendario->septiembre,
                'octubre'    => $calendario->octubre,
                'noviembre'  => $calendario->noviembre,
                'diciembre'  => $calendario->diciembre,
            ];

            echo json_encode([
                'success' => true,
                'detalle' => $detalle,
                'matriz'  => $matriz,
                'renovacion'   => $meses,
                'id_requisito_legal' => $calendario->id_requisito_legal
            ]);

            exit;
    }

    public function getHistorialDetalle($id)
    {
        header('Content-Type: application/json');

        $calendario = RequisitosLegalesCalendario::where('id', $id)
            ->where('id_estacion', $this->estacionId())
            ->first();

        if (!$calendario) {
            echo json_encode([
                'success' => false,
                'message' => 'Registro no encontrado'
            ]);
            exit;
        }

        echo json_encode([
            'success' => true,
            'rows' => $this->formatHistorialRows($calendario->id)
        ]);
        exit;
    }

    public function createHistorialDetalle($id)
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear'
            ]);
            exit;
        }

        $calendario = RequisitosLegalesCalendario::where('id', $id)
            ->where('id_estacion', $this->estacionId())
            ->first();

        if (!$calendario) {
            echo json_encode([
                'success' => false,
                'message' => 'Registro no encontrado'
            ]);
            exit;
        }

        $fechaEmision = $_POST['fecha_emision'] ?? null;
        $fechaVencimiento = $_POST['fecha_vencimiento'] ?? '';

        if (!$fechaEmision) {
            echo json_encode([
                'success' => false,
                'message' => 'La fecha de emisión es obligatoria'
            ]);
            exit;
        }

        $carpeta = __DIR__ . '../../../public/uploads/archivos/reuisitos-legales/';

        if (!file_exists($carpeta)) {
             mkdir_safe($carpeta, true);
        }

        $acusePath = '';
        $requisitoPath = '';

        try {
            if (!empty($_FILES['acuse_pdf']) && $_FILES['acuse_pdf']['error'] === UPLOAD_ERR_OK) {
                $acusePath = $this->guardarArchivoRequisitoLegal($_FILES['acuse_pdf'], $carpeta, 'acuse_hist_');
            }

            if (!empty($_FILES['requisito_pdf']) && $_FILES['requisito_pdf']['error'] === UPLOAD_ERR_OK) {
                $requisitoPath = $this->guardarArchivoRequisitoLegal($_FILES['requisito_pdf'], $carpeta, 'requisito_hist_');
            }

            RequisitosLegalesMatriz::create([
                'idcalendario' => $calendario->id,
                'fecha_emision' => $fechaEmision,
                'fecha_vencimiento' => $fechaVencimiento ?: '',
                'acusepdf' => $acusePath,
                'requisitolegalpdf' => $requisitoPath,
                'estado' => 1
            ]);

            echo json_encode([
                'success' => true,
                'rows' => $this->formatHistorialRows($calendario->id),
                'cumplimiento' => $this->getCumplimientoPorCalendario($calendario),
                'message' => 'Historial guardado correctamente'
            ]);
            exit;
        } catch (\Throwable $e) {
            if ($acusePath && file_exists(__DIR__ . '../../../public/uploads/' . ltrim($acusePath, '/'))) {
                unlink(__DIR__ . '../../../public/uploads/' . ltrim($acusePath, '/'));
            }

            if ($requisitoPath && file_exists(__DIR__ . '../../../public/uploads/' . ltrim($requisitoPath, '/'))) {
                unlink(__DIR__ . '../../../public/uploads/' . ltrim($requisitoPath, '/'));
            }

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
            exit;
        }
    }

    public function updateHistorialDetalle($id)
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!ModuloService::validaPermiso($this->modulo, 'editar')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para editar'
            ]);
            exit;
        }

        $matriz = RequisitosLegalesMatriz::find($id);

        if (!$matriz) {
            echo json_encode([
                'success' => false,
                'message' => 'Registro no encontrado'
            ]);
            exit;
        }

        $calendario = RequisitosLegalesCalendario::where('id', $matriz->idcalendario)
            ->where('id_estacion', $this->estacionId())
            ->first();

        if (!$calendario) {
            echo json_encode([
                'success' => false,
                'message' => 'Calendario no encontrado'
            ]);
            exit;
        }

        $fechaEmision = $_POST['fecha_emision'] ?? null;
        $fechaVencimiento = $_POST['fecha_vencimiento'] ?? '';

        if (!$fechaEmision) {
            echo json_encode([
                'success' => false,
                'message' => 'La fecha de emisión es obligatoria'
            ]);
            exit;
        }

        $carpeta = __DIR__ . '../../../public/uploads/archivos/reuisitos-legales/';

        if (!file_exists($carpeta)) {
             mkdir_safe($carpeta, true);
        }

        try {
            $matriz->fecha_emision = $fechaEmision;
            $matriz->fecha_vencimiento = $fechaVencimiento ?: '';

            if (!empty($_FILES['acuse_pdf']) && $_FILES['acuse_pdf']['error'] === UPLOAD_ERR_OK) {
                if (!empty($matriz->acusepdf) && file_exists(__DIR__ . '../../../public/uploads/' . ltrim($matriz->acusepdf, '/'))) {
                    unlink(__DIR__ . '../../../public/uploads/' . ltrim($matriz->acusepdf, '/'));
                }

                $matriz->acusepdf = $this->guardarArchivoRequisitoLegal($_FILES['acuse_pdf'], $carpeta, 'acuse_hist_');
            }

            if (!empty($_FILES['requisito_pdf']) && $_FILES['requisito_pdf']['error'] === UPLOAD_ERR_OK) {
                if (!empty($matriz->requisitolegalpdf) && file_exists(__DIR__ . '../../../public/uploads/' . ltrim($matriz->requisitolegalpdf, '/'))) {
                    unlink(__DIR__ . '../../../public/uploads/' . ltrim($matriz->requisitolegalpdf, '/'));
                }

                $matriz->requisitolegalpdf = $this->guardarArchivoRequisitoLegal($_FILES['requisito_pdf'], $carpeta, 'requisito_hist_');
            }

            $matriz->save();

            echo json_encode([
                'success' => true,
                'rows' => $this->formatHistorialRows($calendario->id),
                'cumplimiento' => $this->getCumplimientoPorCalendario($calendario),
                'message' => 'Historial actualizado correctamente'
            ]);
            exit;
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
            exit;
        }
    }

    public function deleteHistorialDetalle()
    {
        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;

        if (!ModuloService::validaPermiso($this->modulo, 'eliminar')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para eliminar'
            ]);
            exit;
        }

        $matriz = RequisitosLegalesMatriz::find($id);

        if (!$matriz) {
            echo json_encode([
                'success' => false,
                'message' => 'Registro no encontrado'
            ]);
            exit;
        }

        $calendario = RequisitosLegalesCalendario::where('id', $matriz->idcalendario)
            ->where('id_estacion', $this->estacionId())
            ->first();

        if (!$calendario) {
            echo json_encode([
                'success' => false,
                'message' => 'Calendario no encontrado'
            ]);
            exit;
        }

        if (!empty($matriz->acusepdf) && file_exists(__DIR__ . '../../../public/uploads/' . ltrim($matriz->acusepdf, '/'))) {
            unlink(__DIR__ . '../../../public/uploads/' . ltrim($matriz->acusepdf, '/'));
        }

        if (!empty($matriz->requisitolegalpdf) && file_exists(__DIR__ . '../../../public/uploads/' . ltrim($matriz->requisitolegalpdf, '/'))) {
            unlink(__DIR__ . '../../../public/uploads/' . ltrim($matriz->requisitolegalpdf, '/'));
        }

        $matriz->delete();

        echo json_encode([
            'success' => true,
            'rows' => $this->formatHistorialRows($calendario->id),
            'cumplimiento' => $this->getCumplimientoPorCalendario($calendario),
            'message' => 'Historial eliminado correctamente'
        ]);
        exit;
    }

    private function formatHistorialRows($calendarioId)
    {
        return RequisitosLegalesMatriz::where('idcalendario', $calendarioId)
            ->orderBy('fecha_emision', 'desc')
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'fecha_emision' => $row->fecha_emision ? formatearFecha($row->fecha_emision) : 'S/I',
                    'fecha_vencimiento' => $row->fecha_vencimiento ? formatearFecha($row->fecha_vencimiento) : 'S/I',
                    'fecha_emision_raw' => $row->fecha_emision ? $row->fecha_emision->format('Y-m-d') : '',
                    'fecha_vencimiento_raw' => $row->fecha_vencimiento ? $row->fecha_vencimiento->format('Y-m-d') : '',
                    'acusepdf' => $row->acusepdf ? basename($row->acusepdf) : '',
                    'requisitolegalpdf' => $row->requisitolegalpdf ? basename($row->requisitolegalpdf) : ''
                ];
            })->values();
    }

    private function getCumplimientoPorCalendario(RequisitosLegalesCalendario $calendario)
    {
        return round(
            RequisitosLegalesCalendario::ToRequisitos($this->estacionId(), $calendario->nivel_gobierno,0)['Cumplimiento'] ?? 0
        );
    }

    public function updatePermisoDetalle($id)
    {
            header('Content-Type: application/json');

             if (!ModuloService::validaPermiso($this->modulo, 'editar')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para editar'
            ]);
            return;
        }

            try {

                $idEstacion = $this->estacionId();

                $registro = RequisitosLegalesCalendario::where('id', $id)
                    ->where('id_estacion', $idEstacion)
                    ->first();

                if (!$registro) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Registro no encontrado'
                    ]);
                    exit;
                }

                $permiso = $_POST['permiso'] ?? null;
                $vigencia = $_POST['vigencia'] ?? null;

                if (!$permiso || !$vigencia) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Datos incompletos'
                    ]);
                    exit;
                }

     
                $registro->id_requisito_legal = $permiso;
                $registro->vigencia = $vigencia;

   
                $registro->enero = $_POST['enero'] ?? 0;
                $registro->febrero = $_POST['febrero'] ?? 0;
                $registro->marzo = $_POST['marzo'] ?? 0;
                $registro->abril = $_POST['abril'] ?? 0;
                $registro->mayo = $_POST['mayo'] ?? 0;
                $registro->junio = $_POST['junio'] ?? 0;
                $registro->julio = $_POST['julio'] ?? 0;
                $registro->agosto = $_POST['agosto'] ?? 0;
                $registro->septiembre = $_POST['septiembre'] ?? 0;
                $registro->octubre = $_POST['octubre'] ?? 0;
                $registro->noviembre = $_POST['noviembre'] ?? 0;
                $registro->diciembre = $_POST['diciembre'] ?? 0;

                $registro->save();

                $cumplimiento = round(
                    RequisitosLegalesCalendario::ToRequisitos($this->estacionId(), $registro->nivel_gobierno,0)['Cumplimiento'] ?? 0
                );

                echo json_encode([
                    'success' => true,
                    'cumplimiento' => $cumplimiento,
                    'message' => 'Registro actualizado correctamente'
                ]);

            } catch (\Throwable $e) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Error al actualizar',
                    'error' => $e->getMessage()
                ]);
            }

            exit;
    }
    
}
