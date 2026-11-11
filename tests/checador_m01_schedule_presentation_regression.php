<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\ChecadorLaborPolicy;
use App\Services\ChecadorWeeklyScheduleService;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

$controller = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php') ?: '';
$weekMethodStart = strpos($controller, 'private function renderScheduleWeek(');
$weekMethodEnd = $weekMethodStart === false ? false : strpos($controller, 'public function createScheduleAssignment(', $weekMethodStart);
$weekMethod = $weekMethodStart === false
    ? ''
    : substr($controller, $weekMethodStart, ($weekMethodEnd ?: strlen($controller)) - $weekMethodStart);
$assert(!str_contains($weekMethod, 'Breadcrumb::add('), 'M01 schedule view relies on one institutional breadcrumb composition');

$viewPath = __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/schedule-week.php';
$viewSource = file_get_contents($viewPath) ?: '';
$layoutSource = file_get_contents(__DIR__ . '/../app/Views/layouts/departamento-operativo.php') ?: '';
$assert(!preg_match('/<h1\b/i', $viewSource)
    && str_contains($layoutSource, '<h4 class="fw-semibold mt-3"><?= $title; ?></h4>')
    && str_contains($viewSource, 'Programación semanal / Cambios temporales'), 'institutional page title remains in the layout without a duplicate view title');

$weekDates = ChecadorWeeklyScheduleService::dates('2026-10-26');
$base = [
    'weekDates' => $weekDates,
    'scheduleRows' => [],
    'selectedWorkplaceId' => 1,
    'selectedWorkplaceName' => 'Interlomas',
    'weekStart' => '2026-10-26',
    'timezone' => 'America/Mexico_City',
    'coverage' => 'partial',
    'rosterCoverage' => 'partial',
    'rosterMessage' => 'La cobertura del padrón o de las membresías canónicas es parcial.',
    'matrixCoverage' => 'not_evaluated',
    'matrixMessage' => 'La matriz semanal no se evaluó porque el padrón no está completo.',
    'errorMessage' => 'La cobertura del padrón o de las membresías canónicas es parcial.',
    'feedback' => null,
    'templates' => [],
    'canAssign' => false,
    'demoMode' => false,
    'legalReference' => ChecadorLaborPolicy::referenceForDate('2026-10-26'),
    'dailyLimits' => ChecadorLaborPolicy::generalDailyReferences(),
];

$render = static function (array $data) use ($viewPath): string {
    $bufferLevel = ob_get_level();
    try {
        ob_start();
        extract($data, EXTR_SKIP);
        include $viewPath;
        return (string) ob_get_clean();
    } finally {
        while (ob_get_level() > $bufferLevel) ob_end_clean();
    }
};

$partial = $render($base);
$assert(!str_contains($partial, '0 personas')
    && !str_contains($partial, 'No hay personas con programación visible para esta semana.')
    && str_contains($partial, 'Padrón: cobertura parcial')
    && str_contains($partial, 'Matriz semanal: no evaluada')
    && str_contains($partial, 'No se puede confirmar el conjunto de personas'), 'partial roster does not render a zero count or a definitive empty result');
$assert(substr_count($partial, 'class="alert alert-warning') === 1
    && !str_contains($partial, 'La consulta está disponible; la asignación de turnos'), 'coverage explanation is compact and does not label an incomplete read as available');

$completeEmpty = $render(array_replace($base, [
    'coverage' => 'ready',
    'rosterCoverage' => 'ready',
    'rosterMessage' => 'Personal activo con pertenencia canónica validada para la fecha seleccionada.',
    'matrixCoverage' => 'ready',
    'matrixMessage' => '',
    'errorMessage' => '',
    'scheduleRows' => [],
]));
$assert(str_contains($completeEmpty, '0 personas')
    && str_contains($completeEmpty, 'membresía canónica vigente')
    && str_contains($completeEmpty, 'Esto no equivale a descanso ni ausencia.'), 'only a confirmed complete empty result shows zero and explains its limited meaning');

$apiFailure = $render(array_replace($base, [
    'coverage' => 'ready',
    'rosterCoverage' => 'ready',
    'matrixCoverage' => 'unavailable',
    'matrixMessage' => 'El catálogo contractual de turnos no está disponible.',
    'errorMessage' => 'El catálogo contractual de turnos no está disponible.',
]));
$assert(str_contains($apiFailure, 'Matriz semanal: no disponible')
    && str_contains($apiFailure, 'El catálogo contractual de turnos no está disponible.')
    && !str_contains($apiFailure, '0 personas'), 'a real schedule read error remains distinct from a confirmed empty roster');

printf("CHECADOR_M01_SCHEDULE_PRESENTATION: %d PASS / %d FAIL\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);
