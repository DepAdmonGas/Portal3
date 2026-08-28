<div id="container" class="mb-4"
data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '') ?>"
data-estacion-id="<?= (int) ($estacionId ?? 0) ?>"
x-data="{ ...actions(), ...facturas(<?= $idReporteCre ?>, <?= $year ?>) }">

<?php if (empty($estacionId)): ?>

    <div id="sasisopa-empty-message"
         class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.
    </div>

<?php else: ?>

    <div id="sasisopa-content">

<div  class="container-fluid mt-3">

    <template
        x-if="loading">

        <div class="text-center">

            <div class="spinner-border text-primary"></div>

        </div>

    </template>

    <template
        x-for="mes in meses"
        :key="mes.id">

        <div class="card">

            <div class="card-header bg-white p-3">

                <h5
                    class="mb-0"
                    x-text="mes.nombre + ' ' + mes.year">
                </h5>

            </div>

            <div class="card-body p-3">

                <div class="row">

                    <template
                        x-for="etapa in etapas"
                        :key="etapa.id">

                        <div class="col-xl-4">

                            <div class="card mb-0">

                                <div class="card-header bg-light text-center p-2">

                                    <strong
                                        x-text="etapa.titulo">
                                    </strong>

                                </div>

                                <div class="table-responsive overflow-x-auto overflow-hidden">

                                    <table class="table table-sm table-bordered table-hover align-middle mb-0 pb-0">

                                        <thead>

                                            <tr>
                                                <template
                                                    x-for="producto in productos"
                                                    :key="producto.id">
                                                    <th
                                                        class="text-center"
                                                        x-text="producto.nombre">
                                                    </th>
                                                </template>
                                                <th width="36"></th>
                                            </tr>

                                        </thead>

                                        <tbody>

                                            <tr>

                                                <template
                                                    x-for="producto in productos"
                                                    :key="producto.id">

                                                    <td class="text-center">

                                                        <template
                                                            x-if="obtenerArchivo(mes,etapa,producto)">

                                                            <a
                                                                :href="'/uploads/'+obtenerArchivo(mes,etapa,producto)"
                                                                target="_blank">

                                                                <i class="ti ti-file-type-pdf text-danger fs-7"></i>

                                                            </a>

                                                        </template>

                                                        <template
                                                            x-if="!obtenerArchivo(mes,etapa,producto)">

                                                            <i class="ti ti-x text-secondary fs-7"></i>

                                                        </template>

                                                    </td>

                                                </template>

                                                <td class="text-center">

                                                <a href="javascript:void(0)" @click="abrirModal(mes,etapa)"><i class="ti ti-plus fs-7"></i></a>
                                           

                                                </td>

                                            </tr>

                                        </tbody>

                                    </table>

</div>

</div>

                        </div>

                    </template>

                </div>

            </div>

        </div>

    </template>

</div>

<div class="modal fade"
     id="modalFacturas"
     tabindex="-1">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content border-0 shadow">

            <div class="modal-header modal-colored-header bg-primary text-white">

                     <h4 class="modal-title text-white">

                        Agregar facturas

                    </h4>

                <button
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

            <small
                class="text-muted"
                x-text="modal.mes?.nombre + ' ' + modal.mes?.year">
            </small>

                <div class="alert alert-primary text-primary mt-2">
                    <strong>Periodo:</strong>
                    <span x-text="modal.etapa?.titulo"></span>
                </div>

                <template
                    x-for="producto in productos"
                    :key="producto.id">

                    <div class="mb-3">

                        <div class="">

                            <h6
                                class="fw-bolder"
                                x-text="producto.nombre">
                            </h6>

                            <input
                            type="file"
                            accept=".pdf"

                            class="form-control"

                            :class="{
                                'is-invalid': errores[producto.id]
                            }"

                            @change="
                                archivos[producto.id]=$event.target.files[0];
                                errores[producto.id]=false;
                            ">

                        </div>

                    </div>

                </template>

            </div>

            <div class="modal-footer">

                <button
                    class="btn bg-danger-subtle text-danger"
                    data-bs-dismiss="modal">

                    <i class="ti ti-x"></i> Cancelar

                </button>

                <button
                    class="btn btn-success"

                    @click="guardarFacturas()"

                    :disabled="guardando">

                    <i class="ti ti-check"></i>

                    <span
                        x-show="!guardando">

                        Guardar

                    </span>

                    <span
                        x-show="guardando">

                        <span class="spinner-border spinner-border-sm me-2"></span>

                        Guardando...

                    </span>

                </button>

            </div>

        </div>

    </div>

</div>

    </div>

    <?php endif; ?>

</div>