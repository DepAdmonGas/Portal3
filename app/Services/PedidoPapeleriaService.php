<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Operativo\PedidoPapeleria;
use App\Models\Operativo\PedidoPapeleriaDetalle;
use App\Models\Operativo\PedidoPapeleriaFirma;
use App\Models\Operativo\PedidoPapeleriaToken;
use App\Models\Operativo\PapeleriaLista;
use App\Models\Operativo\InventarioPapeleria;
use App\Models\Operativo\ReportePapeleria;
use App\Models\Operativo\ReportePapeleriaDetalle;
use App\Models\Operativo\RhLocalidad;
use Illuminate\Database\Capsule\Manager as Capsule;
use Carbon\Carbon;

class PedidoPapeleriaService
{

    public const MODULE_KEY = 'pedido-papeleria';
    public const MODULO_PADRE_CLAVE = 'comercializadora';

    public const FIRMANTE_VOBO = 19;

    public const PUESTO_ENCARGADO = 'Encargado';
    public const PUESTO_ASISTENTE_ADMIN = 'Asistente Administrativo';

    public const ESTATUS_PEDIDO = [
        0 => 'Pendiente',
        1 => 'En proceso',
        2 => 'Finalizado',
    ];

    public const ESTATUS_REPORTE = [
        0 => 'Pendiente',
        1 => 'Finalizado',
    ];

    public const ID_ESTACION_DEPARTAMENTOS = 8;

    public const ID_ESTACION_PALO_SOLO = 2;

    public const ID_PUESTO_GESTORIA = 5;

    public const ID_PUESTO_DIRECCION_OPERACIONES = 13;

    public const ID_DEPTO_GESTION = 13;

    public const ID_DEPTO_DIRECCION_OPERACIONES = 11;

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
        $tieneSubmenuPapeleria = false;

        if (!empty($permisosDb['submenus'])) {
            foreach ($permisosDb['submenus'] as $sub) {
                if (($sub['clave'] ?? '') === 'pedido-papeleria') {
                    $tieneSubmenuPapeleria = true;
                    break;
                }
            }
        }

        $esEncargado = in_array($nombrePuesto, [
            self::PUESTO_ENCARGADO,
            self::PUESTO_ASISTENTE_ADMIN,
        ], true);

        $esGestoriaOficina = $idEstacion === self::ID_ESTACION_DEPARTAMENTOS && $idPuesto === self::ID_PUESTO_GESTORIA;
        $esDireccionOficina = $idEstacion === self::ID_ESTACION_DEPARTAMENTOS && $idPuesto === self::ID_PUESTO_DIRECCION_OPERACIONES;

        return [
            'id_usuario'       => $idUsuario,
            'id_estacion'      => $idEstacion,
            'id_puesto'        => $idPuesto,
            'nombre_puesto'    => $nombrePuesto,
            'nombre_usuario'   => $nombreUsuario,
            'multiestacion'    => $multiestacion,
            'esGestoriaOficina'  => $esGestoriaOficina,
            'esDireccionOficina' => $esDireccionOficina,
            'tieneSubmenu'     => $tieneSubmenuPapeleria,
            'puedeLeer'        => $tienePermisoLeer || $tieneSubmenuPapeleria,
            'puedeAcceso'      => $tienePermisoLeer || $tieneSubmenuPapeleria,
            'puedeCrear'       => $tienePermisoCrear,
            'puedeEditar'      => $tienePermisoEditar,
            'puedeEliminar'    => $tienePermisoEliminar,
            'puedeDescargar'   => $tienePermisoDescargar,
            'esEncargado'      => $esEncargado,
            'esFirmanteVOBO'   => $idUsuario === self::FIRMANTE_VOBO,
            'puedeFirmarVoBo'  => $idUsuario === self::FIRMANTE_VOBO,
        ];
    }

    /* ================= CONTEXTO ESTACIÓN / DEPARTAMENTO ================= */

    public static function estacionDesdeLocalidad(int $idLocalidad): ?int
    {
        $convertidos = MultiestacionService::convertIds(
            [$idLocalidad],
            MultiestacionService::TABLA_RH_LOCALIDADES,
            MultiestacionService::TABLA_ESTACIONES
        );
        return !empty($convertidos) ? (int)$convertidos[0] : null;
    }

    public static function getAllowedStationIds(): array
    {
        $ids = [];
        foreach (ModuleStationService::getAvailableStations(self::MODULE_KEY) as $s) {
            $converted = self::estacionDesdeLocalidad((int)$s['id']);
            if ($converted !== null) {
                $ids[] = $converted;
            }
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

    public static function getDireccionEstacion(int $idEstacion): string
    {
        $estacion = Estacion::find($idEstacion);
        return $estacion ? ($estacion->direccioncompleta ?? '') : '';
    }

    public static function getNombreDepartamento(int $idDepto): string
    {
        $localidad = RhLocalidad::find($idDepto);
        return $localidad ? $localidad->localidad : '';
    }

    public static function getNombreContextoEstacion(int $idEstacion, int $idDepto = 0): string
    {
        if ($idDepto > 0) {
            return self::getNombreDepartamento($idDepto);
        }
        return self::getNombreEstacion($idEstacion);
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

    public static function getContextoEfectivo(): array
    {
        $permisos = self::getPermisos();
        if (!empty($permisos['esGestoriaOficina'])) {
            return ['id_estacion' => 0, 'id_depto' => self::ID_DEPTO_GESTION];
        }
        return ModuleStationService::getContext(self::MODULE_KEY);
    }

    public static function getPendingCountsActual(): int
    {
        $ctx = self::getContextoEfectivo();
        $idEstacion = $ctx['id_estacion'];
        $idDepto = $ctx['id_depto'];

        $q = PedidoPapeleria::query()
            ->where('status', '>', 0)
            ->where('status', '<=', 1);

        if ($idDepto) {
            $q->where('id_estacion', self::ID_ESTACION_DEPARTAMENTOS)
                ->where('depto', $idDepto);
        } elseif ($idEstacion) {
            $estacionId = self::estacionDesdeLocalidad($idEstacion);
            if ($estacionId) {
                $q->where('id_estacion', $estacionId);
            }
        } else {
            $allowed = self::getAllowedStationIds();
            $q->where(function ($sub) use ($allowed) {
                $sub->whereIn('id_estacion', $allowed);
                $sub->orWhere('id_estacion', self::ID_ESTACION_DEPARTAMENTOS);
            });
        }

        return (int)$q->count();
    }

    public static function getPendingCountsFlat(): array
    {
        $pendientes = ['total' => 0];
        $ids = [];
        $mapaEstacionALocalidad = [];

        foreach (ModuleStationService::getAvailableStations(self::MODULE_KEY) as $s) {
            $rhId = (int)$s['id'];
            $ids[] = $rhId;
            $pendientes['estacion_' . $rhId] = 0;
        }

        if (!empty($ids)) {
            $convertidos = MultiestacionService::convertIds(
                $ids,
                MultiestacionService::TABLA_RH_LOCALIDADES,
                MultiestacionService::TABLA_ESTACIONES
            );

            foreach ($ids as $i => $rhId) {
                $estId = isset($convertidos[$i]) ? (int)$convertidos[$i] : 0;
                if ($estId) {
                    $mapaEstacionALocalidad[$estId] = $rhId;
                }
            }

            if (!empty($mapaEstacionALocalidad)) {
                $rows = PedidoPapeleria::query()
                    ->selectRaw('id_estacion, COUNT(*) as total')
                    ->whereIn('id_estacion', array_keys($mapaEstacionALocalidad))
                    ->where('status', '>', 0)
                    ->where('status', '<=', 1)
                    ->groupBy('id_estacion')
                    ->get();

                foreach ($rows as $row) {
                    $key = 'estacion_' . $mapaEstacionALocalidad[(int)$row->id_estacion];
                    $pendientes[$key] = (int)$row->total;
                    $pendientes['total'] += (int)$row->total;
                }
            }
        }

        foreach (ModuleStationService::getAvailableDepartments(self::MODULE_KEY) as $d) {
            $idDepto = (int)$d['id'];
            $pendientes['depto_' . $idDepto] = (int)PedidoPapeleria::query()
                ->where('id_estacion', self::ID_ESTACION_DEPARTAMENTOS)
                ->where('depto', $idDepto)
                ->where('status', '>', 0)
                ->where('status', '<=', 1)
                ->count();
            $pendientes['total'] += $pendientes['depto_' . $idDepto];
        }

        return $pendientes;
    }

    /* ================= CATÁLOGO (op_papeleria_lista) ================= */

    public static function getCatalogos(bool $soloActivos = false): array
    {
        $q = PapeleriaLista::query()->orderBy('producto', 'asc');
        if ($soloActivos) {
            $q->where('estatus', 1);
        }
        return $q->get()->map(function ($p) {
            return [
                'id'       => (int)$p->id,
                'producto' => $p->producto ?? '',
                'estatus'  => (int)$p->estatus,
            ];
        })->all();
    }

    public static function guardarProducto(int $id, string $producto): array
    {
        if (trim($producto) === '') {
            return ['success' => false, 'message' => 'Campos obligatorios faltantes'];
        }

        if ($id === 0) {
            PapeleriaLista::create([
                'producto' => $producto,
                'estatus'  => 1,
            ]);
            return ['success' => true, 'message' => 'Producto agregado correctamente'];
        }

        $item = PapeleriaLista::find($id);
        if (!$item) {
            return ['success' => false, 'message' => 'Producto no encontrado'];
        }

        $item->producto = $producto;
        $item->save();

        return ['success' => true, 'message' => 'Producto actualizado correctamente'];
    }

    public static function eliminarProducto(int $id): array
    {
        $item = PapeleriaLista::find($id);
        if (!$item) {
            return ['success' => false, 'message' => 'Producto no encontrado'];
        }

        $item->estatus = 0;
        $item->save();

        return ['success' => true, 'message' => 'Producto eliminado correctamente'];
    }

    /* ================= INVENTARIO (op_inventario_papeleria) ================= */

    public static function getInventario(int $idEstacion): array
    {
        $rows = InventarioPapeleria::query()
            ->where('id_estacion', $idEstacion)
            ->where('status', 1)
            ->orderBy('id', 'asc')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $producto = PapeleriaLista::find($row->id_producto);
            $result[] = [
                'id'            => (int)$row->id,
                'id_producto'   => (int)$row->id_producto,
                'producto'      => $producto ? $producto->producto : '',
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

        $inventario = InventarioPapeleria::where('id_estacion', $idEstacion)
            ->where('id_producto', $idProducto)
            ->first();

        if (!$inventario) {
            InventarioPapeleria::create([
                'id_estacion' => $idEstacion,
                'id_producto' => $idProducto,
                'piezas'      => $piezas,
                'status'      => 1,
            ]);
        } else {
            $inventario->piezas += $piezas;
            $inventario->status = 1;
            $inventario->save();
        }

        return ['success' => true, 'message' => 'Inventario actualizado correctamente'];
    }

    public static function eliminarInventarioItem(int $id): array
    {
        $item = InventarioPapeleria::find($id);
        if (!$item) {
            return ['success' => false, 'message' => 'Registro no encontrado'];
        }

        $item->status = 0;
        $item->save();

        return ['success' => true, 'message' => 'Item eliminado correctamente'];
    }

    /* ================= PEDIDOS (op_pedido_papeleria) ================= */

    public static function getPedidosFiltro(array $filtro = []): array
    {
        $query = PedidoPapeleria::query();

        if (!empty($filtro['id_depto'])) {
            $query->where('id_estacion', self::ID_ESTACION_DEPARTAMENTOS)
                ->where('depto', (int)$filtro['id_depto']);
        } elseif (!empty($filtro['id_estacion'])) {
            $query->where('id_estacion', (int)$filtro['id_estacion']);
        }

        $pedidos = $query->orderBy('id', 'desc')->get();

        $result = [];
        foreach ($pedidos as $pedido) {
            $personal = self::getPersonal($pedido->id_personal);

            $totalPiezas = PedidoPapeleriaDetalle::where('id_pedido', $pedido->id)
                ->sum('piezas');
            $totalPiezas = (int)$totalPiezas;

            $firmaB = PedidoPapeleriaFirma::where('id_pedido', $pedido->id)
                ->where('tipo_firma', 'B')
                ->exists();

            $result[] = [
                'id'            => (int)$pedido->id,
                'id_estacion'   => (int)$pedido->id_estacion,
                'depto'         => (int)$pedido->depto,
                'nombre_estacion' => self::getNombreContextoEstacion((int)$pedido->id_estacion, (int)$pedido->depto),
                'id_personal'   => (int)$pedido->id_personal,
                'personal'      => $personal['nombre'],
                'puesto'        => $personal['puesto'],
                'fecha' => $pedido->fecha ? formatearFecha($pedido->fecha) : '',
                'fecha_formateada' => $pedido->fecha ? formatearFecha($pedido->fecha) . ', ' . date('g:i a', strtotime($pedido->fecha)) : '',
                'fecha_hora' => $pedido->fecha ? formatearFecha($pedido->fecha) . ', ' . date('g:i a', strtotime($pedido->fecha)) : '',
                'total_piezas'  => $totalPiezas,
                'num_productos' => PedidoPapeleriaDetalle::where('id_pedido', $pedido->id)->count(),
                'status'        => (int)$pedido->status,
                'status_label'  => self::ESTATUS_PEDIDO[(int)$pedido->status] ?? 'Desconocido',
                'tiene_firma_b' => $firmaB,
            ];
        }

        return $result;
    }

    public static function getPedido(int $id): ?array
    {
        $pedido = PedidoPapeleria::with('detalle')->find($id);
        if (!$pedido) {
            return null;
        }

        $personal = self::getPersonal($pedido->id_personal);

        $firmas = [];
        foreach (PedidoPapeleriaFirma::where('id_pedido', $id)->get() as $firma) {
            $tipo = $firma->tipo_firma;
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
                'firma'          => $firma->firma ?? 'S/I',
                'firma_texto'    => $firma->firma ?? 'S/I',
                'firma_img_url'  => '',
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
            $detalle[] = [
                'id'      => (int)$item->id,
                'num'     => $num++,
                'producto' => $item->producto ?? '',
                'piezas'  => (int)$item->piezas,
            ];
        }

        return [
            'id'            => (int)$pedido->id,
            'id_estacion'   => (int)$pedido->id_estacion,
            'depto'         => (int)$pedido->depto,
            'nombre_estacion' => self::getNombreContextoEstacion((int)$pedido->id_estacion, (int)$pedido->depto),
            'id_personal'   => (int)$pedido->id_personal,
            'personal'      => $personal['nombre'],
            'puesto'        => $personal['puesto'],
            'fecha' => $pedido->fecha ? formatearFecha($pedido->fecha) : '',
            'fecha_formateada' => $pedido->fecha ? formatearFecha($pedido->fecha) . ', ' . date('g:i a', strtotime($pedido->fecha)) : '',
            'fecha_hora' => $pedido->fecha ? formatearFecha($pedido->fecha) . ', ' . date('g:i a', strtotime($pedido->fecha)) : '',
            'total_piezas'  => $totalPiezas,
            'num_productos' => count($detalle),
            'status'        => (int)$pedido->status,
            'status_label'  => self::ESTATUS_PEDIDO[(int)$pedido->status] ?? 'Desconocido',
            'detalle'       => $detalle,
            'firmas'        => $firmas,
        ];
    }

    public static function crearPedido(int $idEstacion, int $idUsuario, int $idDepto = 0): array
    {
        if (!$idUsuario) {
            return ['success' => false, 'message' => 'Datos incompletos'];
        }

        if ($idDepto > 0) {
            $estacionId = self::ID_ESTACION_DEPARTAMENTOS;
        } else {
            if (!$idEstacion) {
                return ['success' => false, 'message' => 'Datos incompletos'];
            }
            $estacionId = self::estacionDesdeLocalidad($idEstacion);
            if ($estacionId === null) {
                return ['success' => false, 'message' => 'No fue posible resolver la estación'];
            }
        }

        $pedido = PedidoPapeleria::create([
            'id_estacion'   => $estacionId,
            'depto'         => $idDepto > 0 ? $idDepto : 0,
            'id_personal'   => $idUsuario,
            'fecha'         => Carbon::now()->format('Y-m-d H:i:s'),
            'status'        => 0,
        ]);

        return ['success' => true, 'id' => (int)$pedido->id, 'message' => 'Pedido creado correctamente'];
    }

    public static function agregarProducto(int $idPedido, string $idProducto, string $otroProducto, int $piezas): array
    {
        if (!$idPedido || $piezas <= 0) {
            return ['success' => false, 'message' => 'Datos incompletos'];
        }

        $valProducto = '';

        if ((string)$idProducto !== '') {
            $productoCat = PapeleriaLista::find((int)$idProducto);
            if ($productoCat) {
                $valProducto = $productoCat->producto;
            }
        }

        if ($valProducto === '') {
            $valProducto = $otroProducto;
        }

        if (trim($valProducto) === '') {
            return ['success' => false, 'message' => 'Debe seleccionar un producto'];
        }

        $existing = PedidoPapeleriaDetalle::where('id_pedido', $idPedido)
            ->where('producto', $valProducto)
            ->first();

        if ($existing) {
            $existing->piezas += $piezas;
            $existing->save();
        } else {
            PedidoPapeleriaDetalle::create([
                'id_pedido' => $idPedido,
                'producto'  => $valProducto,
                'piezas'    => $piezas,
            ]);
        }

        return ['success' => true, 'message' => 'Producto agregado correctamente'];
    }

    public static function editarPiezas(int $id, int $piezas): array
    {
        if ($piezas <= 0) {
            return ['success' => false, 'message' => 'La cantidad debe ser mayor a cero'];
        }

        $item = PedidoPapeleriaDetalle::find($id);
        if (!$item) {
            return ['success' => false, 'message' => 'Producto no encontrado'];
        }

        $item->piezas = $piezas;
        $item->save();

        return ['success' => true, 'message' => 'Cantidad actualizada correctamente'];
    }

    public static function eliminarItem(int $id): array
    {
        $item = PedidoPapeleriaDetalle::find($id);
        if (!$item) {
            return ['success' => false, 'message' => 'Producto no encontrado'];
        }

        $item->delete();

        return ['success' => true, 'message' => 'Producto eliminado correctamente'];
    }

    public static function finalizarPedido(int $id, int $idUsuario): array
    {
        $pedido = PedidoPapeleria::find($id);
        if (!$pedido) {
            return ['success' => false, 'message' => 'Pedido no encontrado'];
        }

        if ((int)$pedido->status > 0) {
            return ['success' => false, 'message' => 'El pedido ya fue finalizado'];
        }

        $pedido->status = 1;
        $pedido->save();

        self::notificarFinalizarPedido($id, $idUsuario);

        return ['success' => true, 'message' => 'Pedido finalizado correctamente'];
    }

    public static function eliminarPedido(int $id, int $idUsuario): array
    {
        $pedido = PedidoPapeleria::find($id);
        if (!$pedido) {
            return ['success' => false, 'message' => 'Pedido no encontrado'];
        }

        PedidoPapeleriaDetalle::where('id_pedido', $id)->delete();
        $pedido->delete();

        self::notificarEliminarPedido($id, $pedido->id_estacion, (int)$pedido->depto, $pedido->fecha, $idUsuario);

        return ['success' => true, 'message' => 'Pedido eliminado correctamente'];
    }

    /* ================= TOKEN Y FIRMA VOBO ================= */

    public static function crearToken(int $id, int $idUsuario, string $via = 'telegram'): array
    {
        $pedido = PedidoPapeleria::find($id);
        if (!$pedido) {
            return ['success' => false, 'message' => 'Pedido no encontrado'];
        }

        $token = rand(100000, 999999);

        PedidoPapeleriaToken::where('id_pedido', $id)
            ->where('id_usuario', $idUsuario)
            ->delete();

        PedidoPapeleriaToken::create([
            'id_pedido'     => $id,
            'id_usuario'    => $idUsuario,
            'fecha_creacion' => Carbon::now(),
            'token'         => $token,
        ]);

        if ($via === 'email') {
            $usuario = Usuario::find($idUsuario);
            $email = $usuario->email ?? '';
            if (!$email) {
                return ['success' => false, 'message' => 'El usuario no tiene correo electrónico registrado'];
            }

            try {
                $emailService = new EmailService();
                $enviado = $emailService->sendToken($email, (string)$token, 'Pedido de Papelería');
            } catch (\Throwable $e) {
                error_log('Error Email token pedido papeleria: ' . $e->getMessage());
                return ['success' => false, 'message' => 'Error al enviar el correo electrónico'];
            }

            if (!$enviado) {
                return ['success' => false, 'message' => 'Error al enviar el correo electrónico'];
            }

            return ['success' => true, 'message' => 'Token enviado por correo electrónico'];
        }

        $nombreES = self::getNombreContextoEstacion($pedido->id_estacion, (int)$pedido->depto);

        $fechaCompleta  = $pedido->fecha ? formatearFecha($pedido->fecha) . ', ' . date('g:i a', strtotime($pedido->fecha)) : '';
        $mensaje = '📲 Usa el token <b>' . $token . '</b> para firmar el "VOBO" en el Pedido de Papelería de No. ' . $id . ' correspondiente al dia: ' . $fechaCompleta . '.'
            . PHP_EOL . PHP_EOL . '⛽ Estación: ' . $nombreES . '.';

        try {
            $telegram = new TelegramService();
            $telegram->sendToken($idUsuario, $mensaje);
        } catch (\Throwable $e) {
            error_log('Error Telegram token pedido papeleria: ' . $e->getMessage());
        }

        return ['success' => true, 'message' => 'Token enviado por Telegram'];
    }

    public static function firmarVoBo(int $id, int $idUsuario, string $token): array
    {
        $pedido = PedidoPapeleria::find($id);
        if (!$pedido) {
            return ['success' => false, 'message' => 'Pedido no encontrado'];
        }

        $tokenRegistro = PedidoPapeleriaToken::where('id_pedido', $id)
            ->where('id_usuario', $idUsuario)
            ->where('token', (int)$token)
            ->orderBy('id', 'desc')
            ->first();

        if (!$tokenRegistro) {
            return ['success' => false, 'message' => 'El token no es válido'];
        }

        if (PedidoPapeleriaFirma::where('id_pedido', $id)->where('tipo_firma', 'B')->exists()) {
            return ['success' => false, 'message' => 'El VoBo ya fue firmado'];
        }

        $firma = "Firma: " . bin2hex(random_bytes(64)) . "." . uniqid();

        PedidoPapeleriaFirma::create([
            'id_pedido'  => $id,
            'id_usuario' => $idUsuario,
            'fecha'      => Carbon::now(),
            'tipo_firma' => 'B',
            'firma'      => $firma,
        ]);

        $pedido->status = 2;
        $pedido->save();

        PedidoPapeleriaToken::where('id_pedido', $id)
            ->where('id_usuario', $idUsuario)
            ->delete();

        self::notificarFirmarVoBo($id, $idUsuario);

        return ['success' => true, 'message' => 'VoBo firmado correctamente'];
    }

    /* ================= NOTIFICACIONES TELEGRAM ================= */

    private static function notificarFinalizarPedido(int $id, int $idUsuario): void
    {
        try {
            $pedido = PedidoPapeleria::find($id);
            if (!$pedido) return;

            $idEstacion = (int)$pedido->id_estacion;
            $fecha_hora = $pedido->fecha ? formatearFecha($pedido->fecha) . ', ' . date('g:i a', strtotime($pedido->fecha)) : '';

            $nombreES = self::getNombreContextoEstacion($idEstacion, (int)$pedido->depto);
            $nombreUsuario = self::getPersonal($idUsuario)['nombre'];

$paraEstacion = '✅ Se ha <b>finalizado</b> el <b>Pedido de Papelería</b> en el apartado de <b>Comercializadora</b>:' . PHP_EOL . PHP_EOL
    . '🗓 <b>Fecha y hora:</b> ' . $fecha_hora . PHP_EOL
    . '📌 <b>Nota:</b> La solicitud está en espera de obtener la firma de Autorización.' . PHP_EOL . PHP_EOL
    . '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL
    . '⛽ <b>Estación:</b> ' . $nombreES;

$paraCarmen = '✅ Se ha <b>finalizado</b> el <b>Pedido de Papelería</b> en el apartado de <b>Comercializadora</b>:' . PHP_EOL . PHP_EOL
    . '🗓 <b>Fecha y hora:</b> ' . $fecha_hora . PHP_EOL
    . '📌 <b>Nota:</b> Debes firmar la Autorización de dicha solicitud.' . PHP_EOL . PHP_EOL
    . '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL
    . '⛽ <b>Estación:</b> ' . $nombreES;

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
        } catch (\Throwable $e) {
            error_log('Error Telegram finalizar pedido papeleria: ' . $e->getMessage());
        }
    }

    private static function notificarFirmarVoBo(int $id, int $idUsuario): void
    {
        try {
            $pedido = PedidoPapeleria::find($id);
            if (!$pedido) return;

            $idEstacion = (int)$pedido->id_estacion;
        $fechaCompleta  = $pedido->fecha ? formatearFecha($pedido->fecha) . ', ' . date('g:i a', strtotime($pedido->fecha)) : '';
            $nombreES = self::getNombreContextoEstacion($idEstacion, (int)$pedido->depto);
            $nombreUsuario = self::getPersonal($idUsuario)['nombre'];

            $detalle = '✍🏻 ' . $nombreUsuario . ' firmó el <b>VOBO</b> del Pedido de Papelería de No. ' . $id . ' correspondiente al dia ' . $fechaCompleta
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
        } catch (\Throwable $e) {
            error_log('Error Telegram firmar VoBo pedido papeleria: ' . $e->getMessage());
        }
    }

    private static function notificarEliminarPedido(int $id, int $idEstacion, int $idDepto, $fecha, int $idUsuario): void
    {
        try {

            $fecha_hora = $fecha ? formatearFecha($fecha) . ', ' . date('g:i a', strtotime($fecha)) : '';

            $nombreES = self::getNombreContextoEstacion($idEstacion, $idDepto);
            $nombreUsuario = self::getPersonal($idUsuario)['nombre'];

          $detalle = '🗑 Se ha <b>eliminado</b> el <b>Pedido de Papelería</b> en el apartado de <b>Comercializadora</b>:' . PHP_EOL . PHP_EOL
    . '🗓 <b>Fecha y hora:</b> ' . $fecha_hora . PHP_EOL . PHP_EOL
    . '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL
    . '⛽ <b>Estación:</b> ' . $nombreES;

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
        } catch (\Throwable $e) {
            error_log('Error Telegram eliminar pedido papeleria: ' . $e->getMessage());
        }
    }

    /* ================= REPORTES (op_papeleria_reporte) ================= */

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
        $reportes = ReportePapeleria::with('usuario')->where('id_estacion', $idEstacion)
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
                'fecha'           => $fechaStr,
                'hora'            => $horaStr,
                'fecha_hora'      => ($fechaEsValida && $horaEsValida) ? $fechaStr . ', ' . $horaStr : 'Sin información',
                'detalle'         => $detalleTexto,
                'status'          => $statusNorm,
                'status_label'    => self::ESTATUS_REPORTE[$statusNorm] ?? 'Desconocido',
                'num_productos'   => ReportePapeleriaDetalle::where('id_reporte', $reporte->id)->count(),
                'total_unidades'  => ReportePapeleriaDetalle::where('id_reporte', $reporte->id)->sum('unidad'),
            ];
        }

        return $result;
    }

    public static function getReporte(int $id): ?array
    {
        $reporte = ReportePapeleria::with('usuario')->find($id);
        if (!$reporte) {
            return null;
        }

        $detalle = [];
        foreach (ReportePapeleriaDetalle::where('id_reporte', $id)->orderBy('id', 'asc')->get() as $item) {
            $producto = PapeleriaLista::find($item->id_producto);
            $detalle[] = [
                'id'            => (int)$item->id,
                'id_producto'   => (int)$item->id_producto,
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
            'fecha'           => $fechaStr,
            'hora'            => $horaStr,
            'fecha_hora'      => ($fechaEsValida && $horaEsValida) ? $fechaStr . ', ' . $horaStr : 'Sin información',
            'fecha_iso'       => $fechaEsValida ? $fechaCarbon->format('Y-m-d') : '',
            'hora_iso'        => $horaEsValida ? date('H:i', strtotime($horaBase)) : '',
            'fecha_invalida'  => !$fechaEsValida,
            'detalle'         => $detalleTexto,
            'status'          => $statusNorm,
            'status_label'    => self::ESTATUS_REPORTE[$statusNorm] ?? 'Desconocido',
            'detalle_items'   => $detalle,
        ];
    }

    public static function crearReporte(int $idEstacion, int $idUsuario): array
    {
        if (!$idEstacion || !$idUsuario) {
            return ['success' => false, 'message' => 'Datos incompletos'];
        }

        $reporte = ReportePapeleria::create([
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
        $reporte = ReportePapeleria::find($id);
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

        $reporte = ReportePapeleria::find($idReporte);
        if (!$reporte) {
            return ['success' => false, 'message' => 'Reporte no encontrado'];
        }

        $reporte->fecha = trim((string)$reporte->fecha) === '' ? Carbon::now()->format('Y-m-d') : $reporte->fecha;
        $reporte->hora = trim((string)$reporte->hora) === '' ? Carbon::now()->format('H:i:s') : $reporte->hora;
        $reporte->save();

        $inventario = InventarioPapeleria::where('id_estacion', $idEstacion)
            ->where('id_producto', $idProducto)
            ->first();

        $unidadDisponible = $inventario ? (int)$inventario->piezas : 0;
        if ($unidadDisponible < $unidad) {
            return ['success' => false, 'message' => 'No hay suficientes piezas en el inventario', 'error' => 2];
        }

        Capsule::beginTransaction();

        try {
            $detalle = ReportePapeleriaDetalle::where('id_reporte', $idReporte)
                ->where('id_producto', $idProducto)
                ->first();

            if ($detalle) {
                $detalle->unidad += $unidad;
                if ($observaciones !== '') {
                    $detalle->observaciones = $observaciones;
                }
                $detalle->save();
            } else {
                ReportePapeleriaDetalle::create([
                    'id_reporte'    => $idReporte,
                    'id_producto'   => $idProducto,
                    'unidad'        => $unidad,
                    'observaciones' => $observaciones,
                ]);
            }

            $inventario->piezas -= $unidad;
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
        $item = ReportePapeleriaDetalle::find($idItem);
        if (!$item) {
            return ['success' => false, 'message' => 'Producto no encontrado'];
        }

        Capsule::beginTransaction();

        try {
            $inventario = InventarioPapeleria::where('id_estacion', $idEstacion)
                ->where('id_producto', $idProducto)
                ->first();

            if ($inventario) {
                $inventario->piezas += (int)$item->unidad;
                $inventario->save();
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
        $reporte = ReportePapeleria::find($id);
        if (!$reporte) {
            return ['success' => false, 'message' => 'Reporte no encontrado'];
        }

        Capsule::beginTransaction();

        try {
            $items = ReportePapeleriaDetalle::where('id_reporte', $id)->get();

            foreach ($items as $item) {
                $inventario = InventarioPapeleria::where('id_estacion', $idEstacion)
                    ->where('id_producto', $item->id_producto)
                    ->first();

                if ($inventario) {
                    $inventario->piezas += (int)$item->unidad;
                    $inventario->status = 1;
                    $inventario->save();
                } else {
                    InventarioPapeleria::create([
                        'id_estacion' => $idEstacion,
                        'id_producto' => $item->id_producto,
                        'piezas'      => (int)$item->unidad,
                        'status'      => 1,
                    ]);
                }
            }

            ReportePapeleriaDetalle::where('id_reporte', $id)->delete();
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
        $reporte = ReportePapeleria::find($id);
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
        $pedido = PedidoPapeleria::find($id);
        if (!$pedido) {
            return ['success' => false, 'message' => 'Pedido no encontrado'];
        }

        $personal = self::getPersonal($pedido->id_personal);
        $razonSocial = self::getRazonSocialEstacion($pedido->id_estacion);
        $fecha_hora = $pedido->fecha ? formatearFecha($pedido->fecha) . ', ' . date('g:i a', strtotime($pedido->fecha)) : '';

        $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

        $rows = '';
        $num = 1;
        $totalPiezas = 0;
        foreach (PedidoPapeleriaDetalle::where('id_pedido', $id)->get() as $item) {
            $totalPiezas += (int)$item->piezas;
            $rows .= '<tr>'
                . '<td class="text-center">' . $num . '</td>'
                . '<td>' . htmlspecialchars($item->producto ?? '', ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td class="text-center">' . (int)$item->piezas . '</td>'
                . '</tr>';
            $num++;
        }

        $firmaB = '';
        $firmaBRow = PedidoPapeleriaFirma::where('id_pedido', $id)->where('tipo_firma', 'B')->first();
        if ($firmaBRow) {
            $fechaFirmaB  = $firmaBRow->fecha ? formatearFecha($firmaBRow->fecha) . ', ' . date('g:i a', strtotime($firmaBRow->fecha)) : '';
            $nombreFirmanteB = self::getPersonal($firmaBRow->id_usuario)['nombre'];
            $firmaB = '<div class="text-center" style="margin-top: 10px;"><div>' . $nombreFirmanteB . '</div>'
                . '<div class="border-bottom text-center p-2" style="margin-top: 10px;"><small>El pedido de papelería se firmó por un medio electrónico.<br> <b>Fecha: ' . $fechaFirmaB . '</b></small></div>'
                . '<div style="font-size: 1em; font-weight: bold; text-align: center; border-top: 1px solid #dee2e6; padding-top: 10px;">NOMBRE Y FIRMA DE VOBO</div></div>';
        }

        $puesto = $personal['puesto'];
        $direccion = (in_array($puesto, ['Gestoria', 'Dirección de operaciones', 'Dirección de operaciones servicio social'], true))
            ? 'Carretera Rio Hondo-Huixquilucan #401,San Bartolomé Coatepec, Huixquilucan, Estado de México, C.P. 52796'
            : self::getDireccionEstacion($pedido->id_estacion);

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
            . '<div class="text-center" style="font-size: 1.8em;">Pedido de papelería</div>'
            . '<div class="text-center" style="font-size: 1.2em; margin-top:10px; margin-bottom:10px;">' . htmlspecialchars($razonSocial, ENT_QUOTES, 'UTF-8') . '</div>'
            . '<table><tbody>'
            . '<tr><td><b>Departamento</b></td><td><b>Personal</b></td><td><b>Fecha y hora</b></td></tr>'
            . '<tr><td>' . htmlspecialchars($personal['puesto'], ENT_QUOTES, 'UTF-8') . '</td><td>' . htmlspecialchars($personal['nombre'], ENT_QUOTES, 'UTF-8') . '</td><td>' . $fecha_hora . '</td></tr>'
            . '</tbody></table>'
            . '<table style="margin-top:10px;"><thead><tr>'
            . '<td class="text-center"><b>#</b></td><td><b>Producto</b></td><td class="text-center"><b>Piezas</b></td>'
            . '</tr></thead><tbody>' . $rows
            . '<tr><td colspan="2" class="text-right">Total piezas:</td><td class="text-center"><b>' . $totalPiezas . '</b></td></tr>'
            . '</tbody></table>'
            . '<table style="width: 100%; margin-top: 20px;"><tr><td class="p-2">' . $firmaB . '</td></tr></table>'
            . '<div class="text-center" style="font-size: 0.9em;margin-top:30px;">' . htmlspecialchars($direccion, ENT_QUOTES, 'UTF-8') . '</div>'
            . '</body></html>';

        return [
            'success' => true,
            'html'    => $html,
            'nombre'  => 'Pedido de papelería ' . $razonSocial . '.pdf',
        ];
    }

}