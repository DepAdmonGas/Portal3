<?php

namespace App\Services;

use App\Models\Operativo\CorteMes;
use App\Models\Operativo\CorteYear;
use App\Models\Operativo\SolicitudCheque;
use App\Models\Operativo\SolicitudChequeComentario;
use App\Models\Operativo\SolicitudChequeDocumento;
use App\Models\Operativo\SolicitudChequeFirma;
use App\Models\Operativo\SolicitudChequeToken;
use App\Models\Operativo\SolicitudChequeTelcel;
use App\Models\Operativo\FacturaTelcel;
use App\Models\Operativo\FacturaTelcelComentario;
use App\Models\Operativo\Directorio;
use App\Models\Estacion;
use App\Models\Usuario;
use App\Models\Puesto;
use App\Core\Auth;
use App\Core\Session;
use App\Services\TelegramService;
use App\Services\ModuleStationService;
use Illuminate\Support\Carbon;

class SolicitudChequeService
{
const DOCUMENT_TYPES = [
'PRESUPUESTO',
'FACTURA PDF',
'FACTURA XML',
'CARATULA BANCARIA',
'CONSTANCIA DE SITUACION',
'PREFACTURA',
'ORDEN DE SERVICIO',
'ORDEN DE COMPRA',
'ORDEN DE MANTENIMIENTO',
'PÓLIZA DE GARANTÍA',
'PRORRATEO',
'REEMBOLSO CAJA CHICA',
'COTIZACIÓN',
'NOTA DE CREDITO PDF',
'NOTA DE CREDITO XML',
'CONTRATO',
'COMPLEMENTO DE PAGO PDF',
'COMPLEMENTO DE PAGO XML',
'OPINIÓN DE CUMPLIMIENTO',
];

const DEPARTMENTS = [
4 => 'Comercializadora',
5 => 'Gestoria',
18 => 'Quitarga',
19 => 'Operación servicio y mantenimiento de personal',
23 => 'BANCAMIFEL, SOCIEDAD ANÓNIMA, FIDEICOMISO 2176/2016',
];

public static function getPermisos(): array
{
$usuario = Auth::user();
$sessionUsuario = Session::get('usuario');

$multiEstacion = $sessionUsuario['multiestacion'] ?? false;
if (ModuleStationService::isPuesto6Estacion8()) {
$multiEstacion = false;
}
$idUsuario = $sessionUsuario['id'] ?? 0;
$idEstacion = $sessionUsuario['id_estacion'] ?? 0;
$idPuesto = $usuario->id_puesto ?? 0;
$tipoPuesto = $usuario->puesto->tipo_puesto ?? '';
$nombrePuesto = $tipoPuesto;
$esGestoria = $tipoPuesto === 'Gestoria' || $nombrePuesto === 'Gestoria' || $idPuesto === 5;

// Puesto 6 + Estación 8: never gestoria
if (ModuleStationService::isPuesto6Estacion8()) {
$esGestoria = false;
}
$esDireccionOperaciones = $tipoPuesto === 'Dirección de operaciones' || $idPuesto === 13;
$esContabilidad = $tipoPuesto === 'Contabilidad' || $idPuesto === 12;
$esComercializadora = $tipoPuesto === 'Comercializadora' || $idPuesto === 4;
$esEncargado = $tipoPuesto === 'Encargado';
$esAsistente = $tipoPuesto === 'Asistente Administrativo';
$esUser30 = $idUsuario === 30;

$puedeCrear = $esEncargado || $esAsistente || $esDireccionOperaciones || $esGestoria || $esComercializadora;
$puedeEditar = $esEncargado || $esAsistente || $esDireccionOperaciones || $esGestoria;
$puedeEliminar = ($esEncargado || $esAsistente || $esDireccionOperaciones) && !$esGestoria;
$puedeFirmarVOBO = $esUser30 || $esGestoria;

// Align with DB-configured permissions when available
$permisosDb = ModuloDptoOperativoService::permisosSesion('corporativo');
$dbTieneConfig = !empty($permisosDb);
if ($dbTieneConfig) {
$puedeCrear = !empty($permisosDb['crear']);
$puedeEditar = !empty($permisosDb['editar']);
$puedeEliminar = !empty($permisosDb['eliminar']);
}

return [
'multiestacion' => $multiEstacion,
'id_usuario' => $idUsuario,
'id_estacion' => $idEstacion,
'id_puesto' => $idPuesto,
'tipo_puesto' => $tipoPuesto,
'nombre_puesto' => $nombrePuesto,
'es_gestoria' => $esGestoria,
'es_direccion_operaciones' => $esDireccionOperaciones,
'es_contabilidad' => $esContabilidad,
'es_comercializadora' => $esComercializadora,
'es_encargado' => $esEncargado,
'es_asistente' => $esAsistente,
'es_user_30' => $esUser30,
'puede_crear' => $puedeCrear,
'puede_editar' => $puedeEditar,
'puede_eliminar' => $puedeEliminar,
'puede_firmar_vobo' => $puedeFirmarVOBO,
'puede_ver_comentarios' => true,
'puede_agregar_comentarios' => $puedeCrear,
'puede_agregar_documentos' => $puedeCrear,
'puede_gestionar_pagos' => $puedeCrear || $esContabilidad,
'puede_gestionar_telcel' => $puedeCrear || $esContabilidad,
'puede_exportar' => $puedeCrear || $esContabilidad,
];
}

/**
* Station IDs the current user is permitted to see.
* Matches getOpcionesSelector() visibility rules.
*/
public static function getAllowedStationIds(): array
{
// Puesto 6 + Estación 8: OVERRIDE absoluto via regla centralizada
if (ModuleStationService::isPuesto6Estacion8()) {
$stations = ModuleStationService::getAvailableStations('solicitud-cheques');
return array_column($stations, 'id');
}

$permisos = self::getPermisos();
$idUsuario = $permisos['id_usuario'];
$nombrePuesto = $permisos['nombre_puesto'];

if ($idUsuario == 419) {
return [14];
}

$stations = Estacion::where('numlista', '<=', 8)
->orderBy('numlista')
->get(['id', 'numlista']);

$allowed = [];
foreach ($stations as $s) {
$mostrar = false;

if ($nombrePuesto == 'Contabilidad') {
$mostrar = in_array($s->id, [1, 2, 3, 4, 5, 14]);
} elseif ($nombrePuesto == 'Comercializadora') {
$mostrar = in_array($s->id, [6, 7]);
} else {
$mostrar = true;
}
if ($mostrar) $allowed[] = $s->id;
}
return $allowed;
}

/**
* Department (puesto) IDs the current user is permitted to see.
*/
public static function getAllowedDeptIds(): array
{
// Puesto 6 + Estación 8: OVERRIDE absoluto via regla centralizada - no departments
if (ModuleStationService::isPuesto6Estacion8()) {
return [];
}

$permisos = self::getPermisos();
$nombrePuesto = $permisos['nombre_puesto'];
$idUsuario = $permisos['id_usuario'];
$deptIds = array_keys(self::DEPARTMENTS);

$allowed = [];
foreach ($deptIds as $did) {
$mostrar = true;

// Gestoria (depto 5): hidden from Dirección de operaciones and Dirección de operaciones servicio social, unless user 344
if ($did == 5) {
$mostrar = ($nombrePuesto != 'Dirección de operaciones' && $nombrePuesto != 'Dirección de operaciones servicio social');
if ($idUsuario == 344) $mostrar = true;
}

// Comercializadora (depto 4) and Quitarga (depto 18): hidden from Contabilidad and Dirección de operaciones servicio social
if (in_array($did, [4, 18])) {
$mostrar = ($nombrePuesto != 'Contabilidad' && $nombrePuesto != 'Dirección de operaciones servicio social');
}

// Operación (depto 19) and Banca (depto 23): hidden from Comercializadora
if (in_array($did, [19, 23])) {
$mostrar = ($nombrePuesto != 'Comercializadora');
}

if ($mostrar) $allowed[] = $did;
}
return $allowed;
}

private static $mesIdCache = [];

public static function getMesId(int $idEstacion, int $idYear, int $idMes): ?int
{
$key = $idEstacion . '_' . $idYear . '_' . $idMes;
if (array_key_exists($key, self::$mesIdCache)) {
return self::$mesIdCache[$key];
}
$corteMes = CorteMes::whereHas('year', function ($q) use ($idEstacion, $idYear) {
$q->where('id_estacion', $idEstacion)->where('year', $idYear);
})->where('mes', $idMes)->first();

$idReporte = $corteMes?->id;
self::$mesIdCache[$key] = $idReporte;
return $idReporte;
}

private static function ensureMesId(int $idEstacion, int $idYear, int $idMes): int
{
$idReporte = self::getMesId($idEstacion, $idYear, $idMes);
if ($idReporte) return $idReporte;

$corteYear = CorteYear::firstOrCreate(
['id_estacion' => $idEstacion, 'year' => $idYear],
['id_estacion' => $idEstacion, 'year' => $idYear]
);

$corteMes = CorteMes::create(['id_year' => $corteYear->id, 'mes' => $idMes]);
$key = $idEstacion . '_' . $idYear . '_' . $idMes;
self::$mesIdCache[$key] = $corteMes->id;
return $corteMes->id;
}

public static function getData(int $idYear, int $idMes, ?int $estacionFilter = null, ?int $deptoFilter = null): array
{
$permisos = self::getPermisos();
$idUsuario = $permisos['id_usuario'];
$idEstacion = $permisos['id_estacion'];
$idPuesto = $permisos['id_puesto'];
$esGestoria = $permisos['es_gestoria'];
$multiEstacion = $permisos['multiestacion'];

$query = SolicitudCheque::where('id_year', $idYear)
->where('id_mes', $idMes);

// Safety: treat 0 as null (no filter)
if ($estacionFilter !== null && $estacionFilter === 0) $estacionFilter = null;
if ($deptoFilter !== null && $deptoFilter === 0) $deptoFilter = null;

// Puesto 6 + Estación 8: force no filters so the multiestacion guard applies
if (ModuleStationService::isPuesto6Estacion8()) {
$estacionFilter = null;
$deptoFilter = null;
} elseif ($esGestoria) {
if ($estacionFilter === null) $estacionFilter = 8;
if ($deptoFilter === null) $deptoFilter = 5;
}

if ($estacionFilter !== null || $deptoFilter !== null) {
if ($estacionFilter !== null && $estacionFilter > 0) {
$query->where('id_estacion', $estacionFilter);
}
if ($deptoFilter !== null && $deptoFilter > 0) {
$query->where('depto', $deptoFilter);
}
} elseif (!$multiEstacion) {
if ($idEstacion === 14) {
$query->where(function ($q) use ($idEstacion) {
$q->where('id_estacion', $idEstacion)
->orWhere('depto', 23);
});
} else {
$query->where('id_estacion', $idEstacion);
}
} else {
$stationIds = self::getAllowedStationIds();
$deptIds = self::getAllowedDeptIds();
if (!empty($stationIds) || !empty($deptIds)) {
$query->where(function ($q) use ($stationIds, $deptIds) {
if (!empty($stationIds)) {
$q->whereIn('id_estacion', $stationIds);
}
if (!empty($deptIds)) {
$orType = empty($stationIds) ? 'where' : 'orWhere';
$q->$orType(function ($q3) use ($deptIds) {
$q3->where('id_estacion', 8)
->whereIn('depto', $deptIds);
});
}
});
}
}

$rows = $query->orderBy('fecha', 'desc')
->orderBy('id', 'desc')
->get();

$now = Carbon::now();
$esMesActual = ($idYear == $now->year && $idMes == $now->month);

$result = [];
foreach ($rows as $row) {
$numCom = SolicitudChequeComentario::where('id_solicitud', $row->id)->count();
$numFirmas = SolicitudChequeFirma::where('id_solicitud', $row->id)->count();
$firmaB = SolicitudChequeFirma::where('id_solicitud', $row->id)
->where('tipo_firma', 'B')->count();
$firmaC = SolicitudChequeFirma::where('id_solicitud', $row->id)
->where('tipo_firma', 'C')->count();
$pagoDoc = SolicitudChequeDocumento::where('id_solicitud', $row->id)
->where('nombre', 'PAGO')->count();
$firmaA = SolicitudChequeFirma::where('id_solicitud', $row->id)
->where('tipo_firma', 'A')->count();

$telcelCount = SolicitudChequeTelcel::where('id_year', $row->id_year)
->where('id_mes', $row->id_mes)
->where('id_estacion', $row->id_estacion)
->count();
$telcelPagoCount = SolicitudChequeTelcel::where('id_year', $row->id_year)
->where('id_mes', $row->id_mes)
->where('id_estacion', $row->id_estacion)
->whereNotNull('c_pago')
->where('c_pago', '!=', '')
->count();

$estacionNombre = '';
if ($row->id_estacion == 8) {
$deptoNombre = self::DEPARTMENTS[$row->depto] ?? 'Desconocido';
$estacionNombre = $deptoNombre;
} else {
$estacion = Estacion::find($row->id_estacion);
$estacionNombre = $estacion ? $estacion->nombre : '';
}

$expiredPending = ($row->status == 0) && ($idYear < $now->year || ($idYear == $now->year && $idMes < $now->month));
$statusLabel = self::getStatusLabel($row->status, $expiredPending);
$rowColor = self::getRowColor((int)$row->status, $pagoDoc > 0, $idYear, $idMes);

$statusVal = (int)$row->status;
$puedePdf = $statusVal === 2 || ($statusVal === 1 && $esMesActual);
$puedeArchivos = $statusVal === 2 || $esMesActual;
$puedeEditarRow = $statusVal === 0 && $esMesActual;
$puedeEliminarRow = $statusVal === 0 && $esMesActual;
$puedePagos = $statusVal === 2 || ($statusVal === 1 && $esMesActual);
$puedeTelcel = $statusVal === 2;
$puedeFirmar = ($permisos['puede_firmar_vobo'] && $statusVal === 0 && $esMesActual)
|| (in_array($idUsuario, [2, 22]) && $statusVal === 1 && $esMesActual);

$telcelStatus = 'sin_factura';
$telcelText = 'Sin factura';
$telcelClass = 'bg-danger';
if ($telcelCount > 0) {
if ($telcelPagoCount > 0 && $telcelPagoCount >= $telcelCount) {
$telcelStatus = 'pagado';
$telcelText = 'Pagado';
$telcelClass = 'bg-success';
} else {
$telcelStatus = 'factura_disponible';
$telcelText = 'Factura disponible';
$telcelClass = 'bg-warning text-white';
}
}

$result[] = [
'id' => $row->id,
'id_year' => $row->id_year,
'id_mes' => $row->id_mes,
'id_estacion' => $row->id_estacion,
'depto' => $row->depto,
'fecha' => formatearFecha($row->fecha ?? ''),
'fecha_raw' => $row->fecha ? $row->fecha->format('Y-m-d') : '',
'hora' => $row->hora ? $row->hora->format('H:i:s') : '',
'beneficiario' => $row->beneficiario ?? '',
'monto' => (float)($row->monto ?? 0),
'moneda' => $row->moneda ?? '',
'no_factura' => $row->no_factura ?? '',
'email' => $row->email ?? '',
'concepto' => $row->concepto ?? '',
'solicitante' => $row->solicitante ?? '',
'telefono' => $row->telefono ?? '',
'cfdi' => $row->cfdi ?? '',
'metodo_pago' => $row->metodo_pago ?? '',
'forma_pago' => $row->forma_pago ?? '',
'banco' => $row->banco ?? '',
'no_cuenta' => $row->no_cuenta ?? '',
'cuenta_clabe' => $row->cuenta_clabe ?? '',
'referencia' => $row->referencia ?? '',
'observaciones' => $row->observaciones ?? '',
'razonsocial' => $row->razonsocial ?? '',
'status' => $statusVal,
'status_label' => $statusLabel,
'estacion_nombre' => $estacionNombre,
'telcel_status' => $telcelStatus,
'telcel_text' => $telcelText,
'telcel_class' => $telcelClass,
'num_comentarios' => $numCom,
'num_firmas' => $numFirmas,
'tiene_firma_a' => $firmaA > 0,
'tiene_firma_b' => $firmaB > 0,
'tiene_firma_c' => $firmaC > 0,
'tiene_pago' => $pagoDoc > 0,
'row_color' => $rowColor,
'es_mes_actual' => $esMesActual,
'puede_pdf' => $puedePdf,
'puede_archivos' => $puedeArchivos,
'puede_editar_row' => $puedeEditarRow,
'puede_eliminar_row' => $puedeEliminarRow,
'puede_pagos' => $puedePagos,
'puede_telcel' => $puedeTelcel,
'puede_firmar' => $puedeFirmar,
];
}

return $result;
}

private static function getRowColor(int $status, bool $tienePago, int $idYear, int $idMes): string
{
$now = Carbon::now();
$esMesActual = ($idYear == $now->year && $idMes == $now->month);

if ($status == 2 && $tienePago) {
return '#b0f2c2';
}

if ($esMesActual && in_array($status, [0, 1])) {
return '#fcfcda';
}

if (!$esMesActual) {
return '#ffb6af';
}

return '';
}

public static function getStatusLabel(int $status, bool $periodoVencido = false): string
{
if ($status === 0 && $periodoVencido) {
return 'No autorizado';
}
return match ($status) {
0 => 'Pendiente',
1 => 'En proceso',
2 => 'Autorizado',
default => 'Desconocido',
};
}

public static function getDetalle(int $id): ?array
{
$row = SolicitudCheque::find($id);
if (!$row) return null;

$firmas = SolicitudChequeFirma::with('usuario:id,nombre')
->where('id_solicitud', $id)
->orderBy('fecha', 'asc')
->get()
->toArray();

$documentos = SolicitudChequeDocumento::where('id_solicitud', $id)
->where('nombre', '!=', 'PAGO')
->orderBy('id', 'asc')
->get()
->toArray();

// Map eager-loaded usuario data into firmas
foreach ($firmas as &$f) {
$f['usuario_nombre'] = $f['usuario']['nombre'] ?? '#' . $f['id_usuario'];
unset($f['usuario']);
$tipo = $f['tipo_firma'] ?? '';
$fechaStr = $f['fecha'] ?? '';
$fechaF = !empty($fechaStr) ? substr($fechaStr, 0, 10) : '';
$horaF = !empty($fechaStr) && strlen($fechaStr) > 11 ? date("g:i a", strtotime(substr($fechaStr, 11))) : '';
if ($tipo === 'A') {
$f['tipo_label'] = 'NOMBRE Y FIRMA DEL ENCARGADO';
$f['firma_img_url'] = '/uploads/firmas/solicitud-cheque/' . ($f['firma'] ?? '');
$f['firma_texto'] = null;
} elseif ($tipo === 'B') {
$f['tipo_label'] = 'NOMBRE Y FIRMA DE VOBO';
$f['firma_img_url'] = null;
$f['firma_texto'] = '<b>Fecha: ' . formatearFecha($fechaF) . ', ' . $horaF . '</b> <br> La solicitud de cheque se firmó por un medio electrónico.';
} elseif ($tipo === 'C') {
$f['tipo_label'] = 'NOMBRE Y FIRMA DE AUTORIZACIÓN';
$f['firma_img_url'] = null;
$f['firma_texto'] = '<b>Fecha: ' . formatearFecha($fechaF) . ', ' . $horaF . '</b> <br> La solicitud de cheque se firmó por un medio electrónico.';
}
}
unset($f);

$status = (int)$row->status;
$periodoVencido = ($row->id_year < Carbon::now()->year) || ($row->id_year == Carbon::now()->year && $row->id_mes < Carbon::now()->month);
$tieneFirmaA = false;
$tieneFirmaB = false;
$tieneFirmaC = false;
foreach ($firmas as $f) {
if ($f['tipo_firma'] === 'A') $tieneFirmaA = true;
if ($f['tipo_firma'] === 'B') $tieneFirmaB = true;
if ($f['tipo_firma'] === 'C') $tieneFirmaC = true;
}

$firmasPendientes = [];
if ($status === 0) {
if (!$tieneFirmaB) $firmasPendientes[] = 'VoBo';
if (!$tieneFirmaC) $firmasPendientes[] = 'Autorización';
} elseif ($status === 1) {
if (!$tieneFirmaC) $firmasPendientes[] = 'Autorización';
}

$estacionNombre = '';
if ($row->id_estacion == 8) {
$deptoNombre = self::DEPARTMENTS[$row->depto] ?? 'Desconocido';
$estacionNombre = $deptoNombre;
} else {
$estacionModel = Estacion::find($row->id_estacion);
$estacionNombre = $estacionModel ? $estacionModel->nombre : '';
}

$importeLetra = self::numeroALetras((float)($row->monto ?? 0), $row->moneda ?? '');

return [
'id' => $row->id,
'id_year' => $row->id_year,
'id_mes' => $row->id_mes,
'id_estacion' => $row->id_estacion,
'estacion_nombre' => $estacionNombre,
'depto' => $row->depto,
'fecha' => $row->fecha ? $row->fecha->format('Y-m-d') : '',
'fecha_formateada' => formatearFecha($row->fecha ?? ''),
'hora' => $row->hora ? $row->hora->format('H:i:s') : '',
'beneficiario' => $row->beneficiario ?? '',
'monto' => (float)($row->monto ?? 0),
'moneda' => $row->moneda ?? '',
'importe_letra' => $importeLetra,
'no_factura' => $row->no_factura ?? '',
'email' => $row->email ?? '',
'concepto' => $row->concepto ?? '',
'solicitante' => $row->solicitante ?? '',
'telefono' => $row->telefono ?? '',
'cfdi' => $row->cfdi ?? '',
'metodo_pago' => $row->metodo_pago ?? '',
'forma_pago' => $row->forma_pago ?? '',
'banco' => $row->banco ?? '',
'no_cuenta' => $row->no_cuenta ?? '',
'cuenta_clabe' => $row->cuenta_clabe ?? '',
'referencia' => $row->referencia ?? '',
'observaciones' => $row->observaciones ?? '',
'razonsocial' => $row->razonsocial ?? '',
'status' => $status,
'status_label' => self::getStatusLabel($status, $periodoVencido),
'periodo_vencido' => $periodoVencido,
'firmas' => $firmas,
'tiene_firma_a' => $tieneFirmaA,
'tiene_firma_b' => $tieneFirmaB,
'tiene_firma_c' => $tieneFirmaC,
'firmas_pendientes' => $firmasPendientes,
'documentos' => $documentos,
];
}

public static function store(array $data, array $files): array
{
$usuario = Auth::user();
if (!$usuario) {
return ['success' => false, 'message' => 'Sesión no válida'];
}

$idEstacion = (int)($data['id_estacion'] ?? 0);
if (!$idEstacion) {
$sessionUsuario = Session::get('usuario');
$idEstacion = $sessionUsuario['id_estacion'] ?? 0;
}
$sessionUsuario = Session::get('usuario');
$idPuesto = $usuario->id_puesto ?? 0;
$idUsuario = $sessionUsuario['id'] ?? 0;
$nameEstacion = $sessionUsuario['nomestacion'] ?? '';

$idYear = (int)($data['id_year'] ?? 0);
$idMes = (int)($data['id_mes'] ?? 0);

if (!$idYear || !$idMes) {
return ['success' => false, 'message' => 'Año/mes no válido'];
}

$fecha = $data['fecha'] ?? '';
$beneficiario = $data['beneficiario'] ?? '';
$monto = (float)($data['monto'] ?? 0);
$moneda = $data['moneda'] ?? 'MXN';
$noFactura = $data['no_factura'] ?? '';
$correo = $data['email'] ?? '';
$concepto = $data['concepto'] ?? '';
$solicitante = $data['solicitante'] ?? '';
$telefono = $data['telefono'] ?? '';
$cfdi = $data['cfdi'] ?? '';
$metodoPago = $data['metodo_pago'] ?? '';
$formaPago = $data['forma_pago'] ?? '';
$banco = $data['banco'] ?? '';
$noCuenta = $data['no_cuenta'] ?? '';
$cuentaClabe = $data['cuenta_clabe'] ?? '';
$referencia = $data['referencia'] ?? '';
$observaciones = $data['observaciones'] ?? '';
$razonSocial = $data['razonsocial'] ?? '';
$depto = $data['depto'] ?? '';

$valIdEstacion = $idEstacion;
$valDepto = $idPuesto;

$deptIds = array_keys(self::DEPARTMENTS);
if (in_array((int)$depto, $deptIds)) {
$valIdEstacion = 8;
$valDepto = (int)$depto;
} elseif ((int)$depto > 0) {
$valIdEstacion = 8;
$valDepto = (int)$depto;
}

$id = self::nextId();
$hora = Carbon::now()->format('H:i:s');

try {
$solicitud = SolicitudCheque::create([
'id' => $id,
'id_year' => $idYear,
'id_mes' => $idMes,
'id_estacion' => $valIdEstacion,
'fecha' => $fecha,
'hora' => $hora,
'beneficiario' => $beneficiario,
'monto' => $monto,
'moneda' => $moneda,
'no_factura' => $noFactura,
'email' => $correo,
'concepto' => $concepto,
'solicitante' => $solicitante,
'telefono' => $telefono,
'cfdi' => $cfdi,
'metodo_pago' => $metodoPago,
'forma_pago' => $formaPago,
'banco' => $banco,
'no_cuenta' => $noCuenta,
'cuenta_clabe' => $cuentaClabe,
'referencia' => $referencia,
'observaciones' => $observaciones,
'depto' => $valDepto,
'razonsocial' => $razonSocial,
'status' => 0,
]);

if (!empty($data['firma_base64'])) {
self::guardarFirmaImagen($id, $idUsuario, 'A', $data['firma_base64']);
}

self::procesarDocumentosCrear($id, $idUsuario, $files);

self::notificarCreacion($id, $idUsuario, $nameEstacion, $valIdEstacion, $valDepto, $fecha, $monto, $moneda, $concepto, $beneficiario, $razonSocial, $solicitante, $noFactura, $idYear, $idMes);

return ['success' => true, 'message' => 'Solicitud creada exitosamente.', 'id' => $id];
} catch (\Throwable $e) {
return ['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()];
}
}

public static function update(int $id, array $data, array $files): array
{
$solicitud = SolicitudCheque::find($id);
if (!$solicitud) {
return ['success' => false, 'message' => 'Solicitud no encontrada'];
}

$usuario = Auth::user();
if (!$usuario) {
return ['success' => false, 'message' => 'Sesión no válida'];
}

$sessionUsuario = Session::get('usuario');
$idUsuario = $sessionUsuario['id'] ?? 0;
$nameEstacion = $sessionUsuario['nomestacion'] ?? '';

$solicitud->fecha = $data['fecha'] ?? $solicitud->fecha;
$solicitud->beneficiario = $data['beneficiario'] ?? $solicitud->beneficiario;
$solicitud->monto = (float)($data['monto'] ?? $solicitud->monto);
$solicitud->moneda = $data['moneda'] ?? $solicitud->moneda;
$solicitud->no_factura = $data['no_factura'] ?? $solicitud->no_factura;
$solicitud->email = $data['email'] ?? $solicitud->email;
$solicitud->concepto = $data['concepto'] ?? $solicitud->concepto;
$solicitud->solicitante = $data['solicitante'] ?? $solicitud->solicitante;
$solicitud->telefono = $data['telefono'] ?? $solicitud->telefono;
$solicitud->cfdi = $data['cfdi'] ?? $solicitud->cfdi;
$solicitud->metodo_pago = $data['metodo_pago'] ?? $solicitud->metodo_pago;
$solicitud->forma_pago = $data['forma_pago'] ?? $solicitud->forma_pago;
$solicitud->banco = $data['banco'] ?? $solicitud->banco;
$solicitud->no_cuenta = $data['no_cuenta'] ?? $solicitud->no_cuenta;
$solicitud->cuenta_clabe = $data['cuenta_clabe'] ?? $solicitud->cuenta_clabe;
$solicitud->referencia = $data['referencia'] ?? $solicitud->referencia;
$solicitud->observaciones = $data['observaciones'] ?? $solicitud->observaciones;
$solicitud->razonsocial = $data['razonsocial'] ?? $solicitud->razonsocial;
$solicitud->hora = Carbon::now()->format('H:i:s');

try {
$solicitud->save();

if (!empty($data['firma_base64'])) {
self::editarFirmaImagen($id, $idUsuario, 'A', $data['firma_base64']);
}

self::procesarDocumentosEditar($id, $idUsuario, $files);

self::notificarActualizacion($id, $idUsuario, $nameEstacion);

return ['success' => true, 'message' => 'Solicitud actualizada exitosamente.'];
} catch (\Throwable $e) {
return ['success' => false, 'message' => 'Error al actualizar: ' . $e->getMessage()];
}
}

public static function destroy(int $id): array
{
$solicitud = SolicitudCheque::find($id);

if (!$solicitud) {
return [
'success' => false,
'message' => 'Solicitud no encontrada'
];
}

$sessionUsuario = Session::get('usuario');
$idUsuario = $sessionUsuario['id'] ?? 0;

try {

// Eliminar registros relacionados
SolicitudChequeDocumento::where('id_solicitud', $id)->delete();
SolicitudChequeComentario::where('id_solicitud', $id)->delete();
SolicitudChequeFirma::where('id_solicitud', $id)->delete();
SolicitudChequeToken::where('id_solicitud', $id)->delete();

// Enviar notificación antes de eliminar la solicitud
self::notificarEliminacion($solicitud, $idUsuario);

// Eliminar la solicitud
$solicitud->delete();

return [
'success' => true,
'message' => 'Solicitud eliminada exitosamente.'
];

} catch (\Exception $e) {

return [
'success' => false,
'message' => 'Error al eliminar: ' . $e->getMessage()
];
}
}

public static function getDocumentos(int $idSolicitud): array
{
return SolicitudChequeDocumento::where('id_solicitud', $idSolicitud)
->where('nombre', '!=', 'PAGO')
->orderBy('id', 'asc')
->get()
->toArray();
}

private static function notificarDocumento(int $idSolicitud, int $idUsuario, string $accion, string $tipoDocumento = '', string $nombreArchivo = ''): void
{
try {
$solicitud = SolicitudCheque::find($idSolicitud);
if (!$solicitud) return;

$telegram = new TelegramService();
$datosUsuario = Usuario::find($idUsuario);
$nombreUsuario = $datosUsuario ? $datosUsuario->nombre : 'Desconocido';
$nombreMes = nombremes($solicitud->id_mes);

$idEstacion = $solicitud->id_estacion;
$depto = $solicitud->depto;

if ($idEstacion == 8) {
$nombreDepto = self::DEPARTMENTS[$depto] ?? 'Desconocido';
$nombreES = '🏢 Departamento: ' . $nombreDepto;
} else {
$estacion = Estacion::find($idEstacion);
$nombreES = '⛽ Estación: ' . ($estacion ? $estacion->nombre : 'Desconocida');
}

$fechaAhora = Carbon::now()->format('d/m/Y g:i a');
$emoji = $accion === 'agregado' ? '📄' : '🗑️';
$accionTexto = $accion === 'agregado' ? 'agregado' : 'eliminado';

$detalleTelegram =
$emoji . ' Se ha <b>' . $accionTexto . '</b> un documento en el apartado de <b>Solicitud de Cheque</b>, correspondiente al periodo de <b>'
. $nombreMes . ' ' . ($solicitud->id_year ?? '') . '</b>:' . PHP_EOL . PHP_EOL .

'📋 <b>No. de Solicitud:</b> #' . $idSolicitud . PHP_EOL .
'🗓️ <b>Fecha de solicitud:</b> ' . formatearFecha($solicitud->fecha ?? '') . PHP_EOL .
'🏦 <b>Beneficiario:</b> ' . ($solicitud->beneficiario ?? 'N/A') . PHP_EOL .
'🗒 <b>Concepto:</b> ' . ($solicitud->concepto ?? 'N/A') . PHP_EOL .
'💰 <b>Monto:</b> $' . number_format((float)$solicitud->monto, 2) . ' ' . ($solicitud->moneda ?? '') . PHP_EOL .
'📄 <b>Tipo de documento:</b> ' . ($tipoDocumento ?: 'N/A') . PHP_EOL . PHP_EOL .

'👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL .
$nombreES;

if ($idEstacion == 8 && $depto == 5) {
$userIds = $telegram->getUserIdsGestoria($idUsuario);
$telegram->sendToken(30, $detalleTelegram);
} else {
$userIds = $telegram->getUserIdsByStation($idEstacion, $idUsuario);
$telegram->sendToken(19, $detalleTelegram);

if (in_array($idEstacion, [6, 7])) {
$extraIds = $telegram->getUserIdsComercializadora($idUsuario);
if (!empty($extraIds)) {
$telegram->sendMessageToMultiple($extraIds, $detalleTelegram);
}
} elseif (in_array($idEstacion, [1, 2, 3, 4, 5, 14])) {
$extraIds = $telegram->getUserIdsContabilidad($idUsuario);
if (!empty($extraIds)) {
$telegram->sendMessageToMultiple($extraIds, $detalleTelegram);
}
}
}

if (!empty($userIds)) {
$telegram->sendMessageToMultiple($userIds, $detalleTelegram);
}
} catch (\Throwable $e) {
error_log('Error en notificarDocumento: ' . $e->getMessage());
}
}

private static function notificarPago(int $idSolicitud, int $idUsuario, string $accion, string $nombreArchivo = ''): void
{
try {
$solicitud = SolicitudCheque::find($idSolicitud);
if (!$solicitud) return;

$telegram = new TelegramService();
$datosUsuario = Usuario::find($idUsuario);
$nombreUsuario = $datosUsuario ? $datosUsuario->nombre : 'Desconocido';
$nombreMes = nombremes($solicitud->id_mes);

$idEstacion = $solicitud->id_estacion;
$depto = $solicitud->depto;

if ($idEstacion == 8) {
$nombreDepto = self::DEPARTMENTS[$depto] ?? 'Desconocido';
$nombreES = '🏢 Departamento: ' . $nombreDepto;
} else {
$estacion = Estacion::find($idEstacion);
$nombreES = '⛽ Estación: ' . ($estacion ? $estacion->nombre : 'Desconocida');
}

$emoji = $accion === 'agregado' ? '💵' : '🗑️';
$accionTexto = $accion === 'agregado' ? 'agregado' : 'eliminado';

$detalleTelegram =
$emoji . ' Se ha <b>' . $accionTexto . '</b> un comprobante de pago en el apartado de <b>Solicitud de Cheque</b>, correspondiente al periodo de <b>'
. $nombreMes . ' ' . ($solicitud->id_year ?? '') . '</b>:' . PHP_EOL . PHP_EOL .

'📋 <b>No. de Solicitud:</b> #' . $idSolicitud . PHP_EOL .
'🗓️ <b>Fecha de solicitud:</b> ' . formatearFecha($solicitud->fecha ?? '') . PHP_EOL .
'🏦 <b>Beneficiario:</b> ' . ($solicitud->beneficiario ?? 'N/A') . PHP_EOL .
'🗒 <b>Concepto:</b> ' . ($solicitud->concepto ?? 'N/A') . PHP_EOL .
'💰 <b>Monto:</b> $' . number_format((float)$solicitud->monto, 2) . ' ' . ($solicitud->moneda ?? '') . PHP_EOL .
'📎 <b>Comprobante:</b> ' . ($nombreArchivo ?: 'N/A') . PHP_EOL . PHP_EOL .

'👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL .
$nombreES;

if ($idEstacion == 8 && $depto == 5) {
$userIds = $telegram->getUserIdsGestoria($idUsuario);
$telegram->sendToken(30, $detalleTelegram);
} else {
$userIds = $telegram->getUserIdsByStation($idEstacion, $idUsuario);
$telegram->sendToken(19, $detalleTelegram);

if (in_array($idEstacion, [6, 7])) {
$extraIds = $telegram->getUserIdsComercializadora($idUsuario);
if (!empty($extraIds)) {
$telegram->sendMessageToMultiple($extraIds, $detalleTelegram);
}
} elseif (in_array($idEstacion, [1, 2, 3, 4, 5, 14])) {
$extraIds = $telegram->getUserIdsContabilidad($idUsuario);
if (!empty($extraIds)) {
$telegram->sendMessageToMultiple($extraIds, $detalleTelegram);
}
}
}

if (!empty($userIds)) {
$telegram->sendMessageToMultiple($userIds, $detalleTelegram);
}
} catch (\Throwable $e) {
error_log('Error en notificarPago: ' . $e->getMessage());
}
}

private static function notificarFacturaTelcel(int $idEstacion, int $idYear, int $idMes, int $idUsuario, string $accion, string $tipo = ''): void
{
register_shutdown_function(function () use ($idEstacion, $idYear, $idMes, $idUsuario, $accion, $tipo) {
try {
$telegram = new TelegramService();
$datosUsuario = Usuario::find($idUsuario);
$nombreUsuario = $datosUsuario ? $datosUsuario->nombre : 'Desconocido';
$nombreMes = nombremes($idMes);

if ($idEstacion == 8) {
$nombreES = '🏢 Departamento';
} else {
$estacion = Estacion::find($idEstacion);   
$nombreES = '⛽ Estación: ' . ($estacion ? $estacion->nombre : 'Desconocida');
}

$accionTexto = '';
$emoji = '';

switch ($accion) {
case 'agregar_directorio':
$emoji = '📁';
$accionTexto = 'agregado un registro de directorio';
break;

case 'editar_directorio':
$emoji = '🔄';
$accionTexto = 'editado un registro de directorio';
break;

case 'eliminar_directorio':
$emoji = '🗑';
$accionTexto = 'eliminado un registro de directorio';
break;

case 'agregar_factura':
$emoji = '📄';
$accionTexto = 'agregado un documento (' . $tipo . ')';
break;

case 'eliminar_factura':
$emoji = '🗑';
$accionTexto = 'eliminado un documento (' . $tipo . ')';
break;

default:
return;
}

$detalleTelegram = $emoji . ' Se ha ' . $accionTexto
. ' en el apartado de <b>Facturas Telcel</b>, correspondiente al módulo de <b>Solicitud de Cheques</b> del periodo de <b>'
. $nombreMes . ' ' . $idYear . '</b>:'
. PHP_EOL . PHP_EOL
. '👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL
. '⛽ <b>Estación:</b> ' . $nombreES;

if ($idEstacion == 8) {
$telegram->sendToken(30, $detalleTelegram);
$userIds = $telegram->getUserIdsGestoria($idUsuario);
} else {
$userIds = $telegram->getUserIdsByStation($idEstacion, $idUsuario);
$telegram->sendToken(19, $detalleTelegram);

if (in_array($idEstacion, [6, 7])) {
$extraIds = $telegram->getUserIdsComercializadora($idUsuario);
if (!empty($extraIds)) {
$telegram->sendMessageToMultiple($extraIds, $detalleTelegram);
}
} elseif (in_array($idEstacion, [1, 2, 3, 4, 5, 14])) {
$extraIds = $telegram->getUserIdsContabilidad($idUsuario);
if (!empty($extraIds)) {
$telegram->sendMessageToMultiple($extraIds, $detalleTelegram);
}
}
}

if (!empty($userIds)) {
$telegram->sendMessageToMultiple($userIds, $detalleTelegram);
}
} catch (\Throwable $e) {
error_log('Error en notificarFacturaTelcel: ' . $e->getMessage());
}
});
}

public static function storeDocumento(int $idSolicitud, string $descripcion, array $file): array
{
$usuario = Auth::user();
if (!$usuario) {
return ['success' => false, 'message' => 'Sesión no válida'];
}

$solicitud = SolicitudCheque::find($idSolicitud);
if (!$solicitud) {
return ['success' => false, 'message' => 'Solicitud no encontrada'];
}
$esMesActual = (Carbon::now()->year == $solicitud->id_year && Carbon::now()->month == $solicitud->id_mes);
$puedeArchivos = (int)$solicitud->status === 2 || $esMesActual;
if (!$puedeArchivos) {
return ['success' => false, 'message' => 'No es posible agregar archivos en este momento'];
}

if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) {
return ['success' => false, 'message' => 'Archivo no válido'];
}

$sessionUsuario = Session::get('usuario');
$idUsuario = $sessionUsuario['id'] ?? 0;

$aleatorio = uniqid();
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$nombreArchivo = $aleatorio . '-' . $file['name'];
$uploadDir = self::getUploadDir();

if (!is_dir($uploadDir)) {
mkdir($uploadDir, 0755, true);
}

if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $nombreArchivo)) {
return ['success' => false, 'message' => 'Error al subir el archivo'];
}

try {
SolicitudChequeDocumento::create([
'id_solicitud' => $idSolicitud,
'nombre' => strtoupper($descripcion),
'documento' => $nombreArchivo,
]);

if (strtoupper($descripcion) === 'COTIZACIÓN') {
$comentario = 'Factura contra entrega';
self::storeComentarioInterno($idSolicitud, $idUsuario, $comentario);
}

self::notificarDocumento($idSolicitud, $idUsuario, 'agregado', strtoupper($descripcion), $nombreArchivo);

return ['success' => true, 'message' => 'Documento agregado exitosamente.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al guardar documento: ' . $e->getMessage()];
}
}

public static function deleteDocumento(int $idDocumento): array
{
$doc = SolicitudChequeDocumento::find($idDocumento);
if (!$doc) {
return ['success' => false, 'message' => 'Documento no encontrado'];
}

$solicitud = SolicitudCheque::find($doc->id_solicitud);
if ($solicitud) {
$esMesActual = (Carbon::now()->year == $solicitud->id_year && Carbon::now()->month == $solicitud->id_mes);
$puedeArchivos = (int)$solicitud->status === 2 || $esMesActual;
if (!$puedeArchivos) {
return ['success' => false, 'message' => 'No es posible eliminar archivos en este momento'];
}
}

$sessionUsuario = Session::get('usuario');
$idUsuario = $sessionUsuario['id'] ?? 0;

$uploadDir = self::getUploadDir();
$filePath = $uploadDir . '/' . $doc->documento;
if (file_exists($filePath)) {
unlink($filePath);
}

try {
$doc->delete();
self::notificarDocumento($solicitud ? $solicitud->id : 0, $idUsuario, 'eliminado', $doc->nombre ?? '', $doc->documento ?? '');
return ['success' => true, 'message' => 'Documento eliminado.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()];
}
}

public static function getComentarios(int $idSolicitud): array
{
return SolicitudChequeComentario::where('id_solicitud', $idSolicitud)
->orderBy('fecha_hora', 'asc')
->with('usuario:id,nombre')
->get()
->toArray();
}

public static function storeComentario(int $idSolicitud, string $comentario): array
{
$usuario = Auth::user();
if (!$usuario) {
return ['success' => false, 'message' => 'Sesión no válida'];
}

if (!trim($comentario)) {
return ['success' => false, 'message' => 'El comentario no puede estar vacío'];
}

$sessionUsuario = Session::get('usuario');
$idUsuario = $sessionUsuario['id'] ?? 0;

try {
SolicitudChequeComentario::create([
'id_solicitud' => $idSolicitud,
'fecha_hora' => Carbon::now(),
'id_usuario' => $idUsuario,
'comentario' => trim($comentario),
]);

self::notificarComentario($idSolicitud, $idUsuario, $comentario);

return ['success' => true, 'message' => 'Comentario agregado.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al guardar comentario: ' . $e->getMessage()];
}
}

private static function storeComentarioInterno(int $idSolicitud, int $idUsuario, string $comentario): void
{
try {
SolicitudChequeComentario::create([
'id_solicitud' => $idSolicitud,
'fecha_hora' => Carbon::now(),
'id_usuario' => $idUsuario,
'comentario' => $comentario,
]);
} catch (\Exception $e) {
error_log('Error al guardar comentario interno: ' . $e->getMessage());
}
}

public static function getFirmas(int $idSolicitud): array
{
return SolicitudChequeFirma::where('id_solicitud', $idSolicitud)
->orderBy('fecha', 'asc')
->get()
->toArray();
}

public static function crearToken(int $idSolicitud, int $idUsuario, string $via = 'telegram'): array
{
try {
self::eliminarToken($idSolicitud);

$token = rand(100000, 999999);

SolicitudChequeToken::create([
'id_solicitud' => $idSolicitud,
'id_usuario' => $idUsuario,
'token' => $token,
'fecha_creacion' => Carbon::now(),
]);

$solicitudToken = SolicitudCheque::find($idSolicitud);
$fecha = $solicitudToken ? ($solicitudToken->fecha ?? '') : '';
$benefToken = $solicitudToken ? ($solicitudToken->beneficiario ?? '') : '';
$montoToken = $solicitudToken ? number_format((float)($solicitudToken->monto ?? 0), 2) . ' ' . ($solicitudToken->moneda ?? '') : '';
$razonToken = $solicitudToken ? ($solicitudToken->razonsocial ?? '') : '';
$conceptoToken = $solicitudToken ? ($solicitudToken->concepto ?? '') : '';
$idEstacionToken = $solicitudToken ? (int)$solicitudToken->id_estacion : 0;
$deptoToken = $solicitudToken ? (int)$solicitudToken->depto : 0;


if ($idEstacionToken == 8) {
$nombreDeptoToken = self::DEPARTMENTS[$deptoToken] ?? 'Desconocido';
$nombreES_Token = '🏢 Departamento: ' . $nombreDeptoToken;
} else {
$estacionToken = Estacion::find($idEstacionToken);
$nombreES_Token = '⛽ Estación: ' . ($estacionToken ? $estacionToken->nombre : 'Desconocida');
}

$mensaje =
'📲 Usa el token <b>' . $token . '</b> para firmar la <b>Solicitud de Cheque</b>, correspondiente al día <b>' . formatearFecha($fecha) . '</b>:' . PHP_EOL . PHP_EOL .

'📋 <b>No. de Solicitud:</b> #' . $idSolicitud . PHP_EOL .
'🏦 <b>Beneficiario:</b> ' . $benefToken . PHP_EOL .
'🗒 <b>Concepto:</b> ' . ($conceptoToken ?: 'N/A') . PHP_EOL .
'💰 <b>Monto:</b> ' . $montoToken . PHP_EOL . PHP_EOL .
$nombreES_Token;

if ($via === 'email') {
$usuario = Usuario::find($idUsuario);
$email = $usuario?->email ?? '';
if (!$email) {
return ['success' => false, 'message' => 'El usuario no tiene correo electrónico registrado'];
}
$emailService = new EmailService();
$emailService->sendToken($email, $token);
return ['success' => true, 'message' => 'Token enviado por correo electrónico.'];
} else {
$telegram = new TelegramService();
$telegram->sendToken($idUsuario, $mensaje);
return ['success' => true, 'message' => 'Token enviado por Telegram.'];
}
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al crear token: ' . $e->getMessage()];
}
}

public static function puedeFirmarSolicitud(int $idSolicitud, int $idUsuario): bool
{
$permisos = self::getPermisos();
if (!$permisos['es_gestoria']) return true;
$solicitud = SolicitudCheque::find($idSolicitud);
if (!$solicitud) return false;
return (int)$solicitud->id_estacion === 8 && (int)$solicitud->depto === 5;
}

public static function firmar(int $idSolicitud, string $tipoFirma, int $token, int $idUsuario, string $nameEstacion): array
{
$tokenRecord = SolicitudChequeToken::where('id_solicitud', $idSolicitud)
->where('id_usuario', $idUsuario)
->where('token', $token)
->orderBy('id', 'desc')
->first();

if (!$tokenRecord) {
return ['success' => false, 'message' => 'Token no válido'];
}

$expiry = Carbon::parse($tokenRecord->fecha_creacion)->addMinutes(2);
if (Carbon::now()->greaterThan($expiry)) {
return ['success' => false, 'message' => 'El token ha expirado'];
}

$estado = match ($tipoFirma) {
'B' => 1,
'C' => 2,
default => 0,
};

try {
$solicitud = SolicitudCheque::find($idSolicitud);
if (!$solicitud) {
return ['success' => false, 'message' => 'Solicitud no encontrada'];
}

if (!self::puedeFirmarSolicitud($idSolicitud, $idUsuario)) {
return ['success' => false, 'message' => 'No tienes permiso para firmar esta solicitud'];
}

$solicitud->status = $estado;
$solicitud->save();

$firmaHash = 'Firma:' . bin2hex(random_bytes(64)) . '.' . uniqid();

SolicitudChequeFirma::create([
'id_solicitud' => $idSolicitud,
'id_usuario' => $idUsuario,
'tipo_firma' => $tipoFirma,
'firma' => $firmaHash,
'fecha' => Carbon::now(),
]);

$usuario = Usuario::find($idUsuario);
$nombreFirmo = $usuario ? $usuario->nombre : 'Desconocido';
$fechaFirma = Carbon::now()->format('d/m/Y g:i a');
$estacion = Estacion::find($solicitud->id_estacion);
$nomEstacion = $estacion ? $estacion->nombre : $nameEstacion;
$nombreMes = nombremes($solicitud->id_mes);
$idEstacionToken = $solicitud ? (int)$solicitud->id_estacion : 0;
$deptoToken = $solicitud ? (int)$solicitud->depto : 0;

if ($idEstacionToken == 8) {
$nombreDeptoToken = self::DEPARTMENTS[$deptoToken] ?? 'Desconocido';
$nombreES_Token = '🏢 Departamento: ' . $nombreDeptoToken;
} else {
$estacionToken = Estacion::find($idEstacionToken);
$nombreES_Token = '⛽ Estación: ' . ($estacionToken ? $estacionToken->nombre : 'Desconocida');
}

if ($tipoFirma === 'B') {
$detalle =
'✍️ Se ha <b>firmado el VoBo</b> de una <b>Solicitud de Cheque</b>, correspondiente al periodo de <b>'
. $nombreMes . ' ' . $solicitud->id_year . '</b>:' . PHP_EOL . PHP_EOL .

'📋 <b>No. de Solicitud:</b> #' . $idSolicitud . PHP_EOL .
'🗓️ <b>Fecha de solicitud:</b> ' . formatearFecha($solicitud->fecha) . PHP_EOL .
'🏦 <b>Beneficiario:</b> ' . ($solicitud->beneficiario ?: 'N/A') . PHP_EOL .
'🗒 <b>Concepto:</b> ' . ($solicitud->concepto ?: 'N/A') . PHP_EOL .
'💰 <b>Monto:</b> $' . number_format((float)$solicitud->monto, 2) . ' ' . ($solicitud->moneda ?: '') . PHP_EOL .
'📋 <b>Solicitante:</b> ' . ($solicitud->solicitante ?: 'N/A') . PHP_EOL .
'🔖 <b>No. de factura:</b> ' . ($solicitud->no_factura ?: 'N/A') . PHP_EOL . PHP_EOL .

'👤 <b>Responsable:</b> ' . $nombreFirmo . PHP_EOL .
$nombreES_Token;

$telegram = new TelegramService();
$telegram->sendToken(2, $detalle);

} elseif ($tipoFirma === 'C') {

$detalle =
'✅ Se ha <b>autorizado</b> una <b>Solicitud de Cheque</b>, correspondiente al periodo de <b>'
. $nombreMes . ' ' . $solicitud->id_year . '</b>:' . PHP_EOL . PHP_EOL .

'📋 <b>No. de Solicitud:</b> #' . $idSolicitud . PHP_EOL .
'🗓️ <b>Fecha de solicitud:</b> ' . formatearFecha($solicitud->fecha) . PHP_EOL .
'🏦 <b>Beneficiario:</b> ' . ($solicitud->beneficiario ?: 'N/A') . PHP_EOL .
'🗒 <b>Concepto:</b> ' . ($solicitud->concepto ?: 'N/A') . PHP_EOL .
'💰 <b>Monto:</b> $' . number_format((float)$solicitud->monto, 2) . ' ' . ($solicitud->moneda ?: '') . PHP_EOL .
'📋 <b>Solicitante:</b> ' . ($solicitud->solicitante ?: 'N/A') . PHP_EOL .
'🔖 <b>No. de factura:</b> ' . ($solicitud->no_factura ?: 'N/A') . PHP_EOL . PHP_EOL .

'👤 <b>Responsable:</b> ' . $nombreFirmo . PHP_EOL .
$nombreES_Token;

$telegram = new TelegramService();
$telegram->sendToken(2, $detalle);
}

self::eliminarToken($idSolicitud);

return ['success' => true, 'message' => 'Solicitud firmada exitosamente.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al firmar: ' . $e->getMessage()];
}
}

public static function getTelcel(int $idSolicitud): array
{
$row = SolicitudCheque::find($idSolicitud);
if (!$row) return [];

return SolicitudChequeTelcel::where('id_year', $row->id_year)
->where('id_mes', $row->id_mes)
->where('id_estacion', $row->id_estacion)
->orderBy('id', 'asc')
->get()
->toArray();
}

public static function storeTelcel(int $idSolicitud, array $data, array $files): array
{
$row = SolicitudCheque::find($idSolicitud);
if (!$row) {
return ['success' => false, 'message' => 'Solicitud no encontrada'];
}

$factura = '';

if (!empty($files['factura']) && $files['factura']['error'] === UPLOAD_ERR_OK) {
$aleatorio = uniqid();
$nombreArchivo = $aleatorio . '-' . $files['factura']['name'];
$uploadDir = self::getUploadDir();
if (!is_dir($uploadDir)) {
mkdir($uploadDir, 0755, true);
}
if (move_uploaded_file($files['factura']['tmp_name'], $uploadDir . '/' . $nombreArchivo)) {
$factura = $nombreArchivo;
}
}

try {
SolicitudChequeTelcel::create([
'id_year' => $row->id_year,
'id_mes' => $row->id_mes,
'id_estacion' => $row->id_estacion,
'factura' => $factura,
]);
return ['success' => true, 'message' => 'Factura Telcel agregada.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()];
}
}

public static function deleteTelcel(int $id): array
{
$telcel = SolicitudChequeTelcel::find($id);
if (!$telcel) {
return ['success' => false, 'message' => 'Registro no encontrado'];
}

$uploadDir = self::getUploadDir();
if ($telcel->c_pago) {
$filePath = $uploadDir . '/' . $telcel->c_pago;
if (file_exists($filePath)) unlink($filePath);
}

try {
$telcel->delete();
return ['success' => true, 'message' => 'Factura Telcel eliminada.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()];
}
}

public static function updatePagoTelcel(int $id, array $files): array
{
$telcel = SolicitudChequeTelcel::find($id);
if (!$telcel) {
return ['success' => false, 'message' => 'Registro no encontrado'];
}

$uploadDir = self::getUploadDir();

if (!empty($files['factura']) && $files['factura']['error'] === UPLOAD_ERR_OK) {
if ($telcel->factura) {
$oldFile = $uploadDir . '/' . $telcel->factura;
if (file_exists($oldFile)) unlink($oldFile);
}
$aleatorio = uniqid();
$nombreArchivo = $aleatorio . '-' . $files['factura']['name'];
if (move_uploaded_file($files['factura']['tmp_name'], $uploadDir . '/' . $nombreArchivo)) {
$telcel->factura = $nombreArchivo;
}
}

if (!empty($files['c_pago']) && $files['c_pago']['error'] === UPLOAD_ERR_OK) {
if ($telcel->c_pago) {
$oldFile = $uploadDir . '/' . $telcel->c_pago;
if (file_exists($oldFile)) unlink($oldFile);
}
$aleatorio = uniqid();
$nombreArchivo = $aleatorio . '-' . $files['c_pago']['name'];
if (move_uploaded_file($files['c_pago']['tmp_name'], $uploadDir . '/' . $nombreArchivo)) {
$telcel->c_pago = $nombreArchivo;
}
}

try {
$telcel->save();
return ['success' => true, 'message' => 'Registro actualizado.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al actualizar: ' . $e->getMessage()];
}
}

public static function getTelcelByFilter(int $idYear, int $idMes, int $idEstacion): array
{
return SolicitudChequeTelcel::where('id_year', $idYear)
->where('id_mes', $idMes)
->where('id_estacion', $idEstacion)
->orderBy('id', 'asc')
->get()
->toArray();
}

public static function storeTelcelGlobal(int $idYear, int $idMes, int $idEstacion, array $files): array
{
$factura = '';
if (!empty($files['factura']) && $files['factura']['error'] === UPLOAD_ERR_OK) {
$aleatorio = uniqid();
$nombreArchivo = $aleatorio . '-' . $files['factura']['name'];
$uploadDir = self::getUploadDir();
if (!is_dir($uploadDir)) {
mkdir($uploadDir, 0755, true);
}
if (move_uploaded_file($files['factura']['tmp_name'], $uploadDir . '/' . $nombreArchivo)) {
$factura = $nombreArchivo;
}
}
try {
SolicitudChequeTelcel::create([
'id_year' => $idYear,
'id_mes' => $idMes,
'id_estacion' => $idEstacion,
'factura' => $factura,
]);
return ['success' => true, 'message' => 'Factura Telcel agregada.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()];
}
}

public static function deleteComprobanteTelcel(int $id): array
{
$telcel = SolicitudChequeTelcel::find($id);
if (!$telcel) {
return ['success' => false, 'message' => 'Registro no encontrado'];
}
$uploadDir = self::getUploadDir();
if ($telcel->c_pago) {
$filePath = $uploadDir . '/' . $telcel->c_pago;
if (file_exists($filePath)) unlink($filePath);
$telcel->c_pago = null;
}
try {
$telcel->save();
return ['success' => true, 'message' => 'Comprobante eliminado.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()];
}
}

public static function getPagos(int $idSolicitud): array
{
return SolicitudChequeDocumento::where('id_solicitud', $idSolicitud)
->where('nombre', 'PAGO')
->orderBy('id', 'asc')
->get()
->toArray();
}

public static function storePago(int $idSolicitud, array $file): array
{
$solicitud = SolicitudCheque::find($idSolicitud);
if (!$solicitud) {
return ['success' => false, 'message' => 'Solicitud no encontrada'];
}
$esMesActual = (Carbon::now()->year == $solicitud->id_year && Carbon::now()->month == $solicitud->id_mes);
$puedePagos = (int)$solicitud->status === 2 || ((int)$solicitud->status === 1 && $esMesActual);
if (!$puedePagos) {
return ['success' => false, 'message' => 'No es posible agregar pagos en este momento'];
}

if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) {
return ['success' => false, 'message' => 'Archivo no válido'];
}

$aleatorio = uniqid();
$nombreArchivo = $aleatorio . '-' . $file['name'];
$uploadDir = self::getUploadDir();

if (!is_dir($uploadDir)) {
mkdir($uploadDir, 0755, true);
}

if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $nombreArchivo)) {
return ['success' => false, 'message' => 'Error al subir el archivo'];
}

$sessionUsuario = Session::get('usuario');
$idUsuario = $sessionUsuario['id'] ?? 0;

try {
SolicitudChequeDocumento::create([
'id_solicitud' => $idSolicitud,
'nombre' => 'PAGO',
'documento' => $nombreArchivo,
]);
self::notificarPago($idSolicitud, $idUsuario, 'agregado', $nombreArchivo);
return ['success' => true, 'message' => 'Comprobante de pago agregado.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()];
}
}

public static function deletePago(int $id): array
{
$doc = SolicitudChequeDocumento::find($id);
if (!$doc) {
return ['success' => false, 'message' => 'Documento no encontrado'];
}

$solicitud = SolicitudCheque::find($doc->id_solicitud);
if ($solicitud) {
$esMesActual = (Carbon::now()->year == $solicitud->id_year && Carbon::now()->month == $solicitud->id_mes);
$puedePagos = (int)$solicitud->status === 2 || ((int)$solicitud->status === 1 && $esMesActual);
if (!$puedePagos) {
return ['success' => false, 'message' => 'No es posible eliminar pagos en este momento'];
}
}

$sessionUsuario = Session::get('usuario');
$idUsuario = $sessionUsuario['id'] ?? 0;

$uploadDir = self::getUploadDir();
$filePath = $uploadDir . '/' . $doc->documento;
if (file_exists($filePath)) unlink($filePath);

try {
$doc->delete();
self::notificarPago($solicitud ? $solicitud->id : 0, $idUsuario, 'eliminado', $doc->documento ?? '');
return ['success' => true, 'message' => 'Comprobante de pago eliminado.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()];
}
}

public static function getOpcionesSelector(): array
{
// Puesto 6 + Estación 8: OVERRIDE absoluto - no departments, no estacion 8
if (ModuleStationService::isPuesto6Estacion8()) {
$estaciones = Estacion::whereIn('id', [1, 2, 3, 4, 5, 6, 7, 14])
->orderBy('id')
->get(['id', 'nombre']);
$opciones = [];
foreach ($estaciones as $estacion) {
$opciones[] = [
'id' => 'estacion_' . $estacion->id,
'texto' => $estacion->nombre,
'tipo' => 'estacion',
'valor' => $estacion->id,
'hijos' => [],
];
}
return $opciones;
}

$sessionUsuario = Session::get('usuario');
$idUsuario = $sessionUsuario['id'] ?? 0;
$multiEstacion = $sessionUsuario['multiestacion'] ?? false;
$permisos = self::getPermisos();
$esContabilidad = $permisos['es_contabilidad'];
$esComercializadora = $permisos['es_comercializadora'];
$nombrePuesto = $permisos['nombre_puesto'];
$esServicioSocial = $nombrePuesto === 'Dirección de operaciones servicio social';
$esDireccionOp = $nombrePuesto === 'Dirección de operaciones';

$opciones = [];

if ($multiEstacion) {
$estaciones = Estacion::where('numlista', '<=', 8)
->orderBy('numlista', 'asc')
->get(['id', 'nombre', 'numlista']);

foreach ($estaciones as $estacion) {
$sid = $estacion->id;
$nl = $estacion->numlista;
$puedeVer = false;

if ($nombrePuesto == 'Contabilidad') {
if ($idUsuario == 419) continue;
$puedeVer = in_array($sid, [1, 2, 3, 4, 5, 14]);
} elseif ($nombrePuesto == 'Comercializadora') {
$puedeVer = in_array($sid, [6, 7]);
} else {
$puedeVer = in_array($sid, [1, 2, 3, 4, 5, 6, 7, 14]);
}

if (!$puedeVer) continue;

if ($nl == 8) {
$todosDeptos = Puesto::whereIn('id', [5, 4, 18, 19, 23])
->orderBy('id', 'asc')
->get(['id', 'nombre']);

$hijos = [];
foreach ($todosDeptos as $depto) {
$puedeVerDepto = false;
$did = $depto->id;
if ($did == 5) {
$puedeVerDepto = !$esDireccionOp && !$esServicioSocial;
if ($idUsuario == 344) $puedeVerDepto = true;
} elseif ($did == 4 || $did == 18) {
$puedeVerDepto = !$esContabilidad && !$esServicioSocial;
} elseif ($did == 19 || $did == 23) {
$puedeVerDepto = !$esComercializadora;
}
if ($puedeVerDepto) {
$hijos[] = [
'id' => 'depto_' . $did,
'texto' => $depto->nombre,
'tipo' => 'depto',
'valor' => $did,
];
}
}

if (!empty($hijos)) {
$opciones[] = [
'id' => 'estacion_' . $estacion->id,
'texto' => $estacion->nombre,
'tipo' => 'estacion',
'valor' => $estacion->id,
'hijos' => $hijos,
];
}
} else {
$opciones[] = [
'id' => 'estacion_' . $estacion->id,
'texto' => $estacion->nombre,
'tipo' => 'estacion',
'valor' => $estacion->id,
'hijos' => [],
];
}
}

if ($idUsuario == 419) {
$opciones[] = [
'id' => 'estacion_' . 14,
'texto' => 'Bosque Real',
'tipo' => 'estacion',
'valor' => 14,
'hijos' => [],
];
}
}

return $opciones;
}

public static function getPendingCounts(int $idYear, int $idMes): array
{
$now = Carbon::now();
$esPeriodoVencido = $idYear < $now->year || ($idYear === $now->year && $idMes < $now->month);

$allowedStationIds = self::getAllowedStationIds();
$allowedDeptIds = self::getAllowedDeptIds();

$stations = Estacion::where('numlista', '<=', 8)
->orderBy('numlista', 'asc')
->get(['id', 'nombre', 'numlista']);

$stationCounts = [];
foreach ($stations as $est) {
$mostrar = in_array($est->id, $allowedStationIds);
$count = 0;
if ($mostrar && !$esPeriodoVencido) {
$q = SolicitudCheque::where('id_year', $idYear)
->where('id_mes', $idMes)
->where('status', 0)
->where('id_estacion', $est->id);
if ($est->id == 8) {
$q->where(function ($q2) {
$q2->whereNull('depto')->orWhere('depto', 0);
});
}
$count = $q->count();
}
$stationCounts[] = [
'id' => $est->id,
'nombre' => $est->nombre,
'numlista' => $est->numlista,
'pendientes' => $count,
];
}

$deptIds = array_keys(self::DEPARTMENTS);
$deptCounts = [];
foreach ($deptIds as $did) {
$mostrar = in_array($did, $allowedDeptIds);
$count = 0;
if ($mostrar && !$esPeriodoVencido) {
$count = SolicitudCheque::where('id_year', $idYear)
->where('id_mes', $idMes)
->where('status', 0)
->where('id_estacion', 8)
->where('depto', $did)
->count();
}
$deptCounts[] = [
'id_puesto' => $did,
'nombre' => self::DEPARTMENTS[$did],
'pendientes' => $count,
];
}

return [
'stations' => $stationCounts,
'departments' => $deptCounts,
];
}

public static function getFacturaStatus(int $idYear, int $idMes, int $idEstacion, int $depto): array
{
$idReporte = self::getMesId($idEstacion, $idYear, $idMes);
if (!$idReporte) return ['total' => 0, 'con_pago' => 0, 'pendientes' => 0];
$total = FacturaTelcel::where('id_mes', $idReporte)->where('detalle', 'Factura')->count();
$conPago = FacturaTelcel::where('id_mes', $idReporte)->where('detalle', 'Pago')->count();
return [
'total' => $total,
'con_pago' => $conPago,
'pendientes' => $total - $conPago,
];
}

private static function nextId(): int
{
$max = SolicitudCheque::max('id');
return ($max ?: 0) + 1;
}

private static function guardarFirmaImagen(int $idSolicitud, int $idUsuario, string $tipoFirma, string $base64): void
{
$img = str_replace('data:image/png;base64,', '', $base64);
$fileData = base64_decode($img);
$fileName = uniqid() . '.png';
$firmaDir = self::getFirmaDir();

if (!is_dir($firmaDir)) {
mkdir($firmaDir, 0755, true);
}

if (file_put_contents($firmaDir . '/' . $fileName, $fileData)) {
SolicitudChequeFirma::create([
'id_solicitud' => $idSolicitud,
'id_usuario' => $idUsuario,
'tipo_firma' => $tipoFirma,
'firma' => $fileName,
'fecha' => Carbon::now(),
]);
}
}

private static function editarFirmaImagen(int $idSolicitud, int $idUsuario, string $tipoFirma, string $base64): void
{
$firmaExistente = SolicitudChequeFirma::where('id_solicitud', $idSolicitud)
->where('tipo_firma', $tipoFirma)
->first();

$img = str_replace('data:image/png;base64,', '', $base64);
$fileData = base64_decode($img);
$fileName = uniqid() . '.png';
$firmaDir = self::getFirmaDir();

if (!is_dir($firmaDir)) {
mkdir($firmaDir, 0755, true);
}

if (file_put_contents($firmaDir . '/' . $fileName, $fileData)) {
if ($firmaExistente) {
$oldFile = $firmaDir . '/' . $firmaExistente->firma;
if (file_exists($oldFile)) unlink($oldFile);
$firmaExistente->id_usuario = $idUsuario;
$firmaExistente->firma = $fileName;
$firmaExistente->save();
} else {
SolicitudChequeFirma::create([
'id_solicitud' => $idSolicitud,
'id_usuario' => $idUsuario,
'tipo_firma' => $tipoFirma,
'firma' => $fileName,
'fecha' => Carbon::now(),
]);
}
}
}

private static function procesarDocumentosCrear(int $idSolicitud, int $idUsuario, array $files): void
{
$documentTypes = [
0 => 'PRESUPUESTO',
1 => 'FACTURA PDF',
2 => 'FACTURA XML',
3 => 'CARATULA BANCARIA',
4 => 'CONSTANCIA DE SITUACION',
5 => 'PREFACTURA',
6 => 'ORDEN DE SERVICIO',
7 => 'ORDEN DE COMPRA',
8 => 'ORDEN DE MANTENIMIENTO',
9 => 'PÓLIZA DE GARANTÍA',
10 => 'PRORRATEO',
11 => 'REEMBOLSO CAJA CHICA',
12 => 'COTIZACIÓN',
13 => 'NOTA DE CREDITO PDF',
14 => 'NOTA DE CREDITO XML',
15 => 'CONTRATO',
16 => 'COMPLEMENTO DE PAGO PDF',
17 => 'COMPLEMENTO DE PAGO XML',
18 => 'OPINIÓN DE CUMPLIMIENTO',
];

$uploadDir = self::getUploadDir();
if (!is_dir($uploadDir)) {
mkdir($uploadDir, 0755, true);
}

foreach ($documentTypes as $idx => $nombre) {
$fieldName = 'doc_' . $idx;
if (!empty($files[$fieldName]) && $files[$fieldName]['error'] === UPLOAD_ERR_OK) {
$aleatorio = uniqid();
$archivoNombre = $aleatorio . '-' . $files[$fieldName]['name'];
if (move_uploaded_file($files[$fieldName]['tmp_name'], $uploadDir . '/' . $archivoNombre)) {
SolicitudChequeDocumento::create([
'id_solicitud' => $idSolicitud,
'nombre' => $nombre,
'documento' => $archivoNombre,
]);

if ($nombre === 'COTIZACIÓN') {
self::storeComentarioInterno($idSolicitud, $idUsuario, 'Factura contra entrega');
}
}
}
}
}

private static function procesarDocumentosEditar(int $idSolicitud, int $idUsuario, array $files): void
{
$documentTypes = [
0 => 'PRESUPUESTO',
1 => 'FACTURA PDF',
2 => 'FACTURA XML',
3 => 'CARATULA BANCARIA',
4 => 'CONSTANCIA DE SITUACION',
5 => 'PREFACTURA',
6 => 'ORDEN DE SERVICIO',
7 => 'ORDEN DE COMPRA',
8 => 'ORDEN DE MANTENIMIENTO',
9 => 'PÓLIZA DE GARANTÍA',
10 => 'PRORRATEO',
11 => 'REEMBOLSO CAJA CHICA',
12 => 'COTIZACIÓN',
13 => 'NOTA DE CREDITO PDF',
14 => 'NOTA DE CREDITO XML',
15 => 'CONTRATO',
16 => 'COMPLEMENTO DE PAGO PDF',
17 => 'COMPLEMENTO DE PAGO XML',
18 => 'OPINIÓN DE CUMPLIMIENTO',
];

$uploadDir = self::getUploadDir();
if (!is_dir($uploadDir)) {
mkdir($uploadDir, 0755, true);
}

foreach ($documentTypes as $idx => $nombre) {
$fieldName = 'doc_' . $idx;
if (!empty($files[$fieldName]) && $files[$fieldName]['error'] === UPLOAD_ERR_OK) {
$docExistente = SolicitudChequeDocumento::where('id_solicitud', $idSolicitud)
->where('nombre', $nombre)
->first();

if ($docExistente) {
$oldFile = $uploadDir . '/' . $docExistente->documento;
if (file_exists($oldFile)) unlink($oldFile);
}

$aleatorio = uniqid();
$archivoNombre = $aleatorio . '-' . $files[$fieldName]['name'];
if (move_uploaded_file($files[$fieldName]['tmp_name'], $uploadDir . '/' . $archivoNombre)) {
if ($docExistente) {
$docExistente->documento = $archivoNombre;
$docExistente->save();
} else {
SolicitudChequeDocumento::create([
'id_solicitud' => $idSolicitud,
'nombre' => $nombre,
'documento' => $archivoNombre,
]);

if ($nombre === 'COTIZACIÓN') {
self::storeComentarioInterno($idSolicitud, $idUsuario, 'Factura contra entrega');
}
}
}
}
}
}

private static function eliminarToken(int $idSolicitud): void
{
SolicitudChequeToken::where('id_solicitud', $idSolicitud)->delete();
}

public static function getUploadDir(): string
{
$dir = __DIR__ . '/../../public/uploads/archivos/solicitud-cheque';
if (!is_dir($dir)) {
mkdir($dir, 0755, true);
}
return realpath($dir) ?: $dir;
}

public static function getFirmaDir(): string
{
$dir = __DIR__ . '/../../public/uploads/firmas/solicitud-cheque';
if (!is_dir($dir)) {
mkdir($dir, 0755, true);
}
return realpath($dir) ?: $dir;
}

private static function notificarCreacion(int $idSolicitud, int $idUsuario, string $nameEstacion, int $idEstacion, int $depto, string $fecha, float $monto, string $moneda, string $concepto, string $beneficiario, string $razonSocial = '', string $solicitante = '', string $noFactura = '', int $idYear = 0, int $idMes = 0): void
{
try {
$telegram = new TelegramService();
$nombreMes = nombremes($idMes);

if ($idEstacion == 8) {
$nombreDepto = self::DEPARTMENTS[$depto] ?? 'Desconocido';
$nombreES = '🏢 Departamento: ' . $nombreDepto;
} else {
$estacion = Estacion::find($idEstacion);
$nombreES = '⛽ Estación: ' . ($estacion ? $estacion->nombre : 'Desconocida');
}

$datosUsuario = Usuario::find($idUsuario);
$nombreUsuario = $datosUsuario ? $datosUsuario->nombre : 'Desconocido';
$fechaAhora = Carbon::now()->format('d/m/Y g:i a');

$detalleTelegram =
'✅ Se ha <b>creado</b> una <b>Solicitud de Cheque</b>, correspondiente al periodo de <b>'
. $nombreMes . ' ' . $idYear . '</b>:' . PHP_EOL . PHP_EOL .

'📋 <b>No. de Solicitud:</b> #' . $idSolicitud . PHP_EOL .
'🗓️ <b>Fecha de solicitud:</b> ' . formatearFecha($fecha) . PHP_EOL .
'🏦 <b>Beneficiario:</b> ' . ($beneficiario ?: 'N/A') . PHP_EOL .
'🗒 <b>Concepto:</b> ' . ($concepto ?: 'N/A') . PHP_EOL .
'💰 <b>Monto:</b> $' . number_format($monto, 2) . ' ' . ($moneda ?: '') . PHP_EOL .
'📋 <b>Solicitante:</b> ' . ($solicitante ?: 'N/A') . PHP_EOL .
'🔖 <b>No. de factura:</b> ' . ($noFactura ?: 'N/A') . PHP_EOL . PHP_EOL .

'📋 Nota: En espera de firma del VoBo.' . PHP_EOL .
'👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL .
$nombreES;

$detalleVOBO =
'✅ Se ha <b>creado</b> una <b>Solicitud de Cheque</b>, correspondiente al periodo de <b>'
. $nombreMes . ' ' . $idYear . '</b>:' . PHP_EOL . PHP_EOL .

'📋 <b>No. de Solicitud:</b> #' . $idSolicitud . PHP_EOL .
'🗓️ <b>Fecha de solicitud:</b> ' . formatearFecha($fecha) . PHP_EOL .
'🏦 <b>Beneficiario:</b> ' . ($beneficiario ?: 'N/A') . PHP_EOL .
'🗒 <b>Concepto:</b> ' . ($concepto ?: 'N/A') . PHP_EOL .
'💰 <b>Monto:</b> $' . number_format($monto, 2) . ' ' . ($moneda ?: '') . PHP_EOL .
'📋 <b>Solicitante:</b> ' . ($solicitante ?: 'N/A') . PHP_EOL .
'🔖 <b>No. de factura:</b> ' . ($noFactura ?: 'N/A') . PHP_EOL . PHP_EOL .

'🔔 Nota: Debes firmar el VoBo de esta solicitud.' . PHP_EOL .
'👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL .
$nombreES;

$userIds = [];

if ($idEstacion == 8) {
if ($depto == 5) {
$userIds = $telegram->getUserIdsGestoria($idUsuario);
$telegram->sendToken(30, $detalleVOBO);
} else {
$telegram->sendToken(19, $detalleVOBO);
}
} else {
$userIds = $telegram->getUserIdsByStation($idEstacion, $idUsuario);
$telegram->sendToken(19, $detalleVOBO);

if (in_array($idEstacion, [6, 7])) {
$extraIds = $telegram->getUserIdsComercializadora($idUsuario);
if (!empty($extraIds)) {
$telegram->sendMessageToMultiple($extraIds, $detalleTelegram);
}
} elseif (in_array($idEstacion, [1, 2, 3, 4, 5, 14])) {
$extraIds = $telegram->getUserIdsContabilidad($idUsuario);
if (!empty($extraIds)) {
$telegram->sendMessageToMultiple($extraIds, $detalleTelegram);
}
}
}

if (!empty($userIds)) {
$telegram->sendMessageToMultiple($userIds, $detalleTelegram);
}
} catch (\Throwable $e) {
error_log('Error en notificarCreacion: ' . $e->getMessage());
}
}

private static function notificarActualizacion(int $idSolicitud, int $idUsuario, string $nameEstacion): void
{
try {
$solicitud = SolicitudCheque::find($idSolicitud);
if (!$solicitud) return;

$telegram = new TelegramService();
$nombreMes = nombremes($solicitud->id_mes);

$idEstacion = $solicitud->id_estacion;
$depto = $solicitud->depto;

if ($idEstacion == 8) {
$nombreDepto = self::DEPARTMENTS[$depto] ?? 'Desconocido';
$nombreES = '🏢 Departamento: ' . $nombreDepto;
} else {
$estacion = Estacion::find($idEstacion);
$nombreES = '⛽ Estación: ' . ($estacion ? $estacion->nombre : 'Desconocida');
}

$datosUsuario = Usuario::find($idUsuario);
$nombreUsuario = $datosUsuario ? $datosUsuario->nombre : 'Desconocido';

$detalleTelegram =
'🔄 Se ha <b>editado</b> una <b>Solicitud de Cheque</b>, correspondiente al periodo de <b>'
. $nombreMes . ' ' . ($solicitud->id_year ?? '') . '</b>:' . PHP_EOL . PHP_EOL .

'📋 <b>No. de Solicitud:</b> #' . $idSolicitud . PHP_EOL .
'🗓️ <b>Fecha de solicitud:</b> ' . formatearFecha($solicitud->fecha ?? '') . PHP_EOL .
'🏦 <b>Beneficiario:</b> ' . ($solicitud->beneficiario ?? 'N/A') . PHP_EOL .
'🗒 <b>Concepto:</b> ' . ($solicitud->concepto ?? 'N/A') . PHP_EOL .
'💰 <b>Monto:</b> $' . number_format((float)$solicitud->monto, 2) . ' ' . ($solicitud->moneda ?? '') . PHP_EOL .
'📋 <b>Solicitante:</b> ' . ($solicitud->solicitante ?? 'N/A') . PHP_EOL .
'🔖 <b>No. de factura:</b> ' . ($solicitud->no_factura ?? 'N/A') . PHP_EOL .
'💳 <b>Método de pago:</b> ' . ($solicitud->metodo_pago ?? 'N/A') . PHP_EOL . PHP_EOL .

'👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL .
$nombreES;

$userIds = [];

if ($idEstacion == 8) {
if ($depto == 5) {
$userIds = $telegram->getUserIdsGestoria($idUsuario);
$telegram->sendToken(30, $detalleTelegram);
} else {
$telegram->sendToken(19, $detalleTelegram);
}
} else {
$userIds = $telegram->getUserIdsByStation($idEstacion, $idUsuario);
$telegram->sendToken(19, $detalleTelegram);

if (in_array($idEstacion, [6, 7])) {
$extraIds = $telegram->getUserIdsComercializadora($idUsuario);
if (!empty($extraIds)) {
$telegram->sendMessageToMultiple($extraIds, $detalleTelegram);
}
} elseif (in_array($idEstacion, [1, 2, 3, 4, 5, 14])) {
$extraIds = $telegram->getUserIdsContabilidad($idUsuario);
if (!empty($extraIds)) {
$telegram->sendMessageToMultiple($extraIds, $detalleTelegram);
}
}
}

if (!empty($userIds)) {
$telegram->sendMessageToMultiple($userIds, $detalleTelegram);
}
} catch (\Throwable $e) {
error_log('Error en notificarActualizacion: ' . $e->getMessage());
}
}

private static function notificarEliminacion(SolicitudCheque $solicitud,int $idUsuario): void
{
try {

$telegram = new TelegramService();
$datosUsuario = Usuario::find($idUsuario);
$nombreUsuario = $datosUsuario ? $datosUsuario->nombre : 'Desconocido';

if ($solicitud->id_estacion == 8) {
$nombreDepto = self::DEPARTMENTS[$solicitud->depto] ?? 'Desconocido';
$nombreES = '🏢 <b>Departamento:</b> ' . $nombreDepto;

} else {
$estacion = Estacion::find($solicitud->id_estacion);
$nombreES = '⛽ <b>Estación:</b> ' . ($estacion ? $estacion->nombre : 'Desconocida');

}

$nombreMes = nombremes($solicitud->id_mes);

$detalleTelegram =
'🗑️ Se ha <b>eliminado</b> una <b>Solicitud de Cheque</b>, correspondiente al periodo de <b>'
. $nombreMes . ' ' . $solicitud->id_year . '</b>:' . PHP_EOL . PHP_EOL .

'📋 <b>No. de Solicitud:</b> #' . $solicitud->id . PHP_EOL .
'🗓️ <b>Fecha de solicitud:</b> ' . formatearFecha($solicitud->fecha) . PHP_EOL .
'🏦 <b>Beneficiario:</b> ' . ($solicitud->beneficiario ?: 'N/A') . PHP_EOL .
'🗒 <b>Concepto:</b> ' . ($solicitud->concepto ?: 'N/A') . PHP_EOL .
'💰 <b>Monto:</b> $' . number_format((float)$solicitud->monto, 2) . ' ' . ($solicitud->moneda ?: '') . PHP_EOL .
'📋 <b>Solicitante:</b> ' . ($solicitud->solicitante ?: 'N/A') . PHP_EOL .
'🔖 <b>No. de factura:</b> ' . ($solicitud->no_factura ?: 'N/A') . PHP_EOL . PHP_EOL .

'👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL .
$nombreES;

$userIds = [];

if ($solicitud->id_estacion == 8) {

if ($solicitud->depto == 5) {
$userIds = $telegram->getUserIdsGestoria($idUsuario);
$telegram->sendToken(30, $detalleTelegram);

} else {
$telegram->sendToken(19, $detalleTelegram);

}

} else {

$userIds = $telegram->getUserIdsByStation($solicitud->id_estacion, $idUsuario);
$telegram->sendToken(19, $detalleTelegram);

if (in_array($solicitud->id_estacion, [6, 7])) {

$extraIds = $telegram->getUserIdsComercializadora($idUsuario);

if (!empty($extraIds)) {
$telegram->sendMessageToMultiple($extraIds, $detalleTelegram);
}

} elseif (in_array($solicitud->id_estacion, [1, 2, 3, 4, 5, 14])) {

$extraIds = $telegram->getUserIdsContabilidad($idUsuario);

if (!empty($extraIds)) {
$telegram->sendMessageToMultiple($extraIds, $detalleTelegram);
}
}
}

if (!empty($userIds)) {
$telegram->sendMessageToMultiple($userIds, $detalleTelegram);
}

} catch (\Throwable $e) {

error_log('Error en notificarEliminacion: ' . $e->getMessage());

}
}

private static function notificarComentario(int $idSolicitud, int $idUsuario, string $comentario): void
{
try {
$solicitud = SolicitudCheque::find($idSolicitud);
if (!$solicitud) return;

$telegram = new TelegramService();
$datosUsuario = Usuario::find($idUsuario);
$nombreUsuario = $datosUsuario ? $datosUsuario->nombre : 'Desconocido';
$nombreMes = nombremes($solicitud->id_mes);

$idEstacion = $solicitud->id_estacion;
$depto = $solicitud->depto;

if ($idEstacion == 8) {
$nombreDepto = self::DEPARTMENTS[$depto] ?? 'Desconocido';
$nombreES = '🏢 Departamento: ' . $nombreDepto;
} else {
$estacion = Estacion::find($idEstacion);
$nombreES = '⛽ Estación: ' . ($estacion ? $estacion->nombre : 'Desconocida');
}

$detalleTelegram =
'💬 Se ha agregado un comentario al apartado de <b>Solicitud de Cheque</b>, correspondiente al periodo de <b>' . $nombreMes .  ' '.($solicitud->id_year ?? '').'</b>:' . PHP_EOL . PHP_EOL .

'📋 No. de Solicitud: #' . $idSolicitud . PHP_EOL .
'🗓️ <b>Fecha de solicitud:</b> ' . formatearFecha($solicitud->fecha ?? '')  . PHP_EOL .
'🏦 <b>Beneficiario:</b> ' . ($solicitud->beneficiario ?? 'N/A') . PHP_EOL . 
'🗒 <b>Concepto:</b> ' . ($solicitud->concepto ?? 'N/A') . PHP_EOL . 
'💰 <b>Monto:</b> $' . number_format((float)$solicitud->monto, 2) . ' ' . ($solicitud->moneda ?? '') . PHP_EOL . PHP_EOL .

'💬 <b>Comentario:</b> ' . $comentario . PHP_EOL .
'👤 <b>Responsable:</b> ' . $nombreUsuario . PHP_EOL . 
$nombreES;

if ($idEstacion == 8 && $depto == 5) {
$userIds = $telegram->getUserIdsGestoria($idUsuario);
$telegram->sendToken(30, $detalleTelegram);
} else {
$userIds = $telegram->getUserIdsByStation($idEstacion, $idUsuario);
$telegram->sendToken(19, $detalleTelegram);

if (in_array($idEstacion, [6, 7])) {
$extraIds = $telegram->getUserIdsComercializadora($idUsuario);
if (!empty($extraIds)) {
$telegram->sendMessageToMultiple($extraIds, $detalleTelegram);
}
} elseif (in_array($idEstacion, [1, 2, 3, 4, 5, 14])) {
$extraIds = $telegram->getUserIdsContabilidad($idUsuario);
if (!empty($extraIds)) {
$telegram->sendMessageToMultiple($extraIds, $detalleTelegram);
}
}
}

if (!empty($userIds)) {
$telegram->sendMessageToMultiple($userIds, $detalleTelegram);
}
} catch (\Throwable $e) {
error_log('Error en notificarComentario: ' . $e->getMessage());
}
}

private static function numeroALetras($number, $moneda): string
{
$moneda = strtoupper(trim($moneda));
if ($moneda === 'DLLS' || $moneda === 'USD') {
$tipoMoneda = 'DOLARES';
$divisa = 'USD';
} else {
$tipoMoneda = 'PESOS';
$divisa = 'M.N';
}

if ($number < 0 || $number > 999999999) {
return 'No es posible convertir el numero a letras';
}

$numStr = number_format((float)$number, 2, '.', '');
$parts = explode('.', $numStr);
$entero = (int)$parts[0];
$decimal = $parts[1] ?? '00';

$letras = self::convertirGrupo($entero);
$resultado = $letras . ' ' . $tipoMoneda . ' ' . $decimal . '/100 ' . $divisa;
return trim($resultado);
}

private static function convertirGrupo($n): string
{
$unidades = ['', 'UN ', 'DOS ', 'TRES ', 'CUATRO ', 'CINCO ', 'SEIS ', 'SIETE ', 'OCHO ', 'NUEVE ', 'DIEZ ',
'ONCE ', 'DOCE ', 'TRECE ', 'CATORCE ', 'QUINCE ', 'DIECISEIS ', 'DIECISIETE ', 'DIECIOCHO ', 'DIECINUEVE ', 'VEINTE '];
$decenas = ['VENTI', 'TREINTA ', 'CUARENTA ', 'CINCUENTA ', 'SESENTA ', 'SETENTA ', 'OCHENTA ', 'NOVENTA '];
$centenas = ['CIENTO ', 'DOSCIENTOS ', 'TRESCIENTOS ', 'CUATROCIENTOS ', 'QUINIENTOS ', 'SEISCIENTOS ', 'SETECIENTOS ', 'OCHOCIENTOS ', 'NOVECIENTOS '];

if ($n == 0) return 'CERO ';
if ($n == 100) return 'CIEN ';

$str = (string)$n;
$len = strlen($str);

$grupos = [];
$pos = $len;
while ($pos > 0) {
$start = max(0, $pos - 3);
$grupos[] = (int)substr($str, $start, $pos - $start);
$pos = $start;
}

$output = '';

if (isset($grupos[2]) && $grupos[2] > 0) {
if ($grupos[2] == 1) {
$output .= 'UN MILLON ';
} else {
$output .= self::convertirCientos($grupos[2], $unidades, $decenas, $centenas) . 'MILLONES ';
}
}

if (isset($grupos[1]) && $grupos[1] > 0) {
if ($grupos[1] == 1) {
$output .= 'MIL ';
} else {
$output .= self::convertirCientos($grupos[1], $unidades, $decenas, $centenas) . 'MIL ';
}
}

if (isset($grupos[0]) && $grupos[0] > 0) {
if ($grupos[0] == 1 && !isset($grupos[1]) && !isset($grupos[2])) {
$output .= 'UN ';
} else {
$output .= self::convertirCientos($grupos[0], $unidades, $decenas, $centenas);
}
}

return $output;
}

private static function convertirCientos($n, $unidades, $decenas, $centenas): string
{
$output = '';
if ($n >= 100) {
$c = (int)($n / 100);
if ($n == 100) return 'CIEN ';
$output .= $centenas[$c - 1];
$n %= 100;
}
if ($n > 0) {
if ($n <= 20) {
$output .= $unidades[$n];
} else {
$d = (int)($n / 10);
$u = $n % 10;
if ($d >= 2 && $d <= 9) {
if ($u > 0 && $n > 30) {
$output .= $decenas[$d - 2] . 'Y ' . $unidades[$u];
} else {
$output .= $decenas[$d - 2] . $unidades[$u];
}
}
}
}
return $output;
}

private static function getMesContext(int $idMes): ?array
{
$corteMes = CorteMes::with('year')->find($idMes);
if (!$corteMes || !$corteMes->year) return null;
return [
'id_estacion' => $corteMes->year->id_estacion,
'id_year' => $corteMes->year->year,
'id_mes' => $corteMes->mes,
];
}

public static function getFacturaTelcelStatus(int $idEstacion, int $idYear, int $idMes): array
{
$idReporte = self::getMesId($idEstacion, $idYear, $idMes);
if (!$idReporte) return ['total' => 0, 'tiene_factura' => false, 'tiene_pago' => false];
$detalles = FacturaTelcel::where('id_mes', $idReporte)->pluck('detalle')->toArray();
return [
'total' => count($detalles),
'tiene_factura' => in_array('Factura', $detalles),
'tiene_pago' => in_array('Pago', $detalles),
];
}

public static function getDirectorio(int $idEstacion, int $idYear, int $idMes): array
{
$idReporte = self::getMesId($idEstacion, $idYear, $idMes);
if (!$idReporte) return [];
return Directorio::where('id_mes', $idReporte)->orderBy('id', 'asc')->get()->toArray();
}

public static function storeDirectorio(int $idEstacion, int $idYear, int $idMes, array $data): array
{
$idReporte = self::ensureMesId($idEstacion, $idYear, $idMes);
try {
Directorio::create([
'id_mes' => $idReporte,
'cuenta' => $data['cuenta'] ?? '',
'puesto' => $data['puesto'] ?? '',
'clave' => $data['clave'] ?? '',
]);
$usuario = Auth::user();
if ($usuario) {
self::notificarFacturaTelcel($idEstacion, $idYear, $idMes, $usuario->id, 'agregar_directorio');
}
return ['success' => true, 'message' => 'Directorio agregado.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
}
}

public static function updateDirectorio(int $id, array $data): array
{
$row = Directorio::find($id);
if (!$row) return ['success' => false, 'message' => 'No encontrado'];
try {
$row->cuenta = $data['cuenta'] ?? $row->cuenta;
$row->puesto = $data['puesto'] ?? $row->puesto;
$row->clave = $data['clave'] ?? $row->clave;
$row->save();
$usuario = Auth::user();
if ($usuario) {
$ctx = self::getMesContext($row->id_mes);
if ($ctx) {
self::notificarFacturaTelcel($ctx['id_estacion'], $ctx['id_year'], $ctx['id_mes'], $usuario->id, 'editar_directorio');
}
}
return ['success' => true, 'message' => 'Directorio actualizado.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
}
}

public static function deleteDirectorio(int $id): array
{
$row = Directorio::find($id);
if (!$row) return ['success' => false, 'message' => 'No encontrado'];
$idMes = $row->id_mes;
try {
$row->delete();
$usuario = Auth::user();
if ($usuario) {
$ctx = self::getMesContext($idMes);
if ($ctx) {
self::notificarFacturaTelcel($ctx['id_estacion'], $ctx['id_year'], $ctx['id_mes'], $usuario->id, 'eliminar_directorio');
}
}
return ['success' => true, 'message' => 'Directorio eliminado.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
}
}

public static function getFacturasTelcelList(int $idEstacion, int $idYear, int $idMes): array
{
$idReporte = self::getMesId($idEstacion, $idYear, $idMes);
if (!$idReporte) return [];
return FacturaTelcel::where('id_mes', $idReporte)->orderBy('id', 'desc')->get()->toArray();
}

public static function storeFacturaTelcel(int $idEstacion, int $idYear, int $idMes, array $data, array $files): array
{
$idReporte = self::ensureMesId($idEstacion, $idYear, $idMes);
$factura = '';
if (!empty($files['documento']) && $files['documento']['error'] === UPLOAD_ERR_OK) {
$aleatorio = uniqid();
$nombreArchivo = $aleatorio . '-' . $files['documento']['name'];
$uploadDir = self::getUploadDir();
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
if (move_uploaded_file($files['documento']['tmp_name'], $uploadDir . '/' . $nombreArchivo)) {
$factura = $nombreArchivo;
}
}
try {
FacturaTelcel::create([
'id_mes' => $idReporte,
'detalle' => $data['detalle'] ?? '',
'factura' => $factura,
]);
$usuario = Auth::user();
if ($usuario) {
self::notificarFacturaTelcel($idEstacion, $idYear, $idMes, $usuario->id, 'agregar_factura', $data['detalle'] ?? '');
}
return ['success' => true, 'message' => 'Factura agregada.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
}
}

public static function deleteFacturaTelcel(int $id): array
{
$row = FacturaTelcel::find($id);
if (!$row) return ['success' => false, 'message' => 'No encontrado'];
$detalle = $row->detalle;
$idMes = $row->id_mes;
$uploadDir = self::getUploadDir();
if ($row->factura) {
$filePath = $uploadDir . '/' . $row->factura;
if (file_exists($filePath)) unlink($filePath);
}
try {
$row->delete();
$usuario = Auth::user();
if ($usuario) {
$ctx = self::getMesContext($idMes);
if ($ctx) {
self::notificarFacturaTelcel($ctx['id_estacion'], $ctx['id_year'], $ctx['id_mes'], $usuario->id, 'eliminar_factura', $detalle);
}
}
return ['success' => true, 'message' => 'Factura eliminada.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
}
}

public static function getFacturaTelcelComentario(int $idEstacion, int $idYear, int $idMes): string
{
$idReporte = self::ensureMesId($idEstacion, $idYear, $idMes);
$row = FacturaTelcelComentario::where('id_mes', $idReporte)->first();
if (!$row) {
try {
$row = FacturaTelcelComentario::create(['id_mes' => $idReporte, 'comentario' => '']);
} catch (\Exception $e) {
return '';
}
}
return $row->comentario ?? '';
}

public static function storeFacturaTelcelComentario(int $idEstacion, int $idYear, int $idMes, string $comentario): array
{
$idReporte = self::ensureMesId($idEstacion, $idYear, $idMes);
$row = FacturaTelcelComentario::where('id_mes', $idReporte)->first();
if (!$row) {
try {
$row = FacturaTelcelComentario::create(['id_mes' => $idReporte, 'comentario' => '']);
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
}
}
try {
$row->comentario = $comentario;
$row->save();
return ['success' => true, 'message' => 'Comentario guardado.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
}
}
}
