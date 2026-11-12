<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Estacion;
use App\Models\Operativo\ComunicadoDo;
use App\Models\Operativo\ComunicadoDoAcuse;
use App\Models\Usuario;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Comunicados globales del Departamento Operativo.
 *
 * Lógica heredada de departamento-operativo (public/admin y
 * public/comunicados): un comunicado tiene Fecha, Título y un documento
 * adjunto; cada usuario puede confirmar "De Acuerdo" (acuse).
 *
 * - El acceso al módulo se resuelve con la entrada del menú del Home
 *   (roles_menus por puesto o usuarios_menus por usuario, con veto por
 *   denegación), igual que el resto de módulos del Home.
 * - Las acciones de gestión (agregar, editar, eliminar) se reservan al puesto
 *   administrador del módulo (id_puesto 13); el resto de puestos sólo puede
 *   consultar, descargar y confirmar "De Acuerdo".
 * - No hay contexto de estación: los comunicados son globales, sin selector
 *   ni alcance multiestación.
 */
class ComunicadosDoService
{
    /** Ruta del módulo en el menú del Home (mismo id que usa MenuService). */
    public const RUTA = '/comunicados';

    /** Puesto autorizado a agregar, editar y eliminar comunicados. */
    public const PUESTO_ADMIN = 13;

    /** Carpeta de archivos adjuntos (relativa a la raíz del proyecto). */
    public const UPLOAD_DIR = 'public/uploads/archivos/comunicados/';

    /** Tipo de descarga en el DownloadController. */
    public const TIPO_DESCARGA = 'comunicados';

    /** Campos del formulario, en el orden del módulo anterior. */
    public const CAMPOS = ['fecha', 'titulo'];

    /**
     * El módulo anterior aceptaba cualquier archivo; aquí se lista un
     * conjunto amplio y seguro (sin ejecutables ni PHP).
     */
    private const EXTENSIONES_PERMITIDAS = [
        'pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp',
        'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv', 'zip',
    ];

    /* ------------------------------------------------------------------ */
    /* Permisos                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Permisos efectivos del usuario actual.
     *
     * @return array{puede_consultar: bool, puede_agregar: bool, puede_editar: bool, puede_eliminar: bool, puede_descargar: bool, puede_acusar: bool, es_admin: bool}
     */
    public static function getPermisos(): array
    {
        $leer = self::tieneAccesoMenu();

        // Sólo el puesto administrador gestiona el módulo; el resto consulta.
        $gestiona = $leer && self::esPuestoAdmin();

        return [
            'puede_consultar' => $leer,
            'puede_agregar' => $gestiona,
            'puede_editar' => $gestiona,
            'puede_eliminar' => $gestiona,
            // Cualquiera con acceso puede descargar el documento (heredado).
            'puede_descargar' => $leer,
            // El puesto administrador no confirma: sólo el personal acusa.
            'puede_acusar' => $leer && !self::esPuestoAdmin(),
            // Sólo el puesto administrador ve el listado de quién visualizó.
            'es_admin' => $gestiona,
        ];
    }

    /** El usuario pertenece al puesto que administra los comunicados. */
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
     * Listado de comunicados (global) y acuses del usuario actual.
     *
     * @return array{data: array<int, array<string, mixed>>}
     */
    public static function getData(): array
    {
        $idUsuario = self::idUsuarioActual();

        $rows = ComunicadoDo::query()
            ->orderByDesc('fecha')
            ->orderByDesc('id_comunicado')
            ->get();

        $idsAcusados = $idUsuario > 0
            ? ComunicadoDoAcuse::where('id_gerente', $idUsuario)
                ->pluck('id_comunicado')
                ->map(fn ($v) => (int)$v)
                ->all()
            : [];

        $resultado = [];

        foreach ($rows as $row) {
            $resultado[] = self::filaListado($row, in_array((int)$row->id_comunicado, $idsAcusados, true));
        }

        return ['data' => $resultado];
    }

    /**
     * @return array<string, mixed>
     */
    private static function filaListado(ComunicadoDo $row, bool $acusado = false): array
    {
        $fecha = $row->fecha instanceof \DateTimeInterface
            ? $row->fecha->format('Y-m-d')
            : (string)$row->fecha;
        $archivo = (string)$row->archivo;

        return [
            'id' => (int)$row->id_comunicado,
            'titulo' => (string)$row->titulo,
            'fecha' => $fecha,
            'fecha_fmt' => formatearFecha($fecha),
            'archivo' => $archivo,
            'existe_archivo' => $archivo !== '' && self::existeArchivo($archivo),
            'acusado' => $acusado,
        ];
    }

    /**
     * Detalle de un comunicado con la lista de acuses.
     *
     * @return array{success: bool, message?: string, data?: array<string, mixed>}
     */
    public static function show(int $id): array
    {
        if (!self::getPermisos()['puede_consultar']) {
            return ['success' => false, 'message' => 'No tienes permiso de lectura para este módulo.'];
        }

        $registro = ComunicadoDo::find($id);

        if (!$registro) {
            return ['success' => false, 'message' => 'Comunicado no encontrado.'];
        }

        $idUsuario = self::idUsuarioActual();
        $acuso = $idUsuario > 0 && ComunicadoDoAcuse::where('id_comunicado', $id)
            ->where('id_gerente', $idUsuario)
            ->exists();

        $fila = self::filaListado($registro, $acuso);
        $fila['archivo_url'] = $fila['existe_archivo'] ? self::urlDescarga($fila['archivo']) : '';
        // El listado de quién visualizó sólo se expone al puesto administrador.
        $fila['acuses'] = self::esPuestoAdmin() ? self::acuses($id) : [];

        return ['success' => true, 'data' => $fila];
    }

    /**
     * Acuses de un comunicado con nombre de usuario y estación.
     *
     * @return array<int, array{usuario: string, estacion: string, fecha_fmt: string}>
     */
    private static function acuses(int $id): array
    {
        $filas = ComunicadoDoAcuse::query()
            ->where('id_comunicado', $id)
            ->orderBy('fecha')
            ->get();

        if ($filas->isEmpty()) {
            return [];
        }

        $idsUsuarios = $filas->pluck('id_gerente')->map(fn ($v) => (int)$v)->unique()->all();

        $usuarios = Usuario::whereIn('id', $idsUsuarios)->get()->keyBy('id');

        $idsEstaciones = $usuarios->pluck('id_gas')->map(fn ($v) => (int)$v)->unique()->all();
        $estaciones = Estacion::whereIn('id', $idsEstaciones)->pluck('nombre', 'id')->all();

        $resultado = [];

        foreach ($filas as $fila) {
            $idUsuarioAcuse = (int)$fila->id_gerente;
            $usuario = $usuarios->get($idUsuarioAcuse);
            $idEstacion = (int)($usuario->id_gas ?? 0);

            $fechaTs = $fila->fecha;
            $fecha = $fechaTs instanceof \DateTimeInterface ? $fechaTs->format('Y-m-d H:i:s') : (string)$fechaTs;

            $fechaFmt = formatearFecha($fecha);
            $horaFmt = ($ts = strtotime($fecha)) !== false ? date('g:i a', $ts) : '';

            $resultado[] = [
                'usuario' => (string)($usuario->nombre ?? ('Usuario #' . $idUsuarioAcuse)),
                'estacion' => (string)($estaciones[$idEstacion] ?? ''),
                'fecha_fmt' => trim($fechaFmt . ($horaFmt ? ', ' . $horaFmt : '')),
            ];
        }

        return $resultado;
    }

    public static function urlDescarga(string $archivo, bool $vista = false): string
    {
        $url = '/download?tipo=' . rawurlencode(self::TIPO_DESCARGA)
            . '&file=' . rawurlencode(basename($archivo));

        return $vista ? $url . '&view=1' : $url;
    }

    public static function existeArchivo(string $archivo): bool
    {
        if ($archivo === '') {
            return false;
        }

        return is_file(self::getUploadDir() . basename($archivo));
    }

    public static function getUploadDir(): string
    {
        return dirname(__DIR__, 2) . '/' . self::UPLOAD_DIR;
    }

    /* ------------------------------------------------------------------ */
    /* Contador del menú                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * Comunicados pendientes de acuse para un usuario: total menos los que
     * ya confirmó. Se usa para el badge del menú del Home.
     *
     * El puesto administrador no acusa, por lo que no tiene pendientes.
     */
    public static function contarPendientes(int $idUsuario): int
    {
        if ($idUsuario <= 0) {
            return 0;
        }

        $idPuesto = (int)(Usuario::where('id', $idUsuario)->value('id_puesto') ?? 0);

        if ($idPuesto === self::PUESTO_ADMIN) {
            return 0;
        }

        $total = ComunicadoDo::query()->count();

        if ($total === 0) {
            return 0;
        }

        $acuses = $idUsuario > 0
            ? ComunicadoDoAcuse::where('id_gerente', $idUsuario)->count()
            : 0;

        return max(0, (int)$total - (int)$acuses);
    }

    /* ------------------------------------------------------------------ */
    /* Escritura                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Alta de un comunicado.
     *
     * @return array{success: bool, message: string, id?: int}
     */
    public static function store(array $datos, array $files): array
    {
        if (!self::getPermisos()['puede_agregar']) {
            return ['success' => false, 'message' => 'No tienes permiso para agregar comunicados.'];
        }

        $validacion = self::validar($datos, $files, true);

        if (!$validacion['ok']) {
            return ['success' => false, 'message' => $validacion['message']];
        }

        $archivo = self::guardarArchivo($files);

        if ($archivo === null) {
            return ['success' => false, 'message' => 'No fue posible guardar el documento adjunto.'];
        }

        try {
            $registro = ComunicadoDo::create([
                'titulo' => $validacion['titulo'],
                'fecha' => $validacion['fecha'],
                'archivo' => $archivo,
            ]);
        } catch (\Throwable $e) {
            self::borrarArchivo($archivo);
            return ['success' => false, 'message' => 'Error al registrar el comunicado: ' . $e->getMessage()];
        }

        self::notificar('agregó', $registro);

        return [
            'success' => true,
            'message' => 'Comunicado registrado correctamente.',
            'id' => (int)$registro->id_comunicado,
        ];
    }

    /**
     * Actualización de un comunicado.
     *
     * @return array{success: bool, message: string}
     */
    public static function update(array $datos, array $files): array
    {
        if (!self::getPermisos()['puede_editar']) {
            return ['success' => false, 'message' => 'No tienes permiso para editar comunicados.'];
        }

        $id = (int)($datos['id'] ?? 0);
        $registro = ComunicadoDo::find($id);

        if (!$registro) {
            return ['success' => false, 'message' => 'Comunicado no encontrado.'];
        }

        $validacion = self::validar($datos, $files, false, $registro);

        if (!$validacion['ok']) {
            return ['success' => false, 'message' => $validacion['message']];
        }

        $archivoNuevo = self::guardarArchivo($files);

        if ($archivoNuevo === null) {
            return ['success' => false, 'message' => 'No fue posible guardar el documento adjunto.'];
        }

        $archivoAnterior = (string)$registro->archivo;

        try {
            $registro->fill([
                'titulo' => $validacion['titulo'],
                'fecha' => $validacion['fecha'],
                'archivo' => $archivoNuevo !== '' ? $archivoNuevo : $archivoAnterior,
            ])->save();
        } catch (\Throwable $e) {
            if ($archivoNuevo !== '') {
                self::borrarArchivo($archivoNuevo);
            }
            return ['success' => false, 'message' => 'Error al actualizar el comunicado: ' . $e->getMessage()];
        }

        // Sólo al reemplazar se retira el documento anterior del servidor.
        if ($archivoNuevo !== '' && $archivoNuevo !== $archivoAnterior) {
            self::borrarArchivo($archivoAnterior);
        }

        self::notificar('editó', $registro);

        return ['success' => true, 'message' => 'Comunicado actualizado correctamente.'];
    }

    /**
     * Baja del comunicado, de sus acuses y de su documento asociado.
     *
     * @return array{success: bool, message: string}
     */
    public static function destroy(int $id): array
    {
        if (!self::getPermisos()['puede_eliminar']) {
            return ['success' => false, 'message' => 'No tienes permiso para eliminar comunicados.'];
        }

        $registro = ComunicadoDo::find($id);

        if (!$registro) {
            return ['success' => false, 'message' => 'Comunicado no encontrado.'];
        }

        $archivo = (string)$registro->archivo;

        try {
            ComunicadoDoAcuse::where('id_comunicado', $id)->delete();

            if (!$registro->delete()) {
                return ['success' => false, 'message' => 'No fue posible eliminar el comunicado.'];
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error al eliminar el comunicado: ' . $e->getMessage()];
        }

        self::borrarArchivo($archivo);

        self::notificar('eliminó', $registro);

        return ['success' => true, 'message' => 'Comunicado eliminado correctamente.'];
    }

    /**
     * Registra el acuse "De Acuerdo" del usuario actual.
     *
     * @return array{success: bool, message: string}
     */
    public static function acuse(int $id): array
    {
        if (!self::getPermisos()['puede_acusar']) {
            return ['success' => false, 'message' => 'No tienes permiso para confirmar este comunicado.'];
        }

        $registro = ComunicadoDo::find($id);

        if (!$registro) {
            return ['success' => false, 'message' => 'Comunicado no encontrado.'];
        }

        $idUsuario = self::idUsuarioActual();

        if ($idUsuario <= 0) {
            return ['success' => false, 'message' => 'No se pudo identificar al usuario.'];
        }

        $yaAcusado = ComunicadoDoAcuse::where('id_comunicado', $id)
            ->where('id_gerente', $idUsuario)
            ->exists();

        if ($yaAcusado) {
            return ['success' => true, 'message' => 'Ya habías confirmado este comunicado.'];
        }

        try {
            // La columna fecha usa el DEFAULT current_timestamp de la tabla.
            ComunicadoDoAcuse::create([
                'id_comunicado' => $id,
                'id_gerente' => $idUsuario,
            ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error al registrar el acuse: ' . $e->getMessage()];
        }

        self::notificar('confirmó', $registro);

        return ['success' => true, 'message' => 'El comunicado ha sido marcado como visto.'];
    }

    /* ------------------------------------------------------------------ */
    /* Validación y archivos                                               */
    /* ------------------------------------------------------------------ */

    /**
     * @return array{ok: bool, message?: string, fecha?: string, titulo?: string}
     */
    private static function validar(array $datos, array $files, bool $esAlta, ?ComunicadoDo $registro = null): array
    {
        $valores = [];

        foreach (self::CAMPOS as $campo) {
            $valor = trim((string)($datos[$campo] ?? ''));

            // En edición un campo vacío conserva el valor actual.
            if ($valor === '' && !$esAlta && $registro !== null) {
                $valor = trim((string)($registro->{$campo}));
            }

            $valores[$campo] = $valor;
        }

        if ($valores['fecha'] === '') {
            return ['ok' => false, 'message' => 'La fecha es obligatoria.'];
        }

        $objetoFecha = \DateTime::createFromFormat('Y-m-d', $valores['fecha']);

        if (!$objetoFecha || $objetoFecha->format('Y-m-d') !== $valores['fecha']) {
            return ['ok' => false, 'message' => 'La fecha no es válida.'];
        }

        if ($valores['titulo'] === '') {
            return ['ok' => false, 'message' => 'El título es obligatorio.'];
        }

        if ($esAlta) {
            if (empty($files['archivo']['name'])) {
                return ['ok' => false, 'message' => 'El documento adjunto es obligatorio.'];
            }

            $error = self::validarArchivo($files['archivo']);

            if ($error !== null) {
                return ['ok' => false, 'message' => $error];
            }
        } elseif (!empty($files['archivo']['name'])) {
            $error = self::validarArchivo($files['archivo']);

            if ($error !== null) {
                return ['ok' => false, 'message' => $error];
            }
        }

        return array_merge(['ok' => true], $valores);
    }

    private static function validarArchivo(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return 'El archivo no se subió correctamente.';
        }

        if ((int)($file['size'] ?? 0) > 10 * 1024 * 1024) {
            return 'El archivo no puede superar los 10 MB.';
        }

        $extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));

        if ($extension === '' || !in_array($extension, self::EXTENSIONES_PERMITIDAS, true)) {
            return 'Tipo de archivo no permitido.';
        }

        return null;
    }

    /**
     * Guarda el documento con prefijo único, como hacía el módulo anterior.
     * Devuelve '' cuando no viene archivo, null si falla el guardado.
     */
    private static function guardarArchivo(array $files): ?string
    {
        if (empty($files['archivo']['name'])) {
            return '';
        }

        $directorio = self::getUploadDir();

        if (!is_dir($directorio) && !mkdir($directorio, 0755, true) && !is_dir($directorio)) {
            return null;
        }

        $original = basename((string)$files['archivo']['name']);
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $nombre = uniqid() . '-' . $original;

        if (pathinfo($nombre, PATHINFO_EXTENSION) !== $extension) {
            $nombre .= '.' . $extension;
        }

        if (!move_uploaded_file($files['archivo']['tmp_name'], $directorio . $nombre)) {
            return null;
        }

        return $nombre;
    }

    private static function borrarArchivo(string $archivo): void
    {
        if ($archivo === '') {
            return;
        }

        $ruta = self::getUploadDir() . basename($archivo);

        if (is_file($ruta)) {
            @unlink($ruta);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Telegram                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Usuarios con acceso al módulo (menú del Home), excluyendo al autor.
     *
     * @return array<int, int>
     */
    public static function getUserIdsConAcceso(int $excludeUserId = 0): array
    {
        $menuId = self::idMenu();

        if ($menuId === null) {
            return [];
        }

        $puestos = Capsule::table('roles_menus')
            ->where('menu_id', $menuId)
            ->pluck('puesto_id')
            ->map(fn ($v) => (int)$v)
            ->all();

        $permitidos = Capsule::table('usuarios_menus')
            ->where('menu_id', $menuId)
            ->where('tipo', 'permitir')
            ->pluck('usuario_id')
            ->map(fn ($v) => (int)$v)
            ->all();

        $denegados = Capsule::table('usuarios_menus')
            ->where('menu_id', $menuId)
            ->where('tipo', 'denegar')
            ->pluck('usuario_id')
            ->map(fn ($v) => (int)$v)
            ->all();

        $query = Usuario::where('estatus', 0);

        if (empty($puestos) && empty($permitidos)) {
            return [];
        }

        $query->where(function ($q) use ($puestos, $permitidos) {
            if (!empty($puestos)) {
                $q->whereIn('id_puesto', $puestos);
            }
            if (!empty($permitidos)) {
                $metodo = empty($puestos) ? 'whereIn' : 'orWhereIn';
                $q->{$metodo}('id', $permitidos);
            }
        });

        if ($excludeUserId > 0) {
            $query->where('id', '!=', $excludeUserId);
        }

        if (!empty($denegados)) {
            $query->whereNotIn('id', $denegados);
        }

        return $query->pluck('id')->map(fn ($v) => (int)$v)->all();
    }

    /**
     * Notifica sólo después de que la operación se completó con éxito.
     * No hay enrutado por estación: se avisa a quien tiene acceso al módulo.
     */
    private static function notificar(string $accion, ComunicadoDo $registro): void
    {
        try {
            $idUsuario = self::idUsuarioActual();
            $nombreUsuario = (string)(Session::get('usuario')['nombre'] ?? Auth::user()->nombre ?? 'Usuario');

            $mensaje = sprintf(
                "<b>Comunicados</b>\n\n%s %s el comunicado #%d.\nTítulo: <b>%s</b>",
                htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8'),
                $accion,
                (int)$registro->id_comunicado,
                htmlspecialchars((string)$registro->titulo, ENT_QUOTES, 'UTF-8')
            );

            $telegram = new TelegramService();

            foreach (self::getUserIdsConAcceso($idUsuario) as $uid) {
                $telegram->sendTokenAsync($uid, $mensaje);
            }
        } catch (\Throwable $e) {
            // Una notificación fallida no debe romper la operación ya guardada.
            error_log('[Comunicados] Error notificando por Telegram: ' . $e->getMessage());
        }
    }

    /* ------------------------------------------------------------------ */
    /* Utilidades                                                           */
    /* ------------------------------------------------------------------ */

    private static function idUsuarioActual(): int
    {
        return (int)(Session::get('usuario')['id'] ?? 0);
    }
}
