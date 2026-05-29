<?php

namespace App\Renderers\EquipoRenderers;

class DefaultEquipoRenderer implements EquipoRendererInterface
{
    public function render(object $registro): string
    {
        $html = '<table>';

        foreach ($registro->detalles as $detalle) {
            $html .= '<tr>';
            $html .= '<td>' . ($detalle->catalogo?->detalle ?? '') . '</td>';
            $html .= '<td width="120" class="text-center">' . $detalle->resultado . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';

        return $html;
    }
}
