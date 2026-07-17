<?php
// Global station badge shown on all layouts (partial)
// Hidden when a migrated module (ModuleStationService) handles its own context
// Station 8 → shows user's role/department; other stations → station name
// NOT hidden by ocultarSelectorEstacion (that flag only hides the old <select>)
$fu = $filtro_usuario ?? [];
if (empty($moduleStationKey)):
$idEstacion = $fu['id_estacion'] ?? null;
$esTodas    = (int)$idEstacion === 8;
$badgeText  = '';
if ($esTodas):
$badgeText = $user->puesto->tipo_puesto ?? '';
else:
$badgeText = $user->estacion->nombre ?? '';
endif;
$badgeText = trim($badgeText);
if ($badgeText !== ''):
?>
<span class="mb-1 badge rounded-pill text-bg-info"><?= htmlspecialchars($badgeText, ENT_QUOTES, 'UTF-8') ?></span>
<?php
endif;
endif;
