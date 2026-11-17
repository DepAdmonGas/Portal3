<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Operativo\SolicitudAditivo;
use App\Models\Operativo\SolicitudAditivoTambo;
use App\Models\Operativo\SolicitudAditivoComentario;
use App\Models\Operativo\SolicitudAditivoFirma;
use App\Models\Operativo\SolicitudAditivoToken;
use App\Models\Operativo\SolicitudAditivoDocumento;
use Illuminate\Database\Capsule\Manager as Capsule;
use Carbon\Carbon;

class PedidoAditivoService
{

    public const MODULE_KEY = 'pedido-aditivo';
    public const MODULO_PADRE_CLAVE = 'comercializadora';

    public const FIRMANTE_VOBO = 19;

    public const PUESTO_ENCARGADO = 'Encargado';
    public const PUESTO_ASISTENTE_ADMIN = 'Asistente Administrativo';

    public const ESTATUS_PEDIDO = [
        0 => 'Pendiente',
        1 => 'En proceso',
        2 => 'Finalizado',
    ];

    public const ESTATUS_REPORTE = [
        0 => 'Pendiente',
        1 => 'Finalizado',
    ];

    public const PARA_COMERCIALIZADORA = 'Comercializadora de artículos gasolineros SA de CV';
    public const PARA_QUITARGA = 'Quitarga';
    public const REQUIRED_MESSAGE = 'Completa los campos obligatorios.';

    public static function normalizarCantidad(int|string $value): ?int
    {
        $value = trim((string)$value);
        if (!preg_match('/^(?:[0-9]+|[0-9]{1,3}(?:,[0-9]{3})+)$/D', $value)) return null;
        $number = filter_var(ltrim(str_replace(',', '', $value), '0') ?: '0', FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 2147483647],
        ]);
        return $number === false ? null : $number;
    }

    public const FIRMAS = [
        'A' => 'ELABORÓ / SOLICITÓ',
        'B' => 'FIRMA DE VO.BO.',
        'C' => 'AUTORIZACIÓN',
    ];

    public const ADITIVOS = [
        'GASOLINA' => ['aditivo' => 'HITEC 6590C Drum', 'kilogramo' => 185],
        'DIESEL'   => ['aditivo' => 'HITEC 4133G Drum', 'kilogramo' => 180],
    ];

    public const DOCUMENTO_TIPOS = [
        'PAGO'       => 'Pago',
        'FACTURA'    => 'Factura',
        'COMPLEMENTO' => 'Complemento',
        'OTRO'       => 'Otro',
    ];

    public const DOCUMENTO_EXTENSIONES = [
        'pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'xls', 'xlsx', 'doc', 'docx',
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
        $nombreUsuario = $sessionUsuario['nombre'] ?? '';

        $permisosDb = ModuloDptoOperativoService::permisosSesion(self::MODULO_PADRE_CLAVE);
        $tienePermisoLeer = !empty($permisosDb['leer']);
        $tienePermisoCrear = !empty($permisosDb['crear']);
        $tienePermisoEditar = !empty($permisosDb['editar']);
        $tienePermisoEliminar = !empty($permisosDb['eliminar']);
        $tienePermisoDescargar = !empty($permisosDb['descargar']);
        $tieneSubmenuAditivo = false;

        if (!empty($permisosDb['submenus'])) {
            foreach ($permisosDb['submenus'] as $sub) {
                if (($sub['clave'] ?? '') === self::MODULE_KEY) {
                    $tieneSubmenuAditivo = true;
                    break;
                }
            }
        }

        $esEncargado = in_array($nombrePuesto, [
            self::PUESTO_ENCARGADO,
            self::PUESTO_ASISTENTE_ADMIN,
        ], true);

        return [
            'id_usuario'       => $idUsuario,
            'id_estacion'      => $idEstacion,
            'id_puesto'        => $idPuesto,
            'nombre_puesto'    => $nombrePuesto,
            'nombre_usuario'   => $nombreUsuario,
            'multiestacion'    => $multiestacion,
            'tieneSubmenu'     => $tieneSubmenuAditivo,
            'puedeLeer'        => $tienePermisoLeer || $tieneSubmenuAditivo,
            'puedeAcceso'      => $tienePermisoLeer || $tieneSubmenuAditivo,
            'puedeCrear'       => $tienePermisoCrear,
            'puedeEditar'      => $tienePermisoEditar,
            'puedeEliminar'    => $tienePermisoEliminar,
            'puedeDescargar'   => $tienePermisoDescargar,
            'esEncargado'      => $esEncargado,
            'esFirmanteVOBO'   => $idUsuario === self::FIRMANTE_VOBO,
            'puedeFirmarVoBo'  => $idUsuario === self::FIRMANTE_VOBO,
        ];
    }

    public static function getAllowedStationIds(): array
    {
        $ids = [];
        foreach (ModuleStationService::getAvailableStations(self::MODULE_KEY) as $s) {
            $ids[] = (int)$s['id'];
        }
        return array_values(array_unique($ids));
    }

    public static function getNombreEstacion(int $idEstacion): string
    {
        $estacion = Estacion::find($idEstacion);
        return $estacion ? $estacion->nombre : '';
    }

    public static function getRazonSocialEstacion(int $idEstacion): string
    {
        $estacion = Estacion::find($idEstacion);
        return $estacion ? $estacion->razonsocial : '';
    }

    public static function getPersonal(int $idUsuario): array
    {
        $usuario = Usuario::find($idUsuario);
        if (!$usuario) {
            return ['nombre' => '', 'puesto' => ''];
        }
        return [
            'nombre' => $usuario->nombre ?? '',
            'puesto' => $usuario->puesto->tipo_puesto ?? '',
        ];
    }

    private static function queryPendientesFirmaVoBoActual(array $stationIds)
    {
        $stationIds = array_values(array_unique(array_filter(
            array_map('intval', $stationIds),
            static fn(int $id): bool => $id > 0
        )));
        if ($stationIds === []) {
            return null;
        }

        return SolicitudAditivo::query()
            ->whereIn('id_estacion', $stationIds)
            ->where('status', 1)
            ->whereDoesntHave('firmas', function ($query): void {
                $query->where('tipo_firma', 'B');
            });
    }

    /** Solicitudes que siguen esperando Vo.Bo., sin excluir pendientes antiguos. */
    public static function getPendientesFirmaVoBoCard(): int
    {
        $permisos = self::getPermisos();
        if ($permisos['id_usuario'] !== self::FIRMANTE_VOBO) {
            return 0;
        }

        $query = self::queryPendientesFirmaVoBoActual(self::getAllowedStationIds());
        return $query ? (int)$query->count() : 0;
    }

    private static function formatearFechaHora($fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return formatearFecha($fecha) . ', ' . $fecha->format('g:i a');
        }
        if ($fecha === null || trim((string)$fecha) === '') {
            return '';
        }
        return formatearFecha($fecha);
    }

    private static function fechaEntregaValida($fechaEntrega): bool
    {
        $raw = trim((string)$fechaEntrega);
        return $raw !== '' && strpos($raw, '0000-00-00') !== 0;
    }

    public static function getPendingCountsActual(): int
    {
        $ctx = ModuleStationService::getContext(self::MODULE_KEY);
        $idEstacion = $ctx['id_estacion'];

        if ((int)(Session::get('usuario')['id'] ?? 0) === self::FIRMANTE_VOBO) {
            $allowed = self::getAllowedStationIds();
            if ($idEstacion) {
                if (!in_array((int)$idEstacion, $allowed, true)) {
                    return 0;
                }
                $allowed = [(int)$idEstacion];
            }

            $query = self::queryPendientesFirmaVoBoActual($allowed);
            return $query ? (int)$query->count() : 0;
        }

        $q = SolicitudAditivo::query()
            ->where('status', 1)
            ->whereDoesntHave('firmas', function ($query): void {
                $query->where('tipo_firma', 'B');
            });

        if ($idEstacion) {
            $q->where('id_estacion', $idEstacion);
        } else {
            $allowed = self::getAllowedStationIds();
            if (!empty($allowed)) {
                $q->whereIn('id_estacion', $allowed);
            }
        }

        return (int)$q->count();
    }

    public static function getPendingCountsFlat(): array
    {
        $pendientes = ['total' => 0];
        $ids = [];
        foreach (ModuleStationService::getAvailableStations(self::MODULE_KEY) as $s) {
            $id = (int)$s['id'];
            $ids[] = $id;
            $pendientes['estacion_' . $id] = 0;
        }

        if (!empty($ids)) {
            $query = SolicitudAditivo::query()
                ->selectRaw('id_estacion, COUNT(*) as total')
                ->whereIn('id_estacion', $ids)
                ->where('status', 1)
                ->whereDoesntHave('firmas', function ($firmaQuery): void {
                    $firmaQuery->where('tipo_firma', 'B');
                });

            $rows = $query->groupBy('id_estacion')->get();

            foreach ($rows as $row) {
                $key = 'estacion_' . (int)$row->id_estacion;
                if (isset($pendientes[$key])) {
                    $pendientes[$key] = (int)$row->total;
                    $pendientes['total'] += (int)$row->total;
                }
            }
        }

        return $pendientes;
    }

    /* ================= SOLICITUDES (op_solicitud_aditivo) ================= */

    public static function getSolicitudes(int $idEstacion): array
    {
        $solicitudes = SolicitudAditivo::where('id_estacion', $idEstacion)
            ->orderBy('id', 'desc')
            ->get();

        $result = [];
        foreach ($solicitudes as $solicitud) {
            $personal = self::getPersonal($solicitud->id_personal);
            $tambos = SolicitudAditivoTambo::where('id_reporte', $solicitud->id)->get();
            $totalTambos = 0;
            foreach ($tambos as $tambo) {
                $totalTambos += (int)$tambo->cantidad;
            }

            $fechaEntregaRaw = (string)$solicitud->getRawOriginal('fecha_entrega');
            $tieneFirmaB = SolicitudAditivoFirma::where('id_reporte', $solicitud->id)
                ->where('tipo_firma', 'B')
                ->exists();
            if ($tieneFirmaB && (int)$solicitud->status !== 2) {
                $solicitud->status = 2;
                $solicitud->save();
            }
            $status = $tieneFirmaB ? 2 : (int)$solicitud->status;

            $result[] = [
                'id'               => (int)$solicitud->id,
                'orden_compra'     => (int)$solicitud->orden_compra,
                'id_estacion'      => (int)$solicitud->id_estacion,
                'nombre_estacion'  => self::getNombreEstacion((int)$solicitud->id_estacion),
                'id_personal'      => (int)$solicitud->id_personal,
                'personal'         => $personal['nombre'],
                'puesto'           => $personal['puesto'],
                'fecha'            => $solicitud->fecha ? formatearFecha($solicitud->fecha) : 'Sin información',
                'para'             => (string)$solicitud->para,
                'fecha_entrega'    => self::fechaEntregaValida($fechaEntregaRaw) ? formatearFecha($fechaEntregaRaw) : 'Sin información',
                'has_fecha_entrega'=> self::fechaEntregaValida($fechaEntregaRaw),
                'comentarios'      => (string)$solicitud->comentarios,
                'total_comentarios'=> (int)SolicitudAditivoComentario::where('id_reporte', $solicitud->id)->count(),
                'total_tambos'     => $totalTambos,
                'num_tambos'       => $tambos->count(),
                'status'           => $status,
                'status_label'     => self::ESTATUS_PEDIDO[$status] ?? 'Desconocido',
                'tiene_firma'      => $tieneFirmaB,
                'tiene_firma_a'    => SolicitudAditivoFirma::where('id_reporte', $solicitud->id)->where('tipo_firma', 'A')->exists(),
            ];
        }

        return $result;
    }

    public static function getSolicitud(int $id): ?array
    {
        $solicitud = SolicitudAditivo::find($id);
        if (!$solicitud) {
            return null;
        }

        $personal = self::getPersonal($solicitud->id_personal);

        $tambos = [];
        $totalTambos = 0;
        $totalKilogramos = 0;
        $num = 1;
        foreach (SolicitudAditivoTambo::where('id_reporte', $id)->orderBy('id', 'asc')->get() as $tambo) {
            $totalTambos += (int)$tambo->cantidad;
            $totalKilogramos += (int)$tambo->cantidad * (int)$tambo->kilogramo;
            $tambos[] = [
                'id'        => (int)$tambo->id,
                'num'       => $num++,
                'cantidad'  => (int)$tambo->cantidad,
                'producto'  => (string)$tambo->producto,
                'aditivo'   => (string)$tambo->aditivo,
                'kilogramo' => (int)$tambo->kilogramo,
            ];
        }

        $firmas = [];
        foreach (SolicitudAditivoFirma::where('id_reporte', $id)->orderBy('id', 'asc')->get() as $firma) {
            $tipo = $firma->tipo_firma;
            $firmaRaw = (string)($firma->firma ?? '');
            $esImagen = $tipo === 'A' && $firmaRaw !== '' && strpos($firmaRaw, 'Firma:') !== 0;
            $archivoFirma = $esImagen ? basename(str_replace('\\', '/', $firmaRaw)) : null;
            $firmaDisponible = $esImagen && self::existeDocumentoAditivo($firmaRaw);
            $fechaFirmaObj = $firma->fecha;
            $fechaCompuesta = '';
            if ($fechaFirmaObj instanceof \DateTimeInterface) {
                $fechaCompuesta = formatearFecha($fechaFirmaObj) . ', ' . $fechaFirmaObj->format('g:i a');
            } elseif ($fechaFirmaObj !== null && trim((string)$fechaFirmaObj) !== '') {
                $fechaCompuesta = formatearFecha($fechaFirmaObj);
            }

            $firmaTexto = 'La solicitud de aditivo se firmó por un medio electrónico.';

            $firmas[$tipo] = [
                'tipo_firma'     => $tipo,
                'tipo_label'     => self::FIRMAS[$tipo] ?? 'FIRMA',
                'id_usuario'     => (int)$firma->id_usuario,
                'firma'          => $firmaRaw !== '' ? $firmaRaw : 'Sin información',
                'fecha'          => $fechaCompuesta,
                'firma_texto'    => $tipo === 'A' ? '' : $firmaTexto,
                'firma_img_url'  => $firmaDisponible ? '/download?tipo=pedido-aditivo&view=1&file=' . rawurlencode($archivoFirma) : '',
                'archivo_faltante' => $esImagen && !$firmaDisponible,
                'personal'       => self::getPersonal($firma->id_usuario)['nombre'],
                'usuario_nombre' => self::getPersonal($firma->id_usuario)['nombre'],
            ];
        }

        // La firma B confirma el cierre aunque un guardado anterior haya dejado
        // desactualizado el campo status de la solicitud.
        if (isset($firmas['B']) && (int)$solicitud->status !== 2) {
            $solicitud->status = 2;
            $solicitud->save();
        }
        $status = isset($firmas['B']) ? 2 : (int)$solicitud->status;

        $fechaEntregaRaw = (string)$solicitud->getRawOriginal('fecha_entrega');

        return [
            'id'               => (int)$solicitud->id,
            'orden_compra'     => (int)$solicitud->orden_compra,
            'id_estacion'      => (int)$solicitud->id_estacion,
            'nombre_estacion'  => self::getNombreEstacion($solicitud->id_estacion),
            'id_personal'      => (int)$solicitud->id_personal,
            'personal'         => $personal['nombre'],
            'puesto'           => $personal['puesto'],
            'fecha'            => $solicitud->fecha ? formatearFecha($solicitud->fecha) : '',
            'fecha_iso'        => $solicitud->fecha ? $solicitud->fecha->format('Y-m-d') : '',
            'para'             => (string)$solicitud->para,
            'fecha_entrega'    => self::fechaEntregaValida($fechaEntregaRaw) ? formatearFecha($fechaEntregaRaw) : '',
            'fecha_entrega_iso'=> self::fechaEntregaValida($fechaEntregaRaw) ? substr($fechaEntregaRaw, 0, 10) : '',
            'has_fecha_entrega'=> self::fechaEntregaValida($fechaEntregaRaw),
            'comentarios'      => (string)$solicitud->comentarios,
            'status'           => $status,
            'status_label'     => self::ESTATUS_PEDIDO[$status] ?? 'Desconocido',
            'total_tambos'     => $totalTambos,
            'total_kilogramos' => $totalKilogramos,
            'tambos'           => $tambos,
            'firmas'           => $firmas,
        ];
    }

    public static function crearSolicitud(int $idEstacion, int $idUsuario): array
    {
        if (!$idEstacion || !$idUsuario) {
            return ['success' => false, 'message' => 'Datos incompletos'];
        }

        $ordenCompra = (int)SolicitudAditivo::where('id_estacion', $idEstacion)->max('orden_compra') + 1;

        $para = in_array($idEstacion, [6, 7], true)
            ? self::PARA_QUITARGA
            : self::PARA_COMERCIALIZADORA;

        $solicitud = SolicitudAditivo::create([
            'id_estacion'   => $idEstacion,
            'orden_compra'  => $ordenCompra,
            'fecha'         => Carbon::now()->format('Y-m-d'),
            'id_personal'   => $idUsuario,
            'para'          => $para,
            'fecha_entrega' => '0000-00-00',
            'comentarios'   => '',
            'status'        => 0,
        ]);

        return ['success' => true, 'id' => (int)$solicitud->id, 'message' => 'Solicitud creada correctamente'];
    }

    public static function guardarDatos(int $id, string $fecha, string $para, string $fechaEntrega, string $comentarios): array
    {
        $solicitud = SolicitudAditivo::find($id);
        if (!$solicitud) {
            return ['success' => false, 'message' => 'Solicitud no encontrada'];
        }

        if ((int)$solicitud->status !== 0) {
            return ['success' => false, 'message' => 'Solo los borradores pueden editarse'];
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($fecha))) {
            return ['success' => false, 'message' => 'La fecha es obligatoria y debe ser válida'];
        }

        if (trim($para) === '') {
            return ['success' => false, 'message' => 'Debe seleccionar la razón social'];
        }

        $fechaEntregaFinal = '0000-00-00';
        if (trim($fechaEntrega) !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($fechaEntrega))) {
            $fechaEntregaFinal = trim($fechaEntrega);
        }

        $solicitud->fecha = trim($fecha);
        $solicitud->para = trim($para);
        $solicitud->fecha_entrega = $fechaEntregaFinal;
        $solicitud->comentarios = trim($comentarios);
        $solicitud->save();

        return ['success' => true, 'message' => 'Datos guardados correctamente'];
    }

    public static function agregarTambo(int $idReporte, string $producto, int|string $cantidad): array
    {
        $solicitud = SolicitudAditivo::find($idReporte);
        if (!$solicitud) {
            return ['success' => false, 'message' => 'Solicitud no encontrada'];
        }

        if ((int)$solicitud->status !== 0) {
            return ['success' => false, 'message' => 'Solo los borradores pueden editarse'];
        }

        $producto = strtoupper(trim($producto));
        if (!isset(self::ADITIVOS[$producto])) {
            return ['success' => false, 'message' => 'Debe seleccionar un producto válido'];
        }

        $cantidad = self::normalizarCantidad($cantidad);
        if ($cantidad === null) return ['success' => false, 'message' => self::REQUIRED_MESSAGE];

        Capsule::beginTransaction();

        try {
            $tambo = SolicitudAditivoTambo::where('id_reporte', $idReporte)
                ->where('producto', $producto)
                ->lockForUpdate()
                ->first();

            if ($tambo) {
                $tambo->cantidad = $cantidad;
                $tambo->aditivo = self::ADITIVOS[$producto]['aditivo'];
                $tambo->kilogramo = self::ADITIVOS[$producto]['kilogramo'];
                $tambo->save();
            } else {
                SolicitudAditivoTambo::create([
                    'id_reporte' => $idReporte,
                    'cantidad'   => $cantidad,
                    'producto'   => $producto,
                    'aditivo'    => self::ADITIVOS[$producto]['aditivo'],
                    'kilogramo'  => self::ADITIVOS[$producto]['kilogramo'],
                ]);
            }

            Capsule::commit();
        } catch (\Throwable $e) {
            Capsule::rollBack();
            return ['success' => false, 'message' => 'Error al agregar el tambo', 'error' => $e->getMessage()];
        }

        return ['success' => true, 'message' => 'Tambo agregado correctamente'];
    }

    public static function editarTambo(int $id, int|string $cantidad, ?string $producto = null): array
    {
        $tambo = SolicitudAditivoTambo::find($id);
        if (!$tambo) {
            return ['success' => false, 'message' => 'Tambo no encontrado'];
        }

        $solicitud = SolicitudAditivo::find($tambo->id_reporte);
        if (!$solicitud || (int)$solicitud->status !== 0) {
            return ['success' => false, 'message' => 'Solo los borradores pueden editarse'];
        }

        $cantidad = self::normalizarCantidad($cantidad);
        if ($cantidad === null) return ['success' => false, 'message' => self::REQUIRED_MESSAGE];

        if ($producto !== null) {
            $producto = strtoupper(trim($producto));
            if (!isset(self::ADITIVOS[$producto])) {
                return ['success' => false, 'message' => 'Debe seleccionar un producto válido'];
            }
            $tambo->producto = $producto;
            $tambo->aditivo = self::ADITIVOS[$producto]['aditivo'];
            $tambo->kilogramo = self::ADITIVOS[$producto]['kilogramo'];
        }

        $tambo->cantidad = $cantidad;
        $tambo->save();

        return ['success' => true, 'message' => 'Cantidad actualizada correctamente'];
    }

    public static function eliminarTambo(int $id): array
    {
        $tambo = SolicitudAditivoTambo::find($id);
        if (!$tambo) {
            return ['success' => false, 'message' => 'Tambo no encontrado'];
        }

        $solicitud = SolicitudAditivo::find($tambo->id_reporte);
        if (!$solicitud || (int)$solicitud->status !== 0) {
            return ['success' => false, 'message' => 'Solo los borradores pueden editarse'];
        }

        $tambo->delete();

        return ['success' => true, 'message' => 'Tambo eliminado correctamente'];
    }

    public static function finalizarSolicitud(int $id, int $idUsuario, string $firmaBase64 = ''): array
    {
        $permisos = self::getPermisos();
        if ($idUsuario <= 0 || $idUsuario !== $permisos['id_usuario']
            || (!$permisos['esEncargado'] && !$permisos['puedeEditar'])) {
            return ['success' => false, 'message' => 'No tienes permiso para finalizar solicitudes'];
        }
        if ($firmaBase64 === '' || !str_starts_with($firmaBase64, 'data:image/png;base64,')) {
            return ['success' => false, 'message' => self::REQUIRED_MESSAGE];
        }
        $dataFirma = base64_decode(substr($firmaBase64, strlen('data:image/png;base64,')), true);
        if ($dataFirma === false || $dataFirma === '' || strlen($dataFirma) > 5 * 1024 * 1024
            || !($image = @getimagesizefromstring($dataFirma)) || $image[2] !== IMAGETYPE_PNG) {
            return ['success' => false, 'message' => 'La firma no es válida'];
        }

        $solicitud = SolicitudAditivo::find($id);
        if (!$solicitud) {
            return ['success' => false, 'message' => 'Solicitud no encontrada'];
        }

        if ((int)$solicitud->status !== 0) {
            return ['success' => false, 'message' => 'Solo los borradores pueden finalizarse'];
        }

        $filePath = null;
        try {
            Capsule::connection()->transaction(function () use ($id, $idUsuario, $dataFirma, &$filePath): void {
                $locked = SolicitudAditivo::where('id', $id)->lockForUpdate()->first();
                if (!$locked || (int)$locked->status !== 0) throw new \RuntimeException('La solicitud ya no está pendiente');
                if (!in_array((int)$locked->id_estacion, self::getAllowedStationIds(), true)) throw new \RuntimeException('No tienes permiso para finalizar esta solicitud');
                if (!self::fechaEntregaValida((string)$locked->getRawOriginal('fecha_entrega'))) {
                    throw new \InvalidArgumentException(self::REQUIRED_MESSAGE);
                }
                if (!SolicitudAditivoTambo::where('id_reporte', $id)->exists()
                    || SolicitudAditivoTambo::where('id_reporte', $id)->where('cantidad', '<=', 0)->exists()) {
                    throw new \InvalidArgumentException(self::REQUIRED_MESSAGE);
                }
                if (SolicitudAditivoFirma::where('id_reporte', $id)->where('tipo_firma', 'A')->exists()) {
                    throw new \RuntimeException('La firma ya fue registrada');
                }
                $root = dirname(__DIR__, 2) . '/public/uploads/archivos/';
                if (!is_dir($root) && !@mkdir($root, 0775, true) && !is_dir($root)) throw new \RuntimeException('No se pudo preparar el directorio de firmas');
                $filename = 'aditivo-' . $id . '-' . bin2hex(random_bytes(16)) . '.png';
                $filePath = $root . $filename;
                if (@file_put_contents($filePath, $dataFirma, LOCK_EX) !== strlen($dataFirma)) throw new \RuntimeException('No se pudo guardar la firma');
                SolicitudAditivoFirma::create([
                    'id_reporte' => $id, 'id_usuario' => $idUsuario, 'fecha' => Carbon::now(),
                    'tipo_firma' => 'A', 'firma' => $filename,
                ]);
                $locked->status = 1;
                $locked->save();
            });
        } catch (\Throwable $e) {
            if ($filePath && is_file($filePath)) @unlink($filePath);
            error_log('Error al finalizar solicitud de aditivo: ' . $e->getMessage());
            return ['success' => false, 'message' => $e instanceof \InvalidArgumentException ? self::REQUIRED_MESSAGE : ($e->getMessage() ?: 'No se pudo guardar la firma y finalizar la solicitud')];
        }

        self::notificarFinalizarSolicitud($id, $idUsuario);

        return ['success' => true, 'message' => 'Solicitud finalizada correctamente'];
    }

    public static function eliminarSolicitud(int $id, int $idUsuario): array
    {
        $solicitud = SolicitudAditivo::find($id);
        if (!$solicitud) {
            return ['success' => false, 'message' => 'Solicitud no encontrada'];
        }

        if ((int)$solicitud->status >= 2) {
            return ['success' => false, 'message' => 'No se puede eliminar una solicitud autorizada o firmada'];
        }

        $idEstacion = (int)$solicitud->id_estacion;
        $fecha = $solicitud->fecha
            ? (($solicitud->fecha instanceof \DateTimeInterface) ? $solicitud->fecha : $solicitud->getRawOriginal('fecha'))
            : null;

        SolicitudAditivoTambo::where('id_reporte', $id)->delete();
        SolicitudAditivoComentario::where('id_reporte', $id)->delete();
        SolicitudAditivoToken::where('id_reporte', $id)->delete();
        SolicitudAditivoFirma::where('id_reporte', $id)->delete();
        $solicitud->delete();

        self::notificarEliminarSolicitud($id, $idEstacion, $fecha, $idUsuario);

        return ['success' => true, 'message' => 'Solicitud eliminada correctamente'];
    }

    /* ================= TOKEN Y FIRMA VOBO ================= */

    public static function crearToken(int $id, int $idUsuario, string $via = 'telegram'): array
    {
        $solicitud = SolicitudAditivo::find($id);
        if (!$solicitud) {
            return ['success' => false, 'message' => 'Solicitud no encontrada'];
        }

        if (SolicitudAditivoFirma::where('id_reporte', $id)->where('tipo_firma', 'B')->exists()) {
            if ((int)$solicitud->status !== 2) {
                $solicitud->status = 2;
                $solicitud->save();
            }
            return ['success' => false, 'message' => 'El VoBo ya fue firmado'];
        }

        if ((int)$solicitud->status !== 1) {
            return ['success' => false, 'message' => 'La solicitud no está en espera de firma'];
        }

        $token = rand(100000, 999999);

        SolicitudAditivoToken::where('id_reporte', $id)
            ->where('id_usuario', $idUsuario)
            ->delete();

        SolicitudAditivoToken::create([
            'id_reporte'     => $id,
            'id_usuario'     => $idUsuario,
            'fecha_creacion' => Carbon::now(),
            'token'          => $token,
        ]);

        if ($via === 'email') {
            $usuario = Usuario::find($idUsuario);
            $email = $usuario->email ?? '';
            if (!$email) {
                return ['success' => false, 'message' => 'El usuario no tiene correo electrónico registrado'];
            }

            try {
                $enviado = (new EmailService())->sendToken($email, (string)$token, 'Pedido de Aditivo');
            } catch (\Throwable $e) {
                error_log('Error Email token solicitud aditivo: ' . $e->getMessage());
                return ['success' => false, 'message' => 'Error al enviar el correo electrónico'];
            }

            if (!$enviado) {
                return ['success' => false, 'message' => 'Error al enviar el correo electrónico'];
            }

            return ['success' => true, 'message' => 'Token enviado por correo electrónico'];
        }

        $nombreES = self::getNombreEstacion((int)$solicitud->id_estacion);
        $fechaCompleta = self::formatearFechaHora($solicitud->fecha);

        $mensaje = '📲 Usa el token <b>' . $token . '</b> para firmar el "VOBO" en la Solicitud de Aditivo de No. ' . $solicitud->id . ' correspondiente al dia: ' . $fechaCompleta . '.'
            . PHP_EOL . PHP_EOL . '⛽ Estación: ' . $nombreES . '.';

        try {
            $telegram = new TelegramService();
            $telegram->sendToken($idUsuario, $mensaje);
        } catch (\Throwable $e) {
            error_log('Error Telegram token solicitud aditivo: ' . $e->getMessage());
        }

        return ['success' => true, 'message' => 'Token enviado por Telegram'];
    }

    public static function firmarVoBo(int $id, int $idUsuario, string $token): array
    {
        try {
            $estadoFirma = Capsule::connection()->transaction(function () use ($id, $idUsuario, $token): string {
                $solicitud = SolicitudAditivo::where('id', $id)->lockForUpdate()->first();
                if (!$solicitud) return 'not_found';

                $firmaExistente = SolicitudAditivoFirma::where('id_reporte', $id)
                    ->where('tipo_firma', 'B')
                    ->exists();
                if ($firmaExistente) {
                    // Repara la inconsistencia histórica firma B + estado 1.
                    if ((int)$solicitud->status !== 2) {
                        $solicitud->status = 2;
                        $solicitud->save();
                    }
                    return 'already_signed';
                }

                if ((int)$solicitud->status !== 1) return 'not_waiting';

                $tokenRegistro = SolicitudAditivoToken::where('id_reporte', $id)
                    ->where('id_usuario', $idUsuario)
                    ->where('token', (int)$token)
                    ->orderBy('id', 'desc')
                    ->lockForUpdate()
                    ->first();
                if (!$tokenRegistro) return 'invalid_token';

                SolicitudAditivoFirma::create([
                    'id_reporte' => $id,
                    'id_usuario' => $idUsuario,
                    'fecha'      => Carbon::now(),
                    'tipo_firma' => 'B',
                    'firma'      => 'Firma: ' . bin2hex(random_bytes(64)) . '.' . uniqid(),
                ]);

                $solicitud->status = 2;
                $solicitud->save();
                SolicitudAditivoToken::where('id_reporte', $id)
                    ->where('id_usuario', $idUsuario)
                    ->delete();

                return 'signed';
            });
        } catch (\Throwable $e) {
            error_log('Error firma VoBo solicitud aditivo: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error al firmar el VoBo'];
        }

        $errores = [
            'not_found' => 'Solicitud no encontrada',
            'already_signed' => 'El VoBo ya fue firmado',
            'not_waiting' => 'La solicitud no está en espera de firma',
            'invalid_token' => 'El token no es válido',
        ];
        if ($estadoFirma !== 'signed') {
            return ['success' => false, 'message' => $errores[$estadoFirma] ?? 'Error al firmar el VoBo'];
        }

        self::notificarFirmarVoBo($id, $idUsuario);

        return ['success' => true, 'message' => 'VoBo firmado correctamente'];
    }

    /* ================= DOCUMENTOS / PAGOS (op_solicitud_aditivo_documento) ================= */

    private static function rutaDocumentoAditivo(string $referencia): ?string
    {
        $nombre = basename(str_replace('\\', '/', trim($referencia)));
        if ($nombre === '' || $nombre === '.' || $nombre === '..') return null;
        $root = dirname(__DIR__, 2) . '/public/uploads/archivos/';
        $real = realpath($root . $nombre);
        $base = realpath($root);
        return $real && $base && is_file($real) && str_starts_with($real, $base . DIRECTORY_SEPARATOR)
            ? $real
            : null;
    }

    private static function existeDocumentoAditivo(string $referencia): bool
    {
        return self::rutaDocumentoAditivo($referencia) !== null;
    }

    public static function getDocumentos(int $idReporte): array
    {
        $registros = SolicitudAditivoDocumento::where('id_reporte', $idReporte)
            ->orderBy('id', 'desc')
            ->get();

        $result = [];
        foreach ($registros as $doc) {
            $fecha = $doc->fecha;
            $fechaStr = '';
            if ($fecha instanceof \DateTimeInterface) {
                $fechaStr = formatearFecha($fecha) . ', ' . $fecha->format('g:i a');
            } elseif ($fecha !== null && trim((string)$fecha) !== '') {
                $fechaStr = formatearFecha($fecha);
            }

            $result[] = [
                'id'         => (int)$doc->id,
                'nombre'     => (string)$doc->nombre,
                'tipo_label' => self::DOCUMENTO_TIPOS[$doc->nombre] ?? (string)$doc->nombre,
                'documento'  => (string)$doc->documento,
                'archivo_disponible' => self::existeDocumentoAditivo((string)$doc->documento),
                'fecha'      => $fechaStr,
            ];
        }

        return $result;
    }

    public static function subirDocumento(int $idReporte, string $nombre, array $file): array
    {
        $solicitud = SolicitudAditivo::find($idReporte);
        if (!$solicitud) {
            return ['success' => false, 'message' => 'Solicitud no encontrada'];
        }

        $nombre = strtoupper(trim($nombre));
        if (!isset(self::DOCUMENTO_TIPOS[$nombre])) {
            return ['success' => false, 'message' => 'Tipo de documento no válido'];
        }

        if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Selecciona un archivo válido'];
        }

        if ((int)($file['size'] ?? 0) > 20 * 1024 * 1024) {
            return ['success' => false, 'message' => 'El archivo no puede superar los 20 MB'];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::DOCUMENTO_EXTENSIONES, true)) {
            return ['success' => false, 'message' => 'El tipo de archivo no está permitido'];
        }

        $uploadDir = dirname(__DIR__, 2) . '/public/uploads/archivos/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $docName = uniqid() . '-' . basename($file['name']);

        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $docName)) {
            return ['success' => false, 'message' => 'Error al guardar el archivo'];
        }

        SolicitudAditivoDocumento::create([
            'id_reporte' => $idReporte,
            'fecha'      => Carbon::now(),
            'nombre'     => $nombre,
            'documento'  => $docName,
        ]);

        return ['success' => true, 'message' => 'Documento subido correctamente'];
    }

    public static function eliminarDocumento(int $id): array
    {
        $doc = SolicitudAditivoDocumento::find($id);
        if (!$doc) {
            return ['success' => false, 'message' => 'Documento no encontrado'];
        }

        $uploadDir = dirname(__DIR__, 2) . '/public/uploads/archivos/';
        if ($doc->documento && file_exists($uploadDir . $doc->documento)) {
            unlink($uploadDir . $doc->documento);
        }

        $doc->delete();

        return ['success' => true, 'message' => 'Documento eliminado correctamente'];
    }

    /* ================= COMENTARIOS (op_solicitud_aditivo_comentario) ================= */

    public static function getComentarios(int $idReporte): array
    {
        $idUsuarioActual = (int)(Session::get('usuario')['id'] ?? 0);

        $result = [];
        foreach (SolicitudAditivoComentario::where('id_reporte', $idReporte)->orderBy('id', 'asc')->get() as $c) {
            $fechaFmt = '';
            if ($c->fecha_hora) {
                $fechaFmt = formatearFecha($c->fecha_hora->format('Y-m-d')) . ', ' . $c->fecha_hora->format('g:i a');
            }

            $result[] = [
                'id'             => (int)$c->id,
                'id_usuario'     => (int)$c->id_usuario,
                'usuario_nombre' => self::getPersonal((int)$c->id_usuario)['nombre'],
                'comentario'     => (string)$c->comentario,
                'fecha_hora'     => $fechaFmt,
                'esPropio'       => (int)$c->id_usuario === $idUsuarioActual,
            ];
        }

        return $result;
    }

    public static function addComentario(int $idReporte, string $comentarioTexto, int $idUsuario): array
    {
        $comentarioTexto = trim($comentarioTexto);
        if ($idReporte <= 0 || $comentarioTexto === '') {
            return ['success' => false, 'message' => 'El comentario es requerido.', 'code' => 422];
        }

        if (!SolicitudAditivo::find($idReporte)) {
            return ['success' => false, 'message' => 'Solicitud no encontrada'];
        }

        SolicitudAditivoComentario::create([
            'id_reporte' => $idReporte,
            'id_usuario' => (int)$idUsuario,
            'comentario' => $comentarioTexto,
            'fecha_hora' => date('Y-m-d H:i:s'),
        ]);

        return ['success' => true, 'message' => 'Comentario agregado correctamente', 'code' => 200];
    }

    /* ================= PDF ================= */

    public static function generarHtmlPdf(int $id): array
    {
        $solicitud = SolicitudAditivo::find($id);
        if (!$solicitud) {
            return ['success' => false, 'message' => 'Solicitud no encontrada'];
        }

        $personal = self::getPersonal($solicitud->id_personal);
        $razonSocial = self::getRazonSocialEstacion((int)$solicitud->id_estacion);
        $fecha = self::formatearFechaHora($solicitud->fecha);
        $para = (string)$solicitud->para;

        $fechaEntregaRaw = (string)$solicitud->getRawOriginal('fecha_entrega');
        $fechaEntregaTexto = self::fechaEntregaValida($fechaEntregaRaw) ? formatearFecha($fechaEntregaRaw) : 'Sin fecha asignada';

        $comentarios = trim((string)$solicitud->comentarios);

        $logo = $_ENV['APP_URL'] . '/assets/images/logos/Logo.png';

        $rows = '';
        $num = 1;
        $totalTambos = 0;
        $totalKilogramos = 0;
        foreach (SolicitudAditivoTambo::where('id_reporte', $id)->orderBy('id', 'asc')->get() as $tambo) {
            $totalTambos += (int)$tambo->cantidad;
            $totalKilogramos += (int)$tambo->cantidad * (int)$tambo->kilogramo;
            $rows .= '<tr>'
                . '<td class="text-center">' . $num . '</td>'
                . '<td class="text-center">' . htmlspecialchars((string)$tambo->producto, ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars((string)$tambo->aditivo, ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td class="text-center">' . number_format((int)$tambo->cantidad) . '</td>'
                . '<td class="text-center">' . number_format((int)$tambo->kilogramo) . '</td>'
                . '</tr>';
            $num++;
        }

        $firmaA = '';
        $firmaB = '';
        $firmaARow = SolicitudAditivoFirma::where('id_reporte', $id)->where('tipo_firma', 'A')->first();
        if ($firmaARow) {
            $nombreFirma = basename(str_replace('\\', '/', (string)$firmaARow->firma));
            $rutaFirma = self::rutaDocumentoAditivo((string)$firmaARow->firma);
            if ($nombreFirma !== '' && strpos($firmaARow->firma ?? '', 'Firma:') !== 0 && $rutaFirma !== null) {
                $dataFirma = file_get_contents($rutaFirma);
                $nombreFirmante = self::getPersonal($firmaARow->id_usuario)['nombre'];
                $firmaA = '<div class="text-center" style="margin-top: 10px;"><div>' . $nombreFirmante . '</div>'
                    . '<img src="data:image/png;base64,' . base64_encode($dataFirma) . '" style="width: 200px;"></div>'
                    . '<div style="font-size: 1em; font-weight: bold; text-align: center; border-top: 1px solid #dee2e6; padding-top: 10px;">NOMBRE Y FIRMA DEL ENCARGADO</div>';
            } else {
                $firmaA = '<div class="text-center" style="margin-top: 10px;"><div>' . htmlspecialchars(self::getPersonal((int)$firmaARow->id_usuario)['nombre'], ENT_QUOTES, 'UTF-8') . '</div>'
                    . '<div style="padding: 20px 0; color: #6c757d;">La firma está registrada, pero su imagen no está disponible.</div>'
                    . '<div style="font-size: 1em; font-weight: bold; text-align: center; border-top: 1px solid #dee2e6; padding-top: 10px;">NOMBRE Y FIRMA DEL ENCARGADO</div></div>';
            }
        } else {
            $firmaA = '<div class="text-center" style="margin-top: 10px;"><div style="padding: 20px 0; color: #6c757d;">Sin firma registrada.</div><div style="font-size: 1em; font-weight: bold; text-align: center; border-top: 1px solid #dee2e6; padding-top: 10px;">NOMBRE Y FIRMA DEL ENCARGADO</div></div>';
        }

        $firmaBRow = SolicitudAditivoFirma::where('id_reporte', $id)->where('tipo_firma', 'B')->first();
        if ($firmaBRow) {
            $fechaFirmaB = self::formatearFechaHora($firmaBRow->fecha);
            $nombreFirmanteB = self::getPersonal($firmaBRow->id_usuario)['nombre'];
            $firmaB = '<div class="text-center" style="margin-top: 10px;"><div>' . $nombreFirmanteB . '</div>'
                . '<div class="border-bottom text-center p-2" style="margin-top: 10px;"><small><b>Fecha: ' . $fechaFirmaB . '</b><br>La solicitud de aditivo se firmó por un medio electrónico.</small></div>'
                . '<div style="font-size: 1em; font-weight: bold; text-align: center; border-top: 1px solid #dee2e6; padding-top: 10px;">NOMBRE Y FIRMA DE VOBO</div></div>';
        } else {
            $firmaB = '<div class="text-center" style="margin-top: 10px;"><div style="padding: 20px 0; color: #6c757d;">¡Falta la firma de Vo.Bo.!</div><div style="font-size: 1em; font-weight: bold; text-align: center; border-top: 1px solid #dee2e6; padding-top: 10px;">NOMBRE Y FIRMA DE VOBO</div></div>';
        }

        $comentariosHtml = $comentarios !== ''
            ? '<table style="margin-top:10px;"><tbody>'
                . '<tr><td><b>Comentarios</b></td></tr>'
                . '<tr><td>' . htmlspecialchars($comentarios, ENT_QUOTES, 'UTF-8') . '</td></tr>'
                . '</tbody></table>'
            : '';

        $html = '<html lang="es"><head><style type="text/css">
@page { margin: 0.5cm 0.5cm; }
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: .9rem; color: #212529; }
table { border-collapse: collapse; width: 100%; }
th, td { border: 1px solid #dee2e6; padding: 3px; vertical-align: middle; }
th { background: #F2F2F2; font-weight: bold; }
.text-center { text-align: center !important; }
.text-right { text-align: right !important; }
.border-bottom { border-bottom: 1px solid #dee2e6; }
.p-2 { padding: 0.5rem !important; }
</style></head><body>'
            . '<img src="' . $logo . '" width="180">'
            . '<div class="text-center" style="font-size: 1.8em;">Solicitud de Aditivo</div>'
            . '<div class="text-center" style="font-size: 1.2em; margin-top:10px; margin-bottom:10px;">' . htmlspecialchars($razonSocial, ENT_QUOTES, 'UTF-8') . '</div>'
            . '<table><tbody>'
            . '<tr><td>#</td><td class="text-center">' . htmlspecialchars((string)$solicitud->id, ENT_QUOTES, 'UTF-8') . '</td><td><b>Personal</b></td><td><b>Fecha</b></td></tr>'
            . '<tr><td>Orden de compra</td><td class="text-center">' . number_format((int)$solicitud->orden_compra) . '</td><td>' . htmlspecialchars($personal['nombre'], ENT_QUOTES, 'UTF-8') . '</td><td>' . $fecha . '</td></tr>'
            . '<tr><td>Para</td><td colspan="3">' . htmlspecialchars($para, ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '<tr><td>Fecha de entrega</td><td colspan="3">' . $fechaEntregaTexto . '</td></tr>'
            . '</tbody></table>'
            . '<table style="margin-top:10px;"><thead><tr>'
            . '<td class="text-center"><b>#</b></td><td class="text-center"><b>Producto</b></td><td><b>Aditivo</b></td><td class="text-center"><b>Cantidad de tambos</b></td><td class="text-center"><b>Kilogramo</b></td>'
            . '</tr></thead><tbody>' . $rows
            . '<tr><td colspan="3" class="text-right">Total de tambos:</td><td class="text-center"><b>' . number_format($totalTambos) . '</b></td><td class="text-center"><b>' . number_format($totalKilogramos) . '</b></td></tr>'
            . '</tbody></table>'
            . $comentariosHtml
            . '<table style="width: 100%; margin-top: 20px;"><tr><td class="p-2">' . $firmaA . '</td><td class="p-2">' . $firmaB . '</td></tr></table>'
            . '</body></html>';

        return [
            'success' => true,
            'html'    => $html,
            'nombre'  => 'Solicitud de Aditivo ' . $razonSocial . '.pdf',
        ];
    }

    /* ================= NOTIFICACIONES TELEGRAM ================= */

    private static function notificarFinalizarSolicitud(int $id, int $idUsuario): void
    {
        try {
            $solicitud = SolicitudAditivo::find($id);
            if (!$solicitud) return;

            $idEstacion = (int)$solicitud->id_estacion;
            $fecha = self::formatearFechaHora($solicitud->fecha);

            $nombreES = self::getNombreEstacion($idEstacion);
            $nombreUsuario = self::getPersonal($idUsuario)['nombre'];

            $paraEstacion = '✅ ' . $nombreUsuario . ' finalizó su Solicitud de Aditivo en el apartado de Comercializadora.' . PHP_EOL
                . '🗓 Fecha: ' . $fecha
                . PHP_EOL . PHP_EOL . '⛽ Estación: ' . $nombreES . '.'
                . PHP_EOL . 'Nota: La solicitud está en espera de obtener la firma de Autorización.';

            $paraCarmen = '✅ ' . $nombreUsuario . ' finalizó su Solicitud de Aditivo en el apartado de Comercializadora.' . PHP_EOL
                . '🗓 Fecha: ' . $fecha
                . PHP_EOL . PHP_EOL . '⛽ Estación: ' . $nombreES . '.'
                . PHP_EOL . 'Nota: Debes firmar la Autorización de dicha solicitud.';

            $telegram = new TelegramService();
            $telegram->sendToken(self::FIRMANTE_VOBO, $paraCarmen);

            $userIds = $telegram->getUserIdsByStation($idEstacion, $idUsuario);
            if (!empty($userIds)) {
                $telegram->sendMessageToMultiple($userIds, $paraEstacion);
            }

            if ($idEstacion == 6 || $idEstacion == 7) {
                $comercializadora = $telegram->getUserIdsComercializadora($idUsuario);
                if (!empty($comercializadora)) {
                    $telegram->sendMessageToMultiple($comercializadora, $paraEstacion);
                }
            }

            $mantenimiento = $telegram->getUserIdsMantenimiento($idUsuario);
            if (!empty($mantenimiento)) {
                $telegram->sendMessageToMultiple($mantenimiento, $paraEstacion);
            }
        } catch (\Throwable $e) {
            error_log('Error Telegram finalizar solicitud aditivo: ' . $e->getMessage());
        }
    }

    private static function notificarEliminarSolicitud(int $id, int $idEstacion, $fecha, int $idUsuario): void
    {
        try {
            $fecha_hora = $fecha ? self::formatearFechaHora($fecha) : '';

            $nombreES = self::getNombreEstacion($idEstacion);
            $nombreUsuario = self::getPersonal($idUsuario)['nombre'];

            $detalle = '🗑 ' . $nombreUsuario . ' eliminó la Solicitud de Aditivo en el apartado de Comercializadora.' . PHP_EOL
                . '🗓 Fecha: ' . $fecha_hora
                . PHP_EOL . PHP_EOL . '⛽ Estación: ' . $nombreES . '.';

            $telegram = new TelegramService();

            $userIds = $telegram->getUserIdsByStation($idEstacion, $idUsuario);
            if (!empty($userIds)) {
                $telegram->sendMessageToMultiple($userIds, $detalle);
            }

            if ($idEstacion == 6 || $idEstacion == 7) {
                $comercializadora = $telegram->getUserIdsComercializadora($idUsuario);
                if (!empty($comercializadora)) {
                    $telegram->sendMessageToMultiple($comercializadora, $detalle);
                }
            }

            $mantenimiento = $telegram->getUserIdsMantenimiento($idUsuario);
            if (!empty($mantenimiento)) {
                $telegram->sendMessageToMultiple($mantenimiento, $detalle);
            }
        } catch (\Throwable $e) {
            error_log('Error Telegram eliminar solicitud aditivo: ' . $e->getMessage());
        }
    }

    private static function notificarFirmarVoBo(int $id, int $idUsuario): void
    {
        try {
            $solicitud = SolicitudAditivo::find($id);
            if (!$solicitud) return;

            $idEstacion = (int)$solicitud->id_estacion;
            $fecha = self::formatearFechaHora($solicitud->fecha);
            $nombreES = self::getNombreEstacion($idEstacion);
            $nombreUsuario = self::getPersonal($idUsuario)['nombre'];

            $detalle = '✍🏻 ' . $nombreUsuario . ' firmó el <b>VOBO</b> de la Solicitud de Aditivo de No. ' . $id . ' correspondiente al dia ' . $fecha
                . PHP_EOL . PHP_EOL . '⛽ Estación: ' . $nombreES . '.';

            $telegram = new TelegramService();

            $userIds = $telegram->getUserIdsByStation($idEstacion, $idUsuario);
            if (!empty($userIds)) {
                $telegram->sendMessageToMultiple($userIds, $detalle);
            }

            if ($idEstacion == 6 || $idEstacion == 7) {
                $comercializadora = $telegram->getUserIdsComercializadora($idUsuario);
                if (!empty($comercializadora)) {
                    $telegram->sendMessageToMultiple($comercializadora, $detalle);
                }
            }

            $mantenimiento = $telegram->getUserIdsMantenimiento($idUsuario);
            if (!empty($mantenimiento)) {
                $telegram->sendMessageToMultiple($mantenimiento, $detalle);
            }
        } catch (\Throwable $e) {
            error_log('Error Telegram firmar VoBo solicitud aditivo: ' . $e->getMessage());
        }
    }
}
