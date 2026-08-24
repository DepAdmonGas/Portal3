<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Operativo\RhDiaDobleRegistro;
use App\Models\Operativo\RhDiaDoblePersonal;
use App\Models\Operativo\RhDiaDobleComentarios;
use App\Models\Operativo\RhFormatosToken;
use App\Models\Operativo\RhDiasDoblesFirma;
use App\Models\Operativo\RhPersonal;
use App\Models\Operativo\RhPersonalAsistencia;
use App\Models\Operativo\RhListaIncidencias;
use App\Models\Operativo\RhDiasDobles;
use App\Models\Operativo\RhLocalidad;
use App\Models\Operativo\RhPuestos;
use App\Models\Usuario;
use App\Services\ModuloDptoOperativoService;
use App\Services\IncidenciasNominaService;
use App\Services\TelegramService;
use App\Services\EmailService;
use Carbon\Carbon;

class DiaDobleService
{
    public const MODULE_KEY = 'incidencias-nomina';

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
            'puedeCrear'     => !empty($permisosDb['crear']),
            'puedeEditar'    => !empty($permisosDb['editar']),
            'puedeEliminar'  => !empty($permisosDb['eliminar']),
            'puedeDescargar' => !empty($permisosDb['descargar']),
        ];
    }

    public static function getQuincenas(int $year): array
    {
        $quincenas = [];
        $num = 1;

        for ($mes = 1; $mes <= 12; $mes++) {
            $primerDia = mktime(0, 0, 0, $mes, 1, $year);
            $ultimoDia = mktime(0, 0, 0, $mes + 1, 0, $year);

            $quincenas[] = [
                'numero' => $num++,
                'label'  => 'Quincena ' . ($num - 1) . ': del ' . date('d-m-Y', $primerDia) . ' al 15-' . date('m-Y', $primerDia),
                'inicio' => date('Y-m-d', $primerDia),
                'fin'    => date('Y-m-d', mktime(0, 0, 0, $mes, 15, $year)),
            ];

            $quincenas[] = [
                'numero' => $num++,
                'label'  => 'Quincena ' . ($num - 1) . ': del 16-' . date('m-Y', $primerDia) . ' al ' . date('d-m-Y', $ultimoDia),
                'inicio' => date('Y-m-d', mktime(0, 0, 0, $mes, 16, $year)),
                'fin'    => date('Y-m-d', $ultimoDia),
            ];
        }

        return $quincenas;
    }

    public static function getQuincenaRange(int $year, int $quincena): array
    {
        if ($quincena < 1 || $quincena > 24) {
            return ['inicio' => '', 'fin' => ''];
        }

        $mes = (int) ceil($quincena / 2);
        $esSegunda = ($quincena % 2 === 0);

        if ($esSegunda) {
            $inicio = mktime(0, 0, 0, $mes, 16, $year);
            $fin = mktime(0, 0, 0, $mes + 1, 0, $year);
        } else {
            $inicio = mktime(0, 0, 0, $mes, 1, $year);
            $fin = mktime(0, 0, 0, $mes, 15, $year);
        }

        return [
            'inicio' => date('Y-m-d', $inicio),
            'fin'    => date('Y-m-d', $fin),
        ];
    }

    public static function getCurrentQuincena(): int
    {
        $numeroDiaAnio = date('z') + 1;
        return (int) ceil($numeroDiaAnio / 15);
    }

    public static function getList(int $year): array
    {
        $registros = RhDiaDobleRegistro::where('year', $year)
            ->orderBy('quincena', 'asc')
            ->get();

        $data = [];

        foreach ($registros as $row) {
            $quincenaRange = self::getQuincenaRange($row->year, $row->quincena);
            $numComentarios = RhDiaDobleComentarios::where('id_reporte', $row->id)->count();
            $numFirmas = RhDiasDoblesFirma::where('id_formato', $row->id)->count();

            $fechaCreacion = $row->fecha_creacion ?? '';
            $explode = explode(' ', $fechaCreacion);
            $fechaStr = $explode[0] ?? '';
            $horaStr = isset($explode[1]) ? date('g:i a', strtotime($explode[1])) : '';
            $fechaFormateada = $fechaStr ? formatearFecha($fechaStr) . ($horaStr ? ', ' . $horaStr : '') : '';

            $data[] = [
                'id'              => $row->id,
                'quincena'        => $row->quincena,
                'status'          => $row->status,
                'status_label'    => self::statusLabel($row->status),
                'fecha_creacion'  => $fechaFormateada,
                'inicio_quincena' => $quincenaRange['inicio'] ? formatearFecha($quincenaRange['inicio']) : 'S/I',
                'fin_quincena'    => $quincenaRange['fin'] ? formatearFecha($quincenaRange['fin']) : 'S/I',
                'comentarios'     => $numComentarios,
                'num_firmas'      => $numFirmas,
            ];
        }

        return $data;
    }

    public static function statusLabel(int $status): string
    {
        return match ($status) {
            0 => 'Pendiente',
            1 => 'En proceso',
            2 => 'En proceso',
            3 => 'Finalizado', 
            default => 'Desconocido',
        };
    }

    public static function getPendienteRecord(int $year): ?array
    {
        $pendiente = RhDiaDobleRegistro::where('year', $year)
            ->where('status', 0)
            ->orderBy('id', 'asc')
            ->first();

        if (!$pendiente) {
            return null;
        }

        return [
            'id'       => (int) $pendiente->id,
            'quincena' => (int) $pendiente->quincena,
            'status'   => (int) $pendiente->status,
        ];
    }

    public static function tieneFirmasCompletas(int $idReporte): bool
    {
        $registro = RhDiaDobleRegistro::find($idReporte);
        if (!$registro) return false;
        return $registro->status === 3;
    }

    public static function agregar(int $year, int $quincena): int
    {
        $registro = RhDiaDobleRegistro::create([
            'year'     => $year,
            'quincena' => $quincena,
            'status'   => 0,
        ]);

        return $registro->id;
    }

    public static function editarQuincena(int $idReporte, int $quincena): bool
    {
        $registro = RhDiaDobleRegistro::find($idReporte);
        if (!$registro) {
            return false;
        }

        $registro->quincena = $quincena;
        return $registro->save();
    }

    public static function eliminar(int $id): bool
    {
        $registro = RhDiaDobleRegistro::find($id);
        if (!$registro) {
            return false;
        }

        RhDiaDoblePersonal::where('id_registro', $id)->delete();
        RhDiaDobleComentarios::where('id_reporte', $id)->delete();

        return $registro->delete();
    }

    public static function getDetail(int $idReporte): ?array
    {
        $registro = RhDiaDobleRegistro::find($idReporte);
        if (!$registro) {
            return null;
        }

        $quincenaRange = self::getQuincenaRange($registro->year, $registro->quincena);

        $personalAsignado = RhDiaDoblePersonal::where('id_registro', $idReporte)
            ->orderBy('id', 'asc')
            ->get();

        $empleados = [];
        foreach ($personalAsignado as $pa) {
            $personal = RhPersonal::find($pa->id_usuario);
            $empleados[] = [
                'id'           => $pa->id,
                'id_usuario'   => $pa->id_usuario,
                'nombre'       => $personal ? $personal->nombre_completo : 'Desconocido',
                'fecha_doble'  => $pa->fecha_doble,
                'fecha_label'  => formatearFecha($pa->fecha_doble),
            ];
        }

        $firmas = self::getFirmas($idReporte);

        return [
            'id'              => $registro->id,
            'year'            => $registro->year,
            'quincena'        => $registro->quincena,
            'status'          => $registro->status,
            'status_label'    => self::statusLabel($registro->status),
            'fecha_creacion'  => $registro->fecha_creacion ?? '',
            'fecha_formateada'=> formatearFechaLarga(date('Y-m-d', strtotime($registro->fecha_creacion ?? date('Y-m-d')))) . ', ' . date('g:i a', strtotime($registro->fecha_creacion ?? 'now')),
            'inicio_quincena' => $quincenaRange['inicio'] ? formatearFecha($quincenaRange['inicio']) : 'S/I',
            'fin_quincena'    => $quincenaRange['fin'] ? formatearFecha($quincenaRange['fin']) : 'S/I',
            'empleados'       => $empleados,
            'firmas'          => $firmas,
        ];
    }

    public static function agregarPersonal(int $idReporte, int $idUsuario, string $fechaDoble): bool
    {
        $registro = RhDiaDobleRegistro::find($idReporte);
        if (!$registro) {
            return false;
        }

        RhDiaDoblePersonal::create([
            'id_registro'  => $idReporte,
            'id_usuario'   => $idUsuario,
            'fecha_doble'  => $fechaDoble,
        ]);

        return true;
    }

    public static function eliminarPersonal(int $id): bool
    {
        return RhDiaDoblePersonal::where('id', $id)->delete() > 0;
    }

    public static function getPersonal(int $year): array
    {
        return RhPersonal::where('estado', 1)
            ->orderBy('nombre_completo', 'asc')
            ->get(['id', 'nombre_completo'])
            ->toArray();
    }

    public static function getComentarios(int $idReporte): array
    {
        $sessionUsuario = Session::get('usuario');
        $idUsuarioActual = (int)($sessionUsuario['id'] ?? 0);

        $comentarios = RhDiaDobleComentarios::where('id_reporte', $idReporte)
            ->orderBy('fecha_hora', 'asc')
            ->with('usuario:id,nombre')
            ->get();

        $data = [];
        foreach ($comentarios as $c) {
            $usuario = $c->usuario;
            $fechaRaw = $c->fecha_hora ? $c->fecha_hora->format('Y-m-d H:i:s') : '';
            $fechaExplode = explode(' ', $fechaRaw);
            $fechaStr = $fechaExplode[0] ?? '';
            $horaStr = isset($fechaExplode[1]) ? date('g:i a', strtotime($fechaExplode[1])) : '';

            $data[] = [
                'id'              => $c->id,
                'id_usuario'      => $c->id_usuario,
                'usuario_nombre'  => $usuario ? $usuario->nombre : 'Usuario',
                'comentario'      => $c->comentario,
                'fecha_formateada' => ($fechaStr ? formatearFecha($fechaStr) : '') . ($horaStr ? ', ' . $horaStr : ''),
                'esMio'           => $c->id_usuario === $idUsuarioActual,
            ];
        }

        return $data;
    }

    public static function agregarComentario(int $idReporte, string $comentario): bool
    {
        $sessionUsuario = Session::get('usuario');
        $idUsuario = (int)($sessionUsuario['id'] ?? 0);

        if (!$idUsuario || empty(trim($comentario))) {
            return false;
        }

        RhDiaDobleComentarios::create([
            'id_reporte' => $idReporte,
            'id_usuario' => $idUsuario,
            'comentario' => trim($comentario),
            'fecha_hora' => Carbon::now(),
        ]);

        return true;
    }

    public static function getEstacionesData(int $year, int $semana): array
    {
        $stationIds = IncidenciasNominaService::ESTACIONES_IDS;
        $excluidos = IncidenciasNominaService::EXCLUIDOS;
        $incidenciasDoble = IncidenciasNominaService::IDIA_DOBLE_INCIDENCIAS;

        $weekRange = IncidenciasNominaService::getWeekDateRange($year, $semana);
        $diasEntre = IncidenciasNominaService::getDiasEntre($weekRange['inicio'], $weekRange['fin']);

        $estaciones = [];

        foreach ($stationIds as $idEstacion) {
            $localidad = RhLocalidad::find($idEstacion);
            $nombreEstacion = $localidad ? $localidad->localidad : 'Estación ' . $idEstacion;

            $personal = RhPersonal::where('id_estacion', $idEstacion)
                ->where('estado', 1)
                ->whereNotIn('id', $excluidos)
                ->whereIn('puesto', [1, 6])
                ->orderBy('nombre_completo', 'asc')
                ->get();

            $empleados = [];

            foreach ($personal as $emp) {
                $diasDobles = [];

                foreach ($diasEntre as $dia) {
                    $resultado = self::validarFechaDoble($emp->id, $dia, $incidenciasDoble);
                    if ($resultado === 'Dia doble') {
                        $diasDobles[] = formatearFecha($dia);
                    }
                }

                if (count($diasDobles) > 0) {
                    $puestoObj = RhPuestos::find($emp->puesto);
                    $puestoNombre = $puestoObj ? $puestoObj->puesto : 'Sin puesto';

                    $empleados[] = [
                        'nombre'        => $emp->nombre_completo,
                        'puesto_nombre' => $puestoNombre,
                        'dias_dobles'   => $diasDobles,
                    ];
                }
            }

            $estaciones[] = [
                'id'        => $idEstacion,
                'nombre'    => $nombreEstacion,
                'empleados' => $empleados,
            ];
        }

        return $estaciones;
    }

    private static function validarFechaDoble(int $idPersonal, string $fecha, array $incidenciasDoble): string
    {
        $asistencia = RhPersonalAsistencia::where('id_personal', $idPersonal)
            ->where('fecha', $fecha)
            ->first();

        if (!$asistencia) {
            return 'S/I';
        }

        $incidenciaId = (int)$asistencia->incidencia;

        if (in_array($incidenciaId, $incidenciasDoble, false)) {
            return 'Dia doble';
        }

        if ($incidenciaId > 0) {
            $listaIncidencia = RhListaIncidencias::find($incidenciaId);
            if ($listaIncidencia) {
                return self::validarDiaDoble($listaIncidencia->detalle, $fecha);
            }
        }

        return 'S/I';
    }

    private static function validarDiaDoble(string $detalle, string $fecha): string
    {
        $fechaDt = new \DateTime($fecha);
        $dia = (int)$fechaDt->format('j');
        $mes = (int)$fechaDt->format('n');
        $yearShort = (int)$fechaDt->format('y');
        $yearFull = (int)$fechaDt->format('Y');

        $holiday = RhDiasDobles::where('dia', $dia)->where('mes', $mes)->first();

        if (!$holiday) {
            return $detalle;
        }

        $descripcion = $holiday->descripcion;

        if ($descripcion === 'Día de la Constitución') {
            $fechaDoble = date('Y-m-d', strtotime("first monday of February $yearShort"));
        } elseif ($descripcion === 'Natalicio de Benito Juárez') {
            $fechaDoble = date('Y-m-d', strtotime("third monday of March $yearShort"));
        } elseif ($descripcion === 'Revolución Mexicana') {
            $fechaDoble = date('Y-m-d', strtotime("third monday of November $yearShort"));
        } else {
            $fechaDoble = date('Y-m-d', mktime(0, 0, 0, $mes, $dia, $yearFull));
        }

        if ($fecha === $fechaDoble) {
            return 'Dia doble';
        }

        return $detalle;
    }

    public static function notificarCreacion(int $idReporte, int $idUsuario): void
    {
        try {
            $sessionUsuario = Session::get('usuario');
            $nombreUsuario = $sessionUsuario['nombre'] ?? 'Usuario';
            $registro = RhDiaDobleRegistro::find($idReporte);
            if (!$registro) return;

$mensaje = '📋 Se ha agregado un nuevo reporte en el apartado de <b>Día Doble</b>, correspondiente al periodo de la <b>'
. $registro->quincena . 'ª Quincena de ' . $registro->year . '</b>:' . PHP_EOL . PHP_EOL
. '👤 <b>Responsable:</b> ' . $nombreUsuario;

            $idEstacion = (int)($sessionUsuario['id_estacion'] ?? 0);
            TelegramService::notificar($idEstacion, $idUsuario, $mensaje);
        } catch (\Throwable $e) {
            error_log('Error Telegram Día Doble creación: ' . $e->getMessage());
        }
    }

    public static function notificarEdicion(int $idReporte, int $idUsuario): void
    {
        try {
            $sessionUsuario = Session::get('usuario');
            $nombreUsuario = $sessionUsuario['nombre'] ?? 'Usuario';

$mensaje = '📋 Se ha actualizado un reporte en el apartado de <b>Día Doble</b>:' . PHP_EOL . PHP_EOL
. '🔢 <b>Reporte #:</b> 00' . $idReporte . PHP_EOL
. '👤 <b>Responsable:</b> ' . $nombreUsuario;

            $idEstacion = (int)($sessionUsuario['id_estacion'] ?? 0);
            TelegramService::notificar($idEstacion, $idUsuario, $mensaje);
        } catch (\Throwable $e) {
            error_log('Error Telegram Día Doble edición: ' . $e->getMessage());
        }
    }

    public static function notificarEliminacion(int $idReporte, int $idUsuario): void
    {
        try {
            $sessionUsuario = Session::get('usuario');
            $nombreUsuario = $sessionUsuario['nombre'] ?? 'Usuario';

$mensaje = '📋 Se ha eliminado un reporte en el apartado de <b>Día Doble</b>:'
. $idReporte . '</b>:' . PHP_EOL . PHP_EOL
. '🔢 <b>Reporte #:</b> 00' . $idReporte . PHP_EOL . PHP_EOL
. '👤 <b>Responsable:</b> ' . $nombreUsuario;

            $idEstacion = (int)($sessionUsuario['id_estacion'] ?? 0);
            TelegramService::notificar($idEstacion, $idUsuario, $mensaje);
        } catch (\Throwable $e) {
            error_log('Error Telegram Día Doble eliminación: ' . $e->getMessage());
        }
    }

    private static function getUrl(): string
    {
        $year = date('Y');
        return '/departamento-operativo/recursos-humanos/dia-doble/' . $year;
    }

    public static function getDireccionDetail(int $idReporte): ?array
    {
        $registro = RhDiaDobleRegistro::find($idReporte);
        if (!$registro) {
            return null;
        }

        $quincenaRange = self::getQuincenaRange($registro->year, $registro->quincena);

        $personalAsignado = RhDiaDoblePersonal::where('id_registro', $idReporte)
            ->orderBy('id_usuario', 'asc')
            ->get();

        $empleados = [];
        foreach ($personalAsignado as $pa) {
            $personal = RhPersonal::find($pa->id_usuario);
            $empleados[] = [
                'id'          => $pa->id,
                'id_usuario'  => $pa->id_usuario,
                'nombre'      => $personal ? $personal->nombre_completo : 'Desconocido',
                'fecha_doble' => $pa->fecha_doble,
                'fecha_label' => formatearFecha($pa->fecha_doble),
            ];
        }

        $firmas = self::getFirmas($idReporte);
        $numComentarios = RhDiaDobleComentarios::where('id_reporte', $idReporte)->count();

        return [
            'id'              => $registro->id,
            'year'            => $registro->year,
            'quincena'        => $registro->quincena,
            'status'          => $registro->status,
            'status_label'    => self::statusLabel($registro->status),
            'fecha_creacion'  => $registro->fecha_creacion ?? '',
            'inicio_quincena' => $quincenaRange['inicio'] ? formatearFecha($quincenaRange['inicio']) : 'S/I',
            'fin_quincena'    => $quincenaRange['fin'] ? formatearFecha($quincenaRange['fin']) : 'S/I',
            'inicio_quincena_raw' => $quincenaRange['inicio'],
            'fin_quincena_raw'    => $quincenaRange['fin'],
            'empleados'       => $empleados,
            'firmas'          => $firmas,
            'comentarios'     => $numComentarios,
        ];
    }

    public static function getFirmas(int $idReporte): array
    {
        $firmas = RhDiasDoblesFirma::where('id_formato', $idReporte)
            ->orderBy('fecha', 'asc')
            ->get();

        $data = [];
        foreach ($firmas as $f) {
            $usuario = Usuario::find($f->id_usuario);
            $fechaRaw = $f->fecha ? $f->fecha->format('Y-m-d H:i:s') : '';
            $fechaExplode = explode(' ', $fechaRaw);
            $fechaStr = $fechaExplode[0] ?? '';
            $horaStr = isset($fechaExplode[1]) ? date('g:i a', strtotime($fechaExplode[1])) : '';

            $tipoLabels = [
                'A' => 'NOMBRE Y FIRMA DE QUIEN ELABORÓ',
                'B' => 'NOMBRE Y FIRMA DE VOBO',
                'C' => 'NOMBRE Y FIRMA DE AUTORIZACIÓN',
            ];

            $firmaImgUrl = null;
            $firmaTexto = null;

            if ($f->tipo_firma === 'A' && !empty($f->firma)) {
                $firmaImgUrl = '/download?tipo=dia-doble-firma&file=' . urlencode($f->firma);
            } elseif (in_array($f->tipo_firma, ['B', 'C']) && !empty($f->firma)) {
                $firmaTexto = '<b>Fecha: ' . formatearFecha($fechaStr) . ', ' . $horaStr . '</b> <br> El reporte de día doble se firmó por un medio electrónico.';
            }

            $data[] = [
                'id'             => $f->id,
                'id_usuario'     => $f->id_usuario,
                'usuario_nombre' => $usuario ? $usuario->nombre : 'Usuario',
                'tipo_firma'     => $f->tipo_firma,
                'tipo_label'     => $tipoLabels[$f->tipo_firma] ?? 'FIRMA',
                'firma'          => $f->firma,
                'fecha_label'    => $fechaStr ? formatearFecha($fechaStr) : '',
                'hora_label'     => $horaStr,
                'es_imagen'      => $f->tipo_firma === 'A' && !empty($f->firma),
                'firma_img_url'  => $firmaImgUrl,
                'firma_texto'    => $firmaTexto,
            ];
        }

        return $data;
    }


    public static function firmarToken(int $idReporte, string $tipoFirma, int $token): array
    {
        $sessionUsuario = Session::get('usuario');
        $idUsuario = (int)($sessionUsuario['id'] ?? 0);

        if (!$idUsuario || !$idReporte || empty($tipoFirma)) {
            return ['success' => false, 'message' => 'Datos incompletos.'];
        }

        $registro = RhDiaDobleRegistro::find($idReporte);
        if (!$registro) {
            return ['success' => false, 'message' => 'Reporte no encontrado.'];
        }

        if (!self::validarToken($token, $idUsuario)) {
            return ['success' => false, 'message' => 'Token inválido o expirado.'];
        }

        $tipoFirmaUpper = strtoupper($tipoFirma);
        if (!in_array($tipoFirmaUpper, ['B', 'C'])) {
            return ['success' => false, 'message' => 'Tipo de firma inválido.'];
        }

        $newStatus = $tipoFirmaUpper === 'B' ? 2 : 3;
        $registro->status = $newStatus;
        $registro->save();

        RhDiasDoblesFirma::create([
            'id_formato'  => $idReporte,
            'id_usuario'  => $idUsuario,
            'tipo_firma'  => $tipoFirmaUpper,
            'firma'       => 'Firma:' . bin2hex(random_bytes(8)) . '.' . uniqid(),
            'fecha'       => Carbon::now(),
        ]);

        self::eliminarToken($token, $idUsuario);

$nombreUsuario = $sessionUsuario['nombre'] ?? 'Usuario';

$mensaje = '📋 Se ha firmado un formato en el apartado de <b>Día Doble</b>' . PHP_EOL . PHP_EOL
. '🔢 <b>Reporte #:</b> 00' . $idReporte . PHP_EOL
. '📝 <b>Tipo:</b> Firma ' . ($tipoFirmaUpper === 'B' ? 'VoBO' : 'Autorización') . PHP_EOL
. '👤 <b>Responsable:</b> ' . $nombreUsuario;

        $idEstacion = (int)($sessionUsuario['id_estacion'] ?? 0);
        try {
            TelegramService::notificar($idEstacion, $idUsuario, $mensaje);
        } catch (\Throwable $e) {
            error_log('Error Telegram Día Doble firma: ' . $e->getMessage());
        }

        return ['success' => true, 'message' => 'Formato firmado correctamente.'];
    }

    public static function guardarFirmaImagen(int $idReporte, string $base64): array
    {
        $sessionUsuario = Session::get('usuario');
        $idUsuario = (int)($sessionUsuario['id'] ?? 0);

        if (!$idUsuario || !$idReporte || empty($base64)) {
            return ['success' => false, 'message' => 'Datos incompletos.'];
        }

        $registro = RhDiaDobleRegistro::find($idReporte);
        if (!$registro) {
            return ['success' => false, 'message' => 'Reporte no encontrado.'];
        }

        $img = str_replace('data:image/png;base64,', '', $base64);
        $fileData = base64_decode($img);
        if (!$fileData) {
            return ['success' => false, 'message' => 'Error al procesar la imagen.'];
        }

        $firmaDir = self::getFirmaDir();
        if (!is_dir($firmaDir)) {
            mkdir($firmaDir, 0755, true);
        }

        $fileName = uniqid() . '.png';
        file_put_contents($firmaDir . '/' . $fileName, $fileData);

        RhDiasDoblesFirma::create([
            'id_formato' => $idReporte,
            'id_usuario' => $idUsuario,
            'tipo_firma' => 'A',
            'firma'      => $fileName,
            'fecha'      => Carbon::now(),
        ]);

        $registro->status = 1;
        $registro->save();

        $nombreUsuario = $sessionUsuario['nombre'] ?? 'Usuario';

$mensaje = '📋 Se ha firmado un formato (Elaboró) en el apartado de <b>Día Doble</b>' . PHP_EOL . PHP_EOL
. '🔢 <b>Reporte #:</b> 00' . $idReporte . PHP_EOL
. '👤 <b>Responsable:</b> ' . $nombreUsuario;

        $idEstacion = (int)($sessionUsuario['id_estacion'] ?? 0);
        try {
            TelegramService::notificar($idEstacion, $idUsuario, $mensaje);
        } catch (\Throwable $e) {
            error_log('Error Telegram Día Doble firma imagen: ' . $e->getMessage());
        }

        return ['success' => true, 'message' => 'Formato finalizado correctamente.'];
    }

    public static function crearToken(int $idFormato, int $idUsuario, string $via = 'telegram'): array
    {
        try {
            $token = rand(100000, 999999);

            RhFormatosToken::create([
                'id_formato'     => $idFormato,
                'id_usuario'     => $idUsuario,
                'token'          => $token,
                'fecha_creacion' => Carbon::now(),
            ]);

            $usuario = Usuario::find($idUsuario);
            if (!$usuario) {
                return ['success' => false, 'message' => 'Usuario no encontrado.'];
            }

            $registro = RhDiaDobleRegistro::find($idFormato);
            $quincena = $registro ? $registro->quincena : '?';
            $year = $registro ? $registro->year : date('Y');

            $mensaje = "📲 Usa el token <b>{$token}</b> para firmar el reporte de Día Doble (#00{$idFormato}) de la Quincena: No. {$quincena} correspondiente al periodo de {$year}.";

            if ($via === 'email') {
                $email = $usuario->email ?? '';
                if (!$email) {
                    return ['success' => false, 'message' => 'El usuario no tiene correo electrónico registrado.'];
                }
                $emailService = new EmailService();
                $emailService->sendToken($email, (string)$token, 'Día Doble');
                return ['success' => true, 'message' => 'Token enviado por correo electrónico.'];
            } else {
                $telegram = new TelegramService();
                $telegram->sendToken($idUsuario, $mensaje);
                return ['success' => true, 'message' => 'Token enviado por Telegram.'];
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error al crear el token: ' . $e->getMessage()];
        }
    }

    private static function validarToken(int $token, int $idUsuario): bool
    {
        try {
            $record = RhFormatosToken::where('token', $token)
                ->where('id_usuario', $idUsuario)
                ->orderBy('id', 'desc')
                ->first();

            if (!$record) return false;

            $expiry = Carbon::parse($record->fecha_creacion)->addMinutes(2);
            return Carbon::now()->lessThanOrEqualTo($expiry);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function eliminarToken(int $token, int $idUsuario): void
    {
        try {
            RhFormatosToken::where('token', $token)
                ->where('id_usuario', $idUsuario)
                ->delete();
        } catch (\Throwable $e) {
            error_log('Error eliminando token: ' . $e->getMessage());
        }
    }

    public static function getFirmaDir(): string
    {
        $dir = __DIR__ . '/../../public/uploads/archivos/dia-doble-firma';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    public static function getPuedeFirmar(int $idReporte): array
    {
        $sessionUsuario = Session::get('usuario');
        $idUsuario = (int)($sessionUsuario['id'] ?? 0);
        $registro = RhDiaDobleRegistro::find($idReporte);

        if (!$registro) {
            return ['puedeFirmarA' => false, 'puedeFirmarB' => false, 'puedeFirmarC' => false];
        }

        $yaFirmoA = RhDiasDoblesFirma::where('id_formato', $idReporte)->where('id_usuario', $idUsuario)->where('tipo_firma', 'A')->exists();
        $yaFirmoB = RhDiasDoblesFirma::where('id_formato', $idReporte)->where('tipo_firma', 'B')->exists();
        $yaFirmoC = RhDiasDoblesFirma::where('id_formato', $idReporte)->where('tipo_firma', 'C')->exists();

        $firmaA = RhDiasDoblesFirma::where('id_formato', $idReporte)->where('tipo_firma', 'A')->first();
        $firmaB = RhDiasDoblesFirma::where('id_formato', $idReporte)->where('tipo_firma', 'B')->first();

        $usuario = Auth::user();
        $idPuesto = (int)($usuario->id_puesto ?? 0);
        $nombrePuesto = $usuario->puesto->tipo_puesto ?? '';
        $esDireccionOperaciones = $nombrePuesto === 'Dirección de operaciones' || $idPuesto === 13;

        $esFirmanteVoBo = ($idUsuario === 19) || $esDireccionOperaciones;
        $esFirmanteAuth = ($idUsuario === 2) || ($idUsuario === 22);

        return [
            'puedeFirmarA'    => $registro->status === 0 && !$yaFirmoA,
            'puedeFirmarB'    => $registro->status === 1 && !$yaFirmoB && $firmaA && $esFirmanteVoBo,
            'puedeFirmarC'    => $registro->status === 2 && !$yaFirmoC && $firmaB && $esFirmanteAuth,
            'yaFirmoA'        => $yaFirmoA,
            'yaFirmoB'        => $yaFirmoB,
            'yaFirmoC'        => $yaFirmoC,
            'esFirmanteVoBo'  => $esFirmanteVoBo,
            'esFirmanteAuth'  => $esFirmanteAuth,
        ];
    }

    public static function buildPdfDireccionHtml(int $idReporte): string
    {
        $detail = self::getDireccionDetail($idReporte);
        if (!$detail) {
            return '';
        }

        $quincena = $detail['quincena'];
        $year = $detail['year'];
        $inicioQuincena = $detail['inicio_quincena'];
        $finQuincena = $detail['fin_quincena'];
        $fechaCreacion = $detail['fecha_creacion'];
        $explode = explode(' ', $fechaCreacion);
        $fechaStr = $explode[0] ?? date('Y-m-d');
        $horaStr = isset($explode[1]) ? date('g:i a', strtotime($explode[1])) : '';
        $fechaFormateada = formatearFecha($fechaStr);

        $html = '<html><head><meta charset="utf-8"><style>';
        $html .= '@page { margin: 1cm 1.5cm; }';
        $html .= 'body { margin: 0; font-family: Montserrat, sans-serif; font-size: 11px; color: #212529; }';
        $html .= '.custom-table { width: 100%; font-size: 10.5px; border-collapse: collapse; }';
        $html .= '.custom-table thead th, .custom-table tbody td { text-align: left; padding: 8px; }';
        $html .= '.tables-bg { background: #215D98; color: white; }';
        $html .= '.title-table-bg { background: #749ABF; color: white; }';
        $html .= '.firma-col { width: 33%; float: left; padding: 0 1px; box-sizing: border-box; text-align: center; }';
        $html .= '.clearfix::after { content: ""; display: table; clear: both; }';
        $html .= '.firma-col img { max-width: 70%; }';
        $html .= '.text-center { text-align: center; }';
        $html .= '.fw-bold { font-weight: bold; }';
        $html .= '.mb-2 { margin-bottom: 8px; }';
        $html .= '.mb-3 { margin-bottom: 12px; }';
        $html .= '.mt-2 { margin-top: 8px; }';
        $html .= '.p-2 { padding: 8px; }';
        $html .= '</style></head><body>';

        $html .= '<h2 style="font-size:14px;">Formato Dia Doble (Quincena ' . $quincena . ')</h2>';

        $html .= '<div style="text-align:right;margin-bottom:12px;">';
        $html .= '<b>No. de Folio:</b> 00' . $idReporte;
        $html .= '<p>Huixquilucan, Edo. de México a ' . $fechaFormateada . ', ' . $horaStr . '</p>';
        $html .= '</div>';

        $html .= '<b>Lic. Alejandro Guzmán</b><br>';
        $html .= '<p><b>Departamento de Recursos Humanos</b></p>';
        $html .= '<p>Buenos días, Por medio de la presente, les informo sobre los días dobles asignados al personal del Departamento de Dirección de Operaciones, correspondientes a la <b>Quincena No. ' . $quincena . '</b>, ';
        $html .= 'que abarca del <b>' . $inicioQuincena . '</b> al <b>' . $finQuincena . '</b>';
        $html .= '<br> A continuación, detallo la información para cada uno de los colaboradores:</p>';

        $html .= '<div class="table-responsive mb-4">';
        $html .= '<table class="custom-table" width="100%">';
        $html .= '<thead class="tables-bg"><tr>';
        $html .= '<th class="text-center">#</th>';
        $html .= '<th class="text-center">Empleado</th>';
        $html .= '<th class="text-center">Día Doble</th>';
        $html .= '</tr></thead>';
        $html .= '<tbody>';

        if (count($detail['empleados']) > 0) {
            $num = 1;
            foreach ($detail['empleados'] as $emp) {
                $html .= '<tr>';
                $html .= '<th class="text-center">' . $num . '</th>';
                $html .= '<td class="text-center">' . htmlspecialchars($emp['nombre']) . '</td>';
                $html .= '<td class="text-center">' . htmlspecialchars($emp['fecha_label']) . '</td>';
                $html .= '</tr>';
                $num++;
            }
        } else {
            $html .= '<tr><th colspan="3" class="text-center">No se encontró información para mostrar</th></tr>';
        }

        $html .= '</tbody></table></div>';

        $html .= '<div class="text-center"><p>Sin más por el momento quedo de usted.</p><hr></div>';

        if (count($detail['firmas']) > 0) {
            $html .= '<div class="clearfix">';
            foreach ($detail['firmas'] as $firma) {
                $html .= '<div class="firma-col">';
                $html .= '<table class="custom-table" width="100%">';
                $html .= '<thead class="tables-bg"><tr><th class="text-center">' . htmlspecialchars($firma['usuario_nombre']) . '</th></tr></thead>';
                $html .= '<tbody><tr><th class="text-center p-2">';

                if ($firma['tipo_firma'] === 'A' && $firma['es_imagen']) {
                    $firmaUrl = '/download?tipo=dia-doble-firma&file=' . urlencode($firma['firma']);
                    $html .= '<img src="' . $firmaUrl . '" style="max-width:70%;">';
                } else {
                    $html .= '<div style="font-size:1em;"><small>La solicitud se firmó por un medio electrónico.</small></div>';
                    $html .= '<div><b>Fecha: ' . $firma['fecha_label'] . ', ' . $firma['hora_label'] . '</b></div>';
                }

                $html .= '</th></tr><tr><th class="text-center p-2">';

                if ($firma['tipo_firma'] === 'A') {
                    $html .= 'NOMBRE Y FIRMA DE QUIEN ELABORÓ';
                } elseif ($firma['tipo_firma'] === 'B') {
                    $html .= 'NOMBRE Y FIRMA DEL VOBO';
                } else {
                    $html .= 'NOMBRE Y FIRMA DE AUTORIZACIÓN';
                }

                $html .= '</th></tr></tbody></table></div>';
            }
            $html .= '<div class="clearfix"></div></div>';
        }

        $html .= '</body></html>';

        return $html;
    }

    public static function buildPdfStationTable(array $estacion): string
    {
        $html = '<div style="margin-bottom:15px;">';
        $html .= '<table style="width:100%;border-collapse:collapse;font-size:9px;">';
        $html .= '<thead>';
        $html .= '<tr><th colspan="4" style="background:#1f5f9b;color:#fff;text-align:center;padding:6px;font-size:11px;">' . htmlspecialchars($estacion['nombre']) . '</th></tr>';
        $html .= '<tr style="background:#e9ecef;">';
        $html .= '<th style="border:1px solid #dee2e6;text-align:center;padding:4px;width:35px;">No.</th>';
        $html .= '<th style="border:1px solid #dee2e6;text-align:left;padding:4px;">Nombre</th>';
        $html .= '<th style="border:1px solid #dee2e6;text-align:center;padding:4px;">Puesto</th>';
        $html .= '<th style="border:1px solid #dee2e6;text-align:center;padding:4px;">Día doble</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        $empleados = $estacion['empleados'] ?? [];

        if (count($empleados) > 0) {
            $num = 1;
            foreach ($empleados as $emp) {
                $diasCount = count($emp['dias_dobles']);
                if ($diasCount > 0) {
                    foreach ($emp['dias_dobles'] as $idx => $dia) {
                        $html .= '<tr>';
                        if ($idx === 0) {
                            $html .= '<td rowspan="' . $diasCount . '" style="border:1px solid #dee2e6;text-align:center;padding:4px;font-weight:bold;">' . $num . '</td>';
                            $html .= '<td rowspan="' . $diasCount . '" style="border:1px solid #dee2e6;padding:4px;">' . htmlspecialchars($emp['nombre']) . '</td>';
                            $html .= '<td rowspan="' . $diasCount . '" style="border:1px solid #dee2e6;text-align:center;padding:4px;">' . htmlspecialchars($emp['puesto_nombre']) . '</td>';
                            $num++;
                        }
                        $html .= '<td style="border:1px solid #dee2e6;text-align:center;padding:4px;font-weight:normal;">' . htmlspecialchars($dia) . '</td>';
                        $html .= '</tr>';
                    }
                }
            }
        } else {
            $html .= '<tr><td colspan="4" style="border:1px solid #dee2e6;text-align:center;padding:8px;color:#6c757d;">No se encontró información para mostrar</td></tr>';
        }

        $html .= '</tbody></table></div>';

        return $html;
    }

    public static function getPdfStyles(): string
    {
        return '
        @page { margin: 0.8cm 1cm; }
        body { margin: 0; font-family: sans-serif; font-size: .8rem; color: #212529; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #dee2e6; padding: 4px; }
        ';
    }

    public static function getFormDetail(int $idReporte): ?array
    {
        $registro = RhDiaDobleRegistro::find($idReporte);
        if (!$registro) {
            return null;
        }

        $quincenaRange = self::getQuincenaRange($registro->year, $registro->quincena);
        $fechaCreacion = $registro->fecha_creacion ?? '';
        $explode = explode(' ', $fechaCreacion);
        $fechaStr = $explode[0] ?? date('Y-m-d');
        $horaStr = isset($explode[1]) ? date('g:i a', strtotime($explode[1])) : '';

        $personalAsignado = RhDiaDoblePersonal::where('id_registro', $idReporte)
            ->orderBy('id', 'asc')
            ->get();

        $empleados = [];
        foreach ($personalAsignado as $pa) {
            $personal = RhPersonal::find($pa->id_usuario);
            $empleados[] = [
                'id'           => $pa->id,
                'id_usuario'   => $pa->id_usuario,
                'nombre'       => $personal ? $personal->nombre_completo : 'Desconocido',
                'fecha_doble'  => $pa->fecha_doble,
                'fecha_label'  => formatearFecha($pa->fecha_doble),
            ];
        }

        $firmas = self::getFirmas($idReporte);
        $numComentarios = RhDiaDobleComentarios::where('id_reporte', $idReporte)->count();

        $inicioQuincena = $quincenaRange['inicio'] ? formatearFechaLarga($quincenaRange['inicio']) : 'S/I';
        $finQuincena = $quincenaRange['fin'] ? formatearFechaLarga($quincenaRange['fin']) : 'S/I';

        return [
            'id'                 => $registro->id,
            'year'               => $registro->year,
            'quincena'           => $registro->quincena,
            'status'             => $registro->status,
            'status_label'       => self::statusLabel($registro->status),
            'fecha_creacion'     => $fechaCreacion,
            'fecha_formateada'   => formatearFechaLarga($fechaStr) . ', ' . $horaStr,
            'inicio_quincena'    => $inicioQuincena,
            'fin_quincena'       => $finQuincena,
            'inicio_quincena_raw'=> $quincenaRange['inicio'],
            'fin_quincena_raw'   => $quincenaRange['fin'],
            'empleados'          => $empleados,
            'firmas'             => $firmas,
            'comentarios'        => $numComentarios,
        ];
    }

    public static function getPersonalDireccion(): array
    {
        return RhPersonal::where('id_estacion', 11)
            ->where('estado', 1)
            ->orderBy('nombre_completo', 'asc')
            ->get(['id', 'nombre_completo'])
            ->toArray();
    }
}
