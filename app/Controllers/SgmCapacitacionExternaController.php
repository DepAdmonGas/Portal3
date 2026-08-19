<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Request;
use App\Core\JsonResponse;
use App\Services\ModuloService;
use App\Models\Usuario;
use App\Models\Estacion;
use App\Models\Sgm\Autorizado;

use App\Models\Sgm\ProgramaAnualCapacitacionExterna;
use App\Models\Sgm\ProgramaAnualCapacitacionExternaPersonal;
use App\Models\Sgm\ProgramaAnualCapacitacionExternaEvidencia;

use Dompdf\Dompdf;
use Dompdf\Options;

class SgmCapacitacionExternaController extends BaseController
{
    protected string $modulo = 'sgm';

    public function index()
    {
        $title = 'Programa Capacitacion Externa';
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
                '/libs/select2/dist/css/select2.min.css',
                '/css/select2-modal.css?v=1.0'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/libs/select2/dist/js/select2.full.min.js',
                '/libs/select2/dist/js/select2.min.js',
                '/js/sgm/gestion-recursos/capacitacionexterna.actions.init.js?v=1.6.1',
                '/js/sgm/gestion-recursos/capacitacionexterna.datatable.init.js?v=1.4.1',

            ],
            'help' => true
        ];

        View::render('sgm/gestion-recursos/capacitacion-externa', $data, 'sgm');
    }

    public function datatable(int $year): void
    {
        $data = ProgramaAnualCapacitacionExterna::with([
            'personal.usuario',
            'evidencias'
        ])
            ->where(
                'id_estacion',
                $this->estacionId()
            )
            ->whereYear(
                'fecha_programada',
                $year
            )
            ->orderBy(
                'fecha_programada',
                'desc'
            )
            ->get()
            ->map(function ($curso) {

                return [

                    'id' => $curso->id,

                    'curso' => $curso->nombre_curso,

                    'tipo' => $curso->tipo_capacitacion,

                    'fecha_programada' => optional(
                        $curso->fecha_programada
                    )?->format('Y-m-d'),

                    'duracion' => $curso->duracion,

                    'instructor' => $curso->instructor,

                    'fecha_real' => $curso->fecha_real
                        ? $curso->fecha_real->format('Y-m-d')
                        : 'S/I',

                    'personal' => $curso->personal
                        ->pluck('usuario.nombre')
                        ->filter()
                        ->values(),

                    'evidencias' => $curso->evidencias
                        ->map(function ($archivo) {

                            return [

                                'nombre' => basename($archivo->archivo),

                                'url' => 'uploads/archivos/sgm/'
                                    . $archivo->archivo

                            ];
                        })
                        ->values()

                ];
            });

        JsonResponse::custom([
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

    public function detalle(int $id)
    {
        $capacitacion = ProgramaAnualCapacitacionExterna::with([
            'personal.usuario',
            'evidencias'
        ])->findOrFail($id);

        $usuarios = Usuario::query()
            ->where('id_gas', $this->estacionId())
            ->where('estatus', 0)
            ->whereNotIn(
                'id',
                $capacitacion->personal->pluck('id_usuario')
            )
            ->orderBy('nombre')
            ->get([
                'id',
                'nombre'
            ]);

        JsonResponse::custom([
            'id'               => $capacitacion->id,
            'nombre_curso'     => $capacitacion->nombre_curso,
            'fecha_programada' => optional($capacitacion->fecha_programada)->format('Y-m-d'),
            'duracion'         => $capacitacion->duracion,
            'instructor'       => $capacitacion->instructor,
            'fecha_real'       => optional($capacitacion->fecha_real)->format('Y-m-d'),

            // Todos los usuarios
            'usuarios' => $usuarios,

            // Usuarios seleccionados
            'personal' => $capacitacion->personal->map(function ($item) {
                return [
                    'id' => $item->id,
                    'id_usuario' => $item->id_usuario,
                    'nombre' => $item->usuario->nombre,
                ];
            }),

            'evidencias' => $capacitacion->evidencias,
        ]);
    }

    public function create()
    {
        try {

            $nombrecurso     = Request::jsonInput('nombre_curso');
            $capacitacion    = Request::jsonInput('capacitacion');
            $fechaprogramada = Request::jsonInput('fecha_programada');
            $duracion        = Request::jsonInput('duracion');
            $instructor      = Request::jsonInput('instructor');

            if (
                !$nombrecurso ||
                !$capacitacion ||
                !$fechaprogramada ||
                !$duracion ||
                !$instructor
            ) {
                JsonResponse::error(
                    'Complete los campos obligatorios.'
                );
                return;
            }

            $realizadoPor = Autorizado::query()
                ->select('id_usuario')
                ->where('estado', 1)
                ->whereHas('usuario', function ($query) {
                    $query->where(
                        'id_gas',
                        $this->estacionId()
                    );
                })
                ->value('id_usuario') ?? 0;

            ProgramaAnualCapacitacionExterna::create([
                'id_estacion'       => $this->estacionId(),
                'id_personal'       => $this->userId(),
                'nombre_curso'      => $nombrecurso,
                'tipo_capacitacion' => $capacitacion,
                'fecha_programada'  => $fechaprogramada,
                'duracion'          => $duracion,
                'instructor'        => $instructor,
                'fecha_real'        => null,
                'realizadopor'      => $realizadoPor,
                'estado'            => 0,
            ]);

            JsonResponse::success(
                'Registro agregado correctamente.'
            );
        } catch (\Throwable $e) {

            JsonResponse::error('Registro no agregado');
        }
    }

    public function update()
    {
        $registro = ProgramaAnualCapacitacionExterna::findOrFail(
            Request::jsonInput('id')
        );

        $registro->update([
            'nombre_curso'      => Request::jsonInput('nombre_curso'),
            'fecha_programada'  => Request::jsonInput('fecha_programada'),
            'duracion'          => Request::jsonInput('duracion'),
            'instructor'        => Request::jsonInput('instructor'),
            'fecha_real'        => Request::jsonInput('fecha_real'),
            'estado'            => 1,
        ]);

        JsonResponse::success(
            'Registro actualizado correctamente.'
        );
    }

    public function delete(): void
    {
        $id = (int) Request::jsonInput('id');

        if ($id <= 0) {
            JsonResponse::error(
                'El identificador es inválido.'
            );
            return;
        }

        $capacitacion = ProgramaAnualCapacitacionExterna::find($id);

        if (!$capacitacion) {
            JsonResponse::error(
                'El registro no existe.'
            );
            return;
        }

        $capacitacion->delete();

        JsonResponse::success(
            'Registro eliminado correctamente.'
        );
    }


    public function guardarPersonal()
    {

        $id = Request::jsonInput('id_capacitacion');

        $usuarios = Request::jsonInput('usuarios');


        if (!$id || !is_array($usuarios)) {

            JsonResponse::error(
                'Datos incorrectos'
            );

            return;
        }

        $data = [];

        foreach ($usuarios as $usuario) {

            $data[] = [
                'id_capacitacion' => $id,
                'id_usuario' => $usuario
            ];
        }


        if (count($data) > 0) {

            ProgramaAnualCapacitacionExternaPersonal::insert(
                $data
            );
        }


        JsonResponse::success(
            'Personal agregado'
        );
    }

    public function deletePersonal()
    {
        $id = (int) Request::jsonInput('id');

        if ($id <= 0) {
            JsonResponse::error(
                'El identificador es inválido.'
            );
            return;
        }

        $personal = ProgramaAnualCapacitacionExternaPersonal::find($id);

        if (!$personal) {
            JsonResponse::error(
                'El registro no existe.'
            );
            return;
        }

        $personal->delete();

        JsonResponse::success(
            'Registro eliminado correctamente.'
        );
    }


    public function createEvidencia()
    {
        try {

            $idCapacitacion = Request::input('id_capacitacion');

            if (
                !$idCapacitacion ||
                !isset($_FILES['archivo'])
            ) {
                JsonResponse::error(
                    'Debe seleccionar un archivo.'
                );
                return;
            }

            $file = $_FILES['archivo'];

            $extension = pathinfo(
                $file['name'],
                PATHINFO_EXTENSION
            );

            $nombreArchivo = sprintf(
                'EVIDENCIA-%d-%d.%s',
                $idCapacitacion,
                time(),
                $extension
            );

            $carpetaFisica = dirname(__DIR__, 2) . '/public/uploads/archivos/sgm/';

            if (!is_dir($carpetaFisica)) {
                JsonResponse::error("La carpeta no existe: " . $carpetaFisica);
                return;
            }

            $destino = $carpetaFisica . $nombreArchivo;

            if (!move_uploaded_file(
                $file['tmp_name'],
                $destino
            )) {

                JsonResponse::error(
                    'No fue posible guardar el archivo.'
                );

                return;
            }

            $evidencia = ProgramaAnualCapacitacionExternaEvidencia::create([

                'id_capacitacion' => $idCapacitacion,
                'archivo' => $nombreArchivo,

            ]);

            JsonResponse::success(
                'Evidencia agregada.'
            );
        } catch (\Throwable $e) {

            JsonResponse::error(
                'Error del servidor'
            );
        }
    }

    public function deleteEvidencia()
    {
        try {

            $id = Request::input('id');

            $evidencia =
                ProgramaAnualCapacitacionExternaEvidencia::find($id);

            if (!$evidencia) {

                JsonResponse::error(
                    'Evidencia no encontrada.'
                );

                return;
            }

            $archivo = ROOT_PATH .
                '/public/archivos/sgm/' .
                $evidencia->archivo;

            if (is_file($archivo)) {
                unlink($archivo);
            }

            $evidencia->delete();

            JsonResponse::success(
                'Evidencia eliminada.'
            );
        } catch (\Throwable $e) {

            JsonResponse::error(
                'Error del servidor'
            );
        }
    }

    public function pdf(int $year)
    {

        $estacion = Estacion::findOrFail($this->estacionId());
        $realizadoPor = Autorizado::query()
            ->with('usuario')
            ->where('estado', 1)
            ->whereHas('usuario', fn($q) => $q->where('id_gas', $this->estacionId()))
            ->first();

        $capacitaciones = ProgramaAnualCapacitacionExterna::with([
            'personal.usuario',
            'evidencias'
        ])
            ->where('id_estacion', $this->estacionId())
            ->whereYear('fecha_programada', $year)
            ->orderByDesc('fecha_programada')
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
        <body>';

        // Encabezado
        $html .= '
    <table border="1" class="table table-sm table-bordered">
        <tr>
            <td rowspan="2" align="center">' . $estacion->razonsocial . '</td>
            <td rowspan="2" align="center">
                <strong>Programa anual de capacitación interna y externa</strong>
            </td>
            <td align="center">
                <strong>Fecha de autorización: 01-01-2024</strong>
            </td>
        </tr>
        <tr>
            <td align="center">Fo.SGM.009</td>
        </tr>
        <tr>
            <td align="center">
                Realizado por:
                ' . ($realizadoPor?->usuario?->nombre ?? '') . '
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
    ';

        // Tabla principal
        $html .= '
    <table class="table table-sm table-bordered" style="font-size: 14px">
        <thead>
            <tr>
                <th>No</th>
                <th>Nombre del curso</th>
                <th>Tipo capacitación</th>
                <th>Fecha programada</th>
                <th>Duración</th>
                <th>Instructor</th>
                <th>Fecha real de la toma del curso</th>
                <th>Nombre de las personas que asistieron al curso</th>
                <th>Evidencia</th>
            </tr>
        </thead>
        <tbody>';

        foreach ($capacitaciones as $i => $curso) {

            $personal = $curso->personal
                ->map(fn($p) => $p->usuario?->nombre)
                ->filter()
                ->implode(', ');

            $evidencias = $curso->evidencias
                ->map(function ($evidencia) {

                    $url =  base_url() . '/uploads/archivos/sgm/' . $evidencia->archivo;

                    return sprintf(
                        '<a href="%s" target="_blank">%s</a>',
                        $url,
                        $evidencia->archivo
                    );
                })
                ->implode(', ');

            $fechaReal = $curso->fecha_real
                ? formatearFecha($curso->fecha_real->format('Y-m-d'))
                : 'S/I';

            $html .= '
        <tr>
            <td>' . ($i + 1) . '</td>
            <td>' . $curso->nombre_curso . '</td>
            <td>' . $curso->tipo_capacitacion . '</td>
            <td>' . formatearFecha($curso->fecha_programada->format('Y-m-d')) . '</td>
            <td>' . $curso->duracion . '</td>
            <td>' . $curso->instructor . '</td>
            <td>' .
                $fechaReal
                . '</td>
            <td><small>' . $personal . '</small></td>
            <td><small>' . $evidencias . '</small></td>
        </tr>';
        }

        if ($capacitaciones->isEmpty()) {
            $html .= '
        <tr>
            <td colspan="9" align="center">
                No se encontró información para mostrar.
            </td>
        </tr>';
        }

        $html .= '
        </tbody>
    </table>
    </body>
    </html>';

        $dompdf = new Dompdf();

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->stream(
            'Programa anual de capacitacion interna y externa.pdf',
            ['Attachment' => false]
        );
    }
}
