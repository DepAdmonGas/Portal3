<?php

namespace App\Services;

use App\Models\Sgm\CalibracionEquipo;
use App\Models\Sgm\CalibracionEquipoDetalle;
use App\Models\Sgm\CalibracionEquipoDispensario;
use App\Models\Sasisopa\DispensarioAperturaBitacora;

class CalibracionDispensarioService
{
    public function actualizar(
        int $id,
        int $input,
        mixed $valor
    ): bool {

        return match ($input) {

            1 => $this->actualizarFecha(
                $id,
                $valor
            ),

            2 => CalibracionEquipo::whereKey($id)
                ->update(['hora' => $valor]),

            3 => $this->actualizarDetalle(
                $id,
                'Unidad de verificación',
                $valor
            ),

            4 => $this->actualizarDetalle(
                $id,
                'No. de acreditación',
                $valor
            ),

            5 => CalibracionEquipo::whereKey($id)
                ->update([
                    'observaciones' => $valor
                ]),

            6 => CalibracionEquipo::whereKey($id)
                ->update([
                    'responsable_verificacion' => $valor
                ]),

            7 => CalibracionEquipoDispensario::whereKey($id)
                ->update([
                    'resultado1' => $valor
                ]),

            8 => CalibracionEquipoDispensario::whereKey($id)
                ->update([
                    'resultado2' => $valor
                ]),

            9 => CalibracionEquipoDispensario::whereKey($id)
                ->update([
                    'resultado3' => $valor
                ]),

            10 => CalibracionEquipoDispensario::whereKey($id)
                ->update([
                    'resultado4' => $valor
                ]),

            11 => CalibracionEquipo::whereKey($id)
                ->update([
                    'categoria' => (int) $valor
                ]),

            12 => CalibracionEquipo::whereKey($id)
                ->update([
                    'fecha_termino' => $valor
                ]),

            13 => CalibracionEquipo::whereKey($id)
                ->update([
                    'hora_termino' => $valor
                ]),

            default => false
        };
    }

    private function actualizarDetalle(
        int $idCalibracion,
        string $categoria,
        string $valor
    ): bool {

        return CalibracionEquipoDetalle::query()
            ->where('id_calibracion', $idCalibracion)
            ->where('categoria', $categoria)
            ->update([
                'resultado' => $valor
            ]) > 0;
    }

    private function actualizarFecha(
        int $id,
        string $fecha
    ): bool {

        $calibracion = CalibracionEquipo::find($id);

        if (!$calibracion) {
            return false;
        }

        $fechaAnterior = $calibracion->fecha;

        if (
            $calibracion->equipo === 'Dispensario'
            && !empty($fechaAnterior)
        ) {

            DispensarioAperturaBitacora::query()
                ->where('fecha', $fechaAnterior)
                ->where('clave', 'CALI')
                ->where('motivo', 'Ajuste')
                ->update([
                    'fecha' => $fecha
                ]);
        }

        return $calibracion->update([
            'fecha' => $fecha
        ]);
    }
}