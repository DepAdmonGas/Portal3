<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Usuario;
use App\Models\Estacion;
use App\Models\Operativo\RhLocalidad;
use App\Models\Operativo\BitacoraRH;
use App\Models\Operativo\BitacoraRHComentario;
use App\Models\Operativo\BitacoraRHDocumento;
use App\Models\Operativo\BitacoraRHRegistro;
use App\Services\ModuloDptoOperativoService;
use App\Services\MultiestacionService;
use App\Services\ModuleStationService;
use App\Services\TelegramService;

class BitacoraRrhhService
{
    public const MODULE_KEY = 'bitacora-rrhh';

    private const UPLOAD_DIR = __DIR__ . '/../../public/uploads/archivos/bitacora-rrhh/';

    private const NOMBRE_DIRECCION = 'Dirección de operaciones';

    public static function getPermisos(): array
    {
        $usuario = Auth::user();
        $sessionUsuario = Session::get('usuario');
        $idUsuario = (int)($sessionUsuario['id'] ?? 0);
        $idPuesto = (int)($usuario->id_puesto ?? 0);
        $idEstacion = (int)($sessionUsuario['id_estacion'] ?? 0);
        $multiestacion = !empty($sessionUsuario['multiestacion']);
        $nombrePuesto = $usuario->puesto->tipo_puesto ?? '';

        $permisosDb = ModuloDptoOperativoService::permisosSesion('recursos-humanos');

        $esDireccion = ($nombrePuesto === self::NOMBRE_DIRECCION);

        return [
            'id_usuario'     => $idUsuario,
            'id_estacion'    => $idEstacion,
            'id_puesto'      => $idPuesto,
            'nombre_puesto'  => $nombrePuesto,
            'multiestacion'  => $multiestacion,
            'puedeCrear'     => !empty($permisosDb['crear']),
            'puedeEditar'    => !empty($permisosDb['editar']),
            'puedeEliminar'  => !empty($permisosDb['eliminar']),
            'puedeDescargar' => !empty($permisosDb['descargar']),
            // Solo Dirección de operaciones puede finalizar y eliminar documentos
            'puedeFinalizar'   => $esDireccion,
            'puedeEliminarDoc' => $esDireccion,
            'puedeVerVisualizaciones' => ($idPuesto === 13),
        ];
    }

    /**
     * Conjunto de ids de localidad permitidos (estaciones + departamentos)
     * según la configuración del módulo y la multiestación del usuario.
     */
    public static function getAllowedLocalidadIds(): array
    {
        $ids = [];
        foreach (ModuleStationService::getAvailableStations(self::MODULE_KEY) as $s) {
            $ids[] = (int)$s['id'];
        }
        foreach (ModuleStationService::getAvailableDepartments(self::MODULE_KEY) as $d) {
            $ids[] = (int)$d['id'];
        }

        // Fallback a la restricción por puesto (solo cuando no hay config multiestación)
        if (empty($ids)) {
            $ids = self::getPuestoLocalidadIds();
        }

        return array_values(array_unique($ids));
    }

    /**
     * Restricción clásica por puesto (numlista de op_rh_localidades).
     */
    private static function getPuestoLocalidadIds(): array
    {
        $permisos = self::getPermisos();
        $idPuesto = $permisos['id_puesto'];

        // Devolvemos ids de localidad (no numlists)
        $todos = [
            'puesto12' => [2, 1, 3, 4, 5, 14],   // numlista 1,2,3,4,5,8 (Palo Solo, Interlomas, San Agustin, Gasomira, Valle, Bosque Real)
            'puesto4'  => [6, 7],                // numlista 6,7 (Esmegas, Xochimilco)
            'puesto13' => [2, 1, 3, 4, 5, 6, 7, 14, 8, 9, 10, 11, 12, 13, 15], // <=8, 10, 12
        ];

        if ($idPuesto === 4) {
            return $todos['puesto4'];
        }
        if ($idPuesto === 12) {
            return $todos['puesto12'];
        }

        // Puesto 13 => numlista <= 8 o 10 o 12 => ids 2,1,3,4,5,6,7,14,9,11
        return [2, 1, 3, 4, 5, 6, 7, 14, 9, 11];
    }

    public static function puedeVerLocalidad(int $idLocalidad): bool
    {
        $allowed = self::getAllowedLocalidadIds();
        return empty($allowed) || in_array($idLocalidad, $allowed, true);
    }

    public static function getNombreLocalidad(int $id): string
    {
        $loc = RhLocalidad::find($id);
        if ($loc) {
            return $loc->localidad;
        }
        $est = Estacion::find($id);
        if ($est) {
            return $est->nombre;
        }
        return '';
    }

    public static function getNombreMes(int $mes): string
    {
        return nombremes($mes);
    }

    /**
     * Listado de registros de la bitácora para un año/mes según el contexto
     * de estación (multiestación) seleccionado.
     */
    public static function getLista(int $idYear, int $idMes): array
    {
        $ctx = ModuleStationService::getContext(self::MODULE_KEY);
        $idEstacion = $ctx['id_estacion'];
        $idDepto = $ctx['id_depto'];

        $q = BitacoraRH::query()->where('year', $idYear)->where('mes', $idMes);

        if ($idDepto && !$idEstacion) {
            $q->where('id_estacion', $idDepto);
        } elseif ($idEstacion) {
            $q->where('id_estacion', $idEstacion);
        } else {
            $allowed = self::getAllowedLocalidadIds();
            if (!empty($allowed)) {
                $q->whereIn('id_estacion', $allowed);
            }
        }

        $q->orderBy('id', 'asc');

        $records = $q->get();

        $comentarioCounts = BitacoraRHComentario::selectRaw('id_bitacora, COUNT(*) as total')
            ->groupBy('id_bitacora')->pluck('total', 'id_bitacora')->toArray();

        $visualizacionCounts = BitacoraRHRegistro::selectRaw('id_bitacora, COUNT(*) as total')
            ->groupBy('id_bitacora')->pluck('total', 'id_bitacora')->toArray();

        $rows = [];
        foreach ($records as $r) {
            $fechaHora = $r->fecha_hora;
            $diffDias = 0;
            if ($fechaHora) {
                $diffDias = (int)\Carbon\Carbon::parse($fechaHora)->diffInDays(\Carbon\Carbon::now());
            }
            $estatus = (int)$r->estatus;

            $fila = self::buildRow($r, $comentarioCounts, $visualizacionCounts, $diffDias, $estatus);
            $rows[] = $fila;
        }

        return $rows;
    }

    private static function buildRow($r, array $comentarioCounts, array $visualizacionCounts, int $diffDias, int $estatus): array
    {
        $usuario = Usuario::find($r->id_usuario);
        $nombre = $usuario ? $usuario->nombre : 'Desconocido';

        return [
            'id'                => (int)$r->id,
            'id_usuario'        => (int)$r->id_usuario,
            'nombre_solicitante'=> $nombre,
            'id_estacion'       => (int)$r->id_estacion,
            'estacion'          => self::getNombreLocalidad((int)$r->id_estacion),
            'fecha_hora'        => $r->fecha_hora,
            'fecha'             => $r->fecha_hora ? formatearFecha($r->fecha_hora) . ', ' . date('g:i a', strtotime($r->fecha_hora)) : '---',
            'descripcion'       => $r->descripcion ?? '',
            'estatus'           => $estatus,
            'diff_dias'         => $diffDias,
            'total_comentarios' => (int)($comentarioCounts[(int)$r->id] ?? 0),
            'total_visualizaciones' => (int)($visualizacionCounts[(int)$r->id] ?? 0),
        ];
    }

    public static function getDetalle(int $id): ?array
    {
        $r = BitacoraRH::find($id);
        if (!$r) return null;

        $usuario = Usuario::find($r->id_usuario);

        return [
            'id'                => (int)$r->id,
            'id_usuario'        => (int)$r->id_usuario,
            'nombre_solicitante'=> $usuario ? $usuario->nombre : 'Desconocido',
            'id_estacion'       => (int)$r->id_estacion,
            'estacion'          => self::getNombreLocalidad((int)$r->id_estacion),
            'estacion_nombre'   => self::getNombreLocalidad((int)$r->id_estacion),
            'fecha_hora'        => $r->fecha_hora,
            'fecha'             => $r->fecha_hora ? formatearFecha($r->fecha_hora) . ', ' . date('g:i a', strtotime($r->fecha_hora)) : '---',
            'descripcion'       => $r->descripcion ?? '',
            'year'              => (int)$r->year,
            'mes'               => (int)$r->mes,
            'estatus'           => (int)$r->estatus,
        ];
    }

    public static function crear(int $idEstacion, int $idYear, int $idMes, string $descripcion): int
    {
        $permisos = self::getPermisos();

        if (!self::puedeVerLocalidad($idEstacion)) {
            return 0;
        }

        $bitacora = BitacoraRH::create([
            'id_usuario'  => $permisos['id_usuario'],
            'id_estacion' => $idEstacion,
            'fecha_hora'  => \Carbon\Carbon::now()->format('Y-m-d H:i:s'),
            'descripcion' => $descripcion,
            'year'        => $idYear,
            'mes'         => $idMes,
            'estatus'     => 0,
        ]);

        self::notificarCrear((int)$bitacora->id, $permisos['id_usuario']);

        return (int)$bitacora->id;
    }

    public static function finalizar(int $id): bool
    {
        $permisos = self::getPermisos();
        if (!$permisos['puedeFinalizar']) {
            return false;
        }

        $r = BitacoraRH::find($id);
        if (!$r || (int)$r->estatus === 1) {
            return false;
        }

        $r->update(['estatus' => 1]);

        self::notificarFinalizar($id, $permisos['id_usuario']);

        return true;
    }

    public static function eliminar(int $id): bool
    {
        BitacoraRHComentario::where('id_bitacora', $id)->delete();
        BitacoraRHDocumento::where('id_bitacora', $id)->delete();
        BitacoraRHRegistro::where('id_bitacora', $id)->delete();

        return (bool)BitacoraRH::where('id', $id)->delete();
    }

    // ---------------- COMENTARIOS ----------------

    public static function getComentarios(int $idBitacora): array
    {
        $records = BitacoraRHComentario::where('id_bitacora', $idBitacora)
            ->orderBy('id', 'desc')
            ->get();

        $usuario = Session::get('usuario');
        $idUsuarioActual = (int)($usuario['id'] ?? 0);

        $data = [];
        foreach ($records as $r) {
            $user = Usuario::find($r->id_usuario);
            $data[] = [
                'id'             => (int)$r->id,
                'usuario_nombre' => $user ? $user->nombre : 'Desconocido',
                'comentario'     => $r->comentario,
                'fecha_hora'     => $r->fecha_hora
                    ? formatearFecha($r->fecha_hora) . ', ' . date('g:i a', strtotime($r->fecha_hora))
                    : '-',
                'esPropio'       => (int)$r->id_usuario === $idUsuarioActual,
            ];
        }

        return $data;
    }

    public static function addComentario(int $idBitacora, string $comentario, int $idUsuario): bool
    {
        BitacoraRHComentario::create([
            'id_bitacora' => $idBitacora,
            'id_usuario'  => $idUsuario,
            'fecha_hora'  => \Carbon\Carbon::now()->format('Y-m-d H:i:s'),
            'comentario'  => $comentario,
        ]);

        self::notificarComentario($idBitacora, $idUsuario);

        return true;
    }

    // ---------------- DOCUMENTOS ----------------

    public static function getDocumentos(int $idBitacora): array
    {
        $records = BitacoraRHDocumento::where('id_bitacora', $idBitacora)
            ->orderBy('id', 'asc')
            ->get();

        $data = [];
        foreach ($records as $r) {
            $user = Usuario::find($r->id_usuario);
            $data[] = [
                'id'          => (int)$r->id,
                'nombre'      => $r->nombre ?? '-',
                'archivo'     => $r->archivo,
                'subido_por'  => $user ? $user->nombre : 'Desconocido',
            ];
        }

        return $data;
    }

    public static function addDocumento(int $idBitacora, string $nombre, array $file): bool
    {
        if (empty($file['tmp_name']) || ($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
            return false;
        }

        $aleatorio = uniqid();
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $archivo = 'Bitacora-' . $aleatorio . '.' . $ext;

        if (!is_dir(self::UPLOAD_DIR)) {
            mkdir(self::UPLOAD_DIR, 0775, true);
        }

        if (!move_uploaded_file($file['tmp_name'], self::UPLOAD_DIR . $archivo)) {
            return false;
        }

        $permisos = self::getPermisos();

        $doc = BitacoraRHDocumento::create([
            'id_bitacora' => $idBitacora,
            'id_usuario'  => $permisos['id_usuario'],
            'nombre'      => $nombre,
            'archivo'     => $archivo,
        ]);

        self::notificarDocumento($idBitacora, $nombre, $permisos['id_usuario']);

        return (bool)$doc->id;
    }

    public static function eliminarDocumento(int $idDocumento): bool
    {
        $permisos = self::getPermisos();
        if (!$permisos['puedeEliminarDoc']) {
            return false;
        }

        $doc = BitacoraRHDocumento::find($idDocumento);
        if (!$doc) return false;

        $ruta = self::UPLOAD_DIR . $doc->archivo;
        if (is_file($ruta)) {
            @unlink($ruta);
        }

        return (bool)$doc->delete();
    }

    // ---------------- VISUALIZACIONES (registro) ----------------

    public static function getRegistros(int $idBitacora): array
    {
        $records = BitacoraRHRegistro::where('id_bitacora', $idBitacora)
            ->orderBy('id', 'asc')
            ->get();

        $data = [];
        foreach ($records as $r) {
            $user = Usuario::find($r->id_usuario);
            $data[] = [
                'id'             => (int)$r->id,
                'usuario_nombre' => $user ? $user->nombre : 'Desconocido',
                'fecha_hora'     => $r->fecha_hora
                    ? formatearFecha($r->fecha_hora) . ', ' . date('g:i a', strtotime($r->fecha_hora))
                    : '-',
            ];
        }

        return $data;
    }

    public static function registrarVisualizacion(int $idBitacora, int $idUsuario): bool
    {
        BitacoraRHRegistro::create([
            'id_bitacora' => $idBitacora,
            'id_usuario'  => $idUsuario,
            'fecha_hora'  => \Carbon\Carbon::now()->format('Y-m-d H:i:s'),
        ]);

        return true;
    }

    // ---------------- PENDIENTES (ModuleStation selector) ----------------

    public static function getPendingCountsFlat(int $idYear = 0, int $idMes = 0): array
    {
        $idYear = $idYear ?: (int)date('Y');
        $idMes = $idMes ?: (int)date('n');

        $estacionIds = [];
        $pendientes = ['total' => 0];

        foreach (ModuleStationService::getAvailableStations(self::MODULE_KEY) as $s) {
            $id = (int)$s['id'];
            $estacionIds[] = $id;
            $pendientes['estacion_' . $id] = 0;
        }

        $deptoIds = [];
        foreach (ModuleStationService::getAvailableDepartments(self::MODULE_KEY) as $d) {
            $id = (int)$d['id'];
            $deptoIds[] = $id;
            $pendientes['depto_' . $id] = 0;
        }

        $allowed = array_values(array_unique(array_merge($estacionIds, $deptoIds)));
        if (empty($allowed)) {
            $allowed = self::getPuestoLocalidadIds();
        }

        $counts = BitacoraRH::query()
            ->where('estatus', 0)
            ->where('year', $idYear)
            ->where('mes', $idMes);

        if (!empty($allowed)) {
            $counts->whereIn('id_estacion', $allowed);
        }

        $counts->groupBy('id_estacion')->selectRaw('id_estacion, COUNT(*) as total');

        foreach ($counts->get() as $row) {
            $id = (int)$row->id_estacion;
            $key = in_array($id, $deptoIds, true) ? 'depto_' . $id : 'estacion_' . $id;
            $pendientes[$key] = (int)$row->total;
            $pendientes['total'] += (int)$row->total;
        }

        return $pendientes;
    }

    public static function getPendingCountsContext(int $idYear, int $idMes): int
    {
        $ctx = ModuleStationService::getContext(self::MODULE_KEY);
        $idEstacion = $ctx['id_estacion'];
        $idDepto = $ctx['id_depto'];

        $q = BitacoraRH::query()->where('estatus', 0)->where('year', $idYear)->where('mes', $idMes);

        if ($idDepto && !$idEstacion) {
            $q->where('id_estacion', $idDepto);
        } elseif ($idEstacion) {
            $q->where('id_estacion', $idEstacion);
        } else {
            $allowed = self::getAllowedLocalidadIds();
            if (!empty($allowed)) {
                $q->whereIn('id_estacion', $allowed);
            }
        }

        return (int)$q->count();
    }

    // ---------------- TELEGRAM ----------------

    private static function getDatosNotificacion(int $idBitacora): ?array
    {
        $r = BitacoraRH::find($idBitacora);
        if (!$r) return null;

        return [
'id_estacion'       => (int)$r->id_estacion,
            'estacion'          => self::getNombreLocalidad((int)$r->id_estacion),
            'estacion_nombre'   => self::getNombreLocalidad((int)$r->id_estacion),
            'descripcion' => $r->descripcion ?? '',
            'fecha'       => $r->fecha_hora ? formatearFecha($r->fecha_hora) : '',
            'year'        => (int)$r->year,
            'mes'         => (int)$r->mes,
            'estatus'     => (int)$r->estatus,
        ];
    }

    private static function lineaEstacion(array $datos): string
    {
        $idEstacion = $datos['id_estacion'];
        // 11 = Dirección de Operaciones (departamento)
        if ($idEstacion === 11) {
            return '🏢 Departamento: ' . $datos['estacion'] . '.';
        }
        return '⛽ Estación: ' . $datos['estacion'] . '.';
    }

    private static function notificarCrear(int $idBitacora, int $idUsuario): void
    {
        $datos = self::getDatosNotificacion($idBitacora);
        if (!$datos) return;

        $usuario = Usuario::find($idUsuario);
        $nombreUsuario = $usuario ? $usuario->nombre : 'Usuario';

        $mensaje = '✅ ' . $nombreUsuario . ' creó un nuevo registro en la Bitácora RRHH correspondiente al mes de ' .
            self::getNombreMes($datos['mes']) . ' del ' . $datos['year'] . '.' . PHP_EOL .
            '🗓️ Fecha: ' . $datos['fecha'] . PHP_EOL .
            'ℹ Descripción: ' . $datos['descripcion'] . PHP_EOL . PHP_EOL .
            self::lineaEstacion($datos);

        self::enviarTelegram($datos['id_estacion'], $idUsuario, $mensaje);
    }

    private static function notificarComentario(int $idBitacora, int $idUsuario): void
    {
        $datos = self::getDatosNotificacion($idBitacora);
        if (!$datos) return;

        $usuario = Usuario::find($idUsuario);
        $nombreUsuario = $usuario ? $usuario->nombre : 'Usuario';

        $mensaje = '💭 ' . $nombreUsuario . ' agregó un nuevo comentario en el registro de Bitácora RRHH correspondiente al mes de ' .
            self::getNombreMes($datos['mes']) . ' del ' . $datos['year'] . '.' . PHP_EOL .
            '🗓️ Fecha: ' . $datos['fecha'] . PHP_EOL .
            'ℹ Descripción: ' . $datos['descripcion'] . PHP_EOL . PHP_EOL .
            self::lineaEstacion($datos);

        self::enviarTelegram($datos['id_estacion'], $idUsuario, $mensaje);
    }

    private static function notificarDocumento(int $idBitacora, string $nombreDocumento, int $idUsuario): void
    {
        $datos = self::getDatosNotificacion($idBitacora);
        if (!$datos) return;

        $usuario = Usuario::find($idUsuario);
        $nombreUsuario = $usuario ? $usuario->nombre : 'Usuario';

        $mensaje = '✅ ' . $nombreUsuario . ' agregó un nuevo documento en el registro de Bitácora RRHH correspondiente al mes de ' .
            self::getNombreMes($datos['mes']) . ' del ' . $datos['year'] . '.' . PHP_EOL .
            '📄 Nombre del documento: ' . $nombreDocumento . PHP_EOL .
            '🗓️ Fecha: ' . $datos['fecha'] . PHP_EOL .
            'ℹ Descripción: ' . $datos['descripcion'] . PHP_EOL . PHP_EOL .
            self::lineaEstacion($datos);

        self::enviarTelegram($datos['id_estacion'], $idUsuario, $mensaje);
    }

    private static function notificarFinalizar(int $idBitacora, int $idUsuario): void
    {
        $datos = self::getDatosNotificacion($idBitacora);
        if (!$datos) return;

        $usuario = Usuario::find($idUsuario);
        $nombreUsuario = $usuario ? $usuario->nombre : 'Usuario';

        $mensaje = '✅🔚 ' . $nombreUsuario . ' finalizó el registro de Bitácora RRHH correspondiente al mes de ' .
            self::getNombreMes($datos['mes']) . ' del ' . $datos['year'] . '.' . PHP_EOL .
            '🗓️ Fecha: ' . $datos['fecha'] . PHP_EOL .
            'ℹ Descripción: ' . $datos['descripcion'] . PHP_EOL . PHP_EOL .
            self::lineaEstacion($datos);

        self::enviarTelegram($datos['id_estacion'], $idUsuario, $mensaje);
    }

    private static function enviarTelegram(int $idEstacion, int $idUsuario, string $mensaje): void
    {
        try {
            $telegram = new TelegramService();
            $userIds = $telegram->getUserIdsByStation($idEstacion, $idUsuario);

            if (in_array($idEstacion, [6, 7])) {
                $extraIds = $telegram->getUserIdsComercializadora($idUsuario);
                $userIds = array_values(array_unique(array_merge($userIds, $extraIds)));
            } elseif ($idEstacion === 11) {
                $extraIds = $telegram->getUserIdsDeptoOperativo($idUsuario);
                $userIds = array_values(array_unique(array_merge($userIds, $extraIds)));
            }

            foreach ($userIds as $uid) {
                $telegram->sendTokenAsync($uid, $mensaje);
            }
        } catch (\Throwable $e) {
            error_log('Error en Telegram BitacoraRrhh: ' . $e->getMessage());
        }
    }
}
