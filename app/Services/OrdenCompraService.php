<?php

namespace App\Services;

use App\Core\Session;
use App\Models\Operativo\OpOrdenCompra;
use App\Models\Operativo\OpOrdenCompraRazonSocial;
use App\Models\Operativo\OpOrdenCompraProveedor;
use App\Models\Operativo\OpOrdenCompraArticulo;
use App\Models\Operativo\OpOrdenCompraRefacturacion;
use App\Models\Operativo\OpOrdenCompraFirma;
use App\Models\Operativo\OpOrdenCompraToken;
use App\Models\Operativo\RhLocalidad;
use App\Models\Estacion;
use App\Models\Usuario;
use Carbon\Carbon;

class OrdenCompraService
{
    public static function getPermisos(): array
    {
        $sessionUsuario = Session::get('usuario') ?? [];
        $idUsuario = (int)($sessionUsuario['id'] ?? 0);
        $puesto = $sessionUsuario['nombre_puesto'] ?? '';

        return [
            'id_usuario'       => $idUsuario,
            'nombre_puesto'    => $puesto,
            'es_direccion_op'  => ($puesto === 'Dirección de operaciones' || $idUsuario === 19),
            'puede_vobo'       => ($idUsuario === 19)
        ];
    }

    public static function getLista(int $year, int $mes): array
    {
        $compras = OpOrdenCompra::with('usuario')
            ->where('year', $year)
            ->where('mes', $mes)
            ->orderBy('no_control', 'asc')
            ->get();

        return $compras->map(function ($c) {
            $fechaPartes = explode(' ', $c->fecha ?? '');
            $fechaFmt = !empty($fechaPartes[0]) && function_exists('formatearFecha') 
                ? formatearFecha($fechaPartes[0]) 
                : ($fechaPartes[0] ?? 'S/I');

            return [
                'id'          => $c->id,
                'no_control'  => '00' . $c->no_control,
                'responsable' => $c->usuario?->nombre ?? 'S/I',
                'fecha'       => $fechaFmt,
                'estatus'     => (int)$c->estatus
            ];
        })->toArray();
    }

    public static function crearReporte(int $year, int $mes, int $idUsuario): array
    {
        $maxControl = OpOrdenCompra::where('year', $year)
            ->where('mes', $mes)
            ->max('no_control');
        $noControl = ($maxControl ?: 0) + 1;

        $orden = OpOrdenCompra::create([
            'id_usuario'       => $idUsuario,
            'fecha'            => date('Y-m-d'),
            'year'             => $year,
            'mes'              => $mes,
            'porcentaje_total' => 0,
            'cargo'            => '',
            'no_control'       => $noControl,
            'iva'              => 0.16,
            'estatus'          => 0
        ]);

        return [
            'success' => true,
            'id'      => $orden->id,
            'message' => 'Orden de compra creada exitosamente'
        ];
    }

public static function getDetalleCompleto(int $id): ?array
    {
        $orden = OpOrdenCompra::with(['usuario', 'firmas.usuario'])->find($id);
        if (!$orden) return null;

        $fechaPartes = explode(' ', $orden->fecha ?? '');
        $fechaSolo = $fechaPartes[0] ?? date('Y-m-d');

        // 1. Estación
        $datosEstacion = self::resolverDatosEstacion($id);

        // 2. Proveedores con sus Artículos
        $proveedores = OpOrdenCompraProveedor::where('id_ordencompra', $id)
            ->orderBy('id', 'asc')
            ->get();

        $cuadroProveedores = [];
        $ivaTasa = (float)($orden->iva ?: 0.16);

        foreach ($proveedores as $prov) {
            $articulos = OpOrdenCompraArticulo::where('id_ordencompra', $id)
                ->where('id_proveedor', $prov->id)
                ->orderBy('id', 'asc')
                ->get();

            $totalSubTotal = 0.0;
            $articulosData = [];

            $descuentoProv = (float)($prov->descuento ?? 0);
            $envioProv = (float)($prov->envio_cp ?? 0);

            foreach ($articulos as $art) {
                $unidades = (float)($art->unidades ?? 0);
                $precioU = (float)($art->precio_unitario ?? 0);
                $subtotal = $unidades * $precioU;
                $subtotalPU_IVA = ($subtotal - $descuentoProv + $envioProv) * $ivaTasa;
                $totalFila = $subtotal + $subtotalPU_IVA;

                $totalSubTotal += $subtotal;

                $articulosData[] = [
                    'id'              => (int)$art->id,
                    'concepto'        => (string)$art->concepto,
                    'unidades'        => $unidades,
                    'estatus_r'       => (string)$art->estatus_r,
                    'precio_unitario' => $precioU,
                    'subtotal'        => $subtotal,
                    'iva_fila'        => $subtotalPU_IVA,
                    'total_fila'      => $totalFila
                ];
            }

            $subtotalNeto = $totalSubTotal - $descuentoProv + $envioProv;
            $totalIVA = $subtotalNeto * $ivaTasa;
            $totalFinalPagar = $totalIVA + $subtotalNeto;

            $cuadroProveedores[] = [
                'id'            => (int)$prov->id,
                'razon_social'  => (string)$prov->razon_social,
                'direccion'     => (string)$prov->direccion,
                'contacto'      => (string)$prov->contacto,
                'email'         => (string)$prov->email,
                'descuento'     => $descuentoProv,
                'envio_cp'      => $envioProv,
                'check_p'       => (int)($prov->check_p ?? 0),
                'articulos'     => $articulosData,
                'articulos_count' => count($articulosData),
                'suma'          => $totalSubTotal,
                'subtotal_neto' => $subtotalNeto,
                'total_iva'     => $totalIVA,
                'total_pagar'   => $totalFinalPagar
            ];
        }

        // 3. Refacturación
        $refacturaciones = OpOrdenCompraRefacturacion::with('estacion')
            ->where('id_ordencompra', $id)
            ->get();

        $subTotalRefacturacion = 0.0;
        $refacturacionData = [];

        $provSeleccionado = collect($cuadroProveedores)->firstWhere('check_p', 1);
        $descuentoSel = $provSeleccionado ? (float)$provSeleccionado['descuento'] : 0.0;
        $envioSel = $provSeleccionado ? (float)$provSeleccionado['envio_cp'] : 0.0;

        foreach ($refacturaciones as $rf) {
            $cant = (float)($rf->cantidad ?? 0);
            $imp = (float)($rf->importe ?? 0);
            $totalFila = $cant * $imp;
            $subTotalRefacturacion += $totalFila;

            $refacturacionData[] = [
                'id'          => (int)$rf->id,
                'id_estacion' => (int)$rf->id_estacion,
                'estacion'    => $rf->estacion?->nombre ?? 'S/I',
                'descripcion' => (string)$rf->descripcion,
                'cantidad'    => $cant,
                'importe'     => $imp,
                'porcentaje'  => (float)($rf->porcentaje ?? 0),
                'cantidadES'  => (float)($rf->cantidadES ?? 0),
                'cantidadAl'  => (float)($rf->cantidadAl ?? 0),
                'total'       => $totalFila
            ];
        }

        $subtotalNetoRef = $subTotalRefacturacion - $descuentoSel + $envioSel;
        $totalIvaRef = $subtotalNetoRef * $ivaTasa;
        $totalFinalRef = $totalIvaRef + $subtotalNetoRef;

        // 4. Firmas
        $firmasData = $orden->firmas->map(function ($f) {
            $fechaFmt = '';
            if (!empty($f->fecha)) {
                $fechaFmt = function_exists('formatearFecha') ? formatearFecha($f->fecha) : $f->fecha;
            }

            return [
                'id'          => (int)$f->id,
                'tipo_firma'  => (string)$f->tipo_firma,
                'nombre'      => $f->usuario?->nombre ?? 'Usuario',
                'firma'       => (string)$f->firma,
                'fecha'       => $fechaFmt,
                'tipo_label'  => $f->tipo_firma === 'A' ? 'Elaboró' : 'Vo.Bo.'
            ];
        })->toArray();

        return [
            'id'                    => (int)$orden->id,
            'no_control'            => '00' . $orden->no_control,
            'cargo'                 => (string)($orden->cargo ?? ''),
            'fecha'                 => $fechaSolo,
            'fecha_fmt'             => function_exists('formatearFecha') ? formatearFecha($fechaSolo) : $fechaSolo,
            'porcentaje_total'      => (float)($orden->porcentaje_total ?? 0),
            'iva'                   => $ivaTasa,
            'estatus'               => (int)$orden->estatus,
            'estacion'              => $datosEstacion,
            'proveedores'           => $cuadroProveedores,
            'proveedores_count'     => count($cuadroProveedores),
            'refacturacion'         => [
                'filas'         => $refacturacionData,
                'suma'          => $subTotalRefacturacion,
                'descuento'     => $descuentoSel,
                'envio'         => $envioSel,
                'subtotal_neto' => $subtotalNetoRef,
                'iva'           => $totalIvaRef,
                'total_pagar'   => $totalFinalRef
            ],
            'firmas'                => $firmasData
        ];
    }

    public static function resolverDatosEstacion(int $idOrden): array
    {
        $rel = OpOrdenCompraRazonSocial::with('localidad')->where('id_ordencompra', $idOrden)->first();
        if (!$rel || !$rel->localidad) {
            return [
                'id_localidad' => 0,
                'razon_social' => 'Sin estación asignada',
                'rfc'          => 'S/I',
                'direccion'    => 'S/I'
            ];
        }

        $nombreLoc = $rel->localidad->localidad;

        if ($nombreLoc === 'Quitarga') {
            return [
                'id_localidad' => $rel->id_estacion,
                'razon_social' => 'COMERCIAL GASOLINERA QUITARGA',
                'rfc'          => 'CGQ120525C15',
                'direccion'    => 'Calle Plaza Tajin No. 433, Col. CTM Culhuacan Secc. V, C.P. 04480'
            ];
        }

        if ($nombreLoc === 'Comercializadora') {
            return [
                'id_localidad' => $rel->id_estacion,
                'razon_social' => 'COMERCIALIZADORA DE ARTICULOS GASOLINEROS',
                'rfc'          => 'CAG05052557A',
                'direccion'    => 'Carretera Rio Hondo Huixquilucan No. 401, San Bartolomé Coatepec, C.P. 52770'
            ];
        }

        $est = Estacion::where('nombre', $nombreLoc)->first();
        if ($est) {
            return [
                'id_localidad' => $rel->id_estacion,
                'razon_social' => $est->razonsocial,
                'rfc'          => $est->rfc,
                'direccion'    => $est->direccioncompleta
            ];
        }

        return [
            'id_localidad' => $rel->id_estacion,
            'razon_social' => $nombreLoc,
            'rfc'          => 'S/I',
            'direccion'    => 'S/I'
        ];
    }

    public static function guardarEstacion(int $idOrden, int $idEstacion): bool
    {
        OpOrdenCompraRazonSocial::updateOrCreate(
            ['id_ordencompra' => $idOrden],
            ['id_estacion' => $idEstacion]
        );
        return true;
    }

    public static function editarFormato(int $idOrden, int $campo, string $valor): bool
    {
        $orden = OpOrdenCompra::find($idOrden);
        if (!$orden) return false;

        match ($campo) {
            1 => $orden->cargo = $valor,
            2 => $orden->fecha = $valor,
            3 => $orden->porcentaje_total = (float)$valor,
            default => null
        };

        return (bool)$orden->save();
    }

    public static function agregarProveedor(int $idOrden, array $data): array
    {
        $prov = OpOrdenCompraProveedor::create([
            'id_ordencompra' => $idOrden,
            'razon_social'   => $data['razon_social'],
            'direccion'      => $data['direccion'],
            'contacto'       => $data['contacto'],
            'email'          => $data['email'],
            'descuento'      => 0,
            'envio_cp'       => 0,
            'check_p'        => 0
        ]);

        return [
            'success' => (bool)$prov,
            'message' => $prov ? 'Proveedor agregado exitosamente.' : 'Error al registrar el proveedor.'
        ];
    }

    public static function editarProveedor(int $idProveedor, array $data): array
    {
        $prov = OpOrdenCompraProveedor::find($idProveedor);
        if (!$prov) return ['success' => false, 'message' => 'Proveedor no encontrado.'];

        $ok = $prov->update([
            'razon_social' => $data['razon_social'],
            'direccion'    => $data['direccion'],
            'contacto'     => $data['contacto'],
            'email'        => $data['email']
        ]);

        return [
            'success' => (bool)$ok,
            'message' => $ok ? 'Proveedor editado exitosamente.' : 'Error al editar el proveedor.'
        ];
    }

    public static function eliminarProveedor(int $idProveedor): array
    {
        $prov = OpOrdenCompraProveedor::find($idProveedor);
        if (!$prov) return ['success' => false, 'message' => 'Proveedor no encontrado.'];

        OpOrdenCompraArticulo::where('id_proveedor', $idProveedor)->delete();
        $prov->delete();

        return ['success' => true, 'message' => 'Proveedor eliminado exitosamente.'];
    }

    public static function seleccionarProveedor(int $idOrden, int $idProveedor, int $valor): bool
    {
        // Pone a 0 todos los de la orden
        OpOrdenCompraProveedor::where('id_ordencompra', $idOrden)->update(['check_p' => 0]);
        // Marca el seleccionado
        OpOrdenCompraProveedor::where('id', $idProveedor)->update(['check_p' => $valor]);
        return true;
    }

    public static function actualizarCostosProveedor(int $idProveedor, int $tipo, float $valor): bool
    {
        $prov = OpOrdenCompraProveedor::find($idProveedor);
        if (!$prov) return false;

        if ($tipo === 1) {
            $prov->descuento = $valor;
        } elseif ($tipo === 2) {
            $prov->envio_cp = $valor;
        }

        return (bool)$prov->save();
    }

    public static function agregarArticulo(int $idOrden, array $data): array
    {
        $art = OpOrdenCompraArticulo::create([
            'id_ordencompra'  => $idOrden,
            'id_proveedor'    => (int)$data['id_proveedor'],
            'concepto'        => trim($data['concepto']),
            'unidades'        => (float)$data['unidades'],
            'estatus_r'       => trim($data['estatus_r']),
            'precio_unitario' => (float)$data['precio_unitario']
        ]);

        return [
            'success' => (bool)$art,
            'message' => $art ? 'Artículo agregado exitosamente.' : 'Error al agregar el artículo.'
        ];
    }

    public static function eliminarArticulo(int $id): bool
    {
        return (bool)OpOrdenCompraArticulo::destroy($id);
    }

    public static function agregarRefacturacion(int $idOrden, array $data): array
    {
        $rf = OpOrdenCompraRefacturacion::create([
            'id_ordencompra' => $idOrden,
            'id_estacion'    => (int)$data['id_estacion'],
            'descripcion'    => trim($data['descripcion']),
            'cantidad'       => (float)$data['cantidad'],
            'importe'        => (float)$data['importe'],
            'porcentaje'     => (float)($data['porcentaje'] ?? 0),
            'cantidadES'     => (float)($data['cantidadES'] ?? 0),
            'cantidadAl'     => (float)($data['cantidadAl'] ?? 0)
        ]);

        return [
            'success' => (bool)$rf,
            'message' => $rf ? 'Refacturación agregada exitosamente.' : 'Error al agregar la refacturación.'
        ];
    }

    public static function eliminarRefacturacion(int $id): bool
    {
        return (bool)OpOrdenCompraRefacturacion::destroy($id);
    }

    public static function guardarFirmaPad(int $idOrden, int $idUsuario, string $base64): array
    {
        $orden = OpOrdenCompra::find($idOrden);
        if (!$orden) {
            return ['success' => false, 'message' => 'Orden de compra no encontrada.'];
        }

        $img = str_replace('data:image/png;base64,', '', $base64);
        $fileData = base64_decode($img);
        $fileName = uniqid() . '.png';
        $uploadDir = dirname(__DIR__, 2) . '/public/uploads/firmas/orden-compra/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        if (!file_put_contents($uploadDir . $fileName, $fileData)) {
            return ['success' => false, 'message' => 'No se pudo guardar la imagen de la firma.'];
        }

        OpOrdenCompraFirma::updateOrCreate(
            ['id_ordencompra' => $idOrden, 'tipo_firma' => 'A'],
            [
                'id_usuario' => $idUsuario,
                'firma'      => $fileName,
                'fecha'      => date('Y-m-d H:i:s')
            ]
        );

        $orden->estatus = 1;
        $orden->save();

        return ['success' => true, 'message' => 'Orden de compra finalizada y firmada correctamente.'];
    }

    public static function generarToken(int $idOrden, int $idUsuario, int $via): array
    {
        OpOrdenCompraToken::where('id_ordencompra', $idOrden)
            ->where('id_usuario', $idUsuario)
            ->delete();

        $token = (string)rand(100000, 999999);

        OpOrdenCompraToken::create([
            'id_ordencompra' => $idOrden,
            'id_usuario'    => $idUsuario,
            'token'         => $token
        ]);

        return ['success' => true, 'message' => 'Token enviado exitosamente.'];
    }

    public static function firmarToken(int $idOrden, int $idUsuario, string $token): array
    {
        $valido = OpOrdenCompraToken::where('id_ordencompra', $idOrden)
            ->where('id_usuario', $idUsuario)
            ->where('token', $token)
            ->first();

        if (!$valido) {
            return ['success' => false, 'message' => 'El token de seguridad es incorrecto o ha caducado.'];
        }

        $firmaHash = 'Firma: ' . bin2hex(random_bytes(32)) . '.' . uniqid();

        OpOrdenCompraFirma::updateOrCreate(
            ['id_ordencompra' => $idOrden, 'tipo_firma' => 'B'],
            [
                'id_usuario' => $idUsuario,
                'firma'      => $firmaHash,
                'fecha'      => date('Y-m-d H:i:s')
            ]
        );

        $orden = OpOrdenCompra::find($idOrden);
        if ($orden) {
            $orden->estatus = 2;
            $orden->save();
        }

        $valido->delete();

        return ['success' => true, 'message' => 'La orden de compra fue autorizada con éxito.'];
    }

    public static function eliminarOrden(int $id): array
    {
        $orden = OpOrdenCompra::find($id);
        if (!$orden) return ['success' => false, 'message' => 'Registro no encontrado.', 'code' => 404];
        if ($orden->estatus >= 1) return ['success' => false, 'message' => 'No se puede eliminar una orden de compra firmada.', 'code' => 422];

        OpOrdenCompraRazonSocial::where('id_ordencompra', $id)->delete();
        OpOrdenCompraArticulo::where('id_ordencompra', $id)->delete();
        OpOrdenCompraProveedor::where('id_ordencompra', $id)->delete();
        OpOrdenCompraRefacturacion::where('id_ordencompra', $id)->delete();
        OpOrdenCompraFirma::where('id_ordencompra', $id)->delete();
        OpOrdenCompraToken::where('id_ordencompra', $id)->delete();
        $orden->delete();

        return ['success' => true, 'message' => 'Registro eliminado exitosamente.', 'code' => 200];
    }
}