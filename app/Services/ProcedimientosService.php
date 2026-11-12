<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Models\ActividadesTecnica;
use App\Models\VisitaEstacion;
use App\Models\Usuario;
use Throwable;

final class ProcedimientosService
{
    public const PUESTO_ADMIN = 25;

    private const MAX_BYTES = 26214400;

    /** @return class-string<ActividadesTecnica|VisitaEstacion>|null */
    private static function modelo(string $tipo): ?string
    {
        return match ($tipo) {
            'actividades-tecnicas' => ActividadesTecnica::class,
            'visita-estacion' => VisitaEstacion::class,
            default => null,
        };
    }

    private static function directorio(string $tipo): ?string
    {
        $subdirectorio = match ($tipo) {
            'actividades-tecnicas' => 'actividades-tecnicas',
            'visita-estacion' => 'visita-estacion',
            default => null,
        };

        return $subdirectorio === null
            ? null
            : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR
                . 'uploads' . DIRECTORY_SEPARATOR . 'archivos' . DIRECTORY_SEPARATOR . $subdirectorio;
    }

    public static function usuario(): ?Usuario
    {
        return Auth::user();
    }

    public static function esAdministrador(): bool
    {
        $usuario = self::usuario();
        return $usuario !== null && (int) ($usuario->id_puesto ?? 0) === self::PUESTO_ADMIN;
    }

    public static function puedeAdministrar(): bool
    {
        return self::esAdministrador();
    }

    public static function puedeConsultar(): bool
    {
        return self::usuario() !== null;
    }

    public static function puedeDescargar(): bool
    {
        return self::puedeConsultar();
    }

    public static function listar(string $tipo): array
    {
        $modelo = self::modelo($tipo);
        if ($modelo === null) {
            return [];
        }

        $id = $tipo === 'actividades-tecnicas' ? 'id_actividades_tecnicas' : 'id_visita_estacion';

        return $modelo::query()->orderBy($id)->get()->map(static function ($registro): array {
            $tipoRegistro = $registro instanceof ActividadesTecnica
                ? 'actividades-tecnicas'
                : 'visita-estacion';
            $archivo = trim((string) $registro->archivo);

            return [
                ($tipoRegistro === 'actividades-tecnicas' ? 'id_actividades_tecnicas' : 'id_visita_estacion') => (int) $registro->getKey(),
                'nombre_a' => (string) $registro->nombre_a,
                'archivo' => $archivo,
                'archivo_disponible' => self::archivoDisponible($tipoRegistro, $archivo),
            ];
        })->all();
    }

    /** @return array{ok:bool,status:int,message:string,errors?:array} */
    public static function crear(string $tipo, array $datos, ?array $archivo): array
    {
        $modelo = self::modelo($tipo);
        if ($modelo === null) {
            return self::error(404, 'La categoría de procedimientos no existe.');
        }

        [$nombre, $errores] = self::validarNombre($datos['nombre_a'] ?? null);
        $archivoValidado = self::validarArchivo($archivo, true, $errores);
        if ($errores !== []) {
            return self::error(422, 'Verifique la información capturada.', $errores);
        }

        $destino = self::guardarArchivo($tipo, $archivoValidado, $errores);
        if ($destino === null) {
            return self::error(500, 'No fue posible guardar el archivo del procedimiento.');
        }

        try {
            $registro = $modelo::create(['nombre_a' => $nombre, 'archivo' => basename($destino)]);
        } catch (Throwable $e) {
            @unlink($destino);
            error_log('ProcedimientosService::crear - ' . $e->getMessage());
            return self::error(500, 'No fue posible registrar el procedimiento.');
        }

        self::notificar('agregar', $tipo, $nombre);
        return ['ok' => true, 'status' => 200, 'message' => 'El procedimiento se agregó correctamente.'];
    }

    /** @return array{ok:bool,status:int,message:string,errors?:array} */
    public static function actualizar(string $tipo, int $id, array $datos, ?array $archivo): array
    {
        $modelo = self::modelo($tipo);
        if ($modelo === null) {
            return self::error(404, 'La categoría de procedimientos no existe.');
        }
        if ($id <= 0) {
            return self::error(422, 'El registro no es válido.', ['id' => 'El identificador es obligatorio.']);
        }

        $registro = $modelo::find($id);
        if ($registro === null) {
            return self::error(404, 'El procedimiento no existe.');
        }

        [$nombre, $errores] = self::validarNombre($datos['nombre_a'] ?? null);
        $archivoValidado = self::validarArchivo($archivo, false, $errores);
        if ($errores !== []) {
            return self::error(422, 'Verifique la información capturada.', $errores);
        }

        $archivoAnterior = basename((string) $registro->archivo);
        $destinoNuevo = null;
        if ($archivoValidado !== null) {
            $destinoNuevo = self::guardarArchivo($tipo, $archivoValidado, $errores);
            if ($destinoNuevo === null) {
                return self::error(500, 'No fue posible guardar el archivo del procedimiento.');
            }
        }

        try {
            $registro->nombre_a = $nombre;
            if ($destinoNuevo !== null) {
                $registro->archivo = basename($destinoNuevo);
            }
            $registro->save();
        } catch (Throwable $e) {
            if ($destinoNuevo !== null) {
                @unlink($destinoNuevo);
            }
            error_log('ProcedimientosService::actualizar - ' . $e->getMessage());
            return self::error(500, 'No fue posible actualizar el procedimiento.');
        }

        if ($destinoNuevo !== null && $archivoAnterior !== '') {
            self::eliminarArchivoSiNoReferenciado($tipo, $archivoAnterior);
        }
        self::notificar('editar', $tipo, $nombre);
        return ['ok' => true, 'status' => 200, 'message' => 'El procedimiento se actualizó correctamente.'];
    }

    /** @return array{ok:bool,status:int,message:string,errors?:array} */
    public static function eliminar(string $tipo, int $id): array
    {
        $modelo = self::modelo($tipo);
        if ($modelo === null) {
            return self::error(404, 'La categoría de procedimientos no existe.');
        }
        if ($id <= 0) {
            return self::error(422, 'El registro no es válido.', ['id' => 'El identificador es obligatorio.']);
        }

        $registro = $modelo::find($id);
        if ($registro === null) {
            return self::error(404, 'El procedimiento no existe.');
        }

        $nombre = (string) $registro->nombre_a;
        $archivo = basename((string) $registro->archivo);
        try {
            $registro->delete();
        } catch (Throwable $e) {
            error_log('ProcedimientosService::eliminar - ' . $e->getMessage());
            return self::error(500, 'No fue posible eliminar el procedimiento.');
        }

        if ($archivo !== '') {
            self::eliminarArchivoSiNoReferenciado($tipo, $archivo);
        }
        self::notificar('eliminar', $tipo, $nombre);
        return ['ok' => true, 'status' => 200, 'message' => 'El procedimiento se eliminó correctamente.'];
    }

    private static function validarNombre(mixed $valor): array
    {
        $nombre = trim((string) $valor);
        $errores = [];
        if ($nombre === '') {
            $errores['nombre_a'] = 'El nombre es obligatorio.';
        } elseif (mb_strlen($nombre) > 255) {
            $errores['nombre_a'] = 'El nombre no debe exceder 255 caracteres.';
        }
        return [$nombre, $errores];
    }

    private static function validarArchivo(?array $archivo, bool $requerido, array &$errores): ?array
    {
        if ($archivo === null || (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            if ($requerido) {
                $errores['archivo'] = 'Seleccione un archivo PDF.';
            }
            return null;
        }

        $error = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);
        $nombre = basename(str_replace('\\', '/', (string) ($archivo['name'] ?? '')));
        $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        $tmp = (string) ($archivo['tmp_name'] ?? '');
        $size = (int) ($archivo['size'] ?? 0);

        if ($error !== UPLOAD_ERR_OK) {
            $errores['archivo'] = in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'El archivo excede el tamaño permitido.'
                : 'No fue posible recibir el archivo.';
        } elseif ($nombre === '' || str_contains($nombre, "\0") || $extension !== 'pdf') {
            $errores['archivo'] = 'Solo se permiten archivos PDF.';
        } elseif ($size <= 0 || $size > self::MAX_BYTES) {
            $errores['archivo'] = 'El archivo debe pesar más de 0 y no exceder 25 MB.';
        } elseif (!is_uploaded_file($tmp)) {
            $errores['archivo'] = 'El archivo recibido no es válido.';
        } else {
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
            if ($mime !== 'application/pdf') {
                $errores['archivo'] = 'El contenido del archivo no corresponde a un PDF válido.';
            }
        }

        return isset($errores['archivo']) ? null : $archivo;
    }

    private static function guardarArchivo(string $tipo, array $archivo, array &$errores): ?string
    {
        $directorio = self::directorio($tipo);
        if ($directorio === null) {
            return null;
        }
        if (!is_dir($directorio) && !@mkdir($directorio, 0775, true) && !is_dir($directorio)) {
            $errores['archivo'] = 'No fue posible preparar la carpeta de archivos.';
            return null;
        }

        $nombre = basename(str_replace('\\', '/', (string) ($archivo['name'] ?? 'procedimiento.pdf')));
        $base = pathinfo($nombre, PATHINFO_FILENAME);
        $base = preg_replace('/[^\pL\pN._ -]+/u', '_', $base) ?: 'procedimiento';
        $destino = $directorio . DIRECTORY_SEPARATOR . bin2hex(random_bytes(8)) . '-' . trim($base, ' .-_') . '.pdf';

        if (!move_uploaded_file((string) $archivo['tmp_name'], $destino)) {
            $errores['archivo'] = 'No fue posible guardar el archivo del procedimiento.';
            return null;
        }
        return $destino;
    }

    public static function archivoDisponible(string $tipo, string $archivo): bool
    {
        $directorio = self::directorio($tipo);
        $nombre = basename($archivo);
        return $directorio !== null && $nombre !== '' && is_file($directorio . DIRECTORY_SEPARATOR . $nombre);
    }

    private static function eliminarArchivoSiNoReferenciado(string $tipo, string $archivo): void
    {
        $nombre = basename($archivo);
        $otroModelo = $tipo === 'actividades-tecnicas' ? VisitaEstacion::class : ActividadesTecnica::class;
        $modelo = self::modelo($tipo);

        if ($modelo === null || $nombre === '') {
            return;
        }

        try {
            if ($modelo::where('archivo', $nombre)->exists() || $otroModelo::where('archivo', $nombre)->exists()) {
                return;
            }
            $directorio = self::directorio($tipo);
            $ruta = $directorio !== null ? realpath($directorio . DIRECTORY_SEPARATOR . $nombre) : false;
            $base = $directorio !== null ? realpath($directorio) : false;
            if ($ruta !== false && $base !== false && dirname($ruta) === $base && is_file($ruta)) {
                @unlink($ruta);
            }
        } catch (Throwable $e) {
            error_log('ProcedimientosService::eliminarArchivoSiNoReferenciado - ' . $e->getMessage());
        }
    }

    private static function notificar(string $accion, string $tipo, string $nombre): void
    {
        try {
            $usuario = self::usuario();
            $idUsuario = (int) ($usuario->id ?? 0);
            $nombreUsuario = htmlspecialchars((string) ($usuario->nombre ?? ''), ENT_QUOTES, 'UTF-8');
            $titulo = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
            $categoria = $tipo === 'actividades-tecnicas' ? 'Actividades Técnicas' : 'Visitas a la Estación';
            $verbo = match ($accion) {
                'agregar' => 'agregó',
                'editar' => 'actualizó',
                default => 'eliminó',
            };
            $mensaje = "<b>Procedimiento {$verbo}</b>\n\n"
                . "📁 Categoría: <b>{$categoria}</b>\n"
                . "📄 Nombre: <b>{$titulo}</b>";
            if ($nombreUsuario !== '') {
                $mensaje .= "\n👤 Usuario: {$nombreUsuario}";
            }

            $telegram = new TelegramService();
            foreach ($telegram->getUserIdsDeptoOperativo($idUsuario) as $destinatario) {
                $telegram->sendTokenAsync((int) $destinatario, $mensaje);
            }
        } catch (Throwable $e) {
            error_log('ProcedimientosService::notificar - ' . $e->getMessage());
        }
    }

    private static function error(int $status, string $message, array $errors = []): array
    {
        return ['ok' => false, 'status' => $status, 'message' => $message, 'errors' => $errors];
    }
}
