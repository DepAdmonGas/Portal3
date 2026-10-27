<?php
$nombreEstacionSeleccionada = '';
if (!empty($idestacion)) {
    foreach ($estaciones as $estacion) {
        if ((int) $estacion->id === (int) $idestacion) {
            $nombreEstacionSeleccionada = $estacion->nombre ?: $estacion->razonsocial;
            break;
        }
    }
}
?>
<div class="d-flex align-items-center justify-content-between flex-wrap w-100 mb-2">
    <span
        id="filtroEstacionBadge"
        class="badge rounded-pill text-bg-info"
        <?= $nombreEstacionSeleccionada === '' ? 'style="display:none"' : '' ?>>
        <?= htmlspecialchars($nombreEstacionSeleccionada, ENT_QUOTES, 'UTF-8') ?>
    </span>

    <div class="ms-auto">
        <select
            id="filtroEstacion"
            class="form-select form-select-sm"
            style="min-width:260px;">
            <option value="" <?= empty($idestacion) ? 'selected' : '' ?>>Todas las estaciones</option>
            <optgroup label="Estaciones">
                <?php foreach ($estaciones as $estacion): ?>
                    <option
                        value="<?= (int) $estacion->id ?>"
                        <?= (int) $estacion->id === (int) $idestacion ? 'selected' : '' ?>>
                        <?= htmlspecialchars(
                            $estacion->nombre ?: $estacion->razonsocial,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </option>
                <?php endforeach; ?>
            </optgroup>
        </select>
    </div>
</div>
