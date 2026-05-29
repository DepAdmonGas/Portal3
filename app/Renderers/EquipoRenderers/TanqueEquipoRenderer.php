<?php

namespace App\Renderers\EquipoRenderers;

class TanqueEquipoRenderer implements EquipoRendererInterface
{
    public function render(object $registro): string
    {
        $html = '<table>';

        foreach ($registro->tanques as $item) {
            $html .= '<tr>';
            $html .= '<td class="text-center">' . $item->detalle . '</td>';
            $html .= '<td width="120" class="text-center">' . $item->resultado . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';

        return $html;
    }
}
