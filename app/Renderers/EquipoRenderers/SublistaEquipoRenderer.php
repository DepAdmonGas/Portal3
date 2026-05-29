<?php

namespace App\Renderers\EquipoRenderers;

use App\Models\Sasisopa\MantenimientoDetalle;
use Illuminate\Support\Collection;

class SublistaEquipoRenderer implements EquipoRendererInterface
{
    private static array $templateCache = [];

    public function render(object $registro): string
    {
        $equipoId = $registro->id_equipo;

        $templateDetalles = $this->getTemplateDetalles($equipoId);
        $resultados = $registro->detalles->keyBy('id_detalle');

        $html = '<table>';

        foreach ($templateDetalles as $sublistaId => $detalles) {
            $sublistaName = $detalles->first()->sublista?->detalle ?? '';

            $html .= '<tr><td colspan="2" class="table-active"><strong>' . $sublistaName . '</strong></td></tr>';

            foreach ($detalles as $detalle) {
                $resultado = $resultados->get($detalle->id)?->resultado ?? 'X';
                $html .= '<tr>';
                $html .= '<td>' . $detalle->detalle . '</td>';
                $html .= '<td width="120" class="text-center">' . $resultado . '</td>';
                $html .= '</tr>';
            }
        }

        $html .= '</table>';

        return $html;
    }

    private function getTemplateDetalles(int $equipoId): Collection
    {
        if (!isset(self::$templateCache[$equipoId])) {
            self::$templateCache[$equipoId] = MantenimientoDetalle::with('sublista')
                ->where('id_lista', $equipoId)
                ->get()
                ->groupBy(fn(MantenimientoDetalle $d) => $d->id_sublista);
        }

        return self::$templateCache[$equipoId];
    }
}
