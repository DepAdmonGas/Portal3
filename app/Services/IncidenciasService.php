<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Estacion;
use App\Models\Operativo\IncidenciaEstacion;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Incidencias por estación.
 *
 * Lógica heredada del módulo anterior (departamento-operativo/public/
 * estacion-incidencias): un registro por estación con fecha, hora,
 * incidente, responsable, asunto, comentarios y un archivo adjunto.
 *
 * - El acceso al módulo se resuelve con la entrada del menú del Home
 *   (roles_menus por puesto o usuarios_menus por usuario, con veto por
 *   denegación), igual que el resto de módulos del Home.
 * - Las acciones (agregar, editar, eliminar, descargar) se resuelven con
 *   ModuloService sobre el módulo del Home "departamento-operativo", bajo
 *   el que nació el módulo anterior: precedencia usuario > puesto con las
 *   columnas leer/crear/editar/eliminar/descargar.
 * - El contexto de estación lo administra ModuleStationService (selector
 *   compartido). Con "todas" el alcance es el catálogo de estaciones
 *   permitidas; con una estación elegida sólo esa.
 */
class IncidenciasService
{
    /** Clave del módulo en tb_modulos_config y en module_context. */
    public const CLAVE = 'incidencias';

    /** Alias usado por el resto de la vista (selector de estación). */
    public const MODULE_KEY = 'incidencias';

    /** Ruta del módulo en el menú del Home. */
    public const RUTA = '/incidencias';

    /**
     * Módulo del Home donde se resuelven las acciones. Es el mismo bajo el
     * que operaba el módulo anterior; aquí no se crean ni modifican grants.
     */
    public const MODULO_PERMISOS = 'departamento-operativo';

    /** Carpeta de archivos adjuntos (relativa a la raíz del proyecto). */
    public const UPLOAD_DIR = 'public/uploads/archivos/incidencias/';

    /** Tipo de descarga en el DownloadController. */
    public const TIPO_DESCARGA = 'incidencias';

    /** Campos del formulario, en el orden del módulo anterior. */
    public const CAMPOS = ['fecha', 'hora', 'incidente', 'responsable', 'asunto', 'comentarios'];

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
     * @return array{puede_consultar: bool, puede_agregar: bool, puede_editar: bool, puede_eliminar: bool, puede_descargar: bool}
     */
    public static function getPermisos(): array
    {
        $leer = self::tieneAccesoMenu() && self::modulo('leer');

        return [
            'puede_consultar' => $leer,
            // El alta está abierta a todo usuario con acceso al módulo.
            'puede_agregar' => $leer,
            'puede_editar' => $leer && self::modulo('editar'),
            'puede_eliminar' => $leer && self::modulo('eliminar'),
            'puede_descargar' => $leer && self::modulo('descargar'),
        ];
    }

    /**
     * Acción concedida en ModuloService con precedencia usuario > puesto.
     * Mismo patrón que SalaJuntasService: sesión primero, luego BD.
     */
    private static function modulo(string $accion): bool
    {
        $permisos = ModuloService::permisosSesion(self::MODULO_PERMISOS) ?: [];

        if (empty($permisos)) {
            $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);
            $permisos = $idUsuario > 0
                ? (ModuloService::getPermiso($idUsuario, self::MODULO_PERMISOS) ?: [])
                : [];
        }

        return !empty($permisos[$accion]);
    }

    /** Id de la entrada del menú del Home que habilita este módulo. */
    private static function idMenu(): ?int
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
     * siempreabierta por una denegación explícita.
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
    /* Contexto de estación                                                */
    /* ------------------------------------------------------------------ */

    /**
     * Contexto activo del módulo (id de tb_estaciones).
     * null = "todas las estaciones permitidas".
     */
    public static function getContexto(): ?int
    {
        $ctx = ModuleStationService::getContext(self::CLAVE);

        $id = $ctx['id_estacion'] ?? null;

        return $id !== null ? (int)$id : null;
    }

    /**
     * Estaciones que puede consultar el usuario (id de tb_estaciones).
     *
     * @return array<int, int>
     */
    public static function getEstacionesPermitidas(): array
    {
        $ids = [];

        foreach (ModuleStationService::getAvailableStations(self::CLAVE) as $estacion) {
            $ids[] = (int)$estacion['id'];
        }

        return array_values(array_unique($ids));
    }

    /**
     * Ids de estación consultables en este momento.
     *
     * @return array<int, int>
     */
    public static function getAlcance(): array
    {
        $permitidas = self::getEstacionesPermitidas();
        $contexto = self::getContexto();

        if ($contexto === null) {
            return $permitidas;
        }

        // Backend: la selección nunca amplía el alcance.
        return in_array($contexto, $permitidas, true) ? [$contexto] : [];
    }

    /**
     * La columna Estación sólo se muestra cuando el selector está en
     * "todas": con una estación elegida sería redundante.
     */
    public static function mostrarColumnaEstacion(): bool
    {
        return self::getContexto() === null;
    }

    /**
     * La estación elegida (contexto o id del request) está en el alcance.
     */
    private static function estacionValida(int $idEstacion): bool
    {
        return $idEstacion > 0 && in_array($idEstacion, self::getAlcance(), true);
    }

    /**
     * Estaciones permitidas con su nombre, para el alta sin estación fija.
     *
     * @return array<int, array{id: int, nombre: string}>
     */
    public static function estacionesPermitidasConNombre(): array
    {
        $ids = self::getEstacionesPermitidas();

        if (empty($ids)) {
            return [];
        }

        $nombres = self::nombresEstaciones($ids);
        $resultado = [];

        foreach ($ids as $id) {
            $resultado[] = [
                'id' => $id,
                'nombre' => $nombres[$id] ?? ('Est #' . $id),
            ];
        }

        return $resultado;
    }

    /**
     * Nombres de las estaciones dadas (o del alcance completo).
     *
     * @param array<int, int> $ids
     * @return array<int, string>
     */
    private static function nombresEstaciones(array $ids = []): array
    {
        if (empty($ids)) {
            $ids = self::getAlcance();
        }

        if (empty($ids)) {
            return [];
        }

        return Estacion::whereIn('id', $ids)
            ->pluck('nombre', 'id')
            ->map(fn ($nombre) => (string)$nombre)
            ->all();
    }

    /* ------------------------------------------------------------------ */
    /* Lectura                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Listado de incidencias dentro del alcance del usuario.
     *
     * @return array{data: array<int, array<string, mixed>>, mostrar_fila_estacion: bool}
     */
    public static function getData(): array
    {
        $alcance = self::getAlcance();
        $mostrarFila = self::mostrarColumnaEstacion();

        if (empty($alcance)) {
            return ['data' => [], 'mostrar_fila_estacion' => $mostrarFila];
        }

        $nombres = self::nombresEstaciones($alcance);

        $rows = IncidenciaEstacion::query()
            ->whereIn('id_estacion', $alcance)
            ->orderByDesc('id_incidencias_estaciones')
            ->get();

        $resultado = [];

        foreach ($rows as $row) {
            $resultado[] = self::filaListado($row, $nombres);
        }

        return ['data' => $resultado, 'mostrar_fila_estacion' => $mostrarFila];
    }

    /**
     * @param array<int, string> $nombres
     * @return array<string, mixed>
     */
    private static function filaListado(IncidenciaEstacion $row, array $nombres = []): array
    {
        $fecha = $row->fecha instanceof \DateTimeInterface
            ? $row->fecha->format('Y-m-d')
            : (string)$row->fecha;
        $hora = (string)$row->hora;
        $archivo = (string)$row->archivo;

        $fechaFmt = formatearFecha($fecha);
        $horaFmt = self::formatearHora($hora);

        return [
            'id' => (int)$row->id_incidencias_estaciones,
            'id_estacion' => (int)$row->id_estacion,
            'estacion' => $nombres[(int)$row->id_estacion]
                ?? (self::nombresEstaciones([(int)$row->id_estacion])[(int)$row->id_estacion] ?? ''),
            'fecha' => $fecha,
            'hora' => $hora,
            'fecha_fmt' => $fechaFmt,
            'hora_fmt' => $horaFmt,
            'fecha_hora_fmt' => trim($fechaFmt . ($horaFmt ? ', ' . $horaFmt : '')),
            'incidente' => (string)$row->incidente,
            'responsable' => (string)$row->responsable,
            'asunto' => (string)$row->asunto,
            'comentarios' => (string)$row->comentarios,
            'archivo' => $archivo,
            'existe_archivo' => $archivo !== '' && self::existeArchivo($archivo),
        ];
    }

    /** "10:33:00" -> "10:33 a. m." */
    private static function formatearHora(string $hora): string
    {
        if ($hora === '') {
            return '';
        }

        $ts = strtotime($hora);

        return $ts !== false ? date('g:i a', $ts) : '';
    }

    /**
     * Incidencia dentro del alcance del usuario (o null).
     */
    public static function findAutorizado(int $id): ?IncidenciaEstacion
    {
        if ($id <= 0) {
            return null;
        }

        $alcance = self::getAlcance();

        if (empty($alcance)) {
            return null;
        }

        return IncidenciaEstacion::query()
            ->whereKey($id)
            ->whereIn('id_estacion', $alcance)
            ->first();
    }

    /**
     * Detalle de una incidencia.
     *
     * @return array{success: bool, message?: string, data?: array<string, mixed>}
     */
    public static function show(int $id): array
    {
        if (!self::getPermisos()['puede_consultar']) {
            return ['success' => false, 'message' => 'No tienes permiso de lectura para este módulo.'];
        }

        $registro = self::findAutorizado($id);

        if (!$registro) {
            return ['success' => false, 'message' => 'Incidencia no encontrada.'];
        }

        $fila = self::filaListado($registro);
        $fila['archivo_url'] = $fila['existe_archivo']
            ? self::urlDescarga($fila['archivo'])
            : '';

        return ['success' => true, 'data' => $fila];
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
    /* Escritura                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Alta de una incidencia.
     *
     * @return array{success: bool, message: string, id?: int}
     */
    public static function store(array $datos, array $files): array
    {
        if (!self::getPermisos()['puede_agregar']) {
            return ['success' => false, 'message' => 'No tienes permiso para agregar incidencias.'];
        }

        $estacion = self::resolverEstacionAlta($datos);

        if ($estacion === null) {
            return ['success' => false, 'message' => 'Selecciona una estación válida.'];
        }

        $validacion = self::validar($datos, $files, true);

        if (!$validacion['ok']) {
            return ['success' => false, 'message' => $validacion['message']];
        }

        $archivo = self::guardarArchivo($files);

        if ($archivo === null) {
            return ['success' => false, 'message' => 'No fue posible guardar el archivo adjunto.'];
        }

        try {
            $registro = IncidenciaEstacion::create([
                'id_estacion' => $estacion,
                'fecha' => $validacion['fecha'],
                'hora' => $validacion['hora'],
                'incidente' => $validacion['incidente'],
                'responsable' => $validacion['responsable'],
                'asunto' => $validacion['asunto'],
                'comentarios' => $validacion['comentarios'],
                'archivo' => $archivo,
            ]);
        } catch (\Throwable $e) {
            self::borrarArchivo($archivo);
            return ['success' => false, 'message' => 'Error al registrar la incidencia: ' . $e->getMessage()];
        }

        self::notificar('agregó', $registro);

        return [
            'success' => true,
            'message' => 'Incidencia registrada correctamente.',
            'id' => (int)$registro->id_incidencias_estaciones,
        ];
    }

    /**
     * Actualización de una incidencia.
     *
     * @return array{success: bool, message: string}
     */
    public static function update(array $datos, array $files): array
    {
        if (!self::getPermisos()['puede_editar']) {
            return ['success' => false, 'message' => 'No tienes permiso para editar incidencias.'];
        }

        $id = (int)($datos['id'] ?? $datos['id_inc'] ?? 0);
        $registro = self::findAutorizado($id);

        if (!$registro) {
            return ['success' => false, 'message' => 'Incidencia no encontrada.'];
        }

        $validacion = self::validar($datos, $files, false, $registro);

        if (!$validacion['ok']) {
            return ['success' => false, 'message' => $validacion['message']];
        }

        $archivoNuevo = self::guardarArchivo($files);

        if ($archivoNuevo === null) {
            return ['success' => false, 'message' => 'No fue posible guardar el archivo adjunto.'];
        }

        $archivoAnterior = (string)$registro->archivo;

        try {
            $registro->fill([
                'fecha' => $validacion['fecha'],
                'hora' => $validacion['hora'],
                'incidente' => $validacion['incidente'],
                'responsable' => $validacion['responsable'],
                'asunto' => $validacion['asunto'],
                'comentarios' => $validacion['comentarios'],
                'archivo' => $archivoNuevo !== '' ? $archivoNuevo : $archivoAnterior,
            ])->save();
        } catch (\Throwable $e) {
            if ($archivoNuevo !== '') {
                self::borrarArchivo($archivoNuevo);
            }
            return ['success' => false, 'message' => 'Error al actualizar la incidencia: ' . $e->getMessage()];
        }

        // Sólo al reemplazar se retira el archivo anterior del servidor.
        if ($archivoNuevo !== '' && $archivoNuevo !== $archivoAnterior) {
            self::borrarArchivo($archivoAnterior);
        }

        self::notificar('editó', $registro);

        return ['success' => true, 'message' => 'Incidencia actualizada correctamente.'];
    }

    /**
     * Baja de la incidencia y de su archivo asociado.
     *
     * @return array{success: bool, message: string}
     */
    public static function destroy(int $id): array
    {
        if (!self::getPermisos()['puede_eliminar']) {
            return ['success' => false, 'message' => 'No tienes permiso para eliminar incidencias.'];
        }

        $registro = self::findAutorizado($id);

        if (!$registro) {
            return ['success' => false, 'message' => 'Incidencia no encontrada.'];
        }

        $archivo = (string)$registro->archivo;

        if (!$registro->delete()) {
            return ['success' => false, 'message' => 'No fue posible eliminar la incidencia.'];
        }

        self::borrarArchivo($archivo);

        self::notificar('eliminó', $registro);

        return ['success' => true, 'message' => 'Incidencia eliminada correctamente.'];
    }

    /* ------------------------------------------------------------------ */
    /* Validación y archivos                                               */
    /* ------------------------------------------------------------------ */

    /**
     * Estación del alta: la del contexto del selector o la enviada.
     * Con "todas" el alta exige elegir una estación concreta.
     */
    private static function resolverEstacionAlta(array $datos): ?int
    {
        $contexto = self::getContexto();

        if ($contexto !== null) {
            return self::estacionValida($contexto) ? $contexto : null;
        }

        $solicitada = (int)($datos['id_estacion'] ?? 0);

        return self::estacionValida($solicitada) ? $solicitada : null;
    }

    /**
     * @return array{ok: bool, message?: string, fecha?: string, hora?: string, incidente?: string, responsable?: string, asunto?: string, comentarios?: string}
     */
    private static function validar(array $datos, array $files, bool $esAlta, ?IncidenciaEstacion $registro = null): array
    {
        $valores = [];

        foreach (self::CAMPOS as $campo) {
            $valor = trim((string)($datos[$campo] ?? ''));

            // En edición un campo vacío conserva el valor actual (módulo anterior).
            if ($valor === '' && !$esAlta && $registro !== null) {
                $valor = trim((string)($registro->{$campo}));
            }

            $valores[$campo] = $valor;
        }

        if ($valores['fecha'] === '' || $valores['hora'] === '') {
            return ['ok' => false, 'message' => 'La fecha y la hora son obligatorias.'];
        }

        $objetoFecha = \DateTime::createFromFormat('Y-m-d', $valores['fecha']);
        $objetoHora = \DateTime::createFromFormat('H:i', $valores['hora']);

        if (!$objetoFecha || $objetoFecha->format('Y-m-d') !== $valores['fecha']) {
            return ['ok' => false, 'message' => 'La fecha no es válida.'];
        }

        if (!$objetoHora || $objetoHora->format('H:i') !== $valores['hora']) {
            return ['ok' => false, 'message' => 'La hora no es válida.'];
        }

        foreach (['incidente', 'responsable', 'asunto', 'comentarios'] as $campo) {
            if ($valores[$campo] === '') {
                return ['ok' => false, 'message' => 'El campo ' . ucfirst($campo) . ' es obligatorio.'];
            }
        }

        if ($esAlta) {
            if (empty($files['archivo']['name'])) {
                return ['ok' => false, 'message' => 'El archivo adjunto es obligatorio.'];
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
     * Guarda el archivo con prefijo único, como hacía el módulo anterior.
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

        // Se garantiza la extensión validada aunque el nombre original cambie.
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
     * Notifica sólo después de que la operación se completó con éxito.
     * El enrutado por estación es el del sistema (TelegramService::notificar).
     */
    private static function notificar(string $accion, IncidenciaEstacion $registro): void
    {
        try {
            $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);
            $nombreUsuario = (string)(Session::get('usuario')['nombre'] ?? Auth::user()->nombre ?? 'Usuario');
            $idEstacion = (int)$registro->id_estacion;
            $nombreEstacion = self::nombresEstaciones([$idEstacion])[$idEstacion] ?? ('Est #' . $idEstacion);

            $mensaje = sprintf(
                "<b>Incidencias</b>\n\n%s %s la incidencia #%d de <b>%s</b>.\nAsunto: <b>%s</b>\nIncidente: %s",
                htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8'),
                $accion,
                (int)$registro->id_incidencias_estaciones,
                htmlspecialchars($nombreEstacion, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string)$registro->asunto, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string)$registro->incidente, ENT_QUOTES, 'UTF-8')
            );

            TelegramService::notificar($idEstacion, $idUsuario, $mensaje);
        } catch (\Throwable $e) {
            // Una notificación fallida no debe romper la operación ya guardada.
            error_log('[Incidencias] Error notificando por Telegram: ' . $e->getMessage());
        }
    }
}
