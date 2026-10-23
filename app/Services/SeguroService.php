<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PolizaSeguro;
use App\Models\PolizaSeguroCobertura;
use App\Core\Auth;
use Illuminate\Database\Capsule\Manager as Capsule;

final class SeguroService
{
    private const MAX_UPLOAD_BYTES = 10485760;
    private const MODELOS = [PolizaSeguro::class, PolizaSeguroCobertura::class];

    private static function directorio(): string
    {
        return dirname(__DIR__, 2) . '/public/uploads/archivos/poliza-seguro/';
    }

    private static function usuarioAdministrador(): bool
    {
        return in_array((int) (Auth::user()?->id_puesto ?? 0), [25, 13], true);
    }

    public static function getRegistros(string $modelo, bool $soloNoEliminado = false): array
    {
        self::validarModelo($modelo);

        return $modelo::query()
            ->when($soloNoEliminado, static fn($query) => $query->whereIn('estatus', [0, 1]))
            ->orderByDesc('fecha_hora')->orderByDesc('id')->get()
            ->map(static function ($registro): array {
                $archivo = basename((string) $registro->archivo);
                $fecha = (string) $registro->fecha_hora;
                $timestamp = strtotime($fecha);
                $fechaFormateada = formatearFecha($registro->fecha_hora);
                return [
                    'id' => (int) $registro->id,
                    'fecha_hora' => $fecha,
                    'fecha_hora_formateada' => trim($fechaFormateada . ($timestamp === false ? '' : ' ' . date('g:i a', $timestamp))),
                    'archivo' => $archivo,
                    'archivo_disponible' => $archivo !== '' && is_file(self::directorio() . $archivo),
                    'estatus' => (int) $registro->estatus,
                ];
            })->all();
    }

    /** @return array{success:bool,message:string,status?:int} */
    public static function crear(string $modelo, ?array $file): array
    {
        self::validarModelo($modelo);
        if (!self::usuarioAdministrador()) {
            return ['success' => false, 'message' => 'Solo los puestos 25 y 13 pueden administrar Seguro.', 'status' => 403];
        }
        $validacion = self::validarArchivo($file);
        if (!$validacion['valid']) {
            return ['success' => false, 'message' => $validacion['message'], 'status' => 422];
        }

        $directorio = self::directorio();
        if (!is_dir($directorio) && !mkdir($directorio, 0775, true) && !is_dir($directorio)) {
            return ['success' => false, 'message' => 'No fue posible preparar el almacenamiento de archivos.', 'status' => 500];
        }

        $nombre = bin2hex(random_bytes(16)) . '.' . $validacion['extension'];
        $destino = $directorio . $nombre;
        if (!move_uploaded_file($file['tmp_name'], $destino)) {
            return ['success' => false, 'message' => 'No fue posible guardar el archivo.', 'status' => 500];
        }

        try {
            Capsule::transaction(static function () use ($modelo, $nombre): void {
                $ultimo = $modelo::query()->where('estatus', 0)->orderByDesc('id')->first();
                if ($ultimo) {
                    $ultimo->estatus = 1;
                    $ultimo->save();
                }

                $modelo::create(['archivo' => $nombre, 'estatus' => 0]);
            });
        } catch (\Throwable $error) {
            if (is_file($destino)) {
                unlink($destino);
            }
            error_log('SeguroService::crear: ' . $error->getMessage());
            return ['success' => false, 'message' => 'No se pudo registrar el archivo.', 'status' => 500];
        }

        return ['success' => true, 'message' => 'OK'];
    }

    public static function eliminar(string $modelo, int $id): bool
    {
        self::validarModelo($modelo);
        if (!self::usuarioAdministrador()) {
            return false;
        }
        $registro = $modelo::query()->whereKey($id)->whereIn('estatus', [0, 1])->first();
        if (!$registro) {
            return false;
        }

        $registro->estatus = 2;
        return (bool) $registro->save();
    }

    public static function autorizaDescarga(string $archivo): bool
    {
        if (str_contains($archivo, '/') || str_contains($archivo, '\\')) {
            return false;
        }
        $archivo = basename($archivo);
        if ($archivo === '' || $archivo !== trim($archivo) || str_contains($archivo, '..')) {
            return false;
        }
        if (!is_file(self::directorio() . $archivo)) {
            return false;
        }

        foreach (self::MODELOS as $modelo) {
            if ($modelo::query()->where('archivo', $archivo)->whereIn('estatus', [0, 1])->exists()) {
                return true;
            }
        }
        return false;
    }

    public static function notificarTelegram(string $accion, string $tipo, int $idUsuario): void
    {
        try {
            if (trim((string) telegramBotToken()) === '') {
                return;
            }
            $icono = $accion === 'agregar' ? '📄' : '🗑️';
            $verbo = $accion === 'agregar' ? 'se agregó' : 'se eliminó';
            $usuario = htmlspecialchars((string) (\App\Core\Auth::name() ?? 'Usuario'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $mensaje = $icono . ' En el módulo <b>Seguro</b> ' . $verbo . ' un registro de <b>'
                . htmlspecialchars($tipo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b>.' . "\n"
                . '👤 Responsable: ' . $usuario;

            $telegram = new TelegramService();
            $destinatarios = array_values(array_unique(array_merge(
                $telegram->getUserIdsByPuesto(25, $idUsuario),
                $telegram->getUserIdsByPuesto(13, $idUsuario)
            )));
            $telegram->sendMessageToMultiple($destinatarios, $mensaje);
        } catch (\Throwable $error) {
            error_log('SeguroService::notificarTelegram: ' . $error->getMessage());
        }
    }

    /** @return array{valid:bool,message:string,extension?:string} */
    private static function validarArchivo(?array $file): array
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'message' => 'Debes seleccionar un archivo válido.'];
        }
        if (!isset($file['tmp_name'], $file['size'], $file['name'])
            || !is_string($file['tmp_name']) || !is_int($file['size'])
            || $file['size'] < 1 || $file['size'] > self::MAX_UPLOAD_BYTES
            || !is_uploaded_file($file['tmp_name'])) {
            return ['valid' => false, 'message' => 'El archivo excede el tamaño permitido o es inválido.'];
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $tipos = ['pdf' => ['application/pdf'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png']];
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset($tipos[$extension]) || !in_array($mime, $tipos[$extension], true)) {
            return ['valid' => false, 'message' => 'Solo se permiten archivos PDF, JPG o PNG válidos.'];
        }

        return ['valid' => true, 'message' => 'OK', 'extension' => $extension];
    }

    private static function validarModelo(string $modelo): void
    {
        if (!in_array($modelo, self::MODELOS, true)) {
            throw new \InvalidArgumentException('Tipo de registro de Seguro no válido.');
        }
    }
}
