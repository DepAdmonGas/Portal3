<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/support/portal3_autoload.php';

use App\Controllers\TarjetasController;
use App\Services\SeguroService;

function data_valid_temp(string $contents): array
{
    $path = tempnam(sys_get_temp_dir(), 'portal3-upload-');
    if ($path === false || file_put_contents($path, $contents) === false) {
        throw new RuntimeException('Unable to prepare a local upload fixture.');
    }
    return [$path, strlen($contents)];
}

/** @return array{process:resource,pipes:array<int,resource>,url:string} */
function data_valid_start_upload_server(): array
{
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    if (!is_resource($socket)) {
        throw new RuntimeException('Unable to reserve a loopback test port: ' . $errorMessage);
    }
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $port = (int) substr((string) $address, strrpos((string) $address, ':') + 1);
    if ($port < 1) {
        throw new RuntimeException('Unable to determine the loopback test port.');
    }

    $pipes = [];
    $process = proc_open([
        PHP_BINARY,
        '-d', 'upload_max_filesize=20M',
        '-d', 'post_max_size=21M',
        '-S', '127.0.0.1:' . $port,
        __DIR__ . '/fixtures/seguro_upload_validation_router.php',
    ], [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'a'], 2 => ['file', '/dev/null', 'a']], $pipes, dirname(__DIR__));
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start the isolated loopback upload harness.');
    }
    fclose($pipes[0]);

    $ready = false;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $connection = @fsockopen('127.0.0.1', $port, $connectError, $connectMessage, 0.1);
        if (is_resource($connection)) {
            fclose($connection);
            $ready = true;
            break;
        }
        usleep(100000);
    }
    if (!$ready) {
        proc_terminate($process);
        proc_close($process);
        throw new RuntimeException('The isolated loopback upload harness did not become ready.');
    }

    return ['process' => $process, 'pipes' => $pipes, 'url' => 'http://127.0.0.1:' . $port . '/validate'];
}

function data_valid_stop_upload_server(array $server): void
{
    proc_terminate($server['process']);
    foreach ($server['pipes'] as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }
    proc_close($server['process']);
}

/** @return array{process:resource,pipes:array<int,resource>,url:string} */
function data_valid_start_tarjetas_server(): array
{
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    if (!is_resource($socket)) {
        throw new RuntimeException('Unable to reserve a loopback Tarjetas test port: ' . $errorMessage);
    }
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $port = (int) substr((string) $address, strrpos((string) $address, ':') + 1);
    if ($port < 1) {
        throw new RuntimeException('Unable to determine the loopback Tarjetas test port.');
    }

    $pipes = [];
    $process = proc_open([
        PHP_BINARY,
        '-S', '127.0.0.1:' . $port,
        __DIR__ . '/fixtures/tarjetas_json_validation_router.php',
    ], [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'a'], 2 => ['file', '/dev/null', 'a']], $pipes, dirname(__DIR__));
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start the isolated Tarjetas validation harness.');
    }
    fclose($pipes[0]);

    $ready = false;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $connection = @fsockopen('127.0.0.1', $port, $connectError, $connectMessage, 0.1);
        if (is_resource($connection)) {
            fclose($connection);
            $ready = true;
            break;
        }
        usleep(100000);
    }
    if (!$ready) {
        proc_terminate($process);
        proc_close($process);
        throw new RuntimeException('The isolated Tarjetas validation harness did not become ready.');
    }

    return ['process' => $process, 'pipes' => $pipes, 'url' => 'http://127.0.0.1:' . $port . '/solicitud-tarjetas/update-reporte-formulario'];
}

/** @return array{status:int,body:array<string,mixed>} */
function data_valid_post_tarjetas_json(string $url, string $payload): array
{
    $context = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nConnection: close\r\n",
        'content' => $payload,
        'timeout' => 10,
        'ignore_errors' => true,
    ]]);
    $response = @file_get_contents($url, false, $context);
    if (!is_string($response)) {
        throw new RuntimeException('The Tarjetas validation request failed.');
    }
    $statusLine = $http_response_header[0] ?? '';
    if (!preg_match('/\s(\d{3})\s/', $statusLine, $statusMatch)) {
        throw new RuntimeException('The Tarjetas validation endpoint returned no HTTP status.');
    }
    $body = json_decode($response, true);
    if (!is_array($body)) {
        throw new RuntimeException('The Tarjetas validation endpoint returned invalid JSON.');
    }
    return ['status' => (int) $statusMatch[1], 'body' => $body];
}

/** @return array{valid:bool,message:string,extension?:string} */
function data_valid_http_upload(string $url, ?string $path, string $name = ''): array
{
    $boundary = '----Portal3UploadTest' . bin2hex(random_bytes(12));
    $body = '';
    if ($path !== null) {
        $contents = file_get_contents($path);
        if (!is_string($contents)) {
            throw new RuntimeException('Unable to read a local upload fixture.');
        }
        $safeName = str_replace(["\r", "\n", '"', '\\'], '', $name);
        $body .= '--' . $boundary . "\r\n"
            . 'Content-Disposition: form-data; name="upload"; filename="' . $safeName . "\"\r\n"
            . "Content-Type: application/octet-stream\r\n\r\n"
            . $contents . "\r\n";
    }
    $body .= '--' . $boundary . "--\r\n";
    $context = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: multipart/form-data; boundary={$boundary}\r\nConnection: close\r\n",
        'content' => $body,
        'timeout' => 20,
        'ignore_errors' => true,
    ]]);
    $response = @file_get_contents($url, false, $context);
    if (!is_string($response)) {
        throw new RuntimeException('The loopback upload request failed.');
    }
    $statusLine = $http_response_header[0] ?? '';
    if (!preg_match('/\s(\d{3})\s/', $statusLine, $statusMatch) || (int) $statusMatch[1] !== 200) {
        throw new RuntimeException('The loopback upload validator returned an unexpected HTTP status.');
    }
    $result = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($result) || !array_key_exists('valid', $result)) {
        throw new RuntimeException('The loopback upload validator returned an invalid response.');
    }
    return $result;
}

function data_valid_http_result(array $server, ?string $path, string $name = ''): array
{
    return data_valid_http_upload($server['url'], $path, $name);
}

$passes = 0;
$failed = 0;
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
$jpg = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAqf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/AX//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/AX//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/Aqf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/Iqf/2gAMAwEAAgADAAAAEP/EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQMBAT8QH//EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQIBAT8QH//EABQQAQAAAAAAAAAAAAAAAAAAABD/2gAIAQEAAT8QH//Z', true);
$pdf = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF\n";
[$validPath, $validSize] = data_valid_temp($png ?: '');
[$jpgPath, $jpgSize] = data_valid_temp($jpg ?: '');
[$pdfPath, $pdfSize] = data_valid_temp($pdf);
[$spoofedPath, $spoofedSize] = data_valid_temp('%PDF-1.4 not a png');

$tarjetas = (new ReflectionClass(TarjetasController::class))->newInstanceWithoutConstructor();
$tarjetasMethod = new ReflectionMethod($tarjetas, 'validateTarjetaUpload');
$tarjetasMethod->setAccessible(true);
$validTarjeta = $tarjetasMethod->invoke($tarjetas, [
    'error' => UPLOAD_ERR_OK, 'tmp_name' => $validPath, 'size' => $validSize, 'name' => 'evidencia.png',
]);
$invalidExtension = $tarjetasMethod->invoke($tarjetas, [
    'error' => UPLOAD_ERR_OK, 'tmp_name' => $validPath, 'size' => $validSize, 'name' => 'evidencia.exe',
]);
$spoofedMime = $tarjetasMethod->invoke($tarjetas, [
    'error' => UPLOAD_ERR_OK, 'tmp_name' => $spoofedPath, 'size' => $spoofedSize, 'name' => 'evidencia.png',
]);
$tarjetasOversized = $tarjetasMethod->invoke($tarjetas, [
    'error' => UPLOAD_ERR_OK, 'tmp_name' => $validPath, 'size' => 5 * 1024 * 1024 + 1, 'name' => 'evidencia.png',
]);

$seguroValidator = new ReflectionMethod(SeguroService::class, 'validarArchivo');
$partialUpload = $seguroValidator->invoke(null, [
    'error' => UPLOAD_ERR_PARTIAL, 'tmp_name' => $validPath, 'size' => $validSize, 'name' => 'poliza.pdf',
]);
$localTempNotAnUpload = $seguroValidator->invoke(null, [
    'error' => UPLOAD_ERR_OK, 'tmp_name' => $validPath, 'size' => $validSize, 'name' => 'poliza.png',
]);
[$oversizedSeguroPath] = data_valid_temp(str_repeat('A', 10 * 1024 * 1024 + 1));
register_shutdown_function(static function () use ($validPath, $spoofedPath, $jpgPath, $pdfPath, $oversizedSeguroPath): void {
    foreach ([$validPath, $spoofedPath, $jpgPath, $pdfPath, $oversizedSeguroPath] as $path) {
        @unlink($path);
    }
});

$server = data_valid_start_upload_server();
try {
    $validSeguro = data_valid_http_result($server, $validPath, 'poliza.png');
    $validJpg = data_valid_http_result($server, $jpgPath, 'poliza.jpg');
    $validPdf = data_valid_http_result($server, $pdfPath, 'poliza.pdf');
    $invalidSeguroExtension = data_valid_http_result($server, $validPath, 'poliza.exe');
    $spoofedSeguroMime = data_valid_http_result($server, $spoofedPath, 'poliza.png');
    $oversizedSeguro = data_valid_http_result($server, $oversizedSeguroPath, 'poliza.png');
    $doubleExtension = data_valid_http_result($server, $pdfPath, 'document.pdf.php');
    $phtml = data_valid_http_result($server, $pdfPath, 'document.phtml');
    $missingUpload = data_valid_http_result($server, null);
} finally {
    data_valid_stop_upload_server($server);
}

$tarjetasServer = data_valid_start_tarjetas_server();
try {
    $malformedTarjetaJson = data_valid_post_tarjetas_json($tarjetasServer['url'], '{');
    $extraTarjetaField = data_valid_post_tarjetas_json($tarjetasServer['url'], '{"id":1,"unexpected":"x"}');
    $invalidTarjetaType = data_valid_post_tarjetas_json($tarjetasServer['url'], json_encode([
        'id' => 1,
        'razon_social' => 'Empresa de prueba',
        'nombre_usuario' => 'Usuario de prueba',
        'vehiculo' => 'Vehículo de prueba',
        'placas' => 'ABC123',
        'no_unidad' => '1',
        'tarjeta' => '1234',
        'tipo_tarjeta' => 'Tipo no permitido',
    ], JSON_THROW_ON_ERROR));
} finally {
    data_valid_stop_upload_server($tarjetasServer);
}

$tests = [
    'Tarjetas accepts a valid allowed image/MIME pair' => $validTarjeta['valid'] === true,
    'Tarjetas rejects an executable extension' => $invalidExtension['valid'] === false,
    'Tarjetas rejects a spoofed extension/MIME pair' => $spoofedMime['valid'] === false,
    'Tarjetas rejects an oversized upload' => $tarjetasOversized['valid'] === false,
    'Seguro rejects a partial upload before processing' => $partialUpload['valid'] === false,
    'Seguro accepts a valid allowed image/MIME pair through HTTP upload' => $validSeguro['valid'] === true,
    'Seguro accepts a valid JPG through HTTP upload' => $validJpg['valid'] === true,
    'Seguro accepts a valid PDF through HTTP upload' => $validPdf['valid'] === true,
    'Seguro rejects an invalid extension through HTTP upload' => $invalidSeguroExtension['valid'] === false,
    'Seguro rejects a spoofed extension/MIME pair through HTTP upload' => $spoofedSeguroMime['valid'] === false,
    'Seguro rejects an oversized upload through HTTP upload' => $oversizedSeguro['valid'] === false,
    'Seguro rejects PHP and double extensions through HTTP upload' => $doubleExtension['valid'] === false && $phtml['valid'] === false,
    'Seguro rejects requests with no uploaded file' => $missingUpload['valid'] === false,
    'Seguro rejects a local temporary file that is not an HTTP upload' => $localTempNotAnUpload['valid'] === false,
    'Seguro validator retains PHP is_uploaded_file enforcement' => str_contains(file_get_contents(__DIR__ . '/../app/Services/SeguroService.php'), 'is_uploaded_file($file[\'tmp_name\'])'),
    'Seguro neutralizes traversal-like names with generated storage names' => str_contains(file_get_contents(__DIR__ . '/../app/Services/SeguroService.php'), 'bin2hex(random_bytes(16))'),
    'Seguro stores validated uploads using PHP move_uploaded_file' => str_contains(file_get_contents(__DIR__ . '/../app/Services/SeguroService.php'), 'move_uploaded_file($file[\'tmp_name\'], $destino)'),
    'Tarjetas returns HTTP 422 for malformed JSON, disallowed fields, and invalid values' =>
        $malformedTarjetaJson['status'] === 422
        && ($malformedTarjetaJson['body']['type'] ?? null) === 'validation'
        && isset($malformedTarjetaJson['body']['errors']['json'])
        && $extraTarjetaField['status'] === 422
        && ($extraTarjetaField['body']['type'] ?? null) === 'validation'
        && isset($extraTarjetaField['body']['errors']['json'])
        && $invalidTarjetaType['status'] === 422
        && ($invalidTarjetaType['body']['type'] ?? null) === 'validation'
        && isset($invalidTarjetaType['body']['errors']['tipo_tarjeta']),
];

foreach ($tests as $name => $result) {
    if ($result) {
        $passes++;
        echo "PASS {$name}\n";
    } else {
        $failed++;
        echo "FAIL {$name}\n";
    }
}

printf("RESULT passed=%d failed=%d skipped=0\n", $passes, $failed);
exit($failed === 0 ? 0 : 1);
