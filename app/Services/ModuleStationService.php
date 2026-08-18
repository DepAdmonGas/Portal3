<?php

namespace App\Services;

use App\Core\Session;
use App\Core\Auth;
use App\Models\Estacion;
use App\Models\Operativo\RhLocalidad;
use App\Models\ModuloConfig;

class ModuleStationService
{
    public static bool $isBlocked = false;
    public static function getConfig(string $moduleKey): ?array
    {
        $mc = ModuloConfig::where('modulo_key', $moduleKey)->where('activo', true)->first();
        if (!$mc) return null;

        return [
            'type'        => $mc->tipo,
            'allow_all'   => (bool)$mc->allow_all,
            'placeholder' => $mc->placeholder,
        ];
    }

    public static function hasSelector(string $moduleKey): bool
    {
        return self::getConfig($moduleKey) !== null;
    }

    public static function getContext(string $moduleKey): array
    {
        $cfg = self::getConfig($moduleKey);
        if (!$cfg) return ['id_estacion' => null, 'id_depto' => null, 'nombre' => ''];

        $ctx = Session::get('module_context')[$moduleKey] ?? [];

        $idEstacion = $ctx['id_estacion'] ?? null;
        $idDepto = $ctx['id_depto'] ?? null;

        if (!$idEstacion && !$idDepto) {
            $multiestacion = MultiestacionService::isEnabled();
            if (!$multiestacion) {
                $idGas = self::getIdGas();
                if ($idGas) {
                    $idSpace = MultiestacionService::getIdSpaceForModule($moduleKey);
                    if ($idSpace === MultiestacionService::TABLA_ESTACIONES) {
                        $idEstacion = $idGas;
                    } else {
                        $converted = MultiestacionService::convertIds(
                            [$idGas],
                            MultiestacionService::TABLA_ESTACIONES,
                            MultiestacionService::TABLA_RH_LOCALIDADES
                        );
                        $idEstacion = !empty($converted) ? $converted[0] : null;
                    }
                }
            }
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

        if ($idEstacion !== null) {
            $available = self::getAvailableStations($moduleKey);
            if (!empty($available) && !in_array((int)$idEstacion, array_column($available, 'id'))) {
                return;
            }
        }

        if ($idDepto !== null) {
            $available = self::getAvailableDepartments($moduleKey);
            if (!empty($available) && !in_array((int)$idDepto, array_column($available, 'id'))) {
                return;
            }
        }

        $ctx = Session::get('module_context') ?? [];
        $ctx[$moduleKey] = [
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
        $mc = ModuloConfig::where('modulo_key', $moduleKey)->where('activo', true)->first();
        if (!$mc) return [];

        $supported = $mc->estaciones_soportadas ?? [];
        if (empty($supported)) return [];

        $useRhLocalidades = ($moduleKey === 'seguros');
        $user = Auth::user();
        $config = MultiestacionService::getConfig($user);

        if ($config !== null) {
            $allowed = $config['estaciones'];
            if ($allowed === null || empty($allowed)) return [];

            $ids = array_values(array_intersect($supported, $allowed));
            if (empty($ids)) return [];

            if ($useRhLocalidades) {
                $converted = MultiestacionService::convertIds(
                    $ids,
                    MultiestacionService::TABLA_ESTACIONES,
                    MultiestacionService::TABLA_RH_LOCALIDADES
                );
                if (empty($converted)) return [];
                return RhLocalidad::whereIn('id', $converted)
                    ->orderBy('numlista')
                    ->get(['id', 'localidad as nombre'])
                    ->toArray();
            }

            return Estacion::whereIn('id', $ids)
                ->orderBy('id')
                ->get(['id', 'nombre'])
                ->toArray();
        }

        $idGas = self::getIdGas();
        if ($idGas === null) return [];

        if ($useRhLocalidades) {
            $converted = MultiestacionService::convertIds(
                [$idGas],
                MultiestacionService::TABLA_ESTACIONES,
                MultiestacionService::TABLA_RH_LOCALIDADES
            );
            if (empty($converted)) return [];
            return RhLocalidad::whereIn('id', $converted)
                ->orderBy('numlista')
                ->get(['id', 'localidad as nombre'])
                ->toArray();
        }

        if (in_array($idGas, $supported)) {
            return Estacion::where('id', $idGas)
                ->get(['id', 'nombre'])
                ->toArray();
        }

        return [];
    }


    public static function getAvailableDepartments(string $moduleKey): array
    {
        $mc = ModuloConfig::where('modulo_key', $moduleKey)->where('activo', true)->first();
        if (!$mc || $mc->tipo !== 'stations_and_departments') return [];

        $supported = $mc->departamentos_soportados ?? [];
        $tipoDept = $mc->tipo_departamento;

        if (empty($supported)) return [];

        $user = Auth::user();
        $config = MultiestacionService::getConfig($user);

        if ($config !== null) {
            $column = ($tipoDept === 'localidades') ? 'departamentos_localidades' : 'departamentos_puestos';
            $allowed = $config[$column];
            if ($allowed === null || empty($allowed)) return [];

            $ids = array_values(array_intersect($supported, $allowed));
            if (empty($ids)) return [];

            if ($tipoDept === 'localidades') {
                return RhLocalidad::whereIn('id', $ids)
                    ->orderBy('numlista')
                    ->get(['id', 'localidad as nombre'])
                    ->toArray();
            }

            return self::buildPuestos($ids);
        }

        // Legacy: no multiestacion config
        // Only id_gas=2 shows Autolavado for localidades-based modules
        if (self::getIdGas() === 2 && $tipoDept === 'localidades') {
            $autoLavado = RhLocalidad::where('id', 9)->first(['id', 'localidad as nombre']);
            return $autoLavado ? [$autoLavado->toArray()] : [];
        }

        return [];
    }

    public static function isAvailable(string $moduleKey): bool
    {
        $stations = self::getAvailableStations($moduleKey);
        $depts = self::getAvailableDepartments($moduleKey);
        return !empty($stations) || !empty($depts);
    }

    public static function render(string $moduleKey, array $pendientes = [], bool $showSelector = true): string
    {
        $cfg = self::getConfig($moduleKey);
        if (!$cfg) {
            self::$isBlocked = false;
            return '';
        }

        $ctx = self::getContext($moduleKey);
        $idEstacion = $ctx['id_estacion'];
        $idDepto = $ctx['id_depto'];
        $currentName = $ctx['nombre'];

        $multiestacion = MultiestacionService::isEnabled();
        $stations = self::getAvailableStations($moduleKey);
        $depts = self::getAvailableDepartments($moduleKey);

        if (empty($stations) && empty($depts)) {
            self::$isBlocked = true;
            $label = ucwords(str_replace('-', ' ', $moduleKey));
            return '<div class="alert p-4 alert-warning text-center mt-4" role="alert" id="module-station-wrapper-' . $moduleKey . '">

<div>La estación asignada a tu usuario no está disponible para el módulo <strong>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</strong>. Contacta al administrador para configurar tu acceso.</div>
</div>';

}

self::$isBlocked = false;

$hasSelection = $idEstacion !== null || $idDepto !== null;
$hasChoices = (count($stations) > 1) || (!empty($depts) && !empty($stations));
$showSelector = $showSelector && ($multiestacion || $hasChoices);

$html = '<div class="d-flex align-items-center justify-content-between flex-wrap w-100" id="module-station-wrapper-' . $moduleKey . '">';

$badgeHidden = $hasSelection ? '' : ' style="display:none"';
$badgeText = $hasSelection ? ($currentName ?: "\u{2014}") : '';
$html .= '<span id="module-station-badge-' . $moduleKey . '" class="badge rounded-pill text-bg-info"' . $badgeHidden . '>' . htmlspecialchars($badgeText, ENT_QUOTES, 'UTF-8') . '</span>';

if ($showSelector) {
$placeholder = $cfg['placeholder'] ?? 'Selecciona una estación...';
if (empty($depts) && $cfg['type'] === 'stations_and_departments') {
$placeholder = $cfg['allow_all'] ? 'Todas las estaciones' : 'Selecciona una estación...';
}
$allowAll = $cfg['allow_all'] ?? false;

$html .= '<div class="ms-auto">';
$html .= '<select id="module-station-selector-' . $moduleKey . '" class="form-select form-select-sm" style="min-width:260px;" data-module-key="' . htmlspecialchars($moduleKey, ENT_QUOTES, 'UTF-8') . '" data-load-empty="true">';

if ($allowAll) {
$totalPendientes = $pendientes['total'] ?? 0;
$allLabel = $totalPendientes > 0 ? $placeholder . ' (' . $totalPendientes . ')' : $placeholder;
$html .= '<option value="" ' . ((!$idEstacion && !$idDepto) ? 'selected' : '') . '>' . htmlspecialchars($allLabel, ENT_QUOTES, 'UTF-8') . '</option>';
} else {
$html .= '<option value="" ' . ((!$idEstacion && !$idDepto) ? 'selected' : '') . '>' . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . '</option>';
}

if (!empty($stations)) {
//$estLabel = ($moduleKey === 'seguros') ? 'Estaciones' : 'Estaciones';
$estLabel = 'Estaciones';

$html .= '<optgroup label="' . $estLabel . '">';
foreach ($stations as $s) {
$sel = ($s['id'] == $idEstacion && !$idDepto) ? ' selected' : '';
$pend = isset($pendientes['estacion_' . $s['id']]) ? ' (' . $pendientes['estacion_' . $s['id']] . ')' : '';
$html .= '<option value="estacion_' . $s['id'] . '"' . $sel . '>' . htmlspecialchars($s['nombre'], ENT_QUOTES, 'UTF-8') . $pend . '</option>';
}
$html .= '</optgroup>';
}

if (!empty($depts)) {
//$deptLabel = ($moduleKey === 'seguros') ? 'Localidades' : 'Departamentos';
$deptLabel = 'Departamentos';
$html .= '<optgroup label="' . $deptLabel . '">';
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

public static function resetAllContexts(): void
{
Session::set('module_context', []);
}

private static function getIdGas(): ?int
{
$sessionUsuario = Session::get('usuario');
$idGas = $sessionUsuario['id_estacion'] ?? null;
if ($idGas === null) {
$userObj = Auth::user();
$idGas = $userObj ? (int)$userObj->id_gas : null;
}
return $idGas;
}

private static function buildPuestos(array $ids): array
{
$nombres = [
4 => 'Comercializadora',
5 => 'Gestoría',
18 => 'Quitarga',
19 => 'Operación servicio y mantenimiento de personal',
23 => 'BANCAMIFEL, SOCIEDAD ANÓNIMA, FIDEICOMISO 2176/2016',
];
$result = [];
foreach ($ids as $id) {
if (isset($nombres[$id])) {
$result[] = ['id' => $id, 'nombre' => $nombres[$id]];
}
}
return $result;
}

private static function resolveName(string $moduleKey, $idEstacion, $idDepto): string
{
if ($moduleKey === 'seguros') {
$id = $idEstacion ?? $idDepto;
if ($id) {
$loc = RhLocalidad::find($id);
return $loc ? $loc->localidad : "#$id";
}
return '';
}

if (in_array($moduleKey, ['organigrama', 'control-documentos-personal', 'horario-personal', 'biometricos'])) {
if ($idDepto && !$idEstacion) {
$loc = RhLocalidad::find($idDepto);
return $loc ? $loc->localidad : 'Depto #' . $idDepto;
}
if ($idEstacion) {
$est = Estacion::find($idEstacion);
return $est ? $est->nombre : 'Estación #' . $idEstacion;
}
return '';
}

if ($idEstacion && !$idDepto) {
$est = Estacion::find($idEstacion);
return $est ? $est->nombre : 'Estación #' . $idEstacion;
}
if ($idDepto) {
$deptNames = [
4 => 'Comercializadora',
5 => 'Gestoría',
18 => 'Quitarga',
19 => 'Operación servicio y mantenimiento de personal',
23 => 'BANCAMIFEL, SOCIEDAD ANÓNIMA, FIDEICOMISO 2176/2016',
];
return $deptNames[$idDepto] ?? 'Depto #' . $idDepto;
}
return '';
}

}
