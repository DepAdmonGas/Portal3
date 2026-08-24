<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Session;
use App\Core\Request;
use App\Core\JsonResponse;
use App\Services\DiaDobleService;
use App\Services\IncidenciasNominaService;
use App\Services\DropdownYearMesService;
use Dompdf\Dompdf;
use Dompdf\Options;

class DiaDobleController extends BaseController
{
    public function index(int $idYear)
    {
        $yearMes = DropdownYearMesService::validarYearMes($idYear, null);
        $idYear = (int) $yearMes['idYear'];
        $permisos = DiaDobleService::getPermisos();

        $currentQuincena = DiaDobleService::getCurrentQuincena();
        $quincenas = DiaDobleService::getQuincenas($idYear);

        $weeks = IncidenciasNominaService::getWeeksForYear($idYear);
        $totalWeeks = count($weeks);

        $sessionSemana = IncidenciasNominaService::getSemanaFromSession();
        if ($sessionSemana >= 1 && $sessionSemana <= $totalWeeks) {
            $currentWeek = $sessionSemana;
        } else {
            $currentWeek = IncidenciasNominaService::getCurrentWeekNumber($idYear);
        }

        $weekTitle = IncidenciasNominaService::getWeekTitle($idYear, $currentWeek);

        $title = 'Día Doble';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Incidencias de Nómina', '/departamento-operativo/recursos-humanos/incidencias-nomina/' . date('Y'));
        Breadcrumb::add(DropdownYearMesService::dropdownYearManual($idYear, 1, 2022), '');

        $yearMesTemplate = '/departamento-operativo/recursos-humanos/dia-doble/{year}';

        View::render('departamento-operativo/2-recursos-humanos/dia-doble/index', [
            'title'            => $title,
            'idYear'           => $idYear,
            'currentQuincena'  => $currentQuincena,
            'quincenas'        => $quincenas,
            'weeks'            => $weeks,
            'currentWeek'      => $currentWeek,
            'weekTitle'        => $weekTitle,
            'puedeCrear'       => $permisos['puedeCrear'],
            'puedeEditar'      => $permisos['puedeEditar'],
            'puedeEliminar'    => $permisos['puedeEliminar'],
            'puedeDescargar'   => $permisos['puedeDescargar'],
            'idUsuario'        => $permisos['id_usuario'],
            'help'             => false,
            'yearMesTemplate'  => $yearMesTemplate,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/libs/signature_pad/docs/js/signature_pad.umd.min.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/dia-doble.datatable.init.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/dia-doble.actions.init.js?v=' . time(),
            ],
            'links' => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
        ], 'departamento-operativo');
    }

    public function getData(int $idYear)
    {
        $permisos = DiaDobleService::getPermisos();
        $data = DiaDobleService::getList($idYear);
        JsonResponse::success('OK', ['data' => $data, 'permisos' => $permisos]);
    }

    public function getDataEstaciones(int $idYear)
    {
        $semana = (int)Request::input('semana', 1);

        $totalWeeks = count(IncidenciasNominaService::getWeeksForYear($idYear));
        if ($semana < 1) $semana = 1;
        if ($semana > $totalWeeks) $semana = $totalWeeks;

        $weekRange = IncidenciasNominaService::getWeekDateRange($idYear, $semana);
        $weekTitle = IncidenciasNominaService::getWeekTitle($idYear, $semana);

        $estaciones = DiaDobleService::getEstacionesData($idYear, $semana);

        JsonResponse::success('OK', [
            'estaciones' => $estaciones,
            'weekTitle'  => $weekTitle,
            'weekRange'  => $weekRange,
        ]);
    }

    public function guardarContexto()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $semana = isset($input['semana']) ? (int)$input['semana'] : 0;
        $idYear = isset($input['id_year']) ? (int)$input['id_year'] : (int)date('Y');

        $totalWeeks = count(IncidenciasNominaService::getWeeksForYear($idYear));
        if ($semana >= 1 && $semana <= $totalWeeks) {
            IncidenciasNominaService::setSemanaSession($semana);
            session_write_close();
        }

        return JsonResponse::success('OK');
    }

    public function pdfEstaciones(int $idYear)
    {
        $semana = (int)Request::input('semana', 1);

        $totalWeeks = count(IncidenciasNominaService::getWeeksForYear($idYear));
        if ($semana < 1) $semana = 1;
        if ($semana > $totalWeeks) $semana = $totalWeeks;

        $weekTitle = IncidenciasNominaService::getWeekTitle($idYear, $semana);

        $html = '<html><head><style>' . DiaDobleService::getPdfStyles() . '</style></head><body>';

        $html .= '<div style="text-align:center;margin-bottom:10px;">';
        $html .= '<h2 style="margin:0;font-size:14px;">Días Dobles - Estaciones</h2>';
        $html .= '<p style="margin:2px 0;font-size:11px;">' . htmlspecialchars($weekTitle) . '</p>';
        $html .= '</div>';

        $estaciones = DiaDobleService::getEstacionesData($idYear, $semana);

        $first = true;
        foreach ($estaciones as $est) {
            if (!$first) {
                $html .= '<div style="page-break-before: always;"></div>';
            }
            $html .= DiaDobleService::buildPdfStationTable($est);
            $first = false;
        }

        $html .= '</body></html>';

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $dompdf->stream('Dobles - Estaciones - Semana ' . $semana . '.pdf', ['Attachment' => 1]);
        exit;
    }

    public function add(int $idYear)
    {
        $permisos = DiaDobleService::getPermisos();
        if (!$permisos['puedeCrear']) {
            JsonResponse::error('No tienes permisos para crear.');
        }

        $pendiente = DiaDobleService::getPendienteRecord($idYear);
        if ($pendiente) {
            JsonResponse::error(
                'Existe un registro que no ha sido finalizado.',
                400,
                ['pendiente_id' => $pendiente['id']]
            );
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $quincena = (int)($data['quincena'] ?? 0);

        if (!$quincena || $quincena < 1 || $quincena > 24) {
            JsonResponse::error('Selecciona una quincena válida.');
        }

        try {
            $id = DiaDobleService::agregar($idYear, $quincena);
            if (!$id) {
                JsonResponse::error('Error al crear el reporte.');
            }

            DiaDobleService::notificarCreacion($id, $permisos['id_usuario']);

            JsonResponse::success('Reporte creado correctamente.', ['id' => $id]);
        } catch (\Throwable $e) {
            JsonResponse::error('Error al crear: ' . $e->getMessage());
        }
    }

    public function editQuincena()
    {
        $permisos = DiaDobleService::getPermisos();
        if (!$permisos['puedeEditar']) {
            JsonResponse::error('No tienes permisos para editar.');
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $idReporte = (int)($data['id_reporte'] ?? 0);
        $quincena = (int)($data['quincena'] ?? 0);

        if (!$idReporte || !$quincena) {
            JsonResponse::error('Datos incompletos.');
        }

        try {
            $ok = DiaDobleService::editarQuincena($idReporte, $quincena);
            if (!$ok) {
                JsonResponse::error('Error al editar la quincena.');
            }

            DiaDobleService::notificarEdicion($idReporte, $permisos['id_usuario']);

            JsonResponse::success('Quincena actualizada correctamente.');
        } catch (\Throwable $e) {
            JsonResponse::error('Error al editar: ' . $e->getMessage());
        }
    }

    public function delete()
    {
        $permisos = DiaDobleService::getPermisos();
        if (!$permisos['puedeEliminar']) {
            JsonResponse::error('No tienes permisos para eliminar.');
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);

        if (!$id) {
            JsonResponse::error('ID no proporcionado.');
        }

        try {
            $ok = DiaDobleService::eliminar($id);
            if (!$ok) {
                JsonResponse::error('Error al eliminar el registro.');
            }

            DiaDobleService::notificarEliminacion($id, $permisos['id_usuario']);

            JsonResponse::success('Registro eliminado correctamente.');
        } catch (\Throwable $e) {
            JsonResponse::error('Error al eliminar: ' . $e->getMessage());
        }
    }

    public function getDetail()
    {
        $idReporte = (int)Request::input('id', 0);
        if (!$idReporte) {
            JsonResponse::error('ID no proporcionado.');
        }

        $detail = DiaDobleService::getDetail($idReporte);
        if (!$detail) {
            JsonResponse::error('Registro no encontrado.');
        }

        JsonResponse::success('OK', $detail);
    }

    public function addPersonal()
    {
        $permisos = DiaDobleService::getPermisos();
        if (!$permisos['puedeCrear']) {
            JsonResponse::error('No tienes permisos para agregar personal.');
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $idReporte = (int)($data['id_reporte'] ?? 0);
        $idUsuario = (int)($data['id_usuario'] ?? 0);
        $fechaDoble = trim((string)($data['fecha_doble'] ?? ''));

        if (!$idReporte || !$idUsuario || empty($fechaDoble)) {
            JsonResponse::error('Datos incompletos.');
        }

        try {
            $ok = DiaDobleService::agregarPersonal($idReporte, $idUsuario, $fechaDoble);
            if (!$ok) {
                JsonResponse::error('Error al agregar personal.');
            }

            DiaDobleService::notificarEdicion($idReporte, $permisos['id_usuario']);

            JsonResponse::success('Personal agregado correctamente.');
        } catch (\Throwable $e) {
            JsonResponse::error('Error al agregar personal: ' . $e->getMessage());
        }
    }

    public function deletePersonal()
    {
        $permisos = DiaDobleService::getPermisos();
        if (!$permisos['puedeEliminar']) {
            JsonResponse::error('No tienes permisos para eliminar personal.');
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);

        if (!$id) {
            JsonResponse::error('ID no proporcionado.');
        }

        try {
            $ok = DiaDobleService::eliminarPersonal($id);
            if (!$ok) {
                JsonResponse::error('Error al eliminar el registro.');
            }

            JsonResponse::success('Personal eliminado correctamente.');
        } catch (\Throwable $e) {
            JsonResponse::error('Error al eliminar: ' . $e->getMessage());
        }
    }

    public function getPersonal()
    {
        $year = (int)date('Y');
        $personal = DiaDobleService::getPersonal($year);
        JsonResponse::success('OK', ['personal' => $personal]);
    }

    public function getComentarios()
    {
        $idReporte = (int)Request::input('id', 0);
        if (!$idReporte) {
            JsonResponse::error('ID no proporcionado.');
        }

        $comentarios = DiaDobleService::getComentarios($idReporte);
        JsonResponse::success('OK', ['comentarios' => $comentarios]);
    }

    public function addComentario()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $idReporte = (int)($data['id_reporte'] ?? 0);
        $comentario = trim((string)($data['comentario'] ?? ''));

        if (!$idReporte || empty($comentario)) {
            JsonResponse::error('Datos incompletos.');
        }

        try {
            $ok = DiaDobleService::agregarComentario($idReporte, $comentario);
            if (!$ok) {
                JsonResponse::error('Error al guardar el comentario.');
            }

            JsonResponse::success('Comentario guardado correctamente.');
        } catch (\Throwable $e) {
            JsonResponse::error('Error al guardar comentario: ' . $e->getMessage());
        }
    }

    public function pdfDireccion(int $idYear)
    {
        $idReporte = (int)Request::input('id', 0);
        if (!$idReporte) {
            JsonResponse::error('ID no proporcionado.');
        }

        $html = DiaDobleService::buildPdfDireccionHtml($idReporte);
        if (empty($html)) {
            JsonResponse::error('Reporte no encontrado.');
        }

        $detail = DiaDobleService::getDireccionDetail($idReporte);
        $quincena = $detail['quincena'] ?? $idReporte;

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $dompdf->stream('Formato Dia Doble - Quincena ' . $quincena . '.pdf', ['Attachment' => 1]);
        exit;
    }

    public function getFirmas()
    {
        $idReporte = (int)Request::input('id', 0);
        if (!$idReporte) {
            JsonResponse::error('ID no proporcionado.');
        }

        $firmas = DiaDobleService::getFirmas($idReporte);
        $permisosFirma = DiaDobleService::getPuedeFirmar($idReporte);

        JsonResponse::success('OK', ['firmas' => $firmas, 'permisos' => $permisosFirma]);
    }

    public function firmar()
    {
        header('Content-Type: application/json; charset=utf-8');

        $data = json_decode(file_get_contents('php://input'), true);
        $idReporte = (int)($data['id_reporte'] ?? 0);
        $tipoFirma = trim((string)($data['tipo_firma'] ?? ''));
        $token = (int)($data['token'] ?? 0);

        if (!$idReporte || empty($tipoFirma) || !$token) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
            exit;
        }

        try {
            $result = DiaDobleService::firmarToken($idReporte, $tipoFirma, $token);
        } catch (\Throwable $e) {
            $result = ['success' => false, 'message' => 'Error al firmar: ' . $e->getMessage()];
        }
        echo json_encode($result);
        exit;
    }

    public function firmarFirma()
    {
        header('Content-Type: application/json; charset=utf-8');

        $data = json_decode(file_get_contents('php://input'), true);
        $idReporte = (int)($data['id_reporte'] ?? 0);
        $firmaBase64 = trim((string)($data['firma_base64'] ?? ''));

        if (!$idReporte || empty($firmaBase64)) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
            exit;
        }

        try {
            $result = DiaDobleService::guardarFirmaImagen($idReporte, $firmaBase64);
        } catch (\Throwable $e) {
            $result = ['success' => false, 'message' => 'Error al guardar firma: ' . $e->getMessage()];
        }
        echo json_encode($result);
        exit;
    }

    public function crearToken()
    {
        header('Content-Type: application/json; charset=utf-8');

        $data = json_decode(file_get_contents('php://input'), true);
        $idReporte = (int)($data['id_reporte'] ?? 0);
        $via = trim((string)($data['via'] ?? 'telegram'));

        $sessionUsuario = Session::get('usuario');
        $idUsuario = (int)($sessionUsuario['id'] ?? 0);

        if (!$idReporte || !$idUsuario) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
            exit;
        }

        try {
            $result = DiaDobleService::crearToken($idReporte, $idUsuario, $via);
        } catch (\Throwable $e) {
            $result = ['success' => false, 'message' => 'Error al crear token: ' . $e->getMessage()];
        }
        echo json_encode($result);
        exit;
    }

    public function firmarPage(int $idReporte)
    {
        $idReporte = (int)$idReporte;
        if ($idReporte <= 0) {
            http_response_code(404);
            echo 'ID no válido';
            exit;
        }

        $detail = DiaDobleService::getFormDetail($idReporte);
        if (!$detail) {
            http_response_code(404);
            echo 'Registro no encontrado';
            exit;
        }

        $permisos = DiaDobleService::getPermisos();
        $firmas = DiaDobleService::getFirmas($idReporte);
        $permisosFirma = DiaDobleService::getPuedeFirmar($idReporte);

        $firmaB = 0;
        $firmaC = 0;
        foreach ($firmas as $f) {
            if ($f['tipo_firma'] === 'B') $firmaB++;
            if ($f['tipo_firma'] === 'C') $firmaC++;
        }

        $title = 'Firmar Día Doble (# 00' . $idReporte . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Incidencias de Nómina', '/departamento-operativo/recursos-humanos/incidencias-nomina/' . $detail['year']);
        Breadcrumb::add('Día Doble', '/departamento-operativo/recursos-humanos/dia-doble/' . $detail['year']);
        Breadcrumb::add($title, '');

        View::render('departamento-operativo/2-recursos-humanos/dia-doble/firmar', [
            'title'           => $title,
            'detail'          => $detail,
            'firmas'          => $firmas,
            'firmaB'          => $firmaB,
            'firmaC'          => $firmaC,
            'permisosFirma'   => $permisosFirma,
            'esFirmanteVoBo'  => $permisosFirma['esFirmanteVoBo'],
            'esFirmanteAuth'  => $permisosFirma['esFirmanteAuth'],
            'puedeCrear'      => $permisos['puedeCrear'],
            'puedeEditar'     => $permisos['puedeEditar'],
            'puedeEliminar'   => $permisos['puedeEliminar'],
            'puedeDescargar'  => $permisos['puedeDescargar'],
            'idUsuario'       => $permisos['id_usuario'],
            'help'            => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/dia-doble.firmar.js?v=' . time(),
            ],
        ], 'departamento-operativo');
    }

    public function form(int $idReporte)
    {
        $idReporte = (int)$idReporte;
        if ($idReporte <= 0) {
            http_response_code(404);
            echo 'ID no válido';
            exit;
        }

        $detail = DiaDobleService::getFormDetail($idReporte);
        if (!$detail) {
            http_response_code(404);
            echo 'Registro no encontrado';
            exit;
        }

        $permisos = DiaDobleService::getPermisos();
        $quincenas = DiaDobleService::getQuincenas($detail['year']);

        $title = 'Formulario Día Doble';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Incidencias de Nómina', '/departamento-operativo/recursos-humanos/incidencias-nomina/' . date('Y'));
        Breadcrumb::add('Día Doble', '/departamento-operativo/recursos-humanos/dia-doble/' . $detail['year']);
        Breadcrumb::add($title, '');

        View::render('departamento-operativo/2-recursos-humanos/dia-doble/form', [
            'title'            => $title,
            'detail'           => $detail,
            'quincenas'        => $quincenas,
            'puedeCrear'       => $permisos['puedeCrear'],
            'puedeEditar'      => $permisos['puedeEditar'],
            'puedeEliminar'    => $permisos['puedeEliminar'],
            'puedeDescargar'   => $permisos['puedeDescargar'],
            'idUsuario'        => $permisos['id_usuario'],
            'help'             => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/signature_pad/docs/js/signature_pad.umd.min.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/dia-doble.form.init.js?v=' . time(),
            ],
        ], 'departamento-operativo');
    }

    public function getPersonalDireccion()
    {
        $personal = DiaDobleService::getPersonalDireccion();
        JsonResponse::success('OK', ['personal' => $personal]);
    }
}
