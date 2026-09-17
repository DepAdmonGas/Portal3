<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Helpers/helpers.php';

use App\Controllers\SeguroController;
use App\Controllers\TarjetasController;

function data_valid_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function data_valid_temp(string $contents): array
{
    $path = tempnam(sys_get_temp_dir(), 'portal3-upload-');
    file_put_contents($path, $contents);
    return [$path, strlen($contents)];
}

$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
 $jpg = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAqf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/AX//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/AX//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/Aqf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/Iqf/2gAMAwEAAgADAAAAEP/EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQMBAT8QH//EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQIBAT8QH//EABQQAQAAAAAAAAAAAAAAAAAAABD/2gAIAQEAAT8QH//Z', true);
 $pdf = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF\n";
[$validPath, $validSize] = data_valid_temp($png ?: '');
[$jpgPath, $jpgSize] = data_valid_temp($jpg ?: '');
[$pdfPath, $pdfSize] = data_valid_temp($pdf);
[$spoofedPath, $spoofedSize] = data_valid_temp('%PDF-1.4 not a png');

$tarjetas = (new ReflectionClass(TarjetasController::class))->newInstanceWithoutConstructor();
$seguro = (new ReflectionClass(SeguroController::class))->newInstanceWithoutConstructor();
$tarjetasMethod = new ReflectionMethod($tarjetas, 'validateTarjetaUpload');
$seguroMethod = new ReflectionMethod($seguro, 'validateSeguroUpload');
$tarjetasMethod->setAccessible(true);
$seguroMethod->setAccessible(true);

$validTarjeta = $tarjetasMethod->invoke($tarjetas, [
    'error' => UPLOAD_ERR_OK, 'tmp_name' => $validPath, 'size' => $validSize, 'name' => 'evidencia.png',
]);
$invalidExtension = $tarjetasMethod->invoke($tarjetas, [
    'error' => UPLOAD_ERR_OK, 'tmp_name' => $validPath, 'size' => $validSize, 'name' => 'evidencia.exe',
]);
$spoofedMime = $tarjetasMethod->invoke($tarjetas, [
    'error' => UPLOAD_ERR_OK, 'tmp_name' => $spoofedPath, 'size' => $spoofedSize, 'name' => 'evidencia.png',
]);
$oversized = $tarjetasMethod->invoke($tarjetas, [
    'error' => UPLOAD_ERR_OK, 'tmp_name' => $validPath, 'size' => 5 * 1024 * 1024 + 1, 'name' => 'evidencia.png',
]);
$invalidSeguroExtension = $seguroMethod->invoke($seguro, [
    'error' => UPLOAD_ERR_OK, 'tmp_name' => $validPath, 'size' => $validSize, 'name' => 'poliza.exe',
]);
$spoofedSeguroMime = $seguroMethod->invoke($seguro, [
    'error' => UPLOAD_ERR_OK, 'tmp_name' => $spoofedPath, 'size' => $spoofedSize, 'name' => 'poliza.png',
]);
$oversizedSeguro = $seguroMethod->invoke($seguro, [
    'error' => UPLOAD_ERR_OK, 'tmp_name' => $validPath, 'size' => 10 * 1024 * 1024 + 1, 'name' => 'poliza.png',
]);
$uploadError = $seguroMethod->invoke($seguro, [
    'error' => UPLOAD_ERR_PARTIAL, 'tmp_name' => $validPath, 'size' => $validSize, 'name' => 'poliza.pdf',
]);
$validSeguro = $seguroMethod->invoke($seguro, [
    'error' => UPLOAD_ERR_OK, 'tmp_name' => $validPath, 'size' => $validSize, 'name' => 'poliza.png',
]);
$validJpg = $seguroMethod->invoke($seguro, [
    'error' => UPLOAD_ERR_OK, 'tmp_name' => $jpgPath, 'size' => $jpgSize, 'name' => 'poliza.jpg',
]);
$validPdf = $seguroMethod->invoke($seguro, [
    'error' => UPLOAD_ERR_OK, 'tmp_name' => $pdfPath, 'size' => $pdfSize, 'name' => 'poliza.pdf',
]);
$doubleExtension = $seguroMethod->invoke($seguro, [
    'error' => UPLOAD_ERR_OK, 'tmp_name' => $pdfPath, 'size' => $pdfSize, 'name' => 'document.pdf.php',
]);
$phtml = $seguroMethod->invoke($seguro, [
    'error' => UPLOAD_ERR_OK, 'tmp_name' => $pdfPath, 'size' => $pdfSize, 'name' => 'document.phtml',
]);

$tests = [
    'Tarjetas accepts a valid allowed image/MIME pair' => $validTarjeta['valid'] === true,
    'Tarjetas rejects an executable extension' => $invalidExtension['valid'] === false,
    'Tarjetas rejects a spoofed extension/MIME pair' => $spoofedMime['valid'] === false,
    'Tarjetas rejects an oversized upload' => $oversized['valid'] === false,
    'Seguro rejects a partial upload before processing' => $uploadError['valid'] === false,
    'Seguro accepts a valid allowed image/MIME pair' => $validSeguro['valid'] === true,
    'Seguro accepts a valid JPG' => $validJpg['valid'] === true,
    'Seguro accepts a valid PDF' => $validPdf['valid'] === true,
    'Seguro rejects an invalid extension' => $invalidSeguroExtension['valid'] === false,
    'Seguro rejects a spoofed extension/MIME pair' => $spoofedSeguroMime['valid'] === false,
    'Seguro rejects an oversized upload' => $oversizedSeguro['valid'] === false,
    'Seguro rejects PHP and double extensions' => $doubleExtension['valid'] === false && $phtml['valid'] === false,
    'Seguro neutralizes traversal-like names with generated storage names' => (function (): bool {
        $source = file_get_contents(__DIR__ . '/../app/Controllers/SeguroController.php');
        return str_contains($source, 'bin2hex(random_bytes(16))');
    })(),
    'Tarjetas declares a strict JSON allowlist and bounded fields' => (function (): bool {
        $source = file_get_contents(__DIR__ . '/../app/Controllers/TarjetasController.php');
        return str_contains($source, 'JSON_THROW_ON_ERROR')
            && str_contains($source, 'array_diff(array_keys($payload), $allowed)')
            && str_contains($source, "'tipo_tarjeta' => ['enum'");
    })(),
];

$passed = 0;
$failed = 0;
foreach ($tests as $name => $result) {
    if ($result) {
        $passed++;
        echo "PASS {$name}\n";
    } else {
        $failed++;
        echo "FAIL {$name}\n";
    }
}

@unlink($validPath);
@unlink($spoofedPath);
@unlink($jpgPath);
@unlink($pdfPath);
echo "RESULT passed={$passed} failed={$failed} skipped=0\n";
exit($failed === 0 ? 0 : 1);
