<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Estacion;
use App\Models\ListaTransportes;
use App\Models\Operativo\CuentaLitros;
use App\Models\Operativo\CuentaLitrosDetalle;
use App\Models\UnidadesTransporte;

class CuentaLitrosService
{
    public const MODULE_KEY = 'cuenta-litros';

    public const EMBARQUES = ['Pemex', 'Delivery', 'Pick Up'];

    public const TADS_FORMATO = [
        'Atlacomulco',
        'Tizayuca',
        'Tuxpan',
        'Puebla',
        'Vopack',
        'Monterra',
        'Azcapotzalco',
    ];

    public const TADS_DETALLE = [
        'Atlacomulco',
        'Tizayuca',
        'Tuxpan',
        'Puebla',
        'Vopack',
        'Monterra',
    ];

    public const CARPETA_ARCHIVOS = 'cuenta-litros';

    public const CABECERA_ESPECIAL_2720 = 2720;

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

        return [
            'id_usuario'     => $idUsuario,
            'id_puesto'      => $idPuesto,
            'multiestacion'  => $multiestacion,
            'puedeVer'       => $accede && $leer,
            'puedeCrear'     => $accede && $crear && !$multiestacion,
            'puedeEditar'    => $accede && $editar && !$multiestacion,
            'puedeDetalle'   => $accede && $leer,
            'puedeEliminar'  => $accede && $eliminar && !$multiestacion,
            'puedeHabilitar' => $accede && $leer && !$editar && !$multiestacion,
        ];
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

    public static function getProductosEstacion(int $idCuentaLitros): array
    {
        $cabecera = CuentaLitros::find($idCuentaLitros);
        if (!$cabecera) {
            return [];
        }

        $estacion = Estacion::find($cabecera->id_estacion);
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

    public static function getTransportes(): array
    {
        return ListaTransportes::where('estado', 0)
            ->orderBy('nombre_transporte', 'asc')
            ->pluck('nombre_transporte')
            ->toArray();
    }

    public static function getUnidades(): array
    {
        return UnidadesTransporte::where('estado', 0)
            ->orderBy('no_unidad', 'asc')
            ->pluck('no_unidad')
            ->toArray();
    }

    public static function getTads(bool $detalle = false): array
    {
        return $detalle ? self::TADS_DETALLE : self::TADS_FORMATO;
    }

    public static function getListado(int $year, int $mes, ?int $idEstacion): array
    {
        $permisos = self::getPermisos();
        if (!$permisos['puedeVer']) {
            return [];
        }

        $permitidas = array_column(self::getEstacionesPermitidas(), 'id');

        $query = CuentaLitros::where('year', $year)
            ->where('mes', $mes);

        if ($idEstacion !== null) {
            if (!in_array((int)$idEstacion, $permitidas, true)) {
                return [];
            }
            $query->where('id_estacion', $idEstacion);
        } elseif (!empty($permitidas)) {
            $query->whereIn('id_estacion', $permitidas);
        }

        $registros = $query->orderBy('fecha', 'asc')
            ->orderBy('id_cuenta_litros', 'asc')
            ->get();

        if ($registros->isEmpty()) {
            return [];
        }

        $idsEstaciones = array_values(array_unique($registros->pluck('id_estacion')->all()));
        $mapaEstaciones = Estacion::whereIn('id', $idsEstaciones)
            ->get()
            ->keyBy('id');

        $filas = [];
        foreach ($registros as $r) {
            $estatus = (int)$r->estatus;

            $filas[] = [
                'id'              => (int)$r->id_cuenta_litros,
                'fecha_raw'       => $r->fecha->format('Y-m-d'),
                'fecha'           => formatearFechaLarga($r->fecha->format('Y-m-d')),
                'estatus'         => $estatus,
                'estatus_texto'   => $estatus === 1 ? 'Finalizado' : 'En proceso',
                'estacion_id'     => (int)$r->id_estacion,
                'estacion'        => isset($mapaEstaciones[$r->id_estacion])
                    ? $mapaEstaciones[$r->id_estacion]->nombre
                    : 'Estación #' . $r->id_estacion,
                'rowClass'        => $estatus === 1 ? '#b0f2c2' : '#fcfcda',
                'puedeEditar'     => $permisos['puedeEditar'] && $estatus === 0,
                'puedeDetalle'    => $permisos['puedeVer'] && $estatus === 1,
                'puedeEliminar'   => $permisos['puedeEliminar'],
                'puedeHabilitar'  => $permisos['puedeVer'] && !$permisos['puedeEditar'] && $estatus === 1,
                'puedeCrear'      => $permisos['puedeCrear'],
                'anio'            => (int)$r->year,
                'mes'             => (int)$r->mes,
            ];
        }

        return $filas;
    }

    public static function getVista(int $id, string $modo): array
    {
        $cabecera = CuentaLitros::find($id);
        if (!$cabecera) {
            throw new \RuntimeException('No se encontró el registro solicitado.');
        }

        $permisos = self::getPermisos();
        if (!$permisos['puedeVer']) {
            throw new \RuntimeException('No tienes permiso para consultar este módulo.');
        }

        $estacion = (int)$cabecera->id_estacion;
        if (!self::estacionAutorizada($estacion)) {
            throw new \RuntimeException('No tienes permiso para consultar esta estación.');
        }

        $estatus = (int)$cabecera->estatus;

        $detalles = CuentaLitrosDetalle::where('id_cuenta_litros', $id)
            ->orderBy('producto', 'desc')
            ->get();

        $filas = [];
        foreach ($detalles as $det) {
            $calc = self::calcular((array)$det->getAttributes(), (int)$cabecera->id_cuenta_litros);

            $embarque = $det->embarque;
            $archivo = $det->archivo;

            $filas[] = array_merge([
                'id_detalle'         => (int)$det->id_detalle,
                'hora'               => $det->hora,
                'hora_display'       => date('g:i a', strtotime($det->hora)),
                'embarque'           => $embarque,
                'transporte'         => $det->transporte,
                'ocultar_transporte' => $embarque === 'Pemex',
                'producto'           => $det->producto,
                'tanque'             => $det->tanque,
                'litros'             => $det->litros,
                'descarga_neto'      => $det->descarga_neto,
                'descarga_bruto'     => $det->descarga_bruto,
                'litros_c'           => $det->litros_c,
                'tad'                => $det->tad,
                'unidad'             => $det->unidad,
                'venta_momento'      => $det->venta_momento,
                'folio_merma'        => $det->folio_merma,
                'comentario'         => $det->comentario,
                'archivo'            => $archivo,
                'archivo_url'        => $archivo ? self::archivoUrl($archivo) : '',
                'archivo_existe'     => $archivo ? self::archivoExiste($archivo) : false,
            ], $calc);
        }

        $nombreEstacion = self::getEstacionNombre($estacion);

        $productos = self::getProductosEstacion((int)$cabecera->id_cuenta_litros);

        $productosEditar = $productos;
        foreach ($detalles as $det) {
            $p = trim((string)$det->producto);
            if ($p !== '' && !in_array($p, $productosEditar, true)) {
                $productosEditar[] = $p;
            }
        }

        return [
            'id'               => (int)$cabecera->id_cuenta_litros,
            'id_estacion'      => $estacion,
            'estacion'         => $nombreEstacion,
            'fecha'            => $cabecera->fecha instanceof \Carbon\Carbon
                ? $cabecera->fecha->format('Y-m-d')
                : (string)$cabecera->fecha,
            'fecha_larga'      => formatearFechaLarga(
                ($cabecera->fecha instanceof \Carbon\Carbon)
                    ? $cabecera->fecha->format('Y-m-d')
                    : (string)$cabecera->fecha
            ),
            'year'             => (int)$cabecera->year,
            'mes'              => (int)$cabecera->mes,
            'mes_nombre'       => self::nombreMes((int)$cabecera->mes),
            'estatus'          => $estatus,
            'estatus_texto'    => $estatus === 1 ? 'Finalizado' : 'En proceso',
            'modo'             => $modo,
            'editable'         => $modo === 'formato' && $permisos['puedeEditar'] && $estatus === 0,
            'puedeCrear'       => $permisos['puedeCrear'] && $estatus === 0,
            'puedeFinalizar'   => $permisos['puedeEditar'] && $estatus === 0 && !empty($filas),
            'puedeHabilitar'   => $permisos['puedeVer'] && !$permisos['puedeEditar'] && $estatus === 1,
            'puedeEditarFecha' => $permisos['puedeEditar'] && $estatus === 0,
            'puedeEliminarCab' => $permisos['puedeEliminar'],
            'puedeEliminarDet' => $permisos['puedeEditar'] && $estatus === 0,
            'embarques'        => self::EMBARQUES,
            'tads'             => self::getTads(false),
            'tads_editar'      => self::getTads(true),
            'transportes'      => self::getTransportes(),
            'unidades'         => self::getUnidades(),
            'productos'        => $productos,
            'productos_editar' => $productosEditar,
            'filas'            => $filas,
        ];
    }

    public static function crear(int $idEstacion, int $year, int $mes, ?string $fecha = null): int
    {
        $permisos = self::getPermisos();
        if (!$permisos['puedeCrear']) {
            throw new \DomainException('No tienes permiso para crear registros de cuenta litros.');
        }

        if (!self::estacionAutorizada($idEstacion)) {
            throw new \DomainException('La estación seleccionada no está autorizada para tu usuario.');
        }

        $fecha = $fecha ?: date('Y-m-d');

        $cabecera = CuentaLitros::create([
            'id_estacion' => $idEstacion,
            'fecha'       => $fecha,
            'year'        => $year,
            'mes'         => $mes,
            'estatus'     => 0,
        ]);

        return (int)$cabecera->id_cuenta_litros;
    }

    public static function agregarDetalle(int $idCuentaLitros, array $datos, array $archivo): bool
    {
        $cabecera = CuentaLitros::find($idCuentaLitros);
        if (!$cabecera) {
            throw new \RuntimeException('No se encontró el registro de cuenta litros.');
        }
        if ((int)$cabecera->estatus !== 0) {
            throw new \DomainException('El registro ya fue finalizado, no se pueden agregar descargas.');
        }
        if (!self::estacionAutorizada((int)$cabecera->id_estacion)) {
            throw new \DomainException('La estación del registro no está autorizada para tu usuario.');
        }

        $permisos = self::getPermisos();
        if (!$permisos['puedeEditar']) {
            throw new \DomainException('No tienes permiso para agregar descargas.');
        }

        $campos = self::validarCampos($datos);
        $embarque = $campos['embarque'];
        $transporte = ($embarque === 'Pemex') ? '' : $campos['transporte'];

        $archivoGuardado = self::guardarArchivo($archivo);
        if ($archivoGuardado === null) {
            throw new \InvalidArgumentException('La imagen de la descarga es obligatoria.');
        }

        self::upsertAuxiliares($campos['unidad'], $transporte);

        CuentaLitrosDetalle::create([
            'id_cuenta_litros' => $idCuentaLitros,
            'hora'            => $campos['hora'],
            'embarque'        => $embarque,
            'transporte'      => $transporte,
            'producto'        => $campos['producto'],
            'tanque'          => $campos['tanque'],
            'litros'          => $campos['litros'],
            'descarga_neto'   => $campos['descarga_neto'],
            'descarga_bruto'  => $campos['descarga_bruto'],
            'litros_c'        => $campos['litros_c'],
            'tad'             => $campos['tad'],
            'unidad'          => $campos['unidad'],
            'venta_momento'   => $campos['venta_momento'],
            'folio_merma'     => $campos['folio_merma'],
            'comentario'      => $campos['comentario'],
            'archivo'         => $archivoGuardado,
        ]);

        return true;
    }

    public static function editarDetalle(int $idDetalle, array $datos, array $archivo = []): bool
    {
        $detalle = CuentaLitrosDetalle::find($idDetalle);
        if (!$detalle) {
            throw new \RuntimeException('No se encontró el registro de descarga.');
        }

        $cabecera = CuentaLitros::find($detalle->id_cuenta_litros);
        if (!$cabecera || (int)$cabecera->estatus !== 0) {
            throw new \DomainException('El registro ya fue finalizado, no se puede editar.');
        }
        if (!self::estacionAutorizada((int)$cabecera->id_estacion)) {
            throw new \DomainException('La estación del registro no está autorizada para tu usuario.');
        }

        $permisos = self::getPermisos();
        if (!$permisos['puedeEditar']) {
            throw new \DomainException('No tienes permiso para editar descargas.');
        }

        $campos = self::validarCampos($datos);
        $embarque = $campos['embarque'];
        $transporte = ($embarque === 'Pemex') ? '' : $campos['transporte'];

        self::upsertAuxiliares($campos['unidad'], $transporte);

        $archivoGuardado = $detalle->archivo;
        if (!empty($archivo['tmp_name']) && (int)($archivo['error'] ?? 1) === UPLOAD_ERR_OK) {
            $nuevo = self::guardarArchivo($archivo);
            if ($nuevo !== null) {
                $archivoGuardado = $nuevo;
            }
        }

        $detalle->update([
            'hora'            => $campos['hora'],
            'embarque'        => $embarque,
            'transporte'      => $transporte,
            'tanque'          => $campos['tanque'],
            'producto'        => $campos['producto'],
            'litros'          => $campos['litros'],
            'descarga_neto'   => $campos['descarga_neto'],
            'descarga_bruto'  => $campos['descarga_bruto'],
            'litros_c'        => $campos['litros_c'],
            'tad'             => $campos['tad'],
            'unidad'          => $campos['unidad'],
            'venta_momento'   => $campos['venta_momento'],
            'folio_merma'     => $campos['folio_merma'],
            'comentario'      => $campos['comentario'],
            'archivo'         => $archivoGuardado,
        ]);

        return true;
    }

    public static function eliminarDetalle(int $idDetalle): bool
    {
        $detalle = CuentaLitrosDetalle::find($idDetalle);
        if (!$detalle) {
            throw new \RuntimeException('No se encontró el registro de descarga.');
        }

        $cabecera = CuentaLitros::find($detalle->id_cuenta_litros);
        if (!$cabecera || (int)$cabecera->estatus !== 0) {
            throw new \DomainException('El registro ya fue finalizado, no se puede eliminar la descarga.');
        }
        if (!self::estacionAutorizada((int)$cabecera->id_estacion)) {
            throw new \DomainException('La estación del registro no está autorizada para tu usuario.');
        }

        $permisos = self::getPermisos();
        if (!$permisos['puedeEditar']) {
            throw new \DomainException('No tienes permiso para eliminar descargas.');
        }

        return (bool)$detalle->delete();
    }

    public static function finalizar(int $id): bool
    {
        $cabecera = self::cambiarEstatus($id, 1);
        self::notificar('finalizar', self::snapshot($cabecera));
        return true;
    }

    public static function habilitar(int $id): bool
    {
        $cabecera = self::cambiarEstatus($id, 0);
        self::notificar('habilitar', self::snapshot($cabecera));
        return true;
    }

    public static function eliminar(int $id): bool
    {
        $cabecera = CuentaLitros::find($id);
        if (!$cabecera) {
            throw new \RuntimeException('No se encontró el registro de cuenta litros.');
        }
        if (!self::estacionAutorizada((int)$cabecera->id_estacion)) {
            throw new \DomainException('La estación del registro no está autorizada para tu usuario.');
        }

        $permisos = self::getPermisos();
        if (!$permisos['puedeEliminar']) {
            throw new \DomainException('No tienes permiso para eliminar el registro.');
        }

        $datos = self::snapshot($cabecera);

        $cabecera->delete();

        self::notificar('eliminar', $datos);

        return true;
    }

    public static function editarFecha(int $id, string $fecha): bool
    {
        $fecha = trim($fecha);
        if ($fecha === '' || !self::fechaValida($fecha)) {
            throw new \InvalidArgumentException('La fecha ingresada no es válida.');
        }

        $cabecera = CuentaLitros::find($id);
        if (!$cabecera) {
            throw new \RuntimeException('No se encontró el registro de cuenta litros.');
        }
        if ((int)$cabecera->estatus !== 0) {
            throw new \DomainException('El registro ya fue finalizado, no se puede cambiar la fecha.');
        }
        if (!self::estacionAutorizada((int)$cabecera->id_estacion)) {
            throw new \DomainException('La estación del registro no está autorizada para tu usuario.');
        }

        $permisos = self::getPermisos();
        if (!$permisos['puedeEditar']) {
            throw new \DomainException('No tienes permiso para cambiar la fecha.');
        }

        $cabecera->update(['fecha' => $fecha]);
        return true;
    }

    public static function notificar(string $accion, array $datos): void
    {
        if (!in_array($accion, ['finalizar', 'habilitar', 'eliminar'], true)) {
            return;
        }

        $sessionUsuario = Session::get('usuario') ?? [];
        $idUsuario = (int)($sessionUsuario['id'] ?? 0);
        $nombreUsuario = (string)($sessionUsuario['nombre'] ?? '');

        if ($nombreUsuario === '' && Auth::user()) {
            $nombreUsuario = (string)(Auth::user()->nombre ?? '');
        }

        $mensaje = self::mensajeTelegram(
            (int)$datos['id_estacion'],
            (string)$datos['fecha_larga'],
            (int)$datos['year'],
            (int)$datos['mes'],
            $nombreUsuario,
            $accion
        );

        try {
            TelegramService::notificar((int)$datos['id_estacion'], $idUsuario, $mensaje);
        } catch (\Throwable $e) {
            error_log('Error en CuentaLitrosService::notificar: ' . $e->getMessage());
        }
    }

    public static function guardarArchivo(array $file): ?string
    {
        if (!isset($file['tmp_name']) || !isset($file['error'])) {
            return null;
        }
        if ((int)$file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return null;
        }
        if ((int)($file['size'] ?? 0) <= 0) {
            return null;
        }

        $dir = PUBLIC_PATH . '/uploads/archivos/' . self::CARPETA_ARCHIVOS . '/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $nombre = uniqid('cl_', false) . '-' . preg_replace('/[^A-Za-z0-9.\-_]/', '_', basename($file['name']));

        if (!move_uploaded_file($file['tmp_name'], $dir . $nombre)) {
            return null;
        }

        return $nombre;
    }

    public static function archivoUrl(string $nombre): string
    {
        return '/uploads/archivos/' . self::CARPETA_ARCHIVOS . '/' . $nombre;
    }

    public static function archivoExiste(string $nombre): bool
    {
        return is_file(PUBLIC_PATH . '/uploads/archivos/' . self::CARPETA_ARCHIVOS . '/' . $nombre);
    }

    public static function calcular(array $d, int $idCabecera): array
    {
        $litros      = (float)($d['litros'] ?? 0);
        $descNeto    = (float)($d['descarga_neto'] ?? 0);
        $descBruto   = (float)($d['descarga_bruto'] ?? 0);
        $litrosC     = (float)($d['litros_c'] ?? 0);
        $ventaMomento= (float)($d['venta_momento'] ?? 0);

        $resNeto  = ($descNeto - $litros) + $ventaMomento;
        $resBruto = ($descBruto - $litros) + $ventaMomento;
        $resLtsC  = $litrosC - $litros;

        $porcentajeBruto = 0;
        $porcentajeLtsC  = 0;

        if ($resNeto != 0.0) {
            $porcentajeBruto = ($resBruto * 100) / -$resNeto;
            $porcentajeLtsC = ($resLtsC * 100) / ($idCabecera === self::CABECERA_ESPECIAL_2720 ? $resNeto : -$resNeto);
        }

        $toleranciaRaw    = ($litros * 0.55) / 100;
        $tolerancia       = round($toleranciaRaw);
        $reclamomermaRaw  = $resLtsC + $tolerancia;
        $reclamomerma     = round($reclamomermaRaw);
        $mermaValue       = $reclamomermaRaw > 0 ? 0 : $reclamomerma;

        return [
            'res_neto'           => number_format($resNeto, 2),
            'res_bruto'          => number_format($resBruto, 2),
            'res_lts_c'          => number_format($resLtsC, 2),
            'porcentaje_neto'    => '100',
            'porcentaje_bruto'   => number_format($porcentajeBruto, 0),
            'porcentaje_lts_c'   => number_format($porcentajeLtsC, 0),
            'tolerancia'         => number_format($tolerancia, 0),
            'reclamomerma'       => number_format($reclamomerma, 0),
            'merma_value'        => number_format($mermaValue, 0),
            'merma_es_cero'      => $reclamomermaRaw > 0,
        ];
    }

    private static function cambiarEstatus(int $id, int $nuevoEstatus): CuentaLitros
    {
        $cabecera = CuentaLitros::find($id);
        if (!$cabecera) {
            throw new \RuntimeException('No se encontró el registro de cuenta litros.');
        }
        if (!self::estacionAutorizada((int)$cabecera->id_estacion)) {
            throw new \DomainException('La estación del registro no está autorizada para tu usuario.');
        }

        $permisos = self::getPermisos();

        if ($nuevoEstatus === 1) {
            if (!$permisos['puedeEditar']) {
                throw new \DomainException('No tienes permiso para finalizar el registro.');
            }
            if ((int)$cabecera->estatus === 1) {
                throw new \DomainException('El registro ya fue finalizado.');
            }
        } else {
            if (!$permisos['puedeVer'] || $permisos['puedeEditar']) {
                throw new \DomainException('No tienes permiso para habilitar el registro.');
            }
            if ((int)$cabecera->estatus === 0) {
                throw new \DomainException('El registro ya está habilitado.');
            }
        }

        $cabecera->update(['estatus' => $nuevoEstatus]);
        return $cabecera;
    }

    private static function snapshot(CuentaLitros $cabecera): array
    {
        $fecha = ($cabecera->fecha instanceof \Carbon\Carbon)
            ? $cabecera->fecha->format('Y-m-d')
            : (string)$cabecera->fecha;

        return [
            'id_estacion' => (int)$cabecera->id_estacion,
            'fecha_larga' => formatearFechaLarga($fecha),
            'year'        => (int)$cabecera->year,
            'mes'         => (int)$cabecera->mes,
        ];
    }

private static function mensajeTelegram(
        int $idEstacion,
        string $fechaLarga,
        int $year,
        int $mes,
        string $nombreUsuario,
        string $accion
    ): string {
        
        $verbos = [
            'finalizar' => ['✅', 'ha agregado un nuevo registro en'],
            'habilitar' => ['🔓', 'ha habilitado el registro en'],
            'eliminar'  => ['🗑', 'ha eliminado el registro en'],
        ];

        [$emoji, $verbo] = $verbos[$accion];
        $nombreEstacion = self::getEstacionNombre($idEstacion);
        $nombreMesAnio = self::nombreMes($mes) . ' ' . $year;

        return $emoji . ' Se ' . $verbo . ' el <b>Cuenta Litros</b> del módulo de Importación, correspondiente al periodo de <b>' . $nombreMesAnio . '</b>:' . PHP_EOL . PHP_EOL
            . '🗓️ <b>Fecha:</b> ' . $fechaLarga . PHP_EOL
            . '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL
            . '⛽ <b>Estación:</b> ' . $nombreEstacion;
    }

    private static function nombreMes(int $mes): string
    {
        return nombremes(str_pad((string)$mes, 2, '0', STR_PAD_LEFT));
    }

    private static function validarCampos(array $datos): array
    {
        $obligatorios = [
            'hora'            => 'Hora',
            'embarque'        => 'Tipo de embarque',
            'tanque'          => 'Tanque',
            'producto'        => 'Producto',
            'tad'             => 'TAD',
            'unidad'          => 'Unidad',
            'litros'          => 'Factura (litros)',
            'descarga_neto'   => 'Tirilla de descarga neto',
            'descarga_bruto'  => 'Tirilla de descarga bruto',
            'litros_c'        => 'Cuenta litros a 20 °C',
            'venta_momento'   => 'Venta al momento',
        ];

        foreach ($obligatorios as $campo => $etiqueta) {
            if (!array_key_exists($campo, $datos) || trim((string)$datos[$campo]) === '') {
                throw new \InvalidArgumentException('El campo "' . $etiqueta . '" es obligatorio.');
            }
        }

        $embarque = trim((string)$datos['embarque']);
        if (!in_array($embarque, self::EMBARQUES, true)) {
            throw new \DomainException('El tipo de embarque no es válido.');
        }

        $transporte = trim((string)($datos['transporte'] ?? ''));
        if ($embarque !== 'Pemex' && $transporte === '') {
            throw new \InvalidArgumentException(
                'El campo "Nombre del transporte" es obligatorio para embarque tipo ' . $embarque . '.'
            );
        }

        $numericos = ['tanque', 'litros', 'descarga_neto', 'descarga_bruto', 'litros_c', 'venta_momento'];
        foreach ($numericos as $campo) {
            if (!is_numeric($datos[$campo])) {
                throw new \InvalidArgumentException(
                    'El campo "' . ($obligatorios[$campo] ?? $campo) . '" debe ser numérico.'
                );
            }
        }

        $folioMerma = $datos['folio_merma'] ?? 0;
        if ($folioMerma === '' || $folioMerma === null) {
            $folioMerma = 0;
        }
        if (!is_numeric($folioMerma)) {
            throw new \InvalidArgumentException('El campo "Folio de merma" debe ser numérico.');
        }

        $comentario = trim((string)($datos['comentario'] ?? ''));
        if ($comentario === '') {
            $comentario = 'Sin comentarios.';
        }

        return [
            'hora'            => trim((string)$datos['hora']),
            'embarque'        => $embarque,
            'transporte'      => $transporte,
            'tanque'          => (int)$datos['tanque'],
            'producto'        => trim((string)$datos['producto']),
            'tad'             => trim((string)$datos['tad']),
            'unidad'          => trim((string)$datos['unidad']),
            'litros'          => (int)$datos['litros'],
            'descarga_neto'   => (int)$datos['descarga_neto'],
            'descarga_bruto'  => (int)$datos['descarga_bruto'],
            'litros_c'        => (int)$datos['litros_c'],
            'venta_momento'   => (int)$datos['venta_momento'],
            'folio_merma'     => (int)$folioMerma,
            'comentario'      => $comentario,
        ];
    }

    private static function upsertAuxiliares(string $unidad, string $transporte): void
    {
        $unidad = trim($unidad);
        if ($unidad !== '' && !UnidadesTransporte::where('no_unidad', $unidad)->exists()) {
            UnidadesTransporte::create(['no_unidad' => $unidad, 'estado' => 0]);
        }

        $transporte = trim($transporte);
        if ($transporte !== '' && !ListaTransportes::where('nombre_transporte', $transporte)->exists()) {
            ListaTransportes::create(['nombre_transporte' => $transporte, 'estado' => 0]);
        }
    }

    private static function fechaValida(string $fecha): bool
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $fecha);
        return $dt !== false && $dt->format('Y-m-d') === $fecha;
    }
}
