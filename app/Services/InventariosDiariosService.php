<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Operativo\InventariosDiario;
use App\Models\Operativo\InventariosDiariosDetalle;
use Illuminate\Database\Capsule\Manager as Capsule;

class InventariosDiariosService
{
    public const MODULE_KEY = 'inventarios-diarios';

    public const DETALLE_REALES = 'INVENTARIOS REALES';
    public const DETALLE_CAPACIDAD = 'CAPACIDAD ALMACENAJE';

    public const COLOR_OCT87 = '#76bd1d';
    public const COLOR_OCT91 = '#e21683';
    public const COLOR_DIESEL = '#5e0f8e';
    public const COLOR_ALERTA = '#FFC300';

    public const SUCURSALES_PRESET = [
        ['sucursal' => 'Palo solo', 'destino' => 19],
        ['sucursal' => 'San Agustin', 'destino' => 20],
        ['sucursal' => 'Interlomas', 'destino' => 21],
        ['sucursal' => 'Lago', 'destino' => 22],
        ['sucursal' => 'Gasomira', 'destino' => 23],
        ['sucursal' => 'Esmegas', 'destino' => 24],
        ['sucursal' => 'Xochimilco', 'destino' => 38],
        ['sucursal' => 'Bosque Real', 'destino' => 0],
    ];

    public static function getPermisos(): array
    {
        $usuario = Auth::user();
        $sessionUsuario = Session::get('usuario');
        $idUsuario = (int)($sessionUsuario['id'] ?? 0);
        $idPuesto = (int)($usuario->id_puesto ?? 0);

        $allPerms = ModuloDptoOperativoService::getPermisos($idUsuario, 'importacion');
        $modulo = $allPerms['importacion'] ?? [];

        $submodulo = null;
        foreach ($modulo['submenus'] ?? [] as $sm) {
            if (($sm['clave'] ?? '') === self::MODULE_KEY) {
                $submodulo = $sm;
                break;
            }
        }

        $accede = $submodulo !== null;

        $leer     = (bool)($modulo['leer'] ?? false);
        $crear    = (bool)($modulo['crear'] ?? false);
        $editar   = (bool)($modulo['editar'] ?? false);
        $eliminar = (bool)($modulo['eliminar'] ?? false);

        return [
            'id_usuario'    => $idUsuario,
            'id_puesto'     => $idPuesto,
            'puedeVer'      => $accede && $leer,
            'puedeCrear'    => $accede && $crear,
            'puedeEditar'   => $accede && $editar,
            'puedeEliminar' => $accede && $eliminar,
        ];
    }

    public static function getListado(int $year, int $mes): array
    {
        $reportes = InventariosDiario::whereRaw('YEAR(fecha) = ? AND MONTH(fecha) = ?', [$year, $mes])
            ->orderByDesc('fecha')
            ->get();

        if ($reportes->isEmpty()) {
            return [];
        }

        $filas = [];
        foreach ($reportes as $reporte) {
            $detalles = InventariosDiariosDetalle::where('id_reporte', (int)$reporte->id)
                ->where('detalle', self::DETALLE_REALES)
                ->orderBy('id', 'asc')
                ->get();

            $sucursales = [];
            foreach ($detalles as $d) {
                $sucursales[] = [
                    'id'       => (int)$d->id,
                    'sucursal' => (string)$d->sucursal,
                    'destino'  => (int)$d->destino,
                    'oct87'    => (int)$d->oct87,
                    'oct91'    => (int)$d->oct91,
                    'diesel'   => (int)$d->diesel,
                ];
            }

            $filas[] = [
                'id'          => (int)$reporte->id,
                'fecha'       => (string)$reporte->fecha,
                'fecha_label' => formatearFechaLarga($reporte->fecha),
                'estatus'     => (int)$reporte->estatus,
                'sucursales'  => $sucursales,
            ];
        }

        return $filas;
    }

    public static function crear(): ?int
    {
        return Capsule::transaction(function () {
            $reporte = InventariosDiario::create([
                'fecha'   => '0000-00-00',
                'estatus' => 0,
            ]);

            $id = (int)$reporte->id;

            foreach (self::SUCURSALES_PRESET as $sucursal) {
                InventariosDiariosDetalle::create([
                    'id_reporte' => $id,
                    'detalle'    => self::DETALLE_REALES,
                    'sucursal'   => $sucursal['sucursal'],
                    'destino'    => (int)$sucursal['destino'],
                    'oct87'      => 0,
                    'oct91'      => 0,
                    'diesel'     => 0,
                ]);
            }

            return $id;
        });
    }

    public static function getReporte(int $id): ?array
    {
        $reporte = InventariosDiario::find($id);
        if (!$reporte) {
            return null;
        }

        $detalles = InventariosDiariosDetalle::where('id_reporte', $id)
            ->where('detalle', self::DETALLE_REALES)
            ->orderBy('id', 'asc')
            ->get();

        $sucursales = [];
        foreach ($detalles as $d) {
            $sucursales[] = [
                'id'       => (int)$d->id,
                'sucursal' => (string)$d->sucursal,
                'destino'  => (int)$d->destino,
                'oct87'    => (int)$d->oct87,
                'oct91'    => (int)$d->oct91,
                'diesel'   => (int)$d->diesel,
            ];
        }

        return [
            'id'         => (int)$reporte->id,
            'fecha'      => (string)$reporte->fecha,
            'estatus'    => (int)$reporte->estatus,
            'sucursales' => $sucursales,
        ];
    }

    public static function updateDetalle(int $id, int $tipo, string $valor): bool
    {
        $detalle = InventariosDiariosDetalle::find($id);
        if (!$detalle) {
            return false;
        }

        $campo = match ($tipo) {
            1 => 'destino',
            2 => 'oct87',
            3 => 'oct91',
            4 => 'diesel',
            default => null,
        };

        if ($campo === null) {
            return false;
        }

        $detalle->{$campo} = self::valorInt($valor);

        return (bool)$detalle->save();
    }

    public static function agregarSucursal(int $idReporte, array $data): bool
    {
        $reporte = InventariosDiario::find($idReporte);
        if (!$reporte) {
            return false;
        }

        $sucursal = trim((string)($data['Sucursal'] ?? ''));

        return Capsule::transaction(function () use ($idReporte, $sucursal, $data) {
            InventariosDiariosDetalle::create([
                'id_reporte' => $idReporte,
                'detalle'    => self::DETALLE_REALES,
                'sucursal'   => $sucursal,
                'destino'    => self::valorInt($data['Destino1'] ?? null),
                'oct87'      => self::valorInt($data['Oct871'] ?? null),
                'oct91'      => self::valorInt($data['Oct911'] ?? null),
                'diesel'     => self::valorInt($data['Diesel1'] ?? null),
            ]);

            InventariosDiariosDetalle::create([
                'id_reporte' => $idReporte,
                'detalle'    => self::DETALLE_CAPACIDAD,
                'sucursal'   => $sucursal,
                'destino'    => self::valorInt($data['Destino2'] ?? null),
                'oct87'      => self::valorInt($data['Oct872'] ?? null),
                'oct91'      => self::valorInt($data['Oct912'] ?? null),
                'diesel'     => self::valorInt($data['Diesel2'] ?? null),
            ]);

            return true;
        });
    }

    public static function eliminarDestino(int $id): bool
    {
        $detalle = InventariosDiariosDetalle::find($id);
        if (!$detalle) {
            return false;
        }

        return (bool)$detalle->delete();
    }

    public static function finalizar(int $id, string $fecha): array
    {
        $reporte = InventariosDiario::find($id);
        if (!$reporte) {
            return ['ok' => false, 'message' => 'No se encontró el reporte.'];
        }

        $fechaObj = \DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fecha) {
            return ['ok' => false, 'message' => 'Error en la fecha del inventario'];
        }

        $reporte->fecha   = $fecha;
        $reporte->estatus = 1;

        return ['ok' => (bool)$reporte->save()];
    }

    public static function eliminarReporte(int $id): bool
    {
        return Capsule::transaction(function () use ($id) {
            InventariosDiariosDetalle::where('id_reporte', $id)->delete();

            $reporte = InventariosDiario::find($id);
            if (!$reporte) {
                return false;
            }

            return (bool)$reporte->delete();
        });
    }

    public static function colorCelda(int $valor, string $colorBase): string
    {
        $bg = ($valor > 0 && $valor < 11) ? self::COLOR_ALERTA : $colorBase;
        return 'background: ' . $bg . '; color: #fff;';
    }

    protected static function valorInt($valor): int
    {
        $valor = trim((string)$valor);
        return $valor === '' ? 0 : (int)$valor;
    }
}