<?php

namespace App\Reports\Sgm;

use App\Models\Estacion;
use App\Models\Sgm\Autorizado;

class FormatoSgm3
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

        $contenido .= '<table class="table table-sm table-bordered align-middle">';
        $contenido .= '<tbody>';

        $contenido .= '<tr>';
        $contenido .= '<td rowspan="2" align="center" valign="middle">' . $this->e($estacion->razonsocial) . '</td>';
        $contenido .= '<td rowspan="2" align="center" valign="middle"><b>Control documental del SGM</b></td>';
        $contenido .= '<td align="center" valign="middle"><b>Fecha de autorización: 01-01-2024</b></td>';
        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td align="center" valign="middle">Fo.SGM.003</td>';
        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td align="center" valign="middle">Realizado por: ' . $this->e($realizadoPor) . '</td>';
        $contenido .= '<td align="center" valign="middle">Revisado por:<br> Eduardo Galicia Flores</td>';
        $contenido .= '<td align="center" valign="middle">Autorizado por:<br> ' . $this->e($estacion->apoderado_legal) . '</td>';
        $contenido .= '</tr>';

        $contenido .= '</tbody>';
        $contenido .= '</table>';

        $contenido .= '<table class="table table-sm table-bordered align-middle">';
        $contenido .= '<tbody>';
        $contenido .= '<tr><td colspan="3" align="center"><b>Sistema de gestión de medición</b></td></tr>';
        $contenido .= '<tr><td><b>Codificación</b></td><td><b>Nombre</b></td><td><b>Fecha de aprobación</b></td></tr>';
        $contenido .= '<tr><td>SGM.001</td><td>Sistema de Gestión de Medición</td><td>01/01/2024</td></tr>';
        $contenido .= '</tbody>';
        $contenido .= '</table>';

        $procedimientos = [
            ['Po.SGM.001', 'Estructura del sistema de Medición'],
            ['Po.SGM.002', 'Control de Documental del SGM'],
            ['Po.SGM.003', 'Responsabilidades de la Dirección'],
            ['Po.SGM.004', 'Establecimiento de Objetivos enfocados al Cliente'],
            ['Po.SGM.005', 'Gestión de recursos'],
            ['Po.SGM.006', 'Normatividad aplicable a mediciones'],
            ['Po.SGM.007', 'Procesos de medición.'],
            ['Po.SGM.008', 'Gestión de Riesgos que impactan en la medición.'],
            ['Po.SGM.009', 'Establecimiento y Seguimiento Confirmación Metrológica'],
            ['Po.SGM.010', 'Auditorias, Internas, externas y Atención de hallazgos'],
            ['Po.SGM.011', 'Evaluación del cumplimiento de Objetivos y revisión por la Dirección.'],
        ];

        $contenido .= '<table class="table table-sm table-bordered align-middle">';
        $contenido .= '<tbody>';
        $contenido .= '<tr><td colspan="3" align="center"><b>Manual de procedimientos del Sistema de Gestión de Medición</b></td></tr>';
        $contenido .= '<tr><td><b>Codificación</b></td><td><b>Nombre</b></td><td><b>Fecha de aprobación</b></td></tr>';

        foreach ($procedimientos as [$codigo, $nombre]) {
            $contenido .= '<tr>';
            $contenido .= '<td>' . $this->e($codigo) . '</td>';
            $contenido .= '<td>' . $this->e($nombre) . '</td>';
            $contenido .= '<td>01/01/2024</td>';
            $contenido .= '</tr>';
        }

        $contenido .= '</tbody>';
        $contenido .= '</table>';

        $formatos = [
            ['Fo.SGM.001', 'Lista de Asistencia'],
            ['Fo.SGM.002', 'Revisión del SGM, formatos y registros'],
            ['Fo.SGM.003', 'Control documental del SGM'],
            ['Fo.SGM.004', 'Seguimiento de objetivos e indicadores'],
            ['Fo.SGM.005', 'Inventario de normatividad aplicable'],
            ['Fo.SGM.006', 'Requisitos legales del SGM'],
            ['Fo.SGM.007', 'Designación de responsable SGM'],
            ['Fo.SGM.008', 'Lista del personal'],
            ['Fo.SGM.009', 'Programa de capacitación interna y externa'],
            ['Fo.SGM.010', 'Capacitación de inducción'],
            ['Fo.SGM.011', 'Inventario de equipo'],
            ['Fo.SGM.012', 'Orden de servicio'],
            ['Fo.SGM.013', 'Evaluación de proveedores'],
            ['Fo.SGM.014', 'Programa anual de calibración de patrones e instrumentos de medida'],
            ['Fo.SGM.015', 'Programa anual de verificación de equipos de medición'],
            ['Fo.SGM.016', 'Bitácora para la verificación de los equipos de medicion'],
            ['Fo.SGM.017', 'Bitácora para la calibración de equipos'],
            ['Fo.SGM.018', 'Plan de auditoria'],
            ['Fo.SGM.019', 'Reporte de Hallazgos de Auditoria'],
            ['Fo.SGM.020', 'Plan de atención de Hallazgos'],
            ['Fo.SGM.021', 'Cumplimiento de objetivos y revision por la alta direccion'],
        ];

        $contenido .= '<table class="table table-sm table-bordered align-middle">';
        $contenido .= '<tbody>';
        $contenido .= '<tr><td colspan="3" align="center"><b>Formatos del Sistema de Gestión de Medición</b></td></tr>';
        $contenido .= '<tr><td><b>Codificación</b></td><td><b>Nombre</b></td><td><b>Fecha de aprobación</b></td></tr>';

        foreach ($formatos as [$codigo, $nombre]) {
            $contenido .= '<tr>';
            $contenido .= '<td>' . $this->e($codigo) . '</td>';
            $contenido .= '<td>' . $this->e($nombre) . '</td>';
            $contenido .= '<td>01/01/2024</td>';
            $contenido .= '</tr>';
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
            ->join('tb_usuarios', 'sgm_autorizado.id_usuario', '=', 'tb_usuarios.id')
            ->where('tb_usuarios.id_gas', $idestacion)
            ->where('sgm_autorizado.estado', 1)
            ->select('tb_usuarios.nombre')
            ->first();

        return $registro?->nombre ?? 'S/I';
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
