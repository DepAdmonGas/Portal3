<?php

namespace App\Renderers\EquipoRenderers;

class EquipoRendererFactory
{
    private const EQUIPOS_CON_SUBLISTAS = [2, 33, 42, 49, 50, 51, 52, 53, 57];
    private const EQUIPO_EXTINTORES = 20;
    private const EQUIPO_TANQUES = 43;
    private const EQUIPO_HERMETICIDAD = 45;
    private const EQUIPO_DETECTOR_HUMO = 48;

    public function create(int $equipoId): EquipoRendererInterface
    {
        return match (true) {
            $equipoId === self::EQUIPO_EXTINTORES   => new ExtintorEquipoRenderer(),
            $equipoId === self::EQUIPO_TANQUES       => new TanqueEquipoRenderer(),
            $equipoId === self::EQUIPO_HERMETICIDAD  => new HermeticidadEquipoRenderer(),
            $equipoId === self::EQUIPO_DETECTOR_HUMO => new DetectorHumoEquipoRenderer(),
            in_array($equipoId, self::EQUIPOS_CON_SUBLISTAS, true) => new SublistaEquipoRenderer(),
            default => new DefaultEquipoRenderer(),
        };
    }
}
