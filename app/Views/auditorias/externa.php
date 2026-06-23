<div id="container"
x-data="{ ...actions(), ...auditoriaExterna()}">

    <div class="text-end">
        <?= 
            !empty($permisos['crear']) ? 
            '<button type="button" class="btn btn-primary" @click="abrirModalAgregar()">
            <i class="ti ti-plus"></i> Nuevo
            </button>' 
            : '' 
            ?>     
    </div>

    <div class="table-responsive mt-3">

        <table
            class="table table-bordered table-striped table-hover table-sm">

            <thead>

                <tr>

                    <th class="text-center bg-primary text-white align-middle">
                        #
                    </th>
                    <th class="text-center bg-primary text-white align-middle">
                        Fecha
                    </th>
                    <th class="text-center bg-primary text-white align-middle">
                        Prestador de servicio
                    </th>
                    <th
                        class="text-center bg-primary text-white align-middle"
                        colspan="3">
                        Fo.ADMONGAS.024
                        <small>
                            (INFORME DE AUDITORÍA)
                        </small>
                    </th>
                    <th
                        class="text-center bg-primary text-white align-middle"
                        colspan="3">
                        Fo.ADMONGAS.025
                        <small>
                            (PLAN DE ATENCIÓN DE HALLAZGOS)
                        </small>
                    </th>
                    <th class="text-center bg-primary text-white align-middle">
                        ASEA
                    </th>
                    <th class="text-center bg-primary text-white align-middle">
                       <i class="ti ti-trash text-white fs-7"></i>
                    </th>
                </tr>

            </thead>

            <tbody>

                <template
                    x-if="!registros.length">
                    <tr>
                        <td
                            colspan="12"
                            class="text-center">
                            No se encontró información
                        </td>
                    </tr>
                </template>

                <template
                    x-for="item in registros"
                    :key="item.id">
                    <tr>
                        <td
                            class="text-center align-middle"
                            x-text="item.id">
                        </td>
                        <td
                            class="text-center align-middle"
                            x-text="item.fecha_larga">
                        </td>
                        <td
                            class="text-center align-middle"
                            x-text="item.prestador_servicio">
                        </td>
                        <!-- FORMATO 024 -->
                        <td class="text-center">
                            <a href="/uploads/archivos/Fo.ADMONGAS/Fo.ADMONGAS.024.doc" download>
                                <i class="ti ti-file-download text-info fs-7"></i>
                            </a>
                        </td>
                        <td class="text-center">
                        <a href="javascript:void(0)" @click="subir024(item.id)"><i class="ti ti-file-upload text-success fs-7"></i></a>
                        </td>
                        <td class="text-center">
                            <template
                                x-if="
                                    item.formato024.existe
                                ">

                                <a :href="`/uploads/${item.formato024.archivo}`" download=""><i class="ti ti-file-type-pdf text-danger fs-7"></i></a>

                            </template>
                            <template
                                x-if="!item.formato024.existe">
                               <i class="ti ti-x fs-7"></i>
                            </template>
                        </td>

                        <!-- FORMATO 025 -->

                        <td class="text-center">

                            <a href="/uploads/archivos/Fo.ADMONGAS/Fo.ADMONGAS.025.docx" download>
                                <i class="ti ti-file-download text-info fs-7"></i>
                            </a>

                        </td>

                        <td class="text-center">
                            <a href="javascript:void(0)" @click="subir025(item.id)"><i class="ti ti-file-upload text-success fs-7"></i></a>
                        </td>

                        <td class="text-center">

                            <template
                                x-if="
                                    item.formato025.existe
                                ">

                                <a :href="`/uploads/${item.formato025.archivo}`" download=""><i class="ti ti-file-type-pdf text-danger fs-7"></i></a>

                            </template>

                            <template
                                x-if="
                                    !item.formato025.existe
                                ">
                                <i class="ti ti-x fs-7"></i>

                            </template>

                        </td>

                        <td class="text-center">

                           <a @click="abrirAsea(item.id,25)"><i class="ti ti-paperclip text-primary fs-7"></i></a>

                        </td>

                        <td class="text-center">
                            <a @click="eliminar(item.id)"><i class="ti ti-trash text-danger fs-7"></i></a>
                        </td>

                    </tr>

                </template>

            </tbody>

        </table>

</div>

<!-- Modal Nuevo -->

<div
    class="modal fade"
    id="modalAgregar"
    tabindex="-1">

    <div
        class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header head-modal">

                <h5 class="modal-title">

                    Crear auditoria externa

                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <div class="mb-2">

                    <label class="form-label">
                        * Nombre del prestador de servicio:
                    </label>

                </div>

                <input
                    type="text"
                    class="form-control"
                    x-model="auditor"
                    :class="errors.auditor ? 'is-invalid' : ''"
                    @input="errors.auditor = false">

                <hr>

                <small class="fw-bold">

                    * Descarga los siguientes formatos
                    y carga cada uno al sistema

                </small>

                <div class="row mt-3">

                    <div class="col-md-6">

                        <a
                            href="/uploads/archivos/Fo.ADMONGAS/Fo.ADMONGAS.024.doc"
                            download>

                            <div
                                class="bg-light text-center p-3">

                                <i
                                    class="fa fa-file-word fa-3x">
                                </i>

                                <div class="mt-2">

                                    Fo.ADMONGAS.024

                                </div>

                            </div>

                        </a>

                    </div>

                    <div class="col-md-6">

                        <a
                            href="/uploads/archivos/Fo.ADMONGAS/Fo.ADMONGAS.025.docx"
                            download>

                            <div
                                class="bg-light text-center p-3">

                                <i
                                    class="fa fa-file-word fa-3x">
                                </i>

                                <div class="mt-2">

                                    Fo.ADMONGAS.025

                                </div>

                            </div>

                        </a>

                    </div>

                </div>

            </div>

            <div class="modal-footer">

            <button
                    class="btn bg-danger-subtle text-danger"
                    data-bs-dismiss="modal">

                    Cancelar

                </button>

                <button
                    type="button"
                    class="btn btn-success"
                    @click="guardarAuditoria()">

                    Crear auditoría

                </button>

            </div>

        </div>

    </div>

</div>

<!-- Modal -->

<div
    class="modal fade"
    id="modal024"
    tabindex="-1">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header head-modal">

                <h4 class="modal-title">
                    Fo.ADMONGAS.024 (INFORME DE AUDITORÍA)
                </h4>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <div class="mb-1">

                    <label class="form-label">
                        * DOCUMENTO INFORME DE AUDITORÍA:
                    </label>

                </div>

                <input
                    id="archivo024"
                    type="file"
                    accept=".pdf"
                    class="form-control"
                    :class="errors.archivo024 ? 'is-invalid' : ''"
                    @change="
                        archivo024 = $event.target.files[0];
                        errors.archivo024 = false;
                    ">
            </div>

            <div class="modal-footer">

             <button
                    class="btn bg-danger-subtle text-danger"
                    data-bs-dismiss="modal">

                    Cancelar

                </button>

                <button
                    type="button"
                    class="btn btn-primary"
                    @click="guardar024()">

                    Agregar archivo

                </button>

            </div>

        </div>

    </div>

</div>

<!-- Modal -->

<div
    class="modal fade"
    id="modal025"
    tabindex="-1">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header head-modal">

                <h4 class="modal-title">
                    Fo.ADMONGAS.025 (PLAN DE ATENCIÓN DE HALLAZGOS)
                </h4>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <div class="mb-1">

                    <label class="form-label">
                        * DOCUMENTO PLAN DE ATENCIÓN DE HALLAZGOS:
                    </label>

                </div>

                <input
                    id="archivo025"
                    type="file"
                    accept=".pdf"
                    class="form-control"
                    :class="errors.archivo025 ? 'is-invalid' : ''"
                    @change="
                        archivo025 = $event.target.files[0];
                        errors.archivo025 = false;
                    ">
            </div>

            <div class="modal-footer">

             <button
                    class="btn bg-danger-subtle text-danger"
                    data-bs-dismiss="modal">

                    Cancelar

                </button>

                <button
                    type="button"
                    class="btn btn-primary"
                    @click="guardar025()">

                    Agregar archivo

                </button>

            </div>

        </div>

    </div>

</div>

<!-- Modal Asea -->

<div class="modal fade" id="modalAsea" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">

      <div class="modal-header head-modal">
        <h5 class="modal-title">Ingreso a la ASEA</h5>
        <button type="button" class="btn-close"
                @click="modalAsea.hide()"></button>
      </div>

      <div class="modal-body">


        <label class="form-label">* DOCUMENTO INGRESO A LA ASEA:</label>

        <input type="file"
               id="archivoAsea"
               class="form-control mb-3"
               @change="archivoAsea = $event.target.files[0]">

        <label class="form-label">* COMENTARIO:</label>

        <textarea class="form-control mb-3"
        x-model="comentarioAsea"></textarea>

        <div class="text-end">
            <button class="btn btn-primary"
                    @click="guardarAsea">
                Agregar archivo Asea
            </button>
        </div>

        <hr>

        <table class="table table-sm table-bordered">
            <thead>
                <th class="text-center">#</th>
                <th class="text-center">Fecha</th>
                <th class="text-center">Comentario</th>
                <th><i class="ti ti-file-type-pdf text-muted fs-7" width="36"></i></th>
            </thead>
            <tbody>

                <template x-if="aseas.length === 0">
                    <tr>
                        <td colspan="4" class="text-center text-muted">
                            Sin información
                        </td>
                    </tr>
                </template>

                <template x-for="a in aseas" :key="a.id">
                    <tr>
                        <td class="align-middle text-center fw-bolder" x-text="a.id"></td>
                        <td class="align-middle text-center" x-text="a.fecha"></td>
                        <td class="align-middle text-center" x-text="a.comentario"></td>
                        <td class="text-center align-middle" width="36">
                            <a :href="`/uploads/${a.archivo}`" download>
                                <i class="ti ti-file-type-pdf text-danger fs-7"></i>
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