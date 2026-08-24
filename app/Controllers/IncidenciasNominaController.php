<?php
namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Session;
use App\Core\Request;
use App\Core\JsonResponse;
use App\Services\IncidenciasNominaService;
use App\Services\DropdownYearMesService;
use App\Services\ModuleStationService;
use App\Services\ModuloDptoOperativoService;
use App\Services\TelegramService;
use Dompdf\Dompdf;
use Dompdf\Options;

class IncidenciasNominaController extends BaseController
{
    public function index(int $idYear)
    {
        $permisos = IncidenciasNominaService::getPermisos();

        $yearResult = DropdownYearMesService::validarYearMes($idYear, null);
        $idYear = $yearResult['idYear'];

        $ctx = ModuleStationService::getContext(IncidenciasNominaService::MODULE_KEY);
        $estacionId = $ctx['id_estacion'];

        $title = 'Incidencias de Nómina';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add($title, '');
        Breadcrumb::add(DropdownYearMesService::dropdownYearManual($idYear, 1), '');

        $weeks = IncidenciasNominaService::getWeeksForYear($idYear);
        $totalWeeks = count($weeks);

        $sessionSemana = IncidenciasNominaService::getSemanaFromSession();
        if ($sessionSemana >= 1 && $sessionSemana <= $totalWeeks) {
            $currentWeek = $sessionSemana;
        } else {
            $currentWeek = IncidenciasNominaService::getCurrentWeekNumber($idYear);
        }

        $nombreEstacion = $estacionId ? IncidenciasNominaService::getNombreEstacion($estacionId) : '';
        $yearMesTemplate = '/departamento-operativo/recursos-humanos/incidencias-nomina/{year}';
        $weekTitle = IncidenciasNominaService::getWeekTitle($idYear, $currentWeek);

        View::render('departamento-operativo/2-recursos-humanos/incidencias-nomina/index', [
            'title'             => $title,
            'idYear'            => $idYear,
            'weeks'             => $weeks,
            'currentWeek'       => $currentWeek,
            'weekTitle'         => $weekTitle,
            'estacionId'        => $estacionId,
            'nombreEstacion'    => $nombreEstacion,
            'multiestacion'     => $permisos['multiestacion'],
            'puedeCrear'        => $permisos['puedeCrear'],
            'puedeEditar'       => $permisos['puedeEditar'],
            'puedeEliminar'     => $permisos['puedeEliminar'],
            'puedeDescargar'    => $permisos['puedeDescargar'],
            'idUsuario'         => $permisos['id_usuario'],
            'yearMesTemplate'   => $yearMesTemplate,
            'moduleStationKey'  => IncidenciasNominaService::MODULE_KEY,
            'help'              => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/incidencias-nomina.datatable.init.js?v=' . time(),
                '/assets/js/departamento-operativo/2-recursos-humanos/incidencias-nomina.actions.init.js?v=' . time(),
            ],
            'links' => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
        ], 'departamento-operativo');
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

    public function data(int $idYear)
    {
        $idEstacion = (int)Request::input('id_estacion', 0);
        $semana = (int)Request::input('semana', 1);
        $tipo = Request::input('tipo', 'estacion');

        $idYearResult = DropdownYearMesService::validarYearMes($idYear, null);
        $idYear = $idYearResult['idYear'];

        $totalWeeks = count(IncidenciasNominaService::getWeeksForYear($idYear));
        if ($semana < 1) $semana = 1;
        if ($semana > $totalWeeks) $semana = $totalWeeks;

        if ($idEstacion <= 0 && $tipo === 'estacion') {
            return JsonResponse::success('Sin datos', ['data' => [], 'dias' => [], 'weekRange' => []]);
        }

        $weekRange = IncidenciasNominaService::getWeekDateRange($idYear, $semana);
        $weekTitle = IncidenciasNominaService::getWeekTitle($idYear, $semana);
        $diasEntre = IncidenciasNominaService::getDiasEntre($weekRange['inicio'], $weekRange['fin']);
        
        $diasInfo = [];
        foreach ($diasEntre as $d) {
            $diasInfo[] = [
                'fecha'  => formatearFecha($d),
                'nombre' => IncidenciasNominaService::getNombreDiaCorto($d),
                'label'  => IncidenciasNominaService::getDayHeaderLabel($d),
            ];
        }

        if ($tipo === 'todas') {
            $available = ModuleStationService::getAvailableStations(IncidenciasNominaService::MODULE_KEY);
            $stationIds = array_column($available, 'id');
            $rows = IncidenciasNominaService::getAllStationsData($idYear, $semana, $stationIds ?: null);
        } else {
            $rows = IncidenciasNominaService::getAsistenciaData($idEstacion, $idYear, $semana);
        }

        return JsonResponse::success('OK', [
            'data'      => $rows,
            'dias'      => $diasInfo,
            'weekRange' => $weekRange,
            'weekTitle' => $weekTitle,
        ]);
    }

    public function pdfEstaciones(int $idYear)
    {
        $semana = (int)Request::input('semana', 1);

        $idYearResult = DropdownYearMesService::validarYearMes($idYear, null);
        $idYear = $idYearResult['idYear'];

        $totalWeeks = count(IncidenciasNominaService::getWeeksForYear($idYear));
        if ($semana < 1) $semana = 1;
        if ($semana > $totalWeeks) $semana = $totalWeeks;

        $stationIds = IncidenciasNominaService::getStationIdsForReport();

        $html = '<html><head><style>' . IncidenciasNominaService::getPdfStyles() . '</style></head><body>';

        $first = true;
        foreach ($stationIds as $idEstacion) {
            if (!$first) {
                $html .= '<div style="page-break-before: always;">';
            }
            $html .= IncidenciasNominaService::buildStationTableHtml($idEstacion, $idYear, $semana);
            if (!$first) {
                $html .= '</div>';
            }
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

        $dompdf->stream('Reporte de incidencias - Semana ' . $semana . '.pdf', ['Attachment' => 0]);
        exit;
    }

    public function pdfIndividual(int $idYear)
    {
        $idEstacion = (int)Request::input('id_estacion', 0);
        $semana = (int)Request::input('semana', 1);

        $idYearResult = DropdownYearMesService::validarYearMes($idYear, null);
        $idYear = $idYearResult['idYear'];

        $totalWeeks = count(IncidenciasNominaService::getWeeksForYear($idYear));
        if ($semana < 1) $semana = 1;
        if ($semana > $totalWeeks) $semana = $totalWeeks;

        if ($idEstacion <= 0) {
            http_response_code(400);
            echo 'Estación no válida';
            exit;
        }

        $nombreEstacion = IncidenciasNominaService::getNombreEstacion($idEstacion);

        $html = '<html><head><style>' . IncidenciasNominaService::getPdfStyles() . '</style></head><body>';
        $html .= IncidenciasNominaService::buildStationTableHtml($idEstacion, $idYear, $semana);
        $html .= '</body></html>';

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $dompdf->stream('Reporte de incidencias ' . $nombreEstacion . ' - Semana ' . $semana . '.pdf', ['Attachment' => 0]);
        exit;
    }
}
