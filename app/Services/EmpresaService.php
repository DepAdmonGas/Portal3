<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Models\Empresa;
use App\Models\EmpresaTipo;
use Throwable;

final class EmpresaService
{
    private const PUESTO_ADMIN = 25;
    private const MAX_BYTES = 26214400;

    private static function directorio(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR
            . 'uploads' . DIRECTORY_SEPARATOR . 'archivos' . DIRECTORY_SEPARATOR . 'empresa';
    }

    public static function puedeConsultar(): bool
    {
        return Auth::user() !== null;
    }

    public static function puedeAdministrar(): bool
    {
        return (int) (Auth::user()?->id_puesto ?? 0) === self::PUESTO_ADMIN;
    }

    public static function tipos(): array
    {
        return EmpresaTipo::query()
            ->where('estatus', 0)
            ->orderBy('id')
            ->get(['id', 'tipo'])
            ->map(static fn(EmpresaTipo $tipo): array => [
                'id' => (int) $tipo->id,
                'tipo' => (string) $tipo->tipo,
            ])
            ->all();
    }

    public static function listar(): array
    {
        return Empresa::query()
            ->with('tipoPuesto:id,tipo,estatus')
            ->orderBy('id_tipo')
            ->orderBy('id')
            ->get(['id', 'descripcion', 'id_tipo', 'archivo', 'estatus'])
            ->map(static function (Empresa $empresa): array {
                $archivo = basename(str_replace('\\', '/', (string) $empresa->archivo));
                return [
                    'id' => (int) $empresa->id,
                    'descripcion' => (string) $empresa->descripcion,
                    'id_tipo' => (int) $empresa->id_tipo,
                    'tipo' => (string) ($empresa->tipoPuesto?->tipo ?? ''),
                    'archivo' => $archivo,
                    'archivo_disponible' => $archivo !== ''
                        && is_file(self::directorio() . DIRECTORY_SEPARATOR . $archivo),
                    'estatus' => (int) $empresa->estatus,
                ];
            })
            ->all();
    }

    public static function crear(array $datos, ?array $archivo): array
    {
        if (!self::puedeAdministrar()) {
            return self::error(403, 'Solo los usuarios del puesto 25 pueden administrar la información de Empresa.');
        }

        [$validos, $errores] = self::validar($datos, $archivo, true);
        if ($errores !== []) {
            return self::error(422, 'Verifique la información capturada.', $errores);
        }

        $ruta = self::guardarArchivo($archivo, $errores);
        if ($ruta === null) {
            return self::error(500, $errores['archivo'] ?? 'No fue posible guardar el archivo.');
        }

        try {
            $registro = Empresa::query()->create([
                'descripcion' => $validos['descripcion'],
                'id_tipo' => $validos['id_tipo'],
                'archivo' => basename($ruta),
                'estatus' => 0,
            ]);
        } catch (Throwable $e) {
            @unlink($ruta);
            error_log('EmpresaService::crear - ' . $e->getMessage());
            return self::error(500, 'No fue posible registrar la información.');
        }

        self::notificar('agregó', (string) $registro->descripcion);
        return ['ok' => true, 'status' => 200, 'message' => 'El registro se agregó correctamente.'];
    }

    public static function actualizar(int $id, array $datos, ?array $archivo): array
    {
        if (!self::puedeAdministrar()) {
            return self::error(403, 'Solo los usuarios del puesto 25 pueden administrar la información de Empresa.');
        }

        if ($id <= 0) {
            return self::error(422, 'El registro no es válido.', ['id' => 'El identificador no es válido.']);
        }

        $registro = Empresa::query()->find($id);
        if ($registro === null) {
            return self::error(404, 'El registro de Empresa no existe.');
        }

        [$validos, $errores] = self::validar($datos, $archivo, false);
        if ($errores !== []) {
            return self::error(422, 'Verifique la información capturada.', $errores);
        }

        $ruta = null;
        if (self::tieneArchivo($archivo)) {
            $ruta = self::guardarArchivo($archivo, $errores);
            if ($ruta === null) {
                return self::error(422, 'No fue posible guardar el archivo.', $errores);
            }
        }

        try {
            $registro->descripcion = $validos['descripcion'];
            $registro->id_tipo = $validos['id_tipo'];
            if ($ruta !== null) {
                $registro->archivo = basename($ruta);
            }
            $registro->save();
        } catch (Throwable $e) {
            if ($ruta !== null) {
                @unlink($ruta);
            }
            error_log('EmpresaService::actualizar - ' . $e->getMessage());
            return self::error(500, 'No fue posible actualizar el registro.');
        }

        // El archivo previo se conserva como histórico para evitar romper vínculos externos.
        self::notificar('actualizó', (string) $registro->descripcion);
        return ['ok' => true, 'status' => 200, 'message' => 'El registro se actualizó correctamente.'];
    }

    public static function eliminar(int $id): array
    {
        if (!self::puedeAdministrar()) {
            return self::error(403, 'Solo los usuarios del puesto 25 pueden administrar la información de Empresa.');
        }

        if ($id <= 0) {
            return self::error(422, 'El registro no es válido.', ['id' => 'El identificador no es válido.']);
        }

        $registro = Empresa::query()->find($id);
        if ($registro === null) {
            return self::error(404, 'El registro de Empresa no existe.');
        }

        $descripcion = (string) $registro->descripcion;
        try {
            $registro->delete();
        } catch (Throwable $e) {
            error_log('EmpresaService::eliminar - ' . $e->getMessage());
            return self::error(500, 'No fue posible eliminar el registro.');
        }

        // No se elimina el archivo físico: la versión anterior no lo hacía y puede ser histórico.
        self::notificar('eliminó', $descripcion);
        return ['ok' => true, 'status' => 200, 'message' => 'El registro se eliminó correctamente.'];
    }

    public static function autorizaDescarga(string $archivo): bool
    {
        $archivo = basename(str_replace('\\', '/', $archivo));
        if ($archivo === '' || $archivo === '.' || $archivo === '..') {
            return false;
        }

        return Empresa::query()->where('archivo', $archivo)->exists();
    }

    private static function archivoDisponible(string $archivo): bool
    {
        return $archivo !== '' && self::autorizaDescarga($archivo)
            && is_file(self::directorio() . DIRECTORY_SEPARATOR . $archivo);
    }

    private static function validar(array $datos, ?array $archivo, bool $archivoRequerido): array
    {
        $descripcion = trim((string) ($datos['descripcion'] ?? ''));
        $idTipo = filter_var($datos['id_tipo'] ?? null, FILTER_VALIDATE_INT);
        $errores = [];

        if ($descripcion === '') {
            $errores['descripcion'] = 'La descripción es obligatoria.';
        } elseif (mb_strlen($descripcion) > 2000) {
            $errores['descripcion'] = 'La descripción no debe exceder 2000 caracteres.';
        }
        if ($idTipo === false || $idTipo <= 0 || !EmpresaTipo::query()->where('id', $idTipo)->where('estatus', 0)->exists()) {
            $errores['id_tipo'] = 'Seleccione un tipo válido.';
        }

        if ($archivoRequerido || self::tieneArchivo($archivo)) {
            self::validarArchivo($archivo, $errores);
        }

        return [[
            'descripcion' => $descripcion,
            'id_tipo' => $idTipo === false ? 0 : (int) $idTipo,
        ], $errores];
    }

    private static function tieneArchivo(?array $archivo): bool
    {
        return $archivo !== null && (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    private static function validarArchivo(?array $archivo, array &$errores): void
    {
        if (!self::tieneArchivo($archivo)) {
            $errores['archivo'] = 'Seleccione un archivo PDF.';
            return;
        }

        $nombre = basename(str_replace('\\', '/', (string) ($archivo['name'] ?? '')));
        $tmp = (string) ($archivo['tmp_name'] ?? '');
        $error = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);
        $size = (int) ($archivo['size'] ?? 0);
        if ($error !== UPLOAD_ERR_OK) {
            $errores['archivo'] = in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'El archivo excede el tamaño permitido.'
                : 'No fue posible recibir el archivo.';
        } elseif ($nombre === '' || str_contains($nombre, "\0") || strtolower(pathinfo($nombre, PATHINFO_EXTENSION)) !== 'pdf') {
            $errores['archivo'] = 'Solo se permiten archivos PDF.';
        } elseif ($size <= 0 || $size > self::MAX_BYTES) {
            $errores['archivo'] = 'El archivo debe pesar más de 0 y no exceder 25 MB.';
        } elseif (!is_uploaded_file($tmp) || (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) !== 'application/pdf') {
            $errores['archivo'] = 'El archivo recibido no es un PDF válido.';
        }
    }

    private static function guardarArchivo(?array $archivo, array &$errores): ?string
    {
        $directorio = self::directorio();
        if (!is_dir($directorio) && !@mkdir($directorio, 0775, true) && !is_dir($directorio)) {
            $errores['archivo'] = 'No fue posible preparar la carpeta de archivos.';
            return null;
        }

        $nombre = basename(str_replace('\\', '/', (string) ($archivo['name'] ?? 'empresa.pdf')));
        $base = preg_replace('/[^\pL\pN._ -]+/u', '_', pathinfo($nombre, PATHINFO_FILENAME)) ?: 'empresa';
        $destino = $directorio . DIRECTORY_SEPARATOR . bin2hex(random_bytes(8)) . '-' . trim($base, ' .-_') . '.pdf';
        if (!move_uploaded_file((string) $archivo['tmp_name'], $destino)) {
            $errores['archivo'] = 'No fue posible guardar el archivo.';
            return null;
        }
        return $destino;
    }

    private static function notificar(string $accion, string $descripcion): void
    {
        try {
            $telegram = new TelegramService();
            $destinatarios = $telegram->getUserIdsByPuesto(self::PUESTO_ADMIN, 0);
            if ($destinatarios === []) {
                return;
            }

            $mensaje = '🏢 <b>Directorio Empresa</b>' . PHP_EOL
                . 'Un usuario ' . $accion . ' un registro.' . PHP_EOL
                . '📄 <b>Descripción:</b> ' . htmlspecialchars($descripcion, ENT_QUOTES, 'UTF-8');
            $telegram->sendMessageToMultiple($destinatarios, $mensaje);
        } catch (Throwable $e) {
            error_log('EmpresaService::notificar - ' . $e->getMessage());
        }
    }

    private static function error(int $status, string $message, array $errors = []): array
    {
        return ['ok' => false, 'status' => $status, 'message' => $message, 'errors' => $errors];
    }
}
