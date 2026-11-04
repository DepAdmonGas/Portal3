<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\ModuleStationService;
use App\Services\MultiestacionService;
use App\Services\ProveedoresService;
use App\Services\RefaccionesService as Service;
use App\Services\RefaccionesSalidaPdfService;
use Dompdf\Dompdf;
use Dompdf\Options;

class RefaccionesController extends BaseController
{
    private const BASE_URL = '/departamento-operativo/almacen/refacciones';

    public function index(): void
    {
        $this->renderSection(MultiestacionService::isEnabled() ? 'dashboard' : 'inventario');
    }

    public function section(string $section): void
    {
        $esMultiestacion = MultiestacionService::isEnabled();
        $section = strtolower(trim($section));
        if (!isset(Service::SECCIONES[$section])) {
            View::render('errors/404', [], $esMultiestacion ? 'refacciones' : 'departamento-operativo');
            return;
        }
        if (!$esMultiestacion && !in_array($section, ['inventario', 'solicitud-calcomanias'], true)) {
            http_response_code(403);
            View::render('errors/403', ['title' => 'Acceso restringido'], 'departamento-operativo');
            return;
        }
        $this->renderSection($section);
    }

    private function renderSection(string $section): void
    {
        $esMultiestacion = MultiestacionService::isEnabled();
        $title = $section === 'calendarios'
            ? 'Calendario'
            : Service::SECCIONES[$section]['nombre'];
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        // Solicitud de calcomanías se accede directamente desde Refacciones;
        // las demás secciones conservan la jerarquía de Almacén.
        if ($section !== 'solicitud-calcomanias') {
            Breadcrumb::add('Almacén', '/departamento-operativo/almacen');
        }
        Breadcrumb::add('Refacciones', self::BASE_URL);
        if ($section !== 'dashboard') Breadcrumb::add($title, '');

        $permissions = Service::getPermisos();
        if (!$this->guardModuleAccess(Service::MODULE_KEY, 'Refacciones', 'refacciones')) {
            return;
        }
        if (!$permissions['leer']) {
            View::render('errors/404', [], $esMultiestacion ? 'refacciones' : 'departamento-operativo');
            return;
        }

        Service::procesarRetornosVencidos();

        $context = Service::getContexto();
        $locations = Service::getUbicacionesDisponibles();
        $moduleConfig = ModuleStationService::getConfig(Service::MODULE_KEY) ?? [];
        $data = match ($section) {
            'dashboard' => Service::getDashboard(),
            'inventario' => Service::getInventario(),
            'entradas' => Service::getMovimientos('entrada'),
            'salidas' => Service::getSalidas(),
            'productos' => Service::getProductos('refaccion', false),
            'calcomanias' => Service::getProductos('calcomania', false),
            'solicitud-calcomanias' => [],
            'calendarios' => Service::getCalendario(),
            'reportes' => Service::getReporteGeneral(),
            default => [],
        };
        $productCategories = $section === 'productos' ? Service::getCategoriasProducto() : [];
        $providerOptions = in_array($section, ['entradas', 'salidas'], true)
            ? array_values(array_filter(ProveedoresService::getData(), static fn (array $provider): bool => trim((string) ($provider['razon_social'] ?? '')) !== ''))
            : [];

        $sectionView = match ($section) {
            'dashboard', 'inventario', 'entradas', 'salidas', 'reportes', 'productos', 'calcomanias', 'calendarios', 'solicitud-calcomanias' => $section,
            default => 'generic',
        };
        $items = is_array($data) && array_is_list($data) ? $data : [];
        $dashboardCards = [
            ['label' => 'Productos', 'value' => number_format((int) ($data['productos'] ?? 0)), 'icon' => 'ti ti-package', 'iconColor' => 'primary', 'style' => 'default'],
            ['label' => 'Valor del inventario', 'value' => '$' . number_format((float) ($data['valor_inventario'] ?? 0), 2), 'icon' => 'ti ti-currency-dollar', 'iconColor' => 'primary', 'style' => 'primary'],
            ['label' => 'Entradas del mes', 'value' => number_format((int) ($data['entradas_mes'] ?? 0)), 'icon' => 'ti ti-download', 'iconColor' => 'success', 'style' => 'success'],
            ['label' => 'Salidas del mes', 'value' => number_format((int) ($data['salidas_mes'] ?? 0)), 'icon' => 'ti ti-upload', 'iconColor' => 'info', 'style' => 'secondary'],
            ['label' => 'Stock bajo mínimo', 'value' => number_format((int) ($data['stock_bajo'] ?? 0)), 'icon' => 'ti ti-alert-triangle', 'iconColor' => 'warning', 'style' => 'warning'],
        ];
        $inventoryGroups = [];
        $inventoryTotal = 0.0;
        $inventoryIsAll = $section === 'inventario' && ($context['id'] ?? null) === null;
        if ($section === 'inventario') {
            $inventoryTotal = array_sum(array_map(
                static fn (array $row): float => (float) $row['existencia'] * (float) $row['costo_unitario'],
                $items
            ));
            foreach ($locations as $locationId => $locationName) {
                $rows = array_values(array_filter(
                    $items,
                    static fn (array $row): bool => (int) $row['id_estacion'] === (int) $locationId
                ));
                if ($inventoryIsAll) {
                    $inventoryGroups[] = [
                        'id' => (int) $locationId,
                        'name' => (string) $locationName,
                        'rows' => $rows,
                        'value' => array_sum(array_map(
                            static fn (array $row): float => (float) $row['existencia'] * (float) $row['costo_unitario'],
                            $rows
                        )),
                    ];
                }
            }
        }
        $calendarEvents = [];
        if ($section === 'calendarios') {
            foreach ($items as $event) {
                $start = (string) ($event['fecha'] ?? '');
                if (!empty($event['hora'])) {
                    $start .= 'T' . substr((string) $event['hora'], 0, 8);
                }
                $calendarEvents[] = [
                    'id' => (string) ($event['id'] ?? ''),
                    'title' => (string) ($event['titulo'] ?? ''),
                    'start' => $start,
                    'allDay' => empty($event['hora']),
                    'extendedProps' => [
                        'tipo' => (string) ($event['tipo_evento'] ?? 'otro'),
                        'ubicacion' => (string) ($event['ubicacion'] ?? (isset($event['id_estacion']) ? ($locations[(int) $event['id_estacion']] ?? 'Ubicación') : ($moduleConfig['placeholder'] ?? 'Todas las ubicaciones'))),
                        'estado' => (string) ($event['estado'] ?? 'pendiente'),
                        'hora' => (string) ($event['hora'] ?? ''),
                        'categoria' => (string) ($event['categoria'] ?? ''),
                        'proveedor_externo' => (string) ($event['proveedor_externo'] ?? ''),
                        'responsable' => (string) ($event['correo_responsable'] ?? ''),
                        'notas' => (string) ($event['notas'] ?? ''),
                    ],
                ];
            }
        }
        $calendarTotals = [
            'pendientes' => count(array_filter($calendarEvents, static fn (array $event): bool => strtolower((string) ($event['extendedProps']['estado'] ?? '')) === 'pendiente')),
            'finalizados' => count(array_filter($calendarEvents, static fn (array $event): bool => in_array(strtolower((string) ($event['extendedProps']['estado'] ?? '')), ['finalizado', 'finalizada', 'completado', 'completada'], true))),
            'cancelados' => count(array_filter($calendarEvents, static fn (array $event): bool => in_array(strtolower((string) ($event['extendedProps']['estado'] ?? '')), ['cancelado', 'cancelada'], true))),
            'total' => count($calendarEvents),
        ];

        View::render('departamento-operativo/4-almacen/refacciones/index', [
            'title' => $title,
            'section' => $section,
            'sectionView' => $sectionView,
            'sections' => Service::SECCIONES,
            'sectionName' => Service::SECCIONES[$section]['nombre'] ?? 'Refacciones',
            'items' => $items,
            'solicitudesCalcomanias' => $section === 'solicitud-calcomanias' ? \App\Services\RefaccionesSolicitudCalcomaniasService::listar() : [],
            'puedeCrearSolicitudCalcomanias' => $section === 'solicitud-calcomanias' && Service::puedeCrearSolicitudCalcomanias(),
            'solicitudCalcomaniasMultiestacion' => $esMultiestacion,
            'solicitudCalcomaniasPuedeEliminar' => $permissions['eliminar'],
            'productosCalcomaniasSolicitud' => $section === 'solicitud-calcomanias' ? Service::getProductos('calcomania', false) : [],
            'productCategories' => $productCategories,
            'providerOptions' => $providerOptions,
            'dashboardCards' => $dashboardCards,
            'dashboardData' => $section === 'dashboard' ? $data : [],
            'inventoryGroups' => $inventoryGroups,
            'inventoryTotal' => $inventoryTotal,
            'inventoryIsAll' => $inventoryIsAll,
            'calendarEvents' => $calendarEvents,
            'calendarTotals' => $calendarTotals,
            'isMultiestacion' => $esMultiestacion,
            'canSendCalendarReminders' => $section === 'calendarios'
                && $esMultiestacion
                && (int) (\App\Core\Auth::user()?->id_puesto ?? 0) === 13
                && $permissions['editar'],
            'refaccionesOnlySidebar' => true,
            'refaccionesSidebarTitle' => $esMultiestacion ? 'Refacciones' : 'OPCIONES',
            'refaccionesSidebarItems' => $this->refaccionesSidebarItems($esMultiestacion),
            // El badge del selector pertenece únicamente a las secciones que
            // lo muestran. Dashboard, Productos y Calcomanías conservan el
            // badge global del usuario sin heredar la selección de Entradas.
            'moduleStationKey' => in_array($section, ['dashboard', 'productos', 'calcomanias', 'reportes'], true)
                ? null
                : Service::MODULE_KEY,
            'ocultarSelectorEstacion' => !$esMultiestacion || in_array($section, ['dashboard', 'productos', 'calcomanias', 'reportes'], true),
            'nombreBadgeEstacion' => '',
            'productLocationLabel' => 'Almacén central',
            'mostrarBadgeGlobal' => in_array($section, ['dashboard', 'productos', 'calcomanias', 'reportes'], true),
            'moduleStationPlaceholder' => $moduleConfig['placeholder'] ?? null,
            'allLocationsLabel' => $moduleConfig['placeholder'] ?? 'Todas las ubicaciones',
            'baseUrl' => self::BASE_URL,
            'locations' => $locations,
            'context' => $context,
            'allowAllLocations' => (bool) ($moduleConfig['allow_all'] ?? false),
            'productOptions' => $esMultiestacion
                ? array_values(array_filter(
                    Service::getProductos(null, false),
                    static fn (array $product): bool => in_array(
                        $product['tipo_articulo'] ?? '',
                        $section === 'entradas' ? ['refaccion'] : ['refaccion', 'calcomania'],
                        true
                    )
                ))
                : [],
            'data' => $data,
            'permissions' => $permissions,
            'canSendReportEmail' => $section === 'reportes' && (int) (\App\Core\Auth::user()?->id_puesto ?? 0) === 13,
            'scripts' => array_merge(
                $section === 'dashboard'
                    ? ['/assets/libs/apexcharts/dist/apexcharts.min.js']
                    : [],
                $section === 'calendarios'
                    ? ['/assets/js/vendor.min.js', '/assets/libs/select2/dist/js/select2.min.js', '/assets/libs/fullcalendar/index.global.min.js', '/assets/js/departamento-operativo/4-almacen/refacciones-calendar.init.js?v=' . time()]
                    : [],
                in_array($section, ['dashboard', 'productos', 'calcomanias', 'entradas', 'inventario', 'reportes', 'solicitud-calcomanias'], true)
                    ? array_merge(
                        ['/assets/js/vendor.min.js'],
                        in_array($section, ['productos', 'calcomanias', 'entradas', 'reportes'], true) ? ['/assets/libs/select2/dist/js/select2.min.js'] : [],
                        ['/assets/libs/datatables.net/js/jquery.dataTables.min.js']
                    )
                    : [],
                $section === 'salidas'
                    ? ['/assets/js/vendor.min.js', '/assets/libs/select2/dist/js/select2.min.js', '/assets/libs/datatables.net/js/jquery.dataTables.min.js', '/assets/js/departamento-operativo/4-almacen/refacciones-salidas.init.js?v=' . time()]
                    : [],
                $section === 'solicitud-calcomanias'
                    ? ['/assets/js/departamento-operativo/4-almacen/refacciones-solicitud-calcomanias.init.js?v=' . time()]
                    : [],
                ['/assets/js/core/module-station-selector.js?v=' . time(), '/assets/js/departamento-operativo/4-almacen/refacciones.init.js?v=' . time()]
            ),
            'links' => array_merge(
                ['/assets/css/departamento-operativo/4-almacen/refacciones.css?v=' . (@filemtime(PUBLIC_PATH . '/assets/css/departamento-operativo/4-almacen/refacciones.css') ?: 1)],
                in_array($section, ['dashboard', 'productos', 'calcomanias', 'entradas', 'inventario', 'reportes', 'solicitud-calcomanias'], true)
                    ? array_merge(['/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'], in_array($section, ['productos', 'calcomanias', 'entradas'], true) ? ['/assets/libs/select2/dist/css/select2.min.css', '/assets/css/select2-modal.css'] : [])
                    : (in_array($section, ['salidas', 'calendarios'], true) ? array_merge($section === 'salidas' ? ['/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'] : [], ['/assets/libs/select2/dist/css/select2.min.css', '/assets/css/select2-modal.css']) : [])
            ),
        ], $esMultiestacion ? 'refacciones' : 'departamento-operativo');
    }

    public function setContext(): void
    {
        $permissions = Service::getPermisos();
        if (!$permissions['leer']) {
            JsonResponse::forbidden('No tienes permiso para consultar Refacciones.');
        }

        $rawId = Request::input('id_estacion');
        $id = ($rawId === null || $rawId === '') ? null : filter_var($rawId, FILTER_VALIDATE_INT);
        if ($rawId !== null && $rawId !== '' && $id === false) {
            JsonResponse::custom(['success' => false, 'message' => 'La ubicación seleccionada no es válida.'], 422);
        }

        if (!Service::cambiarContexto($id === null ? null : (int) $id)) {
            JsonResponse::forbidden('No tienes acceso a la ubicación seleccionada.');
        }

        JsonResponse::custom(['success' => true, 'url' => self::BASE_URL]);
    }

    public function consultarReporte(): void
    {
        $permissions = Service::getPermisos();
        if (!MultiestacionService::isEnabled()
            || !ModuleStationService::isAvailable(Service::MODULE_KEY)
            || !$permissions['leer']) {
            JsonResponse::forbidden('No tienes permiso para consultar Refacciones.');
        }

        $periodo = (string) Request::input('periodo', '');
        $year = filter_var(Request::input('year'), FILTER_VALIDATE_INT);
        $month = filter_var(Request::input('month'), FILTER_VALIDATE_INT);
        if (!in_array($periodo, ['mensual', 'semestral', 'anual'], true)
            || $year === false || $year < 2000 || $year > 2100
            || $month === false || $month < 1 || $month > 12) {
            JsonResponse::custom(['success' => false, 'message' => 'Selecciona un período válido para el reporte.'], 422);
        }

        JsonResponse::custom([
            'success' => true,
            'data' => Service::getReporteGeneral($periodo, (int) $year, (int) $month),
        ]);
    }

    public function enviarReporteCorreo(): void
    {
        $user = \App\Core\Auth::user();
        $permissions = Service::getPermisos();
        if (!$user || (int) ($user->id_puesto ?? 0) !== 13
            || !MultiestacionService::isEnabled()
            || !ModuleStationService::isAvailable(Service::MODULE_KEY)
            || !$permissions['leer']) {
            JsonResponse::forbidden('No tienes permiso para enviar reportes de Refacciones.');
        }

        $periodo = (string) Request::input('periodo', '');
        $year = filter_var(Request::input('year'), FILTER_VALIDATE_INT);
        $month = filter_var(Request::input('month'), FILTER_VALIDATE_INT);
        if (!in_array($periodo, ['mensual', 'semestral', 'anual'], true)
            || $year === false || $year < 2024 || $year > (int) date('Y')
            || $month === false || $month < 1 || $month > 12) {
            JsonResponse::custom(['success' => false, 'message' => 'Selecciona un período válido para enviar el reporte.'], 422);
        }

        // La vista de Reportes presenta el consolidado de todas las ubicaciones configuradas.
        // El correo manual debe utilizar exactamente el mismo alcance y cálculo.
        $result = \App\Services\RefaccionesReporteCorreoService::enviar($periodo, (int) $year, (int) $month, true);
        JsonResponse::custom($result, $result['success'] ? 200 : 500);
    }

    public function storeProduct(): void
    {
        $result = Service::crearProducto($_POST, $_FILES['imagen'] ?? []);
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function priceHistory(int $id): void
    {
        if (!Service::getPermisos()['leer']) {
            JsonResponse::forbidden('No tienes permiso para consultar el historial de precios.');
        }
        $history = Service::getHistorialPrecios($id);
        if ($history === null) {
            JsonResponse::notFound('El producto no está disponible para consultar su historial.');
        }
        JsonResponse::custom(['success' => true] + $history);
    }

    public function updateProduct(): void
    {
        $result = Service::actualizarProducto($_POST, $_FILES['imagen'] ?? []);
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function deleteProduct(): void
    {
        $result = Service::eliminarProducto(Request::all());
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function storeMovement(): void
    {
        $result = Service::registrarMovimiento(Request::all());
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function nuevaSalidaFormulario(int $id): void
    {
        if (!$this->guardModuleAccess(Service::MODULE_KEY, 'Refacciones', 'refacciones')) return;
        $permissions = Service::getPermisos();
        $context = Service::getContexto();
        $locations = Service::getUbicacionesDisponibles();
        if (!$permissions['crear']) {
            http_response_code(403);
            View::render('errors/403', ['title' => 'Acceso restringido'], 'departamento-operativo');
            return;
        }
        if (!isset($locations[$id]) || (int) ($context['id'] ?? 0) !== $id) {
            JsonResponse::forbidden('Selecciona una estación o departamento específico antes de registrar una salida.');
        }

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Almacén', '/departamento-operativo/almacen');
        Breadcrumb::add('Refacciones', self::BASE_URL);
        Breadcrumb::add('Salidas', self::BASE_URL . '/salidas');
        Breadcrumb::add('Nueva salida', '');

        View::render('departamento-operativo/4-almacen/refacciones/index', [
            'title' => 'Nueva salida', 'section' => 'salidas', 'sectionView' => 'salidas-formulario',
            'sectionName' => 'Salidas', 'sections' => Service::SECCIONES, 'refaccionesOnlySidebar' => true,
            'refaccionesSidebarTitle' => 'Refacciones', 'refaccionesSidebarItems' => $this->refaccionesSidebarItems(),
            // El contexto multiestación del módulo sigue activo y valida la
            // estación origen, pero el formulario no permite cambiarla aquí.
            'moduleStationKey' => Service::MODULE_KEY,
            'ocultarSelectorEstacion' => true,
            'nombreBadgeEstacion' => (string) $locations[$id],
            'mostrarBadgeGlobal' => false,
            'baseUrl' => self::BASE_URL, 'context' => $context, 'locations' => $locations,
            'salidaOriginId' => $id, 'salidaOriginName' => $locations[$id],
            'salidaEdit' => false, 'salidaRecord' => null,
            'salidaProductOptions' => Service::getProductosParaSalida($id),
            'calendarEvents' => [], 'permissions' => $permissions,
            'scripts' => ['/assets/js/vendor.min.js?v=' . time(), '/assets/libs/select2/dist/js/select2.min.js', '/assets/libs/signature_pad/docs/js/signature_pad.umd.min.js', '/assets/js/departamento-operativo/4-almacen/refacciones-salidas.init.js?v=' . time()],
            'links' => ['/assets/libs/select2/dist/css/select2.min.css', '/assets/css/select2-modal.css', '/assets/css/departamento-operativo/4-almacen/refacciones.css'],
        ], 'refacciones');
    }

    public function salidaFormulario(int $id): void
    {
        if (!$this->guardModuleAccess(Service::MODULE_KEY, 'Refacciones', 'refacciones')) return;
        $permissions = Service::getPermisos();
        if (!$permissions['editar']) {
            http_response_code(403);
            View::render('errors/403', ['title' => 'Acceso restringido'], 'departamento-operativo');
            return;
        }
        $salida = Service::getSalida($id, false);
        if (!$salida || (int) ($salida['estatus'] ?? -1) !== 0 || empty($salida['es_orden_mantenimiento'])) {
            JsonResponse::notFound('La salida no está pendiente o no pertenece a tus ubicaciones autorizadas.');
        }
        $locations = Service::getUbicacionesDisponibles();
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Almacén', '/departamento-operativo/almacen');
        Breadcrumb::add('Refacciones', self::BASE_URL);
        Breadcrumb::add('Salidas', self::BASE_URL . '/salidas');
        Breadcrumb::add('Editar salida', '');

        View::render('departamento-operativo/4-almacen/refacciones/index', [
            'title' => 'Editar salida', 'section' => 'salidas', 'sectionView' => 'salidas-formulario',
            'sectionName' => 'Salidas', 'sections' => Service::SECCIONES, 'refaccionesOnlySidebar' => true,
            'refaccionesSidebarTitle' => 'Refacciones', 'refaccionesSidebarItems' => $this->refaccionesSidebarItems(),
            'moduleStationKey' => null, 'ocultarSelectorEstacion' => true, 'nombreBadgeEstacion' => '',
            'mostrarBadgeGlobal' => true, 'baseUrl' => self::BASE_URL, 'context' => Service::getContexto(),
            'locations' => $locations, 'salidaEdit' => true, 'salidaRecord' => $salida,
            'salidaOriginId' => (int) $salida['id_estacion_origen'], 'salidaOriginName' => $salida['origen'],
            'salidaProductOptions' => [], 'calendarEvents' => [], 'permissions' => $permissions,
            'scripts' => ['/assets/js/vendor.min.js?v=' . time(), '/assets/libs/signature_pad/docs/js/signature_pad.umd.min.js', '/assets/js/departamento-operativo/4-almacen/refacciones-salidas.init.js?v=' . time()],
            'links' => ['/assets/css/departamento-operativo/4-almacen/refacciones.css'],
        ], 'refacciones');
    }

    public function salidaFirma(int $id): void
    {
        if (!$this->guardModuleAccess(Service::MODULE_KEY, 'Refacciones', 'refacciones')) return;
        $permissions = Service::getPermisos();
        if (!$permissions['leer']) JsonResponse::forbidden('No tienes permiso para consultar Refacciones.');
        $salida = Service::getSalida($id, false);
        if (!$salida) JsonResponse::notFound('La salida no está disponible o no pertenece a tus ubicaciones autorizadas.');

        $isPending = (int) $salida['estatus'] === 1;
        $isMaintenanceAuthorPending = (int) $salida['estatus'] === 0 && !empty($salida['es_orden_mantenimiento']);
        if (($isPending || $isMaintenanceAuthorPending) && !$permissions['editar']) JsonResponse::forbidden('No tienes permiso para firmar esta salida.');
        $employees = $isPending ? Service::getEncargadosSalida((int) $salida['id_estacion_destino']) : [];
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Almacén', '/departamento-operativo/almacen');
        Breadcrumb::add('Refacciones', self::BASE_URL);
        Breadcrumb::add('Salidas', self::BASE_URL . '/salidas');
        Breadcrumb::add('Firma de recepción', '');

        View::render('departamento-operativo/4-almacen/refacciones/index', [
            'title' => 'Firma de recepción (' . $salida['folio'] . ')', 'section' => 'salidas', 'sectionView' => 'salidas-firma',
            'sectionName' => 'Salidas', 'sections' => Service::SECCIONES, 'refaccionesOnlySidebar' => true,
            'refaccionesSidebarTitle' => 'Refacciones', 'refaccionesSidebarItems' => $this->refaccionesSidebarItems(),
            'moduleStationKey' => null, 'ocultarSelectorEstacion' => true, 'mostrarBadgeGlobal' => true,
            'baseUrl' => self::BASE_URL, 'salida' => $salida, 'salidaEmployees' => $employees,
            'salidaPending' => $isPending,
            'salidaElaboraPendiente' => $isMaintenanceAuthorPending,
            'calendarEvents' => [], 'permissions' => $permissions,
            'scripts' => array_merge(['/assets/js/vendor.min.js?v=' . time()], $isPending ? ['/assets/libs/select2/dist/js/select2.min.js'] : [], ['/assets/libs/signature_pad/docs/js/signature_pad.umd.min.js', '/assets/js/departamento-operativo/4-almacen/refacciones-salidas.init.js?v=' . time()]),
            'links' => $isPending ? ['/assets/libs/select2/dist/css/select2.min.css', '/assets/css/select2-modal.css'] : [],
        ], 'refacciones');
    }

    public function descargarSalidaPdf(int $id): void
    {
        if (!$this->guardModuleAccess(Service::MODULE_KEY, 'Refacciones', 'refacciones')) return;
        if (!Service::getPermisos()['leer']) JsonResponse::forbidden('No tienes permiso para consultar las salidas.');

        $salida = Service::getSalida($id, false);
        if (!$salida || (int) ($salida['estatus'] ?? 0) !== 2) {
            JsonResponse::notFound('El PDF sólo está disponible para salidas finalizadas dentro de tus ubicaciones autorizadas.');
            return;
        }

        $pdf = RefaccionesSalidaPdfService::generar($salida);
        $filename = RefaccionesSalidaPdfService::nombreArchivo($salida['folio'] ?? 'salida');
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($pdf, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->stream($filename, ['Attachment' => true]);
    }

    public function storeSalida(): void
    {
        $result = Service::crearSalida(Request::all());
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function actualizarSalidaPendiente(): void
    {
        $result = Service::actualizarSalidaPendiente(Request::all());
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function firmarSalida(): void
    {
        $result = Service::firmarSalida(Request::all());
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function actualizarRetornoSalida(): void
    {
        $result = Service::actualizarRetornoSalida(Request::all());
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function procesarRetornosVencidos(): void
    {
        if (!$this->guardModuleAccess(Service::MODULE_KEY, 'Refacciones', 'refacciones') || !Service::getPermisos()['leer']) {
            JsonResponse::custom(['success' => false, 'message' => 'No tienes permiso para consultar las salidas.', 'code' => 403], 403);
            return;
        }
        $result = Service::procesarRetornosVencidos();
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function eliminarSalida(): void
    {
        $result = Service::eliminarSalida(Request::all());
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function updateEntry(): void
    {
        $result = Service::actualizarEntrada(Request::all());
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function deleteEntry(): void
    {
        $result = Service::eliminarEntrada(Request::all());
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function solicitudCalcomanias(int $id): void
    {
        if (!$this->guardModuleAccess(Service::MODULE_KEY, 'Solicitud de calcomanías', 'departamento-operativo')) return;
        if (!Service::getPermisos()['leer']) {
            http_response_code(403);
            View::render('errors/403', ['title' => 'Acceso restringido'], 'departamento-operativo');
            return;
        }
        if ((string) Request::get('nuevo', '') === '1') {
            $draft = \App\Services\RefaccionesSolicitudCalcomaniasService::crearBorrador();
            if (empty($draft['success'])) {
                http_response_code((int) ($draft['code'] ?? 403));
                View::render('errors/403', ['title' => 'Acceso restringido'], 'departamento-operativo');
                return;
            }
            header('Location: ' . $draft['url'], true, 303);
            exit;
        }
        $solicitud = \App\Services\RefaccionesSolicitudCalcomaniasService::obtener($id);
        if ($solicitud === null) {
            http_response_code(404);
            View::render('errors/404', [], 'departamento-operativo');
            return;
        }

        $title = 'Solicitud de calcomanías (#00' . $id . ')';
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Refacciones', self::BASE_URL);
        Breadcrumb::add('Solicitud de calcomanías', self::BASE_URL . '/solicitud-calcomanias');
        Breadcrumb::add($title, '');

        View::render('departamento-operativo/4-almacen/refacciones/solicitud-calcomanias-detalle', [
            'title' => $title, 'solicitud' => $solicitud,
            'idUbicacion' => (int) $solicitud['id_ubicacion'],
            'nombreUbicacion' => (string) $solicitud['nombre_ubicacion'],
            // ModuleStationService genera el único badge superior; no pasar
            // nombreContexto porque el layout también lo dibuja por separado.
            'nombreBadgeEstacion' => (string) $solicitud['nombre_ubicacion'],
            'productosCalcomanias' => Service::getProductos('calcomania', false),
            'puedeEditarSolicitud' => (int) ($solicitud['estado'] ?? -1) === 0
                && (int) ($solicitud['id_usuario'] ?? 0) === (int) (\App\Core\Auth::user()->id ?? 0),
            'puedeFirmarEncargado' => (int) ($solicitud['estado'] ?? -1) === 0
                && (int) ($solicitud['id_usuario'] ?? 0) === (int) (\App\Core\Auth::user()->id ?? 0),
            'refaccionesOnlySidebar' => true,
            'refaccionesSidebarTitle' => MultiestacionService::isEnabled() ? 'Refacciones' : 'OPCIONES',
            'refaccionesSidebarItems' => $this->refaccionesSidebarItems(),
            'moduleStationKey' => Service::MODULE_KEY, 'ocultarSelectorEstacion' => true, 'mostrarBadgeGlobal' => false,
            'baseUrl' => self::BASE_URL,
            'scripts' => ['/assets/js/vendor.min.js', '/assets/js/departamento-operativo/4-almacen/refacciones-solicitud-calcomanias.init.js?v=' . time()],
            'links' => ['/assets/css/departamento-operativo/4-almacen/refacciones.css'],
        ], 'departamento-operativo');
    }

    public function solicitudCalcomaniasFirma(int $id): void
    {
        if (!$this->guardModuleAccess(Service::MODULE_KEY, 'Solicitud de calcomanías', 'departamento-operativo')) return;
        if (!Service::getPermisos()['leer']) {
            http_response_code(403);
            View::render('errors/403', ['title' => 'Acceso restringido'], 'departamento-operativo');
            return;
        }
        if ((int) (\App\Core\Auth::user()?->id ?? 0) !== 19) {
            http_response_code(403);
            View::render('errors/403', ['title' => 'Acceso restringido'], 'departamento-operativo');
            return;
        }
        $solicitud = \App\Services\RefaccionesSolicitudCalcomaniasService::obtener($id);
        if ($solicitud === null) {
            http_response_code(404);
            View::render('errors/404', [], 'departamento-operativo');
            return;
        }
        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Refacciones', self::BASE_URL);
        Breadcrumb::add('Solicitud de calcomanías', self::BASE_URL . '/solicitud-calcomanias');
        Breadcrumb::add('Firma de autorización', '');
        View::render('departamento-operativo/4-almacen/refacciones/solicitud-calcomanias-firma', [
            'title' => 'Firma de autorización', 'solicitud' => $solicitud,
            'puedeFirmar' => (int) (\App\Core\Auth::user()->id ?? 0) === 19 && (int) $solicitud['estado'] === 1,
            'refaccionesOnlySidebar' => true,
            'refaccionesSidebarTitle' => MultiestacionService::isEnabled() ? 'Refacciones' : 'OPCIONES',
            'refaccionesSidebarItems' => $this->refaccionesSidebarItems(),
            'moduleStationKey' => Service::MODULE_KEY,
            'ocultarSelectorEstacion' => true,
            'nombreBadgeEstacion' => (string) $solicitud['nombre_ubicacion'],
            'mostrarBadgeGlobal' => false,
            'baseUrl' => self::BASE_URL,
            'scripts' => ['/assets/js/vendor.min.js', '/assets/js/departamento-operativo/4-almacen/refacciones-solicitud-calcomanias.init.js?v=' . time()],
            'links' => ['/assets/css/departamento-operativo/4-almacen/refacciones.css'],
        ], 'departamento-operativo');
    }

    public function guardarSolicitudCalcomanias(): void
    {
        if (!Service::getPermisos()['leer']) JsonResponse::forbidden('No tienes permiso para consultar solicitudes de calcomanías.');
        $result = \App\Services\RefaccionesSolicitudCalcomaniasService::crearBorrador();
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function detalleSolicitudCalcomanias(): void
    {
        if (!Service::getPermisos()['leer']) JsonResponse::forbidden('No tienes permiso para consultar solicitudes de calcomanías.');
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        $solicitud = $id !== false && $id > 0 ? \App\Services\RefaccionesSolicitudCalcomaniasService::obtener((int) $id) : null;
        if ($solicitud === null) JsonResponse::notFound('La solicitud no está disponible.');
        JsonResponse::custom(['success' => true, 'solicitud' => $solicitud]);
    }

    public function descargarSolicitudCalcomaniasPdf(int $id): void
    {
        if (!$this->guardModuleAccess(Service::MODULE_KEY, 'Solicitud de calcomanías', 'refacciones')) return;
        if (!Service::getPermisos()['leer']) JsonResponse::forbidden('No tienes permiso para consultar solicitudes de calcomanías.');
        $solicitud = \App\Services\RefaccionesSolicitudCalcomaniasService::obtener($id);
        if (!$solicitud || (int) ($solicitud['estado'] ?? -1) !== 2) {
            JsonResponse::notFound('El PDF sólo está disponible para solicitudes finalizadas dentro de tus ubicaciones autorizadas.');
        }
        $html = \App\Services\RefaccionesSolicitudCalcomaniasPdfService::generar($solicitud);
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream('Solicitud-calcomanias-00' . $id . '.pdf', ['Attachment' => true]);
    }

    public function eliminarSolicitudCalcomanias(): void
    {
        if (!Service::getPermisos()['leer']) JsonResponse::forbidden('No tienes permiso para consultar solicitudes de calcomanías.');
        $result = \App\Services\RefaccionesSolicitudCalcomaniasService::eliminarSolicitud((int) (Request::all()['id'] ?? 0));
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function agregarCalcomaniaSolicitud(): void
    {
        if (!Service::getPermisos()['leer']) JsonResponse::forbidden('No tienes permiso para consultar solicitudes de calcomanías.');
        $result = \App\Services\RefaccionesSolicitudCalcomaniasService::agregarCalcomania(Request::all());
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function eliminarCalcomaniaSolicitud(): void
    {
        if (!Service::getPermisos()['leer']) JsonResponse::forbidden('No tienes permiso para consultar solicitudes de calcomanías.');
        $result = \App\Services\RefaccionesSolicitudCalcomaniasService::eliminarCalcomania(Request::all());
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function actualizarObservacionesSolicitudCalcomanias(): void
    {
        if (!Service::getPermisos()['leer']) JsonResponse::forbidden('No tienes permiso para consultar solicitudes de calcomanías.');
        $result = \App\Services\RefaccionesSolicitudCalcomaniasService::actualizarObservaciones(Request::all());
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function firmarSolicitudCalcomaniasEncargado(): void
    {
        if (!Service::getPermisos()['leer']) JsonResponse::forbidden('No tienes permiso para consultar solicitudes de calcomanías.');
        $result = \App\Services\RefaccionesSolicitudCalcomaniasService::firmarEncargado(Request::all());
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function firmarSolicitudCalcomaniasAutorizacion(): void
    {
        if (!Service::getPermisos()['leer']) JsonResponse::forbidden('No tienes permiso para consultar solicitudes de calcomanías.');
        $result = \App\Services\RefaccionesSolicitudCalcomaniasService::firmarAutorizacion(Request::all());
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function crearTokenSolicitudCalcomaniasAutorizacion(): void
    {
        if (!Service::getPermisos()['leer']) JsonResponse::forbidden('No tienes permiso para consultar solicitudes de calcomanías.');
        $result = \App\Services\RefaccionesSolicitudCalcomaniasService::crearTokenAutorizacion(Request::all());
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function storeEvent(): void
    {
        $result = Service::crearEvento(Request::all());
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function calendarioDia(): void
    {
        if (!Service::getPermisos()['leer']) JsonResponse::forbidden('No tienes permiso para consultar el calendario de Refacciones.');

        $fecha = trim((string) Request::input('fecha', ''));
        $date = \DateTime::createFromFormat('!Y-m-d', $fecha);
        if (!$date || $date->format('Y-m-d') !== $fecha) {
            JsonResponse::custom(['success' => false, 'message' => 'La fecha seleccionada no es válida.'], 422);
        }

        JsonResponse::custom([
            'success' => true,
            'eventos' => Service::getEventosCalendarioDia($fecha),
        ]);
    }

    public function detalleCalendario(): void
    {
        if (!Service::getPermisos()['leer']) JsonResponse::forbidden('No tienes permiso para consultar el calendario de Refacciones.');

        $id = filter_var(Request::input('id'), FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            JsonResponse::custom(['success' => false, 'message' => 'El evento solicitado no es válido.'], 422);
        }

        $detalle = Service::getDetalleCalendario((int) $id);
        if ($detalle === null) JsonResponse::notFound('El evento no existe o no está disponible en tus ubicaciones.');

        JsonResponse::custom(['success' => true, 'detalle' => $detalle]);
    }

    public function actualizarEstadoCalendario(): void
    {
        if (!Service::getPermisos()['editar']) JsonResponse::forbidden('No tienes permiso para actualizar eventos del calendario.');

        $result = Service::actualizarEstadoEvento(Request::all());
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    public function enviarRecordatoriosCalendario(): void
    {
        if (!$this->guardModuleAccess(Service::MODULE_KEY, 'Refacciones', 'refacciones')) return;
        $result = \App\Services\RefaccionesCalendarioRecordatorioService::enviarManuales();
        JsonResponse::custom($result, $result['code'] ?? 200);
    }

    private function refaccionesSidebarItems(?bool $multiestacion = null): array
    {
        $multiestacion ??= MultiestacionService::isEnabled();
        $sections = $multiestacion
            ? array_diff_key(Service::SECCIONES, ['solicitud-calcomanias' => true])
            : array_intersect_key(Service::SECCIONES, ['inventario' => true]);
        $items = array_map(static fn (string $key, array $item): array => [
            'nombre' => $item['nombre'], 'icono' => $item['icono'],
            'ruta' => $key === 'dashboard' ? self::BASE_URL : self::BASE_URL . '/' . $key,
            'badge_count' => $key === 'calendarios' ? Service::contarEventosPendientesSidebar() : 0,
        ], array_keys($sections), array_values($sections));

        $requestItem = [
            'nombre' => 'Solicitud de calcomanías',
            'icono' => 'ti ti-sticker',
            'ruta' => self::BASE_URL . '/solicitud-calcomanias',
            'badge_count' => (int) (\App\Core\Auth::user()?->id ?? 0) === 19
                ? \App\Services\RefaccionesSolicitudCalcomaniasService::contarPendientesSidebar()
                : 0,
        ];
        $insertAfter = $multiestacion ? 'calcomanias' : 'inventario';
        $sectionKeys = array_keys($sections);
        $position = array_search($insertAfter, $sectionKeys, true);
        array_splice($items, $position === false ? count($items) : $position + 1, 0, [$requestItem]);

        return $items;
    }
}
