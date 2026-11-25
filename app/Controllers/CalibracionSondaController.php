<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Sgm\CalibracionEquipo;
use App\Models\Sgm\CalibracionEquipoDetalle;
use App\Models\Sgm\CalibracionEquipoSonda;
use App\Models\Sasisopa\SondasMedicion;
use App\Services\CalibracionEquipoService;
use App\Services\ModuleStationService;

class CalibracionSondaController extends BaseController
{
    protected string $modulo = 'sasisopa';

    private function estacionModulo(): ?int
    {
        return ModuleStationService::getContext('sasisopa')['id_estacion'] ?? null;
    }

    public function index(int $id)
    {
        $title = 'Bitácora calibración de equipos (Sondas de medición)';

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
            ->where('equipo', 'Sondas de medición')
            ->with([
                'detalles',
                'sondas.sonda'
            ])
            ->findOrFail($id);

        // ==========================================
        // CAMPOS PARA ALPINE
        // ==========================================

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

        $calibracion->metodo_calibracion =
            optional(
                $calibracion->detalles
                    ->firstWhere(
                        'categoria',
                        'Método usado para la calibración'
                    )
            )->resultado ?? '';

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

        // ORIGINAL PARA COMPARAR CAMBIOS
        $calibracion->sondas
            ->each(function ($item) {

                $item->_original = [
                    'resultado1' =>
                        $item->resultado1
                ];
            });

        $permisos = ModuloService::permisosSesion(
            $this->modulo
        );

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'estacionId' => $this->estacionModulo(),
            'moduleStationKey' => 'sasisopa',
            'filtro_usuario' => $this->filtro_usuario,

            'calibracion' => $calibracion,
'ocultarSelectorEstacion'=> true,
            'links' => [],

            'scripts' => [
                '/js/vendor.min.js',
                '/js/core/module-station-selector.js?v=' . time(),
                '/js/controlactividadproceso/calibracionsondas.init.js?v=' . time(),
            ],

            'help' => false
        ];

        View::render('controlactividadproceso/calibracion-sondas',$data,'sasisopa');
    }

    public function update(){

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

            $actualizado = $this->editarCalibracionSonda($valor,$id,$input);

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

    private function calibracionPendiente(int $id): ?CalibracionEquipo
    {
        $estacionId = $this->estacionModulo();
        if (!$estacionId) {
            return null;
        }

        $calibracion = CalibracionEquipo::query()
            ->where('id', $id)
            ->where('id_estacion', $estacionId)
            ->where('equipo', 'Sondas de medición')
            ->where('estado', 0)
            ->first();

        return $calibracion;
    }

    public function getSondas(): void
    {
        header('Content-Type: application/json');
        $idCalibracion = (int) ($_GET['id_calibracion'] ?? 0);

        if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tienes permiso para agregar sondas a esta calibración']);
            return;
        }

        $calibracion = $this->calibracionPendiente($idCalibracion);
        if (!$calibracion) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'La calibración no está disponible para la estación actual']);
            return;
        }

        $idsOcupados = CalibracionEquipoSonda::query()
            ->where('id_calibracion', $idCalibracion)
            ->pluck('id_sonda')
            ->toArray();

        $sondas = SondasMedicion::query()
            ->where('id_estacion', $calibracion->id_estacion)
            ->when($idsOcupados, fn ($query) => $query->whereNotIn('id', $idsOcupados))
            ->orderBy('no_sonda')
            ->get(['id', 'no_sonda', 'marca', 'modelo']);

        echo json_encode(['success' => true, 'data' => $sondas]);
    }

    public function create(): void
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $idCalibracion = (int) ($data['id_calibracion'] ?? 0);
        $idSonda = (int) ($data['id_sonda'] ?? 0);

        if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tienes permiso para agregar sondas']);
            return;
        }

        $calibracion = $this->calibracionPendiente($idCalibracion);
        $sonda = $calibracion
            ? SondasMedicion::query()->where('id', $idSonda)->where('id_estacion', $calibracion->id_estacion)->first()
            : null;

        if (!$calibracion || !$sonda) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'La calibración o la sonda seleccionada no está disponible']);
            return;
        }

        if (CalibracionEquipoSonda::query()->where('id_calibracion', $idCalibracion)->where('id_sonda', $idSonda)->exists()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'La sonda ya está agregada a esta calibración']);
            return;
        }

        $registro = CalibracionEquipoSonda::create([
            'id_calibracion' => $idCalibracion,
            'id_sonda' => $idSonda,
            'resultado1' => '',
        ])->load('sonda');

        echo json_encode(['success' => true, 'message' => 'Sonda agregada', 'sonda' => $registro]);
    }

    public function delete(): void
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
        $id = (int) ($data['id'] ?? 0);

        if (!ModuloService::validaPermiso($this->modulo, 'eliminar')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tienes permiso para eliminar sondas']);
            return;
        }

        $registro = CalibracionEquipoSonda::find($id);
        if (!$registro || !$this->calibracionPendiente((int) $registro->id_calibracion)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'La sonda no pertenece a una calibración pendiente de esta estación']);
            return;
        }

        $registro->delete();
        echo json_encode(['success' => true, 'message' => 'Sonda eliminada']);
    }

    public function editarCalibracionSonda($contenido, $id, $input)
{
    return match ((int) $input) {

        1 => CalibracionEquipo::where('id', $id)
            ->update(['fecha' => $contenido]),

        2 => CalibracionEquipo::where('id', $id)
            ->update(['hora' => $contenido]),

        3 => CalibracionEquipoDetalle::where('id_calibracion', $id)
            ->where('categoria', 'Unidad de verificación')
            ->update(['resultado' => $contenido]),

        4 => CalibracionEquipoDetalle::where('id_calibracion', $id)
            ->where('categoria', 'No. de acreditación')
            ->update(['resultado' => $contenido]),

        5 => CalibracionEquipoDetalle::where('id_calibracion', $id)
            ->where('categoria', 'Método usado para la calibración')
            ->update(['resultado' => $contenido]),

        6 => CalibracionEquipo::where('id', $id)
            ->update(['observaciones' => $contenido]),

        7 => CalibracionEquipo::where('id', $id)
            ->update(['responsable_verificacion' => $contenido]),

        8 => CalibracionEquipoSonda::where('id', $id)
            ->update(['resultado1' => $contenido]),

        default => false,
    };
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
