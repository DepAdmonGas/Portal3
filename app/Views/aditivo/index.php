<?php $esImportacion = ($contexto ?? '') === 'importacion'; ?>
<div id="container" class="mb-4" data-module-station-key="bitacora-aditivo" data-base-url="<?= $baseUrl ?>" data-importacion="<?= $esImportacion ? '1' : '0' ?>">

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
        <div class="fs-3 mb-2">
        Inventario (Gasolina Hitec 6590C):
        <span class="badge rounded-pill text-bg-info fs-1" id="inv-gasolina"><?= $inventario['gasolina'] ?> Galones </span>
        </div>

        <div class="fs-3 mb-2">
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
                    <?= (!$esImportacion && $capacidades['puedeCrear']) ? 
                        '<li class="pointer"><a class="dropdown-item" data-bs-toggle="modal" data-bs-target="#nuevo"> <i class="ti ti-plus"></i> Nuevo </a></li>' 
                        : '' 
                    ?>
                    <?= ($esImportacion && $capacidades['puedeVerResumen']) ?
                        '<li><a class="dropdown-item" href="' . $baseUrl . '/resumen"> <i class="ti ti-chart-dots"></i> Resumen</a></li>'
                        : ''
                    ?>
                    <li>
                        <a class="dropdown-item" href="<?= $baseUrl ?>/reporte"> <i class="ti ti-report-analytics"></i> Reporte</a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="<?= $baseUrl ?>/inventario"> <i class="ti ti-list-check"></i> Inventario</a>
                    </li>
                </ul>
            </div>
    </div>

    </div>
</div>


<div class="datatables">
    <div class="table-responsive">
      <table id="table-aditivo" class="table table-md table-striped table-bordered mb-0 w-100 text-nowrap align-middle">
        <thead>

          <tr>
            <th>Folio</th>
            <th>Fecha</th>
            <th>Litros</th>
            <th>No. Factura</th>
            <th>Producto</th>
            <th>Galones</th>
            <th>Fisico</th>
            <?php if (!$esImportacion): ?>
            <th>Estatus</th>
            <th class="text-center">
              <a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>
            </th>
            <?php endif; ?>
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
               
        <h4 class="modal-title text-white">
        <label> 
        <i class="ti" :class="mode === 'create' ? 'ti-clipboard-text' :'ti-edit'"></i>
          <span x-text="mode === 'create' ? 'Nuevo registro' : 'Editar registro'"></span>
        </label>
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
                <label class="form-label">* Litros:</label>
                <input type="number"
                       class="form-control"
                       x-model="litros"
                       @input="calcular(); errors.litros = false"
                       :class="errors.litros ? 'is-invalid' : ''"
                       :disabled="mode === 'edit'">

                <!-- FECHA -->
                <label class="form-label mt-3">* Fecha:</label>
                <input type="date"
                       class="form-control"
                       x-model="fecha"
                       @input="errors.fecha = false"
                       :class="errors.fecha ? 'is-invalid' : ''"
                       :disabled="mode === 'edit'">

                <!-- FACTURA -->
                <label class="form-label mt-3">No. Factura:</label>
                <input type="text"
                       class="form-control"
                       x-model="no_factura">

                <!-- PRODUCTO -->
                <label class="form-label mt-3">* Producto:</label>
                <select class="form-select"
                        x-model="producto"
                        @change="calcular(); errors.producto = false"
                        :class="errors.producto ? 'is-invalid' : ''"
                        :disabled="mode === 'edit'">

                    <option value="">Selecciona una opción...</option>

                    <?php
                    $productos = array_filter([
                        $estacionProductos['producto_uno'],
                        $estacionProductos['producto_dos'],
                        $estacionProductos['producto_tres']
                    ]);

                    foreach ($productos as $producto): ?>
                            <option value="<?= htmlspecialchars($producto) ?>">
                                <?= htmlspecialchars($producto) ?>
                            </option>
                    <?php endforeach; ?>

                </select>

                <!-- GALONES -->
                <label class="form-label mt-3">Galones:</label>
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