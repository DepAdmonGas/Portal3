<?php
namespace App\Services;

use App\Core\Session;
use App\Core\Auth;
use App\Models\Estacion;
use App\Models\Operativo\RhLocalidad;

class ModuleStationService
{
private static array $configs = [

'corte-diario' => [
'label' => 'Corte Diario',
'use_selector' => true,
'type' => 'stations_only',
'allow_all' => false,
'placeholder' => 'Selecciona una estación...',
'load_empty' => true,
'show_badge' => true,
'context_group' => 'corte-diario',
'station_ids' => [1, 2, 3, 4, 5, 6, 7, 14],
'station_filter' => [
'puestos' => [
'Contabilidad' => [1, 2, 3, 4, 5, 14],
'Comercializadora' => [1, 2, 3, 4, 5, 6, 7, 14],
],
'usuarios' => [
419 => [14],
],
],
],

'solicitud-cheques' => [
'label' => 'Solicitud de Cheques',
'use_selector' => true,
'type' => 'stations_and_departments',
'allow_all' => true,
'placeholder' => 'Todas las estaciones y departamentos',
'load_empty' => true,
'show_badge' => true,
'context_group' => 'solicitud-cheques',
'station_ids' => [1, 2, 3, 4, 5, 6, 7, 14],
],

'solicitud-gafetes' => [
'label' => 'Solicitud de Gafetes',
'use_selector' => true,
'type' => 'stations_only',
'allow_all' => true,
'placeholder' => 'Todas las estaciones',
'load_empty' => true,
'show_badge' => true,
'context_group' => 'solicitud-gafetes',
'station_ids' => [1, 2, 3, 4, 5, 6, 7, 14],
],

'solicitud-tarjetas' => [
'label' => 'Solicitud de Tarjetas',
'use_selector' => true,
'type' => 'stations_only',
'allow_all' => true,
'placeholder' => 'Todas las estaciones',
'load_empty' => true,
'show_badge' => true,
'context_group' => 'solicitud-tarjetas',
'station_ids' => [1, 2, 3, 4, 5, 6, 7, 14],
],

'bitacora-aditivo' => [
'label' => 'Bitácora de Aditivo',
'use_selector' => true,
'type' => 'stations_only',
'allow_all' => false,
'placeholder' => 'Selecciona una estación...',
'load_empty' => true,
'show_badge' => true,
'context_group' => 'bitacora-aditivo',
'station_ids' => [1, 2, 3, 4, 5, 6, 7, 14],
],

'comparativo-xml' => [
'label'          => 'Comparativo XML',
'use_selector'   => true,
'type'           => 'stations_only',
'allow_all'      => false,
'placeholder'    => 'Selecciona una estación...',
'load_empty'     => true,
'show_badge'     => true,
'context_group'  => 'comparativo-xml',
'station_ids'    => [1, 2, 3, 4, 5, 6, 7, 14],
'station_filter' => [
'puestos' => [
'Contabilidad'      => [1, 2, 3, 4, 5, 14],
'Comercializadora'  => [1, 2, 3, 4, 5, 6, 7, 14],
],
],
],

'aclaracion-voucher' => [
'label'          => 'Aclaración Voucher',
'use_selector'   => true,
'type'           => 'stations_only',
'allow_all'      => true,
'placeholder'    => 'Todas las estaciones',
'load_empty'     => true,
'show_badge'     => true,
'context_group'  => 'aclaracion-voucher',
'station_ids'    => [1, 2, 3, 4, 5, 6, 7, 14],
'station_filter' => [
'puestos' => [
'Comercializadora'  => [6, 7],
'Contabilidad'      => [1, 2, 3, 4, 5, 14],
],
],
],

'ingresos-facturacion' => [
'label'          => 'Ingresos vs Facturación',
'use_selector'   => true,
'type'           => 'stations_only',
'allow_all'      => false,
'placeholder'    => 'Selecciona una estación...',
'load_empty'     => true,
'show_badge'     => true,
'context_group'  => 'ingresos-facturacion',
'station_ids'    => [1, 2, 3, 4, 5, 6, 7, 14],
],

'contratos' => [
'label'          => 'Contratos',
'use_selector'   => true,
'type'           => 'stations_only',
'allow_all'      => false,
'placeholder'    => 'Selecciona una estación...',
'load_empty'     => true,
'show_badge'     => true,
'context_group'  => 'contratos',
'station_ids'    => [1, 2, 3, 4, 5, 6, 7, 14],
'station_filter' => [
'puestos' => [
'Contabilidad'      => [1, 2, 3, 4, 5, 14],
'Comercializadora'  => [1, 2, 3, 4, 5, 6, 7, 14],
],
],
],

'seguros' => [
'label'          => 'Seguros',
'use_selector'   => true,
'type'           => 'stations_and_departments',
'allow_all'      => false,
'placeholder'    => 'Selecciona una opción...',
'load_empty'     => true,
'show_badge'     => true,
'context_group'  => 'seguros',
],

'factura-monedero' => [
'label'          => 'Factura Monedero',
'use_selector'   => true,
'type'           => 'stations_only',
'allow_all'      => true,
'placeholder'    => 'Todas las estaciones',
'load_empty'     => true,
'show_badge'     => true,
'context_group'  => 'factura-monedero',
'station_ids'    => [1, 2, 3, 4, 5, 6, 7, 14],
'station_filter' => [
'usuarios' => [
419 => [14],
],
'puestos' => [
'Contabilidad'      => [1, 2, 3, 4, 5, 14],
'Comercializadora'  => [6, 7],
],
],
],

'despacho-ventas' => [
'label'          => 'Despacho vs Ventas',
'use_selector'   => true,
'type'           => 'stations_only',
'allow_all'      => false,
'placeholder'    => 'Selecciona una estación...',
'load_empty'     => true,
'show_badge'     => true,
'context_group'  => 'despacho-ventas',
'station_ids'    => [1, 2, 3, 4, 5, 6, 7, 14],
'station_filter' => [
'puestos' => [
'Contabilidad'      => [1, 2, 3, 4, 5, 14],
'Comercializadora'  => [1, 2, 3, 4, 5, 6, 7, 14],
],
],
],
];

public static function getConfig(string $moduleKey): ?array
{
return self::$configs[$moduleKey] ?? null;
}

public static function hasSelector(string $moduleKey): bool
{
$cfg = self::getConfig($moduleKey);
return $cfg && ($cfg['use_selector'] ?? false);
}

public static function getContext(string $moduleKey): array
{
$cfg = self::getConfig($moduleKey);
if (!$cfg) return ['id_estacion' => null, 'id_depto' => null, 'nombre' => ''];

$group = $cfg['context_group'] ?? $moduleKey;
$ctx = Session::get('module_context')[$group] ?? [];

$idEstacion = $ctx['id_estacion'] ?? null;
$idDepto = $ctx['id_depto'] ?? null;

// For non-multiestacion users with no context set, use their session station
$sessionUsuario = Session::get('usuario');
$multiestacion = !empty($sessionUsuario['multiestacion']);
if (!$multiestacion && $idEstacion === null && $idDepto === null) {
$idEstacion = $sessionUsuario['id_estacion'] ?? null;
}

return [
'id_estacion' => $idEstacion,
'id_depto' => $idDepto,
'nombre' => self::resolveName($moduleKey, $idEstacion, $idDepto),
];
}

public static function setContext(string $moduleKey, $idEstacion, $idDepto = null): void
{
$cfg = self::getConfig($moduleKey);
if (!$cfg) return;

$group = $cfg['context_group'] ?? $moduleKey;
$ctx = Session::get('module_context') ?? [];
$ctx[$group] = [
'id_estacion' => $idEstacion !== null ? (int)$idEstacion : null,
'id_depto' => $idDepto !== null ? (int)$idDepto : null,
];
Session::set('module_context', $ctx);
}

public static function hasSelection(string $moduleKey): bool
{
$ctx = self::getContext($moduleKey);
return $ctx['id_estacion'] !== null || $ctx['id_depto'] !== null;
}

public static function getAvailableStations(string $moduleKey): array
{
$cfg = self::getConfig($moduleKey);
if (!$cfg) return [];

if ($moduleKey === 'seguros') {
$localidades = RhLocalidad::where(function ($q) {
$q->whereBetween('numlista', [0, 9])
->orWhere('numlista', 10);
})
->where('id', '!=', 8)
->orderBy('numlista')
->get(['id', 'localidad as nombre']);

$result = [];
foreach ($localidades as $loc) {
$result[] = ['id' => $loc->id, 'nombre' => $loc->nombre];
}
return $result;
}

if (!empty($cfg['station_ids'])) {
$allStations = Estacion::whereIn('id', $cfg['station_ids'])
->orderBy('id')
->get(['id', 'nombre'])
->toArray();
} else {
$allStations = Estacion::where('numlista', '<=', 8)
->where('id', '!=', 8)
->orderBy('numlista')
->get(['id', 'nombre'])
->toArray();
}

// Puesto 6 + Estación 8: OVERRIDE absoluto - ignora station_filter
if (self::isPuesto6Estacion8()) {
$restrictedIds = [1, 2, 3, 4, 5, 6, 7, 14];
return array_values(array_filter($allStations, fn($s) => in_array($s['id'], $restrictedIds)));
}

$filter = $cfg['station_filter'] ?? null;
if ($filter === null) return $allStations;

$sessionUsuario = Session::get('usuario');
$userId = $sessionUsuario['id'] ?? null;
$usuario = Auth::user();
$puesto = $usuario && $usuario->puesto ? $usuario->puesto->tipo_puesto : '';

if (isset($filter['usuarios'][$userId])) {
$allowed = $filter['usuarios'][$userId];
$allStations = array_values(array_filter($allStations, fn($s) => in_array($s['id'], $allowed)));
} elseif (isset($filter['puestos'][$puesto])) {
$allowed = $filter['puestos'][$puesto];
$allStations = array_values(array_filter($allStations, fn($s) => in_array($s['id'], $allowed)));
}

return $allStations;
}

public static function getAvailableDepartments(string $moduleKey): array
{
$cfg = self::getConfig($moduleKey);
if (!$cfg || ($cfg['type'] ?? 'stations_only') !== 'stations_and_departments') return [];

// Puesto 6 + Est 8: hide departments for all modules
if (self::isPuesto6Estacion8()) {
return [];
}

if ($moduleKey === 'seguros') {
$depts = RhLocalidad::whereBetween('numlista', [22, 23])
->orderBy('numlista')
->get(['id', 'localidad as nombre']);
return $depts->toArray();
}

$deptIds = [4 => 'Comercializadora', 5 => 'Gestoría', 18 => 'Quitarga', 19 => 'Operación servicio y mantenimiento de personal', 23 => 'BANCAMIFEL, SOCIEDAD ANÓNIMA, FIDEICOMISO 2176/2016'];

$depts = [];
foreach ($deptIds as $id => $nombre) {
$depts[] = ['id' => $id, 'nombre' => $nombre];
}
return $depts;
}

/**
* Render the station/department selector for a module.
*
* @param string $moduleKey Module identifier
* @param array $pendientes Optional pendientes data: ['total' => N, 'estacion_X' => N, 'depto_X' => N]
* @return string HTML output
*/
public static function render(string $moduleKey, array $pendientes = [], bool $showSelector = true): string
{
$cfg = self::getConfig($moduleKey);
if (!$cfg) return '';

$ctx = self::getContext($moduleKey);
$idEstacion = $ctx['id_estacion'];
$idDepto = $ctx['id_depto'];
$currentName = $ctx['nombre'];

$sessionUsuario = Session::get('usuario');
$multiestacion = !empty($sessionUsuario['multiestacion']);

$stations = self::getAvailableStations($moduleKey);
$depts = self::getAvailableDepartments($moduleKey);

$hasSelection = $idEstacion !== null || $idDepto !== null;
$showSelector = $showSelector && $cfg['use_selector'] && $multiestacion && (!empty($stations) || !empty($depts));

$html = '<div class="d-flex align-items-center justify-content-between flex-wrap w-100" id="module-station-wrapper-' . $moduleKey . '">';

if ($cfg['show_badge'] ?? false) {
$badgeHidden = $hasSelection ? '' : ' style="display:none"';
$badgeText = $hasSelection ? ($currentName ?: "\u{2014}") : '';
$html .= '<span id="module-station-badge-' . $moduleKey . '" class="badge rounded-pill text-bg-info"' . $badgeHidden . '>' . htmlspecialchars($badgeText, ENT_QUOTES, 'UTF-8') . '</span>';
}

if ($showSelector) {
$placeholder = $cfg['placeholder'] ?? 'Selecciona una estación...';
if (empty($depts) && ($cfg['type'] ?? 'stations_only') === 'stations_and_departments') {
$placeholder = $cfg['allow_all'] ? 'Todas las estaciones' : 'Selecciona una estación...';
}
$loadEmpty = $cfg['load_empty'] ?? true;
$allowAll = $cfg['allow_all'] ?? false;

$html .= '<div class="ms-auto">';
$html .= '<select id="module-station-selector-' . $moduleKey . '" class="form-select form-select-sm" style="min-width:260px;" data-module-key="' . htmlspecialchars($moduleKey, ENT_QUOTES, 'UTF-8') . '" data-load-empty="' . ($loadEmpty ? 'true' : 'false') . '">';

if ($allowAll) {
$totalPendientes = $pendientes['total'] ?? 0;
$allLabel = $placeholder . ' (' . $totalPendientes . ')';
$html .= '<option value="" ' . ((!$idEstacion && !$idDepto) ? 'selected' : '') . '>' . htmlspecialchars($allLabel, ENT_QUOTES, 'UTF-8') . '</option>';
} else {
$html .= '<option value="" ' . ((!$idEstacion && !$idDepto) ? 'selected' : '') . '>' . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . '</option>';
}

if (!empty($stations)) {
$html .= '<optgroup label="Estaciones">';
foreach ($stations as $s) {
$sel = ($s['id'] == $idEstacion && !$idDepto) ? ' selected' : '';
$pend = isset($pendientes['estacion_' . $s['id']]) ? ' (' . $pendientes['estacion_' . $s['id']] . ')' : '';
$html .= '<option value="estacion_' . $s['id'] . '"' . $sel . '>' . htmlspecialchars($s['nombre'], ENT_QUOTES, 'UTF-8') . $pend . '</option>';
}
$html .= '</optgroup>';
}

if (!empty($depts)) {
$html .= '<optgroup label="Departamentos">';
foreach ($depts as $d) {
$sel = ($d['id'] == $idDepto) ? ' selected' : '';
$pend = isset($pendientes['depto_' . $d['id']]) ? ' (' . $pendientes['depto_' . $d['id']] . ')' : '';
$html .= '<option value="depto_' . $d['id'] . '"' . $sel . '>' . htmlspecialchars($d['nombre'], ENT_QUOTES, 'UTF-8') . $pend . '</option>';
}
$html .= '</optgroup>';
}

$html .= '</select></div>';
}

$html .= '</div>';

return $html;
}

private static function resolveName(string $moduleKey, $idEstacion, $idDepto): string
{
if ($moduleKey === 'seguros') {
$id = $idDepto ?: $idEstacion;
if ($id) {
$loc = RhLocalidad::find($id);
return $loc ? $loc->localidad : "#$id";
}
return '';
}

if ($idEstacion && !$idDepto) {
$est = Estacion::find($idEstacion);
return $est ? $est->nombre : 'Estación #' . $idEstacion;
}
if ($idDepto) {
$deptNames = [4 => 'Comercializadora', 5 => 'Gestoría', 18 => 'Quitarga', 19 => 'Operación servicio y mantenimiento de personal', 23 => 'BANCAMIFEL, SOCIEDAD ANÓNIMA, FIDEICOMISO 2176/2016'];
return $deptNames[$idDepto] ?? 'Depto #' . $idDepto;
}
return '';
}

public static function isPuesto6Estacion8(): bool
{
$user = Auth::user();
return $user && (int)$user->id_puesto === 6 && (int)$user->id_gas === 8;
}

/**
* Clear all module contexts from the session.
* Call this when navigating to home or main module pages
* so that module selectors start at their default state.
*/
public static function resetAllContexts(): void
{
Session::set('module_context', []);
}
}
