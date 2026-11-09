<?php

namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Services\ModuleStationService;
use App\Services\DropdownYearMesService;
use App\Models\Sasisopa\ComunicacionIE;
use App\Models\Sasisopa\ComunicacionEvidencia;
use App\Models\Sasisopa\QuejasSugerencia;
use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Puestos;
use App\Models\Sasisopa\ListaAsistencia;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Capsule\Manager as Capsule;

class ComunicacionParticipacionConsultaController extends BaseController{

protected string $modulo = 'sasisopa';

    private function estacionModulo(): ?int
    {
        return ModuleStationService::getContext('sasisopa')['id_estacion'] ?? null;
    }

    /**
     * Accept the multiselect's JSON array and legacy comma-separated values,
     * then persist only unique positive puesto IDs.
     */
    private function normalizarDestinatarios(mixed $values): array
    {
        if (is_string($values)) {
            $values = $values === '' ? [] : explode(',', $values);
        }

        if (!is_array($values)) {
            throw new \InvalidArgumentException('Selecciona puestos válidos en "Dirigido a".');
        }

        $ids = [];
        foreach ($values as $value) {
            if (is_string($value)) {
                $value = trim($value);
            }

            if (!is_scalar($value) || filter_var($value, FILTER_VALIDATE_INT) === false) {
                throw new \InvalidArgumentException('La selección de "Dirigido a" contiene un puesto inválido.');
            }

            $id = (int) $value;
            if ($id <= 0) {
                throw new \InvalidArgumentException('La selección de "Dirigido a" contiene un puesto inválido.');
            }

            $ids[$id] = $id;
        }

        return array_values($ids);
    }

    private function destinatariosActivos(array $ids): bool
    {
        return $ids === [] || Puestos::where('estatus', 0)
            ->whereIn('id', $ids)
            ->count() === count($ids);
    }

  public function index(int $year = 0){

        if ($year <= 0) {
            header('Location: /sasisopa/comunicacion-participacion-consulta/' . date('Y'));
            exit;
        }

        $year = DropdownYearMesService::validarYearMes($year, 1)['idYear'];
        $yearMesTemplate = '/sasisopa/comunicacion-participacion-consulta/{year}';

        $title = '7. COMUNICACIÓN, PARTICIPACIÓN Y CONSULTA';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');
        Breadcrumb::add(DropdownYearMesService::dropdownYearManual($year, 1, 2019, $yearMesTemplate), '');

        // Buscar permisos de los modulos
        $permisos = ModuloService::permisosSesion($this->modulo);

        $idEstacion = $this->estacionModulo();

         $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'estacionId' => $idEstacion,
            'year' => $year,
            'yearMesTemplate' => $yearMesTemplate,
            'moduleStationKey' => 'sasisopa',
             'links' =>[
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                 '/libs/select2/dist/css/select2.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/core/module-station-selector.js?v=' . time(),
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/libs/select2/dist/js/select2.full.min.js',
                '/libs/select2/dist/js/select2.min.js',
                '/js/comunicacionparticipacionconsulta/registrocomunicacion.datatable.init.js?v=' . time(),
                '/js/comunicacionparticipacionconsulta/quejassugerencias.datatable.init.js?v=' . time(),
                '/js/comunicacionparticipacionconsulta/index.action.init.js?v=' . time(),
            ],
            'help' => true
        ];
        
        View::render('comunicacionparticipacionconsulta/index', $data,'sasisopa');

}

    public function datatableRegistroComunicacion()
    {
        $year = $_GET['year'] ?? null;

        $permisos = [
            'crear'      => ModuloService::validaPermiso($this->modulo, 'crear'),
            'editar'     => ModuloService::validaPermiso($this->modulo, 'editar'),
            'eliminar'   => ModuloService::validaPermiso($this->modulo, 'eliminar'),
            'descargar'  => ModuloService::validaPermiso($this->modulo, 'descargar'),
        ];

        $query = ComunicacionIE::query()

            ->with([
                'encargado:id,nombre'
            ])

           ->select([
            'id',
            'id_estacion',
            'no_comunicacion',
            'fecha',
            'tema',
            'detalle',
            'tipo_comunicacion',
            'material',
            'seguimiento',
            'dirigidoa',
            'encargado_comunicacion',
            'asistencia'
        ])

            ->where(
                'id_estacion',
                $this->estacionModulo()
            );

        // FILTRAR POR AÑO
        if (!empty($year)) {

            $query->whereYear('fecha', $year);
        }

        $data = $query

            ->orderBy('fecha')

            ->get()

            ->map(function ($item) {

                return [
                'id' => $item->id,
                'no_comunicacion' => $item->no_comunicacion,
                'fecha' => $item->fecha,
                'tema' => $item->tema,
                'detalle' => $item->detalle,
                'tipo_comunicacion' => $item->tipo_comunicacion,
                'material' => $item->material,
                'seguimiento' => $item->seguimiento ?? 'S/I',
                'dirigidoa' => $item->dirigidoa,
                'asistencia' => $item->asistencia,
                'encargado_comunicacion' => $item->encargado?->nombre ?? 'S/I',
                ];
            });

        echo json_encode([
            'data' => $data,
            'permisos' => $permisos
        ]);
    }

    public function createRegistroComunicacion()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {

            $data = json_decode(
                file_get_contents('php://input'),
                true
            );

            
            $tema = sanitize_input($data['tema'] ?? null, 'string');
            $detalle = sanitize_input($data['detalle'] ?? null, 'string');
            $tipo_comunicacion = sanitize_input($data['tipo_comunicacion'] ?? null, 'string');
            $material = sanitize_input($data['material'] ?? null, 'string');
            $dirigidoaIds = $this->normalizarDestinatarios($data['dirigidoa'] ?? []);
            $seguimiento = sanitize_input($data['seguimiento'] ?? null, 'string');

            if (!in_array($tipo_comunicacion, ['Interna', 'Externa'], true)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Selecciona un tipo de comunicación válido.'
                ]);
                return;
            }

            if (!ModuloService::validaPermiso($this->modulo, 'crear')) {

                echo json_encode([
                    'success' => false,
                    'message' => 'No tienes permiso para crear'
                ]);

                return;
            }

            if (
                empty($tema) ||
                empty($detalle) ||
                empty($tipo_comunicacion) ||
                empty($material)
            ) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Completa todos los campos obligatorios'
                ]);

                return;
            }

            if ($tipo_comunicacion === 'Interna' && $dirigidoaIds === []) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Selecciona al menos un puesto en "Dirigido a".'
                ]);
                return;
            }

            if ($tipo_comunicacion === 'Interna' && !$this->destinatariosActivos($dirigidoaIds)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Uno o más puestos seleccionados ya no están disponibles. Actualiza la página e inténtalo de nuevo.'
                ]);
                return;
            }

            if ($tipo_comunicacion === 'Externa' && empty($seguimiento)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Selecciona el seguimiento de la comunicación.'
                ]);
                return;
            }

            $id_estacion = $this->estacionModulo();
            $id_usuario = $this->userId();

            Capsule::beginTransaction();

            // Obtener último consecutivo
            $ultimo = ComunicacionIE::where(
                'id_estacion',
                $id_estacion
            )
            ->max('no_comunicacion');

            $no_comunicacion = $ultimo
                ? $ultimo + 1
                : 1;

            $comunicacion = ComunicacionIE::create([
                'id_estacion'            => $id_estacion,
                'no_comunicacion'       => $no_comunicacion,
                'fecha'                 => date('Y-m-d'),
                'tema'                  => $tema,
                'detalle'               => $detalle,
                'encargado_comunicacion'=> $id_usuario,
                'tipo_comunicacion'     => $tipo_comunicacion,
                'material'              => $material,
                'seguimiento'           => $tipo_comunicacion === 'Externa' ? $seguimiento : '',
                'dirigidoa'             => $tipo_comunicacion === 'Interna'
                    ? implode(',', $dirigidoaIds)
                    : '',
                'url'                   => '',
                'asistencia'            => 0
            ]);

            Capsule::commit();

            echo json_encode([
                'success' => true,
                'message' => 'Comunicación creada correctamente',
                'id'      => $comunicacion->id
            ]);

        } catch (\Throwable $e) {

            try {
                Capsule::rollBack();
            } catch (\Throwable $rollbackError) {
                error_log('Error al revertir creación de comunicación: ' . $rollbackError->getMessage());
            }

            error_log('Error al crear comunicación: ' . $e->getMessage());

            echo json_encode([
                'success' => false,
                'message' => $e instanceof \InvalidArgumentException
                    ? $e->getMessage()
                    : 'Error al guardar'
            ]);
        }
    }

    public function updateRegistroComunicacion()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {

             $data = json_decode(
                file_get_contents('php://input'),
                true
            );

            
            $id = sanitize_input($data['id'] ?? null, 'int');
            $tema = sanitize_input($data['tema'] ?? null, 'string');
            $detalle = sanitize_input($data['detalle'] ?? null, 'string');
            $tipo_comunicacion = sanitize_input($data['tipo_comunicacion'] ?? null, 'string');
            $material = sanitize_input($data['material'] ?? null, 'string');
            $seguimiento = sanitize_input($data['seguimiento'] ?? null, 'string');
            $dirigidoaIds = $this->normalizarDestinatarios($data['dirigidoa'] ?? []);

            if (!in_array($tipo_comunicacion, ['Interna', 'Externa'], true)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Selecciona un tipo de comunicación válido.'
                ]);
                return;
            }

            if ($tipo_comunicacion === 'Interna' && $dirigidoaIds === []) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Selecciona al menos un puesto en "Dirigido a".'
                ]);
                return;
            }

            if ($tipo_comunicacion === 'Interna' && !$this->destinatariosActivos($dirigidoaIds)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Uno o más puestos seleccionados ya no están disponibles. Actualiza la página e inténtalo de nuevo.'
                ]);
                return;
            }

            if (!ModuloService::validaPermiso($this->modulo, 'editar')) {

                echo json_encode([
                    'success' => false,
                    'message' => 'No tienes permiso para editar'
                ]);

                return;
            }

            $registro = ComunicacionIE::where(
                'id_estacion',
                $this->estacionModulo()
            )->find($id);

            if (!$registro) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Registro no encontrado'
                ]);

                return;
            }

            $registro->update([
                'tema' => $tema,
                'detalle' => $detalle,
                'tipo_comunicacion' => $tipo_comunicacion,
                'material' => $material,
                'seguimiento' => $tipo_comunicacion === 'Externa' ? $seguimiento : '',
                'dirigidoa' => $tipo_comunicacion === 'Interna'
                    ? implode(',', $dirigidoaIds)
                    : '',
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
    }
    public function deleteRegistroComunicacion()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {

            $data = json_decode(
                file_get_contents('php://input'),
                true
            );

            $id = (int) ($data['id'] ?? 0);

            // =========================
            // VALIDAR PERMISOS
            // =========================

            if (!ModuloService::validaPermiso($this->modulo, 'eliminar')) {

                echo json_encode([
                    'success' => false,
                    'message' => 'No tienes permiso para eliminar'
                ]);

                return;
            }

            // =========================
            // BUSCAR REGISTRO
            // =========================

            $registro = ComunicacionIE::with('evidencias')
                ->where('id_estacion', $this->estacionModulo())
                ->find($id);

            if (!$registro) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Registro no encontrado'
                ]);

                return;
            }

            // =========================
            // ELIMINAR ARCHIVOS
            // =========================

            foreach ($registro->evidencias as $evidencia) {

                $ruta = realpath(
                    __DIR__ . '/../../public/uploads/archivos/evidencias/'
                    . $evidencia->archivo
                );

                if ($ruta && file_exists($ruta)) {

                    unlink($ruta);
                }

                $evidencia->delete();
            }

            // =========================
            // ELIMINAR REGISTRO
            // =========================

            $registro->delete();

            echo json_encode([
                'success' => true,
                'message' => 'Comunicación eliminada correctamente'
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function createEvidencia()
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!ModuloService::validaPermiso($this->modulo, 'crear')) {

            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso'
            ]);

            return;
        }

        $idComunicacion = (int) ($_POST['id_comunicacion'] ?? 0);
        $idEstacion = $this->estacionModulo();
        $archivoMovido = null;

        try {
            if ($idComunicacion <= 0 || !$idEstacion) {
                throw new \InvalidArgumentException('Comunicación inválida.');
            }

            // El id recibido no basta para autorizar la carga: debe pertenecer
            // a la estación activa del módulo SASISOPA.
            $comunicacion = ComunicacionIE::where('id_estacion', $idEstacion)
                ->find($idComunicacion);

            if (!$comunicacion) {
                throw new \InvalidArgumentException('La comunicación no existe o no pertenece a la estación activa.');
            }

            $file = $_FILES['evidencia'] ?? null;
            if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
                throw new \InvalidArgumentException('Selecciona una imagen válida.');
            }

            if (!is_uploaded_file($file['tmp_name'])) {
                throw new \InvalidArgumentException('No se recibió correctamente el archivo.');
            }

            if (($file['size'] ?? 0) <= 0 || $file['size'] > 10 * 1024 * 1024) {
                throw new \InvalidArgumentException('La imagen debe pesar menos de 10 MB.');
            }

            // Validar el contenido real del archivo; no confiar en su nombre o extensión.
            $imageInfo = @getimagesize($file['tmp_name']);
            $mime = $imageInfo['mime'] ?? '';
            $extensions = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
            ];

            if (!isset($extensions[$mime])) {
                throw new \InvalidArgumentException('Formato no permitido. Usa una imagen JPG, PNG o GIF.');
            }

            $directory = __DIR__ . '/../../public/uploads/archivos/evidencias/';
            if (!is_dir($directory)) {
                mkdir_safe($directory, true);
            }
            if (!is_dir($directory) || !is_writable($directory)) {
                throw new \RuntimeException('No se pudo preparar la carpeta para guardar la evidencia.');
            }

            $filename = 'evidencia_' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
            $destination = $directory . $filename;

            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                throw new \RuntimeException('No se pudo guardar la imagen seleccionada.');
            }
            $archivoMovido = $destination;

            ComunicacionEvidencia::create([
                'id_comunicacion' => (int) $comunicacion->id,
                'archivo' => $filename,
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Evidencia agregada correctamente'
            ]);
        } catch (\Throwable $e) {
            if ($archivoMovido && is_file($archivoMovido)) {
                @unlink($archivoMovido);
            }

            error_log('Error al guardar evidencia de comunicación: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => $e instanceof \InvalidArgumentException
                    ? $e->getMessage()
                    : 'No se pudo guardar la evidencia. Intenta nuevamente.'
            ]);
        }
    }

    public function getEvidencias(int $id)
    {
        header('Content-Type: application/json; charset=utf-8');

        $evidencias =
            ComunicacionEvidencia::where(
                'id_comunicacion',
                $id
            )->get();

        $data = $evidencias->map(function ($item) {

            return [
                'id' => $item->id,
                'archivo' => $item->archivo,
                'url' => $_ENV['APP_URL'] . '/uploads/archivos/evidencias/' . $item->archivo
            ];
        });

        echo json_encode($data);
    }

    public function deleteEvidencia()
    {
        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);

        if (!ModuloService::validaPermiso($this->modulo, 'eliminar')) {

            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso'
            ]);

            return;
        }

        $id = $data['id'] ?? null;

        $evidencia =
            ComunicacionEvidencia::find($id);

        if (!$evidencia) {

            echo json_encode([
                'success' => false,
                'message' => 'Evidencia no encontrada'
            ]);

            return;
        }

        $ruta =
            __DIR__ . '../../../public/uploads/archivos/evidencias/' .
            ltrim($evidencia->archivo, '/');

        if (file_exists($ruta)) {

            unlink($ruta);
        }

        $evidencia->delete();

        echo json_encode([
            'success' => true,
            'message' => 'Evidencia eliminada correctamente'
        ]);
    }

    public function getDetalleComunicacion(int $id)
    {
        header('Content-Type: application/json; charset=utf-8');

        try {

            $comunicacion = ComunicacionIE::with('encargado')
                ->where('id_estacion', $this->estacionModulo())
                ->find($id);

            if (!$comunicacion) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Registro no encontrado'
                ]);

                return;
            }

            $puestos = [];

            if (!empty($comunicacion->dirigidoa)) {

                $ids = explode(',', $comunicacion->dirigidoa);

                $puestos = Puestos::whereIn('id', $ids)
                    ->pluck('tipo_puesto')
                    ->toArray();
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $comunicacion->id,
                    'fecha' => $comunicacion->fecha,
                    'tema' => $comunicacion->tema,
                    'detalle' => $comunicacion->detalle,
                    'encargado_comunicacion' => $comunicacion->encargado->nombre ?? 'S/I',
                    'tipo_comunicacion' => $comunicacion->tipo_comunicacion,
                    'material' => $comunicacion->material,
                    'seguimiento' => $comunicacion->seguimiento,
                    'dirigidoa' => $comunicacion->dirigidoa,
                    'puestos' => $puestos,
                    'url' => $comunicacion->url ?: null,
                    'asistencia_url' => $comunicacion->asistencia
                        ? '/lista-asistencia/pdf/' . $comunicacion->asistencia
                        : null
                ]
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function pdfRegistroComunicacion(){

    $idEstacion = $this->estacionModulo();
    $registro = $idEstacion ? Estacion::find($idEstacion) : null;
    $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png'; 

    if (!$registro) {
        return "No se encontró la información";
    }

    $apoderadolegal = $registro->apoderado_legal;

    $id = $_GET['id'] ?? null;
    $year = $_GET['year'] ?? null;
    $inicio = $_GET['inicio'] ?? null;
    $fin = $_GET['fin'] ?? null;

    $query = ComunicacionIE::with([
        'encargado',
        'evidencias'
    ])
    ->where('id_estacion', $idEstacion);

    if (!empty($id)) {
        $query->where('id', (int) $id);
    } elseif (!empty($inicio) && !empty($fin)) {

        $query->whereBetween('fecha', [
            $inicio,
            $fin
        ]);

    } elseif (!empty($year)) {
        $query->whereYear('fecha', $year);
    }

    $comunicaciones = $query
        ->orderByDesc('fecha')
        ->get();
   
     $html = '
    <!DOCTYPE html>
    <html>
    <head>
    <meta charset="UTF-8">
    <title>Registro de la atención y el seguimiento a la comunicación interna y externa.</title>
    <link rel="stylesheet" href="'.$_ENV['APP_URL'].'/assets/css/pdf.css">

    <style>
    .img-evidencia{
        width:180px;
        margin:5px;
        border:1px solid #DDD;
        padding:5px;
    }
    .firma{
        width:60px;
    }
    .titulo-seccion{
        margin-top:20px;
        margin-bottom:10px;
        font-size:14px;
        font-weight:bold;
    }
    </style>
    </head>
    <body>
     <table class="table">
        <tr>
            <td class="text-center">
                <img src="'.$logo.'" style="width:130px">
            </td>
            <td colspan="2" class="text-center">
                <b>Registro de la atención y el seguimiento a la comunicación interna y externa. </b>
            </td>
            <td class="text-center"><b>Fo.ADMONGAS.010</b></td>
        </tr>
        <tr>
            <td class="text-center">Realizado por:<br>Nelly Estrada Garcia</td>
            <td class="text-center">Revisado por:<br>Eduardo Galicia Flores</td>
            <td class="text-center">Autorizado por:<br>'.$apoderadolegal.'</td>
            <td class="text-center">Fecha de aprobación:<br>01/10/2018</td>
        </tr>
    </table>';
    
    $totalRegistros = $comunicaciones->count();

    foreach ($comunicaciones as $index => $comunicacion) {
        
        if ($totalRegistros > 1 && $index > 0) {
        $html .= '<div style="page-break-before: always;"></div>';
        }

        $puestos = [];

        if (!empty($comunicacion->dirigidoa)) {

            $ids = explode(',', $comunicacion->dirigidoa);

            $puestos = Puestos::whereIn('id', $ids)
                ->pluck('tipo_puesto')
                ->toArray();
        }

        $lista = null;

        if (!empty($comunicacion->asistencia)) {

            $lista = ListaAsistencia::with('detalles')
                ->find($comunicacion->asistencia);
        }

        $html .= '
        <table class="table">
            <tr>
                <th width="25%">No. Comunicación</th>
                <td>' . $comunicacion->no_comunicacion . '</td>
            </tr>
            <tr>
                <th>Tema</th>
                <td>' . $comunicacion->tema . '</td>
            </tr>
            <tr>
                <th>Fecha</th>
                <td>' . formatearFecha($comunicacion->fecha) . '</td>
            </tr>
            <tr>
                <th>Encargado de la comunicación</th>
                <td>' . ($comunicacion->encargado->nombre ?? 'S/I') . '</td>
            </tr>
            <tr >
                <th>Tipo de comunicación</th>
                <td>' . $comunicacion->tipo_comunicacion . '</td>
            </tr>
            <tr>
                <th>Material utilizado</th>
                <td>' . $comunicacion->material . '</td>
            </tr>
            <tr>
                <th>Seguimiento</th>
                <td>' . $comunicacion->seguimiento . '</td>
            </tr>
            <tr>
                <th>Detalle</th>
                <td>' . $comunicacion->detalle . '</td>
            </tr>
            <tr>
                <th class="align-middle">Dirigido a</th>
            <td>
        ';

        foreach ($puestos as $puesto) {
            $html .= '<span class="badge mt-2">' . $puesto . '</span>';
        }

        $html .= '</td></tr></table>';

        // =========================
        // EVIDENCIAS
        // =========================

        if ($comunicacion->evidencias->count() > 0) {

            $html .= '<div class="titulo-seccion">Evidencias</div>
            <div class="mt-4">';

            foreach ($comunicacion->evidencias as $evidencia) {

                $ruta = $_ENV['APP_URL']. '/uploads/archivos/evidencias/'. $evidencia->archivo;
                $html .= '<img src="' . $ruta . '" class="img-evidencia">';

            }

            $html .= '</div>';
        }

        // =========================
        // LISTA ASISTENCIA
        // =========================

        if ($lista) {

            $html .= '<div class="titulo-seccion">Lista de asistencia</div>
            <table class="table">
                <tr>
                    <th>Hora</th>
                    <th>Lugar</th>
                    <th>Finalidad</th>
                </tr>
                <tr>
                    <td>' . date('g:i a', strtotime($lista->hora)) . '</td>
                    <td>' . $lista->lugar . '</td>
                    <td>' . $lista->finalidad . '</td>
                </tr>
            </table>

            <table class="table">
                <tr>
                    <th>Nombre</th>
                    <th>Puesto</th>
                    <th>Firma</th>
                </tr>
            ';

            foreach ($lista->detalles as $detalle) {

                $firma = Usuario::buscarFirma(
                    $detalle->usuario
                );
                                
                $firmaUsuario = '';
                $rutaFirma = realpath(__DIR__ . '/../../public/uploads/firma-personal/' . $firma);

                if (!empty($firma)) {

                    if (file_exists($rutaFirma)) {
                    $rutaPublica = $_ENV['APP_URL'] . '/uploads/firma-personal/' . $firma;
                    $firmaUsuario = '<img src="' . $rutaPublica . '" class="firma">';
                    } else {
                    $firmaUsuario = '';
                    }
                }

                $html .= '

                <tr>
                    <td>' . $detalle->usuario . '</td>
                    <td>' . $detalle->puesto . '</td>
                    <td class="text-center">' . $firmaUsuario . '</td>
                </tr>
                ';
            }

            $html .= '</table>';
        }

    }

    $html .= '</body></html>';

    // ======================
    // PDF
    // ======================
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait'); 
    $dompdf->render();

    return $dompdf->stream(
        "Registro de la atención y el seguimiento a la comunicación interna y externa.pdf",
        ["Attachment" => true]);

    }
    
    //----- Quejas y Sugerencias ---------

    public function datatableQuejasSugerencias(){

        $permisoEliminar   = ModuloService::validaPermiso($this->modulo, 'eliminar');
        $permisoDescargar = ModuloService::validaPermiso($this->modulo, 'descargar');

        $data = QuejasSugerencia::where('id_estacion',$this->estacionModulo())
        ->orderBy('fecha','desc')
        ->get();

         echo json_encode([
            "data" => $data,
             "permisos" => [
                "eliminar" => $permisoEliminar,
                "descargar" => $permisoDescargar
            ]
        ]);
        
        exit;
    }

    public function createQuejaSugerencia()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {

            $data = json_decode(file_get_contents('php://input'), true);

            $fecha           = $data['fecha'] ?? null;
            $nombre          = $data['nombre'] ?? null;
            $motivos         = $data['motivos'] ?? null;
            $dirigido        = $data['dirigido'] ?? null;
            $contacto        = $data['contacto'] ?? null;
            $nombre_puesto   = $data['nombre_puesto'] ?? null;
            $efectos         = $data['efectos'] ?? null;
            $solucion        = $data['solucion'] ?? null;
            $plazo           = $data['plazo'] ?? null;
            $confirmacion    = $data['confirmacion'] ?? null;

            if (!ModuloService::validaPermiso($this->modulo, 'crear')) {

                echo json_encode([
                    'success' => false,
                    'message' => 'No tienes permiso para crear'
                ]);

                return;
            }

            if (
                empty($fecha) ||
                empty($nombre) ||
                empty($motivos) ||
                empty($dirigido) ||
                empty($contacto) ||
                empty($nombre_puesto) ||
                empty($efectos) ||
                empty($solucion) ||
                empty($plazo) ||
                empty($confirmacion)
            ) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Completa todos los campos obligatorios'
                ]);

                return;
            }

            QuejasSugerencia::create([
                'id_estacion'      => $this->estacionModulo(),
                'fecha'            => $fecha,
                'nombre'           => $nombre,
                'motivos_causas'   => $motivos,
                'nombre_dirigido'  => $dirigido,
                'contacto'         => $contacto,
                'nombre_puesto'    => $nombre_puesto,
                'consecuencias'    => $efectos,
                'solucion'         => $solucion,
                'plazo'            => $plazo,
                'confirmacion'     => $confirmacion,
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Queja o sugerencia agregada correctamente'
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => 'Error al guardar la información'
            ]);
        }
    }

    public function deleteQuejaSugerencia()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {

            $data = json_decode(file_get_contents('php://input'), true);

            $id = $data['id'] ?? null;

            if (!ModuloService::validaPermiso($this->modulo, 'eliminar')) {

                echo json_encode([
                    'success' => false,
                    'message' => 'No tienes permiso para eliminar'
                ]);

                return;
            }

            if (empty($id)) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Registro inválido'
                ]);

                return;
            }

            $queja = QuejasSugerencia::where('id_estacion', $this->estacionModulo())
                ->find($id);

            if (!$queja) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Registro no encontrado'
                ]);

                return;
            }

            $queja->delete();

            echo json_encode([
                'success' => true,
                'message' => 'Registro eliminado correctamente'
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => 'Error al eliminar el registro'
            ]);
        }
    }

    public function pdfQuejaSugerencia(int $id){

    $idEstacion = $this->estacionModulo();

    $registro = Estacion::find($idEstacion);

    if (!$registro) {
        return "No se encontró la información";
    }

      $queja = QuejasSugerencia::where('id_estacion', $idEstacion)
        ->where('id', $id)
        ->first();

     $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';
   

     $html = '
    <!DOCTYPE html>
    <html>
    <head>
    <meta charset="UTF-8">
    <title>Quejas y sugerencias</title>
    <link rel="stylesheet" href="'.$_ENV['APP_URL'].'/assets/css/pdf.css">
    </head>
    <body>

    <div class="text-center"><img src="'.$logo.'" style="width: 250px;"></div>
    <div class="text-center mt-3">'.$registro->permisocre.'</div>
    <div class="text-center mt-1">'.$registro->razonsocial.'</div>
    <div class="text-center mt-1">'.$registro->direccioncompleta.'</div>


    <h1 class="text-center mt-4">Quejas y sugerencias</h1>

    <h2 class="mt-4">1. Datos para ser llenados por el cliente</h2>

        <div class="border p-2">
            <b>Fecha:</b>
            '.formatearFecha($queja->fecha).'
        </div>

        <div class="border p-2 mt-2">
            <b>Nombre:</b>
            '.$queja->nombre.'
        </div>

        <div class="mt-2">
            <b>Exposición de los motivos y del hecho causante:</b>
        </div>

        <div class="border p-2 mt-2">
            '.$queja->motivos_causas.'
        </div>

        <div class="border p-2 mt-2">
            <b>Nombre de a quien va dirigida la queja:</b>
            '.$queja->nombre_dirigido.'
        </div>

        <div class="border p-2 mt-2">
            <b>Datos de contacto:</b>
            '.$queja->contacto.'
        </div>

     

        <h2 class="mt-4">
            2. Datos a ser llenados por quien atiende la queja
        </h2>

        <div>
            <b>Nombre y puesto de quien atiende la queja:</b>
        </div>

        <div class="border p-2 mt-2">
            '.$queja->nombre_puesto.'
        </div>

        <div class="mt-2">
            <b>Efectos o consecuencias de la queja:</b>
        </div>

        <div class="border p-2 mt-2">
            '.$queja->consecuencias.'
        </div>

        <div class="mt-2">
            <b>Solución propuesta y adoptada:</b>
        </div>

        <div class="border p-2 mt-2">
            '.$queja->solucion.'
        </div>

        <div class="border p-2 mt-2">
            <b>Plazo para llevarla a cabo:</b>
            '.$queja->plazo.'
        </div>

        <div class="border p-2 mt-2">
            <b>Confirmación de la resolución:</b>
            '.$queja->confirmacion.'
        </div>


    </body>
    </html>
    ';

    // ======================
    // PDF
    // ======================
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait'); 
    $dompdf->render();

    return $dompdf->stream(
        "Quejas-sugerencias.pdf",
        ["Attachment" => true]
    );

    }

}
