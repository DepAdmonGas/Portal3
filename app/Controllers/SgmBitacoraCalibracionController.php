<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Core\Request;
use App\Core\JsonResponse;

use App\Models\Estacion;
use App\Models\Sgm\Autorizado;
use App\Models\Sgm\ProgramaAnualCalibracionVerificacion;
use App\Models\Sgm\BitacoraCalibracionEquipo;
use App\Models\Sgm\BitacoraCalibracionEquipoDetalle;
use App\Models\Sgm\InventarioEquipo;

use Dompdf\Dompdf;
use Dompdf\Options;

class SgmBitacoraCalibracionController extends BaseController
{
    protected string $modulo = 'sgm';

    public function datatable()
    {
        $fecha = date('Y-m-d');

        $permisoEliminar  = ModuloService::validaPermiso($this->modulo, 'eliminar');
        $permisoEditar    = ModuloService::validaPermiso($this->modulo, 'editar');
        $permisoDescargar = ModuloService::validaPermiso($this->modulo, 'descargar');

        $programas = ProgramaAnualCalibracionVerificacion::query()

            ->with([
                'equipo:id,nombre,periodicidad,categoria'
            ])

            ->where('id_estacion', $this->estacionId())

            ->whereDate('fecha', '<=', $fecha)

            ->whereHas('equipo', function ($query) {

                $query->where(
                    'categoria',
                    '<>',
                    'Equipo sometido a verificación'
                );
            })

            ->orderByDesc('fecha')

            ->get()

            ->map(function ($item) {

                return [
                    'id'            => $item->id,
                    'equipo'        => $item->equipo->nombre,
                    'periodicidad'  => $item->equipo->periodicidad,
                    'fecha'         => $item->fecha->format('Y-m-d'),
                    'estado'        => $item->estado,
                    'color' => match ($item->estado) {
                        0       => '#fbf8ce',
                        1       => '#cffbce',
                        default => '#ffffff',
                    },
                    'acciones' => [
                        'editar' => true,
                        'detalle' => $item->estado === 1,
                        'descargar' => $item->estado === 1,
                    ],

                ];
            })

            ->values();

        JsonResponse::custom([
            'data' => $programas->toArray(),
            'permisos' => [
                'eliminar'   => $permisoEliminar,
                'editar'     => $permisoEditar,
                'descargar'  => $permisoDescargar,
            ],

        ]);
    }

    public function editarBitacoraCalibracionEquipos(int $id)
    {

        $title = 'Editar';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add('7. Procesos de medición', '/sgm/procesos-medicion');
        Breadcrumb::add('Bitácora la para la calibración de equipos', '/sgm/procesos-medicion/bitacora-calibracion-equipos');
        Breadcrumb::add($title, '');
        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'id' => $id,
            'links' => [],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/sgm/procesos-medicion/editarbitacoracalibracion.action.init.js?v=' . time(),

            ],
            'help' => false
        ];

        View::render('sgm/procesos-medicion/editar-bitacora-calibracion-equipos', $data, 'sgm');
    }

    public function obtenerBitacora(int $id)
    {
        $programa = ProgramaAnualCalibracionVerificacion::query()

            ->with('equipo')

            ->findOrFail($id);

        $usuario = Autorizado::query()

            ->join(
                'tb_usuarios',
                'tb_usuarios.id',
                '=',
                'sgm_autorizado.id_usuario'
            )

            ->where('tb_usuarios.id_gas', $programa->id_estacion)

            ->where('sgm_autorizado.estado', 1)

            ->select('sgm_autorizado.id_usuario')

            ->first();

        $bitacora = BitacoraCalibracionEquipo::firstOrCreate(

            [
                'id_programa' => $id,
            ],

            [
                'fecha' => date('Y-m-d'),
                'hora' => date('H:m:s'),
                'nombre_equipo' => $programa->equipo->nombre,
                'marca' => '',
                'capacidad' => '',
                'almacena' => '',
                'nombre_laboratorio' => '',
                'no_acreditacion' => '',
                'metodo_calibracion' => '',
                'nombre_patron' => '',
                'marca_modelo_serie' => '',
                'resolucion' => '',
                'incertidumbre' => '',
                'vigencia_certificado' => '',
                'realizadopor' => $usuario?->id_usuario ?? 0,
            ]

        );

        if (
            !$bitacora->detalles()->exists()
        ) {

            InventarioEquipo::query()

                ->where('id_estacion', $programa->id_estacion)

                ->where(
                    'nombre',
                    $programa->equipo->nombre
                )

                ->get()

                ->each(function ($equipo) use ($id) {

                    BitacoraCalibracionEquipoDetalle::create([

                        'id_programa' => $id,

                        'id_equipo' => $equipo->id,

                        'resultado' => ''

                    ]);
                });
        }

        $bitacora->load([

            'detalles.equipo'

        ]);

        JsonResponse::custom(
            ['bitacora' => $bitacora]
        );
    }

    public function actualizarCampo()
    {
        try {

            $id = Request::jsonInput('id');
            $campo = Request::jsonInput('campo');
            $valor = Request::jsonInput('valor');

            $bitacora = BitacoraCalibracionEquipo::findOrFail($id);

            $bitacora->$campo = $valor;

            if ($bitacora->save()) {
                JsonResponse::success('Campo actualizado correctamente.');
                return;
            }
        } catch (\Throwable $e) {

            JsonResponse::error('Error al actualizar el registro.');
        }
    }

    public function actualizarResultado()
    {
        $detalle = BitacoraCalibracionEquipoDetalle::findOrFail(

            Request::jsonInput('id')

        );

        $detalle->resultado = Request::jsonInput('valor');

        $detalle->save();

        JsonResponse::success('Campo actualizado correctamente.');
    }

    public function finalizar()
    {

        $id = Request::jsonInput('id');
        ProgramaAnualCalibracionVerificacion::query()

            ->whereKey($id)

            ->update([

                'estado' => 1

            ]);

        JsonResponse::success(

            'Bitácora finalizada'

        );
    }

    public function pdfBitacora(int $id)
    {
        header('Content-Type: application/pdf');

        $estacion = Estacion::findOrFail(
            $this->estacionId()
        );

        $bitacora = BitacoraCalibracionEquipo::query()

            ->where('id_programa', $id)

            ->with([
                'detalles.equipo',
                'realizadoPor:id,nombre'
            ])

            ->firstOrFail();

        $css = file_get_contents(
            'assets/css/pdf.css'
        );

        $html = '
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Bitácora la para la calibración de equipos</title>
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
                <strong>Bitácora para la calibración de equipos</strong>
            </td>

            <td class="text-center align-middle">
                <strong>Fecha de autorización: 01-01-2024</strong>
            </td>
        </tr>

        <tr>
            <td class="text-center align-middle">
                Fo.SGM.017
            </td>
        </tr>

        <tr>
            <td class="text-center align-middle">
                Realizado por:<br>
                ' . ($bitacora->realizadoPor?->nombre ?? 'S/I') . '
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

    <table class="table table-bordered table-sm">

        <tbody>

            <tr>
                <td><b>Fecha</b></td>
                <td>' . formatearFecha($bitacora->fecha) . '</td>
            </tr>

            <tr>
                <td><b>Hora</b></td>
                <td>' . $bitacora->hora . '</td>
            </tr>

            <tr>
                <td><b>Nombre del equipo a calibrar</b></td>
                <td>' . $bitacora->nombre_equipo . '</td>
            </tr>

            <tr>
                <td><b>Marca</b></td>
                <td>' . $bitacora->marca . '</td>
            </tr>

            <tr>
                <td><b>Capacidad</b></td>
                <td>' . $bitacora->capacidad . '</td>
            </tr>

            <tr>
                <td><b>Producto que almacena</b></td>
                <td>' . $bitacora->almacena . '</td>
            </tr>

            <tr>
                <td><b>Nombre del laboratorio</b></td>
                <td>' . $bitacora->nombre_laboratorio . '</td>
            </tr>

            <tr>
                <td><b>No. acreditación</b></td>
                <td>' . $bitacora->no_acreditacion . '</td>
            </tr>

            <tr>
                <td><b>Método utilizado</b></td>
                <td>' . $bitacora->metodo_calibracion . '</td>
            </tr>

        </tbody>

    </table>

    <h3 class="mt-2">Descripción de patrones utilizados</h3>

    <table class="table table-bordered table-sm">

        <tbody>

            <tr>
                <td><b>Nombre del patrón</b></td>
                <td>' . $bitacora->nombre_patron . '</td>
            </tr>

            <tr>
                <td><b>Marca, modelo y serie</b></td>
                <td>' . $bitacora->marca_modelo_serie . '</td>
            </tr>

            <tr>
                <td><b>Resolución</b></td>
                <td>' . $bitacora->resolucion . '</td>
            </tr>

            <tr>
                <td><b>Incertidumbre</b></td>
                <td>' . $bitacora->incertidumbre . '</td>
            </tr>

            <tr>
                <td><b>Vigencia certificado</b></td>
                <td>' . $bitacora->vigencia_certificado . '</td>
            </tr>

        </tbody>

    </table>

    <table class="table table-bordered table-sm">

        <thead>

            <tr>
                <th>Equipo o Instrumento</th>
                <th>Identificación</th>
                <th>Resultado</th>
            </tr>

        </thead>

        <tbody>
    ';
        foreach ($bitacora->detalles as $detalle) {

            $html .= '
        <tr>

            <td>' . ($detalle->equipo->nombre ?? '') . '</td>

            <td>' . ($detalle->equipo->identificacion ?? '') . '</td>

            <td>' . $detalle->resultado . '</td>

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
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($html);

        $dompdf->setPaper('A4', 'portrait');

        $dompdf->render();

        $dompdf->stream(
            'Bitácora la para la calibración de equipos.pdf',
            [
                'Attachment' => true,
            ]
        );

        exit;
    }
}
