<?php

namespace App\Services;

use App\Core\Auth;
use Illuminate\Database\Capsule\Manager as DB;
use InvalidArgumentException;

/**
 * Alcance y consultas iniciales del módulo Refacciones.
 * Las ubicaciones permitidas provienen de ModuleStationService; nunca del cliente.
 */
class RefaccionesService
{
    public const MODULE_KEY = 'refacciones';
    public const PARENT_MODULE = 'almacen';

    public const SECCIONES = [
        'dashboard' => ['nombre' => 'Dashboard', 'icono' => 'ti ti-layout-dashboard'],
        'entradas' => ['nombre' => 'Entradas', 'icono' => 'ti ti-download'],
        'salidas' => ['nombre' => 'Salidas', 'icono' => 'ti ti-upload'],
        'inventario' => ['nombre' => 'Inventario', 'icono' => 'ti ti-box-multiple'],
        'reportes' => ['nombre' => 'Reportes', 'icono' => 'ti ti-chart-bar'],
        'productos' => ['nombre' => 'Productos', 'icono' => 'ti ti-package'],
        'calcomanias' => ['nombre' => 'Calcomanías', 'icono' => 'ti ti-sticker'],
        'solicitud-calcomanias' => ['nombre' => 'Solicitud de calcomanías', 'icono' => 'ti ti-file-plus'],
        'calendarios' => ['nombre' => 'Calendario', 'icono' => 'ti ti-calendar-event'],
    ];

    public static function getPermisos(): array
    {
        $multiestacion = MultiestacionService::isEnabled();
        return [
            'leer' => ModuloDptoOperativoService::validaPermiso(self::PARENT_MODULE, 'leer'),
            'crear' => $multiestacion && ModuloDptoOperativoService::validaPermiso(self::PARENT_MODULE, 'crear'),
            'editar' => $multiestacion && ModuloDptoOperativoService::validaPermiso(self::PARENT_MODULE, 'editar'),
            'eliminar' => $multiestacion && ModuloDptoOperativoService::validaPermiso(self::PARENT_MODULE, 'eliminar'),
            'descargar' => $multiestacion && ModuloDptoOperativoService::validaPermiso(self::PARENT_MODULE, 'descargar'),
        ];
    }

    /** @return array<int, string> localidades autorizadas para el usuario */
    public static function getUbicacionesDisponibles(): array
    {
        $ubicaciones = [];
        foreach (ModuleStationService::getAvailableStations(self::MODULE_KEY) as $row) {
            $ubicaciones[(int) $row['id']] = (string) $row['nombre'];
        }
        foreach (ModuleStationService::getAvailableDepartments(self::MODULE_KEY) as $row) {
            $ubicaciones[(int) $row['id']] = (string) $row['nombre'];
        }
        return $ubicaciones;
    }

    public static function getContexto(): array
    {
        $contexto = ModuleStationService::getContext(self::MODULE_KEY);
        $id = $contexto['id_estacion'] ?? $contexto['id_depto'] ?? null;
        return [
            'id' => $id !== null ? (int) $id : null,
            'nombre' => (string) ($contexto['nombre'] ?? ''),
        ];
    }

    /** ID de la localidad real de Almacén en el espacio de op_rh_localidades. */
    public static function getWarehouseLocationId(): ?int
    {
        $id = DB::table('op_rh_localidades')
            ->whereRaw('LOWER(TRIM(localidad)) IN (?, ?)', ['almacen', 'almacén'])
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    /** Usuarios locales sólo pueden solicitar para su propia ubicación. */
    public static function puedeCrearSolicitudCalcomanias(?int $idUbicacion = null): bool
    {
        $ubicaciones = self::getUbicacionesDisponibles();
        $contexto = self::getContexto()['id'];
        $idUbicacion ??= $contexto;
        if ($idUbicacion === null || !isset($ubicaciones[$idUbicacion])) return false;
        // La selección del módulo fija el alcance de escritura incluso para
        // usuarios multiestación. Sin una ubicación concreta, no se crea.
        return $contexto !== null && (int) $contexto === $idUbicacion;
    }

    public static function cambiarContexto(?int $idUbicacion): bool
    {
        if ($idUbicacion === null) {
            $config = ModuleStationService::getConfig(self::MODULE_KEY);
            if (MultiestacionService::isEnabled() && !empty($config['allow_all'])) {
                ModuleStationService::setContext(self::MODULE_KEY, null, null);
                return true;
            }
            return false;
        }

        $disponibles = self::getUbicacionesDisponibles();
        if (!isset($disponibles[$idUbicacion])) {
            return false;
        }

        $estaciones = array_column(ModuleStationService::getAvailableStations(self::MODULE_KEY), 'id');
        if (in_array($idUbicacion, array_map('intval', $estaciones), true)) {
            ModuleStationService::setContext(self::MODULE_KEY, $idUbicacion, null);
        } else {
            ModuleStationService::setContext(self::MODULE_KEY, null, $idUbicacion);
        }

        return self::getContexto()['id'] === $idUbicacion;
    }

    private static function ubicacionesConsulta(): array
    {
        $disponibles = self::getUbicacionesDisponibles();
        if ($disponibles === []) {
            return [];
        }

        $contexto = self::getContexto()['id'];
        if ($contexto !== null) {
            return isset($disponibles[$contexto]) ? [$contexto] : [];
        }

        return array_map('intval', array_keys($disponibles));
    }

    private static function calendarioTieneUbicacionesTabla(): bool
    {
        static $exists = null;
        if ($exists === null) {
            try {
                $exists = DB::schema()->hasTable('op_refacciones_calendario_ubicaciones');
            } catch (\Throwable $exception) {
                $exists = false;
            }
        }
        return $exists;
    }

    /** Aplica el alcance visible a eventos antiguos y a eventos con varias ubicaciones. */
    private static function filtrarCalendarioPorUbicaciones($query, array $ubicaciones): void
    {
        $query->where(static function ($scope) use ($ubicaciones): void {
            $scope->whereNull('id_estacion')->orWhereIn('id_estacion', $ubicaciones);
            if (self::calendarioTieneUbicacionesTabla()) {
                $scope->orWhereExists(static function ($related) use ($ubicaciones): void {
                    $related->select(DB::raw('1'))
                        ->from('op_refacciones_calendario_ubicaciones as cu')
                        ->whereColumn('cu.id_evento', 'op_refacciones_calendario.id')
                        ->whereIn('cu.id_estacion', $ubicaciones);
                });
            }
        });
    }

    /** Ubicaciones de un evento, recortadas al alcance actual. */
    private static function obtenerUbicacionesEvento(int $idEvento, ?int $idLegacy, array $permitidas): array
    {
        $ids = [];
        if (self::calendarioTieneUbicacionesTabla()) {
            $ids = DB::table('op_refacciones_calendario_ubicaciones')->where('id_evento', $idEvento)
                ->whereIn('id_estacion', $permitidas)->orderBy('id_estacion')->pluck('id_estacion')->map(static fn ($id): int => (int) $id)->all();
        }
        if ($ids === [] && $idLegacy !== null && in_array($idLegacy, $permitidas, true)) $ids[] = $idLegacy;
        return array_values(array_unique($ids));
    }

    /**
     * Alcance definido en tb_modulos_config para tareas del sistema sin sesión.
     * En Refacciones las existencias usan IDs de localidad cuando así se
     * configura tipo_departamento; sólo se aceptan ubicaciones existentes.
     */
    public static function getUbicacionesConfiguradas(): array
    {
        $config = ModuleStationService::getConfig(self::MODULE_KEY);
        if ($config === null) return [];

        $stationIds = array_values(array_unique(array_filter(array_map('intval', $config['estaciones_soportadas'] ?? []), static fn (int $id): bool => $id > 0)));
        $departmentIds = [];
        if (($config['type'] ?? '') === 'stations_and_departments'
            && ($config['tipo_departamento'] ?? '') === 'localidades') {
            $departmentIds = array_values(array_unique(array_filter(array_map('intval', $config['departamentos_soportados'] ?? []), static fn (int $id): bool => $id > 0)));
        }

        if (($config['tipo_departamento'] ?? '') === 'localidades') {
            $stationIds = MultiestacionService::convertIds(
                $stationIds,
                MultiestacionService::TABLA_ESTACIONES,
                MultiestacionService::TABLA_RH_LOCALIDADES
            );
            $ids = array_values(array_unique(array_merge($stationIds, $departmentIds)));
            if ($ids === []) return [];
            return DB::table('op_rh_localidades')->whereIn('id', $ids)->pluck('id')
                ->map(static fn ($id): int => (int) $id)->all();
        }

        if ($stationIds === []) return [];
        return DB::table('tb_estaciones')->whereIn('id', $stationIds)->pluck('id')
            ->map(static fn ($id): int => (int) $id)->all();
    }

    public static function getDashboard(): array
    {
        // El dashboard siempre resume todo el alcance autorizado del usuario;
        // el contexto elegido en las demás secciones no debe alterar este resumen.
        $ubicaciones = array_map('intval', array_keys(self::getUbicacionesDisponibles()));
        if ($ubicaciones === []) {
            return [
                'productos' => 0, 'valor_inventario' => 0.0, 'entradas_mes' => 0,
                'salidas_mes' => 0, 'stock_bajo' => 0,
                'stock_por_categoria' => [], 'consumo_por_ubicacion' => [],
                'stock_bajo_detalle' => [], 'proximos_eventos' => [],
            ];
        }

        $stockBase = DB::table('op_refacciones_existencias as e')
            ->join('op_refacciones_catalogo as c', 'c.id', '=', 'e.id_refaccion')
            ->whereIn('e.id_estacion', $ubicaciones)
            ->where('c.activo', 1);

        $valor = (clone $stockBase)->selectRaw('COALESCE(SUM(e.existencia * e.costo_promedio), 0) as total')->value('total');
        $productos = DB::table('op_refacciones_catalogo')->where('activo', 1)->where('tipo_articulo', 'refaccion')->count();
        $stockBajo = (clone $stockBase)->whereRaw('COALESCE(NULLIF(e.stock_minimo, 0), c.stock_minimo) > 0')
            ->whereRaw('e.existencia <= COALESCE(NULLIF(e.stock_minimo, 0), c.stock_minimo)')->count();

        $desde = date('Y-m-01 00:00:00');
        $hasta = date('Y-m-01 00:00:00', strtotime('first day of next month'));
        $movimientosMes = DB::table('op_refacciones_movimientos')
            ->where('estado', 'confirmado')->where('fecha', '>=', $desde)->where('fecha', '<', $hasta);
        $entradas = (clone $movimientosMes)->where('tipo', 'entrada')
            ->whereIn('id_estacion_destino', $ubicaciones)->count();
        // Las salidas sólo cuentan cuando la estación destino ya confirmó la recepción.
        // La tarjeta muestra registros finalizados, no piezas ni salidas pendientes.
        $salidas = DB::table('op_refacciones_salidas as s')
            ->join('op_refacciones_movimientos as m', 'm.id', '=', 's.id_movimiento')
            ->where('m.estado', 'confirmado')->where('m.tipo', 'traspaso')->where('s.estatus', 2)
            ->where('m.fecha', '>=', $desde)->where('m.fecha', '<', $hasta)
            ->whereIn('m.id_estacion_origen', $ubicaciones)->count('s.id');

        $locations = self::getUbicacionesDisponibles();
        $eventQuery = DB::table('op_refacciones_calendario')->whereDate('fecha', '>=', date('Y-m-d'));
        self::filtrarCalendarioPorUbicaciones($eventQuery, $ubicaciones);
        $proximosEventos = $eventQuery->orderBy('fecha')->orderBy('hora')->limit(8)->get()->map(static fn ($row): array => (array) $row)->all();
        foreach ($proximosEventos as &$calendarEvent) {
            $eventLocationIds = self::obtenerUbicacionesEvento((int) $calendarEvent['id'], isset($calendarEvent['id_estacion']) ? (int) $calendarEvent['id_estacion'] : null, $ubicaciones);
            $calendarEvent['ubicacion'] = $eventLocationIds === []
                ? ''
                : implode(', ', array_map(static fn (int $locationId): string => $locations[$locationId] ?? 'Ubicación', $eventLocationIds));
        }
        unset($calendarEvent);
        $lowRows = (clone $stockBase)->whereRaw('COALESCE(NULLIF(e.stock_minimo, 0), c.stock_minimo) > 0')
            ->whereRaw('e.existencia <= COALESCE(NULLIF(e.stock_minimo, 0), c.stock_minimo)')
            ->orderBy('e.existencia')->limit(8)
            ->get(['c.sku', 'c.nombre', 'c.categoria', 'e.existencia', DB::raw('COALESCE(NULLIF(e.stock_minimo, 0), c.stock_minimo) as stock_minimo'), 'e.id_estacion']);

        $categoryRows = (clone $stockBase)
            ->where('c.tipo_articulo', 'refaccion')
            ->selectRaw("COALESCE(NULLIF(TRIM(c.categoria), ''), 'Sin categoría') as categoria, SUM(e.existencia) as total")
            ->groupByRaw("COALESCE(NULLIF(TRIM(c.categoria), ''), 'Sin categoría')")
            ->orderByDesc('total')->get();

        $consumoRows = DB::table('op_refacciones_movimientos as m')
            ->join('op_refacciones_salidas as s', 's.id_movimiento', '=', 'm.id')
            ->join('op_refacciones_movimientos_detalle as d', 'd.id_movimiento', '=', 'm.id')
            ->where('m.estado', 'confirmado')->where('m.tipo', 'traspaso')->where('s.estatus', 2)
            ->whereIn('m.id_estacion_origen', $ubicaciones)
            ->selectRaw('m.id_estacion_origen as id_estacion, SUM(d.cantidad) as total')
            ->groupBy('m.id_estacion_origen')->orderByDesc('total')->get();

        return [
            'productos' => (int) $productos,
            'valor_inventario' => (float) $valor,
            'entradas_mes' => (int) $entradas,
            'salidas_mes' => (int) $salidas,
            'stock_bajo' => (int) $stockBajo,
            'stock_por_categoria' => $categoryRows->map(static fn ($row): array => [
                'categoria' => (string) $row->categoria, 'total' => (float) $row->total,
            ])->all(),
            'consumo_por_ubicacion' => $consumoRows->map(static fn ($row): array => [
                'id_estacion' => (int) $row->id_estacion,
                'nombre' => $locations[(int) $row->id_estacion] ?? 'Ubicación',
                'total' => (float) $row->total,
            ])->all(),
            'stock_bajo_detalle' => $lowRows->map(static fn ($row): array => [
                'sku' => (string) $row->sku, 'nombre' => (string) $row->nombre,
                'categoria' => (string) ($row->categoria ?? 'Sin categoría'),
                'existencia' => (float) $row->existencia, 'minimo' => (float) $row->stock_minimo,
                'ubicacion' => $locations[(int) $row->id_estacion] ?? 'Ubicación',
            ])->all(),
            'proximos_eventos' => array_map(static fn (array $row): array => [
                'titulo' => (string) $row['titulo'], 'tipo' => (string) $row['tipo_evento'],
                'fecha' => (string) $row['fecha'], 'hora' => (string) ($row['hora'] ?? ''),
                'ubicacion' => (string) ($row['ubicacion'] ?? ''),
            ], $proximosEventos),
        ];
    }

    public static function getProductos(?string $tipo = null, bool $usarContexto = true): array
    {
        $ubicaciones = $usarContexto
            ? self::ubicacionesConsulta()
            : array_map('intval', array_keys(self::getUbicacionesDisponibles()));
        if ($ubicaciones === []) {
            return [];
        }

        $query = DB::table('op_refacciones_catalogo as c')
            ->leftJoin('op_refacciones_existencias as e', function ($join) use ($ubicaciones): void {
                $join->on('e.id_refaccion', '=', 'c.id')->whereIn('e.id_estacion', $ubicaciones);
            })
            ->leftJoin('op_refacciones_uniformes as u', 'u.id_refaccion', '=', 'c.id')
            ->leftJoin('op_refacciones_calcomanias as cal', 'cal.id_refaccion', '=', 'c.id')
            ->where('c.activo', 1)
            ->selectRaw('c.id, c.sku, c.tipo_articulo, c.nombre, c.descripcion, c.medida, c.modelo, c.marca, c.categoria, c.unidad_medida, c.stock_minimo, c.costo_unitario, c.imagen, u.talla, u.color, cal.dimension, cal.colores_pantone, cal.acabado, cal.reproduccion, cal.notas as notas_calcomania, COALESCE(SUM(e.existencia), 0) as existencia');

        if ($tipo !== null && in_array($tipo, ['refaccion', 'uniforme', 'calcomania'], true)) {
            $query->where('c.tipo_articulo', $tipo);
        }

        return $query->groupBy('c.id', 'c.sku', 'c.tipo_articulo', 'c.nombre', 'c.descripcion', 'c.medida', 'c.modelo', 'c.marca', 'c.categoria', 'c.unidad_medida', 'c.stock_minimo', 'c.costo_unitario', 'c.imagen', 'u.talla', 'u.color', 'cal.dimension', 'cal.colores_pantone', 'cal.acabado', 'cal.reproduccion', 'cal.notas')
            ->orderBy('c.nombre')->get()->map(static fn ($row): array => (array) $row)->all();
    }

    /** @return list<string> Categorías activas permitidas para productos de refacciones. */
    public static function getCategoriasProducto(): array
    {
        return DB::table('op_refacciones_categorias')
            ->where('activo', 1)
            ->orderBy('id')
            ->pluck('nombre')
            ->map(static fn ($nombre): string => (string) $nombre)
            ->all();
    }

    private static function categoriaProductoValida(string $categoria): bool
    {
        return DB::table('op_refacciones_categorias')
            ->where('nombre', $categoria)
            ->where('activo', 1)
            ->exists();
    }

    public static function getInventario(): array
    {
        $ubicaciones = self::ubicacionesConsulta();
        if ($ubicaciones === []) {
            return [];
        }

        return DB::table('op_refacciones_existencias_lotes as l')
            ->join('op_refacciones_catalogo as c', 'c.id', '=', 'l.id_refaccion')
            ->join('op_refacciones_existencias as e', function ($join): void {
                $join->on('e.id_refaccion', '=', 'l.id_refaccion')->on('e.id_estacion', '=', 'l.id_estacion');
            })
            ->whereIn('l.id_estacion', $ubicaciones)
            ->where('l.existencia', '>', 0)->where('c.activo', 1)
            ->groupBy('l.id_estacion', 'c.id', 'c.sku', 'c.nombre', 'c.categoria', 'c.unidad_medida', 'l.costo_unitario', 'e.stock_minimo', 'c.stock_minimo')
            ->orderBy('l.id_estacion')->orderBy('c.nombre')->orderBy('l.costo_unitario')
            ->get(['l.id_estacion', 'c.id', 'c.sku', 'c.nombre', 'c.categoria', 'c.unidad_medida', 'l.costo_unitario', DB::raw('SUM(l.existencia) as existencia'), DB::raw('MAX(e.existencia) as existencia_total'), DB::raw('COALESCE(NULLIF(e.stock_minimo, 0), c.stock_minimo) as stock_minimo')])
            ->map(static fn ($row): array => (array) $row)->all();
    }

    /** Catálogo disponible en el almacén central para órdenes aún no autorizadas. */
    public static function getProductosAlmacenParaOrden(): array
    {
        $warehouseId = self::getWarehouseLocationId();
        if ($warehouseId === null) return [];

        return DB::table('op_refacciones_catalogo as c')
            ->join('op_refacciones_existencias as e', function ($join) use ($warehouseId): void {
                $join->on('e.id_refaccion', '=', 'c.id')->where('e.id_estacion', '=', $warehouseId);
            })
            ->where('c.activo', 1)->where('c.tipo_articulo', 'refaccion')->where('e.existencia', '>', 0)
            ->orderBy('c.nombre')
            ->get(['c.id', 'c.sku', 'c.nombre', 'c.unidad_medida', 'e.existencia'])
            ->map(static fn ($row): array => [
                'id' => (int)$row->id, 'sku' => (string)$row->sku,
                'nombre' => (string)$row->nombre, 'unidad_medida' => (string)$row->unidad_medida,
                'existencia' => (float)$row->existencia,
            ])->all();
    }

    public static function getMovimientos(?string $tipo = null): array
    {
        $ubicaciones = self::ubicacionesConsulta();
        if ($ubicaciones === []) {
            return [];
        }

        $query = DB::table('op_refacciones_movimientos as m')
            ->leftJoin('op_refacciones_movimientos_detalle as d', 'd.id_movimiento', '=', 'm.id')
            ->leftJoin('op_refacciones_catalogo as c', 'c.id', '=', 'd.id_refaccion')
            ->where(function ($query) use ($ubicaciones): void {
                $query->whereIn('m.id_estacion_origen', $ubicaciones)
                    ->orWhereIn('m.id_estacion_destino', $ubicaciones);
            })
            ->where('m.estado', 'confirmado')
            ->selectRaw('m.id, m.folio, m.tipo, m.fecha, m.id_estacion_origen, m.id_estacion_destino, m.proveedor, m.motivo, m.referencia, MAX(m.notas) as notas, MIN(d.id_refaccion) as id_refaccion, SUM(d.cantidad) as cantidad, GROUP_CONCAT(c.nombre ORDER BY c.nombre SEPARATOR ", ") as productos')
            ->groupBy('m.id', 'm.folio', 'm.tipo', 'm.fecha', 'm.id_estacion_origen', 'm.id_estacion_destino', 'm.proveedor', 'm.motivo', 'm.referencia');

        if ($tipo !== null && in_array($tipo, ['entrada', 'salida', 'traspaso', 'ajuste'], true)) {
            $query->where('m.tipo', $tipo);
        }

        $query = $tipo === 'entrada' ? $query->orderByDesc('m.id') : $query->orderByDesc('m.fecha')->orderByDesc('m.id');

        return $query->limit(500)->get()
            ->map(static fn ($row): array => (array) $row)->all();
    }

    /** Historial de precios del catálogo, del cambio más reciente al más antiguo. */
    public static function getHistorialPrecios(int $id): ?array
    {
        if (!self::getPermisos()['leer'] || $id <= 0) return null;
        $product = DB::table('op_refacciones_catalogo')->where('id', $id)
            ->where('tipo_articulo', 'refaccion')->where('activo', 1)
            ->first(['id', 'sku', 'nombre']);
        if (!$product) return null;

        $history = DB::table('op_refacciones_precios')
            ->where('id_refaccion', $id)
            ->orderByDesc('registrado_en')->orderByDesc('id')
            ->get(['id', 'costo_anterior', 'costo_nuevo', 'registrado_en'])
            ->map(static fn ($row): array => [
                'id' => (int) $row->id,
                'costo_anterior' => $row->costo_anterior === null ? null : (float) $row->costo_anterior,
                'costo_nuevo' => (float) $row->costo_nuevo,
                'registrado_en' => (string) $row->registrado_en,
            ])->all();

        return ['producto' => ['id' => (int) $product->id, 'sku' => (string) $product->sku, 'nombre' => (string) $product->nombre], 'historial' => $history];
    }

    /** Catálogo con disponibilidad exclusiva de la estación de origen. */
    public static function getProductosParaSalida(int $idEstacion): array
    {
        if (!isset(self::getUbicacionesDisponibles()[$idEstacion])) return [];

        return DB::table('op_refacciones_catalogo as c')
            ->leftJoin('op_refacciones_existencias as e', function ($join) use ($idEstacion): void {
                $join->on('e.id_refaccion', '=', 'c.id')->where('e.id_estacion', '=', $idEstacion);
            })
            ->where('c.activo', 1)->where('c.tipo_articulo', 'refaccion')
            ->orderBy('c.nombre')
            ->get(['c.id', 'c.sku', 'c.nombre', 'c.unidad_medida', 'c.costo_unitario', DB::raw('COALESCE(e.existencia, 0) as existencia')])
            ->map(static fn ($row): array => (array) $row)->all();
    }

    /** Salidas con su recepción y firmas, siempre acotadas al alcance permitido. */
    public static function getSalidas(): array
    {
        $ubicaciones = self::ubicacionesConsulta();
        if ($ubicaciones === []) return [];

        return DB::table('op_refacciones_salidas as s')
            ->join('op_refacciones_movimientos as m', 'm.id', '=', 's.id_movimiento')
            ->join('op_refacciones_movimientos_detalle as d', 'd.id_movimiento', '=', 'm.id')
            ->join('op_refacciones_catalogo as c', 'c.id', '=', 'd.id_refaccion')
            ->leftJoin('op_refacciones_acuses as a', 'a.id_movimiento', '=', 'm.id')
            ->where(static function ($query) use ($ubicaciones): void {
                $query->whereIn('m.id_estacion_origen', $ubicaciones)
                    ->orWhereIn('m.id_estacion_destino', $ubicaciones);
            })
            ->where('m.tipo', 'traspaso')->where('m.estado', 'confirmado')
            ->selectRaw('s.id, s.id_movimiento, s.estado_salida, s.fecha_retorno, s.id_proveedor_retorno, s.proveedor_retorno, s.referencia_retorno, s.notas_retorno, s.estatus, s.firma_elabora, s.firma_recibe, s.nombre_elabora, s.nombre_recibe, s.id_encargado_recibe, s.fecha_firma_elabora, s.fecha_firma_recibe, m.folio, m.fecha, m.id_estacion_origen, m.id_estacion_destino, m.motivo, m.notas, c.id as id_refaccion, c.nombre as producto, c.sku, d.cantidad, a.nombre_entrega, a.nombre_recibe as acuse_nombre_recibe, EXISTS(SELECT 1 FROM op_pedido_materiales_refacciones pr WHERE pr.id_movimiento_salida = m.id) as es_orden_mantenimiento')
            ->orderByDesc('s.id')->limit(500)->get()
            ->map(static fn ($row): array => (array) $row)->all();
    }

    public static function getSalida(int $id, bool $requierePendienteRecepcion = false): ?array
    {
        $ubicaciones = self::getUbicacionesDisponibles();
        $row = DB::table('op_refacciones_salidas as s')
            ->join('op_refacciones_movimientos as m', 'm.id', '=', 's.id_movimiento')
            ->join('op_refacciones_movimientos_detalle as d', 'd.id_movimiento', '=', 'm.id')
            ->join('op_refacciones_catalogo as c', 'c.id', '=', 'd.id_refaccion')
            ->leftJoin('op_refacciones_acuses as a', 'a.id_movimiento', '=', 'm.id')
            ->where('s.id_movimiento', $id)->where('m.tipo', 'traspaso')
            ->selectRaw('s.id, s.id_movimiento, s.estado_salida, s.fecha_retorno, s.estatus, s.firma_elabora, s.firma_recibe, s.nombre_elabora, s.nombre_recibe, s.id_encargado_recibe, s.fecha_firma_elabora, s.fecha_firma_recibe, m.folio, m.fecha, m.id_estacion_origen, m.id_estacion_destino, m.motivo, m.notas, m.id_usuario, c.id as id_refaccion, c.nombre as producto, c.sku, c.unidad_medida, d.cantidad, a.nombre_entrega, a.nombre_recibe as acuse_nombre_recibe, EXISTS(SELECT 1 FROM op_pedido_materiales_refacciones pr WHERE pr.id_movimiento_salida = m.id) as es_orden_mantenimiento')
            ->first();
        if (!$row) return null;

        $row = (array) $row;
        $originId = (int)$row['id_estacion_origen'];
        $warehouseId = self::getWarehouseLocationId();
        $fromWarehouseOrder = $warehouseId !== null && $originId === $warehouseId && DB::table('op_pedido_materiales_refacciones')
            ->where('id_movimiento_salida', $id)->exists();
        if ((!isset($ubicaciones[$originId]) && !$fromWarehouseOrder) || !isset($ubicaciones[(int) $row['id_estacion_destino']])) return null;
        if ($requierePendienteRecepcion && ((int) $row['estatus'] !== 1 || empty($row['firma_elabora']))) return null;
        $context = self::getContexto()['id'];
        if ($context !== null && (int)$context !== (int)$row['id_estacion_destino']
            && ($fromWarehouseOrder || (int)$context !== $originId)) return null;

        $row['origen'] = $fromWarehouseOrder ? 'Almacen' : $ubicaciones[$originId];
        $row['destino'] = $ubicaciones[(int) $row['id_estacion_destino']];
        $row['es_orden_mantenimiento'] = $fromWarehouseOrder;
        return $row;
    }

    /** Encargados activos de la localidad destino, usando el contrato existente del módulo de medición. */
    public static function getEncargadosSalida(int $idEstacion): array
    {
        if (!isset(self::getUbicacionesDisponibles()[$idEstacion])) return [];
        return DB::table('tb_usuarios')->where('id_gas', $idEstacion)->where('id_puesto', 6)->where('estatus', 0)
            ->orderBy('id')->get(['id', 'nombre'])
            ->map(static fn ($row): array => ['id' => (int) $row->id, 'nombre' => (string) $row->nombre])->all();
    }

    /** Completa una salida de mantenimiento pendiente con fecha, motivo y firma de quien elabora. */
    public static function actualizarSalidaPendiente(array $data): array
    {
        if (!self::getPermisos()['editar']) return self::respuesta(false, 'No tienes permiso para actualizar esta salida.', 403);
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        $date = trim((string) ($data['fecha'] ?? ''));
        $reason = trim((string) ($data['motivo'] ?? ''));
        $signature = trim((string) ($data['firma_elabora'] ?? ''));
        $parsedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($id === false || $id <= 0 || !$parsedDate || $parsedDate->format('Y-m-d') !== $date) {
            return self::respuesta(false, 'Indica una fecha de salida válida.', 422);
        }
        if ($reason === '' || mb_strlen($reason) > 250) return self::respuesta(false, 'Indica un motivo de hasta 250 caracteres.', 422);
        if (!self::validarImagenFirma($signature)) return self::respuesta(false, 'Agrega la firma de quien elabora la salida.', 422);
        $salida = self::getSalida((int) $id, false);
        if (!$salida || (int) ($salida['estatus'] ?? -1) !== 0 || empty($salida['es_orden_mantenimiento'])) {
            return self::respuesta(false, 'La salida ya no está pendiente o no pertenece a tus ubicaciones autorizadas.', 404);
        }
        $warehouseId = self::getWarehouseLocationId();
        if ($warehouseId === null || (int) $salida['id_estacion_origen'] !== $warehouseId) {
            return self::respuesta(false, 'El origen de la salida de mantenimiento no es válido.', 422);
        }

        $signaturePath = null;
        try {
            $user = Auth::user();
            $userId = (int) ($user->id ?? 0);
            if ($userId <= 0) return self::respuesta(false, 'No se pudo identificar al usuario que elabora la salida.', 401);
            $userName = trim((string) ($user->nombre ?? $user->name ?? '')) ?: 'Usuario ' . $userId;
            $signaturePath = self::guardarFirmaSalida($signature);
            DB::transaction(static function () use ($id, $date, $reason, $signaturePath, $userName, $warehouseId): void {
                $row = DB::table('op_refacciones_salidas as s')
                    ->join('op_refacciones_movimientos as m', 'm.id', '=', 's.id_movimiento')
                    ->where('m.id', (int) $id)->where('m.tipo', 'traspaso')->lockForUpdate()
                    ->first(['s.id as salida_id', 's.estatus', 'm.id_estacion_origen', 'm.id_estacion_destino']);
                $isMaintenanceRequest = DB::table('op_pedido_materiales_refacciones')->where('id_movimiento_salida', (int) $id)->exists();
                $locations = self::getUbicacionesDisponibles();
                if (!$row || (int) $row->estatus !== 0 || !$isMaintenanceRequest
                    || (int) $row->id_estacion_origen !== $warehouseId
                    || !isset($locations[(int) $row->id_estacion_destino])) {
                    throw new InvalidArgumentException('La salida ya no está pendiente o no pertenece a tus ubicaciones autorizadas.');
                }
                $now = date('Y-m-d H:i:s');
                DB::table('op_refacciones_movimientos')->where('id', (int) $id)->update([
                    'fecha' => $date . ' ' . date('H:i:s'), 'motivo' => $reason,
                ]);
                DB::table('op_refacciones_salidas')->where('id', (int) $row->salida_id)->update([
                    'estatus' => 1, 'firma_elabora' => $signaturePath,
                    'nombre_elabora' => $userName, 'fecha_firma_elabora' => $now,
                ]);
                $acuseData = [
                    'nombre_entrega' => mb_substr($userName, 0, 160),
                    'nombre_recibe' => 'Pendiente de recepción', 'firma_entrega' => $signaturePath,
                    'firmado_en' => $now,
                ];
                if (DB::table('op_refacciones_acuses')->where('id_movimiento', (int) $id)->exists()) {
                    DB::table('op_refacciones_acuses')->where('id_movimiento', (int) $id)->update($acuseData);
                } else {
                    DB::table('op_refacciones_acuses')->insert(['id_movimiento' => (int) $id] + $acuseData);
                }
            });
            return self::respuesta(true, 'Fecha de salida, motivo y firma registrados. La salida está lista para la recepción.', 200);
        } catch (InvalidArgumentException $exception) {
            if ($signaturePath !== null) self::eliminarArchivoFirmaSalida($signaturePath);
            return self::respuesta(false, $exception->getMessage(), 422);
        } catch (\Throwable $exception) {
            if ($signaturePath !== null) self::eliminarArchivoFirmaSalida($signaturePath);
            error_log('[Refacciones] actualizarSalidaPendiente: ' . $exception->getMessage());
            return self::respuesta(false, 'No se pudo actualizar la salida pendiente.', 500);
        }
    }

    /** Registra la salida firmada por quien entrega; el inventario cambia al finalizar la recepción. */
    public static function crearSalida(array $data): array
    {
        if (!self::getPermisos()['crear']) return self::respuesta(false, 'No tienes permiso para registrar salidas.', 403);
        $origin = filter_var($data['id_estacion_origen'] ?? null, FILTER_VALIDATE_INT);
        $destination = filter_var($data['id_estacion_destino'] ?? null, FILTER_VALIDATE_INT);
        $productId = filter_var($data['id_refaccion'] ?? null, FILTER_VALIDATE_INT);
        $quantity = filter_var($data['cantidad'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($quantity !== false) $quantity = round((float) $quantity, 2);
        $date = trim((string) ($data['fecha'] ?? ''));
        $reason = trim((string) ($data['motivo'] ?? ''));
        $signature = trim((string) ($data['firma_elabora'] ?? ''));
        $allowed = self::getUbicacionesDisponibles();
        $contextId = self::getContexto()['id'];

        if ($origin === false || $origin <= 0 || !isset($allowed[(int) $origin]) || $contextId === null || (int) $contextId !== (int) $origin) {
            return self::respuesta(false, 'Selecciona una ubicación de origen autorizada en el módulo.', 403);
        }
        if ($destination === false || $destination <= 0 || !isset($allowed[(int) $destination]) || (int) $destination === (int) $origin) {
            return self::respuesta(false, 'Selecciona una estación o departamento destino válido.', 422);
        }
        if ($productId === false || $productId <= 0 || $quantity === false || !is_finite((float) $quantity) || (float) $quantity <= 0) {
            return self::respuesta(false, 'Selecciona una refacción y una cantidad válida.', 422);
        }
        $parsedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date) return self::respuesta(false, 'La fecha de salida no es válida.', 422);
        if ($reason === '' || mb_strlen($reason) > 250) return self::respuesta(false, 'Indica un motivo de hasta 250 caracteres.', 422);
        if (!self::validarImagenFirma($signature)) return self::respuesta(false, 'Agrega la firma de quien elabora la salida.', 422);

        try {
            $user = Auth::user();
            $userId = (int) ($user->id ?? 0);
            if ($userId <= 0) return self::respuesta(false, 'No se pudo identificar al usuario que registra la salida.', 401);
            $userName = trim((string) ($user->nombre ?? $user->name ?? ''));
            if ($userName === '') $userName = 'Usuario ' . $userId;
            $signaturePath = self::guardarFirmaSalida($signature);

            $result = DB::transaction(static function () use ($origin, $destination, $productId, $quantity, $date, $reason, $userId, $userName, $signaturePath): array {
                $product = DB::table('op_refacciones_catalogo')->where('id', (int) $productId)->where('tipo_articulo', 'refaccion')->where('activo', 1)->lockForUpdate()->first();
                if (!$product) throw new InvalidArgumentException('La refacción seleccionada ya no está disponible.');
                $stock = DB::table('op_refacciones_existencias')->where('id_refaccion', (int) $productId)->where('id_estacion', (int) $origin)->lockForUpdate()->first();
                $available = (float) ($stock->existencia ?? 0);
                $quantity = round((float) $quantity, 2);
                if ($quantity > $available) throw new InvalidArgumentException('La cantidad excede el stock disponible en la estación de origen (' . rtrim(rtrim(number_format($available, 2, '.', ''), '0'), '.') . ').');
                if (!$stock) throw new InvalidArgumentException('La refacción no tiene existencia disponible en la estación de origen.');

                $folio = self::nuevoFolio('SAL');
                $movementId = (int) DB::table('op_refacciones_movimientos')->insertGetId([
                    'folio' => $folio, 'tipo' => 'traspaso', 'fecha' => $date . ' ' . date('H:i:s'),
                    'id_estacion_origen' => (int) $origin, 'id_estacion_destino' => (int) $destination,
                    'id_usuario' => $userId, 'motivo' => $reason, 'estado' => 'confirmado', 'creado_en' => date('Y-m-d H:i:s'),
                ]);
                DB::table('op_refacciones_movimientos_detalle')->insert([
                    'id_movimiento' => $movementId, 'id_refaccion' => (int) $productId,
                    'cantidad' => $quantity, 'costo_unitario' => (float) $product->costo_unitario,
                ]);
                $salidaId = (int) DB::table('op_refacciones_salidas')->insertGetId([
                    'id_movimiento' => $movementId, 'estado_salida' => 'Prestado', 'fecha_retorno' => null,
                    'estatus' => 0, 'firma_elabora' => $signaturePath, 'nombre_elabora' => $userName,
                    'fecha_firma_elabora' => date('Y-m-d H:i:s'), 'stock_origen_aplicado' => 0,
                    'creado_en' => date('Y-m-d H:i:s'),
                ]);
                DB::table('op_refacciones_salidas')->where('id', $salidaId)->update(['estatus' => 1]);
                DB::table('op_refacciones_acuses')->insert([
                    'id_movimiento' => $movementId, 'nombre_entrega' => mb_substr($userName, 0, 160),
                    'nombre_recibe' => 'Pendiente de recepción', 'firma_entrega' => $signaturePath,
                    'firmado_en' => date('Y-m-d H:i:s'),
                ]);
                return ['id' => $movementId, 'folio' => $folio];
            });
            return self::respuesta(true, 'Salida registrada y firmada. Falta la firma de recepción.', 201, $result);
        } catch (InvalidArgumentException $exception) {
            if (isset($signaturePath)) self::eliminarArchivoFirmaSalida($signaturePath);
            return self::respuesta(false, $exception->getMessage(), 422);
        } catch (\Throwable $exception) {
            if (isset($signaturePath)) self::eliminarArchivoFirmaSalida($signaturePath);
            error_log('[Refacciones] crearSalida: ' . $exception->getMessage());
            return self::respuesta(false, 'No se pudo registrar la salida.', 500);
        }
    }

    /** Crea como pendiente la salida asociada a una OM autorizada, sin mover stock. */
    public static function crearSalidaDesdeOrdenMantenimiento(
        int $idPedido,
        int $idDestino,
        int $idRefaccion,
        float $cantidad,
        string $firmaElabora,
        string $nombreElabora,
        string $fechaFirmaElabora
    ): int {
        $destinos = self::getUbicacionesDisponibles();
        $warehouseId = self::getWarehouseLocationId();
        if ($idPedido <= 0 || $warehouseId === null || !isset($destinos[$idDestino]) || $idDestino === $warehouseId || $idRefaccion <= 0 || $cantidad <= 0) {
            throw new InvalidArgumentException('La orden no contiene una ubicación, refacción o cantidad válida para generar la salida.');
        }
        if (DB::table('op_pedido_materiales_refacciones')->where('id_pedido', $idPedido)->whereNotNull('id_movimiento_salida')->exists()) {
            throw new InvalidArgumentException('La salida de esta orden ya fue generada.');
        }

        $product = DB::table('op_refacciones_catalogo')->where('id', $idRefaccion)
            ->where('tipo_articulo', 'refaccion')->where('activo', 1)->lockForUpdate()->first();
        if (!$product) throw new InvalidArgumentException('La refacción autorizada ya no está disponible en el catálogo.');
        $stock = DB::table('op_refacciones_existencias')->where('id_refaccion', $idRefaccion)
            ->where('id_estacion', $warehouseId)->lockForUpdate()->first();
        $available = (float)($stock->existencia ?? 0);
            $quantity = round($cantidad, 2);
        if (!$stock || $quantity > $available) {
            throw new InvalidArgumentException('La existencia actual de Almacén ya no cubre la cantidad autorizada.');
        }

        $now = date('Y-m-d H:i:s');
        $movementId = (int)DB::table('op_refacciones_movimientos')->insertGetId([
            'folio' => self::nuevoFolio('SAL'), 'tipo' => 'traspaso', 'fecha' => null,
            'id_estacion_origen' => $warehouseId, 'id_estacion_destino' => $idDestino,
            'id_usuario' => (int)(Auth::user()->id ?? 0), 'motivo' => 'Orden de Mantenimiento #'.str_pad((string)$idPedido, 3, '0', STR_PAD_LEFT),
            'estado' => 'confirmado', 'creado_en' => $now,
        ]);
        DB::table('op_refacciones_movimientos_detalle')->insert([
            'id_movimiento' => $movementId, 'id_refaccion' => $idRefaccion,
            'cantidad' => $quantity, 'costo_unitario' => (float)$product->costo_unitario,
        ]);
        $salidaId = (int)DB::table('op_refacciones_salidas')->insertGetId([
            'id_movimiento' => $movementId, 'estado_salida' => 'Prestado', 'fecha_retorno' => null,
            'estatus' => 0, 'firma_elabora' => null, 'nombre_elabora' => null,
            'fecha_firma_elabora' => null, 'stock_origen_aplicado' => 0, 'creado_en' => $now,
        ]);
        return $movementId;
    }

    /** Finaliza la salida: descuenta el origen y abona el destino al guardar la recepción. */
    public static function firmarSalida(array $data): array
    {
        if (!self::getPermisos()['editar']) return self::respuesta(false, 'No tienes permiso para confirmar la recepción.', 403);
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        $signatureElabora = trim((string) ($data['firma_elabora'] ?? ''));
        $encargadoId = filter_var($data['id_encargado'] ?? null, FILTER_VALIDATE_INT);
        $signature = trim((string) ($data['firma_recibe'] ?? ''));
        if ($id === false || $id <= 0) return self::respuesta(false, 'La salida seleccionada no es válida.', 422);

        if ($signatureElabora !== '') {
            if (!self::validarImagenFirma($signatureElabora)) return self::respuesta(false, 'Agrega la firma de quien elabora.', 422);
            $signaturePath = null;
            try {
                $user = Auth::user();
                $userId = (int) ($user->id ?? 0);
                if ($userId <= 0) return self::respuesta(false, 'No se pudo identificar al usuario que elabora la salida.', 401);
                $userName = trim((string) ($user->nombre ?? $user->name ?? '')) ?: 'Usuario ' . $userId;
                $signaturePath = self::guardarFirmaSalida($signatureElabora);
                DB::transaction(static function () use ($id, $signaturePath, $userName): void {
                    $row = DB::table('op_refacciones_salidas as s')->join('op_refacciones_movimientos as m', 'm.id', '=', 's.id_movimiento')
                        ->where('m.id', (int) $id)->where('m.tipo', 'traspaso')->lockForUpdate()
                        ->first(['s.id as salida_id', 's.estatus', 'm.id_estacion_origen', 'm.id_estacion_destino']);
                    $isMaintenanceRequest = DB::table('op_pedido_materiales_refacciones')->where('id_movimiento_salida', (int) $id)->exists();
                    $warehouseId = self::getWarehouseLocationId();
                    if (!$row || (int) $row->estatus !== 0 || !$isMaintenanceRequest || $warehouseId === null || (int) $row->id_estacion_origen !== $warehouseId) {
                        throw new InvalidArgumentException('Esta salida no está pendiente de la firma de quien elabora.');
                    }
                    $locations = self::getUbicacionesDisponibles();
                    if (!isset($locations[(int) $row->id_estacion_destino])) throw new InvalidArgumentException('No tienes acceso a la estación destino.');
                    $now = date('Y-m-d H:i:s');
                    DB::table('op_refacciones_salidas')->where('id', (int) $row->salida_id)->update([
                        'estatus' => 1, 'firma_elabora' => $signaturePath, 'nombre_elabora' => $userName, 'fecha_firma_elabora' => $now,
                    ]);
                    $acuse = DB::table('op_refacciones_acuses')->where('id_movimiento', (int) $id)->exists();
                    $acuseData = ['nombre_entrega' => mb_substr($userName, 0, 160), 'nombre_recibe' => 'Pendiente de recepción', 'firma_entrega' => $signaturePath, 'firmado_en' => $now];
                    if ($acuse) DB::table('op_refacciones_acuses')->where('id_movimiento', (int) $id)->update($acuseData);
                    else DB::table('op_refacciones_acuses')->insert(['id_movimiento' => (int) $id] + $acuseData);
                });
                return self::respuesta(true, 'Firma de quien elabora registrada. La salida está lista para la recepción.', 200);
            } catch (InvalidArgumentException $exception) {
                if ($signaturePath !== null) self::eliminarArchivoFirmaSalida($signaturePath);
                return self::respuesta(false, $exception->getMessage(), 422);
            } catch (\Throwable $exception) {
                if ($signaturePath !== null) self::eliminarArchivoFirmaSalida($signaturePath);
                error_log('[Refacciones] firmarSalidaElabora: ' . $exception->getMessage());
                return self::respuesta(false, 'No se pudo registrar la firma de quien elabora.', 500);
            }
        }

        if ($encargadoId === false || $encargadoId <= 0) return self::respuesta(false, 'Selecciona un encargado de la estación destino.', 422);
        if (!self::validarImagenFirma($signature)) return self::respuesta(false, 'Agrega la firma de recepción.', 422);

        try {
            $signaturePath = self::guardarFirmaSalida($signature);
            DB::transaction(static function () use ($id, $encargadoId, $signaturePath): void {
                $row = DB::table('op_refacciones_salidas as s')->join('op_refacciones_movimientos as m', 'm.id', '=', 's.id_movimiento')
                    ->where('m.id', (int) $id)->where('m.tipo', 'traspaso')->lockForUpdate()
                    ->first(['s.id as salida_id', 's.estatus', 's.stock_origen_aplicado', 'm.id_estacion_origen', 'm.id_estacion_destino', 'm.fecha']);
                if (!$row || (int) $row->estatus !== 1) throw new InvalidArgumentException('La salida no está pendiente de firma de recepción.');
                $locations = self::getUbicacionesDisponibles();
                if (!isset($locations[(int) $row->id_estacion_destino])) throw new InvalidArgumentException('No tienes acceso a la estación destino.');
                $encargado = DB::table('tb_usuarios')->where('id', (int) $encargadoId)
                    ->where('id_gas', (int) $row->id_estacion_destino)->where('id_puesto', 6)->where('estatus', 0)->first(['id', 'nombre']);
                if (!$encargado) throw new InvalidArgumentException('El encargado seleccionado no pertenece a la estación destino.');

                $detail = DB::table('op_refacciones_movimientos_detalle')->where('id_movimiento', (int) $id)->lockForUpdate()->first();
                if (!$detail) throw new InvalidArgumentException('La salida no tiene una refacción asociada.');
                // Las salidas anteriores a este cambio ya pudieron descontar el origen
                // al crearse; la marca evita descontarlas dos veces al finalizar.
                if (!(bool) $row->stock_origen_aplicado) {
                    $costLayers = self::cambiarExistencia((int) $detail->id_refaccion, (int) $row->id_estacion_origen, -(float) $detail->cantidad);
                    self::guardarCapasConsumidas((int) $detail->id, $costLayers);
                    $costoSalida = self::costoPromedioCapas($costLayers, (float) $detail->cantidad);
                    DB::table('op_refacciones_movimientos_detalle')->where('id', (int) $detail->id)->update(['costo_unitario' => $costoSalida]);
                    $detail->costo_unitario = $costoSalida;
                } else {
                    $costLayers = [];
                }
                self::cambiarExistencia((int) $detail->id_refaccion, (int) $row->id_estacion_destino, (float) $detail->cantidad, (float) $detail->costo_unitario, $costLayers ?: null);
                $now = date('Y-m-d H:i:s');
                DB::table('op_refacciones_movimientos')->where('id', (int) $id)->update(['fecha' => $now]);
                DB::table('op_refacciones_salidas')->where('id', (int) $row->salida_id)->update([
                    'estatus' => 2, 'stock_origen_aplicado' => 1, 'firma_recibe' => $signaturePath, 'id_encargado_recibe' => (int) $encargado->id,
                    'nombre_recibe' => trim((string) $encargado->nombre), 'fecha_firma_recibe' => $now,
                ]);
                DB::table('op_refacciones_acuses')->where('id_movimiento', (int) $id)->update([
                    'nombre_recibe' => mb_substr(trim((string) $encargado->nombre), 0, 160),
                    'firma_recibe' => $signaturePath, 'firmado_en' => $now,
                ]);
            });
            return self::respuesta(true, 'Recepción firmada y fecha de salida actualizada.', 200);
        } catch (InvalidArgumentException $exception) {
            if (isset($signaturePath)) self::eliminarArchivoFirmaSalida($signaturePath);
            return self::respuesta(false, $exception->getMessage(), 422);
        } catch (\Throwable $exception) {
            if (isset($signaturePath)) self::eliminarArchivoFirmaSalida($signaturePath);
            error_log('[Refacciones] firmarSalida: ' . $exception->getMessage());
            return self::respuesta(false, 'No se pudo registrar la firma de recepción.', 500);
        }
    }

    /** Programa la fecha de retorno. La existencia sólo se mueve al llegar esa fecha. */
    public static function actualizarRetornoSalida(array $data): array
    {
        if (!self::getPermisos()['editar']) return self::respuesta(false, 'No tienes permiso para editar el retorno de salidas.', 403);
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        $date = trim((string) ($data['fecha_retorno'] ?? ''));
        $providerId = trim((string) ($data['id_proveedor'] ?? ''));
        $reference = trim((string) ($data['referencia'] ?? ''));
        $notes = trim((string) ($data['notas'] ?? ''));
        $parsedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($id === false || $id <= 0 || !$parsedDate || $parsedDate->format('Y-m-d') !== $date) {
            return self::respuesta(false, 'Indica una fecha de retorno válida.', 422);
        }
        if ($providerId === '' || !ctype_digit($providerId) || (int) $providerId <= 0) return self::respuesta(false, 'Selecciona un proveedor válido.', 422);
        if ($reference === '') return self::respuesta(false, 'La referencia / factura es obligatoria.', 422);
        if (mb_strlen($reference) > 120) return self::respuesta(false, 'La referencia / factura no puede exceder 120 caracteres.', 422);
        if (mb_strlen($notes) > 5000) return self::respuesta(false, 'Las notas no pueden exceder 5000 caracteres.', 422);

        try {
            $provider = DB::table('op_almacen_proveedores')->where('id', (int) $providerId)
                ->where(static function ($query): void { $query->where('status', '!=', 1)->orWhereNull('status'); })
                ->first(['id', 'razon_social']);
            $providerName = trim((string) ($provider->razon_social ?? ''));
            if ($providerName === '') return self::respuesta(false, 'El proveedor seleccionado ya no está disponible.', 422);
            if (mb_strlen($providerName) > 180) return self::respuesta(false, 'El nombre del proveedor excede la longitud permitida.', 422);

            $returnedImmediately = DB::transaction(static function () use ($id, $date, $providerId, $providerName, $reference, $notes): bool {
                $row = DB::table('op_refacciones_salidas as s')->join('op_refacciones_movimientos as m', 'm.id', '=', 's.id_movimiento')
                    ->where('m.id', (int) $id)->where('m.tipo', 'traspaso')->lockForUpdate()
                    ->first(['s.id as salida_id', 's.estatus', 's.estado_salida', 'm.id_estacion_origen', 'm.id_estacion_destino']);
                if (!$row) throw new InvalidArgumentException('La salida ya no está disponible.');
                if ((int) $row->id_estacion_origen === self::getWarehouseLocationId()) throw new InvalidArgumentException('Las salidas provenientes de Almacen no tienen retorno.');
                if (mb_strtolower(trim((string) $row->estado_salida)) === 'devuelto') {
                    throw new InvalidArgumentException('El retorno ya fue procesado y no se puede editar.');
                }
                $allowed = self::getUbicacionesDisponibles();
                $contextId = self::getContexto()['id'];
                if (!isset($allowed[(int) $row->id_estacion_origen], $allowed[(int) $row->id_estacion_destino])
                    || ($contextId !== null && !in_array((int) $contextId, [(int) $row->id_estacion_origen, (int) $row->id_estacion_destino], true))) {
                    throw new InvalidArgumentException('No tienes acceso a esta salida.');
                }
                $movement = DB::table('op_refacciones_movimientos')->where('id', (int) $id)->first(['fecha']);
                if ($movement && $date < substr((string) $movement->fecha, 0, 10)) throw new InvalidArgumentException('La fecha de retorno no puede ser anterior a la salida.');
                DB::table('op_refacciones_salidas')->where('id', (int) $row->salida_id)->update([
                    'fecha_retorno' => $date,
                    'id_proveedor_retorno' => (int) $providerId,
                    'proveedor_retorno' => $providerName,
                    'referencia_retorno' => $reference,
                    'notas_retorno' => $notes !== '' ? $notes : null,
                ]);
                if ($date <= date('Y-m-d') && (int) $row->estatus === 2) {
                    if (!self::procesarRetornoEnTransaccion((int) $id, date('Y-m-d'))) {
                        throw new InvalidArgumentException('No se pudo procesar el retorno con los datos registrados.');
                    }
                    return true;
                }
                return false;
            });
            return self::respuesta(true, $returnedImmediately
                ? 'Retorno procesado; se registró la entrada y se repuso el inventario.'
                : 'Retorno programado con los datos de su futura entrada.', 200, ['returned' => $returnedImmediately]);
        } catch (InvalidArgumentException $exception) {
            return self::respuesta(false, $exception->getMessage(), 422);
        } catch (\Throwable $exception) {
            error_log('[Refacciones] actualizarRetornoSalida: ' . $exception->getMessage());
            return self::respuesta(false, 'No se pudo actualizar el retorno de la salida.', 500);
        }
    }

    /** Procesa, una sola vez, las devoluciones cuya fecha programada ya llegó. */
    public static function procesarRetornosVencidos(): array
    {
        if (PHP_SAPI !== 'cli' && !self::getPermisos()['leer']) {
            return self::respuesta(false, 'No tienes permiso para consultar las salidas.', 403);
        }

        $today = date('Y-m-d');
        $warehouseId = self::getWarehouseLocationId();
        if ($warehouseId === null) return self::respuesta(true, 'No se encontró la localidad de Almacén configurada.', 200, ['processed' => 0, 'ids' => []]);

        $dueIds = DB::table('op_refacciones_salidas as s')
            ->join('op_refacciones_movimientos as m', 'm.id', '=', 's.id_movimiento')
            ->where('m.tipo', 'traspaso')->where('m.estado', 'confirmado')
            ->where('m.id_estacion_origen', '>', 0)
            ->where('m.id_estacion_origen', '<>', $warehouseId)
            ->where('s.estatus', 2)->where('s.estado_salida', 'Prestado')
            ->whereNotNull('s.id_proveedor_retorno')->whereNotNull('s.proveedor_retorno')->whereNotNull('s.referencia_retorno')
            ->whereNotNull('s.fecha_retorno')->whereDate('s.fecha_retorno', '<=', $today)
            ->orderBy('s.id')->get(['s.id_movimiento'])
            ->pluck('id_movimiento')->map(static fn ($id): int => (int) $id)->all();

        $processed = [];
        foreach ($dueIds as $movementId) {
            try {
                $done = DB::transaction(static fn (): bool => self::procesarRetornoEnTransaccion($movementId, $today));
                if ($done) $processed[] = $movementId;
            } catch (\Throwable $exception) {
                error_log('[Refacciones] procesarRetornosVencidos #' . $movementId . ': ' . $exception->getMessage());
            }
        }

        return self::respuesta(true, count($processed) ? 'Se procesaron devoluciones vencidas.' : 'No hay devoluciones pendientes por fecha.', 200, [
            'processed' => count($processed), 'ids' => $processed,
        ]);
    }

    /** Registra la entrada y mueve el inventario cuando el retorno ya es exigible. */
    private static function procesarRetornoEnTransaccion(int $movementId, string $today): bool
    {
        $warehouseId = self::getWarehouseLocationId();
        if ($warehouseId === null) return false;
        $row = DB::table('op_refacciones_salidas as s')
            ->join('op_refacciones_movimientos as m', 'm.id', '=', 's.id_movimiento')
            ->where('m.id', $movementId)->where('m.tipo', 'traspaso')->where('m.estado', 'confirmado')
            ->where('m.id_estacion_origen', '>', 0)
            ->where('m.id_estacion_origen', '<>', $warehouseId)
            ->lockForUpdate()
            ->first(['s.id as salida_id', 's.estatus', 's.estado_salida', 's.fecha_retorno', 's.proveedor_retorno', 's.referencia_retorno', 's.notas_retorno', 'm.folio', 'm.id_usuario', 'm.id_estacion_origen', 'm.id_estacion_destino']);
        if (!$row || (int) $row->id_estacion_origen <= 0 || (int) $row->id_estacion_origen === $warehouseId || (int) $row->estatus !== 2 || mb_strtolower(trim((string) $row->estado_salida)) !== 'prestado'
            || empty($row->fecha_retorno) || empty($row->proveedor_retorno) || empty($row->referencia_retorno)
            || substr((string) $row->fecha_retorno, 0, 10) > $today) return false;

        $details = DB::table('op_refacciones_movimientos_detalle')->where('id_movimiento', $movementId)->lockForUpdate()->get();
        if ($details->isEmpty()) throw new InvalidArgumentException('La salida vencida no tiene refacciones asociadas.');
        foreach ($details as $detail) {
            $entryId = (int) DB::table('op_refacciones_movimientos')->insertGetId([
                'folio' => self::nuevoFolio('ENT'),
                'tipo' => 'entrada',
                'fecha' => substr((string) $row->fecha_retorno, 0, 10) . ' 00:00:00',
                'id_estacion_origen' => null,
                'id_estacion_destino' => (int) $row->id_estacion_origen,
                'id_usuario' => (int) $row->id_usuario,
                'proveedor' => $row->proveedor_retorno,
                'referencia' => $row->referencia_retorno,
                'motivo' => 'Reposición automática por retorno de salida ' . $row->folio,
                'notas' => $row->notas_retorno,
                'estado' => 'confirmado',
                'creado_en' => date('Y-m-d H:i:s'),
            ]);
            DB::table('op_refacciones_movimientos_detalle')->insert([
                'id_movimiento' => $entryId,
                'id_refaccion' => (int) $detail->id_refaccion,
                'cantidad' => (float) $detail->cantidad,
                'costo_unitario' => (float) $detail->costo_unitario,
            ]);
            $costLayers = self::cambiarExistencia((int) $detail->id_refaccion, (int) $row->id_estacion_destino, -(float) $detail->cantidad);
            self::cambiarExistencia((int) $detail->id_refaccion, (int) $row->id_estacion_origen, (float) $detail->cantidad, (float) $detail->costo_unitario, $costLayers);
        }
        DB::table('op_refacciones_salidas')->where('id', (int) $row->salida_id)->update(['estado_salida' => 'Devuelto']);
        return true;
    }

    /** Elimina una salida no finalizada y revierte el descuento si ya se había aplicado. */
    public static function eliminarSalida(array $data): array
    {
        if (!self::getPermisos()['eliminar']) return self::respuesta(false, 'No tienes permiso para eliminar salidas.', 403);
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) return self::respuesta(false, 'La salida seleccionada no es válida.', 422);
        $signaturePaths = [];
        try {
            DB::transaction(static function () use ($id, &$signaturePaths): void {
                $row = DB::table('op_refacciones_salidas as s')->join('op_refacciones_movimientos as m', 'm.id', '=', 's.id_movimiento')
                    ->where('m.id', (int) $id)->where('m.tipo', 'traspaso')->lockForUpdate()
                    ->first(['s.id as salida_id', 's.estatus', 's.stock_origen_aplicado', 's.firma_elabora', 's.firma_recibe', 'm.id_estacion_origen', 'm.id_estacion_destino']);
                if (!$row) throw new InvalidArgumentException('La salida ya no está disponible.');
                $isMaintenanceRequest = DB::table('op_pedido_materiales_refacciones')->where('id_movimiento_salida', (int) $id)->exists();
                if ($isMaintenanceRequest) throw new InvalidArgumentException('Las salidas originadas por una orden de mantenimiento no se pueden eliminar.');
                if ((int) $row->estatus === 2) throw new InvalidArgumentException('No se puede eliminar una salida finalizada.');
                $allowed = self::getUbicacionesDisponibles();
                $contextId = self::getContexto()['id'];
                if (!isset($allowed[(int) $row->id_estacion_origen], $allowed[(int) $row->id_estacion_destino])
                    || ($contextId !== null && !in_array((int) $contextId, [(int) $row->id_estacion_origen, (int) $row->id_estacion_destino], true))) {
                    throw new InvalidArgumentException('No tienes acceso a esta salida.');
                }
                $details = DB::table('op_refacciones_movimientos_detalle')->where('id_movimiento', (int) $id)->lockForUpdate()->get();
                if ($details->isEmpty()) throw new InvalidArgumentException('La salida no tiene refacciones asociadas y no se puede restaurar su existencia.');
                if ((bool) $row->stock_origen_aplicado) {
                    foreach ($details as $detail) self::cambiarExistencia((int) $detail->id_refaccion, (int) $row->id_estacion_origen, (float) $detail->cantidad);
                }
                if (!empty($row->firma_elabora)) $signaturePaths[] = (string) $row->firma_elabora;
                if (!empty($row->firma_recibe)) $signaturePaths[] = (string) $row->firma_recibe;
                DB::table('op_refacciones_acuses')->where('id_movimiento', (int) $id)->delete();
                DB::table('op_refacciones_movimientos_detalle')->where('id_movimiento', (int) $id)->delete();
                DB::table('op_refacciones_salidas')->where('id', (int) $row->salida_id)->delete();
                DB::table('op_refacciones_movimientos')->where('id', (int) $id)->delete();
            });
            foreach (array_unique($signaturePaths) as $path) self::eliminarArchivoFirmaSalida($path);
            return self::respuesta(true, 'Salida eliminada y existencias ajustadas según el movimiento aplicado.', 200);
        } catch (InvalidArgumentException $exception) {
            return self::respuesta(false, $exception->getMessage(), 422);
        } catch (\Throwable $exception) {
            error_log('[Refacciones] eliminarSalida: ' . $exception->getMessage());
            return self::respuesta(false, 'No se pudo eliminar la salida.', 500);
        }
    }

    private static function validarImagenFirma(string $dataUrl): bool
    {
        if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $matches)) return false;
        $binary = base64_decode($matches[1], true);
        return $binary !== false && strlen($binary) > 100 && strlen($binary) <= 2_000_000 && str_starts_with($binary, "\x89PNG\r\n\x1a\n");
    }

    private static function guardarFirmaSalida(string $dataUrl): string
    {
        if (!self::validarImagenFirma($dataUrl)) throw new InvalidArgumentException('La firma no tiene un formato válido.');
        $binary = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
        $relative = 'uploads/refacciones-firmas/' . bin2hex(random_bytes(18)) . '.png';
        $directory = PUBLIC_PATH . '/uploads/refacciones-firmas';
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) throw new \RuntimeException('No se pudo preparar el almacenamiento de firmas.');
        if (file_put_contents(PUBLIC_PATH . '/' . $relative, $binary, LOCK_EX) === false) throw new \RuntimeException('No se pudo guardar la firma.');
        return $relative;
    }

    private static function eliminarArchivoFirmaSalida(string $relative): void
    {
        if (str_starts_with($relative, 'uploads/refacciones-firmas/')) {
            $path = PUBLIC_PATH . '/' . $relative;
            if (is_file($path)) @unlink($path);
        }
    }

    public static function getCalendario(?string $inicio = null, ?string $fin = null): array
    {
        $ubicaciones = self::ubicacionesConsulta();
        if ($ubicaciones === []) {
            return [];
        }

        $query = DB::table('op_refacciones_calendario');
        self::filtrarCalendarioPorUbicaciones($query, $ubicaciones);
        if ($inicio !== null) $query->whereDate('fecha', '>=', $inicio);
        if ($fin !== null) $query->whereDate('fecha', '<=', $fin);

        $events = $query->orderBy('fecha')->orderBy('hora')->limit(500)->get()->map(static fn ($row): array => (array) $row)->all();
        $locationMap = self::getUbicacionesDisponibles();
        foreach ($events as &$event) {
            $legacy = isset($event['id_estacion']) ? (int) $event['id_estacion'] : null;
            $locationIds = self::obtenerUbicacionesEvento((int) $event['id'], $legacy, $ubicaciones);
            $event['id_estaciones'] = $locationIds;
            $event['ubicacion'] = $locationIds === []
                ? 'Todas las ubicaciones'
                : implode(', ', array_map(static fn (int $id): string => $locationMap[$id] ?? 'Ubicación', $locationIds));
        }
        unset($event);
        return $events;
    }

    /** Total histórico de eventos pendientes en las ubicaciones autorizadas. */
    public static function contarEventosPendientesSidebar(): int
    {
        $ubicaciones = array_map('intval', array_keys(self::getUbicacionesDisponibles()));
        if ($ubicaciones === []) return 0;

        $query = DB::table('op_refacciones_calendario')->where('estado', 'pendiente');
        self::filtrarCalendarioPorUbicaciones($query, $ubicaciones);
        return (int) $query->count();
    }

    /** Eventos del día visibles dentro del alcance del módulo. */
    public static function getEventosCalendarioDia(string $fecha): array
    {
        $date = \DateTime::createFromFormat('!Y-m-d', $fecha);
        if (!$date || $date->format('Y-m-d') !== $fecha) return [];

        $locations = self::getUbicacionesDisponibles();
        return array_map(static function (array $event) use ($locations): array {
            return [
                'id' => (int) ($event['id'] ?? 0),
                'nombre' => (string) ($event['titulo'] ?? ''),
                'tipo' => (string) ($event['tipo_evento'] ?? ''),
                'estado' => (string) ($event['estado'] ?? 'pendiente'),
                'ubicacion' => (string) ($event['ubicacion'] ?? 'Todas las ubicaciones'),
            ];
        }, self::getCalendario($fecha, $fecha));
    }

    /** Detalle de un evento, sólo si su ubicación es visible al usuario actual. */
    public static function getDetalleCalendario(int $id): ?array
    {
        $locations = self::getUbicacionesDisponibles();
        $visibleLocations = self::ubicacionesConsulta();
        if ($id < 1 || $visibleLocations === []) return null;

        $query = DB::table('op_refacciones_calendario')->where('id', $id);
        self::filtrarCalendarioPorUbicaciones($query, $visibleLocations);
        $event = $query->first();
        if (!$event) return null;

        $row = (array) $event;
        $legacyLocation = isset($row['id_estacion']) ? (int) $row['id_estacion'] : null;
        $eventLocations = self::obtenerUbicacionesEvento($id, $legacyLocation, $visibleLocations);
        $locationNames = array_map(static fn (int $locationId): string => $locations[$locationId] ?? 'Ubicación', $eventLocations);
        return [
            'id' => (int) $row['id'],
            'titulo' => (string) ($row['titulo'] ?? ''),
            'tipo' => (string) ($row['tipo_evento'] ?? ''),
            'fecha' => (string) ($row['fecha'] ?? ''),
            'hora' => (string) ($row['hora'] ?? ''),
            'categoria' => (string) ($row['categoria'] ?? ''),
            'proveedor_externo' => (string) ($row['proveedor_externo'] ?? ''),
            'ubicacion' => $locationNames === [] ? 'Todas las ubicaciones' : implode(', ', $locationNames),
            'ubicaciones' => array_map(static fn (int $locationId): array => ['id' => $locationId, 'nombre' => $locations[$locationId] ?? 'Ubicación'], $eventLocations),
            'estado' => (string) ($row['estado'] ?? 'pendiente'),
            'correo_responsable' => (string) ($row['correo_responsable'] ?? ''),
            'notas' => (string) ($row['notas'] ?? ''),
        ];
    }

    /** Permite cerrar un evento pendiente una sola vez dentro del alcance autorizado. */
    public static function actualizarEstadoEvento(array $data): array
    {
        if (!self::getPermisos()['editar']) return self::respuesta(false, 'No tienes permiso para actualizar eventos del calendario.', 403);

        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        $estado = strtolower(trim((string) ($data['estado'] ?? '')));
        if ($id === false || $id < 1 || !in_array($estado, ['finalizado', 'cancelado'], true)) {
            return self::respuesta(false, 'Selecciona un evento y un estado válidos.', 422);
        }

        try {
            DB::transaction(static function () use ($id, $estado): void {
                $visibleLocations = self::ubicacionesConsulta();
                if ($visibleLocations === []) throw new InvalidArgumentException('El evento no está disponible en tus ubicaciones.');

                $eventQuery = DB::table('op_refacciones_calendario')->where('id', (int) $id);
                self::filtrarCalendarioPorUbicaciones($eventQuery, $visibleLocations);
                $event = $eventQuery->lockForUpdate()->first();
                if (!$event) throw new InvalidArgumentException('El evento no existe o no está disponible en tus ubicaciones.');
                if (strtolower((string) ($event->estado ?? 'pendiente')) !== 'pendiente') {
                    throw new InvalidArgumentException('Este evento ya fue cerrado y no se puede editar nuevamente.');
                }

                DB::table('op_refacciones_calendario')
                    ->where('id', (int) $id)
                    ->where('estado', 'pendiente')
                    ->update(['estado' => $estado]);
            });

            return self::respuesta(true, 'El estado del evento se actualizó.', 200, ['estado' => $estado]);
        } catch (InvalidArgumentException $exception) {
            return self::respuesta(false, $exception->getMessage(), 422);
        } catch (\Throwable $exception) {
            error_log('[Refacciones] actualizarEstadoEvento: ' . $exception->getMessage());
            return self::respuesta(false, 'No se pudo actualizar el estado del evento.', 500);
        }
    }

    public static function getReporte(string $periodo = 'mensual', int $year = 0, int $month = 0): array
    {
        return self::getReporteParaUbicaciones($periodo, $year, $month, self::ubicacionesConsulta());
    }

    /** Reporte para el alcance configurado, usado por el correo automático. */
    public static function getReporteGeneral(string $periodo = 'mensual', int $year = 0, int $month = 0): array
    {
        return self::getReporteParaUbicaciones($periodo, $year, $month, self::getUbicacionesConfiguradas());
    }

    private static function getReporteParaUbicaciones(string $periodo, int $year, int $month, array $ubicaciones): array
    {
        $periodo = in_array($periodo, ['mensual', 'semestral', 'anual'], true) ? $periodo : 'mensual';
        $year = $year >= 2000 && $year <= 2100 ? $year : (int) date('Y');
        $month = $month >= 1 && $month <= 12 ? $month : (int) date('n');
        $semestre = null;

        if ($periodo === 'anual') {
            $inicio = sprintf('%04d-01-01 00:00:00', $year);
            $fin = sprintf('%04d-01-01 00:00:00', $year + 1);
            $label = sprintf('del 1 de enero al 31 de diciembre de %d', $year);
        } elseif ($periodo === 'semestral') {
            $semestre = $month > 6 ? 2 : 1;
            $monthInicio = $semestre === 1 ? 1 : 7;
            $inicio = sprintf('%04d-%02d-01 00:00:00', $year, $monthInicio);
            $fin = $semestre === 1 ? sprintf('%04d-07-01 00:00:00', $year) : sprintf('%04d-01-01 00:00:00', $year + 1);
            $lastDay = $semestre === 1 ? 30 : 31;
            $label = sprintf('del 1 de %s al %d de %s de %d', $semestre === 1 ? 'enero' : 'julio', $lastDay, $semestre === 1 ? 'junio' : 'diciembre', $year);
        } else {
            $inicio = sprintf('%04d-%02d-01 00:00:00', $year, $month);
            $fin = date('Y-m-d H:i:s', strtotime($inicio . ' +1 month'));
            $meses = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
            $label = 'del 1 al ' . date('t', strtotime($inicio)) . ' de ' . $meses[$month] . ' de ' . $year;
            $semestre = null;
        }

        if ($ubicaciones === []) {
            return ['periodo' => $periodo, 'year' => $year, 'month' => $month, 'semestre' => $semestre, 'label' => $label, 'entradas' => 0, 'valor_entradas' => 0.0, 'salidas' => 0, 'valor_salidas' => 0.0, 'detalle' => []];
        }

        $rows = DB::table('op_refacciones_movimientos as m')
            ->join('op_refacciones_movimientos_detalle as d', 'd.id_movimiento', '=', 'm.id')
            ->join('op_refacciones_catalogo as c', 'c.id', '=', 'd.id_refaccion')
            ->leftJoin('op_refacciones_salidas as s', 's.id_movimiento', '=', 'm.id')
            ->leftJoin(DB::raw('(SELECT id_movimiento_detalle, SUM(cantidad * costo_unitario) AS valor_fifo FROM op_refacciones_movimientos_detalle_costos GROUP BY id_movimiento_detalle) AS costo_fifo'), 'costo_fifo.id_movimiento_detalle', '=', 'd.id')
            ->where('m.estado', 'confirmado')->whereIn('m.tipo', ['entrada', 'salida', 'traspaso'])
            ->where('m.fecha', '>=', $inicio)
            ->where('m.fecha', '<', $fin)
            ->where(function ($query) use ($ubicaciones): void {
                $query->where(function ($sub) use ($ubicaciones): void {
                    $sub->where('m.tipo', 'entrada')->whereIn('m.id_estacion_destino', $ubicaciones);
                })->orWhere(function ($sub) use ($ubicaciones): void {
                    $sub->where('m.tipo', 'salida')->whereIn('m.id_estacion_origen', $ubicaciones);
                })->orWhere(function ($sub) use ($ubicaciones): void {
                    // Las salidas del flujo actual son traspasos y sólo se consuman
                    // en reportes cuando la recepción quedó finalizada (estatus 2).
                    $sub->where('m.tipo', 'traspaso')->where('s.estatus', 2)
                        ->whereIn('m.id_estacion_origen', $ubicaciones);
                });
            })
            ->selectRaw("c.id, c.sku, c.nombre, CASE WHEN m.tipo = 'entrada' THEN 'entrada' ELSE 'salida' END as tipo, SUM(d.cantidad) as cantidad, SUM(COALESCE(costo_fifo.valor_fifo, d.cantidad * d.costo_unitario)) as valor")
            ->groupBy('c.id', 'c.sku', 'c.nombre', 'tipo')->get();

        $detail = [];
        $totals = ['entradas' => 0.0, 'valor_entradas' => 0.0, 'salidas' => 0.0, 'valor_salidas' => 0.0];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $detail[$id] ??= ['sku' => (string) $row->sku, 'nombre' => (string) $row->nombre, 'entradas' => 0.0, 'valor_entradas' => 0.0, 'salidas' => 0.0, 'valor_salidas' => 0.0];
            $key = $row->tipo === 'entrada' ? 'entradas' : 'salidas';
            $valueKey = $row->tipo === 'entrada' ? 'valor_entradas' : 'valor_salidas';
            $detail[$id][$key] = (float) $row->cantidad;
            $detail[$id][$valueKey] = (float) $row->valor;
            $totals[$key] += (float) $row->cantidad;
            $totals[$valueKey] += (float) $row->valor;
        }

        return [
            'periodo' => $periodo, 'year' => $year, 'month' => $month,
            'semestre' => $semestre ?? null, 'label' => $label,
            'entradas' => $totals['entradas'], 'valor_entradas' => $totals['valor_entradas'],
            'salidas' => $totals['salidas'], 'valor_salidas' => $totals['valor_salidas'],
            'detalle' => array_values($detail),
        ];
    }

    /** Registra una calcomanía de catálogo sin asignarle inventario por ubicación. */
    private static function crearCalcomania(array $data, array $imagen): array
    {
        $sku = strtoupper(trim((string) ($data['sku'] ?? '')));
        $nombre = trim((string) ($data['nombre'] ?? ''));
        $dimension = trim((string) ($data['dimension'] ?? ''));
        $colores = trim((string) ($data['colores_pantone'] ?? ''));
        $acabado = trim((string) ($data['acabado'] ?? ''));
        $reproduccion = trim((string) ($data['reproduccion'] ?? ''));
        $notas = trim((string) ($data['notas'] ?? ''));

        if ($sku === '' || mb_strlen($sku) > 60 || $nombre === '' || mb_strlen($nombre) > 250
            || $dimension === '' || mb_strlen($dimension) > 100 || $colores === '' || mb_strlen($colores) > 250
            || !in_array($acabado, ['Mate', 'Brillante', 'Reflejante'], true)
            || !in_array($reproduccion, ['Estireno', 'Acrílico', 'Trovisel', 'Imán'], true)) {
            return self::respuesta(false, 'Completa los datos de la calcomanía y selecciona un acabado y reproducción válidos.', 422);
        }

        if (($imagen['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return self::respuesta(false, 'Adjunta una imagen de referencia JPG o PNG.', 422);
        }

        $nombreImagen = null;
        $rutaImagen = null;
        if (($imagen['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if (($imagen['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($imagen['tmp_name']) || !is_uploaded_file((string) $imagen['tmp_name'])) {
                return self::respuesta(false, 'La imagen seleccionada no se pudo leer.', 422);
            }
            if ((int) ($imagen['size'] ?? 0) <= 0 || (int) $imagen['size'] > 5 * 1024 * 1024) {
                return self::respuesta(false, 'La imagen debe pesar como máximo 5 MB.', 422);
            }
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string) $imagen['tmp_name']);
            $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png'][$mime] ?? null;
            if ($extension === null || @getimagesize((string) $imagen['tmp_name']) === false) {
                return self::respuesta(false, 'La imagen debe ser un archivo JPG o PNG válido.', 422);
            }
            $directorio = dirname(__DIR__, 2) . '/public/uploads/archivos/refacciones';
            if (!is_dir($directorio) && !@mkdir($directorio, 0775, true) && !is_dir($directorio)) {
                return self::respuesta(false, 'No se pudo preparar el almacenamiento de imágenes.', 500);
            }
            $nombreImagen = bin2hex(random_bytes(16)) . '.' . $extension;
            $rutaImagen = $directorio . '/' . $nombreImagen;
            if (!move_uploaded_file((string) $imagen['tmp_name'], $rutaImagen)) {
                return self::respuesta(false, 'No se pudo guardar la imagen de referencia.', 500);
            }
        }

        try {
            $id = DB::transaction(static function () use ($sku, $nombre, $dimension, $colores, $acabado, $reproduccion, $notas, $nombreImagen): int {
                if (DB::table('op_refacciones_catalogo')->where('sku', $sku)->exists()) {
                    throw new InvalidArgumentException('El SKU ya está registrado.');
                }
                $usuario = Auth::user();
                $idProducto = (int) DB::table('op_refacciones_catalogo')->insertGetId([
                    'sku' => $sku, 'tipo_articulo' => 'calcomania', 'nombre' => $nombre,
                    'descripcion' => $notas !== '' ? $notas : null, 'medida' => $dimension,
                    'modelo' => null, 'marca' => null, 'categoria' => 'Calcomanías',
                    'unidad_medida' => 'Pieza', 'stock_minimo' => 0, 'costo_unitario' => 0,
                    'imagen' => $nombreImagen, 'activo' => 1,
                    'creado_por' => (int) ($usuario->id ?? 0) ?: null,
                    'creado_en' => date('Y-m-d H:i:s'),
                ]);
                DB::table('op_refacciones_calcomanias')->insert([
                    'id_refaccion' => $idProducto, 'dimension' => $dimension,
                    'colores_pantone' => $colores, 'acabado' => $acabado,
                    'reproduccion' => $reproduccion, 'notas' => $notas !== '' ? $notas : null,
                ]);
                return $idProducto;
            });
            return self::respuesta(true, 'Calcomanía registrada.', 201, ['id' => $id, 'imagen' => $nombreImagen]);
        } catch (InvalidArgumentException $exception) {
            if ($rutaImagen !== null) @unlink($rutaImagen);
            return self::respuesta(false, $exception->getMessage(), 422);
        } catch (\Throwable $exception) {
            if ($rutaImagen !== null) @unlink($rutaImagen);
            error_log('[Refacciones] crearCalcomania: ' . $exception->getMessage());
            return self::respuesta(false, 'No se pudo registrar la calcomanía.', 500);
        }
    }

    /** Actualiza el catálogo y la ficha técnica de una calcomanía. */
    private static function actualizarCalcomania(array $data, array $imagen): array
    {
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) return self::respuesta(false, 'La calcomanía seleccionada no es válida.', 422);

        $sku = strtoupper(trim((string) ($data['sku'] ?? '')));
        $nombre = trim((string) ($data['nombre'] ?? ''));
        $dimension = trim((string) ($data['dimension'] ?? ''));
        $colores = trim((string) ($data['colores_pantone'] ?? ''));
        $acabado = trim((string) ($data['acabado'] ?? ''));
        $reproduccion = trim((string) ($data['reproduccion'] ?? ''));
        $notas = trim((string) ($data['notas'] ?? ''));
        if ($sku === '' || mb_strlen($sku) > 60 || $nombre === '' || mb_strlen($nombre) > 250
            || $dimension === '' || mb_strlen($dimension) > 100 || $colores === '' || mb_strlen($colores) > 250
            || !in_array($acabado, ['Mate', 'Brillante', 'Reflejante'], true)
            || !in_array($reproduccion, ['Estireno', 'Acrílico', 'Trovisel', 'Imán'], true)) {
            return self::respuesta(false, 'Completa los datos de la calcomanía y selecciona un acabado y reproducción válidos.', 422);
        }

        $calcomaniaActual = DB::table('op_refacciones_catalogo')
            ->where('id', $id)
            ->where('tipo_articulo', 'calcomania')
            ->where('activo', 1)
            ->first(['imagen']);
        if (!$calcomaniaActual) return self::respuesta(false, 'La calcomanía ya no está disponible.', 404);
        if (($imagen['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE && empty($calcomaniaActual->imagen)) {
            return self::respuesta(false, 'Adjunta una imagen de referencia JPG o PNG.', 422);
        }

        $imagenNueva = null;
        if (($imagen['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if (($imagen['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($imagen['tmp_name']) || !is_uploaded_file((string) $imagen['tmp_name'])) {
                return self::respuesta(false, 'La imagen seleccionada no se pudo leer.', 422);
            }
            if ((int) ($imagen['size'] ?? 0) <= 0 || (int) $imagen['size'] > 5 * 1024 * 1024) {
                return self::respuesta(false, 'La imagen debe pesar como máximo 5 MB.', 422);
            }
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string) $imagen['tmp_name']);
            $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png'][$mime] ?? null;
            if ($extension === null || @getimagesize((string) $imagen['tmp_name']) === false) {
                return self::respuesta(false, 'La imagen debe ser un archivo JPG o PNG válido.', 422);
            }
            $directorio = dirname(__DIR__, 2) . '/public/uploads/archivos/refacciones';
            if (!is_dir($directorio) && !@mkdir($directorio, 0775, true) && !is_dir($directorio)) {
                return self::respuesta(false, 'No se pudo preparar el almacenamiento de imágenes.', 500);
            }
            $imagenNueva = bin2hex(random_bytes(16)) . '.' . $extension;
            if (!move_uploaded_file((string) $imagen['tmp_name'], $directorio . '/' . $imagenNueva)) {
                return self::respuesta(false, 'No se pudo guardar la imagen de referencia.', 500);
            }
        }

        try {
            DB::transaction(static function () use ($id, $sku, $nombre, $dimension, $colores, $acabado, $reproduccion, $notas, $imagenNueva): void {
                $producto = DB::table('op_refacciones_catalogo')->where('id', $id)->where('tipo_articulo', 'calcomania')->where('activo', 1)->lockForUpdate()->first();
                if (!$producto) throw new InvalidArgumentException('La calcomanía ya no está disponible.');
                if (DB::table('op_refacciones_catalogo')->where('sku', $sku)->where('id', '<>', $id)->exists()) {
                    throw new InvalidArgumentException('El SKU ya está registrado.');
                }
                $actualizacion = [
                    'sku' => $sku, 'nombre' => $nombre, 'descripcion' => $notas !== '' ? $notas : null,
                    'medida' => $dimension, 'categoria' => 'Calcomanías', 'actualizado_en' => date('Y-m-d H:i:s'),
                ];
                if ($imagenNueva !== null) $actualizacion['imagen'] = $imagenNueva;
                DB::table('op_refacciones_catalogo')->where('id', $id)->update($actualizacion);
                DB::table('op_refacciones_calcomanias')->updateOrInsert(
                    ['id_refaccion' => $id],
                    ['dimension' => $dimension, 'colores_pantone' => $colores, 'acabado' => $acabado, 'reproduccion' => $reproduccion, 'notas' => $notas !== '' ? $notas : null]
                );
            });
            return self::respuesta(true, 'Calcomanía actualizada.', 200, ['id' => $id, 'imagen' => $imagenNueva]);
        } catch (InvalidArgumentException $exception) {
            if ($imagenNueva !== null) @unlink(dirname(__DIR__, 2) . '/public/uploads/archivos/refacciones/' . $imagenNueva);
            return self::respuesta(false, $exception->getMessage(), 422);
        } catch (\Throwable $exception) {
            if ($imagenNueva !== null) @unlink(dirname(__DIR__, 2) . '/public/uploads/archivos/refacciones/' . $imagenNueva);
            error_log('[Refacciones] actualizarCalcomania: ' . $exception->getMessage());
            return self::respuesta(false, 'No se pudo actualizar la calcomanía.', 500);
        }
    }

    /** Registra un producto de catálogo o un artículo especial con inventario inicial. */
    public static function crearProducto(array $data, array $imagen): array
    {
        if (!self::getPermisos()['crear']) return self::respuesta(false, 'No tienes permiso para registrar artículos.', 403);
        if (($data['tipo_articulo'] ?? 'refaccion') === 'calcomania') {
            return self::crearCalcomania($data, $imagen);
        }

        $sku = strtoupper(trim((string) ($data['sku'] ?? '')));
        $nombre = trim((string) ($data['nombre'] ?? ''));
        $medida = trim((string) ($data['medida'] ?? ''));
        $modelo = trim((string) ($data['modelo'] ?? ''));
        $marca = trim((string) ($data['marca'] ?? ''));
        $categoria = trim((string) ($data['categoria'] ?? ''));
        $unidad = trim((string) ($data['unidad_medida'] ?? ''));
        $tipo = (string) ($data['tipo_articulo'] ?? 'refaccion');
        $descripcion = trim((string) ($data['descripcion'] ?? ''));
        $idEstacion = $tipo === 'refaccion' ? null : filter_var($data['id_estacion'] ?? null, FILTER_VALIDATE_INT);
        $costo = filter_var($data['costo_unitario'] ?? null, FILTER_VALIDATE_FLOAT);
        $existencia = $tipo === 'refaccion' ? 0.0 : filter_var($data['existencia'] ?? null, FILTER_VALIDATE_FLOAT);
        $minimo = filter_var($data['stock_minimo'] ?? null, FILTER_VALIDATE_FLOAT);

        if ($sku === '' || mb_strlen($sku) > 60 || $nombre === '' || mb_strlen($nombre) > 250
            || $medida === '' || mb_strlen($medida) > 100 || $modelo === '' || mb_strlen($modelo) > 150
            || $marca === '' || mb_strlen($marca) > 150 || $categoria === '' || mb_strlen($categoria) > 120
            || ($tipo !== 'refaccion' && $descripcion === '')) {
            return self::respuesta(false, 'Completa todos los campos obligatorios del artículo.', 422);
        }
        if ($tipo === 'refaccion' && !self::categoriaProductoValida($categoria)) {
            return self::respuesta(false, 'Selecciona una categoría válida para el producto.', 422);
        }
        if (!in_array($unidad, ['Pieza', 'Litro', 'Kilo', 'Metro', 'Caja', 'Galón'], true)) {
            return self::respuesta(false, 'Selecciona una unidad de medida válida.', 422);
        }
        if (!in_array($tipo, ['refaccion', 'uniforme', 'calcomania'], true)) {
            return self::respuesta(false, 'El tipo de artículo no es válido.', 422);
        }
        if ($tipo === 'uniforme' && (trim((string) ($data['talla'] ?? '')) === '' || trim((string) ($data['color'] ?? '')) === '')) {
            return self::respuesta(false, 'Captura la talla y el color del uniforme.', 422);
        }
        if ($tipo === 'calcomania' && array_filter(['dimension', 'colores_pantone', 'acabado', 'reproduccion'], static fn (string $key): bool => trim((string) ($data[$key] ?? '')) === '') !== []) {
            return self::respuesta(false, 'Completa dimensión, colores, acabado y reproducción de la calcomanía.', 422);
        }
        if ($tipo !== 'refaccion' && ($idEstacion === false || !isset(self::getUbicacionesDisponibles()[(int) $idEstacion]))) {
            return self::respuesta(false, 'No tienes acceso a la ubicación seleccionada.', 403);
        }
        if ($costo === false || $costo < 0 || $minimo === false || $minimo < 0 || ($tipo !== 'refaccion' && ($existencia === false || $existencia < 0))) {
            return self::respuesta(false, 'Precio, existencia inicial y stock mínimo deben ser cantidades iguales o mayores a cero.', 422);
        }
        $costo = round((float) $costo, 2);
        $minimo = round((float) $minimo, 2);
        $existencia = $tipo === 'refaccion' ? 0.0 : round((float) $existencia, 2);
        if (($imagen['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($imagen['tmp_name']) || !is_uploaded_file((string) $imagen['tmp_name'])) {
            return self::respuesta(false, 'Adjunta una imagen de referencia en formato JPG o PNG.', 422);
        }
        if ((int) ($imagen['size'] ?? 0) <= 0 || (int) $imagen['size'] > 5 * 1024 * 1024) {
            return self::respuesta(false, 'La imagen debe pesar como máximo 5 MB.', 422);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string) $imagen['tmp_name']);
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png'][$mime] ?? null;
        if ($extension === null || @getimagesize((string) $imagen['tmp_name']) === false) {
            return self::respuesta(false, 'La imagen debe ser un archivo JPG o PNG válido.', 422);
        }

        $directorioImagenes = dirname(__DIR__, 2) . '/public/uploads/archivos/refacciones';
        if (!is_dir($directorioImagenes) && !@mkdir($directorioImagenes, 0775, true) && !is_dir($directorioImagenes)) {
            return self::respuesta(false, 'No se pudo preparar el almacenamiento de imágenes.', 500);
        }
        $nombreImagen = bin2hex(random_bytes(16)) . '.' . $extension;
        $rutaImagen = $directorioImagenes . '/' . $nombreImagen;
        if (!move_uploaded_file((string) $imagen['tmp_name'], $rutaImagen)) {
            return self::respuesta(false, 'No se pudo guardar la imagen de referencia.', 500);
        }

        try {
            $id = DB::transaction(static function () use ($data, $sku, $nombre, $medida, $modelo, $marca, $categoria, $unidad, $descripcion, $tipo, $idEstacion, $costo, $existencia, $minimo, $nombreImagen): int {
                if (DB::table('op_refacciones_catalogo')->where('sku', $sku)->exists()) {
                    throw new InvalidArgumentException('El SKU ya está registrado.');
                }

                $usuario = Auth::user();
                $idProducto = (int) DB::table('op_refacciones_catalogo')->insertGetId([
                    'sku' => $sku,
                    'tipo_articulo' => $tipo,
                    'nombre' => $nombre,
                    'descripcion' => $descripcion !== '' ? $descripcion : null,
                    'medida' => $medida,
                    'modelo' => $modelo,
                    'marca' => $marca,
                    'categoria' => $categoria,
                    'unidad_medida' => $unidad,
                    'stock_minimo' => $minimo,
                    'costo_unitario' => $costo,
                    'imagen' => $nombreImagen,
                    'activo' => 1,
                    'creado_por' => (int) ($usuario->id ?? 0) ?: null,
                    'creado_en' => date('Y-m-d H:i:s'),
                ]);
                DB::table('op_refacciones_precios')->insert([
                    'id_refaccion' => $idProducto,
                    'costo_anterior' => null,
                    'costo_nuevo' => $costo,
                    'id_usuario' => (int) ($usuario->id ?? 0) ?: null,
                    'registrado_en' => date('Y-m-d H:i:s'),
                ]);

                if ($tipo !== 'refaccion') {
                    DB::table('op_refacciones_existencias')->insert([
                        'id_refaccion' => $idProducto,
                        'id_estacion' => (int) $idEstacion,
                        'existencia' => $existencia,
                        'costo_promedio' => $costo,
                        'stock_minimo' => $minimo,
                    ]);
                }

                if ($tipo !== 'refaccion' && $existencia > 0) {
                    $folioEntrada = self::nuevoFolio('ENT');
                    $idMovimiento = (int) DB::table('op_refacciones_movimientos')->insertGetId([
                        'folio' => $folioEntrada,
                        'tipo' => 'entrada',
                        'fecha' => date('Y-m-d H:i:s'),
                        'id_estacion_destino' => (int) $idEstacion,
                        'id_usuario' => (int) ($usuario->id ?? 0),
                        'motivo' => 'Inventario inicial',
                        'estado' => 'confirmado',
                        'creado_en' => date('Y-m-d H:i:s'),
                    ]);
                    DB::table('op_refacciones_movimientos_detalle')->insert([
                        'id_movimiento' => $idMovimiento,
                        'id_refaccion' => $idProducto,
                        'cantidad' => $existencia,
                        'costo_unitario' => $costo,
                    ]);
                }

                if ($tipo === 'uniforme') {
                    DB::table('op_refacciones_uniformes')->insert([
                        'id_refaccion' => $idProducto,
                        'talla' => trim((string) $data['talla']),
                        'color' => trim((string) $data['color']),
                        'notas' => $descripcion,
                    ]);
                } elseif ($tipo === 'calcomania') {
                    DB::table('op_refacciones_calcomanias')->insert([
                        'id_refaccion' => $idProducto,
                        'dimension' => trim((string) $data['dimension']),
                        'colores_pantone' => trim((string) $data['colores_pantone']),
                        'acabado' => trim((string) $data['acabado']),
                        'reproduccion' => trim((string) $data['reproduccion']),
                        'notas' => $descripcion,
                    ]);
                }

                return $idProducto;
            });

            return self::respuesta(true, 'Artículo registrado.', 201, ['id' => $id, 'imagen' => $nombreImagen]);
        } catch (InvalidArgumentException $exception) {
            @unlink($rutaImagen);
            return self::respuesta(false, $exception->getMessage(), 422);
        } catch (\Throwable $exception) {
            @unlink($rutaImagen);
            error_log('[Refacciones] crearProducto: ' . $exception->getMessage());
            return self::respuesta(false, 'No se pudo registrar el artículo.', 500);
        }
    }

    /** Actualiza únicamente artículos de refacción del catálogo. */
    public static function actualizarProducto(array $data, array $imagen): array
    {
        if (!self::getPermisos()['editar']) return self::respuesta(false, 'No tienes permiso para editar artículos.', 403);
        if (($data['tipo_articulo'] ?? '') === 'calcomania') {
            return self::actualizarCalcomania($data, $imagen);
        }

        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) return self::respuesta(false, 'El producto seleccionado no es válido.', 422);

        $sku = strtoupper(trim((string) ($data['sku'] ?? '')));
        $nombre = trim((string) ($data['nombre'] ?? ''));
        $medida = trim((string) ($data['medida'] ?? ''));
        $modelo = trim((string) ($data['modelo'] ?? ''));
        $marca = trim((string) ($data['marca'] ?? ''));
        $categoria = trim((string) ($data['categoria'] ?? ''));
        $unidad = trim((string) ($data['unidad_medida'] ?? ''));
        $costo = filter_var($data['costo_unitario'] ?? null, FILTER_VALIDATE_FLOAT);
        $minimo = filter_var($data['stock_minimo'] ?? null, FILTER_VALIDATE_FLOAT);

        if ($sku === '' || mb_strlen($sku) > 60 || $nombre === '' || mb_strlen($nombre) > 250
            || $medida === '' || mb_strlen($medida) > 100 || $modelo === '' || mb_strlen($modelo) > 150
            || $marca === '' || mb_strlen($marca) > 150 || $categoria === '' || mb_strlen($categoria) > 120) {
            return self::respuesta(false, 'Completa todos los campos obligatorios del producto.', 422);
        }
        if (!self::categoriaProductoValida($categoria)) {
            return self::respuesta(false, 'Selecciona una categoría válida para el producto.', 422);
        }
        if (!in_array($unidad, ['Pieza', 'Litro', 'Kilo', 'Metro', 'Caja', 'Galón'], true)) {
            return self::respuesta(false, 'Selecciona una unidad de medida válida.', 422);
        }
        if ($costo === false || $costo < 0 || $minimo === false || $minimo < 0) {
            return self::respuesta(false, 'Precio y stock mínimo deben ser cantidades iguales o mayores a cero.', 422);
        }
        $costo = round((float) $costo, 2);
        $minimo = round((float) $minimo, 2);

        $imagenNueva = null;
        if (($imagen['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if (($imagen['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($imagen['tmp_name']) || !is_uploaded_file((string) $imagen['tmp_name'])) {
                return self::respuesta(false, 'La imagen seleccionada no se pudo leer.', 422);
            }
            if ((int) ($imagen['size'] ?? 0) <= 0 || (int) $imagen['size'] > 5 * 1024 * 1024) {
                return self::respuesta(false, 'La imagen debe pesar como máximo 5 MB.', 422);
            }
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string) $imagen['tmp_name']);
            $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png'][$mime] ?? null;
            if ($extension === null || @getimagesize((string) $imagen['tmp_name']) === false) {
                return self::respuesta(false, 'La imagen debe ser un archivo JPG o PNG válido.', 422);
            }
            $directorioImagenes = dirname(__DIR__, 2) . '/public/uploads/archivos/refacciones';
            if (!is_dir($directorioImagenes) && !@mkdir($directorioImagenes, 0775, true) && !is_dir($directorioImagenes)) {
                return self::respuesta(false, 'No se pudo preparar el almacenamiento de imágenes.', 500);
            }
            $imagenNueva = bin2hex(random_bytes(16)) . '.' . $extension;
            if (!move_uploaded_file((string) $imagen['tmp_name'], $directorioImagenes . '/' . $imagenNueva)) {
                return self::respuesta(false, 'No se pudo guardar la imagen de referencia.', 500);
            }
        }

        try {
            DB::transaction(static function () use ($id, $sku, $nombre, $medida, $modelo, $marca, $categoria, $unidad, $costo, $minimo, $imagenNueva): void {
                $producto = DB::table('op_refacciones_catalogo')->where('id', $id)->whereIn('tipo_articulo', ['refaccion', 'calcomania'])->where('activo', 1)->lockForUpdate()->first();
                if (!$producto) throw new InvalidArgumentException('El producto ya no está disponible.');
                if (DB::table('op_refacciones_catalogo')->where('sku', $sku)->where('id', '<>', $id)->exists()) {
                    throw new InvalidArgumentException('El SKU ya está registrado.');
                }
                $costoAnterior = round((float) $producto->costo_unitario, 2);
                if (abs($costoAnterior - (float) $costo) >= 0.005) {
                    DB::table('op_refacciones_precios')->insert([
                        'id_refaccion' => (int) $id,
                        'costo_anterior' => $costoAnterior,
                        'costo_nuevo' => (float) $costo,
                        'id_usuario' => (int) (Auth::user()->id ?? 0) ?: null,
                        'registrado_en' => date('Y-m-d H:i:s'),
                    ]);
                }
                $actualizacion = [
                    'sku' => $sku, 'nombre' => $nombre, 'medida' => $medida, 'modelo' => $modelo,
                    'marca' => $marca, 'categoria' => $categoria, 'unidad_medida' => $unidad,
                    'stock_minimo' => $minimo, 'costo_unitario' => $costo, 'actualizado_en' => date('Y-m-d H:i:s'),
                ];
                if ($imagenNueva !== null) $actualizacion['imagen'] = $imagenNueva;
                DB::table('op_refacciones_catalogo')->where('id', $id)->update($actualizacion);
            });
            return self::respuesta(true, 'Producto actualizado.', 200, ['id' => $id, 'imagen' => $imagenNueva]);
        } catch (InvalidArgumentException $exception) {
            if ($imagenNueva !== null) @unlink(dirname(__DIR__, 2) . '/public/uploads/archivos/refacciones/' . $imagenNueva);
            return self::respuesta(false, $exception->getMessage(), 422);
        } catch (\Throwable $exception) {
            if ($imagenNueva !== null) @unlink(dirname(__DIR__, 2) . '/public/uploads/archivos/refacciones/' . $imagenNueva);
            error_log('[Refacciones] actualizarProducto: ' . $exception->getMessage());
            return self::respuesta(false, 'No se pudo actualizar el producto.', 500);
        }
    }

    /** Desactiva un producto sin existencias para conservar el historial de movimientos. */
    public static function eliminarProducto(array $data): array
    {
        if (!self::getPermisos()['eliminar']) return self::respuesta(false, 'No tienes permiso para eliminar artículos.', 403);
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) return self::respuesta(false, 'El producto seleccionado no es válido.', 422);

        try {
            $tipoArticulo = DB::transaction(static function () use ($id): string {
                $producto = DB::table('op_refacciones_catalogo')->where('id', $id)->whereIn('tipo_articulo', ['refaccion', 'calcomania'])->where('activo', 1)->lockForUpdate()->first();
                if (!$producto) throw new InvalidArgumentException('El artículo ya no está disponible.');
                $tieneExistencias = DB::table('op_refacciones_existencias')->where('id_refaccion', $id)->where('existencia', '>', 0)->exists();
                if ($tieneExistencias) throw new InvalidArgumentException('No se puede eliminar porque el producto tiene existencias registradas.');
                DB::table('op_refacciones_catalogo')->where('id', $id)->update(['activo' => 0, 'actualizado_en' => date('Y-m-d H:i:s')]);
                return (string) $producto->tipo_articulo;
            });
            return self::respuesta(true, $tipoArticulo === 'calcomania' ? 'Calcomanía eliminada.' : 'Producto eliminado.', 200);
        } catch (InvalidArgumentException $exception) {
            return self::respuesta(false, $exception->getMessage(), 422);
        } catch (\Throwable $exception) {
            error_log('[Refacciones] eliminarProducto: ' . $exception->getMessage());
            return self::respuesta(false, 'No se pudo eliminar el producto.', 500);
        }
    }

    /** Entrada, salida y traspaso actualizan saldo y bitácora en una transacción. */
    public static function registrarMovimiento(array $data): array
    {
        if (!self::getPermisos()['crear']) return self::respuesta(false, 'No tienes permiso para registrar movimientos.', 403);

        $tipo = (string) ($data['tipo'] ?? '');
        if (!in_array($tipo, ['entrada', 'salida', 'traspaso'], true)) {
            return self::respuesta(false, 'El tipo de movimiento no es válido.', 422);
        }

        if ($tipo === 'entrada' && array_key_exists('producto_id', $data)) {
            return self::registrarEntradaDistribuida($data);
        }

        $origen = self::parseLocation($data['id_estacion_origen'] ?? null);
        $destino = self::parseLocation($data['id_estacion_destino'] ?? null);
        $allowed = self::getUbicacionesDisponibles();
        if ($tipo === 'entrada' && ($destino === null || !isset($allowed[$destino]))) {
            return self::respuesta(false, 'Selecciona una ubicación destino autorizada.', 403);
        }
        if (in_array($tipo, ['salida', 'traspaso'], true) && ($origen === null || !isset($allowed[$origen]))) {
            return self::respuesta(false, 'Selecciona una ubicación origen autorizada.', 403);
        }
        if ($tipo === 'traspaso' && ($destino === null || !isset($allowed[$destino]) || $destino === $origen)) {
            return self::respuesta(false, 'Selecciona un destino distinto y autorizado.', 422);
        }

        $detalles = self::normalizarDetalles($data['detalles'] ?? []);
        if ($detalles === []) return self::respuesta(false, 'Agrega al menos un artículo y una cantidad válida.', 422);

        $nombreEntrega = trim((string) ($data['nombre_entrega'] ?? ''));
        $nombreRecibe = trim((string) ($data['nombre_recibe'] ?? ''));
        if ($tipo === 'salida' && ($nombreEntrega === '' || $nombreRecibe === '')) {
            return self::respuesta(false, 'Indica quién entrega y quién recibe para generar el acuse.', 422);
        }

        try {
            $folio = self::nuevoFolio($tipo === 'entrada' ? 'ENT' : ($tipo === 'salida' ? 'SAL' : 'TRA'));
            $idMovimiento = DB::transaction(static function () use ($data, $tipo, $origen, $destino, $detalles, $folio, $nombreEntrega, $nombreRecibe): int {
                $user = Auth::user();
                $id = (int) DB::table('op_refacciones_movimientos')->insertGetId([
                    'folio' => $folio,
                    'tipo' => $tipo,
                    'fecha' => trim((string) ($data['fecha'] ?? '')) ?: date('Y-m-d H:i:s'),
                    'id_estacion_origen' => $tipo === 'entrada' ? null : $origen,
                    'id_estacion_destino' => $tipo === 'salida' ? null : $destino,
                    'id_usuario' => (int) ($user->id ?? 0),
                    'proveedor' => trim((string) ($data['proveedor'] ?? '')) ?: null,
                    'referencia' => trim((string) ($data['referencia'] ?? '')) ?: null,
                    'motivo' => trim((string) ($data['motivo'] ?? '')) ?: null,
                    'notas' => trim((string) ($data['notas'] ?? '')) ?: null,
                    'estado' => 'confirmado',
                    'creado_en' => date('Y-m-d H:i:s'),
                ]);

                foreach ($detalles as $detalle) {
                    $producto = DB::table('op_refacciones_catalogo')->where('id', $detalle['id_refaccion'])
                        ->where('activo', 1)->lockForUpdate()->first();
                    if (!$producto) throw new InvalidArgumentException('Uno de los artículos ya no está disponible.');

                    $costo = $tipo === 'entrada'
                        ? (float) $producto->costo_unitario
                        : self::obtenerCostoPromedio((int) $producto->id, (int) $origen);
                    $detailId = (int) DB::table('op_refacciones_movimientos_detalle')->insertGetId([
                        'id_movimiento' => $id,
                        'id_refaccion' => $detalle['id_refaccion'],
                        'cantidad' => $detalle['cantidad'],
                        'costo_unitario' => $costo,
                    ]);

                    if ($tipo === 'entrada') {
                        self::cambiarExistencia($detalle['id_refaccion'], (int) $destino, $detalle['cantidad'], $costo);
                    } elseif ($tipo === 'salida') {
                        $costLayers = self::cambiarExistencia($detalle['id_refaccion'], (int) $origen, -$detalle['cantidad']);
                        self::guardarCapasConsumidas($detailId, $costLayers);
                        $costoSalida = self::costoPromedioCapas($costLayers, (float) $detalle['cantidad']);
                        DB::table('op_refacciones_movimientos_detalle')->where('id', $detailId)->update(['costo_unitario' => $costoSalida]);
                    } else {
                        $costLayers = self::cambiarExistencia($detalle['id_refaccion'], (int) $origen, -$detalle['cantidad']);
                        self::cambiarExistencia($detalle['id_refaccion'], (int) $destino, $detalle['cantidad'], $costo, $costLayers);
                    }
                }

                if ($tipo === 'salida') {
                    DB::table('op_refacciones_acuses')->insert([
                        'id_movimiento' => $id,
                        'nombre_entrega' => $nombreEntrega,
                        'nombre_recibe' => $nombreRecibe,
                    ]);
                }

                return $id;
            });

            return self::respuesta(true, 'Movimiento registrado.', 201, ['id' => $idMovimiento, 'folio' => $folio]);
        } catch (InvalidArgumentException $exception) {
            return self::respuesta(false, $exception->getMessage(), 422);
        } catch (\Throwable $exception) {
            error_log('[Refacciones] registrarMovimiento: ' . $exception->getMessage());
            return self::respuesta(false, 'No se pudo registrar el movimiento.', 500);
        }
    }

    /** Registra una entrada para una o varias ubicaciones sin ampliar el contrato de productos/calcomanías. */
    private static function registrarEntradaDistribuida(array $data): array
    {
        if (!self::getPermisos()['crear']) return self::respuesta(false, 'No tienes permiso para registrar entradas.', 403);

        $productoId = filter_var($data['producto_id'] ?? null, FILTER_VALIDATE_INT);
        if ($productoId === false || $productoId <= 0) return self::respuesta(false, 'Selecciona una refacción.', 422);

        $rawDestinos = $data['destinos'] ?? [];
        if (!is_array($rawDestinos)) $rawDestinos = [$rawDestinos];
        $destinos = [];
        foreach ($rawDestinos as $rawDestino) {
            $destino = self::parseLocation($rawDestino);
            if ($destino !== null && !in_array($destino, $destinos, true)) $destinos[] = $destino;
        }
        $allowed = self::getUbicacionesDisponibles();
        $contextId = self::getContexto()['id'];
        $config = ModuleStationService::getConfig(self::MODULE_KEY) ?? [];
        if ($destinos === [] || count($destinos) !== count($rawDestinos)) {
            return self::respuesta(false, 'Selecciona al menos una ubicación destino válida.', 422);
        }
        foreach ($destinos as $destino) {
            if (!isset($allowed[$destino])) return self::respuesta(false, 'Una de las ubicaciones destino no está autorizada.', 403);
        }
        if ($contextId !== null && $destinos !== [(int) $contextId]) {
            return self::respuesta(false, 'La entrada debe asignarse únicamente a la ubicación seleccionada.', 403);
        }
        if ($contextId === null && (!MultiestacionService::isEnabled() || empty($config['allow_all']))) {
            return self::respuesta(false, 'No tienes permiso para distribuir entradas entre ubicaciones.', 403);
        }

        $rawQuantities = $data['cantidades'] ?? [];
        if (is_string($rawQuantities)) $rawQuantities = json_decode($rawQuantities, true) ?: [];
        if (!is_array($rawQuantities)) return self::respuesta(false, 'Indica una cantidad para cada ubicación.', 422);
        $quantities = [];
        foreach ($destinos as $destino) {
            $quantity = filter_var($rawQuantities[(string) $destino] ?? $rawQuantities[$destino] ?? null, FILTER_VALIDATE_FLOAT);
            if ($quantity === false || !is_finite((float) $quantity) || $quantity <= 0) {
                return self::respuesta(false, 'Indica una cantidad mayor a cero para cada ubicación seleccionada.', 422);
            }
            $quantities[$destino] = round((float) $quantity, 2);
        }

        $fecha = trim((string) ($data['fecha'] ?? ''));
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        if (!$date || $date->format('Y-m-d') !== $fecha) return self::respuesta(false, 'La fecha de entrada no es válida.', 422);
        $referencia = trim((string) ($data['referencia'] ?? ''));
        $notas = trim((string) ($data['notas'] ?? ''));
        if ($referencia === '') return self::respuesta(false, 'La referencia es obligatoria.', 422);
        if (mb_strlen($referencia) > 120) return self::respuesta(false, 'La referencia no puede exceder 120 caracteres.', 422);

        $proveedorId = trim((string) ($data['id_proveedor'] ?? ''));
        if ($proveedorId === '' || !ctype_digit($proveedorId) || (int) $proveedorId <= 0) {
            return self::respuesta(false, 'El proveedor seleccionado no es válido.', 422);
        }

        try {
            $product = DB::table('op_refacciones_catalogo')->where('id', (int) $productoId)->where('activo', 1)
                ->where('tipo_articulo', 'refaccion')->first();
            if (!$product) return self::respuesta(false, 'La refacción seleccionada ya no está disponible.', 422);

            $provider = DB::table('op_almacen_proveedores')->where('id', (int) $proveedorId)
                ->where(static function ($query): void { $query->where('status', '!=', 1)->orWhereNull('status'); })
                ->first(['razon_social']);
            if (!$provider || trim((string) $provider->razon_social) === '') {
                return self::respuesta(false, 'El proveedor seleccionado ya no está disponible.', 422);
            }
            $providerName = trim((string) $provider->razon_social);
            if (mb_strlen($providerName) > 180) return self::respuesta(false, 'El nombre comercial del proveedor excede la longitud permitida.', 422);

            $rows = DB::transaction(static function () use ($destinos, $quantities, $allowed, $fecha, $referencia, $notas, $providerName, $productoId): array {
                $user = Auth::user();
                $result = [];
                foreach ($destinos as $destino) {
                    $lockedProduct = DB::table('op_refacciones_catalogo')->where('id', (int) $productoId)->where('activo', 1)
                        ->where('tipo_articulo', 'refaccion')->lockForUpdate()->first();
                    if (!$lockedProduct) throw new InvalidArgumentException('La refacción seleccionada ya no está disponible.');

                    $folio = self::nuevoFolio('ENT');
                    $idMovimiento = (int) DB::table('op_refacciones_movimientos')->insertGetId([
                        'folio' => $folio,
                        'tipo' => 'entrada',
                        'fecha' => $fecha . ' ' . date('H:i:s'),
                        'id_estacion_origen' => null,
                        'id_estacion_destino' => (int) $destino,
                        'id_usuario' => (int) ($user->id ?? 0),
                        'proveedor' => $providerName,
                        'referencia' => $referencia !== '' ? $referencia : null,
                        'motivo' => null,
                        'notas' => $notas !== '' ? $notas : null,
                        'estado' => 'confirmado',
                        'creado_en' => date('Y-m-d H:i:s'),
                    ]);
                    DB::table('op_refacciones_movimientos_detalle')->insert([
                        'id_movimiento' => $idMovimiento,
                        'id_refaccion' => (int) $productoId,
                        'cantidad' => $quantities[$destino],
                        'costo_unitario' => (float) $lockedProduct->costo_unitario,
                    ]);
                    self::cambiarExistencia((int) $productoId, (int) $destino, $quantities[$destino], (float) $lockedProduct->costo_unitario);
                    $result[] = [
                        'id' => $idMovimiento,
                        'folio' => $folio,
                        'fecha' => $fecha,
                        'fecha_formateada' => \formatearFecha($fecha),
                        'id_refaccion' => (int) $productoId,
                        'producto' => (string) $lockedProduct->nombre,
                        'id_estacion_destino' => (int) $destino,
                        'ubicacion' => (string) $allowed[$destino],
                        'cantidad' => $quantities[$destino],
                        'proveedor' => $providerName ?? '',
                        'referencia' => $referencia,
                        'notas' => $notas,
                    ];
                }
                return $result;
            });

            return self::respuesta(true, count($rows) === 1 ? 'Entrada registrada.' : 'Entradas registradas por ubicación.', 201, ['movimientos' => $rows]);
        } catch (InvalidArgumentException $exception) {
            return self::respuesta(false, $exception->getMessage(), 422);
        } catch (\Throwable $exception) {
            error_log('[Refacciones] registrarEntradaDistribuida: ' . $exception->getMessage());
            return self::respuesta(false, 'No se pudo registrar la entrada.', 500);
        }
    }

    /** Actualiza una línea de entrada, permitiendo cambiar la refacción sin perder la consistencia del inventario. */
    public static function actualizarEntrada(array $data): array
    {
        if (!self::getPermisos()['editar']) return self::respuesta(false, 'No tienes permiso para editar entradas.', 403);
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        $productoId = filter_var($data['producto_id'] ?? null, FILTER_VALIDATE_INT);
        $cantidad = filter_var($data['cantidad'] ?? null, FILTER_VALIDATE_FLOAT);
        $fecha = trim((string) ($data['fecha'] ?? ''));
        $proveedorId = trim((string) ($data['id_proveedor'] ?? ''));
        $referencia = trim((string) ($data['referencia'] ?? ''));
        $notas = trim((string) ($data['notas'] ?? ''));
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        if ($id === false || $id <= 0 || $productoId === false || $productoId <= 0 || $cantidad === false || !is_finite((float) $cantidad) || $cantidad <= 0) {
            return self::respuesta(false, 'La entrada, refacción o cantidad no son válidas.', 422);
        }
        if (!$date || $date->format('Y-m-d') !== $fecha) return self::respuesta(false, 'La fecha de entrada no es válida.', 422);
        if ($proveedorId === '' || !ctype_digit($proveedorId) || (int) $proveedorId <= 0) return self::respuesta(false, 'Selecciona un proveedor.', 422);
        if ($referencia === '' || mb_strlen($referencia) > 120) return self::respuesta(false, 'La referencia es obligatoria y debe tener hasta 120 caracteres.', 422);

        try {
            $provider = DB::table('op_almacen_proveedores')->where('id', (int) $proveedorId)
                ->where(static function ($query): void { $query->where('status', '!=', 1)->orWhereNull('status'); })
                ->first(['razon_social']);
            if (!$provider || trim((string) $provider->razon_social) === '' || mb_strlen(trim((string) $provider->razon_social)) > 180) {
                return self::respuesta(false, 'El proveedor seleccionado ya no está disponible.', 422);
            }

            $updated = DB::transaction(static function () use ($id, $productoId, $cantidad, $fecha, $provider, $referencia, $notas): array {
                $movement = DB::table('op_refacciones_movimientos')->where('id', (int) $id)->where('tipo', 'entrada')->where('estado', 'confirmado')->lockForUpdate()->first();
                if (!$movement) throw new InvalidArgumentException('La entrada ya no está disponible.');
                $location = (int) $movement->id_estacion_destino;
                $available = self::getUbicacionesDisponibles();
                $contextId = self::getContexto()['id'];
                if (!isset($available[$location]) || ($contextId !== null && $location !== (int) $contextId)) {
                    throw new InvalidArgumentException('No tienes acceso a esta entrada.');
                }
                $details = DB::table('op_refacciones_movimientos_detalle')->where('id_movimiento', (int) $id)->lockForUpdate()->get();
                if ($details->count() !== 1) throw new InvalidArgumentException('Esta entrada contiene varias líneas y no puede editarse desde esta vista.');
                $detail = $details->first();
                $product = DB::table('op_refacciones_catalogo')->where('id', (int) $productoId)
                    ->where('activo', 1)->where('tipo_articulo', 'refaccion')->lockForUpdate()->first(['id', 'nombre', 'costo_unitario']);
                if (!$product) throw new InvalidArgumentException('La refacción seleccionada ya no está disponible.');

                $oldProductId = (int) $detail->id_refaccion;
                $oldQuantity = (float) $detail->cantidad;
                if ($oldProductId !== (int) $product->id) {
                    self::cambiarExistencia($oldProductId, $location, -$oldQuantity);
                    self::cambiarExistencia((int) $product->id, $location, (float) $cantidad, (float) $product->costo_unitario);
                    $detailUpdate = [
                        'id_refaccion' => (int) $product->id,
                        'cantidad' => round((float) $cantidad, 2),
                        'costo_unitario' => (float) $product->costo_unitario,
                    ];
                } else {
                    $delta = (float) $cantidad - $oldQuantity;
                    if ($delta !== 0.0) self::cambiarExistencia($oldProductId, $location, $delta, (float) $detail->costo_unitario);
                    $detailUpdate = ['cantidad' => round((float) $cantidad, 2)];
                }
                DB::table('op_refacciones_movimientos_detalle')->where('id', $detail->id)->update($detailUpdate);
                DB::table('op_refacciones_movimientos')->where('id', (int) $id)->update([
                    'fecha' => $fecha . ' ' . date('H:i:s'),
                    'proveedor' => trim((string) $provider->razon_social),
                    'referencia' => $referencia,
                    'notas' => $notas !== '' ? $notas : null,
                ]);
                return [
                    'id' => (int) $id, 'folio' => (string) $movement->folio, 'fecha' => $fecha,
                    'fecha_formateada' => \formatearFecha($fecha),
                    'id_refaccion' => (int) $product->id, 'producto' => (string) $product->nombre,
                    'id_estacion_destino' => $location, 'ubicacion' => (string) $available[$location], 'cantidad' => round((float) $cantidad, 2),
                    'proveedor' => trim((string) $provider->razon_social), 'referencia' => $referencia, 'notas' => $notas,
                ];
            });
            return self::respuesta(true, 'Entrada actualizada.', 200, ['movimiento' => $updated]);
        } catch (InvalidArgumentException $exception) {
            return self::respuesta(false, $exception->getMessage(), 422);
        } catch (\Throwable $exception) {
            error_log('[Refacciones] actualizarEntrada: ' . $exception->getMessage());
            return self::respuesta(false, 'No se pudo actualizar la entrada.', 500);
        }
    }

    /** Elimina una entrada y revierte el saldo con el control global de eliminación. */
    public static function eliminarEntrada(array $data): array
    {
        if (!self::getPermisos()['eliminar']) return self::respuesta(false, 'No tienes permiso para eliminar entradas.', 403);
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) return self::respuesta(false, 'La entrada seleccionada no es válida.', 422);
        try {
            DB::transaction(static function () use ($id): void {
                $movement = DB::table('op_refacciones_movimientos')->where('id', (int) $id)->where('tipo', 'entrada')->where('estado', 'confirmado')->lockForUpdate()->first();
                if (!$movement) throw new InvalidArgumentException('La entrada ya no está disponible.');
                $location = (int) $movement->id_estacion_destino;
                $available = self::getUbicacionesDisponibles();
                $contextId = self::getContexto()['id'];
                if (!isset($available[$location]) || ($contextId !== null && $location !== (int) $contextId)) {
                    throw new InvalidArgumentException('No tienes acceso a esta entrada.');
                }
                $details = DB::table('op_refacciones_movimientos_detalle')->where('id_movimiento', (int) $id)->lockForUpdate()->get();
                if ($details->isEmpty()) throw new InvalidArgumentException('La entrada no tiene productos asociados.');
                foreach ($details as $detail) self::cambiarExistencia((int) $detail->id_refaccion, $location, -(float) $detail->cantidad);
                DB::table('op_refacciones_movimientos_detalle')->where('id_movimiento', (int) $id)->delete();
                DB::table('op_refacciones_movimientos')->where('id', (int) $id)->delete();
            });
            return self::respuesta(true, 'Entrada eliminada y existencias actualizadas.', 200);
        } catch (InvalidArgumentException $exception) {
            return self::respuesta(false, $exception->getMessage(), 422);
        } catch (\Throwable $exception) {
            error_log('[Refacciones] eliminarEntrada: ' . $exception->getMessage());
            return self::respuesta(false, 'No se pudo eliminar la entrada.', 500);
        }
    }

    public static function crearEvento(array $data): array
    {
        if (!self::getPermisos()['crear']) return self::respuesta(false, 'No tienes permiso para crear eventos.', 403);
        $titulo = trim((string) ($data['titulo'] ?? ''));
        $fecha = trim((string) ($data['fecha'] ?? ''));
        $tipo = (string) ($data['tipo_evento'] ?? 'otro');
        $allowedTypes = ['compra', 'mantenimiento_externo', 'prueba', 'calibracion'];
        $locationValues = $data['id_estaciones'] ?? [];
        if (!is_array($locationValues)) $locationValues = [$locationValues];
        $locationIds = array_values(array_unique(array_filter(array_map(static fn ($value): ?int => self::parseLocation($value), $locationValues))));
        $singleLocation = self::parseLocation($data['id_estacion'] ?? null);
        if ($locationIds === [] && $singleLocation !== null) $locationIds = [$singleLocation];
        $categoria = trim((string) ($data['categoria'] ?? ''));
        $proveedorExterno = trim((string) ($data['proveedor_externo'] ?? ''));
        $correo = trim((string) ($data['correo_responsable'] ?? ''));
        $fechaValida = \DateTime::createFromFormat('!Y-m-d', $fecha);

        if ($titulo === '' || mb_strlen($titulo) > 180 || !$fechaValida || $fechaValida->format('Y-m-d') !== $fecha) {
            return self::respuesta(false, 'Captura un título y una fecha válidos.', 422);
        }
        if (!in_array($tipo, $allowedTypes, true)) return self::respuesta(false, 'El tipo de evento no es válido.', 422);
        if ($tipo === 'compra' && ($categoria === '' || mb_strlen($categoria) > 120)) {
            return self::respuesta(false, 'Captura la categoría de la compra (máximo 120 caracteres).', 422);
        }
        if ($tipo === 'mantenimiento_externo' && ($proveedorExterno === '' || mb_strlen($proveedorExterno) > 180)) {
            return self::respuesta(false, 'Captura el nombre del proveedor externo (máximo 180 caracteres).', 422);
        }
        if ($correo === '' || mb_strlen($correo) > 180 || filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            return self::respuesta(false, 'Captura un correo válido del responsable.', 422);
        }
        $hora = $tipo === 'calibracion' ? trim((string) ($data['hora'] ?? '')) : '';
        if ($tipo === 'calibracion' && ($hora === '' || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $hora))) {
            return self::respuesta(false, 'Selecciona una hora válida para la calibración.', 422);
        }
        if ($locationIds === []) return self::respuesta(false, 'Selecciona una estación o departamento.', 422);
        if ($tipo !== 'compra' && count($locationIds) > 1) return self::respuesta(false, 'Este tipo de evento sólo permite una estación o departamento.', 422);
        if ($tipo === 'compra' && count($locationIds) > 1 && !self::calendarioTieneUbicacionesTabla()) {
            return self::respuesta(false, 'La base de datos aún no tiene habilitado el registro de eventos para varias ubicaciones.', 503);
        }
        $availableLocations = self::getUbicacionesDisponibles();
        foreach ($locationIds as $locationId) {
            if (!isset($availableLocations[$locationId])) return self::respuesta(false, 'No tienes acceso a una de las ubicaciones seleccionadas.', 403);
        }
        $contexto = self::getContexto()['id'];
        if ($contexto !== null && (count($locationIds) !== 1 || $locationIds[0] !== $contexto)) {
            return self::respuesta(false, 'El evento debe pertenecer a la estación o departamento seleccionado.', 403);
        }

        try {
            $user = Auth::user();
            $id = DB::transaction(static function () use ($titulo, $tipo, $categoria, $proveedorExterno, $fecha, $hora, $locationIds, $correo, $data, $user): int {
                $eventId = (int) DB::table('op_refacciones_calendario')->insertGetId([
                    'titulo' => $titulo,
                    'tipo_evento' => $tipo,
                    'categoria' => $tipo === 'compra' ? $categoria : null,
                    'proveedor_externo' => $tipo === 'mantenimiento_externo' ? $proveedorExterno : null,
                    'fecha' => $fecha,
                    'hora' => $hora !== '' ? $hora : null,
                    'id_estacion' => $locationIds[0] ?? null,
                    'estado' => 'pendiente',
                    'correo_responsable' => $correo ?: null,
                    'notas' => trim((string) ($data['notas'] ?? '')) ?: null,
                    'id_usuario' => (int) ($user->id ?? 0),
                    'creado_en' => date('Y-m-d H:i:s'),
                ]);
                if ($tipo === 'compra' && self::calendarioTieneUbicacionesTabla()) {
                    DB::table('op_refacciones_calendario_ubicaciones')->insert(array_map(static fn (int $locationId): array => [
                        'id_evento' => $eventId,
                        'id_estacion' => $locationId,
                    ], $locationIds));
                }
                return $eventId;
            });
            return self::respuesta(true, 'Evento guardado.', 201, ['id' => $id]);
        } catch (\Throwable $exception) {
            error_log('[Refacciones] crearEvento: ' . $exception->getMessage());
            return self::respuesta(false, 'No se pudo guardar el evento.', 500);
        }
    }

    private static function cambiarExistencia(int $idRefaccion, int $idEstacion, float $delta, ?float $costoEntrada = null, ?array $lotesEntrada = null): array
    {
        $stock = DB::table('op_refacciones_existencias')->where('id_refaccion', $idRefaccion)
            ->where('id_estacion', $idEstacion)->lockForUpdate()->first();
        $existenciaAnterior = (float) ($stock->existencia ?? 0);
        $nuevoSaldo = round($existenciaAnterior + $delta, 2);
        if ($nuevoSaldo < 0) throw new InvalidArgumentException('La cantidad supera la existencia disponible.');

        $consumidos = [];
        if ($delta < 0) {
            $restante = round(abs($delta), 2);
            $layers = DB::table('op_refacciones_existencias_lotes')->where('id_refaccion', $idRefaccion)
                ->where('id_estacion', $idEstacion)->where('existencia', '>', 0)
                ->orderBy('id')->lockForUpdate()->get();
            foreach ($layers as $layer) {
                if ($restante <= 0.000001) break;
                $tomado = min($restante, (float) $layer->existencia);
                $saldoLote = round((float) $layer->existencia - $tomado, 2);
                DB::table('op_refacciones_existencias_lotes')->where('id', (int) $layer->id)->update(['existencia' => max(0, $saldoLote)]);
                $consumidos[] = ['costo_unitario' => (float) $layer->costo_unitario, 'existencia' => round($tomado, 2)];
                $restante = round($restante - $tomado, 2);
            }
            if ($restante > 0.000001) throw new InvalidArgumentException('Las capas de costo no cubren la existencia disponible. Actualiza el inventario inicial antes de continuar.');
        } elseif ($delta > 0) {
            if ($lotesEntrada !== null) {
                $cantidadCapas = round(array_sum(array_map(static fn (array $layer): float => (float) ($layer['existencia'] ?? 0), $lotesEntrada)), 2);
                if (abs($cantidadCapas - $delta) > 0.01) throw new InvalidArgumentException('El costo transferido no coincide con la cantidad del movimiento.');
                foreach ($lotesEntrada as $layer) {
                    $quantity = round((float) ($layer['existencia'] ?? 0), 2);
                    if ($quantity <= 0) continue;
                    DB::table('op_refacciones_existencias_lotes')->insert([
                        'id_refaccion' => $idRefaccion, 'id_estacion' => $idEstacion,
                        'costo_unitario' => round((float) ($layer['costo_unitario'] ?? 0), 2),
                        'existencia' => $quantity, 'registrado_en' => date('Y-m-d H:i:s'),
                    ]);
                }
            } else {
                $costoEntrada ??= (float) DB::table('op_refacciones_catalogo')->where('id', $idRefaccion)->value('costo_unitario');
                DB::table('op_refacciones_existencias_lotes')->insert([
                    'id_refaccion' => $idRefaccion, 'id_estacion' => $idEstacion,
                    'costo_unitario' => round($costoEntrada, 2), 'existencia' => round($delta, 2),
                    'registrado_en' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        $layerTotals = DB::table('op_refacciones_existencias_lotes')->where('id_refaccion', $idRefaccion)
            ->where('id_estacion', $idEstacion)->where('existencia', '>', 0)
            ->selectRaw('COALESCE(SUM(existencia), 0) as cantidad, COALESCE(SUM(existencia * costo_unitario), 0) as valor')->first();
        $layerQuantity = round((float) ($layerTotals->cantidad ?? 0), 2);
        if (abs($layerQuantity - $nuevoSaldo) > 0.01) throw new InvalidArgumentException('El inventario por capas no coincide con la existencia del producto.');
        $averageCost = $layerQuantity > 0 ? round((float) $layerTotals->valor / $layerQuantity, 2) : (float) ($stock->costo_promedio ?? 0);
        if ($stock) {
            DB::table('op_refacciones_existencias')->where('id', (int) $stock->id)
                ->update(['existencia' => $nuevoSaldo, 'costo_promedio' => $averageCost]);
        } else {
            DB::table('op_refacciones_existencias')->insert([
                'id_refaccion' => $idRefaccion, 'id_estacion' => $idEstacion,
                'existencia' => $nuevoSaldo, 'costo_promedio' => $averageCost, 'stock_minimo' => 0,
            ]);
        }
        return $consumidos;
    }

    /** Persiste las capas FIFO consumidas para valuar esta salida en Reportes. */
    private static function guardarCapasConsumidas(int $idDetalle, array $capas): void
    {
        foreach ($capas as $capa) {
            $cantidad = round((float) ($capa['existencia'] ?? 0), 2);
            if ($cantidad <= 0) continue;
            DB::table('op_refacciones_movimientos_detalle_costos')->insert([
                'id_movimiento_detalle' => $idDetalle,
                'costo_unitario' => round((float) ($capa['costo_unitario'] ?? 0), 2),
                'cantidad' => $cantidad,
                'registrado_en' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /** Promedio ponderado del costo FIFO realmente consumido por la salida. */
    private static function costoPromedioCapas(array $capas, float $cantidadTotal): float
    {
        if ($cantidadTotal <= 0) return 0.0;
        $valor = array_sum(array_map(
            static fn (array $capa): float => (float) ($capa['costo_unitario'] ?? 0) * (float) ($capa['existencia'] ?? 0),
            $capas
        ));
        return round($valor / $cantidadTotal, 2);
    }

    private static function obtenerCostoPromedio(int $idRefaccion, int $idEstacion): float
    {
        $stock = DB::table('op_refacciones_existencias')->where('id_refaccion', $idRefaccion)
            ->where('id_estacion', $idEstacion)->lockForUpdate()->first(['existencia', 'costo_promedio']);
        if (!$stock || (float) $stock->existencia <= 0) {
            throw new InvalidArgumentException('No hay existencia disponible para uno de los artículos.');
        }
        return (float) $stock->costo_promedio;
    }

    private static function parseLocation($value): ?int
    {
        if ($value === null || $value === '') return null;
        $id = filter_var($value, FILTER_VALIDATE_INT);
        return $id !== false && $id > 0 ? (int) $id : null;
    }

    private static function normalizarDetalles($detalles): array
    {
        if (is_string($detalles)) {
            $detalles = json_decode($detalles, true);
        }
        if (!is_array($detalles)) return [];

        $normalizados = [];
        foreach ($detalles as $detalle) {
            if (!is_array($detalle)) continue;
            $id = filter_var($detalle['id_refaccion'] ?? null, FILTER_VALIDATE_INT);
            $cantidad = filter_var($detalle['cantidad'] ?? null, FILTER_VALIDATE_FLOAT);
            if ($id !== false && $id > 0 && $cantidad !== false && $cantidad > 0) {
                $normalizados[] = ['id_refaccion' => (int) $id, 'cantidad' => round((float) $cantidad, 2)];
            }
        }
        return $normalizados;
    }

    private static function nuevoFolio(string $prefijo): string
    {
        return sprintf('RF-%s-%s-%s', $prefijo, date('Ymd'), strtoupper(bin2hex(random_bytes(3))));
    }

    private static function respuesta(bool $success, string $message, int $code, array $data = []): array
    {
        return ['success' => $success, 'message' => $message, 'code' => $code] + $data;
    }
}
