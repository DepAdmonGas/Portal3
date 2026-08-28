<div id="container" class="pb-4"
    x-data="{ ...actions(), ...analisisRiesgo(<?= $idEstacion ?>)}" data-idestacion="<?= $idEstacion ?>">

    <div class="text-end mt-2">
        <button class="btn btn-primary" @click="openNuevo()"><i class="ti ti-plus"></i> Nuevo</button>
    </div>

    <div class="datatables mt-4">

        <div class="table-responsive">
            <table id="table-analisis-riesgo" class="table table-sm table-striped table-bordered mb-0 text-nowrap align-middle">
                <thead>

                    <tr>
                        <th width="48px" class="text-center align-middle">#</th>
                        <th class="text-center">Fecha</th>
                        <th class="text-center">Descripción</th>
                        <th class="text-center">
                            <a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>
                        </th>
                    </tr>

                </thead>
                <tbody></tbody>
            </table>
        </div>

    </div>


    <!-- Modal Nuevo -->
    <div
        class="modal fade"
        id="modalNuevo"
        x-ref="modalNuevo"
        tabindex="-1">

        <div class="modal-dialog modal-md modal-dialog-centered">

            <div class="modal-content">

                <div class="modal-header bg-primary">

                    <h4
                        class="modal-title text-white"
                        x-text="modoFormulario === 'crear'
                            ? 'Nuevo análisis de riesgo'
                            : 'Editar análisis de riesgo'">
                    </h4>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <div class="row">

                        <!-- Fecha -->
                        <div class="col-12 mb-3">

                            <label class="form-label fw-semibold">
                                Fecha
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="date"
                                class="form-control"
                                x-model="form.fecha"
                                :class="{ 'is-invalid': errors.fecha }"
                                @input="errors.fecha = false">

                            <div
                                class="invalid-feedback"
                                x-show="errors.fecha">
                                La fecha es obligatoria.
                            </div>

                        </div>

                        <!-- Descripción -->
                        <div class="col-12 mb-3">

                            <label class="form-label fw-semibold">
                                Descripción
                                <span class="text-danger">*</span>
                            </label>

                            <textarea
                                class="form-control"
                                rows="4"
                                x-model="form.descripcion"
                                :class="{ 'is-invalid': errors.descripcion }"
                                @input="errors.descripcion = false"
                                placeholder="Ingrese la descripción del análisis"></textarea>

                            <div
                                class="invalid-feedback"
                                x-show="errors.descripcion">
                                La descripción es obligatoria.
                            </div>

                        </div>

                        <!-- Documento -->
                        <div class="col-12">

                            <label class="form-label fw-semibold">

                                Documento PDF

                                <span
                                    class="text-danger"
                                    x-show="modoFormulario === 'crear'">
                                    *
                                </span>

                                <span
                                    class="text-muted fw-normal small"
                                    x-show="modoFormulario === 'editar'">
                                    (opcional)
                                </span>

                            </label>

                            <input
                                type="file"
                                class="form-control"
                                x-ref="documento"
                                accept="application/pdf,.pdf"
                                :class="{ 'is-invalid': errors.documento }"
                                @change="validarDocumento()">

                            <div
                                class="invalid-feedback"
                                x-show="errors.documento"
                                x-text="errors.documentoMensaje">
                            </div>

                        </div>

                        <!-- Archivo seleccionado -->
                        <div
                            class="col-12 mt-3"
                            x-show="documentoNombre">

                            <div class="alert alert-light border mb-0">

                                <div class="d-flex align-items-center">

                                    <i class="ti ti-file-type-pdf fs-5 text-danger me-2"></i>

                                    <div class="w-100">

                                        <span
                                            class="fw-semibolder text-muted"
                                            x-text="documentoNombre">
                                        </span>

                                    </div>

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-light"
                                        x-show="modoFormulario === 'crear' || $refs.documento?.files.length"
                                        @click="limpiarDocumento()">

                                        <i class="ti ti-x"></i>

                                    </button>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">

                        <i class="ti ti-x"></i>
                        Cancelar

                    </button>

                    <button
                        type="button"
                        class="btn btn-success"
                        @click="guardar()"
                        :disabled="guardando">

                        <span x-show="!guardando">

                            <i class="ti ti-check"></i>

                            <span
                                x-text="modoFormulario === 'crear'
                ? 'Guardar'
                : 'Actualizar'">
                            </span>

                        </span>

                        <span x-show="guardando">

                            <span
                                class="spinner-border spinner-border-sm me-1">
                            </span>

                            <span
                                x-text="modoFormulario === 'crear'
                ? 'Guardando...'
                : 'Actualizando...'">
                            </span>

                        </span>

                    </button>

                </div>

            </div>

        </div>

    </div>
    <!-- Modal Nuevo -->

    <!-- Modal Anexos -->
    <div
        class="modal fade"
        id="modalAnexos"
        x-ref="modalAnexos"
        tabindex="-1">

        <div class="modal-dialog modal-lg modal-dialog-centered">

            <div class="modal-content">

                <div class="modal-header bg-primary">

                    <h4 class="modal-title text-white">
                        Anexos análisis de riesgo
                    </h4>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <!-- Información del análisis -->
                    <div class="row mb-3">

                        <div class="col-md-4 mb-2">

                            <div class="text-muted small">
                                Fecha:
                            </div>

                            <div
                                class="fw-semibold"
                                x-text="analisisSeleccionado.fecha_formateada || 'S/I'">
                            </div>

                        </div>

                        <div class="col-md-8 mb-2">

                            <div class="text-muted small">
                                Descripción:
                            </div>

                            <div
                                class="fw-semibold"
                                x-text="analisisSeleccionado.descripcion || 'S/I'">
                            </div>

                        </div>

                    </div>

                    <hr>

                    <!-- Formulario nuevo anexo -->
                    <div class="row">

                        <div class="col-12 mb-3">

                            <label class="form-label fw-semibold">
                                Descripción
                                <span class="text-danger">*</span>
                            </label>

                            <textarea
                                class="form-control"
                                rows="3"
                                x-model="anexoForm.descripcion"
                                :class="{ 'is-invalid': anexoErrors.descripcion }"
                                @input="anexoErrors.descripcion = false"
                                placeholder="Ingrese la descripción del anexo"></textarea>

                            <div
                                class="invalid-feedback"
                                x-show="anexoErrors.descripcion">
                                La descripción es obligatoria.
                            </div>

                        </div>

                        <div class="col-12 mb-3">

                            <label class="form-label fw-semibold">
                                Documento PDF
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="file"
                                class="form-control"
                                x-ref="anexoDocumento"
                                accept="application/pdf,.pdf"
                                :class="{ 'is-invalid': anexoErrors.documento }"
                                @change="validarAnexoDocumento()">

                            <div
                                class="invalid-feedback"
                                x-show="anexoErrors.documento"
                                x-text="anexoErrors.documentoMensaje">
                            </div>

                        </div>

                        <!-- Archivo seleccionado -->
                        <div
                            class="col-12 mb-3"
                            x-show="anexoDocumentoNombre">

                            <div class="alert alert-light border mb-0">

                                <div class="d-flex align-items-center">

                                    <i class="ti ti-file-type-pdf fs-5 text-danger me-2"></i>

                                    <div class="w-100">

                                        <span
                                            class="fw-semibolder text-muted"
                                            x-text="anexoDocumentoNombre">
                                        </span>

                                    </div>

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-light"
                                        @click="limpiarAnexoDocumento()">

                                        <i class="ti ti-x"></i>

                                    </button>

                                </div>

                            </div>

                        </div>

                        <div class="col-12 text-end">

                            <button
                                type="button"
                                class="btn btn-success"
                                @click="guardarAnexo()"
                                :disabled="guardandoAnexo">

                                <span x-show="!guardandoAnexo">

                                    <i class="ti ti-check"></i>
                                    Guardar anexo

                                </span>

                                <span x-show="guardandoAnexo">

                                    <span
                                        class="spinner-border spinner-border-sm me-1">
                                    </span>

                                    Guardando...

                                </span>

                            </button>

                        </div>

                    </div>

                    <hr>

                    <!-- Tabla -->
                    <div class="table-responsive">

                        <table class="table table-sm table-striped table-bordered align-middle mb-0">

                            <thead>

                                <tr>

                                    <th>
                                        Descripción
                                    </th>

                                    <th
                                        width="70"
                                        class="text-center">
                                        PDF
                                    </th>

                                    <th
                                        width="70"
                                        class="text-center">

                                        <i class="ti ti-dots-vertical"></i>

                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                <!-- Cargando -->
                                <tr x-show="cargandoAnexos">

                                    <td
                                        colspan="3"
                                        class="text-center py-4">

                                        <div
                                            class="spinner-border spinner-border-sm me-2">
                                        </div>

                                        Cargando anexos...

                                    </td>

                                </tr>

                                <!-- Sin registros -->
                                <tr
                                    x-show="!cargandoAnexos && anexos.length === 0">

                                    <td
                                        colspan="3"
                                        class="text-muted text-center">

                                        No se encontró información para mostrar.

                                    </td>

                                </tr>

                                <!-- Anexos -->
                                <template
                                    x-for="anexo in anexos"
                                    :key="anexo.id">

                                    <tr>

                                        <td x-text="anexo.descripcion"></td>

                                        <td class="text-center">

                                            <a
                                                :href="anexo.url"
                                                class="text-danger"
                                                download
                                                title="Descargar PDF">

                                                <i class="ti ti-file-type-pdf fs-5"></i>

                                            </a>

                                        </td>

                                        <td class="text-center">

                                            <button
                                                type="button"
                                                class="btn btn-sm text-danger"
                                                @click="eliminarAnexo(anexo.id)"
                                                title="Eliminar">

                                                <i class="ti ti-trash fs-5"></i>

                                            </button>

                                        </td>

                                    </tr>

                                </template>

                            </tbody>

                        </table>

                    </div>

                </div>

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">

                        <i class="ti ti-x"></i>
                        Cerrar

                    </button>

                </div>

            </div>

        </div>

    </div>
    <!-- Modal Anexos -->


</div>