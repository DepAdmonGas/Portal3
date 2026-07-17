<div id="container" class="mb-4" data-module-station-key="bitacora-aditivo">

<?php if (!$estacionId): ?>
<div id="aditivo-reporte-empty-message" class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
    Debes de seleccionar una estación del menú superior para poder visualizar el reporte de aditivo.
</div>
<div id="aditivo-reporte-content" style="display:none">
<?php else: ?>
<div id="aditivo-reporte-empty-message" style="display:none" class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
    Debes de seleccionar una estación del menú superior para poder visualizar el reporte de aditivo.
</div>
<div id="aditivo-reporte-content">
<?php endif; ?>

    <div class="text-end mb-4">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevo"><i class="ti ti-plus"></i> Agregar </button>
    </div>

  <div class="datatables">

      <div class="table-responsive">
        <table id="table-aditivo-reporte" class="table table-md table-striped table-bordered mb-0 text-nowrap align-middle">
          <thead>

            <tr>
              <th>#</th>
              <th>Fecha</th>
              <th>Hora</th>
               <th class="text-center">
              <a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>
            </th>
            </tr>

          </thead>
          <tbody></tbody>
        </table>
        </div>

  </div> <!-- end aditivo-reporte-content -->

</div> <!-- end container -->

<div class="modal fade"
     id="nuevo"
     tabindex="-1"
     data-bs-backdrop="static"
     data-bs-keyboard="false"
     x-data="{ ...actions(), ...reporteForm() }">

    <div class="modal-dialog modal-dialog-scrollable modal-lg">

        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header">
                <h4 class="modal-title">Agregar reporte aditivo</h4>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        @click="resetModal()">
                </button>
            </div>

            <!-- BODY -->
            <div class="modal-body">

                <!-- FECHA -->
                <label class="form-label">* Fecha</label>
                <input type="date"
                      class="form-control"
                      x-model="fecha"
                      @input="errors.fecha = false"
                      :class="errors.fecha ? 'is-invalid' : ''">

               <!-- DOCUMENTO -->
              <label class="form-label mt-3">* Documento</label>
              <input type="file"
                    class="form-control"
                    x-ref="documento"
                    @change="handleFile($event)"
                    :class="errors.documento ? 'is-invalid' : ''">

            </div>

            <!-- FOOTER -->
            <div class="modal-footer">

                <button type="button"
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal"
                        @click="resetModal()">
                    Cancelar
                </button>

                <button type="button"
                        class="btn btn-success"
                        @click="submit()"
                        :disabled="loading">

                    <span x-show="!loading">Guardar</span>
                    <span x-show="loading">Guardando...</span>

                </button>

            </div>

        </div>
    </div>
</div>