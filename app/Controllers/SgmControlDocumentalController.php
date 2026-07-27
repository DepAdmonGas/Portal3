<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Sgm\Documento;
use App\Models\Estacion;
use App\Models\Usuario;

use Dompdf\Dompdf;
use Dompdf\Options;

class SgmControlDocumentalController extends BaseController
{

    protected string $modulo = 'sgm';

    public function index()
    {

        $title = '2. Control del documental del Sistema de Gestion de medición';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add($title, '');
        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/libs/select2/dist/css/select2.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/libs/select2/dist/js/select2.full.min.js',
                '/libs/select2/dist/js/select2.min.js',

                '/js/asistencia/listaasistencia.actions.init.js?v=1.0.1',
                '/js/sgm/revision/index.action.init.js?v=1.0.1',

                '/js/asistencia/listaasistencia.datatable.init.js?v=1.0.1',
                '/js/sgm/revision/index.datatable.init.js?v=1.0.1',

                '/js/sgm/control-documental/index.actions.init.js?v=1.0.1',

            ],
            'help' => true
        ];

        View::render('sgm/control-documental/index', $data, 'sgm');
    }

    public function documentos()
    {
        $documentos = Documento::with([
            'archivos' => function ($query) {

                $query->where('id_estacion', $this->estacionId())
                    ->latest('fecha');
            }
        ])
            ->orderBy('seccion')
            ->orderBy('id')
            ->get();

        echo json_encode(

            $documentos->map(function ($documento) {

                $archivo = $documento->archivos->first();

                return [

                    'id' => $documento->id,
                    'seccion' => $documento->seccion,
                    'codificacion' => $documento->codificacion,
                    'nombre' => $documento->nombre,
                    'fecha_aprobacion' => formatearFecha(optional($documento->fecha_aprobacion)
                        ?->format('Y-m-d')),

                    'archivo' => $archivo?->archivo,

                    'url' => $archivo
                        ? '/uploads/archivos/FormatosSGM/' . $archivo->archivo
                        : null

                ];
            })

        );
    }

    public function pdf()
    {
        header('Content-Type: application/pdf');

        $estacion = Estacion::findOrFail($this->estacionId());

        $realizadoPor = Usuario::query()
            ->select('tb_usuarios.nombre')
            ->join('sgm_autorizado', 'sgm_autorizado.id_usuario', '=', 'tb_usuarios.id')
            ->where('tb_usuarios.id_gas', $this->estacionId())
            ->where('sgm_autorizado.estado', 1)
            ->value('nombre') ?? 'S/I';

        $documentos = Documento::orderBy('seccion')
            ->orderBy('id')
            ->get()
            ->groupBy('seccion');

        $css = file_get_contents(
            'assets/css/pdf.css'
        );

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Control documental del SGM</title>
            <link rel="stylesheet" href="' . $_ENV['APP_URL'] . '/assets/css/pdf.css">
            <style>
            ' . $css . '
            </style>

        </head>

        <body>

        <table class="table table-bordered">
            <tr>
                <td rowspan="2" class="text-center align-middle">
                    ' . $estacion->razonsocial . '
                </td>

                <td rowspan="2" class="text-center align-middle">
                    <strong>Control documental del SGM</strong>
                </td>

                <td class="text-center align-middle">
                    <strong>Fecha de autorización: 01-01-2024</strong>
                </td>
            </tr>

            <tr>
                <td class="text-center align-middle">
                    Fo.SGM.003
                </td>
            </tr>

            <tr>
                <td class="text-center align-middle">
                    Realizado por:<br>' . $realizadoPor . '
                </td>

                <td class="text-center align-middle">
                    Revisado por:<br>Eduardo Galicia Flores
                </td>

                <td class="text-center align-middle">
                    Autorizado por:<br>' . $estacion->apoderado_legal . '
                </td>
            </tr>

        </table>';

        $html .= '
<table class="table table-sm table-bordered">
    <tbody>
        <tr class="table-secondary text-center">
            <td colspan="3"><b>Sistema de Gestión de Medición</b></td>
        </tr>

        <tr>
            <td><b>Codificación</b></td>
            <td><b>Nombre</b></td>
            <td><b>Fecha de aprobación</b></td>
        </tr>';

        foreach ($documentos->get(3, collect()) as $documento) {

            $codificacion = $documento->codificacion ?: 'SGM.001';
            $nombre = $documento->nombre ?: 'Sin nombre';

            $html .= "
        <tr>
            <td>{$codificacion}</td>
            <td>{$nombre}</td>
            <td>01/01/2024</td>
        </tr>";
        }

        $html .= '
    </tbody>
</table>';

        $html .= '
<table class="table table-sm table-bordered">
    <tbody>

        <tr class="table-secondary text-center">
            <td colspan="3">
                <b>Manual de procedimientos del Sistema de Gestión de Medición</b>
            </td>
        </tr>

        <tr>
            <td><b>Codificación</b></td>
            <td><b>Nombre</b></td>
            <td><b>Fecha de aprobación</b></td>
        </tr>';

        foreach ($documentos->get(1, collect()) as $documento) {

            $html .= '
        <tr>
            <td>' . htmlspecialchars($documento->codificacion) . '</td>
            <td>' . htmlspecialchars($documento->nombre) . '</td>
            <td>' . $documento->fecha_aprobacion->format('d/m/Y') . '</td>
        </tr>';
        }

        $html .= '
    </tbody>
</table>';

        $html .= '
<table class="table table-sm table-bordered">
    <tbody>

        <tr class="table-secondary text-center">
            <td colspan="3">
                <b>Formatos del Sistema de Gestión de Medición</b>
            </td>
        </tr>

        <tr>
            <td><b>Codificación</b></td>
            <td><b>Nombre</b></td>
            <td><b>Fecha de aprobación</b></td>
        </tr>';

        foreach ($documentos->get(2, collect()) as $documento) {

            $html .= '
        <tr>
            <td>' . htmlspecialchars($documento->codificacion) . '</td>
            <td>' . htmlspecialchars($documento->nombre) . '</td>
            <td>' . $documento->fecha_aprobacion->format('d/m/Y') . '</td>
        </tr>';
        }

        $html .= '
    </tbody>
</table>';

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

        $dompdf->stream(
            'Control documental del SGM.pdf',
            ['Attachment' => true]
        );

        exit;
    }
}
