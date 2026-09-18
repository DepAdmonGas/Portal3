<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Estacion;
use App\Models\Operativo\Pivoteo;
use App\Models\Operativo\PivoteoCorreo;
use App\Models\Operativo\PivoteoDetalle;
use App\Models\Operativo\PivoteoFirma;
use App\Models\Operativo\PivoteoToken;
use App\Models\PivoteoChofer;
use App\Models\UnidadesTransporte;
use App\Models\Usuario;
use Dompdf\Dompdf;
use Dompdf\Options;
use Carbon\Carbon;

class PivoteoService
{
    public const MODULE_KEY = 'pivoteo';

    public const ESTATUS_BORRADOR = 0;
    public const ESTATUS_FINALIZADO = 1;
    public const ESTATUS_FIRMADO = 2;

    public const TIPOS_FIRMA = ['A', 'B', 'C'];

    /**
     * Usuarios autorizados para firmar por defecto.
     *
     * En legacy el bloque de firma solo se mostraba a los usuarios 273 o 19.
     * Por indicación funcional se restringe a esos dos usuarios, quienes
     * solo pueden firmar mediante token (Telegram o correo) y únicamente
     * en este apartado (Pivoteo).
     *
     * @var int[]
     */
    public const USUARIOS_FIRMA_DEFAULT = [273, 19];

    public const CARPETA_PDF = 'Pivoteo';

    public const CORREO_PREDETERMINADO = 'cambiosdedestinovdm@g500network.com';

    public const ASUNTO_PREDETERMINADO = 'Formato de Pivoteo';

    /**
     * Usuarios autorizados para firmar.
     *
     * En legacy esta regla estaba codificada como idUsuario 273 o 19.
     * Para Portal3 se lee de la variable de entorno PIVOTEO_FIRMA_USUARIOS
     * (lista separada por comas). Si viene vacía se usa el valor por
     * defecto: únicamente el usuario 19 (ver USUARIOS_FIRMA_DEFAULT).
     *
     * @var int[]
     */
    public static function getUsuariosAutorizadosFirma(): array
    {
        $raw = getenv('PIVOTEO_FIRMA_USUARIOS') ?: ($_ENV['PIVOTEO_FIRMA_USUARIOS'] ?? '');
        if (trim($raw) === '') {
            return self::USUARIOS_FIRMA_DEFAULT;
        }

        $ids = array_filter(array_map('intval', explode(',', $raw)));
        return array_values($ids);
    }

    public static function getPermisos(): array
    {
        $usuario = Auth::user();
        $sessionUsuario = Session::get('usuario');
        $idUsuario = (int)($sessionUsuario['id'] ?? 0);
        $idPuesto = (int)($usuario->id_puesto ?? 0);
        $multiestacion = !empty($sessionUsuario['multiestacion']);

        $allPerms = ModuloDptoOperativoService::getPermisos($idUsuario, 'importacion');
        $modulo = $allPerms['importacion'] ?? [];

        $submodulo = null;
        foreach ($modulo['submenus'] ?? [] as $sm) {
            if (($sm['clave'] ?? '') === self::MODULE_KEY) {
                $submodulo = $sm;
                break;
            }
        }

        $accede = $submodulo !== null;

        $leer     = (bool)($submodulo['leer']     ?? ($modulo['leer'] ?? false));
        $crear    = (bool)($submodulo['crear']    ?? ($modulo['crear'] ?? false));
        $editar   = (bool)($submodulo['editar']   ?? ($modulo['editar'] ?? false));
        $eliminar = (bool)($submodulo['eliminar'] ?? ($modulo['eliminar'] ?? false));

        $usuariosFirma = self::getUsuariosAutorizadosFirma();
        $puedeFirmar = ($accede && $leer) && (empty($usuariosFirma) || in_array($idUsuario, $usuariosFirma, true));

        return [
            'id_usuario'       => $idUsuario,
            'id_puesto'        => $idPuesto,
            'multiestacion'    => $multiestacion,
            'puedeVer'         => $accede && $leer,
            'puedeCrear'       => $accede && $crear,
            'puedeEditar'      => $accede && $editar,
            'puedeDetalle'     => $accede && $leer,
            'puedeEliminar'    => $accede && $eliminar,
            'puedeFinalizar'   => $accede && ($editar || $puedeFirmar),
            'puedeFirmar'      => $puedeFirmar,
            'puedeGenerarPDF'  => $accede && $leer,
            'puedeEnviarCorreo'=> $accede && $leer && !self::puestoOcultaGmail((int)$idPuesto),
        ];
    }

    /**
     * En legacy el icono Gmail se ocultaba para los puestos
     * "Encargado" y "Asistente Administrativo" (validación por nombre de puesto).
     */
    public static function puestoOcultaGmail(int $idPuesto): bool
    {
        $puestosOcultos = self::getPuestosOcultanGmail();
        return in_array($idPuesto, $puestosOcultos, true);
    }

    /**
     * Puestos que en legacy no podían enviar correo desde Pivoteo.
     *
     * @var int[]
     */
    public static function getPuestosOcultanGmail(): array
    {
        $raw = getenv('PIVOTEO_PUESTOS_SIN_CORREO') ?: ($_ENV['PIVOTEO_PUESTOS_SIN_CORREO'] ?? '');
        if (trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(array_map('intval', explode(',', $raw))));
    }

    public static function getEstacionSeleccionada(): ?int
    {
        $ctx = ModuleStationService::getContext(self::MODULE_KEY);
        $idEstacion = $ctx['id_estacion'] ?? null;

        if ($idEstacion === null || $idEstacion === '') {
            return null;
        }

        return (int)$idEstacion;
    }

    public static function getEstacionesPermitidas(): array
    {
        return ModuleStationService::getAvailableStations(self::MODULE_KEY);
    }

    public static function estacionAutorizada(int $idEstacion): bool
    {
        $permitidas = self::getEstacionesPermitidas();
        if (empty($permitidas)) {
            return false;
        }
        foreach ($permitidas as $e) {
            if ((int)$e['id'] === $idEstacion) {
                return true;
            }
        }
        return false;
    }

    public static function getEstacionNombre(int $idEstacion): string
    {
        $estacion = Estacion::find($idEstacion);
        return $estacion ? $estacion->nombre : ('Estación #' . $idEstacion);
    }

    public static function getProductosEstacion(int $idEstacion): array
    {
        $estacion = Estacion::find($idEstacion);
        if (!$estacion) {
            return [];
        }

        $productos = [];
        foreach (['producto_uno', 'producto_dos', 'producto_tres'] as $campo) {
            $valor = trim((string)$estacion->{$campo});
            if ($valor !== '') {
                $productos[] = $valor;
            }
        }
        return $productos;
    }

    public static function getChoferes(): array
    {
        return PivoteoChofer::where('estado', 0)
            ->orderBy('nombre_chofer', 'asc')
            ->pluck('nombre_chofer')
            ->toArray();
    }

    public static function getUnidades(): array
    {
        return UnidadesTransporte::where('estado', 0)
            ->orderBy('no_unidad', 'asc')
            ->pluck('no_unidad')
            ->toArray();
    }

    public static function getTads(): array
    {
        return [
            'Atlacomulco',
            'Tizayuca',
            'Tuxpan',
            'Puebla',
            'Vopack',
        ];
    }

    public static function getTanques(): array
    {
        return [
            'Pipa',
            'Tanque 1',
            'Tanque 2',
        ];
    }

    /**
     * Catálogo de estaciones de origen para el selector del detalle.
     * Mismo comportamiento legacy: tb_estaciones con numlista <= 8 más tres
     * opciones adicionales que se resuelven por razon social.
     */
    public static function getCatalogoEstaciones(): array
    {
        $estaciones = Estacion::where('numlista', '<=', 8)
            ->orderBy('numlista')
            ->get(['id', 'razonsocial'])
            ->toArray();

        $adicionales = [
            'SERVICIO MENA, S.A. DE C.V.',
            'SUPER SERVICIO VALLEJO, S.A. DE C.V.',
            'SUPER SERVICIO PERIFERICO, S.A. DE C.V.',
        ];

        $resultado = [];
        foreach ($estaciones as $e) {
            $r = trim((string)$e['razonsocial']);
            if ($r === '') {
                continue;
            }
            $resultado[$r] = $r;
        }
        foreach ($adicionales as $r) {
            $resultado[$r] = $r;
        }

        return array_values($resultado);
    }

    /**
     * Contador de pendientes por estación para el selector de multiestación.
     * Se consideran pendientes los pivoteos finalizados (estatus 1) que aún
     * requieren firma. Devuelve el mapa que consume ModuleStationService:
     * ['total' => N, 'estacion_<id>' => N].
     */
    public static function getPendientes(): array
    {
        $permitidas = array_map('intval', array_column(self::getEstacionesPermitidas(), 'id'));

        $mapa = ['total' => 0];
        foreach ($permitidas as $idEstacion) {
            $mapa['estacion_' . $idEstacion] = 0;
        }

        if (empty($permitidas)) {
            return $mapa;
        }

        $conteos = Pivoteo::where('estatus', self::ESTATUS_FINALIZADO)
            ->whereIn('id_estacion', $permitidas)
            ->selectRaw('id_estacion, COUNT(*) as total')
            ->groupBy('id_estacion')
            ->pluck('total', 'id_estacion')
            ->toArray();

        foreach ($conteos as $idEstacion => $total) {
            $mapa['estacion_' . (int)$idEstacion] = (int)$total;
            $mapa['total'] += (int)$total;
        }

        return $mapa;
    }

    public static function getListado(?int $idEstacion = null): array
    {
        $permisos = self::getPermisos();
        if (!$permisos['puedeVer']) {
            return [];
        }

        $permitidas = array_column(self::getEstacionesPermitidas(), 'id');

        $idEstacionResuelto = $idEstacion;
        if ($idEstacionResuelto === null) {
            $ctx = ModuleStationService::getContext(self::MODULE_KEY);
            $idEstacionResuelto = $ctx['id_estacion'] !== null ? (int)$ctx['id_estacion'] : null;
        }

        $query = Pivoteo::query();

        if ($idEstacionResuelto !== null) {
            if (!in_array((int)$idEstacionResuelto, $permitidas, true)) {
                return [];
            }
            $query->where('id_estacion', $idEstacionResuelto);
        } elseif (!empty($permitidas)) {
            $query->whereIn('id_estacion', $permitidas);
        }

        $registros = $query->orderBy('id', 'desc')->get();

        if ($registros->isEmpty()) {
            return [];
        }

        $idsEstaciones = array_values(array_unique($registros->pluck('id_estacion')->all()));
        $mapaEstaciones = Estacion::whereIn('id', $idsEstaciones)
            ->get()
            ->keyBy('id');

        $mapaFirmas = self::cargarFirmas($registros->pluck('id')->map(function ($v) {
            return (int)$v;
        })->all());

        $esMulti = !empty($permisos['multiestacion']);

        $filas = [];
        foreach ($registros as $r) {
            $estatus = (int)$r->estatus;

            // Regla de edición/eliminación:
            //  - No multiestación: solo estatus 0 (Pendiente).
            //  - Multiestación: estatus 0 (Pendiente) y 1 (En proceso).
            //  - Estatus 2 (Finalizado): nadie puede editar ni eliminar.
            $permiteAccion = $esMulti
                ? in_array($estatus, [self::ESTATUS_BORRADOR, self::ESTATUS_FINALIZADO], true)
                : $estatus === self::ESTATUS_BORRADOR;

            $filas[] = [
                'id'            => (int)$r->id,
                'nocontrol'     => (int)$r->nocontrol,
                'nocontrol_txt' => '0' . (int)$r->nocontrol,
                'fecha_raw'     => self::fechaFormatoRaw($r->fecha),
                'fecha'         => self::fechaFormatoLargo($r->fecha),
                'sucursal'      => (string)$r->sucursal,
                'causa'         => (string)$r->causa,
                'estatus'       => $estatus,
                'estatus_texto' => self::estatusTexto($estatus),
                'firmas'        => $mapaFirmas[(int)$r->id] ?? [],
                'rowClass'      => $estatus === self::ESTATUS_BORRADOR ? '#ffb6af' : ($estatus === self::ESTATUS_FINALIZADO ? '#fcfcda' : '#b0f2c2'),
                'estacion_id'   => (int)$r->id_estacion,
                'estacion'      => isset($mapaEstaciones[$r->id_estacion])
                    ? $mapaEstaciones[$r->id_estacion]->nombre
                    : 'Estación #' . $r->id_estacion,
                'puedeVer'      => $estatus !== self::ESTATUS_BORRADOR,
                'puedePDF'      => $estatus === self::ESTATUS_FIRMADO,
                'puedeGmail'    => $estatus === self::ESTATUS_FIRMADO && $permisos['puedeEnviarCorreo'],
                'puedeEditar'   => $permisos['puedeEditar'] && $permiteAccion,
                'puedeEliminar' => $permisos['puedeEliminar'] && $permiteAccion,
                'puedeFirmar'   => $permisos['puedeFirmar'] && $estatus === self::ESTATUS_FINALIZADO,
                'puedeCrear'    => $permisos['puedeCrear'],
            ];
        }

        return $filas;
    }

    /**
     * Normaliza la información de una línea del pivoteo para la vista.
     */
    private static function mapearDetalle(PivoteoDetalle $det): array
    {
        return [
            'id_detalle'  => (int)$det->id,
            'estacion_fc' => (string)$det->estacion_fc,
            'destino_fc'  => (string)$det->destino_fc,
            'producto_fc' => (string)$det->producto_fc,
            'tanque_fc'   => (string)$det->tanque_fc,
            'factura_fc'  => (string)$det->factura_fc,
            'litros'      => (float)$det->litros,
            'tad'         => (string)$det->tad,
            'unidad'      => (string)$det->unidad,
            'chofer'      => (string)$det->chofer,
            'estacion_fn' => (string)$det->estacion_fn,
            'destino_fn'  => (string)$det->destino_fn,
            'tanque_fn'   => (string)$det->tanque_fn,
            'factura_fn'  => (string)$det->factura_fn,
        ];
    }

    /**
     * Devuelve una línea individual ya normalizada (para actualizaciones en vivo).
     */
    public static function detalleFila(int $idDetalle): array
    {
        $detalle = PivoteoDetalle::find($idDetalle);
        if (!$detalle) {
            throw new \RuntimeException('No se encontró la línea del pivoteo.');
        }

        return self::mapearDetalle($detalle);
    }

    public static function getEditar(int $id): array
    {
        $cabecera = self::buscarAutorizado($id);
        $permisos = self::getPermisos();
        $estatus = (int)$cabecera->estatus;

        $detalles = PivoteoDetalle::where('id_pivoteo', $id)
            ->orderBy('id', 'asc')
            ->get();

        $filas = [];
        foreach ($detalles as $det) {
            $filas[] = self::mapearDetalle($det);
        }

        $esMulti = !empty($permisos['multiestacion']);
        // Edición/eliminación: multiestación en estatus 0 y 1; el resto solo en 0.
        $permiteAccion = $esMulti
            ? in_array($estatus, [self::ESTATUS_BORRADOR, self::ESTATUS_FINALIZADO], true)
            : $estatus === self::ESTATUS_BORRADOR;
        // La edición en línea (tabla y cabecera) es exclusiva de multiestación.
        $inlineEditable = $permisos['puedeEditar'] && $esMulti && $estatus !== self::ESTATUS_FIRMADO;

        return [
            'id'               => (int)$cabecera->id,
            'id_estacion'      => (int)$cabecera->id_estacion,
            'estacion'         => self::getEstacionNombre((int)$cabecera->id_estacion),
            'nocontrol'        => (int)$cabecera->nocontrol,
            'nocontrol_txt'    => '0' . (int)$cabecera->nocontrol,
            'fecha'            => self::fechaFormatoRaw($cabecera->fecha),
            'fecha_display'    => self::fechaFormatoLargo($cabecera->fecha),
            'sucursal'         => (string)$cabecera->sucursal,
            'causa'            => (string)$cabecera->causa,
            'estatus'          => $estatus,
            'estatus_texto'    => self::estatusTexto($estatus),
            'multiestacion'    => $esMulti,
            'inlineEditable'   => $inlineEditable,
            'puedeEditar'      => $inlineEditable,
            'puedeAgregar'     => $permisos['puedeEditar'] && $permiteAccion,
            'puedeEliminarDetalle' => $permisos['puedeEditar'] && $permiteAccion,
            'puedeEditarCabecera'  => $permisos['puedeEditar'] && $esMulti && $estatus !== self::ESTATUS_FIRMADO,
            'puedeFinalizarBase'   => (($permisos['puedeFinalizar'] || $esMulti) && $estatus !== self::ESTATUS_FIRMADO),
            'puedeFinalizar'   => (($permisos['puedeFinalizar'] || $esMulti) && $estatus !== self::ESTATUS_FIRMADO && count($filas) >= 1),
            'puedeFirmar'      => $permisos['puedeFirmar'] && $estatus === self::ESTATUS_FINALIZADO,
            'puedePDF'         => $permisos['puedeGenerarPDF'] && $estatus === self::ESTATUS_FIRMADO,
            'puedeGmail'       => $permisos['puedeEnviarCorreo'] && $estatus === self::ESTATUS_FIRMADO,
            'productos'        => self::getProductosEstacion((int)$cabecera->id_estacion),
            'choferes'         => self::getChoferes(),
            'unidades'         => self::getUnidades(),
            'tads'             => self::getTads(),
            'tanques'          => self::getTanques(),
            'estaciones'       => self::getCatalogoEstaciones(),
            'correo_default'   => self::CORREO_PREDETERMINADO,
            'historial_correos'=> self::getHistorialCorreos($id),
            'firmas'           => self::cargarFirmas([$id])[$id] ?? [],
            'filas'            => $filas,
        ];
    }

    public static function crear(int $idEstacion): int
    {
        $permisos = self::getPermisos();
        if (!$permisos['puedeCrear']) {
            throw new \DomainException('No tienes permiso para crear registros de pivoteo.');
        }

        if (!self::estacionAutorizada($idEstacion)) {
            throw new \DomainException('La estación seleccionada no está autorizada para tu usuario.');
        }

        $nocontrol = (int)(Pivoteo::where('id_estacion', $idEstacion)->max('nocontrol') ?? 0) + 1;

        $cabecera = Pivoteo::create([
            'id_estacion' => $idEstacion,
            'nocontrol'   => $nocontrol,
            'estatus'     => self::ESTATUS_BORRADOR,
        ]);

        return (int)$cabecera->id;
    }

    public static function agregarDetalle(int $idPivoteo, array $datos): int
    {
        $cabecera = self::buscarAutorizado($idPivoteo);
        $permisos = self::getPermisos();
        $estatus = (int)$cabecera->estatus;
        $esMulti = !empty($permisos['multiestacion']);

        if ($estatus === self::ESTATUS_FIRMADO) {
            throw new \DomainException('El pivoteo ya fue firmado, no se pueden agregar líneas.');
        }
        if (!$permisos['puedeEditar']) {
            throw new \DomainException('No tienes permiso para agregar líneas al pivoteo.');
        }

        $permiteAccion = $esMulti
            ? in_array($estatus, [self::ESTATUS_BORRADOR, self::ESTATUS_FINALIZADO], true)
            : $estatus === self::ESTATUS_BORRADOR;
        if (!$permiteAccion) {
            throw new \DomainException('El pivoteo no permite agregar líneas en su estatus actual.');
        }

        $campos = self::validarDetalle($datos);

        $razonsocial = self::razonSocial((int)$cabecera->id_estacion);

        $detalle = PivoteoDetalle::create([
            'id_pivoteo'  => $idPivoteo,
            'estacion_fc' => '',
            'destino_fc'  => '0',
            'producto_fc' => $campos['producto'],
            'tanque_fc'   => $campos['tanque'],
            'factura_fc'  => '',
            'litros'      => $campos['litros'],
            'tad'         => $campos['tad'],
            'unidad'      => $campos['unidad'],
            'chofer'      => $campos['chofer'],
            'estacion_fn' => $razonsocial,
            'destino_fn'  => (string)self::resolverDestino($razonsocial),
            'tanque_fn'   => $campos['tanque'],
            'factura_fn'  => 'emitir nueva factura',
        ]);

        self::upsertAuxiliares($campos['chofer'], $campos['unidad']);

        return (int)$detalle->id;
    }

    /**
     * Agrega una línea en blanco (solo multiestación) para capturarla en línea.
     */
    public static function agregarDetalleVacio(int $idPivoteo): int
    {
        $cabecera = self::buscarAutorizado($idPivoteo);
        $permisos = self::getPermisos();
        $estatus = (int)$cabecera->estatus;

        if (empty($permisos['multiestacion'])) {
            throw new \DomainException('Solo los usuarios multiestación pueden agregar líneas en blanco.');
        }
        if (!$permisos['puedeEditar']) {
            throw new \DomainException('No tienes permiso para agregar líneas al pivoteo.');
        }
        if (!in_array($estatus, [self::ESTATUS_BORRADOR, self::ESTATUS_FINALIZADO], true)) {
            throw new \DomainException('El pivoteo no permite agregar líneas en su estatus actual.');
        }

        $detalle = PivoteoDetalle::create([
            'id_pivoteo'  => $idPivoteo,
            'estacion_fc' => '',
            'destino_fc'  => '',
            'producto_fc' => '',
            'tanque_fc'   => '',
            'factura_fc'  => '',
            'litros'      => 0,
            'tad'         => '',
            'unidad'      => '',
            'chofer'      => '',
            'estacion_fn' => '',
            'destino_fn'  => '',
            'tanque_fn'   => '',
            'factura_fn'  => '',
        ]);

        return (int)$detalle->id;
    }

    /**
     * Edición de una línea (opciones 1-12 de legacy) o de la cabecera (7-9).
     *
     * opciones:
     *  1 factura_fc | 2 factura_fn | 3 tad | 4 unidad | 5 chofer
     *  6 estación (ver editarEstacion) | 7 sucursal | 8 fecha | 9 causa
     * 10 tanque_fc+tanque_fn | 11 litros | 12 producto_fc
     */
    public static function editarDetalle(int $id, int $opcion, string $valor): bool
    {
        $mapa = [
            1 => ['tabla' => 'detalle', 'campo' => 'factura_fc'],
            2 => ['tabla' => 'detalle', 'campo' => 'factura_fn'],
            3 => ['tabla' => 'detalle', 'campo' => 'tad'],
            4 => ['tabla' => 'detalle', 'campo' => 'unidad'],
            5 => ['tabla' => 'detalle', 'campo' => 'chofer'],
            10 => ['tabla' => 'detalle', 'campo' => 'tanque_fc'],
            11 => ['tabla' => 'detalle', 'campo' => 'litros'],
            12 => ['tabla' => 'detalle', 'campo' => 'producto_fc'],
            7 => ['tabla' => 'cabecera', 'campo' => 'sucursal'],
            8 => ['tabla' => 'cabecera', 'campo' => 'fecha'],
            9 => ['tabla' => 'cabecera', 'campo' => 'causa'],
        ];

        if (!isset($mapa[$opcion])) {
            throw new \InvalidArgumentException('La opción de edición no es válida.');
        }

        $conf = $mapa[$opcion];

        // Las opciones de cabecera (7, 8, 9) reciben el id del pivoteo.
        if ($conf['tabla'] === 'cabecera') {
            $cabecera = Pivoteo::find($id);
            if (!$cabecera) {
                throw new \RuntimeException('No se encontró el pivoteo solicitado.');
            }
            self::validarEditable($cabecera);

            $permisos = self::getPermisos();
            if (!$permisos['puedeEditar']) {
                throw new \DomainException('No tienes permiso para editar el pivoteo.');
            }
            if (empty($permisos['multiestacion'])) {
                throw new \DomainException('Solo los usuarios multiestación pueden editar la cabecera del pivoteo.');
            }

            if ($opcion === 8) {
                self::editarFecha($id, $valor);
                return true;
            }

            $cabecera->update([$conf['campo'] => $valor]);
            return true;
        }

        $cabecera = self::cabeceraDesdeDetalle($id);
        self::validarEditable($cabecera);

        $permisos = self::getPermisos();
        if (!$permisos['puedeEditar']) {
            throw new \DomainException('No tienes permiso para editar el pivoteo.');
        }
        if (empty($permisos['multiestacion'])) {
            throw new \DomainException('Solo los usuarios multiestación pueden editar las líneas del pivoteo.');
        }

        if ($opcion === 10) {
            $detalle = PivoteoDetalle::find($id);
            $detalle->update(['tanque_fc' => $valor, 'tanque_fn' => $valor]);
            return true;
        }

        if ($opcion === 11 && !is_numeric($valor)) {
            throw new \InvalidArgumentException('El campo "Litros" debe ser numérico.');
        }

        $detalle = PivoteoDetalle::find($id);
        $detalle->update([$conf['campo'] => $valor]);

        return true;
    }

    public static function editarFecha(int $id, string $fecha): bool
    {
        $fecha = trim($fecha);
        if ($fecha === '' || !self::fechaValida($fecha)) {
            throw new \InvalidArgumentException('La fecha ingresada no es válida.');
        }

        $cabecera = Pivoteo::find($id);
        if (!$cabecera) {
            throw new \RuntimeException('No se encontró el pivoteo solicitado.');
        }
        self::validarEditable($cabecera);

        $permisos = self::getPermisos();
        if (!$permisos['puedeEditar']) {
            throw new \DomainException('No tienes permiso para cambiar la fecha.');
        }
        if (empty($permisos['multiestacion'])) {
            throw new \DomainException('Solo los usuarios multiestación pueden cambiar la fecha.');
        }

        $cabecera->update(['fecha' => $fecha]);
        return true;
    }

    public static function editarEstacion(int $idDetalle, int $categoria, string $estacion, string $destino): bool
    {
        $detalle = PivoteoDetalle::find($idDetalle);
        if (!$detalle) {
            throw new \RuntimeException('No se encontró la línea del pivoteo.');
        }
        self::validarEditable(self::cabeceraDesdeDetalle($idDetalle));

        $permisos = self::getPermisos();
        if (!$permisos['puedeEditar']) {
            throw new \DomainException('No tienes permiso para editar el pivoteo.');
        }
        if (empty($permisos['multiestacion'])) {
            throw new \DomainException('Solo los usuarios multiestación pueden editar la estación.');
        }

        $estacion = trim($estacion);
        $destino = trim($destino);

        if ($destino === '') {
            $resuelto = self::resolverDestino($estacion);
            $destino = (string)$resuelto;
        }

        if ($categoria === 1) {
            $detalle->update(['estacion_fc' => $estacion, 'destino_fc' => $destino]);
        } else {
            $detalle->update(['estacion_fn' => $estacion, 'destino_fn' => $destino]);
        }

        return true;
    }

    public static function eliminarDetalle(int $idDetalle): bool
    {
        $detalle = PivoteoDetalle::find($idDetalle);
        if (!$detalle) {
            throw new \RuntimeException('No se encontró la línea del pivoteo.');
        }

        $cabecera = Pivoteo::find($detalle->id_pivoteo);
        if (!$cabecera) {
            throw new \DomainException('No se encontró el pivoteo solicitado.');
        }
        if (!self::estacionAutorizada((int)$cabecera->id_estacion)) {
            throw new \DomainException('La estación del pivoteo no está autorizada para tu usuario.');
        }

        $permisos = self::getPermisos();
        if (!$permisos['puedeEditar']) {
            throw new \DomainException('No tienes permiso para eliminar líneas del pivoteo.');
        }

        $estatus = (int)$cabecera->estatus;
        $esMulti = !empty($permisos['multiestacion']);
        $permiteAccion = $esMulti
            ? in_array($estatus, [self::ESTATUS_BORRADOR, self::ESTATUS_FINALIZADO], true)
            : $estatus === self::ESTATUS_BORRADOR;
        if (!$permiteAccion) {
            throw new \DomainException('El pivoteo no permite eliminar líneas en su estatus actual.');
        }

        return (bool)$detalle->delete();
    }

    public static function finalizar(int $id): bool
    {
        $cabecera = self::buscarAutorizado($id);

        if ((int)$cabecera->estatus === self::ESTATUS_FIRMADO) {
            throw new \DomainException('El pivoteo ya fue firmado, no se puede finalizar.');
        }
        if ((int)$cabecera->estatus === self::ESTATUS_FINALIZADO) {
            return true;
        }

        $totalDetalles = PivoteoDetalle::where('id_pivoteo', $id)->count();
        if ($totalDetalles < 1) {
            throw new \DomainException('Debes agregar al menos una línea antes de finalizar el pivoteo.');
        }

        $cabecera->update(['estatus' => self::ESTATUS_FINALIZADO]);
        return true;
    }

    public static function eliminar(int $id): bool
    {
        $cabecera = self::buscarAutorizado($id);
        $permisos = self::getPermisos();

        if ((int)$cabecera->estatus === self::ESTATUS_FIRMADO) {
            throw new \DomainException('El pivoteo ya fue firmado, no se puede eliminar.');
        }
        if (!$permisos['puedeEliminar']) {
            throw new \DomainException('No tienes permiso para eliminar el pivoteo.');
        }

        PivoteoDetalle::where('id_pivoteo', $id)->delete();
        PivoteoToken::where('id_pivoteo', $id)->delete();
        PivoteoFirma::where('id_pivoteo', $id)->delete();
        PivoteoCorreo::where('id_pivoteo', $id)->delete();

        $cabecera->delete();
        return true;
    }

    /**
     * Genera un token de firma y lo envía por la vía solicitada.
     *
     * $via: 'telegram' | 'email'
     */
    public static function crearToken(int $id, string $via): array
    {
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        $cabecera = self::buscarAutorizado($id);
        if ((int)$cabecera->estatus !== self::ESTATUS_FINALIZADO) {
            return ['success' => false, 'message' => 'El pivoteo debe estar finalizado para generar un token de firma.'];
        }

        $permisos = self::getPermisos();
        if (!$permisos['puedeFirmar']) {
            return ['success' => false, 'message' => 'No tienes permiso para generar tokens de firma.'];
        }

        $token = rand(100000, 999999);

        PivoteoToken::where('id_pivoteo', $id)
            ->where('id_usuario', $idUsuario)
            ->delete();

        PivoteoToken::create([
            'id_pivoteo'     => $id,
            'id_usuario'     => $idUsuario,
            'token'          => $token,
            'fecha_creacion' => Carbon::now(),
        ]);

        $fecha = self::fechaFormatoRaw($cabecera->fecha);
        $nombreES = self::getEstacionNombre((int)$cabecera->id_estacion);

        if ($via === 'email') {
            try {
                $usuario = Usuario::find($idUsuario);
                $email = $usuario?->email ?? '';
                if ($email === '') {
                    return ['success' => false, 'message' => 'El usuario no tiene correo electrónico registrado.'];
                }
                $emailService = new EmailService();
                $emailService->sendToken($email, (string)$token, 'Pivoteo');
                return ['success' => true, 'message' => 'Token enviado por correo electrónico.'];
            } catch (\Throwable $e) {
                return ['success' => false, 'message' => 'Error al enviar el token por correo: ' . $e->getMessage()];
            }
        }

        try {   
$mensaje = "📲 Usa el token <b>{$token}</b> para firmar la solicitud de Pivoteo #{$id}."  . PHP_EOL . PHP_EOL . "⛽ Estación: " . $nombreES;

$telegram = new TelegramService();
            $telegram->sendToken($idUsuario, $mensaje);
            return ['success' => true, 'message' => 'Token enviado por Telegram.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error al enviar el token por Telegram: ' . $e->getMessage()];
        }
    }

    /**
     * Valida el token y registra la firma.
     * Mismo comportamiento legacy: solo tipoFirma "B" marca la cabecera como firmada.
     */
    public static function firmar(int $id, string $tipoFirma, int $token): array
    {
        $idUsuario = (int)(Session::get('usuario')['id'] ?? 0);

        if (!in_array($tipoFirma, self::TIPOS_FIRMA, true)) {
            return ['success' => false, 'message' => 'El tipo de firma no es válido.'];
        }

        $cabecera = self::buscarAutorizado($id);
        if ((int)$cabecera->estatus !== self::ESTATUS_FINALIZADO) {
            return ['success' => false, 'message' => 'El pivoteo debe estar finalizado para poder firmarlo.'];
        }

        $permisos = self::getPermisos();
        if (!$permisos['puedeFirmar']) {
            return ['success' => false, 'message' => 'No tienes permiso para firmar el pivoteo.'];
        }

        $tokenRecord = PivoteoToken::where('id_pivoteo', $id)
            ->where('id_usuario', $idUsuario)
            ->where('token', $token)
            ->orderBy('id', 'desc')
            ->first();

        if (!$tokenRecord) {
            return ['success' => false, 'message' => 'El token no es válido, vuelve a generar uno nuevo.'];
        }

        $estado = null;
        $sucursal = (string)$cabecera->sucursal;
        $causa = (string)$cabecera->causa;
        $fecha = self::fechaFormatoRaw($cabecera->fecha);

        $firmaHash = 'Firma: ' . bin2hex(random_bytes(64)) . '.' . uniqid();

        PivoteoFirma::create([
            'id_pivoteo' => $id,
            'id_usuario' => $idUsuario,
            'tipo_firma' => $tipoFirma,
            'firma'      => $firmaHash,
            'fecha'      => Carbon::now(),
        ]);

        if ($tipoFirma === 'B') {
            $cabecera->update([
                'fecha'    => $fecha,
                'sucursal' => $sucursal,
                'causa'    => $causa,
                'estatus'  => self::ESTATUS_FIRMADO,
            ]);
        }

        PivoteoToken::where('id_pivoteo', $id)->where('id_usuario', $idUsuario)->delete();

        return ['success' => true, 'message' => 'Pivoteo firmado exitosamente.'];
    }

    /**
     * Genera el PDF del pivoteo (contenido igual al legacy) y devuelve el binario.
     */
    public static function generarPdf(int $id): string
    {
        $cabecera = self::buscarAutorizado($id);
        $permisos = self::getPermisos();
        if (!$permisos['puedeGenerarPDF']) {
            throw new \DomainException('No tienes permiso para generar el PDF del pivoteo.');
        }
        if ((int)$cabecera->estatus !== self::ESTATUS_FIRMADO) {
            throw new \DomainException('El pivoteo debe estar firmado para generar el PDF.');
        }

        $detalles = PivoteoDetalle::where('id_pivoteo', $id)->orderBy('id', 'asc')->get();
        $firmas = PivoteoFirma::where('id_pivoteo', $id)->orderBy('id', 'asc')->get();

        $contenido = self::htmlPdf((array)$cabecera->getAttributes(), $detalles, $firmas);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($contenido);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $canvas->page_text(520, 800, 'Pagina: {PAGE_NUM} de {PAGE_COUNT}', null, 9);

        return $dompdf->output();
    }

    /**
     * Genera y guarda el PDF, lo envía por correo y registra el historial.
     */
    public static function enviarCorreo(int $id, string $correo, string $asunto, string $contenido): array
    {
        $cabecera = self::buscarAutorizado($id);
        $permisos = self::getPermisos();

        if ((int)$cabecera->estatus !== self::ESTATUS_FIRMADO) {
            return ['success' => false, 'message' => 'El pivoteo debe estar firmado para poder enviarse por correo.'];
        }
        if (!$permisos['puedeEnviarCorreo']) {
            return ['success' => false, 'message' => 'Tu puesto no puede enviar el pivoteo por correo.'];
        }

        $correo = trim($correo) ?: self::CORREO_PREDETERMINADO;
        $asunto = trim($asunto) ?: self::ASUNTO_PREDETERMINADO;
        $contenido = trim($contenido);

        try {
            $pdfBinario = self::generarPdf($id);

            $dir = PUBLIC_PATH . '/uploads/archivos/' . self::CARPETA_PDF . '/';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $nombrePdf = 'Pivoteo PDF-' . $id . '.pdf';
            file_put_contents($dir . $nombrePdf, $pdfBinario);

            $emailService = new EmailService();
            $emailService->sendWithPdf($correo, $asunto, $contenido, $dir . $nombrePdf, $nombrePdf);

            PivoteoCorreo::create([
                'id_pivoteo'     => $id,
                'correo'         => $correo,
                'fecha_creacion' => Carbon::now(),
            ]);

            return ['success' => true, 'message' => 'Correo enviado exitosamente.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error al enviar el correo: ' . $e->getMessage()];
        }
    }

    public static function getHistorialCorreos(int $id): array
    {
        return PivoteoCorreo::where('id_pivoteo', $id)
            ->orderBy('id', 'desc')
            ->get(['id', 'correo', 'fecha_creacion'])
            ->map(function ($c) {
                return [
                    'id'          => (int)$c->id,
                    'correo'      => (string)$c->correo,
                    'fecha_creacion' => ($c->fecha_creacion instanceof \Carbon\Carbon)
                        ? $c->fecha_creacion->format('d/m/Y g:i a')
                        : (string)$c->fecha_creacion,
                ];
            })
            ->toArray();
    }

    public static function fechaValida(string $fecha): bool
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $fecha);
        return $dt !== false && $dt->format('Y-m-d') === $fecha;
    }

    /**
     * Resuelve el código de destino a partir de la razón social de una estación.
     *
     * IMPORTANTE: no existe una relación equivalente en la base de datos
     * (op_rh_localidades.id no corresponde al código usado por el negocio).
     * El valor almacenado en op_pivoteo_detalle.destino_fc/destino_fn es el
     * código legacy, por lo que se replica exactamente el mapeo histórico
     * (ValidaEstacion) para no romper la información existente.
     */
    public static function resolverDestino(string $razonsocial): int
    {
        $razonsocial = trim($razonsocial);
        if ($razonsocial === '') {
            return 0;
        }

        $mapa = [
            'ADMINISTRADORA DE GASOLINERAS S.A. DE C.V.'              => 19,
            'ADMINISTRADORA DE GASOLINERAS INTERLOMAS'                => 21,
            'ADMINISTRADORA DE GASOLINERAS SAN AGUSTÍN S.A. DE C.V.'  => 20,
            'GASOMIRA S.A. DE C.V.'                                   => 23,
            'GASOLINERA VALLE DE GUADALUPE S.A. DE C.V.'              => 22,
            'ADMINISTRADORA DE GASOLINERAS ESMEGAS S.A. DE C.V.'      => 24,
            'ADMINISTRADORA DE GASOLINERAS XOCHIMILCO S.A. DE C.V.'   => 38,
            'Administradora de Gasolinerias Bosque Real, S. A. de C. V.' => 0,
            'SERVICIO MENA, S.A. DE C.V.'                             => 127,
            'SUPER SERVICIO VALLEJO, S.A. DE C.V.'                    => 182,
            'SUPER SERVICIO PERIFERICO, S.A. DE C.V.'                 => 192,
        ];

        return $mapa[$razonsocial] ?? 0;
    }

    private static function razonSocial(int $idEstacion): string
    {
        $estacion = Estacion::find($idEstacion);
        return $estacion ? (string)$estacion->razonsocial : '';
    }

    /**
     * Carga las firmas de uno o varios pivoteos indexadas por id de pivoteo.
     *
     * @param int[] $idsPivoteos
     * @return array<int, array<int, array{tipo:string,id_usuario:int,nombre:string,fecha:string}>>
     */
    private static function cargarFirmas(array $idsPivoteos): array
    {
        $idsPivoteos = array_values(array_unique(array_filter(array_map('intval', $idsPivoteos))));
        if (empty($idsPivoteos)) {
            return [];
        }

        $firmas = PivoteoFirma::whereIn('id_pivoteo', $idsPivoteos)
            ->orderBy('id', 'asc')
            ->get();
        if ($firmas->isEmpty()) {
            return [];
        }

        $idsUsuarios = [];
        foreach ($firmas as $f) {
            $idsUsuarios[(int)$f->id_usuario] = true;
        }

        $nombres = Usuario::whereIn('id', array_keys($idsUsuarios))
            ->pluck('nombre', 'id')
            ->toArray();

        $mapa = [];
        foreach ($firmas as $f) {
            $mapa[(int)$f->id_pivoteo][] = [
                'tipo'       => (string)$f->tipo_firma,
                'id_usuario' => (int)$f->id_usuario,
                'nombre'     => (string)($nombres[(int)$f->id_usuario] ?? ''),
                'fecha'      => self::fechaFormatoLargo($f->fecha),
            ];
        }

        return $mapa;
    }

    private static function validarDetalle(array $datos): array
    {
        $obligatorios = [
            'producto' => 'Producto',
            'tanque'   => 'Tanque',
            'litros'   => 'Litros',
            'tad'      => 'TAD',
            'unidad'   => 'Unidad',
            'chofer'   => 'Chofer',
        ];

        foreach ($obligatorios as $campo => $etiqueta) {
            if (!array_key_exists($campo, $datos) || trim((string)$datos[$campo]) === '') {
                throw new \InvalidArgumentException('El campo "' . $etiqueta . '" es obligatorio.');
            }
        }

        $litros = trim((string)$datos['litros']);
        if (!is_numeric($litros)) {
            throw new \InvalidArgumentException('El campo "Litros" debe ser numérico.');
        }

        return [
            'producto' => trim((string)$datos['producto']),
            'tanque'   => trim((string)$datos['tanque']),
            'litros'   => (float)$litros,
            'tad'      => trim((string)$datos['tad']),
            'unidad'   => trim((string)$datos['unidad']),
            'chofer'   => trim((string)$datos['chofer']),
        ];
    }

    private static function upsertAuxiliares(string $chofer, string $unidad): void
    {
        $chofer = trim($chofer);
        if ($chofer !== '' && !PivoteoChofer::where('nombre_chofer', $chofer)->exists()) {
            PivoteoChofer::create(['nombre_chofer' => $chofer, 'estado' => 0]);
        }

        $unidad = trim($unidad);
        if ($unidad !== '' && !UnidadesTransporte::where('no_unidad', $unidad)->exists()) {
            UnidadesTransporte::create(['no_unidad' => $unidad, 'estado' => 0]);
        }
    }

    private static function buscarAutorizado(int $id): Pivoteo
    {
        $cabecera = Pivoteo::find($id);
        if (!$cabecera) {
            throw new \RuntimeException('No se encontró el pivoteo solicitado.');
        }
        if (!self::estacionAutorizada((int)$cabecera->id_estacion)) {
            throw new \DomainException('La estación del pivoteo no está autorizada para tu usuario.');
        }
        return $cabecera;
    }

    private static function cabeceraDesdeDetalle(int $idDetalle): Pivoteo
    {
        $detalle = PivoteoDetalle::find($idDetalle);
        if (!$detalle) {
            throw new \RuntimeException('No se encontró la línea del pivoteo.');
        }
        $cabecera = Pivoteo::find($detalle->id_pivoteo);
        if (!$cabecera) {
            throw new \RuntimeException('No se encontró el pivoteo solicitado.');
        }
        return $cabecera;
    }

    private static function validarEditable(Pivoteo $cabecera): void
    {
        $estatus = (int)$cabecera->estatus;
        if ($estatus === self::ESTATUS_FIRMADO) {
            throw new \DomainException('El pivoteo ya fue firmado, no se puede editar.');
        }
        if (!self::estacionAutorizada((int)$cabecera->id_estacion)) {
            throw new \DomainException('La estación del pivoteo no está autorizada para tu usuario.');
        }

        $permisos = self::getPermisos();
        $esMulti = !empty($permisos['multiestacion']);
        $permiteAccion = $esMulti
            ? in_array($estatus, [self::ESTATUS_BORRADOR, self::ESTATUS_FINALIZADO], true)
            : $estatus === self::ESTATUS_BORRADOR;
        if (!$permiteAccion) {
            throw new \DomainException('El pivoteo no permite edición en su estatus actual.');
        }
    }

    private static function estatusTexto(int $estatus): string
    {
        return match ($estatus) {
            self::ESTATUS_BORRADOR   => 'Pendiente',
            self::ESTATUS_FINALIZADO => 'En proceso',
            self::ESTATUS_FIRMADO    => 'Finalizado',
            default => 'Desconocido',
        };
    }

    private static function fechaFormatoRaw($fecha): string
    {
        if ($fecha instanceof \Carbon\Carbon) {
            return $fecha->format('Y-m-d');
        }
        $str = (string)$fecha;
        if ($str === '' || str_contains($str, '-0001')) {
            return '';
        }
        try {
            return \Carbon\Carbon::parse($str)->format('Y-m-d');
        } catch (\Throwable $e) {
            return '';
        }
    }

    private static function fechaFormatoLargo($fecha): string
    {
        $raw = self::fechaFormatoRaw($fecha);
        return $raw === '' ? '' : formatearFechaLarga($raw);
    }

    /**
     * HTML del PDF (mismo contenido que el legacy: cabecera, causa, líneas y firmas)
     * generado con Dompdf en Portal3.
     */
    private static function htmlPdf(array $cabecera, \Illuminate\Support\Collection $detalles, \Illuminate\Support\Collection $firmas): string
    {
        $sucursal = htmlspecialchars((string)($cabecera['sucursal'] ?? ''), ENT_QUOTES, 'UTF-8');
        $causa = nl2br(htmlspecialchars((string)($cabecera['causa'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $fecha = self::fechaFormatoLargo($cabecera['fecha'] ?? '');
        $nocontrol = '0' . (int)($cabecera['nocontrol'] ?? 0);

        $html = '<html lang="es"><head><meta charset="UTF-8">';
        $html .= '<style>
            @page { margin: 0.5cm 0.5cm; }
            body { font-family: Arial, sans-serif; font-size: .9rem; color: #212529; }
            table { border-collapse: collapse; width: 100%; margin-bottom: 1rem; }
            th, td { padding: .5rem; vertical-align: top; border: 1px solid #dee2e6; }
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .bg-primary { background-color: #007bff; color: #fff; }
            .bg-light { background-color: #f8f9fa; }
            .border-bottom { border-bottom: 1px solid #dee2e6; }
            .text-secondary { color: #6c757d; }
        </style></head><body>';

        $html .= '<img src="' . self::rutaLogoBase64() . '" style="width: 150px;">';

        $html .= '<table class="mt-2" style="border:1px solid #dee2e6;">';
        $html .= '<tr>';
        $html .= '<td><b>Depto. Operativo</b></td>';
        $html .= '<td class="text-center" rowspan="3" style="font-size:1.3em"><b>Pivoteo</b></td>';
        $html .= '<td class="text-right"><b>Sucursal:</b></td>';
        $html .= '<td>' . $sucursal . '</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td rowspan="2"><b>G500 Network Operación y Finanzas</b></td>';
        $html .= '<td class="text-right"><b>Fecha:</b></td>';
        $html .= '<td>' . $fecha . '</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td class="text-right"><b>No. De control:</b></td>';
        $html .= '<td>' . $nocontrol . '</td>';
        $html .= '</tr>';
        $html .= '</table>';

        $html .= '<div>Especialista de Planeación Logística</div>';
        $html .= '<div style="margin-top:10px;"><b>Causa:</b></div>';
        $html .= '<div style="border:1px solid #dee2e6; padding:8px;">' . $causa . '</div>';

        foreach ($detalles as $det) {
            $html .= '<table class="mt-2" style="border:1px solid #dee2e6;">';
            $html .= '<tr class="bg-primary text-center"><td colspan="2"><b>Documentación Facturada (CANCELAR)</b></td>';
            $html .= '<td colspan="2"><b>Documentación a refacturar</b></td></tr>';

            $pares = [
                ['Estación:', $det->estacion_fc, 'Estación:', $det->estacion_fn],
                ['Destino:', $det->destino_fc, 'Destino:', $det->destino_fn],
                ['Producto:', $det->producto_fc, 'Producto:', $det->producto_fc],
                ['Tanque:', $det->tanque_fc, 'Tanque:', $det->tanque_fn],
                ['Factura:', $det->factura_fc, 'Factura:', $det->factura_fn],
                ['Litros:', number_format((float)$det->litros, 2), 'Litros:', number_format((float)$det->litros, 2)],
                ['TAD:', $det->tad, 'TAD:', $det->tad],
                ['Unidad:', $det->unidad, 'Unidad:', $det->unidad],
                ['Chofer:', $det->chofer, 'Chofer:', $det->chofer],
            ];

            foreach ($pares as $i => $par) {
                $span = '';
                $html .= '<tr>';
                $html .= '<td' . $span . '><b>' . $par[0] . '</b></td><td' . $span . '>' . htmlspecialchars((string)$par[1], ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td' . $span . '><b>' . $par[2] . '</b></td><td' . $span . '>' . htmlspecialchars((string)$par[3], ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '</tr>';
            }

            $html .= '</table>';
            $html .= '<hr>';
        }

        $html .= '<div style="margin-top:10px;">Sin más por el momento agradezco su apoyo.</div>';

        $html .= '<table class="mt-2" style="margin-top:30px; border:1px solid #dee2e6;">';
        $html .= '<tr>';

        foreach ($firmas as $firma) {
            $tipoFirma = (string)$firma->tipo_firma;
            if ($tipoFirma === 'A') {
                $titulo = 'NOMBRE Y FIRMA DEL ENCARGADO';
                $detalle = '<div><small>Firma electrónica</small></div>';
            } elseif ($tipoFirma === 'B') {
                $titulo = 'Depto Operativo';
                $detalle = '<div class="border-bottom text-center" style="padding:10px;"><small>El pivoteo se firmó por un medio electrónico.<br><b>Fecha: ' . self::fechaFormatoLargo($firma->fecha) . '</b></small></div>';
            } else {
                $titulo = 'NOMBRE Y FIRMA DE AUTORIZACIÓN';
                $detalle = '<div class="border-bottom text-center" style="padding:10px;"><small>El pivoteo se firmó por un medio electrónico.<br><b>Fecha: ' . self::fechaFormatoLargo($firma->fecha) . '</b></small></div>';
            }

            $nombre = '';
            $usuario = Usuario::find((int)$firma->id_usuario);
            if ($usuario) {
                $nombre = htmlspecialchars((string)$usuario->nombre, ENT_QUOTES, 'UTF-8');
            }

            $html .= '<td><div class="text-secondary text-center"><div class="text-center">Atentamente</div>';
            $html .= '<div>' . $nombre . '</div>' . $detalle;
            $html .= '<div style="margin-top:10px;">' . $titulo . '</div></div></td>';
        }

        $html .= '</tr></table>';
        $html .= '</body></html>';

        return $html;
    }

    private static function rutaLogoBase64(): string
    {
        $ruta = PUBLIC_PATH . '/assets/img/Logo.png';
        if (is_file($ruta)) {
            return 'data:image/png;base64,' . base64_encode((string)file_get_contents($ruta));
        }
        return '';
    }
}