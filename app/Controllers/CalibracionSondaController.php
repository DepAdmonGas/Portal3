<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Models\Sgm\CalibracionEquipo;
use App\Models\Sgm\CalibracionEquipoDetalle;
use App\Models\Sgm\CalibracionEquipoSonda;
use App\Services\CalibracionEquipoService;

class CalibracionSondaController extends BaseController
{
    protected string $modulo = 'sasisopa';

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
            'filtro_usuario' => $this->filtro_usuario,

            'calibracion' => $calibracion,

            'links' => [],

            'scripts' => [
                '/js/vendor.min.js',
                '/js/controlactividadproceso/calibracionsondas.init.js?v=1.0'
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