<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Operativo\RhLocalidad;
use App\Models\Operativo\RhPersonal;
use App\Models\Operativo\RhPermisos;
use App\Models\Operativo\RhPermisosFirma;
use App\Models\Operativo\RhPermisosToken;
use Carbon\Carbon;

class PermisosRrhhService
{
    public const MODULE_KEY = 'permisos';

    private const MIN_YEAR = 2026;

    private const UPLOAD_DIR = __DIR__ . '/../../public/uploads/archivos/permisos-firma/';

    private const NOMBRE_DIRECCION = 'Dirección de operaciones';

    public const ESTADO_LABELS = [
        0 => 'Pendiente',
        1 => 'En Proceso',
        2 => 'Finalizado',
    ];

    public const TIPO_LABELS = [
        'A' => 'NOMBRE Y FIRMA DE QUIEN SOLICITA',
        'B' => 'NOMBRE Y FIRMA DE QUIEN CUBRE',
        'C' => 'NOMBRE Y FIRMA DEl VISTO BUENO',
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

        $esDireccion = ($nombrePuesto === self::NOMBRE_DIRECCION || $idPuesto === 13);

        return [
            'id_usuario'     => $idUsuario,
            'id_estacion'    => $idEstacion,
            'id_puesto'      => $idPuesto,
            'nombre_puesto'  => $nombrePuesto,
            'multiestacion'  => $multiestacion,
            'esDireccion'    => $esDireccion,
            'puedeCrear'     => !empty($permisosDb['crear']),
            'puedeEditar'    => !empty($permisosDb['editar']),
            'puedeEliminar'  => !empty($permisosDb['eliminar']),
            'puedeDescargar' => !empty($permisosDb['descargar']),
            // Sustituto del usuario 318 (Dirección de RH): firma el Visto Bueno.
            'puedeVoBo'      => $esDireccion,
        ];
    }

    /**
     * Conjunto de ids de localidad permitidos (estaciones + departamentos)
     * según la configuración del módulo y la multiestación del usuario.
     */
    public static function getAllowedLocalidadIds(): array
    {
        $ids = [];
        foreach (ModuleStationService::getAvailableStations(self::MODULE_KEY) as $s) {
            $ids[] = (int)$s['id'];
        }
        foreach (ModuleStationService::getAvailableDepartments(self::MODULE_KEY) as $d) {
            $ids[] = (int)$d['id'];
        }

        if (empty($ids)) {
            $ids = self::getPuestoLocalidadIds();
        }

        return array_values(array_unique($ids));
    }

    private static function getPuestoLocalidadIds(): array
    {
        $permisos = self::getPermisos();
        $idPuesto = $permisos['id_puesto'];

        $todos = [
            'puesto12' => [2, 1, 3, 4, 5, 14],
            'puesto4'  => [6, 7],
            'puesto13' => [2, 1, 3, 4, 5, 6, 7, 14, 9],
        ];

        if ($idPuesto === 4) {
            return $todos['puesto4'];
        }
        if ($idPuesto === 12) {
            return $todos['puesto12'];
        }

        return $todos['puesto13'];
    }

    public static function puedeVerLocalidad(int $idLocalidad): bool
    {
        $allowed = self::getAllowedLocalidadIds();
        return empty($allowed) || in_array($idLocalidad, $allowed, true);
    }

    public static function getNombreLocalidad(int $id): string
    {
        $loc = RhLocalidad::find($id);
        if ($loc) {
            return $loc->localidad;
        }
        $est = Estacion::find($id);
        if ($est) {
            return $est->nombre;
        }
        return '';
    }

    public static function getLocalidadesDisponibles(): array
    {
        $items = [];
        foreach (self::getAllowedLocalidadIds() as $id) {
            $items[] = [
                'id'     => $id,
                'nombre' => self::getNombreLocalidad($id),
            ];
        }
        return $items;
    }

    /**
     * Personal disponible de una localidad para seleccionar como colaborador
     * o como quien cubre. Autolavado (9) proviene de op_rh_personal; el resto
     * de tb_usuarios (Comodines = 8 solo puesto de comodín).
     */
    public static function getPersonal(int $estacion): array
    {
        $items = [];

        if ($estacion === 9) {
            $rows = RhPersonal::where('id_estacion', 9)
                ->where('estado', 1)
                ->orderBy('nombre_completo', 'asc')
                ->get();

            foreach ($rows as $r) {
                $items[] = [
                    'id'     => (int)$r->id,
                    'nombre' => $r->nombre_completo,
                ];
            }

            return $items;
        }

        $q = Usuario::where('id_gas', $estacion)->where('estatus', 0);
        if ($estacion === 8) {
            $q->where('id_puesto', 6);
        }

        foreach ($q->orderBy('id', 'asc')->get() as $u) {
            $items[] = [
                'id'     => (int)$u->id,
                'nombre' => $u->nombre,
            ];
        }

        return $items;
    }

    /**
     * Personal de todas las localidades permitidas para seleccionar a quien
     * cubre el turno. Cada opción incluye su estación para derivarla de forma
     * automática al seleccionar a la persona.
     */
    public static function getPersonalCubre(): array
    {
        $items = [];
        foreach (self::getAllowedLocalidadIds() as $estacion) {
            foreach (self::getPersonal($estacion) as $p) {
                $items[] = [
                    'id'          => (int)$p['id'],
                    'nombre'      => $p['nombre'],
                    'id_estacion' => (int)$estacion,
                ];
            }
        }

        usort($items, function ($a, $b) {
            if ($a['id_estacion'] === $b['id_estacion']) {
                return strcasecmp($a['nombre'], $b['nombre']);
            }
            return strcasecmp(self::getNombreLocalidad($a['id_estacion']), self::getNombreLocalidad($b['id_estacion']));
        });

        return $items;
    }

    /**
     * Estaciones disponibles para el campo "Estación de quien cubre":
     * las localidades permitidas del módulo más Comodines (id 8).
     */
    public static function getEstacionesCubre(): array
    {
        $items = self::getLocalidadesDisponibles();

        if (!in_array(8, array_column($items, 'id'), true)) {
            $items[] = [
                'id'     => 8,
                'nombre' => self::getNombreLocalidad(8) ?: 'Comodines',
            ];
        }

        return $items;
    }

    /**
     * Verifica que una persona (colaborador o quien cubre) pertenezca a la
     * localidad indicada. Autolavado (9) se resuelve en op_rh_personal y el
     * resto en tb_usuarios.
     */
    private static function personaPerteneceALocalidad(int $idPersona, int $idLocalidad): bool
    {
        if ($idLocalidad === 9) {
            return (bool)RhPersonal::find($idPersona);
        }
        $u = Usuario::find($idPersona);
        if (!$u || (int)$u->id_gas !== $idLocalidad) {
            return false;
        }
        if ($idLocalidad === 8) {
            return (int)$u->id_puesto === 6;
        }
        return true;
    }

    /**
     * Resolución de nombres de personal según la estación del registro:
     * Autolavado (9) usa op_rh_personal, el resto tb_usuarios.
     */
    private static function nombrePersonal(int $id, int $idEstacion): string
    {
        if ($idEstacion === 9) {
            $p = RhPersonal::find($id);
            return $p ? $p->nombre_completo : ('Personal #' . $id);
        }
        $u = Usuario::find($id);
        return $u ? $u->nombre : ('Usuario #' . $id);
    }

    public static function getLista(): array
    {
        $ctx = ModuleStationService::getContext(self::MODULE_KEY);
        $idEstacion = $ctx['id_estacion'];
        $idDepto = $ctx['id_depto'];

        $q = RhPermisos::query()->whereYear('fechacreacion', '>=', self::MIN_YEAR);

        if ($idDepto && !$idEstacion) {
            $q->where(function ($w) use ($idDepto) {
                $w->where('id_estacion', $idDepto)->orWhere('estacion_cubre', $idDepto);
            });
        } elseif ($idEstacion) {
            $q->where(function ($w) use ($idEstacion) {
                $w->where('id_estacion', $idEstacion)->orWhere('estacion_cubre', $idEstacion);
            });
        } else {
            $allowed = self::getAllowedLocalidadIds();
            if (!empty($allowed)) {
                $q->where(function ($w) use ($allowed) {
                    $w->whereIn('id_estacion', $allowed)->orWhereIn('estacion_cubre', $allowed);
                });
            }
        }

        $permisos = self::getPermisos();
        $idUsuario = $permisos['id_usuario'];
        $puedeVoBo = $permisos['puedeVoBo'];
        $puedeEditar = $permisos['puedeEditar'];
        $puedeEliminar = $permisos['puedeEliminar'];

        $records = $q->orderBy('id', 'desc')->get();

        $firmasEstado = RhPermisosFirma::selectRaw('id_permiso, tipo_firma, COUNT(*) as total')
            ->whereIn('id_permiso', $records->pluck('id')->toArray())
            ->groupBy('id_permiso', 'tipo_firma')
            ->get();

        $mapaFirmas = [];
        foreach ($firmasEstado as $f) {
            $mapaFirmas[(int)$f->id_permiso][$f->tipo_firma] = (int)$f->total;
        }

        $rows = [];
        foreach ($records as $r) {
            $id = (int)$r->id;
            $estado = (int)$r->estado;
            $tieneA = !empty($mapaFirmas[$id]['A']);
            $tieneB = !empty($mapaFirmas[$id]['B']);
            $tieneC = !empty($mapaFirmas[$id]['C']);

            $rows[] = [
                'id'               => $id,
                'id_estacion'      => (int)$r->id_estacion,
                'estacion'         => self::getNombreLocalidad((int)$r->id_estacion),
                'fecha'            => $r->fechacreacion ? formatearFecha($r->fechacreacion) . ', ' . date('g:i a', strtotime($r->fechacreacion)) : '---',
                'nombre_colaborador' => self::nombrePersonal((int)$r->id_personal, (int)$r->id_estacion),
                'cubre_nombre'     => self::nombrePersonal((int)$r->cubre_turno, (int)$r->estacion_cubre),
                'cubre_turno'      => (int)$r->cubre_turno,
                'estacion_cubre'   => (int)$r->estacion_cubre,
                'estacion_cubre_nombre' => self::getNombreLocalidad((int)$r->estacion_cubre),
                'fecha_inicio'     => $r->fecha_inicio,
                'fecha_termino'    => $r->fecha_termino,
                'fecha_inicio_label' => $r->fecha_inicio ? formatearFecha($r->fecha_inicio) : '---',
                'fecha_termino_label' => $r->fecha_termino ? formatearFecha($r->fecha_termino) : '---',
                'dias_tomados'     => $r->dias_tomados ?? '',
                'motivo'           => $r->motivo ?? '',
                'observaciones'    => $r->observaciones ?? '',
                'estado'           => $estado,
                'estado_label'     => self::ESTADO_LABELS[$estado] ?? 'Desconocido',
                'puede_firmar_cubre' => ($tieneA && $idUsuario === (int)$r->cubre_turno && $estado <= 1 && !$tieneB),
                'puede_firmar_vobo'  => ($tieneA && $puedeVoBo && $estado === 1 && $tieneB && !$tieneC),
                'tiene_firma_a'      => $tieneA,
                'puede_editar'     => ($puedeEditar && $estado < 2),
                'puede_eliminar'   => ($puedeEliminar && $estado < 2),
            ];
        }

        return $rows;
    }

    public static function getDetalle(int $id): ?array
    {
        $r = RhPermisos::find($id);
        if (!$r) {
            return null;
        }

        $estado = (int)$r->estado;
        $tieneB = RhPermisosFirma::where('id_permiso', $id)->where('tipo_firma', 'B')->exists();
        $tieneC = RhPermisosFirma::where('id_permiso', $id)->where('tipo_firma', 'C')->exists();
        $permisos = self::getPermisos();

        $permisosFirma = [
            'puedeFirmarCubre' => ($permisos['id_usuario'] === (int)$r->cubre_turno && $estado <= 1 && !$tieneB),
            'puedeFirmarVoBo'  => ($permisos['puedeVoBo'] && $estado === 1 && $tieneB && !$tieneC),
            'puedeEditar'      => ($permisos['puedeEditar'] && $estado < 2),
            'puedeEliminar'    => ($permisos['puedeEliminar'] && $estado < 2),
        ];

        return [
            'id'               => $id,
            'id_estacion'      => (int)$r->id_estacion,
            'estacion'         => self::getNombreLocalidad((int)$r->id_estacion),
            'estacion_cubre'   => (int)$r->estacion_cubre,
            'estacion_cubre_nombre' => self::getNombreLocalidad((int)$r->estacion_cubre),
            'fecha'            => $r->fechacreacion ? formatearFecha($r->fechacreacion) . ', ' . date('g:i a', strtotime($r->fechacreacion)) : '---',
            'nombre_colaborador' => self::nombrePersonal((int)$r->id_personal, (int)$r->id_estacion),
            'id_personal'      => (int)$r->id_personal,
            'cubre_nombre'     => self::nombrePersonal((int)$r->cubre_turno, (int)$r->estacion_cubre),
            'cubre_turno'      => (int)$r->cubre_turno,
            'fecha_inicio'     => $r->fecha_inicio,
            'fecha_termino'    => $r->fecha_termino,
            'fecha_inicio_label' => $r->fecha_inicio ? formatearFecha($r->fecha_inicio) : '---',
            'fecha_termino_label' => $r->fecha_termino ? formatearFecha($r->fecha_termino) : '---',
            'dias_tomados'     => $r->dias_tomados ?? '',
            'motivo'           => $r->motivo ?? '',
            'observaciones'    => $r->observaciones ?? '',
            'estado'           => $estado,
            'estado_label'     => self::ESTADO_LABELS[$estado] ?? 'Desconocido',
            'firmas'           => self::getFirmas($id),
            'permisos_firma'   => $permisosFirma,
        ];
    }

    public static function getFirmas(int $id): array
    {
        $firmas = RhPermisosFirma::where('id_permiso', $id)->orderBy('id', 'asc')->get();
        $data = [];

        foreach ($firmas as $f) {
            $tipo = $f->tipo_firma;
            $usuario = Usuario::find((int)$f->id_usuario);
            $nombre = $usuario ? $usuario->nombre : 'Desconocido';

            $fechaStr = $f->fecha ? date('Y-m-d H:i:s', strtotime($f->fecha)) : '';
            $fechaLabel = $fechaStr ? formatearFecha(substr($fechaStr, 0, 10)) . ', ' . date('g:i a', strtotime($fechaStr)) : '';

            $esImagen = in_array($tipo, ['A', 'B'], true);

            $data[] = [
                'id'            => (int)$f->id,
                'id_usuario'    => (int)$f->id_usuario,
                'usuario_nombre' => $nombre,
                'tipo_firma'    => $tipo,
                'tipo_label'    => self::TIPO_LABELS[$tipo] ?? ('FIRMA ' . $tipo),
                'es_imagen'     => $esImagen,
                'firma_img_url' => $esImagen ? '/download?tipo=permisos-firma&file=' . rawurlencode($f->firma) . '&view=1' : '',
                'firma_texto'   => $tipo === 'C'
                    ? '<b>Fecha: ' . $fechaLabel . '</b> <br> El permiso se firmó por un medio electrónico.'
                    : '',
                'fecha_label'   => $fechaLabel,
            ];
        }

        return $data;
    }

    private static function calcularDias(string $fechaInicio, string $fechaTermino): int
    {
        $inicio = Carbon::parse($fechaInicio);
        $termino = Carbon::parse($fechaTermino);
        return (int)$inicio->diffInDays($termino) + 1;
    }

    public static function guardar(array $datos, string $firmaBase64): array
    {
        $permisos = self::getPermisos();
        if (empty($permisos['puedeCrear'])) {
            return ['success' => false, 'message' => 'No tienes permiso para crear permisos'];
        }

        $idEstacion = (int)($datos['id_estacion'] ?? 0);
        $idPersonal = (int)($datos['colaborador'] ?? 0);
        $cubre = (int)($datos['cubre'] ?? 0);
        $estacionCubre = (int)($datos['estacion_cubre'] ?? $idEstacion);
        $fechaInicio = trim((string)($datos['fecha_inicio'] ?? ''));
        $fechaTermino = trim((string)($datos['fecha_termino'] ?? ''));
        $motivo = trim((string)($datos['motivo'] ?? ''));
        $observaciones = trim((string)($datos['observaciones'] ?? ''));

        if ($idEstacion <= 0 || !self::puedeVerLocalidad($idEstacion)) {
            return ['success' => false, 'message' => 'Selecciona una estación o departamento'];
        }
        if ($idPersonal <= 0) {
            return ['success' => false, 'message' => 'Selecciona un colaborador'];
        }
        if (!self::personaPerteneceALocalidad($idPersonal, $idEstacion)) {
            return ['success' => false, 'message' => 'El colaborador no pertenece a la estación indicada'];
        }
        if ($cubre <= 0) {
            return ['success' => false, 'message' => 'Selecciona a quien cubre'];
        }
        if (!self::puedeVerLocalidad($estacionCubre) && $estacionCubre !== 8) {
            return ['success' => false, 'message' => 'La estación de quien cubre no es válida'];
        }
        if (!self::personaPerteneceALocalidad($cubre, $estacionCubre)) {
            return ['success' => false, 'message' => 'La persona que cubre no pertenece a la estación indicada'];
        }
        if ($fechaInicio === '' || $fechaTermino === '') {
            return ['success' => false, 'message' => 'Las fechas son obligatorias'];
        }
        if (!Carbon::hasFormat($fechaInicio, 'Y-m-d') || !Carbon::hasFormat($fechaTermino, 'Y-m-d')) {
            return ['success' => false, 'message' => 'Formato de fecha no válido'];
        }
        if (Carbon::parse($fechaTermino)->lt(Carbon::parse($fechaInicio))) {
            return ['success' => false, 'message' => 'La fecha de término debe ser igual o posterior al inicio'];
        }
        if ($motivo === '') {
            return ['success' => false, 'message' => 'El motivo es obligatorio'];
        }

        $dias = self::calcularDias($fechaInicio, $fechaTermino);
        if ($dias < 1) {
            return ['success' => false, 'message' => 'El permiso debe cubrir al menos un día'];
        }

        $permiso = RhPermisos::create([
            'id_estacion'   => $idEstacion,
            'id_personal'   => $idPersonal,
            'fecha_inicio'  => $fechaInicio,
            'fecha_termino' => $fechaTermino,
            'dias_tomados'  => (string)$dias,
            'cubre_turno'   => $cubre,
            'motivo'        => $motivo,
            'observaciones' => $observaciones,
            'estado'        => 0,
            'estacion_cubre' => $estacionCubre,
        ]);

        if (!self::guardarFirmaImagen((int)$permiso->id, $idPersonal, 'A', $firmaBase64)) {
            $permiso->delete();
            return ['success' => false, 'message' => 'Es necesaria la firma del solicitante'];
        }

        self::notificarCrear((int)$permiso->id, $permisos['id_usuario']);

        return ['success' => true, 'message' => 'Permiso registrado correctamente', 'id' => (int)$permiso->id];
    }

    public static function actualizar(int $id, array $datos, string $firmaBase64 = ''): array
    {
        $permisos = self::getPermisos();
        if (empty($permisos['puedeEditar'])) {
            return ['success' => false, 'message' => 'No tienes permiso para editar permisos'];
        }

        $permiso = RhPermisos::find($id);
        if (!$permiso) {
            return ['success' => false, 'message' => 'Permiso no encontrado'];
        }
        if ((int)$permiso->estado === 2) {
            return ['success' => false, 'message' => 'No se puede editar un permiso ya autorizado'];
        }

        $idPersonal = (int)($datos['colaborador'] ?? 0);
        $cubre = (int)($datos['cubre'] ?? 0);
        $estacionCubre = (int)($datos['estacion_cubre'] ?? (int)$permiso->estacion_cubre);
        $fechaInicio = trim((string)($datos['fecha_inicio'] ?? ''));
        $fechaTermino = trim((string)($datos['fecha_termino'] ?? ''));
        $motivo = trim((string)($datos['motivo'] ?? ''));
        $observaciones = trim((string)($datos['observaciones'] ?? ''));

        if ($idPersonal <= 0) {
            return ['success' => false, 'message' => 'Selecciona un colaborador'];
        }
        if (!self::personaPerteneceALocalidad($idPersonal, (int)$permiso->id_estacion)) {
            return ['success' => false, 'message' => 'El colaborador no pertenece a la estación indicada'];
        }
        if ($cubre <= 0) {
            return ['success' => false, 'message' => 'Selecciona a quien cubre'];
        }
        if (!self::personaPerteneceALocalidad($cubre, $estacionCubre)) {
            return ['success' => false, 'message' => 'La persona que cubre no pertenece a la estación indicada'];
        }
        if ($fechaInicio === '' || $fechaTermino === '') {
            return ['success' => false, 'message' => 'Las fechas son obligatorias'];
        }
        if (Carbon::parse($fechaTermino)->lt(Carbon::parse($fechaInicio))) {
            return ['success' => false, 'message' => 'La fecha de término debe ser igual o posterior al inicio'];
        }
        if ($motivo === '') {
            return ['success' => false, 'message' => 'El motivo es obligatorio'];
        }

        $dias = self::calcularDias($fechaInicio, $fechaTermino);

        $permiso->update([
            'id_personal'   => $idPersonal,
            'fecha_inicio'  => $fechaInicio,
            'fecha_termino' => $fechaTermino,
            'dias_tomados'  => (string)$dias,
            'cubre_turno'   => $cubre,
            'motivo'        => $motivo,
            'observaciones' => $observaciones,
            'estacion_cubre' => $estacionCubre,
        ]);

        if ($firmaBase64 !== '') {
            self::eliminarFirmaArchivo($id, 'A');
            RhPermisosFirma::where('id_permiso', $id)->where('tipo_firma', 'A')->delete();

            if (!self::guardarFirmaImagen($id, $idPersonal, 'A', $firmaBase64)) {
                return ['success' => false, 'message' => 'La firma del solicitante no es válida'];
            }
        }

        self::notificarEditar($id, $permisos['id_usuario']);

        return ['success' => true, 'message' => 'Permiso actualizado correctamente'];
    }

    public static function eliminar(int $id): array
    {
        $permisos = self::getPermisos();
        if (empty($permisos['puedeEliminar'])) {
            return ['success' => false, 'message' => 'No tienes permiso para eliminar permisos'];
        }

        $permiso = RhPermisos::find($id);
        if (!$permiso) {
            return ['success' => false, 'message' => 'Permiso no encontrado'];
        }
        if ((int)$permiso->estado === 2) {
            return ['success' => false, 'message' => 'No se puede eliminar un permiso ya autorizado'];
        }

        self::eliminarFirmaArchivo($id, 'A');
        self::eliminarFirmaArchivo($id, 'B');

        RhPermisosFirma::where('id_permiso', $id)->delete();
        RhPermisosToken::where('id_permiso', $id)->delete();
        $permiso->delete();

        self::notificarEliminar($id, $permisos['id_usuario']);

        return ['success' => true, 'message' => 'Permiso eliminado correctamente'];
    }

    public static function firmaQuienCubre(int $id, string $firmaBase64): array
    {
        $permisos = self::getPermisos();
        $idUsuario = $permisos['id_usuario'];

        $permiso = RhPermisos::find($id);
        if (!$permiso) {
            return ['success' => false, 'message' => 'Permiso no encontrado'];
        }
        if ((int)$permiso->estado === 2) {
            return ['success' => false, 'message' => 'El permiso ya fue autorizado'];
        }
        if ($idUsuario !== (int)$permiso->cubre_turno) {
            return ['success' => false, 'message' => 'Solo puede firmar quien cubre el permiso'];
        }
        if (RhPermisosFirma::where('id_permiso', $id)->where('tipo_firma', 'B')->exists()) {
            return ['success' => false, 'message' => 'La firma de quien cubre ya fue registrada'];
        }

        if (!self::guardarFirmaImagen($id, $idUsuario, 'B', $firmaBase64)) {
            return ['success' => false, 'message' => 'Dibuja tu firma en el canvas correspondiente'];
        }

        $permiso->update(['estado' => 1]);

        self::notificarFirmaCubre($id, $idUsuario);

        return ['success' => true, 'message' => 'Firma de quien cubre registrada correctamente'];
    }

    public static function crearToken(int $id, int $idUsuario, string $via = 'telegram'): array
    {
        $permisos = self::getPermisos();
        if (empty($permisos['puedeVoBo'])) {
            return ['success' => false, 'message' => 'No tienes permisos para firmar el visto bueno'];
        }

        $permiso = RhPermisos::find($id);
        if (!$permiso) {
            return ['success' => false, 'message' => 'Permiso no encontrado'];
        }
        if ((int)$permiso->estado !== 1) {
            return ['success' => false, 'message' => 'Aún no se ha firmado la firma de quien cubre'];
        }
        if (RhPermisosFirma::where('id_permiso', $id)->where('tipo_firma', 'C')->exists()) {
            return ['success' => false, 'message' => 'El visto bueno ya fue firmado'];
        }

        try {
            RhPermisosToken::where('id_permiso', $id)->where('id_usuario', $idUsuario)->delete();

            $token = rand(100000, 999999);

            RhPermisosToken::create([
                'id_permiso' => $id,
                'id_usuario' => $idUsuario,
                'token'      => $token,
            ]);

            $colaborador = self::nombrePersonal((int)$permiso->id_personal, (int)$permiso->id_estacion);
            $contexto = self::getNombreLocalidad((int)$permiso->id_estacion);
$mensaje = '📲 Usa el token <b>' . $token . '</b> para firmar el visto bueno del siguiente permiso en el apartado de <b>Permisos</b>:' . PHP_EOL . PHP_EOL
    . '#️⃣ <b>No. de Control:</b> ' . $id . PHP_EOL . PHP_EOL
    . '👤 <b>Responsable:</b> ' . htmlspecialchars($colaborador, ENT_QUOTES, 'UTF-8') . PHP_EOL
    . '⛽ <b>Estación:</b> ' . $contexto;

            if ($via === 'email') {
                $usuario = Usuario::find($idUsuario);
                $email = $usuario?->email ?? '';
                if (!$email) {
                    return ['success' => false, 'message' => 'El usuario no tiene correo electrónico registrado'];
                }
                $emailService = new EmailService();
                $emailService->sendToken($email, $token, 'Permisos');
                return ['success' => true, 'message' => 'Token enviado por correo electrónico.'];
            }

            $telegram = new TelegramService();
            $telegram->sendToken($idUsuario, $mensaje);
            return ['success' => true, 'message' => 'Token enviado por Telegram.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error al crear el token: ' . $e->getMessage()];
        }
    }

    public static function firmarVoBo(int $id, int $token, int $idUsuario): array
    {
        $permisos = self::getPermisos();
        if (empty($permisos['puedeVoBo'])) {
            return ['success' => false, 'message' => 'No tienes permisos para firmar el visto bueno'];
        }

        $permiso = RhPermisos::find($id);
        if (!$permiso) {
            return ['success' => false, 'message' => 'Permiso no encontrado'];
        }
        if ((int)$permiso->estado !== 1) {
            return ['success' => false, 'message' => 'Aún no se ha firmado la firma de quien cubre'];
        }
        if (RhPermisosFirma::where('id_permiso', $id)->where('tipo_firma', 'C')->exists()) {
            return ['success' => false, 'message' => 'El visto bueno ya fue firmado'];
        }

        $tokenRecord = RhPermisosToken::where('id_permiso', $id)
            ->where('id_usuario', $idUsuario)
            ->where('token', $token)
            ->orderBy('id', 'desc')
            ->first();

        if (!$tokenRecord) {
            return ['success' => false, 'message' => 'Token no válido'];
        }

        $expirado = Carbon::parse($tokenRecord->fecha_creacion)->addMinutes(2);
        if (Carbon::now()->greaterThan($expirado)) {
            return ['success' => false, 'message' => 'El token ha expirado'];
        }

        $firmaHash = 'Firma:' . bin2hex(random_bytes(64)) . '.' . uniqid();

        RhPermisosFirma::create([
            'id_permiso' => $id,
            'id_usuario' => $idUsuario,
            'tipo_firma' => 'C',
            'firma'      => $firmaHash,
        ]);

        $permiso->update(['estado' => 2]);

        self::notificarVoBo($id, $idUsuario);

        return ['success' => true, 'message' => 'Visto bueno firmado, permiso autorizado'];
    }

    // ---------------- FIRMAS (almacenamiento) ----------------

    private static function guardarFirmaImagen(int $idPermiso, int $idUsuario, string $tipo, string $base64): bool
    {
        $img = str_replace('data:image/png;base64,', '', (string)$base64);
        $img = str_replace('data:image/jpeg;base64,', '', $img);
        if ($img === '') {
            return false;
        }

        $fileData = base64_decode($img, true);
        if ($fileData === false || $fileData === '') {
            return false;
        }

        if (!is_dir(self::UPLOAD_DIR)) {
            @mkdir(self::UPLOAD_DIR, 0775, true);
        }

        $fileName = uniqid() . '.png';
        if (!@file_put_contents(self::UPLOAD_DIR . $fileName, $fileData)) {
            return false;
        }

        try {
            RhPermisosFirma::create([
                'id_permiso' => $idPermiso,
                'id_usuario' => $idUsuario,
                'tipo_firma' => $tipo,
                'firma'      => $fileName,
            ]);
        } catch (\Exception $e) {
            @unlink(self::UPLOAD_DIR . $fileName);
            return false;
        }

        return true;
    }

    private static function eliminarFirmaArchivo(int $idPermiso, string $tipo): void
    {
        $firma = RhPermisosFirma::where('id_permiso', $idPermiso)->where('tipo_firma', $tipo)->first();
        if ($firma) {
            $ruta = self::UPLOAD_DIR . $firma->firma;
            if (is_file($ruta)) {
                @unlink($ruta);
            }
        }
    }

    // ---------------- PENDIENTES (ModuleStation selector) ----------------

    public static function getPendingCountsFlat(): array
    {
        $estacionIds = [];
        $pendientes = ['total' => 0];

        foreach (ModuleStationService::getAvailableStations(self::MODULE_KEY) as $s) {
            $id = (int)$s['id'];
            $estacionIds[] = $id;
            $pendientes['estacion_' . $id] = 0;
        }

        $deptoIds = [];
        foreach (ModuleStationService::getAvailableDepartments(self::MODULE_KEY) as $d) {
            $id = (int)$d['id'];
            $deptoIds[] = $id;
            $pendientes['depto_' . $id] = 0;
        }

        $allowed = array_values(array_unique(array_merge($estacionIds, $deptoIds)));
        if (empty($allowed)) {
            $allowed = self::getPuestoLocalidadIds();
        }

        $records = RhPermisos::query()
            ->where('estado', '<', 2)
            ->whereYear('fechacreacion', '>=', self::MIN_YEAR)
            ->where(function ($w) use ($allowed) {
                $w->whereIn('id_estacion', $allowed)->orWhereIn('estacion_cubre', $allowed);
            })
            ->get();

        $vistos = [];
        foreach ($records as $r) {
            $id = (int)$r->id;
            if (isset($vistos[$id])) {
                continue;
            }
            $vistos[$id] = true;
            $pendientes['total'] += 1;

            // Un registro cuenta una sola vez por localidad, aunque
            // id_estacion y estacion_cubre sean la misma localidad.
            $locales = [(int)$r->id_estacion];
            $locCubre = (int)$r->estacion_cubre;
            if ($locCubre > 0 && $locCubre !== $locales[0]) {
                $locales[] = $locCubre;
            }

            foreach ($locales as $loc) {
                if (in_array($loc, $estacionIds, true)) {
                    $pendientes['estacion_' . $loc] += 1;
                } elseif (in_array($loc, $deptoIds, true)) {
                    $pendientes['depto_' . $loc] += 1;
                }
            }
        }

        return $pendientes;
    }

    public static function getPendingCountsContext(): int
    {
        $ctx = ModuleStationService::getContext(self::MODULE_KEY);
        $idEstacion = $ctx['id_estacion'];
        $idDepto = $ctx['id_depto'];

        $q = RhPermisos::query()
            ->where('estado', '<', 2)
            ->whereYear('fechacreacion', '>=', self::MIN_YEAR);

        if ($idDepto && !$idEstacion) {
            $q->where(function ($w) use ($idDepto) {
                $w->where('id_estacion', $idDepto)->orWhere('estacion_cubre', $idDepto);
            });
        } elseif ($idEstacion) {
            $q->where(function ($w) use ($idEstacion) {
                $w->where('id_estacion', $idEstacion)->orWhere('estacion_cubre', $idEstacion);
            });
        } else {
            $allowed = self::getAllowedLocalidadIds();
            if (!empty($allowed)) {
                $q->where(function ($w) use ($allowed) {
                    $w->whereIn('id_estacion', $allowed)->orWhereIn('estacion_cubre', $allowed);
                });
            }
        }

        return (int)$q->count();
    }

    // ---------------- TELEGRAM ----------------

    private static function datosNotificacion(int $id): ?array
    {
        $r = RhPermisos::find($id);
        if (!$r) {
            return null;
        }

        return [
            'id'               => (int)$r->id,
            'id_estacion'      => (int)$r->id_estacion,
            'estacion'         => self::getNombreLocalidad((int)$r->id_estacion),
            'id_personal'      => (int)$r->id_personal,
            'colaborador'      => self::nombrePersonal((int)$r->id_personal, (int)$r->id_estacion),
            'cubre_turno'      => (int)$r->cubre_turno,
            'cubre'            => self::nombrePersonal((int)$r->cubre_turno, (int)$r->estacion_cubre),
            'fecha_inicio'     => $r->fecha_inicio ? formatearFecha($r->fecha_inicio) : '',
            'fecha_termino'    => $r->fecha_termino ? formatearFecha($r->fecha_termino) : '',
            'dias_tomados'     => $r->dias_tomados ?? '',
            'motivo'           => $r->motivo ?? '',
        ];
    }

    private static function bloqueDatos(array $d): string
    {
        return '👤 <b>Colaborador:</b> ' . htmlspecialchars($d['colaborador'], ENT_QUOTES, 'UTF-8') . PHP_EOL
            . '👤 <b>Quien cubre:</b> ' . htmlspecialchars($d['cubre'], ENT_QUOTES, 'UTF-8') . PHP_EOL
            . '🗓️ <b>Del:</b> ' . $d['fecha_inicio'] . PHP_EOL
            . '🗓️ <b>Al:</b> ' . $d['fecha_termino'] . PHP_EOL
            . '📅 <b>Días:</b> ' . $d['dias_tomados'] . PHP_EOL;
    }

private static function lineaEstacion(array $d): string
{
    return ($d['estacion'] ?? '') . '.';
}

    private static function notificarCrear(int $id, int $idUsuario): void
    {
        $d = self::datosNotificacion($id);
        if (!$d) {
            return;
        }

$usuario = Usuario::find($idUsuario);
$nombre = $usuario ? $usuario->nombre : 'Usuario';

$mensaje = '✅ Se ha agregado un nuevo registro en el apartado de <b>Permisos</b>:' . PHP_EOL . PHP_EOL
    . self::bloqueDatos($d)
    . '#️⃣ <b>No. de Control:</b>' . $id . PHP_EOL . PHP_EOL
    . '👤 <b>Responsable:</b> ' . $nombre . PHP_EOL
    . '⛽ <b>Estación:</b> ' . self::lineaEstacion($d);

        self::enviarTelegram($d['id_estacion'], $idUsuario, $mensaje);
    }


    
    private static function notificarEditar(int $id, int $idUsuario): void
    {
        $d = self::datosNotificacion($id);
        if (!$d) {
            return;
        }
     
        $usuario = Usuario::find($idUsuario);
$nombre = $usuario ? $usuario->nombre : 'Usuario';

$mensaje = '✏️ Se ha editado un registro en el apartado de <b>Permisos</b> (#<b>' . $id . '</b>):' . PHP_EOL . PHP_EOL
    . self::bloqueDatos($d) . PHP_EOL
    . '👤 <b>Responsable:</b> ' . $nombre . PHP_EOL
    . '⛽ <b>Estación:</b> ' . self::lineaEstacion($d);

        self::enviarTelegram($d['id_estacion'], $idUsuario, $mensaje);
    }

    private static function notificarEliminar(int $id, int $idUsuario): void
    {
        $d = self::datosNotificacion($id);
        if (!$d) {
            return;
        }
        $usuario = Usuario::find($idUsuario);
        $nombre = $usuario ? $usuario->nombre : 'Usuario';

        $mensaje = '🗑️ ' . $nombre . ' eliminó un registro en el apartado de <b>Permisos</b> (#' . $id . '):' . PHP_EOL . PHP_EOL
            . self::bloqueDatos($d)
            . self::lineaEstacion($d);

        self::enviarTelegram($d['id_estacion'], $idUsuario, $mensaje);
    }

    private static function notificarFirmaCubre(int $id, int $idUsuario): void
    {
        $d = self::datosNotificacion($id);
        if (!$d) {
            return;
        }
$usuario = Usuario::find($idUsuario);
$nombre = $usuario ? $usuario->nombre : 'Usuario';

$mensaje = '✍️ Se ha registrado la firma de quien cubre en el permiso No. 00<b>' . $id . '</b>:' . PHP_EOL . PHP_EOL
    . self::bloqueDatos($d) . PHP_EOL
    . '👤 <b>Responsable:</b> ' . $nombre . PHP_EOL
    . '⛽ <b>Estación:</b> ' . self::lineaEstacion($d);

        self::enviarTelegram($d['id_estacion'], $idUsuario, $mensaje);
    }

    private static function notificarVoBo(int $id, int $idUsuario): void
    {
        $d = self::datosNotificacion($id);
        if (!$d) {
            return;
        }

        $usuario = Usuario::find($idUsuario);
$nombre = $usuario ? $usuario->nombre : 'Usuario';

$mensaje = '✅🔚 Se ha firmado el <b>Visto Bueno</b> y autorizado el permiso No. 00<b>' . $id . '</b>:' . PHP_EOL . PHP_EOL
    . self::bloqueDatos($d) . PHP_EOL
    . '👤 <b>Responsable:</b> ' . $nombre . PHP_EOL
    . '⛽ <b>Estación:</b> ' . self::lineaEstacion($d);
    
        self::enviarTelegram($d['id_estacion'], $idUsuario, $mensaje);
    }

    private static function enviarTelegram(int $idEstacion, int $idUsuario, string $mensaje): void
    {
        try {
            $telegram = new TelegramService();

            if ($idEstacion === 9) {
                $userIds = $telegram->getUserIdsDeptoOperativo($idUsuario);
            } else {
                $userIds = $telegram->getUserIdsByStation($idEstacion, $idUsuario);
                $extraIds = $telegram->getUserIdsDeptoOperativo($idUsuario);
                $userIds = array_values(array_unique(array_merge($userIds, $extraIds)));
            }

            foreach ($userIds as $uid) {
                $telegram->sendTokenAsync($uid, $mensaje);
            }
        } catch (\Throwable $e) {
            error_log('Error en Telegram PermisosRrhh: ' . $e->getMessage());
        }
    }
}