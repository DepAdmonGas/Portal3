<?php

namespace App\Renderers;

use App\Helpers\ImageHelper;
use App\Models\Estacion;
use App\Renderers\EquipoRenderers\EquipoRendererFactory;
use Carbon\Carbon;

class PdfMantenimientoRenderer
{
    private const EQUIPO_EXTINTORES = 20;

    private EquipoRendererFactory $rendererFactory;

    public function __construct()
    {
        $this->rendererFactory = new EquipoRendererFactory();
    }

    public function htmlInicial(Estacion $estacion): string
    {
        $logo = ImageHelper::base64(ImageHelper::logoUrl());

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                @page { margin: 0.5cm 1cm; }
                body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #212529; font-size: 0.8em; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #dee2e6; padding: 5px; vertical-align: top; }
                .text-center { text-align: center; }
                .table-active { background: #f5f5f5; }
                .table-danger { background: #f5c6cb; }
                .page-break { page-break-before: always; }
                .firma { width: 100px; height: auto; }
                .evidencia { width: 300px; height: 300px; object-fit: cover; padding: 5px; }
                .mt-3 { margin-top: 15px; }
                .mt-4 { margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class="text-center"><img src="{$logo}" style="width:300px;"></div>
            <div class="text-center"><strong>Mantenimiento Preventivo</strong></div>
            <div class="text-center"><strong>{$estacion->permisocre}</strong></div>
            <div class="text-center">{$estacion->razonsocial}</div>
            <div class="text-center">{$estacion->direccioncompleta}</div>
            <div class="text-center">Código: DLES/SA/002</div>
        HTML;
    }

    public function renderEquipo(object $equipo, \Illuminate\Support\Collection $registros): string
    {
        $html = '<div class="mt-4">';
        $html .= '<div style="font-size:18px;">Equipo: <strong>' . $equipo->detalle . '</strong></div>';
        $html .= '</div>';

        foreach ($registros as $registro) {
            $html .= $this->renderRegistro($registro);
        }

        return $html;
    }

    private function renderRegistro(object $registro): string
    {
        if ($registro->id_equipo === self::EQUIPO_EXTINTORES) {
            return $this->renderRegistroExtintor($registro);
        }

        $firmaRealiza = $registro->firmas->where('tipo_firma', 'FPR')->first();
        $firmaSupervisa = $registro->firmas->where('tipo_firma', 'FPS')->first();
        $estado = $registro->estado == 2 ? 'table-danger' : '';
        $folio = str_pad($registro->folio, 3, '0', STR_PAD_LEFT);
        $fechaFin = $registro->fechaFin;

        $fecha = $fechaFin?->fechafin
            ? formatearFecha($fechaFin->fechafin)
            : '';

        $hora = $fechaFin?->horafin
            ? date('g:i a', strtotime($fechaFin->horafin))
            : '';

        $observaciones = $fechaFin?->observaciones ?? '';

        $detalleHtml = $this->rendererFactory
            ->create($registro->id_equipo)
            ->render($registro);

        return <<<HTML
        <table class="mt-3">
            <tr class="table-active">
                <th width="70">Folio</th>
                <th width="100">Fecha</th>
                <th width="80">Hora</th>
                <th>Equipo y Resultado</th>
                <th width="130">Observaciones</th>
                <th width="90">Realiza</th>
                <th width="90">Supervisa</th>
            </tr>
            <tr>
                <td class="text-center {$estado}"><strong>{$folio}</strong></td>
                <td class="text-center {$estado}">{$fecha}</td>
                <td class="text-center {$estado}">{$hora}</td>
                <td class="{$estado}" style="font-size:0.75em;">{$detalleHtml}</td>
                <td class="text-center {$estado}" style="font-size:0.75em;">{$observaciones}</td>
                <td class="text-center" style="font-size:0.75em;">{$this->renderFirma($firmaRealiza)}</td>
                <td class="text-center" style="font-size:0.75em;">{$this->renderFirma($firmaSupervisa)}</td>
            </tr>
            {$this->renderEvidencias($registro)}
        </table>
        HTML;
    }

    private function renderRegistroExtintor(object $registro): string
    {
        $firmaRealiza = $registro->firmas->where('tipo_firma', 'FPR')->first();
        $firmaSupervisa = $registro->firmas->where('tipo_firma', 'FPS')->first();
        $folio = str_pad($registro->folio, 3, '0', STR_PAD_LEFT);
        $fechaFin = $registro->fechaFin;

        $fecha = $fechaFin?->fechafin
            ? formatearFecha($fechaFin->fechafin)
            : '';

        $hora = $fechaFin?->horafin
            ? date('g:i a', strtotime($fechaFin->horafin))
            : '';

        $observaciones = $fechaFin?->observaciones ?? '';

        $html = '
        <table class="mt-3">
            <tr>
                <th width="70">Folio</th>
                <th width="100">Fecha</th>
                <th width="80">Hora</th>
                <th>Observaciones</th>
                <th width="140">Realiza</th>
                <th width="140">Supervisa</th>
            </tr>
            <tr>
                <td class="text-center"><strong>' . $folio . '</strong></td>
                <td class="text-center">' . $fecha . '</td>
                <td class="text-center">' . $hora . '</td>
                <td>' . $observaciones . '</td>
                <td class="text-center">' . $this->renderFirma($firmaRealiza) . '</td>
                <td class="text-center">' . $this->renderFirma($firmaSupervisa) . '</td>
            </tr>
        </table>';

        $html .= $this->rendererFactory
            ->create($registro->id_equipo)
            ->render($registro);

        $html .= $this->renderEvidenciasExtintor($registro);

        return $html;
    }

    private function renderEvidenciasExtintor(object $registro): string
    {
        if ($registro->evidencias->isEmpty()) {
            return '';
        }

        $imagenes = '';

        foreach ($registro->evidencias as $evidencia) {
            $src = ImageHelper::base64($evidencia->url);
            if ($src) {
                $imagenes .= '<img class="evidencia" src="' . $src . '">';
            }
        }

        if (empty($imagenes)) {
            return '';
        }

        return '<div class="mt-3"><strong>Evidencias</strong></div><div>' . $imagenes . '</div>';
    }

    private function renderFirma(?object $firma): string
    {
        if (!$firma) {
            return '';
        }

        $imagen = '';

        if (!empty($firma->usuario?->firma)) {
            $src = ImageHelper::base64(
                ImageHelper::firmaUrl($firma->usuario->firma)
            );
            if ($src) {
                $imagen = '<img class="firma" src="' . $src . '">';
            }
        } elseif (!empty($firma->imagen_firma)) {
            $src = ImageHelper::base64(
                ImageHelper::firmaUrl($firma->imagen_firma)
            );
            if ($src) {
                $imagen = '<img class="firma" src="' . $src . '">';
            }
        }

        return $imagen . '<div style="font-size:11px;">' . $firma->nombre . '</div>';
    }

    private function renderEvidencias(object $registro): string
    {
        if ($registro->evidencias->isEmpty()) {
            return '';
        }

        $imagenes = '';

        foreach ($registro->evidencias as $evidencia) {
            $src = ImageHelper::base64($evidencia->url);
            if ($src) {
                $imagenes .= '<img class="evidencia" src="' . $src . '">';
            }
        }

        if (empty($imagenes)) {
            return '';
        }

        return <<<HTML
        <tr>
            <td colspan="7">
                <div><strong>Evidencias</strong></div>
                {$imagenes}
            </td>
        </tr>
        HTML;
    }
}
