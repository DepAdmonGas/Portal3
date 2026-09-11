<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\FormatoDescargaMermaService;
use App\Services\ModuleStationService;
use App\Services\DropdownYearMesService;
use App\Models\Usuario;
use App\Models\Estacion;

class FormatoDescargaMermaController extends BaseController
{
    public function index()
    {
        $permisos = FormatoDescargaMermaService::getPermisos();

        if (!$permisos['puedeVer']) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        if (!$this->guardModuleAccess(FormatoDescargaMermaService::MODULE_KEY, 'Formato de Descarga de Merma', 'departamento-operativo')) {
            return;
        }

        $validated = DropdownYearMesService::validarYearMes(0, 0);
        $idYear = $validated['idYear'];
        $idMes = $validated['idMes'];

        $ctx = ModuleStationService::getContext(FormatoDescargaMermaService::MODULE_KEY);
        $idEstacion = $ctx['id_estacion'] !== null ? (int)$ctx['id_estacion'] : 0;

        $title = 'Formato de Descarga de Merma (' . nombremes($idMes) . ' ' . $idYear . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Importación', '/departamento-operativo/importacion');
        Breadcrumb::add($title, '');
        Breadcrumb::add(DropdownYearMesService::dropdownMes($idYear, $idMes), '');
        Breadcrumb::add(DropdownYearMesService::dropdownYearManual($idYear, $idMes), '');

        $yearMesTemplate = '/departamento-operativo/importacion/formato-descarga-merma/{year}/{mes}';

        View::render('departamento-operativo/3-importacion/formato-descarga-merma/index', [
            'title'            => $title,
            'idUsuario'        => $permisos['id_usuario'],
            'idEstacion'       => $idEstacion,
            'moduleStationKey' => FormatoDescargaMermaService::MODULE_KEY,
            'multiestacion'    => $permisos['multiestacion'],
            'puedeCrear'       => $permisos['puedeCrear'],
            'puedeEditar'      => $permisos['puedeEditar'],
            'puedeEliminar'    => $permisos['puedeEliminar'],
            'puedeDescargar'   => $permisos['puedeDescargar'],
            'help'             => false,
            'idYear'           => $idYear,
            'idMes'            => $idMes,
            'yearMesTemplate'  => $yearMesTemplate,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/3-importacion/formato-descarga-merma.datatable.init.js?v=' . time(),
                '/assets/js/departamento-operativo/3-importacion/formato-descarga-merma.actions.init.js?v=' . time(),
            ],
            'links' => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
        ], 'departamento-operativo');
    }

    public function getData()
    {
        $permisos = FormatoDescargaMermaService::getPermisos();

        if (!$permisos['puedeVer']) {
            JsonResponse::forbidden('No tienes acceso a este módulo.');
        }

        $input = Request::all();
        $filtroYear = !empty($input['year']) ? (int)$input['year'] : null;
        $filtroMes = !empty($input['mes']) ? (int)$input['mes'] : null;

        JsonResponse::success('OK', [
            'data'     => FormatoDescargaMermaService::getData($filtroYear, $filtroMes),
            'permisos' => $permisos,
        ]);
    }

    public function nuevo()
    {
        $permisos = FormatoDescargaMermaService::getPermisos();

        if (!$permisos['puedeCrear']) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        if (!$this->guardModuleAccess(FormatoDescargaMermaService::MODULE_KEY, 'Formato de Descarga de Merma', 'departamento-operativo')) {
            return;
        }

        $ctx = ModuleStationService::getContext(FormatoDescargaMermaService::MODULE_KEY);
        $idEstacion = $ctx['id_estacion'] !== null ? (int)$ctx['id_estacion'] : null;

        if (!$idEstacion) {
            View::render('errors/404', [], 'departamento-operativo');
            return;
        }

        $estacion = Estacion::find($idEstacion);
        $responsable = Usuario::find($permisos['id_usuario']);

        $folio = FormatoDescargaMermaService::folio($idEstacion);

        $title = 'Nuevo Formato de Descarga';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Importación', '/departamento-operativo/importacion');
        Breadcrumb::add('Formato de Descarga de Merma', '/departamento-operativo/importacion/formato-descarga-merma');
        Breadcrumb::add($title, '');

        View::render('departamento-operativo/3-importacion/formato-descarga-merma/nuevo', [
            'title'            => $title,
            'idUsuario'        => $permisos['id_usuario'],
            'idEstacion'       => $idEstacion,
            'moduleStationKey' => FormatoDescargaMermaService::MODULE_KEY,
            'folio'            => $folio,
            'nombreEstacion'   => $estacion ? $estacion->nombre : 'S/I',
            'nombreResponsable'=> $responsable ? $responsable->nombre : '',
            'help'             => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/signature_pad/docs/js/signature_pad.umd.min.js?v=' . time(),
                '/assets/js/departamento-operativo/3-importacion/formato-descarga-merma.actions.init.js?v=' . time(),
            ],
        ], 'departamento-operativo');
    }  

    public function store()
    {
        $permisos = FormatoDescargaMermaService::getPermisos();

        if (!$permisos['puedeCrear']) {
            JsonResponse::error('No tienes permisos para crear formatos.');
        }

        $ctx = ModuleStationService::getContext(FormatoDescargaMermaService::MODULE_KEY);
        $idEstacion = $ctx['id_estacion'] !== null ? (int)$ctx['id_estacion'] : null;

        if (!$idEstacion) {
            JsonResponse::error('Selecciona una estación.');
        }

        if (!FormatoDescargaMermaService::puedeEstacion($idEstacion)) {
            JsonResponse::error('No tienes acceso a esta estación.');
        }

        $input = Request::all();
        $files = $_FILES ?? [];

        $formData = [
            'fecha_llegada'       => trim((string)($input['fecha_llegada'] ?? '')),
            'hora_llegada'        => trim((string)($input['hora_llegada'] ?? '')),
            'producto'            => trim((string)($input['producto'] ?? '')),
            'sellos'              => trim((string)($input['sellos'] ?? 'No')),
            'detuvo_venta'        => trim((string)($input['detuvo_venta'] ?? 'No')),
            'merma'               => (float)($input['merma'] ?? 0),
            'operador'            => trim((string)($input['operador'] ?? '')),
            'transportista'       => trim((string)($input['transportista'] ?? '')),
            'no_factura_remision' => trim((string)($input['no_factura_remision'] ?? '')),
            'litros'              => (float)($input['litros'] ?? 0),
            'precio_litro'        => (float)($input['precio_litro'] ?? 0),
            'unidad'              => trim((string)($input['unidad'] ?? '')),
            'cuenta_litros'       => (float)($input['cuenta_litros'] ?? 0),
        ];

        $archivos = [
            'no_factura'         => $files['no_factura'] ?? null,
            'inventario_inicial' => $files['inventario_inicial'] ?? null,
            'nice'               => $files['nice'] ?? null,
            'inventario_final'   => $files['inventario_final'] ?? null,
            'metro_contador'     => $files['metro_contador'] ?? null,
            'metro_contador20'   => $files['metro_contador20'] ?? null,
        ];

        $firmaEncargado = trim((string)($input['firma_encargado'] ?? ''));
        $firmaOperador = trim((string)($input['firma_operador'] ?? ''));

        if ($formData['fecha_llegada'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $formData['fecha_llegada'])) {
            JsonResponse::error('La fecha de llegada es obligatoria.');
        }
        if ($formData['producto'] === '') {
            JsonResponse::error('El producto es obligatorio.');
        }
        if ($firmaEncargado === '') {
            JsonResponse::error('La firma del encargado es obligatoria.');
        }
        if ($firmaOperador === '') {
            JsonResponse::error('La firma del operador es obligatoria.');
        }

        try {
            $id = FormatoDescargaMermaService::crear(
                $formData,
                $archivos,
                $firmaEncargado,
                $firmaOperador,
                $permisos['id_usuario'],
                $idEstacion
            );

            if (!$id) {
                JsonResponse::error('Error al registrar el formato.');
            }

            JsonResponse::success('Formato registrado exitosamente.', ['id' => $id]);
        } catch (\Throwable $e) {
            JsonResponse::error('Error al registrar: ' . $e->getMessage());
        }
    }

    public function detalle(int $id)
    {
        $permisos = FormatoDescargaMermaService::getPermisos();

        if (!$permisos['puedeVer']) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        if (!$this->guardModuleAccess(FormatoDescargaMermaService::MODULE_KEY, 'Formato de Descarga de Merma', 'departamento-operativo')) {
            return;
        }

        $registro = FormatoDescargaMermaService::getRegistro($id);
        if (!$registro) {
            View::render('errors/404', [], 'departamento-operativo');
            return;
        }

        if (!FormatoDescargaMermaService::puedeEstacion($registro['id_estacion'])) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        $title = 'Detalle Formato de Descarga' . ' (#00' . $registro['folio'] . ')';

        $contextoCtx = \App\Core\Session::get('module_context') ?? [];
        $contextoAnterior = $contextoCtx[FormatoDescargaMermaService::MODULE_KEY] ?? null;

        \App\Services\ModuleStationService::setContext(FormatoDescargaMermaService::MODULE_KEY, $registro['id_estacion']);

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Importación', '/departamento-operativo/importacion');
        Breadcrumb::add('Formato de Descarga de Merma', '/departamento-operativo/importacion/formato-descarga-merma');
        Breadcrumb::add($title , '');

        View::render('departamento-operativo/3-importacion/formato-descarga-merma/detalle', [
            'title'            => $title,
            'registro'         => $registro,
            'moduleStationKey' => FormatoDescargaMermaService::MODULE_KEY,
            'ocultarSelectorEstacion' => true,
            'puedeDescargar'   => $permisos['puedeDescargar'],
            'help'             => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
            ],
        ], 'departamento-operativo');

        $ctxRestaurado = \App\Core\Session::get('module_context') ?? [];
        if ($contextoAnterior === null) {
            unset($ctxRestaurado[FormatoDescargaMermaService::MODULE_KEY]);
        } else {
            $ctxRestaurado[FormatoDescargaMermaService::MODULE_KEY] = $contextoAnterior;
        }
        \App\Core\Session::set('module_context', $ctxRestaurado);
    }

    public function editar(int $id)
    {
        $permisos = FormatoDescargaMermaService::getPermisos();

        if (!$permisos['puedeEditar']) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        if (!$this->guardModuleAccess(FormatoDescargaMermaService::MODULE_KEY, 'Formato de Descarga de Merma', 'departamento-operativo')) {
            return;
        }

        $registro = FormatoDescargaMermaService::getRegistro($id);
        if (!$registro) {
            View::render('errors/404', [], 'departamento-operativo');
            return;
        }

        if (!FormatoDescargaMermaService::puedeEstacion($registro['id_estacion'])) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        $title = 'Editar Formato de Descarga (#00' . $registro['folio'] . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Importación', '/departamento-operativo/importacion');
        Breadcrumb::add('Formato de Descarga de Merma', '/departamento-operativo/importacion/formato-descarga-merma');
        Breadcrumb::add($title, '');

        $adjuntoFields = [
            'no_factura'         => 'Factura / Remisión',
            'inventario_inicial' => 'Reporte de inventario inicial (con fecha y hora)',
            'nice'               => 'Medida Nice',
            'inventario_final'   => 'Reporte de inventario final (con fecha y hora)',
            'metro_contador'     => 'Metro contador (Temperatura Normal)',
            'metro_contador20'   => 'Metro contador (a 20 °C)',
        ];

        $adjuntos = [];
        foreach ($adjuntoFields as $campo => $label) {
            $valor = $registro[$campo] ?? '';
            $url = $valor ? FormatoDescargaMermaService::getAdjuntoUrl($valor) : '';
            $ext = $valor ? strtolower(pathinfo($valor, PATHINFO_EXTENSION)) : '';
            $adjuntos[$campo] = [
                'label'  => $label,
                'archivo'=> $valor,
                'url'    => $url,
                'is_img' => in_array($ext, ['jpg', 'jpeg', 'png', 'gif']),
                'is_pdf' => $ext === 'pdf',
            ];
        }

        View::render('departamento-operativo/3-importacion/formato-descarga-merma/editar', [
            'title'            => $title,
            'registro'         => $registro,
            'adjuntos'         => $adjuntos,
            'moduleStationKey' => FormatoDescargaMermaService::MODULE_KEY,
            'help'             => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/js/departamento-operativo/3-importacion/formato-descarga-merma.actions.init.js?v=' . time(),
            ],
        ], 'departamento-operativo');
    }

    public function update()
    {
        $permisos = FormatoDescargaMermaService::getPermisos();

        if (!$permisos['puedeEditar']) {
            JsonResponse::error('No tienes permisos para editar formatos.');
        }

        $input = Request::all();
        $files = $_FILES ?? [];
        $id = (int)($input['id'] ?? 0);

        if (!$id) {
            JsonResponse::error('ID no proporcionado.');
        }

        $registro = FormatoDescargaMermaService::getRegistro($id);
        if (!$registro) {
            JsonResponse::error('Registro no encontrado.');
        }

        if (!FormatoDescargaMermaService::puedeEstacion($registro['id_estacion'])) {
            JsonResponse::error('No tienes acceso a la estación de este registro.');
        }

        $formData = [
            'fecha_llegada'       => trim((string)($input['fecha_llegada'] ?? '')),
            'hora_llegada'        => trim((string)($input['hora_llegada'] ?? '')),
            'producto'            => trim((string)($input['producto'] ?? '')),
            'sellos'              => trim((string)($input['sellos'] ?? 'No')),
            'detuvo_venta'        => trim((string)($input['detuvo_venta'] ?? 'No')),
            'merma'               => (float)($input['merma'] ?? 0),
            'operador'            => trim((string)($input['operador'] ?? '')),
            'transportista'       => trim((string)($input['transportista'] ?? '')),
            'no_factura_remision' => trim((string)($input['no_factura_remision'] ?? '')),
            'litros'              => (float)($input['litros'] ?? 0),
            'precio_litro'        => (float)($input['precio_litro'] ?? 0),
            'unidad'              => trim((string)($input['unidad'] ?? '')),
            'cuenta_litros'       => (float)($input['cuenta_litros'] ?? 0),
        ];

        $archivos = [
            'no_factura'         => $files['no_factura'] ?? null,
            'inventario_inicial' => $files['inventario_inicial'] ?? null,
            'nice'               => $files['nice'] ?? null,
            'inventario_final'   => $files['inventario_final'] ?? null,
            'metro_contador'     => $files['metro_contador'] ?? null,
            'metro_contador20'   => $files['metro_contador20'] ?? null,
        ];

        $firmaEncargado = trim((string)($input['firma_encargado'] ?? ''));
        $firmaOperador = trim((string)($input['firma_operador'] ?? ''));

        try {
            $ok = FormatoDescargaMermaService::editar(
                $id,
                $formData,
                $archivos,
                $permisos['id_usuario'],
                $registro['id_estacion'],
                $firmaEncargado,
                $firmaOperador
            );

            if (!$ok) {
                JsonResponse::error('Error al actualizar el formato.');
            }

            JsonResponse::success('Formato actualizado exitosamente.', ['id' => $id]);
        } catch (\Throwable $e) {
            JsonResponse::error('Error al actualizar: ' . $e->getMessage());
        }
    }

    public function delete()
    {
        $permisos = FormatoDescargaMermaService::getPermisos();

        if (!$permisos['puedeEliminar']) {
            JsonResponse::error('No tienes permisos para eliminar formatos.');
        }

        $input = Request::all();
        $id = (int)($input['id'] ?? 0);

        if (!$id) {
            JsonResponse::error('ID no proporcionado.');
        }

        $registro = FormatoDescargaMermaService::getRegistro($id);
        if (!$registro) {
            JsonResponse::error('Registro no encontrado.');
        }

        if (!FormatoDescargaMermaService::puedeEstacion($registro['id_estacion'])) {
            JsonResponse::error('No tienes acceso a la estación de este registro.');
        }

        try {
            $ok = FormatoDescargaMermaService::eliminar($id, $permisos['id_usuario']);
            if (!$ok) {
                JsonResponse::error('Error al eliminar el formato.');
            }

            JsonResponse::success('Formato eliminado exitosamente.');
        } catch (\Throwable $e) {
            JsonResponse::error('Error al eliminar: ' . $e->getMessage());
        }
    }

    public function comentarios(int $id)
    {
        $permisos = FormatoDescargaMermaService::getPermisos();

        if (!$permisos['puedeVer']) {
            JsonResponse::forbidden('No tienes acceso.');
        }

        $registro = FormatoDescargaMermaService::getRegistro($id);
        if (!$registro) {
            JsonResponse::error('Registro no encontrado.');
        }

        JsonResponse::success('OK', [
            'comentarios' => FormatoDescargaMermaService::getComentarios($id),
            'count'       => FormatoDescargaMermaService::getComentarioCount($id),
            'folio'       => $registro['folio'],
            'producto'    => $registro['producto'],
        ]);
    }

    public function agregarComentario()
    {
        $permisos = FormatoDescargaMermaService::getPermisos();

        if (!$permisos['puedeVer']) {
            JsonResponse::error('No tienes permisos para comentar.');
        }

        $input = Request::all();
        $idDescarga = (int)($input['id_descarga'] ?? 0);
        $comentario = trim((string)($input['comentario'] ?? ''));

        if (!$idDescarga) {
            JsonResponse::error('ID no proporcionado.');
        }
        if ($comentario === '') {
            JsonResponse::error('El comentario es obligatorio.');
        }

        $registro = FormatoDescargaMermaService::getRegistro($idDescarga);
        if (!$registro) {
            JsonResponse::error('Registro no encontrado.');
        }

        try {
            $ok = FormatoDescargaMermaService::agregarComentario(
                $idDescarga,
                $permisos['id_usuario'],
                $comentario
            );

            if (!$ok) {
                JsonResponse::error('Error al agregar el comentario.');
            }

            JsonResponse::success('Comentario agregado exitosamente.', [
                'comentarios' => FormatoDescargaMermaService::getComentarios($idDescarga),
                'count'       => FormatoDescargaMermaService::getComentarioCount($idDescarga),
            ]);
        } catch (\Throwable $e) {
            JsonResponse::error('Error al comentar: ' . $e->getMessage());
        }
    }

    public function pdf(int $id)
    {
        $permisos = FormatoDescargaMermaService::getPermisos();

        /*
        if (!$permisos['puedeDescargar']) {
            http_response_code(403);
            echo 'No tienes permisos para descargar.';
            exit;
        }
*/

        $registro = FormatoDescargaMermaService::getRegistro($id);
        if (!$registro) {
            http_response_code(404);
            echo 'Registro no encontrado.';
            exit;
        }

        if (!FormatoDescargaMermaService::puedeEstacion($registro['id_estacion'])) {
            http_response_code(403);
            echo 'No tienes acceso a la estación de este registro.';
            exit;
        }

        $firmasHtml = '';
        if (!empty($registro['firmas'])) {
            $firmasHtml .= '<div class="text-secondary mt-2" style="font-size:1.4em"><b>Firmas:</b></div>';
            $firmasHtml .= '<table class="table table-sm mt-2" style="font-size:1.4em"><tr>';
            $firmaPath = FormatoDescargaMermaService::getFirmaPath();
            foreach ($registro['firmas'] as $tipo => $archivo) {
                $firmaSrc = \App\Helpers\ImageHelper::base64($firmaPath . $archivo);
                if ($firmaSrc) {
                    $firmasHtml .= '<td class="p-2">';
                    $firmasHtml .= '<div style="border: 1px solid #dee2e6;"><div class="text-center" style="margin-top:10px"><b>' . htmlspecialchars($tipo) . '</b></div>';
                    $firmasHtml .= '<div class="text-center"><img src="' . $firmaSrc . '" style="width:200px;"></div></div>';
                    $firmasHtml .= '</td>';
                }
            }
            $firmasHtml .= '</tr></table>';
        }

        $adjuntoImages = '';
        $adjuntoFields = [
            'inventario_inicial' => 'Reporte de inventario Inicial con fecha y hora:',
            'nice'               => 'Medida Nice:',
            'inventario_final'   => 'Reporte de inventario final con fecha y hora:',
            'metro_contador'     => 'Metro contador temperatura normal:',
            'metro_contador20'   => 'Metro contador a 20 grados:',
        ];

        $adjPairs = [];
        $uploadPath = FormatoDescargaMermaService::getUploadPath();
        foreach ($adjuntoFields as $campo => $label) {
            $valor = $registro[$campo] ?? '';
            if ($valor !== '') {
                $ext = strtolower(pathinfo($valor, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $imgSrc = \App\Helpers\ImageHelper::base64($uploadPath . $valor);
                    if ($imgSrc) {
                        $adjPairs[] = ['label' => $label, 'img' => $imgSrc];
                    }
                }
            }
        }

        if (!empty($adjPairs)) {
            $adjuntoImages .= '<table class="table table-sm table-bordered mb-1" style="font-size:1.4em">';
            for ($i = 0; $i < count($adjPairs); $i += 2) {
                $adjuntoImages .= '<tr>';
                $adjuntoImages .= '<td class="p-2">';
                $adjuntoImages .= '<div class="text-secondary">' . htmlspecialchars($adjPairs[$i]['label']) . '</div>';
                $adjuntoImages .= '<div class="text-center" style="margin-top:10px;"><img src="' . $adjPairs[$i]['img'] . '" width="300px"></div>';
                $adjuntoImages .= '</td>';
                if (isset($adjPairs[$i + 1])) {
                    $adjuntoImages .= '<td class="p-2">';
                    $adjuntoImages .= '<div class="text-secondary">' . htmlspecialchars($adjPairs[$i + 1]['label']) . '</div>';
                    $adjuntoImages .= '<div class="text-center" style="margin-top:10px;"><img src="' . $adjPairs[$i + 1]['img'] . '" width="300px"></div>';
                    $adjuntoImages .= '</td>';
                } else {
                    $adjuntoImages .= '<td class="p-2"></td>';
                }
                $adjuntoImages .= '</tr>';
            }
            $adjuntoImages .= '</table>';
        }

        $logoPath = dirname(__DIR__, 2) . '/public/assets/img/logo-75.png';
        $logoBase64 = '';
        if (file_exists($logoPath)) {
            $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
        }

        $fechallegada = formatearFecha($registro['fecha_llegada']);
        $horallegada = date('g:i a', strtotime($registro['hora_llegada']));
        $litros = $registro['litros_raw'];
        $cuentaLitros = $registro['cuenta_litros_raw'];
        $valortolerancia = $litros * 0.55 / 100;
        $tolerancia = round($valortolerancia);
        $merma = $litros - $cuentaLitros;
        $calculaNC = $merma - $tolerancia;
        $preciolitro = $registro['precio_litro_raw'];
        if ($cuentaLitros != 0) {
            $NC = number_format($calculaNC * $preciolitro, 2);
        } else {
            $NC = 0;
        }

        $html = '<html lang="es"><head><style type="text/css">
            @page {margin: 0.5cm 0.5cm;}
            *, *::before, *::after { box-sizing: border-box; }
            html { font-family: sans-serif; line-height: 1.15; }
            body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: .9rem; font-weight: 400; line-height: 1.15; color: #212529; background-color: #fff; }
            .text-center { text-align: center !important; }
            .text-secondary { color: #6c757d !important; }
            .mt-2 { margin-top: 0.5rem !important; }
            .mt-1 { margin-top: 0.25rem !important; }
            .mb-1 { margin-bottom: 0.25rem !important; }
            .p-2 { padding: 0.70rem !important; }
            table { border-collapse: collapse; width: 100%; margin-bottom: 10px; }
            .table { width: 100%; max-width: 100%; margin-bottom: 10px; background-color: transparent; }
            .table th, .table td { padding: 0.75rem; vertical-align: top; border-top: 1px solid #dee2e6; }
            .table-sm th, .table-sm td { padding: 0.3rem; }
            .table-bordered { border: 1px solid #dee2e6; }
            .table-bordered th, .table-bordered td { border: 1px solid #dee2e6; }
        </style></head><body>';

        if ($logoBase64) {
            $html .= '<img src="' . $logoBase64 . '" style="width:180px;">';
        }
        $html .= '<div class="text-center" style="font-size:1.8em;margin-top:20px;">Formato de descarga merma</div>';

        $html .= '<table class="table table-sm table-bordered" style="font-size:1.4em;margin-top:20px;">';
        $html .= '<tr>';
        $html .= '<td class="p-2"><div class="text-secondary">Folio:</div><div class="mt-1"><b>00' . $registro['folio'] . '</b></div></td>';
        $html .= '<td class="p-2"><div class="text-secondary">Estación de descarga:</div><div class="mt-1">' . htmlspecialchars($registro['estacion']) . '</div></td>';
        $html .= '<td class="p-2"><div class="text-secondary">Fecha y hora de llegada de full:</div><div class="mt-1">' . htmlspecialchars($fechallegada) . ', ' . htmlspecialchars($horallegada) . '</div></td>';
        $html .= '</tr></table>';

        $html .= '<table class="table table-sm table-bordered" style="font-size:1.4em">';
        $html .= '<tr>';
        $html .= '<td class="p-2"><div class="text-secondary">Productos recibido:</div><div class="mt-1">' . htmlspecialchars($registro['producto']) . '</div></td>';
        $html .= '<td class="p-2"><div class="text-secondary">Numero Factura o Remisión:</div><div class="mt-1">' . htmlspecialchars($registro['no_factura_remision']) . '</div></td>';
        $html .= '<td class="p-2"><div class="text-secondary">Litros:</div><div class="mt-1">' . $litros . '</div></td>';
        $html .= '</tr></table>';

        $html .= '<table class="table table-sm table-bordered mt-2" style="font-size:1.4em">';
        $html .= '<tr>';
        $html .= '<td class="p-2"><div class="text-secondary">Precio por litro:</div><div class="mt-1">' . $preciolitro . '</div></td>';
        $html .= '<td class="p-2"><div class="text-secondary">Cuenta litro:</div><div class="mt-1">' . $cuentaLitros . '</div></td>';
        $html .= '<td class="p-2"><div class="text-secondary">Tolerancia:</div><div class="mt-1">' . $tolerancia . '</div></td>';
        $html .= '</tr></table>';

        $html .= '<table class="table table-sm table-bordered mt-2" style="font-size:1.4em">';
        $html .= '<tr>';
        $html .= '<td class="p-2"><div class="text-secondary">Merma en Litros:</div><div class="mt-1">' . $merma . '</div></td>';
        $html .= '<td class="p-2"><div class="text-secondary">N.C:</div><div class="mt-1">' . $calculaNC . '</div></td>';
        $html .= '<td class="p-2"><div class="text-secondary">Importe N.C:</div><div class="mt-1">' . $NC . '</div></td>';
        $html .= '</tr></table>';

        $html .= '<table class="table table-sm table-bordered mt-2" style="font-size:1.4em">';
        $html .= '<tr>';
        $html .= '<td class="p-2"><div class="text-secondary">Unidad:</div><div class="mt-1">' . htmlspecialchars($registro['unidad']) . '</div></td>';
        $html .= '<td class="p-2"><div class="text-secondary">Nombre del operador de la unidad:</div><div class="mt-1">' . htmlspecialchars($registro['operador']) . '</div></td>';
        $html .= '<td class="p-2"><div class="text-secondary">Compañía de Transportista:</div><div class="mt-1">' . htmlspecialchars($registro['transportista']) . '</div></td>';
        $html .= '</tr></table>';

        if ($adjuntoImages) {
            $html .= $adjuntoImages;
        }

        $html .= '<table class="table table-sm table-bordered mt-2" style="font-size:1.4em">';
        $html .= '<tr>';
        $html .= '<td class="p-2"><div class="text-secondary">Sellos alterados:</div><div class="mt-1">' . htmlspecialchars($registro['sellos']) . '</div></td>';
        $html .= '<td class="p-2"><div class="text-secondary">Se detuvo venta durante la descarga:</div><div class="mt-1">' . htmlspecialchars($registro['detuvo_venta']) . '</div></td>';
        $html .= '</tr></table>';

        if ($firmasHtml) {
            $html .= $firmasHtml;
        }

        $html .= '</body></html>';

        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $fileName = 'formato-descarga-merma-00' . $registro['folio'] . '.pdf';
        $dompdf->stream($fileName, ['Attachment' => 1]);
        exit;
    }

    public function excel(int $id)
    {
        $permisos = FormatoDescargaMermaService::getPermisos();

        /*
        if (!$permisos['puedeDescargar']) {
            http_response_code(403);
            echo 'No tienes permisos para descargar.';
            exit;
        }
            */

        $registro = FormatoDescargaMermaService::getRegistro($id);
        if (!$registro) {
            http_response_code(404);
            echo 'Registro no encontrado.';
            exit;
        }

        if (!FormatoDescargaMermaService::puedeEstacion($registro['id_estacion'])) {
            http_response_code(403);
            echo 'No tienes acceso a la estación de este registro.';
            exit;
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Detalle Merma');

        $headers = [
            'Folio', 'Estación de descarga', 'Fecha y hora de llegada del full',
            'Producto recibido', 'No. de factura o remisión', 'Litros', 'Precio por litro',
            'Cuenta litros', 'Tolerancia', 'Merma en litros', 'N.C', 'Importe N.C',
            'Unidad', 'Nombre del operador de la unidad', 'Compañia del transportista',
            'Sellos alterados', 'Se detuvo venta durante la descarga'
        ];

        foreach ($headers as $col => $header) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
            $cell = $sheet->getCell($colLetter . '1');
            $cell->setValue($header);
            $cell->getStyle()->getFont()->setBold(true);
            $cell->getStyle()->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('749ABF');
            $cell->getStyle()->getFont()->getColor()->setRGB('FFFFFF');
            $cell->getStyle()->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $cell->getStyle()->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $values = [
            '00' . $registro['folio'], $registro['estacion'], $registro['fecha_hora_llegada'],
            $registro['producto'], $registro['no_factura_remision'], $registro['litros_raw'],
            $registro['precio_litro_raw'], $registro['cuenta_litros_raw'],
            $registro['tolerancia_raw'], $registro['merma_raw'], $registro['nc_raw'],
            $registro['importe_nc_raw'], $registro['unidad'], $registro['operador'],
            $registro['transportista'], $registro['sellos'], $registro['detuvo_venta']
        ];

        foreach ($values as $col => $value) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
            $cell = $sheet->getCell($colLetter . '2');
            $cell->setValue($value);
            $cell->getStyle()->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $cell->getStyle()->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        }

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $fileName = 'formato-descarga-merma-00' . $registro['folio'] . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    public function excelBusqueda()
    {
        $permisos = FormatoDescargaMermaService::getPermisos();

        /*
        if (!$permisos['puedeDescargar']) {
            http_response_code(403);
            echo 'No tienes permisos para descargar.';
            exit;
        }
*/

        $input = Request::all();
        $idEstacion = (int)($input['estacion'] ?? 0);
        $year = (int)($input['year'] ?? 0);
        $mes = (int)($input['mes'] ?? 0);

        if (!$idEstacion || !$year || !$mes) {
            http_response_code(400);
            echo 'Parámetros inválidos.';
            exit;
        }

        if (!FormatoDescargaMermaService::puedeEstacion($idEstacion)) {
            http_response_code(403);
            echo 'No tienes acceso a esta estación.';
            exit;
        }

        $data = FormatoDescargaMermaService::getDataBusqueda($idEstacion, $year, $mes);

        $estacion = Estacion::find($idEstacion);
        $nombreEstacion = $estacion ? $estacion->nombre : 'S/I';

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Registros');

        $headers = [
            'Folio', 'Estación de descarga', 'Fecha y hora de llegada del full',
            'Producto recibido', 'No. de factura o remisión', 'Litros', 'Precio por litro',
            'Cuenta litros', 'Tolerancia', 'Merma en litros', 'N.C', 'Importe N.C',
            'Unidad', 'Nombre del operador de la unidad', 'Compañia del transportista',
            'Sellos alterados', 'Se detuvo venta durante la descarga'
        ];

        foreach ($headers as $col => $header) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
            $cell = $sheet->getCell($colLetter . '1');
            $cell->setValue($header);
            $cell->getStyle()->getFont()->setBold(true);
            $cell->getStyle()->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('749ABF');
            $cell->getStyle()->getFont()->getColor()->setRGB('FFFFFF');
        }

        if (empty($data)) {
            $sheet->getCell('A2')->setValue('No se encontró información.');
        } else {
            $row = 2;
            foreach ($data as $r) {
                $values = [
                    '00' . $r['folio'], $r['estacion'], $r['fecha_llegada'],
                    $r['producto'], $r['no_factura_remision'], $r['litros'],
                    $r['precio_litro'], $r['cuenta_litros'], $r['tolerancia'],
                    $r['merma'], $r['nc'], $r['importe_nc'], $r['unidad'],
                    $r['operador'], $r['transportista'], $r['sellos'], $r['detuvo_venta']
                ];
                foreach ($values as $col => $value) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
                    $sheet->getCell($colLetter . $row)->setValue($value);
                }
                $row++;
            }
        }

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $fileName = 'descarga-merma-' . $nombreEstacion . '-' . $year . '-' . str_pad($mes, 2, '0', STR_PAD_LEFT) . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    public function excelGeneral()
    {
        $permisos = FormatoDescargaMermaService::getPermisos();

        /*
        if (!$permisos['puedeDescargar']) {
            http_response_code(403);
            echo 'No tienes permisos para descargar.';
            exit;
        }
            */

        $input = Request::all();
        $year = (int)($input['year'] ?? 0);
        $mes = (int)($input['mes'] ?? 0);

        if (!$year || !$mes) {
            http_response_code(400);
            echo 'Parámetros inválidos.';
            exit;
        }

        $dataGeneral = FormatoDescargaMermaService::getDataBusquedaGeneral($year, $mes);

        $headers = [
            'Folio', 'Estación de descarga', 'Fecha y hora de llegada del full',
            'Producto recibido', 'No. de factura o remisión', 'Litros', 'Precio por litro',
            'Cuenta litros', 'Tolerancia', 'Merma en litros', 'N.C', 'Importe N.C',
            'Unidad', 'Nombre del operador de la unidad', 'Compañia del transportista',
            'Sellos alterados', 'Se detuvo venta durante la descarga'
        ];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $first = true;
        foreach ($dataGeneral as $grupo) {
            $sheetName = preg_replace('/[^a-zA-Z0-9]/', '', $grupo['estacion_nombre']);
            if (empty($sheetName)) {
                $sheetName = 'Estacion-' . $grupo['estacion_id'];
            }
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($sheetName);

            if ($first) {
                $spreadsheet->setActiveSheetIndexByName($sheetName);
                $first = false;
            }

            foreach ($headers as $col => $header) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
                $cell = $sheet->getCell($colLetter . '1');
                $cell->setValue($header);
                $cell->getStyle()->getFont()->setBold(true);
                $cell->getStyle()->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('749ABF');
                $cell->getStyle()->getFont()->getColor()->setRGB('FFFFFF');
                $cell->getStyle()->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $cell->getStyle()->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                $sheet->getColumnDimension($colLetter)->setAutoSize(true);
            }

            if (empty($grupo['registros'])) {
                $sheet->getCell('A2')->setValue('No se encontró información.');
            } else {
                $row = 2;
                foreach ($grupo['registros'] as $r) {
                    $values = [
                        '00' . $r['folio'], $r['estacion'], $r['fecha_llegada'],
                        $r['producto'], $r['no_factura_remision'], $r['litros'],
                        $r['precio_litro'], $r['cuenta_litros'], $r['tolerancia'],
                        $r['merma'], $r['nc'], $r['importe_nc'], $r['unidad'],
                        $r['operador'], $r['transportista'], $r['sellos'], $r['detuvo_venta']
                    ];
                    foreach ($values as $col => $value) {
                        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
                        $cell = $sheet->getCell($colLetter . $row);
                        $cell->setValue($value);
                        $cell->getStyle()->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                        $cell->getStyle()->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                    }
                    $row++;
                }
            }
        }

        if ($spreadsheet->getSheetCount() === 0) {
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('General');
            $sheet->getCell('A1')->setValue('No se encontró información.');
        }

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $fileName = 'descarga-merma-general-' . $year . '-' . str_pad($mes, 2, '0', STR_PAD_LEFT) . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    public function buscar()
    {
        $permisos = FormatoDescargaMermaService::getPermisos();

        if (!$permisos['puedeVer']) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        if (!$this->guardModuleAccess(FormatoDescargaMermaService::MODULE_KEY, 'Formato de Descarga de Merma', 'departamento-operativo')) {
            return;
        }

        $title = 'Buscar Formato de Descarga';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Importación', '/departamento-operativo/importacion');
        Breadcrumb::add('Formato de Descarga de Merma', '/departamento-operativo/importacion/formato-descarga-merma');
        Breadcrumb::add($title, '');

        $ctx = ModuleStationService::getContext(FormatoDescargaMermaService::MODULE_KEY);
        $idEstacion = $ctx['id_estacion'] !== null ? (int)$ctx['id_estacion'] : 0;

        View::render('departamento-operativo/3-importacion/formato-descarga-merma/buscar', [
            'title'            => $title,
            'moduleStationKey' => FormatoDescargaMermaService::MODULE_KEY,
            'idEstacion'       => $idEstacion,
            'puedeDescargar'   => $permisos['puedeDescargar'],
            'help'             => false,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/3-importacion/formato-descarga-merma.buscar.init.js?v=' . time(),
            ],
            'links' => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
        ], 'departamento-operativo');
    }

    public function buscarData()
    {
        $permisos = FormatoDescargaMermaService::getPermisos();

        if (!$permisos['puedeVer']) {
            JsonResponse::forbidden('No tienes acceso.');
        }

        $input = Request::all();
        $idEstacion = (int)($input['estacion'] ?? 0);
        $year = (int)($input['year'] ?? 0);
        $mes = (int)($input['mes'] ?? 0);

        if (!$idEstacion || !$year || !$mes) {
            JsonResponse::error('Selecciona estación, año y mes.');
        }

        if (!FormatoDescargaMermaService::puedeEstacion($idEstacion)) {
            JsonResponse::error('No tienes acceso a esta estación.');
        }

        JsonResponse::success('OK', [
            'data' => FormatoDescargaMermaService::getDataBusqueda($idEstacion, $year, $mes),
        ]);
    }

    public function filtrarPorAnioMes(int $year, int $mes)
    {
        $permisos = FormatoDescargaMermaService::getPermisos();

        if (!$permisos['puedeVer']) {
            View::render('errors/403', [], 'departamento-operativo');
            return;
        }

        if (!$this->guardModuleAccess(FormatoDescargaMermaService::MODULE_KEY, 'Formato de Descarga de Merma', 'departamento-operativo')) {
            return;
        }

        $validated = DropdownYearMesService::validarYearMes($year, $mes);
        $idYear = $validated['idYear'];
        $idMes = $validated['idMes'];

        $ctx = ModuleStationService::getContext(FormatoDescargaMermaService::MODULE_KEY);
        $idEstacion = $ctx['id_estacion'] !== null ? (int)$ctx['id_estacion'] : 0;

        $title = 'Formato de Descarga de Merma (' . nombremes($idMes) . ' ' . $idYear . ')';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Importación', '/departamento-operativo/importacion');
        Breadcrumb::add($title, '');
        Breadcrumb::add(DropdownYearMesService::dropdownMes($idYear, $idMes), '');
        Breadcrumb::add(DropdownYearMesService::dropdownYearManual($idYear, $idMes), '');

        $yearMesTemplate = '/departamento-operativo/importacion/formato-descarga-merma/{year}/{mes}';

        View::render('departamento-operativo/3-importacion/formato-descarga-merma/index', [
            'title'            => $title,
            'idUsuario'        => $permisos['id_usuario'],
            'idEstacion'       => $idEstacion,
            'moduleStationKey' => FormatoDescargaMermaService::MODULE_KEY,
            'multiestacion'    => $permisos['multiestacion'],
            'puedeCrear'       => $permisos['puedeCrear'],
            'puedeEditar'      => $permisos['puedeEditar'],
            'puedeEliminar'    => $permisos['puedeEliminar'],
            'puedeDescargar'   => $permisos['puedeDescargar'],
            'help'             => false,
            'idYear'           => $idYear,
            'idMes'            => $idMes,
            'yearMesTemplate'  => $yearMesTemplate,
            'scripts' => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/libs/datatables.net/js/jquery.dataTables.min.js',
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/3-importacion/formato-descarga-merma.datatable.init.js?v=' . time(),
                '/assets/js/departamento-operativo/3-importacion/formato-descarga-merma.actions.init.js?v=' . time(),
            ],
            'links' => [
                '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
            ],
        ], 'departamento-operativo');
    }
}
