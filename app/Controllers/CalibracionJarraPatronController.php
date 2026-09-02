<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Sgm\CalibracionEquipo;
use App\Models\Sgm\CalibracionEquipoDetalle;
use App\Models\Sgm\CalibracionEquipoJarra;
use App\Services\CalibracionEquipoService;
use App\Services\ModuleStationService;

class CalibracionJarraPatronController extends BaseController
{
    protected string $modulo = 'sasisopa';

    private function estacionModulo(): ?int
    {
        return ModuleStationService::getContext('sasisopa')['id_estacion'] ?? null;
    }

    public function index(int $id)
    {
        $title = 'Bitácora calibración de equipos (Jarra patron)';

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


        $permisos = ModuloService::permisosSesion($this->modulo);

        $calibracion = CalibracionEquipo::query()
            ->when(
                $this->estacionModulo(),
                fn ($q, $est) => $q->where('id_estacion', $est)
            )
            ->with([
                'detalles',
                'jarras.jarra'
            ])
            ->findOrFail($id);

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

        $calibracion->fecha_formateada =
            $calibracion->fecha &&
            $calibracion->fecha->year > 1900
                ? $calibracion->fecha->format('Y-m-d')
                : '';

        $calibracion->jarras
            ->each(function ($item) {

                $item->_original = [
                    'resultado1' =>
                        $item->resultado1
                ];
            });

        $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'estacionId' => $this->estacionModulo(),
            'moduleStationKey' => 'sasisopa',
            'filtro_usuario' => $this->filtro_usuario,
'ocultarSelectorEstacion'=> true,
            'calibracion' => $calibracion,

            'links' => [],

            'scripts' => [
                '/js/vendor.min.js',
                '/js/core/module-station-selector.js?v=' . time(),
                '/js/controlactividadproceso/calibracionjarrapatron.init.js?v=' . time(),
            ],

            'help' => false
        ];

        View::render('controlactividadproceso/calibracion-jarra-patron',$data,'sasisopa');
    }


public function update()
{
    header('Content-Type: application/json');

    $data = json_decode(
        file_get_contents('php://input'),
        true
    );

    if (!ModuloService::validaPermiso(
        $this->modulo,
        'editar'
    )) {

        echo json_encode([
            'success' => false,
            'message' => 'No tienes permiso'
        ]);

        return;
    }

    try {

        $actualizado = $this->editarCalibracionJarra(
            sanitize_input($data['valor'] ?? '', 'string'),
            (int)($data['id'] ?? 0),
            (int)($data['input'] ?? 0)
        );

        echo json_encode([
            'success' => (bool)$actualizado
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

private function editarCalibracionJarra(
    string $contenido,
    int $id,
    int $input
)
{
    return match ($input) {

        1 => CalibracionEquipo::where('id', $id)
            ->update(['fecha' => $contenido]),

        2 => CalibracionEquipo::where('id', $id)
            ->update(['hora' => $contenido]),

        3 => CalibracionEquipoDetalle::where(
                'id_calibracion',
                $id
            )
            ->where(
                'categoria',
                'Temperatura ambiente'
            )
            ->update([
                'resultado' => $contenido
            ]),

        4 => CalibracionEquipoDetalle::where(
                'id_calibracion',
                $id
            )
            ->where(
                'categoria',
                'Presión atmosférica'
            )
            ->update([
                'resultado' => $contenido
            ]),

        5 => CalibracionEquipoDetalle::where(
                'id_calibracion',
                $id
            )
            ->where(
                'categoria',
                'Humedad'
            )
            ->update([
                'resultado' => $contenido
            ]),

        6 => CalibracionEquipoDetalle::where(
                'id_calibracion',
                $id
            )
            ->where(
                'categoria',
                'Liquido usado en la calibración'
            )
            ->update([
                'resultado' => $contenido
            ]),

        7 => CalibracionEquipoDetalle::where(
                'id_calibracion',
                $id
            )
            ->where(
                'categoria',
                'Temperatura del líquido'
            )
            ->update([
                'resultado' => $contenido
            ]),

        8 => CalibracionEquipoDetalle::where(
                'id_calibracion',
                $id
            )
            ->where(
                'categoria',
                'Laboratorio de calibración'
            )
            ->update([
                'resultado' => $contenido
            ]),

        9 => CalibracionEquipoDetalle::where(
                'id_calibracion',
                $id
            )
            ->where(
                'categoria',
                'No. de acreditación'
            )
            ->update([
                'resultado' => $contenido
            ]),

        10 => CalibracionEquipoDetalle::where(
                'id_calibracion',
                $id
            )
            ->where(
                'categoria',
                'Método de calibración'
            )
            ->update([
                'resultado' => $contenido
            ]),

        11 => CalibracionEquipo::where('id', $id)
            ->update([
                'observaciones' => $contenido
            ]),

        12 => CalibracionEquipo::where('id', $id)
            ->update([
                'responsable_verificacion' =>
                    $contenido
            ]),

        13 => CalibracionEquipoJarra::where(
                'id',
                $id
            )
            ->update([
                'resultado1' => $contenido
            ]),

        default => false
    };
}

public function finalizar()
{
    $data = json_decode(
        file_get_contents('php://input'),
        true
    );

    try {

        $service = new CalibracionEquipoService();

        $service->finalizar(
            (int)$data['id']
        );

        echo json_encode([
            'success' => true
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

}