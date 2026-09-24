<?php

namespace App\Services;

use App\Core\Session;
use App\Models\Operativo\CamionetaSaveiroDocumento;
use App\Models\Operativo\CamionetaSaveiroComentario;
use App\Models\Usuario;
use App\Services\TelegramService;

class CamionetaSaveiroService
{
    public const UPLOAD_FOLDER = 'public/uploads/archivos/camioneta-saveiro/';

    /**
     * Obtiene el listado de documentos formateando la fecha con formatearFecha()
     */
    public static function getDocumentos(?string $tipo = null): array
    {
        $query = CamionetaSaveiroDocumento::query()
            ->withCount('comentarios')
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        if (!empty($tipo) && $tipo !== 'Todos los documentos') {
            $query->where('tipo', $tipo);
        }

        return $query->get()->map(function (CamionetaSaveiroDocumento $item) {
            $rawFecha = $item->fecha ? $item->fecha->format('Y-m-d') : '';
            return [
                'id'                 => $item->id,
                'tipo'               => $item->tipo,
                'fecha'              => formatearFecha($rawFecha) ?: 'S/I',
                'fecha_raw'          => $rawFecha,
                'descripcion'        => $item->descripcion,
                'archivo'            => $item->archivo,
                'total_comentarios'  => (int)$item->comentarios_count,
            ];
        })->toArray();
    }

    /**
     * Registra un nuevo documento y almacena su archivo físico.
     */
    public static function storeDocumento(array $data, array $file): array
    {
        $tipo = trim($data['tipo'] ?? '');
        $fecha = trim($data['fecha'] ?? '');
        $descripcion = trim($data['descripcion'] ?? '');

        if ($tipo === '' || $fecha === '' || $descripcion === '' || empty($file['tmp_name'])) {
            return ['success' => false, 'message' => 'Todos los campos son obligatorios.', 'code' => 422];
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $numeroAleatorio1 = rand(1, 1000000);
        $numeroAleatorio2 = rand(1000, 9999);
        $nombreLimpio = preg_replace('/[^a-zA-Z0-9_-]/', '_', $tipo);
        $nuevoNombre = $numeroAleatorio1 . '-Archivo-' . $nombreLimpio . $numeroAleatorio2 . '.' . $extension;

        $directorioDestino = dirname(__DIR__, 2) . '/' . self::UPLOAD_FOLDER;
        if (!is_dir($directorioDestino)) {
            mkdir($directorioDestino, 0775, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $directorioDestino . $nuevoNombre)) {
            return ['success' => false, 'message' => 'Error al subir el archivo físico.', 'code' => 500];
        }

        $documento = CamionetaSaveiroDocumento::create([
            'tipo'        => $tipo,
            'fecha'       => $fecha,
            'descripcion' => $descripcion,
            'archivo'     => $nuevoNombre
        ]);

        self::notificarTelegram('agregó documento', [
            'tipo'        => $tipo,
            'fecha'       => $fecha,
            'descripcion' => $descripcion,
            'archivo'     => $nuevoNombre
        ]);

        return [
            'success' => true,
            'message' => 'Archivo agregado exitosamente.',
            'id'      => $documento->id,
            'code'    => 200
        ];
    }

    /**
     * Actualiza la información del documento y opcionalmente reemplaza el archivo.
     */
    public static function updateDocumento(int $id, array $data, ?array $file = null): array
    {
        $fecha = trim($data['fecha'] ?? '');
        $descripcion = trim($data['descripcion'] ?? '');

        if ($id <= 0 || $fecha === '' || $descripcion === '') {
            return ['success' => false, 'message' => 'La fecha y la descripción son obligatorias.', 'code' => 422];
        }

        $documento = CamionetaSaveiroDocumento::find($id);
        if (!$documento) {
            return ['success' => false, 'message' => 'El documento no existe.', 'code' => 404];
        }

        $documento->fecha = $fecha;
        $documento->descripcion = $descripcion;

        if (!empty($file['tmp_name'])) {
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $numeroAleatorio1 = rand(1, 1000000);
            $numeroAleatorio2 = rand(1000, 9999);
            $nombreLimpio = preg_replace('/[^a-zA-Z0-9_-]/', '_', $documento->tipo);
            $nuevoNombre = $numeroAleatorio1 . '-Archivo-' . $nombreLimpio . $numeroAleatorio2 . '.' . $extension;

            $directorioDestino = dirname(__DIR__, 2) . '/' . self::UPLOAD_FOLDER;
            if (move_uploaded_file($file['tmp_name'], $directorioDestino . $nuevoNombre)) {
                if ($documento->archivo && file_exists($directorioDestino . $documento->archivo)) {
                    @unlink($directorioDestino . $documento->archivo);
                }
                $documento->archivo = $nuevoNombre;
            }
        }

        $documento->save();

        self::notificarTelegram('modificó documento', [
            'tipo'        => $documento->tipo,
            'fecha'       => $fecha,
            'descripcion' => $descripcion
        ]);

        return ['success' => true, 'message' => 'Información editada exitosamente.', 'code' => 200];
    }

    /**
     * Elimina el documento, sus comentarios y el archivo físico.
     */
    public static function deleteDocumento(int $id): array
    {
        $documento = CamionetaSaveiroDocumento::find($id);
        if (!$documento) {
            return ['success' => false, 'message' => 'Documento no encontrado.', 'code' => 404];
        }

        $directorioDestino = dirname(__DIR__, 2) . '/' . self::UPLOAD_FOLDER;
        if ($documento->archivo && file_exists($directorioDestino . $documento->archivo)) {
            @unlink($directorioDestino . $documento->archivo);
        }

        $datosNotif = [
            'tipo'        => $documento->tipo,
            'fecha'       => $documento->fecha ? $documento->fecha->format('Y-m-d') : '',
            'descripcion' => $documento->descripcion
        ];

        CamionetaSaveiroComentario::where('id_documento', $id)->delete();
        $documento->delete();

        self::notificarTelegram('eliminó documento', $datosNotif);

        return ['success' => true, 'message' => 'Registro eliminado exitosamente.', 'code' => 200];
    }

    /**
     * Obtiene los comentarios aplicando formatearFecha()
     */
    public static function getComentarios(int $idDocumento): array
    {
        $idUsuarioActual = (int)(Session::get('usuario')['id'] ?? 0);

        return CamionetaSaveiroComentario::query()
            ->where('id_documento', $idDocumento)
            ->with('usuario:id,nombre')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function (CamionetaSaveiroComentario $c) use ($idUsuarioActual) {
                $fechaFmt = '';
                if ($c->fecha_hora) {
                    $fechaSolo = $c->fecha_hora->format('Y-m-d');
                    $fechaFmt = formatearFecha($fechaSolo) . ', ' . $c->fecha_hora->format('g:i a');
                }

                return [
                    'id'             => $c->id,
                    'usuario_nombre' => $c->usuario ? $c->usuario->nombre : 'Usuario',
                    'comentario'     => $c->comentario,
                    'fecha_hora'     => $fechaFmt,
                    'esPropio'       => $c->id_usuario === $idUsuarioActual
                ];
            })->toArray();
    }

    /**
     * Agrega un nuevo comentario.
     */
    public static function storeComentario(int $idDocumento, string $comentarioTexto): array
    {
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);
        $comentarioTexto = trim($comentarioTexto);

        if ($idDocumento <= 0 || $comentarioTexto === '') {
            return ['success' => false, 'message' => 'El comentario es requerido.', 'code' => 422];
        }

        $documento = CamionetaSaveiroDocumento::find($idDocumento);
        if (!$documento) {
            return ['success' => false, 'message' => 'Documento no encontrado.', 'code' => 404];
        }

        CamionetaSaveiroComentario::create([
            'id_documento' => $idDocumento,
            'id_usuario'   => $idUsuario,
            'comentario'   => $comentarioTexto,
            'fecha_hora'   => date('Y-m-d H:i:s')
        ]);

        self::notificarTelegram('comentó documento', [
            'tipo'        => $documento->tipo,
            'descripcion' => $documento->descripcion,
            'comentario'  => $comentarioTexto
        ]);

        return ['success' => true, 'message' => 'Comentario agregado exitosamente.', 'code' => 200];
    }

    private static function notificarTelegram(string $accion, array $extra = []): void
    {
        try {
            $sessionUsuario = Session::get('usuario');
            $idUsuario = (int)($sessionUsuario['id'] ?? 0);
            $idEstacion = (int)($sessionUsuario['id_estacion'] ?? 0);

            $user = Usuario::find($idUsuario);
            $nombreUsuario = $user ? $user->nombre : 'Desconocido';

            $iconos = [
                'agregó documento'   => '✅',
                'modificó documento' => '🔄',
                'eliminó documento'  => '🗑',
                'comentó documento'  => '💬',
            ];
            $icono = $iconos[$accion] ?? '📝';

            $detalle = $icono . ' Se ha <b>' . $accion . '</b> en el apartado de <b>Comercializadora</b>, correspondiente a la unidad <b>Camioneta Saveiro</b>:' . PHP_EOL . PHP_EOL;

            if (isset($extra['tipo'])) {
                $detalle .= '📁 <b>Tipo:</b> ' . $extra['tipo'] . PHP_EOL;
            }
            if (isset($extra['descripcion'])) {
                $detalle .= '📝 <b>Descripción:</b> ' . $extra['descripcion'] . PHP_EOL;
            }
            if (!empty($extra['fecha'])) {
                $detalle .= '📅 <b>Fecha:</b> ' . formatearFecha($extra['fecha']) . PHP_EOL;
            }
            if (isset($extra['comentario'])) {
                $detalle .= '💬 <b>Comentario:</b> ' . mb_strimwidth($extra['comentario'], 0, 100, '...') . PHP_EOL;
            }

            $detalle .= PHP_EOL . '👤 <b>Responsable:</b> ' . $nombreUsuario;

            TelegramService::notificar($idEstacion, $idUsuario, $detalle);
        } catch (\Throwable $e) {
            error_log('Error notificando camioneta saveiro a Telegram: ' . $e->getMessage());
        }
    }
}