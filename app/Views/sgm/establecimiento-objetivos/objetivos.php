<div id="container"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">

<?php if (empty($estacionId)): ?>

    <div id="sgm-empty-message"
        class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SGM.
    </div>

<?php else: ?>

<div id="sgm-content">

<div
    x-data="{ ...actions(), ...objetivosForm() }"
    class="card mt-4"
>

    <div class="card-body">

        <div
            x-ref="editor"
            style="height:300px"
        ></div>

        <div class="text-end mt-3">

            <button
                class="btn btn-primary"
                @click="guardar()"
            >
                Guardar objetivos
            </button>

        </div>

    </div>

</div>

</div>

<?php endif; ?>

</div>