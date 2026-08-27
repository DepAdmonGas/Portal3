<?php

namespace App\Reports\Sgm;

use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Sgm\Auditoria;
use App\Models\Sgm\PlanAuditoria;
use App\Models\Sgm\PlanAuditoriaResponsable;
use App\Models\Sgm\PlanAuditoriaAuditor;
use App\Models\Sgm\PlanAuditoriaAgenda;

class FormatoSgm18
{
    public function generar(int $idestacion, int $year): string
    {
        $estacion = Estacion::find($idestacion);

        if (!$estacion) {
            return '';
        }

        $contenido = '';

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

            $plan = PlanAuditoria::query()
                ->from('sgm_plan_auditoria')
                ->where('id_auditoria', $idAuditoria)
                ->first();

            if (!$plan) {
                continue;
            }

            $idPlan = (int) $plan->id;

            $realizadoPor = $this->usuario(
                (int) $plan->realizadopor
            );

            $fechaRaw = $plan->getRawOriginal('fecha');
            $fechaProgramadaRaw = $plan->getRawOriginal('fecha_programada');

            /*
             * Encabezado Fo.SGM.018
             */
            $contenido .= '<table class="table table-sm table-bordered align-middle">';
            $contenido .= '<tbody>';

            $contenido .= '<tr>';

            $contenido .= '<td rowspan="2" align="center" valign="middle">';
            $contenido .= $this->e($estacion->razonsocial);
            $contenido .= '</td>';

            $contenido .= '<td rowspan="2" align="center" valign="middle">';
            $contenido .= '<b>Plan de Auditoria</b>';
            $contenido .= '</td>';

            $contenido .= '<td align="center" valign="middle">';
            $contenido .= '<b>Fecha de autorización: 01-01-2024</b>';
            $contenido .= '</td>';

            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td align="center" valign="middle">';
            $contenido .= 'Fo.SGM.018';
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
             * I. DATOS GENERALES DEL PERMISIONARIO
             */
            $contenido .= '<table class="table table-sm table-bordered align-middle">';
            $contenido .= '<tbody>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3"><b>I. DATOS GENERALES DEL PERMISIONARIO</b></td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td>NOMBRE, DENOMINACIÓN O RAZÓN SOCIAL:</td>';
            $contenido .= '<td>Permiso CRE:</td>';
            $contenido .= '<td>FECHA DE ELABORACIÓN:</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td>' . $this->e($estacion->razonsocial) . '</td>';
            $contenido .= '<td>' . $this->e($estacion->permisocre) . '</td>';
            $contenido .= '<td>' . $this->fechaSegura($fechaRaw) . '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td>NOMBRE DEL DIRECTOR (ALTA DIRECCIÓN):</td>';
            $contenido .= '<td colspan="2">';
            $contenido .= $this->e($plan->nom_director);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td>NOMBRE DEL(LOS) RESPONSABLE DEL SGM</td>';
            $contenido .= '<td colspan="2" class="p-0 m-0">';
            $contenido .= $this->responsablesSgm($idPlan);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td>UBICACIÓN DE LA INSTALACIÓN</td>';
            $contenido .= '<td colspan="2">';
            $contenido .= $this->e($plan->ubicacion_instalacion);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '</tbody>';
            $contenido .= '</table>';

            /*
             * II. DATOS DEL AUDITOR
             */
            $contenido .= '<table class="table table-sm table-bordered align-middle">';
            $contenido .= '<tbody>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3"><b>II. DATOS DEL AUDITOR</b></td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td>EQUIPO AUDITOR</td>';
            $contenido .= '<td>NOMBRE:</td>';
            $contenido .= '<td>ÁREA/PROCESO/ACTIVIDAD QUE AUDITA:</td>';
            $contenido .= '</tr>';

            $contenido .= $this->auditor(
                $idPlan,
                'AUDITOR LÍDER'
            );

            $contenido .= $this->auditor(
                $idPlan,
                'AUDITOR'
            );

            $contenido .= '</tbody>';
            $contenido .= '</table>';

            /*
             * III. DATOS DEL EQUIPO AUXILIAR DEL AUDITOR
             */
            $contenido .= '<table class="table table-sm table-bordered align-middle">';
            $contenido .= '<tbody>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3"><b>III DATOS DEL EQUIPO AUXILIAR DEL AUDITOR</b></td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td>GUÍAS:</td>';
            $contenido .= '<td>OBSERVADORES:</td>';
            $contenido .= '<td>EXPERTO(S) TÉCNICO(S)</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';

            $contenido .= '<td valign="top">';
            $contenido .= $this->auxiliar(
                $idPlan,
                'GUÍAS'
            );
            $contenido .= '</td>';

            $contenido .= '<td valign="top">';
            $contenido .= $this->auxiliar(
                $idPlan,
                'OBSERVADORES'
            );
            $contenido .= '</td>';

            $contenido .= '<td valign="top">';
            $contenido .= $this->auxiliar(
                $idPlan,
                'EXPERTO(S) TÉCNICO(S)'
            );
            $contenido .= '</td>';

            $contenido .= '</tr>';

            $contenido .= '</tbody>';
            $contenido .= '</table>';

            /*
             * IV. AUDITORIA
             */
            $contenido .= '<table class="table table-sm table-bordered align-middle">';
            $contenido .= '<tbody>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3"><b>IV Auditoria</b></td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3"><b>OBJETIVOS DE LA AUDITORÍA.</b></td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3">';
            $contenido .= $this->e($plan->objetivo_auditoria);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3"><b>ALCANCE DE LA AUDITORÍA.</b></td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3">';
            $contenido .= $this->e($plan->alcance_auditoria);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3"><b>FECHA PROGRAMADA DE AUDITORIA</b></td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3">';
            $contenido .= $this->fechaSegura($fechaProgramadaRaw);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3"><b>SITIO</b></td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3">';
            $contenido .= $this->e($plan->sitio);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3"><b>MÉTODOS DE AUDITORÍA:</b></td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3">';
            $contenido .= $this->e($plan->metodo_auditoria);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3"><b>AJUSTES AL PLAN:</b></td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3">';
            $contenido .= $this->e($plan->ajuste_plan);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3"><b>ASIGNACIÓN DE RECURSOS APROPIADOS PARA LAS ÁREAS CRÍTICAS, CUANDO APLIQUE:</b></td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3">';
            $contenido .= $this->e($plan->asignacion_recursos);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3"><b>PREPARATIVOS LOGÍSTICOS Y DE COMUNICACIONES (Requisitos para el ingreso a las instalaciones, medidas de seguridad, números de emergencia, lugar de reunión de apertura, lugar de reunión de cierre, transporte y otros requerimientos del Equipo Auditor, como hospedaje, alimentos, entre otros):</b></td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3">';
            $contenido .= $this->e($plan->preparativos_logisticos);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3"><b>ACCIONES DE SEGUIMIENTO A PARTIR DE LA INFORMACIÓN GENERADA EN AUDITORÍAS PREVIAS.</b></td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="3">';
            $contenido .= $this->e($plan->acciones);
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '</tbody>';
            $contenido .= '</table>';


            /*
             * V. AGENDA
             */
            $contenido .= '<table class="table table-sm table-bordered align-middle">';
            $contenido .= '<tbody>';

            $contenido .= '<tr>';
            $contenido .= '<td colspan="5">';
            $contenido .= '<b>V. AGENDA.</b><br>';
            $contenido .= 'Nota: Elaborar una Agenda para cada sitio a ser auditado.';
            $contenido .= '</td>';
            $contenido .= '</tr>';

            $contenido .= '<tr>';
            $contenido .= '<th align="center">HORARIO</th>';
            $contenido .= '<th align="center">PROCESO</th>';
            $contenido .= '<th align="center">ELEMENTO DEL SISTEMA DE GESTION DE MEDICION</th>';
            $contenido .= '<th align="center">NOMBRE Y ROL DEL AUDITOR</th>';
            $contenido .= '<th align="center">GUÍA</th>';
            $contenido .= '</tr>';

            $contenido .= $this->agenda($idPlan);

            $contenido .= '</tbody>';
            $contenido .= '</table>';

            $contenido .= '<hr><br>';
        }

        return $contenido;
    }

    private function responsablesSgm(int $idPlan): string
    {
        $responsables = PlanAuditoriaResponsable::query()
            ->from('sgm_plan_auditoria_responsable')
            ->where('id_plan', $idPlan)
            ->get();

        if ($responsables->isEmpty()) {
            return '';
        }

        $contenido = '<table class="table table-sm table-bordered align-middle">';

        foreach ($responsables as $item) {
            $usuario = $this->usuario(
                (int) $item->id_responsable
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

    private function auditor(
        int $idPlan,
        string $categoria
    ): string {
        $contenido = '';

        $auditores = PlanAuditoriaAuditor::query()
            ->from('sgm_plan_auditoria_auditor')
            ->where('id_plan', $idPlan)
            ->where('categoria', $categoria)
            ->get();

        foreach ($auditores as $auditor) {
            $contenido .= '<tr>';

            $contenido .= '<td valign="middle">';
            $contenido .= $this->e($auditor->categoria);
            $contenido .= '</td>';

            $contenido .= '<td valign="middle">';
            $contenido .= $this->e($auditor->nombre);
            $contenido .= '</td>';

            $contenido .= '<td valign="middle">';
            $contenido .= $this->e($auditor->area_actividad);
            $contenido .= '</td>';

            $contenido .= '</tr>';
        }

        return $contenido;
    }

    private function auxiliar(
        int $idPlan,
        string $categoria
    ): string {
        $auditores = PlanAuditoriaAuditor::query()
            ->from('sgm_plan_auditoria_auditor')
            ->where('id_plan', $idPlan)
            ->where('categoria', $categoria)
            ->get();

        if ($auditores->isEmpty()) {
            return '';
        }

        $contenido = '<table class="table table-sm table-bordered align-middle">';

        foreach ($auditores as $auditor) {
            $contenido .= '<tr>';
            $contenido .= '<td>';
            $contenido .= $this->e($auditor->nombre);
            $contenido .= '</td>';
            $contenido .= '</tr>';
        }

        $contenido .= '</table>';

        return $contenido;
    }

    private function agenda(int $idPlan): string
    {
        $contenido = '';

        $agenda = PlanAuditoriaAgenda::query()
            ->from('sgm_plan_auditoria_agenda')
            ->where('id_plan', $idPlan)
            ->get();

        if ($agenda->isEmpty()) {
            return '<tr><td colspan="5" align="center">No se encontró información para mostrar</td></tr>';
        }

        foreach ($agenda as $item) {
            $contenido .= '<tr>';

            $contenido .= '<td align="center" valign="middle">';
            $contenido .= 'De ';
            $contenido .= $this->formatoHora($item->hora_inicio);
            $contenido .= ' a ';
            $contenido .= $this->formatoHora($item->hora_termino);
            $contenido .= '</td>';

            $contenido .= '<td align="center" valign="middle">';
            $contenido .= $this->e($item->proceso);
            $contenido .= '</td>';

            $contenido .= '<td align="center" valign="middle">';
            $contenido .= $this->e($item->elemento_sistema);
            $contenido .= '</td>';

            $contenido .= '<td align="center" valign="middle">';
            $contenido .= $this->e($item->nombre_rol);
            $contenido .= '</td>';

            $contenido .= '<td align="center" valign="middle">';
            $contenido .= $this->e($item->guia);
            $contenido .= '</td>';

            $contenido .= '</tr>';
        }

        return $contenido;
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

    private function formatoHora(mixed $hora): string
    {
        if ($hora instanceof \DateTimeInterface) {
            return $hora->format('H:i');
        }

        $hora = trim((string) ($hora ?? ''));

        if ($hora === '') {
            return '';
        }

        $timestamp = strtotime($hora);

        if ($timestamp === false) {
            return $this->e($hora);
        }

        return date('H:i', $timestamp);
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
