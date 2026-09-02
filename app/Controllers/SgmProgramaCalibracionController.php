<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\JsonResponse;
use App\Services\ModuleStationService;

use App\Models\Sgm\Autorizado;
use App\Models\Estacion;
use App\Models\Sgm\ProgramaAnualCalibracionVerificacion;
use App\Models\Sgm\PatronInstrumento;
use App\Models\Sgm\InventarioEquipo;

use Dompdf\Dompdf;
use Dompdf\Options;
use Carbon\Carbon;

class SgmProgramaCalibracionController extends BaseController
{

    private function estacionModulo(): ?int
    {
        return ModuleStationService::getContext('sgm')['id_estacion'] ?? null;
    }

    public function tableProgramaCalibracionPatrones()
    {
        $year = Request::input('year');
        $formato = Request::input('formato');

        $categorias = match ((int)$formato) {
            14 => [
                'Instrumentos de medida',
                'Patrones de medida'
            ],
            15 => [
                'Equipo sometido a verificación'
            ],
            default => []
        };

        $resultado = [];

        foreach ($categorias as $categoria) {

            $equipos = ProgramaAnualCalibracionVerificacion::with([
                'equipo:id,nombre,periodicidad,categoria',
                'verificar:id,identificacion'
            ])
                ->where('id_estacion', $this->estacionModulo())
                ->whereYear('fecha', $year)
                ->whereHas('equipo', function ($q) use ($categoria) {
                    $q->where('categoria', $categoria);
                })
                ->orderBy('fecha')
                ->get();

            $resultado[] = [
                'categoria' => $categoria,
                'items' => $equipos->map(function ($item) {

                    return [
                        'id' => $item->id,
                        'nombre' => $item->equipo->nombre,
                        'detalle' => $item->verificar?->identificacion,
                        'periodicidad' => $item->equipo->periodicidad,
                        'fecha' => formatearFecha($item->fecha->format('Y-m-d'))
                    ];
                })
            ];
        }

        JsonResponse::custom([
            'year' => $year,
            'formato' => $formato,
            'data' => $resultado
        ]);
    }

    public function modalProgramaCalibracion()
    {
        $formato = (int) Request::input('formato');

        $equiposProgramados = ProgramaAnualCalibracionVerificacion::query()
            ->where('id_estacion', $this->estacionModulo())
            ->pluck('id_equipo');

        $equipos = PatronInstrumento::query()

            ->when(
                $formato === 14,
                fn($q) => $q->where('categoria', '<>', 'Equipo sometido a verificación')
            )

            ->when(
                $formato === 15,
                fn($q) => $q->where('categoria', 'Equipo sometido a verificación')
            )

            ->whereNotIn('id', $equiposProgramados)

            ->orderBy('nombre')

            ->get([
                'id',
                'nombre'
            ]);

        JsonResponse::custom([
            'equipos' => $equipos
        ]);
    }

    public function createProgramaCalibracion()
    {
        $equipoId = Request::input('equipo_id');

        $fecha = Request::input('fecha');

        $equipo = PatronInstrumento::findOrFail($equipoId);

        if (
            $equipo->periodicidad === 'Mensual'
            && $this->inventarioActivo('Dispensarios') === 0
        ) {
            JsonResponse::error(
                'No se encontraron dispensarios activos para esta estación.'
            );
        }

        if (
            $equipo->periodicidad === 'Trimestral'
            && $this->inventarioActivo('Tanques de almacenamiento') === 0
        ) {
            JsonResponse::error(
                'No se encontraron tanques de almacenamiento activos para esta estación.'
            );
        }

        match ($equipo->periodicidad) {

            'Mensual'     => $this->crearMensual($equipo, $fecha),

            'Trimestral'  => $this->crearTrimestral($equipo, $fecha),

            'Semestral'   => $this->crearMeses($equipo, $fecha, 6, 20),

            'Anual'       => $this->crearAnios($equipo, $fecha, 1, 10),

            '2 años'      => $this->crearAnios($equipo, $fecha, 2, 10),

            '10 años'     => $this->crearAnios($equipo, $fecha, 10, 5),

            default => null
        };

        JsonResponse::success('Programa anual de calibración creado');
    }

    private function inventarioActivo(string $nombre): int
    {
        return InventarioEquipo::query()
            ->where('id_estacion', $this->estacionModulo())
            ->where('nombre', $nombre)
            ->where('estado', 1)
            ->count();
    }

    private function agregarPrograma(
        int $equipoId,
        string $fecha,
        int $verificar = 0
    ): void {

        ProgramaAnualCalibracionVerificacion::firstOrCreate(

            [
                'id_estacion' => $this->estacionModulo(),
                'id_equipo'   => $equipoId,
                'fecha'       => $fecha,
                'id_verificar' => $verificar
            ],

            [
                'estado' => 0
            ]

        );
    }

    private function crearMeses(
        PatronInstrumento $equipo,
        string $fecha,
        int $meses,
        int $repeticiones
    ): void {

        for ($i = 0; $i <= $repeticiones; $i++) {

            $this->agregarPrograma(

                $equipo->id,

                Carbon::parse($fecha)
                    ->addMonths($meses * $i)
                    ->toDateString()

            );
        }
    }

    private function crearAnios(
        PatronInstrumento $equipo,
        string $fecha,
        int $anios,
        int $repeticiones
    ): void {

        for ($i = 0; $i <= $repeticiones; $i++) {

            $this->agregarPrograma(

                $equipo->id,

                Carbon::parse($fecha)
                    ->addYears($anios * $i)
                    ->toDateString()

            );
        }
    }

    private function crearMensual(
        PatronInstrumento $equipo,
        string $fecha
    ): void {
        $disp = InventarioEquipo::query()

            ->where('id_estacion', $this->estacionModulo())

            ->where('nombre', 'Dispensarios')

            ->where('estado', 1)

            ->pluck('id');

        foreach (range(0, 120) as $i) {

            $f = Carbon::parse($fecha)
                ->addMonths($i)
                ->toDateString();

            foreach ($disp as $id) {

                $this->agregarPrograma(
                    $equipo->id,
                    $f,
                    $id
                );
            }
        }
    }

    private function crearTrimestral(
        PatronInstrumento $equipo,
        string $fecha
    ): void {

        $tanques = InventarioEquipo::query()

            ->where('id_estacion', $this->estacionModulo())

            ->where('nombre', 'Tanques de almacenamiento')

            ->where('estado', 1)

            ->pluck('id');

        foreach (range(0, 40) as $i) {

            $f = Carbon::parse($fecha)
                ->addMonths(3 * $i)
                ->toDateString();

            foreach ($tanques as $id) {

                $this->agregarPrograma(
                    $equipo->id,
                    $f,
                    $id
                );
            }
        }
    }

    public function pdfProgramaCalibracion(
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
