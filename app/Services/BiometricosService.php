<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Estacion;
use App\Models\Operativo\RhListaIncidencias;
use App\Models\Operativo\RhLocalidad;
use App\Models\Operativo\RhPersonal;
use App\Models\Operativo\RhPersonalAsistencia;
use App\Models\Operativo\RhPuestos;
use App\Models\Operativo\RhLocalidadesHorario;
use App\Models\Operativo\RhLocalidadesPerfil;
use App\Models\Operativo\RhLocalidadesRetardoIncidencia;
use App\Models\Operativo\RhPersonalAsistenciaIncidencia;
use App\Services\ModuloDptoOperativoService;

class BiometricosService
{
public const MODULE_KEY = 'biometricos';

public const ESTACIONES_SOPORTADAS = [1, 2, 3, 4, 5, 6, 7, 14];

public const DEPTO_AUTOLAVADO = 9;

/** Personal excluido de la vista (misma lista que la versión anterior). */
public const EXCLUIDOS = [387, 358, 296, 326, 300, 335];

public static function getPermisos(): array
{
$usuario = Auth::user();
$sessionUsuario = Session::get('usuario');
$idUsuario = (int)($sessionUsuario['id'] ?? 0);
$idPuesto = (int)($usuario->id_puesto ?? 0);
$idEstacion = (int)($sessionUsuario['id_estacion'] ?? 0);
$multiestacion = !empty($sessionUsuario['multiestacion']);
$nombrePuesto = $usuario->puesto->tipo_puesto ?? '';

$permisosDb = ModuloDptoOperativoService::permisosSesion('recursos-humanos');

return [
'id_usuario'     => $idUsuario,
'id_estacion'    => $idEstacion,
'id_puesto'      => $idPuesto,
'nombre_puesto'  => $nombrePuesto,
'multiestacion'  => $multiestacion,
'puedeCrear'     => !empty($permisosDb['crear']),
'puedeEditar'    => !empty($permisosDb['editar']),
'puedeEliminar'  => !empty($permisosDb['eliminar']),
'puedeDescargar' => !empty($permisosDb['descargar']),
];
}

public static function getContexto(): array
{
$ctx = ModuleStationService::getContext(self::MODULE_KEY);
$idEstacion = $ctx['id_estacion'];
$idDepto = $ctx['id_depto'];

if (self::esTodasPaloSolo()) {
$idEstacion = null;
$idDepto = null;
}

$tipo = 'todas';
if ($idEstacion && !$idDepto) {
$tipo = 'estacion';
} elseif ($idDepto) {
$tipo = 'depto';
}

return [
'id_estacion' => $idEstacion,
'id_depto'    => $idDepto,
'tipo'        => $tipo,
'nombre'      => $ctx['nombre'],
];
}

/**
* Solo usuarios con id_gas = 2 (Palo Solo) que NO son multiestación
* y seleccionaron explícitamente "TODAS LAS ESTACIONES Y DEPARTAMENTOS":
* el contexto se guarda como null/null y ModuleStationService::getContext()
* lo reinterpreta como "mi estación" (Palo Solo). Aquí se restaura el
* comportamiento real de "TODAS" únicamente para este caso.
*/
private static function esTodasPaloSolo(): bool
{
$sessionUsuario = Session::get('usuario');
$idGas = $sessionUsuario['id_estacion'] ?? null;
if ($idGas === null) {
$usuario = Auth::user();
$idGas = $usuario ? $usuario->id_gas : null;
}
if ((int)$idGas !== 2) {
return false;
}

if (MultiestacionService::isEnabled()) {
return false;
}

$contexto = Session::get('module_context') ?? [];
if (!array_key_exists(self::MODULE_KEY, $contexto)) {
return false;
}

$raw = $contexto[self::MODULE_KEY];

return empty($raw['id_estacion']) && empty($raw['id_depto']);
}

private static function getIdsPermitidos(): array
{
$ids = [];

foreach (ModuleStationService::getAvailableStations(self::MODULE_KEY) as $s) {
$ids[] = (int)$s['id'];
}
foreach (ModuleStationService::getAvailableDepartments(self::MODULE_KEY) as $d) {
$ids[] = (int)$d['id'];
}

return array_values(array_unique($ids));
}

public static function resolveNombreEstacion(int $idEstacion): string
{
$mapa = self::getMapaNombresEstaciones([$idEstacion]);

return $mapa[$idEstacion] ?? '';
}

/**
* Mapa de nombres de estaciones/departamentos resuelto con una sola
* consulta (evita N+1 en listados y reportes).
*/
public static function getMapaNombresEstaciones(array $ids): array
{
$mapa = [];
if (empty($ids)) {
return $mapa;
}

foreach (RhLocalidad::whereIn('id', $ids)->get() as $loc) {
$mapa[(int)$loc->id] = $loc->localidad;
}

$faltantes = array_values(array_diff(array_map('intval', $ids), array_keys($mapa)));
if (!empty($faltantes)) {
foreach (Estacion::whereIn('id', $faltantes)->get() as $est) {
$mapa[(int)$est->id] = $est->nombre;
}
}

return $mapa;
}

public static function getMesActual(): array
{
// Mínimo requerido: mes en curso + mes anterior.
$inicio = date('Y-m-01', strtotime('first day of last month'));
$fin = date('Y-m-t');

// Si el periodo (mes anterior + mes actual) aún no tiene registros
// (sincronización pendiente), se muestra el último mes con datos disponibles.
if (!RhPersonalAsistencia::whereBetween('fecha', [$inicio, $fin])->exists()) {
$ultima = RhPersonalAsistencia::orderByDesc('fecha')->value('fecha');
if ($ultima && $ultima !== '0000-00-00' && (string)$ultima < $inicio) {
$mesConDatos = substr((string)$ultima, 0, 7) . '-01';
$inicio = $mesConDatos;
$fin = date('Y-m-t', strtotime($mesConDatos));
}
}

return [
'inicio' => $inicio,
'fin'    => $fin,
];
}

public static function formatearHora($hora): string
{
if (empty($hora) || $hora === '00:00:00') {
return 'S/I';
}
return date('g:i a', strtotime((string)$hora));
}

public static function getDatos(): array
{
$ctx = self::getContexto();
$mes = self::getMesActual();

$query = RhPersonalAsistencia::query()
->join('op_rh_personal', 'op_rh_personal_asistencia.id_personal', '=', 'op_rh_personal.id')
->whereBetween('op_rh_personal_asistencia.fecha', [$mes['inicio'], $mes['fin']]);

if ($ctx['tipo'] === 'estacion') {
$query->where('op_rh_personal.id_estacion', $ctx['id_estacion']);
} elseif ($ctx['tipo'] === 'depto') {
$query->where('op_rh_personal.id_estacion', $ctx['id_depto']);
} else {
$ids = self::getIdsPermitidos();
if (!empty($ids)) {
$query->whereIn('op_rh_personal.id_estacion', $ids);
}
}

$registros = $query->select([
'op_rh_personal_asistencia.*',
'op_rh_personal.nombre_completo',
'op_rh_personal.id_estacion',
])
->orderByDesc('op_rh_personal_asistencia.fecha')
->orderBy('op_rh_personal.nombre_completo', 'asc')
->get();

$idsAsistencia = $registros->pluck('id')->all();
$mapaIncidencia = [];
if (!empty($idsAsistencia)) {
$conIncidencia = RhPersonalAsistenciaIncidencia::whereIn('id_asistencia', $idsAsistencia)
->where('estado', 1)
->selectRaw('id_asistencia, COUNT(*) as total')
->groupBy('id_asistencia')
->get();
foreach ($conIncidencia as $inc) {
$mapaIncidencia[(int)$inc->id_asistencia] = (int)$inc->total;
}
}

$idsEstaciones = $registros->pluck('id_estacion')->unique()->map('intval')->all();
$mapaNombres = self::getMapaNombresEstaciones($idsEstaciones);

$rows = [];
foreach ($registros as $a) {
$detalle = ControlDocumentosPersonalService::getDetalleAsistencia(
(string)$a->fecha,
(string)$a->hora_entrada,
(string)$a->hora_salida,
(string)$a->hora_entrada_sensor,
(string)$a->hora_salida_sensor,
(int)$a->retardo_minutos
);

$idEstacion = (int)$a->id_estacion;
$rows[] = [
'id'                  => (int)$a->id,
'id_personal'         => (int)$a->id_personal,
'id_estacion'         => $idEstacion,
'nombre_estacion'     => $mapaNombres[$idEstacion] ?? ('Estación #' . $idEstacion),
'nombre_completo'     => $a->nombre_completo,
'fecha'               => formatearFecha($a->fecha),
'fecha_raw'           => (string)$a->fecha,
'hora_entrada'        => self::formatearHora($a->hora_entrada),
'hora_salida'         => self::formatearHora($a->hora_salida),
'hora_entrada_sensor' => self::formatearHora($a->hora_entrada_sensor),
'hora_salida_sensor'  => self::formatearHora($a->hora_salida_sensor),
'retardo_minutos'     => (int)$a->retardo_minutos,
'incidencia_dias'     => (int)$a->incidencia_dias,
'sd'                  => (float)$a->sd,
'detalle'             => $detalle,
'detalle_badge'       => ControlDocumentosPersonalService::getDetalleBadgeClass($detalle),
'total_incidencias'   => $mapaIncidencia[$a->id] ?? 0,
];
}

return ['contexto' => $ctx, 'rows' => $rows];
}

public static function editarSueldoIncidencia(int $idAsistencia, float $sueldoDia): array
{
$asistencia = RhPersonalAsistencia::find($idAsistencia);
if (!$asistencia) {
return ['success' => false, 'message' => 'No se encontró el registro de asistencia.'];
}

if ($sueldoDia < 0) {
return ['success' => false, 'message' => 'El sueldo día no puede ser negativo.'];
}

try {
$asistencia->incidencia = $sueldoDia;
$asistencia->save();
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al actualizar el sueldo día: ' . $e->getMessage()];
}

$personal = RhPersonal::find($asistencia->id_personal);
$nombrePersonal = $personal ? $personal->nombre_completo : 'Desconocido';
$usuario = Session::get('nombre') ?? (Auth::user()?->nombre ?? 'Sistema');
$fechaFmt = $asistencia->fecha ? formatearFecha($asistencia->fecha) : 'S/I';

$mensaje = "💰 *Sueldo día actualizado*\n";
$mensaje .= "━━━━━━━━━━━━━━━━━━━━━━\n";
$mensaje .= "👤 *Personal:* {$nombrePersonal}\n";
$mensaje .= "📅 *Fecha:* {$fechaFmt}\n";
$mensaje .= "💵 *Sueldo día:* $" . number_format($sueldoDia, 2) . "\n";
$mensaje .= "📝 *Registró:* {$usuario}";

try {
TelegramService::notificar((int)$asistencia->id_estacion, (int)(Session::get('id_usuario') ?? 0), $mensaje);
} catch (\Throwable $e) {
error_log("Error Telegram sueldo día: " . $e->getMessage());
}

return ['success' => true, 'message' => 'Sueldo día actualizado correctamente.'];
}

public static function eliminarIncidencia(int $idAsistencia): array
{
$incidencia = RhPersonalAsistenciaIncidencia::where('id_asistencia', $idAsistencia)
->where('estado', 1)
->orderBy('id', 'desc')
->first();

if (!$incidencia) {
return ['success' => false, 'message' => 'No se encontró una incidencia registrada para este día.'];
}

$asistencia = RhPersonalAsistencia::find($idAsistencia);

try {
$incidencia->estado = 0;
$incidencia->save();

if ($asistencia) {
$asistencia->incidencia = 0;
$asistencia->save();
}
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al eliminar la incidencia: ' . $e->getMessage()];
}

$personal = $asistencia ? RhPersonal::find($asistencia->id_personal) : null;
$nombrePersonal = $personal ? $personal->nombre_completo : 'Desconocido';
$usuario = Session::get('nombre') ?? (Auth::user()?->nombre ?? 'Sistema');
$fechaFmt = $asistencia && $asistencia->fecha ? formatearFecha($asistencia->fecha) : 'S/I';

$mensaje = "🗑️ *Incidencia eliminada*\n";
$mensaje .= "━━━━━━━━━━━━━━━━━━━━━━\n";
$mensaje .= "👤 *Personal:* {$nombrePersonal}\n";
$mensaje .= "📅 *Fecha:* {$fechaFmt}\n";
$mensaje .= "⚠️ *Incidencia:* " . ($incidencia->incidencia ?? '') . "\n";
$mensaje .= "📝 *Eliminó:* {$usuario}";

try {
TelegramService::notificar((int)($asistencia->id_estacion ?? 0), (int)(Session::get('id_usuario') ?? 0), $mensaje);
} catch (\Throwable $e) {
error_log("Error Telegram eliminar incidencia: " . $e->getMessage());
}

return ['success' => true, 'message' => 'Incidencia eliminada correctamente.'];
}

/* ---------------- REPORTES ---------------- */

/**
* Semanas (ISO) que pertenecen al mes. Inicia en el primer "this Wednesday".
*/
public static function getSemanasDelMes(int $mes, int $year): array
{
$primerDia = strtotime($year . '-' . str_pad((string)$mes, 2, '0', STR_PAD_LEFT) . '-01');
$primerDia = strtotime('this Wednesday', $primerDia);
$semanas = [];

for ($currentDate = $primerDia; (int)date('m', $currentDate) === $mes; $currentDate = strtotime('+1 week', $currentDate)) {
$semana = (int)date('W', $currentDate);
if (!in_array($semana, $semanas)) {
$semanas[] = $semana;
}
}

return $semanas;
}

/**
* Fechas (jueves a miércoles) de una semana de nómina.
*/
public static function fechasNominaSemana(int $year, int $semana): array
{
$inicioDay = new \DateTime();
$inicioDay->setISODate($year, $semana, 1);
$inicioDay->modify('last thursday');

$finDay = clone $inicioDay;
$finDay->modify('+6 days');

return [
'inicio' => $inicioDay->format('Y-m-d'),
'fin'    => $finDay->format('Y-m-d'),
];
}

/**
* Estaciones/departamentos a incluir en el reporte según el contexto.
*/
public static function getEstacionesReporte(): array
{
$ctx = self::getContexto();

if ($ctx['tipo'] === 'estacion') {
return [$ctx['id_estacion']];
}
if ($ctx['tipo'] === 'depto') {
return [$ctx['id_depto']];
}

return self::getIdsPermitidos();
}

/**
* Personal + mapa personal → asistencias del mes para acelerar el reporte.
*/
private static function getAsistenciasMes(int $idEstacion, int $year, int $mes): array
{
$personal = RhPersonal::where('id_estacion', $idEstacion)
->where('estado', 1)
->whereNotIn('id', self::EXCLUIDOS)
->orderBy('nombre_completo', 'asc')
->get();

if ($personal->isEmpty()) {
return [[], [], $personal];
}

$ids = $personal->pluck('id')->all();

$asistencias = RhPersonalAsistencia::whereIn('id_personal', $ids)
->whereBetween('fecha', [$year . '-' . str_pad((string)$mes, 2, '0', STR_PAD_LEFT) . '-01', $year . '-' . str_pad((string)$mes, 2, '0', STR_PAD_LEFT) . '-31'])
->get();

$mapa = [];
foreach ($asistencias as $a) {
$mapa[$a->id_personal . '|' . $a->fecha] = $a;
}

return [$ids, $mapa, $personal];
}

private static function getCatalogoIncidenciaMap(): array
{
$mapa = [];
foreach (RhListaIncidencias::all() as $item) {
$mapa[(int)$item->id] = (string)$item->detalle;
}
return $mapa;
}

/**
* Color de texto del detalle en reportes/PDF (misma lógica que la versión anterior).
*/
public static function colorTextoDetalle(string $detalle): string
{
switch ($detalle) {
case 'Día trabajado':       return 'font-weight-bold text-success';
case 'OK':                  return 'font-weight-bold text-success';
case 'Retardo':             return 'text-warning';
case 'Descanso':            return 'text-secondary';
case 'Falta':               return 'text-danger';
case 'Falta fin de semana': return 'text-danger';
default:                    return 'text-black';
}
}

private static function detalleReporte($a, array $catalogoMap): array
{
if (!$a) {
return ['detalle' => 'S/I', 'cls' => ''];
}

$incId = (int)$a->incidencia;
if ($incId > 0 && isset($catalogoMap[$incId])) {
$detalle = $catalogoMap[$incId];
return ['detalle' => $detalle, 'cls' => self::colorTextoDetalle($detalle)];
}

$detalle = ControlDocumentosPersonalService::getDetalleAsistencia(
(string)$a->fecha,
(string)$a->hora_entrada,
(string)$a->hora_salida,
(string)$a->hora_entrada_sensor,
(string)$a->hora_salida_sensor,
(int)$a->retardo_minutos
);

return ['detalle' => $detalle, 'cls' => self::colorTextoDetalle($detalle)];
}

/**
* Los puestos Encargado y Asistente Administrativo no ven el
* "Reporte Estaciones" (misma regla que la versión anterior).
*/
public static function ocultaReporteEstaciones(array $permisos = []): bool
{
if (empty($permisos)) {
$permisos = self::getPermisos();
}

$nombrePuesto = strtolower(trim((string)($permisos['nombre_puesto'] ?? '')));

return in_array($nombrePuesto, ['encargado', 'asistente administrativo'], true);
}

private static function tablaSemanaHtml(int $idEstacion, int $year, int $mes, int $semana, $personal, array $mapa, array $catalogoMap): string
{
$fechas = self::fechasNominaSemana($year, $semana);
$dias = [];
$fechaActual = date('Y-m-d');
$d = new \DateTime($fechas['inicio']);
for ($i = 0; $i < 7; $i++) {
$dias[] = $d->format('Y-m-d');
$d->modify('+1 day');
}

$terminada = $fechas['fin'] < $fechaActual;
$permisos = self::getPermisos();
$descargar = $terminada && $permisos['puedeDescargar'];
$mostrarEstaciones = $descargar && !self::ocultaReporteEstaciones($permisos);

// Contenedor principal dentro de una Card de Bootstrap
$html = '<div class="card reporte-semana mb-4" data-semana="' . (int)$semana . '">';

// --- CARD HEADER ---
$html .= '<div class="card-header bg-white d-flex align-items-center justify-content-between">';
$html .= '<div class="card-title fw-semibold mb-0">Semana No. ' . (int)$semana 
. ' (' . formatearFecha($fechas['inicio']) . ' al ' . formatearFecha($fechas['fin']) . ')</div>';

if ($descargar) {
$html .= '<div class="dropdown ms-auto">';
$html .= '<button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">';
$html .= '<i class="ti ti-dots-vertical fs-4"></i>';
$html .= '</button>';

$html .= '<ul class="dropdown-menu dropdown-menu-end">';

// Opción 1: Reporte PDF (Semanal)
$html .= '<li><a class="dropdown-item" href="/departamento-operativo/recursos-humanos/biometricos/reporte-pdf?id_estacion=' . (int)$idEstacion
. '&year=' . (int)$year . '&mes=' . (int)$mes . '&semana=' . (int)$semana 
. '" target="_blank"><i class="ti ti-file-text me-2"></i>Reporte (Semana No. ' . (int)$semana . ')</a></li>';

// Opción 2: Reporte Excel (Mensual)
$html .= '<li><a class="dropdown-item" href="/departamento-operativo/recursos-humanos/biometricos/reporte-excel?id_estacion=' . (int)$idEstacion
. '&year=' . (int)$year . '&mes=' . (int)$mes 
. '"><i class="ti ti-file-spreadsheet me-2"></i>Reporte (' . nombremes(str_pad((string)$mes, 2, '0', STR_PAD_LEFT)) . ' ' . (int)$year . ')</a></li>';

// Opción 3: Reporte Estaciones PDF (Condicional)
if ($mostrarEstaciones) {
$html .= '<li><a class="dropdown-item" href="/departamento-operativo/recursos-humanos/biometricos/reporte-estaciones-pdf?year=' . (int)$year
. '&mes=' . (int)$mes . '&semana=' . (int)$semana 
. '" target="_blank"><i class="ti ti-building-community me-2"></i>Reporte Estaciones (Semana No. ' . (int)$semana . ')</a></li>';
}

$html .= '</ul></div>';
}

$html .= '</div>'; // Cierre del card-header

// --- CARD BODY ---
$html .= '<div class="card-body p-0">';

if (!$terminada) {
$html .= '<div class="d-inline-block px-3 py-2 bg-secondary bg-opacity-10 border rounded text-secondary fw-semibold">Reporte No disponible</div>';
$html .= '</div></div>'; // Cierre de card-body y card
return $html;
}

$html .= '<div class="table-responsive overflow-x-auto">';
$html .= '<table class="table table-striped table-bordered mb-0 text-nowrap align-middle reporte-asistencia">';
$html .= '<thead><tr><th class="text-start">Nombre</th>';
foreach ($dias as $dia) {
$html .= '<th class="text-nowrap">' . formatearFecha($dia) . '</th>';
}
$html .= '<th>Retardos</th><th>Faltas</th></tr></thead><tbody>';

foreach ($personal as $p) {
$retardos = 0;
$faltas = 0;
$html .= '<tr><td class="text-start text-nowrap fw-semibold">' . htmlspecialchars((string)$p->nombre_completo, ENT_QUOTES) . '</td>';
foreach ($dias as $dia) {
$a = $mapa[$p->id . '|' . $dia] ?? null;
$res = self::detalleReporte($a, $catalogoMap);
if ($res['detalle'] === 'Retardo') {
$retardos++;
}
if ($res['detalle'] === 'Falta' || $res['detalle'] === 'Falta fin de semana') {
$faltas++;
}
$html .= '<td class="text-nowrap text-center ' . ($res['cls'] ?: 'text-muted') . '">' . htmlspecialchars($res['detalle'], ENT_QUOTES) . '</td>';
}
$html .= '<td class="fw-semibold text-center">' . $retardos . '</td>';
$html .= '<td class="fw-semibold text-center">' . $faltas . '</td>';
$html .= '</tr>';
}

$html .= '</tbody></table></div>';
$html .= '</div></div>'; // Cierre de card-body y card

return $html;
}

public static function getReporteHtml(int $year, int $mes, array $permisos = []): array
{
$catalogoMap = self::getCatalogoIncidenciaMap();
$html = '';

foreach (self::getEstacionesReporte() as $idEstacion) {
[$idsPersonal, $mapa, $personal] = self::getAsistenciasMes($idEstacion, $year, $mes);
if (empty($idsPersonal)) {
continue;
}

$nombreEstacion = self::resolveNombreEstacion($idEstacion) ?: ('Estación #' . $idEstacion);

// Título de la estación dentro de un div alert
$html .= '<div class="alert bg-primary d-flex align-items-center mb-4" role="alert">';
$html .= '<h5 class="mb-0 alert-heading text-white"><i class="ti ti-gas-station me-2 fs-5"></i>' . htmlspecialchars($nombreEstacion, ENT_QUOTES) . '</h5>';
$html .= '</div>';

// Tablas de semanas de la estación
foreach (self::getSemanasDelMes($mes, $year) as $semana) {
$html .= self::tablaSemanaHtml($idEstacion, $year, $mes, $semana, $personal, $mapa, $catalogoMap);
}
}

return ['html' => $html];
}

/**
* Tabla de nómina semanal usada en los PDF (encabezado + detalle por día).
*/
private static function tablaNominaHtml($personal, array $mapa, array $catalogoMap, array $dias): string
{
$html = '<table><thead><tr><th class="txt-left">Nombre</th>';
foreach ($dias as $dia) {
$html .= '<th>' . formatearFecha($dia) . '</th>';
}
$html .= '<th>Retardos</th><th>Faltas</th></tr></thead><tbody>';

foreach ($personal as $p) {
$retardos = 0;
$faltas = 0;
$html .= '<tr><td class="txt-left fw-bold">' . htmlspecialchars((string)$p->nombre_completo, ENT_QUOTES) . '</td>';
foreach ($dias as $dia) {
$a = $mapa[$p->id . '|' . $dia] ?? null;
$res = self::detalleReporte($a, $catalogoMap);
if ($res['detalle'] === 'Retardo') {
$retardos++;
}
if ($res['detalle'] === 'Falta' || $res['detalle'] === 'Falta fin de semana') {
$faltas++;
}
$html .= '<td class="' . $res['cls'] . '">' . htmlspecialchars($res['detalle'], ENT_QUOTES) . '</td>';
}
$html .= '<td class="fw-bold">' . $retardos . '</td>';
$html .= '<td class="fw-bold">' . $faltas . '</td>';
$html .= '</tr>';
}

$html .= '</tbody></table>';
return $html;
}

private static function diasDeSemana(int $year, int $semana): array
{
$fechas = self::fechasNominaSemana($year, $semana);
$dias = [];
$d = new \DateTime($fechas['inicio']);
for ($i = 0; $i < 7; $i++) {
$dias[] = $d->format('Y-m-d');
$d->modify('+1 day');
}

return $dias;
}

public static function getHtmlPdfReporte(int $idEstacion, int $year, int $mes, int $semana): string
{
[$idsPersonal, $mapa, $personal] = self::getAsistenciasMes($idEstacion, $year, $mes);
$catalogoMap = self::getCatalogoIncidenciaMap();
$nombreEstacion = self::resolveNombreEstacion($idEstacion) ?: ('Estación #' . $idEstacion);
$fechas = self::fechasNominaSemana($year, $semana);

$html = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">';
$html .= '<style>'
. 'body{font-family:Arial,Helvetica,sans-serif;font-size:10px;}'
. 'h2,h4{margin:0 0 6px;}'
. 'p{margin:2px 0 8px;}'
. 'table{width:100%;border-collapse:collapse;}'
. 'table,th,td{border:1px solid #999;}'
. 'th,td{padding:4px 6px;text-align:center;}'
. '.txt-left{text-align:left;}'
. '.fw-bold{font-weight:bold;}'
. '.text-warning{color:#ffc107;}'
. '.text-danger{color:#dc3545;}'
. '.text-success{color:#28a745;}'
. '.text-secondary{color:#6c757d;}'
. '.text-black{color:#000;}'
. '</style></head><body>';

$html .= '<h2 class="txt-left">Reporte de incidencias (' . htmlspecialchars($nombreEstacion, ENT_QUOTES) . ')</h2>';
$html .= '<h4 class="txt-left">Semana No. ' . (int)$semana . ' (' . formatearFecha($fechas['inicio']) . ' al ' . formatearFecha($fechas['fin']) . ')</h4>';
$html .= '<div>' . self::tablaNominaHtml($personal, $mapa, $catalogoMap, self::diasDeSemana($year, $semana)) . '</div>';

$html .= '</body></html>';
return $html;
}

public static function getHtmlPdfReporteEstaciones(int $year, int $mes, int $semana): string
{
$estaciones = [2, 1, 3, 4, 5, 6, 7, 9, 14];
$catalogoMap = self::getCatalogoIncidenciaMap();
$mapaNombres = self::getMapaNombresEstaciones($estaciones);
$dias = self::diasDeSemana($year, $semana);
$fechas = self::fechasNominaSemana($year, $semana);

$html = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">';
$html .= '<style>'
. 'body{font-family:Arial,Helvetica,sans-serif;font-size:9px;}'
. 'h3,h4{margin:0 0 6px;}'
. 'p{margin:2px 0 8px;}'
. 'table{width:100%;border-collapse:collapse;}'
. 'table,th,td{border:1px solid #999;}'
. 'th,td{padding:3px 5px;text-align:center;}'
. '.txt-left{text-align:left;}'
. '.fw-bold{font-weight:bold;}'
. '.text-warning{color:#ffc107;}'
. '.text-danger{color:#dc3545;}'
. '.text-success{color:#28a745;}'
. '.text-secondary{color:#6c757d;}'
. '.text-black{color:#000;}'
. '</style></head><body>';

$html .= '<h3>Reporte de incidencias - Semana ' . (int)$semana . ' (General)</h3>';
$html .= '<p>' . formatearFecha($fechas['inicio']) . ' al ' . formatearFecha($fechas['fin']) . '</p>';

$primera = true;
foreach ($estaciones as $idEstacion) {
[$idsPersonal, $mapa, $personal] = self::getAsistenciasMes($idEstacion, $year, $mes);
if (empty($idsPersonal)) {
continue;
}

$html .= ($primera ? '' : '<div style="page-break-before: always;"></div>');
$primera = false;

$nombreEstacion = $mapaNombres[$idEstacion] ?? ('Estación #' . $idEstacion);
$html .= '<h4 class="txt-left">' . htmlspecialchars($nombreEstacion, ENT_QUOTES) . '</h4>';
$html .= self::tablaNominaHtml($personal, $mapa, $catalogoMap, $dias);
}

$html .= '</body></html>';
return $html;
}

public static function getReporteExcelData(int $idEstacion, int $year, int $mes): array
{
$idsPersonal = RhPersonal::where('id_estacion', $idEstacion)
->where('estado', 1)
->pluck('id')
->all();

if (empty($idsPersonal)) {
return [];
}

return RhPersonalAsistencia::join('op_rh_personal', 'op_rh_personal_asistencia.id_personal', '=', 'op_rh_personal.id')
->join('op_rh_puestos', 'op_rh_personal.puesto', '=', 'op_rh_puestos.id')
->whereIn('op_rh_personal_asistencia.id_personal', $idsPersonal)
->whereBetween('op_rh_personal_asistencia.fecha', [$year . '-' . str_pad((string)$mes, 2, '0', STR_PAD_LEFT) . '-01', $year . '-' . str_pad((string)$mes, 2, '0', STR_PAD_LEFT) . '-31'])
->select([
'op_rh_personal_asistencia.*',
'op_rh_personal.nombre_completo',
'op_rh_puestos.puesto',
])
->orderByDesc('op_rh_personal_asistencia.fecha')
->get()
->map(function ($a) {
$detalle = ControlDocumentosPersonalService::getDetalleAsistencia(
(string)$a->fecha,
(string)$a->hora_entrada,
(string)$a->hora_salida,
(string)$a->hora_entrada_sensor,
(string)$a->hora_salida_sensor,
(int)$a->retardo_minutos
);

return [
'fecha'               => (string)$a->fecha,
'nombre_completo'     => $a->nombre_completo,
'puesto'              => $a->puesto ?? '',
'hora_entrada'        => (string)$a->hora_entrada,
'hora_salida'         => (string)$a->hora_salida,
'hora_entrada_sensor' => (string)$a->hora_entrada_sensor,
'hora_salida_sensor'  => (string)$a->hora_salida_sensor,
'retardo_minutos'     => (int)$a->retardo_minutos,
'detalle'             => $detalle,
'sd'                  => (float)$a->sd,
];
})
->all();
}

public static function datatablePuestos(): array
{
$permisoEditar   = ModuloDptoOperativoService::permisosSesion('recursos-humanos');
$editar = !empty($permisoEditar['editar']);
$eliminar = !empty($permisoEditar['eliminar']);

$rows = RhPuestos::where('status', 1)->orderBy('id', 'asc')->get();

$data = [];
foreach ($rows as $row) {
$data[] = [
'id'     => (int)$row->id,
'puesto' => $row->puesto ?? '',
'permisos' => [
'disabledEdit'   => !$editar,
'disabledDelete' => !$eliminar,
],
];
}

return $data;
}

public static function createPuesto(string $nombre): array
{
try {
RhPuestos::create([
'puesto' => $nombre,
'status' => 1,
]);
return ['success' => true, 'message' => 'Puesto creado correctamente.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al crear el puesto: ' . $e->getMessage()];
}
}

public static function updatePuesto(int $id, string $nombre): array
{
$puesto = RhPuestos::find($id);
if (!$puesto) {
return ['success' => false, 'message' => 'Puesto no encontrado.'];
}

try {
$puesto->puesto = $nombre;
$puesto->save();
return ['success' => true, 'message' => 'Puesto actualizado correctamente.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al actualizar el puesto: ' . $e->getMessage()];
}
}

public static function deletePuesto(int $id): array
{
$puesto = RhPuestos::find($id);
if (!$puesto) {
return ['success' => false, 'message' => 'Puesto no encontrado.'];
}

try {
$puesto->status = 0;
$puesto->save();
return ['success' => true, 'message' => 'Puesto eliminado correctamente.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al eliminar el puesto: ' . $e->getMessage()];
}
}

// ─── Encriptación (compatible con versión anterior) ───────────────────────────

private const ENCRYPT_KEY = 'adef0237c8734d590456dab190958a8f7e2ea706689b1ca93dc01ff77791c9096a8fe31fb3078bb3231e329465a5a01392e465049c62e85ce3a7d1b56aaf1b4f';

public static function encrypt(string $string): string
{
$key = self::ENCRYPT_KEY;
$result = '';
for ($i = 0; $i < strlen($string); $i++) {
$char = substr($string, $i, 1);
$keychar = substr($key, ($i % strlen($key)) - 1, 1);
$char = chr(ord($char) + ord($keychar));
$result .= $char;
}
return base64_encode($result);
}

public static function decrypt(string $string): string
{
$key = self::ENCRYPT_KEY;
$result = '';
$string = base64_decode($string);
for ($i = 0; $i < strlen($string); $i++) {
$char = substr($string, $i, 1);
$keychar = substr($key, ($i % strlen($key)) - 1, 1);
$char = chr(ord($char) - ord($keychar));
$result .= $char;
}
return $result;
}

// ─── Perfil de aplicación ────────────────────────────────────────────────────

public static function datatablePerfil(?int $idEstacion = null): array
{
if ($idEstacion === null || $idEstacion <= 0) {
return [];
}

$query = RhLocalidadesPerfil::where('status', 1)
->where('id_estacion', $idEstacion);

$registros = $query->orderBy('id')->get();
$permisos = ModuloDptoOperativoService::permisosSesion('recursos-humanos');
$result = [];

foreach ($registros as $r) {
$result[] = [
'id' => $r->id,
'usuario' => self::decrypt($r->usuario),
'password' => self::decrypt($r->password),
'id_estacion' => (int)$r->id_estacion,
'nombre_estacion' => '',
'permisos' => [
'disabledEdit' => empty($permisos['editar']),
'disabledDelete' => empty($permisos['eliminar']),
],
];
}

return $result;
}

public static function createPerfil(int $idEstacion, string $usuario, string $password): array
{
if ($idEstacion <= 0) {
return ['success' => false, 'message' => 'Estación requerida.'];
}
if ($usuario === '' || $password === '') {
return ['success' => false, 'message' => 'Usuario y contraseña son obligatorios.'];
}

$encryptedUsuario = self::encrypt($usuario);
$encryptedPassword = self::encrypt($password);

$duplicado = RhLocalidadesPerfil::where('usuario', $encryptedUsuario)
->where('password', $encryptedPassword)
->where('status', 1)
->count();

if ($duplicado > 0) {
return ['success' => false, 'message' => 'Ya existe un registro con ese usuario y contraseña.'];
}

try {
RhLocalidadesPerfil::create([
'id_estacion' => $idEstacion,
'usuario' => $encryptedUsuario,
'password' => $encryptedPassword,
'token' => '',
'status' => 1,
]);
return ['success' => true, 'message' => 'Perfil agregado correctamente.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al agregar el perfil: ' . $e->getMessage()];
}
}

public static function updatePerfil(int $id, string $usuario, string $password): array
{
$perfil = RhLocalidadesPerfil::find($id);
if (!$perfil) {
return ['success' => false, 'message' => 'Perfil no encontrado.'];
}

if ($usuario === '') {
return ['success' => false, 'message' => 'El usuario es obligatorio.'];
}

try {
$perfil->usuario = self::encrypt($usuario);
if ($password !== '') {
$perfil->password = self::encrypt($password);
}
$perfil->save();
return ['success' => true, 'message' => 'Perfil actualizado correctamente.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al actualizar el perfil: ' . $e->getMessage()];
}
}

public static function deletePerfil(int $id): array
{
$perfil = RhLocalidadesPerfil::find($id);
if (!$perfil) {
return ['success' => false, 'message' => 'Perfil no encontrado.'];
}

try {
$perfil->status = 0;
$perfil->save();
return ['success' => true, 'message' => 'Perfil eliminado correctamente.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al eliminar el perfil: ' . $e->getMessage()];
}
}

// ─── Retardos, Horarios e Incidencias ───────────────────────────────────────

public static function getRetardoIncidencia(int $idEstacion): array
{
$registro = RhLocalidadesRetardoIncidencia::where('id_estacion', $idEstacion)->first();

if (!$registro) {
$registro = RhLocalidadesRetardoIncidencia::create([
'id_estacion' => $idEstacion,
'retardo' => 0,
'incidencia' => 0,
]);
}

return [
'retardo' => (int)$registro->retardo,
'incidencia' => (int)$registro->incidencia,
];
}

public static function updateRetardoIncidencia(int $idEstacion, int $retardo, int $incidencia): array
{
$registro = RhLocalidadesRetardoIncidencia::where('id_estacion', $idEstacion)->first();

if (!$registro) {
$registro = RhLocalidadesRetardoIncidencia::create([
'id_estacion' => $idEstacion,
'retardo' => $retardo,
'incidencia' => $incidencia,
]);
} else {
$registro->retardo = $retardo;
$registro->incidencia = $incidencia;
$registro->save();
}

return ['success' => true, 'message' => 'Configuración actualizada correctamente.'];
}

public static function datatableHorarios(int $idEstacion): array
{
$registros = RhLocalidadesHorario::where('id_estacion', $idEstacion)
->orderBy('id')
->get();

$permisos = ModuloDptoOperativoService::permisosSesion('recursos-humanos');
$result = [];

foreach ($registros as $r) {
$result[] = [
'id' => $r->id,
'titulo' => $r->titulo,
'hora_entrada' => $r->hora_entrada,
'hora_salida' => $r->hora_salida,
'permisos' => [
'disabledEdit' => empty($permisos['editar']),
'disabledDelete' => empty($permisos['eliminar']),
],
];
}

return $result;
}

public static function createHorario(int $idEstacion, string $titulo, string $horaEntrada, string $horaSalida): array
{
if ($titulo === '') {
return ['success' => false, 'message' => 'El título es obligatorio.'];
}
if ($horaEntrada === '') {
return ['success' => false, 'message' => 'La hora de entrada es obligatoria.'];
}
if ($horaSalida === '') {
return ['success' => false, 'message' => 'La hora de salida es obligatoria.'];
}

try {
RhLocalidadesHorario::create([
'id_estacion' => $idEstacion,
'titulo' => $titulo,
'hora_entrada' => $horaEntrada,
'hora_salida' => $horaSalida,
]);
return ['success' => true, 'message' => 'Horario agregado correctamente.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al agregar el horario: ' . $e->getMessage()];
}
}

public static function updateHorario(int $id, string $titulo, string $horaEntrada, string $horaSalida): array
{
$horario = RhLocalidadesHorario::find($id);
if (!$horario) {
return ['success' => false, 'message' => 'Horario no encontrado.'];
}

if ($titulo === '') {
return ['success' => false, 'message' => 'El título es obligatorio.'];
}
if ($horaEntrada === '') {
return ['success' => false, 'message' => 'La hora de entrada es obligatoria.'];
}
if ($horaSalida === '') {
return ['success' => false, 'message' => 'La hora de salida es obligatoria.'];
}

try {
$horario->titulo = $titulo;
$horario->hora_entrada = $horaEntrada;
$horario->hora_salida = $horaSalida;
$horario->save();
return ['success' => true, 'message' => 'Horario actualizado correctamente.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al actualizar el horario: ' . $e->getMessage()];
}
}

public static function deleteHorario(int $id): array
{
$horario = RhLocalidadesHorario::find($id);
if (!$horario) {
return ['success' => false, 'message' => 'Horario no encontrado.'];
}

try {
$horario->delete();
return ['success' => true, 'message' => 'Horario eliminado correctamente.'];
} catch (\Exception $e) {
return ['success' => false, 'message' => 'Error al eliminar el horario: ' . $e->getMessage()];
}
}
}
