<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Request;
use App\Core\JsonResponse;
use App\Services\ModuloService;

use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Sasisopa\CursoCalendario;

use FPDF;
use Dompdf\Dompdf;
use Dompdf\Options;

class SgmCapacitacionInternaController extends BaseController
{

    protected string $modulo = 'sgm';

    public function index()
    {
        $title = 'Programa Capacitacion Interna';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add('6. Gestion de los Recursos', '/sgm/gestion-recursos');
        Breadcrumb::add($title, '');
        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/sgm/gestion-recursos/capacitacioninterna.actions.init.js?v=1.0.1',
                '/js/sgm/gestion-recursos/capacitacioninterna.datatable.init.js?v=1.0.1',

            ],
            'help' => true
        ];

        View::render('sgm/gestion-recursos/capacitacion-interna', $data, 'sgm');
    }

    public function datatable(int $year)
    {
        $data = CursoCalendario::with([
            'tema',
            'usuario'
        ])
            ->where(
                'id_estacion',
                $this->estacionId()
            )
            ->whereHas('tema', function ($query) {

                $query->where(
                    'categoria',
                    'SGM'
                );
            })
            ->whereYear(
                'fecha_programada',
                $year
            )
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

                    'resultado' => $evaluacion,

                ];
            });



        JsonResponse::custom(
            ["data" => $data]
        );
    }

    public function reconocimiento(int $id)
    {
        $pdf = new FPDF();

        $estacion = Estacion::findOrFail($this->estacionId());

        // ======================
        // OBTENER DATOS
        // ======================
        $cal = CursoCalendario::with([
            'tema.modulo',
            'usuario'
        ])->findOrFail($id);

        $titulo = $cal->tema->titulo;
        $modulo = $cal->tema->modulo->titulo ?? '';
        $fecha = $cal->fecha_programada;
        $nombre = $cal->usuario->nombre;

        $observacion = $cal->observaciones
            ? ' (' . $cal->observaciones . ')'
            : '';

        // ======================
        // PDF
        // ======================
        $pdf = new FPDF('L', 'mm', 'A4');
        $pdf->AddPage();

        // Fondo
        $pdf->Image(
            PUBLIC_PATH . '/assets/images/cursos/fondo-sgm.jpg',
            0,
            0,
            300,
            210
        );

        // Helper encoding
        $txt = fn($t) => mb_convert_encoding($t, 'ISO-8859-1', 'UTF-8');

        // ======================
        // NOMBRE
        // ======================
        $pdf->SetFont('Arial', '', 30);
        $pdf->SetY(100);
        $pdf->Cell(0, 10, $txt($nombre), 0, 0, 'C');

        // ======================
        // TEMA
        // ======================
        $pdf->SetY(133);

        $pdf->SetFont('Arial', '', 17);
        $pdf->SetX(70);
        $pdf->SetMargins(68, 0);

        $pdf->MultiCell(
            0,
            6,
            $txt($titulo . $observacion),
            0,
            'C'
        );

        // ======================
        // FECHA
        // ======================
        $pdf->SetY(160);   // Siempre en la misma posición
        $pdf->SetX(77);

        $pdf->SetFont('Arial', '', 12);

        $pdf->Cell(
            0,
            10,
            $txt(formatearFecha($fecha)),
            0,
            0
        );

        if (isset($estacion->firma)) {
            $extension = pathinfo($estacion->firma, PATHINFO_EXTENSION);
            $pdf->Image(PUBLIC_PATH . '/uploads/firma-personal/' . $estacion->firma, '175', '151', '50', '0', $extension);
        }

        // ======================
        // OUTPUT
        // ======================
        return $pdf->Output('I', 'reconocimiento.pdf');
    }

    public function pdf(int $year)
    {

        $realizadoPor = Usuario::select('tb_usuarios.nombre')
            ->join('sgm_autorizado', 'sgm_autorizado.id_usuario', '=', 'tb_usuarios.id')
            ->where('tb_usuarios.id_gas', $this->estacionId())
            ->where('sgm_autorizado.estado', 1)
            ->value('nombre');

        $estacion = Estacion::findOrFail($this->estacionId());

        $cursos = CursoCalendario::with([
            'tema',
            'usuario'
        ])
            ->where('id_estacion', $this->estacionId())
            ->whereYear('fecha_programada', $year)
            ->whereHas('tema', function ($q) {
                $q->where('categoria', 'SGM');
            })
            ->orderBy('fecha_programada')
            ->get();

        $css = file_get_contents(
            'assets/css/pdf.css'
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
                <b>Programa anual de capacitación interna y externa</b>
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

    <table class="table table-bordered">

        <thead>

            <tr>

                <th>No</th>

                <th>Nombre del curso</th>

                <th>Tipo</th>

                <th>Fecha programada</th>

                <th>Duración</th>

                <th>Instructor</th>

                <th>Fecha real</th>

                <th>Participante</th>

                <th>Evidencia</th>

            </tr>

        </thead>

        <tbody>
    ';

        foreach ($cursos as $index => $curso) {

            $fechaReal = $curso->fecha_real
                ? formatearFecha($curso->fecha_real)
                : 'S/I';

            $pdf = 'S/I';

            if ($curso->resultado >= 60) {

                $pdf = '<a href="' .
                    base_url() .
                    '/sgm/gestion-recursos/programa-capacitacion-interna/reconocimiento/' .
                    $curso->id .
                    '" target="_blank" download>Descargar</a>';
            }

            $html .= '

        <tr>

            <td align="center">' . ($index + 1) . '</td>

            <td>' . $curso->tema?->titulo . '</td>

            <td align="center">
                Interna
            </td>

            <td align="center">
                ' . formatearFecha($curso->fecha_programada) . '
            </td>

            <td align="center">
                30 minutos
            </td>

            <td align="center">
                AdmonGas
            </td>

            <td align="center">
                ' . $fechaReal . '
            </td>

            <td align="center">
                ' . $curso->usuario?->nombre . '
            </td>

            <td align="center">
                ' . $pdf . '
            </td>

        </tr>';
        }

        if ($cursos->isEmpty()) {

            $html .= '

        <tr>

            <td colspan="9" align="center">
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
        $options->setChroot(ROOT_PATH . '/public');
        $options->setDefaultFont('Arial');

        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $dompdf->stream(
            'Programa anual de capacitación interna y externa.pdf',
            ['Attachment' => false]
        );
    }

    public function reconocimientoYear(int $year)
    {
        $idEstacion = $this->estacionId();
        $estacion = Estacion::findOrFail($idEstacion);

        // ======================
        // HELPER TEXTO
        // ======================
        $safeText = function ($text) {
            if (!$text) return '';

            $text = trim($text);
            $text = strip_tags($text);
            $text = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $text);

            return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
        };

        // ======================
        // CONSULTA (IGUAL QUE TU SQL)
        // ======================
        $calendarios = CursoCalendario::with([
            'tema.modulo',
            'usuario'
        ])
            ->whereYear('fecha_programada', $year)
            ->where('id_estacion', $idEstacion)
            ->where('resultado', '>=', 60)
            ->whereHas('tema', function ($q) {
                $q->where('categoria', 'SGM');
            })
            ->orderBy('fecha_programada', 'desc')
            ->get();

        // ======================
        // PDF
        // ======================
        $pdf = new FPDF('L', 'mm', 'A4');

        foreach ($calendarios as $cal) {

            if (!$cal->usuario || !$cal->tema) continue;

            $nombre = $safeText($cal->usuario->nombre);
            $titulo = $safeText($cal->tema->titulo);

            // ⚠️ IMPORTANTE: primero formateas, luego conviertes
            $fechaFormateada = formatearFecha($cal->fecha_programada);
            $fecha = $safeText($fechaFormateada);

            $observacion = $cal->observaciones
                ? ' (' . $safeText($cal->observaciones) . ')'
                : '';

            // ======================
            // HOJA
            // ======================
            $pdf->AddPage();

            $pdf->SetTitle(substr($titulo, 0, 100));

            $pdf->Image(
                asset('images/cursos/fondo-sgm.jpg'),
                0,
                0,
                300,
                210
            );

            // ======================
            // NOMBRE
            // ======================
            $pdf->SetFont('Arial', '', 30);
            $pdf->SetY(100);
            $pdf->Cell(0, 10, $nombre, 0, 0, 'C');

            // ======================
            // TÍTULO
            // ======================
            $pdf->SetFont('Arial', '', 17);
            $pdf->SetXY(68, 135);

            $pdf->MultiCell(
                160,
                6,
                $titulo . $observacion,
                0,
                'C'
            );

            // ======================
            // FECHA
            // ======================
            $pdf->SetFont('Arial', '', 12);
            $pdf->SetXY(50, 165);

            $pdf->Cell(
                100,
                0,
                $fecha,
                0,
                0,
                'C'
            );

            if (isset($estacion->firma)) {
                $extension = pathinfo($estacion->firma, PATHINFO_EXTENSION);
                $pdf->Image(PUBLIC_PATH . '/uploads/firma-personal/' . $estacion->firma, '175', '151', '50', '0', $extension);
            }
        }



        return $pdf->Output(
            'I',
            "reconocimientos_sgm_{$year}.pdf"
        );
    }
}
