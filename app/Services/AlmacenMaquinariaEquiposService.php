<?php

namespace App\Services;

use App\Core\Session;
use App\Models\Operativo\MaquinariaEquipo;
use App\Models\Operativo\MaquinariaEquiposComentario;
use App\Models\Usuario;

/**
 * Maquinaria y Equipos (Almacén)
 *
 * Migración del módulo legacy (departamento-operativo) `.../maquinaria-equipos/*.php`
 * sobre las tablas op_maquinaria_equipos y op_maquinaria_equipos_comentario.
 *
 * Decisiones de paridad con el legacy:
 *  - id_estacion se guarda en el espacio de op_rh_localidades (el legacy la usaba
 *    para estaciones Y departamentos como Autolavado). El filtro/contexto del módulo
 *    viene de ModuleStationService (stations_and_departments + tipo_departamento=localidades).
 *  - "Eliminar" conserva el comportamiento del legacy: BAJA SUAVE (estatus=1),
 *    NO borrado físico. Así no se orfana la bitácora (op_maquinaria_mantenimiento.id_equipo)
 *    ni el histórico de horas/horas_acumuladas del equipo.
 *  - Editar reactiva la maquinaria (estatus='0'), igual que el UPDATE del legacy.
 *  - No se envía Telegram: el legacy no notificaba en el CRUD de maquinaria-equipos
 *    (el único Telegram del módulo es de la bitácora/token, fuera de este alcance).
 *  - Fechas y archivos: fecha vacía o 0000-00-00 → 'S/I'; costo con 2 decimales
 *    (mejora aprobada, el legacy mostraba el dato crudo).
 *  - Archivos validados por extensión y MIME para no permitir ejecutables en
 *    public/uploads/archivos/maquinaria-equipo/.
 *  - Comentarios: sin restricción de escritura (el legacy permitía comentar a cualquiera
 *    con acceso de lectura), mismo diseño de burbujas del resto de módulos.
 */
class AlmacenMaquinariaEquiposService
{
    public const MODULE_KEY    = 'maquinaria-equipos';
    public const PARENT_MODULE = 'almacen';
    public const DOWNLOAD_TIPO = 'maquinaria-equipo';

    public const UPLOAD_FOLDER = 'public/uploads/archivos/maquinaria-equipo/';

    public const MAQUINARIA_OPCIONES = [
        'Bombas',
        'Hidrolavadora',
        'Hidroneumatico',
        'Motor de correo neumatico',
        'Compresor',
        'Planta de Emergencia',
    ];

    public const STATUS_LABELS = [0 => 'Activo', 1 => 'Baja'];
    public const STATUS_BADGES = [0 => 'bg-success', 1 => 'bg-danger'];

    /** Extensiones permitidas para factura/manual (documentos administrativos). */
    public const ARCHIVO_EXTENSIONES = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'xls', 'xlsx', 'doc', 'docx'];

    /** MIME que nunca deben pasar, aunque la extensión diga otra cosa. */
    public const MIME_PELIGROSOS = [
        'application/x-php',
        'text/x-php',
        'application/x-executable',
        'application/x-dosexec',
        'application/x-msdownload',
        'text/html',
        'image/svg+xml',
        'application/x-sh',
        'application/javascript',
        'text/x-javascript',
        'text/javascript',
    ];

    /* ------------------------------------------------------------------ */
    /* Rutas y permisos                                                    */
    /* ------------------------------------------------------------------ */

    public static function getUploadDir(): string
    {
        $dir = dirname(__DIR__, 2) . '/' . self::UPLOAD_FOLDER;

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    public static function getPermisos(): array
    {
        $sesion   = Session::get('usuario') ?? [];
        $usuario  = \App\Core\Auth::user();
        $idPuesto = (int)($usuario->id_puesto ?? 0);

        return [
            'id_usuario'     => (int)($sesion['id'] ?? 0),
            'nombre_usuario' => (string)($sesion['nombre'] ?? ''),
            'id_puesto'      => $idPuesto,

            'puedeLeer'      => ModuloDptoOperativoService::validaPermiso(self::PARENT_MODULE, 'leer'),
            'puedeCrear'     => ModuloDptoOperativoService::validaPermiso(self::PARENT_MODULE, 'crear'),
            'puedeEditar'    => ModuloDptoOperativoService::validaPermiso(self::PARENT_MODULE, 'editar'),
            'puedeEliminar'  => ModuloDptoOperativoService::validaPermiso(self::PARENT_MODULE, 'eliminar'),
            'puedeDescargar' => ModuloDptoOperativoService::validaPermiso(self::PARENT_MODULE, 'descargar'),

            // El legacy elegía el breadcrumb por puesto: Encargado / Asistente Administrativo
            // veían "Almacén"; cualquier otro puesto veía "Mantenimiento".
            'origenMantenimiento' => !in_array($idPuesto, [6, 7], true),

            // Usuarios que pertenecen a una estación (Encargado / Asistente Administrativo).
            'esUsuarioEstacion'    => in_array($idPuesto, [6, 7], true),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Contexto (espacio de ids = op_rh_localidades)                       */
    /* ------------------------------------------------------------------ */

    /**
     * Localidades (estaciones + departamentos) que el usuario puede ver,
     * indexadas por id de op_rh_localidades. Una sola fuente de verdad:
     * autolavado de ModuleStationService ya incluye la regla id_gas=2 → Autolavado.
     *
     * @return array<int, string>
     */
    public static function getLocalidadesDisponibles(): array
    {
        $mapa = [];

        foreach (ModuleStationService::getAvailableStations(self::MODULE_KEY) as $s) {
            $mapa[(int)$s['id']] = (string)$s['nombre'];
        }

        foreach (ModuleStationService::getAvailableDepartments(self::MODULE_KEY) as $d) {
            $mapa[(int)$d['id']] = (string)$d['nombre'];
        }

        return $mapa;
    }

    /**
     * Id de la localidad activa en el filtro (estación o departamento), o null si es "Todas".
     */
    private static function getContextId(): ?int
    {
        $contexto = ModuleStationService::getContext(self::MODULE_KEY);

        if (isset($contexto['id_estacion']) && $contexto['id_estacion'] > 0) {
            return (int)$contexto['id_estacion'];
        }

        if (isset($contexto['id_depto']) && $contexto['id_depto'] > 0) {
            return (int)$contexto['id_depto'];
        }

        return null;
    }

    /**
     * Registro perteneciente a una localidad autorizada para el usuario.
     */
    private static function buscarAutorizado(int $id, array $disponibles): ?MaquinariaEquipo
    {
        if ($id <= 0) {
            return null;
        }

        $registro = MaquinariaEquipo::find($id);

        if (!$registro) {
            return null;
        }

        return isset($disponibles[(int)$registro->id_estacion]) ? $registro : null;
    }

    /* ------------------------------------------------------------------ */
    /* Lectura                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Listado con el mismo orden y formato que el legacy (ORDER BY id),
     * incluyendo las columnas dinámicas "Estación / Departamento" (contexto_label)
     * y "Estatus", más el conteo de comentarios.
     */
    public static function getData(): array
    {
        $disponibles = self::getLocalidadesDisponibles();

        if (empty($disponibles)) {
            return [];
        }

        $idContexto = self::getContextId();

        $query = MaquinariaEquipo::query();

        if ($idContexto !== null) {
            // Autoridad en el backend: no se puede consultar una localidad no autorizada.
            if (!isset($disponibles[$idContexto])) {
                return [];
            }
            $query->where('id_estacion', $idContexto);
        } else {
            $query->whereIn('id_estacion', array_keys($disponibles));
        }

        $rows = $query->orderBy('id')->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $comentarios = MaquinariaEquiposComentario::whereIn('id_maquinaria_equipo', $rows->pluck('id'))
            ->selectRaw('id_maquinaria_equipo, COUNT(*) as total')
            ->groupBy('id_maquinaria_equipo')
            ->pluck('total', 'id_maquinaria_equipo')
            ->map(fn($n) => (int)$n)
            ->toArray();

        $uploadDir = self::getUploadDir();
        $data      = [];

        foreach ($rows as $r) {
            $idEstacion = (int)$r->id_estacion;
            $estatus    = (int)$r->estatus;
            $costo      = (float)($r->costo_compra ?? 0);
            $factura    = (string)($r->factura ?? '');
            $manual     = (string)($r->manual ?? '');

            $data[] = [
                'id'                   => (int)$r->id,
                'id_estacion'          => $idEstacion,
                'contexto_label'       => $disponibles[$idEstacion] ?? ('Estación #' . $idEstacion),
                'maquinaria'           => trim((string)$r->maquinaria) !== '' ? (string)$r->maquinaria : 'S/I',
                'descripcion'          => trim((string)$r->descripcion) !== '' ? (string)$r->descripcion : 'S/I',
                'marca'                => trim((string)$r->marca) !== '' ? (string)$r->marca : 'S/I',
                'modelo'               => trim((string)$r->modelo) !== '' ? (string)$r->modelo : 'S/I',
                'no_serie'             => trim((string)$r->no_serie) !== '' ? (string)$r->no_serie : 'S/I',
                'fecha_compra'         => self::fechaTexto((string)$r->fecha_compra),
                'fecha_instalacion'    => self::fechaTexto((string)$r->fecha_instalacion),
                'proveedor'            => trim((string)$r->proveedor) !== '' ? (string)$r->proveedor : 'S/I',
                'costo_compra'         => $costo,
                'costo_label'          => $costo > 0 ? number_format($costo, 2, '.', ',') : 'S/I',
                'garantia'             => trim((string)$r->garantia) !== '' ? (string)$r->garantia : 'S/I',
                'factura'              => $factura,
                'factura_existe'       => $factura !== '' && is_file($uploadDir . $factura),
                'manual'               => $manual,
                'manual_existe'        => $manual !== '' && is_file($uploadDir . $manual),
                'comentarios'          => $comentarios[(int)$r->id] ?? 0,
                'estatus'              => $estatus,
                'estatus_label'        => self::STATUS_LABELS[$estatus] ?? 'Desconocido',
                'estatus_badge'        => self::STATUS_BADGES[$estatus] ?? 'bg-secondary',
                'acciones_disponibles' => $estatus === 0,
            ];
        }

        return $data;
    }

    /**
     * Detalle de la maquinaria (datos crudos para el formulario de edición Y etiquetas
     * formateadas para el modal de detalle), sólo si pertenece a una localidad autorizada.
     */
    public static function getDetalle(int $id): ?array
    {
        $disponibles = self::getLocalidadesDisponibles();
        $registro    = self::buscarAutorizado($id, $disponibles);

        if ($registro === null) {
            return null;
        }

        $idEstacion = (int)$registro->id_estacion;
        $estatus    = (int)$registro->estatus;
        $costo      = (float)($registro->costo_compra ?? 0);
        $factura    = (string)($registro->factura ?? '');
        $manual     = (string)($registro->manual ?? '');
        $uploadDir  = self::getUploadDir();

        $facturaExiste = $factura !== '' && is_file($uploadDir . $factura);
        $manualExiste  = $manual !== '' && is_file($uploadDir . $manual);

        $comentarios = (int)MaquinariaEquiposComentario::where('id_maquinaria_equipo', $id)->count();

        return [
            'id'                     => $id,
            'id_estacion'            => $idEstacion,
            'contexto_label'         => $disponibles[$idEstacion] ?? ('Estación #' . $idEstacion),

            // Crudos (para editar)
            'maquinaria'             => (string)$registro->maquinaria,
            'descripcion'            => (string)$registro->descripcion,
            'marca'                  => (string)$registro->marca,
            'modelo'                 => (string)$registro->modelo,
            'no_serie'               => (string)$registro->no_serie,
            'fecha_compra'           => (string)$registro->fecha_compra,
            'fecha_instalacion'      => (string)$registro->fecha_instalacion,
            'proveedor'              => (string)$registro->proveedor,
            'costo_compra'           => $costo,
            'garantia'               => (string)$registro->garantia,

            // Formateados (para el detalle)
            'maquinaria_label'       => trim((string)$registro->maquinaria) !== '' ? (string)$registro->maquinaria : 'S/I',
            'descripcion_label'      => trim((string)$registro->descripcion) !== '' ? (string)$registro->descripcion : 'S/I',
            'marca_label'            => trim((string)$registro->marca) !== '' ? (string)$registro->marca : 'S/I',
            'modelo_label'           => trim((string)$registro->modelo) !== '' ? (string)$registro->modelo : 'S/I',
            'no_serie_label'         => trim((string)$registro->no_serie) !== '' ? (string)$registro->no_serie : 'S/I',
            'fecha_compra_label'     => self::fechaTexto((string)$registro->fecha_compra),
            'fecha_instalacion_label'=> self::fechaTexto((string)$registro->fecha_instalacion),
            'proveedor_label'        => trim((string)$registro->proveedor) !== '' ? (string)$registro->proveedor : 'S/I',
            'costo_label'            => $costo > 0 ? '$' . number_format($costo, 2, '.', ',') : 'S/I',
            'garantia_label'         => trim((string)$registro->garantia) !== '' ? (string)$registro->garantia : 'S/I',

            'factura'                => $factura,
            'factura_existe'         => $facturaExiste,
            'factura_url'            => $facturaExiste ? self::descargaUrl($factura) : null,
            'manual'                 => $manual,
            'manual_existe'          => $manualExiste,
            'manual_url'             => $manualExiste ? self::descargaUrl($manual) : null,

            'estatus'                => $estatus,
            'estatus_label'          => self::STATUS_LABELS[$estatus] ?? 'Desconocido',
            'estatus_badge'          => self::STATUS_BADGES[$estatus] ?? 'bg-secondary',
            'comentarios'            => $comentarios,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Escritura                                                           */
    /* ------------------------------------------------------------------ */

    public static function store(array $data, array $files): array
    {
        $disponibles = self::getLocalidadesDisponibles();
        $idContexto  = self::getContextId();

        if ($idContexto === null) {
            return [
                'success' => false,
                'message' => 'Selecciona una estación o departamento en el filtro superior para registrar la maquinaria.',
                'code'    => 422,
            ];
        }

        if (!isset($disponibles[$idContexto])) {
            return ['success' => false, 'message' => 'La estación o departamento seleccionado no está disponible.', 'code' => 422];
        }

        $validacion = self::validarCampos($data);

        if ($validacion['message'] !== null) {
            return ['success' => false, 'message' => $validacion['message'], 'code' => 422];
        }

        $factura = self::procesarArchivoOpcional($files['Factura_doc_file'] ?? [], 'factura', 'La factura no es un archivo válido.');
        if ($factura['error']) {
            return ['success' => false, 'message' => $factura['message'], 'code' => 422];
        }

        $manual = self::procesarArchivoOpcional($files['Manual_doc_file'] ?? [], 'manual', 'El manual no es un archivo válido.');
        if ($manual['error']) {
            self::limpiarArchivo($factura['archivo']);
            return ['success' => false, 'message' => $manual['message'], 'code' => 422];
        }

        // Las columnas factura/manual son NOT NULL en el esquema: usamos '' cuando no se adjunta archivo.
        $nombreFactura = $factura['archivo'] ?? '';
        $nombreManual  = $manual['archivo'] ?? '';

        try {
            $registro = MaquinariaEquipo::create([
                'id_estacion'       => $idContexto,
                'maquinaria'        => $validacion['maquinaria'],
                'descripcion'       => $validacion['descripcion'],
                'marca'             => $validacion['marca'],
                'modelo'            => $validacion['modelo'],
                'no_serie'          => $validacion['no_serie'],
                'fecha_compra'      => $validacion['fecha_compra'],
                'fecha_instalacion' => $validacion['fecha_instalacion'],
                'proveedor'         => $validacion['proveedor'],
                'costo_compra'      => $validacion['costo_compra'],
                'garantia'          => $validacion['garantia'],
                'factura'           => $nombreFactura,
                'manual'            => $nombreManual,
                'estatus'           => 0,
            ]);
        } catch (\Throwable $e) {
            self::limpiarArchivo($nombreFactura);
            self::limpiarArchivo($nombreManual);
            error_log('[AlmacenMaquinariaEquipos] store: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Error al registrar la maquinaria.', 'code' => 500];
        }

        if (!$registro) {
            self::limpiarArchivo($nombreFactura);
            self::limpiarArchivo($nombreManual);

            return ['success' => false, 'message' => 'No se pudo registrar la maquinaria.', 'code' => 500];
        }

        return ['success' => true, 'message' => 'Maquinaria registrada exitosamente.', 'code' => 200];
    }

    public static function update(int $id, array $data, array $files): array
    {
        $disponibles = self::getLocalidadesDisponibles();
        $registro    = self::buscarAutorizado($id, $disponibles);

        if ($registro === null) {
            return ['success' => false, 'message' => 'Registro no encontrado.', 'code' => 404];
        }

        $validacion = self::validarCampos($data);

        if ($validacion['message'] !== null) {
            return ['success' => false, 'message' => $validacion['message'], 'code' => 422];
        }

        // El legacy conservaba la estación de la fila en el UPDATE y además reactivaba
        // la maquinaria (estatus='0') al editar. Se replica ese comportamiento.
        $facturaAnterior = (string)($registro->factura ?? '');
        $manualAnterior  = (string)($registro->manual ?? '');

        $factura = self::procesarArchivoOpcional($files['Factura_doc_file'] ?? [], 'factura', 'La factura no es un archivo válido.');
        if ($factura['error']) {
            return ['success' => false, 'message' => $factura['message'], 'code' => 422];
        }

        $manual = self::procesarArchivoOpcional($files['Manual_doc_file'] ?? [], 'manual', 'El manual no es un archivo válido.');
        if ($manual['error']) {
            self::limpiarArchivo($factura['archivo']);
            return ['success' => false, 'message' => $manual['message'], 'code' => 422];
        }

        $cambiaFactura = $factura['archivo'] !== null && $factura['archivo'] !== $facturaAnterior;
        $cambiaManual  = $manual['archivo'] !== null && $manual['archivo'] !== $manualAnterior;

        try {
            $registro->maquinaria        = $validacion['maquinaria'];
            $registro->descripcion       = $validacion['descripcion'];
            $registro->marca             = $validacion['marca'];
            $registro->modelo            = $validacion['modelo'];
            $registro->no_serie          = $validacion['no_serie'];
            $registro->fecha_compra      = $validacion['fecha_compra'];
            $registro->fecha_instalacion = $validacion['fecha_instalacion'];
            $registro->proveedor         = $validacion['proveedor'];
            $registro->costo_compra      = $validacion['costo_compra'];
            $registro->garantia          = $validacion['garantia'];

            if ($cambiaFactura) {
                $registro->factura = $factura['archivo'];
            }

            if ($cambiaManual) {
                $registro->manual = $manual['archivo'];
            }

            $registro->estatus = 0;
            $registro->save();
        } catch (\Throwable $e) {
            self::limpiarArchivo($factura['archivo']);
            self::limpiarArchivo($manual['archivo']);
            error_log('[AlmacenMaquinariaEquipos] update: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Error al actualizar la maquinaria.', 'code' => 500];
        }

        if ($cambiaFactura) {
            self::limpiarArchivo($facturaAnterior);
        }

        if ($cambiaManual) {
            self::limpiarArchivo($manualAnterior);
        }

        return ['success' => true, 'message' => 'Maquinaria actualizada exitosamente.', 'code' => 200];
    }

    /**
     * Baja SUAVE del legacy: UPDATE estatus = '1'. No se borra el registro ni sus
     * archivos para no orfanar la bitácora (op_maquinaria_mantenimiento.id_equipo).
     */
    public static function destroy(int $id): array
    {
        $disponibles = self::getLocalidadesDisponibles();
        $registro    = self::buscarAutorizado($id, $disponibles);

        if ($registro === null) {
            return ['success' => false, 'message' => 'Registro no encontrado.', 'code' => 404];
        }

        if ((int)$registro->estatus === 1) {
            return ['success' => false, 'message' => 'La maquinaria ya está dada de baja.', 'code' => 422];
        }

        try {
            $registro->estatus = 1;
            $registro->save();
        } catch (\Throwable $e) {
            error_log('[AlmacenMaquinariaEquipos] destroy: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Error al dar de baja la maquinaria.', 'code' => 500];
        }

        return ['success' => true, 'message' => 'Maquinaria dada de baja exitosamente.', 'code' => 200];
    }

    /* ------------------------------------------------------------------ */
    /* Comentarios                                                         */
    /* ------------------------------------------------------------------ */

    public static function getComentarios(int $id): array
    {
        $disponibles = self::getLocalidadesDisponibles();
        $registro    = self::buscarAutorizado($id, $disponibles);

        if ($registro === null) {
            return [];
        }

        $rows = MaquinariaEquiposComentario::where('id_maquinaria_equipo', $id)
            ->orderByDesc('id')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $idUsuarioSesion = (int)(Session::get('usuario')['id'] ?? 0);

        $nombres = [];
        $idsUsuarios = $rows->pluck('id_usuario')->unique()->filter();
        if ($idsUsuarios->isNotEmpty()) {
            foreach (Usuario::whereIn('id', $idsUsuarios)->get(['id', 'nombre']) as $u) {
                $nombres[(int)$u->id] = (string)$u->nombre;
            }
        }

        $data = [];

        foreach ($rows as $r) {
            $data[] = [
                'id'             => (int)$r->id,
                'id_usuario'     => (int)$r->id_usuario,
                'nombre_usuario' => $nombres[(int)$r->id_usuario] ?? ('Usuario #' . (int)$r->id_usuario),
                'es_propio'      => (int)$r->id_usuario === $idUsuarioSesion,
                'comentario'     => (string)$r->comentario,
                'fecha_label'    => self::fechaHoraTexto((string)$r->fecha_hora),
            ];
        }

        return $data;
    }

    public static function guardarComentario(int $id, array $data): array
    {
        $disponibles = self::getLocalidadesDisponibles();
        $registro    = self::buscarAutorizado($id, $disponibles);

        if ($registro === null) {
            return ['success' => false, 'message' => 'Registro no encontrado.', 'code' => 404];
        }

        $comentario = trim((string)($data['comentario'] ?? ''));

        if ($comentario === '') {
            return ['success' => false, 'message' => 'El comentario es obligatorio.', 'code' => 422];
        }

        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        if ($idUsuario <= 0) {
            return ['success' => false, 'message' => 'Sesión no válida.', 'code' => 401];
        }

        try {
            MaquinariaEquiposComentario::create([
                'id_maquinaria_equipo' => $id,
                'id_usuario'           => $idUsuario,
                'comentario'           => $comentario,
            ]);
        } catch (\Throwable $e) {
            error_log('[AlmacenMaquinariaEquipos] guardarComentario: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Error al guardar el comentario.', 'code' => 500];
        }

        return ['success' => true, 'message' => 'Comentario guardado exitosamente.', 'code' => 200];
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * @return array{message: ?string, maquinaria: string, descripcion: string, marca: string, modelo: string, no_serie: string, fecha_compra: string, fecha_instalacion: string, proveedor: string, costo_compra: float, garantia: string}
     */
    private static function validarCampos(array $data): array
    {
        $fail = function (string $message): array {
            return [
                'message'          => $message,
                'maquinaria'       => '',
                'descripcion'      => '',
                'marca'            => '',
                'modelo'           => '',
                'no_serie'         => '',
                'fecha_compra'     => '',
                'fecha_instalacion'=> '',
                'proveedor'        => '',
                'costo_compra'     => 0.0,
                'garantia'         => '',
            ];
        };

        $maquinaria = trim((string)($data['Maquinaria'] ?? $data['maquinaria'] ?? ''));

        if ($maquinaria === '' || !in_array($maquinaria, self::MAQUINARIA_OPCIONES, true)) {
            return $fail('El tipo de maquinaria es obligatorio.');
        }

        $descripcion = trim((string)($data['Descripcion'] ?? $data['descripcion'] ?? ''));

        if ($descripcion === '') {
            return $fail('La descripción es obligatoria.');
        }

        $fechaCompra = trim((string)($data['Fecha_compra'] ?? $data['fecha_compra'] ?? ''));

        if (!self::esFechaValida($fechaCompra)) {
            return $fail('La fecha de compra es obligatoria y debe ser válida.');
        }

        $fechaInstalacion = trim((string)($data['Fecha_instalacion'] ?? $data['fecha_instalacion'] ?? ''));

        if (!self::esFechaValida($fechaInstalacion)) {
            return $fail('La fecha de instalación es obligatoria y debe ser válida.');
        }

        $costoRaw = trim((string)($data['Costo_compra'] ?? $data['costo_compra'] ?? ''));

        if ($costoRaw === '' || !is_numeric($costoRaw) || (float)$costoRaw < 0) {
            return $fail('El costo de compra es obligatorio y debe ser un número mayor o igual a cero.');
        }

        return [
            'message'           => null,
            'maquinaria'        => $maquinaria,
            'descripcion'       => $descripcion,
            'marca'             => trim((string)($data['Marca'] ?? $data['marca'] ?? '')),
            'modelo'            => trim((string)($data['Modelo'] ?? $data['modelo'] ?? '')),
            'no_serie'          => trim((string)($data['No_serie'] ?? $data['no_serie'] ?? '')),
            'fecha_compra'      => $fechaCompra,
            'fecha_instalacion' => $fechaInstalacion,
            'proveedor'         => trim((string)($data['Proveedor'] ?? $data['proveedor'] ?? '')),
            'costo_compra'      => (float)$costoRaw,
            'garantia'          => trim((string)($data['Garantia'] ?? $data['garantia'] ?? '')),
        ];
    }

    /**
     * Procesa un archivo opcional (factura/manual): si llega un upload lo valida y lo
     * mueve al destino; si no llega, devuelve archivo=null sin error.
     *
     * @return array{error: bool, message: string, archivo: ?string}
     */
    private static function procesarArchivoOpcional(array $file, string $campo, string $mensajeInvalido): array
    {
        if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['error' => false, 'message' => '', 'archivo' => null];
        }

        $nombre = self::validarArchivo($file);

        if ($nombre === null) {
            return ['error' => true, 'message' => $mensajeInvalido, 'archivo' => null];
        }

        if (!self::guardarArchivoUploaded($file, $nombre, self::getUploadDir())) {
            return ['error' => true, 'message' => 'No se pudo guardar el archivo (' . $campo . ').', 'archivo' => null];
        }

        return ['error' => false, 'message' => '', 'archivo' => $nombre];
    }

    /**
     * Valida extensión permitida + MIME no peligroso y devuelve el nombre definitivo
     * (uniqid()-original). null si no es válido.
     */
    private static function validarArchivo(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        if ((int)($file['size'] ?? 0) <= 0) {
            return null;
        }

        $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));

        if (!in_array($extension, self::ARCHIVO_EXTENSIONES, true)) {
            return null;
        }

        if (!self::mimeSeguro((string)$file['tmp_name'])) {
            return null;
        }

        return uniqid() . '-' . basename((string)$file['name']);
    }

    private static function guardarArchivoUploaded(array $file, string $nombre, string $dir): bool
    {
        return move_uploaded_file((string)($file['tmp_name'] ?? ''), $dir . $nombre);
    }

    private static function limpiarArchivo(?string $nombre): void
    {
        if ($nombre !== null && $nombre !== '') {
            @unlink(self::getUploadDir() . $nombre);
        }
    }

    private static function mimeSeguro(string $tmpPath): bool
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return false;
        }

        $mime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        return $mime !== false && $mime !== '' && !in_array($mime, self::MIME_PELIGROSOS, true);
    }

    private static function esFechaValida(string $fecha): bool
    {
        if ($fecha === '' || $fecha === '0000-00-00') {
            return false;
        }

        $d = \DateTime::createFromFormat('Y-m-d', $fecha);

        return $d !== false && $d->format('Y-m-d') === $fecha;
    }

    private static function fechaTexto(string $fecha): string
    {
        if ($fecha === '' || $fecha === '0000-00-00') {
            return 'S/I';
        }

        $f = formatearFecha($fecha);

        return $f !== '' ? $f : 'S/I';
    }

    private static function fechaHoraTexto(string $fechaHora): string
    {
        $partes = explode(' ', $fechaHora);
        $fecha  = $partes[0] ?? '';
        $hora   = $partes[1] ?? '';

        $fechaTxt   = self::fechaTexto($fecha);
        $horaTxt    = $hora !== '' ? date('g:i a', strtotime($hora)) : '';

        return trim($fechaTxt . ($horaTxt !== '' ? ', ' . $horaTxt : ''));
    }

    private static function descargaUrl(string $nombre): string
    {
        return '/download?tipo=' . self::DOWNLOAD_TIPO
            . '&file=' . rawurlencode($nombre);
    }
}