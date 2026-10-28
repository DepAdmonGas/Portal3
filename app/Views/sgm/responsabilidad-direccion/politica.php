  <style>
  span[style] {
    color: inherit !important;
    background-color: transparent !important;
}

  strong[style] {
    color: inherit !important;
    background-color: transparent !important;
}

</style>

<div id="container" 
class="pb-4"
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
    x-data="{ ...actions(), ...politicaForm() }"  
>
    <div class="row align-items-center g-3 mt-3 mb-3">
        <div class="col-12 col-md-6">
            <div class="programar-horario-date-filter d-inline-flex align-items-center bg-light border rounded-pill px-3 px-md-4 py-2 gap-2 w-100">
                <label for="politica-sgm-fecha" class="text-muted fw-semibold text-nowrap d-flex align-items-center gap-1 mb-0">
                    <i class="ti ti-calendar fs-5 text-primary"></i>
                    <span>Fecha:</span>
                </label>
                <input
                    id="politica-sgm-fecha"
                    type="date"
                    class="form-control border-0 bg-transparent fw-semibold flex-grow-1"
                    x-model="fecha"
                >
            </div>
        </div>
        <div class="col-12 col-md-6 d-flex justify-content-md-end">
            <button
                type="button"
                class="btn btn-success"
                @click="guardar">
                <i class="ti ti-check"></i>
                Actualizar
            </button>
        </div>
    </div>

<div class="class="card mt-4">
<div class="card-body">
        <div
            x-ref="editor"
            style="height:350px"
        ></div>

    

    </div>

</div>    

</div>

</div>

<?php endif; ?>

</div>
