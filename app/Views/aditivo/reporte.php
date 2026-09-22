<?php $esImportacion = ($contexto ?? '') === 'importacion'; ?>
<div id="container" class="mb-4" data-module-station-key="bitacora-aditivo" data-base-url="<?= $baseUrl ?>" data-importacion="<?= $esImportacion ? '1' : '0' ?>">

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

    <div class="d-flex align-items-center justify-content-end gap-2 mb-4">
        <a href="<?= $baseUrl ?>" class="btn bg-danger-subtle text-danger">
            <i class="ti ti-arrow-left"></i> Regresar
        </a>
        <?= (!$esImportacion && $capacidades['puedeCrear']) ? '<button class="btn bg-primary-subtle text-primary" data-bs-toggle="modal" data-bs-target="#nuevo"><i class="ti ti-plus"></i> Nuevo </button>' : '' ?>
    </div>

  <div class="datatables">

      <div class="table-responsive">
        <table id="table-aditivo-reporte" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
          <thead>

            <tr>
              <th class="text-center align-middle" width="96px">#</th>
              <th class="text-center align-middle">Fecha</th>
              <th class="text-center align-middle">Hora</th>
               <th class="text-center align-middle" width="48px">
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

    <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">

        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header modal-colored-header bg-primary text-white">
                <h4 class="modal-title text-white">
                     <i class="ti ti-clipboard-text"></i>
                    Nuevo reporte aditivo
                </h4>

                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        @click="resetModal()">
                </button>
            </div>

            <!-- BODY -->
            <div class="modal-body">

                <!-- FECHA -->
                <label class="form-label">* Fecha:</label>
                <input type="date"
                      class="form-control"
                      x-model="fecha"
                      @input="errors.fecha = false"
                      :class="errors.fecha ? 'is-invalid' : ''">

               <!-- DOCUMENTO -->
              <label class="form-label mt-3">* Documento:</label>
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
                    <i class="ti ti-x"></i> Cancelar
                </button>

                <button type="button"
                        class="btn btn-success"
                        @click="submit()"
                        :disabled="loading">
                    <i class="ti ti-check"></i>
                    <span x-show="!loading">Guardar</span>
                    <span x-show="loading">Guardando...</span>

                </button>

            </div>

        </div>
    </div>
</div>