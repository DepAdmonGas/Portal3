<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Sasisopa\RequisicionObra;
use App\Models\Sasisopa\RequisicionObraCartaResponsiva;
use App\Models\Sasisopa\RequisicionObraFormato12;
use App\Models\Sasisopa\RequisicionObraFormato12Procedimiento;
use App\Models\Sasisopa\RequisicionObraFormato12TrabajadorEncargado;
use App\Models\Sasisopa\RequisicionObraFormato14;
use App\Models\Sasisopa\RequisicionObraFormato15;
use App\Services\SeguridadContratistasService;
use App\Services\ModuleStationService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Capsule\Manager as Capsule;
use Carbon\Carbon;

class SeguridadContratistasController extends BaseController
{
    protected string $modulo = 'sasisopa';

    private function estacionModulo(): ?int
    {
        return ModuleStationService::getContext('sasisopa')['id_estacion'] ?? null;
    }
    public function index()
    {

        $title = '12. SEGURIDAD DE CONTRATISTAS';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'estacionId' => $this->estacionModulo(),
            'moduleStationKey' => 'sasisopa',
            'filtro_usuario' => $this->filtro_usuario,
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/core/module-station-selector.js?v=' . time(),
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/seguridadcontratistas/index.datatable.init.js?v=' . time(),
                '/js/seguridadcontratistas/index.action.init.js?v=' . time(),
            ],
            'help' => true
        ];

        View::render('seguridadcontratistas/index', $data, 'sasisopa');
    }

    public function datatable()
    {
        $data = RequisicionObra::query()

            ->with(['usuario:id,nombre'])
            ->withExists([
                'formato12',
                'formato14',
                'formato15',
                'cartaResponsiva'
            ])
            ->where('id_estacion', $this->estacionModulo())
            ->orderByDesc('id')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'folio' => '0' . $item->no_folio,
                    'fecha' => optional($item->fecha)->format('Y-m-d'),
                    'solicitante' => $item->usuario?->nombre ?? 'S/I',
                    'descripcion' => $item->descripcion,
                    'justificacion' => $item->justificacion,
                    'proveedor' => $item->proveedor,
                    'formato12' => $item->formato12_exists,
                    'formato14' => $item->formato14_exists,
                    'formato14_url' =>
                    $item->formato14?->archivo
                        ? '/uploads/' .
                        $item->formato14->archivo
                        : '',
                    'formato15' => $item->formato15_exists,
                    'carta_responsiva' => $item->carta_responsiva_exists
                ];
            });

        echo json_encode([
            'data' => $data,
            'permisos' => [
                'editar' => ModuloService::validaPermiso($this->modulo, 'editar'),
                'eliminar' => ModuloService::validaPermiso($this->modulo, 'eliminar')
            ]
        ]);
        exit;
    }

    public function create()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);

        if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear'
            ]);
            return;
        }

        try {

            if ($this->estacionModulo() === null) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Selecciona una estación para continuar'
                ]);

                return;
            }

            $fecha = sanitize_input($data['fecha'] ?? null, 'string');
            $descripcion = sanitize_input($data['descripcion'] ?? null, 'string');
            $justificacion = sanitize_input($data['justificacion'] ?? null, 'string');
            $proveedor = sanitize_input($data['proveedor'] ?? null, 'string');

            if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No tienes permiso para crear'
                ]);
                return;
            }

            if (empty($fecha) || empty($descripcion) || empty($justificacion)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Completa todos los campos obligatorios'
                ]);
                return;
            }

            SeguridadContratistasService::createRequisicionObra(
                $this->estacionModulo(),
                $this->userId(),
                $fecha,
                $descripcion,
                $justificacion,
                $proveedor
            );

            echo json_encode([
                'success' => true,
                'message' => 'Requisición creada correctamente'
            ]);
        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => 'Error al guardar'
            ]);
        }

        exit;
    }

    public function update()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);

        try {

            $id = (int) ($data['id'] ?? 0);

            if (!$id) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Registro no valido'
                ]);

                return;
            }

            $registro = RequisicionObra::query()
                ->when(
                    $this->estacionModulo(),
                    fn ($q, $est) => $q->where('id_estacion', $est)
                )
                ->findOrFail($id);
            $fecha = sanitize_input($data['fecha'] ?? null, 'string');
            $descripcion = sanitize_input($data['descripcion'] ?? null, 'string');
            $justificacion = sanitize_input($data['justificacion'] ?? null, 'string');
            $proveedor = sanitize_input($data['proveedor'] ?? null, 'string');

            if (empty($fecha) || empty($descripcion) || empty($justificacion)) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Todos los datos son obligatorios'
                ]);

                return;
            }

            $registro->update([
                'fecha' => $fecha . ' ' . date('H:i:s'),
                'descripcion' => $descripcion,
                'justificacion' => $justificacion,
                'proveedor' => $proveedor ?: ''
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Requisición actualizada correctamente'
            ]);
        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => 'Error al actualizar'
            ]);
        }

        exit;
    }

    public function delete()
    {

        header('Content-Type: application/json');

        try {

            $data = json_decode(file_get_contents('php://input'), true);
            $registro = RequisicionObra::query()
                ->when(
                    $this->estacionModulo(),
                    fn ($q, $est) => $q->where('id_estacion', $est)
                )
                ->find($data['id']);

            if (!$registro) {

                echo json_encode([
                    'success' => false,
                    'message' => 'El Registro no se puede eliminar'
                ]);

                return;
            }

            $registro->delete();

            echo json_encode([
                'success' => true,
                'message' => 'Registro eliminado correctamente'
            ]);
        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => 'Error al eliminar Registro'
            ]);
        }
    }

    public function formato12(int $id)
    {
        header('Content-Type: application/json');

        try {

            $requisicion = RequisicionObra::query()
                ->when(
                    $this->estacionModulo(),
                    fn ($q, $est) => $q->where('id_estacion', $est)
                )
                ->find($id);

            if (!$requisicion) {

                echo json_encode([
                    'success' => false,
                    'message' => 'No se encontró la requisición'
                ]);

                return;
            }

            $formato = RequisicionObraFormato12::query()
                ->with(['procedimientos'])
                ->where('id_requisicion', $id)
                ->first();

            if (!$formato) {

                $estacion = $this->estacionModulo()
                    ? Estacion::find($this->estacionModulo())
                    : null;

                SeguridadContratistasService::validarRequisicionObra(
                    $id,
                    (string) ($estacion?->di_municipio ?? ''),
                    (string) ($estacion?->di_estado ?? '')
                );

                $formato = RequisicionObraFormato12::query()
                    ->with(['procedimientos'])
                    ->where('id_requisicion', $id)
                    ->firstOrFail();
            }

            $trabajadoresCat1 = RequisicionObraFormato12TrabajadorEncargado::where('id_requisicion', $formato->id)
                ->where('categoria', 1)
                ->get()
                ->map(function ($t) {
                    return [
                        'id' => $t->id,
                        'nombre' => $t->nombre,
                        'puesto' => $t->puesto,
                        'no_seguro' => $t->no_seguro,
                    ];
                });

            $trabajadoresCat2 = RequisicionObraFormato12TrabajadorEncargado::query()
                ->where('id_requisicion', $formato->id)
                ->where('categoria', 2)
                ->get()
                ->map(function ($e) {

                    $usuario = Usuario::with('puesto')
                        ->find($e->id_personal);

                    return [
                        'id' => $e->id,
                        'id_personal' => $e->id_personal,
                        'nombre' => $usuario->nombre ?? $e->nombre,
                        'puesto' => $usuario->puesto->tipo_puesto,
                        'seguro_social' => $usuario->seguro_social,
                    ];
                });

            $encargadosList = Usuario::where('id_gas', $this->estacionModulo())
                ->where('id_puesto', 6)
                ->where('estatus', 0)
                ->get()
                ->map(function ($u) {
                    return [
                        'id' => $u->id,
                        'nombre' => $u->nombre
                    ];
                });

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $formato->id,
                    'id_requisicion' => $formato->id_requisicion,
                    'municipio' => $formato->municipio,
                    'estado' => $formato->estado,
                    'dia' => $formato->dia,
                    'mes' => $formato->mes,
                    'year' => $formato->year,
                    'trabajo_realizar' => $formato->trabajo_realizar,
                    'descripcion' => $formato->descripcion,
                    'area' => $formato->area,

                    'fecha_inicio' => $formato->fecha_inicio?->format('Y-m-d'),
                    'fecha_termino' => $formato->fecha_termino?->format('Y-m-d'),
                    'hora_inicio' => $formato->hora_inicio?->format('H:i:s'),
                    'hora_termino' => $formato->hora_termino?->format('H:i:s'),

                    'prestador_servicio' => $formato->prestador_servicio,
                    'cprtp' => $formato->cprtp,
                    'cteppc' => $formato->cteppc,
                    'nombre_empresa' => $formato->nombre_empresa,
                    'nombre_responsable' => $formato->nombre_responsable,

                    'procedimientos' => $formato->procedimientos,


                    'trabajadores' => $trabajadoresCat1,
                    'encargados' => $trabajadoresCat2,
                    'encargadosList' => $encargadosList,
                ]
            ]);
        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => 'Error al extraer la información'
            ]);
        }

        exit;
    }

    public function updateFormato12()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {

            $data = json_decode(file_get_contents('php://input'), true);
            $formato = RequisicionObraFormato12::findOrFail($data['id']);

            $formato->update([
                'municipio' => sanitize_input($data['municipio'] ?? ''),
                'estado' => sanitize_input($data['estado'] ?? ''),
                'dia' => (int)($data['dia'] ?? 0),
                'mes' => sanitize_input($data['mes'] ?? ''),
                'year' => (int)($data['year'] ?? 0),

                'trabajo_realizar' => sanitize_input($data['trabajo_realizar'] ?? ''),
                'descripcion' => sanitize_input($data['descripcion'] ?? ''),
                'area' => sanitize_input($data['area'] ?? ''),

                'fecha_inicio' => $data['fecha_inicio'] ?? '',
                'fecha_termino' => $data['fecha_termino'] ?? '',
                'hora_inicio' => $data['hora_inicio'] ?? '',
                'hora_termino' => $data['hora_termino'] ?? '',

                'cprtp' => (int)($data['cprtp'] ?? 0),
                'cteppc' => (int)($data['cteppc'] ?? 0),

                'nombre_empresa' => sanitize_input($data['nombre_empresa'] ?? ''),
                'nombre_responsable' => sanitize_input($data['nombre_responsable'] ?? '')
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Autorizacion para realizar trabajos peligrosos actualizado'
            ]);
        } catch (\Throwable $e) {

            http_response_code(500);

            echo json_encode([
                'success' => false,
                'message' => 'Error al actualizar'
            ]);
        }

        exit;
    }

    public function updateProcedimiento()
    {
        header('Content-Type: application/json');

        try {

            $data = json_decode(file_get_contents('php://input'), true);
            RequisicionObraFormato12Procedimiento::where('id', $data['id'])
                ->update(['valor' => (int)($data['valor'] ?? 0)]);

            echo json_encode([
                'success' => true,
                'message' => 'Registro actualizado'
            ]);
        } catch (\Throwable $e) {

            http_response_code(500);

            echo json_encode([
                'success' => false,
                'message' => 'Error al actualizar'
            ]);
        }

        exit;
    }

    public function createTrabajador()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear'
            ]);
            return;
        }

        if (empty($data['nombre']) || empty($data['puesto']) || empty($data['no_seguro'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Completa todos los campos obligatorios'
            ]);
            return;
        }

        try {

            $trabajador = RequisicionObraFormato12TrabajadorEncargado::create([
                'id_requisicion' => $data['id_formato'],
                'categoria' => 1,
                'nombre' => sanitize_input($data['nombre'], 'string'),
                'puesto' => sanitize_input($data['puesto'], 'string'),
                'no_seguro' => sanitize_input($data['no_seguro'], 'string')
            ]);

            echo json_encode([
                'success' => true,
                'data' => $trabajador
            ]);
        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => 'Error al guardar'
            ]);
        }

        exit;
    }

    public function createEncargado()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear'
            ]);
            return;
        }

        if (empty($data['id_personal'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Completa todos los campos obligatorios'
            ]);
            return;
        }

        try {

            $encargado = RequisicionObraFormato12TrabajadorEncargado::create([
                'id_requisicion' => $data['id_formato'],
                'id_personal' => $data['id_personal'],
                'nombre' => '',
                'puesto' => '',
                'no_seguro' => '',
                'categoria' => 2
            ]);


            $usuario = Usuario::with('puesto')
                ->find($data['id_personal']);

            $response = [
                'id' => $encargado->id,
                'id_personal' => $encargado->id_personal,
                'nombre' => $usuario->nombre ?? '',
                'puesto' => $usuario->puesto->tipo_puesto ?? '',
                'seguro_social' => $usuario->seguro_social ?? '',
            ];

            echo json_encode([
                'success' => true,
                'data' => $response
            ]);
        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

    public function deleteTrabajador()
    {
        header('Content-Type: application/json');

        try {

            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int) ($data['id'] ?? 0);
            $registro = RequisicionObraFormato12TrabajadorEncargado::find($id);

            if (!$registro) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Registro no encontrado'
                ]);
                return;
            }

            $registro->delete();

            echo json_encode([
                'success' => true,
                'message' => 'Trabajador eliminado correctamente'
            ]);
        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => 'Error al eliminar'
            ]);
        }

        exit;
    }


    public function pdfFormato12(int $id)
    {
        header('Content-Type: application/pdf');

        $formato = RequisicionObraFormato12::where('id_requisicion', $id)->first();

        if (!$formato) {
            return 'No existe el Formato 12 para esta requisición';
        }

        $horainicio = $formato->hora_inicio?->format('H:i:s');

        $horatermino = $formato->hora_inicio?->format('H:i:s');

        $trabajadoresCat1 = RequisicionObraFormato12TrabajadorEncargado::where('id_requisicion', $formato->id)
            ->where('categoria', 1)
            ->get();

        $encargadosCat2 = RequisicionObraFormato12TrabajadorEncargado::where('id_requisicion', $formato->id)
            ->where('categoria', 2)
            ->get()
            ->map(function ($e) {

                $u = Usuario::with('puesto')->find($e->id_personal);

                return [
                    'nombre' => $u->nombre ?? $e->nombre,
                    'puesto' => $u->puesto->tipo_puesto ?? $e->puesto,
                    'segurosocial' => $u->seguro_social ?? $e->no_seguro,
                ];
            });

        $procedimientos = $formato->procedimientos;

        $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';
        $imgX = 'X';

        $estacion = Estacion::find(
            $this->estacionModulo()
        );

        $html = '
    <!DOCTYPE html>
    <html>
    <head>
    <title>Autorizacion para realizar trabajos peligrosos</title>

    <style type="text/css">
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

    body {
    margin: 0;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
    font-size: 1rem;
    font-weight: 400;
    line-height: 1.5;
    color: #212529;
    background-color: #fff;
    font-size: .9em;
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
    .p-2 {
    padding: 0.5rem !important;
    }

    .mb-3,
    .my-3 {
    margin-bottom: 1rem !important;
    }

    .badge {
    display: inline-block;
    padding: 0.25em 0.4em;
    font-size: 75%;
    font-weight: 700;
    line-height: 1;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 0.25rem;
    }

    .bg-primary {
    background-color: #007bff !important;
    }

    .text-right {
    text-align: right !important;
    }

    .mt-2,
    .my-2 {
    margin-top: 0.5rem !important;
    }

    </style>

    </head>
    <body>


    <table class="table table-bordered mt-2">
    <tr>
    <td class="align-middle text-center"><img src="' . $logo . '" style="width:150px;"></td>
    <td class="align-middle text-center" colspan="2"><b>Autorizacion para realizar trabajos peligrosos</b></td>
    <td class="align-middle text-center"><b>Fo.ADMONGAS.0012</b></td>
    </tr>

    <tr>
    <td class="align-middle text-center">Realizado por:<br>Nelly Estrada Garcia</td>
    <td class="align-middle text-center">Revisado por:<br>Eduardo Galicia Flores</td>
    <td class="align-middle text-center">Autorizado por:<br>' . $estacion?->apoderado_legal . '</td>
    <td class="align-middle text-center">Fecha de autorizacion 01/10/2018</td>
    </tr>
    </table>

    <div class="text-right">
    ' . $formato->municipio . ', ' . $formato->estado . ' a ' . $formato->dia . ' de ' . $formato->mes . ' de ' . $formato->year . '
    </div>

    <div class="mt-2"><b>A quien corresponda</b></div>

    <div class="mt-2">
    <b>Trabajo a realizar:</b>
    <div class="mt-2 border p-2">' . $formato->trabajo_realizar . '</div>
    </div>

    <div class="mt-2">
    <b>Descripcion:</b>
    <div class="mt-2 border p-2">' . $formato->descripcion . '</div>
    </div>

    <div class="mt-2">
    <b>Área:</b>
    <div class="mt-2 border p-2">' . $formato->area . '</div>
    </div>

    <table class="mt-3" style="width: 100%;">
    <tr>
    <td><b>Fecha de inicio:</b></td><td>' . formatearFecha($formato->fecha_inicio) . '</td>
    <td><b>Fecha de término:</b></td><td>' . formatearFecha($formato->fecha_termino) . '</td>
    </tr>
    <tr>
    <td><b>Hora de Inicio:</b></td><td>' . date('g:i a', strtotime($horainicio)) . '</td>
    <td><b>Hora de Termino:</b></td><td>' . date("g:i a", strtotime($horatermino)) . '</td>
    </tr>
    </table>

    <div class="mt-2">
    <b>El trabajo a realizar contempla alguno de los siguientes procedimientos:</b>
    </div>

    <table class="table table-bordered table-sm mt-2">
    <tbody>';

        foreach ($procedimientos as $p) {
            $html .= '
        <tr>
            <td>' . $p->detalle . '</td>
            <td class="text-center">' . ($p->valor == 1 ? $imgX : '') . '</td>
        </tr>';
        }

        $html .= '
    </tbody>
    </table>

    <div class="mt-2">
    <b>Nombre del prestador de servicios:</b>
    <div class="mt-2 border p-2">' . $formato->prestador_servicio . '</div>
    </div>

    <table class="mt-3" style="width: 100%;">
    <tr>
    <td>Cuenta con capacitación para realizar trabajos peligrosos:</td>
    <td>' . ($formato->cprtp == 1 ? 'SI' : 'NO') . '</td>
    </tr>
    <tr>
    <td>Cuenta con EPP</td>
    <td>' . ($formato->cteppc == 1 ? 'SI' : 'NO') . '</td>
    </tr>
    </table>

    <div class="text-center"><small>*De no contar con capacitación, bajo ninguna circunstancia realizara los trabajos</small></div>
    ';

        if ($trabajadoresCat1->count()) {

            $html .= '
    <div><b>Datos de los trabajadores que acuden al servicio:</b></div>

    <table class="table table-bordered table-sm mt-2">
    <thead>
    <tr>
    <th>Nombre</th>
    <th>Puesto</th>
    <th>No. De Seguro</th>
    </tr>
    </thead>
    <tbody>';

            foreach ($trabajadoresCat1 as $t) {
                $html .= '
        <tr>
            <td>' . $t->nombre . '</td>
            <td>' . $t->puesto . '</td>
            <td>' . $t->no_seguro . '</td>
        </tr>';
            }

            $html .= '</tbody></table>';
        }

        if ($encargadosCat2->count()) {

            $html .= '
    <div><b>Encargado de la estación de servicio de darle seguimiento al servicio:</b></div>

    <table class="table table-bordered table-sm mt-2">
    <thead>
    <tr>
    <th>Nombre</th>
    <th>Puesto</th>
    <th>No. De Seguro</th>
    </tr>
    </thead>
    <tbody>';

            foreach ($encargadosCat2 as $e) {
                $html .= '
        <tr>
            <td>' . $e['nombre'] . '</td>
            <td>' . $e['puesto'] . '</td>
            <td>' . $e['segurosocial'] . '</td>
        </tr>';
            }

            $html .= '</tbody></table>';
        }

        if ($formato->nombre_empresa) {
            $html .= '
        <div class="mt-2">
        <b>Nombre empresa:</b>
        <div class="mt-2 border p-2">' . $formato->nombre_empresa . '</div>
        </div>';
        }

        if ($formato->nombre_responsable) {
            $html .= '
        <div class="mt-2">
        <b>Nombre del responsable:</b>
        <div class="mt-2 border p-2">' . $formato->nombre_responsable . '</div>
        </div>

        <div class="text-center mt-2">
        <small>Nota: Si el personal es externo deberá presentar su procedimiento para realizar la actividad</small>
        </div>';
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
        $dompdf->stream("Autorizacion_trabajos_peligrosos.pdf", ["Attachment" => true]);
    }

    //----------------------------------------
    //------------ Formato 13
    public function formato13(int $id)
    {
        header('Content-Type: application/pdf');

        $estacion = Estacion::find($this->estacionModulo());

        $apoderado = htmlspecialchars($estacion?->apoderado_legal ?? '');

        $requisicion = RequisicionObra::with('usuario')
            ->when(
                $this->estacionModulo(),
                fn ($q, $est) => $q->where('id_estacion', $est)
            )
            ->findOrFail($id);

        $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

        $css = file_get_contents(
            'assets/css/pdf.css'
        );

        $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Requisición de obra o servicio</title>
        <link rel="stylesheet" href="' . $_ENV['APP_URL'] . '/assets/css/pdf.css">
        <style>
        ' . $css . '
        </style>
    </head>
    <body>

    <table class="table table-bordered">
        <tbody>
            <tr>

                <td class="align-middle text-center">
                    <img src="' . $logo . '" style="width:150px;">
                </td>

                <td colspan="2" class="align-middle text-center">
                    <b>Requisición de obra o servicio</b>
                </td>

                <td class="align-middle text-center">
                    <b>Fo.ADMONGAS.013</b>
                </td>

            </tr>

            <tr>

                <td class="align-middle text-center">
                    Realizado por:<br>
                    Nelly Estrada Garcia
                </td>

                <td class="align-middle text-center">
                    Revisado por:<br>
                    Eduardo Galicia Flores
                </td>

                <td class="align-middle text-center">
                    Autorizado por:<br>
                    ' . $apoderado . '
                </td>

                <td class="align-middle text-center">
                    Fecha de autorización:<br>
                    01/10/2018
                </td>

            </tr>
        </tbody>
    </table>

    <table class="table table-bordered">
        <tbody>

            <tr>
                <td class="align-middle text-right">
                    <b>No. De Folio:</b>
                    ' . str_pad($requisicion->no_folio, 2, '0', STR_PAD_LEFT) . '
                </td>
            </tr>

            <tr>
                <td class="align-middle text-right">
                    <b>Fecha:</b>
                    ' . formatearFecha($requisicion->fecha?->format('Y-m-d')) . '
                </td>
            </tr>

            <tr>
                <td class="align-middle">
                    <b>Nombre del solicitante:</b>
                    ' . ($requisicion->usuario?->nombre ?? 'S/I') . '
                </td>
            </tr>

            <tr>
                <td class="align-middle">
                    <b>Empresa solicitante:</b>
                    ' . ($estacion?->razonsocial ?? '') . '
                </td>
            </tr>

        </tbody>
    </table>

    <table class="table table-bordered">
        <tbody>

            <tr>
                <th class="align-middle text-center">
                    Descripción detallada del servicio que requiere
                </th>
            </tr>

            <tr>
                <td class="align-middle text-center">
                    ' . nl2br(htmlspecialchars($requisicion->descripcion ?? '')) . '
                </td>
            </tr>

        </tbody>
    </table>

    <table class="table table-bordered">
        <tbody>

            <tr>
                <th class="align-middle text-center">
                    Justificación del servicio solicitado
                </th>
            </tr>

            <tr>
                <td class="align-middle text-center">
                    ' . nl2br(htmlspecialchars($requisicion->justificacion ?? '')) . '
                </td>
            </tr>

        </tbody>
    </table>

    <table class="table table-bordered">
        <tbody>

            <tr>
                <th class="align-middle text-center">
                    Proveedor
                </th>
            </tr>

            <tr>
                <td class="align-middle text-center">
                    ' . htmlspecialchars($requisicion->proveedor ?? '') . '
                </td>
            </tr>

        </tbody>
    </table>

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
            'Requisicion-Obra-Servicio.pdf',
            ['Attachment' => true]
        );
    }

    //----------------------------------------
    //------------ Formato 14

    public function formato14(int $id)
    {
        header('Content-Type: application/json');
        try {

            $requisicion = RequisicionObra::with(
                'usuario:id,nombre'
            )
            ->when(
                $this->estacionModulo(),
                fn ($q, $est) => $q->where('id_estacion', $est)
            )
            ->findOrFail($id);

            $formato = RequisicionObraFormato14::where(
                'id_requisicion',
                $id
            )->first();

            $estacion = Estacion::find($this->estacionModulo());

            echo json_encode([
                'success' => true,
                'formato' => [
                    'id_requisicion' => $id,
                    'folio' => '0' . $requisicion->no_folio,
                    'fecha' => formatearFecha($requisicion->fecha),
                    'nombre_solicitante' => $requisicion->usuario?->nombre ?? '',
                    'empresa' => $estacion?->razonsocial ?? '',
                    'descripcion' => $requisicion->descripcion ?? '',
                    'justificacion' => $requisicion->justificacion ?? '',
                    'archivo' => $formato?->archivo ?? '',
                    'archivo_url' =>
                    !empty($formato?->archivo)
                        ? $_ENV['APP_URL']
                        . '/uploads/'
                        . $formato->archivo
                        : ''
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

    public function updateFormato14()
    {
        header('Content-Type: application/json');

        try {

            $idRequisicion = (int) ($_POST['id_requisicion'] ?? 0);

            if (!$idRequisicion) {

                echo json_encode([
                    'success' => false,
                    'message' => 'No se encontro registro'
                ]);
            }

            if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Debe seleccionar un archivo PDF'
                ]);
            }

            $file = $_FILES['archivo'];

            $extension = strtolower(
                pathinfo(
                    $file['name'],
                    PATHINFO_EXTENSION
                )
            );

            if ($extension !== 'pdf') {

                echo json_encode([
                    'success' => false,
                    'message' => 'Solo se permiten archivos PDF'
                ]);
            }

            // Carpeta física
            $carpetaFisica =
                __DIR__
                . '../../../public/uploads/archivos/seguridad-contratistas/';

            if (!file_exists($carpetaFisica)) {

                mkdir_safe(
                    $carpetaFisica,
                    true
                );
            }

            $nombreArchivo = 'Fo.ADMONGAS.014-' . time() . '.pdf';

            $rutaFisica = $carpetaFisica . $nombreArchivo;

            if (!move_uploaded_file($file['tmp_name'], $rutaFisica)) {

                echo json_encode([
                    'success' => false,
                    'message' => 'No fue posible guardar el archivo'
                ]);
            }

            // Ruta que se guarda en BD
            $rutaBd =
                'archivos/seguridad-contratistas/'
                . $nombreArchivo;

            RequisicionObraFormato14::where(
                'id_requisicion',
                $idRequisicion
            )->delete();

            RequisicionObraFormato14::create([

                'id_requisicion' =>
                $idRequisicion,

                'archivo' =>
                $rutaBd
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Archivo guardado correctamente'
            ]);
        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

    //----------------------------------------
    //------------ Formato 15

    public function formato15(int $id)
    {
        header('Content-Type: application/json');

        try {

            $formato = RequisicionObraFormato15::with(
                'supervisor:id,nombre,firma'
            )
                ->where('id_requisicion', $id)
                ->first();

            if (!$formato) {

                $formato = [
                    'id' => null,
                    'id_requisicion' => $id,
                    'fecha_lv' => null,
                    'fecha_uv' => null,
                    'fecha_rr' => null,
                    'id_supervisor' => null,
                    'supervisor' => null,
                ];
            }

            $supervisores = Usuario::query()
                ->select('id', 'nombre')
                ->where('id_gas', $this->estacionModulo())
                ->where('id_puesto', 6)
                ->where('estatus', 0)
                ->orderBy('nombre')
                ->get();

            echo json_encode([
                'success' => true,
                'formato' => $formato,
                'supervisores' => $supervisores
            ]);
        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => 'Error al mostrar'
            ]);
        }

        exit;
    }

    public function updateFormato15()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        try {

            $formato = RequisicionObraFormato15::updateOrCreate(

                [
                    'id_requisicion' => (int) $data['id_requisicion']
                ],

                [
                    'fecha_lv' => $data['fecha_lv'],
                    'hora_lv' => $data['hora_lv'],
                    'pregunta1' => (int) $data['pregunta1'],
                    'pregunta2' => (int) $data['pregunta2'],
                    'pregunta3' => (int) $data['pregunta3'],
                    'pregunta4' => (int) $data['pregunta4'],
                    'pregunta5' => (int) $data['pregunta5'],
                    'id_usuario' => (int) $data['id_usuario']
                ]
            );

            echo json_encode([
                'success' => true,
                'id' => $formato->id
            ]);
        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

    public function pdfFormato15(int $id)
    {
        $formato = RequisicionObraFormato15::with(
            'supervisor:id,nombre,firma'
        )
            ->where('id_requisicion', $id)
            ->first();

        if (!$formato) {
            return 'No existe el Formato 15 para esta requisición';
        }

        $estacion = Estacion::find(
            $this->estacionModulo()
        );

        $horaFormateada =
            !empty($formato->hora_lv)
            ? Carbon::parse(
                $formato->hora_lv
            )->format('g:i a')
            : '';

        $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

        $firma = '';

        if ($formato->supervisor && !empty($formato->supervisor->firma)) {

            $rutaFirma = realpath(
                __DIR__ .
                    '/../../public/uploads/firma-personal/' .
                    $formato->supervisor->firma
            );

            if (
                $rutaFirma &&
                file_exists($rutaFirma)
            ) {

                $firma = $_ENV['APP_URL'] .
                    '/uploads/firma-personal/' .
                    $formato->supervisor->firma;
            }
        }

        $siNo = fn(
            $valor,
            $esperado
        ) => $valor == $esperado
            ? 'X'
            : '';

        $html = '
    <!DOCTYPE html>
    <html>
    <head>

        <meta charset="UTF-8">

        <title>
            Lista de verificación
        </title>

        <style>

            @page{
                margin:0.5cm 1cm;
            }

            body{
                font-family:Arial, Helvetica, sans-serif;
                font-size:12px;
            }

            table{
                width:100%;
                border-collapse:collapse;
            }

            .table-bordered td,
            .table-bordered th{
                border:1px solid #dee2e6;
                padding:6px;
            }

            .text-center{
                text-align:center;
            }

            .align-middle{
                vertical-align:middle;
            }

        </style>

    </head>

    <body>

    <table class="table-bordered">

        <tr>

            <td
                width="20%"
                class="text-center align-middle">

                <img
                    src="' . $logo . '"
                    width="150">

            </td>

            <td
                colspan="2"
                class="text-center align-middle">

                <b>
                    Listas de verificación
                </b>

            </td>

            <td
                class="text-center align-middle">

                <b>
                    Fo.ADMONGAS.015
                </b>

            </td>

        </tr>

        <tr>

            <td class="text-center">

                Realizado por:<br>
                Nelly Estrada Garcia

            </td>

            <td class="text-center">

                Revisado por:<br>
                Eduardo Galicia Flores

            </td>

            <td class="text-center">

                Autorizado por:<br>
                ' . e(
            $estacion?->apoderado_legal ?? ''
        ) . '

            </td>

            <td class="text-center">

                Fecha de aprobación<br>
                01-oct-18

            </td>

        </tr>

    </table>

    <br>

    <table>

        <tr>

            <td>

                <b>Fecha:</b>
                ' . formatearFecha(
            $formato->fecha_lv
        ) . '

            </td>

            <td>

                <b>Hora:</b>
                ' . $horaFormateada . '

            </td>

        </tr>

    </table>

    <br>

    <table class="table-bordered">

        <tr>

            <th></th>
            <th width="60">SI</th>
            <th width="60">NO</th>

        </tr>

        <tr>
            <td>
                1. El trabajo fue completado conforme a lo solicitado
            </td>

            <td class="text-center">
                ' . $siNo($formato->pregunta1, 1) . '
            </td>

            <td class="text-center">
                ' . $siNo($formato->pregunta1, 0) . '
            </td>
        </tr>

        <tr>
            <td>
                2. El trabajo se realizó conforme al procedimiento
            </td>

            <td class="text-center">
                ' . $siNo($formato->pregunta2, 1) . '
            </td>

            <td class="text-center">
                ' . $siNo($formato->pregunta2, 0) . '
            </td>
        </tr>

        <tr>
            <td>
                3. En todo momento se utilizó el EPP
            </td>

            <td class="text-center">
                ' . $siNo($formato->pregunta3, 1) . '
            </td>

            <td class="text-center">
                ' . $siNo($formato->pregunta3, 0) . '
            </td>
        </tr>

        <tr>
            <td>
                4. Los trabajadores tomaron en cuenta los procedimientos de seguridad
            </td>

            <td class="text-center">
                ' . $siNo($formato->pregunta4, 1) . '
            </td>

            <td class="text-center">
                ' . $siNo($formato->pregunta4, 0) . '
            </td>
        </tr>

        <tr>
            <td>
                5. Ocurrió algún accidente durante el servicio realizado
            </td>

            <td class="text-center">
                ' . $siNo($formato->pregunta5, 1) . '
            </td>

            <td class="text-center">
                ' . $siNo($formato->pregunta5, 0) . '
            </td>
        </tr>

    </table>

    <div
        style="
            margin-top:100px;
            text-align:center;
        ">

        ' . (!empty($firma)
            ? '<img src="' .
            $firma .
            '" width="140">'
            : ''
        ) . '

        <br>' . e($formato->supervisor?->nombre ?? '') . '
        <div
            style="
                width:250px;
                margin:0 auto;
                border-top:1px solid #000;
            ">
        </div>

        Nombre y firma (SUPERVISÓ)

    </div>

    </body>
    </html>';

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream('Listas_de_verificacion.pdf', ['Attachment' => true]);
        exit;
    }


    //--------------------------------------------------------
    //--------- Carta Responsiva

    public function cartaResponsiva(int $id)
    {
        header('Content-Type: application/json');

        $carta = RequisicionObraCartaResponsiva::where(
            'id_requisicion',
            $id
        )->first();

        if (!$carta) {

            $requisicion = RequisicionObra::query()
                ->when(
                    $this->estacionModulo(),
                    fn ($q, $est) => $q->where('id_estacion', $est)
                )
                ->find($id);

            if (!$requisicion) {

                echo json_encode([
                    'success' => false,
                    'message' => 'No se encontró la requisición'
                ]);

                return;
            }

            $estacion = $this->estacionModulo()
                ? Estacion::find($this->estacionModulo())
                : null;

            $carta = SeguridadContratistasService::validarCartaResponsiva(
                $id,
                (string) ($estacion?->di_municipio ?? ''),
                (string) ($estacion?->di_estado ?? ''),
                (string) ($estacion?->apoderado_legal ?? ''),
                (string) ($estacion?->razonsocial ?? ''),
                (string) ($estacion?->direccioncompleta ?? ''),
                (string) ($estacion?->firma ?? '')
            );
        }

        echo json_encode($carta);

        exit;
    }

    public function updateCartaResponsiva()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);

        try {

            $carta = RequisicionObraCartaResponsiva::findOrFail($data['id']);

            $carta->update([

                'municipio' => $data['municipio'],
                'estado' => $data['estado'],
                'dia' => $data['dia'],
                'mes' => $data['mes'],
                'year' => $data['year'],
                'representante_legal' => $data['representante_legal'],
                'razon_social' => $data['razon_social'],
                'domicilio' => $data['domicilio'],
                'apoderado_legal' => $data['apoderado_legal']
            ]);

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

    public function pdfCartaResponsiva(int $id)
    {
        header('Content-Type: application/pdf');

        $carta = RequisicionObraCartaResponsiva::where(
            'id_requisicion',
            $id
        )->first();

        if (!$carta) {
            return 'No existe la carta responsiva para esta requisición';
        }

        $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

        $firma = '';

        if (!empty($carta->firma)) {

            $rutaFirma = realpath(
                __DIR__ . '/../../public/uploads/firma-personal/' . $carta->firma
            );

            if ($rutaFirma && file_exists($rutaFirma)) {
                $firma = $_ENV['APP_URL'] . '/uploads/firma-personal/' . $carta->firma;
            }
        }

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Carta responsiva</title>
            <link rel="stylesheet" href="' .
            $_ENV['APP_URL'] .
            '/assets/css/pdf.css">
        </head>
        <body>

            <div class="text-center">
                <img src="' . $logo . '" style="width:250px;">
            </div>

            <div class="text-right mt-3">
                ' . $carta->municipio . ' ' .
            $carta->estado . ',
                a ' . $carta->dia . ' de ' .
            nombremes($carta->mes) .
            ' del ' . $carta->year . '
            </div>

            <div class="text-right">
                <b>Asunto:</b> Carta responsiva
            </div>

            <div style="margin-top:50px;">
                <b>A QUIEN CORRESPONDA.</b>
            </div>

            <div class="mt-3">
                Por este conducto le mando un cordial saludo,
                a su vez,
                ' . $carta->representante_legal . ',
                representante legal de
                ' . $carta->razon_social . '.
            </div>

            <div class="mt-2">
                Con domicilio en,
                ' . $carta->domicilio . '.
            </div>

            <div class="mt-3">
                Doy mi responsivo total de los daños o perjuicios
                de riesgos y aspectos ambientales presentados
                durante las actividades u operaciones derivadas
                de los contratistas, subcontratistas,
                prestadores de servicio y personal interno
                que labore dentro de la estación de servicio
                antes mencionada.
            </div>

            <div class="mt-2">
                Por último, ratifico mi voluntad a efecto
                de cubrir con todas las obligaciones a cubrir.
            </div>

            <div class="mt-2">
                Sirva la presente para todos los fines legales
                a que haya lugar.
            </div>';

        if (!empty($firma)) {

            $html .= '
            <div class="text-center" style="margin-top:150px;">
                <img src="' . $firma . '" style="width:120px;">
            </div>';
        } else {

            $html .= '
            <div style="height:150px;"></div>';
        }

        $html .= '

            <div class="text-center">
                ' . $carta->apoderado_legal . '
            </div>

            <div class="text-center">
                <b>Apoderado legal</b>
            </div>

            <div class="text-center">
                ' . $carta->razon_social . '
            </div>

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
            'Carta-responsiva.pdf',
            ['Attachment' => true]
        );
    }
}
