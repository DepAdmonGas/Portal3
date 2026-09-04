<div id="container"
data-module-station-key="sasisopa"
data-estacion-id="<?= e($estacionId ?? '') ?>"
x-data="{ ...actions(), ...auditoriaInterna()}">

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
            id="tablaAuditorias"
            class="table table-striped table-bordered mb-0 text-nowrap align-middle">

            <thead>

                <tr>

                    <th class="align-middle text-center">
                        #
                    </th>
                    <th class="align-middle">
                        Fecha
                    </th>
                    <th class="align-middle">
                        Auditor
                    </th>
                    <th
                        class="align-middle text-center"
                        colspan="3">
                        Fo.ADMONGAS.024
                        <small>
                            (INFORME DE AUDITORÍA)
                        </small>
                    </th>
                    <th class="align-middle text-center">
                        Anexos
                    </th>
                    <th
                        class="align-middle text-center"
                        colspan="3">
                        Fo.ADMONGAS.025
                        <small>
                            (PLAN DE ATENCIÓN DE HALLAZGOS)
                        </small>
                    </th>
                    <th class="align-middle text-center">
                        Anexos
                    </th>
                    <th class="align-middle text-center">
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

            <div class="modal-header head-colored-header bg-primary">

                <h5 class="modal-title text-white">
<i class="ti ti-binoculars"></i>
                    Nueva auditoría interna

                </h5>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <div class="mb-2">

                    <label class="form-label">
                        * Nombre del auditor:
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

            <div class="modal-header head-colored-header bg-primary text-white">

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

            <div class="modal-header modal-colored-header bg-primary text-white">

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

<!-- Modal Anexos -->

<div class="modal fade" id="modalAnexos" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">

      <div class="modal-header modal-colored-header bg-primary">
        <h5 class="modal-title text-white">
        <i class="ti ti-file-upload"></i>    
        Anexos
    </h5>
        <button type="button" class="btn-close btn-close-white"
                @click="modalAnexos.hide()"></button>
      </div>

      <div class="modal-body">

        <label class="form-label">* Nombre del anexo:</label>

        <select class="form-select mb-3"
                x-model="documentoAnexo">
            <option value="">Seleccione una opcion...</option>
            <option>Lista de verificación</option>
            <option>Acta de verificación</option>
            <option>Evidencia</option>
        </select>


<div class="input-group mb-2">
       <input type="file"
               id="archivoAnexo"
               class="form-control"
               @change="archivoAnexo = $event.target.files[0]">

  
            <button class="btn btn-success"
                    @click="guardarAnexo">
                    <i class="ti ti-plus"></i>
                Nuevo
            </button>

</div>
     



        <table class="table table-striped  table-bordered mt-3 text-nowrap align-middle">
            <thead>
<tr>
    <th>Documento</th>
    <th><i class="ti ti-file-type-pdf fs-7"></i></th>
</tr>

            </thead>
            <tbody>

                <template x-if="anexos.length === 0">
                    <tr>
                        <td class="text-center text-muted">
                            Sin anexos
                        </td>
                    </tr>
                </template>

                <template x-for="a in anexos" :key="a.id">
                    <tr>
                        <td class="align-middle" x-text="a.documento"></td>
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