<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Models\Operativo\ProcedimientoModulo;
use Throwable;

class ProcedimientosDoService
{
    public const PUESTOS_PROCEDIMIENTOS = [3, 13];

    public const MODULOS = [
        'administrativos' => 'Administrativos',
        'importacion' => 'Importación',
        'mantenimiento' => 'Mantenimiento',
    ];

    private const EXTENSIONES = [
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'ppt',
        'pptx',
        'png',
        'jpg',
        'jpeg',
        'gif',
        'webp',
        'txt',
        'csv',
        'odt',
        'ods',
        'odp',
    ];

    private const MAX_BYTES = 26214400;

    public static function autorizado(): bool
    {
    $usuario = Auth::user();

    if ($usuario === null) {
        return false;
    }

    $idPuesto = (int) ($usuario->id_puesto ?? 0);

    // Permitir si es el puesto 3 o el puesto 13 (puedes agregar self::PUESTO o el valor directo)
    return $idPuesto === 3 || $idPuesto === 13;
    }

    public static function directorio(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR
            . 'uploads' . DIRECTORY_SEPARATOR . 'archivos' . DIRECTORY_SEPARATOR . 'procedimientos-modulos';
    }

    private static function directorioLegado(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'archivos' . DIRECTORY_SEPARATOR . 'procedimientos-modulos';
    }

    public static function modulos(): array
    {
        return self::MODULOS;
    }

    public static function esModuloValido(string $modulo): bool
    {
        return isset(self::MODULOS[$modulo]);
    }

    public static function nombreModulo(string $modulo): string
    {
        return self::MODULOS[$modulo] ?? '';
    }

    public static function listar(string $modulo): array
    {
        $registros = ProcedimientoModulo::where('modulo', $modulo)
            ->orderBy('fecha', 'DESC')
            ->orderBy('id', 'DESC')
            ->get();

        $filas = [];

        foreach ($registros as $registro) {
            $filas[] = self::fila($registro);
        }

        return $filas;
    }

    public static function crear(string $modulo, array $datos, ?array $archivo): array
    {
        $fecha = trim((string) ($datos['fecha'] ?? ''));
        $titulo = trim((string) ($datos['titulo'] ?? ''));

        $errores = [];

        $fechaValida = \DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);

        if (!$fechaValida || $fechaValida->format('Y-m-d') !== $fecha) {
            $errores['fecha'] = 'La fecha no es válida.';
        }

        if ($titulo === '') {
            $errores['titulo'] = 'El título es obligatorio.';
        } elseif (mb_strlen($titulo) > 500) {
            $errores['titulo'] = 'El título no debe exceder 500 caracteres.';
        }

        $errorArchivo = is_array($archivo) ? (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
        $nombreOriginal = '';

        if ($errorArchivo !== UPLOAD_ERR_OK) {
            $errores['documento'] = in_array($errorArchivo, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'El archivo excede el tamaño permitido.'
                : 'Debe seleccionar un archivo.';
        } else {
            $nombreOriginal = basename(str_replace('\\', '/', (string) ($archivo['name'] ?? '')));
            $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

            if ($nombreOriginal === '' || $extension === '' || str_contains($nombreOriginal, "\0")) {
                $errores['documento'] = 'El nombre del archivo no es válido.';
            } elseif (!in_array($extension, self::EXTENSIONES, true)) {
                $errores['documento'] = 'El tipo de archivo no está permitido.';
            } elseif ((int) ($archivo['size'] ?? 0) > self::MAX_BYTES) {
                $errores['documento'] = 'El archivo no debe exceder 25 MB.';
            }
        }

        if ($errores !== []) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Verifique la información capturada.',
                'errors' => $errores,
            ];
        }

        $directorio = self::directorio();

        if (!is_dir($directorio) && !@mkdir($directorio, 0775, true) && !is_dir($directorio)) {
            return [
                'ok' => false,
                'status' => 500,
                'message' => 'No fue posible preparar el directorio de procedimientos.',
                'errors' => [],
            ];
        }

        $nombreArchivo = uniqid() . '-' . $nombreOriginal;
        $destino = $directorio . DIRECTORY_SEPARATOR . $nombreArchivo;

        if (!move_uploaded_file((string) ($archivo['tmp_name'] ?? ''), $destino)) {
            return [
                'ok' => false,
                'status' => 500,
                'message' => 'No fue posible guardar el archivo del procedimiento.',
                'errors' => [],
            ];
        }

        try {
            $registro = ProcedimientoModulo::create([
                'fecha' => $fecha,
                'modulo' => $modulo,
                'titulo' => $titulo,
                'archivo' => $nombreArchivo,
            ]);
        } catch (Throwable $e) {
            @unlink($destino);
            error_log('ProcedimientosDoService::crear - ' . $e->getMessage());

            return [
                'ok' => false,
                'status' => 500,
                'message' => 'No fue posible registrar el procedimiento.',
                'errors' => [],
            ];
        }

        self::notificar('agregar', $registro);

        return [
            'ok' => true,
            'status' => 200,
            'message' => 'El procedimiento ha sido agregado exitosamente.',
            'errors' => [],
            'data' => self::fila($registro),
        ];
    }

    public static function eliminar(int $id): array
    {
        $registro = ProcedimientoModulo::find($id);

        if ($registro === null) {
            return [
                'ok' => false,
                'status' => 404,
                'message' => 'El procedimiento no existe.',
                'errors' => [],
            ];
        }

        try {
            $registro->delete();
        } catch (Throwable $e) {
            error_log('ProcedimientosDoService::eliminar - ' . $e->getMessage());

            return [
                'ok' => false,
                'status' => 500,
                'message' => 'No fue posible eliminar el procedimiento.',
                'errors' => [],
            ];
        }

        self::notificar('eliminar', $registro);

        return [
            'ok' => true,
            'status' => 200,
            'message' => 'El procedimiento ha sido eliminado exitosamente.',
            'errors' => [],
        ];
    }

    public static function rutaArchivo(string $archivo): string
    {
        $nombre = basename($archivo);
        $rutaActual = self::directorio() . DIRECTORY_SEPARATOR . $nombre;

        if (is_file($rutaActual)) {
            return $rutaActual;
        }

        // Conserva el acceso a documentos cargados antes del cambio de ubicación.
        return self::directorioLegado() . DIRECTORY_SEPARATOR . $nombre;
    }

    public static function archivoDisponible(string $archivo): bool
    {
        return $archivo !== '' && is_file(self::rutaArchivo($archivo));
    }

    public static function autorizaDescarga(string $archivo): bool
    {
        if (!self::autorizado()) {
            return false;
        }

        $archivo = basename($archivo);

        if ($archivo === '') {
            return false;
        }

        return ProcedimientoModulo::where('archivo', $archivo)->exists();
    }

    private static function fila(ProcedimientoModulo $registro): array
    {
        $archivo = trim((string) $registro->archivo);

        return [
            'id' => (int) $registro->id,
            'fecha' => formatearFecha($registro->fecha),
            'fecha_iso' => $registro->fecha !== null ? $registro->fecha->format('Y-m-d') : '',
            'titulo' => (string) $registro->titulo,
            'archivo' => $archivo,
            'archivo_disponible' => self::archivoDisponible($archivo),
        ];
    }

    private static function notificar(string $accion, ProcedimientoModulo $registro): void
    {
        try {
            $usuario = Auth::user();
            $idUsuario = (int) ($usuario->id ?? 0);
            $nombreUsuario = trim((string) ($usuario->nombre ?? ''));

            $telegram = new TelegramService();
            $destinatarios = $telegram->getUserIdsDeptoOperativo($idUsuario);

            if ($destinatarios === []) {
                return;
            }

            $modulo = strip_tags(self::nombreModulo((string) $registro->modulo));
            $titulo = htmlspecialchars((string) $registro->titulo, ENT_QUOTES, 'UTF-8');
            $fecha = formatearFecha($registro->fecha);

            $mensaje = $accion === 'agregar'
                ? "<b>Nuevo procedimiento disponible</b>\n\n"
                : "<b>Procedimiento eliminado</b>\n\n";

            $mensaje .= "\xF0\x9F\x93\x82 Módulo: <b>" . $modulo . "</b>\n";
            $mensaje .= "\xF0\x9F\x97\x93\xEF\xB8\x8F Fecha: " . $fecha . "\n";
            $mensaje .= "\xF0\x9F\x93\x84 " . ($accion === 'agregar' ? 'Título: ' : 'Título: ') . $titulo;

            if ($nombreUsuario !== '') {
                $mensaje .= "\n\xF0\x9F\x91\xA4 " . ($accion === 'agregar' ? 'Agregado por: ' : 'Eliminado por: ');
                $mensaje .= htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8');
            }

            $telegram->sendMessageToMultiple($destinatarios, $mensaje);
        } catch (Throwable $e) {
            error_log('ProcedimientosDoService::notificar - ' . $e->getMessage());
        }
    }
}
