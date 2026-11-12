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
>

<div class="row g-0 ms-auto w-100 justify-content-end">
                    <div class="col-12 col-md-auto d-grid mb-3 mt-3">
                       <button
                class="btn btn-success"
                @click="guardar()"
            >
            <i class="ti ti-check"></i>
                Actualizar
            </button>
                    </div>
                  </div>
  
<div class="card">
    <div class="card-body p-0">

        <div
            x-ref="editor"
            style="height:300px"
        ></div>

       

    </div>
</div>


</div>

</div>

<?php endif; ?>

</div>