<?php

namespace App\Reports\Sgm;

use App\Models\Estacion;
use App\Models\Sgm\Autorizado;
use App\Models\Sgm\InventarioNormatividadAplicable;

class FormatoSgm5
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

        $realizadoPor = $this->autorizadoSgm($idestacion);

        /*
         * Encabezado Fo.SGM.005
         */
        $contenido .= '<table class="table table-sm table-bordered align-middle">';
        $contenido .= '<tbody>';

        $contenido .= '<tr>';

        $contenido .= '<td rowspan="2" align="center" valign="middle">';
        $contenido .= $this->e($estacion->razonsocial);
        $contenido .= '</td>';

        $contenido .= '<td rowspan="2" align="center" valign="middle">';
        $contenido .= '<b>Inventario de Normatividad Aplicable</b>';
        $contenido .= '</td>';

        $contenido .= '<td align="center" valign="middle">';
        $contenido .= '<b>Fecha de autorización: 01-01-2024</b>';
        $contenido .= '</td>';

        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td align="center" valign="middle">';
        $contenido .= 'Fo.SGM.005';
        $contenido .= '</td>';
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

        /*
         * Inventario de normatividad aplicable
         */
        $registros = InventarioNormatividadAplicable::query()
            ->from('sgm_inventario_normatividad_aplicable')
            ->where(function ($query) use ($idestacion) {
                $query->where('estado', $idestacion)
                    ->orWhere('estado', 0);
            })
            ->get();

        $contenido .= '<table class="table table-sm table-bordered align-middle">';
        $contenido .= '<thead>';

        $contenido .= '<tr>';
        $contenido .= '<th align="center" valign="middle">#</th>';
        $contenido .= '<th align="center" valign="middle">Norma, acuerdo o disposición</th>';
        $contenido .= '<th align="center" valign="middle">Fecha de publicación</th>';
        $contenido .= '<th align="center" valign="middle">Fecha de aplicación</th>';
        $contenido .= '<th align="center" valign="middle">Equipo o procedimiento de medición al que aplica</th>';
        $contenido .= '<th align="center" valign="middle">Link</th>';
        $contenido .= '</tr>';

        $contenido .= '</thead>';
        $contenido .= '<tbody>';

        if ($registros->isEmpty()) {
            $contenido .= '<tr>';
            $contenido .= '<td colspan="6" align="center">';
            $contenido .= 'No se encontró información para mostrar';
            $contenido .= '</td>';
            $contenido .= '</tr>';
        } else {
            $numero = 1;

            foreach ($registros as $registro) {
                $fechaAplicacionRaw = $registro->getRawOriginal('fecha_aplicacion');

                $fechaAplicacion = (
                    empty($fechaAplicacionRaw)
                    || $fechaAplicacionRaw === '0000-00-00'
                )
                    ? 'S/I'
                    : $this->formatoFecha($fechaAplicacionRaw);

                $contenido .= '<tr>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $numero;
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $this->e($registro->norma);
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $this->formatoFecha($registro->fecha_publicacion->format('Y-m-d'));
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $fechaAplicacion;
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $this->e($registro->equipo);
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= '<a href="' . $this->e($registro->link) . '">LINK</a>';
                $contenido .= '</td>';

                $contenido .= '</tr>';

                $numero++;
            }
        }

        $contenido .= '</tbody>';
        $contenido .= '</table>';

        $contenido .= '<hr><br>';

        return $contenido;
    }

    private function autorizadoSgm(int $idestacion): string
    {
        $registro = Autorizado::query()
            ->from('sgm_autorizado')
            ->join(
                'tb_usuarios',
                'sgm_autorizado.id_usuario',
                '=',
                'tb_usuarios.id'
            )
            ->where('tb_usuarios.id_gas', $idestacion)
            ->where('sgm_autorizado.estado', 1)
            ->select('tb_usuarios.nombre')
            ->first();

        return $registro?->nombre ?? 'S/I';
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

        return $this->e(
            $partes[2] . ' de ' . $mes . ' del ' . $partes[0]
        );
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
