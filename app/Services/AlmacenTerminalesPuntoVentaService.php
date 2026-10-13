<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Estacion;
use App\Models\Operativo\TerminalesTpv;
use App\Models\Operativo\TerminalesTpvReporte;
use App\Models\Usuario;

/**
 * Terminales Punto de Venta — Almacén
 *
 * Migración del módulo legacy (departamento-operativo/public/admin + corte-diario)
 * "terminales-tpv" a la arquitectura de Portal3.
 *
 * Reglas de negocio preservadas del legacy:
 *  - Sólo registros con status = 1 (activos), ordenados por tpv ASC.
 *  - Fila resaltada y columna ESTATUS "En falla" cuando existen reportes abiertos
 *    (op_terminales_tpv_reporte.status < 2).
 *  - "Total reparaciones" = reportes finalizados (status = 2).
 *  - Notificaciones Telegram al agregar / editar / eliminar, con los mismos
 *    destinatarios y texto que el legacy (estación + comodines, comercializadora
 *    para estaciones 6/7 y mantenimiento).
 */
class AlmacenTerminalesPuntoVentaService
{
    public const MODULE_KEY    = 'terminales-punto-venta';
    public const PARENT_MODULE = 'almacen';

    /** Puestos que en el legacy veían el módulo desde "Mantenimiento". */
    private const PUESTOS_MANTENIMIENTO = [6, 7];

    /** Estaciones comercializadoras (notificación extra en legacy). */
    private const ESTACIONES_COMERCIALIZADORA = [6, 7];

    /**
     * Permisos efectivos y contexto del usuario autenticado.
     * Patrón idéntico a AlmacenMantenimientoPreventivoService::getPermisos().
     */
    public static function getPermisos(): array
    {
        $sesion   = Session::get('usuario') ?? [];
        $usuario  = Auth::user();
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

            'origenMantenimiento' => !in_array($idPuesto, self::PUESTOS_MANTENIMIENTO, true),
            'esUsuarioEstacion'   => in_array($idPuesto, self::PUESTOS_MANTENIMIENTO, true),
        ];
    }

    /**
     * Eliminar una terminal es exclusivo de los usuarios multiestación: son los
     * únicos que pueden ver varias estaciones y, por tanto, los únicos que
     * pueden confirmar que el registro no está en uso en otra estación.
     * A los demás la opción les aparece deshabilitada.
     */
    public static function puedeEliminarTerminal(): bool
    {
        if (!self::getPermisos()['puedeEliminar']) {
            return false;
        }

        return MultiestacionService::isEnabled();
    }

    /**
     * Estaciones que el usuario puede ver, indexadas por id.
     * Única fuente de verdad: no se hardcodean estaciones.
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

    /**
     * Color del badge del estatus de la terminal, igual que el resto de módulos
     * de Almacén (bg-*-subtle text-*).
     */
    private static function badgeEstatusTpv(string $estatus): string
    {
        if ($estatus === 'En falla') {
            return 'bg-danger-subtle text-danger';
        }

        if ($estatus === 'Inactivo') {
            return 'bg-secondary-subtle text-secondary';
        }

        return 'bg-success-subtle text-success';
    }

    /**
     * Normaliza y sanea la entrada del formulario (los 14 campos del legacy).
     *
     * @return array<string, string>
     */
    public static function normalizarEntrada(array $input): array
    {
        $campos = [
            'tpv', 'no_serie', 'modelo', 'no_lote', 'tipo_conexion',
            'no_afiliacion', 'telefono', 'estado', 'rollos', 'cargadores',
            'pedestales', 'estatus_tpv', 'no_impresiones', 'tipo_tpv',
        ];

        $datos = [];

        foreach ($campos as $campo) {
            $valor = $input[$campo] ?? '';
            $datos[$campo] = is_scalar($valor) ? trim((string)$valor) : '';
        }

        // no_impresiones viaja formateado con separador de miles en el detalle:
        // se limpia para no truncar el valor al guardar ("31,901" => "31901").
        $datos['no_impresiones'] = str_replace(',', '', $datos['no_impresiones']);

        return $datos;
    }

    /**
     * Catálogos de los dos únicos campos tipo lista del formulario.
     *
     * Sólo contienen las opciones vigentes del módulo. Los valores históricos
     * que ya viven en op_terminales_tpv (p. ej. "Activo" en estatus_tpv o
     * "Dispositivo" en tipo_tpv) NO se ofrecen aquí a propósito: el modal de
     * editar inyecta además una <option> de respaldo con el valor vigente, de
     * modo que abrir y guardar un registro antiguo nunca pisa su dato.
     *
     * @return array<string, array<int, string>>
     */
    public static function getCatalogos(): array
    {
        return [
            'estatus_tpv' => ['Nuevo', 'Usado', 'Reparación'],
            'tipo_tpv'   => ['Tecla', 'Touch'],
        ];
    }

    /**
     * Validaciones de negocio.
     *
     * El legacy dejaba sin llenar No. de lote, Estatus TPV y Tipo de TPV (más de
     * la mitad de los registros existentes los tienen vacíos), por lo que esos
     * tres campos se conservan como opcionales.
     *
     * @return array<string, string> campo => mensaje
     */
    public static function validar(array $datos): array
    {
        $obligatorios = [
            'tpv', 'no_serie', 'modelo', 'tipo_conexion', 'no_afiliacion',
            'telefono', 'estado', 'rollos', 'cargadores', 'pedestales', 'no_impresiones',
        ];

        $errores = [];

        foreach ($obligatorios as $campo) {
            if (!isset($datos[$campo]) || $datos[$campo] === '') {
                $errores[$campo] = 'Este campo es obligatorio.';
            }
        }

        if (isset($datos['no_impresiones']) && $datos['no_impresiones'] !== '') {
            if (!ctype_digit($datos['no_impresiones'])) {
                $errores['no_impresiones'] = 'Debe ser un número entero.';
            }
        }

        return $errores;
    }

    /* ------------------------------------------------------------------ */
    /* Lectura                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Listado de terminales.
     *
     * @param int|null $estacionFiltro null = todas las estaciones autorizadas
     * @return array<int, array<string, mixed>>
     */
    public static function getData(?int $estacionFiltro): array
    {
        $disponibles = self::getEstacionesDisponibles();

        if (empty($disponibles)) {
            return [];
        }

        $query = TerminalesTpv::query()->where('status', 1);

        if ($estacionFiltro !== null && $estacionFiltro > 0) {
            // Autoridad en el backend: nunca filtrar por estación no autorizada.
            if (!isset($disponibles[$estacionFiltro])) {
                return [];
            }
            $query->where('id_estacion', $estacionFiltro);
        } else {
            $query->whereIn('id_estacion', array_keys($disponibles));
        }

        $terminales = $query->orderBy('tpv')->get();

        return $terminales->map(function (TerminalesTpv $t) {

            $fallasAbiertas = (int) TerminalesTpvReporte::where('id_tpv', $t->id)
                ->where('status', '<', 2)
                ->count();

            $totalReparaciones = (int) TerminalesTpvReporte::where('id_tpv', $t->id)
                ->where('status', 2)
                ->count();

            return [
                'id'                 => (int)$t->id,
                'estacion_id'        => (int)$t->id_estacion,
                'estacion_nombre'    => self::nombreEstacion((int)$t->id_estacion),
                'tpv'                => (string)$t->tpv,
                'no_serie'           => (string)$t->no_serie,
                'modelo'             => (string)$t->modelo,
                'no_lote'            => (string)$t->no_lote,
                'tipo_conexion'      => (string)$t->tipo_conexion,
                'no_afiliacion'      => (string)$t->no_afiliacion,
                'telefono'           => (string)$t->telefono,
                'estado'             => (string)$t->estado,
                'rollos'             => (string)$t->rollos,
                'cargadores'         => (string)$t->cargadores,
                'pedestales'         => (string)$t->pedestales,
                'estatus_tpv'        => (string)$t->estatus_tpv,
                'no_impresiones'     => (int)$t->no_impresiones,
                'no_impresiones_label' => number_format((int)$t->no_impresiones, 0, '.', ','),
                'tipo_tpv'           => (string)$t->tipo_tpv,
                'total_reparaciones' => $totalReparaciones,
                'fallas_abiertas'    => $fallasAbiertas,
                'estatus_label'      => $fallasAbiertas > 0 ? 'En falla' : 'Activo',
                'estatus_badge'      => $fallasAbiertas > 0 ? 'bg-danger' : 'bg-success text-white',
            ];
        })->all();
    }

    /**
     * Detalle de una terminal + "Reparaciones anteriores" (reportes status = 2),
     * igual que el modal legacy modal-detalle-terminales-tpv.php.
     */
    public static function getDetalle(int $id): ?array
    {
        $terminal = self::buscarAutorizado($id);

        if (!$terminal) {
            return null;
        }

        $reparaciones = TerminalesTpvReporte::where('id_tpv', $terminal->id)
            ->where('status', 2)
            ->orderBy('dia_solucion', 'asc')
            ->get()
            ->map(function (TerminalesTpvReporte $r) {

                $usuario = Usuario::find((int)$r->id_usuario);

                return [
                    'no_serie'     => (string)$r->serie,
                    'falla'        => (string)$r->falla,
                    'dia_solucion' => formatearFecha($r->dia_solucion ? $r->dia_solucion->format('Y-m-d') : ''),
                    'costo'        => '$' . number_format((float)$r->costo, 2, '.', ','),
                    'responsable'  => ($usuario && $usuario->nombre) ? (string)$usuario->nombre : 'S/I',
                ];
            })
            ->all();

        return [
            'id'            => (int)$terminal->id,
            'estacion_id'   => (int)$terminal->id_estacion,
            'estacion'      => self::nombreEstacion((int)$terminal->id_estacion),
            'estacion_nombre' => self::nombreEstacion((int)$terminal->id_estacion),
            'tpv'           => (string)$terminal->tpv,
            'no_serie'      => (string)$terminal->no_serie,
            'modelo'        => (string)$terminal->modelo,
            'no_lote'       => (string)$terminal->no_lote,
            'tipo_conexion' => (string)$terminal->tipo_conexion,
            'no_afiliacion' => (string)$terminal->no_afiliacion,
            'telefono'      => (string)$terminal->telefono,
            'estado'        => (string)$terminal->estado,
            'rollos'        => (string)$terminal->rollos,
            'cargadores'    => (string)$terminal->cargadores,
            'pedestales'    => (string)$terminal->pedestales,
            'estatus_tpv'   => (string)$terminal->estatus_tpv,
            'estatus_tpv_badge' => self::badgeEstatusTpv((string)$terminal->estatus_tpv),
            'no_impresiones' => number_format((int)$terminal->no_impresiones, 0, '.', ','),
            'no_impresiones_raw' => (int)$terminal->no_impresiones,
            'no_impresiones_label' => number_format((int)$terminal->no_impresiones, 0, '.', ','),
            'tipo_tpv'      => (string)$terminal->tipo_tpv,
            'reparaciones'  => $reparaciones,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Escritura                                                          */
    /* ------------------------------------------------------------------ */

    public static function store(int $idEstacion, array $datos): array
    {
        $disponibles = self::getEstacionesDisponibles();

        if (!isset($disponibles[$idEstacion])) {
            return [
                'success' => false,
                'code'    => 403,
                'message' => 'No tienes permiso para registrar terminales en esa estación.',
            ];
        }

        $terminal = TerminalesTpv::create([
            'id_estacion'    => $idEstacion,
            'tpv'            => $datos['tpv'],
            'no_serie'       => $datos['no_serie'],
            'modelo'         => $datos['modelo'],
            'no_lote'        => $datos['no_lote'],
            'tipo_conexion'  => $datos['tipo_conexion'],
            'no_afiliacion'  => $datos['no_afiliacion'],
            'telefono'       => $datos['telefono'],
            'estado'         => $datos['estado'],
            'rollos'         => $datos['rollos'],
            'cargadores'     => $datos['cargadores'],
            'pedestales'     => $datos['pedestales'],
            'estatus_tpv'    => $datos['estatus_tpv'],
            'no_impresiones' => (int)$datos['no_impresiones'],
            'tipo_tpv'       => $datos['tipo_tpv'],
            'status'         => 1,
        ]);

        self::notificar('agregar', $idEstacion, [
            'tpv'      => $datos['tpv'],
            'no_serie' => $datos['no_serie'],
            'modelo'   => $datos['modelo'],
            'conexion' => $datos['tipo_conexion'],
        ]);

        return [
            'success' => true,
            'code'    => 200,
            'message' => 'Registro agregado exitosamente.',
            'id'      => (int)$terminal->id,
        ];
    }

    public static function update(int $id, array $datos): array
    {
        $terminal = self::buscarAutorizado($id);

        if (!$terminal) {
            return [
                'success' => false,
                'code'    => 404,
                'message' => 'Terminal no encontrada.',
            ];
        }

        $terminal->update([
            'tpv'            => $datos['tpv'],
            'no_serie'       => $datos['no_serie'],
            'modelo'         => $datos['modelo'],
            'no_lote'        => $datos['no_lote'],
            'tipo_conexion'  => $datos['tipo_conexion'],
            'no_afiliacion'  => $datos['no_afiliacion'],
            'telefono'       => $datos['telefono'],
            'estado'         => $datos['estado'],
            'rollos'         => $datos['rollos'],
            'cargadores'     => $datos['cargadores'],
            'pedestales'     => $datos['pedestales'],
            'estatus_tpv'    => $datos['estatus_tpv'],
            'no_impresiones' => (int)$datos['no_impresiones'],
            'tipo_tpv'       => $datos['tipo_tpv'],
        ]);

        self::notificar('editar', (int)$terminal->id_estacion, [
            'tpv'      => $datos['tpv'],
            'no_serie' => $datos['no_serie'],
            'modelo'   => $datos['modelo'],
            'conexion' => $datos['tipo_conexion'],
        ]);

        return [
            'success' => true,
            'code'    => 200,
            'message' => 'Registro editado exitosamente.',
            'id'      => (int)$terminal->id,
        ];
    }

    public static function destroy(int $id): array
    {
        $terminal = self::buscarAutorizado($id);

        if (!$terminal) {
            return [
                'success' => false,
                'code'    => 404,
                'message' => 'Terminal no encontrada.',
            ];
        }

        $datos = [
            'tpv'      => (string)$terminal->tpv,
            'no_serie' => (string)$terminal->no_serie,
            'modelo'   => (string)$terminal->modelo,
        ];
        $idEstacion = (int)$terminal->id_estacion;

        /*
         * Nota de migración: el endpoint legacy eliminaba por error un registro de
         * op_terminales_tpv_reporte usando el id de la terminal (y ni siquiera
         * recibía idReporte desde la lista). Aquí se elimina la terminal real,
         * validando pertenencia a una estación autorizada.
         */
        $terminal->delete();

        self::notificar('eliminar', $idEstacion, $datos);

        return [
            'success' => true,
            'code'    => 200,
            'message' => 'Registro eliminado exitosamente.',
        ];
    }

    /**
     * Busca una terminal activa validando que su estación esté autorizada.
     */
    private static function buscarAutorizado(int $id): ?TerminalesTpv
    {
        $disponibles = self::getEstacionesDisponibles();

        if (empty($disponibles)) {
            return null;
        }

        return TerminalesTpv::where('id', $id)
            ->whereIn('id_estacion', array_keys($disponibles))
            ->first();
    }

    /* ------------------------------------------------------------------ */
    /* Notificaciones Telegram (mismos destinatarios y texto que legacy)  */
    /* ------------------------------------------------------------------ */

    /**
     * Envía la notificación de una operación sobre terminales.
     *
     * Destinatarios (legacy): usuarios de la estación salvo puestos 4,5,8,12,15,
     * más comodines (id_gas 8, puestos 6,13,14), excluyendo al ejecutor;
     * comercializadora (id_gas 8, puesto 4) si la estación es 6 o 7;
     * mantenimiento (id_gas 8, puesto 8) siempre.
     *
     * Los errores de envío nunca degradan la operación de base de datos.
     */
    private static function notificar(string $operacion, int $idEstacion, array $datos): void
    {
        try {
            $sesion   = Session::get('usuario') ?? [];
            $idAutor = (int)($sesion['id'] ?? 0);
            $nombre  = (string)($sesion['nombre'] ?? 'Usuario');

            $telegram = new TelegramService();

            $destinatarios = $telegram->getUserIdsByStationWithComodines($idEstacion, $idAutor);

            if (in_array($idEstacion, self::ESTACIONES_COMERCIALIZADORA, true)) {
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

            $estacion = self::nombreEstacion($idEstacion);

            $mensaje = self::construirMensaje($operacion, $nombre, $estacion, $datos);

            $telegram->sendMessageToMultiple($destinatarios, $mensaje);

        } catch (\Throwable $e) {
            error_log('Error en notificación Telegram (terminales TPV): ' . $e->getMessage());
        }
    }

    private static function construirMensaje(
        string $operacion,
        string $nombre,
        string $estacion,
        array $datos
    ): string {

        $encabezado = [
            'agregar'  => '✅ ' . $nombre . ' agregó una nueva terminal de punto de venta correspondiente al apartado de Almacén.',
            'editar'   => '🔄 ' . $nombre . ' actualizó la terminal de punto de venta correspondiente al apartado de Almacén.',
            'eliminar' => '🗑 ' . $nombre . ' eliminó la terminal de punto de venta correspondiente al apartado de Almacén.',
        ][$operacion] ?? '📟 ' . $nombre . ' modificó una terminal de punto de venta.';

        $lineas = [
            $encabezado,
            '📟 TPV: ' . ($datos['tpv'] ?? ''),
            '#️⃣ No. de Serie: ' . ($datos['no_serie'] ?? ''),
            '®️ Modelo: ' . ($datos['modelo'] ?? ''),
        ];

        if ($operacion !== 'eliminar') {
            $lineas[] = '🌐 Tipo de conexón: ' . ($datos['conexion'] ?? '');
        }

        $lineas[] = '';
        $lineas[] = '⛽ Estación: ' . $estacion . '.';

        return implode("\n", $lineas);
    }
}
