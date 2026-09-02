<div id="container"
data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
data-estacion-id="<?= (int) ($estacionId ?? 0) ?>"
x-data="{ ...actions(), ...sondasMedicion()}">

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

  <div class="datatables">
        <div class="table-responsive pb-4 overflow-x-auto overflow-y-hidden">
      <table id="table-sondas-medicion" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
        <thead>
          <tr>
           <th>No. Sonda</th>
            <th>Marca</th>
            <th>Modelo</th>
            <th>Ubicación</th>
          <th class="text-center" width="100px">
          <a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>
          </th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>

    <!--- Modal nuevo -->

    <div class="modal fade" id="modalNuevo" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

    <div class="modal-header modal-colored-header bg-primary text-white">
        <h4 class="modal-title text-white">
        <label> 
        <i class="ti" :class="mode === 'create' ? 'ti-ruler-3' :'ti-edit'"></i>
          <span x-text="mode === 'create' ? 'Nuevas Sondas de medición' : 'Editar Sonda de medición'"></span>
        </label>
        </h4>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" @click="closeModal()"></button>
    </div>

    <div class="modal-body">


        <label class="form-label">* No. Sonda:</label>
        <input type="number" class="form-control mb-2" x-model="no_sonda"
                :class="errors.no_sonda ? 'is-invalid' : ''"
                @input="errors.no_sonda = false" />

        <label class="form-label">* Marca:</label>
        <input type="text" class="form-control mb-2" x-model="marca"
                :class="errors.marca ? 'is-invalid' : ''"
                @input="errors.marca = false" />

        <label class="form-label">* Modelo:</label>
        <input type="text" class="form-control mb-2" x-model="modelo"
                :class="errors.modelo ? 'is-invalid' : ''"
                @input="errors.modelo = false" />

        <label class="form-label">* Ubicación:</label>
        <input type="text" class="form-control mb-2" x-model="ubicacion"
                :class="errors.ubicacion ? 'is-invalid' : ''"
                @input="errors.ubicacion = false" />
        

    </div>

    <div class="modal-footer">
        <button class="btn bg-danger-subtle text-danger" @click="closeModal()"><i class="ti ti-x"></i> Cancelar</button>
        <button class="btn btn-success" @click="guardar()">
          <i class="ti ti-check"></i>
             <span x-text="mode === 'create' ? 'Guardar' : 'Actualizar'"></span>
        </button>
    </div>

    </div>
    </div>
    </div>
<?php endif; ?>

</div>