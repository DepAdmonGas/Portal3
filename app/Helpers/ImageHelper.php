<?php

namespace App\Helpers;

class ImageHelper
{
    private static array $base64Cache = [];

    public static function base64(string $path): string
    {
        if (empty($path)) {
            return '';
        }

        if (isset(self::$base64Cache[$path])) {
            return self::$base64Cache[$path];
        }

        $content = @file_get_contents($path);

        if ($content === false) {
            self::$base64Cache[$path] = '';
            return '';
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $mimeTypes = [
            'jpg' => 'jpeg',
            'jpeg' => 'jpeg',
            'png'  => 'png',
            'gif'  => 'gif',
            'webp' => 'webp',
        ];

        $mime = $mimeTypes[$extension] ?? 'jpeg';
        $data = 'data:image/' . $mime . ';base64,' . base64_encode($content);

        self::$base64Cache[$path] = $data;

        return $data;
    }

    public static function logoUrl(): string
    {
        return base_url() . '/assets/images/logos/Logo.png';
    }

    public static function firmaUrl(string $firma): string
    {
        return base_url() . '/uploads/firma-personal/' . $firma;
    }

    public static function tirillaUrl(string $imagen): string
    {
        return base_url() . '/uploads/tirillas/' . $imagen;
    }
}
