<?php

namespace App\Services;

use App\Core\Session;
use App\Models\Usuario;
use App\Models\Sistemas\Soporte;
use App\Models\Sistemas\SoporteActividad;
use App\Models\Sistemas\SoporteComentario;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Actividades (Sistemas) — /sistemas/actividades.
 *
 * Es el complemento interno de Soporte (Estaciones):
 *
 *  - Soporte (Estaciones) muestra los tickets solicitados por las
 *    GASOLINERAS (cualquier estación o departamento) y EXCLUYE los del
 *    "Departamento Sistemas".
 *  - Actividades (Sistemas) muestra únicamente los tickets solicitados por
 *    el personal del "Departamento Sistemas" (puesto 25), que son las
 *    actividades internas del propio área.
 *
 * Reglas heredadas del sistema anterior (app/vistas/actividades/):
 *
 *  - Sólo se ven los tickets en estado Pendiente (1), En proceso (2),
 *    Finalizado (3) y Expiración de fecha (5).
 *  - El listado corresponde a los tickets que tiene ASIGNADOS el usuario
 *    (ds_soporte.id_personal_soporte = usuario en sesión). El usuario
 *    principal (id 496) puede ver los de todo el equipo mediante el
 *    filtro por responsable, igual que en Soporte (Estaciones).
 *  - El orden original es por fecha de inicio (descendente) y estado
 *    (descendente).
 *  - Quien captura la actividad puede editarla; el responsible de
 *    soporte es quien fija las fechas y el estatus de las actividades.
 *  - Al finalizar el registro (crear el ticket) se notifica por Telegram
 *    al responsable principal del área, como en el sistema anterior.
 *
 * Reutiliza el servicio base (SoporteService) para el mapeo de datos, el
 * manejo de fechas, los comentarios, la eliminación global y las
 * notificaciones de Telegram.
 */
class SoporteActividadesService
{
    /** Clave del módulo en modulos / roles_modulos. */
    public const MODULE_KEY = 'sistemas-actividades';

    /** Ruta del módulo en el menú del Home. */
    public const RUTA = '/sistemas/actividades';

    /** Puesto del personal del área (solicitante de las actividades). */
    public const PUESTO_SISTEMAS = 25;

    /** Estados visibles en el listado. */
    public const ESTADOS_VISIBLES = [1, 2, 3, 5];

    /** El usuario principal del área (destinatario de las notificaciones). */
    public const USUARIO_SISTEMAS = 496;

    /* ------------------------------------------------------------------ */
    /* Permisos                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Permisos efectivos del módulo "sistemas-actividades".
     *
     * @return array{leer: bool, crear: bool, editar: bool, eliminar: bool, descargar: bool, puede_consultar: bool, puede_agregar: bool, puede_editar: bool, puede_eliminar: bool, puede_descargar: bool, puede_asignar: bool}
     */
    public static function getPermisos(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $idUsuario = self::idUsuario();

        if ($idUsuario <= 0) {
            return self::sinPermisos();
        }

        $permisos = ModuloService::permisosSesion(self::MODULE_KEY);

        if (empty($permisos)) {
            $permisos = ModuloService::getPermiso($idUsuario, self::MODULE_KEY) ?: [];
        }

        $leer = !empty($permisos['leer']);

        $cache = [
            'leer' => $leer,
            'crear' => $leer && !empty($permisos['crear']),
            'editar' => $leer && !empty($permisos['editar']),
            'eliminar' => $leer && !empty($permisos['eliminar']),
            'descargar' => $leer && !empty($permisos['descargar']),
            'puede_consultar' => $leer,
            'puede_agregar' => $leer && !empty($permisos['crear']),
            'puede_editar' => $leer && !empty($permisos['editar']),
            'puede_eliminar' => $leer && !empty($permisos['eliminar']),
            'puede_descargar' => $leer && !empty($permisos['descargar']),
            // Asignar el responsable de una actividad es exclusividad del
            // responsable principal del área (id 496), como en el legacy.
            'puede_asignar' => $leer && $idUsuario === self::USUARIO_SISTEMAS,
            // El filtro por responsable de la tabla también es exclusivo
            // del responsable principal del área.
            'puede_filtrar_responsable' => $leer && $idUsuario === self::USUARIO_SISTEMAS,
        ];

        return $cache;
    }

    /** @return array<string, bool> */
    private static function sinPermisos(): array
    {
        return [
            'leer' => false,
            'crear' => false,
            'editar' => false,
            'eliminar' => false,
            'descargar' => false,
            'puede_consultar' => false,
            'puede_agregar' => false,
            'puede_editar' => false,
            'puede_eliminar' => false,
            'puede_descargar' => false,
            'puede_asignar' => false,
            'puede_filtrar_responsable' => false,
        ];
    }

    public static function puede(string $accion): bool
    {
        return !empty(self::getPermisos()[$accion]);
    }

    /* ------------------------------------------------------------------ */
    /* Alcance de los datos                                                 */
    /* ------------------------------------------------------------------ */

    /**
     * Tickets que el usuario puede consultar: los que tiene asignados
     * (id_personal_soporte). El responsable principal (496) puede ver los de
     * todo el equipo, aplicando el filtro por responsable.
     *
     * @return array<int, Soporte>
     */
    private static function ticketsAlcance(?int $idResponsable = null, ?int $estado = null): array
    {
        $estados = self::ESTADOS_VISIBLES;

        // El valor 5 ("Todos los registros") es el comodín del filtro;
        // cualquier otro estado visible se aplica como filtro real.
        if ($estado !== null && $estado >= 0 && $estado !== SoporteService::ESTADO_TODOS
            && in_array($estado, self::ESTADOS_VISIBLES, true)) {
            $estados = [$estado];
        }

        $query = Soporte::whereIn('estado', $estados)
            ->whereIn('id_personal', self::solicitantesPermitidos());

        if ($idResponsable !== null && $idResponsable > 0) {
            $query->where('id_personal_soporte', $idResponsable);
        } elseif (!self::esUsuarioPrincipal()) {
            // Cada persona ve las actividades que tiene asignadas.
            $query->where('id_personal_soporte', self::idUsuario());
        }

        return $query
            ->orderBy('fecha_inicio', 'desc')
            ->orderBy('estado', 'desc')
            ->get()
            ->all();
    }

    /**
     * Ids de los usuarios que pueden solicitar actividades (personal del
     * "Departamento Sistemas"), igual que en el sistema anterior.
     *
     * @return array<int, int>
     */
    private static function solicitantesPermitidos(): array
    {
        return Usuario::where('id_puesto', self::PUESTO_SISTEMAS)
            ->pluck('id')
            ->map(fn($v) => (int)$v)
            ->all();
    }

    /**
     * Listado de actividades para el DataTable.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getData(?int $idResponsable = null, string $texto = '', ?int $estado = null): array
    {
        $tickets = self::ticketsAlcance($idResponsable, $estado);

        $texto = trim($texto);

        if ($texto !== '') {
            $tickets = array_values(array_filter($tickets, function (Soporte $ticket) use ($texto) {
                return mb_stripos(SoporteService::folio((int)$ticket->id_ticket), $texto) !== false
                    || mb_stripos((string)$ticket->descripcion, $texto) !== false;
            }));
        }

        if (empty($tickets)) {
            return [];
        }

        $ids = array_map(fn(Soporte $t) => (int)$t->id_ticket, $tickets);

        $conteos = SoporteComentario::whereIn('id_ticket', $ids)
            ->selectRaw('id_ticket, COUNT(*) AS total')
            ->groupBy('id_ticket')
            ->pluck('total', 'id_ticket');

        $filas = [];

        foreach ($tickets as $ticket) {
            $idTicket = (int)$ticket->id_ticket;

            $fila = SoporteService::mapearTicketPublico($ticket, (int)($conteos[$idTicket] ?? 0));

            $filas[] = array_merge($fila, [
                'estacion' => self::nombreEstacion($ticket),
                'solicitante' => SoporteService::nombreUsuario((int)$ticket->id_personal),
                'id_personal_soporte' => (int)$ticket->id_personal_soporte,
                'tiene_responsable' => (int)$ticket->id_personal_soporte > 0,
                'esta_concluido' => self::estaConcluido($ticket),
                'puede_editar_actividades' => self::puedeEditarSeguimiento($ticket),
            ]);
        }

        return $filas;
    }

    /**
     * Total de actividades pendientes (Pendiente + En proceso) del alcance
     * del usuario. Es el contador que se muestra en el badge.
     */
    public static function getPendientes(?int $idResponsable = null, ?int $year = null): int
    {
        return count(self::ticketsPendientes($idResponsable, $year));
    }

    /**
     * Tickets pendientes del alcance, agrupados por responsable (para el
     * filtro de la barra de herramientas).
     *
     * @return array<int, array{id: int, nombre: string, pendientes: int}>
     */
    public static function getPendientesPorResponsable(?int $idResponsable = null): array
    {
        $pendientes = self::ticketsPendientes($idResponsable);

        $conteo = [];

        foreach ($pendientes as $ticket) {
            $id = (int)$ticket->id_personal_soporte;
            $conteo[$id] = ($conteo[$id] ?? 0) + 1;
        }

        $resultado = [];

        foreach (SoporteService::getResponsables() as $responsable) {
            $resultado[] = [
                'id' => $responsable['id'],
                'nombre' => $responsable['nombre'],
                'pendientes' => $conteo[$responsable['id']] ?? 0,
            ];
        }

        return $resultado;
    }

    /**
     * @return array<int, Soporte>
     */
    private static function ticketsPendientes(?int $idResponsable = null, ?int $year = null): array
    {
        $query = Soporte::whereIn('estado', [SoporteService::ESTADO_PENDIENTE, SoporteService::ESTADO_EN_PROCESO])
            ->whereIn('id_personal', self::solicitantesPermitidos());

        if ($year !== null) {
            $query->whereYear('fecha_creacion', $year);
        }

        if ($idResponsable !== null && $idResponsable > 0) {
            $query->where('id_personal_soporte', $idResponsable);
        } elseif (!self::esUsuarioPrincipal()) {
            $query->where('id_personal_soporte', self::idUsuario());
        }

        return $query->get()->all();
    }

    /* ------------------------------------------------------------------ */
    /* Detalle y comentarios                                                */
    /* ------------------------------------------------------------------ */

    /**
     * Ticket del alcance del usuario (para el detalle y el seguimiento).
     */
    public static function ticketEnAlcance(int $idTicket): ?Soporte
    {
        $ticket = Soporte::find($idTicket);

        if (!$ticket) {
            return null;
        }

        // El folio recién creado está en estado "Creando": sólo lo ve quien
        // lo creó (para poder capturarlo). Los demás estados son los visibles.
        if (!in_array((int)$ticket->estado, self::ESTADOS_VISIBLES, true)) {
            $esCreador = (int)$ticket->estado === SoporteService::ESTADO_CREANDO
                && (int)$ticket->id_personal === self::idUsuario();

            if (!$esCreador) {
                return null;
            }
        }

        if (!in_array((int)$ticket->id_personal, self::solicitantesPermitidos(), true)) {
            return null;
        }

        if (!self::esUsuarioPrincipal() && (int)$ticket->id_personal_soporte !== self::idUsuario()) {
            return null;
        }

        return $ticket;
    }

    /** Detalle del ticket en JSON. */
    public static function show(int $idTicket): array
    {
        $ticket = self::ticketEnAlcance($idTicket);

        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket no encontrado.'];
        }

        $detalle = SoporteService::detalleDeTicket($ticket);

        return [
            'success' => true,
            'data' => array_merge($detalle, [
                'estacion' => self::nombreEstacion($ticket),
                'solicitante' => SoporteService::nombreUsuario((int)$ticket->id_personal),
            'id_personal_soporte' => (int)$ticket->id_personal_soporte,
            'tiene_responsable' => (int)$ticket->id_personal_soporte > 0,
            'puede_editar_actividades' => self::puedeEditarSeguimiento($ticket),
            'puede_seguimiento' => self::puedeEditarSeguimiento($ticket),
            // Fechas en formato ISO (para los campos de edición).
                'fecha_inicio_iso' => SoporteService::isoFecha($ticket->fecha_inicio),
                'fecha_termino_iso' => SoporteService::isoFecha($ticket->fecha_termino),
                'fecha_creacion_iso' => SoporteService::isoFecha($ticket->fecha_creacion),
                'fecha_termino_real_iso' => SoporteService::isoFecha($ticket->fecha_termino_real),
                'fecha_inicio_texto' => formatearFecha($ticket->fecha_inicio),
                'fecha_termino_texto' => formatearFecha($ticket->fecha_termino),
                'fecha_termino_real_texto' => formatearFecha($ticket->fecha_termino_real),
            ]),
        ];
    }

    /** Comentarios del ticket en JSON. */
    public static function comentariosShow(int $idTicket): array
    {
        $ticket = self::ticketEnAlcance($idTicket);

        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket no encontrado.'];
        }

        return ['success' => true, 'data' => SoporteService::comentariosDeTicket($ticket)];
    }

    /* ------------------------------------------------------------------ */
    /* Captura de la actividad                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Las operaciones que se delegan al servicio base validan contra el
     * módulo en contexto: aquí debe ser "sistemas-actividades".
     */
    private static function activarModulo(): void
    {
        SoporteService::usarModulo(self::MODULE_KEY);
    }

    /**
     * Crea el folio de la actividad (ticket en estado "Creando"). Reutiliza
     * el servicio base: misma numeración, misma categoría y mismo aviso.
     */
    public static function nuevoFolio(): array
    {
        if (!self::puede('crear')) {
            return ['success' => false, 'message' => 'No tienes permiso para crear actividades.'];
        }

        self::activarModulo();

        return SoporteService::nuevoTicket(SoporteService::CATEGORIA_ACTIVIDAD);
    }

    /** Guarda la descripción del folio (el solicitante es el usuario). */
    public static function editarDescripcion(int $idTicket, string $descripcion): array
    {
        self::activarModulo();

        return SoporteService::editarDescripcion($idTicket, $descripcion);
    }

    /** Guarda la prioridad del folio. */
    public static function editarPrioridad(int $idTicket, string $prioridad): array
    {
        self::activarModulo();

        return SoporteService::editarPrioridad($idTicket, $prioridad);
    }

    /**
     * Asigna el responsable de la actividad. Sólo el responsable principal
     * del área (id 496) puede hacerlo, igual que en el sistema anterior.
     */
    public static function asignarResponsable(int $idTicket, int $idResponsable): array
    {
        self::activarModulo();

        if (!self::puede('puede_asignar')) {
            return ['success' => false, 'message' => 'Sólo el responsable principal puede asignar la actividad.'];
        }

        $ticket = Soporte::find($idTicket);

        if (!$ticket || (int)$ticket->id_personal !== self::idUsuario()) {
            return ['success' => false, 'message' => 'Ticket no encontrado.'];
        }

        $valido = false;

        foreach (SoporteService::getResponsables() as $responsable) {
            if ($responsable['id'] === $idResponsable) {
                $valido = true;
                break;
            }
        }

        if (!$valido) {
            return ['success' => false, 'message' => 'El responsable no es válido.'];
        }

        $ticket->id_personal_soporte = $idResponsable;
        $ticket->save();

        // Se avisa al responsable de que se le asignó la actividad.
        SoporteService::notificarAsignacionResponsable($ticket, $idResponsable);

        return ['success' => true, 'message' => 'Responsable asignado.'];
    }

    /** Agrega una actividad (con su archivo opcional) al folio. */
    public static function agregarActividad(int $idTicket, string $descripcion, ?array $archivo): array
    {
        self::activarModulo();

        return SoporteService::agregarActividad($idTicket, $descripcion, $archivo);
    }

    /** Elimina una actividad del folio. */
    public static function eliminarActividad(int $idActividad): array
    {
        self::activarModulo();

        return SoporteService::eliminarActividad($idActividad);
    }

    /** Finaliza el registro: el folio pasa a "Pendiente" y se notifica. */
    public static function finalizarRegistro(int $idTicket): array
    {
        self::activarModulo();

        return SoporteService::finalizarRegistro($idTicket);
    }

    /* ------------------------------------------------------------------ */
    /* Seguimiento (responsable del área)                                  */
    /* ------------------------------------------------------------------ */

    /**
     * El ticket ya está concluido (Finalizado / Expiración de fecha).
     */
    public static function estaConcluido(Soporte $ticket): bool
    {
        $estado = (int)$ticket->estado;

        return $estado === SoporteService::ESTADO_FINALIZADO || $estado === 5;
    }

    /**
     * El responsable asignado (o el responsable principal del área) puede
     * registrar el seguimiento: fechas, estatus de las actividades y cierre.
     */
    public static function puedeEditarSeguimiento(Soporte $ticket): bool
    {
        if (!self::puede('editar')) {
            return false;
        }

        if (self::estaConcluido($ticket)) {
            return false;
        }

        $idResponsable = (int)$ticket->id_personal_soporte;

        if ($idResponsable <= 0) {
            return false;
        }

        // Sólo el responsable asignado puede modificar el seguimiento.
        return $idResponsable === self::idUsuario();
    }

    /**
     * Actualiza una actividad (fechas o estatus). Es la misma operación
     * que realiza Soporte (Estaciones) pero con el alcance de Actividades.
     */
    public static function editarActividad(int $idActividad, string $detalle, int $opcion): array
    {
        self::activarModulo();

        if (!self::puede('editar')) {
            return ['success' => false, 'message' => 'No tienes permiso para editar actividades.'];
        }

        $actividad = SoporteActividad::find($idActividad);

        if (!$actividad) {
            return ['success' => false, 'message' => 'Actividad no encontrada.'];
        }

        $ticket = self::ticketEnAlcance((int)$actividad->id_ticket);

        if (!$ticket || !self::puedeEditarSeguimiento($ticket)) {
            return ['success' => false, 'message' => 'Sólo el responsable asignado puede actualizar las actividades.'];
        }

        switch ($opcion) {
            case 1:
                $actividad->fecha_inicio = $detalle === '' ? '0000-00-00' : $detalle;
                break;

            case 2:
                $actividad->fecha_termino = $detalle === '' ? '0000-00-00' : $detalle;
                break;

            case 3:
                $actividad->estado = (int)$detalle;
                break;

            default:
                return ['success' => false, 'message' => 'Opción no válida.'];
        }

        $actividad->save();

        if ($opcion === 3) {
            self::notificarEstadoActividad($ticket, $actividad);
        }

        return ['success' => true, 'message' => 'Actividad actualizada.'];
    }

    /**
     * Registra las fechas comprometidas del ticket y de sus actividades.
     *
     * @param array<int, array{id: int, fecha_inicio?: string, fecha_termino?: string}> $actividades
     */
    public static function guardarSeguimiento(int $idTicket, string $fechaInicio, string $fechaTermino, array $actividades = []): array
    {
        self::activarModulo();

        if (!self::puede('editar')) {
            return ['success' => false, 'message' => 'No tienes permiso para registrar el seguimiento.'];
        }

        $ticket = self::ticketEnAlcance($idTicket);

        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket no encontrado.'];
        }

        if (!self::puedeEditarSeguimiento($ticket)) {
            return ['success' => false, 'message' => 'Sólo el responsable asignado puede registrar el seguimiento.'];
        }

        if ($fechaInicio === '' || $fechaTermino === '') {
            return ['success' => false, 'message' => 'Indica la fecha de inicio y la fecha de término.'];
        }

        $inicio = strtotime($fechaInicio);
        $termino = strtotime($fechaTermino);

        if ($inicio === false || $termino === false) {
            return ['success' => false, 'message' => 'Las fechas no son válidas.'];
        }

        if ($termino < $inicio) {
            return ['success' => false, 'message' => 'La fecha de término no puede ser anterior a la de inicio.'];
        }

        $sinFechas = SoporteService::isoFecha($ticket->fecha_inicio) === ''
            || SoporteService::isoFecha($ticket->fecha_termino) === '';

        $cambiaFechas = $sinFechas
            || SoporteService::isoFecha($ticket->fecha_inicio) !== $fechaInicio
            || SoporteService::isoFecha($ticket->fecha_termino) !== $fechaTermino;

        $ticket->fecha_inicio = $fechaInicio . ' ' . date('H:i:s');
        $ticket->fecha_termino = $fechaTermino . ' ' . date('H:i:s');
        $ticket->save();

        $ticket->tiempo_solucion = SoporteService::diasHabiles($idTicket);
        $ticket->save();

        foreach ($actividades as $actividad) {
            $idActividad = (int)($actividad['id'] ?? 0);

            if ($idActividad <= 0) {
                continue;
            }

            $registro = SoporteActividad::where('id', $idActividad)
                ->where('id_ticket', $idTicket)
                ->first();

            if (!$registro) {
                continue;
            }

            if (array_key_exists('fecha_inicio', $actividad)) {
                $registro->fecha_inicio = ($actividad['fecha_inicio'] ?? '') === '' ? '0000-00-00' : $actividad['fecha_inicio'];
            }

            if (array_key_exists('fecha_termino', $actividad)) {
                $registro->fecha_termino = ($actividad['fecha_termino'] ?? '') === '' ? '0000-00-00' : $actividad['fecha_termino'];
            }

            $registro->save();
        }

        if ($cambiaFechas) {
            self::notificarFechasSeguimiento($ticket, $sinFechas);
        }

        return [
            'success' => true,
            'message' => !$cambiaFechas
                ? 'Seguimiento registrado y notificado al solicitante.'
                : ($sinFechas
                    ? 'Fechas comprometidas registradas y notificadas al solicitante.'
                    : 'Fechas comprometidas editadas y notificadas al solicitante.'),
        ];
    }

    /**
     * Cierra el trabajo de la actividad: el ticket queda a la espera de la
     * validación del solicitante y las actividades quedan finalizadas.
     */
    public static function finalizarSoporte(int $idTicket): array
    {
        self::activarModulo();

        if (!self::puede('editar')) {
            return ['success' => false, 'message' => 'No tienes permiso para finalizar el soporte.'];
        }

        $ticket = self::ticketEnAlcance($idTicket);

        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket no encontrado.'];
        }

        if (!self::puedeEditarSeguimiento($ticket)) {
            return ['success' => false, 'message' => 'Sólo el responsable asignado puede finalizar el soporte.'];
        }

        $estado = (int)$ticket->estado;

        if ($estado !== SoporteService::ESTADO_PENDIENTE && $estado !== SoporteService::ESTADO_EN_PROCESO) {
            return ['success' => false, 'message' => 'El ticket no está en un estado que permita finalizarlo.'];
        }

        $ticket->fecha_termino_real = date('Y-m-d H:i:s');
        $ticket->porcentaje = 100;
        $ticket->id_personal_soporte = self::idUsuario();
        $ticket->estado = SoporteService::ESTADO_EN_PROCESO;
        $ticket->save();

        // Las actividades quedan finalizadas con la fecha del cierre.
        // Al cerrar el trabajo las actividades quedan finalizadas con la
        // fecha del cierre. Las que no tenían fecha de inicio toman la
        // misma fecha de cierre.
        $fechaCierre = date('Y-m-d');

        SoporteActividad::where('id_ticket', $idTicket)
            ->update([
                'estado' => SoporteService::ACTIVIDAD_FINALIZADA,
                'fecha_termino' => $fechaCierre,
            ]);

        SoporteActividad::where('id_ticket', $idTicket)
            ->where(function ($q) {
                $q->whereNull('fecha_inicio')
                    ->orWhere('fecha_inicio', '0000-00-00')
                    ->orWhere('fecha_inicio', '');
            })
            ->update(['fecha_inicio' => $fechaCierre]);

        SoporteService::notificarSoporteFinalizado($idTicket, SoporteService::ESTADO_EN_PROCESO);

        // Comentario interno: el solicitante tiene 3 días para validar.
        SoporteService::guardarComentario(
            $idTicket,
            'Se finalizó el soporte por el área de sistemas, recuerda que tienes tres días para darle seguimiento antes de que se cierre el ticket.',
            2,
            false
        );

        return ['success' => true, 'message' => 'Soporte finalizado.'];
    }

    /**
     * El solicitante devuelve la actividad al área de Sistemas porque el
     * resultado no es el esperado o la actividad sigue inconclusa: la
     * actividad vuelve a "Pendiente", se registra el motivo en los
     * comentarios y se avisa al responsable por Telegram.
     *
     * Comparte la operación con Soporte (Sistemas) para que el mensaje de
     * Telegram use el nombre del módulo en contexto.
     */
    public static function solicitarRevision(int $idTicket, string $motivo = ''): array
    {
        self::activarModulo();

        if (!self::puede('editar') && !self::puede('leer')) {
            return ['success' => false, 'message' => 'No tienes permiso para solicitar la revisión.'];
        }

        $ticket = self::ticketEnAlcance($idTicket);

        if (!$ticket) {
            // El solicitante puede devolver su propia actividad aunque
            // esté asignada a otra persona (es quien la valida).
            $ticket = Soporte::find($idTicket);

            if (
                !$ticket
                || (int)$ticket->id_personal !== self::idUsuario()
                || !in_array((int)$ticket->estado, self::ESTADOS_VISIBLES, true)
            ) {
                return ['success' => false, 'message' => 'Ticket no encontrado.'];
            }
        }

        return SoporteService::registrarSolicitudRevision($ticket, $motivo);
    }

    /**
     * Agrega un comentario en la actividad.
     *
     * Puede hacerlo cualquier persona del área con acceso al módulo (en el
     * sistema anterior el comentario no comprobaba el dueño del ticket), y
     * se avisa al solicitante.
     */
    public static function guardarComentario(int $idTicket, string $comentario): array
    {
        if (!self::puede('editar') && !self::puede('leer')) {
            return ['success' => false, 'message' => 'No tienes permiso para comentar.'];
        }

        $ticket = self::ticketEnAlcance($idTicket);

        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket no encontrado.'];
        }

        $comentario = trim($comentario);

        if ($comentario === '') {
            return ['success' => false, 'message' => 'El comentario es obligatorio.'];
        }

        if (!SoporteService::puedeComentarTicket($ticket)) {
            return ['success' => false, 'message' => 'El ticket ya no admite comentarios.'];
        }

        try {
            SoporteComentario::create([
                'id_ticket' => $idTicket,
                'id_personal' => self::idUsuario(),
                'comentario' => $comentario,
            ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error al guardar el comentario.'];
        }

        // Se avisa al solicitante de la actividad (opción 2 del legacy).
        self::activarModulo();
        SoporteService::notificarComentario($ticket, $comentario);

        return ['success' => true, 'message' => 'Comentario guardado.'];
    }

    /** Cancela la actividad. */
    public static function cancelarTicket(int $idTicket): array
    {
        self::activarModulo();

        return SoporteService::cancelarTicket($idTicket);
    }

    /* ------------------------------------------------------------------ */
    /* Notificaciones (Telegram)                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Avisa al solicitante de las fechas comprometidas por el área.
     */
    private static function notificarFechasSeguimiento(Soporte $ticket, bool $sinFechas): void
    {
        $idTicket = (int)$ticket->id_ticket;

        $encabezado = $sinFechas
            ? '📅 El <b>área de Sistemas</b> registró las <b>fechas comprometidas</b> de tu <b>Actividad</b>:'
            : '✏️ El <b>área de Sistemas</b> actualizó las <b>fechas comprometidas</b> de tu <b>Actividad</b>:';

        $mensaje =
            $encabezado . PHP_EOL . PHP_EOL
            . SoporteService::lineaTelegram('🎫', 'No. de Ticket', SoporteService::folio($idTicket))
            . SoporteService::lineaTelegram('🗒', 'Descripción', (string)$ticket->descripcion)
            . SoporteService::lineaTelegram('🧑‍💻', 'Responsable', SoporteService::nombreUsuario((int)$ticket->id_personal_soporte))
            . SoporteService::lineaTelegram('📆', 'Fecha de inicio', formatearFecha($ticket->fecha_inicio))
            . SoporteService::lineaTelegram('⏳', 'Fecha de término', formatearFecha($ticket->fecha_termino))
            . PHP_EOL . 'El trabajo se atenderá dentro de ese periodo.';

        SoporteService::enviarTelegram([(int)$ticket->id_personal], $mensaje);
    }

    /**
     * Avisa al solicitante de cada cambio de estatus de una actividad.
     */
    private static function notificarEstadoActividad(Soporte $ticket, SoporteActividad $actividad): void
    {
        $mensaje =
            '🔄 Se actualizó el <b>estatus</b> de una <b>actividad</b> de tu <b>Solicitud</b>:' . PHP_EOL . PHP_EOL
            . SoporteService::lineaTelegram('🎫', 'No. de Ticket', SoporteService::folio((int)$ticket->id_ticket))
            . SoporteService::lineaTelegram('🗒', 'Actividad', (string)$actividad->descripcion)
            . SoporteService::lineaTelegram('📌', 'Nuevo estatus', SoporteService::textoEstadoActividad((int)$actividad->estado))
            . SoporteService::lineaTelegram('🧑‍💻', 'Responsable', SoporteService::nombreUsuario((int)$ticket->id_personal_soporte));

        SoporteService::enviarTelegram([(int)$ticket->id_personal], $mensaje);
    }

    /* ------------------------------------------------------------------ */
    /* Utilidades                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Nombre de la estación o departamento al que pertenece el solicitante.
     */
    private static function nombreEstacion(Soporte $ticket): string
    {
        $solicitante = Usuario::find((int)$ticket->id_personal);

        if (!$solicitante) {
            return '';
        }

        if ((int)$solicitante->id_gas === 8) {
            $puesto = \App\Models\Puestos::find((int)$solicitante->id_puesto);

            return $puesto ? (string)$puesto->tipo_puesto : '';
        }

        $estacion = \App\Models\Estacion::find((int)$solicitante->id_gas);

        return $estacion ? (string)$estacion->nombre : '';
    }

    /** El usuario principal del área (id 496). */
    public static function esUsuarioPrincipal(): bool
    {
        return self::idUsuario() === self::USUARIO_SISTEMAS;
    }

    /** Personal del área al que se puede asignar una actividad. */
    public static function getResponsables(): array
    {
        return SoporteService::getResponsables();
    }

    private static function idUsuario(): int
    {
        return (int)(Session::get('usuario')['id'] ?? 0);
    }
}
