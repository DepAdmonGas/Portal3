<div id="container" class="mb-4">

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

        <div class="text-end">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevo"><i class="ti ti-plus"></i> Agregar </button>
    </div>

    </div>
</div>

  <div class="datatables">

      <div class="table-responsive">
        <table id="table-aditivo-inventario" class="table table-md table-striped table-bordered mb-0 text-nowrap align-middle">
          <thead>

            <tr>
              <th>#</th>
              <th>Fecha</th>
              <th>Aditivo</th>
              <th>Galones</th>
              <th>Detalle</th>
            </tr>

          </thead>
          <tbody></tbody>
        </table>
        </div>

  </div>

</div>

<div class="modal fade"
     id="nuevo"
     tabindex="-1"
     data-bs-backdrop="static"
     data-bs-keyboard="false"
     x-data="{ ...actions(), ...inventarioForm() }">

    <div class="modal-dialog modal-dialog-scrollable modal-lg">

        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header">
                <h4 class="modal-title">Agregar aditivo al inventario</h4>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        @click="resetForm()">
                </button>
            </div>

            <!-- BODY -->
            <div class="modal-body">

                <!-- Galones -->
                <label class="form-label">*  Aditivo Gasolina Hitec 6590C <small>Galones</small> </label>
                <input type="number"
                       class="form-control"
                       x-model="gasolina">

                <!-- FECHA -->
                <label class="form-label mt-3">* Aditivo Diesel Hitec 4133G <small>Galones</small> </label>
                <input type="number"
                       class="form-control"
                       x-model="diesel">

            </div>

            <!-- FOOTER -->
            <div class="modal-footer">

                <button type="button"
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal"
                        @click="resetForm()">
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