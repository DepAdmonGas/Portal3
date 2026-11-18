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
use App\Services\ChecadorAttendancePolicyStoreService;
use App\Services\ChecadorRolComodinesService;
use App\Services\ChecadorV2PermissionService;
use App\Services\ChecadorDailyReadService;
use App\Services\ChecadorShiftTemplateSqlReadService;
use App\Services\ChecadorConfigurationReadService;
use App\Services\ChecadorOperationalPeriod;
use App\Services\ChecadorPresentationFormatter;
use App\Services\ChecadorPayrollComparisonReadService;
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
        $this->normalizeLegacyWorkplaceUrl();
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
        $coverageWorkplaces = $coverageWriterEnabled && $canRequestCoverageGrant && $workplaceId !== null
            ? array_values(array_filter(
                ChecadorRolComodinesService::coverageWorkplaces(0),
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
            $selectedAccess = ChecadorAccessService::authorize($requestedWorkplace);
            if (!$selectedAccess['allowed']) {
                self::renderAccessDenied((string) ($selectedAccess['reason'] ?? 'requested_station_out_of_scope'), $requestedWorkplace);
                return;
            }
            if (!ModuleStationService::setChecadorWorkplaceContext($requestedWorkplace)) {
                self::renderAccessDenied('requested_station_out_of_scope', $requestedWorkplace);
                return;
            }
        } elseif ($hasExplicitWorkplace) {
            // An explicit empty value is a deliberate unselected state, not a
            // signal to restore the previous station from this module's session.
            ModuleStationService::setContext(ChecadorAccessService::MODULE_KEY, null, null);
        } else {
            $moduleContext = ModuleStationService::getContext(ChecadorAccessService::MODULE_KEY);
            $contextWorkplace = self::positiveInteger($moduleContext['id_depto'] ?? $moduleContext['id_estacion'] ?? null);
            if ($contextWorkplace !== null && in_array($contextWorkplace, array_column($workplaces, 'id'), true)) {
                $requestedWorkplace = $contextWorkplace;
            } elseif ($workplaces !== []) {
                // First load uses an actually authorized option from the
                // Checador scope; the normal authorization/context write below
                // still runs before any source is queried.
                $requestedWorkplace = (int) $workplaces[0]['id'];
            }
            if ($requestedWorkplace !== null) {
                $selectedAccess = ChecadorAccessService::authorize($requestedWorkplace);
                if (!$selectedAccess['allowed']) {
                    self::renderAccessDenied((string) ($selectedAccess['reason'] ?? 'requested_station_out_of_scope'), $requestedWorkplace);
                    return;
                }
                if (!ModuleStationService::setChecadorWorkplaceContext($requestedWorkplace)) {
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
        // Demo fixtures only replace M01 reads after the existing Portal3
        // authentication, Dirección role, and workplace ACL have succeeded.
        // Plesk activation additionally requires its explicit server allowlist.
        $advancedDemoMode = ChecadorDailyReadService::demoModeEnabled();
        $m01DemoReader = null;
        $currentDemoUser = Auth::user();
        $authorizedM01DemoWorkplaceIds = array_map('intval', array_column($workplaces, 'id'));
        $m01DemoUserAuthorized = $workspace === 'daily-attendance'
            && $currentDemoUser !== null
            && (int) ($currentDemoUser->id_puesto ?? 0) === 13
            && $requestedWorkplace !== null
            && in_array($requestedWorkplace, $authorizedM01DemoWorkplaceIds, true);
        if ($workspace === 'daily-attendance' && $m01DemoUserAuthorized) {
            $m01DemoReader = ChecadorDailyReadService::createAuthorizedM01PleskDemoReader(
                $currentDemoUser,
                $requestedWorkplace,
                $authorizedM01DemoWorkplaceIds
            );
        }
        $m01DemoMode = $workspace === 'daily-attendance'
            && $m01DemoUserAuthorized
            && ($advancedDemoMode || $m01DemoReader instanceof ChecadorDailyReadService);
        $demoMode = $workspace === 'daily-attendance' ? $m01DemoMode : $advancedDemoMode;
        $realApiPostOperationsEnabled = ChecadorDailyReadService::realApiPostOperationsEnabled();
        $apiPostOperationsEnabled = $m01DemoMode
            ? false
            : ($demoMode || $realApiPostOperationsEnabled);
        $canViewTemporaryPin = $workspace === 'temporary-access'
            && !$demoMode
            && $realApiPostOperationsEnabled
            && (ChecadorAccessService::authorizeTemporaryAccess($requestedWorkplace, 'editar')['allowed'] ?? false) === true;
        $demoScenario = $m01DemoReader instanceof ChecadorDailyReadService
            ? $m01DemoReader->demoScenario()
            : ChecadorDailyReadService::configuredDemoScenario();

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
        $attendanceTimezoneResolution = $defaultTimezoneResolution;
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
        if ($workspaceSnapshotMissing) {
            $state = 'snapshot_state_unavailable';
            $stateMessage = 'Carga primero la asistencia diaria para abrir esta vista con una consulta estable y su referencia autorizada.';
        }

        if ($workspace !== 'temporary-access' && !$workspaceSnapshotMissing && $requestedWorkplace !== null && $date !== '') {
            $user = Auth::user();
            if (!$user) {
                $state = 'error';
                $stateMessage = 'La sesión ya no es válida. Inicia sesión nuevamente.';
            } else {
                $workerCatalog = null;
                try {
                    if ($workspace === 'daily-attendance' && $m01DemoMode) {
                        $demoReader = $m01DemoReader ?? new ChecadorDailyReadService();
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
                        // Resolve the authorized local roster before API
                        // initialization. Missing API configuration must not
                        // prevent an independent roster read.
                        try {
                            $reader = new ChecadorDailyReadService();
                        } catch (\Throwable) {
                            $reader = null;
                        }
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
                        $attendanceRosterFallbackAllowed = $rawAttendance['roster_fallback_allowed'];
                        $workerSelectOptions = $rawAttendance['worker_options'];
                        $requestedWorker = $rawAttendance['selected_worker_id'];
                        $scheduledComodines = $rawAttendance['scheduled_comodines'];
                        $attendanceRows = $rawAttendance['attendance_rows'];
                        $effectiveSchedules = $rawAttendance['effective_schedules'];
                        $effectiveScheduleErrors = $rawAttendance['effective_schedule_errors'];
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
                            $referenceRead = self::readDailyRawAttendance(
                                null,
                                $user,
                                $requestedWorkplace,
                                $date,
                                $requestedWorker,
                                $requestedWorkerInvalid,
                                $authorizedStationWorkplaceIds,
                                $workerCatalog,
                                null
                            );
                            if (($referenceRead['roster_fallback_allowed'] ?? false) === true) {
                                $attendanceRoster = $referenceRead['attendance_roster'];
                                $attendanceRosterFallbackAllowed = true;
                                $attendanceRows = $referenceRead['attendance_rows'];
                                $workerSelectOptions = $referenceRead['worker_options'];
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
                'links' => $workspace === 'daily-attendance'
                    ? [
                        '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
                        '/assets/libs/select2/dist/css/select2.min.css',
                        '/assets/css/select2-modal.css',
                    ]
                    : [],
                'scripts' => [
                    ...($workspace === 'daily-attendance' ? [
                        '/assets/js/vendor.min.js',
                        '/assets/libs/select2/dist/js/select2.full.min.js',
                        '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                        '/assets/js/departamento-operativo/2-recursos-humanos/checador.attendance.datatable.init.js?v=20261116-1',
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
                'attendancePolicyWritesEnabled' => ChecadorDailyReadService::attendancePolicyPostOperationsEnabled(),
                'm11ConfigurationWritesEnabled' => ChecadorDailyReadService::m11ConfigurationPostOperationsEnabled(),
                'scheduleTemplateWritesEnabled' => ChecadorDailyReadService::scheduleApiPostOperationsEnabled(),
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
                'invalid_position_name' => 422, 'm11_write_disabled' => 503, default => 500,
            };
            $message = match ($error) {
                'permission_denied' => 'No tienes permiso para administrar el catálogo institucional de puestos.',
                'm11_write_disabled' => 'La escritura M11 está desactivada en este entorno.',
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
                'm11_write_disabled' => 'La escritura M11 está desactivada en este entorno. No se guardó ningún cambio.',
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
                'invalid_profile_input' => 422, 'm11_write_disabled' => 503, default => 502,
            };
            $message = match ($error) {
                'permission_denied' => 'No tienes permiso para editar perfiles de lector en este centro.',
                'm11_write_disabled' => 'La escritura M11 está desactivada en este entorno.',
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
                'm11_write_disabled' => 'La escritura M11 está desactivada en este entorno. No se guardó ningún cambio.',
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

    /** Create a station-local template or append a new immutable version through the existing V2 contract. */
    public function saveConfigurationScheduleTemplate(): void
    {
        $input = Request::all();
        if (str_contains(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) {
            $this->saveScheduleTemplateJson($input);
        }
        $workplaceId = self::positiveInteger($input['workplace_id'] ?? null);
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
        if ($workplaceId === null || !in_array($operation, ['create', 'version'], true)
            || ($input['confirm_template'] ?? '') !== '1') {
            self::scheduleTemplateFeedback('error', 'Revisa el centro y confirma la operación solicitada. No se envió la plantilla.', $workplaceId);
            self::redirect($redirect);
        }
        if (ChecadorDailyReadService::demoModeEnabled()) {
            self::scheduleTemplateFeedback('error', 'El modo demostración es de consulta y no permite escribir plantillas operativas.', $workplaceId);
            self::redirect($redirect);
        }
        if (!ChecadorDailyReadService::scheduleApiPostOperationsEnabled()) {
            self::scheduleTemplateFeedback('error', 'La escritura de horarios está protegida por el gate local específico. No se envió la solicitud.', $workplaceId);
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
            $reader = new ChecadorDailyReadService();
            $result = $operation === 'create'
                ? $reader->createStationShiftTemplate($user, $workplaceId, [
                    'clave' => $key, 'nombre' => $name, 'tipo_turno' => $type, 'segmentos' => $segments,
                ])
                : $reader->createStationShiftTemplateVersion($user, $workplaceId, $key, [
                    'nombre' => $name, 'tipo_turno' => $type, 'segmentos' => $segments,
                ]);
        } catch (\Throwable) {
            $result = ['ok' => false, 'error' => ['kind' => 'unavailable']];
        }
        self::scheduleTemplateFeedback(($result['ok'] ?? false) ? 'success' : 'error',
            ($result['ok'] ?? false)
                ? 'La plantilla y sus segmentos se guardaron y se confirmaron mediante lectura de API V2.'
                : 'La API no confirmó el guardado y la lectura posterior de la plantilla. Consulta el catálogo; no se declara guardada.',
            $workplaceId);
        self::redirect($redirect);
    }

    /** Preserve the original M04 Alpine workflow while writing only through the existing V2 contract. */
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

        $workplaceId = $this->requestWorkplaceId($input);
        $rawWorkplace = self::positiveInteger($input['workplace_id'] ?? null);
        if ($workplaceId === null || $rawWorkplace !== $workplaceId) {
            $fail('forbidden', 'La estación solicitada no coincide con tu alcance autorizado.', 403);
        }
        if (ChecadorDailyReadService::demoModeEnabled()) $fail('demo_mode', 'La demostración es de consulta.', 403);
        if (!ChecadorDailyReadService::scheduleApiPostOperationsEnabled()) {
            $fail('gate_disabled', 'La escritura de horarios está deshabilitada.', 403);
        }
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

        try {
            $reader = new ChecadorDailyReadService();
            $catalog = $reader->getShiftTemplates($user, 1, 200, $workplaceId);
            if (!($catalog['ok'] ?? false)) $fail('catalog_unavailable', 'No se pudo comprobar el catálogo autorizado.', 502);
            $items = $catalog['data']['items'] ?? [];
            $result = null;

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
                $result = $reader->createStationShiftTemplate($user, $workplaceId, [
                    'clave' => $key, 'nombre' => $name, 'tipo_turno' => $type, 'segmentos' => $segments,
                ]);
            } elseif ($operation === 'customize') {
                if (ChecadorAccessService::isDepartmentLocality($workplaceId)) {
                    $fail('forbidden', 'No tienes permiso para personalizar turnos en esta estación.', 403);
                }
                $sourceKey = is_string($input['source_template_key'] ?? null) ? trim($input['source_template_key']) : '';
                $sourceVersion = self::positiveInteger($input['source_template_version'] ?? null);
                $sourceItem = null;
                foreach ($items as $item) {
                    if ((string) ($item['clave'] ?? '') === $sourceKey
                        && strtoupper((string) ($item['scope'] ?? '')) === 'GLOBAL') {
                        $sourceItem = $item;
                        break;
                    }
                }
                if ($sourceItem === null || $sourceVersion === null || $sourceVersion !== (int) ($sourceItem['version'] ?? 0)) {
                    $fail('source_unavailable', 'El turno global ya no está disponible. Actualiza el catálogo.', 404);
                }
                $source = $reader->getShiftTemplateVersion($user, $sourceKey, $sourceVersion, $workplaceId);
                if (!($source['ok'] ?? false)
                    || strtoupper((string) ($source['data']['scope'] ?? '')) !== 'GLOBAL'
                    || (int) ($source['data']['version'] ?? 0) !== $sourceVersion
                    || (int) ($source['data']['es_activo'] ?? 0) !== 1) {
                    $fail('source_unavailable', 'No se pudo confirmar el turno global de origen.', 404);
                }
                $result = $reader->customizeGlobalShiftTemplate(
                    $user,
                    ['clave' => $sourceKey, 'version' => $sourceVersion],
                    ['nombre' => $name, 'tipo_turno' => (string) $source['data']['tipo_turno'], 'segmentos' => $segments],
                    self::newIdempotencyKey(),
                    $workplaceId
                );
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
                $previous = $reader->getShiftTemplateVersion($user, $key, $expectedVersion, $workplaceId);
                if (!($previous['ok'] ?? false)
                    || (int) ($previous['data']['id'] ?? 0) !== (int) ($local['id'] ?? 0)
                    || self::editableScheduleTemplateFields($previous['data']['segmentos'] ?? null) === null) {
                    $fail('catalog_unavailable', 'No se pudo verificar el turno seleccionado.', 502);
                }
                $result = $reader->createStationShiftTemplateVersion($user, $workplaceId, $key, [
                    'nombre' => $name, 'tipo_turno' => (string) ($previous['data']['tipo_turno'] ?? 'REGULAR'), 'segmentos' => $segments,
                ]);
            }

            if (!($result['ok'] ?? false)) {
                $kind = (string) ($result['error']['kind'] ?? 'api_unavailable');
                $status = match ($kind) {
                    'bad_request' => 422,
                    'api_authentication' => 401,
                    'api_authorization', 'api_post_disabled' => 403,
                    'administrative_conflict' => 409,
                    default => 502,
                };
                $fail($kind, $status === 409 ? 'Ya existe un conflicto con ese turno.' : 'No se pudo confirmar el turno. Actualiza el catálogo antes de reintentar.', $status);
            }
            $writtenTemplate = $result['data'] ?? null;
            if (!is_array($writtenTemplate)
                || !is_string($writtenTemplate['clave'] ?? null)
                || (int) ($writtenTemplate['id'] ?? 0) < 1
                || (int) ($writtenTemplate['version'] ?? 0) < 1
                || strtoupper((string) ($writtenTemplate['scope'] ?? '')) !== 'STATION'
                || (int) ($writtenTemplate['id_estacion'] ?? 0) !== $workplaceId) {
                $fail('schedule_template_readback_unconfirmed', 'No se confirmó el turno mediante lectura posterior.', 502);
            }
            $sqlReader = new ChecadorShiftTemplateSqlReadService();
            $sqlReadback = $sqlReader->getShiftTemplateVersion(
                $user,
                (string) $writtenTemplate['clave'],
                (int) $writtenTemplate['version'],
                $workplaceId,
                (int) $writtenTemplate['id'],
                'STATION',
                $workplaceId
            );
            $template = $sqlReadback['data'] ?? null;
            if (!($sqlReadback['ok'] ?? false) || !is_array($template)
                || ($template['segmentos'] ?? null) !== $segments
                || self::editableScheduleTemplateFields($template['segmentos'] ?? null) === null
                || trim((string) ($template['segmentos'][0]['hora_inicio_local'] ?? '')) === ''
                || trim((string) ($template['segmentos'][array_key_last($template['segmentos'])]['hora_fin_local'] ?? '')) === '') {
                $fail('schedule_template_readback_unconfirmed', 'La plantilla no quedó confirmada con sus segmentos persistidos. No vuelvas a guardar; consulta el catálogo antes de reintentar.', 502);
            }
            JsonResponse::send([
                'ok' => true,
                'message' => $operation === 'customize'
                    ? 'La personalización local y sus horas persistidas fueron confirmadas mediante lectura SQL.'
                    : 'El turno y sus horas persistidas fueron confirmados mediante lectura SQL.',
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
        if (!ChecadorDailyReadService::attendancePolicyPostOperationsEnabled()) {
            if ($wantsJson) $jsonFail(503, 'write_gate_closed', 'La escritura M11 no está disponible en este entorno.');
            self::configurationFeedback('error', 'La escritura M11 permanece deshabilitada en esta configuración. No se guardó ningún cambio.', $workplaceId);
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
                'write_gate_closed', 'demo_mode' => 403,
                default => 503,
            };
            $message = match ($error) {
                'permission_denied' => 'No tienes autorización efectiva para editar la política de esta localidad.',
                'idempotency_conflict' => 'La clave de solicitud ya está asociada a otros valores; conserva la clave sólo para reintentar los mismos datos.',
                'policy_date_conflict', 'policy_version_date_conflict' => 'Ya existe una versión para esa fecha o la vigencia no sigue la secuencia persistida.',
                'policy_must_be_prospective' => 'La fecha debe ser posterior al día local vigente.',
                'invalid_policy_input' => 'Revisa la fecha, los valores y la clave de solicitud.',
                'write_gate_closed' => 'La escritura M11 no está habilitada en este entorno.',
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
        $canManageStationTemplates = $workplaceId !== null && !$demoMode
            && ChecadorDailyReadService::scheduleApiPostOperationsEnabled()
            && (ChecadorAccessService::authorizeStationTemplateAdministration($workplaceId)['allowed'] ?? false);

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
                $templateReader = new ChecadorShiftTemplateSqlReadService();
                $catalog = $templateReader->getShiftTemplates($currentUser, $workplaceId, 1, 200);
                if (($catalog['ok'] ?? false) === true) {
                    $templateCatalogStatus = 'received';
                    $catalogItems = $catalog['data']['items'];
                    foreach (array_slice($catalogItems, 0, 50) as $template) {
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
                    $localTemplateByKey = [];
                    $catalogWorkplaceId = (int) ($templateCatalogAccess['catalog_workplace_id'] ?? $workplaceId);
                    foreach ($scheduleTemplates as $template) {
                        if (strtoupper((string) ($template['scope'] ?? '')) === 'STATION'
                            && (int) ($template['id_estacion'] ?? 0) === $catalogWorkplaceId) {
                            $localTemplateByKey[(string) ($template['clave'] ?? '')] = $template;
                        }
                    }
                    foreach ($scheduleTemplates as &$template) {
                        if (strtoupper((string) ($template['scope'] ?? '')) !== 'GLOBAL') continue;
                        $customization = is_array($template['station_customization'] ?? null) ? $template['station_customization'] : null;
                        if ($customization === null) {
                            if ((int) ($template['es_activo'] ?? 0) === 1) {
                                $template['customize_fields'] = self::editableScheduleTemplateFields($template['segmentos'] ?? null);
                            }
                            continue;
                        }
                        $localCopy = $localTemplateByKey[(string) ($customization['clave'] ?? '')] ?? null;
                        if ($localCopy !== null) {
                            $template['station_customization']['template'] = $localCopy;
                        } else {
                            unset($template['customize_fields']);
                        }
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
                'templateWritesEnabled' => ChecadorDailyReadService::scheduleApiPostOperationsEnabled() && !$demoMode,
                'templateAdminFeedback' => $templateFeedback,
                'canManageStationTemplates' => $canManageStationTemplates,
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
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.schedule-history.templates.init.js?v=20261117-2',
                ],
                'help' => false,
                'moduleStationKey' => ChecadorAccessService::MODULE_KEY,
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
        // Writes stay closed until the legacy API writer is cut off under the
        // reconciled M03 contract; there is deliberately no HTTP fallback.
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

    /** Read the selected week date by date; never labels this as complete assignment history. */
    public function habitualAssignmentReadback(): void
    {
        $query = Request::query();
        $workerId = self::positiveInteger($query['worker_id'] ?? null);
        $workplaceId = $this->requestWorkplaceId($query);
        $weekStart = is_string($query['week_start'] ?? null) ? trim($query['week_start']) : '';
        $dates = \App\Services\ChecadorWeeklyScheduleService::dates($weekStart);
        $user = Auth::user();
        $deny = static function (string $code, string $message, int $status): never {
            JsonResponse::send(['ok' => false, 'code' => $code, 'message' => $message], $status, ['Cache-Control' => 'private, no-store']);
        };

        if ($user === null) $deny('unauthenticated', 'La sesión ya no es válida.', 401);
        if ($workerId === null || $workplaceId === null || $dates === []
            || \App\Services\ChecadorWeeklyScheduleService::monday($weekStart) !== $weekStart) {
            $deny('invalid_request', 'Revisa el trabajador, la estación y la semana seleccionada.', 422);
        }
        if (ChecadorDailyReadService::demoModeEnabled()
            || !(ChecadorAccessService::authorizeScheduling($workplaceId, 'editar')['allowed'] ?? false)) {
            $deny('forbidden', 'No tienes permiso para consultar asignaciones de esta estación.', 403);
        }

        try {
            $reader = new \App\Services\ChecadorWeeklyScheduleSqlReadService();
            $timezone = $reader->timezoneForDate($workplaceId, $weekStart) ?? '';
            if (!\App\Services\ChecadorOperationalPeriod::isIanaTimezone($timezone)
                || !\App\Services\ChecadorWeeklyScheduleService::isCurrentOrFutureWeek($weekStart, $timezone)) {
                $deny('workplace_unavailable', 'No se pudo confirmar la estación, la zona horaria o la semana.', 502);
            }
            $roster = (new \App\Services\ChecadorAttendanceRosterService())
                ->workersForScheduleWeek($workplaceId, $weekStart, $timezone, false);
            $worker = null;
            foreach ($roster['workers'] ?? [] as $candidate) {
                if ((int) ($candidate['worker_id'] ?? 0) === $workerId) { $worker = $candidate; break; }
            }
            if (!is_array($worker)) $deny('membership_unverified', 'La adscripción del trabajador no está confirmada para esta semana.', 403);
            $read = $reader->readWeek($user, $workplaceId, $weekStart, [$worker]);
            if (($read['ok'] ?? false) !== true) {
                $deny('assignment_week_read_unavailable', 'No fue posible consultar la programación persistida; no se habilitó el formulario.', 502);
            }
            $verifiedDates = is_array($worker['versioned_verified_dates'] ?? null)
                ? array_values(array_filter($worker['versioned_verified_dates'], 'is_string')) : [];
            $existingDays = [];
            $existingDaysByDate = [];
            $replacementSources = [];
            foreach ($dates as $day) {
                $readRows = $read['data']['assignments_by_worker'][$workerId] ?? [];
                foreach ($readRows as $assignment) {
                    if (($assignment['tipo_asignacion'] ?? '') !== 'HABITUAL'
                        || ($assignment['dia_semana'] ?? '') !== $day['weekday']
                        || (int) ($assignment['es_activo'] ?? 0) !== 1
                        || (string) ($assignment['valido_desde'] ?? '') > $day['date']
                        || (($assignment['valido_hasta'] ?? null) !== null && $day['date'] >= (string) $assignment['valido_hasta'])) continue;
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

            JsonResponse::send(['ok' => true, 'data' => [
                'complete' => true,
                'read_scope' => 'SELECTED_WEEK_DATE_FILTERS',
                'history_complete' => false,
                'existing_days' => $existingDays,
                'existing_days_by_date' => $existingDaysByDate,
                'verified_dates' => $verifiedDates,
                'habitual_complete' => count($existingDays) === count($dates),
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
        $result = ['ok' => false, 'error' => 'm03_writer_cutover_not_ready', 'status' => 503];
        if ($user === null) {
            $result = ['ok' => false, 'error' => 'unauthenticated', 'status' => 401];
        } elseif ($workplaceId === null || $rawWorkplaceId !== $workplaceId) {
            $result = ['ok' => false, 'error' => 'permission_denied', 'status' => 403];
        } elseif (!in_array((string) ($input['confirm_action'] ?? ''), ['1', 'on'], true)) {
            $result = ['ok' => false, 'error' => 'confirmation_required', 'status' => 422];
        } elseif (!ChecadorDailyReadService::demoModeEnabled()) {
            $writer = new \App\Services\ChecadorWeeklyScheduleSqlWriteService();
            $result = match ($operation) {
                'create' => $writer->create($user, $input),
                'close' => $writer->close($user, $input),
                'replace' => $writer->replace($user, $input),
                default => ['ok' => false, 'error' => 'invalid_operation', 'status' => 422],
            };
        } else {
            $result = ['ok' => false, 'error' => 'demo_mode', 'status' => 403];
        }
        $status = (int) ($result['status'] ?? (($result['ok'] ?? false) ? 200 : 422));
        $labels = [
            'm03_writer_cutover_not_ready' => 'La escritura SQL de M03 sigue cerrada hasta confirmar el corte del escritor API y la validación del esquema.',
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
        if ($hasComparisonFilters) {
            $result = $comparisonReader->compare(array_merge($query, ['workplace_id' => $workplaceId]));
            if (empty($result['ok'])) {
                $errorMessage = match ((string) ($result['error']['kind'] ?? '')) {
                    'filters_required' => 'Completa trabajador, año y periodo para consultar.',
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
        $periodType = $workplaceId !== null && RecibosNominaService::esSemanal($workplaceId) ? 'week' : 'fortnight';

        View::render(
            'departamento-operativo/2-recursos-humanos/checador/payroll-comparison',
            [
                'title' => 'Comparación informativa de asistencia y Nómina',
                'moduleStationKey' => ChecadorAccessService::MODULE_KEY,
                'workplaces' => $authorization['workplaces'] ?? [],
                'selectedWorkplaceId' => $workplaceId,
                'selectedWorkplaceName' => $selectedWorkplaceName,
                'periodType' => $periodType,
                'filters' => [
                    'worker_id' => is_string($query['worker_id'] ?? null) ? trim($query['worker_id']) : '',
                    'year' => is_string($query['year'] ?? null) ? trim($query['year']) : (string) date('Y'),
                    'period_number' => is_string($query['period_number'] ?? null) ? trim($query['period_number']) : '',
                ],
                'comparison' => is_array($result['data'] ?? null) ? $result['data'] : null,
                'errorMessage' => $errorMessage,
                'demoMode' => ChecadorDailyReadService::demoModeEnabled(),
                'links' => [],
                'scripts' => [],
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

    /** Create an API-owned P3-G incidence after a fresh ACL/scope check. */
    public function createIncidence(): void
    {
        $input = Request::all();
        $workplaceId = self::positiveInteger($input['workplace_id'] ?? null);
        $workerId = self::positiveInteger($input['worker_id'] ?? null);
        $returnDate = is_string($input['return_date'] ?? null) ? trim($input['return_date']) : '';
        $snapshotRef = self::safeSnapshotRef($input['snapshot_ref'] ?? null);
        $category = is_string($input['category'] ?? null) ? trim($input['category']) : '';
        $startDate = is_string($input['start_date'] ?? null) ? trim($input['start_date']) : '';
        $endDate = is_string($input['end_date'] ?? null) ? trim($input['end_date']) : '';
        $reason = is_string($input['reason'] ?? null) ? trim($input['reason']) : '';
        $evidenceReferenceValid = !array_key_exists('evidence_reference', $input) || is_string($input['evidence_reference']);
        $evidenceReference = is_string($input['evidence_reference'] ?? null)
            ? trim($input['evidence_reference'])
            : '';
        $idempotencyKey = is_string($input['idempotency_key'] ?? null) ? trim($input['idempotency_key']) : '';
        $redirect = '/departamento-operativo/recursos-humanos/checador/incidencias?' . http_build_query(array_filter([
            'workplace_id' => $workplaceId,
            'date' => $returnDate,
            'worker_id' => $workerId,
            'snapshot_ref' => $snapshotRef,
        ], static fn(mixed $value): bool => $value !== null && $value !== ''));
        $formValues = [
            'category' => $category,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reason' => $reason,
            'evidence_reference' => $evidenceReference,
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

        if ($workplaceId === null || $workerId === null || !self::isCivilDate($returnDate)
            || !in_array($category, ChecadorDailyReadService::INCIDENCE_CATEGORIES, true)
            || !self::isCivilDate($startDate) || !self::isCivilDate($endDate) || $endDate < $startDate
            || $reason === '' || !$evidenceReferenceValid
            || !preg_match('/\A[A-Za-z0-9._:-]{1,64}\z/', $idempotencyKey)
            || (string) ($input['confirm_create'] ?? '') !== '1') {
            $feedback('error', 'Completa los datos válidos y confirma la creación de la incidencia.');
            self::redirect($redirect);
        }

        if (ChecadorDailyReadService::demoModeEnabled()) {
            $feedback('error', 'El modo demostración no permite crear incidencias ni enviar operaciones de escritura.');
            self::redirect($redirect);
        }

        $authorization = ChecadorAccessService::authorizeIncidenceCreate($workplaceId);
        if (!($authorization['allowed'] ?? false)) {
            $feedback('error', ($authorization['reason'] ?? '') === 'incidence_readback_permission_denied'
                ? 'Se requiere permiso de lectura de incidencias para confirmar el resultado después del alta.'
                : 'No tienes permiso específico para crear incidencias o el centro está fuera de tu alcance.');
            self::redirect($redirect);
        }

        $user = Auth::user();
        if (!$user) {
            $feedback('error', 'La sesión ya no es válida. Inicia sesión nuevamente.');
            self::redirect($redirect);
        }
        if (!ChecadorDailyReadService::realApiPostOperationsEnabled()) {
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
            ], $idempotencyKey);
        } catch (\Throwable) {
            $reader = null;
            $created = ['ok' => false, 'error' => ['kind' => 'api_unavailable', 'status' => 503]];
        }

        if (($created['ok'] ?? false) !== true) {
            $kind = (string) ($created['error']['kind'] ?? 'api_unavailable');
            $message = match ($kind) {
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
        $workerId = self::positiveInteger($input['worker_id'] ?? null);
        $date = is_string($input['date'] ?? null) ? trim((string) $input['date']) : '';
        $snapshotRef = self::safeSnapshotRef($input['snapshot_ref'] ?? null);
        $expectedRevision = self::positiveInteger($input['expected_current_revision'] ?? null);
        $idempotencyKey = is_string($input['idempotency_key'] ?? null) ? trim((string) $input['idempotency_key']) : '';
        $redirect = '/departamento-operativo/recursos-humanos/checador/incidencias?' . http_build_query(array_filter([
            'workplace_id' => $workplaceId,
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

        // Legacy query IDs are never applied as authorization or context. They
        // can only be removed while preserving filters when they match the
        // already authorized canonical locality in this user's session.
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

    private static function readDailyRawAttendance(
        ?ChecadorDailyReadService $reader,
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
            'roster_fallback_allowed' => self::canDisplayAttendanceRosterFallback($workerCatalog),
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
            $empty['roster_fallback_allowed'] = false;
            $empty['attendance_rows'] = [];
            return $empty;
        }

        if ($reader === null) {
            if ($requestedWorkerId !== null && !isset($workerOptionsById[$requestedWorkerId])) {
                $empty['state_message'] = 'El colaborador seleccionado no pertenece a la localidad autorizada para esta consulta.';
                $empty['roster_fallback_allowed'] = false;
                $empty['attendance_rows'] = [];
                return $empty;
            }
            $empty['selected_worker_id'] = $requestedWorkerId;
            $empty['state'] = 'unavailable';
            $empty['state_message'] = 'Marcaciones no disponibles. No se pudo inicializar la conexión de lectura.';
            return $empty;
        }

        $timezoneResult = $timezoneResolutionForDate
            ?? $reader->resolveWorkplaceLocalDay($user, $workplaceId, $date);
        if (!($timezoneResult['ok'] ?? false)) {
            $timezoneErrorKind = (string) ($timezoneResult['error']['kind'] ?? 'api_unavailable');
            [$empty['state'], $empty['state_message']] = self::stateForError($timezoneErrorKind);
            try {
                Logger::warning('Checador M01 timezone resolution diagnostic', [
                    'operation' => 'workplace_timezone',
                    'workplace_id' => $workplaceId,
                    'work_date' => $date,
                    'http_status' => $timezoneResult['error']['status'] ?? null,
                    'local_error_kind' => $timezoneErrorKind,
                    'state' => $empty['state'],
                ]);
            } catch (\Throwable) {
                // Read diagnostics must never change the attendance result.
            }
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
        $empty['roster_fallback_allowed'] = self::canDisplayAttendanceRosterFallback($attendanceRoster);
        $empty['attendance_rows'] = $rowsWithoutPunches(
            $attendanceRoster,
            (string) ($timezoneResult['data']['timezone'] ?? ''),
            $empty['selected_worker_id']
        );
        if ($requestedWorkerId !== null && !isset($workerOptionsById[$requestedWorkerId])) {
            $empty['state'] = 'error';
            $empty['state_message'] = 'El colaborador seleccionado no pertenece a la localidad autorizada para esta consulta.';
            $empty['roster_fallback_allowed'] = false;
            $empty['attendance_rows'] = [];
            return $empty;
        }
        $empty['selected_worker_id'] = $requestedWorkerId;

        $events = [];
        $firstPagination = null;
        $readComplete = false;
        $diagnosticId = bin2hex(random_bytes(12));
        $pagesRead = 0;
        $logPaginationDiagnostic = static function (string $stage, array $details = []) use (
            $diagnosticId,
            $workplaceId,
            $date
        ): void {
            $context = array_merge([
                'operation' => 'daily_attendance_pagination',
                'diagnostic_id' => $diagnosticId,
                'workplace_id' => $workplaceId,
                'work_date' => $date,
                'stage' => $stage,
            ], $details);
            try {
                if (in_array($stage, ['page_read_failed', 'page_totals_inconsistent', 'read_incomplete', 'post_pagination_exception'], true)) {
                    Logger::warning('Checador daily attendance pagination diagnostic', $context);
                    return;
                }
                Logger::info('Checador daily attendance pagination diagnostic', $context);
            } catch (\Throwable) {
                // Read diagnostics must never change pagination or availability.
            }
        };
        for ($page = 1; $page <= 20; $page++) {
            $result = $reader->readObservedPunches($user, $workplaceId, $timezone, $page, 200);
            $pagesRead = $page;
            if (!($result['ok'] ?? false)) {
                $readErrorKind = (string) ($result['error']['kind'] ?? 'api_unavailable');
                [$failureState, $failureMessage] = self::stateForError($readErrorKind);
                $logPaginationDiagnostic('page_read_failed', [
                    'page' => $page,
                    'pages_read' => $pagesRead,
                    'events_collected' => count($events),
                    'local_error_kind' => $readErrorKind,
                    'state' => $failureState,
                ]);
                $empty['state'] = $failureState;
                $empty['state_message'] = 'Marcaciones no disponibles. ' . $failureMessage;
                return $empty;
            }
            $pagination = $result['pagination'];
            if ($firstPagination === null) {
                $firstPagination = $pagination;
            } elseif ((int) $pagination['total_records'] !== (int) $firstPagination['total_records']
                || (int) $pagination['total_pages'] !== (int) $firstPagination['total_pages']) {
                $logPaginationDiagnostic('page_totals_inconsistent', [
                    'page' => $page,
                    'pages_read' => $pagesRead,
                    'first_total_records' => (int) $firstPagination['total_records'],
                    'page_total_records' => (int) $pagination['total_records'],
                    'first_total_pages' => (int) $firstPagination['total_pages'],
                    'page_total_pages' => (int) $pagination['total_pages'],
                    'events_collected' => count($events),
                    'state' => 'unavailable',
                ]);
                $empty['state'] = 'unavailable';
                $empty['state_message'] = 'El resultado cambió mientras se leían sus páginas. Vuelve a consultar para obtener una lectura coherente.';
                $empty['state_message'] = 'Marcaciones no disponibles. ' . $empty['state_message'];
                return $empty;
            }
            foreach ($result['events'] as $event) {
                $events[] = $event;
            }
            $logPaginationDiagnostic('page_accumulated', [
                'page' => $page,
                'pages_read' => $pagesRead,
                'total_records' => (int) $pagination['total_records'],
                'total_pages' => (int) $pagination['total_pages'],
                'events_on_page' => count($result['events']),
                'events_collected' => count($events),
            ]);
            $requiredPages = max(1, (int) ($firstPagination['total_pages'] ?? 0));
            if ($page >= $requiredPages) {
                $readComplete = count($events) === (int) ($firstPagination['total_records'] ?? 0);
                break;
            }
        }
        if (!$readComplete) {
            $logPaginationDiagnostic('read_incomplete', [
                'pages_read' => $pagesRead,
                'total_records' => (int) ($firstPagination['total_records'] ?? 0),
                'total_pages' => (int) ($firstPagination['total_pages'] ?? 0),
                'events_collected' => count($events),
                'read_complete' => false,
                'state' => 'unavailable',
            ]);
            $empty['state'] = 'unavailable';
            $empty['state_message'] = 'La consulta excede el límite seguro de páginas o cambió durante la lectura; no se muestran resultados parciales.';
            $empty['state_message'] = 'Marcaciones no disponibles. ' . $empty['state_message'];
            return $empty;
        }

        $logPaginationDiagnostic('read_complete', [
            'pages_read' => $pagesRead,
            'total_records' => (int) ($firstPagination['total_records'] ?? 0),
            'total_pages' => (int) ($firstPagination['total_pages'] ?? 0),
            'events_collected' => count($events),
            'read_complete' => true,
            'state' => 'success',
        ]);

        try {
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
        $programmingRead = ChecadorRolComodinesService::authorizeCanonicalProgramming($workplaceId, 'read');
        if (!empty($programmingRead['allowed'])) {
            $scheduledComodines = ChecadorRolComodinesService::getCanonicalScheduledWorkersForDate(
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
            'roster_fallback_allowed' => false,
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
        } catch (\Throwable $exception) {
            $logPaginationDiagnostic('post_pagination_exception', [
                'pages_read' => $pagesRead,
                'total_records' => (int) ($firstPagination['total_records'] ?? 0),
                'total_pages' => (int) ($firstPagination['total_pages'] ?? 0),
                'events_collected' => count($events),
                'read_complete' => $readComplete,
                'exception_class' => $exception::class,
                'exception_code' => substr((string) $exception->getCode(), 0, 32),
                'exception_file' => basename($exception->getFile()),
                'exception_line' => $exception->getLine(),
                'state' => 'error',
            ]);
            throw $exception;
        }
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
