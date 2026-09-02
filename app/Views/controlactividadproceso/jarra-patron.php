<div id="container"
data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
data-estacion-id="<?= (int) ($estacionId ?? 0) ?>"
x-data="{ ...actions(), ...jarraPatron()}">

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
      <table id="table-jarra-patron" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
        <thead>
          <tr>
           <th>Marca</th>
            <th>No de serie</th>
            <th>Capacidad</th>
            <th>Material de fabricación</th>
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
           <i class="ti" :class= "mode === 'create' ? 'ti-flask-2' :'ti-edit'"></i>
           <span x-text="mode === 'create' ? 'Nueva Jarra de Patrón' : 'Editar Jarra de Patrón'"></span>
           </label>
        </h4>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" @click="closeModal()"></button>
    </div>

    <div class="modal-body">


        <label class="form-label">* Marca:</label>
        <input type="text" class="form-control mb-2" x-model="marca"
                :class="errors.marca ? 'is-invalid' : ''"
                @input="errors.marca = false" />

        <label class="form-label">* No. Serie:</label>
        <input type="text" class="form-control mb-2" x-model="no_serie"
                :class="errors.no_serie ? 'is-invalid' : ''"
                @input="errors.no_serie = false" />

        <label class="form-label">* Capacidad:</label>
        <input type="text" class="form-control mb-2" x-model="capacidad"
                :class="errors.capacidad ? 'is-invalid' : ''"
                @input="errors.capacidad = false" />

        <label class="form-label">* Material de fabricación:</label>
        <input type="text" class="form-control mb-2" x-model="material"
                :class="errors.material ? 'is-invalid' : ''"
                @input="errors.material = false" />
        

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