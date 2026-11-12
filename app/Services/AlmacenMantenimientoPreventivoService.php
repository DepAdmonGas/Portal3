<?php

namespace App\Services;

use App\Core\Session;
use App\Models\Estacion;
use App\Models\Operativo\MantenimientoPreventivo;
use App\Models\Operativo\MantenimientoPreventivoDocumento;
use App\Models\Operativo\MantenimientoPreventivoListado;
use App\Models\Usuario;

/**
 * Mantenimiento Preventivo (Almacén)
 *
 * Migración del módulo legacy public/admin/{vistas,modelo}/...-mantenimiento-preventivo.php
 * sobre la tabla op_mantenimiento_preventivo (+ _listado y _documentos).
 *
 * Decisiones de paridad con el legacy:
 *  - El folio es el MAX(folio)+1 por estación (el legacy lo calculaba así).
 *  - La estación NO se pide en los formularios: se toma del selector de contexto del
 *    módulo (ModuleStationService::getContext), igual que Calibración de Dispensarios.
 *  - El "tipo de mantenimiento" replica el selectize con creación del legacy: si el
 *    valor enviado coincide con un id o una descripción del catálogo se reutiliza;
 *    si no existe se crea en op_mantenimiento_preventivo_listado (estado = 0).
 *  - Fechas 0000-00-00: se conservan tal cual (el histórico las tiene). La columna
 *    "Fecha próximo mantenimiento" muestra "S/I" y la fecha vacía se muestra en blanco,
 *    igual que FormatoFecha() del legacy.
 *  - "Próxima prueba" = mes (en español) + año de fecha2; si fecha2 es 0000-00-00 se
 *    usa fecha, igual que el legacy.
 *  - Telegram replica el legacy: estación + Comodines, Comercializadora (6/7) y
 *    Mantenimiento. Como mejora aprobada, también notifica al AGREGAR (el legacy no) y
 *    al actualizar el estatus. NO se replica ClassEventos::registrarEvento().
 *  - El borrado físico de archivos SÍ se hace (el legacy dejaba huérfanos).
 *  - Los archivos de orden de servicio y de prueba de eficiencia se validan por
 *    extensión y MIME para no permitir ejecutables dentro de public/uploads.
 */
class AlmacenMantenimientoPreventivoService
{
    public const MODULE_KEY          = 'mantenimiento-preventivo';
    public const PARENT_MODULE       = 'almacen';
    public const DOWNLOAD_TIPO       = 'mantenimiento-preventivo';
    public const DOWNLOAD_TIPO_PRUEBA = 'prueba-eficiencia';

    public const UPLOAD_FOLDER        = 'public/uploads/archivos/mantenimiento-preventivo/';
    public const UPLOAD_FOLDER_PRUEBA = 'public/uploads/archivos/prueba-eficiencia/';

    public const STATUS_LABELS   = [0 => 'Pendiente', 1 => 'En proceso', 2 => 'Finalizado'];
    public const STATUS_BADGES   = [0 => 'bg-danger', 1 => 'bg-warning ', 2 => 'bg-success'];
    public const STATUS_TRANSITO = [
        0 => ['from' => 'Pendiente', 'to' => 'En proceso'],
        1 => ['from' => 'En proceso', 'to' => 'Finalizado'],
    ];

    /** Extensiones permitidas para la orden de servicio (documento administrativo). */
    public const ORDEN_EXTENSIONES = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'xls', 'xlsx', 'doc', 'docx'];

    /** MIME que nunca deben pasar, aunque la extensión diga otra cosa. */
    public const MIME_PELIGROSOS = [
        'application/x-php',
        'text/x-php',
        'application/x-executable',
        'application/x-dosexec',
        'application/x-msdownload',
        'text/html',
        'image/svg+xml',
        'application/x-sh',
        'application/javascript',
        'text/x-javascript',
        'text/javascript',
    ];

    /* ------------------------------------------------------------------ */
    /* Rutas y permisos                                                    */
    /* ------------------------------------------------------------------ */

    public static function getUploadDir(): string
    {
        $dir = dirname(__DIR__, 2) . '/' . self::UPLOAD_FOLDER;

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    public static function getUploadDirPrueba(): string
    {
        $dir = dirname(__DIR__, 2) . '/' . self::UPLOAD_FOLDER_PRUEBA;

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    public static function getPermisos(): array
    {
        $sesion   = Session::get('usuario') ?? [];
        $usuario  = \App\Core\Auth::user();
        $idPuesto = (int)($usuario->id_puesto ?? 0);

        return [
            'id_usuario'     => (int)($sesion['id'] ?? 0),
            'nombre_usuario' => (string)($sesion['nombre'] ?? ''),
            'id_puesto'      => $idPuesto,

            'puedeLeer'      => ModuloDptoOperativoService::validaPermiso(self::PARENT_MODULE, 'leer'),
            'puedeCrear'     => ModuloDptoOperativoService::validaPermiso(self::PARENT_MODULE, 'crear'),
            'puedeEditar'    => ModuloDptoOperativoService::validaPermiso(self::PARENT_MODULE, 'editar'),
            'puedeEliminar'  => ModuloDptoOperativoService::validaPermiso(self::PARENT_MODULE, 'eliminar'),
            'puedeDescargar' => ModuloDptoOperativoService::validaPermiso(self::PARENT_MODULE, 'descargar'),

            // El legacy elegía el breadcrumb por puesto: Encargado / Asistente Administrativo
            // veían "Almacén"; cualquier otro puesto veía "Mantenimiento".
            'origenMantenimiento' => !in_array($idPuesto, [6, 7], true),

            // Usuarios que pertenecen a una estación (Encargado / Asistente Administrativo):
            // en el dropdown de acciones sólo ven Calendario y Nuevo.
            'esUsuarioEstacion'    => in_array($idPuesto, [6, 7], true),
        ];
    }

    /**
     * Estaciones que el usuario puede ver, indexadas por id.
     * Es la única fuente de verdad: no se hardcodean estaciones.
     *
     * @return array<int, string>
     */
    public static function getEstacionesDisponibles(): array
    {
        $mapa = [];

        foreach (ModuleStationService::getAvailableStations(self::MODULE_KEY) as $s) {
            $mapa[(int)$s['id']] = (string)$s['nombre'];
        }

        return $mapa;
    }

    private static function nombreEstacion(int $idEstacion): string
    {
        $disponibles = self::getEstacionesDisponibles();

        if (isset($disponibles[$idEstacion])) {
            return $disponibles[$idEstacion];
        }

        $estacion = Estacion::find($idEstacion);

        return $estacion ? (string)$estacion->nombre : 'Estación #' . $idEstacion;
    }

    /* ------------------------------------------------------------------ */
    /* Catálogos                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Tipos de mantenimiento activos del catálogo.
     *
     * @return array<int, array{id: int, descripcion: string}>
     */
    public static function getTipos(): array
    {
        return MantenimientoPreventivoListado::where('estado', 0)
            ->orderBy('descripcion')
            ->get(['id', 'descripcion'])
            ->map(fn($t) => [
                'id'          => (int)$t->id,
                'descripcion' => (string)$t->descripcion,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Encargados disponibles para una estación: puestos 6 (Encargado), como el legacy.
     *
     * @return array<int, array{id: int, nombre: string}>
     */
    public static function getEncargados(int $idEstacion): array
    {
        $disponibles = self::getEstacionesDisponibles();

        if (!isset($disponibles[$idEstacion]) || $idEstacion <= 0) {
            return [];
        }

        return Usuario::where('id_gas', $idEstacion)
            ->where('id_puesto', 6)
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(fn($u) => [
                'id'     => (int)$u->id,
                'nombre' => (string)$u->nombre,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Resuelve el "tipo de mantenimiento" enviado por el usuario.
     *
     * El legacy (selectize con creación) enviaba el id si venía del catálogo o la
     * descripción si el usuario tecleaba una nueva. Se replica esa lógica:
     *  1. Si el valor es el id de un tipo activo, se usa.
     *  2. Si coincide con una descripción del catálogo, se usa su id.
     *  3. Si no existe, se crea (estado = 0) y se devuelve el id nuevo.
     */
    public static function resolveTipo(string $descripcion): ?int
    {
        $descripcion = trim($descripcion);

        if ($descripcion === '') {
            return null;
        }

        if (ctype_digit($descripcion)) {
            $existente = MantenimientoPreventivoListado::where('id', (int)$descripcion)
                ->where('estado', 0)
                ->first();

            if ($existente) {
                return (int)$existente->id;
            }
        }

        // La columna usa utf8_general_ci: la comparación es case-insensitive a nivel SQL.
        $existente = MantenimientoPreventivoListado::where('descripcion', $descripcion)
            ->where('estado', 0)
            ->first();

        if ($existente) {
            return (int)$existente->id;
        }

        $nuevo = MantenimientoPreventivoListado::create([
            'descripcion' => $descripcion,
            'estado'      => 0,
        ]);

        return $nuevo ? (int)$nuevo->id : null;
    }

    /* ------------------------------------------------------------------ */
    /* Lectura                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Listado de la tabla, con el mismo orden y formato que el legacy
     * (ORDER BY folio DESC).
     *
     * @param int|null $estacionFiltro null = todas las estaciones autorizadas
     */
    public static function getData(?int $estacionFiltro, int $year): array
    {
        $disponibles = self::getEstacionesDisponibles();

        if (empty($disponibles)) {
            return [];
        }

        $query = MantenimientoPreventivo::query()
            ->whereYear('fecha', $year);

        if ($estacionFiltro !== null && $estacionFiltro > 0) {
            // Autoridad en el backend: no se puede filtrar por una estación no autorizada.
            if (!isset($disponibles[$estacionFiltro])) {
                return [];
            }
            $query->where('id_estacion', $estacionFiltro);
        } else {
            $query->whereIn('id_estacion', array_keys($disponibles));
        }

        $rows = $query->orderByDesc('folio')
            ->orderByDesc('id')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $encargados = Usuario::whereIn('id', $rows->pluck('id_encargado')->unique()->filter())
            ->pluck('nombre', 'id')
            ->map(fn($n) => (string)$n)
            ->toArray();

        $tipos = MantenimientoPreventivoListado::whereIn('id', $rows->pluck('tipo_mantenimiento')->unique()->filter())
            ->pluck('descripcion', 'id')
            ->map(fn($n) => (string)$n)
            ->toArray();

        $uploadDir = self::getUploadDir();
        $data      = [];

        foreach ($rows as $r) {
            $idEstacion   = (int)$r->id_estacion;
            $folio        = (int)$r->folio;
            $ordenServicio = (string)($r->orden_servicio ?? '');
            $fecha        = (string)$r->fecha;
            $fecha2       = (string)$r->fecha2;
            $costo        = (float)($r->costo ?? 0);

            $data[] = [
                'id'                    => (int)$r->id,
                'id_estacion'           => $idEstacion,
                'estacion_nombre'       => $disponibles[$idEstacion] ?? ('Estación #' . $idEstacion),
                'folio'                 => $folio,
                'folio_label'           => '00' . $folio,
                'encargado'             => $encargados[(int)$r->id_encargado] ?? 'Sin asignar',
                'fecha_mantenimiento'   => self::fechaLarga($fecha),
                'proxima_fecha'         => $fecha2 === '0000-00-00' ? 'S/I' : self::fechaLarga($fecha2),
                'proxima_prueba'        => self::proximaPrueba($fecha, $fecha2),
                'orden_servicio'        => $ordenServicio,
                'orden_servicio_existe' => $ordenServicio !== '' && is_file($uploadDir . $ordenServicio),
                'tipo_mantenimiento'    => $tipos[(int)$r->tipo_mantenimiento] ?? 'Sin tipo',
                'costo'                 => $costo,
                'costo_label'           => number_format($costo, 2, '.', ','),
                'observaciones'         => trim((string)$r->observaciones) !== '' ? (string)$r->observaciones : 'Sin observaciones',
                'status'                => (int)$r->status,
                'status_label'          => self::STATUS_LABELS[(int)$r->status] ?? 'Desconocido',
                'status_badge'          => self::STATUS_BADGES[(int)$r->status] ?? 'bg-secondary',
            ];
        }

        return $data;
    }

    /**
     * Pendientes (status 0) agrupados por estación para el año indicado,
     * sólo sobre las estaciones autorizadas del usuario.
     *
     * Formato consumido por ModuleStationService::render (decora las opciones
     * del selector con "(N)") y por el badge "Pendientes" de la vista:
     * ['total' => int, 'estacion_X' => int]
     */
    public static function getPendientes(int $year): array
    {
        $disponibles = self::getEstacionesDisponibles();

        $mapa = ['total' => 0];
        foreach ($disponibles as $id => $nombre) {
            $mapa['estacion_' . $id] = 0;
        }

        if (empty($disponibles)) {
            return $mapa;
        }

        $rows = MantenimientoPreventivo::query()
            ->whereYear('fecha', $year)
            ->where('status', 0)
            ->whereIn('id_estacion', array_keys($disponibles))
            ->get(['id_estacion', 'status']);

        foreach ($rows as $r) {
            $key = 'estacion_' . (int)$r->id_estacion;
            if (isset($mapa[$key])) {
                $mapa[$key]++;
                $mapa['total']++;
            }
        }

        return $mapa;
    }

    /**
     * Registro editable, sólo si pertenece a una estación autorizada para el usuario.
     */
    public static function getDetalle(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $r = MantenimientoPreventivo::find($id);

        if (!$r) {
            return null;
        }

        $disponibles = self::getEstacionesDisponibles();
        $idEstacion  = (int)$r->id_estacion;

        if (!isset($disponibles[$idEstacion])) {
            return null;
        }

        $ordenServicio = (string)($r->orden_servicio ?? '');

        $encargado = 'Sin asignar';
        if ((int)$r->id_encargado > 0) {
            $u = Usuario::find((int)$r->id_encargado);
            if ($u) {
                $encargado = (string)$u->nombre;
            }
        }

        $tipo = MantenimientoPreventivoListado::find((int)$r->tipo_mantenimiento);

        return [
            'id'                    => (int)$r->id,
            'id_estacion'           => $idEstacion,
            'estacion_nombre'       => $disponibles[$idEstacion],
            'folio'                 => (int)$r->folio,
            'folio_label'           => '00' . (int)$r->folio,
            'id_encargado'          => (int)$r->id_encargado,
            'encargado'             => $encargado,
            'fecha'                 => (string)$r->fecha,
            'fecha2'                => (string)$r->fecha2,
            'orden_servicio'        => $ordenServicio,
            'orden_servicio_existe' => $ordenServicio !== '' && is_file(self::getUploadDir() . $ordenServicio),
            'id_tipo'               => (int)$r->tipo_mantenimiento,
            'tipo_descripcion'      => $tipo ? (string)$tipo->descripcion : '',
            'costo'                 => (float)($r->costo ?? 0),
            'observaciones'         => (string)$r->observaciones,
            'status'                => (int)$r->status,
            'status_label'          => self::STATUS_LABELS[(int)$r->status] ?? 'Desconocido',
        ];
    }

    /**
     * Documentos de "Prueba de Eficiencia" por estación y año (WHERE YEAR(fecha)).
     */
    public static function getDocumentos(?int $estacionFiltro, int $year): array
    {
        $disponibles = self::getEstacionesDisponibles();

        if ($estacionFiltro === null || $estacionFiltro <= 0 || !isset($disponibles[$estacionFiltro])) {
            return [];
        }

        $uploadDir = self::getUploadDirPrueba();
        $rows      = MantenimientoPreventivoDocumento::where('id_estacion', $estacionFiltro)
            ->whereYear('fecha', $year)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        $data = [];

        foreach ($rows as $r) {
            $archivo = (string)($r->archivo ?? '');

            $data[] = [
                'id'            => (int)$r->id,
                'fecha'         => self::fechaLarga((string)$r->fecha),
                'archivo'       => $archivo,
                'archivo_existe' => $archivo !== '' && is_file($uploadDir . $archivo),
            ];
        }

        return $data;
    }

    /* ------------------------------------------------------------------ */
    /* Calendario de mantenimiento                                         */
    /* ------------------------------------------------------------------ */

    /**
     * Eventos para el Calendario (FullCalendar), por estación y rango [inicio, fin].
     *
     * Cada registro genera un evento "mantenimiento" en `fecha` y, si fecha2 es válida,
     * un segundo evento "proximo" en `fecha2`. El color sigue el estatus.
     */
    public static function getCalendarioEventos(int $idEstacion, string $inicio, string $fin): array
    {
        $disponibles = self::getEstacionesDisponibles();

        if ($idEstacion <= 0 || !isset($disponibles[$idEstacion]) || $inicio === '' || $fin === '') {
            return [];
        }

        $mapaTipos = [];
        foreach (MantenimientoPreventivoListado::where('estado', 0)->get() as $t) {
            $mapaTipos[(int)$t->id] = $t->descripcion;
        }
        $mapaEncargados = [];
        $idsEncargados = array_column(self::getEncargados($idEstacion), 'id');
        if (!empty($idsEncargados)) {
            foreach (Usuario::whereIn('id', $idsEncargados)->get() as $u) {
                $mapaEncargados[$u->id] = $u->nombre;
            }
        }

        $porFecha = MantenimientoPreventivo::where('id_estacion', $idEstacion)
            ->whereBetween('fecha', [$inicio, $fin])
            ->get();
        $porFecha2 = MantenimientoPreventivo::where('id_estacion', $idEstacion)
            ->where('fecha2', '!=', '0000-00-00')
            ->whereBetween('fecha2', [$inicio, $fin])
            ->get();

        $idsUnicos = [];
        $eventos   = [];

        foreach ($porFecha as $r) {
            $idsUnicos[(int)$r->id] = true;
            $eventos[] = self::armarEvento($r, 'mantenimiento', (string)$r->fecha, $mapaTipos, $mapaEncargados);
        }

        foreach ($porFecha2 as $r) {
            if (!isset($idsUnicos[(int)$r->id])) {
                $idsUnicos[(int)$r->id] = true;
            }
            $eventos[] = self::armarEvento($r, 'proximo', (string)$r->fecha2, $mapaTipos, $mapaEncargados);
        }

        return [
            'eventos'  => $eventos,
            'totales'  => self::totalesCalendario($idsUnicos),
            'estacion' => $disponibles[$idEstacion],
        ];
    }

    /**
     * Mantenimientos registrados en un día concreto (fecha o próxima fecha).
     */
    public static function getCalendarioDia(int $idEstacion, string $fecha): array
    {
        $disponibles = self::getEstacionesDisponibles();

        if ($idEstacion <= 0 || !isset($disponibles[$idEstacion]) || $fecha === '') {
            return [];
        }

        $mapaTipos = [];
        foreach (MantenimientoPreventivoListado::where('estado', 0)->get() as $t) {
            $mapaTipos[(int)$t->id] = $t->descripcion;
        }
        $mapaEncargados = [];
        $idsEncargados = array_column(self::getEncargados($idEstacion), 'id');
        if (!empty($idsEncargados)) {
            foreach (Usuario::whereIn('id', $idsEncargados)->get() as $u) {
                $mapaEncargados[$u->id] = $u->nombre;
            }
        }

        $rows = MantenimientoPreventivo::where('id_estacion', $idEstacion)
            ->where(function ($q) use ($fecha) {
                $q->where('fecha', $fecha)
                    ->orWhere(function ($q2) use ($fecha) {
                        $q2->where('fecha2', '!=', '0000-00-00')->where('fecha2', $fecha);
                    });
            })
            ->get();

        $data = [];

        foreach ($rows as $r) {
            $esProximo   = (string)$r->fecha2 === $fecha && (string)$r->fecha !== $fecha;
            $idEst       = (int)$r->id_estacion;
            $status      = (int)$r->status;
            $folio       = (int)$r->folio;
            $idEncargado = (int)$r->id_encargado;

            $data[] = [
                'id'             => (int)$r->id,
                'folio_label'    => '00' . $folio,
                'tipo_titulo'    => $mapaTipos[(int)$r->tipo_mantenimiento] ?? 'Sin tipo',
                'es_proximo'     => $esProximo,
                'tipo_badge'     => $esProximo ? 'bg-info' : 'bg-primary',
                'tipo_fecha'     => $esProximo ? 'Próxima fecha' : 'Fecha mantenimiento',
                'fecha'          => self::fechaLarga((string)$r->fecha),
                'fecha2'         => (string)$r->fecha2 === '0000-00-00' ? 'S/I' : self::fechaLarga((string)$r->fecha2),
                'encargado'      => $mapaEncargados[$idEncargado] ?? 'Sin asignar',
                'costo'          => (float)($r->costo ?? 0),
                'costo_label'    => '$' . number_format((float)($r->costo ?? 0), 2, '.', ','),
                'observaciones'  => trim((string)$r->observaciones) !== '' ? (string)$r->observaciones : 'Sin observaciones',
                'status'         => $status,
                'status_label'   => self::STATUS_LABELS[$status] ?? 'Desconocido',
                'status_badge'   => self::STATUS_BADGES[$status] ?? 'bg-secondary',
            ];
        }

        return $data;
    }

    private static function armarEvento($r, string $tipo, string $fecha, array $mapaTipos, array $mapaEncargados): array
    {
        $idEst       = (int)$r->id_estacion;
        $folio       = (int)$r->folio;
        $status      = (int)$r->status;
        $idEncargado = (int)$r->id_encargado;
        $folioLabel  = '00' . $folio;
        $tipoDesc    = $mapaTipos[(int)$r->tipo_mantenimiento] ?? 'Sin tipo';

        return [
            'id'       => ($tipo === 'proximo' ? 'P' : 'M') . (int)$r->id,
            'title'    => ($tipo === 'proximo' ? 'Próx: ' : '') . $folioLabel . ' · ' . $tipoDesc,
            'start'    => $fecha,
            'allDay'   => true,
            'extendedProps' => [
                'id'            => (int)$r->id,
                'tipo'          => $tipo,
                'folio'         => $folioLabel,
                'nombre'        => $tipoDesc,
                'encargado'     => $mapaEncargados[$idEncargado] ?? 'Sin asignar',
                'costo'         => (float)($r->costo ?? 0),
                'costo_label'   => '$' . number_format((float)($r->costo ?? 0), 2, '.', ','),
                'observaciones' => trim((string)$r->observaciones) !== '' ? (string)$r->observaciones : 'Sin observaciones',
                'fecha_mantenimiento' => self::fechaLarga((string)$r->fecha),
                'proxima_fecha' => (string)$r->fecha2 === '0000-00-00' ? 'S/I' : self::fechaLarga((string)$r->fecha2),
                'status'        => $status,
                'status_label'  => self::STATUS_LABELS[$status] ?? 'Desconocido',
                'status_badge'  => self::STATUS_BADGES[$status] ?? 'bg-secondary',
                'calendar'      => $status === 2 ? 'Success' : ($status === 1 ? 'Warning' : 'Danger'),
            ],
        ];
    }

    private static function totalesCalendario(array $idsUnicos): array
    {
        $total      = count($idsUnicos);
        $pendientes = 0;
        $finalizados = 0;

        foreach ($idsUnicos as $id => $_) {
            $r = MantenimientoPreventivo::find($id);
            if ((int)$r->status === 2) {
                $finalizados++;
            } else {
                $pendientes++;
            }
        }

        return [
            'pendientes'  => $pendientes,
            'finalizados' => $finalizados,
            'total'       => $total,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Escritura                                                           */
    /* ------------------------------------------------------------------ */

    public static function store(array $data, array $files): array
    {
        $disponibles = self::getEstacionesDisponibles();

        $idEstacion = (int)(ModuleStationService::getContext(self::MODULE_KEY)['id_estacion'] ?? 0);

        if ($idEstacion <= 0) {
            return [
                'success' => false,
                'message' => 'Selecciona una estación en el filtro superior para registrar el mantenimiento.',
                'code'    => 422,
            ];
        }

        if (!isset($disponibles[$idEstacion])) {
            return ['success' => false, 'message' => 'La estación seleccionada no está disponible.', 'code' => 422];
        }

        $validacion = self::validarCampos($data, $files, true);

        if ($validacion['message'] !== null) {
            return ['success' => false, 'message' => $validacion['message'], 'code' => 422];
        }

        $idTipo = self::resolveTipo($validacion['tipo'] ?? '');

        if ($idTipo === null) {
            return ['success' => false, 'message' => 'El tipo de mantenimiento es obligatorio.', 'code' => 422];
        }

        $nombreArchivo = $validacion['archivo'];

        if ($nombreArchivo === '' || !self::guardarArchivoUploaded($files['Archivo_file'] ?? [], $nombreArchivo, self::getUploadDir())) {
            return ['success' => false, 'message' => 'No se pudo guardar la orden de servicio.', 'code' => 500];
        }

        $folio = self::siguienteFolio($idEstacion);

        try {
            $registro = MantenimientoPreventivo::create([
                'id_estacion'       => $idEstacion,
                'folio'             => $folio,
                'id_encargado'      => $validacion['id_encargado'],
                'fecha'             => $validacion['fecha'],
                'fecha2'            => $validacion['fecha2'],
                'orden_servicio'    => $nombreArchivo,
                'tipo_mantenimiento'=> $idTipo,
                'costo'             => $validacion['costo'],
                'observaciones'     => $validacion['observaciones'],
                'status'            => 0,
            ]);
        } catch (\Throwable $e) {
            @unlink(self::getUploadDir() . $nombreArchivo);
            error_log('[AlmacenMantenimientoPreventivo] store: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Error al registrar el mantenimiento.', 'code' => 500];
        }

        if (!$registro) {
            @unlink(self::getUploadDir() . $nombreArchivo);

            return ['success' => false, 'message' => 'No se pudo registrar el mantenimiento.', 'code' => 500];
        }

        self::notificar(
            $idEstacion,
            '✅',
            'agregó un nuevo registro de No. de folio: 00' . $folio . ' en el Mantenimiento Preventivo correspondiente al apartado de Almacén',
            $folio
        );

        return ['success' => true, 'message' => 'Mantenimiento preventivo registrado exitosamente.', 'code' => 200];
    }

    public static function update(int $id, array $data, array $files): array
    {
        $disponibles = self::getEstacionesDisponibles();
        $registro    = self::buscarAutorizado($id, $disponibles);

        if ($registro === null) {
            return ['success' => false, 'message' => 'Registro no encontrado.', 'code' => 404];
        }

        if ((int)$registro->status !== 0) {
            return ['success' => false, 'message' => 'Sólo se pueden editar registros en estado Pendiente.', 'code' => 422];
        }

        $validacion = self::validarCampos($data, $files, false);

        if ($validacion['message'] !== null) {
            return ['success' => false, 'message' => $validacion['message'], 'code' => 422];
        }

        $idTipo = self::resolveTipo($validacion['tipo'] ?? '');

        if ($idTipo === null) {
            return ['success' => false, 'message' => 'El tipo de mantenimiento es obligatorio.', 'code' => 422];
        }

        $archivoAnterior = (string)($registro->orden_servicio ?? '');
        $cambiaArchivo   = !empty($files['Archivo_file']['tmp_name']);

        // Si se subió un archivo nuevo se valida y se mueve; si no, se conserva el actual.
        if ($cambiaArchivo) {
            $nombreArchivo = self::validarArchivoOrden($files['Archivo_file']);

            if ($nombreArchivo === null) {
                return ['success' => false, 'message' => 'El archivo de orden de servicio no es válido.', 'code' => 422];
            }

            if (!self::guardarArchivoUploaded($files['Archivo_file'], $nombreArchivo, self::getUploadDir())) {
                return ['success' => false, 'message' => 'No se pudo guardar la orden de servicio.', 'code' => 500];
            }
        } else {
            $nombreArchivo = null;
        }

        try {
            $registro->id_encargado       = $validacion['id_encargado'];
            $registro->fecha              = $validacion['fecha'];
            $registro->fecha2             = $validacion['fecha2'];
            $registro->tipo_mantenimiento = $idTipo;
            $registro->costo              = $validacion['costo'];
            $registro->observaciones      = $validacion['observaciones'];

            if ($nombreArchivo !== null) {
                $registro->orden_servicio = $nombreArchivo;
            }

            $registro->save();
        } catch (\Throwable $e) {
            if ($nombreArchivo !== null) {
                @unlink(self::getUploadDir() . $nombreArchivo);
            }
            error_log('[AlmacenMantenimientoPreventivo] update: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Error al actualizar el registro.', 'code' => 500];
        }

        if ($nombreArchivo !== null && $archivoAnterior !== '' && $archivoAnterior !== $nombreArchivo) {
            @unlink(self::getUploadDir() . $archivoAnterior);
        }

        self::notificar(
            (int)$registro->id_estacion,
            '✏️',
            'modificó el registro de No. de folio: 00' . (int)$registro->folio . ' en el Mantenimiento Preventivo correspondiente al apartado de Almacén',
            (int)$registro->folio
        );

        return ['success' => true, 'message' => 'Registro actualizado exitosamente.', 'code' => 200];
    }

    public static function destroy(int $id): array
    {
        $disponibles = self::getEstacionesDisponibles();
        $registro    = self::buscarAutorizado($id, $disponibles);

        if ($registro === null) {
            return ['success' => false, 'message' => 'Registro no encontrado.', 'code' => 404];
        }

        if ((int)$registro->status === 2) {
            return ['success' => false, 'message' => 'No se puede eliminar un registro Finalizado.', 'code' => 422];
        }

        $idEstacion      = (int)$registro->id_estacion;
        $folio           = (int)$registro->folio;
        $archivoAnterior = (string)($registro->orden_servicio ?? '');

        try {
            $eliminado = $registro->delete();
        } catch (\Throwable $e) {
            error_log('[AlmacenMantenimientoPreventivo] destroy: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Error al eliminar el registro.', 'code' => 500];
        }

        if (!$eliminado) {
            return ['success' => false, 'message' => 'No se pudo eliminar el registro.', 'code' => 500];
        }

        // El legacy no borraba el archivo físico; se corrige para no dejar huérfanos.
        if ($archivoAnterior !== '') {
            @unlink(self::getUploadDir() . $archivoAnterior);
        }

        self::notificar(
            $idEstacion,
            '🗑',
            'eliminó el registro de No. de folio: 00' . $folio . ' en el Mantenimiento Preventivo correspondiente al apartado de Almacén',
            $folio
        );

        return ['success' => true, 'message' => 'Registro eliminado exitosamente.', 'code' => 200];
    }

    /**
     * Avanza el estatus: 0 → 1 (Pendiente → En proceso) y 1 → 2 (En proceso → Finalizado).
     */
    public static function actualizarStatus(int $id): array
    {
        $disponibles = self::getEstacionesDisponibles();
        $registro    = self::buscarAutorizado($id, $disponibles);

        if ($registro === null) {
            return ['success' => false, 'message' => 'Registro no encontrado.', 'code' => 404];
        }

        $statusActual = (int)$registro->status;

        if (!isset(self::STATUS_TRANSITO[$statusActual])) {
            return ['success' => false, 'message' => 'El registro ya está Finalizado.', 'code' => 422];
        }

        $transito = self::STATUS_TRANSITO[$statusActual];
        $nuevo    = $statusActual + 1;

        try {
            $registro->status = $nuevo;
            $registro->save();
        } catch (\Throwable $e) {
            error_log('[AlmacenMantenimientoPreventivo] actualizarStatus: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Error al actualizar el estatus.', 'code' => 500];
        }

        $idEstacion = (int)$registro->id_estacion;
        $folio      = (int)$registro->folio;

        self::notificar(
            $idEstacion,
            '🔄',
            'actualizó el estatus del registro con folio No. 00' . $folio . ' del Mantenimiento Preventivo correspondiente al apartado de Almacén.'
            . PHP_EOL . '🛠 Estado: ' . $transito['from'] . ' ➡ ' . $transito['to'],
            $folio
        );

        return ['success' => true, 'message' => 'Estatus actualizado: ' . $transito['from'] . ' ➡ ' . $transito['to'], 'code' => 200];
    }

    /**
     * Guarda una "Prueba de Eficiencia" para la estación del contexto y el año indicado.
     */
    public static function guardarDocumento(int $year, array $data, array $files): array
    {
        $disponibles = self::getEstacionesDisponibles();

        $idEstacion = (int)(ModuleStationService::getContext(self::MODULE_KEY)['id_estacion'] ?? 0);

        if ($idEstacion <= 0 || !isset($disponibles[$idEstacion])) {
            return ['success' => false, 'message' => 'Selecciona una estación en el filtro superior.', 'code' => 422];
        }

        $fecha = trim((string)($data['fecha'] ?? ''));

        if (!self::esFechaValida($fecha, false)) {
            return ['success' => false, 'message' => 'La fecha es obligatoria y debe ser válida.', 'code' => 422];
        }

        $nombreArchivo = self::validarArchivoPrueba($files['Archivo_file'] ?? []);

        if ($nombreArchivo === null) {
            return ['success' => false, 'message' => 'El archivo de prueba de eficiencia debe ser un PDF válido.', 'code' => 422];
        }

        if (!self::guardarArchivoUploaded($files['Archivo_file'], $nombreArchivo, self::getUploadDirPrueba())) {
            return ['success' => false, 'message' => 'No se pudo guardar el archivo.', 'code' => 500];
        }

        try {
            MantenimientoPreventivoDocumento::create([
                'id_estacion' => $idEstacion,
                'fecha'       => $fecha,
                'descripcion' => 'Prueba de eficiencia',
                'archivo'     => $nombreArchivo,
            ]);
        } catch (\Throwable $e) {
            @unlink(self::getUploadDirPrueba() . $nombreArchivo);
            error_log('[AlmacenMantenimientoPreventivo] guardarDocumento: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Error al guardar la prueba de eficiencia.', 'code' => 500];
        }

        return ['success' => true, 'message' => 'Prueba de eficiencia guardada exitosamente.', 'code' => 200];
    }

    public static function eliminarDocumento(int $id): array
    {
        $disponibles = self::getEstacionesDisponibles();
        $documento   = MantenimientoPreventivoDocumento::find($id);

        if (!$documento || !isset($disponibles[(int)$documento->id_estacion])) {
            return ['success' => false, 'message' => 'Documento no encontrado.', 'code' => 404];
        }

        $archivo = (string)($documento->archivo ?? '');

        try {
            $documento->delete();
        } catch (\Throwable $e) {
            error_log('[AlmacenMantenimientoPreventivo] eliminarDocumento: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Error al eliminar el documento.', 'code' => 500];
        }

        if ($archivo !== '') {
            @unlink(self::getUploadDirPrueba() . $archivo);
        }

        return ['success' => true, 'message' => 'Documento eliminado exitosamente.', 'code' => 200];
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    private static function buscarAutorizado(int $id, array $disponibles): ?MantenimientoPreventivo
    {
        if ($id <= 0) {
            return null;
        }

        $registro = MantenimientoPreventivo::find($id);

        if (!$registro) {
            return null;
        }

        return isset($disponibles[(int)$registro->id_estacion]) ? $registro : null;
    }

    /**
     * @return array{message: ?string, id_encargado: int, tipo: string, fecha: string, fecha2: string, costo: float, observaciones: string, archivo: string}
     */
    private static function validarCampos(array $data, array $files, bool $archivoObligatorio): array
    {
        $fail = function (string $message): array {
            return [
                'message'       => $message,
                'id_encargado'  => 0,
                'tipo'          => '',
                'fecha'         => '',
                'fecha2'        => '0000-00-00',
                'costo'         => 0,
                'observaciones' => '',
                'archivo'       => '',
            ];
        };

        $idEncargado = (int)($data['id_encargado'] ?? $data['Nombre'] ?? 0);

        if ($idEncargado <= 0) {
            return $fail('El encargado es obligatorio.');
        }

        $tipo = trim((string)($data['tipo_mantenimiento'] ?? $data['idMantenimiento'] ?? ''));

        if ($tipo === '') {
            return $fail('El tipo de mantenimiento es obligatorio.');
        }

        $fecha = trim((string)($data['fecha'] ?? $data['Fecha'] ?? ''));

        if (!self::esFechaValida($fecha, true)) {
            return $fail('La fecha de mantenimiento es obligatoria y debe ser válida.');
        }

        $fecha2 = trim((string)($data['fecha2'] ?? $data['Fecha2'] ?? ''));

        if ($fecha2 === '') {
            $fecha2 = '0000-00-00';
        } elseif (!self::esFechaValida($fecha2, false)) {
            return $fail('La fecha del próximo mantenimiento no es válida.');
        }

        $costoRaw = trim((string)($data['costo'] ?? $data['Costos'] ?? ''));

        if ($costoRaw === '' || !is_numeric($costoRaw) || (float)$costoRaw < 0) {
            return $fail('El costo es obligatorio y debe ser un número mayor o igual a cero.');
        }

        $observaciones = trim((string)($data['observaciones'] ?? $data['Observacion'] ?? ''));

        $file = $files['Archivo_file'] ?? [];

        if (!empty($file['tmp_name'])) {
            $nombre = self::validarArchivoOrden($file);

            if ($nombre === null) {
                return $fail('El archivo de orden de servicio no es válido.');
            }
        } elseif ($archivoObligatorio) {
            return $fail('El archivo de la orden de servicio es obligatorio.');
        } else {
            $nombre = '';
        }

        return [
            'message'       => null,
            'id_encargado'  => $idEncargado,
            'tipo'          => $tipo,
            'fecha'         => $fecha,
            'fecha2'        => $fecha2,
            'costo'         => (float)$costoRaw,
            'observaciones' => $observaciones,
            'archivo'       => $nombre,
        ];
    }

    /**
     * Valida la orden de servicio: extensión permitida + MIME no peligroso.
     * Devuelve el nombre definitivo (uniqid()-original) o null si no es válido.
     */
    private static function validarArchivoOrden(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        if ((int)($file['size'] ?? 0) <= 0) {
            return null;
        }

        $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));

        if (!in_array($extension, self::ORDEN_EXTENSIONES, true)) {
            return null;
        }

        if (!self::mimeSeguro((string)$file['tmp_name'])) {
            return null;
        }

        return uniqid() . '-' . basename((string)$file['name']);
    }

    /**
     * Valida el PDF de prueba de eficiencia.
     */
    private static function validarArchivoPrueba(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));

        if ($extension !== 'pdf') {
            return null;
        }

        if ((int)($file['size'] ?? 0) <= 0) {
            return null;
        }

        $validador = new FileValidatorService();

        if (!$validador->isValidMimeType((string)$file['tmp_name'], ['application/pdf'])) {
            return null;
        }

        return uniqid() . '-' . basename((string)$file['name']);
    }

    /**
     * Mueve el upload temporal a su destino final con su nombre definitivo.
     */
    private static function guardarArchivoUploaded(array $file, string $nombre, string $dir): bool
    {
        return move_uploaded_file((string)($file['tmp_name'] ?? ''), $dir . $nombre);
    }

    /**
     * Rechaza archivos cuyo MIME real (finfo) sea ejecutable, HTML, SVG o similar.
     */
    private static function mimeSeguro(string $tmpPath): bool
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return false;
        }

        $mime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        return $mime !== false && $mime !== '' && !in_array($mime, self::MIME_PELIGROSOS, true);
    }

    private static function siguienteFolio(int $idEstacion): int
    {
        return (int)MantenimientoPreventivo::where('id_estacion', $idEstacion)->max('folio') + 1;
    }

    private static function esFechaValida(string $fecha, bool $rechazarZero): bool
    {
        if ($fecha === '') {
            return false;
        }

        if ($fecha === '0000-00-00') {
            return !$rechazarZero;
        }

        $d = \DateTime::createFromFormat('Y-m-d', $fecha);

        return $d !== false && $d->format('Y-m-d') === $fecha;
    }

    private static function fechaLarga(string $fecha): string
    {
        if ($fecha === '' || $fecha === '0000-00-00') {
            return '';
        }

        return formatearFechaLarga($fecha);
    }

    /**
     * "Próxima prueba" del legacy: nombremes() + año de fecha2, con fallback a fecha.
     * El legacy tomaba mes/año numérico y los formateaba con nombremes().
     */
    private static function proximaPrueba(string $fecha, string $fecha2): string
    {
        $base = ($fecha2 !== '0000-00-00' && $fecha2 !== '') ? $fecha2 : $fecha;

        if ($base === '' || $base === '0000-00-00') {
            return 'S/I';
        }

        $d = \DateTime::createFromFormat('Y-m-d', $base);

        if ($d === false) {
            return 'S/I';
        }

        return nombremes((string)(int)$d->format('m')) . ' ' . $d->format('Y');
    }

    /**
     * Réplica del legacy (getChatIdUsuarios + Comercializadora 6/7 + Mantenimiento).
     *
     * No se usa TelegramService::notificar() porque no incluye Mantenimiento y agrega
     * Contabilidad, que el legacy no notificaba. No se modifica el servicio global.
     */
    private static function notificar(int $idEstacion, string $icono, string $texto, int $folio): void
    {
        try {
            $telegram = new TelegramService();
            $actor    = (int)(Session::get('usuario')['id'] ?? 0);
            $autor    = (string)(Session::get('usuario')['nombre'] ?? '');

            $destinos = $telegram->getUserIdsByStationWithComodines($idEstacion, $actor);

            if (in_array($idEstacion, [6, 7], true)) {
                $destinos = array_merge($destinos, $telegram->getUserIdsComercializadora($actor));
            }

            $destinos = array_merge($destinos, $telegram->getUserIdsMantenimiento($actor));

            // El SQL del legacy era "... ) AND id <> ?": la exclusión del actor se aplicaba
            // al conjunto completo. Se filtra explícitamente para conservar la semántica.
            $destinos = array_values(array_unique(array_diff($destinos, [$actor])));

            $mensaje = $icono . ' ' . $autor . ' ' . $texto
                . PHP_EOL . PHP_EOL . '⛽ Estación: ' . self::nombreEstacion($idEstacion) . '.';

            foreach ($destinos as $idUsuario) {
                $telegram->sendTokenAsync((int)$idUsuario, $mensaje);
            }
        } catch (\Throwable $e) {
            error_log('[AlmacenMantenimientoPreventivo] Telegram: ' . $e->getMessage());
        }
    }
}