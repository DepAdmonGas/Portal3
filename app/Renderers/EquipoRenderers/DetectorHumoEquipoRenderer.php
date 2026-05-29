<?php

namespace App\Renderers\EquipoRenderers;

class DetectorHumoEquipoRenderer implements EquipoRendererInterface
{
    public function render(object $registro): string
    {
        $html = '<table>';
        $html .= '<tr>';
        $html .= '<th class="text-center">Número</th>';
        $html .= '<th class="text-center">Ubicación</th>';
        $html .= '<th class="text-center">Revisión</th>';
        $html .= '<th class="text-center">Resultado</th>';
        $html .= '</tr>';

        foreach ($registro->detectoresHumo as $item) {
            $detector = $item->detector;

            $html .= '<tr>';
            $html .= '<td class="text-center"><b>' . ($detector?->no_detector ?? '') . '</b></td>';
            $html .= '<td class="text-center">' . ($detector?->ubicacion ?? '') . '</td>';
            $html .= '<td class="text-center">' . $item->revision . '</td>';
            $html .= '<td class="text-center">' . $item->resultado . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';

        return $html;
    }
}
