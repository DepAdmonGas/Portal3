<div id="container" class="mb-4" data-module-station-key="bitacora-aditivo">

<?php if (!$estacionId): ?>
<div id="aditivo-empty-message" class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
Debes de seleccionar una estación del menú superior para poder visualizar la información de la Bitácora de Aditivo.
</div>
<div id="aditivo-content" style="display:none">
<?php else: ?>
<div id="aditivo-empty-message" style="display:none" class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
Debes de seleccionar una estación del menú superior para poder visualizar la información de la Bitácora de Aditivo.
</div>
<div id="aditivo-content">
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

        <div class="text-end">
        <div class="btn-group">
                <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="ti ti-dots-vertical fs-4"></i>
                </button>
                <ul class="dropdown-menu animated rubberBand">
                    <?= !empty($permisos['crear']) ? 
                        '<li><a class="dropdown-item" data-bs-toggle="modal" data-bs-target="#nuevo"> <i class="ti ti-plus"></i> Nuevo </a></li>' 
                        : '' 
                    ?>
                    <li>
                        <a class="dropdown-item" href="bitacora-aditivo/reporte"> <i class="ti ti-report-analytics"></i> Reporte</a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="bitacora-aditivo/inventario"> <i class="ti ti-list-check"></i> Inventario</a>
                    </li>
                </ul>
            </div>
    </div>

    </div>
</div>

<div class="datatables">

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

</div> <!-- end aditivo-content -->
</div>

<div class="modal fade"
     id="nuevo"
     tabindex="-1"
     data-bs-backdrop="static"
     data-bs-keyboard="false"

     x-data="{ ...actions(), ...aditivoForm() }"
     @open-edit.window="openEdit($event.detail)"
>

    <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">

        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header modal-colored-header bg-primary text-white">
                <h4 class="modal-title text-white"
                    x-text="mode === 'create' ? 'Crear registro' : 'Editar registro'">
                </h4>

                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        @click="resetModal()">
                </button>
            </div>

            <!-- BODY -->
            <div class="modal-body">

                <!-- LITROS -->
                <label class="form-label">* Litros</label>
                <input type="number"
                       class="form-control"
                       x-model="litros"
                       @input="calcular(); errors.litros = false"
                       :class="errors.litros ? 'is-invalid' : ''"
                       :disabled="mode === 'edit'">

                <!-- FECHA -->
                <label class="form-label mt-3">* Fecha</label>
                <input type="date"
                       class="form-control"
                       x-model="fecha"
                       @input="errors.fecha = false"
                       :class="errors.fecha ? 'is-invalid' : ''"
                       :disabled="mode === 'edit'">

                <!-- FACTURA -->
                <label class="form-label mt-3">No. Factura</label>
                <input type="text"
                       class="form-control"
                       x-model="no_factura">

                <!-- PRODUCTO -->
                <label class="form-label mt-3">* Producto</label>
                <select class="form-control"
                        x-model="producto"
                        @change="calcular(); errors.producto = false"
                        :class="errors.producto ? 'is-invalid' : ''"
                        :disabled="mode === 'edit'">

                    <option value=""></option>

                    <?php 
                    $productos = [
                        $user->estacion->producto_uno,
                        $user->estacion->producto_dos,
                        $user->estacion->producto_tres
                    ];

                    foreach ($productos as $producto): 
                        if (!empty($producto)): ?>
                            <option value="<?= htmlspecialchars($producto) ?>">
                                <?= htmlspecialchars($producto) ?>
                            </option>
                    <?php endif; endforeach; ?>

                </select>

                <!-- GALONES -->
                <label class="form-label mt-3">Galones</label>
                <input type="number"
                       class="form-control"
                       x-model="galones"
                       disabled>

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