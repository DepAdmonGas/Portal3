<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// The configuration view now renders its standard CSRF-protected policy form.
// Give this isolated render harness a session just as the application does.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

$render = static function (array $configuration, bool $temporaryAccess = true, bool $demo = false, bool $canReadScheduling = true): string {
    $selectedWorkplaceId = 1;
    $demoMode = $demo;
    $canReadScheduling = $canReadScheduling;
    $canReadAttendancePolicy = true;
    $temporaryAccessAvailable = $temporaryAccess;
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
        'v2_schedule_edit' => false, 'template_admin' => true, 'positions_read' => true, 'positions_create' => false,
        'positions_edit' => false, 'positions_delete' => false,
    ],
    'mapping' => ['status' => 'unique_numlista_mapping', 'physical_station_id' => 2, 'name' => 'Interlomas', 'legacy_scope_authorized' => true],
    'api_templates' => [
        'status' => 'ready', 'message' => 'Contrato V2 leído.',
        'items' => [[
            'id' => 10, 'clave' => 'TURNO-DEMO', 'version' => 1,
            'nombre' => 'Turno <script>alert(1)</script>', 'tipo_turno' => 'DIURNO', 'es_activo' => 1,
            'segments' => [['ordinal' => 1, 'tipo_segmento' => 'TRABAJO', 'hora_inicio_local' => '08:00',
                'hora_fin_local' => '16:00', 'dia_offset_fin' => 0]],
        ]],
    ],
    'api_history' => [
        'status' => 'ready', 'message' => 'Lectura V2 sin mutaciones.',
        'effective_schedule' => ['estado_jornada' => 'SCHEDULED'],
        'assignments' => [['dia_semana' => 'LUNES', 'valido_desde' => '2026-10-19', 'valido_hasta' => null,
            'id_plantilla' => 10, 'es_activo' => 1]],
    ],
    'legacy_schedules' => ['status' => 'legacy_catalog', 'items' => [
        ['id' => 3, 'title' => 'Horario registrado anteriormente', 'start' => '09:00', 'end' => '17:00'],
    ]],
    'legacy_rules' => ['status' => 'legacy_unconfigured', 'configured' => false, 'retardo' => 0, 'incidencia' => 0],
    'legacy_profiles' => ['status' => 'legacy_metadata_only', 'active_count' => 2],
    'positions' => ['status' => 'current_catalog', 'items' => [['id' => 4, 'name' => 'Encargado']]],
    'device_auth' => ['status' => 'not_integrated', 'message' => 'Device Auth V2 no está integrado aquí.'],
];

$html = '';
$renderError = null;
try {
    $html = $render($fixture);
} catch (Throwable $exception) {
    $renderError = $exception::class . ': ' . $exception->getMessage();
}

$assert($renderError === null
    && !str_contains($html, 'Consulta de turnos, reglas y catálogos del centro autorizado.')
    && !str_contains($html, 'Centro autorizado:')
    && !str_contains($html, 'Localidad canónica:')
    && !str_contains($html, 'Asistencia del día')
    && !preg_match('/<a\b[^>]*href="#[^"]*"[^>]*>(?:Turnos y horarios|Reglas de asistencia|Personal y puestos|Lectores y accesos)<\/a>/u', $html)
    && !str_contains($html, 'id="configuration-navigation"')
    && !preg_match('/<a\b[^>]*href="[^"]*">\s*Programación semanal\s*<\/a>/u', $html)
    && !preg_match('/<h1\b[^>]*>\s*Configuración de Checador\s*<\/h1>/u', $html), 'redundant header copy and navigation are absent while the institutional title remains layout-owned');
$assert(!str_contains($html, 'id="schedule-configuration"')
    && !str_contains($html, 'id="attendance-rules"')
    && str_contains($html, 'id="attendance-policy-v2"')
    && str_contains($html, 'id="people-positions"')
    && str_contains($html, 'id="reader-access"')
    && str_contains($html, 'Retardos e incidencias'), 'M11 removes duplicate schedule/legacy-rule blocks and retains its administrative sections');
$scheduleHref = '';
if (preg_match('/<a\b[^>]*href="([^"]*)"[^>]*>.*?Horarios y turnos<\/a>/u', $html, $scheduleMatch) === 1) {
    $scheduleHref = html_entity_decode($scheduleMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
$scheduleParams = [];
parse_str((string) parse_url($scheduleHref, PHP_URL_QUERY), $scheduleParams);
$assert(parse_url($scheduleHref, PHP_URL_PATH) === '/departamento-operativo/recursos-humanos/checador/horarios-turnos'
    && $scheduleParams === []
    && !array_key_exists('workplace_id', $scheduleParams), 'single M04 Horarios y turnos link uses the authorized institutional route and session locality');
$assert(!str_contains($html, 'Programación semanal') && !str_contains($html, 'Horario efectivo e historial')
    && !str_contains($html, 'Registros de horarios anteriores') && !str_contains($html, 'Centro físico relacionado:'), 'M11 no duplicates M03/M04 schedule and mapping information');
$assert(!str_contains($html, 'Tolerancia registrada') && !str_contains($html, 'Plazo de aclaración')
    && !str_contains($html, 'No hay valores guardados para este centro.'), 'legacy rule values are not displayed in M11');
$assert(str_contains($html, 'Personal y puestos') && str_contains($html, 'Encargado'), 'positions section uses the existing institutional catalog projection');
$assert(str_contains($html, 'Perfil de aplicación del lector') && str_contains($html, 'Acceso de lectores'), 'reader profile and device access are presented in the integrated Checador page');
$assert(str_contains($html, 'Acceso temporal autorizado'), 'temporary access link is rendered only for the authorized fixture');
$assert(!str_contains($html, 'Turno &lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($html, '<script>alert(1)</script>'), 'schedule catalogue data is no longer rendered by M11');

$noTemporaryAccessHtml = $render($fixture, false);
$assert(!str_contains($noTemporaryAccessHtml, 'Acceso temporal autorizado'), 'temporary-access section is omitted when its independent ACL is absent');
$noSchedulingReadHtml = $render($fixture, true, false, false);
$assert(!str_contains($noSchedulingReadHtml, 'schedule_history=1')
    && !str_contains($noSchedulingReadHtml, '>Horarios y turnos</a>'), 'M04 link is omitted without independent schedule read permission');

$outOfLegacyScope = $fixture;
$outOfLegacyScope['mapping']['legacy_scope_authorized'] = false;
$outOfLegacyScopeHtml = $render($outOfLegacyScope);
$assert(!str_contains($outOfLegacyScopeHtml, 'Horario registrado anteriormente')
    && !str_contains($outOfLegacyScopeHtml, 'No está autorizada la consulta de estos registros'), 'M11 omits Legacy schedule details regardless of mapping scope');

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
$biometricService = file_get_contents(dirname(__DIR__) . '/app/Services/BiometricosService.php') ?: '';
$biometricController = file_get_contents(dirname(__DIR__) . '/app/Controllers/BiometricosController.php') ?: '';
$checadorController = file_get_contents(dirname(__DIR__) . '/app/Controllers/ChecadorController.php') ?: '';
$routes = file_get_contents(dirname(__DIR__) . '/routes/web.php') ?: '';
$profileJs = file_get_contents(dirname(__DIR__) . '/public/assets/js/departamento-operativo/2-recursos-humanos/biometricos-config-perfil.actions.init.js') ?: '';
$operationalLayout = file_get_contents(dirname(__DIR__) . '/app/Views/layouts/departamento-operativo.php') ?: '';

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
$assert(str_contains($checadorController, "'title' => 'Configuración de Checador'")
    && str_contains($operationalLayout, '<h4 class="fw-semibold mt-3"><?= $title; ?></h4>'), 'the institutional layout retains the Configuration page title');
$assert(substr_count($checadorController, "Breadcrumb::add('Configuración', '')") === 1
    && !str_contains($html, 'breadcrumb'), 'the controller and shared layout remain the single breadcrumb source');
$assert(str_contains($checadorController, '$query[\'schedule_week\']')
    && str_contains($checadorController, '$requestedWorkplace = $this->sessionWorkplaceId($access);')
    && str_contains($checadorController, "ChecadorAccessService::authorize($" . 'requestedWorkplace)')
    && str_contains($checadorController, "authorizeScheduling(\$requestedWorkplace, 'leer')"), 'weekly navigation resolves the selected locality from session and rechecks scope and read permission');
$assert(!str_contains($readService, 'getShiftTemplates') && !str_contains($readService, 'getEffectiveSchedule')
    && !str_contains($readService, 'getShiftAssignments') && !str_contains($readService, 'RhLocalidadesHorario::query()')
    && !preg_match('/\b(?:INSERT|UPDATE|DELETE)\b/i', $readService), 'M11 does not query duplicate V2 or Legacy schedule data and performs no schedule writes');
$assert(str_contains($readService, 'readM11LegacyPolicyReference($workplaceId)')
    && str_contains($checadorController, "\$configuration['legacy_rules']['retardo']")
    && str_contains($checadorController, "\$configuration['legacy_rules']['incidencia']"), 'Legacy policy reference remains available only for the existing M11 form defaults');

printf("CHECADOR_V8M_CONFIGURATION: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);
