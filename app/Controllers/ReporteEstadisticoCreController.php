<?php

namespace App\Controllers;

use App\Core\Breadcrumb;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\DropdownYearMesService;
use App\Services\ReporteEstadisticoCreService as Servicio;
use Carbon\Carbon;

class ReporteEstadisticoCreController extends BaseController
{
    protected string $modulo = 'corporativo';

    /** Redirige a año/mes por defecto cuando no vienen en la URL. */
    public function redirect(): void
    {
        $validados = DropdownYearMesService::validarYearMes(0, 0);

        header('Location: ' . Servicio::BASE_URL . '/' . $validados['idYear'] . '/' . $validados['idMes']);
        exit;
    }

    public function index(int $idYear = 0, int $idMes = 0): void
    {
        $validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
        $idYear = $validados['idYear'];
        $idMes  = $validados['idMes'];

        $title = 'Reporte Estadístico de la CRE';

        Breadcrumb::add('Home', '/home');
        Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
        Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
        Breadcrumb::add($title, '');
        Breadcrumb::add(DropdownYearMesService::dropdownMes($idYear, $idMes), '');
        Breadcrumb::add(DropdownYearMesService::dropdownYearManual($idYear, $idMes, Servicio::ANIO_INICIO), '');

        if (!$this->guardModuleAccess(Servicio::MODULE_KEY, $title, 'departamento-operativo')) {
            return;
        }

        $permisos = Servicio::getPermisos();
        $idEstacion = $permisos['id_estacion'];

        $data = [
            'title'             => $title,
            'idYear'            => $idYear,
            'idMes'             => $idMes,
            'idEstacion'        => $idEstacion,
            'estacion'          => $permisos['estacion'],
            'anios'             => $idEstacion ? Servicio::aniosConDatos($idEstacion) : [],
            'productos'         => $idEstacion ? Servicio::productosEstacion($idEstacion) : [],
            'mesesDisponibles'  => $idEstacion ? Servicio::mesesDisponibles($idEstacion, $idYear) : [],
            'puedeDescargar'    => $permisos['puedeDescargar'],
            'esMesActual'       => Carbon::now()->year === $idYear && Carbon::now()->month === $idMes,
            'moduleStationKey'  => Servicio::MODULE_KEY,
            'yearMesTemplate'   => Servicio::BASE_URL . '/{year}/{mes}',
            'help'              => false,
            'scripts'           => [
                '/assets/js/vendor.min.js?v=' . time(),
                '/assets/js/core/module-station-selector.js?v=' . time(),
                '/assets/js/departamento-operativo/1-corporativo/reporte-estadistico-cre/index.actions.init.js?v=' . time(),
            ],
            'links'             => [],
        ];

        View::render('departamento-operativo/1-corporativo/reporte-estadistico-cre/index', $data, 'departamento-operativo');
    }

    /** Reporte del mes seleccionado. */
    public function data(): void
    {
        $idEstacion = $this->estacionValida();

        if ($idEstacion === null) {
            return;
        }

        [$idYear, $idMes] = $this->anioMesValidos();

        $reporte = Servicio::reporteMensual($idEstacion, $idYear, $idMes);

        JsonResponse::custom([
            'reporte' => $reporte,
            'anual'   => Servicio::resumenAnual($idEstacion, $idYear),
        ]);
    }

    /** Totales del año (vista anual del módulo anterior). */
    public function anual(): void
    {
        $idEstacion = $this->estacionValida();

        if ($idEstacion === null) {
            return;
        }

        [$idYear] = $this->anioMesValidos();

        JsonResponse::custom([
            'anual' => Servicio::resumenAnual($idEstacion, $idYear),
        ]);
    }

    public function mensajes(): void
    {
        $idEstacion = $this->estacionValida();

        if ($idEstacion === null) {
            return;
        }

        $idReporte = (int)Request::input('id_reporte');
        $idFecha   = (int)Request::input('id_fecha');

        if ($idReporte <= 0 || $idFecha <= 0) {
            JsonResponse::error('Los datos del reporte no son válidos.');
            return;
        }

        if (!Servicio::reportePerteneceAEstacion($idReporte, $idEstacion)) {
            JsonResponse::error('No se encontró el reporte.');
            return;
        }

        JsonResponse::custom([
            'data' => Servicio::mensajes($idReporte, $idFecha, (int)$this->userId()),
        ]);
    }

    /**
     * Crea un comentario del reporte CRE.
     *
     * Sin.validacion de permiso, igual que el modulo anterior
     * (agregar-mensaje-cre.php de portal-sasisopa): cualquier usuario
     * autenticado con una estacion valida puede comentar.
     * Si se conserva la validacion, el reporte podria leerse pero no
     * anotarse, que no es el comportamiento historico.
     */
    public function crearMensaje(): void
    {
        $idEstacion = $this->estacionValida();

        if ($idEstacion === null) {
            return;
        }

        $idReporte = (int)Request::input('id_reporte');
        $idFecha   = (int)Request::input('id_fecha');
        $texto     = trim((string)Request::input('mensaje'));

        if ($idReporte <= 0 || $idFecha <= 0) {
            JsonResponse::error('Los datos del reporte no son válidos.');
            return;
        }

        if ($texto === '') {
            JsonResponse::error('Escribe un mensaje.');
            return;
        }

        if (mb_strlen($texto) > 2000) {
            JsonResponse::error('El mensaje es demasiado largo.');
            return;
        }

        if (!Servicio::reportePerteneceAEstacion($idReporte, $idEstacion)) {
            JsonResponse::error('No se encontró el reporte.');
            return;
        }

        $mensaje = Servicio::crearMensaje($idReporte, $idFecha, (int)$this->userId(), $texto);

        if (!$mensaje) {
            JsonResponse::error('No fue posible enviar el mensaje.');
            return;
        }

        JsonResponse::custom([
            'data' => Servicio::mensajes($idReporte, $idFecha, (int)$this->userId()),
        ]);
    }

    /**
     * ZIP con las facturas CRE del año, agrupadas por mes.
     * Mismo comportamiento y estructura que la exportación anterior.
     */
    public function descargarFacturas(): void
    {
        $permisos = Servicio::getPermisos();

        if (!$permisos['puedeDescargar']) {
            JsonResponse::forbidden('No tienes permiso para descargar facturas.');
            return;
        }

        $idEstacion = $this->estacionValida();

        if ($idEstacion === null) {
            return;
        }

        $idYear = (int)Request::input('year');
        $anioActual = (int)date('Y');

        if ($idYear < Servicio::ANIO_INICIO || $idYear > $anioActual) {
            JsonResponse::error('El año seleccionado no es válido.');
            return;
        }

        $estacion = Servicio::estacion($idEstacion);

        if (!$estacion) {
            JsonResponse::notFound('No se encontró la estación.');
            return;
        }

        $facturas = Servicio::facturasParaZip($idEstacion, $idYear);

        if (empty($facturas)) {
            JsonResponse::notFound('No se encontraron facturas para descargar.');
            return;
        }

        $zipPath = $this->crearZip($idEstacion, $idYear, $facturas);

        if ($zipPath === null) {
            JsonResponse::serverError('No fue posible generar el archivo de facturas.');
            return;
        }

        $nombreZip = Servicio::nombreZip($estacion, $idYear);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $nombreZip . '"');
        header('Content-Length: ' . filesize($zipPath));
        header('Cache-Control: no-store, no-cache, must-revalidate');

        readfile($zipPath);
        unlink($zipPath);
        exit;
    }

    /**
     * Genera el ZIP en un temporal del sistema con la estructura Mes/archivo.pdf.
     */
    private function crearZip(int $idEstacion, int $year, array $facturas): ?string
    {
        $temporal = tempnam(sys_get_temp_dir(), 'cre_zip_');

        if ($temporal === false) {
            return null;
        }

        $zipPath = $temporal . '.zip';

        if (is_file($temporal)) {
            unlink($temporal);
        }

        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        $agregados = 0;

        foreach ($facturas as $mes => $lista) {

            $carpeta = nombremes((int)$mes);

            foreach ($lista as $factura) {

                $rutaInterna = $carpeta . '/' . $factura['archivo'];

                if ($zip->locateName($rutaInterna) !== false) {
                    continue;
                }

                if ($zip->addFile($factura['ruta'], $rutaInterna)) {
                    $agregados++;
                }
            }
        }

        $zip->close();

        if ($agregados === 0 || !is_file($zipPath)) {
            if (is_file($zipPath)) {
                unlink($zipPath);
            }
            return null;
        }

        return $zipPath;
    }

    //----------------------------------------------------------------------
    // VALIDACIONES DE BACKEND
    //----------------------------------------------------------------------

    /**
     * Devuelve la estación validada o responde 403.
     * Sin esto, ocultar la tabla en el frontend no protegería las consultas.
     */
    private function estacionValida(): ?int
    {
        $idEstacion = Servicio::estacionContextual();

        if ($idEstacion === null) {
            JsonResponse::forbidden('Debes de seleccionar una estación del menú superior para poder visualizar los elementos de REPORTE ESTADÍSTICO DE LA CRE.');
            return null;
        }

        return $idEstacion;
    }

    private function anioMesValidos(): array
    {
        $validados = DropdownYearMesService::validarYearMes(
            (int)Request::input('year'),
            (int)Request::input('mes')
        );

        return [$validados['idYear'], $validados['idMes']];
    }
}