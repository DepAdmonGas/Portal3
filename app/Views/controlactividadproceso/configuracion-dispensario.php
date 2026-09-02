<div id="container"
data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
data-estacion-id="<?= (int) ($estacionId ?? 0) ?>"
x-data="{ ...actions(), ...dispensario()}">

<?php if (empty($estacionId)): ?>
 
    <div id="sasisopa-empty-message"
         class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.
    </div>

<?php else: ?>
<div class="text-end">
      <?= 
        !empty($permisos['crear']) ? 
        '<button type="button" class="btn bg-primary-subtle text-primary" @click="modalopen()">
        <i class="ti ti-plus"></i> Nuevo
        </button>' 
        : '' 
        ?>     
    </div>

  <div class="datatables mt-3">
        <div class="table-responsive pb-4 overflow-x-auto overflow-y-hidden">
      <table id="table-dispensario" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
        <thead>
          <tr>
           <th>No Dispensario</th>
            <th>Marca</th>
            <th>Modelo</th>
            <th>Serie</th>
            <th>Mangueras G SUPER</th>
            <th>Mangueras G PREMIUM</th>
            <th>Mangueras G DIESEL</th>
          <th class="text-center" width="100px">
          <a class="text-muted"><i class="ti ti-trash fs-6"></i></a>
          </th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>

  <!--- Modal nuevo -->

    <div class="modal fade" id="modalNuevo" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
    <div class="modal-content">

    <div class="modal-header modal-colored-header bg-primary text-white">
        <h4 class="modal-title text-white">
        <i class="ti ti-gas-station"></i>
        Nuevo Dispensario</h4>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" @click="closeModal()"></button>
    </div>

    <div class="modal-body">

    <div class="row">
         <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12">
            <label class="form-label">* No. Dispensario:</label>
            <input type="number" class="form-control mb-2" x-model="no_dispensario"
                    :class="errors.no_dispensario ? 'is-invalid' : ''"
                    @input="errors.no_dispensario = false" />
         </div>
         <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12">
            <label class="form-label">* Marca:</label>
            <input type="text" class="form-control mb-2" x-model="marca"
                    :class="errors.marca ? 'is-invalid' : ''"
                    @input="errors.marca = false" />
         </div>
         <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12">
            <label class="form-label">* Modelo:</label>
            <input type="text" class="form-control mb-2" x-model="modelo"
                    :class="errors.modelo ? 'is-invalid' : ''"
                    @input="errors.modelo = false" />
         </div>
         <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12">
            <label class="form-label">* Serie:</label>
            <input type="text" class="form-control mb-2" x-model="serie"
                :class="errors.serie ? 'is-invalid' : ''"
                @input="errors.serie = false" />
         </div>
         <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12">
            <label class="form-label">Mangueras G SUPER:</label>
            <input type="text" class="form-control mb-2" x-model="producto1"
                    :class="errors.producto1 ? 'is-invalid' : ''"
                    @input="errors.producto1 = false" />
         </div>
         <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12">
            <label class="form-label">Mangueras G PREMIUM:</label>
            <input type="text" class="form-control mb-2" x-model="producto2"
                :class="errors.producto2 ? 'is-invalid' : ''"
                @input="errors.producto2 = false" />
         </div>
         <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12">
            <label class="form-label">Mangueras G DIESEL:</label>
            <input type="text" class="form-control mb-2" x-model="producto3"
                :class="errors.producto3 ? 'is-invalid' : ''"
                @input="errors.producto3 = false" />
         </div>
    </div> 

    </div>

    <div class="modal-footer">
        <button class="btn bg-danger-subtle text-danger" @click="closeModal()"><i class="ti ti-x"></i> Cancelar</button>
        <button class="btn btn-success" @click="guardar()"><i class="ti ti-check"></i> Guardar
        </button>
    </div>

    </div>
    </div>
    </div>
<?php endif; ?>

</div>