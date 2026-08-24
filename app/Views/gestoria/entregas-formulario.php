<div id="container" class="pb-4"
    x-data="{ ...actions(), ...entregas(<?= $id ?>)}">


    <div class="row mt-4">

        <!-- FECHA -->
        <div class="col-xl-3 col-lg-3 col-md-3 col-sm-12 mb-2">
            <div class="fw-bolder mb-1">
                * FECHA:
            </div>

            <input
                type="date"
                class="form-control  mt-1"
                id="Fecha"
                x-model="entrega.fecha"
                @input="errors.fecha = false"
                :class="errors.fecha ? 'is-invalid' : ''">
        </div>


        <!-- DESTINATARIO -->
        <div class="col-xl-4 col-lg-4 col-md-4 col-sm-12 mb-2">
            <div class="fw-bolder mb-1">
                * DESTINATARIO:
            </div>

            <input
                type="text"
                class="form-control  mt-1"
                x-model="entrega.destinatario"
                @input="errors.destinatario = false"
                :class="errors.destinatario ? 'is-invalid' : ''">
        </div>


        <!-- ESTACIÓN -->
        <div class="col-xl-5 col-lg-5 col-md-5 col-sm-12 mb-2">

            <div class="fw-bolder mb-1">
                * ESTACIÓN DE ENVÍO:
            </div>

            <select
                x-ref="selectEstacionBody"
                id="idEstacion"
                class="form-control  mt-1"
                x-model="entrega.estacion">

                <template
                    x-for="estacion in estaciones"
                    :key="estacion.id">
                    <option
                        :value="estacion.razonsocial"
                        x-text="estacion.razonsocial"></option>
                </template>

                <!-- Personas / empresas adicionales -->
                <option value="Martin Quinzaños García">
                    Martin Quinzaños García
                </option>

                <option value="Aurelio Quinzaños Suarez">
                    Aurelio Quinzaños Suarez
                </option>

                <option value="Acueducto Guadalupe S.A. de C.V.">
                    Acueducto Guadalupe S.A. de C.V.
                </option>

                <option value="Wingate School S.C.">
                    Wingate School S.C.
                </option>

                <option value="Sabino Aguirre S.A. de C.V.">
                    Sabino Aguirre S.A. de C.V.
                </option>

                <option value="Servicio Ventura Puente S.A. de C.V.">
                    Servicio Ventura Puente S.A. de C.V.
                </option>

            </select>

        </div>

        <!-- DOCUMENTOS -->

        <div class="row mt-3">

            <div class="col-8">

                <h3
                    class="">
                    Documentos
                </h3>

            </div>


            <div
                class="col-4"
                x-show="entrega.estatus == 0">

                <div class="text-end">
                    <button
                        type="button"
                        class="btn btn-info"
                        @click="openDocumento()">
                        <i class="ti ti-plus"></i>
                        Agregar

                    </button>
                </div>

            </div>

        </div>

        <!-- Tabla de documentos -->
        <div class="table-responsive mt-3">

            <table class="table table-bordered table-striped table-hover table-sm w-100">

                <thead class="text-white bg-primary">
                    <tr>

                        <th class="text-center align-middle">
                            No.
                        </th>


                        <th class="text-center align-middle">
                            Razón Social
                        </th>


                        <th class="text-center align-middle">
                            Nombre del documento
                        </th>

                        <th class="text-center align-middle">
                            Fecha del oficio
                        </th>

                        <th class="text-center align-middle">
                            Original y/o copia
                        </th>

                        <th
                            class="text-center align-middle"
                            width="32">

                            <i class="ti ti-file-upload text-muted fs-6"></i>

                        </th>

                        <th
                            class="text-center align-middle"
                            width="32">
                            <i class="ti ti-trash text-muted fs-6"></i>
                        </th>

                    </tr>
                </thead>

                <tbody>

                    <template x-if="documentos.length === 0">

                        <tr>
                            <td
                                colspan="7"
                                class="text-muted text-center align-middle">
                                <small>
                                    No se encontró información para mostrar.
                                </small>
                            </td>
                        </tr>

                    </template>

                    <template
                        x-for="(documento, index) in documentos"
                        :key="documento.id">

                        <tr>

                            <!-- No. -->
                            <td class="text-center align-middle">
                                <b x-text="index + 1"></b>
                            </td>


                            <!-- Razón Social -->
                            <td
                                class="text-center align-middle"
                                x-text="documento.razonsocial"></td>


                            <!-- Documento -->
                            <td
                                class="text-center align-middle"
                                x-text="documento.documento"></td>


                            <!-- Fecha -->
                            <td
                                class="text-center align-middle"
                                x-text="documento.fecha"></td>


                            <!-- Original / copia -->
                            <td
                                class="text-center align-middle"
                                x-text="documento.detalle"></td>


                            <!-- Archivo -->
                            <td class="text-center align-middle">

                                <a
                                    href="javascript:void(0)"
                                    class="pointer"
                                    @click="openAcuse(documento.id,documento.documento, documento.archivo)">

                                    <i class="ti ti-file-upload fs-6"></i>
                                </a>

                            </td>


                            <!-- Eliminar -->
                            <td class="text-center align-middle">

                                <template x-if="entrega.estatus == 0">

                                    <a
                                        href="javascript:void(0)"
                                        class="pointer"
                                        @click="eliminar(documento.id)">

                                        <i class="ti ti-trash text-danger fs-6"></i>
                                    </a>
                                </template>

                                <template x-if="entrega.estatus >= 1">

                                    <a>
                                        <i class="ti ti-x text-muted fs-6"></i>
                                    </a>

                                </template>

                            </td>

                        </tr>

                    </template>

                </tbody>

            </table>

        </div>

        <!-- QUIEN RECIBE -->
        <template x-if="entrega.estatus >= 1">
            <div class="col-12 mt-3">

                <div class="fw-bolder mb-1">
                    * NOMBRE DE QUIEN RECIBE:
                </div>

                <div>

                    <input
                        type="text"
                        class="form-control  mt-2"
                        id="Recibe"
                        placeholder="Ingresa el nombre de quien recibe..."
                        x-model="entrega.recibe"
                        @input="errors.recibe = false"
                        :class="errors.recibe ? 'is-invalid' : ''">

                </div>

            </div>
        </template>

        <!-- FINALIZAR -->
        <div class="text-end">

            <button
                type="button"
                class="btn btn-labeled2 btn-success mt-3"
                x-show="entrega.estatus == 0"
                @click="finalizar()">

                <span class="btn-label2">
                    <i class="ti ti-check"></i>
                </span>

                Finalizar

            </button>

            <button
                type="button"
                class="btn btn-labeled2 btn-success mt-3"
                x-show="entrega.estatus == 1"
                @click="finalizarEntrega()">

                <span class="btn-label2">
                    <i class="ti ti-check"></i>
                </span>

                Finalizar Entrega

            </button>

        </div>

    </div>

    <!-- Modal Nuevo -->
    <div
        class="modal fade"
        id="modalDocumento"
        tabindex="-1"
        aria-hidden="true">

        <div class="modal-dialog modal-md modal-dialog-centered">

            <div class="modal-content">

                <div class="modal-header bg-primary">

                    <h4 class="modal-title text-white">
                        Agregar documento
                    </h4>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                    </button>

                </div>


                <div class="modal-body">

                    <!-- ESTACIÓN -->
                    <div class="text-muted fw-bold mb-1">
                        ESTACIÓN DE ENVÍO:
                    </div>

                    <select
                        x-ref="selectEstacionDocumento"
                        id="selectEstacionDocumento"
                        class="form-control"
                        x-model="selectEstacionDocumento">

                        <option value="">
                            Selecciona una opción...
                        </option>

                        <template
                            x-for="estacion in estaciones"
                            :key="estacion.id">

                            <option
                                :value="estacion.id"
                                x-text="estacion.razonsocial"></option>

                        </template>

                    </select>


                    <!-- DOCUMENTO -->
                    <div class="text-muted fw-bold mt-3 mb-1">
                        * NOMBRE DEL DOCUMENTO:
                    </div>

                    <textarea
                        class="form-control "
                        x-model="documento"
                        @input="errors.documento = false"
                        :class="errors.documento ? 'is-invalid' : ''"
                        rows="3"></textarea>


                    <!-- FECHA -->
                    <div class="text-muted fw-bold mt-3 mb-1">
                        * FECHA DEL OFICIO:
                    </div>

                    <input
                        type="date"
                        class="form-control "
                        x-model="fechaOficio"
                        @change="errors.fechaOficio = false"
                        :class="errors.fechaOficio ? 'is-invalid' : ''">


                    <!-- ORIGINAL / COPIA -->
                    <div class="text-muted fw-bold mt-3 mb-1">
                        * ORIGINAL Y/O COPIA:
                    </div>

                    <select
                        class="form-select "
                        x-model="originalCopia"
                        @change="errors.originalCopia = false"
                        :class="errors.originalCopia ? 'is-invalid' : ''">

                        <option value="">
                            Selecciona una opción...
                        </option>

                        <option value="Original">
                            Original
                        </option>

                        <option value="Copia">
                            Copia
                        </option>

                    </select>

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
                        @click="agregarDocumento()">
                        <i class="ti ti-check"></i>
                        Agregar
                    </button>

                </div>

            </div>

        </div>

    </div>

    <!-- Modal agregar documento -->

    <div
        class="modal fade"
        id="modalAcuse"
        tabindex="-1"
        aria-hidden="true">

        <div class="modal-dialog modal-md modal-dialog-centered">

            <div class="modal-content">

                <div class="modal-header bg-primary">

                    <h4 class="modal-title text-white">
                        Agregar acuse
                    </h4>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                    </button>

                </div>


                <div class="modal-body">

                    <h5 x-text="nombreDocumento"></h5>

                    <div class="text-muted fw-bolder mb-2 mt-2">
                        * ACUSE:
                    </div>


                    <input
                        x-ref="acuse"
                        class="form-control"
                        :class="errors.acuse ? 'is-invalid' : ''"
                        type="file"
                        accept="image/jpeg,image/png,image/gif,image/webp"
                        @change="errors.acuse = false">


                    <div
                        x-show="errors.acuse"
                        class="invalid-feedback">

                        Seleccione una imagen.

                    </div>

                    <!-- Vista previa -->
                    <div
                        class="mt-3 text-center"
                        x-show="acusePreview">

                        <div class="text-muted fw-bolder mb-2">
                            Vista previa
                        </div>

                        <img
                            :src="acusePreview"
                            class="img-fluid rounded border"
                            style="max-height: 300px;"
                            alt="Vista previa del acuse">

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
                        @click="agregarAcuse()">

                        <i class="ti ti-check"></i>
                        Agregar

                    </button>

                </div>

            </div>

        </div>

    </div>

</div>