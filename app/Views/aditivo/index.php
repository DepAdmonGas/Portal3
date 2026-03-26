<div id="container" class="mb-4">

<div class="datatables">

<div class="text-end">
    <div class="btn-group">
            <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="ti ti-dots-vertical fs-4"></i>
            </button>
            <ul class="dropdown-menu animated rubberBand">
                <?= !empty($permisos['crear']) ? 
                    '<li><a class="dropdown-item" data-bs-toggle="modal" data-bs-target="#nuevo">Nuevo</a></li>' 
                    : '' 
                ?>
                <li>
                    <a class="dropdown-item" href="bitacora-aditivo/reporte">Reporte</a>
                </li>
                <li>
                    <a class="dropdown-item" href="bitacora-aditivo/inventario">Inventario</a>
                </li>
            </ul>
        </div>
</div>

    <div class="table-responsive">
      <table id="table-aditivo" class="table table-md table-striped table-bordered mb-0 text-nowrap align-middle">
        <thead>

          <tr>
            <th>Folio</th>
            <th>Fecha</th>
            <th>Litros</th>
            <th>No. Factura</th>
            <th>Producto</th>
            <th>Galones</th>
            <th>Fisico</th>
            <th>Estatus</th>
            <th class="text-center">
              <a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>
            </th>
          </tr>

        </thead>
        <tbody></tbody>
      </table>
      </div>

</div>              

<div class="modal fade"
     id="nuevo"
     tabindex="-1"
     data-bs-backdrop="static"
     data-bs-keyboard="false"
     @editar-grupo.window="abrirEditar($event.detail)">

    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Crear registro</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" @click="resetForm()"></button>
            </div>

            <div class="modal-body">

                <label class="form-label">* Litros</label>
                <div class="form-group">
                    <input type="number" class="form-control">
                </div>

                <label class="form-label">* Fecha</label>
                <div class="form-group">
                    <input type="date" class="form-control">
                </div>

                <label class="form-label">* No. Factura</label>
                <div class="form-group">
                    <input type="text" class="form-control">
                </div>

                <label class="form-label">* Producto</label>
                <div class="form-group">
                    <select name="" id="" class="form-control"></select>
                </div>

                <label class="form-label">Galones</label>
                <div class="form-group">
                    <input type="number" class="form-control" disabled>
                </div>

                
            </div>

            <div class="modal-footer">
                <button class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">
                    Cancelar
                </button>

                <button class="btn btn-success">
                  <span>Guardar</span>
              </button>
            </div>
        </div>
    </div>
</div>

</div>