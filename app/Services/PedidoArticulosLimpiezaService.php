<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Operativo\PedidoLimpieza;
use App\Models\Operativo\PedidoLimpiezaDetalle;
use App\Models\Operativo\PedidoLimpiezaFirma;
use App\Models\Operativo\PedidoLimpiezaToken;
use App\Models\Operativo\LimpiezaLista;
use App\Models\Operativo\InventarioLimpieza;
use App\Models\Operativo\LimpiezaReporte;
use App\Models\Operativo\LimpiezaReporteDetalle;
use Illuminate\Database\Capsule\Manager as Capsule;
use Carbon\Carbon;

class PedidoArticulosLimpiezaService
{

    public const MODULE_KEY = 'pedido-articulos-limpieza';
    public const MODULO_PADRE_CLAVE = 'comercializadora';

    public const FIRMANTE_VOBO = 19;

    public const PUESTO_ENCARGADO = 'Encargado';
    public const PUESTO_ASISTENTE_ADMIN = 'Asistente Administrativo';

    public const ESTATUS_PEDIDO = [
        0 => 'Pendiente',
        1 => 'Finalizado',
        2 => 'Firmado',
        3 => 'Entregado',
    ];

    public const ESTATUS_REPORTE = [
        0 => 'Pendiente',
        1 => 'Finalizado',
    ];

    public static function getPermisos(): array
    {
        $usuario = Auth::user();
        $sessionUsuario = Session::get('usuario');
        $idUsuario = (int)($sessionUsuario['id'] ?? 0);
        $idPuesto = (int)($usuario->id_puesto ?? 0);
        $idEstacion = (int)($sessionUsuario['id_estacion'] ?? 0);
        $multiestacion = !empty($sessionUsuario['multiestacion']);
        $nombrePuesto = $usuario->puesto->tipo_puesto ?? '';
        $nombreUsuario = $sessionUsuario['nombre'] ?? '';

        $permisosDb = ModuloDptoOperativoService::permisosSesion(self::MODULO_PADRE_CLAVE);
        $tienePermisoLeer = !empty($permisosDb['leer']);
        $tienePermisoCrear = !empty($permisosDb['crear']);
        $tienePermisoEditar = !empty($permisosDb['editar']);
        $tienePermisoEliminar = !empty($permisosDb['eliminar']);
        $tienePermisoDescargar = !empty($permisosDb['descargar']);
        $tieneSubmenuLimpieza = false;

        if (!empty($permisosDb['submenus'])) {
            foreach ($permisosDb['submenus'] as $sub) {
                if (($sub['clave'] ?? '') === self::MODULE_KEY) {
                    $tieneSubmenuLimpieza = true;
                    break;
                }
            }
        }

        $esEncargado = in_array($nombrePuesto, [
            self::PUESTO_ENCARGADO,
            self::PUESTO_ASISTENTE_ADMIN,
        ], true);

        return [
            'id_usuario'       => $idUsuario,
            'id_estacion'      => $idEstacion,
            'id_puesto'        => $idPuesto,
            'nombre_puesto'    => $nombrePuesto,
            'nombre_usuario'   => $nombreUsuario,
            'multiestacion'    => $multiestacion,
            'tieneSubmenu'     => $tieneSubmenuLimpieza,
            'puedeLeer'        => $tienePermisoLeer || $tieneSubmenuLimpieza,
            'puedeAcceso'      => $tienePermisoLeer || $tieneSubmenuLimpieza,
            'puedeCrear'       => $tienePermisoCrear,
            'puedeEditar'      => $tienePermisoEditar,
            'puedeEliminar'    => $tienePermisoEliminar,
            'puedeDescargar'   => $tienePermisoDescargar,
            'puedeCatalogo'    => $esEncargado,
            'puedeInventario'  => $esEncargado,
            'puedeEntregar'    => $esEncargado || $idUsuario === self::FIRMANTE_VOBO,
            'esEncargado'      => $esEncargado,
            'esFirmanteVOBO'   => $idUsuario === self::FIRMANTE_VOBO,
            'puedeFirmarVoBo'  => $idUsuario === self::FIRMANTE_VOBO,
        ];
    }

    public static function getAllowedStationIds(): array
    {
        $ids = [];
        foreach (ModuleStationService::getAvailableStations(self::MODULE_KEY) as $s) {
            $ids[] = (int)$s['id'];
        }
        return array_values(array_unique($ids));
    }

    public static function getNombreEstacion(int $idEstacion): string
    {
        $estacion = Estacion::find($idEstacion);
        return $estacion ? $estacion->nombre : '';
    }

    public static function getRazonSocialEstacion(int $idEstacion): string
    {
        $estacion = Estacion::find($idEstacion);
        return $estacion ? $estacion->razonsocial : '';
    }

    public static function getPersonal(int $idUsuario): array
    {
        $usuario = Usuario::find($idUsuario);
        if (!$usuario) {
            return ['nombre' => '', 'puesto' => ''];
        }
        return [
            'nombre' => $usuario->nombre ?? '',
            'puesto' => $usuario->puesto->tipo_puesto ?? '',
        ];
    }

    private static function formatearFechaHora($fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return formatearFecha($fecha) . ', ' . $fecha->format('g:i a');
        }
        if ($fecha === null || trim((string)$fecha) === '') {
            return '';
        }
        return formatearFecha($fecha);
    }

    public static function getPendingCountsActual(): int
    {
        $ctx = ModuleStationService::getContext(self::MODULE_KEY);
        $idEstacion = $ctx['id_estacion'];

        $q = PedidoLimpieza::query()
            ->where('status', '>', 0)
            ->where('status', '<=', 1);

        if ($idEstacion) {
            $q->where('id_estacion', $idEstacion);
        } else {
            $allowed = self::getAllowedStationIds();
            if (!empty($allowed)) {
                $q->whereIn('id_estacion', $allowed);
            }
        }

        return (int)$q->count();
    }

    public static function getPendingCountsFlat(): array
    {
        $pendientes = ['total' => 0];
        $ids = [];
        foreach (ModuleStationService::getAvailableStations(self::MODULE_KEY) as $s) {
            $id = (int)$s['id'];
            $ids[] = $id;
            $pendientes['estacion_' . $id] = 0;
        }

        if (!empty($ids)) {
            $rows = PedidoLimpieza::query()
                ->selectRaw('id_estacion, COUNT(*) as total')
                ->whereIn('id_estacion', $ids)
                ->where('status', '>', 0)
                ->where('status', '<=', 1)
                ->groupBy('id_estacion')
                ->get();

            foreach ($rows as $row) {
                $key = 'estacion_' . (int)$row->id_estacion;
                if (isset($pendientes[$key])) {
                    $pendientes[$key] = (int)$row->total;
                    $pendientes['total'] += (int)$row->total;
                }
            }
        }

        return $pendientes;
    }

    /* ================= CATÁLOGO (op_limpieza_lista) ================= */

    public static function getCatalogos(bool $soloActivos = false): array
    {
        $q = LimpiezaLista::query()->orderBy('producto', 'asc');
        if ($soloActivos) {
            $q->where('estatus', 1);
        }
        return $q->get()->map(function ($p) {
            return [
                'id'       => (int)$p->id,
                'unidad'   => $p->unidad ?? '',
                'producto' => $p->producto ?? '',
                'estatus'  => (int)$p->estatus,
            ];
        })->all();
    }

    public static function guardarProducto(int $id, string $unidad, string $producto): array
    {
        if (trim($unidad) === '' || trim($producto) === '') {
            return ['success' => false, 'message' => 'Campos obligatorios faltantes'];
        }

        if ($id === 0) {
            LimpiezaLista::create([
                'unidad'  => $unidad,
                'producto' => $producto,
                'estatus' => 1,
            ]);
            return ['success' => true, 'message' => 'Producto agregado correctamente'];
        }

        $item = LimpiezaLista::find($id);
        if (!$item) {
            return ['success' => false, 'message' => 'Producto no encontrado'];
        }

        $item->unidad = $unidad;
        $item->producto = $producto;
        $item->save();

        return ['success' => true, 'message' => 'Producto actualizado correctamente'];
    }

    public static function eliminarProducto(int $id): array
    {
        $item = LimpiezaLista::find($id);
        if (!$item) {
            return ['success' => false, 'message' => 'Producto no encontrado'];
        }

        $item->estatus = 0;
        $item->save();

        return ['success' => true, 'message' => 'Producto eliminado correctamente'];
    }

    /* ================= INVENTARIO (op_inventario_limpieza) ================= */

    public static function getInventario(int $idEstacion): array
    {
        $rows = InventarioLimpieza::query()
            ->where('id_estacion', $idEstacion)
            ->where('status', 1)
            ->orderBy('id', 'asc')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $producto = LimpiezaLista::find($row->id_producto);
            $result[] = [
                'id'            => (int)$row->id,
                'id_producto'   => (int)$row->id_producto,
                'producto'      => $producto ? $producto->producto : '',
                'unidad'        => $producto ? $producto->unidad : '',
                'piezas'        => (int)$row->piezas,
                'status'        => (int)$row->status,
            ];
        }

        return $result;
    }

    public static function agregarInventario(int $idEstacion, int $idProducto, int $piezas): array
    {
        if (!$idEstacion || !$idProducto || $piezas <= 0) {
            return ['success' => false, 'message' => 'Datos incompletos'];
        }

        Capsule::beginTransaction();

        try {
            $inventario = InventarioLimpieza::where('id_estacion', $idEstacion)
                ->where('id_producto', $idProducto)
                ->lockForUpdate()
                ->first();

            if (!$inventario) {
                InventarioLimpieza::create([
                    'id_estacion' => $idEstacion,
                    'id_producto' => $idProducto,
                    'piezas'      => $piezas,
                    'status'      => 1,
                ]);
            } else {
                $inventario->piezas = (int)$inventario->piezas + $piezas;
                $inventario->status = 1;
                $inventario->save();
            }

            Capsule::commit();
        } catch (\Throwable $e) {
            Capsule::rollBack();
            return ['success' => false, 'message' => 'Error al actualizar el inventario', 'error' => $e->getMessage()];
        }

        return ['success' => true, 'message' => 'Inventario actualizado correctamente'];
    }

    public static function eliminarInventarioItem(int $id): array
    {
        $item = InventarioLimpieza::find($id);
        if (!$item) {
            return ['success' => false, 'message' => 'Registro no encontrado'];
        }

        $item->status = 0;
        $item->save();

        return ['success' => true, 'message' => 'Item eliminado correctamente'];
    }

    /* ================= PEDIDOS (op_pedido_limpieza) ================= */

    public static function getPedidos(int $idEstacion): array
    {
        $pedidos = PedidoLimpieza::where('id_estacion', $idEstacion)
            ->orderBy('id', 'desc')
            ->get();

        $result = [];
        foreach ($pedidos as $pedido) {
            $personal = self::getPersonal($pedido->id_personal);

            $totalPiezas = PedidoLimpiezaDetalle::where('id_pedido', $pedido->id)
                ->sum('piezas');
            $totalPiezas = (int)$totalPiezas;

            $firmaA = PedidoLimpiezaFirma::where('id_pedido', $pedido->id)
                ->where('tipo_firma', 'A')
                ->exists();
            $firmaB = PedidoLimpiezaFirma::where('id_pedido', $pedido->id)
                ->where('tipo_firma', 'B')
                ->exists();

            $result[] = [
                'id'            => (int)$pedido->id,
                'id_estacion'   => (int)$pedido->id_estacion,
                'nombre_estacion' => self::getNombreEstacion((int)$pedido->id_estacion),
                'id_personal'   => (int)$pedido->id_personal,
                'personal'      => $personal['nombre'],
                'puesto'        => $personal['puesto'],
                'fecha' => $pedido->fecha ? formatearFecha($pedido->fecha) : '',
                'fecha_formateada' => self::formatearFechaHora($pedido->fecha),
                'fecha_hora' => self::formatearFechaHora($pedido->fecha),
                'total_piezas'  => $totalPiezas,
                'num_productos' => PedidoLimpiezaDetalle::where('id_pedido', $pedido->id)->count(),
                'status'        => (int)$pedido->status,
                'status_label'  => self::ESTATUS_PEDIDO[(int)$pedido->status] ?? 'Desconocido',
                'tiene_firma_a' => $firmaA,
                'tiene_firma_b' => $firmaB,
            ];
        }

        return $result;
    }

    public static function getPedido(int $id): ?array
    {
        $pedido = PedidoLimpieza::with('detalle', 'firmas')->find($id);
        if (!$pedido) {
            return null;
        }

        $personal = self::getPersonal($pedido->id_personal);

        $firmas = [];
        foreach ($pedido->firmas as $firma) {
            $tipo = $firma->tipo_firma;
            $esImagen = $tipo === 'A' && $firma->firma && strpos($firma->firma, 'Firma:') !== 0;
            $fechaFirmaObj = $firma->fecha;
            $fechaCompuesta = '';
            if ($fechaFirmaObj instanceof \DateTimeInterface) {
                $fechaCompuesta = formatearFecha($fechaFirmaObj) . ', ' . $fechaFirmaObj->format('g:i a');
            } elseif ($fechaFirmaObj !== null && trim((string)$fechaFirmaObj) !== '') {
                $fechaCompuesta = formatearFecha($fechaFirmaObj);
            }

            $firmas[$tipo] = [
                'tipo_firma'     => $tipo,
                'tipo_label'     => $tipo === 'A' ? 'ELABORÓ / ENCARGADO' : 'FIRMA DE VO.BO.',
                'id_usuario'     => (int)$firma->id_usuario,
                'firma'          => $firma->firma ?? 'Sin información',
                'firma_texto'    => $esImagen ? '' : ($firma->firma ?? 'Sin información'),
                'firma_img_url'  => $esImagen ? '/uploads/firmas/pedido-articulos-limpieza/' . $firma->firma : '',
                'fecha'          => $fechaCompuesta !== '' && $fechaCompuesta !== ', ' ? $fechaCompuesta : '',
                'personal'       => self::getPersonal($firma->id_usuario)['nombre'],
                'usuario_nombre' => self::getPersonal($firma->id_usuario)['nombre'],
            ];
        }

        $detalle = [];
        $totalPiezas = 0;
        $num = 1;
        foreach ($pedido->detalle as $item) {
            $totalPiezas += (int)$item->piezas;

            $nombreProducto = $item->producto ?? '';
            $unidadProducto = $item->unidad ?? '';

            if ((int)$item->id_producto > 0) {
                $productoCat = LimpiezaLista::find($item->id_producto);
                if ($productoCat) {
                    $nombreProducto = $productoCat->producto;
                    $unidadProducto = $productoCat->unidad;
                }
            }

            $detalle[] = [
                'id'      => (int)$item->id,
                'num'     => $num++,
                'id_producto' => (int)$item->id_producto,
                'unidad'  => $unidadProducto ?? '',
                'producto' => $nombreProducto ?? '',
                'piezas'  => (int)$item->piezas,
            ];
        }

        return [
            'id'            => (int)$pedido->id,
            'id_estacion'   => (int)$pedido->id_estacion,
            'id_personal'   => (int)$pedido->id_personal,
            'nombre_estacion' => self::getNombreEstacion($pedido->id_estacion),
            'personal'      => $personal['nombre'],
            'puesto'        => $personal['puesto'],
            'fecha' => $pedido->fecha ? formatearFecha($pedido->fecha) : '',
            'fecha_formateada' => self::formatearFechaHora($pedido->fecha),
            'fecha_hora' => self::formatearFechaHora($pedido->fecha),
            'status'        => (int)$pedido->status,
            'status_label'  => self::ESTATUS_PEDIDO[(int)$pedido->status] ?? 'Desconocido',
            'total_piezas'  => $totalPiezas,
            'detalle'       => $detalle,
            'firmas'        => $firmas,
        ];
    }

    public static function crearPedido(int $idEstacion, int $idUsuario): array
    {
        if (!$idEstacion || !$idUsuario) {
            return ['success' => false, 'message' => 'Datos incompletos'];
        }

        $pedido = PedidoLimpieza::create([
            'id_estacion' => $idEstacion,
            'id_personal' => $idUsuario,
            'fecha'       => Carbon::now()->format('Y-m-d H:i:s'),
            'status'      => 0,
        ]);

        return ['success' => true, 'id' => (int)$pedido->id, 'message' => 'Pedido creado correctamente'];
    }

    public static function agregarProducto(int $idPedido, string $idProducto, string $otroProducto, int $piezas, string $unidad): array
    {
        if (!$idPedido || $piezas <= 0) {
            return ['success' => false, 'message' => 'Debe seleccionar un producto y una cantidad mayor a cero'];
        }

        $valProducto = '';
        $unidadFinal = '';
        $idProductoFinal = 0;

        if ((string)$idProducto !== '' && (int)$idProducto > 0) {
            $productoCat = LimpiezaLista::find((int)$idProducto);
            if ($productoCat) {
                $valProducto = $productoCat->producto;
                $unidadFinal = $productoCat->unidad;
                $idProductoFinal = (int)$idProducto;
            }
        }

        if ($valProducto === '') {
            $valProducto = trim($otroProducto);
            $unidadFinal = trim($unidad);
            $idProductoFinal = 0;
        }

        if (trim($valProducto) === '') {
            return ['success' => false, 'message' => 'Debe seleccionar o escribir un producto'];
        }

        Capsule::beginTransaction();

        try {
            $query = PedidoLimpiezaDetalle::query()
                ->where('id_pedido', $idPedido)
                ->lockForUpdate();

            if ($idProductoFinal > 0) {
                $query->where('id_producto', $idProductoFinal);
            } else {
                $query->where('id_producto', 0)
                    ->where('producto', $valProducto)
                    ->where('unidad', $unidadFinal);
            }

            $existing = $query->first();

            if ($existing) {
                $existing->piezas = (int)$existing->piezas + $piezas;
                $existing->save();
            } else {
                PedidoLimpiezaDetalle::create([
                    'id_pedido'  => $idPedido,
                    'id_producto' => $idProductoFinal,
                    'producto'   => $valProducto,
                    'unidad'     => $unidadFinal,
                    'piezas'     => $piezas,
                ]);
            }

            Capsule::commit();
        } catch (\Throwable $e) {
            Capsule::rollBack();
            return ['success' => false, 'message' => 'Error al agregar el producto', 'error' => $e->getMessage()];
        }

        return ['success' => true, 'message' => 'Producto agregado correctamente'];
    }

    public static function editarPiezas(int $id, int $piezas): array
    {
        if ($piezas <= 0) {
            return ['success' => false, 'message' => 'La cantidad debe ser mayor a cero'];
        }

        $item = PedidoLimpiezaDetalle::find($id);
        if (!$item) {
            return ['success' => false, 'message' => 'Producto no encontrado'];
        }

        $item->piezas = $piezas;
        $item->save();

        return ['success' => true, 'message' => 'Cantidad actualizada correctamente'];
    }

    public static function eliminarItem(int $id): array
    {
        $item = PedidoLimpiezaDetalle::find($id);
        if (!$item) {
            return ['success' => false, 'message' => 'Producto no encontrado'];
        }

        $item->delete();

        return ['success' => true, 'message' => 'Producto eliminado correctamente'];
    }

    public static function finalizarPedido(int $id, int $idUsuario): array
    {
        $pedido = PedidoLimpieza::find($id);
        if (!$pedido) {
            return ['success' => false, 'message' => 'Pedido no encontrado'];
        }

        if ((int)$pedido->status !== 0) {
            return ['success' => false, 'message' => 'Solo los pedidos pendientes pueden finalizarse'];
        }

        $pedido->status = 1;
        $pedido->save();

        self::notificarFinalizarPedido($id, $idUsuario);

        return ['success' => true, 'message' => 'Pedido finalizado correctamente'];
    }

    public static function eliminarPedido(int $id, int $idUsuario): array
    {
        $pedido = PedidoLimpieza::find($id);
        if (!$pedido) {
            return ['success' => false, 'message' => 'Pedido no encontrado'];
        }

        if ((int)$pedido->status >= 2) {
            return ['success' => false, 'message' => 'No se puede eliminar un pedido firmado o entregado'];
        }

        PedidoLimpiezaDetalle::where('id_pedido', $id)->delete();
        PedidoLimpiezaToken::where('id_pedido', $id)->delete();
        PedidoLimpiezaFirma::where('id_pedido', $id)->delete();
        $pedido->delete();

        self::notificarEliminarPedido($id, $pedido->id_estacion, $pedido->fecha, $idUsuario);

        return ['success' => true, 'message' => 'Pedido eliminado correctamente'];
    }

    public static function entregarPedido(int $id, int $idUsuario): array
    {
        $pedido = PedidoLimpieza::find($id);
        if (!$pedido) {
            return ['success' => false, 'message' => 'Pedido no encontrado'];
        }

        if ((int)$pedido->status !== 2) {
            return ['success' => false, 'message' => 'Solo los pedidos firmados pueden entregarse'];
        }

        Capsule::beginTransaction();

        try {
            $detalles = PedidoLimpiezaDetalle::where('id_pedido', $id)->get();

            foreach ($detalles as $item) {
                if ((int)$item->id_producto <= 0) {
                    continue;
                }

                $inventario = InventarioLimpieza::where('id_estacion', $pedido->id_estacion)
                    ->where('id_producto', $item->id_producto)
                    ->lockForUpdate()
                    ->first();

                if ($inventario) {
                    $inventario->piezas = (int)$inventario->piezas + (int)$item->piezas;
                    $inventario->status = 1;
                    $inventario->save();
                } else {
                    InventarioLimpieza::create([
                        'id_estacion' => $pedido->id_estacion,
                        'id_producto' => $item->id_producto,
                        'piezas'      => (int)$item->piezas,
                        'status'      => 1,
                    ]);
                }
            }

            $pedido->status = 3;
            $pedido->save();

            PedidoLimpiezaToken::where('id_pedido', $id)->delete();

            Capsule::commit();
        } catch (\Throwable $e) {
            Capsule::rollBack();
            return ['success' => false, 'message' => 'Error al entregar el pedido', 'error' => $e->getMessage()];
        }

        return ['success' => true, 'message' => 'Pedido entregado e inventario actualizado correctamente'];
    }

    /* ================= TOKEN Y FIRMA VOBO ================= */

    public static function crearToken(int $id, int $idUsuario, string $via = 'telegram'): array
    {
        $pedido = PedidoLimpieza::find($id);
        if (!$pedido) {
            return ['success' => false, 'message' => 'Pedido no encontrado'];
        }

        $token = rand(100000, 999999);

        PedidoLimpiezaToken::where('id_pedido', $id)
            ->where('id_usuario', $idUsuario)
            ->delete();

        PedidoLimpiezaToken::create([
            'id_pedido'      => $id,
            'id_usuario'     => $idUsuario,
            'fecha_creacion' => Carbon::now(),
            'token'          => $token,
        ]);

        if ($via === 'email') {
            $usuario = Usuario::find($idUsuario);
            $email = $usuario->email ?? '';
            if (!$email) {
                return ['success' => false, 'message' => 'El usuario no tiene correo electrónico registrado'];
            }

            try {
                $emailService = new EmailService();
                $enviado = $emailService->sendToken($email, (string)$token, 'Pedido de Artículos de Limpieza');
            } catch (\Throwable $e) {
                error_log('Error Email token pedido limpieza: ' . $e->getMessage());
                return ['success' => false, 'message' => 'Error al enviar el correo electrónico'];
            }

            if (!$enviado) {
                return ['success' => false, 'message' => 'Error al enviar el correo electrónico'];
            }

            return ['success' => true, 'message' => 'Token enviado por correo electrónico'];
        }

        $nombreES = self::getNombreEstacion($pedido->id_estacion);

        $fechaCompleta = self::formatearFechaHora($pedido->fecha);
        $mensaje = '📲 Usa el token <b>' . $token . '</b> para firmar el "VOBO" en el Pedido de Artículos de Limpieza de No. ' . $pedido->id . ' correspondiente al dia: ' . $fechaCompleta . '.'
            . PHP_EOL . PHP_EOL . '⛽ Estación: ' . $nombreES . '.';

        try {
            $telegram = new TelegramService();
            $telegram->sendToken($idUsuario, $mensaje);
        } catch (\Throwable $e) {
            error_log('Error Telegram token pedido limpieza: ' . $e->getMessage());
        }

        return ['success' => true, 'message' => 'Token enviado por Telegram'];
    }

    public static function firmarVoBo(int $id, int $idUsuario, string $token): array
    {
        $pedido = PedidoLimpieza::find($id);
        if (!$pedido) {
            return ['success' => false, 'message' => 'Pedido no encontrado'];
        }

        $tokenRegistro = PedidoLimpiezaToken::where('id_pedido', $id)
            ->where('id_usuario', $idUsuario)
            ->where('token', (int)$token)
            ->orderBy('id', 'desc')
            ->first();

        if (!$tokenRegistro) {
            return ['success' => false, 'message' => 'El token no es válido'];
        }

        if (PedidoLimpiezaFirma::where('id_pedido', $id)->where('tipo_firma', 'B')->exists()) {
            return ['success' => false, 'message' => 'El VoBo ya fue firmado'];
        }

        if ((int)$pedido->status !== 1) {
            return ['success' => false, 'message' => 'El pedido no está en espera de firma'];
        }

        $firma = "Firma: " . bin2hex(random_bytes(64)) . "." . uniqid();

        try {
            PedidoLimpiezaFirma::create([
                'id_pedido'  => $id,
                'id_usuario' => $idUsuario,
                'fecha'      => Carbon::now(),
                'tipo_firma' => 'B',
                'firma'      => $firma,
            ]);

            $pedido->status = 2;
            $pedido->save();

            PedidoLimpiezaToken::where('id_pedido', $id)
                ->where('id_usuario', $idUsuario)
                ->delete();
        } catch (\Throwable $e) {
            error_log('Error firma VoBo pedido limpieza: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error al firmar el VoBo'];
        }

        self::notificarFirmarVoBo($id, $idUsuario);

        return ['success' => true, 'message' => 'VoBo firmado correctamente'];
    }

    /* ================= NOTIFICACIONES TELEGRAM ================= */

    private static function notificarFinalizarPedido(int $id, int $idUsuario): void
    {
        try {
            $pedido = PedidoLimpieza::find($id);
            if (!$pedido) return;

            $idEstacion = (int)$pedido->id_estacion;
            $fecha_hora = self::formatearFechaHora($pedido->fecha);

            $nombreES = self::getNombreEstacion($idEstacion);
            $nombreUsuario = self::getPersonal($idUsuario)['nombre'];

            $paraEstacion = '✅ ' . $nombreUsuario . ' finalizó el Pedido de Artículos de Limpieza en el apartado de Comercializadora.' . PHP_EOL
                . '🗓 Fecha y hora: ' . $fecha_hora
                . PHP_EOL . PHP_EOL . '⛽ Estación: ' . $nombreES . '.'
                . PHP_EOL . 'Nota: La solicitud está en espera de obtener la firma de Autorización.';

            $paraCarmen = '✅ ' . $nombreUsuario . ' finalizó el Pedido de Artículos de Limpieza en el apartado de Comercializadora.' . PHP_EOL
                . '🗓 Fecha y hora: ' . $fecha_hora
                . PHP_EOL . PHP_EOL . '⛽ Estación: ' . $nombreES . '.'
                . PHP_EOL . 'Nota: Debes firmar la Autorización de dicha solicitud.';

            $telegram = new TelegramService();
            $telegram->sendToken(self::FIRMANTE_VOBO, $paraCarmen);

            $userIds = $telegram->getUserIdsByStation($idEstacion, $idUsuario);
            if (!empty($userIds)) {
                $telegram->sendMessageToMultiple($userIds, $paraEstacion);
            }

            if ($idEstacion == 6 || $idEstacion == 7) {
                $comercializadora = $telegram->getUserIdsComercializadora($idUsuario);
                if (!empty($comercializadora)) {
                    $telegram->sendMessageToMultiple($comercializadora, $paraEstacion);
                }
            }

            $mantenimiento = $telegram->getUserIdsMantenimiento($idUsuario);
            if (!empty($mantenimiento)) {
                $telegram->sendMessageToMultiple($mantenimiento, $paraEstacion);
            }
        } catch (\Throwable $e) {
            error_log('Error Telegram finalizar pedido limpieza: ' . $e->getMessage());
        }
    }

    private static function notificarFirmarVoBo(int $id, int $idUsuario): void
    {
        try {
            $pedido = PedidoLimpieza::find($id);
            if (!$pedido) return;

            $idEstacion = (int)$pedido->id_estacion;
            $fechaCompleta = self::formatearFechaHora($pedido->fecha);
            $nombreES = self::getNombreEstacion($idEstacion);
            $nombreUsuario = self::getPersonal($idUsuario)['nombre'];

            $detalle = '✍🏻 ' . $nombreUsuario . ' firmó el <b>VOBO</b> del Pedido de Artículos de Limpieza de No. ' . $id . ' correspondiente al dia ' . $fechaCompleta
                . PHP_EOL . PHP_EOL . '⛽ Estación: ' . $nombreES . '.';

            $telegram = new TelegramService();

            $userIds = $telegram->getUserIdsByStation($idEstacion, $idUsuario);
            if (!empty($userIds)) {
                $telegram->sendMessageToMultiple($userIds, $detalle);
            }

            if ($idEstacion == 6 || $idEstacion == 7) {
                $comercializadora = $telegram->getUserIdsComercializadora($idUsuario);
                if (!empty($comercializadora)) {
                    $telegram->sendMessageToMultiple($comercializadora, $detalle);
                }
            }

            $mantenimiento = $telegram->getUserIdsMantenimiento($idUsuario);
            if (!empty($mantenimiento)) {
                $telegram->sendMessageToMultiple($mantenimiento, $detalle);
            }
        } catch (\Throwable $e) {
            error_log('Error Telegram firmar VoBo pedido limpieza: ' . $e->getMessage());
        }
    }

    private static function notificarEliminarPedido(int $id, int $idEstacion, $fecha, int $idUsuario): void
    {
        try {
            $fecha_hora = self::formatearFechaHora($fecha);

            $nombreES = self::getNombreEstacion($idEstacion);
            $nombreUsuario = self::getPersonal($idUsuario)['nombre'];

            $detalle = '🗑 ' . $nombreUsuario . ' eliminó el Pedido de Artículos de Limpieza en el apartado de Comercializadora.' . PHP_EOL
                . '🗓 Fecha y hora: ' . $fecha_hora
                . PHP_EOL . PHP_EOL . '⛽ Estación: ' . $nombreES . '.';

            $telegram = new TelegramService();

            $userIds = $telegram->getUserIdsByStation($idEstacion, $idUsuario);
            if (!empty($userIds)) {
                $telegram->sendMessageToMultiple($userIds, $detalle);
            }

            if ($idEstacion == 6 || $idEstacion == 7) {
                $comercializadora = $telegram->getUserIdsComercializadora($idUsuario);
                if (!empty($comercializadora)) {
                    $telegram->sendMessageToMultiple($comercializadora, $detalle);
                }
            }

            $mantenimiento = $telegram->getUserIdsMantenimiento($idUsuario);
            if (!empty($mantenimiento)) {
                $telegram->sendMessageToMultiple($mantenimiento, $detalle);
            }
        } catch (\Throwable $e) {
            error_log('Error Telegram eliminar pedido limpieza: ' . $e->getMessage());
        }
    }

    /* ================= REPORTES (op_limpieza_reporte) ================= */

    private static function obtenerFechaReporte($valor): ?Carbon
    {
        try {
            if ($valor instanceof \DateTimeInterface) {
                $year = (int)$valor->format('Y');
                if ($year < 2000 || $year > (int)date('Y') + 1) {
                    return null;
                }
                return Carbon::instance($valor);
            }

            $str = trim((string)($valor ?? ''));
            if ($str === '' || strpos($str, '0000-00-00') === 0 || strpos($str, '/') !== false) {
                return null;
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $str, $m)) {
                return null;
            }
            if ((int)$m[0] < 2000) {
                return null;
            }
            return Carbon::parse($str);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function esHoraReporteValida($valor): bool
    {
        $str = trim((string)($valor ?? ''));
        if ($str === '' || $str === '00:00:00') {
            return false;
        }
        return (bool)preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $str);
    }

    public static function getReportes(int $idEstacion): array
    {
        $reportes = LimpiezaReporte::with('usuario')->where('id_estacion', $idEstacion)
            ->orderBy('id', 'desc')
            ->get();

        $result = [];
        foreach ($reportes as $reporte) {
            $fechaCarbon = self::obtenerFechaReporte($reporte->fecha);
            $fechaEsValida = $fechaCarbon !== null;
            $fechaStr = $fechaEsValida ? formatearFecha($fechaCarbon) : 'Sin información';
            $horaEsValida = self::esHoraReporteValida($reporte->hora);
            $horaStr = $horaEsValida ? date('g:i a', strtotime(trim((string)$reporte->hora))) : 'Sin información';
            $detalleTexto = trim((string)$reporte->detalle) === '' ? '' : (string)$reporte->detalle;
            $statusNorm = (int)$reporte->status >= 1 ? 1 : 0;

            $result[] = [
                'id'              => (int)$reporte->id,
                'id_estacion'     => (int)$reporte->id_estacion,
                'nombre_estacion' => self::getNombreEstacion((int)$reporte->id_estacion),
                'id_usuario'      => (int)$reporte->id_usuario,
                'personal'        => $reporte->usuario ? $reporte->usuario->nombre : '',
                'puesto'          => ($reporte->usuario && $reporte->usuario->puesto) ? ($reporte->usuario->puesto->tipo_puesto ?? '') : '',
                'fecha'           => $fechaStr,
                'hora'            => $horaStr,
                'fecha_hora'      => ($fechaEsValida && $horaEsValida) ? $fechaStr . ', ' . $horaStr : 'Sin información',
                'detalle'         => $detalleTexto,
                'status'          => $statusNorm,
                'status_label'    => self::ESTATUS_REPORTE[$statusNorm] ?? 'Desconocido',
                'num_productos'   => LimpiezaReporteDetalle::where('id_reporte', $reporte->id)->count(),
                'total_unidades'  => LimpiezaReporteDetalle::where('id_reporte', $reporte->id)->sum('unidad'),
            ];
        }

        return $result;
    }

    public static function getReporte(int $id): ?array
    {
        $reporte = LimpiezaReporte::with('usuario')->find($id);
        if (!$reporte) {
            return null;
        }

        $detalle = [];
        $totalPiezas = 0;
        foreach (LimpiezaReporteDetalle::where('id_reporte', $id)->orderBy('id', 'asc')->get() as $item) {
            $producto = LimpiezaLista::find($item->id_producto);
            $totalPiezas += (int)$item->unidad;
            $detalle[] = [
                'id'            => (int)$item->id,
                'id_producto'   => (int)$item->id_producto,
                'unidad'        => $producto ? $producto->unidad : '',
                'producto'      => $producto ? $producto->producto : '',
                'piezas'        => (int)$item->unidad,
                'observaciones' => $item->observaciones ?? '',
            ];
        }

        $fechaCarbon = self::obtenerFechaReporte($reporte->fecha);
        $fechaEsValida = $fechaCarbon !== null;
        $fechaStr = $fechaEsValida ? formatearFecha($fechaCarbon) : 'Sin información';
        $horaEsValida = self::esHoraReporteValida($reporte->hora);
        $horaBase = trim((string)$reporte->hora);
        $horaStr = $horaEsValida ? date('g:i a', strtotime($horaBase)) : 'Sin información';
        $detalleTexto = trim((string)$reporte->detalle) === '' ? '' : (string)$reporte->detalle;
        $statusNorm = (int)$reporte->status >= 1 ? 1 : 0;

        return [
            'id'              => (int)$reporte->id,
            'id_estacion'     => (int)$reporte->id_estacion,
            'id_usuario'      => (int)$reporte->id_usuario,
            'personal'        => $reporte->usuario ? $reporte->usuario->nombre : '',
            'puesto'          => ($reporte->usuario && $reporte->usuario->puesto) ? ($reporte->usuario->puesto->tipo_puesto ?? '') : '',
            'fecha'           => $fechaStr,
            'hora'            => $horaStr,
            'fecha_hora'      => ($fechaEsValida && $horaEsValida) ? $fechaStr . ', ' . $horaStr : 'Sin información',
            'fecha_iso'       => $fechaEsValida ? $fechaCarbon->format('Y-m-d') : '',
            'hora_iso'        => $horaEsValida ? date('H:i', strtotime($horaBase)) : '',
            'fecha_invalida'  => !$fechaEsValida,
            'detalle'         => $detalleTexto,
            'status'          => $statusNorm,
            'status_label'    => self::ESTATUS_REPORTE[$statusNorm] ?? 'Desconocido',
            'total_piezas'    => $totalPiezas,
            'num_productos'   => count($detalle),
            'detalle_items'   => $detalle,
        ];
    }

    public static function crearReporte(int $idEstacion, int $idUsuario): array
    {
        if (!$idEstacion || !$idUsuario) {
            return ['success' => false, 'message' => 'Datos incompletos'];
        }

        $reporte = LimpiezaReporte::create([
            'id_estacion' => $idEstacion,
            'id_usuario'  => $idUsuario,
            'fecha'       => Carbon::now()->format('Y-m-d'),
            'hora'        => Carbon::now()->format('H:i:s'),
            'detalle'     => '',
            'status'      => 0,
        ]);

        return ['success' => true, 'id' => (int)$reporte->id, 'message' => 'Reporte creado correctamente'];
    }

    public static function guardarReporteDatos(int $id, string $fecha, string $hora, string $detalle): array
    {
        $reporte = LimpiezaReporte::find($id);
        if (!$reporte) {
            return ['success' => false, 'message' => 'Reporte no encontrado'];
        }

        $reporte->detalle = $detalle;

        if ($fecha !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $reporte->fecha = $fecha;
        } elseif (trim((string)$reporte->fecha) === '') {
            $reporte->fecha = Carbon::now()->format('Y-m-d');
        }

        if ($hora !== '' && preg_match('/^\d{2}:\d{2}$/', $hora)) {
            $reporte->hora = $hora . ':00';
        } elseif ($hora !== '' && preg_match('/^\d{2}:\d{2}:\d{2}$/', $hora)) {
            $reporte->hora = $hora;
        } elseif (trim((string)$reporte->hora) === '') {
            $reporte->hora = Carbon::now()->format('H:i:s');
        }

        $reporte->save();

        return ['success' => true, 'message' => 'Reporte guardado correctamente'];
    }

    public static function agregarProductoReporte(int $idReporte, int $idEstacion, int $idProducto, int $unidad, string $observaciones = ''): array
    {
        if (!$idReporte || !$idEstacion || !$idProducto || $unidad <= 0) {
            return ['success' => false, 'message' => 'Datos incompletos'];
        }

        $reporte = LimpiezaReporte::find($idReporte);
        if (!$reporte) {
            return ['success' => false, 'message' => 'Reporte no encontrado'];
        }

        $reporte->fecha = trim((string)$reporte->fecha) === '' ? Carbon::now()->format('Y-m-d') : $reporte->fecha;
        $reporte->hora = trim((string)$reporte->hora) === '' ? Carbon::now()->format('H:i:s') : $reporte->hora;
        $reporte->save();

        Capsule::beginTransaction();

        try {
            $inventario = InventarioLimpieza::where('id_estacion', $idEstacion)
                ->where('id_producto', $idProducto)
                ->lockForUpdate()
                ->first();

            $unidadDisponible = $inventario ? (int)$inventario->piezas : 0;
            if ($unidadDisponible < $unidad) {
                Capsule::rollBack();
                return ['success' => false, 'message' => 'No hay suficientes piezas en el inventario', 'error' => 2];
            }

            $detalle = LimpiezaReporteDetalle::where('id_reporte', $idReporte)
                ->where('id_producto', $idProducto)
                ->first();

            if ($detalle) {
                $detalle->unidad += $unidad;
                if ($observaciones !== '') {
                    $detalle->observaciones = $observaciones;
                }
                $detalle->save();
            } else {
                LimpiezaReporteDetalle::create([
                    'id_reporte'    => $idReporte,
                    'id_producto'   => $idProducto,
                    'unidad'        => $unidad,
                    'observaciones' => $observaciones,
                ]);
            }

            $inventario->piezas = $unidadDisponible - $unidad;
            $inventario->save();

            Capsule::commit();
        } catch (\Throwable $e) {
            Capsule::rollBack();
            return ['success' => false, 'message' => 'Error al agregar el producto', 'error' => $e->getMessage()];
        }

        return ['success' => true, 'message' => 'Producto agregado correctamente'];
    }

    public static function eliminarProductoReporte(int $idReporte, int $idEstacion, int $idItem, int $idProducto): array
    {
        $item = LimpiezaReporteDetalle::find($idItem);
        if (!$item) {
            return ['success' => false, 'message' => 'Producto no encontrado'];
        }

        Capsule::beginTransaction();

        try {
            $inventario = InventarioLimpieza::where('id_estacion', $idEstacion)
                ->where('id_producto', $idProducto)
                ->lockForUpdate()
                ->first();

            if ($inventario) {
                $inventario->piezas = (int)$inventario->piezas + (int)$item->unidad;
                $inventario->status = 1;
                $inventario->save();
            } else {
                InventarioLimpieza::create([
                    'id_estacion' => $idEstacion,
                    'id_producto' => $idProducto,
                    'piezas'      => (int)$item->unidad,
                    'status'      => 1,
                ]);
            }

            $item->delete();

            Capsule::commit();
        } catch (\Throwable $e) {
            Capsule::rollBack();
            return ['success' => false, 'message' => 'Error al eliminar el producto', 'error' => $e->getMessage()];
        }

        return ['success' => true, 'message' => 'Producto eliminado correctamente'];
    }

    public static function eliminarReporte(int $id, int $idEstacion): array
    {
        $reporte = LimpiezaReporte::find($id);
        if (!$reporte) {
            return ['success' => false, 'message' => 'Reporte no encontrado'];
        }

        Capsule::beginTransaction();

        try {
            $items = LimpiezaReporteDetalle::where('id_reporte', $id)->get();

            foreach ($items as $item) {
                $inventario = InventarioLimpieza::where('id_estacion', $idEstacion)
                    ->where('id_producto', $item->id_producto)
                    ->lockForUpdate()
                    ->first();

                if ($inventario) {
                    $inventario->piezas = (int)$inventario->piezas + (int)$item->unidad;
                    $inventario->status = 1;
                    $inventario->save();
                } else {
                    InventarioLimpieza::create([
                        'id_estacion' => $idEstacion,
                        'id_producto' => $item->id_producto,
                        'piezas'      => (int)$item->unidad,
                        'status'      => 1,
                    ]);
                }
            }

            LimpiezaReporteDetalle::where('id_reporte', $id)->delete();
            $reporte->delete();

            Capsule::commit();
        } catch (\Throwable $e) {
            Capsule::rollBack();
            return ['success' => false, 'message' => 'Error al eliminar el reporte', 'error' => $e->getMessage()];
        }

        return ['success' => true, 'message' => 'Reporte eliminado correctamente'];
    }

    public static function aprobarReporte(int $id): array
    {
        $reporte = LimpiezaReporte::find($id);
        if (!$reporte) {
            return ['success' => false, 'message' => 'Reporte no encontrado'];
        }

        $fechaCarbon = self::obtenerFechaReporte($reporte->fecha)
            ?? Carbon::now();
        $reporte->fecha = $fechaCarbon->format('Y-m-d');
        $horaEsValida = self::esHoraReporteValida($reporte->hora);
        $reporte->hora = $horaEsValida ? trim((string)$reporte->hora) : Carbon::now()->format('H:i:s');
        $reporte->status = 1;
        $reporte->save();

        return ['success' => true, 'message' => 'Reporte finalizado correctamente'];
    }

    /* ================= PDF ================= */

    public static function generarHtmlPdf(int $id): array
    {
        $pedido = PedidoLimpieza::find($id);
        if (!$pedido) {
            return ['success' => false, 'message' => 'Pedido no encontrado'];
        }

        $personal = self::getPersonal($pedido->id_personal);
        $razonSocial = self::getRazonSocialEstacion($pedido->id_estacion);
        $fecha_hora = self::formatearFechaHora($pedido->fecha);

        $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

        $rows = '';
        $num = 1;
        $totalPiezas = 0;
        foreach (PedidoLimpiezaDetalle::where('id_pedido', $id)->get() as $item) {
            $producto = LimpiezaLista::find($item->id_producto);
            $totalPiezas += (int)$item->piezas;
            $rows .= '<tr>'
                . '<td class="text-center">' . $num . '</td>'
                . '<td class="text-center">' . htmlspecialchars($producto->unidad ?? '', ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars($producto->producto ?? '', ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td class="text-center">' . (int)$item->piezas . '</td>'
                . '</tr>';
            $num++;
        }

        $firmaA = '';
        $firmaB = '';
        $firmaARow = PedidoLimpiezaFirma::where('id_pedido', $id)->where('tipo_firma', 'A')->first();
        if ($firmaARow) {
            $rutaFirma = dirname(__DIR__, 2) . '/public/uploads/firmas/pedido-articulos-limpieza/' . $firmaARow->firma;
            if (strpos($firmaARow->firma ?? '', 'Firma:') !== 0 && is_file($rutaFirma)) {
                $dataFirma = file_get_contents($rutaFirma);
                $nombreFirmante = self::getPersonal($firmaARow->id_usuario)['nombre'];
                $firmaA = '<div class="text-center" style="margin-top: 10px;"><div>' . $nombreFirmante . '</div>'
                    . '<img src="data:image/png;base64,' . base64_encode($dataFirma) . '" style="width: 200px;"></div>'
                    . '<div style="font-size: 1em; font-weight: bold; text-align: center; border-top: 1px solid #dee2e6; padding-top: 10px;">NOMBRE Y FIRMA DEL ENCARGADO</div>';
            }
        }

        $firmaBRow = PedidoLimpiezaFirma::where('id_pedido', $id)->where('tipo_firma', 'B')->first();
        if ($firmaBRow) {
            $fechaFirmaB = self::formatearFechaHora($firmaBRow->fecha);
            $nombreFirmanteB = self::getPersonal($firmaBRow->id_usuario)['nombre'];
            $firmaB = '<div class="text-center" style="margin-top: 10px;"><div>' . $nombreFirmanteB . '</div>'
                . '<div class="border-bottom text-center p-2" style="margin-top: 10px;"><small>El pedido de limpieza se firmó por un medio electrónico.<br> <b>Fecha: ' . $fechaFirmaB . '</b></small></div>'
                . '<div style="font-size: 1em; font-weight: bold; text-align: center; border-top: 1px solid #dee2e6; padding-top: 10px;">NOMBRE Y FIRMA DE VOBO</div></div>';
        }

        $html = '<html lang="es"><head><style type="text/css">
@page { margin: 0.5cm 0.5cm; }
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: .9rem; color: #212529; }
table { border-collapse: collapse; width: 100%; }
th, td { border: 1px solid #dee2e6; padding: 3px; vertical-align: middle; }
th { background: #F2F2F2; font-weight: bold; }
.text-center { text-align: center !important; }
.text-right { text-align: right !important; }
.border-bottom { border-bottom: 1px solid #dee2e6; }
.p-2 { padding: 0.5rem !important; }
</style></head><body>'
            . '<img src="' . $logo . '" width="180">'
            . '<div class="text-center" style="font-size: 1.8em;">Pedido de Artículos de Limpieza</div>'
            . '<div class="text-center" style="font-size: 1.2em; margin-top:10px; margin-bottom:10px;">' . htmlspecialchars($razonSocial, ENT_QUOTES, 'UTF-8') . '</div>'
            . '<table><tbody>'
            . '<tr><td><b>Personal</b></td><td><b>Fecha y hora</b></td></tr>'
            . '<tr><td>' . htmlspecialchars($personal['nombre'], ENT_QUOTES, 'UTF-8') . '</td><td>' . $fecha_hora . '</td></tr>'
            . '</tbody></table>'
            . '<table style="margin-top:10px;"><thead><tr>'
            . '<td class="text-center"><b>#</b></td><td class="text-center"><b>Unidad</b></td><td><b>Nombre del roducto</b></td><td class="text-center"><b>Piezas</b></td>'
            . '</tr></thead><tbody>' . $rows
            . '<tr><td colspan="3" class="text-right">Total piezas:</td><td class="text-center"><b>' . $totalPiezas . '</b></td></tr>'
            . '</tbody></table>'
            . '<table style="width: 100%; margin-top: 20px;"><tr><td class="p-2">' . $firmaA . '</td><td class="p-2">' . $firmaB . '</td></tr></table>'
            . '</body></html>';

        return [
            'success' => true,
            'html'    => $html,
            'nombre'  => 'Pedido de Artículos de Limpieza ' . $razonSocial . '.pdf',
        ];
    }

}