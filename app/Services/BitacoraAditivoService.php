<?php

namespace App\Services;

use App\Models\Operativo\BitacoraAditivo;
use App\Models\Operativo\BitacoraReporte;
use App\Models\Operativo\InventarioAditivo;
use App\Models\Operativo\InventarioAditivoHist;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Collection;

class BitacoraAditivoService
{

    public const PRODUCTO_G_SUPER = 'G SUPER';
    public const PRODUCTO_G_PREMIUM = 'G PREMIUM';
    public const PRODUCTO_G_DIESEL = 'G DIESEL';

    public const ADITIVO_GASOLINA_NOMBRE = 'Gasolina Hitec 6590C';
    public const ADITIVO_DIESEL_NOMBRE = 'Diesel Hitec 4133G';

    private const PL = 20000.0;

    public static function calcularGalones(string $producto, float $litros): float
    {
        if ($producto === '' || $litros == 0.0) {
            return 0.0;
        }

        $PG = 0.0;
        $resultado = 0.0;

        switch ($producto) {
            case self::PRODUCTO_G_SUPER:
                $PG = 1.0;

                if ($litros == 30000.0) {
                    $resultado = 2.0;
                } elseif ($litros <= 10000.0) {
                    $resultado = 0.5;
                } else {
                    $resultado = ($litros / self::PL) * $PG;
                }
                break;

            case self::PRODUCTO_G_PREMIUM:
            case self::PRODUCTO_G_DIESEL:
                $PG = 2.0;
                $resultado = ($litros / self::PL) * $PG;
                break;
        }

        return self::redondearDosDecimales($resultado);
    }

    private static function redondearDosDecimales(float $valor): float
    {
        $exacto = sprintf('%.53f', $valor);
        [$ent, $frac] = explode('.', $exacto);

        $centesimas = (int) substr($frac, 0, 2);
        $resto = $frac[2] ?? '0';

        if ($resto >= '5') {
            $centesimas++;
            if ($centesimas === 100) {
                $ent = (int) $ent + 1;
                $centesimas = 0;
            }
        }

        return (int) $ent + $centesimas / 100;
    }

    public static function getBitacora(int $idEstacion): Collection
    {
        return BitacoraAditivo::where('id_estacion', $idEstacion)->get();
    }

    public static function getTotalInventario(int $idEstacion): array
    {
        $inventario = InventarioAditivo::where('id_estacion', $idEstacion)->first();

        return [
            'gasolina' => $inventario->gasolina ?? 0,
            'diesel'   => $inventario->diesel ?? 0
        ];
    }

    public static function getReportes(int $idEstacion): Collection
    {
        return BitacoraReporte::where('id_estacion', $idEstacion)->get();
    }

    public static function getInventarioHist(int $idEstacion): Collection
    {
        return InventarioAditivoHist::where('id_estacion', $idEstacion)->get();
    }

    public static function folioSiguiente(int $idEstacion): int
    {
        $folio = BitacoraAditivo::where('id_estacion', $idEstacion)->max('folio') + 1;

        return $folio ?: 1;
    }

    public static function crearBitacora(int $idEstacion, float $litros, string $producto, string $fecha, ?string $noFactura): array
    {
        if ($litros == 0.0 || trim($producto) === '' || trim($fecha) === '') {
            return ['success' => false, 'message' => 'Campos obligatorios faltantes'];
        }

        $galones = self::calcularGalones($producto, $litros);

        Capsule::beginTransaction();

        try {
            $inventario = InventarioAditivo::where('id_estacion', $idEstacion)->first();

            if (!$inventario) {
                throw new \Exception('Inventario no encontrado');
            }

            if ($producto === self::PRODUCTO_G_SUPER || $producto === self::PRODUCTO_G_PREMIUM) {
                $inventarioFisico = $inventario->gasolina - $galones;
            } elseif ($producto === self::PRODUCTO_G_DIESEL) {
                $inventarioFisico = $inventario->diesel - $galones;
            } else {
                throw new \Exception('Producto inválido');
            }

            BitacoraAditivo::create([
                'id_estacion'        => $idEstacion,
                'folio'              => self::folioSiguiente($idEstacion),
                'litros'             => $litros,
                'fecha'              => $fecha,
                'no_factura'         => $noFactura,
                'producto'           => $producto,
                'galones'            => $galones,
                'inventario_fisico'  => $inventarioFisico,
                'estado'             => 1
            ]);

            if ($producto === self::PRODUCTO_G_SUPER || $producto === self::PRODUCTO_G_PREMIUM) {
                $inventario->gasolina = $inventarioFisico;
            } else {
                $inventario->diesel = $inventarioFisico;
            }

            $inventario->save();

            Capsule::commit();

            return ['success' => true, 'message' => 'Registro guardado correctamente'];
        } catch (\Exception $e) {
            Capsule::rollBack();

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function editarFactura(int $id, string $noFactura): array
    {
        if (!$id || !$noFactura) {
            return ['success' => false, 'message' => 'Datos incompletos'];
        }

        $registro = BitacoraAditivo::find($id);

        if (!$registro) {
            return ['success' => false, 'message' => 'Registro no encontrado'];
        }

        $registro->no_factura = $noFactura;
        $registro->save();

        return ['success' => true, 'message' => 'Factura actualizada correctamente'];
    }

    public static function eliminarBitacora(int $id): array
    {
        $bitacora = BitacoraAditivo::find($id);

        if (!$bitacora) {
            return ['success' => false, 'message' => 'Folio no encontrado'];
        }

        if ($bitacora->estado == 0) {
            return ['success' => false, 'message' => 'No se puede eliminar un folio ya inactivo'];
        }

        $inventario = InventarioAditivo::where('id_estacion', $bitacora->id_estacion)->first();

        if (!$inventario) {
            return ['success' => false, 'message' => 'Inventario no encontrado'];
        }

        $producto = $bitacora->producto;
        $galones  = $bitacora->galones;
        $folio    = $bitacora->folio;
        $aditivoNombre = null;

        if ($producto === self::PRODUCTO_G_SUPER || $producto === self::PRODUCTO_G_PREMIUM) {
            $inventario->gasolina += $galones;
            $aditivoNombre = self::ADITIVO_GASOLINA_NOMBRE;
        } elseif ($producto === self::PRODUCTO_G_DIESEL) {
            $inventario->diesel += $galones;
            $aditivoNombre = self::ADITIVO_DIESEL_NOMBRE;
        }

        Capsule::beginTransaction();

        try {
            $bitacora->estado = 0;
            $bitacora->save();

            $inventario->save();

            InventarioAditivoHist::create([
                'id_estacion' => $bitacora->id_estacion,
                'aditivo'     => $aditivoNombre,
                'galones'     => $galones,
                'detalle'     => 'Se agrega aditivo por cancelación del folio 00' . $folio
            ]);

            Capsule::commit();

            return ['success' => true, 'message' => 'Folio eliminado correctamente'];
        } catch (\Throwable $e) {
            Capsule::rollBack();

            return ['success' => false, 'message' => 'Error al eliminar', 'error' => $e->getMessage()];
        }
    }

    public static function guardarReporte(int $idEstacion, int $idUsuario, string $fecha, ?array $file): array
    {
        if (!trim($fecha)) {
            return ['success' => false, 'message' => 'La fecha es obligatoria'];
        }

        $carpeta = dirname(__DIR__, 2) . '/public/uploads/archivos/';

        if (!file_exists($carpeta)) {
            mkdir_safe($carpeta, true);
        }

        $nombreArchivo = null;

        try {
            if ($file && $file['error'] === UPLOAD_ERR_OK) {
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $nombreArchivo = uniqid('rep_') . '.' . $extension;

                $rutaDestino = $carpeta . $nombreArchivo;

                if (!move_uploaded_file($file['tmp_name'], $rutaDestino)) {
                    throw new \Exception('No se pudo guardar el archivo');
                }
            }

            BitacoraReporte::create([
                'id_estacion' => $idEstacion,
                'id_usuario'  => $idUsuario,
                'fecha'       => $fecha,
                'hora'        => date('H:i:s'),
                'documento'   => $nombreArchivo
            ]);

            return ['success' => true, 'message' => 'Reporte guardado correctamente'];
        } catch (\Throwable $e) {
            if ($nombreArchivo && file_exists($carpeta . $nombreArchivo)) {
                unlink($carpeta . $nombreArchivo);
            }

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function eliminarReporte(int $id): array
    {
        $rutaBase = dirname(__DIR__, 2) . '/public/uploads/archivos/bitacora-aditivo/';

        Capsule::beginTransaction();

        try {
            $reporte = BitacoraReporte::find($id);

            if (!$reporte) {
                throw new \Exception('Registro no encontrado');
            }

            $rutaArchivo = $rutaBase . $reporte->documento;

            if ($reporte->documento && file_exists($rutaArchivo)) {
                unlink($rutaArchivo);
            }

            $reporte->delete();

            Capsule::commit();

            return ['success' => true, 'message' => 'Reporte eliminado correctamente'];
        } catch (\Throwable $e) {
            Capsule::rollBack();

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function crearInventario(int $idEstacion, float $gasolina, float $diesel): array
    {
        if ($gasolina == 0.0 && $diesel == 0.0) {
            return ['success' => false, 'message' => 'No se ingresado ningun aditivo'];
        }

        Capsule::beginTransaction();

        try {
            $inventario = InventarioAditivo::firstOrCreate(
                ['id_estacion' => $idEstacion],
                ['gasolina' => 0, 'diesel' => 0]
            );

            if (!$inventario) {
                throw new \Exception('Inventario no encontrado');
            }

            if ($gasolina > 0) {
                InventarioAditivoHist::create([
                    'id_estacion' => $idEstacion,
                    'aditivo'     => self::ADITIVO_GASOLINA_NOMBRE,
                    'galones'     => $gasolina,
                    'detalle'     => 'Se agrega aditivo'
                ]);

                $inventario->gasolina += $gasolina;
            }

            if ($diesel > 0) {
                InventarioAditivoHist::create([
                    'id_estacion' => $idEstacion,
                    'aditivo'     => self::ADITIVO_DIESEL_NOMBRE,
                    'galones'     => $diesel,
                    'detalle'     => 'Se agrega aditivo'
                ]);

                $inventario->diesel += $diesel;
            }

            $inventario->save();
            Capsule::commit();

            return ['success' => true, 'message' => 'Registro guardado correctamente'];
        } catch (\Exception $e) {
            Capsule::rollBack();

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function getResumen(array $idEstaciones = []): array
    {
        $query = InventarioAditivo::orderBy('id');

        if (!empty($idEstaciones)) {
            $query->whereIn('id_estacion', $idEstaciones);
        }

        return $query->get()->map(function (InventarioAditivo $inventario) {
            return [
                'id_estacion' => $inventario->id_estacion,
                'gasolina'    => $inventario->gasolina,
                'diesel'      => $inventario->diesel
            ];
        })->all();
    }
}