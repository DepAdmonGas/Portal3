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

<div class="row g-2 ms-auto w-100 justify-content-between">
                   
        
    <div class="col-12 col-md-6 d-grid mt-3">
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


    <div class="col-12 col-md-auto d-grid mt-3">
   <a class="btn bg-primary-subtle text-primary"
   :href="url">
    <i class="ti ti-file-dollar"></i> Facturas
</a>
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