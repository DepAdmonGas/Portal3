<?php

namespace App\Reports\Sgm;

use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Sgm\Autorizado;
use App\Models\Sasisopa\CursoCalendario;

class FormatoSgm10
{
    public function generar(int $idestacion, int $year): string
    {
        $estacion = Estacion::find($idestacion);

        if (!$estacion) {
            return '';
        }

        $realizadoPor = $this->realizadoPor($idestacion);

        $contenido = '';

        /*
         * Encabezado Fo.SGM.010
         */
        $contenido .= '<table class="table table-sm table-bordered align-middle">';
        $contenido .= '<tbody>';

        $contenido .= '<tr>';

        $contenido .= '<td rowspan="2" align="center" valign="middle">';
        $contenido .= $this->e($estacion->razonsocial);
        $contenido .= '</td>';

        $contenido .= '<td rowspan="2" align="center" valign="middle">';
        $contenido .= '<b>Capacitación de inducción</b>';
        $contenido .= '</td>';

        $contenido .= '<td align="center" valign="middle">';
        $contenido .= '<b>Fecha de autorización: 01-01-2024</b>';
        $contenido .= '</td>';

        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td align="center" valign="middle">';
        $contenido .= 'Fo.SGM.010';
        $contenido .= '</td>';
        $contenido .= '</tr>';

        $contenido .= '<tr>';

        $contenido .= '<td align="center" valign="middle">';
        $contenido .= 'Realizado por:<br>' . $this->e($realizadoPor);
        $contenido .= '</td>';

        $contenido .= '<td align="center" valign="middle">';
        $contenido .= 'Revisado por:<br>Eduardo Galicia Flores';
        $contenido .= '</td>';

        $contenido .= '<td align="center" valign="middle">';
        $contenido .= 'Autorizado por:<br>' . $this->e($estacion->apoderado_legal);
        $contenido .= '</td>';

        $contenido .= '</tr>';

        $contenido .= '</tbody>';
        $contenido .= '</table>';

        /*
         * Cursos de inducción
         *
         * Código original:
         *
         * tb_cursos_calendario
         * INNER JOIN tb_cursos_temas
         *
         * id_estacion = $idestacion
         * observaciones = Inducción
         * categoria = SGM
         * YEAR(fecha_programada) = $year
         */
        $cursos = CursoCalendario::query()
            ->from('tb_cursos_calendario')
            ->join(
                'tb_cursos_temas',
                'tb_cursos_calendario.id_tema',
                '=',
                'tb_cursos_temas.id'
            )
            ->where('tb_cursos_calendario.id_estacion', $idestacion)
            ->where('tb_cursos_calendario.observaciones', 'Inducción')
            ->where('tb_cursos_temas.categoria', 'SGM')
            ->whereYear('tb_cursos_calendario.fecha_programada', $year)
            ->orderBy('tb_cursos_calendario.fecha_programada', 'asc')
            ->get([
                'tb_cursos_calendario.id',
                'tb_cursos_calendario.fecha_programada',
                'tb_cursos_calendario.fecha_real',
                'tb_cursos_calendario.id_estacion',
                'tb_cursos_calendario.id_personal',
                'tb_cursos_calendario.id_tema',
                'tb_cursos_calendario.resultado',
                'tb_cursos_calendario.observaciones',
                'tb_cursos_calendario.estado',
                'tb_cursos_temas.num_tema',
                'tb_cursos_temas.categoria',
                'tb_cursos_temas.titulo',
            ]);

        $contenido .= '<table class="table table-sm table-bordered align-middle">';
        $contenido .= '<thead>';

        $contenido .= '<tr>';
        $contenido .= '<th align="center" valign="middle">No</th>';
        $contenido .= '<th align="center" valign="middle">Nombre</th>';
        $contenido .= '<th align="center" valign="middle">Fecha de Ingreso</th>';
        $contenido .= '<th align="center" valign="middle">Nombre del curso de inducción</th>';
        $contenido .= '<th align="center" valign="middle">El curso fue impartido por personal externo o interno</th>';
        $contenido .= '<th align="center" valign="middle">Fecha de la toma del curso</th>';
        $contenido .= '<th align="center" valign="middle">Evidencia</th>';
        $contenido .= '</tr>';

        $contenido .= '</thead>';
        $contenido .= '<tbody>';

        if ($cursos->isEmpty()) {
            $contenido .= '<tr>';
            $contenido .= '<td colspan="7" align="center">';
            $contenido .= 'No se encontró información para mostrar';
            $contenido .= '</td>';
            $contenido .= '</tr>';
        } else {
            $numero = 1;

            foreach ($cursos as $curso) {
                $usuario = $this->usuario((int) $curso->id_personal);

                $fechaProgramadaRaw = $curso->getRawOriginal('fecha_programada');

                $contenido .= '<tr>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $numero;
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $this->e($usuario['nombre']);
                $contenido .= '</td>';

                $contenido .= '<td valign="middle">';
                $contenido .= $usuario['fecha_ingreso'];
                $contenido .= '</td>';

                $contenido .= '<td valign="middle">';
                $contenido .= $this->e($curso->titulo);
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= 'Interno';
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $this->fechaSegura($fechaProgramadaRaw);
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $this->evidenciaCurso(
                    (int) $curso->id,
                    (int) $curso->resultado
                );
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

    private function realizadoPor(int $idestacion): string
    {
        return Autorizado::query()
            ->join(
                'tb_usuarios',
                'tb_usuarios.id',
                '=',
                'sgm_autorizado.id_usuario'
            )
            ->where('tb_usuarios.id_gas', $idestacion)
            ->where('sgm_autorizado.estado', 1)
            ->value('tb_usuarios.nombre') ?? 'S/I';
    }

    private function usuario(int $idUsuario): array
    {
        if ($idUsuario <= 0) {
            return [
                'nombre' => '',
                'fecha_ingreso' => 'S/I',
            ];
        }

        $usuario = Usuario::query()
            ->from('tb_usuarios')
            ->where('id', $idUsuario)
            ->first();

        if (!$usuario) {
            return [
                'nombre' => '',
                'fecha_ingreso' => 'S/I',
            ];
        }

        $fechaIngresoRaw = $usuario->getRawOriginal('fecha_ingreso');

        return [
            'nombre' => $usuario->nombre ?? '',
            'fecha_ingreso' => $this->fechaSegura($fechaIngresoRaw),
        ];
    }

    private function evidenciaCurso(int $idCurso, int $resultado): string
    {
        if ($resultado === 0) {
            return 'S/I';
        }

        if ($resultado >= 60) {
            return '<a href="'
                . $this->e($_ENV['APP_URL'] . '/cursos/descargar/' . $idCurso)
                . '">Descargar</a>';
        }

        return 'S/I';
    }

    private function fechaSegura(mixed $fecha): string
    {
        $fecha = trim((string) ($fecha ?? ''));

        if (
            $fecha === ''
            || $fecha === '0000-00-00'
            || $fecha === '0000-00-00 00:00:00'
        ) {
            return 'S/I';
        }

        return $this->formatoFecha($fecha);
    }

    private function formatoFecha(string $fecha): string
    {
        $fecha = substr($fecha, 0, 10);

        $partes = explode('-', $fecha);

        if (
            count($partes) !== 3
            || !checkdate(
                (int) $partes[1],
                (int) $partes[2],
                (int) $partes[0]
            )
        ) {
            return 'S/I';
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

        return $partes[2]
            . ' de '
            . ($meses[$partes[1]] ?? $partes[1])
            . ' del '
            . $partes[0];
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
