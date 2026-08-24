<?php

/** @var \App\Models\Usuario $user  */
?>
<div id="container" class="pb-4"
    x-data="{ ...actions(), ...permisos(<?= $user->id ?>)}">

    <?php if ($user->id == 60): ?>
        <div class="row mt-3 mb-3">
            <div class="col-3">
                <select
                    class="form-select"
                    x-model="idUsuario"
                    @change="cambiarUsuario()">
                    <option value="">Seleccione un usuario</option>

                    <template x-for="persona in personal" :key="persona.id">

                        <option
                            :value="persona.id"
                            x-text="persona.nombre"></option>

                    </template>
                </select>
            </div>
        </div>
    <?php endif; ?>

    <!-- Loading -->

    <template x-if="loading && estaciones.length === 0">

        <div class="text-center py-5">

            <div
                class="spinner-border text-primary"
                role="status"></div>

            <div class="mt-2 text-muted">
                Cargando permisos...
            </div>

        </div>

    </template>


    <!-- Error -->

    <template x-if="error">

        <div class="alert alert-danger">

            <i class="fa-solid fa-triangle-exclamation"></i>

            <span x-text="error"></span>

        </div>

    </template>


    <!-- Estaciones -->

    <template x-for="estacion in estaciones" :key="estacion.id">

        <div class="table-responsive mb-4">

            <table
                class="table table-sm table-bordered table-striped table-hover"
                width="100%">

                <thead>

                    <tr>

                        <th
                            class="text-center p-2 bg-info text-white"
                            colspan="9">

                            <span x-text="estacion.nombre"></span>

                        </th>

                    </tr>

                    <tr class="bg-primary text-white font-weight-bold">

                        <th class="text-center">
                            Nivel de gobierno
                        </th>

                        <th class="text-center">
                            Dependencia
                        </th>

                        <th class="text-center">
                            Permiso
                        </th>

                        <th class="text-center">
                            Vigencia
                        </th>

                        <th class="text-center">
                            Fecha emisión
                        </th>

                        <th class="text-center">
                            Fecha vencimiento
                        </th>

                        <th class="text-center">
                            Acuse
                        </th>

                        <th class="text-center">
                            Requisito legal
                        </th>

                        <th class="text-center">

                            <i class="ti ti-file-dots fs-7 text-muted"></i>

                        </th>

                    </tr>

                </thead>


                <tbody>

                    <template
                        x-for="requisito in estacion.requisitos"
                        :key="requisito.id">

                        <tr
                            class="table-tr"
                            :class="{
                                'table-warning': requisito.table_warning
                            }">

                            <td>
                                <span
                                    x-text="requisito.nivel_gobierno || ''"></span>
                            </td>


                            <td>
                                <span
                                    x-text="requisito.dependencia || ''"></span>
                            </td>


                            <td>
                                <span
                                    x-text="requisito.permiso || ''"></span>
                            </td>


                            <td>
                                <span
                                    x-text="requisito.vigencia || ''"></span>
                            </td>


                            <td>
                                <span
                                    x-text="requisito.fecha_emision_texto"></span>
                            </td>


                            <td>
                                <span
                                    x-text="requisito.fecha_vencimiento_texto"></span>
                            </td>


                            <!-- Acuse -->

                            <td
                                class="text-center align-middle">

                                <template
                                    x-if="requisito.acuse_url">

                                    <a class="pointer"
                                        @click="download('requisitos-legales', requisito.acuse_url)"
                                        target="_blank"
                                        download>

                                        <i class="ti ti-file-type-pdf fs-7 text-danger"></i>

                                    </a>

                                </template>


                                <template
                                    x-if="!requisito.acuse_url">

                                    <i class="ti ti-x fs-7 text-muted"></i>

                                </template>

                            </td>


                            <!-- Requisito legal -->

                            <td
                                class="text-center align-middle">

                                <template
                                    x-if="requisito.requisitolegal_url">

                                    <a class="pointer"
                                        @click="download('requisitos-legales', requisito.requisitolegal_url)"
                                        target="_blank"
                                        download>

                                        <i class="ti ti-file-type-pdf fs-7 text-danger"></i>

                                    </a>

                                </template>


                                <template
                                    x-if="!requisito.requisitolegal_url">

                                    <i class="ti ti-x fs-6 text-muted"></i>

                                </template>

                            </td>


                            <!-- Documentos -->

                            <td
                                class="text-center align-middle">
                                <a class="pointer" @click="openHistorial(requisito.id, requisito.permiso, requisito.vigencia, estacion.id, estacion.nombre)">
                                    <i class="ti ti-file-dots fs-7"></i>
                                </a>

                            </td>

                        </tr>

                    </template>


                    <!-- Sin registros -->

                    <template
                        x-if="
                            !loading &&
                            estacion.requisitos.length === 0
                        ">

                        <tr>

                            <td
                                colspan="9"
                                class="text-center text-muted py-3">

                                No hay permisos registrados
                                para este usuario.

                            </td>

                        </tr>

                    </template>

                </tbody>

            </table>

        </div>

    </template>

    <!-- MODAL HISTORIAL -->
    <div class="modal fade"
        id="modalHistorial"
        x-ref="modalHistorial"
        @hidden.bs.modal="resetHistorialModal()"
        tabindex="-1">

        <div class="modal-dialog modal-dialog-scrollable modal-xl modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header modal-colored-header bg-primary text-white">
                    <h4 class="modal-title text-white">
                        <i class="ti ti-history"></i>
                        <label class="modal-title text-white" x-text="historialTitle || 'Historial'">
                        </label>
                        <span class="mb-1 badge rounded-pill text-bg-info" x-text="nombreEstacion"></span>
                    </h4>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <div class="table-responsive" x-show="!showHistorialForm" x-transition>

                        <div class="d-flex justify-content-end mb-3">
                            <button class="btn bg-primary-subtle text-primary"
                                @click="showHistorialForm = true; resetHistorialForm()">
                                <i class="ti ti-plus"></i> Nuevo
                            </button>
                        </div>

                        <div class="table-responsive overflow-x-auto overflow-y-hidden">
                            <table class="table table-bordered align-middle">
                                <thead>
                                    <tr class="text-center">
                                        <th>Fecha emisión</th>
                                        <th>Fecha vencimiento</th>
                                        <th>Acuse</th>
                                        <th>Requisito legal</th>
                                        <th><a class="text-muted"><i class="ti ti-edit fs-6"></i></a></th>
                                        <th><a class="text-muted"><i class="ti ti-trash fs-6"></i></a></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="row in historialRows" :key="row.id">
                                        <tr class="text-center">
                                            <td x-text="row.fecha_emision || 'S/I'"></td>
                                            <td x-text="row.fecha_vencimiento || 'S/I'"></td>
                                            <td>
                                                <template x-if="row.acusepdf">
                                                    <a class="pointer"
                                                        @click="download('requisitos-legales', row.acusepdf)">
                                                        <i class="ti ti-download text-success fs-6"></i>
                                                    </a>
                                                </template>
                                                <template x-if="!row.acusepdf">
                                                    <i class="ti ti-x text-danger fs-6"></i>
                                                </template>
                                            </td>
                                            <td>
                                                <template x-if="row.requisitolegalpdf">
                                                    <a class="pointer"
                                                        @click="download('requisitos-legales', row.requisitolegalpdf)">
                                                        <i class="ti ti-download text-success fs-6"></i>
                                                    </a>
                                                </template>
                                                <template x-if="!row.requisitolegalpdf">
                                                    <i class="ti ti-x text-danger fs-6"></i>
                                                </template>
                                            </td>
                                            <td>
                                                <a class="pointer" @click="editHistorialRow(row)"><i class="ti ti-edit fs-6"></i></a>
                                            </td>
                                            <td><a class="pointer" @click="deleteHistorialRow(row)"><i class="ti ti-trash text-danger fs-6"></i></a></td>
                                        </tr>
                                    </template>
                                    <tr x-show="historialRows.length === 0">
                                        <td colspan="5" class="text-center">No se encontró información</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                    </div>

                    <div x-show="showHistorialForm" x-transition>

                        <h4 class="mb-3" x-text="historialForm.id ? 'Editar registro' : 'Nuevo registro'"></h4>

                        <div class="row">
                            <div class="col-lg-6 col-md-12">
                                <label class="form-label">Fecha de emisión</label>
                                <input type="date"
                                    class="form-control"
                                    x-model="historialForm.fecha_emision"
                                    @change="historialErrors.fecha_emision = false"
                                    :class="historialErrors.fecha_emision ? 'is-invalid' : ''">
                            </div>
                            <div class="col-lg-6 col-md-12">
                                <label class="form-label">Fecha de vencimiento</label>
                                <input type="date"
                                    class="form-control"
                                    x-model="historialForm.fecha_vencimiento">
                            </div>
                            <div class="col-lg-6 col-md-12">
                                <label class="form-label mt-3">Acuse PDF</label>
                                <input class="form-control" type="file" x-ref="historialAcusePDF">
                            </div>
                            <div class="col-lg-6 col-md-12">
                                <label class="form-label mt-3">Requisito Legal PDF</label>
                                <input class="form-control" type="file" x-ref="historialRequisitoPDF">
                            </div>
                        </div>

                        <div class="modal-footer">

                            <button type="button"
                                class="btn bg-danger-subtle text-danger"
                                @click="showHistorialForm = false; resetHistorialForm()">
                                <i class="ti ti-x"></i> Cancelar
                            </button>

                            <button type="button"
                                class="btn btn-success"
                                @click="submitHistorial()"
                                :disabled="loading">
                                <i class="ti ti-check"></i>
                                <span x-text="historialForm.id ? 'Actualizar' : 'Guardar'"></span>
                            </button>
                        </div>

                    </div>

                </div>


            </div>
        </div>
    </div>

</div>