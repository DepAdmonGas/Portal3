<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Operativo\RhPersonal;
use App\Models\Operativo\RhPersonalHorarioProgramar;
use App\Models\Operativo\RhPersonalHorarioProgramarDetalle;
use App\Models\Operativo\RhLocalidadesHorario;
use App\Models\Operativo\RhLocalidad;
use App\Models\Operativo\RhPuestos;

class ProgramarHorarioService
{
    public const MODULE_KEY = 'programar-horario';

    public const ESTACIONES_SOPORTADAS = [1, 2, 3, 4, 5, 6, 7, 14];

    public const DEPTO_AUTOLAVADO = 9;

    public const EXCLUIDOS = [387, 358, 296, 326, 300, 335];

    public const DIAS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    public static function getPermisos(): array
    {
        $usuario = Auth::user();
        $sessionUsuario = Session::get('usuario');
        $idUsuario = (int)($sessionUsuario['id'] ?? 0);
        $idPuesto = (int)($usuario->id_puesto ?? 0);
        $idEstacion = (int)($sessionUsuario['id_estacion'] ?? 0);
        $multiestacion = !empty($sessionUsuario['multiestacion']);
        $nombrePuesto = $usuario->puesto->tipo_puesto ?? '';

        $permisosDb = ModuloDptoOperativoService::permisosSesion('recursos-humanos');

        return [
            'id_usuario'     => $idUsuario,
            'id_estacion'    => $idEstacion,
            'id_puesto'      => $idPuesto,
            'nombre_puesto'  => $nombrePuesto,
            'multiestacion'  => $multiestacion,
            'puedeCrear'     => !empty($permisosDb['agregar']),
            'puedeEditar'    => !empty($permisosDb['editar']),
            'puedeEliminar'  => !empty($permisosDb['eliminar']),
            'puedeDescargar' => !empty($permisosDb['descargar']),
        ];
    }

    public static function getContexto(): array
    {
        $ctx = ModuleStationService::getContext(self::MODULE_KEY);
        $idEstacion = $ctx['id_estacion'];
        $idDepto = $ctx['id_depto'];

        if (self::esTodasPaloSolo()) {
            $idEstacion = null;
            $idDepto = null;
        }

        $tipo = 'todas';
        if ($idEstacion && !$idDepto) {
            $tipo = 'estacion';
        } elseif ($idDepto) {
            $tipo = 'depto';
        }

        return [
            'id_estacion' => $idEstacion,
            'id_depto'    => $idDepto,
            'tipo'        => $tipo,
            'nombre'      => $ctx['nombre'],
        ];
    }

    private static function esTodasPaloSolo(): bool
    {
        $sessionUsuario = Session::get('usuario');
        $idGas = $sessionUsuario['id_estacion'] ?? null;
        if ($idGas === null) {
            $usuario = Auth::user();
            $idGas = $usuario ? $usuario->id_gas : null;
        }
        if ((int)$idGas !== 2) {
            return false;
        }

        if (MultiestacionService::isEnabled()) {
            return false;
        }

        $contexto = Session::get('module_context') ?? [];
        if (!array_key_exists(self::MODULE_KEY, $contexto)) {
            return false;
        }

        $raw = $contexto[self::MODULE_KEY];

        return empty($raw['id_estacion']) && empty($raw['id_depto']);
    }

    public static function getCatalogoTurnos(int $idEstacion): array
    {
        $idCatalogo = $idEstacion === self::DEPTO_AUTOLAVADO ? 2 : $idEstacion;

        return RhLocalidadesHorario::where('id_estacion', $idCatalogo)
            ->orderBy('id')
            ->get(['titulo', 'hora_entrada', 'hora_salida'])
            ->toArray();
    }

    private static function getIdsPermitidos(): array
    {
        $ids = [];

        foreach (ModuleStationService::getAvailableStations(self::MODULE_KEY) as $s) {
            $ids[] = (int)$s['id'];
        }
        foreach (ModuleStationService::getAvailableDepartments(self::MODULE_KEY) as $d) {
            $ids[] = (int)$d['id'];
        }

        return array_values(array_unique($ids));
    }

    public static function crearReporteInicial(int $idEstacion): int
    {
        $reporte = RhPersonalHorarioProgramar::create([
            'id_estacion' => $idEstacion,
            'fecha'       => date('Y-m-d'),
            'estado'      => 0,
        ]);
        return (int)$reporte->id;
    }

    public static function actualizarFecha(int $idReporte, string $fecha): void
    {
        $reporte = RhPersonalHorarioProgramar::find($idReporte);
        if (!$reporte) {
            throw new \InvalidArgumentException('El reporte no existe.');
        }

        if ((int)$reporte->estado !== 0) {
            throw new \InvalidArgumentException('No se puede modificar la fecha de un registro finalizado.');
        }

        $fechaLimpia = date('Y-m-d', strtotime($fecha));
        if (!$fechaLimpia || $fechaLimpia === '1970-01-01') {
            throw new \InvalidArgumentException('La fecha proporcionada no es válida.');
        }

        RhPersonalHorarioProgramar::where('id', $idReporte)->update(['fecha' => $fechaLimpia]);
    }

    public static function getReportes(): array
    {
        $ctx = self::getContexto();

        $query = RhPersonalHorarioProgramar::query();

        if ($ctx['tipo'] === 'estacion') {
            $stationId = $ctx['id_estacion'];
            $query->where(function ($q) use ($stationId) {
                $q->whereIn('id', function ($sub) use ($stationId) {
                    $sub->select('id_reporte')
                        ->from('op_rh_personal_horario_programar_detalle')
                        ->where('id_estacion', $stationId)
                        ->groupBy('id_reporte');
                })->orWhere('id_estacion', $stationId);
            });
        } elseif ($ctx['tipo'] === 'depto') {
            $deptId = $ctx['id_depto'];
            $query->whereIn('id', function ($sub) use ($deptId) {
                $sub->select('id_reporte')
                    ->from('op_rh_personal_horario_programar_detalle')
                    ->where('id_estacion', $deptId)
                    ->groupBy('id_reporte');
            });
        } else {
            $ids = self::getIdsPermitidos();
            if (!empty($ids)) {
                $query->whereIn('id_estacion', $ids);
            }
        }

        $reportes = $query->orderBy('id', 'desc')->get();

        $rows = [];
        foreach ($reportes as $r) {
            $nombreEstacion = self::resolveNombreEstacion((int)$r->id_estacion);
            $rawFecha = $r->getRawOriginal('fecha');
            if ($rawFecha instanceof \Carbon\Carbon) {
                $rawFecha = $rawFecha->format('Y-m-d');
            }
            $rows[] = [
                'id'                => (int)$r->id,
                'id_estacion'       => (int)$r->id_estacion,
                'nombre_estacion'   => $nombreEstacion,
                'fecha'             => self::formatFechaSafe($rawFecha),
                'estado'            => (int)$r->estado,
                'estado_texto'      => $r->estado == 1 ? 'Finalizado' : 'Pendiente',
            ];
        }

        return [
            'rows'     => $rows,
            'contexto' => $ctx,
        ];
    }

    public static function resolverIdEstacionContexto(): int
    {
        $ctx = self::getContexto();
        if ($ctx['tipo'] === 'estacion') {
            return (int)$ctx['id_estacion'];
        }
        if ($ctx['tipo'] === 'depto') {
            return (int)$ctx['id_depto'];
        }
        $sessionUsuario = Session::get('usuario');
        return (int)($sessionUsuario['id_estacion'] ?? 0);
    }

    public static function getDetalle(int $idReporte): array
    {
        $ctx = self::getContexto();
        $stationIds = [];
        if ($ctx['tipo'] === 'estacion') {
            $stationIds = [$ctx['id_estacion']];
        } elseif ($ctx['tipo'] === 'depto') {
            $stationIds = [$ctx['id_depto']];
        } else {
            $stationIds = self::getIdsPermitidos();
        }

        $idEstacionCtx = self::resolverIdEstacionContexto();

        if ($idReporte > 0) {
            $reporte = RhPersonalHorarioProgramar::find($idReporte);
            if (!$reporte) {
                throw new \InvalidArgumentException('El reporte no existe.');
            }
            $idEstacionReporte = (int)$reporte->id_estacion;
            if (empty($stationIds)) {
                $stationIds = [$idEstacionReporte];
            }
            $detalles = [];
            foreach (RhPersonalHorarioProgramarDetalle::where('id_reporte', $idReporte)->get() as $d) {
                $detalles[(int)$d->id_personal][$d->dia] = $d;
            }
            $rawFecha = $reporte->getRawOriginal('fecha');
            if ($rawFecha instanceof \Carbon\Carbon) {
                $rawFecha = $rawFecha->format('Y-m-d');
            } else {
                $rawFecha = (string)$rawFecha;
            }
            if ($rawFecha === '' || $rawFecha === '0000-00-00' || $rawFecha === '0000-00-00 00:00:00') {
                $rawFecha = '';
            }
            $reporteData = [
                'id'            => (int)$reporte->id,
                'id_estacion'   => $idEstacionReporte,
                'fecha'         => self::formatFechaSafe($rawFecha),
                'fecha_raw'     => $rawFecha,
                'estado'        => (int)$reporte->estado,
            ];
        } else {
            if (empty($stationIds)) {
                $stationIds = [$idEstacionCtx];
            }
            $detalles = [];
            $reporteData = [
                'id'            => 0,
                'id_estacion'   => $idEstacionCtx,
                'fecha'         => '',
                'fecha_raw'     => '',
                'estado'        => 0,
            ];
        }

        $personal = RhPersonal::where('estado', 1)
            ->whereIn('id_estacion', $stationIds)
            ->whereNotIn('id', self::EXCLUIDOS)
            ->orderBy('id_estacion', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $rows = [];
        foreach ($personal as $p) {
            $puesto = RhPuestos::find($p->puesto);
            $turnos = self::getCatalogoTurnos((int)$p->id_estacion);

            $row = [
                'id'               => (int)$p->id,
                'id_estacion'      => (int)$p->id_estacion,
                'nombre_estacion'  => self::resolveNombreEstacion((int)$p->id_estacion),
                'nombre_completo'  => $p->nombre_completo,
                'puesto'           => $puesto ? $puesto->puesto : '',
                'turnos'           => $turnos,
            ];

            foreach (self::DIAS as $num => $nombreDia) {
                $row[self::keyDia($num)] = self::datosDetalle($detalles[$p->id][$nombreDia] ?? null);
            }

            $rows[] = $row;
        }

        return [
            'reporte' => $reporteData,
            'rows'    => $rows,
        ];
    }

    public static function editarTurno(int $idReporte, int $idPersonal, int $dia, string $horario, int $idEstacion): array
    {
        if (!isset(self::DIAS[$dia])) {
            throw new \InvalidArgumentException('Día no válido.');
        }

        if ($idReporte > 0) {
            $reporte = RhPersonalHorarioProgramar::find($idReporte);
            if (!$reporte) {
                throw new \InvalidArgumentException('El reporte no existe.');
            }
            if ((int)$reporte->estado !== 0) {
                throw new \InvalidArgumentException('No se puede modificar un registro con estatus Finalizado.');
            }
        }

        $personal = RhPersonal::find($idPersonal);
        if (!$personal) {
            throw new \InvalidArgumentException('El personal no existe.');
        }

        $idEstacionConsulta = (int)$personal->id_estacion;
        $idEstacionHorario = $idEstacionConsulta === self::DEPTO_AUTOLAVADO ? 2 : $idEstacionConsulta;

        $nomDia = self::DIAS[$dia];
        $horaEntrada = '00:00:00';
        $horaSalida = '00:00:00';

        if ($horario === '') {
            $registro = RhPersonalHorarioProgramarDetalle::where('id_reporte', $idReporte)
                ->where('id_personal', $idPersonal)
                ->where('dia', $nomDia)
                ->first();
            if ($registro) {
                $registro->delete();
            }
            return [
                'horario'      => '',
                'hora_entrada' => '',
                'hora_salida'  => '',
                'formateado'   => 'Sin asignar',
            ];
        }

        if ($horario !== 'Descanso') {
            $turno = RhLocalidadesHorario::where('id_estacion', $idEstacionHorario)
                ->where('titulo', $horario)
                ->first();

            if (!$turno) {
                throw new \InvalidArgumentException('El turno seleccionado no existe en el catálogo.');
            }

            $horaEntrada = (string)$turno->hora_entrada;
            $horaSalida = (string)$turno->hora_salida;
        }

        $registro = RhPersonalHorarioProgramarDetalle::where('id_reporte', $idReporte)
            ->where('id_personal', $idPersonal)
            ->where('dia', $nomDia)
            ->first();

        if ($registro) {
            $registro->update([
                'horario'      => $horario,
                'hora_entrada' => $horaEntrada,
                'hora_salida'  => $horaSalida,
            ]);
        } else {
            RhPersonalHorarioProgramarDetalle::create([
                'id_reporte'   => $idReporte,
                'id_estacion'  => $idEstacion,
                'id_personal'  => $idPersonal,
                'horario'      => $horario,
                'dia'          => $nomDia,
                'hora_entrada' => $horaEntrada,
                'hora_salida'  => $horaSalida,
            ]);
        }

        return [
            'horario'      => $horario,
            'hora_entrada' => $horaEntrada,
            'hora_salida'  => $horaSalida,
            'formateado'   => self::formatearHorarioDia($horaEntrada, $horaSalida),
        ];
    }

    public static function guardarReporte(int $idReporte, string $fecha, int $idEstacion = 0, array $detalles = []): int
    {
        if ($idReporte > 0) {
            $reporte = RhPersonalHorarioProgramar::find($idReporte);
            if (!$reporte) {
                throw new \InvalidArgumentException('El reporte no existe.');
            }
            if ((int)$reporte->estado !== 0) {
                throw new \InvalidArgumentException('No se puede modificar un registro con estatus Finalizado.');
            }
            $reporte->update([
                'fecha'  => $fecha,
                'estado' => 1,
            ]);
            $idEstacionNotif = (int)$reporte->id_estacion;
        } else {
            if (empty($detalles)) {
                throw new \InvalidArgumentException('Debe programar al menos un turno antes de guardar.');
            }
            $reporte = RhPersonalHorarioProgramar::create([
                'id_estacion' => $idEstacion,
                'fecha'       => $fecha,
                'estado'      => 1,
            ]);
            $idEstacionNotif = $idEstacion;
            self::guardarDetallesBulk((int)$reporte->id, $idEstacion, $detalles);
        }

        self::notificarTelegram('guardar', [
            'id_estacion' => $idEstacionNotif,
            'fecha'       => $fecha,
        ]);

        return (int)$reporte->id;
    }

    public static function guardarDetallesBulk(int $idReporte, int $idEstacion, array $detalles): void
    {
        foreach ($detalles as $d) {
            $idPersonal = (int)($d['id_personal'] ?? 0);
            $dia = (int)($d['dia'] ?? 0);
            $horario = trim((string)($d['horario'] ?? ''));

            if (!$idPersonal || !$dia || $horario === '' || !isset(self::DIAS[$dia])) {
                continue;
            }

            $personal = RhPersonal::find($idPersonal);
            if (!$personal) {
                continue;
            }

            $idEstacionConsulta = (int)$personal->id_estacion;
            $idEstacionHorario = $idEstacionConsulta === self::DEPTO_AUTOLAVADO ? 2 : $idEstacionConsulta;

            $nomDia = self::DIAS[$dia];
            $horaEntrada = '00:00:00';
            $horaSalida = '00:00:00';

            if ($horario !== 'Descanso') {
                $turno = RhLocalidadesHorario::where('id_estacion', $idEstacionHorario)
                    ->where('titulo', $horario)
                    ->first();

                if ($turno) {
                    $horaEntrada = (string)$turno->hora_entrada;
                    $horaSalida = (string)$turno->hora_salida;
                }
            }

            RhPersonalHorarioProgramarDetalle::create([
                'id_reporte'   => $idReporte,
                'id_estacion'  => $idEstacion,
                'id_personal'  => $idPersonal,
                'horario'      => $horario,
                'dia'          => $nomDia,
                'hora_entrada' => $horaEntrada,
                'hora_salida'  => $horaSalida,
            ]);
        }
    }

    public static function eliminarReporte(int $idReporte): void
    {
        $reporte = RhPersonalHorarioProgramar::find($idReporte);
        if (!$reporte) {
            throw new \InvalidArgumentException('El reporte no existe.');
        }

        RhPersonalHorarioProgramarDetalle::where('id_reporte', $idReporte)->delete();

        $reporte->delete();

        self::notificarTelegram('eliminar', [
            'id_estacion' => (int)$reporte->id_estacion,
            'fecha'       => (string)$reporte->getRawOriginal('fecha'),
        ]);
    }

    public static function validarEdicion(int $idReporte): void
    {
        $reporte = RhPersonalHorarioProgramar::find($idReporte);
        if (!$reporte) {
            throw new \InvalidArgumentException('El reporte no existe.');
        }

        if ((int)$reporte->estado !== 0) {
            throw new \InvalidArgumentException('No se puede modificar un registro con estatus Finalizado.');
        }
    }

    public static function validarEliminar(int $idReporte): void
    {
        $reporte = RhPersonalHorarioProgramar::find($idReporte);
        if (!$reporte) {
            throw new \InvalidArgumentException('El reporte no existe.');
        }

        if ((int)$reporte->estado !== 0) {
            throw new \InvalidArgumentException('No se puede eliminar un registro con estatus Finalizado.');
        }
    }

    public static function resolveNombreEstacion(int $idEstacion): string
    {
        $loc = RhLocalidad::find($idEstacion);
        if ($loc) {
            return $loc->localidad;
        }

        $est = Estacion::find($idEstacion);
        if ($est) {
            return $est->nombre;
        }

        return '';
    }

    public static function formatearHorarioDia(string $horaEntrada, string $horaSalida): string
    {
        if (self::esHoraCero($horaEntrada) && self::esHoraCero($horaSalida)) {
            return 'Descanso';
        }

        $entrada = date('g:i a', strtotime($horaEntrada));
        $salida = date('g:i a', strtotime($horaSalida));

        return $entrada . ' a ' . $salida;
    }

    private static function formatFechaSafe($fecha): string
    {
        if ($fecha instanceof \Carbon\Carbon) {
            $fecha = $fecha->format('Y-m-d');
        }

        if (!$fecha) {
            return 'Sin informacion';
        }

        $raw = (string)$fecha;
        if ($raw === '' || $raw === '0000-00-00' || $raw === '0000-00-00 00:00:00') {
            return 'Sin informacion';
        }

        $ts = strtotime($raw);
        if ($ts === false || $ts === -1) {
            return 'Sin informacion';
        }

        return formatearFecha($raw);
    }

    private static function esHoraCero(string $hora): bool
    {
        if ($hora === '') {
            return true;
        }

        if ($hora === '00:00' || $hora === '00:00:00') {
            return true;
        }

        $t = strtotime($hora);

        return $t !== false && date('H:i', $t) === '00:00';
    }

    private static function keyDia(int $num): string
    {
        return [
            1 => 'lunes',
            2 => 'martes',
            3 => 'miercoles',
            4 => 'jueves',
            5 => 'viernes',
            6 => 'sabado',
            7 => 'domingo',
        ][$num];
    }

    private static function datosDetalle(?RhPersonalHorarioProgramarDetalle $d): ?array
    {
        if (!$d) {
            return null;
        }

        $horaEntrada = (string)$d->getRawOriginal('hora_entrada');
        $horaSalida = (string)$d->getRawOriginal('hora_salida');

        return [
            'horario'      => $d->horario,
            'hora_entrada' => $horaEntrada,
            'hora_salida'  => $horaSalida,
            'formateado'   => self::formatearHorarioDia($horaEntrada, $horaSalida),
        ];
    }

    private static function notificarTelegram(string $accion, array $params): void
    {
        try {
            $sessionUsuario = Session::get('usuario');
            $idUsuario = (int)($sessionUsuario['id'] ?? 0);

            $usuario = Usuario::find($idUsuario);
            $nombreUsuario = $usuario ? $usuario->nombre : 'Desconocido';

            $nombreEstacion = self::resolveNombreEstacion((int)$params['id_estacion']);

            switch ($accion) {
                case 'guardar':
                    $icono = '✅';
                    $accionTexto = 'programó un nuevo horario correspondiente al día '
                        . formatearFecha($params['fecha'])
                        . ' en el apartado de Recursos Humanos';
                    break;
                case 'eliminar':
                    $icono = '🗑';
                    $accionTexto = 'eliminó el horario correspondiente al día '
                        . formatearFecha($params['fecha'] ?? '')
                        . ' en el apartado de Recursos Humanos';
                    break;
                default:
                    return;
            }

            $detalle = $icono . ' ' . $nombreUsuario . ' ' . $accionTexto . '.'
                . PHP_EOL . PHP_EOL
                . '⛽ Estación: ' . $nombreEstacion . '.';

            TelegramService::notificar((int)$params['id_estacion'], $idUsuario, $detalle);
        } catch (\Throwable $e) {
            error_log('Error en ProgramarHorarioService::notificarTelegram: ' . $e->getMessage());
        }
    }
}
