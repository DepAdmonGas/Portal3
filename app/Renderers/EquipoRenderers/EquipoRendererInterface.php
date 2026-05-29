<?php

namespace App\Renderers\EquipoRenderers;

interface EquipoRendererInterface
{
    public function render(object $registro): string;
}
