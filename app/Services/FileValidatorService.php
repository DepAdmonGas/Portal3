<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use Throwable;

class FileValidatorService
{
    /**
     * Valida si el tipo MIME real de un archivo se encuentra dentro de los permitidos
     * analizando los Magic Bytes del archivo mediante finfo.
     *
     * @param string $tmpPath Ruta temporal del archivo
     * @param array $allowedMimes Lista de MIME types permitidos
     */
    public function isValidMimeType(string $tmpPath, array $allowedMimes = []): bool
    {
        if (!file_exists($tmpPath) || !is_readable($tmpPath) || empty($allowedMimes)) {
            return false;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return false;
        }

        $realMimeType = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        if ($realMimeType === false) {
            return false;
        }

        return in_array($realMimeType, $allowedMimes, true);
    }

    /**
     * Valida la integridad física de imágenes de mapa de bits (JPG, PNG, WEBP, GIF, etc.)
     * y las dimensiones para prevenir ataques de descompresión (Pixel Floods / Image Bombs).
     *
     * @param string $tmpPath Ruta temporal del archivo
     * @param int $maxWidth Ancho máximo permitido en píxeles
     * @param int $maxHeight Alto máximo permitido en píxeles
     * @param int $maxMegapixels Límite de megapíxeles totales (evita desbordamiento de memoria GD/Imagick)
     */
    public function isValidImageIntegrity(
        string $tmpPath,
        int $maxWidth = 4096,
        int $maxHeight = 4096,
        int $maxMegapixels = 12
    ): bool {
        if (!file_exists($tmpPath) || !is_readable($tmpPath)) {
            return false;
        }

        // getimagesize intenta parsear el encabezado gráfico real de la imagen
        $imageInfo = @getimagesize($tmpPath);

        if ($imageInfo === false) {
            return false; // El archivo está dañado o no es una imagen real
        }

        [$width, $height] = $imageInfo;

        // 1. Validar dimensiones máximas en píxeles
        if ($width <= 0 || $height <= 0 || $width > $maxWidth || $height > $maxHeight) {
            return false;
        }

        // 2. Prevenir Image Bombs calculando la resolución total en Megapíxeles
        $megapixels = ($width * $height) / 1_000_000;
        if ($megapixels > $maxMegapixels) {
            return false;
        }

        return true;
    }

    /**
     * Valida que un archivo SVG sea seguro y no contenga etiquetas <script>,
     * eventos JavaScript (onload, onclick) o referencias peligrosas a código (XSS).
     *
     * @param string $tmpPath Ruta temporal del archivo SVG
     */
    public function isSafeSvg(string $tmpPath): bool
    {
        if (!file_exists($tmpPath) || !is_readable($tmpPath)) {
            return false;
        }

        $content = file_get_contents($tmpPath);
        if ($content === false || empty(trim($content))) {
            return false;
        }

        // 1. Detección rápida de patrones peligrosos usando preg_match
        $dangerousPatterns = [
            '/<script/i',                      // Etiquetas de script
            '/javascript\s*:/i',              // URIs del tipo javascript:
            '/data\s*:\s*text\/html/i',        // Data URIs HTML
            '/xmlns\s*:\s*script/i',           // Espacios de nombres de script
            '/<foreignObject/i',              // Permite incrustar HTML dentro de SVG
            '/on[a-z]+\s*=/i',                 // Eventos JS (onload, onclick, onerror, etc.)
            '/<!ENTITY/i'                     // Ataques XXE (XML External Entity)
        ];

        foreach ($dangerousPatterns as $pattern) {
            // Se usa preg_match en lugar del nombre incorrecto anterior
            if (preg_match($pattern, $content) === 1) {
                return false;
            }
        }

        // 2. Parsing estricto con DOMDocument para asegurar que sea XML válido
        $libxmlState = libxml_use_internal_errors(true);
        $dom = new DOMDocument();

        // LIBXML_NONET evita la carga de entidades externas/red (protección XXE)
        $loaded = $dom->loadXML($content, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($libxmlState);

        if (!$loaded || !$dom->documentElement) {
            return false; // XML malformado o malicioso
        }

        // Confirmar que la etiqueta raíz sea <svg>
        return strtolower($dom->documentElement->tagName) === 'svg';
    }

    /**
     * Método helper integral para validar cualquier imagen (Raster o SVG).
     *
     * @param string $tmpPath Ruta del archivo temporal
     * @param array $allowedMimes Tipos permitidos
     * @param int $maxSizeBytes Tamaño máximo del archivo en bytes (por defecto 5MB)
     */
    public function validateImage(
        string $tmpPath,
        array $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'],
        int $maxSizeBytes = 5_242_880
    ): bool {
        // 1. Validar existencia y tamaño del archivo
        if (!file_exists($tmpPath) || filesize($tmpPath) > $maxSizeBytes) {
            return false;
        }

        // 2. Validar MIME Type por Magic Bytes
        if (!$this->isValidMimeType($tmpPath, $allowedMimes)) {
            return false;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        // 3. Regla específica para SVG
        if ($mime === 'image/svg+xml') {
            return $this->isSafeSvg($tmpPath);
        }

        // 4. Regla para imágenes de mapa de bits (JPG, PNG, WEBP, GIF, etc.)
        return $this->isValidImageIntegrity($tmpPath);
    }
}
