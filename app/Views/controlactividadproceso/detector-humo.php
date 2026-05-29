<div id="container" class="mb-4"
x-data="{ ...actions(), ...detectorHumo()}">

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
      <table id="table-detector-humo" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
        <thead>
          <tr>
           <th>No. Detector</th>
            <th>Ubicación</th>
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
    <div class="modal-dialog">
    <div class="modal-content">

    <div class="modal-header rounded-0 head-modal">
        <h4 class="modal-title">Agregar Detector de Humo</h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" @click="closeModal()"></button>
    </div>

    <div class="modal-body">


        <label class="form-label">* No. Detector:</label>
        <input type="number" class="form-control mb-2" x-model="no_detector"
                :class="errors.no_detector ? 'is-invalid' : ''"
                @input="errors.no_detector = false" />

        <label class="form-label">* Ubicación:</label>
        <textarea class="form-control mb-2" x-model="ubicacion"
                :class="errors.ubicacion ? 'is-invalid' : ''"
                @input="errors.ubicacion = false"></textarea>

    </div>

    <div class="modal-footer">
        <button class="btn bg-danger-subtle text-danger" @click="closeModal()">Cancelar</button>
        <button class="btn btn-primary" @click="guardar()">Guardar
        </button>
    </div>

    </div>
    </div>
    </div>

</div>