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
use App\Models\Estacion;
use App\Services\ChecadorAccessService;
use App\Services\ChecadorAttendanceRosterService;
use App\Services\ChecadorDailyReadService;
use App\Services\ChecadorConfigurationReadService;
use App\Services\ChecadorOperationalPeriod;
use App\Services\ChecadorPresentationFormatter;
use App\Services\ChecadorPayrollComparisonReadService;
use App\Services\ChecadorPersistentQaFixtureService;
use App\Services\ChecadorRawPunchReadService;
use App\Services\ChecadorWorkplaceCoverageApiService;
use App\Services\ChecadorV2PermissionService;
use App\Services\ModuleStationService;
use App\Services\RecibosNominaService;
use App\Services\RolComodinesService;

final class ChecadorController extends BaseController
{
    /** @var null|\Closure():\DateTimeImmutable */
    private ?\Closure $utcClock;

    public function __construct(?callable $utcClock = null)
    {
        parent::__construct();
        $this->utcClock = $utcClock === null ? null : \Closure::fromCallable($utcClock);
    }

    public function index(): void
    {
        $query = Request::query();
        if (Request::method() === 'GET'
            && ($query['schedule_week'] ?? '') === '1'
            && ($query['schedule_history'] ?? '') !== '1') {
            self::redirect(self::scheduleWeekHref($query));
        }
        $this->normalizeLegacyWorkplaceUrl();
        $query = Request::query();
        if (($query['schedule_history'] ?? '') === '1') {
            $legacyHistoryQuery = [];
            foreach (['date', 'worker_id'] as $key) {
                if (isset($query[$key]) && is_scalar($query[$key]) && trim((string) $query[$key]) !== '') {
                    $legacyHistoryQuery[$key] = trim((string) $query[$key]);
                }
            }
            $legacyPage = self::positiveInteger($query['schedule_history_page'] ?? null);
            if ($legacyPage !== null && $legacyPage > 1) $legacyHistoryQuery['page'] = $legacyPage;
            $target = '/departamento-operativo/recursos-humanos/checador/horarios-turnos';
            self::redirect($target . ($legacyHistoryQuery === [] ? '' : '?' . http_build_query($legacyHistoryQuery)));
        }
        // The module entry route is a lightweight directory. Operational
        // attendance is available only through its dedicated workspace route.
        $this->renderModuleDirectory();
    }

    /** Dedicated institutional M04 route; reuses the existing authorized read workspace. */
    public function scheduleHistory(): void
    {
        $this->normalizeLegacyWorkplaceUrl();
        $this->renderDashboardWorkspace(null, true);
    }

    /** Canonical M03 route; only the week filter is accepted from the URL. */
    public function scheduleWeek(): void
    {
        $query = Request::query();
        $safeQuery = self::scheduleWeekQueryParameters($query);
        if ($query !== $safeQuery) {
            self::redirect(self::scheduleWeekHref($query));
        }

        $this->normalizeLegacyWorkplaceUrl();
        $this->renderDashboardWorkspace(null, false, true);
    }

    /** Read one authorized worker's complete assignment history on demand for M03. */
    public function habitualAssignmentReadback(): void
    {
        $query = Request::query();
        $workerId = filter_var($query['worker_id'] ?? null, FILTER_VALIDATE_INT);
        $workplaceId = filter_var($query['workplace_id'] ?? null, FILTER_VALIDATE_INT);
        $weekStart = is_string($query['week_start'] ?? null) ? trim($query['week_start']) : '';
        $dates = \App\Services\ChecadorWeeklyScheduleService::dates($weekStart);
        $user = Auth::user();
        $deny = static function (string $code, string $message, int $status): never {
            JsonResponse::send(['ok' => false, 'code' => $code, 'message' => $message], $status, ['Cache-Control' => 'private, no-store']);
        };

        if ($user === null) $deny('unauthenticated', 'La sesión ya no es válida.', 401);
        if (!is_int($workerId) || $workerId < 1 || !is_int($workplaceId) || $workplaceId < 1
            || $dates === [] || \App\Services\ChecadorWeeklyScheduleService::monday($weekStart) !== $weekStart) {
            $deny('invalid_request', 'Revisa el trabajador, la estación y la semana seleccionada.', 422);
        }
        if (ChecadorDailyReadService::demoModeEnabled()
            || !ChecadorDailyReadService::scheduleApiPostOperationsEnabled()
            || !(ChecadorAccessService::authorizeScheduling($workplaceId, 'editar')['allowed'] ?? false)) {
            $deny('forbidden', 'No tienes permiso para administrar horarios en esta estación.', 403);
        }

        try {
            $reader = new ChecadorDailyReadService();
            $timezoneResult = $reader->resolveWorkplaceLocalDay($user, $workplaceId, $weekStart);
            if (($timezoneResult['ok'] ?? false) !== true) $deny('workplace_unavailable', 'No se pudo confirmar la estación autorizada.', 502);
            $timezone = (string) ($timezoneResult['data']['timezone'] ?? '');
            if (!\App\Services\ChecadorOperationalPeriod::isIanaTimezone($timezone)) {
                $deny('workplace_unavailable', 'No se pudo confirmar la zona horaria de la estación.', 502);
            }

            $rosterService = new \App\Services\ChecadorAttendanceRosterService();
            $roster = $rosterService->workersForScheduleWeek($workplaceId, $weekStart, $timezone, false);
            $worker = null;
            foreach ($roster['workers'] ?? [] as $candidate) {
                if ((int) ($candidate['worker_id'] ?? 0) === $workerId) {
                    $worker = $candidate;
                    break;
                }
            }
            if (!is_array($worker)) $deny('membership_unverified', 'La adscripción del trabajador no está confirmada para esta semana.', 403);

            $eligible = \App\Services\ChecadorWeeklyScheduleService::workersWithAnyVerifiedMembership(
                [$worker],
                array_column($dates, 'date')
            );
            if ($eligible === []) $deny('membership_unverified', 'La adscripción del trabajador no está confirmada en la semana seleccionada.', 403);

            // The recurring pattern may start midweek. Weekdays that already
            // passed in the selected week first apply on their next occurrence.
            // Read that following week as well so the form never enables an
            // assignment or duplicate without date-specific canonical evidence.
            $nextWeekStart = (new \DateTimeImmutable($weekStart, new \DateTimeZone('UTC')))
                ->modify('+7 days')->format('Y-m-d');
            $nextRoster = $rosterService->workersForScheduleWeek($workplaceId, $nextWeekStart, $timezone, false);
            $nextWorker = null;
            foreach ($nextRoster['workers'] ?? [] as $candidate) {
                if ((int) ($candidate['worker_id'] ?? 0) === $workerId) {
                    $nextWorker = $candidate;
                    break;
                }
            }
            $verifiedDates = array_values(array_unique(array_merge(
                is_array($worker['versioned_verified_dates'] ?? null) ? $worker['versioned_verified_dates'] : [],
                is_array($nextWorker['versioned_verified_dates'] ?? null) ? $nextWorker['versioned_verified_dates'] : []
            )));
            sort($verifiedDates, SORT_STRING);

            $read = $reader->getAllShiftAssignmentsSnapshot($user, $workerId, $workplaceId, null, 200);
            if (($read['ok'] ?? false) !== true || ($read['data']['complete'] ?? false) !== true) {
                $status = (int) ($read['error']['status'] ?? 0);
                $deny('assignment_read_incomplete', 'No se pudo confirmar el historial completo del trabajador. No se habilitó el formulario.', $status === 429 ? 429 : 502);
            }

            $weekdayLabels = [];
            foreach ($dates as $day) $weekdayLabels[$day['weekday']] = $day['label'];
            $existingDays = [];
            $existingDaysByDate = [];
            $replacementSources = [];
            $assignmentDates = array_merge($dates, \App\Services\ChecadorWeeklyScheduleService::dates($nextWeekStart));
            foreach ($assignmentDates as $day) {
                $matches = array_values(array_filter($read['data']['items'], static function (array $assignment) use ($day): bool {
                    $validFrom = (string) ($assignment['valido_desde'] ?? '');
                    $validTo = $assignment['valido_hasta'] ?? null;
                    $active = (int) ($assignment['es_activo'] ?? 0) === 1;
                    $closedHistory = !$active && is_string($validTo) && $validTo > $validFrom;
                    return ($active || $closedHistory)
                        && strtoupper((string) ($assignment['tipo_asignacion'] ?? 'HABITUAL')) !== 'TEMPORAL'
                        && (string) ($assignment['dia_semana'] ?? '') === $day['weekday']
                        && $validFrom <= $day['date']
                        && ($validTo === null || $day['date'] < (string) $validTo);
                }));
                if (count($matches) > 1) $deny('assignment_conflict', 'El historial contiene asignaciones incompatibles. No se habilitó el formulario.', 409);
                if ($matches === []) continue;

                $assignment = $matches[0];
                $weekday = (string) $day['weekday'];
                $templateId = $assignment['id_plantilla'] ?? null;
                $existingValue = $templateId === null ? 'REST' : (string) $templateId;
                $existingDaysByDate[(string) $day['date']] = $existingValue;
                if ((string) $day['date'] < $nextWeekStart) $existingDays[$weekday] = $existingValue;
                if ((int) ($assignment['es_activo'] ?? 0) === 1 && (string) ($day['date'] ?? '') < $nextWeekStart) {
                    $replacementSources[] = [
                        'id' => (int) ($assignment['id'] ?? 0),
                        'weekday' => $weekday,
                        'weekday_label' => (string) ($weekdayLabels[$weekday] ?? $weekday),
                        'label' => $templateId === null ? 'Descanso explícito' : 'Turno habitual',
                        'valid_from' => (string) ($assignment['valido_desde'] ?? ''),
                        'valid_to' => $assignment['valido_hasta'] ?? null,
                    ];
                }
            }

            JsonResponse::send([
                'ok' => true,
                'data' => [
                    'complete' => true,
                    'existing_days' => $existingDays,
                    'existing_days_by_date' => $existingDaysByDate,
                    'verified_dates' => $verifiedDates,
                    'habitual_complete' => count($existingDays) === count($dates),
                    'replacement_sources' => $replacementSources,
                ],
            ], 200, ['Cache-Control' => 'private, no-store']);
        } catch (\Throwable) {
            $deny('assignment_read_unavailable', 'No se pudo completar la lectura de asignaciones. No se habilitó el formulario.', 502);
        }
    }

    public function dailyAttendance(): void
    {
        if (Request::method() === 'POST') {
            $contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
            $requestedWith = strtoupper(trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
            if (!str_starts_with($contentType, 'application/json') || $requestedWith !== 'XMLHTTPREQUEST') {
                JsonResponse::send([
                    'success' => false,
                    'error' => ['code' => 'invalid_request_format', 'message' => 'La solicitud de búsqueda no tiene un formato válido.'],
                ], 415, ['Cache-Control' => 'private, no-store']);
            }

            $payload = Request::json();
            $payloadKeys = array_keys($payload);
            sort($payloadKeys);
            if ($payloadKeys !== ['date', 'worker_id']) {
                JsonResponse::send([
                    'success' => false,
                    'error' => ['code' => 'invalid_search_payload', 'message' => 'Selecciona una fecha y un filtro de colaborador válidos.'],
                ], 400, ['Cache-Control' => 'private, no-store']);
            }

            $date = $payload['date'];
            $workerId = $payload['worker_id'];
            $validDate = $date === null
                || (is_string($date)
                    && preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date) === 1
                    && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4)));
            $validWorker = $workerId === null
                || $workerId === ''
                || self::positiveInteger($workerId) !== null;
            if (!$validDate || !$validWorker) {
                JsonResponse::send([
                    'success' => false,
                    'error' => ['code' => 'invalid_search_filter', 'message' => 'La fecha o el colaborador seleccionado no son válidos.'],
                ], 422, ['Cache-Control' => 'private, no-store']);
            }

            // Route::auth has already checked the Portal3 session and CSRF token.
            // The existing renderer resolves locality exclusively from that session.
            ob_start();
            $this->renderDashboardWorkspace('daily-attendance');
            $html = (string) ob_get_clean();
            $status = http_response_code();
            if ($status !== false && $status >= 400) {
                JsonResponse::send([
                    'success' => false,
                    'error' => [
                        'code' => $status === 403 ? 'attendance_forbidden' : 'attendance_read_failed',
                        'message' => $status === 403
                            ? 'No se autorizó la consulta para el centro de trabajo seleccionado.'
                            : 'No se pudo completar la consulta de asistencia. Intenta nuevamente.',
                    ],
                ], $status, ['Cache-Control' => 'private, no-store']);
            }

            if (!str_contains($html, 'id="container"') || !str_contains($html, 'data-checador-attendance-results')) {
                JsonResponse::send([
                    'success' => false,
                    'error' => ['code' => 'attendance_fragment_unavailable', 'message' => 'El servidor no devolvió una vista de asistencia utilizable. Intenta nuevamente.'],
                ], 500, ['Cache-Control' => 'private, no-store']);
            }

            JsonResponse::send([
                'success' => true,
                'html' => $html,
            ], 200, ['Cache-Control' => 'private, no-store']);
        }

        $this->renderDashboardWorkspace('daily-attendance');
    }

    /** Station-scoped Rol de Comodines view with controlled canonical programming readiness. */
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

        $this->normalizeLegacyWorkplaceUrl();
        $query = Request::query();
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
        $workplaceId = $this->sessionWorkplaceId($access);
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
            : RolComodinesService::authorizeCanonicalProgramming($workplaceId, 'read');
        $readModel = $workplaceId === null || empty($roleReadAuthorization['allowed'])
            ? ['ok' => false, 'error' => $workplaceId === null ? 'station_selection_required' : 'legacy_read_not_authorized']
            : RolComodinesService::getChecadorReadModel(
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
        $coverageWriterEnabled = \App\Services\RolComodinesPortalWriterService::writesEnabled()
            && \App\Core\Database::isLocalDevelopmentDatabaseConnection();
        // The shared MariaDB is the canonical record. The Portal3 administration UI
        // must not depend on the API HTTP service to list or mutate coverage records.
        $coverageRead = $coverageWriter->listVisible($authorizedWorkplaceIds);
        $coverageReadMessage = match ((string) ($coverageRead['code'] ?? '')) {
            'coverage_scope_denied' => 'No se pudieron consultar coberturas fuera del alcance autorizado.',
            default => !empty($coverageRead['ok']) ? '' : 'No fue posible consultar las coberturas canónicas en este momento.',
        };
        $coverageRows = !empty($coverageRead['ok'])
            ? RolComodinesService::coveragePresentationRows($coverageRead['data']['items'] ?? [])
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
        $coverageWorkplaces = $coverageWriterEnabled && $canRequestCoverageGrant && $workplaceId !== null
            ? array_values(array_filter(
                RolComodinesService::coverageWorkplaces(0),
                static fn(array $workplace): bool => in_array((int) ($workplace['id'] ?? 0), $authorizedWorkplaceIds, true)
            ))
            : [];
        $coverageTemplates = [];
        $coverageTemplateCatalogReady = false;
        if ($coverageWriterEnabled && $canRequestCoverageGrant && $workplaceId !== null && !empty($coverageRead['ok'])) {
            $catalog = $coverageWriter->templateCatalog();
            if (!empty($catalog['ok']) && !empty($catalog['data']['complete'])) {
                $coverageTemplates = $catalog['data']['items'] ?? [];
                $coverageTemplateCatalogReady = true;
            }
        }
        $canRequestCoverage = $coverageWriterEnabled
            && $canRequestCoverageGrant
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
                'coverageWriterEnabled' => $coverageWriterEnabled,
                'coverageWorkplaces' => $coverageWorkplaces,
                'coverageTemplates' => $coverageTemplates,
                'canRequestCoverage' => $canRequestCoverage,
                'canReviewCoverage' => $coverageWriterEnabled && $canApproveCoverageGrant && !empty($coverageRead['ok']),
                'canCancelCoverage' => $coverageWriterEnabled && $canCancelCoverageGrant && !empty($coverageRead['ok']),
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
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.role-comodines.programming.init.js?v=20261110-1',
                ],
                'help' => false,
            ],
            'departamento-operativo'
        );
    }

    public function journeys(): void
    {
        $this->renderDashboardWorkspace('journeys');
    }

    public function incidences(): void
    {
        $this->renderDashboardWorkspace('incidences');
    }

    public function adjustments(): void
    {
        $this->renderDashboardWorkspace('adjustments');
    }

    /** Same-origin server-side DataTables read; API V2 remains the data owner. */
    public function administrativeAdjustmentsData(): void
    {
        $draw = self::nonNegativeInteger(Request::query()['draw'] ?? 0) ?? 0;
        $user = Auth::user();
        if ($user === null) self::adjustmentsDataError(401, 'Inicia sesión nuevamente.', $draw);

        $query = Request::query();
        $workplaceId = self::positiveInteger($query['workplace_id'] ?? null);
        $dateFrom = is_string($query['date_from'] ?? null) ? trim($query['date_from']) : '';
        $dateTo = is_string($query['date_to'] ?? null) ? trim($query['date_to']) : '';
        $workerId = ($query['worker_id'] ?? '') === '' ? null : self::positiveInteger($query['worker_id'] ?? null);
        $type = is_string($query['adjustment_type'] ?? null) ? trim($query['adjustment_type']) : '';
        $status = is_string($query['status'] ?? null) ? trim($query['status']) : '';
        $start = self::nonNegativeInteger($query['start'] ?? 0);
        $length = self::positiveInteger($query['length'] ?? 25);
        if ($workplaceId === null || !self::isCivilDate($dateFrom) || !self::isCivilDate($dateTo)
            || ($query['worker_id'] ?? '') !== '' && $workerId === null
            || $start === null || $length === null || $length > 100) {
            self::adjustmentsDataError(400, 'Revisa los filtros y el tamaño de página.', $draw);
        }
        $current = ChecadorAccessService::authorize($workplaceId);
        if (empty($current['allowed'])) self::adjustmentsDataError(403, 'La localidad seleccionada no está autorizada.', $draw);
        $page = intdiv($start, $length) + 1;
        $result = (new ChecadorDailyReadService())->readAdministrativeAdjustments($user, [
            'workplace_id' => $workplaceId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'worker_id' => $workerId,
            'adjustment_type' => $type,
            'status' => $status,
            'page' => $page,
            'per_page' => $length,
        ]);
        if (!($result['ok'] ?? false)) {
            $error = $result['error'] ?? [];
            $statusCode = (int) ($error['status'] ?? 503);
            $message = match ((string) ($error['kind'] ?? '')) {
                'bad_request' => 'Los filtros no son válidos.',
                'station_scope_denied', 'function_permission_denied', 'api_authorization' => 'No tienes acceso a esta consulta.',
                'worker_not_found_or_out_of_scope' => 'El colaborador no está disponible para esta localidad y periodo.',
                'source_unavailable' => 'La fuente de ajustes no está disponible.',
                default => 'No fue posible consultar los ajustes. Inténtalo nuevamente.',
            };
            self::adjustmentsDataError($statusCode, $message, $draw);
        }

        $rows = $result['data']['adjustments'];
        $rosterNames = [];
        // M06 uses the period end as the canonical roster reference. Return
        // that same scoped roster with the table response so M07's modal can
        // refresh worker choices when an applied date period changes.
        $roster = (new ChecadorAttendanceRosterService())->workersForAttendanceFilter($workplaceId, $dateTo);
        $workerRosterStatus = (string) ($roster['status'] ?? 'unavailable');
        $workerOptions = is_array($roster['workers'] ?? null) ? $roster['workers'] : [];
        if (in_array($workerRosterStatus, ['ready', 'partial', 'current_roster_only'], true)) {
            foreach ($workerOptions as $profile) {
                if (!is_array($profile)) continue;
                $workerId = (int) ($profile['worker_id'] ?? 0);
                $workerName = trim((string) ($profile['full_name'] ?? ''));
                if ($workerId > 0 && $workerName !== '') $rosterNames[$workerId] = $workerName;
            }
        }
        $workplaceLabel = $selectedWorkplaceName = '';
        foreach (($current['workplaces'] ?? []) as $workplaceOption) {
            if ((int) ($workplaceOption['id'] ?? 0) === $workplaceId) {
                $workplaceLabel = (string) ($workplaceOption['nombre'] ?? '');
                break;
            }
        }
        $data = array_map(static function (array $item) use ($rosterNames): array {
            $worker = (int) $item['worker_id'];
            return $item + [
                'worker_label' => $rosterNames[$worker] ?? 'Personal #' . $worker,
                'created_by_label' => 'Usuario #' . (int) $item['created_by_user_id'],
                'approved_by_label' => 'Usuario #' . (int) $item['approved_by_user_id'],
                'adjustment_type_label' => ChecadorPresentationFormatter::administrativeAdjustmentTypeLabel($item['adjustment_type']),
                'status_label' => ChecadorPresentationFormatter::administrativeAdjustmentStatusLabel($item['status']),
            ];
        }, $rows);
        foreach ($data as &$row) $row['workplace_label'] = $workplaceLabel !== '' ? $workplaceLabel : 'Localidad autorizada';
        unset($row);
        JsonResponse::send([
            'draw' => $draw,
            'recordsTotal' => $result['data']['total_records'],
            'recordsFiltered' => $result['data']['total_records'],
            'data' => $data,
            'worker_options' => $workerOptions,
            'worker_roster_status' => $workerRosterStatus,
            'as_of_utc' => $result['data']['as_of_utc'],
            'total_pages' => $result['data']['total_pages'],
        ], 200, ['Cache-Control' => 'private, no-store']);
    }

    /** Read-only same-origin detail endpoint for one canonical adjustment UUID. */
    public function administrativeAdjustmentDetail(string $uuidAjuste): void
    {
        $user = Auth::user();
        if ($user === null) JsonResponse::send(['error' => 'Inicia sesión nuevamente.'], 401, ['Cache-Control' => 'private, no-store']);
        $workplaceId = self::positiveInteger(Request::query()['workplace_id'] ?? null);
        if ($workplaceId === null || empty(ChecadorAccessService::authorize($workplaceId)['allowed'])) {
            JsonResponse::send(['error' => 'La localidad seleccionada no está autorizada.'], 403, ['Cache-Control' => 'private, no-store']);
        }
        $result = (new ChecadorDailyReadService())->readAdministrativeAdjustmentDetail($user, $uuidAjuste);
        if (!($result['ok'] ?? false)) {
            $error = $result['error'] ?? [];
            $statusCode = (int) ($error['status'] ?? 503);
            $message = match ((string) ($error['kind'] ?? '')) {
                'bad_request' => 'La referencia del ajuste no es válida.',
                'not_found', 'adjustment_not_found' => 'El ajuste no existe o no está disponible en esta localidad.',
                'station_scope_denied', 'function_permission_denied', 'api_authorization' => 'No tienes acceso a este ajuste.',
                default => 'No fue posible consultar el detalle del ajuste.',
            };
            JsonResponse::send(['error' => $message], $statusCode, ['Cache-Control' => 'private, no-store']);
        }
        if ((int) ($result['data']['adjustment']['workplace_id'] ?? 0) !== $workplaceId) {
            JsonResponse::send(['error' => 'El ajuste no existe o no está disponible en esta localidad.'], 404, ['Cache-Control' => 'private, no-store']);
        }
        $detail = $result['data'];
        $adjustment = $detail['adjustment'];
        $roster = (new ChecadorAttendanceRosterService())->workersForAttendanceFilter(
            $workplaceId,
            (string) $adjustment['work_date_local']
        );
        $workerLabel = 'Personal #' . (int) $adjustment['worker_id'];
        if (($roster['status'] ?? '') === 'success') {
            foreach ($roster['workers'] as $profile) {
                if ((int) $profile['worker_id'] === (int) $adjustment['worker_id']) {
                    $workerLabel = (string) $profile['full_name'];
                    break;
                }
            }
        }
        $detail['adjustment']['worker_label'] = $workerLabel;
        foreach ($detail['revision_history'] as &$revision) $revision['created_by_label'] = 'Usuario #' . (int) $revision['created_by_user_id'];
        unset($revision);
        JsonResponse::send(['data' => $detail], 200, ['Cache-Control' => 'private, no-store']);
    }

    private static function adjustmentsDataError(int $status, string $message, int $draw): never
    {
        JsonResponse::send(['draw' => max(0, $draw), 'error' => $message], $status, ['Cache-Control' => 'private, no-store']);
    }

    public function temporaryAccess(): void
    {
        $this->renderDashboardWorkspace('temporary-access');
    }

    /** Render a lightweight entry page; operational data is loaded only by its workspace route. */
    private function renderModuleDirectory(): void
    {
        $this->normalizeLegacyWorkplaceUrl();
        $query = Request::query();
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
        $requiresExplicitWorkplaceSelection = (int) ($access['position_id'] ?? 0) === 13;
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
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.module-station.init.js?v=20261027-1',
                ],
                'help' => false,
            ],
            'departamento-operativo'
        );
    }

    /** Build the institutional cards; each optional capability uses its existing read gate. */
    private function buildModuleNavigation(?int $workplaceId, array $access): array
    {
        $base = '/departamento-operativo/recursos-humanos/checador';
        $queryString = static fn(string $path, array $params): string => $path
            . ($params === [] ? '' : '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
        $scheduleRead = $workplaceId !== null
            && (ChecadorAccessService::authorizeScheduling($workplaceId, 'leer')['allowed'] ?? false) === true;
        $departmentSelected = $workplaceId !== null && ChecadorAccessService::isDepartmentLocality($workplaceId);
        $m04TemplateRead = $workplaceId !== null
            && ($departmentSelected
                ? (ChecadorAccessService::authorizeM04TemplateRead($workplaceId)['allowed'] ?? false)
                : $scheduleRead);
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

        // Keep existing PIN availability for other profiles, but do not expose
        // the card in Dirección's Checador directory.
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

    private function renderDashboardWorkspace(
        ?string $workspace,
        bool $dedicatedScheduleHistory = false,
        bool $dedicatedScheduleWeek = false
    ): void
    {
        // M07 can render a one-time PIN from session feedback. Prevent browser,
        // proxy, and intermediary caches from retaining that response (or its
        // access-denied/legacy-redirect variants).
        if ($workspace === 'temporary-access') {
            self::applyTemporaryAccessNoStoreHeaders();
        }
        // A non-M07 workspace may clean only an already-expired or malformed
        // PIN value. Live feedback remains untouched for its matching M07 GET.
        self::discardExpiredTemporaryAccessPinFeedback();
        $this->normalizeLegacyWorkplaceUrl();
        $query = $workspace === 'daily-attendance' && Request::method() === 'POST'
            ? Request::json()
            : Request::query();
        if ($workspace === 'daily-attendance'
            && Request::method() === 'POST'
            && array_key_exists('worker_id', $query)
            && $query['worker_id'] === null) {
            $query['worker_id'] = '';
        }
        $scheduleHistoryMode = $workspace === null && ($dedicatedScheduleHistory || ($query['schedule_history'] ?? '') === '1');
        $scheduleWeekMode = $workspace === null && $dedicatedScheduleWeek;
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        $workspaceTitles = [
            'daily-attendance' => 'Asistencia del día',
            'journeys' => 'Jornadas laborales',
            'incidences' => 'Incidencias',
            'adjustments' => 'Ajustes administrativos',
            'temporary-access' => 'PIN temporal',
        ];

        $requestedWorkplace = null;
        $snapshotRefProvided = array_key_exists('snapshot_ref', $query);
        $snapshotRef = self::safeSnapshotRef($query['snapshot_ref'] ?? null);
        $access = ChecadorAccessService::authorize(null);
        if (!$access['allowed']) {
            self::renderAccessDenied((string) ($access['reason'] ?? 'authorization_lookup_failed'), $requestedWorkplace);
            return;
        }
        $rawContext = ModuleStationService::getContext(ChecadorAccessService::MODULE_KEY);
        $currentContextWorkplaceId = self::positiveInteger($rawContext['id_depto'] ?? $rawContext['id_estacion'] ?? null);
        $isDepartmentSelected = $currentContextWorkplaceId !== null
            && ChecadorAccessService::isDepartmentLocality($currentContextWorkplaceId);
        $allAuthorizedWorkplaces = $access['workplaces'];

        if ($scheduleHistoryMode || $scheduleWeekMode) {
            if ($isDepartmentSelected && ($scheduleHistoryMode || $scheduleWeekMode)) {
                // Preserve an explicitly authorized department context. M04
                // reads its local template catalog; M03 renders its guarded
                // department-unavailable state until its complete runtime
                // prerequisites (including an authoritative timezone) exist.
                $access = ChecadorAccessService::authorize($currentContextWorkplaceId);
            } else {
                $scheduleAccess = ChecadorAccessService::authorizeScheduling(null, 'leer');
                if (!($scheduleAccess['allowed'] ?? false)) {
                    self::renderAccessDenied((string) ($scheduleAccess['reason'] ?? 'scheduling_permission_denied'), null);
                    return;
                }
                // Only physical station localities belong to M03 scheduling; the
                // Checador menu may also include department-level read exceptions.
                $access = $scheduleAccess;
            }
        }
        $workplaces = ($isDepartmentSelected && ($scheduleHistoryMode || $scheduleWeekMode))
            ? $allAuthorizedWorkplaces
            : $access['workplaces'];
        $authorizedStationWorkplaceIds = array_values(array_map(
            static fn(array $item): int => (int) ($item['id'] ?? 0),
            is_array($access['station_workplaces'] ?? null) ? $access['station_workplaces'] : []
        ));
        $requestedWorkplace = $this->sessionWorkplaceId($access);
        $requestedWorkerValue = $query['worker_id'] ?? null;
        $requestedWorker = self::positiveInteger($requestedWorkerValue);
        $requestedWorkerInvalid = array_key_exists('worker_id', $query)
            && !(is_scalar($requestedWorkerValue) && trim((string) $requestedWorkerValue) === '')
            && $requestedWorker === null;
        if ($requestedWorkplace !== null
            && empty(ChecadorAccessService::authorize($requestedWorkplace)['allowed'])) {
            self::renderAccessDenied('requested_station_out_of_scope', $requestedWorkplace);
            return;
        }
        // M05 must recheck its own narrow read capability before the timezone
        // or PunchEvent GETs are issued. The menu grant alone is not sufficient.
        if ($workspace === 'journeys') {
            $journeyReadAccess = ChecadorAccessService::authorizeJourneyRead($requestedWorkplace);
            if (!($journeyReadAccess['allowed'] ?? false)) {
                self::renderAccessDenied((string) ($journeyReadAccess['reason'] ?? 'journey_read_permission_denied'), $requestedWorkplace);
                return;
            }
        }
        if ($workspace === 'incidences') {
            $incidenceReadAccess = ChecadorAccessService::authorizeIncidenceRead($requestedWorkplace);
            if (!($incidenceReadAccess['allowed'] ?? false)) {
                self::renderAccessDenied((string) ($incidenceReadAccess['reason'] ?? 'incidence_read_permission_denied'), $requestedWorkplace);
                return;
            }
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
        // API response fixtures remain CLI-only.
        $demoMode = ChecadorDailyReadService::demoModeEnabled();
        $realApiPostOperationsEnabled = ChecadorDailyReadService::realApiPostOperationsEnabled();
        $temporaryAccessPostOperationsEnabled = ChecadorDailyReadService::temporaryAccessPostOperationsEnabled();
        $p3gBApiPostOperationsEnabled = ChecadorDailyReadService::p3gBPostOperationsEnabled();
        $apiPostOperationsEnabled = $demoMode || $realApiPostOperationsEnabled;
        $canViewTemporaryPin = $workspace === 'temporary-access'
            && !$demoMode
            && $temporaryAccessPostOperationsEnabled
            && (ChecadorAccessService::authorizeTemporaryAccess($requestedWorkplace, 'crear')['allowed'] ?? false) === true;
        $demoScenario = ChecadorDailyReadService::configuredDemoScenario();

        $date = isset($query['date']) && is_string($query['date']) ? trim($query['date']) : '';
        $journeyDefaultDateError = false;
        if ($workspace === 'adjustments' && !self::isCivilDate($date) && $requestedWorkplace !== null && ($adjustmentUser = Auth::user()) !== null) {
            $nowUtc = $this->nowUtc();
            $candidateDate = $nowUtc->format('Y-m-d');
            $date = $candidateDate;
            try {
                $localDay = (new ChecadorDailyReadService())
                    ->resolveWorkplaceLocalDay($adjustmentUser, $requestedWorkplace, $candidateDate);
                if (($localDay['ok'] ?? false) === true) {
                    $localToday = ChecadorOperationalPeriod::currentLocalDate(
                        (string) ($localDay['data']['timezone'] ?? ''),
                        $nowUtc
                    );
                    if ($localToday !== null) $date = $localToday;
                }
            } catch (\Throwable) {
                // Keep a valid visible date; the roster remains unavailable or
                // current-only unless its canonical source can verify scope.
            }
        }
        if ($workspace === 'journeys' && $date === '') {
            $nowUtc = $this->nowUtc();
            $candidateDate = $nowUtc->format('Y-m-d');
            $date = $candidateDate;
            $journeyUser = Auth::user();
            if ($requestedWorkplace !== null && $journeyUser !== null) {
                try {
                    $candidateTimezone = (new ChecadorDailyReadService())
                        ->resolveWorkplaceLocalDay($journeyUser, $requestedWorkplace, $candidateDate);
                    if (($candidateTimezone['ok'] ?? false) !== true) {
                        $journeyDefaultDateError = true;
                    } else {
                        $localToday = ChecadorOperationalPeriod::currentLocalDate(
                            (string) ($candidateTimezone['data']['timezone'] ?? ''),
                            $nowUtc
                        );
                        if ($localToday === null) {
                            $journeyDefaultDateError = true;
                        } else {
                            $date = $localToday;
                        }
                    }
                } catch (\Throwable) {
                    $journeyDefaultDateError = true;
                }
            }
        }
        $qaFixtureService = new ChecadorPersistentQaFixtureService();
        $incidenceDefaultDateError = false;
        $incidenceDefaultTimezoneResolution = null;
        if ($workspace === 'incidences' && $date === '') {
            if ($requestedWorkplace !== null && ($incidenceUser = Auth::user()) !== null) {
                $nowUtc = $this->nowUtc();
                $candidateDate = $nowUtc->format('Y-m-d');
                try {
                    $incidenceDefaultTimezoneResolution = (new ChecadorDailyReadService())
                        ->resolveWorkplaceLocalDay($incidenceUser, $requestedWorkplace, $candidateDate);
                    if (($incidenceDefaultTimezoneResolution['ok'] ?? false) === true) {
                        $localToday = ChecadorOperationalPeriod::currentLocalDate(
                            (string) ($incidenceDefaultTimezoneResolution['data']['timezone'] ?? ''),
                            $nowUtc
                        );
                        if ($localToday !== null) {
                            $date = $localToday;
                        } else {
                            $incidenceDefaultDateError = true;
                        }
                    } else {
                        $incidenceDefaultDateError = true;
                    }
                } catch (\Throwable) {
                    $incidenceDefaultDateError = true;
                }
                if ($date === '') $date = $candidateDate;
            }
        }
        $defaultTimezoneResolution = null;
        $attendanceTimezoneResolution = null;
        if (($workspace === null || $workspace === 'daily-attendance')
            && !$scheduleHistoryMode && $requestedWorkplace !== null && $date === '') {
            if ($demoMode) {
                // The existing ready fixtures are contract-shaped for this
                // fixed reference date. The discrete Demo marker below makes
                // clear that this is not the calendar's real current day.
                $date = ChecadorDailyReadService::demoReferenceDate();
            } else {
                // Seed from UTC, then let the API-owned IANA timezone determine
                // today's local work date. The normal read reuses this response
                // when its date matches, or resolves the corrected date once.
                $nowUtc = $this->nowUtc();
                $candidateDate = $nowUtc->format('Y-m-d');
                $date = $candidateDate;
                $defaultUser = Auth::user();
                if ($defaultUser) {
                    try {
                        $defaultReader = new ChecadorDailyReadService();
                        $defaultTimezoneResolution = $defaultReader->resolveWorkplaceLocalDay(
                            $defaultUser,
                            $requestedWorkplace,
                            $candidateDate
                        );
                        if (($defaultTimezoneResolution['ok'] ?? false) === true) {
                            $localToday = ChecadorOperationalPeriod::currentLocalDate(
                                (string) ($defaultTimezoneResolution['data']['timezone'] ?? ''),
                                $nowUtc
                            );
                            if ($localToday === null) {
                                $defaultTimezoneResolution = [
                                    'ok' => false,
                                    'error' => ['kind' => 'timezone_unresolved', 'status' => 502],
                                ];
                            } else {
                                $date = $localToday;
                                if ($localToday !== $candidateDate) {
                                    $defaultTimezoneResolution = null;
                                }
                            }
                        }
                    } catch (\Throwable) {
                        $defaultTimezoneResolution = [
                            'ok' => false,
                            'error' => ['kind' => 'api_unavailable', 'status' => 0],
                        ];
                    }
                }
                $attendanceTimezoneResolution = $defaultTimezoneResolution;
            }
        }
        // Route a blank attendance date only after the operational date has
        // been resolved. The QA fixture must follow the same effective date
        // used by the view, including the no-query default.
        $persistentQaFixtureMode = $qaFixtureService->shouldServe($workspace, $requestedWorkplace, $date, $query);

        // The breadcrumb carries the effective date and worker filters back to
        // attendance. Build it only after authorization and date defaults have
        // resolved, so both values are initialized and match the rendered view.
        $checadorBreadcrumbHref = '/departamento-operativo/recursos-humanos/checador';
        if ($scheduleWeekMode) {
            $checadorBreadcrumbHref = self::scheduleWeekHref($query);
        }
        if ($workspace === 'incidences') {
            $attendanceQuery = [];
            if (self::isCivilDate($date)) $attendanceQuery['date'] = $date;
            if ($requestedWorker !== null) $attendanceQuery['worker_id'] = $requestedWorker;
            $checadorBreadcrumbHref = '/departamento-operativo/recursos-humanos/checador/asistencia'
                . ($attendanceQuery === [] ? '' : '?' . http_build_query($attendanceQuery));
        }
        Breadcrumb::add(
            'Checador',
            ($scheduleHistoryMode || $scheduleWeekMode || $workspace !== null)
                ? $checadorBreadcrumbHref
                : ''
        );
        if ($workspace !== null) {
            // Daily attendance already has its page heading in the workspace;
            // keep the institutional breadcrumb chain without repeating it.
            if ($workspace !== 'daily-attendance') {
                Breadcrumb::add($workspaceTitles[$workspace] ?? 'Checador', '');
            }
        } elseif ($scheduleHistoryMode) {
            Breadcrumb::add('Horarios y turnos', '');
        } elseif ($scheduleWeekMode) {
            Breadcrumb::add('Programación semanal', '');
        }

        if ($scheduleHistoryMode) {
            $rawHistoryPage = self::positiveInteger($query[$dedicatedScheduleHistory ? 'page' : 'schedule_history_page'] ?? 1);
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
        $attendanceEvents = [];
        $attendanceCoverage = ['status' => 'not_available', 'complete' => false, 'message' => 'No se pudo confirmar la cobertura de marcaciones.'];
        $attendanceRoster = ['status' => 'not_available', 'coverage' => 'unknown', 'workers' => [], 'message' => 'Padrón no disponible.'];
        $workerSelectOptions = [];
        $workerDisplayNames = [];
        $workerSelectStatus = 'unavailable';
        $workerSelectMessage = '';
        $scheduledComodines = ['status' => 'not_queried', 'workers' => [], 'message' => ''];
        $attendanceRows = [];
        $journeys = [];
        $journeyObservations = [];
        $journeyObservationCoverage = null;
        $persistedJourneysList = null;
        $journeyEmptyStateMessage = '';
        $journeyDateFrom = isset($query['date_from']) && is_string($query['date_from']) && self::isCivilDate(trim($query['date_from']))
            ? trim($query['date_from'])
            : (self::isCivilDate($date) ? $date : date('Y-m-01'));
        $journeyDateTo = isset($query['date_to']) && is_string($query['date_to']) && self::isCivilDate(trim($query['date_to']))
            ? trim($query['date_to'])
            : (self::isCivilDate($date) ? $date : date('Y-m-d'));
        $journeyStatus = isset($query['status']) && is_string($query['status'])
            ? trim($query['status'])
            : null;
        $persistedIncidencesList = null;
        $incidenceDateFrom = isset($query['date_from']) && is_string($query['date_from']) && self::isCivilDate(trim($query['date_from']))
            ? trim($query['date_from'])
            : (self::isCivilDate($date) ? $date : date('Y-m-01'));
        $incidenceDateTo = isset($query['date_to']) && is_string($query['date_to']) && self::isCivilDate(trim($query['date_to']))
            ? trim($query['date_to'])
            : (self::isCivilDate($date) ? $date : date('Y-m-d'));
        $incidenceType = isset($query['type']) && is_string($query['type']) && trim($query['type']) !== ''
            ? trim($query['type'])
            : null;
        $incidenceStatus = isset($query['status']) && is_string($query['status']) && trim($query['status']) !== ''
            ? trim($query['status'])
            : null;
        $attendanceReadSource = null;
        $journeyEvaluationState = in_array($workspace, ['daily-attendance', 'journeys'], true)
            ? 'not_evaluated'
            : 'not_run';
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
        $incidenceCreateKey = is_array($incidenceCreateFeedback)
            ? trim((string) ($incidenceCreateFeedback['retry_idempotency_key'] ?? ''))
            : '';
        if ($incidenceCreateKey === '') $incidenceCreateKey = self::newIdempotencyKey();
        $evaluationPermissions = [
            'evaluate' => false,
            'authorize_falta' => false,
        ];
        $evaluationKeys = [];
        $evaluationFeedback = Session::pull('checador_evaluation_feedback', null);
        $currentUser = Auth::user();
        $temporaryFeedbackState = self::temporaryAccessFeedbackForWorkspace(
            $workspace,
            $query,
            self::positiveInteger($currentUser->id ?? null),
            $requestedWorkplace,
            $canViewTemporaryPin
        );
        $temporaryAccessFeedback = $temporaryFeedbackState['feedback'];
        $temporaryAccessPanel = [
            'visible' => false,
            'can_authorize' => false,
            'can_cancel' => false,
            'can_reject' => false,
            'state' => 'unavailable',
            'requests' => [],
            'message' => '',
            'cancelable_uuid' => null,
        ];
        $state = 'idle';
        $stateMessage = '';
        $workspaceNeedsExistingSnapshot = $workspace === 'adjustments';
        $workspaceSnapshotMissing = $workspaceNeedsExistingSnapshot && $snapshotRef === null && !$persistentQaFixtureMode;
        if ($workspaceSnapshotMissing) {
            $state = 'snapshot_state_unavailable';
            $stateMessage = $workspace === 'journeys'
                ? 'No hay una consulta de Journey disponible para esta referencia. Abrir esta vista no crea snapshots ni ejecuta evaluaciones.'
                : 'Carga primero la asistencia diaria para abrir esta vista con una consulta estable y su referencia autorizada.';
        }

        if ($workspace === 'journeys') {
            if ($requestedWorkplace === null) {
                $state = 'error';
                $stateMessage = 'Selecciona una estación autorizada.';
            } elseif ($requestedWorkerInvalid) {
                $state = 'error';
                $stateMessage = 'Selecciona un colaborador válido.';
            } elseif (($journeyUser = Auth::user()) === null) {
                $state = 'error';
                $stateMessage = 'La sesión ya no es válida. Inicia sesión nuevamente.';
            } else {
                $persistedReader = new \App\Services\ChecadorPersistedJourneyReadService();
                $persistedRead = $persistedReader->getPersistedJourneys(
                    $requestedWorkplace,
                    $journeyDateFrom,
                    $journeyDateTo,
                    $requestedWorker,
                    $journeyStatus,
                    $journeyUser
                );

                if (($persistedRead['ok'] ?? false) === true) {
                    $coverageStatus = (string)($persistedRead['evaluation_coverage']['status'] ?? 'UNDETERMINED');
                    $isPartialProjection = ($persistedRead['projection_status'] ?? '') === 'PARTIAL';
                    $hasIncompleteCoverage = in_array($coverageStatus, ['PARTIALLY_EVALUATED', 'UNDETERMINED'], true);
                    $state = ($isPartialProjection || $hasIncompleteCoverage) ? 'partial' : 'success';
                    $stateMessage = $isPartialProjection
                        ? 'La consulta devolvió jornadas persistidas con proyección incompleta; no se ejecutó una evaluación.'
                        : ($coverageStatus === 'UNDETERMINED'
                            ? 'No se pudo determinar completamente la cobertura de jornadas programadas y evaluadas.'
                            : ($coverageStatus === 'PARTIALLY_EVALUATED'
                                ? 'Hay jornadas programadas con y sin evaluación persistida; la consulta no ejecuta evaluaciones.'
                                : ''));
                    $journeyEmptyStateMessage = ($journeyStatus !== null && $journeyStatus !== '')
                        ? 'No hay jornadas evaluadas persistidas que coincidan con los filtros seleccionados; esta consulta no ejecuta evaluaciones.'
                        : match ($coverageStatus) {
                            'NOT_SCHEDULED' => 'No hay jornadas programadas para este periodo y alcance autorizado.',
                            'NOT_EVALUATED' => 'Hay jornadas programadas sin evaluación persistida. Esta consulta no ejecuta evaluaciones.',
                            'PARTIALLY_EVALUATED' => 'Hay jornadas programadas con y sin evaluación persistida. Esta consulta no ejecuta evaluaciones.',
                            'EVALUATED' => 'No hay resultados persistidos que coincidan con los filtros seleccionados.',
                            default => 'No se pudo determinar si las jornadas están programadas o evaluadas.',
                        };
                    $persistedJourneysList = $persistedRead['journeys'] ?? [];

                    $rosterResult = (new \App\Services\ChecadorAttendanceRosterService())
                        ->workersForAttendanceFilter($requestedWorkplace, $journeyDateTo);
                    $workerSelectOptions = $rosterResult['workers'] ?? [];

                    $existingIds = array_map(static fn(array $w): int => (int) ($w['worker_id'] ?? 0), $workerSelectOptions);
                    foreach ($persistedJourneysList as $pj) {
                        $pjwId = (int) ($pj['worker_id'] ?? 0);
                        if ($pjwId > 0 && !in_array($pjwId, $existingIds, true)) {
                            $workerSelectOptions[] = [
                                'worker_id' => $pjwId,
                                'full_name' => $pj['worker_name'],
                                'employee_number' => $pj['employee_number'] ?? null,
                            ];
                            $existingIds[] = $pjwId;
                        }
                    }
                    usort($workerSelectOptions, static fn(array $a, array $b): int =>
                        strcmp((string) ($a['full_name'] ?? ''), (string) ($b['full_name'] ?? ''))
                    );
                    $selectedWorkerId = $requestedWorker;
                } else {
                    $state = 'error';
                    $stateMessage = 'No se pudo consultar el registro de jornadas laborales evaluadas.';
                }
            }
        } elseif ($workspace === 'incidences') {
            if ($requestedWorkplace === null || !self::isCivilDate($date)) {
                $state = 'error';
                $stateMessage = 'Selecciona una estación autorizada y una fecha válida.';
            } elseif ($requestedWorkerInvalid) {
                $state = 'error';
                $stateMessage = 'Selecciona un colaborador válido del padrón autorizado.';
            } elseif ($incidenceDefaultDateError) {
                $state = 'error';
                $stateMessage = 'No se pudo determinar la fecha local para consultar incidencias.';
                if ($requestedWorkplace !== null) {
                    $localRoster = (new \App\Services\ChecadorAttendanceRosterService())
                        ->workersForAttendanceFilter($requestedWorkplace, $date, null, false);
                    if (in_array((string) ($localRoster['status'] ?? ''), ['ready', 'partial', 'current_roster_only'], true)) {
                        $attendanceRoster = $localRoster;
                        $workerSelectOptions = $localRoster['workers'] ?? [];
                        $workerSelectStatus = (string) $localRoster['status'];
                        $workerSelectMessage = (string) ($localRoster['message'] ?? '');
                        foreach ($workerSelectOptions as $profile) {
                            if (!is_array($profile)) continue;
                            $workerId = self::positiveInteger($profile['worker_id'] ?? null);
                            $name = trim((string) ($profile['full_name'] ?? ''));
                            if ($workerId !== null && $name !== '') $workerDisplayNames[$workerId] = $name;
                        }
                    }
                }
            } elseif ($persistentQaFixtureMode) {
                $qaRead = $qaFixtureService->readIncidences($requestedWorkplace, $date, $requestedWorker);
                if (($qaRead['ok'] ?? false) !== true) {
                    $state = 'error';
                    $stateMessage = (string) ($qaRead['state_message'] ?? 'No fue posible consultar las incidencias.');
                } else {
                    $state = 'success';
                    $stateMessage = '';
                    $timezone = $qaRead['timezone'] ?? null;
                    $attendanceRoster = $qaRead['attendance_roster'] ?? $attendanceRoster;
                    $workerSelectOptions = $qaRead['worker_options'] ?? [];
                    $workerDisplayNames = $qaRead['worker_display_names'] ?? [];
                    $workerSelectStatus = (string) ($qaRead['worker_options_status'] ?? 'ready');
                    $workerSelectMessage = (string) ($qaRead['worker_options_message'] ?? '');
                    $requestedWorker = $qaRead['selected_worker_id'] ?? $requestedWorker;
                    $administrativeIncidences = $qaRead['administrative_incidences'] ?? [];
                    $administrativeErrors = $qaRead['administrative_errors'] ?? [];
                    $attendanceReadSource = 'persistent_qa_fixture';
                }
            } elseif (($incidenceUser = Auth::user()) === null) {
                $state = 'error';
                $stateMessage = 'La sesión ya no es válida. Inicia sesión nuevamente.';
            } else {
                $persistedReader = new \App\Services\ChecadorPersistedIncidenceReadService();
                $persistedRead = $persistedReader->getPersistedIncidences(
                    $requestedWorkplace,
                    $incidenceDateFrom,
                    $incidenceDateTo,
                    $requestedWorker,
                    $incidenceType,
                    $incidenceStatus,
                    $incidenceUser
                );

                if (($persistedRead['ok'] ?? false) === true) {
                    $isPartialProjection = ($persistedRead['projection_status'] ?? '') === 'PARTIAL';
                    $state = $isPartialProjection ? 'partial' : 'success';
                    $stateMessage = $isPartialProjection
                        ? 'Algunas relaciones de las incidencias no pudieron proyectarse completamente.'
                        : '';
                    $persistedIncidencesList = $persistedRead['incidences'] ?? [];

                    $rosterResult = (new \App\Services\ChecadorAttendanceRosterService())
                        ->workersForAttendanceFilter($requestedWorkplace, $incidenceDateTo);
                    $workerSelectOptions = $rosterResult['workers'] ?? [];

                    $existingIds = array_map(static fn(array $w): int => (int) ($w['worker_id'] ?? 0), $workerSelectOptions);
                    foreach ($persistedIncidencesList as $pi) {
                        $piwId = (int) ($pi['worker_id'] ?? 0);
                        if ($piwId > 0 && !in_array($piwId, $existingIds, true)) {
                            $workerSelectOptions[] = [
                                'worker_id' => $piwId,
                                'full_name' => $pi['worker_name'],
                                'employee_number' => $pi['employee_number'] ?? null,
                            ];
                            $existingIds[] = $piwId;
                        }
                    }
                    usort($workerSelectOptions, static fn(array $a, array $b): int =>
                        strcmp((string) ($a['full_name'] ?? ''), (string) ($b['full_name'] ?? ''))
                    );
                    $selectedWorkerId = $requestedWorker;
                    $attendanceReadSource = 'api_v2_persisted_incidences';
                } else {
                    $state = 'error';
                    $stateMessage = 'No fue posible consultar las incidencias persistidas.';
                }
            }
        } elseif ($persistentQaFixtureMode) {
            $qaRead = $qaFixtureService->read($requestedWorkplace ?? 0, $date, $requestedWorker);
            if (($qaRead['ok'] ?? false) !== true) {
                $state = 'error';
                $stateMessage = (string) ($qaRead['state_message'] ?? 'No fue posible consultar la asistencia.');
                $workerSelectOptions = is_array($qaRead['worker_options'] ?? null) ? $qaRead['worker_options'] : [];
                $workerSelectStatus = (string) ($qaRead['worker_options_status'] ?? 'unavailable');
                $workerSelectMessage = (string) ($qaRead['worker_options_message'] ?? '');
            } else {
                $state = 'success';
                $stateMessage = '';
                $timezone = $qaRead['timezone'] ?? null;
                $events = $qaRead['events'] ?? [];
                $attendanceEvents = $qaRead['attendance_events'] ?? [];
                $attendanceCoverage = $qaRead['attendance_coverage'] ?? ['status' => 'synthetic_qa_fixture', 'complete' => false, 'message' => ''];
                $attendanceRoster = $qaRead['attendance_roster'] ?? ['status' => 'ready', 'coverage' => 'qa_fixture_cohort', 'workers' => []];
                $workerSelectOptions = $qaRead['worker_options'] ?? [];
                $workerDisplayNames = $qaRead['worker_display_names'] ?? [];
                $workerSelectStatus = (string) ($qaRead['worker_options_status'] ?? 'ready');
                $workerSelectMessage = (string) ($qaRead['worker_options_message'] ?? '');
                $requestedWorker = $qaRead['selected_worker_id'] ?? null;
                $scheduledComodines = $qaRead['scheduled_comodines'] ?? ['status' => 'not_queried', 'workers' => [], 'message' => ''];
                $attendanceRows = $qaRead['attendance_rows'] ?? [];
                $effectiveSchedules = $qaRead['effective_schedules'] ?? [];
                $effectiveScheduleErrors = $qaRead['effective_schedule_errors'] ?? [];
                $journeys = $qaRead['journeys'] ?? [];
                $journeyErrors = $qaRead['journey_errors'] ?? [];
                $administrativeIncidences = $qaRead['administrative_incidences'] ?? [];
                $administrativeErrors = $qaRead['administrative_errors'] ?? [];
                $journeyEvaluationState = 'fixture_only';
                $attendanceReadSource = 'persistent_qa_fixture';
            }
        } elseif ($workspace !== 'temporary-access' && !$workspaceSnapshotMissing && $requestedWorkplace !== null && $date !== '') {
            $user = Auth::user();
            if (!$user) {
                $state = 'error';
                $stateMessage = 'La sesión ya no es válida. Inicia sesión nuevamente.';
            } else {
                $workerCatalog = ['status' => 'unavailable', 'workers' => [], 'message' => ''];
                if ($workspace === 'daily-attendance' && !$demoMode) {
                    $workerCatalog = (new \App\Services\ChecadorAttendanceRosterService())
                        ->workersForAttendanceFilter($requestedWorkplace, $date, null, false);
                    $workerSelectOptions = $workerCatalog['workers'] ?? [];
                    $workerSelectStatus = (string) ($workerCatalog['status'] ?? 'unavailable');
                }
                try {
                    $reader = new ChecadorDailyReadService();
                    if ($workspace === 'daily-attendance' && !$demoMode) {
                        $snapshotRef = null;
                        $snapshotMeta = null;
                        $level2Meta = null;
                        $attendanceReadSource = 'raw_punch_events';
                        $rawAttendance = self::readDailyRawAttendance(
                            $reader,
                            $user,
                            $requestedWorkplace,
                            $date,
                            $requestedWorker,
                            $requestedWorkerInvalid,
                            $authorizedStationWorkplaceIds,
                            $workerCatalog,
                            $attendanceTimezoneResolution
                        );
                        $state = $rawAttendance['state'];
                        $stateMessage = $rawAttendance['state_message'];
                        $timezone = $rawAttendance['timezone'];
                        $events = $rawAttendance['events'];
                        $attendanceEvents = $rawAttendance['attendance_events'];
                        $attendanceCoverage = $rawAttendance['attendance_coverage'];
                        $attendanceRoster = $rawAttendance['attendance_roster'];
                        $workerSelectOptions = $rawAttendance['worker_options'];
                        $workerSelectStatus = $rawAttendance['worker_options_status'];
                        $workerSelectMessage = $rawAttendance['worker_options_message'];
                        $requestedWorker = $rawAttendance['selected_worker_id'];
                        $scheduledComodines = $rawAttendance['scheduled_comodines'];
                        $attendanceRows = $rawAttendance['attendance_rows'];
                        $journeyEvaluationState = $rawAttendance['journey_evaluation_state'];
                        $effectiveSchedules = $rawAttendance['effective_schedules'];
                        $effectiveScheduleErrors = $rawAttendance['effective_schedule_errors'];
                    } else {
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
                        $state = 'snapshot_state_unavailable';
                        $stateMessage = 'No existe una consulta estable disponible. Esta lectura no crea snapshots automáticamente.';
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
                                        $scheduleResult = $demoMode
                                            ? $reader->getEffectiveSchedule($user, (int) $workerId, $requestedWorkplace, $date)
                                            : $reader->getWorkerEffectiveSchedule($user, (int) $workerId, $date, $requestedWorkplace);
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
                                    if (!$demoMode && $requestedWorkplace !== null
                                        && ($workspace === null || $workspace === 'daily-attendance')
                                        && !$scheduleHistoryMode && !$scheduleWeekMode) {
                                        $programmingRead = RolComodinesService::authorizeCanonicalProgramming(
                                            $requestedWorkplace,
                                            'read'
                                        );
                                        $scheduledComodines = !empty($programmingRead['allowed'])
                                            ? RolComodinesService::getCanonicalScheduledWorkersForDate(
                                                $requestedWorkplace,
                                                $date,
                                                $programmingRead['station_workplace_ids'] ?? $authorizedStationWorkplaceIds
                                            )
                                            : [
                                                'status' => 'not_authorized',
                                                'workers' => [],
                                                'message' => '',
                                            ];
                                    }
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
                                        $scheduleResult = $demoMode
                                            ? $reader->getEffectiveSchedule($user, $rosterWorkerId, $requestedWorkplace, $date)
                                            : $reader->getWorkerEffectiveSchedule($user, $rosterWorkerId, $date, $requestedWorkplace);
                                        if ($scheduleResult['ok']) $effectiveSchedules[$rosterWorkerId] = $scheduleResult['data'];
                                        else $effectiveScheduleErrors[$rosterWorkerId] = $scheduleResult['error']['kind'];
                                    }
                                    if (self::workspaceRetainsJourneyEvaluation($workspace)) {
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
                                    $observedIds = [];
                                    $eventsByWorker = [];
                                    foreach ($attendanceEvents as $attendanceEvent) {
                                        $workerId = self::positiveInteger($attendanceEvent['id_personal_resuelto'] ?? null);
                                        if ($workerId === null) continue;
                                        $observedIds[$workerId] = true;
                                        $eventsByWorker[$workerId][] = $attendanceEvent;
                                    }
                                    // The current roster can authorize a scoped lookup, but an active
                                    // employee without a PunchEvent is not an attendance row. A valid
                                    // empty snapshot must therefore stay empty in the table.
                                    $profiles = [];
                                    foreach ($attendanceRoster['workers'] as $profile) {
                                        $profileWorkerId = (int) ($profile['worker_id'] ?? 0);
                                        if (isset($observedIds[$profileWorkerId])) {
                                            $profiles[$profileWorkerId] = $profile;
                                        }
                                    }
                                    $profiles += (new \App\Services\ChecadorAttendanceRosterService())->observedProfiles(
                                        array_map('intval', array_keys($observedIds)),
                                        $demoMode
                                    );
                                    foreach (array_keys($observedIds) as $workerId) {
                                        $profiles[$workerId] ??= ['worker_id' => (int) $workerId, 'full_name' => 'Nombre no disponible', 'employee_number' => null];
                                    }
                                    foreach ($scheduledComodines['workers'] ?? [] as $scheduledProfile) {
                                        $scheduledWorkerId = (int) ($scheduledProfile['worker_id'] ?? 0);
                                        if ($scheduledWorkerId > 0) {
                                            $profiles[$scheduledWorkerId] ??= $scheduledProfile;
                                        }
                                    }
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
                                            ?? (isset($effectiveSchedules[(int) $workerId]) || in_array(
                                                (int) $workerId,
                                                array_map(static fn(array $scheduled): int => (int) ($scheduled['worker_id'] ?? 0), $scheduledComodines['workers'] ?? []),
                                                true
                                            ) ? null : 'schedule_not_loaded');
                                        $attendanceRows[(int) $workerId]['attendance_relationship'] =
                                            \App\Services\ChecadorAttendanceRosterService::observedWorkerRelationship(
                                                (int) $workerId,
                                                $attendanceRoster,
                                                $scheduledComodines['workers'] ?? []
                                            );
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
                } catch (\Throwable) {
                    $state = 'error';
                    $stateMessage = 'La consulta no está disponible en este momento.';
                }
            }
        } elseif ($workspace !== 'temporary-access' && !$workspaceSnapshotMissing && ($requestedWorkplace !== null || $date !== '')) {
            $state = 'error';
            $stateMessage = 'Selecciona un centro de trabajo y una fecha válida.';
        }

        $paginationLinks = self::paginationLinks($requestedWorkplace, $date, $pagination, $snapshotRef, $page, $requestedWorker);

        foreach (array_keys($lifecyclePermissions) as $action) {
            $lifecyclePermissions[$action] = !$persistentQaFixtureMode
                && $apiPostOperationsEnabled
                && (ChecadorAccessService::authorizeLifecycle(null, $action)['allowed'] ?? false);
        }
        $evaluationPermissions['evaluate'] = !$persistentQaFixtureMode
            && $apiPostOperationsEnabled
            && (ChecadorAccessService::authorizeEvaluation(null)['allowed'] ?? false);
        $evaluationPermissions['authorize_falta'] = !$persistentQaFixtureMode
            && $apiPostOperationsEnabled
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
        $qaIncidenceStoreForUi = new \App\Services\ChecadorPersistentQaIncidenceStore();
        $localQaIncidenceStoreAvailable = $workspace === 'incidences'
            && $persistentQaFixtureMode
            && (int) ($currentUser->id_puesto ?? 0) === 6
            && (int) ($requestedWorkplace ?? 0) === \App\Services\ChecadorPersistentQaFixtureService::WORKPLACE_ID
            && $date === \App\Services\ChecadorPersistentQaFixtureService::WORK_DATE
            && !$realApiPostOperationsEnabled
            && !$p3gBApiPostOperationsEnabled
            && $qaIncidenceStoreForUi->localQaWritesExplicitlyEnabled()
            && $qaIncidenceStoreForUi->usesDefaultIsolatedDestination()
            && $qaIncidenceStoreForUi->canWriteDefaultIsolatedDestination();
        if (!$demoMode && $requestedWorkplace !== null
            && ($realApiPostOperationsEnabled || $p3gBApiPostOperationsEnabled || $localQaIncidenceStoreAvailable)) {
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
        if ($requestedWorkplace !== null && ($workspace === null || $workspace === 'temporary-access')) {
                $temporaryReadAccess = ChecadorAccessService::authorizeTemporaryAccess($requestedWorkplace, 'leer');
                if (!empty($temporaryReadAccess['allowed'])) {
                    $temporaryAccessPanel['visible'] = true;
                    $temporaryAccessPanel['can_authorize'] = !$demoMode
                        && $temporaryAccessPostOperationsEnabled
                        && ($temporaryReadAccess['can_authorize'] ?? false) === true;
                    $temporaryAccessPanel['can_cancel'] = !$demoMode
                        && $temporaryAccessPostOperationsEnabled
                        && ($temporaryReadAccess['can_cancel'] ?? false) === true;
                    $temporaryAccessPanel['can_reject'] = !$demoMode
                        && $temporaryAccessPostOperationsEnabled
                        && ($temporaryReadAccess['can_reject'] ?? false) === true;
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
                    && $temporaryAccessPanel['can_cancel']) {
                    $temporaryAccessPanel['cancelable_uuid'] = strtolower($cancelable['request_uuid']);
                } elseif (is_array($cancelable) && (int) ($cancelable['expires_at'] ?? 0) <= time()) {
                    Session::remove('checador_temporary_access_cancelable');
                }
            }
        }

        $payrollComparisonAvailable = !$demoMode && !$persistentQaFixtureMode
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
        $canReadAdministrativeAdjustments = $workspace === 'adjustments'
            && $requestedWorkplace !== null
            && (ChecadorAccessService::authorize($requestedWorkplace)['allowed'] ?? false) === true;
        $adjustmentRosterStatus = 'unavailable';
        if ($canReadAdministrativeAdjustments && $requestedWorkplace !== null && self::isCivilDate($date)) {
            // M07's worker filter comes from the same canonical, date-scoped
            // institutional roster used by M05/M06, never from adjustment rows.
            $adjustmentRoster = (new \App\Services\ChecadorAttendanceRosterService())
                ->workersForAttendanceFilter($requestedWorkplace, $date, null, false);
            $adjustmentRosterStatus = (string) ($adjustmentRoster['status'] ?? 'unavailable');
            $workerSelectOptions = is_array($adjustmentRoster['workers'] ?? null)
                ? $adjustmentRoster['workers']
                : [];
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
            'date' => $date,
            'worker_id' => $requestedWorker,
            'snapshot_ref' => $snapshotRef,
        ] as $key => $value) {
            if ($value !== null && $value !== '') {
                $workspaceContext[$key] = $value;
            }
        }
        $viewPath = 'departamento-operativo/2-recursos-humanos/checador/' . $view;
        $viewData = [
                'title' => $workspace === null ? 'Checador' : ($workspaceTitles[$workspace] ?? 'Checador'),
                'workspace' => $workspace,
                'workspaceContext' => $workspaceContext,
                'moduleNavigation' => $workspace === 'daily-attendance'
                    ? $this->buildModuleNavigation($requestedWorkplace, $access)
                    : [],
                'isModuleDirectory' => false,
                'dashboardHref' => '/departamento-operativo/recursos-humanos/checador',
                'selectedWorkplaceName' => $selectedWorkplaceName,
                'demoMode' => $demoMode,
                'persistentQaFixtureMode' => $persistentQaFixtureMode,
                'realApiPostOperationsEnabled' => $realApiPostOperationsEnabled,
                'temporaryAccessPostOperationsEnabled' => $temporaryAccessPostOperationsEnabled,
                'p3gBApiPostOperationsEnabled' => $p3gBApiPostOperationsEnabled,
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
                'workerDisplayNames' => $workerDisplayNames,
                'workerSelectStatus' => $workerSelectStatus,
                'workerSelectMessage' => $workerSelectMessage,
                'snapshotRef' => $snapshotRef,
                'demoWorkerFilterNoMatches' => $demoWorkerFilterNoMatches,
                'timezone' => $timezone,
                'events' => $events,
                'attendanceRows' => array_values($attendanceRows),
                'attendanceReadSource' => $attendanceReadSource,
                'journeyEvaluationState' => $journeyEvaluationState,
                'attendanceCoverage' => $attendanceCoverage,
                'attendanceRoster' => $attendanceRoster,
                'scheduledComodines' => $scheduledComodines,
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
                'persistedJourneys' => $workspace === 'journeys' ? $persistedJourneysList : null,
                'journeyEmptyStateMessage' => $workspace === 'journeys' ? $journeyEmptyStateMessage : '',
                'journeyDateFrom' => $journeyDateFrom,
                'journeyDateTo' => $journeyDateTo,
                'journeyStatus' => $journeyStatus,
                'contractualStatuses' => \App\Services\ChecadorPersistedJourneyReadService::getContractualStatuses(),
                'persistedIncidences' => $workspace === 'incidences' ? $persistedIncidencesList : null,
                'incidenceDateFrom' => $incidenceDateFrom,
                'incidenceDateTo' => $incidenceDateTo,
                'incidenceType' => $incidenceType,
                'incidenceStatus' => $incidenceStatus,
                'contractualIncidenceTypes' => \App\Services\ChecadorPersistedIncidenceReadService::getContractualTypes(),
                'contractualIncidenceStatuses' => \App\Services\ChecadorPersistedIncidenceReadService::getContractualStatuses(),
                'journeyObservations' => $workspace === 'journeys' ? $journeyObservations : [],
                'journeyObservationCoverage' => $workspace === 'journeys' ? $journeyObservationCoverage : null,
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
                'incidenceCreateKey' => $incidenceCreateKey,
                'incidenceCreateFeedback' => $incidenceCreateFeedback,
                'localQaIncidenceStoreAvailable' => $localQaIncidenceStoreAvailable,
                'evaluationPermissions' => $evaluationPermissions,
                'canReadAdministrativeAdjustments' => $canReadAdministrativeAdjustments,
                'adjustmentRosterStatus' => $adjustmentRosterStatus,
                'evaluationKeys' => $evaluationKeys,
                'evaluationFeedback' => $evaluationFeedback,
                'temporaryAccessPanel' => $workspaceTemporaryAccessPanel,
                'temporaryAccessFeedback' => $temporaryAccessFeedback,
                'canViewTemporaryPin' => $workspace === null
                    ? !empty($temporaryAccessPanel['can_authorize'])
                    : $canViewTemporaryPin,
                'state' => $state,
                'stateMessage' => $stateMessage,
                'payrollComparisonAvailable' => $payrollComparisonAvailable,
                'links' => in_array($workspace, ['daily-attendance', 'journeys', 'incidences', 'adjustments'], true)
                    ? [
                        '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                        '/assets/libs/select2/dist/css/select2.min.css',
                        '/assets/css/select2-modal.css',
                        '/assets/css/table-actions.css',
                        ...($workspace === 'journeys' ? ['/assets/css/checador-journeys.css?v=20261109-1'] : []),
                        ...($workspace === 'incidences' ? ['/assets/css/checador-incidences.css?v=20261109-3'] : []),
                        ...($workspace === 'adjustments' ? ['/assets/css/checador-adjustments.css?v=20261109-3'] : []),
                    ]
                    : [],
                'scripts' => [
                    ...($workspace === 'daily-attendance' ? [
                        '/assets/js/vendor.min.js',
                        '/assets/libs/select2/dist/js/select2.full.min.js',
                        '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                        '/assets/js/core/module-station-selector.js',
                        '/assets/js/departamento-operativo/2-recursos-humanos/checador.attendance.datatable.init.js?v=20261028-2',
                    ] : []),
                    ...($workspace === 'journeys' ? [
                        '/assets/js/vendor.min.js',
                        '/assets/libs/select2/dist/js/select2.full.min.js',
                        '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                        '/assets/libs/datatables.net-bs5/js/dataTables.bootstrap5.min.js',
                        '/assets/js/core/module-station-selector.js',
                        '/assets/js/departamento-operativo/2-recursos-humanos/checador.journeys.init.js?v=20261109-2',
                    ] : []),
                    ...($workspace === 'incidences' ? [
                        '/assets/js/vendor.min.js',
                        '/assets/libs/select2/dist/js/select2.full.min.js',
                        '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                        '/assets/libs/datatables.net-bs5/js/dataTables.bootstrap5.min.js',
                        '/assets/js/core/module-station-selector.js',
                        '/assets/js/departamento-operativo/2-recursos-humanos/checador.incidences.init.js?v=20261109-2',
                    ] : []),
                    ...($workspace === 'adjustments' ? [
                        '/assets/js/vendor.min.js',
                        '/assets/libs/select2/dist/js/select2.full.min.js',
                        '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                        '/assets/js/departamento-operativo/2-recursos-humanos/checador.adjustments.init.js?v=20261109-4',
                    ] : []),
                    ...($workspace === 'daily-attendance' ? [] : ['/assets/js/core/module-station-selector.js']),
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.module-station.init.js?v=20261027-1',
                    ...(($workspace === null || $workspace === 'daily-attendance') ? ['/assets/js/departamento-operativo/2-recursos-humanos/checador.filters.init.js?v=20261028-8'] : []),
                ],
                'help' => false,
                'moduleStationKey' => ChecadorAccessService::MODULE_KEY,
            ];

        // POST search requests reuse the authenticated page route and the same
        // renderer; the JSON wrapper in dailyAttendance returns its partial view.
        $isAttendanceAjaxRequest = $workspace === 'daily-attendance'
            && Request::method() === 'POST'
            && strtoupper(trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''))) === 'XMLHTTPREQUEST';
        if ($isAttendanceAjaxRequest) {
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: private, no-store');
            View::renderPartial($viewPath, $viewData);
            return;
        }

        View::render($viewPath, $viewData, 'departamento-operativo');
        // Keep the flash until the actual view and institutional layout have
        // rendered. Remove only the exact record read by this M07 operation;
        // a concurrent replacement must not be discarded. PHP's active file
        // session lock remains held through this render and consume step.
        self::consumeTemporaryAccessFeedbackAfterRender($temporaryFeedbackState['consume_hash']);
    }

    /** Centralized, read-only configuration view for Checador. */
    public function configuration(): void
    {
        $this->normalizeLegacyWorkplaceUrl();
        $access = ChecadorAccessService::authorize();
        if (empty($access['allowed'])) {
            self::renderAccessDenied((string) ($access['reason'] ?? 'authorization_lookup_failed'), null);
            return;
        }

        $workplaces = $access['workplaces'] ?? [];
        $workplaceId = $this->sessionWorkplaceId($access);

        $user = Auth::user();
        $demoMode = ChecadorDailyReadService::demoModeEnabled();

        $configuration = null;
        if ($workplaceId !== null && $user !== null) {
            try {
                $configuration = (new ChecadorConfigurationReadService())->read(
                    $user,
                    $workplaceId,
                    $demoMode
                );
            } catch (\Throwable) {
                $configuration = ['status' => 'unavailable'];
            }
        }

        $temporaryAccessAvailable = $workplaceId !== null
            && (ChecadorAccessService::authorizeTemporaryAccess($workplaceId, 'leer')['allowed'] ?? false) === true;
        $canReadScheduling = $workplaceId !== null
            && (ChecadorAccessService::authorizeScheduling($workplaceId, 'leer')['allowed'] ?? false) === true;

        $policyReadAccess = $workplaceId !== null
            ? ChecadorAccessService::authorizeAttendancePolicy($workplaceId, 'leer')
            : ['allowed' => false];
        $policyEditAccess = $workplaceId !== null
            ? ChecadorAccessService::authorizeAttendancePolicy($workplaceId, 'editar')
            : ['allowed' => false];
        $readerReadAccess = $workplaceId !== null
            ? ChecadorAccessService::authorizeReaderProfiles($workplaceId, 'leer')
            : ['allowed' => false];
        $readerEditAccess = $workplaceId !== null
            ? ChecadorAccessService::authorizeReaderProfiles($workplaceId, 'editar')
            : ['allowed' => false];
        $positionReadAccess = ChecadorAccessService::authorizePositionCatalog($workplaceId, 'leer');
        $positionEditAccess = ChecadorAccessService::authorizePositionCatalog($workplaceId, 'editar');
        $canReadAdministrativeDevices = $user !== null
            && in_array((int) ($access['position_id'] ?? 0), [6, 13], true)
            && ChecadorV2PermissionService::allows(
                (int) $user->id,
                (int) $access['position_id'],
                ChecadorV2PermissionService::DEVICE_READ
            );
        if ($configuration === null && ($positionReadAccess['allowed'] ?? false) === true) {
            $configuration = [
                'status' => 'ready',
                'permissions' => ['positions_read' => true],
                'positions' => \App\Services\ChecadorConfigurationReadService::readM11PositionCatalog($workplaceId),
            ];
        }
        $attendancePolicy = is_array($configuration['attendance_policy']['data'] ?? null)
            ? $configuration['attendance_policy']['data'] : [];
        $attendancePolicyCurrent = is_array($attendancePolicy['policy'] ?? null) ? $attendancePolicy['policy'] : [];
        $attendancePolicyLatest = is_array($attendancePolicy['latest_scheduled_policy'] ?? null)
            ? $attendancePolicy['latest_scheduled_policy'] : [];
        $attendancePolicyActorNames = [];
        $policyHistoryRows = is_array($attendancePolicy['policy_history'] ?? null)
            ? $attendancePolicy['policy_history'] : [];
        $policyActorIds = array_values(array_unique(array_filter(array_map(
            static fn(mixed $policy): int => is_array($policy) ? (int) ($policy['actor_portal_user_id'] ?? 0) : 0,
            [...$policyHistoryRows, $attendancePolicyCurrent, $attendancePolicyLatest]
        ), static fn(int $id): bool => $id > 0)));
        if ($user !== null && (int) ($user->id ?? 0) > 0 && trim((string) ($user->nombre ?? '')) !== '') {
            $attendancePolicyActorNames[(int) $user->id] = trim((string) $user->nombre);
        }
        if ($policyActorIds !== []) {
            try {
                foreach (\App\Models\Usuario::query()->whereIn('id', $policyActorIds)->get(['id', 'nombre']) as $actor) {
                    $actorName = trim((string) ($actor->nombre ?? ''));
                    if ((int) ($actor->id ?? 0) > 0 && $actorName !== '') {
                        $attendancePolicyActorNames[(int) $actor->id] = $actorName;
                    }
                }
            } catch (\Throwable) {
                // The policy remains readable if an actor display name cannot be resolved.
            }
        }
        $attendancePolicyEffectiveFrom = '';
        $attendancePolicyCurrentLocalDate = (string) ($attendancePolicy['current_local_date'] ?? '');
        if (self::isCivilDate($attendancePolicyCurrentLocalDate)) {
            $attendancePolicyEffectiveFrom = (new \DateTimeImmutable($attendancePolicyCurrentLocalDate, new \DateTimeZone('UTC')))
                ->modify('+1 day')->format('Y-m-d');
        }
        $latestEffectiveFrom = (string) ($attendancePolicyLatest['effective_from_local_date'] ?? '');
        if ($attendancePolicyEffectiveFrom !== '' && self::isCivilDate($latestEffectiveFrom)
            && $latestEffectiveFrom >= $attendancePolicyEffectiveFrom) {
            $attendancePolicyEffectiveFrom = (new \DateTimeImmutable($latestEffectiveFrom, new \DateTimeZone('UTC')))
                ->modify('+1 day')->format('Y-m-d');
        }
        $attendancePolicyDefaults = [
            'retardo_tolerance_minutes' => '',
            'incidence_deadline_days' => '',
        ];
        if ($attendancePolicyLatest !== []) {
            $attendancePolicyDefaults['retardo_tolerance_minutes'] = (string) ($attendancePolicyLatest['retardo_tolerance_minutes'] ?? '');
            $attendancePolicyDefaults['incidence_deadline_days'] = (string) ($attendancePolicyLatest['incidence_deadline_days'] ?? '');
        } elseif (($configuration['attendance_policy']['status'] ?? '') === 'VERSIONED' && $attendancePolicyCurrent !== []) {
            $attendancePolicyDefaults['retardo_tolerance_minutes'] = (string) ($attendancePolicyCurrent['retardo_tolerance_minutes'] ?? '');
            $attendancePolicyDefaults['incidence_deadline_days'] = (string) ($attendancePolicyCurrent['incidence_deadline_days'] ?? '');
        } elseif (($configuration['legacy_rules']['status'] ?? '') === 'legacy_configured') {
            $legacyTolerance = self::strictNonNegativeInteger($configuration['legacy_rules']['retardo'] ?? null);
            $legacyDeadline = self::strictNonNegativeInteger($configuration['legacy_rules']['incidencia'] ?? null);
            if ($legacyTolerance !== null && $legacyTolerance <= 120) {
                $attendancePolicyDefaults['retardo_tolerance_minutes'] = (string) $legacyTolerance;
            }
            if ($legacyDeadline !== null && $legacyDeadline <= 30) {
                $attendancePolicyDefaults['incidence_deadline_days'] = (string) $legacyDeadline;
            }
        }

        $attendancePolicyFeedback = null;
        $storedPolicyFeedback = Session::get('checador_attendance_policy_feedback');
        if (is_array($storedPolicyFeedback)
            && (int) ($storedPolicyFeedback['user_id'] ?? 0) === (int) ($user->id ?? 0)
            && (int) ($storedPolicyFeedback['workplace_id'] ?? 0) === (int) ($workplaceId ?? 0)) {
            $attendancePolicyFeedback = $storedPolicyFeedback;
            Session::remove('checador_attendance_policy_feedback');
        } elseif (is_array($storedPolicyFeedback)) {
            Session::remove('checador_attendance_policy_feedback');
        }

        $m11ConfigurationFeedback = null;
        $storedM11Feedback = Session::get('checador_m11_configuration_feedback');
        $expectedM11WorkplaceId = (int) ($storedM11Feedback['workplace_id'] ?? 0);
        if (is_array($storedM11Feedback)
            && (int) ($storedM11Feedback['user_id'] ?? 0) === (int) ($user->id ?? 0)
            && ($expectedM11WorkplaceId === 0 || $expectedM11WorkplaceId === (int) ($workplaceId ?? 0))) {
            $m11ConfigurationFeedback = $storedM11Feedback;
            Session::remove('checador_m11_configuration_feedback');
        } elseif (is_array($storedM11Feedback)) {
            Session::remove('checador_m11_configuration_feedback');
        }

        $m11AdministrationReader = new \App\Services\ChecadorM11AdministrationReadService();
        $effectivePermissionRead = $workplaceId !== null && ($policyReadAccess['allowed'] ?? false) === true
            ? $m11AdministrationReader->readCurrentUserPermissions($workplaceId)
            : ['status' => 'permission_denied'];
        $m11AuditRead = $workplaceId !== null && ($policyReadAccess['allowed'] ?? false) === true
            ? $m11AdministrationReader->readChangeAudit($workplaceId)
            : ['status' => 'permission_denied'];
        $selectedWorkplaceName = '';
        foreach ($workplaces as $workplace) {
            if ((int) ($workplace['id'] ?? 0) === (int) ($workplaceId ?? 0)) {
                $selectedWorkplaceName = (string) ($workplace['nombre'] ?? '');
                break;
            }
        }

        $physicalStationMapping = is_array($configuration['mapping'] ?? null)
            ? $configuration['mapping'] : ['status' => 'not_available'];
        if ($workplaceId !== null && ($readerReadAccess['allowed'] ?? false) === true
            && ($physicalStationMapping['status'] ?? '') !== 'unique_numlista_mapping') {
            $physicalStationMapping = ChecadorConfigurationReadService::resolveUniquePhysicalStation($workplaceId);
        }
        if (($physicalStationMapping['status'] ?? '') === 'unique_numlista_mapping') {
            try {
                $physicalStation = Estacion::query()
                    ->where('id', (int) ($physicalStationMapping['physical_station_id'] ?? 0))
                    ->where('estatus', 0)
                    ->first(['id', 'nombre', 'numlista']);
                if ($physicalStation) {
                    $physicalStationMapping['name'] = (string) $physicalStation->nombre;
                    $physicalStationMapping['numlista'] = (int) $physicalStation->numlista;
                } else {
                    $physicalStationMapping['status'] = 'mapping_unavailable';
                }
            } catch (\Throwable) {
                $physicalStationMapping['status'] = 'mapping_unavailable';
            }
        }
        if ($workplaceId === ChecadorAccessService::AUTOLAVADO_LOCALITY_ID && ($readerReadAccess['allowed'] ?? false) === true) {
            $physicalStationMapping = [
                'status' => 'department_shared_station',
                'physical_station_id' => 2,
                'name' => 'Palo Solo (Lector compartido)',
                'numlista' => 1,
                'department_numlista' => 10,
            ];
        }

        $m11HistoryEvents = [];
        $auditActorIds = [];
        foreach ($m11AuditRead['items'] ?? [] as $event) {
            $actorId = (int) ($event['actor_id'] ?? 0);
            if ($actorId > 0) $auditActorIds[] = $actorId;
            $actionLabel = match ((string) ($event['action'] ?? '')) {
                'UPDATE_USERNAME' => 'Actualización del usuario del lector',
                'ROTATE_SECRET' => 'Rotación de contraseña del lector',
                'CREATE' => 'Alta de puesto',
                'UPDATE' => 'Edición de puesto',
                default => 'Cambio de configuración',
            };
            $entityLabel = match ((string) ($event['entity_type'] ?? '')) {
                'READER_PROFILE' => 'Perfil del lector',
                'POSITION' => 'Puesto',
                default => 'Configuración',
            };
            $m11HistoryEvents[] = [
                'timestamp' => (string) ($event['created_at_utc'] ?? ''),
                'actor_id' => $actorId,
                'workplace_id' => $event['workplace_id'] ?? null,
                'action' => $actionLabel,
                'entity' => $entityLabel,
                'reference' => $event['entity_id'] === null ? '' : (string) (int) $event['entity_id'],
                'detail' => '',
            ];
        }
        foreach ($policyHistoryRows as $policyVersion) {
            if (!is_array($policyVersion)) continue;
            $actorId = (int) ($policyVersion['actor_portal_user_id'] ?? 0);
            if ($actorId > 0) $auditActorIds[] = $actorId;
            $version = (int) ($policyVersion['policy_version'] ?? 0);
            $m11HistoryEvents[] = [
                'timestamp' => (string) ($policyVersion['created_at_utc'] ?? $policyVersion['effective_from_local_date'] ?? ''),
                'actor_id' => $actorId,
                'workplace_id' => $workplaceId,
                'action' => 'Nueva versión de retardos e incidencias',
                'entity' => 'Política' . ($version > 0 ? ' · versión ' . $version : ''),
                'reference' => '',
                'detail' => sprintf('%s min · %s días · vigencia %s',
                    (string) ($policyVersion['retardo_tolerance_minutes'] ?? '—'),
                    (string) ($policyVersion['incidence_deadline_days'] ?? '—'),
                    (string) ($policyVersion['effective_from_local_date'] ?? 'no disponible')),
            ];
        }
        $auditActorNames = [];
        $auditActorIds = array_values(array_unique(array_filter($auditActorIds, static fn(int $id): bool => $id > 0)));
        if ($user !== null && trim((string) ($user->nombre ?? '')) !== '') {
            $auditActorNames[(int) $user->id] = trim((string) $user->nombre);
        }
        if ($auditActorIds !== []) {
            try {
                foreach (\App\Models\Usuario::query()->whereIn('id', $auditActorIds)->get(['id', 'nombre']) as $actor) {
                    $actorName = trim((string) ($actor->nombre ?? ''));
                    if ($actorName !== '') $auditActorNames[(int) $actor->id] = $actorName;
                }
            } catch (\Throwable) {
                // Keep verified event rows even if actor names are unavailable.
            }
        }
        foreach ($m11HistoryEvents as &$historyEvent) {
            $historyEvent['actor_name'] = $auditActorNames[(int) ($historyEvent['actor_id'] ?? 0)] ?? '';
            $historyEvent['workplace_name'] = (int) ($historyEvent['workplace_id'] ?? 0) === (int) ($workplaceId ?? 0)
                ? $selectedWorkplaceName : (($historyEvent['workplace_id'] ?? null) === null ? 'Catálogo institucional' : 'Localidad autorizada');
        }
        unset($historyEvent);
        usort($m11HistoryEvents, static fn(array $left, array $right): int => strcmp((string) ($right['timestamp'] ?? ''), (string) ($left['timestamp'] ?? '')));
        $m11HistoryEvents = array_slice($m11HistoryEvents, 0, 100);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Checador', '/departamento-operativo/recursos-humanos/checador');
        Breadcrumb::add('Configuración', '');

        $configurationScripts = [
            '/assets/js/vendor.min.js',
            '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
            '/assets/js/core/module-station-selector.js',
            '/assets/js/departamento-operativo/2-recursos-humanos/checador.attendance-policy.init.js?v=20261103-2',
            '/assets/js/departamento-operativo/2-recursos-humanos/checador.m11-positions.init.js?v=20261103-4',
            '/assets/js/departamento-operativo/2-recursos-humanos/checador.m11-reader-profile.init.js?v=20261103-2',
        ];
        if ($canReadAdministrativeDevices) {
            $configurationScripts[] = '/assets/js/departamento-operativo/2-recursos-humanos/checador.m11-devices.init.js?v=20261109-2';
        }

        View::render(
            'departamento-operativo/2-recursos-humanos/checador/configuration',
            [
                'title' => 'Configuración de Checador',
                'workplaces' => $workplaces,
                'selectedWorkplaceId' => $workplaceId,
                'configuration' => $configuration,
                'demoMode' => $demoMode,
                'temporaryAccessAvailable' => $temporaryAccessAvailable,
                'canReadScheduling' => $canReadScheduling,
                'canReadAttendancePolicy' => ($policyReadAccess['allowed'] ?? false) === true,
                'canEditAttendancePolicy' => ($policyEditAccess['allowed'] ?? false) === true,
                'attendancePolicyPostEnabled' => ChecadorDailyReadService::attendancePolicyPostOperationsEnabled(),
                'canReadReaderProfiles' => ($readerReadAccess['allowed'] ?? false) === true,
                'canEditReaderProfiles' => ($readerEditAccess['allowed'] ?? false) === true,
                'canReadPositionCatalog' => ($positionReadAccess['allowed'] ?? false) === true,
                'canEditPositionCatalog' => ($positionEditAccess['allowed'] ?? false) === true,
                'canReadAdministrativeDevices' => $canReadAdministrativeDevices,
                'administrativeDevicesDataUrl' => '/departamento-operativo/recursos-humanos/checador/configuracion/dispositivos/data' . ($workplaceId !== null ? '?workplace_id=' . (int) $workplaceId : ''),
                'm11ConfigurationPostEnabled' => ChecadorDailyReadService::m11ConfigurationPostOperationsEnabled(),
                'm11ConfigurationFeedback' => $m11ConfigurationFeedback,
                'effectivePermissionRead' => $effectivePermissionRead,
                'm11AuditRead' => $m11AuditRead,
                'm11HistoryEvents' => $m11HistoryEvents,
                'physicalStationMapping' => $physicalStationMapping,
                'selectedWorkplaceName' => $selectedWorkplaceName,
                'auditActorNames' => $auditActorNames,
                'attendancePolicyEffectiveFrom' => $attendancePolicyEffectiveFrom,
                'attendancePolicyDefaults' => $attendancePolicyDefaults,
                'attendancePolicyActorNames' => $attendancePolicyActorNames,
                'attendancePolicyFeedback' => $attendancePolicyFeedback,
                'attendancePolicyIdempotencyKey' => self::newIdempotencyKey(),
                'links' => [
                    '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                ],
                'scripts' => $configurationScripts,
                'moduleStationKey' => ChecadorAccessService::MODULE_KEY,
                'help' => false,
            ],
            'departamento-operativo'
        );
    }

    /** Server-side M11 device inventory proxy; the browser never receives the delegated JWT. */
    public function administrativeDevicesData(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');

        $access = ChecadorAccessService::authorize();
        $user = Auth::user();
        $userId = (int) ($user?->id ?? 0);
        $positionId = (int) ($access['position_id'] ?? 0);
        $workplaces = $access['workplaces'] ?? [];
        $authorizedIds = array_values(array_unique(array_filter(
            array_map(static fn(array $workplace): int => (int) ($workplace['id'] ?? 0), is_array($workplaces) ? $workplaces : []),
            static fn(int $id): bool => $id > 0
        )));

        $stationIds = array_column($access['station_workplaces'] ?? [], 'id');

        if (empty($access['allowed']) || $user === null || !in_array($positionId, [6, 13], true)
            || $authorizedIds === [] || ($positionId === 6 && count($stationIds) !== 1)
            || !ChecadorV2PermissionService::allows($userId, $positionId, ChecadorV2PermissionService::DEVICE_READ)) {
            http_response_code(403);
            echo json_encode(['status' => 'ERROR', 'message' => 'No tienes acceso al inventario de dispositivos autorizado.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $requestedWorkplaceId = filter_var($_GET['workplace_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        if ($requestedWorkplaceId !== null && !in_array($requestedWorkplaceId, $authorizedIds, true)) {
            http_response_code(403);
            echo json_encode(['status' => 'ERROR', 'message' => 'La localidad solicitada no está autorizada.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $result = (new ChecadorDailyReadService())->readAdministrativeDevices($user);
        } catch (\Throwable) {
            $result = ['ok' => false, 'status' => 502];
        }
        if (($result['ok'] ?? false) !== true) {
            $status = (int) ($result['status'] ?? 502);
            if (!in_array($status, [401, 403, 409, 413, 502, 503], true)) $status = 502;
            http_response_code($status);
            echo json_encode([
                'status' => 'ERROR',
                'message' => match ($status) {
                    401 => 'Tu sesión ya no está autorizada. Inicia sesión nuevamente.',
                    403 => 'No tienes permiso para consultar estos dispositivos.',
                    409 => 'No se pudo validar una relación institucional del inventario.',
                    413 => 'El inventario autorizado excede el límite de consulta.',
                    default => 'No fue posible consultar el inventario de dispositivos.',
                },
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $data = $result['data'];
        $items = [];
        // The API derives this same scope from the delegated JWT. Validate its
        // rows again before returning them through the Portal3 same-origin route.
        foreach ($data['items'] as $item) {
            $itemWorkplaceId = (int) ($item['workplace']['id'] ?? 0);
            if ($itemWorkplaceId > 0 && !in_array($itemWorkplaceId, $authorizedIds, true)) {
                http_response_code(502);
                echo json_encode(['status' => 'ERROR', 'message' => 'La respuesta del inventario no coincide con el alcance autorizado.'], JSON_UNESCAPED_UNICODE);
                return;
            }
            if ($requestedWorkplaceId !== null) {
                if ($requestedWorkplaceId === ChecadorAccessService::AUTOLAVADO_LOCALITY_ID) {
                    if ($itemWorkplaceId === ChecadorAccessService::PALO_SOLO_LOCALITY_ID) {
                        $item['workplace']['name'] = 'Palo Solo (Lector compartido con Autolavado)';
                        $items[] = $item;
                    }
                } elseif ($itemWorkplaceId === $requestedWorkplaceId) {
                    $items[] = $item;
                }
            } else {
                $items[] = $item;
            }
        }
        echo json_encode([
            'status' => 'OK',
            'completeness' => $data['completeness'],
            'read_at_utc' => $data['read_at_utc'],
            'data' => $items,
            'partial_reasons' => $data['partial_reasons'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /** Append one future M11 policy version through the dedicated API writer. */
    public function saveAttendancePolicy(): void
    {
        $input = Request::all();
        $workplaceId = $this->requestWorkplaceId($input);
        $effectiveFrom = is_string($input['effective_from_local_date'] ?? null)
            ? trim($input['effective_from_local_date']) : '';
        $tolerance = self::strictNonNegativeInteger($input['retardo_tolerance_minutes'] ?? null);
        $deadlineDays = self::strictNonNegativeInteger($input['incidence_deadline_days'] ?? null);
        $idempotencyKey = is_string($input['idempotency_key'] ?? null) ? trim($input['idempotency_key']) : '';
        $confirm = (string) ($input['confirm_policy'] ?? '') === '1';
        $user = Auth::user();
        $wantsJson = str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
        $jsonResponse = static function (int $status, array $payload): never {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, private');
            header('Vary: Accept');
            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        };

        $feedback = static function (string $type, string $message, array $extra = []) use (
            $user,
            $workplaceId,
            $effectiveFrom,
            $tolerance,
            $deadlineDays
        ): void {
            Session::set('checador_attendance_policy_feedback', array_merge([
                'type' => $type,
                'message' => $message,
                'user_id' => (int) ($user->id ?? 0),
                'workplace_id' => (int) ($workplaceId ?? 0),
                'form_values' => [
                    'effective_from_local_date' => $effectiveFrom,
                    'retardo_tolerance_minutes' => $tolerance === null ? '' : (string) $tolerance,
                    'incidence_deadline_days' => $deadlineDays === null ? '' : (string) $deadlineDays,
                ],
            ], $extra));
        };
        $redirect = '/departamento-operativo/recursos-humanos/checador/configuracion';

        $validationError = match (true) {
            $workplaceId === null => 'workplace_required',
            $user === null => 'unauthenticated',
            !self::isCivilDate($effectiveFrom) => 'effective_date_invalid',
            $tolerance === null || $tolerance < 0 || $tolerance > 120 => 'tolerance_out_of_range',
            $deadlineDays === null || $deadlineDays < 0 || $deadlineDays > 30 => 'deadline_out_of_range',
            !preg_match('/\A[A-Za-z0-9._:-]{1,64}\z/', $idempotencyKey) => 'idempotency_key_invalid',
            !$confirm => 'confirmation_required',
            !ChecadorDailyReadService::attendancePolicyPostOperationsEnabled() => 'attendance_policy_post_disabled',
            default => null,
        };
        if ($validationError !== null) {
            if ($wantsJson) {
                $status = $validationError === 'unauthenticated' ? 401
                    : (in_array($validationError, ['workplace_required', 'effective_date_invalid', 'tolerance_out_of_range', 'deadline_out_of_range', 'idempotency_key_invalid', 'confirmation_required'], true) ? 422 : 403);
                $jsonResponse($status, ['ok' => false, 'code' => $validationError, 'message' => match ($validationError) {
                    'workplace_required' => 'Selecciona una localidad autorizada.',
                    'unauthenticated' => 'La sesión ya no es válida. Inicia sesión nuevamente.',
                    'effective_date_invalid' => 'Indica una fecha local válida para la nueva vigencia.',
                    'tolerance_out_of_range' => 'La tolerancia debe ser un entero de 0 a 120 minutos.',
                    'deadline_out_of_range' => 'El plazo debe ser un entero de 0 a 30 días naturales.',
                    'idempotency_key_invalid' => 'Actualiza el formulario antes de volver a intentarlo.',
                    'confirmation_required' => 'Confirma la nueva configuración antes de guardar.',
                    default => 'El registro está temporalmente deshabilitado.',
                }]);
            }
            $feedback('error', match ($validationError) {
                'workplace_required' => 'Selecciona una localidad autorizada.',
                'unauthenticated' => 'La sesión ya no es válida. Inicia sesión nuevamente.',
                'effective_date_invalid' => 'Indica una fecha local válida para la nueva vigencia.',
                'tolerance_out_of_range' => 'La tolerancia debe ser un entero de 0 a 120 minutos.',
                'deadline_out_of_range' => 'El plazo debe ser un entero de 0 a 30 días naturales.',
                'idempotency_key_invalid' => 'Actualiza el formulario antes de volver a intentarlo.',
                'confirmation_required' => 'Confirma la nueva versión antes de guardar.',
                'attendance_policy_post_disabled' => 'El registro de políticas permanece deshabilitado por el gate específico de desarrollo.',
                default => 'Revisa los datos de la política.',
            });
            self::redirect($redirect);
        }

        $authorization = ChecadorAccessService::authorizeAttendancePolicy($workplaceId, 'editar');
        if (!($authorization['allowed'] ?? false)) {
            if ($wantsJson) $jsonResponse(403, ['ok' => false, 'code' => 'permission_denied', 'message' => 'No tienes permiso para modificar la configuración de esta localidad.']);
            $feedback('error', 'El perfil no tiene permiso específico para modificar la política de esta localidad.');
            self::redirect($redirect);
        }
        $activeActor = \App\Models\Usuario::query()
            ->where('id', (int) $user->id)
            ->where('estatus', 0)
            ->first();
        if (!$activeActor || !in_array((int) ($activeActor->id_puesto ?? 0), [6, 13], true)) {
            if ($wantsJson) $jsonResponse(403, ['ok' => false, 'code' => 'permission_denied', 'message' => 'Tu perfil no está autorizado para esta operación.']);
            $feedback('error', 'Solo Encargado o Dirección de Operaciones activos con permiso específico pueden modificar esta política.');
            self::redirect($redirect);
        }

        try {
            $reader = new ChecadorDailyReadService();
            $created = $reader->createAttendancePolicy($user, [
                'workplace_id' => $workplaceId,
                'effective_from_local_date' => $effectiveFrom,
                'retardo_tolerance_minutes' => $tolerance,
                'incidence_deadline_days' => $deadlineDays,
            ], $idempotencyKey);
        } catch (\Throwable) {
            $reader = null;
            $created = ['ok' => false, 'error' => ['kind' => 'api_unavailable', 'status' => 503]];
        }
        if (($created['ok'] ?? false) !== true) {
            $kind = (string) ($created['error']['kind'] ?? 'api_unavailable');
            if ($wantsJson) {
                $apiStatus = (int) ($created['error']['status'] ?? 0);
                $status = in_array($apiStatus, [401, 403, 409, 422], true) ? $apiStatus
                    : ($kind === 'bad_request' ? 422 : ($kind === 'administrative_conflict' || $kind === 'policy_version_date_conflict' ? 409 : ($kind === 'api_authorization' || $kind === 'level2_authorization' ? 403 : ($kind === 'unauthenticated' ? 401 : 503))));
                $message = match ($kind) {
                    'api_authorization', 'level2_authorization' => 'La API no autoriza esta operación o localidad.',
                    'bad_request' => 'Revisa la fecha y los valores permitidos.',
                    'api_post_disabled' => 'El registro de configuraciones está deshabilitado en este entorno.',
                    'development_only' => 'La API rechazó la escritura fuera del entorno de desarrollo autorizado.',
                    'policy_not_prospective' => 'La fecha de vigencia debe ser futura.',
                    'policy_version_date_conflict', 'administrative_conflict' => 'La configuración entra en conflicto con otra versión. Actualiza la pantalla y revisa el historial.',
                    default => 'No se pudo guardar la configuración. Puedes reintentar con la misma solicitud.',
                };
                $jsonResponse($status, ['ok' => false, 'code' => $kind, 'message' => $message]);
            }
            $message = match ($kind) {
                'api_authorization', 'level2_authorization' => 'La API no autoriza este permiso o localidad.',
                'bad_request' => 'Los valores no cumplen el rango entero autorizado de la política.',
                'api_post_disabled' => 'El registro de políticas permanece deshabilitado por su gate local específico.',
                'development_only' => 'La API rechazó la escritura porque no se encuentra en un entorno de desarrollo autorizado.',
                'policy_not_prospective' => 'La nueva vigencia debe comenzar después del día local actual.',
                'policy_version_date_conflict' => 'La nueva vigencia debe ser posterior a la última versión programada.',
                'administrative_conflict' => 'La clave de reintento se utilizó con otros datos o la vigencia entra en conflicto.',
                default => 'La política no se guardó o no se pudo confirmar. No se muestra éxito.',
            };
            $extra = in_array($kind, ['api_unavailable', 'administrative_unavailable'], true)
                ? ['retry_idempotency_key' => $idempotencyKey] : [];
            $feedback('error', $message, $extra);
            self::redirect($redirect);
        }

        try {
            $readback = $reader->readAttendancePolicy($user, $workplaceId, $effectiveFrom);
        } catch (\Throwable) {
            $readback = ['ok' => false];
        }
        $readPolicy = $readback['data']['policy'] ?? null;
        $createdData = $created['data'] ?? [];
        $confirmed = ($readback['ok'] ?? false) === true
            && is_array($readPolicy)
            && (int) ($readPolicy['id'] ?? 0) === (int) ($createdData['id'] ?? 0)
            && (int) ($readPolicy['workplace_id'] ?? 0) === $workplaceId
            && (int) ($readPolicy['policy_version'] ?? 0) === (int) ($createdData['policy_version'] ?? 0)
            && (string) ($readPolicy['effective_from_local_date'] ?? '') === $effectiveFrom
            && (string) ($readPolicy['timezone_id'] ?? '') === (string) ($createdData['timezone_id'] ?? '')
            && (int) ($readPolicy['retardo_tolerance_minutes'] ?? -1) === $tolerance
            && (int) ($readPolicy['incidence_deadline_days'] ?? -1) === $deadlineDays
            && (int) ($readPolicy['actor_portal_user_id'] ?? 0) === (int) $user->id
            && (string) ($readPolicy['source'] ?? '') === 'PORTAL3_ADMIN';
        if (!$confirmed) {
            if ($wantsJson) $jsonResponse(502, ['ok' => false, 'code' => 'readback_unconfirmed', 'message' => 'El servidor respondió, pero no confirmó la lectura exacta. No se muestra éxito; vuelve a intentar con la misma clave.']);
            $feedback('error', 'La escritura respondió, pero la lectura exacta de la versión no pudo confirmarse. Reintenta con la misma clave.', [
                'retry_idempotency_key' => $idempotencyKey,
            ]);
            self::redirect($redirect);
        }

        if ($wantsJson) {
            $jsonResponse(200, [
                'ok' => true,
                'message' => 'La configuración quedó guardada y confirmada.',
                'policy' => $readPolicy,
                'latest_scheduled_policy' => $readback['data']['latest_scheduled_policy'] ?? null,
                'current_local_date' => $readback['data']['current_local_date'] ?? null,
                'next_idempotency_key' => self::newIdempotencyKey(),
            ]);
        }
        $feedback('success', 'La política quedó guardada como versión prospectiva y se confirmó mediante lectura de API V2.');
        self::redirect($redirect);
    }

    /** Update a station-scoped reader username and optionally rotate its secret without reading it back. */
    public function updateM11ReaderProfile(): void
    {
        $wantsJson = str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
        $input = Request::all();
        $rawWorkplaceId = $input['workplace_id'] ?? null;
        $workplaceId = $this->requestWorkplaceId($input);
        $profileId = self::positiveInteger($input['profile_id'] ?? null);
        $username = is_string($input['username'] ?? null) ? trim($input['username']) : '';
        $password = is_string($input['new_password'] ?? null) ? $input['new_password'] : '';
        $passwordConfirm = is_string($input['new_password_confirm'] ?? null) ? $input['new_password_confirm'] : '';
        $user = Auth::user();
        $readerEditAccess = $workplaceId !== null
            ? ChecadorAccessService::authorizeReaderProfiles($workplaceId, 'editar')
            : ['allowed' => false];
        $status = 422;
        $message = '';
        $result = null;

        if ($user === null) {
            $status = 401;
            $message = 'Inicia sesión nuevamente para editar el perfil del lector.';
        } elseif (($rawWorkplaceId !== null && $rawWorkplaceId !== '' && $workplaceId === null) || $workplaceId === null) {
            $status = 403;
            $message = 'Selecciona una localidad autorizada para continuar.';
        } elseif (empty($readerEditAccess['allowed'])) {
            $status = 403;
            $message = 'No tienes permiso para editar el perfil del lector de esta localidad.';
        } elseif (!ChecadorDailyReadService::m11ConfigurationPostOperationsEnabled()) {
            $status = 503;
            $message = 'La edición del perfil del lector no está disponible en este entorno.';
        } elseif ($profileId === null || $username === '' || mb_strlen($username) > 120 || strlen($password) > 512) {
            $message = 'Revisa el usuario y el tamaño de la nueva contraseña.';
        } elseif ($password !== '' && !hash_equals($password, $passwordConfirm)) {
            $message = 'La confirmación de la nueva contraseña no coincide.';
        } else {
            $result = \App\Services\BiometricosService::updateM11ReaderProfile($workplaceId, $profileId, $username, $password);
            if (empty($result['success'])) {
                $message = (string) ($result['message'] ?? 'No se confirmó la actualización.');
                $lowerMessage = strtolower($message);
                $status = str_contains($lowerMessage, 'no pertenece') ? 403
                    : (str_contains($lowerMessage, 'mapeo') ? 409 : 500);
            }
        }

        if ($wantsJson) {
            if ($message !== '') {
                JsonResponse::send(['ok' => false, 'message' => $message], $status, [
                    'Cache-Control' => 'private, no-store', 'Vary' => 'Accept',
                ]);
            }
            $readback = \App\Services\BiometricosService::readM11ReaderProfiles($workplaceId);
            $confirmed = null;
            if (($readback['status'] ?? '') === 'station_scoped') {
                foreach ($readback['items'] as $item) {
                    if ((int) ($item['id'] ?? 0) === $profileId) {
                        $confirmed = ['id' => $profileId, 'username' => (string) ($item['usuario'] ?? '')];
                        break;
                    }
                }
            }
            if ($confirmed === null || $confirmed['username'] !== $username) {
                JsonResponse::send([
                    'ok' => false,
                    'message' => 'El cambio no pudo confirmarse. Consulta el perfil antes de volver a intentarlo.',
                ], 502, ['Cache-Control' => 'private, no-store', 'Vary' => 'Accept']);
            }
            JsonResponse::send([
                'ok' => true,
                'message' => (string) ($result['message'] ?? 'Perfil del lector actualizado y confirmado.'),
                'profile' => $confirmed,
            ], 200, ['Cache-Control' => 'private, no-store', 'Vary' => 'Accept']);
        }

        self::storeM11ConfigurationFeedback(($result['success'] ?? false) ? 'success' : 'error',
            $message !== '' ? $message : (string) ($result['message'] ?? 'No se confirmó la actualización.'), $user, $workplaceId);
        self::redirect('/departamento-operativo/recursos-humanos/checador/configuracion');
    }

    /** Create or rename one row in the existing institutional position catalog; no deletion is exposed. */
    public function saveM11Position(): void
    {
        $wantsJson = str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
        $input = Request::all();
        $user = Auth::user();
        $rawWorkplace = $input['workplace_id'] ?? null;
        $workplaceId = $rawWorkplace === null || $rawWorkplace === '' ? null : $this->requestWorkplaceId($input);
        $rawId = $input['position_id'] ?? null;
        $id = $rawId === null || $rawId === '' ? null : self::positiveInteger($rawId);
        $name = is_string($input['position_name'] ?? null) ? trim($input['position_name']) : '';
        $positionAccess = ChecadorAccessService::authorizePositionCatalog($workplaceId, 'editar');
        $status = 422;
        $message = '';
        $result = null;
        if ($user === null) {
            $status = 401;
            $message = 'Inicia sesión nuevamente para administrar el catálogo.';
        } elseif (!($positionAccess['allowed'] ?? false)) {
            $status = 403;
            $message = 'No tienes permiso para modificar el catálogo institucional de puestos.';
        } elseif (($rawWorkplace !== null && $rawWorkplace !== '' && $workplaceId === null)
            || ($rawId !== null && $rawId !== '' && $id === null)) {
            $message = 'El identificador del puesto o el contexto institucional no es válido.';
        } elseif (!ChecadorDailyReadService::m11ConfigurationPostOperationsEnabled()) {
            $status = 503;
            $message = 'La escritura local M11 está deshabilitada por su gate específico.';
        } elseif ($name === '' || mb_strlen($name) > 100) {
            $message = 'El nombre del puesto debe contener entre 1 y 100 caracteres.';
        } else {
            $result = $id === null
                ? \App\Services\BiometricosService::createM11Position($name, $workplaceId)
                : \App\Services\BiometricosService::updateM11Position($id, $name, $workplaceId);
            if (!($result['success'] ?? false)) {
                $message = (string) ($result['message'] ?? 'No se confirmó el cambio del catálogo.');
                $status = str_contains(strtolower($message), 'ya existe') ? 409 : 422;
            }
        }

        if ($wantsJson) {
            if ($message !== '') {
                JsonResponse::send(['ok' => false, 'message' => $message], $status, ['Cache-Control' => 'private, no-store']);
            }
            $readback = \App\Services\ChecadorConfigurationReadService::readM11PositionCatalog($workplaceId);
            $savedId = (int) ($result['id'] ?? 0);
            $confirmed = null;
            if (($readback['status'] ?? '') === 'current_catalog') {
                foreach ($readback['items'] as $item) {
                    if ((int) ($item['id'] ?? 0) === $savedId) {
                        $confirmed = ['id' => $savedId, 'name' => (string) ($item['name'] ?? '')];
                        break;
                    }
                }
            }
            if ($confirmed === null || $confirmed['name'] !== $name) {
                JsonResponse::send([
                    'ok' => false,
                    'message' => 'El cambio no pudo confirmarse en la lectura del catálogo. Actualiza la página y verifica el registro.',
                ], 502, ['Cache-Control' => 'private, no-store']);
            }
            JsonResponse::send([
                'ok' => true,
                'message' => (string) ($result['message'] ?? 'Puesto guardado y confirmado.'),
                'item' => $confirmed,
            ], 200, ['Cache-Control' => 'private, no-store']);
        }

        if ($user === null || ($rawWorkplace !== null && $rawWorkplace !== '' && $workplaceId === null)) {
            self::storeM11ConfigurationFeedback('error', 'La sesión o el contexto institucional ya no son válidos.', $user, $workplaceId);
        } elseif (!ChecadorDailyReadService::m11ConfigurationPostOperationsEnabled()) {
            self::storeM11ConfigurationFeedback('error', 'La escritura local M11 está deshabilitada por su gate específico.', $user, $workplaceId);
        } elseif ($name === '' || mb_strlen($name) > 100) {
            self::storeM11ConfigurationFeedback('error', 'El nombre del puesto debe contener entre 1 y 100 caracteres.', $user, $workplaceId);
        } else {
            self::storeM11ConfigurationFeedback(($result['success'] ?? false) ? 'success' : 'error', (string) ($result['message'] ?? 'No se confirmó el cambio del catálogo.'), $user, $workplaceId);
        }
        self::redirect('/departamento-operativo/recursos-humanos/checador/configuracion');
    }

    private static function storeM11ConfigurationFeedback(string $type, string $message, mixed $user, ?int $workplaceId): void
    {
        Session::set('checador_m11_configuration_feedback', [
            'type' => $type,
            'message' => $message,
            'user_id' => (int) ($user->id ?? 0),
            'workplace_id' => (int) ($workplaceId ?? 0),
        ]);
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
        if ($workplaceId !== null) {
            $scheduleAccess = ChecadorAccessService::isDepartmentLocality($workplaceId)
                ? ChecadorAccessService::authorizeM04TemplateRead($workplaceId)
                : ChecadorAccessService::authorizeScheduling($workplaceId, 'leer');
            if (!($scheduleAccess['allowed'] ?? false)) {
                self::renderAccessDenied((string) ($scheduleAccess['reason'] ?? 'scheduling_permission_denied'), $workplaceId);
                return;
            }
        }
        $effectiveSchedule = null;
        $effectiveScheduleError = null;
        $historyItems = [];
        $historyError = null;
        $historyHasNext = false;
        $dateAssignments = [];
        $dateAssignmentsTruncated = false;
        $validityCheck = null;
        $currentUser = Auth::user();

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
        } elseif ($workplaceId !== null && !ChecadorAccessService::isDepartmentLocality($workplaceId)
            && $workerId !== null && self::isCivilDate($date) && $currentUser !== null) {
            try {
                $reader = new ChecadorDailyReadService();
                $effectiveResult = $reader->getEffectiveSchedule($currentUser, $workerId, $workplaceId, $date);
                if ($effectiveResult['ok']) {
                    $effectiveSchedule = $effectiveResult['data'];
                } else {
                    $effectiveScheduleError = (string) $effectiveResult['error']['kind'];
                }

                $historyResult = $reader->getShiftAssignments($currentUser, $workerId, $workplaceId, $historyPage, 50);
                if ($historyResult['ok']) {
                    $historyItems = $historyResult['data']['items'];
                    // The API does not expose a total count. A full page means
                    // another page may exist, so the UI keeps pagination open.
                    $historyHasNext = count($historyItems) === 50;
                } else {
                    $historyError = (string) $historyResult['error']['kind'];
                }

                $dateResult = $reader->getShiftAssignments($currentUser, $workerId, $workplaceId, 1, 200, $date);
                if ($dateResult['ok']) {
                    $dateAssignments = $dateResult['data']['items'];
                    $dateAssignmentsTruncated = count($dateAssignments) === 200;
                    if ($effectiveSchedule !== null) {
                        $validityCheck = ChecadorDailyReadService::reconcileEffectiveScheduleValidity(
                            $effectiveSchedule,
                            $dateAssignments,
                            $workerId,
                            $workplaceId,
                            $date,
                            $dateAssignmentsTruncated
                        );
                    }
                } else {
                    $validityCheck = ['status' => 'history_unavailable', 'weekday' => '', 'assignment' => null];
                }
            } catch (\Throwable) {
                $effectiveScheduleError ??= 'api_unavailable';
                $historyError ??= 'api_unavailable';
            }
        }

        $workplaceName = '';
        foreach ($workplaces as $workplace) {
            if ((int) ($workplace['id'] ?? 0) === (int) ($workplaceId ?? 0)) {
                $workplaceName = (string) ($workplace['nombre'] ?? '');
                break;
            }
        }
        $canEditSchedules = $workplaceId !== null && !ChecadorAccessService::isDepartmentLocality($workplaceId) && !$demoMode
            && ChecadorDailyReadService::scheduleApiPostOperationsEnabled()
            && (ChecadorAccessService::authorizeScheduling($workplaceId, 'editar')['allowed'] ?? false);
        $canManageStationTemplates = $workplaceId !== null && !$demoMode
            && ChecadorDailyReadService::scheduleApiPostOperationsEnabled()
            && (ChecadorAccessService::authorizeStationTemplateAdministration($workplaceId)['allowed'] ?? false);
        // Template catalog reads are available to schedule readers; they must
        // not be hidden behind assignment-edit permission.
        $scheduleTemplates = [];
        $templateCatalogStatus = 'not_requested';
        $templateCatalogMessage = '';
        if ($workplaceId !== null && !$demoMode && $currentUser !== null) {
            try {
                $reader = new ChecadorDailyReadService();
                $catalog = $reader->getShiftTemplates($currentUser, 1, 200, $workplaceId);
                if (($catalog['ok'] ?? false) === true) {
                    $templateCatalogStatus = 'received';
                    $catalogItems = $catalog['data']['items'];
                    $itemsForDetail = array_slice($catalogItems, 0, 50);
                    foreach ($itemsForDetail as $template) {
                        $detail = $reader->getShiftTemplateVersion(
                            $currentUser,
                            (string) $template['clave'],
                            (int) $template['version'],
                            $workplaceId
                        );
                        if (($detail['ok'] ?? false) !== true) {
                            $templateCatalogStatus = 'partial';
                            continue;
                        }
                        $scheduleTemplates[] = array_merge($template, ['segmentos' => $detail['data']['segmentos']]);
                    }
                    if (count($catalogItems) > 50) {
                        $templateCatalogStatus = 'partial';
                        $templateCatalogMessage = 'Se alcanzó el límite seguro de detalle de 50 plantillas. La lista puede estar incompleta.';
                    } elseif ($templateCatalogStatus === 'partial') {
                        $templateCatalogMessage = 'No se pudo leer el detalle de todas las plantillas recibidas.';
                    } else {
                        $templateCatalogMessage = count($scheduleTemplates) . ' plantillas recibidas. El contrato API no publica un total; esta página no confirma por sí sola que no existan más.';
                    }
                } else {
                    $templateCatalogStatus = 'unavailable';
                    $templateCatalogMessage = 'No se pudo consultar el catálogo V2.';
                }
            } catch (\Throwable) {
                $templateCatalogStatus = 'unavailable';
                $templateCatalogMessage = 'No se pudo consultar el catálogo V2.';
            }
        } elseif ($demoMode) {
            $templateCatalogStatus = 'unavailable';
            $templateCatalogMessage = 'El modo de demostración no consulta el catálogo operativo.';
        }
        if ($canManageStationTemplates) {
            foreach ($scheduleTemplates as &$template) {
                if (strtoupper((string) ($template['scope'] ?? '')) !== 'STATION'
                    || (int) ($template['id_estacion'] ?? 0) !== $workplaceId
                    || (int) ($template['es_activo'] ?? 0) !== 1) continue;
                $template['edit_fields'] = self::m04EditableTemplateFields($template['segmentos'] ?? []);
            }
            unset($template);
            $localByKey = [];
            foreach ($scheduleTemplates as $candidate) {
                if (strtoupper((string) ($candidate['scope'] ?? '')) === 'STATION'
                    && (int) ($candidate['id_estacion'] ?? 0) === $workplaceId) {
                    $candidate['edit_fields'] = self::m04EditableTemplateFields($candidate['segmentos'] ?? []);
                    $localByKey[(string) ($candidate['clave'] ?? '')] = $candidate;
                }
            }
            foreach ($scheduleTemplates as &$template) {
                if (strtoupper((string) ($template['scope'] ?? '')) !== 'GLOBAL') continue;
                $template['customize_fields'] = self::m04EditableTemplateFields($template['segmentos'] ?? []);
                $localKey = (string) ($template['station_customization']['clave'] ?? '');
                if ($localKey !== '') $template['station_customization']['template'] = $localByKey[$localKey] ?? null;
            }
            unset($template);
        }
        $feedback = Session::pull('checador_schedule_feedback', null);
        $historyWeek = $date !== '' ? (\App\Services\ChecadorWeeklyScheduleService::monday($date) ?? '') : '';
        if (!is_array($feedback) || (int) ($feedback['workplace_id'] ?? 0) !== (int) ($workplaceId ?? 0)
            || (string) ($feedback['week_start'] ?? '') !== $historyWeek) $feedback = null;

        View::render(
            'departamento-operativo/2-recursos-humanos/checador/schedule-history',
            [
                'title' => 'Horarios y turnos',
                'workplaces' => $workplaces,
                'selectedWorkplaceId' => $workplaceId,
                'selectedWorkplaceName' => $workplaceName,
                'selectedDate' => $date,
                'selectedWorkerId' => $workerId,
                'effectiveSchedule' => $effectiveSchedule,
                'effectiveScheduleError' => $effectiveScheduleError,
                'effectiveScheduleErrorMessage' => self::scheduleHistoryErrorMessage($effectiveScheduleError),
                'historyItems' => $historyItems,
                'historyError' => $historyError,
                'historyErrorMessage' => self::scheduleHistoryErrorMessage($historyError),
                'historyPage' => $historyPage,
                'historyHasPrevious' => $historyPage > 1,
                'historyHasNext' => $historyHasNext,
                'validityCheck' => $validityCheck,
                'canEditSchedules' => $canEditSchedules,
                'canManageStationTemplates' => $canManageStationTemplates,
                'scheduleTemplates' => $scheduleTemplates,
                'templateCatalogStatus' => $templateCatalogStatus,
                'templateCatalogMessage' => $templateCatalogMessage,
                'feedback' => $feedback,
                'weekStart' => $historyWeek,
                'demoMode' => $demoMode,
                'demoScenario' => $demoScenario,
                'scripts' => [
                    '/assets/js/vendor.min.js',
                    '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                    '/assets/js/core/module-station-selector.js',
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.module-station.init.js?v=20261027-1',
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.schedule-history.templates.init.js?v=20261106-1',
                ],
                'links' => ['/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'],
                'help' => false,
                'moduleStationKey' => ChecadorAccessService::MODULE_KEY,
            ],
            'departamento-operativo'
        );
    }

    private function renderScheduleWeek(?int $workplaceId, array $workplaces, string $weekStart, bool $demoMode, ?string $demoScenario): void
    {
        $isDepartmentLocality = $workplaceId !== null && ChecadorAccessService::isDepartmentLocality($workplaceId);
        $isAutolavado = $workplaceId === ChecadorAccessService::AUTOLAVADO_LOCALITY_ID;
        if ($workplaceId !== null && (!$isDepartmentLocality || $isAutolavado)) {
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
        $timezone = '';
        $dates = \App\Services\ChecadorWeeklyScheduleService::dates($weekStart);
        $effectiveDateMin = $weekStart;
        $effectiveDateMax = (string) ($dates[6]['date'] ?? $weekStart);
        $validWeek = $dates !== [] && \App\Services\ChecadorWeeklyScheduleService::monday($weekStart) === $weekStart;
        $errorMessage = '';
        $workers = [];
        $matrix = ['dates' => $dates, 'workers' => []];
        $templates = [];
        $listedTemplatesById = [];
        $templateDetailsById = [];
        $coverage = 'not_loaded';
        $rosterCoverage = 'not_loaded';
        $rosterMessage = '';
        $matrixCoverage = 'not_evaluated';
        $matrixMessage = '';
        $referenceRosterCount = 0;
        $weekMembershipOnlyCount = 0;
        $workersWithVersionedMembership = 0;
        $workersWithCurrentRosterDateEvidence = 0;
        $unversionedMembershipCandidateCount = null;
        $assignmentApiWorkerCount = 0;
        $assignmentApiRecordCount = 0;
        $assignmentHistoryIncomplete = false;
        $reader = null;
        $templateCatalogAvailable = false;
        $currentUser = Auth::user();
        if ($isDepartmentLocality && !$isAutolavado) {
            $rosterMessage = 'La programación semanal de turnos no está disponible para esta localidad (departamento operativo). Los turnos aplican exclusivamente a estaciones de servicio autorizadas.';
            $matrixMessage = 'La programación semanal no está disponible para departamentos.';
            $legalReference = \App\Services\ChecadorLaborPolicy::referenceForDate($weekStart);
            View::render(
                'departamento-operativo/2-recursos-humanos/checador/schedule-week',
                [
                    'title' => 'Programación semanal de turnos',
                    'workplaces' => $workplaces,
                    'selectedWorkplaceId' => $workplaceId,
                    'selectedWorkplaceName' => $workplaceName,
                    'weekStart' => $weekStart,
                    'weekDates' => $matrix['dates'],
                    'scheduleRows' => [],
                    'habitualScheduleRows' => [],
                    'templates' => [],
                    'templateCatalogAvailable' => false,
                    'assignableWorkers' => [],
                    'timezone' => 'No aplica',
                    'coverage' => 'not_available',
                    'rosterCoverage' => 'not_available',
                    'rosterMessage' => $rosterMessage,
                    'referenceRosterCount' => 0,
                    'weekMembershipOnlyCount' => 0,
                    'workersWithVersionedMembership' => 0,
                    'workersWithCurrentRosterDateEvidence' => 0,
                    'unversionedMembershipCandidateCount' => 0,
                    'assignmentApiWorkerCount' => 0,
                    'assignmentApiRecordCount' => 0,
                    'assignmentHistoryIncomplete' => false,
                    'matrixCoverage' => 'not_available',
                    'matrixMessage' => $matrixMessage,
                    'errorMessage' => '',
                    'feedback' => null,
                    'canAssign' => false,
                    'canEditSchedules' => false,
                    'canManageTemplates' => false,
                    'canManageStationTemplates' => false,
                    'canManageGlobalTemplates' => false,
                    'demoMode' => $demoMode,
                    'legalReference' => $legalReference,
                    'dailyLimits' => \App\Services\ChecadorLaborPolicy::generalDailyReferences(),
                    'isDepartmentLocality' => true,
                    'links' => [],
                    'scripts' => [
                        '/assets/js/core/module-station-selector.js',
                        '/assets/js/departamento-operativo/2-recursos-humanos/checador.module-station.init.js?v=20261027-1'
                    ],
                    'help' => false,
                    'moduleStationKey' => ChecadorAccessService::MODULE_KEY,
                ],
                'departamento-operativo'
            );
            return;
        } elseif (!$validWeek) {
            $errorMessage = 'Selecciona una semana actual o futura cuyo inicio sea lunes.';
        } elseif ($workplaceId === null) {
            $errorMessage = 'Selecciona un centro autorizado en el selector institucional.';
        } elseif ($currentUser === null) {
            $errorMessage = 'La sesión ya no es válida. Inicia sesión nuevamente.';
        } else {
            try {
                $reader = new ChecadorDailyReadService();
                // In demo this method is served only by the existing local
                // fixture; in real mode it reads the API's Workplace timezone.
                $timezoneResult = $reader->resolveWorkplaceLocalDay($currentUser, $workplaceId, $weekStart);
                if (($timezoneResult['ok'] ?? false) !== true) {
                    $errorMessage = 'No se pudo confirmar la zona horaria laboral del centro; no se cargó la programación semanal.';
                } else {
                    $timezone = (string) $timezoneResult['data']['timezone'];
                    if (!\App\Services\ChecadorOperationalPeriod::isIanaTimezone($timezone)) {
                        $errorMessage = 'La zona horaria laboral no cumple el contrato IANA.';
                    } else {
                        $today = \App\Services\ChecadorOperationalPeriod::currentLocalDate(
                            $timezone,
                            new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
                        );
                        if ($today !== null) {
                            $tomorrow = (new \DateTimeImmutable($today, new \DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d');
                            $effectiveDateMin = max($weekStart, $tomorrow);
                        }
                    }
                }
                if ($errorMessage === '' && !\App\Services\ChecadorWeeklyScheduleService::isCurrentOrFutureWeek($weekStart, $timezone)) {
                    $errorMessage = 'Sólo se pueden consultar semanas actuales o futuras según la zona horaria laboral del centro.';
                }
                if ($errorMessage === '') {
                    $roster = (new \App\Services\ChecadorAttendanceRosterService())->workersForScheduleWeek($workplaceId, $weekStart, $timezone, $demoMode);
                    $workers = $roster['workers'];
                    $rosterCoverage = (string) ($roster['status'] ?? 'unknown');
                    $rosterMessage = (string) ($roster['message'] ?? 'El padrón no está disponible.');
                    $referenceRosterCount = (int) ($roster['reference_roster_count'] ?? 0);
                    $weekMembershipOnlyCount = (int) ($roster['week_membership_only_count'] ?? 0);
                    $workersWithVersionedMembership = (int) ($roster['workers_with_versioned_membership'] ?? 0);
                    $unversionedMembershipCandidateCount = is_int($roster['unversioned_membership_candidate_count'] ?? null)
                        ? $roster['unversioned_membership_candidate_count'] : null;
                    $workersWithCurrentRosterDateEvidence = count(array_filter(
                        $workers,
                        static fn(array $worker): bool => ($worker['verified_dates'] ?? []) !== []
                            && ($worker['versioned_verified_dates'] ?? []) === []
                    ));
                    $coverage = $rosterCoverage;
                    $rosterReadable = in_array($rosterCoverage, ['ready', 'partial', 'demo_fixture'], true);
                    if (!$rosterReadable) {
                        $errorMessage = $rosterMessage;
                        $matrixMessage = 'La matriz semanal no se evaluó porque el padrón no está completo.';
                        $matrixCoverage = 'not_evaluated';
                    } elseif ($rosterCoverage === 'partial') {
                        // Keep confirmed rows visible, but never turn an incomplete
                        // membership set into a definitive roster or empty result.
                        $matrixCoverage = 'partial';
                        $matrixMessage = 'La matriz sólo incluirá las fechas de membresía verificadas.';
                    }
                    if ($rosterReadable) {
                        $templateResult = $reader->getShiftTemplates($currentUser, 1, 100, $workplaceId);
                        if (($templateResult['ok'] ?? false) !== true) {
                            $errorMessage = 'El catálogo contractual de turnos no está disponible; no se puede ofrecer una asignación.';
                            $matrixCoverage = 'unavailable';
                            $matrixMessage = $errorMessage;
                        } else {
                            $listedTemplates = is_array($templateResult['data']['items'] ?? null) ? $templateResult['data']['items'] : [];
                            foreach ($listedTemplates as $listedTemplate) {
                                if (is_array($listedTemplate) && (int) ($listedTemplate['id'] ?? 0) > 0) {
                                    $listedTemplatesById[(int) $listedTemplate['id']] = $listedTemplate;
                                }
                            }
                            $templateCatalogAvailable = true;
                            // Current API payload exposes page/per_page/items,
                            // but no total or continuation signal. A shorter
                            // page alone cannot certify complete coverage.
                            $coverage = 'partial';
                            $matrixCoverage = 'partial';
                            $matrixMessage = 'La API no informa total ni página siguiente; se muestran los registros visibles y la matriz no se declara completa.';
                            if (count($listedTemplates) === 100) {
                                $coverage = 'partial';
                                $matrixCoverage = 'partial';
                                $matrixMessage = 'El catálogo alcanzó el límite de lectura; la matriz puede estar incompleta.';
                            }
                            foreach (array_slice($listedTemplates, 0, 50) as $listedTemplate) {
                                if (!is_array($listedTemplate) || trim((string) ($listedTemplate['clave'] ?? '')) === ''
                                    || (int) ($listedTemplate['version'] ?? 0) < 1) {
                                    continue;
                                }
                                $detail = $reader->getShiftTemplateVersion($currentUser, (string) $listedTemplate['clave'], (int) $listedTemplate['version'], $workplaceId);
                                $segments = $detail['data']['segmentos'] ?? null;
                                if (($detail['ok'] ?? false) !== true || !is_array($segments) || $segments === []) {
                                    $coverage = 'partial';
                                    $matrixCoverage = 'partial';
                                    $matrixMessage = 'No se pudo confirmar el detalle de todas las plantillas disponibles.';
                                    continue;
                                }
                                $template = array_merge($listedTemplate, $detail['data']);
                                $templates[] = $template;
                                $templateDetailsById[(int) $template['id']] = $template;
                            }
                            if (count($listedTemplates) > 50) {
                                $coverage = 'partial';
                                $matrixCoverage = 'partial';
                                $matrixMessage = 'Se alcanzó el límite seguro de detalle de plantillas disponibles.';
                            }
                        }
                    }
                    $assignmentsByWorker = [];
                    if ($errorMessage === '' && $rosterReadable) {
                        $assignmentApiWorkerCount = 0;
                        $assignmentApiRecordCount = 0;
                        $assignmentHistoryIncomplete = false;

                        foreach ($workers as $workerIndex => $worker) {
                            $workerId = (int) ($worker['worker_id'] ?? 0);
                            if (($worker['verified_dates'] ?? []) === []) {
                                $workers[$workerIndex]['schedule_read_status'] = 'not_requested_unverified';
                                $assignmentsByWorker[$workerId] = [];
                                continue;
                            }
                            $assignmentResult = $reader->getShiftAssignments($currentUser, $workerId, $workplaceId, 1, 200);
                            if (($assignmentResult['ok'] ?? false) !== true) {
                                $assignmentsByWorker[$workerId] = [];
                                $workers[$workerIndex]['schedule_read_status'] = 'unavailable';
                                $assignmentHistoryIncomplete = true;
                                $coverage = 'partial';
                                continue;
                            }
                            $items = is_array($assignmentResult['data']['items'] ?? null)
                                ? $assignmentResult['data']['items']
                                : [];
                            $assignmentsByWorker[$workerId] = $items;
                            $workers[$workerIndex]['schedule_read_status'] = 'available';
                            $assignmentApiWorkerCount++;
                            $assignmentApiRecordCount += count($items);
                            if (count($items) === 200) {
                                $coverage = 'partial';
                                $assignmentHistoryIncomplete = true;
                            }
                        }

                        $usedTemplateIds = [];
                        foreach ($assignmentsByWorker as $items) {
                            foreach ($items as $assignment) {
                                if (($assignment['id_plantilla'] ?? null) !== null) {
                                    $usedTemplateIds[(int) $assignment['id_plantilla']] = true;
                                }
                            }
                        }
                        $templatesById = [];
                        foreach (array_keys($usedTemplateIds) as $templateId) {
                            if (isset($listedTemplatesById[$templateId])) {
                                $templatesById[$templateId] = $listedTemplatesById[$templateId];
                            }
                        }
                        $matrix = \App\Services\ChecadorWeeklyScheduleService::matrix($workers, $assignmentsByWorker, $templatesById, $weekStart);
                    }
                }
            } catch (\Throwable) {
                $errorMessage = 'La programación semanal no está disponible. No se consultó ninguna fuente legacy como sustituto.';
                $matrixCoverage = 'unavailable';
                $matrixMessage = $errorMessage;
            }
        }

        $feedback = Session::pull('checador_schedule_feedback', null);
        if (!is_array($feedback) || (int) ($feedback['workplace_id'] ?? 0) !== (int) ($workplaceId ?? 0)
            || (string) ($feedback['week_start'] ?? '') !== $weekStart) $feedback = null;
        $assignableWorkers = \App\Services\ChecadorWeeklyScheduleService::workersWithAnyVerifiedMembership(
            $workers,
            array_column($dates, 'date')
        );
        $canEditSchedules = $workplaceId !== null && !$demoMode && $templateCatalogAvailable
            && ChecadorDailyReadService::scheduleApiPostOperationsEnabled()
            && (ChecadorAccessService::authorizeScheduling($workplaceId, 'editar')['allowed'] ?? false);
        $canManageStationTemplates = $workplaceId !== null && !$demoMode
            && ChecadorDailyReadService::scheduleApiPostOperationsEnabled()
            && (ChecadorAccessService::authorizeStationTemplateAdministration($workplaceId)['allowed'] ?? false);
        $canManageGlobalTemplates = $workplaceId !== null && !$demoMode
            && ChecadorDailyReadService::scheduleApiPostOperationsEnabled()
            && (ChecadorAccessService::authorizeTemplateAdministration($workplaceId)['allowed'] ?? false);
        $canManageTemplates = $canManageStationTemplates || $canManageGlobalTemplates;
        // A partial roster must not hide entry for a date whose membership is
        // verified. The selector and write handler narrow eligibility by date.
        $canAssign = $canEditSchedules && $assignableWorkers !== [];
        $legalReference = \App\Services\ChecadorLaborPolicy::referenceForDate($weekStart);
        View::render(
            'departamento-operativo/2-recursos-humanos/checador/schedule-week',
            [
                'title' => 'Programación semanal de turnos',
                'workplaces' => $workplaces,
                'selectedWorkplaceId' => $workplaceId,
                'selectedWorkplaceName' => $workplaceName,
                'weekStart' => $weekStart,
                'effectiveDateMin' => $effectiveDateMin,
                'effectiveDateMax' => $effectiveDateMax,
                'weekDates' => $matrix['dates'],
                'scheduleRows' => $matrix['workers'],
                'habitualScheduleRows' => $matrix['habitual_workers'] ?? [],
                'templates' => $templates,
                'templateCatalogAvailable' => $templateCatalogAvailable,
                'assignableWorkers' => $assignableWorkers,
                'timezone' => $timezone,
                'coverage' => $coverage,
                'rosterCoverage' => $rosterCoverage,
                'rosterMessage' => $rosterMessage,
                'referenceRosterCount' => $referenceRosterCount,
                'weekMembershipOnlyCount' => $weekMembershipOnlyCount,
                'workersWithVersionedMembership' => $workersWithVersionedMembership,
                'workersWithCurrentRosterDateEvidence' => $workersWithCurrentRosterDateEvidence,
                'unversionedMembershipCandidateCount' => $unversionedMembershipCandidateCount,
                'assignmentApiWorkerCount' => $assignmentApiWorkerCount,
                'assignmentApiRecordCount' => $assignmentApiRecordCount,
                'assignmentHistoryIncomplete' => $assignmentHistoryIncomplete,
                'matrixCoverage' => $matrixCoverage,
                'matrixMessage' => $matrixMessage,
                'errorMessage' => $errorMessage,
                'feedback' => $feedback,
                'canAssign' => $canAssign,
                'canEditSchedules' => $canEditSchedules,
                'canManageTemplates' => $canManageTemplates,
                'canManageStationTemplates' => $canManageStationTemplates,
                'canManageGlobalTemplates' => $canManageGlobalTemplates,
                'demoMode' => $demoMode,
                'legalReference' => $legalReference,
                'dailyLimits' => \App\Services\ChecadorLaborPolicy::generalDailyReferences(),
                'links' => [],
                'scripts' => ['/assets/js/core/module-station-selector.js', '/assets/js/departamento-operativo/2-recursos-humanos/checador.module-station.init.js?v=20261027-1', '/assets/js/departamento-operativo/2-recursos-humanos/checador.schedule-template.init.js', '/assets/js/departamento-operativo/2-recursos-humanos/checador.habitual-schedule.init.js?v=20261105-4', '/assets/js/departamento-operativo/2-recursos-humanos/checador.schedule-week-ui.init.js?v=20261104-1'],
                'help' => false,
                'moduleStationKey' => ChecadorAccessService::MODULE_KEY,
            ],
            'departamento-operativo'
        );
    }

    /** Mutates exactly one worker/day through the frozen API contract, then requires canonical readback. */
    public function createScheduleAssignment(): void
    {
        $input = Request::all();
        $workplaceId = $this->requestWorkplaceId($input);
        $workerId = self::positiveInteger($input['worker_id'] ?? null);
        $targetDate = is_string($input['target_date'] ?? null) ? trim($input['target_date']) : '';
        $weekStart = is_string($input['week_start'] ?? null) ? trim($input['week_start']) : '';
        $effectiveStartDate = is_string($input['effective_start_date'] ?? null) ? trim($input['effective_start_date']) : '';
        $templateValue = is_string($input['template_id'] ?? null) ? trim($input['template_id']) : '';
        $tipoAsignacion = strtoupper(trim((string) ($input['tipo_asignacion'] ?? 'HABITUAL')));
        if (!in_array($tipoAsignacion, ['HABITUAL', 'TEMPORAL'], true)) $tipoAsignacion = 'HABITUAL';
        $message = 'No se realizó la asignación.';
        $type = 'warning';
        $responseStatus = 422;
        $demoMode = ChecadorDailyReadService::demoModeEnabled();
        $user = Auth::user();
        $wantsJson = Request::expectsJson();
        $selectedWeekDates = array_column(\App\Services\ChecadorWeeklyScheduleService::dates($weekStart), 'date');
        $validAssignmentDate = $tipoAsignacion === 'TEMPORAL'
            ? in_array($targetDate, $selectedWeekDates, true)
            : (self::isCivilDate($effectiveStartDate)
                && in_array($effectiveStartDate, $selectedWeekDates, true)
                && \App\Services\ChecadorWeeklyScheduleService::firstWeekdayOnOrAfter(
                    (string) \App\Services\ChecadorWeeklyScheduleService::weekdayFor($targetDate),
                    $effectiveStartDate
                ) === $targetDate);
        if ($workplaceId === null || $workerId === null || !self::isCivilDate($targetDate)
            || !in_array((string) ($input['confirm_action'] ?? ''), ['1', 'on'], true)
            || \App\Services\ChecadorWeeklyScheduleService::monday($weekStart) !== $weekStart
            || !$validAssignmentDate) {
            $message = $tipoAsignacion === 'HABITUAL'
                ? 'Revisa el centro, la semana, la fecha de inicio, el primer día recurrente y la confirmación solicitada.'
                : 'Revisa el centro, la semana, el día y la confirmación solicitada.';
        } elseif ($demoMode) {
            $message = 'La demostración es de consulta; no guarda asignaciones sintéticas ni llama al API.';
            $responseStatus = 403;
        } elseif (!ChecadorDailyReadService::scheduleApiPostOperationsEnabled()) {
            $message = 'La escritura de programación permanece protegida por el gate local específico de horarios.';
            $responseStatus = 403;
        } elseif (!(ChecadorAccessService::authorizeScheduling($workplaceId, 'editar')['allowed'] ?? false)) {
            if ($wantsJson) {
                $message = 'No tienes permiso para modificar la programación de este centro.';
                $responseStatus = 403;
            } else {
                self::renderAccessDenied('scheduling_permission_denied', $workplaceId);
                return;
            }
        } elseif ($user === null) {
            $message = 'La sesión ya no es válida.';
            $responseStatus = 401;
        } else {
            try {
                $reader = new ChecadorDailyReadService();
                $timezoneResult = $reader->resolveWorkplaceLocalDay($user, $workplaceId, $targetDate);
                $timezone = ($timezoneResult['ok'] ?? false) === true
                    ? (string) ($timezoneResult['data']['timezone'] ?? '')
                    : '';
                $membershipWeekStart = \App\Services\ChecadorWeeklyScheduleService::monday($targetDate);
                $todayAtWorkplace = \App\Services\ChecadorOperationalPeriod::isIanaTimezone($timezone)
                    ? \App\Services\ChecadorOperationalPeriod::currentLocalDate($timezone, new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                    : null;
                if (!\App\Services\ChecadorOperationalPeriod::isIanaTimezone($timezone)
                    || $membershipWeekStart === null
                    || !\App\Services\ChecadorWeeklyScheduleService::isCurrentOrFutureWeek($weekStart, $timezone)
                    || !\App\Services\ChecadorWeeklyScheduleService::isCurrentOrFutureWeek($membershipWeekStart, $timezone)
                    || ($tipoAsignacion === 'HABITUAL' && ($todayAtWorkplace === null || $effectiveStartDate <= $todayAtWorkplace))) {
                    $message = 'No se pudo confirmar la zona horaria, la fecha futura de inicio o una semana actual/futura; no se envió la asignación.';
                    throw new \DomainException('Workplace timezone or week boundary is unavailable.');
                }
                $roster = (new \App\Services\ChecadorAttendanceRosterService())
                    ->workersForScheduleWeek($workplaceId, $membershipWeekStart, $timezone, false);
                $eligibleRoster = \App\Services\ChecadorWeeklyScheduleService::workersWithVerifiedMembership(
                    $roster['workers'] ?? [],
                    [$targetDate]
                );
                $workerVerifiedForDate = false;
                foreach ($eligibleRoster as $eligibleWorker) {
                    if ((int) ($eligibleWorker['worker_id'] ?? 0) === $workerId) {
                        $workerVerifiedForDate = true;
                        break;
                    }
                }
                if (!in_array((string) ($roster['status'] ?? ''), ['ready', 'partial'], true) || !$workerVerifiedForDate) {
                    $message = 'No se pudo confirmar que el trabajador pertenezca canónicamente a este centro en la fecha seleccionada; no se envió la asignación.';
                } else {
                    $templates = $reader->getShiftTemplates($user, 1, 100, $workplaceId);
                    $templateId = $templateValue === 'REST' ? null : self::positiveInteger($templateValue);
                    $templateAllowed = $templateId === null && $templateValue === 'REST';
                    foreach ($templates['data']['items'] ?? [] as $template) {
                        if ((int) ($template['id'] ?? 0) === (int) $templateId && (int) ($template['es_activo'] ?? 0) === 1) $templateAllowed = true;
                    }
                    if (!($templates['ok'] ?? false) || !$templateAllowed) {
                        $message = 'La plantilla seleccionada no está disponible o activa en el catálogo API.';
                        if (!($templates['ok'] ?? false)) $responseStatus = 502;
                    } else {
                        $weekday = \App\Services\ChecadorWeeklyScheduleService::weekdayFor($targetDate);
                        // A date-filtered read hides later versions. For a new
                        // recurring HABITUAL interval, those future versions
                        // determine where the new interval must stop. TEMPORAL
                        // remains a one-day interval and keeps its date-filtered read.
                        $existing = $tipoAsignacion === 'HABITUAL'
                            ? $reader->getAllShiftAssignmentsSnapshot($user, $workerId, $workplaceId, null, 200)
                            : $reader->getShiftAssignments($user, $workerId, $workplaceId, 1, 200, $targetDate);
                        if (!($existing['ok'] ?? false)
                            || ($tipoAsignacion === 'HABITUAL' && ($existing['data']['complete'] ?? false) !== true)) {
                            $message = 'No se pudo consultar la asignación existente; por seguridad no se envió el cambio.';
                            $responseStatus = 502;
                        } else {
                            $historyItems = $existing['data']['items'];
                            $effective = array_values(array_filter($historyItems, static fn(array $row): bool =>
                                ($row['dia_semana'] ?? '') === $weekday
                                && strtoupper((string) ($row['tipo_asignacion'] ?? 'HABITUAL')) === $tipoAsignacion
                                && (string) ($row['valido_desde'] ?? '') <= $targetDate
                                && (($row['valido_hasta'] ?? null) === null || $targetDate < (string) $row['valido_hasta'])
                            ));
                            $alreadySame = count($effective) === 1
                                && (int) ($effective[0]['es_activo'] ?? 0) === 1
                                && (($effective[0]['id_plantilla'] ?? null) === $templateId);
                            if ($alreadySame) {
                                $message = 'La asignación solicitada ya aparece vigente en la API; no se creó un registro duplicado.';
                                $type = 'info';
                                $responseStatus = 200;
                            } elseif ($effective !== []) {
                                $message = "El historial ya contiene una asignación {$tipoAsignacion} aplicable al {$targetDate}. No se creó otra; consulta el historial antes de elegir una fecha.";
                                $responseStatus = 409;
                            } else {
                                $validTo = null;
                                if ($tipoAsignacion === 'TEMPORAL') {
                                    $validTo = (new \DateTimeImmutable($targetDate, new \DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d');
                                } else {
                                    $interval = \App\Services\ChecadorWeeklyScheduleService::habitualAssignmentInterval(
                                        $historyItems,
                                        $workerId,
                                        $workplaceId,
                                        $weekday,
                                        $targetDate
                                    );
                                    if (($interval['ok'] ?? false) !== true) {
                                        $message = "El historial habitual no permite crear una vigencia sin traslape para el {$targetDate}. No se envió la asignación.";
                                        $responseStatus = 409;
                                    } else {
                                        $validTo = $interval['valid_to'];
                                    }
                                }
                                if ($tipoAsignacion === 'TEMPORAL' || $responseStatus !== 409) {
                                    $assignment = [
                                        'id_personal' => $workerId, 'id_estacion' => $workplaceId, 'id_plantilla' => $templateId,
                                        'tipo_asignacion' => $tipoAsignacion,
                                        'dia_semana' => $weekday, 'valido_desde' => $targetDate, 'valido_hasta' => $validTo,
                                    ];
                                    $result = $reader->createShiftAssignment($user, $assignment, self::newIdempotencyKey());
                                    if (!($result['ok'] ?? false)) {
                                        $apiStatus = (int) ($result['error']['status'] ?? 0);
                                        $responseStatus = $apiStatus >= 400 && $apiStatus < 500 ? $apiStatus : 502;
                                        $message = ($result['error']['kind'] ?? '') === 'administrative_conflict'
                                            ? "La API rechazó la asignación del {$targetDate} porque entra en conflicto con otra vigencia. No se creó ese registro."
                                            : 'La API no aceptó la asignación. No se creó un registro.';
                                    } elseif ((int) ($result['http_status'] ?? 0) !== 201) {
                                        $message = 'La API no confirmó la creación HTTP 201 con su detalle de auditoría; no se declara guardado.';
                                        $responseStatus = 502;
                                    } else {
                                        $readback = $reader->getShiftAssignments($user, $workerId, $workplaceId, 1, 200, $targetDate);
                                        $confirmed = false;
                                        foreach ($readback['data']['items'] ?? [] as $row) {
                                            if ((int) ($row['id_personal'] ?? 0) === $workerId && (int) ($row['id_estacion'] ?? 0) === $workplaceId
                                                && (string) ($row['dia_semana'] ?? '') === $weekday && ($row['id_plantilla'] ?? null) === $templateId
                                                && strtoupper((string) ($row['tipo_asignacion'] ?? 'HABITUAL')) === $tipoAsignacion
                                                && (string) ($row['valido_desde'] ?? '') === $targetDate
                                                && (($row['valido_hasta'] ?? null) === $validTo) && (int) ($row['es_activo'] ?? 0) === 1) {
                                                $confirmed = true;
                                                break;
                                            }
                                        }
                                        if ($confirmed) {
                                            $message = ($tipoAsignacion === 'TEMPORAL' ? 'Excepción temporal' : 'Asignación habitual') . ' confirmada por lectura posterior de la API.';
                                            $type = 'success';
                                            $responseStatus = 201;
                                        } else {
                                            $message = 'La respuesta de escritura no pudo confirmarse mediante lectura posterior; no se declara guardado.';
                                            $responseStatus = 502;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (\Throwable) {
                if ($message === 'No se realizó la asignación.') {
                    $message = 'No fue posible comprobar la pertenencia, guardar o releer la asignación. No se declara éxito.';
                }
                $responseStatus = 502;
            }
        }
        if ($wantsJson) {
            http_response_code($responseStatus);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, private');
            header('Vary: Accept');
            echo json_encode([
                'ok' => in_array($responseStatus, [200, 201], true),
                'status' => $type,
                'message' => $message,
                'worker_id' => $workerId,
                'workplace_id' => $workplaceId,
                'target_date' => $targetDate,
                'assignment_type' => $tipoAsignacion,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $feedbackWorkplaceId = $workplaceId;
        if ($feedbackWorkplaceId === null) {
            $feedbackScope = ChecadorAccessService::authorize(null);
            if (($feedbackScope['allowed'] ?? false) === true) {
                $feedbackWorkplaceId = $this->sessionWorkplaceId($feedbackScope);
            }
        }
        Session::set('checador_schedule_feedback', [
            'type' => $type, 'message' => $message, 'workplace_id' => (int) ($feedbackWorkplaceId ?? 0), 'week_start' => $weekStart,
        ]);
        $query = [];
        if ($weekStart !== '') $query['week_start'] = $weekStart;
        self::redirect(self::scheduleWeekHref($query));
    }

    /** Close an existing assignment through the API and require a canonical history readback. */
    public function closeScheduleAssignment(): void
    {
        $input = Request::all();
        $workplaceId = $this->requestWorkplaceId($input);
        $workerId = self::positiveInteger($input['worker_id'] ?? null);
        $assignmentId = self::positiveInteger($input['assignment_id'] ?? null);
        $selectedDate = is_string($input['selected_date'] ?? null) ? trim($input['selected_date']) : '';
        $closeDate = is_string($input['effective_close_date'] ?? null) ? trim($input['effective_close_date']) : '';
        $expectedFrom = is_string($input['expected_valido_desde'] ?? null) ? trim($input['expected_valido_desde']) : '';
        $expectedToRaw = $input['expected_valido_hasta'] ?? null;
        $expectedTo = is_string($expectedToRaw) && $expectedToRaw !== '' ? trim($expectedToRaw) : null;
        $weekStart = is_string($input['week_start'] ?? null) ? trim($input['week_start']) : '';
        $message = 'No se cerró la asignación.';
        $type = 'warning';
        $user = Auth::user();

        if ($workplaceId === null || $workerId === null || $assignmentId === null
            || !self::isCivilDate($selectedDate) || !self::isCivilDate($closeDate)
            || !self::isCivilDate($expectedFrom) || ($expectedTo !== null && !self::isCivilDate($expectedTo))
            || \App\Services\ChecadorWeeklyScheduleService::monday($weekStart) !== $weekStart
            || !in_array((string) ($input['confirm_action'] ?? ''), ['1', 'on'], true)) {
            $message = 'Revisa la fecha efectiva, la versión de la asignación y la confirmación.';
        } elseif (ChecadorDailyReadService::demoModeEnabled()) {
            $message = 'La demostración es de consulta; no cierra asignaciones ni llama al API.';
        } elseif (!ChecadorDailyReadService::scheduleApiPostOperationsEnabled()) {
            $message = 'La escritura de programación permanece protegida por el gate local específico de horarios.';
        } elseif (!(ChecadorAccessService::authorizeScheduling($workplaceId, 'editar')['allowed'] ?? false)) {
            self::renderAccessDenied('scheduling_permission_denied', $workplaceId);
            return;
        } elseif ($user === null) {
            $message = 'La sesión ya no es válida.';
        } else {
            try {
                $reader = new ChecadorDailyReadService();
                $before = $reader->getShiftAssignments($user, $workerId, $workplaceId, 1, 200);
                $source = null;
                foreach ($before['data']['items'] ?? [] as $item) {
                    if ((int) ($item['id'] ?? 0) === $assignmentId) $source = $item;
                }
                if (!($before['ok'] ?? false) || $source === null
                    || (int) ($source['es_activo'] ?? 0) !== 1
                    || (string) ($source['valido_desde'] ?? '') !== $expectedFrom
                    || (($source['valido_hasta'] ?? null) !== $expectedTo)) {
                    $message = 'La asignación cambió o no pudo corroborarse; no se envió el cierre.';
                } else {
                    $closeResult = $reader->closeShiftAssignment(
                        $user, $assignmentId, $workplaceId, $closeDate, $expectedFrom, $expectedTo, self::newIdempotencyKey()
                    );
                    $readback = $reader->getShiftAssignments($user, $workerId, $workplaceId, 1, 200);
                    $confirmed = false;
                    foreach ($readback['data']['items'] ?? [] as $item) {
                        if ((int) ($item['id'] ?? 0) === $assignmentId
                            && (int) ($item['es_activo'] ?? -1) === 0
                            && (string) ($item['valido_hasta'] ?? '') === $closeDate) {
                            $confirmed = true;
                            break;
                        }
                    }
                    if (($closeResult['ok'] ?? false) && ($readback['ok'] ?? false) && $confirmed) {
                        $message = 'Cierre confirmado mediante lectura posterior de la API.';
                        $type = 'success';
                    } else {
                        $message = 'El cierre no pudo confirmarse mediante historial posterior; no se declara guardado.';
                    }
                }
            } catch (\Throwable) {
                $message = 'No fue posible cerrar o releer la asignación; no se declara éxito.';
            }
        }
        Session::set('checador_schedule_feedback', [
            'type' => $type, 'message' => $message, 'workplace_id' => (int) ($workplaceId ?? 0), 'week_start' => $weekStart,
        ]);
        $query = array_filter(['date' => $selectedDate, 'worker_id' => $workerId], static fn(mixed $value): bool => $value !== null && $value !== '');
        $target = '/departamento-operativo/recursos-humanos/checador/horarios-turnos';
        self::redirect($target . ($query === [] ? '' : '?' . http_build_query($query)));
    }

    /** Atomically replace an assignment through API V2 and verify both historical rows. */
    public function replaceScheduleAssignment(): void
    {
        $input = Request::all();
        $workplaceId = $this->requestWorkplaceId($input);
        $workerId = self::positiveInteger($input['worker_id'] ?? null);
        $assignmentId = self::positiveInteger($input['assignment_id'] ?? null);
        $selectedDate = is_string($input['selected_date'] ?? null) ? trim($input['selected_date']) : '';
        $closeDate = is_string($input['effective_close_date'] ?? null) ? trim($input['effective_close_date']) : '';
        $expectedFrom = is_string($input['expected_valido_desde'] ?? null) ? trim($input['expected_valido_desde']) : '';
        $expectedToRaw = $input['expected_valido_hasta'] ?? null;
        $expectedTo = is_string($expectedToRaw) && $expectedToRaw !== '' ? trim($expectedToRaw) : null;
        $replacementToRaw = $input['replacement_valido_hasta'] ?? null;
        $replacementTo = is_string($replacementToRaw) && $replacementToRaw !== '' ? trim($replacementToRaw) : null;
        $templateValue = is_string($input['replacement_template_id'] ?? null) ? trim($input['replacement_template_id']) : '';
        $templateId = $templateValue === 'REST' ? null : self::positiveInteger($templateValue);
        $weekStart = is_string($input['week_start'] ?? null) ? trim($input['week_start']) : '';
        $message = 'No se reemplazó la asignación.';
        $type = 'warning';
        $user = Auth::user();

        if ($workplaceId === null || $workerId === null || $assignmentId === null
            || !self::isCivilDate($selectedDate) || !self::isCivilDate($closeDate)
            || !self::isCivilDate($expectedFrom) || ($expectedTo !== null && !self::isCivilDate($expectedTo))
            || ($replacementTo !== null && !self::isCivilDate($replacementTo))
            || ($templateValue !== 'REST' && $templateId === null)
            || \App\Services\ChecadorWeeklyScheduleService::monday($weekStart) !== $weekStart
            || !in_array((string) ($input['confirm_action'] ?? ''), ['1', 'on'], true)) {
            $message = 'Revisa el turno, la vigencia, la fecha efectiva y la confirmación.';
        } elseif (ChecadorDailyReadService::demoModeEnabled()) {
            $message = 'La demostración es de consulta; no sustituye asignaciones ni llama al API.';
        } elseif (!ChecadorDailyReadService::scheduleApiPostOperationsEnabled()) {
            $message = 'La escritura de programación permanece protegida por el gate local específico de horarios.';
        } elseif (!(ChecadorAccessService::authorizeScheduling($workplaceId, 'editar')['allowed'] ?? false)) {
            self::renderAccessDenied('scheduling_permission_denied', $workplaceId);
            return;
        } elseif ($user === null) {
            $message = 'La sesión ya no es válida.';
        } else {
            try {
                $reader = new ChecadorDailyReadService();
                $before = $reader->getShiftAssignments($user, $workerId, $workplaceId, 1, 200);
                $source = null;
                foreach ($before['data']['items'] ?? [] as $item) {
                    if ((int) ($item['id'] ?? 0) === $assignmentId) $source = $item;
                }
                if (!($before['ok'] ?? false) || $source === null
                    || (int) ($source['es_activo'] ?? 0) !== 1
                    || (string) ($source['valido_desde'] ?? '') !== $expectedFrom
                    || (($source['valido_hasta'] ?? null) !== $expectedTo)) {
                    $message = 'La asignación cambió o no pudo corroborarse; no se envió el reemplazo.';
                } else {
                    $replacement = $reader->replaceShiftAssignment(
                        $user, $assignmentId, $workplaceId, $closeDate, $expectedFrom, $expectedTo,
                        $templateId, $replacementTo, self::newIdempotencyKey()
                    );
                    $readback = $reader->getShiftAssignments($user, $workerId, $workplaceId, 1, 200);
                    $closedConfirmed = false;
                    $replacementConfirmed = false;
                    $expectedReplacementId = (int) ($replacement['data']['replacement']['id'] ?? 0);
                    foreach ($readback['data']['items'] ?? [] as $item) {
                        if ((int) ($item['id'] ?? 0) === $assignmentId
                            && (int) ($item['es_activo'] ?? -1) === 0
                            && (string) ($item['valido_hasta'] ?? '') === $closeDate) $closedConfirmed = true;
                        if ((int) ($item['id'] ?? 0) === $expectedReplacementId && $expectedReplacementId > 0
                            && (int) ($item['id_personal'] ?? 0) === $workerId
                            && (int) ($item['id_estacion'] ?? 0) === $workplaceId
                            && (($item['id_plantilla'] ?? null) === $templateId)
                            && (string) ($item['valido_desde'] ?? '') === $closeDate
                            && (($item['valido_hasta'] ?? null) === $replacementTo)
                            && (int) ($item['es_activo'] ?? -1) === 1) $replacementConfirmed = true;
                    }
                    if (($replacement['ok'] ?? false) && ($readback['ok'] ?? false) && $closedConfirmed && $replacementConfirmed) {
                        $message = 'Cierre y reemplazo confirmados mediante lectura posterior de la API.';
                        $type = 'success';
                    } else {
                        $message = 'El cierre y el reemplazo no pudieron confirmarse con el historial posterior; no se declara éxito.';
                    }
                }
            } catch (\Throwable) {
                $message = 'No fue posible reemplazar o releer la asignación; no se declara éxito.';
            }
        }
        Session::set('checador_schedule_feedback', [
            'type' => $type, 'message' => $message, 'workplace_id' => (int) ($workplaceId ?? 0), 'week_start' => $weekStart,
        ]);
        self::redirect(self::scheduleWeekHref(['week_start' => $weekStart]));
    }

    /** Create one immutable version-1 template through the authorized V2 contract. */
    public function createScheduleTemplate(): void
    {
        $input = Request::all();
        $wantsJson = str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json')
            || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
        $rawWorkplaceId = $input['workplace_id'] ?? null;
        $workplaceId = $this->requestWorkplaceId($input);
        if ($rawWorkplaceId !== null && $rawWorkplaceId !== '' && ($workplaceId === null || self::positiveInteger($rawWorkplaceId) !== $workplaceId)) {
            if ($wantsJson) {
                JsonResponse::send(['ok' => false, 'code' => 'forbidden', 'message' => 'No tienes permiso para registrar turnos en esta estación.'], 403, ['Cache-Control' => 'private, no-store']);
            }
            self::renderAccessDenied('scheduling_permission_denied', self::positiveInteger($rawWorkplaceId));
            return;
        }
        $weekStartInput = is_string($input['week_start'] ?? null) ? trim($input['week_start']) : '';
        $weekStart = $weekStartInput !== '' ? $weekStartInput : \App\Services\ChecadorWeeklyScheduleService::monday(date('Y-m-d'));
        $confirmAction = (string) ($input['confirm_action'] ?? ($wantsJson ? '1' : ''));
        $user = Auth::user();
        $message = 'No se realizó el registro de plantilla.';
        $type = 'warning';
        $demoMode = ChecadorDailyReadService::demoModeEnabled();
        $segmentsInput = $input['segments'] ?? null;
        $segments = [];
        if (is_array($segmentsInput)) {
            foreach (array_values($segmentsInput) as $index => $segment) {
                if (!is_array($segment)) {
                    $segments = [];
                    break;
                }
                $segments[] = [
                    'ordinal' => $index + 1,
                    'tipo_segmento' => is_string($segment['tipo_segmento'] ?? null) ? strtoupper(trim($segment['tipo_segmento'])) : 'WORK',
                    'hora_inicio_local' => is_string($segment['hora_inicio_local'] ?? null) ? trim($segment['hora_inicio_local']) : '',
                    'hora_fin_local' => is_string($segment['hora_fin_local'] ?? null) ? trim($segment['hora_fin_local']) : '',
                    'dia_offset_inicio' => isset($segment['dia_offset_inicio']) ? (int) $segment['dia_offset_inicio'] : 0,
                    'dia_offset_fin' => isset($segment['dia_offset_fin']) ? (int) $segment['dia_offset_fin'] : 0,
                ];
            }
        }
        $templateKey = is_string($input['template_key'] ?? null) ? trim($input['template_key']) : '';
        $templateName = is_string($input['template_name'] ?? null) ? trim($input['template_name']) : '';
        $templateType = is_string($input['template_type'] ?? null) ? strtoupper(trim($input['template_type'])) : '';

        if ($templateKey === '' && $templateName !== '' && $workplaceId !== null && $segments !== []) {
            $cleanName = strtoupper(preg_replace('/[^A-Z0-9]/', '_', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $templateName) ?: $templateName));
            $cleanName = trim(preg_replace('/_+/', '_', $cleanName), '_');
            $startClean = str_replace(':', '', substr($segments[0]['hora_inicio_local'] ?? '0000', 0, 5));
            $endClean = str_replace(':', '', substr(end($segments)['hora_fin_local'] ?? '0000', 0, 5));
            $templateKey = substr("LOC_{$workplaceId}_{$cleanName}_{$startClean}{$endClean}", 0, 64);
        }

        if (!in_array($templateType, ['REGULAR', 'NOCTURNO', 'MIXTO', 'ROTATIVO'], true)) {
            $hasOvernight = false;
            foreach ($segments as $seg) {
                if ((int) ($seg['dia_offset_fin'] ?? 0) > (int) ($seg['dia_offset_inicio'] ?? 0)) {
                    $hasOvernight = true;
                    break;
                }
            }
            $templateType = $hasOvernight ? 'NOCTURNO' : 'REGULAR';
        }

        $template = [
            'clave' => $templateKey,
            'nombre' => $templateName,
            'tipo_turno' => $templateType,
            'segmentos' => $segments,
        ];

        if ($workplaceId === null || \App\Services\ChecadorWeeklyScheduleService::monday($weekStart) !== $weekStart
            || !in_array($confirmAction, ['1', 'on'], true) || $templateName === '' || $segments === []) {
            $message = 'Revisa el centro, el nombre, los horarios y la confirmación solicitada.';
            if ($wantsJson) {
                JsonResponse::send(['ok' => false, 'code' => 'invalid_input', 'message' => $message], 422, ['Cache-Control' => 'private, no-store']);
            }
        } elseif ($demoMode) {
            $message = 'La demostración es de consulta; no guarda plantillas sintéticas ni llama al API.';
            if ($wantsJson) {
                JsonResponse::send(['ok' => false, 'code' => 'demo_mode', 'message' => $message], 403, ['Cache-Control' => 'private, no-store']);
            }
        } elseif (!ChecadorDailyReadService::scheduleApiPostOperationsEnabled()) {
            $message = 'La escritura de plantillas permanece protegida por el gate local específico de horarios.';
            if ($wantsJson) {
                JsonResponse::send(['ok' => false, 'code' => 'gate_disabled', 'message' => $message], 403, ['Cache-Control' => 'private, no-store']);
            }
        } elseif (!(ChecadorAccessService::authorizeStationTemplateAdministration($workplaceId)['allowed'] ?? false)
            && !(ChecadorAccessService::authorizeTemplateAdministration($workplaceId)['allowed'] ?? false)) {
            if ($wantsJson) {
                JsonResponse::send(['ok' => false, 'code' => 'forbidden', 'message' => 'No tienes permiso para administrar turnos en esta estación.'], 403, ['Cache-Control' => 'private, no-store']);
            }
            self::renderAccessDenied('scheduling_permission_denied', $workplaceId);
            return;
        } elseif ($user === null) {
            $message = 'La sesión ya no es válida.';
            if ($wantsJson) {
                JsonResponse::send(['ok' => false, 'code' => 'unauthenticated', 'message' => $message], 401, ['Cache-Control' => 'private, no-store']);
            }
        } else {
            try {
                $reader = new ChecadorDailyReadService();
                $timezoneResult = $reader->resolveWorkplaceLocalDay($user, $workplaceId, $weekStart);
                $timezone = ($timezoneResult['ok'] ?? false) === true
                    ? (string) ($timezoneResult['data']['timezone'] ?? '')
                    : '';
                if (!\App\Services\ChecadorOperationalPeriod::isIanaTimezone($timezone)
                    || !\App\Services\ChecadorWeeklyScheduleService::isCurrentOrFutureWeek($weekStart, $timezone)) {
                    $message = 'No se pudo confirmar la zona horaria laboral o la semana ya no es actual/futura; no se envió la plantilla.';
                    if ($wantsJson) {
                        JsonResponse::send(['ok' => false, 'code' => 'timezone_error', 'message' => $message], 422, ['Cache-Control' => 'private, no-store']);
                    }
                } else {
                    $catalog = $reader->getShiftTemplates($user, 1, 200, $workplaceId);
                    if (!($catalog['ok'] ?? false)) {
                        $message = 'No se pudo consultar el catálogo de plantillas; no se envió la solicitud.';
                        if ($wantsJson) {
                            JsonResponse::send(['ok' => false, 'code' => 'catalog_error', 'message' => $message], 502, ['Cache-Control' => 'private, no-store']);
                        }
                    } else {
                        $existingKey = false;
                        $existingName = false;
                        foreach ($catalog['data']['items'] as $existingTemplate) {
                            if (strcasecmp((string) ($existingTemplate['clave'] ?? ''), (string) $template['clave']) === 0) {
                                $existingKey = true;
                                break;
                            }
                            if (strcasecmp(trim((string) ($existingTemplate['nombre'] ?? '')), trim((string) $template['nombre'])) === 0) {
                                $existingName = true;
                                break;
                            }
                        }
                        if ($existingKey) {
                            $message = 'La clave ya aparece en el catálogo. Usa otra clave o consulta la versión existente; no se creó un duplicado.';
                            if ($wantsJson) {
                                JsonResponse::send(['ok' => false, 'code' => 'conflict', 'message' => $message], 409, ['Cache-Control' => 'private, no-store']);
                            }
                        } elseif ($existingName) {
                            $message = 'Ya existe un turno con este nombre en el catálogo. Indica un nombre diferente.';
                            if ($wantsJson) {
                                JsonResponse::send(['ok' => false, 'code' => 'conflict', 'message' => $message], 409, ['Cache-Control' => 'private, no-store']);
                            }
                        } else {
                            $result = $reader->createShiftTemplate($user, $template, self::newIdempotencyKey(), $workplaceId);
                            if (($result['ok'] ?? false) === true) {
                                $message = 'Plantilla y segmentos confirmados mediante lectura posterior de la API.';
                                $type = 'success';
                                if ($wantsJson) {
                                    JsonResponse::send(['ok' => true, 'message' => $message, 'template' => $result['data']], 200, ['Cache-Control' => 'private, no-store']);
                                }
                            } else {
                                $kind = (string) ($result['error']['kind'] ?? '');
                                $message = match ($kind) {
                                    'bad_request' => 'Revisa la clave, el nombre, el tipo y todos los segmentos. No se guardó una plantilla inválida.',
                                    'administrative_conflict' => 'La API ya contiene esa clave o detectó un conflicto. Consulta el catálogo antes de reintentar.',
                                    'schedule_template_readback_unconfirmed' => 'La respuesta no pudo confirmarse mediante lectura posterior. Consulta el catálogo antes de reintentar; no se declara guardado.',
                                    default => 'La API no confirmó la plantilla y sus segmentos. Consulta el catálogo antes de reintentar; no se declara guardado.',
                                };
                                if ($wantsJson) {
                                    $statusCode = $kind === 'bad_request' ? 422 : ($kind === 'administrative_conflict' ? 409 : 502);
                                    JsonResponse::send(['ok' => false, 'code' => $kind, 'message' => $message], $statusCode, ['Cache-Control' => 'private, no-store']);
                                }
                            }
                        }
                    }
                }
            } catch (\Throwable) {
                $message = 'No fue posible validar, guardar o releer la plantilla. No se declara éxito; consulta el catálogo antes de reintentar.';
                if ($wantsJson) {
                    JsonResponse::send(['ok' => false, 'code' => 'exception', 'message' => $message], 500, ['Cache-Control' => 'private, no-store']);
                }
            }
        }

        $feedbackWorkplaceId = $workplaceId;
        if ($feedbackWorkplaceId === null) {
            $feedbackScope = ChecadorAccessService::authorize(null);
            if (($feedbackScope['allowed'] ?? false) === true) {
                $feedbackWorkplaceId = $this->sessionWorkplaceId($feedbackScope);
            }
        }

        Session::set('checador_schedule_feedback', [
            'type' => $type,
            'message' => $message,
            'workplace_id' => (int) ($feedbackWorkplaceId ?? 0),
            'week_start' => $weekStart,
        ]);
        $query = [];
        if ($weekStart !== '') $query['week_start'] = $weekStart;
        self::redirect(self::scheduleWeekHref($query));
    }

    /** Create one station-scoped template through the authorized V2 contract. */
    public function createStationScheduleTemplate(): void
    {
        $this->createScheduleTemplate();
    }

    /** Copy a visible global template into the current station through the V2 writer. */
    public function customizeGlobalScheduleTemplate(): void
    {
        $input = Request::all(); $user = Auth::user();
        $rawWorkplace = $input['workplace_id'] ?? null; $workplaceId = $this->requestWorkplaceId($input);
        $sourceKey = is_string($input['source_template_key'] ?? null) ? trim($input['source_template_key']) : '';
        $sourceVersion = self::positiveInteger($input['source_template_version'] ?? null);
        $name = is_string($input['template_name'] ?? null) ? trim($input['template_name']) : '';
        if ($user === null) JsonResponse::send(['ok' => false, 'code' => 'unauthenticated', 'message' => 'La sesión ya no es válida.'], 401, ['Cache-Control' => 'private, no-store']);
        if ($rawWorkplace === null || $workplaceId === null || self::positiveInteger($rawWorkplace) !== $workplaceId) {
            JsonResponse::send(['ok' => false, 'code' => 'forbidden', 'message' => 'La estación solicitada no coincide con tu alcance autorizado.'], 403, ['Cache-Control' => 'private, no-store']);
        }
        if (!ChecadorDailyReadService::scheduleApiPostOperationsEnabled()
            || ChecadorAccessService::isDepartmentLocality((int) ($workplaceId ?? 0))
            || !(ChecadorAccessService::authorizeStationTemplateAdministration($workplaceId)['allowed'] ?? false)) {
            JsonResponse::send(['ok' => false, 'code' => 'forbidden', 'message' => 'No tienes permiso para personalizar turnos en esta estación.'], 403, ['Cache-Control' => 'private, no-store']);
        }
        if (preg_match('/\\A[A-Za-z0-9_-]{1,100}\\z/', $sourceKey) !== 1 || $sourceVersion === null
            || $name === '' || mb_strlen($name) > 128 || !is_array($input['segments'] ?? null) || $input['segments'] === []) {
            JsonResponse::send(['ok' => false, 'code' => 'invalid_input', 'message' => 'Revisa el nombre y los segmentos del turno.'], 422, ['Cache-Control' => 'private, no-store']);
        }
        try {
            $reader = new ChecadorDailyReadService();
            $catalog = $reader->getShiftTemplates($user, 1, 200, $workplaceId);
            if (!($catalog['ok'] ?? false)) JsonResponse::send(['ok' => false, 'code' => 'catalog_unavailable', 'message' => 'No se pudo comprobar el catálogo autorizado.'], 502, ['Cache-Control' => 'private, no-store']);
            $visibleSource = null;
            foreach ($catalog['data']['items'] as $item) {
                if ((string) ($item['clave'] ?? '') === $sourceKey && strtoupper((string) ($item['scope'] ?? '')) === 'GLOBAL') { $visibleSource = $item; break; }
            }
            if ($visibleSource === null || $sourceVersion !== (int) ($visibleSource['version'] ?? 0)) {
                JsonResponse::send(['ok' => false, 'code' => 'source_unavailable', 'message' => 'El turno global seleccionado ya no está disponible. Actualiza el catálogo.'], 404, ['Cache-Control' => 'private, no-store']);
            }
            $source = $reader->getShiftTemplateVersion($user, $sourceKey, $sourceVersion, $workplaceId);
            if (!($source['ok'] ?? false) || strtoupper((string) ($source['data']['scope'] ?? '')) !== 'GLOBAL'
                || (int) ($source['data']['version'] ?? 0) !== $sourceVersion || (int) ($source['data']['es_activo'] ?? 0) !== 1) {
                JsonResponse::send(['ok' => false, 'code' => 'source_unavailable', 'message' => 'No se pudo confirmar el turno global de origen.'], 404, ['Cache-Control' => 'private, no-store']);
            }
            $segments = self::m04NormalizeRequestedSegments($input['segments']);
            if ($segments === null) JsonResponse::send(['ok' => false, 'code' => 'invalid_input', 'message' => 'Los segmentos del turno no son válidos.'], 422, ['Cache-Control' => 'private, no-store']);
            $result = $reader->customizeGlobalShiftTemplate($user, ['clave' => $sourceKey, 'version' => $sourceVersion], [
                'nombre' => $name, 'tipo_turno' => (string) $source['data']['tipo_turno'], 'segmentos' => $segments,
            ], self::newIdempotencyKey(), $workplaceId);
            if (!($result['ok'] ?? false)) {
                $kind = (string) ($result['error']['kind'] ?? '');
                $status = $kind === 'bad_request' ? 422 : ($kind === 'administrative_conflict' ? 409 : 502);
                if ($kind === 'api_authorization') $status = 403;
                JsonResponse::send(['ok' => false, 'code' => $kind ?: 'customization_failed', 'message' => $status === 409
                    ? 'Ya existe una personalización vinculada a otra versión de origen.' : ($status === 403 ? 'La API rechazó el alcance de esta personalización.' : 'No se pudo confirmar la personalización. Consulta el catálogo antes de reintentar.')], $status, ['Cache-Control' => 'private, no-store']);
            }
            JsonResponse::send(['ok' => true, 'message' => $result['created'] ? 'La personalización local fue guardada y confirmada mediante lectura posterior.' : 'Ya existía una personalización; se recuperó mediante lectura posterior.', 'template' => $result['data']], $result['created'] ? 201 : 200, ['Cache-Control' => 'private, no-store']);
        } catch (\Throwable) {
            JsonResponse::send(['ok' => false, 'code' => 'server_error', 'message' => 'No fue posible confirmar la personalización. Consulta el catálogo antes de reintentar.'], 500, ['Cache-Control' => 'private, no-store']);
        }
    }

    private static function m04NormalizeRequestedSegments(array $segments): ?array
    {
        if (!array_is_list($segments) || $segments === [] || count($segments) > 255) return null;
        $normalized = [];
        foreach ($segments as $index => $segment) {
            if (!is_array($segment) || (int) ($segment['ordinal'] ?? 0) !== $index + 1) return null;
            $type = strtoupper(trim((string) ($segment['tipo_segmento'] ?? '')));
            $start = (string) ($segment['hora_inicio_local'] ?? ''); $end = (string) ($segment['hora_fin_local'] ?? '');
            $startOffset = filter_var($segment['dia_offset_inicio'] ?? null, FILTER_VALIDATE_INT); $endOffset = filter_var($segment['dia_offset_fin'] ?? null, FILTER_VALIDATE_INT);
            if (!in_array($type, ['WORK', 'BREAK'], true)
                || preg_match('/\\A(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?\\z/', $start) !== 1
                || preg_match('/\\A(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?\\z/', $end) !== 1
                || !in_array($startOffset, [0, 1], true) || !in_array($endOffset, [0, 1], true)) return null;
            $normalized[] = ['ordinal' => $index + 1, 'tipo_segmento' => $type,
                'hora_inicio_local' => strlen($start) === 5 ? $start . ':00' : $start,
                'hora_fin_local' => strlen($end) === 5 ? $end . ':00' : $end,
                'dia_offset_inicio' => $startOffset, 'dia_offset_fin' => $endOffset];
        }
        return $normalized;
    }

    /** Edit a station-local template by creating its next immutable V2 version. */
    public function createStationScheduleTemplateVersion(): void
    {
        $input = Request::all();
        $user = Auth::user();
        $rawWorkplaceId = $input['workplace_id'] ?? null;
        $workplaceId = $this->requestWorkplaceId($input);
        if ($user === null) {
            JsonResponse::send(['ok' => false, 'code' => 'unauthenticated', 'message' => 'La sesión ya no es válida.'], 401, ['Cache-Control' => 'private, no-store']);
        }
        if ($rawWorkplaceId === null || $workplaceId === null || self::positiveInteger($rawWorkplaceId) !== $workplaceId) {
            JsonResponse::send(['ok' => false, 'code' => 'forbidden', 'message' => 'La estación solicitada no coincide con tu localidad autorizada.'], 403, ['Cache-Control' => 'private, no-store']);
        }
        if (!ChecadorDailyReadService::scheduleApiPostOperationsEnabled()) {
            JsonResponse::send(['ok' => false, 'code' => 'gate_disabled', 'message' => 'La edición de turnos está protegida por el gate local de horarios.'], 403, ['Cache-Control' => 'private, no-store']);
        }
        if (!(ChecadorAccessService::authorizeStationTemplateAdministration($workplaceId)['allowed'] ?? false)) {
            JsonResponse::send(['ok' => false, 'code' => 'forbidden', 'message' => 'No tienes permiso para editar turnos de esta estación.'], 403, ['Cache-Control' => 'private, no-store']);
        }

        $key = is_string($input['template_key'] ?? null) ? trim($input['template_key']) : '';
        $expectedVersion = self::positiveInteger($input['expected_version'] ?? null);
        $name = is_string($input['template_name'] ?? null) ? trim($input['template_name']) : '';
        $rawSegments = $input['segments'] ?? null;
        $segments = [];
        if (is_array($rawSegments) && array_is_list($rawSegments)) {
            foreach ($rawSegments as $index => $segment) {
                if (!is_array($segment)) { $segments = []; break; }
                $segments[] = ['ordinal' => $index + 1,
                    'tipo_segmento' => is_string($segment['tipo_segmento'] ?? null) ? strtoupper(trim($segment['tipo_segmento'])) : '',
                    'hora_inicio_local' => is_string($segment['hora_inicio_local'] ?? null) ? trim($segment['hora_inicio_local']) : '',
                    'hora_fin_local' => is_string($segment['hora_fin_local'] ?? null) ? trim($segment['hora_fin_local']) : '',
                    'dia_offset_inicio' => filter_var($segment['dia_offset_inicio'] ?? null, FILTER_VALIDATE_INT),
                    'dia_offset_fin' => filter_var($segment['dia_offset_fin'] ?? null, FILTER_VALIDATE_INT)];
            }
        }
        if (preg_match('/\A[A-Za-z0-9_-]{1,64}\z/', $key) !== 1 || $expectedVersion === null || $name === ''
            || mb_strlen($name) > 128 || $segments === [] || !self::m04SegmentsAreEditable($segments)) {
            JsonResponse::send(['ok' => false, 'code' => 'invalid_input', 'message' => 'Revisa el nombre y las horas del turno.'], 422, ['Cache-Control' => 'private, no-store']);
        }

        try {
            $reader = new ChecadorDailyReadService();
            $catalog = $reader->getShiftTemplates($user, 1, 200, $workplaceId);
            if (!($catalog['ok'] ?? false)) {
                JsonResponse::send(['ok' => false, 'code' => 'catalog_unavailable', 'message' => 'No se pudo comprobar el catálogo actual. No se guardó ningún cambio.'], 502, ['Cache-Control' => 'private, no-store']);
            }
            $current = null;
            foreach ($catalog['data']['items'] as $item) if ((string) ($item['clave'] ?? '') === $key) { $current = $item; break; }
            if ($current === null || strtoupper((string) ($current['scope'] ?? '')) !== 'STATION'
                || (int) ($current['id_estacion'] ?? 0) !== $workplaceId) {
                JsonResponse::send(['ok' => false, 'code' => 'forbidden', 'message' => 'Solo puedes editar turnos locales de tu estación.'], 403, ['Cache-Control' => 'private, no-store']);
            }
            if ((int) ($current['es_activo'] ?? 0) !== 1) {
                JsonResponse::send(['ok' => false, 'code' => 'conflict', 'message' => 'El turno ya no está activo; actualiza el catálogo.'], 409, ['Cache-Control' => 'private, no-store']);
            }
            if ((int) ($current['version'] ?? 0) === $expectedVersion + 1 && $current['nombre'] === $name) {
                $reconciled = $reader->getShiftTemplateVersion($user, $key, $expectedVersion + 1, $workplaceId);
                if (($reconciled['ok'] ?? false) === true
                    && (int) ($reconciled['data']['id'] ?? 0) === (int) ($current['id'] ?? 0)
                    && (string) ($reconciled['data']['nombre'] ?? '') === $name
                    && (string) ($reconciled['data']['tipo_turno'] ?? '') === (string) ($current['tipo_turno'] ?? '')
                    && (int) ($reconciled['data']['es_activo'] ?? 0) === 1
                    && ($reconciled['data']['segmentos'] ?? null) === $segments) {
                    $reconciled['data']['scope'] = 'STATION';
                    $reconciled['data']['id_estacion'] = $workplaceId;
                    JsonResponse::send(['ok' => true, 'message' => 'La versión ya estaba guardada y fue confirmada mediante lectura posterior.', 'template' => $reconciled['data']], 200, ['Cache-Control' => 'private, no-store']);
                }
            }
            if ((int) ($current['version'] ?? 0) !== $expectedVersion) {
                JsonResponse::send(['ok' => false, 'code' => 'conflict', 'message' => 'El turno cambió desde que abriste el formulario. Actualiza el catálogo antes de editarlo.'], 409, ['Cache-Control' => 'private, no-store']);
            }
            $previous = $reader->getShiftTemplateVersion($user, $key, $expectedVersion, $workplaceId);
            if (!($previous['ok'] ?? false) || (int) ($previous['data']['id'] ?? 0) !== (int) ($current['id'] ?? 0)) {
                JsonResponse::send(['ok' => false, 'code' => 'catalog_unavailable', 'message' => 'No se pudo verificar la versión que se va a conservar. No se guardó ningún cambio.'], 502, ['Cache-Control' => 'private, no-store']);
            }
            $editable = self::m04EditableTemplateFields($previous['data']['segmentos'] ?? []);
            if ($editable === null) {
                JsonResponse::send(['ok' => false, 'code' => 'invalid_input', 'message' => 'La estructura de segmentos de este turno no se puede editar con este formulario.'], 422, ['Cache-Control' => 'private, no-store']);
            }
            $result = $reader->createShiftTemplateVersion($user, $key, $expectedVersion, [
                'nombre' => $name, 'tipo_turno' => (string) ($previous['data']['tipo_turno'] ?? 'REGULAR'), 'segmentos' => $segments,
            ], $workplaceId);
            if (!($result['ok'] ?? false)) {
                $kind = (string) ($result['error']['kind'] ?? '');
                $status = in_array($kind, ['bad_request'], true) ? 422 : (in_array($kind, ['api_authorization'], true) ? 403 : 502);
                if (in_array($kind, ['administrative_conflict'], true)) $status = 409;
                JsonResponse::send(['ok' => false, 'code' => $kind ?: 'readback_unconfirmed', 'message' => $status === 422
                    ? 'Los datos del turno no son válidos.' : ($status === 403 ? 'La API no autoriza esta operación para la estación.' : 'No se pudo confirmar la nueva versión. Consulta el catálogo antes de reintentar.')], $status, ['Cache-Control' => 'private, no-store']);
            }
            JsonResponse::send(['ok' => true, 'message' => 'La nueva versión del turno fue guardada y confirmada mediante lectura posterior.', 'template' => $result['data']], 200, ['Cache-Control' => 'private, no-store']);
        } catch (\Throwable) {
            JsonResponse::send(['ok' => false, 'code' => 'server_error', 'message' => 'No fue posible validar o confirmar la versión. Consulta el catálogo antes de reintentar.'], 500, ['Cache-Control' => 'private, no-store']);
        }
    }

    /** Return edit controls only for segment shapes represented losslessly by the M04 editor. */
    private static function m04EditableTemplateFields(mixed $segments): ?array
    {
        if (!is_array($segments) || !array_is_list($segments)) return null;
        $segments = array_values($segments);
        if (count($segments) !== 1 && count($segments) !== 3) return null;
        $types = array_map(static fn(array $segment): string => strtoupper((string) ($segment['tipo_segmento'] ?? '')), $segments);
        if ($types !== ['WORK'] && $types !== ['WORK', 'BREAK', 'WORK']) return null;
        $previousEnd = null;
        foreach ($segments as $index => $segment) {
            if ((int) ($segment['ordinal'] ?? 0) !== $index + 1) return null;
            $startTime = (string) ($segment['hora_inicio_local'] ?? '');
            $endTime = (string) ($segment['hora_fin_local'] ?? '');
            if (preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9]:00\z/', $startTime) !== 1
                || preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9]:00\z/', $endTime) !== 1) return null;
            $startOffset = (int) ($segment['dia_offset_inicio'] ?? -1);
            $endOffset = (int) ($segment['dia_offset_fin'] ?? -1);
            if (!in_array($startOffset, [0, 1], true) || !in_array($endOffset, [0, 1], true)) return null;
            $start = $startOffset * 1440 + (int) substr($startTime, 0, 2) * 60 + (int) substr($startTime, 3, 2);
            $end = $endOffset * 1440 + (int) substr($endTime, 0, 2) * 60 + (int) substr($endTime, 3, 2);
            if ($end <= $start || ($previousEnd !== null && $previousEnd !== $start)) return null;
            if ($index === 0 && $startOffset !== 0) return null;
            $previousEnd = $end;
        }
        $work = array_values(array_filter($segments, static fn(array $segment): bool => strtoupper((string) $segment['tipo_segmento']) === 'WORK'));
        $breaks = array_values(array_filter($segments, static fn(array $segment): bool => strtoupper((string) $segment['tipo_segmento']) === 'BREAK'));
        return ['start_time' => substr((string) $work[0]['hora_inicio_local'], 0, 5),
            'end_time' => substr((string) $work[array_key_last($work)]['hora_fin_local'], 0, 5),
            'break_start' => $breaks === [] ? '' : substr((string) $breaks[0]['hora_inicio_local'], 0, 5),
            'break_end' => $breaks === [] ? '' : substr((string) $breaks[0]['hora_fin_local'], 0, 5)];
    }

    private static function m04SegmentsAreEditable(array $segments): bool
    {
        $fields = self::m04EditableTemplateFields($segments);
        return $fields !== null;
    }

    private static function scheduleHistoryErrorMessage(?string $kind): string
    {
        return match ($kind) {
            'demo_history_unavailable' => 'La demostración no contiene historial de asignaciones; no se consultó la API real.',
            'bad_request' => 'Indica una fecha válida, un trabajador canónico y una estación autorizada.',
            'api_authentication' => 'La autenticación del servicio de horarios no está disponible.',
            'api_authorization' => 'La API no autoriza consultar este horario para la estación seleccionada.',
            'schedule_history_unavailable' => 'La respuesta del historial de asignaciones no cumple el contrato esperado.',
            'api_unavailable' => 'El servicio de horarios no está disponible; no se puede confirmar la vigencia.',
            default => $kind === null ? '' : 'No fue posible consultar la información de horarios autorizada.',
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
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.module-station.init.js?v=20261027-1',
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.filters.init.js?v=20261026-1',
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
        $this->normalizeLegacyWorkplaceUrl();
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Checador', '/departamento-operativo/recursos-humanos/checador');
        Breadcrumb::add('Comparación informativa con Nómina', '');

        $query = Request::query();
        $checadorAccess = ChecadorAccessService::authorize(null);
        if (empty($checadorAccess['allowed'])) {
            http_response_code(403);
            View::renderPartial('errors/403');
            return;
        }
        $workplaceId = $this->sessionWorkplaceId($checadorAccess);
        $comparisonReader = new ChecadorPayrollComparisonReadService();
        $combinedScope = $comparisonReader->authorization(null);
        if (empty($combinedScope['allowed'])) {
            http_response_code(403);
            View::renderPartial('errors/403');
            return;
        }
        $authorization = $workplaceId === null ? $combinedScope : $comparisonReader->authorization($workplaceId);
        if (empty($authorization['allowed'])) {
            http_response_code(403);
            View::renderPartial('errors/403');
            return;
        }

        $filterNames = ['worker_id', 'year', 'period_type', 'period_number'];
        $hasComparisonFilters = false;
        foreach ($filterNames as $filterName) {
            $filterValue = $query[$filterName] ?? null;
            if (is_array($filterValue) || is_object($filterValue)
                || (is_string($filterValue) && trim($filterValue) !== '')
                || is_int($filterValue)) {
                $hasComparisonFilters = true;
                break;
            }
        }

        $result = null;
        $errorMessage = '';
        if ($hasComparisonFilters && $workplaceId !== null && !empty($query['worker_id'])) {
            $result = $comparisonReader->compare(array_merge($query, ['workplace_id' => $workplaceId]));
            if (empty($result['ok'])) {
                $errorMessage = match ((string) ($result['error']['kind'] ?? '')) {
                    'filters_required' => 'Completa trabajador, año y periodo para consultar.',
                    'invalid_period' => 'El periodo no corresponde al tipo configurado para la localidad o está fuera de rango.',
                    'worker_not_available' => 'El trabajador no está disponible en la localidad autorizada.',
                    'historical_period_access_unavailable' => 'No se pudo verificar la adscripción versionada del trabajador para todo el periodo consultado.',
                    'forbidden' => 'No se pudo autorizar la consulta simultáneamente para Checador y Nómina.',
                    'development_database_unavailable' => 'La lectura está deshabilitada porque el destino no pudo verificarse como bd_admongas local de desarrollo.',
                    default => 'No fue posible validar la consulta solicitada.',
                };
            }
        } elseif ($hasComparisonFilters && $workplaceId === null) {
            $errorMessage = 'Selecciona una estación autorizada antes de consultar.';
        }

        $workplaceMap = [];
        foreach ($authorization['workplaces'] ?? [] as $workplace) {
            $workplaceMap[(int) $workplace['id']] = (string) $workplace['nombre'];
        }
        $selectedWorkplaceName = $workplaceId !== null ? ($workplaceMap[$workplaceId] ?? '') : '';
        $periodType = $workplaceId !== null && RecibosNominaService::esSemanal($workplaceId) ? 'week' : 'fortnight';

        $selectedYear = isset($query['year']) && is_string($query['year']) && preg_match('/^\d{4}$/', trim($query['year']))
            ? (int) trim($query['year'])
            : (int) date('Y');
        if ($selectedYear < 2024 || $selectedYear > (int) date('Y')) {
            $selectedYear = (int) date('Y');
        }

        $periodsList = [];
        $defaultPeriodNumber = null;
        if ($workplaceId !== null) {
            $periodsData = RecibosNominaService::getPeriodosAnio($workplaceId, $selectedYear);
            $periodsList = $periodsData['periodos'] ?? [];
            $defaultPeriodNumber = (int) ($periodsData['periodo_actual'] ?? 1);
        }

        $selectedPeriodNumber = isset($query['period_number']) && (is_string($query['period_number']) || is_int($query['period_number'])) && (int) $query['period_number'] > 0
            ? (int) $query['period_number']
            : ($defaultPeriodNumber ?? 1);

        $selectedWorkerId = isset($query['worker_id']) && (is_string($query['worker_id']) || is_int($query['worker_id'])) && (int) $query['worker_id'] > 0
            ? (int) $query['worker_id']
            : null;

        $selectedStatus = isset($query['status']) && is_string($query['status'])
            ? trim($query['status'])
            : '';

        $summaryResult = null;
        $workerSelectOptions = [];
        if ($workplaceId !== null) {
            $summaryResult = $comparisonReader->comparePeriodSummary([
                'workplace_id' => $workplaceId,
                'year' => $selectedYear,
                'period_number' => $selectedPeriodNumber,
                'period_type' => $periodType,
                'worker_id' => $selectedWorkerId,
                'status' => $selectedStatus,
            ]);

            if (empty($summaryResult['ok']) && $errorMessage === '') {
                $errorMessage = match ((string) ($summaryResult['error']['kind'] ?? '')) {
                    'invalid_period' => 'El periodo no corresponde al tipo configurado para la localidad o está fuera de rango.',
                    'forbidden' => 'No se pudo autorizar la consulta simultáneamente para Checador y Nómina.',
                    'development_database_unavailable' => 'La lectura está deshabilitada porque el destino no pudo verificarse como bd_admongas local de desarrollo.',
                    default => 'No fue posible consultar el periodo solicitado.',
                };
            }

            // Build worker select options for Select2 dropdown
            $resolvedPeriod = $summaryResult['data']['period'] ?? null;
            $endDate = (string) ($resolvedPeriod['end_date'] ?? date('Y-m-d'));
            $rosterResult = (new \App\Services\ChecadorAttendanceRosterService())
                ->workersForAttendanceFilter($workplaceId, $endDate);
            $workerSelectOptions = $rosterResult['workers'] ?? [];

            $existingIds = array_map(static fn(array $w): int => (int) ($w['worker_id'] ?? 0), $workerSelectOptions);
            if (!empty($summaryResult['ok']) && is_array($summaryResult['data']['rows'] ?? null)) {
                foreach ($summaryResult['data']['rows'] as $rRow) {
                    $rwId = (int) ($rRow['worker_id'] ?? 0);
                    if ($rwId > 0 && !in_array($rwId, $existingIds, true)) {
                        $workerSelectOptions[] = [
                            'worker_id' => $rwId,
                            'full_name' => $rRow['worker_name'],
                            'employee_number' => $rRow['employee_number'] ?? null,
                        ];
                        $existingIds[] = $rwId;
                    }
                }
            }
            usort($workerSelectOptions, static fn(array $a, array $b): int =>
                strcmp((string) ($a['full_name'] ?? ''), (string) ($b['full_name'] ?? ''))
            );
        }

        View::render(
            'departamento-operativo/2-recursos-humanos/checador/payroll-comparison',
            [
                'title' => 'Comparación informativa de asistencia y Nómina',
                'moduleStationKey' => ChecadorAccessService::MODULE_KEY,
                'workplaces' => $authorization['workplaces'] ?? [],
                'selectedWorkplaceId' => $workplaceId,
                'selectedWorkplaceName' => $selectedWorkplaceName,
                'periodType' => $periodType,
                'periodsList' => $periodsList,
                'workerSelectOptions' => $workerSelectOptions,
                'filters' => [
                    'worker_id' => $selectedWorkerId !== null ? (string) $selectedWorkerId : '',
                    'year' => (string) $selectedYear,
                    'period_number' => (string) $selectedPeriodNumber,
                    'status' => $selectedStatus,
                ],
                'comparison' => is_array($result['data'] ?? null) ? $result['data'] : null,
                'comparisonSummary' => $summaryResult['data'] ?? null,
                'comparisonRows' => $summaryResult['data']['rows'] ?? [],
                'summaryMetrics' => $summaryResult['data']['summary'] ?? [],
                'resolvedPeriod' => $summaryResult['data']['period'] ?? null,
                'errorMessage' => $errorMessage,
                'demoMode' => ChecadorDailyReadService::demoModeEnabled(),
                'links' => [
                    '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                    '/assets/libs/select2/dist/css/select2.min.css',
                    '/assets/css/select2-modal.css',
                    '/assets/css/table-actions.css',
                    '/assets/css/checador-incidences.css?v=20261106-1',
                ],
                'scripts' => [
                    '/assets/js/vendor.min.js',
                    '/assets/libs/select2/dist/js/select2.full.min.js',
                    '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                    '/assets/libs/datatables.net-bs5/js/dataTables.bootstrap5.min.js',
                    '/assets/js/core/module-station-selector.js',
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.module-station.init.js?v=20261027-1',
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.payroll-comparison.init.js?v=20261109-1',
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
        // POST responses are redirects today, but keep the redirect itself
        // non-cacheable so the PIN cannot be replayed from browser/proxy cache.
        self::applyTemporaryAccessNoStoreHeaders();
        $input = Request::all();
        $workplaceId = $this->requestWorkplaceId($input);
        $date = is_string($input['date'] ?? null) ? trim($input['date']) : '';
        $workerId = self::positiveInteger($input['worker_id'] ?? null);
        $requestUuid = strtolower(trim($requestUuid));
        $redirect = '/departamento-operativo/recursos-humanos/checador/acceso-temporal?' . http_build_query(array_filter([
            'date' => self::isCivilDate($date) ? $date : null,
            'worker_id' => $workerId,
            'request_uuid' => self::safeTemporaryAccessRequestUuid($requestUuid),
        ], static fn(mixed $value): bool => $value !== null && $value !== ''));
        $user = Auth::user();
        $feedback = static function (string $type, string $message, array $extra = []) use ($user, $workplaceId, $action, $requestUuid): void {
            Session::set('checador_temporary_access_feedback', array_merge([
                'user_id' => (int) ($user->id ?? 0),
                'workplace_id' => $workplaceId,
                'request_uuid' => self::safeTemporaryAccessRequestUuid($requestUuid),
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

        $requiredPermission = match ($action) {
            'authorize' => 'crear',
            'reject' => 'editar',
            'cancel' => 'eliminar',
            default => '',
        };
        $authorization = $requiredPermission !== ''
            ? ChecadorAccessService::authorizeTemporaryAccess($workplaceId, $requiredPermission)
            : ['allowed' => false];
        if (empty($authorization['allowed']) || (int) ($authorization['permissions']['leer'] ?? 0) !== 1) {
            $feedback('error', 'No tienes permiso para realizar esta acción en el centro de trabajo seleccionado.');
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
        if (!ChecadorDailyReadService::temporaryAccessPostOperationsEnabled()) {
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

    /** Create an incidence through the canonical API writer or isolated local QA storage after a fresh ACL/scope check. */
    public function createIncidence(): void
    {
        $input = Request::all();
        $workplaceId = $this->requestWorkplaceId($input);
        $workerId = self::positiveInteger($input['worker_id'] ?? null);
        $returnDate = is_string($input['return_date'] ?? null) ? trim($input['return_date']) : '';
        $snapshotRef = self::safeSnapshotRef($input['snapshot_ref'] ?? null);
        $category = is_string($input['category'] ?? null) ? trim($input['category']) : '';
        $startDate = is_string($input['start_date'] ?? null) ? trim($input['start_date']) : '';
        $endDate = is_string($input['end_date'] ?? null) ? trim($input['end_date']) : '';
        $checkDate = is_string($input['check_date'] ?? null) ? trim($input['check_date']) : '';
        $checkKind = is_string($input['check_kind'] ?? null) ? trim($input['check_kind']) : '';
        $nature = is_string($input['nature'] ?? null) ? trim($input['nature']) : '';
        $sourceEventDeviceId = self::positiveInteger($input['source_event_device_id'] ?? null);
        $sourceEventUuid = is_string($input['source_event_uuid'] ?? null) ? trim($input['source_event_uuid']) : '';
        if ($category === 'PUNCH_ATTENDANCE' && self::isCivilDate($checkDate)) {
            $startDate = $checkDate;
            $endDate = $checkDate;
        }
        $reason = is_string($input['reason'] ?? null) ? trim($input['reason']) : '';
        $evidenceReferenceValid = !array_key_exists('evidence_reference', $input) || is_string($input['evidence_reference']);
        $evidenceReference = is_string($input['evidence_reference'] ?? null)
            ? trim($input['evidence_reference'])
            : '';
        $idempotencyKey = is_string($input['idempotency_key'] ?? null) ? trim($input['idempotency_key']) : '';
        $confirmCreate = (string) ($input['confirm_create'] ?? '') === '1';
        $realApiPostOperationsEnabled = ChecadorDailyReadService::realApiPostOperationsEnabled();
        $p3gBApiPostOperationsEnabled = ChecadorDailyReadService::p3gBPostOperationsEnabled();
        $apiIncidencePostOperationsEnabled = $realApiPostOperationsEnabled || $p3gBApiPostOperationsEnabled;
        $qaFixtureService = new ChecadorPersistentQaFixtureService();
        $qaIncidenceStore = new \App\Services\ChecadorPersistentQaIncidenceStore();
        $localQaWriteEnvironmentAvailable = !$apiIncidencePostOperationsEnabled
            && $qaFixtureService->localIncidenceWriteEnvironmentEnabled()
            && $qaIncidenceStore->localQaWritesExplicitlyEnabled()
            && $qaIncidenceStore->usesDefaultIsolatedDestination()
            && $qaIncidenceStore->canWriteDefaultIsolatedDestination();
        $localQaIncidenceWrite = false;
        $storageMode = $localQaWriteEnvironmentAvailable
            ? 'local_qa_file'
            : ($apiIncidencePostOperationsEnabled ? 'api_v2' : 'disabled');
        $redirect = '/departamento-operativo/recursos-humanos/checador/incidencias?' . http_build_query(array_filter([
            'date' => $returnDate,
            'worker_id' => $workerId,
            'snapshot_ref' => $snapshotRef,
        ], static fn(mixed $value): bool => $value !== null && $value !== ''));
        $formValues = [
            'category' => $category,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'check_date' => $checkDate,
            'check_kind' => $checkKind,
            'nature' => $nature,
            'source_event_device_id' => $sourceEventDeviceId,
            'source_event_uuid' => $sourceEventUuid,
            'reason' => $reason,
            'evidence_reference' => $evidenceReference,
            'confirm_create' => $confirmCreate,
        ];
        $feedback = static function (string $type, string $message, array $extra = []) use (
            $workerId,
            $workplaceId,
            $returnDate,
            $formValues
        ): void {
            Session::set('checador_incidence_create_feedback', array_merge([
                'type' => $type,
                'message' => $message,
                'worker_id' => $workerId,
                'workplace_id' => $workplaceId,
                'return_date' => $returnDate,
                'form_values' => $formValues,
            ], $extra));
        };

        $diagnostic = static function (
            string $level,
            string $stage,
            string $errorCode,
            string $validationResult,
            string $authorizationResult,
            string $persistenceResult,
            string $readbackResult
        ) use ($storageMode): void {
            $context = [
                'stage' => $stage,
                'error_code' => $errorCode,
                'storage_mode' => $storageMode,
                'validation_result' => $validationResult,
                'authorization_result' => $authorizationResult,
                'persistence_result' => $persistenceResult,
                'readback_result' => $readbackResult,
            ];
            if ($level === 'info') {
                Logger::info('Checador incidence create outcome', $context);
                return;
            }
            Logger::warning('Checador incidence create rejected', $context);
        };

        $validationError = match (true) {
            $workplaceId === null => 'workplace_required',
            $workerId === null => 'worker_required',
            $category === 'PUNCH_ATTENDANCE' && !$p3gBApiPostOperationsEnabled => 'p3g_b_gate_disabled',
            $category === 'PUNCH_ATTENDANCE' && !self::isCivilDate($checkDate) => 'check_date_invalid',
            $category === 'PUNCH_ATTENDANCE' && $checkDate !== $returnDate => 'check_date_mismatch',
            $category === 'PUNCH_ATTENDANCE' && !in_array($checkKind, ['ENTRY', 'LUNCH_EXIT', 'LUNCH_RETURN', 'FINAL_EXIT'], true) => 'check_kind_invalid',
            $category === 'PUNCH_ATTENDANCE' && !in_array($nature, ['LATENESS', 'OMISSION'], true) => 'nature_invalid',
            $category === 'PUNCH_ATTENDANCE' && $nature === 'LATENESS' && ($sourceEventDeviceId === null || $sourceEventUuid === '' || strlen($sourceEventUuid) > 64) => 'source_event_required',
            $category === 'PUNCH_ATTENDANCE' && $nature === 'OMISSION' && (($input['source_event_device_id'] ?? '') !== '' || $sourceEventUuid !== '') => 'source_event_not_allowed',
            $category === 'PUNCH_ATTENDANCE' && !str_starts_with($reason, '[QA DEV]') => 'qa_reason_required',
            $category === 'PUNCH_ATTENDANCE' && !str_starts_with($evidenceReference, 'QA-M06-') => 'qa_evidence_required',
            !self::isCivilDate($returnDate) => 'return_date_invalid',
            !self::isCivilDate($startDate) => 'start_date_invalid',
            !self::isCivilDate($endDate) => 'end_date_invalid',
            $endDate < $startDate => 'date_range_invalid',
            $reason === '' => 'reason_required',
            !$evidenceReferenceValid => 'evidence_reference_invalid',
            !preg_match('/\A[A-Za-z0-9._:-]{1,64}\z/', $idempotencyKey) => 'idempotency_key_invalid',
            !$confirmCreate => 'confirmation_required',
            default => null,
        };
        if ($validationError !== null) {
            $diagnostic('warning', 'validation', $validationError, 'rejected', 'not_checked', 'not_attempted', 'not_attempted');
            $validationMessage = match ($validationError) {
                'workplace_required' => 'Selecciona una localidad autorizada antes de registrar la incidencia.',
                'worker_required' => 'Selecciona un colaborador del padrón autorizado.',
                'return_date_invalid' => 'La fecha laboral no es válida. Actualiza la consulta e inténtalo nuevamente.',
                'p3g_b_gate_disabled' => 'El registro QA M06 no está habilitado en este entorno de desarrollo.',
                'check_date_invalid' => 'Indica una fecha local válida para el chequeo de marcación.',
                'check_date_mismatch' => 'La fecha del chequeo debe coincidir con la fecha local consultada.',
                'check_kind_invalid' => 'Selecciona qué marcación se está documentando.',
                'nature_invalid' => 'Selecciona si se reporta un retardo o una omisión.',
                'source_event_required' => 'Para reportar un retardo se requiere la referencia de la marcación inmutable.',
                'source_event_not_allowed' => 'Una omisión declarada no debe incluir una marcación fuente.',
                'qa_reason_required' => 'La descripción QA debe comenzar con [QA DEV].',
                'qa_evidence_required' => 'La referencia de evidencia QA debe comenzar con QA-M06-.',
                'category_invalid' => 'Selecciona un tipo de incidencia disponible.',
                'start_date_invalid', 'end_date_invalid', 'date_range_invalid' => 'Revisa las fechas del periodo de la incidencia.',
                'reason_required' => 'Escribe el motivo de la incidencia.',
                'evidence_reference_invalid' => 'Revisa la referencia de evidencia o déjala vacía.',
                'idempotency_key_invalid' => 'Actualiza el formulario antes de volver a intentar el registro.',
                'confirmation_required' => 'Confirma el registro de la incidencia para continuar.',
                default => 'Revisa los datos de la incidencia e inténtalo nuevamente.',
            };
            $feedback('error', $validationMessage);
            self::redirect($redirect);
        }

        if (ChecadorDailyReadService::demoModeEnabled()) {
            $diagnostic('warning', 'mode_gate', 'demo_mode_write_blocked', 'passed', 'not_checked', 'not_attempted', 'not_attempted');
            $feedback('error', 'El modo demostración no permite crear incidencias ni enviar operaciones de escritura.');
            self::redirect($redirect);
        }

        $authorization = ChecadorAccessService::authorizeIncidenceCreate($workplaceId);
        if (!($authorization['allowed'] ?? false)) {
            $diagnostic('warning', 'authorization', (string) ($authorization['reason'] ?? 'authorization_denied'), 'passed', 'denied', 'not_attempted', 'not_attempted');
            $feedback('error', in_array(($authorization['reason'] ?? ''), ['incidence_readback_permission_denied', 'incidence_read_permission_denied'], true)
                ? 'Se requiere permiso de lectura de incidencias para confirmar el resultado después del alta.'
                : 'No tienes permiso específico para crear incidencias o el centro está fuera de tu alcance.');
            self::redirect($redirect);
        }

        $user = Auth::user();
        if (!$user) {
            $diagnostic('warning', 'session', 'authenticated_user_missing', 'passed', 'failed', 'not_attempted', 'not_attempted');
            $feedback('error', 'La sesión ya no es válida. Inicia sesión nuevamente.');
            self::redirect($redirect);
        }
        $activeActor = \App\Models\Usuario::query()
            ->where('id', (int) $user->id)
            ->where('estatus', 0)
            ->first();
        if (!$activeActor) {
            $diagnostic('warning', 'session', 'active_actor_missing', 'passed', 'failed', 'not_attempted', 'not_attempted');
            $feedback('error', 'La sesión ya no es válida. Inicia sesión nuevamente.');
            self::redirect($redirect);
        }

        $fixtureWorkerEligible = $qaFixtureService->isEligibleLocalIncidenceWorker(
            $workplaceId ?? 0,
            $returnDate,
            $workerId
        );
        if ($fixtureWorkerEligible && $realApiPostOperationsEnabled) {
            $diagnostic('warning', 'qa_write_gate', 'canonical_api_writes_enabled_for_qa_scope', 'passed', 'passed', 'not_attempted', 'not_attempted');
            $feedback('error', 'El escenario QA queda bloqueado mientras las escrituras canónicas de API V2 estén habilitadas.');
            self::redirect($redirect);
        }

        $localQaIncidenceWrite = $localQaWriteEnvironmentAvailable
            && (int) ($activeActor->id_puesto ?? 0) === 6
            && $fixtureWorkerEligible
            && $qaFixtureService->shouldServe('incidences', $workplaceId, $returnDate, ['worker_id' => $workerId]);
        $allowedCategories = $localQaIncidenceWrite
            ? ['VACATION', 'PERMISSION', 'INCAPACITY', 'JUSTIFICATION']
            : ($p3gBApiPostOperationsEnabled && !$realApiPostOperationsEnabled
                ? ['PUNCH_ATTENDANCE']
                : array_values(array_filter(
                    ChecadorDailyReadService::INCIDENCE_CATEGORIES,
                    static fn(string $value): bool => $value !== 'PUNCH_ATTENDANCE' || $p3gBApiPostOperationsEnabled
                )));
        if (!in_array($category, $allowedCategories, true)) {
            $diagnostic('warning', 'validation', 'category_invalid', 'rejected', 'passed', 'not_attempted', 'not_attempted');
            $feedback('error', 'Selecciona un tipo de incidencia disponible.');
            self::redirect($redirect);
        }

        $workerCatalog = (new \App\Services\ChecadorAttendanceRosterService())
            ->workersForAttendanceFilter($workplaceId, $returnDate, null, false);
        $authorizedWorkerIds = [];
        $workerProfile = null;
        foreach (($workerCatalog['workers'] ?? []) as $profile) {
            if (is_array($profile)) {
                $authorizedId = self::positiveInteger($profile['worker_id'] ?? null);
                if ($authorizedId !== null) {
                    $authorizedWorkerIds[] = $authorizedId;
                    if ($authorizedId === $workerId) $workerProfile = $profile;
                }
            }
        }
        $canonicalRosterAllows = ($workerCatalog['status'] ?? '') === 'ready'
            && $workerProfile !== null;
        $historicalQaRosterAllows = $localQaIncidenceWrite
            && $qaFixtureService->allowsHistoricalQaIncidenceRoster(
                $workerCatalog,
                $workplaceId ?? 0,
                $returnDate,
                $workerId
            );
        if ((!$canonicalRosterAllows && !$historicalQaRosterAllows)
            || !in_array($workerId, $authorizedWorkerIds, true)) {
            $diagnostic('warning', 'worker_scope', 'worker_not_in_authorized_roster', 'passed', 'passed', 'not_attempted', 'not_attempted');
            $feedback('error', 'El colaborador no pertenece al padrón autorizado para el centro y fecha seleccionados.');
            self::redirect($redirect);
        }

        if ($localQaIncidenceWrite) {
            $stored = $qaIncidenceStore->create([
                'worker_id' => $workerId,
                'workplace_id' => $workplaceId,
                'return_date' => $returnDate,
                'category' => $category,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'reason' => $reason,
                'evidence_reference' => $evidenceReference,
                'idempotency_key' => $idempotencyKey,
            ], (int) $user->id, [$workerId]);
            if (($stored['ok'] ?? false) !== true) {
                $kind = (string) ($stored['error'] ?? 'storage_unavailable');
                $storeValidationRejected = in_array($kind, [
                    'invalid_input', 'invalid_actor', 'unsupported_scope', 'worker_out_of_scope', 'category_invalid',
                    'worker_not_in_fixture',
                    'return_date_invalid', 'dates_invalid', 'period_must_include_work_date', 'reason_required',
                    'reason_too_long', 'evidence_too_long', 'idempotency_key_invalid',
                ], true);
                $diagnostic(
                    'warning',
                    $storeValidationRejected ? 'storage_validation' : 'persistence',
                    $kind,
                    $storeValidationRejected ? 'rejected' : 'passed',
                    'passed',
                    'failed',
                    'not_attempted'
                );
                $retry = $kind === 'storage_unavailable' ? ['retry_idempotency_key' => $idempotencyKey] : [];
                $message = match ($kind) {
                    'idempotency_conflict' => 'Esta solicitud ya se intentó con otros datos. Revisa el formulario antes de volver a enviarlo.',
                    'invalid_actor' => 'La sesión ya no es válida. Inicia sesión nuevamente.',
                    'worker_out_of_scope' => 'El colaborador seleccionado no pertenece al padrón autorizado.',
                    'worker_not_in_fixture' => 'El colaborador no pertenece al escenario QA autorizado para esta fecha y localidad.',
                    'category_invalid' => 'Selecciona un tipo de incidencia disponible.',
                    'return_date_invalid' => 'La fecha laboral no es válida. Actualiza la consulta e inténtalo nuevamente.',
                    'dates_invalid' => 'Revisa las fechas del periodo de la incidencia.',
                    'period_must_include_work_date' => 'El periodo de la incidencia debe incluir la fecha laboral seleccionada.',
                    'reason_required' => 'Escribe el motivo de la incidencia.',
                    'reason_too_long' => 'El motivo supera la longitud permitida. Acórtalo e inténtalo nuevamente.',
                    'evidence_too_long' => 'La referencia de evidencia supera la longitud permitida.',
                    'idempotency_key_invalid' => 'Actualiza el formulario antes de volver a intentar el registro.',
                    'disabled' => 'El registro local de pruebas no está disponible para esta consulta. Conservamos el formulario.',
                    'invalid_input', 'unsupported_scope' => 'Revisa la localidad, el colaborador y las fechas de la incidencia.',
                    default => 'No se pudo confirmar el registro en el almacenamiento local. Conservamos el formulario y la clave para reintentar sin duplicarlo.',
                };
                $feedback('error', $message, $retry);
                self::redirect($redirect);
            }
            $storedData = $stored['data'] ?? [];
            $readback = $qaIncidenceStore->readBack(
                (string) ($storedData['uuid_incidencia'] ?? ''),
                $workplaceId,
                $workerId
            );
            $firstRevision = is_array($readback['revisions'][0] ?? null) ? $readback['revisions'][0] : [];
            $expectedEvidenceReference = $evidenceReference === '' ? null : $evidenceReference;
            $readbackConfirmed = is_array($readback)
                && $readback !== []
                && (string) ($readback['uuid_incidencia'] ?? '') === (string) ($storedData['uuid_incidencia'] ?? '')
                && (int) ($readback['worker_id'] ?? 0) === $workerId
                && (int) ($readback['workplace_id'] ?? 0) === $workplaceId
                && (string) ($readback['category'] ?? '') === $category
                && (string) ($readback['date_range']['start_date'] ?? '') === $startDate
                && (string) ($readback['date_range']['end_date'] ?? '') === $endDate
                && (string) ($readback['summary_reason'] ?? '') === $reason
                && ($readback['summary_evidence_reference'] ?? null) === $expectedEvidenceReference
                && (string) ($readback['current_status'] ?? '') === 'PENDING_REVIEW'
                && (int) ($readback['current_revision'] ?? 0) === 1
                && (string) ($readback['source_classification'] ?? '') === 'LOCAL_QA_INCIDENCE'
                && (string) ($readback['source_fixture_version'] ?? '') === ChecadorPersistentQaFixtureService::LOCAL_INCIDENCE_WRITE_FIXTURE_VERSION
                && (int) ($firstRevision['revision_number'] ?? 0) === 1
                && (string) ($firstRevision['reason'] ?? '') === $reason
                && ($firstRevision['evidence_reference'] ?? null) === $expectedEvidenceReference
                && (int) ($firstRevision['created_by_user_id'] ?? 0) === (int) $user->id;
            if (!$readbackConfirmed) {
                $diagnostic('error', 'readback', 'stored_record_not_confirmed', 'passed', 'passed', 'store_acknowledged', 'failed');
                $feedback('error', 'No se pudo confirmar el registro guardado. Reintenta la misma solicitud para evitar duplicados.', [
                    'retry_idempotency_key' => $idempotencyKey,
                ]);
                self::redirect($redirect);
            }
            $diagnostic(
                'info',
                'complete',
                'created_and_read_back',
                'passed',
                'passed',
                ($stored['created'] ?? false) === true ? 'created' : 'idempotent_existing_record',
                'confirmed'
            );
            $feedback(
                'success',
                ($stored['created'] ?? false) === true
                    ? 'Incidencia registrada y disponible para consulta.'
                    : 'La incidencia ya estaba registrada y está disponible para consulta.'
            );
            self::redirect($redirect);
        }
        if (!$apiIncidencePostOperationsEnabled) {
            $feedback('error', 'La creación real de incidencias está deshabilitada hasta validar el ambiente, la autenticación y los permisos del servicio.');
            self::redirect($redirect);
        }

        try {
            $reader = new ChecadorDailyReadService();
            $created = $reader->createIncidence($user, [
                'worker_id' => $workerId,
                'workplace_id' => $workplaceId,
                'category' => $category,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'reason' => $reason,
                'evidence_reference' => $evidenceReference === '' ? null : $evidenceReference,
                'check_date' => $category === 'PUNCH_ATTENDANCE' ? $checkDate : null,
                'check_kind' => $category === 'PUNCH_ATTENDANCE' ? $checkKind : null,
                'nature' => $category === 'PUNCH_ATTENDANCE' ? $nature : null,
                'source_event_device_id' => $category === 'PUNCH_ATTENDANCE' ? $sourceEventDeviceId : null,
                'source_event_uuid' => $category === 'PUNCH_ATTENDANCE' ? $sourceEventUuid : null,
            ], $idempotencyKey);
        } catch (\Throwable) {
            $reader = null;
            $created = ['ok' => false, 'error' => ['kind' => 'api_unavailable', 'status' => 503]];
        }

        if (($created['ok'] ?? false) !== true) {
            $kind = (string) ($created['error']['kind'] ?? 'api_unavailable');
            $message = match ($kind) {
                'attendance_policy_not_versioned' => 'No existe una política V2 versionada para la fecha del chequeo; la referencia legacy no se usa como sustituto.',
                'incidence_deadline_expired' => 'El plazo local para registrar esta incidencia ya venció.',
                'worker_membership_unverified' => 'No se pudo verificar la adscripción del colaborador para esa fecha.',
                'check_date_future' => 'La fecha del chequeo no puede estar en el futuro.',
                'worker_not_found' => 'El identificador no corresponde a un trabajador canónico disponible.',
                'api_authentication' => 'La autenticación del servicio no está disponible.',
                'api_authorization' => 'La API no autoriza la creación o el centro de trabajo seleccionado.',
                'api_post_disabled' => 'La creación real de incidencias permanece deshabilitada hasta validar el ambiente y la integración del servicio.',
                'idempotency_conflict' => 'La clave de idempotencia ya se usó con otros datos. La operación quedó detenida.',
                'bad_request' => 'Los datos enviados no cumplen el contrato de creación de incidencias.',
                default => 'No se pudo confirmar el envío. No se muestra éxito; puedes reintentar la misma solicitud.',
            };
            $extra = in_array($kind, ['api_unavailable', 'administrative_unavailable'], true)
                ? ['retry_idempotency_key' => $idempotencyKey]
                : [];
            $feedback('error', $message, $extra);
            self::redirect($redirect);
        }

        $createdData = $created['data'];
        try {
            $readback = $reader->readIncidence($user, (string) $createdData['uuid_incidencia']);
        } catch (\Throwable) {
            $readback = ['ok' => false];
        }
        $readData = $readback['data'] ?? [];
        $firstRevision = is_array($readData['revisions'][0] ?? null) ? $readData['revisions'][0] : [];
        $readbackConfirmed = ($readback['ok'] ?? false) === true
            && (int) ($readData['worker_id'] ?? 0) === $workerId
            && (int) ($readData['workplace_id'] ?? 0) === $workplaceId
            && (string) ($readData['uuid_incidencia'] ?? '') === (string) $createdData['uuid_incidencia']
            && (string) ($readData['category'] ?? '') === $category
            && (string) ($readData['scope'] ?? '') === 'WORKER'
            && (string) ($readData['date_range']['start_date'] ?? '') === $startDate
            && (string) ($readData['date_range']['end_date'] ?? '') === $endDate
            && (int) ($readData['current_revision'] ?? 0) >= 1
            && (int) ($firstRevision['revision_number'] ?? 0) === 1
            && (string) ($firstRevision['status'] ?? '') === (string) $createdData['status'];
        if (!$readbackConfirmed) {
            $feedback('error', 'El alta se envió, pero la lectura canónica del historial no pudo confirmarla. Reintenta la misma solicitud para evitar duplicados.', [
                'retry_idempotency_key' => $idempotencyKey,
            ]);
            self::redirect($redirect);
        }

        $statusLabel = ChecadorPresentationFormatter::incidenceStatusLabel($readData['current_status'] ?? null);
        $feedback('success', 'Incidencia creada y confirmada mediante lectura canónica. Estado actual: ' . $statusLabel . '.');
        self::redirect($redirect);
    }

    public function administrativeEvaluation(): void
    {
        $input = Request::all();
        $workplaceId = $this->requestWorkplaceId($input);
        $workerId = self::positiveInteger($input['worker_id'] ?? null);
        $date = is_string($input['date'] ?? null) ? trim((string) $input['date']) : '';
        $snapshotRef = self::safeSnapshotRef($input['snapshot_ref'] ?? null);
        $expectedCalculationId = self::positiveInteger($input['expected_calculation_revision_id'] ?? null);
        $idempotencyKey = is_string($input['idempotency_key'] ?? null) ? trim($input['idempotency_key']) : '';
        $authorizeFalta = (string) ($input['authorize_falta'] ?? '') === '1';
        $action = $authorizeFalta ? 'authorize_falta' : 'evaluate';
        $redirect = '/departamento-operativo/recursos-humanos/checador/ajustes?' . http_build_query(array_filter([
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
        $workplaceId = $this->requestWorkplaceId($input);
        $workerId = self::positiveInteger($input['worker_id'] ?? null);
        $date = is_string($input['date'] ?? null) ? trim((string) $input['date']) : '';
        $snapshotRef = self::safeSnapshotRef($input['snapshot_ref'] ?? null);
        $expectedRevision = self::positiveInteger($input['expected_current_revision'] ?? null);
        $idempotencyKey = is_string($input['idempotency_key'] ?? null) ? trim((string) $input['idempotency_key']) : '';
        $redirect = '/departamento-operativo/recursos-humanos/checador/incidencias?' . http_build_query(array_filter([
            'date' => $date,
            'worker_id' => $workerId,
            'snapshot_ref' => $snapshotRef,
        ], static fn(mixed $value): bool => $value !== null && $value !== ''));

        $feedback = static function (string $type, string $message, array $extra = []) use ($uuidIncidencia, $action): void {
            Session::set('checador_lifecycle_feedback', array_merge([
                'type' => $type,
                'message' => $message,
                'uuid' => $uuidIncidencia,
                'action' => $action,
            ], $extra));
        };

        if (!in_array($action, ['resolve', 'authorize', 'reject', 'revoke'], true)
            || $workplaceId === null || $expectedRevision === null
            || !preg_match('/\A[A-Za-z0-9._:-]{1,64}\z/', $idempotencyKey)
            || $date === ''
            || (string) ($input['confirm_action'] ?? '') !== '1') {
            $feedback('error', 'La acción administrativa no es válida o requiere confirmación explícita.');
            self::redirect($redirect);
        }

        $authorization = ChecadorAccessService::authorizeLifecycle($workplaceId, $action);
        if (!($authorization['allowed'] ?? false)) {
            $feedback('error', 'No tienes permiso administrativo o alcance de centro de trabajo para esta acción.');
            self::redirect($redirect);
        }

        $fields = match ($action) {
            'resolve' => [
                'resolution_action' => $input['resolution_action'] ?? 'CONFIRM_INCIDENCE_PREVALENCE',
                'resolution_notes' => $input['resolution_notes'] ?? '',
                'evidence_reference' => $input['evidence_reference'] ?? '',
            ],
            'authorize' => ['authorization_notes' => $input['action_notes'] ?? ''],
            'reject' => ['rejection_reason' => $input['rejection_reason'] ?? ''],
            'revoke' => [
                'revocation_reason' => $input['revocation_reason'] ?? '',
                'evidence_reference' => $input['evidence_reference'] ?? '',
            ],
        };
        $user = Auth::user();
        if (!$user) {
            $feedback('error', 'La sesión ya no es válida. Inicia sesión nuevamente.');
            self::redirect($redirect);
        }
        if (!ChecadorDailyReadService::demoModeEnabled()
            && !ChecadorDailyReadService::realApiPostOperationsEnabled()) {
            $feedback('error', 'Las acciones reales de incidencias están deshabilitadas hasta validar el ambiente, la autenticación y los permisos del servicio.');
            self::redirect($redirect);
        }

        try {
            $reader = new ChecadorDailyReadService();
            $result = $reader->lifecycleAction(
                $user,
                $uuidIncidencia,
                $action,
                $expectedRevision,
                $idempotencyKey,
                $fields,
                $workplaceId
            );
        } catch (\Throwable) {
            $reader = null;
            $result = ['ok' => false, 'error' => ['kind' => 'api_unavailable', 'status' => 503]];
        }

        if (($result['ok'] ?? false) === true) {
            $resultData = is_array($result['data'] ?? null) ? $result['data'] : [];
            $expectedStatus = (string) (($resultData['resolution_status'] ?? null)
                ?? ($resultData['status'] ?? ''));
            $revisionField = match ($action) {
                'resolve' => 'resolution_revision_number',
                'authorize' => 'authorized_revision_number',
                'reject' => 'rejected_revision_number',
                'revoke' => 'revocation_revision_number',
            };
            $expectedRevisionNumber = self::positiveInteger(
                $resultData['current_revision'] ?? $resultData[$revisionField] ?? null
            );
            $readback = $reader instanceof ChecadorDailyReadService
                ? $reader->readIncidence($user, $uuidIncidencia)
                : ['ok' => false];
            $revisionConfirmed = false;
            foreach (($readback['data']['revisions'] ?? []) as $revision) {
                if (is_array($revision)
                    && (int) ($revision['revision_number'] ?? 0) === $expectedRevisionNumber
                    && (string) ($revision['status'] ?? '') === $expectedStatus) {
                    $revisionConfirmed = true;
                    break;
                }
            }
            if (!($readback['ok'] ?? false) || !$revisionConfirmed) {
                $message = $reader instanceof ChecadorDailyReadService && $reader->isDemoMode()
                    ? 'La acción sintética se registró, pero la relectura del historial no pudo confirmarla. Actualiza la pantalla antes de continuar.'
                    : 'La acción se envió, pero la relectura del historial no pudo confirmarla. Actualiza la pantalla antes de continuar.';
                $feedback('error', $message, ['retry_idempotency_key' => $idempotencyKey]);
                self::redirect($redirect);
            }
            $currentStatus = $readback['data']['current_status'] ?? $expectedStatus;
            $currentStatusLabel = ChecadorPresentationFormatter::incidenceStatusLabel($currentStatus);
            $message = $reader instanceof ChecadorDailyReadService && $reader->isDemoMode()
                ? 'Acción confirmada en el historial sintético; no modifica información operativa. Estado actual: ' . $currentStatusLabel . '.'
                : 'Acción confirmada por relectura del historial. Estado actual: ' . $currentStatusLabel . '.';
            $feedback('success', $message);
            self::redirect($redirect);
        }

        $kind = (string) ($result['error']['kind'] ?? 'api_unavailable');
        $message = match ($kind) {
            'stale_revision_conflict' => 'La incidencia cambió mientras la revisabas. Se actualizará el historial; confirma una nueva acción.',
            'idempotency_conflict' => 'La clave de idempotencia tiene un payload distinto. La acción quedó detenida para revisión.',
            'invalid_lifecycle_transition' => 'El estado actual ya no permite esta acción. Se actualizará el historial.',
            'incidence_not_found' => 'La incidencia ya no está disponible dentro del alcance autorizado.',
            'api_authentication' => 'La autenticación del servicio no está disponible.',
            'api_authorization' => 'La API no autoriza esta acción o el centro de trabajo seleccionado.',
            'api_post_disabled' => 'Las acciones reales de incidencias permanecen deshabilitadas hasta validar el ambiente y la integración del servicio.',
            'bad_request' => 'La información enviada no cumple el contrato de la acción.',
            default => 'La acción no pudo confirmarse. No se muestra éxito; puedes reintentar explícitamente.',
        };
        $extra = $kind === 'api_unavailable'
            ? ['retry_idempotency_key' => $idempotencyKey]
            : [];
        if ($reader instanceof ChecadorDailyReadService && $reader->isDemoMode()) {
            $message .= ' El estado sintético permanece sin cambios.';
        }
        $feedback('error', $message, $extra);
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

    /** Stream the same authorized, searched, and ordered SQL set used by DataTables. */
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

    private static function isUtcTimestamp(string $value): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new \DateTimeZone('UTC'));
        return $parsed instanceof \DateTimeImmutable
            && $parsed->format('Y-m-d H:i:s') === $value
            && !array_filter(\DateTimeImmutable::getLastErrors() ?: []);
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

    private function nowUtc(): \DateTimeImmutable
    {
        $now = $this->utcClock === null
            ? new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
            : ($this->utcClock)();
        if (!$now instanceof \DateTimeImmutable) {
            throw new \UnexpectedValueException('Checador clock must return DateTimeImmutable.');
        }
        return $now->setTimezone(new \DateTimeZone('UTC'));
    }

    private static function positiveInteger(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) return $value;
        if (!is_string($value) || !ctype_digit($value) || (int) $value < 1) return null;
        return (int) $value;
    }

    private static function strictNonNegativeInteger(mixed $value): ?int
    {
        if (is_int($value)) return $value >= 0 ? $value : null;
        if (!is_string($value) || preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $value) !== 1) return null;
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        return $parsed === false || $parsed < 0 ? null : $parsed;
    }

    /**
     * Read incidence records directly through the existing read contract. This
     * workspace intentionally does not require a punch snapshot or run any
     * journey/evaluation operation.
     *
     * @return array<string,mixed>
     */
    private static function readIncidenceWorkspace(
        ChecadorDailyReadService $reader,
        \App\Models\Usuario $user,
        int $workplaceId,
        string $date,
        ?int $selectedWorkerId,
        bool $selectedWorkerInvalid
    ): array {
        $empty = [
            'state' => 'error',
            'state_message' => 'La consulta no está disponible en este momento.',
            'timezone' => null,
            'attendance_roster' => ['status' => 'not_available', 'workers' => []],
            'worker_options' => [],
            'worker_display_names' => [],
            'worker_options_status' => 'unavailable',
            'worker_options_message' => 'No se pudo cargar el padrón autorizado.',
            'administrative_incidences' => [],
            'administrative_errors' => [],
        ];
        if (!self::isCivilDate($date)) return $empty;

        $timezoneResult = $reader->resolveWorkplaceLocalDay($user, $workplaceId, $date);
        if (($timezoneResult['ok'] ?? false) !== true) {
            $empty['state_message'] = 'No se pudo determinar la zona horaria del centro seleccionado.';
            // Keep the independently sourced local roster available in the
            // worker filters, while leaving the incidence read in an error
            // state until API-owned timezone and record reads succeed.
            $localRoster = (new \App\Services\ChecadorAttendanceRosterService())
                ->workersForAttendanceFilter($workplaceId, $date, null, false);
            if (in_array((string) ($localRoster['status'] ?? ''), ['ready', 'partial', 'current_roster_only'], true)) {
                $empty['attendance_roster'] = $localRoster;
                $empty['worker_options_status'] = (string) $localRoster['status'];
                $empty['worker_options_message'] = (string) ($localRoster['message'] ?? '');
                foreach ($localRoster['workers'] ?? [] as $profile) {
                    if (!is_array($profile)) continue;
                    $workerId = self::positiveInteger($profile['worker_id'] ?? null);
                    $name = trim((string) ($profile['full_name'] ?? ''));
                    if ($workerId === null || $name === '') continue;
                    $empty['worker_options'][] = [
                        'worker_id' => $workerId,
                        'full_name' => $name,
                        'employee_number' => $profile['employee_number'] ?? null,
                    ];
                    $empty['worker_display_names'][$workerId] = $name;
                }
            }
            return $empty;
        }
        $timezone = $timezoneResult['data'] ?? [];
        $timezoneName = (string) ($timezone['timezone'] ?? '');
        if (!ChecadorOperationalPeriod::isIanaTimezone($timezoneName)) {
            $empty['state_message'] = 'No se pudo determinar la zona horaria del centro seleccionado.';
            return $empty;
        }
        $roster = (new \App\Services\ChecadorAttendanceRosterService())
            ->workersForAttendanceFilter($workplaceId, $date, $timezoneName, false);
        if (!in_array((string) ($roster['status'] ?? ''), ['ready', 'partial', 'current_roster_only'], true)
            || !is_array($roster['workers'] ?? null)) {
            $empty['state_message'] = 'No se pudo cargar el padrón autorizado del centro seleccionado.';
            return $empty;
        }
        $profilesById = [];
        $workerOptions = [];
        $workerDisplayNames = [];
        foreach ($roster['workers'] as $profile) {
            if (!is_array($profile)) continue;
            $workerId = self::positiveInteger($profile['worker_id'] ?? null);
            if ($workerId === null) continue;
            $name = trim((string) ($profile['full_name'] ?? ''));
            if ($name === '') continue;
            $profilesById[$workerId] = $profile;
            $workerOptions[] = [
                'worker_id' => $workerId,
                'full_name' => $name,
                'employee_number' => $profile['employee_number'] ?? null,
            ];
            $workerDisplayNames[$workerId] = $name;
        }
        if (($roster['status'] ?? '') !== 'ready') {
            // Current workers may remain useful as filter choices, but a
            // partial/current-only roster cannot authorize historical reads.
            $empty['attendance_roster'] = $roster;
            $empty['worker_options'] = $workerOptions;
            $empty['worker_display_names'] = $workerDisplayNames;
            $empty['worker_options_status'] = (string) $roster['status'];
            $empty['worker_options_message'] = (string) ($roster['message'] ?? '');
            $empty['state_message'] = (string) ($roster['message'] ?? 'No se pudo confirmar el padrón para la fecha seleccionada.');
            return $empty;
        }
        if ($selectedWorkerInvalid) {
            $empty['state_message'] = 'Selecciona un colaborador válido del padrón autorizado.';
            $empty['attendance_roster'] = $roster;
            $empty['worker_options'] = $workerOptions;
            $empty['worker_display_names'] = $workerDisplayNames;
            $empty['worker_options_status'] = 'ready';
            $empty['worker_options_message'] = '';
            return $empty;
        }
        if ($selectedWorkerId !== null && !isset($profilesById[$selectedWorkerId])) {
            $empty['state_message'] = 'El colaborador seleccionado no pertenece al padrón autorizado para este centro y fecha.';
            $empty['attendance_roster'] = $roster;
            $empty['worker_options'] = $workerOptions;
            $empty['worker_display_names'] = $workerDisplayNames;
            $empty['worker_options_status'] = 'ready';
            $empty['worker_options_message'] = '';
            return $empty;
        }

        $administrativeIncidences = [];
        $administrativeErrors = [];
        $workerIds = $selectedWorkerId === null ? array_keys($profilesById) : [$selectedWorkerId];
        $successfulWorkerReads = 0;
        $sharedFailureLogged = false;
        foreach ($workerIds as $workerIndex => $workerId) {
            $read = $reader->readApplicableIncidences($user, (int) $workerId, $workplaceId, $date, $date);
            if (($read['ok'] ?? false) !== true) {
                $failureKind = (string) ($read['error']['kind'] ?? 'administrative_unavailable');
                $administrativeErrors[(int) $workerId] = $failureKind;
                if (self::isSharedIncidenceReadFailure($failureKind)) {
                    self::logSharedIncidenceReadFailure(
                        $failureKind,
                        self::positiveInteger($read['error']['status'] ?? null),
                        max(0, count($workerIds) - $workerIndex - 1)
                    );
                    $sharedFailureLogged = true;
                    break;
                }
                continue;
            }
            $details = [];
            $historyFailed = false;
            $historyFailureKind = 'administrative_unavailable';
            foreach (($read['data']['incidences'] ?? []) as $summary) {
                if (!is_array($summary)) {
                    $historyFailed = true;
                    break;
                }
                $detail = $reader->readIncidence($user, (string) ($summary['uuid_incidencia'] ?? ''));
                $detailData = $detail['data'] ?? [];
                if (($detail['ok'] ?? false) !== true
                    || !is_array($detailData)
                    || (int) ($detailData['worker_id'] ?? 0) !== (int) $workerId
                    || (int) ($detailData['workplace_id'] ?? 0) !== $workplaceId
                    || (string) ($detailData['uuid_incidencia'] ?? '') !== (string) ($summary['uuid_incidencia'] ?? '')) {
                    $historyFailed = true;
                    $historyFailureKind = (string) ($detail['error']['kind'] ?? 'administrative_unavailable');
                    break;
                }
                $details[] = array_merge($detailData, [
                    'source_classification' => $summary['source_classification'] ?? 'API_V2',
                    'summary_reason' => $summary['reason'] ?? null,
                    'summary_evidence_reference' => $summary['evidence_reference'] ?? null,
                    'summary_created_at_utc' => $summary['created_at_utc'] ?? null,
                ]);
            }
            if ($historyFailed) {
                $administrativeErrors[(int) $workerId] = $historyFailureKind;
                if (self::isSharedIncidenceReadFailure($historyFailureKind)) {
                    self::logSharedIncidenceReadFailure(
                        $historyFailureKind,
                        self::positiveInteger($detail['error']['status'] ?? null),
                        max(0, count($workerIds) - $workerIndex - 1)
                    );
                    $sharedFailureLogged = true;
                    break;
                }
                continue;
            }

            $successfulWorkerReads++;
            if ($details !== [] || $selectedWorkerId !== null) {
                $administrativeIncidences[(int) $workerId] = $details;
            }
        }
        $hasErrors = $administrativeErrors !== [];
        if ($hasErrors && !$sharedFailureLogged) {
            self::logIncidenceReadErrorSummary($administrativeErrors, count($workerIds));
        }
        $state = !$hasErrors ? 'success' : ($successfulWorkerReads > 0 ? 'partial' : 'error');
        return [
            'state' => $state,
            'state_message' => match ($state) {
                'partial' => 'Algunos registros no pudieron consultarse.',
                'error' => 'No fue posible completar la consulta de incidencias.',
                default => '',
            },
            'timezone' => $timezone,
            'attendance_roster' => $roster,
            'worker_options' => $workerOptions,
            'worker_display_names' => $workerDisplayNames,
            'worker_options_status' => 'ready',
            'worker_options_message' => '',
            'administrative_incidences' => $administrativeIncidences,
            'administrative_errors' => $administrativeErrors,
        ];
    }

    private static function isSharedIncidenceReadFailure(string $kind): bool
    {
        return in_array($kind, ['api_authentication', 'api_authorization', 'api_unavailable'], true);
    }

    private static function logSharedIncidenceReadFailure(string $kind, ?int $httpStatus, int $remainingWorkers): void
    {
        try {
            Logger::warning('Checador incidence read stopped after shared API failure', [
                'operation' => 'read_incidence_workspace',
                'error_kind' => $kind,
                'http_status' => $httpStatus,
                'remaining_worker_count' => $remainingWorkers,
            ]);
        } catch (\Throwable) {
            // Diagnostics must not affect the read response.
        }
    }

    /** @param array<int,string> $errors */
    private static function logIncidenceReadErrorSummary(array $errors, int $workerCount): void
    {
        try {
            Logger::warning('Checador incidence read completed with worker-level errors', [
                'operation' => 'read_incidence_workspace',
                'worker_count' => $workerCount,
                'error_count' => count($errors),
                'error_kinds' => array_count_values(array_values($errors)),
            ]);
        } catch (\Throwable) {
            // Diagnostics must not affect the read response.
        }
    }

    /** Resolve Checador solely from its institutional session context. */
    private function sessionWorkplaceId(array $access): ?int
    {
        $context = ModuleStationService::getContext(ChecadorAccessService::MODULE_KEY);
        $workplaceId = self::positiveInteger($context['id_depto'] ?? $context['id_estacion'] ?? null);
        $authorizedIds = array_map('intval', array_column($access['workplaces'] ?? [], 'id'));

        if ($workplaceId !== null && !in_array($workplaceId, $authorizedIds, true)) {
            ModuleStationService::setContext(ChecadorAccessService::MODULE_KEY, null, null);
            return null;
        }

        // Preserve the established first-workplace behavior for other roles.
        // Dirección de Operaciones must make an explicit selector choice.
        if ($workplaceId === null
            && (int) ($access['position_id'] ?? 0) !== 13
            && $authorizedIds !== []) {
            $workplaceId = $authorizedIds[0];
            if (!ModuleStationService::setChecadorWorkplaceContext($workplaceId)) {
                return null;
            }
        }

        if ($workplaceId !== null
            && empty(ChecadorAccessService::authorize($workplaceId)['allowed'])) {
            ModuleStationService::setContext(ChecadorAccessService::MODULE_KEY, null, null);
            return null;
        }
        return $workplaceId;
    }

    /** Resolve an action's locality from session and reject a stale form value. */
    private function requestWorkplaceId(array $input): ?int
    {
        $access = ChecadorAccessService::authorize(null);
        if (empty($access['allowed'])) {
            return null;
        }
        $sessionId = $this->sessionWorkplaceId($access);
        if ($sessionId === null) {
            return null;
        }
        if (array_key_exists('workplace_id', $input)
            && self::positiveInteger($input['workplace_id']) !== $sessionId) {
            return null;
        }
        return $sessionId;
    }

    /** Remove legacy locality query state without applying it to session context. */
    private function normalizeLegacyWorkplaceUrl(): void
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

        $legacyId = self::positiveInteger($params['workplace_id']);
        $context = ModuleStationService::getContext(ChecadorAccessService::MODULE_KEY);
        $sessionId = self::positiveInteger($context['id_depto'] ?? $context['id_estacion'] ?? null);
        $sameLocality = $legacyId !== null && $legacyId === $sessionId;
        unset($params['workplace_id']);

        if (!$sameLocality) {
            $safeFilters = [
                'date', 'from_date', 'to_date', 'period', 'year', 'month',
                'week_start', 'schedule_week', 'schedule_history',
            ];
            $params = array_intersect_key($params, array_flip($safeFilters));
        }

        $location = $path . ($params === [] ? '' : '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
        self::redirect($location);
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

    /** Keep the pre-existing Adjustments branch isolated from the P0 GET scopes. */
    private static function workspaceRetainsJourneyEvaluation(?string $workspace): bool
    {
        return $workspace === 'adjustments';
    }

    /** Read the existing raw PunchEvent GET boundary for the daily screen. */
    private static function readDailyRawAttendance(
        ChecadorDailyReadService $reader,
        \App\Models\Usuario $user,
        int $workplaceId,
        string $date,
        ?int $requestedWorkerId,
        bool $requestedWorkerInvalid,
        array $authorizedWorkplaceIds,
        array $workerCatalog,
        ?array $timezoneResolutionForDate
    ): array {
        $empty = [
            'state' => 'error',
            'state_message' => 'Las marcaciones observadas no están disponibles en este momento.',
            'timezone' => null,
            'events' => [],
            'attendance_events' => [],
            'attendance_coverage' => ['status' => 'not_available', 'complete' => false, 'message' => 'No se pudo confirmar la cobertura de la lectura.'],
            'attendance_roster' => $workerCatalog,
            'worker_options' => [],
            'worker_options_status' => (string) ($workerCatalog['status'] ?? 'unavailable'),
            'worker_options_message' => '',
            'selected_worker_id' => null,
            'scheduled_comodines' => ['status' => 'not_queried', 'workers' => [], 'message' => ''],
            'attendance_rows' => [],
            'effective_schedules' => [],
            'effective_schedule_errors' => [],
            'journey_evaluation_state' => 'not_evaluated',
        ];
        $sortWorkerOptions = static function (array &$options): void {
            $collator = class_exists(\Collator::class) ? new \Collator('es_MX') : null;
            usort($options, static function (array $left, array $right) use ($collator): int {
                $leftName = (string) ($left['full_name'] ?? '');
                $rightName = (string) ($right['full_name'] ?? '');
                $nameOrder = $collator instanceof \Collator
                    ? $collator->compare($leftName, $rightName)
                    : strcmp(strtolower($leftName), strtolower($rightName));
                if (!is_int($nameOrder) || $nameOrder === 0) {
                    return (int) ($left['worker_id'] ?? 0) <=> (int) ($right['worker_id'] ?? 0);
                }
                return $nameOrder;
            });
        };
        $workerOptionsById = [];
        foreach ($workerCatalog['workers'] ?? [] as $profile) {
            if (!is_array($profile)) continue;
            $workerId = self::positiveInteger($profile['worker_id'] ?? null);
            $name = trim((string) ($profile['full_name'] ?? ''));
            if ($workerId === null || $name === '' || $name === 'Nombre no disponible') continue;
            $workerOptionsById[$workerId] = [
                'worker_id' => $workerId,
                'full_name' => $name,
                'employee_number' => is_scalar($profile['employee_number'] ?? null)
                    ? trim((string) $profile['employee_number'])
                    : '',
            ];
        }
        $empty['worker_options'] = array_values($workerOptionsById);
        $sortWorkerOptions($empty['worker_options']);
        $empty['selected_worker_id'] = $requestedWorkerId !== null && isset($workerOptionsById[$requestedWorkerId])
            ? $requestedWorkerId
            : null;
        $rowsWithoutPunches = static function (array $catalog, ?string $timezone, ?int $workerFilter): array {
            $rows = [];
            foreach ($catalog['workers'] ?? [] as $profile) {
                if (!is_array($profile)) continue;
                $workerId = self::positiveInteger($profile['worker_id'] ?? null);
                if ($workerId === null || ($workerFilter !== null && $workerId !== $workerFilter)) continue;
                $row = \App\Services\ChecadorAttendanceProjection::workerRow($profile, [], null, $timezone);
                $row['status'] = 'punches_unavailable';
                $row['schedule'] = null;
                $row['schedule_error'] = 'schedule_not_loaded';
                $row['attendance_relationship'] = \App\Services\ChecadorAttendanceRosterService::observedWorkerRelationship(
                    $workerId,
                    $catalog,
                    []
                );
                $row['worked_seconds'] = null;
                $row['worked_time_source'] = null;
                $row['incidence_count'] = null;
                $rows[$workerId] = $row;
            }
            return $rows;
        };
        $empty['attendance_rows'] = $rowsWithoutPunches(
            $workerCatalog,
            null,
            $empty['selected_worker_id']
        );
        $empty['attendance_roster'] = $workerCatalog;

        if ($requestedWorkerInvalid) {
            $empty['state'] = 'error';
            $empty['state_message'] = 'El filtro de colaborador no es válido.';
            return $empty;
        }

        $timezoneResult = $timezoneResolutionForDate
            ?? $reader->resolveWorkplaceLocalDay($user, $workplaceId, $date);
        if (!($timezoneResult['ok'] ?? false)) {
            [$empty['state'], $empty['state_message']] = self::stateForError(
                (string) ($timezoneResult['error']['kind'] ?? 'api_unavailable')
            );
            if (($workerCatalog['coverage'] ?? null) === 'current_active_roster') {
                $empty['state_message'] .= ' Se conserva el padrón activo actual; no acredita pertenencia histórica para la fecha solicitada.';
            }
            return $empty;
        }

        $timezone = $timezoneResult['data'];
        $empty['timezone'] = $timezone;
        $rosterService = new \App\Services\ChecadorAttendanceRosterService();
        $dateWorkerCatalog = $rosterService->workersForAttendanceFilter(
            $workplaceId,
            $date,
            (string) ($timezone['timezone'] ?? ''),
            false
        );
        if (in_array((string) ($dateWorkerCatalog['status'] ?? ''), ['ready', 'partial', 'current_roster_only'], true)) {
            $workerOptionsById = [];
            foreach ($dateWorkerCatalog['workers'] ?? [] as $profile) {
                if (!is_array($profile)) continue;
                $workerId = self::positiveInteger($profile['worker_id'] ?? null);
                $name = trim((string) ($profile['full_name'] ?? ''));
                if ($workerId === null || $name === '' || $name === 'Nombre no disponible') continue;
                $workerOptionsById[$workerId] = [
                    'worker_id' => $workerId,
                    'full_name' => $name,
                    'employee_number' => is_scalar($profile['employee_number'] ?? null)
                        ? trim((string) $profile['employee_number'])
                        : '',
                ];
            }
            $empty['worker_options_status'] = (string) $dateWorkerCatalog['status'];
            $empty['worker_options'] = array_values($workerOptionsById);
            $empty['worker_options_message'] = (string) ($dateWorkerCatalog['message'] ?? '');
            $sortWorkerOptions($empty['worker_options']);
        }
        $attendanceRoster = in_array((string) ($dateWorkerCatalog['status'] ?? ''), ['ready', 'partial', 'current_roster_only'], true)
            ? $dateWorkerCatalog
            : $workerCatalog;
        $empty['attendance_roster'] = $attendanceRoster;
        $empty['attendance_rows'] = $rowsWithoutPunches(
            $attendanceRoster,
            (string) ($timezoneResult['data']['timezone'] ?? ''),
            $empty['selected_worker_id']
        );
        if ($requestedWorkerId !== null && !isset($workerOptionsById[$requestedWorkerId])) {
            $empty['state'] = 'error';
            $empty['state_message'] = 'El colaborador seleccionado no pertenece a la localidad autorizada para esta consulta.';
            $empty['attendance_rows'] = [];
            return $empty;
        }
        $empty['selected_worker_id'] = $requestedWorkerId;
        $dateRoster = $rosterService->workersForScheduleDate(
            $workplaceId,
            $date,
            (string) ($timezone['timezone'] ?? ''),
            false
        );

        $events = [];
        $firstPagination = null;
        $readComplete = false;
        for ($page = 1; $page <= 20; $page++) {
            $result = $reader->readObservedPunches($user, $workplaceId, $timezone, $page, 200);
            if (!($result['ok'] ?? false)) {
                [$empty['state'], $empty['state_message']] = self::stateForError(
                    (string) ($result['error']['kind'] ?? 'api_unavailable')
                );
                $empty['state_message'] = 'Marcaciones no disponibles. ' . $empty['state_message'];
                return $empty;
            }
            $pagination = $result['pagination'];
            if ($firstPagination === null) {
                $firstPagination = $pagination;
            } elseif ((int) $pagination['total_records'] !== (int) $firstPagination['total_records']
                || (int) $pagination['total_pages'] !== (int) $firstPagination['total_pages']) {
                $empty['state'] = 'unavailable';
                $empty['state_message'] = 'El resultado cambió mientras se leían sus páginas. Vuelve a consultar para obtener una lectura coherente.';
                $empty['state_message'] = 'Marcaciones no disponibles. ' . $empty['state_message'];
                return $empty;
            }
            foreach ($result['events'] as $event) {
                $events[] = $event;
            }
            $requiredPages = max(1, (int) ($firstPagination['total_pages'] ?? 0));
            if ($page >= $requiredPages) {
                $readComplete = count($events) === (int) ($firstPagination['total_records'] ?? 0);
                break;
            }
        }
        if (!$readComplete) {
            $empty['state'] = 'unavailable';
            $empty['state_message'] = 'La consulta excede el límite seguro de páginas o cambió durante la lectura; no se muestran resultados parciales.';
            $empty['state_message'] = 'Marcaciones no disponibles. ' . $empty['state_message'];
            return $empty;
        }

        // Only events with an API-verified HMAC, a resolved canonical worker,
        // and a completed identity state become attendance rows. The raw GET
        // response does not classify events into the four neutral positions.
        $attendanceEvents = [];
        $unverifiedIntegrityCount = 0;
        $unresolvedIdentityCount = 0;
        $stationMismatchCount = 0;
        foreach ($events as $event) {
            $eventStationId = self::positiveInteger($event['id_estacion_resuelta'] ?? null);
            $integrityStatus = strtoupper(trim((string) ($event['integrity']['status'] ?? '')));
            $workerId = self::positiveInteger($event['id_personal_resuelto'] ?? null);
            $processingStatus = strtoupper(trim((string) ($event['processing_status'] ?? '')));
            if ($eventStationId !== $workplaceId) {
                $stationMismatchCount++;
                continue;
            }
            if ($integrityStatus !== 'VALID') {
                $unverifiedIntegrityCount++;
            }
            if ($workerId === null || $processingStatus !== 'RECEIVED') {
                $unresolvedIdentityCount++;
            }
            if ($integrityStatus !== 'VALID' || $workerId === null || $processingStatus !== 'RECEIVED') {
                continue;
            }
            $attendanceEvents[] = $event;
        }

        $attendanceRoster = in_array((string) ($dateWorkerCatalog['status'] ?? ''), ['ready', 'partial', 'current_roster_only'], true)
            ? $dateWorkerCatalog
            : $workerCatalog;
        $scheduledComodines = ['status' => 'not_queried', 'workers' => [], 'message' => ''];
        $programmingRead = RolComodinesService::authorizeCanonicalProgramming($workplaceId, 'read');
        if (!empty($programmingRead['allowed'])) {
            $scheduledComodines = RolComodinesService::getCanonicalScheduledWorkersForDate(
                $workplaceId,
                $date,
                $programmingRead['station_workplace_ids'] ?? $authorizedWorkplaceIds
            );
        } else {
            $scheduledComodines = ['status' => 'not_authorized', 'workers' => [], 'message' => ''];
        }

        $eventsByWorker = [];
        $observedIds = [];
        foreach ($attendanceEvents as $event) {
            $workerId = (int) $event['id_personal_resuelto'];
            $observedIds[$workerId] = true;
            $eventsByWorker[$workerId][] = $event;
        }

        $observedProfiles = $rosterService->observedProfiles(array_map('intval', array_keys($observedIds)), false);
        $profiles = [];
        foreach ($attendanceRoster['workers'] ?? [] as $profile) {
            $profileWorkerId = (int) ($profile['worker_id'] ?? 0);
            if ($profileWorkerId > 0) {
                $profiles[$profileWorkerId] = $profile;
            }
        }
        $profiles += $observedProfiles;
        foreach (array_keys($observedIds) as $workerId) {
            $profiles[$workerId] ??= ['worker_id' => (int) $workerId, 'full_name' => 'Nombre no disponible', 'employee_number' => null];
        }
        if ($requestedWorkerId !== null) {
            $profiles = array_intersect_key($profiles, [$requestedWorkerId => true]);
        }

        $effectiveSchedules = [];
        $effectiveScheduleErrors = [];
        foreach (array_slice(array_keys($profiles), 0, 50) as $workerId) {
            $scheduleResult = $reader->getEffectiveSchedule($user, (int) $workerId, $workplaceId, $date);
            if ($scheduleResult['ok']) {
                $effectiveSchedules[(int) $workerId] = $scheduleResult['data'];
            } else {
                $effectiveScheduleErrors[(int) $workerId] = $scheduleResult['error']['kind'];
            }
        }

        $journeys = [];
        $journeyErrors = [];
        $journeyEvaluationState = 'not_evaluated';

        $attendanceRows = [];
        foreach ($profiles as $workerId => $profile) {
            $workerId = (int) $workerId;
            $attendanceRows[$workerId] = \App\Services\ChecadorAttendanceProjection::workerRow(
                $profile,
                $eventsByWorker[$workerId] ?? [],
                $journeys[$workerId] ?? null,
                (string) ($timezone['timezone'] ?? '')
            );
            if (isset($journeyErrors[$workerId])) {
                $attendanceRows[$workerId]['journey_error'] = $journeyErrors[$workerId];
                if (($attendanceRows[$workerId]['observed_count'] ?? 0) > 0) {
                    $attendanceRows[$workerId]['status'] = 'evaluation_unavailable';
                }
            }
            $attendanceRows[$workerId]['schedule'] = $effectiveSchedules[$workerId] ?? null;
            $attendanceRows[$workerId]['schedule_error'] = $effectiveScheduleErrors[$workerId] ?? 'schedule_not_loaded';
            $attendanceRows[$workerId]['attendance_relationship'] =
                \App\Services\ChecadorAttendanceRosterService::observedWorkerRelationship(
                    $workerId,
                    $attendanceRoster,
                    $scheduledComodines['workers'] ?? []
                );
            $attendanceRows[$workerId]['worked_seconds'] = null;
            $attendanceRows[$workerId]['worked_time_source'] = null;
            $attendanceRows[$workerId]['incidence_count'] = null;
        }

        $omittedCount = max(0, count($events) - count($attendanceEvents));
        $coverageMessage = $omittedCount > 0
            ? sprintf(
                'Se omitieron %d marcaciones con integridad, identidad, estación o estado de procesamiento no verificable. La clasificación de las posiciones no está disponible en esta lectura.',
                $omittedCount
            )
            : (count($events) === 0
                ? 'La API devolvió cero marcaciones para esta estación y fecha. Esta lectura no acredita que la fuente haya recibido todas las marcaciones del periodo.'
                : 'Se leyeron todas las páginas disponibles del API; la lectura no acredita que la fuente haya enviado todas las marcaciones del periodo.');

        return [
            'state' => 'success',
            'state_message' => '',
            'timezone' => $timezone,
            'events' => $attendanceEvents,
            'attendance_events' => $attendanceEvents,
            'attendance_coverage' => [
                'status' => $omittedCount > 0 ? 'partial' : 'source_completeness_unknown',
                'complete' => false,
                'message' => $coverageMessage,
                'api_records_read' => count($events),
                'unverified_integrity_count' => $unverifiedIntegrityCount,
                'unresolved_identity_count' => $unresolvedIdentityCount,
                'station_mismatch_count' => $stationMismatchCount,
            ],
            'attendance_roster' => $attendanceRoster,
            'worker_options' => $empty['worker_options'],
            'worker_options_status' => $empty['worker_options_status'],
            'worker_options_message' => $empty['worker_options_message'],
            'selected_worker_id' => $empty['selected_worker_id'],
            'scheduled_comodines' => $scheduledComodines,
            'attendance_rows' => $attendanceRows,
            'effective_schedules' => $effectiveSchedules,
            'effective_schedule_errors' => $effectiveScheduleErrors,
            'journey_evaluation_state' => $journeyEvaluationState,
        ];
    }

    /** Read a bounded stable set for the worker-oriented snapshot workspace. */
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
            'date' => $date,
            'page' => $page,
            'per_page' => $perPage,
            'snapshot_ref' => $snapshotRef,
        ];
        if ($workerId !== null) $query['worker_id'] = $workerId;
        return [
            'page' => $page,
            'url' => '/departamento-operativo/recursos-humanos/checador/asistencia?' . http_build_query($query),
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

    /**
     * M07-only session feedback lookup. Other workspaces do not read, expose,
     * or consume the one-time PIN feedback key.
     *
     * @return array{feedback:?array,consume_hash:?string}
     */
    private static function temporaryAccessFeedbackForWorkspace(
        ?string $workspace,
        array $query,
        ?int $userId,
        ?int $workplaceId,
        bool $canViewTemporaryPin
    ): array {
        $empty = ['feedback' => null, 'consume_hash' => null];
        if ($workspace !== 'temporary-access') {
            return $empty;
        }

        $stored = Session::get('checador_temporary_access_feedback');
        if (!is_array($stored)) {
            return $empty;
        }

        $requestUuid = self::safeTemporaryAccessRequestUuid($query['request_uuid'] ?? null);
        if (!self::temporaryAccessFeedbackMatchesContext(
            $stored,
            $requestUuid,
            $userId,
            $workplaceId
        )) {
            return $empty;
        }

        // A matching M07 operation may consume only after View::render returns.
        // This hash is process-local bookkeeping, never stored in session or sent.
        $consumeHash = hash('sha256', serialize($stored));
        $feedback = self::temporaryAccessFeedbackForPresentation(
            $stored,
            $requestUuid,
            $userId,
            $workplaceId,
            $canViewTemporaryPin
        );

        return ['feedback' => $feedback, 'consume_hash' => $consumeHash];
    }

    private static function temporaryAccessFeedbackMatchesContext(
        array $feedback,
        ?string $requestUuid,
        ?int $userId,
        ?int $workplaceId
    ): bool {
        $feedbackUuid = self::safeTemporaryAccessRequestUuid($feedback['request_uuid'] ?? null);
        return $requestUuid !== null
            && $feedbackUuid !== null
            && hash_equals($feedbackUuid, $requestUuid)
            && $userId !== null
            && $userId > 0
            && (int) ($feedback['user_id'] ?? 0) === $userId
            && $workplaceId !== null
            && $workplaceId > 0
            && (int) ($feedback['workplace_id'] ?? 0) === $workplaceId
            && in_array(($feedback['action'] ?? null), ['authorize', 'reject', 'cancel'], true)
            && in_array(($feedback['type'] ?? null), ['success', 'error'], true);
    }

    private static function temporaryAccessFeedbackForPresentation(
        array $feedback,
        ?string $requestUuid,
        ?int $userId,
        ?int $workplaceId,
        bool $canViewTemporaryPin
    ): ?array {
        if (!self::temporaryAccessFeedbackMatchesContext($feedback, $requestUuid, $userId, $workplaceId)) {
            return null;
        }

        $action = (string) $feedback['action'];
        $type = (string) $feedback['type'];
        $message = is_string($feedback['message'] ?? null) ? trim($feedback['message']) : '';
        if ($message === '' || strlen($message) > 500) {
            return null;
        }

        $presentation = [
            'type' => $type,
            'message' => $message,
            'request_uuid' => $requestUuid,
            'action' => $action,
        ];
        $hasPin = array_key_exists('temporary_pin', $feedback);
        if ($hasPin || ($action === 'authorize' && $type === 'success')) {
            if (!$hasPin
                || !$canViewTemporaryPin
                || $action !== 'authorize'
                || $type !== 'success'
                || !is_string($feedback['temporary_pin'])
                || !preg_match('/\A[0-9]{6}\z/', $feedback['temporary_pin'])
                || !is_string($feedback['pin_expires_at_utc'] ?? null)) {
                return null;
            }

            $expiresAt = self::temporaryPinExpiryTimestamp($feedback['pin_expires_at_utc']);
            if ($expiresAt === null || $expiresAt <= time()) {
                return null;
            }

            $presentation['temporary_pin'] = $feedback['temporary_pin'];
            $presentation['pin_expires_at_utc'] = $feedback['pin_expires_at_utc'];
        }

        return $presentation;
    }

    private static function safeTemporaryAccessRequestUuid(mixed $value): ?string
    {
        if (!is_string($value)
            || !preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $value)) {
            return null;
        }
        return strtolower($value);
    }

    private static function temporaryPinExpiryTimestamp(mixed $value): ?int
    {
        if (!is_string($value)) {
            return null;
        }
        $expiresAt = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new \DateTimeZone('UTC'));
        $dateErrors = \DateTimeImmutable::getLastErrors();
        if (!$expiresAt instanceof \DateTimeImmutable
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            || $expiresAt->format('Y-m-d H:i:s') !== $value) {
            return null;
        }
        return $expiresAt->getTimestamp();
    }

    private static function discardExpiredTemporaryAccessPinFeedback(): void
    {
        $feedback = Session::get('checador_temporary_access_feedback');
        if (!is_array($feedback) || !array_key_exists('temporary_pin', $feedback)) {
            return;
        }
        $expiresAt = self::temporaryPinExpiryTimestamp($feedback['pin_expires_at_utc'] ?? null);
        if (!is_string($feedback['temporary_pin'])
            || !preg_match('/\A[0-9]{6}\z/', $feedback['temporary_pin'])
            || $expiresAt === null
            || $expiresAt <= time()) {
            Session::remove('checador_temporary_access_feedback');
        }
    }

    private static function applyTemporaryAccessNoStoreHeaders(): void
    {
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true);
        header('Pragma: no-cache', true);
        header('Expires: 0', true);
        header('Surrogate-Control: no-store', true);
    }

    private static function consumeTemporaryAccessFeedbackAfterRender(mixed $feedbackHash): void
    {
        if (!is_string($feedbackHash) || $feedbackHash === '') {
            return;
        }
        $storedFeedback = Session::get('checador_temporary_access_feedback');
        if (is_array($storedFeedback)
            && hash_equals($feedbackHash, hash('sha256', serialize($storedFeedback)))) {
            Session::remove('checador_temporary_access_feedback');
        }
    }

    private static function redirect(string $location): never
    {
        header('Location: ' . $location, true, 303);
        exit;
    }

    /** Return only a valid Monday filter; locality remains session-authoritative. */
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

    private static function scheduleWeekHref(array $query): string
    {
        $parameters = self::scheduleWeekQueryParameters($query);
        $path = '/departamento-operativo/recursos-humanos/checador/programacion-semanal';
        return $path . ($parameters === []
            ? ''
            : '?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986));
    }

    private static function creationRequestUuid(): string
    {
        return sprintf('%s-%s-%s-%s-%s', bin2hex(random_bytes(4)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(6)));
    }

    private static function safeSnapshotRef(mixed $value): ?string
    {
        return is_string($value) && preg_match('/\A[a-f0-9]{32}\z/', $value) ? $value : null;
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

    /**
     * Backward-compatible helper preserving test assertions for observation set inspection.
     * Portal3 rechecks M05 read before any timezone or PunchEvent API GET.
     * API permission failures remain non-empty and fail closed.
     */
    private function executeJourneyObservationSetFallback(
        \App\Models\Usuario $journeyUser,
        int $requestedWorkplace,
        string $date
    ): array {
        $accessCheck = ChecadorAccessService::authorizeJourneyRead($requestedWorkplace);
        if (!($accessCheck['allowed'] ?? false)) {
            return ['error', 'No se muestran marcaciones.'];
        }
        $physicalStationId = ChecadorAccessService::resolveActiveStationIdForLocality($requestedWorkplace);
        if ($physicalStationId === null) {
            return ['unavailable', 'No se pudo resolver de forma única la estación física de esta localidad.'];
        }
        $reader = new ChecadorDailyReadService();
        $tzRes = $reader->resolveWorkplaceLocalDay($journeyUser, $requestedWorkplace, $date);
        $read = $reader->readJourneyObservationSet(
            $journeyUser,
            $requestedWorkplace,
            $physicalStationId,
            $date
        );
        if (($read['ok'] ?? false) !== true) {
            return ['error', 'API V2 no autorizó la lectura de Jornada para esta estación. No se muestran marcaciones.'];
        }
        return ['success', ''];
    }
}
