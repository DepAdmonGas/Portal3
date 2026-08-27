<?php

namespace App\Reports\Sgm;

use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Sgm\Autorizado;
use App\Models\Sasisopa\CursoCalendario;
use App\Models\Sgm\ProgramaAnualCapacitacionExterna;
use App\Models\Sgm\ProgramaAnualCapacitacionExternaPersonal;
use App\Models\Sgm\ProgramaAnualCapacitacionExternaEvidencia;

class FormatoSgm9
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
         * Encabezado Fo.SGM.009
         */
        $contenido .= '<table class="table table-sm table-bordered align-middle">';
        $contenido .= '<tbody>';

        $contenido .= '<tr>';

        $contenido .= '<td rowspan="2" align="center" valign="middle">';
        $contenido .= $this->e($estacion->razonsocial);
        $contenido .= '</td>';

        $contenido .= '<td rowspan="2" align="center" valign="middle">';
        $contenido .= '<b>Programa anual de capacitacion interna y externa</b>';
        $contenido .= '</td>';

        $contenido .= '<td align="center" valign="middle">';
        $contenido .= '<b>Fecha de autorización: 01-01-2024</b>';
        $contenido .= '</td>';

        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td align="center" valign="middle">';
        $contenido .= 'Fo.SGM.009';
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


        $cursosInternos = CursoCalendario::query()
            ->from('tb_cursos_calendario')
            ->join(
                'tb_cursos_temas',
                'tb_cursos_calendario.id_tema',
                '=',
                'tb_cursos_temas.id'
            )
            ->where('tb_cursos_calendario.id_estacion', $idestacion)
            ->where('tb_cursos_temas.categoria', 'SGM')
            ->whereYear('tb_cursos_calendario.fecha_programada', $year)
            ->orderBy('tb_cursos_calendario.fecha_programada', 'asc')
            ->get([
                'tb_cursos_calendario.id',
                'tb_cursos_calendario.fecha_programada',
                'tb_cursos_calendario.fecha_real',
                'tb_cursos_calendario.id_personal',
                'tb_cursos_calendario.id_tema',
                'tb_cursos_calendario.resultado',
                'tb_cursos_calendario.estado',
                'tb_cursos_temas.num_tema',
                'tb_cursos_temas.categoria',
                'tb_cursos_temas.titulo',
            ]);

        $contenido .= '<table class="table table-sm table-bordered align-middle">';
        $contenido .= '<thead>';
        $contenido .= '<tr>';
        $contenido .= '<th align="center">No</th>';
        $contenido .= '<th align="center">Nombre del curso</th>';
        $contenido .= '<th align="center">Tipo de capacitacion</th>';
        $contenido .= '<th align="center">Fecha programada</th>';
        $contenido .= '<th align="center">Duracion</th>';
        $contenido .= '<th align="center">Instructor</th>';
        $contenido .= '<th align="center">Fecha real de la toma del curso</th>';
        $contenido .= '<th align="center">Nombre de las personas que asistieron al curso</th>';
        $contenido .= '<th align="center">Evidencia</th>';
        $contenido .= '</tr>';
        $contenido .= '</thead>';

        $contenido .= '<tbody>';

        if ($cursosInternos->isEmpty()) {
            $contenido .= '<tr>';
            $contenido .= '<td colspan="9" align="center">';
            $contenido .= 'No se encontró información para mostrar';
            $contenido .= '</td>';
            $contenido .= '</tr>';
        } else {
            $numero = 1;

            foreach ($cursosInternos as $curso) {
                $usuario = $this->usuario((int) $curso->id_personal);

                $fechaProgramadaRaw = $curso->getRawOriginal('fecha_programada');
                $fechaRealRaw = $curso->getRawOriginal('fecha_real');

                $fechaProgramada = $this->fechaSegura($fechaProgramadaRaw);
                $fechaReal = $this->fechaSegura($fechaRealRaw);

                $pdf = $this->evidenciaCursoInterno(
                    (int) $curso->id,
                    (int) $curso->resultado
                );

                $contenido .= '<tr>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $numero;
                $contenido .= '</td>';

                $contenido .= '<td valign="middle">';
                $contenido .= $this->e($curso->titulo);
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= 'Interna';
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $fechaProgramada;
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= '30 minutos';
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= 'GestoGas';
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $fechaReal;
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $this->e($usuario['nombre']);
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $pdf;
                $contenido .= '</td>';

                $contenido .= '</tr>';

                $numero++;
            }
        }

        $contenido .= '</tbody>';
        $contenido .= '</table>';

        /*
         * CAPACITACIÓN EXTERNA
         */
        $cursosExternos = ProgramaAnualCapacitacionExterna::query()
            ->from('sgm_programa_anual_capacitacion_externa')
            ->where('id_estacion', $idestacion)
            ->whereYear('fecha_programada', $year)
            ->orderBy('fecha_programada', 'desc')
            ->get();

        $contenido .= '<table class="table table-sm table-bordered align-middle">';
        $contenido .= '<thead>';
        $contenido .= '<tr>';
        $contenido .= '<th align="center">No</th>';
        $contenido .= '<th align="center">Nombre del curso</th>';
        $contenido .= '<th align="center">Tipo de capacitacion</th>';
        $contenido .= '<th align="center">Fecha programada</th>';
        $contenido .= '<th align="center">Duracion</th>';
        $contenido .= '<th align="center">Instructor</th>';
        $contenido .= '<th align="center">Fecha real de la toma del curso</th>';
        $contenido .= '<th align="center">Nombre de las personas que asistieron al curso</th>';
        $contenido .= '<th align="center">Evidencia</th>';
        $contenido .= '</tr>';
        $contenido .= '</thead>';

        $contenido .= '<tbody>';

        if ($cursosExternos->isEmpty()) {
            $contenido .= '<tr>';
            $contenido .= '<td colspan="9" align="center">';
            $contenido .= 'No se encontró información para mostrar';
            $contenido .= '</td>';
            $contenido .= '</tr>';
        } else {
            $numero = 1;

            foreach ($cursosExternos as $curso) {
                $fechaProgramadaRaw = $curso->getRawOriginal('fecha_programada');
                $fechaRealRaw = $curso->getRawOriginal('fecha_real');

                $contenido .= '<tr>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $numero;
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $this->e($curso->nombre_curso);
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $this->e($curso->tipo_capacitacion);
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $this->fechaSegura($fechaProgramadaRaw);
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $this->e($curso->duracion);
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $this->e($curso->instructor);
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $this->fechaSegura($fechaRealRaw);
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $this->personal((int) $curso->id);
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $this->evidencias((int) $curso->id);
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
            ];
        }

        $usuario = Usuario::query()
            ->from('tb_usuarios')
            ->where('id', $idUsuario)
            ->first();

        return [
            'nombre' => $usuario?->nombre ?? '',
        ];
    }

    private function personal(int $idCapacitacion): string
    {
        $personal = ProgramaAnualCapacitacionExternaPersonal::query()
            ->from('sgm_programa_anual_capacitacion_externa_personal')
            ->where('id_capacitacion', $idCapacitacion)
            ->get();

        if ($personal->isEmpty()) {
            return '';
        }

        $nombres = [];

        foreach ($personal as $item) {
            $usuario = $this->usuario((int) $item->id_usuario);

            if ($usuario['nombre'] !== '') {
                $nombres[] = $usuario['nombre'];
            }
        }

        return $this->e(implode(', ', $nombres));
    }

    private function evidencias(int $idCapacitacion): string
    {
        $evidencias = ProgramaAnualCapacitacionExternaEvidencia::query()
            ->from('sgm_programa_anual_capacitacion_externa_evidencia')
            ->where('id_capacitacion', $idCapacitacion)
            ->get();

        if ($evidencias->isEmpty()) {
            return '';
        }

        $archivos = [];

        foreach ($evidencias as $evidencia) {
            if (empty($evidencia->archivo)) {
                continue;
            }

            $url = dirname(__DIR__, 2)  . '/public/uploads/archivos/sgm/' . $evidencia->archivo;

            $archivos[] = '<a href="'
                . $this->e($url)
                . '">'
                . $this->e($evidencia->archivo)
                . '</a>';
        }

        return implode(', ', $archivos);
    }

    private function evidenciaCursoInterno(int $idCurso, int $resultado): string
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
