<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Estacion;
use App\Models\Operativo\MiselaneaDocumento;
use App\Models\Operativo\MiselaneaDocumentoArchivo;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Miscelánea 30, 31 del Departamento Operativo.
 *
 * Lógica heredada de departamento-operativo (public/miselanea-30-31): el
 * proceso de inspección del Anexo 30 y 31 de la RMF se organiza en tres
 * etapas (documental, en sitio y certificación). Cada etapa se compone de
 * un catálogo de documentos (op_miselanea_documentos) y, por estación y
 * ejercicio, de los archivos cargados (op_miselanea_documentos_archivo).
 *
 * - El acceso al módulo se resuelve con el permiso del módulo para el Home
 *   (roles_modulos por puesto o usuarios_modulos por usuario) más el alcance
 *   de estaciones del usuario (tb_modulos_config a través de
 *   ModuleStationService). No depende del menú del sidebar: el módulo puede
 *   usarse aunque no esté listado en el sidebar.
 * - Las operaciones de gestión (agregar y eliminar archivos) se reservan
 *   al puesto que administra el módulo en el sistema heredado (id_puesto
 *   13, Dirección de Operaciones); el resto de puestos sólo consulta. Se
 *   valida tanto en la interfaz como en el backend.
 * - El contexto de estación lo administra ModuleStationService (selector
 *   compartido). No hay alcance "todas": se exige una estación válida.
 * - El ejercicio inicia en 2022 (primera aplicación del Anexo 30 y 31).
 */
class MiselaneaService
{
    /** Clave del módulo en tb_modulos_config y en module_context. */
    public const MODULE_KEY = 'miselanea-30-31';

    /** Ruta del módulo en el menú del Home. */
    public const RUTA = '/miselanea-30-31';

    /** Puesto autorizado a gestionar los archivos del módulo. */
    public const PUESTO_ADMIN = 13;

    /** Primer ejercicio disponible (Anexo 30 y 31 de la RMF 2022). */
    public const ANIO_INICIO = 2022;

    /** Etapa documental (categoría 1 de op_miselanea_documentos). */
    public const ETAPA_DOCUMENTAL = 1;

    /** Etapa de certificación (categoría 2 de op_miselanea_documentos). */
    public const ETAPA_CERTIFICACION = 2;

    /** Clave de la etapa documental en la URL. */
    public const SLUG_DOCUMENTAL = 'documental';

    /** Clave de la etapa de certificación en la URL. */
    public const SLUG_CERTIFICACION = 'certificacion';

    /** Carpeta de archivos (relativa a la raíz del proyecto). */
    public const UPLOAD_DIR = 'public/uploads/archivos/miselanea/';

    /** Tipo de descarga en el DownloadController. */
    public const TIPO_DESCARGA = 'miselanea';

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
     * @return array{puede_consultar: bool, puede_agregar: bool, puede_eliminar: bool, es_admin: bool}
     */
    public static function getPermisos(): array
    {
        $leer = self::tieneAccesoModulo();
        $gestiona = $leer && self::esPuestoAdmin();

        return [
            'puede_consultar' => $leer,
            // Alta y baja de archivos del proceso de inspección.
            'puede_agregar' => $gestiona,
            'puede_eliminar' => $gestiona,
            'es_admin' => $gestiona,
        ];
    }

    /**
     * El usuario puede consultar el módulo. El acceso ya no depende del
     * menú del sidebar (puede no estar listado ahí), sino del permiso del
     * módulo para el Home (roles_modulos / usuarios_modulos) y del alcance
     * de estaciones del usuario (tb_modulos_config).
     */
    private static function tieneAccesoModulo(): bool
    {
        if (!ModuleStationService::isAvailable(self::MODULE_KEY)) {
            return false;
        }

        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        if ($idUsuario <= 0) {
            return false;
        }

        return !empty(ModuloService::getPermiso($idUsuario, self::MODULE_KEY)['leer']);
    }

    /** El usuario pertenece al puesto que administra el módulo. */
    private static function esPuestoAdmin(): bool
    {
        $usuario = Auth::user();

        if ($usuario !== null && isset($usuario->id_puesto)) {
            return (int)$usuario->id_puesto === self::PUESTO_ADMIN;
        }

        $sesion = Session::get('usuario');

        return (int)($sesion['id_puesto'] ?? 0) === self::PUESTO_ADMIN;
    }

    /* ------------------------------------------------------------------ */
    /* Contexto de estación                                                */
    /* ------------------------------------------------------------------ */

    /**
     * Estaciones que puede consultar el usuario (id de tb_estaciones).
     *
     * @return array<int, int>
     */
    public static function getEstacionesPermitidas(): array
    {
        $ids = [];

        foreach (ModuleStationService::getAvailableStations(self::MODULE_KEY) as $estacion) {
            $ids[] = (int)$estacion['id'];
        }

        return array_values(array_unique($ids));
    }

    /**
     * Contexto activo del módulo (id de tb_estaciones) o null.
     */
    public static function getContexto(): ?int
    {
        $ctx = ModuleStationService::getContext(self::MODULE_KEY);

        $id = $ctx['id_estacion'] ?? null;

        return $id !== null ? (int)$id : null;
    }

    /**
     * Estación validada (contexto dentro del alcance) o null.
     * Backend: la selección nunca amplía el alcance.
     */
    public static function estacionContextual(): ?int
    {
        $id = self::getContexto();

        if ($id === null || !in_array($id, self::getEstacionesPermitidas(), true)) {
            return null;
        }

        return $id;
    }

    /** Nombre de una estación (o cadena vacía). */
    public static function nombreEstacion(int $idEstacion): string
    {
        if ($idEstacion <= 0) {
            return '';
        }

        $nombre = Estacion::where('id', $idEstacion)->value('nombre');

        return $nombre !== null ? (string)$nombre : '';
    }

    /* ------------------------------------------------------------------ */
    /* Etapas                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Definición de las tres etapas del proceso de inspección.
     *
     * Sólo las etapas 1 (documental) y 3 (certificación) tienen catálogo de
     * documentos propio y, por tanto, sub-página con su tabla. La etapa 2
     * (en sitio) es únicamente informativa.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function etapas(): array
    {
        return [
            [
                'numero' => 1,
                'slug' => self::SLUG_DOCUMENTAL,
                'titulo' => 'Etapa documental',
                'categoria' => self::ETAPA_DOCUMENTAL,
                'icono' => 'ti ti-folder',
                'descripcion' => 'Es la etapa inicial del proceso de inspección, se realiza previo a la visita en sitio de las instalaciones y se basa en evaluar los requisitos a partir de la revisión de la siguiente documentación.',
                'navegable' => true,
            ],
            [
                'numero' => 2,
                'slug' => 'en-sitio',
                'titulo' => 'Etapa en sitio',
                'categoria' => 0,
                'icono' => 'ti ti-map-pin',
                'descripcion' => 'Luego de analizar la documentación presentada durante la etapa documental se procede a realizar la visita en sitio con el fin de constatar las instalaciones y realizar algunas pruebas de funcionamiento del sistema de controles volumétricos. Para su desarrollo se requerirá la presencia del personal de la estación de servicio que preste las facilidades para el acceso a las instalaciones, así como el acceso al programa informático con los diferentes perfiles.',
                'navegable' => false,
            ],
            [
                'numero' => 3,
                'slug' => self::SLUG_CERTIFICACION,
                'titulo' => 'Certificación',
                'categoria' => self::ETAPA_CERTIFICACION,
                'icono' => 'ti ti-certificate',
                'descripcion' => 'Es la etapa final del proceso de inspección, durante esta se analizan los hallazgos de la etapa documental y en sitio, para emitir conclusiones respecto al cumplimiento con el Anexo 30 y 31 de la RMF 2022.',
                'navegable' => true,
            ],
        ];
    }

    /**
     * Etapa navegable (con catálogo) por su clave de URL, o null.
     *
     * @return array<string, mixed>|null
     */
    public static function etapaPorSlug(string $slug): ?array
    {
        foreach (self::etapas() as $etapa) {
            if ($etapa['navegable'] && $etapa['slug'] === $slug) {
                return $etapa;
            }
        }

        return null;
    }

    /**
     * Número total de documentos del catálogo de una categoría (etapa).
     */
    public static function totalDocumentos(int $categoria): int
    {
        return (int)MiselaneaDocumento::query()->where('categoria', $categoria)->count();
    }

    /** URL de una etapa navegable para un ejercicio. */
    public static function hrefEtapa(string $slug, int $year): string
    {
        return self::RUTA . '/' . $slug . '/' . $year;
    }

    /**
     * Tarjetas de las etapas del proceso listas para pintar (HTML).
     *
     * La vista del índice sólo imprime el resultado: el recorrido, las
     * condicionales de navegabilidad y el escapado viven aquí, en el
     * servicio (no en la vista).
     */
    public static function tarjetasHtml(int $year): string
    {
        $html = '<div class="row mt-4">';

        foreach (self::etapas() as $etapa) {
            $numero = (int)$etapa['numero'];
            $titulo = self::esc($numero . '. ' . $etapa['titulo']);
            $descripcion = self::esc($etapa['descripcion']);
            $icono = self::esc($etapa['icono']);
            $navegable = (bool)$etapa['navegable'];

            $href = $navegable ? self::esc(self::hrefEtapa($etapa['slug'], $year)) : '';

            $etiqueta = $navegable
                ? '<span class="small text-primary">Ver documentos</span>'
                    . '<div class="icon-transition">'
                    . '<i class="ti ti-arrow-right fs-5 text-primary"></i>'
                    . '</div>'
                : '<span class="small text-muted">Etapa informativa</span>'
                    . '<div>'
                    . '<i class="ti ti-info-circle fs-5 text-muted"></i>'
                    . '</div>';

            $contenidoInterno = <<<HTML
                <div class="card-body d-flex flex-column justify-content-between">
                    <div class="d-flex flex-column">
                        <div class="d-flex align-items-start ">
                            <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                                 style="width: 60px; height: 60px; font-size: 1.1rem; min-width: 60px;">
                                <i class="{$icono} text-white display-6"></i>
                            </div>
                            <div class="flex-grow-1 text-end">
                                <span class="badge bg-primary-subtle text-primary fw-bold mb-2 ms-auto d-inline-block">
                                    Etapa No. {$numero}
                                </span>
                                <h4 class="fw-bold text-dark mb-0 mt-1">
                                    {$titulo}
                                </h4>
                            </div>
                        </div>
                        <div class="w-100 mt-4">
                            <p class="text-muted mb-0" style="text-align: justify;">
                                {$descripcion}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between fw-semibold">
                    {$etiqueta}
                </div>
            HTML;

            if ($navegable) {
                $html .= '<div class="col-xl-4 col-lg-6 mb-4">'
                    . '<a href="' . $href . '" class="card text-decoration-none card-hover overflow-hidden position-relative">'
                    . $contenidoInterno
                    . '</a></div>';
            } else {
                $html .= '<div class="col-xl-4 col-lg-6 mb-4">'
                    . '<div class="card overflow-hidden position-relative">'
                    . $contenidoInterno
                    . '</div></div>';
            }
        }

        return $html . '</div>';
    }

    /** Escapa un valor para HTML (comillas dobles incluidas). */
    private static function esc(string $valor): string
    {
        return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Contenido del índice: el aviso de estación o las tarjetas de las
     * etapas. La vista se limita a imprimirlo.
     */
    public static function contenidoIndex(?int $idEstacion, int $year): string
    {
        if ($idEstacion === null) {
            return '<div id="miselanea-empty-message"'
                . ' class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">'
                . 'Debes de seleccionar una estación del menú superior para poder visualizar MISELANEA 30, 31.'
                . '</div>';
        }

        return self::tarjetasHtml($year);
    }

    /* ------------------------------------------------------------------ */
    /* Ejercicio                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Valida el ejercicio recibido: entero entre ANIO_INICIO y el año
     * actual. Devuelve el año vigente cuando no se recibe un valor válido.
     */
    public static function validarYear(int $year): int
    {
        $actual = (int)date('Y');

        if ($year < self::ANIO_INICIO || $year > $actual) {
            return $actual;
        }

        return $year;
    }

    /* ------------------------------------------------------------------ */
    /* Lectura                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Catálogo de documentos de una etapa con el conteo de archivos cargados
     * para la estación y ejercicio indicados.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function documentosEtapa(int $categoria, int $idEstacion, int $year): array
    {
        $documentos = MiselaneaDocumento::query()
            ->where('categoria', $categoria)
            ->orderBy('id_lista')
            ->get();

        $conteos = self::conteosPorDocumento($idEstacion, $year, $documentos->pluck('id')->all());

        $resultado = [];

        foreach ($documentos as $documento) {
            $id = (int)$documento->id;

            $resultado[] = [
                'id' => $id,
                'id_lista' => (string)$documento->id_lista,
                'documento' => (string)$documento->documento,
                'total' => $conteos[$id] ?? 0,
            ];
        }

        return $resultado;
    }

    /**
     * Archivos cargados de un documento para la estación y ejercicio.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function archivosDocumento(int $idDocumento, int $idEstacion, int $year): array
    {
        $filas = MiselaneaDocumentoArchivo::query()
            ->where('id_estacion', $idEstacion)
            ->where('year', $year)
            ->where('id_documento', $idDocumento)
            ->orderBy('id')
            ->get();

        $resultado = [];

        foreach ($filas as $fila) {
            $archivo = (string)$fila->archivo;

            $resultado[] = [
                'id' => (int)$fila->id,
                'detalle' => (string)$fila->detalle,
                'archivo' => $archivo,
                'existe_archivo' => $archivo !== '' && self::existeArchivo($archivo),
                'fecha_fmt' => self::formatearFechaHora($fila->fecha),
            ];
        }

        return $resultado;
    }

    /**
     * Documento del catálogo por id (o null).
     */
    public static function documento(int $id): ?MiselaneaDocumento
    {
        if ($id <= 0) {
            return null;
        }

        return MiselaneaDocumento::find($id);
    }

    /**
     * @param array<int, int> $idsDocumento
     * @return array<int, int>
     */
    private static function conteosPorDocumento(int $idEstacion, int $year, array $idsDocumento): array
    {
        if (empty($idsDocumento)) {
            return [];
        }

        $filas = MiselaneaDocumentoArchivo::query()
            ->selectRaw('id_documento, COUNT(*) as total')
            ->where('id_estacion', $idEstacion)
            ->where('year', $year)
            ->whereIn('id_documento', $idsDocumento)
            ->groupBy('id_documento')
            ->get();

        $conteos = [];

        foreach ($filas as $fila) {
            $conteos[(int)$fila->id_documento] = (int)$fila->total;
        }

        return $conteos;
    }

    /* ------------------------------------------------------------------ */
    /* Escritura                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Agrega un archivo a un documento para la estación y ejercicio.
     *
     * @param array<string, mixed> $datos
     * @param array<string, mixed> $files
     * @return array{success: bool, message: string}
     */
    public static function agregarArchivo(array $datos, array $files): array
    {
        if (!self::getPermisos()['puede_agregar']) {
            return ['success' => false, 'message' => 'No tienes permiso para agregar archivos.'];
        }

        $idDocumento = (int)($datos['id_documento'] ?? 0);
        $documento = self::documento($idDocumento);

        if (!$documento) {
            return ['success' => false, 'message' => 'El documento seleccionado no existe.'];
        }

        $idEstacion = self::estacionContextual();

        if ($idEstacion === null) {
            return ['success' => false, 'message' => 'Debes seleccionar una estación válida para agregar archivos.'];
        }

        $year = self::validarYear((int)($datos['year'] ?? 0));

        $detalle = trim((string)($datos['detalle'] ?? ''));

        if ($detalle === '') {
            return ['success' => false, 'message' => 'El detalle es obligatorio.'];
        }

        $archivo = $files['archivo'] ?? null;

        if (!is_array($archivo) || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['success' => false, 'message' => 'El archivo es obligatorio.'];
        }

        if ((int)($archivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Error al recibir el archivo. Intente de nuevo.'];
        }

        $validacion = self::validarArchivo($archivo);

        if ($validacion['message'] !== null) {
            return ['success' => false, 'message' => $validacion['message']];
        }

        $nombre = $validacion['nombre'];

        if (!is_dir(self::getUploadDir())) {
            @mkdir(self::getUploadDir(), 0775, true);
        }

        if (!move_uploaded_file((string)$archivo['tmp_name'], self::getUploadDir() . $nombre)) {
            return ['success' => false, 'message' => 'No se pudo guardar el archivo.'];
        }

        try {
            MiselaneaDocumentoArchivo::create([
                'id_estacion' => $idEstacion,
                'year' => $year,
                'id_documento' => $idDocumento,
                'fecha' => date('Y-m-d H:i:s'),
                'detalle' => $detalle,
                'archivo' => $nombre,
            ]);
        } catch (\Throwable $e) {
            @unlink(self::getUploadDir() . $nombre);

            return ['success' => false, 'message' => 'Error al registrar el archivo: ' . $e->getMessage()];
        }

        return ['success' => true, 'message' => 'Archivo agregado exitosamente.'];
    }

    /**
     * Elimina un archivo (registro y archivo físico) del proceso.
     *
     * @return array{success: bool, message: string}
     */
    public static function eliminarArchivo(int $id): array
    {
        if (!self::getPermisos()['puede_eliminar']) {
            return ['success' => false, 'message' => 'No tienes permiso para eliminar archivos.'];
        }

        $registro = MiselaneaDocumentoArchivo::find($id);

        if (!$registro) {
            return ['success' => false, 'message' => 'Archivo no encontrado.'];
        }

        $idEstacion = self::estacionContextual();

        // Sólo se elimina dentro de la estación seleccionada.
        if ($idEstacion === null || (int)$registro->id_estacion !== $idEstacion) {
            return ['success' => false, 'message' => 'No tienes acceso al archivo de esa estación.'];
        }

        $archivo = (string)$registro->archivo;

        try {
            $registro->delete();
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error al eliminar el archivo: ' . $e->getMessage()];
        }

        // El módulo anterior dejaba huérfanos; aquí se limpia el físico.
        if ($archivo !== '') {
            @unlink(self::getUploadDir() . $archivo);
        }

        return ['success' => true, 'message' => 'Archivo eliminado exitosamente.'];
    }

    /* ------------------------------------------------------------------ */
    /* Utilidades                                                          */
    /* ------------------------------------------------------------------ */

    private static function getUploadDir(): string
    {
        return dirname(__DIR__, 2) . '/' . self::UPLOAD_DIR;
    }

    private static function existeArchivo(string $archivo): bool
    {
        return $archivo !== '' && is_file(self::getUploadDir() . $archivo);
    }

    /**
     * Valida el archivo recibido y devuelve el nombre a guardar.
     * El módulo anterior guardaba uniqid()-nombreOriginal.
     *
     * @param array<string, mixed> $archivo
     * @return array{message: ?string, nombre: string}
     */
    private static function validarArchivo(array $archivo): array
    {
        $nombreOriginal = (string)($archivo['name'] ?? '');

        if ($nombreOriginal === '') {
            return ['message' => 'El archivo es obligatorio.', 'nombre' => ''];
        }

        $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

        if ($extension === '' || !in_array($extension, self::EXTENSIONES_PERMITIDAS, true)) {
            return ['message' => 'El tipo de archivo no está permitido.', 'nombre' => ''];
        }

        if ((int)($archivo['size'] ?? 0) <= 0) {
            return ['message' => 'El archivo está vacío.', 'nombre' => ''];
        }

        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', $nombreOriginal);
        $base = trim((string)$base, '_');

        if ($base === '') {
            $base = 'archivo.' . $extension;
        }

        return [
            'message' => null,
            'nombre' => uniqid() . '-' . $base,
        ];
    }

    /** "2026-10-19 15:04:05" -> "19 de Octubre del 2026, 3:04 pm". */
    private static function formatearFechaHora($fecha): string
    {
        if ($fecha === null) {
            return '';
        }

        $texto = $fecha instanceof \DateTimeInterface
            ? $fecha->format('Y-m-d H:i:s')
            : (string)$fecha;

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
}
