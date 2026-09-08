<div
    id="container"
    class="pb-4"
    x-data="{
        ...actions(),
        ...calibracionTanquesDetalle(
            <?= $idEstacion ?>,
            <?= $idCalibracion ?>
        )
    }">


    <div class="row align-items-end mt-3 mb-3">

        <div
            class="col-12 col-md-4">

            <label
                class="form-label fw-semibold">

                Fecha:

            </label>


            <input
                type="date"
                class="form-control"
                x-model="form.fecha"
                :class="{
                            'is-invalid':
                                errors.fecha
                        }"
                @change="
                            errors.fecha = false
                        ">


            <div
                class="invalid-feedback">

                La fecha es obligatoria.

            </div>

        </div>


        <div
            class="col-12 col-md-auto mt-3 mt-md-0">

            <button
                type="button"
                class="btn btn-success"
                @click="finalizar()"
                :disabled="finalizando || cargando">

                <template
                    x-if="!finalizando">

                    <span
                        class="d-flex align-items-center gap-1">

                        <i
                            class="ti ti-check fs-5">
                        </i>

                        Finalizar Calibración de Tanques

                    </span>

                </template>


                <template
                    x-if="finalizando">

                    <span
                        class="d-flex align-items-center gap-2">

                        <span
                            class="spinner-border spinner-border-sm"
                            role="status"
                            aria-hidden="true">
                        </span>

                        Guardando...

                    </span>

                </template>

            </button>

        </div>

    </div>

    <div
        class="text-center py-5"
        x-show="cargando">

        <div
            class="spinner-border text-primary"
            role="status">
        </div>

        <div
            class="text-muted mt-2">

            Cargando información...

        </div>

    </div>


    <div
        class="table-responsive"
        x-show="!cargando"
        x-cloak>

        <table
            class="table table-striped table-bordered mb-0 align-middle">

            <thead>

                <tr>

                    <th
                        class="text-center"
                        width="60px">

                        #

                    </th>

                    <th>
                        Documento
                    </th>

                    <th
                        class="text-center"
                        width="80px">

                        <i
                            class="ti ti-upload fs-6 text-muted">
                        </i>

                    </th>

                </tr>

            </thead>


            <tbody>

                <template
                    x-for="documento in documentos"
                    :key="documento.id">

                    <tr>

                        <!-- ID -->
                        <td
                            class="text-center fw-semibold"
                            x-text="documento.id">
                        </td>


                        <!-- Documento -->
                        <td
                            x-text="documento.nombre">
                        </td>


                        <!-- Archivos -->
                        <td
                            class="text-center">

                            <div
                                class="position-relative d-inline-block">


                                <a
                                    class="pointer"
                                    @click="
                                        abrirDocumentos(
                                            documento
                                        )
                                    "
                                    :title="
                                        'Agregar archivos a ' +
                                        documento.nombre
                                    ">

                                    <i
                                        class="ti ti-upload fs-6">
                                    </i>

                                </a>


                                <!-- Total -->
                                <span
                                    class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary"
                                    style="
                                    font-size: 9px;
                                    padding: 2px 5px;
                                    min-width: 16px;
                                    line-height: 12px;"
                                    x-show="Number(documento.total) > 0"
                                    x-text="documento.total">
                                </span>

                            </div>

                        </td>

                    </tr>

                </template>


                <!-- Vacío -->
                <tr
                    x-show="
                        documentos.length === 0
                    ">

                    <td
                        colspan="3"
                        class="text-center text-muted py-4">

                        No se encontró información para mostrar.

                    </td>

                </tr>

            </tbody>

        </table>

    </div>


    <div
        class="modal fade"
        id="modalDocumentos"
        tabindex="-1"
        aria-hidden="true">

        <div
            class="modal-dialog modal-lg modal-dialog-centered">

            <div
                class="modal-content">

                <div
                    class="modal-header bg-primary">

                    <h5
                        class="modal-title text-white"
                        x-text="
                            documentoSeleccionado?.nombre
                            ?? 'Documentos'
                        ">
                    </h5>


                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Cerrar">
                    </button>

                </div>


                <!-- Body -->
                <div
                    class="modal-body">


                    <!-- Archivo -->
                    <div
                        class="mb-3">

                        <label
                            class="form-label fw-semibold">

                            Documento PDF

                        </label>


                        <input
                            type="file"
                            class="form-control"
                            accept="application/pdf,.pdf"
                            x-ref="documento"
                            @change="
                                validarDocumento()
                            "
                            :class="{
                                'is-invalid':
                                    errors.documento
                            }">


                        <div
                            class="invalid-feedback"
                            x-text="
                                errors.documentoMensaje
                            ">
                        </div>


                        <!-- Archivo seleccionado -->
                        <template x-if="documentoNombre">

                            <div
                                class="alert alert-light border mt-2 mb-0 d-flex align-items-center justify-content-between">

                                <div
                                    class="d-flex align-items-center gap-2">

                                    <i
                                        class="ti ti-file-type-pdf text-danger fs-5">
                                    </i>

                                    <span
                                        class="small text-muted"
                                        x-text="documentoNombre">
                                    </span>

                                </div>


                                <button
                                    type="button"
                                    class="btn btn-sm text-danger p-0"
                                    @click="limpiarDocumento()">

                                    <i
                                        class="ti ti-x fs-5">
                                    </i>

                                </button>

                            </div>

                        </template>

                    </div>


                    <!-- Guardar -->
                    <div
                        class="d-flex justify-content-end mb-4">

                        <button
                            type="button"
                            class="btn btn-success"
                            @click="guardarDocumento()"
                            :disabled="guardando">

                            <template
                                x-if="!guardando">

                                <span
                                    class="d-flex align-items-center gap-1">

                                    <i
                                        class="ti ti-check fs-5">
                                    </i>

                                    Guardar

                                </span>

                            </template>


                            <template
                                x-if="guardando">

                                <span
                                    class="d-flex align-items-center gap-2">

                                    <span
                                        class="spinner-border spinner-border-sm"
                                        role="status"
                                        aria-hidden="true">
                                    </span>

                                    Guardando...

                                </span>

                            </template>

                        </button>

                    </div>

                    <div
                        class="table-responsive">

                        <table
                            class="table table-sm table-striped table-bordered mb-0 align-middle">

                            <thead>

                                <tr>

                                    <th>
                                        Archivo
                                    </th>

                                    <th
                                        class="text-center"
                                        width="70px">

                                        Descargar

                                    </th>

                                    <th
                                        class="text-center"
                                        width="70px">

                                        Eliminar

                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <template
                                    x-for="
                                        archivo in
                                        (
                                            documentoSeleccionado
                                                ?.archivos
                                            ?? []
                                        )
                                    "
                                    :key="
                                        archivo.id
                                    ">

                                    <tr>

                                        <!-- Archivo -->
                                        <td>

                                            <div
                                                class="d-flex align-items-center gap-2">

                                                <i
                                                    class="ti ti-file-type-pdf text-danger fs-5">
                                                </i>

                                                <span
                                                    class="text-break"
                                                    x-text="
                                                        archivo.archivo
                                                    ">
                                                </span>

                                            </div>

                                        </td>


                                        <!-- Descargar -->
                                        <td
                                            class="text-center">

                                            <a
                                                :href="
                                                    archivo.url
                                                "
                                                download
                                                class="pointer"
                                                title="Descargar">

                                                <i
                                                    class="ti ti-download fs-6">
                                                </i>

                                            </a>

                                        </td>


                                        <!-- Eliminar -->
                                        <td
                                            class="text-center">

                                            <aa
                                                class="pointer"
                                                @click="
                                                    eliminarDocumento(
                                                        archivo.id
                                                    )
                                                "
                                                title="Eliminar">

                                                <i
                                                    class="ti ti-trash fs-6 text-danger">
                                                </i>

                                            </aa>

                                        </td>

                                    </tr>

                                </template>


                                <!-- Sin archivos -->
                                <tr
                                    x-show="
                                        (
                                            documentoSeleccionado
                                                ?.archivos
                                            ?? []
                                        ).length === 0
                                    ">

                                    <td
                                        colspan="3"
                                        class="text-center text-muted">

                                        No se encontró información para mostrar.

                                    </td>

                                </tr>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>