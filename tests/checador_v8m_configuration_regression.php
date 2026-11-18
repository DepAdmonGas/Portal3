<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
\App\Core\Session::init();

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

$render = static function (array $configuration, bool $temporaryAccess = true, bool $demo = false, bool $canReadScheduling = true, bool $templateEdit = false, bool $m11Writes = false, bool $scheduleWrites = false): string {
    $selectedWorkplaceId = (int) ($configuration['workplace_id'] ?? 1);
    $selectedWorkplaceName = (string) ($configuration['workplace_name'] ?? 'Interlomas & Centro');
    $workerId = 558;
    $date = '2026-10-19';
    $demoMode = $demo;
    $canReadScheduling = $canReadScheduling;
    $temporaryAccessAvailable = $temporaryAccess;
    $m11ConfigurationWritesEnabled = $m11Writes;
    $attendancePolicyWritesEnabled = $m11Writes;
    $scheduleTemplateWritesEnabled = $scheduleWrites;
    $historyInputInvalid = false;
    ob_start();
    include dirname(__DIR__) . '/app/Views/departamento-operativo/2-recursos-humanos/checador/configuration.php';
    return (string) ob_get_clean();
};
$renderTemplateAdmin = static function (array $catalog, bool $authorized, bool $writesEnabled = true): string {
    $selectedWorkplaceId = 1;
    $selectedWorkplaceName = 'Interlomas';
    $selectedDate = '';
    $selectedWorkerId = null;
    $selectedWorkerName = '';
    $effectiveSchedule = null;
    $effectiveScheduleError = null;
    $effectiveScheduleErrorMessage = '';
    $historyItems = [];
    $historyError = null;
    $historyErrorMessage = '';
    $historyPage = 1;
    $historyHasPrevious = false;
    $historyHasNext = false;
    $validityCheck = null;
    $feedback = null;
    $workplaces = [];
    $scheduleTemplates = $catalog['items'] ?? [];
    $localByKey = [];
    foreach ($scheduleTemplates as &$template) {
        $template['segmentos'] = $template['segments'] ?? [];
        $template['edit_fields'] = ['start_time' => '08:00', 'end_time' => '16:00', 'break_start' => '', 'break_end' => ''];
        if (($template['scope'] ?? '') === 'STATION') $localByKey[(string) ($template['clave'] ?? '')] = $template;
    }
    unset($template);
    foreach ($scheduleTemplates as &$template) {
        if (($template['scope'] ?? '') !== 'GLOBAL') continue;
        if (is_array($template['station_customization'] ?? null)) {
            $localCopy = $localByKey[(string) ($template['station_customization']['clave'] ?? '')] ?? null;
            if ($localCopy !== null) $template['station_customization']['template'] = $localCopy;
        } else {
            $template['customize_fields'] = ['start_time' => '08:00', 'end_time' => '16:00', 'break_start' => '', 'break_end' => ''];
        }
    }
    unset($template);
    $canManageStationTemplates = $authorized;
    $templateWritesEnabled = $writesEnabled;
    $templateCatalogStatus = ($catalog['status'] ?? '') === 'ready' ? 'received' : 'unavailable';
    $demoMode = false;
    ob_start();
    include dirname(__DIR__) . '/app/Views/departamento-operativo/2-recursos-humanos/checador/schedule-history.php';
    return (string) ob_get_clean();
};

$fixture = [
    'status' => 'ready',
    'demo_mode' => false,
    'workplace_id' => 1,
    'workplace_kind' => 'station',
    'permissions' => [
        'read' => true, 'legacy_create' => false, 'legacy_edit' => false, 'legacy_delete' => false,
        'policy_read' => true, 'policy_edit' => false,
        'v2_schedule_edit' => false, 'positions_read' => true, 'positions_create' => false,
        'positions_edit' => false, 'positions_delete' => false,
        'reader_profile_read' => true, 'reader_profile_edit' => false, 'device_read' => true,
    ],
    'mapping' => ['status' => 'unique_numlista_mapping', 'physical_station_id' => 2, 'name' => 'Interlomas', 'legacy_scope_authorized' => true],
    'api_templates' => [
        'status' => 'ready', 'message' => 'Contrato V2 leído.',
        'items' => [[
            'id' => 10, 'clave' => 'TURNO-DEMO', 'version' => 1,
            'nombre' => 'Turno <script>alert(1)</script>', 'tipo_turno' => 'DIURNO', 'es_activo' => 1,
            'segments' => [['ordinal' => 1, 'tipo_segmento' => 'TRABAJO', 'hora_inicio_local' => '08:00',
                'hora_fin_local' => '16:00', 'dia_offset_fin' => 0]],
        ], [
            'id' => 11, 'clave' => 'LEGACY-TURNO-1', 'version' => 1,
            'nombre' => 'Legacy Interlomas — TURNO 1 / ENCARGADO 1 — 06:00–14:00', 'tipo_turno' => 'NOCTURNO', 'es_activo' => 1,
            'segments' => [
                ['ordinal' => 1, 'tipo_segmento' => 'WORK', 'hora_inicio_local' => '06:00:00', 'hora_fin_local' => '12:00:00', 'dia_offset_inicio' => 0, 'dia_offset_fin' => 0],
                ['ordinal' => 2, 'tipo_segmento' => 'BREAK', 'hora_inicio_local' => '12:00:00', 'hora_fin_local' => '13:00:00', 'dia_offset_inicio' => 0, 'dia_offset_fin' => 0],
                ['ordinal' => 3, 'tipo_segmento' => 'WORK', 'hora_inicio_local' => '13:00:00', 'hora_fin_local' => '22:00:00', 'dia_offset_inicio' => 0, 'dia_offset_fin' => 0],
            ],
        ]],
    ],
    'api_history' => [
        'status' => 'ready', 'message' => 'Lectura V2 sin mutaciones.',
        'effective_schedule' => ['estado_jornada' => 'SCHEDULED'],
        'assignments' => [['dia_semana' => 'LUNES', 'valido_desde' => '2026-10-19', 'valido_hasta' => null,
            'id_plantilla' => 10, 'es_activo' => 1]],
    ],
    'policy' => ['status' => 'ready', 'data' => [
        'workplace_id' => 1, 'effective_date' => '2026-10-19', 'current_local_date' => '2026-10-19',
        'timezone_id' => 'America/Mexico_City', 'status' => 'LEGACY_UNVERSIONED', 'policy' => null,
    ]],
    'legacy_schedules' => ['status' => 'legacy_catalog', 'items' => [
        ['id' => 3, 'title' => 'Horario registrado anteriormente', 'start' => '09:00', 'end' => '17:00'],
    ]],
    'legacy_rules' => ['status' => 'legacy_unconfigured', 'configured' => false, 'retardo' => 0, 'incidencia' => 0],
    'legacy_profiles' => ['status' => 'legacy_metadata_only', 'active_count' => 2],
    'positions' => ['status' => 'current_catalog', 'items' => [['id' => 4, 'name' => 'Encargado']]],
    'reader_profiles' => ['status' => 'station_scoped', 'items' => [['id' => 9, 'usuario' => 'reader-test']]],
    'audit_history' => [
        'status' => 'ready',
        'items' => [[
            'id' => 1, 'timestamp_utc' => '2026-11-18 12:00:00', 'actor_label' => 'Usuario #42',
            'module' => 'Puestos', 'action' => 'Creación',
        ], [
            'id' => 2, 'timestamp_utc' => '2026-11-18 11:00:00', 'actor_label' => 'Usuario #43',
            'module' => 'Perfil del lector', 'action' => 'Cambio de usuario',
        ]],
        'pagination' => ['current_page' => 1, 'page_size' => 15, 'total_records' => 2, 'total_pages' => 1,
            'has_previous' => false, 'has_next' => false],
    ],
    'device_auth' => ['status' => 'ready', 'completeness' => 'COMPLETE', 'read_at_utc' => '2026-10-19T12:00:00Z', 'items' => [[
        'device' => ['id' => 2, 'name' => 'Lector de prueba', 'persisted_status' => 'ACTIVE'],
        'physical_station' => ['id' => 2, 'name' => 'Estación de prueba'],
        'workplace' => ['id' => 1, 'name' => 'Interlomas', 'mapping_status' => 'VERIFIED'],
        'assignment' => ['status' => 'ASSIGNED', 'valid_from_utc' => null, 'valid_to_utc' => null],
        'last_communication' => ['last_server_received_at_utc' => null],
    ]]],
];

$html = '';
$renderError = null;
try {
    $html = $render($fixture);
} catch (Throwable $exception) {
    $renderError = $exception::class . ': ' . $exception->getMessage();
}

$assert($renderError === null && str_contains($html, 'id="attendance-policy-v2"'), 'configuration view renders from an authorized synthetic projection');
$sectionIds = ['attendance-policy-v2', 'people-positions', 'reader-access', 'm11-devices', 'm11-station-readers', 'm11-effective-permissions', 'm11-change-history'];
$sectionOrder = array_map(static fn(string $id): int|false => strpos($html, 'id="' . $id . '"'), $sectionIds);
$sortedSectionOrder = $sectionOrder;
sort($sortedSectionOrder);
$assert(!in_array(false, $sectionOrder, true) && $sectionOrder === $sortedSectionOrder, 'configuration restores original section order and keeps the catalog after the original sections');
$assert(str_contains($html, '/departamento-operativo/recursos-humanos/checador/horarios-turnos')
    && !str_contains($html, 'schedule_week=1') && !str_contains($html, 'id="configuration-navigation"'), 'configuration keeps a clean schedule route and omits horizontal section navigation');
$assert(str_contains($html, 'href="/departamento-operativo/recursos-humanos/checador/horarios-turnos"')
    && !str_contains($html, 'id="schedule-configuration"') && !str_contains($html, 'station-template-create-modal')
    && !str_contains($html, 'ti-calendar-time'), 'M11 keeps only the original shortcut to the independent schedule view, without duplicate administration');
$assert(!str_contains($html, 'Los horarios de catálogos anteriores no se convierten')
    && !str_contains($html, 'Abrir configuración anterior') && !str_contains($html, 'API V2 es la autoridad'), 'legacy schedules are not presented as Checador V2 configuration');
$assert(!str_contains($html, 'pairing_role') && !str_contains($html, 'segment_ordinal')
    && !str_contains($html, 'SCHEDULED'), 'configuration translates schedule results without exposing internal identifiers');
$assert(!str_contains($html, 'Estos registros conservan su propio centro seleccionado.') && !str_contains($html, '/biometricos/'), 'Checador configuration does not redirect to a Biométricos implementation');
$assert(!str_contains($html, 'El catálogo de turnos') && !str_contains($html, 'acciones autorizadas')
    && !str_contains($html, 'station-template-create-modal') && !str_contains($html, 'data-bs-target="#station-template-version-'), 'M11 excludes the schedule administration card and its duplicated controls');
$assert(str_contains($html, 'id="m11-position-table"'), 'original searchable position table is rendered from the authorized catalog');
$assert(!str_contains($html, 'id="attendance-policy-history-table"')
    && str_contains($html, 'no incluye el historial de versiones')
    && str_contains($html, 'id="attendance-policy-history-modal"'), 'history modal reports that the current authorized policy read has no version-history contract instead of showing an empty table');
$assert(str_contains($html, 'data-checador-configuration-datatable') && str_contains($html, 'id="m11-devices-table"')
    && str_contains($html, 'class="table-responsive"'), 'server-read device list uses the existing institutional DataTable and responsive wrapper');
$assert(str_contains($html, 'No hay una política V2 confirmada vigente')
    && !str_contains($html, '0 minutos</dd>') && !str_contains($html, '0 días</dd>'), 'missing V2 policy is not displayed as a persisted default');
$scheduledFixture = $fixture;
$scheduledFixture['policy']['data']['status'] = 'VERSIONED';
$scheduledFixture['policy']['data']['policy'] = [
    'id' => 70, 'workplace_id' => 1, 'policy_version' => 2,
    'effective_from_local_date' => '2026-11-07',
    'retardo_tolerance_minutes' => 30, 'incidence_deadline_days' => 6,
    'timezone_id' => 'America/Mexico_City',
];
$scheduledFixture['policy']['data']['latest_scheduled_policy'] = [
    'id' => 71, 'workplace_id' => 1, 'policy_version' => 3,
    'effective_from_local_date' => '2026-11-19',
    'retardo_tolerance_minutes' => 30, 'incidence_deadline_days' => 7,
    'timezone_id' => 'America/Mexico_City',
];
$scheduledFixture['policy']['data']['current_local_date'] = '2026-11-18';
$scheduledHtml = $render($scheduledFixture);
$nextPolicyAttribute = preg_match('/data-next-policy="([^"]*)"/', $scheduledHtml, $nextPolicyMatch) === 1
    ? json_decode(html_entity_decode($nextPolicyMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true)
    : null;
$assert(is_array($nextPolicyAttribute)
    && ($nextPolicyAttribute['id'] ?? 0) === 71
    && ($nextPolicyAttribute['effective_from_local_date'] ?? '') === '2026-11-19'
    && ($nextPolicyAttribute['incidence_deadline_days'] ?? 0) === 7,
    'future API V2 policy remains visible in the configuration view after a full reload');
$crossLocalityScheduled = $scheduledFixture;
$crossLocalityScheduled['workplace_id'] = 2;
$crossLocalityScheduled['workplace_name'] = 'Palo Solo';
$crossLocalityScheduled['policy']['data']['workplace_id'] = 2;
$crossLocalityScheduled['policy']['data']['policy']['workplace_id'] = 2;
$crossLocalityScheduled['policy']['data']['latest_scheduled_policy']['workplace_id'] = 1;
$crossLocalityHtml = $render($crossLocalityScheduled);
$crossLocalityNextPolicy = preg_match('/data-next-policy="([^"]*)"/', $crossLocalityHtml, $crossLocalityMatch) === 1
    ? json_decode(html_entity_decode($crossLocalityMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true)
    : null;
$assert($crossLocalityNextPolicy === []
    && str_contains($crossLocalityHtml, 'la respuesta no confirma la localidad seleccionada'),
    'scheduled policy from another canonical workplace is withheld and reported');
$departmentFixture = $fixture;
$departmentFixture['workplace_id'] = 9;
$departmentFixture['workplace_name'] = 'Autolavado';
$departmentFixture['workplace_kind'] = 'department';
$departmentFixture['permissions']['policy_read'] = false;
$departmentFixture['permissions']['policy_edit'] = false;
$departmentFixture['permissions']['reader_profile_read'] = false;
$departmentFixture['permissions']['reader_profile_edit'] = false;
$departmentFixture['permissions']['device_read'] = false;
$departmentFixture['policy'] = ['status' => 'not_applicable', 'data' => null];
$departmentFixture['reader_profiles'] = ['status' => 'not_applicable', 'items' => []];
$departmentHtml = $render($departmentFixture);
$assert(str_contains($departmentHtml, 'Configuración de asistencia: No aplica a esta localidad.')
    && !str_contains($departmentHtml, 'id="attendance-policy-v2"')
    && str_contains($departmentHtml, 'Perfil del lector: No aplica a esta localidad.')
    && !str_contains($departmentHtml, 'id="reader-access"')
    && !str_contains($departmentHtml, 'Editar configuración'),
    'department view replaces non-applicable station administration cards and actions with concise status text');
$assert(str_contains($html, 'Personal y puestos') && str_contains($html, 'Encargado'), 'positions section uses the existing institutional catalog projection');
$noPositionAccess = $fixture;
$noPositionAccess['permissions']['positions_read'] = false;
$noPositionAccess['positions'] = ['status' => 'permission_denied', 'items' => []];
$assert(!str_contains($render($noPositionAccess), 'id="people-positions"'), 'position section is absent without the Dirección-only catalog grant');
$assert(str_contains($html, 'Perfil del lector') && str_contains($html, 'Administración de dispositivos')
    && str_contains($html, 'Estaciones y lectores asignados') && str_contains($html, 'Permisos efectivos')
    && str_contains($html, 'Historial de cambios'), 'reader, device, scope, permission and history sections render');
$assert(str_contains($html, '2026-11-18 12:00:00') && str_contains($html, 'Usuario #42')
    && str_contains($html, '<th scope="col">Módulo</th>') && str_contains($html, 'Puestos')
    && str_contains($html, 'Creación') && str_contains($html, 'Cambio de usuario')
    && !str_contains($html, 'no integrado'), 'history table renders only supplied audit event fields and known action labels');
$pagedHistory = $fixture;
$pagedHistory['audit_history']['pagination'] = ['current_page' => 1, 'page_size' => 15, 'total_records' => 16, 'total_pages' => 2,
    'has_previous' => false, 'has_next' => true];
$pagedHistoryHtml = $render($pagedHistory);
$assert(str_contains($pagedHistoryHtml, 'Paginación del historial de cambios')
    && str_contains($pagedHistoryHtml, 'Página 1 de 2 · 16 registros')
    && str_contains($pagedHistoryHtml, 'audit_page=2'), 'history uses server pagination and preserves selected locality');
$emptyHistory = $fixture;
$emptyHistory['audit_history'] = ['status' => 'ready', 'items' => [], 'pagination' => []];
$assert(str_contains($render($emptyHistory), 'No hay cambios auditados disponibles para esta localidad.'), 'authorized empty history has an explicit empty state');
$deniedHistory = $fixture;
$deniedHistory['audit_history'] = ['status' => 'permission_denied', 'items' => [], 'pagination' => []];
$deniedHistoryHtml = $render($deniedHistory);
$assert(str_contains($deniedHistoryHtml, 'No tienes autorización para consultar este historial.')
    && !str_contains($deniedHistoryHtml, 'Usuario #42'), 'denied history does not render event rows');
$demoHistory = $fixture;
$demoHistory['audit_history'] = ['status' => 'not_read_in_demo', 'items' => [], 'pagination' => []];
$assert(str_contains($render($demoHistory), 'no se consulta en modo demostración'), 'demo view does not expose persisted audit events');
$assert(str_contains($html, 'Acceso temporal autorizado'), 'temporary access link is rendered only for the authorized fixture');
$assert(!str_contains($html, 'Turno &lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($html, '<script>alert(1)</script>'), 'M11 no longer renders the schedule catalog or its duplicate administration surface');

$noTemporaryAccessHtml = $render($fixture, false);
$assert(!str_contains($noTemporaryAccessHtml, 'Acceso temporal autorizado'), 'temporary-access section is omitted when its independent ACL is absent');
$noSchedulingReadHtml = $render($fixture, true, false, false);
$assert(!str_contains($noSchedulingReadHtml, 'schedule_week=1')
    && !str_contains($noSchedulingReadHtml, '/checador/horarios-turnos'), 'weekly and historical schedule links are omitted without the independent read permission');

$outOfLegacyScope = $fixture;
$outOfLegacyScope['mapping']['legacy_scope_authorized'] = false;
$outOfLegacyScopeHtml = $render($outOfLegacyScope);
$assert(!str_contains($outOfLegacyScopeHtml, 'Horario registrado anteriormente')
    && !str_contains($outOfLegacyScopeHtml, '/biometricos/'), 'legacy mapping does not expose data through a Biométricos link');

$demoFixture = $fixture;
$demoFixture['status'] = 'demo_isolated';
$demoFixture['demo_mode'] = true;
$demoFixture['mapping'] = ['status' => 'not_read_in_demo', 'physical_station_id' => null, 'name' => null];
$demoFixture['legacy_schedules'] = ['status' => 'not_read_in_demo', 'items' => []];
$demoFixture['legacy_rules'] = ['status' => 'not_read_in_demo', 'configured' => false, 'retardo' => null, 'incidencia' => null];
$demoFixture['legacy_profiles'] = ['status' => 'not_read_in_demo', 'active_count' => null];
$demoFixture['positions'] = ['status' => 'not_read_in_demo', 'items' => []];
$demoHtml = $render($demoFixture, false, true);
$assert(str_contains($demoHtml, 'Los datos mostrados no son operativos')
    && str_contains($demoHtml, 'Demostración local. No consulta datos operativos'), 'demo is labelled and keeps real operational sources out of its presentation');

$readService = file_get_contents(dirname(__DIR__) . '/app/Services/ChecadorConfigurationReadService.php') ?: '';
$dailyReadService = file_get_contents(dirname(__DIR__) . '/app/Services/ChecadorDailyReadService.php') ?: '';
$biometricService = file_get_contents(dirname(__DIR__) . '/app/Services/BiometricosService.php') ?: '';
$biometricController = file_get_contents(dirname(__DIR__) . '/app/Controllers/BiometricosController.php') ?: '';
$checadorController = file_get_contents(dirname(__DIR__) . '/app/Controllers/ChecadorController.php') ?: '';
$routes = file_get_contents(dirname(__DIR__) . '/routes/web.php') ?: '';
$profileJs = file_get_contents(dirname(__DIR__) . '/public/assets/js/departamento-operativo/2-recursos-humanos/biometricos-config-perfil.actions.init.js') ?: '';
$checadorReaderProfileJs = file_get_contents(dirname(__DIR__) . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.m11-reader-profile.init.js') ?: '';
$checadorPositionJs = file_get_contents(dirname(__DIR__) . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.m11-positions.init.js') ?: '';
$attendancePolicyJs = file_get_contents(dirname(__DIR__) . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.attendance-policy.init.js') ?: '';
$configurationJs = file_get_contents(dirname(__DIR__) . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.configuration.datatable.init.js') ?: '';
$permissionsJs = file_get_contents(dirname(__DIR__) . '/public/assets/js/departamento-operativo/2-recursos-humanos/permisos.datatable.init.js') ?: '';

$methodSlice = static function (string $source, string $start, string $end): string {
    $startAt = strpos($source, $start);
    if ($startAt === false) return '';
    $endAt = strpos($source, $end, $startAt + strlen($start));
    return substr($source, $startAt, $endAt === false ? null : $endAt - $startAt);
};
$profileListMethod = $methodSlice($biometricService, 'public static function datatablePerfil(', 'public static function createPerfil(');
$rulesReadMethod = $methodSlice($biometricService, 'public static function getRetardoIncidencia(', 'public static function updateRetardoIncidencia(');
$assert(str_contains($profileListMethod, "'usuario' => self::decrypt(" . '$r->usuario' . ")")
    && !str_contains($profileListMethod, '$r->password') && !str_contains($profileListMethod, "'password' =>"), 'profile listing returns username metadata but never decrypts or serializes password');
$assert(str_contains($checadorReaderProfileJs, "this.password = '';")
    && str_contains($checadorReaderProfileJs, "this.passwordConfirm = '';")
    && !str_contains($checadorReaderProfileJs, 'password: this.password'), 'original reader profile editor starts with blank, write-only credential fields');
$assert(str_contains($configurationJs, "querySelectorAll('[data-checador-configuration-datatable]')")
    && str_contains($configurationJs, 'pageLength: 15') && str_contains($configurationJs, 'lengthMenu: [15, 30, 50, 100]')
    && str_contains($configurationJs, "scrollX: ''") && str_contains($configurationJs, "scrollY: ''")
    && str_contains($configurationJs, 'drawCallback')
    && str_contains($checadorController, 'checador.configuration.datatable.init.js'), 'positions DataTable reuses the Checador initializer and maintains dropdowns after redraw');
$assert(str_contains($checadorController, 'checador.attendance-policy.init.js')
    && str_contains($checadorController, 'checador.m11-positions.init.js')
    && str_contains($checadorController, 'checador.m11-reader-profile.init.js')
    && str_contains($attendancePolicyJs, "Alpine.data('attendancePolicyForm'")
    && str_contains($checadorPositionJs, "Alpine.data('m11PositionCatalog'")
    && str_contains($checadorReaderProfileJs, "Alpine.data('m11ReaderProfile'"), 'original configuration initializers are loaded for the restored modals and tables');
$configurationMethod = $methodSlice($checadorController, 'public function configuration(): void', 'public function saveConfigurationPosition(): void');
$assert(str_contains($permissionsJs, 'pageLength: 15') && str_contains($permissionsJs, 'lengthMenu: [15, 30, 50, 100]')
    && str_contains($permissionsJs, "url: '/assets/libs/datatables.net/js/es-ES.json'")
    && str_contains($configurationJs, 'pageLength: 15') && str_contains($configurationJs, 'lengthMenu: [15, 30, 50, 100]')
    && !str_contains($configurationJs, 'pagingType:') && !str_contains($configurationJs, 'dom:')
    && !str_contains($configurationMethod, '/assets/libs/datatables.net-bs5/js/dataTables.bootstrap5.min.js'),
    'configuration uses the same DataTables core pagination defaults as the institutional Permisos table');
$assert(str_contains($rulesReadMethod, 'RhLocalidadesRetardoIncidencia::query()')
    && !str_contains($rulesReadMethod, '::create(') && !str_contains($rulesReadMethod, '->save(')
    && !preg_match('/\b(?:INSERT|UPDATE|DELETE)\b/i', $rulesReadMethod), 'legacy attendance-rule GET is query-only and has no persistence side effect');
$assert(str_contains($biometricService, 'authorizedLegacyResourceQuery')
    && str_contains($biometricService, 'resolveAuthorizedLegacyStationId')
    && str_contains($biometricController, "requireBiometricosAction('editar')"), 'legacy resource lookups and operations recheck station scope and action-level ACL server-side');
$assert(str_contains($biometricController, "hasActionPermission(BiometricosService::MODULE_KEY, 'leer')")
    && str_contains($biometricController, "hasActionPermission('recursos-humanos', 'leer')")
    && !str_contains($methodSlice($biometricController, 'public function configuracionModulo(', 'public function datatablePuestos()'), "['multiestacion']"), 'configuration access is read-ACL based rather than requiring a global multi-station flag');
$assert(str_contains($readService, "if ($" . 'demoMode)')
    && str_contains($readService, "'not_read_in_demo'")
    && str_contains($readService, 'return $result;'), 'demo branch returns before any legacy table reads');
$assert(str_contains($checadorController, "ChecadorAccessService::authorize($" . 'workplaceId)')
    && str_contains($checadorController, 'setChecadorWorkplaceContext($workplaceId)')
    && str_contains($routes, "'/recursos-humanos/checador/configuracion'"), 'configuration route revalidates canonical Checador scope and is present in the authenticated route group');
$assert(str_contains($readService, 'getShiftTemplates') && str_contains($readService, 'getEffectiveSchedule')
    && str_contains($readService, 'getShiftAssignments')
    && !preg_match('/\b(?:INSERT\s+INTO|UPDATE\s+[A-Za-z_]\w*\s+SET|DELETE\s+FROM)\b/i', $readService)
    && !str_contains($readService, '->insert(') && !str_contains($readService, '->update(') && !str_contains($readService, '->delete('),
    'V2 configuration reads reuse existing readers and never write schedule data locally');
$assert(str_contains($readService, '$detailsComplete = false') && str_contains($readService, "'partial' : 'ready'")
    && str_contains($readService, "'demo_fixture_partial' : 'demo_fixture'")
    && str_contains($readService, "'partial' : 'ready'"), 'template-detail failures are reported as partial instead of appearing complete');

$policyReaderPath = $methodSlice($readService, "} elseif (\$policyRead['allowed'] ?? false) {", "if (\$workerId !== null && self::isCivilDate(\$date)) {");
$assert(str_contains($readService, 'authorizeAttendancePolicy') && str_contains($policyReaderPath, 'readCurrentAttendancePolicy')
    && !str_contains($policyReaderPath, 'BiometricosService'), 'policy reading uses the Checador V2 permission and API reader without depending on the legacy profile codec');
$assert(str_contains($readService, 'getShiftTemplates($user, $page, $pageSize, $workplaceId)')
    && str_contains($readService, 'getShiftTemplateVersion($user, (string) $template[\'clave\'], (int) $template[\'version\'], $workplaceId)')
    && str_contains($readService, "['GLOBAL', 'STATION']")
    && str_contains($readService, '$workplaceId'), 'template listing and detail reads pass the authorized locality scope to API V2 and validate returned scope');
$assert(str_contains($checadorController, 'saveAttendancePolicy') && str_contains($routes, "'/recursos-humanos/checador/configuracion/politica-asistencia'")
    && str_contains($routes, "Route::auth(['ChecadorController', 'saveAttendancePolicy'])"), 'policy write endpoint is authenticated and CSRF protected');

$writeFixture = $fixture;
$writeFixture['permissions']['positions_edit'] = true;
$writeFixture['permissions']['positions_create'] = true;
$writeFixture['permissions']['reader_profile_read'] = true;
$writeFixture['permissions']['reader_profile_edit'] = true;
$writeFixture['permissions']['station_template_edit'] = true;
$writeFixture['permissions']['policy_edit'] = true;
$writeFixture['reader_profiles'] = ['status' => 'station_scoped', 'items' => [['id' => 8, 'usuario' => 'Sensor-test']]];
$writeHtml = $render($writeFixture, true, false, true, true, true, true);
$segments = [['ordinal' => 1, 'tipo_segmento' => 'WORK', 'hora_inicio_local' => '08:00:00', 'hora_fin_local' => '16:00:00', 'dia_offset_inicio' => 0, 'dia_offset_fin' => 0]];
$adminCatalog = ['status' => 'ready', 'items' => [
    ['id' => 10, 'clave' => 'STATION-ONLY', 'version' => 1, 'nombre' => 'Legacy Interlomas — TURNO LOCAL', 'tipo_turno' => 'REGULAR', 'scope' => 'STATION', 'id_estacion' => 1, 'es_activo' => 1, 'segments' => $segments],
    ['id' => 11, 'clave' => 'GLOBAL-NEW', 'version' => 1, 'nombre' => 'Turno global nuevo', 'tipo_turno' => 'NOCTURNO', 'scope' => 'GLOBAL', 'id_estacion' => null, 'es_activo' => 1, 'segments' => $segments, 'station_customization' => null],
    ['id' => 12, 'clave' => 'GLOBAL-EXISTING', 'version' => 1, 'nombre' => 'Turno global adaptado', 'tipo_turno' => 'NOCTURNO', 'scope' => 'GLOBAL', 'id_estacion' => null, 'es_activo' => 1, 'segments' => $segments, 'station_customization' => ['clave' => 'ST1_EXISTING', 'source_version' => 1]],
    ['id' => 13, 'clave' => 'ST1_EXISTING', 'version' => 2, 'nombre' => 'Personalización de estación', 'tipo_turno' => 'NOCTURNO', 'scope' => 'STATION', 'id_estacion' => 1, 'es_activo' => 1, 'segments' => $segments],
    ['id' => 14, 'clave' => 'BAD-TYPE', 'version' => 1, 'nombre' => 'No editable', 'tipo_turno' => 'SPECIAL', 'scope' => 'STATION', 'id_estacion' => 1, 'es_activo' => 1, 'segments' => $segments],
]];
$templateAdminHtml = $renderTemplateAdmin($adminCatalog, true);
$scheduleHeaderStart = strpos($templateAdminHtml, 'id="m04-template-catalog-heading"');
$scheduleTableStart = strpos($templateAdminHtml, 'caption class="visually-hidden">Horarios disponibles');
$readOnlyAdminHtml = $renderTemplateAdmin($adminCatalog, false);
$assert(str_contains($writeHtml, 'id="attendance-policy-edit-button"')
    && str_contains($writeHtml, '@click="openHistoryModal()"')
    && str_contains($writeHtml, 'href="/departamento-operativo/recursos-humanos/checador/horarios-turnos"'), 'original policy edit/history actions and schedule shortcut retain their independent targets');
$assert(substr_count($templateAdminHtml, 'id="m04-btn-new-template"') === 1
    && $scheduleHeaderStart !== false && $scheduleTableStart !== false
    && strpos($templateAdminHtml, 'id="m04-btn-new-template"') < $scheduleTableStart,
    'authorized template creation uses the original schedule catalog header');
$globalExistingPosition = strpos($templateAdminHtml, 'Turno global adaptado');
$globalExistingRowStart = $globalExistingPosition === false ? false : strrpos(substr($templateAdminHtml, 0, $globalExistingPosition), '<tr>');
$globalExistingRowEnd = $globalExistingPosition === false ? false : strpos($templateAdminHtml, '</tr>', $globalExistingPosition);
$globalExistingRow = $globalExistingRowStart === false || $globalExistingRowEnd === false
    ? ''
    : substr($templateAdminHtml, $globalExistingRowStart, $globalExistingRowEnd + 5 - $globalExistingRowStart);
$assert(!str_contains($templateAdminHtml, 'Legacy Interlomas') && str_contains($templateAdminHtml, 'TURNO LOCAL')
    && str_contains($templateAdminHtml, 'data-m04-edit-template=')
    && str_contains($templateAdminHtml, 'data-m04-customize-template=')
    && str_contains($globalExistingRow, 'Editar personalización')
    && str_contains($templateAdminHtml, 'm04-create-template-modal')
    && str_contains($templateAdminHtml, 'm04-edit-template-modal'),
    'the original row actions edit local schedules, personalize global ones, and edit existing station copies');
$assert(!str_contains($readOnlyAdminHtml, 'id="m04-btn-new-template"')
    && !str_contains($readOnlyAdminHtml, 'data-m04-edit-template=')
    && !str_contains($readOnlyAdminHtml, 'data-m04-customize-template=')
    && !str_contains($readOnlyAdminHtml, 'm04-create-template-modal'),
    'original schedule actions remain absent without the effective versioning permission and write gate');
$assert(str_contains($writeHtml, '/configuracion/puestos') && str_contains($writeHtml, '/configuracion/perfiles-lector')
    && str_contains($templateAdminHtml, '/horarios-turnos/plantillas') && str_contains($templateAdminHtml, 'Guardar turno'), 'M11 settings remain separate while the original M04 form posts through its protected V2 adapter');
$assert(str_contains($writeHtml, 'name="new_password"') && !str_contains($writeHtml, 'name="password" value=')
    && str_contains($writeHtml, 'Deja vacío para conservar la contraseña actual.'), 'reader credentials are write-only and blank preserves the stored secret');
$assert(str_contains($checadorController, 'saveConfigurationPosition') && str_contains($checadorController, 'updateConfigurationReaderProfile')
    && str_contains($checadorController, 'saveConfigurationScheduleTemplate')
    && str_contains($routes, "Route::auth(['ChecadorController', 'saveConfigurationPosition'])")
    && str_contains($routes, "Route::auth(['ChecadorController', 'updateConfigurationReaderProfile'])"), 'configuration mutations are authenticated and routed through Checador');
$assert(str_contains($writeService = file_get_contents(dirname(__DIR__) . '/app/Services/ChecadorConfigurationWriteService.php') ?: '', 'Capsule::connection()->transaction')
    && str_contains($writeService, 'POSITION_CATALOG_EDIT') && str_contains($writeService, 'READER_PROFILE_EDIT')
    && str_contains($writeService, 'op_rh_v2_m11_configuration_audit') && !str_contains($writeService, 'delete'), 'local catalog/profile writes require explicit M11 authorization, transactional audit, and no unsupported delete');
$assert(str_contains($checadorController, 'authorizeStationTemplateAdministration($workplaceId)')
    && str_contains($dailyReadService, 'createStationShiftTemplateVersion')
    && str_contains($routes, "Route::auth(['ChecadorController', 'saveConfigurationScheduleTemplate'])"), 'station template writes remain behind the explicit Encargado grant and API readback');
$assert(str_contains($templateAdminHtml, 'm04-turn-name') && str_contains($templateAdminHtml, 'm04-turn-start')
    && str_contains($templateAdminHtml, 'm04-turn-end') && str_contains($templateAdminHtml, 'm04-turn-break-start')
    && str_contains($templateAdminHtml, 'm04-turn-break-end')
    && !str_contains($templateAdminHtml, 'Tipo de segmento')
    && str_contains($dailyReadService, 'schedule_template_readback_unconfirmed'), 'original template forms keep only the original schedule controls and V2 readback');
$templateJs = file_get_contents(dirname(__DIR__) . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.schedule-history.templates.init.js') ?: '';
$templateAccessService = file_get_contents(dirname(__DIR__) . '/app/Services/ChecadorAccessService.php') ?: '';
$assert(str_contains($templateAdminHtml, 'data-m04-customize-template=')
    && str_contains($templateJs, 'openCustomize(source)')
    && str_contains($templateJs, 'this.customizationSource = { key: String(source.key), version: Number(source.version) }')
    && str_contains($templateJs, "operation: 'customize'")
    && str_contains($templateAccessService, 'authorizeStationTemplateAdministration')
    && str_contains($templateAccessService, 'STATION_TEMPLATE_ADMIN'),
    'the original global personalization flow posts to the V2 adapter under station administration authorization');
$assert(str_contains($dailyReadService, "'scope' => 'STATION'")
    && str_contains($dailyReadService, 'customizeGlobalShiftTemplate(')
    && str_contains($dailyReadService, "'derived_from_global' => ['clave' => \$sourceKey, 'version' => \$sourceVersion]")
    && !str_contains($dailyReadService, 'createGlobalShiftTemplate'),
    'global personalization uses the existing API V2 station-copy lineage and readback contract');

printf("CHECADOR_V8M_CONFIGURATION: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);
