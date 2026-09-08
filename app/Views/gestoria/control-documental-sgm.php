<div
    id="container"
    class="pb-4"
    x-data="{
        ...actions(),
        ...controlDocumentalSgm(
            <?= $idEstacion ?>
        )
    }">

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
        x-show="!cargando"
        x-cloak>

        <div
            class="table-responsive mt-4 mb-4">

            <table
                class="table table-sm table-bordered table-hover mb-0 align-middle">

                <tbody>

                    <template
                        x-for="
                            documento in seccion3
                        "
                        :key="
                            documento.id
                        ">

                        <tr>

                            <!-- Nombre -->
                            <td
                                x-text="
                                    documento.nombre
                                ">
                            </td>


                            <!-- Subir -->
                            <td
                                width="45"
                                class="text-center">

                                <a
                                    href="javascript:void(0)"
                                    class="pointer"
                                    @click="
                                        abrirDocumento(
                                            documento
                                        )
                                    "
                                    title="Agregar documento">

                                    <i
                                        class="ti ti-upload fs-6">
                                    </i>

                                </a>

                            </td>


                            <!-- Descargar -->
                            <td
                                width="45"
                                class="text-center">


                                <!-- Tiene documento -->
                                <template
                                    x-if="
                                        documento.archivo_actual
                                    ">

                                    <a
                                        :href="
                                            documento
                                                .archivo_actual
                                                .url
                                        "
                                        download
                                        title="Descargar">

                                        <i
                                            class="ti ti-download fs-6 text-primary">
                                        </i>

                                    </a>

                                </template>


                                <!-- No tiene -->
                                <template
                                    x-if="
                                        !documento.archivo_actual
                                    ">

                                    <span
                                        class="text-danger"
                                        title="Sin documento">

                                        <i
                                            class="ti ti-x fs-6">
                                        </i>

                                    </span>

                                </template>

                            </td>

                        </tr>

                    </template>


                    <!-- Vacío -->
                    <tr
                        x-show="
                            seccion3.length === 0
                        ">

                        <td
                            colspan="3"
                            class="text-center text-muted py-3">

                            No se encontró información para mostrar.

                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

        <div
            class="table-responsive mb-4">

            <table
                class="table table-sm table-bordered table-hover mb-0 align-middle">

                <thead>

                    <tr>

                        <th
                            colspan="6"
                            class="text-center bg-primary text-white">

                            Manual de procedimientos del Sistema de Gestión de Medición

                        </th>

                    </tr>


                    <tr>

                        <th
                            class="text-center bg-light">

                            #

                        </th>

                        <th
                            class="text-center bg-light">

                            Codificación

                        </th>

                        <th class="bg-light">

                            Nombre

                        </th>

                        <th
                            class="text-center bg-light">

                            Fecha de aprobación

                        </th>

                        <th
                            class="text-center bg-light"
                            width="45">

                            <i
                                class="ti ti-upload fs-6">
                            </i>

                        </th>

                        <th
                            class="text-center bg-light"
                            width="45">

                            <i
                                class="ti ti-download fs-6">
                            </i>

                        </th>

                    </tr>

                </thead>


                <tbody>

                    <template
                        x-for="
                            documento in seccion1
                        "
                        :key="
                            documento.id
                        ">

                        <tr>

                            <td
                                class="text-center fw-semibold"
                                x-text="
                                    documento.id
                                ">
                            </td>


                            <td
                                class="text-center"
                                x-text="
                                    documento.codificacion
                                ">
                            </td>


                            <td
                                x-text="
                                    documento.nombre
                                ">
                            </td>


                            <td
                                class="text-center"
                                x-text="
                                    documento
                                        .fecha_aprobacion_formateada
                                ">
                            </td>


                            <!-- Subir -->
                            <td
                                class="text-center">

                                <a
                                    href="javascript:void(0)"
                                    class="pointer"
                                    @click="
                                        abrirDocumento(
                                            documento
                                        )
                                    "
                                    title="Agregar documento">

                                    <i
                                        class="ti ti-upload fs-6">
                                    </i>

                                </a>

                            </td>


                            <!-- Descargar -->
                            <td
                                class="text-center">

                                <template
                                    x-if="
                                        documento.archivo_actual
                                    ">

                                    <a
                                        :href="
                                            documento
                                                .archivo_actual
                                                .url
                                        "
                                        download>

                                        <i
                                            class="ti ti-download fs-6 text-primary">
                                        </i>

                                    </a>

                                </template>


                                <template
                                    x-if="
                                        !documento.archivo_actual
                                    ">

                                    <span
                                        class="text-danger">

                                        <i
                                            class="ti ti-x fs-6">
                                        </i>

                                    </span>

                                </template>

                            </td>

                        </tr>

                    </template>


                    <tr
                        x-show="
                            seccion1.length === 0
                        ">

                        <td
                            colspan="6"
                            class="text-center text-muted">

                            No se encontró información para mostrar.

                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

        <div
            class="table-responsive">

            <table
                class="table table-sm table-bordered table-hover mb-0 align-middle">

                <thead>

                    <tr>

                        <th
                            colspan="6"
                            class="text-center bg-primary text-white">

                            Formatos del Sistema de Gestión de Medición

                        </th>

                    </tr>


                    <tr>

                        <th
                            class="text-center bg-light">

                            #

                        </th>

                        <th
                            class="text-center bg-light">

                            Codificación

                        </th>

                        <th class="bg-light">

                            Nombre

                        </th>

                        <th
                            class="text-center bg-light">

                            Fecha de aprobación

                        </th>

                        <th
                            class="text-center bg-light"
                            width="45">

                            <i
                                class="ti ti-upload fs-6">
                            </i>

                        </th>

                        <th
                            class="text-center bg-light"
                            width="45">

                            <i
                                class="ti ti-download fs-6">
                            </i>

                        </th>

                    </tr>

                </thead>


                <tbody>

                    <template
                        x-for="
                            documento in seccion2
                        "
                        :key="
                            documento.id
                        ">

                        <tr>

                            <td
                                class="text-center fw-semibold"
                                x-text="
                                    documento.id
                                ">
                            </td>


                            <td
                                class="text-center"
                                x-text="
                                    documento.codificacion
                                ">
                            </td>


                            <td
                                x-text="
                                    documento.nombre
                                ">
                            </td>


                            <td
                                class="text-center"
                                x-text="
                                    documento
                                        .fecha_aprobacion_formateada
                                ">
                            </td>


                            <!-- Subir -->
                            <td
                                class="text-center">

                                <a
                                    href="javascript:void(0)"
                                    class="pointer"
                                    @click="
                                        abrirDocumento(
                                            documento
                                        )
                                    ">

                                    <i
                                        class="ti ti-upload fs-6">
                                    </i>

                                </a>

                            </td>


                            <!-- Descargar -->
                            <td
                                class="text-center">

                                <template
                                    x-if="
                                        documento.archivo_actual
                                    ">

                                    <a
                                        :href="
                                            documento
                                                .archivo_actual
                                                .url
                                        "
                                        download>

                                        <i
                                            class="ti ti-download fs-6 text-primary">
                                        </i>

                                    </a>

                                </template>


                                <template
                                    x-if="
                                        !documento.archivo_actual
                                    ">

                                    <span
                                        class="text-danger">

                                        <i
                                            class="ti ti-x fs-6">
                                        </i>

                                    </span>

                                </template>

                            </td>

                        </tr>

                    </template>


                    <tr
                        x-show="
                            seccion2.length === 0
                        ">

                        <td
                            colspan="6"
                            class="text-center text-muted">

                            No se encontró información para mostrar.

                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

    </div>


    <div
        class="modal fade"
        id="modalDocumentoSgm"
        tabindex="-1"
        aria-hidden="true">

        <div
            class="modal-dialog modal-lg modal-dialog-centered">

            <div
                class="modal-content">


                <!-- Header -->
                <div
                    class="modal-header bg-primary">

                    <h5
                        class="modal-title text-white">

                        Control documental del SGM

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


                    <!-- Documento -->
                    <h6
                        class="mb-3"
                        x-text="
                            documentoSeleccionado
                                ? (
                                    documentoSeleccionado.codificacion +
                                    ' ' +
                                    documentoSeleccionado.nombre
                                )
                                : ''
                        ">
                    </h6>


                    <!-- Archivo -->
                    <div
                        class="mb-3">

                        <label
                            class="form-label fw-semibold">

                            Documento

                        </label>


                        <input
                            type="file"
                            class="form-control"
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
                        <template
                            x-if="
                                documentoNombre
                            ">

                            <div
                                class="alert alert-light border mt-2 mb-0 d-flex align-items-center justify-content-between">

                                <div
                                    class="d-flex align-items-center gap-2">

                                    <i
                                        class="ti ti-file fs-5 text-primary">
                                    </i>


                                    <span
                                        class="small text-muted"
                                        x-text="
                                            documentoNombre
                                        ">
                                    </span>

                                </div>


                                <button
                                    type="button"
                                    class="btn btn-sm text-danger p-0"
                                    @click="
                                        limpiarDocumento()
                                    ">

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
                            @click="
                                guardarDocumento()
                            "
                            :disabled="
                                guardando
                            ">


                            <template
                                x-if="
                                    !guardando
                                ">

                                <span
                                    class="d-flex align-items-center gap-1">

                                    <i
                                        class="ti ti-check fs-5">
                                    </i>

                                    Guardar documento

                                </span>

                            </template>


                            <template
                                x-if="
                                    guardando
                                ">

                                <span
                                    class="d-flex align-items-center gap-2">

                                    <span
                                        class="spinner-border spinner-border-sm">
                                    </span>

                                    Guardando...

                                </span>

                            </template>

                        </button>

                    </div>


                    <hr>

                    <div
                        class="table-responsive">

                        <table
                            class="table table-bordered table-striped table-sm mb-0 align-middle">

                            <thead>

                                <tr>

                                    <th
                                        class="text-center"
                                        width="50">

                                        #

                                    </th>

                                    <th
                                        class="text-center">

                                        Fecha

                                    </th>

                                    <th
                                        class="text-center"
                                        width="70">

                                        <i
                                            class="ti ti-download fs-5">
                                        </i>

                                    </th>

                                    <th
                                        class="text-center"
                                        width="70">

                                        <i
                                            class="ti ti-trash fs-5">
                                        </i>

                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <template
                                    x-for="
                                        (
                                            archivo,
                                            index
                                        ) in (
                                            documentoSeleccionado
                                                ?.archivos
                                            ?? []
                                        )
                                    "
                                    :key="
                                        archivo.id
                                    ">

                                    <tr>

                                        <!-- Número -->
                                        <td
                                            class="text-center"
                                            x-text="
                                                index + 1
                                            ">
                                        </td>


                                        <!-- Fecha -->
                                        <td
                                            class="text-center"
                                            x-text="
                                                archivo
                                                    .fecha_formateada
                                            ">
                                        </td>


                                        <!-- Descargar -->
                                        <td
                                            class="text-center">

                                            <a
                                                :href="
                                                    archivo.url
                                                "
                                                download
                                                title="Descargar">

                                                <i
                                                    class="ti ti-download fs-5 text-primary">
                                                </i>

                                            </a>

                                        </td>


                                        <!-- Eliminar -->
                                        <td
                                            class="text-center">

                                            <a
                                                href="javascript:void(0)"
                                                class="pointer text-danger"
                                                @click="
                                                    eliminarDocumento(
                                                        archivo.id
                                                    )
                                                "
                                                title="Eliminar">

                                                <i
                                                    class="ti ti-trash fs-5">
                                                </i>

                                            </a>

                                        </td>

                                    </tr>

                                </template>


                                <!-- Vacío -->
                                <tr
                                    x-show="
                                        (
                                            documentoSeleccionado
                                                ?.archivos
                                            ?? []
                                        ).length === 0
                                    ">

                                    <td
                                        colspan="4"
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