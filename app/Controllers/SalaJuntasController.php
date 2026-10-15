<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Request;
use App\Core\JsonResponse;

use App\Services\ModuloService;
use App\Services\SalaJuntasService;

use App\Models\Junta;

class SalaJuntasController extends BaseController
{
    protected string $modulo = 'sala-juntas';

    public function index()
    {
        $title = 'Sala de Juntas';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add($title, '');

        // Permisos efectivos: se calculan una vez y se reutilizan en backend.
        $permisos = SalaJuntasService::getPermisos((int)$this->userId());

        // "Convoca" siempre es quien esta navegando y solo es informativo.
        $usuario = \App\Models\Usuario::query()
            ->select(['id', 'nombre', 'id_puesto'])
            ->find((int)$this->userId());

        $data = [
            'title'             => $title,
            'permisos'          => $permisos,
            'modulo'            => $this->modulo,
            'idUsuario'         => (int)$this->userId(),
            'nombreUsuario'     => $usuario->nombre ?? '',
            'idPuestoUsuario'   => (int)($usuario->id_puesto ?? 0),
            'moduleStationKey'  => null,
            'links'             => [
                // El layout 'main' antepone /assets/ via asset(),
                // asi que aqui van las rutas SIN ese prefijo.
                '/libs/select2/dist/css/select2.min.css',
                '/css/select2-modal.css?v=2.0.1',
            ],
            'scripts'           => [
                '/js/vendor.min.js',
                '/libs/fullcalendar/index.global.min.js',
                '/libs/select2/dist/js/select2.full.min.js',
                '/js/salajuntas/calendar-init.js?v=2.13.0'
            ],
            'help'              => false

        ];

        View::render('sala-juntas/index', $data, 'main');
    }

    public function dataCalendario(): void
    {
        if (!$this->exige('leer')) {
            return;
        }

        $inicio = Request::input('start');
        $fin    = Request::input('end');

        $juntas = Junta::query()
            ->with([
                'usuario:id,nombre,id_puesto',
                'usuario.puesto:id,tipo_puesto',
            ])
            ->withCount('comentarios')
            ->where(function ($query) {
                $query
                    ->where('estatus', '<>', 'Cancelada')
                    ->orWhereNull('estatus');
            })
            ->when(
                !empty($inicio),
                fn($query) => $query->whereDate(
                    'fecha',
                    '>=',
                    substr($inicio, 0, 10)
                )
            )
            ->when(
                !empty($fin),
                fn($query) => $query->whereDate(
                    'fecha',
                    '<',
                    substr($fin, 0, 10)
                )
            )
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();

        $eventos = $juntas->map(function (Junta $junta) {

            $fecha = $junta->fecha?->format('Y-m-d');

            $horaInicio =
                $junta->hora_inicio?->format('H:i:s');

            $horaTermino =
                $junta->hora_termino?->format('H:i:s');

            return [
                'id' => (string) $junta->id,

                'title' => $junta->descripcion
                    ?: 'Sin DescripciÃ³n',

                'start' => $fecha && $horaInicio
                    ? $fecha . 'T' . $horaInicio
                    : $fecha,

                'end' => $fecha && $horaTermino
                    ? $fecha . 'T' . $horaTermino
                    : null,

                'extendedProps' => [
                    'idJunta' => (int) $junta->id,

                    'idPuesto' => (int) $junta->idPuesto,

                    'idUsuario' => (int) $junta->idUsuario,

                    'convoca' =>
                    $junta->usuario?->nombre ?? 'S/I',

                    'puesto' =>
                    $junta->usuario?->puesto?->tipo_puesto
                        ?? 'S/I',

                    'descripcion' =>
                    $junta->descripcion
                        ?: 'Sin DescripciÃ³n',

                    'fecha' => $fecha,

                    'hora_inicio' =>
                    $junta->hora_inicio?->format('H:i'),

                    'hora_termino' =>
                    $junta->hora_termino?->format('H:i'),

                    'estatus' => SalaJuntasService::estatusVisible($junta->estatus),

                    'comentarios' =>
                    (int) $junta->comentarios_count,
                ],
            ];
        });

        JsonResponse::custom([
            'eventos' => $eventos->values()->toArray(),

            /*
             * Totales del rango visible, para el encabezado del calendario
             * (mismo bloque que /sasisopa/calendario y /sgm/calendario).
             */
            'totales' => [
                'programadas' => (int)$juntas
                    ->where('estatus', '!=', SalaJuntasService::ESTATUS_SOLICITUD)
                    ->count(),

                'solicitudes' => (int)$juntas
                    ->where('estatus', SalaJuntasService::ESTATUS_SOLICITUD)
                    ->count(),

                'total' => (int)$juntas->count(),
            ],
        ]);
    }

    public function diaCalendario(): void
    {
        if (!$this->exige('leer')) {
            return;
        }

        $fecha = Request::input('fecha');

        if (empty($fecha)) {
            JsonResponse::custom([
                'success' => false,
                'data' => [],
            ]);

            return;
        }

        $juntas = Junta::query()
            ->with([
                'usuario:id,nombre,id_puesto',
                'usuario.puesto:id,tipo_puesto',
            ])
            ->withCount('comentarios')
            ->whereDate('fecha', $fecha)
            ->where(function ($query) {
                $query
                    ->where('estatus', '<>', 'Cancelada')
                    ->orWhereNull('estatus');
            })
            ->orderBy('hora_inicio')
            ->get();

        $data = $juntas->map(function (Junta $junta) {

            return [
                'id' => (int) $junta->id,

                'convoca' =>
                $junta->usuario?->nombre ?? 'S/I',

                'puesto' =>
                $junta->usuario?->puesto?->tipo_puesto
                    ?? 'S/I',

                'descripcion' =>
                $junta->descripcion
                    ?: 'Sin DescripciÃ³n',

                'fecha' =>
                $junta->fecha?->format('Y-m-d'),

                'hora_inicio' =>
                $junta->hora_inicio?->format('H:i'),

                'hora_termino' =>
                $junta->hora_termino?->format('H:i'),

                'estatus' => SalaJuntasService::estatusVisible($junta->estatus),

                'comentarios' =>
                (int) $junta->comentarios_count,
            ];
        });

        JsonResponse::custom([
            'success' => true,
            'data' => $data->values()->toArray(),
        ]);
    }
    public function detalleCalendario(int $id): void
    {
        if (!$this->exige('leer')) {
            return;
        }

        $junta = Junta::query()
            ->with([
                'usuario:id,nombre,id_puesto',
                'usuario.puesto:id,tipo_puesto',
            ])
            ->find($id);

        if (!$junta) {
            JsonResponse::custom([
                'success' => false,
                'message' => 'Junta no encontrada',
            ]);

            return;
        }

        // Los comentarios se resuelven en el Service para traer autor y
        // bandera de propio, igual que en el resto de los mÃ³dulos.
        $esCreador = SalaJuntasService::esCreador($junta, (int)$this->userId());
        $esActiva  = $junta->estatus !== SalaJuntasService::ESTATUS_CANCELADA;

        /*
         * Mientras haya una solicitud pendiente la junta queda bloqueada:
         * el creador debe aceptarla o denegarla antes de hacer cualquier otra cosa.
         */
        $solicitud = SalaJuntasService::solicitudPendiente((int)$junta->id);
        $pendiente = $solicitud !== null;

        JsonResponse::custom([
            'success' => true,

            'data' => [
                'id' =>
                (int) $junta->id,

                'convoca' =>
                $junta->usuario?->nombre ?? 'S/I',

                'puesto' =>
                $junta->usuario?->puesto?->tipo_puesto
                    ?? 'S/I',

                'descripcion' =>
                $junta->descripcion
                    ?: 'Sin DescripciÃ³n',

                'fecha' =>
                $junta->fecha?->format('Y-m-d'),

                'fecha_label' =>
                formatearFecha($junta->fecha),

                'hora_inicio' =>
                $junta->hora_inicio?->format('H:i'),

                'hora_termino' =>
                $junta->hora_termino?->format('H:i'),

                'estatus' =>
                SalaJuntasService::estatusVisible($junta->estatus),

                'personal' =>
                self::personalDetalle((string) $junta->personalA),

                'esCreador' => $esCreador,

                'solicitud' => $solicitud,

                'tieneSolicitudPendiente' => $pendiente,

                /*
                 * Solo quien creo la junta puede editarla o cancelarla.
                 * Quien no, debe registrar una solicitud de uso.
                 */
                'puedeEditar'   => $esCreador
                    && $esActiva
                    && ! $pendiente
                    && SalaJuntasService::puede('editar'),

                'puedeEliminar' => $esCreador
                    && $esActiva
                    && ! $pendiente
                    && SalaJuntasService::puede('eliminar'),

                'puedeSolicitar' => ! $esCreador
                    && $esActiva
                    && ! $pendiente,

                'puedeResolverSolicitud' => $esCreador && $pendiente,

                'puedeComentar' => $esActiva && ! $pendiente,

                'comentarios' =>
                SalaJuntasService::comentarios((int) $junta->id, (int) $this->userId()),
            ],
        ]);
    }

    //----------------------------------------------------------------------
    // ALTA / EDICION / BAJA
    //----------------------------------------------------------------------

    /**
     * Catalogos del formulario: convocantes, departamentos y personal.
     */
    public function nuevo(): void
    {
        if (!$this->exige('crear')) {
            return;
        }

        JsonResponse::custom(
            SalaJuntasService::catalogoFormulario((int)$this->userId())
        );
    }

    /** Horas de inicio libres para la fecha elegida. */
    public function horas(): void
    {
        /*
         * Se permite con permiso de crear o de editar: al editar tambien
         * hay que consultar la disponibilidad del dia.
         */
        if (!SalaJuntasService::puede('crear') && !SalaJuntasService::puede('editar')) {
            JsonResponse::forbidden('No tienes permiso para consultar horarios.');
            return;
        }

        $fecha = (string)Request::input('fecha');

        if ($fecha === '') {
            JsonResponse::custom(['horas' => []]);
            return;
        }

        /*
         * Al editar se excluye la junta propia: si no, su horario actual
         * aparece como ocupado y el select de hora de inicio queda vacio,
         * ademas de permitir elegir horas que si estan ocupadas.
         */
        $excluir = (int)Request::input('id', 0);

        JsonResponse::custom([
            'horas' => SalaJuntasService::horasDisponibles($fecha, $excluir),
        ]);
    }

    /** Datos de una junta para cargar el formulario de edicion. */
    public function editable(int $id): void
    {
        if (!$this->exige('editar')) {
            return;
        }

        $junta = Junta::find($id);

        if (!$junta) {
            JsonResponse::error('La junta no existe.', 404);
            return;
        }

        if ($junta->estatus === SalaJuntasService::ESTATUS_CANCELADA) {
            JsonResponse::error('No se puede modificar una junta cancelada.');
            return;
        }

        if (!SalaJuntasService::esCreador($junta, (int)$this->userId())) {
            JsonResponse::error('Solo quien creÃ³ la junta puede modificarla.');
            return;
        }

        $catalogo = SalaJuntasService::catalogoFormulario((int)$this->userId());
        $personal = array_values(array_filter(array_map(
            'intval',
            explode(',', (string)$junta->personalA)
        )));

        JsonResponse::custom([
            'success' => true,
            'datos'   => [
                'id'           => (int)$junta->id,
                'descripcion'  => $junta->descripcion,
                'idUsuario'    => (string)$junta->idUsuario,
                'fecha'        => $junta->fecha?->format('Y-m-d'),
                'hora_inicio'  => $junta->hora_inicio?->format('H:i'),
                'hora_termino' => $junta->hora_termino?->format('H:i'),
                // Los invitados guardados se devuelven como personal individual.
                'personal'     => $personal,
                'departamentos' => [],
            ],
            'catalogo' => $catalogo,
            'horas'    => SalaJuntasService::horasDisponibles(
                (string)$junta->fecha?->format('Y-m-d'),
                (int)$junta->id
            ),
        ]);
    }

    public function crear(): void
    {
        if (!$this->exige('crear')) {
            return;
        }

        JsonResponse::custom(
            SalaJuntasService::crear($this->datosFormulario(), (int)$this->userId())
        );
    }

    public function actualizar(int $id): void
    {
        if (!$this->exige('editar')) {
            return;
        }

        JsonResponse::custom(
            SalaJuntasService::actualizar($id, $this->datosFormulario(), (int)$this->userId())
        );
    }

    public function eliminar(int $id): void
    {
        if (!$this->exige('eliminar')) {
            return;
        }

        JsonResponse::custom(
            SalaJuntasService::eliminar($id, (int)$this->userId())
        );
    }

    //----------------------------------------------------------------------
    // SOLICITUD DE USO DE LA SALA DE JUNTAS
    //----------------------------------------------------------------------

    /** Catalogos (reducidos) para solicitar el horario de una junta ajena. */
    public function nuevoSolicitud(int $id): void
    {
        if (!$this->exige('leer')) {
            return;
        }

        $junta = Junta::with([
            'usuario:id,nombre,id_puesto',
            'usuario.puesto:id,tipo_puesto',
        ])->find($id);

        if (!$junta) {
            JsonResponse::error('La junta no existe.', 404);
            return;
        }

        if (SalaJuntasService::esCreador($junta, (int)$this->userId())) {
            JsonResponse::error(
                'Esta junta es tuya. EdÃ­tala o cancÃ©lala directamente.'
            );
            return;
        }

        JsonResponse::custom([
            'success'  => true,
            'junta'    => [
                'id'           => (int)$junta->id,
                'descripcion'  => $junta->descripcion,
                'fecha'        => $junta->fecha?->format('Y-m-d'),
                'fecha_label'  => formatearFecha($junta->fecha),
                'hora_inicio'  => $junta->hora_inicio?->format('H:i'),
                'hora_termino' => $junta->hora_termino?->format('H:i'),
                'convoca'      => $junta->usuario?->nombre ?? 'S/I',
            ],
            'catalogo' => SalaJuntasService::catalogoSolicitud((int)$this->userId()),
        ]);
    }

    /** Registra la solicitud de horario. */
    public function crearSolicitud(int $id): void
    {        if (!$this->exige('leer')) {
            return;
        }

        /*
         * Request::jsonInput() exige una clave; para leer el cuerpo completo
         * se usa Request::json() con respaldo en el form data.
         */
        $datos = Request::json();

        if (!is_array($datos) || !$datos) {
            $datos = Request::all();
        }

        JsonResponse::custom(
            SalaJuntasService::solicitarHorario(
                $id,
                is_array($datos) ? $datos : [],
                (int)$this->userId()
            )
        );
    }

    /** Datos de la solicitud pendiente para que el creador la resuelva. */
    public function verSolicitud(int $id): void
    {
        if (!$this->exige('leer')) {
            return;
        }

        $junta = Junta::find($id);

        if (!$junta) {
            JsonResponse::error('La junta no existe.', 404);
            return;
        }

        if (!SalaJuntasService::esCreador($junta, (int)$this->userId())) {
            JsonResponse::error('Solo quien creÃ³ la junta puede revisar la solicitud.');
            return;
        }

        $solicitud = SalaJuntasService::solicitudPendiente($id);

        if ($solicitud === null) {
            JsonResponse::error('No hay ninguna solicitud pendiente.');
            return;
        }

        JsonResponse::custom([
            'success'   => true,
            'solicitud' => $solicitud,

        /*
         * Horarios libres de esa fecha para que el creador elija a donde
         * mover SU reunion. NO se excluye la junta: su propio horario debe
         * seguir viendose ocupado, asi no se puede mover al mismo lugar.
         */
        'junta'     => [
            'fecha'       => $junta->fecha?->format('Y-m-d'),
            'fecha_label' => formatearFecha($junta->fecha),
            'hora_inicio' => $junta->hora_inicio?->format('H:i'),
            'hora_termino'=> $junta->hora_termino?->format('H:i'),
        ],
        'horas'     => SalaJuntasService::horasDisponibles(
            (string)$solicitud['fecha']
        ),
    ]);
    }

    /** El creador acepta o deniega la solicitud. */
    public function responderSolicitud(int $id): void
    {
        if (!$this->exige('leer')) {
            return;
        }

        $datos = Request::json();

        if (!is_array($datos) || !$datos) {
            $datos = Request::all();
        }

        JsonResponse::custom(
            SalaJuntasService::responderSolicitud(
                $id,
                !empty($datos['aceptar']),
                (int)$this->userId(),
                [
                    'fecha'        => $datos['fecha'] ?? '',
                    'hora_inicio'  => $datos['hora_inicio'] ?? '',
                    'hora_termino' => $datos['hora_termino'] ?? '',
                ]
            )
        );
    }

    //----------------------------------------------------------------------
    // COMENTARIOS
    //----------------------------------------------------------------------

    public function comentarios(int $id): void
    {
        if (!$this->exige('leer')) {
            return;
        }

        if (!Junta::where('id', $id)->exists()) {
            JsonResponse::notFound('La junta no existe.');
            return;
        }

        JsonResponse::custom([
            'success' => true,
            'data'    => SalaJuntasService::comentarios($id, (int)$this->userId()),
        ]);
    }

    public function crearComentario(int $id): void
    {
        if (!$this->exige('editar')) {
            return;
        }

        JsonResponse::custom(
            SalaJuntasService::crearComentario(
                $id,
                (string)Request::input('comentario'),
                (int)$this->userId()
            )
        );
    }

    //----------------------------------------------------------------------
    // SOPORTE
    //----------------------------------------------------------------------

    /**
     * Valida el permiso en el backend.
     * Ocultar botones en la vista no protege las consultas.
     */
    private function exige(string $accion): bool
    {
        if (SalaJuntasService::puede($accion)) {
            return true;
        }

        JsonResponse::forbidden('No tienes permiso para ' . $accion . ' en Sala de Juntas.');

        return false;
    }

    /** Normaliza el payload del formulario. */
    private function datosFormulario(): array
    {
        /*
         * Request::jsonInput() exige una clave y lanzaba ArgumentCountError,
         * por lo que el alta y la edicion nunca llegaban al Service.
         */
        $datos = Request::json();

        if (!is_array($datos) || !$datos) {
            $datos = Request::all();
        }

        return [
            'descripcion'  => $datos['descripcion'] ?? '',
            'idUsuario'    => (int)($datos['idUsuario'] ?? 0),
            'id_puesto'    => (int)($datos['id_puesto'] ?? 0),
            'fecha'        => $datos['fecha'] ?? '',
            'hora_inicio'  => $datos['hora_inicio'] ?? '',
            'hora_termino' => $datos['hora_termino'] ?? '',
            'departamentos' => array_values((array)($datos['departamentos'] ?? [])),
            'personal'     => array_values((array)($datos['personal'] ?? [])),
        ];
    }

    /** Nombres de los invitados, para el modal de detalle. */
    private static function personalDetalle(string $personalA): array
    {
        $ids = array_values(array_filter(array_map('intval', explode(',', $personalA))));

        if (empty($ids)) {
            return [];
        }

        $usuarios = \App\Models\Usuario::query()
            ->select([
                'tb_usuarios.id',
                'tb_usuarios.nombre',
                'tb_usuarios.id_puesto',
                'tb_puestos.tipo_puesto',
                'tb_estaciones.nombre as estacion',
            ])
            ->join('tb_puestos', 'tb_puestos.id', '=', 'tb_usuarios.id_puesto')
            ->join('tb_estaciones', 'tb_estaciones.id', '=', 'tb_usuarios.id_gas')
            ->whereIn('tb_usuarios.id', $ids)
            ->orderBy('tb_puestos.tipo_puesto')
            ->orderBy('tb_estaciones.nombre')
            ->orderBy('tb_usuarios.nombre')
            ->get();

        /*
         * Agrupacion identica al legacy: el puesto 6 (Encargado) se separa
         * por estacion y el resto de puestos se agrupa en una sola lista.
         */
        $porPuesto = [];

        foreach ($usuarios as $u) {
            $esEncargado = strtolower((string) $u->tipo_puesto) === 'encargado';

            if ($esEncargado) {
                $clave = 'Encargado|' . $u->estacion;
                $porPuesto[$clave]['puesto'] = 'Encargado';
                $porPuesto[$clave]['estacion'] = $u->estacion;
            } else {
                $clave = $u->tipo_puesto;
                $porPuesto[$clave]['puesto'] = $u->tipo_puesto;
                $porPuesto[$clave]['estacion'] = null;
            }

            $porPuesto[$clave]['usuarios'][] = [
                'id' => (int) $u->id,
                'nombre' => $u->nombre,
            ];
        }

        return array_values($porPuesto);
    }
}
