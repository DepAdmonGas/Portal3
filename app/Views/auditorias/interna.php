<div id="container"
x-data="{ ...actions(), ...auditoriaInterna()}">

    <div class="text-end">
        <?= 
            !empty($permisos['crear']) ? 
            '<button type="button" class="btn bg-primary-subtle text-primary" @click="abrirModalAgregar()">
            <i class="ti ti-plus"></i> Nuevo
            </button>' 
            : '' 
            ?>     
    </div>

<div class="table-responsive mt-3">

        <table
            class="table table-bordered table-striped ">

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
                            x-text="item.auditor">
                        </td>
                        <!-- FORMATO 024 -->
                        <td class="text-center align-middle">
                            <a href="/uploads/archivos/Fo.ADMONGAS/Fo.ADMONGAS.024.doc" download>
                                <i class="ti ti-file-download text-info fs-7"></i>
                            </a>
                        </td>
                        <td class="text-center align-middle">
                        <a href="javascript:void(0)" @click="subir024(item.id)"><i class="ti ti-file-upload text-success fs-7"></i></a>
                        </td>
                        <td class="text-center align-middle">
                            <template
                                x-if="
                                    item.formato024.existe
                                ">

                                <a :href="`/uploads/${item.formato024.archivo}`" download=""><i class="ti ti-download text-danger fs-7 "></i></a>

                            </template>
                            <template
                                x-if="!item.formato024.existe">
                               <i class="ti ti-x fs-7"></i>
                            </template>
                        </td>
                        <td class="text-center align-middle">

                        <a @click="abrirAnexos(item.id,24)"><i class=" pointer ti ti-paperclip text-primary fs-7"></i></a>

                        </td>

                        <!-- FORMATO 025 -->

                        <td class="text-center align-middle">

                            <a href="/uploads/archivos/Fo.ADMONGAS/Fo.ADMONGAS.025.docx" download>
                                <i class="ti ti-file-download text-info fs-7"></i>
                            </a>

                        </td>

                        <td class="text-center align-middle">
                            <a href="javascript:void(0)" @click="subir025(item.id)"><i class="ti ti-file-upload text-success fs-7"></i></a>
                        </td>

                        <td class="text-center align-middle">

                            <template
                                x-if="
                                    item.formato025.existe
                                ">
                                <a :href="`/uploads/${item.formato025.archivo}`" download=""><i class="ti ti-download text-danger fs-7"></i></a>

                            </template>

                            <template
                                x-if="
                                    !item.formato025.existe
                                ">
                                <i class="ti ti-x fs-7"></i>

                            </template>

                        </td>

                        <td class="text-center align-middle">

                           <a @click="abrirAnexos(item.id,25)"><i class="pointer ti ti-paperclip text-primary fs-7"></i></a>

                        </td>

                        <td class="text-center align-middle">
                            <a @click="eliminar(item.id)"><i class="pointer ti ti-trash text-danger fs-7"></i></a>
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

        <select class="form-select mb-2"
                x-model="documentoAnexo">
            <option value="">Seleccione una opcion...</option>
            <option>Lista de verificación</option>
            <option>Acta de verificación</option>
            <option>Evidencia</option>
        </select>

        <input type="file"
               id="archivoAnexo"
               class="form-control mb-3"
               @change="archivoAnexo = $event.target.files[0]">

        <div class="text-end">
            <button class="btn btn-success"
                    @click="guardarAnexo">
                    <i class="ti ti-check"></i>
                Agregar
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

</div>