<?php

namespace App\Reports\Sgm;

use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Sgm\RevisionProcedimientoRegistro;
use App\Models\Sgm\RevisionProcedimientoRegistroDetalle;

class FormatoSgm2
{
    public function generar(int $idestacion, int $year): string
    {
        $contenido = '';

        $estacion = Estacion::query()
            ->from('tb_estaciones')
            ->select([
                'razonsocial',
                'apoderado_legal',
                'rfc',
                'direccioncompleta',
                'permisocre',
            ])
            ->where('id', $idestacion)
            ->first();

        if (!$estacion) {
            return '';
        }

        $registros = RevisionProcedimientoRegistro::query()
            ->from('sgm_revision_procedimiento_registro')
            ->where('id_estacion', $idestacion)
            ->whereYear('fecha', $year)
            ->get();

        foreach ($registros as $registro) {
            $idRegistro = (int) $registro->id;
            $realizadoPor = $this->realizadoPor((int) $registro->realizadopor);

            $contenido .= '<table class="table table-sm table-bordered align-middle">';
            $contenido .= '<tbody>';

            $contenido .= '<tr>';
            $contenido .= '<td rowspan="2" align="center" valign="middle">';
            $contenido .= $this->e($estacion->razonsocial);
            $contenido .= '</td>';

            $contenido .= '<td rowspan="2" align="center" valign="middle">';
            $contenido .= '<b>Revisión del SGM, procedimientos y registros</b>';
            $contenido .= '</td>';

            $contenido .= '<td align="center" valign="middle">';
            $contenido .= '<b>Fecha de autorización: 01-01-2024</b>';
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td align="center" valign="middle">Fo.SGM.002</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td align="center" valign="middle">';
            $contenido .= 'Realizado por: ' . $this->e($realizadoPor);
            $contenido .= '</td>';

            $contenido .= '<td align="center" valign="middle">';
            $contenido .= 'Revisado por:<br> Eduardo Galicia Flores';
            $contenido .= '</td>';

            $contenido .= '<td align="center" valign="middle">';
            $contenido .= 'Autorizado por:<br> ' . $this->e($estacion->apoderado_legal);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '</tbody>';
            $contenido .= '</table>';


            $contenido .= $this->revision($registro);

            $contenido .= '<h4 class="mt-2">SGM</h4>';
            $contenido .= $this->detalleRevision($idRegistro, 'SGM');

            $contenido .= '<h4 class="mt-2">Procedimientos</h4>';
            $contenido .= $this->detalleRevision($idRegistro, 'Procedimientos');

            $contenido .= '<h4 class="mt-2">Registros</h4>';
            $contenido .= $this->detalleRevision($idRegistro, 'Registros');
        }

        $contenido .= '<hr><br>';

        return $contenido;
    }

    private function revision(object $registro): string
    {
        $contenido = '';

        $contenido .= '<table class="table table-sm table-bordered align-middle">';

        $contenido .= '<tr>';
        $contenido .= '<td><b>Fecha:</b></td>';
        $contenido .= '<td><b>Hora:</b></td>';
        $contenido .= '<td><b>Lugar:</b></td>';
        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td>' . $this->formatoFecha($registro->fecha) . '</td>';
        $contenido .= '<td>' . $this->formatoHora($registro->hora) . '</td>';
        $contenido .= '<td>' . $this->e($registro->lugar) . '</td>';
        $contenido .= '</tr>';

        $contenido .= '</table>';

        return $contenido;
    }

    private function detalleRevision(int $idRegistro, string $categoria): string
    {
        $contenido = '';

        $detalles = RevisionProcedimientoRegistroDetalle::query()
            ->from('sgm_revision_procedimiento_registro_detalle')
            ->where('id_revision', $idRegistro)
            ->where('categoria', $categoria)
            ->get();

        if ($detalles->isEmpty()) {
            return '<div>No se encontró información</div>';
        }

        foreach ($detalles as $detalle) {
            $contenido .= '<div class="mt-1"><b>' . $this->e($detalle->pregunta) . '</b></div>';

            $contenido .= '<table class="table table-sm table-bordered align-middle">';
            $contenido .= '<tr>';
            $contenido .= '<td>' . $this->e($detalle->respuesta) . '</td>';
            $contenido .= '</tr>';
            $contenido .= '</table>';
        }

        return $contenido;
    }

    private function realizadoPor(int $usuario): string
    {
        return (string) (
            Usuario::query()
            ->from('tb_usuarios')
            ->where('id', $usuario)
            ->value('nombre') ?? ''
        );
    }

    private function formatoFecha($fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            $fecha = $fecha->format('Y-m-d');
        }

        $fecha = (string) ($fecha ?? '');

        if ($fecha === '' || $fecha === '0000-00-00') {
            return 'S/I';
        }

        $partes = explode('-', $fecha);

        if (count($partes) !== 3) {
            return $this->e($fecha);
        }

        $meses = [
            '01' => 'Enero',
            '02' => 'Febrero',
            '03' => 'Marzo',
            '04' => 'Abril',
            '05' => 'Mayo',
            '06' => 'Junio',
            '07' => 'Julio',
            '08' => 'Agosto',
            '09' => 'Septiembre',
            '10' => 'Octubre',
            '11' => 'Noviembre',
            '12' => 'Diciembre',
        ];

        $mes = $meses[$partes[1]] ?? $partes[1];

        return $this->e($partes[2] . ' de ' . $mes . ' del ' . $partes[0]);
    }

    private function formatoHora($hora): string
    {
        if ($hora instanceof \DateTimeInterface) {
            return $hora->format('g:i a');
        }

        if (empty($hora)) {
            return '';
        }

        $timestamp = strtotime((string) $hora);

        if ($timestamp === false) {
            return $this->e($hora);
        }

        return date('g:i a', $timestamp);
    }

    private function e(mixed $valor): string
    {
        return htmlspecialchars(
            (string) ($valor ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}
