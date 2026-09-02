<div id="container" class="mb-4"
data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '') ?>"
data-estacion-id="<?= (int) ($estacionId ?? 0) ?>"
x-data="{ ...actions(), ...corteDiario() }">

<?php if (empty($estacionId)): ?>

    <div id="sasisopa-empty-message"
         class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.
    </div>

<?php else: ?>

    <div id="sasisopa-content">

<div class="text-end mt-2">
   <div class="btn-group">
            <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="ti ti-dots-vertical fs-4"></i>
            </button>
            <ul class="dropdown-menu animated rubberBand">
                <li>
                    <a class="dropdown-item pointer" :href="url"><i class="ti ti-file-dollar"></i> Facturas</a>
                </li>
            </ul>
        </div>
    </div>

<div class="row mt-2">
    <label class="form-label">Año:</label>
    <div class="col-12 col-sm-3">
        <select
            class="form-select"
            x-model.number="year"
            @change="buscar()">
            <?php for($i = date('Y'); $i >= 2019; $i--): ?>
                <option value="<?= $i ?>">
                    <?= $i ?>
                </option>
            <?php endfor; ?>
        </select>
    </div>
    
</div>

<div class="mt-3">
<template
    x-for="item in meses"
    :key="item.mes">

    <div class="alert d-flex justify-content-between align-items-center mb-2"

        :class="item.habilitado
            ? 'alert-primary bg-primary text-white border-0'
            : 'bg-light-subtle'"

        :style="item.habilitado
            ? 'cursor:pointer'
            : ''"

        @click="abrir(item)">

        <div>
            <i class="ti ti-calendar-event me-2 fs-5"></i>
            <strong
                x-text="item.nombre">
            </strong>
            <span
                x-text="item.year">
            </span>
        </div>
        <i
            class="ti ti-arrow-right fs-5"
            x-show="item.habilitado">
        </i>
    </div>
</template>
</div>

</div>

    <?php endif; ?>

</div>