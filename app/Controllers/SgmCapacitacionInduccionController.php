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
use App\Models\Sasisopa\CursoCalendario;

use Dompdf\Dompdf;
use Dompdf\Options;

class SgmCapacitacionInduccionController extends BaseController
{
    protected string $modulo = 'sgm';

    private function estacionModulo(): ?int
    {
        return ModuleStationService::getContext('sgm')['id_estacion'] ?? null;
    }

    public function index()
    {
        $title = 'Capacitación de inducción';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add('6. Gestion de los Recursos', '/sgm/gestion-recursos');
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
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/core/module-station-selector.js?v=' . time(),
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/sgm/gestion-recursos/capacitacioninduccion.actions.init.js?v=1.0.1',
                '/js/sgm/gestion-recursos/capacitacioninduccion.datatable.init.js?v=1.0.1',

            ],
            'help' => true
        ];

        View::render('sgm/gestion-recursos/capacitacion-induccion', $data, 'sgm');
    }

    public function datatable(): void
    {
        $data = CursoCalendario::with([
            'tema',
            'usuario'
        ])
            ->where(
                'id_estacion',
                $this->estacionModulo()
            )
            ->where('observaciones', 'Inducción')
            ->whereHas('tema', function ($query) {

                $query->where(
                    'categoria',
                    'SGM'
                );
            })
            ->orderBy(
                'fecha_programada',
                'asc'
            )
            ->get()
            ->map(function ($curso) {

                $resultado = (int)$curso->resultado;


                if ($resultado == 0) {

                    $evaluacion = [
                        'texto' => 'Pendiente',
                        'clase' => 'text-danger',
                        'pdf' => false
                    ];
                } elseif ($resultado >= 90) {

                    $evaluacion = [
                        'texto' => $resultado . ' (Excelente)',
                        'clase' => 'text-success',
                        'pdf' => true
                    ];
                } elseif ($resultado >= 80) {

                    $evaluacion = [
                        'texto' => $resultado . ' (Bueno)',
                        'clase' => 'text-primary',
                        'pdf' => true
                    ];
                } elseif ($resultado >= 60) {

                    $evaluacion = [
                        'texto' => $resultado . ' (Regular)',
                        'clase' => 'text-warning',
                        'pdf' => true
                    ];
                } else {

                    $evaluacion = [
                        'texto' => $resultado . ' (Malo)',
                        'clase' => 'text-danger',
                        'pdf' => false
                    ];
                }



                return [

                    'id' => $curso->id,

                    'curso' => $curso->tema?->titulo,

                    'tipo' => 'Interna',

                    'fecha_programada' => $curso->fecha_programada->format('Y-m-d'),

                    'duracion' => '30 minutos',

                    'instructor' => 'AdmonGas',

                    'fecha_real' => $curso->fecha_real->format('Y-m-d') ?? 'S/I',

                    'usuario' => $curso->usuario?->nombre,
                    'fecha_ingreso' => $curso->usuario?->fecha_ingreso,

                    'resultado' => $evaluacion,

                ];
            });



        JsonResponse::custom(
            ["data" => $data]
        );
    }

    public function pdf()
    {
        $realizadoPor = Usuario::query()
            ->join(
                'sgm_autorizado',
                'sgm_autorizado.id_usuario',
                '=',
                'tb_usuarios.id'
            )
            ->where('tb_usuarios.id_gas', $this->estacionModulo())
            ->where('sgm_autorizado.estado', 1)
            ->value('tb_usuarios.nombre');

        $estacion = Estacion::findOrFail(
            $this->estacionModulo()
        );

        $cursos = CursoCalendario::with([
            'tema',
            'usuario'
        ])
            ->where('id_estacion', $this->estacionModulo())
            ->where('observaciones', 'Inducción')
            ->whereHas('tema', function ($query) {
                $query->where('categoria', 'SGM');
            })
            ->orderBy('fecha_programada')
            ->get();

        $css = file_get_contents(
            PUBLIC_PATH . '/assets/css/pdf.css'
        );

        $html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>' . $css . '</style>
</head>
<body>

<table class="table table-bordered">

    <tr>

        <td rowspan="2" align="center">
            ' . $estacion->razonsocial . '
        </td>

        <td rowspan="2" align="center">
            <b>Capacitación de inducción</b>
        </td>

        <td align="center">
            <b>Fecha de autorización: 01-01-2024</b>
        </td>

    </tr>

    <tr>

        <td align="center">
            Fo.SGM.009
        </td>

    </tr>

    <tr>

        <td align="center">
            Realizado por:<br>
            ' . $realizadoPor . '
        </td>

        <td align="center">
            Revisado por:<br>
            Eduardo Galicia Flores
        </td>

        <td align="center">
            Autorizado por:<br>
            ' . $estacion->apoderado_legal . '
        </td>

    </tr>

</table>

<br>

<table class="table table-bordered table-sm">

<thead>

<tr>

<th>No</th>

<th>Nombre</th>

<th>Fecha de ingreso</th>

<th>Nombre del curso de inducción</th>

<th>Curso impartido por</th>

<th>Fecha de la toma del curso</th>

<th>Evidencia</th>

</tr>

</thead>

<tbody>
';

        foreach ($cursos as $index => $curso) {

            $pdf = 'S/I';

            if ($curso->resultado >= 60) {

                $pdf = '<a target="_blank" href="' .
                    base_url() .
                    '/sgm/gestion-recursos/programa-capacitacion-interna/reconocimiento/' .
                    $curso->id .
                    '">Descargar</a>';
            }

            $html .= '

<tr>

<td align="center">' . ($index + 1) . '</td>

<td>' . ($curso->usuario?->nombre ?? '') . '</td>

<td align="center">'
                . ($curso->usuario?->fecha_ingreso
                    ? formatearFecha($curso->usuario->fecha_ingreso)
                    : 'S/I') .
                '</td>

<td>' . ($curso->tema?->titulo ?? '') . '</td>

<td align="center">
Interno
</td>

<td align="center">
' . formatearFecha($curso->fecha_programada) . '
</td>

<td align="center">
' . $pdf . '
</td>

</tr>';
        }

        if ($cursos->isEmpty()) {

            $html .= '

<tr>

<td colspan="7" align="center">

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

        $options->setIsHtml5ParserEnabled(true);
        $options->setIsRemoteEnabled(false);
        $options->setChroot(PUBLIC_PATH);
        $options->setDefaultFont('Arial');

        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($html);

        $dompdf->setPaper(
            'A4',
            'landscape'
        );

        $dompdf->render();

        $dompdf->stream(
            'Capacitación de inducción.pdf',
            [
                'Attachment' => false
            ]
        );
    }
}

