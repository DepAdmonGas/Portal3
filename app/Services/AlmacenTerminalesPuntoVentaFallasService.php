<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Estacion;
use App\Models\Operativo\TerminalesTpv;
use App\Models\Operativo\TerminalesTpvReporte;
use App\Models\Usuario;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Fallas de Terminales Punto de Venta — Almacén
 *
 * Migración del legacy `4-almacen/tpv` (falla-tpv.php, falla-tpv-estaciones.php,
 * modal-nueva-falla, modal-editar/detalle-falla, modal-anexos y
 * modal-comentarios) más los modelos públicos/admin del mismo módulo.
 *
 * Reglas heredadas del legacy:
 *  - status 0 = Pendiente (rosa), 1 = En reparación (amarillo), 2 = Finalizado (verde).
 *  - "Actualizar estatus" sólo es operable sobre fallas pendientes.
 *  - Sólo Encargado / Asistente Administrativo finalizan directamente; el resto
 *    pasa por la estación.
 *  - La persona que atiende puede coming del catálogo de ControlUx (635, 650)
 *    o escribirse libre; nunca ambas.
 *  - Factura, cotización y anexos viven en public/uploads/archivos/tpv/.
 */
class AlmacenTerminalesPuntoVentaFallasService
{
    /** El módulo comparte la configuración y permisos con el listado de TPV. */
    public const MODULE_KEY    = 'terminales-punto-venta';
    public const PARENT_MODULE = 'almacen';

    public const BASE_URL = '/departamento-operativo/almacen/terminales-punto-venta-fallas';

    /**
     * Carpetas de archivo (factura/cotización de la falla y anexos).
     *
     * La base es /public/uploads/archivos/ y DENTRO replica la estructura de la
     * carpeta "archivos" del módulo anterior, sin agregar niveles extras:
     *   - factura / cotización -> archivos/                 (raíz, igual que antes)
     *   - anexos                -> archivos/tpv/anexos/     (misma ruta relativa)
     *
     * Así, para migrar basta con arrastrar el contenido de "archivos/" del
     * módulo anterior dentro de /public/uploads/archivos/ y todo coincide.
     */
    public const UPLOAD_FOLDER        = 'public/uploads/archivos/';
    public const UPLOAD_FOLDER_ANEXOS = 'public/uploads/archivos/tpv/anexos/';
    public const DOWNLOAD_TIPO        = 'tpv';

    public const STATUS = [
        0 => 'Pendiente',
        1 => 'En reparación',
        2 => 'Finalizado',
    ];

    public const STATUS_BADGES = [
        0 => 'bg-danger',
        1 => 'bg-warning',
        2 => 'bg-success text-white',
    ];

    public const ARCHIVO_EXTENSIONES = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'xls', 'xlsx', 'doc', 'docx'];

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

    /** Catálogo de fallas del legacy (modal-nueva-falla-terminales-tpv.php). */
    public const CATALOGO_FALLAS = [
        'Falla en impresora',
        'Se le va la señal',
        'La pila no retiene carga',
        'Centro de carga',
        'Ya no prende',
        'Se cayó',
        'Se mojó',
        'Falla en la botonera',
        'Falla en el touch',
        'Se pasmó',
        'Se traba',
        'Cargador',
        'No genera cierre de lote',
    ];

    /** Puestos que en el legacy finalizan la falla directamente. */
    private const PUESTOS_FINALIZAN = [6, 7];

    /** Personal de ControlUx que puede atender una falla (legacy: 635, 650). */
    private const IDS_PERSONAL_CONTROLUX = [635, 650];

    /**
     * Texto que identifica las terminales de este personal.
     * Se busca sin distinguir mayúsculas y con espacios tolerantes, porque
     * en la base conviven "CONTROL VOLUMETRICO", "T.CONTROLUX T 3",
     * "SOPORTE CONTROLUX" y variantes con espacios al final.
     */
    private const PATRONES_CONTROLUX = ['CONTROLUX', 'VOLUMETRICO'];

    /** Columnas de la terminal donde puede aparecer ese texto. */
    private const COLUMNAS_CONTROLUX = ['tpv', 'no_afiliacion', 'telefono'];

    /**
     * ¿El usuario en sesión es parte del personal de ControlUx?
     * Solo a ellos se les restringe el listado de terminales.
     */
    public static function esPersonalControlux(): bool
    {
        $sessionUsuario = Session::get('usuario');
        $idUsuario = (int)($sessionUsuario['id'] ?? 0);

        return in_array($idUsuario, self::IDS_PERSONAL_CONTROLUX, true);
    }

    /**
     * Aplica el filtro de terminales al query, solo si el usuario es de
     * ControlUx. Se invoca sobre queries de op_terminales_tpv o sobre un
     * query que ya la une con alias "t".
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param string $alias Alias de la tabla de terminales ("t" por defecto).
     * @return \Illuminate\Database\Query\Builder
     */
    private static function aplicarFiltroControlux($query, string $alias = 't')
    {
        if (!self::esPersonalControlux()) {
            return $query;
        }

        $columnas = [];
        foreach (self::COLUMNAS_CONTROLUX as $col) {
            $columnas[] = $alias . '.' . $col;
        }

        return $query->where(function ($q) use ($columnas) {
            foreach (self::PATRONES_CONTROLUX as $patron) {
                foreach ($columnas as $col) {
                    $q->orWhere($col, 'LIKE', '%' . $patron . '%');
                }
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /* Permisos                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Idéntico a AlmacenTerminalesPuntoVentaService: la falla vive dentro del
     * mismo módulo, así que hereda leer/crear/editar/eliminar/descargar.
     */
    public static function getPermisos(): array
    {
        return AlmacenTerminalesPuntoVentaService::getPermisos();
    }

    /**
     * El legacy sólo habilitaba "Actualizar estatus" (y el alta de fallas) a
     * Encargado / Asistente Administrativo.
     */
    public static function puedeOperar(): bool
    {
        $usuario = Auth::user();
        $idPuesto = (int)($usuario->id_puesto ?? 0);

        return in_array($idPuesto, self::PUESTOS_FINALIZAN, true);
    }

    /**
     * Quién puede cambiar el estatus de una falla, según su estado actual:
     *   - Pendiente      → los usuarios multiestación
     *   - En reparación  → los usuarios que no son multiestación
     *   - Finalizado     → nadie
     *
     * Quien puede actualizar, además, avanza la falla un paso al finalizar.
     */
    public static function puedeActualizarFalla(int $status): bool
    {
        if (!self::getPermisos()['puedeEditar']) {
            return false;
        }

        return MultiestacionService::isEnabled()
            ? $status === 0
            : $status === 1;
    }

    /** Una falla finalizada no se puede eliminar; las demás, con permiso. */
    public static function puedeEliminarFalla(int $status): bool
    {
        if (!self::getPermisos()['puedeEliminar']) {
            return false;
        }

        return $status !== 2;
    }

    /**
     * Estado resultante: "editar" conserva el actual, "finalizar" avanza un paso
     * (Pendiente → En reparación → Finalizado) sin pasarse del último.
     */
    public static function siguienteStatus(int $statusActual, string $accion): int
    {
        if ($accion !== 'finalizar') {
            return $statusActual;
        }

        return min($statusActual + 1, 2);
    }

    /**
     * Sólo reportan fallas los usuarios que pertenecen a una id_gas concreta:
     * los usuarios multiestación sólo consultan el historial de la estación y
     * nunca capturan una falla de una terminal puntual.
     *
     * Además la estación del contexto debe ser la de la terminal.
     */
    public static function puedeReportarFalla(?int $idEstacion): bool
    {
        if ($idEstacion === null || $idEstacion <= 0) {
            return false;
        }

        if (!self::getPermisos()['puedeCrear']) {
            return false;
        }

        if (MultiestacionService::isEnabled()) {
            return false;
        }

        // Con el contexto resuelto por id_gas basta con comparar estaciones.
        $contexto = ModuleStationService::getContext(self::MODULE_KEY);

        return $contexto['id_estacion'] !== null
            && (int)$contexto['id_estacion'] === $idEstacion;
    }

    public static function getEstacionesDisponibles(): array
    {
        return AlmacenTerminalesPuntoVentaService::getEstacionesDisponibles();
    }

    public static function nombreEstacion(int $idEstacion): string
    {
        $disponibles = self::getEstacionesDisponibles();

        if (isset($disponibles[$idEstacion])) {
            return $disponibles[$idEstacion];
        }

        $estacion = Estacion::find($idEstacion);

        return $estacion ? (string)$estacion->nombre : 'Estación #' . $idEstacion;
    }

    /* ------------------------------------------------------------------ */
    /* Autorización                                                        */
    /* ------------------------------------------------------------------ */

    /** Terminal dentro de las estaciones autorizadas; null si no existe o no se puede ver. */
    public static function buscarTerminalAutorizado(int $idTpv): ?TerminalesTpv
    {
        if ($idTpv <= 0) {
            return null;
        }

        $disponibles = self::getEstacionesDisponibles();

        if (empty($disponibles)) {
            return null;
        }

        $query = TerminalesTpv::where('id', $idTpv)
            ->whereIn('id_estacion', array_keys($disponibles));

        // El personal de ControlUx solo alcanza las terminales de su texto.
        self::aplicarFiltroControlux($query, (new TerminalesTpv())->getTable());

        return $query->first();
    }

    /** Falla que pertenece a una terminal autorizada; null si no existe o no se puede ver. */
    public static function buscarFallaAutorizada(int $idFalla): ?TerminalesTpvReporte
    {
        if ($idFalla <= 0) {
            return null;
        }

        $falla = TerminalesTpvReporte::find($idFalla);

        if (!$falla) {
            return null;
        }

        return self::buscarTerminalAutorizado((int)$falla->id_tpv) ? $falla : null;
    }

    /* ------------------------------------------------------------------ */
    /* Lectura                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Ficha de la terminal + su estación, para el encabezado de la vista.
     *
     * @return array<string, mixed>|null
     */
    public static function getTerminal(int $idTpv): ?array
    {
        $t = self::buscarTerminalAutorizado($idTpv);

        if (!$t) {
            return null;
        }

        return [
            'id'               => (int)$t->id,
            'id_estacion'      => (int)$t->id_estacion,
            'estacion_nombre'  => self::nombreEstacion((int)$t->id_estacion),
            'tpv'              => (string)$t->tpv,
            'no_serie'         => (string)$t->no_serie,
            'modelo'           => (string)$t->modelo,
            'tipo_conexion'    => (string)$t->tipo_conexion,
            'no_afiliacion'    => (string)$t->no_afiliacion,
            'estatus_tpv'      => (string)$t->estatus_tpv,
        ];
    }

    /**
     * Historial de fallas de UNA terminal (legacy falla-tpv.php → lista-reporte.php).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getDataFallas(int $idTpv): array
    {
        $terminal = self::buscarTerminalAutorizado($idTpv);

        if (!$terminal) {
            return [];
        }

        return self::consultaFallas((int)$terminal->id_estacion, (int)$terminal->id);
    }

    /**
     * Historial de fallas de la estación del contexto (legacy falla-tpv-estaciones).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getDataFallasEstacion(?int $idEstacion): array
    {
        $disponibles = self::getEstacionesDisponibles();

        if (empty($disponibles)) {
            return [];
        }

        // Autoridad en el backend: nunca leer una estación no autorizada.
        if ($idEstacion !== null && $idEstacion > 0) {
            if (!isset($disponibles[$idEstacion])) {
                return [];
            }
            return self::consultaFallas($idEstacion, null);
        }

        $salida = [];
        foreach (array_keys($disponibles) as $id) {
            $salida = array_merge($salida, self::consultaFallas((int)$id, null));
        }

        usort($salida, static function (array $a, array $b): int {
            return strcmp((string)$b['fechacreacion_raw'], (string)$a['fechacreacion_raw']);
        });

        return $salida;
    }

    /**
     * Consulta común: une la falla con su terminal y con los usuarios que la
     * registraron / atendieron, y agrega el conteo de comentarios y anexos.
     */
    private static function consultaFallas(int $idEstacion, ?int $idTpv): array
    {
        $query = DB::table('op_terminales_tpv_reporte as r')
            ->leftJoin('op_terminales_tpv as t', 't.id', '=', 'r.id_tpv')
            // Columnas explícitas: con select * la columna "id" de la terminal
            // sobrescribiría la de la falla y el id devuelto sería el equivocado.
            ->select('r.*', 't.tpv as tpv', 't.no_serie as no_serie')
            ->where('t.id_estacion', $idEstacion);

        // El personal de ControlUx solo ve fallas de terminales de su texto.
        self::aplicarFiltroControlux($query, 't');

        if ($idTpv !== null) {
            $query->where('r.id_tpv', $idTpv);
        }

        $filas = $query->orderBy('r.id', 'desc')->get();

        $salida = [];

        foreach ($filas as $f) {
            $idFalla = (int)$f->id;

            $comentarios = (int)DB::table('op_comentarios_falla_tpv')
                ->where('id_tpv_reporte', $idFalla)->count();

            $anexos = (int)DB::table('op_terminales_tpv_anexos')
                ->where('id_tpv_reporte', $idFalla)->count();

            $status = (int)$f->status;

            $diaReporte = (string)$f->dia_reporte;
            $fecha = self::esFechaCero($diaReporte)
                ? substr((string)$f->fechacreacion, 0, 10)
                : $diaReporte;

            $atiendeControlux = $f->atiende_controlux !== null ? (int)$f->atiende_controlux : 0;

            $salida[] = [
                'id'                => $idFalla,
                'id_tpv'            => (int)$f->id_tpv,
                'estacion_id'       => $idEstacion,
                'estacion_nombre'   => self::nombreEstacion($idEstacion),
                'tpv'               => (string)$f->tpv,
                'no_serie'          => (string)$f->no_serie,

                'reporta'           => self::nombreUsuario((int)$f->id_usuario),
                'fecha_label'       => formatearFecha($fecha),
                'fecha_raw'         => $fecha,
                'fechacreacion_raw' => (string)$f->fechacreacion,
                'no_reporte'        => self::texto($f->no_reporte, 'S/I'),
                'atiende'           => self::texto($f->atiende, self::nombreUsuario($atiendeControlux)),
                'falla'             => self::texto($f->falla, 'Sin información'),
                'vida_util'         => self::texto($f->vida_util, 'S/I'),

                'factura'           => (string)$f->factura,
                'cotizacion'        => (string)$f->cotizacion,

                'status'            => $status,
                'status_label'      => self::STATUS[$status] ?? 'Desconocido',
                'status_badge'      => self::STATUS_BADGES[$status] ?? 'bg-secondary-subtle text-secondary',

                'comentarios'       => $comentarios,
                'anexos'            => $anexos,

                // Quién puede tocar cada acción, según el estado y el tipo de
                // usuario (regla de estados del módulo).
                'puede_actualizar'  => self::puedeActualizarFalla($status),
                'puede_eliminar'    => self::puedeEliminarFalla($status),
            ];
        }

        return $salida;
    }

    /** Detalle completo de una falla (modal-detalle-falla-terminales-tpv.php). */
    public static function getDetalle(int $idFalla): ?array
    {
        $f = self::buscarFallaAutorizada($idFalla);

        if (!$f) {
            return null;
        }

        $terminal = self::buscarTerminalAutorizado((int)$f->id_tpv);
        $atiendeControlux = $f->atiende_controlux !== null ? (int)$f->atiende_controlux : 0;
        $status = (int)$f->status;

        $diaReporte = self::esFechaCero((string)$f->dia_reporte) ? '' : (string)$f->dia_reporte;
        $fecha = $diaReporte === ''
            ? substr((string)$f->fechacreacion, 0, 10)
            : $diaReporte;

        return [
            'id'              => (int)$f->id,
            'id_tpv'          => (int)$f->id_tpv,
            'tpv'             => $terminal ? (string)$terminal->tpv : '',
            'no_serie'        => $terminal ? (string)$terminal->no_serie : '',
            'estacion_nombre' => $terminal ? self::nombreEstacion((int)$terminal->id_estacion) : '',

            'reporta'         => self::nombreUsuario((int)$f->id_usuario),
            'fecha_label'     => formatearFecha($fecha),
            'dia_reporte'     => $fecha,
            // DÍA DEPORTE: la fecha del reporte ya resuelta (dia_reporte o la de
            // creación cuando aquél venía en 0000-00-00), siempre formateada.
            'dia_reporte_label' => formatearFecha($fecha) !== ''
                ? formatearFecha($fecha)
                : formatearFecha(substr((string)$f->fechacreacion, 0, 10)),
            'dia_solucion'    => formatearFecha($f->dia_solucion),
            'costo_label'     => '$' . number_format((float)$f->costo, 2),

            'falla'           => (string)$f->falla,
            'vida_util'       => (string)$f->vida_util,
            'no_reporte'      => (string)$f->no_reporte,
            'observaciones'   => (string)$f->observaciones,
            'atiende'         => (string)$f->atiende,
            'atiende_controlux' => $atiendeControlux,
            'atiende_label'   => self::texto($f->atiende, self::nombreUsuario($atiendeControlux)),

            'factura'         => (string)$f->factura,
            'cotizacion'      => (string)$f->cotizacion,

            'status'          => $status,
            'status_label'    => self::STATUS[$status] ?? 'Desconocido',
            'status_badge'    => self::STATUS_BADGES[$status] ?? 'bg-secondary-subtle text-secondary',

            'puede_actualizar' => self::puedeActualizarFalla($status),
            'puede_eliminar'   => self::puedeEliminarFalla($status),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Catálogos                                                           */
    /* ------------------------------------------------------------------ */

    /** Personal de ControlUx que puede atender una falla. */
    public static function getPersonalControlux(): array
    {
        return Usuario::whereIn('id', self::IDS_PERSONAL_CONTROLUX)
            ->orderBy('id')
            ->get(['id', 'nombre'])
            ->map(static fn ($u) => [
                'id'     => (int)$u->id,
                'nombre' => (string)$u->nombre,
            ])
            ->all();
    }

    /**
     * Catálogo de fallas: el del legacy más los valores que ya existen en la
     * tabla, para no perder una falla registrada como texto libre.
     *
     * @return array<int, string>
     */
    public static function getCatalogoFallas(): array
    {
        $base = self::CATALOGO_FALLAS;

        try {
            $libres = DB::table('op_terminales_tpv_reporte')
                ->whereNotNull('falla')->distinct()->pluck('falla')->all();

            foreach ($libres as $valor) {
                $valor = trim((string)$valor);
                if ($valor === '') {
                    continue;
                }
                if (!in_array($valor, $base, true)) {
                    $base[] = $valor;
                }
            }
        } catch (\Throwable $e) {
            // Si el catálogo libre no está disponible, el legacy sigue sirviendo.
        }

        return $base;
    }

    public static function getCatalogos(): array
    {
        return [
            'fallas'   => self::getCatalogoFallas(),
            'controlux' => self::getPersonalControlux(),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Utilidades de presentación                                          */
    /* ------------------------------------------------------------------ */

    private static function texto($valor, string $alternativa): string
    {
        $valor = trim((string)$valor);

        return $valor === '' ? $alternativa : $valor;
    }

    /**
     * MySQL guarda las fechas sin uso como '0000-00-00', que el cast date de
     * Eloquent entrega como '-0001-11-30'. Ambas cuentan como "sin fecha".
     */
    private static function esFechaCero(string $fecha): bool
    {
        $fecha = trim($fecha);

        return $fecha === ''
            || str_starts_with($fecha, '0000')
            || str_contains($fecha, '-0001');
    }

    public static function nombreUsuario(int $idUsuario): string
    {
        if ($idUsuario <= 0) {
            return 'S/I';
        }

        $u = Usuario::find($idUsuario);

        return $u ? (string)$u->nombre : 'S/I';
    }

    /* ------------------------------------------------------------------ */
    /* Escritura: alta de falla                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Alta de falla (legacy reporte-terminal-tpv.php): deja el registro en
     * status 0 (Pendiente) con la fecha del día.
     */
    public static function storeFalla(int $idTpv, string $falla): array
    {
        $terminal = self::buscarTerminalAutorizado($idTpv);

        if (!$terminal) {
            return ['success' => false, 'code' => 404, 'message' => 'Terminal no encontrada.'];
        }

        $falla = trim($falla);

        if ($falla === '') {
            return ['success' => false, 'code' => 422, 'message' => 'Describe la falla de la terminal.'];
        }

        $sesion  = Session::get('usuario') ?? [];
        $idUsuario = (int)($sesion['id'] ?? 0);

        $registro = TerminalesTpvReporte::create([
            'id_tpv'       => (int)$terminal->id,
            'id_usuario'   => $idUsuario,
            'falla'        => $falla,
            'atiende'      => '',
            'no_reporte'   => '',
            'dia_reporte'  => date('Y-m-d'),
            'dia_solucion' => '0000-00-00',
            'costo'        => 0,
            'vida_util'    => '',
            'factura'      => '',
            'cotizacion'   => '',
            'serie'        => '',
            'modelo'       => '',
            'conexion'     => '',
            'observaciones' => '',
            'status'       => 0,
        ]);

        self::notificar(
            'agregar',
            (int)$terminal->id_estacion,
            ['tpv' => (string)$terminal->tpv, 'no_serie' => (string)$terminal->no_serie],
            'Pendiente'
        );

        return [
            'success' => true,
            'code'    => 200,
            'message' => 'La falla ha sido agregada exitosamente.',
            'id'      => (int)$registro->id,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Escritura: edición / finalización                                   */
    /* ------------------------------------------------------------------ */

    /**
     * Normaliza la entrada de la falla. `accion` = 'editar' deja el status como
     * está; 'finalizar' lo pasa a 1 (En reparación), igual que el legacy.
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    public static function normalizarEntradaFalla(array $input): array
    {
        $datos = [
            'falla'           => trim((string)($input['falla'] ?? '')),
            'vida_util'       => trim((string)($input['vida_util'] ?? '')),
            'no_reporte'      => trim((string)($input['no_reporte'] ?? '')),
            'observaciones'   => trim((string)($input['observaciones'] ?? '')),
            'atiende'         => trim((string)($input['atiende'] ?? '')),
            'atiende_controlux' => (int)($input['atiende_controlux'] ?? 0),
        ];

        $errores = [];

        if ($datos['falla'] === '') {
            $errores['falla'] = 'Describe la falla de la terminal.';
        }

        if ($datos['vida_util'] === '') {
            $errores['vida_util'] = 'Captura la vida útil o garantía.';
        }

        // La persona que atiende se elige del catálogo O se escribe; nunca ambas.
        if ($datos['atiende_controlux'] > 0) {
            $datos['atiende'] = '';
        } elseif ($datos['atiende'] === '') {
            $errores['atiende'] = 'Selecciona o escribe la persona que atiende.';
        }

        if ($datos['no_reporte'] === '') {
            $errores['no_reporte'] = 'Captura el número de reporte.';
        }

        return [$datos, $errores];
    }

    /**
     * Editar o finalizar una falla. Acepta factura/cotización opcionales.
     */
    public static function updateFalla(int $idFalla, array $input, array $files = [], string $accion = 'editar'): array
    {
        $falla = self::buscarFallaAutorizada($idFalla);

        if (!$falla) {
            return ['success' => false, 'code' => 404, 'message' => 'Falla no encontrada.'];
        }

        if (!self::puedeActualizarFalla((int)$falla->status)) {
            return [
                'success' => false,
                'code'    => 403,
                'message' => 'No tienes permiso para actualizar el estatus de esta falla.',
            ];
        }

        [$datos, $errores] = self::normalizarEntradaFalla($input);

        if (!empty($errores)) {
            return [
                'success' => false,
                'code'    => 422,
                'message' => 'Revisa los campos obligatorios.',
                'errors'  => $errores,
            ];
        }

        $directorio = self::getUploadDir();

        // Factura / cotización: sólo se reemplazan si se sube un archivo nuevo.
        $factura    = self::guardarOpcional($files, 'factura', $directorio, (string)$falla->factura);
        $cotizacion = self::guardarOpcional($files, 'cotizacion', $directorio, (string)$falla->cotizacion);

        $falla->falla            = $datos['falla'];
        $falla->atiende          = $datos['atiende'];
        $falla->atiende_controlux = $datos['atiende_controlux'] > 0 ? $datos['atiende_controlux'] : null;
        $falla->no_reporte       = $datos['no_reporte'];
        $falla->vida_util        = $datos['vida_util'];
        $falla->observaciones    = $datos['observaciones'];

        if ($factura !== null) {
            $falla->factura = $factura;
        }

        if ($cotizacion !== null) {
            $falla->cotizacion = $cotizacion;
        }

        if ($accion === 'finalizar') {
            $nuevoStatus = self::siguienteStatus((int)$falla->status, 'finalizar');

            if ($nuevoStatus !== (int)$falla->status) {
                $falla->status = $nuevoStatus;

                if ($nuevoStatus === 2 && self::esFechaCero((string)$falla->dia_solucion)) {
                    $falla->dia_solucion = date('Y-m-d');
                }
            }
        }

        $falla->save();

        $terminal = self::buscarTerminalAutorizado((int)$falla->id_tpv);
        $estadoFinal = self::STATUS[(int)$falla->status] ?? '';

        self::notificar(
            $accion === 'finalizar' ? 'finalizar' : 'editar',
            $terminal ? (int)$terminal->id_estacion : 0,
            ['tpv' => $terminal ? (string)$terminal->tpv : '', 'no_serie' => $terminal ? (string)$terminal->no_serie : ''],
            $accion === 'finalizar' ? $estadoFinal : (self::STATUS[(int)$falla->status] ?? '')
        );

        return [
            'success' => true,
            'code'    => 200,
            'message' => $accion === 'finalizar'
                ? 'El estado de la falla del TPV fue actualizado exitosamente.'
                : 'La falla ha sido editada exitosamente.',
        ];
    }

    /**
     * Cierra una falla que ya está "En reparación": la marca como Finalizada y
     * le pone el día de solución.
     *
     * Es el equivalente al `finalizar-falla-terminal-tpv-estacion.php` del
     * legacy: un UPDATE simple, sin volver a validar los demás campos (la
     * falla ya se capturó cuando estaba Pendiente).
     */
    public static function finalizarFalla(int $idFalla): array
    {
        $falla = self::buscarFallaAutorizada($idFalla);

        if (!$falla) {
            return ['success' => false, 'code' => 404, 'message' => 'Falla no encontrada.'];
        }

        if (!self::puedeActualizarFalla((int)$falla->status)) {
            return [
                'success' => false,
                'code'    => 403,
                'message' => 'No tienes permiso para actualizar el estatus de esta falla.',
            ];
        }

        $falla->status = 2;

        if (self::esFechaCero((string)$falla->dia_solucion)) {
            $falla->dia_solucion = date('Y-m-d');
        }

        $falla->save();

        $terminal = self::buscarTerminalAutorizado((int)$falla->id_tpv);

        self::notificar(
            'finalizar',
            $terminal ? (int)$terminal->id_estacion : 0,
            ['tpv' => $terminal ? (string)$terminal->tpv : '', 'no_serie' => $terminal ? (string)$terminal->no_serie : ''],
            self::STATUS[2]
        );

        return [
            'success' => true,
            'code'    => 200,
            'message' => 'El estado de la falla del TPV fue actualizado exitosamente.',
        ];
    }

    /** Elimina la falla (y sus comentarios y anexos). */
    public static function destroyFalla(int $idFalla): array
    {
        $falla = self::buscarFallaAutorizada($idFalla);

        if (!$falla) {
            return ['success' => false, 'code' => 404, 'message' => 'Falla no encontrada.'];
        }

        if (!self::puedeEliminarFalla((int)$falla->status)) {
            return [
                'success' => false,
                'code'    => 403,
                'message' => (int)$falla->status === 2
                    ? 'Una falla finalizada no se puede eliminar.'
                    : 'No tienes permiso para eliminar esta falla.',
            ];
        }

        // Los anexos van a disco, la BD no los conserva.
        $anexos = DB::table('op_terminales_tpv_anexos')->where('id_tpv_reporte', $idFalla)->get();
        foreach ($anexos as $anexo) {
            self::borrarArchivo(self::getUploadDirAnexos(), (string)$anexo->documento);
        }

        DB::table('op_comentarios_falla_tpv')->where('id_tpv_reporte', $idFalla)->delete();
        DB::table('op_terminales_tpv_anexos')->where('id_tpv_reporte', $idFalla)->delete();

        self::borrarArchivo(self::getUploadDir(), (string)$falla->factura);
        self::borrarArchivo(self::getUploadDir(), (string)$falla->cotizacion);

        $falla->delete();

        return ['success' => true, 'code' => 200, 'message' => 'Registro eliminado exitosamente.'];
    }

    /* ------------------------------------------------------------------ */
    /* Documentación: factura / cotización                                 */
    /* ------------------------------------------------------------------ */

    /**
     * Actualiza sólo la documentación (legacy actualizar-documentacion-...php).
     */
    public static function actualizarDocumentacion(int $idFalla, array $files): array
    {
        $falla = self::buscarFallaAutorizada($idFalla);

        if (!$falla) {
            return ['success' => false, 'code' => 404, 'message' => 'Falla no encontrada.'];
        }

        $directorio = self::getUploadDir();

        $factura    = self::guardarOpcional($files, 'factura', $directorio, (string)$falla->factura);
        $cotizacion = self::guardarOpcional($files, 'cotizacion', $directorio, (string)$falla->cotizacion);

        if ($factura === null && $cotizacion === null) {
            return [
                'success' => false,
                'code'    => 422,
                'message' => 'Debes subir al menos un archivo para actualizar la documentación.',
            ];
        }

        if ($factura !== null) {
            $falla->factura = $factura;
        }

        if ($cotizacion !== null) {
            $falla->cotizacion = $cotizacion;
        }

        $falla->save();

        return ['success' => true, 'code' => 200, 'message' => 'Los documentos han sido actualizados exitosamente.'];
    }

    /* ------------------------------------------------------------------ */
    /* Anexos                                                              */
    /* ------------------------------------------------------------------ */

    public static function getAnexos(int $idFalla): array
    {
        if (!self::buscarFallaAutorizada($idFalla)) {
            return [];
        }

        return DB::table('op_terminales_tpv_anexos')
            ->where('id_tpv_reporte', $idFalla)
            ->orderBy('id')
            ->get()
            ->map(static fn ($a) => [
                'id'          => (int)$a->id,
                'descripcion' => (string)$a->descripcion,
                'documento'   => (string)$a->documento,
            ])
            ->all();
    }

    public static function storeAnexo(int $idFalla, string $descripcion, array $archivo): array
    {
        $falla = self::buscarFallaAutorizada($idFalla);

        if (!$falla) {
            return ['success' => false, 'code' => 404, 'message' => 'Falla no encontrada.'];
        }

        $descripcion = trim($descripcion);

        if ($descripcion === '') {
            return ['success' => false, 'code' => 422, 'message' => 'Captura la descripción del anexo.'];
        }

        $nombre = self::validarArchivo($archivo, self::getUploadDirAnexos(), 'anexo');

        if ($nombre === null) {
            return [
                'success' => false,
                'code'    => 422,
                'message' => 'Selecciona un archivo válido para el anexo.',
            ];
        }

        DB::table('op_terminales_tpv_anexos')->insert([
            'id_tpv_reporte' => $idFalla,
            'descripcion'    => $descripcion,
            'documento'      => $nombre,
        ]);

        return ['success' => true, 'code' => 200, 'message' => 'Anexo agregado exitosamente.'];
    }

    public static function destroyAnexo(int $idAnexo): array
    {
        $anexo = DB::table('op_terminales_tpv_anexos')->where('id', $idAnexo)->first();

        if (!$anexo) {
            return ['success' => false, 'code' => 404, 'message' => 'Anexo no encontrado.'];
        }

        if (!self::buscarFallaAutorizada((int)$anexo->id_tpv_reporte)) {
            return ['success' => false, 'code' => 403, 'message' => 'No tienes permiso sobre esta falla.'];
        }

        self::borrarArchivo(self::getUploadDirAnexos(), (string)$anexo->documento);

        DB::table('op_terminales_tpv_anexos')->where('id', $idAnexo)->delete();

        return ['success' => true, 'code' => 200, 'message' => 'Anexo eliminado exitosamente.'];
    }

    /* ------------------------------------------------------------------ */
    /* Comentarios                                                          */
    /* ------------------------------------------------------------------ */

    public static function getComentarios(int $idFalla): array
    {
        if (!self::buscarFallaAutorizada($idFalla)) {
            return [];
        }

        $sesion   = Session::get('usuario') ?? [];
        $idActual = (int)($sesion['id'] ?? 0);

        return DB::table('op_comentarios_falla_tpv')
            ->where('id_tpv_reporte', $idFalla)
            ->orderBy('id', 'desc')
            ->get()
            ->map(static function ($c) use ($idActual) {
                $fecha = (string)$c->fecha;
                $soloFecha = substr($fecha, 0, 10);
                $hora = substr($fecha, 11, 5);

                return [
                    'id'         => (int)$c->id,
                    'id_personal' => (int)$c->id_personal,
                    'nombre'     => self::nombreUsuario((int)$c->id_personal),
                    'comentario' => (string)$c->comentario,
                    'es_propio'  => (int)$c->id_personal === $idActual,
                    'fecha_label' => formatearFecha($soloFecha),
                    'hora_label'  => $hora !== '' ? date('g:i a', strtotime($hora)) : '',
                ];
            })
            ->all();
    }

    public static function storeComentario(int $idFalla, string $comentario): array
    {
        $falla = self::buscarFallaAutorizada($idFalla);

        if (!$falla) {
            return ['success' => false, 'code' => 404, 'message' => 'Falla no encontrada.'];
        }

        $comentario = trim($comentario);

        if ($comentario === '') {
            return ['success' => false, 'code' => 422, 'message' => 'Escribe el comentario.'];
        }

        $sesion = Session::get('usuario') ?? [];

        DB::table('op_comentarios_falla_tpv')->insert([
            'id_tpv_reporte' => $idFalla,
            'fecha'         => date('Y-m-d H:i:s'),
            'id_personal'   => (int)($sesion['id'] ?? 0),
            'comentario'    => $comentario,
        ]);

        return ['success' => true, 'code' => 200, 'message' => 'Comentario agregado exitosamente.'];
    }

    /* ------------------------------------------------------------------ */
    /* Archivos                                                             */
    /* ------------------------------------------------------------------ */

    public static function getUploadDir(): string
    {
        return self::prepararDir(dirname(__DIR__, 2) . '/' . self::UPLOAD_FOLDER);
    }

    public static function getUploadDirAnexos(): string
    {
        return self::prepararDir(dirname(__DIR__, 2) . '/' . self::UPLOAD_FOLDER_ANEXOS);
    }

    private static function prepararDir(string $dir): string
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /**
     * Guarda factura/cotización si viene archivo; si no, devuelve null para no
     * borrar el documento ya registrado.
     */
    private static function guardarOpcional(array $files, string $campo, string $dir, string $anterior): ?string
    {
        if (!isset($files[$campo]) || !is_array($files[$campo])) {
            return null;
        }

        if (($files[$campo]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        $nombre = self::validarArchivo($files[$campo], $dir, $campo);

        if ($nombre === null) {
            return null;
        }

        // El documento anterior sólo se borra cuando se reemplazo con éxito.
        if ($anterior !== '' && $anterior !== $nombre) {
            self::borrarArchivo($dir, $anterior);
        }

        return $nombre;
    }

    /**
     * Valida extensión permitida + MIME no peligroso y mueve el archivo.
     * Devuelve el nombre definitivo o null si no es válido.
     *
     * $tipoDocumento define la nomenclatura para que coincida con la del
     * módulo anterior:
     *   - 'factura'    -> {uniqid}-factura-{nombre original}
     *   - 'cotizacion' -> {uniqid}-cotizacion-{nombre original}
     *   - 'anexo'      -> Anexos-tpv-{uniqid}.{extension}
     */
    private static function validarArchivo(array $file, string $dir, string $tipoDocumento): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        $nombre = basename((string)($file['name'] ?? ''));

        if ($nombre === '') {
            return null;
        }

        $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));

        if (!in_array($extension, self::ARCHIVO_EXTENSIONES, true)) {
            return null;
        }

        if (!self::mimeSeguro((string)($file['tmp_name'] ?? ''))) {
            return null;
        }

        $definitivo = self::construirNombreArchivo($tipoDocumento, $nombre, $extension);

        return move_uploaded_file((string)$file['tmp_name'], $dir . $definitivo) ? $definitivo : null;
    }

    /**
     * Genera el nombre del archivo con el formato del módulo anterior, de modo
     * que los archivos ya existentes y los nuevosconviven en la misma carpeta.
     */
    private static function construirNombreArchivo(string $tipoDocumento, string $nombre, string $extension): string
    {
        if ($tipoDocumento === 'anexo') {
            return 'Anexos-tpv-' . uniqid() . '.' . $extension;
        }

        return uniqid() . '-' . $tipoDocumento . '-' . $nombre;
    }

    private static function mimeSeguro(string $tmpPath): bool
    {
        if ($tmpPath === '' || !is_readable($tmpPath)) {
            return false;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return false;
        }

        $mime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        if ($mime === false || in_array($mime, self::MIME_PELIGROSOS, true)) {
            return false;
        }

        return str_starts_with($mime, 'image/')
            || str_starts_with($mime, 'application/pdf')
            || in_array($mime, [
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ], true);
    }

    private static function borrarArchivo(string $dir, string $nombre): void
    {
        if ($nombre === '') {
            return;
        }

        // Nunca se borra fuera de la carpeta autorizada.
        if (str_contains($nombre, '/') || str_contains($nombre, '\\') || str_contains($nombre, '..')) {
            return;
        }

        @unlink($dir . $nombre);
    }

    /* ------------------------------------------------------------------ */
    /* Telegram (mismos destinatarios que el listado de TPV)                */
    /* ------------------------------------------------------------------ */

    private static function notificar(string $operacion, int $idEstacion, array $datos, string $estado): void
    {
        if ($idEstacion <= 0) {
            return;
        }

        try {
            $sesion  = Session::get('usuario') ?? [];
            $idAutor = (int)($sesion['id'] ?? 0);
            $nombre  = (string)($sesion['nombre'] ?? 'Usuario');

            $telegram = new TelegramService();

            $destinatarios = $telegram->getUserIdsByStationWithComodines($idEstacion, $idAutor);

            if (in_array($idEstacion, [6, 7], true)) {
                $destinatarios = array_values(array_unique(array_merge(
                    $destinatarios,
                    $telegram->getUserIdsComercializadora($idAutor)
                )));
            }

            $destinatarios = array_values(array_unique(array_merge(
                $destinatarios,
                $telegram->getUserIdsMantenimiento($idAutor)
            )));

            if (empty($destinatarios)) {
                return;
            }

            $verbo = [
                'agregar'   => 'agrego una nueva falla a la terminal de punto de venta',
                'editar'    => 'actualizo la informacion de la falla de la terminal de punto de venta',
                'finalizar' => 'cambio el estado de la falla de la terminal de punto de venta',
            ][$operacion] ?? 'modifico la falla de la terminal de punto de venta';

            $mensaje = "\xE2\x80\xA2 " . $nombre . ' ' . $verbo . ' correspondiente al apartado de Almacen.' . "\n\n"
                . "\xE2\x80\xA2\xE2\x80\x8F TPV: " . ($datos['tpv'] ?? '') . "\n"
                . "\xE2\x80\xA3\xE2\x80\x8D No. de Serie: " . ($datos['no_serie'] ?? '') . "\n"
                . "\xE2\x80\xA6\xE2\x80\x87 Estado: " . $estado . "\n"
                . 'Estacion: ' . self::nombreEstacion($idEstacion) . '.';

            $telegram->sendMessageToMultiple($destinatarios, $mensaje);

        } catch (\Throwable $e) {
            error_log('Error en notificacion Telegram (fallas TPV): ' . $e->getMessage());
        }
    }
}