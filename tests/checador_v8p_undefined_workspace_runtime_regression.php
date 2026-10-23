<?php

declare(strict_types=1);

namespace App\Controllers {
    class BaseController
    {
    }
}

namespace App\Core {
    final class Request
    {
        public static array $queryParams = ['workplace_id' => '1'];

        public static function query(): array
        {
            return self::$queryParams;
        }
    }

    final class Breadcrumb
    {
        public static array $items = [];

        public static function add(string $label, string $url): void
        {
            self::$items[] = [$label, $url];
        }
    }

    final class View
    {
        public static string $lastView = '';
        public static string $lastLayout = '';
        public static array $lastData = [];
        public static string $lastHtml = '';

        public static function render(string $view, array $data = [], string $layout = 'main'): void
        {
            self::$lastView = $view;
            self::$lastLayout = $layout;
            self::$lastData = $data;
            $viewPath = dirname(__DIR__)
                . '/app/Views/departamento-operativo/2-recursos-humanos/checador/index.php';
            extract($data, EXTR_SKIP);
            ob_start();
            require $viewPath;
            self::$lastHtml = (string) ob_get_clean();
        }
    }
}

namespace App\Services {
    final class ChecadorAccessService
    {
        public const MODULE_KEY = 'checador';
        public static array $authorizationCalls = [];

        public static function authorize(?int $workplaceId = null): array
        {
            self::$authorizationCalls[] = $workplaceId;
            $workplaces = [['id' => 1, 'nombre' => 'Interlomas']];
            if ($workplaceId !== null && $workplaceId !== 1) {
                return ['allowed' => false, 'reason' => 'requested_station_out_of_scope', 'workplaces' => $workplaces];
            }

            return [
                'allowed' => true,
                'workplaces' => $workplaces,
                'station_workplaces' => $workplaces,
                'department_workplaces' => [],
            ];
        }

        public static function authorizeScheduling(int $workplaceId, string $action): array
        {
            return ['allowed' => false, 'reason' => 'scheduling_permission_denied'];
        }

        public static function authorizeTemporaryAccess(int $workplaceId, string $action): array
        {
            return ['allowed' => false, 'reason' => 'temporary_access_permission_denied'];
        }
    }

    final class ModuleStationService
    {
        public static ?int $selectedWorkplaceId = null;

        public static function getContext(string $moduleKey): array
        {
            return [];
        }

        public static function setChecadorWorkplaceContext(int $workplaceId): bool
        {
            self::$selectedWorkplaceId = $workplaceId;
            return $workplaceId === 1;
        }

        public static function setContext(string $moduleKey, ?int $stationId, ?int $departmentId): void
        {
        }
    }

    final class ChecadorPayrollComparisonReadService
    {
        public function authorization(int $workplaceId): array
        {
            return ['allowed' => false];
        }
    }
}

namespace {
    $root = dirname(__DIR__);
    require_once $root . '/app/Controllers/ChecadorController.php';

    $passes = 0;
    $failures = 0;
    $assert = static function (bool $condition, string $label) use (&$passes, &$failures): void {
        echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
        $condition ? $passes++ : $failures++;
    };

    $runtimeError = null;
    set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }, E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE);
    try {
        (new \App\Controllers\ChecadorController())->index();
    } catch (\Throwable $error) {
        $runtimeError = $error;
    } finally {
        restore_error_handler();
    }

    $data = \App\Core\View::$lastData;
    $html = \App\Core\View::$lastHtml;
    if ($runtimeError !== null) {
        echo 'RUNTIME_ERROR: ' . $runtimeError::class . ': ' . $runtimeError->getMessage() . "\n";
    }
    $assert($runtimeError === null, 'ChecadorController::index and renderModuleDirectory execute without undefined-variable or runtime errors');
    $assert(\App\Core\View::$lastView === 'departamento-operativo/2-recursos-humanos/checador/index'
        && \App\Core\View::$lastLayout === 'departamento-operativo'
        && ($data['isModuleDirectory'] ?? false) === true
        && !array_key_exists('workspace', $data), 'main route renders only the dedicated module directory without injecting workspace data');
    $assert(substr_count($html, 'data-checador-featured-card') === 1
        && substr_count($html, 'data-checador-module-card') === 9
        && substr_count($html, '<article') === 10, 'controller-rendered directory contains one featured and nine secondary module cards');
    $assert(substr_count($html, '<a href=') === 6
        && count(array_filter($data['moduleNavigation'] ?? [], static fn(array $card): bool => ($card['available'] ?? false) === false)) === 4,
        'restricted modules remain non-interactive and server-provided read gates are preserved');
    $assert(($data['selectedWorkplaceId'] ?? null) === 1
        && ($data['moduleStationKey'] ?? null) === 'checador'
        && \App\Services\ModuleStationService::$selectedWorkplaceId === 1
        && \App\Services\ChecadorAccessService::$authorizationCalls === [null, 1],
        'selected workplace is authorized and retained in the independent Checador selector context');
    $assert(($data['links'] ?? null) === []
        && !in_array('/assets/js/departamento-operativo/2-recursos-humanos/checador.attendance.datatable.init.js?v=20261023-1', $data['scripts'] ?? [], true)
        && !str_contains($html, '<table'), 'directory does not load or render daily attendance assets or operational data');

    $controllerSource = file_get_contents($root . '/app/Controllers/ChecadorController.php') ?: '';
    $routes = file_get_contents($root . '/routes/web.php') ?: '';
    $dailyMethodStart = strpos($controllerSource, 'public function dailyAttendance(): void');
    $dailyMethodEnd = $dailyMethodStart === false ? false : strpos($controllerSource, 'public function journeys(): void', $dailyMethodStart);
    $dailyMethod = $dailyMethodStart !== false && $dailyMethodEnd !== false
        ? substr($controllerSource, $dailyMethodStart, $dailyMethodEnd - $dailyMethodStart)
        : '';
    $assert(str_contains($routes, "'/recursos-humanos/checador/asistencia', Route::auth(['ChecadorController', 'dailyAttendance'])")
        && str_contains($dailyMethod, '$this->renderDashboardWorkspace(\'daily-attendance\')'),
        'independent daily-attendance route remains registered and dispatches to its own workspace');

    printf("CHECADOR_V8P_UNDEFINED_WORKSPACE_RUNTIME: %d PASS / %d FAIL\n", $passes, $failures);
    exit($failures === 0 ? 0 : 1);
}
