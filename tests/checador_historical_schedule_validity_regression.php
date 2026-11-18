<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Usuario;
use App\Services\ChecadorDailyReadService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

$_ENV['APP_ENV'] = 'testing';
$_ENV['CHECADOR_ADVANCED_DEMO_ENABLED'] = 'false';
putenv('APP_ENV=testing');
putenv('CHECADOR_ADVANCED_DEMO_ENABLED=false');

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

$base = 'https://checador.test/';
$user = new Usuario();
$user->id = 91;
$make = static function (array $responses, ?array &$history = null) use ($base): ChecadorDailyReadService {
    $history ??= [];
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));
    return new ChecadorDailyReadService(
        new Client(['handler' => $stack, 'base_uri' => $base, 'http_errors' => false]),
        static fn (Usuario $user): string => 'test-only-token',
        $base
    );
};
$assignment = static fn (array $overrides = []): array => array_replace([
    'id' => 301,
    'id_personal' => 77,
    'id_estacion' => 8,
    'id_plantilla' => 4,
    'dia_semana' => 'LUNES',
    'valido_desde' => '2026-10-01',
    'valido_hasta' => '2026-11-01',
    'es_activo' => 1,
    'registrado_at_utc' => '2026-09-30 18:30:00',
], $overrides);
$historyResponse = static fn (array $items, int $page = 2, int $perPage = 25): Response => new Response(
    200,
    ['Content-Type' => 'application/json'],
    json_encode(['status' => 'success', 'data' => ['page' => $page, 'per_page' => $perPage, 'items' => $items]], JSON_THROW_ON_ERROR)
);

$requestHistory = [];
$result = $make([$historyResponse([$assignment()], 2, 25)], $requestHistory)
    ->getShiftAssignments($user, 77, 8, 2, 25, '2026-10-19');
$request = $requestHistory[0]['request'] ?? null;
$query = $request instanceof RequestInterface ? [] : [];
if ($request instanceof RequestInterface) {
    parse_str((string) $request->getUri()->getQuery(), $query);
}
$assert($result['ok'] === true && ($result['data']['items'][0]['id'] ?? 0) === 301, 'V8C01 reads API-owned shift assignment history');
$assert($request instanceof RequestInterface && $request->getMethod() === 'GET'
    && str_contains((string) $request->getUri(), '/api/v2/shift-assignments'), 'V8C02 history lookup is a server-side GET on the frozen endpoint');
$assert(($query['id_personal'] ?? '') === '77' && ($query['id_estacion'] ?? '') === '8'
    && ($query['fecha_referencia'] ?? '') === '2026-10-19'
    && ($query['page'] ?? '') === '2' && ($query['per_page'] ?? '') === '25', 'V8C03 canonical worker, workplace, date and pagination are explicit');

$invalidHistory = $make([$historyResponse([$assignment(['valido_hasta' => '2026-10-01'])])])
    ->getShiftAssignments($user, 77, 8, 2, 25);
$wrongIdentity = $make([$historyResponse([$assignment(['id_estacion' => 2])])])
    ->getShiftAssignments($user, 77, 8, 2, 25);
$assert(($invalidHistory['error']['kind'] ?? '') === 'schedule_history_unavailable', 'V8C04 empty or reversed validity interval is rejected');
$assert(($wrongIdentity['error']['kind'] ?? '') === 'schedule_history_unavailable', 'V8C05 response identity mismatch is rejected');

$denied = $make([new Response(403, [], json_encode(['status' => 'error', 'error' => ['code' => 'WORKPLACE_SCOPE_DENIED']], JSON_THROW_ON_ERROR))])
    ->getShiftAssignments($user, 77, 8);
$unavailable = $make([new Response(500, [], json_encode(['status' => 'error', 'error' => ['code' => 'SERVER_ERROR']], JSON_THROW_ON_ERROR))])
    ->getShiftAssignments($user, 77, 8);
$assert(($denied['error']['kind'] ?? '') === 'api_authorization', 'V8C06 API authorization denial remains a denial');
$assert(($unavailable['error']['kind'] ?? '') === 'api_unavailable', 'V8C07 API failure is not rendered as an empty history');

$effective = [
    'id_personal' => 77,
    'id_estacion' => 8,
    'fecha_local' => '2026-10-19',
    'estado_jornada' => 'SCHEDULED',
    'origen' => ['id_asignacion' => 301, 'id_plantilla' => 4],
];
$row = $assignment();
$reconcile = static fn (array $schedule, array $rows, bool $truncated = false, string $date = '2026-10-19'): array =>
    ChecadorDailyReadService::reconcileEffectiveScheduleValidity($schedule, $rows, 77, 8, $date, $truncated);
$matched = $reconcile($effective, [$row]);
$assert(($matched['status'] ?? '') === 'matched', 'V8C08 matching effective schedule and half-open assignment validity are corroborated');
$closedHistorical = $row;
$closedHistorical['es_activo'] = 0;
$closedHistoricalMatch = $reconcile($effective, [$closedHistorical]);
$assert(($closedHistoricalMatch['status'] ?? '') === 'matched', 'V8C08A a closed row remains valid historical evidence inside its interval');
$changedAssignment = $assignment([
    'id' => 302,
    'id_plantilla' => 5,
    'valido_desde' => '2026-11-01',
    'valido_hasta' => null,
]);
$effectiveAfterChange = array_replace($effective, [
    'fecha_local' => '2026-11-02',
    'origen' => ['id_asignacion' => 302, 'id_plantilla' => 5],
]);
$afterChange = $reconcile($effectiveAfterChange, [$changedAssignment], false, '2026-11-02');
$assert(($matched['assignment']['id'] ?? 0) === 301 && ($afterChange['assignment']['id'] ?? 0) === 302, 'V8C08B dates before and after a schedule change resolve to their own assignment records');
$openEnded = $row;
$openEnded['valido_hasta'] = null;
$assert(($reconcile($effective, [$openEnded])['status'] ?? '') === 'matched', 'V8C08C an open-ended assignment remains applicable when the contract permits NULL end dates');

$unscheduled = array_replace($effective, ['estado_jornada' => 'UNSCHEDULED', 'origen' => null]);
$atExclusiveEnd = $row;
$atExclusiveEnd['valido_hasta'] = '2026-10-19';
$exclusive = $reconcile($unscheduled, [$atExclusiveEnd]);
$assert(($exclusive['status'] ?? '') === 'no_assignment', 'V8C09 validity end is exclusive');
$assert(($reconcile($unscheduled, [])['status'] ?? '') === 'no_assignment', 'V8C09A no covering assignment is not converted to an attendance conclusion');
$assert(($reconcile($effective, [$row, $row])['status'] ?? '') === 'ambiguous', 'V8C10 overlapping assignments are not arbitrarily selected');
$assert(($reconcile($effective, [$row], true)['status'] ?? '') === 'incomplete', 'V8C11 a truncated assignment result cannot confirm one validity');
$closedAssignment = $reconcile($unscheduled, [$closedHistorical]);
$assert(($closedAssignment['status'] ?? '') === 'inconsistent', 'V8C12 a closed inactive historical row conflicts safely with resolver UNSCHEDULED');
$unknown = array_replace($effective, ['estado_jornada' => 'UNKNOWN', 'origen' => null]);
$unknownWithHistory = $reconcile($unknown, [$row]);
$assert(($unknownWithHistory['status'] ?? '') === 'resolver_unknown_with_assignment', 'V8C13 UNKNOWN is not converted into absence when history exists');

$invalidQueryHistory = [];
$invalidQuery = $make([], $invalidQueryHistory)->getShiftAssignments($user, 77, 8, 1, 50, '2026-02-30');
$assert(($invalidQuery['error']['kind'] ?? '') === 'bad_request' && $invalidQueryHistory === [], 'V8C14 invalid civil dates fail before an API request');

$controller = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php') ?: '';
$routes = file_get_contents(__DIR__ . '/../routes/web.php') ?: '';
$service = file_get_contents(__DIR__ . '/../app/Services/ChecadorDailyReadService.php') ?: '';
$view = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/schedule-history.php') ?: '';
$templateAdminView = $view;
$historyScript = file_get_contents(__DIR__ . '/../public/assets/js/departamento-operativo/2-recursos-humanos/checador.schedule-history.templates.init.js') ?: '';
$templateScript = $historyScript;
$mainView = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/index.php') ?: '';
$weeklyView = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/schedule-week.php') ?: '';
$indexMethodStart = strpos($controller, 'public function index(): void');
$indexMethodEnd = $indexMethodStart === false ? false : strpos($controller, 'public function scheduleHistory(): void', $indexMethodStart);
$indexMethod = $indexMethodStart === false ? '' : substr($controller, $indexMethodStart, ($indexMethodEnd ?: strlen($controller)) - $indexMethodStart);
$historyRouteStart = strpos($controller, 'public function scheduleHistory(): void');
$historyRouteEnd = $historyRouteStart === false ? false : strpos($controller, 'public function scheduleWeek(): void', $historyRouteStart);
$historyRouteMethod = $historyRouteStart === false ? '' : substr($controller, $historyRouteStart, ($historyRouteEnd ?: strlen($controller)) - $historyRouteStart);
$navigationMethodStart = strpos($controller, 'private function buildModuleNavigation(');
$navigationMethodEnd = $navigationMethodStart === false ? false : strpos($controller, 'private function renderDashboardWorkspace(', $navigationMethodStart);
$navigationMethod = $navigationMethodStart === false ? '' : substr($controller, $navigationMethodStart, ($navigationMethodEnd ?: strlen($controller)) - $navigationMethodStart);
$historyMethodStart = strpos($controller, 'private function renderScheduleHistory(');
$historyMethod = $historyMethodStart === false ? '' : substr($controller, $historyMethodStart, (strpos($controller, 'private static function scheduleHistoryErrorMessage', $historyMethodStart) ?: strlen($controller)) - $historyMethodStart);
$assert(str_contains($routes, "'/recursos-humanos/checador/horarios-turnos', Route::auth(['ChecadorController', 'scheduleHistory'])")
    && str_contains($historyRouteMethod, '$this->renderDashboardWorkspace(null, true)')
    && str_contains($historyMethod, "ChecadorAccessService::authorizeScheduling(\$workplaceId, 'leer')"), 'V8C15 clean history route is authenticated and server-authorizes schedule reads');
$assert(!str_contains($routes, 'horarios-turnos/trabajadores')
    && !str_contains($controller, 'scheduleHistoryWorkers')
    && str_contains($controller, "authorizeScheduling(\$workplaceId, 'leer')"), 'V8C15A the original worker-row navigation is restored without a parallel Select2 search route');
$workerRosterStart = strpos($controller, 'private static function scheduleHistoryWorkerRoster(');
$workerRosterMethod = $workerRosterStart === false ? '' : substr($controller, $workerRosterStart, (strpos($controller, 'private static function scheduleHistoryQueryParameters', $workerRosterStart) ?: strlen($controller)) - $workerRosterStart);
$rosterSource = file_get_contents(__DIR__ . '/../app/Services/ChecadorAttendanceRosterService.php') ?: '';
$assert(str_contains($workerRosterMethod, 'scheduleHistoryWorkerForDate(')
    && str_contains($rosterSource, 'resolveAssignments($workerId, $effectiveAt)')
    && str_contains($workerRosterMethod, 'resolveWorkplaceLocalDay('), 'V8C15B a selected worker is checked against canonical membership for the requested local date');
$assert(str_contains($historyMethod, 'elseif ($demoMode)') && strpos($historyMethod, 'new ChecadorDailyReadService()') > strpos($historyMethod, 'elseif ($demoMode)'), 'V8C16 demo mode does not invoke live schedule API reads');
$assignmentMethodStart = strpos($service, 'public function getShiftAssignments(');
$assignmentMethodEnd = $assignmentMethodStart === false ? false : (strpos($service, "\n    /**", $assignmentMethodStart + 1) ?: strlen($service));
$assignmentMethod = $assignmentMethodStart === false ? '' : substr($service, $assignmentMethodStart, $assignmentMethodEnd - $assignmentMethodStart);
$assert(str_contains($assignmentMethod, "'GET', 'api/v2/shift-assignments'") && !str_contains($assignmentMethod, "'POST'")
    && !preg_match('/PDO|mysqli|UPDATE\s|INSERT\s|DELETE\s/i', $assignmentMethod), 'V8C17 history service has no write operation or local database fallback');
$assert(str_contains($view, 'm04-template-table') && !str_contains($view, 'Historial de asignaciones')
    && !str_contains($view, 'assignment-history-heading')
    && str_contains($controller, "'historyItems' => \$historyItems"), 'V8C18 original schedule presentation has no added assignment-history card while the V2 reader remains available');
$assert(str_contains($view, 'dia_offset_fin') && str_contains($templateScript, '(+${offset} día)'), 'V8C18A overnight end-day offset remains preserved and is formatted for the original schedule table');
$assert(str_contains($view, 'moduleStationKey') || str_contains($controller, "'moduleStationKey' => ChecadorAccessService::MODULE_KEY"), 'V8C19 existing institutional station selector context is preserved');
$assert(str_contains($navigationMethod, "'href' => \$base . '/horarios-turnos'")
    && str_contains($mainView, 'data-checador-module-card')
    && str_contains($mainView, '$href')
    && str_contains($indexMethod, 'checador/horarios-turnos')
    && !str_contains($indexMethod, '$this->renderDashboardWorkspace(null)'), 'V8C20 directory uses a clean route and the legacy query only redirects');
$assert(str_contains($controller, 'scheduleHistoryQueryParameters')
    && str_contains($view, "'/departamento-operativo/recursos-humanos/checador/horarios-turnos'")
    && str_contains($view, "\$historyQuery['worker_id']")
    && str_contains($controller, "\$query['page'] ?? 1")
    && !str_contains($view, 'schedule_history_page')
    && !str_contains($view, "'schedule_history' => '1'")
    && str_contains($weeklyView, '/checador/horarios-turnos?')
    && !str_contains($weeklyView, 'schedule_history='), 'V8C20A canonical history filters, pagination and weekly links do not use screen-selection flags');
$assert(str_contains($templateAdminView, 'Turnos disponibles') && str_contains($templateAdminView, 'id="m04-template-table"')
    && str_contains($controller, 'new ChecadorShiftTemplateSqlReadService()')
    && str_contains($controller, 'getShiftTemplates($currentUser, $workplaceId, 1, 200)')
    && str_contains($controller, 'getShiftTemplateVersion(')
    && str_contains($templateScript, 'pageLength: 25'), 'V8C20B original schedule catalog is rendered from authorized, locality-scoped SQL reads with its original DataTable');
$assert(str_contains($weeklyView, 'Ver historial')
    && str_contains($weeklyView, "'worker_id' => \$workerId, 'date' => (string) \$weekStart")
    && str_contains($weeklyView, '/checador/horarios-turnos?'), 'V8C20C each weekly worker row links directly to the clean, selected-worker history route');
$assert(str_contains($historyMethod, 'checador.schedule-history.templates.init.js')
    && !str_contains($historyMethod, 'select2.full.min.js')
    && str_contains($templateAdminView, 'x-data="m04TurnCreation()"')
    && str_contains($templateScript, "Alpine.data('m04TurnCreation'")
    && str_contains($templateAdminView, 'data-save-url="/departamento-operativo/recursos-humanos/checador/horarios-turnos/plantillas"'), 'V8C20D the original Alpine workflow is loaded without Select2 dependencies');
$assert(str_contains($templateAdminView, 'm04-turn-name')
    && str_contains($templateAdminView, 'm04-turn-start')
    && str_contains($templateAdminView, 'm04-turn-end')
    && str_contains($templateAdminView, 'm04-turn-break-start')
    && str_contains($templateAdminView, 'm04-turn-break-end')
    && str_contains($templateAdminView, 'm04-edit-template-modal')
    && !str_contains($templateAdminView, 'data-schedule-add-segment')
    && !str_contains($templateAdminView, 'Día de inicio'), 'V8C20E the original create and edit modals retain the five schedule inputs and original actions');
$assert(str_contains($templateScript, 'buildSegmentsFrom(')
    && str_contains($templateScript, 'async saveEdit()')
    && str_contains($templateScript, "operation: 'version'")
    && str_contains($templateScript, "operation: 'customize'")
    && str_contains($controller, 'customizeGlobalShiftTemplate(')
    && str_contains($service, "'scope' => 'STATION', 'derived_from_global' => ['clave' => \$sourceKey, 'version' => \$sourceVersion]")
    && str_contains($templateScript, "operation: 'create'"), 'V8C20F the original form builds V2 segments and posts through the verified create, customization and version operations');
$assert(!str_contains($view, 'Historial de programación'), 'V8C20G removed redundant schedule-history heading');
$assert(str_contains($controller, "'historyItems' => \$historyItems")
    && !str_contains($view, 'Historial de asignaciones')
    && strpos($historyMethod, '$historyResult = $reader->getShiftAssignments') < strpos($historyMethod, '$dateResult = $reader->getShiftAssignments'), 'V8C21 assignment history lookup remains independent of effective-schedule resolution without changing the original schedule presentation');

$renderData = [
    'selectedWorkplaceId' => 8,
    'selectedWorkplaceName' => 'Interlomas',
    'selectedDate' => '2026-10-19',
    'selectedWorkerId' => 77,
    'selectedWorkerName' => 'Ana Trabajadora',
    'demoMode' => false,
    'demoScenario' => null,
    'effectiveSchedule' => array_replace($effective, [
        'horario_local' => [
            'timezone' => 'America/Mexico_City',
            'segmentos' => [[
                'ordinal' => 1,
                'tipo' => 'WORK',
                'hora_inicio_local' => '22:00:00',
                'hora_fin_local' => '06:00:00',
                'dia_offset_fin' => 1,
            ]],
        ],
    ]),
    'effectiveScheduleError' => null,
    'effectiveScheduleErrorMessage' => '',
    'historyItems' => [$row],
    'historyError' => null,
    'historyErrorMessage' => '',
    'historyPage' => 1,
    'historyHasPrevious' => false,
    'historyHasNext' => false,
    'validityCheck' => $matched,
    'scheduleTemplates' => [[
        'id' => 4,
        'clave' => 'TURNO_PRUEBA',
        'nombre' => 'Legacy Interlomas — ASISTENTE / INTENDENCIA — 09:00–18:00',
        'es_activo' => 1,
        'segmentos' => [
            ['ordinal' => 1, 'tipo_segmento' => 'WORK', 'hora_inicio_local' => '22:00:00', 'dia_offset_inicio' => 0, 'hora_fin_local' => '06:00:00', 'dia_offset_fin' => 1],
        ],
    ]],
    'templateCatalogStatus' => 'received',
    'templateCatalogMessage' => '1 horario recibido.',
    'apiTemplates' => ['status' => 'ready', 'items' => [[
        'id' => 4,
        'clave' => 'TURNO_PRUEBA',
        'version' => 1,
        'nombre' => 'Legacy Interlomas — ASISTENTE / INTENDENCIA — 09:00–18:00',
        'tipo_turno' => 'REGULAR',
        'scope' => 'GLOBAL',
        'id_estacion' => null,
        'es_activo' => 1,
        'segments' => [['ordinal' => 1, 'tipo_segmento' => 'WORK', 'hora_inicio_local' => '22:00:00', 'dia_offset_inicio' => 0, 'hora_fin_local' => '06:00:00', 'dia_offset_fin' => 1]],
    ]]],
    'templateWritesEnabled' => false,
    'templateAdminFeedback' => null,
    'canManageStationTemplates' => false,
];
$rendered = '';
$renderError = null;
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
ob_start();
try {
    extract($renderData, EXTR_SKIP);
    include __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/schedule-history.php';
    $rendered = (string) ob_get_clean();
} catch (Throwable $exception) {
    $renderError = $exception::class;
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
} finally {
    restore_error_handler();
}
$assert($renderError === null
    && str_contains($rendered, 'Turnos disponibles')
    && str_contains($rendered, 'ASISTENTE / INTENDENCIA')
    && !str_contains($rendered, 'Legacy Interlomas')
    && str_contains($rendered, '/checador/horarios-turnos')
    && str_contains($rendered, '22:00')
    && str_contains($rendered, '06:00')
    && str_contains($rendered, 'Consultar historial')
    && !str_contains($rendered, 'Trabajador canónico')
    && !str_contains($rendered, 'Plantilla ID')
    && !str_contains($rendered, 'Contrato API')
    && !str_contains($rendered, 'Historial de asignaciones')
    && !str_contains($rendered, 'assignment-history-heading'), 'V8C22 original schedule catalog renders without runtime warnings or the added history card');

echo "RESULTS: {$passes} PASS / {$fails} FAIL\n";
exit($fails === 0 ? 0 : 1);
