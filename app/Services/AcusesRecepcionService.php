<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Estacion;
use App\Models\Operativo\AcuseRecepcion;
use App\Models\Operativo\AcuseRecepcionDocumento;
use App\Models\Usuario;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Acuses de Recepción del Departamento Operativo.
 *
 * Lógica heredada de departamento-operativo (public/acuses-recepcion): un
 * acuse registra la entrega de documentos a una empresa con un estado
 * (0 = Pendiente, 1 = En proceso, 2 = Finalizado) y una lista de documentos
 * (nombre, número de páginas, original, copia). El acuse se crea vacío, se le
 * agregan documentos mientras está en proceso y se finaliza indicando la
 * empresa y quién recibe.
 *
 * - El acceso al módulo se resuelve con la entrada del menú del Home
 *   (roles_menus por puesto o usuarios_menus por usuario, con veto por
 *   denegación), igual que el resto de módulos del Home.
 * - Las operaciones de gestión (agregar, editar documentos, finalizar y
 *   eliminar) se reservan al puesto que administra el módulo en el sistema
 *   heredado (id_puesto 13, Dirección de Operaciones); el resto de puestos
 *   sólo consulta. Se valida tanto en la interfaz como en el backend.
 * - No hay contexto de estación: los acuses son globales, sin selector ni
 *   alcance multiestación.
 */
class AcusesRecepcionService
{
    /** Ruta del módulo en el menú del Home. */
    public const RUTA = '/acuses-recepcion';

    /** Puesto autorizado a gestionar los acuses de recepción. */
    public const PUESTO_ADMIN = 13;

    /** Empresas (razón social) que pueden registrarse, ordenadas por numlista. */
    public const EMPRESAS_LIMITE_NUMLISTA = 8;

    /** Estados del acuse, tal como los maneja el módulo anterior. */
    public const ESTADO_PENDIENTE = 0;
    public const ESTADO_PROCESO = 1;
    public const ESTADO_FINALIZADO = 2;

    /* ------------------------------------------------------------------ */
    /* Permisos                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Permisos efectivos del usuario actual.
     *
     * @return array{puede_consultar: bool, puede_agregar: bool, puede_editar: bool, puede_eliminar: bool, es_admin: bool}
     */
    public static function getPermisos(): array
    {
        $leer = self::tieneAccesoMenu();
        $gestiona = $leer && self::esPuestoAdmin();

        return [
            'puede_consultar' => $leer,
            // Alta del acuse, agregar documentos, finalizar y eliminar.
            'puede_agregar' => $gestiona,
            'puede_editar' => $gestiona,
            'puede_eliminar' => $gestiona,
            // El listado de empresas del formulario sólo aplica al gestor.
            'es_admin' => $gestiona,
        ];
    }

    /** El usuario pertenece al puesto que administra los acuses. */
    private static function esPuestoAdmin(): bool
    {
        $usuario = Auth::user();

        if ($usuario !== null && isset($usuario->id_puesto)) {
            return (int)$usuario->id_puesto === self::PUESTO_ADMIN;
        }

        $sesion = Session::get('usuario');

        return (int)($sesion['id_puesto'] ?? 0) === self::PUESTO_ADMIN;
    }

    /** Id de la entrada del menú del Home que habilita este módulo. */
    public static function idMenu(): ?int
    {
        static $id = null;

        if ($id === null) {
            $fila = Capsule::table('menus')
                ->where('ruta', self::RUTA)
                ->where('activo', 1)
                ->first();

            $id = $fila ? (int)$fila->id : 0;
        }

        return $id > 0 ? $id : null;
    }

    /**
     * El usuario tiene concedido el módulo en el menú del Home.
     * Misma precedencia que usa MenuService: puesto o usuario (permitir),
     * siempre por encima de una denegación explícita.
     */
    private static function tieneAccesoMenu(): bool
    {
        $menuId = self::idMenu();

        if ($menuId === null) {
            return false;
        }

        $usuario = Auth::user();
        $sesionUsuario = Session::get('usuario');
        $idUsuario = (int)($sesionUsuario['id'] ?? 0);
        $idPuesto = (int)($usuario->id_puesto ?? 0);

        $denegado = Capsule::table('usuarios_menus')
            ->where('menu_id', $menuId)
            ->where('usuario_id', $idUsuario)
            ->where('tipo', 'denegar')
            ->exists();

        if ($denegado) {
            return false;
        }

        if ($idUsuario > 0 && Capsule::table('usuarios_menus')
            ->where('menu_id', $menuId)
            ->where('usuario_id', $idUsuario)
            ->where('tipo', 'permitir')
            ->exists()) {
            return true;
        }

        return $idPuesto > 0 && Capsule::table('roles_menus')
            ->where('menu_id', $menuId)
            ->where('puesto_id', $idPuesto)
            ->exists();
    }

    /* ------------------------------------------------------------------ */
    /* Lectura                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Listado de acuses según el filtro del buscador heredado.
     *
     * - '' (por defecto): estados Pendiente y En proceso, sin ordenar.
     * - '0': todos, ordenados por estado y fecha de creación descendente.
     * - '1': sólo En proceso, por fecha de creación descendente.
     * - '2': sólo Finalizados, por fecha de creación descendente.
     * - '3': sólo Pendientes, por fecha de creación descendente.
     *
     * @return array{data: array<int, array<string, mixed>>}
     */
    public static function getData(string $buscar = ''): array
    {
        $query = AcuseRecepcion::query();

        switch ($buscar) {
            case '0':
                $query->orderBy('estado')->orderByDesc('fecha_creacion');
                break;
            case '1':
                $query->where('estado', self::ESTADO_PROCESO)->orderByDesc('fecha_creacion');
                break;
            case '2':
                $query->where('estado', self::ESTADO_FINALIZADO)->orderByDesc('fecha_creacion');
                break;
            case '3':
                $query->where('estado', self::ESTADO_PENDIENTE)->orderByDesc('fecha_creacion');
                break;
            default:
                $query->whereIn('estado', [self::ESTADO_PENDIENTE, self::ESTADO_PROCESO, self::ESTADO_FINALIZADO]);
                break;
        }

        $rows = $query->get();

        $ids = $rows->pluck('id')->map(fn ($v) => (int)$v)->all();
        $documentos = self::documentosPorAcuse($ids);

        $resultado = [];

        foreach ($rows as $row) {
            $resultado[] = self::filaListado($row, $documentos[(int)$row->id] ?? []);
        }

        return ['data' => $resultado];
    }

    /**
     * Documentos de un acuse agrupados por id_acuse.
     *
     * @param array<int, int> $ids
     * @return array<int, array<int, array<string, mixed>>>
     */
    private static function documentosPorAcuse(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $filas = AcuseRecepcionDocumento::query()
            ->whereIn('id_acuse', $ids)
            ->orderBy('id')
            ->get();

        $agrupado = [];

        foreach ($filas as $fila) {
            $agrupado[(int)$fila->id_acuse][] = [
                'id' => (int)$fila->id,
                'documento' => (string)$fila->documento,
                'paginas' => (int)$fila->paginas,
                'original' => (int)$fila->original === 1,
                'copia' => (int)$fila->copia === 1,
            ];
        }

        return $agrupado;
    }

    /**
     * @param array<int, array<string, mixed>> $documentos
     * @return array<string, mixed>
     */
    private static function filaListado(AcuseRecepcion $row, array $documentos = []): array
    {
        $fechaCreacion = $row->fecha_creacion instanceof \DateTimeInterface
            ? $row->fecha_creacion->format('Y-m-d H:i:s')
            : (string)$row->fecha_creacion;

        $fechaEntrega = $row->fecha instanceof \DateTimeInterface
            ? $row->fecha->format('Y-m-d H:i:s')
            : (string)$row->fecha;

        $nombres = self::documentosResumen($documentos);

        return [
            'id' => (int)$row->id,
            'estado' => (int)$row->estado,
            'estado_texto' => self::estadoTexto((int)$row->estado),
            'estado_color' => self::estadoColor((int)$row->estado),
            'empresa' => (string)$row->empresa,
            'fecha_creacion' => $fechaCreacion,
            'fecha_creacion_fmt' => self::formatearFechaHora($fechaCreacion),
            'fecha_entrega' => $fechaEntrega,
            'fecha_entrega_fmt' => self::formatearFechaHora($fechaEntrega),
            'nombre_recibe' => (string)$row->nombre_recibe,
            'entrega' => self::nombreUsuario((int)$row->personal_entrega),
            'id_personal' => (int)$row->id_personal,
            'personal_entrega' => (int)$row->personal_entrega,
            'creado_por' => self::nombreUsuario((int)$row->id_personal),
            'documentos' => $documentos,
            'documentos_resumen' => $nombres,
        ];
    }

    /** "(1. Acta, 2. Factura)" como en el módulo anterior. */
    private static function documentosResumen(array $documentos): string
    {
        if (empty($documentos)) {
            return '()';
        }

        $partes = [];

        foreach ($documentos as $i => $doc) {
            $partes[] = ($i + 1) . '. ' . $doc['documento'];
        }

        return '(' . implode(', ', $partes) . ')';
    }

    public static function estadoTexto(int $estado): string
    {
        return match ($estado) {
            self::ESTADO_PENDIENTE => 'Pendiente',
            self::ESTADO_PROCESO => 'En proceso',
            self::ESTADO_FINALIZADO => 'Finalizado',
            default => 'Desconocido',
        };
    }

    public static function estadoColor(int $estado): string
    {
        return match ($estado) {
            self::ESTADO_PENDIENTE => 'danger',
            self::ESTADO_PROCESO => 'warning',
            self::ESTADO_FINALIZADO => 'success',
            default => 'secondary',
        };
    }

    /**
     * Detalle de un acuse con sus documentos, empresa y quién entrega/recibe.
     *
     * @return array{success: bool, message?: string, data?: array<string, mixed>}
     */
    public static function show(int $id): array
    {
        if (!self::getPermisos()['puede_consultar']) {
            return ['success' => false, 'message' => 'No tienes permiso de lectura para este módulo.'];
        }

        $registro = AcuseRecepcion::find($id);

        if (!$registro) {
            return ['success' => false, 'message' => 'Acuse de recepción no encontrado.'];
        }

        $documentos = self::documentosPorAcuse([$id])[$id] ?? [];

        return ['success' => true, 'data' => self::filaListado($registro, $documentos)];
    }

    /**
     * Documentos de un acuse (endpoint dedicado, usado en el modal de gestión).
     *
     * @return array{success: bool, message?: string, data?: array<int, array<string, mixed>>}
     */
    public static function documentos(int $id): array
    {
        if (!self::getPermisos()['puede_consultar']) {
            return ['success' => false, 'message' => 'No tienes permiso de lectura para este módulo.'];
        }

        if (!AcuseRecepcion::whereKey($id)->exists()) {
            return ['success' => false, 'message' => 'Acuse de recepción no encontrado.'];
        }

        $documentos = self::documentosPorAcuse([$id])[$id] ?? [];

        $resultado = [];

        foreach ($documentos as $i => $doc) {
            $doc['num'] = $i + 1;
            $resultado[] = $doc;
        }

        return ['success' => true, 'data' => $resultado];
    }

    /** Empresas (razón social) que ofrece el formulario de finalización. */
    public static function empresas(): array
    {
        return Estacion::query()
            ->where('numlista', '<=', self::EMPRESAS_LIMITE_NUMLISTA)
            ->orderBy('numlista')
            ->pluck('razonsocial')
            ->map(fn ($v) => (string)$v)
            ->values()
            ->all();
    }

    /* ------------------------------------------------------------------ */
    /* Escritura                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Alta de un acuse vacío (Pendiente) y abre su gestión.
     *
     * @return array{success: bool, message: string, id?: int}
     */
    public static function store(): array
    {
        if (!self::getPermisos()['puede_agregar']) {
            return ['success' => false, 'message' => 'No tienes permiso para agregar acuses de recepción.'];
        }

        $idUsuario = self::idUsuarioActual();

        if ($idUsuario <= 0) {
            return ['success' => false, 'message' => 'No se pudo identificar al usuario.'];
        }

        try {
            $registro = AcuseRecepcion::create([
                'id_personal' => $idUsuario,
                'empresa' => '',
                'personal_entrega' => 0,
                'nombre_recibe' => '',
                'estado' => self::ESTADO_PENDIENTE,
            ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error al registrar el acuse: ' . $e->getMessage()];
        }

        self::notificar('creó', $registro);

        return [
            'success' => true,
            'message' => 'Acuse de recepción creado correctamente.',
            'id' => (int)$registro->id,
        ];
    }

    /**
     * Agrega un documento al acuse mientras está en proceso.
     *
     * @return array{success: bool, message: string, id?: int}
     */
    public static function agregarDocumento(array $datos): array
    {
        if (!self::getPermisos()['puede_editar']) {
            return ['success' => false, 'message' => 'No tienes permiso para agregar documentos.'];
        }

        $id = (int)($datos['id'] ?? 0);
        $registro = AcuseRecepcion::find($id);

        if (!$registro) {
            return ['success' => false, 'message' => 'Acuse de recepción no encontrado.'];
        }

        if ((int)$registro->estado !== self::ESTADO_PENDIENTE) {
            return ['success' => false, 'message' => 'Solo se pueden agregar documentos mientras el acuse está Pendiente.'];
        }

        $documento = trim((string)($datos['documento'] ?? ''));
        $paginas = (int)($datos['paginas'] ?? 0);

        if ($documento === '') {
            return ['success' => false, 'message' => 'El nombre del documento es obligatorio.'];
        }

        if ($paginas <= 0) {
            return ['success' => false, 'message' => 'El número de páginas es obligatorio.'];
        }

        $original = !empty($datos['original']) ? 1 : 0;
        $copia = !empty($datos['copia']) ? 1 : 0;

        try {
            $doc = AcuseRecepcionDocumento::create([
                'id_acuse' => $id,
                'documento' => $documento,
                'paginas' => $paginas,
                'original' => $original,
                'copia' => $copia,
            ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error al agregar el documento: ' . $e->getMessage()];
        }

        self::notificar('agregó un documento a', $registro);

        return [
            'success' => true,
            'message' => 'Documento agregado exitosamente.',
            'id' => (int)$doc->id,
        ];
    }

    /**
     * Elimina un documento del acuse.
     *
     * @return array{success: bool, message: string}
     */
    public static function eliminarDocumento(int $idDocumento): array
    {
        if (!self::getPermisos()['puede_editar']) {
            return ['success' => false, 'message' => 'No tienes permiso para eliminar documentos.'];
        }

        $documento = AcuseRecepcionDocumento::find($idDocumento);

        if (!$documento) {
            return ['success' => false, 'message' => 'Documento no encontrado.'];
        }

        $registro = AcuseRecepcion::find((int)$documento->id_acuse);

        if ($registro && (int)$registro->estado !== self::ESTADO_PENDIENTE) {
            return ['success' => false, 'message' => 'Solo se pueden eliminar documentos mientras el acuse está Pendiente.'];
        }

        try {
            $documento->delete();
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error al eliminar el documento: ' . $e->getMessage()];
        }

        self::notificar('eliminó un documento de', $registro);

        return ['success' => true, 'message' => 'Documento eliminado exitosamente.'];
    }

    /**
     * Guarda la empresa y pasa el acuse de Pendiente a En proceso.
     * Equivale al paso dato=1 del módulo anterior (empresa + estado=1).
     *
     * @return array{success: bool, message: string}
     */
    public static function guardarEmpresa(array $datos): array
    {
        if (!self::getPermisos()['puede_editar']) {
            return ['success' => false, 'message' => 'No tienes permiso para editar acuses.'];
        }

        $id = (int)($datos['id'] ?? 0);
        $registro = AcuseRecepcion::find($id);

        if (!$registro) {
            return ['success' => false, 'message' => 'Acuse de recepción no encontrado.'];
        }

        if ((int)$registro->estado === self::ESTADO_FINALIZADO) {
            return ['success' => false, 'message' => 'El acuse ya está finalizado y no admite cambios.'];
        }

        $empresa = trim((string)($datos['empresa'] ?? ''));

        if ($empresa === '') {
            return ['success' => false, 'message' => 'La empresa es obligatoria.'];
        }

        try {
            $registro->fill([
                'empresa' => $empresa,
                'estado' => self::ESTADO_PROCESO,
            ])->save();
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error al guardar la empresa: ' . $e->getMessage()];
        }

        self::notificar('pasó a entrega', $registro);

        return ['success' => true, 'message' => 'Acuse en proceso de entrega.'];
    }

    /**
     * Finaliza el acuse indicando quién recibe; pasa a estado Finalizado.
     * Equivale al paso dato=2 del módulo anterior (personal_entrega +
     * nombre_recibe + fecha + estado=2). Sólo aplica a acuses En proceso.
     *
     * @return array{success: bool, message: string}
     */
    public static function finalizar(array $datos): array
    {
        if (!self::getPermisos()['puede_editar']) {
            return ['success' => false, 'message' => 'No tienes permiso para finalizar acuses.'];
        }

        $id = (int)($datos['id'] ?? 0);
        $registro = AcuseRecepcion::find($id);

        if (!$registro) {
            return ['success' => false, 'message' => 'Acuse de recepción no encontrado.'];
        }

        if ((int)$registro->estado === self::ESTADO_FINALIZADO) {
            return ['success' => false, 'message' => 'El acuse ya está finalizado.'];
        }

        if ((int)$registro->estado !== self::ESTADO_PROCESO) {
            return ['success' => false, 'message' => 'El acuse debe estar en proceso de entrega antes de finalizar.'];
        }

        $quienRecibe = trim((string)($datos['quien_recibe'] ?? ''));

        if ($quienRecibe === '') {
            return ['success' => false, 'message' => 'El nombre de quién recibe es obligatorio.'];
        }

        $idUsuario = self::idUsuarioActual();

        try {
            $registro->fill([
                'personal_entrega' => $idUsuario,
                'nombre_recibe' => $quienRecibe,
                'fecha' => date('Y-m-d H:i:s'),
                'estado' => self::ESTADO_FINALIZADO,
            ])->save();
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error al finalizar el acuse: ' . $e->getMessage()];
        }

        self::notificar('finalizó', $registro);

        return ['success' => true, 'message' => 'Acuse de recepción finalizado.'];
    }

    /**
     * Baja del acuse y de todos sus documentos.
     *
     * @return array{success: bool, message: string}
     */
    public static function destroy(int $id): array
    {
        if (!self::getPermisos()['puede_eliminar']) {
            return ['success' => false, 'message' => 'No tienes permiso para eliminar acuses de recepción.'];
        }

        $registro = AcuseRecepcion::find($id);

        if (!$registro) {
            return ['success' => false, 'message' => 'Acuse de recepción no encontrado.'];
        }

        try {
            AcuseRecepcionDocumento::where('id_acuse', $id)->delete();

            if (!$registro->delete()) {
                return ['success' => false, 'message' => 'No fue posible eliminar el acuse de recepción.'];
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error al eliminar el acuse de recepción: ' . $e->getMessage()];
        }

        self::notificar('eliminó', $registro);

        return ['success' => true, 'message' => 'Acuse de recepción eliminado correctamente.'];
    }

    /* ------------------------------------------------------------------ */
    /* Telegram                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Notifica sólo después de que la operación se completó con éxito.
     * No hay enrutado por estación: se avisa al puesto que administra el
     * módulo (misma audiencia que el módulo anterior).
     */
    private static function notificar(string $accion, ?AcuseRecepcion $registro): void
    {
        if ($registro === null) {
            return;
        }

        try {
            $idUsuario = self::idUsuarioActual();
            $nombreUsuario = (string)(Session::get('usuario')['nombre'] ?? Auth::user()->nombre ?? 'Usuario');

            $mensaje = sprintf(
                "<b>Acuses de Recepción</b>\n\n%s %s el acuse #%d.\nEmpresa: <b>%s</b>\nEstado: <b>%s</b>",
                htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8'),
                $accion,
                (int)$registro->id,
                htmlspecialchars((string)($registro->empresa !== '' ? $registro->empresa : 'Sin empresa'), ENT_QUOTES, 'UTF-8'),
                self::estadoTexto((int)$registro->estado)
            );

            $telegram = new TelegramService();

            foreach ($telegram->getUserIdsByPuesto(self::PUESTO_ADMIN, $idUsuario) as $uid) {
                $telegram->sendTokenAsync($uid, $mensaje);
            }
        } catch (\Throwable $e) {
            // Una notificación fallida no debe romper la operación ya guardada.
            error_log('[AcusesRecepcion] Error notificando por Telegram: ' . $e->getMessage());
        }
    }

    /* ------------------------------------------------------------------ */
    /* Utilidades                                                           */
    /* ------------------------------------------------------------------ */

    private static function idUsuarioActual(): int
    {
        return (int)(Session::get('usuario')['id'] ?? 0);
    }

    private static function nombreUsuario(int $id): string
    {
        if ($id <= 0) {
            return '';
        }

        $nombre = Usuario::where('id', $id)->value('nombre');

        return $nombre !== null ? (string)$nombre : '';
    }

    /** "2026-10-19 15:04:05" -> "19 de Octubre del 2026, 3:04 pm". */
    private static function formatearFechaHora(string $fecha): string
    {
        if ($fecha === '') {
            return '';
        }

        // Fechas nulas/cero (MySQL las entrega como '0000-00-00' o casts
        // negativos) no se muestran.
        if (str_starts_with($fecha, '0000-00-00') || ($fecha[0] ?? '') === '-') {
            return '';
        }

        $ts = strtotime($fecha);

        if ($ts === false || $ts <= 0) {
            return '';
        }

        $fechaFmt = formatearFecha(date('Y-m-d', $ts));
        $horaFmt = date('g:i a', $ts);

        return trim($fechaFmt . ($horaFmt ? ', ' . $horaFmt : ''));
    }
}
