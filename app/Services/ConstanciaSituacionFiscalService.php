<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\ConstanciaSituacionFiscal;
use App\Models\Operativo\RhLocalidad;
use App\Services\ModuloDptoOperativoService;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Constancia de Situación Fiscal.
 *
 * Lógica heredada del módulo anterior:
 *   - Un registro por entidad (gasolinera, Departamento, Comercializadora o
 *     Quitarga) con fecha de actualización y un PDF adjunto.
 *   - El alta exige fecha y archivo; el listado ordenaba por fecha desc.
 *   - El borrado quitaba la fila de la base de datos (el archivo quedaba
 *     huérfano en el módulo anterior; aquí sí se elimina).
 *
 * El catálogo de entidades NO usa tb_estaciones porque id_estacion vive en
 * el espacio de op_rh_localidades: en tb_estaciones el id 11 es "Sabino
 * aguirre", que es una entidad distinta a "Dirección de Operaciones".
 */
class ConstanciaSituacionFiscalService
{
    /**
     * Clave del módulo en tb_modulos_config y en module_context: es la que
     * usa ModuleStationService para el selector de estación compartido.
     */
    public const CLAVE = 'constancia-situacion-fiscal';

    /**
     * Ruta del módulo. Es un módulo del Home (menus), no un submódulo de
     * Departamento Operativo, así que su acceso se resuelve con la entrada
     * del menú y no con el catálogo de submódulos de DO.
     */
    public const RUTA = '/constancia-situacion-fiscal';

    /** Módulo de permisos donde se resuelven las acciones (Almacén). */
    public const MODULO = 'almacen';

    public const UPLOAD_DIR = 'public/uploads/archivos/constancia-situacion-es/';

    /** Tipo de descarga en el DownloadController. */
    public const TIPO_DESCARGA = 'constancia-fiscal';

    /**
     * numlista de op_rh_localidades que el módulo anterior mostraba como
     * tarjeta: las ocho gasolineras, Dirección de Operaciones (12),
     * Comercializadora (22) y Quitarga (23).
     */
    private const NUMEROS_ESTACION = [1, 2, 3, 4, 5, 6, 7, 8, 12, 22, 23];

    private const RAZON_SOCIAL_ESPECIAL = [
        12 => 'DEPARTAMENTO',
        22 => 'COMERCIALIZADORA DE ARTICULOS GASOLINEROS S.A. DE C.V.',
        23 => 'COMERCIAL GASOLINERA QUITARGA S.A. DE C.V.',
    ];

    /* ------------------------------------------------------------------ */
    /* Permisos                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Puestos autorizados a registrar y eliminar constancias.
     *
     * Este módulo no pertenece al árbol de Departamento Operativo (su acceso
     * viene del menú del Home), así que no usa modulos_puestos_do: tocar esas
     * filas afectaría a todos los módulos de Almacén. El resto de los puestos
     * que ven el módulo sólo puede consultarlo y descargar el PDF.
     *
     * @var array<int, int>
     */
    public const PUESTOS_ESCRITURA = [13];

    /** El puesto del usuario actual está autorizado a escribir. */
    private static function puedeEscribir(): bool
    {
        $idPuesto = (int)(Auth::user()->id_puesto ?? 0);

        return $idPuesto > 0 && in_array($idPuesto, self::PUESTOS_ESCRITURA, true);
    }

    /**
     * Permisos efectivos del usuario actual.
     *
     * Consultar y descargar exige la entrada del menú del Home (roles_menus
     * por puesto o usuarios_menus por usuario, sin denegación) más permiso de
     * lectura sobre Almacén. Agregar y eliminar se restringen a
     * PUESTOS_ESCRITURA.
     */
    public static function getPermisos(): array
    {
        $lee = self::tieneAccesoMenu()
            && ModuloDptoOperativoService::validaPermiso(self::MODULO, 'leer');

        return [
            'puede_consultar' => $lee,
            'puede_agregar' => $lee && self::puedeEscribir(),
            'puede_editar' => false,
            'puede_eliminar' => $lee && self::puedeEscribir(),
            'puede_descargar' => $lee,
        ];
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
     * siempreabuerta por una denegación explícita.
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
    /* Catálogo de entidades y contexto                                    */
    /* ------------------------------------------------------------------ */

    /**
     * Entidades del módulo: id (espado localidades), nombre, razón social,
     * y el numlista para decidir el icono.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getEntidades(): array
    {
        return RhLocalidad::query()
            ->whereIn('numlista', self::NUMEROS_ESTACION)
            ->orderBy('numlista')
            ->get()
            ->map(function (RhLocalidad $localidad) {
                $numlista = (int)$localidad->numlista;
                return [
                    'id' => (int)$localidad->id,
                    'numlista' => $numlista,
                    'nombre' => (string)$localidad->localidad,
                    'razon_social' => self::razonSocial($numlista),
                    'icono' => self::icono($numlista),
                ];
            })
            ->all();
    }

    /** Catálogo con el id como llave, para validaciones rápidas. */
    public static function getEntidadesIndexadas(): array
    {
        $indexadas = [];

        foreach (self::getEntidades() as $entidad) {
            $indexadas[$entidad['id']] = $entidad;
        }

        return $indexadas;
    }

    private static function razonSocial(int $numlista): string
    {
        if (isset(self::RAZON_SOCIAL_ESPECIAL[$numlista])) {
            return self::RAZON_SOCIAL_ESPECIAL[$numlista];
        }

        $estacion = \App\Models\Estacion::where('numlista', $numlista)->first();

        return $estacion
            ? mb_strtoupper((string)$estacion->razonsocial)
            : '';
    }

    private static function icono(int $numlista): string
    {
        if (in_array($numlista, [22, 23], true)) {
            return 'ti ti-truck-delivery';
        }

        if ($numlista === 12) {
            return 'ti ti-building';
        }

        return 'ti ti-fuel-pump';
    }

    /**
     * Entidades que el usuario puede consultar.
     *
     * Se cruza el catálogo del módulo con las estaciones y localidades
     * que el usuario tiene permitidas en tb_multiestacion_* (misma fuente
     * que usa el resto del sistema para el contexto multiestación).
     *
     * @return array<int, int> ids autorizados
     */
    public static function getEntidadesPermitidas(): array
    {
        // El selector compartido ya resuelve el alcance del usuario contra la
        // configuración del módulo (tb_modulos_config) y devuelve los ids ya
        // convertidos al espacio de op_rh_localidades, que es donde vive
        // id_estacion en tb_constancia_fiscal_es.
        $ids = [];

        foreach (ModuleStationService::getAvailableStations(self::CLAVE) as $estacion) {
            $ids[] = (int)$estacion['id'];
        }

        foreach (ModuleStationService::getAvailableDepartments(self::CLAVE) as $departamento) {
            $ids[] = (int)$departamento['id'];
        }

        // Nunca devolver una entidad fuera del catálogo del módulo.
        $ids = array_values(array_intersect($ids, array_column(self::getEntidades(), 'id')));

        return array_values(array_unique($ids));
    }

    /**
     * Contexto activo del módulo.
     *
     * Lo administra ModuleStationService (misma clave de sesión que el resto
     * de módulos del Home). null = "todas las entidades permitidas".
     *
     * El selector entrega estaciones (id_estacion) y, para las tres entidades
     * que no son gasolineras, departamentos (id_depto). Ambos viven en el
     * espacio de op_rh_localidades, que es donde está id_estacion en
     * tb_constancia_fiscal_es, así que se resuelven igual.
     */
    public static function getContexto(): ?int
    {
        $ctx = ModuleStationService::getContext(self::CLAVE);

        $id = $ctx['id_estacion'] ?? null;

        if ($id === null) {
            $id = $ctx['id_depto'] ?? null;
        }

        return $id !== null ? (int)$id : null;
    }

    /**
     * Ids que puede consultar el usuario en este momento.
     *
     * @return array<int, int>
     */
    public static function getAlcance(): array
    {
        $permitidas = self::getEntidadesPermitidas();

        // Sin selección: todas las permitidas.
        $contexto = self::getContexto();

        if ($contexto === null) {
            return $permitidas;
        }

        // Backend: la selección nunca amplía el alcance.
        return in_array($contexto, $permitidas, true) ? [$contexto] : [];
    }

    /* ------------------------------------------------------------------ */
    /* Lectura                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * La columna Estacion/Departamento sólo se muestra cuando el selector
     * está en "todas". Con una entidad elegida el alcance es esa única
     * entidad, así que la columna sería redundante.
     */
    public static function mostrarColumnaEstacion(): bool
    {
        return self::getContexto() === null;
    }

    /**
     * Listado de constancias dentro del alcance del usuario.
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

        $entidades = self::getEntidadesIndexadas();

        $rows = ConstanciaSituacionFiscal::query()
            ->whereIn('id_estacion', $alcance)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        $resultado = [];

        foreach ($rows as $row) {
            $entidad = $entidades[(int)$row->id_estacion] ?? null;

            $resultado[] = [
                'id' => (int)$row->id,
                'id_entidad' => (int)$row->id_estacion,
                'estacion' => $entidad['nombre'] ?? ('Est #' . $row->id_estacion),
                'fecha' => formatearFecha($row->fecha?->format('Y-m-d') ?? ''),
                'fecha_raw' => $row->fecha?->format('Y-m-d') ?? '',
                'archivo' => (string)$row->archivo,
                'existe_archivo' => self::existeArchivo((string)$row->archivo),
            ];
        }

        return ['data' => $resultado, 'mostrar_fila_estacion' => $mostrarFila];
    }

    /**
     * Registro sólo si está dentro del alcance del usuario.
     */
    public static function findAutorizado(int $id): ?ConstanciaSituacionFiscal
    {
        if ($id <= 0) {
            return null;
        }

        $alcance = self::getAlcance();

        if (empty($alcance)) {
            return null;
        }

        return ConstanciaSituacionFiscal::query()
            ->whereKey($id)
            ->whereIn('id_estacion', $alcance)
            ->first();
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
     * Alta de una constancia.
     */
    public static function store(array $datos, array $files): array
    {
        $permisos = self::getPermisos();

        if (!$permisos['puede_agregar']) {
            return ['success' => false, 'message' => 'No tienes permiso para agregar constancias.'];
        }

        $validacion = self::validar($datos, $files);

        if (!$validacion['ok']) {
            return ['success' => false, 'message' => $validacion['message']];
        }

        $idEntidad = (int)$datos['id_entidad'];
        $fecha = $validacion['fecha'];

        $archivo = self::guardarArchivo($files['documento']);

        if ($archivo === null) {
            return ['success' => false, 'message' => 'No fue posible guardar el archivo de la constancia.'];
        }

        try {
            $registro = ConstanciaSituacionFiscal::create([
                'id_estacion' => $idEntidad,
                'fecha' => $fecha,
                'archivo' => $archivo,
            ]);
        } catch (\Throwable $e) {
            self::borrarArchivo($archivo);
            return ['success' => false, 'message' => 'Error al registrar la constancia: ' . $e->getMessage()];
        }

        self::notificar(
            'agregó',
            $idEntidad,
            $fecha,
            (int)$registro->id,
            (int)(Session::get('usuario')['id'] ?? 0)
        );

        return [
            'success' => true,
            'message' => 'Constancia de situación fiscal agregada correctamente.',
            'id' => (int)$registro->id,
        ];
    }


    /**
     * Baja de la constancia y de su archivo asociado.
     */
    public static function destroy(int $id): array
    {
        $permisos = self::getPermisos();

        if (!$permisos['puede_eliminar']) {
            return ['success' => false, 'message' => 'No tienes permiso para eliminar constancias.'];
        }

        $registro = self::findAutorizado($id);

        if (!$registro) {
            return ['success' => false, 'message' => 'Constancia no encontrada.'];
        }

        $archivo = (string)$registro->archivo;
        $idEntidad = (int)$registro->id_estacion;
        $fecha = $registro->fecha?->format('Y-m-d') ?? '';

        if (!$registro->delete()) {
            return ['success' => false, 'message' => 'No fue posible eliminar la constancia.'];
        }

        self::borrarArchivo($archivo);

        self::notificar('eliminó', $idEntidad, $fecha, $id, (int)(Session::get('usuario')['id'] ?? 0));

        return ['success' => true, 'message' => 'Constancia eliminada correctamente.'];
    }

    /* ------------------------------------------------------------------ */
    /* Validación y archivos                                               */
    /* ------------------------------------------------------------------ */

    /**
     * @return array{ok: bool, message?: string, fecha?: string}
     */
    private static function validar(array $datos, array $files): array
    {
        $idEntidad = (int)($datos['id_entidad'] ?? 0);

        if (!in_array($idEntidad, self::getEntidadesPermitidas(), true)) {
            return ['ok' => false, 'message' => 'La entidad seleccionada no está autorizada.'];
        }

        $fecha = trim((string)($datos['fecha'] ?? ''));

        if ($fecha === '') {
            return ['ok' => false, 'message' => 'La fecha de actualización es obligatoria.'];
        }

        $objetoFecha = \DateTime::createFromFormat('Y-m-d', $fecha);

        if (!$objetoFecha || \DateTime::getLastErrors() !== false && array_sum(\DateTime::getLastErrors()) > 0) {
            return ['ok' => false, 'message' => 'La fecha de actualización no es válida.'];
        }

        if (empty($files['documento']['name'])) {
            return ['ok' => false, 'message' => 'El archivo de la constancia es obligatorio.'];
        }

        $error = self::validarArchivo($files['documento']);

        if ($error !== null) {
            return ['ok' => false, 'message' => $error];
        }

        return ['ok' => true, 'fecha' => $objetoFecha->format('Y-m-d')];
    }

    private static function validarArchivo(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return 'El archivo no se subió correctamente.';
        }

        $extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));

        if ($extension !== 'pdf') {
            return 'Solo se permiten archivos PDF.';
        }

        $permitidos = ['application/pdf', 'application/x-pdf', 'application/octet-stream', 'application/download'];

        if (!empty($file['type']) && !in_array(strtolower((string)$file['type']), $permitidos, true)) {
            return 'El archivo subido no es un PDF válido.';
        }

        return null;
    }

    /** Guarda el PDF con prefijo único, como hacía el módulo anterior. */
    private static function guardarArchivo(array $file): ?string
    {
        $directorio = self::getUploadDir();

        if (!is_dir($directorio) && !mkdir($directorio, 0755, true) && !is_dir($directorio)) {
            return null;
        }

        $original = basename((string)$file['name']);
        $nombre = uniqid() . '-' . $original;

        if (!move_uploaded_file($file['tmp_name'], $directorio . '/' . $nombre)) {
            return null;
        }

        return $nombre;
    }

    /** El módulo anterior dejaba el PDF huérfano; aquí se elimina con la fila. */
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
     * Puesto que atiende cada entidad que no es gasolinera.
     *
     * Dirección de Operaciones (11), Comercializadora (22) y Quitarga (23) no
     * tienen usuarios con id_gas igual a su id, así que getUserIdsByStation()
     * no devuelve a nadie. Se notifica al puesto que corresponde.
     */
    private const PUESTO_POR_ENTIDAD = [
        11 => 13, // Dirección de operaciones
        22 => 4,  // Comercializadora
        23 => 18, // Quitarga
    ];

    /**
     * Notifica solo después de que la operación se completó con éxito.
     */
    private static function notificar(string $accion, int $idEntidad, string $fecha, int $idConstancia, int $idUsuario): void
    {
        try {
            $entidades = self::getEntidadesIndexadas();
            $entidad = $entidades[$idEntidad] ?? null;
            $nombreEntidad = $entidad['nombre'] ?? ('Est #' . $idEntidad);

            $usuario = Auth::user();
            $nombreUsuario = (string)(Session::get('usuario')['nombre'] ?? $usuario->nombre ?? 'Usuario');

            $mensaje = sprintf(
                "<b>Constancia de Situación Fiscal</b>\n\n%s %s la constancia #%d de <b>%s</b>.\nFecha de actualización: <b>%s</b>.",
                $nombreUsuario,
                $accion,
                $idConstancia,
                $nombreEntidad,
                formatearFecha($fecha)
            );

            $telegram = new TelegramService();

            if (isset(self::PUESTO_POR_ENTIDAD[$idEntidad])) {
                $destinatarios = $telegram->getUserIdsByPuesto(self::PUESTO_POR_ENTIDAD[$idEntidad], $idUsuario);

                foreach (array_unique($destinatarios) as $uid) {
                    $telegram->sendTokenAsync($uid, $mensaje);
                }

                return;
            }

            // Gasolineras: reutiliza el enrutado del sistema, que ya
            // reparte a Contabilidad o Comercializadora según la estación.
            TelegramService::notificar($idEntidad, $idUsuario, $mensaje);
        } catch (\Throwable $e) {
            // Una notificación fallida no debe romper la operación ya guardada.
            error_log('[CSF] Error notificando por Telegram: ' . $e->getMessage());
        }
    }
}
