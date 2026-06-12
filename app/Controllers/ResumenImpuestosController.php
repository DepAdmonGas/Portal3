<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\DropdownYearMesService;
use App\Services\ModuloDptoOperativoService;
use App\Services\ResumenImpuestosService;
use App\Services\ImpuestoService;

class ResumenImpuestosController extends BaseController
{
    protected string $modulo = 'corporativo';

    public function index($idYear, $idMes)
    {
        $puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer');
        if (!$puedeLeer) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        $idEstacion = $this->estacionId();
        $multiEstacion = $this->isMultiEs();

        if (!$idEstacion || ($multiEstacion && $idEstacion === 8)) {
            View::render('departamento-operativo/1-corporativo/resumen-impuestos/index', [
                'title' => 'Resumen Impuestos (' . nombremes($idMes) . ' ' . $idYear . ')',
                'idEstacion' => 0,
                'idYear' => $idYear,
                'idMes' => $idMes,
                'multiestacion' => $multiEstacion,
                'help' => false,
                'scripts' => [],
            ], 'departamento-operativo');
            return;
        }

        $validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
        $idYear = $validados['idYear'];
        $idMes = $validados['idMes'];

        $title = 'Resumen Impuestos (' . nombremes($idMes) . ' ' . $idYear . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
        Breadcrumb::add('Corte Diario ' . nombremes($idMes) . ' ' . $idYear, '/departamento-operativo/corporativo/corte-diario/' . $idYear . '/' . $idMes);
        Breadcrumb::add('<span class="breadcrumb-item active">' . $title . '</span>', '');

        View::render('departamento-operativo/1-corporativo/resumen-impuestos/index', [
            'title' => $title,
            'idEstacion' => $idEstacion,
            'idYear' => $idYear,
            'idMes' => $idMes,
            'multiestacion' => false,
            'help' => false,
            'links' => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/departamento-operativo/1-corporativo/actions.resumen-impuestos.init.js?v=' . time(),
                '/assets/js/departamento-operativo/1-corporativo/resumen-impuestos-dias.datatable.init.js?v=' . time(),
            ],
        ], 'departamento-operativo');
    }

    public function getDias($idYear, $idMes)
    {
        header('Content-Type: application/json');

        $puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer');
        if (!$puedeLeer) {
            echo json_encode(['success' => false, 'message' => 'Sin permisos']);
            exit;
        }

        $idEstacion = $this->estacionId();
        if (!$idEstacion || ($this->isMultiEs() && $idEstacion === 8)) {
            echo json_encode(['success' => false, 'message' => 'Selecciona una estación']);
            exit;
        }

        $validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
        $idYear = $validados['idYear'];
        $idMes = $validados['idMes'];

        $dias = ResumenImpuestosService::getDias($idEstacion, $idYear, $idMes);

        echo json_encode(['success' => true, 'dias' => $dias]);
        exit;
    }

    public function getDetalleDia($idDia)
    {
        header('Content-Type: application/json');

        $puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer');
        if (!$puedeLeer) {
            echo json_encode(['success' => false, 'message' => 'Sin permisos']);
            exit;
        }

        $data = ImpuestoService::getData((int) $idDia);

        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    public function getTotales($idYear, $idMes)
    {
        header('Content-Type: application/json');

        $puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer');
        if (!$puedeLeer) {
            echo json_encode(['success' => false, 'message' => 'Sin permisos']);
            exit;
        }

        $idEstacion = $this->estacionId();
        if (!$idEstacion || ($this->isMultiEs() && $idEstacion === 8)) {
            echo json_encode(['success' => false, 'message' => 'Selecciona una estación']);
            exit;
        }

        $validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
        $idYear = $validados['idYear'];
        $idMes = $validados['idMes'];

        $totales = ResumenImpuestosService::getTotales($idEstacion, $idYear, $idMes);

        echo json_encode(['success' => true, 'totales' => $totales]);
        exit;
    }
}
