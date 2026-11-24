<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ModuloService;
use App\Services\ModuleStationService;
use App\Models\Estacion;
use App\Models\Sasisopa\CambioPrecio;
use App\Models\Sasisopa\Dispensario;
use App\Models\Sasisopa\DispensarioAperturaBitacora;

use Illuminate\Database\Capsule\Manager as Capsule;

class CambioPrecioController extends BaseController{
    protected string $modulo = 'sasisopa';

    private function estacionModulo(): ?int
    {
        return ModuleStationService::getContext('sasisopa')['id_estacion'] ?? null;
    }

    public function index()
    {

    $title = 'CAMBIO PRECIO';
         // Buscar permisos de los modulos
        $permisos = ModuloService::permisosSesion($this->modulo);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('SASISOPA', '/sasisopa');        
        Breadcrumb::add($title, '');

        $estacionId = $this->estacionModulo();

        $productoTres = $estacionId !== null
            ? (bool) (Estacion::find($estacionId)?->producto_tres)
            : true;

         $data = [
            'title' => $title,
            'permisos' => $permisos,
            'modulo' => $this->modulo,
            'filtro_usuario' => $this->filtro_usuario,
            'estacionId' => $estacionId,
            'moduleStationKey' => 'sasisopa',
            'productoTres' => $productoTres,
             'links' =>[
                '/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
            ],
            'scripts' => [
                '/js/vendor.min.js',
                '/js/core/module-station-selector.js?v=' . time(),
                '/libs/datatables.net/js/jquery.dataTables.min.js',
                '/js/cambioprecio/index.datatable.init.js?v=' . time(),
                '/js/cambioprecio/index.actions.init.js?v=' . time(),
            ],
            'help' => true
        ];
        
        View::render('cambioprecio/index', $data,'sasisopa');

    }

    public function datetable(): void
{
    header('Content-Type: application/json; charset=utf-8');

    try {

        $permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');
        $permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');

        $rows = CambioPrecio::query()
            ->where('id_estacion', $this->estacionModulo())
            ->orderByDesc('id')
            ->get();

        $data = [];

        foreach ($rows as $row) {

            $estado = [
                "titulo"     => "",
                "color_css"  => "",
                "color_hexa" => "",
                "estatus"    => false
            ];

            if ($row->estado == 0) {

                    $estado = [
                        'icon' => 'ti-alert-triangle',
                        'color_css' => 'text-warning'
                    ];

                } else {

                    $estado = [
                        'icon' => 'ti-check',
                        'color_css' => 'text-success'
                    ];

                }

            $data[] = [

                "id" => $row->id,
                "fecha" => formatearFecha($row->fecha->format('Y-m-d')),
                "hora" => $row->hora,
                "gsuper" => $row->gsuper,
                "gpremium" => $row->gpremium,
                "gdiesel" => $row->gdiesel,
                "estado" => $estado

            ];

        }

        echo json_encode([
            "data" => $data,
            "permisos" => [
                "editar" => $permisoEditar,
                "eliminar" => $permisoEliminar
            ]
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            "data" => [],
            "success" => false,
            "message" => $e->getMessage()
        ]);

    }

    exit;
}

public function create(): void
{
    header('Content-Type: application/json; charset=utf-8');

    try {

        $data = json_decode(file_get_contents('php://input'), true);

        if (!is_array($data)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Completa los campos obligatorios.'
            ]);
            return;
        }

        $fecha = trim((string) ($data['fecha'] ?? ''));
        $hora = trim((string) ($data['hora'] ?? ''));
        $fechaValida = \DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        $erroresFecha = \DateTimeImmutable::getLastErrors();
        $horaValida = \DateTimeImmutable::createFromFormat('!H:i', $hora);
        $erroresHora = \DateTimeImmutable::getLastErrors();

        if (
            $fecha === '' || $hora === '' ||
            !$fechaValida || $fechaValida->format('Y-m-d') !== $fecha ||
            !$horaValida || $horaValida->format('H:i') !== $hora ||
            ($erroresFecha !== false && ($erroresFecha['warning_count'] || $erroresFecha['error_count'])) ||
            ($erroresHora !== false && ($erroresHora['warning_count'] || $erroresHora['error_count']))
        ) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Completa los campos obligatorios.'
            ]);
            return;
        }

        $precios = [
            'gsuper' => $this->normalizarPrecio($data['gsuper'] ?? ''),
            'gpremium' => $this->normalizarPrecio($data['gpremium'] ?? ''),
            'gdiesel' => $this->normalizarPrecio($data['gdiesel'] ?? '')
        ];


        $idEstacion = $this->estacionModulo();
        $idUsuario  = $this->userId();

        if (!$idEstacion) {

            echo json_encode([
                'success' => false,
                'message' => 'Selecciona una estación para continuar'
            ]);

            exit;
        }

        $cambioPrecio = null;

        Capsule::transaction(function () use ($fecha, $hora, $precios, $idEstacion, $idUsuario, &$cambioPrecio) {

            $productos = $this->validarProductos(
                $idEstacion,
                $precios['gsuper'],
                $precios['gpremium'],
                $precios['gdiesel']
            );

            /*
            |--------------------------------------------------------------------------
            | Crear bitácoras únicamente cuando cambie el precio
            |--------------------------------------------------------------------------
            */

            if ($productos['super']) {

                $this->crearBitacoraCambioPrecio(
                    $idEstacion,
                    $fecha,
                    $hora,
                    'G SUPER',
                    $idUsuario,
                    number_format($precios['gsuper'], 2, '.', '')
                );

            }

            if ($productos['premium']) {

                $this->crearBitacoraCambioPrecio(
                    $idEstacion,
                    $fecha,
                    $hora,
                    'G PREMIUM',
                    $idUsuario,
                    number_format($precios['gpremium'], 2, '.', '')
                );

            }

            if ($productos['diesel']) {

                $this->crearBitacoraCambioPrecio(
                    $idEstacion,
                    $fecha,
                    $hora,
                    'G DIESEL',
                    $idUsuario,
                    number_format($precios['gdiesel'], 2, '.', '')
                );

            }

            /*
            |--------------------------------------------------------------------------
            | Guardar cambio de precio
            |--------------------------------------------------------------------------
            */

            $cambioPrecio = CambioPrecio::create([

                'id_estacion' => $idEstacion,

                'fecha' => $fecha,

                'hora' => $hora,

                'gsuper' => $precios['gsuper'],

                'gpremium' => $precios['gpremium'],

                'gdiesel' => $precios['gdiesel'],

                'estado' => 0

            ]);

        });

        echo json_encode([
            'success' => true,
            'message' => 'Cambio de precio agregado correctamente.',
            'id' => $cambioPrecio?->id
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);

    }

    exit;
}

private function normalizarPrecio($value): float
{
    $value = trim(str_replace(',', '', (string) $value));
    if ($value === '') {
        return 0.0;
    }

    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
        throw new \InvalidArgumentException('Captura un precio válido.');
    }

    return round((float) $value, 2);
}

public function syncWeb(): void
{
    header('Content-Type: application/json; charset=utf-8');

    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $idReporte = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        $idEstacion = $this->estacionModulo();

        if (!$idReporte || !$idEstacion) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'No se encontró el cambio de precio para sincronizar.'
            ]);
            return;
        }

        $cambio = CambioPrecio::query()
            ->where('id', $idReporte)
            ->where('id_estacion', $idEstacion)
            ->first();

        if (!$cambio) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'No se encontró el cambio de precio para sincronizar.'
            ]);
            return;
        }

        [$sincronizado, $mensaje] = $this->sincronizarCambioPrecioPaginaWeb($cambio);
        if (!$sincronizado) {
            http_response_code(502);
            echo json_encode(['success' => false, 'message' => $mensaje]);
            return;
        }

        echo json_encode([
            'success' => true,
            'message' => $mensaje
        ]);
    } catch (\Throwable $e) {
        http_response_code(502);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }

    exit;
}

private function sincronizarCambioPrecioPaginaWeb(CambioPrecio $cambio): array
{
    if (!function_exists('curl_init')) {
        return [false, 'El servidor no tiene habilitada la conexión cURL requerida.'];
    }

    // Replica el endpoint/token del módulo legado desde el servidor para evitar CSP.
    $params = http_build_query([
        'idEstacion' => $cambio->id_estacion,
        'idReporte' => $cambio->id,
        'GSUPER' => number_format((float) $cambio->gsuper, 2, '.', ''),
        'GPREMIUM' => number_format((float) $cambio->gpremium, 2, '.', ''),
        'GDIESEL' => number_format((float) $cambio->gdiesel, 2, '.', ''),
        'Fecha' => $cambio->fecha->format('Y-m-d'),
        'Hora' => substr((string) $cambio->hora, 0, 5),
        'TOKEN' => '28102020'
    ]);

    $curl = curl_init('https://www.admongas.com.mx/app/api/postCambioPrecio.php?' . $params);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        // fetch del módulo anterior seguía redirecciones automáticamente.
        // Mantener esa compatibilidad, limitando los saltos a HTTPS.
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => ['Accept: application/json']
    ]);

    $body = curl_exec($curl);
    $curlError = curl_error($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($body === false || $status < 200 || $status >= 300) {
        return [false, $curlError !== ''
            ? 'No fue posible conectar con el servicio de precios.'
            : 'El servicio de precios respondió con un error HTTP.'];
    }

    $respuesta = json_decode($body, true);
    if (!is_array($respuesta) || ($respuesta['status'] ?? null) !== 'success') {
        return [false, 'El servicio de precios no confirmó la actualización.'];
    }

    return [true, 'Cambio de precio actualizado en la Página Web.'];
}

private function validarProductos(
    int $idEstacion,
    float $gsuper,
    float $gpremium,
    float $gdiesel
): array
{

    $ultimo = CambioPrecio::query()
        ->where('id_estacion', $idEstacion)
        ->latest('id')
        ->first();

    if (!$ultimo) {

        return [
            'super'   => $gsuper > 0,
            'premium' => $gpremium > 0,
            'diesel'  => $gdiesel > 0
        ];

    }

    return [

        'super' => $gsuper > 0 &&
            abs((float)$ultimo->gsuper - $gsuper) > 0.0001,

        'premium' => $gpremium > 0 &&
            abs((float)$ultimo->gpremium - $gpremium) > 0.0001,

        'diesel' => $gdiesel > 0 &&
            abs((float)$ultimo->gdiesel - $gdiesel) > 0.0001,

    ];

}

private function crearBitacoraCambioPrecio(
    int $idEstacion,
    string $fecha,
    string $horaInicio,
    string $producto,
    int $idUsuario,
    string $detalle
): void
{

    $dispensarios = Dispensario::query()
        ->where('id_estacion', $idEstacion)
        ->where('estado', 1)
        ->orderBy('no_dispensario')
        ->get();

    foreach ($dispensarios as $dispensario) {

        $mangueras = 0;

        switch ($producto) {

            case 'G SUPER':
                $mangueras = (int) $dispensario->producto1;
                break;

            case 'G PREMIUM':
                $mangueras = (int) $dispensario->producto2;
                break;

            case 'G DIESEL':
                $mangueras = (int) $dispensario->producto3;
                break;

        }

        $this->guardarRegistroBitacora(

            $dispensario->id,

            $fecha,

            $horaInicio,

            '',

            $producto,

            $idUsuario,

            $detalle,

            $mangueras

        );

    }

}

private function guardarRegistroBitacora(
    int $idDispensario,
    string $fecha,
    string $horaInicio,
    string $horaTermino,
    string $producto,
    int $idUsuario,
    string $detalle,
    int $mangueras
): void
{

    if ($mangueras <= 0) {
        return;
    }

    for ($lado = 1; $lado <= $mangueras; $lado++) {

        DispensarioAperturaBitacora::create([

            'id_dispensario' => $idDispensario,

            'fecha' => $fecha,

            'hora_inicio' => $horaInicio,

            'hora_termino' => $horaTermino,

            'lado' => $lado,

            'producto' => $producto,

            'clave' => 'CAMP',

            'motivo' => 'Cambio de precio',

            'responsable' => $idUsuario,

            'detalle' => $detalle

        ]);

    }

}

public function delete(): void
{
    header('Content-Type: application/json; charset=utf-8');

    try {

        if (!ModuloService::validaPermiso($this->modulo, 'eliminar')) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para eliminar cambios de precio.'
            ]);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        $id = (int)($data['id'] ?? 0);
        $idEstacion = $this->estacionModulo();

        $cambio = $id > 0 && $idEstacion
            ? CambioPrecio::query()
                ->where('id', $id)
                ->where('id_estacion', $idEstacion)
                ->first()
            : null;

        if (!$cambio) {
            http_response_code(404);

            echo json_encode([
                'success' => false,
                'message' => 'El cambio de precio no existe.'
            ]);

            return;

        }

        Capsule::transaction(function () use ($cambio) {

           $this->eliminarBitacoraCambioPrecio(
                $cambio->id_estacion,
                $cambio->fecha->format('Y-m-d'),
                $cambio->hora
            );

            $cambio->delete();

        });

        [$eliminadoRemoto, $mensajeEliminarRemoto] = $this->eliminarCambioPrecioPaginaWeb($id);
        $cambioAnterior = CambioPrecio::query()
            ->where('id_estacion', $idEstacion)
            ->where('id', '<', $id)
            ->orderByDesc('id')
            ->first();

        $precioRestaurado = true;
        $mensajeRestauracion = '';
        if ($cambioAnterior) {
            [$precioRestaurado, $mensajeRestauracion] = $this->sincronizarCambioPrecioPaginaWeb($cambioAnterior);
        }

        $sincronizado = $eliminadoRemoto && $precioRestaurado;
        if ($cambioAnterior && $eliminadoRemoto && $precioRestaurado) {
            $mensajeRemoto = 'Se eliminó el registro y se aplicó el cambio de precio a $0.00 en la Página Web.';
        } elseif ($cambioAnterior && !$eliminadoRemoto && !$precioRestaurado) {
            $mensajeRemoto = 'Se eliminó en Portal3, pero la Página Web no confirmó la eliminación ni la restauración del último precio.';
        } elseif ($cambioAnterior && !$eliminadoRemoto) {
            $mensajeRemoto = 'Se aplicó el último precio en la Página Web, pero no se pudo retirar el cambio eliminado.';
        } elseif ($cambioAnterior) {
            $mensajeRemoto = 'Se eliminó en Portal3 y en la Página Web, pero no se pudo aplicar el último precio existente.';
        } elseif ($eliminadoRemoto) {
            $mensajeRemoto = 'El registro también fue eliminado en la Página Web; no hay otro cambio para restaurar.';
        } else {
            $mensajeRemoto = 'Se eliminó en Portal3, pero la Página Web no confirmó la eliminación.';
        }

        echo json_encode([
            'success' => true,
            'message' => 'Cambio de precio eliminado correctamente.',
            'web_sync' => $sincronizado,
            'web_message' => $mensajeRemoto,
            'web_delete_message' => $mensajeEliminarRemoto,
            'web_restore_message' => $mensajeRestauracion
        ]);

    } catch (\Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);

    }

    exit;
}

private function eliminarCambioPrecioPaginaWeb(int $idReporte): array
{
    if (!function_exists('curl_init')) {
        return [false, 'El cambio se eliminó en Portal3, pero el servidor no tiene habilitada la conexión cURL.'];
    }

    $curl = curl_init('https://www.admongas.com.mx/app/api/postEliminarCambioPrecio.php');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['idReporte' => $idReporte]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json'
        ]
    ]);

    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($body === false || $status < 200 || $status >= 300) {
        return [false, 'El cambio se eliminó en Portal3, pero no fue posible conectar con la Página Web.'];
    }

    $respuesta = json_decode($body, true);
    if (!is_array($respuesta) || empty($respuesta['success'])) {
        return [false, 'El cambio se eliminó en Portal3, pero la Página Web no confirmó la eliminación.'];
    }

    return [true, 'El cambio también fue eliminado en la Página Web.'];
}

private function eliminarBitacoraCambioPrecio(
    int $idEstacion,
    string $fecha,
    string $hora
): void
{
    $idsDispensarios = Dispensario::query()
        ->where('id_estacion', $idEstacion)
        ->where('estado', 1)
        ->pluck('id');

    DispensarioAperturaBitacora::query()
        ->whereIn('id_dispensario', $idsDispensarios)
        ->where('fecha', $fecha)
        ->where('hora_inicio', $hora)
        ->where('clave', 'CAMP')
        ->where('motivo', 'Cambio de precio')
        ->delete();
}
}
