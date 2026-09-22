<?php $esImportacion = ($contexto ?? '') === 'importacion'; ?>
<div id="container" class="mb-4" data-module-station-key="bitacora-aditivo" data-base-url="<?= $baseUrl ?>" data-importacion="<?= $esImportacion ? '1' : '0' ?>">

<?php if (!$estacionId): ?>
<div id="aditivo-inventario-empty-message" class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
Debes de seleccionar una estación del menú superior para poder visualizar el inventario de aditivo.
</div>
<div id="aditivo-inventario-content" style="display:none">
<?php else: ?>
<div id="aditivo-inventario-empty-message" style="display:none" class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
Debes de seleccionar una estación del menú superior para poder visualizar el inventario de aditivo.
</div>
<div id="aditivo-inventario-content">
<?php endif; ?>



<div class="row mt-4 mb-4">
    <div class="col-md-6 order-2 order-md-1">
        <div class="fs-3">
        Inventario (Gasolina Hitec 6590C):
        <span class="badge rounded-pill text-bg-info fs-1" id="inv-gasolina"><?= $inventario['gasolina'] ?> Galones </span>
        </div>

        <div class="fs-3">
        Inventario (Diesel Hitec 4133G): 
        <span class="badge rounded-pill text-bg-info fs-1" id="inv-diesel"><?= $inventario['diesel'] ?> Galones </span>
        </div>
    </div>
    <div class="col-md-6 order-1 order-md-2">

        <div class="d-flex align-items-center justify-content-end gap-2">
        <a href="<?= $baseUrl ?>" class="btn bg-danger-subtle text-danger">
            <i class="ti ti-arrow-left"></i> Regresar
        </a>
        <?= (!$esImportacion && $capacidades['puedeCrear']) ? '<button class="btn bg-primary-subtle text-primary" data-bs-toggle="modal" data-bs-target="#nuevo"><i class="ti ti-plus"></i> Nuevo </button>' : '' ?>
    </div>

    </div>
</div>

  <div class="datatables">

      <div class="table-responsive">
        <table id="table-aditivo-inventario" class="table table-md table-striped table-bordered mb-0 text-nowrap align-middle">
          <thead>

            <tr>
              <th class="text-center align-middle">#</th>
              <th class="text-center align-middle">Fecha</th>
              <th class="text-center align-middle">Aditivo</th>
              <th class="text-center align-middle">Galones</th>
              <th class="text-start align-middle">Detalle</th>
            </tr>

          </thead>
          <tbody></tbody>
        </table>
        </div> <!-- end table-responsive -->

  </div> <!-- end datatables -->

</div> <!-- end aditivo-inventario-content -->

</div> <!-- end container -->

<div class="modal fade"
     id="nuevo"
     tabindex="-1"
     data-bs-backdrop="static"
     data-bs-keyboard="false"
     x-data="{ ...actions(), ...inventarioForm() }">

    <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">

        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header modal-colored-header bg-primary text-white">
                <h4 class="modal-title text-white"><i class="ti ti-flask"></i>
 Nuevo aditivo al inventario</h4>

                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        @click="resetModal()">
                </button>
            </div>

            <!-- BODY -->
            <div class="modal-body">

                <!-- Galones -->
                <label class="form-label">*  Aditivo Gasolina Hitec 6590C: <small>(Galones)</small> </label>
                <input type="number"
                       class="form-control"
                       x-model="gasolina">

                <!-- FECHA -->
                <label class="form-label mt-3">* Aditivo Diesel Hitec 4133G: <small>(Galones)</small> </label>
                <input type="number"
                       class="form-control"
                       x-model="diesel">

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