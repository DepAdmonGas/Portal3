<?php

namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Sgm\CalibracionEquipo;
use App\Services\CalibracionEquipoService;
use App\Models\Estacion;
use Carbon\Carbon;
use App\Helpers\ImageHelper;
use Dompdf\Dompdf;
use Dompdf\Options;

class BitacoraCalibracionEquiposController extends BaseController
{

protected string $modulo = 'sasisopa';

    public function index()
    {

     $title = 'Bitácora calibración de equipos';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('10. CONTROL DE ACTIVIDADES Y PROCESOS','/sasisopa/control-actividades-procesos');
        Breadcrumb::add('Calibración de Equipos','/sasisopa/control-actividades-procesos/calibracion-equipos');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'links' => [
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/controlactividadproceso/bitacoracalibracionequipos.datatable.init.js?v=1.7',
                '/js/controlactividadproceso/bitacoracalibracionequipos.action.init.js?v=1.8'

            ],

            'help' => false
        ];

        View::render('controlactividadproceso/bitacora-calibracion-equipos',$data,'sasisopa');
        
    }

    public function datatable(){

        $year = sanitize_input($_GET['year'] ?? null,'int');
        $mes = sanitize_input($_GET['mes'] ?? null,'int');

        $data = CalibracionEquipo::where('id_estacion',$this->estacionId())

        ->when($year, function ($q) use ($year) {
                $q->whereYear('fecha',$year);
        })

        ->when($mes, function ($q) use ($mes) {
                $q->whereMonth('fecha',$mes);
        })

        ->whereDate('fecha', '<=', Carbon::today())
        ->orderBy('id', 'DESC')
        ->get()

        ->map(function ($item) {

        $location = '';

        if($item->equipo == 'Dispensario'){
            $location = 'bitacora-calibracion-equipos-dispensario/'.$item->id;
        }else if($item->equipo == 'Jarra patron'){
            $location = 'bitacora-calibracion-equipos-jarra-patron/'.$item->id;
        }else if($item->equipo == 'Sondas de medición'){
            $location = 'bitacora-calibracion-equipos-sonda/'.$item->id;
        }else if($item->equipo == 'Tanques de almacenamiento'){
            $location = 'bitacora-calibracion-equipos-tanques-almacenamiento/'.$item->id;
        }

            return [
                'id' => $item->id,
                'folio' => str_pad($item->folio,3,'0',STR_PAD_LEFT),
                'fecha' => $item->fecha->format('Y-m-d'),
                'fecha_larga' => formatearFecha($item->fecha->format('Y-m-d')),
                'equipo' => $item->equipo,
                'resultado' => $item->resultados,
                'resultado_estado' => $item->resultados == null ? '<i class="ti ti-file-x text-danger fs-7"></i>' : '<a href="/uploads/archivos/calibracion/'.$item->resultados.'" target="_blank"><i class="ti ti-file-check text-success fs-7"></i></a>',
                'estado' => $item->estado == 1 ? '<span class="badge bg-success">Finalizado</span>' : '<span class="badge bg-danger">Pendiente</span>',
                'detalle' => $item->estado == 1 ? true : false,
                'location' => $location
            ];

        });

    echo json_encode([
        'data' => $data,
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

    public function create(): void{

    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'),true);

    $equipo = sanitize_input($data['equipo'] ?? null, 'string');
    $id_estacion = $this->estacionId();
    $id_usuario = $this->userId();

     if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para crear'
            ]);
            return;
        }

         if (empty($equipo)) {
            echo json_encode([
                'success' => false,
                'message' => 'Completa todos los campos obligatorios'
            ]);
            return;
        }

     try {

            $service = new CalibracionEquipoService();

            $resultado = $service->agregarCalibracionEquipo(
                $id_estacion,
                $id_usuario,
                $equipo
            );

            echo json_encode([
                'success' => true,
                'message' => 'Registro generado correctamente',
                'data' => $resultado
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
   

    }

    public function uploadResultado()
    {
        header('Content-Type: application/json');

        try {

            $id = (int)($_POST['id'] ?? 0);

            $file = $_FILES['documento'] ?? null;

            if (!$id) {
                throw new \Exception(
                    'ID inválido'
                );
            }

            if (!$file || $file['error'] !== UPLOAD_ERR_OK) {

                throw new \Exception(
                    'Debe seleccionar un PDF'
                );
            }

            $extension =
                strtolower(
                    pathinfo(
                        $file['name'],
                        PATHINFO_EXTENSION
                    )
                );

            if ($extension !== 'pdf') {

                throw new \Exception(
                    'Solo se permiten PDF'
                );
            }

            $carpeta = __DIR__ . '../../../public/uploads/archivos/calibracion/';

            if (!file_exists($carpeta)) {

                mkdir_safe(
                    $carpeta,
                    true
                );
            }

            $nombreArchivo =
                'RESULTADOS_' .
                $id .
                '_' .
                time() .
                '.pdf';

            $ruta =
                $carpeta .
                $nombreArchivo;

            if (!move_uploaded_file($file['tmp_name'],$ruta)) {

                throw new \Exception(
                    'No fue posible guardar el archivo'
                );
            }

            $calibracion =
                CalibracionEquipo::findOrFail(
                    $id
                );

            if (
                !empty(
                    $calibracion->resultados
                )
            ) {

                $anterior =
                    $carpeta .
                    $calibracion->resultados;

                if (
                    file_exists(
                        $anterior
                    )
                ) {

                    unlink(
                        $anterior
                    );
                }
            }

            $calibracion->update([
                'resultados' =>
                    $nombreArchivo
            ]);

            echo json_encode([
                'success' => true,
                'archivo' => $nombreArchivo,
                'message' =>
                    'Archivo guardado correctamente'
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' =>
                    $e->getMessage()
            ]);
        }

        exit;
    }

    public function detalle(int $id)
    {
        header('Content-Type: application/json');

        $calibracion = CalibracionEquipo::query()
            ->with([
                'usuario',
                'detalles',
                'dispensarios.dispensario',
                'jarras.jarra',
                'sondas.sonda',
                'tanques.tanque'
            ])
            ->findOrFail($id);

            $calibracion->fecha_formateada =
                $calibracion->fecha &&
                $calibracion->fecha->year > 1900
                    ? formatearFecha($calibracion->fecha->format('Y-m-d'))
                    : '';

            $calibracion->hora_formateada =
            !empty($calibracion->hora)
                ? Carbon::createFromFormat(
                    'H:i:s',
                    $calibracion->hora
                )->format('g:i a')
                : '';

                $calibracion->categoria_detalle = 
                $calibracion->categoria == 1? 'Ordinaria' : 'Extraordinaria';

            $calibracion->unidad_verificacion =
            optional(
                $calibracion->detalles
                    ->firstWhere(
                        'categoria',
                        'Unidad de verificación'
                    )
            )->resultado ?? '';

            $calibracion->numero_acreditacion =
                optional(
                    $calibracion->detalles
                        ->firstWhere(
                            'categoria',
                            'No. de acreditación'
                        )
                )->resultado ?? '';

            $calibracion->metodo_usado_calibracion =
                optional(
                    $calibracion->detalles
                        ->firstWhere(
                            'categoria',
                            'Método usado para la calibración'
                        )
                )->resultado ?? '';

            $calibracion->temperatura_ambiente =
                optional(
                    $calibracion->detalles
                        ->firstWhere(
                            'categoria',
                            'Temperatura ambiente'
                        )
                )->resultado ?? '';

            $calibracion->presion_atmosferica =
                optional(
                    $calibracion->detalles
                        ->firstWhere(
                            'categoria',
                            'Presión atmosférica'
                        )
                )->resultado ?? '';

            $calibracion->humedad =
                optional(
                    $calibracion->detalles
                        ->firstWhere(
                            'categoria',
                            'Humedad'
                        )
                )->resultado ?? '';

            $calibracion->liquido_calibracion =
                optional(
                    $calibracion->detalles
                        ->firstWhere(
                            'categoria',
                            'Liquido usado en la calibración'
                        )
                )->resultado ?? '';

            $calibracion->temperatura_liquido =
                optional(
                    $calibracion->detalles
                        ->firstWhere(
                            'categoria',
                            'Temperatura del líquido'
                        )
                )->resultado ?? '';

            $calibracion->laboratorio_calibracion =
                optional(
                    $calibracion->detalles
                        ->firstWhere(
                            'categoria',
                            'Laboratorio de calibración'
                        )
                )->resultado ?? '';

            $calibracion->numero_acreditacion =
                optional(
                    $calibracion->detalles
                        ->firstWhere(
                            'categoria',
                            'No. de acreditación'
                        )
                )->resultado ?? '';

                $calibracion->metodo_calibracion =
                optional(
                    $calibracion->detalles
                        ->firstWhere(
                            'categoria',
                            'Método de calibración'
                        )
                )->resultado ?? '';


        
        if (
            $calibracion->usuario &&
            !empty($calibracion->usuario->firma)
        ) {

            $calibracion->usuario->firma_url =
                ImageHelper::firmaUrl(
                    $calibracion->usuario->firma
                );

        } else {

            $calibracion->usuario->firma_url = null;
        }

        echo json_encode([
            'success' => true,
            'data' => $calibracion
        ]);

        exit;
    }

    public function pdf()
{
    [
        'year' => $year,
        'mes'  => $mes
    ] = $this->filtros();

    $estacion = Estacion::find(
        $this->estacionId()
    );

    if (!$estacion) {
        return 'No se encontró información';
    }

    $logo =
        $_ENV['APP_URL'] .
        '/assets/images/logos/Logo.png';

    $tituloFecha =
        !empty($mes)
            ? nombremes($mes).' '.$year
            : $year;

    $registros = CalibracionEquipo::query()

        ->with([
            'usuario',
            'detalles',
            'dispensarios.dispensario',
            'jarras.jarra',
            'sondas.sonda',
            'tanques.tanque'
        ])

        ->where('id_estacion',$this->estacionId())
         ->where('estado',1)
        ->when(
            $year,
            fn($q) => $q->whereYear('fecha',$year)
        )
        ->when(
            $mes,
            fn($q) => $q->whereMonth('fecha',$mes)
        )
        ->orderBy('fecha')
        ->get();

       $registros->each(function ($item) {

    $item->categoria_detalle =
        $item->categoria == 1
            ? 'Ordinaria'
            : 'Extraordinaria';

    $item->unidad_verificacion =
        optional(
            $item->detalles->firstWhere(
                'categoria',
                'Unidad de verificación'
            )
        )->resultado ?? '';

    $item->numero_acreditacion =
        optional(
            $item->detalles->firstWhere(
                'categoria',
                'No. de acreditación'
            )
        )->resultado ?? '';

    $item->metodo_usado_calibracion =
        optional(
            $item->detalles->firstWhere(
                'categoria',
                'Método usado para la calibración'
            )
        )->resultado ?? '';

    $item->temperatura_ambiente =
        optional(
            $item->detalles->firstWhere(
                'categoria',
                'Temperatura ambiente'
            )
        )->resultado ?? '';

    $item->presion_atmosferica =
        optional(
            $item->detalles->firstWhere(
                'categoria',
                'Presión atmosférica'
            )
        )->resultado ?? '';

    $item->humedad =
        optional(
            $item->detalles->firstWhere(
                'categoria',
                'Humedad'
            )
        )->resultado ?? '';

    $item->liquido_calibracion =
        optional(
            $item->detalles->firstWhere(
                'categoria',
                'Liquido usado en la calibración'
            )
        )->resultado ?? '';

    $item->temperatura_liquido =
        optional(
            $item->detalles->firstWhere(
                'categoria',
                'Temperatura del líquido'
            )
        )->resultado ?? '';

    $item->laboratorio_calibracion =
        optional(
            $item->detalles->firstWhere(
                'categoria',
                'Laboratorio de calibración'
            )
        )->resultado ?? '';

    $item->metodo_calibracion =
        optional(
            $item->detalles->firstWhere(
                'categoria',
                'Método de calibración'
            )
        )->resultado ?? '';
});
        

    $html = '

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<style>

@page{
    margin:0.6cm;
}

body{
    font-family:Arial, Helvetica, sans-serif;
    font-size:10px;
    color:#212529;
}

.text-center{
    text-align:center;
}

.text-end{
    text-align:right;
}

.mt-1{
    margin-top:5px;
}

.mt-2{
    margin-top:10px;
}

.mt-3{
    margin-top:15px;
}

.mt-4{
    margin-top:20px;
}

.page-break{
    page-break-after:always;
}

table{
    width:100%;
    border-collapse:collapse;
}

th{
    background:#F2F2F2;
    font-weight:bold;
}

th,
td{
    border:1px solid #dee2e6;
    padding:3px;
    vertical-align:middle;
}

.firma{
    text-align:center;
}

.firma img{
    max-width:70px;
    max-height:45px;
}

</style>

</head>

<body>

<div style="margin-top:180px;" class="text-center">

    <img
        src="'.$logo.'"
        width="220">

</div>

<div style="font-size:16px">
<div class="text-center mt-3">

    <strong>
        Bitácora calibración de equipos
    </strong>

</div>

<div class="text-center mt-1">

    <strong>
        '.$estacion->permisocre.'
    </strong>

</div>

<div class="text-center mt-1">

    '.$estacion->razonsocial.'

</div>

<div class="text-center mt-1">

    '.$estacion->direccioncompleta.'

</div>

<div class="text-center mt-1">

    Código: DLES/SA/002

</div>

<div class="text-center mt-2">

    <strong>
        '.$tituloFecha.'
    </strong>

</div>
</div>

<div class="page-break"></div>

<table>

<thead>

<tr>

    <th width="8%">
        Fecha
    </th>

    <th width="6%">
        Hora
    </th>

    <th width="12%">
        Equipo
    </th>

    <th width="42%">
        Detalle
    </th>

    <th width="10%">
        Observaciones
    </th>

    <th width="10%">
        Responsable
    </th>

    <th width="12%">
        Firma
    </th>

</tr>

</thead>

<tbody>
';

    foreach ($registros as $item) {

        $firma = '';

        if (
            $item->usuario &&
            !empty($item->usuario->firma)
        ) {

            try {

                $firma = ImageHelper::base64(
                    ImageHelper::firmaUrl(
                        $item->usuario->firma
                    )
                );

            } catch (\Throwable $e) {

                $firma = '';
            }
        }

        $hora = '';

        if (!empty($item->hora)) {

            try {

                $hora =
                    Carbon::createFromFormat(
                        'H:i:s',
                        $item->hora
                    )
                    ->format('g:i A');

            } catch (\Throwable $e) {

                $hora = $item->hora;
            }
        }

        $detalles =
            $this->detalleEquipoPdf(
                $item
            );

        $html .= '

        <tr>

            <td>

                '.formatearFecha(
                    $item->fecha->format(
                        'Y-m-d'
                    )
                ).'

            </td>

            <td>

                '.$hora.'

            </td>

            <td>

                '.$item->equipo.'

            </td>

            <td>

                '.$detalles.'

            </td>

            <td>

                '.htmlspecialchars(
                    $item->observaciones ?? ''
                ).'

            </td>

            <td>

                '.htmlspecialchars(
                    $item->responsable_verificacion ?? ''
                ).'

            </td>

            <td class="firma">

                '.(
                    $firma
                        ? '<img src="'.$firma.'"><br>'
                        : ''
                ).'

                '.htmlspecialchars(
                    $item->usuario->nombre ?? ''
                ).'

            </td>

        </tr>
        ';
    }

    $html .= '

</tbody>

</table>

</body>

</html>
';

    $options = new Options();

    $options->set(
        'isRemoteEnabled',
        true
    );

    $options->set(
        'defaultFont',
        'Arial'
    );

    $dompdf =
        new Dompdf(
            $options
        );

    $dompdf->loadHtml($html);

    $dompdf->setPaper(
        'A4',
        'landscape'
    );

    $dompdf->render();

    return $dompdf->stream(
        'Bitacora-calibracion-equipos.pdf',
        [
            'Attachment' => true
        ]
    );
}

    private function filtros(): array
    {
        return [
            'year' => sanitize_input($_GET['year'] ?? null,'int'),
            'mes' => sanitize_input($_GET['mes'] ?? null,'int')
        ];
    }

private function detalleGeneralPdf(
    CalibracionEquipo $item
): string {

    $categoriaDetalle =
        $item->categoria == 1
            ? 'Ordinaria'
            : 'Extraordinaria';

    $unidadVerificacion = optional(
        $item->detalles->firstWhere(
            'categoria',
            'Unidad de verificación'
        )
    )->resultado ?? '';

    $numeroAcreditacion = optional(
        $item->detalles->firstWhere(
            'categoria',
            'No. de acreditación'
        )
    )->resultado ?? '';

    $metodoUsadoCalibracion = optional(
        $item->detalles->firstWhere(
            'categoria',
            'Método usado para la calibración'
        )
    )->resultado ?? '';

    $temperaturaAmbiente = optional(
        $item->detalles->firstWhere(
            'categoria',
            'Temperatura ambiente'
        )
    )->resultado ?? '';

    $presionAtmosferica = optional(
        $item->detalles->firstWhere(
            'categoria',
            'Presión atmosférica'
        )
    )->resultado ?? '';

    $humedad = optional(
        $item->detalles->firstWhere(
            'categoria',
            'Humedad'
        )
    )->resultado ?? '';

    $liquidoCalibracion = optional(
        $item->detalles->firstWhere(
            'categoria',
            'Liquido usado en la calibración'
        )
    )->resultado ?? '';

    $temperaturaLiquido = optional(
        $item->detalles->firstWhere(
            'categoria',
            'Temperatura del líquido'
        )
    )->resultado ?? '';

    $laboratorioCalibracion = optional(
        $item->detalles->firstWhere(
            'categoria',
            'Laboratorio de calibración'
        )
    )->resultado ?? '';

    $metodoCalibracion = optional(
        $item->detalles->firstWhere(
            'categoria',
            'Método de calibración'
        )
    )->resultado ?? '';

    switch ($item->equipo) {

        case 'Dispensario':

            return '
            <div class="mt-2">
            <b>Unidad de verificación:</b> '.$unidadVerificacion.'<br>
            <b>No. acreditación:</b> '.$numeroAcreditacion.'<br>
            <b>Tipo calibración:</b> '.$categoriaDetalle.'<br>
            </div>';

        case 'Sondas de medición':

            return '
            <div class="mt-2">
            <b>Unidad de verificación:</b> '.$unidadVerificacion.'<br>
            <b>No. acreditación:</b> '.$numeroAcreditacion.'<br>
            <b>Método usado para la calibración:</b> '.$metodoUsadoCalibracion.'
            </div>';

        case 'Tanques de almacenamiento':

            return '
            <div class="mt-2">
            <b>Unidad de verificación:</b> '.$unidadVerificacion.'<br>
            <b>No. acreditación:</b> '.$numeroAcreditacion.'<br>
            <b>Método usado:</b> '.$metodoUsadoCalibracion.'
            </div>
            ';

        case 'Jarra patron':

            return '

            <table width="100%" style="margin-bottom:5px;">

                <tr>
                    <td><b>Temperatura ambiente:</b></td>
                    <td>'.$temperaturaAmbiente.'</td>

                    <td><b>Presión atmosférica:</b></td>
                    <td>'.$presionAtmosferica.'</td>
                </tr>

                <tr>
                    <td><b>Humedad:</b></td>
                    <td>'.$humedad.'</td>

                    <td><b>Liquido calibración:</b></td>
                    <td>'.$liquidoCalibracion.'</td>
                </tr>

                <tr>
                    <td><b>Temperatura líquido:</b></td>
                    <td>'.$temperaturaLiquido.'</td>

                    <td><b>Laboratorio:</b></td>
                    <td>'.$laboratorioCalibracion.'</td>
                </tr>

                <tr>
                    <td><b>No. acreditación:</b></td>
                    <td>'.$numeroAcreditacion.'</td>

                    <td><b>Método calibración:</b></td>
                    <td>'.$metodoCalibracion.'</td>
                </tr>

            </table>';
    }

    return '';
}

private function detalleEquipoPdf(
    CalibracionEquipo $item
): string {

    switch ($item->equipo) {

        case 'Dispensario':


            $html = '
            <table width="100%" border="1" cellpadding="2">
                <tr style="background:#F2F2F2;">
                    <th>No.</th>
                    <th>Dispensario</th>
                    <th>¿Cumple con el error maximo tolerado?</th>
                    <th>¿Cumple con la repetibilidad?</th>
                    <th>Folio del holograma</th>
                    <th>Distintivo empresarial</th>
                </tr>';

            foreach ($item->dispensarios as $d) {

                $html .= '

                <tr>
                    <td align="center">
                        '.$d->dispensario?->no_dispensario.'
                    </td>
                    <td>
                        '.$d->dispensario?->marca.' , '.$d->dispensario?->modelo.'
                    </td>
                    <td align="center">
                        '.$d->resultado1.'
                    </td>
                    <td align="center">
                        '.$d->resultado2.'
                    </td>
                    <td align="center">
                        '.$d->resultado3.'
                    </td>
                    <td align="center">
                        '.$d->resultado4.'
                    </td>
                </tr>';
            }

            $html .= '</table>';
            $html .= $this->detalleGeneralPdf($item);
            return $html;

        case 'Tanques de almacenamiento':

            $html = '

            <table width="100%" border="1" cellpadding="2">

                <tr style="background:#F2F2F2;">
                    <th>Tanque</th>
                    <th>Capacidad</th>
                    <th>Producto</th>
                    <th>Incertidumbre de calibración</th>
                    <th>Cumple con los límites establecidos</th>
                </tr>';

            foreach ($item->tanques as $t) {

                $html .= '

                <tr>

                    <td align="center">
                        '.$t->tanque?->no_tanque.'
                    </td>

                    <td align="center">
                        '.$t->tanque?->capacidad.'
                    </td>

                    <td>
                        '.$t->tanque?->producto.'
                    </td>

                    <td align="center">
                        '.$t->resultado1.'
                    </td>

                    <td align="center">
                        '.$t->resultado2.'
                    </td>

                </tr>';
            }

            $html .= '</table>';
            $html .= $this->detalleGeneralPdf($item);

            return $html;

        case 'Sondas de medición':

            $html = '

            <table width="100%" border="1" cellpadding="2">

                <tr style="background:#F2F2F2;">
                    <th>No. Sonda</th>
                    <th>Marca</th>
                    <th>Modelo</th>
                    <th>Incertidumbre de calibracion</th>
                </tr>';

            foreach ($item->sondas as $s) {

                $html .= '
                <tr>
                    <td align="center">
                        '.$s->sonda?->no_sonda.'
                    </td>
                    <td>
                        '.$s->sonda?->marca.'
                    </td>
                    <td>
                        '.$s->sonda?->modelo.'
                    </td>
                    <td align="center">
                        '.$s->resultado1.'
                    </td>
                </tr>';
            }

            $html .= '</table>';
            $html .= $this->detalleGeneralPdf($item);

            return $html;

        case 'Jarra patron':

            $html = '

            <table width="100%" border="1" cellpadding="2">

                <tr style="background:#F2F2F2;">
                    <th>Marca</th>
                    <th>Serie</th>
                    <th>Capacidad</th>
                    <th>Incertidumbre</th>
                </tr>';

            foreach ($item->jarras as $j) {

                $html .= '

                <tr>

                    <td>
                        '.$j->jarra?->marca.'
                    </td>

                    <td>
                        '.$j->jarra?->no_serie.'
                    </td>

                    <td align="center">
                        '.$j->jarra?->capacidad.'
                    </td>

                    <td align="center">
                        '.$j->resultado1.'
                    </td>

                </tr>';
            }

            $html .= '</table>';
            $html .= $this->detalleGeneralPdf($item);

            return $html;
    }

    return '';
}

}