<?php

namespace App\Renderers\EquipoRenderers;

class HermeticidadEquipoRenderer implements EquipoRendererInterface
{
    public function render(object $registro): string
    {
        $html = '<table>';
        $html .= '<tr>';
        $html .= '<th class="text-center">Fecha</th>';
        $html .= '<th class="text-center">Hora inicio</th>';
        $html .= '<th class="text-center">Hora termino</th>';
        $html .= '<th class="text-center">Tanque</th>';
        $html .= '<th class="text-center">Producto</th>';
        $html .= '<th class="text-center">Resultado</th>';
        $html .= '</tr>';

        foreach ($registro->pruebasHermeticidad as $item) {
            $tanque = $item->tanque;

            $html .= '<tr>';
            $html .= '<td class="text-center">' . ($item->fecha ? \Carbon\Carbon::parse($item->fecha)->format('d-m-Y') : '') . '</td>';
            $html .= '<td class="text-center">' . $item->hora_inicio . '</td>';
            $html .= '<td class="text-center">' . $item->hora_termino . '</td>';
            $html .= '<td class="text-center"><b>' . ($tanque?->no_tanque ?? '') . '</b></td>';
            $html .= '<td class="text-center">' . ($tanque?->producto ?? '') . '</td>';
            $html .= '<td class="text-center">' . $item->resultado . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';

        return $html;
    }
}
