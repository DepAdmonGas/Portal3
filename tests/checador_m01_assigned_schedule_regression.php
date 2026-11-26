<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use App\Services\ChecadorWeeklyScheduleService;
use App\Services\ChecadorAttendanceProjection;
$worker = ['worker_id'=>565, 'full_name'=>'Fixture'];
$assignment = ['id'=>30,'id_personal'=>565,'id_estacion'=>1,'id_plantilla'=>8,'tipo_asignacion'=>'HABITUAL','dia_semana'=>'JUEVES','valido_desde'=>'2026-11-01','valido_hasta'=>null,'es_activo'=>1];
$template = ['id'=>8,'nombre'=>'TURNO 1','segmentos'=>[['hora_inicio_local'=>'06:00:00','hora_fin_local'=>'14:00:00','dia_offset_inicio'=>0,'dia_offset_fin'=>0]]];
$matrix = ChecadorWeeklyScheduleService::matrix([$worker],[565=>[$assignment]],[8=>$template],'2026-11-23');
$cell = $matrix['workers'][0]['cells']['2026-11-26'];
if (($cell['status'] ?? '') !== 'assigned' || ($cell['template']['nombre'] ?? '') !== 'TURNO 1') throw new RuntimeException('assignment not resolved');
$row = ChecadorAttendanceProjection::workerRow($worker,[['id'=>'1','local_time'=>'10:49:39'],['id'=>'2','local_time'=>'10:51:59']],null,'America/Mexico_City');
if (count($row['additional_events']) !== 2 || $row['slots']['ENTRY:1']['event'] !== null || $row['slots']['EXIT:1']['event'] !== null) throw new RuntimeException('raw events classified');
$conflict = $assignment; $conflict['id']=31;
$matrix = ChecadorWeeklyScheduleService::matrix([$worker],[565=>[$assignment,$conflict]],[8=>$template],'2026-11-23');
if (($matrix['workers'][0]['cells']['2026-11-26']['status'] ?? '') !== 'ambiguous') throw new RuntimeException('conflict hidden');
echo "PASS assignment, observed events and conflicting assignments (isolated fixtures)\n";
