<div
    id="container"
    class="pb-4"
    x-data="{
        ...actions(),
        ...puestos()
    }">

    <div
        class="d-flex align-items-center justify-content-end mb-4">

        <button
            type="button"
            class="btn btn-primary"
            @click="openModalCrear()">
            <i class="ti ti-plus me-1"></i>
            Nuevo
        </button>

    </div>

    <div class="datatables">

        <div
            class="table-responsive">

            <table
                id="table-puestos"
                class="table table-striped table-bordered mb-0 text-nowrap align-middle">

                <thead>

                    <tr>

                        <th>
                            #
                        </th>

                        <th>
                            Nombre del Puesto
                        </th>

                        <th>
                            Estatus
                        </th>

                        <th class="text-center">

                            <a class="text-muted">

                                <i
                                    class="ti ti-dots-vertical fs-6"></i>

                            </a>

                        </th>

                    </tr>

                </thead>

                <tbody></tbody>

            </table>

        </div>

    </div>



    <div
        class="modal fade"
        id="modalPuesto"
        tabindex="-1"
        aria-labelledby="modalPuestoLabel"
        aria-hidden="true">

        <div
            class="modal-dialog modal-dialog-centered">

            <div class="modal-content">

                <!-- HEADER -->
                <div class="modal-header bg-primary">

                    <div>

                        <h5
                            class="modal-title fw-semibold text-white"
                            id="modalPuestoLabel"
                            x-text="
                                modo === 'create'
                                    ? 'Nuevo puesto'
                                    : 'Editar puesto'
                            "></h5>

                        <small class="text-white">
                            Información del puesto
                        </small>

                    </div>


                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Cerrar"></button>

                </div>


                <!-- BODY -->
                <div class="modal-body">

                    <div class="mb-3">

                        <label class="form-label">

                            Nombre del puesto

                            <span class="text-danger">
                                *
                            </span>

                        </label>


                        <input
                            type="text"
                            class="form-control"
                            :class="{
                                'is-invalid':
                                    errors.tipo_puesto
                            }"
                            x-model="form.tipo_puesto"
                            @keydown.enter.prevent="guardar()"
                            maxlength="255"
                            autocomplete="off"
                            placeholder="Ej. Gerente de estación">


                        <div class="invalid-feedback">
                            Ingresa el nombre del puesto.
                        </div>

                    </div>

                </div>


                <!-- FOOTER -->
                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal"
                        :disabled="loading">
                        <i class="ti ti-x"></i> Cancelar
                    </button>


                    <button
                        type="button"
                        class="btn btn-success"
                        @click="guardar()"
                        :disabled="loading">

                        <template x-if="!loading">

                            <span>

                                <i
                                    class="ti ti-check me-1"></i>

                                <span
                                    x-text="
                                        modo === 'create'
                                            ? 'Crear puesto'
                                            : 'Guardar cambios'
                                    "></span>

                            </span>

                        </template>


                        <template x-if="loading">

                            <span>

                                <span
                                    class="spinner-border spinner-border-sm me-2"></span>

                                Guardando...

                            </span>

                        </template>

                    </button>

                </div>

            </div>

        </div>

    </div>

</div>