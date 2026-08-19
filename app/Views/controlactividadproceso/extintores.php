<div id="container" class="mb-4"
x-data="{ ...actions(), ...extintores()}">

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
      <table id="table-extintores" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
        <thead>
          <tr>
           <th>No. De extintor</th>
            <th>Ubicación</th>
            <th>Fecha de ultima recarga</th>
            <th>Tipo de Extintor</th>
            <th>Peso Kg</th>
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
          <i class="ti" :class="mode === 'create' ? 'ti-fire-extinguisher' :'ti-edit'"></i>
          <span  x-text="mode === 'create' ? 'Nuevo Extintor' : 'Editar Extintor'"></span>
        </label>
        </h4>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" @click="closeModal()"></button>
    </div>

    <div class="modal-body">


        <label class="form-label">* No. De extintor:</label>
        <input type="number" class="form-control mb-2" x-model="no_extintor"
                :class="errors.no_extintor ? 'is-invalid' : ''"
                @input="errors.no_extintor = false" />

        <label class="form-label">* Ubicación:</label>
        <textarea class="form-control mb-2" x-model="ubicacion"
                :class="errors.ubicacion ? 'is-invalid' : ''"
                @input="errors.ubicacion = false"></textarea>

        <label class="form-label">* Fecha de ultima recarga:</label>
        <input type="date" class="form-control mb-2" x-model="fecha_recarga"
                :class="errors.fecha_recarga ? 'is-invalid' : ''"
                @input="errors.fecha_recarga = false" />

        <label class="form-label">*  Tipo de Extintor:</label>
        <input type="text" class="form-control mb-2" x-model="tipo_extintor"
                :class="errors.tipo_extintor ? 'is-invalid' : ''"
                @input="errors.tipo_extintor = false" />

        <label class="form-label">*  Peso Kg:</label>
        <input type="text" class="form-control mb-2" x-model="peso_kg"
                :class="errors.peso_kg ? 'is-invalid' : ''"
                @input="errors.peso_kg = false" />

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

</div>