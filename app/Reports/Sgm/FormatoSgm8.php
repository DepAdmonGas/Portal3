<?php

namespace App\Reports\Sgm;

use App\Models\Estacion;
use App\Models\Puestos;
use App\Models\Usuario;
use App\Models\Sgm\Autorizado;
use App\Models\UsuariosExperienciaEmpresaGrupo;

class FormatoSgm8
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
         * Encabezado Fo.SGM.008
         */
        $contenido .= '<table class="table table-sm table-bordered align-middle">';
        $contenido .= '<tbody>';

        $contenido .= '<tr>';

        $contenido .= '<td rowspan="2" align="center" valign="middle">';
        $contenido .= $this->e($estacion->razonsocial);
        $contenido .= '</td>';

        $contenido .= '<td rowspan="2" align="center" valign="middle">';
        $contenido .= '<b>Lista de personal</b>';
        $contenido .= '</td>';

        $contenido .= '<td align="center" valign="middle">';
        $contenido .= '<b>Fecha de autorización: 01-01-2024</b>';
        $contenido .= '</td>';

        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td align="center" valign="middle">';
        $contenido .= 'Fo.SGM.008';
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
         * Lista del personal
         *
         * Código original:
         * SELECT * FROM tb_usuarios WHERE id_gas = ?
         */
        $usuarios = Usuario::query()
            ->from('tb_usuarios')
            ->where('id_gas', $idestacion)
            ->get();

        $contenido .= '<table class="table table-sm table-bordered align-middle">';
        $contenido .= '<thead>';

        $contenido .= '<tr>';
        $contenido .= '<th valign="middle">No</th>';
        $contenido .= '<th valign="middle">Nombre</th>';
        $contenido .= '<th valign="middle">Estatus</th>';
        $contenido .= '<th valign="middle">Fecha de Ingreso</th>';
        $contenido .= '<th valign="middle">Puesto</th>';
        $contenido .= '<th valign="middle">Grado de responsabilidad respecto al SGM</th>';
        $contenido .= '</tr>';

        $contenido .= '</thead>';
        $contenido .= '<tbody>';

        if ($usuarios->isEmpty()) {
            $contenido .= '<tr>';
            $contenido .= '<td colspan="6" align="center">';
            $contenido .= 'No se encontró información para mostrar';
            $contenido .= '</td>';
            $contenido .= '</tr>';
        } else {
            foreach ($usuarios as $usuario) {
                $puesto = $this->puesto((int) $usuario->id_puesto);
                $fechaIngreso = $this->fechaIngreso((int) $usuario->id);

                $contenido .= '<tr>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $this->e($usuario->id);
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $this->e($usuario->nombre);
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= 'Activo';
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $fechaIngreso;
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $this->e($puesto);
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= $this->e($usuario->responsabilidad_sgm);
                $contenido .= '</td>';

                $contenido .= '</tr>';
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

    private function puesto(int $idPuesto): string
    {
        if ($idPuesto <= 0) {
            return '';
        }

        return (string) (
            Puestos::query()
            ->from('tb_puestos')
            ->where('id', $idPuesto)
            ->value('tipo_puesto') ?? ''
        );
    }

    private function fechaIngreso(int $idUsuario): string
    {
        $experiencia = UsuariosExperienciaEmpresaGrupo::query()
            ->from('tb_usuarios_experiencia_empresa_grupo')
            ->where('id_usuario', $idUsuario)
            ->orderByDesc('periodo_inicio')
            ->first();

        if (!$experiencia) {
            return '';
        }

        $fechaRaw = $experiencia->getRawOriginal('periodo_inicio');

        return $this->fechaSegura($fechaRaw);
    }

    private function fechaSegura(mixed $fecha): string
    {
        $fecha = trim((string) ($fecha ?? ''));

        if (
            $fecha === ''
            || $fecha === '0000-00-00'
            || $fecha === '0000-00-00 00:00:00'
        ) {
            return '';
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
            return '';
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
