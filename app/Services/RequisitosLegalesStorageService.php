<?php
namespace App\Services;

final class RequisitosLegalesStorageService
{
    public static function privateRoot(): string
    {
        $testRoot = getenv('P0_TEST_DOWNLOAD_ROOT');
        $root = (is_string($testRoot) && str_starts_with($testRoot, '/tmp/portal3-p0-download-'))
            ? rtrim($testRoot, '/') . '/reuisitos-legales/'
            : dirname(__DIR__, 2) . '/storage/private/requisitos-legales/';
        if (!is_dir($root)) mkdir($root, 0750, true);
        return $root;
    }

    public static function storeUploadedFile(array $file, string $prefix): string
    {
        $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        $name = uniqid($prefix, true) . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], self::privateRoot() . $name)) throw new \RuntimeException('No se pudo guardar el archivo');
        return $name;
    }

    public static function resolveReadablePath(string $reference): ?string
    {
        $reference = self::normalizeReference($reference);
        if ($reference === null) return null;
        $legacyRoot = dirname(__DIR__, 2) . '/public/uploads/archivos/reuisitos-legales/';
        $testRoot = getenv('P0_TEST_DOWNLOAD_ROOT');
        if (is_string($testRoot) && str_starts_with($testRoot, '/tmp/portal3-p0-download-')) {
            $legacyRoot = rtrim($testRoot, '/') . '/reuisitos-legales/';
        }
        foreach ([self::privateRoot(), $legacyRoot] as $root) {
            $base = realpath($root); $path = realpath($root . $reference);
            if ($base !== false && $path !== false && str_starts_with($path, rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) && is_file($path)) return $path;
        }
        return null;
    }

    public static function normalizeReference(string $reference): ?string
    {
        $reference = trim($reference);
        if ($reference === '' || str_contains($reference, "\0")) return null;
        if ($reference !== basename($reference)) {
            if (!str_starts_with($reference, 'archivos/reuisitos-legales/')) return null;
            $reference = substr($reference, strlen('archivos/reuisitos-legales/'));
        }
        return $reference !== '' && $reference === basename($reference) ? $reference : null;
    }

    public static function deleteReference(?string $reference): void
    {
        if ($reference === null) return;
        $path = self::resolveReadablePath($reference);
        if ($path !== null) @unlink($path);
    }
}
