<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Usuario;
use App\Models\Operativo\RhLocalidad;
use App\Models\Operativo\RhPersonal;
use App\Models\Operativo\RhPersonalListaNegra;
use App\Models\Operativo\RhListaNegraComentario;
use App\Models\Operativo\RhListaNegraArchivo;
use App\Services\ModuloDptoOperativoService;
use App\Services\TelegramService;

class ListaNegraService
{
    public const MODULE_KEY = 'lista-negra';

    private const UPLOAD_DIR = __DIR__ . '/../../public/uploads/archivos/lista-negra/';

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
        ];
    }

    public static function getPersonalDisponible(int $idEstacion): array
    {
        $rows = RhPersonal::where('estado', 1)
            ->where('id_estacion', $idEstacion)
            ->orderBy('nombre_completo', 'asc')
            ->get(['id', 'nombre_completo'])
            ->toArray();

        return array_map(static function (array $p): array {
            return [
                'id'     => (int)$p['id'],
                'nombre' => $p['nombre_completo'],
            ];
        }, $rows);
    }

    public static function getList($fechaInicio = null, $fechaFin = null): array
    {
        $fechaInicio = trim((string)($fechaInicio ?? ''));
        $fechaFin = trim((string)($fechaFin ?? ''));

        $query = RhPersonalListaNegra::join(
            'op_rh_personal',
            'op_rh_personal_lista_negra.id_personal',
            '=',
            'op_rh_personal.id'
        )
            ->join('op_rh_puestos', 'op_rh_personal.puesto', '=', 'op_rh_puestos.id')
            ->select(
                'op_rh_personal_lista_negra.id',
                'op_rh_personal_lista_negra.fecha',
                'op_rh_personal_lista_negra.motivo',
                'op_rh_personal_lista_negra.detalle',
                'op_rh_personal_lista_negra.id_personal',
                'op_rh_personal.id_estacion',
                'op_rh_personal.nombre_completo',
                'op_rh_puestos.puesto'
            );

        if ($fechaInicio !== '' && $fechaFin !== '') {
            $query->whereBetween('op_rh_personal_lista_negra.fecha', [$fechaInicio, $fechaFin]);
        }

        $query->orderBy('op_rh_personal.id_estacion', 'asc');

        $records = $query->get();

        $localidades = RhLocalidad::pluck('localidad', 'id')->toArray();

        $counts = RhListaNegraComentario::selectRaw('id_lista_negra, COUNT(*) as total')
            ->groupBy('id_lista_negra')
            ->pluck('total', 'id_lista_negra')
            ->toArray();

        $rows = [];
        foreach ($records as $r) {
            $rows[] = [
                'id'              => (int)$r->id,
                'id_personal'     => (int)$r->id_personal,
                'id_estacion'     => (int)$r->id_estacion,
                'nombre_completo' => $r->nombre_completo ?? '',
                'puesto'          => $r->puesto ?? '',
                'fecha'           => $r->fecha ? formatearFecha($r->fecha) : '---',
                'fecha_raw'       => $r->fecha,
                'estacion'        => $localidades[(int)$r->id_estacion] ?? 'S/I',
                'motivo'          => $r->motivo ?? '',
                'detalle'         => $r->detalle ?? '',
                'total_comentarios' => (int)($counts[(int)$r->id] ?? 0),
            ];
        }

        return $rows;
    }

    public static function agregar(int $idPersonal, string $motivo, string $detalle): int
    {
        $record = RhPersonalListaNegra::create([
            'id_personal' => $idPersonal,
            'fecha'       => date('Y-m-d'),
            'motivo'      => $motivo,
            'detalle'     => $detalle,
        ]);

        RhPersonal::where('id', $idPersonal)->update(['estado' => 0]);

        return (int)$record->id;
    }

    public static function eliminar(int $idListaNegra): bool
    {
        RhListaNegraComentario::where('id_lista_negra', $idListaNegra)->delete();
        RhListaNegraArchivo::where('id_lista_negra', $idListaNegra)->delete();

        return (bool)RhPersonalListaNegra::where('id', $idListaNegra)->delete();
    }

    public static function getComentarios(int $idListaNegra): array
    {
        $records = RhListaNegraComentario::where('id_lista_negra', $idListaNegra)
            ->orderBy('id', 'desc')
            ->get();

        $usuario = Session::get('usuario');
        $idUsuarioActual = (int)($usuario['id'] ?? 0);

        $data = [];
        foreach ($records as $r) {
            $user = Usuario::find($r->id_usuario);
            $data[] = [
                'id'            => (int)$r->id,
                'usuario_nombre' => $user ? $user->nombre : 'Desconocido',
                'comentario'    => $r->comentario,
                'fecha_hora'    => $r->fecha_hora
                    ? formatearFecha($r->fecha_hora) . ', ' . date('g:i a', strtotime($r->fecha_hora))
                    : 'Sin información',
                'esPropio'      => (int)$r->id_usuario === $idUsuarioActual,
            ];
        }

        return $data;
    }

    public static function addComentario(int $idListaNegra, string $comentario, int $idUsuario): bool
    {
        RhListaNegraComentario::create([
            'id_lista_negra' => $idListaNegra,
            'id_usuario'     => $idUsuario,
            'comentario'     => $comentario,
        ]);

        return true;
    }

    public static function getArchivos(int $idListaNegra): array
    {
        $records = RhListaNegraArchivo::where('id_lista_negra', $idListaNegra)
            ->orderBy('id', 'desc')
            ->get();

        $data = [];
        foreach ($records as $r) {
            $data[] = [
                'id'          => (int)$r->id,
                'descripcion' => $r->descripcion ?? '-',
                'archivo'     => $r->archivo,
            ];
        }

        return $data;
    }

    public static function addArchivo(int $idListaNegra, string $descripcion, array $file): bool
    {
        if (empty($file['tmp_name']) || ($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
            return false;
        }

        $aleatorio1 = rand(1, 1000000);
        $aleatorio2 = rand(1000, 9999);
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $nombreLimpio = preg_replace('/[^A-Za-z0-9._-]/', '-', $descripcion) ?: 'documento';
        $archivo = $aleatorio1 . '-' . $nombreLimpio . '-' . $aleatorio2 . '.' . $ext;

        if (!is_dir(self::UPLOAD_DIR)) {
            mkdir(self::UPLOAD_DIR, 0775, true);
        }

        if (!move_uploaded_file($file['tmp_name'], self::UPLOAD_DIR . $archivo)) {
            return false;
        }

        RhListaNegraArchivo::create([
            'id_lista_negra' => $idListaNegra,
            'descripcion'    => $descripcion,
            'archivo'        => $archivo,
        ]);

        return true;
    }

    public static function deleteArchivo(int $idArchivo): bool
    {
        $archivo = RhListaNegraArchivo::find($idArchivo);
        if (!$archivo) return false;

        $ruta = self::UPLOAD_DIR . $archivo->archivo;
        if (is_file($ruta)) {
            @unlink($ruta);
        }

        return (bool)$archivo->delete();
    }

    public static function notificarCreacion(int $idListaNegra, int $idUsuario): void
    {
        $datos = self::getDatosLista($idListaNegra);
        if (!$datos) return;

        $usuario = Usuario::find($idUsuario);
        $nombreUsuario = $usuario ? $usuario->nombre : 'Usuario';

        $mensaje = '✅ ' . $nombreUsuario . ' agregó información en la Lista Negra, correspondiente al apartado de Recursos Humanos.' . PHP_EOL .
            '👤 Nombre: ' . $datos['nombre_completo'] . PHP_EOL .
            '💼 Puesto: ' . $datos['puesto'] . PHP_EOL .
            '🚫 Motivo: ' . $datos['motivo'] . PHP_EOL . PHP_EOL .
            $datos['linea_estacion'];

        self::enviarTelegram($datos['id_estacion'], $idUsuario, $mensaje);
    }

    public static function notificarComentario(int $idListaNegra, int $idUsuario): void
    {
        $datos = self::getDatosLista($idListaNegra);
        if (!$datos) return;

        $usuario = Usuario::find($idUsuario);
        $nombreUsuario = $usuario ? $usuario->nombre : 'Usuario';

        $mensaje = '💭 ' . $nombreUsuario . ' agregó un nuevo comentario en la Lista Negra correspondiente al apartado de Recursos Humanos.' . PHP_EOL .
            '👤 Nombre del Personal: ' . $datos['nombre_completo'] . PHP_EOL .
            '💼 Puesto: ' . $datos['puesto'] . PHP_EOL . PHP_EOL .
            $datos['linea_estacion'];

        self::enviarTelegram($datos['id_estacion'], $idUsuario, $mensaje);
    }

    public static function notificarEliminacion(array $datos, int $idUsuario): void
    {
        $usuario = Usuario::find($idUsuario);
        $nombreUsuario = $usuario ? $usuario->nombre : 'Usuario';

        $mensaje = '🗑 ' . $nombreUsuario . ' eliminó información de la Lista Negra, correspondiente al apartado de Recursos Humanos.' . PHP_EOL .
            '👤 Nombre: ' . $datos['nombre_completo'] . PHP_EOL .
            '💼 Puesto: ' . $datos['puesto'] . PHP_EOL . PHP_EOL .
            $datos['linea_estacion'];

        self::enviarTelegram($datos['id_estacion'], $idUsuario, $mensaje);
    }

    public static function getDatosLista(int $idListaNegra): ?array
    {
        $row = RhPersonalListaNegra::join(
            'op_rh_personal',
            'op_rh_personal_lista_negra.id_personal',
            '=',
            'op_rh_personal.id'
        )
            ->join('op_rh_puestos', 'op_rh_personal.puesto', '=', 'op_rh_puestos.id')
            ->where('op_rh_personal_lista_negra.id', $idListaNegra)
            ->select(
                'op_rh_personal_lista_negra.motivo',
                'op_rh_personal.id_estacion',
                'op_rh_personal.nombre_completo',
                'op_rh_puestos.puesto'
            )
            ->first();

        if (!$row) return null;

        $localidad = RhLocalidad::find($row->id_estacion);
        $nombreEstacion = $localidad ? $localidad->localidad : 'S/I';
        $numlista = $localidad ? (int)$localidad->numlista : 99;

        return [
            'id_estacion'    => (int)$row->id_estacion,
            'nombre_completo' => $row->nombre_completo ?? '',
            'puesto'         => $row->puesto ?? '',
            'motivo'         => $row->motivo ?? '',
            'linea_estacion' => $numlista <= 8
                ? '⛽ Estación: ' . $nombreEstacion . '.'
                : '🏢 Departamento: ' . $nombreEstacion . '.',
        ];
    }

    private static function enviarTelegram(int $idEstacion, int $idUsuario, string $mensaje): void
    {
        try {
            TelegramService::notificar($idEstacion, $idUsuario, $mensaje);
        } catch (\Throwable $e) {
            error_log('Error en notificar ListaNegra: ' . $e->getMessage());
        }
    }

    public static function buildPdfHtml($fechaInicio = null, $fechaFin = null): string
    {
        $fechaInicio = trim((string)($fechaInicio ?? ''));
        $fechaFin = trim((string)($fechaFin ?? ''));

        if ($fechaInicio !== '' && $fechaFin !== '') {
            $textoDetalle = 'del: ' . formatearFecha($fechaInicio) . ' al: ' . formatearFecha($fechaFin);
        } else {
            $textoDetalle = 'General';
        }

        $rows = self::getList($fechaInicio, $fechaFin);

        $html = '<div class="content-wrapper">';
        $html .= '<h2>Listado de lista negra <br>' . $textoDetalle . '</h2>';
        $html .= '<table class="custom-table">';
        $html .= '<thead class="tables-bg"><tr>';
        $html .= '<th class="text-center">#</th>';
        $html .= '<th class="text-start">Nombre completo</th>';
        $html .= '<th class="text-center">Puesto</th>';
        $html .= '<th class="text-center">Fecha de baja</th>';
        $html .= '<th class="text-center">Estación</th>';
        $html .= '<th class="text-center">Motivo</th>';
        $html .= '<th class="text-center">Descripción</th>';
        $html .= '</tr></thead><tbody class="bg-light">';

        if (empty($rows)) {
            $html .= '<tr><td colspan="7" class="text-center">No se encontró información</td></tr>';
        } else {
            $num = 1;
            foreach ($rows as $row) {
                $html .= '<tr>';
                $html .= '<td class="text-center">' . $num . '</td>';
                $html .= '<td class="text-start">' . htmlspecialchars($row['nombre_completo'], ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td class="text-center">' . htmlspecialchars($row['puesto'], ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td class="text-center">' . ($row['fecha_raw'] ? formatearFecha($row['fecha_raw']) : '---') . '</td>';
                $html .= '<td class="text-center">' . htmlspecialchars($row['estacion'], ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td class="text-center">' . htmlspecialchars($row['motivo'], ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td class="text-center">' . htmlspecialchars($row['detalle'], ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '</tr>';
                $num++;
            }
        }

        $html .= '</tbody></table></div>';

        return $html;
    }

    public static function getPdfStyles(): string
    {
        return '
            body, html { margin: 0; padding: 0; font-family: Arial, sans-serif; }
            .content-wrapper { padding: 40px; }
            h2 { color: #215D98; }
            .custom-table { width: 100%; border-collapse: collapse; font-size: 10.5px; }
            .custom-table thead th, .custom-table tbody td { padding: 8px; border: 1px solid #dee2e6; text-align: left; }
            .tables-bg { background: #215D98; color: white; }
            .bg-light { background-color: #f8f9fa; }
        ';
    }
}