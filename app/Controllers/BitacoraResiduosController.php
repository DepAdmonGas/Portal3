<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Services\ModuleStationService;
use App\Models\Sasisopa\ResiduoPeligroso;
use App\Models\Estacion;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;

class BitacoraResiduosController extends BaseController
{
    protected string $modulo = 'sasisopa';

    private function estacionModulo(): ?int
    {
        return ModuleStationService::getContext('sasisopa')['id_estacion'] ?? null;
    }

    public function index()
    {

        $title = 'Bitácora de Residuos Peligrosos';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('10. CONTROL DE ACTIVIDADES Y PROCESOS','/sasisopa/control-actividades-procesos');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'estacionId' => $this->estacionModulo(),
            'moduleStationKey' => 'sasisopa',
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/controlactividadproceso/bitacoraresiduos.datatable.init.js?v=' . time(),
                '/js/controlactividadproceso/bitacoraresiduos.action.init.js?v=' . time(),
                '/js/core/module-station-selector.js?v=' . time(),

            ],

            'help' => false
        ];

        View::render('controlactividadproceso/bitacora-residuos-peligrosos',$data,'sasisopa');
       
    }

    public function datatable()
{
    $data = ResiduoPeligroso::query()

        ->with('usuario')

        ->where(
            'id_estacion',
            $this->estacionModulo()
        )

        ->where(
            'estado',
            1
        )

        ->orderByDesc('folio')

        ->get()

        ->map(function ($item) {

           return [
    'id' => $item->id,
    'folio' => '00'.$item->folio,
    'nombreresiduo' => $item->nombreresiduo,
    'cantidadgenerada' => $item->cantidadgenerada,
    'caracteristicas' => $item->caracteristicas,
    'caracteristica_descripcion' => $this->obtenerCaracteristica(
        $item->caracteristicas
    ),
    'areaproceso' => $item->areaproceso,
    'fechaingreso' => $item->fechaingreso->format('Y-m-d'),
    'fechasalida' => $item->fechasalida->format('Y-m-d'),
    'fechaingreso_larga' => formatearFecha($item->fechaingreso) ?: 'S/I',
    'fechasalida_larga' => formatearFecha($item->fechasalida) ?: 'S/I',

    'nombrerecolector' => $item->nombrerecolector,
    'numerorecolector' => $item->numerorecolector,

    'nombretransportista' => $item->nombretransportista,
    'numerotransportista' => $item->numerotransportista,

    'nombredestinatario' => $item->nombredestinatario,
    'numerodestinatario' => $item->numerodestinatario,
    'procesodestinatario' => $item->procesodestinatario,

    'responsable' => $item->usuario->nombre ?? '',
];
        });

    echo json_encode([
        'data' => $data
    ]);
}

private function obtenerCaracteristica(?string $peligrosidad): string
    {
        if ($peligrosidad === null) {
            return "";
        }

        return match ($peligrosidad) {
            "C"  => $peligrosidad . " (Corrosividad)",
            "R"  => $peligrosidad . " (Reactividad)",
            "E"  => $peligrosidad . " (Explosividad)",
            "T"  => $peligrosidad . " (Toxicidad)",
            "Te" => $peligrosidad . " (Toxicidad Ambiental)",
            "Th" => $peligrosidad . " (Toxicidad Agua)",
            "Tt" => $peligrosidad . " (Toxicidad Crónica)",
            "I"  => $peligrosidad . " (Inflamabilidad)",
            "B"  => $peligrosidad . " (Biológico-Infeccioso)",
            default => ""
        };
    }

    private function filtros(): array
    {
        return [
            'year' => sanitize_input($_GET['year'] ?? null,'int'),
            'mes' => sanitize_input($_GET['mes'] ?? null,'int')
        ];
    }

public function pdf()
{
    [
        'year' => $year,
        'mes'  => $mes
    ] = $this->filtros();

    $estacion = Estacion::find(
        $this->estacionModulo()
    );

    if (!$estacion) {
        return 'No se encontró información';
    }

    $registros = ResiduoPeligroso::query()

        ->with('usuario')

        ->where(
            'id_estacion',
            $this->estacionModulo()
        )

        ->when(
            $year,
            fn ($q) =>
                $q->whereYear(
                    'fechaingreso',
                    $year
                )
        )

        ->when(
            !empty($mes) && $mes != 13,
            fn ($q) =>
                $q->whereMonth(
                    'fechaingreso',
                    $mes
                )
        )

        ->orderByDesc('folio')

        ->get();

    $tituloFecha =
        !empty($mes)
            ? nombremes($mes).' '.$year
            : $year;

    $totalKg = 0;
    $responsables = [];

    foreach ($registros as $item) {

        preg_match(
            '/\d+(\.\d+)?/',
            $item->cantidadgenerada,
            $match
        );

        if (!empty($match)) {
            $totalKg += (float) $match[0];
        }

        $nombre =
            $item->usuario->nombre ?? '';

        $responsables[$nombre] =
            ($responsables[$nombre] ?? 0) + 1;
    }

    arsort($responsables);

    $responsablePrincipal =
        array_key_first($responsables);

    $logo =
        $_ENV['APP_URL'] .
        '/assets/images/logos/Logo.png';

    $html = '

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<style>

@page{
    margin:0.4cm;
}

body{
    font-family:Arial, Helvetica, sans-serif;
    font-size:10px;
    color:#212529;
}

table{
    width:100%;
    border-collapse:collapse;
}

th,
td{
    border:1px solid #dee2e6;
    padding:3px;
    vertical-align:middle;
}

th{
    background:#F2F2F2;
    font-weight:bold;
}

.page-break{
    page-break-after:always;
}

.text-center {
  text-align: center !important;
}
</style>

</head>

<body>

<div class="text-center">

    <img
        src="'.$logo.'"
        width="180">

</div>

<h2 class="text-center">
    Bitácora de Residuos Peligrosos
</h2>

<div class="text-center">
    '.$tituloFecha.'
</div>

<br>

<table>

<tr>
    <td width="30%">
        <strong>Estación de servicio</strong>
    </td>
    <td>
        '.htmlspecialchars(
            $estacion->razonsocial
        ).'
    </td>
</tr>

<tr>
    <td>
        <strong>No. Registro Generador</strong>
    </td>
    <td>
        '.htmlspecialchars(
            $estacion->noregistro_generador
        ).'
    </td>
</tr>

<tr>
    <td>
        <strong>Categoría</strong>
    </td>
    <td>
        '.htmlspecialchars(
            $estacion->categoria
        ).'
    </td>
</tr>

</table>

<br>

<table>

<thead>

<tr>

<th rowspan="3">
Nombre del residuo peligroso
</th>

<th rowspan="3">
Cantidad generada
</th>

<th colspan="2">
Generación
</th>

<th colspan="2">
Almacenamiento temporal
</th>

<th colspan="7">
Prestador de servicios
</th>

</tr>

<tr>

<th rowspan="2">
CPR
</th>

<th rowspan="2">
Área o proceso
</th>

<th rowspan="2">
Fecha ingreso
</th>

<th rowspan="2">
Fecha salida
</th>

<th colspan="2">
Recolector
</th>

<th colspan="2">
Transportista
</th>

<th colspan="3">
Destinatario
</th>

</tr>

<tr>

<th>
Nombre
</th>

<th>
Autorización
</th>

<th>
Nombre
</th>

<th>
Autorización
</th>

<th>
Nombre
</th>

<th>
Autorización
</th>

<th>
Destino final
</th>

</tr>

</thead>

<tbody>
';

    foreach ($registros as $item) {

        $html .= '

        <tr>

            <td class="text-center">
                '.htmlspecialchars(
                    $item->nombreresiduo
                ).'
            </td>

            <td class="text-center">
                '.htmlspecialchars(
                    $item->cantidadgenerada
                ).'
            </td>

            <td class="text-center">
                '.$this->obtenerCaracteristicaPdf(
                    $item->caracteristicas
                ).'
            </td>

            <td class="text-center">
                '.htmlspecialchars(
                    $item->areaproceso
                ).'
            </td>

            <td class="text-center">
                '.formatearFecha(
                    $item->fechaingreso
                ).'
            </td>

            <td class="text-center">
                '.formatearFecha(
                    $item->fechasalida
                ).'
            </td>

            <td class="text-center">
                '.htmlspecialchars(
                    $item->nombrerecolector
                ).'
            </td>

            <td class="text-center">
                '.htmlspecialchars(
                    $item->numerorecolector
                ).'
            </td>

            <td class="text-center">
                '.htmlspecialchars(
                    $item->nombretransportista
                ).'
            </td>

            <td class="text-center">
                '.htmlspecialchars(
                    $item->numerotransportista
                ).'
            </td>

            <td class="text-center">
                '.htmlspecialchars(
                    $item->nombredestinatario
                ).'
            </td>

            <td class="text-center">
                '.htmlspecialchars(
                    $item->numerodestinatario
                ).'
            </td>

            <td class="text-center">
                '.htmlspecialchars(
                    $item->procesodestinatario
                ).'
            </td>

        </tr>
        ';
    }

    $html .= '

    <tr>

        <td class="text-center">
            <strong>TOTAL</strong>
        </td>

        <td class="text-center">
            <strong>'.$totalKg.' kg</strong>
        </td>

        <td colspan="4">
            Nombre del Responsable Técnico de la Bitácora
        </td>

        <td colspan="7">
            <strong>'.
            htmlspecialchars(
                $responsablePrincipal
            ).
            '</strong>
        </td>

    </tr>

</tbody>

</table>

</body>

</html>';

    $options = new Options();

    $options->set(
        'isRemoteEnabled',
        true
    );

    $options->set(
        'defaultFont',
        'Arial'
    );

    $dompdf = new Dompdf(
        $options
    );

    $dompdf->loadHtml($html);

    $dompdf->setPaper(
        'legal',
        'landscape'
    );

    $dompdf->render();

    return $dompdf->stream(
        'Bitacora-Residuos-Peligrosos.pdf',
        [
            'Attachment' => true
        ]
    );
}

private function obtenerCaracteristicaPdf(
    ?string $codigo
): string {

    return match ($codigo) {

        'C'  => 'C (Corrosividad)',
        'R'  => 'R (Reactividad)',
        'E'  => 'E (Explosividad)',
        'T'  => 'T (Toxicidad)',
        'Te' => 'Te (Toxicidad Ambiental)',
        'Th' => 'Th (Toxicidad Agua)',
        'Tt' => 'Tt (Toxicidad Crónica)',
        'I'  => 'I (Inflamabilidad)',
        'B'  => 'B (Biológico-Infeccioso)',

        default => ''
    };
}

}