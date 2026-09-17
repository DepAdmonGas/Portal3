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

    <div class="text-end mb-3">
        <?= 
            !empty($permisos['crear']) ? 
            '<button type="button" class="btn bg-primary-subtle text-primary" @click="abrirModalAgregar()">
            <i class="ti ti-plus"></i> Nuevo
            </button>' 
            : '' 
            ?>     
    </div>

<div class="datatables">
    <div class="table-responsive mb-3 overflow-x-auto overflow-hidden">

        <table
            id="tablaAuditorias"
            class="table table-striped table-bordered mb-0 text-nowrap align-middle">

            <thead>

                <tr>

                    <th class="align-middle text-center">
                        #
                    </th>
                    <th class="align-middle text-center">
                        Fecha
                    </th>
                    <th class="align-middle text-center">
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
                    <th class="align-middle text-center" width="96px">
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
                    <th class="align-middle text-center" width="100px">
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
        class="modal-dialog modal-xl modal-dialog-centered">

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

 <div class="row mt-4">

    <!-- Tarjeta 1: Fo.ADMONGAS.024 -->
    <div class="col-12 col-md-6 d-flex align-items-stretch mb-4">
        <a href="/uploads/archivos/Fo.ADMONGAS/Fo.ADMONGAS.024.doc" 
           download
           class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
            
            <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <!-- Icono circular (Apropiado para Word con tono primario o azul suave) -->
                    <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 60px; height: 60px;">
                        <i class="fa fa-file-word text-white fs-4"></i> 
                    </div>

                    <!-- Título a la derecha -->
                    <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                        <h4 class="fw-bold text-dark mb-0 lh-sm">
                            Fo.ADMONGAS.024
                        </h4>
                        <span class="text-muted small mt-1">Documento Word (.doc)</span>
                    </div>
                </div>
            </div>

            <!-- Pie de la tarjeta -->
            <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
                <span class="small">Descargar documento</span>
                <div class="icon-transition">
                    <i class="ti ti-download fs-5"></i>
                </div>
            </div>
        </a>
    </div>

    <!-- Tarjeta 2: Fo.ADMONGAS.025 -->
    <div class="col-12 col-md-6 d-flex align-items-stretch mb-4">
        <a href="/uploads/archivos/Fo.ADMONGAS/Fo.ADMONGAS.025.docx" 
           download
           class="card h-100 w-100 text-decoration-none card-hover overflow-hidden position-relative">
            
            <div class="card-body p-4 pb-0 mb-0 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <!-- Icono circular -->
                    <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 60px; height: 60px;">
                        <i class="fa fa-file-word text-white fs-4"></i> 
                    </div>

                    <!-- Título a la derecha -->
                    <div class="flex-grow-1 d-flex flex-column align-items-end justify-content-center text-end h-100">
                        <h4 class="fw-bold text-dark mb-0 lh-sm">
                            Fo.ADMONGAS.025
                        </h4>
                        <span class="text-muted small mt-1">Documento Word (.docx)</span>
                    </div>
                </div>
            </div>

            <!-- Pie de la tarjeta -->
            <div class="card-footer bg-transparent border-top border-light px-4 py-3 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
                <span class="small">Descargar documento</span>
                <div class="icon-transition">
                    <i class="ti ti-download fs-5"></i>
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
     


<div class="table-responsive">
        <table class="table table-striped table-bordered mt-3 text-nowrap align-middle">
            <thead>
<tr>
    <th class="text-start align-middle">Documento</th>
    <th class="text-center align-middle" width="48px"><i class="ti ti-file-type-pdf text-danger fs-7"></i></th>
</tr>

            </thead>
            <tbody>

                <template x-if="anexos.length === 0">
                    <tr>
                        <td class="text-center text-primary" colspan="2">
                            No se encontro información
                        </td>
                    </tr>
                </template>

                <template x-for="a in anexos" :key="a.id">
                    <tr>
                        <td class="text-start align-middle" x-text="a.documento"></td>
                        <td class="text-center align-middle" >
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