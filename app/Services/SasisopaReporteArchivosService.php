<?php

namespace App\Services;

/**
 * Resolución segura de adjuntos usados por el reporte SASISOPA.
 * Los valores guardados en la base pueden ser sólo el nombre o una ruta
 * heredada; únicamente se sirve el basename desde una carpeta fija.
 */
final class SasisopaReporteArchivosService
{
    private const DIRECTORIOS = [
        'analisis-riesgo' => 'analisis-riesgo',
        'seguridad-contratistas' => 'seguridad-contratistas',
        'simulacros' => 'protocolo',
        'informe-revision-resultados' => 'informe-revision-resultados',
        'atencion-hallazgos' => 'atencion-hallazgos',
        'auditorias' => 'auditorias',
        'incidentes-accidentes' => 'incidentes-accidentes',
        'revision-resultados' => 'revision-resultados',
        'evaluacion-desempeno' => 'evaluacion-desempeño',
    ];

    private static function storageDirectories(): array
    {
        $root = dirname(__DIR__, 2) . '/public/uploads/archivos/';
        $directories = [];
        foreach (self::DIRECTORIOS as $tipo => $directorio) {
            $directories['sasisopa-' . $tipo] = $root . $directorio . '/';
        }
        return $directories;
    }

    /** @return array{archivo_disponible: bool, archivo_url: ?string} */
    public static function metadata(?string $referencia, string $tipo): array
    {
        $basename = basename(str_replace('\\', '/', trim((string) $referencia)));
        $directorios = self::storageDirectories();
        $base = $directorios['sasisopa-' . $tipo] ?? null;
        $baseReal = $base !== null ? realpath($base) : false;
        $archivoReal = ($base !== null && $basename !== '' && $basename !== '.' && $basename !== '..')
            ? realpath($base . $basename)
            : false;
        $disponible = $baseReal !== false
            && $archivoReal !== false
            && is_file($archivoReal)
            && str_starts_with($archivoReal, rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);

        return [
            'archivo_disponible' => $disponible,
            'archivo_url' => $disponible
                ? '/download?tipo=sasisopa-' . rawurlencode($tipo) . '&file=' . rawurlencode($basename)
                : null,
        ];
    }

    /** @return array<string, bool|string|null> */
    public static function fieldMetadata(?string $referencia, string $tipo, string $campo): array
    {
        $metadata = self::metadata($referencia, $tipo);
        return [
            $campo . '_disponible' => $metadata['archivo_disponible'],
            $campo . '_url' => $metadata['archivo_url'],
        ];
    }
}
