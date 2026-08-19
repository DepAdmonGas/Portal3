<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Request;
use App\Core\JsonResponse;
use App\Services\ModuloService;
use App\Services\FileValidatorService;

use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Sasisopa\Dispensario;
use App\Models\Sasisopa\TanqueAlmacenamiento;
use App\Models\Sgm\InventarioEquipo;
use App\Models\Sgm\InventarioEquipoManual;

use Dompdf\Dompdf;
use Dompdf\Options;

class SgmInventarioEquipoController extends BaseController
{
    protected string $modulo = 'sgm';

    public function index()
    {
        $title = 'Inventario de equipo';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SGM', '/sgm');
        Breadcrumb::add('6. Gestion de los Recursos', '/sgm/gestion-recursos/inventario-equipo');
        Breadcrumb::add($title, '');
        $permisos = ModuloService::permisosSesion($this->modulo);

        $this->importarEquipo(
            'Tanques de almacenamiento',
            TanqueAlmacenamiento::class,
            'no_tanque',
            true
        );

        $this->importarEquipo(
            'Dispensarios',
            Dispensario::class,
            'no_dispensario'
        );


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
                '/js/sgm/gestion-recursos/inventarioequipo.actions.init.js?v=1.2.0',
                '/js/sgm/gestion-recursos/inventarioequipo.datatable.init.js?v=1.3.0'
            ],
            'help' => true
        ];

        View::render('sgm/gestion-recursos/inventario-equipo', $data, 'sgm');
    }

    private function importarEquipo(
        string $nombre,
        string $modelo,
        string $campo,
        bool $crearSondas = false
    ): void {

        $realizadoPor = Usuario::query()
            ->join(
                'sgm_autorizado',
                'sgm_autorizado.id_usuario',
                '=',
                'tb_usuarios.id'
            )
            ->where('tb_usuarios.id_gas', $this->estacionId())
            ->where('sgm_autorizado.estado', 1)
            ->value('tb_usuarios.id') ?? 0;

        if (
            InventarioEquipo::query()
            ->where('id_estacion', $this->estacionId())
            ->where('nombre', $nombre)
            ->exists()
        ) {
            return;
        }

        $modelo::query()
            ->where('id_estacion', $this->estacionId())
            ->orderBy($campo)
            ->get()
            ->each(function ($registro) use (
                $nombre,
                $campo,
                $realizadoPor,
                $crearSondas
            ) {

                InventarioEquipo::create([
                    'id_estacion' => $this->estacionId(),
                    'nombre' => $nombre,
                    'identificacion' => $registro->{$campo},
                    'funcion' => '',
                    'fecha_instalacion' => '',
                    'realizadopor' => $realizadoPor,
                    'estado' => 1
                ]);

                if ($crearSondas) {

                    InventarioEquipo::create([
                        'id_estacion' => $this->estacionId(),
                        'nombre' => 'Sondas de nivel y temperatura',
                        'identificacion' => $registro->{$campo},
                        'funcion' => '',
                        'fecha_instalacion' => '',
                        'realizadopor' => $realizadoPor,
                        'estado' => 1
                    ]);
                }
            });
    }

    public function datatable(): void
    {
        $data = InventarioEquipo::query()
            ->where('id_estacion', $this->estacionId())
            ->where('estado', '<', 2)
            ->orderByDesc('nombre')
            ->get()
            ->map(function ($equipo, $index) {

                return [
                    'id' => $equipo->id,
                    'numero' => $index + 1,
                    'nombre' => $equipo->nombre,
                    'identificacion' => $equipo->identificacion,
                    'funcion' => $equipo->funcion,
                    'fecha_instalacion' => $equipo->fecha_instalacion
                        ? $equipo->fecha_instalacion->format('Y-m-d')
                        : 'S/I',
                    'estado' => $equipo->estado,
                    'row_class' => match ($equipo->estado) {
                        0 => 'table-warning',
                        default => ''
                    },
                    'manual' => [
                        'id' => $equipo->id
                    ],
                    'acciones' => [
                        'editar' => ModuloService::validaPermiso(
                            $this->modulo,
                            'editar'
                        ),
                        'eliminar' => ModuloService::validaPermiso(
                            $this->modulo,
                            'eliminar'
                        )
                    ]
                ];
            });

        JsonResponse::custom([
            'data' => $data
        ]);
    }

    public function create(): void
    {
        $realizadoPor = Usuario::query()
            ->join(
                'sgm_autorizado',
                'sgm_autorizado.id_usuario',
                '=',
                'tb_usuarios.id'
            )
            ->where('tb_usuarios.id_gas', $this->estacionId())
            ->where('sgm_autorizado.estado', 1)
            ->value('tb_usuarios.id') ?? 0;

        $inventario = InventarioEquipo::create([
            'id_estacion' => $this->estacionId(),
            'nombre' => Request::jsonInput('nombre'),
            'identificacion' => Request::jsonInput('identificacion'),
            'funcion' => Request::jsonInput('funcion'),
            'fecha_instalacion' => Request::jsonInput('fecha_instalacion') ?: '0000-00-00',
            'realizadopor' => $realizadoPor,
            'estado' => 1

        ]);

        JsonResponse::success('Equipo registrado correctamente.', [
            'id' => $inventario->id
        ]);
    }

    public function detalleInventario(
        int $id
    ): void {

        $inventario = InventarioEquipo::with('manuales')
            ->findOrFail($id);

        JsonResponse::custom([

            'success' => true,

            'data' => [

                'id' => $inventario->id,

                'nombre' => $inventario->nombre,

                'identificacion' => $inventario->identificacion,

                'funcion' => $inventario->funcion,

                'fecha_instalacion' => optional(
                    $inventario->fecha_instalacion
                )?->format('Y-m-d'),

                'manuales' => $inventario->manuales
                    ->map(fn($manual) => [

                        'id' => $manual->id,

                        'fecha_hora' => optional(
                            $manual->fecha_hora
                        )?->format('Y-m-d H:i'),

                        'archivo' => $manual->archivo,

                        'url' => '/uploads/archivos/manuales/' .
                            $manual->archivo

                    ])
                    ->values()

            ]

        ]);
    }

    public function update(): void
    {
        $id = (int) Request::jsonInput('id');
        $inventario = InventarioEquipo::findOrFail($id);

        $inventario->update([

            'nombre' => Request::jsonInput('nombre'),
            'identificacion' => Request::jsonInput('identificacion'),
            'funcion' => Request::jsonInput('funcion'),
            'fecha_instalacion' => Request::jsonInput('fecha_instalacion') ?: '0000-00-00'

        ]);

        JsonResponse::success('Equipo actualizado correctamente.');
    }

    public function delete(): void
    {
        $id = (int) Request::Input('id');
        $inventario = InventarioEquipo::findOrFail($id);

        // Validar que no esté eliminado
        if ($inventario->estado == 2) {
            JsonResponse::error('El equipo ya fue eliminado.');
            return;
        }

        $inventario->update([
            'estado' => 2
        ]);

        JsonResponse::success('Equipo eliminado correctamente.');
    }

    public function createManual(): void
    {
        try {

            $idEquipo = Request::input('id_equipo');

            if (
                !$idEquipo ||
                !isset($_FILES['archivo'])
            ) {

                JsonResponse::error(
                    'Debe seleccionar un archivo.'
                );

                return;
            }

            $validator = new FileValidatorService();
            if (!$validator->isValidMimeType($_FILES['archivo']['tmp_name'], ['application/pdf'])) {
                JsonResponse::error(
                    'El tipo de archivo no es válido o está corrupto. Solo se permiten PDF.'
                );
                exit;
            }

            $equipo = InventarioEquipo::findOrFail(
                $idEquipo
            );

            $file = $_FILES['archivo'];

            $extension = strtolower(
                pathinfo(
                    $file['name'],
                    PATHINFO_EXTENSION
                )
            );

            $nombreArchivo = sprintf(
                'MANUAL-EQUIPO-%d-%d.%s',
                $equipo->id,
                time(),
                $extension
            );

            $carpeta = dirname(__DIR__, 2) .
                '/public/uploads/archivos/manuales/';

            if (!is_dir($carpeta)) {

                mkdir(
                    $carpeta,
                    0777,
                    true
                );
            }

            $destino = $carpeta .
                $nombreArchivo;

            if (
                !move_uploaded_file(
                    $file['tmp_name'],
                    $destino
                )
            ) {

                JsonResponse::error(
                    'No fue posible guardar el archivo.'
                );

                return;
            }

            $manual = InventarioEquipoManual::create([

                'id_equipo' => $equipo->id,

                'archivo' => $nombreArchivo

            ]);

            JsonResponse::success('Manual agregado correctamente.', [
                'manual' => [
                    'id' => $manual->id,
                    'fecha_hora' => optional(
                        $manual->fecha_hora
                    )?->format('Y-m-d H:i'),
                    'archivo' => $manual->archivo,
                    'url' => '/uploads/archivos/manuales/' .
                        $manual->archivo
                ]
            ]);
        } catch (\Throwable $e) {

            JsonResponse::error(
                $e->getMessage()
            );
        }
    }

    public function deleteManual(): void
    {
        try {

            $id = (int) Request::Input('id');

            $manual = InventarioEquipoManual::findOrFail(
                $id
            );

            $archivo = PUBLIC_PATH .
                '/uploads/archivos/manuales/' .
                $manual->archivo;

            if (file_exists($archivo)) {

                unlink($archivo);
            }

            $manual->delete();

            JsonResponse::success(
                'Manual eliminado correctamente.'
            );
        } catch (\Throwable $e) {

            JsonResponse::error(
                'Error del servidor.'
            );
        }
    }

    public function manuales(
        int $idEquipo
    ): void {
        $manuales = InventarioEquipoManual::query()
            ->where(
                'id_equipo',
                $idEquipo
            )
            ->orderByDesc('id')
            ->get()
            ->map(function ($manual) {

                return [

                    'id' => $manual->id,

                    'fecha_hora' => optional(
                        $manual->fecha_hora
                    )?->format('Y-m-d H:i'),

                    'archivo' => $manual->archivo,

                    'url' => '/uploads/archivos/manuales/' .
                        $manual->archivo

                ];
            });

        JsonResponse::custom([
            "success" => true,
            'data' => $manuales
        ]);
    }

    public function pdf(): void
    {
        $realizadoPor = Usuario::select('tb_usuarios.nombre')
            ->join(
                'sgm_autorizado',
                'sgm_autorizado.id_usuario',
                '=',
                'tb_usuarios.id'
            )
            ->where(
                'tb_usuarios.id_gas',
                $this->estacionId()
            )
            ->where(
                'sgm_autorizado.estado',
                1
            )
            ->value('nombre');

        $estacion = Estacion::findOrFail(
            $this->estacionId()
        );

        $equipos = InventarioEquipo::with('manuales')
            ->where(
                'id_estacion',
                $this->estacionId()
            )
            ->where(
                'estado',
                '<',
                2
            )
            ->orderByDesc('nombre')
            ->get();

        $css = file_get_contents(
            ROOT_PATH . '/public/assets/css/pdf.css'
        );

        $html = '
    <!DOCTYPE html>
    <html>

    <head>

        <meta charset="UTF-8">

        <title>Inventario de equipo</title>

        <style>' . $css . '</style>

    </head>

    <body>

    <table class="table table-bordered">

        <tbody>

            <tr>

                <td rowspan="2" align="center">
                    ' . $estacion->razonsocial . '
                </td>

                <td rowspan="2" align="center">
                    <b>Inventario de equipo</b>
                </td>

                <td align="center">
                    <b>Fecha de autorización: 01-01-2024</b>
                </td>

            </tr>

            <tr>

                <td align="center">
                    Fo.SGM.011
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

        </tbody>

    </table>

    <br>

    <table class="table table-bordered table-sm">

        <thead>

            <tr>

                <th>#</th>

                <th>Nombre del equipo de medición</th>

                <th>Identificación</th>

                <th>Función que desempeña dentro de la ES</th>

                <th>Fecha de instalación</th>

                <th>Manuales, garantías o información documental</th>

            </tr>

        </thead>

        <tbody>';

        if ($equipos->isEmpty()) {

            $html .= '

            <tr>

                <td colspan="6" align="center">
                    No se encontró información para mostrar
                </td>

            </tr>';
        }

        foreach ($equipos as $index => $equipo) {

            $manuales = $equipo->manuales
                ->map(function ($manual) {

                    return '<a style="font-size:.7em;" href="' .
                        base_url() .
                        '/uploads/archivos/manuales/' .
                        $manual->archivo .
                        '">' .
                        $manual->archivo .
                        '</a>';
                })
                ->implode('<br>');

            $html .= '

        <tr>

            <td align="center">' . ($index + 1) . '</td>

            <td>' . $equipo->nombre . '</td>

            <td align="center">' . $equipo->identificacion . '</td>

            <td>' . $equipo->funcion . '</td>

            <td align="center">' .
                (
                    $equipo->fecha_instalacion
                    ? formatearFecha(
                        $equipo->fecha_instalacion
                    )
                    : ''
                ) .
                '</td>

            <td>' . $manuales . '</td>

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

        $options->setChroot(
            ROOT_PATH . '/public'
        );

        $options->setDefaultFont('Arial');

        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($html);

        $dompdf->setPaper(
            'A4',
            'landscape'
        );

        $dompdf->render();

        $dompdf->stream(
            'Inventario de equipo.pdf',
            [
                'Attachment' => false
            ]
        );
    }
}
