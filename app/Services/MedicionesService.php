<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Operativo\Medicion;
use App\Models\Operativo\RhLocalidad;
use App\Models\Usuario;
use App\Services\ModuloDptoOperativoService;
use App\Services\ModuleStationService;
use App\Services\TelegramService;

class MedicionesService
{
    public const MODULE_KEY = 'mediciones';

    public const PROVEEDORES = ['Pemex', 'Delivery', 'Pick Up'];

    public static function getPermisos(): array
    {
        $usuario = Auth::user();
        $sessionUsuario = Session::get('usuario');
        $idUsuario = (int)($sessionUsuario['id'] ?? 0);
        $idEstacion = (int)($sessionUsuario['id_estacion'] ?? 0);
        $multiestacion = !empty($sessionUsuario['multiestacion']);
        $nombrePuesto = $usuario->puesto->tipo_puesto ?? '';

        $allPerms = ModuloDptoOperativoService::getPermisos($idUsuario, 'importacion');
        $permisosDb = $allPerms['importacion'] ?? [];

        $tieneSubmenu = false;
        foreach ($permisosDb['submenus'] ?? [] as $sm) {
            if (($sm['clave'] ?? '') === self::MODULE_KEY) {
                $tieneSubmenu = true;
                break;
            }
        }

        $leer = !empty($permisosDb['leer']);
        $crear = !empty($permisosDb['crear']);
        $eliminar = !empty($permisosDb['eliminar']);

        return [
            'id_usuario'    => $idUsuario,
            'id_estacion'   => $idEstacion,
            'nombre_puesto' => $nombrePuesto,
            'multiestacion' => $multiestacion,
            'puedeVer'      => $tieneSubmenu && $leer,
            'puedeCrear'    => $tieneSubmenu && $crear && !$multiestacion,
            'puedeEliminar' => $tieneSubmenu && $eliminar && !$multiestacion,
        ];
    }

    public static function getEstacionesPermitidas(): array
    {
        $stations = ModuleStationService::getAvailableStations(self::MODULE_KEY);
        return array_values(array_column($stations, 'id'));
    }

    public static function puedeEstacion(int $idEstacion): bool
    {
        return in_array($idEstacion, self::getEstacionesPermitidas(), true);
    }

    public static function getData(): array
    {
        $ctx = ModuleStationService::getContext(self::MODULE_KEY);
        $idEstacion = $ctx['id_estacion'] !== null ? (int)$ctx['id_estacion'] : null;

        $query = Medicion::query();

        if ($idEstacion !== null) {
            if (!self::puedeEstacion($idEstacion)) {
                return [];
            }
            $query->where('id_estacion', $idEstacion);
        } else {
            $allowed = self::getEstacionesPermitidas();
            if (empty($allowed)) {
                return [];
            }
            $query->whereIn('id_estacion', $allowed);
        }

        $records = $query->orderByDesc('id')->get();
        $localidades = RhLocalidad::pluck('localidad', 'id')->toArray();

        $rows = [];
        foreach ($records as $r) {
            $rows[] = [
                'id'            => (int)$r->id,
                'id_estacion'   => (int)$r->id_estacion,
                'fecha'         => $r->fecha ? formatearFecha($r->fecha) : '---',
                'fecha_raw'     => (string)($r->fecha ? $r->fecha->format('Y-m-d') : ''),
                'factura'       => (string)$r->factura,
                'neto'          => number_format((float)$r->neto, 2, '.', ','),
                'bruto'         => number_format((float)$r->bruto, 2, '.', ','),
                'cuenta_litros' => number_format((float)$r->cuenta_litros, 2, '.', ','),
                'proveedor'     => (string)$r->proveedor,
                'estacion'      => $localidades[(int)$r->id_estacion] ?? 'S/I',
            ];
        }

        return $rows;
    }

    public static function crear(
        int $idEstacion,
        string $fecha,
        string $factura,
        float $neto,
        float $bruto,
        float $cuentaLitros,
        string $proveedor
    ): int {
        $record = Medicion::create([
            'id_estacion'  => $idEstacion,
            'fecha'        => $fecha,
            'factura'      => $factura,
            'neto'         => $neto,
            'bruto'        => $bruto,
            'cuenta_litros' => $cuentaLitros,
            'proveedor'    => $proveedor,
        ]);

        return (int)$record->id;
    }

    public static function eliminar(int $idMedicion): bool
    {
        return (bool)Medicion::where('id', $idMedicion)->delete();
    }

    public static function getDatosMedicion(int $idMedicion): ?array
    {
        $row = Medicion::find($idMedicion);

        if (!$row) {
            return null;
        }

        $localidad = RhLocalidad::find($row->id_estacion);

        return [
            'id'            => (int)$row->id,
            'id_estacion'   => (int)$row->id_estacion,
            'estacion'      => $localidad ? $localidad->localidad : 'S/I',
            'fecha'         => $row->fecha ? formatearFecha($row->fecha) : '---',
            'fecha_raw'     => (string)($row->fecha ? $row->fecha->format('Y-m-d') : ''),
            'factura'       => (string)$row->factura,
            'proveedor'     => (string)$row->proveedor,
        ];
    }

    public static function notificarCreacion(int $idMedicion, int $idUsuario): void
    {
        $datos = self::getDatosMedicion($idMedicion);
        if (!$datos) {
            return;
        }  

        $usuario = Usuario::find($idUsuario);
        $nombreUsuario = $usuario ? $usuario->nombre : 'Usuario';

$mensaje = '📄 Se ha agregado un nuevo registro en el apartado de Mediciones del módulo de Importación:' . PHP_EOL . PHP_EOL .
'#️⃣ Folio: ' . $datos['id'] . PHP_EOL .
'🧾 Factura: ' . $datos['factura'] . PHP_EOL .
'👤 Proveedor: ' . $datos['proveedor'] . PHP_EOL .
'🗓 Fecha: ' . $datos['fecha'] . PHP_EOL . PHP_EOL .
'👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL .
 '⛽ <b>Estación:</b> ' . $datos['estacion'] . '.';

        self::enviarTelegram($datos['id_estacion'], $idUsuario, $mensaje, true);
    }

    public static function notificarEliminacion(int $idMedicion, int $idUsuario): void
    {
        $datos = self::getDatosMedicion($idMedicion);
        if (!$datos) {
            return;
        }

        $usuario = Usuario::find($idUsuario);
        $nombreUsuario = $usuario ? $usuario->nombre : 'Usuario';


$mensaje = '🗑 Se ha eliminado un registro en el apartado de Mediciones del módulo de Importación:' . PHP_EOL . PHP_EOL .
    '#️⃣ Folio: ' . $datos['id'] . PHP_EOL .
    '🧾 Factura: ' . $datos['factura'] . PHP_EOL .
    '👤 Proveedor: ' . $datos['proveedor'] . PHP_EOL .
    '🗓 Fecha: ' . $datos['fecha'] . PHP_EOL . PHP_EOL .
    '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL .
    '⛽ <b>Estación:</b> ' . $datos['estacion'] . '.';

        self::enviarTelegram($datos['id_estacion'], $idUsuario, $mensaje, false);
    }

    private static function enviarTelegram(int $idEstacion, int $idUsuario, string $mensaje, bool $conDeptoOperativo): void
    {
        try {
            $telegram = new TelegramService();
            $userIds = $telegram->getUserIdsByStationWithComodines($idEstacion, $idUsuario);

            if ($conDeptoOperativo) {
                $extraIds = $telegram->getUserIdsDeptoOperativo($idUsuario);
                $userIds = array_values(array_unique(array_merge($userIds, $extraIds)));
            }

            foreach ($userIds as $uid) {
                $telegram->sendTokenAsync($uid, $mensaje);
            }
        } catch (\Throwable $e) {
            error_log('Error en Telegram Mediciones: ' . $e->getMessage());
        }
    }
}