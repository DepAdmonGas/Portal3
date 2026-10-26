<?php

namespace App\Services;

use App\Core\Session;
use App\Models\Usuario;
use App\Models\Estacion;
use App\Models\Puestos;
use App\Models\Sistemas\Soporte;
use App\Models\Sistemas\SoporteActividad;
use App\Models\Sistemas\SoporteComentario;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Soporte (Estaciones) — /sistemas/estaciones.
 *
 * Es la vista del área de Sistemas sobre los tickets de soporte de todas las
 * estaciones y departamentos (heredado de departamento-sistemas,
 * app/vistas/soporte/sistemas-index.php y contenido-lista-sistemas.php).
 *
 * Reglas de negocio heredadas que se conservan:
 *
 * - Sólo se muestran los tickets en estado Pendiente (1), En proceso (2),
 *   Finalizado (3) y Expiración de fecha (5). Se excluyen "Creando" (0)
 *   (el solicitante aún no lo ha enviado) y "Cancelado" (4).
 * - Se excluyen los tickets solicitados por el propio personal del
 *   "Departamento Sistemas" (tb_puestos.tipo_puesto = 'Departamento
 *   Sistemas'), igual que el WHERE del sistema anterior.
 * - La columna "Estación o Departamento" muestra el nombre del puesto
 *   cuando el solicitante pertenece a "Comodines" (tb_estaciones.id = 8) y
 *   el nombre de la estación en cualquier otro caso.
 * - El orden heredado es: responsable, estado y fecha de creación (desc).
 *
 * A diferencia de Soporte (Sistemas), este módulo no muestra "los tickets
 * propios" sino los de los contextos (estaciones/departamentos) que el
 * usuario tiene autorizados, mediante el selector de contexto compartido de
 * Portal3 (ModuleStationService).
 */
class SoporteEstacionesService
{
    /** Clave del módulo en modulos / roles_modulos. */
    public const MODULE_KEY = 'sistemas-estaciones';

    /** Clave del módulo en tb_modulos_config (selector de contexto). */
    public const CONTEXT_KEY = 'sistemas-estaciones';

    /** Ruta del módulo en el menú del Home. */
    public const RUTA = '/sistemas/estaciones';

    /** Estados que el módulo muestra (heredado del WHERE del legacy). */
    public const ESTADOS_VISIBLES = [1, 2, 3, 5];

    /** Puesto cuyo personal genera tickets que este módulo excluye. */
    public const PUESTO_EXCLUIDO = 25;

    /** Puesto del área de Sistemas (destinatario de las notificaciones). */
    public const PUESTO_SISTEMAS = 25;

    /** Usuario responsable del área de Sistemas (destinatatario principal). */
    public const USUARIO_SISTEMAS = 496;

    /** Número de días hábiles sugerido por defecto (heredado). */
    public const DIAS_HABILES_POR_DEFECTO = 2;

    /* ------------------------------------------------------------------ */
    /* Permisos                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Permisos efectivos del módulo "sistemas-estaciones".
     *
     * @return array{leer: bool, crear: bool, editar: bool, eliminar: bool, descargar: bool, puede_consultar: bool, puede_agregar: bool, puede_editar: bool, puede_eliminar: bool, puede_descargar: bool}
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
            // El filtro por responsable de la tabla sólo lo ve el usuario
            // principal del área (id 496), igual que en el sistema anterior.
            'puede_filtrar_responsable' => $leer && self::esUsuarioPrincipal(),
        ];

        return $cache;
    }

    /** Contador del home: solicitudes asignadas abiertas o solicitudes propias en proceso. */
    public static function getContadorHome(): int
    {
        $idUsuario = self::idUsuario();
        if ($idUsuario <= 0) {
            return 0;
        }

        $usuario = Usuario::find($idUsuario);
        if (!$usuario) {
            return 0;
        }

        $year = (int) date('Y');
        if ((int)$usuario->id_puesto === self::PUESTO_SISTEMAS) {
            return Soporte::query()
                ->where('id_personal_soporte', $idUsuario)
                ->whereIn('estado', [SoporteService::ESTADO_PENDIENTE, SoporteService::ESTADO_EN_PROCESO])
                ->whereYear('fecha_creacion', $year)
                ->count();
        }

        if (!self::puede('crear')) {
            return 0;
        }

        return Soporte::where('id_personal', $idUsuario)
            ->where('estado', SoporteService::ESTADO_EN_PROCESO)
            ->whereYear('fecha_creacion', $year)
            ->count();
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
            'puede_filtrar_responsable' => false,
        ];
    }

    /**
     * Usuario principal del área de Sistemas (id 496). Es el único que
     * puede filtrar la tabla por responsable, como en el sistema anterior.
     */
    public static function esUsuarioPrincipal(): bool
    {
        return self::idUsuario() === self::USUARIO_SISTEMAS;
    }

    public static function puede(string $accion): bool
    {
        return !empty(self::getPermisos()[$accion]);
    }

    /* ------------------------------------------------------------------ */
    /* Contexto (estaciones y departamentos)                                */
    /* ------------------------------------------------------------------ */

    /**
     * Contexto seleccionado actualmente (null,null = todas las estaciones y
     * departamentos).
     *
     * @return array{id_estacion: int|null, id_depto: int|null, nombre: string}
     */
    public static function getContexto(): array
    {
        $ctx = ModuleStationService::getContext(self::CONTEXT_KEY);

        $idEstacion = $ctx['id_estacion'] ?? null;
        $idDepto = $ctx['id_depto'] ?? null;

        return [
            'id_estacion' => $idEstacion !== null ? (int)$idEstacion : null,
            'id_depto' => $idDepto !== null ? (int)$idDepto : null,
            'nombre' => (string)($ctx['nombre'] ?? ''),
        ];
    }

    /**
     * Nombre de la estación o departamento al que pertenece un ticket
     * (el solicitante). Si el solicitante pertenece a "Comodines" se
     * devuelve el nombre de su puesto.
     */
    public static function nombreEstacionTicket(Soporte $ticket): string
    {
        return self::datosContexto($ticket)['estacion'];
    }

    /**
     * Estaciones que el usuario tiene autorizadas en este módulo.
     *
     * @return array<int, int>
     */
    public static function getEstacionesPermitidas(): array
    {
        $ids = [];

        foreach (ModuleStationService::getAvailableStations(self::CONTEXT_KEY) as $estacion) {
            $ids[] = (int)$estacion['id'];
        }

        return array_values(array_unique($ids));
    }

    /**
     * Departamentos (puestos) que el usuario tiene autorizados en este módulo.
     *
     * @return array<int, int>
     */
    public static function getDepartamentosPermitidos(): array
    {
        $ids = [];

        foreach (ModuleStationService::getAvailableDepartments(self::CONTEXT_KEY) as $depto) {
            $ids[] = (int)$depto['id'];
        }

        return array_values(array_unique($ids));
    }

    /**
     * Alcance de consulta: la estación o el departamento seleccionado, o todos
     * los autorizados cuando no hay selección.
     *
     * La selección nunca amplía el alcance (mismo criterio que
     * IncidenciasService::getAlcance()).
     *
     * @return array{estaciones: array<int, int>, departamentos: array<int, int>, todos: bool}
     */
    public static function getAlcance(): array
    {
        $estaciones = self::getEstacionesPermitidas();
        $departamentos = self::getDepartamentosPermitidos();

        $contexto = self::getContexto();

        if ($contexto['id_estacion'] !== null) {
            $permitidas = in_array($contexto['id_estacion'], $estaciones, true)
                ? [$contexto['id_estacion']]
                : [];

            return ['estaciones' => $permitidas, 'departamentos' => [], 'todos' => false];
        }

        if ($contexto['id_depto'] !== null) {
            $permitidos = in_array($contexto['id_depto'], $departamentos, true)
                ? [$contexto['id_depto']]
                : [];

            return ['estaciones' => [], 'departamentos' => $permitidos, 'todos' => false];
        }

        return ['estaciones' => $estaciones, 'departamentos' => $departamentos, 'todos' => true];
    }

    /**
     * La columna "Estación" sólo tiene sentido cuando se consultan todos los
     * contextos: con un contexto único sería redundante.
     */
    public static function mostrarColumnaEstacion(): bool
    {
        return self::getContexto()['id_estacion'] === null
            && self::getContexto()['id_depto'] === null;
    }

    /**
     * Personal del área de Sistemas al que se puede asignar un ticket.
     * Se reutiliza la lista del servicio base (misma que el legacy).
     *
     * @return array<int, array{id: int, nombre: string}>
     */
    public static function getResponsables(): array
    {
        return SoporteService::getResponsables();
    }

    /* ------------------------------------------------------------------ */
    /* Listado                                                             */
    /* ------------------------------------------------------------------ */

        /**
     * Fija el módulo de contexto para que los mensajes de Telegram de este
     * módulo se identifiquen como "Soporte (Estaciones)".
     */
    private static function activarModulo(): void
    {
        SoporteService::usarModulo(self::MODULE_KEY);
    }
/**
     * Tickets del contexto seleccionado.
     *
     * @param array{estaciones: array<int, int>, departamentos: array<int, int>, todos: bool}|null $alcance
     * @return array<int, array<string, mixed>>
     */
    public static function getData(?int $estado = null, ?int $idResponsable = null, ?array $alcance = null, string $texto = ''): array
    {
        $alcance = $alcance ?? self::getAlcance();

        $estaciones = $alcance['estaciones'];
        $departamentos = $alcance['departamentos'];

        // Sin alcances no se puede consultar nada.
        if (empty($estaciones) && empty($departamentos)) {
            return [];
        }

        $query = Soporte::query()
            ->whereIn('estado', self::ESTADOS_VISIBLES)
            ->whereIn('id_personal', self::solicitantesPermitidos($estaciones, $departamentos));

        if ($estado !== null && $estado !== SoporteService::ESTADO_TODOS) {
            $query->where('estado', $estado);
        }

        if ($idResponsable !== null && $idResponsable > 0) {
            $query->where('id_personal_soporte', $idResponsable);
        }

     


        // Orden heredado: responsable, estado y fecha de creación (desc).
        $tickets = $query->orderBy('id_personal_soporte')
            ->orderBy('estado')
            ->orderBy('fecha_creacion', 'desc')
            ->get();

   $texto = trim($texto);

   if ($texto !== '') {
   $tickets = $tickets->filter(function (Soporte $ticket) use ($texto) {
   $folio = SoporteService::folio((int)$ticket->id_ticket);

   return mb_stripos($folio, $texto) !== false
   || mb_stripos((string)$ticket->descripcion, $texto) !== false;
   })->values();
   }

        $conteos = self::contarComentarios($tickets->pluck('id_ticket')->all());

        return $tickets
            ->map(function (Soporte $ticket) use ($conteos) {
                $fila = SoporteService::mapearTicketPublico($ticket, $conteos[(int)$ticket->id_ticket] ?? 0);

                return array_merge($fila, self::datosContexto($ticket), self::datosFlujo($ticket));
            })
            ->all();
    }

    /**
     * Datos de flujo del ticket: si ya tiene responsable asignado y si el
     * usuario en sesión puede trabajar el seguimiento.
     *
     * @return array<string, mixed>
     */
    private static function datosFlujo(Soporte $ticket): array
    {
        $idResponsable = (int)$ticket->id_personal_soporte;

        return [
            'id_personal_soporte' => $idResponsable,
            'tiene_responsable' => $idResponsable > 0,
            // Si el ticket ya está concluido (Finalizado / Expiración) no
            // requiere responsable: el estado manda sobre el responsable.
            'esta_concluido' => self::estaConcluido($ticket),
            // Todo el equipo de Sistemas con permiso de edición registra el
            // seguimiento del ticket.
            'puede_seguimiento' => self::puedeEditarSeguimiento($ticket),
            // Las actividades sólo se editan después de registrar las
            // fechas comprometidas del ticket.
            'puede_editar_actividades' => self::puedeEditarActividades($ticket),
            'puede_asignar' => self::puedeAsignarResponsable($ticket),
        ];
    }

    /**
     * El ticket todavía no tiene responsable: se debe asignar antes de
     * cualquier otra acción (así funcionaba en el sistema anterior).
     *
     * Los tickets que ya están concluidos (Finalizado o Expiración de
     * fecha) quedan excluidos: ya no requieren responsable.
     */
    public static function puedeAsignarResponsable(Soporte $ticket): bool
    {
        if (!self::puede('editar')) {
            return false;
        }

        if (self::estaConcluido($ticket)) {
            return false;
        }

        return (int)$ticket->id_personal_soporte <= 0;
    }

    /**
     * El ticket ya está concluido (Finalizado o Expiración de fecha).
     */
    public static function estaConcluido(Soporte $ticket): bool
    {
        $estado = (int)$ticket->estado;

        return $estado === SoporteService::ESTADO_FINALIZADO || $estado === 5;
    }

    /**
     * Puede registrar las fechas comprometidas del ticket.
     *
     * Sólo lo puede hacer el responsable asignado (o el responsable principal
     * del área, id 496) y únicamente mientras el ticket siga abierto
     * (estado "Pendiente"). Es el primer paso del flujo: al registrar las
     * fechas se notifica al solicitante y con ello arranca el seguimiento.
     */
    public static function puedeEditarSeguimiento(Soporte $ticket): bool
    {
        if (!self::puede('editar')) {
            return false;
        }

        // Con el trabajo ya cerrado la pantalla queda en modo sólo lectura.
        if ((int)$ticket->estado !== SoporteService::ESTADO_PENDIENTE) {
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
     * Las actividades se pueden modificar cuando el ticket ya tiene fechas
     * comprometidas registradas (el seguimiento ya arrancó) y el usuario es
     * el responsable del ticket.
     */
    public static function puedeEditarActividades(Soporte $ticket): bool
    {
        if (!self::puedeEditarSeguimiento($ticket)) {
            return false;
        }

        return SoporteService::isoFecha($ticket->fecha_inicio) !== ''
            && SoporteService::isoFecha($ticket->fecha_termino) !== '';
    }

    /**
     * Ids de los usuarios solicitantes visibles en el contexto dado.
     *
     * Se excluye el personal del "Departamento Sistemas" (regla heredada) y
     * se limita a las estaciones/departamentos autorizados.
     *
     * @param array<int, int> $estaciones
     * @param array<int, int> $departamentos
     * @return array<int, int>
     */
    private static function solicitantesPermitidos(array $estaciones, array $departamentos): array
    {
        $ids = [];

        if (!empty($estaciones)) {
            $ids = array_merge($ids, Usuario::whereIn('id_gas', $estaciones)->pluck('id')->map(fn($v) => (int)$v)->all());
        }

        if (!empty($departamentos)) {
            $ids = array_merge(
                $ids,
                Usuario::whereIn('id_puesto', $departamentos)
                    ->where('id_gas', self::ESTACION_COMODINES)
                    ->pluck('id')
                    ->map(fn($v) => (int)$v)
                    ->all()
            );
        }

        $ids = array_values(array_unique($ids));

        if (empty($ids)) {
            return [];
        }

        // El personal del Departamento Sistemas no genera tickets para este
        // módulo (regla heredada del WHERE del legacy).
        $excluidos = Usuario::whereIn('id', $ids)
            ->where('id_puesto', self::PUESTO_EXCLUIDO)
            ->pluck('id')
            ->map(fn($v) => (int)$v)
            ->all();

        return array_values(array_diff($ids, $excluidos));
    }

    /** Id de la estación "Comodines" (sede de los departamentos). */
    public const ESTACION_COMODINES = 8;

    /**
     * Datos de contexto de un ticket: estación o departamento del solicitante
     * y nombre del solicitante (heredado del contenido-lista-sistemas.php).
     *
     * @return array<string, string>
     */
    private static function datosContexto(Soporte $ticket): array
    {
        $idPersonal = (int)$ticket->id_personal;

        $solicitante = Usuario::find($idPersonal);
        $idGas = $solicitante ? (int)$solicitante->id_gas : 0;
        $idPuesto = $solicitante ? (int)$solicitante->id_puesto : 0;

        $nombreEstacion = '';
        if ($idGas === self::ESTACION_COMODINES) {
            $puesto = Puestos::find($idPuesto);
            $nombreEstacion = $puesto ? (string)$puesto->tipo_puesto : '';
        } else {
            $estacion = Estacion::find($idGas);
            $nombreEstacion = $estacion ? (string)$estacion->nombre : '';
        }

        return [
            'estacion' => $nombreEstacion,
            'solicitante' => SoporteService::nombreUsuario($idPersonal),
            'id_personal' => $idPersonal,
        ];
    }

    /**
     * Total de comentarios de varios tickets en una sola consulta.
     *
     * @param array<int, mixed> $idTickets
     * @return array<int, int>
     */
    private static function contarComentarios(array $idTickets): array
    {
        $idTickets = array_values(array_filter(array_map('intval', $idTickets)));

        if (empty($idTickets)) {
            return [];
        }

        $conteos = SoporteComentario::whereIn('id_ticket', $idTickets)
            ->selectRaw('id_ticket, COUNT(*) AS total')
            ->groupBy('id_ticket')
            ->pluck('total', 'id_ticket');

        $resultado = [];

        foreach ($conteos as $idTicket => $total) {
            $resultado[(int)$idTicket] = (int)$total;
        }

        return $resultado;
    }

    /* ------------------------------------------------------------------ */
    /* Pendientes                                                         */
    /* ------------------------------------------------------------------ */

    /**
     * Contadores de pendientes por estación y por departamento.
     *
     * Un ticket está pendiente cuando sigue abierto para el área de Sistemas:
     * estado Pendiente (1) o En proceso (2). Los estados Finalizado (3) y
     * Expiración (5) ya no requieren atención y los estados Creando (0) y
     * Cancelado (4) no son visibles para este módulo.
     *
     * @return array{stations: array<int, array<string, mixed>>, departments: array<int, array<string, mixed>>}
     */
    public static function getPendingCounts(): array
    {
        $estacionesPermitidas = self::getEstacionesPermitidas();
        $departamentosPermitidos = self::getDepartamentosPermitidos();

        $stations = [];

        foreach (ModuleStationService::getAvailableStations(self::CONTEXT_KEY) as $estacion) {
            $idEstacion = (int)$estacion['id'];

            $mostrar = in_array($idEstacion, $estacionesPermitidas, true);

            $stations[] = [
                'id' => $idEstacion,
                'nombre' => (string)$estacion['nombre'],
                'pendientes' => $mostrar ? self::contarPendientesEstacion($idEstacion) : 0,
            ];
        }

        $departments = [];

        foreach (ModuleStationService::getAvailableDepartments(self::CONTEXT_KEY) as $depto) {
            $idDepto = (int)$depto['id'];

            $mostrar = in_array($idDepto, $departamentosPermitidos, true);

            $departments[] = [
                'id_puesto' => $idDepto,
                'nombre' => (string)$depto['nombre'],
                'pendientes' => $mostrar ? self::contarPendientesDepartamento($idDepto) : 0,
            ];
        }

        return [
            'stations' => $stations,
            'departments' => $departments,
        ];
    }

    /** Tickets pendientes solicitados en una estación. */
    private static function contarPendientesEstacion(int $idEstacion): int
    {
        $solicitantes = Usuario::where('id_gas', $idEstacion)
            ->where('id_puesto', '!=', self::PUESTO_EXCLUIDO)
            ->pluck('id')
            ->map(fn($v) => (int)$v)
            ->all();

        if (empty($solicitantes)) {
            return 0;
        }

        return Soporte::whereIn('id_personal', $solicitantes)
            ->whereIn('estado', [SoporteService::ESTADO_PENDIENTE, SoporteService::ESTADO_EN_PROCESO])
            ->count();
    }

    /** Tickets pendientes solicitados por un departamento (Comodines). */
    private static function contarPendientesDepartamento(int $idPuesto): int
    {
        $solicitantes = Usuario::where('id_puesto', $idPuesto)
            ->where('id_gas', self::ESTACION_COMODINES)
            ->pluck('id')
            ->map(fn($v) => (int)$v)
            ->all();

        if (empty($solicitantes)) {
            return 0;
        }

        return Soporte::whereIn('id_personal', $solicitantes)
            ->whereIn('estado', [SoporteService::ESTADO_PENDIENTE, SoporteService::ESTADO_EN_PROCESO])
            ->count();
    }

    /**
     * Mapa de pendientes que consume ModuleStationService::render() para
     * pintar los contadores del selector y el badge.
     *
     * @return array<string, int>
     */
    public static function getPendientesMap(): array
    {
        $counts = self::getPendingCounts();

        $mapa = ['total' => 0];

        foreach ($counts['stations'] as $estacion) {
            $mapa['estacion_' . $estacion['id']] = (int)$estacion['pendientes'];
            $mapa['total'] += (int)$estacion['pendientes'];
        }

        foreach ($counts['departments'] as $depto) {
            $mapa['depto_' . $depto['id_puesto']] = (int)$depto['pendientes'];
            $mapa['total'] += (int)$depto['pendientes'];
        }

        return $mapa;
    }

    /**
     * Pendientes del contexto seleccionado (badge de la vista).
     */
    public static function getPendientesContexto(): int
    {
        $mapa = self::getPendientesMap();
        $contexto = self::getContexto();

        if ($contexto['id_estacion'] !== null) {
            return (int)($mapa['estacion_' . $contexto['id_estacion']] ?? 0);
        }

        if ($contexto['id_depto'] !== null) {
            return (int)($mapa['depto_' . $contexto['id_depto']] ?? 0);
        }

        return (int)$mapa['total'];
    }

    /* ------------------------------------------------------------------ */
    /* Detalle y comentarios                                                */
    /* ------------------------------------------------------------------ */

    /**
     * Ticket del contexto, ya validado.
     */
    private static function ticketEnAlcance(int $idTicket): ?Soporte
    {
        $alcance = self::getAlcance();

        $estaciones = $alcance['estaciones'];
        $departamentos = $alcance['departamentos'];

        if (empty($estaciones) && empty($departamentos)) {
            return null;
        }

        $ticket = Soporte::where('id_ticket', $idTicket)->first();

        if (!$ticket) {
            return null;
        }

        if (!in_array((int)$ticket->estado, self::ESTADOS_VISIBLES, true)) {
            return null;
        }

        $solicitantes = self::solicitantesPermitidos($estaciones, $departamentos);

        if (!in_array((int)$ticket->id_personal, $solicitantes, true)) {
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
            'data' => array_merge($detalle, self::datosContexto($ticket), self::datosEdicion($ticket), self::datosFlujo($ticket), [
                'responsables' => self::getResponsables(),
            ]),
        ];
    }

    /**
     * Datos crudos que necesita la pantalla de seguimiento.
     *
     * @return array<string, mixed>
     */
    private static function datosEdicion(Soporte $ticket): array
    {
        return [
            'id_personal_soporte' => (int)$ticket->id_personal_soporte,
            'fecha_inicio_iso' => SoporteService::isoFecha($ticket->fecha_inicio),
            'fecha_termino_iso' => SoporteService::isoFecha($ticket->fecha_termino),
            'fecha_creacion_iso' => SoporteService::isoFecha($ticket->fecha_creacion),
            'fecha_termino_real_iso' => SoporteService::isoFecha($ticket->fecha_termino_real),
            // Fechas ya formateadas con formatearFecha() para mostrarlas.
            'fecha_inicio_texto' => formatearFecha($ticket->fecha_inicio),
            'fecha_termino_texto' => formatearFecha($ticket->fecha_termino),
            'fecha_termino_real_texto' => formatearFecha($ticket->fecha_termino_real),
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

    /**
     * Guarda un comentario en un ticket del contexto.
     * La notificación de Telegram es la misma de Soporte (Sistemas).
     */
    public static function guardarComentario(int $idTicket, string $comentario): array
    {
        self::activarModulo();

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

        /*
        if (!SoporteService::puedeComentarTicket($ticket)) {
            return ['success' => false, 'message' => 'El ticket ya no admite comentarios.'];
        }
        */
        try {
            SoporteComentario::create([
                'id_ticket' => $idTicket,
                'id_personal' => self::idUsuario(),
                'comentario' => $comentario,
            ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error al guardar el comentario.'];
        }

        SoporteService::notificarComentario($ticket, $comentario);

        return ['success' => true, 'message' => 'Comentario guardado.'];
    }

    /* ------------------------------------------------------------------ */
    /* Acciones del área de Sistemas                                        */
    /* ------------------------------------------------------------------ */

    /**
     * Asigna el responsable del ticket (equivalente a asignarPersonal).
     * Notifica por Telegram al responsable asignado.
     */
    public static function asignarResponsable(int $idTicket, int $idResponsable): array
    {
        self::activarModulo();

        if (!self::puede('editar')) {
            return ['success' => false, 'message' => 'No tienes permiso para asignar el responsable.'];
        }

        $ticket = self::ticketEnAlcance($idTicket);

        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket no encontrado.'];
        }

        if ((int)$ticket->id_personal_soporte > 0) {
            return ['success' => false, 'message' => 'El ticket ya tiene un responsable asignado.'];
        }

        if (self::estaConcluido($ticket)) {
            return ['success' => false, 'message' => 'El ticket ya está concluido, no requiere responsable asignado.'];
        }

        $valido = false;

        foreach (self::getResponsables() as $responsable) {
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

        self::notificarAsignacion($ticket, $idResponsable);

        return ['success' => true, 'message' => 'Responsable asignado. Ya puedes iniciar el seguimiento.'];
    }

    /**
     * Guarda el seguimiento: fechas del ticket y de sus actividades.
     *
     * Al guardar se notifica por Telegram al solicitante, que es quien
     * necesita conocer las fechas comprometidas.
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
            $cerrado = (int)$ticket->estado !== SoporteService::ESTADO_PENDIENTE;

            return [
                'success' => false,
                'message' => $cerrado
                    ? 'El ticket ya está cerrado: no se pueden modificar las fechas.'
                    : 'Sólo el responsable asignado puede registrar el seguimiento.',
            ];
        }

            if ($fechaInicio === '' || $fechaTermino === '') {
            return ['success' => false, 'message' => 'Indica la fecha de inicio y la fecha de término.'];
        }

        $inicio = strtotime($fechaInicio);
        $termino = strtotime($fechaTermino);

        if ($inicio === false || $termino === false) {
            return ['success' => false, 'message' => 'Las fechas no son válidas.'];
        }

        $hoy = strtotime(date('Y-m-d'));

        if ($inicio < $hoy) {
            return ['success' => false, 'message' => 'La fecha de inicio no puede ser anterior a hoy.'];
        }

        if ($termino < $inicio) {
            return ['success' => false, 'message' => 'La fecha de término no puede ser anterior a la de inicio.'];
        }

        // Evita fechas absurdas (por ejemplo, un año 2498 por un error de captura).
        if ($termino > strtotime('+2 years')) {
            return ['success' => false, 'message' => 'La fecha de término no puede ser mayor a dos años.'];
        }

        // Se distingue entre el primer registro y la edición posterior.
        $sinFechas = SoporteService::isoFecha($ticket->fecha_inicio) === ''
            || SoporteService::isoFecha($ticket->fecha_termino) === '';

        $cambiaFechas = $sinFechas
            || SoporteService::isoFecha($ticket->fecha_inicio) !== $fechaInicio
            || SoporteService::isoFecha($ticket->fecha_termino) !== $fechaTermino;

        $ticket->fecha_inicio = $fechaInicio . ' ' . date('H:i:s');
        $ticket->fecha_termino = $fechaTermino . ' ' . date('H:i:s');
        $ticket->save();

        $ticket->tiempo_solucion = self::diasHabiles($idTicket);
        $ticket->save();

        // Fechas de las actividades del ticket.
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
                // Columna NOT NULL: la fecha vacía se guarda como "cero".
                $registro->fecha_inicio = ($actividad['fecha_inicio'] ?? '') === '' ? '0000-00-00' : $actividad['fecha_inicio'];
            }

            if (array_key_exists('fecha_termino', $actividad)) {
                $registro->fecha_termino = ($actividad['fecha_termino'] ?? '') === '' ? '0000-00-00' : $actividad['fecha_termino'];
            }

            $registro->save();
        }

        self::notificarFechasSeguimiento($ticket, $sinFechas, $cambiaFechas);

        if (!$cambiaFechas) {
            return ['success' => true, 'message' => 'Seguimiento registrado y notificado al solicitante.'];
        }

        return [
            'success' => true,
            'message' => $sinFechas
                ? 'Fechas comprometidas registradas y notificadas al solicitante.'
                : 'Fechas comprometidas editadas y notificadas al solicitante.',
        ];
    }

    /**
     * Actualiza los campos del ticket (heredado de Sistemas::EditarRegistro).
     * Opciones: 2 fecha_inicio, 3 fecha_termino, 5 porcentaje.
     */
    public static function editarRegistro(int $idTicket, string $detalle, int $opcion): array
    {
        if (!self::puede('editar')) {
            return ['success' => false, 'message' => 'No tienes permiso para editar.'];
        }

        $ticket = self::ticketEnAlcance($idTicket);

        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket no encontrado.'];
        }

        if (!self::puedeEditarActividades($ticket)) {
            return ['success' => false, 'message' => 'Registra primero las fechas comprometidas del ticket.'];
        }

        switch ($opcion) {
            case 1:
                $actividad->fecha_inicio = $detalle === '' ? null : $detalle;
                break;

            case 3:
                $ticket->fecha_termino = $detalle . ' ' . date('H:i:s');
                break;

            case 5:
                $ticket->porcentaje = (int)$detalle;
                break;

            default:
                return ['success' => false, 'message' => 'Opción no válida.'];
        }

        $ticket->save();

        if ($opcion === 3) {
            $ticket->tiempo_solucion = self::diasHabiles($idTicket);
            $ticket->save();
        }

        return ['success' => true, 'message' => 'Registro actualizado.'];
    }

    /** Actualiza una actividad (heredado de Sistemas::EditarActividad). */
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

        if (!self::ticketEnAlcance((int)$actividad->id_ticket)) {
            return ['success' => false, 'message' => 'Actividad no encontrada.'];
        }

        $ticket = self::ticketEnAlcance((int)$actividad->id_ticket);

        if (!$ticket || !self::puedeEditarSeguimiento($ticket)) {
            return ['success' => false, 'message' => 'Sólo el responsable asignado puede actualizar las actividades.'];
        }

        switch ($opcion) {
            case 1:
                // Las columnas son NOT NULL: se usa la fecha "cero" de MySQL
                // en lugar de null (provocaría un error en la base de datos).
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
     * Cierra el trabajo de Sistemas sobre el ticket.
     *
     * $finalizar = 2 deja el ticket "En proceso": el solicitante tiene
     * 3 días para validarlo (si no, se cierra automáticamente).
     * $finalizar = 3 lo deja "Finalizado" directamente.
     */
    public static function finalizarSoporte(int $idTicket, int $finalizar): array
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
            return ['success' => false, 'message' => 'No tienes permiso para finalizar el soporte.'];
        }

        // El trabajo sólo se cierra cuando ya hay fechas comprometidas.
        if (!self::puedeEditarActividades($ticket)) {
            return ['success' => false, 'message' => 'Registra primero las fechas comprometidas del ticket.'];
        }

        $estado = (int)$ticket->estado;

        if ($estado !== SoporteService::ESTADO_PENDIENTE && $estado !== SoporteService::ESTADO_EN_PROCESO) {
            return ['success' => false, 'message' => 'El ticket no está en un estado que permita finalizarlo.'];
        }

        $ticket->fecha_termino_real = date('Y-m-d H:i:s');
        $ticket->porcentaje = 100;
        $ticket->id_personal_soporte = self::idUsuario();
        $ticket->estado = $finalizar;
        $ticket->save();

        // Al cerrar el trabajo las actividades quedan finalizadas con la
        // fecha de término del cierre. La fecha de inicio se conserva: si la
        // actividad no tenía, se toma la fecha de inicio registrada del
        // ticket.
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


        SoporteService::notificarSoporteFinalizado($idTicket, $finalizar);

        if ($finalizar === SoporteService::ESTADO_EN_PROCESO) {
            // Comentario interno: la notificación la emite
            // notificarSoporteFinalizado(), no se duplica.
            SoporteService::guardarComentario(
                $idTicket,
                'Se finalizó el soporte por el área de sistemas, recuerda que tienes tres días para darle seguimiento antes de que se cierre el ticket.',
                2,
                false
            );
        }

        return ['success' => true, 'message' => 'Soporte finalizado.'];
    }

    /** Cancela un ticket del contexto (equivale a Sistemas::CancelarTicket). */
    public static function cancelarTicket(int $idTicket): array
    {
        if (!self::puede('eliminar')) {
            return ['success' => false, 'message' => 'No tienes permiso para cancelar.'];
        }

        $ticket = self::ticketEnAlcance($idTicket);

        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket no encontrado.'];
        }

        // El sistema anterior deshabilitaba la eliminación en los tickets
        // finalizados y en expiración de fecha.
        $estado = (int)$ticket->estado;

        if ($estado !== SoporteService::ESTADO_PENDIENTE && $estado !== SoporteService::ESTADO_EN_PROCESO) {
            return ['success' => false, 'message' => 'El ticket ya no se puede cancelar.'];
        }

        $ticket->estado = SoporteService::ESTADO_CANCELADO;
        $ticket->save();

        return ['success' => true, 'message' => 'Ticket cancelado.'];
    }

    /* ------------------------------------------------------------------ */
    /* Notificaciones (Telegram)                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Aviso al responsable asignado (heredado de Sistemas::asignarPersonal).
     */
    private static function notificarAsignacion(Soporte $ticket, int $idResponsable): void
    {
        SoporteService::notificarAsignacionResponsable($ticket, $idResponsable);
    }
    /**
     * Avisa al solicitante de las fechas comprometidas por el área de
     * Sistemas al registrar el seguimiento.
     */
    private static function notificarFechasSeguimiento(Soporte $ticket, bool $sinFechas, bool $cambiaFechas): void
    {
        if (!$cambiaFechas) {
            return;
        }

        $idTicket = (int)$ticket->id_ticket;

        $encabezado = $sinFechas
            ? '📅 El <b>área de Sistemas</b> registró las <b>fechas comprometidas</b> de tu <b>Solicitud</b>:'
            : '✏️ El <b>área de Sistemas</b> actualizó las <b>fechas comprometidas</b> de tu <b>Solicitud</b>:';

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
        $idTicket = (int)$ticket->id_ticket;

        $mensaje =
            '🔄 Se actualizó el <b>estatus</b> de una <b>actividad</b> de tu <b>Solicitud</b>:' . PHP_EOL . PHP_EOL
            . SoporteService::lineaTelegram('🎫', 'No. de Ticket', SoporteService::folio($idTicket))
            . SoporteService::lineaTelegram('🗒', 'Actividad', (string)$actividad->descripcion)
            . SoporteService::lineaTelegram('📌', 'Nuevo estatus', SoporteService::textoEstadoActividad((int)$actividad->estado))
            . SoporteService::lineaTelegram('🧑‍💻', 'Responsable', SoporteService::nombreUsuario((int)$ticket->id_personal_soporte));

        SoporteService::enviarTelegram([(int)$ticket->id_personal], $mensaje);
    }

    /* ------------------------------------------------------------------ */
    /* Utilidades                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Calcula las fechas de inicio y término sugeridas usando días hábiles
     * (regla heredada de sistemas-editar-registro.php).
     *
     * @return array{fecha_inicio: string, fecha_termino: string, dias_habiles: int}
     */
    public static function calcularFechasSugeridas(int $diasHabiles = self::DIAS_HABILES_POR_DEFECTO): array
    {
        return SoporteService::calcularFechasSugeridas($diasHabiles);
    }

    /** Días de solución (DATEDIFF) de un ticket. */
    private static function diasHabiles(int $idTicket): int
    {
        return SoporteService::diasHabiles($idTicket);
    }

    private static function idUsuario(): int
    {
        return (int)(Session::get('usuario')['id'] ?? 0);
    }
}
