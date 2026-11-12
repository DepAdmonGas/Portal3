<?php

namespace App\Services;

use App\Core\Session;
use App\Models\Usuario;
use App\Models\Sistemas\Soporte;
use App\Models\Sistemas\SoporteActividad;
use App\Models\Sistemas\SoporteEvidencia;
use App\Models\Sistemas\SoporteComentario;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Soporte del Departamento de Sistemas (/sistemas/soporte).
 *
 * Lógica heredada de departamento-sistemas (app/modelo/Sistemas.php,
 * SistemasContenido.php y controlador-sistemas.php). Se conserva el
 * comportamiento de negocio original:
 *
 * - Cada usuario ve únicamente sus propios tickets (id_personal = sesión).
 * - Categorías: "Actividad" (con actividades) y "Evidencia" (con evidencias).
 * - Estados del ticket: 0 Creando, 1 Pendiente, 2 En proceso, 3 Finalizado,
 *   4 Cancelado. El filtro de búsqueda acepta 5 = Todos.
 * - El folio se calcula como id_ticket máximo + 1 (la tabla no confía en el
 *   AUTO_INCREMENT por compatibilidad con el sistema heredado).
 * - Los archivos de actividades y evidencias se guardan con el mismo formato
 *   de nombre generado que el sistema heredado (ActividadYYYYMMDD-<id>.<ext>
 *   y EvidenciaYYYYMMDD-<id>.<ext>) y se almacenan en public/uploads/archivos
 *   y se descargan a través de DownloadController.
 *
 * El acceso se resuelve con el permiso del módulo "sistemas-soporte"
 * (roles_modulos por puesto o usuarios_modulos por usuario). La UI y el
 * backend validan los permisos (leer, crear, editar, eliminar, descargar).
 */
class SoporteService
{
    /** Clave del módulo en modulos / roles_modulos. */
    public const MODULE_KEY = 'sistemas-soporte';

    /** Ruta del módulo en el menú del Home. */
    public const RUTA = '/sistemas/soporte';

    /** Carpeta física de archivos de actividades. */
    public const UPLOAD_DIR_ACTIVIDADES = 'public/uploads/archivos/soporte/actividades/';

    /** Carpeta física de archivos de evidencias. */
    public const UPLOAD_DIR_EVIDENCIAS = 'public/uploads/archivos/soporte/evidencias/';

    /** Tipos de descarga registrados en DownloadController. */
    public const TIPO_DESCARGA_ACTIVIDAD = 'soporte-actividades';
    public const TIPO_DESCARGA_EVIDENCIA = 'soporte-evidencias';

    /** Estados del ticket. */
    public const ESTADO_CREANDO = 0;
    public const ESTADO_PENDIENTE = 1;
    public const ESTADO_EN_PROCESO = 2;
    public const ESTADO_FINALIZADO = 3;
    public const ESTADO_CANCELADO = 4;
    public const ESTADO_TODOS = 5;

    /** Categorías del ticket. */
    public const CATEGORIA_ACTIVIDAD = 'Actividad';
    public const CATEGORIA_EVIDENCIA = 'Evidencia';

    /** Estados de una actividad. */
    public const ACTIVIDAD_PENDIENTE = 0;
    public const ACTIVIDAD_EN_PROCESO = 1;
    public const ACTIVIDAD_FINALIZADA = 2;

    /** Puesto del área de Sistemas que recibe las notificaciones de Telegram. */
    public const PUESTO_SISTEMAS = 25;

    /** Usuario responsable del área de Sistemas (destinatatario principal). */
    public const USUARIO_SISTEMAS = 496;

    /** Extensiones permitidas (el sistema heredado aceptaba cualquier archivo). */
    private const EXTENSIONES_PERMITIDAS = [
        'pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'txt', 'csv', 'zip', 'rar',
    ];

    /* ------------------------------------------------------------------ */
    /* Permisos                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Permisos efectivos del usuario actual. Expone las claves heredadas
     * (leer/crear/editar/eliminar/descargar) y las claves puede_* que usa la
     * interfaz (mismo patrón que el resto de Portal3).
     *
     * Se resuelven desde la sesión (ModuloService::permisosSesion) y sólo si
     * no están disponibles se consulta la base de datos, igual que en
     * IncidenciasService y SalaJuntasService. Esto evita el costoso
     * `Modulo::with(['roles','usuarios'])` en cada petición.
     *
     * @return array{leer: bool, crear: bool, editar: bool, eliminar: bool, descargar: bool, puede_consultar: bool, puede_agregar: bool, puede_editar: bool, puede_eliminar: bool, puede_descargar: bool}
     */
    public static function getPermisos(): array
    {
        static $cache = [];

        $clave = self::$moduloContexto;

        if (isset($cache[$clave])) {
            return $cache[$clave];
        }

        $idUsuario = self::idUsuario();

        if ($idUsuario <= 0) {
            return self::sinPermisos();
        }

        $permiso = self::permisoModulo($idUsuario);

        $leer = !empty($permiso['leer']);

        $cache[$clave] = [
            'leer' => $leer,
            'crear' => $leer && !empty($permiso['crear']),
            'editar' => $leer && !empty($permiso['editar']),
            'eliminar' => $leer && !empty($permiso['eliminar']),
            'descargar' => $leer && !empty($permiso['descargar']),
            'puede_consultar' => $leer,
            'puede_agregar' => $leer && !empty($permiso['crear']),
            'puede_editar' => $leer && !empty($permiso['editar']),
            'puede_eliminar' => $leer && !empty($permiso['eliminar']),
            'puede_descargar' => $leer && !empty($permiso['descargar']),
        ];

        return $cache[$clave];
    }

    /**
     * Módulo contra el que se validan las acciones en curso.
     *
     * Permite que otros módulos reutilicen las operaciones de este servicio
     * validando contra sus propios permisos (por ejemplo, Actividades
     * (Sistemas), que comparte tickets pero tiene su propio módulo).
     */
    private static string $moduloContexto = self::MODULE_KEY;

    /**
     * Fija el módulo de contexto para las siguientes operaciones.
     */
    /**
     * Nombre del modulo que aparece en los mensajes de Telegram: permite
     * distinguir "Soporte (Sistemas)" de "Actividades (Sistemas)" y de
     * "Soporte (Estaciones)".
     */
    public static function nombreModulo(): string
    {
        return match (self::$moduloContexto) {
            'sistemas-actividades' => 'Actividades (Sistemas)',
            'sistemas-estaciones' => 'Soporte (Estaciones)',
            default => 'Soporte (Sistemas)',
        };
    }

    public static function usarModulo(string $clave): void
    {
        self::$moduloContexto = $clave;
    }

    /**
     * Permiso del módulo "sistemas-soporte": primero la sesión (rápido) y
     * como respaldo la consulta directa a la base de datos.
     */
    private static function permisoModulo(int $idUsuario): array
    {
        $permisos = ModuloService::permisosSesion(self::$moduloContexto);

        if (!empty($permisos)) {
            return $permisos;
        }

        return ModuloService::getPermiso($idUsuario, self::$moduloContexto) ?: [];
    }

    /** @return array{leer: bool, crear: bool, editar: bool, eliminar: bool, descargar: bool, puede_consultar: bool, puede_agregar: bool, puede_editar: bool, puede_eliminar: bool, puede_descargar: bool} */
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
        ];
    }

    public static function puede(string $accion): bool
    {
        return !empty(self::getPermisos()[$accion]);
    }

    /* ------------------------------------------------------------------ */
    /* Listado de tickets                                                  */
    /* ------------------------------------------------------------------ */

    /**
     * Tickets del usuario actual (o filtrados por estado).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listarTickets(?int $estado = null): array
    {
        $idUsuario = self::idUsuario();

        $query = Soporte::query()
            ->where('id_personal', $idUsuario)
            ->orderBy('fecha_termino_real', 'asc');

        if ($estado !== null && $estado !== self::ESTADO_TODOS) {
            $query->where('estado', $estado);
        }

        $tickets = $query->get();

        // Un solo GROUP BY en lugar de un COUNT por fila.
        $conteos = self::contarComentarios($tickets->pluck('id_ticket')->all());

        return $tickets
            ->map(fn(Soporte $ticket) => self::mapearTicket($ticket, $conteos[(int)$ticket->id_ticket] ?? 0))
            ->all();
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

    /**
     * Filas para el DataTable (patrón Portal3). Idéntico a listarTickets
     * pero con el formato de nombres que consume la tabla.
     */
    public static function getData(?int $estado = null): array
    {
        return self::listarTickets($estado);
    }

    /** Detalle del ticket listo para JSON (ver/editar). */
    public static function show(int $idTicket): array
    {
        $detalle = self::detalleTicket($idTicket);

        if ($detalle === null) {
            return ['success' => false, 'message' => 'Ticket no encontrado.'];
        }

        return ['success' => true, 'data' => $detalle];
    }

    /** Comentarios del ticket listos para JSON (modal Alpine). */
    public static function comentariosShow(int $idTicket): array
    {
        $contenido = self::comentarios($idTicket);

        if ($contenido === null) {
            return ['success' => false, 'message' => 'Ticket no encontrado.'];
        }

        return ['success' => true, 'data' => $contenido];
    }

    /** Actividades del ticket listas para JSON (modal Alpine). */
    public static function actividadesShow(int $idTicket): array
    {
        if (!self::ticketPropio($idTicket)) {
            return ['success' => false, 'message' => 'Ticket no encontrado.'];
        }

        return ['success' => true, 'data' => self::listarActividades($idTicket)];
    }

    /** Detalle completo de un ticket del usuario. */
    public static function detalleTicket(int $idTicket): ?array
    {
        $ticket = self::ticketPropio($idTicket);

        if (!$ticket) {
            return null;
        }

        return self::detalleDeTicket($ticket);
    }

    /**
     * Detalle completo de un ticket ya resuelto. Es la base que comparten
     * Soporte (Sistemas) y Soporte (Estaciones): el segundo módulo valida
     * el contexto antes de llamar a este método.
     */
    public static function detalleDeTicket(Soporte $ticket): array
    {
        $idTicket = (int)$ticket->id_ticket;

        $actividades = SoporteActividad::where('id_ticket', $idTicket)
            ->orderBy('id')
            ->get()
            ->map(fn(SoporteActividad $a) => [
                'id' => (int)$a->id,
                'descripcion' => (string)$a->descripcion,
                'fecha_inicio' => self::formatearFechaActividad($a->fecha_inicio),
                'fecha_termino' => self::formatearFechaActividad($a->fecha_termino),
                'fecha_inicio_iso' => self::isoFecha($a->fecha_inicio),
                'fecha_termino_iso' => self::isoFecha($a->fecha_termino),
                'estado' => self::textoEstadoActividad((int)$a->estado),
                'estado_valor' => (int)$a->estado,
                'archivo' => (string)$a->archivo,
                'url' => self::urlDescarga(self::TIPO_DESCARGA_ACTIVIDAD, (string)$a->archivo),
            ])
            ->all();

        $evidencias = SoporteEvidencia::where('id_ticket', $idTicket)
            ->orderBy('id')
            ->get()
            ->map(fn(SoporteEvidencia $e) => [
                'id' => (int)$e->id,
                'descripcion' => (string)$e->descripcion,
                'archivo' => (string)$e->evidencia,
                'url' => self::urlDescarga(self::TIPO_DESCARGA_EVIDENCIA, (string)$e->evidencia),
            ])
            ->all();

        return array_merge(self::mapearTicket($ticket), [
            'actividades' => $actividades,
            'evidencias' => $evidencias,
            'puede_finalizar_soporte' => self::puedeFinalizarSoporte($ticket),
            // Sólo el solicitante puede validar el trabajo de Sistemas.
            'puede_validar' => self::puedeValidarSolicitud($ticket)
                && (int)$ticket->id_personal === self::idUsuario(),
            'dias_validacion' => self::DIAS_VALIDACION,
        ]);
    }

    /** Listado de actividades de un ticket propio. */
    public static function listarActividades(int $idTicket): array
    {
        if (!self::ticketPropio($idTicket)) {
            return [];
        }

        return SoporteActividad::where('id_ticket', $idTicket)
            ->orderBy('id')
            ->get()
            ->map(fn(SoporteActividad $a) => [
                'id' => (int)$a->id,
                'descripcion' => (string)$a->descripcion,
                'archivo' => (string)$a->archivo,
                'url' => self::urlDescarga(self::TIPO_DESCARGA_ACTIVIDAD, (string)$a->archivo),
            ])
            ->all();
    }

    /* ------------------------------------------------------------------ */
    /* Comentarios                                                         */
    /* ------------------------------------------------------------------ */

    /** Comentarios de un ticket propio, más el bloqueo de cierre. */
    public static function comentarios(int $idTicket): ?array
    {
        $ticket = self::ticketPropio($idTicket);

        if (!$ticket) {
            return null;
        }

        return self::comentariosDeTicket($ticket);
    }

    /**
     * Comentarios de un ticket ya resuelto. Compartido con Soporte
     * (Estaciones), que valida el contexto antes de invocarlo.
     */
    public static function comentariosDeTicket(Soporte $ticket): array
    {
        $idTicket = (int)$ticket->id_ticket;

        $comentarios = SoporteComentario::where('id_ticket', $idTicket)
            ->orderBy('id', 'desc')
            ->get()
            ->map(function (SoporteComentario $c) {
                $idPersonal = (int)$c->id_personal;

                return [
                    'id' => (int)$c->id,
                    'comentario' => (string)$c->comentario,
                    'id_personal' => $idPersonal,
                    'nombre' => self::nombreUsuario($idPersonal),
                    'es_propio' => $idPersonal === self::idUsuario(),
                    'fecha' => self::formatearFechaHora($c->fecha_hora),
                ];
            })
            ->all();

        return [
            'ticket' => [
                'id_ticket' => $idTicket,
                'descripcion' => (string)$ticket->descripcion,
                'prioridad' => (string)$ticket->prioridad,
                'porcentaje' => (int)$ticket->porcentaje,
            ],
            'comentarios' => $comentarios,
            'puede_comentar' => self::puedeComentar($ticket),
        ];
    }

    /**
     * Guarda un comentario. $opcion = 1 cuando lo escribe el personal de
     * sistemas (notifica al área), 2 cuando lo escribe el solicitante
     * (notifica al solicitante). Se conserva la opción heredada.
     *
     * @param bool $notificar Permite silenciar la notificación de Telegram
     *                       (comentarios internos generados por el sistema).
     */
    public static function guardarComentario(int $idTicket, string $comentario, int $opcion = 1, bool $notificar = true): array
    {
        if (!self::puede('editar') && !self::puede('leer')) {
            return ['success' => false, 'message' => 'No tienes permiso para comentar.'];
        }

        $ticket = self::ticketPropio($idTicket);

        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket no encontrado.'];
        }

        $comentario = trim($comentario);

        if ($comentario === '') {
            return ['success' => false, 'message' => 'El comentario es obligatorio.'];
        }

        /*
        if (!self::puedeComentar($ticket)) {
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

        if ($notificar) {
            self::notificarComentario($ticket, $comentario);
        }

        return ['success' => true, 'message' => 'Comentario guardado.'];
    }

    /* ------------------------------------------------------------------ */
    /* Ticket: crear y editar                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Crea un folio (ticket en estado Creando). Devuelve el id_ticket.
     * Equivale a Sistemas::NuevoTicket().
     */
    public static function nuevoTicket(string $categoria = self::CATEGORIA_ACTIVIDAD): array
    {
        if (!self::puede('crear')) {
            return ['success' => false, 'message' => 'No tienes permiso para crear tickets.'];
        }

        $categoria = self::normalizarCategoria($categoria);

        $idTicket = self::siguienteFolio();

        try {
            Capsule::table('ds_soporte')->insert([
                'id_ticket' => $idTicket,
                'id_personal' => self::idUsuario(),
                'descripcion' => '',
                'prioridad' => '',
                'categoria' => $categoria,
                'id_personal_soporte' => 0,
                'porcentaje' => 0,
                'estado' => self::ESTADO_CREANDO,
                'fecha_creacion' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error al crear el ticket.'];
        }

        return ['success' => true, 'message' => 'Ticket creado.', 'id_ticket' => $idTicket];
    }

    /** Edita la prioridad de un ticket en estado Creando. */
    public static function editarPrioridad(int $idTicket, string $prioridad): array
    {
        if (!self::puede('editar')) {
            return ['success' => false, 'message' => 'No tienes permiso para editar.'];
        }

        if (!in_array($prioridad, ['Baja', 'Media', 'Alta'], true)) {
            return ['success' => false, 'message' => 'La prioridad no es válida.'];
        }

        $ticket = self::ticketPropio($idTicket, self::ESTADO_CREANDO);

        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket no encontrado.'];
        }

        $ticket->prioridad = $prioridad;
        $ticket->save();

        return ['success' => true, 'message' => 'Prioridad actualizada.'];
    }

    /** Edita la descripción de un ticket en estado Creando. */
    public static function editarDescripcion(int $idTicket, string $descripcion): array
    {
        if (!self::puede('editar')) {
            return ['success' => false, 'message' => 'No tienes permiso para editar.'];
        }

        $ticket = self::ticketPropio($idTicket, self::ESTADO_CREANDO);

        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket no encontrado.'];
        }

        $ticket->descripcion = trim($descripcion);
        $ticket->save();

        return ['success' => true, 'message' => 'Descripción actualizada.'];
    }

    /**
     * Finaliza el registro (envía el ticket). Equivale a
     * Sistemas::FinalizarRegistro(): fecha_creacion = ahora, estado = 1 y
     * notifica al área de sistemas.
     */
    public static function finalizarRegistro(int $idTicket): array
    {
        if (!self::puede('crear')) {
            return ['success' => false, 'message' => 'No tienes permiso para enviar el ticket.'];
        }

        $ticket = self::ticketPropio($idTicket, self::ESTADO_CREANDO);

        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket no encontrado.'];
        }

        if (trim((string)$ticket->descripcion) === '') {
            return ['success' => false, 'message' => 'La descripción es obligatoria.'];
        }

        if (!in_array((string)$ticket->prioridad, ['Baja', 'Media', 'Alta'], true)) {
            return ['success' => false, 'message' => 'Selecciona una prioridad.'];
        }

        $ticket->fecha_creacion = date('Y-m-d H:i:s');
        $ticket->estado = self::ESTADO_PENDIENTE;
        $ticket->save();

        self::notificarTicketEnviado($ticket);

        return ['success' => true, 'message' => 'Registro enviado.'];
    }

    /** Cancela un ticket. Equivale a Sistemas::CancelarTicket(). */
    public static function cancelarTicket(int $idTicket): array
    {
        if (!self::puede('eliminar')) {
            return ['success' => false, 'message' => 'No tienes permiso para cancelar.'];
        }

        $ticket = self::ticketPropio($idTicket);

        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket no encontrado.'];
        }

        $ticket->estado = self::ESTADO_CANCELADO;
        $ticket->save();

        return ['success' => true, 'message' => 'Ticket cancelado.'];
    }

    /**
     * Actualiza campos del ticket. Replica Sistemas::EditarRegistro()
     * opciones: 1 estado, 2 fecha_inicio, 3 fecha_termino (recalcula
     * tiempo_solucion con DATEDIFF), 4 id_personal_soporte, 5 porcentaje.
     */
    public static function editarRegistro(int $idTicket, string $detalle, int $opcion): array
    {
        if (!self::puede('editar')) {
            return ['success' => false, 'message' => 'No tienes permiso para editar.'];
        }

        $ticket = self::ticketPropio($idTicket);

        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket no encontrado.'];
        }

        switch ($opcion) {
            case 1:
                $ticket->estado = (int)$detalle;
                break;

            case 2:
                $ticket->fecha_inicio = $detalle . ' ' . date('H:i:s');
                break;

            case 3:
                $ticket->fecha_termino = $detalle . ' ' . date('H:i:s');
                break;

            case 4:
                $ticket->id_personal_soporte = (int)$detalle;
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

    /**
     * Finaliza el soporte. Equivale a Sistemas::FinalizarSoporte(): estado
     * final depende del origen (2 = visto bueno, 3 = finalizado), marca las
     * actividades como finalizadas y responde por Telegram.
     */
    public static function finalizarSoporte(int $idTicket, int $finalizar): array
    {
        if (!self::puede('editar')) {
            return ['success' => false, 'message' => 'No tienes permiso para finalizar el soporte.'];
        }

        $ticket = self::ticketPropio($idTicket);

        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket no encontrado.'];
        }

        $estado = (int)$ticket->estado;

        if ($estado !== self::ESTADO_PENDIENTE && $estado !== self::ESTADO_EN_PROCESO) {
            return ['success' => false, 'message' => 'El ticket no está en un estado que permita finalizarlo.'];
        }

        $ticket->fecha_termino_real = date('Y-m-d H:i:s');
        $ticket->porcentaje = 100;
        $ticket->id_personal_soporte = self::idUsuario();
        $ticket->estado = $finalizar;
        $ticket->save();

        SoporteActividad::where('id_ticket', $idTicket)
            ->update(['estado' => self::ACTIVIDAD_FINALIZADA]);

        self::notificarSoporteFinalizado($idTicket, $finalizar);

        if ($finalizar === self::ESTADO_EN_PROCESO) {
            // Comentario interno: la notificación la emite
            // notificarSoporteFinalizado(), no se duplica.
            self::guardarComentario(
                $idTicket,
                'Se finalizó el soporte por el área de sistemas, recuerda que tienes tres días para darle seguimiento antes de que se cierre el ticket.',
                2,
                false
            );
        }

        return ['success' => true, 'message' => 'Soporte finalizado.'];
    }

    /* ------------------------------------------------------------------ */
    /* Actividades                                                         */
    /* ------------------------------------------------------------------ */

    /** Agrega una actividad (con archivo opcional). */
    public static function agregarActividad(int $idTicket, string $descripcion, ?array $archivo): array
    {
        if (!self::puede('editar')) {
            return ['success' => false, 'message' => 'No tienes permiso para agregar actividades.'];
        }

        if (!self::ticketPropio($idTicket, self::ESTADO_CREANDO)) {
            return ['success' => false, 'message' => 'Ticket no encontrado.'];
        }

        $descripcion = trim($descripcion);

        if ($descripcion === '') {
            return ['success' => false, 'message' => 'La descripción es obligatoria.'];
        }

        $nombreArchivo = '';

        if (self::tieneArchivo($archivo)) {
            $guardado = self::guardarArchivo($archivo, 'Actividad', self::UPLOAD_DIR_ACTIVIDADES);

            if (!$guardado['success']) {
                return $guardado;
            }

            $nombreArchivo = $guardado['archivo'];
        }

        try {
            SoporteActividad::create([
                'id_ticket' => $idTicket,
                'descripcion' => $descripcion,
                'archivo' => $nombreArchivo,
                'estado' => self::ACTIVIDAD_PENDIENTE,
            ]);
        } catch (\Throwable $e) {
            if ($nombreArchivo !== '') {
                @unlink(self::uploadDir(self::UPLOAD_DIR_ACTIVIDADES) . $nombreArchivo);
            }

            return ['success' => false, 'message' => 'Error al agregar la actividad.'];
        }

        return ['success' => true, 'message' => 'Actividad agregada.'];
    }

    /** Elimina una actividad propia. */
    public static function eliminarActividad(int $idActividad): array
    {
        if (!self::puede('editar')) {
            return ['success' => false, 'message' => 'No tienes permiso para eliminar actividades.'];
        }

        $actividad = SoporteActividad::find($idActividad);

        if (!$actividad || !self::ticketPropio((int)$actividad->id_ticket)) {
            return ['success' => false, 'message' => 'Actividad no encontrada.'];
        }

        $archivo = (string)$actividad->archivo;

        $actividad->delete();

        if ($archivo !== '') {
            @unlink(self::uploadDir(self::UPLOAD_DIR_ACTIVIDADES) . $archivo);
        }

        return ['success' => true, 'message' => 'Actividad eliminada.'];
    }

    /** Edita una actividad. Opciones: 1 fecha_inicio, 2 fecha_termino, 3 estado. */
    public static function editarActividad(int $idActividad, string $detalle, int $opcion): array
    {
        if (!self::puede('editar')) {
            return ['success' => false, 'message' => 'No tienes permiso para editar actividades.'];
        }

        $actividad = SoporteActividad::find($idActividad);

        if (!$actividad || !self::ticketPropio((int)$actividad->id_ticket)) {
            return ['success' => false, 'message' => 'Actividad no encontrada.'];
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

        return ['success' => true, 'message' => 'Actividad actualizada.'];
    }

    /* ------------------------------------------------------------------ */
    /* Evidencias                                                          */
    /* ------------------------------------------------------------------ */

    /** Agrega una evidencia (con archivo obligatorio). */
    public static function agregarEvidencia(int $idTicket, string $descripcion, ?array $archivo): array
    {
        if (!self::puede('editar')) {
            return ['success' => false, 'message' => 'No tienes permiso para agregar evidencias.'];
        }

        if (!self::ticketPropio($idTicket, self::ESTADO_CREANDO)) {
            return ['success' => false, 'message' => 'Ticket no encontrado.'];
        }

        if (!self::tieneArchivo($archivo)) {
            return ['success' => false, 'message' => 'El archivo es obligatorio.'];
        }

        $guardado = self::guardarArchivo($archivo, 'Evidencia', self::UPLOAD_DIR_EVIDENCIAS);

        if (!$guardado['success']) {
            return $guardado;
        }

        try {
            SoporteEvidencia::create([
                'id_ticket' => $idTicket,
                'descripcion' => trim($descripcion),
                'evidencia' => $guardado['archivo'],
            ]);
        } catch (\Throwable $e) {
            @unlink(self::uploadDir(self::UPLOAD_DIR_EVIDENCIAS) . $guardado['archivo']);

            return ['success' => false, 'message' => 'Error al agregar la evidencia.'];
        }

        return ['success' => true, 'message' => 'Evidencia agregada.'];
    }

    /** Elimina una evidencia propia. */
    public static function eliminarEvidencia(int $idEvidencia): array
    {
        if (!self::puede('editar')) {
            return ['success' => false, 'message' => 'No tienes permiso para eliminar evidencias.'];
        }

        $evidencia = SoporteEvidencia::find($idEvidencia);

        if (!$evidencia || !self::ticketPropio((int)$evidencia->id_ticket)) {
            return ['success' => false, 'message' => 'Evidencia no encontrada.'];
        }

        $archivo = (string)$evidencia->evidencia;

        $evidencia->delete();

        if ($archivo !== '') {
            @unlink(self::uploadDir(self::UPLOAD_DIR_EVIDENCIAS) . $archivo);
        }

        return ['success' => true, 'message' => 'Evidencia eliminada.'];
    }

    /* ------------------------------------------------------------------ */
    /* Archivos                                                            */
    /* ------------------------------------------------------------------ */

    private static function tieneArchivo(?array $archivo): bool
    {
        return is_array($archivo)
            && isset($archivo['tmp_name'])
            && (int)($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * Guarda el archivo recibido con el nombre generado que usa el sistema
     * heredado: <prefijo>YYYYMMDD-<id>.<ext>.
     *
     * @return array{success: bool, message: string, archivo?: string}
     */
    private static function guardarArchivo(array $archivo, string $prefijo, string $dir): array
    {
        if ((int)($archivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Error al recibir el archivo.'];
        }

        $nombreOriginal = (string)($archivo['name'] ?? '');
        $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

        if ($extension === '' || !in_array($extension, self::EXTENSIONES_PERMITIDAS, true)) {
            return ['success' => false, 'message' => 'El tipo de archivo no está permitido.'];
        }

        if ((int)($archivo['size'] ?? 0) <= 0) {
            return ['success' => false, 'message' => 'El archivo está vacío.'];
        }

        $nombre = $prefijo . date('Ymd') . '-' . uniqid() . '.' . $extension;

        $destino = self::uploadDir($dir);

        if (!is_dir($destino)) {
            @mkdir($destino, 0775, true);
        }

        if (!move_uploaded_file((string)$archivo['tmp_name'], $destino . $nombre)) {
            return ['success' => false, 'message' => 'No se pudo guardar el archivo.'];
        }

        return ['success' => true, 'message' => '', 'archivo' => $nombre];
    }

    private static function uploadDir(string $dir): string
    {
        return dirname(__DIR__, 2) . '/' . $dir;
    }

    private static function urlDescarga(string $tipo, string $archivo): string
    {
        if ($archivo === '') {
            return '';
        }

        return '/download?tipo=' . $tipo . '&file=' . rawurlencode($archivo);
    }

    /* ------------------------------------------------------------------ */
    /* Notificaciones (Telegram)                                           */
    /* ------------------------------------------------------------------ */

    /**
     * El ticket fue enviado por el solicitante (estado Pendiente).
     * Equivale a Sistemas::FinalizarRegistro(), que en el sistema heredado
     * notificaba al usuario 496. Se mantiene ese destinatario y se amplía al
     * personal con puesto "Departamento Sistemas" (25).
     */
    private static function notificarTicketEnviado(Soporte $ticket): void
    {
        $idTicket = (int)$ticket->id_ticket;
        $solicitante = self::nombreUsuario((int)$ticket->id_personal);
        $area = self::areaUsuario((int)$ticket->id_personal);

        $mensaje =
            '🆘 Se ha <b>registrado</b> un nuevo <b>' . self::nombreModulo() . '</b>:' . PHP_EOL . PHP_EOL
            . self::lineaTelegram('🎫', 'No. de Ticket', self::folio($idTicket))
            . self::lineaTelegram('🗓️', 'Fecha de registro', formatearFecha($ticket->fecha_creacion))
            . self::lineaTelegram('⚡', 'Prioridad', (string)$ticket->prioridad)
            . self::lineaTelegram('🗒', 'Descripción', (string)$ticket->descripcion)
            . self::lineaTelegram('👤', 'Solicitante', $solicitante)
            . self::lineaTelegram($area['etiqueta'] === 'Departamento' ? '🏢' : '⛽', $area['etiqueta'], $area['valor']);

        self::enviarTelegram(self::destinatariosSistemas(), $mensaje);
    }

    /**
     * Se registró un comentario en el ticket. Si lo escribió el solicitante
     * se avisa al área de Sistemas; si lo escribió el personal de Sistemas
     * se avisa al solicitante del ticket (comportamiento heredado).
     */
    public static function notificarComentario(Soporte $ticket, string $comentario): void
    {
        $idTicket = (int)$ticket->id_ticket;
        $idAutor = self::idUsuario();
        $solicitante = (int)$ticket->id_personal;

        $mensaje =
            '💬 Se ha <b>registrado</b> un nuevo <b>comentario</b> en el <b>' . self::nombreModulo() . '</b>:' . PHP_EOL . PHP_EOL
            . self::lineaTelegram('🎫', 'No. de Ticket', self::folio($idTicket))
            . self::lineaTelegram('🗒', 'Descripción', (string)$ticket->descripcion)
            . self::lineaTelegram('💬', 'Comentario', $comentario)
            . self::lineaTelegram('👤', 'Autor', self::nombreUsuario($idAutor))
            . self::lineaTelegram('🗓️', 'Fecha', formatearFecha(date('Y-m-d H:i:s')));

        if (self::esPersonalSistemas($idAutor)) {
            // El personal de Sistemas comenta: se avisa al solicitante.
            self::enviarTelegram([$solicitante], $mensaje);
        } else {
            // El solicitante comenta: se avisa al área de Sistemas.
            self::enviarTelegram(self::destinatariosSistemas(), $mensaje);
        }
    }

    /**
     * El soporte quedó finalizado. Avisa al solicitante y al área de
     * Sistemas (usuario 496 en el sistema heredado).
     */
    public static function notificarSoporteFinalizado(int $idTicket, int $finalizar): void
    {
        $ticket = Soporte::find($idTicket);

        if (!$ticket) {
            return;
        }

        $atendio = self::nombreUsuario(self::idUsuario());

        if ($finalizar === self::ESTADO_EN_PROCESO) {
            // El área de Sistemas terminó el trabajo: el solicitante tiene
            // 3 días para validarlo; si no lo hace, se cierra solo.
            $mensaje =
                '⏳ El <b>' . self::nombreModulo() . '</b> está listo para <b>tu validación</b>:' . PHP_EOL . PHP_EOL
                . self::lineaTelegram('🎫', 'No. de Ticket', self::folio($idTicket))
                . self::lineaTelegram('🗒', 'Descripción', (string)$ticket->descripcion)
                . self::lineaTelegram('🧑‍💻', 'Atendió', $atendio)
                . self::lineaTelegram('🗓️', 'Fecha de término', formatearFecha(date('Y-m-d H:i:s')))
                . PHP_EOL . 'Favor de revisar la actividad y validarla en <b>' . self::nombreModulo() . '</b>.'
                . PHP_EOL . 'Tienes <b>' . self::DIAS_VALIDACION . ' días</b> para confirmar;'
                . ' si no lo haces, la solicitud se finalizará de forma automática.';
        } else {
            $mensaje =
                '✅ Se ha <b>finalizado</b> el <b>' . self::nombreModulo() . '</b>:' . PHP_EOL . PHP_EOL
                . self::lineaTelegram('🎫', 'No. de Ticket', self::folio($idTicket))
                . self::lineaTelegram('🗒', 'Descripción', (string)$ticket->descripcion)
                . self::lineaTelegram('📊', 'Porcentaje', (int)$ticket->porcentaje . ' %')
                . self::lineaTelegram('👤', 'Atendió', $atendio)
                . self::lineaTelegram('🗓️', 'Fecha de término', formatearFecha($ticket->fecha_termino_real));
        }

        self::enviarTelegram(
            array_merge([(int)$ticket->id_personal], self::destinatariosSistemas()),
            $mensaje
        );
    }

    /**
     * Destinatarios del área de Sistemas: personal con puesto
     * "Departamento Sistemas" (25) más el usuario 496, que es el
     * destinatario principal heredado del sistema anterior.
     *
     * @return array<int, int>
     */
    public static function destinatariosSistemas(): array
    {
        $destinatarios = [];

        try {
            $telegram = new TelegramService();

            foreach ($telegram->getUserIdsByPuesto(self::PUESTO_SISTEMAS, 0) as $idUsuario) {
                $destinatarios[] = (int)$idUsuario;
            }
        } catch (\Throwable $e) {
            error_log('SoporteService::destinatariosSistemas: ' . $e->getMessage());
        }

        // El usuario 496 es el destinatario principal del sistema heredado.
        $destinatarios[] = self::USUARIO_SISTEMAS;

        return array_values(array_unique(array_filter($destinatarios)));
    }

    /**
     * Avisa por Telegram al responsable al que se le acaba de asignar una
     * actividad (heredado de Sistemas::asignarPersonal).
     */
        public static function mensajeAsignacionResponsable(Soporte $ticket, int $idResponsable): string
    {
        $idTicket = (int)$ticket->id_ticket;
        $nombre = self::nombreUsuario($idResponsable);
        $area = self::areaUsuario((int)$ticket->id_personal);

        $mensaje =
            'Hola <b>' . self::escTelegram($nombre) . '</b>,' . PHP_EOL . PHP_EOL
            . 'Se te ha asignado la siguiente actividad para darle seguimiento:' . PHP_EOL . PHP_EOL
            . self::lineaTelegram('🎫', 'No. de Ticket', self::folio($idTicket))
            . self::lineaTelegram('🗓️', 'Fecha de registro', formatearFecha($ticket->fecha_creacion))
            . self::lineaTelegram('⚡', 'Prioridad', (string)$ticket->prioridad)
            . self::lineaTelegram('🗒', 'Descripción', (string)$ticket->descripcion)
            . self::lineaTelegram('👤', 'Solicitante', self::nombreUsuario((int)$ticket->id_personal))
            . self::lineaTelegram($area['etiqueta'] === 'Departamento' ? '🏢' : '⛽', $area['etiqueta'], $area['valor'])
            . PHP_EOL . 'Puedes dar seguimiento en <b>' . self::nombreModulo() . '</b>.';

        return $mensaje;
    }

    /**
     * Envía al responsable el aviso de la asignación (sólo a él).
     */
    public static function notificarAsignacionResponsable(Soporte $ticket, int $idResponsable): void
    {
        self::enviarTelegram([$idResponsable], self::mensajeAsignacionResponsable($ticket, $idResponsable));
    }
    /**
     * Envía el mensaje a los destinatarios indicados. El usuario 496 se
     * envía de forma individual (destinatatario principal) y el resto en
     * paralelo, tal como lo hace SolicitudChequeService.
     *
     * El envío se registra como función de cierre para no retrasar la
     * respuesta al usuario: primero se libera la respuesta y después se
     * notifica por Telegram (mismo patrón que AceitesController y
     * SolicitudChequeService).
     *
     * @param array<int, int> $userIds
     */
    public static function enviarTelegram(array $userIds, string $mensaje): void
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));

        if (empty($userIds)) {
            return;
        }

        $principal = self::USUARIO_SISTEMAS;
        $resto = array_values(array_diff($userIds, [$principal]));
        $enviarPrincipal = in_array($principal, $userIds, true);

        register_shutdown_function(function () use ($resto, $enviarPrincipal, $principal, $mensaje) {
            // Se libera la respuesta al cliente antes de notificar por Telegram.
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }

            @flush();

            try {
                $telegram = new TelegramService();

                if (!empty($resto)) {
                    $telegram->sendMessageToMultiple($resto, $mensaje);
                }

                if ($enviarPrincipal) {
                    $telegram->sendToken($principal, $mensaje);
                }
            } catch (\Throwable $e) {
                // La notificación no debe bloquear el flujo del ticket.
                error_log('SoporteService::enviarTelegram: ' . $e->getMessage());
            }
        });
    }

    /** Indica si el usuario pertenece al área de Sistemas. */
    private static function esPersonalSistemas(int $idUsuario): bool
    {
        if ($idUsuario <= 0) {
            return false;
        }

        if ($idUsuario === self::USUARIO_SISTEMAS) {
            return true;
        }

        $puesto = (int)(Usuario::where('id', $idUsuario)->value('id_puesto') ?? 0);

        return in_array($puesto, [self::PUESTO_SISTEMAS, 2], true);
    }

    /** Línea del mensaje de Telegram: "🔹 <b>Etiqueta:</b> valor". */
    public static function lineaTelegram(string $icono, string $etiqueta, string $valor): string
    {
        return $icono . ' <b>' . $etiqueta . ':</b> ' . self::escTelegram($valor) . PHP_EOL;
    }

    /** Escapa el texto para el parse_mode HTML de Telegram. */
    public static function escTelegram(string $texto): string
    {
        return htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Folio con el formato heredado (0 + id_ticket). */
    public static function folio(int $idTicket): string
    {
        return '0' . $idTicket;
    }

    /**
     * Ubicación del usuario para el mensaje de Telegram. Cuando la estación
     * es "Comodines" (id 8) se devuelve el nombre del puesto con la etiqueta
     * "Departamento"; en cualquier otra estación se usa la etiqueta
     * "Estación" con el nombre de la estación.
     *
     * @return array{etiqueta: string, valor: string}
     */
    public static function areaUsuario(int $idUsuario): array
    {
        $idGas = (int)(Usuario::where('id', $idUsuario)->value('id_gas') ?? 0);
        $idPuesto = (int)(Usuario::where('id', $idUsuario)->value('id_puesto') ?? 0);

        if ($idGas === 8) {
            $puesto = \App\Models\Puestos::find($idPuesto);

            return [
                'etiqueta' => 'Departamento',
                'valor' => $puesto ? (string)$puesto->tipo_puesto : 'Sin departamento asignado',
            ];
        }

        if ($idGas <= 0) {
            return ['etiqueta' => 'Estación', 'valor' => 'Sin estación asignada'];
        }

        $estacion = \App\Models\Estacion::find($idGas);

        return [
            'etiqueta' => 'Estación',
            'valor' => $estacion ? (string)$estacion->nombre : 'Sin estación asignada',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Validación del solicitante (3 días)                                */
    /* ------------------------------------------------------------------ */

    /** Días que tiene el solicitante para validar el trabajo de Sistemas. */
    public const DIAS_VALIDACION = 3;

    /**
     * El solicitante puede validar cuando el área de Sistemas terminó el
     * trabajo (estado "En proceso" = 2) y está dentro de la ventana de
     * 3 días contados desde la fecha de término real.
     */
    public static function puedeValidarSolicitud(Soporte $ticket): bool
    {
        if ((int)$ticket->estado !== self::ESTADO_EN_PROCESO) {
            return false;
        }

        // El estado 2 también significa "trabajo en curso": sólo está
        // esperando validación cuando el área de Sistemas ya terminó
        // (fecha de término real registrada).
        if (self::isoFecha($ticket->fecha_termino_real) === '') {
            return false;
        }

        return self::ventanaValidacionAbierta($ticket);
    }

    /**
     * Ventana de validación abierta: fecha_termino_real + 3 días >= hoy.
     */
    public static function ventanaValidacionAbierta(Soporte $ticket): bool
    {
        $fechaTermino = self::isoFecha($ticket->fecha_termino_real);

        if ($fechaTermino === '') {
            return false;
        }

        $cierre = strtotime($fechaTermino . ' + ' . self::DIAS_VALIDACION . ' days');

        return $cierre >= strtotime(date('Y-m-d'));
    }

    /**
     * Valida (acepta) el trabajo realizado y cierra el ticket.
     * Es la acción que realiza el solicitante dentro de los 3 días.
     */
    public static function validarSolicitud(int $idTicket): array
    {
        $ticket = self::ticketPropio($idTicket);

        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket no encontrado.'];
        }

        if (!self::puedeValidarSolicitud($ticket)) {
            return ['success' => false, 'message' => 'El ticket no está esperando validación.'];
        }

        $ticket->estado = self::ESTADO_FINALIZADO;
        $ticket->porcentaje = 100;
        $ticket->save();

        self::notificarSolicitudValidada($ticket, false);

        return ['success' => true, 'message' => 'Solicitud validada y finalizada.'];
    }

    /**
     * Cierra automáticamente los tickets que el solicitante no validó
     * dentro de los 3 días.
     *
     * Se ejecuta al cargar los listados (mismo patrón "lazy" que usaba el
     * sistema anterior con Home::actualizarPorcentajeActividades).
     *
     * @return int Número de tickets cerrados.
     */
    public static function procesarValidacionesVencidas(): int
    {
        try {
            $vencidos = Soporte::where('estado', self::ESTADO_EN_PROCESO)
                ->whereNotNull('fecha_termino_real')
                ->where('fecha_termino_real', '<>', '0000-00-00 00:00:00')
                ->whereRaw('DATEDIFF(CURDATE(), DATE(fecha_termino_real)) > ?', [self::DIAS_VALIDACION])
                ->get();

            foreach ($vencidos as $ticket) {
                $ticket->estado = self::ESTADO_FINALIZADO;
                $ticket->porcentaje = 100;
                $ticket->save();

                self::notificarSolicitudValidada($ticket, true);
            }

            return $vencidos->count();
        } catch (\Throwable $e) {
            error_log('SoporteService::procesarValidacionesVencidas: ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * Notifica el cierre del ticket (validado por el solicitante o
     * automatico por vencimiento de los 3 días).
     */
    public static function notificarSolicitudValidada(Soporte $ticket, bool $automatico): void
    {
        $idTicket = (int)$ticket->id_ticket;
        $solicitante = self::nombreUsuario((int)$ticket->id_personal);
        $responsable = (int)$ticket->id_personal_soporte;

        $encabezado = $automatico
            ? '⏰ Se ha <b>finalizado automáticamente</b> el <b>' . self::nombreModulo() . '</b>:'
            : '✅ Se ha <b>validado</b> el <b>' . self::nombreModulo() . '</b>:';

        $detalle = $automatico
            ? 'No se recibió la validación del solicitante dentro de los ' . self::DIAS_VALIDACION . ' días.'
            : 'El solicitante confirmó que la actividad fue realizada.';

        $mensaje =
            $encabezado . PHP_EOL . PHP_EOL
            . self::lineaTelegram('🎫', 'No. de Ticket', self::folio($idTicket))
            . self::lineaTelegram('🗒', 'Descripción', (string)$ticket->descripcion)
            . self::lineaTelegram('👤', 'Solicitante', $solicitante)
            . self::lineaTelegram('🧑‍💻', 'Responsable', $responsable > 0 ? self::nombreUsuario($responsable) : 'Sistemas')
            . self::lineaTelegram('🗓️', 'Fecha de término', formatearFecha($ticket->fecha_termino_real))
            . PHP_EOL . $detalle;

        $destinatarios = [];

        if ($responsable > 0) {
            $destinatarios[] = $responsable;
        }

        $destinatarios[] = self::USUARIO_SISTEMAS;

        self::enviarTelegram($destinatarios, $mensaje);
    }

    /**
     * El solicitante devuelve el ticket al área de Sistemas porque el
     * resultado no es el esperado o la solicitud sigue inconclusa.
     *
     * El ticket vuelve a estado "Pendiente" para que el responsable continúe
     * con el seguimiento. Se registra un comentario en el ticket y se
     * notifica por Telegram al responsable.
     */
    public static function solicitarRevision(int $idTicket, string $motivo = ''): array
    {
        if (!self::puede('editar') && !self::puede('leer')) {
            return ['success' => false, 'message' => 'No tienes permiso para solicitar la revisión.'];
        }

        $ticket = self::ticketPropio($idTicket);

        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket no encontrado.'];
        }

        return self::registrarSolicitudRevision($ticket, $motivo);
    }

    /**
     * Registra la solicitud de revisión de un ticket ya validado por el
     * solicitante. Es la operación que comparten Soporte (Sistemas),
     * Soporte (Estaciones) y Actividades (Sistemas): cada módulo resuelve
     * primero su propio alcance y aquí se hace el cambio de estado, el
     * comentario y el aviso por Telegram.
     */
    public static function registrarSolicitudRevision(Soporte $ticket, string $motivo = ''): array
    {
        if (!self::puedeValidarSolicitud($ticket)) {
            return ['success' => false, 'message' => 'El ticket no está esperando validación.'];
        }

        $idTicket = (int)$ticket->id_ticket;

        $motivo = trim($motivo);

        if ($motivo === '') {
            $motivo = 'El resultado de la atención no es el esperado o la solicitud sigue inconclusa.';
        }

        // La solicitud vuelve a "Pendiente" para que el área de Sistemas
        // continúe con el seguimiento.
        $ticket->estado = self::ESTADO_PENDIENTE;
        $ticket->save();

        try {
            SoporteComentario::create([
                'id_ticket' => $idTicket,
                'id_personal' => self::idUsuario(),
                'comentario' => 'Solicitó la revisión de la solicitud: ' . $motivo,
            ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error al registrar la solicitud de revisión.'];
        }

        self::notificarSolicitudRevision($ticket, $motivo);

        return ['success' => true, 'message' => 'Solicitud de revisión registrada.'];
    }

    /**
     * Notifica al responsable que el solicitante pidió la revisión de la
     * solicitud.
     */
    public static function notificarSolicitudRevision(Soporte $ticket, string $motivo): void
    {
        $idTicket = (int)$ticket->id_ticket;
        $responsable = (int)$ticket->id_personal_soporte;

        $mensaje =
            '🔄 <b>El personal solicita la revisión</b> de su <b>' . self::nombreModulo() . '</b>:' . PHP_EOL . PHP_EOL
            . self::lineaTelegram('🎫', 'No. de Ticket', self::folio($idTicket))
            . self::lineaTelegram('🗒', 'Descripción', (string)$ticket->descripcion)
            . self::lineaTelegram('👤', 'Solicitante', self::nombreUsuario((int)$ticket->id_personal))
            . self::lineaTelegram('🧑‍💻', 'Responsable', $responsable > 0 ? self::nombreUsuario($responsable) : 'Sistemas')
            . self::lineaTelegram('🗓️', 'Fecha de término', formatearFecha($ticket->fecha_termino_real))
            . self::lineaTelegram('📝', 'Motivo', $motivo)
            . PHP_EOL . 'La solicitud vuelve a estado Pendiente para que el área de Sistemas continúe con el seguimiento.';

        $destinatarios = [];

        if ($responsable > 0) {
            $destinatarios[] = $responsable;
        }

        $destinatarios[] = self::USUARIO_SISTEMAS;

        self::enviarTelegram($destinatarios, $mensaje);
    }

    /**
     * Personal del área de Sistemas al que se puede asignar un ticket
     * (puesto 25 "Departamento Sistemas", usuarios activos). Es la misma
     * lista que usaba el sistema anterior.
     *
     * @return array<int, array{id: int, nombre: string}>
     */
    public static function getResponsables(): array
    {
        return Usuario::where('id_puesto', 25)
            ->where('estatus', 0)
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(fn(Usuario $u) => ['id' => (int)$u->id, 'nombre' => (string)$u->nombre])
            ->all();
    }

    /**
     * Calcula las fechas de inicio y término sugeridas usando días hábiles
     * (regla heredada de sistemas-editar-registro.php). La fecha de inicio
     * es mañana (saltando el fin de semana) y la de término se calcula
     * contando los días hábiles indicados.
     *
     * @return array{fecha_inicio: string, fecha_termino: string, dias_habiles: int}
     */
    public static function calcularFechasSugeridas(int $diasHabiles = 2): array
    {
        $diasHabiles = $diasHabiles > 0 ? $diasHabiles : 2;

        $fechaInicio = new \DateTime('today');
        $fechaInicio->modify('+1 day');

        // Si cae en sábado o domingo se recorre al lunes.
        if ((int)$fechaInicio->format('N') === 6) {
            $fechaInicio->modify('+2 days');
        } elseif ((int)$fechaInicio->format('N') === 7) {
            $fechaInicio->modify('+1 day');
        }

        $fechaFin = clone $fechaInicio;
        $contador = 0;

        while ($contador < $diasHabiles) {
            if ((int)$fechaFin->format('N') < 6) {
                $contador++;
            }

            $fechaFin->modify('+1 day');
        }

        return [
            'fecha_inicio' => $fechaInicio->format('Y-m-d'),
            'fecha_termino' => $fechaFin->format('Y-m-d'),
            'dias_habiles' => $diasHabiles,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Utilidades                                                          */
    /* ------------------------------------------------------------------ */

    private static function idUsuario(): int
    {
        return (int)(Session::get('usuario')['id'] ?? 0);
    }

    private static function siguienteFolio(): int
    {
        $ultimo = (int)Soporte::query()->max('id_ticket');

        return $ultimo + 1;
    }

    /** Ticket del usuario actual, opcionalmente restringido a un estado. */
    private static function ticketPropio(int $idTicket, ?int $estado = null): ?Soporte
    {
        $query = Soporte::query()
            ->where('id_ticket', $idTicket)
            ->where('id_personal', self::idUsuario());

        if ($estado !== null) {
            $query->where('estado', $estado);
        }

        return $query->first();
    }

    private static function puedeComentar(Soporte $ticket): bool
    {
        return self::puedeComentarTicket($ticket);
    }

    /**
     * Regla heredada: el ticket admite comentarios mientras no esté
     * finalizado/cancelado, o dentro de los 3 días siguientes al término.
     */
    public static function puedeComentarTicket(Soporte $ticket): bool
    {
        $estado = (int)$ticket->estado;

        // Mientras el ticket no esté finalizado/cancelado se puede comentar.
        if ($estado !== self::ESTADO_FINALIZADO && $estado !== self::ESTADO_CANCELADO) {
            return true;
        }

        $fechaTermino = (string)$ticket->fecha_termino_real;

        if ($fechaTermino === '' || str_starts_with($fechaTermino, '0000-00-00')) {
            return false;
        }

        $cierre = strtotime(date('Y-m-d', strtotime($fechaTermino . ' + 3 days')));

        return $cierre >= strtotime(date('Y-m-d'));
    }

    private static function puedeFinalizarSoporte(Soporte $ticket): bool
    {
        return (int)$ticket->estado === self::ESTADO_EN_PROCESO
            && (int)$ticket->porcentaje === 100;
    }

    /**
     * Días de solución del ticket.
     *
     * Se calcula con DATEDIFF (como en el sistema heredado) y no con la
     * diferencia de timestamps porque el formato global de Carbon no es ISO
     * y strtotime() no puede interpretarlo.
     */
    public static function diasHabiles(int $idTicket): int
    {
        $fila = Capsule::table('ds_soporte')
            ->where('id_ticket', $idTicket)
            ->selectRaw('DATEDIFF(fecha_termino, fecha_inicio) AS dias')
            ->first();

        return (int)($fila->dias ?? 0);
    }

    /** Nombre del usuario; si no existe devuelve "Sistemas". */
    public static function nombreUsuario(int $idUsuario): string
    {
        if ($idUsuario <= 0) {
            return 'Sistemas';
        }

        $nombre = Usuario::where('id', $idUsuario)->value('nombre');

        return $nombre !== null && $nombre !== '' ? (string)$nombre : 'Sistemas';
    }

    private static function normalizarCategoria(string $categoria): string
    {
        return $categoria === self::CATEGORIA_EVIDENCIA
            ? self::CATEGORIA_EVIDENCIA
            : self::CATEGORIA_ACTIVIDAD;
    }

    public static function textoEstado(int $estado): string
    {
        return match ($estado) {
            self::ESTADO_CREANDO => 'Creando',
            self::ESTADO_PENDIENTE => 'Pendiente',
            self::ESTADO_EN_PROCESO => 'En proceso',
            self::ESTADO_FINALIZADO => 'Finalizado',
            self::ESTADO_CANCELADO => 'Cancelado',
            default => '',
        };
    }

    public static function textoEstadoActividad(int $estado): string
    {
        return match ($estado) {
            self::ACTIVIDAD_PENDIENTE => 'Pendiente',
            self::ACTIVIDAD_EN_PROCESO => 'En proceso',
            self::ACTIVIDAD_FINALIZADA => 'Finalizada',
            default => '',
        };
    }

    public static function colorPrioridad(string $prioridad): string
    {
        return match ($prioridad) {
            'Baja' => 'text-primary',
            'Media' => 'text-warning',
            'Alta' => 'text-danger',
            default => '',
        };
    }

    public static function colorFila(int $estado): string
    {
        return match ($estado) {
            self::ESTADO_CREANDO => '',
            self::ESTADO_PENDIENTE => '',
            self::ESTADO_EN_PROCESO => '',
            self::ESTADO_FINALIZADO => '',
            self::ESTADO_CANCELADO => '',
            default => '',
        };
    }

    /**
     * Color de la fila según el estado real del ticket.
     *
     * Un ticket que espera la validación del solicitante se pinta en azul
     * (table-info) para que sea identificable de un vistazo.
     */
    public static function colorFilaDeTicket(Soporte $ticket): string
    {
        if (self::puedeValidarSolicitud($ticket)) {
            return '#fcfcda';
        }

        return self::colorFila((int)$ticket->estado);
    }


    private static function mapearTicket(Soporte $ticket, ?int $totalComentarios = null): array
    {
        return self::mapearTicketPublico($ticket, $totalComentarios);
    }

    /**
     * Mapeo de un ticket al formato de la interfaz. Compartido con
     * Soporte (Estaciones), que agrega los datos de contexto (estación,
     * departamento y solicitante).
     */
    public static function mapearTicketPublico(Soporte $ticket, ?int $totalComentarios = null): array
    {
        $estado = (int)$ticket->estado;
        $idTicket = (int)$ticket->id_ticket;

        if ($totalComentarios === null) {
            $totalComentarios = (int)SoporteComentario::where('id_ticket', $idTicket)->count();
        }

        $idPersonalSoporte = (int)$ticket->id_personal_soporte;

        return [
            'id_ticket' => $idTicket,
            'folio' => '0' . $idTicket,
            'descripcion' => (string)$ticket->descripcion,
            'prioridad' => (string)$ticket->prioridad,
            'color_prioridad' => self::colorPrioridad((string)$ticket->prioridad),
            'categoria' => (string)$ticket->categoria,
            'porcentaje' => (int)$ticket->porcentaje,
            'estado' => $estado,
            'estado_texto' => self::textoEstado($estado),
            'color_fila' => self::colorFilaDeTicket($ticket),
            'fecha_creacion' => self::formatearFechaHora($ticket->fecha_creacion),
            'fecha_inicio' => self::formatearFechaHora($ticket->fecha_inicio),
            'fecha_termino' => self::formatearFechaHora($ticket->fecha_termino),
            'fecha_termino_real' => self::formatearFechaHora($ticket->fecha_termino_real),
            'tiempo_solucion' => (int)$ticket->tiempo_solucion,
            'responsable' => $idPersonalSoporte > 0 ? self::nombreUsuario($idPersonalSoporte) : '',
            'total_comentarios' => $totalComentarios,
            'puede_editar' => $estado === self::ESTADO_CREANDO,
            // Sólo el solicitante puede validar el trabajo de Sistemas.
            'puede_validar' => self::puedeValidarSolicitud($ticket)
                && (int)$ticket->id_personal === self::idUsuario(),
        ];
    }

    /** "2026-10-19 15:04:05" -> "19 de Octubre del 2026, 3:04 pm". */
    public static function formatearFechaHora($fecha): string
    {        if ($fecha === null) {
            return '';
        }

        if ($fecha instanceof \DateTimeInterface) {
            // Las fechas "cero" de MySQL (0000-00-00) llegan como año -1.
            if ((int)$fecha->format('Y') <= 0) {
                return '';
            }

            $texto = $fecha->format('Y-m-d H:i:s');
        } else {
            $texto = (string)$fecha;
        }

        if ($texto === '' || str_starts_with($texto, '0000-00-00')) {
            return '';
        }

        $ts = strtotime($texto);

        if ($ts === false || $ts <= 0) {
            return '';
        }

        $fechaFmt = formatearFecha(date('Y-m-d', $ts));
        $horaFmt = date('g:i a', $ts);

        return trim($fechaFmt . ($horaFmt ? ', ' . $horaFmt : ''));
    }

    private static function formatearFechaActividad($fecha): string
    {
        if ($fecha === null) {
            return '';
        }

        if ($fecha instanceof \DateTimeInterface) {
            // Las fechas "cero" de MySQL (0000-00-00) llegan como año -1.
            if ((int)$fecha->format('Y') <= 0) {
                return '';
            }

            return formatearFecha($fecha->format('Y-m-d'));
        }

        $texto = (string)$fecha;

        if ($texto === '' || str_starts_with($texto, '0000-00-00')) {
            return '';
        }

        return formatearFecha($texto);
    }

    /**
     * Fecha en formato ISO (Y-m-d) para los campos de tipo date.
     */
    public static function isoFecha($fecha): string
    {
        if ($fecha === null) {
            return '';
        }

        if ($fecha instanceof \DateTimeInterface) {
            // Las fechas "cero" de MySQL (0000-00-00) llegan como año -1.
            if ((int)$fecha->format('Y') <= 0) {
                return '';
            }

            return $fecha->format('Y-m-d');
        }

        $texto = (string)$fecha;

        if ($texto === '' || str_starts_with($texto, '0000-00-00')) {
            return '';
        }

        $ts = strtotime($texto);

        return $ts === false ? '' : date('Y-m-d', $ts);
    }
}
