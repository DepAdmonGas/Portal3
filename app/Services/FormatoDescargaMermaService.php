<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Operativo\DescargaTuxpa;
use App\Models\Operativo\DescargaTuxpaFirma;
use App\Models\Operativo\DescargaTuxpanComentario;
use App\Models\Estacion;
use App\Models\Usuario;
use App\Services\ModuloDptoOperativoService;
use App\Services\ModuleStationService;
use App\Services\TelegramService;
use Illuminate\Database\Capsule\Manager as Capsule;

class FormatoDescargaMermaService
{
    public const MODULE_KEY = 'formato-descarga-merma';

    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];

    public const IDS_ESTACIONES = [1, 2, 3, 4, 5, 6, 7, 14];

    public const FILE_FIELDS = [
        'no_factura'        => ['campo' => 'no_factura',        'prefijo' => 'no_factura'],
        'inventario_inicial'=> ['campo' => 'inventario_inicial','prefijo' => 'inventario_inicial'],
        'nice'              => ['campo' => 'nice',              'prefijo' => 'nice'],
        'inventario_final'  => ['campo' => 'inventario_final',  'prefijo' => 'inventario_final'],
        'metro_contador'    => ['campo' => 'metro_contador',    'prefijo' => 'metro_contador'],
        'metro_contador20'  => ['campo' => 'metro_contador20',  'prefijo' => 'metro_contador20'],
    ];

    public const UPLOAD_DIR = '/uploads/archivos/formato-descarga-merma/';
    public const FIRMA_DIR  = '/uploads/archivos/formato-descarga-merma-firmas/';

    public static function getUploadPath(): string
    {
        return dirname(__DIR__, 2) . '/public' . self::UPLOAD_DIR;
    }

    public static function getFirmaPath(): string
    {
        return dirname(__DIR__, 2) . '/public' . self::FIRMA_DIR;
    }

    public static function getPermisos(): array
    {
        $usuario = Auth::user();
        $sessionUsuario = Session::get('usuario');
        $idUsuario = (int)($sessionUsuario['id'] ?? 0);
        $idEstacion = (int)($sessionUsuario['id_estacion'] ?? 0);
        $multiestacion = !empty($sessionUsuario['multiestacion']);
        $nombrePuesto = $usuario->puesto->tipo_puesto ?? '';

        $allPerms = ModuloDptoOperativoService::getPermisos($idUsuario, 'importacion');
        $permisosDb = $allPerms['importacion'] ?? [];

        $tieneSubmenu = false;
        foreach ($permisosDb['submenus'] ?? [] as $sm) {
            if (($sm['clave'] ?? '') === self::MODULE_KEY) {
                $tieneSubmenu = true;
                break;
            }
        }

        $leer = !empty($permisosDb['leer']);
        $crear = !empty($permisosDb['crear']);
        $editar = !empty($permisosDb['editar']);
        $eliminar = !empty($permisosDb['eliminar']);
        $descargar = !empty($permisosDb['descargar']);

        return [
            'id_usuario'    => $idUsuario,
            'id_estacion'   => $idEstacion,
            'nombre_puesto' => $nombrePuesto,
            'multiestacion' => $multiestacion,
            'puedeVer'      => $tieneSubmenu && $leer,
            'puedeCrear'    => $tieneSubmenu && $crear && !$multiestacion,
            'puedeEditar'   => $tieneSubmenu && $editar && !$multiestacion,
            'puedeEliminar' => $tieneSubmenu && $eliminar && !$multiestacion,
            'puedeDescargar'=> $tieneSubmenu && $descargar,
        ];
    }

    public static function getEstacionesPermitidas(): array
    {
        $stations = ModuleStationService::getAvailableStations(self::MODULE_KEY);
        return array_values(array_column($stations, 'id'));
    }

    public static function puedeEstacion(int $idEstacion): bool
    {
        return in_array($idEstacion, self::getEstacionesPermitidas(), true);
    }

    public static function getData(?int $filtroYear = null, ?int $filtroMes = null): array
    {
        $ctx = ModuleStationService::getContext(self::MODULE_KEY);
        $idEstacion = $ctx['id_estacion'] !== null ? (int)$ctx['id_estacion'] : null;

        $query = DescargaTuxpa::query();

        if ($idEstacion !== null) {
            if (!self::puedeEstacion($idEstacion)) {
                return [];
            }
            $query->where('id_estacion', $idEstacion);
        } else {
            $allowed = self::getEstacionesPermitidas();
            if (empty($allowed)) {
                return [];
            }
            $query->whereIn('id_estacion', $allowed);
        }

        if ($filtroYear && $filtroMes) {
            $query->whereYear('fecha_llegada', $filtroYear)
                  ->whereMonth('fecha_llegada', $filtroMes);
        }

        $records = $query->orderByDesc('folio')->get();

        $estaciones = Estacion::pluck('nombre', 'id')->toArray();

        $rows = [];
        foreach ($records as $r) {
            $comentarioCount = DescargaTuxpanComentario::where('id_descarga', $r->id)->count();
            $responsable = '';
            if ($r->id_usuario) {
                $u = Usuario::find($r->id_usuario);
                $responsable = $u ? $u->nombre : 'S/I';
            }

            $rows[] = [
                'id'           => (int)$r->id,
                'folio'        => str_pad((string)$r->folio, 3, '0', STR_PAD_LEFT),
                'fecha_hora'   => formatearFecha($r->fecha_llegada) . ', ' . date('g:i a', strtotime($r->hora_llegada)),
                'responsable'  => $responsable,
                'producto'     => (string)$r->producto,
                'estacion'     => $estaciones[(int)$r->id_estacion] ?? 'S/I',
                'id_estacion'  => (int)$r->id_estacion,
                'num_comentarios'  => $comentarioCount,
            ];
        }

        return $rows;
    }

    public static function getDataBusqueda(int $idEstacion, int $year, int $mes): array
    {
        if (!self::puedeEstacion($idEstacion)) {
            return [];
        }

        $records = DescargaTuxpa::where('id_estacion', $idEstacion)
            ->whereYear('fechahora', $year)
            ->whereMonth('fechahora', $mes)
            ->orderByDesc('folio')
            ->get();

        $estaciones = Estacion::pluck('nombre', 'id')->toArray();

        $rows = [];
        foreach ($records as $r) {
            $responsable = '';
            if ($r->id_usuario) {
                $u = Usuario::find($r->id_usuario);
                $responsable = $u ? $u->nombre : 'S/I';
            }

            $merma = (float)$r->litros - (float)$r->cuenta_litros;
            $tolerancia = round((float)$r->litros * 0.55 / 100, 2);
            $nc = round($merma - $tolerancia, 2);
            $importeNc = round($nc * (float)$r->precio_litro, 2);

            $rows[] = [
                'id'                 => (int)$r->id,
                'folio'              => str_pad((string)$r->folio, 3, '0', STR_PAD_LEFT),
                'estacion'           => $estaciones[(int)$r->id_estacion] ?? 'S/I',
                'fecha_llegada'   => formatearFecha($r->fecha_llegada) . ', ' . date('g:i a', strtotime($r->hora_llegada)),
                'producto'           => (string)$r->producto,
                'no_factura_remision'=> (string)$r->no_factura_remision,
                'litros'             => number_format((float)$r->litros, 2, '.', ','),
                'precio_litro'       => number_format((float)$r->precio_litro, 2, '.', ','),
                'cuenta_litros'      => number_format((float)$r->cuenta_litros, 2, '.', ','),
                'tolerancia'         => number_format($tolerancia, 2, '.', ','),
                'merma'              => number_format($merma, 2, '.', ','),
                'nc'                 => number_format($nc, 2, '.', ','),
                'importe_nc'         => number_format($importeNc, 2, '.', ','),
                'unidad'             => (string)$r->unidad,
                'operador'           => (string)$r->operador,
                'transportista'      => (string)$r->transportista,
                'sellos'             => (string)$r->sellos,
                'detuvo_venta'       => (string)$r->detuvo_venta,
            ];
        }

        return $rows;
    }

    public static function getDataBusquedaGeneral(int $year, int $mes): array
    {
        $allowed = self::getEstacionesPermitidas();
        $idsBusqueda = array_intersect(self::IDS_ESTACIONES, $allowed);

        $rows = [];
        foreach ($idsBusqueda as $idEstacion) {
            $estacionRows = self::getDataBusqueda($idEstacion, $year, $mes);
            $nombreEstacion = '';
            $est = Estacion::find($idEstacion);
            if ($est) {
                $nombreEstacion = $est->nombre;
            }
            $rows[] = [
                'estacion_id' => $idEstacion,
                'estacion_nombre' => $nombreEstacion,
                'registros' => $estacionRows,
            ];
        }

        return $rows;
    }

    public static function getRegistro(int $id): ?array
    {
        $r = DescargaTuxpa::find($id);
        if (!$r) {
            return null;
        }

        $estacion = Estacion::find($r->id_estacion);
        $responsable = '';
        if ($r->id_usuario) {
            $u = Usuario::find($r->id_usuario);
            $responsable = $u ? $u->nombre : 'S/I';
        }

        $litros = (float)$r->litros;
        $cuentaLitros = (float)$r->cuenta_litros;
        $precioLitro = (float)$r->precio_litro;
        $merma = $litros - $cuentaLitros;
        $tolerancia = round($litros * 0.55 / 100, 2);
        $nc = round($merma - $tolerancia, 2);
        $importeNc = round($nc * $precioLitro, 2);

        $firmas = DescargaTuxpaFirma::where('id_descarga', $id)->get()->pluck('imagen_firma', 'tipo_firma')->toArray();

        return [
            'id'                 => (int)$r->id,
            'folio'              => str_pad((string)$r->folio, 3, '0', STR_PAD_LEFT),
            'id_estacion'        => (int)$r->id_estacion,
            'estacion'           => $estacion ? $estacion->nombre : 'S/I',
            'responsable'        => $responsable,
            'id_usuario'         => (int)$r->id_usuario,
            'fecha_llegada'      => $r->fecha_llegada ? $r->fecha_llegada->format('Y-m-d') : '',
            'hora_llegada'       => $r->hora_llegada ? (string)$r->hora_llegada : '',
            'fecha_hora_llegada'   => formatearFecha($r->fecha_llegada) . ', ' . date('g:i a', strtotime($r->hora_llegada)),
            'fechahora'   => formatearFecha($r->fechahora) . ', ' . date('g:i a', strtotime($r->fechahora)),
            'producto'           => (string)$r->producto,
            'no_factura'         => (string)$r->no_factura,
            'no_factura_remision'=> (string)$r->no_factura_remision,
            'sellos'             => (string)$r->sellos,
            'detuvo_venta'       => (string)$r->detuvo_venta,
            'inventario_inicial' => (string)$r->inventario_inicial,
            'nice'               => (string)$r->nice,
            'inventario_final'   => (string)$r->inventario_final,
            'metro_contador'     => (string)$r->metro_contador,
            'metro_contador20'   => (string)$r->metro_contador20,
            'merma'              => number_format($merma, 2, '.', ','),
            'merma_raw'          => $merma,
            'operador'           => (string)$r->operador,
            'transportista'      => (string)$r->transportista,
            'litros'             => number_format($litros, 2, '.', ','),
            'litros_raw'         => $litros,
            'precio_litro'       => number_format($precioLitro, 2, '.', ','),
            'precio_litro_raw'   => $precioLitro,
            'unidad'             => (string)$r->unidad,
            'cuenta_litros'      => number_format($cuentaLitros, 2, '.', ','),
            'cuenta_litros_raw'  => $cuentaLitros,
            'tolerancia'         => number_format($tolerancia, 2, '.', ','),
            'tolerancia_raw'     => $tolerancia,
            'nc'                 => number_format($nc, 2, '.', ','),
            'nc_raw'             => $nc,
            'importe_nc'         => number_format($importeNc, 2, '.', ','),
            'importe_nc_raw'     => $importeNc,
            'firmas'             => $firmas,
        ];
    }

    public static function folio(int $idEstacion): int
    {
        $last = DescargaTuxpa::where('id_estacion', $idEstacion)
            ->orderByDesc('folio')
            ->value('folio');

        return $last ? ((int)$last + 1) : 1;
    }

    public static function crear(array $formData, array $archivos, string $firmaEncargado, string $firmaOperador, int $idUsuario, int $idEstacion): int
    {
        $uploadPath = self::getUploadPath();
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        $firmaPath = self::getFirmaPath();
        if (!is_dir($firmaPath)) {
            mkdir($firmaPath, 0755, true);
        }

        $Folio = self::folio($idEstacion);

        $noFactura = self::fileUpload($archivos['no_factura'] ?? null, $uploadPath, 'no_factura');
        $inventarioInicial = self::fileUpload($archivos['inventario_inicial'] ?? null, $uploadPath, 'inventario_inicial');
        $nice = self::fileUpload($archivos['nice'] ?? null, $uploadPath, 'nice');
        $inventarioFinal = self::fileUpload($archivos['inventario_final'] ?? null, $uploadPath, 'inventario_final');
        $metroContador = self::fileUpload($archivos['metro_contador'] ?? null, $uploadPath, 'metro_contador');
        $metroContador20 = self::fileUpload($archivos['metro_contador20'] ?? null, $uploadPath, 'metro_contador20');

        $record = DescargaTuxpa::create([
            'folio'              => $Folio,
            'id_estacion'        => $idEstacion,
            'id_usuario'         => $idUsuario,
            'fecha_llegada'      => $formData['fecha_llegada'] ?? null,
            'hora_llegada'       => $formData['hora_llegada'] ?? null,
            'producto'           => $formData['producto'] ?? '',
            'no_factura'         => $noFactura,
            'sellos'             => $formData['sellos'] ?? 'No',
            'inventario_inicial' => $inventarioInicial,
            'nice'               => $nice,
            'detuvo_venta'       => $formData['detuvo_venta'] ?? 'No',
            'inventario_final'   => $inventarioFinal,
            'metro_contador'     => $metroContador,
            'metro_contador20'   => $metroContador20,
            'merma'              => (float)($formData['merma'] ?? 0),
            'operador'           => $formData['operador'] ?? '',
            'transportista'      => $formData['transportista'] ?? '',
            'no_factura_remision'=> $formData['no_factura_remision'] ?? '',
            'litros'             => (float)($formData['litros'] ?? 0),
            'precio_litro'       => (float)($formData['precio_litro'] ?? 0),
            'unidad'             => $formData['unidad'] ?? '',
            'cuenta_litros'      => (float)($formData['cuenta_litros'] ?? 0),
        ]);

        $idPrincipal = (int)$record->id;

        if (!empty($firmaEncargado)) {
            self::guardarFirma($idPrincipal, 'Encargado de estación', $firmaEncargado, $firmaPath);
        }
        if (!empty($firmaOperador)) {
            self::guardarFirma($idPrincipal, 'Operador', $firmaOperador, $firmaPath);
        }

        self::notificarCrear($idPrincipal, $Folio, $idUsuario, $idEstacion);

        return $idPrincipal;
    }

    public static function editar(int $id, array $formData, array $archivos, int $idUsuario, int $idEstacion, string $firmaEncargado = '', string $firmaOperador = ''): bool
    {
        $registro = DescargaTuxpa::find($id);
        if (!$registro) {
            return false;
        }

        $uploadPath = self::getUploadPath();
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        $fileUpdates = [];
        foreach (self::FILE_FIELDS as $key => $conf) {
            if (!empty($archivos[$key]) && isset($archivos[$key]['name']) && $archivos[$key]['name'] !== '') {
                $uploaded = self::fileUpload($archivos[$key], $uploadPath, $conf['prefijo']);
                if ($uploaded !== '') {
                    $fileUpdates[$conf['campo']] = $uploaded;
                }
            }
        }

        $updateData = array_merge([
            'fecha_llegada'       => $formData['fecha_llegada'] ?? $registro->fecha_llegada,
            'hora_llegada'        => $formData['hora_llegada'] ?? $registro->hora_llegada,
            'producto'            => $formData['producto'] ?? $registro->producto,
            'sellos'              => $formData['sellos'] ?? $registro->sellos,
            'detuvo_venta'        => $formData['detuvo_venta'] ?? $registro->detuvo_venta,
            'merma'               => (float)($formData['merma'] ?? $registro->merma),
            'operador'            => $formData['operador'] ?? $registro->operador,
            'transportista'       => $formData['transportista'] ?? $registro->transportista,
            'no_factura_remision' => $formData['no_factura_remision'] ?? $registro->no_factura_remision,
            'litros'              => (float)($formData['litros'] ?? $registro->litros),
            'precio_litro'        => (float)($formData['precio_litro'] ?? $registro->precio_litro),
            'unidad'              => $formData['unidad'] ?? $registro->unidad,
            'cuenta_litros'       => (float)($formData['cuenta_litros'] ?? $registro->cuenta_litros),
        ], $fileUpdates);

        $registro->update($updateData);

        $firmaPath = self::getFirmaPath();
        if (!is_dir($firmaPath)) {
            mkdir($firmaPath, 0755, true);
        }
        if ($firmaEncargado !== '') {
            self::guardarFirma($id, 'Encargado de estación', $firmaEncargado, $firmaPath);
        }
        if ($firmaOperador !== '') {
            self::guardarFirma($id, 'Operador', $firmaOperador, $firmaPath);
        }

        self::notificarEditar($id, $registro->folio, $idUsuario, $idEstacion);

        return true;
    }

    public static function eliminar(int $id, int $idUsuario): bool
    {
        $registro = DescargaTuxpa::find($id);
        if (!$registro) {
            return false;
        }

        $idEstacion = (int)$registro->id_estacion;
        $folio = (int)$registro->folio;

        Capsule::transaction(function () use ($id, $registro) {
            DescargaTuxpaFirma::where('id_descarga', $id)->delete();
            DescargaTuxpanComentario::where('id_descarga', $id)->delete();
            $registro->delete();
        });

        self::notificarEliminar($folio, $idUsuario, $idEstacion);

        return true;
    }

    public static function getComentarios(int $idDescarga): array
    {
        $comentarios = DescargaTuxpanComentario::where('id_descarga', $idDescarga)
            ->orderBy('fecha_hora', 'asc')
            ->get();

        $usuario = Session::get('usuario');
        $currentUserId = is_array($usuario) ? ($usuario['id'] ?? 0) : 0;

        $rows = [];
        foreach ($comentarios as $c) {
            $u = Usuario::find($c->id_usuario);
            $fechaHoraStr = (string)($c->fecha_hora ?? '');
            $fechaHoraDisplay = '-';
            if ($fechaHoraStr !== '' && $fechaHoraStr !== '0000-00-00 00:00:00') {
                $fechaFmt = formatearFecha($fechaHoraStr);
                if ($fechaFmt !== '') {
                    $fechaHoraDisplay = $fechaFmt . ', ' . date('g:i a', strtotime($fechaHoraStr));
                }
            }
            $rows[] = [
                'id'             => (int)$c->id,
                'usuario_nombre' => $u ? $u->nombre : 'Sistema',
                'comentario'     => (string)$c->comentario,
                'fecha_hora'     => $fechaHoraDisplay,
                'esPropio'       => ((int)$c->id_usuario === (int)$currentUserId),
            ];
        }

        return $rows;
    }

    public static function getComentarioCount(int $idDescarga): int
    {
        return DescargaTuxpanComentario::where('id_descarga', $idDescarga)->count();
    }

    public static function agregarComentario(int $idDescarga, int $idUsuario, string $comentario): bool
    {
        $registro = DescargaTuxpa::find($idDescarga);
        if (!$registro) {
            return false;
        }

        DescargaTuxpanComentario::create([
            'id_descarga' => $idDescarga,
            'id_usuario'  => $idUsuario,
            'comentario'  => $comentario,
        ]);

        self::notificarComentario($idDescarga, $registro->folio, $idUsuario, (int)$registro->id_estacion);

        return true;
    }

    private static function fileUpload(?array $file, string $uploadPath, string $fieldName): string
    {
        if (empty($file) || empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return '';
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return '';
        }

        $fileName = uniqid() . '-' . $fieldName . '.' . $extension;
        $destino = $uploadPath . $fileName;

        if (move_uploaded_file($file['tmp_name'], $destino)) {
            return $fileName;
        }

        return '';
    }

    private static function guardarFirma(int $idDescarga, string $tipoFirma, string $base64Data, string $firmaPath): void
    {
        $clean = preg_replace('#^data:image/\w+;base64,#i', '', $base64Data);
        $decoded = base64_decode($clean, true);
        if ($decoded === false) {
            return;
        }

        $fileName = uniqid() . '.png';
        if (file_put_contents($firmaPath . $fileName, $decoded)) {
            DescargaTuxpaFirma::create([
                'id_descarga'  => $idDescarga,
                'tipo_firma'   => $tipoFirma,
                'imagen_firma' => $fileName,
            ]);
        }
    }

    public static function getFileUrl(?string $fileName, string $tipo): string
    {
        if (empty($fileName)) {
            return '';
        }

        return '/download?tipo=' . $tipo . '&file=' . urlencode($fileName) . '&view=1';
    }

    public static function getAdjuntoUrl(?string $fileName): string
    {
        return self::getFileUrl($fileName, 'formato-descarga-merma');
    }

    public static function getFirmaUrl(?string $fileName): string
    {
        return self::getFileUrl($fileName, 'formato-descarga-merma-firma');
    }

    // ===================== NOTIFICACIONES TELEGRAM =====================

    private static function notificarCrear(int $idDescarga, int $folio, int $idUsuario, int $idEstacion): void
    {
        $usuario = Usuario::find($idUsuario);
        $estacion = Estacion::find($idEstacion);
        $nombreUsuario = $usuario ? $usuario->nombre : 'Usuario';
        $nombreEstacion = $estacion ? $estacion->nombre : 'S/I';

        $fecha = date('d/m/Y');
        $hora = date('h:i A');

$mensaje = '📄 Se ha agregado un nuevo registro en el Formato de Descarga de Merma:' . PHP_EOL . PHP_EOL .
    '#️⃣ Folio: 00' . $folio . PHP_EOL .
    '🗓 Fecha: ' . $fecha . PHP_EOL .
    '🕛 Hora: ' . $hora . PHP_EOL . PHP_EOL .
    '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL .
    '⛽ <b>Estación:</b> ' . $nombreEstacion . '.';

        TelegramService::notificar($idEstacion, $idUsuario, $mensaje);
    }

    private static function notificarEditar(int $idDescarga, int $folio, int $idUsuario, int $idEstacion): void
    {
     $usuario = Usuario::find($idUsuario);
        $estacion = Estacion::find($idEstacion);
        $nombreUsuario = $usuario ? $usuario->nombre : 'Usuario';
        $nombreEstacion = $estacion ? $estacion->nombre : 'S/I';

        $registro = DescargaTuxpa::find($idDescarga);
        $fechaLlegada = $registro && $registro->fecha_llegada ? formatearFecha($registro->fecha_llegada) : 'S/I';

        $mensaje = '✏️ ' . $nombreUsuario . ' editó el formato con folio 00' . $folio .
            ' el día ' . $fechaLlegada . ' en Formato de descarga de la estación ' . $nombreEstacion . '.';

        TelegramService::notificar($idEstacion, $idUsuario, $mensaje);
    }

    private static function notificarEliminar(int $folio, int $idUsuario, int $idEstacion): void
    {
        $usuario = Usuario::find($idUsuario);
        $estacion = Estacion::find($idEstacion);
        $nombreUsuario = $usuario ? $usuario->nombre : 'Usuario';
        $nombreEstacion = $estacion ? $estacion->nombre : 'S/I';

        $fecha = date('d/m/Y');

$mensaje = '🗑️ Se ha eliminado un registro en el Formato de Descarga:' . PHP_EOL . PHP_EOL .
    '🗓 Fecha de eliminación: ' . $fecha . PHP_EOL . PHP_EOL .
    '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL .
    '⛽ <b>Estación:</b> ' . $nombreEstacion . '.';

        TelegramService::notificar($idEstacion, $idUsuario, $mensaje);
    }

    private static function notificarComentario(int $idDescarga, int $folio, int $idUsuario, int $idEstacion): void
    {
        $usuario = Usuario::find($idUsuario);
        $estacion = Estacion::find($idEstacion);
        $nombreUsuario = $usuario ? $usuario->nombre : 'Usuario';
        $nombreEstacion = $estacion ? $estacion->nombre : 'S/I';

        $fecha = date('d/m/Y');
        $hora = date('h:i A');

$mensaje = '💬 Se ha agregado un nuevo comentario en el Formato de Descarga de Merma:' . PHP_EOL . PHP_EOL .
    '#️⃣ Folio: 00' . $folio . PHP_EOL .
    '🗓 Fecha: ' . $fecha . PHP_EOL .
    '🕛 Hora: ' . $hora . PHP_EOL . PHP_EOL .
    '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL .
    '⛽ <b>Estación:</b> ' . $nombreEstacion . '.';

        TelegramService::notificar($idEstacion, $idUsuario, $mensaje);
    }
}
