<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/scripts/prepare_checador_m02_qa_1004_schedule.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$assert(
    ChecadorM02Qa1004Preparation::acceptsScope(1004, 1, '2026-10-28'),
    'the sole QA scope must be accepted'
);
$assert(
    !ChecadorM02Qa1004Preparation::acceptsScope(1005, 1, '2026-10-28'),
    'another worker must be rejected'
);
$assert(
    !ChecadorM02Qa1004Preparation::acceptsScope(1004, 2, '2026-10-28'),
    'another locality must be rejected'
);
$assert(
    !ChecadorM02Qa1004Preparation::acceptsScope(1004, 1, '2026-10-29'),
    'another date must be rejected'
);

$template = ChecadorM02Qa1004Preparation::templatePayload();
$assert($template['clave'] === 'QA1004_20261028_0800_1700', 'template key must be fixed to the QA scope');
$assert(count($template['segmentos']) === 1, 'template must contain one segment');
$assert($template['segmentos'][0] === [
    'ordinal' => 1,
    'tipo_segmento' => 'WORK',
    'hora_inicio_local' => '08:00:00',
    'hora_fin_local' => '17:00:00',
    'dia_offset_inicio' => 0,
    'dia_offset_fin' => 0,
], 'template segment must match the approved proposal');

$assignment = ChecadorM02Qa1004Preparation::assignmentPayload(321);
$assert($assignment === [
    'id_personal' => 1004,
    'id_estacion' => 1,
    'id_plantilla' => 321,
    'dia_semana' => 'MIERCOLES',
    'valido_desde' => '2026-10-28',
    'valido_hasta' => '2026-10-29',
    'es_activo' => 1,
], 'assignment payload must be one-day and use only the canonical response template id');

$idRejected = false;
try {
    ChecadorM02Qa1004Preparation::assignmentPayload(0);
} catch (InvalidArgumentException) {
    $idRejected = true;
}
$assert($idRejected, 'a missing or guessed template id must be rejected');

$source = file_get_contents(dirname(__DIR__) . '/scripts/prepare_checador_m02_qa_1004_schedule.php');
$assert(is_string($source), 'preparation source must be readable');
$assert(!preg_match('/GuzzleHttp|curl_init|requestJson|new\\s+Client\\s*\\(/i', $source), 'preparation must contain no HTTP client or request path');
$assert(str_contains($source, "'mutations_enabled' => false"), 'manifest must explicitly disable mutations');
$assert(str_contains($source, "'http_requests_sent' => 0"), 'manifest must explicitly report zero HTTP requests');
$assert(str_contains($source, "'create_allowed_in_this_script' => false"), 'future template and assignment POSTs must remain disabled');
$assert(str_contains($source, "'scenario_evidence' => ["), 'manifest must expose its read-only worker, mapping, timezone, event, and duplicate evidence');

echo "PASS: scoped QA preparation rejects other workers, localities, dates, and incomplete template dependencies; no HTTP/write path is present.\n";
