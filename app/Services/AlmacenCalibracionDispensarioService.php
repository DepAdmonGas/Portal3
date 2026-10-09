<?php

namespace App\Services;

use App\Core\Session;
use App\Models\Estacion;
use App\Models\Operativo\CalibracionDispensario;

/**
 * Calibración de Dispensarios (Almacén)
 *
 * Migración del módulo legacy public/admin/modelo/{agregar,eliminar}-calibracion-dispensario.php
 * sobre la tabla op_calibracion_dispensario.
 *
 * Decisiones de paridad con el legacy:
 *  - Sin día doble, sin comodines de negocio, sin comentarios, sin estados ni cálculos.
 *  - Se permiten duplicados (estación, año, periodo): el legacy no los validaba y existen
 *    8 combinaciones repetidas en la tabla. No se agrega UNIQUE.
 *  - El año solo se valida como entero; NO se impone un rango, para no invalidar los
 *    registros históricos corruptos (202 y 4022023) y permitir corregirlos desde Editar.
 *  - La estación nunca se pide en el formulario: se toma del selector de contexto del
 *    módulo (ModuleStationService::getContext). Al editar tampoco se modifica: define la
 *    propiedad del dato y la audiencia de la notificación de Telegram.
 *  - Telegram replica getChatIdUsuarios() del legacy (estación + Comodines) más
 *    Comercializadora para estaciones 6/7 y Mantenimiento siempre. Ver notificarTelegram().
 *  - NO se replica ClassEventos::registrarEvento(): Portal3 no tiene esa bitácora en
 *    ningún módulo y exigiría DDL en tiempo de ejecución.
 */
class AlmacenCalibracionDispensarioService
{
    public const MODULE_KEY    = 'calibracion-dispensarios';
    public const PARENT_MODULE = 'almacen';
    public const DOWNLOAD_TIPO = 'calibracion-dispensarios';

    /**
     * El legacy guardaba en la raíz de archivos/ con el nombre uniqid()-original.
     * Se replica la misma posición relativa para que una futura exportación coincida.
     */
    public const UPLOAD_FOLDER = 'public/uploads/archivos/';

    public const PERIODOS      = ['Primer periodo', 'Segundo periodo'];

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
        ];
    }

    /**
     * Estaciones que el usuario puede ver, indexadas por id.
     * Es la única fuente de verdad: no se hardcodean estaciones.
     *
     * @return array<int, string>
     */
    public static function getEstacionesDisponibles(): array
    {
        $mapa = [];

        foreach (ModuleStationService::getAvailableStations(self::MODULE_KEY) as $s) {
            $mapa[(int)$s['id']] = (string)$s['nombre'];
        }

        return $mapa;
    }

    private static function nombreEstacion(int $idEstacion): string
    {
        $disponibles = self::getEstacionesDisponibles();

        if (isset($disponibles[$idEstacion])) {
            return $disponibles[$idEstacion];
        }

        $estacion = Estacion::find($idEstacion);

        return $estacion ? (string)$estacion->nombre : 'Estación #' . $idEstacion;
    }

    /* ------------------------------------------------------------------ */
    /* Lectura                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * @param int|null $estacionFiltro null = todas las estaciones autorizadas
     */
    public static function getData(?int $estacionFiltro = null): array
    {
        $disponibles = self::getEstacionesDisponibles();

        if (empty($disponibles)) {
            return [];
        }

        $query = CalibracionDispensario::query();

        if ($estacionFiltro !== null && $estacionFiltro > 0) {
            // Autoridad en el backend: no se puede filtrar por una estación no autorizada.
            if (!isset($disponibles[$estacionFiltro])) {
                return [];
            }
            $query->where('id_estacion', $estacionFiltro);
        } else {
            $query->whereIn('id_estacion', array_keys($disponibles));
        }

        $rows = $query->orderByDesc('year')
            ->orderBy('periodo')
            ->orderByDesc('id')
            ->get();

        $uploadDir = self::getUploadDir();
        $data      = [];

        foreach ($rows as $r) {
            $idEstacion = (int)$r->id_estacion;
            $archivo    = (string)($r->archivo ?? '');

            $data[] = [
                'id'              => (int)$r->id,
                'id_estacion'     => $idEstacion,
                'estacion_nombre' => $disponibles[$idEstacion] ?? ('Estación #' . $idEstacion),
                'fecha'           => $r->fecha ? formatearFecha($r->fecha) : '',
                'year'            => (int)$r->year,
                'periodo'         => (string)$r->periodo,
                'archivo'         => $archivo,
                'archivo_existe'  => $archivo !== '' && is_file($uploadDir . $archivo),
            ];
        }

        return $data;
    }

    /**
     * Registro editable, sólo si pertenece a una estación autorizada para el usuario.
     */
    public static function getDetalle(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $r = CalibracionDispensario::find($id);

        if (!$r) {
            return null;
        }

        $disponibles = self::getEstacionesDisponibles();
        $idEstacion  = (int)$r->id_estacion;

        if (!isset($disponibles[$idEstacion])) {
            return null;
        }

        $archivo = (string)($r->archivo ?? '');

        return [
            'id'              => (int)$r->id,
            'id_estacion'     => $idEstacion,
            'estacion_nombre' => $disponibles[$idEstacion],
            'fecha'           => $r->fecha ? formatearFecha($r->fecha) : '',
            'year'            => (int)$r->year,
            'periodo'         => (string)$r->periodo,
            'archivo'         => $archivo,
            'archivo_existe'  => $archivo !== '' && is_file(self::getUploadDir() . $archivo),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Escritura                                                           */
    /* ------------------------------------------------------------------ */

    public static function store(array $data, array $files): array
    {
        $disponibles = self::getEstacionesDisponibles();

        // La estación NO se pide en el formulario: se toma del selector de contexto del
        // módulo (mismo patrón que FormatoDescargaMermaService y MedicionesService).
        // Así el cliente nunca puede inyectar una estación.
        $idEstacion = (int)(ModuleStationService::getContext(self::MODULE_KEY)['id_estacion'] ?? 0);

        if ($idEstacion <= 0) {
            return [
                'success' => false,
                'message' => 'Selecciona una estación en el filtro superior para registrar la calibración.',
                'code'    => 422,
            ];
        }

        if (!isset($disponibles[$idEstacion])) {
            return ['success' => false, 'message' => 'La estación seleccionada no está disponible.', 'code' => 422];
        }

        $validacion = self::validarCampos($data, $files, true);

        if ($validacion['message'] !== null) {
            return ['success' => false, 'message' => $validacion['message'], 'code' => 422];
        }

        $nombreArchivo = self::guardarArchivo($files['Archivo_file'] ?? []);

        if ($nombreArchivo === null) {
            return ['success' => false, 'message' => 'No se pudo guardar el archivo.', 'code' => 500];
        }

        try {
            $registro = CalibracionDispensario::create([
                'id_estacion' => $idEstacion,
                'year'        => $validacion['year'],
                'periodo'     => $validacion['periodo'],
                'archivo'     => $nombreArchivo,
            ]);
        } catch (\Throwable $e) {
            @unlink(self::getUploadDir() . $nombreArchivo);
            error_log('[AlmacenCalibracionDispensario] store: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Error al registrar la calibración.', 'code' => 500];
        }

        if (!$registro) {
            @unlink(self::getUploadDir() . $nombreArchivo);

            return ['success' => false, 'message' => 'No se pudo registrar la calibración.', 'code' => 500];
        }

        self::notificar(
            $idEstacion,
            '✅',
            'agregó un nuevo registro',
            $validacion['year'],
            $validacion['periodo']
        );

        return ['success' => true, 'message' => 'Calibración registrada exitosamente.', 'code' => 200];
    }

    public static function update(int $id, array $data, array $files): array
    {
        $disponibles = self::getEstacionesDisponibles();
        $registro    = self::buscarAutorizado($id, $disponibles);

        if ($registro === null) {
            return ['success' => false, 'message' => 'Registro no encontrado.', 'code' => 404];
        }

        $validacion = self::validarCampos($data, $files, false);

        if ($validacion['message'] !== null) {
            return ['success' => false, 'message' => $validacion['message'], 'code' => 422];
        }

        $archivoAnterior = (string)($registro->archivo ?? '');
        $cambiaArchivo   = !empty($files['Archivo_file']['tmp_name']);
        $nombreArchivo   = $cambiaArchivo ? self::guardarArchivo($files['Archivo_file']) : null;

        if ($cambiaArchivo && $nombreArchivo === null) {
            return ['success' => false, 'message' => 'No se pudo guardar el archivo.', 'code' => 500];
        }

        try {
            $registro->year    = $validacion['year'];
            $registro->periodo = $validacion['periodo'];

            if ($nombreArchivo !== null) {
                $registro->archivo = $nombreArchivo;
            }

            $registro->save();
        } catch (\Throwable $e) {
            if ($nombreArchivo !== null) {
                @unlink(self::getUploadDir() . $nombreArchivo);
            }
            error_log('[AlmacenCalibracionDispensario] update: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Error al actualizar el registro.', 'code' => 500];
        }

        if ($nombreArchivo !== null && $archivoAnterior !== '' && $archivoAnterior !== $nombreArchivo) {
            @unlink(self::getUploadDir() . $archivoAnterior);
        }

        self::notificar(
            (int)$registro->id_estacion,
            '✏️',
            'actualizó el registro',
            (int)$registro->year,
            (string)$registro->periodo
        );

        return ['success' => true, 'message' => 'Registro actualizado exitosamente.', 'code' => 200];
    }

    public static function destroy(int $id): array
    {
        $disponibles = self::getEstacionesDisponibles();
        $registro    = self::buscarAutorizado($id, $disponibles);

        if ($registro === null) {
            return ['success' => false, 'message' => 'Registro no encontrado.', 'code' => 404];
        }

        $idEstacion     = (int)$registro->id_estacion;
        $year           = (int)$registro->year;
        $periodo        = (string)$registro->periodo;
        $archivoAnterior = (string)($registro->archivo ?? '');

        try {
            $eliminado = $registro->delete();
        } catch (\Throwable $e) {
            error_log('[AlmacenCalibracionDispensario] destroy: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Error al eliminar el registro.', 'code' => 500];
        }

        if (!$eliminado) {
            return ['success' => false, 'message' => 'No se pudo eliminar el registro.', 'code' => 500];
        }

        // El legacy no borraba el archivo físico; se corrige para no dejar huérfanos.
        if ($archivoAnterior !== '') {
            @unlink(self::getUploadDir() . $archivoAnterior);
        }

        self::notificar($idEstacion, '🗑', 'eliminó el registro', $year, $periodo);

        return ['success' => true, 'message' => 'Registro eliminado exitosamente.', 'code' => 200];
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Busca el registro y confirma que su estación esté autorizada.
     * Devuelve null si no existe o si el usuario no tiene acceso.
     */
    private static function buscarAutorizado(int $id, array $disponibles): ?CalibracionDispensario
    {
        if ($id <= 0) {
            return null;
        }

        $registro = CalibracionDispensario::find($id);

        if (!$registro) {
            return null;
        }

        return isset($disponibles[(int)$registro->id_estacion]) ? $registro : null;
    }

    /**
     * @return array{message: ?string, year: int, periodo: string}
     */
    private static function validarCampos(array $data, array $files, bool $archivoObligatorio): array
    {
        $yearRaw = trim((string)($data['Year'] ?? $data['year'] ?? ''));

        if ($yearRaw === '') {
            return ['message' => 'El año es obligatorio.', 'year' => 0, 'periodo' => ''];
        }

        // Sin rango: el histórico contiene 202 y 4022023 y debe poder corregirse.
        if (!preg_match('/^\d{1,10}$/', $yearRaw)) {
            return ['message' => 'El año debe ser un número entero válido.', 'year' => 0, 'periodo' => ''];
        }

        $periodo = trim((string)($data['Periodo'] ?? $data['periodo'] ?? ''));

        if ($periodo === '') {
            return ['message' => 'El periodo es obligatorio.', 'year' => 0, 'periodo' => ''];
        }

        if (!in_array($periodo, self::PERIODOS, true)) {
            return ['message' => 'El periodo seleccionado no es válido.', 'year' => 0, 'periodo' => ''];
        }

        $file = $files['Archivo_file'] ?? [];

        if (!empty($file['tmp_name'])) {
            $error = self::validarPdf($file);

            if ($error !== null) {
                return ['message' => $error, 'year' => 0, 'periodo' => ''];
            }
        } elseif ($archivoObligatorio) {
            return ['message' => 'El archivo es obligatorio.', 'year' => 0, 'periodo' => ''];
        }

        return ['message' => null, 'year' => (int)$yearRaw, 'periodo' => $periodo];
    }

    /**
     * Valida el PDF recibido.
     *
     * No se impose un límite de peso: el único tope es el de PHP
     * (upload_max_filesize / post_max_size). Sólo se descartan los archivos
     * vacíos, que nunca son un PDF utilizable.
     */
    private static function validarPdf(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return 'Error al recibir el archivo. Intente de nuevo.';
        }

        $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));

        if ($extension !== 'pdf') {
            return 'El archivo debe ser un PDF válido.';
        }

        if ((int)($file['size'] ?? 0) <= 0) {
            return 'El archivo está vacío.';
        }

        $validador = new FileValidatorService();

        if (!$validador->isValidMimeType((string)$file['tmp_name'], ['application/pdf'])) {
            return 'El archivo debe ser un PDF válido.';
        }

        return null;
    }

    /**
     * Mismo patrón de nombre que el legacy: uniqid()-nombreOriginal.
     */
    private static function guardarArchivo(array $file): ?string
    {
        $nombreOriginal = basename((string)($file['name'] ?? 'documento.pdf'));
        $nombre         = uniqid() . '-' . $nombreOriginal;

        if (!move_uploaded_file((string)$file['tmp_name'], self::getUploadDir() . $nombre)) {
            return null;
        }

        return $nombre;
    }

    /**
     * Réplica del legacy. El legacy notificaba:
     *   1. getChatIdUsuarios($idEstacion, $actor)  → estación + Comodines (puestos 6/13/14)
     *   2. Comercializadora, sólo si la estación es 6 o 7
     *   3. Mantenimiento (puesto 8), siempre
     *
     * No se usa TelegramService::notificar() porque no incluye Mantenimiento y agrega
     * Contabilidad, que el legacy no notificaba. No se modifica el servicio global.
     */
    private static function notificar(int $idEstacion, string $icono, string $verbo, int $year, string $periodo): void
    {
        try {
            $telegram = new TelegramService();
            $actor    = (int)(Session::get('usuario')['id'] ?? 0);
            $autor    = (string)(Session::get('usuario')['nombre'] ?? '');

            $destinos = $telegram->getUserIdsByStationWithComodines($idEstacion, $actor);

            if (in_array($idEstacion, [6, 7], true)) {
                $destinos = array_merge($destinos, $telegram->getUserIdsComercializadora($actor));
            }

            $destinos = array_merge($destinos, $telegram->getUserIdsMantenimiento($actor));

            // El SQL del legacy era "... ) AND id <> ?": la exclusión del actor se aplicaba
            // al conjunto completo. getUserIdsByStationWithComodines() sólo lo excluye de la
            // rama de estación, no de la de Comodines, así que un usuario Comodín
            // (id_gas = 8, puesto 6/13/14) se auto-notificaría. Se filtra explícitamente
            // para conservar la semántica del legacy.
            $destinos = array_values(array_unique(array_diff($destinos, [$actor])));

            $mensaje = $icono . ' ' . $autor . ' ' . $verbo . ' en Calibración de dispensarios correspondiente al apartado de Almacén'
                . PHP_EOL . '📄 ' . $periodo . ', ' . $year . '.'
                . PHP_EOL . PHP_EOL . '⛽ Estación: ' . self::nombreEstacion($idEstacion) . '.';

            foreach ($destinos as $idUsuario) {
                $telegram->sendTokenAsync((int)$idUsuario, $mensaje);
            }
        } catch (\Throwable $e) {
            error_log('[AlmacenCalibracionDispensario] Telegram: ' . $e->getMessage());
        }
    }
}
