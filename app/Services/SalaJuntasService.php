<?php

namespace App\Services;

use App\Models\Junta;
use App\Models\JuntasComentario;
use App\Models\Puestos;
use App\Models\Usuario;
use App\Models\Estacion;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Sala de Juntas - logica de negocio.
 *
 * Comportamiento recuperado del proyecto portal-app:
 *   - app/vistas/juntas/juntas-index.php        (contenedor)
 *   - app/vistas/juntas/modal-agregar-junta.php  (formulario y horarios)
 *   - app/modelo/Home.php                        (agregar/editar/eliminar/comentar)
 *   - assets/js/solicitud-juntas.js               (interacciones)
 *
 * El modulo NO usa selector de estacion: las juntas son de alcance global
 * para toda la empresa. "Departamento / Estacion" es solo la forma de
 * elegir destinatarios, no un filtro de contexto.
 */
class SalaJuntasService
{
    public const MODULE_KEY = 'sala-juntas';

    /** Ventana horaria en la que se pueden agendar juntas (legacy). */
    public const HORA_INICIO = '08:00';
    public const HORA_FIN    = '23:00';
    public const INTERVALO   = 30; // minutos

    /** Estatus que ocupan agenda. */
    public const ESTATUS_OCUPAN = ['Programada', 'Horario Solicitado', 'Solicitud'];
    public const ESTATUS_CANCELADA = 'Cancelada';

    /** Puestos que pueden recibir una notificacion como destinatario. */
    private const PUESTOS_DESTINATARIO = [3, 5, 8, 13, 15, 20, 25, 26];

    /**
     * Puestos cuyo personal puede listarse para agendar una junta.
     * El 6 (Encargado) se agrupa por estacion; el resto, por puesto.
     */
    private const PUESTOS_PERSONAL = [3, 5, 7, 8, 13, 15, 20, 25, 26];

    /** Puesto que se agrupa por estacion en lugar de por puesto. */
    private const PUESTO_POR_ESTACION = 6;

    /** Estaciones consideradas para el puesto 6 (numlista <= 8). */
    private const NUMLISTA_MAX_ENCARGADO = 8;

    /**
     * Catalogos de "Solicitud de uso de la sala de juntas".
     *
     * El legacy usa una lista MAS corta de puestos que en el alta normal:
     * quien solicita el horario de otra junta solo puede convocar a estos.
     * Ver modal-solicitar-cambio.php.
     */
    private const PUESTOS_SOLICITUD_DESTINATARIO = [3, 5, 13, 15, 25];
    private const PUESTOS_SOLICITUD_PERSONAL     = [3, 5, 7, 13, 15, 25];

    /**
     * Estatus de una junta que tiene una solicitud de horario pendiente.
     *
     * "Solicitud" se mantiene en ESTATUS_OCUPAN por compatibilidad con
     * registros previos: sigue ocupando agenda, pero se muestra como
     * "Horario Solicitado".
     */
    public const ESTATUS_SOLICITUD = 'Horario Solicitado';

    /** Valor historico del estatus de solicitud, para mostrar. */
    public const ESTATUS_SOLICITUD_LEGACY = 'Solicitud';

    /**
     * Estatus listo para mostrar al usuario.
     */
    public static function estatusVisible($estatus): string
    {
        $valor = (string)$estatus;

        return $valor === self::ESTATUS_SOLICITUD_LEGACY
            ? self::ESTATUS_SOLICITUD
            : $valor;
    }

    /**
     * Estatus de una solicitud de uso.
     * 0 = pendiente, 1 = denegada, 2 = autorizada (legacy).
     */
    public const SOLICITUD_PENDIENTE  = 0;
    public const SOLICITUD_DENEGADA   = 1;
    public const SOLICITUD_AUTORIZADA = 2;

    /** Ubicacion fija de las reuniones (legacy). */
    private const UBICACION = '📍 <b>Ubicación:</b> <b>(Agor Interlomas)</b> Av. Jesús del Monte 32 - Piso 10, Hacienda de las Palmas, C.P. 52764, Jesús del Monte, México.';

    //======================================================================
    // PERMISOS
    //======================================================================

    /**
     * Permisos efectivos del usuario para este modulo.
     * Se leen de la sesion que arma ModuloService en el login.
     */
    public static function getPermisos(int $idUsuario): array
    {
        $db = ModuloService::permisosSesion(self::MODULE_KEY) ?: [];

        if (empty($db)) {
            $db = ModuloService::getPermiso($idUsuario, self::MODULE_KEY) ?: [];
        }

        return [
            'leer'     => !empty($db['leer']),
            'crear'    => !empty($db['crear']),
            'editar'   => !empty($db['editar']),
            'eliminar' => !empty($db['eliminar']),
            'descargar' => !empty($db['descargar']),
        ];
    }

    /** Validacion en backend: no basta con ocultar botones. */
    public static function puede(string $accion): bool
    {
        $usuario = \App\Core\Session::get('usuario') ?: [];
        $permisos = self::getPermisos((int)($usuario['id'] ?? 0));

        return !empty($permisos[$accion]);
    }

    //======================================================================
    // CATALOGOS DEL FORMULARIO
    //======================================================================

    /**
     * Listas que necesita el modal de alta/edicion.
     *
     * - convocantes : usuarios del mismo puesto que el usuario en sesion
     *   (mismo criterio que el legacy, que lo traia fijo y deshabilitado)
     * - puestos     : combinaciones "idPuesto-idEstacion"
     * - personal    : usuarios activos, para seleccion individual
     */
    public static function catalogoFormulario(int $idUsuario): array
    {
        $usuario = Usuario::query()->select(['id', 'id_puesto', 'nombre'])->find($idUsuario);
        $idPuestoUsuario = (int)($usuario->id_puesto ?? 0);

        $convocantes = Usuario::query()
            ->select(['id', 'nombre'])
            ->where('estatus', 0)
            ->when($idPuestoUsuario > 0, fn ($q) => $q->where('id_puesto', $idPuestoUsuario))
            ->orderBy('nombre')
            ->get()
            ->map(fn ($u) => ['id' => (int)$u->id, 'nombre' => $u->nombre])
            ->values()
            ->all();

        return [
            'convocantes'    => $convocantes,
            'puestos'        => self::puestosDestinatario(),
            'personal'       => self::personalDisponible(),

            /*
             * "Quien convoca" siempre es quien esta navegando: se devuelve
             * como dato informativo y no como opcion editable.
             */
            'convoca'        => [
                'id'     => $idUsuario,
                'nombre' => $usuario->nombre ?? '',
                'puesto' => $idPuestoUsuario,
            ],

            'usuario_actual' => $idUsuario,
            'id_puesto'      => $idPuestoUsuario,
        ];
    }

    /**
     * Departamentos / estaciones a los que se puede convocar.
     * El value es "idPuesto-idEstacion", formato que usa el legacy para
     * expandir despues a usuarios.
     *
     * @param array|null $puestos Lista de puestos; null usa la del alta normal.
     */
    public static function puestosDestinatario(?array $puestos = null): array
    {
        $puestos = $puestos ?? self::PUESTOS_DESTINATARIO;

        return Puestos::query()
            ->select(['id', 'tipo_puesto'])
            ->where('estatus', 0)
            ->where(function ($q) use ($puestos) {
                // El puesto 6 solo aparece en las primeras estaciones.
                $q->where('id', self::PUESTO_POR_ESTACION)
                  ->orWhereIn('id', $puestos);
            })
            ->orderBy('tipo_puesto')
            ->get()
            ->flatMap(function (Puestos $puesto) {

                $esEncargado = (int)$puesto->id === self::PUESTO_POR_ESTACION;

                $estaciones = Estacion::query()
                    ->select(['id', 'nombre'])
                    ->whereHas('usuarios', fn ($q) => $q
                        ->where('tb_usuarios.id_puesto', $puesto->id)
                        ->where('tb_usuarios.estatus', 0))
                    ->when($esEncargado, fn ($q) => $q->where('numlista', '<=', self::NUMLISTA_MAX_ENCARGADO))
                    ->orderBy('numlista')
                    ->orderBy('nombre')
                    ->get();

                return $estaciones->map(fn (Estacion $estacion) => [

                    'id'       => ((int)$puesto->id) . '-' . ((int)$estacion->id),

                    'estacion' => $estacion->nombre,

                    'nombre'   => $esEncargado
                        ? $puesto->tipo_puesto . ' (' . $estacion->nombre . ')'
                        : $puesto->tipo_puesto,
                ]);
            })
            ->values()
            ->all();
    }

    /**
     * Usuarios activos agrupados para el <select multiple>.
     *
     * Reproduce los <optgroup> del legacy: el puesto 6 (Encargado) se agrupa
     * por estacion y el resto por tipo de puesto.
     *
     * @param array|null $puestos Lista de puestos; null usa la del alta normal.
     */
    public static function personalDisponible(?array $puestos = null): array
    {
        $puestos = $puestos ?? self::PUESTOS_PERSONAL;

        $usuarios = Usuario::query()
            ->select([
                'tb_usuarios.id',
                'tb_usuarios.nombre',
                'tb_usuarios.id_puesto',
                'tb_puestos.tipo_puesto',
                'tb_estaciones.nombre as estacion',
                'tb_estaciones.numlista',
            ])
            ->join('tb_puestos', 'tb_puestos.id', '=', 'tb_usuarios.id_puesto')
            ->join('tb_estaciones', 'tb_estaciones.id', '=', 'tb_usuarios.id_gas')
            ->where('tb_usuarios.estatus', 0)
            ->where('tb_puestos.estatus', 0)
            ->where(function ($q) use ($puestos) {
                $q->where(function ($q) {
                    $q->where('tb_usuarios.id_puesto', self::PUESTO_POR_ESTACION)
                      ->where('tb_estaciones.numlista', '<=', self::NUMLISTA_MAX_ENCARGADO);
                })->orWhereIn('tb_usuarios.id_puesto', $puestos);
            })
            ->orderBy('tb_estaciones.numlista')
            ->orderBy('tb_estaciones.nombre')
            ->orderBy('tb_puestos.tipo_puesto')
            ->orderBy('tb_usuarios.nombre')
            ->get();

        $porEstacion = [];
        $porPuesto   = [];

        foreach ($usuarios as $u) {
            $item = [
                'id'      => (int)$u->id,
                'nombre'  => $u->nombre . ' (' . $u->tipo_puesto . ')',
                'puesto'  => $u->tipo_puesto,
            ];

            if ((int)$u->id_puesto === self::PUESTO_POR_ESTACION) {
                $etiqueta = ($u->estacion ?: 'Sin estación') . ' (Encargados)';
                $porEstacion[$etiqueta][] = $item;
            } else {
                $porPuesto[$u->tipo_puesto ?: 'Sin puesto'][] = $item;
            }
        }

        $grupos = [];
        foreach ($porEstacion as $etiqueta => $items) {
            $grupos[] = ['label' => $etiqueta, 'usuarios' => $items];
        }
        foreach ($porPuesto as $etiqueta => $items) {
            $grupos[] = ['label' => $etiqueta, 'usuarios' => $items];
        }

        return $grupos;
    }

    //======================================================================
    // HORARIOS
    //======================================================================

    /**
     * Horas de inicio libres para una fecha.
     *
     * Reproduce generarHorasDisponibles() del legacy: bloques de 30 minutos
     * entre 08:00 y 23:00, descartando los que se solapan con juntas ya
     * programadas o en solicitud ese mismo dia.
     */
    public static function horasDisponibles(string $fecha, int $excluirJuntaId = 0): array
    {
        $ocupadas = Junta::query()
            ->whereDate('fecha', $fecha)
            ->whereIn('estatus', self::ESTATUS_OCUPAN)
            ->when($excluirJuntaId > 0, fn ($q) => $q->where('id', '<>', $excluirJuntaId))
            ->get(['hora_inicio', 'hora_termino']);

        /*
         * Se trabaja en minutos desde medianoche para no depender de la
         * zona horaria ni del formato con que Carbon convierta a string.
         */
        $inicio = self::minutosDe(self::HORA_INICIO) ?? 0;
        $fin    = self::minutosDe(self::HORA_FIN) ?? 0;
        $paso   = self::INTERVALO;

        $horas = [];

        for ($m = $inicio; $m + $paso <= $fin; $m += $paso) {

            $colision = false;

            foreach ($ocupadas as $junta) {

                $desde = self::minutosDe($junta->hora_inicio);
                $hasta = self::minutosDe($junta->hora_termino);

                // Junta con horario ilegible: no puede bloque nada.
                if ($desde === null || $hasta === null) {
                    continue;
                }

                // El bloque completo se traslapa con alguna junta.
                if ($m < $hasta && ($m + $paso) > $desde) {
                    $colision = true;
                    break;
                }
            }

            if (!$colision) {
                $horas[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
            }
        }

        return $horas;
    }

    //======================================================================
    // ALTA / EDICION / BAJA
    //======================================================================

    /**
     * Crea una junta.
     *
     * @return array{success:bool,message:string,id?:int,invitados?:int}
     */
    public static function crear(array $datos, int $idUsuario): array
    {
        $validacion = self::validar($datos);

        if ($validacion !== null) {
            return ['success' => false, 'message' => $validacion];
        }

        $invitados = self::expandirDestinatarios(
            $datos['departamentos'] ?? [],
            $datos['personal'] ?? []
        );

        $junta = Junta::create([
            'idPuesto'       => (int)$datos['id_puesto'],
            'idUsuario'      => (int)$datos['idUsuario'],
            'descripcion'    => trim((string)$datos['descripcion']),
            'personalA'      => implode(',', $invitados),
            'fecha'          => $datos['fecha'],
            'hora_inicio'    => $datos['hora_inicio'],
            'hora_termino'   => $datos['hora_termino'],
            'estatus'        => 'Programada',
            'deletedBy'      => 0,
        ]);

        // Notificacion DESPUES de confirmar la operacion.
        self::notificar(
            $invitados,
            $junta,
            '📣 <b>Estimado(a) colaborador(a)</b>' . PHP_EOL .
            'Ha sido <b>convocado(a)</b> para asistir a la siguiente reunión:' . PHP_EOL . PHP_EOL .
            self::detalle($junta) . PHP_EOL . PHP_EOL .
            '📝 <b>Nota:</b> Se solicita su puntual asistencia para el adecuado desarrollo de la reunión.',
            $idUsuario
        );

        return [
            'success'   => true,
            'message'   => 'La junta se agregó correctamente.',
            'id'        => (int)$junta->id,
            'invitados' => count($invitados),
        ];
    }

    /** Edita una junta existente. */
    public static function actualizar(int $id, array $datos, int $idUsuario): array
    {
        $junta = Junta::find($id);

        if (!$junta) {
            return ['success' => false, 'message' => 'La junta no existe.'];
        }

        if (!self::esCreador($junta, $idUsuario)) {
            return [
                'success' => false,
                'message' => 'Solo quien creó la junta puede modificarla.',
            ];
        }

        if (self::tieneSolicitudPendiente($id)) {
            return [
                'success' => false,
                'message' => 'Hay una solicitud de uso pendiente de resolver.',
            ];
        }

        if ($junta->estatus === self::ESTATUS_CANCELADA) {
            return ['success' => false, 'message' => 'No se puede modificar una junta cancelada.'];
        }

        $validacion = self::validar($datos, $id);

        if ($validacion !== null) {
            return ['success' => false, 'message' => $validacion];
        }

        $invitados = self::expandirDestinatarios(
            $datos['departamentos'] ?? [],
            $datos['personal'] ?? []
        );

        $junta->update([
            'idPuesto'       => (int)$datos['id_puesto'],
            'idUsuario'      => (int)$datos['idUsuario'],
            'descripcion'    => trim((string)$datos['descripcion']),
            'personalA'      => implode(',', $invitados),
            'fecha'          => $datos['fecha'],
            'hora_inicio'    => $datos['hora_inicio'],
            'hora_termino'   => $datos['hora_termino'],
        ]);

        self::notificar(
            $invitados,
            $junta,
            '📣 <b>Estimado(a) colaborador(a)</b>' . PHP_EOL .
            'Conforme a la reunión a la que ha sido convocado(a), se han realizado algunos ajustes en los puntos a tratar. La reunión se llevará a cabo con los siguientes detalles:' . PHP_EOL . PHP_EOL .
            self::detalle($junta) . PHP_EOL . PHP_EOL .
            '📝 <b>Nota:</b> Se solicita su puntual asistencia para el adecuado desarrollo de la reunión.',
            $idUsuario
        );

        return [
            'success'   => true,
            'message'   => 'La junta se actualizó correctamente.',
            'id'        => (int)$junta->id,
            'invitados' => count($invitados),
        ];
    }

    /**
     * Indica si el usuario creo la junta.
     *
     * Solo quien agenda puede editar o cancelar. El resto debe registrar una
     * "Solicitud de uso de la sala de juntas".
     */
    public static function esCreador(Junta $junta, int $idUsuario): bool
    {
        return (int)$junta->idUsuario === $idUsuario;
    }

    /**
     * Una junta con solicitud pendiente queda bloqueada: no se puede editar,
     * cancelar ni comentar hasta que el creador acepte o deniegue.
     */
    public static function tieneSolicitudPendiente(int $idJunta): bool
    {
        return self::solicitudPendiente($idJunta) !== null;
    }

    /**
     * Catálogos de la "Solicitud de uso de la sala de juntas".
     *
     * El legacy restringe los puestos a una lista mas corta que en el alta
     * normal (modal-solicitar-cambio.php).
     */
    public static function catalogoSolicitud(int $idUsuario): array
    {
        $usuario = Usuario::query()
            ->select(['id', 'id_puesto', 'nombre'])
            ->find($idUsuario);

        return [
            'convoca' => [
                'id'     => $idUsuario,
                'nombre' => $usuario->nombre ?? '',
                'puesto' => (int)($usuario->id_puesto ?? 0),
            ],
            'id_puesto' => (int)($usuario->id_puesto ?? 0),
            'puestos'   => self::puestosDestinatario(self::PUESTOS_SOLICITUD_DESTINATARIO),
            'personal'  => self::personalDisponible(self::PUESTOS_SOLICITUD_PERSONAL),
        ];
    }

    /**
     * Registra la solicitud de uso del horario de una junta existente.
     *
     * Reproduce Home.php::solicitarCambioJunta(): inserta en
     * tb_juntas_solicitud_cambio y marca la junta como
     * 'Horario Solicitado' para que quien la creo la revise y autorice o
     * rechace.
     */
    public static function solicitarHorario(int $idJunta, array $datos, int $idUsuario): array
    {
        $junta = Junta::find($idJunta);

        if (!$junta) {
            return ['success' => false, 'message' => 'La junta no existe.'];
        }

        if (self::esCreador($junta, $idUsuario)) {
            return [
                'success' => false,
                'message' => 'Esta junta es tuya. Edítala o cancélala directamente.',
            ];
        }

        if ($junta->estatus === self::ESTATUS_CANCELADA) {
            return ['success' => false, 'message' => 'La junta se encuentra cancelada.'];
        }

        // Solo una solicitud pendiente por junta.
        if (self::tieneSolicitudPendiente($idJunta)) {
            return [
                'success' => false,
                'message' => 'Ya hay una solicitud de uso pendiente de resolver.',
            ];
        }

        $motivo = trim((string)($datos['motivo'] ?? ''));
        $tema   = trim((string)($datos['descripcion'] ?? ''));

        if ($motivo === '') {
            return ['success' => false, 'message' => 'Escribe el motivo de la solicitud.'];
        }

        if ($tema === '') {
            return ['success' => false, 'message' => 'Escribe el tema de la reunión.'];
        }

        // El horario y la fecha son los de la junta: no se pueden cambiar.
        $invitados = self::expandirDestinatarios(
            $datos['departamentos'] ?? [],
            $datos['personal'] ?? []
        );

        if (empty($invitados)) {
            return [
                'success' => false,
                'message' => 'Selecciona al menos un departamento o una persona.',
            ];
        }

        $usuario = Usuario::query()
            ->select(['id', 'id_puesto', 'nombre'])
            ->find($idUsuario);

        Capsule::table('tb_juntas_solicitud_cambio')->insert([
            'id_junta'               => $idJunta,
            'id_usuario'             => $idUsuario,
            'id_puesto'              => (int)($usuario->id_puesto ?? 0),
            'motivo_cambio'          => $motivo,
            'descripcion'            => $tema,
            'fecha'                  => (string)$junta->fecha?->format('Y-m-d'),
            'hora_inicio'            => (string)$junta->hora_inicio?->format('H:i:s'),
            'hora_fin'               => (string)$junta->hora_termino?->format('H:i:s'),
            'id_personal_autorizado' => implode(',', $invitados),
            'estatus'                => 0,
        ]);

        // La junta pasa a 'Horario Solicitado': el creador ve el pendiente.
        $junta->estatus = self::ESTATUS_SOLICITUD;
        $junta->save();

        // Aviso a los convocantes de la junta (si tienen Telegram verificado).
        self::notificarSolicitud(
            self::idsPersonal($junta->personalA),
            $junta,
            $usuario->nombre ?? '',
            $motivo
        );

        return [
            'success' => true,
            'message' => 'La solicitud se envió. Quien creó la junta debe autorizarla.',
            'id'      => $idJunta,
        ];
    }

    /**
     * Solicitud de uso pendiente de una junta, o null si no hay ninguna.
     *
     * Devuelve los datos tal como los muestra el legacy en
     * modal-respuesta-cambio.php: quien solicita, tema, motivo, fecha y horas.
     */
    public static function solicitudPendiente(int $idJunta): ?array
    {
        $fila = Capsule::table('tb_juntas_solicitud_cambio')
            ->where('id_junta', $idJunta)
            ->where('estatus', self::SOLICITUD_PENDIENTE)
            ->orderByDesc('id')
            ->first();

        if (!$fila) {
            return null;
        }

        $solicitante = Usuario::query()
            ->select(['id', 'nombre'])
            ->find($fila->id_usuario);

        $fecha = (string)$fila->fecha;

        return [
            'id'             => (int)$fila->id,
            'id_junta'       => (int)$fila->id_junta,
            'id_usuario'     => (int)$fila->id_usuario,
            'solicitante'    => $solicitante->nombre ?? 'Usuario',
            'tema'           => (string)$fila->descripcion,
            'motivo'         => (string)$fila->motivo_cambio,
            'fecha'          => $fecha,
            'fecha_label'    => formatearFecha($fecha),
            'hora_inicio'    => self::horaLegible($fila->hora_inicio),
            'hora_termino'   => self::horaLegible($fila->hora_fin),
        ];
    }

    /**
     * El creador acepta o deniega la solicitud de uso del horario.
     *
     * Reproduce Home.php::FinalizarCambioJunta():
     *   - aceptada: crea la junta del solicitante, deja la original como
     *     Programada y notifica a ambos.
     *   - denegada: deja la original como Programada y avisa al solicitante.
     *
     * Al aceptar, el creador puede elegir en que fecha y horario se agenda
     * la reunion del solicitante (modal "Autorizacion de uso de la sala de
     * juntas"). Si no se indica, se usa el horario original de la junta.
     *
     * @param bool  $aceptar true = autorizar, false = denegar.
     * @param array $nuevo   ['fecha', 'hora_inicio', 'hora_termino'] opcionales.
     */
    public static function responderSolicitud(
        int $idJunta,
        bool $aceptar,
        int $idUsuario,
        array $nuevo = []
    ): array {
        $junta = Junta::find($idJunta);

        if (!$junta) {
            return ['success' => false, 'message' => 'La junta no existe.'];
        }

        if (!self::esCreador($junta, $idUsuario)) {
            return [
                'success' => false,
                'message' => 'Solo quien creó la junta puede resolver la solicitud.',
            ];
        }

        $solicitud = self::solicitudPendiente($idJunta);

        if ($solicitud === null) {
            return ['success' => false, 'message' => 'No hay ninguna solicitud pendiente.'];
        }

        $nuevoEstatus = $aceptar
            ? self::SOLICITUD_AUTORIZADA
            : self::SOLICITUD_DENEGADA;

        /*
         * Comportamiento del legacy (Home.php::FinalizarCambioJunta):
         *
         *   - La junta ORIGINAL se MUEVE al horario que eligio el creador,
         *     y conserva su tema, su convocante y su personal.
         *   - Se crea una junta NUEVA para el solicitante en el horario que
         *     solicito (el que ocupaba la junta), con el tema de la solicitud.
         *
         * Es decir: el solicitante se queda con el horario que pidio y la
         * reunion original se reubica para no encimarse.
         */
        $fechaSolicitante     = (string)$junta->fecha?->format('Y-m-d');
        $horaInicioSolicitante = (string)$junta->hora_inicio?->format('H:i');
        $horaTerminoSolicitante = (string)$junta->hora_termino?->format('H:i');

        $solicitante = Usuario::query()
            ->select(['id', 'id_puesto', 'nombre'])
            ->find($solicitud['id_usuario']);

        $invitados = array_values(array_filter(array_map(
            'intval',
            explode(',', (string)Capsule::table('tb_juntas_solicitud_cambio')
                ->where('id', $solicitud['id'])
                ->value('id_personal_autorizado'))
        )));

        // Horario al que se movera la junta original.
        $fechaNueva       = $fechaSolicitante;
        $horaInicioNueva  = $horaInicioSolicitante;
        $horaTerminoNueva = $horaTerminoSolicitante;

        if ($aceptar) {
            $fechaNueva       = trim((string)($nuevo['fecha'] ?? '')) ?: $fechaSolicitante;
            $horaInicioNueva  = trim((string)($nuevo['hora_inicio'] ?? '')) ?: $horaInicioSolicitante;
            $horaTerminoNueva = trim((string)($nuevo['hora_termino'] ?? '')) ?: $horaTerminoSolicitante;

            if ($fechaNueva === $fechaSolicitante
                && $horaInicioNueva === $horaInicioSolicitante
                && $horaTerminoNueva === $horaTerminoSolicitante) {
                return [
                    'success' => false,
                    'message' => 'Elige una fecha y un horario distintos a los actuales '
                        . 'para mover tu reunión.',
                ];
            }
        }

        /*
         * Se valida ANTES de tocar nada: si la aceptacion no fuera valida
         * (por ejemplo si el horario ya se ocupo), la solicitud debe quedar
         * pendiente y no perderse.
         *
         * La junta original se reubica, asi que se excluye a si misma: de lo
         * contrario bloquearia el horario que va a liberar.
         */
        if ($aceptar) {

            $validacion = self::validar([
                'descripcion' => (string)$junta->descripcion,
                'idUsuario'   => (int)$junta->idUsuario,
                'id_puesto'   => (int)$junta->idPuesto,
                'fecha'       => $fechaNueva,
                'hora_inicio' => $horaInicioNueva,
                'hora_termino'=> $horaTerminoNueva,
                'personal'    => self::idsPersonal((string)$junta->personalA),
            ], $idJunta);

            if ($validacion !== null) {
                return ['success' => false, 'message' => $validacion];
            }

        }

        Capsule::table('tb_juntas_solicitud_cambio')
            ->where('id', $solicitud['id'])
            ->update(['estatus' => $nuevoEstatus]);

        // La junta vuelve a quedar disponible.
        $junta->estatus = 'Programada';

        if ($aceptar) {
            // La reunion original se mueve al horario elegido por el creador.
            $junta->fecha         = $fechaNueva;
            $junta->hora_inicio   = $horaInicioNueva;
            $junta->hora_termino  = $horaTerminoNueva;
        }

        $junta->save();

        if (!$aceptar) {
            self::notificarSolicitudRespuesta(
                [$solicitud['id_usuario']],
                false,
                $fechaNueva,
                $horaInicioNueva,
                $horaTerminoNueva
            );

            return [
                'success' => true,
                'message' => 'La solicitud fue denegada.',
            ];
        }

        /*
         * La reunion del solicitante toma el horario que solicito, con el
         * tema que capturo. Se crea despues de mover la original para que
         * el horario ya este libre.
         */
        $nueva = Junta::create([
            'idPuesto'    => (int)($solicitante->id_puesto ?? 0),
            'idUsuario'   => (int)$solicitud['id_usuario'],
            'descripcion' => $solicitud['tema'],
            'personalA'   => implode(',', $invitados),
            'fecha'       => $fechaSolicitante,
            'hora_inicio' => $horaInicioSolicitante,
            'hora_termino'=> $horaTerminoSolicitante,
            'estatus'     => 'Programada',
            'deletedBy'   => 0,
        ]);

        self::notificar(
            $invitados,
            $nueva,
            '📣 <b>Estimado(a) colaborador(a)</b>' . PHP_EOL
            . 'Su solicitud de uso de la sala de juntas fue <b>autorizada</b>.' . PHP_EOL . PHP_EOL
            . self::detalle($nueva) . PHP_EOL . PHP_EOL
            . self::UBICACION,
            (int)$solicitud['id_usuario']
        );

        self::notificarSolicitudRespuesta(
            [$solicitud['id_usuario']],
            true,
            $fechaSolicitante,
            $horaInicioSolicitante,
            $horaTerminoSolicitante
        );

        return [
            'success' => true,
            'message' => 'Solicitud autorizada. Tu reunión se movió al horario elegido.',
            'id'      => (int)$nueva->id,
        ];
    }

    /**
     * Baja logica: la junta no se borra, se cancela.
     * Es el comportamiento del legacy (Home.php:476) y permite conservar
     * el historial de comentarios y solicitudes de cambio.
     */
    public static function eliminar(int $id, int $idUsuario): array
    {
        $junta = Junta::find($id);

        if (!$junta) {
            return ['success' => false, 'message' => 'La junta no existe.'];
        }

        if (!self::esCreador($junta, $idUsuario)) {
            return [
                'success' => false,
                'message' => 'Solo quien creó la junta puede cancelarla.',
            ];
        }

        if (self::tieneSolicitudPendiente($id)) {
            return [
                'success' => false,
                'message' => 'Hay una solicitud de uso pendiente de resolver.',
            ];
        }

        if ($junta->estatus === self::ESTATUS_CANCELADA) {
            return ['success' => false, 'message' => 'La junta ya se encuentra cancelada.'];
        }

        $invitados = self::idsPersonal($junta->personalA);

        $junta->update([
            'estatus'   => self::ESTATUS_CANCELADA,
            'deletedBy' => $idUsuario,
        ]);

        self::notificar(
            $invitados,
            $junta,
            '📣 <b>Estimado(a) colaborador(a)</b>' . PHP_EOL .
            'Conforme a la reunión a la que había sido convocado(a). La reunión ha sido <b>cancelada</b> y no se llevará a cabo según lo programado.' . PHP_EOL . PHP_EOL .
            self::detalle($junta),
            $idUsuario
        );

        return [
            'success'   => true,
            'message'   => 'La junta se canceló correctamente.',
            'invitados' => count($invitados),
        ];
    }

    //======================================================================
    // COMENTARIOS
    //======================================================================

    /**
     * Agrega un comentario a la junta.
     *
     * El legacy (Home.php:500) inserta, arma el mensaje y notifica al
     * convocante si quien comenta no es el mismo, y al resto de la lista.
     */
    public static function crearComentario(int $idJunta, string $comentario, int $idUsuario): array
    {
        $comentario = trim($comentario);

        if ($comentario === '') {
            return ['success' => false, 'message' => 'Escribe un comentario.'];
        }

        if (mb_strlen($comentario) > 2000) {
            return ['success' => false, 'message' => 'El comentario es demasiado largo.'];
        }

        $junta = Junta::find($idJunta);

        if (!$junta) {
            return ['success' => false, 'message' => 'La junta no existe.'];
        }

        if ($junta->estatus === self::ESTATUS_CANCELADA) {
            return ['success' => false, 'message' => 'No se pueden agregar comentarios a una junta cancelada.'];
        }

        if (self::tieneSolicitudPendiente((int)$idJunta)) {
            return [
                'success' => false,
                'message' => 'Hay una solicitud de uso pendiente de resolver.',
            ];
        }

        JuntasComentario::create([
            'id_junta'   => $idJunta,
            'fecha_hora' => date('Y-m-d H:i:s'),
            'id_usuario' => $idUsuario,
            'comentario' => $comentario,
        ]);

        $invitados = self::idsPersonal($junta->personalA);

        // Quien convoca solo recibe el aviso si el comentario es de otra
        // persona (regla del legacy, Home.php:538).
        if ((int)$junta->idUsuario !== $idUsuario && !in_array((int)$junta->idUsuario, $invitados, true)) {
            $invitados[] = (int)$junta->idUsuario;
        }

        $autor = Usuario::find($idUsuario);

        self::notificar(
            $invitados,
            $junta,
            '💭 ' . ($autor->nombre ?? 'Un usuario') . ' agregó un nuevo comentario en la siguiente reunión:' . PHP_EOL . PHP_EOL .
            self::detalle($junta) . PHP_EOL . PHP_EOL .
            '💬 <b>Comentario:</b> ' . $comentario,
            $idUsuario
        );

        return ['success' => true, 'message' => 'Comentario agregado exitosamente.'];
    }

    /** Comentarios de una junta, con el nombre de quien los escribio. */
    public static function comentarios(int $idJunta, int $idUsuarioActual): array
    {
        return JuntasComentario::query()
            ->where('id_junta', $idJunta)
            ->orderBy('fecha_hora')
            ->orderBy('id')
            ->get()
            ->map(function ($comentario) use ($idUsuarioActual) {

                $usuario = Usuario::query()
                    ->select(['id', 'nombre'])
                    ->find($comentario->id_usuario);

                return [
                    'id'           => (int)$comentario->id,
                    'id_usuario'   => (int)$comentario->id_usuario,
                    'autor'        => $usuario->nombre ?? 'Usuario',
                    'fecha_hora'   => $comentario->fecha_hora
                        ? $comentario->fecha_hora->format('Y-m-d H:i')
                        : null,
                    'fecha_label'  => $comentario->fecha_hora
                        ? formatearFecha($comentario->fecha_hora->format('Y-m-d'))
                        : '',
                    'hora_label'   => $comentario->fecha_hora
                        ? $comentario->fecha_hora->format('h:i A')
                        : '',
                    'comentario'   => $comentario->comentario,
                    'es_propio'    => (int)$comentario->id_usuario === $idUsuarioActual,
                ];
            })
            ->values()
            ->all();
    }

    //======================================================================
    // SOPORTE
    //======================================================================

    /**
     * Convierte "idPuesto-idEstacion" en usuarios y los une con la
     * seleccion individual, deduplicando (Home.php:313-332).
     */
    private static function expandirDestinatarios(array $departamentos, array $personal): array
    {
        $ids = [];

        foreach ($personal as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        foreach ($departamentos as $combo) {

            $partes = explode('-', (string)$combo);

            if (count($partes) < 2) {
                continue;
            }

            $idPuesto    = (int)$partes[0];
            $idEstacion  = (int)$partes[1];

            if ($idPuesto <= 0 || $idEstacion <= 0) {
                continue;
            }

            $usuarios = Usuario::query()
                ->where('id_puesto', $idPuesto)
                ->where('id_gas', $idEstacion)
                ->where('estatus', 0)
                ->pluck('id')
                ->map(fn ($v) => (int)$v)
                ->all();

            foreach ($usuarios as $id) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /** Lee el CSV de personalA. */
    private static function idsPersonal(?string $personalA): array
    {
        if ($personalA === null || trim($personalA) === '') {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            'intval',
            explode(',', $personalA)
        ))));
    }

    /**
     * Reglas de negocio de la reunion.
     * Devuelve null si todo es correcto, o el mensaje de error.
     */
    private static function validar(array $datos, int $excluirJuntaId = 0): ?string
    {
        $descripcion = trim((string)($datos['descripcion'] ?? ''));
        $idUsuario   = (int)($datos['idUsuario'] ?? 0);
        $fecha       = (string)($datos['fecha'] ?? '');
        $horaInicio  = (string)($datos['hora_inicio'] ?? '');
        $horaTermino = (string)($datos['hora_termino'] ?? '');

        if ($descripcion === '') {
            return 'Captura el tema de la reunión.';
        }

        if (mb_strlen($descripcion) > 255) {
            return 'El tema no puede exceder 255 caracteres.';
        }

        if ($idUsuario <= 0 || !Usuario::query()->where('id', $idUsuario)->where('estatus', 0)->exists()) {
            return 'Selecciona quién convoca.';
        }

        if ($fecha === '') {
            return 'Selecciona la fecha programada.';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return 'La fecha no es válida.';
        }

        if ($fecha < date('Y-m-d')) {
            return 'La fecha no puede ser anterior a hoy.';
        }

        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $horaInicio) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $horaTermino)) {
            return 'Selecciona el horario de la reunión.';
        }

        /*
         * La sala solo opera entre HORA_INICIO y HORA_FIN. Sin este limite
         * del lado del servidor, una peticion directa podria agendar una
         * junta fuera del rango que ofrecen los selects.
         */
        $desdeMin = self::minutosDe(self::HORA_INICIO) ?? 0;
        $hastaMin = self::minutosDe(self::HORA_FIN) ?? 0;
        $iniMin   = self::minutosDe($horaInicio);
        $finMin   = self::minutosDe($horaTermino);

        if ($iniMin === null || $finMin === null) {
            return 'Selecciona el horario de la reunión.';
        }

        if ($finMin <= $iniMin) {
            return 'La hora de término debe ser posterior a la de inicio.';
        }

        if ($iniMin < $desdeMin || $iniMin >= $hastaMin) {
            return 'La hora de inicio debe estar entre '
                . self::HORA_INICIO . ' y ' . self::HORA_FIN . '.';
        }

        if ($finMin > $hastaMin) {
            return 'La hora de término no puede ser posterior a '
                . self::HORA_FIN . '.';
        }

        if (empty($datos['departamentos']) && empty($datos['personal'])) {
            return 'Selecciona al menos un departamento o una persona.';
        }

        $choque = Junta::query()
            ->whereDate('fecha', $fecha)
            ->whereIn('estatus', self::ESTATUS_OCUPAN)
            ->when($excluirJuntaId > 0, fn ($q) => $q->where('id', '<>', $excluirJuntaId))
            ->get(['hora_inicio', 'hora_termino'])
            ->filter(function ($otra) use ($iniMin, $finMin) {

                $desde = self::minutosDe($otra->hora_inicio);
                $hasta = self::minutosDe($otra->hora_termino);

                if ($desde === null || $hasta === null) {
                    return false;
                }

                // Se traslapa si el nuevo bloque invade el existente o al reves.
                return $iniMin < $hasta && $finMin > $desde;
            });

        if ($choque->isNotEmpty()) {
            return 'El horario se traslapa con otra junta programada ese día.';
        }

        return null;
    }

    /** Bloque comun de los mensajes de Telegram. */
    private static function detalle(Junta $junta): string
    {
        $convoca = Usuario::query()->select(['nombre'])->find($junta->idUsuario)->nombre ?? 'S/I';

        return '👤 <b>Convoca:</b> ' . $convoca . PHP_EOL .
               '🗂️ <b>Tema:</b> ' . $junta->descripcion . PHP_EOL .
               '📅 <b>Fecha:</b> ' . formatearFecha($junta->fecha) . PHP_EOL .
               '🕒 <b>Horario:</b> ' . self::horaLegible($junta->hora_inicio)
                   . ' a ' . self::horaLegible($junta->hora_termino) . PHP_EOL .
               self::UBICACION;
    }

    /**
     * Hora en formato de 12 horas con AM/PM: "02:30 PM".
     *
     * Usa minutosDe() para no depender del formato con que Carbon
     * convierte el modelo a string.
     */
    private static function horaLegible($hora): string
    {
        $minutos = self::minutosDe($hora);

        if ($minutos === null) {
            return '';
        }

        $h = intdiv($minutos, 60);
        $i = $minutos % 60;
        $sufijo = $h >= 12 ? 'PM' : 'AM';
        $h12 = $h % 12;

        if ($h12 === 0) {
            $h12 = 12;
        }

        return sprintf('%02d:%02d %s', $h12, $i, $sufijo);
    }

    /**
     * Convierte una hora a minutos desde medianoche.
     *
     * Acepta Carbon, DateTime o una cadena "HH:MM" / "HH:MM:SS". Devuelve
     * null cuando el valor no es una hora valida.
     *
     * Es la unica forma segura de comparar horarios: el cast a string de un
     * modelo Carbon obedece a Carbon::setToStringFormat(), que Bootstrap
     * fija con APP_DATE_FORMAT ("d/m/Y H:i"), asi que (string)$hora puede
     * devolver "14/10/2026 08:00" y strtotime() daria false.
     */
    private static function minutosDe($hora): ?int
    {
        if ($hora instanceof \DateTimeInterface) {
            return ((int)$hora->format('H')) * 60 + (int)$hora->format('i');
        }

        if (!preg_match('/^\s*(\d{1,2}):(\d{2})/', (string)$hora, $m)) {
            return null;
        }

        $h = (int)$m[1];
        $i = (int)$m[2];

        if ($h > 23 || $i > 59) {
            return null;
        }

        return ($h * 60) + $i;
    }

    /**
     * Envia la notificacion por Telegram.
     *
     * Se ejecuta DESPUES de confirmar la operacion y nunca propaga errores:
     * un fallo de Telegram no debe reportar como fallida una operacion de
     * base de datos que ya se completo.
     */
    private static function notificar(array $invitados, Junta $junta, string $mensaje, int $idUsuario): void
    {
        $destinatarios = array_values(array_diff(
            array_filter($invitados, static fn ($v) => $v > 0),
            [$idUsuario]
        ));

        if (empty($destinatarios)) {
            return;
        }

        try {
            $telegram = new TelegramService();

            foreach ($destinatarios as $idDestinatario) {

                // Solo se notifica a quien tenga su token verificado.
                if ($telegram->getChatId($idDestinatario) <= 0) {
                    continue;
                }

                $telegram->sendMessage(
                    $telegram->getChatId($idDestinatario),
                    $mensaje
                );
            }
          } catch (\Throwable $e) {
              error_log('[SalaJuntas] Error notificando a Telegram: ' . $e->getMessage());
          }
      }

      /**
       * Aviso de que alguien solicito el horario de una junta.
       *
       * A diferencia de notificar(), aqui NO se excluye al solicitante
       * porque el destinatario natural es quien creo la junta.
       */
      private static function notificarSolicitud(
          array $convocados,
          Junta $junta,
          string $solicitante,
          string $motivo
      ): void {
          $destinatarios = array_values(array_filter(
              array_map('intval', $convocados),
              static fn ($v) => $v > 0
          ));

          if (empty($destinatarios)) {
              return;
          }

          $mensaje = '📨 <b>' . $solicitante . '</b> ha solicitado la autorización '
              . 'para disponer de la fecha y horario asignados de la siguiente reunión:'
              . PHP_EOL . PHP_EOL
              . '📌 <b>Motivo:</b> ' . $motivo . PHP_EOL . PHP_EOL
              . self::detalle($junta) . PHP_EOL . PHP_EOL
              . self::UBICACION;

          try {
              $telegram = new TelegramService();

              foreach (array_unique($destinatarios) as $idDestinatario) {

                  if ($telegram->getChatId($idDestinatario) <= 0) {
                      continue;
                  }

                  $telegram->sendMessage(
                      $telegram->getChatId($idDestinatario),
                      $mensaje
                  );
              }
          } catch (\Throwable $e) {
              error_log('[SalaJuntas] Error notificando solicitud: ' . $e->getMessage());
          }
      }

      /**
       * Avisa al solicitante si su solicitud fue autorizada o denegada.
       */
      private static function notificarSolicitudRespuesta(
          array $destinatarios,
          bool $aceptada,
          string $fecha,
          string $horaInicio,
          string $horaTermino
      ): void {
          $destinatarios = array_values(array_filter(
              array_map('intval', $destinatarios),
              static fn ($v) => $v > 0
          ));

          if (empty($destinatarios)) {
              return;
          }

          $fechaLabel = formatearFecha($fecha);

          $mensaje = $aceptada
              ? '✅ Tu solicitud para utilizar la sala de juntas el día '
                  . $fechaLabel . ' con un horario de '
                  . self::horaLegible($horaInicio) . ' a '
                  . self::horaLegible($horaTermino)
                  . ' ha sido <b>autorizada</b>.' . PHP_EOL . PHP_EOL
                  . '📝 <b>Nota:</b> Ya puedes continuar con la programación de tu reunión.'
              : '❌ Tu solicitud para utilizar la sala de juntas el día '
                  . $fechaLabel . ' con un horario de '
                  . self::horaLegible($horaInicio) . ' a '
                  . self::horaLegible($horaTermino)
                  . ' ha sido <b>denegada</b>.';

          try {
              $telegram = new TelegramService();

              foreach (array_unique($destinatarios) as $idDestinatario) {

                  if ($telegram->getChatId($idDestinatario) <= 0) {
                      continue;
                  }

                  $telegram->sendMessage(
                      $telegram->getChatId($idDestinatario),
                      $mensaje
                  );
              }
          } catch (\Throwable $e) {
              error_log('[SalaJuntas] Error notificando respuesta: ' . $e->getMessage());
          }
      }
  }