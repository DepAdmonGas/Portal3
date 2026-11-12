<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Estacion;
use App\Models\Operativo\NivelExplosividad;
use App\Models\Operativo\NivelExplosividadDetalle;
use App\Models\Operativo\NivelExplosividadFirma;
use App\Models\Operativo\NivelExplosividadPozoMotobomba;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Medición Nivel de Explosividad (Almacén)
 *
 * Migración del legacy
 *   public/admin/vistas/{nivel-explosividad-index,lista-nivel-explosividad,
 *                        lista-detalle-nivel-explosividad,nivel-explosividad-nuevo}.php
 *   public/admin/modelo/{agregar,guardar,eliminar}-nivel-explosividad.php
 *   public/admin/modelo/{agregar,eliminar}-pozo-motobombas.php
 *
 * Decisiones de paridad con el legacy:
 *  - Sin día doble, sin comentarios, sin descargas: el legacy no los tiene.
 *  - Estados a la inversa de lo esperado (así los pinta el legacy):
 *      estado 0 = borrador rosa #ffb6af, editable y eliminable, detalle bloqueado;
 *      estado 1 = finalizado verde #b0f2c2, sólo detalle, sin editar ni eliminar.
 *    Por eso NO existe edición de un registro finalizado.
 *  - Permisos C1 = legacy: Encargado (6) y Asistente Administrativo (7) sólo
 *    consultan; los demás puestos crean/editan/eliminan. El legacy lo resolvía por
 *    nombre de puesto, aquí se resuelve por id de puesto sobre el mismo umbral.
 *  - Folio por estación: legacy = folio de la última fila de esa estación + 1
 *    (no MAX(folio)); se replica tal cual. El id lo asigna AUTO_INCREMENT.
 *  - Borrador con fecha '0000-00-00', igual que el legacy.
 *  - Elemento1-3 texto obligatorios; Elemento4-18 son PPM enteros Opcionales: el
 *    legacy no validaba nada en servidor y un vacío se guardaba como 0. No se
 *    impone dominio 0-70 ni múltiplo de 5: no existía restricción.
 *  - Pozos y firmas sólo desde el formulario de un borrador (así lo alcanzaba el
 *    legacy); el detalle es sólo lectura.
 *  - Telegram sólo en finalizar y en eliminar (como el legacy): nada al crear el
 *    borrador.
 *  - NO se replica ClassEventos::registrarEvento(): Portal3 no tiene esa bitácora
 *    en ningún módulo y exigiría DDL en tiempo de ejecución.
 *  - Sí se corrige C4: el legacy borraba sólo la cabecera y dejaba huérfanos de
 *    detalle, firmas y pozos. Aquí la eliminación es en cascada.
 */
class AlmacenMedicionNivelExplosividadService
{
    public const MODULE_KEY    = 'medicion-nivel-explosividad';
    public const PARENT_MODULE = 'almacen';
    public const TITULO        = 'Medición Nivel de Explosividad';

    /**
     * El legacy escribía en departamento-operativo/imgs/firma/. Se replica una
     * carpeta de firmas propia del módulo dentro de uploads/.
     */
    public const FOLDER_FIRMAS = 'public/uploads/firmas/';

    /** Legacy: Encargado y Asistente Administrativo sólo consultaban. */
    public const PUESTOS_SOLO_LECTURA = [6, 7];

    public const TIPO_FIRMA_TOMA      = 'FIRMA DE QUIEN TOMA MEDICIÓN';
    public const TIPO_FIRMA_ESTACION  = 'FIRMA POR LA ESTACIÓN';

    /** Centinela de borrador: el legacy insertaba fecha '' y MySQL la guardaba así. */
    public const FECHA_BORRADOR = '0000-00-00';

    /** Etiquetas de Elemento4..18, en el mismo orden que la tabla del detalle. */
    public const ETIQUETAS = [
        1  => 'Tipo de Medicion',
        2  => 'Verificador',
        3  => 'Observaciones',
        4  => 'Estacionamiento',
        5  => 'Local Comercial',
        6  => 'Oficinas',
        7  => 'Bodega Local',
        8  => 'Baños Empleados',
        9  => 'Bodega de Aceites',
        10 => 'Baños Hombres',
        11 => 'Baños Mujeres',
        12 => 'Cuarto de Sucios',
        13 => 'Cuarto de Maquinas',
        14 => 'Zona 1 Despacho',
        15 => 'Zona 2 Despacho',
        16 => 'Zona 3 Despacho',
        17 => 'Cuarto de aditivo',
        18 => 'Zona de tanques',
    ];

    /** Columnas PPM (enteras) de la tabla detalle. */
    private const CAMPOS_PPM = [
        'elemento4', 'elemento5', 'elemento6', 'elemento7', 'elemento8',
        'elemento9', 'elemento10', 'elemento11', 'elemento12', 'elemento13',
        'elemento14', 'elemento15', 'elemento16', 'elemento17', 'elemento18',
    ];

    /**
     * Opciones de pozo por estación, tal como el modal legacy
     * (modal-pozo-motobombas.php). Las estaciones 8 y 14 no tenían lista: el
     * select quedaba vacío y no se podía dar de alta ningún pozo.
     */
    public const POZOS_POR_ESTACION = [
        1 => [
            'MOTOBOMBA SUPER 1', 'MOTOBOMBA SUPER 2', 'MOTOBOMBA PREMIUM',
            'MOTOBOMBA DIESEL', 'POZO DE OBSERVACIÓN 1', 'POZO DE OBSERVACIÓN 2',
            'POZO DE OBSERVACIÓN 3', 'POZO DE OBSERVACIÓN 4', 'MANIFUEL',
        ],
        2 => [
            'MOTOBOMBA SUPER', 'MOTOBOMBA PREMIUM', 'POZO DE OBSERVACIÓN 1',
            'POZO DE OBSERVACIÓN 2', 'MANIFUEL',
        ],
        3 => [
            'POZO DE OBSERVACIÓN 1', 'POZO DE OBSERVACIÓN 2', 'POZO DE OBSERVACIÓN 3',
            'POZO DE OBSERVACIÓN 4', 'MOTOBOMBA DIESEL 1', 'MOTOBOMBA DIESEL 2',
            'MOTOBOMBA SUPER 3', 'MOTOBOMBA SUPER 4', 'MOTOBOMBA PREMIUM 5',
            'MANIFUEL',
        ],
        4 => [
            'POZO DE OBSERVACIÓN 1', 'POZO DE OBSERVACIÓN 2', 'POZO DE OBSERVACIÓN 3',
            'POZO DE OBSERVACIÓN 4', 'POZO DE OBSERVACIÓN 5', 'POZO DE OBSERVACIÓN 6',
            'POZO DE OBSERVACIÓN 7', 'POZO DE OBSERVACIÓN 8', 'MOTOBOMBA DIESEL',
            'MOTOBOMBA PREMIUM', 'MOTOBOMBA SUPER 1', 'MOTOBOMBA SUPER 2', 'MANIFUEL',
        ],
        5 => [
            'POZO DE OBSERVACIÓN 1', 'POZO DE OBSERVACIÓN 2', 'POZO DE OBSERVACIÓN 3',
            'POZO DE OBSERVACIÓN 4', 'POZO DE OBSERVACIÓN 5', 'POZO DE OBSERVACIÓN 6',
            'MOTOBOMBA SUPER 1', 'MOTOBOMBA SUPER 2', 'MOTOBOMBA PREMIUM 3',
            'MOTOBOMBA DIESEL 4', 'MANIFUEL',
        ],
        6 => [
            'POZO DE OBSERVACIÓN 1', 'POZO DE OBSERVACIÓN 2', 'POZO DE OBSERVACIÓN 3',
            'MOTOBOMBA TANQUE 1', 'MOTOBOMBA TANQUE 2', 'MOTOBOMBA TANQUE 3',
            'MOTOBOMBA TANQUE 4', 'MANIFUEL',
        ],
        7 => [
            'POZO DE OBSERVACIÓN 1', 'POZO DE OBSERVACIÓN 2', 'POZO DE OBSERVACIÓN 3',
            'POZO DE OBSERVACIÓN 4', 'POZO DE OBSERVACIÓN 5', 'MOTOBOMBA SUPER 1',
            'MOTOBOMBA PREMIUM 2', 'MANIFUEL',
        ],
    ];

    /* ------------------------------------------------------------------ */
    /* Rutas y permisos                                                    */
    /* ------------------------------------------------------------------ */

    public static function getRutaBase(): string
    {
        return '/departamento-operativo/almacen/' . self::MODULE_KEY;
    }

    public static function getFirmasDir(): string
    {
        $dir = dirname(__DIR__, 2) . '/' . self::FOLDER_FIRMAS;

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /**
     * C1 (decidido): se conserva el umbral del legacy.
     *
     * El gate de acceso es el permiso 'leer' del módulo Almacén, que es la
     * autoridad de Portal3 para abrir el módulo. Dentro de él, la capacidad de
     * escritura NO se toma de modulos_puestos_do (ahí los puestos 3, 4 y 13 sólo
     * tienen leer) porque eso contradiría al legacy, que les permitía escribir.
     */
    public static function getPermisos(): array
    {
        $sesion   = Session::get('usuario') ?? [];
        $usuario  = Auth::user();
        $idPuesto = (int)($usuario->id_puesto ?? 0);

        $puedeLeer = ModuloDptoOperativoService::validaPermiso(self::PARENT_MODULE, 'leer');
        $escribe   = $puedeLeer && !in_array($idPuesto, self::PUESTOS_SOLO_LECTURA, true);

        return [
            'id_usuario'     => (int)($sesion['id'] ?? 0),
            'nombre_usuario' => (string)($sesion['nombre'] ?? ''),
            'id_puesto'      => $idPuesto,

            'puedeLeer'      => $puedeLeer,
            'puedeCrear'     => $escribe,
            'puedeEditar'    => $escribe,
            'puedeEliminar'  => $escribe,
            'puedeDescargar' => false,

            // El legacy armaba el breadcrumb por puesto: Encargado / Asistente
            // Administrativo veían "Almacén"; cualquier otro puesto veía "Mantenimiento".
            'origenMantenimiento' => !in_array($idPuesto, self::PUESTOS_SOLO_LECTURA, true),
        ];
    }

    /**
     * @return array<int, string> estaciones indexadas por id
     */
    public static function getEstacionesDisponibles(): array
    {
        $mapa = [];

        foreach (ModuleStationService::getAvailableStations(self::MODULE_KEY) as $s) {
            $mapa[(int)$s['id']] = (string)$s['nombre'];
        }

        return $mapa;
    }

    public static function nombreEstacion(int $idEstacion): string
    {
        $disponibles = self::getEstacionesDisponibles();

        if (isset($disponibles[$idEstacion])) {
            return $disponibles[$idEstacion];
        }

        $estacion = Estacion::find($idEstacion);

        return $estacion ? (string)$estacion->nombre : 'Estación #' . $idEstacion;
    }

    /**
     * Select "NOMBRE Y FIRMA POR LA ESTACIÓN": legacy filtraba
     * tb_usuarios por id_gas + id_puesto = 6 + estatus = 0 (estatus 0 = activo).
     *
     * @return array<int, array{id:int, nombre:string}>
     */
    public static function getEncargados(int $idEstacion): array
    {
        if ($idEstacion <= 0) {
            return [];
        }

        $filas = Capsule::table('tb_usuarios')
            ->where('id_gas', $idEstacion)
            ->where('id_puesto', 6)
            ->where('estatus', 0)
            ->orderBy('id')
            ->get();

        $lista = [];

        foreach ($filas as $fila) {
            $lista[] = [
                'id'      => (int)$fila->id,
                'nombre'  => (string)$fila->nombre,
            ];
        }

        return $lista;
    }

    /**
     * @return array<int, string> vacío si la estación no tenía lista en el legacy
     */
    public static function getOpcionesPozo(int $idEstacion): array
    {
        return self::POZOS_POR_ESTACION[$idEstacion] ?? [];
    }

    /**
     * @return array<int, array{id:int, pozo:string, ppm:int, ubicacion:string}>
     */
    public static function getPozos(int $idReporte): array
    {
        $pozos = [];

        if ($idReporte <= 0) {
            return $pozos;
        }

        foreach (NivelExplosividadPozoMotobomba::where('id_reporte', $idReporte)->orderBy('id')->get() as $p) {
            $pozos[] = [
                'id'         => (int)$p->id,
                'pozo'       => (string)$p->pozo_motobomba,
                'ppm'        => (int)$p->ppm,
                'ubicacion'  => (string)$p->ubicacion,
            ];
        }

        return $pozos;
    }

    /**
     * Valores precargados del formulario.
     *
     * Un borrador normalmente no tiene detalle (sólo se crea al finalizar), pero
     * se lee por si acaso para no perder lo que el usuario ya había capturado.
     *
     * @return array<string, string> claves Elemento1..18 y Observaciones,
     *                               iguales a las del payload de guardado
     */
    public static function getCamposFormulario(int $idReporte): array
    {
        $detalle = $idReporte > 0
            ? NivelExplosividadDetalle::where('id_reporte', $idReporte)->first()
            : null;

        $campos = [];

        foreach (self::ETIQUETAS as $n => $_) {
            $campos['Elemento' . $n] = $detalle
                ? (string)($detalle->{'elemento' . $n} ?? '')
                : '';
        }

        $campos['Observaciones'] = $detalle ? (string)($detalle->observaciones ?? '') : '';

        return $campos;
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

        $query = NivelExplosividad::query();

        if ($estacionFiltro !== null && $estacionFiltro > 0) {
            // Autoridad en el backend: no se puede filtrar por una estación no autorizada.
            if (!isset($disponibles[$estacionFiltro])) {
                return [];
            }
            $query->where('id_estacion', $estacionFiltro);
        } else {
            $query->whereIn('id_estacion', array_keys($disponibles));
        }

        $data = [];

        foreach ($query->orderByDesc('folio')->get() as $r) {
            $idEstacion = (int)$r->id_estacion;

            $data[] = [
                'id'              => (int)$r->id,
                'id_estacion'     => $idEstacion,
                'estacion_nombre' => $disponibles[$idEstacion] ?? ('Estación #' . $idEstacion),
                'folio'           => (int)$r->folio,
                // El legacy pintaba '00'.$folio en la columna Folio.
                'folio_texto'     => '00' . $r->folio,
                'fecha'           => $r->fecha ? formatearFecha($r->fecha) : '',
                'estado'          => (int)$r->estado,
                'es_borrador'     => (int)$r->estado === 0,
            ];
        }

        return $data;
    }

    /**
     * Detalle sólo para registros finalizados, como en el legacy: un borrador no
     * tiene contenido que mostrar.
     */
    public static function getDetalle(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $reporte = NivelExplosividad::find($id);

        if (!$reporte) {
            return null;
        }

        $disponibles = self::getEstacionesDisponibles();
        $idEstacion  = (int)$reporte->id_estacion;

        if (!isset($disponibles[$idEstacion])) {
            return null;
        }

        if ((int)$reporte->estado !== 1) {
            return null;
        }

        $detalle = NivelExplosividadDetalle::where('id_reporte', $id)->first();
        $campos  = [];

        foreach (self::ETIQUETAS as $n => $etiqueta) {
            $campo = 'elemento' . $n;
            $valor = $detalle ? ($detalle->{$campo} ?? '') : '';

            $campos[] = [
                'n'        => $n,
                'etiqueta' => $etiqueta,
                'valor'    => $valor,
                // El legacy evaluaba !empty(): 0 y '0' se mostraban como "S/I",
                // y a los 4..18 les agregaba " PPM" en la vista.
                'texto'    => empty($valor) ? 'S/I' : (string)$valor,
                'es_ppm'   => $n >= 4,
            ];
        }

        $firmas = [];

        foreach (NivelExplosividadFirma::where('id_reporte', $id)->get() as $f) {
            $firmas[] = [
                'tipo'      => (string)$f->tipo_firma,
                'id_usuario' => (int)$f->id_usuario,
                'usuario'   => self::nombreUsuario((int)$f->id_usuario),
                'imagen'    => (string)$f->imagen_firma,
                // El legacy servía la firma desde departamento-operativo/imgs/firma/.
                'url'       => $f->imagen_firma
                    ? '/uploads/firmas/' . rawurlencode((string)$f->imagen_firma)
                    : '',
            ];
        }

        $estacion = Estacion::find($idEstacion);

        return [
            'id'              => (int)$reporte->id,
            'id_estacion'     => $idEstacion,
            'estacion_nombre' => $disponibles[$idEstacion],
            'estacion_razonsocial' => (string)($estacion->razonsocial ?? ''),
            'estacion_cre'         => (string)($estacion->permisocre ?? ''),
            'folio'           => (int)$reporte->folio,
            'folio_texto'     => '00' . $reporte->folio,
            'fecha'           => $reporte->fecha ? formatearFecha($reporte->fecha) : '',
            'estado'          => (int)$reporte->estado,
            'campos'          => $campos,
            'observaciones'   => $detalle ? (string)($detalle->observaciones ?? '') : '',
            'firmas'          => $firmas,
            'pozos'           => self::getPozos($id),
        ];
    }

    /**
     * Estado del registro tal como lo pinta la lista, para que la UI bloquee
     * detalle en borradores y bloquear editar/eliminar en finalizados.
     */
    public static function getRegistro(int $id): ?NivelExplosividad
    {
        $reporte = $id > 0 ? NivelExplosividad::find($id) : null;

        if (!$reporte) {
            return null;
        }

        $disponibles = self::getEstacionesDisponibles();

        if (!isset($disponibles[(int)$reporte->id_estacion])) {
            return null;
        }

        return $reporte;
    }

    /* ------------------------------------------------------------------ */
    /* Escritura                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * El legacy hacía POST a agregar-nivel-explosividad.php y devolvía el id
     * antes de abrir el formulario. Aquí se crea el borrador y se redirige al
     * formulario: es lo que reproduce el estado rosa y permite retomarlo si el
     * usuario abandona.
     */
    public static function crearBorrador(): array
    {
        $permisos = self::getPermisos();

        if (!$permisos['puedeCrear']) {
            return self::error('No tienes permiso para registrar mediciones.', 403);
        }

        $contexto  = ModuleStationService::getContext(self::MODULE_KEY);
        $idEstacion = (int)($contexto['id_estacion'] ?? 0);
        $disponibles = self::getEstacionesDisponibles();

        if ($idEstacion <= 0) {
            return self::error('Selecciona una estación antes de registrar.', 422);
        }

        if (!isset($disponibles[$idEstacion])) {
            return self::error('No tienes permiso sobre esa estación.', 403);
        }

        $ultimo = NivelExplosividad::where('id_estacion', $idEstacion)
            ->orderByDesc('id')
            ->first();

        $folio = $ultimo ? ((int)$ultimo->folio + 1) : 1;

        $reporte = NivelExplosividad::create([
            'id_estacion' => $idEstacion,
            'folio'       => $folio,
            'fecha'       => self::FECHA_BORRADOR,
            'estado'      => 0,
        ]);

        return [
            'success' => true,
            'id'      => (int)$reporte->id,
            'folio'   => $folio,
            'message' => 'Borrador creado.',
            'code'    => 200,
        ];
    }

    /**
     * Finaliza un borrador: cabecera (fecha + estado 1), detalle y las dos firmas.
     * Corresponde a guardar-nivel-explosividad.php.
     *
     * @param array $data payload con idReporte, Fecha, Elemento1..18,
     *                    Observaciones, Encargado, baseImage1, baseImage2
     */
    public static function guardar(array $data): array
    {
        $permisos = self::getPermisos();

        if (!$permisos['puedeEditar']) {
            return self::error('No tienes permiso para registrar mediciones.', 403);
        }

        $idReporte = (int)($data['idReporte'] ?? 0);
        $reporte   = self::getRegistro($idReporte);

        if (!$reporte) {
            return self::error('Registro no encontrado.', 404);
        }

        // Sólo los borradores son editables; un finalizado es de sólo lectura.
        if ((int)$reporte->estado !== 0) {
            return self::error('El registro ya está finalizado y no puede modificarse.', 409);
        }

        if (($errores = self::validar($data)) !== []) {
            return self::error(implode(' ', $errores), 422, $errores);
        }

        $fecha = trim((string)($data['Fecha'] ?? ''));

        $filas = ['id_reporte' => $idReporte];

        foreach (self::ETIQUETAS as $n => $_) {
            $filas['elemento' . $n] = trim((string)($data['Elemento' . $n] ?? ''));
        }

        foreach (self::CAMPOS_PPM as $campo) {
            // Vacío = 0, igual que MySQL en modo no estricto con el legacy.
            $valor          = trim((string)($filas[$campo] ?? ''));
            $filas[$campo]  = $valor === '' ? 0 : (int)$valor;
        }

        $filas['observaciones'] = trim((string)($data['Observaciones'] ?? ''));

        $ruta1 = self::guardarFirma((string)($data['baseImage1'] ?? ''));
        $ruta2 = self::guardarFirma((string)($data['baseImage2'] ?? ''));

        $encargado = (int)($data['Encargado'] ?? 0);

        Capsule::transaction(function () use ($reporte, $filas, $fecha, $ruta1, $ruta2, $encargado, $permisos, $idReporte) {
            $reporte->update([
                'fecha'  => $fecha,
                'estado' => 1,
            ]);

            // Idempotencia: el borrador no tendría detalle, pero un reintento no
            // debe duplicar filas.
            NivelExplosividadDetalle::where('id_reporte', $idReporte)->delete();
            NivelExplosividadFirma::where('id_reporte', $idReporte)->delete();

            NivelExplosividadDetalle::create($filas);

            NivelExplosividadFirma::create([
                'id_reporte'   => $idReporte,
                'id_usuario'   => $permisos['id_usuario'],
                'tipo_firma'   => self::TIPO_FIRMA_TOMA,
                'imagen_firma' => $ruta1,
            ]);

            NivelExplosividadFirma::create([
                'id_reporte'   => $idReporte,
                'id_usuario'   => $encargado,
                'tipo_firma'   => self::TIPO_FIRMA_ESTACION,
                'imagen_firma' => $ruta2,
            ]);
        });

        self::notificar(
            (int)$reporte->id_estacion,
            '✅🔚',
            'finalizó el registro',
            '00' . $reporte->folio,
            $fecha
        );

        return [
            'success' => true,
            'id'      => $idReporte,
            'message' => 'Registro finalizado.',
            'code'    => 200,
        ];
    }

    /**
     * C4: además de la cabecera, borra detalle, firmas y pozos. El legacy sólo
     * borraba la cabecera y dejaba huérfanos.
     */
    public static function destroy(int $id): array
    {
        $permisos = self::getPermisos();

        if (!$permisos['puedeEliminar']) {
            return self::error('No tienes permiso para eliminar registros.', 403);
        }

        $reporte = self::getRegistro($id);

        if (!$reporte) {
            return self::error('Registro no encontrado.', 404);
        }

        // En el legacy la fila verde no tenía botón de eliminar.
        if ((int)$reporte->estado !== 0) {
            return self::error('Sólo se pueden eliminar borradores.', 409);
        }

        $idEstacion = (int)$reporte->id_estacion;
        $folio      = (int)$reporte->folio;

        Capsule::transaction(function () use ($id) {
            NivelExplosividadDetalle::where('id_reporte', $id)->delete();
            NivelExplosividadFirma::where('id_reporte', $id)->delete();
            NivelExplosividadPozoMotobomba::where('id_reporte', $id)->delete();

            NivelExplosividad::where('id', $id)->delete();
        });

        self::notificar($idEstacion, '🗑', 'eliminó el registro', '00' . $folio, '');

        return ['success' => true, 'message' => 'Registro eliminado exitosamente.', 'code' => 200];
    }

    /* ------------------------------------------------------------------ */
    /* Pozos y motobombas                                                  */
    /* ------------------------------------------------------------------ */

    public static function agregarPozo(array $data): array
    {
        $permisos = self::getPermisos();

        if (!$permisos['puedeEditar']) {
            return self::error('No tienes permiso para modificar este registro.', 403);
        }

        $reporte = self::registroEditable((int)($data['idReporte'] ?? 0));

        if (is_string($reporte)) {
            return self::error($reporte, 409);
        }

        $pozo = trim((string)($data['PozoMotobomba'] ?? ''));
        $ubic = trim((string)($data['Ubicacion'] ?? ''));
        $ppm  = trim((string)($data['PPM'] ?? ''));

        if ($pozo === '') {
            return self::error('El pozo es obligatorio.', 422);
        }

        if ($ppm === '') {
            return self::error('El PPM es obligatorio.', 422);
        }

        if (!is_numeric($ppm)) {
            return self::error('El PPM debe ser numérico.', 422);
        }

        $fila = NivelExplosividadPozoMotobomba::create([
            'id_reporte'      => (int)$reporte->id,
            'pozo_motobomba'  => $pozo,
            'ppm'             => (int)$ppm,
            'ubicacion'       => $ubic,
        ]);

        return [
            'success' => true,
            'id'      => (int)$fila->id,
            'message' => 'Pozo agregado.',
            'code'    => 200,
        ];
    }

    public static function eliminarPozo(int $idNivel, int $idReporte): array
    {
        $permisos = self::getPermisos();

        if (!$permisos['puedeEditar']) {
            return self::error('No tienes permiso para modificar este registro.', 403);
        }

        $reporte = self::registroEditable($idReporte);

        if (is_string($reporte)) {
            return self::error($reporte, 409);
        }

        $pozo = $idNivel > 0
            ? NivelExplosividadPozoMotobomba::find($idNivel)
            : null;

        // El legacy borraba por id sin comprobar pertenencia: aquí se valida.
        if (!$pozo || (int)$pozo->id_reporte !== (int)$reporte->id) {
            return self::error('Pozo no encontrado.', 404);
        }

        $pozo->delete();

        return ['success' => true, 'message' => 'Pozo eliminado.', 'code' => 200];
    }

    /**
     * Los pozos se daban de alta desde el formulario, que sólo se abre con un
     * borrador; el detalle es de sólo lectura.
     *
     * @return NivelExplosividad|string registro válido o mensaje de error
     */
    private static function registroEditable(int $idReporte): mixed
    {
        $reporte = self::getRegistro($idReporte);

        if (!$reporte) {
            return 'Registro no encontrado.';
        }

        if ((int)$reporte->estado !== 0) {
            return 'Los pozos sólo se modifican en borradores sin finalizar.';
        }

        return $reporte;
    }

    /* ------------------------------------------------------------------ */
    /* Validaciones                                                        */
    /* ------------------------------------------------------------------ */

    /**
     * Reglas del formulario legacy (nivel-explosividad-nuevo.php, función Guardar):
     * Fecha, Elemento1-3, Encargado y las dos firmas eran obligatorios.
     *
     * @return string[] lista vacía si todo es válido
     */
    private static function validar(array $data): array
    {
        $errores = [];

        $fecha = trim((string)($data['Fecha'] ?? ''));

        if ($fecha === '') {
            $errores[] = 'La fecha es obligatoria.';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || (int)date('Y', strtotime($fecha)) < 1900) {
            $errores[] = 'La fecha no es válida.';
        }

        foreach ([1, 2, 3] as $n) {
            if (trim((string)($data['Elemento' . $n] ?? '')) === '') {
                $errores[] = self::ETIQUETAS[$n] . ' es obligatorio.';
            }
        }

        foreach (self::CAMPOS_PPM as $campo) {
            $valor = trim((string)($data[str_replace('elemento', 'Elemento', $campo)] ?? ''));

            if ($valor !== '' && !is_numeric($valor)) {
                $errores[] = 'Las mediciones PPM deben ser numéricas.';
                break;
            }
        }

        if ((int)($data['Encargado'] ?? 0) <= 0) {
            $errores[] = 'Debes seleccionar al encargado de la estación.';
        }

        foreach (['baseImage1' => 'Firma de quien toma la medición', 'baseImage2' => 'Firma por la estación'] as $campo => $etiqueta) {
            if (trim((string)($data[$campo] ?? '')) === '') {
                $errores[] = $etiqueta . ' es obligatoria.';
            }
        }

        return $errores;
    }

    /* ------------------------------------------------------------------ */
    /* Firmas y Telegram                                                   */
    /* ------------------------------------------------------------------ */

    /**
     * @return string nombre de archivo guardado
     */
    private static function guardarFirma(string $base64): string
    {
        $base64 = str_replace(['data:image/png;base64,', 'data:image/jpeg;base64,', ' '], '', $base64);

        $binario = base64_decode($base64, true);

        if ($binario === false || $binario === '') {
            return '';
        }

        $nombre = uniqid('firma_', true) . '.png';

        @file_put_contents(self::getFirmasDir() . $nombre, $binario);

        return $nombre;
    }

    /**
     * Replica getChatIdUsuarios() del legacy: estación (más Comodines) y, además:
     *   1. Comercializadora, sólo si la estación es 6 o 7
     *   2. Mantenimiento (puesto 8), siempre
     *
     * El actor queda excluido del conjunto completo, como en el SQL del legacy.
     * No se usa TelegramService::notificar() porque no incluye Mantenimiento y
     * agrega Contabilidad, que el legacy no notificaba.
     */
    private static function notificar(int $idEstacion, string $icono, string $verbo, string $folio, string $fecha): void
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
            $destinos = array_values(array_unique(array_diff($destinos, [$actor])));

            $mensaje = $icono . ' ' . $autor . ' ' . $verbo
                . ' correspondiente al apartado de Nivel de Explosividad correspondiente al de módulo de Almacén.'
                . PHP_EOL . '#️⃣ No. de folio: ' . $folio;

            if ($fecha !== '') {
                $mensaje .= PHP_EOL . '🗓️ Fecha: ' . formatearFecha($fecha);
            }

            $mensaje .= PHP_EOL . PHP_EOL . '⛽ Estación: ' . self::nombreEstacion($idEstacion) . '.';

            foreach ($destinos as $idUsuario) {
                $telegram->sendTokenAsync((int)$idUsuario, $mensaje);
            }
        } catch (\Throwable $e) {
            error_log('[AlmacenMedicionNivelExplosividad] Telegram: ' . $e->getMessage());
        }
    }

    private static function nombreUsuario(int $idUsuario): string
    {
        if ($idUsuario <= 0) {
            return '';
        }

        try {
            $usuario = \App\Models\Usuario::find($idUsuario);

            return $usuario ? (string)($usuario->nombre ?? '') : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    private static function error(string $mensaje, int $code, array $errores = []): array
    {
        return [
            'success'  => false,
            'message'  => $mensaje,
            'errors'   => $errores,
            'code'     => $code,
        ];
    }
}
