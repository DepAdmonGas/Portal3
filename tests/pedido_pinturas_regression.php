<?php
declare(strict_types=1);

// Fixture aislado: SQLite temporal, sin .env, MySQL, API ni notificaciones.
require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/support/portal3_autoload.php';

use App\Services\PedidoPinturasService as Service;
use Illuminate\Database\Capsule\Manager as DB;

function pinturasFixture(string $database): void
{
    $db = new DB();
    $db->addConnection(['driver' => 'sqlite', 'database' => $database, 'prefix' => '']);
    $db->setAsGlobal(); $db->bootEloquent();
    $_SESSION = ['usuario' => ['id' => 1, 'id_estacion' => 1, 'multiestacion' => false],
        'permisos_do' => ['comercializadora' => ['leer' => 1, 'editar' => 1, 'crear' => 1, 'descargar' => 1]]];
    $user = new App\Models\Usuario();
    $user->forceFill(['id' => 1, 'id_gas' => 1, 'id_puesto' => 6]);
    $user->setRelation('puesto', (new App\Models\Puestos())->forceFill(['tipo_puesto' => 'Encargado']));
    (new ReflectionProperty(App\Core\Auth::class, 'user'))->setValue(null, $user);
}

if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (str_starts_with($path, '/assets/')) return false;
    $database = getenv('PINTURAS_TEST_DB');
    if (!$database || !str_starts_with(basename($database), 'portal3-pinturas-')) { http_response_code(500); exit; }
    pinturasFixture($database);
    if ($path === '/download') { (new App\Controllers\DownloadController())->download(); exit; }
    if (str_contains($path, '/pdf/')) {
        usleep(2000000); // Permite observar el loader del fixture, nunca producción.
        $_ENV['APP_URL'] = '';
        $pdfData = Service::generarHtmlPdf(5);
        $dompdf = new Dompdf\Dompdf();
        $dompdf->loadHtml(str_replace('<img src="/assets/images/logos/Logo.png" width="180">', '', $pdfData['html']));
        $dompdf->render(); $dompdf->stream('pedido-pinturas-fixture.pdf', ['Attachment' => true]); exit;
    }
    if (str_ends_with($path, '/get-data')) {
        App\Core\JsonResponse::custom(['success' => true, 'data' => [[
            'id' => 5, 'status' => 2, 'status_label' => 'Finalizado', 'personal' => 'Firmante de prueba',
            'puesto' => 'Encargado', 'nombre_estacion' => 'Estación fixture', 'fecha_hora' => '17/11/2026',
            'tiene_firma_a' => true, 'tiene_firma_b' => true,
        ]]]);
    }
    if (str_ends_with($path, '/get-pendientes')) { App\Core\JsonResponse::custom(['success' => true, 'total' => 0, 'contexto' => 0]); }
    if (str_ends_with($path, '/detalle')) {
        $pedido = ['id' => (int)($_GET['id'] ?? 1), 'detalle' => [], 'status' => 2];
        if ($pedido['id'] === 1) $pedido['firmas'] = [
            'A' => ['tipo_label' => 'ELABORÓ / ENCARGADO', 'usuario_nombre' => 'Firmante de prueba', 'firma_img_url' => '', 'archivo_faltante' => true],
            'B' => ['tipo_label' => 'FIRMA DE VO.BO.', 'usuario_nombre' => 'VoBo de prueba', 'fecha' => '17 de noviembre de 2026'],
        ];
        App\Core\JsonResponse::custom(['success' => true, 'pedido' => $pedido]);
    }
    if ($path === '/catalogo' || $path === '/firmas' || $path === '/pdf-loader') {
        $puedeAcceso = $puedeEditar = $puedeEliminar = true;
        $puedeCrear = $puedeDescargar = $puedeFirmarVoBo = $esEncargado = $multiestacion = false;
        $idEstacion = $idUsuario = 1;
        if ($path === '/pdf-loader') $puedeDescargar = true;
        echo '<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="/assets/css/styles.css">';
        echo '<script src="/assets/js/vendor.min.js"></script><script src="/assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>';
        echo '<script src="/assets/libs/axios/dist/axios.min.js"></script>';
        echo '<script src="/assets/libs/datatables.net/js/jquery.dataTables.min.js"></script>';
        echo '<script src="/assets/js/core/notify.js"></script><script src="/assets/js/core/actions.alpine.js"></script>';
        echo '<script src="/assets/js/core/request-action-loader.js"></script>';
        echo '<script src="/assets/js/departamento-operativo/5-comercializadora/pedido-pinturas.form.js"></script>';
        $componentScript = $path === '/catalogo' ? 'catalogo' : 'actions';
        echo '<script src="/assets/js/departamento-operativo/5-comercializadora/pedido-pinturas.' . $componentScript . '.init.js"></script>';
        echo '<script defer src="/assets/libs/alpinejs/dist/alpinejs.min.js"></script></head><body>';
        echo '<div class="loader-admongas" style="display:none;position:fixed;inset:0;background:#ffffffdd;z-index:99999;align-items:center;justify-content:center"><img src="/assets/images/logos/logo-empresaMov.gif" alt="Cargando..."></div>';
        if ($path === '/pdf-loader') echo '<script src="/assets/js/departamento-operativo/5-comercializadora/pedido-pinturas.datatable.init.js"></script>';
        if ($path === '/firmas') {
            echo '<button onclick="window.pedidoPinturasComponentInstance.abrirDetallePedido(1)">Fixture: imagen ausente</button>';
            echo '<button onclick="window.pedidoPinturasComponentInstance.abrirDetallePedido(2)">Fixture: sin firmas</button>';
        }
        require dirname(__DIR__) . '/app/Views/departamento-operativo/5-comercializadora/pedido-pinturas/' . ($path === '/catalogo' ? 'catalogo' : 'index') . '.php';
        echo '</body></html>'; exit;
    }
    $controller = new App\Controllers\PedidoPinturasController();
    if (str_ends_with($path, '/catalogos')) $controller->getCatalogos();
    if (str_ends_with($path, '/guardar-producto')) $controller->guardarProducto();
    if (str_ends_with($path, '/editar-piezas')) $controller->editarPiezas();
    http_response_code(404); exit;
}

function checkPinturas(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "PASS $message\n";
}

$database = sys_get_temp_dir() . '/portal3-pinturas-' . bin2hex(random_bytes(8)) . '.sqlite';
touch($database);
$files = []; $process = null;
try {
    pinturasFixture($database);
    foreach ([
        'CREATE TABLE tb_estaciones (id INTEGER PRIMARY KEY, nombre TEXT, numlista INTEGER, razonsocial TEXT)',
        'CREATE TABLE tb_usuarios (id INTEGER PRIMARY KEY, nombre TEXT, id_puesto INTEGER)',
        'CREATE TABLE tb_puestos (id INTEGER PRIMARY KEY, tipo_puesto TEXT)',
        'CREATE TABLE op_pedido_pinturas_complementos (id INTEGER PRIMARY KEY, id_estacion INTEGER, id_personal INTEGER, fecha TEXT, observaciones TEXT, status INTEGER)',
        'CREATE TABLE tb_multiestacion_usuario (id_usuario INTEGER, activo INTEGER)',
        'CREATE TABLE tb_multiestacion_puesto (id_puesto INTEGER, activo INTEGER)',
        'CREATE TABLE tb_modulos_config (modulo_key TEXT, activo INTEGER, tipo TEXT, tipo_departamento TEXT, estaciones_soportadas TEXT, departamentos_soportados TEXT, allow_all INTEGER, placeholder TEXT)',
        'CREATE TABLE op_pinturas_lista (id INTEGER PRIMARY KEY AUTOINCREMENT, unidad TEXT, producto TEXT, estatus INTEGER)',
        'CREATE TABLE op_pedido_pinturas_detalle (id INTEGER PRIMARY KEY, id_pedido INTEGER, piezas INTEGER)',
        'CREATE TABLE op_pedido_pinturas_complementos_firma (id INTEGER PRIMARY KEY AUTOINCREMENT, id_pedido INTEGER, id_usuario INTEGER, fecha TEXT, tipo_firma TEXT, firma TEXT)',
    ] as $sql) DB::statement($sql);
    DB::table('tb_estaciones')->insert(['id' => 1, 'nombre' => 'Estación fixture', 'numlista' => 1, 'razonsocial' => 'Empresa de prueba']);
    DB::table('tb_usuarios')->insert(['id' => 1, 'nombre' => 'Firmante de prueba', 'id_puesto' => 1]);
    DB::table('tb_puestos')->insert(['id' => 1, 'tipo_puesto' => 'Encargado']);
    DB::table('op_pedido_pinturas_complementos')->insert(['id' => 5, 'id_estacion' => 1, 'id_personal' => 1, 'fecha' => '2026-11-17 12:00:00', 'observaciones' => '', 'status' => 2]);
    DB::table('tb_modulos_config')->insert(['modulo_key' => 'pedido-pinturas', 'activo' => 1, 'tipo' => 'stations_only',
        'tipo_departamento' => null, 'estaciones_soportadas' => '[1]', 'departamentos_soportados' => '[]', 'allow_all' => 0]);
    checkPinturas(Service::normalizarPiezas('1,234') === 1234, 'normaliza miles sin truncar');
    foreach (['', '0', '-1', '1.5', '1,2', 'abc', [], '2147483648'] as $value) {
        checkPinturas(Service::normalizarPiezas($value) === null, 'rechaza cantidad inválida ' . json_encode($value));
    }
    checkPinturas(!Service::guardarProducto(0, ' ', '')['success'] && DB::table('op_pinturas_lista')->count() === 0, 'catálogo vacío no persiste');
    checkPinturas(!Service::guardarReporteDatos(1, '', '', '')['success'], 'reporte exige fecha y hora antes de escribir');
    checkPinturas(!Service::guardarReporteDatos(1, '2026-02-30', '25:99', '')['success'], 'rechaza fecha y hora imposibles');
    checkPinturas(Service::nombreArchivo('../archivos/importado.pdf') === 'importado.pdf', 'referencia heredada conserva nombre');
    checkPinturas(Service::nombreArchivo('../../secreto.pdf') === null, 'rechaza traversal');
    checkPinturas(Service::rutaArchivo('portal3-pinturas-inexistente.pdf') === null, 'archivo ausente se detecta físicamente');
    $signature = new ReflectionMethod(Service::class, 'guardarFirmaImagen');
    $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aD1sAAAAASUVORK5CYII=';
    checkPinturas($signature->invoke(null, 5, 1, 'data:image/png;base64,' . $png)['success'], 'guarda firma PNG con relación al pedido');
    $signatureName = DB::table('op_pedido_pinturas_complementos_firma')->value('firma');
    $files[] = Service::archivosRoot() . $signatureName;
    checkPinturas(Service::rutaArchivo('imgs/firma/' . $signatureName, true) !== null, 'firma importada se resuelve desde uploads/archivos');
    checkPinturas(!$signature->invoke(null, 5, 1, 'data:image/png;base64,' . base64_encode('no es imagen'))['success'], 'rechaza contenido que no es una firma PNG');
    $_ENV['APP_URL'] = '';
    $withImage = Service::generarHtmlPdf(5)['html'];
    checkPinturas(str_contains($withImage, 'data:image/png;base64,') && !str_contains($withImage, 'La firma está registrada'), 'PDF incluye la imagen cuando existe');
    DB::table('op_pedido_pinturas_complementos_firma')->update(['firma' => 'firma-fixture-inexistente.png']);
    DB::table('op_pedido_pinturas_complementos_firma')->insert(['id_pedido' => 5, 'id_usuario' => 1,
        'fecha' => '2026-11-17 13:00:00', 'tipo_firma' => 'B', 'firma' => 'Firma: fixture-electronica']);
    $withoutImage = Service::generarHtmlPdf(5)['html'];
    checkPinturas(str_contains($withoutImage, 'La firma está registrada, pero su imagen no está disponible.')
        && str_contains($withoutImage, 'Firmante de prueba') && str_contains($withoutImage, 'Fecha:'), 'PDF conserva firmante y fecha sin imagen');
    checkPinturas(str_contains($withoutImage, 'El pedido de pinturas y complementos se firmó por un medio electrónico.')
        && str_contains($withoutImage, 'NOMBRE Y FIRMA DE VOBO'), 'PDF conserva el VoBo electrónico');
    if (in_array('--pdf-output', $argv, true)) {
        $outputDir = dirname(__DIR__) . '/tmp/pdfs';
        if (!is_dir($outputDir)) mkdir($outputDir, 0755, true);
        $dompdf = new Dompdf\Dompdf();
        $dompdf->loadHtml(str_replace('<img src="/assets/images/logos/Logo.png" width="180">', '', $withoutImage));
        $dompdf->render(); file_put_contents($outputDir . '/pedido-pinturas-firma-faltante.pdf', $dompdf->output());
    }
    $port = random_int(21000, 29000);
    $environment = getenv(); $environment['PINTURAS_TEST_DB'] = $database;
    $process = proc_open([PHP_BINARY, '-d', 'upload_tmp_dir=' . sys_get_temp_dir(), '-S', '127.0.0.1:' . $port, '-t', dirname(__DIR__) . '/public', __FILE__],
        [['pipe', 'r'], ['file', $database . '.http.log', 'a'], ['file', $database . '.http.log', 'a']], $pipes, dirname(__DIR__), $environment);
    if (!is_resource($process)) throw new RuntimeException('No se pudo iniciar el fixture HTTP');
    $base = 'http://127.0.0.1:' . $port;
    for ($i = 0; $i < 40; $i++) { $socket = @fsockopen('127.0.0.1', $port); if ($socket) { fclose($socket); break; } usleep(50000); }
    $download = $base . '/download?tipo=pedido-pinturas&file=' . rawurlencode($signatureName);
    checkPinturas(file_get_contents($download) === base64_decode($png), 'DownloadController descarga la firma existente');
    checkPinturas(file_get_contents($download . '&view=1') === base64_decode($png), 'DownloadController visualiza la firma existente');
    $index = file_get_contents(dirname(__DIR__) . '/app/Views/departamento-operativo/5-comercializadora/pedido-pinturas/index.php');
    checkPinturas(!str_contains($index, 'modalArchivosPinturas') && !str_contains($index, 'abrirArchivos'), 'interfaz sin botón ni modal adicional de archivos');
    $routes = file_get_contents(dirname(__DIR__) . '/routes/web.php');
    checkPinturas(!str_contains($routes, "'/comercializadora/pedido-pinturas/archivos'"), 'sin rutas nuevas de gestión de archivos');
    DB::table('op_pedido_pinturas_detalle')->insert(['id' => 1, 'id_pedido' => 1, 'piezas' => 1]);
    $numberContext = stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query(['id' => 1, 'piezas' => '1,234']), 'ignore_errors' => true]]);
    $saved = json_decode(file_get_contents($base . '/editar-piezas', false, $numberContext), true);
    checkPinturas(($saved['success'] ?? false) && (int)DB::table('op_pedido_pinturas_detalle')->value('piezas') === 1234, 'Controller normaliza, guarda y recupera cantidades');
    $numberContext = stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query(['id' => 1, 'piezas' => '']), 'ignore_errors' => true]]);
    $invalid = json_decode(file_get_contents($base . '/editar-piezas', false, $numberContext), true);
    checkPinturas(($invalid['message'] ?? '') === Service::REQUIRED_MESSAGE && (int)DB::table('op_pedido_pinturas_detalle')->value('piezas') === 1234, 'backend rechaza campo vacío sin modificar cantidad');
    if (in_array('--serve', $argv, true)) {
        echo "UI_FIXTURE=$base/catalogo\n";
        fgets(STDIN);
    }
    echo "HTTP fixture terminado; sin acceso a MySQL ni servicios externos.\n";
} finally {
    if (is_resource($process)) { proc_terminate($process); foreach ($pipes as $pipe) fclose($pipe); proc_close($process); }
    foreach ($files as $file) if (is_file($file)) unlink($file);
    DB::connection()->disconnect();
    if (is_file($database)) unlink($database);
    if (is_file($database . '.http.log')) unlink($database . '.http.log');
}
