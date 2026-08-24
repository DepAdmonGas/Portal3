<?php
namespace App\Controllers;
use App\Core\View;
use App\Services\ModuloService;
use App\Core\Breadcrumb;
use App\Models\Estacion;
use App\Models\Sasisopa\Implementacionsa;
use App\Models\Sasisopa\ImplementacionsaDetalle;

use Illuminate\Database\Capsule\Manager as Capsule;
class ImplementacionSAController extends BaseController{
    protected string $modulo = 'sasisopa';

    public function index(){

        $title = 'IMPLEMENTACION DEL SA';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add('14. MONITOREO, VERIFICACIÓN Y EVALUACIÓN', '/sasisopa/monitoreo-verificacion-evaluacion');
        Breadcrumb::add($title, '');

        $permisos = ModuloService::permisosSesion($this->modulo);

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'links' =>[
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'    
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/monitoreoverificacionevaluacion/implementacionsa.datatable.init.js?v=' . time(),
                '/js/monitoreoverificacionevaluacion/implementacionsa.actions.init.js?v=' . time(),
            ],
            'help' => false
        ];
        
        View::render('monitoreoverificacionevaluacion/implementacionsa', $data,'sasisopa');
    }

    public function datatable(){


   $implementaciones = Implementacionsa::query()
    ->with('usuario:id,nombre')
    ->where('id_estacion', $this->estacionId())
    ->orderByDesc('fecha')
    ->get();

    $data = $implementaciones->map(
    function (Implementacionsa $item, int $index) {

        $respuestasNo = $item->preguntas - $item->respuestas;

        $resultado = $item->puntos >= 60
            ? sprintf(
                '<span class="text-success fw-bolder">%.0f%% Excelente</span>',
                $item->puntos
            )
            : sprintf(
                '<span class="text-warning fw-bolder">%.0f%% Regular</span>',
                $item->puntos
            );

        return [
            'id' => $item->id,
            'numero' => $index + 1,

            'responsable' => e(
                $item->usuario?->nombre ?? 'Sin responsable'
            ),

            'fecha' => 
                $item->fecha->format('Y-m-d'),

            'fecha_larga' => formatearFecha(
                $item->fecha->format('Y-m-d')
            ),

            'preguntas' => $item->preguntas,

            'si' => sprintf(
                '<span class="text-success">%d</span>',
                $item->respuestas
            ),

            'no' => sprintf(
                '<span class="text-danger">%d</span>',
                $respuestasNo
            ),

            'resultado' => $resultado,

            'acciones' => sprintf(
                '
                <button
                    class="btn btn-info btn-sm"
                    onclick="ModalDetalle(%d)">
                    <i class="fa fa-eye"></i>
                </button>

                <button
                    class="btn btn-primary btn-sm"
                    onclick="ModalEditar(%d)">
                    <i class="fa fa-edit"></i>
                </button>
                ',
                $item->id,
                $item->id
            )
        ];
    }
)->values();


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

    public function create()
    {
        header('Content-Type: application/json');

        try {

            $data = json_decode(file_get_contents('php://input'),true);
            $fecha = $data['fecha'];
            $preguntas = $data['preguntas'] ?? [];

            if (count($preguntas) !== 18) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Debes responder las 36 preguntas'
                ]);
                return;
            }

            $this->createImplementacion(
                $this->estacionId(),
                $this->userId(),
                $fecha,
                $preguntas
            );

            echo json_encode([
                'success' => true,
                'message' => 'Cuestionario guardado correctamente'
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

    public static function createImplementacion(
        int $estacionId,
        int $usuarioId,
        string $fecha,
        array $grupos
    ): bool {

        return Capsule::transaction(function () use (
            $estacionId,
            $usuarioId,
            $fecha,
            $grupos
        ) {

            $totalPreguntas = 0;
            $resultado = 0;
            $detalle = [];

            foreach ($grupos as $grupo) {

                foreach ($grupo['preguntas'] as $pregunta) {

                    $respuesta = (int) $pregunta['respuesta'];

                    $totalPreguntas++;

                    $resultado += $respuesta;

                    $detalle[] = [
                        'pregunta'  => $pregunta['id'] . '. ' . $pregunta['texto'],
                        'respuesta' => $respuesta === 1
                            ? 'Si'
                            : 'No',
                        'resultado' => $respuesta
                    ];
                }
            }

            $promedio = round(
                ($resultado / $totalPreguntas) * 100,
                2
            );

            $implementacion = Implementacionsa::create([
                'id_estacion' => $estacionId,
                'id_usuario'  => $usuarioId,
                'fecha'       => $fecha . ' ' . date('H:i:s'),
                'preguntas'   => $totalPreguntas,
                'respuestas'  => $resultado,
                'puntos'      => $promedio
            ]);

            foreach ($detalle as &$item) {

                $item['id_implementacion']
                    = $implementacion->id;

            }

            ImplementacionsaDetalle::insert($detalle);

            return true;
        });
    }


    public function update()
    {
        header('Content-Type: application/json');

        try {

            $data = json_decode(file_get_contents('php://input'),true);
            $id = (int) $data['id'];
            $fecha = $data['fecha'];
            $preguntas = $data['preguntas'] ?? [];

            if (count($preguntas) !== 18) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Información incompleta'
                ]);

                return;
            }

            $this->updateImplementacion(
                $id,
                $fecha,
                $preguntas
            );

            echo json_encode([
                'success' => true,
                'message' => 'Implementación actualizada correctamente'
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

    public static function updateImplementacion(
    int $id,
    string $fecha,
    array $grupos
    ): bool {

        return Capsule::transaction(function () use (
            $id,
            $fecha,
            $grupos
        ) {

            $implementacion = Implementacionsa::findOrFail($id);

            $totalPreguntas = 0;
            $resultado = 0;
            $detalle = [];

            foreach ($grupos as $grupo) {

                foreach ($grupo['preguntas'] as $pregunta) {

                    $respuesta = (int) $pregunta['respuesta'];

                    $totalPreguntas++;

                    $resultado += $respuesta;

                    $detalle[] = [
                        'id_implementacion' => $id,
                        'pregunta' => $pregunta['id'] . '. ' . $pregunta['texto'],
                        'respuesta' => $respuesta === 1
                            ? 'Si'
                            : 'No',
                        'resultado' => $respuesta
                    ];
                }
            }

            $promedio = round(
                ($resultado / $totalPreguntas) * 100,
                2
            );

            $implementacion->update([
                'fecha'      => $fecha . ' ' . date('H:i:s'),
                'preguntas'  => $totalPreguntas,
                'respuestas' => $resultado,
                'puntos'     => $promedio
            ]);

            ImplementacionsaDetalle::where(
                'id_implementacion',
                $id
            )->delete();

            ImplementacionsaDetalle::insert($detalle);

            return true;
        });
    }

    public static function getImplementacion(int $id): array
    {
        $implementacion = Implementacionsa::findOrFail($id);

        $detalle = ImplementacionsaDetalle::where('id_implementacion', $id)
        ->orderBy('id')
        ->get([
            'pregunta',
            'resultado'
        ]);

        return [
            'id' => $implementacion->id,
            'fecha' => $implementacion->fecha->format('Y-m-d'),
            'fecha_larga' => formatearFecha($implementacion->fecha->format('Y-m-d')),
            'detalle' => $detalle
        ];
    }

    public function get(int $id)
    {
        header('Content-Type: application/json');

        try {

            echo json_encode([
                'success' => true,
                'data' => self::getImplementacion($id)
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        exit;
    }

}