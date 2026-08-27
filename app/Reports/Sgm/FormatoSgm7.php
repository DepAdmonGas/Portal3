<?php

namespace App\Reports\Sgm;

use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Sgm\Autorizado;
use App\Models\Sgm\Responsable;

class FormatoSgm7
{
    public function generar(int $idestacion, int $year): string
    {
        $contenido = '';

        $estacion = Estacion::find($idestacion);

        if (!$estacion) {
            return '';
        }

        $realizadoPor = $this->realizadoPor($idestacion);

        /*
         * Encabezado Fo.SGM.007
         */
        $contenido .= '<table class="table table-sm table-bordered align-middle">';
        $contenido .= '<tbody>';

        $contenido .= '<tr>';

        $contenido .= '<td rowspan="2" align="center" valign="middle">';
        $contenido .= $this->e($estacion->razonsocial);
        $contenido .= '</td>';

        $contenido .= '<td rowspan="2" align="center" valign="middle">';
        $contenido .= '<b>Designación de responsable SGM</b>';
        $contenido .= '</td>';

        $contenido .= '<td align="center" valign="middle">';
        $contenido .= '<b>Fecha de autorización: 01-01-2024</b>';
        $contenido .= '</td>';

        $contenido .= '</tr>';

        $contenido .= '<tr>';
        $contenido .= '<td align="center" valign="middle">';
        $contenido .= 'Fo.SGM.007';
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
         * Última designación registrada
         */
        $registro = Responsable::query()
            ->from('sgm_responsable')
            ->where('id_estacion', $idestacion)
            ->orderByDesc('id')
            ->first();

        if (!$registro) {
            $contenido .= '<div align="center">No se encontró información para mostrar</div>';
            $contenido .= '<hr><br>';

            return $contenido;
        }

        $responsable = $this->usuario((int) $registro->responsable);
        $auxiliar = $this->usuario((int) $registro->auxiliar);

        $fechaRaw = $registro->getRawOriginal('fecha');
        $fecha = $this->fechaSegura($fechaRaw);

        $municipio = $registro->di_municipio ?? $estacion->di_municipio ?? '';
        $estado = $registro->di_estado ?? $estacion->di_estado ?? '';
        $apoderadoLegal = $registro->apoderado_legal ?? $estacion->apoderado_legal ?? '';
        $razonSocial = $registro->razonsocial ?? $estacion->razonsocial ?? '';
        $direccionCompleta = $registro->direccioncompleta ?? $estacion->direccioncompleta ?? '';
        $firmaApoderado = $registro->firma ?? $estacion->firma ?? '';

        if ($municipio !== '' || $estado !== '') {
            $contenido .= '<div align="right">';
            $contenido .= '<p>';
            $contenido .= $this->e($municipio);

            if ($municipio !== '' && $estado !== '') {
                $contenido .= ', ';
            }

            $contenido .= $this->e($estado);
            $contenido .= ' a ' . $fecha;
            $contenido .= '</p>';
            $contenido .= '</div>';
        }

        $contenido .= '<p>';
        $contenido .= 'A QUIEN CORRESPONDA<br>';
        $contenido .= 'COMISIÓN REGULADORA DE ENERGÍA<br>';
        $contenido .= 'PRESENTE:';
        $contenido .= '</p>';

        $contenido .= '<p>';
        $contenido .= '<b>' . $this->e($apoderadoLegal) . '</b> ';
        $contenido .= 'en carácter de representante legal de la estacion ';
        $contenido .= '<b>' . $this->e($razonSocial) . '</b> ';
        $contenido .= 'con ubicación en ';
        $contenido .= '<b>' . $this->e($direccionCompleta) . '</b>';
        $contenido .= '</p>';

        $contenido .= '<p>';
        $contenido .= 'Sírvase la presente para designar la persona que será el responsable de la implementación y adecuada operación del Sistema de Gestión de Mediciones, así como al personal especializado que auxiliará en dichas tareas.';
        $contenido .= '</p>';

        $contenido .= '<p>';
        $contenido .= 'Quienes tienen las siguientes responsabilidades, (entre otras):';
        $contenido .= '</p>';

        $contenido .= '<ol>';
        $contenido .= '<li>Asegurar que las actividades del SGM se apeguen a los procedimientos correspondientes.</li>';
        $contenido .= '<li>Elaborar los reportes e información sobre el SGM requerida por la Comisión o por la Empresa especializada que los solicite como parte de una visita de verificación.</li>';
        $contenido .= '<li>Conservar la documentación relativa al SGM para su consulta por la Comisión cuando ésta lo requiera o para consulta de otros Permisionarios, o usuarios del sistema de almacenamiento permisionado por un periodo mínimo de 10 años.</li>';
        $contenido .= '<li>Generar, organizar, implementar cambios, difundir, almacenar y dar trazabilidad a toda la información derivada de la operación del SGM.</li>';
        $contenido .= '</ol>';

        $contenido .= '<p>';
        $contenido .= 'La designación del grupo de personas se realizó por así convenir a mi representada, eligiendo personal relacionado directamente con la operación de la empresa.';
        $contenido .= '</p>';

        $firmaResponsable = $this->firmaBase64(
            $responsable['firma'] ?? ''
        );

        $firmaAuxiliar = $this->firmaBase64(
            $auxiliar['firma'] ?? ''
        );

        $firmaRepresentante = $this->firmaBase64(
            $firmaApoderado
        );

        $contenido .= '<br>';

        $contenido .= '<table class="table table-sm table-bordered align-middle">';
        $contenido .= '<tr>';

        $contenido .= '<td align="center" valign="top" width="50%">';
        $contenido .= '<b>Nombre y firma de conformidad del responsable de implementacion del Sistema de Gestión de Medición</b>';
        $contenido .= '<br><br>';

        if ($firmaResponsable !== '') {
            $contenido .= '<img src="' . $firmaResponsable . '" width="120">';
            $contenido .= '<br>';
        }

        $contenido .= $this->e($responsable['nombre'] ?? '');
        $contenido .= '</td>';

        $contenido .= '<td align="center" valign="top" width="50%">';
        $contenido .= '<b>Personal especializado que auxiliará en las tareas de implementacion del Sistema de Gestión de Medición</b>';
        $contenido .= '<br><br>';

        if ($firmaAuxiliar !== '') {
            $contenido .= '<img src="' . $firmaAuxiliar . '" width="120">';
            $contenido .= '<br>';
        }

        $contenido .= $this->e($auxiliar['nombre'] ?? '');
        $contenido .= '</td>';

        $contenido .= '</tr>';
        $contenido .= '</table>';

        $contenido .= '<br><br>';

        $contenido .= '<table class="table table-sm table-bordered align-middle">';
        $contenido .= '<tr>';

        $contenido .= '<td align="center" valign="top">';
        $contenido .= '<b>Representante legal</b>';
        $contenido .= '<br><br>';

        if ($firmaRepresentante !== '') {
            $contenido .= '<img src="' . $firmaRepresentante . '" width="120">';
            $contenido .= '<br>';
        }

        $contenido .= $this->e($apoderadoLegal);
        $contenido .= '</td>';

        $contenido .= '</tr>';
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

    private function usuario(int $id): array
    {
        if ($id <= 0) {
            return [
                'nombre' => '',
                'puesto' => '',
                'firma' => '',
                'fecha_ingreso' => '',
            ];
        }

        $usuario = Usuario::query()
            ->from('tb_usuarios')
            ->leftJoin(
                'tb_puestos',
                'tb_usuarios.id_puesto',
                '=',
                'tb_puestos.id'
            )
            ->where('tb_usuarios.id', $id)
            ->select([
                'tb_usuarios.nombre',
                'tb_usuarios.firma',
                'tb_usuarios.fecha_ingreso',
                'tb_puestos.tipo_puesto',
            ])
            ->first();

        if (!$usuario) {
            return [
                'nombre' => '',
                'puesto' => '',
                'firma' => '',
                'fecha_ingreso' => '',
            ];
        }

        return [
            'nombre' => $usuario->nombre ?? '',
            'puesto' => $usuario->tipo_puesto ?? '',
            'firma' => $usuario->firma ?? '',
            'fecha_ingreso' => $usuario->fecha_ingreso ?? '',
        ];
    }

    private function firmaBase64(?string $archivo): string
    {
        $archivo = trim((string) $archivo);

        if ($archivo === '') {
            return '';
        }

        $ruta = dirname(__DIR__, 2) . '/public/assets/img/firmas/' . $archivo;

        if (!is_file($ruta) || !is_readable($ruta)) {
            return '';
        }

        $datos = file_get_contents($ruta);

        if ($datos === false) {
            return '';
        }

        $extension = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));

        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return 'data:' . $mime . ';base64,' . base64_encode($datos);
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
