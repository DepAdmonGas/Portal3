<div id="container"
data-module-station-key="sasisopa"
data-estacion-id="<?= e($estacionId ?? '') ?>"
x-data="{ ...actions(), ...auditoriaExterna()}">

<?php if (empty($estacionId)): ?>
 
    <div id="sasisopa-empty-message"
         class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.
    </div>

<?php else: ?>

    <div class="text-end">
        <?= 
            !empty($permisos['crear']) ? 
            '<button type="button" class="btn bg-primary-subtle text-primary" @click="abrirModalAgregar()">
            <i class="ti ti-plus"></i> Nuevo
            </button>' 
            : '' 
            ?>     
    </div>

    <div class="datatables">
    <div class="table-responsive mt-3 pb-4 overflow-x-auto overflow-hidden">

        <table
            id="tablaAuditoriasExternas"
            class="table table-striped table-bordered mb-0 text-nowrap align-middle">

            <thead>

                <tr>

                    <th class="text-center align-middle">
                        #
                    </th>
                    <th class="text-center align-middle">
                        Fecha
                    </th>
                    <th class="text-center align-middle">
                        Prestador de servicio
                    </th>
                    <th
                        class="text-center align-middle"
                        colspan="3">
                        Fo.ADMONGAS.024
                        <small>
                            (INFORME DE AUDITORÍA)
                        </small>
                    </th>
                    <th
                        class="text-center align-middle"
                        colspan="3">
                        Fo.ADMONGAS.025
                        <small>
                            (PLAN DE ATENCIÓN DE HALLAZGOS)
                        </small>
                    </th>
                    <th class="text-center align-middle">
                        ASEA
                    </th>
                    <th class="text-center align-middle">
                       <i class="ti ti-trash text-danger fs-7"></i>
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
    id="modalAgregar"
    tabindex="-1">

    <div
        class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header modal-colored-header bg-primary">

                <h5 class="modal-title text-white">
<i class="ti ti-binoculars"></i>
                    Nueva auditoria externa

                </h5>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <div class="mb-1">

                    <label class="form-label">
                        * Nombre del prestador:
                    </label>

                </div>

                <input
                    type="text"
                    class="form-control mb-3"
                    x-model="auditor"
                    :class="errors.auditor ? 'is-invalid' : ''"
                    @input="errors.auditor = false">
                <small class="form-label">

                    * Descarga los siguientes formatos
                    y carga cada uno al sistema

                </small>

                <div class="row mt-3">

                    <div class="col-md-6">

                        <a
                            href="/uploads/archivos/Fo.ADMONGAS/Fo.ADMONGAS.024.doc"
                            download>

                            <div
                                class="bg-primary-subtle text-center p-3">

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
                                class="bg-primary-subtle text-center p-3">

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
<i class="ti ti-x"></i>
                    Cancelar

                </button>

                <button
                    type="button"
                    class="btn btn-success"
                    @click="guardarAuditoria()">
<i class="ti ti-check"></i>
                    Guardar

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

            <div class="modal-header modal-colored-header bg-primary">

                <h4 class="modal-title text-white">
                    <i class="ti ti-file-upload"></i>
                    Fo.ADMONGAS.024 (INFORME DE AUDITORÍA)
                </h4>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <div class="mb-1">

                    <label class="form-label">
                        * Formato (Fo.ADMONGAS.024):
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
<i class="ti ti-x"></i>
                    Cancelar

                </button>

                <button
                    type="button"
                    class="btn btn-success"
                    @click="guardar024()">
<i class="ti ti-check"></i>
                    Guardar

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

            <div class="modal-header modal-colored-header bg-primary">

                <h4 class="modal-title text-white">
                    <i class="ti ti-file-upload"></i>
                    Fo.ADMONGAS.025 (PLAN DE ATENCIÓN DE HALLAZGOS)
                </h4>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <div class="mb-1">

                    <label class="form-label">
                        * Formato (Fo.ADMONGAS.025):
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
<i class="ti ti-x"></i>
                    Cancelar

                </button>

                <button
                    type="button"
                    class="btn btn-success"
                    @click="guardar025()">
<i class="ti ti-check"></i>
                    Guardar

                </button>

            </div>

        </div>

    </div>

</div>

<!-- Modal Asea -->

<div class="modal fade" id="modalAsea" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">

      <div class="modal-header modal-colored-header bg-primary">
        <h5 class="modal-title text-white">
<i class="ti ti-file-upload"></i>
            Ingreso de ASEA</h5>
        <button type="button" class="btn-close btn-close-white"
                @click="modalAsea.hide()"></button>
      </div>

      <div class="modal-body">


        <label class="form-label">* Documento:</label>

        <input type="file"
               id="archivoAsea"
               class="form-control mb-3"
               @change="archivoAsea = $event.target.files[0]">

        <label class="form-label">* Comentario:</label>

        <textarea class="form-control mb-3"
        x-model="comentarioAsea"></textarea>

        <div class="text-end">
            <button class="btn btn-success"
                    @click="guardarAsea">
                    <i class="ti ti-check"></i>
                Agregar
            </button>
        </div>
<div class="table-responsive mt-3">
        <table class="table table-bordered table-striped">
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
      <div class="modal-footer">
              <button
                    class="btn bg-danger-subtle text-danger"
                    data-bs-dismiss="modal">
<i class="ti ti-x"></i>
                    Cancelar

                </button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
</div>