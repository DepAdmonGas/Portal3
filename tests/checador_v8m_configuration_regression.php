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
    $selectedWorkplaceId = 1;
    $selectedWorkplaceName = 'Interlomas & Centro';
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
                ['ordinal' => 3, 'tipo_segmento' => 'WORK', 'hora_inicio_local' => '22:00:00', 'hora_fin_local' => '06:00:00', 'dia_offset_inicio' => 0, 'dia_offset_fin' => 1],
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
$sectionIds = ['attendance-policy-v2', 'people-positions', 'reader-access', 'm11-devices', 'm11-station-readers', 'm11-effective-permissions', 'm11-change-history', 'schedule-configuration'];
$sectionOrder = array_map(static fn(string $id): int|false => strpos($html, 'id="' . $id . '"'), $sectionIds);
$sortedSectionOrder = $sectionOrder;
sort($sortedSectionOrder);
$assert(!in_array(false, $sectionOrder, true) && $sectionOrder === $sortedSectionOrder, 'configuration restores original section order and keeps the catalog after the original sections');
$assert(str_contains($html, '/departamento-operativo/recursos-humanos/checador/programacion-semanal')
    && !str_contains($html, 'schedule_week=1') && !str_contains($html, 'id="configuration-navigation"'), 'configuration links to the clean weekly module and omits horizontal section navigation');
$assert(str_contains($html, 'schedule_history=1') && str_contains($html, 'worker_id=558') && str_contains($html, 'date=2026-10-19'), 'history link carries the selected worker, workplace and date');
$assert(!str_contains($html, 'Los horarios de catálogos anteriores no se convierten')
    && !str_contains($html, 'Abrir configuración anterior') && !str_contains($html, 'API V2 es la autoridad'), 'legacy schedules are not presented as Checador V2 configuration');
$assert(!str_contains($html, 'pairing_role') && !str_contains($html, 'segment_ordinal')
    && !str_contains($html, 'SCHEDULED'), 'configuration translates schedule results without exposing internal identifiers');
$assert(!str_contains($html, 'Estos registros conservan su propio centro seleccionado.') && !str_contains($html, '/biometricos/'), 'Checador configuration does not redirect to a Biométricos implementation');
$assert(str_contains($html, 'Horarios disponibles para el centro seleccionado.')
    && str_contains($html, 'Global') && !str_contains($html, 'id="weekly-matrix"'), 'station-scoped templates retain their global or local scope without implying worker assignment');
$assert(str_contains($html, 'TURNO 1 / ENCARGADO 1 — 06:00–14:00')
    && !str_contains($html, 'Legacy Interlomas')
    && str_contains($html, '06:00–12:00')
    && str_contains($html, 'Descanso · 12:00–13:00')
    && str_contains($html, '22:00–06:00 (+1 día)')
    && !preg_match('/<li>\s*WORK\b/', $html), 'schedule names and segments are formatted for display without changing source data');
$assert(substr_count($html, 'data-checador-configuration-datatable') === 7
    && !str_contains($html, 'class="table-responsive"'), 'all configuration data tables are paginatable and use small-screen-only horizontal wrappers');
$assert(str_contains($html, 'No hay una configuración vigente para la fecha consultada')
    && !str_contains($html, '0 minutos</dd>') && !str_contains($html, '0 días</dd>'), 'missing V2 policy is not displayed as a persisted default');
$assert(str_contains($html, 'Personal y puestos') && str_contains($html, 'Encargado'), 'positions section uses the existing institutional catalog projection');
$noPositionAccess = $fixture;
$noPositionAccess['permissions']['positions_read'] = false;
$noPositionAccess['positions'] = ['status' => 'permission_denied', 'items' => []];
$assert(!str_contains($render($noPositionAccess), 'id="people-positions"'), 'position section is absent without the Dirección-only catalog grant');
$assert(str_contains($html, 'Perfil del lector') && str_contains($html, 'Administración de dispositivos')
    && str_contains($html, 'Estaciones y lectores asignados') && str_contains($html, 'Permisos efectivos')
    && str_contains($html, 'Historial de cambios'), 'reader, device, scope, permission and history sections render');
$assert(str_contains($html, 'Acceso temporal autorizado'), 'temporary access link is rendered only for the authorized fixture');
$assert(str_contains($html, 'Turno &lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($html, '<script>alert(1)</script>'), 'dynamic catalogue labels are escaped in HTML');

$noTemporaryAccessHtml = $render($fixture, false);
$assert(!str_contains($noTemporaryAccessHtml, 'Acceso temporal autorizado'), 'temporary-access section is omitted when its independent ACL is absent');
$noSchedulingReadHtml = $render($fixture, true, false, false);
$assert(!str_contains($noSchedulingReadHtml, 'schedule_week=1')
    && !str_contains($noSchedulingReadHtml, 'schedule_history=1'), 'weekly and historical schedule links are omitted without the independent read permission');

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
$assert(str_contains($profileJs, "this.password = '';") && str_contains($profileJs, "password: this.password"), 'profile edit starts with a blank password so blank update preserves the stored credential');
$assert(str_contains($configurationJs, "querySelectorAll('[data-checador-configuration-datatable]')")
    && str_contains($configurationJs, 'pageLength: 15') && str_contains($configurationJs, 'lengthMenu: [15, 30, 50, 100]')
    && str_contains($configurationJs, "scrollX: ''") && str_contains($configurationJs, "scrollY: ''")
    && str_contains($configurationJs, 'drawCallback')
    && str_contains($checadorController, 'checador.configuration.datatable.init.js'), 'positions DataTable reuses the Checador initializer and maintains dropdowns after redraw');
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
    && !preg_match('/\b(?:INSERT|UPDATE|DELETE)\b/i', $readService), 'V2 configuration reads reuse existing readers and never write schedule data locally');
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
$writeFixture['api_templates']['items'][0]['scope'] = 'STATION';
$writeFixture['api_templates']['items'][0]['tipo_turno'] = 'REGULAR';
$writeFixture['api_templates']['items'][0]['id_estacion'] = 1;
$writeFixture['api_templates']['items'][0]['segments'] = [['ordinal' => 1, 'tipo_segmento' => 'WORK', 'hora_inicio_local' => '08:00:00',
    'hora_fin_local' => '16:00:00', 'dia_offset_inicio' => 0, 'dia_offset_fin' => 0]];
$writeFixture['api_templates']['items'][1]['scope'] = 'GLOBAL';
$writeFixture['api_templates']['items'][1]['id_estacion'] = null;
$writeFixture['api_templates']['items'][1]['tipo_turno'] = 'NOCTURNO';
$writeFixture['api_templates']['items'][] = array_replace($writeFixture['api_templates']['items'][1], [
    'id' => 12, 'clave' => 'GLOBAL-WITH-CUSTOMIZATION', 'nombre' => 'Global ya adaptada',
    'station_customization' => ['clave' => 'ST1_EXISTING', 'version' => 2],
]);
$writeHtml = $render($writeFixture, true, false, true, true, true, true);
$scheduleHeaderStart = strpos($writeHtml, 'id="schedule-configuration"');
$scheduleTableStart = strpos($writeHtml, 'caption class="visually-hidden">Turnos disponibles');
$scheduleTableEnd = $scheduleTableStart === false ? false : strpos($writeHtml, '</table>', $scheduleTableStart);
$scheduleTableHtml = $scheduleTableStart === false || $scheduleTableEnd === false
    ? ''
    : substr($writeHtml, $scheduleTableStart, $scheduleTableEnd - $scheduleTableStart);
$readOnlyTableStart = strpos($html, 'caption class="visually-hidden">Turnos disponibles');
$readOnlyTableEnd = $readOnlyTableStart === false ? false : strpos($html, '</table>', $readOnlyTableStart);
$readOnlyTableHtml = $readOnlyTableStart === false || $readOnlyTableEnd === false
    ? ''
    : substr($html, $readOnlyTableStart, $readOnlyTableEnd - $readOnlyTableStart);
$assert(str_contains($writeHtml, 'Acciones') && str_contains($writeHtml, 'Consultar horarios y turnos')
    && str_contains($writeHtml, 'data-bs-target="#policy-edit-modal"')
    && substr_count($writeHtml, 'Consultar horarios y turnos') === 1, 'attendance policy actions share one permission-aware dropdown without changing their targets');
$assert(substr_count($writeHtml, 'Nueva plantilla de estación') === 1
    && $scheduleHeaderStart !== false && $scheduleTableStart !== false && strpos($writeHtml, 'Nueva plantilla de estación') < $scheduleTableStart,
    'station-template action is shown once in the schedule card header');
$assert(!str_contains($scheduleTableHtml, 'TURNO-DEMO') && !str_contains($scheduleTableHtml, 'LEGACY-TURNO-1')
    && !str_contains($writeHtml, '>Nueva versión de')
    && str_contains($scheduleTableHtml, 'data-bs-target="#station-template-version-10"')
    && str_contains($scheduleTableHtml, '>Nueva versión</button>')
    && str_contains($scheduleTableHtml, 'data-bs-target="#station-template-create-modal"')
    && str_contains($scheduleTableHtml, '>Personalizar para esta estación</button>')
    && substr_count($scheduleTableHtml, '>Personalizar para esta estación</button>') === 1
    && !str_contains($scheduleTableHtml, 'data-bs-target="#station-template-version-11"')
    && str_contains($writeHtml, 'name="template_key" value="TURNO-DEMO"')
    && str_contains($writeHtml, 'name="operation" value="create"')
    && !str_contains($scheduleTableHtml, 'LEGACY-TURNO-1'),
    'authorized local rows expose immutable versioning while global rows create a station-only copy without exposing keys');
$assert(!str_contains($readOnlyTableHtml, 'data-bs-toggle="dropdown"')
    && !str_contains($readOnlyTableHtml, 'Acciones para'),
    'station template row actions are absent without the effective versioning permission and write gate');
$assert(str_contains($writeHtml, '/configuracion/puestos') && str_contains($writeHtml, '/configuracion/perfiles-lector')
    && str_contains($writeHtml, '/configuracion/plantillas') && str_contains($writeHtml, 'Guardar nueva vigencia'), 'authorized configuration surfaces use existing protected POST routes and confirmation forms');
$assert(str_contains($writeHtml, 'name="new_password"') && !str_contains($writeHtml, 'name="password" value=')
    && str_contains($writeHtml, 'Deja ambos campos vacíos para conservar la credencial existente'), 'reader credentials are write-only and blank preserves the stored secret');
$assert(str_contains($checadorController, 'saveConfigurationPosition') && str_contains($checadorController, 'updateConfigurationReaderProfile')
    && str_contains($checadorController, 'saveConfigurationScheduleTemplate')
    && str_contains($routes, "Route::auth(['ChecadorController', 'saveConfigurationPosition'])")
    && str_contains($routes, "Route::auth(['ChecadorController', 'updateConfigurationReaderProfile'])"), 'configuration mutations are authenticated and routed through Checador');
$assert(str_contains($writeService = file_get_contents(dirname(__DIR__) . '/app/Services/ChecadorConfigurationWriteService.php') ?: '', 'Capsule::connection()->transaction')
    && str_contains($writeService, 'POSITION_CATALOG_EDIT') && str_contains($writeService, 'READER_PROFILE_EDIT')
    && str_contains($writeService, 'op_rh_v2_m11_configuration_audit') && !str_contains($writeService, 'delete'), 'local catalog/profile writes require explicit M11 authorization, transactional audit, and no unsupported delete');
$assert(str_contains($checadorController, 'authorizeStationTemplateAdministration($workplaceId)')
    && str_contains($dailyReadService, 'createStationShiftTemplateVersion') && str_contains($routes, "'saveConfigurationScheduleTemplate'"), 'station template writes remain behind the explicit Encargado grant and API readback');
$assert(str_contains($writeHtml, 'station-template-create-modal') && str_contains($writeHtml, 'data-schedule-add-segment')
    && str_contains($dailyReadService, 'schedule_template_readback_unconfirmed'), 'template forms reuse the existing segment editor and do not claim an unconfirmed write');
$templateJs = file_get_contents(dirname(__DIR__) . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.schedule-template.init.js') ?: '';
$templateAccessService = file_get_contents(dirname(__DIR__) . '/app/Services/ChecadorAccessService.php') ?: '';
$assert(str_contains($scheduleTableHtml, 'data-template-name="TURNO 1 / ENCARGADO 1 — 06:00–14:00"')
    && str_contains($scheduleTableHtml, 'data-template-type="NOCTURNO"')
    && str_contains($scheduleTableHtml, 'data-template-segments=')
    && str_contains($templateJs, 'show.bs.modal') && str_contains($templateJs, 'setSegments(segments)')
    && str_contains($templateJs, "title.textContent = 'Personalizar turno para esta estación'")
    && str_contains($templateAccessService, 'authorizeStationTemplateAdministration')
    && str_contains($templateAccessService, 'STATION_TEMPLATE_ADMIN'),
    'global personalization preloads its displayed values in the shared create modal and server-side station administration authorization remains required');
$assert(str_contains($dailyReadService, "'scope' => 'STATION'")
    && str_contains($dailyReadService, 'createStationShiftTemplate(Usuario $user, int $workplaceId')
    && str_contains($dailyReadService, 'normalizeStationTemplateInput($workplaceId, $template, true)')
    && !str_contains($dailyReadService, 'createGlobalShiftTemplate'),
    'global personalization uses only the existing station-scoped create/readback contract');

printf("CHECADOR_V8M_CONFIGURATION: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);
