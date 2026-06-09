<div id="container"
x-data="{ ...actions(), ...jarraPatron()}">

<div class="text-end">
      <?= 
        !empty($permisos['crear']) ? 
        '<button type="button" class="btn btn-primary" @click="modalopen()">
        <i class="ti ti-plus"></i> Nuevo
        </button>' 
        : '' 
        ?>     
    </div>

  <div class="datatables mt-3">
    <div class="table-responsive">
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
    <div class="modal-dialog">
    <div class="modal-content">

    <div class="modal-header rounded-0 head-modal">
        <h4 class="modal-title"
            x-text="mode === 'create' ? 'Agregar Jarra de Patrón' : 'Editar Jarra de Patrón'">
        </h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" @click="closeModal()"></button>
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
        <button class="btn bg-danger-subtle text-danger" @click="closeModal()">Cancelar</button>
        <button class="btn btn-primary" @click="guardar()">
             <span x-text="mode === 'create' ? 'Guardar' : 'Actualizar'"></span>
        </button>
    </div>

    </div>
    </div>
    </div>

</div>