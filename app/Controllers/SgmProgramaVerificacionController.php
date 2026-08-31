<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\JsonResponse;
use App\Services\ModuleStationService;

use App\Models\Estacion;
use App\Models\Sgm\Autorizado;
use App\Models\Sgm\ProgramaAnualCalibracionVerificacion;

use Dompdf\Dompdf;
use Dompdf\Options;

class SgmProgramaVerificacionController extends BaseController
{

    private function estacionModulo(): ?int
    {
        return ModuleStationService::getContext('sgm')['id_estacion'] ?? null;
    }

    public function deleteProgramacionAnualVerificacion()
    {
        $id = (int) Request::jsonInput('id');

        ProgramaAnualCalibracionVerificacion::where(
            'id_estacion',
            $this->estacionModulo()
        )
            ->findOrFail($id)
            ->delete();

        JsonResponse::success('Programa anual de verificación eliminado');
    }

    public function pdfProgramacionAnualVerificacion(
        int $year,
        int $formato
    ) {
        header('Content-Type: application/pdf');

        $estacion = Estacion::findOrFail(
            $this->estacionModulo()
        );

        $realizadoPor = 'S/I';

        $realizadoPor = Autorizado::query()
            ->where('estado', 1)
            ->whereHas('usuario', function ($q) {
                $q->where('id_gas', $this->estacionModulo());
            })
            ->with('usuario:id,nombre')
            ->first()
            ?->usuario
            ?->nombre ?? 'S/I';


        if ($formato === 14) {

            $numeroFormato = 'SGM.Fo.014';

            $nombreFormato = 'Programa anual de calibración de patrones e instrumentos de medida';

            $categorias = [
                'Instrumentos de medida',
                'Patrones de medida'
            ];
        } else {

            $numeroFormato = 'SGM.Fo.015';

            $nombreFormato = 'Programa anual de verificación de equipos de medición';

            $categorias = [
                'Equipo sometido a verificación'
            ];
        }

        $css = file_get_contents(
            'assets/css/pdf.css'
        );

        $html = '
    <!DOCTYPE html>
    <html lang="es">
    <head>
    <meta charset="UTF-8">
    <title>' . $nombreFormato . '</title>
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
    <strong>' . $nombreFormato . '</strong>
    </td>
    <td class="text-center align-middle">
    <strong>Fecha de autorización: 01-01-2024</strong>
    </td>
    </tr>
    <tr>
    <td class="text-center align-middle">
    ' . $numeroFormato . '
    </td>
    </tr>
    <tr>
    <td class="text-center align-middle">
    Realizado por:<br>
    ' . $realizadoPor . '
    </td>
    <td class="text-center align-middle">
    Revisado por:<br>
    Eduardo Galicia Flores
    </td>
    <td class="text-center align-middle">
    Autorizado por:<br>
    ' . $estacion->apoderado_legal . '
    </td>
    </tr>
    </table>
    <br>
    <table class="table table-bordered table-sm">
    ';

        foreach ($categorias as $categoria) {
            $programas = ProgramaAnualCalibracionVerificacion::query()

                ->with([
                    'equipo:id,nombre,periodicidad,categoria',
                    'verificar:id,identificacion'
                ])

                ->where('id_estacion', $this->estacionModulo())
                ->whereYear('fecha', $year)
                ->whereHas('equipo', function ($query) use ($categoria) {
                    $query->where(
                        'categoria',
                        $categoria
                    );
                })

                ->orderBy('fecha')
                ->get();

            $html .= '
            <tbody>
            <tr class="table-secondary">
            <td>
            <strong>' . $categoria . '</strong>
            </td>
            <td>
            <strong>Periodicidad</strong>
            </td>
            <td>
            <strong>Fechas programadas</strong>
            </td>
            </tr>
            ';

            if ($programas->isEmpty()) {

                $html .= '

            <tr>
            <td colspan="3" class="text-center">
            No se encontró información para mostrar
            </td>
            </tr>
            ';
            } else {

                foreach ($programas as $programa) {
                    $detalle = $programa->verificar?->identificacion;
                    $html .= '
            <tr>
            <td>
            ' . $programa->equipo->nombre;
                    if ($detalle) {

                        $html .= ' ' . $detalle;
                    }

                    $html .= '

            </td>
            <td>

        ' . $programa->equipo->periodicidad . '
        </td>
        <td>
        ' . formatearFecha($programa->fecha->format('Y-m-d')) . '
        </td>
        </tr>
        ';
                }
            }

            $html .= '
        </tbody>
        ';
        }

        $html .= '
        </table>
        </body>
        </html>
        ';

        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper(
            'A4',
            'portrait'
        );
        $dompdf->render();
        $dompdf->stream(
            $nombreFormato . '.pdf',
            [
                'Attachment' => true
            ]
        );

        exit;
    }
}
