<?php

namespace App\Reports\Sgm;

use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Sgm\Auditoria;
use App\Models\Sgm\PlanAtencionHallazgo;
use App\Models\Sgm\PlanAtencionHallazgosResponsable;

class FormatoSgm20
{
    public function generar(int $idestacion, int $year): string
    {
        $estacion = Estacion::find($idestacion);

        if (!$estacion) {
            return '';
        }

        $contenido = '';

        /*
         * Auditorías activas de la estación para el año solicitado.
         */
        $auditorias = Auditoria::query()
            ->from('sgm_auditoria')
            ->where('id_estacion', $idestacion)
            ->where('year', $year)
            ->where('estado', 1)
            ->get([
                'id',
            ]);

        foreach ($auditorias as $auditoria) {
            $idAuditoria = (int) $auditoria->id;

            /*
             * Plan de atención de hallazgos.
             */
            $planes = PlanAtencionHallazgo::query()
                ->from('sgm_plan_atencion_hallazgos')
                ->where('id_auditoria', $idAuditoria)
                ->get();

            foreach ($planes as $plan) {
                $idPlan = (int) $plan->id;

                $responsable = $this->usuario(
                    (int) $plan->responsable
                );

                $responsableSgm = $this->usuario(
                    (int) $plan->responsable_sgm
                );

                $realizadoPor = $this->usuario(
                    (int) $plan->realizadopor
                );

                $fechaRaw = $plan->getRawOriginal('fecha');
                $fechaCumplimientoRaw = $plan->getRawOriginal('fecha_complimiento');
                $fechaAtencionRaw = $plan->getRawOriginal('fecha_atencion_hallazgos');

                /*
                 * Encabezado Fo.SGM.020
                 */
                $contenido .= '<table class="table table-sm table-bordered align-middle">';
                $contenido .= '<tbody>';

                $contenido .= '<tr>';

                $contenido .= '<td rowspan="2" align="center" valign="middle">';
                $contenido .= $this->e($estacion->razonsocial);
                $contenido .= '</td>';

                $contenido .= '<td rowspan="2" align="center" valign="middle">';
                $contenido .= '<b>Plan de atención de Hallazgos</b>';
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= '<b>Fecha de autorización: 01-01-2024</b>';
                $contenido .= '</td>';

                $contenido .= '</tr>';

                $contenido .= '<tr>';
                $contenido .= '<td align="center" valign="middle">';
                $contenido .= 'Fo.SGM.020';
                $contenido .= '</td>';
                $contenido .= '</tr>';

                $contenido .= '<tr>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= 'Realizado por:<br>';
                $contenido .= $this->e($realizadoPor['nombre']);
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= 'Revisado por:<br>Eduardo Galicia Flores';
                $contenido .= '</td>';

                $contenido .= '<td align="center" valign="middle">';
                $contenido .= 'Autorizado por:<br>';
                $contenido .= $this->e($estacion->apoderado_legal);
                $contenido .= '</td>';

                $contenido .= '</tr>';

                $contenido .= '</tbody>';
                $contenido .= '</table>';

                /*
                 * I. Datos generales del permisionario
                 */
                $contenido .= '<table class="table table-sm table-bordered align-middle">';
                $contenido .= '<tbody>';

                $contenido .= '<tr>';
                $contenido .= '<td colspan="3">';
                $contenido .= '<b>I. DATOS GENERALES DEL PERMISIONARIO</b>';
                $contenido .= '</td>';
                $contenido .= '</tr>';

                $contenido .= '<tr>';
                $contenido .= '<td>NOMBRE, DENOMINACIÓN O RAZÓN SOCIAL:</td>';
                $contenido .= '<td>PERMISO CRE:</td>';
                $contenido .= '<td>FECHA DEL INFORME DE AUDITORÍA (Reporte de hallazgos de auditorias):</td>';
                $contenido .= '</tr>';

                $contenido .= '<tr>';
                $contenido .= '<td>';
                $contenido .= $this->e($estacion->razonsocial);
                $contenido .= '</td>';

                $contenido .= '<td>';
                $contenido .= $this->e($estacion->permisocre);
                $contenido .= '</td>';

                $contenido .= '<td>';
                $contenido .= $this->fechaSegura($fechaRaw);
                $contenido .= '</td>';
                $contenido .= '</tr>';

                $contenido .= '<tr>';
                $contenido .= '<td colspan="2">SITIO/ÁREA:</td>';
                $contenido .= '<td>RESPONSABLE</td>';
                $contenido .= '</tr>';

                $contenido .= '<tr>';

                $contenido .= '<td colspan="2">';
                $contenido .= $this->e($plan->sitio_area);
                $contenido .= '</td>';

                $contenido .= '<td>';
                $contenido .= $this->e($responsable['nombre']);
                $contenido .= '</td>';

                $contenido .= '</tr>';

                /*
                 * II. Hallazgo
                 */
                $contenido .= '<tr>';
                $contenido .= '<td colspan="3">';
                $contenido .= '<b>II. HALLAZGO: (DESCRIPCIÓN/EVIDENCIA/CRITERIO)</b>';
                $contenido .= '</td>';
                $contenido .= '</tr>';

                $contenido .= '<tr>';
                $contenido .= '<td colspan="3">';
                $contenido .= $this->e($plan->hallazgo);
                $contenido .= '</td>';
                $contenido .= '</tr>';

                /*
                 * III. Análisis de causa raíz
                 */
                $contenido .= '<tr>';
                $contenido .= '<td colspan="3">';
                $contenido .= '<b>III. ANÁLISIS DE LA CAUSA RAÍZ</b>';
                $contenido .= '</td>';
                $contenido .= '</tr>';

                $contenido .= '<tr>';
                $contenido .= '<td colspan="3">';
                $contenido .= $this->e($plan->analisis_causa);
                $contenido .= '</td>';
                $contenido .= '</tr>';

                /*
                 * IV. Acciones
                 */
                $contenido .= '<tr>';
                $contenido .= '<td colspan="3">';
                $contenido .= '<b>IV. ACCIONES PARA LA ATENCIÓN DE LOS HALLAZGOS NO CONFORMES</b>';
                $contenido .= '</td>';
                $contenido .= '</tr>';

                $contenido .= '<tr>';
                $contenido .= '<td colspan="3">';
                $contenido .= $this->e($plan->acciones_hallazgos);
                $contenido .= '</td>';
                $contenido .= '</tr>';

                /*
                 * V. Responsables
                 */
                $contenido .= '<tr>';

                $contenido .= '<td colspan="2" valign="middle">';
                $contenido .= '<b>V. NOMBRE DE LOS RESPONSABLES DEL CUMPLIMIENTO DE LAS ACCIONES</b>';
                $contenido .= '</td>';

                $contenido .= '<td>';
                $contenido .= $this->responsablesCumplimiento($idPlan);
                $contenido .= '</td>';

                $contenido .= '</tr>';

                /*
                 * VI. Fechas compromiso
                 */
                $contenido .= '<tr>';
                $contenido .= '<td colspan="3">';
                $contenido .= '<b>VI. FECHAS COMPROMISO PARA EL CUMPLIMIENTO DE LA IMPLEMENTACIÓN DE ACCIONES</b>';
                $contenido .= '</td>';
                $contenido .= '</tr>';

                $contenido .= '<tr>';
                $contenido .= '<td colspan="3">';
                $contenido .= $this->fechaSegura($fechaCumplimientoRaw);
                $contenido .= '</td>';
                $contenido .= '</tr>';

                /*
                 * VII. Recursos asignados
                 */
                $contenido .= '<tr>';
                $contenido .= '<td colspan="3">';
                $contenido .= '<b>VII. RECURSOS ASIGNADOS PARA LA IMPLEMENTACIÓN DE ACCIONES</b>';
                $contenido .= '</td>';
                $contenido .= '</tr>';

                $contenido .= '<tr>';
                $contenido .= '<td colspan="3">';
                $contenido .= $this->e($plan->recursos_implementacion);
                $contenido .= '</td>';
                $contenido .= '</tr>';

                $contenido .= '</tbody>';
                $contenido .= '</table>';

                /*
                 * Fecha del plan y responsable del SGM
                 */
                $firmaResponsableSgm = $this->firmaBase64(
                    $responsableSgm['firma']
                );

                $contenido .= '<table class="table table-sm table-bordered align-middle">';
                $contenido .= '<tbody>';

                $contenido .= '<tr>';
                $contenido .= '<td>FECHA DEL PLAN DE ATENCIÓN DE HALLAZGOS:</td>';
                $contenido .= '<td>';
                $contenido .= $this->fechaSegura($fechaAtencionRaw);
                $contenido .= '</td>';
                $contenido .= '</tr>';

                $contenido .= '<tr>';
                $contenido .= '<td valign="middle">RESPONSABLE DEL SGM:</td>';
                $contenido .= '<td align="center" valign="middle">';

                if ($firmaResponsableSgm !== '') {
                    $contenido .= '<img src="';
                    $contenido .= $firmaResponsableSgm;
                    $contenido .= '" width="100">';
                    $contenido .= '<br>';
                }

                $contenido .= $this->e($responsableSgm['nombre']);
                $contenido .= '</td>';
                $contenido .= '</tr>';

                $contenido .= '</tbody>';
                $contenido .= '</table>';

                $contenido .= '<hr><br>';
            }
        }

        return $contenido;
    }

    private function responsablesCumplimiento(int $idPlan): string
    {
        $responsables = PlanAtencionHallazgosResponsable::query()
            ->from('sgm_plan_atencion_hallazgos_responsables')
            ->where('id_plan', $idPlan)
            ->get();

        if ($responsables->isEmpty()) {
            return '';
        }

        $contenido = '<table class="table table-sm table-bordered align-middle">';

        foreach ($responsables as $responsable) {
            $usuario = $this->usuario(
                (int) $responsable->id_responsable
            );

            $contenido .= '<tr>';
            $contenido .= '<td>';
            $contenido .= $this->e($usuario['nombre']);
            $contenido .= '</td>';
            $contenido .= '</tr>';
        }

        $contenido .= '</table>';

        return $contenido;
    }

    private function usuario(int $idUsuario): array
    {
        if ($idUsuario <= 0) {
            return [
                'nombre' => '',
                'firma' => '',
            ];
        }

        $usuario = Usuario::query()
            ->from('tb_usuarios')
            ->where('id', $idUsuario)
            ->first();

        if (!$usuario) {
            return [
                'nombre' => '',
                'firma' => '',
            ];
        }

        return [
            'nombre' => $usuario->nombre ?? '',
            'firma' => $usuario->firma ?? '',
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
