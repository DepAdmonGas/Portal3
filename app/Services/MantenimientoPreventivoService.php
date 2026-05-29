<?php

namespace App\Services;

use App\Models\Estacion;
use App\Models\Sasisopa\MantenimientoVerificar;
use App\Models\Sasisopa\MantenimientoLista;
use App\Renderers\PdfMantenimientoRenderer;
use Dompdf\Dompdf;
use Dompdf\Options;

class MantenimientoPreventivoService
{
    private PdfMantenimientoRenderer $renderer;

    public function __construct(
        private readonly int $idestacion
    ) {
        $this->renderer = new PdfMantenimientoRenderer();
    }

    public function generarPdf(): void
    {
        ['id' => $id, 'year' => $year, 'mes' => $mes, 'equipo' => $equipo] = $this->filtros();

        $estacion = Estacion::find($this->idestacion);

        if (!$estacion) {
            throw new \RuntimeException('Estación no encontrada: ' . $this->idestacion);
        }

        $equipos = $this->obtenerEquipos($id, $year, $mes, $equipo);

        $html = $this->renderer->htmlInicial($estacion);

        foreach ($equipos as $index => $itemEquipo) {
            

            $registros = $this->queryRegistros($id, $year, $mes, $itemEquipo->id)->get();

            if ($registros->isEmpty()) {
                continue;
            }

            $html .= $this->renderer->renderEquipo($itemEquipo, $registros);

            if ($index + 1 < count($equipos)) {
                $html .= '<div class="page-break"></div>';
            }
        }

        $html .= '</body></html>';

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('LEGAL', 'landscape');
        $dompdf->render();
        $dompdf->stream('Reporte_Mantenimiento_Preventivo.pdf', ['Attachment' => true]);
    }

    private function queryRegistros(?int $id, ?int $year, ?int $mes, int $equipoId): \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
    {
        return MantenimientoVerificar::query()
            ->with([
                'fechaFin',
                'detalles.catalogo.sublista',
                'firmas.usuario:id,nombre,firma',
                'evidencias',
                'extintores.extintor',
                'tanques',
                'pruebasHermeticidad.tanque',
                'detectoresHumo.detector',
            ])
            ->where('id_estacion', $this->idestacion)
            ->where('id_equipo', $equipoId)
            ->when($id,fn ($q) => $q->where('id', $id))
            ->when($year, fn($q) => $q->whereYear('fechacreacion', $year))
            ->when($mes, fn($q) => $q->whereMonth('fechacreacion', $mes))
            ->where('estado', '>=', 1)
            ->orderBy('folio');
    }

    private function obtenerEquipos(?int $id, ?int $year, ?int $mes, mixed $equipo): \Illuminate\Support\Collection
    {

      if ($id) {

        $mantenimiento = MantenimientoVerificar::query()
            ->select('id_equipo')
            ->where('id_estacion', $this->idestacion)
            ->find($id);

        if (!$mantenimiento) {
            return collect();
        }

        return MantenimientoLista::query()
            ->where('id', $mantenimiento->id_equipo)
            ->get();
    }

    return MantenimientoLista::query()

        ->when(
            !empty($equipo),
            fn ($q) => $q->where('id', $equipo)
        )

        ->whereHas('verificaciones', function ($q) use ($year, $mes) {

            $q->where('id_estacion', $this->idestacion)

                ->when(
                    $year,
                    fn ($qq) => $qq->whereYear('fechacreacion', $year)
                )

                ->when(
                    $mes,
                    fn ($qq) => $qq->whereMonth('fechacreacion', $mes)
                );
        })

        ->orderBy('num_lista')
        ->get();
    }

    private function filtros(): array
    {
        return [
            'id' => sanitize_input($_GET['id'] ?? null, 'int'),
            'year' => sanitize_input($_GET['year'] ?? null, 'int'),
            'mes' => sanitize_input($_GET['mes'] ?? null, 'int'),
            'equipo' => $_GET['equipo'] ?? '',
        ];
    }
}
