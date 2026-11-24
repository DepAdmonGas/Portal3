<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Services\ChecadorAccessService;
use App\Services\ChecadorAttendanceRosterService;
use App\Services\ChecadorAttendancePolicyStoreService;
use App\Services\ChecadorRolComodinesService;
use App\Services\ChecadorV2PermissionService;
use App\Services\ChecadorDailyReadService;
use App\Services\ChecadorDailyAttendanceSqlReadService;
use App\Services\ChecadorShiftTemplateSqlReadService;
use App\Services\ChecadorShiftTemplateSqlWriteService;
use App\Services\ChecadorConfigurationReadService;
use App\Services\ChecadorOperationalPeriod;
use App\Services\ChecadorPresentationFormatter;
use App\Services\ChecadorPayrollComparisonReadService;
use App\Services\ChecadorJourneySqlReadService;
use App\Services\ChecadorAdjustmentSqlReadService;
use App\Services\ChecadorWeeklyScheduleSqlReadService;
use App\Services\ChecadorRawPunchReadService;
use App\Services\ModuleStationService;
use App\Services\RecibosNominaService;

final class ChecadorController extends BaseController
{
    public function index(): void
    {
        $this->normalizeLegacyWorkplaceUrl();
        $query = Request::query();
        if (Request::method() === 'GET' && ($query['schedule_week'] ?? '') === '1') {
            self::redirect(self::scheduleWeekHref($query));
        }
        if (($query['schedule_history'] ?? '') === '1') {
            $legacyHistoryQuery = [];
            foreach (['date', 'worker_id'] as $key) {
                if (isset($query[$key]) && is_scalar($query[$key]) && trim((string) $query[$key]) !== '') {
                    $legacyHistoryQuery[$key] = trim((string) $query[$key]);
                }
            }
            $legacyPage = self::positiveInteger($query['schedule_history_page'] ?? null);
            if ($legacyPage !== null && $legacyPage > 1) {
                $legacyHistoryQuery['page'] = $legacyPage;
            }
            $target = '/departamento-operativo/recursos-humanos/checador/horarios-turnos';
            self::redirect($target . ($legacyHistoryQuery === [] ? '' : '?' . http_build_query($legacyHistoryQuery)));
        }

        // The module entry route is a lightweight directory. Operational
        // attendance is available only through its dedicated workspace route.
        $this->renderModuleDirectory();
    }

    /** Dedicated M04 route; only canonical, safe filters are accepted. */
    public function scheduleHistory(): void
    {
        $query = Request::query();
        $safeQuery = self::scheduleHistoryQueryParameters($query);
        if ($query !== $safeQuery) {
            $path = '/departamento-operativo/recursos-humanos/checador/horarios-turnos';
            self::redirect($path . ($safeQuery === [] ? '' : '?' . http_build_query($safeQuery, '', '&', PHP_QUERY_RFC3986)));
        }
        $this->normalizeLegacyWorkplaceUrl(true);
        $this->renderDashboardWorkspace(null, true);
    }

    /** Canonical M03 route; only a valid Monday filter is retained in the URL. */
    public function scheduleWeek(): void
    {
        $query = Request::query();
        $safeQuery = self::scheduleWeekQueryParameters($query);
        if ($query !== $safeQuery) {
            self::redirect(self::scheduleWeekHref($query));
        }
        $this->renderDashboardWorkspace('schedule-week');
    }

    public function dailyAttendance(): void
    {
        if (Request::method() === 'POST') {
            $contentType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''), 2)[0]));
            $requestedWith = strtoupper(trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
            if ($contentType !== 'application/json' || $requestedWith !== 'XMLHTTPREQUEST') {
                JsonResponse::send([
                    'success' => false,
                    'error' => ['code' => 'invalid_request_format', 'message' => 'La solicitud de búsqueda no tiene un formato válido.'],
                ], 415, ['Cache-Control' => 'private, no-store']);
            }

            $payload = Request::json();
            $payloadKeys = array_keys($payload);
            sort($payloadKeys);
            $date = $payload['date'] ?? null;
            $workerId = $payload['worker_id'] ?? null;
            $validDate = $date === null || (is_string($date) && self::isCivilDate($date));
            $validWorkerId = $workerId === null
                || ((is_string($workerId) || is_int($workerId))
                    && filter_var($workerId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false);
            if ($payloadKeys !== ['date', 'worker_id'] || !$validDate || !$validWorkerId) {
                JsonResponse::send([
                    'success' => false,
                    'error' => ['code' => 'invalid_search_payload', 'message' => 'Selecciona una fecha y un filtro de colaborador válidos.'],
                ], 422, ['Cache-Control' => 'private, no-store']);
            }

            ob_start();
            $this->renderDashboardWorkspace('daily-attendance');
            $html = (string) ob_get_clean();
            $status = http_response_code();
            if ($status !== false && $status >= 400) {
                JsonResponse::send([
                    'success' => false,
                    'error' => ['code' => 'attendance_read_unavailable', 'message' => 'No fue posible consultar la asistencia para el alcance autorizado.'],
                ], $status, ['Cache-Control' => 'private, no-store']);
            }
            if (!str_contains($html, 'data-checador-attendance-results')) {
                JsonResponse::send([
                    'success' => false,
                    'error' => ['code' => 'attendance_context_unavailable', 'message' => 'No fue posible confirmar el contexto autorizado. Recarga la página.'],
                ], 403, ['Cache-Control' => 'private, no-store']);
            }

            JsonResponse::send(['success' => true, 'html' => $html], 200, ['Cache-Control' => 'private, no-store']);
        }

        $this->renderDashboardWorkspace('daily-attendance');
    }

    public function rolComodines(): void
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            self::renderAccessDenied('unauthenticated', null);
            return;
        }
        $positionId = (int) ($currentUser->id_puesto ?? 0);
        $userId = (int) ($currentUser->id ?? 0);
        if ($positionId !== 13 || !ChecadorV2PermissionService::allowsComodines(
            $userId,
            $positionId,
            ChecadorV2PermissionService::COMODINES_READ
        )) {
            self::renderAccessDenied('comodines_permission_denied', null);
            return;
        }

        $access = ChecadorAccessService::authorize(null);
        if (empty($access['allowed'])) {
            self::renderAccessDenied((string) ($access['reason'] ?? 'authorization_lookup_failed'), null);
            return;
        }

        $stationWorkplaces = is_array($access['station_workplaces'] ?? null)
            ? $access['station_workplaces']
            : [];
        $authorizedWorkplaceIds = array_values(array_map(
            static fn(array $item): int => (int) ($item['id'] ?? 0),
            $stationWorkplaces
        ));
        $workplaceId = $this->comodinesSessionWorkplaceId($access);
        if ($workplaceId !== null && !in_array($workplaceId, $authorizedWorkplaceIds, true)) {
            self::renderAccessDenied('requested_station_out_of_scope', $workplaceId);
            return;
        }

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Checador', '/departamento-operativo/recursos-humanos/checador');
        Breadcrumb::add('Rol de comodines', '');

        $selectedWorkplaceName = '';
        foreach ($stationWorkplaces as $workplace) {
            if ((int) ($workplace['id'] ?? 0) === (int) ($workplaceId ?? 0)) {
                $selectedWorkplaceName = (string) ($workplace['nombre'] ?? '');
                break;
            }
        }

        $roleReadAuthorization = $workplaceId === null
            ? ['allowed' => false, 'reason' => 'station_selection_required']
            : ChecadorRolComodinesService::authorizeCanonicalProgramming($workplaceId, 'read');
        $readModel = $workplaceId === null || empty($roleReadAuthorization['allowed'])
            ? ['ok' => false, 'error' => $workplaceId === null ? 'station_selection_required' : 'legacy_read_not_authorized']
            : ChecadorRolComodinesService::getChecadorReadModel(
                $workplaceId,
                $roleReadAuthorization['station_workplace_ids'] ?? []
            );
        $state = !empty($readModel['ok']) ? 'ready' : 'unavailable';
        $stateMessage = match ($readModel['error'] ?? '') {
            'station_selection_required' => 'Selecciona una estación autorizada para consultar sus programaciones.',
            'legacy_read_not_authorized' => 'Los registros históricos no están disponibles para este perfil. La consulta canónica usa sus permisos independientes.',
            'station_mapping_unavailable' => 'No se pudo resolver de forma única la relación entre la estación y la localidad.',
            'read_limit_exceeded' => 'La consulta excede el límite seguro de lectura. Solicita revisión administrativa.',
            default => $state === 'ready' ? '' : 'No se pudo consultar el rol de comodines de forma segura.',
        };
        $coverageWriter = new \App\Services\RolComodinesPortalWriterService();
        // The shared MariaDB is the canonical record. The Portal3 administration UI
        // must not depend on the API HTTP service to list or mutate coverage records.
        $coverageRead = $coverageWriter->listVisible($authorizedWorkplaceIds);
        $coverageReadMessage = match ((string) ($coverageRead['code'] ?? '')) {
            'coverage_scope_denied' => 'No se pudieron consultar coberturas fuera del alcance autorizado.',
            default => !empty($coverageRead['ok']) ? '' : 'No fue posible consultar las coberturas canónicas en este momento.',
        };
        $coverageRows = !empty($coverageRead['ok'])
            ? ChecadorRolComodinesService::coveragePresentationRows($coverageRead['data']['items'] ?? [])
            : [];
        $canRequestCoverageGrant = $currentUser instanceof \App\Models\Usuario
            && ChecadorV2PermissionService::allowsComodines(
                $userId,
                $positionId,
                ChecadorV2PermissionService::COMODINES_REQUEST
            );
        $canApproveCoverageGrant = $currentUser instanceof \App\Models\Usuario
            && ChecadorV2PermissionService::allowsComodines(
                $userId,
                $positionId,
                ChecadorV2PermissionService::COMODINES_APPROVE
            );
        $canCancelCoverageGrant = $currentUser instanceof \App\Models\Usuario
            && ChecadorV2PermissionService::allowsComodines(
                $userId,
                $positionId,
                ChecadorV2PermissionService::COMODINES_CANCEL
            );
        $coverageWorkplaces = $canRequestCoverageGrant && $workplaceId !== null
            ? array_values(array_filter(
                ChecadorRolComodinesService::coverageWorkplaces(0),
                static fn(array $workplace): bool => in_array((int) ($workplace['id'] ?? 0), $authorizedWorkplaceIds, true)
            ))
            : [];
        $coverageTemplates = [];
        $coverageTemplateCatalogReady = false;
        if ($canRequestCoverageGrant && $workplaceId !== null && !empty($coverageRead['ok'])) {
            $catalog = $coverageWriter->templateCatalog();
            if (!empty($catalog['ok']) && !empty($catalog['data']['complete'])) {
                $coverageTemplates = $catalog['data']['items'] ?? [];
                $coverageTemplateCatalogReady = true;
            }
        }
        $canRequestCoverage = $canRequestCoverageGrant
            && $workplaceId !== null
            && !empty($coverageRead['ok'])
            && $coverageTemplateCatalogReady
            && $coverageTemplates !== []
            && $coverageWorkplaces !== [];
        $returnHref = '/departamento-operativo/recursos-humanos/checador';

        View::render(
            'departamento-operativo/2-recursos-humanos/checador/role-comodines',
            [
                'title' => 'Rol de comodines',
                'roles' => $readModel['roles'] ?? [],
                'state' => $state,
                'stateMessage' => $stateMessage,
                'selectedWorkplaceId' => $workplaceId,
                'selectedWorkplaceName' => $selectedWorkplaceName,
                'coverageRows' => $coverageRows,
                'coverageReadState' => !empty($coverageRead['ok']) ? 'ready' : 'unavailable',
                'coverageReadMessage' => $coverageReadMessage,
                'coverageWorkplaces' => $coverageWorkplaces,
                'coverageTemplates' => $coverageTemplates,
                'canRequestCoverage' => $canRequestCoverage,
                'canReviewCoverage' => $canApproveCoverageGrant && !empty($coverageRead['ok']),
                'canCancelCoverage' => $canCancelCoverageGrant && !empty($coverageRead['ok']),
                'coverageCreateUrl' => '/departamento-operativo/recursos-humanos/checador/rol-comodines/coberturas',
                'coverageWorkersUrl' => '/departamento-operativo/recursos-humanos/checador/rol-comodines/trabajadores',
                'coverageDetailUrl' => '/departamento-operativo/recursos-humanos/checador/rol-comodines/coberturas',
                'workplaces' => $stationWorkplaces,
                'returnHref' => $returnHref,
                'moduleStationKey' => ChecadorAccessService::MODULE_KEY,
                'links' => [
                    '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                    '/assets/libs/select2/dist/css/select2.min.css',
                    '/assets/css/select2-modal.css',
                ],
                'scripts' => [
                    '/assets/js/vendor.min.js',
                    '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                    '/assets/libs/select2/dist/js/select2.full.min.js',
                    '/assets/js/core/module-station-selector.js',
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.module-station.init.js?v=20261027-1',
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.role-comodines.datatable.init.js?v=20261110-1',
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.role-comodines.programming.init.js?v=20261123-1',
                ],
                'help' => false,
            ],
            'departamento-operativo'
        );
    }


    public function journeys(): void
    {
        $query = Request::query();
        $access = ChecadorAccessService::authorize(null);
        if (!($access['allowed'] ?? false)) {
            self::renderAccessDenied((string) ($access['reason'] ?? 'authorization_lookup_failed'), null);
            return;
        }
        $workplaces = is_array($access['workplaces'] ?? null) ? $access['workplaces'] : [];
        $isOperationsDirector = (int) ($access['position_id'] ?? 0) === 13;
        $rawWorkplace = $query['workplace_id'] ?? null;
        $workplaceId = $rawWorkplace === null ? null : (is_scalar($rawWorkplace) ? self::positiveInteger($rawWorkplace) : null);
        if (array_key_exists('workplace_id', $query) && $rawWorkplace !== '' && $workplaceId === null) {
            self::renderAccessDenied('invalid_station_selection', null);
            return;
        }
        if ($workplaceId === null) {
            $context = ModuleStationService::getContext(ChecadorAccessService::MODULE_KEY);
            $contextId = self::positiveInteger($context['id_depto'] ?? $context['id_estacion'] ?? null);
            $savedContexts = Session::get('module_context') ?? [];
            $savedChecadorContext = $savedContexts[ChecadorAccessService::MODULE_KEY] ?? [];
            $hasSavedContext = !empty($savedChecadorContext['id_depto']) || !empty($savedChecadorContext['id_estacion']);
            if ($isOperationsDirector && !$hasSavedContext) {
                // ModuleStationService may infer a sole allowed workplace for
                // ordinary users; Dirección still must make an explicit choice.
                $contextId = null;
            }
            $authorizedIds = array_map('intval', array_column($workplaces, 'id'));
            $workplaceId = $contextId !== null && in_array($contextId, $authorizedIds, true)
                ? $contextId
                : (!$isOperationsDirector && isset($workplaces[0]['id']) ? (int) $workplaces[0]['id'] : null);
        }
        $selectedWorkplace = null;
        if ($workplaceId !== null) {
            $selectedAccess = ChecadorAccessService::authorize($workplaceId);
            if (!($selectedAccess['allowed'] ?? false)) {
                self::renderAccessDenied((string) ($selectedAccess['reason'] ?? 'requested_station_out_of_scope'), $workplaceId);
                return;
            }
            $authorizedIds = array_map('intval', array_column($workplaces, 'id'));
            if (!in_array($workplaceId, $authorizedIds, true)
                || !ModuleStationService::setChecadorWorkplaceContext($workplaceId)) {
                self::renderAccessDenied('requested_station_out_of_scope', $workplaceId);
                return;
            }
            foreach ($workplaces as $workplace) {
                if ((int) ($workplace['id'] ?? 0) === $workplaceId) { $selectedWorkplace = $workplace; break; }
            }
            if ($selectedWorkplace === null) {
                self::renderAccessDenied('requested_station_out_of_scope', $workplaceId);
                return;
            }
        }
        $readString = static function (string $key) use ($query): ?string {
            $value = $query[$key] ?? null;
            return is_string($value) && trim($value) !== '' ? trim($value) : null;
        };
        $startDate = $readString('start_date');
        $endDate = $readString('end_date');
        $workerRaw = $query['worker_id'] ?? null;
        $workerId = $workerRaw === null || $workerRaw === '' ? null : self::positiveInteger($workerRaw);
        $workerInvalid = $workerRaw !== null && $workerRaw !== '' && $workerId === null;
        $pageRaw = $query['page'] ?? 1;
        $page = self::positiveInteger($pageRaw);
        $pageInvalid = $page === null;
        $page ??= 1;
        $state = $workplaceId === null ? 'selection_required' : 'filter_required';
        $stateMessage = $workplaceId === null
            ? 'Selecciona una estación o departamento autorizado para consultar su padrón y sus jornadas.'
            : 'Selecciona un rango de fechas locales para consultar jornadas persistidas.';
        $journeys = [];
        $rosterOptions = $workplaceId === null
            ? ['workers' => [], 'message' => 'Selecciona una localidad para cargar su padrón autorizado.']
            : self::journeyWorkerRosterOptions($workplaceId, $startDate, $endDate);
        $workerOptions = $rosterOptions['workers'];
        $workerOptionsMessage = $rosterOptions['message'];
        $pagination = ['page' => $page, 'per_page' => ChecadorJourneySqlReadService::DEFAULT_PAGE_SIZE, 'total_records' => 0, 'total_pages' => 0];
        $excludedUnverifiedCount = 0;

        if ($workplaceId === null) {
            // Dirección must choose an authorized locality explicitly. Do not
            // query the roster or C01 until the server has a validated scope.
            $state = 'selection_required';
        } elseif ($workerInvalid || $pageInvalid) {
            $state = 'invalid_filter';
            $stateMessage = 'El trabajador o la página solicitados no son válidos.';
        } elseif (($startDate === null) !== ($endDate === null)) {
            $state = 'invalid_filter';
            $stateMessage = 'Indica ambas fechas para consultar el periodo laboral.';
        } elseif ($startDate !== null && $endDate !== null) {
            $read = (new ChecadorJourneySqlReadService())->read($workplaceId, $startDate, $endDate, $workerId, $page);
            if (($read['ok'] ?? false) === true) {
                $state = 'success';
                $stateMessage = '';
                $journeys = $read['data']['journeys'] ?? [];
                foreach ($read['data']['worker_options'] ?? [] as $persistedWorker) {
                    if (!is_array($persistedWorker)) continue;
                    $persistedWorkerId = (int) ($persistedWorker['worker_id'] ?? 0);
                    if ($persistedWorkerId > 0 && !isset($workerOptions[$persistedWorkerId])) {
                        $workerOptions[$persistedWorkerId] = $persistedWorker;
                    }
                }
                $pagination = $read['data']['pagination'] ?? $pagination;
                $excludedUnverifiedCount = (int) ($read['data']['excluded_unverified_count'] ?? 0);
            } else {
                $state = 'error';
                $stateMessage = match ((string) ($read['error'] ?? 'sql_unavailable')) {
                    'permission_denied' => 'No tienes permiso para consultar esta localidad.',
                    'invalid_date_range', 'invalid_request' => 'El filtro no es válido. El rango máximo es de 31 fechas laborales.',
                    'page_out_of_range' => 'La página solicitada ya no está dentro de los resultados del filtro.',
                    'result_limit_exceeded' => 'El periodo contiene demasiadas filas para una consulta segura. Reduce el rango de fechas.',
                    'schema_unavailable' => 'La proyección SQL de Jornadas no está completa o disponible.',
                    'persisted_data_inconsistent' => 'Hay datos persistidos con relaciones o revisiones inconsistentes. No se presentan como resultados confirmados.',
                    default => 'No fue posible consultar las jornadas persistidas. La falla no se presenta como un resultado vacío.',
                };
            }
        }

        $selectedWorkplaceName = trim((string) ($selectedWorkplace['nombre'] ?? ''));
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Checador', '/departamento-operativo/recursos-humanos/checador');
        Breadcrumb::add('Jornadas laborales', '');
        View::render('departamento-operativo/2-recursos-humanos/checador/workspace-journeys', [
            'title' => 'Jornadas laborales',
            'workspace' => 'journeys',
            'moduleStationKey' => ChecadorAccessService::MODULE_KEY,
            'moduleStationForceSelector' => $isOperationsDirector,
            'moduleStationRequireExplicitSelection' => $isOperationsDirector && $workplaceId === null,
            'workplaces' => $workplaces,
            'selectedWorkplaceId' => $workplaceId,
            'selectedWorkplaceName' => $selectedWorkplaceName,
            'startDate' => $startDate ?? '',
            'endDate' => $endDate ?? '',
            'selectedWorkerId' => $workerId,
            'workerSelectOptions' => $workerOptions,
            'workerOptionsMessage' => $workerOptionsMessage,
            'journeys' => $journeys,
            'journeyErrors' => [],
            'journeyPagination' => $pagination,
            'excludedUnverifiedCount' => $excludedUnverifiedCount,
            'state' => $state,
            'stateMessage' => $stateMessage,
            'links' => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/assets/libs/select2/dist/css/select2.min.css',
                '/assets/css/select2-modal.css',
            ],
            'scripts' => [
                '/assets/js/vendor.min.js',
                '/assets/js/core/module-station-selector.js',
                '/assets/libs/select2/dist/js/select2.full.min.js',
                '/assets/libs/select2/dist/js/i18n/es.js',
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/departamento-operativo/2-recursos-humanos/checador.module-station.init.js?v=20261120-journeys-1',
                '/assets/js/departamento-operativo/2-recursos-humanos/checador.journeys.datatable.init.js?v=20261120-5',
            ],
            'help' => false,
        ], 'departamento-operativo');
    }

    /** Build the filter catalog from the existing authorized roster, independently of persisted journeys. */
    private static function journeyWorkerRosterOptions(int $workplaceId, ?string $startDate, ?string $endDate): array
    {
        $dates = [];
        $message = '';
        $timezoneReader = new ChecadorWeeklyScheduleSqlReadService();
        if ($startDate === null && $endDate === null) {
            $nowUtc = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $probeDate = $nowUtc->format('Y-m-d');
            $probeTimezone = $timezoneReader->timezoneForDate($workplaceId, $probeDate);
            if ($probeTimezone === null || !ChecadorOperationalPeriod::isIanaTimezone($probeTimezone)) {
                return ['workers' => [], 'message' => 'No se pudo validar la zona horaria para cargar el padrón autorizado.'];
            }
            $localDate = $nowUtc->setTimezone(new \DateTimeZone($probeTimezone))->format('Y-m-d');
            $localTimezone = $timezoneReader->timezoneForDate($workplaceId, $localDate);
            if ($localTimezone === null || !ChecadorOperationalPeriod::isIanaTimezone($localTimezone)) {
                return ['workers' => [], 'message' => 'No se pudo validar la zona horaria para cargar el padrón autorizado.'];
            }
            $dates[$localDate] = $localTimezone;
            $message = 'Padrón activo de la localidad. Al consultar, la adscripción se valida para cada fecha seleccionada.';
        } elseif ($startDate !== null && $endDate !== null
            && self::isCivilDate($startDate) && self::isCivilDate($endDate)) {
            $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $startDate, new \DateTimeZone('UTC'));
            $end = \DateTimeImmutable::createFromFormat('!Y-m-d', $endDate, new \DateTimeZone('UTC'));
            if ($start === false || $end === false || $end < $start || (int) $start->diff($end)->format('%a') + 1 > ChecadorJourneySqlReadService::MAX_RANGE_DAYS) {
                return ['workers' => [], 'message' => 'Corrige el rango para cargar trabajadores con adscripción confirmada en esas fechas.'];
            }
            for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
                $date = $day->format('Y-m-d');
                $timezone = $timezoneReader->timezoneForDate($workplaceId, $date);
                if ($timezone === null || !ChecadorOperationalPeriod::isIanaTimezone($timezone)) {
                    $message = 'El padrón puede estar incompleto porque no se confirmó la zona horaria para todas las fechas.';
                    continue;
                }
                $dates[$date] = $timezone;
            }
            if ($message === '') $message = 'Trabajadores con adscripción canónica confirmada en al menos una fecha del rango.';
        } else {
            return ['workers' => [], 'message' => 'Indica ambas fechas válidas para cargar el padrón histórico del periodo.'];
        }

        $workersById = [];
        $incomplete = false;
        $rosterService = new ChecadorAttendanceRosterService();
        $nowUtc = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        foreach ($dates as $date => $timezone) {
            $roster = $rosterService->workersForAttendanceFilter($workplaceId, $date, $timezone, false);
            $coverage = (string) ($roster['coverage'] ?? 'unknown');
            $isCurrentWorkday = ChecadorOperationalPeriod::currentLocalDate($timezone, $nowUtc) === $date;
            $coverageUsable = in_array($coverage, ['historical_versioned_membership', 'incomplete'], true)
                || ($coverage === 'current_active_roster' && $isCurrentWorkday);
            if (!in_array((string) ($roster['status'] ?? ''), ['ready', 'partial'], true) || !$coverageUsable) {
                $incomplete = true;
                continue;
            }
            foreach ($roster['workers'] ?? [] as $worker) {
                if (!is_array($worker)) continue;
                $workerId = (int) ($worker['worker_id'] ?? 0);
                $name = trim((string) ($worker['full_name'] ?? ''));
                if ($workerId < 1 || $name === '' || $name === 'Nombre no disponible') continue;
                $employeeNumber = trim((string) ($worker['employee_number'] ?? ''));
                $workersById[$workerId] = [
                    'worker_id' => $workerId,
                    'full_name' => $name,
                    'employee_number' => $employeeNumber !== '' && $employeeNumber !== '0' ? $employeeNumber : null,
                ];
            }
            if (($roster['status'] ?? '') !== 'ready') $incomplete = true;
        }
        if ($incomplete) $message = 'El padrón puede estar incompleto; sólo se muestran adscripciones verificadas para las fechas cuyo contexto se confirmó.';
        if ($dates === []) $message = 'No se pudo confirmar la zona horaria de las fechas solicitadas; no se cargó el padrón.';
        if ($workersById === [] && !$incomplete && $dates !== []) {
            $message = 'No se encontraron trabajadores con adscripción canónica confirmada para el contexto consultado.';
        }
        uasort($workersById, static fn(array $left, array $right): int =>
            strcasecmp($left['full_name'], $right['full_name']) ?: ($left['worker_id'] <=> $right['worker_id'])
        );
        return ['workers' => $workersById, 'message' => $message];
    }

    public function incidences(): void
    {
        $query = Request::query();
        if ($query !== []) {
            self::redirect('/departamento-operativo/recursos-humanos/checador/incidencias');
            return;
        }
        $access = ChecadorAccessService::authorize(null);
        if (!($access['allowed'] ?? false)) {
            $reason = (string) ($access['reason'] ?? 'authorization_lookup_failed');
            self::logM06IncidenceAccessDenial('checador_scope', $reason, null);
            self::renderAccessDenied($reason, null);
            return;
        }

        $workplaces = is_array($access['workplaces'] ?? null) ? $access['workplaces'] : [];
        $context = ModuleStationService::getContext(ChecadorAccessService::MODULE_KEY);
        $contextId = self::positiveInteger($context['id_depto'] ?? $context['id_estacion'] ?? null);
        $authorizedIds = array_map('intval', array_column($workplaces, 'id'));
        $workplaceId = $contextId !== null && in_array($contextId, $authorizedIds, true)
            ? $contextId
            : (isset($workplaces[0]['id']) ? (int) $workplaces[0]['id'] : null);
        if ($workplaceId === null) {
            self::logM06IncidenceAccessDenial('locality_selection', 'empty_station_scope', null);
            self::renderAccessDenied('empty_station_scope', null);
            return;
        }
        $readAuthorization = ChecadorAccessService::authorizeIncidenceRead($workplaceId);
        if (!($readAuthorization['allowed'] ?? false)) {
            $reason = (string) ($readAuthorization['reason'] ?? 'requested_station_out_of_scope');
            self::logM06IncidenceAccessDenial('incidence_read_acl', $reason, $workplaceId);
            self::renderAccessDenied($reason, $workplaceId);
            return;
        }
        if (!ModuleStationService::setChecadorWorkplaceContext($workplaceId)) {
            self::logM06IncidenceAccessDenial('workplace_context', 'requested_station_out_of_scope', $workplaceId);
            self::renderAccessDenied('requested_station_out_of_scope', $workplaceId);
            return;
        }

        $selectedWorkplace = null;
        foreach ($readAuthorization['workplaces'] ?? [] as $workplace) {
            if ((int) ($workplace['id'] ?? 0) === $workplaceId) { $selectedWorkplace = $workplace; break; }
        }
        if ($selectedWorkplace === null) {
            self::logM06IncidenceAccessDenial('selected_workplace', 'requested_station_out_of_scope', $workplaceId);
            self::renderAccessDenied('requested_station_out_of_scope', $workplaceId);
            return;
        }

        $actor = Auth::user();
        if (!$actor) {
            self::logM06IncidenceAccessDenial('session_identity', 'unauthenticated', $workplaceId);
            self::renderAccessDenied('unauthenticated', $workplaceId);
            return;
        }

        $service = self::m06AdministrativeIncidenceSqlService();
        $state = 'ready';

        $policyRead = (new ChecadorAttendancePolicyStoreService())->readCurrent($workplaceId);
        $timezone = (string) ($policyRead['data']['timezone_id'] ?? '');
        $today = (string) ($policyRead['data']['current_local_date'] ?? gmdate('Y-m-d'));
        $rosterDate = $today;
        $roster = (new \App\Services\ChecadorAttendanceRosterService())->workersForAttendanceFilter(
            $workplaceId,
            $rosterDate,
            $timezone !== '' ? $timezone : null,
            false
        );
        $workers = in_array((string) ($roster['status'] ?? ''), ['ready', 'partial', 'current_roster_only'], true)
            ? (is_array($roster['workers'] ?? null) ? $roster['workers'] : [])
            : [];
        $createAuthorization = ChecadorAccessService::authorizeIncidenceCreate($workplaceId);
        $canCreate = (bool) ($createAuthorization['allowed'] ?? false);
        $revisionAuthorization = ChecadorAccessService::authorizeIncidenceRevision($workplaceId);
        $lifecyclePermissions = [];
        foreach (['resolve', 'authorize', 'reject', 'revoke'] as $lifecycleAction) {
            $lifecyclePermissions[$lifecycleAction] = (bool) (ChecadorAccessService::authorizeLifecycle($workplaceId, $lifecycleAction)['allowed'] ?? false);
        }
        $writeReadiness = $service->writeReadiness();

        $feedback = Session::pull('checador_m06_incidence_feedback', null);
        $currentUserId = (int) $actor->id;
        if (!is_array($feedback)
            || (int) ($feedback['user_id'] ?? 0) !== $currentUserId
            || (int) ($feedback['workplace_id'] ?? 0) !== $workplaceId) {
            $feedback = null;
        }
        $lifecycleFeedback = Session::pull('checador_m06_incidence_lifecycle_feedback', null);
        if (is_array($lifecycleFeedback)
            && (int) ($lifecycleFeedback['user_id'] ?? 0) === $currentUserId
            && (int) ($lifecycleFeedback['workplace_id'] ?? 0) === $workplaceId) {
            $feedback = $lifecycleFeedback;
        }
        $retryKey = is_array($feedback) && ($feedback['type'] ?? '') === 'error'
            && preg_match('/\A[A-Za-z0-9._:-]{1,64}\z/D', (string) ($feedback['idempotency_key'] ?? ''))
            ? (string) $feedback['idempotency_key']
            : bin2hex(random_bytes(24));
        $formValues = is_array($feedback['form_values'] ?? null) ? $feedback['form_values'] : [];

        $selectedWorkplaceName = trim((string) ($selectedWorkplace['nombre'] ?? ''));
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Checador', '/departamento-operativo/recursos-humanos/checador');
        Breadcrumb::add('Incidencias', '');

        View::render(
            'departamento-operativo/2-recursos-humanos/checador/workspace-incidences',
            [
                'title' => 'Incidencias',
                'moduleStationKey' => ChecadorAccessService::MODULE_KEY,
                'workplaces' => $workplaces,
                'selectedWorkplaceId' => $workplaceId,
                'selectedWorkplaceName' => $selectedWorkplaceName,
                'selectedDate' => $today,
                'dashboardHref' => '/departamento-operativo/recursos-humanos/checador',
                'incidenceState' => $state,
                'incidenceWorkers' => $workers,
                'incidenceRosterAvailable' => in_array((string) ($roster['status'] ?? ''), ['ready', 'partial', 'current_roster_only'], true),
                'incidenceCanCreate' => $canCreate,
                'incidenceCanRevise' => (bool) ($revisionAuthorization['allowed'] ?? false),
                'incidenceLifecyclePermissions' => $lifecyclePermissions,
                'incidenceWriterReady' => (bool) ($writeReadiness['ready'] ?? false),
                'incidenceWriterBlockReason' => (string) ($writeReadiness['reason'] ?? 'schema_preflight_failed'),
                'incidenceFeedback' => $feedback,
                'incidenceFormValues' => $formValues,
                'incidenceIdempotencyKey' => $retryKey,
                'incidenceCategories' => [
                    'VACATION' => 'Vacaciones',
                    'PERMISSION' => 'Permiso',
                    'DISABILITY' => 'Incapacidad',
                    'AUTHORIZED_LATENESS' => 'Retardo autorizado',
                    'AUTHORIZED_EARLY_DEPARTURE' => 'Salida anticipada autorizada',
                    'OFFICIAL_MISSION' => 'Misión oficial',
                ],
                'links' => [
                    '/assets/libs/select2/dist/css/select2.min.css',
                    '/assets/css/select2-modal.css',
                    '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                ],
                'scripts' => [
                    '/assets/js/vendor.min.js',
                    '/assets/libs/select2/dist/js/select2.full.min.js',
                    '/assets/libs/select2/dist/js/i18n/es.js',
                    '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                    '/assets/js/core/module-station-selector.js',
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.module-station.init.js?v=20261027-1',
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.incidences.datatable.init.js?v=20261123-1',
                ],
                'help' => false,
            ],
            'departamento-operativo'
        );
    }

    /** Read M06 incidence rows for DataTables without putting filters in the URL. */
    public function incidencesData(): void
    {
        $payload = Request::json();
        $request = self::normalizeM06IncidenceDataPayload($payload);
        $draw = (int) ($request['draw'] ?? 0);
        $empty = ['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []];
        if (!($request['ok'] ?? false)) {
            $message = match ((string) ($request['error'] ?? '')) {
                'invalid_filters' => 'Los filtros enviados no son válidos.',
                'invalid_search' => 'El texto de búsqueda no es válido.',
                'invalid_order' => 'El orden solicitado no es válido.',
                default => 'La solicitud de búsqueda no es válida.',
            };
            JsonResponse::custom($empty + ['success' => false, 'message' => $message], 422);
        }
        $workplaceId = (int) $request['workplace_id'];

        $actor = Auth::user();
        if (!$actor) JsonResponse::custom($empty + ['success' => false, 'message' => 'La sesión ya no está disponible.'], 401);

        $access = ChecadorAccessService::authorize($workplaceId);
        $readAuthorization = ChecadorAccessService::authorizeIncidenceRead($workplaceId);
        if (!($access['allowed'] ?? false) || !($readAuthorization['allowed'] ?? false)) {
            self::logM06IncidenceAccessDenial('incidence_data_acl', (string) ($readAuthorization['reason'] ?? $access['reason'] ?? 'requested_station_out_of_scope'), $workplaceId);
            JsonResponse::custom($empty + ['success' => false, 'message' => 'No tienes permiso efectivo para consultar incidencias en esta localidad.'], 403);
        }

        $context = ModuleStationService::getContext(ChecadorAccessService::MODULE_KEY);
        $contextId = self::positiveInteger($context['id_depto'] ?? $context['id_estacion'] ?? null);
        if ($contextId !== $workplaceId) {
            self::logM06IncidenceAccessDenial('incidence_data_context', 'selected_station_context_mismatch', $workplaceId);
            JsonResponse::custom($empty + ['success' => false, 'message' => 'La localidad activa cambió. Actualiza la página y vuelve a buscar.'], 403);
        }

        $page = (int) $request['page'];
        $read = self::m06AdministrativeIncidenceSqlService()->listForWorkplace(
            $actor, $workplaceId, $request['filters'], $page, (int) $request['length'],
            $request['sort_by'], $request['sort_direction']
        );
        if (!($read['ok'] ?? false)) {
            $error = (string) ($read['error'] ?? 'sql_read_failed');
            $status = $error === 'permission_denied' ? 403 : ($error === 'invalid_filter' || $error === 'invalid_request' ? 422 : 500);
            $message = match ($error) {
                'permission_denied' => 'No tienes permiso efectivo para consultar incidencias en esta localidad.',
                'invalid_filter', 'invalid_request' => 'Los filtros enviados no son válidos.',
                default => 'Ocurrió un error al consultar las incidencias. Intenta nuevamente.',
            };
            JsonResponse::custom($empty + ['success' => false, 'message' => $message], $status);
        }

        $data = $read['data'] ?? [];
        $pagination = $data['pagination'] ?? [];
        JsonResponse::custom([
            'success' => true,
            'draw' => $draw,
            'recordsTotal' => (int) ($pagination['records_total'] ?? 0),
            'recordsFiltered' => (int) ($pagination['total_records'] ?? 0),
            'data' => is_array($data['items'] ?? null) ? $data['items'] : [],
        ]);
    }

    /** Normalize the exact JSON shape emitted by DataTables/Axios before ACL or SQL work. */
    private static function normalizeM06IncidenceDataPayload(array $payload): array
    {
        $draw = self::nonNegativeInteger($payload['draw'] ?? 0);
        $draw = $draw !== null ? min($draw, 1000000000) : null;
        $workplaceId = self::positiveInteger($payload['workplace_id'] ?? null);
        $start = self::nonNegativeInteger($payload['start'] ?? 0);
        $length = self::positiveInteger($payload['length'] ?? 25);
        if ($draw === null || $workplaceId === null || $start === null || $start > 10000000
            || $length === null || !in_array($length, [10, 25, 50, 100], true)) {
            return ['ok' => false, 'error' => 'invalid_request', 'draw' => $draw ?? 0];
        }

        $workerRaw = $payload['worker_id'] ?? null;
        $workerId = $workerRaw === null || (is_string($workerRaw) && trim($workerRaw) === '')
            ? null
            : self::positiveInteger(is_string($workerRaw) ? trim($workerRaw) : $workerRaw);
        if ($workerRaw !== null && !(is_string($workerRaw) && trim($workerRaw) === '') && $workerId === null) {
            return ['ok' => false, 'error' => 'invalid_filters', 'draw' => $draw];
        }

        $normalizeOptionalString = static function (mixed $value): mixed {
            if ($value === null) return null;
            if (!is_string($value)) return $value;
            $trimmed = trim($value);
            return $trimmed === '' ? null : $trimmed;
        };
        $filters = [
            'worker_id' => $workerId,
            'category' => $normalizeOptionalString($payload['category'] ?? null),
            'status' => $normalizeOptionalString($payload['status'] ?? null),
            'start_date' => $normalizeOptionalString($payload['start_date'] ?? null),
            'end_date' => $normalizeOptionalString($payload['end_date'] ?? null),
        ];

        $search = $payload['search'] ?? [];
        if (is_string($search)) {
            $searchValue = trim($search);
        } elseif (is_array($search)) {
            $searchValue = $search['value'] ?? '';
            $regex = $search['regex'] ?? false;
            if (!is_bool($regex) || $regex) return ['ok' => false, 'error' => 'invalid_search', 'draw' => $draw];
            if (!is_string($searchValue)) return ['ok' => false, 'error' => 'invalid_search', 'draw' => $draw];
            $searchValue = trim($searchValue);
        } else {
            return ['ok' => false, 'error' => 'invalid_search', 'draw' => $draw];
        }
        if (strlen($searchValue) > 120) return ['ok' => false, 'error' => 'invalid_search', 'draw' => $draw];
        $filters['search'] = $searchValue === '' ? null : $searchValue;

        $sortColumns = [0 => 'worker_name', 1 => 'category', 2 => 'start_date', 3 => 'status', 4 => 'created_at'];
        $sortBy = 'created_at';
        $sortDirection = 'desc';
        $order = $payload['order'] ?? [];
        if (!is_array($order)) return ['ok' => false, 'error' => 'invalid_order', 'draw' => $draw];
        if (isset($order[0])) {
            if (!is_array($order[0])) return ['ok' => false, 'error' => 'invalid_order', 'draw' => $draw];
            $column = self::nonNegativeInteger($order[0]['column'] ?? null);
            $direction = $order[0]['dir'] ?? null;
            if ($column === null || !isset($sortColumns[$column]) || !is_string($direction)
                || !in_array(strtolower($direction), ['asc', 'desc'], true)) {
                return ['ok' => false, 'error' => 'invalid_order', 'draw' => $draw];
            }
            $sortBy = $sortColumns[$column];
            $sortDirection = strtolower($direction);
        }

        return [
            'ok' => true,
            'draw' => $draw,
            'workplace_id' => $workplaceId,
            'start' => $start,
            'length' => $length,
            'page' => intdiv($start, $length) + 1,
            'filters' => $filters,
            'sort_by' => $sortBy,
            'sort_direction' => $sortDirection,
        ];
    }

    public function adjustments(): void
    {
        $query = Request::query();
        $access = ChecadorAccessService::authorize(null);
        if (!($access['allowed'] ?? false)) {
            self::renderAccessDenied((string) ($access['reason'] ?? 'authorization_lookup_failed'), null);
            return;
        }
        $workplaces = is_array($access['workplaces'] ?? null) ? $access['workplaces'] : [];
        $authorizedIds = array_values(array_map(static fn(array $item): int => (int) ($item['id'] ?? 0), $workplaces));
        $isDirector = (int) ($access['position_id'] ?? 0) === 13;
        $rawWorkplace = $query['workplace_id'] ?? null;
        $workplaceId = $rawWorkplace === null || $rawWorkplace === '' ? null : self::positiveInteger($rawWorkplace);
        if ($rawWorkplace !== null && $rawWorkplace !== '' && $workplaceId === null) {
            self::renderAccessDenied('invalid_station_selection', null);
            return;
        }
        if ($workplaceId === null) {
            $context = ModuleStationService::getContext(ChecadorAccessService::MODULE_KEY);
            $contextId = self::positiveInteger($context['id_depto'] ?? $context['id_estacion'] ?? null);
            $saved = Session::get('module_context') ?? [];
            $savedChecador = $saved[ChecadorAccessService::MODULE_KEY] ?? [];
            $hasSavedContext = !empty($savedChecador['id_depto']) || !empty($savedChecador['id_estacion']);
            if ($isDirector && !$hasSavedContext) $contextId = null;
            $workplaceId = $contextId !== null && in_array($contextId, $authorizedIds, true)
                ? $contextId
                : (!$isDirector && isset($workplaces[0]['id']) ? (int) $workplaces[0]['id'] : null);
        }
        $selectedWorkplace = null;
        if ($workplaceId !== null) {
            $selectedAccess = ChecadorAccessService::authorize($workplaceId);
            if (!($selectedAccess['allowed'] ?? false) || !in_array($workplaceId, $authorizedIds, true)
                || !ModuleStationService::setChecadorWorkplaceContext($workplaceId)) {
                self::renderAccessDenied((string) ($selectedAccess['reason'] ?? 'requested_station_out_of_scope'), $workplaceId);
                return;
            }
            foreach ($workplaces as $option) {
                if ((int) ($option['id'] ?? 0) === $workplaceId) { $selectedWorkplace = $option; break; }
            }
            if ($selectedWorkplace === null) {
                self::renderAccessDenied('requested_station_out_of_scope', $workplaceId);
                return;
            }
        } elseif ($rawWorkplace === '') {
            ModuleStationService::setContext(ChecadorAccessService::MODULE_KEY, null, null);
        }

        $readFilter = static function (string $key) use ($query): ?string {
            $value = $query[$key] ?? null;
            return is_string($value) && trim($value) !== '' ? trim($value) : null;
        };
        $startDate = $readFilter('start_date');
        $endDate = $readFilter('end_date');
        $workerRaw = $query['worker_id'] ?? null;
        $workerId = $workerRaw === null || $workerRaw === '' ? null : self::positiveInteger($workerRaw);
        $workerInvalid = $workerRaw !== null && $workerRaw !== '' && $workerId === null;
        $type = $readFilter('type');
        $status = $readFilter('status');
        $page = self::positiveInteger($query['page'] ?? 1);
        $pageInvalid = $page === null;
        $page ??= 1;
        $filters = ['worker_id' => $workerId, 'type' => $type, 'status' => $status, 'start_date' => $startDate, 'end_date' => $endDate];
        $state = $workplaceId === null ? 'selection_required' : 'filter_required';
        $stateMessage = $workplaceId === null
            ? 'Selecciona una estación o departamento autorizado para consultar sus ajustes.'
            : 'Selecciona un rango de fechas laborales para consultar ajustes persistidos.';
        $items = [];
        $excludedUnverified = 0;
        $pagination = ['page' => $page, 'per_page' => ChecadorAdjustmentSqlReadService::DEFAULT_PAGE_SIZE, 'total_records' => 0, 'total_pages' => 0];
        $workers = [];
        $workerOptionsMessage = '';
        if ($workplaceId !== null && (($startDate === null && $endDate === null) || ($startDate !== null && $endDate !== null))) {
            $roster = self::journeyWorkerRosterOptions($workplaceId, $startDate, $endDate);
            $workers = array_values($roster['workers'] ?? []);
            $workerOptionsMessage = (string) ($roster['message'] ?? '');
        }
        if ($workplaceId !== null && ($workerInvalid || $pageInvalid)) {
            $state = 'invalid_filter';
            $stateMessage = 'Los filtros enviados no son válidos.';
        } elseif ($workplaceId !== null && (($startDate === null) !== ($endDate === null))) {
            $state = 'invalid_filter';
            $stateMessage = 'Indica ambas fechas para consultar el periodo laboral.';
        } elseif ($workplaceId !== null && $startDate !== null && $endDate !== null) {
            $actor = Auth::user();
            if ($actor === null) {
                self::renderAccessDenied('unauthenticated', $workplaceId);
                return;
            }
            $read = (new ChecadorAdjustmentSqlReadService())->listForWorkplace($actor, $workplaceId, $filters, $page);
            if (($read['ok'] ?? false) === true) {
                $state = 'ready';
                $stateMessage = '';
                $items = $read['data']['items'] ?? [];
                $pagination = $read['data']['pagination'] ?? $pagination;
                $excludedUnverified = (int) ($read['data']['excluded_unverified_count'] ?? 0);
            } else {
                $state = ($read['error'] ?? '') === 'permission_denied' ? 'denied' : 'error';
                $stateMessage = match ((string) ($read['error'] ?? 'sql_read_failed')) {
                    'permission_denied' => 'No tienes permiso efectivo para consultar ajustes en esta localidad.',
                    'invalid_filter', 'invalid_request' => 'Los filtros no son válidos. El rango máximo es de 31 fechas laborales.',
                    'page_out_of_range' => 'La página solicitada ya no está dentro de los resultados del filtro.',
                    'result_limit_exceeded' => 'El periodo contiene demasiados registros para una consulta segura. Reduce el rango de fechas.',
                    'schema_unavailable' => 'La estructura SQL requerida para consultar ajustes no está disponible.',
                    default => 'Ocurrió un error SQL al consultar los ajustes. No se interpreta como una lista vacía.',
                };
            }
        }

        $selectedWorkplaceName = trim((string) ($selectedWorkplace['nombre'] ?? ''));
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Checador', '/departamento-operativo/recursos-humanos/checador');
        Breadcrumb::add('Ajustes administrativos', '');
        View::render('departamento-operativo/2-recursos-humanos/checador/workspace-adjustments', [
            'title' => 'Ajustes administrativos', 'workspace' => 'adjustments',
            'moduleStationKey' => ChecadorAccessService::MODULE_KEY,
            'moduleStationForceSelector' => $isDirector,
            'moduleStationRequireExplicitSelection' => $isDirector && $workplaceId === null,
            'workplaces' => $workplaces, 'selectedWorkplaceId' => $workplaceId,
            'selectedWorkplaceName' => $selectedWorkplaceName, 'adjustmentState' => $state,
            'adjustmentStateMessage' => $stateMessage, 'adjustmentItems' => $items,
            'adjustmentFilters' => $filters, 'adjustmentPagination' => $pagination,
            'adjustmentWorkers' => $workers, 'adjustmentWorkerOptionsMessage' => $workerOptionsMessage,
            'adjustmentExcludedUnverified' => $excludedUnverified,
            'adjustmentTypes' => [
                'OVERRIDE_STATUS' => 'Ajuste de estado', 'INTERPRETED_ENTRY_TIME' => 'Hora de entrada interpretada',
                'INTERPRETED_EXIT_TIME' => 'Hora de salida interpretada', 'JUSTIFY_LATENESS' => 'Justificación de retardo',
            ],
            'adjustmentStatuses' => ['ACTIVE' => 'Activo', 'SUPERSEDED' => 'Sustituido', 'CANCELLED' => 'Cancelado'],
            'links' => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                '/assets/libs/select2/dist/css/select2.min.css', '/assets/css/select2-modal.css',
            ],
            'scripts' => [
                '/assets/js/vendor.min.js', '/assets/js/core/module-station-selector.js',
                '/assets/libs/select2/dist/js/select2.full.min.js', '/assets/libs/select2/dist/js/i18n/es.js',
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/departamento-operativo/2-recursos-humanos/checador.adjustments.init.js?v=20261120-1',
            ],
            'help' => false,
        ], 'departamento-operativo');
    }

    public function temporaryAccess(): void
    {
        $this->renderDashboardWorkspace('temporary-access');
    }

    /** Render a lightweight entry page; operational data is loaded only by its workspace route. */
    private function renderModuleDirectory(): void
    {
        $this->normalizeLegacyWorkplaceUrl();
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Checador', '');

        $access = ChecadorAccessService::authorize();
        if (empty($access['allowed'])) {
            self::renderAccessDenied((string) ($access['reason'] ?? 'authorization_lookup_failed'), null);
            return;
        }

        $workplaces = is_array($access['workplaces'] ?? null) ? $access['workplaces'] : [];
        $workplaceId = $this->sessionWorkplaceId($access);
        $isOperationsDirector = (int) ($access['position_id'] ?? 0) === 13;
        if ($workplaceId === null && !$isOperationsDirector && $workplaces !== []) {
            $fallbackWorkplaceId = (int) ($workplaces[0]['id'] ?? 0);
            if ($fallbackWorkplaceId > 0
                && ChecadorAccessService::authorize($fallbackWorkplaceId)['allowed']
                && ModuleStationService::setChecadorWorkplaceContext($fallbackWorkplaceId)) {
                $workplaceId = $fallbackWorkplaceId;
            }
        }

        $workplaceName = '';
        foreach ($workplaces as $workplace) {
            if ((int) ($workplace['id'] ?? 0) === (int) ($workplaceId ?? 0)) {
                $workplaceName = (string) ($workplace['nombre'] ?? '');
                break;
            }
        }
        $departmentIds = array_map(
            static fn(array $item): int => (int) ($item['id'] ?? 0),
            $access['department_workplaces'] ?? []
        );
        $workplaceKind = $workplaceId !== null && in_array($workplaceId, $departmentIds, true)
            ? 'Departamento'
            : 'Estación';
        $requiresExplicitWorkplaceSelection = $isOperationsDirector;
        $moduleNavigation = $requiresExplicitWorkplaceSelection && $workplaceId === null
            ? []
            : $this->buildModuleNavigation($workplaceId, $access);

        View::render(
            'departamento-operativo/2-recursos-humanos/checador/index',
            [
                'title' => 'Checador',
                'workplaces' => $workplaces,
                'selectedWorkplaceId' => $workplaceId,
                'selectedWorkplaceName' => $workplaceName,
                'selectedWorkplaceKind' => $workplaceKind,
                'moduleNavigation' => $moduleNavigation,
                'requiresExplicitWorkplaceSelection' => $requiresExplicitWorkplaceSelection,
                'isModuleDirectory' => true,
                'moduleStationKey' => ChecadorAccessService::MODULE_KEY,
                'links' => [],
                'scripts' => [
                    '/assets/js/core/module-station-selector.js',
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.module-station.init.js?v=20261116-1',
                ],
                'help' => false,
            ],
            'departamento-operativo'
        );
    }

    /** Build the original institutional cards using the current authorized routes and read gates. */
    private function buildModuleNavigation(?int $workplaceId, array $access): array
    {
        $base = '/departamento-operativo/recursos-humanos/checador';
        $departmentSelected = $workplaceId !== null
            && in_array(
                $workplaceId,
                array_map(
                    'intval',
                    array_column(
                        $access['department_workplaces'] ?? [],
                        'id'
                    )
                ),
                true
            );
        $scheduleRead = $workplaceId !== null
            && (ChecadorAccessService::authorizeScheduling($workplaceId, 'leer')['allowed'] ?? false) === true;
        $m04TemplateRead = $scheduleRead;
        $temporaryRead = $workplaceId !== null
            && in_array($workplaceId, array_map('intval', array_column($access['station_workplaces'] ?? [], 'id')), true)
            && (ChecadorAccessService::authorizeTemporaryAccess($workplaceId, 'leer')['allowed'] ?? false) === true;
        $currentUser = Auth::user();
        $isOperationsDirector = $currentUser instanceof \App\Models\Usuario
            && (int) $currentUser->id_puesto === 13;
        $comodinesRead = $currentUser instanceof \App\Models\Usuario
            && ChecadorV2PermissionService::allowsComodines(
                (int) $currentUser->id,
                (int) $currentUser->id_puesto,
                ChecadorV2PermissionService::COMODINES_READ
            );
        $payrollRead = $workplaceId !== null
            && (new ChecadorPayrollComparisonReadService())->authorization($workplaceId)['allowed'];

        $entries = [
            [
                'label' => 'Asistencia del día',
                'description' => 'Consulta las checadas, horarios y estado de asistencia del personal de tu estación.',
                'utility' => 'Personal · Marcaciones · Incidencias',
                'action' => 'Ver asistencia',
                'icon' => 'ti-calendar-check',
                'href' => $base . '/asistencia',
                'available' => true,
            ],
            [
                'label' => 'Rol de comodines',
                'description' => 'Consulta las programaciones registradas y su estado para la estación autorizada.',
                'utility' => 'Periodo · Estado · Estación · Consulta',
                'action' => 'Consultar rol',
                'icon' => 'ti-users-group',
                'href' => $base . '/rol-comodines',
                'available' => $comodinesRead,
                'unavailable' => 'Disponible para estaciones autorizadas en Checador.',
            ],
            [
                'label' => 'Programación semanal',
                'description' => 'Consulta y organiza los turnos del personal de lunes a domingo.',
                'utility' => 'Turnos · Descansos · Asignaciones',
                'action' => 'Ver programación',
                'icon' => 'ti-calendar-week',
                'href' => $base . '/programacion-semanal',
                'available' => $scheduleRead,
                'unavailable' => $departmentSelected
                    ? 'La programación semanal no está habilitada para este departamento.'
                    : 'Disponible con autorización de lectura de programación.',
            ],
            [
                'label' => 'Horarios y turnos',
                'description' => 'Revisa los horarios disponibles, las asignaciones vigentes y su historial.',
                'utility' => 'Horarios · Vigencias · Historial',
                'action' => 'Consultar horarios',
                'icon' => 'ti-clock-hour-4',
                'href' => $base . '/horarios-turnos',
                'available' => $m04TemplateRead,
                'unavailable' => 'Disponible con autorización de lectura de horarios.',
            ],
            [
                'label' => 'Jornadas laborales',
                'description' => 'Consulta jornadas evaluadas, tiempos registrados y situaciones que requieren revisión.',
                'utility' => 'Jornadas · Horas · Seguimiento',
                'action' => 'Consultar jornadas',
                'icon' => 'ti-clipboard-check',
                'href' => $base . '/jornadas',
                'available' => true,
            ],
            [
                'label' => 'Incidencias',
                'description' => 'Revisa las incidencias del personal, su estado y el seguimiento de cada caso.',
                'utility' => 'Registros · Estados · Historial',
                'action' => 'Ver incidencias',
                'icon' => 'ti-alert-circle',
                'href' => $base . '/incidencias',
                'available' => true,
            ],
            [
                'label' => 'Ajustes administrativos',
                'description' => 'Consulta los ajustes documentados y las referencias asociadas a las evaluaciones.',
                'utility' => 'Referencias · Motivos · Consulta',
                'action' => 'Consultar ajustes',
                'icon' => 'ti-adjustments',
                'href' => $base . '/ajustes',
                'available' => true,
            ],
            [
                'label' => 'PIN temporal',
                'description' => 'Consulta solicitudes de acceso temporal para trabajadores y su estado.',
                'utility' => 'Solicitudes · Accesos · Seguimiento',
                'action' => 'Ver solicitudes',
                'icon' => 'ti-key',
                'href' => $base . '/acceso-temporal',
                'available' => $temporaryRead,
                'unavailable' => 'No disponible para este centro o perfil.',
            ],
            [
                'label' => 'Reportes',
                'description' => 'Consulta marcaciones por estación, trabajador y periodo, con opción de exportación.',
                'utility' => 'Marcaciones · Filtros · CSV',
                'action' => 'Abrir reportes',
                'icon' => 'ti-file-report',
                'href' => $base . '/reporte',
                'available' => true,
            ],
            [
                'label' => 'Comparación con Nómina',
                'description' => 'Revisa información de asistencia y referencias de nómina por trabajador y periodo.',
                'utility' => 'Periodos · Referencias · Consulta',
                'action' => 'Ver comparación',
                'icon' => 'ti-scale',
                'href' => $base . '/comparacion-nomina',
                'available' => $payrollRead,
                'unavailable' => 'No disponible para este centro o perfil.',
            ],
            [
                'label' => 'Configuración',
                'description' => 'Accede a la configuración de horarios, reglas de asistencia, puestos y lectores.',
                'utility' => 'Horarios · Reglas · Personal · Lectores',
                'action' => 'Abrir configuración',
                'icon' => 'ti-settings',
                'href' => $base . '/configuracion',
                'available' => true,
            ],
        ];

        if ($isOperationsDirector) {
            $entries = array_values(array_filter(
                $entries,
                static fn(array $entry): bool => ($entry['label'] ?? '') !== 'PIN temporal'
            ));
        }
        if (!$comodinesRead) {
            $entries = array_values(array_filter(
                $entries,
                static fn(array $entry): bool => ($entry['label'] ?? '') !== 'Rol de comodines'
            ));
        }

        return $entries;
    }

    private function renderDashboardWorkspace(?string $workspace, bool $dedicatedScheduleHistory = false): void
    {
        $query = $workspace === 'daily-attendance' && Request::method() === 'POST'
            ? Request::json()
            : Request::query();
        $scheduleHistoryMode = $workspace === null && $dedicatedScheduleHistory;
        $scheduleWeekMode = $workspace === 'schedule-week';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        $workspaceTitles = [
            'daily-attendance' => 'Asistencia del día',
            'schedule-week' => 'Programación semanal',
            'journeys' => 'Jornadas laborales',
            'incidences' => 'Incidencias',
            'adjustments' => 'Ajustes administrativos',
            'temporary-access' => 'PIN temporal',
        ];
        Breadcrumb::add(
            'Checador',
            ($scheduleHistoryMode || $scheduleWeekMode || $workspace !== null)
                ? '/departamento-operativo/recursos-humanos/checador'
                : ''
        );
        if ($workspace !== null) {
            Breadcrumb::add($workspaceTitles[$workspace] ?? 'Checador', '');
        } elseif ($scheduleHistoryMode) {
            Breadcrumb::add('Vigencias de horarios', '');
        } elseif ($scheduleWeekMode) {
            Breadcrumb::add('Programación semanal', '');
        }

        $hasExplicitWorkplace = array_key_exists('workplace_id', $query);
        $requestedWorkplace = self::positiveInteger($query['workplace_id'] ?? null);
        $requestedWorkerValue = $query['worker_id'] ?? null;
        $requestedWorker = self::positiveInteger($requestedWorkerValue);
        $requestedWorkerInvalid = array_key_exists('worker_id', $query)
            && $requestedWorkerValue !== null
            && !(is_scalar($requestedWorkerValue) && trim((string) $requestedWorkerValue) === '')
            && $requestedWorker === null;
        $snapshotRefProvided = array_key_exists('snapshot_ref', $query);
        $snapshotRef = self::safeSnapshotRef($query['snapshot_ref'] ?? null);
        $access = ChecadorAccessService::authorize(null);
        if (!$access['allowed']) {
            self::renderAccessDenied((string) ($access['reason'] ?? 'authorization_lookup_failed'), $requestedWorkplace);
            return;
        }
        $workplaces = $access['workplaces'];
        $m04AutolavadoAdmin = $scheduleHistoryMode
            ? ChecadorAccessService::authorizeStationTemplateAdministration(ChecadorAccessService::AUTOLAVADO_LOCALITY_ID)
            : ['allowed' => false, 'workplaces' => []];
        if (($m04AutolavadoAdmin['allowed'] ?? false) === true) {
            foreach (($m04AutolavadoAdmin['workplaces'] ?? []) as $autolavado) {
                if ((int) ($autolavado['id'] ?? 0) === ChecadorAccessService::AUTOLAVADO_LOCALITY_ID
                    && !in_array(ChecadorAccessService::AUTOLAVADO_LOCALITY_ID, array_map('intval', array_column($workplaces, 'id')), true)) {
                    $workplaces[] = $autolavado;
                }
            }
        }
        $authorizedStationWorkplaceIds = array_values(array_map(
            static fn(array $item): int => (int) ($item['id'] ?? 0),
            is_array($access['station_workplaces'] ?? null) ? $access['station_workplaces'] : []
        ));
        $rawWorkplace = $query['workplace_id'] ?? null;
        if ($hasExplicitWorkplace && $rawWorkplace !== '' && $requestedWorkplace === null) {
            self::renderAccessDenied('invalid_station_selection', null);
            return;
        }
        $isDailyAttendanceGet = $workspace === 'daily-attendance' && Request::method() === 'GET';
        if ($isDailyAttendanceGet && $hasExplicitWorkplace) {
            if ($rawWorkplace === '' || $requestedWorkplace === null) {
                self::renderAccessDenied('invalid_station_selection', null);
                return;
            }

            $authorizedWorkplaceIds = array_map('intval', array_column($workplaces, 'id'));
            $legacyWorkplaceAccess = ChecadorAccessService::authorize($requestedWorkplace);
            if (!in_array($requestedWorkplace, $authorizedWorkplaceIds, true)
                || !($legacyWorkplaceAccess['allowed'] ?? false)) {
                self::renderAccessDenied(
                    (string) ($legacyWorkplaceAccess['reason'] ?? 'requested_station_out_of_scope'),
                    $requestedWorkplace
                );
                return;
            }

            // A legacy URL can seed a missing context, but it cannot replace a
            // newer authorized selection already saved by the station selector.
            $moduleContext = ModuleStationService::getContext(ChecadorAccessService::MODULE_KEY);
            $sessionWorkplace = self::positiveInteger($moduleContext['id_depto'] ?? $moduleContext['id_estacion'] ?? null);
            if ($sessionWorkplace !== null) {
                $sessionWorkplaceAccess = ChecadorAccessService::authorize($sessionWorkplace);
                if (in_array($sessionWorkplace, $authorizedWorkplaceIds, true)
                    && ($sessionWorkplaceAccess['allowed'] ?? false)) {
                    $requestedWorkplace = $sessionWorkplace;
                } else {
                    ModuleStationService::setContext(ChecadorAccessService::MODULE_KEY, null, null);
                }
            }
        }
        if ($requestedWorkplace !== null) {
            $isM04AutolavadoSelection = $scheduleHistoryMode
                && $requestedWorkplace === ChecadorAccessService::AUTOLAVADO_LOCALITY_ID;
            $selectedAccess = $isM04AutolavadoSelection
                ? ChecadorAccessService::authorizeShiftTemplateCatalogRead($requestedWorkplace)
                : ChecadorAccessService::authorize($requestedWorkplace);
            if (!$selectedAccess['allowed']) {
                self::renderAccessDenied((string) ($selectedAccess['reason'] ?? 'requested_station_out_of_scope'), $requestedWorkplace);
                return;
            }
            $contextSaved = ModuleStationService::setChecadorWorkplaceContext($requestedWorkplace)
                || ($isM04AutolavadoSelection
                    && ModuleStationService::setChecadorM04TemplateWorkplaceContext($requestedWorkplace));
            if (!$contextSaved) {
                self::renderAccessDenied('requested_station_out_of_scope', $requestedWorkplace);
                return;
            }
        } elseif ($hasExplicitWorkplace) {
            // An explicit empty value is a deliberate unselected state, not a
            // signal to restore the previous station from this module's session.
            ModuleStationService::setContext(ChecadorAccessService::MODULE_KEY, null, null);
        } else {
            $moduleContext = ModuleStationService::getContext(ChecadorAccessService::MODULE_KEY);
            $contextWorkplace = $scheduleHistoryMode
                ? (ModuleStationService::getChecadorM04TemplateWorkplaceContext()
                    ?? self::positiveInteger($moduleContext['id_depto'] ?? $moduleContext['id_estacion'] ?? null))
                : self::positiveInteger($moduleContext['id_depto'] ?? $moduleContext['id_estacion'] ?? null);
            if ($contextWorkplace !== null && in_array($contextWorkplace, array_column($workplaces, 'id'), true)) {
                $requestedWorkplace = $contextWorkplace;
            } elseif ($workplaces !== []) {
                // First load uses an actually authorized option from the
                // Checador scope; the normal authorization/context write below
                // still runs before any source is queried.
                $requestedWorkplace = (int) $workplaces[0]['id'];
            }
            if ($requestedWorkplace !== null) {
                $isM04AutolavadoSelection = $scheduleHistoryMode
                    && $requestedWorkplace === ChecadorAccessService::AUTOLAVADO_LOCALITY_ID;
                $selectedAccess = $isM04AutolavadoSelection
                    ? ChecadorAccessService::authorizeShiftTemplateCatalogRead($requestedWorkplace)
                    : ChecadorAccessService::authorize($requestedWorkplace);
                if (!$selectedAccess['allowed']) {
                    self::renderAccessDenied((string) ($selectedAccess['reason'] ?? 'requested_station_out_of_scope'), $requestedWorkplace);
                    return;
                }
                $contextSaved = ModuleStationService::setChecadorWorkplaceContext($requestedWorkplace)
                    || ($isM04AutolavadoSelection
                        && ModuleStationService::setChecadorM04TemplateWorkplaceContext($requestedWorkplace));
                if (!$contextSaved) {
                    self::renderAccessDenied('requested_station_out_of_scope', $requestedWorkplace);
                    return;
                }
            }
        }

        // M01 filters are POSTed as JSON. Any legacy GET context/filter query
        // is normalized only after its workplace has been reauthorized and the
        // selected canonical context has been persisted in this session.
        if ($isDailyAttendanceGet && $query !== []) {
            self::redirect('/departamento-operativo/recursos-humanos/checador/asistencia');
        }

        // Temporary Access has a separate read ACL and applies only to an
        // authorized physical station. Re-check it before any queue request.
        if ($workspace === 'temporary-access') {
            $temporaryAccessRead = ChecadorAccessService::authorizeTemporaryAccess($requestedWorkplace, 'leer');
            if (!($temporaryAccessRead['allowed'] ?? false)) {
                self::renderAccessDenied((string) ($temporaryAccessRead['reason'] ?? 'temporary_access_permission_denied'), $requestedWorkplace);
                return;
            }
        }
        // Synthetic fixtures are permitted only by the development-only gate.
        // Production and preproduction always use the operational data path.
        $advancedDemoMode = ChecadorDailyReadService::demoModeEnabled();
        $m01DemoMode = $workspace === 'daily-attendance' && $advancedDemoMode;
        $demoMode = $workspace === 'daily-attendance' ? $m01DemoMode : $advancedDemoMode;
        $realApiPostOperationsEnabled = ChecadorDailyReadService::realApiPostOperationsEnabled();
        $apiPostOperationsEnabled = $m01DemoMode
            ? false
            : ($demoMode || $realApiPostOperationsEnabled);
        $canViewTemporaryPin = $workspace === 'temporary-access'
            && !$demoMode
            && $realApiPostOperationsEnabled
            && (ChecadorAccessService::authorizeTemporaryAccess($requestedWorkplace, 'editar')['allowed'] ?? false) === true;
        $demoScenario = $demoMode ? ChecadorDailyReadService::configuredDemoScenario() : null;

        $date = isset($query['date']) && is_string($query['date']) ? trim($query['date']) : '';
        $defaultTimezoneResolution = null;
        if (($workspace === null || $workspace === 'daily-attendance' || $workspace === 'schedule-week')
            && !$scheduleHistoryMode && $requestedWorkplace !== null && $date === '') {
            if ($demoMode) {
                // The existing ready fixtures are contract-shaped for this
                // fixed reference date. The discrete Demo marker below makes
                // clear that this is not the calendar's real current day.
                $date = ChecadorDailyReadService::demoReferenceDate();
            } else {
                // M03 resolves the canonical timezone from Portal3's SQL-backed
                // versioned locality records. Other Checador reads retain their
                // current owner-specific path until their slice is migrated.
                $candidateDate = gmdate('Y-m-d');
                $date = $candidateDate;
                $defaultUser = Auth::user();
                if ($defaultUser) {
                    try {
                        if ($workspace === 'schedule-week') {
                            $defaultTimezone = (new \App\Services\ChecadorWeeklyScheduleSqlReadService())
                                ->timezoneForDate($requestedWorkplace, $candidateDate);
                            if ($defaultTimezone !== null) {
                                $date = ChecadorOperationalPeriod::currentLocalDate(
                                    $defaultTimezone,
                                    new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
                                ) ?? $candidateDate;
                            }
                        } elseif ($workspace === 'daily-attendance') {
                            $authorizedM01LocalityIds = array_values(array_filter(array_map(
                                static fn(array $item): int => (int) ($item['id'] ?? 0),
                                $workplaces
                            ), static fn(int $id): bool => $id > 0));
                            $defaultTimezoneResolution = (new ChecadorDailyAttendanceSqlReadService())
                                ->resolveCurrentLocalDay($requestedWorkplace, $authorizedM01LocalityIds);
                            if (($defaultTimezoneResolution['ok'] ?? false) === true) {
                                $date = (string) $defaultTimezoneResolution['data']['local_date'];
                            }
                        } else {
                            $defaultReader = new ChecadorDailyReadService();
                            $defaultTimezoneResolution = $defaultReader->resolveWorkplaceLocalDay(
                                $defaultUser, $requestedWorkplace, $candidateDate
                            );
                            if (($defaultTimezoneResolution['ok'] ?? false) === true) {
                                $localToday = ChecadorOperationalPeriod::currentLocalDate(
                                    (string) ($defaultTimezoneResolution['data']['timezone'] ?? ''),
                                    new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
                                );
                                if ($localToday === null) {
                                    $defaultTimezoneResolution = ['ok' => false, 'error' => ['kind' => 'timezone_unresolved', 'status' => 502]];
                                } else {
                                    $date = $localToday;
                                    if ($localToday !== $candidateDate) $defaultTimezoneResolution = null;
                                }
                            }
                        }
                    } catch (\Throwable) {
                        if ($workspace !== 'schedule-week') {
                            $defaultTimezoneResolution = ['ok' => false, 'error' => ['kind' => 'api_unavailable', 'status' => 0]];
                        }
                    }
                }
            }
        }
        $m01DefaultDateResolution = $workspace === 'daily-attendance' && !$demoMode
            ? $defaultTimezoneResolution
            : null;
        if ($scheduleHistoryMode) {
            $rawHistoryPage = self::positiveInteger($query['page'] ?? 1);
            $this->renderScheduleHistory(
                $requestedWorkplace,
                $workplaces,
                $requestedWorker,
                $date,
                $rawHistoryPage ?? 1,
                $rawHistoryPage === null,
                $demoMode,
                $demoScenario
            );
            return;
        }
        if ($scheduleWeekMode) {
            $weekStart = isset($query['week_start']) && is_string($query['week_start'])
                ? trim($query['week_start'])
                : (\App\Services\ChecadorWeeklyScheduleService::monday($date) ?? '');
            $this->renderScheduleWeek($requestedWorkplace, $workplaces, $weekStart, $demoMode, $demoScenario);
            return;
        }

        $page = self::positiveInteger($query['page'] ?? 1) ?? 1;
        $perPage = min(200, self::positiveInteger($query['per_page'] ?? 50) ?? 50);
        $timezone = null;
        $events = [];
        $demoWorkerFilterNoMatches = false;
        $pagination = null;
        $snapshotMeta = null;
        $level2Meta = null;
        $level2Loaded = false;
        $effectiveSchedules = [];
        $effectiveScheduleErrors = [];
        $observedOnlyEvents = [];
        $m01SqlReadSuccessful = false;
        $attendanceEvents = [];
        $attendanceCoverage = ['status' => 'not_available', 'complete' => false, 'message' => 'No se pudo confirmar la cobertura de marcaciones.'];
        $attendanceRoster = ['status' => 'not_available', 'coverage' => 'unknown', 'workers' => [], 'message' => 'Padrón no disponible.'];
        $attendanceRosterFallbackAllowed = false;
        $attendanceRows = [];
        $workerSelectOptions = [];
        $journeys = [];
        $journeyErrors = [];
        $administrativeIncidences = [];
        $administrativeErrors = [];
        $administrativeEvaluations = [];
        $administrativeEvaluationErrors = [];
        $lifecyclePermissions = [
            'resolve' => false,
            'authorize' => false,
            'reject' => false,
            'revoke' => false,
        ];
        $lifecycleKeys = [];
        $lifecycleFeedback = Session::pull('checador_lifecycle_feedback', null);
        $incidenceCreateFeedback = Session::pull('checador_incidence_create_feedback', null);
        if (!is_array($incidenceCreateFeedback)
            || (int) ($incidenceCreateFeedback['workplace_id'] ?? 0) !== (int) ($requestedWorkplace ?? 0)
            || (string) ($incidenceCreateFeedback['return_date'] ?? '') !== $date) {
            $incidenceCreateFeedback = null;
        }
        $incidenceCreateAllowed = false;
        $incidenceCreateKeys = [];
        $evaluationPermissions = [
            'evaluate' => false,
            'authorize_falta' => false,
        ];
        $evaluationKeys = [];
        $evaluationFeedback = Session::pull('checador_evaluation_feedback', null);
        $temporaryAccessFeedback = Session::pull('checador_temporary_access_feedback', null);
        $currentUser = Auth::user();
        if (!is_array($temporaryAccessFeedback)
            || (int) ($temporaryAccessFeedback['user_id'] ?? 0) !== (int) ($currentUser->id ?? 0)
            || (int) ($temporaryAccessFeedback['workplace_id'] ?? 0) !== (int) ($requestedWorkplace ?? 0)) {
            // One-time secret feedback is discarded if the user or station
            // context changed before the redirect was consumed.
            $temporaryAccessFeedback = null;
        }
        $temporaryAccessPanel = [
            'visible' => false,
            'can_edit' => false,
            'state' => 'unavailable',
            'requests' => [],
            'message' => '',
            'cancelable_uuid' => null,
        ];
        $state = 'idle';
        $stateMessage = '';
        $workspaceNeedsExistingSnapshot = in_array($workspace, ['journeys', 'incidences', 'adjustments'], true);
        $workspaceSnapshotMissing = $workspaceNeedsExistingSnapshot && $snapshotRef === null;
        if ($workspace === 'journeys' && $requestedWorkplace === null) {
            $state = 'idle';
            $stateMessage = 'Selecciona una localidad autorizada para revisar jornadas.';
        } elseif ($workspace === 'journeys') {
            $state = 'persisted_read_contract_unavailable';
            $stateMessage = 'La consulta independiente de jornadas persistidas está bloqueada: falta un contrato SQL autorizado para esta proyección. No se consulta API ni se interpreta la falta de resultados como una jornada vacía.';
        } elseif ($workspaceSnapshotMissing) {
            $state = 'snapshot_state_unavailable';
            $stateMessage = 'Carga primero la asistencia diaria para abrir esta vista con una consulta estable y su referencia autorizada.';
        }

        if ($workspace !== 'temporary-access' && $workspace !== 'journeys' && !$workspaceSnapshotMissing && $requestedWorkplace !== null && $date !== '') {
            $user = Auth::user();
            if (!$user) {
                $state = 'error';
                $stateMessage = 'La sesión ya no es válida. Inicia sesión nuevamente.';
            } else {
                $workerCatalog = null;
                try {
                    if ($workspace === 'daily-attendance' && $m01DemoMode) {
                        $demoReader = new ChecadorDailyReadService();
                        $demoAttendance = self::readM01SyntheticAttendance(
                            $demoReader,
                            $user,
                            $requestedWorkplace,
                            $date,
                            $requestedWorker,
                            $requestedWorkerInvalid
                        );
                        $state = $demoAttendance['state'];
                        $stateMessage = $demoAttendance['state_message'];
                        $timezone = $demoAttendance['timezone'];
                        $events = $demoAttendance['events'];
                        $attendanceEvents = $demoAttendance['attendance_events'];
                        $attendanceCoverage = $demoAttendance['attendance_coverage'];
                        $attendanceRoster = $demoAttendance['attendance_roster'];
                        $attendanceRosterFallbackAllowed = false;
                        $workerSelectOptions = $demoAttendance['worker_options'];
                        $requestedWorker = $demoAttendance['selected_worker_id'];
                        $scheduledComodines = $demoAttendance['scheduled_comodines'];
                        $attendanceRows = $demoAttendance['attendance_rows'];
                        $effectiveSchedules = $demoAttendance['effective_schedules'];
                        $effectiveScheduleErrors = $demoAttendance['effective_schedule_errors'];
                        $journeys = $demoAttendance['journeys'];
                        $journeyEvaluationState = $demoAttendance['journey_evaluation_state'];
                    } elseif ($workspace === 'daily-attendance' && !$demoMode) {
                        $workerCatalog = (new \App\Services\ChecadorAttendanceRosterService())
                            ->workersForAttendanceFilter($requestedWorkplace, $date, null, false);
                        $authorizedM01LocalityIds = array_values(array_filter(array_map(
                            static fn(array $item): int => (int) ($item['id'] ?? 0),
                            $workplaces
                        ), static fn(int $id): bool => $id > 0));
                        $rawAttendance = self::readDailySqlAttendance(
                            $requestedWorkplace,
                            $date,
                            $requestedWorker,
                            $requestedWorkerInvalid,
                            $authorizedM01LocalityIds,
                            $workerCatalog,
                            $m01DefaultDateResolution
                        );
                        $state = $rawAttendance['state'];
                        $stateMessage = $rawAttendance['state_message'];
                        $m01SqlReadSuccessful = ($rawAttendance['sql_read_success'] ?? false) === true;
                        $timezone = $rawAttendance['timezone'];
                        $events = $rawAttendance['events'];
                        $attendanceEvents = $rawAttendance['attendance_events'];
                        $attendanceCoverage = $rawAttendance['attendance_coverage'];
                        $attendanceRoster = $rawAttendance['attendance_roster'];
                        $attendanceRosterFallbackAllowed = $rawAttendance['roster_fallback_allowed'];
                        $workerSelectOptions = $rawAttendance['worker_options'];
                        $requestedWorker = $rawAttendance['selected_worker_id'];
                        $scheduledComodines = $rawAttendance['scheduled_comodines'];
                        $attendanceRows = $rawAttendance['attendance_rows'];
                        $effectiveSchedules = $rawAttendance['effective_schedules'];
                        $effectiveScheduleErrors = $rawAttendance['effective_schedule_errors'];
                        $observedOnlyEvents = $rawAttendance['observed_only_events'];
                    } else {
                    $reader = new ChecadorDailyReadService();
                    $snapshotState = null;

                    if ($snapshotRefProvided && $snapshotRef === null) {
                        $state = 'snapshot_state_unavailable';
                        $stateMessage = 'La consulta estable ya no está disponible. Vuelve a consultar para obtener una nueva lectura.';
                    } elseif ($snapshotRef !== null) {
                        $snapshotState = self::loadSnapshotState($snapshotRef, (int) $user->id, $requestedWorkplace, $date);
                        if ($snapshotState === null) {
                            $state = 'snapshot_state_unavailable';
                            $stateMessage = 'La consulta estable ya no está disponible. Vuelve a consultar para obtener una nueva lectura.';
                        } elseif (($snapshotState['expired'] ?? false) === true) {
                            $state = 'snapshot_expired';
                            $stateMessage = 'La consulta estable expiró. Vuelve a consultar para obtener una nueva lectura.';
                        }
                    } else {
                        // Snapshot creation is an explicit API write and does not
                        // belong to rendering a workspace. A previously issued
                        // reference remains readable through the existing contract.
                        $state = 'snapshot_state_unavailable';
                        $stateMessage = 'La consulta estable no está disponible. Vuelve a consultar mediante la operación explícita autorizada.';
                    }

                    if ($snapshotState !== null && !isset($snapshotState['expired']) && $state === 'idle') {
                        $timezone = $snapshotState['timezone'];
                        $snapshotMeta = $snapshotState['snapshot'];
                        if (!$level2Loaded) {
                            $level2Result = $reader->getLevel2WindowStatus($user, $requestedWorkplace, $timezone);
                            if (!$level2Result['ok']) {
                                [$state, $stateMessage] = self::stateForLevel2Error($level2Result['error']['kind']);
                            } else {
                                $level2Meta = $level2Result['data'];
                                $level2Loaded = true;
                            }
                        }
                        if ($state !== 'idle') {
                            // Fail closed: do not reuse a prior completeness badge.
                        } else {
                            $cursor = $snapshotState['cursors'][$page] ?? ($page === 1 ? null : '__missing__');
                            if ($cursor === '__missing__') {
                                $state = 'snapshot_state_unavailable';
                                $stateMessage = 'La página solicitada no pertenece a la consulta estable actual.';
                            } else {
                                $readResult = $reader->readDailyPunchSnapshotPage(
                                    $user,
                                    (string) $snapshotMeta['snapshot_token'],
                                    $cursor,
                                    $perPage,
                                    $demoMode ? $requestedWorker : null
                                );
                                if (!$readResult['ok']) {
                                    [$state, $stateMessage] = self::stateForError($readResult['error']['kind']);
                                    if (in_array($readResult['error']['kind'], ['snapshot_expired', 'snapshot_invalid', 'snapshot_authorization'], true)) $snapshotMeta = null;
                                } else {
                                    $state = 'success';
                                    $events = $readResult['events'];
                                    if ($demoMode && $requestedWorker !== null) {
                                        $events = array_values(array_filter(
                                            $events,
                                            static fn(array $event): bool => self::positiveInteger($event['id_personal_resuelto'] ?? null) === $requestedWorker
                                        ));
                                        $matchedRecords = self::nonNegativeInteger($readResult['pagination']['matched_records'] ?? null);
                                        $demoWorkerFilterNoMatches = $matchedRecords === 0;
                                    }
                                    $canonicalWorkerIds = [];
                                    foreach ($events as $event) {
                                        $workerId = self::positiveInteger($event['id_personal_resuelto'] ?? null);
                                        if ($workerId !== null) {
                                            $canonicalWorkerIds[$workerId] = true;
                                        }
                                    }
                                    foreach (array_keys($canonicalWorkerIds) as $workerId) {
                                        $scheduleResult = $reader->getEffectiveSchedule(
                                            $user,
                                            (int) $workerId,
                                            $requestedWorkplace,
                                            $date
                                        );
                                        if ($scheduleResult['ok']) {
                                            $effectiveSchedules[(int) $workerId] = $scheduleResult['data'];
                                        } else {
                                            $effectiveScheduleErrors[(int) $workerId] = $scheduleResult['error']['kind'];
                                        }
                                    }
                                    $attendanceRoster = (new \App\Services\ChecadorAttendanceRosterService())->currentRoster(
                                        $requestedWorkplace,
                                        $date,
                                        (string) ($timezone['timezone'] ?? ''),
                                        $demoMode
                                    );
                                    if ($demoMode && $requestedWorker !== null
                                        && in_array($requestedWorker, array_map(
                                            static fn(array $worker): int => (int) ($worker['worker_id'] ?? 0),
                                            $attendanceRoster['workers'] ?? []
                                        ), true)) {
                                        // A demo filter may only select the fixture worker explicitly returned
                                        // by the isolated roster; arbitrary browser-supplied identities stay out.
                                        $canonicalWorkerIds[$requestedWorker] = true;
                                    }
                                    // A browser-supplied worker filter is not itself proof of identity scope.
                                    // For a current-day worker without a PunchEvent, only the locally resolved
                                    // canonical roster may enable read-only incidence/evaluation lookups. Historical
                                    // workers are admitted only when the authorized snapshot actually observed them.
                                    $administrativeReadWorkerIds = $canonicalWorkerIds;
                                    if (!$demoMode && $requestedWorker !== null
                                        && in_array($requestedWorker, array_map(
                                            static fn(array $worker): int => (int) ($worker['worker_id'] ?? 0),
                                            $attendanceRoster['workers'] ?? []
                                        ), true)) {
                                        $administrativeReadWorkerIds[$requestedWorker] = true;
                                    }
                                    // Read the stable snapshot independently of the on-screen page so the daily
                                    // worker summary is not silently limited to the current 50-row page.
                                    $attendanceSet = self::readAttendanceSnapshotSet(
                                        $reader,
                                        $user,
                                        (string) ($snapshotMeta['snapshot_token'] ?? ''),
                                        $demoMode ? $requestedWorker : null
                                    );
                                    $attendanceEvents = $attendanceSet['events'];
                                    $attendanceCoverage = $attendanceSet['coverage'];
                                    $rosterIds = array_map(static fn(array $worker): int => (int) $worker['worker_id'], $attendanceRoster['workers']);
                                    foreach (array_slice($rosterIds, 0, 50) as $rosterWorkerId) {
                                        if (isset($effectiveSchedules[$rosterWorkerId]) || isset($effectiveScheduleErrors[$rosterWorkerId])) continue;
                                        $scheduleResult = $reader->getEffectiveSchedule($user, $rosterWorkerId, $requestedWorkplace, $date);
                                        if ($scheduleResult['ok']) $effectiveSchedules[$rosterWorkerId] = $scheduleResult['data'];
                                        else $effectiveScheduleErrors[$rosterWorkerId] = $scheduleResult['error']['kind'];
                                    }
                                    foreach (array_keys($canonicalWorkerIds) as $workerId) {
                                        $journeyResult = $reader->evaluateJourney(
                                            $user,
                                            (int) $workerId,
                                            $requestedWorkplace,
                                            $date,
                                            (string) ($snapshotMeta['snapshot_audit_ref'] ?? '')
                                        );
                                        if ($journeyResult['ok']) {
                                            $journeys[(int) $workerId] = $journeyResult['data'];
                                        } else {
                                            $journeyErrors[(int) $workerId] = $journeyResult['error']['kind'];
                                        }
                                    }
                                    foreach (array_keys($administrativeReadWorkerIds) as $workerId) {
                                        // P3-G reads go through the existing authenticated read boundary in both
                                        // runtimes. Demo mode only changes the service response source; it does
                                        // not bypass the Portal3 page/station authorization above.
                                        $incidenceResult = $reader->readApplicableIncidences(
                                            $user,
                                            (int) $workerId,
                                            $requestedWorkplace,
                                            $date,
                                            $date
                                        );
                                        if (!$incidenceResult['ok']) {
                                            $administrativeErrors[(int) $workerId] = $incidenceResult['error']['kind'];
                                        } else {
                                            $details = [];
                                            $historyFailed = false;
                                            foreach ($incidenceResult['data']['incidences'] as $summary) {
                                                $detailResult = $reader->readIncidence($user, (string) $summary['uuid_incidencia']);
                                                if (!$detailResult['ok']) {
                                                    $historyFailed = true;
                                                    break;
                                                }
                                                $details[] = array_merge(
                                                    $detailResult['data'],
                                                    [
                                                        'source_classification' => $summary['source_classification'] ?? 'API_V2',
                                                        'summary_reason' => $summary['reason'] ?? null,
                                                        'summary_evidence_reference' => $summary['evidence_reference'] ?? null,
                                                        'summary_created_at_utc' => $summary['created_at_utc'] ?? null,
                                                    ]
                                                );
                                            }
                                            if ($historyFailed) {
                                                $administrativeErrors[(int) $workerId] = 'administrative_unavailable';
                                            } else {
                                                $administrativeIncidences[(int) $workerId] = $details;
                                            }
                                        }

                                        $evaluationResult = $reader->readAdministrativeEvaluation(
                                            $user,
                                            (int) $workerId,
                                            $requestedWorkplace,
                                            $date
                                        );
                                        if ($evaluationResult['ok']) {
                                            $administrativeEvaluations[(int) $workerId] = $evaluationResult['data'];
                                        } else {
                                            $administrativeEvaluationErrors[(int) $workerId] = $evaluationResult['error']['kind'];
                                        }
                                    }
                                    $pagination = [
                                        'current_page' => $page,
                                        'has_more' => $readResult['pagination']['has_more'],
                                        'next_cursor' => $readResult['pagination']['next_cursor'],
                                        'page_size' => $readResult['pagination']['page_size'],
                                        'matched_records' => $readResult['pagination']['matched_records'] ?? null,
                                    ];
                                    $snapshotState['cursors'][$page + 1] = $readResult['pagination']['next_cursor'];
                                    self::saveSnapshotState($snapshotRef, $snapshotState);
                                    $profiles = [];
                                    foreach ($attendanceRoster['workers'] as $profile) $profiles[(int) $profile['worker_id']] = $profile;
                                    $observedIds = [];
                                    $eventsByWorker = [];
                                    foreach ($attendanceEvents as $attendanceEvent) {
                                        $workerId = self::positiveInteger($attendanceEvent['id_personal_resuelto'] ?? null);
                                        if ($workerId === null) continue;
                                        $observedIds[$workerId] = true;
                                        $eventsByWorker[$workerId][] = $attendanceEvent;
                                    }
                                    $profiles += (new \App\Services\ChecadorAttendanceRosterService())->observedProfiles(
                                        array_map('intval', array_keys($observedIds)),
                                        $demoMode
                                    );
                                    foreach (array_keys($observedIds) as $workerId) {
                                        $profiles[$workerId] ??= ['worker_id' => (int) $workerId, 'full_name' => 'Trabajador ' . (int) $workerId, 'employee_number' => null];
                                    }
                                    $workerSelectOptions = array_values(array_filter(
                                        $profiles,
                                        static function (array $profile): bool {
                                            $name = trim((string) ($profile['full_name'] ?? ''));
                                            return $name !== '' && preg_match('/^Trabajador\s+\d+$/u', $name) !== 1;
                                        }
                                    ));
                                    usort($workerSelectOptions, static function (array $left, array $right): int {
                                        $byName = strnatcasecmp(
                                            trim((string) ($left['full_name'] ?? '')),
                                            trim((string) ($right['full_name'] ?? ''))
                                        );
                                        return $byName !== 0
                                            ? $byName
                                            : ((int) ($left['worker_id'] ?? 0) <=> (int) ($right['worker_id'] ?? 0));
                                    });
                                    if ($requestedWorker !== null) {
                                        $profiles = array_intersect_key($profiles, [$requestedWorker => true]);
                                    }
                                    foreach ($profiles as $workerId => $profile) {
                                        $attendanceRows[(int) $workerId] = \App\Services\ChecadorAttendanceProjection::workerRow(
                                            $profile,
                                            $eventsByWorker[(int) $workerId] ?? [],
                                            $journeys[(int) $workerId] ?? null,
                                            (string) ($timezone['timezone'] ?? '')
                                        );
                                        $attendanceRows[(int) $workerId]['schedule'] = $effectiveSchedules[(int) $workerId] ?? null;
                                        $attendanceRows[(int) $workerId]['schedule_error'] = $effectiveScheduleErrors[(int) $workerId]
                                            ?? (isset($effectiveSchedules[(int) $workerId]) ? null : 'schedule_not_loaded');
                                        $calculation = $journeys[(int) $workerId]['calculation'] ?? null;
                                        $workedSeconds = is_array($calculation)
                                            && ($calculation['estado_calculo'] ?? null) === 'CALCULATED'
                                            && is_int($calculation['segundos_trabajados_totales'] ?? null)
                                            && $calculation['segundos_trabajados_totales'] >= 0
                                            ? $calculation['segundos_trabajados_totales']
                                            : null;
                                        $attendanceRows[(int) $workerId]['worked_seconds'] = $workedSeconds;
                                        $attendanceRows[(int) $workerId]['worked_time_source'] = $workedSeconds === null ? null : 'API_V2_P2';
                                        $attendanceRows[(int) $workerId]['incidence_count'] = isset($administrativeIncidences[(int) $workerId])
                                            ? count($administrativeIncidences[(int) $workerId]) : null;
                                    }
                                }
                            }
                        }
                    }
                    }
                } catch (\Throwable $exception) {
                    try {
                        Logger::error('Checador dashboard workspace read failed', [
                            'workspace' => $workspace ?? 'directory',
                            'workplace_id' => $requestedWorkplace,
                            'exception_class' => $exception::class,
                            'exception_code' => substr((string) $exception->getCode(), 0, 32),
                            'exception_file' => basename($exception->getFile()),
                            'exception_line' => $exception->getLine(),
                        ]);
                    } catch (\Throwable) {
                        // Diagnostics must never prevent the safe read-only fallback.
                    }

                    $state = 'error';
                    $stateMessage = 'La consulta no está disponible en este momento.';
                    if ($workspace === 'daily-attendance'
                        && !$demoMode
                        && $workerCatalog !== null
                        && $requestedWorkplace !== null) {
                        try {
                            $authorizedM01LocalityIds = array_values(array_filter(array_map(
                                static fn(array $item): int => (int) ($item['id'] ?? 0),
                                $workplaces
                            ), static fn(int $id): bool => $id > 0));
                            $referenceRead = self::readDailySqlAttendance(
                                $requestedWorkplace,
                                $date,
                                $requestedWorker,
                                $requestedWorkerInvalid,
                                $authorizedM01LocalityIds,
                                $workerCatalog,
                                $m01DefaultDateResolution
                            );
                            if (($referenceRead['roster_fallback_allowed'] ?? false) === true) {
                                $attendanceRoster = $referenceRead['attendance_roster'];
                                $attendanceRosterFallbackAllowed = true;
                                $attendanceRows = $referenceRead['attendance_rows'];
                                $workerSelectOptions = $referenceRead['worker_options'];
                                $observedOnlyEvents = $referenceRead['observed_only_events'];
                                $requestedWorker = $referenceRead['selected_worker_id'];
                                $state = 'unavailable';
                                $stateMessage = 'Marcaciones no disponibles. Se muestra el padrón vigente autorizado como referencia; no confirma asistencia, horario ni pertenencia histórica para la fecha.';
                            }
                        } catch (\Throwable $fallbackException) {
                            try {
                                Logger::error('Checador attendance roster fallback failed', [
                                    'workplace_id' => $requestedWorkplace,
                                    'exception_class' => $fallbackException::class,
                                    'exception_code' => substr((string) $fallbackException->getCode(), 0, 32),
                                    'exception_file' => basename($fallbackException->getFile()),
                                    'exception_line' => $fallbackException->getLine(),
                                ]);
                            } catch (\Throwable) {
                                // Keep the original workspace failure as the response.
                            }
                        }
                    }
                }
            }
        } elseif ($workspace !== 'temporary-access' && !$workspaceSnapshotMissing && ($requestedWorkplace !== null || $date !== '')) {
            $state = 'error';
            $stateMessage = 'Selecciona un centro de trabajo y una fecha válida.';
        }

        $paginationLinks = self::paginationLinks($requestedWorkplace, $date, $pagination, $snapshotRef, $page, $requestedWorker);

        foreach (array_keys($lifecyclePermissions) as $action) {
            $lifecyclePermissions[$action] = $apiPostOperationsEnabled
                && (ChecadorAccessService::authorizeLifecycle(null, $action)['allowed'] ?? false);
        }
        $evaluationPermissions['evaluate'] = $apiPostOperationsEnabled
            && (ChecadorAccessService::authorizeEvaluation(null)['allowed'] ?? false);
        $evaluationPermissions['authorize_falta'] = $apiPostOperationsEnabled
            && (ChecadorAccessService::authorizeFalta(null)['allowed'] ?? false);
        foreach (array_keys($journeys) as $workerId) {
            $evaluationKeys[(int) $workerId] = [
                'evaluate' => self::newIdempotencyKey(),
                'authorize_falta' => self::newIdempotencyKey(),
                'expected_calculation_revision_id' => self::positiveInteger(
                    $journeys[$workerId]['calculation']['calculation_id'] ?? null
                ),
            ];
            if (is_array($evaluationFeedback)
                && (int) ($evaluationFeedback['worker_id'] ?? 0) === (int) $workerId
                && (string) ($evaluationFeedback['date'] ?? '') === $date
                && (int) ($evaluationFeedback['workplace_id'] ?? 0) === (int) $requestedWorkplace
                && in_array((string) ($evaluationFeedback['action'] ?? ''), ['evaluate', 'authorize_falta'], true)
                && trim((string) ($evaluationFeedback['retry_idempotency_key'] ?? '')) !== '') {
                $evaluationKeys[(int) $workerId][(string) $evaluationFeedback['action']] = (string) $evaluationFeedback['retry_idempotency_key'];
            }
        }
        if (!$demoMode && $realApiPostOperationsEnabled && $requestedWorkplace !== null) {
            $incidenceCreateAllowed = ChecadorAccessService::authorizeIncidenceCreate($requestedWorkplace)['allowed'] ?? false;
        }
        $administrativeWorkerIds = array_unique(array_merge(
            array_map('intval', array_keys($administrativeIncidences)),
            array_map('intval', array_keys($administrativeErrors)),
            array_map('intval', array_keys($administrativeEvaluations)),
            array_map('intval', array_keys($administrativeEvaluationErrors)),
            array_map('intval', array_keys($journeys))
        ));
        foreach ($administrativeWorkerIds as $workerId) {
            $retryKey = is_array($incidenceCreateFeedback)
                && (int) ($incidenceCreateFeedback['worker_id'] ?? 0) === (int) $workerId
                ? (string) ($incidenceCreateFeedback['retry_idempotency_key'] ?? '')
                : '';
            $incidenceCreateKeys[(int) $workerId] = $retryKey !== '' ? $retryKey : self::newIdempotencyKey();
        }
        foreach ($administrativeIncidences as $workerIncidences) {
            foreach ($workerIncidences as $incidence) {
                $uuid = (string) ($incidence['uuid_incidencia'] ?? '');
                if ($uuid === '') continue;
                foreach (array_keys($lifecyclePermissions) as $action) {
                    $retryKey = is_array($lifecycleFeedback)
                        && ($lifecycleFeedback['uuid'] ?? '') === $uuid
                        && ($lifecycleFeedback['action'] ?? '') === $action
                        ? (string) ($lifecycleFeedback['retry_idempotency_key'] ?? '')
                        : '';
                    $lifecycleKeys[$uuid][$action] = $retryKey !== '' ? $retryKey : self::newIdempotencyKey();
                }
            }
        }

        // Temporary Access is a separate capability and only applies to an
        // explicitly authorized physical station, never a department alias.
        if ($requestedWorkplace !== null) {
                $temporaryReadAccess = ChecadorAccessService::authorizeTemporaryAccess($requestedWorkplace, 'leer');
                if (!empty($temporaryReadAccess['allowed'])) {
                    $temporaryAccessPanel['visible'] = true;
                    $temporaryAccessPanel['can_edit'] = !$demoMode
                        && $realApiPostOperationsEnabled
                        && (int) ($temporaryReadAccess['permissions']['editar'] ?? 0) === 1;
                if ($demoMode) {
                    $temporaryAccessPanel['state'] = 'demo_blocked';
                    $temporaryAccessPanel['message'] = 'El acceso temporal no está simulado. No se consultó la API ni se ejecutaron acciones.';
                } elseif (!$currentUser) {
                    $temporaryAccessPanel['state'] = 'error';
                    $temporaryAccessPanel['message'] = 'La sesión ya no es válida. Inicia sesión nuevamente.';
                } else {
                    try {
                        $temporaryReader = new ChecadorDailyReadService();
                        $queueResult = $temporaryReader->listTemporaryAccessRequests($currentUser);
                    } catch (\Throwable) {
                        $queueResult = ['ok' => false, 'error' => ['kind' => 'api_unavailable', 'status' => 503]];
                    }
                    if (($queueResult['ok'] ?? false) === true) {
                        $temporaryAccessPanel['state'] = 'success';
                        $temporaryAccessPanel['requests'] = array_values(array_filter(
                            $queueResult['requests'] ?? [],
                            static fn(array $request): bool => ChecadorAccessService::temporaryAccessRequestBelongsToLocality(
                                self::positiveInteger($request['id_estacion'] ?? null),
                                $requestedWorkplace
                            )
                        ));
                    } else {
                        $temporaryAccessPanel['state'] = 'error';
                        $temporaryAccessPanel['message'] = match ((string) ($queueResult['error']['kind'] ?? 'api_unavailable')) {
                            'api_authentication' => 'La autenticación de acceso temporal no está disponible.',
                            'api_authorization' => 'La API no autoriza la consulta de solicitudes en este centro.',
                            default => 'Las solicitudes de acceso temporal no están disponibles; no se confirma que la cola esté vacía.',
                        };
                    }
                }

                $cancelable = Session::get('checador_temporary_access_cancelable');
                if (is_array($cancelable)
                    && (int) ($cancelable['user_id'] ?? 0) === (int) ($currentUser->id ?? 0)
                    && (int) ($cancelable['workplace_id'] ?? 0) === $requestedWorkplace
                    && is_string($cancelable['request_uuid'] ?? null)
                    && (int) ($cancelable['expires_at'] ?? 0) > time()
                    && $temporaryAccessPanel['can_edit']) {
                    $temporaryAccessPanel['cancelable_uuid'] = strtolower($cancelable['request_uuid']);
                } elseif (is_array($cancelable) && (int) ($cancelable['expires_at'] ?? 0) <= time()) {
                    Session::remove('checador_temporary_access_cancelable');
                }
            }
        }

        $payrollComparisonAvailable = !$demoMode
            && (new ChecadorPayrollComparisonReadService())->authorization($requestedWorkplace)['allowed'];
        // The weekly route has a separate read capability from schedule
        // editing. Resolve it here so the dashboard never advertises a link
        // that the current user's existing Biométricos ACL will reject.
        $canReadScheduling = $requestedWorkplace !== null
            && (ChecadorAccessService::authorizeScheduling($requestedWorkplace, 'leer')['allowed'] ?? false) === true;
        $departmentLocalityIds = array_map(
            static fn(array $item): int => (int) ($item['id'] ?? 0),
            $access['department_workplaces'] ?? []
        );
        $selectedWorkplaceKind = $requestedWorkplace !== null && in_array($requestedWorkplace, $departmentLocalityIds, true)
            ? 'Departamento'
            : 'Estación';
        $positionId = (int) ($currentUser->id_puesto ?? 0);
        $authorizedProfileLabel = match ($positionId) {
            13 => 'Dirección de Operaciones',
            6 => 'Encargado de estación',
            default => 'Perfil autorizado',
        };
        $selectedWorkplaceName = '';
        foreach ($workplaces as $workplaceOption) {
            if ((int) ($workplaceOption['id'] ?? 0) === (int) ($requestedWorkplace ?? 0)) {
                $selectedWorkplaceName = (string) ($workplaceOption['nombre'] ?? '');
                break;
            }
        }
        $workspaceWorkerMap = static function (array $items) use ($workspace, $requestedWorker): array {
            if ($workspace === null || $requestedWorker === null) {
                return $items;
            }
            return array_intersect_key($items, [$requestedWorker => true]);
        };
        $workspaceTemporaryAccessPanel = $temporaryAccessPanel;
        if ($workspace === 'temporary-access' && $requestedWorker !== null) {
            $workspaceTemporaryAccessPanel['requests'] = array_values(array_filter(
                $workspaceTemporaryAccessPanel['requests'] ?? [],
                static fn(array $request): bool => (int) ($request['id_personal_resuelto'] ?? 0) === $requestedWorker
            ));
        }

        $viewByWorkspace = [
            'daily-attendance' => 'daily-attendance',
            'journeys' => 'workspace-journeys',
            'incidences' => 'workspace-incidences',
            'adjustments' => 'workspace-adjustments',
            'temporary-access' => 'workspace-temporary-access',
        ];
        $view = $workspace === null
            ? ($scheduleHistoryMode ? 'schedule-history' : ($scheduleWeekMode ? 'schedule-week' : 'daily-attendance'))
            : ($viewByWorkspace[$workspace] ?? 'index');
        $workspaceContext = [];
        foreach ([
            'workplace_id' => $requestedWorkplace,
            'date' => $date,
            'worker_id' => $requestedWorker,
            'snapshot_ref' => $snapshotRef,
        ] as $key => $value) {
            if ($value !== null && $value !== '') {
                $workspaceContext[$key] = $value;
            }
        }
        View::render(
            'departamento-operativo/2-recursos-humanos/checador/' . $view,
            [
                'title' => $scheduleHistoryMode ? 'Vigencias de horarios' : ($workspace === null ? 'Checador' : ($workspaceTitles[$workspace] ?? 'Checador')),
                'workspace' => $workspace,
                'workspaceContext' => $workspaceContext,
                'dashboardHref' => '/departamento-operativo/recursos-humanos/checador'
                    . ($workspaceContext === [] ? '' : '?' . http_build_query($workspaceContext, '', '&', PHP_QUERY_RFC3986)),
                'selectedWorkplaceName' => $selectedWorkplaceName,
                'demoMode' => $demoMode,
                'realApiPostOperationsEnabled' => $realApiPostOperationsEnabled,
                'demoScenario' => $demoScenario,
                'workplaces' => $workplaces,
                'selectedWorkplaceId' => $requestedWorkplace,
                'selectedDate' => $date,
                'selectedWorkplaceKind' => $selectedWorkplaceKind,
                'authorizedProfileLabel' => $authorizedProfileLabel,
                'canReadScheduling' => $canReadScheduling,
                'defaultReportPeriod' => ((int) ($currentUser->id_puesto ?? 0) === 13) ? 'month' : 'today',
                'defaultReportYear' => (int) gmdate('Y'),
                'defaultReportMonth' => (int) gmdate('n'),
                'selectedWorkerId' => $requestedWorker,
                'workerSelectOptions' => $workerSelectOptions,
                'snapshotRef' => $snapshotRef,
                'demoWorkerFilterNoMatches' => $demoWorkerFilterNoMatches,
                'timezone' => $timezone,
                'events' => $events,
                'attendanceRows' => array_values($attendanceRows),
                'observedOnlyEvents' => $observedOnlyEvents,
                'm01SqlReadSuccessful' => $m01SqlReadSuccessful,
                'attendanceCoverage' => $attendanceCoverage,
                'attendanceRoster' => $attendanceRoster,
                'attendanceRosterFallbackAllowed' => $attendanceRosterFallbackAllowed,
                'pagination' => $pagination,
                'paginationLinks' => $paginationLinks,
                'snapshotMeta' => $snapshotMeta === null ? null : [
                    'status' => (string) ($snapshotMeta['status'] ?? 'READY'),
                    'total_records' => (int) ($snapshotMeta['total_records'] ?? 0),
                    'completeness_status' => (string) ($snapshotMeta['completeness_status'] ?? 'LEVEL_1_COMPLETE_ONLY'),
                ],
                'level2Meta' => $level2Meta === null ? null : [
                    'window_level2_status' => (string) ($level2Meta['window_level2_status'] ?? 'UNKNOWN'),
                    'delivery_completeness' => (string) ($level2Meta['delivery_completeness'] ?? 'UNKNOWN'),
                    'operational_coverage' => (string) ($level2Meta['operational_coverage'] ?? 'UNKNOWN'),
                    'time_window_attribution' => (string) ($level2Meta['time_window_attribution'] ?? 'UNKNOWN'),
                    'has_zero_governed_events' => (bool) ($level2Meta['has_zero_governed_events'] ?? false),
                    'governed_event_count' => (int) ($level2Meta['governed_event_count'] ?? 0),
                ],
                'effectiveSchedules' => $effectiveSchedules,
                'effectiveScheduleErrors' => $effectiveScheduleErrors,
                'journeys' => $workspaceWorkerMap($journeys),
                'journeyErrors' => $workspaceWorkerMap($journeyErrors),
                'administrativeIncidences' => $workspaceWorkerMap($administrativeIncidences),
                'administrativeErrors' => $workspaceWorkerMap($administrativeErrors),
                'administrativeEvaluations' => $workspaceWorkerMap($administrativeEvaluations),
                'administrativeEvaluationErrors' => $workspaceWorkerMap($administrativeEvaluationErrors),
                'lifecyclePermissions' => $lifecyclePermissions,
                'lifecycleKeys' => $lifecycleKeys,
                'lifecycleFeedback' => $lifecycleFeedback,
                'incidenceCreateAllowed' => $incidenceCreateAllowed,
                'incidenceCreateKeys' => $incidenceCreateKeys,
                'incidenceCreateFeedback' => $incidenceCreateFeedback,
                'evaluationPermissions' => $evaluationPermissions,
                'evaluationKeys' => $evaluationKeys,
                'evaluationFeedback' => $evaluationFeedback,
                'temporaryAccessPanel' => $workspaceTemporaryAccessPanel,
                'temporaryAccessFeedback' => $temporaryAccessFeedback,
                'canViewTemporaryPin' => $workspace === null
                    ? !empty($temporaryAccessPanel['can_edit'])
                    : $canViewTemporaryPin,
                'state' => $state,
                'stateMessage' => $stateMessage,
                'payrollComparisonAvailable' => $payrollComparisonAvailable,
                'links' => in_array($workspace, ['daily-attendance', 'journeys'], true)
                    ? [
                        '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                        '/assets/libs/select2/dist/css/select2.min.css',
                        '/assets/css/select2-modal.css',
                    ]
                    : [],
                'scripts' => [
                    ...(in_array($workspace, ['daily-attendance', 'journeys'], true) ? [
                        '/assets/js/vendor.min.js',
                        '/assets/libs/select2/dist/js/select2.full.min.js',
                        '/assets/libs/select2/dist/js/i18n/es.js',
                        '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                        ...($workspace === 'daily-attendance'
                            ? ['/assets/js/departamento-operativo/2-recursos-humanos/checador.attendance.datatable.init.js?v=20261116-1']
                            : ['/assets/js/departamento-operativo/2-recursos-humanos/checador.journeys.datatable.init.js?v=20261120-1']),
                    ] : []),
                    '/assets/js/core/module-station-selector.js',
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.module-station.init.js?v=20261116-1',
                    ...(($workspace === null || $workspace === 'daily-attendance') ? ['/assets/js/departamento-operativo/2-recursos-humanos/checador.filters.init.js?v=20261116-6'] : []),
                ],
                'help' => false,
                'moduleStationKey' => ChecadorAccessService::MODULE_KEY,
            ],
            'departamento-operativo'
        );
    }

    /** Centralized, read-only configuration view for Checador. */
    public function configuration(): void
    {
        $query = Request::query();
        $access = ChecadorAccessService::authorize();
        if (empty($access['allowed'])) {
            self::renderAccessDenied((string) ($access['reason'] ?? 'authorization_lookup_failed'), null);
            return;
        }

        $workplaces = $access['workplaces'] ?? [];
        $hasExplicitWorkplace = array_key_exists('workplace_id', $query);
        $rawWorkplace = $query['workplace_id'] ?? null;
        $workplaceId = self::positiveInteger($rawWorkplace);
        if ($hasExplicitWorkplace && $rawWorkplace !== '' && $workplaceId === null) {
            self::renderAccessDenied('invalid_station_selection', null);
            return;
        }

        if ($workplaceId !== null) {
            if (empty(ChecadorAccessService::authorize($workplaceId)['allowed'])
                || !ModuleStationService::setChecadorWorkplaceContext($workplaceId)) {
                self::renderAccessDenied('requested_station_out_of_scope', $workplaceId);
                return;
            }
        } elseif ($hasExplicitWorkplace) {
            ModuleStationService::setContext(ChecadorAccessService::MODULE_KEY, null, null);
        } else {
            $context = ModuleStationService::getContext(ChecadorAccessService::MODULE_KEY);
            $contextId = self::positiveInteger($context['id_depto'] ?? $context['id_estacion'] ?? null);
            if ($contextId !== null && in_array($contextId, array_map('intval', array_column($workplaces, 'id')), true)) {
                $workplaceId = $contextId;
            } elseif (!$hasExplicitWorkplace && $workplaces !== []) {
                $workplaceId = (int) $workplaces[0]['id'];
                ModuleStationService::setChecadorWorkplaceContext($workplaceId);
            }
        }

        $user = Auth::user();
        $demoMode = ChecadorDailyReadService::demoModeEnabled();
        $workerRaw = $query['worker_id'] ?? null;
        $workerId = self::positiveInteger($workerRaw);
        $historyInputInvalid = $workerRaw !== null && $workerRaw !== '' && $workerId === null;
        $date = isset($query['date']) && is_string($query['date']) ? trim($query['date']) : '';
        if ($date !== '' && !self::isCivilDate($date)) {
            $historyInputInvalid = true;
            $date = '';
        }
        $auditPage = self::positiveInteger($query['audit_page'] ?? null) ?? 1;

        $configuration = null;
        if ($workplaceId !== null && $user !== null && !$historyInputInvalid) {
            try {
                $configuration = (new ChecadorConfigurationReadService())->read(
                    $user,
                    $workplaceId,
                    $demoMode,
                    $workerId,
                    $date,
                    $auditPage
                );
            } catch (\Throwable) {
                $configuration = ['status' => 'unavailable'];
            }
        }

        $workplaceName = '';
        foreach ($workplaces as $option) {
            if ((int) ($option['id'] ?? 0) === (int) ($workplaceId ?? 0)) {
                $workplaceName = (string) ($option['nombre'] ?? '');
                break;
            }
        }

        $temporaryAccessAvailable = $workplaceId !== null
            && (ChecadorAccessService::authorizeTemporaryAccess($workplaceId, 'leer')['allowed'] ?? false) === true;
        $canReadScheduling = $workplaceId !== null
            && (ChecadorAccessService::authorizeScheduling($workplaceId, 'leer')['allowed'] ?? false) === true;

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Checador', '/departamento-operativo/recursos-humanos/checador');
        Breadcrumb::add('Configuración', '');

        View::render(
            'departamento-operativo/2-recursos-humanos/checador/configuration',
            [
                'title' => 'Configuración de Checador',
                'workplaces' => $workplaces,
                'selectedWorkplaceId' => $workplaceId,
                'selectedWorkplaceName' => $workplaceName,
                'configuration' => $configuration,
                'demoMode' => $demoMode,
                'configurationFeedback' => Session::pull('checador_configuration_feedback', null),
                'attendancePolicyWritesEnabled' => !$demoMode && !empty($configuration['permissions']['policy_edit']),
                'm11ConfigurationWritesEnabled' => !$demoMode && (
                    !empty($configuration['permissions']['positions_edit'])
                    || !empty($configuration['permissions']['reader_profile_edit'])
                ),
                'workerId' => $workerId,
                'date' => $date,
                'historyInputInvalid' => $historyInputInvalid,
                'temporaryAccessAvailable' => $temporaryAccessAvailable,
                'canReadScheduling' => $canReadScheduling,
                'links' => [
                    '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                ],
                'scripts' => [
                    '/assets/js/vendor.min.js',
                    '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                    '/assets/js/core/module-station-selector.js',
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.attendance-policy.init.js?v=20261118-1',
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.m11-positions.init.js?v=20261103-4',
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.m11-reader-profile.init.js?v=20261103-2',
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.configuration.datatable.init.js?v=20261117-2',
                ],
                'moduleStationKey' => ChecadorAccessService::MODULE_KEY,
                'help' => false,
            ],
            'departamento-operativo'
        );
    }

    /** Save the Dirección-only institutional position catalog under the M11 gate. */
    public function saveConfigurationPosition(): void
    {
        $wantsJson = str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
        $input = Request::all();
        $workplaceId = self::positiveInteger($input['workplace_id'] ?? null);
        $positionIdProvided = array_key_exists('position_id', $input) && $input['position_id'] !== null;
        $positionId = self::positiveInteger($input['position_id'] ?? null);
        $nameValue = $input['name'] ?? $input['position_name'] ?? null;
        $name = is_string($nameValue) ? trim($nameValue) : '';
        $redirect = self::configurationHref($workplaceId);
        $user = Auth::user();
        $jsonFail = static function (int $status, string $message, array $metadata = []): never {
            JsonResponse::send(array_merge(['ok' => false, 'message' => $message], $metadata), $status, ['Cache-Control' => 'private, no-store', 'Vary' => 'Accept']);
        };
        if (!$user) {
            if ($wantsJson) $jsonFail(401, 'La sesión ya no es válida. Inicia sesión nuevamente.');
            self::configurationFeedback('error', 'La sesión ya no es válida. Inicia sesión nuevamente.', $workplaceId);
            self::redirect($redirect);
        }
        $csrfToken = trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['_csrf_token'] ?? ''));
        if (!\App\Core\CsrfToken::validate($csrfToken)) {
            if ($wantsJson) $jsonFail(419, 'Actualiza la página antes de guardar el puesto.');
            self::configurationFeedback('error', 'Actualiza la página antes de guardar el puesto.', $workplaceId);
            self::redirect($redirect);
        }
        if ($workplaceId === null || $name === '' || ($positionIdProvided && $positionId === null)
            || (!$wantsJson && ($input['confirm_position'] ?? '') !== '1')) {
            if ($wantsJson) $jsonFail(422, 'Completa el nombre del puesto y vuelve a intentarlo.');
            self::configurationFeedback('error', 'Completa el nombre y confirma el cambio. No se guardó ningún puesto.', $workplaceId);
            self::redirect($redirect);
        }
        if (ChecadorDailyReadService::demoModeEnabled()) {
            if ($wantsJson) $jsonFail(403, 'La demostración no permite modificar el catálogo operativo.');
            self::configurationFeedback('error', 'La demostración no permite modificar el catálogo operativo.', $workplaceId);
            self::redirect($redirect);
        }
        $saved = (new \App\Services\ChecadorConfigurationWriteService())->savePosition($user, $workplaceId, $positionId, $name);
        if ($wantsJson) {
            if ($saved['ok'] ?? false) JsonResponse::send(['ok' => true, 'item' => $saved['item']], 200, ['Cache-Control' => 'private, no-store', 'Vary' => 'Accept']);
            $error = (string) ($saved['error'] ?? '');
            $status = match ($error) {
                'permission_denied' => 403, 'duplicate_position', 'position_not_found' => 409,
                'invalid_position_name' => 422, default => 500,
            };
            $message = match ($error) {
                'permission_denied' => 'No tienes permiso para administrar el catálogo institucional de puestos.',
                'duplicate_position' => 'Ya existe un puesto activo con ese nombre.',
                'position_not_found' => 'El puesto seleccionado ya no está disponible.',
                'invalid_position_name' => 'El nombre del puesto no es válido.',
                default => 'No fue posible guardar y confirmar el puesto.',
            };
            $jsonFail($status, $message, $error === 'position_write_failed'
                ? ['diagnostic_id' => $saved['diagnostic_id'] ?? null]
                : []);
        }
        if ($saved['ok'] ?? false) {
            self::configurationFeedback('success', 'El puesto se guardó y se confirmó mediante lectura posterior.', $workplaceId);
        } else {
            $message = match ($saved['error'] ?? '') {
                'permission_denied' => 'No tienes permiso para administrar el catálogo institucional de puestos.',
                'duplicate_position' => 'Ya existe un puesto activo con ese nombre.',
                'position_not_found' => 'El puesto seleccionado ya no está disponible.',
                'invalid_position_name' => 'El nombre del puesto no es válido.',
                default => 'No fue posible guardar y confirmar el puesto.',
            };
            self::configurationFeedback('error', $message, $workplaceId);
        }
        self::redirect($redirect);
    }

    /** Update an authorized station's reader profile; credentials are write-only. */
    public function updateConfigurationReaderProfile(): void
    {
        $wantsJson = str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
        $input = Request::all();
        $workplaceId = self::positiveInteger($input['workplace_id'] ?? null);
        $profileId = self::positiveInteger($input['profile_id'] ?? null);
        $username = is_string($input['username'] ?? null) ? trim($input['username']) : '';
        $newPassword = is_string($input['new_password'] ?? null) ? $input['new_password'] : '';
        $passwordConfirmationValue = $input['new_password_confirmation'] ?? $input['new_password_confirm'] ?? null;
        $passwordConfirmation = is_string($passwordConfirmationValue) ? $passwordConfirmationValue : '';
        $redirect = self::configurationHref($workplaceId);
        $user = Auth::user();
        $jsonFail = static function (int $status, string $message): never {
            JsonResponse::send(['ok' => false, 'message' => $message], $status, ['Cache-Control' => 'private, no-store', 'Vary' => 'Accept']);
        };
        if (!$user) {
            if ($wantsJson) $jsonFail(401, 'La sesión ya no es válida. Inicia sesión nuevamente.');
            self::configurationFeedback('error', 'La sesión ya no es válida. Inicia sesión nuevamente.', $workplaceId);
            self::redirect($redirect);
        }
        if ($wantsJson && !\App\Core\CsrfToken::validate(trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')))) {
            $jsonFail(419, 'Actualiza la página antes de guardar el perfil.');
        }
        if ($workplaceId === null || $profileId === null || $username === ''
            || ($newPassword !== '' && !hash_equals($newPassword, $passwordConfirmation))
            || (!$wantsJson && ($input['confirm_reader_profile'] ?? '') !== '1')) {
            if ($wantsJson) $jsonFail(422, 'Revisa el usuario y los campos de credencial.');
            self::configurationFeedback('error', 'Revisa el usuario, la confirmación y los campos de credencial. No se guardó el perfil.', $workplaceId);
            self::redirect($redirect);
        }
        if (ChecadorDailyReadService::demoModeEnabled()) {
            if ($wantsJson) $jsonFail(403, 'La demostración no permite modificar perfiles operativos.');
            self::configurationFeedback('error', 'La demostración no permite modificar perfiles operativos.', $workplaceId);
            self::redirect($redirect);
        }
        $saved = (new \App\Services\ChecadorConfigurationWriteService())->updateReaderProfile(
            $user, $workplaceId, $profileId, $username, $newPassword
        );
        if ($wantsJson) {
            if ($saved['ok'] ?? false) JsonResponse::send([
                'ok' => true,
                'message' => 'El perfil se guardó y el usuario se confirmó mediante lectura posterior.',
                'profile' => $saved['profile'],
            ], 200, ['Cache-Control' => 'private, no-store', 'Vary' => 'Accept']);
            $error = (string) ($saved['error'] ?? '');
            $status = match ($error) {
                'permission_denied' => 403, 'profile_not_found', 'duplicate_profile_username' => 409,
                'invalid_profile_input' => 422, default => 502,
            };
            $message = match ($error) {
                'permission_denied' => 'No tienes permiso para editar perfiles de lector en este centro.',
                'profile_not_found' => 'El perfil ya no está disponible en el centro seleccionado.',
                'duplicate_profile_username' => 'Ya existe ese usuario para el lector del centro seleccionado.',
                'station_mapping_unresolved' => 'No se pudo confirmar el mapeo único de la estación física.',
                'invalid_profile_input' => 'Los datos del perfil no tienen un formato válido.',
                default => 'No fue posible guardar y confirmar el perfil del lector.',
            };
            $jsonFail($status, $message);
        }
        if ($saved['ok'] ?? false) {
            self::configurationFeedback('success', 'El perfil se guardó y el usuario se confirmó mediante lectura posterior.', $workplaceId);
        } else {
            $message = match ($saved['error'] ?? '') {
                'permission_denied' => 'No tienes permiso para editar perfiles de lector en este centro.',
                'profile_not_found' => 'El perfil ya no está disponible en el centro seleccionado.',
                'duplicate_profile_username' => 'Ya existe ese usuario para el lector del centro seleccionado.',
                'station_mapping_unresolved' => 'No se pudo confirmar el mapeo único de la estación física.',
                'invalid_profile_input' => 'Los datos del perfil no tienen un formato válido.',
                default => 'No fue posible guardar y confirmar el perfil del lector.',
            };
            self::configurationFeedback('error', $message, $workplaceId);
        }
        self::redirect($redirect);
    }

    /** Create, customize, or append a station-local template version through Portal3's SQL boundary. */
    public function saveConfigurationScheduleTemplate(): void
    {
        $input = Request::all();
        if (str_contains(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) {
            $this->saveScheduleTemplateJson($input);
        }
        $workplaceId = $this->requestM04TemplateWorkplaceId($input);
        $operation = is_string($input['operation'] ?? null) ? trim($input['operation']) : '';
        $key = is_string($input['template_key'] ?? null) ? trim($input['template_key']) : '';
        $name = is_string($input['template_name'] ?? null) ? trim($input['template_name']) : '';
        $type = is_string($input['template_type'] ?? null) ? strtoupper(trim($input['template_type'])) : '';
        $redirect = '/departamento-operativo/recursos-humanos/checador/horarios-turnos'
            . ($workplaceId !== null ? '?workplace_id=' . $workplaceId : '');
        $user = Auth::user();
        if (!$user) {
            self::scheduleTemplateFeedback('error', 'La sesión ya no es válida. Inicia sesión nuevamente.', $workplaceId);
            self::redirect($redirect);
        }
        $formCsrf = trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['_token'] ?? ''));
        if (!\App\Core\CsrfToken::validate($formCsrf)) {
            self::scheduleTemplateFeedback('error', 'Actualiza la página antes de guardar el turno.', $workplaceId);
            self::redirect($redirect);
        }
        if ($workplaceId === null || !in_array($operation, ['create', 'version'], true)
            || ($input['confirm_template'] ?? '') !== '1') {
            self::scheduleTemplateFeedback('error', 'Revisa el centro y confirma la operación solicitada. No se envió la plantilla.', $workplaceId);
            self::redirect($redirect);
        }
        if (ChecadorDailyReadService::demoModeEnabled()) {
            self::scheduleTemplateFeedback('error', 'El modo demostración es de consulta y no permite escribir plantillas operativas.', $workplaceId);
            self::redirect($redirect);
        }
        if (!(ChecadorAccessService::authorizeStationTemplateAdministration($workplaceId)['allowed'] ?? false)) {
            self::scheduleTemplateFeedback('error', 'No tienes permiso explícito para administrar plantillas de esta estación.', $workplaceId);
            self::redirect($redirect);
        }

        $segmentsInput = is_array($input['segments'] ?? null) ? array_values($input['segments']) : [];
        $segments = [];
        foreach ($segmentsInput as $segment) {
            if (!is_array($segment)) continue;
            $start = is_string($segment['hora_inicio_local'] ?? null) ? trim($segment['hora_inicio_local']) : '';
            $end = is_string($segment['hora_fin_local'] ?? null) ? trim($segment['hora_fin_local']) : '';
            if ($start === '' && $end === '') continue;
            $segments[] = [
                'ordinal' => count($segments) + 1,
                'tipo_segmento' => is_string($segment['tipo_segmento'] ?? null) ? strtoupper(trim($segment['tipo_segmento'])) : 'WORK',
                'hora_inicio_local' => $start,
                'hora_fin_local' => $end,
                'dia_offset_inicio' => isset($segment['dia_offset_inicio']) ? (int) $segment['dia_offset_inicio'] : 0,
                'dia_offset_fin' => isset($segment['dia_offset_fin']) ? (int) $segment['dia_offset_fin'] : 0,
            ];
        }
        if ($type === '' && $segments !== []) $type = self::scheduleSegmentsAreOvernight($segments) ? 'NOCTURNO' : 'REGULAR';
        if ($name === '' || $type === '' || $segments === []) {
            self::scheduleTemplateFeedback('error', 'Indica nombre, tipo y al menos un segmento horario válido.', $workplaceId);
            self::redirect($redirect);
        }
        if ($operation === 'create') {
            $normalizedKey = strtoupper((string) (iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name));
            $normalizedKey = trim((string) preg_replace('/[^A-Z0-9]+/', '_', $normalizedKey), '_');
            $key = substr('ST' . $workplaceId . '_' . $normalizedKey . '_' . strtoupper(bin2hex(random_bytes(4))), 0, 64);
        }

        try {
            $result = (new ChecadorShiftTemplateSqlWriteService())->write($user, $workplaceId, [
                'operation' => $operation, 'template_key' => $key, 'expected_version' => $input['expected_version'] ?? null,
                'template_name' => $name, 'template_type' => $type, 'segments' => $segments,
                'idempotency_key' => $input['idempotency_key'] ?? '', 'confirm_action' => $input['confirm_template'] ?? '',
            ]);
        } catch (\Throwable) {
            $result = ['ok' => false, 'error' => 'database_write_failed'];
        }
        self::scheduleTemplateFeedback(($result['ok'] ?? false) ? 'success' : 'error',
            ($result['ok'] ?? false)
                ? 'La plantilla y sus segmentos persistidos se confirmaron mediante lectura SQL.'
                : 'No se confirmó el guardado y la lectura SQL posterior. Consulta el catálogo antes de reintentar.',
            $workplaceId);
        self::redirect($redirect);
    }

    /** Preserve the original M04 Alpine workflow while using the Portal3 SQL writer only. */
    private function saveScheduleTemplateJson(array $input): never
    {
        $headers = ['Cache-Control' => 'private, no-store'];
        $fail = static function (string $code, string $message, int $status) use ($headers): never {
            JsonResponse::send(['ok' => false, 'code' => $code, 'message' => $message], $status, $headers);
        };
        $user = Auth::user();
        if ($user === null) $fail('unauthenticated', 'La sesión ya no es válida. Inicia sesión nuevamente.', 401);
        $csrf = trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (!\App\Core\CsrfToken::validate($csrf)) $fail('csrf_invalid', 'Actualiza la página antes de guardar el turno.', 419);

        $workplaceId = $this->requestM04TemplateWorkplaceId($input);
        $rawWorkplace = self::positiveInteger($input['workplace_id'] ?? null);
        if ($workplaceId === null || $rawWorkplace !== $workplaceId) {
            $fail('forbidden', 'La estación solicitada no coincide con tu alcance autorizado.', 403);
        }
        if (ChecadorDailyReadService::demoModeEnabled()) $fail('demo_mode', 'La demostración es de consulta.', 403);
        if (!(ChecadorAccessService::authorizeStationTemplateAdministration($workplaceId)['allowed'] ?? false)) {
            $fail('forbidden', 'No tienes permiso para administrar turnos en esta estación.', 403);
        }
        $operation = is_string($input['operation'] ?? null) ? trim($input['operation']) : '';
        $name = is_string($input['template_name'] ?? null) ? trim($input['template_name']) : '';
        $segments = self::normalizeOriginalScheduleTemplateSegments($input['segments'] ?? null);
        if (!in_array($operation, ['create', 'customize', 'version'], true)
            || $name === '' || mb_strlen($name) > 120 || $segments === []) {
            $fail('invalid_input', 'Revisa el nombre y las horas del turno.', 422);
        }
        if ($operation === 'create' && ($input['confirm_action'] ?? '') !== '1') {
            $fail('confirmation_required', 'Confirma la creación del turno.', 422);
        }

        $idempotencyKey = trim((string) ($input['idempotency_key'] ?? ''));
        if (strlen($idempotencyKey) < 16 || strlen($idempotencyKey) > 128
            || preg_match('/\A[A-Za-z0-9._:-]+\z/D', $idempotencyKey) !== 1) {
            $fail('invalid_input', 'Actualiza la página e intenta nuevamente; no se recibió una clave segura de operación.', 422);
        }
        try {
            $reader = new ChecadorShiftTemplateSqlReadService(null, ChecadorShiftTemplateSqlWriteService::CONNECTION_NAME);
            $catalog = $reader->getShiftTemplates($user, $workplaceId, 1, 200);
            if (!($catalog['ok'] ?? false)) $fail('catalog_unavailable', 'No se pudo comprobar el catálogo SQL autorizado.', 502);
            $items = $catalog['data']['items'] ?? [];
            $templateType = self::scheduleSegmentsAreOvernight($segments) ? 'NOCTURNO' : 'REGULAR';
            $writeInput = [
                'operation' => $operation, 'template_name' => $name, 'template_type' => $templateType,
                'segments' => $segments, 'idempotency_key' => $idempotencyKey,
            ];

            if ($operation === 'create') {
                $normalized = strtoupper((string) (iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name));
                $normalized = trim((string) preg_replace('/[^A-Z0-9]+/', '_', $normalized), '_');
                $key = substr('ST' . $workplaceId . '_' . $normalized . '_' . strtoupper(bin2hex(random_bytes(4))), 0, 64);
                foreach ($items as $item) {
                    if (strcasecmp((string) ($item['clave'] ?? ''), $key) === 0
                        || strcasecmp(trim((string) ($item['nombre'] ?? '')), $name) === 0) {
                        $fail('administrative_conflict', 'Ya existe un turno con este nombre. Actualiza el catálogo.', 409);
                    }
                }
                $type = self::scheduleSegmentsAreOvernight($segments) ? 'NOCTURNO' : 'REGULAR';
                $writeInput['confirm_action'] = '1';
            } elseif ($operation === 'customize') {
                if (ChecadorAccessService::isDepartmentLocality($workplaceId)
                    && $workplaceId !== ChecadorAccessService::AUTOLAVADO_LOCALITY_ID) {
                    $fail('forbidden', 'No tienes permiso para personalizar turnos en esta estación.', 403);
                }
                $sourceKey = is_string($input['source_template_key'] ?? null) ? trim($input['source_template_key']) : '';
                $sourceVersion = self::positiveInteger($input['source_template_version'] ?? null);
                $sourceItem = null;
                $globalCatalog = $reader->getShiftTemplates($user, $workplaceId, 1, 200, 'GLOBAL');
                if (!($globalCatalog['ok'] ?? false)) {
                    $globalError = (string) ($globalCatalog['error']['kind'] ?? 'database_unavailable');
                    $fail($globalError === 'permission_denied' ? 'forbidden' : 'catalog_unavailable',
                        'No se pudo verificar el catálogo GLOBAL autorizado.', $globalError === 'permission_denied' ? 403 : 502);
                }
                $globalItems = $globalCatalog['data']['items'] ?? [];
                foreach ($globalItems as $item) {
                    if ((string) ($item['clave'] ?? '') === $sourceKey
                        && strtoupper((string) ($item['scope'] ?? '')) === 'GLOBAL') {
                        $sourceItem = $item;
                        break;
                    }
                }
                if ($sourceItem === null || $sourceVersion === null || $sourceVersion !== (int) ($sourceItem['version'] ?? 0)) {
                    $fail('source_unavailable', 'El turno global ya no está disponible. Actualiza el catálogo.', 404);
                }
                $source = $this->readAuthorizedTemplateDetail($reader, $globalItems, $user, $workplaceId, $sourceKey, $sourceVersion, 'GLOBAL');
                if (!($source['ok'] ?? false)
                    || strtoupper((string) ($source['data']['scope'] ?? '')) !== 'GLOBAL'
                    || (int) ($source['data']['version'] ?? 0) !== $sourceVersion
                    || (int) ($source['data']['es_activo'] ?? 0) !== 1) {
                    $fail('source_unavailable', 'No se pudo confirmar el turno global de origen.', 404);
                }
                $writeInput['source_template_key'] = $sourceKey;
                $writeInput['source_template_version'] = $sourceVersion;
                $writeInput['template_type'] = (string) $source['data']['tipo_turno'];
            } else {
                $key = is_string($input['template_key'] ?? null) ? trim($input['template_key']) : '';
                $expectedVersion = self::positiveInteger($input['expected_version'] ?? null);
                $local = null;
                foreach ($items as $item) {
                    if ((string) ($item['clave'] ?? '') === $key) { $local = $item; break; }
                }
                if ($local === null || $expectedVersion === null
                    || strtoupper((string) ($local['scope'] ?? '')) !== 'STATION'
                    || (int) ($local['id_estacion'] ?? 0) !== $workplaceId) {
                    $fail('forbidden', 'Solo puedes editar turnos locales de tu estación.', 403);
                }
                if ((int) ($local['es_activo'] ?? 0) !== 1) $fail('conflict', 'El turno está inactivo.', 409);
                if ((int) ($local['version'] ?? 0) !== $expectedVersion) {
                    $fail('conflict', 'El turno cambió. Actualiza el catálogo antes de editarlo.', 409);
                }
                $previous = $this->readAuthorizedTemplateDetail($reader, $items, $user, $workplaceId, $key, $expectedVersion, 'STATION');
                if (!($previous['ok'] ?? false)
                    || (int) ($previous['data']['id'] ?? 0) !== (int) ($local['id'] ?? 0)
                    || self::editableScheduleTemplateFields($previous['data']['segmentos'] ?? null) === null) {
                    $fail('catalog_unavailable', 'No se pudo verificar el turno seleccionado.', 502);
                }
                $writeInput['template_key'] = $key;
                $writeInput['expected_version'] = $expectedVersion;
                $writeInput['template_type'] = (string) ($previous['data']['tipo_turno'] ?? 'REGULAR');
            }

            $result = (new ChecadorShiftTemplateSqlWriteService())->write($user, $workplaceId, $writeInput);
            if (!($result['ok'] ?? false)) {
                $kind = (string) ($result['error'] ?? 'database_write_failed');
                $status = match ($kind) {
                    'invalid_input', 'confirmation_required' => 422,
                    'permission_denied' => 403,
                    'idempotency_conflict', 'version_conflict', 'customization_exists', 'duplicate_template_name' => 409,
                    'template_not_found', 'source_not_found' => 404,
                    default => (int) ($result['status'] ?? 503),
                };
                $message = match ($kind) {
                    'demo_mode_read_only' => 'El modo demostración es de consulta y no permite guardar plantillas operativas.',
                    'duplicate_template_name' => 'Ya existe un turno con este nombre. Actualiza el catálogo.',
                    'version_conflict' => 'El turno cambió desde que abriste el formulario. Actualiza el catálogo antes de editarlo.',
                    'idempotency_conflict' => 'La clave de operación ya se utilizó con otros datos. Actualiza la página antes de reintentar.',
                    default => $status === 409 ? 'Ya existe un conflicto con ese turno.' : 'No se pudo confirmar el turno. Consulta el catálogo antes de reintentar.',
                };
                $fail($kind, $message, $status);
            }
            $writtenTemplate = $result['template'] ?? null;
            if (!is_array($writtenTemplate)
                || !is_string($writtenTemplate['clave'] ?? null)
                || (int) ($writtenTemplate['id'] ?? 0) < 1
                || (int) ($writtenTemplate['version'] ?? 0) < 1
                || strtoupper((string) ($writtenTemplate['scope'] ?? '')) !== 'STATION'
                || (int) ($writtenTemplate['id_estacion'] ?? 0) !== $workplaceId) {
                $fail('schedule_template_readback_unconfirmed', 'No se confirmó el turno mediante lectura posterior.', 502);
            }
            $template = $writtenTemplate;
            if (!is_array($template)
                || ($template['segmentos'] ?? null) !== $segments
                || self::editableScheduleTemplateFields($template['segmentos'] ?? null) === null
                || trim((string) ($template['segmentos'][0]['hora_inicio_local'] ?? '')) === ''
                || trim((string) ($template['segmentos'][array_key_last($template['segmentos'])]['hora_fin_local'] ?? '')) === '') {
                $fail('schedule_template_readback_unconfirmed', 'La plantilla no quedó confirmada con sus segmentos persistidos. No vuelvas a guardar; consulta el catálogo antes de reintentar.', 502);
            }
            JsonResponse::send([
                'ok' => true,
                'message' => ($result['replayed'] ?? false)
                    ? 'La operación anterior y sus horas persistidas fueron confirmadas mediante lectura SQL.'
                    : ($operation === 'customize'
                        ? 'La personalización local y sus horas persistidas fueron confirmadas mediante lectura SQL.'
                        : 'El turno y sus horas persistidas fueron confirmados mediante lectura SQL.'),
                'template' => $template,
            ], $operation === 'create' ? 201 : 200, $headers);
        } catch (\Throwable) {
            $fail('server_error', 'No fue posible confirmar el turno. Consulta el catálogo antes de reintentar.', 500);
        }
    }

    private static function normalizeOriginalScheduleTemplateSegments(mixed $raw): array
    {
        if (!is_array($raw) || !array_is_list($raw) || $raw === [] || count($raw) > 3) return [];
        $segments = [];
        foreach ($raw as $index => $segment) {
            if (!is_array($segment)) return [];
            $kind = strtoupper(trim((string) ($segment['tipo_segmento'] ?? '')));
            $start = trim((string) ($segment['hora_inicio_local'] ?? ''));
            $end = trim((string) ($segment['hora_fin_local'] ?? ''));
            $startOffset = filter_var($segment['dia_offset_inicio'] ?? null, FILTER_VALIDATE_INT);
            $endOffset = filter_var($segment['dia_offset_fin'] ?? null, FILTER_VALIDATE_INT);
            if (!in_array($kind, ['WORK', 'BREAK'], true)
                || preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?\z/D', $start) !== 1
                || preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?\z/D', $end) !== 1
                || !in_array($startOffset, [0, 1], true) || !in_array($endOffset, [0, 1], true)) return [];
            $segments[] = [
                'ordinal' => $index + 1, 'tipo_segmento' => $kind,
                'hora_inicio_local' => strlen($start) === 5 ? $start . ':00' : $start,
                'hora_fin_local' => strlen($end) === 5 ? $end . ':00' : $end,
                'dia_offset_inicio' => $startOffset, 'dia_offset_fin' => $endOffset,
            ];
        }
        return self::editableScheduleTemplateFields($segments) === null ? [] : $segments;
    }

    /** Resolve an exact item from the authorized SQL catalog before reading its persisted version. */
    private function readAuthorizedTemplateDetail(
        ChecadorShiftTemplateSqlReadService $reader,
        array $items,
        \App\Models\Usuario $user,
        int $workplaceId,
        string $key,
        int $version,
        string $scope
    ): array {
        foreach ($items as $item) {
            if ((string) ($item['clave'] ?? '') !== $key || (int) ($item['version'] ?? 0) !== $version
                || strtoupper((string) ($item['scope'] ?? '')) !== strtoupper($scope)) continue;
            $stationId = $item['id_estacion'] === null ? null : (int) $item['id_estacion'];
            return $reader->getShiftTemplateVersion(
                $user, $key, $version, $workplaceId, (int) ($item['id'] ?? 0), strtoupper($scope), $stationId
            );
        }
        return ['ok' => false, 'error' => ['kind' => 'schedule_template_not_found', 'status' => 404]];
    }

    private static function scheduleSegmentsAreOvernight(array $segments): bool
    {
        foreach ($segments as $segment) {
            if ((int) ($segment['dia_offset_fin'] ?? 0) > (int) ($segment['dia_offset_inicio'] ?? 0)) return true;
        }
        return false;
    }

    private static function editableScheduleTemplateFields(mixed $segments): ?array
    {
        if (!is_array($segments) || !array_is_list($segments) || !in_array(count($segments), [1, 3], true)) return null;
        foreach ($segments as $segment) if (!is_array($segment)) return null;
        $types = array_map(static fn(array $segment): string => strtoupper((string) ($segment['tipo_segmento'] ?? '')), $segments);
        if ($types !== ['WORK'] && $types !== ['WORK', 'BREAK', 'WORK']) return null;
        $previousEnd = null;
        foreach ($segments as $index => $segment) {
            $startTime = (string) ($segment['hora_inicio_local'] ?? '');
            $endTime = (string) ($segment['hora_fin_local'] ?? '');
            $startOffset = filter_var($segment['dia_offset_inicio'] ?? null, FILTER_VALIDATE_INT);
            $endOffset = filter_var($segment['dia_offset_fin'] ?? null, FILTER_VALIDATE_INT);
            if (preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9]:00\z/D', $startTime) !== 1
                || preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9]:00\z/D', $endTime) !== 1
                || !in_array($startOffset, [0, 1], true) || !in_array($endOffset, [0, 1], true)) return null;
            $start = $startOffset * 1440 + (int) substr($startTime, 0, 2) * 60 + (int) substr($startTime, 3, 2);
            $end = $endOffset * 1440 + (int) substr($endTime, 0, 2) * 60 + (int) substr($endTime, 3, 2);
            if ($end <= $start || ($previousEnd !== null && $previousEnd !== $start) || ($index === 0 && $startOffset !== 0)) return null;
            $previousEnd = $end;
        }
        $work = array_values(array_filter($segments, static fn(array $segment): bool => strtoupper((string) $segment['tipo_segmento']) === 'WORK'));
        $break = array_values(array_filter($segments, static fn(array $segment): bool => strtoupper((string) $segment['tipo_segmento']) === 'BREAK'));
        return [
            'start_time' => substr((string) $work[0]['hora_inicio_local'], 0, 5),
            'end_time' => substr((string) $work[array_key_last($work)]['hora_fin_local'], 0, 5),
            'break_start' => $break === [] ? '' : substr((string) $break[0]['hora_inicio_local'], 0, 5),
            'break_end' => $break === [] ? '' : substr((string) $break[0]['hora_fin_local'], 0, 5),
        ];
    }

    private static function configurationHref(?int $workplaceId): string
    {
        return '/departamento-operativo/recursos-humanos/checador/configuracion'
            . ($workplaceId !== null ? '?workplace_id=' . $workplaceId : '');
    }

    private static function configurationFeedback(string $type, string $message, ?int $workplaceId): void
    {
        Session::set('checador_configuration_feedback', [
            'type' => $type,
            'message' => $message,
            'workplace_id' => $workplaceId,
        ]);
    }

    private static function scheduleTemplateFeedback(string $type, string $message, ?int $workplaceId): void
    {
        Session::set('checador_schedule_template_feedback', [
            'type' => in_array($type, ['success', 'error'], true) ? $type : 'error',
            'message' => $message,
            'workplace_id' => $workplaceId,
        ]);
    }

    /** Create and verify a prospective M11 policy version in the authorized SQL store. */
    public function saveAttendancePolicy(): void
    {
        $wantsJson = str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
        $input = Request::all();
        $workplaceId = self::positiveInteger($input['workplace_id'] ?? null);
        $effectiveDate = is_string($input['effective_from_local_date'] ?? null) ? trim($input['effective_from_local_date']) : '';
        $tolerance = $input['retardo_tolerance_minutes'] ?? null;
        $deadline = $input['incidence_deadline_days'] ?? null;
        $idempotencyKey = is_string($input['idempotency_key'] ?? null) ? trim($input['idempotency_key']) : '';
        $redirect = self::configurationHref($workplaceId);
        $jsonFail = static function (int $status, string $code, string $message, array $metadata = []): never {
            JsonResponse::send(array_merge(['ok' => false, 'code' => $code, 'message' => $message], $metadata), $status, ['Cache-Control' => 'private, no-store', 'Vary' => 'Accept']);
        };

        if (!\App\Core\CsrfToken::validate(trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['_csrf_token'] ?? '')))) {
            if ($wantsJson) $jsonFail(419, 'csrf_invalid', 'Actualiza la página antes de guardar la configuración.');
            self::configurationFeedback('error', 'Actualiza la página antes de guardar la configuración.', $workplaceId);
            self::redirect($redirect);
        }

        if ($workplaceId === null || !self::isCivilDate($effectiveDate)
            || !is_scalar($tolerance) || !is_scalar($deadline)
            || !preg_match('/\A[A-Za-z0-9._:-]{1,64}\z/', $idempotencyKey)
            || (string) ($input['confirm_policy'] ?? '') !== '1') {
            if ($wantsJson) $jsonFail(422, 'invalid_input', 'Completa los valores y la fecha de vigencia solicitados.');
            self::configurationFeedback('error', 'Completa los valores y la fecha de vigencia solicitados. No se guardó ningún cambio.', $workplaceId);
            self::redirect($redirect);
        }

        $authorization = ChecadorAccessService::authorizeAttendancePolicy($workplaceId, 'editar');
        $readAuthorization = ChecadorAccessService::authorizeAttendancePolicy($workplaceId, 'leer');
        if (!($authorization['allowed'] ?? false) || !($readAuthorization['allowed'] ?? false)) {
            if ($wantsJson) $jsonFail(403, 'permission_denied', 'No tienes permiso específico para editar políticas en el centro seleccionado.');
            self::configurationFeedback('error', 'No tienes permiso específico para editar políticas en el centro seleccionado.', $workplaceId);
            self::redirect($redirect);
        }
        if (ChecadorDailyReadService::demoModeEnabled()) {
            if ($wantsJson) $jsonFail(403, 'demo_mode', 'El modo demostración no permite guardar políticas operativas.');
            self::configurationFeedback('error', 'El modo demostración no permite guardar políticas operativas.', $workplaceId);
            self::redirect($redirect);
        }
        $user = Auth::user();
        if (!$user) {
            if ($wantsJson) $jsonFail(401, 'unauthenticated', 'La sesión ya no es válida. Inicia sesión nuevamente.');
            self::configurationFeedback('error', 'La sesión ya no es válida. Inicia sesión nuevamente.', $workplaceId);
            self::redirect($redirect);
        }
        $saved = (new ChecadorAttendancePolicyStoreService())->create(
            $user, $workplaceId, $effectiveDate, $tolerance, $deadline, $idempotencyKey
        );
        if (!($saved['ok'] ?? false)) {
            $error = (string) ($saved['error'] ?? 'attendance_policy_write_failed');
            $status = match ($error) {
                'permission_denied' => 403,
                'idempotency_conflict', 'policy_date_conflict', 'policy_version_date_conflict' => 409,
                'policy_must_be_prospective' => 422,
                'invalid_policy_input' => 422,
                'demo_mode' => 403,
                default => 503,
            };
            $message = match ($error) {
                'permission_denied' => 'No tienes autorización efectiva para editar la política de esta localidad.',
                'idempotency_conflict' => 'La clave de solicitud ya está asociada a otros valores; conserva la clave sólo para reintentar los mismos datos.',
                'policy_date_conflict', 'policy_version_date_conflict' => 'Ya existe una versión para esa fecha o la vigencia no sigue la secuencia persistida.',
                'policy_must_be_prospective' => 'La fecha debe ser posterior al día local vigente.',
                'invalid_policy_input' => 'Revisa la fecha, los valores y la clave de solicitud.',
                'demo_mode' => 'El modo demostración no permite guardar políticas operativas.',
                'workplace_timezone_unresolved' => 'No se pudo confirmar la zona horaria persistida de esta localidad.',
                default => 'No fue posible guardar y confirmar la política en la base de datos.',
            };
            if ($wantsJson) $jsonFail($status, $error, $message, ['retry_idempotency_key' => $idempotencyKey]);
            self::configurationFeedback('error', $message, $workplaceId);
            self::redirect($redirect);
        }

        $message = !empty($saved['created'])
            ? 'La nueva vigencia se guardó en la base de datos y se confirmó mediante lectura posterior.'
            : 'La vigencia ya existía con esos valores y se confirmó mediante lectura SQL; no se creó otra versión.';
        if ($wantsJson) JsonResponse::send([
            'ok' => true,
            'message' => $message,
            'policy' => $saved['policy'],
            'next_scheduled_policy' => $saved['next_scheduled_policy'] ?? null,
            'current_local_date' => $saved['current_local_date'] ?? null,
            'next_idempotency_key' => bin2hex(random_bytes(16)),
        ], 200, ['Cache-Control' => 'private, no-store', 'Vary' => 'Accept']);
        self::configurationFeedback('success', $message, $workplaceId);
        self::redirect($redirect);
    }

    /** Read-only schedule validity view, independent of the PunchEvent snapshot. */
    private function renderScheduleHistory(
        ?int $workplaceId,
        array $workplaces,
        ?int $workerId,
        string $date,
        int $historyPage,
        bool $invalidPage,
        bool $demoMode,
        ?string $demoScenario
    ): void {
        $scheduleHistoryAllowed = true;
        $templateCatalogAccess = $workplaceId === null
            ? ['allowed' => false, 'reason' => 'invalid_workplace', 'workplaces' => []]
            : ChecadorAccessService::authorizeShiftTemplateCatalogRead($workplaceId);
        if ($workplaceId !== null) {
            $scheduleAccess = ChecadorAccessService::authorizeScheduling($workplaceId, 'leer');
            $scheduleHistoryAllowed = (bool) ($scheduleAccess['allowed'] ?? false);
            if (!$scheduleHistoryAllowed && !($templateCatalogAccess['allowed'] ?? false)) {
                self::renderAccessDenied((string) ($scheduleAccess['reason'] ?? 'scheduling_permission_denied'), $workplaceId);
                return;
            }
        }
        $effectiveSchedule = null;
        $effectiveScheduleError = null;
        $historyItems = [];
        $historyError = null;
        $selectedWorkerName = '';
        $historyHasNext = false;
        $dateAssignments = [];
        $dateAssignmentsTruncated = false;
        $validityCheck = null;
        $currentUser = Auth::user();
        $scheduleTemplates = [];
        $templateCatalogStatus = 'not_requested';
        $templateCatalogMessage = '';
        $m04AutolavadoAdmin = ChecadorAccessService::authorizeStationTemplateAdministration(
            ChecadorAccessService::AUTOLAVADO_LOCALITY_ID
        );
        $templateAdminAccess = $workplaceId === null
            ? ['allowed' => false]
            : ChecadorAccessService::authorizeStationTemplateAdministration($workplaceId);
        // Keep administration controls tied to the effective server-side permission.
        $canManageStationTemplates = $workplaceId !== null && (bool) ($templateAdminAccess['allowed'] ?? false);
        $templateWritesEnabled = $canManageStationTemplates && !$demoMode;
        $templateWriteBlockMessage = $demoMode ? 'El modo demostración es de consulta.' : '';

        if ($invalidPage || $historyPage > 10000) {
            $historyError = 'bad_request';
        } elseif ($date !== '' && !self::isCivilDate($date)) {
            $historyError = 'bad_request';
            $effectiveScheduleError = 'bad_request';
        } elseif ($demoMode) {
            // The approved demo fixtures do not contain assignment history.
            // Never let this read fall through to the live API in demo mode.
            $historyError = 'demo_history_unavailable';
            $effectiveScheduleError = 'demo_history_unavailable';
        } elseif ($scheduleHistoryAllowed && $workplaceId !== null && $workerId !== null && self::isCivilDate($date) && $currentUser !== null) {
            $roster = self::scheduleHistoryWorkerRoster($workplaceId, $date, $workerId);
            $selectedWorkerName = trim((string) ($roster['workers'][0]['full_name'] ?? ''));
            if (!($roster['ok'] ?? false) || $selectedWorkerName === '') {
                $historyError = 'worker_scope_mismatch';
                $effectiveScheduleError = 'worker_scope_mismatch';
                $workerId = null;
            } else {
                try {
                    $assignmentHistory = (new \App\Services\ChecadorWeeklyScheduleSqlReadService())
                        ->readAssignmentHistory($currentUser, $workplaceId, $workerId, $historyPage, 50, $date);
                    if (($assignmentHistory['ok'] ?? false) === true) {
                        $historyData = $assignmentHistory['data'];
                        $historyItems = $historyData['items'];
                        $historyHasNext = (bool) $historyData['has_next'];
                        $dateAssignments = $historyData['date_assignment_facts'];
                        $dateAssignmentsTruncated = (bool) $historyData['date_facts_truncated'];
                    } else {
                        $historyError = (string) ($assignmentHistory['error']['kind'] ?? 'database_unavailable');
                        $effectiveScheduleError = $historyError;
                        $validityCheck = ['status' => 'history_unavailable', 'weekday' => '', 'assignment' => null];
                    }
                } catch (\Throwable) {
                    $effectiveScheduleError ??= 'database_unavailable';
                    $historyError ??= 'database_unavailable';
                }
            }
        }

        if ($workplaceId !== null && !$demoMode && $currentUser !== null) {
            try {
                $templateReader = new ChecadorShiftTemplateSqlReadService(null, ChecadorShiftTemplateSqlWriteService::CONNECTION_NAME);
                $catalog = $templateReader->getShiftTemplates($currentUser, $workplaceId, 1, 200);
                if (($catalog['ok'] ?? false) === true) {
                    $templateCatalogStatus = 'received';
                    $catalogItems = $catalog['data']['items'];
                    foreach (array_slice($catalogItems, 0, 50) as $template) {
                        if (($template['scope'] ?? '') !== 'STATION'
                            || (int) ($template['id_estacion'] ?? 0) !== $workplaceId) {
                            $templateCatalogStatus = 'partial';
                            continue;
                        }
                        $detail = $templateReader->getShiftTemplateVersion(
                            $currentUser,
                            (string) $template['clave'],
                            (int) $template['version'],
                            $workplaceId,
                            (int) $template['id'],
                            (string) $template['scope'],
                            $template['id_estacion'] === null ? null : (int) $template['id_estacion']
                        );
                        if (($detail['ok'] ?? false) !== true) {
                            $detailError = (string) ($detail['error']['kind'] ?? 'database_unavailable');
                            $templateCatalogStatus = $detailError === 'permission_denied'
                                ? 'permission_denied'
                                : ($detailError === 'database_unavailable' ? 'database_error' : 'partial');
                            continue;
                        }
                        $scheduleTemplates[] = array_merge($template, $detail['data'], [
                            'segmentos' => $detail['data']['segmentos'],
                            'segments' => $detail['data']['segmentos'],
                        ]);
                    }
                    foreach ($scheduleTemplates as &$template) {
                        $template['edit_fields'] = self::editableScheduleTemplateFields($template['segmentos'] ?? null);
                    }
                    unset($template);
                    if ($templateCatalogStatus === 'database_error') {
                        $templateCatalogMessage = 'Ocurrió un error SQL al leer el detalle de los horarios. Revisa el registro técnico.';
                    } elseif ($templateCatalogStatus === 'permission_denied') {
                        $templateCatalogMessage = 'No tienes autorización para consultar los horarios de esta localidad.';
                    } elseif (count($catalogItems) > 50) {
                        $templateCatalogStatus = 'partial';
                        $templateCatalogMessage = 'Se alcanzó el límite seguro de detalle de 50 plantillas. La lista puede estar incompleta.';
                    } elseif ($templateCatalogStatus === 'partial') {
                        $templateCatalogMessage = 'No se pudo leer el detalle de todas las plantillas recibidas.';
                    } else {
                        $templateCatalogMessage = count($scheduleTemplates) . ' horarios recibidos. El catálogo local no publica un total; la consulta no confirma por sí sola que no existan más.';
                    }
                } else {
                    if (($catalog['error']['kind'] ?? '') === 'permission_denied') {
                        $templateCatalogStatus = 'permission_denied';
                        $templateCatalogMessage = 'No tienes autorización para consultar los horarios de esta localidad.';
                    } else {
                        $templateCatalogStatus = 'database_error';
                        $templateCatalogMessage = 'Ocurrió un error SQL al leer el catálogo de horarios. Revisa el registro técnico.';
                    }
                }
            } catch (\Throwable $exception) {
                try {
                    Logger::warning('Checador M04 schedule catalog rendering failed', [
                        'operation' => 'm04_schedule_catalog_render',
                        'source' => 'portal3_sql_reader',
                        'error_kind' => 'local_render_failure',
                        'exception_class' => $exception::class,
                    ]);
                } catch (\Throwable) {
                    // Diagnostic logging must never change the page response.
                }
                $templateCatalogStatus = 'render_error';
                $templateCatalogMessage = 'Ocurrió un error al procesar la lectura del catálogo de horarios.';
            }
        } elseif ($demoMode) {
            $templateCatalogStatus = 'unavailable';
            $templateCatalogMessage = 'El modo de demostración no consulta el catálogo operativo.';
        }

        $workplaceName = '';
        foreach ($workplaces as $workplace) {
            if ((int) ($workplace['id'] ?? 0) === (int) ($workplaceId ?? 0)) {
                $workplaceName = (string) ($workplace['nombre'] ?? '');
                break;
            }
        }

        $templateFeedback = Session::pull('checador_schedule_template_feedback', null);
        if (!is_array($templateFeedback)
            || (int) ($templateFeedback['workplace_id'] ?? 0) !== (int) ($workplaceId ?? 0)) {
            $templateFeedback = null;
        }

        View::render(
            'departamento-operativo/2-recursos-humanos/checador/schedule-history',
            [
                'title' => 'Vigencias de horarios',
                'workplaces' => $workplaces,
                'selectedWorkplaceId' => $workplaceId,
                'selectedWorkplaceName' => $workplaceName,
                'selectedDate' => $date,
                'selectedWorkerId' => $workerId,
                'selectedWorkerName' => $selectedWorkerName,
                'effectiveSchedule' => $effectiveSchedule,
                'effectiveScheduleError' => $effectiveScheduleError,
                'effectiveScheduleErrorMessage' => self::scheduleHistoryErrorMessage($effectiveScheduleError),
                'historyItems' => $historyItems,
                'historyError' => $historyError,
                'historyErrorMessage' => self::scheduleHistoryErrorMessage($historyError),
                'historyPage' => $historyPage,
                'historyHasPrevious' => $historyPage > 1,
                'historyHasNext' => $historyHasNext,
                'dateAssignments' => $dateAssignments,
                'dateAssignmentsTruncated' => $dateAssignmentsTruncated,
                'validityCheck' => $validityCheck,
                'scheduleTemplates' => $scheduleTemplates,
                'templateCatalogStatus' => $templateCatalogStatus,
                'templateCatalogMessage' => $templateCatalogMessage,
                'feedback' => $templateFeedback,
                'apiTemplates' => [
                    'status' => $templateCatalogStatus === 'received' ? 'ready' : $templateCatalogStatus,
                    'items' => $scheduleTemplates,
                ],
                'templateAdminFeedback' => $templateFeedback,
                'canManageStationTemplates' => $canManageStationTemplates,
                'templateWritesEnabled' => $templateWritesEnabled,
                'templateWriteBlockMessage' => $templateWriteBlockMessage,
                'demoMode' => $demoMode,
                'demoScenario' => $demoScenario,
                'links' => [
                    '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                ],
                'scripts' => [
                    '/assets/js/vendor.min.js',
                    '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                    '/assets/js/core/module-station-selector.js',
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.module-station.init.js?v=20261021-1',
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.schedule-history.templates.init.js?v=20261119-1',
                ],
                'help' => false,
                'moduleStationKey' => ChecadorAccessService::MODULE_KEY,
                'moduleStationExtraDepartments' => ($m04AutolavadoAdmin['allowed'] ?? false)
                    ? ($m04AutolavadoAdmin['workplaces'] ?? [])
                    : [],
            ],
            'departamento-operativo'
        );
    }

    private function renderScheduleWeek(?int $workplaceId, array $workplaces, string $weekStart, bool $demoMode, ?string $demoScenario): void
    {
        if ($workplaceId !== null) {
            $access = ChecadorAccessService::authorizeScheduling($workplaceId, 'leer');
            if (!($access['allowed'] ?? false)) {
                self::renderAccessDenied((string) ($access['reason'] ?? 'scheduling_permission_denied'), $workplaceId);
                return;
            }
        }
        $workplaceName = '';
        foreach ($workplaces as $workplace) {
            if ((int) ($workplace['id'] ?? 0) === (int) ($workplaceId ?? 0)) $workplaceName = (string) ($workplace['nombre'] ?? '');
        }
        $dates = \App\Services\ChecadorWeeklyScheduleService::dates($weekStart);
        $effectiveDateMin = $weekStart;
        $effectiveDateMax = (string) ($dates[6]['date'] ?? $weekStart);
        $validWeek = $dates !== [] && \App\Services\ChecadorWeeklyScheduleService::monday($weekStart) === $weekStart;
        $errorMessage = '';
        $workers = [];
        $matrix = ['dates' => [], 'workers' => []];
        $templates = [];
        $coverage = 'not_loaded';
        $rosterMessage = '';
        $timezone = '';
        $currentUser = Auth::user();
        if (!$validWeek) {
            $errorMessage = 'Selecciona una semana actual o futura cuyo inicio sea lunes.';
        } elseif ($workplaceId === null) {
            $errorMessage = 'Selecciona un centro autorizado en el selector institucional.';
        } elseif ($currentUser === null) {
            $errorMessage = 'La sesión ya no es válida. Inicia sesión nuevamente.';
        } else {
            try {
                $reader = new \App\Services\ChecadorWeeklyScheduleSqlReadService();
                $timezone = $reader->timezoneForDate($workplaceId, $weekStart) ?? '';
                if (!\App\Services\ChecadorOperationalPeriod::isIanaTimezone($timezone)) {
                    $errorMessage = 'No se pudo confirmar la zona horaria laboral del centro; no se cargó la programación semanal.';
                }
                if ($errorMessage === '' && !\App\Services\ChecadorWeeklyScheduleService::isCurrentOrFutureWeek($weekStart, $timezone)) {
                    $errorMessage = 'Sólo se pueden consultar semanas actuales o futuras según la zona horaria laboral del centro.';
                }
                if ($errorMessage === '') {
                    $today = \App\Services\ChecadorOperationalPeriod::currentLocalDate(
                        $timezone, new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
                    );
                    if ($today !== null) {
                        $effectiveDateMin = max($weekStart,
                            (new \DateTimeImmutable($today, new \DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d'));
                    }
                    $roster = (new \App\Services\ChecadorAttendanceRosterService())
                        ->workersForScheduleWeek($workplaceId, $weekStart, $timezone, $demoMode);
                    $workers = $roster['workers'] ?? [];
                    $coverage = (string) ($roster['status'] ?? 'unknown');
                    $rosterMessage = (string) ($roster['message'] ?? '');
                    if (!in_array($coverage, ['ready', 'demo_fixture'], true)
                        && !($coverage === 'partial' && $workers !== [])) {
                        $errorMessage = $rosterMessage !== '' ? $rosterMessage : 'El padrón no está disponible.';
                    }
                    if ($errorMessage === '') {
                        $scheduleRead = $reader->readWeek($currentUser, $workplaceId, $weekStart, $workers);
                        if (($scheduleRead['ok'] ?? false) !== true) {
                            $errorMessage = match ((string) ($scheduleRead['error']['kind'] ?? '')) {
                                'permission_denied' => 'No tienes autorización para consultar esta programación.',
                                'database_unavailable' => 'No fue posible leer la programación persistida en Checador V2.',
                                'assignment_template_incomplete', 'template_scope_conflict' => 'La programación contiene referencias incompletas o fuera de alcance; se bloqueó su presentación.',
                                default => 'La programación semanal no está disponible.',
                            };
                        } else {
                            $data = $scheduleRead['data'];
                            $templates = $data['templates'];
                            foreach ($workers as &$worker) $worker['schedule_read_status'] = 'available';
                            unset($worker);
                            $matrix = \App\Services\ChecadorWeeklyScheduleService::matrix(
                                $workers, $data['assignments_by_worker'], $data['templates_by_id'], $weekStart
                            );
                        }
                    }
                }
            } catch (\Throwable) {
                $errorMessage = 'La programación semanal no está disponible. No se consultó ninguna fuente legacy como sustituto.';
            }
        }

        $feedback = Session::pull('checador_schedule_feedback', null);
        if (!is_array($feedback) || (int) ($feedback['workplace_id'] ?? 0) !== (int) ($workplaceId ?? 0)
            || (string) ($feedback['week_start'] ?? '') !== $weekStart) $feedback = null;
        $assignableWorkers = \App\Services\ChecadorWeeklyScheduleService::workersWithAnyVerifiedMembership(
            $workers, array_column($dates, 'date')
        );
        // The active M03 write path is Portal3 SQL; demo mode remains read-only.
        $canEditSchedules = $workplaceId !== null && !$demoMode
            && \App\Services\ChecadorWeeklyScheduleSqlWriteService::enabled()
            && (ChecadorAccessService::authorizeScheduling($workplaceId, 'editar')['allowed'] ?? false);
        $canAssign = $canEditSchedules && $assignableWorkers !== [];
        $legalReference = \App\Services\ChecadorLaborPolicy::referenceForDate($weekStart);
        View::render(
            'departamento-operativo/2-recursos-humanos/checador/schedule-week',
            [
                'title' => 'Programación semanal de turnos', 'workplaces' => $workplaces,
                'selectedWorkplaceId' => $workplaceId, 'selectedWorkplaceName' => $workplaceName,
                'weekStart' => $weekStart, 'effectiveDateMin' => $effectiveDateMin,
                'effectiveDateMax' => $effectiveDateMax, 'weekDates' => $matrix['dates'],
                'scheduleRows' => $matrix['workers'], 'habitualScheduleRows' => $matrix['habitual_workers'] ?? [],
                'templates' => $templates, 'templateCatalogAvailable' => $templates !== [],
                'assignableWorkers' => $assignableWorkers, 'timezone' => $timezone, 'coverage' => $coverage,
                'rosterCoverage' => in_array($coverage, ['ready', 'demo_fixture'], true) ? 'ready' : $coverage,
                'matrixCoverage' => $errorMessage !== '' ? 'unavailable'
                    : (in_array($coverage, ['ready', 'demo_fixture'], true) ? 'ready' : 'partial'),
                'rosterMessage' => $rosterMessage, 'errorMessage' => $errorMessage, 'feedback' => $feedback,
                'canAssign' => $canAssign, 'canEditSchedules' => $canEditSchedules, 'demoMode' => $demoMode,
                'legalReference' => $legalReference,
                'dailyLimits' => \App\Services\ChecadorLaborPolicy::generalDailyReferences(),
                'links' => [],
                'scripts' => ['/assets/js/core/module-station-selector.js', '/assets/js/departamento-operativo/2-recursos-humanos/checador.module-station.init.js?v=20261021-1', '/assets/js/departamento-operativo/2-recursos-humanos/checador.schedule-template.init.js', '/assets/js/departamento-operativo/2-recursos-humanos/checador.habitual-schedule.init.js', '/assets/js/departamento-operativo/2-recursos-humanos/checador.schedule-week-ui.init.js'],
                'help' => false, 'moduleStationKey' => ChecadorAccessService::MODULE_KEY,
            ],
            'departamento-operativo'
        );
    }

    /** Read the seven effective weekday occurrences from canonical SQL memberships. */
    public function habitualAssignmentReadback(): void
    {
        $query = Request::query();
        $workerId = self::positiveInteger($query['worker_id'] ?? null);
        $workplaceId = $this->requestWorkplaceId($query);
        $weekStart = is_string($query['week_start'] ?? null) ? trim($query['week_start']) : '';
        $effectiveStart = is_string($query['effective_start_date'] ?? null) ? trim($query['effective_start_date']) : '';
        $weekDates = \App\Services\ChecadorWeeklyScheduleService::dates($weekStart);
        $user = Auth::user();
        $deny = static function (string $code, string $message, int $status): never {
            JsonResponse::send(['ok' => false, 'code' => $code, 'message' => $message], $status, ['Cache-Control' => 'private, no-store']);
        };

        if ($user === null) $deny('unauthenticated', 'La sesión ya no es válida.', 401);
        if ($workerId === null || $workplaceId === null || $weekDates === []
            || \App\Services\ChecadorWeeklyScheduleService::monday($weekStart) !== $weekStart
            || !preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $effectiveStart)
            || $effectiveStart < $weekStart
            || $effectiveStart > (new \DateTimeImmutable($weekStart, new \DateTimeZone('UTC')))->modify('+13 days')->format('Y-m-d')) {
            $deny('invalid_request', 'Revisa el trabajador, la estación, la semana y el inicio de vigencia.', 422);
        }
        if (ChecadorDailyReadService::demoModeEnabled()
            || !(ChecadorAccessService::authorizeScheduling($workplaceId, 'editar')['allowed'] ?? false)) {
            $deny('forbidden', 'No tienes permiso para consultar asignaciones de esta estación.', 403);
        }

        try {
            $reader = new \App\Services\ChecadorWeeklyScheduleSqlReadService();
            $timezone = $reader->timezoneForDate($workplaceId, $effectiveStart) ?? '';
            if (!\App\Services\ChecadorOperationalPeriod::isIanaTimezone($timezone)
                || !\App\Services\ChecadorWeeklyScheduleService::isCurrentOrFutureWeek($weekStart, $timezone)) {
                $deny('workplace_unavailable', 'No se pudo confirmar la estación, la zona horaria o la semana.', 502);
            }
            $attendanceRoster = new \App\Services\ChecadorAttendanceRosterService();
            $targetDates = [];
            foreach ([
                'LUNES' => 'Lunes', 'MARTES' => 'Martes', 'MIERCOLES' => 'Miércoles', 'JUEVES' => 'Jueves',
                'VIERNES' => 'Viernes', 'SABADO' => 'Sábado', 'DOMINGO' => 'Domingo',
            ] as $weekday => $label) {
                $date = \App\Services\ChecadorWeeklyScheduleService::firstWeekdayOnOrAfter($weekday, $effectiveStart);
                if ($date === null) $deny('invalid_request', 'No se pudieron calcular las siete fechas efectivas.', 422);
                $targetDates[] = ['weekday' => $weekday, 'label' => $label, 'date' => $date];
            }

            $weeks = [];
            foreach ($targetDates as $target) {
                $targetMonday = \App\Services\ChecadorWeeklyScheduleService::monday($target['date']);
                if (!isset($weeks[$targetMonday])) {
                    $weekTimezone = $reader->timezoneForDate($workplaceId, $target['date']) ?? '';
                    if (!\App\Services\ChecadorOperationalPeriod::isIanaTimezone($weekTimezone)) {
                        $deny('workplace_unavailable', 'No se confirmó la zona horaria para cada fecha efectiva.', 502);
                    }
                    $roster = $attendanceRoster->workersForScheduleWeek($workplaceId, $targetMonday, $weekTimezone, false);
                    $worker = null;
                    foreach ($roster['workers'] ?? [] as $candidate) {
                        if ((int) ($candidate['worker_id'] ?? 0) === $workerId) { $worker = $candidate; break; }
                    }
                    $weeks[$targetMonday] = ['worker' => $worker, 'read' => null];
                    if (is_array($worker)) {
                        $read = $reader->readWeek($user, $workplaceId, $targetMonday, [$worker]);
                        if (($read['ok'] ?? false) !== true) {
                            $deny('assignment_week_read_unavailable', 'No fue posible consultar las asignaciones persistidas; no se habilitó el formulario.', 502);
                        }
                        $weeks[$targetMonday]['read'] = $read;
                    }
                }
            }

            $verifiedDateSet = [];
            $existingDays = [];
            $existingDaysByDate = [];
            $replacementSources = [];
            foreach ($targetDates as $day) {
                $targetMonday = \App\Services\ChecadorWeeklyScheduleService::monday($day['date']);
                $worker = $weeks[$targetMonday]['worker'] ?? null;
                if (!is_array($worker)) continue;
                $workerVerified = is_array($worker['versioned_verified_dates'] ?? null)
                    ? array_fill_keys(array_filter($worker['versioned_verified_dates'], 'is_string'), true) : [];
                if (!isset($workerVerified[$day['date']])) continue;
                $verifiedDateSet[$day['date']] = true;
                $readRows = $weeks[$targetMonday]['read']['data']['assignments_by_worker'][$workerId] ?? [];
                $dayMatches = [];
                foreach ($readRows as $assignment) {
                    if (($assignment['tipo_asignacion'] ?? '') !== 'HABITUAL'
                        || ($assignment['dia_semana'] ?? '') !== $day['weekday']
                        || (int) ($assignment['es_activo'] ?? 0) !== 1
                        || (string) ($assignment['valido_desde'] ?? '') > $day['date']
                        || (($assignment['valido_hasta'] ?? null) !== null && $day['date'] >= (string) $assignment['valido_hasta'])) continue;
                    $dayMatches[] = $assignment;
                }
                if (count($dayMatches) > 1) {
                    $deny('assignment_conflict', 'Hay más de una asignación habitual vigente en una fecha efectiva; revisa el historial antes de continuar.', 409);
                }
                foreach ($dayMatches as $assignment) {
                    $templateId = $assignment['id_plantilla'] ?? null;
                    $existingDays[(string) $day['weekday']] = $templateId === null ? 'REST' : (string) $templateId;
                    $existingDaysByDate[(string) $day['date']] = $templateId === null ? 'REST' : (string) $templateId;
                    $replacementSources[] = [
                        'id' => (int) ($assignment['id'] ?? 0),
                        'weekday' => (string) $day['weekday'],
                        'weekday_label' => (string) $day['label'],
                        'label' => $templateId === null ? 'Descanso explícito' : 'Turno habitual',
                        'valid_from' => (string) ($assignment['valido_desde'] ?? ''),
                        'valid_to' => $assignment['valido_hasta'] ?? null,
                    ];
                }
            }
            $verifiedDates = array_keys($verifiedDateSet);

            JsonResponse::send(['ok' => true, 'data' => [
                'complete' => true,
                'read_scope' => 'EFFECTIVE_SEVEN_OCCURRENCES',
                'history_complete' => false,
                'effective_start_date' => $effectiveStart,
                'target_dates' => $targetDates,
                'existing_days' => $existingDays,
                'existing_days_by_date' => $existingDaysByDate,
                'verified_dates' => $verifiedDates,
                'habitual_complete' => count($verifiedDateSet) === 7 && count($existingDaysByDate) === 7,
                'replacement_sources' => $replacementSources,
            ]], 200, ['Cache-Control' => 'private, no-store']);
        } catch (\Throwable) {
            $deny('assignment_week_read_unavailable', 'No fue posible completar la consulta semanal; no se habilitó el formulario.', 502);
        }
    }

    /** Create one assignment through the single gated Portal3 SQL writer. */
    /** Execute only the gated Portal3 SQL writer; no HTTP fallback exists. */
    private function handleM03SqlWrite(string $operation, array $input): never
    {
        $workplaceId = $this->requestWorkplaceId($input);
        $rawWorkplaceId = self::positiveInteger($input['workplace_id'] ?? null);
        $weekStart = is_string($input['week_start'] ?? null) ? trim($input['week_start']) : '';
        $user = Auth::user();
        $result = ['ok' => false, 'error' => 'database_write_failed', 'status' => 500];
        if ($user === null) {
            $result = ['ok' => false, 'error' => 'unauthenticated', 'status' => 401];
        } elseif ($workplaceId === null || $rawWorkplaceId !== $workplaceId) {
            $result = ['ok' => false, 'error' => 'permission_denied', 'status' => 403];
        } elseif (!in_array((string) ($input['confirm_action'] ?? ''), ['1', 'on'], true)) {
            $result = ['ok' => false, 'error' => 'confirmation_required', 'status' => 422];
        } elseif (!ChecadorDailyReadService::demoModeEnabled()) {
            $writer = new \App\Services\ChecadorWeeklyScheduleSqlWriteService();
            $result = match ($operation) {
                'create' => (($input['tipo_asignacion'] ?? '') === 'HABITUAL' && is_array($input['schedule'] ?? null))
                    ? $writer->createHabitualPattern($user, $input)
                    : $writer->create($user, $input),
                'close' => $writer->close($user, $input),
                'replace' => $writer->replace($user, $input),
                default => ['ok' => false, 'error' => 'invalid_operation', 'status' => 422],
            };
        } else {
            $result = ['ok' => false, 'error' => 'demo_mode', 'status' => 403];
        }
        $status = (int) ($result['status'] ?? (($result['ok'] ?? false) ? 200 : 422));
        $labels = [
            'permission_denied' => 'No tienes permiso para modificar la programación de esta localidad.',
            'worker_unavailable' => 'El trabajador no está disponible para programación.',
            'membership_unverified' => 'No se confirmó la adscripción del trabajador para toda la vigencia solicitada.',
            'membership_conflict' => 'La adscripción del trabajador es ambigua para la fecha solicitada.',
            'assignment_conflict' => 'Ya existe una asignación incompatible para ese periodo.',
            'template_not_allowed' => 'El turno no pertenece al catálogo autorizado de esta localidad.',
            'invalid_template_segments' => 'El turno no tiene segmentos laborales válidos persistidos.',
            'stale_assignment' => 'La asignación cambió desde que abriste el formulario. Actualiza la semana e inténtalo de nuevo.',
            'invalid_close_date' => 'La fecha de cierre no coincide con la vigencia de la asignación.',
            'invalid_validity' => 'La vigencia solicitada no es válida.',
            'invalid_effective_date' => 'La fecha de inicio debe ser futura y las siete ocurrencias deben quedar dentro del rango consultado.',
            'idempotency_conflict' => 'La clave de solicitud ya se usó con otros datos; vuelve a cargar el formulario.',
            'concurrent_write_busy' => 'Hay otro cambio de programación en curso. Actualiza la página antes de reintentar.',
            'readback_failed', 'readback_mismatch', 'audit_readback_failed' => 'La operación no se confirmó mediante lectura posterior; no se declara guardada.',
            'database_write_failed' => 'No fue posible guardar la programación en MariaDB; no se declara guardada.',
            'demo_mode' => 'La demostración es de consulta; no guarda asignaciones.',
            'confirmation_required' => 'Confirma la operación antes de guardar.',
        ];
        $error = (string) ($result['error'] ?? 'database_write_failed');
        $message = ($result['ok'] ?? false)
            ? (($result['replayed'] ?? false) ? 'Cambio ya confirmado; se recuperó la asignación persistida.' : 'Cambio confirmado mediante lectura posterior de MariaDB.')
            : ($labels[$error] ?? 'No fue posible confirmar el cambio de programación.');
        if (Request::expectsJson()) {
            JsonResponse::send([
                'ok' => (bool) ($result['ok'] ?? false), 'code' => $error, 'message' => $message,
                'workplace_id' => $workplaceId, 'assignment' => $result['assignment'] ?? null,
                'assignments' => $result['assignments'] ?? null,
                'replayed' => (bool) ($result['replayed'] ?? false),
            ], $status, ['Cache-Control' => 'private, no-store']);
        }
        Session::set('checador_schedule_feedback', [
            'type' => ($result['ok'] ?? false) ? 'success' : 'warning', 'message' => $message,
            'workplace_id' => (int) ($workplaceId ?? 0), 'week_start' => $weekStart,
        ]);
        self::redirect(self::scheduleWeekHref($weekStart !== '' ? ['week_start' => $weekStart] : []));
    }

    public function createScheduleAssignment(): void
    {
        $input = Request::all();
        $this->handleM03SqlWrite('create', $input);
    }

    /** Close an existing assignment through the single gated Portal3 SQL writer. */
    public function closeScheduleAssignment(): void
    {
        $input = Request::all();
        $this->handleM03SqlWrite('close', $input);
    }

    /** Atomically replace an assignment and confirm both rows through SQL. */
    public function replaceScheduleAssignment(): void
    {
        $input = Request::all();
        $this->handleM03SqlWrite('replace', $input);
    }

    /** Create one immutable version-1 template through the authorized V2 contract. */
    private static function scheduleHistoryErrorMessage(?string $kind): string
    {
        return match ($kind) {
            'demo_history_unavailable' => 'El historial no está disponible en esta consulta.',
            'bad_request', 'worker_scope_mismatch' => 'Revisa el trabajador y la fecha seleccionados.',
            'api_authentication', 'api_authorization', 'schedule_history_unavailable', 'api_unavailable'
                => 'No fue posible consultar el historial para esta estación.',
            default => $kind === null ? '' : 'No fue posible consultar el historial para esta estación.',
        };
    }

    /** Read-only MVP report of raw V2 punch events. */
    public function punchReport(): void
    {
        $this->normalizeLegacyWorkplaceUrl();
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Checador', '/departamento-operativo/recursos-humanos/checador');
        Breadcrumb::add('Reporte de marcaciones', '');

        $query = Request::query();
        $requestedWorkplace = null;
        $fromDate = is_string($query['from_date'] ?? null) ? trim($query['from_date']) : '';
        $toDate = is_string($query['to_date'] ?? null) ? trim($query['to_date']) : '';
        $workerIdInvalidType = array_key_exists('worker_id', $query) && !is_string($query['worker_id']);
        $workerIdInput = is_string($query['worker_id'] ?? null) ? trim($query['worker_id']) : '';
        $workerId = $workerIdInput === '' ? null : self::positiveInteger($workerIdInput);
        $captureMethodInput = is_string($query['capture_method'] ?? null) ? trim($query['capture_method']) : '';
        $processingStatusInput = is_string($query['processing_status'] ?? null) ? trim($query['processing_status']) : '';
        $sourceWorkDateCandidate = is_string($query['source_work_date'] ?? null) ? trim($query['source_work_date']) : '';
        $sourceWorkerCandidate = self::positiveInteger($query['source_worker_id'] ?? null);
        $sort = ($query['dt_order_direction'] ?? $query['sort'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $tableSearch = is_string($query['dt_search'] ?? null) ? trim($query['dt_search']) : '';
        $tableOrderColumn = self::nonNegativeInteger($query['dt_order_column'] ?? 1) ?? 1;
        if (!in_array($tableOrderColumn, [1, 2, 3, 4, 5, 6], true)) {
            $tableOrderColumn = 1;
        }
        $exportCsv = ($query['export'] ?? '') === 'csv';

        // Resolve the full server-authorized list first so the user can change
        // stations without ever widening the locally enforced scope.
        $access = ChecadorAccessService::authorize(null);
        if (!($access['allowed'] ?? false)) {
            self::renderAccessDenied((string) ($access['reason'] ?? 'authorization_lookup_failed'), $requestedWorkplace);
            return;
        }
        $workplaces = $access['workplaces'];
        $requestedWorkplace = $this->sessionWorkplaceId($access);
        $sourceContextValid = $requestedWorkplace !== null && self::isCivilDate($sourceWorkDateCandidate);
        $sourceWorkplaceId = $sourceContextValid ? $requestedWorkplace : null;
        $sourceWorkDate = $sourceContextValid ? $sourceWorkDateCandidate : '';
        $sourceWorkerId = $sourceContextValid ? $sourceWorkerCandidate : null;
        $selectedWorkplaceForView = $requestedWorkplace;
        if ($selectedWorkplaceForView !== null) {
            $selectedAccess = ChecadorAccessService::authorize($selectedWorkplaceForView);
            if (!($selectedAccess['allowed'] ?? false)) {
                self::renderAccessDenied((string) ($selectedAccess['reason'] ?? 'requested_station_out_of_scope'), $selectedWorkplaceForView);
                return;
            }
            if (!ModuleStationService::setChecadorWorkplaceContext($selectedWorkplaceForView)) {
                self::renderAccessDenied('requested_station_out_of_scope', $selectedWorkplaceForView);
                return;
            }
        }
        $requestedWorkplace = $selectedWorkplaceForView;
        if ($sourceWorkplaceId !== null && $selectedWorkplaceForView !== $sourceWorkplaceId) {
            $sourceWorkplaceId = null;
            $sourceWorkDate = '';
            $sourceWorkerId = null;
        }
        $selectedWorkerForView = array_key_exists('worker_id', $query)
            ? $workerIdInput
            : ($sourceWorkerId === null ? '' : (string) $sourceWorkerId);
        if (!array_key_exists('worker_id', $query) && $sourceWorkerId !== null) {
            $workerId = $sourceWorkerId;
            $workerIdInput = (string) $sourceWorkerId;
        }

        $nowUtc = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $periodInput = is_string($query['period'] ?? null) ? trim($query['period']) : '';
        $periodYearInput = self::positiveInteger($query['year'] ?? null);
        $periodMonthInput = self::positiveInteger($query['month'] ?? null);
        $periodError = false;
        $periodPreset = 'custom';
        $periodLabel = 'Rango personalizado (UTC)';
        $periodYear = (int) $nowUtc->format('Y');
        $periodMonth = (int) $nowUtc->format('n');
        $hasExplicitDateRange = $fromDate !== '' || $toDate !== '';
        if (!$hasExplicitDateRange && $selectedWorkplaceForView !== null) {
            $currentUserForPeriod = Auth::user();
            $periodPreset = $periodInput !== ''
                ? $periodInput
                : ((int) ($currentUserForPeriod->id_puesto ?? 0) === 13 ? 'month' : 'today');
            if (!in_array($periodPreset, ['today', 'last7', 'month', 'year'], true)) {
                $periodError = true;
            } else {
                $periodRange = ChecadorOperationalPeriod::reportRange(
                    $periodPreset,
                    $nowUtc,
                    $periodYearInput,
                    $periodMonthInput
                );
                if ($periodRange === null) {
                    $periodError = true;
                } else {
                    $fromDate = $periodRange['from_date'];
                    $toDate = $periodRange['to_date'];
                    $periodLabel = $periodRange['label'];
                    $periodYear = $periodRange['year'];
                    $periodMonth = $periodRange['month'];
                }
            }
        } elseif ($hasExplicitDateRange) {
            $periodPreset = 'custom';
        }
        $periodUiPreset = $periodPreset;
        if ($hasExplicitDateRange && $periodInput === 'year') {
            $specificYear = self::positiveInteger($query['year'] ?? null);
            $specificMonth = self::positiveInteger($query['month'] ?? null);
            if ($specificYear !== null && $specificMonth !== null && $specificMonth <= 12) {
                $periodUiPreset = 'year';
                $periodYear = $specificYear;
                $periodMonth = $specificMonth;
            }
        }

        $yearSegmentLinks = [];
        if (!$periodError && $selectedWorkplaceForView !== null && $periodPreset === 'year') {
            $currentMonthInYear = (int) $nowUtc->format('n');
            $monthLabels = [1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun',
                7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'];
            for ($monthNumber = 1; $monthNumber <= $currentMonthInYear; $monthNumber++) {
                $yearSegmentLinks[] = [
                    'month' => $monthNumber,
                    'label' => $monthLabels[$monthNumber],
                    'url' => '/departamento-operativo/recursos-humanos/checador/reporte?'
                        . http_build_query([
                            'period' => 'year',
                            'year' => $periodYear,
                            'month' => $monthNumber,
                        ]),
                ];
            }
        }

        $state = 'idle';
        $stateMessage = '';
        $selectedWorkerOption = null;
        $selectedWorkerError = $workerIdInvalidType ? 'bad_request' : null;
        $events = [];
        $pagination = null;
        $selectedWorkplaceName = '';
        foreach ($workplaces as $workplace) {
            if ((int) ($workplace['id'] ?? 0) === $selectedWorkplaceForView) {
                $selectedWorkplaceName = (string) ($workplace['nombre'] ?? '');
                break;
            }
        }

        if ($selectedWorkerForView !== '') {
            $selectedWorkerId = self::positiveInteger($selectedWorkerForView);
            if ($selectedWorkerId === null || $requestedWorkplace === null) {
                $selectedWorkerError = 'worker_scope_mismatch';
            } else {
                $roster = self::punchReportAuthorizedRoster($requestedWorkplace);
                $selectedWorkerLookup = ($roster['ok'] ?? false)
                    ? (new ChecadorRawPunchReadService())->findReportWorker(
                        $requestedWorkplace,
                        $roster['worker_ids'],
                        $selectedWorkerId
                    )
                    : ['ok' => false, 'error' => ['kind' => 'database_unavailable']];
                if (($selectedWorkerLookup['ok'] ?? false) === true) {
                    $selectedWorkerOption = $selectedWorkerLookup['worker'];
                } else {
                    $selectedWorkerError = (string) ($selectedWorkerLookup['error']['kind'] ?? 'database_unavailable');
                }
            }
        }

        // A daily-screen source context may preselect station/worker, but only
        // explicit UTC date criteria or export initiate a raw SQL read.
        $hasAnyFilter = $fromDate !== '' || $toDate !== '' || $workerIdInput !== ''
            || $captureMethodInput !== '' || $processingStatusInput !== '' || $exportCsv || $periodError;
        if ($selectedWorkerError !== null) {
            $state = 'error';
            $stateMessage = $selectedWorkerError === 'worker_scope_mismatch'
                ? 'El colaborador seleccionado no está disponible en esta localidad.'
                : 'No se pudo confirmar el colaborador seleccionado. Inténtalo nuevamente.';
        } elseif ($hasAnyFilter) {
            if ($periodError) {
                $state = 'error';
                $stateMessage = 'El periodo solicitado no es válido. El año en curso se consulta por segmentos mensuales y no permite seleccionar meses futuros.';
            } elseif ($requestedWorkplace === null
                || !self::isCivilDate($fromDate)
                || !self::isCivilDate($toDate)
                || $fromDate > $toDate
                || ($workerIdInput !== '' && $workerId === null)
                || ($captureMethodInput !== '' && !in_array($captureMethodInput, ChecadorRawPunchReadService::CAPTURE_METHOD_FILTERS, true))
                || ($processingStatusInput !== '' && !in_array($processingStatusInput, ChecadorRawPunchReadService::PROCESSING_STATUS_FILTERS, true))) {
                $state = 'error';
                $stateMessage = 'Selecciona una estación autorizada, un rango de fechas válido y filtros reconocidos.';
            } else {
                $user = Auth::user();
                if (!$user) {
                    $state = 'error';
                    $stateMessage = 'La sesión ya no es válida. Inicia sesión nuevamente.';
                } else {
                    try {
                        $utc = new \DateTimeZone('UTC');
                        $fromBoundary = \DateTimeImmutable::createFromFormat('!Y-m-d', $fromDate, $utc);
                        $toBoundary = \DateTimeImmutable::createFromFormat('!Y-m-d', $toDate, $utc);
                        if ($fromBoundary === false || $toBoundary === false) {
                            $state = 'error';
                            $stateMessage = 'El rango de fechas UTC no es válido.';
                        } else {
                            $fromUtc = $fromBoundary->format('Y-m-d H:i:s');
                            $toUtc = $toBoundary->modify('+1 day')->format('Y-m-d H:i:s');
                            if (self::utcRangeSeconds($fromUtc, $toUtc) > 2678400) {
                                $state = 'error';
                                $stateMessage = 'El rango excede el límite de 31 días UTC.';
                            } else {
                                if ($exportCsv) {
                                    // Re-resolve current scope and effective download grant at export time.
                                    $downloadAccess = ChecadorAccessService::authorize($requestedWorkplace);
                                    if (!($downloadAccess['allowed'] ?? false)) {
                                        self::renderAccessDenied((string) ($downloadAccess['reason'] ?? 'requested_station_out_of_scope'), $requestedWorkplace);
                                        return;
                                    }
                                    if (empty($downloadAccess['descargar'])) {
                                        self::renderAccessDenied('download_denied', $requestedWorkplace);
                                        return;
                                    }
                                }

                                if (!$exportCsv) {
                                    // The DataTable reads through the authorized JSON endpoint only.
                                    $state = 'success';
                                } else {
                                    $reader = new ChecadorRawPunchReadService();
                                    $result = $reader->readDataTablePage(
                                        $requestedWorkplace,
                                        $fromUtc,
                                        $toUtc,
                                        $workerId,
                                        0,
                                        100,
                                        $tableSearch,
                                        $tableOrderColumn,
                                        $sort,
                                        $selectedWorkplaceName,
                                        $captureMethodInput,
                                        $processingStatusInput
                                    );
                                    if (!($result['ok'] ?? false)) {
                                        $kind = (string) ($result['error']['kind'] ?? 'database_unavailable');
                                        if ($kind === 'station_scope_mismatch') {
                                            self::renderAccessDenied($kind, $requestedWorkplace);
                                            return;
                                        }
                                        [$state, $stateMessage] = self::punchReportError($kind);
                                    } elseif (($result['records_filtered'] ?? 0) > ChecadorRawPunchReadService::MAX_EXPORT_RECORDS) {
                                        $state = 'error';
                                        $stateMessage = 'El archivo supera el límite seguro de 100,000 filas. Reduce el rango o ajusta la búsqueda.';
                                    } else {
                                        self::streamPunchReportDataTableCsv(
                                            $reader,
                                            $result,
                                            $requestedWorkplace,
                                            $selectedWorkplaceName,
                                            $fromUtc,
                                            $toUtc,
                                            $workerId,
                                            $tableSearch,
                                            $tableOrderColumn,
                                            $sort,
                                            $captureMethodInput,
                                            $processingStatusInput
                                        );
                                        return;
                                    }
                                }
                            }
                        }
                    } catch (\Throwable) {
                        $state = 'error';
                        $stateMessage = 'La consulta de marcaciones no está disponible; no se puede confirmar si hay registros.';
                    }
                }
            }
        }

        View::render(
            'departamento-operativo/2-recursos-humanos/checador/punch-report',
            [
                'title' => 'Reporte de marcaciones',
            'demoMode' => false,
                'moduleStationKey' => ChecadorAccessService::MODULE_KEY,
                'workplaces' => $workplaces,
                'selectedWorkplaceId' => $selectedWorkplaceForView,
                'selectedWorkplaceName' => $selectedWorkplaceName,
                'fromDate' => $fromDate,
                'toDate' => $toDate,
                'workerId' => $selectedWorkerForView,
                'selectedWorkerOption' => $selectedWorkerOption,
                'captureMethod' => $captureMethodInput,
                'processingStatus' => $processingStatusInput,
                'captureMethodOptions' => array_combine(
                    ChecadorRawPunchReadService::CAPTURE_METHOD_FILTERS,
                    array_map([ChecadorPresentationFormatter::class, 'captureMethodLabel'], ChecadorRawPunchReadService::CAPTURE_METHOD_FILTERS)
                ),
                'processingStatusOptions' => array_combine(
                    ChecadorRawPunchReadService::PROCESSING_STATUS_FILTERS,
                    array_map([ChecadorPresentationFormatter::class, 'processingStatusLabel'], ChecadorRawPunchReadService::PROCESSING_STATUS_FILTERS)
                ),
                'sourceWorkplaceId' => $sourceWorkplaceId,
                'sourceWorkDate' => $sourceWorkDate,
                'sourceWorkerId' => $sourceWorkerId,
                'selectedPerPage' => 25,
                'sort' => $sort,
                'tableSearch' => $tableSearch,
                'tableOrderColumn' => $tableOrderColumn,
                'periodPreset' => $periodPreset,
                'periodUiPreset' => $periodUiPreset,
                'periodLabel' => $periodLabel,
                'periodYear' => $periodYear,
                'periodMonth' => $periodMonth,
                'yearSegmentLinks' => $yearSegmentLinks,
                'events' => $events,
                'pagination' => $pagination,
                'canDownload' => !empty($access['descargar']),
                'state' => $state,
                'stateMessage' => $stateMessage,
                'links' => [
                    '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                    '/assets/libs/select2/dist/css/select2.min.css',
                    '/assets/css/checador-punch-report.css?v=20261106-1',
                ],
                'scripts' => [
                    '/assets/js/vendor.min.js',
                    '/assets/libs/select2/dist/js/select2.full.min.js',
                    '/assets/js/core/module-station-selector.js',
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.module-station.init.js?v=20261113-2',
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.filters.init.js?v=20261116-6',
                    '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.datatable.init.js?v=20261106-6',
                ],
                'help' => false,
            ],
            'departamento-operativo'
        );
    }

    /** Search the authorized active roster and historical punch identities by worker name. */
    public function punchReportWorkers(): void
    {
        if (!Auth::check() || Auth::user() === null) {
            JsonResponse::send(['results' => [], 'pagination' => ['more' => false]], 401, ['Cache-Control' => 'private, no-store']);
        }
        $access = ChecadorAccessService::authorize(null);
        if (empty($access['allowed'])) {
            JsonResponse::send(['results' => [], 'pagination' => ['more' => false]], 403, ['Cache-Control' => 'private, no-store']);
        }
        $context = ModuleStationService::getContext(ChecadorAccessService::MODULE_KEY);
        $localityId = self::positiveInteger($context['id_depto'] ?? $context['id_estacion'] ?? null);
        if ($localityId === null || empty(ChecadorAccessService::authorize($localityId)['allowed'])) {
            JsonResponse::send(['results' => [], 'pagination' => ['more' => false]], 403, ['Cache-Control' => 'private, no-store']);
        }

        $query = Request::query();
        $term = is_string($query['term'] ?? null) ? trim($query['term']) : '';
        $page = self::positiveInteger($query['page'] ?? 1) ?? 1;
        $workerId = array_key_exists('worker_id', $query) ? self::positiveInteger($query['worker_id']) : null;
        $roster = self::punchReportAuthorizedRoster($localityId);
        if (!($roster['ok'] ?? false)) {
            JsonResponse::send(['results' => [], 'pagination' => ['more' => false]], 503, ['Cache-Control' => 'private, no-store']);
        }
        $reader = new ChecadorRawPunchReadService();
        if (array_key_exists('worker_id', $query)) {
            if ($workerId === null) {
                JsonResponse::send(['results' => [], 'pagination' => ['more' => false]], 422, ['Cache-Control' => 'private, no-store']);
            }
            $found = $reader->findReportWorker($localityId, $roster['worker_ids'], $workerId);
            if (!($found['ok'] ?? false)) {
                $kind = (string) ($found['error']['kind'] ?? 'database_unavailable');
                $status = $kind === 'worker_scope_mismatch' ? 403 : 503;
                JsonResponse::send(['results' => [], 'pagination' => ['more' => false]], $status, ['Cache-Control' => 'private, no-store']);
            }
            JsonResponse::send([
                'results' => [['id' => $found['worker']['id'], 'text' => $found['worker']['text']]],
                'pagination' => ['more' => false],
            ], 200, ['Cache-Control' => 'private, no-store']);
        }
        $result = $reader->searchReportWorkers($localityId, $roster['worker_ids'], $term, $page);
        if (!($result['ok'] ?? false)) {
            $kind = (string) ($result['error']['kind'] ?? 'database_unavailable');
            $status = $kind === 'bad_request' ? 422 : 503;
            JsonResponse::send(['results' => [], 'pagination' => ['more' => false]], $status, ['Cache-Control' => 'private, no-store']);
        }
        JsonResponse::send([
            'results' => $result['workers'],
            'pagination' => ['more' => (bool) $result['more']],
        ], 200, ['Cache-Control' => 'private, no-store']);
    }

    /** DataTables endpoint; each request re-resolves the user and station scope. */
    public function punchReportData(): void
    {
        $query = Request::query();
        $draw = self::nonNegativeInteger($query['draw'] ?? null);
        if ($draw === null) {
            self::punchReportJsonError(422, 'La solicitud de la tabla no es válida.', 0);
        }

        $submittedWorkplace = array_key_exists('workplace_id', $query)
            ? self::positiveInteger($query['workplace_id'])
            : null;
        $fromDate = is_string($query['from_date'] ?? null) ? trim($query['from_date']) : '';
        $toDate = is_string($query['to_date'] ?? null) ? trim($query['to_date']) : '';
        $workerInput = is_string($query['worker_id'] ?? null) ? trim($query['worker_id']) : '';
        $workerId = $workerInput === '' ? null : self::positiveInteger($workerInput);
        $captureMethod = is_string($query['capture_method'] ?? null) ? trim($query['capture_method']) : '';
        $processingStatus = is_string($query['processing_status'] ?? null) ? trim($query['processing_status']) : '';
        $start = self::nonNegativeInteger($query['start'] ?? null);
        $length = self::positiveInteger($query['length'] ?? null);
        $searchInput = $query['search'] ?? [];
        $search = is_array($searchInput) && is_string($searchInput['value'] ?? null)
            ? trim($searchInput['value'])
            : '';
        $regexSearch = is_array($searchInput)
            && (($searchInput['regex'] ?? false) === true || ($searchInput['regex'] ?? null) === 'true');
        $orderInput = $query['order'] ?? [];
        $order = is_array($orderInput) ? ($orderInput[0] ?? null) : null;
        $orderColumn = is_array($order) ? self::nonNegativeInteger($order['column'] ?? null) : 1;
        $direction = is_array($order) ? ($order['dir'] ?? null) : ($query['dt_order_direction'] ?? 'asc');

        if (!Auth::check() || Auth::user() === null) {
            self::punchReportJsonError(401, 'La sesión no está disponible. Inicia sesión nuevamente.', $draw);
        }
        $access = ChecadorAccessService::authorize(null);
        if (!($access['allowed'] ?? false)) {
            self::punchReportJsonError(403, 'No tienes acceso al reporte de marcaciones.', $draw);
        }
        $context = ModuleStationService::getContext(ChecadorAccessService::MODULE_KEY);
        $requestedWorkplace = self::positiveInteger($context['id_depto'] ?? $context['id_estacion'] ?? null);
        if ($requestedWorkplace === null
            || (array_key_exists('workplace_id', $query) && $submittedWorkplace !== $requestedWorkplace)) {
            self::punchReportJsonError(403, 'La estación no coincide con el contexto autorizado de la sesión.', $draw);
        }
        $selectedWorkplace = null;
        foreach ($access['workplaces'] ?? [] as $workplace) {
            if ((int) ($workplace['id'] ?? 0) === $requestedWorkplace) {
                $selectedWorkplace = $workplace;
                break;
            }
        }
        if ($requestedWorkplace === null || $selectedWorkplace === null
            || empty(ChecadorAccessService::authorize($requestedWorkplace)['allowed'])) {
            self::punchReportJsonError(403, 'La estación solicitada no está autorizada.', $draw);
        }

        if (!self::isCivilDate($fromDate)
            || !self::isCivilDate($toDate)
            || $fromDate > $toDate
            || (array_key_exists('worker_id', $query) && !is_string($query['worker_id']))
            || ($workerInput !== '' && $workerId === null)
            || $start === null
            || $length === null
            || !in_array($length, ChecadorRawPunchReadService::DATATABLE_PAGE_SIZES, true)
            || (preg_match_all('/./us', $search, $unused) === false)
            || preg_match_all('/./us', $search, $unused) > 100
            || $regexSearch
            || $orderColumn === null
            || !in_array($orderColumn, [1, 2, 3, 4, 5, 6], true)
            || ($captureMethod !== '' && !in_array($captureMethod, ChecadorRawPunchReadService::CAPTURE_METHOD_FILTERS, true))
            || ($processingStatus !== '' && !in_array($processingStatus, ChecadorRawPunchReadService::PROCESSING_STATUS_FILTERS, true))
            || !in_array($direction, ['asc', 'desc'], true)) {
            self::punchReportJsonError(422, 'Revisa la estación, el rango de fechas y los filtros de la tabla.', $draw);
        }

        $utc = new \DateTimeZone('UTC');
        $fromBoundary = \DateTimeImmutable::createFromFormat('!Y-m-d', $fromDate, $utc);
        $toBoundary = \DateTimeImmutable::createFromFormat('!Y-m-d', $toDate, $utc);
        if ($fromBoundary === false || $toBoundary === false) {
            self::punchReportJsonError(422, 'El rango de fechas UTC no es válido.', $draw);
        }
        $fromUtc = $fromBoundary->format('Y-m-d H:i:s');
        $toUtc = $toBoundary->modify('+1 day')->format('Y-m-d H:i:s');
        if (self::utcRangeSeconds($fromUtc, $toUtc) > 2678400) {
            self::punchReportJsonError(422, 'El rango excede el límite de 31 días UTC.', $draw);
        }

        if ($workerId !== null) {
            $roster = self::punchReportAuthorizedRoster($requestedWorkplace);
            $workerScope = ($roster['ok'] ?? false)
                ? (new ChecadorRawPunchReadService())->findReportWorker(
                    $requestedWorkplace,
                    $roster['worker_ids'],
                    $workerId
                )
                : ['ok' => false, 'error' => ['kind' => 'database_unavailable']];
            if (!($workerScope['ok'] ?? false)) {
                $kind = (string) ($workerScope['error']['kind'] ?? 'database_unavailable');
                self::punchReportJsonError(
                    $kind === 'worker_scope_mismatch' ? 403 : 503,
                    $kind === 'worker_scope_mismatch'
                        ? 'El colaborador seleccionado no está disponible en esta localidad.'
                        : 'No se pudo confirmar el colaborador seleccionado.',
                    $draw
                );
            }
        }

        $result = (new ChecadorRawPunchReadService())->readDataTablePage(
            $requestedWorkplace,
            $fromUtc,
            $toUtc,
            $workerId,
            $start,
            $length,
            $search,
            $orderColumn,
            $direction,
            (string) ($selectedWorkplace['nombre'] ?? ''),
            $captureMethod,
            $processingStatus
        );
        if (!($result['ok'] ?? false)) {
            $kind = (string) ($result['error']['kind'] ?? 'database_unavailable');
            $status = match ($kind) {
                'bad_request' => 422,
                'station_scope_mismatch' => 403,
                default => 503,
            };
            $message = $kind === 'database_unavailable'
                ? 'La consulta no está disponible; no se interpreta como ausencia de marcaciones.'
                : ($status === 403 ? 'La estación solicitada no está autorizada.' : 'Revisa los filtros de la tabla.');
            self::punchReportJsonError($status, $message, $draw);
        }

        $workplaceName = (string) ($selectedWorkplace['nombre'] ?? 'Estación no disponible');
        $rows = [];
        foreach (self::preparePunchReportRows($result['events']) as $event) {
            $resolvedWorkerId = $event['id_personal_resuelto'] ?? null;
            $occurredUtc = $event['ocurrido_at_utc'] === null ? null : (string) $event['ocurrido_at_utc'];
            $receivedUtc = (string) ($event['recibido_at_utc'] ?? '');
            $rows[] = [
                'station' => $workplaceName,
                'received_at_utc' => $receivedUtc,
                'event_time_display' => $occurredUtc !== null
                    ? $occurredUtc . ' UTC'
                    : ($receivedUtc !== '' ? $receivedUtc . ' UTC' : 'No disponible'),
                'occurrence_available' => $occurredUtc !== null,
                'collaborator' => (string) ($event['collaborator_display'] ?? ChecadorPresentationFormatter::collaboratorLabel($resolvedWorkerId)),
                'capture_method' => ChecadorPresentationFormatter::captureMethodLabel($event['metodo_captura'] ?? null),
                'processing_status' => ChecadorPresentationFormatter::processingStatusLabel($event['processing_status'] ?? null),
                'processing_status_class' => ChecadorPresentationFormatter::processingStatusBadgeClass($event['processing_status'] ?? null),
                'event_uuid' => (string) ($event['event_uuid'] ?? ''),
                'device' => isset($event['id_dispositivo']) ? 'Dispositivo ' . (int) $event['id_dispositivo'] : 'No disponible',
                'source_timestamp_raw' => (string) ($event['source_timestamp_raw'] ?? ''),
                'source_timezone_reported' => (string) ($event['source_timezone_reported'] ?? ''),
                'ocurrido_at_utc' => $occurredUtc,
                'recibido_at_utc_detail' => (string) ($event['recibido_at_utc'] ?? ''),
                'processing_explanation' => ChecadorPresentationFormatter::processingStatusExplanation($event['processing_status'] ?? null),
                'integrity' => ChecadorPresentationFormatter::integrityLabel(),
            ];
        }

        JsonResponse::send([
            'draw' => $draw,
            'recordsTotal' => (int) $result['records_total'],
            'recordsFiltered' => (int) $result['records_filtered'],
            'data' => $rows,
        ]);
    }

    /**
     * Resolve current employees from the canonical attendance roster. The
     * returned worker IDs are canonical op_rh_personal.id values and are
     * re-evaluated on each Select2, report, and CSV request.
     *
     * @return array{ok:bool,worker_ids?:list<int>,status?:string}
     */
    private static function punchReportAuthorizedRoster(int $localityId): array
    {
        $timezone = trim((string) ($_ENV['APP_TIMEZONE'] ?? getenv('APP_TIMEZONE') ?: ''));
        if (!ChecadorOperationalPeriod::isIanaTimezone($timezone)) {
            $timezone = 'UTC';
        }
        $today = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone($timezone))
            ->format('Y-m-d');
        $roster = (new \App\Services\ChecadorAttendanceRosterService())
            ->workersForAttendanceFilter($localityId, $today, null, false);
        $status = (string) ($roster['status'] ?? 'unavailable');
        if (!in_array($status, ['ready', 'current_roster_only'], true)) {
            return ['ok' => false, 'status' => $status];
        }
        $workerIds = [];
        foreach ($roster['workers'] ?? [] as $worker) {
            $workerId = (int) ($worker['worker_id'] ?? 0);
            if ($workerId > 0) {
                $workerIds[] = $workerId;
            }
        }
        return ['ok' => true, 'worker_ids' => array_values(array_unique($workerIds)), 'status' => $status];
    }

    /** Informational, strictly read-only comparison with payroll period references. */
    public function payrollComparison(): void
    {
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Checador', '/departamento-operativo/recursos-humanos/checador');
        Breadcrumb::add('Comparación informativa con Nómina', '');

        $query = Request::query();
        $hasExplicitWorkplace = array_key_exists('workplace_id', $query);
        $workplaceInput = $query['workplace_id'] ?? null;
        $workplaceId = self::positiveInteger($workplaceInput);
        $workplaceInvalid = $hasExplicitWorkplace && $workplaceInput !== '' && $workplaceId === null;
        $comparisonReader = new ChecadorPayrollComparisonReadService();
        $combinedScope = $workplaceInvalid
            ? ['allowed' => false, 'reason' => 'invalid_locality']
            : $comparisonReader->authorization(null);
        if (empty($combinedScope['allowed'])) {
            http_response_code(403);
            View::renderPartial('errors/403');
            return;
        }
        if (!$hasExplicitWorkplace) {
            $context = ModuleStationService::getContext(ChecadorAccessService::MODULE_KEY);
            $workplaceId = self::positiveInteger($context['id_depto'] ?? $context['id_estacion'] ?? null);
        }

        $authorization = $workplaceInvalid
            ? ['allowed' => false, 'reason' => 'invalid_locality']
            : ($workplaceId === null ? $combinedScope : $comparisonReader->authorization($workplaceId));
        if (empty($authorization['allowed'])) {
            http_response_code(403);
            View::renderPartial('errors/403');
            return;
        }

        $periodType = $workplaceId !== null && RecibosNominaService::esSemanal($workplaceId) ? 'week' : 'fortnight';
        $yearFilter = is_string($query['year'] ?? null) && preg_match('/\\A20[0-9]{2}\\z/', $query['year'])
            ? (int) $query['year'] : null;
        $numberFilter = self::positiveInteger($query['period_number'] ?? null);
        $rosterPeriod = $workplaceId !== null && $yearFilter !== null && $numberFilter !== null
            ? ChecadorPayrollComparisonReadService::resolvePeriod($workplaceId, $yearFilter, $periodType, $numberFilter)
            : null;
        $rosterOptions = $workplaceId === null
            ? ['status' => 'unavailable', 'workers' => [], 'message' => 'Selecciona una localidad autorizada.']
            : $comparisonReader->workerOptions(
                $workplaceId,
                $rosterPeriod['start_date'] ?? null,
                $rosterPeriod['end_date'] ?? null
            );

        $result = null;
        $errorMessage = '';
        if (($query['load_roster'] ?? null) === '1') {
            if ($yearFilter === null || $numberFilter === null) {
                $errorMessage = 'Indica año y periodo para cargar el padrón histórico correspondiente.';
            } elseif ($rosterPeriod === null) {
                $errorMessage = 'El año o periodo no corresponde a la regla semanal o quincenal de esta localidad.';
            }
        }
        $hasCompareRequest = ($query['compare'] ?? null) === '1';
        if ($hasCompareRequest) {
            $result = $comparisonReader->compare(array_merge($query, ['workplace_id' => $workplaceId]));
            if (empty($result['ok'])) {
                $errorMessage = match ((string) ($result['error']['kind'] ?? '')) {
                    'filters_required' => 'Selecciona trabajador, año y periodo para consultar.',
                    'invalid_period' => 'El periodo no corresponde al tipo configurado para la localidad o está fuera de rango.',
                    'worker_not_available' => 'El trabajador no está disponible en la localidad autorizada.',
                    'forbidden' => 'No se pudo autorizar la consulta simultáneamente para Checador y Nómina.',
                    'development_database_unavailable' => 'La lectura está deshabilitada porque el destino no pudo verificarse como bd_admongas local de desarrollo.',
                    default => 'No fue posible validar la consulta solicitada.',
                };
            }
        }

        $workplaceMap = [];
        foreach ($authorization['workplaces'] ?? [] as $workplace) {
            $workplaceMap[(int) $workplace['id']] = (string) $workplace['nombre'];
        }
        $selectedWorkplaceName = $workplaceId !== null ? ($workplaceMap[$workplaceId] ?? '') : '';

        View::render(
            'departamento-operativo/2-recursos-humanos/checador/payroll-comparison',
            [
                'title' => 'Comparación informativa de asistencia y Nómina',
                'moduleStationKey' => ChecadorAccessService::MODULE_KEY,
                'workplaces' => $authorization['workplaces'] ?? [],
                'selectedWorkplaceId' => $workplaceId,
                'selectedWorkplaceName' => $selectedWorkplaceName,
                'periodType' => $periodType,
                'workerOptions' => $rosterOptions['workers'] ?? [],
                'workerOptionsStatus' => $rosterOptions['status'] ?? 'unavailable',
                'workerOptionsMessage' => $rosterOptions['message'] ?? '',
                'rosterOnly' => ($query['load_roster'] ?? null) === '1',
                'filters' => [
                    'worker_id' => is_string($query['worker_id'] ?? null) ? trim($query['worker_id']) : '',
                    'year' => is_string($query['year'] ?? null) ? trim($query['year']) : (string) date('Y'),
                    'period_number' => is_string($query['period_number'] ?? null) ? trim($query['period_number']) : '',
                ],
                'comparison' => is_array($result['data'] ?? null) ? $result['data'] : null,
                'errorMessage' => $errorMessage,
                'demoMode' => ChecadorDailyReadService::demoModeEnabled(),
                'links' => [
                    '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                    '/assets/libs/select2/dist/css/select2.min.css',
                    '/assets/css/select2-modal.css',
                ],
                'scripts' => [
                    '/assets/js/vendor.min.js',
                    '/assets/js/core/module-station-selector.js',
                    '/assets/libs/select2/dist/js/select2.full.min.js',
                    '/assets/libs/select2/dist/js/i18n/es.js',
                    '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.module-station.init.js?v=20261120-payroll-1',
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.payroll-comparison.init.js?v=20261120-1',
                ],
            ],
            'departamento-operativo'
        );
    }

    public function lifecycleResolve(string $uuidIncidencia): void
    {
        $this->handleLifecycle($uuidIncidencia, 'resolve');
    }

    public function lifecycleAuthorize(string $uuidIncidencia): void
    {
        $this->handleLifecycle($uuidIncidencia, 'authorize');
    }

    public function lifecycleReject(string $uuidIncidencia): void
    {
        $this->handleLifecycle($uuidIncidencia, 'reject');
    }

    public function lifecycleRevoke(string $uuidIncidencia): void
    {
        $this->handleLifecycle($uuidIncidencia, 'revoke');
    }

    public function createIncidenceRevision(string $uuidIncidencia): void
    {
        $input = Request::all();
        $workplaceId = self::positiveInteger($input['workplace_id'] ?? null);
        $expectedRevision = self::positiveInteger($input['expected_current_revision'] ?? null);
        $reason = is_string($input['reason'] ?? null) ? trim((string) $input['reason']) : '';
        $evidence = is_string($input['evidence_reference'] ?? null) ? trim((string) $input['evidence_reference']) : '';
        $startDate = is_string($input['start_date'] ?? null) && trim((string) $input['start_date']) !== ''
            ? trim((string) $input['start_date']) : null;
        $endDate = is_string($input['end_date'] ?? null) && trim((string) $input['end_date']) !== ''
            ? trim((string) $input['end_date']) : null;
        $redirect = '/departamento-operativo/recursos-humanos/checador/incidencias'
            . ($workplaceId === null ? '' : '?workplace_id=' . $workplaceId);
        $store = static function (string $type, string $message) use ($workplaceId): void {
            Session::set('checador_m06_incidence_lifecycle_feedback', [
                'type' => $type,
                'message' => $message,
                'user_id' => (int) (Auth::user()->id ?? 0),
                'workplace_id' => (int) ($workplaceId ?? 0),
            ]);
        };
        $csrf = trim((string) ($input['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (!\App\Core\CsrfToken::validate($csrf)) {
            $store('error', 'La sesión del formulario venció. Actualiza la página antes de continuar.');
            self::redirect($redirect);
            return;
        }
        if ($workplaceId === null || $expectedRevision === null || !preg_match('/\A[0-9a-fA-F-]{36}\z/D', $uuidIncidencia)
            || $reason === '' || mb_strlen($reason, 'UTF-8') > 5000 || mb_strlen($evidence, 'UTF-8') > 100
            || (string) ($input['confirm_action'] ?? '') !== '1'
            || (($startDate === null) !== ($endDate === null))) {
            $store('error', 'La revisión requiere motivo, datos válidos y confirmación explícita.');
            self::redirect($redirect);
            return;
        }
        $actor = Auth::user();
        if (!$actor) {
            $store('error', 'La sesión ya no es válida. Inicia sesión nuevamente.');
            self::redirect($redirect);
            return;
        }
        $result = self::m06AdministrativeIncidenceSqlService()->appendRevision(
            $actor, $workplaceId, $uuidIncidencia, $expectedRevision, $reason,
            $evidence === '' ? null : $evidence, $startDate, $endDate
        );
        if (!($result['ok'] ?? false)) {
            $store('error', self::m06WriteErrorMessage((string) ($result['error'] ?? 'sql_write_failed')));
            self::redirect($redirect);
            return;
        }
        $store('success', !empty($result['data']['replayed'])
            ? 'La revisión equivalente ya existe y se confirmó mediante lectura SQL.'
            : 'La nueva revisión quedó guardada y confirmada mediante lectura SQL.');
        self::redirect($redirect);
    }

    public function temporaryAccessAuthorize(string $requestUuid): void
    {
        $this->handleTemporaryAccessAction($requestUuid, 'authorize');
    }

    public function temporaryAccessReject(string $requestUuid): void
    {
        $this->handleTemporaryAccessAction($requestUuid, 'reject');
    }

    public function temporaryAccessCancel(string $requestUuid): void
    {
        $this->handleTemporaryAccessAction($requestUuid, 'cancel');
    }

    private function handleTemporaryAccessAction(string $requestUuid, string $action): void
    {
        $input = Request::all();
        $workplaceId = self::positiveInteger($input['workplace_id'] ?? null);
        $date = is_string($input['date'] ?? null) ? trim($input['date']) : '';
        $workerId = self::positiveInteger($input['worker_id'] ?? null);
        $requestUuid = strtolower(trim($requestUuid));
        $redirect = '/departamento-operativo/recursos-humanos/checador/acceso-temporal?' . http_build_query(array_filter([
            'workplace_id' => $workplaceId,
            'date' => self::isCivilDate($date) ? $date : null,
            'worker_id' => $workerId,
        ], static fn(mixed $value): bool => $value !== null && $value !== ''));
        $user = Auth::user();
        $feedback = static function (string $type, string $message, array $extra = []) use ($user, $workplaceId, $action): void {
            Session::set('checador_temporary_access_feedback', array_merge([
                'user_id' => (int) ($user->id ?? 0),
                'workplace_id' => $workplaceId,
                'action' => $action,
                'type' => $type,
                'message' => $message,
            ], $extra));
        };

        if (!in_array($action, ['authorize', 'reject', 'cancel'], true)
            || $workplaceId === null
            || !preg_match('/\\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\\z/i', $requestUuid)
            || (string) ($input['confirm_action'] ?? '') !== '1') {
            $feedback('error', 'La acción de acceso temporal no es válida o requiere confirmación explícita.');
            self::redirect($redirect);
        }

        $authorization = ChecadorAccessService::authorizeTemporaryAccess($workplaceId, 'editar');
        if (empty($authorization['allowed']) || (int) ($authorization['permissions']['leer'] ?? 0) !== 1) {
            $feedback('error', 'No tienes permisos independientes de lectura y edición para este centro de trabajo.');
            self::redirect($redirect);
        }
        if (!$user) {
            $feedback('error', 'La sesión ya no es válida. Inicia sesión nuevamente.');
            self::redirect($redirect);
        }
        if (ChecadorDailyReadService::demoModeEnabled()) {
            $feedback('error', 'El modo demostración no simula solicitudes, PIN ni acciones de acceso temporal.');
            self::redirect($redirect);
        }
        if (!ChecadorDailyReadService::realApiPostOperationsEnabled()) {
            $feedback('error', 'Las acciones reales de acceso temporal están deshabilitadas hasta validar el ambiente, la autenticación y los permisos del servicio.');
            self::redirect($redirect);
        }

        $cancelMarker = Session::get('checador_temporary_access_cancelable');
        $temporaryAccessRecoveryMarker = $action === 'cancel'
            && is_array($cancelMarker)
            && strtolower((string) ($cancelMarker['request_uuid'] ?? '')) === $requestUuid
            && (int) ($cancelMarker['user_id'] ?? 0) === (int) $user->id
            && (int) ($cancelMarker['workplace_id'] ?? 0) === $workplaceId
            && (int) ($cancelMarker['expires_at'] ?? 0) > time();

        try {
            $reader = new ChecadorDailyReadService();
            $queue = $reader->listTemporaryAccessRequests($user);
        } catch (\Throwable) {
            $queue = ['ok' => false, 'error' => ['kind' => 'api_unavailable', 'status' => 503]];
        }
        if (($queue['ok'] ?? false) !== true) {
            $feedback('error', 'No fue posible verificar que la solicitud pertenezca a la cola y al centro seleccionado. No se ejecutó la acción.');
            self::redirect($redirect);
        }
        $pendingInSelectedWorkplace = false;
        foreach ($queue['requests'] as $request) {
            if (strtolower((string) ($request['request_uuid'] ?? '')) === $requestUuid
                && ChecadorAccessService::temporaryAccessRequestBelongsToLocality(
                    self::positiveInteger($request['id_estacion'] ?? null),
                    $workplaceId
                )) {
                $pendingInSelectedWorkplace = true;
                break;
            }
        }
        if (!$pendingInSelectedWorkplace && !$temporaryAccessRecoveryMarker) {
            $feedback('error', 'La solicitud ya no está pendiente o no pertenece al centro autorizado. Actualiza la cola.');
            self::redirect($redirect);
        }

        if ($action === 'authorize') {
            // If the API commits authorization but the response is lost, keep
            // only the scoped UUID so the supervisor can cancel without
            // replaying authorization or exposing a possibly lost PIN.
            $queuedExpiry = null;
            foreach ($queue['requests'] as $request) {
                if (strtolower((string) ($request['request_uuid'] ?? '')) === $requestUuid
                    && ChecadorAccessService::temporaryAccessRequestBelongsToLocality(
                        self::positiveInteger($request['id_estacion'] ?? null),
                        $workplaceId
                    )) {
                    try {
                        $queuedExpiry = (new \DateTimeImmutable((string) $request['request_expires_at_utc'], new \DateTimeZone('UTC')))->getTimestamp();
                    } catch (\Throwable) {
                        $queuedExpiry = null;
                    }
                    break;
                }
            }
            Session::set('checador_temporary_access_cancelable', [
                'user_id' => (int) $user->id,
                'workplace_id' => $workplaceId,
                'request_uuid' => $requestUuid,
                'expires_at' => max($queuedExpiry ?? 0, time() + 3600),
            ]);
        }

        $result = $reader->temporaryAccessAction($user, $requestUuid, $action);
        if (($result['ok'] ?? false) === true) {
            $data = $result['data'];
            if ($action === 'authorize') {
                $feedback('success', 'PIN temporal autorizado. Se muestra una sola vez y vence en el horario UTC indicado.', [
                    'temporary_pin' => $data['temporary_pin'],
                    'pin_expires_at_utc' => $data['pin_expires_at_utc'],
                ]);
                Session::set('checador_temporary_access_cancelable', [
                    'user_id' => (int) $user->id,
                    'workplace_id' => $workplaceId,
                    'request_uuid' => $requestUuid,
                    'expires_at' => (new \DateTimeImmutable($data['pin_expires_at_utc'], new \DateTimeZone('UTC')))->getTimestamp(),
                ]);
            } else {
                if (is_array($cancelMarker)
                    && strtolower((string) ($cancelMarker['request_uuid'] ?? '')) === $requestUuid
                    && (int) ($cancelMarker['user_id'] ?? 0) === (int) $user->id
                    && (int) ($cancelMarker['workplace_id'] ?? 0) === $workplaceId) {
                    Session::remove('checador_temporary_access_cancelable');
                }
                $feedback('success', $action === 'reject'
                    ? 'La solicitud fue rechazada por la API.'
                    : 'La solicitud fue cancelada por la API.');
            }
            self::redirect($redirect);
        }

        $kind = (string) ($result['error']['kind'] ?? 'api_unavailable');
        if ($kind !== 'api_unavailable'
            && is_array($cancelMarker)
            && strtolower((string) ($cancelMarker['request_uuid'] ?? '')) === $requestUuid
            && (int) ($cancelMarker['user_id'] ?? 0) === (int) $user->id
            && (int) ($cancelMarker['workplace_id'] ?? 0) === $workplaceId) {
            Session::remove('checador_temporary_access_cancelable');
        }
        $message = match ($kind) {
            'api_authentication' => 'La autenticación con el servicio no está disponible.',
            'api_authorization' => 'La API no autoriza esta acción o el centro seleccionado.',
            'bad_request' => 'La solicitud no cumple el contrato de acceso temporal.',
            'demo_operation_blocked' => 'La operación de acceso temporal no se simula en modo demostración.',
            'api_post_disabled' => 'Las acciones reales de acceso temporal permanecen deshabilitadas hasta validar el ambiente y la integración del servicio.',
            default => 'La acción no pudo confirmarse. Actualiza la cola antes de reintentar.',
        };
        $feedback('error', $message);
        self::redirect($redirect);
    }

    /** Create an M06 incidence only through the gated Portal3 SQL writer. */
    public function createIncidence(): void
    {
        $input = Request::all();
        $workplaceId = self::positiveInteger($input['workplace_id'] ?? null);
        $workerId = self::positiveInteger($input['worker_id'] ?? null);
        $category = is_string($input['category'] ?? null) ? trim($input['category']) : '';
        $startDate = is_string($input['start_date'] ?? null) ? trim($input['start_date']) : '';
        $endDate = is_string($input['end_date'] ?? null) ? trim($input['end_date']) : '';
        $reason = is_string($input['reason'] ?? null) ? trim($input['reason']) : '';
        $evidenceValid = !array_key_exists('evidence_reference', $input) || is_string($input['evidence_reference']);
        $evidence = is_string($input['evidence_reference'] ?? null) ? trim($input['evidence_reference']) : '';
        $idempotencyKey = is_string($input['idempotency_key'] ?? null) ? trim($input['idempotency_key']) : '';
        $redirect = '/departamento-operativo/recursos-humanos/checador/incidencias';
        $formValues = [
            'worker_id' => $workerId,
            'category' => $category,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reason' => $reason,
            'evidence_reference' => $evidence,
        ];
        $storeFeedback = static function (string $type, string $message, bool $keepKey = true) use (
            $workplaceId, $formValues, $idempotencyKey
        ): void {
            Session::set('checador_m06_incidence_feedback', [
                'type' => $type,
                'message' => $message,
                'user_id' => (int) (Auth::user()->id ?? 0),
                'workplace_id' => (int) ($workplaceId ?? 0),
                'form_values' => $formValues,
                'idempotency_key' => $keepKey ? $idempotencyKey : '',
            ]);
        };

        $csrf = trim((string) ($input['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (!\App\Core\CsrfToken::validate($csrf)) {
            $storeFeedback('error', 'La sesión del formulario venció. Actualiza la página antes de continuar.');
            self::redirect($redirect);
            return;
        }
        if ($workplaceId === null || $workerId === null
            || !in_array($category, \App\Services\ChecadorAdministrativeIncidenceSqlService::CATEGORIES, true)
            || !self::isCivilDate($startDate) || !self::isCivilDate($endDate) || $endDate < $startDate
            || $reason === '' || mb_strlen($reason, 'UTF-8') > 5000 || !$evidenceValid
            || mb_strlen($evidence, 'UTF-8') > 100
            || !preg_match('/\A[A-Za-z0-9._:-]{1,64}\z/D', $idempotencyKey)
            || (string) ($input['confirm_create'] ?? '') !== '1') {
            $storeFeedback('error', 'Completa los datos válidos y confirma el registro de la incidencia.');
            self::redirect($redirect);
            return;
        }
        $authorization = ChecadorAccessService::authorizeIncidenceCreate($workplaceId);
        if (!($authorization['allowed'] ?? false)) {
            $storeFeedback('error', 'No tienes permiso efectivo para crear incidencias en la localidad seleccionada.');
            self::redirect($redirect);
            return;
        }
        $actor = Auth::user();
        if (!$actor) {
            $storeFeedback('error', 'La sesión ya no es válida. Inicia sesión nuevamente.');
            self::redirect($redirect);
            return;
        }

        $result = self::m06AdministrativeIncidenceSqlService()->create(
            $actor,
            $workplaceId,
            $workerId,
            $category,
            $startDate,
            $endDate,
            $reason,
            $evidence === '' ? null : $evidence,
            $idempotencyKey
        );
        if (!($result['ok'] ?? false)) {
            $message = self::m06WriteErrorMessage((string) ($result['error'] ?? 'sql_write_failed'));
            $storeFeedback('error', $message);
            self::redirect($redirect);
            return;
        }

        $storeFeedback('success', !empty($result['data']['replayed'])
            ? 'La incidencia existente coincide con este reintento y fue confirmada mediante lectura SQL.'
            : 'La incidencia quedó guardada y confirmada mediante lectura SQL.');
        self::redirect($redirect);
    }

    public function administrativeEvaluation(): void
    {
        $input = Request::all();
        $workplaceId = self::positiveInteger($input['workplace_id'] ?? null);
        $workerId = self::positiveInteger($input['worker_id'] ?? null);
        $date = is_string($input['date'] ?? null) ? trim((string) $input['date']) : '';
        $snapshotRef = self::safeSnapshotRef($input['snapshot_ref'] ?? null);
        $expectedCalculationId = self::positiveInteger($input['expected_calculation_revision_id'] ?? null);
        $idempotencyKey = is_string($input['idempotency_key'] ?? null) ? trim($input['idempotency_key']) : '';
        $authorizeFalta = (string) ($input['authorize_falta'] ?? '') === '1';
        $action = $authorizeFalta ? 'authorize_falta' : 'evaluate';
        $redirect = '/departamento-operativo/recursos-humanos/checador/ajustes?' . http_build_query(array_filter([
            'workplace_id' => $workplaceId,
            'date' => $date,
            'worker_id' => $workerId,
            'snapshot_ref' => $snapshotRef,
        ], static fn(mixed $value): bool => $value !== null && $value !== ''));

        $feedback = static function (string $type, string $message, array $extra = []) use ($workerId, $workplaceId, $date, $action): void {
            Session::set('checador_evaluation_feedback', array_merge([
                'type' => $type,
                'message' => $message,
                'worker_id' => $workerId,
                'workplace_id' => $workplaceId,
                'date' => $date,
                'action' => $action,
            ], $extra));
        };

        if ($workerId === null || $workplaceId === null || $date === ''
            || !preg_match('/\A[A-Za-z0-9._:-]{1,64}\z/', $idempotencyKey)
            || (string) ($input['confirm_evaluation'] ?? '') !== '1') {
            $feedback('error', 'La evaluación requiere una acción y confirmación explícitas.');
            self::redirect($redirect);
        }

        $authorization = $authorizeFalta
            ? ChecadorAccessService::authorizeFalta($workplaceId)
            : ChecadorAccessService::authorizeEvaluation($workplaceId);
        if (!($authorization['allowed'] ?? false)) {
            $feedback('error', $authorizeFalta
                ? 'No tienes permiso para autorizar una FALTA o el centro está fuera de alcance.'
                : 'No tienes permiso para ejecutar la evaluación o el centro está fuera de alcance.');
            self::redirect($redirect);
        }

        $user = Auth::user();
        if (!$user) {
            $feedback('error', 'La sesión ya no es válida. Inicia sesión nuevamente.');
            self::redirect($redirect);
        }
        if (!ChecadorDailyReadService::demoModeEnabled()
            && !ChecadorDailyReadService::realApiPostOperationsEnabled()) {
            $feedback('error', 'La evaluación real está deshabilitada hasta validar el ambiente, la autenticación y los permisos del servicio.');
            self::redirect($redirect);
        }

        try {
            $reader = new ChecadorDailyReadService();
            $result = $reader->executeAdministrativeEvaluation(
                $user,
                $workerId,
                $workplaceId,
                $date,
                $idempotencyKey,
                $expectedCalculationId,
                $authorizeFalta
            );
            if (($result['ok'] ?? false) === true) {
                // The POST response is not the permanent UI authority. Read
                // back the persisted canonical evaluation before showing success.
                $readback = $reader->readAdministrativeEvaluation($user, $workerId, $workplaceId, $date);
                if (($readback['ok'] ?? false) === true) {
                    $status = (string) ($readback['data']['evaluation']['administrative_status'] ?? 'UNKNOWN');
                    $statusLabel = match ($status) {
                        'REVIEW_REQUIRED', 'OVERLAP_CONFLICT' => 'Requiere revisión humana',
                        'DATA_INCOMPLETE' => 'Información incompleta',
                        'VACATION_APPLIED' => 'Vacaciones aplicadas en la respuesta contractual',
                        'FALTA' => 'Clasificación FALTA autorizada explícitamente',
                        default => 'Estado administrativo disponible',
                    };
                    $feedback('success', $reader->isDemoMode()
                        ? 'Resultado sintético confirmado mediante relectura de demostración. Estado: ' . $statusLabel . '.'
                        : 'Evaluación administrativa confirmada por la API. Estado: ' . $statusLabel . '.');
                    self::redirect($redirect);
                }
                $feedback('error', 'La evaluación fue enviada pero la lectura canónica no pudo confirmarse.', [
                    'retry_idempotency_key' => $idempotencyKey,
                ]);
                self::redirect($redirect);
            }
        } catch (\Throwable) {
            $result = ['ok' => false, 'error' => ['kind' => 'api_unavailable', 'status' => 503]];
        }

        $kind = (string) ($result['error']['kind'] ?? 'api_unavailable');
        $message = match ($kind) {
            'calculation_not_found' => 'No existe un cálculo de Jornada evaluable para este trabajador y fecha.',
            'stale_revision_conflict' => 'El cálculo cambió mientras lo revisabas. Actualiza el contexto y confirma una nueva evaluación.',
            'idempotency_conflict' => 'La clave de idempotencia tiene un payload distinto. La operación quedó detenida.',
            'api_authentication' => 'La autenticación del servicio no está disponible.',
            'api_authorization' => 'La API no autoriza esta evaluación o el centro seleccionado.',
            'api_post_disabled' => 'La evaluación real permanece deshabilitada hasta validar el ambiente y la integración del servicio.',
            'bad_request' => 'La información enviada no cumple el contrato de evaluación.',
            'administrative_conflict' => 'La evaluación entró en conflicto con el estado canónico. Actualiza el contexto.',
            default => 'La evaluación no pudo confirmarse. No se muestra éxito; puedes reintentar explícitamente.',
        };
        $extra = in_array($kind, ['api_unavailable', 'administrative_conflict'], true)
            ? ['retry_idempotency_key' => $idempotencyKey]
            : [];
        $feedback('error', $message, $extra);
        self::redirect($redirect);
    }

    private function handleLifecycle(string $uuidIncidencia, string $action): void
    {
        $input = Request::all();
        $workplaceId = self::positiveInteger($input['workplace_id'] ?? null);
        $expectedRevision = self::positiveInteger($input['expected_current_revision'] ?? null);
        $reasonField = match ($action) {
            'resolve' => 'resolution_notes',
            'authorize' => 'action_notes',
            'reject' => 'rejection_reason',
            'revoke' => 'revocation_reason',
            default => '',
        };
        $reason = $reasonField !== '' && is_string($input[$reasonField] ?? null)
            ? trim((string) $input[$reasonField]) : '';
        $evidence = is_string($input['evidence_reference'] ?? null)
            ? trim((string) $input['evidence_reference']) : '';
        $redirect = '/departamento-operativo/recursos-humanos/checador/incidencias'
            . ($workplaceId === null ? '' : '?workplace_id=' . $workplaceId);
        $store = static function (string $type, string $message) use ($workplaceId): void {
            Session::set('checador_m06_incidence_lifecycle_feedback', [
                'type' => $type,
                'message' => $message,
                'user_id' => (int) (Auth::user()->id ?? 0),
                'workplace_id' => (int) ($workplaceId ?? 0),
            ]);
        };
        $csrf = trim((string) ($input['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (!\App\Core\CsrfToken::validate($csrf)) {
            $store('error', 'La sesión del formulario venció. Actualiza la página antes de continuar.');
            self::redirect($redirect);
            return;
        }
        if (!in_array($action, ['resolve', 'authorize', 'reject', 'revoke'], true)
            || $workplaceId === null || $expectedRevision === null || !preg_match('/\A[0-9a-fA-F-]{36}\z/D', $uuidIncidencia)
            || ($action !== 'authorize' && $reason === '') || mb_strlen($reason, 'UTF-8') > 5000
            || mb_strlen($evidence, 'UTF-8') > 100 || (string) ($input['confirm_action'] ?? '') !== '1') {
            $store('error', 'La acción administrativa no es válida o requiere confirmación explícita.');
            self::redirect($redirect);
            return;
        }
        $actor = Auth::user();
        if (!$actor) {
            $store('error', 'La sesión ya no es válida. Inicia sesión nuevamente.');
            self::redirect($redirect);
            return;
        }
        $result = self::m06AdministrativeIncidenceSqlService()->transition(
            $actor, $workplaceId, $uuidIncidencia, $action, $expectedRevision,
            $reason, $evidence === '' ? null : $evidence
        );
        if (!($result['ok'] ?? false)) {
            $store('error', self::m06WriteErrorMessage((string) ($result['error'] ?? 'sql_write_failed')));
            self::redirect($redirect);
            return;
        }
        $incidence = is_array($result['data']['incidence'] ?? null) ? $result['data']['incidence'] : [];
        $label = match ((string) ($incidence['current_status'] ?? '')) {
            'RESOLVED_PENDING_AUTHORIZATION' => 'Resuelta, pendiente de autorización',
            'ACTIVE' => 'Activa',
            'REJECTED' => 'Rechazada',
            'CANCELLED' => 'Cancelada',
            default => 'Estado actualizado',
        };
        $store('success', !empty($result['data']['replayed'])
            ? 'La misma acción ya estaba registrada y se confirmó mediante lectura SQL. Estado actual: ' . $label . '.'
            : 'La acción quedó registrada y confirmada mediante lectura SQL. Estado actual: ' . $label . '.');
        self::redirect($redirect);
    }

    /** @param array<int,array<string,mixed>> $events @return array<int,array<string,mixed>> */
    private static function preparePunchReportRows(array $events): array
    {
        $workerIds = [];
        foreach ($events as $event) {
            $workerId = self::positiveInteger($event['id_personal_resuelto'] ?? null);
            if ($workerId !== null) {
                $workerIds[] = $workerId;
            }
        }
        $profiles = (new \App\Services\ChecadorAttendanceRosterService())->observedProfiles($workerIds, false);
        foreach ($events as &$event) {
            $event['integrity_status'] = 'NOT_VERIFIED_BY_PORTAL3';
            $event['occurrence_status'] = ($event['ocurrido_at_utc'] ?? null) === null
                ? 'OCCURRENCE_TIME_UNRESOLVED'
                : 'OCCURRENCE_TIME_AVAILABLE_UTC';
            $workerId = self::positiveInteger($event['id_personal_resuelto'] ?? null);
            if ($workerId === null) {
                $event['collaborator_display'] = ChecadorPresentationFormatter::collaboratorLabel(null);
                continue;
            }
            $profile = $profiles[$workerId] ?? null;
            $name = is_array($profile) ? trim((string) ($profile['full_name'] ?? '')) : '';
            $event['collaborator_display'] = $name !== '' && $name !== 'Nombre no disponible'
                ? $name . ' · ID interno ' . $workerId
                : ChecadorPresentationFormatter::collaboratorLabel($workerId) . ' · ID interno ' . $workerId;
        }
        unset($event);
        return $events;
    }

    /**
     * Stream 200-row SQL pages without accumulating the full export in PHP
     * memory. A footer explicitly marks a changed/partial live export.
     *
     * @param array{ok:true,events:array<int,array<string,mixed>>,pagination:array{current_page:int,per_page:int,total_records:int,total_pages:int}} $firstPage
     */
    private static function streamPunchReportCsv(
        ChecadorRawPunchReadService $reader,
        array $firstPage,
        int $workplaceId,
        string $workplaceName,
        string $fromUtc,
        string $toUtc,
        ?int $workerId,
        int $perPage,
        string $sort
    ): void
    {
        $stream = fopen('php://output', 'wb');
        if (!is_resource($stream)) {
            http_response_code(500);
            return;
        }
        http_response_code(200);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="checador-marcaciones-' . gmdate('Ymd-His') . '.csv"');
        header('Cache-Control: no-store');

        fwrite($stream, "\xEF\xBB\xBF");
        self::writePunchReportCsvHeader($stream);
        fputcsv($stream, [
            'Aviso',
            'Consulta en vivo: los registros pueden cambiar durante la descarga. UTC significa Tiempo Universal Coordinado. La integridad no fue verificada por Portal3.',
        ], ',', '"', '');

        $pagination = $firstPage['pagination'];
        $expectedRecords = $pagination['total_records'];
        $totalPages = $pagination['total_pages'];
        $written = self::writePunchReportCsvRows($stream, $firstPage['events'], $workplaceName);
        $complete = $written === count($firstPage['events']);
        for ($nextPage = 2; $complete && $nextPage <= $totalPages; $nextPage++) {
            $result = $reader->readPage(
                $workplaceId,
                $fromUtc,
                $toUtc,
                $workerId,
                $nextPage,
                $perPage,
                $sort
            );
            if (!($result['ok'] ?? false)
                || ($result['pagination']['total_records'] ?? -1) !== $expectedRecords
                || ($result['pagination']['total_pages'] ?? -1) !== $totalPages
                || ($result['pagination']['current_page'] ?? -1) !== $nextPage) {
                $complete = false;
                break;
            }
            $written += self::writePunchReportCsvRows($stream, $result['events'], $workplaceName);
        }
        $complete = $complete && $written === $expectedRecords;
        fputcsv($stream, [
            'Estado de la descarga',
            $complete ? 'Completa' : 'Incompleta: los registros cambiaron o no se pudo completar la consulta',
        ], ',', '"', '');
        fputcsv($stream, ['Registros exportados', (string) $written], ',', '"', '');
        fclose($stream);
    }

    /** Stream the same authorized, searched and ordered SQL set used by DataTables. */
    private static function streamPunchReportDataTableCsv(
        ChecadorRawPunchReadService $reader,
        array $firstPage,
        int $workplaceId,
        string $workplaceName,
        string $fromUtc,
        string $toUtc,
        ?int $workerId,
        string $search,
        int $orderColumn,
        string $direction,
        string $captureMethod = '',
        string $processingStatus = ''
    ): void {
        $stream = fopen('php://output', 'wb');
        if (!is_resource($stream)) {
            http_response_code(500);
            return;
        }
        http_response_code(200);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="checador-marcaciones-' . gmdate('Ymd-His') . '.csv"');
        header('Cache-Control: no-store');

        fwrite($stream, "\xEF\xBB\xBF");
        self::writePunchReportCsvHeader($stream);
        fputcsv($stream, [
            'Aviso',
            'Consulta SQL local en vivo; los registros pueden cambiar durante la descarga. UTC significa Tiempo Universal Coordinado. La integridad no fue verificada por Portal3; un resultado vacío no confirma ausencia ni completitud.',
        ], ',', '"', '');

        $expectedTotal = (int) ($firstPage['records_total'] ?? -1);
        $expectedFiltered = (int) ($firstPage['records_filtered'] ?? -1);
        $length = (int) ($firstPage['length'] ?? 0);
        if ($expectedTotal < 0 || $expectedFiltered < 0 || $length < 1) {
            fputcsv($stream, ['Estado de la descarga', 'Incompleta: no se pudo validar el resultado'], ',', '"', '');
            fputcsv($stream, ['Registros exportados', '0'], ',', '"', '');
            fclose($stream);
            return;
        }

        $written = self::writePunchReportCsvRows($stream, $firstPage['events'] ?? [], $workplaceName);
        $complete = $written === count($firstPage['events'] ?? []);
        for ($start = $length; $complete && $start < $expectedFiltered; $start += $length) {
            $result = $reader->readDataTablePage(
                $workplaceId,
                $fromUtc,
                $toUtc,
                $workerId,
                $start,
                $length,
                $search,
                $orderColumn,
                $direction,
                $workplaceName,
                $captureMethod,
                $processingStatus
            );
            if (!($result['ok'] ?? false)
                || ($result['records_total'] ?? -1) !== $expectedTotal
                || ($result['records_filtered'] ?? -1) !== $expectedFiltered
                || ($result['start'] ?? -1) !== $start) {
                $complete = false;
                break;
            }
            $pageRows = $result['events'] ?? [];
            $written += self::writePunchReportCsvRows($stream, $pageRows, $workplaceName);
            $complete = count($pageRows) === min($length, $expectedFiltered - $start);
        }
        $complete = $complete && $written === $expectedFiltered;
        fputcsv($stream, [
            'Estado de la descarga',
            $complete ? 'Completa' : 'Incompleta: los registros cambiaron o no se pudo completar la consulta',
        ], ',', '"', '');
        fputcsv($stream, ['Registros exportados', (string) $written], ',', '"', '');
        fclose($stream);
    }

    /** @param array<int,array<string,mixed>> $events */
    private static function punchReportCsvContent(array $events, int $workplaceId, string $workplaceName): ?string
    {
        $stream = self::buildPunchReportCsvStream($events, $workplaceId, $workplaceName);
        if (!is_resource($stream)) return null;
        $csv = stream_get_contents($stream);
        fclose($stream);
        return is_string($csv) ? $csv : null;
    }

    /** @param array<int,array<string,mixed>> $events @return resource|null */
    private static function buildPunchReportCsvStream(array $events, int $workplaceId, string $workplaceName)
    {
        $stream = fopen('php://temp/maxmemory:5242880', 'w+b');
        if ($stream === false) return null;
        fwrite($stream, "\xEF\xBB\xBF");
        self::writePunchReportCsvHeader($stream);
        self::writePunchReportCsvRows($stream, $events, $workplaceName);
        rewind($stream);
        return $stream;
    }

    /** @param resource $stream */
    private static function writePunchReportCsvHeader($stream): void
    {
        fputcsv($stream, [
            'Estación', 'Identificador del registro', 'Colaborador', 'Dispositivo',
            'Hora original informada por el dispositivo', 'Zona horaria informada por el dispositivo',
            'Fecha y hora de marcación (UTC)', 'Fecha y hora de recepción (UTC)',
            'Método de registro', 'Estado de la marcación', 'Disponibilidad de la hora de marcación',
            'Integridad del registro',
        ], ',', '"', '');
    }

    /** @param resource $stream @param array<int,array<string,mixed>> $events */
    private static function writePunchReportCsvRows($stream, array $events, string $workplaceName): int
    {
        foreach (self::preparePunchReportRows($events) as $event) {
            fputcsv($stream, [
                self::csvText($workplaceName),
                self::csvText($event['event_uuid'] ?? ''),
                self::csvText($event['collaborator_display'] ?? ChecadorPresentationFormatter::collaboratorLabel($event['id_personal_resuelto'] ?? null)),
                self::csvText(isset($event['id_dispositivo']) ? 'Dispositivo ' . (string) $event['id_dispositivo'] : 'No disponible'),
                self::csvText($event['source_timestamp_raw'] ?? ''),
                self::csvText($event['source_timezone_reported'] ?? ''),
                self::csvText($event['ocurrido_at_utc'] ?? ''),
                self::csvText($event['recibido_at_utc'] ?? ''),
                self::csvText(ChecadorPresentationFormatter::captureMethodLabel($event['metodo_captura'] ?? null)),
                self::csvText(ChecadorPresentationFormatter::processingStatusLabel($event['processing_status'] ?? null)),
                self::csvText(($event['ocurrido_at_utc'] ?? null) === null
                    ? 'Hora no determinada; se muestra la fecha y hora de recepción'
                    : (ChecadorPresentationFormatter::eventLocalDateTime($event) === null
                        ? 'Hora de marcación disponible (UTC; zona efectiva no resuelta)'
                        : 'Hora de marcación convertida para presentación usando la zona efectiva registrada por V2')),
                ChecadorPresentationFormatter::integrityLabel(),
            ], ',', '"', '');
        }
        return count($events);
    }

    private static function csvText(mixed $value): string
    {
        $text = is_scalar($value) ? (string) $value : '';
        return preg_match('/\A[\x00-\x20]*[=+@\-]/', $text) === 1 ? "'" . $text : $text;
    }

    private static function isCivilDate(string $date): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed !== false
            && $parsed->format('Y-m-d') === $date
            && !array_filter(\DateTimeImmutable::getLastErrors() ?: []);
    }

    private static function utcRangeSeconds(string $fromUtc, string $toUtc): ?int
    {
        try {
            $utc = new \DateTimeZone('UTC');
            $from = new \DateTimeImmutable($fromUtc, $utc);
            $to = new \DateTimeImmutable($toUtc, $utc);
            return $to->getTimestamp() - $from->getTimestamp();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{0:string,1:string} */
    private static function punchReportError(string $kind): array
    {
        return match ($kind) {
            'bad_request' => ['error', 'Revisa las fechas (máximo 31 días), el número de colaborador y la página solicitada.'],
            'result_too_large' => ['error', 'El resultado supera el límite seguro de 100,000 registros. Reduce el rango de fechas.'],
            'station_mapping_unavailable' => ['unavailable', 'No se pudo validar la localidad canónica autorizada para el reporte.'],
            'database_unavailable' => ['unavailable', 'La consulta SQL no está disponible; no se interpreta como ausencia de marcaciones.'],
            default => ['unavailable', 'La consulta no está disponible; no se interpreta como ausencia de marcaciones.'],
        };
    }

    private static function punchReportJsonError(int $status, string $message, int $draw): never
    {
        JsonResponse::send([
            'draw' => max(0, $draw),
            'error' => $message,
        ], $status);
    }

    private static function nonNegativeInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }
        if (!is_string($value) || $value === '' || !ctype_digit($value)) {
            return null;
        }
        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        return $integer === false ? null : $integer;
    }

    private static function positiveInteger(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) return $value;
        if (!is_string($value) || !ctype_digit($value) || (int) $value < 1) return null;
        return (int) $value;
    }

    /** Resolve Comodines from the canonical Checador session context only. */
    private function comodinesSessionWorkplaceId(array $access): ?int
    {
        $context = ModuleStationService::getContext(ChecadorAccessService::MODULE_KEY);
        $workplaceId = self::positiveInteger($context['id_depto'] ?? $context['id_estacion'] ?? null);
        $authorizedIds = array_map('intval', array_column($access['station_workplaces'] ?? [], 'id'));

        if ($workplaceId !== null && !in_array($workplaceId, $authorizedIds, true)) {
            ModuleStationService::setContext(ChecadorAccessService::MODULE_KEY, null, null);
            return null;
        }
        if ($workplaceId !== null
            && empty(ChecadorAccessService::authorize($workplaceId)['allowed'])) {
            ModuleStationService::setContext(ChecadorAccessService::MODULE_KEY, null, null);
            return null;
        }
        return $workplaceId;
    }

    private static function renderAccessDenied(string $reason, ?int $requestedStationId): void
    {
        http_response_code(403);

        $safeReason = preg_match('/\A[a-z0-9_]{1,64}\z/', $reason) === 1
            ? $reason
            : 'authorization_lookup_failed';
        if (in_array($_ENV['APP_ENV'] ?? 'prod', ['dev', 'development', 'local'], true)) {
            $requestPath = Request::uri();
            $safeRequestPath = preg_match('/\A\/[a-zA-Z0-9_\/.\-]{1,255}\z/', $requestPath) === 1
                ? $requestPath
                : '/';
            $correlationId = null;
            foreach (['HTTP_X_REQUEST_ID', 'HTTP_X_CORRELATION_ID'] as $headerKey) {
                $candidate = $_SERVER[$headerKey] ?? null;
                if (is_string($candidate) && preg_match('/\A[a-zA-Z0-9._-]{1,64}\z/', $candidate) === 1) {
                    $correlationId = $candidate;
                    break;
                }
            }
            try {
                Logger::warning('Checador access denied', [
                    'reason' => $safeReason,
                    'requested_station_id' => $requestedStationId,
                    'requested_path' => $safeRequestPath,
                    'correlation_id' => $correlationId,
                ]);
            } catch (\Throwable) {
                // Logging must not weaken or alter the fail-closed response.
            }
        }

        View::renderPartial('errors/403');
    }

    /** Log only private, non-identifying diagnostics for M06 read denials. */
    private static function logM06IncidenceAccessDenial(
        string $stage,
        string $reason,
        ?int $localityId
    ): void {
        $allowedStages = [
            'checador_scope',
            'locality_selection',
            'incidence_read_acl',
            'workplace_context',
            'selected_workplace',
            'session_identity',
        ];
        $path = Request::uri();
        $path = is_string($path)
            && preg_match('/\\A\\/[a-zA-Z0-9_\\/.\\-]{1,255}\\z/', $path) === 1
                ? $path
                : '/';
        $correlationId = null;
        foreach (['HTTP_X_REQUEST_ID', 'HTTP_X_CORRELATION_ID'] as $headerKey) {
            $candidate = $_SERVER[$headerKey] ?? null;
            if (is_string($candidate) && preg_match('/\\A[a-zA-Z0-9._-]{1,64}\\z/', $candidate) === 1) {
                $correlationId = $candidate;
                break;
            }
        }
        if ($correlationId === null) {
            try {
                $correlationId = bin2hex(random_bytes(12));
                if (!headers_sent()) {
                    header('X-Correlation-ID: ' . $correlationId);
                }
            } catch (\Throwable) {
                $correlationId = null;
            }
        }

        try {
            Logger::warning('M06 incidence access denied', [
                'stage' => in_array($stage, $allowedStages, true) ? $stage : 'unknown',
                'reason' => preg_match('/\\A[a-z0-9_]{1,64}\\z/', $reason) === 1
                    ? $reason
                    : 'authorization_lookup_failed',
                'locality_id' => $localityId !== null && $localityId > 0 ? $localityId : null,
                'route' => $path,
                'correlation_id' => $correlationId,
            ]);
        } catch (\Throwable) {
            // Diagnostics must never change the fail-closed authorization result.
        }
    }

    /** @return array{0:string,1:string} */
    private static function stateForError(string $kind): array
    {
        return match ($kind) {
            'timezone_unresolved' => ['timezone_unresolved', 'No existe una configuración de zona horaria autorizada para el centro de trabajo y la fecha seleccionados.'],
            'timezone_ambiguous' => ['error', 'La configuración de zona horaria del centro de trabajo es ambigua. Contacta al administrador.'],
            'timezone_invalid' => ['error', 'La configuración de zona horaria del centro de trabajo no es válida.'],
            'api_authentication' => ['error', 'La autenticación del servicio de consulta no está disponible.'],
            'api_post_disabled' => ['unavailable', 'Las operaciones de consulta que requieren POST están deshabilitadas hasta validar el ambiente y la integración del servicio.'],
            'worker_not_found' => ['error', 'El trabajador canónico seleccionado no existe.'],
            'journey_unavailable' => ['journey_error', 'El resultado de Jornada no está disponible en este momento.'],
            'api_authorization', 'snapshot_authorization' => ['error', 'La API ya no autoriza esta consulta para el centro de trabajo seleccionado.'],
            'snapshot_expired' => ['snapshot_expired', 'La consulta estable expiró. Vuelve a consultar para obtener una nueva lectura.'],
            'snapshot_invalid' => ['error', 'La página de la consulta estable no es válida. Vuelve a consultar.'],
            'snapshot_not_ready' => ['unavailable', 'La consulta estable todavía no está disponible. Vuelve a consultar.'],
            'snapshot_state_unavailable' => ['snapshot_state_unavailable', 'La consulta estable ya no está disponible. Vuelve a consultar para obtener una nueva lectura.'],
            'snapshot_unavailable' => ['unavailable', 'No fue posible crear o leer la consulta estable.'],
            'bad_request' => ['error', 'La consulta enviada no es válida.'],
            default => ['unavailable', 'Las marcaciones observadas no están disponibles en este momento.'],
        };
    }

    /** @return array{0:string,1:string} */
    private static function stateForLevel2Error(string $kind): array
    {
        return match ($kind) {
            'api_authentication', 'level2_authorization', 'api_authorization' => ['level2_error', 'La API no autoriza la consulta de completitud para el centro de trabajo seleccionado.'],
            'bad_request' => ['level2_error', 'La ventana de completitud enviada no es válida.'],
            default => ['level2_error', 'El estado de la fuente no está disponible en este momento.'],
        };
    }

    /** Read a bounded stable set for the worker-oriented daily summary. */
    private static function readAttendanceSnapshotSet(
        ChecadorDailyReadService $reader,
        \App\Models\Usuario $user,
        string $snapshotToken,
        ?int $demoResolvedWorkerId
    ): array {
        $events = [];
        $cursor = null;
        $seenCursors = [];
        $complete = false;
        $failure = null;
        for ($page = 0; $page < 20; $page++) {
            $result = $reader->readDailyPunchSnapshotPage($user, $snapshotToken, $cursor, 200, $demoResolvedWorkerId);
            if (!($result['ok'] ?? false)) {
                $failure = (string) ($result['error']['kind'] ?? 'snapshot_unavailable');
                break;
            }
            foreach ($result['events'] as $event) $events[] = $event;
            $pagination = $result['pagination'];
            if (($pagination['has_more'] ?? false) !== true) {
                $complete = true;
                break;
            }
            $next = $pagination['next_cursor'] ?? null;
            if (!is_string($next) || $next === '' || isset($seenCursors[$next])) {
                $failure = 'invalid_cursor_chain';
                break;
            }
            $seenCursors[$next] = true;
            $cursor = $next;
        }
        return [
            'events' => $events,
            'coverage' => [
                'status' => $complete ? 'complete' : ($failure === null ? 'bounded_partial' : 'read_error'),
                'complete' => $complete,
                'message' => $complete
                    ? 'Se recorrieron todas las páginas de la lectura estable.'
                    : ($failure === null
                        ? 'La lectura superó el límite de páginas de seguridad; la cobertura es parcial.'
                        : 'No se pudieron leer todas las páginas; la cobertura es parcial.'),
            ],
        ];
    }

    private static function paginationLinks(?int $workplaceId, string $date, ?array $pagination, ?string $snapshotRef, int $page, ?int $workerId = null): array
    {
        if (!$pagination || $workplaceId === null || $date === '' || $snapshotRef === null) return [];
        $links = [];
        if ($page > 1) $links[] = self::pageLink($workplaceId, $date, $snapshotRef, $page - 1, (int) ($pagination['page_size'] ?? 50), 'Anterior', $workerId);
        if (($pagination['has_more'] ?? false) === true && !empty($pagination['next_cursor'])) $links[] = self::pageLink($workplaceId, $date, $snapshotRef, $page + 1, (int) ($pagination['page_size'] ?? 50), 'Siguiente', $workerId);
        return $links;
    }

    private static function pageLink(int $workplaceId, string $date, string $snapshotRef, int $page, int $perPage, string $label, ?int $workerId = null): array
    {
        $query = [
            'workplace_id' => $workplaceId,
            'date' => $date,
            'page' => $page,
            'per_page' => $perPage,
            'snapshot_ref' => $snapshotRef,
        ];
        if ($workerId !== null) $query['worker_id'] = $workerId;
        return [
            'page' => $page,
            'url' => '/departamento-operativo/recursos-humanos/checador?' . http_build_query($query),
            'label' => $label,
        ];
    }

    private static function newSnapshotRef(): string
    {
        return bin2hex(random_bytes(16));
    }

    private static function newIdempotencyKey(): string
    {
        return 'portal3-' . bin2hex(random_bytes(24));
    }

    /** Return only a valid Monday filter; workplace context stays session-authoritative. */
    private static function scheduleWeekQueryParameters(array $query): array
    {
        $weekStart = $query['week_start'] ?? null;
        if (!is_string($weekStart)
            || !self::isCivilDate($weekStart)
            || \App\Services\ChecadorWeeklyScheduleService::monday($weekStart) !== $weekStart) {
            return [];
        }
        return ['week_start' => $weekStart];
    }

    /** Return the station-scoped roster only when V2 resolves its local date and timezone. */
    private static function scheduleHistoryWorkerRoster(
        int $workplaceId,
        string $date,
        int $workerId
    ): array {
        try {
            $reader = new \App\Services\ChecadorWeeklyScheduleSqlReadService();
            $candidateDate = $date !== '' ? $date : gmdate('Y-m-d');
            if (!self::isCivilDate($candidateDate)) return ['ok' => false, 'workers' => []];
            $timezone = $reader->timezoneForDate($workplaceId, $candidateDate) ?? '';
            if (!\App\Services\ChecadorOperationalPeriod::isIanaTimezone($timezone)) return ['ok' => false, 'workers' => []];
            if ($date === '') {
                $date = \App\Services\ChecadorOperationalPeriod::currentLocalDate(
                    $timezone,
                    new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
                ) ?? '';
                if ($date === '') return ['ok' => false, 'workers' => []];
                if ($date !== $candidateDate) {
                    $timezone = $reader->timezoneForDate($workplaceId, $date) ?? '';
                    if (!\App\Services\ChecadorOperationalPeriod::isIanaTimezone($timezone)) return ['ok' => false, 'workers' => []];
                }
            }
            $worker = (new \App\Services\ChecadorAttendanceRosterService())->scheduleHistoryWorkerForDate(
                $workerId,
                $workplaceId,
                $date,
                $timezone
            );
            return ['ok' => $worker !== null, 'workers' => $worker === null ? [] : [$worker]];
        } catch (\Throwable) {
            return ['ok' => false, 'workers' => []];
        }
    }

    /** Keep filters on the canonical history route; query flags never select its screen. */
    private static function scheduleHistoryQueryParameters(array $query): array
    {
        $safe = [];
        foreach (['workplace_id', 'worker_id', 'page'] as $key) {
            $value = self::positiveInteger($query[$key] ?? null);
            if ($value !== null && ($key !== 'page' || $value > 1)) {
                $safe[$key] = (string) $value;
            }
        }
        if (is_string($query['date'] ?? null) && self::isCivilDate($query['date'])) {
            $safe['date'] = $query['date'];
        }
        return $safe;
    }

    private static function scheduleWeekHref(array $query): string
    {
        $parameters = self::scheduleWeekQueryParameters($query);
        $path = '/departamento-operativo/recursos-humanos/checador/programacion-semanal';
        return $path . ($parameters === []
            ? ''
            : '?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986));
    }

    private static function redirect(string $location): never
    {
        header('Location: ' . $location, true, 303);
        exit;
    }

    /** Strip legacy locality URL state without changing the authorized session context. */
    private function normalizeLegacyWorkplaceUrl(bool $m04TemplateScope = false): void
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? null;
        if (!is_string($requestUri) || $requestUri === '' || $requestUri[0] !== '/') {
            return;
        }

        $parsed = parse_url($requestUri);
        $path = is_array($parsed) && is_string($parsed['path'] ?? null) ? $parsed['path'] : '';
        $base = '/departamento-operativo/recursos-humanos/checador';
        if ($path !== $base && !str_starts_with($path, $base . '/')) {
            return;
        }

        $params = [];
        parse_str((string) ($parsed['query'] ?? ''), $params);
        if (!array_key_exists('workplace_id', $params)) {
            return;
        }

        // Legacy query IDs are never applied as authorization or context. They
        // can only be removed while preserving filters when they match the
        // already authorized canonical locality in this user's session.
        $legacyId = self::positiveInteger($params['workplace_id']);
        $context = ModuleStationService::getContext(ChecadorAccessService::MODULE_KEY);
        $sessionId = $m04TemplateScope
            ? (ModuleStationService::getChecadorM04TemplateWorkplaceContext()
                ?? self::positiveInteger($context['id_depto'] ?? $context['id_estacion'] ?? null))
            : self::positiveInteger($context['id_depto'] ?? $context['id_estacion'] ?? null);
        $sameLocality = $legacyId !== null && $legacyId === $sessionId;
        unset($params['workplace_id']);

        if (!$sameLocality) {
            $safeFilters = [
                'date', 'from_date', 'to_date', 'period', 'year', 'month',
                'week_start', 'schedule_week', 'schedule_history',
            ];
            $params = array_intersect_key($params, array_flip($safeFilters));
        }

        $location = $path . ($params === []
            ? ''
            : '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
        self::redirect($location);
    }

    /** Resolve the report locality only from the current authorized session context. */
    private function requestWorkplaceId(array $input): ?int
    {
        $access = ChecadorAccessService::authorize(null);
        if (empty($access['allowed'])) return null;
        $sessionId = $this->sessionWorkplaceId($access);
        if ($sessionId === null) return null;
        if (array_key_exists('workplace_id', $input)
            && self::positiveInteger($input['workplace_id']) !== $sessionId) return null;
        return $sessionId;
    }

    /** Resolve the M04 target from the server session, with only the approved Autolavado exception. */
    private function requestM04TemplateWorkplaceId(array $input): ?int
    {
        $departmentId = ModuleStationService::getChecadorM04TemplateWorkplaceContext();
        if ($departmentId === ChecadorAccessService::AUTOLAVADO_LOCALITY_ID) {
            $requestedId = self::positiveInteger($input['workplace_id'] ?? null);
            if ($requestedId !== ChecadorAccessService::AUTOLAVADO_LOCALITY_ID
                || !(ChecadorAccessService::authorizeStationTemplateAdministration($departmentId)['allowed'] ?? false)) {
                return null;
            }
            return $departmentId;
        }

        return $this->requestWorkplaceId($input);
    }

    /** Resolve the report locality only from the current authorized session context. */
    private function sessionWorkplaceId(array $access): ?int
    {
        if (empty($access['allowed'])) {
            return null;
        }

        $context = ModuleStationService::getContext(ChecadorAccessService::MODULE_KEY);
        $workplaceId = self::positiveInteger($context['id_depto'] ?? $context['id_estacion'] ?? null);
        $authorizedIds = array_map('intval', array_column($access['workplaces'] ?? [], 'id'));

        if ($workplaceId === null) {
            return null;
        }
        if (!in_array($workplaceId, $authorizedIds, true)
            || empty(ChecadorAccessService::authorize($workplaceId)['allowed'])) {
            ModuleStationService::setContext(ChecadorAccessService::MODULE_KEY, null, null);
            return null;
        }

        return $workplaceId;
    }

    private static function creationRequestUuid(): string
    {
        return sprintf('%s-%s-%s-%s-%s', bin2hex(random_bytes(4)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(6)));
    }

    /** Build presentation-only M01 examples; this method performs no database or API access. */
    private static function m01SyntheticDetailPreviewRows(string $date): array
    {
        if (!self::isCivilDate($date)) {
            throw new \InvalidArgumentException('Synthetic M01 preview date is invalid.');
        }

        $event = static function (int $workerId, int $ordinal, string $clock, string $role, ?int $segmentOrdinal) use ($date): array {
            $utc = (new \DateTimeImmutable($date . ' ' . $clock, new \DateTimeZone('America/Mexico_City')))
                ->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            return [
                'event_uuid' => sprintf('e7500000-0000-4000-8000-%012d', (($workerId - 900000) * 100) + $ordinal),
                'id_dispositivo' => null,
                'ocurrido_at_utc' => $utc,
                'metodo_captura' => 'SYNTHETIC_PREVIEW',
                'processing_status' => 'SYNTHETIC_ONLY',
                'integrity' => ['status' => 'UNVERIFIABLE'],
                'pairing_role' => $role,
                'segment_ordinal' => $segmentOrdinal,
            ];
        };
        $schedule = static function (string $status, array $segments = []): array {
            return [
                'estado_jornada' => $status,
                'horario_local' => $status === 'SCHEDULED' ? [
                    'timezone' => 'America/Mexico_City',
                    'segmentos' => $segments,
                ] : null,
            ];
        };
        $segment = static fn(int $ordinal, string $start, string $end): array => [
            'ordinal' => $ordinal,
            'tipo_segmento' => 'WORK',
            'hora_inicio_local' => $start,
            'hora_fin_local' => $end,
            'dia_offset_inicio' => 0,
            'dia_offset_fin' => 0,
        ];
        $makeRow = static function (
            int $workerId,
            string $name,
            string $employeeNumber,
            array $effectiveSchedule,
            array $events,
            string $status,
            string $journeyStatus,
            ?int $incidenceCount
        ) use ($date): array {
            $slots = array_fill_keys(['ENTRY:1', 'EXIT:1', 'ENTRY:2', 'EXIT:2'], [
                'event' => null,
                'time_local' => null,
                'ambiguous' => false,
            ]);
            $additional = [];
            foreach ($events as $event) {
                $role = (string) ($event['pairing_role'] ?? '');
                $ordinal = is_int($event['segment_ordinal'] ?? null) ? $event['segment_ordinal'] : null;
                $key = in_array($role, ['ENTRY', 'EXIT'], true) && $ordinal !== null
                    ? $role . ':' . $ordinal
                    : '';
                if ($key !== '' && array_key_exists($key, $slots)) {
                    $local = (new \DateTimeImmutable((string) $event['ocurrido_at_utc'], new \DateTimeZone('UTC')))
                        ->setTimezone(new \DateTimeZone('America/Mexico_City'));
                    $slots[$key] = [
                        'event' => $event,
                        'time_local' => ['date' => $local->format('Y-m-d'), 'time' => $local->format('H:i'), 'utc' => (string) $event['ocurrido_at_utc']],
                        'ambiguous' => false,
                    ];
                } else {
                    $additional[] = $event;
                }
            }
            return [
                'worker_id' => $workerId,
                'full_name' => $name,
                'employee_number' => $employeeNumber,
                'schedule' => $effectiveSchedule,
                'schedule_error' => $effectiveSchedule['estado_jornada'] === 'UNKNOWN' ? 'schedule_unverified' : null,
                'slots' => $slots,
                'additional_events' => $additional,
                'status' => $status,
                'journey_status' => $journeyStatus,
                'evaluated' => $journeyStatus !== 'NOT_EVALUATED',
                'evaluated_raw_event_count' => $journeyStatus === 'NOT_EVALUATED' ? null : count($events),
                'evaluated_raw_events' => $journeyStatus === 'NOT_EVALUATED' ? null : $events,
                'incidence_count' => $incidenceCount,
                'no_absence_inference' => true,
                'preview_date' => $date,
            ];
        };

        $fourPunches = [
            $event(900001, 1, '08:00:00', 'ENTRY', 1),
            $event(900001, 2, '12:00:00', 'EXIT', 1),
            $event(900001, 3, '13:00:00', 'ENTRY', 2),
            $event(900001, 4, '17:00:00', 'EXIT', 2),
        ];
        $twoPunches = [
            $event(900002, 1, '08:05:00', 'ENTRY', 1),
            $event(900002, 2, '17:02:00', 'EXIT', 1),
        ];
        $entryOnly = [$event(900003, 1, '08:12:00', 'ENTRY', 1)];
        $reviewEvents = [
            $event(900005, 1, '08:00:00', 'ENTRY', 1),
            $event(900005, 2, '12:00:00', 'EXIT', 1),
            $event(900005, 3, '13:15:00', 'UNMATCHED', null),
        ];
        $rows = [
            900001 => $makeRow(900001, 'Ana Mercado', 'EMP-1041', $schedule('SCHEDULED', [$segment(1, '08:00:00', '12:00:00'), $segment(2, '13:00:00', '17:00:00')]), $fourPunches, 'evaluated', 'EVALUATED', 0),
            900002 => $makeRow(900002, 'Luis Navarro', 'EMP-1042', $schedule('SCHEDULED', [$segment(1, '08:00:00', '17:00:00')]), $twoPunches, 'evaluated', 'EVALUATED', 0),
            900003 => $makeRow(900003, 'Carla Salas', 'EMP-1043', $schedule('SCHEDULED', [$segment(1, '08:00:00', '17:00:00')]), $entryOnly, 'incomplete', 'REVIEW_REQUIRED', 0),
            900004 => $makeRow(900004, 'Diego Molina', 'EMP-1044', $schedule('DAY_OFF'), [], 'no_observed_event', 'DAY_OFF', 0),
            900005 => $makeRow(900005, 'Elena Fuentes', 'EMP-1045', $schedule('SCHEDULED', [$segment(1, '08:00:00', '17:00:00')]), $reviewEvents, 'review', 'REVIEW_REQUIRED', 1),
            900006 => $makeRow(900006, 'Marcos Gil', 'EMP-1046', $schedule('UNKNOWN'), [], 'incomplete', 'NOT_EVALUATED', null),
        ];

        return [
            'attendance_rows' => $rows,
            'administrative_incidences' => [900005 => [[
                'category' => 'PERMISSION',
                'date_range' => ['start_date' => $date, 'end_date' => $date],
                'current_status' => 'PENDING_REVIEW',
                'current_revision' => 1,
                'uuid_incidencia' => 'f1200005-0000-4000-8000-000000000005',
                'source_classification' => 'SYNTHETIC_DEMO_FIXTURE',
            ]]],
        ];
    }

    /** Build one isolated M01 demo result from the existing synthetic API adapter. */
    private static function readM01SyntheticAttendance(
        ChecadorDailyReadService $reader,
        \App\Models\Usuario $user,
        int $workplaceId,
        string $date,
        ?int $requestedWorkerId,
        bool $requestedWorkerInvalid
    ): array {
        $unavailable = static function (string $message, ?array $timezone = null, array $roster = []): array {
            return [
                'state' => 'unavailable',
                'state_message' => $message,
                'timezone' => $timezone,
                'events' => [],
                'attendance_events' => [],
                'attendance_coverage' => [
                    'status' => 'synthetic_qa_fixture_partial',
                    'complete' => false,
                    'message' => 'La demostración usa únicamente respuestas sintéticas locales.',
                ],
                'attendance_roster' => $roster,
                'worker_options' => [],
                'selected_worker_id' => null,
                'scheduled_comodines' => ['status' => 'not_queried', 'workers' => [], 'message' => ''],
                'attendance_rows' => [],
                'effective_schedules' => [],
                'effective_schedule_errors' => [],
                'journeys' => [],
                'journey_evaluation_state' => 'not_evaluated',
            ];
        };

        // Existing fixtures are scoped to their reserved demo station and date.
        // Never fall through to live API or operational roster for other context.
        if (!$reader->isDemoMode()
            || $workplaceId !== 14
            || $date !== ChecadorDailyReadService::demoReferenceDate()) {
            return $unavailable('La demostración sintética no está configurada para este centro y fecha.');
        }
        if ($requestedWorkerInvalid) {
            return $unavailable('El filtro de colaborador no es válido.');
        }

        $timezoneResult = $reader->resolveWorkplaceLocalDay($user, $workplaceId, $date);
        if (!($timezoneResult['ok'] ?? false) || !is_array($timezoneResult['data'] ?? null)) {
            return $unavailable('No fue posible cargar el escenario sintético de asistencia.');
        }
        $timezone = $timezoneResult['data'];
        $roster = (new \App\Services\ChecadorAttendanceRosterService())->currentRoster(
            $workplaceId,
            $date,
            (string) ($timezone['timezone'] ?? ''),
            true
        );
        $workers = is_array($roster['workers'] ?? null) ? $roster['workers'] : [];
        if (($roster['status'] ?? '') !== 'demo_fixture' || count($workers) !== 1) {
            return $unavailable('El padrón sintético no está disponible para este escenario.', $timezone, $roster);
        }
        $profile = $workers[0];
        $workerId = self::positiveInteger($profile['worker_id'] ?? null);
        if ($workerId === null || ($requestedWorkerId !== null && $requestedWorkerId !== $workerId)) {
            return $unavailable('El colaborador solicitado no pertenece al escenario sintético.', $timezone, $roster);
        }

        $snapshot = $reader->createDailyPunchSnapshot(
            $user,
            $workplaceId,
            $timezone,
            self::creationRequestUuid()
        );
        $snapshotData = $snapshot['data'] ?? null;
        if (!($snapshot['ok'] ?? false) || !is_array($snapshotData)
            || trim((string) ($snapshotData['snapshot_token'] ?? '')) === '') {
            return $unavailable('No fue posible cargar las marcaciones sintéticas del escenario.', $timezone, $roster);
        }

        $page = $reader->readDailyPunchSnapshotPage($user, (string) $snapshotData['snapshot_token']);
        if (!($page['ok'] ?? false) || !is_array($page['events'] ?? null)) {
            return $unavailable('No fue posible cargar las marcaciones sintéticas del escenario.', $timezone, $roster);
        }
        $events = array_values(array_filter($page['events'], static function (mixed $event) use ($workerId, $workplaceId): bool {
            return is_array($event)
                && (int) ($event['id_personal_resuelto'] ?? 0) === $workerId
                && (int) ($event['id_estacion_resuelta'] ?? 0) === $workplaceId
                && str_starts_with((string) ($event['event_uuid'] ?? ''), 'DEMO-CHECADOR');
        }));

        $scheduleResult = $reader->getEffectiveSchedule($user, $workerId, $workplaceId, $date);
        $journeyResult = $reader->evaluateJourney(
            $user,
            $workerId,
            $workplaceId,
            $date,
            (string) ($snapshotData['snapshot_audit_ref'] ?? '')
        );
        $schedule = ($scheduleResult['ok'] ?? false) ? ($scheduleResult['data'] ?? null) : null;
        $journey = ($journeyResult['ok'] ?? false) ? ($journeyResult['data'] ?? null) : null;
        $row = \App\Services\ChecadorAttendanceProjection::workerRow($profile, $events, $journey, (string) ($timezone['timezone'] ?? ''));
        $row['schedule'] = $schedule;
        $row['schedule_error'] = $schedule === null ? (string) ($scheduleResult['error']['kind'] ?? 'schedule_unavailable') : null;
        $row['worked_seconds'] = null;
        $row['worked_time_source'] = null;
        $row['incidence_count'] = null;

        return [
            'state' => 'success',
            'state_message' => '',
            'timezone' => $timezone,
            'events' => $events,
            'attendance_events' => $events,
            'attendance_coverage' => [
                'status' => 'synthetic_qa_fixture',
                'complete' => false,
                'message' => 'Datos sintéticos de demostración; no representan asistencia real.',
                'api_records_read' => count($page['events']),
            ],
            'attendance_roster' => $roster,
            'worker_options' => [[
                'worker_id' => $workerId,
                'full_name' => (string) ($profile['full_name'] ?? 'Colaborador de demostración'),
                'employee_number' => '',
            ]],
            'selected_worker_id' => $requestedWorkerId,
            'scheduled_comodines' => ['status' => 'not_queried', 'workers' => [], 'message' => ''],
            'attendance_rows' => [$workerId => $row],
            'effective_schedules' => $schedule === null ? [] : [$workerId => $schedule],
            'effective_schedule_errors' => $schedule === null ? [$workerId => (string) ($scheduleResult['error']['kind'] ?? 'schedule_unavailable')] : [],
            'journeys' => $journey === null ? [] : [$workerId => $journey],
            'journey_evaluation_state' => $journey === null ? 'unavailable' : 'evaluated',
        ];
    }

    private static function safeSnapshotRef(mixed $value): ?string
    {
        return is_string($value) && preg_match('/\A[a-f0-9]{32}\z/', $value) ? $value : null;
    }

    private static function canDisplayAttendanceRosterFallback(array $roster): bool
    {
        $status = (string) ($roster['status'] ?? '');
        $coverage = (string) ($roster['coverage'] ?? '');
        $workers = $roster['workers'] ?? null;

        $verifiedRoster = (in_array($status, ['current_roster_only', 'ready'], true) && $coverage === 'current_active_roster')
            || ($status === 'ready' && $coverage === 'historical_versioned_membership')
            || ($status === 'partial' && $coverage === 'current_roster_historical_unverified');

        return $verifiedRoster
            && is_array($workers)
            && $workers !== [];
    }

    private static function readDailySqlAttendance(
        int $workplaceId,
        string $date,
        ?int $requestedWorkerId,
        bool $requestedWorkerInvalid,
        array $authorizedWorkplaceIds,
        array $workerCatalog,
        ?array $defaultDateResolution
    ): array {
        $empty = [
            'state' => 'error',
            'state_message' => 'Las marcaciones observadas no están disponibles en este momento.',
            'sql_read_success' => false,
            'timezone' => null,
            'events' => [],
            'attendance_events' => [],
            'attendance_coverage' => ['status' => 'not_available', 'complete' => false, 'message' => 'No se confirmó la lectura SQL.'],
            'attendance_roster' => $workerCatalog,
            'roster_fallback_allowed' => self::canDisplayAttendanceRosterFallback($workerCatalog),
            'worker_options' => [],
            'worker_options_status' => (string) ($workerCatalog['status'] ?? 'unavailable'),
            'worker_options_message' => (string) ($workerCatalog['message'] ?? ''),
            'selected_worker_id' => null,
            'scheduled_comodines' => ['status' => 'not_queried', 'workers' => [], 'message' => ''],
            'attendance_rows' => [],
            'observed_only_events' => [],
            'effective_schedules' => [],
            'effective_schedule_errors' => [],
            'journey_evaluation_state' => 'not_evaluated',
        ];
        $catalogWorkers = is_array($workerCatalog['workers'] ?? null) ? $workerCatalog['workers'] : [];
        $catalogById = [];
        foreach ($catalogWorkers as $profile) {
            if (!is_array($profile)) continue;
            $id = self::positiveInteger($profile['worker_id'] ?? null);
            $name = trim((string) ($profile['full_name'] ?? ''));
            if ($id !== null && $name !== '' && $name !== 'Nombre no disponible') {
                $catalogById[$id] = $profile;
            }
        }
        $empty['worker_options'] = array_values($catalogById);
        $sortProfiles = static function (array &$profiles): void {
            usort($profiles, static function (array $left, array $right): int {
                $nameOrder = strnatcasecmp((string) ($left['full_name'] ?? ''), (string) ($right['full_name'] ?? ''));
                return $nameOrder !== 0
                    ? $nameOrder
                    : ((int) ($left['worker_id'] ?? 0) <=> (int) ($right['worker_id'] ?? 0));
            });
        };
        $sortProfiles($empty['worker_options']);
        $fallbackRows = static function (array $profiles, ?int $workerFilter): array {
            $rows = [];
            foreach ($profiles as $profile) {
                if (!is_array($profile)) continue;
                $id = self::positiveInteger($profile['worker_id'] ?? null);
                if ($id === null || ($workerFilter !== null && $id !== $workerFilter)) continue;
                $row = \App\Services\ChecadorAttendanceProjection::workerRow($profile, [], null, null);
                $row['status'] = 'punches_unavailable';
                $row['schedule'] = null;
                $row['schedule_error'] = 'persisted_schedule_not_requested';
                $row['worked_seconds'] = null;
                $row['worked_time_source'] = null;
                $row['incidence_count'] = null;
                $rows[$id] = $row;
            }
            return $rows;
        };
        $empty['attendance_rows'] = $fallbackRows($catalogWorkers, null);

        if ($requestedWorkerInvalid) {
            $empty['state'] = 'error';
            $empty['state_message'] = 'El filtro de colaborador no es válido.';
            $empty['roster_fallback_allowed'] = false;
            $empty['attendance_rows'] = [];
            return $empty;
        }

        if ($defaultDateResolution !== null) {
            if (($defaultDateResolution['ok'] ?? false) !== true) {
                $kind = (string) ($defaultDateResolution['error']['kind'] ?? 'database_unavailable');
                $empty['state'] = 'unavailable';
                $empty['state_message'] = $kind === 'timezone_unresolved'
                    ? 'No se pudo confirmar la zona horaria histórica para la fecha local actual.'
                    : 'No fue posible resolver la fecha local mediante la fuente SQL autorizada.';
                $empty['attendance_rows'] = $fallbackRows($catalogWorkers, $requestedWorkerId);
                return $empty;
            }
            if ((string) ($defaultDateResolution['data']['local_date'] ?? '') !== $date) {
                $empty['state'] = 'error';
                $empty['state_message'] = 'La fecha local no coincide con la resolución horaria confirmada.';
                $empty['roster_fallback_allowed'] = false;
                $empty['attendance_rows'] = [];
                return $empty;
            }
        }

        $reader = new ChecadorDailyAttendanceSqlReadService();
        $read = $reader->readDay($workplaceId, $date, $authorizedWorkplaceIds, $requestedWorkerId);
        if (!($read['ok'] ?? false)) {
            $kind = (string) ($read['error']['kind'] ?? 'database_unavailable');
            $empty['state'] = $kind === 'locality_not_authorized' ? 'error' : 'unavailable';
            $empty['state_message'] = match ($kind) {
                'locality_not_authorized' => 'La localidad seleccionada no está autorizada para esta consulta.',
                'timezone_unresolved' => 'No se pudo confirmar la zona horaria histórica de la localidad.',
                'station_mapping_unavailable' => 'No se pudo confirmar el vínculo único entre la estación y la localidad.',
                'schema_unavailable' => 'La estructura SQL necesaria para leer las marcaciones no está disponible.',
                'read_limit_exceeded' => 'La lectura excede el límite seguro; no se muestran resultados parciales.',
                default => 'No fue posible consultar las marcaciones persistidas. Se conserva el padrón autorizado sólo como referencia.',
            };
            if ($requestedWorkerId !== null && !isset($catalogById[$requestedWorkerId])) {
                $empty['state'] = 'error';
                $empty['state_message'] = 'El colaborador seleccionado no puede confirmarse dentro del padrón autorizado.';
                $empty['roster_fallback_allowed'] = false;
                $empty['attendance_rows'] = [];
                $empty['worker_options'] = [];
                return $empty;
            }
            $empty['selected_worker_id'] = $requestedWorkerId;
            $empty['attendance_rows'] = $fallbackRows($catalogWorkers, $requestedWorkerId);
            return $empty;
        }

        $timezone = (string) $read['timezone'];
        $dateCatalog = (new ChecadorAttendanceRosterService())->workersForAttendanceFilter(
            $workplaceId,
            $date,
            $timezone,
            false
        );
        $rosterUsable = ($dateCatalog['status'] ?? '') === 'ready'
            || (($dateCatalog['status'] ?? '') === 'partial' && ($dateCatalog['coverage'] ?? '') === 'incomplete')
            || (($dateCatalog['coverage'] ?? '') === 'current_active_roster' && (string) $read['local_date'] === $date);
        $attendanceRoster = $rosterUsable
            ? $dateCatalog
            : array_merge($dateCatalog, ['workers' => []]);
        $rosterById = [];
        foreach (is_array($attendanceRoster['workers'] ?? null) ? $attendanceRoster['workers'] : [] as $profile) {
            if (!is_array($profile)) continue;
            $id = self::positiveInteger($profile['worker_id'] ?? null);
            if ($id !== null) $rosterById[$id] = $profile;
        }

        $events = is_array($read['events'] ?? null) ? $read['events'] : [];
        $observedIds = [];
        $eventsByRosterWorker = [];
        $unrosteredEvents = [];
        $membershipResolver = new \App\Services\WorkerWorkplaceAssignmentResolver();
        foreach ($events as $event) {
            if (!is_array($event)) continue;
            $workerId = self::positiveInteger($event['id_personal_resuelto'] ?? null);
            if ($workerId === null) {
                $unrosteredEvents[] = [
                    'event' => $event,
                    'worker_label' => 'Colaborador no identificado',
                    'membership_label' => 'La identidad no está resuelta; no se atribuye a una persona.',
                ];
                continue;
            }
            $observedIds[$workerId] = true;
            $occurredAtUtc = trim((string) ($event['ocurrido_at_utc'] ?? ''));
            $membership = preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/', $occurredAtUtc) === 1
                ? $membershipResolver->resolveAssignments($workerId, str_replace(' ', 'T', $occurredAtUtc) . 'Z')
                : ['status' => 'DATA_INVALID', 'assignments' => []];
            $assignments = is_array($membership['assignments'] ?? null) ? $membership['assignments'] : [];
            $belongsToLocalityAtEvent = ($membership['status'] ?? '') === 'RESOLVED_SINGLE'
                && count($assignments) === 1
                && (int) ($assignments[0]['workplace_id'] ?? 0) === $workplaceId;
            if (isset($rosterById[$workerId]) && $belongsToLocalityAtEvent) {
                $eventsByRosterWorker[$workerId][] = $event;
                continue;
            }
            $membershipLabel = match (true) {
                ($membership['status'] ?? '') === 'RESOLVED_NONE' => 'No existe una adscripción canónica efectiva confirmada al momento del evento.',
                ($membership['status'] ?? '') === 'RESOLVED_MULTIPLE' => 'Hay varias adscripciones efectivas; el evento no se atribuye al padrón.',
                ($membership['status'] ?? '') === 'RESOLVED_SINGLE'
                    && (int) ($assignments[0]['workplace_id'] ?? 0) !== $workplaceId => 'La adscripción efectiva corresponde a otra localidad; el evento no se atribuye al padrón.',
                ($membership['status'] ?? '') === 'RESOLVED_SINGLE'
                    && (int) ($assignments[0]['workplace_id'] ?? 0) === $workplaceId => 'La adscripción histórica coincide con la localidad, pero la persona no aparece en el padrón acreditado para esta fecha.',
                ($dateCatalog['status'] ?? '') === 'partial' => 'Padrón parcial: no se pudo confirmar la adscripción histórica de este trabajador.',
                default => 'No se pudo confirmar la adscripción canónica efectiva al momento del evento.',
            };
            $unrosteredEvents[] = [
                'event' => $event,
                'worker_id' => $workerId,
                'worker_label' => 'Referencia RH ' . $workerId . ' · nombre no disponible',
                'membership_label' => $membershipLabel,
            ];
        }

        $observedProfiles = (new ChecadorAttendanceRosterService())->observedProfiles(
            array_map('intval', array_keys($observedIds)),
            false
        );
        foreach ($unrosteredEvents as &$observed) {
            $workerId = self::positiveInteger($observed['worker_id'] ?? null);
            $profile = $workerId === null ? null : ($observedProfiles[$workerId] ?? null);
            if (is_array($profile)) {
                $name = trim((string) ($profile['full_name'] ?? ''));
                if ($name !== '' && $name !== 'Nombre no disponible') {
                    $observed['worker_label'] = $name . ' · ID ' . $workerId;
                }
            }
        }
        unset($observed);

        $allowedSelectedWorker = $requestedWorkerId === null
            || isset($rosterById[$requestedWorkerId])
            || isset($observedIds[$requestedWorkerId]);
        if (!$allowedSelectedWorker) {
            $empty['state'] = 'error';
            $empty['state_message'] = 'El colaborador seleccionado no aparece en el padrón ni en las marcaciones de la localidad autorizada.';
            $empty['roster_fallback_allowed'] = false;
            $empty['attendance_rows'] = [];
            $empty['observed_only_events'] = [];
            return $empty;
        }

        $profiles = $rosterById + array_intersect_key($observedProfiles, $observedIds);
        $workerOptions = $profiles;
        $workerOptions = array_values(array_filter($workerOptions, static function (array $profile): bool {
            $name = trim((string) ($profile['full_name'] ?? ''));
            return $name !== '' && $name !== 'Nombre no disponible';
        }));
        $sortProfiles($workerOptions);
        $attendanceRows = [];
        foreach ($rosterById as $workerId => $profile) {
            if ($requestedWorkerId !== null && $requestedWorkerId !== $workerId) continue;
            $row = \App\Services\ChecadorAttendanceProjection::workerRow(
                $profile,
                $eventsByRosterWorker[$workerId] ?? [],
                null,
                $timezone
            );
            $row['schedule'] = null;
            $row['schedule_error'] = 'persisted_schedule_projection_unavailable';
            $row['worked_seconds'] = null;
            $row['worked_time_source'] = null;
            $row['incidence_count'] = null;
            $attendanceRows[$workerId] = $row;
        }
        $unrosteredEvents = array_values(array_filter($unrosteredEvents, static function (array $item) use ($requestedWorkerId): bool {
            return $requestedWorkerId === null || (int) ($item['worker_id'] ?? 0) === $requestedWorkerId;
        }));

        $scheduledComodines = ['status' => 'not_queried', 'workers' => [], 'message' => ''];
        $programmingRead = ChecadorRolComodinesService::authorizeCanonicalProgramming($workplaceId, 'read');
        if (!empty($programmingRead['allowed'])) {
            $scheduledComodines = ChecadorRolComodinesService::getCanonicalScheduledWorkersForDate(
                $workplaceId,
                $date,
                $programmingRead['station_workplace_ids'] ?? $authorizedWorkplaceIds
            );
        }
        $eventCount = count($events);
        $empty['state'] = 'success';
        $empty['state_message'] = '';
        $empty['sql_read_success'] = true;
        $empty['timezone'] = ['timezone' => $timezone, 'from_utc' => $read['from_utc'], 'to_utc' => $read['to_utc']];
        $empty['events'] = $events;
        $empty['attendance_events'] = $events;
        $empty['attendance_coverage'] = [
            'status' => 'source_completeness_unknown',
            'complete' => false,
            'message' => 'Lectura SQL viva: se presentan sólo eventos persistidos en esta consulta; no se confirma sincronización completa ni ausencia física.',
            'sql_records_read' => $eventCount,
        ];
        $empty['attendance_roster'] = $attendanceRoster;
        $empty['roster_fallback_allowed'] = false;
        $empty['worker_options'] = $workerOptions;
        $empty['worker_options_status'] = (string) ($dateCatalog['status'] ?? 'unavailable');
        $empty['worker_options_message'] = (string) ($dateCatalog['message'] ?? '');
        $empty['selected_worker_id'] = $requestedWorkerId;
        $empty['scheduled_comodines'] = $scheduledComodines;
        $empty['attendance_rows'] = $attendanceRows;
        $empty['observed_only_events'] = $unrosteredEvents;
        $empty['effective_schedules'] = [];
        $empty['effective_schedule_errors'] = array_fill_keys(array_keys($rosterById), 'persisted_schedule_projection_unavailable');
        return $empty;
    }

    private static function m06AdministrativeIncidenceSqlService(): \App\Services\ChecadorAdministrativeIncidenceSqlService
    {
        return new \App\Services\ChecadorAdministrativeIncidenceSqlService();
    }

    private static function m06WriteErrorMessage(string $error): string
    {
        return match ($error) {
            'permission_denied' => 'No tienes permiso efectivo para esta operación en la localidad seleccionada.',
            'worker_scope_unverified' => 'No se confirmó la adscripción canónica del trabajador durante todo el periodo.',
            'registration_deadline_expired' => 'El plazo de registro configurado para esta localidad ya venció.',
            'policy_unavailable', 'workplace_timezone_unresolved' => 'No se pudo confirmar la política M11 y zona horaria para el periodo.',
            'idempotency_conflict' => 'La clave de reintento ya corresponde a otros datos. No se creó otra incidencia.',
            'schema_preflight_failed' => 'La estructura SQL requerida no está disponible; no se guardó el cambio.',
            'readback_unconfirmed' => 'No se confirmó el cambio mediante lectura SQL; no se muestra éxito.',
            'worker_not_found' => 'El trabajador ya no está activo en el padrón autorizado.',
            'incidence_not_found' => 'La incidencia no existe en la localidad autorizada.',
            'stale_revision_conflict' => 'La incidencia cambió desde que la abriste. Actualiza la página antes de volver a intentar.',
            'invalid_lifecycle_transition' => 'El estado actual no permite esta acción.',
            'persisted_data_inconsistent' => 'El maestro y su última revisión no coinciden. Se detuvo el cambio para proteger el historial.',
            'invalid_request' => 'Los datos de la operación no son válidos.',
            default => 'No se pudo confirmar el cambio SQL. No se muestra éxito.',
        };
    }

    private static function loadSnapshotState(string $ref, int $userId, int $workplaceId, string $date): ?array
    {
        $states = Session::get('checador_level1_snapshots', []);
        $state = is_array($states) ? ($states[$ref] ?? null) : null;
        if (!is_array($state)
            || (int) ($state['user_id'] ?? 0) !== $userId
            || (int) ($state['workplace_id'] ?? 0) !== $workplaceId
            || (string) ($state['date'] ?? '') !== $date) return null;
        $expiresAt = strtotime((string) ($state['snapshot']['expires_at'] ?? ''));
        return $expiresAt !== false && $expiresAt <= time() ? ['expired' => true] : $state;
    }

    private static function saveSnapshotState(string $ref, array $state): void
    {
        $states = Session::get('checador_level1_snapshots', []);
        if (!is_array($states)) $states = [];
        $states[$ref] = $state;
        Session::set('checador_level1_snapshots', $states);
    }
}
