<?php
namespace App\Services;

use App\Core\Auth;
use App\Models\Estacion;
use App\Models\MultiestacionPuesto;
use App\Models\MultiestacionUsuario;
use App\Models\Operativo\RhLocalidad;

class MultiestacionService
{
const TABLA_ESTACIONES = 'estaciones';
const TABLA_RH_LOCALIDADES = 'localidades';
const TABLA_PUESTOS = 'puestos';

/**
* Resolve the effective multi-station config for a user.
* Priority: user config → puesto config → null (no restriction).
*
* Returns array with keys:
*   - estaciones:               array|null  (null = no restriction)
*   - departamentos_puestos:    array|null  (null = no restriction)
*   - departamentos_localidades: array|null  (null = no restriction)
*   - activo:                   bool
* Or null if no config exists at any level.
*/
public static function getConfig(?object $usuario = null): ?array
{
$usuario ??= Auth::user();
if (!$usuario) return null;

$puestoConfig = MultiestacionPuesto::where('id_puesto', $usuario->id_puesto)
->where('activo', true)
->first();

$userConfig = MultiestacionUsuario::where('id_usuario', $usuario->id)
->where('activo', true)
->first();

if (!$userConfig && !$puestoConfig) return null;

return self::mergeConfig($userConfig, $puestoConfig);
}

/**
* Whether the user has multi-station enabled.
*/
public static function isEnabled(?object $usuario = null): bool
{
return self::getConfig($usuario) !== null;
}

/**
* Get the allowed station IDs for the user, in tb_estaciones space.
* Returns null = no restriction (use module defaults).
*/
public static function getAllowedStations(?object $usuario = null): ?array
{
$cfg = self::getConfig($usuario);
if (!$cfg) return null;

return $cfg['estaciones'];
}

/**
 * Determine which station ID space a module belongs to.
 *
 * Derived from tb_modulos_config.tipo_departamento: modules configured
 * with 'localidades' consume op_rh_localidades as their station catalog,
 * everything else uses tb_estaciones.
 */
public static function getIdSpaceForModule(string $moduleKey): string
{
$tipo = ModuleStationService::getConfig($moduleKey)['tipo_departamento'] ?? null;
return ($tipo === 'localidades') ? self::TABLA_RH_LOCALIDADES : self::TABLA_ESTACIONES;
}

/**
 * Determine which department ID space a module belongs to.
 *
 * The department catalog is configured in tb_modulos_config.tipo_departamento
 * ('localidades' => op_rh_localidades, anything else => tb_puestos).
 */
public static function getDepartmentSpaceForModule(string $moduleKey): string
{
$tipo = ModuleStationService::getConfig($moduleKey)['tipo_departamento'] ?? null;
return ($tipo === 'localidades') ? self::TABLA_RH_LOCALIDADES : self::TABLA_PUESTOS;
}

/**
* Get the allowed department IDs for the user, in the ID space
* required by the given module.
*
* Returns null = no restriction (module shows all its departments).
*/
public static function getAllowedDepartmentsForModule(string $moduleKey, ?object $usuario = null): ?array
{
$cfg = self::getConfig($usuario);
if (!$cfg) return null;

$space = self::getDepartmentSpaceForModule($moduleKey);
$column = 'departamentos_' . $space;

return $cfg[$column] ?? null;
}

/**
* Convert station IDs from tb_estaciones space to op_rh_localidades space via numlista.
*/
public static function convertIds(array $ids, string $from, string $to): array
{
if ($from === $to || empty($ids)) return $ids;

$result = [];

if ($from === self::TABLA_ESTACIONES && $to === self::TABLA_RH_LOCALIDADES) {
foreach ($ids as $estId) {
$est = Estacion::find((int) $estId);
if (!$est) continue;
$loc = RhLocalidad::where('numlista', $est->numlista)->first();
if ($loc) {
$result[] = (int) $loc->id;
}
}
} elseif ($from === self::TABLA_RH_LOCALIDADES && $to === self::TABLA_ESTACIONES) {
foreach ($ids as $rhId) {
$loc = RhLocalidad::find((int) $rhId);
if (!$loc) continue;
$est = Estacion::where('numlista', $loc->numlista)->first();
if ($est) {
$result[] = (int) $est->id;
}
}
}

return array_values(array_unique($result));
}

/**
* Merge user config over puesto config.
* User values take precedence; null means inherit from puesto.
* [] (empty array) is treated as null = no restriction.
*/
private static function mergeConfig(?object $userConfig, ?object $puestoConfig): array
{
$estaciones = null;
$deptosPuestos = null;
$deptosLocalidades = null;

if ($userConfig) {
$estaciones = self::resolveArray($userConfig->estaciones, $puestoConfig?->estaciones);
$deptosPuestos = self::resolveArray($userConfig->departamentos_puestos, $puestoConfig?->departamentos_puestos);
$deptosLocalidades = self::resolveArray($userConfig->departamentos_localidades, $puestoConfig?->departamentos_localidades);
} elseif ($puestoConfig) {
$estaciones = self::resolveArray($puestoConfig->estaciones, null);
$deptosPuestos = self::resolveArray($puestoConfig->departamentos_puestos, null);
$deptosLocalidades = self::resolveArray($puestoConfig->departamentos_localidades, null);
}

$activo = $userConfig ? (bool) $userConfig->activo : (bool) ($puestoConfig?->activo ?? true);

return [
'estaciones' => $estaciones,
'departamentos_puestos' => $deptosPuestos,
'departamentos_localidades' => $deptosLocalidades,
'activo' => $activo,
];
}

/**
* Resolve a value with optional fallback inheritance.
*
* Semantics:
* - null / 'null' → inherit from fallback (or return null if no fallback)
* - '*' string    → no restriction (return null)
* - [] empty array → no restriction (return null), same as '*' or null
* - [1,2,3]       → explicit list
*/
private static function resolveArray($value, $fallback): ?array
{
if ($value === null || $value === 'null') {
return $fallback !== null ? self::resolveArray($fallback, null) : null;
}
if (is_string($value) && $value === '*') {
return null;
}
if (is_array($value)) {
if (empty($value)) {
return null;
}
return $value;
}
return null;
}
}
