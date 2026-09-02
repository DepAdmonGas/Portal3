<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Sgm\CalibracionEquipo;
use App\Models\Sgm\CalibracionEquipoDispensario;
use App\Models\Sasisopa\Dispensario;
use App\Services\CalibracionEquipoService;
use App\Services\CalibracionDispensarioService;
use App\Services\ModuleStationService;

class CalibracionDispensarioController extends BaseController
{
    protected string $modulo = 'sasisopa';

    private function estacionModulo(): ?int
    {
        return ModuleStationService::getContext('sasisopa')['id_estacion'] ?? null;
    }

    public function index(int $id)
    {
        $title = 'Bitácora calibración de equipos (Dispensario)';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');
        Breadcrumb::add(
            '10. CONTROL DE ACTIVIDADES Y PROCESOS',
            '/sasisopa/control-actividades-procesos'
        );
        Breadcrumb::add(
            'Calibración de Equipos',
            '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos'
        );
        Breadcrumb::add($title, '');

        $calibracion = CalibracionEquipo::query()
        ->when(
            $this->estacionModulo(),
            fn ($q, $est) => $q->where('id_estacion', $est)
        )
        ->select([
            'id',
            'id_estacion',
            'id_usuario',
            'folio',
            'fecha',
            'hora',
            'fecha_termino',
            'hora_termino',
            'equipo',
            'observaciones',
            'responsable_verificacion',
            'resultados',
            'categoria',
            'estado',
        ])
        ->with([

        'detalles' => function ($query) {
            $query->select([
                'id',
                'id_calibracion',
                'categoria',
                'resultado',
            ]);
        },

        'dispensarios' => function ($query) {
            $query->select([
                'id',
                'id_calibracion',
                'id_dispensario',
                'resultado1',
                'resultado2',
                'resultado3',
                'resultado4',
            ]);
        },

        'dispensarios.dispensario' => function ($query) {
            $query->select([
                'id',
                'no_dispensario',
                'marca',
                'modelo',
                'serie',
                'producto1',
                'producto2',
                'producto3',
                'estado',
            ]);
        }

    ])
    ->findOrFail($id);

    $detalles = $calibracion->detalles
        ->pluck('resultado', 'categoria')
        ->toArray();

    // Agregar propiedades al objeto
    $calibracion->unidad_verificacion = $detalles['Unidad de verificación'] ?? '';
    $calibracion->numero_acreditacion = $detalles['No. de acreditación'] ?? '';
   
    $calibracion->fecha_formateada =
    $calibracion->fecha &&
    $calibracion->fecha->year > 1900
        ? $calibracion->fecha->format('Y-m-d')
        : '';

    $calibracion->fecha_termino_formateada =
    $calibracion->fecha_termino &&
    $calibracion->fecha_termino->year > 1900
        ? $calibracion->fecha_termino->format('Y-m-d')
        : '';

        $permisos = ModuloService::permisosSesion($this->modulo);
        
        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'estacionId' => $this->estacionModulo(),
            'moduleStationKey' => 'sasisopa',
            'filtro_usuario' => $this->filtro_usuario,

            'calibracion' => $calibracion,

            'unidadVerificacion' =>
                $calibracion->detalles
                    ->firstWhere(
                        'categoria',
                        'Unidad de verificación'
                    )?->resultado ?? '',

            'numeroAcreditacion' =>
                $calibracion->detalles
                    ->firstWhere(
                        'categoria',
                        'No. de acreditación'
                    )?->resultado ?? '',
'ocultarSelectorEstacion'=> true,
            'links' => [],
            'scripts' => [
                    '/js/vendor.min.js',
                    '/js/core/module-station-selector.js?v=' . time(),
                    '/js/controlactividadproceso/calibraciondispensario.init.js?v=' . time(),
                
            ],
            'help' => false
        ];

        View::render(
            'controlactividadproceso/calibracion-dispensario',
            $data,
            'sasisopa'
        );
    }

    public function update()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'),true);

        if (!ModuloService::validaPermiso($this->modulo,'editar')) {

            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para editar'
            ]);

            return;
        }

        $id = (int) ($data['id'] ?? 0);
        $input = (int) ($data['input'] ?? 0);
        $valor = sanitize_input($data['valor'] ?? '','string');

        try {

            $service = new CalibracionDispensarioService;
            $actualizado = $service->actualizar($id,$input,$valor);

            echo json_encode([
                'success' => $actualizado,
                'message' => $actualizado
                    ? 'Registro actualizado correctamente'
                    : 'No fue posible actualizar'
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => 'Error al actualizar',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getDispensarios()
    {
        header('Content-Type: application/json');
        $idCalibracion = (int) ($_GET['id_calibracion'] ?? 0);
        $calibracion = CalibracionEquipo::find($idCalibracion);

        if (!$calibracion) {
            echo json_encode([
                'success' => false,
                'message' => 'Calibración no encontrada'
            ]);
            return;
        }

        $idsOcupados = CalibracionEquipoDispensario::query()
            ->where('id_calibracion',$idCalibracion)
            ->pluck('id_dispensario')
            ->toArray();

        $dispensarios = Dispensario::query()
            ->where('id_estacion',$calibracion->id_estacion)
            ->where('estado',1)
            ->when(
                count($idsOcupados) > 0,
                fn ($q) => $q->whereNotIn('id',$idsOcupados)
            )
            ->orderBy('no_dispensario')
            ->get([
                'id',
                'no_dispensario',
                'marca',
                'modelo',
                'serie'
            ]);

        echo json_encode([
            'success' => true,
            'data' => $dispensarios
        ]);
    }

    public function create()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'),true);

        try {

            $registro = CalibracionEquipoDispensario::create([
                    'id_calibracion' => $data['id_calibracion'],
                    'id_dispensario' => $data['id_dispensario'],
                    'resultado1' => '',
                    'resultado2' => '',
                    'resultado3' => '',
                    'resultado4' => ''
                ]);

            $registro->load(
                'dispensario'
            );

            echo json_encode([

                'success' => true,
                'message' => 'Dispensario agregado',
                'dispensario' => $registro
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => 'Error al agregar'
            ]);

        }
    }

    public function delete()
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'),true);

        $registro = CalibracionEquipoDispensario::find($data['id']);

        if (!$registro) {
            echo json_encode([
                'success' => false,
                'message' => 'Registro no encontrado'
            ]);
            return;
        }

        $registro->delete();

        echo json_encode([
            'success' => true,
            'message' =>'Dispensario eliminado'
        ]);
    }

    public function finalizar()
    {
        $data = json_decode(file_get_contents('php://input'),true);

        try {

        $service = new CalibracionEquipoService();
        $service->finalizar((int) $data['id']);

            echo json_encode([
                'success' => true,
                'message' => 'Calibración finalizada correctamente'
            ]);

        } catch (\Throwable $e) {

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}