<div id="container"
x-data="{ ...actions(), ...sondasMedicion()}">

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
    <div class="modal-dialog">
    <div class="modal-content">

    <div class="modal-header rounded-0 head-modal">
        <h4 class="modal-title"
            x-text="mode === 'create' ? 'Agregar Sondas de medición' : 'Editar Sonda de medición'">
        </h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" @click="closeModal()"></button>
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
        <button class="btn bg-danger-subtle text-danger" @click="closeModal()">Cancelar</button>
        <button class="btn btn-primary" @click="guardar()">
             <span x-text="mode === 'create' ? 'Guardar' : 'Actualizar'"></span>
        </button>
    </div>

    </div>
    </div>
    </div>

</div>