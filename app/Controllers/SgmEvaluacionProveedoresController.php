<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Request;
use App\Core\JsonResponse;
use App\Services\ModuloService;
use App\Services\ModuleStationService;

use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Sasisopa\RequisicionObra;
use App\Models\Sgm\Autorizado;
use App\Models\Sgm\OrdenServicio;
use App\Models\Sgm\EvaluacionProveedor;

use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;

class SgmEvaluacionProveedoresController extends BaseController
{
    protected string $modulo = 'sgm';

    private function estacionModulo(): ?int
    {
        return ModuleStationService::getContext('sgm')['id_estacion'] ?? null;
    }

    public function index()
    {
        $title = 'Orden de servicio y Evaluación de proveedores';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add('6. Gestion de los Recursos', '/sgm/gestion-recursos/inventario-equipo');
        Breadcrumb::add($title, '');
        $permisos = ModuloService::permisosSesion($this->modulo);

        $estacionId = $this->estacionModulo();

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'estacionId' => $estacionId,
            'moduleStationKey' => 'sgm',
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/core/module-station-selector.js?v=' . time(),
                '/js/sgm/gestion-recursos/orderservicioevaluacionproveedores.actions.init.js?v=1.1.0'
            ],
            'help' => true
        ];

        View::render('sgm/gestion-recursos/orden-servicio-evaluacion-proveedores', $data, 'sgm');
    }

    public function data(): void
    {
        $ordenes = OrdenServicio::query()
            ->with('evaluacion')
            ->where(
                'id_estacion',
                $this->estacionModulo()
            )
            ->orderByDesc('id')
            ->get()
            ->map(function ($orden, $index) {

                return [

                    'numero' => $index + 1,

                    'id' => $orden->id,

                    'folio' => $orden->folio,

                    'fecha' => $orden->fecha
                        ? formatearFecha($orden->fecha)
                        : '',

                    'hora' => $orden->hora,

                    'descripcion' => $orden->descripcion,

                    'evaluacion' => $orden->evaluacion?->estado == 1

                ];
            });

        JsonResponse::custom([
            'success' => true,
            'data' => $ordenes
        ]);
    }

    public function detalle(int $id): void
    {
        try {

            $orden = OrdenServicio::query()
                ->findOrFail($id);

            JsonResponse::custom([
                'success' => true,
                'data' => [
                    'id'             => $orden->id,
                    'descripcion'    => $orden->descripcion,
                    'justificacion'  => $orden->justificacion,
                    'folio'          => $orden->folio,
                    'fecha'          => optional($orden->fecha)?->format('Y-m-d'),
                    'hora'           => $orden->hora,
                ]
            ]);
        } catch (\Throwable $e) {

            JsonResponse::error(
                'No fue posible obtener la orden de servicio.'
            );
        }
    }

    private function usuarioAutorizado(): int
    {
        return Autorizado::query()

            ->join(
                'tb_usuarios',
                'tb_usuarios.id',
                '=',
                'sgm_autorizado.id_usuario'
            )

            ->where(
                'tb_usuarios.id_gas',
                $this->estacionModulo()
            )

            ->where(
                'sgm_autorizado.estado',
                1
            )

            ->value('sgm_autorizado.id_usuario') ?? 0;
    }

    public function create(): void
    {
        try {

            $folio = RequisicionObra::query()
                ->where('id_estacion', $this->estacionModulo())
                ->max('no_folio');

            $folio = $folio ? $folio + 1 : 1;

            $orden = OrdenServicio::create([

                'id_estacion'     => $this->estacionModulo(),
                'fecha'           => date('Y-m-d'),
                'hora'            => date('H:i:s'),
                'id_solicitante'  => $this->estacionModulo(),
                'descripcion'     => Request::input('descripcion'),
                'justificacion'   => Request::input('justificacion'),
                'realizadopor'    => $this->usuarioAutorizado(),
                'folio'           => $folio,

            ]);

            RequisicionObra::create([

                'id_estacion'    => $this->estacionModulo(),
                'id_usuario'     => $this->userId(),
                'no_folio'       => $folio,
                'fecha'          => date('Y-m-d H:i:s'),
                'descripcion'    => $orden->descripcion,
                'justificacion'  => $orden->justificacion,
                'proveedor'     => '',
                'estado'         => 1

            ]);

            JsonResponse::success(
                'Orden creada.',
                [
                    'id' => $orden->id
                ]
            );
        } catch (\Throwable $e) {

            JsonResponse::error(
                $e->getMessage()
            );
        }
    }

    public function update(): void
    {
        try {

            $orden = OrdenServicio::findOrFail(
                Request::input('id')
            );

            $orden->update([

                'descripcion'   => Request::input('descripcion'),
                'justificacion' => Request::input('justificacion')

            ]);

            RequisicionObra::query()

                ->where(
                    'id_estacion',
                    $this->estacionModulo()
                )

                ->where(
                    'no_folio',
                    $orden->folio
                )

                ->update([

                    'descripcion'   => $orden->descripcion,
                    'justificacion' => $orden->justificacion

                ]);

            JsonResponse::success(
                'Orden actualizada.'
            );
        } catch (\Throwable $e) {

            JsonResponse::error(
                'No fue posible actualizar la orden.'
            );
        }
    }

    public function delete(): void
    {
        try {

            $orden = OrdenServicio::findOrFail(
                Request::input('id')
            );

            EvaluacionProveedor::query()

                ->where(
                    'id_orden_servicio',
                    $orden->id
                )

                ->delete();

            RequisicionObra::query()

                ->where(
                    'id_estacion',
                    $this->estacionModulo()
                )

                ->where(
                    'no_folio',
                    $orden->folio
                )

                ->delete();

            $orden->delete();

            JsonResponse::success(
                'Orden eliminada.'
            );
        } catch (\Throwable $e) {

            JsonResponse::error(
                'No fue posible eliminar la orden.'
            );
        }
    }

    public function detalleCompleto(
        int $id
    ): void {

        $estacion = Estacion::findOrFail(
            $this->estacionModulo()
        );

        $orden = OrdenServicio::query()

            ->with('solicitante.puesto')

            ->findOrFail($id);

        JsonResponse::custom([

            'success' => true,

            'data' => [

                'fecha' => formatearFecha(optional(
                    $orden->fecha
                )?->format('Y-m-d')),

                'hora' => $orden->hora,

                'solicitante' => $orden->solicitante->nombre,

                'puesto' => $orden->solicitante->puesto->tipo_puesto,

                'razon_social' => $estacion->razonsocial,

                'rfc' => $estacion->rfc,

                'direccion' => $estacion->direccioncompleta,

                'descripcion' => $orden->descripcion,

                'justificacion' => $orden->justificacion

            ]

        ]);
    }

    public function pdfOrdenServicio(int $id): void
    {
        $estacion = Estacion::findOrFail(
            $this->estacionModulo()
        );

        $orden = OrdenServicio::query()
            ->with([
                'solicitante.puesto',
                'realizadoPor'
            ])
            ->findOrFail($id);

        $hora = Carbon::createFromFormat(
            'H:i:s',
            $orden->hora
        )->format('g:i a');

        $css = file_get_contents(
            'assets/css/pdf.css'
        );

        $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Orden de servicio</title>

    <style>
    ' . $css . '
    </style>
    </head>

    <body>';

        $html .= '
    <table class="table table-bordered">
        <tr>

            <td rowspan="2" class="text-center">
                ' . $estacion->razonsocial . '
            </td>

            <td rowspan="2" class="text-center">
                <b>Orden de servicio</b>
            </td>

            <td class="text-center">
                <b>Fecha de autorización: 01-01-2024</b>
            </td>

        </tr>

        <tr>

            <td class="text-center">
                Fo.SGM.012
            </td>

        </tr>

        <tr>

            <td class="text-center">
                Realizado por:<br>
                ' . optional($orden->realizadoPor)->nombre . '
            </td>

            <td class="text-center">
                Revisado por:<br>
                Eduardo Galicia Flores
            </td>

            <td class="text-center">
                Autorizado por:<br>
                ' . $estacion->apoderado_legal . '
            </td>

        </tr>

    </table>
    ';

        $html .= '

    <table class="table table-bordered">

        <tr>
            <td width="30%"><b>Fecha</b></td>
            <td>' . formatearFecha(optional($orden->fecha)->format('Y-m-d')) . '</td>
        </tr>

        <tr>
            <td><b>Hora</b></td>
            <td>' . $hora . '</td>
        </tr>

        <tr>
            <td><b>Nombre del solicitante</b></td>
            <td>' . optional($orden->solicitante)->nombre . '</td>
        </tr>

        <tr>
            <td><b>Puesto</b></td>
            <td>' . optional($orden->solicitante?->puesto)->tipo_puesto . '</td>
        </tr>

        <tr>
            <td><b>Razón Social</b></td>
            <td>' . $estacion->razonsocial . '</td>
        </tr>

        <tr>
            <td><b>RFC</b></td>
            <td>' . $estacion->rfc . '</td>
        </tr>

        <tr>
            <td><b>Dirección</b></td>
            <td>' . $estacion->direccioncompleta . '</td>
        </tr>

    </table>

    <div class="mt-3 mb-2">
        <b>Descripción detallada del servicio o equipo que requiere:</b>
    </div>

    <div class="border mt-3 p-3">
        ' . $orden->descripcion . '
    </div>

    <div class="mt-3 mb-2">
        <b>Justificación del servicio que requiere:</b>
    </div>

    <div class="border mt-3 p-3">
        ' . $orden->justificacion . '
    </div>

    </body>
    </html>';

        $pdf = new Dompdf();

        $pdf->loadHtml($html);

        $pdf->setPaper(
            'A4',
            'portrait'
        );

        $pdf->render();

        $pdf->stream(
            'Orden de servicio.pdf',
            ['Attachment' => false]
        );
    }

    public function detalleEvaluacion(
        int $idOrden
    ): void {

        $orden = OrdenServicio::query()

            ->with([
                'evaluacion',
            ])

            ->findOrFail($idOrden);

        if (!$orden->evaluacion) {


            $orden->evaluacion()->create([

                'fecha' => date('Y-m-d'),
                'hora_inicio' => date('H:i:s'),
                'hora_termino' => date('H:i:s'),

                'nombre_proveedor' => '',
                'no_acreditacion' => '',
                'observaciones' => '',

                'id_personal_evaluacion' => $this->userId(),

                'respuesta_1' => 2,
                'respuesta_2' => 2,
                'respuesta_3' => 2,
                'respuesta_4' => 2,
                'respuesta_5' => 2,

                'estado' => 0,
            ]);

            $orden->load('evaluacion');
        }

        $usuarios = Usuario::query()

            ->where(
                'id_gas',
                $this->estacionModulo()
            )

            ->where(
                'estatus',
                0
            )

            ->select(
                'id',
                'nombre'
            )

            ->get();

        JsonResponse::custom([

            'success' => true,

            'data' => [

                'evaluacion' => [

                    'id' => $orden->evaluacion->id,

                    'fecha' => optional($orden->evaluacion->fecha)
                        ?->format('Y-m-d'),

                    'hora_inicio' => $orden->evaluacion->hora_inicio,

                    'hora_termino' => $orden->evaluacion->hora_termino,

                    'nombre_proveedor' => $orden->evaluacion->nombre_proveedor,

                    'no_acreditacion' => $orden->evaluacion->no_acreditacion,

                    'observaciones' => $orden->evaluacion->observaciones,

                    'id_personal_evaluacion' => $orden->evaluacion->id_personal_evaluacion,

                    'respuesta_1' => $orden->evaluacion->respuesta_1,
                    'respuesta_2' => $orden->evaluacion->respuesta_2,
                    'respuesta_3' => $orden->evaluacion->respuesta_3,
                    'respuesta_4' => $orden->evaluacion->respuesta_4,
                    'respuesta_5' => $orden->evaluacion->respuesta_5,

                    'estado' => $orden->evaluacion->estado,

                ],

                'usuarios' => $usuarios,

            ]

        ]);
    }

    public function updateEvaluacion(): void
    {
        $evaluacion = EvaluacionProveedor::findOrFail(

            Request::input('id')

        );

        $evaluacion->update([

            'fecha' => Request::input('fecha'),

            'hora_inicio' => Request::input('hora_inicio'),

            'hora_termino' => Request::input('hora_termino'),

            'nombre_proveedor' => Request::input('nombre_proveedor'),

            'no_acreditacion' => Request::input('no_acreditacion'),

            'observaciones' => Request::input('observaciones'),

            'id_personal_evaluacion' => Request::input('id_personal_evaluacion'),

            'respuesta_1' => Request::input('respuesta_1'),

            'respuesta_2' => Request::input('respuesta_2'),

            'respuesta_3' => Request::input('respuesta_3'),

            'respuesta_4' => Request::input('respuesta_4'),

            'respuesta_5' => Request::input('respuesta_5'),

            'estado' => 1

        ]);

        JsonResponse::success(
            'Evaluación actualizada.'
        );
    }

    public function detalleCompletoEvaluacion(
        int $idOrden
    ): void {

        $orden = OrdenServicio::query()

            ->with([
                'evaluacion.personalEvaluacion.puesto'
            ])

            ->findOrFail($idOrden);

        $evaluacion = $orden->evaluacion;

        if (!$evaluacion) {

            JsonResponse::custom([
                'success' => false,
                'message' => 'La orden no cuenta con una evaluación.'
            ]);

            return;
        }

        $evaluador = $evaluacion->personalEvaluacion;

        JsonResponse::custom([

            'success' => true,

            'data' => [

                'descripcion' => $orden->descripcion,

                'fecha' => formatearFecha(optional($evaluacion->fecha)?->format('Y-m-d')),

                'hora_inicio' => $evaluacion->hora_inicio,

                'hora_termino' => $evaluacion->hora_termino,

                'nombre_proveedor' => $evaluacion->nombre_proveedor,

                'no_acreditacion' => $evaluacion->no_acreditacion,

                'respuesta_1' => $evaluacion->respuesta_1 == 1,
                'respuesta_2' => $evaluacion->respuesta_2 == 1,
                'respuesta_3' => $evaluacion->respuesta_3 == 1,
                'respuesta_4' => $evaluacion->respuesta_4 == 1,
                'respuesta_5' => $evaluacion->respuesta_5 == 1,

                'observaciones' => $evaluacion->observaciones,

                'usuario' => optional($evaluador)->nombre,

                'puesto' => optional($evaluador?->puesto)->tipo_puesto,

            ]

        ]);
    }

    public function pdfEvaluacion(int $idOrden)
    {
        $estacion = Estacion::findOrFail(
            $this->estacionModulo()
        );

        $orden = OrdenServicio::query()

            ->with([
                'realizadoPor',
                'evaluacion.personalEvaluacion.puesto'
            ])

            ->findOrFail($idOrden);

        $evaluacion = $orden->evaluacion;



        $horaInicio = Carbon::createFromFormat(
            'H:i:s',
            $evaluacion->hora_inicio
        )->format('g:i a');

        $horaTermino = Carbon::createFromFormat(
            'H:i:s',
            $evaluacion->hora_termino
        )->format('g:i a');

        $resultado = (
            $evaluacion->respuesta_1 +
            $evaluacion->respuesta_2 +
            $evaluacion->respuesta_3 +
            $evaluacion->respuesta_4 +
            $evaluacion->respuesta_5
        ) / 5 * 100;

        $css = file_get_contents(
            'assets/css/pdf.css'
        );

        $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Orden de servicio</title>

    <style>
    ' . $css . '
    </style>
    </head>

    <body>';

        $html .= '
    <table class="table table-bordered">
        <tr>

            <td rowspan="2" class="text-center">
                ' . $estacion->razonsocial . '
            </td>

            <td rowspan="2" class="text-center">
                <b>Orden de servicio</b>
            </td>

            <td class="text-center">
                <b>Fecha de autorización: 01-01-2024</b>
            </td>

        </tr>

        <tr>

            <td class="text-center">
                Fo.SGM.012
            </td>

        </tr>

        <tr>

            <td class="text-center">
                Realizado por:<br>
                ' . optional($orden->realizadoPor)->nombre . '
            </td>

            <td class="text-center">
                Revisado por:<br>
                Eduardo Galicia Flores
            </td>

            <td class="text-center">
                Autorizado por:<br>
                ' . $estacion->apoderado_legal . '
            </td>

        </tr>

    </table>
    ';
        $html .= '

    <table class="table">

        <tbody>

            <tr>
                <td><b>Trabajo realizado o producto adquirido:</b></td>
                <td>' . $orden->descripcion . '</td>
            </tr>

            <tr>
                <td><b>Fecha de ejecución del servicio:</b></td>
                <td>' . formatearFecha(optional($evaluacion->fecha)?->format('Y-m-d')) . '</td>
            </tr>

            <tr>
                <td><b>Hora de inicio del servicio:</b></td>
                <td>' . $horaInicio . '</td>
            </tr>

            <tr>
                <td><b>Hora de culminación del servicio:</b></td>
                <td>' . $horaTermino . '</td>
            </tr>

            <tr>
                <td><b>Nombre del proveedor o prestador de servicio:</b></td>
                <td>' . $evaluacion->nombre_proveedor . '</td>
            </tr>

            <tr>
                <td><b>No de acreditación o aprobación:</b></td>
                <td>' . $evaluacion->no_acreditacion . '</td>
            </tr>

        </tbody>

    </table>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th width="40">No</th>
                <th>Aspecto a evaluar</th>
                <th width="90">Respuesta</th>
            </tr>
        </thead>
        <tbody>

            ' . $this->filaEvaluacion(
            1,
            'El trabajo fue ejecutado conforme a lo solicitado',
            $evaluacion->respuesta_1
        ) . '

            ' . $this->filaEvaluacion(
            2,
            'Se verificó que el proveedor contara con procedimientos para ejecutar los trabajos',
            $evaluacion->respuesta_2
        ) . '

            ' . $this->filaEvaluacion(
            3,
            'Mientras el personal permaneció en las instalaciones ocupó EPP',
            $evaluacion->respuesta_3
        ) . '

            ' . $this->filaEvaluacion(
            4,
            'Los trabajos ejecutados tomaron en cuenta los procedimientos de seguridad',
            $evaluacion->respuesta_4
        ) . '

            ' . $this->filaEvaluacion(
            5,
            'Al culminar el trabajo se encuentra a entera satisfacción',
            $evaluacion->respuesta_5
        ) . '

            <tr>

                <td colspan="2" align="right">

                    <b>Resultado</b>

                </td>

                <td align="center">

                    <b>' . number_format($resultado, 0) . ' %</b>

                </td>

            </tr>

        </tbody>

    </table>
    </br>

    <h4>Observaciones:</h4>

    <div class="border p-3">

        ' . $evaluacion->observaciones . '

    </div>

    <table class="table mt-3">

        <tr>

            <td width="60%"><b>Nombre de quien realiza la evaluación:</b></td>

            <td>' . $evaluacion->personalEvaluacion?->nombre . '</td>

        </tr>

        <tr>

            <td><b>Puesto:</b></td>

            <td>' . $evaluacion->personalEvaluacion?->puesto?->tipo_puesto . '</td>

        </tr>

    </table>
    </body>
    </html>
    ';

        $pdf = new Dompdf();

        $pdf->loadHtml($html);

        $pdf->setPaper(
            'A4',
            'portrait'
        );

        $pdf->render();

        $pdf->stream(
            'Evaluacion de proveedores.pdf',
            ['Attachment' => false]
        );
    }



    private function filaEvaluacion(
        int $numero,
        string $texto,
        int $respuesta
    ): string {

        return sprintf(
            '
        <tr>
            <td align="center">%d</td>
            <td>%s</td>
            <td align="center"><b>%s</b></td>
        </tr>
        ',
            $numero,
            $texto,
            $respuesta === 1 ? 'SI' : 'NO'
        );
    }
}

