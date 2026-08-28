<div id="container" class="pb-4"
    x-data="{ ...actions(), ...cambioPrecio()}"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">

<?php if (empty($estacionId)): ?>

    <div id="cambioprecio-empty-message"
        class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.
    </div>

<?php else: ?>

    <div id="cambioprecio-content">

<div class="text-end mt-2">
   <div class="btn-group">
            <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="ti ti-dots-vertical fs-4"></i>
            </button>
            <ul class="dropdown-menu animated rubberBand">
                <?= 
                !empty($permisos['crear']) ? 
                '<li>
                  <a class="dropdown-item pointer" @click="openCreate()"><i class="ti ti-plus"></i> Agregar</a>
                </li>' 
                : '' 
                ?>   
                 
            </ul>
        </div>
</div>

    <div class="datatables mt-3">
          <div class="table-responsive overflow-x-auto overflow-y-hidden">
        <table id="table-cambio-precio" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
          <thead>
            <tr>
            <th>#</th>
            <th>Fecha</th>            
            <th>Hora</th>
            <th>G Super</th>
            <th>G Premium</th>
            <th>G Diesel</th>
            <th class="text-center">
            <a class="text-muted"><i class="ti ti-trash fs-7"></i></a>
            </th>
            <th class="text-center">
            <a class="text-muted"><i class="ti ti-check fs-7"></i></a>
            </th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div> 

<div class="modal fade"
     id="modalCambioPrecio"
     tabindex="-1"
     aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header modal-colored-header bg-primary text-white">

                <h4 class="modal-title text-white">

                    Cambio de Precio

                </h4>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <div class="row g-3">

                    <div class="col-md-12">

                        <label class="form-label fw-bolder text-success">

                            G SUPER

                        </label>

                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            class="form-control"

                            x-model="form.gsuper">

                    </div>

                    <div class="col-md-12">

                        <label class="form-label fw-bolder text-danger">

                            G PREMIUM

                        </label>

                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            class="form-control"

                            x-model="form.gpremium">

                    </div>

                    <div class="col-md-12">

                        <label class="form-label fw-bolder">

                            G DIESEL

                        </label>

                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            class="form-control"
                            x-model="form.gdiesel" <?= empty($productoTres) ? 'disabled' : ''; ?> >

                    </div>

                    <div class="col-md-12">

                        <label class="form-label">

                            Fecha programada

                        </label>

                        <input
                            type="date"
                            class="form-control"
                            x-model="form.fecha"
                            @input="errors.fecha = false"
                            :class="errors.fecha ? 'is-invalid' : ''">

                    </div>

                    <div class="col-md-12">

                        <label class="form-label">

                            Hora programada

                        </label>

                        <input
                            type="time"
                            class="form-control"
                            x-model="form.hora"
                            @input="errors.hora = false"
                            :class="errors.hora ? 'is-invalid' : ''">

</div>

    </div>

    <?php endif; ?>

</div>

            </div>

            <div class="modal-footer">

                <button
                    class="btn bg-danger-subtle text-danger"

                    data-bs-dismiss="modal">

                    <i class="ti ti-x"></i> Cancelar

                </button>

                <button
                    class="btn btn-success"

                    @click="guardar()"

                    :disabled="loading">

                    <span
                        x-show="loading"
                        class="spinner-border spinner-border-sm me-2">
                    </span>

                    <i class="ti ti-check"></i>

                    Guardar

                </button>

            </div>

        </div>

    </div>

</div>

</div>