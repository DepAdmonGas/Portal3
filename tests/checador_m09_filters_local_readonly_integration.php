<?php

declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
App\Core\Database::initialize();
ob_start();
$reader = new App\Services\ChecadorRawPunchReadService();
$assert = static function(bool $ok, string $name): void { if (!$ok) throw new RuntimeException($name); echo "PASS $name\n"; };
$context = $reader->localReportContext(1, '2026-10-28', '2026-10-28');
$assert(($context['ok'] ?? false) === true, 'real locality timezone and local interval resolve');
$intervals = $context['intervals'];
$from = $intervals[0][0]; $to = $intervals[count($intervals)-1][1];
$assert($from === '2026-10-28 06:00:00' && $to === '2026-10-29 06:00:00', 'real local day has the expected UTC boundaries');
$assert(($context['devices'] ?? []) !== [], 'real scoped devices are available for the period');
$device = (int)$context['devices'][0]['id'];
$page = $reader->readDataTablePage(1,$from,$to,null,0,10,'',1,'asc','Interlomas','','',$intervals,$device);
$assert(($page['ok'] ?? false) === true && ($page['records_filtered'] ?? 0) > 0, 'selected real device returns a SQL page');
foreach ($page['events'] as $event) {
    $assert((int)$event['id_dispositivo'] === $device && (int)$event['id_estacion_resuelta'] === 1
        && ($event['local_capture']['local_datetime'] ?? '') >= '2026-10-28 00:00:00'
        && ($event['local_capture']['local_datetime'] ?? '') < '2026-10-29 00:00:00', 'each row matches device, scope and local date');
}
$denied = $reader->readDataTablePage(1,$from,$to,null,0,10,'',1,'asc','Interlomas','','',$intervals,2147483647);
$assert(($denied['error']['kind'] ?? '') === 'device_scope_mismatch', 'unattributed device is denied');
$other = $reader->readDataTablePage(1,$from,$to,null,0,10,'__no_match__',1,'asc','Interlomas','','',$intervals,$device);
$assert(($other['ok'] ?? false) === true && ($other['records_filtered'] ?? -1) === 0, 'valid device remains valid when another filter finds no rows');
$ref = new ReflectionMethod(App\Controllers\ChecadorController::class, 'punchReportCsvContent');
$ref->setAccessible(true);
$csv = $ref->invoke(null, $page['events'], 1, 'Interlomas');
$assert(is_string($csv) && str_contains($csv,'Fecha y hora local de captura') && str_contains($csv,'Clasificación J1'), 'CSV includes local capture and J1 columns');
foreach ($page['events'] as $event) $assert(str_contains($csv, (string)$event['event_uuid']), 'CSV includes each row from the filtered page');
$stream = new ReflectionMethod(App\Controllers\ChecadorController::class, 'streamPunchReportDataTableCsv');
$stream->setAccessible(true);
ob_start();
$stream->invoke(null, $reader, $page, 1, 'Interlomas', $from, $to, null, '', 1, 'asc', '', '', $intervals, $device);
$streamCsv = (string)ob_get_clean();
$assert(str_contains($streamCsv, 'Completa') && str_contains($streamCsv, 'Registros exportados'), 'CSV stream completes using the same local intervals and device filter');
$next = $reader->readDataTablePage(1,$from,$to,null,10,10,'',1,'asc','Interlomas','','',$intervals,$device);
foreach ($next['events'] ?? [] as $event) $assert(str_contains($streamCsv, (string)$event['event_uuid']), 'CSV stream includes matching later pages');
echo "M09_REAL_LOCAL_FILTERS: PASS\n";
ob_end_flush();
