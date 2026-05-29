<?php

namespace App\Renderers\EquipoRenderers;

class ExtintorEquipoRenderer implements EquipoRendererInterface
{
    public function render(object $registro): string
    {
        $html = '<table>';
        $html .= '<tr>';
        $html .= '<th class="text-center">No. Extintor</th>';
        $html .= '<th class="text-center">Ubicación</th>';
        $html .= '<th class="text-center">Ult. Recarga</th>';
        $html .= '<th class="text-center">Tipo</th>';
        $html .= '<th class="text-center">Peso Kg</th>';
        $html .= '<th class="text-center">Manómetro</th>';
        $html .= '<th class="text-center">Boquilla</th>';
        $html .= '<th class="text-center">Manguera</th>';
        $html .= '<th class="text-center">Funcionalidad</th>';
        $html .= '<th class="text-center">Observaciones</th>';
        $html .= '</tr>';

        foreach ($registro->extintores as $item) {
            $ext = $item->extintor;

            $html .= '<tr>';
            $html .= '<td class="text-center"><b>' . ($ext?->no_extintor ?? '') . '</b></td>';
            $html .= '<td class="text-center">' . ($ext?->ubicacion ?? '') . '</td>';
            $html .= '<td class="text-center">' . ($item->ultima_recarga ? \Carbon\Carbon::parse($item->ultima_recarga)->format('d-m-Y') : '') . '</td>';
            $html .= '<td class="text-center">' . ($ext?->tipo_extintor ?? '') . '</td>';
            $html .= '<td class="text-center">' . ($ext?->peso_kg ?? '') . '</td>';
            $html .= '<td class="text-center">' . $item->manometro . '</td>';
            $html .= '<td class="text-center">' . $item->boquilla_descarga . '</td>';
            $html .= '<td class="text-center">' . $item->manguera . '</td>';
            $html .= '<td class="text-center">' . $item->funcionalidad . '</td>';
            $html .= '<td class="text-center">' . $item->observaciones . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';

        return $html;
    }
}
