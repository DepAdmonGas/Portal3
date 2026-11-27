<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
use App\Services\ChecadorWeeklyScheduleService;
use App\Services\ChecadorAttendanceProjection;
use App\Services\ChecadorJourneySqlReadService;
$worker = ['worker_id'=>565, 'full_name'=>'Fixture'];
$assignment = ['id'=>30,'id_personal'=>565,'id_estacion'=>1,'id_plantilla'=>8,'tipo_asignacion'=>'HABITUAL','dia_semana'=>'JUEVES','valido_desde'=>'2026-11-01','valido_hasta'=>null,'es_activo'=>1];
$template = ['id'=>8,'nombre'=>'TURNO 1','segmentos'=>[['hora_inicio_local'=>'06:00:00','hora_fin_local'=>'14:00:00','dia_offset_inicio'=>0,'dia_offset_fin'=>0]]];
$matrix = ChecadorWeeklyScheduleService::matrix([$worker],[565=>[$assignment]],[8=>$template],'2026-11-23');
$cell = $matrix['workers'][0]['cells']['2026-11-26'];
if (($cell['status'] ?? '') !== 'assigned' || ($cell['template']['nombre'] ?? '') !== 'TURNO 1') throw new RuntimeException('assignment not resolved');
$row = ChecadorAttendanceProjection::workerRow($worker,[['id'=>'1','local_time'=>'10:49:39'],['id'=>'2','local_time'=>'10:51:59']],null,'America/Mexico_City');
if (count($row['additional_events']) !== 2 || $row['slots']['ENTRY:1']['event'] !== null || $row['slots']['EXIT:1']['event'] !== null) throw new RuntimeException('raw events classified');
$persistedStatusCases = [
    [['p1_status'=>'EXACT_PAIR','p2_status'=>'CALCULATED','pairing_current_status'=>'ready'],'evaluated'],
    [['p1_status'=>'PENDING_SYNC_COMPLETION','p2_status'=>'CALCULATED','pairing_current_status'=>'ready'],'incomplete'],
    [['p1_status'=>'PARTIAL_MISSING_OUT','p2_status'=>'CALCULATED','pairing_current_status'=>'ready'],'incomplete'],
    [['p1_status'=>'AMBIGUOUS_PAIRING','p2_status'=>'CALCULATED','pairing_current_status'=>'ready'],'ambiguous'],
    [['p1_status'=>'PENDING_REVIEW','p2_status'=>'CALCULATED','pairing_current_status'=>'ready'],'pending_review'],
    [['p1_status'=>'WORKPLACE_MISMATCH','p2_status'=>'CALCULATED','pairing_current_status'=>'ready'],'review'],
    [['p1_status'=>'EXACT_PAIR','p2_status'=>null,'pairing_current_status'=>'ready'],'calculation_unavailable'],
    [['p1_status'=>null,'p2_status'=>null,'pairing_current_status'=>'missing'],'journey_not_evaluated'],
    [['p1_status'=>'EXACT_PAIR','p2_status'=>'DAY_OFF_RECORDED','pairing_current_status'=>'ready'],'day_off_recorded'],
];
foreach ($persistedStatusCases as [$persisted, $expected]) {
    if (ChecadorAttendanceProjection::persistedJourneyStatus($persisted) !== $expected) {
        throw new RuntimeException('persisted P1/P2 status precedence failed for ' . json_encode($persisted));
    }
}
$partial = ['p1_status'=>'PENDING_SYNC_COMPLETION', 'p2_status'=>'PENDING_SYNC', 'pairing_current_status'=>'ready',
    'persisted_pairing_events'=>[['event_uuid'=>'controlled-22', 'pairing_role'=>'ENTRY', 'segment_ordinal'=>1,
        'ocurrido_at_utc'=>'2026-11-27 16:57:04']]];
$partialRow = ChecadorAttendanceProjection::workerRow($worker, [], $partial, 'America/Mexico_City');
if (ChecadorAttendanceProjection::persistedJourneyStatus($partial) !== 'incomplete'
    || ($partialRow['slots']['ENTRY:1']['event']['event_uuid'] ?? '') !== 'controlled-22'
    || ($partialRow['slots']['EXIT:1']['event'] ?? null) !== null
    || ($partialRow['slots']['ENTRY:1']['time_local']['time'] ?? '') !== '10:57'
    || ($partialRow['slots']['ENTRY:1']['event']['ocurrido_at_utc'] ?? '') !== '2026-11-27 16:57:04') {
    throw new RuntimeException('partial persisted J1 projection failed');
}
$segment = ['id_marcacion_entrada' => 22, 'id_marcacion_salida' => null];
if (!ChecadorJourneySqlReadService::associationMatchesSegment('ENTRY', 22, $segment)
    || ChecadorJourneySqlReadService::associationMatchesSegment('EXIT', 22, $segment)
    || ChecadorJourneySqlReadService::associationMatchesSegment('ENTRY', 21, $segment)
    || ChecadorJourneySqlReadService::associationMatchesSegment('ENTRY', 22, null)) {
    throw new RuntimeException('J1 event/segment identity was not verified');
}
$conflict = $assignment; $conflict['id']=31;
$matrix = ChecadorWeeklyScheduleService::matrix([$worker],[565=>[$assignment,$conflict]],[8=>$template],'2026-11-23');
if (($matrix['workers'][0]['cells']['2026-11-26']['status'] ?? '') !== 'ambiguous') throw new RuntimeException('conflict hidden');
echo "PASS assignment, observed events, conflicting assignments and persisted P1/P2 status precedence (isolated fixtures)\n";
