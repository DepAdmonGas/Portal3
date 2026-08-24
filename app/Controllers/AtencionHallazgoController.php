<?php

namespace App\Controllers;

use App\Core\View;
use App\Services\ModuloService;
use App\Core\Breadcrumb;
use App\Models\Estacion;
use App\Models\Sasisopa\AtencionHallazgo;
use App\Models\Sasisopa\AtencionHallazgoDetalle;
use App\Models\Sasisopa\AtencionHallazgoEvidencia;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Capsule\Manager as Capsule;

class AtencionHallazgoController extends BaseController
{

    protected string $modulo = 'sasisopa';

    public function index()
    {

        $title = 'ATENCIÓN DE HALLAZGOS';

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
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/monitoreoverificacionevaluacion/atencionhallazgos.actions.init.js?v=' . time(),
            ],
            'help' => false
        ];

        View::render('monitoreoverificacionevaluacion/atencion-hallazgos', $data, 'sasisopa');
    }

    public function datatable()
    {

        $informes = AtencionHallazgo::query()
            ->where(
                'id_estacion',
                $this->estacionId()
            )
            ->orderByDesc('fecha_auditoria')
            ->get()
            ->values()
            ->map(function ($item, $index) {

                return [
                    'id' => $item->id,
                    'numero' => $index + 1,
                    'fecha' => $item->fecha_auditoria->format('Y-m-d'),
                    'fecha_larga' => formatearFecha($item->fecha_auditoria?->format('Y-m-d')),
                    'no_control' => $item->no_control,
                    'tipo_auditoria' => $item->tipo_auditoria
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

    public function create()
    {

        $folio = AtencionHallazgo::query()
            ->where('id_estacion', $this->estacionId())
            ->max('folio');

        $hallazgo = AtencionHallazgo::create([

            'id_estacion'     => $this->estacionId(),
            'folio'           => ($folio ?? 0) + 1,
            'fecha_auditoria' => date('Y-m-d'),
            'no_control'      => '',
            'tipo_auditoria'  => '',
        ]);

        echo json_encode([
            'success' => true,
            'id' => $hallazgo->id
        ]);
    }

    public function pdf(int $id)
    {

        $estacion = Estacion::find($this->estacionId());
        $hallazgo = AtencionHallazgo::find($id);
        $detalles = AtencionHallazgoDetalle::with([
            'sasisopa',
            'evidencias'
        ])
            ->where('id_atencion', $id)
            ->orderBy('id_sasisopa')
            ->get();

        $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

        $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <title>Atención de Hallazgos</title>
        <style>
            @page {
                margin: 0.5cm;
                font-family: Arial, Helvetica, sans-serif;
            }
            body{
                font-family: Arial, Helvetica, sans-serif;
                font-size:15px;
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
            .mt-3 {
            margin-top: 1rem !important;
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
                    <strong>Atención de Hallazgos</strong>
                </td>
                <td class="text-center align-middle">
                    Fo.ADMONGAS.018
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
                <th class="text-center">#</th>
               <th class="text-center">
                    Fecha de la auditoría
                </th>
                <th class="text-center">
                    No. de control de la auditoría
                </th>
                <th class="text-center">
                    Tipo de auditoría
                </th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="text-center">
                    ' . $hallazgo->folio . '
                </td>
                <td class="text-center">
                    ' . formatearFecha($hallazgo->fecha_auditoria) . '
                </td>
                <td class="text-center">
                    ' . htmlspecialchars($hallazgo->no_control) . '
                </td>
                <td class="text-center">
                    ' . htmlspecialchars($hallazgo->tipo_auditoria) . '
                </td>
            </tr>
        </tbody>
    </table>

    <table class="table mt-3">
        <thead>
            <tr>
                <th class="text-center">
                    SASISOPA
                </th>
                <th class="text-center">
                    Hallazgos
                </th>
                <th class="text-center">
                    Acción preventiva por hallazgo
                </th>
                <th class="text-center">
                    Fecha de implementación
                </th>
                <th class="text-center">
                    Evidencia
                </th>
                <th class="text-center">
                    % de cumplimiento
                </th>
            </tr>
        </thead>
        <tbody>';

        foreach ($detalles as $detalle) {

            $evidencias = '';

            foreach ($detalle->evidencias as $evidencia) {
                $evidencias .= htmlspecialchars($evidencia->archivo) . '<br>';
            }

            $html .= '
        <tr>
            <td class="text-center">
                <strong>' .
                htmlspecialchars(
                    $detalle->sasisopa?->nombre ?? ''
                ) .
                '</strong>
            </td>
            <td class="text-center">
                ' . nl2br(
                    htmlspecialchars(
                        $detalle->hallazgos
                    )
                ) . '
            </td>
            <td class="text-center">
                ' . nl2br(
                    htmlspecialchars(
                        $detalle->accion
                    )
                ) . '
            </td>
            <td class="text-center">
                ' .
                formatearFecha(
                    $detalle->fecha_implementacion
                        ->format('Y-m-d')
                ) .
                '
            </td>
            <td class="text-center">
                ' . $evidencias . '
            </td>
            <td class="text-center">
                <strong>' .
                (
                    $detalle->evidencias->isNotEmpty()
                    ? '100%'
                    : '0%'
                ) .
                '</strong>
            </td>
        </tr>';
        }

        if ($detalles->isEmpty()) {

            $html .= '
        <tr>
            <td colspan="6"
                class="text-center">
                No se encontró información para mostrar
            </td>
        </tr>';
        }

        $html .= '
    </tbody>
    </table>
    </body>
    </html>';

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $dompdf->stream(
            'Atención de Hallazgos.pdf',
            [
                'Attachment' => true
            ]
        );

        exit;
    }

    public function delete()
    {

        header('Content-Type: application/json');

        try {

            $request = json_decode(file_get_contents('php://input'), true);
            $id = (int) ($request['id'] ?? 0);
            $hallazgo = AtencionHallazgo::find($id);

            if (!$hallazgo) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Registro no encontrado'
                ]);

                return;
            }

            Capsule::transaction(function () use ($id, $hallazgo) {

                $detalles = AtencionHallazgoDetalle::query()
                    ->where('id_atencion', $id)
                    ->get();

                foreach ($detalles as $detalle) {

                    AtencionHallazgoEvidencia::query()
                        ->where('id_hallazgo', $detalle->id)
                        ->delete();
                }

                AtencionHallazgoDetalle::query()
                    ->where('id_atencion', $id)
                    ->delete();

                $hallazgo->delete();
            });

            echo json_encode([
                'success' => true,
                'message' => 'Hallazgo eliminado correctamente'
            ]);
        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

    function Semanas($Fecha, $Semanas)
    {
        return date("d-m-Y", strtotime($Fecha . "+ $Semanas week"));
    }

    function Dias($Fecha, $Dias)
    {
        return date("d-m-Y", strtotime($Fecha . "+ $Dias days"));
    }

    function Year($Fecha, $Year)
    {
        return date("d-m-Y", strtotime($Fecha . "+ $Year year"));
    }

    function DiasMenos($Fecha, $Dias)
    {
        return date("d-m-Y", strtotime($Fecha . "- $Dias days"));
    }

    function SemanasMenos($Fecha, $Semanas)
    {
        return date("d-m-Y", strtotime($Fecha . "- $Semanas week"));
    }

    public function descargarProgramaImplementacion()
    {

        $estacion = Estacion::find($this->estacionId());
        $FechaInicio = formatearFechaCorta($estacion->fecha_autorizacion);
        $SS1 = $this->Semanas($FechaInicio, 2);
        $SD1 = $this->Dias($SS1, 1);
        $SS3 = $this->Semanas($SD1, 3);
        $SD3 = $this->Dias($SS3, 1);
        $SS7 = $this->Semanas($SD3, 4);
        $SD7 = $this->Dias($SS7, 1);
        $SS9 = $this->Semanas($SD7, 8);
        $SD9 = $this->Dias($SS9, 1);
        $SS10 = $this->Semanas($SD9, 2);
        $SD10 = $this->Dias($SS10, 1);
        $SS11 = $this->Semanas($SD10, 2);
        $SD11 = $this->Dias($SS11, 1);
        $SS18 = $this->Semanas($SD11, 2);
        $SD18 = $this->Dias($SS18, 1);
        $SS19 = $this->Semanas($SD18, 2);
        $SD19 = $this->Dias($SS19, 1);
        $SS20 = $this->Semanas($SD19, 3);
        $SD20 = $this->Dias($SS20, 1);
        $SS28 = $this->Semanas($SD20, 3);
        $SD28 = $this->Dias($SS28, 1);
        $SS29 = $this->Semanas($SD20, 1);
        $SS30 = $this->Semanas($SD20, 2);
        $SS31 = $this->Semanas($SD28, 3);
        $SD31 = $this->Dias($SS31, 1);
        $SS33 = $this->Semanas($SD31, 3);
        $SD33 = $this->Dias($SS33, 1);
        $SS35 = $this->Semanas($SD33, 4);
        $SD35 = $this->Dias($SS35, 1);
        $SS37 = $this->Semanas($SD35, 4);
        $SD37 = $this->Dias($SS37, 1);
        $SS39 = $this->Semanas($SD37, 2);
        $SD39 = $this->Dias($SS39, 1);
        $SS40 = $this->Semanas($SD39, 2);
        $FIAnual = $this->Year($FechaInicio, 1);
        $FF43 = $this->DiasMenos($FIAnual, 1);
        $FI43 = $this->SemanasMenos($FF43, 8);

        $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <title>Programa de implementación del Sistema de Administración</title>

        <style>

            @page {
                margin: 0.5cm;
                font-family: Arial, Helvetica, sans-serif;
            }

            body{
                font-family: Arial, Helvetica, sans-serif;
                font-size:15px;
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

            .mt-3 {
            margin-top: 1rem !important;
            }

            .bg-light {
            background-color: #f8f9fa !important;
            }

            .bg-dark {
            background-color: #343a40 !important;
            }

            .text-white {
            color: #fff !important;
            }

        </style>
    </head>
    <body>
    
    <table class="table table-sm table-bordered">
        <tr>
        <td class="align-middle">' . $estacion->razonsocial . ' ' . $estacion->permisocre . '</td>
        <td class="align-middle"><b>Programa de implementación del Sistema de Administración</b></td>
        <td class="align-middle">Fecha de aprobacion 04-Marzo-21</td>
        <td class="align-middle">Fo.ADMONGAS.017</td>
        </tr>
        <tr>
        <td class="align-middle">Realizado por: Nelly Garcia Estrada</td>
        <td class="align-middle">Revisado por: Eduardo Galicia Flores </td>
        <td class="align-middle" colspan="2">Aprobado por: ' . $estacion->apoderado_legal . '</td>
        </tr>
    </table>

    <table class="table table-sm table-bordered mt-3">
    <thead>
  <tr>
  <th class="align-middle text-white bg-dark" width="20px">No</th>
  <th class="align-middle text-white bg-dark">Actividad</th>
  <th class="align-middle text-white bg-dark" width="60px">Semanas</th>
  <th class="align-middle text-white bg-dark" width="85px">Periodicidad</th>
  <th class="align-middle text-white bg-dark" width="90px">Fecha de Inicio</th>
  <th class="align-middle text-white bg-dark" width="90px">Fecha de Termino</th>
  </tr>
  </thead>
  <tbody>
  <tr>
  <td colspan="6" class="text-center bg-light"><b>I. Politica</b></td>
  </tr>
  //--------1
  <tr>
  <td class="align-middle text-center">1</td>
  <td class="align-middle">Revision de politica del Sistema de Administracion</td>
  <td class="align-middle text-center">2</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $FechaInicio . '</td>
  <td class="align-middle text-center">' . $SS1 . '</td>
  </tr>
  //--------2
  <tr>
  <td class="align-middle text-center">2</td>
  <td class="align-middle">Difusion de politica</td>
  <td class="align-middle text-center">12</td>
  <td class="align-middle text-center">Mensual</td>
  <td class="align-middle text-center" colspan="2">Primer semana de cada mes </td>
  </tr>

  <tr>
  <td colspan="6" class="text-center bg-light"><b>II. Identificación de peligros y aspectos ambientales, análisis de riesgo y evaluación de impactos ambientales.</b></td>
  </tr>

  //--------3
  <tr>
  <td class="align-middle text-center">3</td>
  <td class="align-middle">Listado de peligros y aspectos ambientales.</td>
  <td class="align-middle text-center">3</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD1 . '</td>
  <td class="align-middle text-center">' . $SS3 . '</td>
  </tr>
  //--------4
  <tr>
  <td class="align-middle text-center">4</td>
  <td class="align-middle">El resultado del análisis de riesgo.</td>
  <td class="align-middle text-center">3</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD1 . '</td>
  <td class="align-middle text-center">' . $SS3 . '</td>
  </tr>
  //--------5
  <tr>
  <td class="align-middle text-center">5</td>
  <td class="align-middle">El resultado de la evaluación de Aspectos Ambientales.</td>
  <td class="align-middle text-center">3</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD1 . '</td>
  <td class="align-middle text-center">' . $SS3 . '</td>
  </tr>
  //--------6
  <tr>
  <td class="align-middle text-center">6</td>
  <td class="align-middle">El listado de los riesgos y los aspectos ambientales significativos a controlar.</td>
  <td class="align-middle text-center">3</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD1 . '</td>
  <td class="align-middle text-center">' . $SS3 . '</td>
  </tr>

  <tr>
  <td colspan="6" class="text-center bg-light"><b>III. Requisitos legales.</b></td>
  </tr>

  //--------7
  <tr>
  <td class="align-middle text-center">7</td>
  <td class="align-middle">El listado de los requisitos legales vigentes y otros requisitos aplicables a los procesos y actividades del regulado.</td>
  <td class="align-middle text-center">4</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD3 . '</td>
  <td class="align-middle text-center">' . $SS7 . '</td>
  </tr>
  //--------8
  <tr>
  <td class="align-middle text-center">8</td>
  <td class="align-middle">El listado de los requisitos legales vigentes de los permisos, autorizaciones, licencias y otros trámites.</td>
  <td class="align-middle text-center">4</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD3 . '</td>
  <td class="align-middle text-center">' . $SS7 . '</td>
  </tr>

  <tr>
  <td colspan="6" class="text-center bg-light"><b>IV. Objetivos, metas, indicadores</b></td>
  </tr>

  //--------9
  <tr>
  <td class="align-middle text-center">9</td>
  <td class="align-middle">El listado de los requisitos legales vigentes de los permisos, autorizaciones, licencias y otros trámites.</td>
  <td class="align-middle text-center">8</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD7 . '</td>
  <td class="align-middle text-center">' . $SS9 . '</td>
  </tr

  <tr>
  <td colspan="6" class="text-center bg-light"><b>V. Funciones, responsabilidades y autoridad.</b></td>
  </tr>

  //--------10
  <tr>
  <td class="align-middle text-center">10</td>
  <td class="align-middle">La designación documentada del Representante Técnico ante la Agencia.</td>
  <td class="align-middle text-center">2</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD9 . '</td>
  <td class="align-middle text-center">' . $SS10 . '</td>
  </tr>

  <tr>
  <td colspan="6" class="text-center bg-light"><b>VI. Competencia del personal, capacitación y entrenamiento.</b></td>
  </tr>

  //--------11
  <tr>
  <td class="align-middle text-center">11</td>
  <td class="align-middle">Perfiles de puesto</td>
  <td class="align-middle text-center">2</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD10 . '</td>
  <td class="align-middle text-center">' . $SS11 . '</td>
  </tr>
  //--------12
  <tr>
  <td class="align-middle text-center">12</td>
  <td class="align-middle">Programas anuales para el desarrollo de la competencia incluida la capacitación inicial para personal de nuevo ingreso</td>
  <td class="align-middle text-center">2</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD10 . '</td>
  <td class="align-middle text-center">' . $SS11 . '</td>
  </tr>
  //--------13
  <tr>
  <td class="align-middle text-center">13</td>
  <td class="align-middle">Programas anuales para el desarrollo de la competencia incluida la capacitación para operar y mantener equipos nuevos</td>
  <td class="align-middle text-center">2</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD10 . '</td>
  <td class="align-middle text-center">' . $SS11 . '</td>
  </tr>
  //--------14
  <tr>
  <td class="align-middle text-center">14</td>
  <td class="align-middle">Programas anuales para el desarrollo de la competencia incluida la capacitación de actualización para el personal al menos cada 3 años o de acuerdo a la actualización por cambios en las instrucciones de trabajo o tecnología, procedimientos o normatividad</td>
  <td class="align-middle text-center">2</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD10 . '</td>
  <td class="align-middle text-center">' . $SS11 . '</td>
  </tr>
  //--------15
  <tr>
  <td class="align-middle text-center">15</td>
  <td class="align-middle">Programas anuales para el desarrollo de la competencia incluida la capacitación para contratistas, subcontratistas, prestadores de servicios y proveedores</td>
  <td class="align-middle text-center">2</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD10 . '</td>
  <td class="align-middle text-center">' . $SS11 . '</td>
  </tr>
  //--------16
  <tr>
  <td class="align-middle text-center">16</td>
  <td class="align-middle">Registros de competencia (inducción, capacitación, entrenamiento y reentrenamientos) del personal propio, contratistas, subcontratistas, prestadores de servicio y proveedores</td>
  <td class="align-middle text-center">2</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD10 . '</td>
  <td class="align-middle text-center">' . $SS11 . '</td>
  </tr>

  <tr>
  <td colspan="6" class="text-center bg-light"><b>VII. Comunicación, participación y consulta.</b></td>
  </tr>

  //--------17
  <tr>
  <td class="align-middle text-center">17</td>
  <td class="align-middle">Formatos para la distribución y control de las comunicaciones</td>
  <td class="align-middle text-center">1</td>
  <td class="align-middle text-center">Mensual</td>
  <td class="align-middle text-center" colspan="2">Primer semana de cada mes</td>
  </tr>

  <tr>
  <td colspan="6" class="text-center bg-light"><b>VIII. Control de documentos y registros.</b></td>
  </tr>

  //--------18
  <tr>
  <td class="align-middle text-center">18</td>
  <td class="align-middle">Listado de la información documentada del Sistema de Administración.</td>
  <td class="align-middle text-center">2</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD11 . '</td>
  <td class="align-middle text-center">' . $SS18 . '</td>
  </tr>

  <tr>
  <td colspan="6" class="text-center bg-light"><b>IX. Mejores prácticas y estándares.</b></td>
  </tr>

  //--------19
  <tr>
  <td class="align-middle text-center">19</td>
  <td class="align-middle">El listado de la normatividad, códigos, estándares o prácticas de ingeniería que se utilizarán y aplicarán en las etapas de desarrollo, así como en la inspección de las instalaciones, equipos y procesos del Proyecto</td>
  <td class="align-middle text-center">2</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD18 . '</td>
  <td class="align-middle text-center">' . $SS19 . '</td>
  </tr>

  <tr>
  <td colspan="6" class="text-center bg-light"><b>X. Control de actividades y procesos.</b></td>
  </tr>

  //--------20
  <tr>
  <td class="align-middle text-center">20</td>
  <td class="align-middle">La descripción de todos los criterios y controles de operación para aplicar en las diferentes Etapas de Desarrollo del Proyecto, atendiendo al menos Actividades de la etapa de operación y mantenimiento considerando: Pruebas y puesta en marcha de instalaciones y equipos;</td>
  <td class="align-middle text-center">3</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD19 . '</td>
  <td class="align-middle text-center">' . $SS20 . '</td>
  </tr>
  //--------21
  <tr>
  <td class="align-middle text-center">21</td>
  <td class="align-middle">La descripción de todos los criterios y controles de operación para aplicar en las diferentes Etapas de Desarrollo del Proyecto, atendiendo al menos Actividades de la etapa de operación y mantenimiento considerando: Uso de maquinaria, equipo, manejo de combustibles y sustancias químicas;</td>
  <td class="align-middle text-center">3</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD19 . '</td>
  <td class="align-middle text-center">' . $SS20 . '</td>
  </tr>
  //--------22
  <tr>
  <td class="align-middle text-center">22</td>
  <td class="align-middle">La descripción de todos los criterios y controles de operación para aplicar en las diferentes Etapas de Desarrollo del Proyecto, atendiendo al menos Actividades de la etapa de operación y mantenimiento considerando: Protección de suelo y cuerpos de agua, descarga de agua residual, emisión de ruido, emisión de gases a la atmósfera y manejo de residuos</td>
  <td class="align-middle text-center">3</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD19 . '</td>
  <td class="align-middle text-center">' . $SS20 . '</td>
  </tr>
  //--------23
  <tr>
  <td class="align-middle text-center">23</td>
  <td class="align-middle">La descripción de todos los criterios y controles de operación para aplicar en las diferentes Etapas de Desarrollo del Proyecto, atendiendo al menos Actividades de la etapa de operación y mantenimiento considerando: Expendio al público de Gas Natural, Distribución y Expendio al público de Gas Licuado de Petróleo y de Petrolíferos</td>
  <td class="align-middle text-center">3</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD19 . '</td>
  <td class="align-middle text-center">' . $SS20 . '</td>
  </tr>
  //--------24
  <tr>
  <td class="align-middle text-center">24</td>
  <td class="align-middle">La descripción de todos los criterios y controles de operación para aplicar en las diferentes Etapas de Desarrollo del Proyecto, atendiendo al menos Actividades de la etapa de operación y mantenimiento considerando: Acceso y circulación de auto-tanques y vehículos de reparto</td>
  <td class="align-middle text-center">3</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD19 . '</td>
  <td class="align-middle text-center">' . $SS20 . '</td>
  </tr>
  //--------25
  <tr>
  <td class="align-middle text-center">25</td>
  <td class="align-middle">La descripción de todos los criterios y controles de operación para aplicar en las diferentes Etapas de Desarrollo del Proyecto, atendiendo al menos Actividades de la etapa de operación y mantenimiento considerando: Manejo de recipientes transportables (cilindros) de Gas L.P</td>
  <td class="align-middle text-center">3</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD19 . '</td>
  <td class="align-middle text-center">' . $SS20 . '</td>
  </tr>
  //--------26
  <tr>
  <td class="align-middle text-center">26</td>
  <td class="align-middle">La descripción de todos los criterios y controles de operación para aplicar en las diferentes Etapas de Desarrollo del Proyecto, atendiendo al menos Actividades de la etapa de operación y mantenimiento considerando: Administración de cambios de tecnología</td>
  <td class="align-middle text-center">3</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD19 . '</td>
  <td class="align-middle text-center">' . $SS20 . '</td>
  </tr>
  //--------27
  <tr>
  <td class="align-middle text-center">27</td>
  <td class="align-middle">La descripción de todos los criterios y controles de operación para aplicar en las diferentes Etapas de Desarrollo del Proyecto, atendiendo al menos Actividades de la etapa de operación y mantenimiento considerando: Administración de cambios de personal</td>
  <td class="align-middle text-center">3</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD19 . '</td>
  <td class="align-middle text-center">' . $SS20 . '</td>
  </tr>

  <tr>
  <td colspan="6" class="text-center bg-light"><b>XI. Integridad mecánica y aseguramiento de la calidad.</b></td>
  </tr>

  //--------28
  <tr>
  <td class="align-middle text-center">28</td>
  <td class="align-middle">Los programas de mantenimiento predictivo, preventivo, calibración, certificación, verificación, inspeccione y pruebas de equipos críticos.</td>
  <td class="align-middle text-center">3</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD20 . '</td>
  <td class="align-middle text-center">' . $SS28 . '</td>
  </tr>
  //--------29
  <tr>
  <td class="align-middle text-center">29</td>
  <td class="align-middle">Carta responsiva firmada por el Represente Legal, en donde asume la responsabilidad por la administración del riesgo y de los impactos al ambiente que se deriven de las actividades de contratistas, prestadores de servicio y proveedores.</td>
  <td class="align-middle text-center">1</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD20 . '</td>
  <td class="align-middle text-center">' . $SS29 . '</td>
  </tr>
  //--------30
  <tr>
  <td class="align-middle text-center">30</td>
  <td class="align-middle">Requisitos en materia de Seguridad Industrial, Seguridad Operativa y de Protección al Medio Ambiente a los que deben sujetarse los contratistas, subcontratistas, prestadores de servicio y proveedores</td>
  <td class="align-middle text-center">2</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD20 . '</td>
  <td class="align-middle text-center">' . $SS30 . '</td>
  </tr>

  <tr>
  <td colspan="6" class="text-center bg-light"><b>XII. Seguridad de contratistas.</b></td>
  </tr>

  //--------31
  <tr>
  <td class="align-middle text-center">31</td>
  <td class="align-middle">Carta responsiva firmada por el Represente Legal, en donde asume la responsabilidad por la administración del riesgo y de los impactos al ambiente que se deriven de las actividades de contratistas, prestadores de servicio y proveedores.</td>
  <td class="align-middle text-center">3</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD28 . '</td>
  <td class="align-middle text-center">' . $SS31 . '</td>
  </tr>
   //--------32
  <tr>
  <td class="align-middle text-center">32</td>
  <td class="align-middle">Requisitos en materia de Seguridad Industrial, Seguridad Operativa y de Protección al Medio Ambiente a los que deben sujetarse los contratistas, subcontratistas, prestadores de servicio y proveedores</td>
  <td class="align-middle text-center">3</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD28 . '</td>
  <td class="align-middle text-center">' . $SS31 . '</td>
  </tr>

  <tr>
  <td colspan="6" class="text-center bg-light"><b>XIII. Preparación y respuesta a emergencias.</b></td>
  </tr>

   //--------33
  <tr>
  <td class="align-middle text-center">33</td>
  <td class="align-middle">El listado de situaciones potenciales de emergencia identificadas para todas las instalaciones y sitios donde se desarrollen las actividades de expendio al público</td>
  <td class="align-middle text-center">3</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD31 . '</td>
  <td class="align-middle text-center">' . $SS33 . '</td>
  </tr>
   //--------34
  <tr>
  <td class="align-middle text-center">34</td>
  <td class="align-middle">Los planes de atención y respuesta a emergencias y programa de simulacros</td>
  <td class="align-middle text-center">3</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD31 . '</td>
  <td class="align-middle text-center">' . $SS33 . '</td>
  </tr>

  <tr>
  <td colspan="6" class="text-center bg-light"><b>XIV. Monitoreo, verificación y evaluación.</b></td>
  </tr>

  //--------35
  <tr>
  <td class="align-middle text-center">35</td>
  <td class="align-middle">Programa de monitoreo y medición de parámetros de desempeño</td>
  <td class="align-middle text-center">4</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD33 . '</td>
  <td class="align-middle text-center">' . $SS35 . '</td>
  </tr>
  //--------36
  <tr>
  <td class="align-middle text-center">36</td>
  <td class="align-middle">Resultados de calibración y mantenimiento de equipos empleados en monitoreo del Sistema de Administración</td>
  <td class="align-middle text-center">4</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD33 . '</td>
  <td class="align-middle text-center">' . $SS35 . '</td>
  </tr>

  <tr>
  <td colspan="6" class="text-center bg-light"><b>XV. Auditorias.</b></td>
  </tr>

  //--------37
  <tr>
  <td class="align-middle text-center">37</td>
  <td class="align-middle">El programa de auditorías, internas y externas, del Sistema a aplicar en el año en curso</td>
  <td class="align-middle text-center">4</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD35 . '</td>
  <td class="align-middle text-center">' . $SS37 . '</td>
  </tr>
  //--------38
  <tr>
  <td class="align-middle text-center">38</td>
  <td class="align-middle">Los criterios de competencia para la calificación, entrenamiento y selección de auditores internos</td>
  <td class="align-middle text-center">4</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD35 . '</td>
  <td class="align-middle text-center">' . $SS37 . '</td>
  </tr>

  <tr>
  <td colspan="6" class="text-center bg-light"><b>XVI. Investigación de incidentes y accidentes.</b></td>
  </tr>

   //--------39
  <tr>
  <td class="align-middle text-center">39</td>
  <td class="align-middle">La metodología utilizada para la investigación y análisis de incidentes y accidentes que considera lo establecido en las Disposiciones aplicables.</td>
  <td class="align-middle text-center">2</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD37 . '</td>
  <td class="align-middle text-center">' . $SS39 . '</td>
  </tr>

  <tr>
  <td colspan="6" class="text-center bg-light"><b>XVII. Revision de resultados. </b></td>
  </tr>

   //--------40
  <tr>
  <td class="align-middle text-center">40</td>
  <td class="align-middle">Elaborar el Informe de resultados de la implementación del Sistema de Administración</td>
  <td class="align-middle text-center">2</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD39 . '</td>
  <td class="align-middle text-center">' . $SS40 . '</td>
  </tr>
   //--------41
  <tr>
  <td class="align-middle text-center">41</td>
  <td class="align-middle">Elaborar del Plan de acciones correctivas y de mejora, que se deriven del informe de resultados</td>
  <td class="align-middle text-center">2</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD39 . '</td>
  <td class="align-middle text-center">' . $SS40 . '</td>
  </tr>
   //--------42
  <tr>
  <td class="align-middle text-center">42</td>
  <td class="align-middle">Comunicar las  acciones correctivas y de mejora,que se realizaron</td>
  <td class="align-middle text-center">2</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $SD39 . '</td>
  <td class="align-middle text-center">' . $SS40 . '</td>
  </tr>

  <tr>
  <td colspan="6" class="text-center bg-light"><b>XVIII. Informes de desempeño.</b></td>
  </tr>

   //--------43
  <tr>
  <td class="align-middle text-center">43</td>
  <td class="align-middle">Los indicadores de evaluación del desempeño del Sistema de Administración</td>
  <td class="align-middle text-center">8</td>
  <td class="align-middle text-center">Anual</td>
  <td class="align-middle text-center">' . $FI43 . '</td>
  <td class="align-middle text-center">' . $FF43 . '</td>
  </tr>
  </tbody>
  </table>';

        $RutaFirmaF1 = $_ENV['APP_URL'] . '/uploads/firma-personal/Nelly-13062023.PNG';
        $RutaFirmaF2 = $_ENV['APP_URL'] . '/uploads/firma-personal/Lalo-13062023.PNG';
        $RutaFirmaAL = $_ENV['APP_URL'] . '/uploads/firma-personal/' . $estacion->firma;

        $html .= '<table class="table table-sm table-bordered">
    <tr>
    <td colspan="3" class="text-center"><b>Firmas de conformidad</b></td>
    </tr>
    <tr>
    <td class="text-center">Nelly Estrada</td>
    <td class="text-center">Eduardo Galicia Flores</td>
    <td class="text-center">Tomas Tarno Quinzaños</td>
    </tr>
    <tr>
    <td class="text-center"><img src="' . $RutaFirmaF1 . '" style="width: 120px;"></td>
    <td class="text-center"><img src="' . $RutaFirmaF2 . '" style="width: 120px;"></td>
    <td class="text-center"><img src="' . $RutaFirmaAL . '" style="width: 120px;"></td>
    </tr>
    </table>';

        $html .= '
    </body>
    </html>';

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $dompdf->stream(
            'Programa de implementación del Sistema de Administración.pdf',
            [
                'Attachment' => true
            ]
        );

        exit;
    }
}
