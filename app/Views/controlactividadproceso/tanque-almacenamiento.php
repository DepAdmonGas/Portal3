<div id="container"
x-data="{ ...actions(), ...tanqueAlmacenamiento()}">

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
      <table id="table-tanque-almacenamiento" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
        <thead>
          <tr>
           <th>No. Tanque</th>
            <th>Capacidad</th>
            <th>Producto</th>
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
            x-text="mode === 'create' ? 'Agregar Extintor' : 'Editar Extintor'">
        </h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" @click="closeModal()"></button>
    </div>

    <div class="modal-body">


        <label class="form-label">* No. Tanque:</label>
        <input type="number" class="form-control mb-2" x-model="no_tanque"
                :class="errors.no_tanque ? 'is-invalid' : ''"
                @input="errors.no_tanque = false" />

        <label class="form-label">* Capacidad:</label>
        <textarea class="form-control mb-2" x-model="capacidad"
                :class="errors.capacidad ? 'is-invalid' : ''"
                @input="errors.capacidad = false"></textarea>

        <label class="form-label">* Producto:</label>
        <select class="form-control mb-2" x-model="producto"
                :class="errors.producto ? 'is-invalid' : ''"
                @input="errors.producto = false">
            <option value="">Seleccione un producto</option>
            <option value="G SUPER">G SUPER</option>
            <option value="G PREMIUM">G PREMIUM</option>
            <option value="G DIESEL">G DIESEL</option>
        </select>

        

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