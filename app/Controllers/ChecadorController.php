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
use App\Services\ChecadorDailyReadService;
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
        $query = Request::query();
        if (($query['schedule_history'] ?? '') === '1' || ($query['schedule_week'] ?? '') === '1') {
            // Preserve the existing dedicated schedule workspaces, which use
            // query flags for backwards-compatible navigation.
            $this->renderDashboardWorkspace(null);
            return;
        }

        // The module entry route is a lightweight directory. Operational
        // attendance is available only through its dedicated workspace route.
        $this->renderModuleDirectory();
    }

    public function dailyAttendance(): void
    {
        $this->renderDashboardWorkspace('daily-attendance');
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
        $hasExplicitWorkplace = array_key_exists('workplace_id', $query);
        $rawWorkplace = $query['workplace_id'] ?? null;
        $workplaceId = self::positiveInteger($rawWorkplace);
        if ($hasExplicitWorkplace && $rawWorkplace !== '' && $workplaceId === null) {
            self::renderAccessDenied('invalid_station_selection', null);
            return;
        }

        if ($workplaceId !== null) {
            $selectedAccess = ChecadorAccessService::authorize($workplaceId);
            if (empty($selectedAccess['allowed'])
                || !ModuleStationService::setChecadorWorkplaceContext($workplaceId)) {
                self::renderAccessDenied((string) ($selectedAccess['reason'] ?? 'requested_station_out_of_scope'), $workplaceId);
                return;
            }
        } elseif ($hasExplicitWorkplace) {
            // An explicit empty selection must not resurrect a previous module context.
            ModuleStationService::setContext(ChecadorAccessService::MODULE_KEY, null, null);
        } else {
            $moduleContext = ModuleStationService::getContext(ChecadorAccessService::MODULE_KEY);
            $contextWorkplace = self::positiveInteger($moduleContext['id_depto'] ?? $moduleContext['id_estacion'] ?? null);
            if ($contextWorkplace !== null && in_array($contextWorkplace, array_map('intval', array_column($workplaces, 'id')), true)) {
                $workplaceId = $contextWorkplace;
            } elseif ($workplaces !== []) {
                $workplaceId = (int) $workplaces[0]['id'];
            }

            if ($workplaceId !== null) {
                $selectedAccess = ChecadorAccessService::authorize($workplaceId);
                if (empty($selectedAccess['allowed'])
                    || !ModuleStationService::setChecadorWorkplaceContext($workplaceId)) {
                    self::renderAccessDenied((string) ($selectedAccess['reason'] ?? 'requested_station_out_of_scope'), $workplaceId);
                    return;
                }
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
        $moduleNavigation = $this->buildModuleNavigation($workplaceId, $access);

        View::render(
            'departamento-operativo/2-recursos-humanos/checador/index',
            [
                'title' => 'Checador',
                'workplaces' => $workplaces,
                'selectedWorkplaceId' => $workplaceId,
                'selectedWorkplaceName' => $workplaceName,
                'selectedWorkplaceKind' => $workplaceKind,
                'moduleNavigation' => $moduleNavigation,
                'isModuleDirectory' => true,
                'moduleStationKey' => ChecadorAccessService::MODULE_KEY,
                'links' => [],
                'scripts' => [
                    '/assets/js/core/module-station-selector.js',
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.module-station.init.js?v=20261021-1',
                ],
                'help' => false,
            ],
            'departamento-operativo'
        );
    }

    /** Build the ten institutional cards; each optional capability uses its existing read gate. */
    private function buildModuleNavigation(?int $workplaceId, array $access): array
    {
        $base = '/departamento-operativo/recursos-humanos/checador';
        $context = $workplaceId === null ? [] : ['workplace_id' => $workplaceId];
        $queryString = static fn(string $path, array $params): string => $path
            . ($params === [] ? '' : '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
        $scheduleRead = $workplaceId !== null
            && (ChecadorAccessService::authorizeScheduling($workplaceId, 'leer')['allowed'] ?? false) === true;
        $temporaryRead = $workplaceId !== null
            && in_array($workplaceId, array_map('intval', array_column($access['station_workplaces'] ?? [], 'id')), true)
            && (ChecadorAccessService::authorizeTemporaryAccess($workplaceId, 'leer')['allowed'] ?? false) === true;
        $payrollRead = $workplaceId !== null
            && (new ChecadorPayrollComparisonReadService())->authorization($workplaceId)['allowed'];

        $entries = [
            [
                'label' => 'Asistencia del día',
                'description' => 'Consulta las checadas, horarios y estado de asistencia del personal de tu estación.',
                'utility' => 'Personal · Marcaciones · Incidencias',
                'action' => 'Ver asistencia',
                'icon' => 'ti-calendar-check',
                'href' => $queryString($base . '/asistencia', $context),
                'available' => true,
            ],
            [
                'label' => 'Programación semanal',
                'description' => 'Consulta y organiza los turnos del personal de lunes a domingo.',
                'utility' => 'Turnos · Descansos · Asignaciones',
                'action' => 'Ver programación',
                'icon' => 'ti-calendar-week',
                'href' => $queryString($base, $context + ['schedule_week' => '1']),
                'available' => $scheduleRead,
                'unavailable' => 'Disponible con autorización de lectura de programación.',
            ],
            [
                'label' => 'Horarios y turnos',
                'description' => 'Revisa los horarios disponibles, las asignaciones vigentes y su historial.',
                'utility' => 'Horarios · Vigencias · Historial',
                'action' => 'Consultar horarios',
                'icon' => 'ti-clock-hour-4',
                'href' => $queryString($base, $context + ['schedule_history' => '1']),
                'available' => $scheduleRead,
                'unavailable' => 'Disponible con autorización de lectura de programación.',
            ],
            [
                'label' => 'Jornadas laborales',
                'description' => 'Consulta jornadas evaluadas, tiempos registrados y situaciones que requieren revisión.',
                'utility' => 'Jornadas · Horas · Seguimiento',
                'action' => 'Consultar jornadas',
                'icon' => 'ti-clipboard-check',
                'href' => $queryString($base . '/jornadas', $context),
                'available' => true,
            ],
            [
                'label' => 'Incidencias',
                'description' => 'Revisa las incidencias del personal, su estado y el seguimiento de cada caso.',
                'utility' => 'Registros · Estados · Historial',
                'action' => 'Ver incidencias',
                'icon' => 'ti-alert-circle',
                'href' => $queryString($base . '/incidencias', $context),
                'available' => true,
            ],
            [
                'label' => 'Ajustes administrativos',
                'description' => 'Consulta los ajustes documentados y las referencias asociadas a las evaluaciones.',
                'utility' => 'Referencias · Motivos · Consulta',
                'action' => 'Consultar ajustes',
                'icon' => 'ti-adjustments',
                'href' => $queryString($base . '/ajustes', $context),
                'available' => true,
            ],
            [
                'label' => 'PIN temporal',
                'description' => 'Consulta solicitudes de acceso temporal para trabajadores y su estado.',
                'utility' => 'Solicitudes · Accesos · Seguimiento',
                'action' => 'Ver solicitudes',
                'icon' => 'ti-key',
                'href' => $queryString($base . '/acceso-temporal', $context),
                'available' => $temporaryRead,
                'unavailable' => 'No disponible para este centro o perfil.',
            ],
            [
                'label' => 'Reportes',
                'description' => 'Consulta marcaciones por estación, trabajador y periodo, con opción de exportación.',
                'utility' => 'Marcaciones · Filtros · CSV',
                'action' => 'Abrir reportes',
                'icon' => 'ti-file-report',
                'href' => $queryString($base . '/reporte', $context),
                'available' => true,
            ],
            [
                'label' => 'Comparación con Nómina',
                'description' => 'Revisa información de asistencia y referencias de nómina por trabajador y periodo.',
                'utility' => 'Periodos · Referencias · Consulta',
                'action' => 'Ver comparación',
                'icon' => 'ti-scale',
                'href' => $queryString($base . '/comparacion-nomina', $context),
                'available' => $payrollRead,
                'unavailable' => 'No disponible para este centro o perfil.',
            ],
            [
                'label' => 'Configuración',
                'description' => 'Accede a la configuración de horarios, reglas de asistencia, puestos y lectores.',
                'utility' => 'Horarios · Reglas · Personal · Lectores',
                'action' => 'Abrir configuración',
                'icon' => 'ti-settings',
                'href' => $queryString($base . '/configuracion', $context),
                'available' => true,
            ],
        ];

        return $entries;
    }

    private function renderDashboardWorkspace(?string $workspace): void
    {
        $query = Request::query();
        $scheduleHistoryMode = $workspace === null && ($query['schedule_history'] ?? '') === '1';
        $scheduleWeekMode = $workspace === null && ($query['schedule_week'] ?? '') === '1';
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
        $requestedWorker = self::positiveInteger($query['worker_id'] ?? null);
        $snapshotRefProvided = array_key_exists('snapshot_ref', $query);
        $snapshotRef = self::safeSnapshotRef($query['snapshot_ref'] ?? null);
        $access = ChecadorAccessService::authorize(null);
        if (!$access['allowed']) {
            self::renderAccessDenied((string) ($access['reason'] ?? 'authorization_lookup_failed'), $requestedWorkplace);
            return;
        }
        $workplaces = $access['workplaces'];
        $rawWorkplace = $query['workplace_id'] ?? null;
        if ($hasExplicitWorkplace && $rawWorkplace !== '' && $requestedWorkplace === null) {
            self::renderAccessDenied('invalid_station_selection', null);
            return;
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

        // Temporary Access has a separate read ACL and applies only to an
        // authorized physical station. Re-check it before any queue request.
        if ($workspace === 'temporary-access') {
            $temporaryAccessRead = ChecadorAccessService::authorizeTemporaryAccess($requestedWorkplace, 'leer');
            if (!($temporaryAccessRead['allowed'] ?? false)) {
                self::renderAccessDenied((string) ($temporaryAccessRead['reason'] ?? 'temporary_access_permission_denied'), $requestedWorkplace);
                return;
            }
        }
        // Demo fixtures only replace API responses after the existing Portal3
        // authentication and workplace-scope check above has succeeded.
        $demoMode = ChecadorDailyReadService::demoModeEnabled();
        $realApiPostOperationsEnabled = ChecadorDailyReadService::realApiPostOperationsEnabled();
        $apiPostOperationsEnabled = $demoMode || $realApiPostOperationsEnabled;
        $canViewTemporaryPin = $workspace === 'temporary-access'
            && !$demoMode
            && $realApiPostOperationsEnabled
            && (ChecadorAccessService::authorizeTemporaryAccess($requestedWorkplace, 'editar')['allowed'] ?? false) === true;
        $demoScenario = ChecadorDailyReadService::configuredDemoScenario();

        $date = isset($query['date']) && is_string($query['date']) ? trim($query['date']) : '';
        $defaultTimezoneResolution = null;
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
                $candidateDate = gmdate('Y-m-d');
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
                                new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
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
            }
        }
        if ($scheduleHistoryMode) {
            $rawHistoryPage = self::positiveInteger($query['schedule_history_page'] ?? 1);
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
        $attendanceRows = [];
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
                try {
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
                        $timezoneResult = $defaultTimezoneResolution
                            ?? $reader->resolveWorkplaceLocalDay($user, $requestedWorkplace, $date);
                        if (!$timezoneResult['ok']) {
                            [$state, $stateMessage] = self::stateForError($timezoneResult['error']['kind']);
                        } else {
                            $timezone = $timezoneResult['data'];
                            $snapshotResult = $reader->createDailyPunchSnapshot($user, $requestedWorkplace, $timezone, self::creationRequestUuid());
                            if (!$snapshotResult['ok']) {
                                [$state, $stateMessage] = self::stateForError($snapshotResult['error']['kind']);
                                $timezone = null;
                            } else {
                                $level2Result = $reader->getLevel2WindowStatus($user, $requestedWorkplace, $timezone);
                                if (!$level2Result['ok']) {
                                    [$state, $stateMessage] = self::stateForLevel2Error($level2Result['error']['kind']);
                                    $timezone = null;
                                } else {
                                    $level2Meta = $level2Result['data'];
                                    $level2Loaded = true;
                                    $snapshotRef = self::newSnapshotRef();
                                    $snapshotState = [
                                        'user_id' => (int) $user->id,
                                        'workplace_id' => $requestedWorkplace,
                                        'date' => $date,
                                        'timezone' => $timezone,
                                        'snapshot' => $snapshotResult['data'],
                                        'cursors' => [1 => null],
                                    ];
                                    self::saveSnapshotState($snapshotRef, $snapshotState);
                                }
                            }
                        }
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
                'title' => $workspace === null ? 'Checador' : ($workspaceTitles[$workspace] ?? 'Checador'),
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
                'snapshotRef' => $snapshotRef,
                'demoWorkerFilterNoMatches' => $demoWorkerFilterNoMatches,
                'timezone' => $timezone,
                'events' => $events,
                'attendanceRows' => array_values($attendanceRows),
                'attendanceCoverage' => $attendanceCoverage,
                'attendanceRoster' => $attendanceRoster,
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
                    ? ['/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css']
                    : [],
                'scripts' => [
                    ...($workspace === 'daily-attendance' ? [
                        '/assets/js/vendor.min.js',
                        '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                        '/assets/libs/datatables.net-bs5/js/dataTables.bootstrap5.min.js',
                        '/assets/js/departamento-operativo/2-recursos-humanos/checador.attendance.datatable.init.js?v=20261023-1',
                    ] : []),
                    '/assets/js/core/module-station-selector.js',
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.module-station.init.js?v=20261021-1',
                    ...(($workspace === null || $workspace === 'daily-attendance') ? ['/assets/js/departamento-operativo/2-recursos-humanos/checador.filters.init.js?v=20261021-2'] : []),
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

        $configuration = null;
        if ($workplaceId !== null && $user !== null && !$historyInputInvalid) {
            try {
                $configuration = (new ChecadorConfigurationReadService())->read(
                    $user,
                    $workplaceId,
                    $demoMode,
                    $workerId,
                    $date
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
                'workerId' => $workerId,
                'date' => $date,
                'historyInputInvalid' => $historyInputInvalid,
                'temporaryAccessAvailable' => $temporaryAccessAvailable,
                'canReadScheduling' => $canReadScheduling,
                'links' => [],
                'scripts' => ['/assets/js/core/module-station-selector.js'],
                'moduleStationKey' => ChecadorAccessService::MODULE_KEY,
                'help' => false,
            ],
            'departamento-operativo'
        );
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
            $scheduleAccess = ChecadorAccessService::authorizeScheduling($workplaceId, 'leer');
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
        } elseif ($workplaceId !== null && $workerId !== null && self::isCivilDate($date) && $currentUser !== null) {
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

        View::render(
            'departamento-operativo/2-recursos-humanos/checador/schedule-history',
            [
                'title' => 'Vigencias de horarios',
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
                'demoMode' => $demoMode,
                'demoScenario' => $demoScenario,
                'links' => [],
                'scripts' => [
                    '/assets/js/core/module-station-selector.js',
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.module-station.init.js?v=20261021-1',
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
        $timezone = '';
        $dates = \App\Services\ChecadorWeeklyScheduleService::dates($weekStart);
        $validWeek = $dates !== [] && \App\Services\ChecadorWeeklyScheduleService::monday($weekStart) === $weekStart;
        $errorMessage = '';
        $workers = [];
        $matrix = ['dates' => [], 'workers' => []];
        $templates = [];
        $coverage = 'not_loaded';
        $reader = null;
        $currentUser = Auth::user();
        if (!$validWeek) {
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
                    }
                }
                if ($errorMessage === '' && !\App\Services\ChecadorWeeklyScheduleService::isCurrentOrFutureWeek($weekStart, $timezone)) {
                    $errorMessage = 'Sólo se pueden consultar semanas actuales o futuras según la zona horaria laboral del centro.';
                }
                if ($errorMessage === '') {
                    $roster = (new \App\Services\ChecadorAttendanceRosterService())->workersForScheduleDate($workplaceId, $weekStart, $timezone, $demoMode);
                    $workers = $roster['workers'];
                    $coverage = (string) ($roster['status'] ?? 'unknown');
                    if (!in_array($coverage, ['ready', 'demo_fixture'], true)) $errorMessage = (string) ($roster['message'] ?? 'El padrón no está disponible.');
                    $templateResult = $reader->getShiftTemplates($currentUser, 1, 100);
                    if (($templateResult['ok'] ?? false) !== true) {
                        $errorMessage = 'El catálogo contractual de turnos no está disponible; no se puede ofrecer una asignación.';
                    } else {
                        $templates = $templateResult['data']['items'];
                        if (count($templates) === 100) $coverage = 'partial';
                    }
                    $assignmentsByWorker = [];
                    if ($errorMessage === '') {
                        foreach (array_slice($workers, 0, 50) as $worker) {
                            $workerId = (int) $worker['worker_id'];
                            $assignmentResult = $reader->getShiftAssignments($currentUser, $workerId, $workplaceId, 1, 200);
                            if (($assignmentResult['ok'] ?? false) !== true) {
                                $assignmentsByWorker[$workerId] = [];
                                $coverage = 'partial';
                                continue;
                            }
                            $assignmentsByWorker[$workerId] = $assignmentResult['data']['items'];
                            if (count($assignmentResult['data']['items']) === 200) $coverage = 'partial';
                        }
                        if (count($workers) > 50) $coverage = 'partial';
                        $listedTemplatesById = [];
                        foreach ($templates as $template) $listedTemplatesById[(int) $template['id']] = $template;
                        $usedTemplateIds = [];
                        foreach ($assignmentsByWorker as $items) foreach ($items as $assignment) {
                            if (($assignment['id_plantilla'] ?? null) !== null) $usedTemplateIds[(int) $assignment['id_plantilla']] = true;
                        }
                        $templatesById = [];
                        foreach (array_slice(array_keys($usedTemplateIds), 0, 50) as $templateId) {
                            $template = $listedTemplatesById[$templateId] ?? null;
                            if ($template === null) {
                                $coverage = 'partial';
                                continue;
                            }
                            $detail = $reader->getShiftTemplateVersion($currentUser, (string) $template['clave'], (int) $template['version']);
                            if (($detail['ok'] ?? false) === true) $templatesById[$templateId] = array_merge($template, $detail['data']);
                            else $coverage = 'partial';
                        }
                        if (count($usedTemplateIds) > 50) $coverage = 'partial';
                        $matrix = \App\Services\ChecadorWeeklyScheduleService::matrix($workers, $assignmentsByWorker, $templatesById, $weekStart);
                    }
                }
            } catch (\Throwable) {
                $errorMessage = 'La programación semanal no está disponible. No se consultó ninguna fuente legacy como sustituto.';
            }
        }

        $feedback = Session::pull('checador_schedule_feedback', null);
        if (!is_array($feedback) || (int) ($feedback['workplace_id'] ?? 0) !== (int) ($workplaceId ?? 0)
            || (string) ($feedback['week_start'] ?? '') !== $weekStart) $feedback = null;
        $canAssign = $workplaceId !== null && !$demoMode && ChecadorDailyReadService::realApiPostOperationsEnabled()
            && in_array($coverage, ['ready', 'demo_fixture'], true)
            && (ChecadorAccessService::authorizeScheduling($workplaceId, 'editar')['allowed'] ?? false);
        $legalReference = \App\Services\ChecadorLaborPolicy::referenceForDate($weekStart);
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Checador', '/departamento-operativo/recursos-humanos/checador');
        Breadcrumb::add('Programación semanal', '');
        View::render(
            'departamento-operativo/2-recursos-humanos/checador/schedule-week',
            [
                'title' => 'Programación semanal de turnos',
                'workplaces' => $workplaces,
                'selectedWorkplaceId' => $workplaceId,
                'selectedWorkplaceName' => $workplaceName,
                'weekStart' => $weekStart,
                'weekDates' => $matrix['dates'],
                'scheduleRows' => $matrix['workers'],
                'templates' => $templates,
                'timezone' => $timezone,
                'coverage' => $coverage,
                'errorMessage' => $errorMessage,
                'feedback' => $feedback,
                'canAssign' => $canAssign,
                'demoMode' => $demoMode,
                'legalReference' => $legalReference,
                'dailyLimits' => \App\Services\ChecadorLaborPolicy::generalDailyReferences(),
                'links' => [],
                'scripts' => ['/assets/js/core/module-station-selector.js', '/assets/js/departamento-operativo/2-recursos-humanos/checador.module-station.init.js?v=20261021-1'],
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
        $workplaceId = self::positiveInteger($input['workplace_id'] ?? null);
        $workerId = self::positiveInteger($input['worker_id'] ?? null);
        $targetDate = is_string($input['target_date'] ?? null) ? trim($input['target_date']) : '';
        $weekStart = is_string($input['week_start'] ?? null) ? trim($input['week_start']) : '';
        $templateValue = is_string($input['template_id'] ?? null) ? trim($input['template_id']) : '';
        $message = 'No se realizó la asignación.';
        $type = 'warning';
        $demoMode = ChecadorDailyReadService::demoModeEnabled();
        $user = Auth::user();
        if ($workplaceId === null || $workerId === null || !self::isCivilDate($targetDate)
            || !in_array((string) ($input['confirm_action'] ?? ''), ['1', 'on'], true)
            || \App\Services\ChecadorWeeklyScheduleService::monday($weekStart) !== $weekStart
            || !in_array($targetDate, array_column(\App\Services\ChecadorWeeklyScheduleService::dates($weekStart), 'date'), true)) {
            $message = 'Revisa el centro, la semana, el día y la confirmación solicitada.';
        } elseif ($demoMode) {
            $message = 'La demostración es de consulta; no guarda asignaciones sintéticas ni llama al API.';
        } elseif (!ChecadorDailyReadService::realApiPostOperationsEnabled()) {
            $message = 'La escritura real de asignaciones permanece protegida por CHECADOR_API_REAL_POSTS_ENABLED.';
        } elseif (!(ChecadorAccessService::authorizeScheduling($workplaceId, 'editar')['allowed'] ?? false)) {
            self::renderAccessDenied('scheduling_permission_denied', $workplaceId);
            return;
        } elseif ($user === null) {
            $message = 'La sesión ya no es válida.';
        } else {
            try {
                $reader = new ChecadorDailyReadService();
                $timezoneResult = $reader->resolveWorkplaceLocalDay($user, $workplaceId, $targetDate);
                $timezone = ($timezoneResult['ok'] ?? false) === true
                    ? (string) ($timezoneResult['data']['timezone'] ?? '')
                    : '';
                if (!\App\Services\ChecadorOperationalPeriod::isIanaTimezone($timezone)
                    || !\App\Services\ChecadorWeeklyScheduleService::isCurrentOrFutureWeek($weekStart, $timezone)) {
                    $message = 'No se pudo confirmar la zona horaria laboral o la semana ya no es actual/futura; no se envió la asignación.';
                    throw new \DomainException('Workplace timezone or week boundary is unavailable.');
                }
                $workerActive = \App\Models\Operativo\RhPersonal::query()
                    ->where('id', $workerId)
                    ->where('estado', 1)
                    ->exists();
                if (!$workerActive) {
                    $message = 'El trabajador no está disponible en el padrón activo de Portal3; no se envió la asignación.';
                    throw new \DomainException('The worker is not in the current active roster.');
                }
                $membershipInstant = (new \DateTimeImmutable($targetDate . ' 12:00:00', new \DateTimeZone($timezone)))
                    ->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
                $membership = (new \App\Services\WorkerWorkplaceAssignmentResolver())->resolveAssignments($workerId, $membershipInstant);
                $belongs = false;
                foreach ($membership['assignments'] ?? [] as $assignment) {
                    if ((int) ($assignment['workplace_id'] ?? 0) === $workplaceId) $belongs = true;
                }
                if (!in_array((string) ($membership['status'] ?? ''), ['RESOLVED_SINGLE', 'RESOLVED_MULTIPLE'], true) || !$belongs) {
                    $message = 'No se pudo confirmar que el trabajador pertenezca canónicamente a este centro en la fecha seleccionada.';
                } else {
                    $templates = $reader->getShiftTemplates($user, 1, 100);
                    $templateId = $templateValue === 'REST' ? null : self::positiveInteger($templateValue);
                    $templateAllowed = $templateId === null && $templateValue === 'REST';
                    foreach ($templates['data']['items'] ?? [] as $template) {
                        if ((int) ($template['id'] ?? 0) === (int) $templateId && (int) ($template['es_activo'] ?? 0) === 1) $templateAllowed = true;
                    }
                    if (!($templates['ok'] ?? false) || !$templateAllowed) {
                        $message = 'La plantilla seleccionada no está disponible o activa en el catálogo API.';
                    } else {
                        $weekday = \App\Services\ChecadorWeeklyScheduleService::weekdayFor($targetDate);
                        $existing = $reader->getShiftAssignments($user, $workerId, $workplaceId, 1, 200, $targetDate);
                        if (!($existing['ok'] ?? false)) {
                            $message = 'No se pudo consultar la asignación existente; por seguridad no se envió el cambio.';
                        } else {
                            $effective = array_values(array_filter($existing['data']['items'], static fn(array $row): bool =>
                                ($row['dia_semana'] ?? '') === $weekday && (int) ($row['es_activo'] ?? 0) === 1
                                && (string) ($row['valido_desde'] ?? '') <= $targetDate
                                && (($row['valido_hasta'] ?? null) === null || $targetDate < (string) $row['valido_hasta'])
                            ));
                            $alreadySame = count($effective) === 1 && (($effective[0]['id_plantilla'] ?? null) === $templateId);
                            if ($alreadySame) {
                                $message = 'La asignación solicitada ya aparece vigente en la API; no se creó un registro duplicado.';
                                $type = 'info';
                            } elseif ($effective !== []) {
                                $message = 'Ya existe una asignación vigente para ese día. No se cierra ni sobrescribe; consulta el historial y solicita una corrección autorizada.';
                            } else {
                                $validTo = (new \DateTimeImmutable($targetDate, new \DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d');
                                $assignment = [
                                    'id_personal' => $workerId, 'id_estacion' => $workplaceId, 'id_plantilla' => $templateId,
                                    'dia_semana' => $weekday, 'valido_desde' => $targetDate, 'valido_hasta' => $validTo,
                                ];
                                $result = $reader->createShiftAssignment($user, $assignment, self::newIdempotencyKey());
                                $readback = $reader->getShiftAssignments($user, $workerId, $workplaceId, 1, 200, $targetDate);
                                $confirmed = false;
                                foreach ($readback['data']['items'] ?? [] as $row) {
                                    if ((int) ($row['id_personal'] ?? 0) === $workerId && (int) ($row['id_estacion'] ?? 0) === $workplaceId
                                        && (string) ($row['dia_semana'] ?? '') === $weekday && ($row['id_plantilla'] ?? null) === $templateId
                                        && (string) ($row['valido_desde'] ?? '') === $targetDate
                                        && (string) ($row['valido_hasta'] ?? '') === $validTo && (int) ($row['es_activo'] ?? 0) === 1) {
                                        $confirmed = true;
                                        break;
                                    }
                                }
                                if ($confirmed) {
                                    $message = 'Asignación individual confirmada por lectura posterior de la API.';
                                    $type = 'success';
                                } elseif (!($result['ok'] ?? false)) {
                                    $message = 'La API no confirmó la asignación y la lectura posterior no encontró el registro esperado.';
                                } else {
                                    $message = 'La respuesta de escritura no pudo confirmarse mediante lectura posterior; no se declara guardado.';
                                }
                            }
                        }
                    }
                }
            } catch (\Throwable) {
                if ($message === 'No se realizó la asignación.') {
                    $message = 'No fue posible comprobar la pertenencia, guardar o releer la asignación. No se declara éxito.';
                }
            }
        }
        Session::set('checador_schedule_feedback', [
            'type' => $type, 'message' => $message, 'workplace_id' => (int) ($workplaceId ?? 0), 'week_start' => $weekStart,
        ]);
        $query = ['schedule_week' => '1'];
        if ($workplaceId !== null) $query['workplace_id'] = $workplaceId;
        if ($weekStart !== '') $query['week_start'] = $weekStart;
        self::redirect('/departamento-operativo/recursos-humanos/checador?' . http_build_query($query));
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
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
        Breadcrumb::add('Checador', '/departamento-operativo/recursos-humanos/checador');
        Breadcrumb::add('Reporte de marcaciones', '');

        $query = Request::query();
        $hasExplicitWorkplace = array_key_exists('workplace_id', $query);
        $requestedWorkplace = self::positiveInteger($query['workplace_id'] ?? null);
        $fromDate = is_string($query['from_date'] ?? null) ? trim($query['from_date']) : '';
        $toDate = is_string($query['to_date'] ?? null) ? trim($query['to_date']) : '';
        $workerIdInput = is_string($query['worker_id'] ?? null) ? trim($query['worker_id']) : '';
        $workerId = $workerIdInput === '' ? null : self::positiveInteger($workerIdInput);
        $sourceWorkplaceCandidate = self::positiveInteger($query['source_workplace_id'] ?? null);
        $sourceWorkDateCandidate = is_string($query['source_work_date'] ?? null) ? trim($query['source_work_date']) : '';
        $sourceWorkerCandidate = self::positiveInteger($query['source_worker_id'] ?? null);
        $sort = ($query['dt_order_direction'] ?? $query['sort'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $tableSearch = is_string($query['dt_search'] ?? null) ? trim($query['dt_search']) : '';
        $tableOrderColumn = self::nonNegativeInteger($query['dt_order_column'] ?? 1) ?? 1;
        if (!in_array($tableOrderColumn, [1, 2, 3, 4], true)) {
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
        $rawWorkplace = $query['workplace_id'] ?? null;
        if ($hasExplicitWorkplace && $rawWorkplace !== '' && $requestedWorkplace === null) {
            self::renderAccessDenied('invalid_station_selection', null);
            return;
        }
        if ($requestedWorkplace !== null
            && !in_array($requestedWorkplace, array_column($workplaces, 'id'), true)) {
            self::renderAccessDenied('requested_station_out_of_scope', $requestedWorkplace);
            return;
        }
        $sourceContextValid = $sourceWorkplaceCandidate !== null
            && in_array($sourceWorkplaceCandidate, array_column($workplaces, 'id'), true)
            && self::isCivilDate($sourceWorkDateCandidate);
        $sourceWorkplaceId = $sourceContextValid ? $sourceWorkplaceCandidate : null;
        $sourceWorkDate = $sourceContextValid ? $sourceWorkDateCandidate : '';
        $sourceWorkerId = $sourceContextValid ? $sourceWorkerCandidate : null;
        $selectedWorkplaceForView = $requestedWorkplace
            ?? ($hasExplicitWorkplace ? null : $sourceWorkplaceId);
        if ($selectedWorkplaceForView === null && !$hasExplicitWorkplace) {
            $moduleContext = ModuleStationService::getContext(ChecadorAccessService::MODULE_KEY);
            $contextWorkplace = self::positiveInteger($moduleContext['id_depto'] ?? $moduleContext['id_estacion'] ?? null);
            if ($contextWorkplace !== null && in_array($contextWorkplace, array_column($workplaces, 'id'), true)) {
                $selectedWorkplaceForView = $contextWorkplace;
            } elseif ($workplaces !== []) {
                $selectedWorkplaceForView = (int) $workplaces[0]['id'];
            }
        }
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
        } elseif ($hasExplicitWorkplace) {
            ModuleStationService::setContext(ChecadorAccessService::MODULE_KEY, null, null);
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
                            'workplace_id' => $selectedWorkplaceForView,
                            'period' => 'year',
                            'year' => $periodYear,
                            'month' => $monthNumber,
                        ]),
                ];
            }
        }

        $state = 'idle';
        $stateMessage = '';
        $events = [];
        $pagination = null;
        $selectedWorkplaceName = '';
        foreach ($workplaces as $workplace) {
            if ((int) ($workplace['id'] ?? 0) === $selectedWorkplaceForView) {
                $selectedWorkplaceName = (string) ($workplace['nombre'] ?? '');
                break;
            }
        }

        // A daily-screen source context may preselect station/worker, but only
        // explicit UTC date criteria or export initiate a raw SQL query.
        $hasAnyFilter = $fromDate !== '' || $toDate !== '' || $workerIdInput !== '' || $exportCsv || $periodError;
        if ($hasAnyFilter) {
            if ($periodError) {
                $state = 'error';
                $stateMessage = 'El periodo solicitado no es válido. El año en curso se consulta por segmentos mensuales y no permite seleccionar meses futuros.';
            } elseif ($requestedWorkplace === null
                || !self::isCivilDate($fromDate)
                || !self::isCivilDate($toDate)
                || $fromDate > $toDate
                || ($workerIdInput !== '' && $workerId === null)) {
                $state = 'error';
                $stateMessage = 'Selecciona una estación autorizada, un rango de fechas válido y, si aplica, un identificador de colaborador válido.';
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
                                        $sort
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
                                            $sort
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
                'title' => 'Checador',
                'demoMode' => ChecadorDailyReadService::demoModeEnabled(),
                'moduleStationKey' => ChecadorAccessService::MODULE_KEY,
                'workplaces' => $workplaces,
                'selectedWorkplaceId' => $selectedWorkplaceForView,
                'selectedWorkplaceName' => $selectedWorkplaceName,
                'fromDate' => $fromDate,
                'toDate' => $toDate,
                'workerId' => $selectedWorkerForView,
                'sourceWorkplaceId' => $sourceWorkplaceId,
                'sourceWorkDate' => $sourceWorkDate,
                'sourceWorkerId' => $sourceWorkerId,
                'selectedPerPage' => 25,
                'sort' => $sort,
                'tableSearch' => $tableSearch,
                'tableOrderColumn' => $tableOrderColumn,
                'periodPreset' => $periodPreset,
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
                ],
                'scripts' => [
                    '/assets/js/vendor.min.js',
                    '/assets/js/core/module-station-selector.js',
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.module-station.init.js?v=20261022-2',
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.filters.init.js?v=20261021-2',
                    '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                    '/assets/js/departamento-operativo/2-recursos-humanos/checador.datatable.init.js?v=20261022-1',
                ],
                'help' => false,
            ],
            'departamento-operativo'
        );
    }

    /** DataTables endpoint; each request re-resolves the user and station scope. */
    public function punchReportData(): void
    {
        $query = Request::query();
        $draw = self::nonNegativeInteger($query['draw'] ?? null);
        if ($draw === null) {
            self::punchReportJsonError(422, 'La solicitud de la tabla no es válida.', 0);
        }

        $requestedWorkplace = self::positiveInteger($query['workplace_id'] ?? null);
        $fromDate = is_string($query['from_date'] ?? null) ? trim($query['from_date']) : '';
        $toDate = is_string($query['to_date'] ?? null) ? trim($query['to_date']) : '';
        $workerInput = is_string($query['worker_id'] ?? null) ? trim($query['worker_id']) : '';
        $workerId = $workerInput === '' ? null : self::positiveInteger($workerInput);
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
        $selectedWorkplace = null;
        foreach ($access['workplaces'] ?? [] as $workplace) {
            if ((int) ($workplace['id'] ?? 0) === $requestedWorkplace) {
                $selectedWorkplace = $workplace;
                break;
            }
        }
        if ($requestedWorkplace === null || $selectedWorkplace === null) {
            self::punchReportJsonError(403, 'La estación solicitada no está autorizada.', $draw);
        }

        if (!self::isCivilDate($fromDate)
            || !self::isCivilDate($toDate)
            || $fromDate > $toDate
            || ($workerInput !== '' && $workerId === null)
            || $start === null
            || $length === null
            || !in_array($length, ChecadorRawPunchReadService::DATATABLE_PAGE_SIZES, true)
            || $regexSearch
            || $orderColumn === null
            || !in_array($orderColumn, [1, 2, 3, 4], true)
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

        $result = (new ChecadorRawPunchReadService())->readDataTablePage(
            $requestedWorkplace,
            $fromUtc,
            $toUtc,
            $workerId,
            $start,
            $length,
            $search,
            $orderColumn,
            $direction
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
        foreach ($result['events'] as $event) {
            $resolvedWorkerId = $event['id_personal_resuelto'] ?? null;
            $collaborator = ChecadorPresentationFormatter::collaboratorLabel($resolvedWorkerId);
            if ($resolvedWorkerId !== null) {
                $collaborator .= ' · ID interno ' . (int) $resolvedWorkerId;
            }
            $rows[] = [
                'station' => $workplaceName,
                'received_at_utc' => (string) ($event['recibido_at_utc'] ?? ''),
                'occurrence_available' => ($event['ocurrido_at_utc'] ?? null) !== null,
                'collaborator' => $collaborator,
                'capture_method' => ChecadorPresentationFormatter::captureMethodLabel($event['metodo_captura'] ?? null),
                'processing_status' => ChecadorPresentationFormatter::processingStatusLabel($event['processing_status'] ?? null),
                'processing_status_class' => ChecadorPresentationFormatter::processingStatusBadgeClass($event['processing_status'] ?? null),
                'event_uuid' => (string) ($event['event_uuid'] ?? ''),
                'device' => isset($event['id_dispositivo']) ? 'Dispositivo ' . (int) $event['id_dispositivo'] : 'No disponible',
                'source_timestamp_raw' => (string) ($event['source_timestamp_raw'] ?? ''),
                'source_timezone_reported' => (string) ($event['source_timezone_reported'] ?? ''),
                'ocurrido_at_utc' => $event['ocurrido_at_utc'] === null ? null : (string) $event['ocurrido_at_utc'],
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
        foreach ($events as &$event) {
            $event['integrity_status'] = 'NOT_VERIFIED_BY_PORTAL3';
            $event['occurrence_status'] = ($event['ocurrido_at_utc'] ?? null) === null
                ? 'OCCURRENCE_TIME_UNRESOLVED'
                : 'OCCURRENCE_TIME_AVAILABLE_UTC';
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
        string $direction
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
            'Consulta en vivo: los registros pueden cambiar durante la descarga. UTC significa Tiempo Universal Coordinado. La integridad no fue verificada por Portal3.',
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
                $direction
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
                self::csvText(ChecadorPresentationFormatter::collaboratorLabel($event['id_personal_resuelto'] ?? null)),
                self::csvText(isset($event['id_dispositivo']) ? 'Dispositivo ' . (string) $event['id_dispositivo'] : 'No disponible'),
                self::csvText($event['source_timestamp_raw'] ?? ''),
                self::csvText($event['source_timezone_reported'] ?? ''),
                self::csvText($event['ocurrido_at_utc'] ?? ''),
                self::csvText($event['recibido_at_utc'] ?? ''),
                self::csvText(ChecadorPresentationFormatter::captureMethodLabel($event['metodo_captura'] ?? null)),
                self::csvText(ChecadorPresentationFormatter::processingStatusLabel($event['processing_status'] ?? null)),
                self::csvText(($event['ocurrido_at_utc'] ?? null) === null
                    ? 'Hora no determinada; se muestra la fecha y hora de recepción'
                    : 'Hora de marcación disponible (UTC)'),
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
            'database_unavailable' => ['unavailable', 'La consulta de marcaciones no está disponible; no se puede confirmar si hay registros.'],
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

    private static function redirect(string $location): never
    {
        header('Location: ' . $location, true, 303);
        exit;
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
}
