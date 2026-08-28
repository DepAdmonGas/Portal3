<div id="container" class="mb-4"
data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '') ?>"
data-estacion-id="<?= (int) ($estacionId ?? 0) ?>"
x-data="{ ...actions(), ...corteNuevo({
        idReporteCre: <?= $idReporteCre ?>,
        modo: '<?= $modo ?? 'nuevo' ?>',
        fecha: '<?= $fecha ?? '' ?>'
     }) }">

<?php if (empty($estacionId)): ?>

    <div id="sasisopa-empty-message"
         class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.
    </div>

<?php else: ?>

    <div id="sasisopa-content">


          <div class="row mt-3">

          <div class="col-12 col-sm-3">
        
            <div class="input-group mb-3">
            <span class="input-group-text fw-bolder">Fecha:</span>
            <input
                type="date"
                class="form-control"
                x-model="fecha"
                min="<?= $diamin ?>"
                max="<?= $diamax ?>"
                :class="{ 'is-invalid': errors.fecha }"
                @input="errors.fecha = false">
            </div>

            </div>

          </div>


<template x-for="(producto,index) in productos" :key="index">

    <div class="border mb-3">

        <div class="p-3">

            <!-- Titulo -->
            <div style="font-size:1.2em">
                <label :style="`border-bottom:2px solid var(--bs-${producto.color})`">
                    Producto:
                    <b x-text="producto.nombre"></b>
                </label>
            </div>

            <div class="row">

                <!-- ========================= -->
                <!-- VOLUMEN -->
                <!-- ========================= -->

                <div class="col-xl-5 col-lg-5 col-md-12 col-sm-12">

                    <div class="text-muted mt-2 mb-2 fs-3">
                        <b>* 1.</b> Agregar el volumen inicial, final y ventas en (Lt).
                    </div>

                    <div style="overflow-x:auto">

                        <table class="table table-bordered table-sm">

                            <thead>

                            <tr>
                                <th class="text-center text-muted">
                                    Volumen (Lt) Inicial
                                </th>

                                <th class="text-center text-muted">
                                    Volumen (Lt) de venta
                                </th>

                                <th class="text-center text-muted">
                                    Volumen (Lt) Final
                                </th>

                            </tr>

                            </thead>

                            <tbody>

                            <tr>

                                <td class="p-0">

                                    <input
                                        type="number"
                                        min="0"
                                        step="any"
                                        class="form-control border-0"
                                        placeholder="Inicial"
                                        x-model="producto.volumen.inicial"
                                        :class="{ 'is-invalid': errors.volumen[index]?.inicial }"
                                        @input="errors.volumen[index].inicial = false">

                                </td>

                                <td class="p-0">

                                    <input
                                        type="number"
                                        min="0"
                                        step="any"
                                        class="form-control border-0"
                                        placeholder="Venta"
                                        x-model="producto.volumen.venta"
                                        :class="{ 'is-invalid': errors.volumen[index]?.venta }"
                                        @input="errors.volumen[index].venta = false">

                                </td>

                                <td class="p-0">

                                    <input
                                        type="number"
                                        min="0"
                                        step="any"
                                        class="form-control border-0"
                                        placeholder="Final"
                                        x-model="producto.volumen.final"
                                        :class="{ 'is-invalid': errors.volumen[index]?.final }"
                                        @input="errors.volumen[index].final = false">

                                </td>

                            </tr>

                            </tbody>

                        </table>

                    </div>

                </div>

                <!-- ========================= -->
                <!-- PIPAS -->
                <!-- ========================= -->

                <div class="col-xl-7 col-lg-7 col-md-12 col-sm-12">

                    <div class="text-muted mt-2 mb-2 fs-3">
                        <b>* 2.</b> Agregar el volumen de las compras de pipas.
                    </div>

                    <div style="overflow-x:auto">

                    <a class="btn btn-sm btn-light mb-2"                   
                    href="javascript:void(0)"
                    @click="agregarPipa(producto)">
                    <i class="ti ti-plus fs-6"></i>
                    </a>

                        <table class="table table-bordered table-sm">

                            <thead>

                            <tr>

                                <th class="text-center align-middle">
                                    Volumen (Lt) de Compra
                                </th>

                                <th class="text-center align-middle">
                                    Precio ($) por litro de producto
                                </th>

                                <th class="text-center align-middle">
                                    Costo ($) del flete mas IVA
                                </th>

                                <th class="text-center align-middle">
                                    No. De factura
                                </th>

                                <th class="text-center align-middle">
                                    Nombre o Razón Social del Transportista
                                </th>

                                <th class="text-center align-middle">
                                    Importe
                                </th>

                                <th class="text-center align-middle">
                                   <i class="ti ti-trash fs-6"></i>
                                </th>

                            </tr>

                            </thead>

                            <tbody>

                            <template x-for="(pipa,i) in producto.pipas.filter(p => !p.eliminar)" :key="i">

                                <tr>

                                    <td class="p-0">

                                        <div class="input-group">

                                            <span
                                                class="input-group-text border-0 rounded-0"
                                                style="font-size:.9em">

                                                Pipa <span x-text="i+1"></span>

                                            </span>

                                            <input
                                                type="number"
                                                min="0"
                                                step="any"
                                                class="form-control border-0"
                                                x-model="pipa.volumen"
                                                @input="calcularPrecio(pipa)">

                                        </div>

                                    </td>

                                    <td class="p-0">

                                        <input
                                            type="number"
                                            min="0"
                                            step="any"
                                            class="form-control border-0 rounded-0 bg-light"
                                            x-model="pipa.precio"
                                            readonly
                                            tabindex="-1">

                                    </td>

                                    <td class="p-0">

                                        <input
                                            type="number"
                                            min="0"
                                            step="any"
                                            class="form-control border-0"
                                            x-model="pipa.costo">

                                    </td>

                                    <td class="p-0">

                                        <input
                                            type="text"
                                            class="form-control border-0"
                                            x-model="pipa.factura">

                                    </td>

                                    <td class="p-0">

                                        <input
                                            type="text"
                                            class="form-control border-0"
                                            x-model="pipa.transportista">

                                    </td>

                                    <td class="p-0">

                                        <input
                                            type="number"
                                            min="0"
                                            step="any"
                                            class="form-control border-0"
                                            x-model="pipa.importe"
                                            @input="calcularPrecio(pipa)">

                                    </td>

                                    <td class="text-center align-middle">
                                    <a href="javascript:void(0)" @click="eliminarPipa(producto,i)">
                                    <i class="ti ti-trash text-danger fs-6"></i>
                                    </a>

                                    </td>

                                </tr>

                            </template>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>

    </div>

</template>

      <div class="text-end mt-2">
        <button
            class="btn btn-success"
            @click="submit()"
            :disabled="loading">

            Guardar cambios

        </button>
      </div>

    </div>

    <?php endif; ?>

</div>